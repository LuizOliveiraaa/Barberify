# 06 — Dependências entre Módulos, Plano de Desenvolvimento, Segurança, Testes e Riscos

Voltar ao [índice](00-INDICE.md)

---

## 1. Matriz de dependências entre módulos

Legenda: **D** = depende de (precisa existir antes) · **F** = fornece para (quem usa depois).

| # | Módulo | D | F |
|---|---|---|---|
| 1 | **Núcleo técnico** (config, PDO, view, sessão, CSRF, validação, auditoria, erros, cache, scheduler) | — | todos |
| 2 | **Instalação e migrations** | 1 | todos |
| 3 | **Control: identidade global** (usuários, papéis, sessões, 2FA, recuperação) | 1, 2 | 4, 5, 6, 7 |
| 4 | **Empresas** (cadastro, status, configurações, unidades) | 1, 2, 3 | 5, 8, 9, 10, 11, 12, 13, 14 |
| 5 | **SaaS** (planos, assinaturas, faturas, pagamentos, cobrança) | 3, 4 | 8 (bloqueio por suspensão), 16 (financeiro global) |
| 6 | **Control: equipe, suporte, comunicados, monitoramento, auditoria** | 3 | — (consumidor final de dados) |
| 7 | **Usuários da empresa, papéis e permissões** | 1, 4 | 8, 9, 10, 11, 12, 13, 14, 15, 16, 17 |
| 8 | **Catálogo de serviços** (+ categorias, preços por unidade) | 4 | 9, 11, 13, 14, 17 |
| 9 | **Profissionais** (+ vínculos, horários, ausências, metas) | 4, 7, 8 | 10, 11, 13, 16, 17 |
| 10 | **Clientes e CRM** | 4, 7 | 11, 12, 13, 14, 15, 16 |
| 11 | **Agenda e agendamentos** (+ disponibilidade, holds, lista de espera) | 4, 7, 8, 9, 10 | 12, 13, 14, 16, 17 |
| 12 | **Atendimento e comandas** | 9, 10, 11 | 13, 15, 17 |
| 13 | **Financeiro, caixa e comissões** | 7, 9, 11, 12 | 16, 17 |
| 14 | **Estoque e produtos** | 7, 12 | 16, 17 |
| 15 | **Fidelidade e assinaturas do consumidor** (+ pontos) | 7, 8, 10, 11, 12 | 13, 16, 17 |
| 16 | **Marketplace** (perfil, busca, avaliações, eventos) | 4, 5, 8, 9, 11 | 18 (aquisição) |
| 17 | **Marketing, Vitrine, Indicações** | 7, 10, 13, 15 | 16, 19 |
| 18 | **Relatórios** | 11, 12, 13, 14, 15 | 19 |
| 19 | **Inteligência, Oportunidades, Recupera, Performance** | 11, 12, 13, 15, 18 | 16, 17 |
| 20 | **Área do cliente** | 10, 11, 15, 16 | — |
| 21 | **Backup, exportação e LGPD operacional** | 1, 2, 4 | — |

Dependências proibidas (verificadas por revisão de código e por teste):

- `saas_*` ↔ `fidelidade_*`: nenhum JOIN, nenhum serviço compartilhado.
- `control_*` ↔ qualquer tabela de tenant em consulta de tela: só via `control_acessos_suporte`.
- Marketplace → dados privados de cliente: nunca (só agregados públicos e avaliação aprovada).
- Painel de uma empresa → tabela de outro tenant: nunca.

---

## 2. Plano de desenvolvimento

Numeração alinhada ao Prompt Mestre: as **Etapas 1 e 2** (arquitetura e banco) são esta entrega de
planejamento. A implementação executável começa na **Etapa 3** e termina na **Etapa 10**.

Cada etapa fecha com critérios de aceite verificáveis e um registro do que foi executado em
`docs/testes/etapa-N.md`. Nenhuma etapa avança com pendência escondida.

### Etapa 3 — Fundação técnica e banco físico

**Entregas:** estrutura de diretórios, autoload, bootstrap, `.env` e instalador com token de uso único,
PDO + `Db::transaction`, migrator com checksum, migrations `001`–`0NN` (todo o schema do documento 04),
seeds (permissões, papéis padrão, papéis do Control, planos SaaS rascunho, configurações globais,
categorias de serviço), quatro layouts e biblioteca de componentes, tratamento de erros
(403/404/419/500), logger, auditoria, os três realms de sessão, CSRF, validador, uploader, throttle,
scheduler com lock, healthcheck e `.htaccess` dos dois layouts.

**Aceite:**
- Instalação limpa em base vazia em ≤ 2 min, com `migracoes` completa e checksums corretos.
- `EXPLAIN` das 10 consultas mais importantes sem `full scan` em tabela populada de teste.
- Instalador bloqueado após uso; reutilizar o token retorna erro auditado.
- Nenhuma credencial em arquivo versionado; `.env` com permissão restrita.
- 404/403/500 renderizam o layout correto sem stack trace; `/status` responde sem expor dados.
- Nenhuma tabela, coluna ou índice fora do documento 04 sem justificativa registrada.

### Etapa 4 — Barberify Control

**Entregas:** login global com 2FA e códigos de recuperação, sessões com revogação, proteções do
proprietário, dashboard global, gestão de empresas (criação transacional: empresa + unidade principal +
proprietário + trial de 7 dias), planos SaaS, assinaturas, faturas, pagamentos manuais com comprovante,
suspensão/reativação/cancelamento, financeiro global, equipe interna e permissões, suporte, comunicados,
monitoramento, auditoria e configurações globais.

**Aceite (todos testados e registrados):**
- Proprietário não pode ser excluído, rebaixado, ter senha alterada por terceiro nem ter 2FA desativado.
- Pagamento manual atualiza fatura e assinatura na mesma transação; estorno não duplica valor.
- Suspender empresa bloqueia a operação e preserva os dados; reativar restaura o acesso.
- Indicadores do dashboard conferem com consulta SQL independente.
- Nenhuma tela do Control lê `clientes`, `agendamentos` ou `financeiro_lancamentos` sem acesso de
  suporte aprovado e temporal.

### Etapa 5 — Operação das barbearias

**Entregas:** login da empresa e troca de senha, usuários/papéis/permissões com telas de gestão,
unidades e horários, clientes e CRM, catálogo de serviços com preço por unidade, profissionais
(vínculos, horários, ausências, metas), configurações da empresa, agenda (diária/semanal/mensal),
disponibilidade no servidor, agendamento manual, confirmação, reagendamento, cancelamento, bloqueios,
folgas/férias, comanda, conclusão de atendimento e histórico de status.

**Aceite:** cada CRUD grava e relê; permissão negada no backend retorna 403 e audita; isolamento
validado com duas empresas; exclusão lógica não quebra histórico; upload validado; nenhum horário
duplo; transições de status inválidas rejeitadas; agenda respeita jornada, intervalo, ausência,
bloqueio e duração combinada.

### Etapa 6 — Marketplace, agendamento online e área do cliente

**Entregas:** home, busca com filtros e proximidade, perfil da empresa, perfil do profissional, páginas
de serviço, wizard de agendamento com hold de 15 min, confirmação interna + link WhatsApp, página de
acompanhamento por código, avaliações com moderação, sitemap/robots/SEO, imagens em variantes e área do
cliente (conta global opcional).

**Aceite:** fluxo completo do wizard em desktop e celular; hold expira e libera o horário; duplo clique
não cria duas reservas; confirmação não duplica; WhatsApp não é usado como prova de envio; avaliação só
de atendimento concluído; empresa oculta não aparece no marketplace mas mantém o painel ativo.

### Etapa 7 — Gestão comercial e fidelidade

**Entregas:** planos de fidelidade com serviços/unidades/profissionais autorizados, assinaturas do
consumidor, membros e dependentes, ciclos, limites semanal/diário, consumo no atendimento concluído,
estornos, pagamentos do plano, programas de pontos/carimbo/cashback, recompensas e resgates, planos
familiares, indicações com recompensa e Vitrine de ofertas.

**Aceite:** todos os testes da seção 13 do Prompt Mestre executados e registrados (ver §4.4),
incluindo concorrência pelo último crédito e estorno sem duplicidade; nenhuma receita duplicada entre
pagamento de plano e uso do benefício; indicação não recompensa duas vezes.

### Etapa 8 — Financeiro, caixa, comissões, estoque e vendas

**Entregas:** abertura/fechamento de caixa, movimentos, sangria/reforço, contas a pagar/receber,
formas de pagamento, comissões e fechamentos, produtos, fornecedores, compras, estoque por unidade,
inventário, transferência entre unidades, vendas na comanda e relatórios financeiros com filtros e
exportação CSV.

**Aceite:** fechamento confere com os movimentos; diferença de caixa registrada; comissão fecha e
estorna sem duplicar; estoque não fica negativo sem configuração explícita; venda baixa estoque e entra
no caixa na mesma transação.

### Etapa 9 — Inteligência e marketing

**Entregas:** campanhas segmentadas com link de WhatsApp, Barberify Inteligência (regras PHP/MySQL),
Performance por profissional, Oportunidades, Recupera (clientes inativos, lista de espera), snapshots
diários de indicadores, relatórios de assinaturas e fidelidade.

**Aceite:** todo indicador vem de consulta real; cada oportunidade explica o que foi identificado, por
que importa e qual ação tomar; valores estimados são rotulados como estimativa; segmento respeita
consentimento e descadastramento.

### Etapa 10 — Finalização

**Entregas:** revisão de segurança completa, LGPD operacional (anonimização, retenção, exportação),
backup e restauração testados, revisão de índices e consultas lentas, teste de carga leve,
responsividade final, testes integrados de concorrência e multiempresa, manuais de instalação e uso,
deploy, conta do proprietário, dados de demonstração removíveis e checklist de go-live.

**Aceite:** checklist de segurança (§3.6) 100% executado; restauração de backup validada em base limpa;
telas principais abaixo de 1,5 s em base com volume realista; manuais revisados por quem não escreveu o
código; nenhuma funcionalidade apenas visual; nenhum erro crítico aberto.

---

## 3. Estratégia de segurança

### 3.1 Mapa OWASP (Top 10) → controle no Barberify

| Risco | Controle |
|---|---|
| A01 Quebra de controle de acesso | `empresa_id` da sessão, middleware de permissão, verificação dupla em operações financeiras, teste de isolamento por módulo |
| A02 Falhas criptográficas | `password_hash`, HTTPS obrigatório, segredos no `.env`, TOTP cifrado, hash de tokens de sessão/recuperação |
| A03 Injeção | 100% PDO preparado, `ATTR_EMULATE_PREPARES=false`, nenhuma interpolação de SQL, escape de saída |
| A04 Design inseguro | fluxos de estado explícitos, idempotência, limites de tentativa, aprovação para acesso de suporte |
| A05 Configuração incorreta | `.htaccess` negando pastas privadas, erros sem stack trace em produção, diretório de upload sem execução, instalador removido |
| A06 Componentes vulneráveis | sem framework de terceiros; Bootstrap e libs locais em versão fixa e revisada; política de atualização documentada |
| A07 Falhas de identificação | sessão regenerada no login, cookies `HttpOnly/Secure/SameSite`, revogação, bloqueio progressivo, 2FA no Control |
| A08 Falhas de integridade | snapshots, ledger imutável, migrations com checksum, auditoria append-only |
| A09 Falhas de log/monitoramento | `logs_aplicacao` + `auditoria` + alertas no Control; nunca registrar senha/token |
| A10 SSRF | nenhuma requisição a URL fornecida pelo usuário; integrações externas com allowlist |

### 3.2 Cabeçalhos e transporte

`Content-Security-Policy` restritiva (self + inline mínimo necessário, sem `unsafe-eval`),
`X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
`X-Frame-Options: DENY` (marketplace pode usar `SAMEORIGIN` apenas em embed autorizado),
`Permissions-Policy` mínima, `Strict-Transport-Security` quando HTTPS estável, `Cache-Control: no-store`
em páginas autenticadas.

### 3.3 Entrada, saída e estado

- Validação declarativa no servidor para todo campo (tipo, tamanho, formato, faixa, existência no tenant).
- Escape contextual na view (`e()` para HTML, `json_encode` para JS, `rawurlencode` para URL).
- CSRF por realm e por sessão em todo método mutante; requisição cross-origin em endpoint de mutação é
  rejeitada mesmo com token.
- Uploads: extensão + MIME real (`finfo`) + `getimagesize` + limite de bytes + re-encode GD + nome
  aleatório + diretório sem execução; arquivos privados servidos por controller autorizado.
- Nunca confiar em `X-Forwarded-For` sem proxy conhecido; IP usado apenas para auditoria/throttle.

### 3.4 Segredos e configuração

- `.env` fora do versionamento, permissão `600`, com `APP_KEY`, credenciais de banco, chave de
  criptografia do TOTP, `OWNER_RECOVERY_KEY` e SMTP opcional.
- `.env.example` sem valores. Nenhum segredo em JS, HTML, log ou mensagem de erro.
- Rotação documentada; trocar `APP_KEY` invalida segredos cifrados e exige reprovisionamento do 2FA.

### 3.5 Multiempresa e privacidade

- Teste automatizado obrigatório: empresa A não lê, altera nem exclui registro da empresa B, inclusive
  por manipulação de ID em URL, corpo JSON, header e cookie.
- Consultas de relatório sempre com `empresa_id`; nunca `GROUP BY` global.
- Exportação de dados restrita ao proprietário da empresa e auditada.
- LGPD: base legal por finalidade, consentimento em `cliente_consentimentos`, direito de
  acesso/correção/eliminação com anonimização preservando obrigações fiscais, retenção definida
  (auditoria 24 meses, logs 90 dias, dados de cliente excluído anonimizados após 90 dias).

### 3.6 Checklist de segurança por etapa (executado antes de fechar cada uma)

1. Toda rota do painel e do Control exige autenticação e permissão verificadas no servidor.
2. Todo `POST/PUT/DELETE` valida CSRF e origem.
3. Toda consulta de tenant tem `empresa_id` vindo da sessão.
4. Nenhuma resposta devolve dados de outro tenant (teste manual com duas empresas).
5. Nenhuma tela exibe hash, token, chave ou dado de cartão.
6. Erros de produção não mostram caminho, SQL nem stack trace.
7. Upload rejeita arquivo com dupla extensão, SVG com script e tamanho acima do limite.
8. Sessão é invalidada em logout, troca de senha e revogação.
9. Tentativas de login e de 2FA são limitadas e auditadas.
10. Ações críticas exigem reautenticação.
11. `auditoria` registra ator, ação, entidade e resultado.
12. Instalador e arquivos de migration não são acessíveis publicamente.

---

## 4. Estratégia de testes

### 4.1 Níveis

| Nível | O que cobre | Ferramenta |
|---|---|---|
| Unitário | regras puras: disponibilidade, limites de fidelidade, comissão, dinheiro/data | PHPUnit (desenvolvimento, nunca enviado ao servidor) |
| Serviço/integração | serviços com banco real de teste, transações, locks, idempotência | PHPUnit + base `barberify_test` |
| HTTP/feature | rotas, permissões, CSRF, isolamento por tenant, estados | PHPUnit (kernel de teste) + cliente HTTP |
| Concorrência | dupla reserva, último crédito, duplo pagamento | PHP com `curl_multi` disparando N requisições simultâneas |
| Segurança | checklist §3.6, acesso cruzado, upload malicioso | testes de autorização + roteiro manual documentado |
| UI/responsividade | navegação real em 360, 768, 1024 e 1440 px | inspeção com navegador, registro em `docs/testes/etapa-N.md` |

Nada é declarado “testado” sem saída registrada (comando, resultado, data). Teste que não pode rodar é
reportado como limitação, nunca como sucesso.

### 4.2 Cobertura mínima obrigatória (seção 31 do Prompt Mestre)

| Grupo | Casos |
|---|---|
| Gerais | login, logout, recuperação de senha, permissões (positivo e negativo), cadastro, edição, exclusão lógica, busca, filtros, paginação, validações, erros, responsividade, isolamento entre empresas |
| Financeiros | pagamento, estorno, cancelamento, duplicidade, fechamento de caixa, comissões, assinaturas, movimentações, histórico imutável |
| Agendamento | reserva temporária, expiração em 15 min, confirmação, WhatsApp (link correto, sem prova de envio), fechar WhatsApp, duplo clique, concorrência, sobreposição, cancelamento, reagendamento |
| Fidelidade | os casos da seção 13.8 + limite diário, plano sem crédito, serviço fora do plano, profissional não autorizado |
| Multiempresa | duas empresas de teste; acesso cruzado por ID em URL, JSON, header e cookie; exportação cruzada |
| Control | login global, separação de contas, proteção de privilégios, criação de administradores, permissões, revogação de sessão, auditoria, gestão de empresas, planos, pagamentos, suspensão/reativação, exclusão do proprietário bloqueada, separação do financeiro global |

### 4.3 Cenários de concorrência (executados de verdade)

1. **Duas reservas no mesmo slot:** 10 requisições simultâneas para o mesmo profissional/horário →
   exatamente 1 `reservado`, 9 respostas `409`, nenhum registro duplicado.
2. **Duplo clique em confirmar:** 2 requisições com o mesmo `reserva_token` → 1 confirmação, 1 resposta
   idempotente, 1 evento de auditoria.
3. **Último crédito de fidelidade:** 2 atendimentos concluídos simultâneos com 1 crédito → 1 consumo,
   1 recusa, saldo nunca negativo.
4. **Fechamento de caixa concorrente:** 2 fechamentos simultâneos → 1 fecha, o outro recebe erro claro.
5. **Pagamento duplicado:** 2 registros do mesmo pagamento → 1 aceito, 1 bloqueado por idempotência.
6. **Estoque negativo:** 2 vendas simultâneas do último item → comportamento conforme política
   configurada, sem saldo inconsistente.

### 4.4 Fidelidade — contrato de teste (seção 13.8)

| Teste | Resultado esperado |
|---|---|
| Limite semanal | segunda utilização na mesma semana é recusada com motivo explícito |
| Mudança de semana | uso no domingo e uso na segunda seguinte são permitidos |
| Saldo insuficiente | recusa sem gravar utilização nem movimento |
| Expiração | créditos do ciclo encerrado não são usáveis no ciclo novo |
| Novo ciclo | renovação cria ciclo com créditos cheios, sem arrastar saldo |
| Dependentes | dependente com limite individual respeita o próprio limite |
| Compartilhamento | saldo coletivo consumido corretamente por titular e dependente |
| Estorno | devolve crédito uma única vez, com `estorno_de_id` único |
| Pagamento pendente | assinatura não libera consumo até confirmação (quando configurado) |
| Concorrência | último crédito não é consumido duas vezes |
| Snapshot | editar o plano depois não altera direitos da assinatura vigente |
| Ciclo atravessando semanas | limite semanal aplicado em cada semana-calendário |
| Unidade não autorizada | recusa mesmo com saldo disponível |

### 4.5 Teste de isolamento entre empresas (obrigatório em cada módulo)

Roteiro fixo com duas empresas (`Empresa Alfa` e `Empresa Beta`) e um usuário de cada:

1. Autenticado na Alfa, trocar IDs de cliente/serviço/agendamento/comanda por IDs da Beta na URL → 403/404.
2. Enviar `empresa_id` no corpo de um `POST` apontando para Beta → ignorado (prevalece a sessão).
3. Forçar cookie de sessão da Beta → sessão inválida para a Alfa.
4. Acessar upload privado da Beta por URL → negado.
5. Tentar exportar relatório da Beta → negado e auditado.
6. Verificar que listagens, contadores e somatórios não incluem linhas da Beta.

### 4.6 Ambiente de teste na InfinityFree

- Subdomínio de homologação separado, banco próprio, dados sintéticos (nunca dados reais de cliente).
- `bin/seed-demo.php` cria duas empresas, profissionais, serviços e agenda — marcado como demonstração e
  removível por comando.
- Limitações declaradas: sem cron real, a expiração é testada alterando `reserva_expira_em` no banco de
  teste com o predicado real; sem e-mail, o envio é testado localmente com SMTP de captura antes de
  qualquer tentativa no provedor.

---

## 5. Riscos técnicos e alternativas

| # | Risco | Prob. | Impacto | Mitigação | Alternativa se falhar |
|---|---|---|---|---|---|
| 1 | Suspensão/bloqueio por limites da InfinityFree (CPU, hits, entry processes) | Média | Alto | cache, paginação, índices, consultas enxutas, sem chamadas externas no caminho crítico | migrar para hospedagem premium (Layout A) sem reescrever código |
| 2 | Ausência de cron | Certa | Médio | expiração por predicado + scheduler preguiçoso idempotente | cron real quando houver plano pago, se permitido pelo provedor |
| 3 | E-mail transacional não confiável | Alta | Médio | fluxo funciona sem e-mail, códigos de recuperação, reset assistido auditado | SMTP transacional (587/TLS) ou API HTTPS de e-mail em plano pago |
| 4 | Dupla reserva em horário | Média | Alto | trava por (profissional, dia) + `FOR UPDATE` + revalidação + teste de concorrência | índice único em `(profissional_id, inicio)` + retry com alternativas |
| 5 | Vazamento entre empresas | Baixa se testado | Crítico | `empresa_id` da sessão, repositórios com contexto obrigatório, testes de isolamento por módulo | revisão de código dedicada + auditoria de consultas sem `empresa_id` |
| 6 | Complexidade de fidelidade gerar bug financeiro | Média | Alto | ledger imutável, idempotência por chave única, 13 testes contratuais, snapshot de regras | congelar novas features de fidelidade até estabilizar o núcleo |
| 7 | Relatórios lentos em base grande | Média | Médio | snapshots diários (`indicadores_snapshots`), índices compostos, cache, exportação em fila | pré-agregação noturna no scheduler |
| 8 | Sem WebSocket / tempo real | Certa | Baixo | polling leve, atualização ao focar a tela, recarregar seção | plano pago com serviço de realtime, sem mudar regras |
| 9 | Marketplace sem tráfego | Alta | Comercial | SEO, sitemap, links individuais por barbearia, ofertas compartilháveis | focar em links de agendamento por empresa enquanto o marketplace ganha densidade |
| 10 | WhatsApp sem confirmação de entrega | Certa | Médio | confirmação interna é a fonte da verdade; interface não promete entrega | API oficial do WhatsApp Business em fase futura, com custo e opt-in |
| 11 | Backup manual falhar | Média | Alto | rotina documentada, checksum, teste de restauração na Etapa 10 | backup externo automatizado em plano pago |
| 12 | Diferenças MySQL 5.7 × MariaDB 10.4 | Média | Médio | evitar features exclusivas, charset comum, JSON só como payload, testar nos dois quando possível | fixar MariaDB 10.4 como referência e validar SQL nele |
| 13 | Deprecações do PHP 8.4 | Média | Baixo | `strict_types`, sem funções depreciadas, testes locais em 8.2 e 8.4 | ajuste pontual por release |
| 14 | Fraude em indicações/avaliações | Média | Médio | recompensa só após atendimento concluído, telefone único, moderação, limite por cliente | revisão manual pelo proprietário da empresa |
| 15 | Dependência de uma única pessoa (bus factor) | Média | Alto | documentação de decisões, migrations versionadas, manuais, seeds reproduzíveis | onboarding documentado + revisão externa |
| 16 | Picos de uso no lançamento comercial | Baixa | Alto | limites por plano, cache, fila de e-mail, monitoramento | plano pago com mais recursos e CDN para assets |
