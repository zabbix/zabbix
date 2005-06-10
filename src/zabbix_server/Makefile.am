SUBDIRS = alerter escalator housekeeper pinger trapper .
INCLUDES=-I@top_srcdir@/include @MYSQL_INCLUDE@

bin_PROGRAMS = zabbix_server
zabbix_server_SOURCES = checks_simple.c checks_snmp.c checks_agent.c checks_internal.c expression.c actions.c zlog.c functions.c server.c
zabbix_server_LDADD = ../libs/zbxsysinfo/libzbxsysinfo.a ../libs/zbxlog/libzbxlog.a ../libs/zbxpid/libzbxpid.a ../libs/zbxconf/libzbxconf.a ../libs/zbxdbhigh/libzbxdbhigh.a ../libs/zbxcrypto/libzbxcrypto.a ../libs/zbxnet/libzbxnet.a pinger/libzbxpinger.a escalator/libzbxescalator.a housekeeper/libzbxhousekeeper.a alerter/libzbxalerter.a ../libs/zbxemail/libzbxemail.a ../libs/zbxtrapper/libzbxtrapper.a
zabbix_server_LDFLAGS=@MYSQL_LFLAGS@ @PGSQL_LFLAGS@
