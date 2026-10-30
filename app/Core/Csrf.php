<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Csrf
{
    private const COOKIE = 'bf_csrf';
    private static string $realm = 'publico';

    public static function definirRealm(string $realm): void
    {
        self::$realm = $realm;
    }

    public static function token(): string
    {
        $segredo = self::segredo();

        return hash_hmac('sha256', $segredo . '|' . self::$realm, self::chave());
    }

    public static function validar(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return hash_equals(self::token(), $token);
    }

    public static function rotacionar(): void
    {
        $novo = bin2hex(random_bytes(32));
        self::gravar($novo);
    }

    private static function segredo(): string
    {
        $atual = $_COOKIE[self::COOKIE] ?? null;

        if (is_string($atual) && preg_match('/^[a-f0-9]{64}$/', $atual)) {
            return $atual;
        }

        $novo = bin2hex(random_bytes(32));
        self::gravar($novo);

        return $novo;
    }

    private static function chave(): string
    {
        $chave = (string) Config::get('security.app_key', '');

        return $chave !== '' ? $chave : 'barberify-sem-chave';
    }

    private static function gravar(string $valor): void
    {
        $opcoes = [
            'expires'  => time() + 7200,
            'path'     => '/',
            'secure'   => (bool) Config::get('app.https', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (!headers_sent()) {
            setcookie(self::COOKIE, $valor, $opcoes);
        }

        $_COOKIE[self::COOKIE] = $valor;
    }
}
