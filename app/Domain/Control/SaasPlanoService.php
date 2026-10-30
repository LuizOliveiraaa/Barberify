<?php

declare(strict_types=1);

namespace Barberify\Domain\Control;

use Barberify\Core\Audit;
use Barberify\Core\Db;
use Barberify\Core\Money;
use Barberify\Core\Str;
use RuntimeException;

final class SaasPlanoService
{
    private const LIMITES = [
        'max_unidades' => 'Unidades',
        'max_usuarios' => 'Usuários',
        'max_profissionais' => 'Profissionais',
        'marketplace' => 'Marketplace',
        'fidelidade' => 'Fidelidade',
        'estoque' => 'Estoque',
        'relatorios_avancados' => 'Relatórios avançados',
    ];

    public function criar(array $dados, int $atorId): int
    {
        $nome = trim((string) $dados['nome']);
        $slug = Str::slug($dados['slug'] ?? $nome, 40);

        if (Db::um('SELECT id FROM saas_planos WHERE slug = :s', [':s' => $slug]) !== null) {
            throw new RuntimeException('Já existe um plano com este identificador.');
        }

        return Db::tx(function () use ($dados, $nome, $slug, $atorId): int {
            $id = Db::inserir('saas_planos', [
                'nome'          => $nome,
                'slug'          => $slug,
                'descricao'     => trim((string) ($dados['descricao'] ?? '')) ?: null,
                'preco'         => Money::paraDecimal($dados['preco'] ?? 0),
                'periodicidade' => in_array($dados['periodicidade'] ?? 'mensal', ['mensal', 'anual'], true) ? $dados['periodicidade'] : 'mensal',
                'trial_dias'    => max(1, (int) ($dados['trial_dias'] ?? 7)),
                'ativo'         => 1,
                'criado_em'     => now(),
            ]);

            $this->salvarLimites($id, $dados);

            Audit::registrar('plano_criado', 'control', 'saas_planos', $id, null, ['nome' => $nome], 'sucesso', null, 'control', $atorId);

            return $id;
        });
    }

    public function atualizar(int $planoId, array $dados, int $atorId): void
    {
        Db::tx(function () use ($planoId, $dados, $atorId): void {
            $plano = Db::um('SELECT * FROM saas_planos WHERE id = :id FOR UPDATE', [':id' => $planoId]);

            if ($plano === null) {
                throw new RuntimeException('Plano não encontrado.');
            }

            Db::atualizar('saas_planos', [
                'nome'          => trim((string) $dados['nome']),
                'descricao'     => trim((string) ($dados['descricao'] ?? '')) ?: null,
                'preco'         => Money::paraDecimal($dados['preco'] ?? 0),
                'periodicidade' => in_array($dados['periodicidade'] ?? 'mensal', ['mensal', 'anual'], true) ? $dados['periodicidade'] : 'mensal',
                'trial_dias'    => max(1, (int) ($dados['trial_dias'] ?? 7)),
            ], 'id = :id', [':id' => $planoId]);

            $this->salvarLimites($planoId, $dados);

            Audit::registrar('plano_atualizado', 'control', 'saas_planos', $planoId,
                ['nome' => $plano['nome'], 'preco' => $plano['preco']],
                ['nome' => $dados['nome'], 'preco' => Money::paraDecimal($dados['preco'] ?? 0)],
                'sucesso', null, 'control', $atorId);
        });
    }

    public function alternar(int $planoId, int $atorId): void
    {
        $plano = Db::um('SELECT * FROM saas_planos WHERE id = :id', [':id' => $planoId]);

        if ($plano === null) {
            throw new RuntimeException('Plano não encontrado.');
        }

        $novo = (int) $plano['ativo'] === 1 ? 0 : 1;
        Db::atualizar('saas_planos', ['ativo' => $novo], 'id = :id', [':id' => $planoId]);

        Audit::registrar('plano_alternado', 'control', 'saas_planos', $planoId,
            ['ativo' => (int) $plano['ativo']], ['ativo' => $novo], 'sucesso', null, 'control', $atorId);
    }

    /** @return array<string,string> */
    public static function limitesDisponiveis(): array
    {
        return self::LIMITES;
    }

    private function salvarLimites(int $planoId, array $dados): void
    {
        foreach (array_keys(self::LIMITES) as $chave) {
            $valor = trim((string) ($dados['limite_' . $chave] ?? '0'));
            if ($valor === '') {
                $valor = '0';
            }

            Db::executar(
                'INSERT INTO saas_plano_limites (plano_id, chave, tipo, valor) VALUES (:p, :c, :t, :v)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
                [':p' => $planoId, ':c' => $chave, ':t' => 'limite', ':v' => $valor]
            );
        }
    }
}
