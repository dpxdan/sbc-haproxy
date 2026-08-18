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
TEMP_USER_ANSWER="no"
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
FLUX_DB_USER="fluxuser"
MYSQL_CNF="/etc/mysql/mysql.cnf"

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
        for i in `seq 1 $length`
        do
                index=$(($RANDOM%$ArrayLength))
                char=${CharArray[$index]}
                password=${password}${char}
        done
        echo $password
}

MYSQL_ROOT_PASSWORD=`echo "$(genpasswd 20)" | sed s/./*/5`
FLUXUSER_MYSQL_PASSWORD=`echo "$(genpasswd 20)" | sed s/./*/5`

#Fetch OS Distribution
get_linux_distribution ()
{
        log_message "Executando função: get_linux_distribution"
        log_message "$Cyan ===get_linux_distribution===$Color_Off"
        sleep 2s
        V1=`cat /etc/*release | head -n1 | tail -n1 | cut -c 14- | cut -c1-18`
        V2=`cat /etc/*release | head -n7 | tail -n1 | cut -c 14- | cut -c1-14`
        V3=`cat /etc/*release | grep Deb | head -n1 | tail -n1 | cut -c 14- | cut -c1-19`
        V4=`cat /etc/*release | grep Deb | head -n1 | tail -n1 | cut -c 14- | cut -c1-19`
        if [[ $V1 = "Debian GNU/Linux 9" ]]; then
                DIST="DEBIAN"
                log_message "$Green ===Your OS is $V1===$Color_Off"
        else if [[ $V2 = "CentOS Linux 7" ]]; then
                DIST="CENTOS"
                log_message "$Green ===Your OS is $V2===$Color_Off"
        else if [[ $V3 = "Debian GNU/Linux 10" ]]; then
                DIST="DEBIAN10"
                log_message "$Green ===Your OS is $V3===$Color_Off"
        else if [[ $V4 = "Debian GNU/Linux 11" ]]; then
                DIST="DEBIAN11"
                log_message "$Green ===Your OS is $V4===$Color_Off"
        else
                DIST="OTHER"
                log_message 'Ooops!!! Versao Linux nao suportada.'
                exit 1
        fi
        fi
        fi
        fi
        #log_message "$Green ===Your OS is $DIST===$Color_Off"
        sleep 4s
}

#Verify freeswitch token
verification ()
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
        #if [ $DIST = "DEBIAN10" ]; then
        if [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
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
install_prerequisties ()
{
        log_message "Executando função: install_prerequisties"
        if [ $DIST = "CENTOS" ]; then
                systemctl stop httpd
                systemctl disable httpd
                yum update -y
                yum install -y wget curl git bind-utils ntpdate systemd net-tools whois sendmail sendmail-cf mlocate vim
        else if [ $DIST = "DEBIAN" ]; then
                systemctl stop apache2
                systemctl disable apache2
                apt update -y
                apt install -y sudo wget curl git dnsutils ntpdate systemd net-tools whois sendmail-bin sensible-mda mlocate vim
        #else if [ $DIST = "DEBIAN10" ]; then
        else if [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
                apt-get update -y
                apt-get install -y sudo wget curl git dnsutils python3-pip ntpdate systemd net-tools whois sendmail-bin sensible-mda mlocate vim imagemagick
        fi
        fi
        fi
        cd /usr/src/
        wget http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
        tar -xzvf ioncube_loaders_lin_x86-64.tar.gz
        cd ioncube
}

#Fetch FLUX Source
get_flux_source ()
{
        log_message "Executando função: get_flux_source"
        cd /opt
        git clone https://github.com/Flux-Tecnologia/sbc-fluxv6 flux
        
}

#License Acceptence
license_accept ()
{
        log_message "Executando função: license_accept"
        cd /usr/src
        if [ $IS_ENTERPRISE = "True" ]; then
                echo ""
        fi
        if [ $IS_ENTERPRISE = "False" ]; then
                #clear
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
install_php ()
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
        else if [ "$DIST" = "CENTOS" ]; then
                yum -y install http://rpms.remirepo.net/enterprise/remi-release-7.rpm 
                yum -y install epel-release yum-utils
                yum-config-manager --disable remi-php54
                yum-config-manager --enable remi-php73
                yum install -y php php-fpm php-mysql php-cli php-json php-readline php-xml php-curl php-gd php-json php-mbstring php-mysql php-opcache php-imap
                systemctl stop httpd
                systemctl disable httpd
        else if [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
#        else if [ "$DIST" = "DEBIAN10" ]; then
                apt -y install lsb-release apt-transport-https ca-certificates
                wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
                echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php7.3.list
                apt-get update
                apt install -y php7.3 php7.3-common php7.3-fpm php7.3-mysql php7.3-cli php7.3-json php7.3-readline php7.3-xml php7.3-curl php7.3-gd php7.3-json php7.3-mbstring php7.3-opcache php7.3-imap php7.3-geoip php-pear php7.3-imagick libreoffice ghostscript
                systemctl stop apache2
                systemctl disable apache2
        fi
        fi
        fi 
}

#Install Mysql
install_mysql ()
{
        log_message "Executando função: install_mysql"
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

        else if [ "$DIST" = "CENTOS" ]; then
                wget https://repo.mysql.com/mysql80-community-release-el7-1.noarch.rpm
                rpm --import https://repo.mysql.com/RPM-GPG-KEY-mysql-2022
                yum localinstall -y mysql80-community-release-el7-1.noarch.rpm
                yum install -y mysql-community-server unixODBC mysql-connector-odbc
                systemctl start mysqld
                MYSQL_ROOT_TEMP=$(grep 'temporary password' /var/log/mysqld.log | cut -c 14- | cut -c100-111 2>&1)
                mysql -uroot -p${MYSQL_ROOT_TEMP} --connect-expired-password -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';FLUSH PRIVILEGES;"
        #else if [ "$DIST" = "DEBIAN10" ]; then
        else if [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
                apt install gnupg -y
                sudo apt install -y dirmngr --install-recommends
                apt-get install software-properties-common -y
                sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys B7B3B788A8D3785C
                wget https://dev.mysql.com/get/mysql-apt-config_0.8.24-1_all.deb
                sudo dpkg -i mysql-apt-config_0.8.24-1_all.deb
                apt update -y
                #apt -y install unixodbc unixodbc-bin
                apt-get -y install unixodbc unixodbc-dev
                debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password ${MYSQL_ROOT_PASSWORD}"
                debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password ${MYSQL_ROOT_PASSWORD}"
                debconf-set-selections <<< "mysql-community-server mysql-server/default-auth-override select Use Legacy Authentication Method (Retain MySQL 5.x Compatibility)"
                DEBIAN_FRONTEND=noninteractive apt install -y mysql-server
                cd ${FLUX_SOURCE_DIR}/misc
                #cd /opt/flux/misc/
                tar -xzvf odbc.tar.gz
                mkdir -p /usr/lib/x86_64-linux-gnu/odbc/.
                cp -rf libmyodbc8* /usr/lib/x86_64-linux-gnu/odbc/.
        
        fi
        fi
        fi
        echo ""
        echo "MySQL password set to '${MYSQL_ROOT_PASSWORD}'. Remember to delete ~/.mysql_passwd" >> ~/.mysql_passwd
        echo "" >>  ~/.mysql_passwd
        echo "MySQL fluxuser password:  ${FLUXUSER_MYSQL_PASSWORD} " >>  ~/.mysql_passwd
        chmod 400 ~/.mysql_passwd       
}

#Normalize mysql installation
normalize_mysql ()
{
        log_message "Executando função: normalize_mysql"
        if [ ${DIST} = "DEBIAN" ]; then
                cp ${FLUX_SOURCE_DIR}/misc/odbc_conf/deb_odbc.ini /etc/odbc.ini
                mv /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.old
                cp ${FLUX_SOURCE_DIR}/config/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf                
                systemctl restart mysql
                systemctl enable mysql
        elif  [ ${DIST} = "CENTOS" ]; then
                systemctl start mysqld
                systemctl enable mysqld
                cp ${FLUX_SOURCE_DIR}/misc/odbc_conf/cent_odbc.ini /etc/odbc.ini
                sed -i '26i wait_timeout=600' /etc/my.cnf
                sed -i '26i interactive_timeout = 600' /etc/my.cnf
                sed -i '26i sql-mode=""' /etc/my.cnf
                systemctl restart mysqld
                systemctl enable mysqld
        elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
                cp ${FLUX_SOURCE_DIR}/misc/odbc_conf/deb_odbc.ini /etc/odbc.ini
                mv /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.old
                cp ${FLUX_SOURCE_DIR}/config/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf   
                systemctl restart mysql
                systemctl enable mysql
        fi
}

#Configure my.cnf alternative
configure_my_cnf ()
{
    log_message "Executando função: configure_my_cnf"
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

#User Response Gathering
get_user_response ()
{
        log_message "Executando função: get_user_response"
        echo ""
        read -p "Enter FQDN example (i.e ${FLUX_HOST_DOMAIN_NAME}): "
        FLUX_HOST_DOMAIN_NAME=${REPLY}
        echo "Your entered FQDN is : ${FLUX_HOST_DOMAIN_NAME} "
        echo ""
        # read -p "Enter your freeswitch token: ${FS_TOKEN}"
        # FS_TOKEN=${REPLY}
        # echo ""
        read -p "Enter your email address: ${EMAIL}"
        EMAIL=${REPLY}
        echo ""
        read -n 1 -p "Press any key to continue ... "
        NAT1=$(dig +short myip.opendns.com @resolver1.opendns.com)
        NAT2=$(curl http://ip-api.com/json/)
        INTF=$(ifconfig $1|sed -n 2p|awk '{ print $2 }'|awk -F : '{ print $2 }')
        if [ "${NAT1}" != "${INTF}" ]; then
                echo "Server is behind NAT";
                NAT="True"
        fi
}

#Install FLUX with dependencies
install_flux ()
{
        log_message "Executando função: install_flux"
        if [[ ${DIST} = "DEBIAN" || ${DIST} = "DEBIAN10" || ${DIST} = "DEBIAN11" ]]; then
                echo "Installing dependencies for FLUX"
                apt update
                apt install -y nginx ntpdate chrony lua5.1 bc libxml2 libxml2-dev openssl libcurl4-openssl-dev gettext gcc g++
                echo "Installing dependencies for FLUX"
        elif  [ ${DIST} = "CENTOS" ]; then
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
install_iptables ()
{
        log_message "Executando função: install_iptables"
        if [[ ${DIST} = "DEBIAN" || ${DIST} = "DEBIAN10" || ${DIST} = "DEBIAN11" ]]; then
        echo "Installing iptables"
        sudo apt-get install -y iptables
        update-alternatives --set iptables /usr/sbin/iptables-legacy
        update-alternatives --set ip6tables /usr/sbin/ip6tables-legacy
        elif  [ ${DIST} = "CENTOS" ]; then
                echo "Installing iptables"
                yum install -y iptables
        fi

}

#Normalize flux installation
normalize_flux ()
{
	log_message "Executando função: normalize_flux"
	sudo apt-get install -y locales-all
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
                chown -Rf root.root ${FLUXDIR}	
                chown -Rf www-data.www-data ${FLUXLOGDIR}
                chown -Rf root.root ${FLUXEXECDIR}
                chown -Rf www-data.www-data ${WWWDIR}/flux
                chown -Rf www-data.www-data ${FLUX_SOURCE_DIR}/web_interface/flux
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
        elif  [ ${DIST} = "CENTOS" ]; then
                cp /usr/src/ioncube/ioncube_loader_lin_7.3.so /usr/lib64/php/modules/
                sed -i '2i zend_extension ="/usr/lib64/php/modules/ioncube_loader_lin_7.3.so"' /etc/php.ini
                cp ${FLUX_SOURCE_DIR}/web_interface/nginx/cent_flux.conf /etc/nginx/conf.d/flux.conf
                setenforce 0
                systemctl start nginx
                systemctl enable nginx
                systemctl start php-fpm
                systemctl enable php-fpm
                chown -Rf root.root ${FLUXDIR}
                chown -Rf apache.apache ${FLUXLOGDIR}
                chown -Rf root.root ${FLUXEXECDIR}
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
        elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
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
                certbot -m suporte@flux.net.br --nginx -d ${FLUX_HOST_DOMAIN_NAME} --agree-tos -n --no-redirect certonly -q
                cp -rf ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_flux.conf /etc/nginx/conf.d/flux.conf
                sed "s@ssl_certificate[ \t]*/etc/nginx/ssl/nginx.crt;@ssl_certificate /etc/letsencrypt/live/${FLUX_HOST_DOMAIN_NAME}/fullchain.pem;@g" -i /etc/nginx/conf.d/flux.conf
                sed "s@ssl_certificate_key[ \t]*/etc/nginx/ssl/nginx.key;@ssl_certificate_key /etc/letsencrypt/live/${FLUX_HOST_DOMAIN_NAME}/privkey.pem;@g" -i /etc/nginx/conf.d/flux.conf
                sed -i "s#server_name _#server_name ${FLUX_HOST_DOMAIN_NAME}#g" /etc/nginx/conf.d/flux.conf                
                systemctl restart nginx
                systemctl enable nginx
                systemctl restart php7.3-fpm
                systemctl enable php7.3-fpm
                chown -Rf root.root ${FLUXDIR}
                chown -Rf www-data.www-data ${FLUXLOGDIR}
                chown -Rf root.root ${FLUXEXECDIR}
                chown -Rf www-data.www-data ${WWWDIR}/flux
                chown -Rf www-data.www-data ${FLUX_SOURCE_DIR}/web_interface/flux
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
	# chmod -Rf 777 /opt/flux/
	# chmod -Rf 777 /opt/flux/*
	# chmod -Rf 777 /opt/flux
        # chmod 777 /var/log/flux/flux.log
        # chmod 777 /var/log/flux/flux_email.log
        sed -i "s#dbpass = <PASSSWORD>#dbpass = ${FLUXUSER_MYSQL_PASSWORD}#g" ${FLUXDIR}flux-config.conf
        sed -i "s#DB_PASSWD=\"<PASSSWORD>\"#DB_PASSWD = \"${FLUXUSER_MYSQL_PASSWORD}\"#g" ${FLUXDIR}flux.lua
        sed -i "s#base_url=https://localhost:443/#base_url=https://${FLUX_HOST_DOMAIN_NAME}/#g" ${FLUXDIR}/flux-config.conf
        sed -i "s#PASSWORD = <PASSWORD>#PASSWORD = ${FLUXUSER_MYSQL_PASSWORD}#g" /etc/odbc.ini
        systemctl restart nginx
}

#Install freeswitch with dependencies
install_freeswitch ()
{
        log_message "Executando função: install_freeswitch"
        if [ ${DIST} = "DEBIAN" ]; then
                #clear
                echo "Installing FREESWITCH"
                sleep 5
                apt-get install -y gnupg2
                echo "machine freeswitch.signalwire.com login signalwire password $FS_TOKEN" > /etc/apt/auth.conf
                echo "deb [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ `lsb_release -sc` main" > /etc/apt/sources.list.d/freeswitch.list
                echo "deb-src [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ `lsb_release -sc` main" >> /etc/apt/sources.list.d/freeswitch.list
                apt-get update -y
                sleep 1s
                apt-get install freeswitch-meta-all -y
                
        elif  [ ${DIST} = "CENTOS" ]; then
                #clear
                sleep 5
                echo "Installing FREESWITCH"
                yum install -y epel-release
                yum install -y freeswitch-config-vanilla freeswitch-lang-* freeswitch-sounds-* freeswitch-xml-curl freeswitch-event-json-cdr freeswitch-lua
                apt-get update && apt-get install -y freeswitch-meta-all
                echo "FREESWITCH installed successfully. . ."

        elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
                echo "Installing FREESWITCH"
                sleep 6s
                apt-get update && apt-get install -y gnupg2 wget lsb-release
                echo "machine freeswitch.signalwire.com login signalwire password $FS_TOKEN" > /etc/apt/auth.conf
                echo "deb [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ `lsb_release -sc` main" > /etc/apt/sources.list.d/freeswitch.list
                echo "deb-src [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ `lsb_release -sc` main" >> /etc/apt/sources.list.d/freeswitch.list
                apt-get update -y
                sleep 2s
                apt-get install -y gdb ntp                
                apt-get install freeswitch-meta-all -y
                
        fi
        mv -f ${FS_DIR}/scripts /tmp/.
        ln -s ${FLUX_SOURCE_DIR}/freeswitch/fs ${WWWDIR}
        ln -s ${FLUX_SOURCE_DIR}/freeswitch/scripts ${FS_DIR}
        cp -rf ${FLUX_SOURCE_DIR}/freeswitch/sounds/*.wav ${FS_SOUNDSDIR}/
        cp -rf ${FLUX_SOURCE_DIR}/freeswitch/conf/autoload_configs/* /etc/freeswitch/autoload_configs/
        cp ${FLUX_SOURCE_DIR}/freeswitch/conf/vars.xml /etc/freeswitch/vars.xml
        sed -i "s#dbname:user:password#FLUX:fluxuser:${FLUXUSER_MYSQL_PASSWORD}#g" /etc/freeswitch/vars.xml
        sed -i "s#{db_password}#${FLUXUSER_MYSQL_PASSWORD}#g" /etc/freeswitch/autoload_configs/switch.conf.xml        
}

#Normalize freeswitch installation
normalize_freeswitch ()
{
        log_message "Executando função: normalize_freeswitch"
        systemctl start freeswitch
        systemctl enable freeswitch
        sed -i "s#max-sessions\" value=\"1000#max-sessions\" value=\"2000#g" /etc/freeswitch/autoload_configs/switch.conf.xml
        sed -i "s#sessions-per-second\" value=\"30#sessions-per-second\" value=\"50#g" /etc/freeswitch/autoload_configs/switch.conf.xml
        sed -i "s#max-db-handles\" value=\"50#max-db-handles\" value=\"500#g" /etc/freeswitch/autoload_configs/switch.conf.xml
        sed -i "s#db-handle-timeout\" value=\"10#db-handle-timeout\" value=\"30#g" /etc/freeswitch/autoload_configs/switch.conf.xml
        rm -rf  /etc/freeswitch/dialplan/*
        touch /etc/freeswitch/dialplan/flux.xml
        rm -rf  /etc/freeswitch/directory/*
        touch /etc/freeswitch/directory/flux.xml
        rm -rf  /etc/freeswitch/sip_profiles/*
        touch /etc/freeswitch/sip_profiles/flux.xml
        chmod -Rf 755 ${FS_SOUNDSDIR}
        if [ ${DIST} = "DEBIAN" ]; then
                cp -rf ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_fs.conf /etc/nginx/conf.d/fs.conf
                chown -Rf root.root ${WWWDIR}/fs
                chmod -Rf 755 ${WWWDIR}/fs
                /bin/systemctl restart freeswitch
                /bin/systemctl enable freeswitch
        elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
                cp -rf ${FLUX_SOURCE_DIR}/web_interface/nginx/deb_fs.conf /etc/nginx/conf.d/fs.conf
                chown -Rf root.root ${WWWDIR}/fs
                chmod -Rf 755 ${WWWDIR}/fs
                /bin/systemctl restart freeswitch
                /bin/systemctl enable freeswitch
        elif  [ ${DIST} = "CENTOS" ]; then
                cp ${FLUX_SOURCE_DIR}/web_interface/nginx/cent_fs.conf /etc/nginx/conf.d/fs.conf
                chown -Rf root.root ${WWWDIR}/fs
                chmod -Rf 755 ${WWWDIR}/fs
                sed -i "s/SELINUX=enforcing/SELINUX=disabled/" /etc/sysconfig/selinux
                sed -i "s/SELINUX=enforcing/SELINUX=disabled/" /etc/selinux/config
                /usr/bin/systemctl restart freeswitch
                /usr/bin/systemctl enable freeswitch
        fi
}

#Install Database for FLUX
install_database ()
{
        log_message "Executando função: install_database"
        mysqladmin -u root -p${MYSQL_ROOT_PASSWORD} create ${FLUX_DATABASE_NAME}
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} -e "CREATE USER 'fluxuser'@'%' IDENTIFIED BY '${FLUXUSER_MYSQL_PASSWORD}';"
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} -e "ALTER USER 'fluxuser'@'%' IDENTIFIED WITH mysql_native_password BY '${FLUXUSER_MYSQL_PASSWORD}';"
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL PRIVILEGES ON \`${FLUX_DATABASE_NAME}\` . * TO 'fluxuser'@'%' WITH GRANT OPTION;FLUSH PRIVILEGES;"
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/database/flux-6.4.sql
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/database/flux-tables.sql
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/database/flux-6.4.1.sql
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/database/flux-views.sql
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/web_interface/flux/addons/plugins/ringgroup/database/ringgroup_1.0.0.sql
        mysql -uroot -p${MYSQL_ROOT_PASSWORD} ${FLUX_DATABASE_NAME} -f < ${FLUX_SOURCE_DIR}/web_interface/flux/addons/plugins/language_portuguese/database/language_portuguese_2.0.0.sql
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
                                
    # Credenciais do banco de dados
    DB_USER="root"
    DB_PASS="${MYSQL_ROOT_PASSWORD}"
    DB_NAME="${FLUX_DATABASE_NAME}"
    DB_HOST="localhost"

    # Diretório de migrações (relativo a FLUX_SOURCE_DIR)
    MIGRATIONS_DIR="${FLUX_SOURCE_DIR}/database/updates/"
    LOG_TABLE="sql_migration_history"

    # Verificar se a tabela de log de migrações existe, se não, criar
    mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -N -B -e "
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
        APPLIED=$(mysql --user="$DB_USER" -p"$DB_PASS" --host="$DB_HOST" "$DB_NAME" -N -B -e "$QUERY")
        
        # Se não foi aplicado, execute
        if [ "$APPLIED" -eq 0 ]; then
            log_message "Applying migration: $FILENAME"
            mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" < "$FILE"
            
            # Registrar a migração no banco
            mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
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
logrotate_install ()
{
log_message "Executando função: logrotate_install"
if [ ${DIST} = "DEBIAN" ]; then
        sed -i -e 's/daily/size 30M/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/weekly/size 30M/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/rotate 7/rotate 5/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/weekly/size 30M/g' /etc/logrotate.d/php7.3-fpm
        sed -i -e 's/rotate 12/rotate 5/g' /etc/logrotate.d/php7.3-fpm
        sed -i -e 's/weekly/size 30M/g' /etc/logrotate.d/nginx
        sed -i -e 's/rotate 52/rotate 5/g' /etc/logrotate.d/nginx
#elif [ ${DIST} = "DEBIAN10" ]; then
elif [[ $DIST = "DEBIAN10" || $DIST = "DEBIAN11" ]]; then
        sed -i -e 's/daily/size 30M/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/weekly/size 30M/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/rotate 7/rotate 5/g' /etc/logrotate.d/rsyslog
        sed -i -e 's/weekly/size 30M/g' /etc/logrotate.d/php7.3-fpm
        sed -i -e 's/rotate 12/rotate 5/g' /etc/logrotate.d/php7.3-fpm
        sed -i -e 's/weekly/size 30M/g' /etc/logrotate.d/nginx
        sed -i -e 's/rotate 52/rotate 5/g' /etc/logrotate.d/nginx
elif [ ${DIST} = "CENTOS" ]; then
        sed -i '7 i size 30M' /etc/logrotate.d/syslog
        sed -i '7 i rotate 5' /etc/logrotate.d/syslog
        sed -i '2 i size 30M' /etc/logrotate.d/php-fpm
        sed -i '2 i rotate 5' /etc/logrotate.d/php-fpm
        sed -i -e 's/daily/size 30M/g' /etc/logrotate.d/nginx
        sed -i -e 's/rotate 10/rotate 5/g' /etc/logrotate.d/nginx
fi
}

#Install G729
install_mod_bcg729 ()
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

#Remove all downloaded and temp files from server
clean_server ()
{
        log_message "Executando função: clean_server"
        cd /usr/src
        rm -rf fail2ban* GNU-AGPL* flux_install.sh ioncube* mysql-apt* mysql80-community-release-el7-1.noarch.rpm
        echo "FS restarting...!"
        systemctl restart freeswitch
        echo "FS restarted...!"
}

#Installation Information Print
start_installation ()
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
        install_freeswitch        
        install_php
        install_flux
        install_iptables
        install_database
        normalize_freeswitch
        normalize_flux
        install_ptbr_language
        install_database_updates
        logrotate_install
        install_mod_bcg729
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
        echo "                     MySQL root user password:"
        echo "                     ${MYSQL_ROOT_PASSWORD}"                                       
        echo ""
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
