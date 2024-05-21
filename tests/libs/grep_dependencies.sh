#!/bin/bash

# This function prints public zabbix libraries dependencies. (except zbxcommon)
#
# Run it like:
#    ./grep_dependencies.sh zbxeval
#    

NOW=$(date "+%Y.%m.%d-%H.%M.%S")
TEMP_RES_FILE=/tmp/zabbix_cmocka_includes_$NOW

grep -rI "#.*include.*zbx" "../../include/$1.h" > $TEMP_RES_FILE

grep -rI "#.*include.*zbx" --include="*."{c,h} ../../src/libs/$1 | cut -d "#" -f2 >> $TEMP_RES_FILE

cat $TEMP_RES_FILE | cut -d " " -f2 | tr -d '"' |  sed "s/.\{2\}$//" | grep -v $1 \
| sed   '/.*version/d' \
| sed   '/.*constants/d' \
| sed   '/.*zbxsysinc/d' \
| sed   '/.*zbxcommon/d' \
| sed   '/.*zbxtypes/d' \
| sort | uniq

rm $TEMP_RES_FILE
