<?php

declare(strict_types=1);

namespace Barberify\Domain\Sistema;

use Barberify\Core\Config;
use Barberify\Core\Db;
use Barberify\Core\Throttle;
use Barberify\Domain\Control\SaasAssinaturaService;

/**
 * Tarefas periódicas executadas pelo scheduler preguiçoso. Todas idempotentes e
 * limitadas em lote, para não estourar o tempo de execução da hospedagem.
 */
final class TarefasService
{
    /**
     * Marca holds vencidos como expirados. A liberação do horário, porém, já é
     * garantida pelo predicado nas consultas de disponibilidade.
     */
    public static function expirarReservas(): int
    {
        $vencidas = Db::select(
            "SELECT id, empresa_id FROM agendamentos
              WHERE status = 'reservado' AND reserva_expira_em IS NOT NULL AND reserva_expira_em <= :agora
              LIMIT 200",
            [':agora' => now()]
        );

        $total = 0;

        foreach ($vencidas as $linha) {
            $afetadas = Db::executar(
                "UPDATE agendamentos SET status = 'expirado'
                  WHERE id = :id AND status = 'reservado' AND reserva_expira_em <= :agora",
                [':id' => (int) $linha['id'], ':agora' => now()]
            )->rowCount();

            if ($afetadas === 0) {
                continue;
            }

            $total++;

            Db::inserir('agendamento_status_historico', [
                'empresa_id'     => (int) $linha['empresa_id'],
                'agendamento_id' => (int) $linha['id'],
                'de'             => 'reservado',
                'para'           => 'expirado',
                'motivo'         => 'Reserva temporária não confirmada em 15 minutos.',
                'ator_tipo'      => 'sistema',
                'ator_id'        => null,
                'criado_em'      => now(),
            ]);
        }

        return $total;
    }

    public static function atualizarSituacoesSaas(): int
    {
        return (new SaasAssinaturaService())->avaliarTodas();
    }

    public static function expurgar(): int
    {
        $total = 0;

        $diasLogs = (int) Db::escalar(
            "SELECT valor FROM control_configuracoes WHERE chave = 'retencao.logs_dias'"
        ) ?: 90;

        $total += Db::executar(
            'DELETE FROM logs_aplicacao WHERE criado_em < :limite LIMIT 1000',
            [':limite' => date('Y-m-d H:i:s', time() - ($diasLogs * 86400))]
        )->rowCount();

        $mesesAuditoria = (int) Db::escalar(
            "SELECT valor FROM control_configuracoes WHERE chave = 'retencao.auditoria_meses'"
        ) ?: 24;

        $total += Db::executar(
            'DELETE FROM auditoria WHERE criado_em < :limite LIMIT 1000',
            [':limite' => date('Y-m-d H:i:s', strtotime('-' . $mesesAuditoria . ' months'))]
        )->rowCount();

        $total += Throttle::expurgar(30);

        $total += Db::executar(
            'DELETE FROM idempotencia WHERE expira_em IS NOT NULL AND expira_em < :agora LIMIT 500',
            [':agora' => now()]
        )->rowCount();

        if ($total > 0) {
            \Barberify\Core\Logger::info('Expurgo executado', ['registros' => $total]);
        }

        return $total;
    }

    /** Intervalo de amostragem do scheduler, exposto para diagnóstico. */
    public static function configuracao(): array
    {
        return [
            'amostragem'   => Config::get('app.scheduler.amostragem'),
            'orcamento_ms' => Config::get('app.scheduler.orcamento_ms'),
        ];
    }
}
