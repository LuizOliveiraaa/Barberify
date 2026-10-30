<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Request
{
    private static string $idRequisicao = '';

    /** @var array<string,string|array|null> */
    private array $query;
    /** @var array<string,mixed> */
    private array $body;
    /** @var array<string,mixed> */
    private array $files;

    public function __construct(
        private readonly string $metodo,
        private readonly string $caminho,
        private readonly array $cabecalhos,
        private readonly string $ip
    ) {
        $this->query = $_GET;
        $this->body  = $_POST;
        $this->files = $_FILES;
    }

    public static function capturar(): self
    {
        self::$idRequisicao = self::$idRequisicao !== ''
            ? self::$idRequisicao
            : substr(bin2hex(random_bytes(13)), 0, 26);

        $caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $caminho = '/' . trim($caminho, '/');

        if ($caminho === '/') {
            $caminho = '/';
        }

        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($metodo === 'POST' && isset($_POST['_method'])) {
            $metodo = strtoupper((string) $_POST['_method']);
        }

        $cabecalhos = [];
        foreach ($_SERVER as $chave => $valor) {
            if (str_starts_with($chave, 'HTTP_')) {
                $cabecalhos[str_replace('_', '-', strtolower(substr($chave, 5)))] = (string) $valor;
            }
        }

        return new self($metodo, $caminho, $cabecalhos, self::detectarIp());
    }

    public static function idRequisicao(): string
    {
        return self::$idRequisicao !== '' ? self::$idRequisicao : '-';
    }

    public function metodo(): string
    {
        return $this->metodo;
    }

    public function caminho(): string
    {
        return $this->caminho;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function agente(): string
    {
        return mb_substr((string) ($this->cabecalhos['user-agent'] ?? ''), 0, 255);
    }

    public function cabecalho(string $nome): ?string
    {
        return $this->cabecalhos[strtolower($nome)] ?? null;
    }

    public function entrada(string $chave, mixed $padrao = null): mixed
    {
        return $this->body[$chave] ?? $this->query[$chave] ?? $padrao;
    }

    public function texto(string $chave, string $padrao = ''): string
    {
        $valor = $this->entrada($chave, $padrao);

        return is_scalar($valor) ? trim((string) $valor) : $padrao;
    }

    public function inteiro(string $chave, int $padrao = 0): int
    {
        $valor = $this->entrada($chave, $padrao);

        return is_scalar($valor) ? (int) $valor : $padrao;
    }

    public function booleano(string $chave): bool
    {
        $valor = $this->entrada($chave, false);

        return in_array($valor, ['1', 1, true, 'on', 'sim'], true);
    }

    /** @return array<string,mixed> */
    public function todos(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function arquivo(string $chave): ?array
    {
        $arquivo = $this->files[$chave] ?? null;

        return is_array($arquivo) ? $arquivo : null;
    }

    public function querString(string $chave, string $padrao = ''): string
    {
        $valor = $this->query[$chave] ?? $padrao;

        return is_string($valor) ? trim($valor) : $padrao;
    }

    public function aceitaJson(): bool
    {
        return str_contains((string) $this->cabecalho('accept'), 'application/json')
            || strtolower((string) $this->cabecalho('x-requested-with')) === 'xmlhttprequest';
    }

    public function mesmaOrigem(): bool
    {
        $origem = $this->cabecalho('origin');

        if ($origem === null) {
            $referer = $this->cabecalho('referer');
            if ($referer === null) {
                return true;
            }
            $origem = $referer;
        }

        $hostOrigem = parse_url($origem, PHP_URL_HOST);
        $hostAtual  = parse_url(Config::get('app.url'), PHP_URL_HOST);

        return $hostOrigem !== null && $hostAtual !== null && strcasecmp($hostOrigem, $hostAtual) === 0;
    }

    private static function detectarIp(): string
    {
        // Não confiar em X-Forwarded-For sem proxy conhecido: usado só para auditoria/throttle.
        return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }
}
