<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Money
{
    public static function centavos(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_float($valor)) {
            return (int) round($valor * 100);
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return 0;
        }

        $texto = str_replace(['R$', ' ', '.'], '', $texto);

        if (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', $texto);
        }

        return (int) round(((float) $texto) * 100);
    }

    public static function reais(mixed $centavos): float
    {
        return round(((int) $centavos) / 100, 2);
    }

    public static function formatar(mixed $valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    public static function paraDecimal(mixed $valorEntrada): string
    {
        return number_format(self::reais(self::centavos($valorEntrada)), 2, '.', '');
    }
}
