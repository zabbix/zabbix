SUBDIRS = alerter housekeeper pinger poller timer trapper
INCLUDES=-I@top_srcdir@/include @MYSQL_INCLUDE@ @ORACLE_INCLUDE@ @SNMP_INCLUDE@ @PGSQL_INCLUDE@

bin_PROGRAMS = zabbix_server
zabbix_server_SOURCES = evalfunc.c expression.c actions.c zlog.c functions.c server.c
zabbix_server_LDADD = ../libs/zbxsysinfo/libzbxsysinfo.a ../libs/zbxsysinfo/@ARCH@/libzbxsysinfo2.a \
../libs/zbxlog/libzbxlog.a ../libs/zbxpid/libzbxpid.a ../libs/zbxconf/libzbxconf.a ../libs/zbxcrypto/libzbxcrypto.a ../libs/zbxnet/libzbxnet.a pinger/libzbxpinger.a poller/libzbxpoller.a housekeeper/libzbxhousekeeper.a alerter/libzbxalerter.a timer/libzbxtimer.a trapper/libzbxtrapper.a ../libs/zbxemail/libzbxemail.a  ../libs/zbxsms/libzbxsms.a ../libs/zbxdbhigh/libzbxdbhigh.a ../libs/zbxcommon/libzbxcommon.a @MYSQL_LIBS@ @PGSQL_LIBS@ @ORACLE_LIBS@ @LDAP_LIBS@ @SNMP_LIBS@
zabbix_server_LDFLAGS=@MYSQL_LFLAGS@ @PGSQL_LFLAGS@ @ORACLE_LFLAGS@ @LDAP_LFLAGS@ @SNMP_LFLAGS@
