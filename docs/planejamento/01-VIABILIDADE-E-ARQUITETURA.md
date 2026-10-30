# 01 — Viabilidade na InfinityFree, Arquitetura Técnica e Estrutura de Diretórios

Voltar ao [índice](00-INDICE.md)

---

## 1. Análise de viabilidade na InfinityFree

### 1.1 O que a hospedagem oferece hoje (verificado, não presumido)

| Recurso | Situação | Fonte |
|---|---|---|
| PHP | **8.4** no plano gratuito (antes 8.3 e 8.2); sem seleção de versão no gratuito | Anúncio "Free hosting is now upgraded to PHP 8.4" (set/2026) e "PHP 8.2 and MariaDB 10.4 upgrades!" (nov/2023) no fórum oficial |
| Banco | MariaDB 10.4; contas antigas em MySQL 5.7 | Mesmo anúncio de nov/2023 + "MySQL 5.7 is now available" |
| Cron Jobs | **Desativados desde 09/08/2023**; requisições periódicas automatizadas são proibidas no gratuito | Tópico oficial "Cron Job Feature is now disabled" |
| SMTP | Porta 25 bloqueada; `mail()` restrito; SMTP externo na **587 com TLS** é o caminho suportado | Tópicos oficiais "Can I send Emails" e "SMTP" |
| Shell / SSH | Não disponível | Limites do plano gratuito |
| MySQL remoto | Não disponível no gratuito | Comparativo de planos |
| Raiz web | Fixa em `htdocs` | Painel do provedor |
| Limites de uso | ~50.000 hits/dia, 5 GB de disco, além de limites de inodes, I/O, *entry processes* e CPU (suspensão automática em picos) | Base de conhecimento + fóruns oficiais |
| Processos contínuos / WebSocket / Node / Docker | Não suportados | Limites do plano |

### 1.2 Consequências diretas para a arquitetura

| Necessidade do Barberify | Restrição real | Solução adotada |
|---|---|---|
| Expirar reserva de 15 min e liberar horário | Sem cron | O horário ocupado é decidido por **predicado SQL** (`status='reservado' AND reserva_expira_em > NOW()`). A reserva expira sozinha na leitura, independentemente de qualquer tarefa. Um *reaper* apenas arruma o status. |
| Marcar inadimplência, gerar faturas SaaS, fechar ciclos de fidelidade | Sem cron | **Scheduler preguiçoso** com lock em tabela, disparado no fim de requisições autenticadas, com intervalo mínimo e tempo máximo de execução. Idempotente. |
| Enviar e-mail (recuperação de senha, avisos) | SMTP só na 587/TLS e com reputação frágil | Fila `email_fila` enviada dentro de requisições; se o envio falhar, o sistema **não depende dele**: recuperação de senha tem códigos de recuperação, e avisos críticos aparecem no painel. |
| Notificar cliente sobre agendamento | Sem automação de WhatsApp na hospedagem | Confirmação interna no Barberify + link `wa.me` montado no servidor. Sem promessa de entrega/leitura. |
| Migrations e instalação | Sem shell | Instalador web com token de uso único, migrations versionadas em `database/migrations`, e remoção do instalador após uso. |
| Backup | Sem `mysqldump` | Exportador SQL em PHP (`bin/backup.php`), somente leitura, com download autenticado e retenção manual documentada. |
| Uploads de logo/fotos | Disco compartilhado, sem antivírus | Validação por `finfo` + `getimagesize`, re-encode via GD com perda de metadados, nome aleatório, limite de tamanho e diretório separado. |
| Desempenho | Limite de hits e CPU; latência variável | Cache de arquivo para catálogo/marketplace, paginação obrigatória, índices compostos, consultas com colunas explícitas, assets locais e estáveis, sem chamadas externas no caminho crítico. |
| Recuperação de senha sem e-mail | SMTP frágil | Códigos de recuperação gerados no primeiro acesso + reset assistido pelo proprietário da empresa (auditado) + reset pelo Control com justificativa. |

### 1.3 Veredito de viabilidade

**Viável para operação inicial e para demonstração comercial**, desde que respeitados três pontos:

1. **O sistema não pode depender de tempo real.** Tudo o que é "periódico" precisa ser idempotente e
   reexecutável sob demanda. O desenho acima atende.
2. **E-mail transacional não é confiável no plano gratuito.** Cadastro, login e operação funcionam
   sem e-mail; apenas conveniências (recuperação automática, avisos) degradam.
3. **O provedor pode suspender a conta por uso intenso.** Isso é risco operacional, não arquitetural:
   mitigado com cache, paginação e limites, e resolvido definitivamente pela migração (§1.5).

**Não é viável** na InfinityFree gratuita: envio de e-mail em massa, processamento em background real,
relatórios pesados sem cache, armazenamento de muitos arquivos (limite de inodes) e SLA comercial.

### 1.4 Ajustes obrigatórios por causa de MariaDB 10.4 / MySQL 5.7

- Sem `CHECK` efetivo (MySQL 5.7 ignora) → validação de domínio no PHP + `VARCHAR` com constantes.
- Sem `JSON` em MySQL 5.7 com funções de caminho completas → campos JSON são usados apenas como
  *payload* de leitura integral (snapshots, contexto de auditoria), nunca como fonte de consulta
  relacional. Dados consultáveis ficam em colunas próprias.
- Sem CTE/window functions garantidas → relatórios usam `JOIN`/subquery/agregação e, quando preciso,
  agregação em PHP sobre conjunto paginado.
- `utf8mb4_unicode_ci` (não `utf8mb4_0900_ai_ci`, que é MySQL 8).
- Sem `ALTER TABLE ... ALGORITHM=INSTANT` confiável → migrations pequenas, uma por arquivo.
- `ONLY_FULL_GROUP_BY` tratado explicitamente: toda agregação lista colunas não agregadas.

### 1.5 Caminho de saída da hospedagem gratuita

A arquitetura é **portável por desenho**: só o *document root* muda.

- **Layout A (premium/VPS):** `public/` é a raiz do domínio; `app/`, `config/`, `database/`,
  `resources/`, `storage/` ficam fora da árvore pública.
- **Layout B (InfinityFree):** tudo em `htdocs/`, com `.htaccess` negando acesso às pastas privadas e
  guarda PHP (`defined('BARBERIFY')`) em cada arquivo, como defesa em profundidade.

Migrar de B para A é mover arquivos e apontar o domínio — sem reescrever código, porque os caminhos
são resolvidos por `config/paths.php` e nunca por caminho relativo fixo.

### 1.6 Pontos que só podem ser confirmados no ambiente real

Não presumo estes itens; ficam como verificação da Etapa 3, registrada em `docs/instalacao.md`:

- Tempo máximo de execução de PHP e limites exatos de memória/CPU da conta.
- Disponibilidade de `curl` para HTTPS de saída (usado apenas por integrações opcionais).
- Extensões disponíveis: `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `openssl`, `intl`.
- Injeção de script de proteção/anti-bot pelo provedor e seu efeito em requisições `POST`/AJAX.
- Comportamento real do SMTP 587 com o provedor escolhido.

---

## 2. Arquitetura técnica definitiva

### 2.1 Estilo arquitetural

**Monólito modular** com front controller único, em três áreas públicas e uma camada de serviços de
domínio. Sem framework pesado, sem ORM, sem build de front-end, sem processo contínuo — o que a
hospedagem permite e o que mantém o produto auditável.

```
Requisição HTTP
   │
   ├─ public/index.php  (front controller: bootstrap, sessão, roteador)
   │     │
   │     ├─ Middleware (ordem fixa): erro → segurança/cabeçalhos → CSRF → realm/auth → empresa ativa
   │     │                          → permissão → throttling
   │     │
   │     ├─ Controller  (HTTP: valida entrada, chama serviço, devolve view/JSON)
   │     │     │
   │     │     ├─ Domain Service  (regra de negócio, transação, idempotência, auditoria)
   │     │     │     │
   │     │     │     └─ Repository (SQL explícito, PDO, sempre com empresa_id)
   │     │     │
   │     │     └─ View (template PHP + componentes + escape)
   │     │
   │     └─ Scheduler preguiçoso (pós-resposta, com lock e orçamento de tempo)
   │
   └─ Resposta
```

Regras de camada:

| Camada | Pode | Não pode |
|---|---|---|
| Controller | Ler request, validar formato, chamar serviço, escolher view/redirect/JSON | Conter SQL ou regra de negócio |
| Domain Service | Regra, transação, lock, idempotência, auditoria, chamar repositórios | Emitir HTML, ler `$_POST` |
| Repository | SQL parametrizado do seu agregado | Decidir regra de negócio, abrir transação própria |
| View | Escapar e renderizar | Consultar banco |

### 2.2 Componentes de infraestrutura (`app/Core`)

| Componente | Responsabilidade |
|---|---|
| `App` | Bootstrap, container simples, resolução de serviços, tratamento global de erro |
| `Config` | Leitura de `config/*.php` + `.env` (nunca versionar `.env`) |
| `Router` | Rotas por método+padrão, grupos com prefixo e middleware, URL assinada por nome |
| `Request` | Acesso tipado a query/body/arquivos, IP real, detecção de same-origin |
| `Response` | HTML, JSON, redirect, download, status, cabeçalhos de segurança |
| `View` | Layout + seções + parciais + helpers `e()`, `money()`, `datebr()`, `csrf()` |
| `Db` | Conexão PDO única, `transaction(callable)`, `lockRow()`, `insertId()`, log de query lenta |
| `Migrator` | Aplicação ordenada de migrations, tabela `migracoes`, checksum do arquivo |
| `SessionStore` | Sessão por token opaco em cookie + hash no banco, três realms independentes |
| `Auth` | `AuthEmpresa`, `AuthControl`, `AuthCliente` — guardas separados, sem herança entre eles |
| `Permissions` | Carrega permissões efetivas, `can('agenda.criar')`, bypass do proprietário |
| `Csrf` | Token por realm e por sessão, validação em todo `POST`/`PUT`/`DELETE` |
| `Validator` | Regras declarativas, mensagens em pt-BR, validação no servidor sempre |
| `Uploader` | Validação MIME/extensão/tamanho, re-encode de imagem, nome aleatório, registro em `arquivos` |
| `Audit` | Gravação append-only em `auditoria` com ator, contexto e resultado |
| `Idempotency` | Chave única por operação sensível, resposta reproduzida em repetição |
| `Throttle` | Limite por IP/e-mail/rota em `tentativas_login` e afins |
| `Scheduler` | Tarefas registradas, lock, intervalo mínimo, orçamento de tempo, execução idempotente |
| `Logger` | Arquivo diário em `storage/logs` + `logs_aplicacao` para eventos críticos |
| `Cache` | Arquivo com versão por empresa, TTL curto, invalidação em escrita |
| `Money` | Conversão para centavos (int) em cálculos, formatação `R$` e arredondamento bancário |
| `Clock` | Fuso `America/Sao_Paulo` fixo, `now()`, `today()`, semana segunda→domingo |

### 2.3 Padrões obrigatórios de implementação

- **PHP 8.2+** com tipos declarados, `declare(strict_types=1)`, `readonly` para DTOs, enums nativos
  para status onde o valor não vai para o banco como string livre.
- **PDO com prepared statements** em 100% das consultas; `ATTR_EMULATE_PREPARES=false`.
- **Transação explícita** em toda operação que grava mais de uma tabela (agendamento, venda, caixa,
  fidelidade, cobrança).
- **Snapshot** na gravação: nome/preço/duração/comissão/regras contratadas são copiados para a linha
  histórica no momento do fato. Mudar o cadastro depois não altera passado.
- **Nenhum `UPDATE` em tabela de ledger.** Correção é lançamento de estorno com `estorno_de_id`
  e chave única que impede estorno duplicado.
- **`empresa_id` obrigatório** em tabela de tenant; repositório recebe `TenantContext` no construtor
  e falha (exceção) se ele não existir.
- **Nomenclatura:** domínio em português (`agendamentos`, `comissao_fechamentos`), infraestrutura em
  inglês (`Router`, `Request`). Constantes de status em classes `Status*` do domínio.
- **Comentários de decisão:** toda regra não óbvia tem comentário com o "porquê" e a referência à
  seção do Prompt Mestre.

### 2.4 Áreas e roteamento

| Área | Prefixo | Realm | Observação |
|---|---|---|---|
| Marketplace / site público | `/` | anônimo (+ `bf_cli` na área do cliente) | cacheável, indexável |
| Agendamento público | `/agendar` | anônimo | hold de 15 min, confirmação por token |
| Área do cliente | `/minha-conta` | `bf_cli` | global, vinculada a `clientes` por empresa |
| Painel da barbearia | `/painel` | `bf_emp` | multiempresa, permissões granulares |
| Barberify Control | `/control` | `bf_ctl` | identidade global, isolado |
| Endpoints internos (AJAX) | `/api` | conforme a área | JSON, CSRF, mesmos guards |
| Instalação | `/instalar` | token de uso único | removido após instalar |
| Healthcheck | `/status` | anônimo | retorna versão e estado do banco, sem dados |

URLs amigáveis: `/barbearia/{slug}`, `/barbearia/{slug}/profissional/{slug}`, `/barbearia/{slug}/servico/{slug}`,
`/agendar/{slug}`, `/agendamento/{codigo}`, `/painel/agenda`, `/control/empresas`.

### 2.5 Ciclo de vida de uma requisição autenticada (painel)

1. `.htaccess` reescreve para `index.php`; arquivo existente em `public/` é servido direto.
2. Bootstrap: constantes, autoload PSR-4, config, fuso, handler de erro, `Db`.
3. Middleware de segurança: cabeçalhos, HTTPS forçado, `SameSite`, bloqueio de método inválido.
4. Resolução do realm pela rota → carrega sessão do cookie correspondente.
5. `AuthEmpresa`: valida token ativo e não expirado, atualiza `ultimo_acesso`, renova em janela
   deslizante, regenera token em elevação de privilégio.
6. `TenantContext`: `empresa_id` **da sessão** (nunca do request), `unidade_ativa` validada contra as
   unidades do usuário.
7. `EmpresaAtiva`: se assinatura SaaS suspensa/cancelada, permite apenas login, cobrança e saída;
   bloqueia o resto com mensagem clara (dados preservados).
8. `Permissão`: `can('modulo.acao')`; sem permissão → 403 auditado (não 404 silencioso).
9. Controller → Service → Repository (PDO) com `empresa_id` em todas as cláusulas.
10. Pós-resposta: `Scheduler::tick()` com lock `GET_LOCK`/linha de tabela, orçamento de ~2 s.

### 2.6 Concorrência e integridade (ferramentas do projeto)

- `Db::transaction()` com rollback em exceção e `PDO::ATTR_ERRMODE = EXCEPTION`.
- `Db::lockRow('tabela', id)` → `SELECT ... FOR UPDATE` para linhas de saldo/ciclo/caixa.
- Trava por recurso (`agenda_bloqueios_concorrencia` com PK `(profissional_id, data)`) para
  serializar a criação de agendamento do mesmo profissional no mesmo dia.
- Chaves únicas como contrato de idempotência (`requisicao_id`, `agendamento_id` em utilização,
  `fatura_id` em pagamento, `chave` em idempotência).
- Timeout e limite de retry em jobs do scheduler.

### 2.7 Identidade visual (proposta a validar na Etapa 3)

- Marca: **Barberify** — wordmark geométrico, "B" com corte diagonal referenciando navalha.
- Paleta: grafite `#12141A`, carvão `#1D212B`, dourado `#C9A227`, areia `#F3EFE7`, verde-status
  `#2E7D5B`, vermelho-status `#B3392E`. Contraste AA obrigatório.
- Tipografia: sistema para UI (`-apple-system, Segoe UI, Roboto`) + display serifada discreta nos
  títulos do marketplace; sem CDN de fontes.
- Componentes: sidebar recolhível, topbar com empresa/unidade/usuário, cards de KPI, tabela com
  filtros e paginação, badges de status, modais de confirmação, toasts, estados vazios ilustrados.
- Assinatura institucional: "Barberify — um produto Soluções AL" no rodapé do marketplace e do Control.

---

## 3. Estrutura completa de diretórios

### 3.1 Árvore (Layout A — referência canônica)

```
barberify/
├── public/                         # única raiz web no Layout A
│   ├── index.php                   # front controller
│   ├── .htaccess                   # rewrite, segurança, cache de assets
│   ├── robots.txt
│   ├── sitemap.php                 # sitemap dinâmico do marketplace
│   ├── favicon.ico
│   ├── assets/
│   │   ├── css/{app.css,marketplace.css,control.css,tokens.css}
│   │   ├── js/{app.js,agenda.js,marketplace.js,control.js,forms.js}
│   │   ├── vendor/bootstrap/{bootstrap.min.css,bootstrap.bundle.min.js}
│   │   ├── img/{logo.svg,logo-dark.svg,marca/,estados-vazios/}
│   │   └── icons/sprite.svg
│   └── uploads/                    # imagens públicas validadas (logo, capa, fotos)
│       └── .htaccess               # proíbe execução de script
├── app/
│   ├── Core/                       # App, Config, Router, Request, Response, View, Db, Migrator,
│   │                               # SessionStore, Csrf, Validator, Uploader, Audit, Idempotency,
│   │                               # Throttle, Scheduler, Logger, Cache, Money, Clock, Paginator
│   ├── Auth/                       # AuthEmpresa, AuthControl, AuthCliente, Password, RecoveryCodes
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Marketplace/        # Home, Busca, Barbearia, Profissional, Agendamento, Avaliacao
│   │   │   ├── Cliente/            # Login, Painel, Agendamentos, Assinaturas
│   │   │   ├── Painel/             # Dashboard, Agenda, Clientes, Servicos, Profissionais, Unidades,
│   │   │   │                       # Comandas, Caixa, Financeiro, Comissoes, Estoque, Produtos,
│   │   │   │                       # Fidelidade, Planos, Assinaturas, Marketing, Relatorios,
│   │   │   │                       # Inteligencia, Oportunidades, Configuracoes, Usuarios
│   │   │   ├── Control/            # Dashboard, Empresas, PlanosSaas, AssinaturasSaas, Pagamentos,
│   │   │   │                       # FinanceiroGlobal, Equipe, Papeis, Suporte, Comunicados,
│   │   │   │                       # Monitoramento, Auditoria, Configuracoes, Proprietario
│   │   │   └── Api/                # Disponibilidade, Busca, Notificacoes, Uploads
│   │   └── Middleware/             # ErrorHandler, SecurityHeaders, ForceHttps, VerifyCsrf,
│   │                               # ResolveRealm, RequireEmpresa, EmpresaAtiva, RequirePermission,
│   │                               # ThrottleRequests, RequireControlOwner
│   ├── Domain/
│   │   ├── Control/                # EmpresaService, SaasPlanoService, SaasAssinaturaService,
│   │   │                           # SaasCobrancaService, ControlEquipeService, SuporteService,
│   │   │                           # ComunicadoService, PropriedadeGlobalService
│   │   ├── Empresa/                # UnidadeService, UsuarioEmpresaService, PapelService,
│   │   │                           # ConfiguracaoEmpresaService
│   │   ├── Catalogo/               # ServicoService, CategoriaService, PrecoService
│   │   ├── Agenda/                 # DisponibilidadeService, AgendamentoService, ReservaService,
│   │   │                           # ConfirmacaoService, ListaEsperaService, BloqueioService
│   │   ├── Atendimento/            # ComandaService, AtendimentoService, ConsumoService
│   │   ├── Clientes/               # ClienteService, CrmService, ContaClienteService
│   │   ├── Fidelidade/             # PlanoService, AssinaturaConsumidorService, CicloService,
│   │   │                           # UtilizacaoService, LedgerFidelidade, ProgramaPontosService
│   │   ├── Financeiro/             # CaixaService, LancamentoService, PagamentoService,
│   │   │                           # ComissaoService, FechamentoService
│   │   ├── Estoque/                # ProdutoService, EstoqueService, CompraService, InventarioService
│   │   ├── Marketplace/            # PerfilPublicoService, BuscaService, AvaliacaoService,
│   │   │                           # RankingService, EventoService
│   │   ├── Marketing/              # CampanhaService, VitrineService, IndicacaoService, SegmentoService
│   │   ├── Inteligencia/           # OportunidadeService, IndicadorService, PerformanceService
│   │   ├── Relatorios/             # RelatorioService, ExportadorCsv
│   │   └── Sistema/                # TarefasService, NotificacaoService, EmailService, BackupService
│   ├── Repositories/               # um por agregado; SQL explícito; sempre com empresa_id
│   ├── Support/                    # helpers, Collections leves, Texto, Telefone, CpfCnpj, Slug, Csv
│   └── routes/{web.php,painel.php,control.php,api.php,cliente.php}
├── resources/views/
│   ├── layouts/{app.php,marketplace.php,control.php,cliente.php,auth.php}
│   ├── components/{sidebar.php,topbar.php,tabela.php,paginacao.php,modal.php,alerta.php,
│   │                badge.php,kpi.php,estado-vazio.php,grafico.php,form-*.php}
│   ├── marketplace/  painel/  control/  cliente/  auth/  erros/{403,404,419,500,manutencao}.php
│   └── emails/                     # templates de e-mail (quando SMTP disponível)
├── database/
│   ├── migrations/                 # 001_..., 002_... (SQL versionado + checksum)
│   ├── seeds/                      # permissoes, papéis padrão, configurações globais, dados de demo
│   └── schema/                     # diagramas e dumps de referência (não executados em produção)
├── storage/                        # fora da árvore pública (Layout A)
│   ├── logs/                       # app-AAAA-MM-DD.log, erro, auditoria crítica
│   ├── cache/
│   ├── private/                    # comprovantes, exports, backups, anexos de suporte
│   └── tmp/
├── config/{app.php,database.php,security.php,permissoes.php,marketplace.php,fidelidade.php}
├── bin/{instalar.php,migrate.php,seed.php,criar-proprietario.php,backup.php,tarefas.php}
├── docs/{planejamento/,instalacao.md,manual-uso.md,decisoes-tecnicas.md}
├── .env.example                    # sem valores reais; .env nunca versionado
├── .htaccess                       # Layout B: protege pastas privadas na raiz de htdocs
├── composer.json                   # apenas autoload PSR-4 (sem dependências obrigatórias)
└── README.md
```

### 3.2 Layout B — InfinityFree (`htdocs`)

```
htdocs/                     # raiz web fixa do provedor
├── index.php  .htaccess  assets/  uploads/     # = conteúdo de public/
├── app/  resources/  database/  config/  storage/  bin/  docs/
└── .htaccess               # nega acesso a app/, config/, database/, storage/, bin/, resources/, docs/
```

Com `.htaccess` negando listagem e acesso direto e, em cada arquivo PHP privado, a guarda:

```php
defined('BARBERIFY') || exit;   // defesa em profundidade, além do .htaccess
```

Nenhum arquivo privado contém segredo em URL ou credencial em texto puro; `config/*.php` lê do `.env`
(gravado no instalador, fora do controle de versão, com permissão restrita).

### 3.3 Arquivos públicos que existem de propósito

| Arquivo | Por quê |
|---|---|
| `public/index.php` | único ponto de entrada; nada de lógica espalhada |
| `public/sitemap.php` | sitemap dinâmico do marketplace (SEO), cacheado |
| `public/status` (rota) | verificação de saúde sem expor dados |
| `public/uploads/.htaccess` | impede execução de PHP em diretório de upload |
| `.htaccess` raiz (Layout B) | bloqueia as pastas privadas quando não há docroot configurável |

### 3.4 O que **não** existirá na árvore

- `node_modules`, build de front-end, bundler: Bootstrap e JS são servidos como arquivos estáticos.
- Pasta `vendor` com framework: o autoload é próprio (Composer apenas para PSR-4, opcional).
- Qualquer pasta ou arquivo do Soluções AL Hub.
- Qualquer credencial, chave ou token em arquivo versionado.
