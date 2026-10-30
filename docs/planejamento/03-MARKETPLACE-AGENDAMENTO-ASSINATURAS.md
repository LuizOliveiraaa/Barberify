# 03 — Marketplace, Agendamento com WhatsApp e os Dois Modelos de Assinatura

Voltar ao [índice](00-INDICE.md)

---

## 1. Arquitetura do marketplace público

### 1.1 Modelo de publicação

| Entidade | Escopo | Regra |
|---|---|---|
| Perfil público | por **empresa** (`marketplace_perfis`) | visível **por padrão**; a empresa pode ocultar sem desativar o painel |
| Vitrine de busca | por **unidade** | cada unidade tem endereço e coordenadas; a busca por proximidade retorna unidades e agrupa por empresa |
| Serviços e preços | da empresa, com override por unidade | só aparecem os marcados `visivel_marketplace = 1` |
| Profissionais | da empresa, com perfil público opcional | foto, apresentação, especialidades, serviços, avaliações |
| Avaliações | por empresa e por profissional | somente de atendimento concluído |

Elegibilidade para aparecer: empresa `ativa` no SaaS (teste válido também entra), unidade ativa com
endereço, perfil público visível e ao menos um serviço público. Suspensão por inadimplência remove do
marketplace automaticamente (sem apagar nada).

### 1.2 Rotas públicas

| Rota | Função |
|---|---|
| `/` | home com busca, destaques, categorias, cidades |
| `/buscar` | busca com filtros (`q`, `cidade`, `servico`, `preco`, `nota`, `distancia`, `ordem`) |
| `/barbearia/{slug}` | perfil da empresa: fotos, descrição, unidades, serviços com preço, profissionais, avaliações, horários |
| `/barbearia/{slug}/profissional/{slug}` | perfil público do profissional |
| `/barbearia/{slug}/servico/{slug}` | página do serviço (SEO) |
| `/agendar/{slug}` | wizard de agendamento (unidade → serviços → profissional → data → horário → dados → revisão) |
| `/agendamento/{codigo}` | acompanhamento/confirmação do agendamento |

### 1.3 Busca por proximidade sem PostGIS

Duas etapas, compatível com MariaDB 10.4:

1. **Caixa delimitadora** com índice em `(latitude, longitude)` da unidade:
   `lat BETWEEN :lat_min AND :lat_max AND lng BETWEEN :lng_min AND :lng_max`.
2. **Haversine** só nos candidatos, com `ORDER BY` por distância calculada em SQL e `LIMIT`.

Coordenadas ausentes não entram na busca por proximidade (entram na busca por cidade).
Fórmula usada exatamente como documentada no código (`6371 * ACOS(...)`), com cuidado de `RADIANS`.
Sem índice espacial, o custo é aceitável porque a caixa reduz o conjunto antes.

### 1.4 Cache e desempenho

- Home e páginas de perfil: cache de arquivo com TTL curto (5 min) e invalidação na escrita
  (`marketplace_versao` por empresa em `empresa_configuracoes`).
- Toda listagem é paginada (20 itens) e retorna apenas colunas usadas.
- “Disponibilidade hoje” no perfil é calculada por endpoint AJAX (`/api/disponibilidade`), não no HTML
  cacheado, para não servir horário velho.
- Imagens de capa/logo são redimensionadas na Etapa 6 (variantes 400/800/1600) para economizar hits.

### 1.5 SEO e compartilhamento

- URLs com slug estável; `title`/`meta description` derivados do perfil; `canonical`.
- `sitemap.php` dinâmico: empresas visíveis, unidades, serviços e profissionais públicos (cache 1 h).
- `robots.txt` bloqueia `/painel`, `/control`, `/api`, `/minha-conta`, `/instalacao`.
- Open Graph com capa e nome; fallback de imagem da marca quando a empresa não tem capa.
- Dados estruturados `LocalBusiness` com endereço, telefone público, horário e nota agregada.

### 1.6 LGPD no marketplace

- Só é público o que a empresa autorizou: nome fantasia, endereço comercial, telefone comercial,
  WhatsApp comercial, fotos, serviços, profissionais com perfil habilitado, avaliações aprovadas.
- Nunca público: e-mail de clientes, telefone de clientes, histórico, valores internos, comissões,
  dados de caixa, agenda nominal de outros clientes.
- Nome do cliente em avaliação aparece abreviado (`João S.`); comentário passa por moderação quando a
  empresa habilita.
- Direito de exclusão: exclusão lógica de cliente mantém agregados estatísticos anonimizados.

### 1.7 Avaliações — regras duras

- Só avalia quem tem `agendamentos.status = 'concluido'` e ainda não avaliou aquele atendimento
  (`UNIQUE (agendamento_id)`).
- Janela de 30 dias após a conclusão; depois expira.
- Nota 1–5 + comentário opcional (até 1.000 caracteres) + profissional e serviço herdados do atendimento.
- Empresa pode responder uma vez por avaliação; pode denunciar (motivo) e o Control modera.
- Denúncia não remove nota automaticamente: existe estado (`publicada`, `em_analise`, `oculta`).
- Média exibida considera apenas avaliações `publicada` dos últimos 12 meses, com contagem explícita.

---

## 2. Agendamento e confirmação pelo WhatsApp

### 2.1 Máquina de estados do agendamento

| Estado | Significado | Entra em | Sai para |
|---|---|---|---|
| `pendente` | criado no painel sem confirmação de horário | criação manual interna | `confirmado`, `cancelado` |
| `reservado` | hold público de 15 min, horário travado | wizard público (etapa final) | `confirmado`, `expirado`, `cancelado` |
| `confirmado` | confirmado no Barberify (clique no botão) | confirmação pública, confirmação manual | `em_atendimento`, `cancelado`, `nao_compareceu` |
| `em_atendimento` | cliente presente, atendimento iniciado | painel/recepção | `concluido`, `cancelado` (com justificativa) |
| `concluido` | atendimento finalizado; gera comanda/comissão/consumo | painel | terminal (só estorno auditado) |
| `cancelado` | cancelado por cliente ou empresa | ambos | terminal |
| `nao_compareceu` | confirmado e não apareceu | painel | terminal |
| `expirado` | hold não confirmado em 15 min | automático (por predicado/scheduler) | terminal |

Transições inválidas são rejeitadas no serviço (`TransicaoInvalidaException`) e auditadas.
Toda transição grava `agendamento_status_historico` (de, para, ator, motivo, data).
`concluido`, `cancelado` e `nao_compareceu` **nunca** são apagados: são o histórico operacional.

### 2.2 Disponibilidade — como o horário é calculado (servidor)

Entradas: empresa, unidade, profissional (ou “qualquer”), lista de serviços, data.

1. **Duração total** = soma das durações efetivas (override do profissional → override da unidade →
   padrão do serviço) + intervalo entre serviços configurado na empresa.
2. **Janela de funcionamento**: `unidade_horarios[dia_semana]` menos `unidade_bloqueios` daquele dia.
3. **Jornada do profissional**: `profissional_horarios` (por unidade e dia) menos
   `profissional_ausencias` (folga, férias, bloqueio) e menos `agenda_bloqueios` avulsos.
4. **Antecedência**: mínimo configurável (ex.: 30 min) e máximo (ex.: 60 dias).
5. **Ocupação real**, com holds válidos:
   ```sql
   SELECT inicio, fim
     FROM agendamentos
    WHERE empresa_id = :empresa
      AND profissional_id = :profissional
      AND status IN ('reservado','confirmado','em_atendimento')
      AND NOT (status = 'reservado' AND reserva_expira_em <= NOW())
      AND inicio < :fim_candidato
      AND fim    > :inicio_candidato
   ```
   A negação do hold expirado é o que libera o horário **mesmo sem cron**.
6. **Grade** gerada de 15 em 15 min (configurável), descartando candidatos que colidem, que terminam
   após o fechamento ou que caem em intervalo/ausência.
7. Serviços que exigem profissionais diferentes geram **dois agendamentos do mesmo grupo**
   (`grupo_id`), cada um com sua janela, e o wizard só aceita combinações encaixáveis.

Nada disso é confiado ao navegador: o endpoint de disponibilidade é apenas informativo; a criação
revalida tudo dentro de transação.

### 2.3 Criação da reserva temporária (hold de 15 minutos)

`POST /agendar/reservar` (CSRF + `requisicao_id` único):

```
BEGIN;
  -- 1) serializa o profissional no dia (trava curta, por linha)
  INSERT INTO agenda_bloqueios_concorrencia (profissional_id, data) VALUES (:p, :d)
    ON DUPLICATE KEY UPDATE atualizado_em = NOW();
  SELECT * FROM agenda_bloqueios_concorrencia WHERE profissional_id=:p AND data=:d FOR UPDATE;

  -- 2) revalida sobreposição (query da §2.2 com FOR UPDATE)
  -- 3) se livre: insere agendamento
  INSERT INTO agendamentos (..., status='reservado', reserva_token=:token,
      reserva_expira_em = NOW() + INTERVAL 15 MINUTE, requisicao_id=:rid);
  INSERT INTO agendamento_servicos (... snapshots de nome, preço, duração, comissão ...);
COMMIT;
```

Resultados possíveis:

| Situação | Resposta |
|---|---|
| Horário livre | `201` com `reserva_token` e código do agendamento |
| Horário ocupado por outro cliente | `409` com alternativas próximas (recalculadas) |
| `requisicao_id` repetido | `200` com a **mesma** reserva (idempotência), sem criar outra |
| Empresa suspensa / sem agendamento online | `403` com mensagem de indisponibilidade |

O `reserva_token` é aleatório (32 bytes), de uso único, e vai na URL de revisão.

### 2.4 Confirmação obrigatória pelo botão

Tela de revisão mostra o resumo e o botão **“CONFIRME SEU AGENDAMENTO PELO WHATSAPP”**.

`POST /agendar/confirmar` com `reserva_token` + CSRF:

```
BEGIN;
  UPDATE agendamentos
     SET status = 'confirmado',
         confirmado_em = NOW(),
         confirmacao_ip = :ip,
         confirmacao_canal = 'whatsapp_botao',
         reserva_token = NULL
   WHERE reserva_token = :token
     AND status = 'reservado'
     AND reserva_expira_em > NOW();
  -- se affected_rows = 0: reserva expirada/inexistente/já confirmada → 410 ou 200 idempotente
  INSERT INTO agendamento_status_historico (...);
  INSERT INTO auditoria (...);
COMMIT;
```

Depois do `COMMIT`, a resposta renderiza a página de sucesso com o link:

```
https://wa.me/55DDDNUMERO?text=<mensagem urlencoded>
```

Mensagem pré-preenchida:

```
Olá! Confirmei meu agendamento no Barberify.
Código: A7K2M9
Cliente: Maria Souza
Data: 12/11/2026 às 15:30
Unidade: Centro
Profissional: João
Serviços: Corte (R$ 45,00) + Barba (R$ 30,00)
Total: R$ 75,00
```

**O que o sistema garante e o que ele não garante (declarado na interface e na documentação):**

| Garantido | Não garantido |
|---|---|
| O clique foi recebido e validado no servidor | Que o WhatsApp abriu |
| O agendamento está `confirmado` e o horário permanece ocupado | Que a mensagem foi enviada |
| Registro de data, hora, IP e código | Que a barbearia leu ou respondeu |
| Confirmação duplicada é bloqueada | — |
| Se o cliente fechar o WhatsApp, o agendamento continua confirmado | — |
| Após 15 min sem clique, o hold expira e o horário é liberado | — |

O sistema **nunca** marca `mensagem_enviada = 1` por clique. Se a empresa quiser comprovar recebimento,
o caminho é o painel mostrar o status “confirmado no site” e a recepção marcar presença — sem inventar
dados de entrega.

### 2.5 Expiração do hold

Três mecanismos, todos idempotentes:

1. **Predicado nas consultas** (§2.2): holds vencidos nunca bloqueiam, mesmo que ninguém os processe.
2. **Reaper no scheduler**: `UPDATE agendamentos SET status='expirado' WHERE status='reservado'
   AND reserva_expira_em <= NOW() LIMIT 200` + histórico + liberação de lista de espera.
3. **Verificação pontual** ao abrir a agenda do dia.

A expiração **nunca** toca em `confirmado`: a cláusula `status='reservado'` é a garantia.

### 2.6 Concorrência, idempotência e casos-limite

| Cenário | Tratamento |
|---|---|
| Dois clientes clicam “reservar” no mesmo slot | Trava por (profissional, dia) + `SELECT ... FOR UPDATE` na checagem; o segundo recebe `409` |
| Duplo clique no botão de reservar | `requisicao_id` único devolve a mesma reserva |
| Duplo clique em confirmar | `UPDATE ... WHERE status='reservado'` deixa passar só uma vez; a segunda retorna estado atual |
| Refresh na página de confirmação | Token já consumido: mostra o agendamento confirmado, não confirma de novo |
| Reserva expira enquanto o cliente preenche | Confirmação retorna `410` com CTA para escolher outro horário |
| Serviço de 90 min não cabe no fim do dia | Slot não é oferecido |
| Profissional entra de féria depois da reserva | Cancelamento com motivo + oferta de outro profissional/horário |
| Cliente agenda dois horários sobrepostos na mesma empresa | Regra: bloqueio de sobreposição por cliente no mesmo dia (configurável) |
| Mudança de preço depois da reserva | `agendamento_servicos` guarda snapshot: contrato preservado |
| Fuso | Toda data/hora é `America/Sao_Paulo`; horário de verão não existe no Brasil desde 2019, e a coluna `unidades.fuso` fica reservada para expansão |

### 2.7 Agenda interna (Etapa 5)

- Visões diária, semanal e mensal; filtros por unidade e profissional; arrastar para reagendar
  (mesma validação de sobreposição do servidor).
- Ações: criar manual, confirmar, iniciar, concluir (abre comanda), cancelar com motivo,
  marcar não compareceu, bloquear horário, registrar folga/férias.
- Concluir atendimento é o gatilho de: comanda, comissão (pendente), consumo de fidelidade,
  elegibilidade de avaliação e atualização do CRM (`ultima_visita`).

### 2.8 Lista de espera (Barberify Recupera)

- Entrada por serviço/profissional/data ou faixa de datas, com prioridade e expiração.
- Quando um horário é liberado (cancelamento, expiração de hold, conclusão antecipada), o scheduler
  seleciona o primeiro da fila compatível e **reserva por 15 min** para ele, notificando no painel
  (e WhatsApp manual pelo atendente).
- A conversão usa a mesma trava e a mesma checagem de sobreposição: não existe reserva duplicada.
- Sem confirmação no prazo, o item volta para a fila com `tentativas + 1` (máx. 3) e o próximo é chamado.

---

## 3. Arquitetura dos dois modelos de assinatura

> Separação obrigatória: `saas_*` (barbearia → Soluções AL) e `fidelidade_*` (consumidor → barbearia).
> Tabelas, serviços, telas, permissões e relatórios distintos. Nenhum `JOIN` entre os dois mundos.

### 3.1 Assinatura SaaS (barbearia paga à Soluções AL)

**Entidades:** `saas_planos`, `saas_plano_limites`, `saas_assinaturas`, `saas_assinatura_eventos`,
`saas_faturas`, `saas_pagamentos`.

**Plano:** nome, slug, preço, periodicidade (`mensal`, `anual`), `trial_dias` (padrão 7), limites
(`max_unidades`, `max_usuarios`, `max_profissionais`, `marketplace`, `fidelidade`, `estoque`,
`relatorios_avancados`), benefícios textuais, ativo, ordem.

**Ciclo de vida da assinatura:**

```
[em_teste] --7 dias--> [ativa] (pagamento confirmado)
     |                    |
     |                    ├── venceu e não pago --> [pagamento_pendente] --+--> [vencida] --+--> [inadimplente] --> [suspensa]
     |                    |                                              |                |
     |                    └── pagamento --> [ativa] <--------------------+----------------+
     └── venceu sem contratar --> [vencida]
[qualquer estado exceto cancelada] --cancelamento--> [cancelada]
[suspensa] --pagamento/reativação manual--> [ativa]
```

Regras:

- Teste de **7 dias** por empresa, uma única vez (proteção: `empresas.cpf_cnpj` único e
  `empresas.trial_concedido_em` não nulo impede novo teste).
- Situação é **derivada de datas e pagamentos**, não digitada. `SaasCobrancaService::avaliar(empresa)`
  roda no login e no scheduler, sempre idempotente.
- Graça configurável: `vencida` + 5 dias → `inadimplente`; `inadimplente` + 5 dias → `suspensa`
  (valores padrão globais, alteráveis no Control com auditoria).
- **Suspender não apaga nada.** Painel entra em modo somente-leitura restrito: login, tela de cobrança,
  histórico de faturas e exportação de dados; operação bloqueada com aviso.
- Reativação manual restaura o acesso imediatamente e registra evento.
- Mudança de plano gera nova vigência com snapshot de preço/limites; faturas antigas não mudam.
- Pagamentos são **registrados manualmente** pela equipe da Soluções AL (forma, data, comprovante).
  Arquitetura preparada para gateway: `saas_pagamentos.origem` (`manual`, `gateway`) e campo
  `referencia_externa`, sem implementar gateway agora.

**Faturas:** emitidas por competência no scheduler (ou manualmente), com `valor`, `vencimento`,
`status` (`aberta`, `paga`, `vencida`, `cancelada`, `estornada`). Pagamento parcial não é permitido
na v1 (registro de valor diferente exige justificativa e fica como `ajuste`).

**Financeiro global:** receita reconhecida no **recebimento** (regime de caixa), com relatório separado
de competência. Nenhuma receita de barbearia aparece aqui.

### 3.2 Assinaturas dos consumidores (fidelidade)

**Entidades:** `fidelidade_planos`, `fidelidade_plano_servicos`, `fidelidade_planos_unidades`,
`fidelidade_assinaturas`, `fidelidade_assinatura_membros`, `fidelidade_ciclos`,
`fidelidade_utilizacoes`, `fidelidade_movimentacoes`, `fidelidade_pagamentos`.
Programas de pontos: `fidelidade_programas`, `fidelidade_programa_recompensas`, `fidelidade_saldos`,
`fidelidade_movimentos`, `fidelidade_resgates`.

**Plano (configuração):** nome, descrição, preço, periodicidade, créditos por ciclo, limite semanal,
limite diário, serviços permitidos, profissionais autorizados, unidades autorizadas, dependentes
permitidos, compartilhamento (individual/coletivo), validade do ciclo, regra de renovação.

**Ciclo:** começa na contratação e dura o período contratado (mensal = 1 mês a partir da data;
ex.: 15/10 → 14/11). A **semana** para limites é sempre **segunda a domingo**, independentemente do
início do ciclo. Créditos não usados expiram no fim do ciclo (sem rollover na v1).

**Limites (independentes e cumulativos):**

```
podeConsumir(assinatura, servico, data) =
      servicoPertenceAoPlano
  AND unidadeAutorizada
  AND (profissionalAutorizado se houver restrição)
  AND cicloAtivo
  AND creditosDisponiveisNoCiclo > 0
  AND usosNaSemana < limiteSemanal
  AND usosNoDia     < limiteDiario
  AND membroAtivo (titular ou dependente com direito)
```

**Consumo** — só no **atendimento concluído**, nunca na reserva:

```
BEGIN;
  SELECT * FROM fidelidade_ciclos WHERE assinatura_id=:a AND status='ativo' FOR UPDATE;
  -- revalida limites com SELECT COUNT dentro da transação
  INSERT INTO fidelidade_utilizacoes (agendamento_id, ...)         -- UNIQUE(agendamento_id)
  INSERT INTO fidelidade_movimentacoes (tipo='consumo', creditos=-1, ...)  -- UNIQUE(utilizacao_id)
COMMIT;
```

- `UNIQUE (agendamento_id)` em `fidelidade_utilizacoes` e `UNIQUE (utilizacao_id)` em
  `fidelidade_movimentacoes` tornam o consumo idempotente por construção.
- Cancelamento/estorno: lançamento `estorno` com `estorno_de_id` **único**, devolvendo o crédito ao
  ciclo de origem (se ainda ativo) ou registrando crédito de cortesia no ciclo novo com justificativa.
  Nunca dois estornos para a mesma utilização.

**Financeiro da fidelidade (sem duplicidade):**

| Fato | Lançamento |
|---|---|
| Cliente paga o plano | `fidelidade_pagamentos` (dinheiro recebido) + receita do ciclo |
| Cliente usa o benefício | **nenhuma** entrada de dinheiro; apenas `fidelidade_utilizacoes` + movimento de crédito |
| Atendimento coberto | comanda item com `valor_dinheiro = 0` e `valor_referencia` (preço de tabela) |
| Comissão do profissional | calculada sobre `valor_referencia` por padrão, ou regra da empresa, sempre registrada |
| Estorno/reembolso | lançamento negativo vinculado ao pagamento original |

Assim, o pagamento do plano não vira receita a cada corte, e o faturamento do serviço não é contado
duas vezes. Relatórios separam “dinheiro recebido”, “receita reconhecida”, “benefício consumido” e
“atendimentos realizados”.

**Planos familiares:** `fidelidade_assinatura_membros` com `papel` (`titular`, `dependente`),
`limite_individual` opcional e vínculo ao `clientes.id`. Saldo coletivo por padrão; individual quando
configurado. Remover dependente encerra o vínculo (`removido_em`) preservando utilização e histórico.

**Ciclos que atravessam semanas:** o limite semanal é contado por semana-calendário (seg→dom) dentro
do ciclo; um ciclo de 15/10 a 14/11 contém semanas parciais e completas, e o limite semanal vale em
cada uma delas. Exemplo protegido por teste: 10 créditos no ciclo, 1 por semana → o cliente não
consegue 2 usos na mesma semana mesmo com saldo.

### 3.3 Programas de pontos/cashback (Barberify Clube)

- `fidelidade_programas`: tipo `pontos` | `carimbo` | `cashback`, regra de acúmulo (por valor gasto,
  por visita, por serviço), validade e ativo.
- `fidelidade_saldos`: saldo materializado por cliente/programa (atualizado por transação, com
  `SELECT ... FOR UPDATE`).
- `fidelidade_movimentos`: ledger append-only (acúmulo, resgate, expiração, ajuste) com
  `UNIQUE (chave_idempotencia)`.
- `fidelidade_resgates`: resgate de recompensa, com custo em pontos e status (`solicitado`,
  `aprovado`, `entregue`, `cancelado`); devolve pontos se cancelado antes da entrega.

### 3.4 Testes obrigatórios deste domínio (detalhados em 06 §4)

Limite semanal, virada de semana, saldo insuficiente, expiração de crédito, renovação de ciclo sem
rollover, dependentes (individual e coletivo), compartilhamento, estorno sem duplicidade, pagamento
pendente bloqueando uso, concorrência pelo último crédito (dois atendimentos simultâneos),
snapshot de regras após edição do plano, ciclo atravessando semanas, unidade não autorizada,
serviço fora do plano, profissional não autorizado e consumo duplicado do mesmo agendamento.

---

## 4. Conflitos e lacunas identificados no Prompt Mestre

Resolvidos sem remover requisito:

| # | Ponto | Conflito aparente | Solução adotada |
|---|---|---|---|
| 1 | Área do cliente × isolamento por empresa | cliente pertence à empresa, mas a área do cliente sugere visão única | duas camadas: `clientes` (tenant) + `contas_cliente` (global, opcional), vinculadas por consentimento; cada empresa continua isolada |
| 2 | “Confirmar pelo WhatsApp” | clique não prova envio nem leitura | confirmação é **interna** no Barberify; o WhatsApp é canal de mensagem com link, sem telemetria inventada |
| 3 | Reserva de 15 min × ausência de cron | sem agendador, o hold ficaria preso | expiração por predicado + reaper idempotente; nenhum horário fica bloqueado por hold vencido |
| 4 | Duas assinaturas | risco de mistura | prefixos, serviços e telas separados; teste que falha se houver `JOIN` entre `saas_*` e `fidelidade_*` |
| 5 | Fidelidade × financeiro | risco de receita dupla | pagamento do plano no recebimento; uso do benefício não gera caixa; comissão sobre valor de referência; relatórios separados |
| 6 | Pontos × fidelidade | sobreposição | programa de pontos é submódulo com ledger próprio, não uma segunda assinatura |
| 7 | Marketplace por empresa × busca por proximidade | empresa tem várias unidades com locais distintos | busca retorna **unidades**, agrupadas por empresa; perfil é da empresa |
| 8 | Proprietário global × proprietário da barbearia | nomes iguais, poderes diferentes | “Proprietário Global” (Control) e “Proprietário da Empresa” (painel), tabelas e telas distintas |
| 9 | Serviço com promoção | duplicar serviço para promoção | promoção é campo/ período do próprio serviço + `vitrine_ofertas`; nunca serviço duplicado |
| 10 | Contratação de plano pelo cliente sem gateway | não há pagamento online na v1 | contratação cria assinatura `pagamento_pendente` e pagamento manual/Pix registrado pela barbearia; uso liberado só após confirmação (configurável) |
| 11 | Relatórios “em tempo real” em hospedagem gratuita | sem cron/consulta pesada | indicadores calculados sob demanda com cache curto e índices; nada de varredura completa por pageview |
| 12 | Exclusão de dados (LGPD) × histórico financeiro | apagar cliente quebraria histórico | exclusão lógica/anonimização: dados pessoais eliminados ou mascarados, agregados e obrigações fiscais preservados |
