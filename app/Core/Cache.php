<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Cache
{
    public static function lembrar(string $chave, int $segundos, callable $gerador): mixed
    {
        $valor = self::obter($chave);

        if ($valor !== null) {
            return $valor;
        }

        $valor = $gerador();
        self::gravar($chave, $valor, $segundos);

        return $valor;
    }

    public static function obter(string $chave): mixed
    {
        $arquivo = self::caminho($chave);

        if (!is_file($arquivo)) {
            return null;
        }

        $conteudo = @file_get_contents($arquivo);
        if ($conteudo === false) {
            return null;
        }

        $pacote = @unserialize($conteudo, ['allowed_classes' => false]);

        if (!is_array($pacote) || !isset($pacote['expira']) || $pacote['expira'] < time()) {
            @unlink($arquivo);

            return null;
        }

        return $pacote['valor'] ?? null;
    }

    public static function gravar(string $chave, mixed $valor, int $segundos): void
    {
        $dir = (string) Config::get('paths.cache', BASE_PATH . '/storage/cache');

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(self::caminho($chave), serialize([
            'expira' => time() + $segundos,
            'valor'  => $valor,
        ]), LOCK_EX);
    }

    public static function esquecer(string $chave): void
    {
        @unlink(self::caminho($chave));
    }

    public static function esquecerPrefixo(string $prefixo): void
    {
        $dir = (string) Config::get('paths.cache', BASE_PATH . '/storage/cache');
        $base = preg_replace('/[^a-z0-9_-]/i', '', $prefixo) ?? '';

        foreach (glob($dir . '/' . $base . '*') ?: [] as $arquivo) {
            @unlink($arquivo);
        }
    }

    private static function caminho(string $chave): string
    {
        $dir = (string) Config::get('paths.cache', BASE_PATH . '/storage/cache');
        $nome = preg_replace('/[^a-z0-9_-]/i', '', $chave) ?? 'cache';

        return $dir . '/' . $nome . '.cache';
    }
}
