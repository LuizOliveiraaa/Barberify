<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Throttle
{
    public static function registrar(string $escopo, string $identificador, string $ip, bool $sucesso): void
    {
        try {
            Db::inserir('tentativas_login', [
                'escopo'        => $escopo,
                'identificador' => mb_substr(mb_strtolower($identificador), 0, 160),
                'ip'            => $ip,
                'sucesso'       => $sucesso ? 1 : 0,
                'criado_em'     => now(),
            ]);
        } catch (\Throwable $e) {
            Logger::aviso('Falha ao registrar tentativa', ['erro' => $e->getMessage()]);
        }
    }

    public static function bloqueado(string $escopo, string $identificador, string $ip, int $limite, int $janelaMinutos): bool
    {
        if ($limite <= 0) {
            return false;
        }

        $desde = date('Y-m-d H:i:s', time() - ($janelaMinutos * 60));

        $porUsuario = (int) Db::escalar(
            'SELECT COUNT(*) FROM tentativas_login WHERE escopo = :e AND identificador = :i AND sucesso = 0 AND criado_em >= :d',
            [':e' => $escopo, ':i' => mb_strtolower($identificador), ':d' => $desde]
        );

        $porIp = (int) Db::escalar(
            'SELECT COUNT(*) FROM tentativas_login WHERE escopo = :e AND ip = :ip AND sucesso = 0 AND criado_em >= :d',
            [':e' => $escopo, ':ip' => $ip, ':d' => $desde]
        );

        return $porUsuario >= $limite || $porIp >= ($limite * 3);
    }

    public static function limpar(string $escopo, string $identificador, string $ip): void
    {
        Db::executar(
            'DELETE FROM tentativas_login WHERE escopo = :e AND (identificador = :i OR ip = :ip)',
            [':e' => $escopo, ':i' => mb_strtolower($identificador), ':ip' => $ip]
        );
    }

    public static function expurgar(int $dias = 30): int
    {
        return Db::executar(
            'DELETE FROM tentativas_login WHERE criado_em < :limite',
            [':limite' => date('Y-m-d H:i:s', time() - ($dias * 86400))]
        )->rowCount();
    }
}
