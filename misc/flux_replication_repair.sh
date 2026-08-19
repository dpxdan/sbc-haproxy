#!/usr/bin/env bash
# Flux SBC - Unindo pessoas e negócios
# Copyright (C) 2026 Flux Telecom
# Daniel Paixao <daniel@flux.net.br>
# FluxSBC Version 6.4 and above
# License https://www.gnu.org/licenses/agpl-3.0.html
# Resolucao automatica de replicacao degradada
# Testado em: Debian 11 (Bullseye)
#
# Uso:
#   ./flux_replication_repair.sh                 diagnostica e corrige
#   ./flux_replication_repair.sh --dry-run       simula
#   ./flux_replication_repair.sh --max-level 2   nunca re-semeia
#   ./flux_replication_repair.sh --status        ultimo reparo e cooldown
#   ./flux_replication_repair.sh --quiet         para cron
set -uo pipefail

FLUXDIR="${FLUXDIR:-/var/lib/flux}"
FLUX_CONF="${FLUX_CONF:-${FLUXDIR}/flux-config.conf}"
MYSQLD_CNF="${MYSQLD_CNF:-/etc/mysql/mysql.conf.d/mysqld.cnf}"
FLUXLOGDIR="${FLUXLOGDIR:-/var/log/flux}"
LOG_FILE="${LOG_FILE:-${FLUXLOGDIR}/replication_repair.log}"

REPL_CNF="${REPL_CNF:-/etc/mysql/flux-replication.cnf}"
REPAIR_CNF="${REPAIR_CNF:-/etc/mysql/flux-repair.cnf}"
CHECK_SCRIPT="${CHECK_SCRIPT:-$(dirname "$0")/flux_replication_check.sh}"

LOCK_FILE="${LOCK_FILE:-/var/lock/flux-replication-repair.lock}"
RESEED_STAMP="${RESEED_STAMP:-${FLUXDIR}/.last_reseed}"
RESEED_COOLDOWN="${RESEED_COOLDOWN:-21600}"
RESEED_WINDOW="${RESEED_WINDOW:-}"
RESEED_JITTER_MAX="${RESEED_JITTER_MAX:-60}"
RESEED_MIN_FREE_MB="${RESEED_MIN_FREE_MB:-2048}"

DRY_RUN=0
QUIET=0
FORCE=0
MAX_LEVEL=3
ACTION="repair"
MYSQL_CNF=""
LEVEL_APPLIED=0

if [[ -t 1 ]]; then
    RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; BLUE=$'\033[0;34m'; NC=$'\033[0m'
else
    RED=""; GREEN=""; YELLOW=""; BLUE=""; NC=""
fi

log()
{
    local level="$1"; shift
    local color=""
    case "$level" in
        OK)   color="$GREEN" ;;
        WARN) color="$YELLOW" ;;
        FAIL) color="$RED" ;;
        DRY)  color="$YELLOW" ;;
        INFO) color="$BLUE" ;;
    esac

    if [[ $QUIET -eq 0 ]]; then
        echo "${color}[${level}]${NC} $*"
    fi
    if [[ -d "$FLUXLOGDIR" ]]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') [${level}] $*" >> "$LOG_FILE" 2>/dev/null || true
    fi
}

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
FluxSBC - resolucao automatica de replicacao degradada

Diagnostica pelo flux_replication_check.sh e corrige em tres niveis, parando
no primeiro que resolver:

  Nivel 1  Threads paradas sem erro          -> START REPLICA
  Nivel 2  Erro transitorio na thread SQL    -> STOP + START REPLICA
  Nivel 3  Divergencia confirmada            -> re-seed completo do master

Uso: $0 [opcoes]

  --dry-run        Mostra o que faria, sem executar
  --max-level N    Limita a escalada (1, 2 ou 3). --max-level 2 nunca re-semeia
  --force          Ignora cooldown e janela de manutencao
  --quiet          Nao escreve na saida padrao, apenas em ${LOG_FILE}
  --status         Mostra o ultimo reparo e o cooldown restante
  -h, --help       Esta ajuda

Codigos de saida:
  0  no saudavel ao final
  1  a correcao nao resolveu
  2  abortado por salvaguarda (papel errado, cooldown, trava, janela)

Salvaguardas do re-seed:
  - so executa em no com replicacao configurada e super_read_only=ON
  - trava de concorrencia em ${LOCK_FILE}
  - cooldown de ${RESEED_COOLDOWN}s entre re-seeds (RESEED_COOLDOWN)
  - janela opcional RESEED_WINDOW="01:00-05:00"
  - jitter de ate ${RESEED_JITTER_MAX}s para nao coincidir com outras replicas
  - exige ${RESEED_MIN_FREE_MB} MB livres antes de comecar

Registrar no cron da replica:
  */5 * * * * /opt/flux/misc/flux_replication_repair.sh --quiet
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)   DRY_RUN=1 ;;
        --quiet)     QUIET=1 ;;
        --force)     FORCE=1 ;;
        --max-level) shift; MAX_LEVEL="${1:-3}" ;;
        --status)    ACTION="status" ;;
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

run()
{
    if [[ $DRY_RUN -eq 1 ]]; then
        log DRY "$*"
        return 0
    fi
    "$@"
}

mysql_admin()
{
    if [[ $DRY_RUN -eq 1 ]]; then
        log DRY "mysql --defaults-extra-file=*** $*"
        return 0
    fi
    mysql --defaults-extra-file="$REPAIR_CNF" "$@"
}

mysql_admin_q()
{
    mysql --defaults-extra-file="$REPAIR_CNF" -N -B -e "$1" 2>/dev/null
}

show_status()
{
    local saved_log="$LOG_FILE"
    LOG_FILE="/dev/null"

    log INFO "Log de reparos: ${saved_log}"

    if [[ -f "$RESEED_STAMP" ]]; then
        local last now elapsed remaining
        last=$(cat "$RESEED_STAMP" 2>/dev/null || echo 0)
        now=$(date +%s)
        elapsed=$(( now - last ))
        log INFO "Ultimo re-seed: $(date -d "@${last}" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || date -r "${last}" '+%Y-%m-%d %H:%M:%S' 2>/dev/null) (ha ${elapsed}s)"
        if [[ $elapsed -lt $RESEED_COOLDOWN ]]; then
            remaining=$(( RESEED_COOLDOWN - elapsed ))
            log WARN "Cooldown ativo: faltam ${remaining}s para um novo re-seed."
        else
            log OK "Cooldown cumprido; um novo re-seed e permitido."
        fi
    else
        log INFO "Nenhum re-seed registrado neste no."
    fi

    if [[ -n "$RESEED_WINDOW" ]]; then
        log INFO "Janela de re-seed: ${RESEED_WINDOW}"
    else
        log INFO "Janela de re-seed: sem restricao de horario"
    fi

    if [[ -f "$saved_log" ]]; then
        log INFO "Ultimas linhas do log:"
        tail -5 "$saved_log" | while IFS= read -r l; do log INFO "  $l"; done
    fi

    LOG_FILE="$saved_log"
}

require_replica_role()
{
    if [[ ! -r "$REPAIR_CNF" ]]; then
        log FAIL "Credencial administrativa ausente: ${REPAIR_CNF}"
        log FAIL "Rode o flux_ha_setup.sh --make-db-node neste no para gera-la."
        return 1
    fi

    local sro
    sro=$(mysql_admin_q "SELECT @@GLOBAL.super_read_only;")
    local repl
    repl=$(mysql --defaults-extra-file="$REPAIR_CNF" -e "SHOW REPLICA STATUS\G" 2>/dev/null)
    if [[ -z "$repl" ]]; then
        repl=$(mysql --defaults-extra-file="$REPAIR_CNF" -e "SHOW SLAVE STATUS\G" 2>/dev/null)
    fi

    if [[ -z "$repl" ]]; then
        log FAIL "Este no nao tem replicacao configurada."
        log FAIL "O reparo NAO roda em servidor primario - abortando por seguranca."
        return 1
    fi

    if [[ "$sro" != "1" ]]; then
        log FAIL "super_read_only=OFF: este no aceita escrita e pode ser um primario promovido."
        log FAIL "O re-seed apagaria dados que talvez nao existam em outro lugar - abortando."
        return 1
    fi

    return 0
}

replica_field()
{
    local out
    out=$(mysql --defaults-extra-file="$REPAIR_CNF" -e "SHOW REPLICA STATUS\G" 2>/dev/null)
    if [[ -z "$out" ]]; then
        out=$(mysql --defaults-extra-file="$REPAIR_CNF" -e "SHOW SLAVE STATUS\G" 2>/dev/null)
    fi
    printf '%s' "$out" | grep -E "^[[:space:]]*$1:" | head -n1 | cut -d: -f2- | sed -e 's/^[[:space:]]*//'
}

supports_source_syntax()
{
    local v
    v=$(mysql_admin_q "SELECT VERSION();" | head -1)
    case "$v" in
        5.*|8.0.1?|8.0.2[0-2]*) return 1 ;;
        *) return 0 ;;
    esac
}

start_replica_stmt()
{
    if supports_source_syntax; then echo "START REPLICA"; else echo "START SLAVE"; fi
}

stop_replica_stmt()
{
    if supports_source_syntax; then echo "STOP REPLICA"; else echo "STOP SLAVE"; fi
}

replication_healthy()
{
    local io sql
    io=$(replica_field "Replica_IO_Running")
    [[ -z "$io" ]] && io=$(replica_field "Slave_IO_Running")
    sql=$(replica_field "Replica_SQL_Running")
    [[ -z "$sql" ]] && sql=$(replica_field "Slave_SQL_Running")
    [[ "$io" == "Yes" && "$sql" == "Yes" ]]
}

level1_start()
{
    log INFO "Nivel 1: religando a replicacao..."

    if [[ $DRY_RUN -eq 1 ]]; then
        log DRY "$(start_replica_stmt)"
        return 0
    fi

    mysql --defaults-extra-file="$REPAIR_CNF" -e "$(start_replica_stmt);" 2>/dev/null
    sleep 3

    if replication_healthy; then
        LEVEL_APPLIED=1
        log OK "Nivel 1 resolveu: threads de replicacao em execucao."
        return 0
    fi

    log WARN "Nivel 1 nao resolveu."
    return 1
}

level2_restart()
{
    log INFO "Nivel 2: reiniciando as threads de replicacao..."

    if [[ $DRY_RUN -eq 1 ]]; then
        log DRY "$(stop_replica_stmt); $(start_replica_stmt)"
        return 0
    fi

    mysql --defaults-extra-file="$REPAIR_CNF" -e "$(stop_replica_stmt);" 2>/dev/null
    sleep 2
    mysql --defaults-extra-file="$REPAIR_CNF" -e "$(start_replica_stmt);" 2>/dev/null
    sleep 5

    if replication_healthy; then
        LEVEL_APPLIED=2
        log OK "Nivel 2 resolveu: threads reiniciadas com sucesso."
        return 0
    fi

    log WARN "Nivel 2 nao resolveu."
    return 1
}

within_reseed_window()
{
    if [[ -z "$RESEED_WINDOW" || $FORCE -eq 1 ]]; then
        return 0
    fi

    local start_h end_h now_h
    start_h="${RESEED_WINDOW%%-*}"
    end_h="${RESEED_WINDOW##*-}"
    now_h=$(date '+%H:%M')

    if [[ "$start_h" < "$end_h" ]]; then
        [[ "$now_h" > "$start_h" && "$now_h" < "$end_h" ]]
    else
        [[ "$now_h" > "$start_h" || "$now_h" < "$end_h" ]]
    fi
}

cooldown_ok()
{
    if [[ $FORCE -eq 1 ]]; then
        log WARN "--force: cooldown ignorado."
        return 0
    fi

    if [[ ! -f "$RESEED_STAMP" ]]; then
        return 0
    fi

    local last now elapsed
    last=$(cat "$RESEED_STAMP" 2>/dev/null || echo 0)
    now=$(date +%s)
    elapsed=$(( now - last ))

    if [[ $elapsed -lt $RESEED_COOLDOWN ]]; then
        log FAIL "Cooldown ativo: ultimo re-seed ha ${elapsed}s, minimo de ${RESEED_COOLDOWN}s."
        log FAIL "Faltam $(( RESEED_COOLDOWN - elapsed ))s. Use --force para ignorar."
        return 1
    fi

    return 0
}

disk_space_ok()
{
    local free_mb
    free_mb=$(df -Pm /var/lib/mysql 2>/dev/null | awk 'NR==2 {print $4}')

    if [[ -z "$free_mb" ]]; then
        log WARN "Nao foi possivel medir o espaco livre; prosseguindo."
        return 0
    fi

    if [[ "$free_mb" -lt "$RESEED_MIN_FREE_MB" ]]; then
        log FAIL "Espaco livre insuficiente: ${free_mb} MB (minimo ${RESEED_MIN_FREE_MB} MB)."
        return 1
    fi

    log OK "Espaco livre: ${free_mb} MB."
    return 0
}

level3_reseed()
{
    log INFO "Nivel 3: re-seed completo a partir do master."

    if [[ "$MAX_LEVEL" -lt 3 ]]; then
        log FAIL "Divergencia exige re-seed, mas --max-level ${MAX_LEVEL} impede a escalada."
        log FAIL "Rode sem --max-level, ou re-semeie manualmente."
        return 2
    fi

    if [[ ! -r "$REPL_CNF" ]]; then
        log FAIL "Parametros de replicacao ausentes: ${REPL_CNF}"
        log FAIL "Sem eles nao e possivel reconectar apos o re-seed."
        return 2
    fi

    local source_host source_port repl_user repl_pass
    source_host=$(read_conf_value REPL_SOURCE_HOST "$REPL_CNF")
    source_port=$(read_conf_value REPL_SOURCE_PORT "$REPL_CNF")
    repl_user=$(read_conf_value REPL_USER "$REPL_CNF")
    repl_pass=$(read_conf_value REPL_PASSWORD "$REPL_CNF")
    source_port="${source_port:-3306}"

    local db_name db_user db_pass
    db_name=$(read_conf_value dbname "$FLUX_CONF")
    db_user=$(read_conf_value dbuser "$FLUX_CONF")
    db_pass=$(read_conf_value dbpass "$FLUX_CONF")

    if [[ -z "$source_host" || -z "$repl_pass" || -z "$db_name" ]]; then
        log FAIL "Parametros incompletos para o re-seed."
        return 2
    fi

    if ! within_reseed_window; then
        log FAIL "Fora da janela de re-seed (${RESEED_WINDOW}). Nada foi alterado."
        log FAIL "Use --force para executar agora."
        return 2
    fi

    if ! cooldown_ok; then
        return 2
    fi

    if ! disk_space_ok; then
        return 2
    fi

    if [[ $DRY_RUN -eq 0 && $RESEED_JITTER_MAX -gt 0 ]]; then
        local jitter=$(( RANDOM % RESEED_JITTER_MAX ))
        log INFO "Aguardando ${jitter}s (jitter, evita dump simultaneo de varias replicas)..."
        sleep "$jitter"
    fi

    local seed
    seed=$(mktemp "/var/tmp/flux_reseed_XXXXXX")

    log INFO "Gerando dump do master ${source_host}:${source_port}..."

    if [[ $DRY_RUN -eq 1 ]]; then
        log DRY "mysqldump -h ${source_host} --single-transaction --set-gtid-purged=ON --triggers --routines --events --databases ${db_name} | gzip > ${seed}"
        log DRY "$(stop_replica_stmt); RESET REPLICA ALL; RESET MASTER; restore; CHANGE REPLICATION SOURCE; $(start_replica_stmt)"
        rm -f "$seed"
        LEVEL_APPLIED=3
        return 0
    fi

    if ! mysqldump -h "$source_host" -P "$source_port" --protocol=TCP \
            -u"$db_user" -p"$db_pass" \
            --single-transaction --set-gtid-purged=ON \
            --triggers --routines --events \
            --databases "$db_name" 2>/dev/null | gzip > "$seed"; then
        log FAIL "Falha ao gerar o dump do master. Nada foi alterado nesta replica."
        rm -f "$seed"
        return 1
    fi

    local seed_size
    seed_size=$(du -h "$seed" | cut -f1)
    if [[ ! -s "$seed" ]]; then
        log FAIL "Dump vazio; abortando sem tocar na base local."
        rm -f "$seed"
        return 1
    fi
    log OK "Dump obtido (${seed_size})."

    local stop_stmt reset_stmt
    stop_stmt=$(stop_replica_stmt)
    if supports_source_syntax; then reset_stmt="RESET REPLICA ALL"; else reset_stmt="RESET SLAVE ALL"; fi

    log INFO "Recarregando a base local a partir do dump..."
    mysql --defaults-extra-file="$REPAIR_CNF" -e "
        ${stop_stmt};
        ${reset_stmt};
        SET GLOBAL super_read_only=OFF;
        SET GLOBAL read_only=OFF;
        RESET MASTER;" 2>/dev/null

    if ! gunzip -c "$seed" | mysql --defaults-extra-file="$REPAIR_CNF"; then
        log FAIL "Falha ao restaurar o dump. A replica ficou em estado inconsistente."
        log FAIL "Dump preservado em ${seed} para investigacao."
        mysql --defaults-extra-file="$REPAIR_CNF" -e "SET GLOBAL read_only=ON; SET GLOBAL super_read_only=ON;" 2>/dev/null
        return 1
    fi

    rm -f "$seed"

    local ev
    for ev in staging_cdrs remove_cdrs_records; do
        mysql --defaults-extra-file="$REPAIR_CNF" -e "ALTER EVENT \`${db_name}\`.\`${ev}\` DISABLE;" 2>/dev/null || true
    done

    mysql --defaults-extra-file="$REPAIR_CNF" -e "SET GLOBAL read_only=ON; SET GLOBAL super_read_only=ON;" 2>/dev/null

    local change_stmt
    if supports_source_syntax; then
        change_stmt="CHANGE REPLICATION SOURCE TO
            SOURCE_HOST='${source_host}', SOURCE_PORT=${source_port},
            SOURCE_USER='${repl_user}', SOURCE_PASSWORD='${repl_pass}',
            SOURCE_AUTO_POSITION=1, GET_SOURCE_PUBLIC_KEY=1"
    else
        change_stmt="CHANGE MASTER TO
            MASTER_HOST='${source_host}', MASTER_PORT=${source_port},
            MASTER_USER='${repl_user}', MASTER_PASSWORD='${repl_pass}',
            MASTER_AUTO_POSITION=1, GET_MASTER_PUBLIC_KEY=1"
    fi

    mysql --defaults-extra-file="$REPAIR_CNF" -e "${change_stmt}; $(start_replica_stmt);" 2>/dev/null
    sleep 5

    date +%s > "$RESEED_STAMP"
    chmod 600 "$RESEED_STAMP" 2>/dev/null || true

    if replication_healthy; then
        LEVEL_APPLIED=3
        log OK "Nivel 3 resolveu: base recarregada e replicacao reconectada."
        return 0
    fi

    log FAIL "Re-seed concluido, mas a replicacao nao iniciou."
    log FAIL "Last_IO_Error: $(replica_field 'Last_IO_Error')"
    log FAIL "Last_SQL_Error: $(replica_field 'Last_SQL_Error')"
    return 1
}

repair()
{
    log INFO "=== Verificacao de replicacao ==="

    if [[ ! -x "$CHECK_SCRIPT" ]]; then
        log FAIL "Script de verificacao nao encontrado: ${CHECK_SCRIPT}"
        return 2
    fi

    "$CHECK_SCRIPT" --quiet
    local check_code=$?

    case $check_code in
        0)
            log OK "Replicacao saudavel; nada a fazer."
            return 0
            ;;
        2)
            log FAIL "Erro de configuracao detectado pelo check; o reparo nao se aplica."
            log FAIL "Rode: flux_ha_setup.sh --verify-config"
            return 2
            ;;
    esac

    if ! require_replica_role; then
        return 2
    fi

    if [[ $check_code -eq 3 ]]; then
        log WARN "Divergencia confirmada: escalando direto ao nivel 3."
        level3_reseed
        local rc=$?
        [[ $rc -ne 0 ]] && return $rc
    else
        log WARN "Replicacao degradada; iniciando escalada."

        local sql_error
        sql_error=$(replica_field "Last_SQL_Error")

        if [[ -z "$sql_error" ]] && level1_start; then
            :
        elif [[ "$MAX_LEVEL" -ge 2 ]] && level2_restart; then
            :
        elif [[ "$MAX_LEVEL" -ge 3 ]]; then
            log WARN "Niveis leves nao resolveram; escalando ao re-seed."
            level3_reseed
            local rc=$?
            [[ $rc -ne 0 ]] && return $rc
        else
            log FAIL "Nao resolvido dentro de --max-level ${MAX_LEVEL}."
            return 1
        fi
    fi

    if [[ $DRY_RUN -eq 1 ]]; then
        log DRY "Verificacao final ignorada em dry-run."
        return 0
    fi

    log INFO "=== Verificacao final ==="
    "$CHECK_SCRIPT" --quiet
    local final=$?

    if [[ $final -eq 0 ]]; then
        log OK "Replicacao restabelecida (nivel ${LEVEL_APPLIED})."
        return 0
    fi

    log FAIL "A replicacao continua degradada apos o reparo (codigo ${final})."
    return 1
}

mkdir -p "$FLUXLOGDIR" 2>/dev/null || true

if [[ "$ACTION" == "status" ]]; then
    show_status
    exit 0
fi

if [[ $DRY_RUN -eq 0 ]]; then
    exec 9>"$LOCK_FILE" 2>/dev/null || true
    if ! flock -n 9 2>/dev/null; then
        log WARN "Outro reparo ja esta em execucao neste no; saindo."
        exit 2
    fi
fi

repair
exit $?
