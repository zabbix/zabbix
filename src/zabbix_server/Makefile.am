## Process this file with automake to produce Makefile.in

SUBDIRS = \
	alerter \
	housekeeper \
	pinger \
	poller \
	httppoller \
	timer \
	trapper \
	nodewatcher \
	watchdog \
	utils

bin_PROGRAMS = zabbix_server

zabbix_server_SOURCES = \
	evalfunc.c evalfunc.h \
	expression.c expression.h \
	actions.c actions.h \
	events.c events.h \
	zlog.c \
	functions.c functions.h \
	server.c

zabbix_server_LDADD = \
	alerter/libzbxalerter.a \
	pinger/libzbxpinger.a \
	poller/libzbxpoller.a \
	housekeeper/libzbxhousekeeper.a \
	timer/libzbxtimer.a \
	trapper/libzbxtrapper.a \
	nodewatcher/libzbxnodewatcher.a \
	utils/libzbxutils.a \
	httppoller/libzbxhttppoller.a \
	watchdog/libzbxwatchdog.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libzbxsysinfo2.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxemail/libzbxemail.a \
	$(top_srcdir)/src/libs/zbxnet/libzbxnet.a \
	$(top_srcdir)/src/libs/zbxsms/libzbxsms.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a

zabbix_server_CPPFLAGS = -DZABBIX_DAEMON $(DB_CPPFLAGS) $(SNMP_CPPFLAGS) $(LDAP_CPPFLAGS) $(LIBCURL_CPPFLAGS)

zabbix_server_LDFLAGS = $(DB_LDFLAGS) $(SNMP_LDFLAGS) $(LDAP_LDFLAGS) $(LIBCURL_LDFLAGS)
