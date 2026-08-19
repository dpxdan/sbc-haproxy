# flux-dbcheck — health check por papel

O HAProxy do FluxSBC não pergunta apenas "este nó está vivo?", e sim **"este nó pode
receber escrita?"** e **"este nó pode servir leitura?"**. São perguntas diferentes, e é
o que este agente responde.

Isso é o que permite declarar uma réplica no pool de escrita em caráter permanente: ela
fica DOWN enquanto for `read_only`, e entra sozinha quando for promovida. Um
`option mysql-check` comum não distingue os dois casos e mandaria `INSERT`s para um nó
que os recusa com `ERROR 1290`.

## Endpoints

| Endpoint | Responde 200 quando |
|---|---|
| `/write` | O nó aceita escrita: `read_only=OFF` e `super_read_only=OFF`. Em Galera, exige também `wsrep_ready=ON` e `wsrep_local_state=4`. |
| `/read` | O nó serve leitura: sendo réplica, threads de IO/SQL rodando e lag ≤ `FLUX_DB_READ_MAX_LAG`; sendo primário ou Galera sincronizado, basta responder. |
| `/` | Equivalente a `/write`. |

O corpo da resposta traz o motivo (`read_only=ON`, `lag=87s`, `wsrep_local_state=2`),
que aparece no log do HAProxy e encurta o diagnóstico.

Galera é detectado pela presença de `wsrep_local_state` em `SHOW GLOBAL STATUS` — não
há o que configurar para alternar entre os modos.

## Instalação

Em **todos** os hosts do pool, incluindo o servidor que roda o HAProxy:

```bash
install -m 0755 flux-dbcheck /usr/local/bin/flux-dbcheck
```

```bash
install -m 0644 flux-dbcheck.socket flux-dbcheck@.service /etc/systemd/system/
```

Criar o usuário de check no MySQL do nó:

```sql
CREATE USER IF NOT EXISTS 'haproxy_check'@'localhost';
GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'haproxy_check'@'localhost';
FLUSH PRIVILEGES;
```

`REPLICATION CLIENT` é obrigatório: sem ele o `SHOW REPLICA STATUS` volta vazio e o nó
seria classificado como primário. O usuário não recebe acesso a dado nenhum.

Gravar `/etc/mysql/flux-dbcheck.cnf`:

```ini
[client]
user = haproxy_check
host = 127.0.0.1
port = 3316
protocol = TCP
```

Ajuste `port` para a porta real do MySQL do nó — `3316` nos servidores convertidos pelo
`flux_ha_setup.sh`, `3306` nos demais.

```bash
chown mysql:mysql /etc/mysql/flux-dbcheck.cnf && chmod 600 /etc/mysql/flux-dbcheck.cnf
```

```bash
systemctl daemon-reload && systemctl enable --now flux-dbcheck.socket
```

O `flux_ha_setup.sh` faz tudo isso pela opção **Instalar o agente de health check** do
menu; os passos acima servem para hosts preparados à mão.

## Validação

```bash
curl -i http://127.0.0.1:9200/write
```

```bash
curl -i http://127.0.0.1:9200/read
```

Num primário saudável: 200 nos dois. Numa réplica: **503 em `/write`** — esse é o
resultado correto, não um defeito — e 200 em `/read`.

## Variáveis de ambiente

Definíveis com `systemctl edit flux-dbcheck@.service`:

| Variável | Padrão | Efeito |
|---|---|---|
| `MYSQL_DEFAULTS_FILE` | `/etc/mysql/flux-dbcheck.cnf` | Arquivo de credenciais |
| `FLUX_DB_READ_MAX_LAG` | `30` | Lag máximo, em segundos, para o nó seguir no pool de leitura |
| `AVAILABLE_WHEN_DONOR` | `0` | `1` mantém o nó Galera no pool enquanto atua como Donor |
| `CONNECT_TIMEOUT` | `5` | Timeout de conexão ao MySQL |

## Firewall

A porta 9200 deve ser alcançável **apenas** a partir dos servidores que rodam HAProxy.
