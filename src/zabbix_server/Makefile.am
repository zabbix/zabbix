## Process this file with automake to produce Makefile.in

SUBDIRS = \
	alerter \
	discoverer \
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
	operations.c operations.h \
	events.c events.h \
	zlog.c \
	functions.c functions.h \
	server.c

if JABBER 
JABBER_LDADD = $(top_srcdir)/src/libs/zbxjabber/libzbxjabber.a
endif

zabbix_server_LDADD = \
	alerter/libzbxalerter.a \
	discoverer/libzbxdiscoverer.a \
	pinger/libzbxpinger.a \
	poller/libzbxpoller.a \
	housekeeper/libzbxhousekeeper.a \
	timer/libzbxtimer.a \
	trapper/libzbxtrapper.a \
	nodewatcher/libzbxnodewatcher.a \
	utils/libzbxutils.a \
	httppoller/libzbxhttppoller.a \
	watchdog/libzbxwatchdog.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxemail/libzbxemail.a \
	$(top_srcdir)/src/libs/zbxsms/libzbxsms.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(JABBER_LDADD) \
	@SERVER_LIBS@

zabbix_server_LDFLAGS = \
	@SERVER_LDFLAGS@

zabbix_server_CFLAGS = -DZABBIX_DAEMON
