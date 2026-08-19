# Roteiro de implantação — topologia de banco do FluxSBC

Guia de execução, na ordem, para colocar o HAProxy em frente a **múltiplos hosts de
banco**: o MySQL local como master mais uma ou mais réplicas.

Cada passo traz o comando pronto, o resultado esperado e como verificar antes de seguir
adiante. Execute na ordem — **master primeiro, réplicas depois, topologia por último**.

Para o racional da arquitetura, veja [HA-MYSQL-HAPROXY.md](HA-MYSQL-HAPROXY.md) e
[HA-MYSQL-REPLICACAO.md](HA-MYSQL-REPLICACAO.md). Quando algo não funcionar como
descrito aqui, vá para o [diagnóstico](HA-MYSQL-DIAGNOSTICO.md).

---

## Parte 0 — Antes de começar

### Inventário

Anote antes de executar qualquer coisa:

| Papel | Hostname | IP privado | Porta MySQL |
|---|---|---|---|
| Master | fs-1 | 10.0.0.11 | 3316 (local, atrás do HAProxy) |
| Réplica | fs-db-1 | 10.0.0.12 | 3316 |

Os exemplos deste documento usam esses valores. Substitua pelos seus.

### Pré-requisitos

No **master**:

- `flux_ha_setup.sh --enable-local` já executado (MySQL local na 3316, HAProxy na
  3306/3307).
- Código atualizado em `/opt/flux`, contendo `config/healthcheck/` e
  `config/haproxy/flux-mysql.cfg`.

Na **réplica**:

- Servidor instalado (tipicamente um clone do master).
- **Ainda isolado da rede de produção**, se for clone — a Parte 2 explica por quê.

Entre os dois:

- Porta **3306** (MySQL do master) alcançável a partir da réplica.
- Porta **9200** (agente de health check) alcançável a partir do master, em todos os
  hosts do pool.

### Como validar a conectividade

Do master, para cada réplica:

```bash
nc -zv 10.0.0.12 9200
```

Da réplica, para o master:

```bash
nc -zv 10.0.0.11 3306
```

Se estiver usando o `rc.firewall`, preencha `DB_CLIENTS` com os IPs dos servidores que
rodam HAProxy — isso libera 3306, 3307 e 9200 apenas para eles.

---

## Parte 1 — No master (fs-1)

### 1.1 Preparar como source de replicação

Simule primeiro:

```bash
/opt/flux/misc/flux_ha_setup.sh --add-replica --dry-run
```

Depois execute:

```bash
/opt/flux/misc/flux_ha_setup.sh --add-replica
```

Serão pedidos o IP privado deste servidor, o IP da réplica e a senha a definir para o
usuário de replicação. **Anote essa senha** — ela é necessária na Parte 2.

O comando ajusta o `mysqld.cnf` (GTID, `server-id=1`, `binlog_format=ROW`, retenção de
binlog para 7 dias, `bind-address` aberto para a rede privada), reinicia o MySQL, cria
os usuários `repl` e `fluxuser@127.0.0.1`, e gera o dump de seed.

Ao final ele imprime o caminho do seed e o comando pronto para a réplica.

### 1.2 Confirmar o GTID

```bash
mysql -h 127.0.0.1 -P 3316 --protocol=TCP -u root -p -e "SELECT @@GLOBAL.gtid_mode, @@GLOBAL.server_id, @@GLOBAL.binlog_expire_logs_seconds;"
```

Esperado: `ON`, `1` e `604800`.

### 1.3 Instalar o agente de health check

O HAProxy passa a decidir o papel de cada nó consultando este agente — inclusive o do
próprio master.

```bash
/opt/flux/misc/flux_ha_setup.sh --install-agent
```

### 1.4 Verificar o agente

```bash
curl -i http://127.0.0.1:9200/write
```

```bash
curl -i http://127.0.0.1:9200/read
```

Esperado no master: **200 nos dois**, com `no aceita escrita` e `no primario, leitura
local` no corpo.

### 1.5 Auditar a configuração do master

```bash
/opt/flux/misc/flux_ha_setup.sh --verify-config
```

O comando detecta sozinho que este é o master e valida GTID, `bind-address`, usuários de
replicação, portas e arquivos `.conf`. Só siga adiante sem nenhum `FAIL`.

### 1.6 Copiar o seed para a réplica

```bash
scp /var/backups/fluxsbc/ha_setup/<timestamp>/seed_flux_<timestamp>.sql.gz root@10.0.0.12:/root/
```

---

## Parte 2 — Em cada réplica (fs-db-1)

> **Se a réplica é um clone do master, execute esta parte com a VM ainda isolada da rede
> de produção.** Um clone ligado sobe `json_cdr` e `event_guard` no boot (ambos ficam
> `enable` sem `start`), processa CDRs em duplicidade e dispara o cron `crons/index`,
> que faz `wget` contra a **URL de produção** — incluindo `purge/ProcessPurge`, que é
> irreversível. O passo 2.1 desarma tudo isso antes de qualquer outra coisa.

### 2.1 Desarmar o clone e configurar a réplica

Simule primeiro:

```bash
REPL_SOURCE_HOST=10.0.0.11 REPL_PASSWORD='<senha da Parte 1.1>' SEED_DUMP=/root/seed_flux_<timestamp>.sql.gz /opt/flux/misc/flux_ha_setup.sh --make-db-node --dry-run
```

Depois execute:

```bash
REPL_SOURCE_HOST=10.0.0.11 REPL_PASSWORD='<senha da Parte 1.1>' SEED_DUMP=/root/seed_flux_<timestamp>.sql.gz /opt/flux/misc/flux_ha_setup.sh --make-db-node
```

Será exigido que você **digite o hostname desta máquina** para confirmar — proteção
contra rodar o comando no master por engano.

### 2.2 Conferir o desarme

```bash
systemctl is-enabled freeswitch json_cdr event_guard nginx php7.3-fpm
```

Esperado: `disabled` em todas as linhas.

```bash
crontab -l -u flux
```

Esperado: vazio.

```bash
mysql -h 127.0.0.1 -P 3316 --protocol=TCP -u root -p -e "SELECT @@server_uuid, @@server_id, @@GLOBAL.super_read_only, @@GLOBAL.event_scheduler;"
```

Esperado: `server_uuid` **diferente** do master, `server_id` = 2,
`super_read_only` = 1 e `event_scheduler` = `OFF`.

### 2.3 Confirmar a replicação

```bash
/opt/flux/misc/flux_replication_check.sh
```

Esperado: saída toda `[OK]` e código de saída 0.

### 2.4 Instalar o agente de health check

```bash
/opt/flux/misc/flux_ha_setup.sh --install-agent
```

### 2.5 Auditar a configuração da réplica

```bash
/opt/flux/misc/flux_ha_setup.sh --verify-config
```

Valida `read_only`, `event_scheduler`, `server_uuid` distinto do master, o vínculo de
replicação e o desarme do clone.

### 2.6 Verificar o agente

```bash
curl -i http://127.0.0.1:9200/write
```

Esperado: **503**, com `read_only=ON (no de replica; promova antes de receber escrita)`.
Esse 503 é o resultado **correto** — é ele que impede o HAProxy de mandar escrita para
uma réplica.

```bash
curl -i http://127.0.0.1:9200/read
```

Esperado: **200**, com `replica saudavel (lag=Ns)`.

---

## Parte 3 — De volta ao master: declarar a topologia

### 3.1 Aplicar

```bash
FLUX_DB_HOSTS="local,10.0.0.12:3306" /opt/flux/misc/flux_ha_setup.sh --set-topology
```

A lista é ordenada: **o primeiro host é o ativo na escrita**, os demais entram como
`backup`. Todos participam da leitura. A palavra `local` resolve para `127.0.0.1` na
porta do MySQL desta máquina.

Antes de aplicar, o comando sonda a porta 9200 de cada host e **recusa a topologia** se
algum não tiver o agente instalado.

### 3.2 Verificar os pools

```bash
echo "show stat" | socat /run/haproxy/admin.sock stdio | cut -d, -f1,2,18
```

Estado esperado:

| Pool | Servidor | Estado |
|---|---|---|
| `fluxdb_write_pool` | `db_local` | UP |
| `fluxdb_write_pool` | `db_10_0_0_12` | **DOWN** |
| `fluxdb_read_pool` | `db_local` | UP |
| `fluxdb_read_pool` | `db_10_0_0_12` | UP |

A réplica aparecer **DOWN no pool de escrita é o comportamento esperado**, não um erro.
Ela só fica UP ali depois de promovida.

### 3.3 Auditar a topologia completa

```bash
/opt/flux/misc/flux_ha_setup.sh --verify-config
```

No master, a auditoria sonda o agente de **todos** os hosts da topologia e confere que
cada um responde conforme o papel.

### 3.4 Validar em uso real

```bash
/opt/flux/misc/flux_ha_setup.sh --status
```

Depois, faça login na interface web e complete uma chamada tarifada. Confirme que o CDR
entra **uma vez** em `cdrs` e que o débito em `accounts.balance` ocorre **uma vez**.

---

## Parte 4 — Acrescentar outra réplica depois

Não é preciso refazer nada do que já está no ar.

### 4.1 No master, autorizar a nova réplica

```bash
REPL_REPLICA_HOST=10.0.0.13 /opt/flux/misc/flux_ha_setup.sh --add-replica
```

### 4.2 Na nova réplica

Repita a **Parte 2** inteira, apontando `REPL_SOURCE_HOST` para o master.

### 4.3 No master, ampliar a topologia

```bash
FLUX_DB_HOSTS="local,10.0.0.12:3306,10.0.0.13:3306" /opt/flux/misc/flux_ha_setup.sh --set-topology
```

---

## Parte 5 — Operação

### Estado atual

```bash
/opt/flux/misc/flux_ha_setup.sh --status
```

Painel HTTP, apenas em loopback: `http://127.0.0.1:8404/`

### Tirar um nó do pool de leitura para manutenção

```bash
echo "disable server fluxdb_read_pool/db_10_0_0_12" | socat /run/haproxy/admin.sock stdio
```

Devolver ao pool:

```bash
echo "enable server fluxdb_read_pool/db_10_0_0_12" | socat /run/haproxy/admin.sock stdio
```

### Monitorar a replicação continuamente

Na réplica, registre no cron:

```bash
*/5 * * * * /opt/flux/misc/flux_replication_repair.sh --quiet
```

O reparo executa o check internamente e corrige o que puder — religa a replicação
parada e, em caso de divergência confirmada, re-semeia a réplica. As salvaguardas
(cooldown, janela, trava) estão em [HA-MYSQL-DIAGNOSTICO.md](HA-MYSQL-DIAGNOSTICO.md).

Para apenas diagnosticar, sem agir:

```bash
/opt/flux/misc/flux_replication_check.sh --quiet
```

### Desastre: promover a réplica

Só com o master **comprovadamente parado**. Na réplica:

```bash
/opt/flux/misc/flux_ha_setup.sh --promote-replica
```

O agente passa a responder **200 em `/write`** automaticamente, e o HAProxy que apontar
para ela a coloca em serviço **sem precisar editar o `haproxy.cfg`**.

Depois da promoção, reconstrua o servidor antigo **como réplica** antes de religá-lo —
o procedimento está na seção 5.1 de
[HA-MYSQL-REPLICACAO.md](HA-MYSQL-REPLICACAO.md). Religar o antigo master aceitando
escrita enquanto o novo também escreve produz split-brain, sem reconciliação automática
de CDRs nem de saldo.

---

## Referência rápida

| Onde | Comando | Quando |
|---|---|---|
| Master | `--add-replica` | Uma vez por réplica nova |
| Master | `--install-agent` | Uma vez |
| Master | `--set-topology` | Sempre que a lista de hosts mudar |
| Réplica | `--make-db-node` | Uma vez, na conversão |
| Réplica | `--install-agent` | Uma vez |
| Réplica | `flux_replication_repair.sh --quiet` | Contínuo, via cron |
| Ambos | `--verify-config` | Ao final de cada parte e ao investigar |
| Réplica | `--promote-replica` | Apenas em desastre |

### Estado esperado por papel

| Endpoint | Master | Réplica |
|---|---|---|
| `/write` | 200 | **503** |
| `/read` | 200 | 200 (503 se o lag passar de 30s) |
