# 07 — Decisões Pendentes (necessárias antes da implementação)

Voltar ao [índice](00-INDICE.md)

Estas são as únicas decisões que **não podem ser tomadas tecnicamente sozinhas**, porque afetam
comportamento comercial ou jurídico do produto. Cada uma traz uma recomendação. Se você aprovar as
recomendações, basta dizer “aprovado, siga as recomendações” e a implementação começa.

Nenhuma delas impede o início da **Etapa 3** (fundação técnica e banco físico), exceto quando indicado.

---

## D1 — Como novas barbearias entram no Barberify

| Opção | Consequência |
|---|---|
| **A. Somente pelo Control** (recomendada) | A Soluções AL cadastra a empresa e o proprietário; trial de 7 dias criado no ato. Controle total, zero spam, operação comercial assistida. |
| B. Auto-cadastro público com trial | Precisa de antifraude (e-mail/telefone/CPF), verificação e tela pública de cadastro; aumenta escopo e risco de contas falsas. |
| C. Auto-cadastro com aprovação manual | Meio-termo: formulário público + fila de aprovação no Control. |

**Recomendação:** A na v1, com caminho aberto para C na Etapa 9 (o modelo de dados já suporta:
`empresas.origem_cadastro` e `trial_concedido_em`).
**Bloqueia:** Etapa 4 (escopo do Control).

---

## D2 — Conta global do consumidor (Área do cliente)

| Opção | Consequência |
|---|---|
| **A. Conta global opcional + vínculo por empresa** (recomendada) | Cliente agenda sem conta; se quiser, cria conta e vê agendamentos/assinaturas de todas as barbearias onde tem vínculo. Exige verificação de e-mail/telefone. |
| B. Acesso só por código do agendamento | Mais simples e sem cadastro; não permite histórico consolidado nem autoatendimento de assinatura. |
| C. Conta por empresa | Repetitivo para o cliente e não resolve visão única. |

**Recomendação:** A. O isolamento é preservado porque `clientes` continua por empresa e o vínculo é
explícito e consentido.
**Bloqueia:** Etapa 6.

---

## D3 — Combinação de serviços com profissionais diferentes

| Opção | Consequência |
|---|---|
| **A. Suportar na v1 via `grupo_id`** (recomendada) | Corte com barbeiro X + barba com barbeiro Y no mesmo fluxo, com validação de encaixe de horários. Mais complexo, porém é realidade comum de barbearia. |
| B. Somente o mesmo profissional na v1 | Wizard mais simples; cliente combina manualmente em dois agendamentos. |

**Recomendação:** A — o modelo já prevê `agendamentos.grupo_id`; o custo é no wizard, não no banco.
**Bloqueia:** Etapa 6.

---

## D4 — Comissão em atendimento coberto por assinatura do consumidor

| Opção | Consequência |
|---|---|
| **A. Base = valor de tabela do serviço** (recomendada) | Profissional não é penalizado por atender assinante; custo previsível. |
| B. Base = valor efetivamente pago (rateio) | Comissão menor e variável; exige rateio por ciclo. |
| C. Configurável por empresa/profissional | Mais flexível, mais telas e testes. |

**Recomendação:** A como padrão, com o campo `comissao_base_assinatura` já no modelo para virar C depois.
**Bloqueia:** Etapa 7 (fidelidade) e Etapa 8 (comissões).

---

## D5 — Contratação de plano de fidelidade pelo cliente

| Opção | Consequência |
|---|---|
| **A. Cliente solicita; pagamento registrado pela barbearia (Pix/dinheiro/cartão na loja)** (recomendada) | Sem gateway, sem taxa, funciona na hospedagem gratuita. Assinatura fica `pagamento_pendente` até confirmação (ou já ativa, se a empresa configurar). |
| B. Somente contratação presencial pela recepção | Mais simples; limita conversão online. |
| C. Gateway de pagamento online | Exige conta em gateway, webhooks (sem cron), PCI, custo — fora do escopo aprovado. |

**Recomendação:** A. Arquitetura já prevê `fidelidade_pagamentos` com forma e comprovante.
**Bloqueia:** Etapa 7.

---

## D6 — Estoque negativo

| Opção | Consequência |
|---|---|
| **A. Bloqueado por padrão, liberável por empresa** (recomendada) | Venda sem saldo é recusada; a empresa pode permitir com alerta. |
| B. Sempre permitido com alerta | Simples, mas gera saldo inconsistente. |

**Recomendação:** A, com configuração `estoque.permite_negativo`.
**Bloqueia:** Etapa 8.

---

## D7 — E-mail transacional

| Opção | Consequência |
|---|---|
| **A. Sem e-mail na v1; recuperação por códigos e reset assistido** (recomendada para o plano gratuito) | Nada quebra; perde-se conveniência. |
| B. SMTP externo na 587/TLS | Porta suportada pelo provedor, mas entrega frágil e possível bloqueio. |
| C. API HTTPS de e-mail transacional | Mais confiável; exige conta e chave (provavelmente plano pago do provedor). |

**Recomendação:** implementar a fila `email_fila` com driver `null` (A) já na Etapa 3, e ativar B ou C
quando você decidir o provedor. O código não muda — só a configuração.
**Bloqueia:** Etapa 10 (só para ativar envio real).

---

## D8 — Identidade visual

| Opção | Consequência |
|---|---|
| **A. Usar a proposta deste planejamento** (recomendada) | Grafite + dourado, wordmark simples, tipografia de sistema. Consistente e sem dependência externa. |
| B. Você fornece manual de marca/logo | Implemento exatamente o que existir. |

**Recomendação:** A, com tokens de cor centralizados para troca barata depois.
**Bloqueia:** Etapa 3 (apenas para fechar o layout; posso começar com A e trocar).

---

## D9 — Planos SaaS iniciais e preços

Preciso de nomes e faixas para popular o seed da Etapa 4. Proposta:

| Plano | Preço sugerido | Limites |
|---|---|---|
| Essencial | R$ 79,90/mês | 1 unidade, 3 usuários, 5 profissionais, agenda + clientes + caixa |
| Profissional | R$ 149,90/mês | 2 unidades, 10 usuários, 15 profissionais, marketplace + fidelidade + estoque |
| Premium | R$ 249,90/mês | 5 unidades, 25 usuários, profissionais ilimitados, relatórios avançados + inteligência |

**Recomendação:** aprovar como **rascunho comercial** (valores editáveis no Control sem migration).
**Bloqueia:** Etapa 4 (seed), não o código.

---

## D10 — Parâmetros operacionais padrão

Proposta (todos configuráveis por empresa):

| Parâmetro | Padrão |
|---|---|
| Hold de reserva | 15 minutos (fixo por requisito) |
| Antecedência mínima para agendar | 30 minutos |
| Janela máxima de agendamento | 60 dias |
| Grade de horários | 15 minutos |
| Intervalo entre serviços | 5 minutos |
| Cancelamento pelo cliente | até 4 horas antes (configurável) |
| Janela para avaliar | 30 dias após o atendimento |
| Limite de tentativas de login | 5 em 15 min (empresa), 5 em 15 min (Control) |
| Retenção de auditoria | 24 meses |
| Retenção de logs técnicos | 90 dias |

**Recomendação:** aprovar.
**Bloqueia:** Etapa 3 (seeds de configuração).

---

## D11 — Domínio, homologação e go-live

- Domínio definitivo (ex.: `barberify.com.br`) e subdomínio de homologação.
- Se o domínio será apontado para a InfinityFree desde o início ou só no go-live.
- Contato de suporte/DPO que aparecerá nas páginas legais (Política de Privacidade, Termos de Uso).

**Recomendação:** usar subdomínio gratuito do provedor para homologação e o domínio próprio no go-live.
**Bloqueia:** Etapa 10 (não bloqueia desenvolvimento).

---

## D12 — Escopo de conformidade legal na v1

A v1 entrega páginas de Política de Privacidade, Termos de Uso, consentimento de marketing e canal de
solicitação de titular. Contrato de assinatura SaaS e nota fiscal **não** são gerados pelo sistema na v1
(registro manual de pagamento apenas).

**Recomendação:** aprovar assim; emissão fiscal é integração externa e não estava no escopo aprovado.
**Bloqueia:** Etapa 10 (textos legais precisam do contato do DPO).

---

## Resumo para aprovação

| Item | Recomendação | Bloqueia |
|---|---|---|
| D1 entrada de empresas | A (somente Control) | Etapa 4 |
| D2 conta do consumidor | A (global opcional) | Etapa 6 |
| D3 combos multi-profissional | A (via `grupo_id`) | Etapa 6 |
| D4 comissão em assinatura | A (valor de tabela) | Etapas 7–8 |
| D5 contratação de plano | A (pagamento na loja) | Etapa 7 |
| D6 estoque negativo | A (bloqueado, liberável) | Etapa 8 |
| D7 e-mail | A (sem e-mail na v1, driver configurável) | Etapa 10 |
| D8 identidade visual | A (proposta deste plano) | Etapa 3 |
| D9 planos e preços | rascunho proposto | Etapa 4 (seed) |
| D10 parâmetros | proposta | Etapa 3 (seed) |
| D11 domínio/homologação | subdomínio agora, domínio no go-live | Etapa 10 |
| D12 escopo legal | sem nota fiscal na v1 | Etapa 10 |

**Aprovação do planejamento + “siga as recomendações” libera a Etapa 3.**
