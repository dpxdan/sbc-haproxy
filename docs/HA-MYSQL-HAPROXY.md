# Alta disponibilidade de MySQL no FluxSBC com HAProxy e Galera

Este documento descreve a camada de alta disponibilidade e balanceamento de carga
introduzida entre o FluxSBC e o banco de dados. O objetivo é dar failover automático
para todos os caminhos de acesso ao MySQL sem alterar o código PHP da aplicação.

---

## 1. Arquitetura

O FluxSBC alcança o banco por cinco caminhos independentes:

| Caminho | Mecanismo | Configuração | Endpoint |
|---|---|---|---|
| Interface web (CodeIgniter) | `mysqli_connect` | `/var/lib/flux/flux-config.conf` | escrita |
| FreeSWITCH PHP (`fs/index.php`, `cdr.php`, event guard) | PDO persistente | `/var/lib/flux/flux-config.conf` | escrita |
| Lua — escrita (`db_write`) | `freeswitch.Dbh` / DSN `FLUX` | `/etc/odbc.ini` | escrita |
| Lua — leitura (`db_read`) | `freeswitch.Dbh` / DSN `FLUX_RO` | `/etc/odbc.ini` | leitura |
| `mod_nibblebill` | `odbc-dsn` = `$${dsn}` | `/etc/freeswitch/vars.xml` | escrita |

Convenção de portas do HAProxy:

| Porta | Backend | Balanceamento |
|---|---|---|
| **3306** | `fluxdb_write_pool` | `first` — um nó ativo, os demais em `backup` |
| **3307** | `fluxdb_read_pool` | `leastconn` — todos os nós ativos |
| **3316** | MySQL local | usada apenas na etapa 1, quando o banco ainda roda no SBC |
| **8404** | estatísticas HTTP | somente loopback |

A porta de escrita é a **3306** de propósito: como o MySQL deixa de rodar no SBC, o
HAProxy assume a porta padrão e nada no PHP, no `mysqldump` ou no `migrations.sh`
precisa saber que existe um proxy no caminho.

### Escrita em nó único

O backend de escrita usa `balance first` com os nós secundários marcados como `backup`.
Isso é **obrigatório**, não uma preferência: `accounts.balance` é atualizado
concorrentemente por `mod_nibblebill` e por `update_balance()` do processamento de CDR.
Em Galera multi-master, escritas concorrentes na mesma linha a partir de nós diferentes
produzem falha de certificação (deadlock) sob carga.

### Topologia declarada por lista

A composição do pool é uma lista ordenada em `FLUX_DB_HOSTS`, aplicada com
`flux_ha_setup.sh --set-topology`:

```
FLUX_DB_HOSTS="local,10.0.0.12:3306,10.0.0.13:3306"
```

- `local` resolve para `127.0.0.1` na porta do MySQL da máquina — é assim que o MySQL
  local participa do pool junto com hosts remotos.
- A ordem define a prioridade de escrita: o primeiro é ativo, os demais entram com
  `backup`.
- Todos os hosts participam do pool de leitura.
- Um host sem porta explícita assume 3306.

A topologia corrente fica registrada em `/var/lib/flux/ha-topology.conf`.

### Health check por papel

Cada nó do pool roda o agente `flux-dbcheck` na porta 9200
([config/healthcheck/README.md](../config/healthcheck/README.md)), que responde por
papel em vez de responder por liveness:

| Endpoint | 200 quando |
|---|---|
| `/write` | O nó aceita escrita: `read_only=OFF`; em Galera, também `wsrep_ready=ON` e `wsrep_local_state=4` |
| `/read` | O nó serve leitura: sendo réplica, replicação rodando e lag ≤ `FLUX_DB_READ_MAX_LAG` |

É isso que permite declarar uma réplica no pool de escrita em caráter permanente: ela
fica DOWN enquanto for `read_only` e entra sozinha quando promovida. Um `mysql-check`
comum não faz essa distinção e mandaria `INSERT`s para um nó que os recusa com
`ERROR 1290`.

**O agente precisa estar instalado em todos os hosts do pool, inclusive no local.**

### Modos de implantação

**`haproxy_sidecar`** — HAProxy roda no próprio SBC, escutando em `127.0.0.1`.
Sem ponto único de falha adicional, sem VIP, latência mínima. É o modo recomendado.

**`haproxy_vip`** — par de HAProxy dedicado com IP virtual gerenciado por keepalived.
Concentra a configuração em um lugar, ao custo de um salto de rede a mais.
O instalador habilita `net.ipv4.ip_nonlocal_bind` para permitir o bind no VIP.

---

## 2. Pré-requisitos no cluster Galera

> **Atenção — engine de banco.** O `flux_install.sh` instala `mysql-community-server` 8
> da Oracle, que **não suporta Galera** (Galera é MariaDB / Percona XtraDB Cluster).
> Adotar a topologia desta seção exige trocar o servidor de banco nos nós do cluster.
> Galera também precisa de **3 nós** para ter quorum — com 2, a perda de um deixa o
> sobrevivente inoperante sem um árbitro (`garbd`).
>
> Para o cenário de **dois servidores** com o MySQL que já está instalado, use
> replicação assíncrona com GTID: veja [HA-MYSQL-REPLICACAO.md](HA-MYSQL-REPLICACAO.md).

### 2.1 Chaves primárias

Galera não suporta tabelas InnoDB sem chave primária. A migração
`database/updates/update-18-08-2026.sql` adiciona uma PK substituta em `cdrs`,
`cdrs_staging`, `reseller_cdrs` e `q850code`.

Em base de produção, `cdrs` costuma ter milhões de linhas. O `ALTER TABLE` roda por
padrão em **TOI** (Total Order Isolation) e bloqueia o cluster inteiro durante a
execução. Use RSU, aplicando nó a nó com o nó fora do balanceamento:

```sql
SET SESSION wsrep_OSU_method = 'RSU';
ALTER TABLE cdrs ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id);
SET SESSION wsrep_OSU_method = 'TOI';
```

As 37 tabelas de `database/flux-tables.sql` (`channels`, `registrations`,
`sip_registrations`, `tasks`, …) pertencem ao core do FreeSWITCH e **18 delas não têm
PK**. Elas estão inertes: `core-db-dsn` permanece comentado em `switch.conf.xml` e o
core do FreeSWITCH usa SQLite. Não ative `core-db-dsn` apontando para o cluster sem
antes resolver essas chaves — o core faz `INSERT` posicional em algumas versões, então
adicionar colunas nessas tabelas exige validação com a versão do FreeSWITCH em uso.

### 2.2 Usuário da aplicação

O `fluxuser` é criado como `'fluxuser'@'%'` com `mysql_native_password` — o
Connector/ODBC 8 não negocia `caching_sha2_password` com a configuração usada aqui.
Com HAProxy em modo sidecar, o MySQL enxerga o IP do SBC; em modo VIP, o IP do par
HAProxy. O curinga `%` cobre os dois casos.

### 2.3 Health check

Instale o agente `flux-dbcheck` em cada nó Galera seguindo
[config/healthcheck/README.md](../config/healthcheck/README.md).
O HAProxy consulta a porta 9200 e só envia tráfego para nós com
`wsrep_local_state=4`, `wsrep_ready=ON` e — no pool de escrita — `read_only=OFF`.

### 2.4 Parâmetros do servidor

`config/mysqld.cnf` vale para instalações standalone. No cluster, garanta ao menos:

```ini
transaction_isolation = READ-COMMITTED
wait_timeout = 600
max_connections = 10000
```

O `timeout client`/`timeout server` do HAProxy é 620s, deliberadamente **maior** que o
`wait_timeout`, para que o MySQL encerre a conexão ociosa primeiro e o cliente receba
um erro limpo — as conexões PDO do processamento de CDR são persistentes.

---

## 3. Instalação

> Para implantação passo a passo em dois ou mais servidores, com os comandos na ordem
> de execução, use o [roteiro de implantação](HA-MYSQL-TOPOLOGIA.md). Para investigar
> problemas, o [diagnóstico](HA-MYSQL-DIAGNOSTICO.md).

Ajuste as variáveis no topo de `flux_install.sh`:

```bash
FLUX_DB_MODE="haproxy_sidecar"
FLUX_DB_NODES="10.0.0.11:3306,10.0.0.12:3306,10.0.0.13:3306"
FLUX_DB_WRITE_PORT=3306
FLUX_DB_READ_PORT=3307
MYSQL_ADMIN_HOST="10.0.0.11"
MYSQL_ADMIN_PASSWORD="senha-root-do-cluster"
```

No modo `haproxy_vip`, preencha também `FLUX_DB_VIP`.

O primeiro nó de `FLUX_DB_NODES` é o nó de escrita; os demais entram como `backup`.
Quando `MYSQL_ADMIN_HOST` fica vazio, o instalador usa o primeiro nó da lista para
carregar o schema e aplicar as migrações — a carga vai direto ao nó, não pelo proxy.

Com `FLUX_DB_MODE="local"` o instalador se comporta exatamente como antes: instala o
MySQL na máquina, não instala o HAProxy e aponta tudo para `127.0.0.1:3306`.

### O que o instalador faz

| Função | Comportamento |
|---|---|
| `install_mysql` | Em modo remoto, delega para `install_db_client_only` (cliente + unixODBC, sem servidor) |
| `normalize_mysql` | Copia sempre o `odbc.ini`; só aplica `mysqld.cnf` em modo local |
| `install_haproxy` | Instala o pacote, gera os backends a partir de `FLUX_DB_NODES`, valida com `haproxy -c` |
| `configure_db_endpoints` | Reescreve `dbhost` no `flux-config.conf` e `SERVER`/`PORT` das duas seções do `odbc.ini` |
| `install_database` | Carga de schema via `MYSQL_ADMIN_HOST` com `--protocol=TCP` |

---

## 4. Habilitar em uma instalação existente

Instalações já em produção são convertidas pelo script `misc/flux_ha_setup.sh`, que faz
tudo em duas etapas, com snapshot e rollback.

```bash
/opt/flux/misc/flux_ha_setup.sh
```

Sem argumentos ele abre um menu. Para automação, use as flags — `--help` lista todas.

### Pré-requisito

O código em `/opt/flux` precisa estar atualizado para esta versão: o script recusa
rodar se `freeswitch/scripts/flux/lib/pbx_scripts/db.lua` ainda não tiver o
`ODBC_DSN_RO`. Sem isso o DSN de leitura seria criado mas nunca usado.

### Etapa 1 — HAProxy sobre o MySQL local

```bash
/opt/flux/misc/flux_ha_setup.sh --enable-local --dry-run
/opt/flux/misc/flux_ha_setup.sh --enable-local
```

O MySQL local sai da 3306 para a **3316** e o HAProxy assume 3306 (escrita) e 3307
(leitura), apontando de volta para ele. Ainda não há cluster, mas toda a aplicação
passa a falar com o proxy — a partir daí, plugar nós Galera é uma mudança só de
backend.

O que o script faz:

1. Snapshot de `odbc.ini`, `flux-config.conf`, `flux.lua`, `mysqld.cnf`,
   `haproxy.cfg`, `vars.xml` e `nibblebill.conf.xml`, mais um `mysqldump` completo,
   em `/var/backups/fluxsbc/ha_setup/<timestamp>/`.
2. Instala `haproxy` e `socat`.
3. Acrescenta `port = 3316` ao `mysqld.cnf`, reinicia o MySQL e confirma com
   `mysqladmin ping` na porta nova.
4. Cria o usuário `haproxy_check`@`127.0.0.1` (só `USAGE`) para o `option mysql-check`.
5. Gera o `haproxy.cfg`, **valida com `haproxy -c` antes de aplicar**, e sobe o serviço.
6. Reescreve o `odbc.ini`: remove `Socket`, fixa `SERVER`/`PORT` em `[FLUX]` e
   **acrescenta a seção `[FLUX_RO]`**, que não existe nas instalações antigas.
7. Acrescenta `ODBC_DSN_RO` e `DB_SYNC_WAIT` ao `flux.lua`.
8. Troca o `odbc-dsn` do nibblebill por `$${dsn}`, se ainda estiver no placeholder.
9. Reinicia `php7.3-fpm` e `nginx`.

O downtime é o restart do MySQL — alguns segundos.

**O FreeSWITCH não é reiniciado automaticamente.** Reiniciá-lo derruba as chamadas
ativas, e o pool ODBC só passa a usar os DSNs novos em conexões novas. Programe a
janela e execute:

```bash
systemctl restart freeswitch
```

Todos os passos são idempotentes: reexecutar sobre um servidor já convertido apenas
reporta o estado, sem alterar nada.

### Etapa 2 — repontar para o cluster Galera

Antes, aplique a migração de chaves primárias (opção 4 do menu ou `--pk-migration`) —
o script bloqueia a etapa 2 enquanto houver tabela de CDR sem PK.

```bash
export FLUX_DB_NODES="10.0.0.11:3306,10.0.0.12:3306,10.0.0.13:3306"
export MYSQL_ADMIN_PASSWORD="senha-root-do-cluster"
/opt/flux/misc/flux_ha_setup.sh --migrate-galera
```

O script faz o dump do MySQL local, carrega no nó de escrita e **compara a contagem de
tabelas, de `accounts` e de `cdrs` entre origem e destino antes de repontar**. Se houver
divergência, aborta sem trocar nada. Só depois da verificação passar ele sugere parar o
MySQL local.

### Inspeção e rollback

```bash
/opt/flux/misc/flux_ha_setup.sh --status
/opt/flux/misc/flux_ha_setup.sh --rollback
```

O rollback restaura o snapshot mais recente, desabilita o HAProxy e devolve o MySQL à
3306. Cada snapshot tem um `MANIFEST.txt` com os comandos de restauração manual.

### Procedimento manual equivalente

Se preferir intervir à mão:

1. Provisione o cluster Galera e instale o `flux-dbcheck` nos nós.
2. Aplique `database/updates/update-18-08-2026.sql` na base atual.
3. Dump e restauração no nó de escrita:
   ```bash
   mysqldump --single-transaction --databases flux > flux.sql
   mysql -h 10.0.0.11 --protocol=TCP flux < flux.sql
   ```
4. Instale o HAProxy a partir de `config/haproxy/flux-mysql.cfg`, substituindo os
   placeholders `__FLUX_DB_*__`.
5. `systemctl stop mysql && systemctl disable mysql`.
6. Atualize os endpoints:
   - `/var/lib/flux/flux-config.conf` → `dbhost`
   - `/etc/odbc.ini` → `SERVER`/`PORT` de `[FLUX]` (3306) e `[FLUX_RO]` (3307), **sem**
     a diretiva `Socket`
   - `/var/lib/flux/flux.lua` → `ODBC_DSN_RO="FLUX_RO"` e `DB_SYNC_WAIT="TRUE"`
7. Reinicie `haproxy`, `php7.3-fpm`, `nginx` e `freeswitch`.

O `ODBC_DSN_RO` tem fallback no código Lua (`ODBC_DSN_RO or ODBC_DSN`), então uma
instalação que ainda não tenha a variável continua funcionando com o DSN único.

## 5. Consistência de leitura

Galera replica de forma síncrona, mas aplica os write-sets em background: uma leitura
imediatamente após uma escrita pode não enxergá-la. Com `DB_SYNC_WAIT="TRUE"` em
`/var/lib/flux/flux.lua`, o `db_read()` emite `SET SESSION wsrep_sync_wait=1` ao abrir
a conexão, garantindo que autorização, directory e ACL não decidam sobre dado defasado.

O custo é um round-trip adicional por conexão nova. Como o FreeSWITCH mantém pool de
handles por DSN (`max-db-handles` = 500), isso ocorre raramente.

Em instalação standalone a variável fica em `"FALSE"` — a instrução é ignorada e o
comportamento não muda.

---

## 6. Operação

### Estado do balanceamento

```bash
echo "show stat" | socat /run/haproxy/admin.sock stdio | cut -d, -f1,2,18,19
```

Interface HTTP: `http://127.0.0.1:8404/`

### Drenar um nó para manutenção

```bash
echo "disable server fluxdb_read_pool/db_10_0_0_12" | socat /run/haproxy/admin.sock stdio
echo "enable server fluxdb_read_pool/db_10_0_0_12" | socat /run/haproxy/admin.sock stdio
```

### Promover outro nó a nó de escrita

Sem reinício do serviço:

```bash
echo "set server fluxdb_write_pool/db_10_0_0_11 state maint" | socat /run/haproxy/admin.sock stdio
```

O tráfego migra para o próximo nó elegível. Para tornar permanente, reordene a lista em
`FLUX_DB_HOSTS` e reaplique com `flux_ha_setup.sh --set-topology`.

### Failover automático

Com `on-marked-down shutdown-sessions`, ao marcar um nó como DOWN o HAProxy encerra as
sessões existentes. Isso é o que força as conexões PDO **persistentes** do
processamento de CDR a reconectarem no nó novo em vez de ficarem presas a um socket
morto.

Detecção: `inter 2s rise 2 fall 2` — um nó indisponível sai do pool em cerca de 4s.

### Verificação após mudanças

```bash
haproxy -c -f /etc/haproxy/haproxy.cfg
isql FLUX fluxuser <senha> -v
isql FLUX_RO fluxuser <senha> -v
fs_cli -x "sofia status"
```

---

## 7. Rollback para MySQL local

1. `FLUX_DB_MODE="local"` para reinstalações.
2. Restaure `/etc/haproxy/haproxy.cfg.orig` (salvo pelo instalador) ou desabilite o
   serviço: `systemctl disable --now haproxy`.
3. Em `/etc/odbc.ini`, aponte as duas seções para o MySQL local e, se preferir socket
   Unix, recoloque `Socket = /var/run/mysqld/mysqld.sock` na seção `[FLUX]`.
4. Em `/var/lib/flux/flux.lua`, volte `DB_SYNC_WAIT="FALSE"`.
5. `dbhost = 127.0.0.1` em `/var/lib/flux/flux-config.conf`.
6. Reinicie `php7.3-fpm`, `nginx` e `freeswitch`.

---

## 8. Pontos de atenção

- **Cliente MySQL e `-h localhost`**: o cliente `mysql` usa socket Unix quando o host é
  `localhost`, mesmo com `-h`. Por isso todos os scripts passaram a usar `127.0.0.1`
  com `--protocol=TCP`. Sem isso, uma migração "bem-sucedida" pode ter sido aplicada na
  máquina errada.
- **Backup**: `mysqldump` passou a usar `--single-transaction`. Sem essa flag o dump
  usa `LOCK TABLES` e bloqueia o cluster.
- **Cliente no modo remoto**: `install_db_client_only` instala `default-mysql-client`
  no Debian. Se precisar especificamente das ferramentas Oracle, adicione o repositório
  `mysql-apt-config` e instale `mysql-client` antes de rodar o instalador.
- **Senhas com metacaracteres**: `genpasswd` em `flux_install.sh` aplica
  `sed s/./*/5`, inserindo um `*` na senha gerada. Combinado com `sed` de delimitador
  `#`, senhas com metacaracteres podem quebrar as substituições em `odbc.ini`,
  `flux.lua` e `flux-config.conf`. É comportamento pré-existente, mas agora atinge mais
  arquivos.
- **Porta 9200**: deve ser alcançável apenas a partir dos servidores que rodam HAProxy.
- **Porta 3316 e o `haproxy_check`**: na etapa 1 o MySQL local passa a escutar na 3316 e
  ganha o usuário `haproxy_check`@`127.0.0.1` (apenas `USAGE`, sem senha), usado pelo
  `option mysql-check`. Ao migrar para Galera, o health check muda para `httpchk` na
  9200 e esse usuário deixa de ser consultado.
- **Restart do FreeSWITCH**: nenhuma etapa do `flux_ha_setup.sh` reinicia o FreeSWITCH
  sozinha. Até o restart, o pool ODBC continua usando as conexões antigas.
