<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Config
{
    /** @var array<string,mixed> */
    private static array $itens = [];
    private static bool $carregado = false;

    public static function carregar(string $diretorio): void
    {
        if (self::$carregado) {
            return;
        }

        foreach (glob($diretorio . '/*.php') ?: [] as $arquivo) {
            $nome = basename($arquivo, '.php');
            /** @var mixed $dados */
            $dados = require $arquivo;

            if (is_array($dados)) {
                self::$itens[$nome] = $dados;
            }
        }

        self::$carregado = true;
    }

    public static function get(string $caminho, mixed $padrao = null): mixed
    {
        $partes = explode('.', $caminho);
        $valor  = self::$itens;

        foreach ($partes as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $padrao;
            }
            $valor = $valor[$parte];
        }

        return $valor;
    }

    public static function definir(string $caminho, mixed $valor): void
    {
        $partes = explode('.', $caminho);
        $ref    = &self::$itens;

        foreach ($partes as $parte) {
            if (!isset($ref[$parte]) || !is_array($ref[$parte])) {
                $ref[$parte] = [];
            }
            $ref = &$ref[$parte];
        }

        $ref = $valor;
    }
}
