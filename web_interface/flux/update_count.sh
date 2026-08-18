#!/bin/bash
FLUXCONF=/var/lib/flux/flux-config.conf

read_conf_value()
{
    grep -Po "^\s*$1\s*=\s*\K.*" "$FLUXCONF" | head -n1 | sed -e 's/[[:space:]]*$//'
}

DBHOST=$(read_conf_value dbhost)
DBPORT=$(read_conf_value dbport)
DBHOST=${DBHOST:-127.0.0.1}
DBPORT=${DBPORT:-3306}

MYSQL="/usr/bin/mysql -h $DBHOST -P $DBPORT --protocol=TCP -u fluxuser -p$3 flux"

if [ $4 -gt 0 ]
then
        $MYSQL -e "update routes set call_count =call_count+1 where id= $4 ";
        $MYSQL -e "update routing set call_count =call_count+1 where routes_id= $4 and trunk_id=$1";
else
        $MYSQL -e "update pricelists set call_count =call_count+1 where id= $2";
        $MYSQL -e "update routing set call_count =call_count+1 where pricelist_id= $2 and trunk_id=$1";
fi
