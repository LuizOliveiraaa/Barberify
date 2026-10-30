<?php

declare(strict_types=1);

namespace Barberify\Core;

use RuntimeException;

final class Crypto
{
    private const CIFRA = 'aes-256-gcm';

    public static function cifrar(string $texto): string
    {
        $chave = self::chave();
        $nonce = random_bytes(12);
        $tag   = '';

        $cifrado = openssl_encrypt($texto, self::CIFRA, $chave, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        if ($cifrado === false) {
            throw new RuntimeException('Falha ao cifrar dado sensível.');
        }

        return base64_encode($nonce . $tag . $cifrado);
    }

    public static function decifrar(string $pacote): ?string
    {
        $binario = base64_decode($pacote, true);

        if ($binario === false || strlen($binario) < 29) {
            return null;
        }

        $nonce   = substr($binario, 0, 12);
        $tag     = substr($binario, 12, 16);
        $cifrado = substr($binario, 28);

        $texto = openssl_decrypt($cifrado, self::CIFRA, self::chave(), OPENSSL_RAW_DATA, $nonce, $tag);

        return $texto === false ? null : $texto;
    }

    private static function chave(): string
    {
        $appKey = (string) Config::get('security.app_key', '');

        if ($appKey === '') {
            throw new RuntimeException('APP_KEY ausente: configure o .env.');
        }

        return hash('sha256', 'barberify|' . $appKey, true);
    }
}
