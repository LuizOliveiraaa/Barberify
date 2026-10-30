<?php

declare(strict_types=1);

namespace Barberify\Domain\Control;

use Barberify\Core\Audit;
use Barberify\Core\Db;
use Barberify\Core\Money;
use Barberify\Core\Passwords;
use Barberify\Core\Str;
use Barberify\Domain\Sistema\Seeder;
use RuntimeException;

final class EmpresaService
{
    /**
     * Cria empresa, unidade principal, papéis, configurações, proprietário e
     * assinatura de teste de 7 dias — tudo na mesma transação.
     *
     * @return array{empresa_id:int, usuario_id:int, senha_temporaria:?string}
     */
    public function criar(array $dados, int $atorId): array
    {
        $nomeFantasia = trim((string) $dados['nome_fantasia']);
        $slug         = Str::slug($dados['slug'] ?? $nomeFantasia, 60);
        $documento    = preg_replace('/\D+/', '', (string) ($dados['cpf_cnpj'] ?? '')) ?: null;

        if (Db::um('SELECT id FROM empresas WHERE slug = :s', [':s' => $slug]) !== null) {
            throw new RuntimeException('Já existe uma empresa com este identificador (slug).');
        }

        if ($documento !== null && Db::um('SELECT id FROM empresas WHERE cpf_cnpj = :d', [':d' => $documento]) !== null) {
            throw new RuntimeException('Já existe uma empresa com este CPF/CNPJ.');
        }

        $senhaTemporaria = null;
        if (trim((string) ($dados['senha'] ?? '')) === '') {
            $senhaTemporaria = Passwords::temporaria(10);
        }

        return Db::tx(function () use ($dados, $nomeFantasia, $slug, $documento, $atorId, $senhaTemporaria): array {
            $empresaId = Db::inserir('empresas', [
                'slug'              => $slug,
                'nome_fantasia'     => $nomeFantasia,
                'razao_social'      => trim((string) ($dados['razao_social'] ?? '')) ?: null,
                'cpf_cnpj'          => $documento,
                'email'             => trim((string) ($dados['email'] ?? '')) ?: null,
                'telefone'          => Str::telefoneNormalizado($dados['telefone'] ?? '') ?: null,
                'whatsapp'          => Str::telefoneNormalizado($dados['whatsapp'] ?? '') ?: null,
                'status'            => 'ativa',
                'trial_concedido_em' => now(),
                'origem_cadastro'   => 'control',
                'criado_em'         => now(),
            ]);

            $unidadeId = Db::inserir('unidades', [
                'empresa_id'  => $empresaId,
                'nome'        => 'Unidade Principal',
                'slug'        => 'principal',
                'telefone'    => Str::telefoneNormalizado($dados['telefone'] ?? '') ?: null,
                'whatsapp'    => Str::telefoneNormalizado($dados['whatsapp'] ?? '') ?: null,
                'cidade'      => trim((string) ($dados['cidade'] ?? '')) ?: null,
                'uf'          => strtoupper(substr(trim((string) ($dados['uf'] ?? '')), 0, 2)) ?: null,
                'aceita_agendamento_online' => 1,
                'status'      => 'ativa',
                'criado_em'   => now(),
            ]);

            (new Seeder())->padroesEmpresa($empresaId, $unidadeId);

            $usuarioId = Db::inserir('usuarios_empresa', [
                'empresa_id'    => $empresaId,
                'nome'          => trim((string) $dados['proprietario_nome']),
                'email'         => mb_strtolower(trim((string) $dados['proprietario_email'])),
                'telefone'      => Str::telefoneNormalizado($dados['proprietario_telefone'] ?? '') ?: null,
                'senha_hash'    => Passwords::hash((string) ($dados['senha'] ?? $senhaTemporaria)),
                'is_proprietario' => 1,
                'status'        => 'ativo',
                'cargo'         => 'Proprietário',
                'trocar_senha'  => $senhaTemporaria === null ? 0 : 1,
                'criado_em'     => now(),
            ]);

            $papel = Db::um('SELECT id FROM papeis WHERE empresa_id = :e AND slug = :s', [':e' => $empresaId, ':s' => 'proprietario']);
            if ($papel !== null) {
                Db::inserir('usuario_papeis', [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'papel_id'   => (int) $papel['id'],
                    'criado_em'  => now(),
                ]);
            }

            Db::inserir('usuario_empresa_unidades', [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'unidade_id' => $unidadeId,
            ]);

            $plano = Db::um(
                'SELECT * FROM saas_planos WHERE ativo = 1 AND excluido_em IS NULL ORDER BY ordem LIMIT 1'
            );

            if ($plano === null) {
                throw new RuntimeException('Nenhum plano SaaS ativo cadastrado. Rode as seeds antes de criar empresas.');
            }

            $limites = [];
            foreach (Db::select('SELECT chave, valor FROM saas_plano_limites WHERE plano_id = :p', [':p' => (int) $plano['id']]) as $limite) {
                $limites[(string) $limite['chave']] = (string) $limite['valor'];
            }

            $trialDias = max(1, (int) $plano['trial_dias']);
            $assinaturaId = Db::inserir('saas_assinaturas', [
                'empresa_id'       => $empresaId,
                'plano_id'         => (int) $plano['id'],
                'status'           => 'em_teste',
                'inicio'           => hoje(),
                'fim_teste'        => date('Y-m-d', strtotime('+' . $trialDias . ' days')),
                'proximo_vencimento' => date('Y-m-d', strtotime('+' . $trialDias . ' days')),
                'preco_snapshot'   => Money::paraDecimal($plano['preco']),
                'periodicidade_snapshot' => (string) $plano['periodicidade'],
                'limites_snapshot' => json_encode($limites, JSON_UNESCAPED_UNICODE),
                'renovacao_automatica' => 1,
                'criado_em'        => now(),
            ]);

            Db::inserir('saas_assinatura_eventos', [
                'assinatura_id' => $assinaturaId,
                'empresa_id'    => $empresaId,
                'de'            => null,
                'para'          => 'em_teste',
                'motivo'        => sprintf('Teste gratuito de %d dias concedido no cadastro.', $trialDias),
                'ator_tipo'     => 'control',
                'ator_id'       => $atorId,
                'criado_em'     => now(),
            ]);

            Db::inserir('saas_faturas', [
                'assinatura_id' => $assinaturaId,
                'empresa_id'    => $empresaId,
                'competencia'   => date('Y-m', strtotime('+' . $trialDias . ' days')),
                'valor'         => Money::paraDecimal($plano['preco']),
                'vencimento'    => date('Y-m-d', strtotime('+' . $trialDias . ' days')),
                'status'        => 'aberta',
                'criado_em'     => now(),
            ]);

            Audit::registrar(
                'empresa_criada',
                'control',
                'empresas',
                $empresaId,
                null,
                ['nome' => $nomeFantasia, 'slug' => $slug, 'plano' => $plano['nome']],
                'sucesso',
                $empresaId,
                'control',
                $atorId
            );

            return ['empresa_id' => $empresaId, 'usuario_id' => $usuarioId, 'senha_temporaria' => $senhaTemporaria];
        });
    }

    public function suspender(int $empresaId, string $motivo, int $atorId): void
    {
        Db::tx(function () use ($empresaId, $motivo, $atorId): void {
            $empresa = Db::um('SELECT * FROM empresas WHERE id = :id FOR UPDATE', [':id' => $empresaId]);

            if ($empresa === null) {
                throw new RuntimeException('Empresa não encontrada.');
            }

            if ((string) $empresa['status'] === 'cancelada') {
                throw new RuntimeException('Empresa cancelada não pode ser suspensa.');
            }

            Db::atualizar('empresas', [
                'status'        => 'suspensa',
                'suspensa_em'   => now(),
                'motivo_status' => mb_substr($motivo, 0, 255),
            ], 'id = :id', [':id' => $empresaId]);

            $this->mudarAssinatura($empresaId, 'suspensa', 'Empresa suspensa pela administração: ' . $motivo, $atorId);

            Audit::registrar('empresa_suspensa', 'control', 'empresas', $empresaId, ['status' => $empresa['status']],
                ['status' => 'suspensa', 'motivo' => $motivo], 'sucesso', $empresaId, 'control', $atorId);
        });
    }

    public function reativar(int $empresaId, int $atorId): void
    {
        Db::tx(function () use ($empresaId, $atorId): void {
            $empresa = Db::um('SELECT * FROM empresas WHERE id = :id FOR UPDATE', [':id' => $empresaId]);

            if ($empresa === null) {
                throw new RuntimeException('Empresa não encontrada.');
            }

            Db::atualizar('empresas', [
                'status'        => 'ativa',
                'suspensa_em'   => null,
                'motivo_status' => null,
            ], 'id = :id', [':id' => $empresaId]);

            $this->mudarAssinatura($empresaId, 'ativa', 'Empresa reativada pela administração.', $atorId);

            Audit::registrar('empresa_reativada', 'control', 'empresas', $empresaId, ['status' => $empresa['status']],
                ['status' => 'ativa'], 'sucesso', $empresaId, 'control', $atorId);
        });
    }

    public function cancelar(int $empresaId, string $motivo, int $atorId): void
    {
        Db::tx(function () use ($empresaId, $motivo, $atorId): void {
            $empresa = Db::um('SELECT * FROM empresas WHERE id = :id FOR UPDATE', [':id' => $empresaId]);

            if ($empresa === null) {
                throw new RuntimeException('Empresa não encontrada.');
            }

            Db::atualizar('empresas', [
                'status'        => 'cancelada',
                'cancelada_em'  => now(),
                'motivo_status' => mb_substr($motivo, 0, 255),
            ], 'id = :id', [':id' => $empresaId]);

            $this->mudarAssinatura($empresaId, 'cancelada', 'Assinatura cancelada: ' . $motivo, $atorId);

            Audit::registrar('empresa_cancelada', 'control', 'empresas', $empresaId, ['status' => $empresa['status']],
                ['status' => 'cancelada', 'motivo' => $motivo], 'sucesso', $empresaId, 'control', $atorId);
        });
    }

    public function registrarObservacao(int $empresaId, string $texto, int $atorId): void
    {
        Db::inserir('control_observacoes_empresa', [
            'empresa_id' => $empresaId,
            'usuario_id' => $atorId,
            'texto'      => trim($texto),
            'criado_em'  => now(),
        ]);

        Audit::registrar('observacao_registrada', 'control', 'empresas', $empresaId, null,
            ['texto' => Str::resumir($texto, 120)], 'sucesso', $empresaId, 'control', $atorId);
    }

    private function mudarAssinatura(int $empresaId, string $status, string $motivo, int $atorId): void
    {
        $assinatura = Db::um(
            'SELECT * FROM saas_assinaturas WHERE empresa_id = :e ORDER BY id DESC LIMIT 1 FOR UPDATE',
            [':e' => $empresaId]
        );

        if ($assinatura === null || (string) $assinatura['status'] === $status) {
            return;
        }

        Db::atualizar('saas_assinaturas', [
            'status'                 => $status,
            'cancelada_em'           => $status === 'cancelada' ? now() : $assinatura['cancelada_em'],
            'motivo_cancelamento'    => $status === 'cancelada' ? mb_substr($motivo, 0, 255) : $assinatura['motivo_cancelamento'],
        ], 'id = :id', [':id' => (int) $assinatura['id']]);

        Db::inserir('saas_assinatura_eventos', [
            'assinatura_id' => (int) $assinatura['id'],
            'empresa_id'    => $empresaId,
            'de'            => (string) $assinatura['status'],
            'para'          => $status,
            'motivo'        => mb_substr($motivo, 0, 255),
            'ator_tipo'     => 'control',
            'ator_id'       => $atorId,
            'criado_em'     => now(),
        ]);
    }
}
