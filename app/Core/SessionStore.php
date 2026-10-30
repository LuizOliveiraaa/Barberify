<?php

declare(strict_types=1);

namespace Barberify\Core;

final class SessionStore
{
    private const TABELAS = [
        'control' => ['tabela' => 'control_sessoes', 'coluna' => 'usuario_id'],
        'empresa' => ['tabela' => 'usuario_sessoes', 'coluna' => 'usuario_id'],
        'cliente' => ['tabela' => 'conta_cliente_sessoes', 'coluna' => 'conta_cliente_id'],
    ];

    private ?string $token = null;
    private ?array $registro = null;

    public function __construct(private readonly string $realm)
    {
        if (!isset(self::TABELAS[$realm])) {
            throw new \InvalidArgumentException('Realm inválido: ' . $realm);
        }
    }

    public function realm(): string
    {
        return $this->realm;
    }

    private function tabela(): string
    {
        return self::TABELAS[$this->realm]['tabela'];
    }

    private function colunaUsuario(): string
    {
        return self::TABELAS[$this->realm]['coluna'];
    }

    private function nomeCookie(): string
    {
        return (string) Config::get('app.sessao.' . $this->realm . '.cookie', 'bf_' . $this->realm);
    }

    private function sameSite(): string
    {
        return (string) Config::get('app.sessao.' . $this->realm . '.same_site', 'Lax');
    }

    public function token(): ?string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $cookie = $_COOKIE[$this->nomeCookie()] ?? null;

        if (!is_string($cookie) || !preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            return null;
        }

        $this->token = $cookie;

        return $this->token;
    }

    /** Cria a sessão e regenera o cookie (previne fixação de sessão). */
    public function iniciar(int $usuarioId, ?int $empresaId = null, ?int $unidadeId = null, string $estado = 'ativa'): void
    {
        $this->encerrarRegistroAtual();

        $token = bin2hex(random_bytes(32));
        $cfg   = Config::get('app.sessao.' . $this->realm, []);

        $dados = [
            $this->colunaUsuario() => $usuarioId,
            'token_hash'           => hash('sha256', $token),
            'ip'                   => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'user_agent'           => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'expira_em'            => date('Y-m-d H:i:s', time() + (int) ($cfg['absoluto'] ?? 28800)),
            'ultimo_uso'           => now(),
            'criado_em'            => now(),
        ];

        if ($this->realm === 'empresa') {
            $dados['empresa_id']      = $empresaId;
            $dados['unidade_ativa_id'] = $unidadeId;
        }

        if ($this->realm === 'control') {
            $dados['estado'] = $estado;
        }

        $id = Db::inserir($this->tabela(), $dados);

        $this->token    = $token;
        $this->registro = ['id' => $id] + $dados;

        $this->gravarCookie($token, (int) ($cfg['absoluto'] ?? 28800));
    }

    public function usuarioId(): ?int
    {
        $registro = $this->registro();

        return $registro === null ? null : (int) $registro[$this->colunaUsuario()];
    }

    public function registro(): ?array
    {
        if ($this->registro !== null) {
            return $this->registro;
        }

        $token = $this->token();
        if ($token === null) {
            return null;
        }

        $registro = Db::um(
            sprintf(
                'SELECT * FROM %s WHERE token_hash = :hash AND revogada_em IS NULL AND expira_em > :agora LIMIT 1',
                $this->tabela()
            ),
            [':hash' => hash('sha256', $token), ':agora' => now()]
        );

        if ($registro === null) {
            $this->limparCookie();
            $this->token = null;

            return null;
        }

        $this->registro = $registro;

        return $registro;
    }

    /** Renova a janela deslizante sem trocar o token. */
    public function renovar(): void
    {
        $registro = $this->registro();
        if ($registro === null) {
            return;
        }

        $cfg       = Config::get('app.sessao.' . $this->realm, []);
        $inativo   = (int) ($cfg['inativo'] ?? 1800);
        $ultimoUso = strtotime((string) $registro['ultimo_uso']) ?: time();

        if (time() - $ultimoUso < 60) {
            return;
        }

        $expira = min(
            strtotime((string) $registro['expira_em']) ?: time(),
            time() + $inativo
        );

        Db::executar(
            sprintf('UPDATE %s SET ultimo_uso = :uso, expira_em = :exp WHERE id = :id', $this->tabela()),
            [':uso' => now(), ':exp' => date('Y-m-d H:i:s', $expira), ':id' => (int) $registro['id']]
        );
    }

    public function definirEstado(string $estado): void
    {
        $registro = $this->registro();
        if ($registro === null || $this->realm !== 'control') {
            return;
        }

        Db::executar('UPDATE control_sessoes SET estado = :e WHERE id = :id', [
            ':e'  => $estado,
            ':id' => (int) $registro['id'],
        ]);

        $this->registro['estado'] = $estado;
    }

    public function estado(): string
    {
        $registro = $this->registro();

        return (string) ($registro['estado'] ?? 'ativa');
    }

    public function encerrar(string $motivo = 'logout'): void
    {
        $this->encerrarRegistroAtual($motivo);
        $this->limparCookie();
        $this->token    = null;
        $this->registro = null;
    }

    public function revogarTodas(int $usuarioId, string $motivo = 'revogacao'): int
    {
        $sql = sprintf(
            'UPDATE %s SET revogada_em = :agora, motivo_revogacao = :motivo WHERE %s = :uid AND revogada_em IS NULL',
            $this->tabela(),
            $this->colunaUsuario()
        );

        return Db::executar($sql, [':agora' => now(), ':motivo' => $motivo, ':uid' => $usuarioId])->rowCount();
    }

    private function encerrarRegistroAtual(string $motivo = 'logout'): void
    {
        $token = $this->token();
        if ($token === null) {
            return;
        }

        Db::executar(
            sprintf('UPDATE %s SET revogada_em = :agora, motivo_revogacao = :motivo WHERE token_hash = :hash AND revogada_em IS NULL', $this->tabela()),
            [':agora' => now(), ':motivo' => $motivo, ':hash' => hash('sha256', $token)]
        );
    }

    private function gravarCookie(string $token, int $duracao): void
    {
        $opcoes = [
            'expires'  => time() + $duracao,
            'path'     => '/',
            'secure'   => (bool) Config::get('app.https', false),
            'httponly' => true,
            'samesite' => $this->sameSite(),
        ];

        setcookie($this->nomeCookie(), $token, $opcoes);
        $_COOKIE[$this->nomeCookie()] = $token;
    }

    private function limparCookie(): void
    {
        setcookie($this->nomeCookie(), '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => (bool) Config::get('app.https', false),
            'httponly' => true,
            'samesite' => $this->sameSite(),
        ]);

        unset($_COOKIE[$this->nomeCookie()]);
    }
}
