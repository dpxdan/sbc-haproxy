#!/bin/bash

# =========================
# Defaults
# =========================
DB_HOST="127.0.0.1"
DB_PORT="3306"

DB_USER_DEFAULT="root"
DB_PASS_DEFAULT=""
DB_NAME_DEFAULT="flux"
FS_CLI_PASS_DEFAULT="ClueCon"

DEFAULT_INTERVAL="1 HOUR"
MIN_INTERVAL_MINUTES=30

DRY_RUN=false
FORCE=false

# =========================
# Parse arguments
# =========================
for arg in "$@"; do
    case "$arg" in
        --dry-run)
            DRY_RUN=true
            ;;
        --force)
            FORCE=true
            ;;
        *)
            echo "Unknown option: $arg"
            exit 1
            ;;
    esac
done

# =========================
# Read credentials
# =========================
read -p "MySQL user [${DB_USER_DEFAULT}]: " DB_USER
DB_USER="${DB_USER:-$DB_USER_DEFAULT}"

read -s -p "MySQL password: " DB_PASS
echo
DB_PASS="${DB_PASS:-$DB_PASS_DEFAULT}"

read -p "Database name [${DB_NAME_DEFAULT}]: " DB_NAME
DB_NAME="${DB_NAME:-$DB_NAME_DEFAULT}"

read -s -p "FreeSWITCH fs_cli password: " FS_CLI_PASS
echo
FS_CLI_PASS="${FS_CLI_PASS:-$FS_CLI_PASS_DEFAULT}"

# =========================
# Interval configuration
# =========================
read -p "Cleanup interval [${DEFAULT_INTERVAL}]: " USER_INTERVAL
INTERVAL_RAW="${USER_INTERVAL:-$DEFAULT_INTERVAL}"

INTERVAL_VALUE=$(echo "$INTERVAL_RAW" | awk '{print $1}')
INTERVAL_UNIT=$(echo "$INTERVAL_RAW" | awk '{print toupper($2)}')

if [[ -z "$INTERVAL_VALUE" || -z "$INTERVAL_UNIT" || ! "$INTERVAL_VALUE" =~ ^[0-9]+$ ]]; then
    echo "Invalid interval format. Use examples: '30 MINUTE', '1 HOUR'"
    exit 1
fi

# Convert interval to minutes
case "$INTERVAL_UNIT" in
    MINUTE|MINUTES)
        INTERVAL_MINUTES="$INTERVAL_VALUE"
        ;;
    HOUR|HOURS)
        INTERVAL_MINUTES=$((INTERVAL_VALUE * 60))
        ;;
    DAY|DAYS)
        INTERVAL_MINUTES=$((INTERVAL_VALUE * 1440))
        ;;
    *)
        echo "Invalid interval unit. Allowed units: MINUTE, HOUR, DAY"
        exit 1
        ;;
esac

# Enforce minimum interval unless --force is used
if (( INTERVAL_MINUTES < MIN_INTERVAL_MINUTES )); then
    if [[ "$FORCE" != "true" ]]; then
        echo "Interval ${INTERVAL_RAW} is below the minimum (${MIN_INTERVAL_MINUTES} MINUTE). Use --force to override."
        exit 1
    fi
fi

INTERVAL="$INTERVAL_VALUE $INTERVAL_UNIT"

# =========================
# Log file
# =========================
MODE="LIVE"
$DRY_RUN && MODE="DRY-RUN"

OUTPUT_FILE="/var/log/freeswitch/orphan_cleanup_${MODE}_$(date +%F_%H-%M-%S).log"

# =========================
# Helper functions
# =========================
log() {
    local LEVEL="$1"
    shift
    echo "[$(date '+%F %T')] [$LEVEL] $*" | tee -a "$OUTPUT_FILE"
}

fatal() {
    log "FATAL" "$*"
    exit 1
}

# =========================
# Dependency checks
# =========================
command -v mysql >/dev/null 2>&1 || fatal "mysql client not found"
command -v fs_cli >/dev/null 2>&1 || fatal "fs_cli not found"

# =========================
# MySQL connectivity test
# =========================
mysql -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" >/dev/null 2>&1 \
    || fatal "Unable to connect to MySQL database"

# =========================
# FS_CLI connectivity test
# =========================
FS_TEST_OUTPUT=$(fs_cli -p"$FS_CLI_PASS" -x "show status" 2>&1)
if [[ $? -ne 0 || -z "$FS_TEST_OUTPUT" ]]; then
    fatal "Unable to connect to FS CLI: $FS_TEST_OUTPUT"
fi

if ! echo "$FS_TEST_OUTPUT" | grep -qi "UP"; then
    fatal "FreeSWITCH connected but not in UP state"
fi

# =========================
# Queries
# =========================
CHANNELS_QUERY="
SELECT uuid, created
FROM channels
WHERE created < NOW() - INTERVAL $INTERVAL
ORDER BY created ASC;
"

CALLS_QUERY="
SELECT c.call_uuid, c.call_created_epoch
FROM calls c
LEFT JOIN channels ch ON ch.call_uuid = c.call_uuid
WHERE ch.call_uuid IS NULL
  AND c.call_created_epoch < UNIX_TIMESTAMP() - 3600
ORDER BY c.call_created_epoch ASC;
"

# =========================
# CHANNELS cleanup
# =========================
log "INFO" "Starting orphan channel cleanup (mode=$MODE, interval=$INTERVAL, force=$FORCE)"

mysql -N -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$CHANNELS_QUERY" |
while read -r UUID CREATED
do
    [[ -z "$UUID" ]] && continue

    EXISTS=$(fs_cli -p"$FS_CLI_PASS" -x "uuid_exists $UUID" 2>/dev/null | tr -d '\r')

    if [[ "$EXISTS" == "false" ]]; then
        log "INFO" "CHANNEL-ORPHAN uuid=$UUID created=$CREATED"

        if ! $DRY_RUN; then
            mysql -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
                -e "DELETE FROM channels WHERE uuid = '$UUID';" \
                || log "ERROR" "Failed to delete channel uuid=$UUID"
        else
            log "INFO" "DRY-RUN: channel uuid=$UUID would be deleted"
        fi
    fi
done

# =========================
# CALLS cleanup
# =========================
log "INFO" "Starting orphan calls cleanup"

mysql -N -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$CALLS_QUERY" |
while read -r CALL_UUID CREATED_EPOCH
do
    [[ -z "$CALL_UUID" ]] && continue

    log "INFO" "CALL-ORPHAN call_uuid=$CALL_UUID epoch=$CREATED_EPOCH"

    if ! $DRY_RUN; then
        mysql -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
            -e "DELETE FROM calls WHERE call_uuid = '$CALL_UUID';" \
            || log "ERROR" "Failed to delete call_uuid=$CALL_UUID"
    else
        log "INFO" "DRY-RUN: call_uuid=$CALL_UUID would be deleted"
    fi
done

log "INFO" "Cleanup finished (mode=$MODE)"