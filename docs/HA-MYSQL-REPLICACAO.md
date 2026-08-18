# Replicação MySQL entre dois servidores FluxSBC

Este documento cobre a topologia de **dois servidores**: um FluxSBC em produção
(`fs-1`) e um servidor de reserva (`fs-db-1`) que replica o banco e pode assumir em
desastre.

É um cenário diferente do descrito em [HA-MYSQL-HAPROXY.md](HA-MYSQL-HAPROXY.md), que
trata de um cluster Galera externo.

---

## 1. Por que replicação e não Galera

O instalador do FluxSBC usa **`mysql-community-server` 8 da Oracle**. Galera é uma
extensão de MariaDB e Percona XtraDB Cluster — não existe para o MySQL da Oracle. Usar
os artefatos de `config/galera/` exigiria trocar o servidor de banco nos dois nós e
revalidar ODBC, as views com `DEFINER` e o `mod_nibblebill`.

Além disso, **Galera com dois nós não tem quorum**. A perda de um nó deixa o
sobrevivente sem maioria e, portanto, inoperante, a menos que se adicione um árbitro
(`garbd`) num terceiro host.

O caminho compatível com o que já está instalado é **replicação assíncrona com GTID**:
`fs-1` como source, `fs-db-1` como réplica `read_only`.

### Consequência: não há failover automático

Replicação assíncrona não garante que toda transação confirmada no source chegou à
réplica. Promover automaticamente arriscaria perder transações e, se o source voltar,
split-brain. Por isso:

- O `fs-db-1` **não entra no `haproxy.cfg` do `fs-1`**. Ele replica, mas não recebe
  tráfego.
- A promoção é um procedimento **manual e deliberado** (`--promote-replica`).

Se failover automático for requisito, o caminho é um terceiro nó com Galera ou
InnoDB Cluster.

---

## 2. O risco de um servidor clonado

Se o `fs-db-1` é uma VM clonada do `fs-1`, ele carrega uma instalação FluxSBC completa
e funcional. Três coisas subiriam sozinhas e causariam dano real:

### 2.1 `json_cdr.service` e `event_guard`

Ambos são instalados com `systemctl enable` **sem** `start` — ficam parados na
instalação, mas **iniciam no próximo boot**.

O `cdr.php` percorre o spool com `glob()` e move os arquivos com `rename()`, **sem
lock**. Dois daemons listam o mesmo arquivo, ambos chamam `process_file_cdr()` — que
insere em `cdrs` e debita `accounts.balance` — e só depois um dos `rename()` falha.
O resultado é **tarifação e faturamento em dobro**.

O `event_guard` executa `sudo fail2ban-client set <jail> banip <ip>`. Dois daemons
reagindo aos mesmos eventos SIP podem **banir gateways de operadora** e derrubar
troncos. A fila de desbloqueio vive em `event_guard_logs`, compartilhada, e o escopo
por host cai em `gethostname()` — que num clone é idêntico ao da origem.

### 2.2 O crontab

O instalador registra uma única entrada, de minuto em minuto, que chama
`crons/index`. Esse controller lê a fila da tabela `cron_settings` e executa
`shell_exec` de `wget {BASE_URL}...`.

Em CLI o hook de roteamento por domínio não dispara, então `base_url` vem do
`flux-config.conf` — ou seja, **o clone dispara os jobs contra a URL de produção**.
Entre eles está `purge/ProcessPurge`, que apaga gravações, CDRs, invoices e contas de
forma irreversível. A coluna `next_execution_date` é lida e escrita sem atomicidade,
então não serve de trava entre dois hosts.

### 2.3 Os EVENTs do MySQL

O schema traz dois eventos ativos:

| Event | Intervalo | Ação |
|---|---|---|
| `staging_cdrs` | 1 minuto | `CALL master_pro()` |
| `remove_cdrs_records` | 1 hora | Purga de `cdrs_staging` |

`event_scheduler` é `ON` por padrão no MySQL 8 e não é desligado em lugar nenhum da
instalação. O `master_pro()` avança `reports_process_list.last_execution_date` sem
lock, e a agregação usa `ON DUPLICATE KEY UPDATE` aditivo
(`billseconds = billseconds + VALUES(billseconds)`): duas execuções sobre a mesma
janela **contabilizam os CDRs duas vezes**.

Esse problema é conhecido no projeto — o cabeçalho de `misc/mysql_events_staging.php`
registra que os EVENTs foram substituídos por um script PHP justamente por causarem
problema com múltiplos servidores master.

**Por isso a ordem importa: desarme o clone antes de conectá-lo à rede de produção.**

---

## 3. Procedimento

### 3.1 No fs-1 (produção)

Pré-requisito: `flux_ha_setup.sh --enable-local` já executado.

```bash
/opt/flux/misc/flux_ha_setup.sh --add-replica --dry-run
/opt/flux/misc/flux_ha_setup.sh --add-replica
```

O script pede o IP privado do `fs-1`, o IP do `fs-db-1` e a senha a definir para o
usuário de replicação. Em seguida:

1. Ajusta o `mysqld.cnf`: `server-id = 1`, `gtid_mode = ON`,
   `enforce_gtid_consistency = ON`, `binlog_format = ROW`, `log_replica_updates = ON`.
2. **Eleva `binlog_expire_logs_seconds` de 86400 para 604800.** O valor original é de
   um dia: se a réplica ficar fora mais que isso, os binlogs necessários já terão sido
   removidos e será preciso re-semear do zero.
3. **Abre o `bind-address`** para `127.0.0.1,<ip_privado>` — nunca `0.0.0.0`.
4. Reinicia o MySQL e confirma que `gtid_mode` ficou `ON`.
5. Cria `'repl'@'<ip_da_replica>'` com `REPLICATION SLAVE`.
6. Cria **`'fluxuser'@'127.0.0.1'`**. Ver seção 6 — isso corrige um defeito que já
   existe hoje, independente de replicação.
7. Gera o dump de seed com `--single-transaction --set-gtid-purged=ON --triggers
   --routines --events` e imprime o comando pronto para o `fs-db-1`.

O downtime é o restart do MySQL: alguns segundos.

### 3.2 No fs-db-1 (reserva)

Copie o seed e execute — de preferência com a VM ainda isolada da rede de produção:

```bash
scp fs-1:/var/backups/fluxsbc/ha_setup/<ts>/seed_flux_<ts>.sql.gz /root/

REPL_SOURCE_HOST=10.0.0.11 \
REPL_PASSWORD='<senha definida no fs-1>' \
SEED_DUMP=/root/seed_flux_<ts>.sql.gz \
/opt/flux/misc/flux_ha_setup.sh --make-db-node
```

Rode antes com `--dry-run`. O script exige que você **digite o hostname da máquina**
para confirmar, porque executá-lo no `fs-1` por engano derruba a produção.

O que ele faz, nesta ordem:

1. **Desarma o clone**: `systemctl disable --now` em `freeswitch`, `json_cdr`,
   `event_guard`, `nginx`, `php7.3-fpm`, `fail2ban` e `haproxy`. Os serviços continuam
   **instalados** — apenas parados e desabilitados — para preservar o papel de SBC
   reserva.
2. **Esvazia o crontab** do usuário `flux`, guardando cópia no snapshot.
3. **Move o spool de CDR pendente** para o snapshot, para que não seja reprocessado.
4. **Regenera o `server_uuid`**: remove `/var/lib/mysql/auto.cnf`. Sem isso a
   replicação recusa conectar, porque o clone herdou o UUID do `fs-1`.
5. Ajusta o `mysqld.cnf`: `server-id = 2`, `read_only = ON`, `super_read_only = ON`,
   `event_scheduler = OFF`, `skip_replica_start = ON`, `relay_log`.
6. Restaura o seed (liberando a escrita temporariamente, com `RESET MASTER` para
   aceitar o `gtid_purged` do dump) e **desabilita os EVENTs herdados**.
7. Emite `CHANGE REPLICATION SOURCE TO ... SOURCE_AUTO_POSITION = 1` e `START REPLICA`,
   com fallback automático para a sintaxe `CHANGE MASTER TO` / `START SLAVE` em versões
   anteriores à 8.0.23.

---

## 4. Verificação

No `fs-db-1`, antes de liberar a VM na rede:

```bash
systemctl is-enabled freeswitch json_cdr event_guard nginx php7.3-fpm
crontab -l -u flux
```

Todos devem retornar `disabled`, e o crontab deve estar vazio.

```sql
SELECT @@server_uuid;      -- precisa ser DIFERENTE do fs-1
SELECT @@event_scheduler;  -- OFF
SELECT @@GLOBAL.super_read_only;  -- 1
SELECT EVENT_NAME, STATUS FROM information_schema.EVENTS WHERE EVENT_SCHEMA='flux';
```

Estado da replicação:

```bash
mysql -e "SHOW REPLICA STATUS\G" | grep -E "Running|Behind|Error"
```

Ou, de forma automatizada:

```bash
/opt/flux/misc/flux_replication_check.sh
```

O script verifica as threads de IO e SQL, o lag contra um limiar, os campos de erro, a
diferença entre GTIDs recebidos e aplicados, o `super_read_only` e o `event_scheduler`.
Retorna `0` se saudável, `1` se degradado e `2` em erro de configuração. Registre-o no
cron do `fs-db-1`:

```bash
*/5 * * * * /opt/flux/misc/flux_replication_check.sh --quiet
```

Para comparar contagens com o source (mais pesado, use com parcimônia):

```bash
/opt/flux/misc/flux_replication_check.sh --compare
```

**Teste de propagação**: altere um registro inócuo no `fs-1` — por exemplo o
`display_name` de uma chave da tabela `system` — e confirme que chega ao `fs-db-1` em
segundos.

**Prova de que o clone está inerte**: complete uma chamada tarifada no `fs-1` e
confirme que o CDR entra **uma vez** em `cdrs` e o débito em `accounts.balance` ocorre
**uma vez**.

---

## 5. Desastre: promover o fs-db-1

Só execute com o `fs-1` **comprovadamente parado**.

```bash
/opt/flux/misc/flux_ha_setup.sh --promote-replica
```

Exige o hostname digitado e uma confirmação de que o servidor de origem não voltará a
escrever. O script para a replicação, libera a escrita, religa o `event_scheduler` e os
EVENTs, e reativa `haproxy`, `php7.3-fpm`, `nginx`, `freeswitch`, `json_cdr` e
`fail2ban`.

Passos manuais que restam, deliberadamente fora do script:

1. **Crontab** — restaure do snapshot:
   ```bash
   cp <snapshot>/var/spool/cron/crontabs/flux /var/spool/cron/crontabs/flux
   crontab -u flux /var/spool/cron/crontabs/flux
   ```
2. **`event_guard`** — só ative depois de revisar a topologia de rede:
   ```bash
   systemctl enable --now event_guard
   ```
3. **DNS / IP** — aponte o domínio do FluxSBC e os IPs de sinalização SIP para o novo
   servidor. Os gateways e troncos precisam alcançá-lo.

### 5.1 Reconstruir o fs-1 depois da promoção

**Esta etapa não é opcional.** Religar o `fs-1` com o banco aceitando escrita, enquanto
o `fs-db-1` também escreve, produz split-brain: as duas bases divergem e não há
reconciliação automática de CDRs nem de saldo.

O `fs-1` deve voltar **como réplica** do `fs-db-1`:

1. No `fs-db-1` (agora primário): `--add-replica`, informando o IP do `fs-1`.
2. No `fs-1`: `--make-db-node` com o seed gerado no passo anterior.

Depois de estabilizado, a inversão de papéis (voltar o `fs-1` a primário) é o mesmo
procedimento no sentido oposto, em janela programada.

---

## 6. Pontos de atenção

- **`server_uuid` duplicado**: o sintoma é a replicação recusando conectar com erro de
  UUID, nem sempre óbvio no log. O `--make-db-node` remove o `auto.cnf` antes de
  qualquer outra coisa.
- **Retenção de binlog**: elevada de 1 para 7 dias. Se a réplica ficar fora mais que
  isso, é necessário re-semear com um novo dump.
- **`DEFINER = fluxuser@127.0.0.1`**: 53 objetos de banco (20 views, procedures,
  functions e os 2 events) declaram esse definer, mas o instalador cria o usuário como
  `'fluxuser'@'%'`. As 8 views com `SQL SECURITY DEFINER`, as 2 procedures e os 2
  events falham hoje com `ERROR 1449` — mascarado pelo `-f` na carga do schema. O
  `--add-replica` cria `'fluxuser'@'127.0.0.1'` e corrige isso nos dois nós; sem ele o
  dump também não restaura na réplica.
- **Serviços `disabled`, não `masked`**: para preservar o papel de SBC reserva. Um
  `systemctl start` manual no `fs-db-1` reintroduz todos os riscos da seção 2.
- **`sql_mode=""`**: com `binlog_format=ROW` o risco de divergência é baixo, mas o
  `mysqld.cnf` passa a fixar o formato explicitamente em vez de depender do padrão.
- **A réplica não atende leitura**: mesmo com lag baixo, o `db_read()` do Lua serve
  autorização e directory. Uma conta recém-bloqueada poderia ser autorizada a partir de
  dado defasado, e não existe equivalente ao `wsrep_sync_wait` fora do Galera.
