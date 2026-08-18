#!/usr/bin/env bash
# ====================================================================
# FluxSBC Resource Tuner v3
# Copyright (C) 2025 Flux Tecnologia - flux.net.br
# Author: Daniel Paixao <daniel@flux.net.br>
# - v3 features + analysis of php-fpm, mysql, nginx, freeswitch configs
# - proposes improvements and can generate tuned files under:
#   /opt/flux/misc/tunning/tuned-configs/<service>/
# - supports: --dry-run, --apply, --package, --analyze, --apply-tuned, --rollback
# ====================================================================
set -euo pipefail

# -------------------- Configuration / Defaults ------------------------
BACKUP_PARENT="/opt/flux/misc/tunning/resource-backups"
TUNED_BASE="/opt/flux/misc/tunning/tuned-configs"
SERVICES=("freeswitch" "mysql" "php7.3-fpm" "nginx")
TIMESTAMP=$(date +%Y%m%d-%H%M%S)

DRY_RUN=true
APPLY=false
PACKAGE=false
ROLLBACK=false
ANALYZE=false
APPLY_TUNED=false
MANUAL=false
AUTO_PRIORITY=""
PER_PHP_CHILD_MB=50  # estimate for php-fpm child memory in MB (used to compute pm.max_children)

declare -A PRIORITY
declare -A WEIGHT_BY_PRIORITY=([1]=40 [2]=30 [3]=20 [4]=10)
declare -A WEIGHT
declare -A CPUQUOTA
declare -A MEM_MB
declare -A ALLOWED_RANGE

PACKAGE_DIR="/tmp/fluxsbc_package_${TIMESTAMP}"
PACKAGE_FILE="/tmp/fluxsbc_package_${TIMESTAMP}.tar.gz"
BACKUP_DIR="${BACKUP_PARENT}/${TIMESTAMP}"

PHP_POOL_PATH="/etc/php/7.3/fpm/pool.d/www.conf"
MYSQL_CANDIDATES=("/etc/mysql/mysql.conf.d/mysqld.cnf" "/etc/mysql/my.cnf" "/etc/my.cnf")
NGINX_CONF="/etc/nginx/nginx.conf"
FREESWITCH_CONF="/etc/freeswitch/autoload_configs/switch.conf.xml"

# -------------------- Helpers ----------------------------------------
info() { echo "[INFO] $*"; }
warn() { echo "[WARN] $*" >&2; }
err() { echo "[ERROR] $*" >&2; exit 1; }
require_root() { [[ $EUID -eq 0 ]] || err "Execute como root (sudo)"; }

human_to_mb() {
  local v="$1"
  if [[ "$v" =~ ^([0-9]+)([KkMmGgTt])$ ]]; then
    local n=${BASH_REMATCH[1]}; local u=${BASH_REMATCH[2]}
    case "$u" in
      K|k) echo $(( n/1024 )) ;;
      M|m) echo "$n" ;;
      G|g) echo $(( n*1024 )) ;;
      T|t) echo $(( n*1024*1024 )) ;;
    esac
  else
    echo "$v"
  fi
}

timestamp() { date +%Y%m%d-%H%M%S; }

usage() {
  cat <<EOF
FluxSBC Resource Tuner v3

Opções:
  --dry-run            (padrão) apenas simula
  --apply              aplica configurações (override + tuned files)
  --package            gera pacote .tar.gz com arquivos gerados
  --analyze            analisa configs atuais e propõe melhorias
  --apply-tuned        aplica os arquivos .tuned gerados (substitui configs)
  --manual             inserir recursos manualmente
  --auto-priority STR  auto priority "freeswitch:1 mysql:2 php7.3-fpm:3 nginx:4"
  --rollback           restaura backup mais recente criado por o script
  -h, --help           mostra essa ajuda

Exemplos:
  sudo $0 --dry-run --analyze
  sudo $0 --apply --auto-priority "freeswitch:1 mysql:2 php7.3-fpm:3 nginx:4" --package
  sudo $0 --analyze --apply-tuned
EOF
  exit 1
}

# -------------------- Arg parsing -------------------------------------
if [[ $# -eq 0 ]]; then usage; fi
while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRY_RUN=true; shift;;
    --apply) DRY_RUN=false; APPLY=true; shift;;
    --package) PACKAGE=true; shift;;
    --analyze) ANALYZE=true; shift;;
    --apply-tuned) APPLY_TUNED=true; shift;;
    --manual) MANUAL=true; shift;;
    --auto-priority) AUTO_PRIORITY="$2"; shift 2;;
    --rollback) ROLLBACK=true; shift;;
    -h|--help) usage;;
    *) echo "Opção inválida: $1"; usage;;
  esac
done

require_root

# -------------------- Rollback ----------------------------------------
if $ROLLBACK; then
  latest=$(find "${BACKUP_PARENT}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p
' 2>/dev/null | sort -nr | head -n1 | cut -d' ' -f2-)
  [[ -z "$latest" ]] && err "Nenhum backup encontrado em ${BACKUP_PARENT}"
  read -rp "Restaurar backup ${latest}? (y/N): " ok
  [[ ! "$ok" =~ ^[Yy]$ ]] && info "Abortando rollback" && exit 0
  cp -a "${latest}/etc/." /etc/ || err "Falha ao copiar backup"
  systemctl daemon-reload || warn "systemctl daemon-reload falhou"
  info "Rollback concluído"
  exit 0
fi

# -------------------- Resource detection or manual ---------------------
if $MANUAL; then
  read -rp "Número de núcleos (ex: 4): " TOTAL_CORES
  read -rp "Memória total (GB, ex: 8): " TOTAL_MEM_GB
  read -rp "Espaço disco root (ex: 100G): " TOTAL_DISK
  TOTAL_MEM_MB=$(( TOTAL_MEM_GB * 1024 ))
else
  TOTAL_CORES=$(nproc --all)
  TOTAL_MEM_KB=$(awk '/MemTotal/ {print $2}' /proc/meminfo)
  TOTAL_MEM_MB=$(( TOTAL_MEM_KB / 1024 ))
  TOTAL_DISK=$(df -h --output=avail -BG / | tail -1 | tr -d ' ')
fi

RESERVED_CORES=1
RESERVED_MEM_MB=1024
USABLE_CORES=$(( TOTAL_CORES - RESERVED_CORES ))
(( USABLE_CORES < 1 )) && USABLE_CORES=1
USABLE_MEM_MB=$(( TOTAL_MEM_MB - RESERVED_MEM_MB ))
(( USABLE_MEM_MB < 256 )) && USABLE_MEM_MB=$TOTAL_MEM_MB

info "Hardware: cores=${TOTAL_CORES}, total_mem=${TOTAL_MEM_MB}MB, usable_cores=${USABLE_CORES}, usable_mem=${USABLE_MEM_MB}MB, disk=${TOTAL_DISK}"

# -------------------- Priority handling ----------------------------------
if [[ -n "$AUTO_PRIORITY" ]]; then
  for pair in $AUTO_PRIORITY; do
    if [[ "$pair" =~ ^([^:]+):([1-4])$ ]]; then
      svc="${BASH_REMATCH[1]}"; p="${BASH_REMATCH[2]}"
      PRIORITY[$svc]="$p"
    else
      err "Formato inválido para --auto-priority: $pair"
    fi
  done
  # ensure all services present
  for s in "${SERVICES[@]}"; do [[ -z "${PRIORITY[$s]:-}" ]] && err "auto-priority não informou $s"; done
else
  echo "Defina prioridades (1=mais importante, 4=menos importante). Não repita números."
  for s in "${SERVICES[@]}"; do
    while true; do
      read -rp "Prioridade para ${s} [1-4]: " p
      [[ ! "$p" =~ ^[1-4]$ ]] && echo "Escolha 1..4" && continue
      dup=false
      for v in "${PRIORITY[@]:-}"; do [[ "$v" == "$p" ]] && dup=true && break; done
      $dup && echo "Prioridade já usada" && continue
      PRIORITY[$s]="$p"; break
    done
  done
fi

# map weights
for s in "${SERVICES[@]}"; do WEIGHT[$s]=${WEIGHT_BY_PRIORITY[${PRIORITY[$s]}]}; done

# normalize to 100
sum=0
for s in "${SERVICES[@]}"; do sum=$((sum + WEIGHT[$s])); done
if (( sum != 100 )); then
  # normalize proportionally
  tmp_sum=0
  declare -A NEWW
  for s in "${SERVICES[@]}"; do v=${WEIGHT[$s]}; nv=$(( v*100 / sum )); (( nv<1 )) && nv=1; NEWW[$s]=$nv; tmp_sum=$(( tmp_sum + nv )); done
  rem=$((100 - tmp_sum)); i=0
  while (( rem>0 )); do s=${SERVICES[$((i % ${#SERVICES[@]}))]}; NEWW[$s]=$((NEWW[$s]+1)); rem=$((rem-1)); i=$((i+1)); done
  for s in "${SERVICES[@]}"; do WEIGHT[$s]=${NEWW[$s]}; done
fi

# compute allocations
start_core=$RESERVED_CORES
for s in "${SERVICES[@]}"; do
  pct=${WEIGHT[$s]}
  cores=$(( (USABLE_CORES * pct + 50) / 100 ))
  (( cores<1 )) && cores=1
  e=$(( start_core + cores -1 ))
  ALLOWED_RANGE[$s]="${start_core}-${e}"
  start_core=$(( e + 1 ))
  MEM_MB[$s]=$(( (USABLE_MEM_MB * pct)/100 ))
  CPUQUOTA[$s]=$(( (TOTAL_CORES * pct * 100) / 100 ))  # simplified => pct% of total CPUs -> pct% of a single cpu? keep as pct*TOTAL_CORES?
done

echo "Plano:"
for s in "${SERVICES[@]}"; do
  echo "  $s -> peso ${WEIGHT[$s]}% | allowed=${ALLOWED_RANGE[$s]} | MemoryMax=${MEM_MB[$s]}MB | CPUQuota=${CPUQUOTA[$s]}%"
done

# -------------------- Functions: analyze configs and propose -------------

mkdir -p "$BACKUP_DIR"
mkdir -p "$TUNED_BASE"

# php-fpm analysis
analyze_phpfpm() {
  echo "Analyzing PHP-FPM pool: $PHP_POOL_PATH"
  if [[ ! -f "$PHP_POOL_PATH" ]]; then warn "php-fpm pool not found: $PHP_POOL_PATH"; return; fi
  # read current values (if exist)
  current_pm=$(grep -E '^\s*pm\s*=' "$PHP_POOL_PATH" 2>/dev/null | awk -F= '{gsub(/ /,"",$2); print $2}' || true)
  current_max=$(grep -E '^\s*pm.max_children' "$PHP_POOL_PATH" 2>/dev/null | awk -F= '{gsub(/ /,"",$2); print $2}' || true)
  current_start=$(grep -E '^\s*pm.start_servers' "$PHP_POOL_PATH" 2>/dev/null | awk -F= '{gsub(/ /,"",$2); print $2}' || true)
  current_min=$(grep -E '^\s*pm.min_spare_servers' "$PHP_POOL_PATH" 2>/dev/null | awk -F= '{gsub(/ /,"",$2); print $2}' || true)
  current_max_spare=$(grep -E '^\s*pm.max_spare_servers' "$PHP_POOL_PATH" 2>/dev/null | awk -F= '{gsub(/ /,"",$2); print $2}' || true)

  mem_php=${MEM_MB["php7.3-fpm"]:-$((USABLE_MEM_MB/4))}
  est_max_children=$(( mem_php / PER_PHP_CHILD_MB ))
  (( est_max_children<2 )) && est_max_children=2
  est_start=$(( est_max_children / 4 )); (( est_start<1 )) && est_start=1
  est_min=$(( est_max_children / 6 )); (( est_min<1 )) && est_min=1
  est_max_spare=$(( est_max_children / 3 )); (( est_max_spare<2 )) && est_max_spare=2

  echo " Current: pm=${current_pm:-unknown}, max_children=${current_max:-unknown}, start=${current_start:-unknown}, min_spare=${current_min:-unknown}, max_spare=${current_max_spare:-unknown}"
  echo " Proposed: pm=dynamic, pm.max_children=${est_max_children}, pm.start_servers=${est_start}, pm.min_spare_servers=${est_min}, pm.max_spare_servers=${est_max_spare}"

  tuned_dir="${TUNED_BASE}/php7.3-fpm"
  mkdir -p "$tuned_dir"
  tuned_file="${tuned_dir}/www.conf.tuned"
  cat > "$tuned_file" <<EOF
; Tuned by fluxsbc_resource_tuner_v3
pm = dynamic
pm.max_children = ${est_max_children}
pm.start_servers = ${est_start}
pm.min_spare_servers = ${est_min}
pm.max_spare_servers = ${est_max_spare}
EOF
  info "Generated tuned php-fpm: $tuned_file"
}

# mysql analysis
analyze_mysql() {
  local target=""
  for c in "${MYSQL_CANDIDATES[@]}"; do [[ -f "$c" ]] && { target="$c"; break; } done
  if [[ -z "$target" ]]; then warn "No MySQL config found"; return; fi
  echo "Analyzing MySQL config: $target"
  # read current innodb buffer
  cur_innodb=$(grep -E '^\s*innodb_buffer_pool_size' "$target" 2>/dev/null | awk -F= '{gsub(/ /,"",$2); print $2}' || true)
  cur_maxconn=$(grep -E '^\s*max_connections' "$target" 2>/dev/null | awk -F= '{gsub(/ /,"",$2); print $2}' || true)
  # propose innodb = min(70% of usable mem, TOTAL_MEM_MB-1GB) if mysql priority high
  weight=${WEIGHT["mysql"]}
  propose_innodb_mb=$(( (USABLE_MEM_MB * weight) / 100 ))
  # cap to 70% of TOTAL_MEM_MB if weight=100
  cap=$(( (TOTAL_MEM_MB * 70) / 100 ))
  (( propose_innodb_mb > cap )) && propose_innodb_mb=$cap
  (( propose_innodb_mb < 128 )) && propose_innodb_mb=128
  propose_max_conn=200
  echo " Current: innodb_buffer_pool_size=${cur_innodb:-unknown}, max_connections=${cur_maxconn:-unknown}"
  echo " Proposed: innodb_buffer_pool_size=${propose_innodb_mb}M, max_connections=${propose_max_conn}"
  tuned_dir="${TUNED_BASE}/mysql"
  mkdir -p "$tuned_dir"
  tuned_file="${tuned_dir}/my.cnf.tuned"
  cat > "$tuned_file" <<EOF
# Tuned by fluxsbc_resource_tuner_v3
[mysqld]
innodb_buffer_pool_size = ${propose_innodb_mb}M
max_connections = ${propose_max_conn}
key_buffer_size = 32M
EOF
  info "Generated tuned mysql: $tuned_file"
}

# nginx analysis
analyze_nginx() {
  if [[ ! -f "$NGINX_CONF" ]]; then warn "nginx.conf not found: $NGINX_CONF"; return; fi
  echo "Analyzing Nginx config: $NGINX_CONF"
  cur_workers=$(grep -E '^\s*worker_processes' "$NGINX_CONF" 2>/dev/null | awk '{print $2}' | tr -d ';' || true)
  cur_conn=$(grep -E 'worker_connections' "$NGINX_CONF" 2>/dev/null | awk '{print $2}' | tr -d ';' || true)
  prop_workers=$USABLE_CORES
  prop_conn=1024
  (( USABLE_MEM_MB >= 4096 )) && prop_conn=4096
  echo " Current: worker_processes=${cur_workers:-unknown}, worker_connections=${cur_conn:-unknown}"
  echo " Proposed: worker_processes=${prop_workers}, worker_connections=${prop_conn}"
  tuned_dir="${TUNED_BASE}/nginx"
  mkdir -p "$tuned_dir"
  tuned_file="${tuned_dir}/nginx.conf.tuned"
  # produce a minimal tuned snippet to insert/replace
  cat > "$tuned_file" <<EOF
# Tuned by fluxsbc_resource_tuner_v3
worker_processes ${prop_workers};
events {
    worker_connections ${prop_conn};
}
EOF
  info "Generated tuned nginx: $tuned_file"
}

# freeswitch analysis (basic)
analyze_freeswitch() {
  if [[ ! -f "$FREESWITCH_CONF" ]]; then warn "FreeSWITCH conf not found: $FREESWITCH_CONF"; return; fi
  echo "Analyzing FreeSWITCH config: $FREESWITCH_CONF"
  # Try to extract <param name="log-level" value="..."/> or other params; make safe conservative proposals
  tuned_dir="${TUNED_BASE}/freeswitch"
  mkdir -p "$tuned_dir"
  tuned_file="${tuned_dir}/switch.conf.xml.tuned"
  # We'll propose nothing invasive, suggest thread limits and session tuning comments
  cat > "$tuned_file" <<'EOF'
<!-- Tuned by fluxsbc_resource_tuner_v3 -->
<!-- Review and merge manually -->
<configuration name="switch.conf" description="tuned">
  <!-- Suggested tuning placeholders:
       - tune thread and session parameters according to cores and expected concurrent calls
       - consider modules load and mod_sofia settings
  -->
</configuration>
EOF
  info "Generated tuned freeswitch: $tuned_file (placeholder - revise manually)"
}

# -------------------- Generate systemd override files ---------------------
generate_override() {
  local svc="$1"
  local cpuq="$2"
  local memm="$3"
  local allowed="$4"
  local base="${PACKAGE_DIR:-/tmp}"
  local dir="${base}/etc/systemd/system/${svc}.service.d"
  mkdir -p "$dir"
  cat > "${dir}/override.conf" <<EOF
[Service]
# Generated by fluxsbc_resource_tuner_v3
CPUQuota=${cpuq}%
MemoryMax=${memm}M
AllowedCPUs=${allowed}
ExecStartPost=/usr/bin/taskset -cp ${allowed} \$MAINPID
EOF
  info "Prepared override for $svc -> ${dir}/override.conf"
}

# -------------------- Apply or package flow ------------------------------
# Create backup of touched files
create_backup() {
  mkdir -p "$BACKUP_DIR/etc"
  # backup systemd override if exists, php pool, mysql conf, nginx
  for s in "${SERVICES[@]}"; do
    d="/etc/systemd/system/${s}.service.d"
    if [[ -d "$d" ]]; then
  cp -a "$d" "$BACKUP_DIR/etc/$(basename "$d")"
fi
  done
  [[ -f "$PHP_POOL_PATH" ]] && mkdir -p "$BACKUP_DIR/etc/php/7.3/fpm/pool.d" && cp -a "$PHP_POOL_PATH" "$BACKUP_DIR/etc/php/7.3/fpm/pool.d/"
  for c in "${MYSQL_CANDIDATES[@]}"; do
  if [[ -f "$c" ]]; then
    mkdir -p "$BACKUP_DIR$(dirname "$c")"
    cp -a "$c" "$BACKUP_DIR$(dirname "$c")/"
  fi
done
  [[ -f "$NGINX_CONF" ]] && mkdir -p "$BACKUP_DIR/etc/nginx" && cp -a "$NGINX_CONF" "$BACKUP_DIR/etc/nginx/"
  [[ -f "$FREESWITCH_CONF" ]] && mkdir -p "$BACKUP_DIR/etc/freeswitch/autoload_configs" && cp -a "$FREESWITCH_CONF" "$BACKUP_DIR/etc/freeswitch/autoload_configs/"
  info "Backup parcial criado em $BACKUP_DIR"
}

# package generator
generate_package() {
  rm -rf "$PACKAGE_DIR"
  mkdir -p "$PACKAGE_DIR"
  # copy generated overrides and tuned files to package dir
  for s in "${SERVICES[@]}"; do
    src="/etc/systemd/system/${s}.service.d"
    if [[ -d "$src" ]]; then
      mkdir -p "$PACKAGE_DIR/etc/systemd/system/${s}.service.d"
      cp -a "$src/"* "$PACKAGE_DIR/etc/systemd/system/${s}.service.d/" || true
    fi
  done
  # tuned files
  if [[ -d "$TUNED_BASE" ]]; then
    mkdir -p "$PACKAGE_DIR/etc/fluxsbc"
    cp -a "$TUNED_BASE" "$PACKAGE_DIR/etc/fluxsbc" || true
  fi
  # php pool and other confs
  [[ -f "$PHP_POOL_PATH" ]] && mkdir -p "$PACKAGE_DIR$(dirname $PHP_POOL_PATH)" && cp -a "$PHP_POOL_PATH" "$PACKAGE_DIR$(dirname $PHP_POOL_PATH)/"
  for c in "${MYSQL_CANDIDATES[@]}"; do
  if [[ -f "$c" ]]; then
    mkdir -p "$PACKAGE_DIR$(dirname "$c")"
    cp -a "$c" "$PACKAGE_DIR$(dirname "$c")/"
  fi
done
  [[ -f "$NGINX_CONF" ]] && mkdir -p "$PACKAGE_DIR$(dirname $NGINX_CONF)" && cp -a "$NGINX_CONF" "$PACKAGE_DIR$(dirname $NGINX_CONF)/"
  [[ -f "$FREESWITCH_CONF" ]] && mkdir -p "$PACKAGE_DIR$(dirname $FREESWITCH_CONF)" && cp -a "$FREESWITCH_CONF" "$PACKAGE_DIR$(dirname $FREESWITCH_CONF)/"
  tar -czf "$PACKAGE_FILE" -C "$PACKAGE_DIR" .
  info "Package created: $PACKAGE_FILE"
}

# -------------------- Main execution ------------------------------------
# Analyze step: generate tuned proposals
if $ANALYZE; then
  info "Running analysis and generating tuned config proposals..."
  analyze_phpfpm
  analyze_mysql
  analyze_nginx
  analyze_freeswitch
  info "Tuned configs generated at: $TUNED_BASE"
  if $DRY_RUN; then
    info "Dry-run: tuned files created but not applied"
  fi
fi

# If user asked to apply tuned configs directly
if $APPLY_TUNED; then
  info "Applying tuned configs (will overwrite live configs) - backing up first..."
  create_backup
  # php-fpm
  if [[ -f "${TUNED_BASE}/php7.3-fpm/www.conf.tuned" ]]; then
    cp -a "${TUNED_BASE}/php7.3-fpm/www.conf.tuned" "$PHP_POOL_PATH"
    info "php-fpm pool replaced with tuned file"
  fi
  # mysql
  if [[ -f "${TUNED_BASE}/mysql/my.cnf.tuned" ]]; then
    tgt="${MYSQL_CANDIDATES[0]}"
    cp -a "${TUNED_BASE}/mysql/my.cnf.tuned" "$tgt"
    info "MySQL main conf replaced at $tgt"
  fi
  # nginx
  if [[ -f "${TUNED_BASE}/nginx/nginx.conf.tuned" ]]; then
    cp -a "${TUNED_BASE}/nginx/nginx.conf.tuned" "$NGINX_CONF"
    info "nginx.conf replaced with tuned file"
  fi
  # freeswitch - be conservative: copy tuned as suggestion file
  if [[ -f "${TUNED_BASE}/freeswitch/switch.conf.xml.tuned" ]]; then
    cp -a "${TUNED_BASE}/freeswitch/switch.conf.xml.tuned" "/etc/freeswitch/autoload_configs/switch.conf.xml.tuned"
    info "freeswitch tuned file copied as suggestion"
  fi
  systemctl daemon-reload || warn "systemctl daemon-reload failed"
  info "Tuned configs applied. Verify services before restarting production traffic."
fi

# Generate systemd overrides (in /etc/systemd/system) - create but do not restart unless APPLY true
for s in "${SERVICES[@]}"; do
  cpuq=${CPUQUOTA[$s]:-100}
  memm=${MEM_MB[$s]:-$((USABLE_MEM_MB/4))}
  allowed=${ALLOWED_RANGE[$s]:-"0-0"}
  if $DRY_RUN; then
    info "[DRY-RUN] Would create override for $s: CPUQuota=${cpuq}%, MemoryMax=${memm}M, AllowedCPUs=${allowed}"
    # also prepare in package dir for inspection
    mkdir -p "$PACKAGE_DIR/etc/systemd/system/${s}.service.d"
    cat > "$PACKAGE_DIR/etc/systemd/system/${s}.service.d/override.conf" <<EOF
[Service]
# Generated (dry-run)
CPUQuota=${cpuq}%
MemoryMax=${memm}M
AllowedCPUs=${allowed}
ExecStartPost=/usr/bin/taskset -cp ${allowed} \$MAINPID
EOF
  else
    create_backup
    generate_override "$s" "$cpuq" "$memm" "$allowed"
  fi
done

# If apply requested, move prepared overrides from PACKAGE_DIR to /etc/systemd/system
if $APPLY && ! $DRY_RUN; then
  info "Applying overrides into /etc/systemd/system and reloading systemd..."
  for s in "${SERVICES[@]}"; do
    src_dir="$PACKAGE_DIR/etc/systemd/system/${s}.service.d"
    dest_dir="/etc/systemd/system/${s}.service.d"
    if [[ -d "$src_dir" ]]; then
      mkdir -p "$dest_dir"
      cp -a "$src_dir/"* "$dest_dir/"
      info "Installed overrides for $s"
    fi
  done
  systemctl daemon-reload || warn "systemctl daemon-reload failed"
  info "Overrides applied. Consider restarting services if needed."
fi

# Package generation if requested
if $PACKAGE; then
  generate_package
  echo "Package available at: $PACKAGE_FILE"
fi

info "Done."
