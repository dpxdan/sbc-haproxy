#!/bin/bash
DATE=`date +"%m-%d-%y %T"`
RECDIR=/var/lib/freeswitch/recordings/
FLUXCONF=/var/lib/flux/flux-config.conf

read_conf_value()
{
    grep -Po "^\s*$1\s*=\s*\K.*" "$FLUXCONF" | head -n1 | sed -e 's/[[:space:]]*$//'
}

DBHOST=$(read_conf_value dbhost)
DBUSER=$(read_conf_value dbuser)
DBPASS=$(read_conf_value dbpass)
DBNAME=$(read_conf_value dbname)
DBPORT=$(read_conf_value dbport)

DBHOST=${DBHOST:-127.0.0.1}
DBPORT=${DBPORT:-3306}

MYSQL_CNF=$(mktemp)
chmod 600 "$MYSQL_CNF"
echo "[client]
user = $DBUSER
password = '$DBPASS'
host = $DBHOST
port = $DBPORT
protocol = TCP
" > "$MYSQL_CNF"
DAYS=$(echo 'select value from `system` where name="purge_recordings";' | mysql --defaults-extra-file="$MYSQL_CNF" $DBNAME | tail -n1)
if [ $DAYS -gt "0" ]; then
        NOFILES=$(/usr/bin/find $RECDIR -mindepth 0 -mtime +$DAYS | wc -l;)
        /usr/bin/find $RECDIR -mindepth 0 -mtime +$DAYS -exec rm -rf {} \;
fi
echo "[$DATE] ****** Total $NOFILES call recording files deleted ****** " >> /var/log/flux/purge_recordings.log
rm -f "$MYSQL_CNF"
