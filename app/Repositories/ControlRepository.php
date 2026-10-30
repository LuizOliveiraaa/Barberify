<?php

declare(strict_types=1);

namespace Barberify\Repositories;

use Barberify\Core\Db;

/**
 * Consultas de leitura do Barberify Control. Sempre em escopo global
 * (empresas, saas_*), nunca em dados operacionais de uma barbearia.
 */
final class ControlRepository
{
    public function metricasDashboard(): array
    {
        return [
            'total_empresas'  => (int) Db::escalar('SELECT COUNT(*) FROM empresas WHERE excluido_em IS NULL'),
            'ativas'          => (int) Db::escalar("SELECT COUNT(*) FROM saas_assinaturas WHERE status = 'ativa'"),
            'em_teste'        => (int) Db::escalar("SELECT COUNT(*) FROM saas_assinaturas WHERE status = 'em_teste' AND fim_teste >= CURDATE()"),
            'inadimplentes'   => (int) Db::escalar("SELECT COUNT(*) FROM saas_assinaturas WHERE status IN ('inadimplente','vencida')"),
            'suspensas'       => (int) Db::escalar("SELECT COUNT(*) FROM empresas WHERE status = 'suspensa'"),
            'canceladas'      => (int) Db::escalar("SELECT COUNT(*) FROM empresas WHERE status = 'cancelada'"),
            'mrr'             => (float) Db::escalar(
                "SELECT COALESCE(SUM(CASE WHEN periodicidade_snapshot = 'anual' THEN preco_snapshot / 12 ELSE preco_snapshot END), 0)
                   FROM saas_assinaturas WHERE status = 'ativa'"
            ),
            'recebido_mes'    => (float) Db::escalar(
                "SELECT COALESCE(SUM(valor), 0) FROM saas_pagamentos
                  WHERE estorno_de_id IS NULL AND data_pagamento >= :inicio AND data_pagamento <= :fim",
                [':inicio' => date('Y-m-01'), ':fim' => date('Y-m-t')]
            ),
            'a_receber'       => (float) Db::escalar(
                "SELECT COALESCE(SUM(valor), 0) FROM saas_faturas WHERE status IN ('aberta','vencida')"
            ),
            'novas_mes'       => (int) Db::escalar(
                'SELECT COUNT(*) FROM empresas WHERE criado_em >= :inicio',
                [':inicio' => date('Y-m-01 00:00:00')]
            ),
            'cancelamentos_mes' => (int) Db::escalar(
                "SELECT COUNT(*) FROM saas_assinatura_eventos WHERE para = 'cancelada' AND criado_em >= :inicio",
                [':inicio' => date('Y-m-01 00:00:00')]
            ),
            'faturas_vencidas' => (int) Db::escalar(
                "SELECT COUNT(*) FROM saas_faturas WHERE status IN ('aberta','vencida') AND vencimento < CURDATE()"
            ),
        ];
    }

    /** @return list<array> */
    public function proximosVencimentos(int $limite = 6): array
    {
        return Db::select(
            "SELECT f.*, e.nome_fantasia
               FROM saas_faturas f
               JOIN empresas e ON e.id = f.empresa_id
              WHERE f.status IN ('aberta','vencida')
              ORDER BY f.vencimento
              LIMIT " . max(1, $limite)
        );
    }

    /** @return array{itens:list<array>,total:int} */
    public function empresas(string $busca, string $status, int $pagina, int $porPagina = 20): array
    {
        $where = ['e.excluido_em IS NULL'];
        $params = [];

        if ($busca !== '') {
            $where[] = '(e.nome_fantasia LIKE :busca OR e.slug LIKE :busca OR e.cpf_cnpj LIKE :busca OR e.email LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }

        if ($status !== '') {
            $where[] = 'e.status = :status';
            $params[':status'] = $status;
        }

        $condicao = implode(' AND ', $where);

        $total = (int) Db::escalar('SELECT COUNT(*) FROM empresas e WHERE ' . $condicao, $params);
        $offset = max(0, ($pagina - 1) * $porPagina);

        $itens = Db::select(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM unidades u WHERE u.empresa_id = e.id AND u.excluido_em IS NULL) AS unidades,
                    (SELECT COUNT(*) FROM usuarios_empresa ue WHERE ue.empresa_id = e.id AND ue.excluido_em IS NULL) AS usuarios,
                    a.status AS assinatura_status, a.proximo_vencimento, p.nome AS plano_nome
               FROM empresas e
               LEFT JOIN saas_assinaturas a ON a.id = (SELECT MAX(id) FROM saas_assinaturas WHERE empresa_id = e.id)
               LEFT JOIN saas_planos p ON p.id = a.plano_id
              WHERE {$condicao}
              ORDER BY e.criado_em DESC
              LIMIT {$porPagina} OFFSET {$offset}",
            $params
        );

        return ['itens' => $itens, 'total' => $total];
    }

    public function empresa(int $id): ?array
    {
        return Db::um(
            'SELECT e.*, a.id AS assinatura_id, a.status AS assinatura_status, a.inicio AS assinatura_inicio,
                    a.fim_teste, a.proximo_vencimento, a.preco_snapshot, a.periodicidade_snapshot,
                    a.limites_snapshot, p.nome AS plano_nome, p.slug AS plano_slug
               FROM empresas e
               LEFT JOIN saas_assinaturas a ON a.id = (SELECT MAX(id) FROM saas_assinaturas WHERE empresa_id = e.id)
               LEFT JOIN saas_planos p ON p.id = a.plano_id
              WHERE e.id = :id AND e.excluido_em IS NULL',
            [':id' => $id]
        );
    }

    public function proprietarioDaEmpresa(int $empresaId): ?array
    {
        return Db::um(
            'SELECT * FROM usuarios_empresa WHERE empresa_id = :e AND is_proprietario = 1 LIMIT 1',
            [':e' => $empresaId]
        );
    }

    /** @return list<array> */
    public function unidadesDaEmpresa(int $empresaId): array
    {
        return Db::select(
            'SELECT * FROM unidades WHERE empresa_id = :e AND excluido_em IS NULL ORDER BY id',
            [':e' => $empresaId]
        );
    }

    /** @return list<array> */
    public function usuariosDaEmpresa(int $empresaId): array
    {
        return Db::select(
            'SELECT id, nome, email, cargo, status, is_proprietario, ultimo_acesso
               FROM usuarios_empresa WHERE empresa_id = :e AND excluido_em IS NULL ORDER BY id',
            [':e' => $empresaId]
        );
    }

    /** @return list<array> */
    public function faturasDaEmpresa(int $empresaId): array
    {
        return Db::select(
            'SELECT * FROM saas_faturas WHERE empresa_id = :e ORDER BY vencimento DESC LIMIT 24',
            [':e' => $empresaId]
        );
    }

    /** @return list<array> */
    public function pagamentosDaEmpresa(int $empresaId): array
    {
        return Db::select(
            'SELECT p.*, c.nome AS registrado_por_nome
               FROM saas_pagamentos p
               LEFT JOIN control_usuarios c ON c.id = p.registrado_por
              WHERE p.empresa_id = :e
              ORDER BY p.criado_em DESC
              LIMIT 24',
            [':e' => $empresaId]
        );
    }

    /** @return list<array> */
    public function eventosDaEmpresa(int $empresaId): array
    {
        return Db::select(
            'SELECT * FROM saas_assinatura_eventos WHERE empresa_id = :e ORDER BY id DESC LIMIT 30',
            [':e' => $empresaId]
        );
    }

    /** @return list<array> */
    public function observacoesDaEmpresa(int $empresaId): array
    {
        return Db::select(
            'SELECT o.*, c.nome AS autor FROM control_observacoes_empresa o
               LEFT JOIN control_usuarios c ON c.id = o.usuario_id
              WHERE o.empresa_id = :e ORDER BY o.id DESC LIMIT 30',
            [':e' => $empresaId]
        );
    }

    /** @return list<array> */
    public function planos(): array
    {
        $planos = Db::select('SELECT * FROM saas_planos WHERE excluido_em IS NULL ORDER BY ordem, id');

        foreach ($planos as &$plano) {
            $limites = Db::select('SELECT chave, valor FROM saas_plano_limites WHERE plano_id = :p', [':p' => (int) $plano['id']]);
            $plano['limites'] = array_column($limites, 'valor', 'chave');
            $plano['empresas'] = (int) Db::escalar('SELECT COUNT(*) FROM saas_assinaturas WHERE plano_id = :p', [':p' => (int) $plano['id']]);
        }

        return $planos;
    }

    public function plano(int $id): ?array
    {
        $plano = Db::um('SELECT * FROM saas_planos WHERE id = :id', [':id' => $id]);

        if ($plano === null) {
            return null;
        }

        $limites = Db::select('SELECT chave, valor FROM saas_plano_limites WHERE plano_id = :p', [':p' => $id]);
        $plano['limites'] = array_column($limites, 'valor', 'chave');

        return $plano;
    }

    /** @return array{itens:list<array>,total:int} */
    public function assinaturas(string $status, int $pagina, int $porPagina = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if ($status !== '') {
            $where[] = 'a.status = :status';
            $params[':status'] = $status;
        }

        $condicao = implode(' AND ', $where);
        $total = (int) Db::escalar('SELECT COUNT(*) FROM saas_assinaturas a WHERE ' . $condicao, $params);
        $offset = max(0, ($pagina - 1) * $porPagina);

        $itens = Db::select(
            "SELECT a.*, e.nome_fantasia, p.nome AS plano_nome
               FROM saas_assinaturas a
               JOIN empresas e ON e.id = a.empresa_id
               JOIN saas_planos p ON p.id = a.plano_id
              WHERE {$condicao}
              ORDER BY a.proximo_vencimento IS NULL, a.proximo_vencimento
              LIMIT {$porPagina} OFFSET {$offset}",
            $params
        );

        return ['itens' => $itens, 'total' => $total];
    }

    public function assinatura(int $id): ?array
    {
        return Db::um(
            'SELECT a.*, e.nome_fantasia, e.id AS empresa_id, p.nome AS plano_nome, p.dias_graca_vencida, p.dias_graca_inadimplente
               FROM saas_assinaturas a
               JOIN empresas e ON e.id = a.empresa_id
               JOIN saas_planos p ON p.id = a.plano_id
              WHERE a.id = :id',
            [':id' => $id]
        );
    }

    /** @return list<array> */
    public function faturasAbertasDaAssinatura(int $assinaturaId): array
    {
        return Db::select(
            "SELECT * FROM saas_faturas WHERE assinatura_id = :a AND status IN ('aberta','vencida') ORDER BY vencimento",
            [':a' => $assinaturaId]
        );
    }

    /** @return list<array> */
    public function equipe(): array
    {
        $usuarios = Db::select('SELECT * FROM control_usuarios WHERE excluido_em IS NULL ORDER BY is_proprietario DESC, nome');

        foreach ($usuarios as &$usuario) {
            $usuario['papeis'] = Db::select(
                'SELECT p.id, p.nome, p.slug FROM control_usuario_papeis up
                   JOIN control_papeis p ON p.id = up.papel_id
                  WHERE up.usuario_id = :u ORDER BY p.nome',
                [':u' => (int) $usuario['id']]
            );
            $usuario['sessoes'] = (int) Db::escalar(
                'SELECT COUNT(*) FROM control_sessoes WHERE usuario_id = :u AND revogada_em IS NULL AND expira_em > :agora',
                [':u' => (int) $usuario['id'], ':agora' => now()]
            );
        }

        return $usuarios;
    }

    /** @return list<array> */
    public function papeisControl(): array
    {
        return Db::select(
            'SELECT p.*, (SELECT COUNT(*) FROM control_usuario_papeis up WHERE up.papel_id = p.id) AS usuarios
               FROM control_papeis p WHERE p.ativo = 1 ORDER BY p.id'
        );
    }

    /** @return list<array> */
    public function auditoria(string $busca, string $resultado, int $pagina, int $porPagina = 25): array
    {
        $where = ['1=1'];
        $params = [];

        if ($busca !== '') {
            $where[] = '(a.acao LIKE :busca OR a.entidade LIKE :busca OR a.modulo LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }

        if ($resultado !== '') {
            $where[] = 'a.resultado = :resultado';
            $params[':resultado'] = $resultado;
        }

        $condicao = implode(' AND ', $where);
        $offset = max(0, ($pagina - 1) * $porPagina);

        return Db::select(
            "SELECT a.*, e.nome_fantasia
               FROM auditoria a
               LEFT JOIN empresas e ON e.id = a.empresa_id
              WHERE {$condicao}
              ORDER BY a.id DESC
              LIMIT {$porPagina} OFFSET {$offset}",
            $params
        );
    }

    public function totalAuditoria(string $busca, string $resultado): int
    {
        $where = ['1=1'];
        $params = [];

        if ($busca !== '') {
            $where[] = '(acao LIKE :busca OR entidade LIKE :busca OR modulo LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }

        if ($resultado !== '') {
            $where[] = 'resultado = :resultado';
            $params[':resultado'] = $resultado;
        }

        return (int) Db::escalar('SELECT COUNT(*) FROM auditoria WHERE ' . implode(' AND ', $where), $params);
    }

    /** @return list<array> */
    public function logsRecentes(int $limite = 40): array
    {
        return Db::select(
            'SELECT * FROM logs_aplicacao ORDER BY id DESC LIMIT ' . max(1, $limite)
        );
    }

    /** @return list<array> */
    public function tarefas(): array
    {
        return Db::select('SELECT * FROM sistema_tarefas ORDER BY chave');
    }

    /** @return list<array> */
    public function sessoesAtivas(int $usuarioId): array
    {
        return Db::select(
            'SELECT * FROM control_sessoes WHERE usuario_id = :u AND revogada_em IS NULL AND expira_em > :agora ORDER BY ultimo_uso DESC',
            [':u' => $usuarioId, ':agora' => now()]
        );
    }

    /** @return list<array> */
    public function falhasLoginRecentes(int $limite = 20): array
    {
        return Db::select(
            "SELECT * FROM control_log_acessos WHERE resultado <> 'sucesso' ORDER BY id DESC LIMIT " . max(1, $limite)
        );
    }

    public function saudeBanco(): array
    {
        $tabelas = (int) Db::escalar(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
        );

        $migracoes = (int) Db::escalar('SELECT COUNT(*) FROM migracoes');
        $ultima = Db::um('SELECT arquivo, aplicada_em FROM migracoes ORDER BY versao DESC LIMIT 1');

        return ['tabelas' => $tabelas, 'migracoes' => $migracoes, 'ultima_migracao' => $ultima];
    }
}
