# 02 — Multiempresa, Identidade Global, Barberify Control, Autenticação e Permissões

Voltar ao [índice](00-INDICE.md)

---

## 1. Modelo multiempresa

### 1.1 Conceitos

| Conceito | Definição | Tabela |
|---|---|---|
| **Empresa** | barbearia cliente do SaaS (tenant). Unidade de isolamento. | `empresas` |
| **Unidade** | filial/loja de uma empresa. Tem endereço, horários, caixa, estoque e agenda próprios. | `unidades` |
| **Contexto de tenant** | objeto resolvido no servidor (`empresa_id`, `unidade_ativa`, `usuario`, `permissoes`) | — (runtime) |
| **Escopo global** | dados da Soluções AL / marketplace / planos SaaS, sem `empresa_id` | `control_*`, `saas_planos`, `permissoes` |
| **Escopo de empresa** | tudo o que pertence a uma barbearia | demais tabelas |

### 1.2 Regra de ouro

> `empresa_id` **nunca** é lido de `$_GET`, `$_POST`, JSON, header ou cookie.
> Ele vem exclusivamente da sessão autenticada, resolvida no servidor, e é validado contra o
> vínculo do usuário com a empresa.

Consequências práticas:

- Um usuário pode ter vínculo com **uma** empresa (v1). O vínculo fica em `usuarios_empresa.empresa_id`.
  Caso futuro de um contador com acesso a várias empresas: tabela `usuario_empresa_vinculos`, sem
  mudar o resto do desenho.
- Trocar de unidade é uma **ação autorizada**: `unidade_ativa` só é aceita se a unidade pertencer à
  empresa da sessão **e** estiver na lista `usuario_empresa_unidades` do usuário (ou se ele tiver
  permissão `unidades.todas`).
- Toda consulta de tenant carrega `empresa_id = :empresa_id`; toda gravação preenche a coluna a partir
  do contexto, jamais do formulário.

### 1.3 Defesa em três camadas

| Camada | Mecanismo | Exemplo |
|---|---|---|
| 1. Estrutural | Coluna `empresa_id BIGINT UNSIGNED NOT NULL` + FK + índice composto em toda tabela de tenant | `KEY (empresa_id, status, inicio)` |
| 2. Código | `TenantContext` obrigatório no repositório; `Repository::forTenant()` falha com exceção se ausente; nenhum `SELECT * FROM clientes WHERE id = ?` sem `empresa_id` | `WHERE empresa_id = :e AND id = :id` |
| 3. Verificação | Teste automatizado de isolamento (dois tenants) em cada módulo entregue + auditoria de acesso a registro de outra empresa | 06 §4.5 |

Regras adicionais:

- Chaves únicas de negócio são **compostas com `empresa_id`**: `UNIQUE (empresa_id, slug)`,
  `UNIQUE (empresa_id, telefone_normalizado)`, `UNIQUE (empresa_id, codigo)`.
- Chaves estrangeiras de tabelas filhas carregam a empresa quando o risco de “colar” registro de outra
  empresa é real (ex.: `agendamento_servicos` referencia `agendamentos(id)` e guarda `empresa_id`
  redundante, validado por serviço na gravação).
- IDs são numéricos sequenciais internos; URLs públicas usam **código/slug aleatório**
  (`agendamentos.codigo`, `empresas.slug`), reduzindo enumeração.
- Consultas de contagem/relatório sempre agregam com `empresa_id` no `WHERE`/`GROUP BY`.
- `JOIN` entre tabelas de tenant repete `empresa_id` nas duas pontas (`ON a.empresa_id = b.empresa_id`),
  impedindo cruzamento acidental.
- Arquivos: `arquivos` guarda `empresa_id`; download privado passa por controller que valida o tenant.
  Upload público só gera URL opaca e é servido como estático.

### 1.4 Multiunidades dentro da empresa

| Aspecto | Regra |
|---|---|
| Escopo dos dados | agenda, caixa, estoque, horários e bloqueios são **por unidade**; clientes, serviços e profissionais são **da empresa**, associados a unidades |
| Serviços | preço e duração podem variar por unidade (`servico_unidades`); sem preço específico, herda o da empresa |
| Profissionais | atuam nas unidades listadas em `profissional_unidades`; horário de trabalho é por profissional + unidade + dia da semana |
| Usuários | acesso restrito às unidades vinculadas, salvo `unidades.todas` |
| Relatórios | sempre com filtro “Consolidado” ou unidade específica; consolidado nunca soma estoque entre unidades (quantidades não são aditivas para decisão) |
| Transferência de estoque | movimento `transferencia` com par saída/entrada e `transferencia_id` comum |
| Caixa | um caixa aberto por unidade por vez (índice único parcial garantido por regra de serviço + validação transacional) |

### 1.5 Isolamento de sessão e de cache

- Cache de arquivo sempre com chave prefixada por `empresa_id` e `unidade_ativa`.
- Nenhum fragmento de HTML de tenant é cacheado em página compartilhada do marketplace sem
  autorização pública explícita (`marketplace_perfis.visivel = 1`).
- Logs nunca misturam contexto: cada linha carrega `empresa_id` quando aplicável.

---

## 2. Identidade global do proprietário

### 2.1 Separação conceitual obrigatória

| Papel | Onde vive | Escopo | Pode ver dados de clientes das barbearias? |
|---|---|---|---|
| **Proprietário Global (Soluções AL)** | `control_usuarios` com `is_proprietario = 1` | plataforma inteira | Não por padrão; só via acesso de suporte justificado, autorizado e auditado |
| **Equipe interna** (admin, financeiro, comercial, suporte, desenvolvimento) | `control_usuarios` + `control_usuario_papeis` | conforme permissão `control.*` | Não por padrão |
| **Proprietário da empresa** | `usuarios_empresa` com `is_proprietario = 1` | a própria barbearia | Sim, da própria empresa |
| **Consumidor** | `contas_cliente` | próprios dados | — |

Nenhuma linha de `control_usuarios` possui `empresa_id`. Nenhum usuário de barbearia acessa `/control`.
Nenhum usuário do Control é criado como usuário comum “disfarçado”.

### 2.2 Criação e ciclo de vida da conta do proprietário

- Criada no instalador por `bin/criar-proprietario.php` (ou pelo assistente web), com senha definida
  no ato, **forçando troca no primeiro login**.
- Existe **exatamente um** `is_proprietario = 1`, garantido por índice único
  `UNIQUE (is_proprietario)` com valor nulo para os demais (`NULL` não colide em MySQL).
- A conta nasce com: 2FA TOTP habilitável, 10 códigos de recuperação de uso único, `email`
  confirmado, `protegido = 1`.
- Não pode ser desativada, excluída, renomeada para outro e-mail sem confirmação dupla, nem ter
  permissões reduzidas.

### 2.3 Matriz de proteção (o que ninguém consegue fazer com a conta do proprietário)

| Ação tentada | Resultado |
|---|---|
| Outro usuário do Control editar nome/e-mail | Bloqueado no serviço (`ProtegidoException`) + auditoria de tentativa |
| Alterar a senha do proprietário | Impossível: troca de senha exige sessão **do próprio** + senha atual + código 2FA |
| Remover o papel de proprietário / reduzir permissões | Bloqueado; `is_proprietario` não é campo editável por formulário |
| Excluir a conta | Bloqueado por código e por `FK RESTRICT` nas referências de auditoria |
| Criar um segundo proprietário | Só com confirmação dupla do proprietário atual + registro em auditoria; índice único impede duplicidade |
| Transferir propriedade | Fluxo dedicado: senha atual + 2FA + código de confirmação + carência de 24 h com possibilidade de cancelamento |
| Revogar as próprias sessões por terceiro | Só o proprietário revoga as próprias; outros podem apenas **solicitar** |
| Desativar 2FA do proprietário por outro usuário | Bloqueado |
| Alterar configurações críticas sozinho | Exigem `control.configuracoes.criticas` **e** confirmação de senha (reautenticação) |

### 2.4 Autenticação reforçada do proprietário

1. **Senha** com política forte (mínimo 12 caracteres, bloqueio de senhas comuns e de dados do
   próprio cadastro, `password_hash` com `PASSWORD_DEFAULT` e rehash automático).
2. **2FA TOTP** (RFC 6238) implementado com `hash_hmac` — sem dependência externa, funciona em
   hospedagem compartilhada. Segredo cifrado em repouso com chave do `.env`.
3. **Códigos de recuperação**: 10 códigos, hash armazenado, uso único, regeneração invalida os antigos.
4. **Reautenticação** para ações críticas (suspender empresa, alterar plano, registrar pagamento,
   alterar permissões, exportar dados, acessar dados de suporte).
5. **Sessão curta e absoluta**: expiração deslizante de 30 min de inatividade e limite absoluto de
   8 h; token rotacionado a cada elevação de privilégio.
6. **Revogação de sessões**: lista de sessões ativas (IP, agente, último uso) com botão de revogar
   todas exceto a atual.
7. **Registro de logins**: sucesso e falha em `control_log_acessos`, com IP, agente e motivo.
8. **Throttle**: 5 falhas por e-mail/IP em 15 min → espera progressiva; 20 falhas → bloqueio temporário
   de 30 min, auditado. Nunca revela se o e-mail existe.
9. **Alertas no painel**: novo login de IP desconhecido, troca de senha, alteração de 2FA, criação de
   administrador, suspensão de empresa.

### 2.5 Recuperação de acesso sem e-mail confiável

Ordem de tentativa:

1. **Código de recuperação** (offline, impresso pelo proprietário) → redefine senha + 2FA.
2. **Reset assistido no servidor** (somente se houver acesso ao arquivo `.env`/banco):
   `bin/recuperar-proprietario.php` exige um `OWNER_RECOVERY_KEY` do `.env`, gera token de uso único
   válido por 30 min e registra auditoria com origem “CLI”.
3. **E-mail**, se SMTP estiver configurado e funcionando: link assinado de uso único, 30 min.

Nenhum caminho permite reset por simples conhecimento do e-mail. Todos geram auditoria e invalidam
sessões existentes.

### 2.6 Modelo de ameaças do que é mais sensível

| Ameaça | Mitigação específica |
|---|---|
| Escalonamento horizontal (admin interno vira proprietário) | `is_proprietario` imutável por formulário; fluxo de transferência com dupla confirmação e carência |
| Sequestro de sessão do Control | cookie `HttpOnly`+`Secure`+`SameSite=Strict`, token rotativo, hash no banco, vínculo com agente/IP registrado e alerta de mudança |
| Força bruta | throttle + bloqueio progressivo + 2FA + senha forte |
| Acesso silencioso a dados de clientes | acesso de suporte exige ticket, justificativa, autorização e expira em 24 h, tudo auditado (03 do Control, §3.6) |
| Exclusão acidental da plataforma | nenhuma exclusão física de empresa pelo Control; apenas suspensão/cancelamento com retenção |
| Vazamento por backup | backups em `storage/private`, download autenticado, nomes aleatórios, expiração |

---

## 3. Arquitetura do Barberify Control

### 3.1 Princípio

O Control é uma aplicação **separada por realm, layout, permissões e tabelas** dentro do mesmo
codebase. Ele não compartilha sessão, menu, guarda nem consultas com o painel das barbearias. Toda
consulta dele é de **escopo global** (`empresas`, `saas_*`) ou de leitura agregada; leitura de dados
operacionais de uma empresa é exceção controlada (§3.6).

### 3.2 Mapa de páginas

| Módulo | Páginas | Permissões |
|---|---|---|
| Dashboard global | KPIs, gráfico de crescimento, alertas, próximos vencimentos | `control.dashboard.ver` |
| Empresas | lista com filtros, ficha da empresa (cadastro, proprietário, usuários, unidades, plano, assinatura, pagamentos, histórico), suspender/reativar/cancelar, observações | `control.empresas.ver/criar/editar/suspender` |
| Planos SaaS | lista, criar/editar, limites e benefícios, ativar/desativar, empresas vinculadas | `control.planos.ver/gerenciar` |
| Assinaturas SaaS | lista, ficha, período de teste, registrar pagamento, renovar, marcar inadimplência, suspender, reativar, cancelar | `control.assinaturas.ver/gerenciar/pagamentos` |
| Financeiro global | receitas SaaS, recebimentos, pendências, inadimplência, receita por plano/período, próximos vencimentos | `control.financeiro.ver/registrar` |
| Equipe interna | usuários do Control, papéis, permissões, convites, revogação de sessão | `control.equipe.ver/gerenciar` |
| Suporte | tickets das barbearias, prioridade, responsável, mensagens, resolução, acesso autorizado | `control.suporte.ver/responder/acesso-dados` |
| Comunicados | criar, segmentar (todas, por plano, por empresa), publicar, histórico de leitura | `control.comunicados.ver/publicar` |
| Monitoramento | erros recentes, falhas de login, saúde do banco, tarefas do scheduler, fila de e-mail | `control.monitoramento.ver` |
| Auditoria | busca por ator, ação, entidade, período, resultado | `control.auditoria.ver` |
| Configurações globais | marca, e-mail, limites padrão, parâmetros de cobrança, retenção, manutenção | `control.configuracoes.ver/editar` (+ `criticas` + reauth) |
| Proprietário | sessões, 2FA, códigos de recuperação, transferência de propriedade | `control.proprietario.self` (somente ele) |

### 3.3 Indicadores do dashboard (definições exatas, não “bonitos”)

| Indicador | Definição |
|---|---|
| Total de barbearias | `COUNT(*) FROM empresas WHERE excluido_em IS NULL` |
| Ativas | assinatura SaaS em `ativa` |
| Em teste | assinatura em `em_teste` com `fim_teste >= hoje` |
| Inadimplentes | assinatura em `inadimplente` |
| Suspensas | `empresas.status = 'suspensa'` |
| MRR | soma do valor mensalizado das assinaturas `ativa` (plano anual dividido por 12, documentado) |
| Recebido no mês | soma de `saas_pagamentos` do mês, excluindo estornados |
| A receber | soma de `saas_faturas` abertas/vencidas não pagas |
| Novas empresas (mês) | `COUNT(*)` por `criado_em` no mês |
| Cancelamentos (mês) | transições para `cancelada` em `saas_assinatura_eventos` |
| Crescimento | variação de MRR e de empresas ativas versus mês anterior |
| Alertas | faturas vencidas há > 5 dias, empresas em teste vencendo em 2 dias, empresas sem login há 30 dias, tarefas com falha, erros críticos nas últimas 24 h |

Nenhum indicador é digitado ou estimado: todos vêm de consulta ao banco. Valores estimados aparecem
somente na Inteligência das barbearias e sempre rotulados como estimativa.

### 3.4 Gestão de empresas — regras

- Criar empresa cria também: unidade principal, usuário proprietário, configurações padrão e
  assinatura em teste de **7 dias** (transação única).
- Suspender/cancelar **não exclui dados**. Suspensão bloqueia operação, mantém login e cobrança.
- Toda ação grava `control_observacoes_empresa` (quando texto) e `auditoria`.
- Alterar plano de uma empresa cria **nova vigência**; o histórico anterior permanece em
  `saas_assinatura_eventos` e nas faturas já emitidas. Nunca recálculo retroativo.
- Exclusão física de empresa não é oferecida na interface (apenas por rotina técnica documentada e
  com exportação prévia).

### 3.5 Financeiro global

- Faturas (`saas_faturas`) e pagamentos (`saas_pagamentos`) com `DECIMAL(12,2)`, histórico imutável,
  estorno como novo registro.
- Pagamento manual exige: fatura, valor, forma, data, comprovante (upload privado) e identificação de
  quem registrou. Recalcula situação da assinatura na mesma transação.
- Relatórios: receita por plano, por período, por forma, inadimplência, aging de vencidos.
- **Nenhuma** linha do financeiro global referencia `financeiro_lancamentos` das barbearias.

### 3.6 Suporte com acesso excepcional a dados de empresas

Fluxo obrigatório (nada de acesso silencioso):

1. O ticket registra o problema e o cliente autoriza por escrito no próprio ticket.
2. O atendente solicita acesso (`control_acessos_suporte`): empresa, escopo (módulos), justificativa,
   duração (máx. 24 h).
3. O proprietário global **ou** um administrador com `control.suporte.acesso-dados` aprova.
4. Durante a janela, o acesso é **somente leitura** e cada tela visitada gera auditoria com
   `ticket_id` e justificativa.
5. Expira automaticamente; encerrar antes é possível. Nenhuma exportação em massa é permitida nesse modo.

### 3.7 Monitoramento e auditoria

- `logs_aplicacao`: nível, mensagem, contexto (sem dados sensíveis), `request_id`, empresa quando
  aplicável, agrupamento por impressão digital para evitar spam.
- `auditoria`: ator (tipo + id), ação, entidade, `entidade_id`, dados antes/depois em JSON,
  IP, agente, resultado, `empresa_id` nulo no escopo global.
- Eventos mínimos auditados (lista fechada na Etapa 3): login/logout, falha de login, criação/alteração
  de usuário e papel, alteração de permissão, suspensão/reativação de empresa, mudança de plano,
  registro/estorno de pagamento, abertura/fechamento de caixa, cancelamento de agendamento,
  estorno de comissão, consumo/estorno de fidelidade, acesso de suporte, alteração de configuração
  crítica, exclusão lógica, exportação de dados.

---

## 4. Autenticação e permissões

### 4.1 Realms independentes

| Realm | Cookie | Tabela de sessão | Guarda | Expiração |
|---|---|---|---|---|
| Empresa | `bf_emp` | `usuario_sessoes` | `AuthEmpresa` | 8 h deslizante, absoluto 12 h |
| Control | `bf_ctl` | `control_sessoes` | `AuthControl` | 30 min inativo, absoluto 8 h |
| Cliente | `bf_cli` | `conta_cliente_sessoes` | `AuthCliente` | 30 dias deslizante |

Nenhuma sessão é aceita em área de outro realm, mesmo com cookie válido.
Cookies: `HttpOnly`, `Secure` (obrigatório em produção), `SameSite=Lax` no painel/cliente e
`SameSite=Strict` no Control, `Path` restrito ao prefixo da área quando possível.

### 4.2 Fluxo de login (empresa e control)

1. `POST /login` com e-mail + senha + token CSRF.
2. Throttle por IP+e-mail antes de consultar o banco.
3. Busca por e-mail normalizado; compara com `password_hash`; mensagem genérica em qualquer falha.
4. Verifica status (`ativo`), empresa não suspensa para o painel (suspensão da empresa permite login
   apenas para cobrança), e se exige troca de senha.
5. Sucesso: **regenera o token de sessão** (previne fixação), grava sessão, atualiza `ultimo_acesso`,
   audita, aplica rehash de senha se necessário.
6. Control: se 2FA habilitado, cria sessão em estado `pendente_2fa` que só acessa a tela de código.
7. Logout: apaga a sessão no banco e o cookie; auditoria registrada.

### 4.3 Permissões granulares

Catálogo em `permissoes` (global, versionado por seed), chave no formato `modulo.acao`:

| Módulo | Ações |
|---|---|
| `dashboard` | `ver` |
| `agenda` | `ver`, `criar`, `editar`, `cancelar`, `reagendar`, `bloquear`, `confirmar` |
| `atendimento` | `ver`, `iniciar`, `concluir`, `reabrir` |
| `clientes` | `ver`, `criar`, `editar`, `excluir`, `exportar`, `ver-dados-sensiveis` |
| `profissionais` | `ver`, `criar`, `editar`, `excluir`, `ver-comissoes` |
| `servicos` | `ver`, `criar`, `editar`, `excluir`, `gerenciar-precos` |
| `unidades` | `ver`, `gerenciar`, `todas` |
| `caixa` | `ver`, `abrir`, `fechar`, `movimentar`, `sangria` |
| `financeiro` | `ver`, `lancar`, `editar`, `estornar`, `ver-lucro` |
| `comissoes` | `ver`, `fechar`, `ajustar`, `pagar` |
| `estoque` | `ver`, `movimentar`, `ajustar`, `inventario` |
| `produtos` | `ver`, `criar`, `editar`, `excluir` |
| `vendas` | `ver`, `criar`, `cancelar`, `aplicar-desconto` |
| `fidelidade` | `ver`, `planos`, `assinaturas`, `consumir`, `estornar`, `configurar` |
| `marketing` | `ver`, `campanhas`, `vitrine`, `indicacoes` |
| `relatorios` | `ver`, `exportar`, `financeiros` |
| `inteligencia` | `ver`, `configurar` |
| `usuarios` | `ver`, `criar`, `editar`, `desativar`, `gerenciar-permissoes` |
| `configuracoes` | `ver`, `editar`, `faturamento` |
| `auditoria` | `ver` |

Regras:

- Permissão efetiva = união das permissões dos papéis ativos do usuário, **limitada** pelo teto da
  empresa (nenhuma empresa concede `control.*`).
- `is_proprietario = 1` na empresa tem todas as permissões e **não pode ter o papel removido**.
- Permissão é checada no **backend** por middleware e novamente no serviço para operações sensíveis
  (defesa dupla em dinheiro e cancelamento).
- A view usa `can()` apenas para esconder o que o usuário não pode fazer; nunca como autorização.
- Toda negação de permissão em ação de escrita é auditada com a chave negada.

### 4.4 Papéis padrão (seed) e escopo

| Papel | Escopo típico |
|---|---|
| Proprietário da barbearia | todas as permissões da empresa; imutável quanto a remoção |
| Administrador | tudo, exceto alterar o proprietário e configurações críticas financeiras (configurável) |
| Gerente | agenda, clientes, profissionais, serviços, relatórios, fidelidade, marketing; financeiro limitado |
| Recepcionista | agenda, clientes, atendimento, vendas, caixa operacional; sem lucro nem comissões |
| Barbeiro | própria agenda, próprios atendimentos, próprios clientes (leitura), sem financeiro |
| Financeiro | caixa, financeiro, comissões, relatórios financeiros; sem editar catálogo |
| Estoquista | estoque, produtos, compras, inventário; sem financeiro |

Papéis personalizados: criados pela empresa, com nome livre e conjunto de permissões do catálogo.
Exclusão de papel em uso é bloqueada (é preciso remover os vínculos antes).

### 4.5 Proteção contra escalonamento dentro da empresa

- Ninguém concede permissão que não possui (exceto o proprietário).
- Ninguém edita o próprio papel nem remove o próprio acesso administrativo.
- Alterar papel de outro usuário exige `usuarios.gerenciar-permissoes` + reautenticação (senha).
- Desativar o último usuário com `usuarios.gerenciar-permissoes` é bloqueado.
- Propriedade da empresa só muda por fluxo explícito do proprietário atual (senha + confirmação),
  auditado, com o novo proprietário precisando aceitar o convite.

### 4.6 Cliente (consumidor)

- Autenticação opcional: o agendamento funciona sem conta.
- `contas_cliente` é global (e-mail único, senha opcional, login por link mágico/telefone quando
  disponível). O vínculo com a barbearia é `clientes.conta_cliente_id`, criado quando o cliente
  confirma o mesmo e-mail/telefone ou aceita vincular.
- A conta do cliente nunca acessa dados internos: vê apenas os próprios agendamentos, assinaturas,
  benefícios e avaliações **dentro das empresas onde tem vínculo**.
- Consulta de agendamento por código (`/agendamento/{codigo}`) exige código + sobrenome/telefone
  parcial para reduzir enumeração; nunca expõe telefone completo, endereço de outros clientes ou
  valores internos.

### 4.7 Auditoria de autenticação

Registrar sempre: login com sucesso, login com falha (motivo genérico), bloqueio por throttle,
logout, troca de senha, ativação/desativação de 2FA, geração de códigos de recuperação, revogação de
sessão, criação de sessão de suporte. Nunca registrar senha, token, segredo TOTP ou hash.
