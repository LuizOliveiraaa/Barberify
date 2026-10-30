<?php

declare(strict_types=1);

namespace Barberify\Core;

use RuntimeException;
use Throwable;

final class View
{
    /** @var array<string,mixed> */
    private static array $compartilhado = [];

    public static function compartilhar(string $chave, mixed $valor): void
    {
        self::$compartilhado[$chave] = $valor;
    }

    public static function render(string $template, array $dados = [], string $layout = 'app'): Response
    {
        $dados = array_merge(self::$compartilhado, $dados);

        $conteudo = self::incluir($template, $dados);

        if ($layout === '') {
            return Response::html($conteudo);
        }

        $dados['conteudo'] = $conteudo;

        return Response::html(self::incluir('layouts/' . $layout, $dados));
    }

    public static function existe(string $template): bool
    {
        return is_file(self::caminho($template));
    }

    private static function incluir(string $template, array $dados): string
    {
        $arquivo = self::caminho($template);

        if (!is_file($arquivo)) {
            throw new RuntimeException('Template não encontrado: ' . $template);
        }

        extract($dados, EXTR_SKIP);
        ob_start();

        try {
            require $arquivo;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    private static function caminho(string $template): string
    {
        $base = (string) Config::get('paths.views', BASE_PATH . '/resources/views');
        $seguro = str_replace(['..', '\\'], '', $template);

        return $base . '/' . $seguro . '.php';
    }
}
