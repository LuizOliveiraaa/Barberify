# Barberify — Planejamento Definitivo

**Empresa proprietária:** Soluções AL
**Produto:** Barberify
**Central administrativa:** Barberify Control
**Status deste documento:** entrega 1 (planejamento) — aguardando aprovação para iniciar a Etapa 3
**Data:** 30/10/2026

---

## 1. O que é esta entrega

Esta é a **primeira entrega** exigida pela seção 36 do Prompt Mestre: planejamento completo, sem
implementação. Nenhuma tabela foi criada, nenhuma página foi escrita, nenhum dado fictício foi
produzido. O repositório contém apenas `README.md`, `LICENSE` e o commit inicial — ou seja, o
projeto parte de zero e nada do que está descrito aqui foi presumido como existente.

A entrega cobre os 17 itens solicitados:

| # | Item exigido | Documento / seção |
|---|---|---|
| 1 | Análise de viabilidade na InfinityFree | [01-VIABILIDADE-E-ARQUITETURA.md](01-VIABILIDADE-E-ARQUITETURA.md) §1 |
| 2 | Arquitetura técnica definitiva | [01-VIABILIDADE-E-ARQUITETURA.md](01-VIABILIDADE-E-ARQUITETURA.md) §2 |
| 3 | Estrutura completa de diretórios | [01-VIABILIDADE-E-ARQUITETURA.md](01-VIABILIDADE-E-ARQUITETURA.md) §3 |
| 4 | Modelo multiempresa | [02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md](02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md) §1 |
| 5 | Identidade global do proprietário | [02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md](02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md) §2 |
| 6 | Arquitetura do Barberify Control | [02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md](02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md) §3 |
| 7 | Autenticação e permissões | [02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md](02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md) §4 |
| 8 | Arquitetura do marketplace | [03-MARKETPLACE-AGENDAMENTO-ASSINATURAS.md](03-MARKETPLACE-AGENDAMENTO-ASSINATURAS.md) §1 |
| 9 | Fluxo de agendamento e WhatsApp | [03-MARKETPLACE-AGENDAMENTO-ASSINATURAS.md](03-MARKETPLACE-AGENDAMENTO-ASSINATURAS.md) §2 |
| 10 | Arquitetura dos dois modelos de assinatura | [03-MARKETPLACE-AGENDAMENTO-ASSINATURAS.md](03-MARKETPLACE-AGENDAMENTO-ASSINATURAS.md) §3 |
| 11 | Modelo completo do banco de dados | [04-BANCO-DE-DADOS.md](04-BANCO-DE-DADOS.md) |
| 12 | Diagrama de relacionamentos | [05-DIAGRAMAS-ER.md](05-DIAGRAMAS-ER.md) |
| 13 | Matriz de dependências entre módulos | [06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md](06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md) §1 |
| 14 | Plano de desenvolvimento | [06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md](06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md) §2 |
| 15 | Estratégia de segurança | [06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md](06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md) §3 |
| 16 | Estratégia de testes | [06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md](06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md) §4 |
| 17 | Riscos técnicos e alternativas | [06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md](06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md) §5 |
| — | Decisões que precisam de resposta antes da Etapa 3 | [07-DECISOES-PENDENTES.md](07-DECISOES-PENDENTES.md) |

## 2. Princípios que governam todo o desenho

1. **Independência total do Soluções AL Hub.** Nenhuma tabela, sessão, API ou deploy do Barberify
   referencia o Hub. O produto é autônomo: banco próprio, autenticação própria, administração própria.
2. **Três identidades separadas, nunca misturadas:**
   - **Proprietário Global / equipe interna** → tabelas `control_*`, sem `empresa_id`;
   - **Usuário da barbearia** → tabelas `usuarios_empresa*`, sempre com `empresa_id`;
   - **Consumidor final** → `contas_cliente`, identidade opcional e independente.
3. **Dois modelos de assinatura separados** até no nome das tabelas: `saas_*` (barbearia paga à
   Soluções AL) e `fidelidade_*` (consumidor paga à barbearia). Nenhum ledger, serviço ou tela é
   compartilhado entre eles.
4. **Dinheiro e histórico são imutáveis.** Valores em `DECIMAL`, movimentos financeiros e de
   fidelidade/estoque são lançamentos (ledger) que só recebem estorno, nunca `UPDATE` de valor.
   Preços, comissões e regras são copiados por snapshot no momento da operação.
5. **Nada depende de cron.** A hospedagem alvo não oferece agendamento e proíbe requisições
   periódicas automatizadas. Expiração de reserva, cobrança e ciclos funcionam por predicado no
   banco + um *scheduler preguiçoso* executado dentro de requisições reais.
6. **Segurança é validação de servidor.** `empresa_id` nunca vem do navegador; toda operação
   resolve o contexto no servidor e valida permissão no backend, não apenas escondendo botões.
7. **Sem funcionalidade de fachada.** Cada tela que entra na Etapa N grava e lê dados reais, com
   teste executado e registrado. Se algo não puder funcionar na hospedagem, é declarado como
   limitação com alternativa — não simulado.

## 3. Resumo das decisões estruturais

| Tema | Decisão | Onde está detalhado |
|---|---|---|
| Stack | PHP 8.2+ (alvo de execução 8.4), MySQL/MariaDB, PDO, Bootstrap 5 local, JS sem build | 01 §1–2 |
| Padrão | Monólito modular MVC com front controller, serviços de domínio e repositórios SQL explícitos | 01 §2 |
| Docroot | Layout A (`public/` como raiz) e Layout B (InfinityFree: `htdocs` + pastas privadas protegidas) | 01 §3 |
| Tenant | `empresa_id` obrigatório + `unidade_id` quando aplicável, índices compostos iniciando por `empresa_id` | 02 §1 |
| Sessões | Token opaco em cookie + hash no banco, três cookies/realms independentes (`bf_emp`, `bf_ctl`, `bf_cli`) | 02 §4 |
| Permissões | Catálogo `modulo.acao` no banco + middleware no backend + papéis por empresa | 02 §4 |
| Marketplace | Listagem por unidade (proximidade) e perfil por empresa; visível por padrão com opt-out | 03 §1 |
| Reserva | Hold de 15 min por `reserva_token` + expiração por predicado; confirmação idempotente no servidor | 03 §2 |
| Fidelidade | Ciclos segunda→domingo, limites independentes, consumo no atendimento concluído, ledger imutável | 03 §3 |
| Banco | 113 tabelas em 15 domínios (A–O), InnoDB, `utf8mb4_unicode_ci`, FK + índices compostos, migrations versionadas | 04 |
| Cron | Scheduler preguiçoso com lock em `sistema_tarefas` + nada crítico dependente dele | 01 §5 |
| E-mail | Fila `email_fila` + SMTP 587/TLS; se indisponível, fallback por códigos de recuperação e avisos internos | 01 §1, 06 §5 |

## 4. Fora de escopo desta entrega (por exigência do próprio Prompt Mestre)

- Criação de tabelas, migrations executáveis, páginas, controllers, assets.
- Gateway de pagamento (somente preparação de arquitetura).
- PWA / aplicativo instalável.
- Automação de WhatsApp por API oficial (v1 é link `wa.me` com mensagem pré-preenchida).

A implementação começa na Etapa 3, **depois da aprovação deste planejamento e das respostas de
[07-DECISOES-PENDENTES.md](07-DECISOES-PENDENTES.md)**.
