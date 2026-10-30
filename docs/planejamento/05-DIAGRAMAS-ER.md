# 05 — Diagramas de Relacionamentos

Voltar ao [índice](00-INDICE.md) · Modelo detalhado em [04-BANCO-DE-DADOS.md](04-BANCO-DE-DADOS.md)

Diagramas por domínio (um diagrama único com 113 tabelas seria ilegível). As linhas mostram
**relacionamentos com FK**; colunas de ligação lógica sem FK aparecem indicadas.

---

## 1. Plataforma, empresas e unidades

```mermaid
erDiagram
  EMPRESAS ||--o{ EMPRESA_CONFIGURACOES : tem
  EMPRESAS ||--o{ UNIDADES : possui
  EMPRESAS ||--o{ ARQUIVOS : envia
  EMPRESAS ||--o{ AUDITORIA : gera
  UNIDADES ||--o{ UNIDADE_HORARIOS : define
  UNIDADES ||--o{ UNIDADE_BLOQUEIOS : bloqueia
  EMPRESAS ||--o{ NOTIFICACOES : recebe
  EMPRESAS ||--o{ LOGS_APLICACAO : registra

  EMPRESAS {
    bigint id PK
    varchar slug UK
    varchar nome_fantasia
    varchar cpf_cnpj UK
    varchar status
    datetime trial_concedido_em
    datetime excluido_em
  }
  UNIDADES {
    bigint id PK
    bigint empresa_id FK
    varchar slug
    varchar cidade
    decimal latitude
    decimal longitude
    tinyint aceita_agendamento_online
  }
  EMPRESA_CONFIGURACOES {
    bigint id PK
    bigint empresa_id FK
    varchar chave
    text valor
  }
```

---

## 2. Barberify Control (identidade global)

```mermaid
erDiagram
  CONTROL_USUARIOS ||--o{ CONTROL_USUARIO_PAPEIS : possui
  CONTROL_PAPEIS ||--o{ CONTROL_USUARIO_PAPEIS : agrupa
  CONTROL_PAPEIS ||--o{ CONTROL_PAPEL_PERMISSOES : concede
  CONTROL_PERMISSOES ||--o{ CONTROL_PAPEL_PERMISSOES : cataloga
  CONTROL_USUARIOS ||--o{ CONTROL_SESSOES : abre
  CONTROL_USUARIOS ||--o{ CONTROL_LOG_ACESSOS : gera
  CONTROL_USUARIOS ||--o{ CONTROL_CODIGOS_RECUPERACAO : guarda
  CONTROL_USUARIOS ||--o{ CONTROL_TICKETS : responde
  CONTROL_TICKETS ||--o{ CONTROL_TICKET_MENSAGENS : contem
  CONTROL_TICKETS ||--o{ CONTROL_ACESSOS_SUPORTE : autoriza
  CONTROL_COMUNICADOS ||--o{ CONTROL_COMUNICADO_DESTINATARIOS : envia
  EMPRESAS ||--o{ CONTROL_OBSERVACOES_EMPRESA : recebe
  EMPRESAS ||--o{ CONTROL_TICKETS : abre
  EMPRESAS ||--o{ CONTROL_ACESSOS_SUPORTE : solicitado_para
  EMPRESAS ||--o{ CONTROL_COMUNICADO_DESTINATARIOS : destinatario

  CONTROL_USUARIOS {
    bigint id PK
    varchar email UK
    tinyint is_proprietario
    tinyint protegido
    tinyint totp_ativo
    varchar status
  }
  CONTROL_ACESSOS_SUPORTE {
    bigint id PK
    bigint empresa_id FK
    bigint solicitante_id FK
    bigint aprovador_id FK
    text justificativa
    datetime expira_em
    varchar status
  }
```

> `control_*` não possui `empresa_id` como coluna de tenant. O vínculo com empresa, quando existe
> (ticket, acesso de suporte, comunicado), é explícito e auditado.

---

## 3. SaaS (barbearia → Soluções AL)

```mermaid
erDiagram
  SAAS_PLANOS ||--o{ SAAS_PLANO_LIMITES : define
  SAAS_PLANOS ||--o{ SAAS_ASSINATURAS : contrata
  EMPRESAS ||--o{ SAAS_ASSINATURAS : possui
  SAAS_ASSINATURAS ||--o{ SAAS_FATURAS : gera
  SAAS_ASSINATURAS ||--o{ SAAS_ASSINATURA_EVENTOS : registra
  SAAS_FATURAS ||--o{ SAAS_PAGAMENTOS : recebe
  SAAS_PAGAMENTOS ||--o| SAAS_PAGAMENTOS : estorna

  SAAS_ASSINATURAS {
    bigint id PK
    bigint empresa_id FK
    bigint plano_id FK
    varchar status
    date inicio
    date fim_teste
    date proximo_vencimento
    decimal preco_snapshot
    json limites_snapshot
  }
  SAAS_FATURAS {
    bigint id PK
    bigint assinatura_id FK
    bigint empresa_id FK
    char competencia
    decimal valor
    date vencimento
    varchar status
  }
```

---

## 4. Usuários da empresa, papéis e sessões

```mermaid
erDiagram
  EMPRESAS ||--o{ USUARIOS_EMPRESA : emprega
  USUARIOS_EMPRESA ||--o{ USUARIO_PAPEIS : recebe
  PAPEIS ||--o{ USUARIO_PAPEIS : atribui
  PAPEIS ||--o{ PAPEL_PERMISSOES : contem
  PERMISSOES ||--o{ PAPEL_PERMISSOES : concede
  USUARIOS_EMPRESA ||--o{ USUARIO_EMPRESA_UNIDADES : acessa
  UNIDADES ||--o{ USUARIO_EMPRESA_UNIDADES : autoriza
  USUARIOS_EMPRESA ||--o{ USUARIO_SESSOES : abre
  USUARIOS_EMPRESA ||--o{ PROFISSIONAIS : representa

  USUARIOS_EMPRESA {
    bigint id PK
    bigint empresa_id FK
    varchar email
    tinyint is_proprietario
    varchar status
    varchar cargo
  }
  PAPEIS {
    bigint id PK
    bigint empresa_id FK
    varchar slug
    tinyint sistema
  }
  PERMISSOES {
    bigint id PK
    varchar chave UK
    varchar modulo
    tinyint critica
  }
```

**UQ relevantes:** `(empresa_id, email)`, `(empresa_id, is_proprietario)`, `(empresa_id, slug)` em papéis,
`(usuario_id, papel_id)`, `(usuario_id, unidade_id)`.

---

## 5. Clientes, CRM e conta do consumidor

```mermaid
erDiagram
  EMPRESAS ||--o{ CLIENTES : atende
  CLIENTES ||--o{ CLIENTE_ENDERECOS : possui
  CLIENTES ||--o{ CLIENTE_NOTAS : anota
  CLIENTES ||--o{ CLIENTE_CONSENTIMENTOS : consente
  CONTAS_CLIENTE ||--o{ CLIENTES : vincula
  CONTAS_CLIENTE ||--o{ CONTA_CLIENTE_SESSOES : autentica
  CONTAS_CLIENTE ||--o{ CONTA_CLIENTE_TOKENS : valida
  CLIENTES ||--o{ AGENDAMENTOS : agenda
  CLIENTES ||--o{ INDICACOES : indica

  CLIENTES {
    bigint id PK
    bigint empresa_id FK
    varchar telefone_normalizado
    varchar email
    date nascimento
    tinyint consent_marketing
    bigint conta_cliente_id FK
    datetime ultima_visita
  }
  CONTAS_CLIENTE {
    bigint id PK
    varchar email UK
    varchar telefone
    datetime email_verificado_em
  }
```

**Por que duas tabelas:** o cliente é **da empresa** (isolamento e histórico próprios); a conta é
**global e opcional**, criada só quando o consumidor quer ver tudo em um lugar. O vínculo
`clientes.conta_cliente_id` é registrado com consentimento em `cliente_consentimentos`, e cada empresa
continua enxergando apenas as próprias linhas.

---

## 6. Profissionais, catálogo e agenda

```mermaid
erDiagram
  EMPRESAS ||--o{ PROFISSIONAIS : contrata
  PROFISSIONAIS ||--o{ PROFISSIONAL_UNIDADES : atua
  UNIDADES ||--o{ PROFISSIONAL_UNIDADES : recebe
  PROFISSIONAIS ||--o{ PROFISSIONAL_SERVICOS : executa
  SERVICOS ||--o{ PROFISSIONAL_SERVICOS : habilitado
  PROFISSIONAIS ||--o{ PROFISSIONAL_HORARIOS : trabalha
  PROFISSIONAIS ||--o{ PROFISSIONAL_AUSENCIAS : ausenta
  PROFISSIONAIS ||--o{ PROFISSIONAL_METAS : tem
  EMPRESAS ||--o{ SERVICO_CATEGORIAS : organiza
  SERVICO_CATEGORIAS ||--o{ SERVICOS : agrupa
  SERVICOS ||--o{ SERVICO_UNIDADES : precifica
  SERVICOS ||--o{ SERVICO_IMAGENS : exibe
  SERVICOS ||--o{ AGENDAMENTO_SERVICOS : compoe
  AGENDAMENTOS ||--o{ AGENDAMENTO_SERVICOS : detalha
  AGENDAMENTOS ||--o{ AGENDAMENTO_STATUS_HISTORICO : transiciona
  PROFISSIONAIS ||--o{ AGENDAMENTOS : atende
  UNIDADES ||--o{ AGENDAMENTOS : sedia
  AGENDAMENTOS ||--o{ COMANDAS : gera

  AGENDAMENTOS {
    bigint id PK
    bigint empresa_id FK
    bigint unidade_id FK
    bigint profissional_id FK
    bigint cliente_id FK
    varchar codigo UK
    varchar status
    datetime inicio
    datetime fim
    datetime reserva_expira_em
    char requisicao_id UK
  }
  AGENDAMENTO_SERVICOS {
    bigint id PK
    bigint agendamento_id FK
    bigint servico_id FK
    varchar nome_snapshot
    decimal preco_snapshot
    smallint duracao_snapshot
  }
```

**Tabela de trava:** `agenda_bloqueios_concorrencia (profissional_id, data)` — PK composta, sem FK de
negócio, usada só para serializar a checagem de sobreposição.

---

## 7. Atendimento, financeiro e estoque

```mermaid
erDiagram
  COMANDAS ||--o{ COMANDA_ITENS : contem
  COMANDAS ||--o{ COMANDA_PAGAMENTOS : paga
  FORMAS_PAGAMENTO ||--o{ COMANDA_PAGAMENTOS : classifica
  CAIXAS ||--o{ CAIXA_MOVIMENTOS : movimenta
  CAIXAS ||--o{ COMANDAS : agrupa
  FINANCEIRO_CATEGORIAS ||--o{ FINANCEIRO_LANCAMENTOS : classifica
  FINANCEIRO_LANCAMENTOS ||--o{ FINANCEIRO_LANCAMENTOS : estorna
  COMISSOES ||--o| COMISSAO_FECHAMENTOS : agrupa
  PROFISSIONAIS ||--o{ COMISSOES : recebe
  PRODUTOS ||--o{ ESTOQUE_SALDOS : saldo
  UNIDADES ||--o{ ESTOQUE_SALDOS : local
  PRODUTOS ||--o{ ESTOQUE_MOVIMENTOS : movimenta
  COMPRAS ||--o{ COMPRA_ITENS : detalha
  FORNECEDORES ||--o{ COMPRAS : fornece
  PRODUTOS ||--o{ COMANDA_ITENS : vendido

  COMANDAS {
    bigint id PK
    bigint empresa_id FK
    bigint unidade_id FK
    bigint cliente_id FK
    bigint caixa_id FK
    varchar status
    decimal total
  }
  FINANCEIRO_LANCAMENTOS {
    bigint id PK
    bigint empresa_id FK
    varchar tipo
    varchar origem
    bigint origem_id
    decimal valor
    char competencia
    varchar chave_idempotencia UK
    bigint estorno_de_id UK
  }
  ESTOQUE_MOVIMENTOS {
    bigint id PK
    bigint empresa_id FK
    bigint unidade_id FK
    bigint produto_id FK
    varchar tipo
    decimal quantidade
    decimal saldo_apos
  }
```

---

## 8. Fidelidade e assinaturas dos consumidores

```mermaid
erDiagram
  FIDELIDADE_PLANOS ||--o{ FIDELIDADE_PLANO_SERVICOS : cobre
  FIDELIDADE_PLANOS ||--o{ FIDELIDADE_PLANOS_UNIDADES : permite
  FIDELIDADE_PLANOS ||--o{ FIDELIDADE_PLANOS_PROFISSIONAIS : autoriza
  FIDELIDADE_PLANOS ||--o{ FIDELIDADE_ASSINATURAS : contrata
  CLIENTES ||--o{ FIDELIDADE_ASSINATURAS : titular
  FIDELIDADE_ASSINATURAS ||--o{ FIDELIDADE_ASSINATURA_MEMBROS : inclui
  FIDELIDADE_ASSINATURAS ||--o{ FIDELIDADE_CICLOS : cicla
  FIDELIDADE_CICLOS ||--o{ FIDELIDADE_UTILIZACOES : consome
  FIDELIDADE_UTILIZACOES ||--o| FIDELIDADE_MOVIMENTACOES : registra
  FIDELIDADE_ASSINATURAS ||--o{ FIDELIDADE_PAGAMENTOS : paga
  FIDELIDADE_PROGRAMAS ||--o{ FIDELIDADE_PROGRAMA_RECOMPENSAS : oferece
  FIDELIDADE_PROGRAMAS ||--o{ FIDELIDADE_SALDOS : saldo
  CLIENTES ||--o{ FIDELIDADE_SALDOS : possui
  FIDELIDADE_PROGRAMAS ||--o{ FIDELIDADE_MOVIMENTOS : movimenta
  FIDELIDADE_PROGRAMA_RECOMPENSAS ||--o{ FIDELIDADE_RESGATES : resgata
  AGENDAMENTOS ||--o| FIDELIDADE_UTILIZACOES : coberto

  FIDELIDADE_CICLOS {
    bigint id PK
    bigint empresa_id FK
    bigint assinatura_id FK
    smallint numero
    date inicio
    date fim
    smallint creditos_concedidos
    smallint creditos_utilizados
    varchar status
  }
  FIDELIDADE_UTILIZACOES {
    bigint id PK
    bigint empresa_id FK
    bigint ciclo_id FK
    bigint membro_id FK
    bigint agendamento_id UK
    date data_uso
    smallint creditos
  }
  FIDELIDADE_MOVIMENTACOES {
    bigint id PK
    bigint ciclo_id FK
    varchar tipo
    smallint creditos
    smallint saldo_apos
    bigint utilizacao_id UK
    bigint estorno_de_id UK
  }
```

**Invariante:** `saldo` do ciclo = soma de `creditos` em `fidelidade_movimentacoes` daquele ciclo.
Toda operação grava movimento + saldo na mesma transação; nenhum `UPDATE` altera movimento.

---

## 9. Marketplace, marketing e inteligência

```mermaid
erDiagram
  EMPRESAS ||--o| MARKETPLACE_PERFIS : publica
  MARKETPLACE_PERFIS ||--o{ MARKETPLACE_MIDIAS : exibe
  EMPRESAS ||--o{ MARKETPLACE_AVALIACOES : recebe
  AGENDAMENTOS ||--o| MARKETPLACE_AVALIACOES : origina
  MARKETPLACE_AVALIACOES ||--o{ MARKETPLACE_AVALIACAO_DENUNCIAS : denuncia
  EMPRESAS ||--o{ MARKETPLACE_EVENTOS : registra
  EMPRESAS ||--o{ CAMPANHAS : cria
  CAMPANHAS ||--o{ CAMPANHA_DESTINATARIOS : envia
  CLIENTES ||--o{ CAMPANHA_DESTINATARIOS : recebe
  EMPRESAS ||--o{ VITRINE_OFERTAS : publica
  EMPRESAS ||--o{ INDICACOES : programa
  EMPRESAS ||--o{ INTELIGENCIA_OPORTUNIDADES : recebe
  EMPRESAS ||--o{ INDICADORES_SNAPSHOTS : agrega

  MARKETPLACE_PERFIS {
    bigint id PK
    bigint empresa_id FK
    tinyint visivel
    decimal nota_media
    int total_avaliacoes
  }
  MARKETPLACE_AVALIACOES {
    bigint id PK
    bigint empresa_id FK
    bigint agendamento_id FK
    tinyint nota
    varchar status
  }
  INTELIGENCIA_OPORTUNIDADES {
    bigint id PK
    bigint empresa_id FK
    varchar tipo
    decimal valor_estimado
    varchar status
  }
```

---

## 10. Fronteiras que os diagramas tornam explícitas

| Fronteira | Garantia |
|---|---|
| `control_*` ↔ operação da barbearia | nenhuma FK direta; contato só por `control_acessos_suporte` autorizado |
| `saas_*` ↔ `fidelidade_*` | nenhuma FK, nenhum JOIN; tabelas, serviços e telas separados |
| `empresas` ↔ `clientes/serviços/agenda` | sempre com `empresa_id`, FK com `RESTRICT` |
| `contas_cliente` ↔ `clientes` | vínculo opcional (`SET NULL`) e consentido |
| Ledger ↔ operação | ledger nunca é atualizado; operação referencia o lançamento |
