# Diagnóstico de replicação e configuração

Guia organizado por sintoma. Cada entrada segue a mesma estrutura: **como confirmar →
causa → correção → como validar**.

Para implantar do zero, use o [roteiro](HA-MYSQL-TOPOLOGIA.md). Para o racional da
arquitetura, [HA-MYSQL-REPLICACAO.md](HA-MYSQL-REPLICACAO.md).

---

## Triagem: por onde começar

Rode isto primeiro, no servidor com problema:

```bash
/opt/flux/misc/flux_ha_setup.sh --verify-config
```

Ele detecta sozinho se o nó é master ou réplica e audita a configuração inteira.
Códigos de saída: `0` tudo certo, `1` avisos, `2` falhas.

| Resultado | Vá para |
|---|---|
| `FAIL` em arquivos, portas ou usuários | [§6 Conferência de configuração](#6-conferência-de-configuração) |
| Tudo `PASS`, mas dados não replicam | [§1 Tabela vazia na réplica](#1-réplica-com-tabela-vazia-e-replicação-saudável) |
| Threads paradas | [§2](#2-replicação-parada-após-restart) e [§3](#3-thread-sql-parada-com-erro) |
| Lag alto | [§4 Lag crescente](#4-lag-crescente) |
| Réplica DOWN no HAProxy | [§5](#5-réplica-down-no-pool-de-escrita) |

Na réplica, o estado da replicação em si:

```bash
/opt/flux/misc/flux_replication_check.sh
```

`0` saudável, `1` degradado, `2` erro de configuração, **`3` divergência confirmada**.

---

## 1. Réplica com tabela vazia e replicação "saudável"

**Sintoma.** O master tem registros novos — por exemplo 8 linhas em `cdrs` — e a réplica
segue com a tabela vazia. E o pior: `SHOW REPLICA STATUS` não acusa nada.

### Como confirmar

Nos dois servidores:

```bash
mysql -h 127.0.0.1 -P 3316 --protocol=TCP -u fluxuser -p flux -e "SELECT COUNT(*) FROM cdrs;"
```

Na réplica:

```bash
mysql -h 127.0.0.1 -P 3316 --protocol=TCP -u fluxuser -p -e "SHOW REPLICA STATUS\G" | grep -E "Running|Behind|Error"
```

Se aparecer `Replica_IO_Running: Yes`, `Replica_SQL_Running: Yes`,
`Seconds_Behind_Source: 0` e nenhum erro — mas os dados não estão lá — o teste decisivo
é comparar os GTIDs.

No master:

```bash
mysql -h 127.0.0.1 -P 3316 --protocol=TCP -u fluxuser -p -N -B -e "SELECT @@GLOBAL.gtid_executed;"
```

Na réplica, colando o valor obtido acima:

```bash
mysql -h 127.0.0.1 -P 3316 --protocol=TCP -u fluxuser -p -N -B -e "SELECT GTID_SUBTRACT('<gtid_executed do master>', @@GLOBAL.gtid_executed);"
```

**Resultado não vazio com lag zero = transações descartadas.** O `flux_replication_check.sh`
faz exatamente essa comparação e retorna `3` nesse caso.

### Causa

A réplica considera essas transações **já aplicadas** e as ignora. Acontece quando a
réplica é um clone do master e foi conectada **sem seed**: ela herdou o `gtid_executed`
do momento da cópia, que já cobre os GTIDs que o master está emitindo agora.

O `--make-db-node` avisa quando roda sem `SEED_DUMP` — mas é só um `WARN`, fácil de
passar batido.

### Correção

```bash
/opt/flux/misc/flux_replication_repair.sh
```

Com divergência confirmada, o script escala direto ao nível 3: puxa um dump novo do
master, recarrega a base local e reconecta a replicação. Para ver o que ele faria antes:

```bash
/opt/flux/misc/flux_replication_repair.sh --dry-run
```

### Como validar

```bash
/opt/flux/misc/flux_replication_check.sh
```

Deve sair com `0`. Confirme a contagem nos dois servidores — os 8 registros precisam
estar na réplica.

---

## 2. Replicação parada após restart

**Sintoma.** Depois de reiniciar o MySQL ou a VM, `Replica_IO_Running: No` e
`Replica_SQL_Running: No`, sem nenhum erro registrado.

### Como confirmar

```bash
grep -E "^\s*skip_replica_start" /etc/mysql/mysql.conf.d/mysqld.cnf
```

### Causa

`skip_replica_start = ON` faz o MySQL subir **sem** iniciar a replicação. Versões
anteriores deste conjunto de scripts gravavam esse valor; o padrão atual é `OFF`.

### Correção

Imediata:

```bash
mysql -h 127.0.0.1 -P 3316 --protocol=TCP -u root -p -e "START REPLICA;"
```

Permanente:

```bash
sed -i 's/^\s*skip_replica_start.*/skip_replica_start = OFF/' /etc/mysql/mysql.conf.d/mysqld.cnf
```

O `flux_replication_repair.sh` resolve o sintoma sozinho no nível 1, mas o ajuste no
`.cnf` é o que evita a recorrência.

### Como validar

Reinicie o MySQL e confirme que a replicação volta sem intervenção:

```bash
systemctl restart mysql && sleep 5 && /opt/flux/misc/flux_replication_check.sh
```

---

## 3. Thread SQL parada com erro

**Sintoma.** `Replica_SQL_Running: No` com `Last_SQL_Error` preenchido.

### Como confirmar

```bash
mysql -h 127.0.0.1 -P 3316 --protocol=TCP -u root -p -e "SHOW REPLICA STATUS\G" | grep -A3 Last_SQL_Error
```

### Causas e correções

**`ERROR 1449: The user specified as a definer does not exist`** — falta o usuário
`fluxuser@127.0.0.1`, que é o DEFINER de 53 objetos do schema. No master:

```bash
mysql -u root -p -e "CREATE USER IF NOT EXISTS 'fluxuser'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '<senha do flux-config.conf>'; GRANT ALL PRIVILEGES ON \`flux\`.* TO 'fluxuser'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

Repita na réplica. Depois, `START REPLICA`.

**`Duplicate entry` / `Can't find record`** — a base da réplica divergiu. Não pule o
erro com `sql_slave_skip_counter`: isso mascara a divergência e ela cresce. Re-semeie:

```bash
/opt/flux/misc/flux_replication_repair.sh
```

**Erro transitório** (perda de conexão, timeout) — o nível 2 do reparo reinicia as
threads e costuma resolver.

### Como validar

```bash
/opt/flux/misc/flux_replication_check.sh
```

---

## 4. Lag crescente

**Sintoma.** `Seconds_Behind_Source` sobe e não volta.

### Como confirmar

```bash
/opt/flux/misc/flux_replication_check.sh --threshold 10
```

### Causas

- **Volume de escrita acima da capacidade de aplicação da réplica.** Comum em VM com
  disco mais lento que o do master.
- **Transação longa no master** — um `ALTER TABLE` em `cdrs`, por exemplo.
- **Rede saturada** entre os nós.

Para distinguir, observe se `Retrieved_Gtid_Set` continua avançando: se sim, a réplica
está recebendo e o gargalo é na aplicação; se não, o gargalo é rede ou o master.

### Efeito no HAProxy

Acima de 30s de atraso (`FLUX_DB_READ_MAX_LAG`), o agente passa a responder **503** em
`/read` e o nó **sai sozinho do pool de leitura** — leitura defasada não chega à
aplicação. Ele volta quando o lag normaliza.

Para ajustar o limite:

```bash
systemctl edit flux-dbcheck@.service
```

Acrescentando `Environment=FLUX_DB_READ_MAX_LAG=60` na seção `[Service]`.

---

## 5. Réplica DOWN no pool de escrita

**Sintoma.** No painel do HAProxy ou no `show stat`, a réplica aparece DOWN em
`fluxdb_write_pool`.

**Isto é o comportamento correto, não um defeito.** O agente responde 503 em `/write`
enquanto o nó estiver `read_only` — é justamente o que impede o HAProxy de enviar
`INSERT`s para uma réplica, que os recusaria com `ERROR 1290`.

A réplica passa a UP nesse pool automaticamente quando for promovida:

```bash
/opt/flux/misc/flux_ha_setup.sh --promote-replica
```

Para confirmar que o motivo é esse:

```bash
curl -i http://<ip-da-replica>:9200/write
```

O corpo da resposta traz `read_only=ON (no de replica; promova antes de receber escrita)`.

---

## 6. Conferência de configuração

O que o `--verify-config` valida, para quem preferir conferir à mão.

### Comum aos dois papéis

| Item | Esperado | Se divergir |
|---|---|---|
| `/var/lib/flux/flux-config.conf` | Legível, com `dbhost` definido | A aplicação inteira perde o banco |
| `/etc/odbc.ini` | `[FLUX]` e `[FLUX_RO]` presentes | Sem `[FLUX_RO]`, o `db_read()` do Lua cai no DSN de escrita |
| `/etc/odbc.ini` | **Sem** `Socket = ...` | Com `Socket`, o ODBC vai direto ao MySQL local e ignora o HAProxy |
| `/var/lib/flux/flux.lua` | `ODBC_DSN_RO` definido | O split de leitura não acontece |
| `/etc/mysql/flux-dbcheck.cnf` | Presente, 0600, dono `mysql` | O agente não consegue consultar o MySQL |
| Portas | 3316 MySQL; 3306/3307/8404 HAProxy; 9200 agente | Ver `ss -lntp` |
| DSNs | `isql FLUX` e `isql FLUX_RO` conectam | Credencial ou porta errada no `odbc.ini` |

### Somente no master

| Item | Esperado | Se divergir |
|---|---|---|
| `gtid_mode` / `enforce_gtid_consistency` | `ON` | Sem GTID não há `SOURCE_AUTO_POSITION` |
| `log_bin` / `binlog_format` | Ativo / `ROW` | Sem binlog não existe replicação |
| `server_id` | Definido, único no conjunto | Dois nós com o mesmo id quebram a replicação |
| `binlog_expire_logs_seconds` | ≥ 604800 | Réplica fora do ar por mais tempo exige re-seed |
| `bind-address` | Inclui o IP privado | Só com `127.0.0.1`, nenhuma réplica conecta |
| Usuário `repl` | Existe, host casando com cada réplica | A réplica não autentica |
| `fluxuser@127.0.0.1` | Existe | 8 views, 2 procedures e 2 events falham com `ERROR 1449` |
| Topologia | Cada host responde na 9200 | O HAProxy marca o nó DOWN |

### Somente na réplica

| Item | Esperado | Se divergir |
|---|---|---|
| `server_id` / `server_uuid` | Diferentes do master | Clone não tratado; a replicação recusa conectar |
| `read_only` / `super_read_only` | `ON` | Escrita acidental cria divergência |
| `event_scheduler` | `OFF`, nenhum EVENT `ENABLED` | `staging_cdrs` duplicaria as agregações de CDR |
| `skip_replica_start` | `OFF` | A replicação não sobe após restart |
| `flux-replication.cnf` / `flux-repair.cnf` | Presentes, 0600, root | O reparo automático não consegue agir |
| `Source_Host` | Coerente com o `.cnf` | Vínculo apontando para o servidor errado |
| Serviços do clone | `freeswitch`, `json_cdr`, `event_guard`, `nginx`, `php7.3-fpm` **disabled** | Sobem no boot e duplicam CDR e cobrança |
| Crontab do `flux` | Vazio | O cron dispara jobs contra a URL de produção |

---

## 7. Recuperação manual completa

Quando preferir não usar o script. Na réplica:

```bash
mysql -u root -p -e "STOP REPLICA; RESET REPLICA ALL;"
```

```bash
mysqldump -h <ip-do-master> -P 3306 --protocol=TCP -u fluxuser -p --single-transaction --set-gtid-purged=ON --triggers --routines --events --databases flux > /root/seed.sql
```

```bash
mysql -u root -p -e "SET GLOBAL super_read_only=OFF; SET GLOBAL read_only=OFF; RESET MASTER;"
```

```bash
mysql -u root -p < /root/seed.sql
```

```bash
mysql -u root -p -e "ALTER EVENT flux.staging_cdrs DISABLE; ALTER EVENT flux.remove_cdrs_records DISABLE; SET GLOBAL read_only=ON; SET GLOBAL super_read_only=ON;"
```

```bash
mysql -u root -p -e "CHANGE REPLICATION SOURCE TO SOURCE_HOST='<ip-do-master>', SOURCE_PORT=3306, SOURCE_USER='repl', SOURCE_PASSWORD='<senha>', SOURCE_AUTO_POSITION=1, GET_SOURCE_PUBLIC_KEY=1; START REPLICA;"
```

---

## 8. Salvaguardas do reparo automático

O `flux_replication_repair.sh` pode apagar e recarregar a base da réplica. As proteções:

| Salvaguarda | Comportamento |
|---|---|
| **Papel** | Aborta se o nó não tiver replicação configurada ou não estiver em `super_read_only`. **Nunca roda no master.** |
| **Trava** | `flock` em `/var/lock/flux-replication-repair.lock`; duas execuções no mesmo nó não se sobrepõem |
| **Cooldown** | 6h entre re-seeds (`RESEED_COOLDOWN`), com carimbo em `/var/lib/flux/.last_reseed` |
| **Janela** | `RESEED_WINDOW="01:00-05:00"` restringe o horário; vazio por padrão |
| **Jitter** | Até 60s de espera aleatória, para duas réplicas não puxarem dump ao mesmo tempo |
| **Disco** | Exige 2 GB livres antes de começar |

Estado atual das salvaguardas:

```bash
/opt/flux/misc/flux_replication_repair.sh --status
```

Limitar a escalada, sem nunca re-semear:

```bash
/opt/flux/misc/flux_replication_repair.sh --max-level 2
```

Ignorar cooldown e janela:

```bash
/opt/flux/misc/flux_replication_repair.sh --force
```

### Automação

Na réplica:

```bash
*/5 * * * * /opt/flux/misc/flux_replication_repair.sh --quiet
```

O reparo chama o check internamente, então uma entrada só cobre diagnóstico e correção.
Registros em `/var/log/flux/replication_repair.log`.

> **Credenciais em disco.** A recuperação automática exige
> `/etc/mysql/flux-replication.cnf` (parâmetros e senha de replicação) e
> `/etc/mysql/flux-repair.cnf` (credencial administrativa local), ambos `0600` e
> root-only. Sem eles o script não consegue reconectar a replicação, e o nível 3 aborta.
