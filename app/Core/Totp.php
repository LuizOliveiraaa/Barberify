<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Totp
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function gerarSegredo(int $bytes = 20): string
    {
        return self::base32Codificar(random_bytes($bytes));
    }

    public static function codigoAtual(string $segredo, ?int $instante = null): string
    {
        $instante ??= time();
        $contador  = intdiv($instante, 30);
        $chave     = self::base32Decodificar($segredo);
        $binario   = pack('N*', 0) . pack('N*', $contador);
        $hash      = hash_hmac('sha1', $binario, $chave, true);
        $offset    = ord($hash[strlen($hash) - 1]) & 0x0F;
        $valor     = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $valor, 6, '0', STR_PAD_LEFT);
    }

    /** Janela de ±1 período para tolerar desvio de relógio. */
    public static function verificar(string $segredo, string $codigo, int $janela = 1): bool
    {
        $codigo = preg_replace('/\D+/', '', $codigo) ?? '';

        if (strlen($codigo) !== 6) {
            return false;
        }

        for ($i = -$janela; $i <= $janela; $i++) {
            if (hash_equals(self::codigoAtual($segredo, time() + ($i * 30)), $codigo)) {
                return true;
            }
        }

        return false;
    }

    public static function uri(string $segredo, string $email, string $emissor = 'Barberify Control'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=6&period=30',
            rawurlencode($emissor),
            rawurlencode($email),
            $segredo,
            rawurlencode($emissor)
        );
    }

    private static function base32Codificar(string $binario): string
    {
        $bits   = '';
        $saida  = '';

        for ($i = 0, $n = strlen($binario); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($binario[$i])), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 5) as $pedaco) {
            $pedaco = str_pad($pedaco, 5, '0', STR_PAD_RIGHT);
            $saida .= self::ALFABETO[bindec($pedaco)];
        }

        return $saida;
    }

    private static function base32Decodificar(string $segredo): string
    {
        $segredo = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $segredo) ?? '');
        $bits    = '';

        for ($i = 0, $n = strlen($segredo); $i < $n; $i++) {
            $pos = strpos(self::ALFABETO, $segredo[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $saida = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $saida .= chr(bindec($byte));
            }
        }

        return $saida;
    }
}
