<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Passwords
{
    public static function hash(string $senha): string
    {
        return password_hash($senha, PASSWORD_DEFAULT);
    }

    public static function verificar(string $senha, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify($senha, $hash);
    }

    public static function precisaRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    public static function temporaria(int $tamanho = 12): string
    {
        $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $saida    = '';
        $maximo   = strlen($alfabeto) - 1;

        for ($i = 0; $i < $tamanho; $i++) {
            $saida .= $alfabeto[random_int(0, $maximo)];
        }

        // Garante requisito mínimo de letras e números.
        return $saida . random_int(10, 99);
    }
}
