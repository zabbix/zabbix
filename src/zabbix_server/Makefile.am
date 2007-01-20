SUBDIRS = alerter housekeeper pinger poller httppoller timer trapper nodewatcher utils
INCLUDES=-I@top_srcdir@/include @ORACLE_INCLUDE@ @SNMP_INCLUDE@

bin_PROGRAMS = zabbix_server
zabbix_server_SOURCES = evalfunc.c expression.c actions.c events.c zlog.c functions.c server.c
zabbix_server_CPPFLAGS = -DZABBIX_DAEMON @MYSQL_CFLAGS@ @POSTGRESQL_CFLAGS@ @SQLITE3_CFLAGS@ @LIBCURL_CPPFLAGS@
zabbix_server_LDADD = ../libs/zbxsysinfo/libzbxsysinfo.a ../libs/zbxsysinfo/@ARCH@/libzbxsysinfo2.a \
../libs/zbxlog/libzbxlog.a ../libs/zbxnix/libzbxnix.a ../libs/zbxsys/libzbxsys.a ../libs/zbxconf/libzbxconf.a \
../libs/zbxnet/libzbxnet.a pinger/libzbxpinger.a poller/libzbxpoller.a housekeeper/libzbxhousekeeper.a \
alerter/libzbxalerter.a timer/libzbxtimer.a trapper/libzbxtrapper.a nodewatcher/libzbxnodewatcher.a \
../libs/zbxemail/libzbxemail.a utils/libzbxutils.a  httppoller/libzbxhttppoller.a \
../libs/zbxsms/libzbxsms.a ../libs/zbxdbhigh/libzbxdbhigh.a ../libs/zbxcommon/libzbxcommon.a \
../libs/zbxcrypto/libzbxcrypto.a ../libs/zbxcomms/libzbxcomms.a @ORACLE_LIBS@ @LDAP_LIBS@ @SNMP_LIBS@ @LIBCURL@
zabbix_server_LDFLAGS=@MYSQL_LDFLAGS@ @POSTGRESQL_LDFLAGS@ @ORACLE_LFLAGS@ @SQLITE3_LDFLAGS@ @LDAP_LFLAGS@ @SNMP_LFLAGS@
