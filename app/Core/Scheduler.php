<?php

declare(strict_types=1);

namespace Barberify\Core;

use Throwable;

/**
 * Substituto do cron na hospedagem compartilhada.
 *
 * Executa dentro de requisições reais, com amostragem, lock por linha e orçamento
 * de tempo. NENHUMA regra crítica depende dele: a expiração de reserva, por
 * exemplo, é garantida por predicado nas consultas.
 */
final class Scheduler
{
    /** @var array<string,array{intervalo:int,tarefa:callable}> */
    private static array $tarefas = [];

    public static function registrar(string $chave, int $intervaloSegundos, callable $tarefa): void
    {
        self::$tarefas[$chave] = ['intervalo' => $intervaloSegundos, 'tarefa' => $tarefa];
    }

    public static function tick(): void
    {
        if (self::$tarefas === [] || !Db::inicializado()) {
            return;
        }

        $sorteio = (int) Config::get('app.scheduler.amostragem', 10);
        if ($sorteio > 1 && random_int(1, $sorteio) !== 1) {
            return;
        }

        $inicio   = microtime(true);
        $orcamento = ((int) Config::get('app.scheduler.orcamento_ms', 2000)) / 1000;

        foreach (self::$tarefas as $chave => $definicao) {
            if ((microtime(true) - $inicio) > $orcamento) {
                break;
            }

            try {
                if (!self::adquirir($chave, $definicao['intervalo'])) {
                    continue;
                }

                $antes = microtime(true);
                ($definicao['tarefa'])();
                $ms = (int) round((microtime(true) - $antes) * 1000);

                Db::executar(
                    'UPDATE sistema_tarefas SET ultima_execucao = :agora, bloqueado_ate = NULL, duracao_ms = :ms, ultimo_resultado = :res, falhas_consecutivas = 0 WHERE chave = :chave',
                    [':agora' => now(), ':ms' => $ms, ':res' => 'ok', ':chave' => $chave]
                );
            } catch (Throwable $e) {
                Logger::aviso('Tarefa do scheduler falhou', ['tarefa' => $chave, 'erro' => $e->getMessage()]);

                try {
                    Db::executar(
                        'UPDATE sistema_tarefas SET bloqueado_ate = NULL, ultimo_resultado = :res, falhas_consecutivas = falhas_consecutivas + 1 WHERE chave = :chave',
                        [':res' => mb_substr($e->getMessage(), 0, 255), ':chave' => $chave]
                    );
                } catch (Throwable) {
                    // Silencioso: scheduler nunca derruba a requisição.
                }
            }
        }
    }

    private static function adquirir(string $chave, int $intervalo): bool
    {
        Db::executar(
            'INSERT INTO sistema_tarefas (chave, intervalo_min, ultima_execucao, bloqueado_ate, falhas_consecutivas)
             VALUES (:chave, :intervalo, NULL, NULL, 0)
             ON DUPLICATE KEY UPDATE chave = chave',
            [':chave' => $chave, ':intervalo' => max(1, (int) ceil($intervalo / 60))]
        );

        $linha = Db::um('SELECT * FROM sistema_tarefas WHERE chave = :chave', [':chave' => $chave]);

        if ($linha === null) {
            return false;
        }

        $agora      = time();
        $bloqueado  = $linha['bloqueado_ate'] !== null ? strtotime((string) $linha['bloqueado_ate']) : null;
        $ultima     = $linha['ultima_execucao'] !== null ? strtotime((string) $linha['ultima_execucao']) : null;

        if ($bloqueado !== null && $bloqueado > $agora) {
            return false;
        }

        if ($ultima !== null && ($agora - $ultima) < $intervalo) {
            return false;
        }

        $afetadas = Db::executar(
            'UPDATE sistema_tarefas SET bloqueado_ate = :ate WHERE chave = :chave AND (bloqueado_ate IS NULL OR bloqueado_ate < :agora)',
            [':ate' => date('Y-m-d H:i:s', $agora + 120), ':chave' => $chave, ':agora' => now()]
        )->rowCount();

        return $afetadas > 0;
    }
}
