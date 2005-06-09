SUBDIRS = .
INCLUDES=-I@top_srcdir@/include @MYSQL_INCLUDE@

bin_PROGRAMS = zabbix_server
zabbix_server_SOURCES = alerter.c pinger.c housekeeper.c checks_simple.c checks_snmp.c checks_agent.c checks_internal.c trapper.c escalator.c expression.c actions.c
