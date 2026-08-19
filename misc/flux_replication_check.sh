#!/usr/bin/env bash
# Flux SBC - Unindo pessoas e negócios
# Copyright (C) 2026 Flux Telecom
# Daniel Paixao <daniel@flux.net.br>
# FluxSBC Version 6.4 and above
# License https://www.gnu.org/licenses/agpl-3.0.html
# Verifica a saude da replicacao MySQL neste no
# Testado em: Debian 11 (Bullseye)
#
# Uso:
#   ./flux_replication_check.sh                    verificacao padrao
#   ./flux_replication_check.sh --threshold 120    limiar de lag em segundos
#   ./flux_replication_check.sh --quiet            so registra em log
#   ./flux_replication_check.sh --compare          compara contagens com o source
#
# Codigos de saida: 0 saudavel, 1 degradado, 2 erro de configuracao,
#                   3 divergencia confirmada (transacoes descartadas)
set -uo pipefail

FLUXDIR="${FLUXDIR:-/var/lib/flux}"
FLUX_CONF="${FLUX_CONF:-${FLUXDIR}/flux-config.conf}"
MYSQLD_CNF="${MYSQLD_CNF:-/etc/mysql/mysql.conf.d/mysqld.cnf}"
FLUXLOGDIR="${FLUXLOGDIR:-/var/log/flux}"
LOG_FILE="${LOG_FILE:-${FLUXLOGDIR}/replication_check.log}"

LAG_THRESHOLD="${LAG_THRESHOLD:-60}"
QUIET=0
COMPARE=0
MYSQL_CNF=""
EXIT_CODE=0

if [[ -t 1 ]]; then
    RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; NC=$'\033[0m'
else
    RED=""; GREEN=""; YELLOW=""; NC=""
fi

log()
{
    local level="$1"; shift
    local color=""
    case "$level" in
        OK)   color="$GREEN" ;;
        WARN) color="$YELLOW" ;;
        FAIL) color="$RED" ;;
    esac

    if [[ $QUIET -eq 0 ]]; then
        echo "${color}[${level}]${NC} $*"
    fi
    if [[ -d "$FLUXLOGDIR" ]]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') [${level}] $*" >> "$LOG_FILE" 2>/dev/null || true
    fi
}

fail()  { log FAIL "$@"; if [[ $EXIT_CODE -lt 1 ]]; then EXIT_CODE=1; fi; }
diverged() { log FAIL "$@"; EXIT_CODE=3; }
warn()  { log WARN "$@"; if [[ $EXIT_CODE -eq 0 ]]; then EXIT_CODE=1; fi; }

cleanup()
{
    if [[ -n "${MYSQL_CNF:-}" && -f "$MYSQL_CNF" ]]; then
        rm -f "$MYSQL_CNF"
    fi
}
trap cleanup EXIT

show_help()
{
    cat <<EOF
FluxSBC - verificacao de replicacao MySQL

Uso: $0 [opcoes]

  --threshold N   Limiar de lag em segundos (padrao ${LAG_THRESHOLD})
  --quiet         Nao escreve na saida padrao, apenas em ${LOG_FILE}
  --compare       Compara contagem de accounts e cdrs com o servidor source
  -h, --help      Esta ajuda

Codigos de saida:
  0  replicacao saudavel
  1  replicacao degradada ou parada
  2  erro de configuracao (credenciais, MySQL inacessivel)
  3  divergencia confirmada: o master tem transacoes que esta replica
     descartou. Nao se resolve sozinha; exige re-seed.

Registrar no cron do no de banco:
  */5 * * * * /opt/flux/misc/flux_replication_check.sh --quiet
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --threshold) shift; LAG_THRESHOLD="${1:-60}" ;;
        --quiet)     QUIET=1 ;;
        --compare)   COMPARE=1 ;;
        -h|--help)   show_help; exit 0 ;;
        *)           echo "Opcao desconhecida: $1"; show_help; exit 2 ;;
    esac
    shift
done

trim()
{
    local s="$1"
    s="${s#"${s%%[![:space:]]*}"}"
    s="${s%"${s##*[![:space:]]}"}"
    printf '%s' "$s"
}

read_conf_value()
{
    local key="$1"
    local file="$2"
    local value
    value=$(grep -Po "^\s*${key}\s*=\s*\K.*" "$file" 2>/dev/null | head -n1 || true)
    trim "$value"
}

if [[ ! -r "$FLUX_CONF" ]]; then
    log FAIL "Nao foi possivel ler ${FLUX_CONF}"
    exit 2
fi

DB_USER=$(read_conf_value dbuser "$FLUX_CONF")
DB_PASS=$(read_conf_value dbpass "$FLUX_CONF")
DB_NAME=$(read_conf_value dbname "$FLUX_CONF")
DB_PORT="${DB_PORT:-$(read_conf_value port "$MYSQLD_CNF")}"
DB_PORT="${DB_PORT:-3306}"

MYSQL_ADMIN_USER="${MYSQL_ADMIN_USER:-}"
MYSQL_ADMIN_PASSWORD="${MYSQL_ADMIN_PASSWORD:-}"

if [[ -n "$MYSQL_ADMIN_USER" ]]; then
    CONN_USER="$MYSQL_ADMIN_USER"
    CONN_PASS="$MYSQL_ADMIN_PASSWORD"
else
    CONN_USER="$DB_USER"
    CONN_PASS="$DB_PASS"
fi

if [[ -z "$CONN_USER" || -z "$CONN_PASS" ]]; then
    log FAIL "Credenciais de banco incompletas."
    exit 2
fi

MYSQL_CNF=$(mktemp)
chmod 600 "$MYSQL_CNF"
cat > "$MYSQL_CNF" <<EOF
[client]
host = 127.0.0.1
port = ${DB_PORT}
protocol = TCP
user = ${CONN_USER}
password = '${CONN_PASS}'
EOF

if ! mysql --defaults-extra-file="$MYSQL_CNF" -e "SELECT 1;" >/dev/null 2>&1; then
    log FAIL "MySQL inacessivel em 127.0.0.1:${DB_PORT} como ${CONN_USER}."
    exit 2
fi

MYSQL_VERSION=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT VERSION();" 2>/dev/null)
STATUS_STMT="SHOW REPLICA STATUS"
FIELD_IO="Replica_IO_Running"
FIELD_SQL="Replica_SQL_Running"
FIELD_LAG="Seconds_Behind_Source"

case "$MYSQL_VERSION" in
    8.0.1?|8.0.2[0-2]*|5.*)
        STATUS_STMT="SHOW SLAVE STATUS"
        FIELD_IO="Slave_IO_Running"
        FIELD_SQL="Slave_SQL_Running"
        FIELD_LAG="Seconds_Behind_Master"
        ;;
esac

STATUS=$(mysql --defaults-extra-file="$MYSQL_CNF" -e "${STATUS_STMT}\G" 2>/dev/null)

if [[ -z "$STATUS" ]]; then
    STATUS=$(mysql --defaults-extra-file="$MYSQL_CNF" -e "SHOW SLAVE STATUS\G" 2>/dev/null)
    FIELD_IO="Slave_IO_Running"
    FIELD_SQL="Slave_SQL_Running"
    FIELD_LAG="Seconds_Behind_Master"
fi

if [[ -z "$STATUS" ]]; then
    log FAIL "Este no nao esta configurado como replica (${STATUS_STMT} vazio)."
    log FAIL "Se este e o servidor primario, esta verificacao nao se aplica."
    exit 2
fi

field()
{
    echo "$STATUS" | grep -E "^\s*$1:" | head -n1 | cut -d: -f2- | sed -e 's/^[[:space:]]*//'
}

IO_RUNNING=$(field "$FIELD_IO")
SQL_RUNNING=$(field "$FIELD_SQL")
LAG=$(field "$FIELD_LAG")
IO_ERROR=$(field "Last_IO_Error")
SQL_ERROR=$(field "Last_SQL_Error")
SOURCE_HOST=$(field "Source_Host")
[[ -z "$SOURCE_HOST" ]] && SOURCE_HOST=$(field "Master_Host")
SOURCE_PORT=$(field "Source_Port")
[[ -z "$SOURCE_PORT" ]] && SOURCE_PORT=$(field "Master_Port")
RETRIEVED=$(field "Retrieved_Gtid_Set")
EXECUTED=$(field "Executed_Gtid_Set")

log OK "Source: ${SOURCE_HOST:-desconhecido} | MySQL ${MYSQL_VERSION}"

if [[ "$IO_RUNNING" == "Yes" ]]; then
    log OK "Thread de IO em execucao."
else
    fail "Thread de IO parada (${FIELD_IO}=${IO_RUNNING:-?})."
fi

if [[ "$SQL_RUNNING" == "Yes" ]]; then
    log OK "Thread de SQL em execucao."
else
    fail "Thread de SQL parada (${FIELD_SQL}=${SQL_RUNNING:-?})."
fi

if [[ -n "$IO_ERROR" ]]; then
    fail "Last_IO_Error: ${IO_ERROR}"
fi

if [[ -n "$SQL_ERROR" ]]; then
    fail "Last_SQL_Error: ${SQL_ERROR}"
fi

if [[ "$LAG" == "NULL" || -z "$LAG" ]]; then
    fail "Lag indisponivel (replicacao provavelmente parada)."
elif [[ "$LAG" -gt "$LAG_THRESHOLD" ]]; then
    warn "Lag de ${LAG}s acima do limiar de ${LAG_THRESHOLD}s."
else
    log OK "Lag de ${LAG}s (limiar ${LAG_THRESHOLD}s)."
fi

if [[ -n "$RETRIEVED" && "$RETRIEVED" != "$EXECUTED" ]]; then
    log WARN "Ha GTIDs recebidos ainda nao aplicados (normal sob carga, persistente indica atraso)."
fi

# Divergência silenciosa: o master emite transacoes que esta replica considera
# ja aplicadas (tipico de clone cuja base foi conectada sem seed). As threads
# ficam Yes e o lag zero, mas os dados nunca chegam.
check_divergence()
{
    local gtid_mode
    gtid_mode=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT @@GLOBAL.gtid_mode;" 2>/dev/null)

    if [[ "$gtid_mode" != "ON" ]]; then
        log WARN "gtid_mode=${gtid_mode:-desconhecido}: verificacao de divergencia ignorada."
        return 0
    fi

    if [[ -z "$SOURCE_HOST" ]]; then
        log WARN "Sem Source_Host: verificacao de divergencia ignorada."
        return 0
    fi

    local master_gtid
    master_gtid=$(mysql -h "$SOURCE_HOST" -P "${SOURCE_PORT:-3306}" --protocol=TCP \
        -u"$DB_USER" -p"$DB_PASS" -N -B -e "SELECT @@GLOBAL.gtid_executed;" 2>/dev/null | tr -d '\n')

    if [[ -z "$master_gtid" ]]; then
        log WARN "Nao foi possivel ler o gtid_executed do master ${SOURCE_HOST}."
        return 0
    fi

    local missing
    missing=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B \
        -e "SELECT GTID_SUBTRACT('${master_gtid}', @@GLOBAL.gtid_executed);" 2>/dev/null | tr -d '\n')

    if [[ -z "$missing" ]]; then
        log OK "Nenhuma transacao pendente do master."
        return 0
    fi

    if [[ "${LAG:-0}" != "0" ]]; then
        log WARN "Transacoes pendentes com lag=${LAG}s: atraso normal, a replica esta aplicando."
        return 0
    fi

    log WARN "Transacoes do master ausentes com lag zero; reamostrando em 5s..."
    sleep 5

    missing=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B \
        -e "SELECT GTID_SUBTRACT('${master_gtid}', @@GLOBAL.gtid_executed);" 2>/dev/null | tr -d '\n')

    if [[ -z "$missing" ]]; then
        log OK "Pendencia aplicada na reamostragem; replicacao saudavel."
        return 0
    fi

    diverged "DIVERGENCIA CONFIRMADA: o master tem transacoes que esta replica nao aplicou"
    log FAIL "GTIDs ausentes: ${missing}"
    log FAIL "As threads estao rodando e o lag e zero: as transacoes foram descartadas."
    log FAIL "Causa tipica: replica conectada sem seed, herdando o gtid_executed do clone."
    log FAIL "Correcao: /opt/flux/misc/flux_replication_repair.sh"
}

check_divergence

READ_ONLY=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT @@GLOBAL.super_read_only;" 2>/dev/null)
if [[ "$READ_ONLY" == "1" ]]; then
    log OK "No protegido contra escrita (super_read_only=ON)."
else
    warn "super_read_only esta OFF neste no de replica: risco de escrita acidental."
fi

EVENT_SCHED=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT @@GLOBAL.event_scheduler;" 2>/dev/null)
if [[ "$EVENT_SCHED" == "OFF" ]]; then
    log OK "event_scheduler desligado."
else
    warn "event_scheduler=${EVENT_SCHED}: os EVENTs staging_cdrs/remove_cdrs_records podem duplicar agregacoes."
fi

if [[ $COMPARE -eq 1 ]]; then
    if [[ -z "$SOURCE_HOST" ]]; then
        warn "Sem Source_Host para comparar contagens."
    else
        for tbl in accounts cdrs; do
            local_count=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.\`${tbl}\`;" 2>/dev/null)
            remote_count=$(mysql -h "$SOURCE_HOST" --protocol=TCP -u"$DB_USER" -p"$DB_PASS" -N -B \
                -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.\`${tbl}\`;" 2>/dev/null)

            if [[ -z "$remote_count" ]]; then
                warn "Nao foi possivel consultar ${tbl} no source ${SOURCE_HOST}."
            elif [[ "$local_count" == "$remote_count" ]]; then
                log OK "${tbl}: ${local_count} linhas em ambos."
            else
                warn "${tbl}: local ${local_count} vs source ${remote_count} (diferenca esperada sob carga)."
            fi
        done
    fi
fi

if [[ $EXIT_CODE -eq 0 ]]; then
    log OK "Replicacao saudavel."
elif [[ $EXIT_CODE -eq 3 ]]; then
    log FAIL "Replicacao divergente. Rode: /opt/flux/misc/flux_replication_repair.sh"
else
    log FAIL "Replicacao degradada. Verifique ${LOG_FILE}"
fi

exit $EXIT_CODE
