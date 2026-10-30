<?php

declare(strict_types=1);

namespace Barberify\Domain\Sistema;

use Barberify\Core\Db;
use Barberify\Core\Money;
use Barberify\Core\Str;

/**
 * Dados iniciais idempotentes: catálogo de permissões, papéis do Control,
 * planos SaaS e configurações globais. Pode rodar mais de uma vez.
 */
final class Seeder
{
    /** @var array<string,string> chave => descrição */
    public const PERMISSOES_EMPRESA = [
        'dashboard.ver' => 'Ver o dashboard da barbearia',
        'agenda.ver' => 'Ver a agenda',
        'agenda.criar' => 'Criar agendamento',
        'agenda.editar' => 'Editar agendamento',
        'agenda.cancelar' => 'Cancelar agendamento',
        'agenda.reagendar' => 'Reagendar atendimento',
        'agenda.bloquear' => 'Bloquear horários, folgas e férias',
        'agenda.confirmar' => 'Confirmar agendamento',
        'atendimento.ver' => 'Ver atendimentos',
        'atendimento.iniciar' => 'Iniciar atendimento',
        'atendimento.concluir' => 'Concluir atendimento',
        'atendimento.reabrir' => 'Reabrir atendimento',
        'clientes.ver' => 'Ver clientes',
        'clientes.criar' => 'Cadastrar cliente',
        'clientes.editar' => 'Editar cliente',
        'clientes.excluir' => 'Excluir cliente',
        'clientes.exportar' => 'Exportar clientes',
        'clientes.ver-dados-sensiveis' => 'Ver dados sensíveis do cliente',
        'profissionais.ver' => 'Ver profissionais',
        'profissionais.criar' => 'Cadastrar profissional',
        'profissionais.editar' => 'Editar profissional',
        'profissionais.excluir' => 'Excluir profissional',
        'profissionais.ver-comissoes' => 'Ver comissões do profissional',
        'servicos.ver' => 'Ver serviços',
        'servicos.criar' => 'Cadastrar serviço',
        'servicos.editar' => 'Editar serviço',
        'servicos.excluir' => 'Excluir serviço',
        'servicos.gerenciar-precos' => 'Gerenciar preços',
        'unidades.ver' => 'Ver unidades',
        'unidades.gerenciar' => 'Gerenciar unidades',
        'unidades.todas' => 'Acessar todas as unidades',
        'caixa.ver' => 'Ver caixa',
        'caixa.abrir' => 'Abrir caixa',
        'caixa.fechar' => 'Fechar caixa',
        'caixa.movimentar' => 'Movimentar caixa',
        'caixa.sangria' => 'Realizar sangria',
        'financeiro.ver' => 'Ver financeiro',
        'financeiro.lancar' => 'Lançar receita/despesa',
        'financeiro.editar' => 'Editar lançamento',
        'financeiro.estornar' => 'Estornar lançamento',
        'financeiro.ver-lucro' => 'Ver lucro e resultado',
        'comissoes.ver' => 'Ver comissões',
        'comissoes.fechar' => 'Fechar comissões',
        'comissoes.ajustar' => 'Ajustar comissão',
        'comissoes.pagar' => 'Pagar comissão',
        'estoque.ver' => 'Ver estoque',
        'estoque.movimentar' => 'Movimentar estoque',
        'estoque.ajustar' => 'Ajustar estoque',
        'estoque.inventario' => 'Realizar inventário',
        'produtos.ver' => 'Ver produtos',
        'produtos.criar' => 'Cadastrar produto',
        'produtos.editar' => 'Editar produto',
        'produtos.excluir' => 'Excluir produto',
        'vendas.ver' => 'Ver vendas',
        'vendas.criar' => 'Registrar venda',
        'vendas.cancelar' => 'Cancelar venda',
        'vendas.aplicar-desconto' => 'Aplicar desconto',
        'fidelidade.ver' => 'Ver fidelidade',
        'fidelidade.planos' => 'Gerenciar planos de fidelidade',
        'fidelidade.assinaturas' => 'Gerenciar assinaturas de clientes',
        'fidelidade.consumir' => 'Registrar consumo de benefício',
        'fidelidade.estornar' => 'Estornar benefício',
        'fidelidade.configurar' => 'Configurar fidelidade',
        'marketing.ver' => 'Ver marketing',
        'marketing.campanhas' => 'Gerenciar campanhas',
        'marketing.vitrine' => 'Gerenciar ofertas da vitrine',
        'marketing.indicacoes' => 'Gerenciar indicações',
        'relatorios.ver' => 'Ver relatórios',
        'relatorios.exportar' => 'Exportar relatórios',
        'relatorios.financeiros' => 'Ver relatórios financeiros',
        'inteligencia.ver' => 'Ver inteligência e oportunidades',
        'inteligencia.configurar' => 'Configurar inteligência',
        'usuarios.ver' => 'Ver usuários',
        'usuarios.criar' => 'Criar usuário',
        'usuarios.editar' => 'Editar usuário',
        'usuarios.desativar' => 'Desativar usuário',
        'usuarios.gerenciar-permissoes' => 'Gerenciar papéis e permissões',
        'configuracoes.ver' => 'Ver configurações',
        'configuracoes.editar' => 'Editar configurações',
        'configuracoes.faturamento' => 'Configurar faturamento',
        'auditoria.ver' => 'Ver auditoria',
    ];

    /** @var array<string,string> */
    public const PERMISSOES_CONTROL = [
        'control.dashboard.ver' => 'Ver dashboard global',
        'control.empresas.ver' => 'Ver empresas',
        'control.empresas.criar' => 'Cadastrar empresa',
        'control.empresas.editar' => 'Editar empresa',
        'control.empresas.suspender' => 'Suspender, reativar ou cancelar empresa',
        'control.planos.ver' => 'Ver planos SaaS',
        'control.planos.gerenciar' => 'Criar e editar planos SaaS',
        'control.assinaturas.ver' => 'Ver assinaturas SaaS',
        'control.assinaturas.gerenciar' => 'Gerenciar assinaturas SaaS',
        'control.assinaturas.pagamentos' => 'Registrar pagamentos SaaS',
        'control.financeiro.ver' => 'Ver financeiro global',
        'control.financeiro.registrar' => 'Registrar recebimento global',
        'control.equipe.ver' => 'Ver equipe interna',
        'control.equipe.gerenciar' => 'Gerenciar equipe interna',
        'control.suporte.ver' => 'Ver tickets de suporte',
        'control.suporte.responder' => 'Responder tickets',
        'control.suporte.acesso-dados' => 'Aprovar acesso a dados de empresas',
        'control.comunicados.ver' => 'Ver comunicados',
        'control.comunicados.publicar' => 'Publicar comunicados',
        'control.monitoramento.ver' => 'Ver monitoramento',
        'control.auditoria.ver' => 'Ver auditoria global',
        'control.configuracoes.ver' => 'Ver configurações globais',
        'control.configuracoes.editar' => 'Editar configurações globais',
        'control.configuracoes.criticas' => 'Editar configurações críticas',
        'control.proprietario.self' => 'Gerenciar a própria conta global',
    ];

    /** @var array<string,list<string>> */
    private const PAPEIS_EMPRESA = [
        'proprietario' => ['*'],
        'administrador' => ['*'],
        'gerente' => [
            'dashboard.ver', 'agenda.*', 'atendimento.*', 'clientes.ver', 'clientes.criar', 'clientes.editar',
            'clientes.exportar', 'profissionais.ver', 'profissionais.criar', 'profissionais.editar',
            'profissionais.ver-comissoes', 'servicos.*', 'unidades.ver', 'caixa.ver', 'financeiro.ver',
            'comissoes.ver', 'estoque.ver', 'estoque.movimentar', 'produtos.*', 'vendas.*', 'fidelidade.*',
            'marketing.*', 'relatorios.ver', 'relatorios.exportar', 'inteligencia.ver', 'usuarios.ver',
            'configuracoes.ver',
        ],
        'recepcionista' => [
            'dashboard.ver', 'agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.cancelar',
            'agenda.reagendar', 'agenda.confirmar', 'atendimento.ver', 'atendimento.iniciar',
            'atendimento.concluir', 'clientes.ver', 'clientes.criar', 'clientes.editar',
            'profissionais.ver', 'servicos.ver', 'caixa.ver', 'caixa.abrir', 'caixa.fechar',
            'caixa.movimentar', 'vendas.ver', 'vendas.criar', 'fidelidade.ver', 'fidelidade.consumir',
            'relatorios.ver',
        ],
        'barbeiro' => [
            'dashboard.ver', 'agenda.ver', 'atendimento.ver', 'atendimento.iniciar',
            'atendimento.concluir', 'clientes.ver', 'servicos.ver', 'relatorios.ver',
        ],
        'financeiro' => [
            'dashboard.ver', 'clientes.ver', 'caixa.*', 'financeiro.*', 'comissoes.*',
            'relatorios.*', 'configuracoes.ver', 'auditoria.ver', 'profissionais.ver-comissoes',
        ],
        'estoquista' => [
            'dashboard.ver', 'estoque.*', 'produtos.*', 'relatorios.ver',
        ],
    ];

    /** @var array<string,list<string>> */
    private const PAPEIS_CONTROL = [
        'proprietario-global' => ['*'],
        'administrador-plataforma' => ['control.*'],
        'financeiro' => [
            'control.dashboard.ver', 'control.empresas.ver', 'control.assinaturas.ver',
            'control.assinaturas.pagamentos', 'control.financeiro.ver', 'control.financeiro.registrar',
            'control.auditoria.ver',
        ],
        'comercial' => [
            'control.dashboard.ver', 'control.empresas.ver', 'control.empresas.criar',
            'control.empresas.editar', 'control.planos.ver', 'control.assinaturas.ver',
            'control.assinaturas.gerenciar', 'control.financeiro.ver',
        ],
        'suporte' => [
            'control.dashboard.ver', 'control.empresas.ver', 'control.suporte.ver',
            'control.suporte.responder', 'control.monitoramento.ver',
        ],
        'desenvolvimento' => [
            'control.dashboard.ver', 'control.empresas.ver', 'control.monitoramento.ver',
            'control.auditoria.ver',
        ],
    ];

    public function executar(): void
    {
        $this->permissoes();
        $this->papeisControl();
        $this->planosSaas();
        $this->configuracoesGlobais();
    }

    public function permissoes(): void
    {
        foreach (self::PERMISSOES_EMPRESA as $chave => $descricao) {
            $this->garantirPermissao('permissoes', $chave, $descricao);
        }

        foreach (self::PERMISSOES_CONTROL as $chave => $descricao) {
            $this->garantirPermissao('control_permissoes', $chave, $descricao);
        }
    }

    public function papeisControl(): void
    {
        foreach (self::PAPEIS_CONTROL as $slug => $permissoes) {
            $papel = Db::um('SELECT id FROM control_papeis WHERE slug = :slug', [':slug' => $slug]);

            if ($papel === null) {
                $id = Db::inserir('control_papeis', [
                    'nome'      => $this->titulo($slug),
                    'slug'      => $slug,
                    'descricao' => 'Papel padrão da equipe interna',
                    'sistema'   => 1,
                    'ativo'     => 1,
                ]);
            } else {
                $id = (int) $papel['id'];
            }

            $this->vincularControl($id, $permissoes);
        }
    }

    public function planosSaas(): void
    {
        $planos = [
            [
                'nome' => 'Essencial', 'slug' => 'essencial',
                'descricao' => 'Para a barbearia que está organizando a operação.',
                'preco' => 79.90,
                'limites' => ['max_unidades' => '1', 'max_usuarios' => '3', 'max_profissionais' => '5',
                    'marketplace' => '1', 'fidelidade' => '0', 'estoque' => '0', 'relatorios_avancados' => '0'],
            ],
            [
                'nome' => 'Profissional', 'slug' => 'profissional',
                'descricao' => 'Marketplace, fidelidade e controle de estoque incluídos.',
                'preco' => 149.90,
                'limites' => ['max_unidades' => '2', 'max_usuarios' => '10', 'max_profissionais' => '15',
                    'marketplace' => '1', 'fidelidade' => '1', 'estoque' => '1', 'relatorios_avancados' => '1'],
            ],
            [
                'nome' => 'Premium', 'slug' => 'premium',
                'descricao' => 'Para redes de barbearias com inteligência e relatórios avançados.',
                'preco' => 249.90,
                'limites' => ['max_unidades' => '5', 'max_usuarios' => '25', 'max_profissionais' => '999',
                    'marketplace' => '1', 'fidelidade' => '1', 'estoque' => '1', 'relatorios_avancados' => '1'],
            ],
        ];

        foreach ($planos as $ordem => $dados) {
            $plano = Db::um('SELECT id FROM saas_planos WHERE slug = :slug', [':slug' => $dados['slug']]);

            if ($plano === null) {
                $id = Db::inserir('saas_planos', [
                    'nome'          => $dados['nome'],
                    'slug'          => $dados['slug'],
                    'descricao'     => $dados['descricao'],
                    'preco'         => Money::paraDecimal($dados['preco']),
                    'periodicidade' => 'mensal',
                    'trial_dias'    => 7,
                    'ordem'         => $ordem,
                    'ativo'         => 1,
                ]);
            } else {
                $id = (int) $plano['id'];
            }

            foreach ($dados['limites'] as $chave => $valor) {
                $existe = Db::um('SELECT id FROM saas_plano_limites WHERE plano_id = :p AND chave = :c', [':p' => $id, ':c' => $chave]);

                if ($existe === null) {
                    Db::inserir('saas_plano_limites', [
                        'plano_id' => $id,
                        'chave'    => $chave,
                        'tipo'     => 'limite',
                        'valor'    => $valor,
                    ]);
                }
            }
        }
    }

    public function configuracoesGlobais(): void
    {
        $padroes = [
            'plataforma.nome'                 => ['Barberify', 'string', 0],
            'plataforma.proprietario'         => ['Soluções AL', 'string', 0],
            'saas.trial_dias'                 => ['7', 'int', 1],
            'saas.graca_vencida_dias'         => ['5', 'int', 1],
            'saas.graca_inadimplente_dias'    => ['5', 'int', 1],
            'saas.suspender_automaticamente'  => ['1', 'bool', 1],
            'cadastro.publico_habilitado'     => ['0', 'bool', 1],
            'retencao.auditoria_meses'        => ['24', 'int', 0],
            'retencao.logs_dias'              => ['90', 'int', 0],
            'email.driver'                    => ['null', 'string', 0],
        ];

        foreach ($padroes as $chave => [$valor, $tipo, $critica]) {
            $existe = Db::um('SELECT chave FROM control_configuracoes WHERE chave = :c', [':c' => $chave]);

            if ($existe === null) {
                Db::inserir('control_configuracoes', [
                    'chave'   => $chave,
                    'valor'   => $valor,
                    'tipo'    => $tipo,
                    'critica' => $critica,
                ]);
            }
        }
    }

    /** Papéis padrão, categorias, formas de pagamento e configurações de uma nova empresa. */
    public function padroesEmpresa(int $empresaId, int $unidadeId): void
    {
        $configuracoes = [
            'agenda.antecedencia_min'   => '30',
            'agenda.janela_dias'        => '60',
            'agenda.grade_min'          => '15',
            'agenda.intervalo_servicos' => '5',
            'agenda.hold_minutos'       => '15',
            'cancelamento.horas'        => '4',
            'avaliacao.janela_dias'     => '30',
            'estoque.permite_negativo'  => '0',
            'fidelidade.libera_sem_pagamento' => '0',
            'marketplace.visivel'       => '1',
        ];

        foreach ($configuracoes as $chave => $valor) {
            Db::executar(
                'INSERT INTO empresa_configuracoes (empresa_id, chave, valor, tipo) VALUES (:e, :c, :v, :t)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
                [':e' => $empresaId, ':c' => $chave, ':v' => $valor, ':t' => 'string']
            );
        }

        $permissoes = Db::select('SELECT id, chave FROM permissoes');

        foreach (self::PAPEIS_EMPRESA as $slug => $conjunto) {
            $existente = Db::um('SELECT id FROM papeis WHERE empresa_id = :e AND slug = :s', [':e' => $empresaId, ':s' => $slug]);

            if ($existente === null) {
                $papelId = Db::inserir('papeis', [
                    'empresa_id' => $empresaId,
                    'nome'       => $this->titulo($slug),
                    'slug'       => $slug,
                    'descricao'  => 'Papel padrão do sistema',
                    'sistema'    => 1,
                    'ativo'      => 1,
                ]);
            } else {
                $papelId = (int) $existente['id'];
            }

            foreach ($permissoes as $permissao) {
                if (!$this->corresponde((string) $permissao['chave'], $conjunto)) {
                    continue;
                }

                Db::executar(
                    'INSERT IGNORE INTO papel_permissoes (empresa_id, papel_id, permissao_id) VALUES (:e, :p, :perm)',
                    [':e' => $empresaId, ':p' => $papelId, ':perm' => (int) $permissao['id']]
                );
            }
        }

        $categorias = ['Cabelo', 'Barba', 'Acabamento e pezinho', 'Tratamentos capilares', 'Sobrancelha', 'Combos', 'Outros'];
        foreach ($categorias as $ordem => $nome) {
            Db::executar(
                'INSERT IGNORE INTO servico_categorias (empresa_id, nome, slug, ordem, ativo) VALUES (:e, :n, :s, :o, 1)',
                [':e' => $empresaId, ':n' => $nome, ':s' => Str::slug($nome, 40), ':o' => $ordem]
            );
        }

        $formas = [
            ['Dinheiro', 'dinheiro'],
            ['Pix', 'pix'],
            ['Cartão de débito', 'cartao_debito'],
            ['Cartão de crédito', 'cartao_credito'],
        ];
        foreach ($formas as $ordem => [$nome, $tipo]) {
            Db::executar(
                'INSERT IGNORE INTO formas_pagamento (empresa_id, nome, tipo, taxa_percentual, afeta_caixa, ativo, ordem)
                 VALUES (:e, :n, :t, 0, 1, 1, :o)',
                [':e' => $empresaId, ':n' => $nome, ':t' => $tipo, ':o' => $ordem]
            );
        }

        foreach ([['Vendas de serviços', 'receita'], ['Vendas de produtos', 'receita'], ['Assinaturas', 'receita'],
                  ['Despesas operacionais', 'despesa'], ['Comissões', 'despesa']] as [$nome, $tipo]) {
            Db::executar(
                'INSERT IGNORE INTO financeiro_categorias (empresa_id, nome, tipo, ativo) VALUES (:e, :n, :t, 1)',
                [':e' => $empresaId, ':n' => $nome, ':t' => $tipo]
            );
        }

        Db::executar('INSERT IGNORE INTO marketplace_perfis (empresa_id, visivel, aceita_agendamento) VALUES (:e, 1, 1)', [
            ':e' => $empresaId,
        ]);
    }

    private function garantirPermissao(string $tabela, string $chave, string $descricao): void
    {
        $existe = Db::um(sprintf('SELECT id FROM %s WHERE chave = :chave', $tabela), [':chave' => $chave]);

        if ($existe !== null) {
            return;
        }

        Db::inserir($tabela, [
            'chave'     => $chave,
            'modulo'    => explode('.', $chave)[0],
            'descricao' => $descricao,
            'critica'   => in_array($chave, ['control.configuracoes.criticas', 'control.empresas.suspender'], true) ? 1 : 0,
        ]);
    }

    /** @param list<string> $conjunto */
    private function vincularControl(int $papelId, array $conjunto): void
    {
        $permissoes = Db::select('SELECT id, chave FROM control_permissoes');

        foreach ($permissoes as $permissao) {
            if (!$this->corresponde((string) $permissao['chave'], $conjunto)) {
                continue;
            }

            Db::executar(
                'INSERT IGNORE INTO control_papel_permissoes (papel_id, permissao_id) VALUES (:p, :perm)',
                [':p' => $papelId, ':perm' => (int) $permissao['id']]
            );
        }
    }

    /** @param list<string> $conjunto */
    private function corresponde(string $chave, array $conjunto): bool
    {
        foreach ($conjunto as $padrao) {
            if ($padrao === '*') {
                return true;
            }

            if (str_ends_with($padrao, '.*') && str_starts_with($chave, substr($padrao, 0, -1))) {
                return true;
            }

            if ($padrao === $chave) {
                return true;
            }
        }

        return false;
    }

    private function titulo(string $slug): string
    {
        return ucfirst(str_replace('-', ' ', $slug));
    }
}
