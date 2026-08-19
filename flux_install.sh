#!/bin/bash
###############################################################################
# Flux Tecnologia - Unindo pessoas e negócios
#
# Copyright (C) 2024 Flux Tecnologia
# FluxSBC Version 6.3
# License https://www.gnu.org/licenses/agpl-3.0.html
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
###############################################################################

#################################
##########  variáveis ###########
#################################

#General Configuration
FLUX_SOURCE_DIR=/opt/flux
FLUX_HOST_DOMAIN_NAME="host.domain.tld"
IS_ENTERPRISE="False"

#FLUX Configuration
FLUXDIR=/var/lib/flux/
FLUXEXECDIR=/usr/local/flux/
FLUXLOGDIR=/var/log/flux/

TIMESTAMP=$(date "+%Y%m%d_%H%M%S")

LOG_FILE="$FLUXLOGDIR/flux_install_$TIMESTAMP.log"

#Freeswich Configuration
FS_DIR=/usr/share/freeswitch
FS_SOUNDSDIR=${FS_DIR}/sounds/pt/BR/karina

#HTML and Mysql Configuration
WWWDIR=/var/www/html
FLUX_DATABASE_NAME="flux"
MYSQL_CNF="/etc/mysql/mysql.cnf"

#Database High Availability Configuration
#FLUX_DB_MODE: local | haproxy_sidecar | haproxy_vip
FLUX_DB_MODE="local"
#FLUX_DB_NODES: nos Galera no formato "ip:porta,ip:porta,ip:porta"
FLUX_DB_NODES=""
#FLUX_DB_HOSTS: lista para o HAProxy; em instalacao remota use apenas hosts MySQL.
#Ex.: "10.211.55.28:3316,10.211.55.29:3316"
FLUX_DB_HOSTS=""
#FLUX_DB_VIP: obrigatorio quando FLUX_DB_MODE=haproxy_vip
FLUX_DB_VIP=""
FLUX_DB_WRITE_PORT=3306
FLUX_DB_READ_PORT=3307
FLUX_DB_CHECK_PORT=9200
FLUX_DB_STATS_PORT=8404
#Credenciais administrativas usadas para carga de schema e migracoes
MYSQL_ADMIN_HOST=""
MYSQL_ADMIN_PORT=""
MYSQL_ADMIN_USER="root"
MYSQL_ADMIN_PASSWORD=""

#Event Guard (Protetor SIP) Configuration
EVENT_GUARD_FAIL2BAN_SRC="${FLUX_SOURCE_DIR}/config/fail2ban"
EVENT_GUARD_SUDOERS_SRC="${FLUX_SOURCE_DIR}/config/sudoers.d"
EVENT_GUARD_DAEMON="${FLUX_SOURCE_DIR}/freeswitch/fs/event_guard_daemon.php"

mkdir -p ${FLUXLOGDIR}
touch "$LOG_FILE"

#################################
####  general functions #########
#################################

#Log Messages
log_message()
{
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

#Generate random password
genpasswd()
{
    length=$1
    digits=({1..9})
    lower=({a..z})
    upper=({A..Z})
    CharArray=(${digits[*]} ${lower[*]} ${upper[*]})
    ArrayLength=${#CharArray[*]}
    password=""
    for i in $(seq 1 $length); do
        index=$(($RANDOM % $ArrayLength))
        char=${CharArray[$index]}
        password=${password}${char}
    done
    echo $password
}

MYSQL_ROOT_PASSWORD=$(echo "$(genpasswd 20)" | sed s/./*/5)
FLUXUSER_MYSQL_PASSWORD=$(echo "$(genpasswd 20)" | sed s/./*/5)

#Resolve o endpoint administrativo do banco
resolve_db_admin_endpoint()
{
    if [ "${FLUX_DB_MODE}" = "local" ]; then
        MYSQL_ADMIN_HOST="${MYSQL_ADMIN_HOST:-127.0.0.1}"
        MYSQL_ADMIN_PORT="${MYSQL_ADMIN_PORT:-3306}"
        MYSQL_ADMIN_PASSWORD="${MYSQL_ADMIN_PASSWORD:-${MYSQL_ROOT_PASSWORD}}"
        return 0
    fi

    if [ -z "${MYSQL_ADMIN_HOST}" ]; then
        local first_node=${FLUX_DB_NODES%%,*}
        MYSQL_ADMIN_HOST=${first_node%%:*}
        if [ "${first_node}" != "${MYSQL_ADMIN_HOST}" ]; then
            MYSQL_ADMIN_PORT=${first_node##*:}
        fi
    fi

    MYSQL_ADMIN_PORT="${MYSQL_ADMIN_PORT:-3306}"

    if [ -z "${MYSQL_ADMIN_HOST}" ]; then
        log_message "Erro: nao foi possivel determinar MYSQL_ADMIN_HOST. Informe FLUX_DB_NODES ou MYSQL_ADMIN_HOST."
        exit 1
    fi

    if [ -z "${MYSQL_ADMIN_PASSWORD}" ]; then
        log_message "Erro: MYSQL_ADMIN_PASSWORD e obrigatorio quando FLUX_DB_MODE nao e local."
        exit 1
    fi
}

#Cliente mysql apontando para o endpoint administrativo
mysql_admin()
{
    mysql -u"${MYSQL_ADMIN_USER}" -p"${MYSQL_ADMIN_PASSWORD}" -h "${MYSQL_ADMIN_HOST}" -P "${MYSQL_ADMIN_PORT}" --protocol=TCP "$@"
}

#mysqladmin apontando para o endpoint administrativo
mysqladmin_admin()
{
    mysqladmin -u"${MYSQL_ADMIN_USER}" -p"${MYSQL_ADMIN_PASSWORD}" -h "${MYSQL_ADMIN_HOST}" -P "${MYSQL_ADMIN_PORT}" --protocol=TCP "$@"
}

#Fetch OS Distribution
get_linux_distribution()
{
    log_message "Executando função: get_linux_distribution"
    log_message "$Cyan ===get_linux_distribution===$Color_Off"
    sleep 2s
    V1=$(cat /etc/*release | head -n1 | tail -n1 | cut -c 14- | cut -c1-18)
    V2=$(cat /etc/*release | head -n7 | tail -n1 | cut -c 14- | cut -c1-14)
    V3=$(cat /etc/*release | grep Deb | head -n1 | tail -n1 | cut -c 14- | cut -c1-19)
    V4=$(cat /etc/*release | grep Deb | head -n1 | tail -n1 | cut -c 14- | cut -c1-19)
    V5=$(cat /etc/*release | grep Deb | head -n1 | tail -n1 | cut -c 14- | cut -c1-19)
    if [[ $V1 = "Debian GNU/Linux 9" ]]; then
        DIST="DEBIAN"
        log_message "$Green ===Your OS is $V1===$Color_Off"
    elif [[ $V2 = "CentOS Linux 7" ]]; then
        DIST="CENTOS"
        log_message "$Green ===Your OS is $V2===$Color_Off"
    elif [[ $V3 = "Debian GNU/Linux 10" ]]; then
        DIST="DEBIAN10"
        log_message "$Green ===Your OS is $V3===$Color_Off"
    elif [[ $V4 = "Debian GNU/Linux 11" ]]; then
        DIST="DEBIAN11"
        log_message "$Green ===Your OS is $V4===$Color_Off"
    elif [[ $V5 = "Debian GNU/Linux 12" ]]; then
        DIST="DEBIAN12"
        log_message "$Green ===Your OS is $V5===$Color_Off"
    else
        DIST="OTHER"
        log_message 'Ooops!!! Versao Linux nao suportada.'
        exit 1
    fi
    sleep 4s
}

#Verify freeswitch token
verification()
{
    log_message "Executando função: verification"
    tput bold
    echo "                       Autentificação requerida !!!!!!
Os Tokens de Acesso são necessários para acessar os pacotes de instalação do Softswitch."
    echo ""

    echo "Caso não posua o token, entre em contato com felipe@flux.net.br"
    sleep 3s
    echo "" && echo ""
    read -s -p "Insira o token Flux: ${FS_TOKEN}"
    tput sgr0
    FS_TOKEN=${REPLY}
    echo ""
    if [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" || $DIST = "DEBIAN12" ]]; then
        wget --http-user=signalwire --http-password=$FS_TOKEN -O /usr/share/keyrings/signalwire-freeswitch-repo.gpg https://freeswitch.signalwire.com/repo/deb/debian-release/signalwire-freeswitch-repo.gpg
        verify_debian10="$?"
        if [ $verify_debian10 = 0 ]; then
            tput bold
            echo "******************************************************************************"
            echo ""
            echo "Você inseriu um token válido"
            echo ""
            echo "******************************************************************************"
            sleep 4s
            tput sgr0
        else
            echo ""
            tput bold
            echo "Token inválido"
            echo "******************************************************************************"
            echo ""
            echo "Para mais informacoes felipe@flux.net.br "
            echo ""
            echo "******************************************************************************"
            sleep 3s
            tput sgr0
            exit 0
        fi
    elif [ $DIST = "CENTOS" ]; then
        yum -y remove freeswitch-release-repo.noarch
        echo "signalwire" > /etc/yum/vars/signalwireusername
        echo "$FS_TOKEN" > /etc/yum/vars/signalwiretoken
        yum install -y https://$(< /etc/yum/vars/signalwireusername):$(< /etc/yum/vars/signalwiretoken)@freeswitch.signalwire.com/repo/yum/centos-release/freeswitch-release-repo-0-1.noarch.rpm
        verify_centos="$?"
        if [ $verify_centos = 0 ]; then
            tput bold
            echo "******************************************************************************"
            echo ""
            echo "Você inseriu um token válido"
            echo ""
            echo "******************************************************************************"
            sleep 4s
            tput sgr0
        else
            echo ""
            tput bold
            echo "Token inválido"
            echo "******************************************************************************"
            echo ""
            echo "Para mais informacoes felipe@flux.net.br "
            echo ""
            echo "******************************************************************************"
            sleep 3s
            tput sgr0
            exit 0
        fi
    fi
}

#Install Prerequisties
install_prerequisties()
{
    log_message "Executando função: install_prerequisties"
    if [ $DIST = "CENTOS" ]; then
        systemctl stop httpd
        systemctl disable httpd
        yum update -y
        yum install -y wget curl git bind-utils ntpdate systemd net-tools whois sendmail sendmail-cf mlocate vim
    elif [ $DIST = "DEBIAN" ]; then
        systemctl stop apache2
        systemctl disable apache2
        apt update -y
        apt install -y sudo wget curl git dnsutils ntpdate systemd net-tools whois sendmail-bin sensible-mda mlocate vim
    elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" || $DIST = "DEBIAN12" ]]; then
        apt-get update -y
        apt-get install -y sudo wget curl git dnsutils python3-pip ntpdate systemd net-tools whois sendmail-bin sensible-mda mlocate vim imagemagick rsyslog sngrep debconf-utils joe
    fi
    cd /usr/src/
    wget http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
    tar -xzvf ioncube_loaders_lin_x86-64.tar.gz
    cd ioncube
}

#Fetch FLUX Source
get_flux_source()
{
    log_message "Executando função: get_flux_source"
    cd /opt
    git clone https://github.com/Amax-Software/sbc flux
}

#License Acceptence
license_accept()
{
    log_message "Executando função: license_accept"
    cd /usr/src
    if [ $IS_ENTERPRISE = "True" ]; then
        echo ""
    fi
    if [ $IS_ENTERPRISE = "False" ]; then
        echo "********************"
        echo "License acceptance"
        echo "********************"
        if [ -f LICENSE ]; then
            more LICENSE
        else
            wget --no-check-certificate -q -O GNU-AGPLv5.0.txt https://raw.githubusercontent.com/fluxtelecom/fluxsbc/master/LICENSE
            more GNU-AGPLv5.0.txt
        fi
        echo "***"
        echo "*** I agree to be bound by the terms of the license - [YES/NO]"
        echo "*** "
        read ACCEPT
        while [ "$ACCEPT" != "yes" ] && [ "$ACCEPT" != "Yes" ] && [ "$ACCEPT" != "YES" ] && [ "$ACCEPT" != "no" ] && [ "$ACCEPT" != "No" ] && [ "$ACCEPT" != "NO" ]; do
            echo "I agree to be bound by the terms of the license - [YES/NO]"
            read ACCEPT
        done
        if [ "$ACCEPT" != "yes" ] && [ "$ACCEPT" != "Yes" ] && [ "$ACCEPT" != "YES" ]; then
            echo "Ooops!!! License rejected!"
            LICENSE_VALID=False
            exit 0
        else
            echo "Hey!!! Licence accepted!"
            LICENSE_VALID=True
        fi
    fi
}

#Install PHP
install_php()
{
    log_message "Executando função: install_php"
    cd /usr/src
    if [ "$DIST" = "DEBIAN" ]; then
        apt -y install lsb-release apt-transport-https ca-certificates
        wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php7.3.list
        apt-get update
        apt install -y php7.3 php7.3-fpm php7.3-mysql php7.3-cli php7.3-json php7.3-readline php7.3-xml php7.3-curl php7.3-gd php7.3-json php7.3-mbstring php7.3-mysql php7.3-opcache php7.3-imap
        apt purge php8.1*
        systemctl stop apache2
        systemctl disable apache2
    elif [ "$DIST" = "CENTOS" ]; then
        yum -y install http://rpms.remirepo.net/enterprise/remi-release-7.rpm
        yum -y install epel-release yum-utils
        yum-config-manager --disable remi-php54
        yum-config-manager --enable remi-php73
        yum install -y php php-fpm php-mysql php-cli php-json php-readline php-xml php-curl php-gd php-json php-mbstring php-mysql php-opcache php-imap
        systemctl stop httpd
        systemctl disable httpd
    elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" || $DIST = "DEBIAN12" ]]; then
        apt -y install lsb-release apt-transport-https ca-certificates
        wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php7.3.list
        apt-get update
        apt install -y php7.3 php7.3-common php7.3-fpm php7.3-mysql php7.3-cli php7.3-json php7.3-readline php7.3-xml php7.3-curl php7.3-gd php7.3-json php7.3-mbstring php7.3-opcache php7.3-imap php7.3-geoip php-pear php7.3-imagick libreoffice ghostscript
        systemctl stop apache2
        systemctl disable apache2
    fi
}

#Install database client and ODBC driver only (backend remoto)
install_db_client_only()
{
    log_message "Executando função: install_db_client_only"
    log_message "FLUX_DB_MODE=${FLUX_DB_MODE}: o servidor MySQL nao sera instalado localmente."

    if [ "${DIST}" = "CENTOS" ]; then
        yum install -y unixODBC mysql mysql-connector-odbc
    else
        apt-get update
        apt-get install -y unixodbc unixodbc-dev default-mysql-client
        cd ${FLUX_SOURCE_DIR}/misc
        tar -xzf odbc.tar.gz
        mkdir -p /usr/lib/x86_64-linux-gnu/odbc/
        if [ -d odbc_conf ] && ls odbc_conf/libmyodbc8* >/dev/null 2>&1; then
            cp -rf odbc_conf/libmyodbc8* /usr/lib/x86_64-linux-gnu/odbc/.
        else
            cp -rf libmyodbc8* /usr/lib/x86_64-linux-gnu/odbc/.
        fi
    fi

    echo "" >> ~/.mysql_passwd
    echo "MySQL fluxuser password:  ${FLUXUSER_MYSQL_PASSWORD} " >> ~/.mysql_passwd
    chmod 400 ~/.mysql_passwd
}

#Install Mysql
install_mysql()
{
    log_message "Executando função: install_mysql"

    if [ "${FLUX_DB_MODE}" != "local" ]; then
        install_db_client_only
        return 0
    fi

    cd /usr/src
    if [ "$DIST" = "DEBIAN" ]; then
        sudo apt install -y dirmngr --install-recommends
        sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys B7B3B788A8D3785C
        wget https://repo.mysql.com/mysql-apt-config_0.8.13-1_all.deb
        dpkg -i mysql-apt-config_0.8.13-1_all.deb
        apt update
        apt -y install unixodbc unixodbc-bin
        debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password ${MYSQL_ROOT_PASSWORD}"
        debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password ${MYSQL_ROOT_PASSWORD}"
        debconf-set-selections <<< "mysql-community-server mysql-server/default-auth-override select Use Legacy Authentication Method (Retain MySQL 5.x Compatibility)"
        DEBIAN_FRONTEND=noninteractive apt install -y mysql-server
        cd /opt/flux/misc/
        tar -xzvf odbc.tar.gz
        cp -rf odbc_conf/libmyodbc8* /usr/lib/x86_64-linux-gnu/odbc/.

    elif [ "$DIST" = "CENTOS" ]; then
        wget https://repo.mysql.com/mysql80-community-release-el7-1.noarch.rpm
        rpm --import https://repo.mysql.com/RPM-GPG-KEY-mysql-2022
        yum localinstall -y mysql80-community-release-el7-1.noarch.rpm
        yum install -y mysql-community-server unixODBC mysql-connector-odbc
        systemctl start mysqld
        MYSQL_ROOT_TEMP=$(grep 'temporary password' /var/log/mysqld.log | cut -c 14- | cut -c100-111 2>&1)
        mysql -uroot -p${MYSQL_ROOT_TEMP} --connect-expired-password -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';FLUSH PRIVILEGES;"
    elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
        apt install gnupg -y
        sudo apt install -y dirmngr --install-recommends
        apt-get install software-properties-common -y
        sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys B7B3B788A8D3785C
        wget https://dev.mysql.com/get/mysql-apt-config_0.8.24-1_all.deb
        sudo dpkg -i mysql-apt-config_0.8.24-1_all.deb
        apt update -y
        apt-get -y install unixodbc unixodbc-dev
        debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password ${MYSQL_ROOT_PASSWORD}"
        debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password ${MYSQL_ROOT_PASSWORD}"
        debconf-set-selections <<< "mysql-community-server mysql-server/default-auth-override select Use Legacy Authentication Method (Retain MySQL 5.x Compatibility)"
        DEBIAN_FRONTEND=noninteractive apt install -y mysql-server
        cd ${FLUX_SOURCE_DIR}/misc
        tar -xzvf odbc.tar.gz
        mkdir -p /usr/lib/x86_64-linux-gnu/odbc/.
        cp -rf libmyodbc8* /usr/lib/x86_64-linux-gnu/odbc/.
    elif [[ $DIST = "DEBIAN12" ]]; then
        apt install gnupg -y
        sudo apt install -y dirmngr --install-recommends
        apt-get install software-properties-common -y
        sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys B7B3B788A8D3785C
        wget https://dev.mysql.com/get/mysql-apt-config_0.8.29-1_all.deb
        sudo dpkg -i mysql-apt-config_0.8.29-1_all.deb
        curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | gpg --dearmor > /usr/share/keyrings/mysql-apt-config.gpg
        apt update
        apt-get -y install unixodbc unixodbc-dev
        debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password ${MYSQL_ROOT_PASSWORD}"
        debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password ${MYSQL_ROOT_PASSWORD}"
        debconf-set-selections <<< "mysql-community-server mysql-server/default-auth-override select Use Legacy Authentication Method (Retain MySQL 5.x Compatibility)"
        DEBIAN_FRONTEND=noninteractive apt install -y mysql-server
        cd ${FLUX_SOURCE_DIR}/misc
        tar -xzvf odbc.tar.gz
        mkdir -p /usr/lib/x86_64-linux-gnu/odbc/.
        cp -rf libmyodbc8* /usr/lib/x86_64-linux-gnu/odbc/.

    fi
    echo ""
    echo "MySQL password set to '${MYSQL_ROOT_PASSWORD}'. Remember to delete ~/.mysql_passwd" >> ~/.mysql_passwd
    echo "" >> ~/.mysql_passwd
    echo "MySQL fluxuser password:  ${FLUXUSER_MYSQL_PASSWORD} " >> ~/.mysql_passwd
    chmod 400 ~/.mysql_passwd
}

#Normalize mysql installation
normalize_mysql()
{
    log_message "Executando função: normalize_mysql"

    if [ ${DIST} = "CENTOS" ]; then
        cp ${FLUX_SOURCE_DIR}/misc/odbc_conf/cent_odbc.ini /etc/odbc.ini
    else
        cp ${FLUX_SOURCE_DIR}/misc/odbc_conf/deb_odbc.ini /etc/odbc.ini
    fi

    if [ "${FLUX_DB_MODE}" != "local" ]; then
        log_message "FLUX_DB_MODE=${FLUX_DB_MODE}: ajuste de mysqld.cnf ignorado (backend remoto)."
        return 0
    fi

    if [ ${DIST} = "DEBIAN" ]; then
        mv /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.old
        cp ${FLUX_SOURCE_DIR}/config/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf
        systemctl restart mysql
        systemctl enable mysql
    elif [ ${DIST} = "CENTOS" ]; then
        systemctl start mysqld
        systemctl enable mysqld
        sed -i '26i wait_timeout=600' /etc/my.cnf
        sed -i '26i interactive_timeout = 600' /etc/my.cnf
        sed -i '26i sql-mode=""' /etc/my.cnf
        systemctl restart mysqld
        systemctl enable mysqld
    elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" || $DIST = "DEBIAN12" ]]; then
        mv /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.old
        cp ${FLUX_SOURCE_DIR}/config/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf
        systemctl restart mysql
        systemctl enable mysql
    fi
}

#Configure my.cnf alternative
configure_my_cnf()
{
    log_message "Executando função: configure_my_cnf"

    if [ "${FLUX_DB_MODE}" != "local" ]; then
        log_message "FLUX_DB_MODE=${FLUX_DB_MODE}: alternativa my.cnf ignorada (backend remoto)."
        return 0
    fi

    log_message "Configurando alternativa para my.cnf..."
    local target_config=${MYSQL_CNF}
    local alt_name="my.cnf"
    local link_path="/etc/mysql/my.cnf"
    local priority=200

    if [ -z "$target_config" ]; then
        log_message "Erro: Caminho do arquivo de configuração para my.cnf não fornecido."
        log_message "Uso: configure_my_cnf <caminho_do_arquivo_my.cnf>"
        return 1
    fi

    if [ ! -f "$target_config" ]; then
        log_message "Erro: O arquivo de configuração '$target_config' não existe."
        return 1
    fi

    log_message "Registrando '$target_config' como alternativa para '$link_path' no grupo '$alt_name' com prioridade $priority."
    update-alternatives --install "$link_path" "$alt_name" "$target_config" "$priority"

    log_message "Definindo '$target_config' como a alternativa padrão para '$alt_name'."
    update-alternatives --set "$alt_name" "$target_config"

    log_message "Verificando o status da alternativa my.cnf:"
    update-alternatives --display "$alt_name"
    systemctl restart mysql
    log_message "Configuração da alternativa my.cnf concluída."
}

#Install and configure HAProxy for MySQL/Galera
install_haproxy()
{
    log_message "Executando função: install_haproxy"

    if [ "${FLUX_DB_MODE}" = "local" ]; then
        log_message "FLUX_DB_MODE=local: HAProxy nao sera instalado."
        return 0
    fi

    local db_hosts="${FLUX_DB_HOSTS:-${FLUX_DB_NODES}}"
    if [ -z "${db_hosts}" ]; then
        log_message "Erro: FLUX_DB_HOSTS/FLUX_DB_NODES vazio. Informe os nos MySQL no formato ip:porta separados por virgula."
        exit 1
    fi

    if [ "${FLUX_DB_MODE}" = "haproxy_vip" ] && [ -z "${FLUX_DB_VIP}" ]; then
        log_message "Erro: FLUX_DB_MODE=haproxy_vip exige FLUX_DB_VIP."
        exit 1
    fi

    if [ "${DIST}" = "CENTOS" ]; then
        yum install -y haproxy socat
    else
        apt-get update
        apt-get install -y haproxy socat
    fi

    local bind_addr="127.0.0.1"
    if [ "${FLUX_DB_MODE}" = "haproxy_vip" ]; then
        bind_addr="${FLUX_DB_VIP}"
        echo "net.ipv4.ip_nonlocal_bind = 1" > /etc/sysctl.d/60-flux-haproxy.conf
        sysctl -p /etc/sysctl.d/60-flux-haproxy.conf
    fi

    local tmp_write=$(mktemp)
    local tmp_read=$(mktemp)
    local idx=0
    local node

    while IFS= read -r node; do
        node=$(echo "${node}" | tr -d '[:space:]')
        if [ -z "${node}" ]; then
            continue
        fi
        idx=$((idx + 1))
        if [ ${idx} -eq 1 ]; then
            printf '    server galera%s %s check port %s\n' "${idx}" "${node}" "${FLUX_DB_CHECK_PORT}" >> "${tmp_write}"
        else
            printf '    server galera%s %s check port %s backup\n' "${idx}" "${node}" "${FLUX_DB_CHECK_PORT}" >> "${tmp_write}"
        fi
        printf '    server galera%s %s check port %s\n' "${idx}" "${node}" "${FLUX_DB_CHECK_PORT}" >> "${tmp_read}"
    done <<< "$(echo "${db_hosts}" | tr ',' '\n')"

    if [ ${idx} -eq 0 ]; then
        log_message "Erro: nenhum no valido encontrado em FLUX_DB_HOSTS=${db_hosts}"
        exit 1
    fi

    if [ -f /etc/haproxy/haproxy.cfg ] && [ ! -f /etc/haproxy/haproxy.cfg.orig ]; then
        cp /etc/haproxy/haproxy.cfg /etc/haproxy/haproxy.cfg.orig
    fi

    cp ${FLUX_SOURCE_DIR}/config/haproxy/flux-mysql.cfg /etc/haproxy/haproxy.cfg
    sed -i "s#__FLUX_DB_WRITE_BIND__#${bind_addr}:${FLUX_DB_WRITE_PORT}#g" /etc/haproxy/haproxy.cfg
    sed -i "s#__FLUX_DB_READ_BIND__#${bind_addr}:${FLUX_DB_READ_PORT}#g" /etc/haproxy/haproxy.cfg
    sed -i "s#__FLUX_DB_STATS_BIND__#127.0.0.1:${FLUX_DB_STATS_PORT}#g" /etc/haproxy/haproxy.cfg
    sed -i -e "/__FLUX_DB_WRITE_SERVERS__/r ${tmp_write}" -e "/__FLUX_DB_WRITE_SERVERS__/d" /etc/haproxy/haproxy.cfg
    sed -i -e "/__FLUX_DB_READ_SERVERS__/r ${tmp_read}" -e "/__FLUX_DB_READ_SERVERS__/d" /etc/haproxy/haproxy.cfg
    rm -f "${tmp_write}" "${tmp_read}"

    if ! haproxy -c -f /etc/haproxy/haproxy.cfg; then
        log_message "Erro: configuracao do HAProxy invalida. Verifique /etc/haproxy/haproxy.cfg"
        exit 1
    fi

    systemctl enable haproxy
    systemctl restart haproxy
    log_message "HAProxy configurado: escrita ${bind_addr}:${FLUX_DB_WRITE_PORT}, leitura ${bind_addr}:${FLUX_DB_READ_PORT}"
}

#Point FLUX and ODBC to the configured database endpoints
configure_db_endpoints()
{
    log_message "Executando função: configure_db_endpoints"

    local db_host="127.0.0.1"
    local write_port="3306"
    local read_port="3306"

    if [ "${FLUX_DB_MODE}" != "local" ]; then
        write_port="${FLUX_DB_WRITE_PORT}"
        read_port="${FLUX_DB_READ_PORT}"
        if [ "${FLUX_DB_MODE}" = "haproxy_vip" ]; then
            db_host="${FLUX_DB_VIP}"
        fi
    fi

    sed -i "s#^dbhost = .*#dbhost = ${db_host}#" ${FLUXDIR}flux-config.conf

    awk -v host="${db_host}" -v wport="${write_port}" -v rport="${read_port}" '
        /^\[/ { section=$0 }
        /^SERVER *=/ { print "SERVER = " host; next }
        /^PORT *=/ {
            if (section == "[FLUX_RO]") { print "PORT = " rport } else { print "PORT = " wport }
            next
        }
        { print }
    ' /etc/odbc.ini > /etc/odbc.ini.flux && mv /etc/odbc.ini.flux /etc/odbc.ini

    if [ "${FLUX_DB_MODE}" != "local" ]; then
        sed -i 's#^DB_SYNC_WAIT=.*#DB_SYNC_WAIT="TRUE"#' ${FLUXDIR}flux.lua
    fi

    log_message "Endpoints de banco: PHP=${db_host}:${write_port}, ODBC escrita=${write_port}, ODBC leitura=${read_port}"
}

#User Response Gathering
get_user_response()
{
    log_message "Executando função: get_user_response"
    echo ""
    read -p "Enter FQDN example (i.e ${FLUX_HOST_DOMAIN_NAME}): "
    FLUX_HOST_DOMAIN_NAME=${REPLY}
    echo "Your entered FQDN is : ${FLUX_HOST_DOMAIN_NAME} "
    echo ""
    read -p "Enter your email address: ${EMAIL}"
    EMAIL=${REPLY}
    echo ""
    read -n 1 -p "Press any key to continue ... "
    NAT1=$(dig +short myip.opendns.com @resolver1.opendns.com)
    NAT2=$(curl http://ip-api.com/json/)
    INTF=$(ifconfig $1 | sed -n 2p | awk '{ print $2 }' | awk -F : '{ print $2 }')
    if [ "${NAT1}" != "${INTF}" ]; then
        echo "Server is behind NAT"
        NAT="True"
    else
        NAT="False"
    fi
}

#Install FLUX with dependencies
install_flux()
{
    log_message "Executando função: install_flux"
    if [[ ${DIST} = "DEBIAN" || ${DIST} = "DEBIAN10" || ${DIST} = "DEBIAN11" || ${DIST} = "DEBIAN12" ]]; then
        echo "Installing dependencies for FLUX"
        apt update
        apt install -y nginx ntpdate chrony lua5.1 bc libxml2 libxml2-dev openssl libcurl4-openssl-dev gettext gcc g++
        echo "Installing dependencies for FLUX"
    elif [ ${DIST} = "CENTOS" ]; then
        echo "Installing dependencies for FLUX"
        yum install -y nginx libxml2 libxml2-devel openssl openssl-devel gettext-devel fileutils gcc-c++
    fi
    echo "Creating neccessary locations and configuration files ..."
    mkdir -p ${FLUXDIR}
    mkdir -p ${FLUXLOGDIR}
    mkdir -p ${FLUXEXECDIR}
    mkdir -p /var/www/
    mkdir -p ${WWWDIR}
    cp -rf ${FLUX_SOURCE_DIR}/config/flux-config.conf ${FLUXDIR}flux-config.conf
    cp -rf ${FLUX_SOURCE_DIR}/config/flux.lua ${FLUXDIR}flux.lua
    ln -s ${FLUX_SOURCE_DIR}/web_interface/flux ${WWWDIR}
    ln -s ${FLUX_SOURCE_DIR}/freeswitch/fs ${WWWDIR}
    mv /etc/chrony/chrony.conf /etc/chrony/chrony.old
    cp ${FLUX_SOURCE_DIR}/config/chrony.conf /etc/chrony/chrony.conf
    systemctl restart chrony
}

#Install iptables
install_iptables()
{
    log_message "Executando função: install_iptables"
    if [[ ${DIST} = "DEBIAN" || ${DIST} = "DEBIAN10" || ${DIST} = "DEBIAN11" || ${DIST} = "DEBIAN12" ]]; then
        echo "Installing iptables"
        sudo apt-get install -y iptables
        update-alternatives --set iptables /usr/sbin/iptables-legacy
        update-alternatives --set ip6tables /usr/sbin/ip6tables-legacy
    elif [ ${DIST} = "CENTOS" ]; then
        echo "Installing iptables"
        yum install -y iptables
    fi

}

#Normalize flux installation
normalize_flux()
{
    log_message "Executando função: normalize_flux"
    sudo apt-get install -y locales-all
    if [[ ${NAT} = "True" ]]; then
    mkdir -p /etc/nginx/ssl
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/nginx/ssl/nginx.key -out /etc/nginx/ssl/nginx.crt
    fi
    if [ ${DIST} = "DEBIAN" ]; then
        /bin/cp /usr/src/ioncube/ioncube_loader_lin_7.3.so /usr/lib/php/20180731/
        sed -i '2i zend_extension ="/usr/lib/php/20180731/ioncube_loader_lin_7.3.so"' /etc/php/7.3/fpm/php.ini
        sed -i '2i zend_extension ="/usr/lib/php/20180731/ioncube_loader_lin_7.3.so"' /etc/php/7.3/cli/php.ini
        cp -rf ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_flux.conf /etc/nginx/conf.d/flux.conf
        mv /etc/nginx/nginx.conf /etc/nginx/nginx.old
        cp ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_nginx.conf /etc/nginx/nginx.conf
        mv /etc/php/7.3/fpm/pool.d/www.conf /etc/php/7.3/fpm/pool.d/www.old
        cp ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_www.conf /etc/php/7.3/fpm/pool.d/www.conf
        systemctl start nginx
        systemctl enable nginx
        systemctl start php7.3-fpm
        systemctl enable php7.3-fpm
        chown -Rf root:root ${FLUXDIR}
        chown -Rf www-data:www-data ${FLUXLOGDIR}
        chown -Rf root:root ${FLUXEXECDIR}
        chown -Rf www-data:www-data ${WWWDIR}/flux
        chown -Rf www-data:www-data ${FLUX_SOURCE_DIR}/web_interface/flux
        chmod -Rf 755 ${WWWDIR}/flux
        sed -i "s/;request_terminate_timeout = 0/request_terminate_timeout = 300/" /etc/php/7.3/fpm/pool.d/www.conf
        sed -i "s#short_open_tag = Off#short_open_tag = On#g" /etc/php/7.3/fpm/php.ini
        sed -i "s#;cgi.fix_pathinfo=1#cgi.fix_pathinfo=1#g" /etc/php/7.3/fpm/php.ini
        sed -i "s/max_execution_time = 30/max_execution_time = 3000/" /etc/php/7.3/fpm/php.ini
        sed -i "s/upload_max_filesize = 2M/upload_max_filesize = 20M/" /etc/php/7.3/fpm/php.ini
        sed -i "s/post_max_size = 8M/post_max_size = 20M/" /etc/php/7.3/fpm/php.ini
        sed -i "s/memory_limit = 128M/memory_limit = 512M/" /etc/php/7.3/fpm/php.ini
        systemctl restart php7.3-fpm
        CRONPATH='/var/spool/cron/crontabs/flux'
    elif [ ${DIST} = "CENTOS" ]; then
        cp /usr/src/ioncube/ioncube_loader_lin_7.3.so /usr/lib64/php/modules/
        sed -i '2i zend_extension ="/usr/lib64/php/modules/ioncube_loader_lin_7.3.so"' /etc/php.ini
        cp ${FLUX_SOURCE_DIR}/web_interface/nginx/cent_flux.conf /etc/nginx/conf.d/flux.conf
        setenforce 0
        systemctl start nginx
        systemctl enable nginx
        systemctl start php-fpm
        systemctl enable php-fpm
        chown -Rf root:root ${FLUXDIR}
        chown -Rf apache.apache ${FLUXLOGDIR}
        chown -Rf root:root ${FLUXEXECDIR}
        chown -Rf apache.apache ${WWWDIR}/flux
        chown -Rf apache.apache ${FLUX_SOURCE_DIR}/web_interface/flux
        chmod -Rf 755 ${WWWDIR}/flux
        sed -i "s/;request_terminate_timeout = 0/request_terminate_timeout = 300/" /etc/php-fpm.d/www.conf
        sed -i "s#short_open_tag = Off#short_open_tag = On#g" /etc/php.ini
        sed -i "s#;cgi.fix_pathinfo=1#cgi.fix_pathinfo=1#g" /etc/php.ini
        sed -i "s/max_execution_time = 30/max_execution_time = 3000/" /etc/php.ini
        sed -i "s/upload_max_filesize = 2M/upload_max_filesize = 20M/" /etc/php.ini
        sed -i "s/post_max_size = 8M/post_max_size = 20M/" /etc/php.ini
        sed -i "s/memory_limit = 128M/memory_limit = 512M/" /etc/php.ini
        systemctl restart php-fpm
        CRONPATH='/var/spool/cron/flux'
    elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" || $DIST = "DEBIAN12" ]]; then
        sudo apt-get install -y locales-all python3-certbot-nginx python3-certbot
        /bin/cp /usr/src/ioncube/ioncube_loader_lin_7.3.so /usr/lib/php/20180731/
        echo "zend_extension = /usr/lib/php/20180731/ioncube_loader_lin_7.3.so" | tee /etc/php/7.3/fpm/conf.d/00-ioncube.ini
        echo "zend_extension = /usr/lib/php/20180731/ioncube_loader_lin_7.3.so" | tee /etc/php/7.3/cli/conf.d/00-ioncube.ini
        mv /etc/nginx/nginx.conf /etc/nginx/nginx.old
        cp ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_nginx.conf /etc/nginx/nginx.conf
        mv /etc/php/7.3/fpm/pool.d/www.conf /etc/php/7.3/fpm/pool.d/www.old
        cp ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_www.conf /etc/php/7.3/fpm/pool.d/www.conf
        systemctl restart nginx
        systemctl restart php7.3-fpm
        iptables -A INPUT -p tcp -m tcp --dport 80 -j ACCEPT
        iptables -A INPUT -p tcp -m tcp --dport 443 -j ACCEPT
        if [[ ${NAT} = "False" ]]; then
        certbot -m suporte@flux.net.br --nginx -d ${FLUX_HOST_DOMAIN_NAME} --agree-tos -n --no-redirect certonly -q
        cp -rf ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_flux.conf /etc/nginx/conf.d/flux.conf
        sed "s@ssl_certificate[ \t]*/etc/nginx/ssl/nginx.crt;@ssl_certificate /etc/letsencrypt/live/${FLUX_HOST_DOMAIN_NAME}/fullchain.pem;@g" -i /etc/nginx/conf.d/flux.conf
        sed "s@ssl_certificate_key[ \t]*/etc/nginx/ssl/nginx.key;@ssl_certificate_key /etc/letsencrypt/live/${FLUX_HOST_DOMAIN_NAME}/privkey.pem;@g" -i /etc/nginx/conf.d/flux.conf
        else
        cp -rf ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_flux.conf /etc/nginx/conf.d/flux.conf
        fi
        sed -i "s#server_name _#server_name ${FLUX_HOST_DOMAIN_NAME}#g" /etc/nginx/conf.d/flux.conf
        systemctl restart nginx
        systemctl enable nginx
        systemctl restart php7.3-fpm
        systemctl enable php7.3-fpm
        chown -Rf root:root ${FLUXDIR}
        chown -Rf www-data:www-data ${FLUXLOGDIR}
        chown -Rf root:root ${FLUXEXECDIR}
        chown -Rf www-data:www-data ${WWWDIR}/flux
        chown -Rf www-data:www-data ${FLUX_SOURCE_DIR}/web_interface/flux
        chmod -Rf 755 ${WWWDIR}/flux
        sed -i "s/;request_terminate_timeout = 0/request_terminate_timeout = 300/" /etc/php/7.3/fpm/pool.d/www.conf
        sed -i "s#short_open_tag = Off#short_open_tag = On#g" /etc/php/7.3/fpm/php.ini
        sed -i "s#;cgi.fix_pathinfo=1#cgi.fix_pathinfo=1#g" /etc/php/7.3/fpm/php.ini
        sed -i "s/max_execution_time = 30/max_execution_time = 3000/" /etc/php/7.3/fpm/php.ini
        sed -i "s/upload_max_filesize = 2M/upload_max_filesize = 20M/" /etc/php/7.3/fpm/php.ini
        sed -i "s/post_max_size = 8M/post_max_size = 20M/" /etc/php/7.3/fpm/php.ini
        sed -i "s/memory_limit = 128M/memory_limit = 512M/" /etc/php/7.3/fpm/php.ini
        systemctl restart php7.3-fpm
        CRONPATH='/var/spool/cron/crontabs/flux'
    fi
    echo "# To call all crons   
                * * * * * cd ${FLUX_SOURCE_DIR}/web_interface/flux/cron/ && php cron.php crons
                " > $CRONPATH
    chmod 600 $CRONPATH
    crontab $CRONPATH
    touch /var/log/flux/flux.log
    touch /var/log/flux/flux_email.log
    chmod -Rf 755 $FLUX_SOURCE_DIR
    sed -i "s#dbpass = <PASSSWORD>#dbpass = ${FLUXUSER_MYSQL_PASSWORD}#g" ${FLUXDIR}flux-config.conf
    sed -i "s#DB_PASSWD=\"<PASSSWORD>\"#DB_PASSWD = \"${FLUXUSER_MYSQL_PASSWORD}\"#g" ${FLUXDIR}flux.lua
    sed -i "s#base_url=https://localhost:443/#base_url=https://${FLUX_HOST_DOMAIN_NAME}/#g" ${FLUXDIR}/flux-config.conf
    sed -i "s#PASSWORD = <PASSWORD>#PASSWORD = ${FLUXUSER_MYSQL_PASSWORD}#g" /etc/odbc.ini
    systemctl restart nginx
}

#Install freeswitch with dependencies
install_freeswitch()
{
    log_message "Executando função: install_freeswitch"
    if [ ${DIST} = "DEBIAN" ]; then
        echo "Installing FREESWITCH"
        sleep 5
        apt-get install -y gnupg2
        echo "machine freeswitch.signalwire.com login signalwire password $FS_TOKEN" > /etc/apt/auth.conf
        echo "deb [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/freeswitch.list
        echo "deb-src [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ $(lsb_release -sc) main" >> /etc/apt/sources.list.d/freeswitch.list
        apt-get update -y
        sleep 1s
        apt-get install freeswitch-meta-all -y

    elif [ ${DIST} = "CENTOS" ]; then
        sleep 5
        echo "Installing FREESWITCH"
        yum install -y epel-release
        yum install -y freeswitch-config-vanilla freeswitch-lang-* freeswitch-sounds-* freeswitch-xml-curl freeswitch-event-json-cdr freeswitch-lua
        apt-get update && apt-get install -y freeswitch-meta-all
        echo "FREESWITCH installed successfully. . ."

    elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" || $DIST = "DEBIAN12" ]]; then
        echo "Installing FREESWITCH"
        sleep 6s
        apt-get update && apt-get install -y gnupg2 wget lsb-release
        echo "machine freeswitch.signalwire.com login signalwire password $FS_TOKEN" > /etc/apt/auth.conf
        echo "deb [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/freeswitch.list
        echo "deb-src [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ $(lsb_release -sc) main" >> /etc/apt/sources.list.d/freeswitch.list
        apt-get update -y
        sleep 2s
        apt-get install -y gdb
        apt-get install freeswitch-meta-all -y

    fi
    mv -f ${FS_DIR}/scripts /tmp/.
    ln -s ${FLUX_SOURCE_DIR}/freeswitch/fs ${WWWDIR}
    ln -s ${FLUX_SOURCE_DIR}/freeswitch/scripts ${FS_DIR}
    cp -rf ${FLUX_SOURCE_DIR}/freeswitch/sounds/*.wav ${FS_SOUNDSDIR}/
    cp -rf ${FLUX_SOURCE_DIR}/freeswitch/conf/autoload_configs/* /etc/freeswitch/autoload_configs/
    cp ${FLUX_SOURCE_DIR}/freeswitch/conf/vars.xml /etc/freeswitch/vars.xml
    
    if [ -n "${FLUX_HOST_DOMAIN_NAME:-}" ]; then
        sed -i "s#data=\"domain_name=\$\${domain}\"#data=\"domain_name=${FLUX_HOST_DOMAIN_NAME}\"#g" /etc/freeswitch/vars.xml
    fi
    
    sed -i "s#dbname:user:password#FLUX:fluxuser:${FLUXUSER_MYSQL_PASSWORD}#g" /etc/freeswitch/vars.xml
#   sed -i "s#{db_password}#${FLUXUSER_MYSQL_PASSWORD}#g" /etc/freeswitch/autoload_configs/switch.conf.xml
    mkdir -p /etc/freeswitch/templates
    cp -rf ${FLUX_SOURCE_DIR}/freeswitch/conf/templates/* /etc/freeswitch/templates/
    cp -rf ${FLUX_SOURCE_DIR}/freeswitch/init/json_cdr.service /etc/systemd/system/
    chmod 644 /etc/systemd/system/json_cdr.service
    systemctl daemon-reload
    systemctl enable json_cdr.service
    systemctl stop json_cdr.service
    mkdir -p /etc/sudoers.d
    cp ${FLUX_SOURCE_DIR}/config/sudoers.d/jsoncdr /etc/sudoers.d/
    chmod 440 /etc/sudoers.d/jsoncdr
    chown root:root /etc/sudoers.d/jsoncdr

}

#Normalize freeswitch installation
normalize_freeswitch()
{
    log_message "Executando função: normalize_freeswitch"
    sed -i "s#max-sessions\" value=\"1000#max-sessions\" value=\"2000#g" /etc/freeswitch/autoload_configs/switch.conf.xml
    sed -i "s#sessions-per-second\" value=\"30#sessions-per-second\" value=\"50#g" /etc/freeswitch/autoload_configs/switch.conf.xml
    sed -i "s#max-db-handles\" value=\"50#max-db-handles\" value=\"500#g" /etc/freeswitch/autoload_configs/switch.conf.xml
    sed -i "s#db-handle-timeout\" value=\"10#db-handle-timeout\" value=\"30#g" /etc/freeswitch/autoload_configs/switch.conf.xml
    rm -rf /etc/freeswitch/dialplan/*
    touch /etc/freeswitch/dialplan/flux.xml
    rm -rf /etc/freeswitch/directory/*
    touch /etc/freeswitch/directory/flux.xml
    rm -rf /etc/freeswitch/sip_profiles/*
    touch /etc/freeswitch/sip_profiles/flux.xml
    chmod -Rf 755 ${FS_SOUNDSDIR}
    if [ ${DIST} = "DEBIAN" ]; then
        cp -rf ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_fs.conf /etc/nginx/conf.d/fs.conf
        chown -Rf root:root ${WWWDIR}/fs
        chmod -Rf 755 ${WWWDIR}/fs
        /bin/systemctl restart freeswitch
        /bin/systemctl enable freeswitch
    elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" || $DIST = "DEBIAN12" ]]; then
        cp -rf ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_fs.conf /etc/nginx/conf.d/fs.conf
        chown -Rf root:root ${WWWDIR}/fs
        chmod -Rf 755 ${WWWDIR}/fs
        /bin/systemctl stop freeswitch
        cp ${FLUX_SOURCE_DIR}/freeswitch/init/freeswitch.debian.service /etc/systemd/system/freeswitch.service
        chmod 644 /etc/systemd/system/freeswitch.service
        /bin/systemctl daemon-reload
        /bin/systemctl restart freeswitch
        /bin/systemctl enable freeswitch
    elif [ ${DIST} = "CENTOS" ]; then
        cp ${FLUX_SOURCE_DIR}/web_interface/nginx/cent_fs.conf /etc/nginx/conf.d/fs.conf
        chown -Rf root:root ${WWWDIR}/fs
        chmod -Rf 755 ${WWWDIR}/fs
        sed -i "s/SELINUX=enforcing/SELINUX=disabled/" /etc/sysconfig/selinux
        sed -i "s/SELINUX=enforcing/SELINUX=disabled/" /etc/selinux/config
        /usr/bin/systemctl restart freeswitch
        /usr/bin/systemctl enable freeswitch
    fi
}

#Install Database for FLUX
install_database()
{
    log_message "Executando função: install_database"
    resolve_db_admin_endpoint
    log_message "Carregando schema em ${MYSQL_ADMIN_HOST}:${MYSQL_ADMIN_PORT}"
    mysqladmin_admin create ${FLUX_DATABASE_NAME}
    mysql_admin -e "CREATE USER IF NOT EXISTS 'fluxuser'@'%' IDENTIFIED BY '${FLUXUSER_MYSQL_PASSWORD}';"
    mysql_admin -e "ALTER USER 'fluxuser'@'%' IDENTIFIED WITH mysql_native_password BY '${FLUXUSER_MYSQL_PASSWORD}';"
    mysql_admin -e "GRANT ALL PRIVILEGES ON \`${FLUX_DATABASE_NAME}\` . * TO 'fluxuser'@'%' WITH GRANT OPTION;FLUSH PRIVILEGES;"
    mysql_admin ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/database/flux-6.4.sql
    mysql_admin ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/database/flux-tables.sql
    mysql_admin ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/database/flux-6.4.1.sql
    mysql_admin ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/database/flux-views.sql
    mysql_admin ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/web_interface/flux/addons/plugins/ringgroup/database/ringgroup_1.0.0.sql
    mysql_admin ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/web_interface/flux/addons/plugins/language_portuguese/database/language_portuguese_2.0.0.sql
}

install_ptbr_language()
{
    log_message "Executando função: install_ptbr_language"
    cp ${FLUX_SOURCE_DIR}/web_interface/flux/addons/plugins/language_portuguese/web_interface/flux/system/language/Portuguese/* ${FLUX_SOURCE_DIR}/web_interface/flux/system/language/Portuguese/
    cd ${FLUX_SOURCE_DIR}/web_interface/flux/language/pt_BR/LC_MESSAGES
    chown www-data:www-data messages.po
    chown www-data:www-data messages.mo
    chmod -Rf 755 ${FLUX_SOURCE_DIR}/web_interface/flux/language/pt_BR/LC_MESSAGES
    chmod -Rf 777 ${FLUX_SOURCE_DIR}/web_interface/flux/language/pt_BR/LC_MESSAGES/messages.po
    chmod -Rf 777 ${FLUX_SOURCE_DIR}/web_interface/flux/language/pt_BR/LC_MESSAGES/messages.mo
    msgfmt messages.po -o messages.mo

    systemctl restart nginx.service
    systemctl restart php7.3-fpm.service
}

run_database_migrations()
{
    log_message "Executando função: run_database_migrations"

    resolve_db_admin_endpoint

    # Credenciais do banco de dados
    DB_USER="${MYSQL_ADMIN_USER}"
    DB_PASS="${MYSQL_ADMIN_PASSWORD}"
    DB_NAME="${FLUX_DATABASE_NAME}"
    DB_HOST="${MYSQL_ADMIN_HOST}"
    DB_PORT="${MYSQL_ADMIN_PORT}"

    # Diretório de migrações (relativo a FLUX_SOURCE_DIR)
    MIGRATIONS_DIR="${FLUX_SOURCE_DIR}/database/updates/"
    LOG_TABLE="sql_migration_history"

    # Verificar se a tabela de log de migrações existe, se não, criar
    mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP "$DB_NAME" -N -B -e "
    CREATE TABLE IF NOT EXISTS $LOG_TABLE (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sql_file_name VARCHAR(255),
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );"

    # Loop sobre arquivos de migração pendentes
    for FILE in $(ls "$MIGRATIONS_DIR"*.sql | sort -t '-' -k 4,4n -k 3,3n -k 2,2n); do
        # Pegar o nome do arquivo
        FILENAME=$(basename "$FILE")

        # Verificar se o arquivo já foi aplicado
        QUERY=$(printf "SELECT COUNT(*) FROM %s WHERE sql_file_name = '%s';" "$LOG_TABLE" "$FILENAME")
        APPLIED=$(mysql --user="$DB_USER" -p"$DB_PASS" --host="$DB_HOST" --port="$DB_PORT" --protocol=TCP "$DB_NAME" -N -B -e "$QUERY")

        # Se não foi aplicado, execute
        if [ "$APPLIED" -eq 0 ]; then
            log_message "Applying migration: $FILENAME"
            mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP "$DB_NAME" < "$FILE"

            # Registrar a migração no banco
            mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP "$DB_NAME" -e "
            INSERT INTO $LOG_TABLE (sql_file_name) VALUES ('$FILENAME');"
        else
            log_message "Migration $FILENAME already applied. Skipping."
        fi
    done
}

install_database_updates()
{
    run_database_migrations
}

#Configure logrotation for maintain log size
logrotate_install()
{
    log_message "Executando função: logrotate_install"
    if [ ${DIST} = "DEBIAN" ]; then
        sed -i -e 's/daily/size 30M/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/weekly/size 30M/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/rotate 7/rotate 5/g' /etc/logrotate.d/rsyslog
        cp -rf ${FLUX_SOURCE_DIR}/config/logrotate.d/* /etc/logrotate.d/
    elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" || $DIST = "DEBIAN12" ]]; then
        sed -i -e 's/daily/size 30M/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/weekly/size 30M/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/rotate 7/rotate 5/g' /etc/logrotate.d/rsyslog
        cp -rf ${FLUX_SOURCE_DIR}/config/logrotate.d/* /etc/logrotate.d/
    elif [ ${DIST} = "CENTOS" ]; then
        sed -i '7 i size 30M' /etc/logrotate.d/syslog
        sed -i '7 i rotate 5' /etc/logrotate.d/syslog
        sed -i '2 i size 30M' /etc/logrotate.d/php-fpm
        sed -i '2 i rotate 5' /etc/logrotate.d/php-fpm
        sed -i -e 's/daily/size 30M/g' /etc/logrotate.d/nginx
        sed -i -e 's/rotate 10/rotate 5/g' /etc/logrotate.d/nginx
    fi
    /usr/sbin/logrotate -f /etc/logrotate.conf
    log_message "instalação logrotate concluída com sucesso!"
}

#Install G729
install_mod_bcg729()
{
    log_message "Executando função: install_mod_bcg729"

    FREESWITCH_MOD_DIR="/usr/lib/freeswitch/mod"
    MODULES_CONF="/etc/freeswitch/autoload_configs/modules.conf.xml"

    # Backup
    BACKUP_DIR="/tmp/freeswitch_backup_$TIMESTAMP"
    mkdir -p "$BACKUP_DIR"

    log_message "Criando backups..."
    cp "$MODULES_CONF" "$BACKUP_DIR/"
    if [ -f "${FREESWITCH_MOD_DIR}/mod_bcg729.so" ]; then
        cp "${FREESWITCH_MOD_DIR}/mod_bcg729.so" "$BACKUP_DIR/"
    fi

    log_message "Instalando dependências..."
    apt update
    apt install -y git build-essential cmake automake autoconf libtool pkg-config wget unzip libssl-dev libncurses5-dev libfreeswitch-dev

    log_message "Baixando mod_bcg729..."
    cd /tmp
    if [ -d "mod_bcg729" ]; then rm -rf mod_bcg729; fi
    git clone https://github.com/xadhoom/mod_bcg729.git
    cd mod_bcg729

    log_message "Configurando Makefile..."
    sed -i "s|^FS_DIR=.*|FS_DIR=${FREESWITCH_MOD_DIR}|" Makefile

    log_message "Compilando mod_bcg729..."
    make -j$(nproc)

    log_message "Instalando mod_bcg729..."
    make install

    log_message "Atualizando modules.conf.xml de forma segura..."
    if grep -q '<load module="mod_g729"/>' "$MODULES_CONF"; then
        cp "$MODULES_CONF" "$BACKUP_DIR/modules.conf.xml.g729.bak"

        sed -i 's|<load module="mod_g729"/>|<!-- & -->|' "$MODULES_CONF"

        sed -i '/<load module="mod_g729"\/>/a\    <load module="mod_bcg729"/>' "$MODULES_CONF"
    fi

    log_message "Reiniciando FreeSWITCH..."
    if systemctl is-active --quiet freeswitch; then
        systemctl restart freeswitch
    else
        log_message "FreeSWITCH não está rodando via systemd. Reinicie manualmente."
    fi

    log_message "Instalação do mod_bcg729 concluída com sucesso! Backups em $BACKUP_DIR"
}

#Install fail2ban + geoip-bin (Event Guard / Protetor SIP)
install_fail2ban()
{
    log_message "Executando função: install_fail2ban"

    apt-get update -qq

    if dpkg -l fail2ban 2> /dev/null | grep -q '^ii'; then
        log_message "fail2ban já instalado: $(fail2ban-client --version 2>&1 | head -1)"
    else
        log_message "Instalando fail2ban via apt..."
        DEBIAN_FRONTEND=noninteractive apt-get install -y fail2ban
        log_message "fail2ban instalado."
    fi

    if dpkg -l geoip-bin 2> /dev/null | grep -q '^ii'; then
        log_message "geoip-bin já instalado."
    else
        log_message "Instalando geoip-bin via apt..."
        DEBIAN_FRONTEND=noninteractive apt-get install -y geoip-bin geoip-database
        log_message "geoip-bin instalado."
    fi

    if ! systemctl is-enabled fail2ban &> /dev/null; then
        systemctl enable fail2ban
        log_message "fail2ban habilitado no boot."
    fi

    if ! systemctl is-active --quiet fail2ban; then
        systemctl start fail2ban
        log_message "fail2ban iniciado."
    fi
}

#Configure fail2ban filters + jail (Event Guard / Protetor SIP)
configure_fail2ban()
{
    log_message "Executando função: configure_fail2ban"

    # Cria jail.local se não existir (evita sobrescrever customizações)
    if [ ! -f /etc/fail2ban/jail.local ]; then
        cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
        log_message "jail.local criado a partir de jail.conf."
    else
        log_message "jail.local já existe, mantendo."
    fi

    # Filtros
    for filter in sip-auth-fail sip-auth-ip; do
        cp "${EVENT_GUARD_FAIL2BAN_SRC}/${filter}.conf" "/etc/fail2ban/filter.d/${filter}.conf"
        log_message "filter.d/${filter}.conf instalado."
    done

    # Jail
    cp "${EVENT_GUARD_FAIL2BAN_SRC}/event_guard.conf" "/etc/fail2ban/jail.d/event_guard.conf"
    log_message "jail.d/event_guard.conf instalado."

    # Valida configuração antes de recarregar
    log_message "Validando configuração do fail2ban..."
    if ! fail2ban-client --test &> /dev/null; then
        log_message "ERRO: configuração do fail2ban inválida. Saída detalhada:"
        fail2ban-client --test
        return 1
    fi

    if systemctl is-active --quiet fail2ban; then
        systemctl reload fail2ban
    else
        systemctl start fail2ban
    fi
    sleep 2

    # Verifica jails
    for jail in sip-auth-fail sip-auth-ip; do
        if fail2ban-client status "$jail" &> /dev/null; then
            log_message "Jail '${jail}' ativo."
        else
            log_message "ERRO: jail '${jail}' não iniciou. Verifique /var/log/fail2ban.log"
            return 1
        fi
    done
}

#Configure sudoers (Event Guard / Protetor SIP)
configure_sudoers()
{
    log_message "Executando função: configure_sudoers"

    local src="${EVENT_GUARD_SUDOERS_SRC}/fluxsbc-event-guard"
    local dst="/etc/sudoers.d/fluxsbc-event-guard"

    # Validação prévia: roda contra o source (não exige cópia)
    if ! visudo -c -f "$src" &> /dev/null; then
        log_message "ERRO: sintaxe inválida no source do sudoers: $src"
        visudo -c -f "$src" || true
        return 1
    fi
    log_message "Sintaxe do sudoers (source) válida."

    mkdir -p /etc/sudoers.d
    cp "$src" "$dst"
    chmod 440 "$dst"
    chown root:root "$dst"

    if ! visudo -c -f "$dst" &> /dev/null; then
        log_message "ERRO: sintaxe inválida no sudoers instalado. Removendo arquivo."
        rm -f "$dst"
        return 1
    fi

    log_message "sudoers configurado e validado."
}

#Configure systemd service do daemon (Event Guard / Protetor SIP)
install_service()
{
    log_message "Executando função: install_service"

    local src="${EVENT_GUARD_FAIL2BAN_SRC}/event_guard.service"
    local dst="/etc/systemd/system/event_guard.service"

    # Ajusta ExecStart para o path real do daemon no repositório clonado
    sed "s|/var/www/html/fs/event_guard_daemon.php|${EVENT_GUARD_DAEMON}|g" \
        "$src" > "$dst"
    chmod 644 "$dst"

    systemctl daemon-reload
    systemctl enable event_guard
    # Habilitado no boot, mas NÃO iniciado nesta instalação.
    # Para iniciar agora:
    #   systemctl start event_guard
    log_message "Unit event_guard.service instalada e habilitada no boot (não iniciada)."
    log_message "Para iniciar agora: systemctl start event_guard"
}

configure_openssl_legacy()
{
    log_message "Executando função: configure_openssl_legacy"

    local cnf="/etc/ssl/openssl.cnf"
    local marker="# FLUX-OPENSSL-LEGACY-PROVIDER"
    local libssl_deb="libssl1.1_1.1.1w-0+deb11u1_amd64.deb"
    local libssl_url1="http://deb.debian.org/debian/pool/main/o/openssl/${libssl_deb}"
    local libssl_url2="https://archive.debian.org/debian/pool/main/o/openssl/${libssl_deb}"

    local ver
    ver=$(. /etc/os-release 2> /dev/null && echo "$VERSION_ID")
    if [ "$ver" != "12" ]; then
        log_message "Sistema não é Debian 12 (VERSION_ID=$ver). Pulando configure_openssl_legacy."
        return 0
    fi

    if dpkg -l libssl1.1 2> /dev/null | grep -q '^ii'; then
        log_message "libssl1.1 já instalada."
    else
        log_message "Baixando libssl1.1 (Debian 11)..."
        cd /usr/src
        if wget -q "$libssl_url1" -O "$libssl_deb" || wget -q "$libssl_url2" -O "$libssl_deb"; then
            dpkg -i "$libssl_deb" >> "$LOG_FILE" 2>&1
            rm -f "$libssl_deb"
            log_message "libssl1.1 instalada."
        else
            log_message "ERRO: falha ao baixar libssl1.1 (deb.debian.org e archive.debian.org)."
            return 1
        fi
    fi

    if grep -q "$marker" "$cnf" 2> /dev/null; then
        log_message "Legacy provider já configurado em $cnf."
    else
        log_message "Configurando legacy provider em $cnf..."

        cp "$cnf" "${cnf}.bak_${TIMESTAMP}"
        log_message "Backup criado: ${cnf}.bak_${TIMESTAMP}"

        sed -i 's/^openssl_conf = openssl_init/# openssl_conf = openssl_init (desativado pelo FLUX)/' "$cnf"
        sed -i 's/^\[openssl_init\]/[openssl_init_desativado_pelo_flux]/' "$cnf"

        local tmp
        tmp=$(mktemp)
        cat > "$tmp" << EOF
${marker}
openssl_conf = openssl_init

[openssl_init]
providers = provider_sect

[provider_sect]
default = default_sect
legacy = legacy_sect

[default_sect]
activate = 1

[legacy_sect]
activate = 1

EOF
        cat "$cnf" >> "$tmp"
        mv "$tmp" "$cnf"
        chmod 644 "$cnf"
        log_message "Bloco legacy provider adicionado ao topo de $cnf."
    fi

    if openssl list -providers 2> /dev/null | grep -qi 'legacy'; then
        log_message "Legacy provider ativo no OpenSSL."
    else
        log_message "ERRO: legacy provider não ativou. Verifique $cnf."
        return 1
    fi

    if systemctl list-units --type=service --all 2> /dev/null | grep -q 'php7.3-fpm'; then
        systemctl restart php7.3-fpm
        log_message "php7.3-fpm reiniciado."
    else
        log_message "Serviço php7.3-fpm não encontrado. Reinicie o PHP-FPM manualmente."
    fi
}

install_java()
{
    log_message "Executando função: install_java"

    local JAVA_VERSION
    JAVA_VERSION=$(java -version 2>&1 | awk -F '"' '/version/ {print $2}' | cut -d'.' -f1)

    if [[ "$JAVA_VERSION" == "11" ]]; then
        log_message "Java 11 já instalado e ativo. Pulando install_java."
        return 0
    fi

    if [[ "$DIST" == "DEBIAN12" ]]; then
        log_message "Debian 12 detectado. Instalando Java 11 via Eclipse Adoptium (Temurin)..."

        apt-get install -y wget gnupg

        local keyring="/etc/apt/keyrings/adoptium.gpg"
        local sources_list="/etc/apt/sources.list.d/adoptium.list"

        install -d -m 0755 /etc/apt/keyrings

        wget -qO- https://packages.adoptium.net/artifactory/api/gpg/key/public \
            | gpg --dearmor > "$keyring"

        if [ "${PIPESTATUS[0]}" -ne 0 ] || [ "${PIPESTATUS[1]}" -ne 0 ]; then
            log_message "AVISO: Falha ao baixar/processar chave GPG do Adoptium. Java não será instalado."
            rm -f "$keyring"
            return 0
        fi

        chmod 0644 "$keyring"

        echo "deb [signed-by=${keyring}] https://packages.adoptium.net/artifactory/deb bookworm main" \
            > "$sources_list"

        apt-get update -y || log_message "AVISO: apt-get update falhou, tentando continuar..."

        apt-get install -y temurin-11-jdk
        local install_status=$?

        if [ $install_status -ne 0 ]; then
            log_message "AVISO: Falha ao instalar temurin-11-jdk. Java não será instalado."
            return 0
        fi

        local java11_bin
        java11_bin=$(update-alternatives --list java 2>/dev/null | grep -E "temurin-11|java-11" | head -1)

        if [ -z "$java11_bin" ]; then
            log_message "AVISO: Java 11 instalado mas binário não encontrado em update-alternatives."
            return 0
        fi

        update-alternatives --set java "$java11_bin"
        log_message "Java 11 (Temurin) definido como padrão: $java11_bin"

    elif [[ "$DIST" == "DEBIAN10" || "$DIST" == "DEBIAN11" ]]; then
        log_message "Debian 10/11: Java 11 disponível nos repositórios padrão."
        apt-get install -y openjdk-11-jdk || {
            log_message "AVISO: Falha ao instalar openjdk-11-jdk. Java não será instalado."
            return 0
        }
    else
        log_message "Distribuição $DIST não suportada por install_java. Pulando."
        return 0
    fi

    log_message "Verificando versão Java ativa..."
    java -version 2>&1 | tee -a "$LOG_FILE"
    log_message "install_java concluída com sucesso."
    return 0
}

apply_php_fpm_systemd_override()
{
    log_message "Executando função: apply_php_fpm_systemd_override"

    if [ "$DIST" != "DEBIAN12" ]; then
        return 0
    fi

    local service="php7.3-fpm"

    if ! systemctl list-unit-files --no-legend "${service}.service" 2>/dev/null \
            | grep -q "^${service}.service"; then
        log_message "AVISO: [${service}] unit não encontrado. PHP 7.3 provavelmente não foi instalado. Pulando override."
        return 0
    fi

    local override_dir="/etc/systemd/system/${service}.service.d"
    local override_file="${override_dir}/flux-freeswitch-rw.conf"

    if ! mkdir -p "$override_dir"; then
        log_message "AVISO: [${service}] Falha ao criar $override_dir — override não aplicado."
        return 0
    fi

    if grep -qs 'ReadWritePaths=/etc/freeswitch' "$override_file" 2>/dev/null; then
        log_message "[${service}] ReadWritePaths=/etc/freeswitch já presente, ignorando."
        return 0
    fi

    cat > "$override_file" <<'EOF'
[Service]
ReadWritePaths=/etc/freeswitch
EOF

    chmod 0644 "$override_file"

    if ! systemctl daemon-reload; then
        log_message "AVISO: [${service}] daemon-reload falhou — override pode não ter sido carregado."
        return 0
    fi

    if ! systemctl restart "${service}.service"; then
        log_message "AVISO: [${service}] Falha ao reiniciar o serviço."
        return 0
    fi

    log_message "[${service}] Override aplicado e serviço reiniciado com sucesso."
}

#Remove all downloaded and temp files from server
clean_server()
{
    log_message "Executando função: clean_server"
    cd /usr/src
    rm -rf fail2ban* GNU-AGPL* flux_install.sh ioncube* mysql-apt* mysql80-community-release-el7-1.noarch.rpm
    echo "FS restarting...!"
    systemctl restart freeswitch
    echo "FS restarted...!"
}

#Installation Information Print
start_installation()
{
    log_message "Executando função: start_installation"
    get_linux_distribution
    verification
    install_prerequisties
    license_accept
    get_flux_source
    get_user_response
    install_mysql
    normalize_mysql
    configure_my_cnf
    install_haproxy
    configure_openssl_legacy
    install_php
    install_flux
    configure_db_endpoints
    install_iptables
    install_database
    install_freeswitch
    install_java
    normalize_freeswitch
    normalize_flux
    install_ptbr_language
    install_database_updates
    logrotate_install
    install_mod_bcg729
    install_fail2ban
    configure_fail2ban
    configure_sudoers
    install_service
    apply_php_fpm_systemd_override
    clean_server
    echo "******************************************************************************************"
    echo "******************************************************************************************"
    echo "******************************************************************************************"
    echo "**********                                                                      **********"
    echo "**********           Your FLUX is installed successfully                       **********"
    echo "                     Browse URL: https://${FLUX_HOST_DOMAIN_NAME}"
    echo "                     Username: admin"
    echo "                     Password: admin"
    echo ""
    if [ "${FLUX_DB_MODE}" = "local" ]; then
        echo "                     MySQL root user password:"
        echo "                     ${MYSQL_ROOT_PASSWORD}"
        echo ""
    else
        echo "                     Banco: ${FLUX_DB_MODE} via HAProxy"
        echo "                     Nos Galera: ${FLUX_DB_NODES}"
        echo "                     Stats HAProxy: http://127.0.0.1:${FLUX_DB_STATS_PORT}/"
        echo ""
    fi
    echo "                     MySQL fluxuser password:"
    echo "                     ${FLUXUSER_MYSQL_PASSWORD}"
    echo ""
    echo "**********           IMPORTANT NOTE: Please reboot your server once.            **********"
    echo "**********                                                                      **********"
    echo "******************************************************************************************"
    echo "******************************************************************************************"
    echo "******************************************************************************************"
}
start_installation
