#!/usr/bin/env bash
# ##############################################################################
# Flux SBC - Unindo pessoas e negócios
#
# Copyright (C) 2026 Flux Telecom
# Daniel Paixao <daniel@flux.net.br>
# FluxSBC Version 4.2 and above
# License https://www.gnu.org/licenses/agpl-3.0.html
#
# Script de instalação do módulo Protetor SIP (event_guard)
# Testado em: Debian 11 (Bullseye)
#
# Uso:
#   chmod +x event_guard_install.sh
#   sudo ./event_guard_install.sh              # instalação real
#   sudo ./event_guard_install.sh --dry-run    # simula sem aplicar
# ##############################################################################

set -euo pipefail

# ── Flags ──────────────────────────────────────────────────────────────────────
DRY_RUN=0
BACKUP_ONLY=0
RUN_ALL=0

show_help() {
    cat <<EOF
Uso: $0 [--all] [--dry-run] [--backup-only] [--help]

Sem flags de ação, o script abre um menu interativo permitindo escolher quais
etapas executar individualmente.

Opções:
  --all            Executa a instalação completa sem menu (modo não-interativo,
                   adequado para cron/Ansible).
  --backup-only    Cria um snapshot dos arquivos-alvo da instalação que já
                   existam no sistema (em /var/backups/fluxsbc/event_guard/).
                   Não toca em apt, sudoers, MySQL nem systemd.
  --dry-run        Modificador. Simula execução; nenhuma alteração é aplicada.
                   Combina com --all, --backup-only ou modo menu.
                   Comandos aparecem prefixados com [DRY].
  -h, --help       Mostra esta mensagem.

Exemplos:
  sudo $0                          # menu interativo
  sudo $0 --all                    # instalação completa não-interativa
  sudo $0 --all --dry-run          # simula instalação completa
  sudo $0 --backup-only            # só snapshot
  sudo $0 --backup-only --dry-run  # lista o que seria preservado

Backup:
  Todo arquivo sobrescrito é copiado para:
    /var/backups/fluxsbc/event_guard/<timestamp>/<caminho-absoluto-original>

  Um MANIFEST.txt é gravado no diretório do snapshot com a lista de arquivos
  preservados e comandos prontos de restauração.

  Restauração completa do snapshot mais recente:
    SNAP=\$(ls -1dt /var/backups/fluxsbc/event_guard/*/ | head -1)
    cp -a "\${SNAP}." /
EOF
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --all)         RUN_ALL=1; shift ;;
            --dry-run)     DRY_RUN=1; shift ;;
            --backup-only) BACKUP_ONLY=1; shift ;;
            -h|--help)     show_help; exit 0 ;;
            *)
                echo "Argumento desconhecido: $1" >&2
                show_help
                exit 1
                ;;
        esac
    done

    # Validação: --all e --backup-only são mutuamente exclusivos
    if [[ $RUN_ALL -eq 1 && $BACKUP_ONLY -eq 1 ]]; then
        echo "Erro: --all e --backup-only não podem ser combinados." >&2
        exit 1
    fi
}

# ── Cores ─────────────────────────────────────────────────────────────────────
if [[ -t 1 ]]; then
    RED=$'\033[0;31m'
    GREEN=$'\033[0;32m'
    YELLOW=$'\033[1;33m'
    BLUE=$'\033[0;34m'
    NC=$'\033[0m'
else
    RED=""; GREEN=""; YELLOW=""; BLUE=""; NC=""
fi

# ── Caminhos ──────────────────────────────────────────────────────────────────
FLUXSBC_FS_DIR="/opt/flux/freeswitch/fs"
FLUXSBC_MODULE_DIR="/opt/flux/web_interface/flux/application/modules/event_guard"

# Raiz do repositório FluxSBC — mesmo parâmetro usado em flux_install.sh
FLUX_SOURCE_DIR="/opt/flux"

# Origens na raiz do repositório (não mais ao lado do script)
SRC_FS_DIR="${FLUX_SOURCE_DIR}/freeswitch/fs"
SRC_SQL_DIR="${FLUX_SOURCE_DIR}/database"
SRC_FAIL2BAN_DIR="${FLUX_SOURCE_DIR}/config/fail2ban"
SRC_SUDOERS_DIR="${FLUX_SOURCE_DIR}/config/sudoers.d"

# Estrutura esperada em ${FLUX_SOURCE_DIR} (raiz do repositório, ex: /opt/flux):
# module.xml
# static/images/event_guard_icon.png
# database/
#   event_guard_1.0.0.sql
#   uninstall.sql
# config/fail2ban/
#   event_guard.conf          → /etc/fail2ban/jail.d/event_guard.conf
#   sip-auth-fail.conf        → /etc/fail2ban/filter.d/sip-auth-fail.conf
#   sip-auth-ip.conf          → /etc/fail2ban/filter.d/sip-auth-ip.conf
#   event_guard.service       → /etc/systemd/system/event_guard.service
# config/sudoers.d/
#   fluxsbc-event-guard       → /etc/sudoers.d/fluxsbc-event-guard
# freeswitch/fs/
#   event_guard_daemon.php
#   lib/flux.eventsocket.php
# web_interface/flux/application/modules/event_guard/
#   controllers/event_guard.php
#   libraries/event_guard_form.php
#   models/event_guard_model.php
#   views/view_event_guard_*.php
#
# O script (event_guard_install.sh) pode estar em qualquer lugar,
# inclusive em /opt/flux/web_interface/flux/addons/plugins/event_guard —
# a busca dos arquivos de origem sempre parte de ${FLUX_SOURCE_DIR}.


# ── Config de banco (lida do flux-config.conf) ────────────────────────────────
# Localização padrão em produção; /opt/flux/config/ é fallback para instalações alternativas.
FLUX_CONF_CANDIDATES=(
    "/var/lib/flux/flux-config.conf"
    "/opt/flux/config/flux-config.conf"
)
FLUX_CONF=""
for candidate in "${FLUX_CONF_CANDIDATES[@]}"; do
    if [[ -f "$candidate" ]]; then
        FLUX_CONF="$candidate"
        break
    fi
done

# Globais usadas entre run_sql() e verify_installation()
declare -g DB_HOST="" DB_NAME="" DB_USER="" DB_PASS=""
declare -g MYSQL_CNF=""

# ── Backup ─────────────────────────────────────────────────────────────────────
# Backups centralizados: /var/backups/fluxsbc/event_guard/<timestamp>/...
# A estrutura interna espelha o caminho absoluto original, simplificando o rollback.
BACKUP_ROOT="/var/backups/fluxsbc/event_guard"
BACKUP_TS=""
BACKUP_DIR=""
declare -ga BACKED_UP_FILES=()

# ── Estado (modo interativo) ───────────────────────────────────────────────────
# Garantem idempotência em check_files e init_backup_dir quando chamados via menu
STATE_FILES_CHECKED=0
STATE_BACKUP_INIT=0
STATE_MANIFEST_WRITTEN=0

# ── Funções de log ─────────────────────────────────────────────────────────────
log_info()    { echo "${BLUE}[INFO]${NC}  $*"; }
log_ok()      { echo "${GREEN}[OK]${NC}    $*"; }
log_warn()    { echo "${YELLOW}[WARN]${NC}  $*"; }
log_error()   { echo "${RED}[ERROR]${NC} $*"; }
log_dry()     { echo "${YELLOW}[DRY]${NC}   $*"; }
log_section() {
    echo
    echo "${BLUE}══════════════════════════════════════════${NC}"
    echo "${BLUE} $*${NC}"
    echo "${BLUE}══════════════════════════════════════════${NC}"
}

# ── Helpers de execução ────────────────────────────────────────────────────────
# run cmd args...      → executa, ou apenas ecoa quando DRY_RUN=1
run() {
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "$*"
        return 0
    fi
    "$@"
}

# ── Helpers de backup ──────────────────────────────────────────────────────────
init_backup_dir() {
    # Idempotente: chamadas repetidas não recriam o diretório
    if [[ $STATE_BACKUP_INIT -eq 1 ]]; then
        return 0
    fi

    BACKUP_TS="$(date +%Y%m%d_%H%M%S)"
    BACKUP_DIR="${BACKUP_ROOT}/${BACKUP_TS}"

    log_section "Inicializando diretório de backup"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "install -d -m 0700 -o root -g root ${BACKUP_DIR}"
        log_ok "Backup (simulado): ${BACKUP_DIR}"
        STATE_BACKUP_INIT=1
        return 0
    fi

    install -d -m 0700 -o root -g root "$BACKUP_DIR"
    log_ok "Backup: ${BACKUP_DIR}"
    STATE_BACKUP_INIT=1
}

# backup_file <caminho-absoluto>
# Copia o arquivo para BACKUP_DIR preservando o caminho absoluto.
# Silencioso se o arquivo não existir (nada a preservar).
# Idempotente: se o mesmo arquivo já foi processado nesta sessão, retorna sem refazer.
backup_file() {
    local src="$1"
    [[ -f "$src" ]] || return 0

    # Evita reprocessar o mesmo arquivo (relevante quando uma opção do menu
    # é executada mais de uma vez na mesma sessão)
    local existing
    for existing in "${BACKED_UP_FILES[@]}"; do
        [[ "$existing" == "$src" ]] && return 0
    done

    local dst="${BACKUP_DIR}${src}"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "backup: ${src} → ${dst}"
        BACKED_UP_FILES+=("$src")
        return 0
    fi

    install -d -m 0700 "$(dirname "$dst")"
    cp -p "$src" "$dst"
    BACKED_UP_FILES+=("$src")
}

write_backup_manifest() {
    if [[ $STATE_MANIFEST_WRITTEN -eq 1 ]]; then
        return 0
    fi

    log_section "Manifesto de backup"

    if [[ ${#BACKED_UP_FILES[@]} -eq 0 ]]; then
        log_info "Nenhum arquivo pré-existente foi sobrescrito (instalação limpa)."
        # Remove diretório vazio para não poluir /var/backups
        if [[ $DRY_RUN -eq 0 && -n "$BACKUP_DIR" && -d "$BACKUP_DIR" ]]; then
            rmdir "$BACKUP_DIR" 2>/dev/null || true
        fi
        STATE_MANIFEST_WRITTEN=1
        return 0
    fi

    local manifest="${BACKUP_DIR}/MANIFEST.txt"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Manifesto: ${manifest}"
        log_dry "Arquivos com backup (${#BACKED_UP_FILES[@]}):"
        for f in "${BACKED_UP_FILES[@]}"; do
            log_dry "  ${f}"
        done
        return 0
    fi

    {
        echo "FluxSBC event_guard - Backup pré-instalação"
        echo "Data:       $(date -Iseconds)"
        echo "Host:       $(hostname -f 2>/dev/null || hostname)"
        echo "Script:          ${BASH_SOURCE[0]}"
        echo "FLUX_SOURCE_DIR: ${FLUX_SOURCE_DIR}"
        echo "Total:      ${#BACKED_UP_FILES[@]} arquivo(s)"
        echo
        echo "── Arquivos preservados ──"
        for f in "${BACKED_UP_FILES[@]}"; do
            echo "  ${f}"
        done
        echo
        echo "── Restauração completa ──"
        echo "  cp -a ${BACKUP_DIR}/. /"
        echo
        echo "── Restauração seletiva ──"
        echo "  cp -p ${BACKUP_DIR}<caminho-absoluto> <caminho-absoluto>"
        echo
        echo "── Listar conteúdo ──"
        echo "  find ${BACKUP_DIR} -type f ! -name MANIFEST.txt"
    } > "$manifest"

    chmod 0600 "$manifest"
    log_ok "Manifesto:       ${manifest}"
    log_ok "Arquivos:        ${#BACKED_UP_FILES[@]}"
    log_ok "Restore rápido:  cp -a ${BACKUP_DIR}/. /"
    STATE_MANIFEST_WRITTEN=1
}

# ── Backup standalone (--backup-only) ──────────────────────────────────────────
# Lista todos os destinos que o instalador toca. Centralizada aqui para evitar
# duplicação com as funções de instalação.
backup_targets() {
    local targets=(
        "/etc/fail2ban/filter.d/sip-auth-fail.conf"
        "/etc/fail2ban/filter.d/sip-auth-ip.conf"
        "/etc/fail2ban/jail.d/event_guard.conf"
        "/etc/sudoers.d/fluxsbc-event-guard"
        "/etc/systemd/system/event_guard.service"
        "${FLUXSBC_FS_DIR}/event_guard_daemon.php"
        "${FLUXSBC_FS_DIR}/lib/flux.eventsocket.php"
    )
    printf '%s\n' "${targets[@]}"

    # Todos os .php do módulo CI2, se o diretório existir
    if [[ -d "$FLUXSBC_MODULE_DIR" ]]; then
        find "$FLUXSBC_MODULE_DIR" -type f -name "*.php" 2>/dev/null
    fi
}

backup_only() {
    log_section "Backup standalone (--backup-only)"

    init_backup_dir

    log_section "Coletando arquivos-alvo"

    local present=0 absent=0 target
    while IFS= read -r target; do
        if [[ -f "$target" ]]; then
            backup_file "$target"
            log_ok "  ${target}"
            present=$((present + 1))
        else
            log_info "  (ausente) ${target}"
            absent=$((absent + 1))
        fi
    done < <(backup_targets)

    log_info "Presentes: ${present} | Ausentes: ${absent}"

    write_backup_manifest
}

# ── Cleanup ────────────────────────────────────────────────────────────────────
cleanup() {
    if [[ -n "${MYSQL_CNF:-}" && -f "$MYSQL_CNF" ]]; then
        rm -f "$MYSQL_CNF"
    fi
}
trap cleanup EXIT

# ── Pré-requisitos ─────────────────────────────────────────────────────────────
check_root() {
    if [[ $EUID -ne 0 ]]; then
        if [[ $DRY_RUN -eq 1 ]]; then
            log_warn "Executando dry-run sem root. Verificações que exigem privilégios podem falhar."
        else
            log_error "Este script deve ser executado como root."
            exit 1
        fi
    fi
}

check_debian() {
    if [[ ! -f /etc/debian_version ]]; then
        log_error "Este script suporta apenas Debian."
        exit 1
    fi
    log_ok "Debian detectado: $(cat /etc/debian_version)"
}

check_files() {
    if [[ $STATE_FILES_CHECKED -eq 1 ]]; then
        return 0
    fi

    log_section "Verificando arquivos de instalação"

    local missing=0
    local required_files=(
        "web_interface/flux/application/modules/event_guard/controllers/event_guard.php"
        "web_interface/flux/application/modules/event_guard/models/event_guard_model.php"
        "web_interface/flux/application/modules/event_guard/libraries/event_guard_form.php"
        "web_interface/flux/application/modules/event_guard/views/view_event_guard_list.php"
        "web_interface/flux/application/modules/event_guard/views/view_event_guard_whitelist_list.php"
        "web_interface/flux/application/modules/event_guard/views/view_event_guard_whitelist_add.php"
        "web_interface/flux/application/modules/event_guard/views/view_event_guard_edit.php"
        "freeswitch/fs/event_guard_daemon.php"
        "freeswitch/fs/lib/flux.eventsocket.php"
        "config/fail2ban/event_guard.conf"
        "config/fail2ban/sip-auth-fail.conf"
        "config/fail2ban/sip-auth-ip.conf"
        "config/sudoers.d/fluxsbc-event-guard"
        "config/fail2ban/event_guard.service"
    )

    for f in "${required_files[@]}"; do
        if [[ ! -f "${FLUX_SOURCE_DIR}/${f}" ]]; then
            log_error "Arquivo não encontrado: ${FLUX_SOURCE_DIR}/${f}"
            missing=1
        else
            log_ok "  ${f}"
        fi
    done

    if [[ $missing -eq 1 ]]; then
        log_error "Arquivos obrigatórios ausentes. Abortando."
        exit 1
    fi

    STATE_FILES_CHECKED=1
}

# ── fail2ban ──────────────────────────────────────────────────────────────────
install_fail2ban() {
    log_section "Instalando fail2ban e geoip-bin"

    run apt-get update -qq

    if dpkg -l fail2ban 2>/dev/null | grep -q '^ii'; then
        log_ok "fail2ban já instalado: $(fail2ban-client --version 2>&1 | head -1)"
    else
        log_info "Instalando fail2ban via apt..."
        run env DEBIAN_FRONTEND=noninteractive apt-get install -y fail2ban
        log_ok "fail2ban instalado."
    fi

    if dpkg -l geoip-bin 2>/dev/null | grep -q '^ii'; then
        log_ok "geoip-bin já instalado."
    else
        log_info "Instalando geoip-bin via apt..."
        run env DEBIAN_FRONTEND=noninteractive apt-get install -y geoip-bin geoip-database
        log_ok "geoip-bin instalado."
    fi

    if ! systemctl is-enabled fail2ban &>/dev/null; then
        run systemctl enable fail2ban
        log_ok "fail2ban habilitado no boot."
    fi

    if ! systemctl is-active --quiet fail2ban; then
        run systemctl start fail2ban
        log_ok "fail2ban iniciado."
    fi
}

configure_fail2ban() {
    log_section "Configurando fail2ban"

    # Cria jail.local se não existir (evita sobrescrever customizações)
    if [[ ! -f /etc/fail2ban/jail.local ]]; then
        run cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
        log_ok "jail.local criado a partir de jail.conf."
    else
        log_ok "jail.local já existe, mantendo."
    fi

    # Filtros
    for filter in sip-auth-fail sip-auth-ip; do
        local src="${SRC_FAIL2BAN_DIR}/${filter}.conf"
        local dst="/etc/fail2ban/filter.d/${filter}.conf"

        if [[ -f "$dst" ]]; then
            log_ok "filter.d/${filter}.conf já existe, mantendo."
        else
            run cp "$src" "$dst"
            log_ok "filter.d/${filter}.conf instalado."
        fi
    done

    # Jail
    local jail_src="${SRC_FAIL2BAN_DIR}/event_guard.conf"
    local jail_dst="/etc/fail2ban/jail.d/event_guard.conf"

    if [[ -f "$jail_dst" ]]; then
        log_ok "jail.d/event_guard.conf já existe, mantendo."
    else
        run cp "$jail_src" "$jail_dst"
        log_ok "jail.d/event_guard.conf instalado."
    fi

    # Em dry-run, não testa nem recarrega — a config ainda não foi escrita
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "fail2ban-client --test"
        log_dry "systemctl reload fail2ban"
        log_dry "fail2ban-client status sip-auth-fail / sip-auth-ip"
        return 0
    fi

    # Valida configuração antes de recarregar
    log_info "Validando configuração do fail2ban..."
    if ! fail2ban-client --test &>/dev/null; then
        log_error "Erro na configuração do fail2ban. Saída detalhada:"
        fail2ban-client --test
        exit 1
    fi

    if systemctl is-active --quiet fail2ban; then
        systemctl reload fail2ban
    else
        systemctl start fail2ban
    fi
    sleep 2

    # Verifica jails
    for jail in sip-auth-fail sip-auth-ip; do
        if fail2ban-client status "$jail" &>/dev/null; then
            log_ok "Jail '${jail}' ativo."
        else
            log_error "Jail '${jail}' não iniciou. Verifique /var/log/fail2ban.log"
            exit 1
        fi
    done
}

# ── sudoers ───────────────────────────────────────────────────────────────────
configure_sudoers() {
    log_section "Configurando sudoers"

    local src="${SRC_SUDOERS_DIR}/fluxsbc-event-guard"
    local dst="/etc/sudoers.d/fluxsbc-event-guard"

    if [[ -f "$dst" ]]; then
        log_ok "sudoers já existe, mantendo: ${dst}"
        return 0
    fi

    # Validação prévia: roda contra o source (não exige cópia)
    if ! visudo -c -f "$src" &>/dev/null; then
        log_error "Erro de sintaxe no source do sudoers: $src"
        visudo -c -f "$src" || true
        exit 1
    fi
    log_ok "Sintaxe do sudoers (source) válida."

    run cp "$src" "$dst"
    run chmod 440 "$dst"

    # Em dry-run, não há $dst para validar
    if [[ $DRY_RUN -eq 0 ]]; then
        if ! visudo -c -f "$dst" &>/dev/null; then
            log_error "Erro de sintaxe no sudoers instalado. Removendo arquivo."
            rm -f "$dst"
            exit 1
        fi
    fi

    log_ok "sudoers configurado e validado."
}

# ── Banco de dados ─────────────────────────────────────────────────────────────
# Remove apenas espaços de início e fim, preservando espaços internos (senhas legítimas)
trim() {
    local s="$1"
    s="${s#"${s%%[![:space:]]*}"}"
    s="${s%"${s##*[![:space:]]}"}"
    printf '%s' "$s"
}

# Lê um valor de chave do flux-config.conf sem abortar se a chave faltar
read_conf_value() {
    local key="$1" file="$2"
    grep -Po "^\\s*${key}\\s*=\\s*\\K.*" "$file" 2>/dev/null | head -n1 || true
}

build_mysql_cnf() {
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Criaria arquivo temporário de credenciais MySQL (mktemp, chmod 600)."
        return 0
    fi
    MYSQL_CNF=$(mktemp)
    chmod 600 "$MYSQL_CNF"
    cat > "$MYSQL_CNF" <<EOF
[client]
host=${DB_HOST}
user=${DB_USER}
password=${DB_PASS}
EOF
}

mysql_run() {
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "mysql --defaults-extra-file=*** ${DB_NAME} $*"
        return 0
    fi
    mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" "$@"
}

mysql_run_file() {
    local file="$1"
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "mysql --defaults-extra-file=*** ${DB_NAME} < ${file}"
        return 0
    fi
    mysql --defaults-extra-file="$MYSQL_CNF" -f "$DB_NAME" < "$file"
}

run_sql() {
    log_section "Executando scripts SQL"

    if [[ -z "$FLUX_CONF" ]]; then
        log_warn "flux-config.conf não encontrado em nenhum dos caminhos padrão:"
        for candidate in "${FLUX_CONF_CANDIDATES[@]}"; do
            log_warn "  - ${candidate}"
        done
        log_info "Informe os dados de conexão manualmente:"
        read -rp "  Host MySQL: " DB_HOST
        read -rp "  Banco:      " DB_NAME
        read -rp "  Usuário:    " DB_USER
        read -rsp "  Senha:      " DB_PASS
        echo
    else
        # Trim apenas borda — preserva espaços internos (item 4)
        DB_HOST=$(trim "$(read_conf_value dbhost "$FLUX_CONF")")
        DB_NAME=$(trim "$(read_conf_value dbname "$FLUX_CONF")")
        DB_USER=$(trim "$(read_conf_value dbuser "$FLUX_CONF")")
        DB_PASS=$(trim "$(read_conf_value dbpass "$FLUX_CONF")")

        if [[ -z "$DB_HOST" || -z "$DB_NAME" || -z "$DB_USER" || -z "$DB_PASS" ]]; then
            log_error "Não foi possível ler todas as credenciais de ${FLUX_CONF}."
            log_error "  dbhost='${DB_HOST}' dbname='${DB_NAME}' dbuser='${DB_USER}' dbpass=$([[ -n "$DB_PASS" ]] && echo '***' || echo '(vazio)')"
            exit 1
        fi
        log_ok "Credenciais lidas de ${FLUX_CONF}"
    fi

    # Cria arquivo de credenciais temporário (item 1) — senha nunca vai para a linha de comando
    build_mysql_cnf

    # Testa conexão
    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Testaria conexão: mysql --defaults-extra-file=*** ${DB_NAME} -e 'SELECT 1'"
    else
        if ! mysql_run -e "SELECT 1" &>/dev/null; then
            log_error "Não foi possível conectar ao MySQL. Verifique as credenciais."
            exit 1
        fi
        log_ok "Conexão MySQL OK."
    fi

    local sql_file="${SRC_SQL_DIR}/event_guard_1.0.0.sql"
    local filename
    filename=$(basename "$sql_file")
    log_info "Executando ${filename}..."

    if mysql_run_file "$sql_file"; then
        log_ok "${filename} executado com sucesso."
    else
        log_error "Erro ao executar ${filename}. Abortando."
        exit 1
    fi
}

# ── Arquivos fs/ ──────────────────────────────────────────────────────────────
install_fs_files() {
    log_section "Instalando arquivos fs/"

    if [[ ! -d "$FLUXSBC_FS_DIR" ]]; then
        log_error "Diretório fs/ não encontrado: ${FLUXSBC_FS_DIR}"
        exit 1
    fi

    # Daemon
    local daemon_src="${SRC_FS_DIR}/event_guard_daemon.php"
    local daemon_dst="${FLUXSBC_FS_DIR}/event_guard_daemon.php"

    if [[ -f "$daemon_dst" ]]; then
        log_ok "event_guard_daemon.php já existe, mantendo."
    else
        run cp "$daemon_src" "$daemon_dst"
        run chown www-data:www-data "$daemon_dst"
        log_ok "event_guard_daemon.php instalado."
    fi

    # flux.eventsocket.php — garante que lib/ exista (item 6)
    local esl_src="${SRC_FS_DIR}/lib/flux.eventsocket.php"
    local esl_dst="${FLUXSBC_FS_DIR}/lib/flux.eventsocket.php"

    if [[ -f "$esl_dst" ]]; then
        log_ok "flux.eventsocket.php já existe, mantendo."
    else
        run mkdir -p "$(dirname "$esl_dst")"
        run cp "$esl_src" "$esl_dst"
        run chown www-data:www-data "$esl_dst"
        log_ok "flux.eventsocket.php instalado."
    fi
}

# ── Módulo web CI2 ────────────────────────────────────────────────────────────
check_module() {
    log_section "Verificando módulo web event_guard"

    if [[ -d "$FLUXSBC_MODULE_DIR" ]]; then
        log_ok "Diretório do módulo encontrado: ${FLUXSBC_MODULE_DIR}"
    else
        log_error "Diretório do módulo não encontrado: ${FLUXSBC_MODULE_DIR}"
        exit 1
    fi
}

# ── Systemd service ───────────────────────────────────────────────────────────
install_service() {
    log_section "Configurando systemd service"

    local src="${SRC_FAIL2BAN_DIR}/event_guard.service"
    local dst="/etc/systemd/system/event_guard.service"

    if [[ -f "$dst" ]]; then
        log_ok "systemd unit já existe, mantendo: ${dst}"
    else
        # Ajusta ExecStart para o path real do daemon
        if [[ $DRY_RUN -eq 1 ]]; then
            log_dry "sed 's|/var/www/html/fs/event_guard_daemon.php|${FLUXSBC_FS_DIR}/event_guard_daemon.php|g' '$src' > '$dst'"
        else
            sed "s|/var/www/html/fs/event_guard_daemon.php|${FLUXSBC_FS_DIR}/event_guard_daemon.php|g" \
                "$src" > "$dst"
            log_ok "systemd unit instalado: ${dst}"
        fi
    fi

    run systemctl daemon-reload
    run systemctl enable event_guard

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "systemctl is-enabled event_guard (confirmação)"
        return 0
    fi

    if systemctl is-enabled --quiet event_guard; then
        log_ok "Serviço event_guard habilitado (inicia automaticamente no próximo boot)."
    else
        log_error "Serviço event_guard não está habilitado. Verifique:"
        log_error "  systemctl status event_guard"
        exit 1
    fi
}

# ── Verificação final ─────────────────────────────────────────────────────────
verify_installation() {
    log_section "Verificação final"

    if [[ $DRY_RUN -eq 1 ]]; then
        log_dry "Verificação final pulada em dry-run (nada foi instalado)."
        return 0
    fi

    local ok=1

    # fail2ban jails
    for jail in sip-auth-fail sip-auth-ip; do
        if fail2ban-client status "$jail" &>/dev/null; then
            log_ok "fail2ban jail '${jail}': ativo"
        else
            log_error "fail2ban jail '${jail}': inativo"
            ok=0
        fi
    done

    # sudoers
    if visudo -c -f /etc/sudoers.d/fluxsbc-event-guard &>/dev/null; then
        log_ok "sudoers: válido"
    else
        log_error "sudoers: inválido"
        ok=0
    fi

    # Daemon
    if systemctl is-enabled --quiet event_guard; then
        log_ok "Daemon event_guard: habilitado (inicia no boot)"
    else
        log_error "Daemon event_guard: não habilitado"
        ok=0
    fi

    # GeoIP
    if command -v geoiplookup &>/dev/null && geoiplookup 8.8.8.8 &>/dev/null; then
        log_ok "geoiplookup: funcional"
    else
        log_warn "geoiplookup: não responde — verifique se geoip-database está instalado"
    fi

    echo
    if [[ $ok -eq 1 ]]; then
        log_ok "Instalação concluída com sucesso."
    else
        log_warn "Instalação concluída com erros. Verifique os itens acima."
        exit 1
    fi
}

# ── Menu interativo ────────────────────────────────────────────────────────────
mode_label() {
    [[ $DRY_RUN -eq 1 ]] && echo "DRY-RUN (simulação)" || echo "REAL (aplica mudanças)"
}

backup_label() {
    if [[ $STATE_BACKUP_INIT -eq 1 ]]; then
        echo "${BACKUP_DIR}"
    else
        echo "(ainda não inicializado)"
    fi
}

show_menu() {
    cat <<EOF

${BLUE}══════════════════════════════════════════${NC}
${BLUE} Menu - FluxSBC event_guard${NC}
${BLUE}══════════════════════════════════════════${NC}
  Modo:           $(mode_label)
  Backup ativo:   $(backup_label)
  Arquivos com backup: ${#BACKED_UP_FILES[@]}

   [1]  Instalação completa
   [2]  Backup dos arquivos atuais (snapshot)
   ──
   [3]  Instalar fail2ban + geoip-bin (apt)
   [4]  Configurar fail2ban (filtros + jail)
   [5]  Configurar sudoers
   [6]  Instalar arquivos fs/ (FreeSWITCH)
   [7]  Verificar módulo web (CI2)
   [8]  Configurar systemd service
   [9]  Verificar instalação
   ──
  [10]  Alternar modo DRY-RUN
  [11]  Gravar manifesto agora
   [0]  Sair

EOF
}

confirm() {
    local prompt="$1" default="${2:-n}" reply
    if [[ "$default" == "s" ]]; then
        prompt="${prompt} [S/n]: "
    else
        prompt="${prompt} [s/N]: "
    fi
    read -rp "$prompt" reply
    reply="${reply:-$default}"
    [[ "$reply" =~ ^[sSyY]$ ]]
}

interactive_loop() {
    if ! [[ -t 0 ]]; then
        log_error "Modo interativo requer um terminal. Use --all para execução não-interativa."
        exit 1
    fi

    local choice
    while true; do
        show_menu
        read -rp "Opção: " choice
        echo

        case "$choice" in
            1)
                if [[ $DRY_RUN -eq 0 ]]; then
                    confirm "Confirmar instalação completa em produção?" "n" || { log_info "Cancelado."; continue; }
                fi
                run_full_install
                ;;
            2)  backup_only ;;
            3)  check_files; init_backup_dir; install_fail2ban ;;
            4)  check_files; init_backup_dir; configure_fail2ban ;;
            5)  check_files; init_backup_dir; configure_sudoers ;;
            6)  check_files; init_backup_dir; install_fs_files ;;
            7)  check_module ;;
            8)  check_files; init_backup_dir; install_service ;;
            9)  verify_installation ;;
            10)
                DRY_RUN=$(( 1 - DRY_RUN ))
                log_info "Modo alterado para: $(mode_label)"
                ;;
            11)
                # Reset do flag para permitir reescrever após nova rodada de backups
                STATE_MANIFEST_WRITTEN=0
                write_backup_manifest
                ;;
            0)
                # Ao sair, garante manifesto se houve backups e ainda não foi gravado
                if [[ ${#BACKED_UP_FILES[@]} -gt 0 ]]; then
                    write_backup_manifest
                fi
                log_info "Saindo."
                break
                ;;
            "")
                continue
                ;;
            *)
                log_warn "Opção inválida: '${choice}'"
                ;;
        esac
    done
}

# Fluxo completo de instalação (usado por --all e pela opção [1] do menu)
run_full_install() {
    check_files
    init_backup_dir
    install_fail2ban
    configure_fail2ban
    configure_sudoers
    install_fs_files
    check_module
    install_service
    write_backup_manifest
    verify_installation
}

print_banner() {
    local mode_line
    if [[ $BACKUP_ONLY -eq 1 && $DRY_RUN -eq 1 ]]; then
        mode_line="║   Modo: BACKUP-ONLY + DRY-RUN              ║"
    elif [[ $BACKUP_ONLY -eq 1 ]]; then
        mode_line="║   Modo: BACKUP-ONLY (sem instalação)       ║"
    elif [[ $RUN_ALL -eq 1 && $DRY_RUN -eq 1 ]]; then
        mode_line="║   Modo: ALL + DRY-RUN                      ║"
    elif [[ $RUN_ALL -eq 1 ]]; then
        mode_line="║   Modo: ALL (instalação completa)          ║"
    elif [[ $DRY_RUN -eq 1 ]]; then
        mode_line="║   Modo: MENU + DRY-RUN                     ║"
    else
        mode_line="║   Modo: MENU INTERATIVO                    ║"
    fi

    echo
    echo "${BLUE}╔════════════════════════════════════════════╗${NC}"
    echo "${BLUE}║   FluxSBC — Protetor SIP (event_guard)     ║${NC}"
    echo "${BLUE}${mode_line}${NC}"
    echo "${BLUE}╚════════════════════════════════════════════╝${NC}"
    echo
}

# ── Main ──────────────────────────────────────────────────────────────────────
main() {
    parse_args "$@"
    print_banner

    check_root
    check_debian

    if [[ $BACKUP_ONLY -eq 1 ]]; then
        backup_only
        echo
        if [[ $DRY_RUN -eq 1 ]]; then
            log_warn "DRY-RUN concluído. Nenhum arquivo foi efetivamente copiado."
        else
            log_ok "Backup standalone concluído."
        fi
        exit 0
    fi

    if [[ $RUN_ALL -eq 1 ]]; then
        run_full_install
        if [[ $DRY_RUN -eq 1 ]]; then
            echo
            log_warn "DRY-RUN concluído. Nenhuma alteração foi aplicada."
        fi
        exit 0
    fi

    # Sem --all e sem --backup-only: menu interativo
    interactive_loop
}

main "$@"