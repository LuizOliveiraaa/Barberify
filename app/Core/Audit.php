<?php

declare(strict_types=1);

namespace Barberify\Core;

use Throwable;

final class Audit
{
    /**
     * @param array|null $antes  Dados relevantes antes da operação
     * @param array|null $depois Dados relevantes depois da operação
     */
    public static function registrar(
        string $acao,
        string $modulo,
        string $entidade,
        ?int $entidadeId = null,
        ?array $antes = null,
        ?array $depois = null,
        string $resultado = 'sucesso',
        ?int $empresaId = null,
        ?string $atorTipo = null,
        ?int $atorId = null
    ): void {
        try {
            Db::inserir('auditoria', [
                'empresa_id'   => $empresaId ?? Contexto::empresaId(),
                'ator_tipo'    => $atorTipo ?? self::atorTipoPadrao(),
                'ator_id'      => $atorId ?? self::atorIdPadrao(),
                'acao'         => mb_substr($acao, 0, 80),
                'modulo'       => mb_substr($modulo, 0, 40),
                'entidade'     => mb_substr($entidade, 0, 60),
                'entidade_id'  => $entidadeId,
                'dados_antes'  => $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE),
                'dados_depois' => $depois === null ? null : json_encode($depois, JSON_UNESCAPED_UNICODE),
                'resultado'    => $resultado,
                'ip'           => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'user_agent'   => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'request_id'   => Request::idRequisicao(),
                'contexto'     => null,
                'criado_em'    => now(),
            ]);
        } catch (Throwable $e) {
            Logger::erro('Falha ao registrar auditoria', ['erro' => $e->getMessage(), 'acao' => $acao]);
        }
    }

    private static function atorTipoPadrao(): string
    {
        if (\Barberify\Auth\AuthControl::instancia()->autenticado()) {
            return 'control';
        }

        if (Contexto::usuarioId() !== null) {
            return 'empresa';
        }

        return 'sistema';
    }

    private static function atorIdPadrao(): ?int
    {
        $control = \Barberify\Auth\AuthControl::instancia();
        if ($control->autenticado()) {
            return $control->id();
        }

        return Contexto::usuarioId();
    }
}
