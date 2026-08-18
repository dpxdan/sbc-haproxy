# Análise de Vulnerabilidades — FluxSBC v6.4

## Contexto

Auditoria de segurança do repositório FluxSBC (CodeIgniter 2.1.0 + HMVC, PHP 7.3, MySQL, integração FreeSWITCH), solicitada pelo usuário para mapear vulnerabilidades com foco no stack legado e nas funcionalidades de billing/telecom/multi-tenant.

A análise foi feita por leitura de código-fonte (não há execução/DAST). Todos os achados abaixo foram **confirmados lendo o código**. O objetivo deste documento é servir de mapa priorizado de correção; **nenhuma alteração foi feita** — o repositório continua intacto.

Escopo coberto: `web_interface/flux/application/` (controllers, models, modules, libraries, views, config, hooks), `web_interface/flux/system/` (framework CI embarcado), `config/`, `freeswitch/`, `flux_install.sh`, vhosts Nginx/Apache.

> **Aviso de gravidade:** o repositório é público (AGPL-3) e distribui chaves de criptografia fixas. Trate **todas** as chaves em `config/flux-config.conf` e `config/api.php` como **comprometidas** em qualquer deployment que as tenha herdado.

---

## Sumário executivo — cadeias de ataque

O caminho mais curto para comprometimento total, **sem nenhuma credencial**:

1. `GET /relogin/1/0/` → sessão de Super Admin direto pela URL (F-01).

Alternativas, também **sem credencial**:
2. `POST /api/login` com `username=' OR 1=1 -- ` → token de API válido (F-02).
3. Forjar token de API offline usando as chaves default versionadas em `config/api.php` (F-03 + F-05).
4. `POST /signup/check_otp` com `account_id=1&otp_number=` (comparação frouxa `NULL == ""`) → reset da senha do admin (F-04).

Com **qualquer** conta de Customer autenticada:
5. `POST /user/user_change_password` com `id=1` → assume o Super Admin (F-06).
6. `POST /accounts/customer_save` com `id=<próprio>&type=-1` → auto-promoção a Super Admin (F-07).

Pós-comprometimento / impacto de dados:
7. Qualquer dump do banco → decifrar **todas** as senhas de conta com a `PRIVATE_KEY` do repositório (F-05 + F-08).
8. Qualquer um dos RCEs de upload (F-09..F-13) + sudoers → **root** na máquina (F-14).

CSRF está globalmente desabilitado (F-15), então os passos por POST (5, 6) e o GET (1) são acionáveis a partir de uma página externa enquanto um admin estiver logado.

---

## Achados por severidade

### CRÍTICO

**F-01 — Bypass total de autenticação via rota `relogin/`**
`config/routes.php:90` expõe `relogin/(:any)/(:any)`; `modules/login/controllers/login.php:1005` (`relogin()`) monta a sessão inteira (`user_login`, `logintype`, `accountinfo`, `permissioninfo`) a partir do `id` da URL, sem verificação de sessão prévia, de `master_id` ou de token. `GET /relogin/1/0/` = Super Admin para qualquer um. Métodos `login_as_reseller/customer/admin` (`login.php:977-999`) também são públicos e sem checagem. Ainda vaza a senha do admin em claro para a sessão (`login.php:1021`).

**F-02 — Autenticação da API inteiramente desabilitada**
`libraries/API_Controller.php:180` tem `early_checks()` comentado, e o bloco de validação de `HTTP_X_AUTH_TOKEN` (`:696-703`) está dentro de `/* ... */`. Nenhum endpoint sob `controllers/api/` ou `controllers/common/account.php` exige token.

**F-03 — SQL injection não autenticado no login da API (auth bypass)**
`controllers/api/login.php:81-83` concatena `$this->postdata['username']` cru no SQL (`post()` retorna dados sem escape). `username=' OR 1=1 -- ` retorna conta e emite token.

**F-04 — Reset de senha não autenticado sobre `account_id` arbitrário**
`modules/signup/controllers/signup.php:552-660` (`check_otp`): `account_id` vem do POST e não é vinculado ao `number`/`email` validado; comparação frouxa `$otp_verify['otp'] == $post['otp_number']` permite `NULL == ""` quando não há OTP; OTP gerado com `rand()`; sem rate limit; senha nova gravada/enviada em claro.

**F-05 — `PRIVATE_KEY`/`ENCRYPTION_KEY`/`token_key`/`iv_key` versionadas e idênticas em toda instalação**
`config/flux-config.conf:7-8` e `config/api.php:33,47` trazem chaves fixas; `flux_install.sh:473` copia o arquivo verbatim e nunca regenera (`:609,:611` só trocam `dbpass`/`base_url`). Segredo replicado em `misc/password_change.php:10`. ESL default `ClueCon` em `freeswitch/fs/lib/flux.eventsocket.php:62`. `ENCRYPTION_KEY` tem 11 chars (CI pede 32).

**F-06 — IDOR + ausência de senha atual em `user/user_change_password`**
`modules/user/controllers/user.php:940-955`: `id` alvo vem do POST, nunca comparado com `accountinfo['id']`, sem verificação da senha atual. Customer autenticado troca a senha da conta 1.

**F-07 — IDOR + mass assignment em `accounts/customer_save`**
`modules/accounts/controllers/accounts.php:254-315` + `models/accounts_model.php:197-204` (`edit_account`): `id` do POST sem checagem de posse; array POST inteiro no `UPDATE` com whitelist apenas negativa de 2 campos. Injeta `type=-1`, `reseller_id`, `credit_limit`, `balance` → auto-promoção.

**F-08 — Senhas em cifra reversível Blowfish-ECB, não hash**
`libraries/flux/common.php:2603-2632` (e duplicado em `login.php:962-969`): `openssl_encrypt(..., 'BF-ECB', PRIVATE_KEY)`. ECB ignora o IV (saída determinística → daí a comparação por igualdade em `auth_model.php:27-42`), reversível, sem salt/KDF, bloco de 64 bits. Senhas decifradas e exibidas em várias telas (`signup/views/view_signup_active.php:123`, `accounts.php:233,1727,1908,2717`, `user.php:301,934`).

**F-09 — RCE via upload sem validação (Support Ticket)**
`modules/supportticket/controllers/supportticket.php:255-264`: extensão extraída do nome enviado, sem checagem de MIME/extensão, grava em `/attachments/` (dentro do docroot, sem `.htaccess`). Disponível até para Customer. `x.php` → RCE.

**F-10 — RCE via upload sem validação (System Configuration)**
`modules/systems/controllers/systems.php:120-141`: nome original preservado, grava em `/upload/`. `shell.php` → RCE.

**F-11 — RCE via upload validado só por `Content-Type` do cliente**
`modules/user/controllers/user.php:844-847`: valida `$_FILES['file']['type']` (header controlado pelo atacante). `Content-Type: image/png` + `filename=s.php` → `/upload/<id>_s.php`.

**F-12 — RCE via bypass de extensão por índice `explode(".")[1]`**
`modules/invoices/controllers/invoices.php:1471-1476` (logo/favicon) e `modules/systems/controllers/systems.php:936-948` (DB import, que ainda grava com `basename()` = path relativo no docroot). `x.php.png` / `evil.sql.php` passam. `getimagesize` satisfeito por polyglot. Variante `voice_broadcast.php:179-197` (valida `[1]` mas usa `pathinfo()['extension']`).

**F-13 — Command injection no backup de banco (`path` do POST no shell)**
`modules/systems/controllers/systems.php:824-835`: `$add_array['path']` (input livre) entra em `exec("mysqldump ... > '$backup_file'")` e em `exec("$gzip $backup_file")` **sem aspas**. `x;id>/tmp/p;.gz` executa. Second-order no restore (`:885-893`) e leitura arbitrária de arquivo no download (`:900-923`, ex.: `path=../../../etc/passwd` ou o `flux-config.conf` com senha do MySQL).

**F-14 — Escalação a root: `www-data` dono de script com sudo NOPASSWD**
`config/sudoers.d/fluxsbc-event-guard`: `www-data ALL=(root) NOPASSWD:` para `.../event_guard/event_guard_install.sh` **e** `www-data ALL=(ALL) NOPASSWD: /bin/systemctl` (irrestrito). `flux_install.sh:524` faz `chown -Rf www-data:www-data` sobre o diretório que contém esse script. Reescrever o script + sudo = root. Amplifica todos os RCEs.

**F-15 — CSRF globalmente desabilitado**
`config/config.php:313` `csrf_protection = FALSE`. Nenhum POST protegido; combina com F-01, F-06, F-07.

**F-16 — `unserialize()` de dados de POST (object injection / provável RCE)**
`modules/rates/controllers/rates.php:1616`, `modules/account_import/controllers/account_import.php:159`, `modules/did/controllers/did.php:889`. Valor serializado no servidor e devolvido em campo hidden (`account_import.php:120`), trivialmente substituível; sem `allowed_classes=>false`. Gadgets prováveis (TCPDF/Curl/MailChimp carregados).

**F-17 — `GitUpdate` executa `git pull`/`reset --hard` sem autenticação**
`controllers/GitUpdate.php:2-11` (construtor sem checagem), `:186 executeUpdate()`, `:208 executeRollback()`, `:13-18 runCommand()` → `exec("cd $path && $command")`. Requisição anônima altera o código em `/opt/flux`.

**F-18 — SQL injection sistêmica (causa raiz em `db_model.php`)**
O motor de busca/listagem monta SQL por concatenação de `$this->input->post()` salvo em sessão. Pontos centrais: `models/db_model.php:917-971` (`get_string_array`, LIKE), `:821-823` (`disposition`), `:1002-1097` (família `build_*_where`), `:145-146`/`:328-329` (ORDER BY por `$_GET['sortname']`, replicado em ~13 models). Agravante: `system/database/DB_driver.php` `_protect_identifiers()` retorna o identificador **cru** ao encontrar `(`, então `order_by`/`select`/`from` são injetáveis. Módulo **Summary** (`summary_model.php:67-77` com `_protect_identifiers=false` e `select($x,false)`) retorna dados na resposta (não é cega). Padrão `"id IN ($ids)"` com POST cru replicado em **25+** controllers `*_delete_multiple()` (lista completa no relatório do agente). `local_number_customer()` (`local_number.php:551-556`) e INSERTs sem aspas (`products.php:1007-1040`, `did.php:314`) completam o quadro. `update_balance()` (`db_model.php:1113-1123`) concatena em UPDATE financeiro.

### ALTO

**F-19 — `Account::balance` lê saldo de qualquer conta + SQLi, sem token** — `controllers/common/account.php:93-118` (`id` cru no SQL, `_authorize_account` não valida token).
**F-20 — Invocação de método arbitrário via `object`/`action`** — `api/login.php:53-58`, `common/account.php:123-127`, `api/user_cdrs.php:42`, `admin/product.php:35` (`method_exists` + `$this->$fn()` alcança métodos private).
**F-21 — Cookie de sessão sem HttpOnly/Secure, "cifra" XOR sem MAC** — `system/libraries/Session.php:663-670` (setcookie sem httponly), `config.php:288` (`cookie_secure=FALSE` sob HTTPS), Encrypt cai em `_xor_encode` no PHP 7.3 (mcrypt ausente) com `md5(ENCRYPTION_KEY)` e `mt_rand()`; `sess_match_ip=FALSE`; session id via `md5(uniqid)`.
**F-22 — Sem anti-brute-force nem regeneração de sessão no login** — `login.php:65,427`; permite fixação de sessão.
**F-23 — Command injection nos segmentos de URI (addons)** — `modules/addons/controllers/addons.php:129,168,205,250,389` (`system`/`exec` com `$type`/`$module`); mitigado parcialmente por `permitted_uri_chars` mas permite injeção de argumentos e `rm -rf` destrutivo.
**F-24 — `$filter` sem `escapeshellarg` no daemon root** — `freeswitch/fs/event_guard_daemon.php:391,409` (`sudo fail2ban-client ... {$filter}`).
**F-25 — Controllers sem auth: `Cdr_config::setMode`, `getbalance`, `getendpoint`** — `Cdr_config.php:26-52` (altera config global), `getbalance.php:24`, `getendpoint.php:25` (enumeração/exposição de dados). Rotas explícitas em `routes.php`.
**F-26 — XSS em sink JS global de flash messages** — `views/master.php:174-178`, `left_panel_master.php:38-44` sem escape em contexto JS; alimentado por nome de arquivo (`systems.php:951`), `destination`, `partner_name`, `taxes_description` etc. `master.php:182` ecoa HTML cru.
**F-27 — XSS refletido `$_POST[...]` em JS (7+ views)** — `modules/fsmonitor/views/*` e `user/views/view_user_registered_sipdevices_list.php:84`.
**F-28 — Nome de tabela vindo de POST** — `cdrs_year` (`user_model.php:380`), `summary_model.php:59`, `reports_model.php:44` passam por `get($table)` injetável.
**F-29 — Leitura arbitrária de arquivo via backup path** — coberto em F-13 (`systems.php:900-923`).

### MÉDIO

**F-30 — Host header injection no `base_url`** — hook ativo `hooks/router.php:7-23` deriva `base_url` de `HTTP_HOST` → envenenamento de links (reset de senha).
**F-31 — `check_web_record_permission` é no-op para admin/customer** — `libraries/flux/permission.php:131-147` só bloqueia `type==1|5`; e só ~13 de 46 módulos chamam a verificação.
**F-32 — Módulo `permissions` acessível a Customer autenticado** — `modules/permissions/controllers/permissions.php` só checa `user_login`.
**F-33 — Controllers de cron acessíveis por HTTP** — `purge.php`, `updateBalance.php`, `ProcessInvoice.php`, `generateInvoice.php` etc. sem `is_cli()`.
**F-34 — Comparações frouxas de senha/OTP (`==`, não `hash_equals`)** — `api/login.php:163`, `common/account.php:293`.
**F-35 — Bypass de auth da API por User-Agent forjado (código morto)** — `API_Controller.php:692-694`; reintroduz bypass se a auth for reativada sem cuidado.
**F-36 — SSRF full-control em `api_endpoints`** — `api_endpoints.php:625-636` (URL/método/headers/body do POST, `FOLLOWLOCATION`, sem allowlist → alcança `127.0.0.1:8021`, `169.254.169.254`).
**F-37 — `mysql_query()` com `$_POST` em view** — `fsmonitor/views/view_opensips_extension_report.php:71` (SQLi + fatal no PHP 7).
**F-38 — Permissões 0777 no código** — `addons.php:95,236,385`, `Translation_script.php:46`.
**F-39 — `display_errors=on` em produção** — `index.php:20` (mitigado por `error_reporting(0)`).
**F-40 — Logger de credenciais latente** — `hooks/http_request_logger.php` (comentado hoje em `hooks.php:47-53`; loga POST/cookies em claro se ativado).
**F-41 — Componentes desatualizados (OWASP A06)** — CI 2.1.0 (EOL 2015), jQuery 1.12.4/1.7.1 (CVE-2020-11022/11023, CVE-2019-11358), TinyMCE 4.3.8, TCPDF 5.0.002 (SSRF/LFI/phar), CKEditor antigo.
**F-42 — Uploads e diretórios sensíveis dentro do docroot** — `upload/`, `attachments/`, `database_backup/` sem `.htaccess` e sem regra `deny .php` no Nginx (`web_interface/nginx/deb_flux.conf`).

---

## Causas raiz (correção de maior alavancagem)

1. **`models/db_model.php`** concentra o motor de busca de toda a app montando SQL por concatenação. Corrigir `build_search`, `get_string_array`, `build_search_string`, `build_*_where` e o bloco `order_by($_GET['sortname'])` neutraliza a maior parte da superfície de SQLi de uma vez. Modelo a seguir: bindings já usados em `models/dashboard_model.php`, `sync_model.php`, `invoice_model.php`, `detraf_reports_model.php:41`.
2. **Padrão `"id IN ($ids)"`** copiado ~25 vezes → substituir mecanicamente por `where_in('id', array_map('intval', explode(',', $ids)))`.
3. **Autenticação quebrada por design**: rota `relogin`, API sem token, "Login as" público. Reescrever com verificação de papel + posse + token de uso único.
4. **Criptografia**: chaves fixas versionadas + senhas reversíveis. Regenerar chaves por instalação e migrar para `password_hash`/`password_verify`.
5. **Uploads**: padronizar todos no modelo correto de `modules/flux_import/controllers/flux_import.php:72-92` (conta segmentos, `end()`, `finfo_file` real, nome gerado no servidor) e tirar os diretórios do docroot.

---

## Plano de correção priorizado (fases)

**Fase 0 — Contenção imediata (config, baixo risco de regressão)**
- Remover a rota `relogin` de `config/routes.php:90` e desabilitar/gate os métodos `login_as_*`.
- `config/config.php`: `csrf_protection=TRUE`, `cookie_secure=TRUE`, `cookie_httponly=TRUE`, `sess_match_ip=TRUE`; corrigir `index.php:20` (`display_errors=off`).
- Nginx (`deb_flux.conf`): `location ~ ^/(upload|attachments|database_backup)/ .*\.php$ { deny all; }` (ou desligar FastCGI nesses paths).
- Gate `is_cli()` nos controllers de cron; remover/gate `GitUpdate`, `Cdr_config`, `getbalance`, `getendpoint`.
- `flux_install.sh`: gerar `PRIVATE_KEY`/`ENCRYPTION_KEY`/`token_key`/`iv_key` aleatórias por instalação; **remover `config/flux-config.conf` do versionamento**; enxugar sudoers (tirar `NOPASSWD: /bin/systemctl` genérico e o script `event_guard_install.sh` — passar para `root:root`).

**Fase 1 — Autenticação/autorização**
- Reativar `early_checks()` em `API_Controller.php` sem o bypass por User-Agent; parametrizar `api/login.php` e `common/account.php`.
- Checagem de posse + senha atual em `user_change_password`; whitelist explícita de campos em `edit_account` (F-06, F-07).
- Vincular `account_id` ao OTP em `check_otp`, `hash_equals()`, `random_int()`, rate limit (F-04).
- Substituir `method_exists+$this->$fn()` por mapa allowlist (F-20).

**Fase 2 — SQL injection (causa raiz)**
- Refatorar `db_model.php` para bindings/`where_in`/allowlist de colunas em `order_by`.
- Varrer os 25+ `"id IN ($ids)"` e os INSERTs sem aspas.

**Fase 3 — Uploads/RCE/injeção de comando**
- Padronizar uploads no modelo `flux_import`; mover destino para fora do docroot.
- `escapeshellarg()` em todos os sinks `exec`/`system` (backup, addons, convert, daemon).
- `unserialize(..., ['allowed_classes'=>false])` ou trocar por JSON (F-16).

**Fase 4 — Cripto de senhas (migração)**
- Migrar para `password_hash`/`password_verify` (bcrypt/Argon2id); remover `common->decode($password)` das telas; script de migração progressiva no login.

**Fase 5 — XSS e higiene**
- Escapar sinks JS/HTML (`master.php`, `left_panel_master.php`, views `fsmonitor`).
- Atualizar/mitigar componentes vendorizados (F-41) conforme viabilidade.

---

## Verificação

- **SQLi:** após corrigir `db_model.php`, testar grids (`*_list_json`) com `sortname` malicioso e busca avançada com payload em chave/valor; confirmar via log de query do MySQL que os binds são parametrizados. Regressão funcional das listagens e do módulo Summary.
- **Auth:** confirmar que `GET /relogin/1/0/` retorna 404/403; que `POST /api/login` exige token e resiste a `' OR 1=1`; que `user_change_password`/`customer_save` rejeitam `id` de terceiros.
- **Upload:** subir `.php`, `x.php.png`, polyglot `getimagesize` e `Content-Type` forjado em cada endpoint corrigido; confirmar rejeição e que Nginx não executa `.php` em `upload/`.
- **Comando:** payloads com `;`, `` ` ``, `|`, aspas em `path` do backup e segmentos de addons; confirmar neutralização por `escapeshellarg`.
- **Config:** validar CSRF ativo (POST sem token falha), cookie com `HttpOnly; Secure`, cron controllers retornando erro por HTTP.
- Este é um sistema legado sem testes automatizados e em produção — aplicar por fases, com `git diff` revisado e validação funcional manual a cada fase, priorizando Fase 0 (contenção) primeiro.
