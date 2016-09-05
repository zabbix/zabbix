#!/bin/bash
./sbin/zabbix_server -c /etc/zabbix/zabbix_server.conf
./sbin/zabbix_proxy -c /etc/zabbix/zabbix_proxy.conf
./sbin/zabbix_agentd -c /etc/zabbix/zabbix_agentd.conf
 
