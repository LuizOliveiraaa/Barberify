<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Flash
{
    private const CHAVE = '_flash_mensagens';

    public static function sucesso(string $mensagem): void
    {
        self::adicionar('sucesso', $mensagem);
    }

    public static function erro(string $mensagem): void
    {
        self::adicionar('erro', $mensagem);
    }

    public static function aviso(string $mensagem): void
    {
        self::adicionar('aviso', $mensagem);
    }

    /** @return array<int,array{tipo:string,mensagem:string}> */
    public static function consumir(): array
    {
        $mensagens = $_SESSION[self::CHAVE] ?? [];

        unset($_SESSION[self::CHAVE]);

        return is_array($mensagens) ? $mensagens : [];
    }

    private static function adicionar(string $tipo, string $mensagem): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION[self::CHAVE][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }
}
