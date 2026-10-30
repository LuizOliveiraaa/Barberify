<?php

declare(strict_types=1);

use Barberify\Core\Config;
use Barberify\Core\Contexto;
use Barberify\Core\Csrf;
use Barberify\Core\Flash;
use Barberify\Core\Money;

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('hoje')) {
    function hoje(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('e')) {
    /** Escape HTML obrigatório em toda saída de dado de usuário. */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $caminho = '/'): string
    {
        return rtrim((string) Config::get('app.url'), '/') . '/' . ltrim($caminho, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $caminho): string
    {
        $arquivo = BASE_PATH . '/public/' . ltrim($caminho, '/');
        $versao  = is_file($arquivo) ? (string) filemtime($arquivo) : (string) Config::get('app.versao', '1');

        return url($caminho) . '?v=' . $versao;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('can')) {
    function can(string $chave): bool
    {
        return Contexto::pode($chave);
    }
}

if (!function_exists('flash')) {
    /** @return array<int,array{tipo:string,mensagem:string}> */
    function flash(): array
    {
        return Flash::consumir();
    }
}

if (!function_exists('money')) {
    function money(mixed $valor): string
    {
        return Money::formatar((float) $valor);
    }
}

if (!function_exists('data_br')) {
    function data_br(?string $data): string
    {
        if ($data === null || $data === '' || str_starts_with($data, '0000')) {
            return '—';
        }

        $ts = strtotime($data);

        return $ts === false ? '—' : date('d/m/Y', $ts);
    }
}

if (!function_exists('data_hora_br')) {
    function data_hora_br(?string $data): string
    {
        if ($data === null || $data === '' || str_starts_with($data, '0000')) {
            return '—';
        }

        $ts = strtotime($data);

        return $ts === false ? '—' : date('d/m/Y H:i', $ts);
    }
}

if (!function_exists('idade_dias')) {
    function idade_dias(?string $data): ?int
    {
        if ($data === null || $data === '') {
            return null;
        }

        $ts = strtotime($data);

        return $ts === false ? null : (int) floor((time() - $ts) / 86400);
    }
}

if (!function_exists('iniciais')) {
    function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $letras = '';

        foreach ($partes as $parte) {
            if ($parte !== '' && mb_strlen($letras) < 2) {
                $letras .= mb_strtoupper(mb_substr($parte, 0, 1));
            }
        }

        return $letras !== '' ? $letras : '?';
    }
}

if (!function_exists('money_centavos')) {
    function money_centavos(mixed $valor): string
    {
        return Money::formatar((float) $valor);
    }
}
