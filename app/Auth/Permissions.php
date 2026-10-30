<?php

declare(strict_types=1);

namespace Barberify\Auth;

use Barberify\Core\Db;

final class Permissions
{
    /** @var array<string,list<string>> */
    private static array $cache = [];

    /** @return list<string> */
    public static function control(int $usuarioId): array
    {
        $chave = 'control:' . $usuarioId;

        if (isset(self::$cache[$chave])) {
            return self::$cache[$chave];
        }

        $linhas = Db::select(
            'SELECT DISTINCT p.chave
               FROM control_usuario_papeis up
               JOIN control_papel_permissoes pp ON pp.papel_id = up.papel_id
               JOIN control_permissoes p ON p.id = pp.permissao_id
               JOIN control_papeis pa ON pa.id = up.papel_id AND pa.ativo = 1
              WHERE up.usuario_id = :uid',
            [':uid' => $usuarioId]
        );

        return self::$cache[$chave] = array_values(array_map(static fn (array $l): string => (string) $l['chave'], $linhas));
    }

    /** @return list<string> */
    public static function empresa(int $usuarioId, int $empresaId): array
    {
        $chave = 'empresa:' . $empresaId . ':' . $usuarioId;

        if (isset(self::$cache[$chave])) {
            return self::$cache[$chave];
        }

        $linhas = Db::select(
            'SELECT DISTINCT p.chave
               FROM usuario_papeis up
               JOIN papeis pa ON pa.id = up.papel_id AND pa.ativo = 1 AND pa.empresa_id = up.empresa_id
               JOIN papel_permissoes pp ON pp.papel_id = up.papel_id
               JOIN permissoes p ON p.id = pp.permissao_id
              WHERE up.usuario_id = :uid AND up.empresa_id = :empresa',
            [':uid' => $usuarioId, ':empresa' => $empresaId]
        );

        return self::$cache[$chave] = array_values(array_map(static fn (array $l): string => (string) $l['chave'], $linhas));
    }

    public static function limparCache(?int $usuarioId = null): void
    {
        if ($usuarioId === null) {
            self::$cache = [];

            return;
        }

        foreach (array_keys(self::$cache) as $chave) {
            if (str_ends_with($chave, ':' . $usuarioId)) {
                unset(self::$cache[$chave]);
            }
        }
    }
}
