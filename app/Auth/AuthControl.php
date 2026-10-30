<?php

declare(strict_types=1);

namespace Barberify\Auth;

use Barberify\Core\Audit;
use Barberify\Core\Config;
use Barberify\Core\Crypto;
use Barberify\Core\Db;
use Barberify\Core\Passwords;
use Barberify\Core\SessionStore;
use Barberify\Core\Throttle;
use Barberify\Core\Totp;
use Barberify\Core\Validator;

final class AuthControl
{
    private static ?self $instancia = null;

    private SessionStore $sessao;
    private ?array $usuario = null;
    private bool $carregado = false;

    private function __construct()
    {
        $this->sessao = new SessionStore('control');
    }

    public static function instancia(): self
    {
        return self::$instancia ??= new self();
    }

    public function sessao(): SessionStore
    {
        return $this->sessao;
    }

    /**
     * @return array{ok:bool,erro?:string,2fa?:bool,usuario_id?:int}
     */
    public function tentarLogin(string $email, string $senha): array
    {
        $email = mb_strtolower(trim($email));
        $ip    = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        $limite  = (int) Config::get('app.login.max_tentativas', 5);
        $janela  = (int) Config::get('app.login.janela_minutos', 15);

        if (Throttle::bloqueado('control', $email, $ip, $limite, $janela)) {
            Audit::registrar('login_bloqueado', 'control', 'control_usuarios', null, null, null, 'negado', null, 'control', null);

            return ['ok' => false, 'erro' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'];
        }

        $usuario = Db::um(
            'SELECT * FROM control_usuarios WHERE email = :email AND excluido_em IS NULL LIMIT 1',
            [':email' => $email]
        );

        if ($usuario === null) {
            Throttle::registrar('control', $email, $ip, false);
            Audit::registrar('login_falha', 'control', 'control_usuarios', null, null, ['motivo' => 'inexistente'], 'negado', null, 'control', null);

            return ['ok' => false, 'erro' => 'E-mail ou senha inválidos.'];
        }

        if ((string) $usuario['status'] !== 'ativo') {
            Throttle::registrar('control', $email, $ip, false);
            Audit::registrar('login_falha', 'control', 'control_usuarios', (int) $usuario['id'], null, ['motivo' => $usuario['status']], 'negado', null, 'control', (int) $usuario['id']);

            return ['ok' => false, 'erro' => 'Esta conta está inativa. Procure o proprietário da plataforma.'];
        }

        if ($usuario['bloqueado_ate'] !== null && strtotime((string) $usuario['bloqueado_ate']) > time()) {
            return ['ok' => false, 'erro' => 'Conta temporariamente bloqueada por tentativas inválidas.'];
        }

        if (!Passwords::verificar($senha, (string) $usuario['senha_hash'])) {
            Throttle::registrar('control', $email, $ip, false);

            $falhas = (int) $usuario['tentativas_falhas'] + 1;
            $dados  = ['tentativas_falhas' => $falhas];

            if ($falhas >= $limite) {
                $dados['bloqueado_ate'] = date('Y-m-d H:i:s', time() + ((int) Config::get('app.login.bloqueio_minutos', 15) * 60));
                $dados['tentativas_falhas'] = 0;
            }

            Db::atualizar('control_usuarios', $dados, 'id = :id', [':id' => (int) $usuario['id']]);
            Audit::registrar('login_falha', 'control', 'control_usuarios', (int) $usuario['id'], null, ['motivo' => 'senha'], 'negado', null, 'control', (int) $usuario['id']);

            return ['ok' => false, 'erro' => 'E-mail ou senha inválidos.'];
        }

        Throttle::limpar('control', $email, $ip);

        if (Passwords::precisaRehash((string) $usuario['senha_hash'])) {
            Db::atualizar('control_usuarios', ['senha_hash' => Passwords::hash($senha)], 'id = :id', [':id' => (int) $usuario['id']]);
        }

        Db::atualizar('control_usuarios', [
            'tentativas_falhas' => 0,
            'bloqueado_ate'     => null,
            'ultimo_acesso'     => now(),
        ], 'id = :id', [':id' => (int) $usuario['id']]);

        $precisa2fa = (int) $usuario['totp_ativo'] === 1;

        $this->sessao->iniciar((int) $usuario['id'], null, null, $precisa2fa ? 'pendente_2fa' : 'ativa');
        $this->usuario  = $usuario;
        $this->carregado = true;

        Audit::registrar('login_sucesso', 'control', 'control_usuarios', (int) $usuario['id'], null, ['2fa' => $precisa2fa], 'sucesso', null, 'control', (int) $usuario['id']);

        return ['ok' => true, '2fa' => $precisa2fa, 'usuario_id' => (int) $usuario['id']];
    }

    public function verificarSegundoFator(string $codigo): bool
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return false;
        }

        if ($this->sessao->estado() !== 'pendente_2fa') {
            return true;
        }

        $codigo = preg_replace('/\D+/', '', $codigo) ?? '';
        $segredo = Crypto::decifrar((string) ($usuario['totp_segredo'] ?? ''));

        $valido = false;

        if ($segredo !== null && Totp::verificar($segredo, $codigo)) {
            $valido = true;
        } else {
            $registro = Db::um(
                'SELECT id FROM control_codigos_recuperacao WHERE usuario_id = :uid AND codigo_hash = :hash AND usado_em IS NULL LIMIT 1',
                [':uid' => (int) $usuario['id'], ':hash' => hash('sha256', mb_strtoupper($codigo))]
            );

            if ($registro !== null) {
                Db::atualizar('control_codigos_recuperacao', ['usado_em' => now()], 'id = :id', [':id' => (int) $registro['id']]);
                $valido = true;
            }
        }

        Audit::registrar(
            $valido ? '2fa_sucesso' : '2fa_falha',
            'control',
            'control_usuarios',
            (int) $usuario['id'],
            null,
            null,
            $valido ? 'sucesso' : 'negado',
            null,
            'control',
            (int) $usuario['id']
        );

        if ($valido) {
            $this->sessao->definirEstado('ativa');
        } else {
            Throttle::registrar('control', (string) $usuario['email'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''), false);
        }

        return $valido;
    }

    public function autenticado(): bool
    {
        $usuario = $this->usuario();

        return $usuario !== null && $this->sessao->estado() === 'ativa';
    }

    public function pendente2fa(): bool
    {
        return $this->usuario() !== null && $this->sessao->estado() === 'pendente_2fa';
    }

    public function usuario(): ?array
    {
        if ($this->carregado) {
            return $this->usuario;
        }

        $this->carregado = true;
        $registro = $this->sessao->registro();

        if ($registro === null) {
            return $this->usuario = null;
        }

        $this->usuario = Db::um(
            'SELECT * FROM control_usuarios WHERE id = :id AND excluido_em IS NULL AND status = :status',
            [':id' => (int) $registro['usuario_id'], ':status' => 'ativo']
        );

        if ($this->usuario === null) {
            $this->sessao->encerrar('usuario_invalido');
        }

        return $this->usuario;
    }

    public function id(): ?int
    {
        $usuario = $this->usuario();

        return $usuario === null ? null : (int) $usuario['id'];
    }

    public function ehProprietario(): bool
    {
        $usuario = $this->usuario();

        return $usuario !== null && (int) $usuario['is_proprietario'] === 1;
    }

    public function pode(string $chave): bool
    {
        if (!$this->autenticado()) {
            return false;
        }

        if ($this->ehProprietario()) {
            return true;
        }

        return in_array($chave, Permissions::control((int) $this->id()), true);
    }

    public function sair(): void
    {
        $id = $this->id();

        if ($id !== null) {
            Audit::registrar('logout', 'control', 'control_usuarios', $id, null, null, 'sucesso', null, 'control', $id);
        }

        $this->sessao->encerrar();
        $this->usuario = null;
        $this->carregado = true;
    }

    public function exigirSenha(string $senha): bool
    {
        $usuario = $this->usuario();

        return $usuario !== null && Passwords::verificar($senha, (string) $usuario['senha_hash']);
    }

    public function trocarSenha(string $senhaAtual, string $nova): array
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return ['ok' => false, 'erro' => 'Sessão inválida.'];
        }

        if (!Passwords::verificar($senhaAtual, (string) $usuario['senha_hash'])) {
            return ['ok' => false, 'erro' => 'A senha atual não confere.'];
        }

        $erro = Validator::senhaForte($nova, (int) Config::get('app.login.senha_control', 12));
        if ($erro !== null) {
            return ['ok' => false, 'erro' => $erro];
        }

        Db::atualizar('control_usuarios', [
            'senha_hash'   => Passwords::hash($nova),
            'trocar_senha' => 0,
        ], 'id = :id', [':id' => (int) $usuario['id']]);

        $this->sessao->revogarTodas((int) $usuario['id'], 'troca_senha');
        Audit::registrar('senha_alterada', 'control', 'control_usuarios', (int) $usuario['id'], null, null, 'sucesso', null, 'control', (int) $usuario['id']);

        return ['ok' => true];
    }
}
