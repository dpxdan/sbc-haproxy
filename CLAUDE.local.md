# Análise Completa do Repositório dpxdan/sbc (FluxSBC v6.4)

## Visão Geral

**FluxSBC** (Flux Session Border Controller) v6.4 -- plataforma de billing e gerenciamento VoIP/telecom construída sobre **CodeIgniter 2.1.0** com extensão **HMVC** (Wiredesignz MX), banco **MySQL**, integração nativa com **FreeSWITCH** e licença **AGPL-3**.

---

## 1. Framework e Stack Técnico

| Camada | Tecnologia |
|---|---|
| **Framework PHP** | CodeIgniter 2.1.0 + HMVC (Wiredesignz Modular Extensions) |
| **PHP** | 7.3 (via FPM) |
| **Banco de dados** | MySQL (driver MySQLi, Active Record) |
| **Web server** | Nginx (HTTPS 443) ou Apache (porta 8081) |
| **Motor VoIP** | FreeSWITCH (com Lua dialplan scripts) |
| **Frontend** | Bootstrap 3/4/5, jQuery 1.12.4, Flexigrid, Select2, Chart.js |
| **Rich text** | TinyMCE, CKEditor |
| **PDF** | HTML2PDF (wrapping TCPDF 5.0.002) |
| **SMS** | Twilio |
| **Email marketing** | MailChimp API v3 |
| **Pagamento** | PayPal (sandbox + produção) |
| **i18n** | GNU gettext (.po/.mo) |
| **Gerenciamento de pacotes** | Nenhum (sem Composer, sem npm). Dependências vendored. |
| **Testes** | Nenhum (sem PHPUnit, sem suítes de teste) |
| **CI/CD** | Nenhum (sem Docker, sem pipelines) |
| **OS alvo** | Debian Linux |

**Entry point**: `web_interface/flux/index.php` -- define constantes, verifica prefixos `/api` e `/admin`, carrega `system/core/CodeIgniter.php`.

**Configuração externa**: `/var/lib/flux/flux-config.conf` (INI com dbhost, dbname, dbuser, dbpass, PRIVATE_KEY, ENCRYPTION_KEY, base_url).

---

## 2. Estrutura de Diretórios

```
sbc/
├── config/              -- Configs de sistema (DB, fail2ban, logrotate, MySQL, sudoers)
├── database/            -- Schema SQL e migrações
│   ├── flux-6.4.sql         -- Schema completo (~345 KB, 66+ tabelas)
│   ├── flux-tables.sql      -- Tabelas FreeSWITCH (37 tabelas)
│   ├── flux-views.sql       -- 15+ MySQL views
│   └── updates/             -- 44 migrações incrementais (Out/2024 - Jul/2026)
├── freeswitch/          -- Configuração e scripts FreeSWITCH
│   ├── conf/                -- XML config do FreeSWITCH
│   ├── fs/lib/              -- Bibliotecas PHP server-side (CDR, XML, EventSocket)
│   ├── scripts/flux/        -- Lua dialplan (routing, billing, PBX)
│   ├── mod/                 -- Módulos customizados (mod_nibblebill.c)
│   └── sounds/              -- Áudios PT-BR (IVR, saldo, bloqueio)
├── misc/                -- Scripts de manutenção, drivers ODBC
├── web_interface/
│   ├── apache/              -- VHost Apache (porta 8081)
│   ├── nginx/               -- VHost Nginx (HTTPS 443)
│   └── flux/                -- Aplicação CodeIgniter (document root)
│       ├── index.php            -- Front controller
│       ├── application/
│       │   ├── config/          -- CI configs (database.php, routes.php, autoload.php, hooks.php)
│       │   ├── controllers/     -- Controllers top-level + admin/ + api/ + common/
│       │   ├── models/          -- Models globais (db_model, common_model, auth_model, etc.)
│       │   ├── modules/         -- **46 módulos HMVC** (o core da aplicação)
│       │   ├── views/           -- Views globais (master, header, footer, left_panel)
│       │   ├── libraries/       -- Bibliotecas customizadas (flux/, API, SMS, PDF)
│       │   ├── helpers/         -- Helpers (form, CSV, email)
│       │   ├── hooks/           -- Pre-system/pre-controller hooks
│       │   ├── core/            -- MY_Router.php, MY_Loader.php (wiring HMVC)
│       │   ├── third_party/MX/  -- Wiredesignz HMVC
│       │   └── language/        -- i18n (en_En, pt_BR)
│       ├── assets/              -- CSS, JS, imagens, fontes
│       ├── addons/plugins/      -- 14 plugins addon
│       ├── cron/                -- Scripts de cron
│       └── upload/, attachments/, database_backup/, playlist/
├── flux_install.sh      -- Instalador (~55 KB)
├── migrations.sh        -- Runner de migrações SQL
└── rc.firewall          -- Regras iptables
```

---

## 3. Padrão Arquitetural: HMVC (Hierarchical MVC)

Cada módulo segue a estrutura:
```
modules/<nome>/
├── controllers/<nome>.php
├── models/<nome>_model.php
├── libraries/<nome>_form.php    -- Form builder dinâmico
├── views/view_*.php
├── tooltip.php                  -- Tooltips em inglês
└── tooltip_pt_BR.php            -- Tooltips em português
```

### 46 Módulos HMVC

**Core VoIP/Telecom:**
`accounts`, `did`, `trunk`, `freeswitch`, `fsmonitor`, `ipmap`, `animap`, `accessnumber`, `calltype`, `siprouting`, `ringgroup`, `local_number`

**Billing/Financeiro:**
`rates`, `custom_rates`, `ratedeck`, `pricing`, `products`, `orders`, `invoices`, `taxes`, `refill_coupon`

**Relatórios:**
`reports`, `activity_report`, `summary`, `dashboard`, `login_activity`, `automated_report`

**Sistema/Configuração:**
`systems`, `permissions`, `localization`, `cronsettings`, `email`, `department`, `addons`, `pages`, `audit`, `event_guard`

**Usuário:**
`login`, `signup`, `user`, `low_balance`, `supportticket`, `voice_broadcast`

**Importação:**
`account_import`, `flux_import`

---

## 4. Metodologia e Padrões de Código

### Formulários e Grids
- Formulários construídos dinamicamente via classes `*_form.php` (ex: `accounts_form.php`)
- Grids de dados usam `flexigrid.js` com JSON servido por métodos `*_list_json()` nos controllers
- Biblioteca central `flux/form.php` fornece `build_serach_form()`, `build_grid()`, `build_batchupdate_form()`

### Acesso a Dados
- **Sem ORM** -- usa Active Record do CI via `db_model.php` centralizado (60 KB)
- `db_model.php` fornece CRUD genérico: `save()`, `update()`, `getSelect()`, `countQuery()`, `select()`, `update_balance()`
- Queries SQL raw usadas diretamente em controllers quando necessário

### Autenticação
- Login via `Auth_model::verify_login()` -- verifica tabela `accounts` (campo `number` ou `email`)
- Senhas criptografadas com Blowfish (BF-ECB) via `openssl_encrypt()` (chave simétrica, não hash)
- Sessões no banco (`ci_sessions`), token AES-256-CBC
- "Login as" -- admin pode personificar resellers/customers

### Autorização (2 níveis)
1. **Acesso a módulo** via `userlevels` -- `module_permissions` com IDs de `menu_modules`
2. **Permissões granulares** via `permissions` -- JSON mapeando `módulo > sub_módulo > ação` (search, create, delete, batch_update)

### Tipos de Usuário
| Tipo | Código |
|---|---|
| Super Admin | -1 |
| Customer | 0 |
| Reseller | 1 |
| Admin | 2 |
| Sub-customer | 3 |
| Sub-admin | 4 |

### Migrações de Banco
- **Custom**: script bash `migrations.sh` que aplica arquivos SQL de `database/updates/` em ordem
- Tabela `sql_migration_history` para tracking
- Migração nativa do CI **desabilitada**

### Hooks do CI
- `pre_system`: Handler de erros fatais PHP + roteamento baseado em domínio (multi-tenant)
- `pre_controller`: Override de `base_url` com domínio detectado
- `post_system`: User tracking
- Hooks de addons carregados dinamicamente

### Routing
- Controller padrão: `login`
- HMVC auto-routing (ex: `/accounts/...` → `modules/accounts/controllers/accounts.php`)
- Rotas explícitas para signup, reset de senha, API, CDR, status
- Inclui rotas de addons dinamicamente

### Internacionalização
- GNU gettext (`gettext()`) em todas as views e controllers
- **pt_BR**: 8.755 linhas (primário)
- **en_En**: 7.002 linhas (completo)
- **es_ES, fr_FR**: traduções parciais por módulo
- **ru_RU**: apenas CSV para módulos específicos

---

## 5. Integrações e Domínio Telecom

### FreeSWITCH
- Dialplan dinâmico via Lua scripts
- CDR processing via `mod_json_cdr` (PHP)
- Billing em tempo real via `mod_nibblebill` (customizado em C)
- Event Socket para comandos em tempo real
- XML Curl para configuração dinâmica (ACLs, diretório, dialplan)
- PBX features: IVR, filas, ring groups, conferência, blacklist, voicemail, time-based routing

### Billing
- Rating engine (origination + termination rates, rate groups, ratedecks)
- Invoicing automático (cron-driven, suporta postpaid com sweep diário/mensal)
- PayPal (sandbox + produção)
- Cupons de recarga (prepaid)
- Gerenciamento de balance (prepaid: dedução; postpaid: limite de crédito)
- Comissões para resellers/distribuidores
- Taxas por conta

### API REST
- Base abstrata `API_Controller.php` (GET/POST/PUT/DELETE)
- Endpoints: login, CDRs, DIDs, IP maps, SIP devices, signup, notificações
- Sync bidirecional com sistemas VoIP externos (`ApiSync.php`)

### Segurança
- Event Guard: detecção de fraude SIP com integração Fail2Ban
- Firewall iptables (`rc.firewall`)
- XSS filtering global (CSRF desabilitado)

### 14 Plugins Addon
`account_range`, `api`, `automated_reports`, `demo`, `event_guard`, `fm_addon`, `internationalcredits`, `language_portuguese`, `local_number`, `personalized_rates`, `ringgroup`, `siprouting`, `supportticket`, `voice_broadcast`

---

## 6. Cron Jobs

| Controller | Função |
|---|---|
| `generateInvoice.php` | Geração de invoices postpaid |
| `ProcessInvoice.php` | Renovação de produtos/assinaturas |
| `updateBalance.php` | Cobranças de assinatura/DID |
| `PaymentStatus.php` | Limpeza de ordens expiradas |
| `currencyupdate.php` | Atualização de câmbio |
| `lowbalance.php` | Alertas de saldo baixo |
| `purge.php` | Purga de dados |
| `automatedReport.php` | Relatórios agendados |
| `broadcastemail.php` | Email broadcasting |
| `departmentmail.php` | Polling de email (tickets) |

---

## Resumo

FluxSBC é uma plataforma telecom completa e madura com:
- **Stack legado** (CI 2, jQuery 1.12, PHP 7.3, sem gerenciador de pacotes)
- **Sem testes automatizados** e **sem CI/CD**
- **Arquitetura modular** via HMVC com 46 módulos bem organizados
- **Domínio complexo** cobrindo routing VoIP, billing, invoicing, multi-tenant/reseller
- **Foco Brasil** (pt_BR primário, ANATEL/CADUP, timezone São Paulo)
- **Totalmente self-contained** -- todas as dependências vendored
