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

create_check_user()
{
    log_info "Criando usuario de health check '${HAPROXY_CHECK_USER}'..."
    build_mysql_cnf "127.0.0.1" "$DB_LOCAL_PORT" "$DB_USER" "$DB_PASS"
    mysql_run -e "CREATE USER IF NOT EXISTS '${HAPROXY_CHECK_USER}'@'127.0.0.1';" 2>/dev/null \
        || log_warn "Nao foi possivel criar '${HAPROXY_CHECK_USER}' com o usuario da aplicacao; o check TCP simples sera usado."
}

write_haproxy_config()
{
    local mode="$1"
    local nodes="$2"
    local check_option
    local tmp_write tmp_read

    tmp_write=$(mktemp)
    tmp_read=$(mktemp)

    if [[ "$mode" == "local" ]]; then
        check_option="option mysql-check user ${HAPROXY_CHECK_USER}"
        printf '    server mysqllocal 127.0.0.1:%s check\n' "$DB_LOCAL_PORT" > "$tmp_write"
        cp "$tmp_write" "$tmp_read"
    else
        check_option="option httpchk GET /"
        local idx=0 node
        while IFS= read -r node; do
            node=$(echo "$node" | tr -d '[:space:]')
            if [[ -z "$node" ]]; then
                continue
            fi
            idx=$((idx + 1))
            if [[ $idx -eq 1 ]]; then
                printf '    server galera%s %s check port %s\n' "$idx" "$node" "$DB_CHECK_PORT" >> "$tmp_write"
            else
                printf '    server galera%s %s check port %s backup\n' "$idx" "$node" "$DB_CHECK_PORT" >> "$tmp_write"
            fi
            printf '    server galera%s %s check port %s\n' "$idx" "$node" "$DB_CHECK_PORT" >> "$tmp_read"
        done <<< "$(echo "$nodes" | tr ',' '\n')"

        if [[ $idx -eq 0 ]]; then
            log_error "Nenhum no valido informado: ${nodes}"
            rm -f "$tmp_write" "$tmp_read"
            exit 1
        fi
    fi

    backup_file "$HAPROXY_CFG"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Gerando ${HAPROXY_CFG} a partir de ${HAPROXY_TPL} (modo ${mode})"
        log_dry "Backends:"
        sed 's/^/          /' "$tmp_write" | while IFS= read -r l; do log_dry "$l"; done
        rm -f "$tmp_write" "$tmp_read"
        return 0
    fi

    local staged
    staged=$(mktemp)
    cp "$HAPROXY_TPL" "$staged"
    sed -i "s#__FLUX_DB_WRITE_BIND__#127.0.0.1:${DB_WRITE_PORT}#g" "$staged"
    sed -i "s#__FLUX_DB_READ_BIND__#127.0.0.1:${DB_READ_PORT}#g" "$staged"
    sed -i "s#__FLUX_DB_STATS_BIND__#127.0.0.1:${DB_STATS_PORT}#g" "$staged"
    sed -i "s#__FLUX_DB_CHECK_OPTION__#${check_option}#g" "$staged"
    sed -i -e "/__FLUX_DB_WRITE_SERVERS__/r ${tmp_write}" -e "/__FLUX_DB_WRITE_SERVERS__/d" "$staged"
    sed -i -e "/__FLUX_DB_READ_SERVERS__/r ${tmp_read}" -e "/__FLUX_DB_READ_SERVERS__/d" "$staged"
    rm -f "$tmp_write" "$tmp_read"

    if ! haproxy -c -f "$staged" >/dev/null 2>&1; then
        log_error "Configuracao gerada do HAProxy e invalida:"
        haproxy -c -f "$staged" || true
        rm -f "$staged"
        exit 1
    fi

    mv "$staged" "$HAPROXY_CFG"
    chmod 644 "$HAPROXY_CFG"
    log_ok "Configuracao do HAProxy validada e aplicada (modo ${mode})."

    systemctl enable haproxy >/dev/null 2>&1 || true
    systemctl restart haproxy
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

    if [[ -d "$BACKUP_ROOT" ]]; then
        log_info "Snapshots disponiveis: $(ls -1 "$BACKUP_ROOT" 2>/dev/null | wc -l)"
    fi
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
    create_check_user
    write_haproxy_config "local" ""
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

    write_haproxy_config "galera" "$nodes"
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

Opcoes:
  --enable-local      Etapa 1: HAProxy sobre o MySQL local
  --migrate-galera    Etapa 2: migra os dados e reponta para o cluster Galera
  --pk-migration      Aplica update-18-08-2026.sql (PRIMARY KEY nas tabelas de CDR)
  --status            Mostra o estado atual da configuracao
  --backup-only       Gera apenas o snapshot de configuracao e o dump
  --rollback          Restaura o snapshot mais recente
  --dry-run           Simula, sem alterar nada (combinavel com as demais)
  -h, --help          Esta ajuda

Sem opcoes, abre o menu interativo.

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

Documentacao: ${FLUX_SOURCE_DIR}/docs/HA-MYSQL-HAPROXY.md
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

  1) Etapa 1  - habilitar HAProxy sobre o MySQL local
  2) Etapa 2  - migrar para cluster Galera
  3) Status   - inspecionar a configuracao atual
  4) Migracao - aplicar PRIMARY KEY nas tabelas de CDR
  5) Backup   - gerar snapshot de configuracao e dump
  6) Verificar instalacao
  7) Reiniciar o FreeSWITCH (derruba chamadas ativas)
  8) Rollback - restaurar o snapshot mais recente
  9) Alternar dry-run (atual: $([[ $DRY_RUN -eq 1 ]] && echo LIGADO || echo desligado))
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
            3) show_status ;;
            4) apply_pk_migration || true ;;
            5) backup_only ;;
            6) load_db_credentials; verify_setup || true ;;
            7)
                if confirm "Reiniciar o FreeSWITCH agora? Chamadas ativas serao derrubadas"; then
                    run systemctl restart freeswitch
                    log_ok "FreeSWITCH reiniciado."
                fi
                ;;
            8) rollback || true ;;
            9)
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
        apply_pk_migration) apply_pk_migration ;;
        show_status)        show_status ;;
        backup_only)        backup_only ;;
        rollback)           rollback ;;
        *)                  interactive_loop ;;
    esac
}

main "$@"
