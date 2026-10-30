<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Str
{
    public static function slug(string $texto, int $max = 60): string
    {
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = strtolower($texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');

        return mb_substr($texto !== '' ? $texto : 'item', 0, $max);
    }

    /** Código público legível, sem caracteres ambíguos (0/O, 1/I). */
    public static function codigo(int $tamanho = 8): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $saida    = '';
        $maximo   = strlen($alfabeto) - 1;

        for ($i = 0; $i < $tamanho; $i++) {
            $saida .= $alfabeto[random_int(0, $maximo)];
        }

        return $saida;
    }

    public static function telefoneNormalizado(?string $telefone): string
    {
        return preg_replace('/\D+/', '', (string) $telefone) ?? '';
    }

    public static function telefoneFormatado(?string $telefone): string
    {
        $d = self::telefoneNormalizado($telefone);

        if (strlen($d) === 11) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
        }

        if (strlen($d) === 10) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
        }

        return $d !== '' ? $d : '—';
    }

    public static function emailMascarado(?string $email): string
    {
        $email = (string) $email;
        $pos   = strpos($email, '@');

        if ($pos === false || $pos < 1) {
            return $email;
        }

        $usuario = substr($email, 0, $pos);
        $dominio = substr($email, $pos + 1);

        return mb_substr($usuario, 0, 1) . str_repeat('*', max(3, mb_strlen($usuario) - 1)) . '@' . $dominio;
    }

    public static function documento(?string $documento): string
    {
        $d = preg_replace('/\D+/', '', (string) $documento) ?? '';

        if (strlen($d) === 11) {
            return sprintf('%s.%s.%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9));
        }

        if (strlen($d) === 14) {
            return sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12));
        }

        return $d !== '' ? $d : '—';
    }

    public static function resumir(string $texto, int $limite = 80): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);

        return mb_strlen($texto) <= $limite ? $texto : mb_substr($texto, 0, $limite - 1) . '…';
    }
}
