# clustercheck — health check do HAProxy nos nós Galera

Estes arquivos são instalados **nos nós do cluster Galera**, não no servidor FluxSBC.
O HAProxy do SBC consulta a porta 9200 de cada nó para decidir se ele pode receber tráfego.

O check responde `200` apenas quando o nó está de fato apto: `wsrep_ready=ON`,
`wsrep_local_state=4` (Synced) e `read_only=OFF`. Um nó em SST ou Donor responde `503`
e é retirado do balanceamento — é justamente isso que o `mysql-check` nativo do HAProxy
não consegue distinguir.

## Instalação em cada nó Galera

```bash
install -m 0755 clustercheck /usr/local/bin/clustercheck
install -m 0644 galera-clustercheck.socket /etc/systemd/system/
install -m 0644 galera-clustercheck@.service /etc/systemd/system/
```

Criar o usuário de check no cluster (basta em um nó, replica para os demais):

```sql
CREATE USER 'clustercheck'@'localhost' IDENTIFIED BY 'senha-do-check';
GRANT PROCESS ON *.* TO 'clustercheck'@'localhost';
FLUSH PRIVILEGES;
```

Gravar as credenciais em `/etc/mysql/clustercheck.cnf`:

```ini
[client]
user = clustercheck
password = senha-do-check
host = localhost
```

```bash
chown mysql:mysql /etc/mysql/clustercheck.cnf
chmod 600 /etc/mysql/clustercheck.cnf
systemctl daemon-reload
systemctl enable --now galera-clustercheck.socket
```

## Validação

```bash
curl -i http://127.0.0.1:9200/
```

`200 OK` com `Galera node ready` indica nó sincronizado. Durante um SST a resposta
deve mudar para `503`.

## Variáveis de ambiente

Podem ser definidas via `systemctl edit galera-clustercheck@.service`:

| Variável | Padrão | Efeito |
|---|---|---|
| `MYSQL_DEFAULTS_FILE` | `/etc/mysql/clustercheck.cnf` | Arquivo de credenciais |
| `AVAILABLE_WHEN_DONOR` | `0` | `1` mantém o nó no pool enquanto atua como Donor |
| `AVAILABLE_WHEN_READONLY` | `0` | `1` aceita nó em `read_only` (use apenas no backend de leitura) |
| `CONNECT_TIMEOUT` | `5` | Timeout de conexão ao MySQL, em segundos |

## Firewall

A porta 9200 deve ser alcançável **apenas** a partir dos IPs dos servidores FluxSBC
que rodam HAProxy.
