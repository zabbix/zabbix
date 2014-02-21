#!/bin/bash

cd $(dirname $0)
source settings.sh

if [ -n "$PID_FILE" ]; then
	if [ -e "$PID_FILE" ]; then
		PID=`cat "$PID_FILE"`
		if ps -p "$PID" > /dev/null 2>&1; then
			kill `cat $PID_FILE`
			exit 0
		fi
		rm -f "$PID_FILE"
	fi
	echo "Zabbix Java Gateway is not running"
	exit 1
else
	echo "Zabbix Java Gateway is not configured as a daemon: variable \$PID_FILE is not set"
	exit 1
fi
