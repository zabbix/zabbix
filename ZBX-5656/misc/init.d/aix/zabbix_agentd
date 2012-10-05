#!/bin/sh

##########################################################
###### Zabbix agent daemon init script
##########################################################

case $1 in

start)
	/usr/local/sbin/zabbix_agentd -c /usr/local/etc/zabbix_agentd.conf ;;

stop)
	kill -TERM `cat /tmp/zabbix_agentd.pid` ;;

restart)
	$0 stop
	sleep 10
	$0 start
	;;

*)
	echo "Usage: $0 start|stop|restart"
	exit 1
esac
