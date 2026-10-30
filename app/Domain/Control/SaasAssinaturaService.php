<?php

declare(strict_types=1);

namespace Barberify\Domain\Control;

use Barberify\Core\Audit;
use Barberify\Core\Db;
use Barberify\Core\Money;
use RuntimeException;

final class SaasAssinaturaService
{
    /**
     * Deriva a situação da assinatura a partir de datas e pagamentos.
     * Idempotente: só grava quando o status realmente muda.
     */
    public function avaliar(int $assinaturaId): ?string
    {
        $assinatura = Db::um(
            'SELECT a.*, p.dias_graca_vencida, p.dias_graca_inadimplente
               FROM saas_assinaturas a
               JOIN saas_planos p ON p.id = a.plano_id
              WHERE a.id = :id',
            [':id' => $assinaturaId]
        );

        if ($assinatura === null || (string) $assinatura['status'] === 'cancelada') {
            return null;
        }

        $hoje    = new \DateTimeImmutable(hoje());
        $status  = (string) $assinatura['status'];
        $novo    = $status;
        $motivo  = '';

        if ($status === 'em_teste') {
            $fim = $assinatura['fim_teste'] !== null ? new \DateTimeImmutable((string) $assinatura['fim_teste']) : $hoje;

            if ($fim < $hoje) {
                $novo   = 'vencida';
                $motivo = 'Período de teste encerrado sem contratação.';
            }
        } elseif (in_array($status, ['ativa', 'pagamento_pendente', 'vencida', 'inadimplente'], true)) {
            $venc = $assinatura['proximo_vencimento'] !== null
                ? new \DateTimeImmutable((string) $assinatura['proximo_vencimento'])
                : $hoje;

            if ($venc >= $hoje) {
                if ($status !== 'ativa') {
                    $novo   = 'ativa';
                    $motivo = 'Vencimento regularizado.';
                }
            } else {
                $atraso = (int) $venc->diff($hoje)->days;
                $g1     = (int) $assinatura['dias_graca_vencida'];
                $g2     = (int) $assinatura['dias_graca_inadimplente'];

                if ($atraso <= $g1) {
                    $novo   = 'vencida';
                    $motivo = sprintf('Fatura vencida há %d dia(s).', $atraso);
                } elseif ($atraso <= $g1 + $g2) {
                    $novo   = 'inadimplente';
                    $motivo = sprintf('Inadimplência: %d dias de atraso.', $atraso);
                } else {
                    $novo   = 'suspensa';
                    $motivo = sprintf('Acesso suspenso por atraso de %d dias.', $atraso);
                }
            }
        }

        Db::atualizar('saas_assinaturas', ['ultima_avaliacao' => now()], 'id = :id', [':id' => $assinaturaId]);

        if ($novo === $status) {
            return $novo;
        }

        Db::tx(function () use ($assinatura, $status, $novo, $motivo): void {
            Db::atualizar('saas_assinaturas', ['status' => $novo], 'id = :id', [':id' => (int) $assinatura['id']]);

            Db::inserir('saas_assinatura_eventos', [
                'assinatura_id' => (int) $assinatura['id'],
                'empresa_id'    => (int) $assinatura['empresa_id'],
                'de'            => $status,
                'para'          => $novo,
                'motivo'        => $motivo,
                'ator_tipo'     => 'sistema',
                'ator_id'       => null,
                'criado_em'     => now(),
            ]);

            if ($novo === 'suspensa') {
                Db::executar(
                    "UPDATE empresas SET status = 'suspensa', suspensa_em = :agora, motivo_status = :motivo
                      WHERE id = :id AND status = 'ativa'",
                    [':agora' => now(), ':motivo' => $motivo, ':id' => (int) $assinatura['empresa_id']]
                );
            }

            Audit::registrar(
                'assinatura_avaliada',
                'control',
                'saas_assinaturas',
                (int) $assinatura['id'],
                ['status' => $status],
                ['status' => $novo, 'motivo' => $motivo],
                'sucesso',
                (int) $assinatura['empresa_id'],
                'sistema',
                null
            );
        });

        return $novo;
    }

    public function avaliarTodas(): int
    {
        $alteradas = 0;

        foreach (Db::select("SELECT id, status FROM saas_assinaturas WHERE status <> 'cancelada' LIMIT 500") as $linha) {
            $antes = (string) $linha['status'];
            $depois = $this->avaliar((int) $linha['id']);

            if ($depois !== null && $depois !== $antes) {
                $alteradas++;
            }
        }

        return $alteradas;
    }

    /**
     * Registra pagamento manual, quita a fatura e renova o vencimento.
     */
    public function registrarPagamento(int $assinaturaId, array $dados, int $atorId): int
    {
        return Db::tx(function () use ($assinaturaId, $dados, $atorId): int {
            $assinatura = Db::um(
                'SELECT * FROM saas_assinaturas WHERE id = :id FOR UPDATE',
                [':id' => $assinaturaId]
            );

            if ($assinatura === null) {
                throw new RuntimeException('Assinatura não encontrada.');
            }

            if ((string) $assinatura['status'] === 'cancelada') {
                throw new RuntimeException('Assinatura cancelada não recebe pagamento.');
            }

            $faturaId = (int) ($dados['fatura_id'] ?? 0);

            if ($faturaId === 0) {
                $fatura = Db::um(
                    "SELECT * FROM saas_faturas
                      WHERE assinatura_id = :a AND status IN ('aberta','vencida')
                      ORDER BY vencimento LIMIT 1",
                    [':a' => $assinaturaId]
                );

                if ($fatura === null) {
                    $faturaId = Db::inserir('saas_faturas', [
                        'assinatura_id' => $assinaturaId,
                        'empresa_id'    => (int) $assinatura['empresa_id'],
                        'competencia'   => date('Y-m'),
                        'valor'         => Money::paraDecimal($assinatura['preco_snapshot']),
                        'vencimento'    => (string) ($assinatura['proximo_vencimento'] ?? hoje()),
                        'status'        => 'aberta',
                        'criado_em'     => now(),
                    ]);
                } else {
                    $faturaId = (int) $fatura['id'];
                }
            }

            $fatura = Db::um('SELECT * FROM saas_faturas WHERE id = :id AND empresa_id = :e FOR UPDATE', [
                ':id' => $faturaId,
                ':e'  => (int) $assinatura['empresa_id'],
            ]);

            if ($fatura === null) {
                throw new RuntimeException('Fatura não pertence a esta empresa.');
            }

            if ((string) $fatura['status'] === 'paga') {
                throw new RuntimeException('Esta fatura já está paga.');
            }

            $valor = Money::paraDecimal($dados['valor'] ?? $fatura['valor']);

            $pagamentoId = Db::inserir('saas_pagamentos', [
                'fatura_id'      => $faturaId,
                'empresa_id'     => (int) $assinatura['empresa_id'],
                'valor'          => $valor,
                'forma'          => mb_substr((string) $dados['forma'], 0, 24),
                'data_pagamento' => (string) ($dados['data_pagamento'] ?? hoje()),
                'origem'         => 'manual',
                'registrado_por' => $atorId,
                'observacao'     => $dados['observacao'] ?? null,
                'criado_em'      => now(),
            ]);

            Db::atualizar('saas_faturas', [
                'status'  => 'paga',
                'paga_em' => now(),
            ], 'id = :id', [':id' => $faturaId]);

            Db::atualizar('saas_assinaturas', [
                'status'             => 'ativa',
                'proximo_vencimento' => $this->proximoVencimento(
                    (string) ($assinatura['proximo_vencimento'] ?? hoje()),
                    (string) $assinatura['periodicidade_snapshot']
                ),
            ], 'id = :id', [':id' => $assinaturaId]);

            Db::executar(
                "UPDATE empresas SET status = 'ativa', suspensa_em = NULL, motivo_status = NULL WHERE id = :id AND status = 'suspensa'",
                [':id' => (int) $assinatura['empresa_id']]
            );

            Db::inserir('saas_assinatura_eventos', [
                'assinatura_id' => $assinaturaId,
                'empresa_id'    => (int) $assinatura['empresa_id'],
                'de'            => (string) $assinatura['status'],
                'para'          => 'ativa',
                'motivo'        => 'Pagamento manual registrado.',
                'ator_tipo'     => 'control',
                'ator_id'       => $atorId,
                'criado_em'     => now(),
            ]);

            Audit::registrar(
                'pagamento_saas_registrado',
                'control',
                'saas_pagamentos',
                $pagamentoId,
                null,
                ['fatura_id' => $faturaId, 'valor' => $valor, 'forma' => $dados['forma']],
                'sucesso',
                (int) $assinatura['empresa_id'],
                'control',
                $atorId
            );

            return $pagamentoId;
        });
    }

    public function renovar(int $assinaturaId, int $atorId): void
    {
        Db::tx(function () use ($assinaturaId, $atorId): void {
            $assinatura = Db::um('SELECT * FROM saas_assinaturas WHERE id = :id FOR UPDATE', [':id' => $assinaturaId]);

            if ($assinatura === null) {
                throw new RuntimeException('Assinatura não encontrada.');
            }

            Db::atualizar('saas_assinaturas', [
                'status'             => 'ativa',
                'proximo_vencimento' => $this->proximoVencimento(hoje(), (string) $assinatura['periodicidade_snapshot']),
                'ultima_avaliacao'   => now(),
            ], 'id = :id', [':id' => $assinaturaId]);

            Audit::registrar('assinatura_renovada', 'control', 'saas_assinaturas', $assinaturaId,
                ['status' => $assinatura['status']], ['status' => 'ativa'], 'sucesso',
                (int) $assinatura['empresa_id'], 'control', $atorId);
        });
    }

    public function proximoVencimento(string $base, string $periodicidade): string
    {
        $data = new \DateTimeImmutable($base < hoje() ? hoje() : $base);

        return $data->modify($periodicidade === 'anual' ? '+1 year' : '+1 month')->format('Y-m-d');
    }
}
