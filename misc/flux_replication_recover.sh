#!/usr/bin/env bash
# Diagnose and safely recover a FluxSBC MySQL replica.
set -uo pipefail

FLUXDIR="${FLUXDIR:-/var/lib/flux}"
FLUX_CONF="${FLUX_CONF:-${FLUXDIR}/flux-config.conf}"
MYSQLD_CNF="${MYSQLD_CNF:-/etc/mysql/mysql.conf.d/mysqld.cnf}"
HA_TOPOLOGY_CONF="${HA_TOPOLOGY_CONF:-${FLUXDIR}/ha-topology.conf}"
HA_SETUP="${HA_SETUP:-/opt/flux/misc/flux_ha_setup.sh}"
LAG_THRESHOLD="${LAG_THRESHOLD:-30}"
log() { printf '[%s] %s\n' "$1" "$2"; }
fail() { log FAIL "$1"; return 1; }

conf_value() {
    local key="$1" file="$2"
    sed -n "s/^[[:space:]]*${key}[[:space:]]*=[[:space:]]*//p" "$file" 2>/dev/null | head -n1
}

mysql_port() {
    local port
    port="$(conf_value port "$MYSQLD_CNF")"
    printf '%s' "${port:-3316}"
}

build_cnf() {
    local user pass port
    user="$(conf_value dbuser "$FLUX_CONF")"
    pass="$(conf_value dbpass "$FLUX_CONF")"
    port="$(mysql_port)"
    [[ -n "$user" && -n "$pass" ]] || return 1
    MYSQL_CNF="$(mktemp)"
    chmod 600 "$MYSQL_CNF"
    printf '[client]\nhost = 127.0.0.1\nport = %s\nprotocol = TCP\nuser = %s\npassword = %s\n' "$port" "$user" "$pass" > "$MYSQL_CNF"
}

cleanup() { [[ -n "${MYSQL_CNF:-}" && -f "$MYSQL_CNF" ]] && rm -f "$MYSQL_CNF"; }
trap cleanup EXIT

replica_status() {
    mysql --defaults-extra-file="$MYSQL_CNF" -e 'SHOW REPLICA STATUS\G' 2>/dev/null || \
        mysql --defaults-extra-file="$MYSQL_CNF" -e 'SHOW SLAVE STATUS\G' 2>/dev/null
}

field() { sed -n "s/^[[:space:]]*$1:[[:space:]]*//p" <<< "$STATUS" | head -n1; }

audit_config() {
    local port
    port="$(mysql_port)"
    log INFO "MySQL config: ${MYSQLD_CNF}"
    for key in port server-id gtid_mode enforce_gtid_consistency binlog_format log_bin bind-address read_only super_read_only event_scheduler relay_log; do
        log INFO "${key}=$(conf_value "$key" "$MYSQLD_CNF" || true)"
    done
    log INFO "MySQL expected on 127.0.0.1:${port}"
    ss -lnt 2>/dev/null | grep -E "[:.]${port}$|:9200$" || log WARN "Listeners MySQL/health check not found by ss"
    [[ -r "$HA_TOPOLOGY_CONF" ]] && log INFO "$(<"$HA_TOPOLOGY_CONF")" || log WARN "Topology file absent: ${HA_TOPOLOGY_CONF}"
    [[ "$(conf_value read_only "$MYSQLD_CNF")" == "ON" && "$(conf_value super_read_only "$MYSQLD_CNF")" == "ON" ]] || log WARN 'Replica is not protected by read_only and super_read_only.'
}

check() {
    build_cnf || { fail "Cannot read DB credentials from ${FLUX_CONF}"; return 2; }
    STATUS="$(replica_status)"
    [[ -n "$STATUS" ]] || { fail 'This host has no configured replication channel.'; return 2; }
    local io sql lag io_error sql_error
    io="$(field Replica_IO_Running)"; [[ -z "$io" ]] && io="$(field Slave_IO_Running)"
    sql="$(field Replica_SQL_Running)"; [[ -z "$sql" ]] && sql="$(field Slave_SQL_Running)"
    lag="$(field Seconds_Behind_Source)"; [[ -z "$lag" ]] && lag="$(field Seconds_Behind_Master)"
    io_error="$(field Last_IO_Error)"; sql_error="$(field Last_SQL_Error)"
    log INFO "IO=${io:-unknown} SQL=${sql:-unknown} lag=${lag:-unknown}"
    [[ -n "$io_error" ]] && log FAIL "Last_IO_Error: ${io_error}"
    [[ -n "$sql_error" ]] && log FAIL "Last_SQL_Error: ${sql_error}"
    if [[ "$io" != Yes || "$sql" != Yes || ! "$lag" =~ ^[0-9]+$ || "$lag" -gt "$LAG_THRESHOLD" || -n "$io_error" || -n "$sql_error" ]]; then
        return 1
    fi
    log OK "Replica healthy and within ${LAG_THRESHOLD}s."
    return 0
}

repair() {
    check && return 0
    build_cnf || return 2
    STATUS="$(replica_status)"
    [[ -n "$STATUS" ]] || { fail 'No replication channel is configured on this host.'; return 2; }
    local io_error sql_error
    io_error="$(field Last_IO_Error)"; sql_error="$(field Last_SQL_Error)"
    if [[ -n "$io_error" || -n "$sql_error" ]]; then
        fail 'Replication has an IO/SQL error. Review it or use --reseed; transactions are never skipped automatically.'
        return 1
    fi
    local read_only super_read_only
    read_only=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e 'SELECT @@GLOBAL.read_only;' 2>/dev/null)
    super_read_only=$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B -e 'SELECT @@GLOBAL.super_read_only;' 2>/dev/null)
    if [[ "$read_only" != '1' || "$super_read_only" != '1' ]]; then
        fail 'Replica is not protected by read_only and super_read_only; repair aborted.'
        return 1
    fi
    log INFO 'Starting replication threads after transient degradation.'
    mysql --defaults-extra-file="$MYSQL_CNF" -e 'START REPLICA;' 2>/dev/null || \
        mysql --defaults-extra-file="$MYSQL_CNF" -e 'START SLAVE;'
    sleep 3
    check
}

reseed() {
    [[ -n "${SEED_DUMP:-}" && -n "${REPL_SOURCE_HOST:-}" && -n "${REPL_PASSWORD:-}" ]] || {
        fail 'RESEED requires SEED_DUMP, REPL_SOURCE_HOST and REPL_PASSWORD.'; return 2; }
    [[ -x "$HA_SETUP" ]] || { fail "HA setup script not found: ${HA_SETUP}"; return 2; }
    local expected
    expected="$(hostname)"
    read -rp "Type ${expected} to replace this replica from the seed: " typed
    [[ "$typed" == "$expected" ]] || { fail 'Hostname confirmation failed.'; return 1; }
    read -rp 'Type RESEED to continue: ' typed
    [[ "$typed" == RESEED ]] || { fail 'Reseed cancelled.'; return 1; }
    exec "$HA_SETUP" --make-db-node
}

case "${1:-}" in
    --audit-config) audit_config ;;
    --repair) repair ;;
    --reseed) reseed ;;
    --check|'') check ;;
    -h|--help) echo "Usage: $0 [--check|--audit-config|--repair|--reseed]" ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
esac
