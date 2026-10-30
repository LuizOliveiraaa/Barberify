<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Env
{
    /** @var array<string,string> */
    private static array $valores = [];
    private static bool $carregado = false;

    public static function carregar(string $arquivo): void
    {
        self::$carregado = true;

        if (!is_file($arquivo) || !is_readable($arquivo)) {
            return;
        }

        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '' || str_starts_with($linha, '#')) {
                continue;
            }

            $pos = strpos($linha, '=');
            if ($pos === false) {
                continue;
            }

            $chave = trim(substr($linha, 0, $pos));
            $valor = trim(substr($linha, $pos + 1));

            if ($chave === '') {
                continue;
            }

            if (strlen($valor) >= 2) {
                $primeiro = $valor[0];
                $ultimo   = $valor[strlen($valor) - 1];
                if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                    $valor = substr($valor, 1, -1);
                }
            }

            self::$valores[$chave] = $valor;
            $_ENV[$chave]          = $valor;
            putenv($chave . '=' . $valor);
        }
    }

    public static function get(string $chave, ?string $padrao = null): ?string
    {
        if (array_key_exists($chave, self::$valores)) {
            return self::$valores[$chave];
        }

        $v = getenv($chave);

        return $v === false ? $padrao : (string) $v;
    }

    public static function bool(string $chave, bool $padrao = false): bool
    {
        $v = self::get($chave);

        if ($v === null || $v === '') {
            return $padrao;
        }

        return in_array(strtolower($v), ['1', 'true', 'sim', 'yes', 'on'], true);
    }

    public static function int(string $chave, int $padrao = 0): int
    {
        $v = self::get($chave);

        return $v === null || $v === '' ? $padrao : (int) $v;
    }

    /** Usado pelo instalador, que precisa gravar sem reiniciar a aplicação. */
    public static function definir(string $chave, string $valor): void
    {
        self::$valores[$chave] = $valor;
        $_ENV[$chave]          = $valor;
        putenv($chave . '=' . $valor);
    }
}
