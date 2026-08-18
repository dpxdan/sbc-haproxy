#!/bin/bash
set -euo pipefail

echo "=== FluxSBC TLS setup ==="
echo

# ==============================
# 1. Tipo de certificado
# ==============================
echo "Certificado gerado via:"
select CERT_TYPE in "letsencrypt" "dehydrated"; do
	case "$CERT_TYPE" in
		letsencrypt|dehydrated) break ;;
		*) echo "Opção inválida." ;;
	esac
done

# ==============================
# 2. FQDN
# ==============================
read -rp "Informe o FQDN do servidor: " FQDN
[[ -z "$FQDN" ]] && echo "FQDN inválido." && exit 1

# Validação de FQDN
if ! [[ "$FQDN" =~ ^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$ ]]; then
    echo "FQDN com formato inválido."
    exit 1
fi

# ==============================
# 2.1 Portas TLS
# ==============================
read -rp "Porta TLS interna [5061]: " INTERNAL_TLS_PORT
INTERNAL_TLS_PORT=${INTERNAL_TLS_PORT:-5061}

read -rp "Porta TLS externa [5081]: " EXTERNAL_TLS_PORT
EXTERNAL_TLS_PORT=${EXTERNAL_TLS_PORT:-5081}

for p in "$INTERNAL_TLS_PORT" "$EXTERNAL_TLS_PORT"; do
	if ! [[ "$p" =~ ^[0-9]+$ ]] || (( p < 1 || p > 65535 )); then
		echo "Porta inválida: $p"
		exit 1
	fi
done

# ==============================
# 3. Diretório TLS do FluxSBC
# ==============================
read -rp "Diretório TLS do FluxSBC [/etc/freeswitch/tls]: " FS_TLS_DIR
FS_TLS_DIR=${FS_TLS_DIR:-/etc/freeswitch/tls}

[[ "$FS_TLS_DIR" == "/" ]] && echo "Diretório TLS inválido." && exit 1

# ==============================
# 4. Diretório do certificado
# ==============================
if [[ "$CERT_TYPE" == "letsencrypt" ]]; then
	CERT_DIR="/etc/letsencrypt/live/$FQDN"
else
	CERT_DIR="/etc/dehydrated/certs/$FQDN"
fi

for f in cert.pem chain.pem fullchain.pem privkey.pem; do
	[[ ! -f "$CERT_DIR/$f" ]] && echo "Arquivo ausente: $CERT_DIR/$f" && exit 1
done

# ==============================
# 4.1 Verificar usuário freeswitch
# ==============================
if ! id freeswitch &>/dev/null; then
    echo "Erro: Usuário freeswitch não encontrado."
    exit 1
fi

# ==============================
# 5. Backup
# ==============================
BACKUP_DIR="/var/backups/freeswitch-tls-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

[[ -d "$FS_TLS_DIR" ]] && cp -a "$FS_TLS_DIR" "$BACKUP_DIR/"

# ==============================
# 6. Diretório TLS
# ==============================
mkdir -p "$FS_TLS_DIR"
rm -f "$FS_TLS_DIR"/*

# ==============================
# 7. all.pem
# ==============================
cat "$CERT_DIR/fullchain.pem" > "$FS_TLS_DIR/all.pem"
cat "$CERT_DIR/privkey.pem"   >> "$FS_TLS_DIR/all.pem"

# ==============================
# 8. Copiar certificados
# ==============================
cp "$CERT_DIR/"{cert.pem,chain.pem,fullchain.pem,privkey.pem} "$FS_TLS_DIR"

# ==============================
# 9. Links simbólicos
# ==============================
ln -sf "$FS_TLS_DIR/all.pem" "$FS_TLS_DIR/agent.pem"
ln -sf "$FS_TLS_DIR/all.pem" "$FS_TLS_DIR/tls.pem"
ln -sf "$FS_TLS_DIR/all.pem" "$FS_TLS_DIR/wss.pem"
ln -sf "$FS_TLS_DIR/all.pem" "$FS_TLS_DIR/dtls-srtp.pem"

# ==============================
# 10. Permissões
# ==============================
chmod 600 "$FS_TLS_DIR"/privkey.pem
chmod 600 "$FS_TLS_DIR"/all.pem
chmod 644 "$FS_TLS_DIR"/{cert,chain,fullchain}.pem
chown -R freeswitch:freeswitch "$FS_TLS_DIR"

# ==============================
# 11. vars.xml
# ==============================
VARS_XML="/etc/freeswitch/vars.xml"
[[ ! -f "$VARS_XML" ]] && echo "vars.xml não encontrado." && exit 1

cp "$VARS_XML" "$BACKUP_DIR/vars.xml"

# domain_name
sed -i -E \
  "s|(X-PRE-PROCESS cmd=\"set\" data=\"domain_name=)[^\"]*|\1$FQDN|" \
  "$VARS_XML"

# ==============================
# 12. TLS internal
# ==============================
if ! grep -q 'internal_ssl_enable=' "$VARS_XML"; then
	sed -i '/domain_name=/a\
<X-PRE-PROCESS cmd="set" data="internal_ssl_enable=true"/>' "$VARS_XML"
fi
sed -i -E \
  's|(internal_ssl_enable=)[^"]*|\1true|' "$VARS_XML"

if ! grep -q 'internal_tls_port=' "$VARS_XML"; then
	sed -i \
	  "/internal_ssl_enable=/a\
<X-PRE-PROCESS cmd=\"set\" data=\"internal_tls_port=$INTERNAL_TLS_PORT\"/>" \
	  "$VARS_XML"
fi
sed -i -E \
  "s|(internal_tls_port=)[^\"]*|\1$INTERNAL_TLS_PORT|" \
  "$VARS_XML"

# ==============================
# 13. TLS external
# ==============================
if ! grep -q 'external_ssl_enable=' "$VARS_XML"; then
	sed -i '/domain_name=/a\
<X-PRE-PROCESS cmd="set" data="external_ssl_enable=true"/>' "$VARS_XML"
fi
sed -i -E \
  "s|(external_ssl_enable=)[^\"]*|\1true|" "$VARS_XML"

if ! grep -q 'external_tls_port=' "$VARS_XML"; then
	sed -i "/external_ssl_enable=/a\
<X-PRE-PROCESS cmd=\"set\" data=\"external_tls_port=$EXTERNAL_TLS_PORT\"/>" "$VARS_XML"
fi
sed -i -E \
  "s|(external_tls_port=)[^\"]*|\1$EXTERNAL_TLS_PORT|" "$VARS_XML"

# ==============================
# Final
# ==============================
echo
echo "TLS configurado com sucesso!"
echo
echo "Configurações aplicadas em vars.xml:"
echo " - domain_name=$FQDN"
echo " - internal_ssl_enable=true"
echo " - internal_tls_port=$INTERNAL_TLS_PORT"
echo " - external_ssl_enable=true"
echo " - external_tls_port=$EXTERNAL_TLS_PORT"
echo
echo "Backup salvo em: $BACKUP_DIR"
echo
echo "Aplicar configurações:"
echo "  fs_cli -x 'reloadxml'"
echo
echo "Se necessário reiniciar perfil:"
echo "  fs_cli -x 'sofia profile external restart'"
echo "  fs_cli -x 'sofia profile internal restart'"