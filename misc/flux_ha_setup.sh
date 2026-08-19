#!/usr/bin/env bash
# Flux SBC - Unindo pessoas e negócios
# Copyright (C) 2026 Flux Telecom
# Daniel Paixao <daniel@flux.net.br>
# FluxSBC Version 6.4 and above
# License https://www.gnu.org/licenses/agpl-3.0.html
# Habilita a camada HAProxy para MySQL em um FluxSBC ja instalado
# Testado em: Debian 11 (Bullseye)
#
# Uso:
#   ./flux_ha_setup.sh                      menu interativo
#   ./flux_ha_setup.sh --enable-local       etapa 1: HAProxy sobre o MySQL local
#   ./flux_ha_setup.sh --migrate-galera     etapa 2: reponta para um cluster Galera
#   ./flux_ha_setup.sh --status             mostra o estado atual
#   ./flux_ha_setup.sh --rollback           restaura o ultimo snapshot
#   ./flux_ha_setup.sh --backup-only        apenas gera o snapshot
#   ./flux_ha_setup.sh --enable-local --dry-run
set -euo pipefail

#############################
#  Configuracao
#############################

FLUX_SOURCE_DIR="${FLUX_SOURCE_DIR:-/opt/flux}"
FLUXDIR="${FLUXDIR:-/var/lib/flux}"
FLUXLOGDIR="${FLUXLOGDIR:-/var/log/flux}"

FLUX_CONF="${FLUX_CONF:-${FLUXDIR}/flux-config.conf}"
FLUX_LUA="${FLUX_LUA:-${FLUXDIR}/flux.lua}"
ODBC_INI="${ODBC_INI:-/etc/odbc.ini}"
MYSQLD_CNF="${MYSQLD_CNF:-/etc/mysql/mysql.conf.d/mysqld.cnf}"
HAPROXY_CFG="${HAPROXY_CFG:-/etc/haproxy/haproxy.cfg}"
HAPROXY_TPL="${HAPROXY_TPL:-${FLUX_SOURCE_DIR}/config/haproxy/flux-mysql.cfg}"
FS_VARS="${FS_VARS:-/etc/freeswitch/vars.xml}"
FS_NIBBLEBILL="${FS_NIBBLEBILL:-/etc/freeswitch/autoload_configs/nibblebill.conf.xml}"
DEBIAN_RELEASE_FILE="${DEBIAN_RELEASE_FILE:-/etc/debian_version}"

DB_WRITE_PORT="${DB_WRITE_PORT:-3306}"
DB_READ_PORT="${DB_READ_PORT:-3307}"
DB_LOCAL_PORT="${DB_LOCAL_PORT:-3316}"
DB_STATS_PORT="${DB_STATS_PORT:-8404}"
DB_CHECK_PORT="${DB_CHECK_PORT:-9200}"
HAPROXY_CHECK_USER="haproxy_check"

REPL_USER="${REPL_USER:-repl}"
REPL_SOURCE_ID="${REPL_SOURCE_ID:-1}"
REPL_REPLICA_ID="${REPL_REPLICA_ID:-2}"
REPL_BINLOG_EXPIRE="${REPL_BINLOG_EXPIRE:-604800}"
FS_CDR_SPOOL="${FS_CDR_SPOOL:-/var/lib/freeswitch/cdr}"
MYSQL_DATADIR="${MYSQL_DATADIR:-/var/lib/mysql}"
CRONTAB_FILE="${CRONTAB_FILE:-/var/spool/cron/crontabs/flux}"

CLONE_SERVICES="freeswitch json_cdr event_guard nginx php7.3-fpm fail2ban haproxy"

FLUX_DB_HOSTS="${FLUX_DB_HOSTS:-}"
FLUX_DB_READ_MAX_LAG="${FLUX_DB_READ_MAX_LAG:-30}"
TOPOLOGY_FILE="${TOPOLOGY_FILE:-${FLUXDIR}/ha-topology.conf}"
DBCHECK_BIN="${DBCHECK_BIN:-/usr/local/bin/flux-dbcheck}"
DBCHECK_CNF="${DBCHECK_CNF:-/etc/mysql/flux-dbcheck.cnf}"
DBCHECK_SRC="${DBCHECK_SRC:-${FLUX_SOURCE_DIR}/config/healthcheck}"
SYSTEMD_DIR="${SYSTEMD_DIR:-/etc/systemd/system}"
REPL_CNF="${REPL_CNF:-/etc/mysql/flux-replication.cnf}"
REPAIR_CNF="${REPAIR_CNF:-/etc/mysql/flux-repair.cnf}"

BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/fluxsbc/ha_setup}"
TIMESTAMP=$(date "+%Y%m%d_%H%M%S")
BACKUP_DIR=""
LOG_FILE="${FLUXLOGDIR}/flux_ha_${TIMESTAMP}.log"

DRY_RUN=0
ACTION=""

MYSQL_CNF=""
declare -a BACKED_UP_FILES=()
STATE_BACKUP_INIT=0

#############################
#  Saida
#############################

if [[ -t 1 ]]; then
    RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; BLUE=$'\033[0;34m'; NC=$'\033[0m'
else
    RED=""; GREEN=""; YELLOW=""; BLUE=""; NC=""
fi

_log()
{
    local line="$1"
    echo "$line"
    if [[ -d "$FLUXLOGDIR" ]]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - $(echo "$line" | sed -e 's/\x1b\[[0-9;]*m//g')" >> "$LOG_FILE" 2>/dev/null || true
    fi
}

log_info()  { _log "${BLUE}[INFO]${NC}  $*"; }
log_ok()    { _log "${GREEN}[OK]${NC}    $*"; }
log_warn()  { _log "${YELLOW}[WARN]${NC}  $*"; }
log_error() { _log "${RED}[ERROR]${NC} $*"; }
log_dry()   { _log "${YELLOW}[DRY]${NC}   $*"; }

log_section()
{
    _log ""
    _log "${BLUE}══════════════════════════════════════════════════════════════${NC}"
    _log "${BLUE} $*${NC}"
    _log "${BLUE}══════════════════════════════════════════════════════════════${NC}"
}

run()
{
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "$*"
        return 0
    fi
    "$@"
}

confirm()
{
    local prompt="$1"
    local answer
    read -rp "${prompt} [s/N]: " answer
    [[ "$answer" =~ ^[sSyY]$ ]]
}

#############################
#  Helpers de configuracao
#############################

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

build_mysql_cnf()
{
    local host="$1" port="$2" user="$3" pass="$4"

    MYSQL_CNF=$(mktemp)
    chmod 600 "$MYSQL_CNF"
    cat > "$MYSQL_CNF" <<EOF
[client]
host = ${host}
port = ${port}
protocol = TCP
user = ${user}
password = '${pass}'
EOF
}

cleanup()
{
    if [[ -n "${MYSQL_CNF:-}" && -f "$MYSQL_CNF" ]]; then
        rm -f "$MYSQL_CNF"
    fi
}
trap cleanup EXIT

mysql_run()
{
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "mysql --defaults-extra-file=*** $*"
        return 0
    fi
    mysql --defaults-extra-file="$MYSQL_CNF" "$@"
}

#############################
#  Backup
#############################

init_backup_dir()
{
    if [[ $STATE_BACKUP_INIT -eq 1 ]]; then
        return 0
    fi

    BACKUP_DIR="${BACKUP_ROOT}/${TIMESTAMP}"
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "install -d -m 0700 ${BACKUP_DIR}"
    else
        install -d -m 0700 -o root -g root "$BACKUP_DIR"
    fi
    STATE_BACKUP_INIT=1
    log_info "Snapshot: ${BACKUP_DIR}"
}

backup_file()
{
    local src="$1"
    local dst

    if [[ ! -f "$src" ]]; then
        return 0
    fi

    local f
    for f in "${BACKED_UP_FILES[@]}"; do
        if [[ "$f" == "$src" ]]; then
            return 0
        fi
    done

    init_backup_dir
    dst="${BACKUP_DIR}${src}"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "cp -p ${src} ${dst}"
    else
        install -d -m 0700 "$(dirname "$dst")"
        cp -p "$src" "$dst"
    fi

    BACKED_UP_FILES+=("$src")
    log_ok "Backup de ${src}"
}

backup_database()
{
    local host="$1" port="$2" dbname="$3"
    local dump="${BACKUP_DIR}/${dbname}_${TIMESTAMP}.sql.gz"

    init_backup_dir
    log_info "Gerando dump de seguranca de ${dbname} (${host}:${port})"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "mysqldump --defaults-extra-file=*** --single-transaction --databases ${dbname} | gzip > ${dump}"
        return 0
    fi

    if ! mysqldump --defaults-extra-file="$MYSQL_CNF" --single-transaction --databases "$dbname" | gzip > "$dump"; then
        log_error "Falha ao gerar o dump de seguranca."
        rm -f "$dump"
        return 1
    fi

    chmod 600 "$dump"
    log_ok "Dump gravado em ${dump} ($(du -h "$dump" | cut -f1))"
}

write_backup_manifest()
{
    if [[ $STATE_BACKUP_INIT -eq 0 || $DRY_RUN -eq 1 ]]; then
        return 0
    fi

    if [[ ${#BACKED_UP_FILES[@]} -eq 0 ]] && ! ls "${BACKUP_DIR}"/*.sql.gz >/dev/null 2>&1; then
        rmdir "$BACKUP_DIR" 2>/dev/null || true
        return 0
    fi

    local manifest="${BACKUP_DIR}/MANIFEST.txt"
    {
        echo "FluxSBC - snapshot de configuracao (HAProxy/MySQL)"
        echo "Data:    $(date '+%Y-%m-%d %H:%M:%S')"
        echo "Host:    $(hostname)"
        echo "Script:  $0"
        echo "Total:   ${#BACKED_UP_FILES[@]} arquivo(s)"
        echo ""
        echo "Arquivos:"
        local f
        for f in "${BACKED_UP_FILES[@]}"; do
            echo "  ${f}"
        done
        echo ""
        echo "Restauracao completa:"
        echo "  cp -a ${BACKUP_DIR}/. /"
        echo "  systemctl restart mysql php7.3-fpm nginx"
        echo ""
        echo "Restauracao seletiva:"
        echo "  cp -p ${BACKUP_DIR}/etc/odbc.ini /etc/odbc.ini"
        echo ""
        echo "Ou use: $0 --rollback"
    } > "$manifest"
    chmod 600 "$manifest"
    log_ok "Manifesto gravado em ${manifest}"
}

#############################
#  Pre-requisitos
#############################

check_root()
{
    if [[ $EUID -ne 0 ]]; then
        if [[ $DRY_RUN -eq 1 ]]; then
            log_warn "Sem privilegios de root (aceitavel em dry-run)."
            return 0
        fi
        log_error "Este script precisa ser executado como root."
        exit 1
    fi
}

check_debian()
{
    if [[ ! -f "$DEBIAN_RELEASE_FILE" ]]; then
        log_error "Este script suporta apenas Debian."
        exit 1
    fi
    log_ok "Debian detectado: $(cat "$DEBIAN_RELEASE_FILE")"
}

check_flux_install()
{
    local missing=0

    if [[ ! -r "$FLUX_CONF" ]]; then
        log_error "Nao encontrado: ${FLUX_CONF}"
        missing=1
    fi

    if [[ ! -f "$ODBC_INI" ]]; then
        log_error "Nao encontrado: ${ODBC_INI}"
        missing=1
    fi

    if [[ ! -f "$HAPROXY_TPL" ]]; then
        log_error "Template do HAProxy ausente: ${HAPROXY_TPL}"
        log_error "Atualize o codigo em ${FLUX_SOURCE_DIR} antes de rodar este script."
        missing=1
    fi

    local db_lua="${FLUX_SOURCE_DIR}/freeswitch/scripts/flux/lib/pbx_scripts/db.lua"
    if [[ ! -f "$db_lua" ]] || ! grep -q "ODBC_DSN_RO" "$db_lua"; then
        log_error "O codigo em ${FLUX_SOURCE_DIR} nao tem o split de leitura/escrita (ODBC_DSN_RO em db.lua)."
        log_error "Atualize o repositorio antes de habilitar o HAProxy."
        missing=1
    fi

    if [[ $missing -eq 1 ]]; then
        exit 1
    fi

    log_ok "Instalacao do FluxSBC validada."
}

port_in_use()
{
    local port="$1"
    ss -lnt 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${port}\$"
}

check_ports()
{
    local port
    for port in "$DB_READ_PORT" "$DB_LOCAL_PORT" "$DB_STATS_PORT"; do
        if port_in_use "$port"; then
            log_error "Porta ${port} ja esta em uso. Libere-a ou ajuste as variaveis do script."
            exit 1
        fi
    done
    log_ok "Portas ${DB_READ_PORT}, ${DB_LOCAL_PORT} e ${DB_STATS_PORT} disponiveis."
}

load_db_credentials()
{
    DB_HOST=$(read_conf_value dbhost "$FLUX_CONF")
    DB_NAME=$(read_conf_value dbname "$FLUX_CONF")
    DB_USER=$(read_conf_value dbuser "$FLUX_CONF")
    DB_PASS=$(read_conf_value dbpass "$FLUX_CONF")

    DB_HOST="${DB_HOST:-127.0.0.1}"

    if [[ -z "$DB_NAME" || -z "$DB_USER" || -z "$DB_PASS" ]]; then
        log_error "Credenciais incompletas em ${FLUX_CONF} (dbname=${DB_NAME:-vazio}, dbuser=${DB_USER:-vazio}, dbpass=${DB_PASS:+definida})."
        exit 1
    fi

    log_ok "Credenciais lidas de ${FLUX_CONF} (banco: ${DB_NAME})."
}

#############################
#  Helpers de MySQL
#############################

set_cnf_value()
{
    local key="$1"
    local value="$2"
    local file="$3"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "${file}: ${key} = ${value}"
        return 0
    fi

    if grep -qE "^\s*${key}\s*=" "$file"; then
        sed -i "s#^\s*${key}\s*=.*#${key} = ${value}#" "$file"
    else
        printf '%s = %s\n' "$key" "$value" >> "$file"
    fi
}

build_mysql_admin_cnf()
{
    local port="$1"
    local user="${MYSQL_ADMIN_USER:-root}"
    local pass="${MYSQL_ADMIN_PASSWORD:-}"

    if [[ -z "$pass" ]]; then
        if [[ ! -t 0 ]]; then
            log_error "MYSQL_ADMIN_PASSWORD nao definida e sem terminal para solicitar."
            return 1
        fi
        read -rsp "Senha do usuario ${user} no MySQL local: " pass
        echo ""
        MYSQL_ADMIN_PASSWORD="$pass"
    fi

    build_mysql_cnf "127.0.0.1" "$port" "$user" "$pass"

    if [[ $DRY_RUN -eq 0 ]] && ! mysql --defaults-extra-file="$MYSQL_CNF" -e "SELECT 1;" >/dev/null 2>&1; then
        log_error "Nao foi possivel autenticar como ${user} em 127.0.0.1:${port}."
        return 1
    fi
    return 0
}

mysql_local_port()
{
    if haproxy_already_enabled; then
        echo "$DB_LOCAL_PORT"
    else
        echo "3306"
    fi
}

mysql_local_cnf()
{
    local port="$DB_LOCAL_PORT"
    if ! haproxy_already_enabled; then
        port="3306"
    fi
    build_mysql_cnf "127.0.0.1" "$port" "$DB_USER" "$DB_PASS"
}

mysql_supports_source_syntax()
{
    local version
    version=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT VERSION();" 2>/dev/null | head -n1)

    if [[ -z "$version" ]]; then
        return 1
    fi

    local major minor patch
    major=$(echo "$version" | cut -d. -f1)
    minor=$(echo "$version" | cut -d. -f2)
    patch=$(echo "$version" | cut -d. -f3 | tr -cd '0-9')

    if [[ ${major:-0} -gt 8 ]]; then
        return 0
    fi
    if [[ ${major:-0} -eq 8 && ${minor:-0} -eq 0 && ${patch:-0} -ge 23 ]]; then
        return 0
    fi
    if [[ ${major:-0} -eq 8 && ${minor:-0} -gt 0 ]]; then
        return 0
    fi
    return 1
}

replica_status_field()
{
    local field="$1"
    local stmt="SHOW REPLICA STATUS"

    if ! mysql_supports_source_syntax; then
        stmt="SHOW SLAVE STATUS"
    fi

    mysql --defaults-extra-file="$MYSQL_CNF" -e "${stmt}\G" 2>/dev/null \
        | grep -E "^\s*${field}:" | head -n1 | cut -d: -f2- | sed -e 's/^[[:space:]]*//'
}

wait_mysql_ready()
{
    local port="$1"
    local tries=0

    if [[ $DRY_RUN -eq 1 ]]; then
        return 0
    fi

    until mysqladmin --defaults-extra-file="$MYSQL_CNF" --port="$port" ping >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [[ $tries -ge 30 ]]; then
            log_error "MySQL nao respondeu na porta ${port} apos o restart."
            return 1
        fi
        sleep 1
    done
    return 0
}

#############################
#  Etapa 1: HAProxy local
#############################

haproxy_already_enabled()
{
    [[ -f "$HAPROXY_CFG" ]] && grep -q "fluxdb_write" "$HAPROXY_CFG"
}

install_haproxy_packages()
{
    if dpkg -l haproxy 2>/dev/null | grep -q '^ii' && dpkg -l socat 2>/dev/null | grep -q '^ii'; then
        log_ok "haproxy e socat ja instalados."
        return 0
    fi

    log_info "Instalando haproxy e socat..."
    run apt-get update
    run apt-get install -y haproxy socat
}

move_mysql_port()
{
    local current
    current=$(read_conf_value port "$MYSQLD_CNF")

    if [[ "$current" == "$DB_LOCAL_PORT" ]]; then
        log_ok "MySQL local ja escuta na porta ${DB_LOCAL_PORT}."
        return 0
    fi

    if [[ -n "$current" ]]; then
        log_warn "MySQL local configurado na porta ${current}; sera movido para ${DB_LOCAL_PORT}."
    fi

    backup_file "$MYSQLD_CNF"

    log_info "Movendo o MySQL local para a porta ${DB_LOCAL_PORT}..."
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "sed/append port = ${DB_LOCAL_PORT} em ${MYSQLD_CNF}"
    elif [[ -n "$current" ]]; then
        sed -i "s#^\s*port\s*=.*#port = ${DB_LOCAL_PORT}#" "$MYSQLD_CNF"
    else
        sed -i "/^bind-address/i port                            = ${DB_LOCAL_PORT}" "$MYSQLD_CNF"
        if ! grep -qE "^\s*port\s*=" "$MYSQLD_CNF"; then
            echo "port = ${DB_LOCAL_PORT}" >> "$MYSQLD_CNF"
        fi
    fi

    run systemctl restart mysql

    if [[ $DRY_RUN -eq 0 ]]; then
        local tries=0
        until mysqladmin --defaults-extra-file="$MYSQL_CNF" --port="$DB_LOCAL_PORT" ping >/dev/null 2>&1; do
            tries=$((tries + 1))
            if [[ $tries -ge 15 ]]; then
                log_error "MySQL nao respondeu na porta ${DB_LOCAL_PORT} apos o restart."
                log_error "Restaure com: cp -p ${BACKUP_DIR}${MYSQLD_CNF} ${MYSQLD_CNF} && systemctl restart mysql"
                exit 1
            fi
            sleep 1
        done
    fi

    log_ok "MySQL local respondendo na porta ${DB_LOCAL_PORT}."
}

resolve_db_host()
{
    local entry="$1"

    entry=$(echo "$entry" | tr -d '[:space:]')
    if [[ -z "$entry" ]]; then
        return 1
    fi

    if [[ "$entry" == "local" ]]; then
        printf '127.0.0.1:%s' "$DB_LOCAL_PORT"
        return 0
    fi

    if [[ "$entry" == *:* ]]; then
        printf '%s' "$entry"
    else
        printf '%s:3306' "$entry"
    fi
    return 0
}

db_server_name()
{
    local entry="$1"

    entry=$(echo "$entry" | tr -d '[:space:]')
    if [[ "$entry" == "local" ]]; then
        printf 'db_local'
        return 0
    fi

    printf 'db_%s' "$(echo "${entry%%:*}" | tr '.:-' '___')"
}

load_topology()
{
    if [[ -n "$FLUX_DB_HOSTS" ]]; then
        return 0
    fi

    if [[ -f "$TOPOLOGY_FILE" ]]; then
        FLUX_DB_HOSTS=$(read_conf_value FLUX_DB_HOSTS "$TOPOLOGY_FILE" | tr -d '"')
    fi

    if [[ -z "$FLUX_DB_HOSTS" ]]; then
        FLUX_DB_HOSTS="local"
    fi
}

save_topology()
{
    local hosts="$1"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Gravar FLUX_DB_HOSTS=\"${hosts}\" em ${TOPOLOGY_FILE}"
        return 0
    fi

    cat > "$TOPOLOGY_FILE" <<EOF
# Topologia de banco do FluxSBC - gerado por flux_ha_setup.sh
# Ordem define a prioridade de escrita: o primeiro e ativo, os demais sao backup.
# "local" resolve para 127.0.0.1 na porta do MySQL desta maquina.
FLUX_DB_HOSTS="${hosts}"
EOF
    chmod 640 "$TOPOLOGY_FILE"
    log_ok "Topologia registrada em ${TOPOLOGY_FILE}"
}

install_dbcheck_agent()
{
    log_info "Instalando o agente de health check flux-dbcheck..."

    if [[ ! -f "${DBCHECK_SRC}/flux-dbcheck" ]]; then
        log_error "Agente nao encontrado em ${DBCHECK_SRC}/flux-dbcheck"
        log_error "Atualize o codigo em ${FLUX_SOURCE_DIR} antes de continuar."
        return 1
    fi

    run install -m 0755 "${DBCHECK_SRC}/flux-dbcheck" "$DBCHECK_BIN"
    run install -m 0644 "${DBCHECK_SRC}/flux-dbcheck.socket" "${SYSTEMD_DIR}/flux-dbcheck.socket"
    run install -m 0644 "${DBCHECK_SRC}/flux-dbcheck@.service" "${SYSTEMD_DIR}/flux-dbcheck@.service"

    local port
    port=$(mysql_local_port)

    mysql_local_cnf
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "CREATE USER '${HAPROXY_CHECK_USER}'@'localhost' + GRANT PROCESS, REPLICATION CLIENT"
        log_dry "Gravar ${DBCHECK_CNF} (porta ${port})"
    else
        if ! mysql --defaults-extra-file="$MYSQL_CNF" -e "
            CREATE USER IF NOT EXISTS '${HAPROXY_CHECK_USER}'@'localhost';
            CREATE USER IF NOT EXISTS '${HAPROXY_CHECK_USER}'@'127.0.0.1';
            GRANT PROCESS, REPLICATION CLIENT ON *.* TO '${HAPROXY_CHECK_USER}'@'localhost';
            GRANT PROCESS, REPLICATION CLIENT ON *.* TO '${HAPROXY_CHECK_USER}'@'127.0.0.1';
            FLUSH PRIVILEGES;" 2>/dev/null; then
            log_warn "Nao foi possivel criar '${HAPROXY_CHECK_USER}' com o usuario da aplicacao."
            log_warn "Crie manualmente com um usuario administrativo:"
            log_warn "  GRANT PROCESS, REPLICATION CLIENT ON *.* TO '${HAPROXY_CHECK_USER}'@'127.0.0.1';"
        fi

        cat > "$DBCHECK_CNF" <<EOF
[client]
user = ${HAPROXY_CHECK_USER}
host = 127.0.0.1
port = ${port}
protocol = TCP
EOF
        chmod 600 "$DBCHECK_CNF"
        chown mysql:mysql "$DBCHECK_CNF" 2>/dev/null || true
    fi

    if [[ $DRY_RUN -eq 0 && "$FLUX_DB_READ_MAX_LAG" != "30" ]]; then
        install -d -m 0755 "${SYSTEMD_DIR}/flux-dbcheck@.service.d"
        printf '[Service]\nEnvironment=FLUX_DB_READ_MAX_LAG=%s\n' "$FLUX_DB_READ_MAX_LAG" \
            > "${SYSTEMD_DIR}/flux-dbcheck@.service.d/override.conf"
    fi

    run systemctl daemon-reload
    run systemctl enable --now flux-dbcheck.socket

    if [[ $DRY_RUN -eq 0 ]]; then
        sleep 1
        local code
        code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${DB_CHECK_PORT}/write" 2>/dev/null)
        if [[ "$code" == "200" ]]; then
            log_ok "Agente respondendo: /write = 200 (no aceita escrita)."
        elif [[ "$code" == "503" ]]; then
            log_ok "Agente respondendo: /write = 503 (no em modo somente leitura)."
        else
            log_error "Agente nao respondeu como esperado na porta ${DB_CHECK_PORT} (codigo ${code:-vazio})."
            log_error "Verifique: journalctl -u 'flux-dbcheck@*'"
            return 1
        fi
    fi

    log_ok "Agente de health check instalado."
}

write_haproxy_config()
{
    local hosts="$1"
    local tmp_write tmp_read

    tmp_write=$(mktemp)
    tmp_read=$(mktemp)

    local idx=0 entry addr name
    while IFS= read -r entry; do
        entry=$(echo "$entry" | tr -d '[:space:]')
        if [[ -z "$entry" ]]; then
            continue
        fi

        addr=$(resolve_db_host "$entry") || continue
        name=$(db_server_name "$entry")
        idx=$((idx + 1))

        if [[ $idx -eq 1 ]]; then
            printf '    server %s %s check port %s\n' "$name" "$addr" "$DB_CHECK_PORT" >> "$tmp_write"
        else
            printf '    server %s %s check port %s backup\n' "$name" "$addr" "$DB_CHECK_PORT" >> "$tmp_write"
        fi
        printf '    server %s %s check port %s\n' "$name" "$addr" "$DB_CHECK_PORT" >> "$tmp_read"
    done <<< "$(echo "$hosts" | tr ',' '\n')"

    if [[ $idx -eq 0 ]]; then
        log_error "Nenhum host valido em FLUX_DB_HOSTS=${hosts}"
        rm -f "$tmp_write" "$tmp_read"
        return 1
    fi

    backup_file "$HAPROXY_CFG"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Gerando ${HAPROXY_CFG} a partir de ${HAPROXY_TPL}"
        log_dry "Pool de escrita (primeiro ativo, demais backup):"
        while IFS= read -r l; do log_dry "  $l"; done < "$tmp_write"
        log_dry "Pool de leitura (todos ativos):"
        while IFS= read -r l; do log_dry "  $l"; done < "$tmp_read"
        rm -f "$tmp_write" "$tmp_read"
        return 0
    fi

    local staged
    staged=$(mktemp)
    cp "$HAPROXY_TPL" "$staged"
    sed -i "s#__FLUX_DB_WRITE_BIND__#127.0.0.1:${DB_WRITE_PORT}#g" "$staged"
    sed -i "s#__FLUX_DB_READ_BIND__#127.0.0.1:${DB_READ_PORT}#g" "$staged"
    sed -i "s#__FLUX_DB_STATS_BIND__#127.0.0.1:${DB_STATS_PORT}#g" "$staged"
    sed -i -e "/__FLUX_DB_WRITE_SERVERS__/r ${tmp_write}" -e "/__FLUX_DB_WRITE_SERVERS__/d" "$staged"
    sed -i -e "/__FLUX_DB_READ_SERVERS__/r ${tmp_read}" -e "/__FLUX_DB_READ_SERVERS__/d" "$staged"
    rm -f "$tmp_write" "$tmp_read"

    if ! haproxy -c -f "$staged" >/dev/null 2>&1; then
        log_error "Configuracao gerada do HAProxy e invalida:"
        haproxy -c -f "$staged" || true
        rm -f "$staged"
        return 1
    fi

    if [[ -f "$HAPROXY_CFG" ]] && cmp -s "$staged" "$HAPROXY_CFG"; then
        rm -f "$staged"
        log_ok "Configuracao do HAProxy ja esta correta; nada a alterar."
        return 0
    fi

    mv "$staged" "$HAPROXY_CFG"
    chmod 644 "$HAPROXY_CFG"
    log_ok "Configuracao do HAProxy validada e aplicada (${idx} no(s))."

    systemctl enable haproxy >/dev/null 2>&1 || true
    run systemctl restart haproxy
    log_ok "HAProxy ativo: escrita 127.0.0.1:${DB_WRITE_PORT}, leitura 127.0.0.1:${DB_READ_PORT}."
}

update_odbc_ini()
{
    backup_file "$ODBC_INI"

    if grep -q "^\[FLUX_RO\]" "$ODBC_INI" && ! grep -qi "^\s*Socket\s*=" "$ODBC_INI"; then
        log_ok "${ODBC_INI} ja contem [FLUX_RO] e nao usa socket Unix."
        return 0
    fi

    log_info "Atualizando ${ODBC_INI} (TCP + DSN de leitura)..."

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Reescrever [FLUX] sem Socket, SERVER=127.0.0.1, PORT=${DB_WRITE_PORT}"
        log_dry "Acrescentar secao [FLUX_RO] com PORT=${DB_READ_PORT}"
        return 0
    fi

    local staged
    staged=$(mktemp)

    awk -v host="127.0.0.1" -v wport="${DB_WRITE_PORT}" '
        /^\[/          { section=$0 }
        /^[[:space:]]*Socket[[:space:]]*=/ { next }
        /^[[:space:]]*SERVER[[:space:]]*=/ { print "SERVER = " host; next }
        /^[[:space:]]*PORT[[:space:]]*=/   { print "PORT = " wport; next }
        { print }
    ' "$ODBC_INI" > "$staged"

    if ! grep -q "^\[FLUX_RO\]" "$staged"; then
        local ro_section
        ro_section=$(mktemp)
        awk -v rport="${DB_READ_PORT}" '
            /^\[FLUX\]/ { influx=1; print ""; print "[FLUX_RO]"; next }
            /^\[/        { influx=0 }
            influx {
                if ($0 ~ /^[[:space:]]*PORT[[:space:]]*=/) { print "PORT = " rport } else { print }
            }
        ' "$staged" > "$ro_section"
        cat "$ro_section" >> "$staged"
        rm -f "$ro_section"
    fi

    install -m 0644 "$staged" "$ODBC_INI"
    rm -f "$staged"
    log_ok "${ODBC_INI} atualizado com [FLUX] (${DB_WRITE_PORT}) e [FLUX_RO] (${DB_READ_PORT})."
}

update_flux_lua()
{
    local sync_wait="$1"

    if [[ ! -f "$FLUX_LUA" ]]; then
        log_warn "${FLUX_LUA} nao encontrado; o split de leitura do Lua nao sera configurado."
        return 0
    fi

    backup_file "$FLUX_LUA"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Garantir ODBC_DSN_RO=\"FLUX_RO\" e DB_SYNC_WAIT=\"${sync_wait}\" em ${FLUX_LUA}"
        return 0
    fi

    if grep -q "^ODBC_DSN_RO" "$FLUX_LUA"; then
        sed -i 's#^ODBC_DSN_RO.*#ODBC_DSN_RO="FLUX_RO"#' "$FLUX_LUA"
    else
        sed -i '/^ODBC_DSN=/a ODBC_DSN_RO="FLUX_RO"' "$FLUX_LUA"
    fi

    if grep -q "^DB_SYNC_WAIT" "$FLUX_LUA"; then
        sed -i "s#^DB_SYNC_WAIT.*#DB_SYNC_WAIT=\"${sync_wait}\"#" "$FLUX_LUA"
    else
        echo "DB_SYNC_WAIT=\"${sync_wait}\"" >> "$FLUX_LUA"
    fi

    log_ok "${FLUX_LUA}: ODBC_DSN_RO=FLUX_RO, DB_SYNC_WAIT=${sync_wait}."
}

update_flux_conf()
{
    local host="$1"

    backup_file "$FLUX_CONF"

    local current
    current=$(read_conf_value dbhost "$FLUX_CONF")

    if [[ "$current" == "$host" ]]; then
        log_ok "${FLUX_CONF}: dbhost ja e ${host}."
        return 0
    fi

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "sed dbhost = ${host} em ${FLUX_CONF}"
        return 0
    fi

    sed -i "s#^\s*dbhost\s*=.*#dbhost = ${host}#" "$FLUX_CONF"
    log_ok "${FLUX_CONF}: dbhost = ${host}."
}

update_nibblebill()
{
    if [[ ! -f "$FS_NIBBLEBILL" ]]; then
        log_warn "${FS_NIBBLEBILL} nao encontrado; nibblebill nao sera ajustado."
        return 0
    fi

    if ! grep -q 'value="dbname:user:password"' "$FS_NIBBLEBILL"; then
        log_ok "nibblebill.conf.xml ja esta configurado."
        return 0
    fi

    backup_file "$FS_NIBBLEBILL"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Trocar odbc-dsn por \$\${dsn} em ${FS_NIBBLEBILL}"
        return 0
    fi

    sed -i 's#<param name="odbc-dsn" value="dbname:user:password"/>#<param name="odbc-dsn" value="$${dsn}"/>#' "$FS_NIBBLEBILL"
    log_ok "nibblebill.conf.xml: odbc-dsn agora usa \$\${dsn} de vars.xml."

    if [[ -f "$FS_VARS" ]] && grep -q 'data="dsn=dbname:user:password"' "$FS_VARS"; then
        log_warn "${FS_VARS} ainda tem o placeholder do DSN. Ajuste manualmente:"
        log_warn "  sed -i 's#dbname:user:password#FLUX:${DB_USER}:<senha>#' ${FS_VARS}"
    fi
}

restart_web_services()
{
    log_info "Reiniciando php7.3-fpm e nginx..."
    run systemctl restart php7.3-fpm
    run systemctl restart nginx
    log_ok "Servicos web reiniciados."
    log_warn "O FreeSWITCH NAO foi reiniciado: isso derruba as chamadas ativas."
    log_warn "O pool ODBC so passa a usar os novos DSNs apos o restart. Programe uma janela e execute:"
    log_warn "  systemctl restart freeswitch"
}

#############################
#  Verificacao
#############################

verify_setup()
{
    local ok=0

    log_section "Verificacao"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Verificacao ignorada em dry-run."
        return 0
    fi

    if haproxy -c -f "$HAPROXY_CFG" >/dev/null 2>&1; then
        log_ok "Configuracao do HAProxy valida."
    else
        log_error "Configuracao do HAProxy invalida."
        ok=1
    fi

    if systemctl is-active --quiet haproxy; then
        log_ok "Servico haproxy ativo."
    else
        log_error "Servico haproxy inativo."
        ok=1
    fi

    local port
    for port in "$DB_WRITE_PORT" "$DB_READ_PORT"; do
        if mysql -h 127.0.0.1 -P "$port" --protocol=TCP -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -B -e "SELECT 1;" >/dev/null 2>&1; then
            log_ok "Consulta pela porta ${port} respondeu."
        else
            log_error "Falha ao consultar o banco pela porta ${port}."
            ok=1
        fi
    done

    local dsn
    for dsn in FLUX FLUX_RO; do
        if command -v isql >/dev/null 2>&1; then
            if echo "SELECT 1;" | isql "$dsn" "$DB_USER" "$DB_PASS" -b >/dev/null 2>&1; then
                log_ok "DSN ODBC ${dsn} respondeu."
            else
                log_error "DSN ODBC ${dsn} nao respondeu."
                ok=1
            fi
        else
            log_warn "isql indisponivel; DSN ${dsn} nao verificado (instale unixodbc-bin)."
        fi
    done

    if curl -s -o /dev/null "http://127.0.0.1:${DB_STATS_PORT}/"; then
        log_ok "Painel de estatisticas em http://127.0.0.1:${DB_STATS_PORT}/"
    else
        log_warn "Painel de estatisticas nao respondeu na porta ${DB_STATS_PORT}."
    fi

    if [[ $ok -eq 0 ]]; then
        log_ok "Verificacao concluida sem erros."
    else
        log_error "Verificacao concluiu com erros. Revise antes de seguir."
    fi

    return $ok
}

show_status()
{
    log_section "Estado atual"

    if haproxy_already_enabled; then
        log_ok "HAProxy configurado para o FluxSBC."
        if systemctl is-active --quiet haproxy; then
            log_ok "Servico haproxy: ativo"
        else
            log_warn "Servico haproxy: inativo"
        fi
        grep -E "^\s+server " "$HAPROXY_CFG" | sed 's/^/          /' | while IFS= read -r l; do log_info "$l"; done
    else
        log_warn "HAProxy ainda nao configurado neste servidor."
    fi

    log_info "dbhost em flux-config.conf: $(read_conf_value dbhost "$FLUX_CONF")"
    log_info "porta do MySQL local:       $(read_conf_value port "$MYSQLD_CNF" || echo '3306 (padrao)')"

    if grep -q "^\[FLUX_RO\]" "$ODBC_INI" 2>/dev/null; then
        log_ok "odbc.ini: DSN [FLUX_RO] presente"
    else
        log_warn "odbc.ini: DSN [FLUX_RO] ausente"
    fi

    if grep -qi "^\s*Socket\s*=" "$ODBC_INI" 2>/dev/null; then
        log_warn "odbc.ini: ainda usa socket Unix (nao passa pelo HAProxy)"
    fi

    if [[ -f "$FLUX_LUA" ]]; then
        log_info "flux.lua: $(grep -E '^(ODBC_DSN_RO|DB_SYNC_WAIT)' "$FLUX_LUA" | tr '\n' ' ')"
    fi

    local role="primario / standalone"
    if [[ "$(read_conf_value read_only "$MYSQLD_CNF")" == "ON" ]]; then
        role="replica (read_only)"
    fi
    log_info "Papel do MySQL local:       ${role}"
    log_info "server-id:                  $(read_conf_value server-id "$MYSQLD_CNF" || echo '1 (padrao)')"
    log_info "gtid_mode:                  $(read_conf_value gtid_mode "$MYSQLD_CNF" || echo 'OFF (padrao)')"
    log_info "event_scheduler:            $(read_conf_value event_scheduler "$MYSQLD_CNF" || echo 'ON (padrao)')"
    log_info "binlog_expire_logs_seconds: $(read_conf_value binlog_expire_logs_seconds "$MYSQLD_CNF")"

    local svc
    local disabled=""
    for svc in $CLONE_SERVICES; do
        if systemctl list-unit-files "${svc}.service" >/dev/null 2>&1; then
            if ! systemctl is-enabled --quiet "$svc" 2>/dev/null; then
                disabled="${disabled} ${svc}"
            fi
        fi
    done
    if [[ -n "$disabled" ]]; then
        log_warn "Servicos desabilitados:${disabled}"
    fi

    if [[ -f "$CRONTAB_FILE" && ! -s "$CRONTAB_FILE" ]]; then
        log_warn "Crontab do flux esta vazio (esperado em no de banco)."
    fi

    load_topology
    log_info "Topologia:                  ${FLUX_DB_HOSTS}"

    if [[ -x "$DBCHECK_BIN" ]]; then
        local code
        code=$(curl -s --max-time 3 -o /dev/null -w '%{http_code}' "http://127.0.0.1:${DB_CHECK_PORT}/write" 2>/dev/null)
        case "$code" in
            200) log_ok "Agente local: /write = 200 (aceita escrita)" ;;
            503) log_ok "Agente local: /write = 503 (somente leitura)" ;;
            *)   log_warn "Agente local nao respondeu (codigo ${code:-vazio})" ;;
        esac
    else
        log_warn "Agente flux-dbcheck nao instalado neste host."
    fi

    if [[ -S /run/haproxy/admin.sock ]] && command -v socat >/dev/null 2>&1; then
        _log ""
        echo "show stat" | socat /run/haproxy/admin.sock stdio 2>/dev/null \
            | awk -F, '$1 ~ /fluxdb_(write|read)_pool/ && $2 !~ /^(FRONTEND|BACKEND)$/ {printf "          %-22s %-16s %s\n", $1, $2, $18}' \
            | while IFS= read -r l; do log_info "$l"; done
    fi

    if [[ -d "$BACKUP_ROOT" ]]; then
        log_info "Snapshots disponiveis: $(ls -1 "$BACKUP_ROOT" 2>/dev/null | wc -l)"
    fi
}

#############################
#  Auditoria de configuracao
#############################

VC_PASS=0
VC_WARN=0
VC_FAIL=0

vc_pass() { VC_PASS=$((VC_PASS + 1)); _log "  ${GREEN}[PASS]${NC}  $*"; }
vc_warn() { VC_WARN=$((VC_WARN + 1)); _log "  ${YELLOW}[WARN]${NC}  $*"; }
vc_fail() { VC_FAIL=$((VC_FAIL + 1)); _log "  ${RED}[FAIL]${NC}  $*"; }

vc_mysql()
{
    local port
    port=$(mysql_local_port)
    mysql -h 127.0.0.1 -P "$port" --protocol=TCP -u"$DB_USER" -p"$DB_PASS" -N -B -e "$1" 2>/dev/null
}

vc_global()
{
    vc_mysql "SELECT @@GLOBAL.$1;" | head -1
}

vc_detect_role()
{
    local repl
    repl=$(mysql -h 127.0.0.1 -P "$(mysql_local_port)" --protocol=TCP -u"$DB_USER" -p"$DB_PASS" \
        -e "SHOW REPLICA STATUS\G" 2>/dev/null)
    if [[ -z "$repl" ]]; then
        repl=$(mysql -h 127.0.0.1 -P "$(mysql_local_port)" --protocol=TCP -u"$DB_USER" -p"$DB_PASS" \
            -e "SHOW SLAVE STATUS\G" 2>/dev/null)
    fi

    if [[ -n "$repl" ]]; then
        echo "replica"
    else
        echo "master"
    fi
}

vc_check_file()
{
    local file="$1"
    local desc="$2"
    local mode="${3:-}"

    if [[ ! -f "$file" ]]; then
        vc_fail "${desc}: ausente (${file})"
        return 1
    fi

    if [[ -n "$mode" ]]; then
        local actual
        actual=$(stat -c '%a' "$file" 2>/dev/null || stat -f '%Lp' "$file" 2>/dev/null)
        if [[ "$actual" != "$mode" ]]; then
            vc_warn "${desc}: permissao ${actual}, esperado ${mode} (${file})"
            return 0
        fi
    fi

    vc_pass "${desc}: presente"
    return 0
}

vc_check_port()
{
    local port="$1"
    local desc="$2"
    local required="${3:-1}"

    if port_in_use "$port"; then
        vc_pass "${desc}: escutando na porta ${port}"
        return 0
    fi

    if [[ "$required" == "1" ]]; then
        vc_fail "${desc}: nada escutando na porta ${port}"
    else
        vc_warn "${desc}: nada escutando na porta ${port}"
    fi
    return 1
}

vc_check_agent()
{
    local host="$1"
    local expect_write="$2"
    local label="$3"

    local code_w code_r
    code_w=$(curl -s --max-time 3 -o /dev/null -w '%{http_code}' "http://${host}:${DB_CHECK_PORT}/write" 2>/dev/null)
    code_r=$(curl -s --max-time 3 -o /dev/null -w '%{http_code}' "http://${host}:${DB_CHECK_PORT}/read" 2>/dev/null)

    if [[ -z "$code_w" || "$code_w" == "000" ]]; then
        vc_fail "Agente ${label}: sem resposta em ${host}:${DB_CHECK_PORT}"
        return 1
    fi

    if [[ "$code_w" == "$expect_write" ]]; then
        vc_pass "Agente ${label}: /write = ${code_w} (esperado para o papel)"
    else
        vc_fail "Agente ${label}: /write = ${code_w}, esperado ${expect_write}"
    fi

    if [[ "$code_r" == "200" ]]; then
        vc_pass "Agente ${label}: /read = 200"
    else
        vc_warn "Agente ${label}: /read = ${code_r} (no fora do pool de leitura)"
    fi
}

vc_common()
{
    log_section "Arquivos de configuracao"

    vc_check_file "$FLUX_CONF" "flux-config.conf"
    vc_check_file "$MYSQLD_CNF" "mysqld.cnf"
    vc_check_file "$ODBC_INI" "odbc.ini"
    vc_check_file "$FLUX_LUA" "flux.lua"
    vc_check_file "$DBCHECK_CNF" "credencial do agente" "600"

    local dbhost
    dbhost=$(read_conf_value dbhost "$FLUX_CONF")
    if [[ -n "$dbhost" ]]; then
        vc_pass "flux-config.conf: dbhost = ${dbhost}"
    else
        vc_fail "flux-config.conf: dbhost nao definido"
    fi

    if grep -q "^\[FLUX\]" "$ODBC_INI" 2>/dev/null; then
        vc_pass "odbc.ini: secao [FLUX] presente"
    else
        vc_fail "odbc.ini: secao [FLUX] ausente"
    fi

    if grep -q "^\[FLUX_RO\]" "$ODBC_INI" 2>/dev/null; then
        vc_pass "odbc.ini: secao [FLUX_RO] presente"
    else
        vc_fail "odbc.ini: secao [FLUX_RO] ausente (o split de leitura nao funciona)"
    fi

    if grep -qiE "^\s*Socket\s*=" "$ODBC_INI" 2>/dev/null; then
        vc_fail "odbc.ini: diretiva Socket presente - o ODBC ignora o HAProxy e vai direto ao socket local"
    else
        vc_pass "odbc.ini: sem diretiva Socket (usa TCP)"
    fi

    local odbc_ports
    odbc_ports=$(grep -E "^\s*PORT\s*=" "$ODBC_INI" 2>/dev/null | awk '{print $3}' | tr '\n' ' ')
    vc_pass "odbc.ini: portas configuradas = ${odbc_ports}"

    if [[ -f "$FLUX_LUA" ]]; then
        if grep -q "^ODBC_DSN_RO" "$FLUX_LUA"; then
            vc_pass "flux.lua: ODBC_DSN_RO definido"
        else
            vc_fail "flux.lua: ODBC_DSN_RO ausente (db_read usa o DSN de escrita)"
        fi
        local sync
        sync=$(grep -E "^DB_SYNC_WAIT" "$FLUX_LUA" | cut -d'"' -f2)
        vc_pass "flux.lua: DB_SYNC_WAIT = ${sync:-nao definido}"
    fi

    log_section "Portas e servicos"

    vc_check_port "$(mysql_local_port)" "MySQL local"

    if haproxy_already_enabled; then
        vc_check_port "$DB_WRITE_PORT" "HAProxy escrita"
        vc_check_port "$DB_READ_PORT" "HAProxy leitura"
        vc_check_port "$DB_STATS_PORT" "HAProxy stats" 0

        if haproxy -c -f "$HAPROXY_CFG" >/dev/null 2>&1; then
            vc_pass "haproxy.cfg: configuracao valida"
        else
            vc_fail "haproxy.cfg: configuracao invalida"
        fi

        if systemctl is-active --quiet haproxy 2>/dev/null; then
            vc_pass "Servico haproxy ativo"
        else
            vc_fail "Servico haproxy inativo"
        fi
    fi

    vc_check_port "$DB_CHECK_PORT" "Agente flux-dbcheck"

    if [[ -x "$DBCHECK_BIN" ]]; then
        vc_pass "Agente instalado em ${DBCHECK_BIN}"
    else
        vc_fail "Agente ausente em ${DBCHECK_BIN}"
    fi

    log_section "DSN ODBC"

    if command -v isql >/dev/null 2>&1; then
        local dsn
        for dsn in FLUX FLUX_RO; do
            if echo "SELECT 1;" | isql "$dsn" "$DB_USER" "$DB_PASS" -b >/dev/null 2>&1; then
                vc_pass "DSN ${dsn}: conecta"
            else
                vc_fail "DSN ${dsn}: nao conecta"
            fi
        done
    else
        vc_warn "isql indisponivel; DSNs nao verificados (instale unixodbc-bin)"
    fi
}

vc_master()
{
    log_section "Configuracao de source (master)"

    local v
    v=$(vc_global gtid_mode)
    if [[ "$v" == "ON" ]]; then vc_pass "gtid_mode = ON"; else vc_fail "gtid_mode = ${v:-?} (esperado ON)"; fi

    v=$(vc_global enforce_gtid_consistency)
    if [[ "$v" == "ON" ]]; then vc_pass "enforce_gtid_consistency = ON"; else vc_fail "enforce_gtid_consistency = ${v:-?} (esperado ON)"; fi

    v=$(vc_global log_bin)
    if [[ "$v" == "1" || "$v" == "ON" ]]; then vc_pass "log_bin ativo"; else vc_fail "log_bin desativado - replicacao impossivel"; fi

    v=$(vc_global binlog_format)
    if [[ "$v" == "ROW" ]]; then vc_pass "binlog_format = ROW"; else vc_warn "binlog_format = ${v:-?} (recomendado ROW)"; fi

    v=$(vc_global server_id)
    if [[ -n "$v" && "$v" != "0" ]]; then vc_pass "server_id = ${v}"; else vc_fail "server_id invalido"; fi

    v=$(vc_global binlog_expire_logs_seconds)
    if [[ -n "$v" && "$v" -ge 604800 ]]; then
        vc_pass "binlog_expire_logs_seconds = ${v}"
    else
        vc_warn "binlog_expire_logs_seconds = ${v:-?}: abaixo de 604800, uma replica fora do ar por mais tempo exigira re-seed"
    fi

    local bind
    bind=$(read_conf_value bind-address "$MYSQLD_CNF")
    if [[ "$bind" == "127.0.0.1" ]]; then
        vc_fail "bind-address = 127.0.0.1: nenhuma replica remota consegue conectar"
    elif [[ -n "$bind" ]]; then
        vc_pass "bind-address = ${bind}"
    else
        vc_warn "bind-address nao definido no mysqld.cnf"
    fi

    local repl_users
    repl_users=$(vc_mysql "SELECT CONCAT(user,'@',host) FROM mysql.user WHERE user='${REPL_USER}';" | tr '\n' ' ')
    if [[ -n "$repl_users" ]]; then
        vc_pass "Usuario de replicacao: ${repl_users}"
    else
        vc_fail "Usuario '${REPL_USER}' nao existe - nenhuma replica consegue conectar"
    fi

    local definer
    definer=$(vc_mysql "SELECT COUNT(*) FROM mysql.user WHERE user='${DB_USER}' AND host='127.0.0.1';")
    if [[ "${definer:-0}" == "1" ]]; then
        vc_pass "Usuario ${DB_USER}@127.0.0.1 existe (DEFINER das views e procedures)"
    else
        vc_fail "Usuario ${DB_USER}@127.0.0.1 ausente - views SQL SECURITY DEFINER falham com ERROR 1449"
    fi

    log_section "Topologia"

    load_topology
    _log "  Topologia: ${FLUX_DB_HOSTS}"

    local entry addr host idx=0
    while IFS= read -r entry; do
        entry=$(echo "$entry" | tr -d '[:space:]')
        [[ -z "$entry" ]] && continue
        idx=$((idx + 1))
        addr=$(resolve_db_host "$entry")
        host="${addr%%:*}"

        if [[ $idx -eq 1 ]]; then
            vc_check_agent "$host" "200" "$(db_server_name "$entry") (ativo na escrita)"
        else
            vc_check_agent "$host" "503" "$(db_server_name "$entry") (replica)"
        fi
    done <<< "$(echo "$FLUX_DB_HOSTS" | tr ',' '\n')"
}

vc_replica()
{
    log_section "Configuracao de replica"

    local v
    v=$(vc_global server_id)
    vc_pass "server_id = ${v}"

    v=$(vc_global server_uuid)
    vc_pass "server_uuid = ${v}"

    v=$(vc_global read_only)
    if [[ "$v" == "1" ]]; then vc_pass "read_only = ON"; else vc_fail "read_only = OFF (replica deve ser somente leitura)"; fi

    v=$(vc_global super_read_only)
    if [[ "$v" == "1" ]]; then vc_pass "super_read_only = ON"; else vc_fail "super_read_only = OFF"; fi

    v=$(vc_global event_scheduler)
    if [[ "$v" == "OFF" ]]; then
        vc_pass "event_scheduler = OFF"
    else
        vc_fail "event_scheduler = ${v}: os EVENTs staging_cdrs/remove_cdrs_records duplicariam agregacoes"
    fi

    local ev_enabled
    ev_enabled=$(vc_mysql "SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA='${DB_NAME}' AND STATUS='ENABLED';")
    if [[ "${ev_enabled:-0}" == "0" ]]; then
        vc_pass "Nenhum EVENT habilitado"
    else
        vc_fail "${ev_enabled} EVENT(s) habilitado(s) nesta replica"
    fi

    local skip
    skip=$(read_conf_value skip_replica_start "$MYSQLD_CNF")
    if [[ "$skip" == "OFF" || -z "$skip" ]]; then
        vc_pass "skip_replica_start = ${skip:-OFF (padrao)}: a replicacao sobe apos restart"
    else
        vc_fail "skip_replica_start = ${skip}: a replicacao NAO sobe sozinha apos restart"
    fi

    log_section "Vinculo com o master"

    vc_check_file "$REPL_CNF" "parametros de replicacao" "600"
    vc_check_file "$REPAIR_CNF" "credencial de reparo" "600"

    local repl_out src_host src_port
    repl_out=$(mysql -h 127.0.0.1 -P "$(mysql_local_port)" --protocol=TCP -u"$DB_USER" -p"$DB_PASS" -e "SHOW REPLICA STATUS\G" 2>/dev/null)
    [[ -z "$repl_out" ]] && repl_out=$(mysql -h 127.0.0.1 -P "$(mysql_local_port)" --protocol=TCP -u"$DB_USER" -p"$DB_PASS" -e "SHOW SLAVE STATUS\G" 2>/dev/null)

    src_host=$(printf '%s' "$repl_out" | grep -E "^[[:space:]]*(Source|Master)_Host:" | head -1 | cut -d: -f2- | tr -d ' ')
    src_port=$(printf '%s' "$repl_out" | grep -E "^[[:space:]]*(Source|Master)_Port:" | head -1 | cut -d: -f2- | tr -d ' ')

    if [[ -n "$src_host" ]]; then
        vc_pass "Replicando de ${src_host}:${src_port}"
    else
        vc_fail "Replicacao nao configurada"
        return
    fi

    if [[ -f "$REPL_CNF" ]]; then
        local conf_host
        conf_host=$(read_conf_value REPL_SOURCE_HOST "$REPL_CNF")
        if [[ "$conf_host" == "$src_host" ]]; then
            vc_pass "${REPL_CNF} coerente com o vinculo ativo"
        else
            vc_fail "${REPL_CNF} aponta para ${conf_host}, mas a replicacao usa ${src_host}"
        fi
    fi

    if (exec 3<>"/dev/tcp/${src_host}/${src_port}") 2>/dev/null; then
        vc_pass "Porta ${src_port} do master alcancavel"
    else
        vc_fail "Porta ${src_port} do master (${src_host}) inacessivel"
    fi

    vc_check_agent "$src_host" "200" "do master"

    local io sql
    io=$(printf '%s' "$repl_out" | grep -E "^[[:space:]]*(Replica|Slave)_IO_Running:" | head -1 | cut -d: -f2- | tr -d ' ')
    sql=$(printf '%s' "$repl_out" | grep -E "^[[:space:]]*(Replica|Slave)_SQL_Running:" | head -1 | cut -d: -f2- | tr -d ' ')

    if [[ "$io" == "Yes" && "$sql" == "Yes" ]]; then
        vc_pass "Threads de replicacao em execucao"
    else
        vc_fail "Threads de replicacao paradas (IO=${io}, SQL=${sql})"
    fi

    log_section "Clone desarmado"

    local svc
    for svc in freeswitch json_cdr event_guard nginx php7.3-fpm; do
        if ! systemctl list-unit-files "${svc}.service" >/dev/null 2>&1; then
            continue
        fi
        if systemctl is-enabled --quiet "$svc" 2>/dev/null; then
            vc_fail "${svc}: HABILITADO - sobe no proximo boot e duplica processamento"
        else
            vc_pass "${svc}: desabilitado"
        fi
    done

    if [[ -f "$CRONTAB_FILE" && -s "$CRONTAB_FILE" ]]; then
        vc_fail "Crontab do flux nao esta vazio - o cron dispararia jobs contra a producao"
    else
        vc_pass "Crontab do flux vazio"
    fi
}

verify_config()
{
    VC_PASS=0; VC_WARN=0; VC_FAIL=0

    check_flux_install
    load_db_credentials

    local role
    role=$(vc_detect_role)

    log_section "Auditoria de configuracao - papel detectado: ${role}"

    vc_common

    if [[ "$role" == "master" ]]; then
        vc_master
    else
        vc_replica
    fi

    log_section "Resumo"
    _log "  ${GREEN}PASS${NC}: ${VC_PASS}   ${YELLOW}WARN${NC}: ${VC_WARN}   ${RED}FAIL${NC}: ${VC_FAIL}"

    if [[ $VC_FAIL -gt 0 ]]; then
        log_error "Auditoria encontrou ${VC_FAIL} problema(s). Consulte docs/HA-MYSQL-DIAGNOSTICO.md"
        return 2
    fi

    if [[ $VC_WARN -gt 0 ]]; then
        log_warn "Auditoria concluida com ${VC_WARN} aviso(s)."
        return 1
    fi

    log_ok "Auditoria concluida sem problemas."
    return 0
}

#############################
#  Acoes
#############################

enable_local()
{
    log_section "Etapa 1 - HAProxy sobre o MySQL local"

    check_flux_install
    load_db_credentials

    if haproxy_already_enabled; then
        log_ok "HAProxy ja configurado neste servidor. Nada a fazer."
        log_info "Use --status para inspecionar ou --migrate-galera para repontar a um cluster."
        return 0
    fi

    check_ports

    build_mysql_cnf "127.0.0.1" "3306" "$DB_USER" "$DB_PASS"
    backup_file "$FLUX_CONF"
    backup_file "$FLUX_LUA"
    backup_file "$ODBC_INI"
    backup_file "$MYSQLD_CNF"
    backup_file "$HAPROXY_CFG"
    backup_file "$FS_VARS"
    backup_file "$FS_NIBBLEBILL"
    backup_database "127.0.0.1" "3306" "$DB_NAME"

    install_haproxy_packages
    move_mysql_port
    build_mysql_cnf "127.0.0.1" "$DB_LOCAL_PORT" "$DB_USER" "$DB_PASS"
    install_dbcheck_agent || return 1
    write_haproxy_config "${FLUX_DB_HOSTS:-local}" || return 1
    save_topology "${FLUX_DB_HOSTS:-local}"
    update_odbc_ini
    update_flux_lua "FALSE"
    update_flux_conf "127.0.0.1"
    update_nibblebill
    restart_web_services
    write_backup_manifest

    verify_setup || true

    log_section "Etapa 1 concluida"
    log_info "MySQL local:  127.0.0.1:${DB_LOCAL_PORT}"
    log_info "HAProxy:      escrita ${DB_WRITE_PORT} / leitura ${DB_READ_PORT} / stats ${DB_STATS_PORT}"
    log_info "Snapshot:     ${BACKUP_DIR}"
    log_warn "Reinicie o FreeSWITCH em janela programada para ativar os novos DSNs."
}

apply_pk_migration()
{
    log_section "Migracao de chaves primarias (pre-requisito do Galera)"

    local mig="${FLUX_SOURCE_DIR}/database/updates/update-18-08-2026.sql"

    if [[ ! -f "$mig" ]]; then
        log_error "Migracao nao encontrada: ${mig}"
        return 1
    fi

    load_db_credentials

    local port="$DB_LOCAL_PORT"
    if ! haproxy_already_enabled; then
        port="3306"
    fi

    log_warn "Esta migracao adiciona PRIMARY KEY em cdrs, cdrs_staging, reseller_cdrs e q850code."
    log_warn "Em bases grandes o ALTER TABLE de cdrs pode levar horas e bloquear escrita."

    if [[ $DRY_RUN -eq 0 ]] && [[ -t 0 ]]; then
        if ! confirm "Prosseguir com a migracao agora?"; then
            log_info "Migracao cancelada."
            return 0
        fi
    fi

    build_mysql_cnf "127.0.0.1" "$port" "$DB_USER" "$DB_PASS"
    backup_database "127.0.0.1" "$port" "$DB_NAME"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "mysql --defaults-extra-file=*** ${DB_NAME} < ${mig}"
        return 0
    fi

    if mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" < "$mig"; then
        log_ok "Migracao de chaves primarias aplicada."
    else
        log_error "Falha ao aplicar a migracao."
        return 1
    fi
}

tables_without_pk()
{
    mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "
        SELECT COUNT(*) FROM information_schema.TABLES t
        WHERE t.TABLE_SCHEMA = '${DB_NAME}'
          AND t.TABLE_TYPE = 'BASE TABLE'
          AND t.TABLE_NAME IN ('cdrs','cdrs_staging','reseller_cdrs')
          AND NOT EXISTS (
              SELECT 1 FROM information_schema.TABLE_CONSTRAINTS c
              WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA
                AND c.TABLE_NAME = t.TABLE_NAME
                AND c.CONSTRAINT_TYPE = 'PRIMARY KEY'
          );" 2>/dev/null || echo "-1"
}

migrate_galera()
{
    log_section "Etapa 2 - repontar para o cluster Galera"

    check_flux_install
    load_db_credentials

    if ! haproxy_already_enabled; then
        log_error "Execute primeiro --enable-local."
        return 1
    fi

    local nodes="${FLUX_DB_NODES:-}"
    local admin_host="${MYSQL_ADMIN_HOST:-}"
    local admin_port="${MYSQL_ADMIN_PORT:-3306}"
    local admin_user="${MYSQL_ADMIN_USER:-root}"
    local admin_pass="${MYSQL_ADMIN_PASSWORD:-}"

    if [[ -z "$nodes" ]]; then
        read -rp "Nos Galera (ip:porta separados por virgula): " nodes
    fi
    if [[ -z "$admin_host" ]]; then
        admin_host="${nodes%%,*}"
        admin_host="${admin_host%%:*}"
    fi
    if [[ -z "$admin_pass" ]]; then
        read -rsp "Senha do usuario ${admin_user} no cluster: " admin_pass
        echo ""
    fi

    if [[ -z "$nodes" || -z "$admin_pass" ]]; then
        log_error "Nos do cluster e senha administrativa sao obrigatorios."
        return 1
    fi

    build_mysql_cnf "127.0.0.1" "$DB_LOCAL_PORT" "$DB_USER" "$DB_PASS"

    local pending
    pending=$(tables_without_pk)
    if [[ "$pending" != "0" ]]; then
        log_error "Existem ${pending} tabela(s) de CDR sem PRIMARY KEY. Galera nao replica tabela sem PK."
        log_error "Aplique a migracao (opcao 4 do menu ou update-18-08-2026.sql) antes de continuar."
        return 1
    fi
    log_ok "Todas as tabelas de CDR possuem PRIMARY KEY."

    local src_tables src_accounts src_cdrs
    src_tables=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}';")
    src_accounts=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.accounts;")
    src_cdrs=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.cdrs;")
    log_info "Origem: ${src_tables} tabelas, ${src_accounts} contas, ${src_cdrs} CDRs."

    init_backup_dir
    local dump="${BACKUP_DIR}/${DB_NAME}_migracao_${TIMESTAMP}.sql"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "mysqldump local -> ${dump}"
        log_dry "carga em ${admin_host}:${admin_port}"
    else
        log_info "Gerando dump para migracao..."
        mysqldump --defaults-extra-file="$MYSQL_CNF" --single-transaction --databases "$DB_NAME" > "$dump"
        chmod 600 "$dump"
        log_ok "Dump gerado ($(du -h "$dump" | cut -f1))."

        log_info "Carregando no cluster (${admin_host}:${admin_port})..."
        if ! mysql -h "$admin_host" -P "$admin_port" --protocol=TCP -u"$admin_user" -p"$admin_pass" < "$dump"; then
            log_error "Falha ao carregar o dump no cluster. Nada foi repontado."
            return 1
        fi

        local dst_tables dst_accounts dst_cdrs
        dst_tables=$(mysql -h "$admin_host" -P "$admin_port" --protocol=TCP -u"$admin_user" -p"$admin_pass" -N -B -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}';")
        dst_accounts=$(mysql -h "$admin_host" -P "$admin_port" --protocol=TCP -u"$admin_user" -p"$admin_pass" -N -B -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.accounts;")
        dst_cdrs=$(mysql -h "$admin_host" -P "$admin_port" --protocol=TCP -u"$admin_user" -p"$admin_pass" -N -B -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.cdrs;")
        log_info "Destino: ${dst_tables} tabelas, ${dst_accounts} contas, ${dst_cdrs} CDRs."

        if [[ "$src_tables" != "$dst_tables" || "$src_accounts" != "$dst_accounts" || "$src_cdrs" != "$dst_cdrs" ]]; then
            log_error "Divergencia entre origem e destino. O repontamento foi abortado."
            log_error "Investigue o cluster antes de repetir."
            return 1
        fi
        log_ok "Contagens conferem entre origem e destino."

        mysql -h "$admin_host" -P "$admin_port" --protocol=TCP -u"$admin_user" -p"$admin_pass" \
            -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';
                GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';FLUSH PRIVILEGES;" \
            || log_warn "Nao foi possivel garantir o usuario da aplicacao no cluster; verifique manualmente."
    fi

    install_dbcheck_agent || return 1
    write_haproxy_config "$nodes" || return 1
    save_topology "$nodes"
    update_flux_lua "TRUE"
    restart_web_services
    write_backup_manifest

    if verify_setup; then
        log_ok "Cluster em uso. O MySQL local pode ser desativado:"
        log_info "  systemctl stop mysql && systemctl disable mysql"
    else
        log_error "Verificacao falhou. O MySQL local foi mantido ativo para rollback."
    fi
}

#############################
#  Replicacao assincrona
#############################

configure_source_mysql()
{
    local bind_ip="$1"

    backup_file "$MYSQLD_CNF"

    log_info "Ajustando ${MYSQLD_CNF} para atuar como source..."
    set_cnf_value "server-id" "$REPL_SOURCE_ID" "$MYSQLD_CNF"
    set_cnf_value "gtid_mode" "ON" "$MYSQLD_CNF"
    set_cnf_value "enforce_gtid_consistency" "ON" "$MYSQLD_CNF"
    set_cnf_value "binlog_format" "ROW" "$MYSQLD_CNF"
    set_cnf_value "log_replica_updates" "ON" "$MYSQLD_CNF"
    set_cnf_value "binlog_expire_logs_seconds" "$REPL_BINLOG_EXPIRE" "$MYSQLD_CNF"
    set_cnf_value "bind-address" "127.0.0.1,${bind_ip}" "$MYSQLD_CNF"

    if grep -qE "^\s*(super_)?read_only\s*=\s*ON" "$MYSQLD_CNF" 2>/dev/null; then
        log_warn "Este no estava marcado como somente leitura; liberando escrita para atuar como source."
        set_cnf_value "read_only" "OFF" "$MYSQLD_CNF"
        set_cnf_value "super_read_only" "OFF" "$MYSQLD_CNF"
    fi

    log_warn "binlog_expire_logs_seconds elevado para ${REPL_BINLOG_EXPIRE}s. Com o valor antigo (86400s),"
    log_warn "uma replica fora do ar por mais de um dia exigiria re-seed completo."
}

check_gtid_compatibility()
{
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Verificacao de compatibilidade com GTID"
        return 0
    fi

    local non_innodb
    non_innodb=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_TYPE = 'BASE TABLE'
          AND ENGINE <> 'InnoDB';" 2>/dev/null || echo "0")

    if [[ "${non_innodb:-0}" != "0" ]]; then
        log_warn "Existem ${non_innodb} tabela(s) fora do InnoDB em ${DB_NAME}."
        log_warn "Com enforce_gtid_consistency=ON, transacoes que misturem engines serao rejeitadas."
    else
        log_ok "Todas as tabelas de ${DB_NAME} sao InnoDB."
    fi

    local anon
    anon=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "
        SHOW GLOBAL STATUS LIKE 'ONGOING_ANONYMOUS_TRANSACTION_COUNT';" 2>/dev/null | awk '{print $2}')
    if [[ -n "${anon:-}" && "${anon}" != "0" ]]; then
        log_warn "Ha ${anon} transacao(oes) anonima(s) em andamento; aguarde antes de reiniciar."
    fi
}

add_replica()
{
    log_section "Configurar este servidor como source de replicacao"

    check_flux_install
    load_db_credentials

    if ! haproxy_already_enabled; then
        log_error "Execute primeiro --enable-local neste servidor."
        return 1
    fi

    local source_ip="${REPL_SOURCE_HOST:-}"
    local replica_ip="${REPL_REPLICA_HOST:-}"
    local repl_pass="${REPL_PASSWORD:-}"

    if [[ -z "$source_ip" ]]; then
        read -rp "IP privado DESTE servidor (source), usado no bind-address: " source_ip
    fi
    if [[ -z "$replica_ip" ]]; then
        read -rp "IP da replica (fs-db-1): " replica_ip
    fi
    if [[ -z "$repl_pass" ]]; then
        read -rsp "Senha a definir para o usuario de replicacao '${REPL_USER}': " repl_pass
        echo ""
    fi

    if [[ -z "$source_ip" || -z "$replica_ip" || -z "$repl_pass" ]]; then
        log_error "IP do source, IP da replica e senha de replicacao sao obrigatorios."
        return 1
    fi

    local port
    port=$(mysql_local_port)
    build_mysql_admin_cnf "$port" || return 1

    check_gtid_compatibility
    configure_source_mysql "$source_ip"

    run systemctl restart mysql
    wait_mysql_ready "$port" || return 1

    if [[ $DRY_RUN -eq 0 ]]; then
        local gtid
        gtid=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "SELECT @@GLOBAL.gtid_mode;" 2>/dev/null)
        if [[ "$gtid" != "ON" ]]; then
            log_error "gtid_mode nao ficou ON apos o restart (valor: ${gtid:-desconhecido})."
            log_error "Verifique ${MYSQLD_CNF} e /var/log/mysql/error.log"
            return 1
        fi
        log_ok "gtid_mode = ON"
    fi

    log_info "Criando usuario de replicacao e definer da aplicacao..."
    mysql_run -e "
        CREATE USER IF NOT EXISTS '${REPL_USER}'@'${replica_ip}' IDENTIFIED WITH mysql_native_password BY '${repl_pass}';
        ALTER USER '${REPL_USER}'@'${replica_ip}' IDENTIFIED WITH mysql_native_password BY '${repl_pass}';
        GRANT REPLICATION SLAVE ON *.* TO '${REPL_USER}'@'${replica_ip}';
        CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';
        GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
        FLUSH PRIVILEGES;"

    log_ok "Usuario '${REPL_USER}'@'${replica_ip}' criado."
    log_ok "Usuario '${DB_USER}'@'127.0.0.1' criado (definer das views e procedures)."

    init_backup_dir
    local seed="${BACKUP_DIR}/seed_${DB_NAME}_${TIMESTAMP}.sql.gz"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "mysqldump --single-transaction --set-gtid-purged=ON --triggers --routines --events -> ${seed}"
    else
        log_info "Gerando dump de seed para a replica..."
        if ! mysqldump --defaults-extra-file="$MYSQL_CNF" \
                --single-transaction --set-gtid-purged=ON \
                --triggers --routines --events \
                --databases "$DB_NAME" | gzip > "$seed"; then
            log_error "Falha ao gerar o dump de seed."
            rm -f "$seed"
            return 1
        fi
        chmod 600 "$seed"
        log_ok "Seed gerado: ${seed} ($(du -h "$seed" | cut -f1))"
    fi

    write_backup_manifest

    log_section "Proximos passos no fs-db-1"
    _log ""
    _log "  1) Copie o seed para a replica:"
    _log "     scp ${seed} root@${replica_ip}:/root/"
    _log ""
    _log "  2) Na replica, desarme o clone e configure a replicacao:"
    _log "     REPL_SOURCE_HOST=${source_ip} \\"
    _log "     REPL_PASSWORD='<a senha definida acima>' \\"
    _log "     SEED_DUMP=/root/$(basename "$seed") \\"
    _log "     /opt/flux/misc/flux_ha_setup.sh --make-db-node"
    _log ""
    log_warn "A replica NAO deve ser adicionada ao haproxy.cfg deste servidor."
    log_warn "Com dois nos nao ha quorum: a promocao e manual (--promote-replica)."
}

disarm_clone_services()
{
    log_info "Desativando servicos de aplicacao neste no..."

    local svc
    for svc in $CLONE_SERVICES; do
        if systemctl list-unit-files "${svc}.service" >/dev/null 2>&1; then
            run systemctl disable --now "$svc"
            log_ok "${svc}: parado e desabilitado"
        else
            log_info "${svc}: unit ausente, ignorado"
        fi
    done

    log_warn "Os servicos continuam instalados (apenas disable+stop) para permitir DR completo."
}

disarm_clone_crontab()
{
    if [[ ! -f "$CRONTAB_FILE" ]]; then
        log_ok "Nenhum crontab em ${CRONTAB_FILE}."
        return 0
    fi

    backup_file "$CRONTAB_FILE"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Esvaziar ${CRONTAB_FILE}"
        return 0
    fi

    : > "$CRONTAB_FILE"
    log_ok "Crontab esvaziado (o job crons/index dispararia wget contra a URL de producao)."
}

disarm_clone_cdr_spool()
{
    if [[ ! -d "$FS_CDR_SPOOL" ]]; then
        log_ok "Sem spool de CDR em ${FS_CDR_SPOOL}."
        return 0
    fi

    local pending
    pending=$(find "$FS_CDR_SPOOL" -maxdepth 1 -name '*.json' 2>/dev/null | wc -l | tr -d ' ')

    if [[ "${pending:-0}" == "0" ]]; then
        log_ok "Spool de CDR vazio."
        return 0
    fi

    init_backup_dir
    log_warn "${pending} CDR(s) pendentes no spool herdado do clone."

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Mover ${pending} arquivo(s) de ${FS_CDR_SPOOL} para ${BACKUP_DIR}/cdr_spool/"
        return 0
    fi

    install -d -m 0700 "${BACKUP_DIR}/cdr_spool"
    find "$FS_CDR_SPOOL" -maxdepth 1 -name '*.json' -exec mv {} "${BACKUP_DIR}/cdr_spool/" \;
    log_ok "Spool movido para ${BACKUP_DIR}/cdr_spool/ (nao sera reprocessado)."
}

reset_mysql_identity()
{
    local auto_cnf="${MYSQL_DATADIR}/auto.cnf"

    if [[ ! -f "$auto_cnf" ]]; then
        log_info "${auto_cnf} ausente; o MySQL gerara um server_uuid novo."
        return 0
    fi

    backup_file "$auto_cnf"

    log_info "Regenerando server_uuid (o clone herdou o UUID do fs-1)..."
    run systemctl stop mysql
    run rm -f "$auto_cnf"
}

configure_replica_mysql()
{
    backup_file "$MYSQLD_CNF"

    log_info "Ajustando ${MYSQLD_CNF} para atuar como replica..."
    set_cnf_value "server-id" "$REPL_REPLICA_ID" "$MYSQLD_CNF"
    set_cnf_value "gtid_mode" "ON" "$MYSQLD_CNF"
    set_cnf_value "enforce_gtid_consistency" "ON" "$MYSQLD_CNF"
    set_cnf_value "binlog_format" "ROW" "$MYSQLD_CNF"
    set_cnf_value "read_only" "ON" "$MYSQLD_CNF"
    set_cnf_value "super_read_only" "ON" "$MYSQLD_CNF"
    set_cnf_value "event_scheduler" "OFF" "$MYSQLD_CNF"
    set_cnf_value "skip_replica_start" "OFF" "$MYSQLD_CNF"
    set_cnf_value "relay_log" "/var/log/mysql/relay-bin" "$MYSQLD_CNF"
}

disable_inherited_events()
{
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "ALTER EVENT staging_cdrs DISABLE / remove_cdrs_records DISABLE"
        return 0
    fi

    local ev
    for ev in staging_cdrs remove_cdrs_records; do
        if mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e "
            SELECT COUNT(*) FROM information_schema.EVENTS
            WHERE EVENT_SCHEMA='${DB_NAME}' AND EVENT_NAME='${ev}';" 2>/dev/null | grep -q '^1$'; then
            mysql --defaults-extra-file="$MYSQL_CNF" -e "ALTER EVENT \`${DB_NAME}\`.\`${ev}\` DISABLE;" 2>/dev/null \
                && log_ok "EVENT ${ev} desabilitado." \
                || log_warn "Nao foi possivel desabilitar o EVENT ${ev}."
        fi
    done
}

save_replication_credentials()
{
    local source_ip="$1"
    local source_port="$2"
    local repl_pass="$3"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Gravar ${REPL_CNF} (source, usuario e senha de replicacao)"
        log_dry "Gravar ${REPAIR_CNF} (credencial administrativa local)"
        return 0
    fi

    cat > "$REPL_CNF" <<EOF
# FluxSBC - parametros de replicacao deste no
# Usado por flux_replication_repair.sh para reconectar sem intervencao.
REPL_SOURCE_HOST=${source_ip}
REPL_SOURCE_PORT=${source_port}
REPL_USER=${REPL_USER}
REPL_PASSWORD=${repl_pass}
EOF
    chmod 600 "$REPL_CNF"
    chown root:root "$REPL_CNF" 2>/dev/null || true
    log_ok "Parametros de replicacao gravados em ${REPL_CNF}"

    cat > "$REPAIR_CNF" <<EOF
[client]
host = 127.0.0.1
port = $(mysql_local_port)
protocol = TCP
user = ${MYSQL_ADMIN_USER:-root}
password = '${MYSQL_ADMIN_PASSWORD:-}'
EOF
    chmod 600 "$REPAIR_CNF"
    chown root:root "$REPAIR_CNF" 2>/dev/null || true
    log_ok "Credencial administrativa gravada em ${REPAIR_CNF}"
    log_warn "${REPL_CNF} e ${REPAIR_CNF} contem senhas em texto (0600, root)."
    log_warn "Sao necessarios para a recuperacao automatica da replicacao."
}

start_replication()
{
    local source_ip="$1"
    local repl_pass="$2"
    local source_port="$3"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "CHANGE REPLICATION SOURCE TO SOURCE_HOST='${source_ip}' ... SOURCE_AUTO_POSITION=1"
        log_dry "START REPLICA"
        return 0
    fi

    local change_stmt start_stmt
    if mysql_supports_source_syntax; then
        change_stmt="CHANGE REPLICATION SOURCE TO
            SOURCE_HOST='${source_ip}', SOURCE_PORT=${source_port},
            SOURCE_USER='${REPL_USER}', SOURCE_PASSWORD='${repl_pass}',
            SOURCE_AUTO_POSITION=1, GET_SOURCE_PUBLIC_KEY=1"
        start_stmt="START REPLICA"
    else
        change_stmt="CHANGE MASTER TO
            MASTER_HOST='${source_ip}', MASTER_PORT=${source_port},
            MASTER_USER='${REPL_USER}', MASTER_PASSWORD='${repl_pass}',
            MASTER_AUTO_POSITION=1, GET_MASTER_PUBLIC_KEY=1"
        start_stmt="START SLAVE"
    fi

    if ! mysql --defaults-extra-file="$MYSQL_CNF" -e "${change_stmt}; ${start_stmt};"; then
        log_error "Falha ao configurar a replicacao."
        return 1
    fi

    sleep 3

    local io_state sql_state
    io_state=$(replica_status_field "Replica_IO_Running")
    [[ -z "$io_state" ]] && io_state=$(replica_status_field "Slave_IO_Running")
    sql_state=$(replica_status_field "Replica_SQL_Running")
    [[ -z "$sql_state" ]] && sql_state=$(replica_status_field "Slave_SQL_Running")

    if [[ "$io_state" == "Yes" && "$sql_state" == "Yes" ]]; then
        log_ok "Replicacao ativa (IO=${io_state}, SQL=${sql_state})."
        return 0
    fi

    log_error "Replicacao nao iniciou (IO=${io_state:-?}, SQL=${sql_state:-?})."
    local err
    err=$(replica_status_field "Last_IO_Error")
    [[ -n "$err" ]] && log_error "Last_IO_Error: ${err}"
    err=$(replica_status_field "Last_SQL_Error")
    [[ -n "$err" ]] && log_error "Last_SQL_Error: ${err}"
    return 1
}

make_db_node()
{
    log_section "Converter este servidor em no de banco (replica standby)"

    check_flux_install
    load_db_credentials

    log_warn "Este servidor deixara de operar como SBC: FreeSWITCH, nginx, php-fpm,"
    log_warn "json_cdr, event_guard, fail2ban e o crontab serao desativados."
    log_warn "Rodar isso no servidor de PRODUCAO derruba o servico."
    _log ""

    if [[ $DRY_RUN -eq 0 ]]; then
        if [[ ! -t 0 ]]; then
            log_error "Esta acao exige confirmacao interativa. Use --dry-run para simular."
            return 1
        fi
        local typed
        read -rp "Digite o hostname deste servidor (${HOSTNAME:-$(hostname)}) para confirmar: " typed
        if [[ "$typed" != "$(hostname)" ]]; then
            log_error "Hostname nao confere. Abortado."
            return 1
        fi
    fi

    local source_ip="${REPL_SOURCE_HOST:-}"
    local repl_pass="${REPL_PASSWORD:-}"
    local seed="${SEED_DUMP:-}"
    local source_port="${REPL_SOURCE_PORT:-3306}"

    if [[ -z "$source_ip" ]]; then
        read -rp "IP do servidor source (fs-1): " source_ip
    fi
    if [[ -z "$repl_pass" ]]; then
        read -rsp "Senha do usuario de replicacao '${REPL_USER}': " repl_pass
        echo ""
    fi
    if [[ -z "$seed" ]]; then
        read -rp "Caminho do dump de seed gerado no fs-1 (vazio para pular o seed): " seed
    fi

    local port
    port=$(mysql_local_port)
    build_mysql_admin_cnf "$port" || return 1

    disarm_clone_services
    disarm_clone_crontab
    disarm_clone_cdr_spool

    reset_mysql_identity
    configure_replica_mysql

    run systemctl start mysql
    wait_mysql_ready "$port" || return 1
    log_ok "MySQL reiniciado com identidade e configuracao de replica."

    if [[ -n "$seed" ]]; then
        if [[ ! -f "$seed" && $DRY_RUN -eq 0 ]]; then
            log_error "Dump de seed nao encontrado: ${seed}"
            return 1
        fi

        log_info "Restaurando o seed (isso sobrescreve a base local deste no)..."

        if [[ $DRY_RUN -eq 1 ]]; then
            log_dry "SET GLOBAL super_read_only=OFF; RESET MASTER; restore ${seed}"
        else
            mysql --defaults-extra-file="$MYSQL_CNF" -e "SET GLOBAL super_read_only=OFF; SET GLOBAL read_only=OFF; RESET MASTER;"

            local restore_ok=0
            if [[ "$seed" == *.gz ]]; then
                gunzip -c "$seed" | mysql --defaults-extra-file="$MYSQL_CNF" || restore_ok=1
            else
                mysql --defaults-extra-file="$MYSQL_CNF" < "$seed" || restore_ok=1
            fi

            if [[ $restore_ok -ne 0 ]]; then
                log_error "Falha ao restaurar o seed."
                return 1
            fi

            disable_inherited_events
            mysql --defaults-extra-file="$MYSQL_CNF" -e "SET GLOBAL read_only=ON; SET GLOBAL super_read_only=ON;"
            log_ok "Seed restaurado e no devolvido para super_read_only."
        fi
    else
        log_warn "Seed nao informado; a replicacao so funcionara se as bases ja estiverem alinhadas."
        disable_inherited_events
    fi

    start_replication "$source_ip" "$repl_pass" "$source_port" || return 1
    save_replication_credentials "$source_ip" "$source_port" "$repl_pass"
    write_backup_manifest

    log_section "No de banco configurado"
    log_info "Papel:      replica standby (read_only)"
    log_info "Source:     ${source_ip}:${source_port}"
    log_info "Servicos:   desativados (reativaveis em DR)"
    log_info "Snapshot:   ${BACKUP_DIR}"
    log_warn "Este no NAO recebe trafego. Em desastre, use --promote-replica."
}

promote_replica()
{
    log_section "Promover esta replica a servidor primario"

    check_flux_install
    load_db_credentials

    log_error "ATENCAO: promocao manual de replica assincrona."
    log_warn "Transacoes ainda nao replicadas do source serao PERDIDAS."
    log_warn "O servidor de origem NAO pode voltar a escrever antes de ser reconstruido"
    log_warn "como replica deste no, sob pena de split-brain."
    _log ""

    if [[ $DRY_RUN -eq 0 ]]; then
        if [[ ! -t 0 ]]; then
            log_error "Esta acao exige confirmacao interativa."
            return 1
        fi
        local typed
        read -rp "Digite o hostname deste servidor para confirmar a promocao: " typed
        if [[ "$typed" != "$(hostname)" ]]; then
            log_error "Hostname nao confere. Abortado."
            return 1
        fi
        if ! confirm "Confirma que o servidor de origem esta parado e nao voltara a escrever?"; then
            log_info "Promocao cancelada."
            return 0
        fi
    fi

    local port
    port=$(mysql_local_port)
    build_mysql_admin_cnf "$port" || return 1

    local lag
    lag=$(replica_status_field "Seconds_Behind_Source")
    [[ -z "$lag" ]] && lag=$(replica_status_field "Seconds_Behind_Master")
    log_info "Lag no momento da promocao: ${lag:-desconhecido}s"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "STOP REPLICA; RESET REPLICA ALL; read_only=OFF; event_scheduler=ON"
    else
        local stop_stmt reset_stmt
        if mysql_supports_source_syntax; then
            stop_stmt="STOP REPLICA"; reset_stmt="RESET REPLICA ALL"
        else
            stop_stmt="STOP SLAVE"; reset_stmt="RESET SLAVE ALL"
        fi
        mysql --defaults-extra-file="$MYSQL_CNF" -e "${stop_stmt}; ${reset_stmt};"
        mysql --defaults-extra-file="$MYSQL_CNF" -e "SET GLOBAL super_read_only=OFF; SET GLOBAL read_only=OFF; SET GLOBAL event_scheduler=ON;"

        local ev
        for ev in staging_cdrs remove_cdrs_records; do
            mysql --defaults-extra-file="$MYSQL_CNF" -e "ALTER EVENT \`${DB_NAME}\`.\`${ev}\` ENABLE;" 2>/dev/null || true
        done
        log_ok "Replicacao encerrada e no liberado para escrita."
    fi

    backup_file "$MYSQLD_CNF"
    set_cnf_value "read_only" "OFF" "$MYSQLD_CNF"
    set_cnf_value "super_read_only" "OFF" "$MYSQLD_CNF"
    set_cnf_value "event_scheduler" "ON" "$MYSQLD_CNF"
    set_cnf_value "server-id" "$REPL_SOURCE_ID" "$MYSQLD_CNF"

    log_info "Reativando servicos de aplicacao..."
    local svc
    for svc in haproxy php7.3-fpm nginx freeswitch json_cdr fail2ban; do
        if systemctl list-unit-files "${svc}.service" >/dev/null 2>&1; then
            run systemctl enable --now "$svc"
            log_ok "${svc}: ativo"
        fi
    done

    log_warn "event_guard nao foi iniciado automaticamente. Revise a rede antes de ativa-lo:"
    log_warn "  systemctl enable --now event_guard"

    if [[ -f "$CRONTAB_FILE" && ! -s "$CRONTAB_FILE" ]]; then
        log_warn "O crontab esta vazio. Restaure-o do snapshot para reativar os jobs:"
        log_warn "  cp <snapshot>${CRONTAB_FILE} ${CRONTAB_FILE} && crontab -u flux ${CRONTAB_FILE}"
    fi

    write_backup_manifest

    log_section "Promocao concluida"
    log_warn "Reconstrua o servidor antigo como replica ANTES de religa-lo."
    log_warn "Procedimento em ${FLUX_SOURCE_DIR}/docs/HA-MYSQL-REPLICACAO.md"
}

set_topology()
{
    log_section "Definir a topologia de banco"

    check_flux_install
    load_db_credentials

    if [[ -z "$FLUX_DB_HOSTS" ]]; then
        load_topology
        _log ""
        _log "  Topologia atual: ${FLUX_DB_HOSTS}"
        _log ""
        _log "  Formato: lista separada por virgula, na ordem de prioridade de escrita."
        _log "  A palavra 'local' representa o MySQL desta maquina."
        _log "  Exemplo: local,10.0.0.12:3306"
        _log ""
        local typed
        read -rp "Nova topologia (vazio mantem a atual): " typed
        if [[ -n "$typed" ]]; then
            FLUX_DB_HOSTS="$typed"
        fi
    fi

    log_info "Topologia solicitada: ${FLUX_DB_HOSTS}"

    local entry addr host port idx=0 problems=0
    while IFS= read -r entry; do
        entry=$(echo "$entry" | tr -d '[:space:]')
        if [[ -z "$entry" ]]; then
            continue
        fi

        idx=$((idx + 1))
        addr=$(resolve_db_host "$entry")
        host="${addr%%:*}"
        port="${addr##*:}"

        local papel="backup na escrita, ativo na leitura"
        if [[ $idx -eq 1 ]]; then
            papel="ativo na escrita e na leitura"
        fi
        log_info "  ${idx}. $(db_server_name "$entry") -> ${addr} (${papel})"

        if [[ $DRY_RUN -eq 0 ]]; then
            local code
            code=$(curl -s --max-time 3 -o /dev/null -w '%{http_code}' "http://${host}:${DB_CHECK_PORT}/write" 2>/dev/null)
            if [[ "$code" == "200" || "$code" == "503" ]]; then
                log_ok "     agente respondendo em ${host}:${DB_CHECK_PORT} (/write = ${code})"
            else
                log_error "     agente NAO respondeu em ${host}:${DB_CHECK_PORT}"
                log_error "     instale o flux-dbcheck neste host antes de inclui-lo na topologia."
                problems=$((problems + 1))
            fi

            if ! (exec 3<>"/dev/tcp/${host}/${port}") 2>/dev/null; then
                log_warn "     porta MySQL ${host}:${port} inacessivel deste servidor"
            fi
        fi
    done <<< "$(echo "$FLUX_DB_HOSTS" | tr ',' '\n')"

    if [[ $idx -eq 0 ]]; then
        log_error "Topologia vazia."
        return 1
    fi

    if [[ $problems -gt 0 ]]; then
        log_error "${problems} host(s) sem o agente de health check. Topologia nao aplicada."
        return 1
    fi

    write_haproxy_config "$FLUX_DB_HOSTS" || return 1
    save_topology "$FLUX_DB_HOSTS"
    write_backup_manifest

    log_section "Topologia aplicada"
    log_info "Escrita: primeiro host da lista; os demais assumem apenas se forem promovidos."
    log_info "Leitura: todos os hosts, com o agente retirando quem passar de ${FLUX_DB_READ_MAX_LAG}s de atraso."
    log_info "Estado:  echo \"show stat\" | socat /run/haproxy/admin.sock stdio | cut -d, -f1,2,18"
}

rollback()
{
    log_section "Rollback"

    if [[ ! -d "$BACKUP_ROOT" ]]; then
        log_error "Nenhum snapshot encontrado em ${BACKUP_ROOT}."
        return 1
    fi

    local last
    last=$(ls -1 "$BACKUP_ROOT" 2>/dev/null | sort | tail -n1)
    if [[ -z "$last" ]]; then
        log_error "Nenhum snapshot encontrado em ${BACKUP_ROOT}."
        return 1
    fi

    local snap="${BACKUP_ROOT}/${last}"
    log_info "Snapshot selecionado: ${snap}"
    if [[ -f "${snap}/MANIFEST.txt" ]]; then
        sed 's/^/          /' "${snap}/MANIFEST.txt" | head -20 | while IFS= read -r l; do log_info "$l"; done
    fi

    if [[ $DRY_RUN -eq 0 ]] && [[ -t 0 ]]; then
        if ! confirm "Restaurar este snapshot e desativar o HAProxy?"; then
            log_info "Rollback cancelado."
            return 0
        fi
    fi

    run systemctl stop haproxy
    run systemctl disable haproxy

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "cp -a ${snap}/etc ${snap}/var / (arquivos de configuracao)"
    else
        local d
        for d in etc var; do
            if [[ -d "${snap}/${d}" ]]; then
                cp -a "${snap}/${d}" /
            fi
        done
    fi

    run systemctl restart mysql
    run systemctl restart php7.3-fpm
    run systemctl restart nginx

    log_ok "Configuracao restaurada a partir de ${snap}."
    log_warn "Reinicie o FreeSWITCH para restabelecer os DSNs originais: systemctl restart freeswitch"
}

backup_only()
{
    log_section "Somente backup"

    check_flux_install
    load_db_credentials

    local port="3306"
    if haproxy_already_enabled; then
        port="$DB_LOCAL_PORT"
    fi

    build_mysql_cnf "127.0.0.1" "$port" "$DB_USER" "$DB_PASS"

    backup_file "$FLUX_CONF"
    backup_file "$FLUX_LUA"
    backup_file "$ODBC_INI"
    backup_file "$MYSQLD_CNF"
    backup_file "$HAPROXY_CFG"
    backup_file "$FS_VARS"
    backup_file "$FS_NIBBLEBILL"
    backup_database "127.0.0.1" "$port" "$DB_NAME"
    write_backup_manifest

    log_ok "Snapshot concluido."
}

#############################
#  Interface
#############################

show_help()
{
    cat <<EOF
FluxSBC - camada de alta disponibilidade de MySQL com HAProxy

Habilita o HAProxy para MySQL em um FluxSBC ja instalado, em duas etapas:

  Etapa 1  O MySQL local sai da porta ${DB_WRITE_PORT} para a ${DB_LOCAL_PORT} e o HAProxy
           assume a ${DB_WRITE_PORT} (escrita) e a ${DB_READ_PORT} (leitura). A aplicacao
           passa a falar com o proxy sem nenhuma alteracao de codigo.

  Etapa 2  Reponta os backends para um cluster Galera, migrando os dados.

Uso: $0 [opcao]

Opcoes de HAProxy:
  --enable-local      Etapa 1: HAProxy sobre o MySQL local
  --migrate-galera    Etapa 2: migra os dados e reponta para o cluster Galera
  --set-topology      Define a lista de hosts do pool (FLUX_DB_HOSTS)
  --install-agent     Instala o agente de health check flux-dbcheck neste host
  --verify-config     Audita a configuracao deste no (detecta o papel sozinho)

Opcoes de replicacao (dois servidores, MySQL 8 + GTID):
  --add-replica       No servidor de producao: prepara como source e gera o seed
  --make-db-node      No servidor reserva: desarma o clone e configura a replica
  --promote-replica   No servidor reserva: promove a primario (apenas em desastre)

Operacao:
  --pk-migration      Aplica update-18-08-2026.sql (PRIMARY KEY nas tabelas de CDR)
  --status            Mostra o estado atual da configuracao
  --backup-only       Gera apenas o snapshot de configuracao e o dump
  --rollback          Restaura o snapshot mais recente
  --dry-run           Simula, sem alterar nada (combinavel com as demais)
  -h, --help          Esta ajuda

Sem opcoes, abre o menu interativo.

Topologia:
  FLUX_DB_HOSTS         lista separada por virgula, na ordem de prioridade de escrita.
                        "local" representa o MySQL desta maquina.
                        Ex.: FLUX_DB_HOSTS="local,10.0.0.12:3306"
  FLUX_DB_READ_MAX_LAG  atraso maximo, em segundos, para um no seguir no pool de
                        leitura (padrao 30)

Variaveis de ambiente da replicacao:
  REPL_SOURCE_HOST      IP privado do servidor source (producao)
  REPL_REPLICA_HOST     IP do servidor replica
  REPL_SOURCE_PORT      porta do MySQL no source (padrao 3306)
  REPL_PASSWORD         senha do usuario de replicacao
  REPL_USER             nome do usuario de replicacao (padrao repl)
  SEED_DUMP             caminho do dump gerado por --add-replica
  MYSQL_ADMIN_USER      usuario administrativo local (padrao root)
  MYSQL_ADMIN_PASSWORD  senha administrativa local

Variaveis de ambiente aceitas na etapa 2:
  FLUX_DB_NODES         "10.0.0.11:3306,10.0.0.12:3306,10.0.0.13:3306"
  MYSQL_ADMIN_HOST      no do cluster para carga (padrao: primeiro de FLUX_DB_NODES)
  MYSQL_ADMIN_PORT      padrao 3306
  MYSQL_ADMIN_USER      padrao root
  MYSQL_ADMIN_PASSWORD  senha administrativa do cluster

Portas (sobrescreviveis por ambiente):
  DB_WRITE_PORT=${DB_WRITE_PORT}  DB_READ_PORT=${DB_READ_PORT}  DB_LOCAL_PORT=${DB_LOCAL_PORT}  DB_STATS_PORT=${DB_STATS_PORT}

Backups:
  Snapshots em ${BACKUP_ROOT}/<timestamp>/, com MANIFEST.txt e dump do banco.
  Restauracao: $0 --rollback

Documentacao:
  ${FLUX_SOURCE_DIR}/docs/HA-MYSQL-HAPROXY.md    (HAProxy / Galera)
  ${FLUX_SOURCE_DIR}/docs/HA-MYSQL-REPLICACAO.md (replicacao com dois servidores)
EOF
}

print_banner()
{
    local mode="Execucao real"
    if [[ $DRY_RUN -eq 1 ]]; then
        mode="DRY-RUN (nenhuma alteracao sera feita)"
    fi

    _log ""
    _log "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
    _log "${BLUE}║${NC}  FluxSBC - Alta disponibilidade de MySQL (HAProxy)            ${BLUE}║${NC}"
    _log "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
    _log "  Modo: ${mode}"
    _log "  Log:  ${LOG_FILE}"
    _log ""
}

show_menu()
{
    cat <<EOF

  -- HAProxy --------------------------------------------------
  1) Etapa 1  - habilitar HAProxy sobre o MySQL local
  2) Etapa 2  - migrar para cluster Galera
 13) Topologia - definir a lista de hosts do pool
 14) Agente    - instalar o flux-dbcheck neste host
 15) Auditar   - verificar a configuracao deste no

  -- Replicacao (2 servidores) --------------------------------
  3) No fs-1     - preparar como source e gerar o seed
  4) No fs-db-1  - desarmar o clone e configurar como replica
  5) No fs-db-1  - promover a primario (apenas em desastre)

  -- Operacao -------------------------------------------------
  6) Status   - inspecionar a configuracao atual
  7) Migracao - aplicar PRIMARY KEY nas tabelas de CDR
  8) Backup   - gerar snapshot de configuracao e dump
  9) Verificar instalacao
 10) Reiniciar o FreeSWITCH (derruba chamadas ativas)
 11) Rollback - restaurar o snapshot mais recente
 12) Alternar dry-run (atual: $([[ $DRY_RUN -eq 1 ]] && echo LIGADO || echo desligado))
  0) Sair

EOF
}

interactive_loop()
{
    if [[ ! -t 0 ]]; then
        log_error "Menu interativo exige terminal. Use uma das flags (--help)."
        exit 1
    fi

    local opt
    while true; do
        show_menu
        read -rp "Opcao: " opt
        case "$opt" in
            1) enable_local ;;
            2) migrate_galera || true ;;
            13) set_topology || true ;;
            14) load_db_credentials; install_dbcheck_agent || true ;;
            15) verify_config || true ;;
            3) add_replica || true ;;
            4) make_db_node || true ;;
            5) promote_replica || true ;;
            6) show_status ;;
            7) apply_pk_migration || true ;;
            8) backup_only ;;
            9) load_db_credentials; verify_setup || true ;;
            10)
                if confirm "Reiniciar o FreeSWITCH agora? Chamadas ativas serao derrubadas"; then
                    run systemctl restart freeswitch
                    log_ok "FreeSWITCH reiniciado."
                fi
                ;;
            11) rollback || true ;;
            12)
                if [[ $DRY_RUN -eq 1 ]]; then DRY_RUN=0; else DRY_RUN=1; fi
                log_info "dry-run agora: $([[ $DRY_RUN -eq 1 ]] && echo LIGADO || echo desligado)"
                ;;
            0)
                write_backup_manifest
                log_info "Encerrando."
                exit 0
                ;;
            *) log_warn "Opcao invalida." ;;
        esac
    done
}

parse_args()
{
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --enable-local)   ACTION="enable_local" ;;
            --migrate-galera) ACTION="migrate_galera" ;;
            --add-replica)    ACTION="add_replica" ;;
            --make-db-node)   ACTION="make_db_node" ;;
            --promote-replica) ACTION="promote_replica" ;;
            --set-topology)   ACTION="set_topology" ;;
            --install-agent)  ACTION="install_dbcheck_agent" ;;
            --verify-config)  ACTION="verify_config" ;;
            --pk-migration)   ACTION="apply_pk_migration" ;;
            --status)         ACTION="show_status" ;;
            --backup-only)    ACTION="backup_only" ;;
            --rollback)       ACTION="rollback" ;;
            --dry-run)        DRY_RUN=1 ;;
            -h|--help)        show_help; exit 0 ;;
            *)
                log_error "Opcao desconhecida: $1"
                show_help
                exit 1
                ;;
        esac
        shift
    done
}

main()
{
    mkdir -p "$FLUXLOGDIR" 2>/dev/null || true
    parse_args "$@"
    print_banner
    check_root
    check_debian

    case "$ACTION" in
        enable_local)       enable_local ;;
        migrate_galera)     migrate_galera ;;
        add_replica)        add_replica ;;
        make_db_node)       make_db_node ;;
        promote_replica)    promote_replica ;;
        set_topology)       set_topology ;;
        install_dbcheck_agent) load_db_credentials; install_dbcheck_agent ;;
        verify_config)      verify_config ;;
        apply_pk_migration) apply_pk_migration ;;
        show_status)        show_status ;;
        backup_only)        backup_only ;;
        rollback)           rollback ;;
        *)                  interactive_loop ;;
    esac
}

main "$@"
