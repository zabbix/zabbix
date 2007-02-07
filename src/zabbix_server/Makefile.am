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

zabbix_server_CPPFLAGS = -DZABBIX_DAEMON $(DB_CPPFLAGS) $(SNMP_INCLUDE) $(LIBCURL_CPPFLAGS)

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
	../libs/zbxsysinfo/libzbxsysinfo.a \
	../libs/zbxsysinfo/$(ARCH)/libzbxsysinfo2.a \
	../libs/zbxlog/libzbxlog.a \
	../libs/zbxnix/libzbxnix.a \
	../libs/zbxsys/libzbxsys.a \
	../libs/zbxconf/libzbxconf.a \
	../libs/zbxemail/libzbxemail.a \
	../libs/zbxnet/libzbxnet.a \
	../libs/zbxsms/libzbxsms.a \
	../libs/zbxdbhigh/libzbxdbhigh.a \
	../libs/zbxcommon/libzbxcommon.a \
	../libs/zbxcrypto/libzbxcrypto.a \
	../libs/zbxcomms/libzbxcomms.a

zabbix_server_LDFLAGS = $(DB_LDFLAGS) $(SNMP_LIBS) $(LDAP_LIBS) $(LIBCURL)
