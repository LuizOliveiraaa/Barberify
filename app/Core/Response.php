<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Response
{
    /** @var array<string,string> */
    private array $cabecalhos = [];
    private int $status = 200;
    private string $corpo = '';

    public static function html(string $conteudo, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->corpo  = $conteudo;
        $r->cabecalhos['Content-Type'] = 'text/html; charset=utf-8';

        return $r;
    }

    public static function json(array $dados, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->corpo  = (string) json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $r->cabecalhos['Content-Type'] = 'application/json; charset=utf-8';

        return $r;
    }

    public static function redirect(string $destino, int $status = 302): self
    {
        $r = new self();
        $r->status = $status;
        $r->cabecalhos['Location'] = $destino;

        return $r;
    }

    public static function texto(string $conteudo, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->corpo  = $conteudo;
        $r->cabecalhos['Content-Type'] = 'text/plain; charset=utf-8';

        return $r;
    }

    public function comCabecalho(string $nome, string $valor): self
    {
        $this->cabecalhos[$nome] = $valor;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function corpo(): string
    {
        return $this->corpo;
    }

    /** @return array<string,string> */
    public function cabecalhos(): array
    {
        return $this->cabecalhos;
    }

    public function enviar(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach (Config::get('security.cabecalhos', []) as $nome => $valor) {
                header($nome . ': ' . $valor);
            }

            foreach ($this->cabecalhos as $nome => $valor) {
                header($nome . ': ' . $valor);
            }

            if ($this->status >= 400) {
                header('Cache-Control: no-store');
            }
        }

        echo $this->corpo;
    }
}
