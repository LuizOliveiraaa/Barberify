<?php

declare(strict_types=1);

namespace Barberify\Core;

use Throwable;

final class Logger
{
    public static function erro(string $mensagem, array $contexto = []): void
    {
        self::registrar('erro', $mensagem, $contexto);
    }

    public static function aviso(string $mensagem, array $contexto = []): void
    {
        self::registrar('aviso', $mensagem, $contexto);
    }

    public static function info(string $mensagem, array $contexto = []): void
    {
        self::registrar('info', $mensagem, $contexto);
    }

    public static function registrar(string $nivel, string $mensagem, array $contexto = []): void
    {
        $mensagem = self::sanitizar($mensagem);
        $contexto = self::sanitizarArray($contexto);

        try {
            $dir = Config::get('paths.logs', BASE_PATH . '/storage/logs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $linha = sprintf(
                "[%s] %s %s %s\n",
                date('Y-m-d H:i:s'),
                strtoupper($nivel),
                $mensagem,
                $contexto === [] ? '' : json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $linha, FILE_APPEND);
        } catch (Throwable) {
            // Log nunca deve derrubar a aplicação.
        }

        if (in_array($nivel, ['erro', 'aviso'], true) && Db::inicializado()) {
            try {
                $impressao = substr(hash('sha256', $mensagem . json_encode($contexto)), 0, 32);

                Db::inserir('logs_aplicacao', [
                    'nivel'      => $nivel,
                    'mensagem'   => mb_substr($mensagem, 0, 500),
                    'contexto'   => $contexto === [] ? null : json_encode($contexto, JSON_UNESCAPED_UNICODE),
                    'impressao'  => $impressao,
                    'request_id' => Request::idRequisicao(),
                    'empresa_id' => Contexto::empresaId(),
                    'criado_em'  => Db::agora(),
                ]);
            } catch (Throwable) {
                // Tabela pode não existir durante a instalação.
            }
        }
    }

    private static function sanitizar(string $texto): string
    {
        return preg_replace('/(senha|password|token|secret|chave)[=:]\s*\S+/i', '$1=***', $texto) ?? $texto;
    }

    private static function sanitizarArray(array $dados): array
    {
        foreach ($dados as $chave => $valor) {
            if (is_array($valor)) {
                $dados[$chave] = self::sanitizarArray($valor);
            } elseif (is_string($valor)) {
                $dados[$chave] = self::sanitizar(mb_substr($valor, 0, 400));
            }
        }

        return $dados;
    }
}
