#!/bin/bash
set -euo pipefail

# process_hostname function applies the host-specific script to hostname.
process_hostname() {
   sql_file=$1
   host_name=$2
   host_port=$3

   echo "-- Starting db update with sql file ${sql_file} to ${host_name}:${host_port}..."

   envsubst < "${sql_file}" > subst.sql
   STATUS=$?
   if [ $STATUS -ne 0 ]; then
        echo "-- DB update failed: Failed to process ${sql_file} with envsubst" >$2
        return $STATUS
   fi

   mysql -h ${host_name} -P ${host_port} -u root < subst.sql
   STATUS=$?
   if [ $STATUS -ne 0 ]; then
       echo "-- DB update failed: $STATUS" >&2
       return $STATUS
   else
       echo "-- DB update succeeded!"
   fi
}

# MySQL variable to avoid specifying a password in the command line.
export MYSQL_PWD=$MYSQL_ROOT_PASSWORD

if [[ ! -v MYSQL_HOSTNAMES || -z "${MYSQL_HOSTNAMES}" || ! -v MYSQL_PORTS || -z "${MYSQL_PORTS}" || \
  ! -v MYSQL_SQL_FILES || -z "${MYSQL_SQL_FILES}" ]]; then
    echo " MYSQL_HOSTNAMES, MYSQL_PORTS and MYSQL_SQL_FILES must be set!" >&2
    exit 1
fi

hosts=($MYSQL_HOSTNAMES)
sqls=($MYSQL_SQL_FILES)
ports=($MYSQL_PORTS)

if ! (( ${#hosts[@]} == ${#ports[@]} && ${#ports[@]} == ${#sqls[@]} )); then
    echo "Error: MYSQL_HOSTNAMES, MYSQL_PORTS and MYSQL_SQL_FILES must have the same number of elements!" >&2
    exit 1
fi

# Iterating through hosts to execute script files.
for i in "${!hosts[@]}"; do
   sql="${sqls[i]}"
   host="${hosts[i]}"
   port="${ports[i]}"

   process_hostname ${sql} ${host} ${port}
done
