# Barberify

**SaaS de gestão para barbearias com marketplace integrado — um produto Soluções AL.**

> Muito além de agendar cortes. Sua barbearia conectada, organizada e preparada para crescer.

O Barberify é uma plataforma **independente** (banco, autenticação, administração e APIs próprios, sem
dependência do Soluções AL Hub) com três identidades separadas: a **Soluções AL** (Barberify Control),
as **barbearias** (painel multiempresa) e os **consumidores** (marketplace e área do cliente).

## Status

**Planejamento definitivo entregue — implementação não iniciada.** O repositório contém apenas
documentação. Nenhuma tabela, página ou funcionalidade foi criada ainda; a implementação começa após a
aprovação do planejamento.

## Documentação

| Documento | Conteúdo |
|---|---|
| [00 — Índice](docs/planejamento/00-INDICE.md) | mapa da entrega, princípios e resumo das decisões |
| [01 — Viabilidade e Arquitetura](docs/planejamento/01-VIABILIDADE-E-ARQUITETURA.md) | viabilidade na InfinityFree, arquitetura técnica, estrutura de diretórios |
| [02 — Multiempresa, Control e Acesso](docs/planejamento/02-MULTIEMPRESA-CONTROL-AUTENTICACAO.md) | isolamento por empresa, identidade global do proprietário, Barberify Control, autenticação e permissões |
| [03 — Marketplace, Agenda e Assinaturas](docs/planejamento/03-MARKETPLACE-AGENDAMENTO-ASSINATURAS.md) | marketplace público, fluxo de agendamento com WhatsApp, os dois modelos de assinatura |
| [04 — Banco de Dados](docs/planejamento/04-BANCO-DE-DADOS.md) | modelo completo, tabelas, colunas, chaves, índices, migrations e backup |
| [05 — Diagramas ER](docs/planejamento/05-DIAGRAMAS-ER.md) | relacionamentos por domínio |
| [06 — Dependências, Plano, Segurança e Testes](docs/planejamento/06-DEPENDENCIAS-PLANO-SEGURANCA-TESTES.md) | matriz de módulos, etapas de desenvolvimento, OWASP, estratégia de testes, riscos |
| [07 — Decisões Pendentes](docs/planejamento/07-DECISOES-PENDENTES.md) | o que precisa da sua decisão antes de codar (com recomendações) |

## Stack

PHP 8.2+ (execução em 8.4), MySQL/MariaDB, PDO, HTML5, CSS3, JavaScript, Bootstrap 5 servido
localmente — sem Node em produção, sem Docker, sem processo contínuo, sem dependência de cron.

## Próximo passo

Aprovar o [planejamento](docs/planejamento/00-INDICE.md) e responder às
[decisões pendentes](docs/planejamento/07-DECISOES-PENDENTES.md). A Etapa 3 entrega a fundação
técnica, o schema físico completo e o instalador.

---

© Soluções AL — Barberify. Todos os direitos reservados.
