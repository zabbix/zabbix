#!/bin/sh

echo ssh zabbix@$1 "/etc/init.d/zabbix_agentd $2"
