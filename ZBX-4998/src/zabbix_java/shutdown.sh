#!/bin/sh

cd `dirname $0`
. ./settings.sh

if [ -n "$PID_FILE" ]; then
	if [ -f "$PID_FILE" ]; then
		PID=`cat "$PID_FILE"`
		if ps -p "$PID" > /dev/null 2>&1; then
			kill `cat $PID_FILE`
			for i in 1 2 3 4 5; do
				sleep 1
				ps -p "$PID" > /dev/null 2>&1
				if [ $? -ne 0 ]; then
					exit 0
				fi
			done
			echo "Zabbix Java Gateway did not stop"
			exit 1
		fi
		rm -f "$PID_FILE"
	fi
	echo "Zabbix Java Gateway is not running"
	exit 1
else
	echo "Zabbix Java Gateway is not configured as a daemon: variable \$PID_FILE is not set"
	exit 1
fi
