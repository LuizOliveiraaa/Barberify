<?php

declare(strict_types=1);

namespace Barberify\Auth;

use Barberify\Core\Audit;
use Barberify\Core\Config;
use Barberify\Core\Contexto;
use Barberify\Core\Db;
use Barberify\Core\Passwords;
use Barberify\Core\SessionStore;
use Barberify\Core\Throttle;

final class AuthEmpresa
{
    private static ?self $instancia = null;

    private SessionStore $sessao;
    private ?array $usuario = null;
    private ?array $empresa = null;
    private ?int $unidadeId = null;
    private bool $carregado = false;

    private function __construct()
    {
        $this->sessao = new SessionStore('empresa');
    }

    public static function instancia(): self
    {
        return self::$instancia ??= new self();
    }

    public function sessao(): SessionStore
    {
        return $this->sessao;
    }

    /** @return array{ok:bool,erro?:string} */
    public function tentarLogin(string $email, string $senha): array
    {
        $email = mb_strtolower(trim($email));
        $ip    = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $limite = (int) Config::get('app.login.max_tentativas', 5);
        $janela = (int) Config::get('app.login.janela_minutos', 15);

        if (Throttle::bloqueado('empresa', $email, $ip, $limite, $janela)) {
            return ['ok' => false, 'erro' => 'Muitas tentativas. Aguarde alguns minutos.'];
        }

        $candidatos = Db::select(
            'SELECT u.*, e.nome_fantasia, e.status AS empresa_status, e.excluido_em AS empresa_excluida
               FROM usuarios_empresa u
               JOIN empresas e ON e.id = u.empresa_id
              WHERE u.email = :email AND u.excluido_em IS NULL
              ORDER BY u.id',
            [':email' => $email]
        );

        foreach ($candidatos as $usuario) {
            if (!Passwords::verificar($senha, (string) $usuario['senha_hash'])) {
                continue;
            }

            if ((string) $usuario['status'] !== 'ativo') {
                Throttle::registrar('empresa', $email, $ip, false);

                return ['ok' => false, 'erro' => 'Usuário inativo. Procure o proprietário da barbearia.'];
            }

            if ((string) $usuario['empresa_status'] === 'cancelada' || $usuario['empresa_excluida'] !== null) {
                return ['ok' => false, 'erro' => 'Esta barbearia não está mais ativa na plataforma.'];
            }

            if ($usuario['bloqueado_ate'] !== null && strtotime((string) $usuario['bloqueado_ate']) > time()) {
                return ['ok' => false, 'erro' => 'Conta temporariamente bloqueada por tentativas inválidas.'];
            }

            $unidade = Db::um(
                'SELECT id FROM unidades WHERE empresa_id = :empresa AND status = :status AND excluido_em IS NULL ORDER BY id LIMIT 1',
                [':empresa' => (int) $usuario['empresa_id'], ':status' => 'ativa']
            );

            Throttle::limpar('empresa', $email, $ip);

            Db::atualizar('usuarios_empresa', [
                'tentativas_falhas' => 0,
                'bloqueado_ate'     => null,
                'ultimo_acesso'     => now(),
            ], 'id = :id', [':id' => (int) $usuario['id']]);

            if (Passwords::precisaRehash((string) $usuario['senha_hash'])) {
                Db::atualizar('usuarios_empresa', ['senha_hash' => Passwords::hash($senha)], 'id = :id', [':id' => (int) $usuario['id']]);
            }

            $this->sessao->iniciar(
                (int) $usuario['id'],
                (int) $usuario['empresa_id'],
                $unidade === null ? null : (int) $unidade['id']
            );

            Audit::registrar(
                'login_sucesso',
                'empresa',
                'usuarios_empresa',
                (int) $usuario['id'],
                null,
                null,
                'sucesso',
                (int) $usuario['empresa_id'],
                'empresa',
                (int) $usuario['id']
            );

            return ['ok' => true];
        }

        Throttle::registrar('empresa', $email, $ip, false);
        Audit::registrar('login_falha', 'empresa', 'usuarios_empresa', null, null, ['email' => $email], 'negado', null, 'empresa', null);

        return ['ok' => false, 'erro' => 'E-mail ou senha inválidos.'];
    }

    public function autenticado(): bool
    {
        return $this->usuario() !== null;
    }

    public function usuario(): ?array
    {
        if ($this->carregado) {
            return $this->usuario;
        }

        $this->carregado = true;
        $registro = $this->sessao->registro();

        if ($registro === null) {
            return null;
        }

        $usuario = Db::um(
            'SELECT u.*, e.nome_fantasia, e.slug AS empresa_slug, e.status AS empresa_status,
                    e.excluido_em AS empresa_excluida, e.id AS empresa_id_confirmado
               FROM usuarios_empresa u
               JOIN empresas e ON e.id = u.empresa_id
              WHERE u.id = :id AND u.empresa_id = :empresa AND u.excluido_em IS NULL AND u.status = :status',
            [
                ':id'      => (int) $registro['usuario_id'],
                ':empresa' => (int) $registro['empresa_id'],
                ':status'  => 'ativo',
            ]
        );

        if ($usuario === null || (string) $usuario['empresa_status'] === 'cancelada' || $usuario['empresa_excluida'] !== null) {
            $this->sessao->encerrar('contexto_invalido');
            $this->usuario = null;

            return null;
        }

        $this->usuario  = $usuario;
        $this->empresa  = [
            'id'            => (int) $usuario['empresa_id'],
            'nome_fantasia' => (string) $usuario['nome_fantasia'],
            'slug'          => (string) $usuario['empresa_slug'],
            'status'        => (string) $usuario['empresa_status'],
        ];
        $this->unidadeId = $registro['unidade_ativa_id'] !== null ? (int) $registro['unidade_ativa_id'] : null;

        // Unidade ativa é revalidada contra o banco: nunca confiar só na sessão.
        if ($this->unidadeId !== null) {
            $unidade = Db::um(
                'SELECT id FROM unidades WHERE id = :id AND empresa_id = :empresa AND excluido_em IS NULL',
                [':id' => $this->unidadeId, ':empresa' => (int) $usuario['empresa_id']]
            );

            if ($unidade === null) {
                $this->unidadeId = null;
            }
        }

        Contexto::definir(
            $usuario,
            $this->empresa,
            $this->unidadeId,
            Permissions::empresa((int) $usuario['id'], (int) $usuario['empresa_id'])
        );

        return $usuario;
    }

    public function id(): ?int
    {
        return $this->usuario === null ? null : (int) $this->usuario['id'];
    }

    public function empresa(): ?array
    {
        $this->usuario();

        return $this->empresa;
    }

    public function empresaId(): ?int
    {
        return $this->empresa === null ? null : (int) $this->empresa['id'];
    }

    public function unidadeId(): ?int
    {
        $this->usuario();

        return $this->unidadeId;
    }

    public function ehProprietario(): bool
    {
        return $this->usuario !== null && (int) $this->usuario['is_proprietario'] === 1;
    }

    public function sair(): void
    {
        $id = $this->id();
        $empresaId = $this->empresaId();

        if ($id !== null) {
            Audit::registrar('logout', 'empresa', 'usuarios_empresa', $id, null, null, 'sucesso', $empresaId, 'empresa', $id);
        }

        $this->sessao->encerrar();
        Contexto::limpar();
        $this->usuario = null;
        $this->empresa = null;
        $this->carregado = true;
    }
}
