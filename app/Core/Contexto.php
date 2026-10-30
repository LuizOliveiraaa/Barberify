<?php

declare(strict_types=1);

namespace Barberify\Core;

use RuntimeException;

/**
 * Contexto de tenant da requisição.
 *
 * REGRA: empresa_id/unidade_id vêm SEMPRE da sessão autenticada e validada,
 * nunca de parâmetros enviados pelo navegador.
 */
final class Contexto
{
    private static ?int $empresaId = null;
    private static ?int $unidadeId = null;
    private static ?int $usuarioId = null;
    private static array $usuario = [];
    private static array $permissoes = [];
    private static bool $proprietario = false;
    private static array $empresa = [];

    public static function definir(array $usuario, array $empresa, ?int $unidadeId, array $permissoes): void
    {
        self::$usuarioId    = (int) $usuario['id'];
        self::$usuario      = $usuario;
        self::$empresa      = $empresa;
        self::$empresaId    = (int) $empresa['id'];
        self::$unidadeId    = $unidadeId;
        self::$permissoes   = $permissoes;
        self::$proprietario = (int) ($usuario['is_proprietario'] ?? 0) === 1;
    }

    public static function limpar(): void
    {
        self::$usuarioId    = null;
        self::$usuario      = [];
        self::$empresa      = [];
        self::$empresaId    = null;
        self::$unidadeId    = null;
        self::$permissoes   = [];
        self::$proprietario = false;
    }

    public static function empresaId(): ?int
    {
        return self::$empresaId;
    }

    public static function unidadeId(): ?int
    {
        return self::$unidadeId;
    }

    public static function usuarioId(): ?int
    {
        return self::$usuarioId;
    }

    public static function usuario(): array
    {
        return self::$usuario;
    }

    public static function empresa(): array
    {
        return self::$empresa;
    }

    public static function empresaNome(): string
    {
        return (string) (self::$empresa['nome_fantasia'] ?? '');
    }

    public static function ehProprietario(): bool
    {
        return self::$proprietario;
    }

    /** @return list<string> */
    public static function permissoes(): array
    {
        return self::$permissoes;
    }

    public static function pode(string $chave): bool
    {
        if (self::$usuarioId === null) {
            return false;
        }

        if (self::$proprietario) {
            return true;
        }

        return in_array($chave, self::$permissoes, true);
    }

    public static function exigirEmpresa(): int
    {
        if (self::$empresaId === null) {
            throw new RuntimeException('Contexto de empresa ausente.');
        }

        return self::$empresaId;
    }
}
