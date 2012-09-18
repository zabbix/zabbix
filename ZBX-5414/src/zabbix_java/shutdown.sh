#!/bin/bash

cd $(dirname $0)
source settings.sh

if [ -n "$PID_FILE" ]; then
	if [ -e "$PID_FILE" ]; then
		kill `cat $PID_FILE`
	else
		echo "Zabbix Java Gateway is not running"
		exit 1
	fi
else
	echo "Zabbix Java Gateway is not configured as a daemon: variable \$PID_FILE is not set"
	exit 1
fi
