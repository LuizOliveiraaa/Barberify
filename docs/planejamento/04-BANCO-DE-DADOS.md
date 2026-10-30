# 04 — Modelo Completo do Banco de Dados

Voltar ao [índice](00-INDICE.md) · Diagramas em [05-DIAGRAMAS-ER.md](05-DIAGRAMAS-ER.md)

Banco: **MySQL 5.7 / MariaDB 10.4** (compatível com ambos) · Engine **InnoDB** ·
Charset **utf8mb4 / utf8mb4_unicode_ci** · Timezone da aplicação **America/Sao_Paulo**.

**Total: 113 tabelas** em 15 domínios (A–O): 13 de plataforma/sistema, 16 do Control, 6 de SaaS,
10 de identidade da empresa, 7 de clientes, 6 de profissionais, 4 de catálogo, 6 de agenda,
3 de atendimento, 9 de financeiro, 7 de estoque, 15 de fidelidade, 5 de marketplace, 4 de marketing e
2 de inteligência.

> Este documento é o **modelo lógico completo**. O DDL físico (arquivos de migration com tipos,
> engines, colações e ordem de criação) é a entrega da Etapa 3, gerado a partir daqui e revisado
> antes de rodar. Nenhuma tabela existe ainda no servidor.

---

## 1. Convenções obrigatórias

### 1.1 Colunas padrão

Toda tabela de negócio possui, salvo indicação contrária:

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | PK interna, nunca exposta em URL pública |
| `criado_em` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | hora local (São Paulo) |
| `atualizado_em` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | idem |
| `criado_por` / `atualizado_por` | `BIGINT UNSIGNED NULL` | quando a autoria importa (aponta para `usuarios_empresa` ou `control_usuarios`, conforme o domínio) |
| `excluido_em` | `DATETIME NULL` | **apenas** onde há exclusão lógica |

Tabelas **imutáveis** (ledger, auditoria, histórico, eventos) não têm `atualizado_em` nem
`excluido_em`: correção é sempre um novo registro.

### 1.2 Tipos

| Uso | Tipo |
|---|---|
| Dinheiro | `DECIMAL(12,2)` — nunca `FLOAT`; cálculo interno em centavos (`int`) |
| Percentual | `DECIMAL(5,2)` (0,00–100,00) |
| Quantidade | `DECIMAL(10,3)` (permite 0,5 unidade) |
| Status / tipo / enumeração | `VARCHAR(24)` ou `VARCHAR(40)` + constantes no PHP (sem `ENUM`, para evoluir sem `ALTER`) |
| Slug / código público | `VARCHAR(60)` / `VARCHAR(16)` com `UNIQUE` |
| Texto longo | `TEXT` |
| Snapshot de regras | `JSON` **apenas como payload de leitura integral** (nunca filtro relacional) |
| Booleano | `TINYINT(1) NOT NULL DEFAULT 0` |
| Data/hora | `DATETIME` (local) e `DATE` para competência/vencimento |

### 1.3 Chaves e isolamento

- FK nomeada `fk_<tabela>_<coluna>`, com `ON DELETE RESTRICT` por padrão e `ON UPDATE CASCADE` só onde
  é seguro. `SET NULL` apenas em vínculos opcionais (ex.: `clientes.conta_cliente_id`).
- Toda tabela de tenant tem **`empresa_id BIGINT UNSIGNED NOT NULL`** com FK para `empresas(id)`.
- Índice composto sempre **começa por `empresa_id`** quando a consulta é de tenant.
- Unicidade de negócio é composta com `empresa_id` (ex.: `UNIQUE (empresa_id, slug)`).
- Tabelas do Control e do marketplace global **não** têm `empresa_id` (exceto `marketplace_*`, que são
  por empresa por natureza).
- Prefixos por domínio: `control_`, `saas_`, `fidelidade_`, `marketplace_`, e nomes próprios para
  operação da barbearia.

### 1.4 Regras de integridade que o banco não consegue impor e o serviço impõe

| Regra | Onde é garantida |
|---|---|
| Sobreposição de agenda | `AgendamentoService` com transação + `FOR UPDATE` |
| Saldo de crédito/pontos nunca negativo | `LedgerFidelidade` com `SELECT ... FOR UPDATE` + validação |
| Um caixa aberto por unidade | `CaixaService`: `SELECT ... FOR UPDATE` nas linhas do caixa da unidade antes de abrir, dentro da transação; a coluna `status` é validada na mesma consulta. Não se usa índice único parcial (não existe no MySQL 5.7); a transação é a garantia |
| Estorno único por lançamento | `UNIQUE (estorno_de_id)` nas tabelas de ledger |
| Consumo único por agendamento | `UNIQUE (agendamento_id)` em `fidelidade_utilizacoes` |
| Proprietário único por realm | `UNIQUE (is_proprietario)` com `NULL` nos demais |
| Limites de plano SaaS | `SaasPlanoService` na criação de unidade/usuário |

---

## 2. Domínio A — Plataforma, empresas e sistema

**`empresas`** — raiz do tenant.
`slug` VARCHAR(60) · `nome_fantasia` VARCHAR(140) · `razao_social` VARCHAR(160) · `cpf_cnpj` VARCHAR(18) ·
`email` VARCHAR(160) · `telefone` VARCHAR(20) · `whatsapp` VARCHAR(20) · `status` VARCHAR(24)
(`ativa`,`suspensa`,`cancelada`) · `trial_concedido_em` DATETIME · `suspensa_em` DATETIME ·
`cancelada_em` DATETIME · `motivo_status` VARCHAR(255) · `origem_cadastro` VARCHAR(24) ·
`excluido_em` DATETIME
**UQ** `(slug)`, `(cpf_cnpj)` · **IDX** `(status)`, `(criado_em)`

**`empresa_configuracoes`** — chave/valor por empresa.
`empresa_id` · `chave` VARCHAR(60) · `valor` TEXT · `tipo` VARCHAR(16) (`string`,`int`,`bool`,`json`)
**UQ** `(empresa_id, chave)`

**`unidades`** — filiais.
`empresa_id` · `nome` VARCHAR(120) · `slug` VARCHAR(60) · `telefone` VARCHAR(20) · `whatsapp` VARCHAR(20) ·
`email` VARCHAR(160) · `cep` VARCHAR(9) · `logradouro` VARCHAR(160) · `numero` VARCHAR(20) ·
`complemento` VARCHAR(80) · `bairro` VARCHAR(80) · `cidade` VARCHAR(80) · `uf` CHAR(2) ·
`latitude` DECIMAL(10,7) · `longitude` DECIMAL(10,7) · `fuso` VARCHAR(40) DEFAULT 'America/Sao_Paulo' ·
`aceita_agendamento_online` TINYINT(1) · `status` VARCHAR(16) · `ordem` SMALLINT · `excluido_em` DATETIME
**UQ** `(empresa_id, slug)` · **IDX** `(empresa_id, status)`, `(cidade, uf)`, `(latitude, longitude)`

**`unidade_horarios`** — funcionamento semanal.
`empresa_id` · `unidade_id` · `dia_semana` TINYINT (0=domingo…6=sábado) · `aberto` TINYINT(1) ·
`hora_abertura` TIME · `hora_fechamento` TIME · `intervalo_inicio` TIME NULL · `intervalo_fim` TIME NULL
**UQ** `(unidade_id, dia_semana)`

**`unidade_bloqueios`** — feriados, fechamentos, horários especiais.
`empresa_id` · `unidade_id` · `tipo` VARCHAR(16) (`fechado`,`especial`) · `data_inicio` DATETIME ·
`data_fim` DATETIME · `motivo` VARCHAR(160)
**IDX** `(unidade_id, data_inicio, data_fim)`

**`arquivos`** — registro de todo upload.
`empresa_id` NULL · `tipo` VARCHAR(24) (`logo`,`capa`,`servico`,`profissional`,`comprovante`,`backup`,`anexo_suporte`) ·
`caminho` VARCHAR(255) · `nome_original` VARCHAR(180) · `mime` VARCHAR(80) · `tamanho` INT UNSIGNED ·
`hash_sha256` CHAR(64) · `privado` TINYINT(1) · `enviado_por_tipo` VARCHAR(16) · `enviado_por_id` BIGINT UNSIGNED
**IDX** `(empresa_id, tipo)`, `(hash_sha256)`

**`auditoria`** — append-only.
`empresa_id` NULL · `ator_tipo` VARCHAR(16) (`empresa`,`control`,`cliente`,`sistema`) · `ator_id` BIGINT NULL ·
`acao` VARCHAR(80) · `modulo` VARCHAR(40) · `entidade` VARCHAR(60) · `entidade_id` BIGINT NULL ·
`dados_antes` JSON NULL · `dados_depois` JSON NULL · `resultado` VARCHAR(16) (`sucesso`,`negado`,`erro`) ·
`ip` VARCHAR(45) · `user_agent` VARCHAR(255) · `request_id` CHAR(26) · `contexto` JSON NULL
**IDX** `(empresa_id, criado_em)`, `(ator_tipo, ator_id, criado_em)`, `(acao, criado_em)`, `(entidade, entidade_id)`
Sem `UPDATE`/`DELETE` na aplicação.

**`sistema_tarefas`** — controle do scheduler preguiçoso.
`chave` VARCHAR(60) PK · `intervalo_min` SMALLINT · `ultima_execucao` DATETIME NULL ·
`bloqueado_ate` DATETIME NULL · `duracao_ms` INT NULL · `ultimo_resultado` VARCHAR(255) NULL ·
`falhas_consecutivas` SMALLINT
`chave` PK (substitui `id`).

**`idempotencia`** — proteção de operação repetida.
`empresa_id` NULL · `escopo` VARCHAR(60) · `chave` VARCHAR(80) · `resposta_json` JSON NULL ·
`expira_em` DATETIME
**UQ** `(escopo, chave)` · **IDX** `(expira_em)`

**`logs_aplicacao`** — eventos técnicos (sem credenciais).
`nivel` VARCHAR(10) · `mensagem` VARCHAR(500) · `contexto` JSON NULL · `impressao` CHAR(32) (agrupamento) ·
`request_id` CHAR(26) · `empresa_id` NULL · `arquivo` VARCHAR(160) · `linha` INT
**IDX** `(nivel, criado_em)`, `(impressao, criado_em)`

**`email_fila`** — outbox quando SMTP existir.
`empresa_id` NULL · `para` VARCHAR(160) · `assunto` VARCHAR(180) · `corpo_html` TEXT ·
`corpo_texto` TEXT · `status` VARCHAR(16) (`pendente`,`enviado`,`falha`) · `tentativas` TINYINT ·
`ultimo_erro` VARCHAR(255) · `enviado_em` DATETIME
**IDX** `(status, criado_em)`

**`notificacoes`** — avisos internos do painel.
`empresa_id` · `destinatario_tipo` VARCHAR(16) · `destinatario_id` BIGINT · `tipo` VARCHAR(40) ·
`titulo` VARCHAR(140) · `mensagem` VARCHAR(500) · `link` VARCHAR(255) · `lida_em` DATETIME NULL
**IDX** `(destinatario_tipo, destinatario_id, lida_em)`

**`migracoes`** — controle de migrations.
`versao` VARCHAR(20) PK · `arquivo` VARCHAR(180) · `checksum` CHAR(64) · `aplicada_em` DATETIME · `duracao_ms` INT

---

## 3. Domínio B — Barberify Control (identidade global)

**`control_usuarios`** — equipe da Soluções AL e proprietário global.
`nome` VARCHAR(120) · `email` VARCHAR(160) · `senha_hash` VARCHAR(255) · `is_proprietario` TINYINT(1) NULL ·
`protegido` TINYINT(1) · `status` VARCHAR(16) (`ativo`,`inativo`,`bloqueado`) · `cargo` VARCHAR(40) ·
`totp_segredo` VARCHAR(255) NULL (cifrado) · `totp_ativo` TINYINT(1) · `ultimo_acesso` DATETIME NULL ·
`trocar_senha` TINYINT(1) · `tentativas_falhas` TINYINT · `bloqueado_ate` DATETIME NULL · `excluido_em` DATETIME
**UQ** `(email)`, `(is_proprietario)`

**`control_papeis`** — `nome` VARCHAR(60) · `slug` VARCHAR(40) · `descricao` VARCHAR(255) ·
`sistema` TINYINT(1) · `ativo` TINYINT(1) — **UQ** `(slug)`

**`control_permissoes`** — catálogo global. `chave` VARCHAR(80) · `modulo` VARCHAR(40) ·
`descricao` VARCHAR(160) · `critica` TINYINT(1) — **UQ** `(chave)`

**`control_papel_permissoes`** — `papel_id` · `permissao_id` — **UQ** `(papel_id, permissao_id)`

**`control_usuario_papeis`** — `usuario_id` · `papel_id` — **UQ** `(usuario_id, papel_id)`

**`control_sessoes`** — `usuario_id` · `token_hash` CHAR(64) · `estado` VARCHAR(16) (`pendente_2fa`,`ativa`) ·
`ip` VARCHAR(45) · `user_agent` VARCHAR(255) · `expira_em` DATETIME · `ultimo_uso` DATETIME ·
`revogada_em` DATETIME NULL · `motivo_revogacao` VARCHAR(60) — **UQ** `(token_hash)` ·
**IDX** `(usuario_id, revogada_em)`

**`control_recuperacao_senha`** — `usuario_id` · `token_hash` CHAR(64) · `expira_em` · `usado_em` ·
`ip` — **UQ** `(token_hash)`

**`control_codigos_recuperacao`** — `usuario_id` · `codigo_hash` CHAR(64) · `usado_em` DATETIME NULL —
**UQ** `(usuario_id, codigo_hash)`

**`control_log_acessos`** — append-only: `usuario_id` NULL · `email_tentado` VARCHAR(160) ·
`resultado` VARCHAR(16) (`sucesso`,`senha_invalida`,`2fa_invalido`,`bloqueado`,`desativado`) ·
`ip` · `user_agent` · `criado_em`

**`control_configuracoes`** — `chave` VARCHAR(60) PK · `valor` TEXT · `tipo` VARCHAR(16) ·
`critica` TINYINT(1) · `atualizado_por` BIGINT NULL

**`control_observacoes_empresa`** — `empresa_id` · `usuario_id` · `texto` TEXT · `criado_em`

**`control_acessos_suporte`** — acesso excepcional e temporário.
`empresa_id` · `ticket_id` NULL · `solicitante_id` · `aprovador_id` NULL · `justificativa` TEXT ·
`escopo` VARCHAR(255) · `status` VARCHAR(16) (`solicitado`,`aprovado`,`negado`,`expirado`,`encerrado`) ·
`inicio` DATETIME · `expira_em` DATETIME · `encerrado_em` DATETIME NULL
**IDX** `(empresa_id, status)`

**`control_tickets`** — suporte das barbearias.
`empresa_id` · `protocolo` VARCHAR(20) · `assunto` VARCHAR(160) · `categoria` VARCHAR(40) ·
`prioridade` VARCHAR(12) · `status` VARCHAR(16) (`aberto`,`em_andamento`,`aguardando_cliente`,`resolvido`,`fechado`) ·
`responsavel_id` NULL · `aberto_por_tipo`/`aberto_por_id` · `resolvido_em` DATETIME NULL
**UQ** `(protocolo)` · **IDX** `(empresa_id, status)`, `(status, prioridade)`

**`control_ticket_mensagens`** — `ticket_id` · `autor_tipo` (`control`,`empresa`) · `autor_id` ·
`mensagem` TEXT · `interno` TINYINT(1) · `arquivo_id` NULL

**`control_comunicados`** — `titulo` VARCHAR(160) · `corpo` TEXT · `tipo` VARCHAR(24)
(`info`,`manutencao`,`atualizacao`,`alerta`) · `segmento` VARCHAR(24) (`todas`,`plano`,`empresa`) ·
`plano_id` NULL · `empresa_id` NULL · `publicado_em` DATETIME NULL · `expira_em` DATETIME NULL ·
`criado_por`

**`control_comunicado_destinatarios`** — `comunicado_id` · `empresa_id` · `lido_em` DATETIME NULL —
**UQ** `(comunicado_id, empresa_id)`

---

## 4. Domínio C — Assinatura SaaS (barbearia → Soluções AL)

**`saas_planos`** — `nome` VARCHAR(80) · `slug` VARCHAR(40) · `descricao` TEXT ·
`preco` DECIMAL(12,2) · `periodicidade` VARCHAR(12) (`mensal`,`anual`) · `trial_dias` SMALLINT DEFAULT 7 ·
`dias_graca_vencida` SMALLINT · `dias_graca_inadimplente` SMALLINT · `ativo` TINYINT(1) · `ordem` SMALLINT ·
`excluido_em` DATETIME — **UQ** `(slug)`

**`saas_plano_limites`** — `plano_id` · `chave` VARCHAR(40) (`max_unidades`,`max_usuarios`,`max_profissionais`,
`marketplace`,`fidelidade`,`estoque`,`relatorios_avancados`) · `tipo` VARCHAR(12) (`limite`,`beneficio`) ·
`valor` VARCHAR(60) — **UQ** `(plano_id, chave)`

**`saas_assinaturas`** — `empresa_id` · `plano_id` · `status` VARCHAR(24) (`em_teste`,`ativa`,
`pagamento_pendente`,`vencida`,`inadimplente`,`suspensa`,`cancelada`) · `inicio` DATE · `fim_teste` DATE NULL ·
`proximo_vencimento` DATE NULL · `preco_snapshot` DECIMAL(12,2) · `periodicidade_snapshot` VARCHAR(12) ·
`limites_snapshot` JSON · `renovacao_automatica` TINYINT(1) · `cancelada_em` DATETIME NULL ·
`motivo_cancelamento` VARCHAR(255) · `ultima_avaliacao` DATETIME NULL
**IDX** `(empresa_id, status)`, `(status, proximo_vencimento)` — apenas **uma assinatura vigente** por
empresa é garantida no serviço (histórico fica com `status='cancelada'`).

**`saas_assinatura_eventos`** — append-only: `assinatura_id` · `empresa_id` · `de` VARCHAR(24) · `para` VARCHAR(24) ·
`motivo` VARCHAR(255) · `ator_tipo` · `ator_id` · `criado_em`
**IDX** `(empresa_id, criado_em)`

**`saas_faturas`** — `assinatura_id` · `empresa_id` · `competencia` CHAR(7) (`2026-11`) · `valor` DECIMAL(12,2) ·
`vencimento` DATE · `status` VARCHAR(16) (`aberta`,`paga`,`vencida`,`cancelada`,`estornada`) ·
`paga_em` DATETIME NULL · `observacao` VARCHAR(255)
**UQ** `(assinatura_id, competencia)` · **IDX** `(empresa_id, status, vencimento)`

**`saas_pagamentos`** — `fatura_id` · `empresa_id` · `valor` DECIMAL(12,2) · `forma` VARCHAR(24) ·
`data_pagamento` DATE · `origem` VARCHAR(12) (`manual`,`gateway`) · `referencia_externa` VARCHAR(120) NULL ·
`comprovante_arquivo_id` NULL · `registrado_por` BIGINT · `observacao` VARCHAR(255) ·
`estorno_de_id` BIGINT NULL
**UQ** `(estorno_de_id)` · **IDX** `(empresa_id, data_pagamento)`

---

## 5. Domínio D — Usuários da empresa, papéis e sessões

**`usuarios_empresa`** — `empresa_id` · `nome` VARCHAR(120) · `email` VARCHAR(160) ·
`telefone` VARCHAR(20) · `senha_hash` VARCHAR(255) · `is_proprietario` TINYINT(1) NULL ·
`status` VARCHAR(16) (`ativo`,`inativo`) · `cargo` VARCHAR(40) · `totp_segredo` VARCHAR(255) NULL ·
`totp_ativo` TINYINT(1) · `trocar_senha` TINYINT(1) · `ultimo_acesso` DATETIME NULL ·
`tentativas_falhas` TINYINT · `bloqueado_ate` DATETIME NULL · `excluido_em` DATETIME
**UQ** `(empresa_id, email)`, `(empresa_id, is_proprietario)` · **IDX** `(empresa_id, status)`

**`usuario_empresa_unidades`** — `empresa_id` · `usuario_id` · `unidade_id` — **UQ** `(usuario_id, unidade_id)`

**`papeis`** — `empresa_id` · `nome` VARCHAR(60) · `slug` VARCHAR(40) · `descricao` VARCHAR(255) ·
`sistema` TINYINT(1) · `ativo` TINYINT(1) — **UQ** `(empresa_id, slug)`

**`permissoes`** — catálogo global: `chave` VARCHAR(80) · `modulo` VARCHAR(40) · `descricao` VARCHAR(160) ·
`critica` TINYINT(1) — **UQ** `(chave)`

**`papel_permissoes`** — `empresa_id` · `papel_id` · `permissao_id` — **UQ** `(papel_id, permissao_id)`

**`usuario_papeis`** — `empresa_id` · `usuario_id` · `papel_id` — **UQ** `(usuario_id, papel_id)`

**`usuario_sessoes`** — `empresa_id` · `usuario_id` · `token_hash` CHAR(64) · `ip` VARCHAR(45) ·
`user_agent` VARCHAR(255) · `expira_em` DATETIME · `ultimo_uso` DATETIME · `revogada_em` DATETIME NULL ·
`unidade_ativa_id` NULL — **UQ** `(token_hash)` · **IDX** `(empresa_id, usuario_id, revogada_em)`

**`usuario_recuperacao_senha`** — `empresa_id` · `usuario_id` · `token_hash` · `expira_em` · `usado_em` · `ip`

**`usuario_codigos_recuperacao`** — `empresa_id` · `usuario_id` · `codigo_hash` · `usado_em` —
**UQ** `(usuario_id, codigo_hash)`

**`tentativas_login`** — `escopo` VARCHAR(12) (`empresa`,`control`,`cliente`) · `identificador` VARCHAR(160) ·
`ip` VARCHAR(45) · `sucesso` TINYINT(1) · `criado_em` — **IDX** `(escopo, identificador, criado_em)`, `(ip, criado_em)`

---

## 6. Domínio E — Clientes, CRM e conta do consumidor

**`clientes`** — tenant. `empresa_id` · `nome` VARCHAR(140) · `telefone` VARCHAR(20) ·
`telefone_normalizado` VARCHAR(20) · `email` VARCHAR(160) NULL · `nascimento` DATE NULL ·
`genero` VARCHAR(16) NULL · `observacoes` TEXT · `preferencias` TEXT ·
`profissional_preferido_id` NULL · `origem` VARCHAR(24) (`painel`,`marketplace`,`indicacao`,`importacao`) ·
`consent_marketing` TINYINT(1) · `consent_em` DATETIME NULL · `conta_cliente_id` NULL ·
`ultima_visita` DATETIME NULL · `total_atendimentos` INT · `total_gasto` DECIMAL(12,2) ·
`status` VARCHAR(16) (`ativo`,`inativo`,`bloqueado`) · `excluido_em` DATETIME
**UQ** `(empresa_id, telefone_normalizado)` · **IDX** `(empresa_id, status)`, `(empresa_id, ultima_visita)`,
`(empresa_id, nascimento)`, `(conta_cliente_id)`

**`cliente_enderecos`** — `empresa_id` · `cliente_id` · `cep` · `logradouro` · `numero` · `complemento` ·
`bairro` · `cidade` · `uf` · `principal` TINYINT(1)

**`cliente_notas`** — `empresa_id` · `cliente_id` · `usuario_id` · `texto` TEXT · `criado_em`
(timeline do CRM; não apagável, apenas arquivável por `arquivada_em`)

**`cliente_consentimentos`** — trilha LGPD: `empresa_id` · `cliente_id` · `tipo` VARCHAR(24) ·
`concedido` TINYINT(1) · `origem` VARCHAR(24) · `ip` · `criado_em`

**`contas_cliente`** — identidade global do consumidor (opcional).
`nome` VARCHAR(140) · `email` VARCHAR(160) · `telefone` VARCHAR(20) · `senha_hash` VARCHAR(255) NULL ·
`email_verificado_em` DATETIME NULL · `telefone_verificado_em` DATETIME NULL · `status` VARCHAR(16) ·
`ultimo_acesso` DATETIME NULL · `excluido_em` DATETIME
**UQ** `(email)` · **IDX** `(telefone)`

**`conta_cliente_sessoes`** — `conta_cliente_id` · `token_hash` · `ip` · `user_agent` · `expira_em` ·
`revogada_em` — **UQ** `(token_hash)`

**`conta_cliente_tokens`** — link mágico/verificação: `conta_cliente_id` · `tipo` (`login`,`verificacao`,`vinculo`) ·
`token_hash` · `expira_em` · `usado_em`

---

## 7. Domínio F — Profissionais

**`profissionais`** — `empresa_id` · `usuario_id` NULL · `nome` VARCHAR(120) · `nome_publico` VARCHAR(120) ·
`slug` VARCHAR(60) · `bio` TEXT · `foto_arquivo_id` NULL · `especialidades` VARCHAR(255) ·
`unidade_principal_id` NULL · `comissao_tipo` VARCHAR(12) (`percentual`,`fixo`,`hibrido`) ·
`comissao_percentual` DECIMAL(5,2) NULL · `comissao_fixo` DECIMAL(12,2) NULL ·
`comissao_base_assinatura` VARCHAR(12) (`valor_tabela`,`valor_pago`,`configuravel`) ·
`perfil_publico` TINYINT(1) · `status` VARCHAR(16) · `ordem` SMALLINT · `excluido_em` DATETIME
**UQ** `(empresa_id, slug)` · **IDX** `(empresa_id, status)`

**`profissional_unidades`** — `empresa_id` · `profissional_id` · `unidade_id` — **UQ** `(profissional_id, unidade_id)`

**`profissional_servicos`** — `empresa_id` · `profissional_id` · `servico_id` ·
`preco_especifico` DECIMAL(12,2) NULL · `duracao_especifica` SMALLINT NULL ·
`comissao_percentual_especifica` DECIMAL(5,2) NULL · `ativo` TINYINT(1) —
**UQ** `(profissional_id, servico_id)`

**`profissional_horarios`** — `empresa_id` · `profissional_id` · `unidade_id` · `dia_semana` TINYINT ·
`hora_inicio` TIME · `hora_fim` TIME · `intervalo_inicio` TIME NULL · `intervalo_fim` TIME NULL ·
`ativo` TINYINT(1) — **IDX** `(profissional_id, unidade_id, dia_semana)`

**`profissional_ausencias`** — `empresa_id` · `profissional_id` · `tipo` (`folga`,`ferias`,`bloqueio`,`atestado`) ·
`inicio` DATETIME · `fim` DATETIME · `motivo` VARCHAR(160) — **IDX** `(profissional_id, inicio, fim)`

**`profissional_metas`** — `empresa_id` · `profissional_id` · `competencia` CHAR(7) ·
`meta_faturamento` DECIMAL(12,2) · `meta_atendimentos` INT — **UQ** `(profissional_id, competencia)`

---

## 8. Domínio G — Catálogo de serviços

**`servico_categorias`** — `empresa_id` · `nome` VARCHAR(80) · `slug` VARCHAR(40) · `ordem` SMALLINT ·
`ativo` TINYINT(1) · `excluido_em` — **UQ** `(empresa_id, slug)`

**`servicos`** — `empresa_id` · `categoria_id` · `nome` VARCHAR(120) · `slug` VARCHAR(60) ·
`descricao` TEXT · `duracao_min` SMALLINT · `preco` DECIMAL(12,2) · `preco_promocional` DECIMAL(12,2) NULL ·
`promocao_inicio` DATETIME NULL · `promocao_fim` DATETIME NULL · `imagem_arquivo_id` NULL ·
`visivel_marketplace` TINYINT(1) · `permite_agendamento_online` TINYINT(1) · `comissao_tipo` VARCHAR(12) NULL ·
`comissao_valor` DECIMAL(12,2) NULL · `requer_avaliacao` TINYINT(1) · `ativo` TINYINT(1) · `ordem` SMALLINT ·
`excluido_em` — **UQ** `(empresa_id, slug)` · **IDX** `(empresa_id, ativo, visivel_marketplace)`

**`servico_unidades`** — `empresa_id` · `servico_id` · `unidade_id` · `preco` DECIMAL(12,2) NULL ·
`duracao_min` SMALLINT NULL · `ativo` TINYINT(1) — **UQ** `(servico_id, unidade_id)`

**`servico_imagens`** — `empresa_id` · `servico_id` · `arquivo_id` · `ordem` SMALLINT

---

## 9. Domínio H — Agenda e agendamentos

**`agendamentos`** — núcleo operacional.
`empresa_id` · `unidade_id` · `profissional_id` · `cliente_id` · `codigo` VARCHAR(12) ·
`grupo_id` CHAR(26) NULL (combinação com profissionais distintos) · `origem` VARCHAR(16)
(`painel`,`marketplace`,`link`) · `status` VARCHAR(24) · `inicio` DATETIME · `fim` DATETIME ·
`duracao_total` SMALLINT · `valor_servicos` DECIMAL(12,2) · `desconto` DECIMAL(12,2) ·
`valor_total` DECIMAL(12,2) · `valor_pago` DECIMAL(12,2) · `observacoes` TEXT ·
`reserva_token` CHAR(64) NULL · `reserva_expira_em` DATETIME NULL · `confirmado_em` DATETIME NULL ·
`confirmacao_ip` VARCHAR(45) NULL · `confirmacao_canal` VARCHAR(24) NULL · `requisicao_id` CHAR(36) NULL ·
`fidelidade_assinatura_id` NULL · `lista_espera_id` NULL · `cancelado_em` DATETIME NULL ·
`cancelado_por_tipo` VARCHAR(16) NULL · `motivo_cancelamento` VARCHAR(255) · `concluido_em` DATETIME NULL ·
`criado_por_tipo` VARCHAR(16) · `criado_por_id` BIGINT NULL
**UQ** `(codigo)`, `(requisicao_id)` · **IDX** `(empresa_id, inicio)`, `(profissional_id, inicio)`,
`(unidade_id, inicio)`, `(empresa_id, status)`, `(cliente_id, inicio)`, `(reserva_expira_em)`

**`agendamento_servicos`** — snapshot comercial.
`empresa_id` · `agendamento_id` · `servico_id` · `profissional_id` · `ordem` TINYINT ·
`nome_snapshot` VARCHAR(120) · `preco_snapshot` DECIMAL(12,2) · `duracao_snapshot` SMALLINT ·
`comissao_tipo_snapshot` VARCHAR(12) · `comissao_valor_snapshot` DECIMAL(12,2) ·
`coberto_por_assinatura` TINYINT(1) — **IDX** `(agendamento_id)`

**`agendamento_status_historico`** — append-only: `empresa_id` · `agendamento_id` · `de` VARCHAR(24) NULL ·
`para` VARCHAR(24) · `motivo` VARCHAR(255) · `ator_tipo` · `ator_id` · `criado_em` —
**IDX** `(agendamento_id, criado_em)`

**`agenda_bloqueios_concorrencia`** — trava técnica de serialização.
`profissional_id` BIGINT UNSIGNED · `data` DATE · `atualizado_em` DATETIME — **PK** `(profissional_id, data)`

**`agenda_bloqueios`** — bloqueios manuais de horário.
`empresa_id` · `unidade_id` · `profissional_id` NULL (NULL = unidade inteira) · `inicio` DATETIME ·
`fim` DATETIME · `motivo` VARCHAR(160) · `criado_por` — **IDX** `(empresa_id, unidade_id, inicio, fim)`

**`lista_espera`** — `empresa_id` · `unidade_id` · `cliente_id` · `servico_id` · `profissional_id` NULL ·
`data_preferida` DATE · `periodo` VARCHAR(12) (`manha`,`tarde`,`noite`,`qualquer`) · `prioridade` TINYINT ·
`status` VARCHAR(16) (`aguardando`,`notificado`,`convertido`,`expirado`,`cancelado`) · `tentativas` TINYINT ·
`notificado_em` DATETIME NULL · `reserva_expira_em` DATETIME NULL · `agendamento_id` NULL ·
`expira_em` DATETIME NULL — **IDX** `(empresa_id, status, prioridade, criado_em)`

---

## 10. Domínio I — Atendimento e vendas

**`comandas`** — `empresa_id` · `unidade_id` · `cliente_id` · `agendamento_id` NULL · `caixa_id` NULL ·
`profissional_id` NULL · `numero` INT · `status` VARCHAR(16) (`aberta`,`fechada`,`cancelada`) ·
`subtotal` DECIMAL(12,2) · `desconto` DECIMAL(12,2) · `acrescimo` DECIMAL(12,2) · `total` DECIMAL(12,2) ·
`fechada_em` DATETIME NULL · `fechada_por` NULL · `cancelada_em` DATETIME NULL
**UQ** `(empresa_id, numero)` · **IDX** `(empresa_id, status, criado_em)`, `(agendamento_id)`

**`comanda_itens`** — `empresa_id` · `comanda_id` · `tipo` VARCHAR(12) (`servico`,`produto`,`plano`,`ajuste`) ·
`referencia_id` BIGINT NULL · `descricao_snapshot` VARCHAR(160) · `quantidade` DECIMAL(10,3) ·
`preco_unitario` DECIMAL(12,2) · `total` DECIMAL(12,2) · `valor_dinheiro` DECIMAL(12,2) ·
`valor_referencia` DECIMAL(12,2) · `profissional_id` NULL · `comissao_tipo_snapshot` VARCHAR(12) NULL ·
`comissao_valor_snapshot` DECIMAL(12,2) NULL · `fidelidade_utilizacao_id` NULL · `criado_por`
**IDX** `(comanda_id)`, `(empresa_id, criado_em)`

**`comanda_pagamentos`** — `empresa_id` · `comanda_id` · `forma_pagamento_id` · `valor` DECIMAL(12,2) ·
`parcelas` TINYINT · `bandeira` VARCHAR(30) NULL · `troco` DECIMAL(12,2) · `caixa_movimento_id` NULL ·
`estornado_em` DATETIME NULL — **IDX** `(comanda_id)`

---

## 11. Domínio J — Financeiro e caixa (tenant)

**`formas_pagamento`** — `empresa_id` · `nome` VARCHAR(60) · `tipo` VARCHAR(16)
(`dinheiro`,`cartao_credito`,`cartao_debito`,`pix`,`transferencia`,`outro`) · `taxa_percentual` DECIMAL(5,2) ·
`afeta_caixa` TINYINT(1) · `ativo` TINYINT(1) — **UQ** `(empresa_id, nome)`

**`caixas`** — `empresa_id` · `unidade_id` · `aberto_por` · `aberto_em` DATETIME · `valor_abertura` DECIMAL(12,2) ·
`fechado_por` NULL · `fechado_em` DATETIME NULL · `valor_sistema` DECIMAL(12,2) NULL ·
`valor_informado` DECIMAL(12,2) NULL · `diferenca` DECIMAL(12,2) NULL · `observacao` VARCHAR(255) ·
`status` VARCHAR(12) (`aberto`,`fechado`)
**IDX** `(empresa_id, unidade_id, status)`

**`caixa_movimentos`** — `empresa_id` · `caixa_id` · `tipo` VARCHAR(16)
(`venda`,`recebimento`,`despesa`,`sangria`,`reforco`,`estorno`) · `forma_pagamento_id` NULL ·
`valor` DECIMAL(12,2) · `descricao` VARCHAR(180) · `comanda_id` NULL · `lancamento_id` NULL ·
`estorno_de_id` NULL · `criado_por` — **IDX** `(caixa_id, criado_em)` · **UQ** `(estorno_de_id)`

**`financeiro_categorias`** — `empresa_id` · `nome` VARCHAR(80) · `tipo` VARCHAR(12) (`receita`,`despesa`) ·
`ativo` TINYINT(1) — **UQ** `(empresa_id, nome, tipo)`

**`financeiro_lancamentos`** — `empresa_id` · `unidade_id` NULL · `tipo` VARCHAR(12) (`receita`,`despesa`) ·
`categoria_id` NULL · `descricao` VARCHAR(180) · `valor` DECIMAL(12,2) · `competencia` CHAR(7) ·
`vencimento` DATE NULL · `pago_em` DATE NULL · `status` VARCHAR(16) (`aberto`,`pago`,`cancelado`) ·
`origem` VARCHAR(24) (`manual`,`comanda`,`assinatura`,`comissao`,`compra`,`estorno`) · `origem_id` BIGINT NULL ·
`caixa_id` NULL · `forma_pagamento_id` NULL · `chave_idempotencia` VARCHAR(80) NULL · `estorno_de_id` NULL ·
`observacao` VARCHAR(255) · `criado_por`
**UQ** `(chave_idempotencia)`, `(estorno_de_id)` · **IDX** `(empresa_id, competencia)`,
`(empresa_id, status, vencimento)`, `(empresa_id, tipo, pago_em)`

**`comissoes`** — `empresa_id` · `profissional_id` · `competencia` CHAR(7) · `comanda_item_id` NULL ·
`agendamento_id` NULL · `base_calculo` DECIMAL(12,2) · `tipo` VARCHAR(12) · `percentual` DECIMAL(5,2) NULL ·
`valor` DECIMAL(12,2) · `status` VARCHAR(16) (`pendente`,`fechada`,`paga`,`estornada`) ·
`fechamento_id` NULL · `estorno_de_id` NULL · `observacao` VARCHAR(255)
**UQ** `(comanda_item_id)`, `(estorno_de_id)` · **IDX** `(empresa_id, profissional_id, competencia)`,
`(empresa_id, status)`

**`comissao_fechamentos`** — `empresa_id` · `profissional_id` · `periodo_inicio` DATE · `periodo_fim` DATE ·
`total` DECIMAL(12,2) · `status` VARCHAR(12) (`aberto`,`fechado`,`pago`) · `fechado_em` DATETIME NULL ·
`pago_em` DATETIME NULL · `lancamento_id` NULL · `criado_por`
**IDX** `(empresa_id, profissional_id, periodo_inicio)`

**`metas`** — metas por escopo: `empresa_id` · `unidade_id` NULL · `competencia` CHAR(7) ·
`meta_faturamento` DECIMAL(12,2) · `meta_atendimentos` INT · `meta_ticket_medio` DECIMAL(12,2)

**`relatorios_salvos`** — `empresa_id` · `usuario_id` · `nome` VARCHAR(120) · `tipo` VARCHAR(60) ·
`filtros` JSON · `publico` TINYINT(1)

---

## 12. Domínio K — Estoque e produtos

**`produto_categorias`** — `empresa_id` · `nome` VARCHAR(80) · `ativo` TINYINT(1) — **UQ** `(empresa_id, nome)`

**`fornecedores`** — `empresa_id` · `nome` VARCHAR(140) · `cnpj` VARCHAR(18) NULL · `telefone` VARCHAR(20) ·
`email` VARCHAR(160) · `observacoes` TEXT · `ativo` TINYINT(1) · `excluido_em`

**`produtos`** — `empresa_id` · `categoria_id` NULL · `fornecedor_padrao_id` NULL · `sku` VARCHAR(40) NULL ·
`nome` VARCHAR(140) · `descricao` TEXT · `unidade_medida` VARCHAR(8) · `preco_custo` DECIMAL(12,2) ·
`preco_venda` DECIMAL(12,2) · `controla_estoque` TINYINT(1) · `estoque_minimo` DECIMAL(10,3) ·
`comissao_percentual` DECIMAL(5,2) NULL · `ativo` TINYINT(1) · `excluido_em`
**UQ** `(empresa_id, sku)` · **IDX** `(empresa_id, ativo)`

**`estoque_saldos`** — saldo materializado.
`empresa_id` · `unidade_id` · `produto_id` · `quantidade` DECIMAL(10,3) · `atualizado_em`
**UQ** `(unidade_id, produto_id)` · **IDX** `(empresa_id, produto_id)`

**`estoque_movimentos`** — append-only: `empresa_id` · `unidade_id` · `produto_id` · `tipo` VARCHAR(16)
(`entrada`,`saida_venda`,`saida_consumo`,`ajuste`,`inventario`,`transferencia`,`devolucao`) ·
`quantidade` DECIMAL(10,3) (positiva/negativa) · `custo_unitario` DECIMAL(12,2) · `saldo_apos` DECIMAL(10,3) ·
`comanda_id` NULL · `compra_id` NULL · `transferencia_id` CHAR(26) NULL · `motivo` VARCHAR(160) ·
`criado_por` · `criado_em` — **IDX** `(empresa_id, produto_id, criado_em)`

**`compras`** — `empresa_id` · `unidade_id` · `fornecedor_id` NULL · `numero` VARCHAR(20) ·
`data_compra` DATE · `total` DECIMAL(12,2) · `status` VARCHAR(12) (`rascunho`,`recebida`,`cancelada`) ·
`lancamento_id` NULL · `criado_por` — **UQ** `(empresa_id, numero)`

**`compra_itens`** — `empresa_id` · `compra_id` · `produto_id` · `quantidade` DECIMAL(10,3) ·
`custo_unitario` DECIMAL(12,2) · `total` DECIMAL(12,2)

---

## 13. Domínio L — Fidelidade e assinaturas dos consumidores

**`fidelidade_planos`** — `empresa_id` · `nome` VARCHAR(120) · `descricao` TEXT ·
`preco` DECIMAL(12,2) · `periodicidade` VARCHAR(12) (`mensal`,`bimestral`,`trimestral`,`semestral`,`anual`) ·
`creditos_por_ciclo` SMALLINT · `limite_semanal` SMALLINT NULL · `limite_diario` SMALLINT NULL ·
`permite_dependentes` TINYINT(1) · `max_dependentes` TINYINT · `compartilha_creditos` TINYINT(1) ·
`renovacao_automatica` TINYINT(1) · `dias_validade_ciclo` SMALLINT · `ativo` TINYINT(1) ·
`visivel_marketplace` TINYINT(1) · `excluido_em`
**IDX** `(empresa_id, ativo)`

**`fidelidade_plano_servicos`** — `empresa_id` · `plano_id` · `servico_id` —
**UQ** `(plano_id, servico_id)`

**`fidelidade_planos_unidades`** — `empresa_id` · `plano_id` · `unidade_id` —
**UQ** `(plano_id, unidade_id)`

**`fidelidade_planos_profissionais`** — `empresa_id` · `plano_id` · `profissional_id` —
**UQ** `(plano_id, profissional_id)`

**`fidelidade_assinaturas`** — `empresa_id` · `plano_id` · `cliente_titular_id` · `status` VARCHAR(24)
(`pagamento_pendente`,`ativa`,`vencida`,`suspensa`,`cancelada`) · `inicio` DATE · `fim` DATE NULL ·
`preco_snapshot` DECIMAL(12,2) · `regras_snapshot` JSON · `renovacao_automatica` TINYINT(1) ·
`cancelada_em` DATETIME NULL · `motivo_cancelamento` VARCHAR(255)
**IDX** `(empresa_id, status)`, `(cliente_titular_id)`

**`fidelidade_assinatura_membros`** — `empresa_id` · `assinatura_id` · `cliente_id` · `papel` VARCHAR(12)
(`titular`,`dependente`) · `limite_individual` SMALLINT NULL · `ativo` TINYINT(1) ·
`entrou_em` DATE · `removido_em` DATE NULL — **UQ** `(assinatura_id, cliente_id)` ·
**IDX** `(empresa_id, cliente_id)`

**`fidelidade_ciclos`** — `empresa_id` · `assinatura_id` · `numero` SMALLINT · `inicio` DATE · `fim` DATE ·
`creditos_concedidos` SMALLINT · `creditos_utilizados` SMALLINT · `status` VARCHAR(12)
(`ativo`,`encerrado`,`cancelado`) · `encerrado_em` DATETIME NULL
**UQ** `(assinatura_id, numero)` · **IDX** `(empresa_id, status, fim)`

**`fidelidade_utilizacoes`** — `empresa_id` · `ciclo_id` · `assinatura_id` · `membro_id` ·
`agendamento_id` · `comanda_item_id` NULL · `servico_id` · `profissional_id` · `unidade_id` ·
`data_uso` DATE · `creditos` SMALLINT · `estornada_em` DATETIME NULL · `motivo_estorno` VARCHAR(255) ·
`criado_por`
**UQ** `(agendamento_id)` · **IDX** `(empresa_id, ciclo_id, data_uso)`, `(membro_id, data_uso)`

**`fidelidade_movimentacoes`** — ledger imutável.
`empresa_id` · `ciclo_id` · `assinatura_id` · `utilizacao_id` NULL · `tipo` VARCHAR(16)
(`concessao`,`consumo`,`estorno`,`expiracao`,`ajuste`) · `creditos` SMALLINT (com sinal) ·
`saldo_apos` SMALLINT · `descricao` VARCHAR(180) · `estorno_de_id` NULL · `criado_por` · `criado_em`
**UQ** `(utilizacao_id)`, `(estorno_de_id)` · **IDX** `(empresa_id, ciclo_id, criado_em)`

Semântica do estorno: o movimento de `consumo` grava `utilizacao_id` e `estorno_de_id = NULL`; o
movimento de `estorno` grava `utilizacao_id = NULL` e `estorno_de_id` apontando para o movimento de
consumo. Assim as duas chaves únicas impedem consumo duplicado **e** estorno duplicado.

**`fidelidade_pagamentos`** — `empresa_id` · `assinatura_id` · `ciclo_id` NULL · `valor` DECIMAL(12,2) ·
`forma_pagamento_id` NULL · `data_pagamento` DATE · `status` VARCHAR(12) (`pendente`,`pago`,`estornado`) ·
`lancamento_id` NULL · `comprovante_arquivo_id` NULL · `registrado_por` · `estorno_de_id` NULL
**UQ** `(estorno_de_id)` · **IDX** `(empresa_id, assinatura_id, data_pagamento)`

**`fidelidade_programas`** — `empresa_id` · `nome` VARCHAR(80) · `tipo` VARCHAR(12)
(`pontos`,`carimbo`,`cashback`) · `regra` VARCHAR(24) (`por_valor`,`por_visita`,`por_servico`) ·
`pontos_por_real` DECIMAL(8,3) NULL · `validade_dias` SMALLINT NULL · `ativo` TINYINT(1)

**`fidelidade_programa_recompensas`** — `empresa_id` · `programa_id` · `nome` VARCHAR(120) ·
`descricao` VARCHAR(255) · `custo_pontos` INT · `tipo` VARCHAR(16) (`servico`,`desconto`,`produto`) ·
`referencia_id` NULL · `valor_desconto` DECIMAL(12,2) NULL · `ativo` TINYINT(1)

**`fidelidade_saldos`** — `empresa_id` · `programa_id` · `cliente_id` · `saldo` INT · `atualizado_em` —
**UQ** `(programa_id, cliente_id)` · **IDX** `(empresa_id, cliente_id)`

**`fidelidade_movimentos`** — `empresa_id` · `programa_id` · `cliente_id` · `tipo` VARCHAR(16)
(`acumulo`,`resgate`,`expiracao`,`ajuste`,`estorno`) · `pontos` INT (com sinal) · `saldo_apos` INT ·
`origem_tipo` VARCHAR(24) · `origem_id` BIGINT NULL · `chave_idempotencia` VARCHAR(80) NULL ·
`estorno_de_id` NULL · `criado_em`
**UQ** `(chave_idempotencia)`, `(estorno_de_id)` · **IDX** `(empresa_id, cliente_id, criado_em)`

**`fidelidade_resgates`** — `empresa_id` · `recompensa_id` · `cliente_id` · `pontos` INT ·
`status` VARCHAR(12) (`solicitado`,`aprovado`,`entregue`,`cancelado`) · `comanda_id` NULL ·
`aprovado_por` NULL · `entregue_em` DATETIME NULL · `cancelado_em` DATETIME NULL

---

## 14. Domínio M — Marketplace

**`marketplace_perfis`** — `empresa_id` · `descricao` TEXT · `logo_arquivo_id` NULL · `capa_arquivo_id` NULL ·
`site` VARCHAR(180) NULL · `instagram` VARCHAR(80) NULL · `facebook` VARCHAR(80) NULL ·
`tiktok` VARCHAR(80) NULL · `visivel` TINYINT(1) DEFAULT 1 · `destaque_ate` DATE NULL ·
`aceita_agendamento` TINYINT(1) · `politica_cancelamento` TEXT · `nota_media` DECIMAL(3,2) ·
`total_avaliacoes` INT · `ordem_busca` INT
**UQ** `(empresa_id)` · **IDX** `(visivel, nota_media)`

**`marketplace_midias`** — `empresa_id` · `tipo` VARCHAR(16) (`galeria`,`antes_depois`) · `arquivo_id` ·
`legenda` VARCHAR(160) · `ordem` SMALLINT · `ativo` TINYINT(1)

**`marketplace_avaliacoes`** — `empresa_id` · `unidade_id` · `agendamento_id` · `cliente_id` ·
`profissional_id` NULL · `nota` TINYINT (1–5) · `comentario` VARCHAR(1000) NULL · `status` VARCHAR(16)
(`publicada`,`em_analise`,`oculta`) · `resposta` VARCHAR(1000) NULL · `respondido_em` DATETIME NULL ·
`respondido_por` NULL · `publicada_em` DATETIME NULL
**UQ** `(agendamento_id)` · **IDX** `(empresa_id, status, publicada_em)`, `(profissional_id)`

**`marketplace_avaliacao_denuncias`** — `avaliacao_id` · `empresa_id` · `motivo` VARCHAR(40) ·
`descricao` VARCHAR(500) · `status` VARCHAR(16) (`aberta`,`procedente`,`improcedente`) ·
`resolvido_por` NULL · `resolvido_em` NULL

**`marketplace_eventos`** — analytics própria (sem cookies de terceiros).
`empresa_id` · `unidade_id` NULL · `tipo` VARCHAR(24) (`visualizacao`,`clique_whatsapp`,`inicio_agendamento`,
`reserva_criada`,`confirmacao`,`busca`) · `origem` VARCHAR(24) (`marketplace`,`link`,`busca`) ·
`termo_busca` VARCHAR(120) NULL · `cidade` VARCHAR(80) NULL · `sessao_hash` CHAR(32) NULL · `criado_em`
**IDX** `(empresa_id, tipo, criado_em)`

---

## 15. Domínio N — Marketing

**`campanhas`** — `empresa_id` · `nome` VARCHAR(120) · `tipo` VARCHAR(16)
(`whatsapp`,`link`,`interno`) · `segmento` VARCHAR(40) (`inativos_30`,`inativos_60`,`aniversariantes`,
`frequentes`,`novos`,`assinantes`,`customizado`) · `segmento_filtros` JSON NULL · `mensagem` TEXT ·
`status` VARCHAR(16) (`rascunho`,`pronta`,`enviada`,`cancelada`) · `enviada_em` DATETIME NULL ·
`criado_por` — **IDX** `(empresa_id, status)`

**`campanha_destinatarios`** — `empresa_id` · `campanha_id` · `cliente_id` · `status` VARCHAR(16)
(`pendente`,`aberto`,`enviado`,`convertido`,`descartado`) · `enviado_em` DATETIME NULL ·
`convertido_em` DATETIME NULL · `agendamento_id` NULL — **UQ** `(campanha_id, cliente_id)`

**`vitrine_ofertas`** — `empresa_id` · `titulo` VARCHAR(140) · `descricao` TEXT · `tipo` VARCHAR(16)
(`percentual`,`valor_fixo`,`combo`) · `valor` DECIMAL(12,2) NULL · `servicos` JSON NULL ·
`inicio` DATE · `fim` DATE · `canais` VARCHAR(60) (`marketplace`,`link`,`painel`) · `limite_usos` INT NULL ·
`usos` INT · `ativo` TINYINT(1) — **IDX** `(empresa_id, ativo, inicio, fim)`

**`indicacoes`** — `empresa_id` · `indicador_cliente_id` · `codigo` VARCHAR(16) ·
`indicado_cliente_id` NULL · `indicado_nome` VARCHAR(140) · `indicado_telefone` VARCHAR(20) ·
`status` VARCHAR(16) (`pendente`,`cadastrado`,`atendido`,`recompensado`,`expirado`,`cancelado`) ·
`recompensa_descricao` VARCHAR(160) · `recompensa_tipo` VARCHAR(16) NULL · `recompensa_valor` DECIMAL(12,2) NULL ·
`liberada_em` DATETIME NULL · `primeiro_agendamento_id` NULL
**UQ** `(empresa_id, codigo)` · **IDX** `(empresa_id, indicador_cliente_id)`

---

## 16. Domínio O — Inteligência e indicadores

**`inteligencia_oportunidades`** — `empresa_id` · `tipo` VARCHAR(40)
(`cliente_inativo`,`horario_vago`,`cancelamento`,`assinatura_vencendo`,`servico_crescendo`,
`cliente_potencial`,`profissional_ocioso`,`queda_faturamento`) · `titulo` VARCHAR(160) ·
`descricao` TEXT · `por_que_importa` VARCHAR(400) · `acao_sugerida` VARCHAR(255) ·
`valor_estimado` DECIMAL(12,2) NULL · `referencia_tipo` VARCHAR(24) NULL · `referencia_id` BIGINT NULL ·
`prioridade` TINYINT · `status` VARCHAR(16) (`aberta`,`em_acao`,`concluida`,`dispensada`) ·
`gerada_em` DATETIME · `valida_ate` DATE NULL · `dispensada_por` NULL
**IDX** `(empresa_id, status, prioridade)`, `(empresa_id, tipo, gerada_em)`

**`indicadores_snapshots`** — agregados diários para relatórios rápidos.
`empresa_id` · `unidade_id` NULL · `data` DATE · `atendimentos` INT · `faturamento` DECIMAL(12,2) ·
`ticket_medio` DECIMAL(12,2) · `clientes_novos` INT · `clientes_recorrentes` INT · `cancelamentos` INT ·
`nao_compareceram` INT · `ocupacao_percentual` DECIMAL(5,2) · `assinaturas_ativas` INT
**UQ** `(empresa_id, unidade_id, data)` · **IDX** `(empresa_id, data)`

---

## 17. Estratégia de migrations, atualização e backup

### 17.1 Migrations

- Arquivos `database/migrations/NNN_descricao.sql` (ou classe PHP quando precisa de lógica),
  numerados e **imutáveis depois de aplicados**.
- Tabela `migracoes` guarda versão, arquivo, checksum SHA-256, data e duração.
- Execução: `bin/migrate.php` (CLI) ou `/instalar/migrar` (web, token de uso único, removido depois).
- Alteração de migration já aplicada é **detectada por checksum** e bloqueada com erro claro.
- Cada migration roda em transação quando o engine permite; `ALTER TABLE` de tabela grande é dividido
  em passos e executado fora de horário de pico.
- Ordem de criação respeita as FKs (domínios A → B → C → D → E → F → G → H → I/J/K → L → M → N → O).
- Seeds separadas: catálogo de permissões, papéis padrão, configurações globais, plano SaaS inicial,
  formas de pagamento padrão por empresa, categorias de serviço iniciais.

### 17.2 Evolução de schema

| Situação | Estratégia |
|---|---|
| Nova coluna obrigatória | adicionar `NULL`, preencher em lote, depois tornar `NOT NULL` em migration seguinte |
| Renomear coluna | criar nova, copiar, migrar código, remover antiga em release posterior |
| Nova tabela de tenant | criar com `empresa_id` desde o início + índice composto |
| Remover coluna | parar de usar, marcar como legada, remover após um ciclo de release |
| Índice novo | criar online quando possível; medir antes/depois com `EXPLAIN` |

### 17.3 Backup e recuperação

- `bin/backup.php` gera dump SQL por tabela com `INSERT` em blocos, em `storage/private/backups/`,
  nome `barberify_AAAAMMDD_HHMM.sql.gz`, checksum registrado.
- Download somente por usuário do Control com `control.configuracoes.criticas` + reautenticação.
- Retenção: 7 diários + 4 semanais (arquivos antigos removidos pelo scheduler com registro).
- **Restauração documentada e testada**: importação em base vazia, `migrations` conferidas,
  verificação de contagens por tabela crítica (`empresas`, `agendamentos`, `fidelidade_movimentacoes`).
- Backup nunca inclui `.env`; segredos são restaurados manualmente.
- Retenção LGPD: dados pessoais de cliente excluído logicamente são anonimizados após o prazo
  definido em configuração, preservando lançamentos financeiros.

### 17.4 Índices — critérios

Criar índice quando: coluna aparece em `WHERE`/`JOIN`/`ORDER BY` de consulta real do produto;
cardinalidade justifica; e o índice composto começa pela coluna de maior seletividade dentro do escopo
de tenant (`empresa_id` primeiro, depois status/data). Evitar índice em coluna booleana isolada.
Revisar com `EXPLAIN` nos relatórios da Etapa 8 e ajustar antes de produção.

### 17.5 Volume estimado (para dimensionar)

Uma barbearia média: ~600 clientes, ~120 agendamentos/mês, ~5 profissionais, ~40 serviços,
~30 produtos. `agendamentos` e `comissoes` são as tabelas que mais crescem; `auditoria` e
`logs_aplicacao` crescem mais rápido e têm expurgo configurável (auditoria: 24 meses; logs: 90 dias),
sempre com registro de que o expurgo ocorreu.
