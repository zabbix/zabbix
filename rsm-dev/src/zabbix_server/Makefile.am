## Process this file with automake to produce Makefile.in

SUBDIRS = \
	alerter \
	dbsyncer \
	dbconfig \
	discoverer \
	housekeeper \
	httppoller \
	pinger \
	poller \
	snmptrapper \
	timer \
	trapper \
	watchdog \
	escalator \
	proxypoller \
	selfmon \
	vmware

sbin_PROGRAMS = zabbix_server

zabbix_server_SOURCES = \
	actions.c actions.h \
	operations.c operations.h \
	events.c events.h \
	scripts.c scripts.h \
	server.c

zabbix_server_LDADD = \
	alerter/libzbxalerter.a \
	dbsyncer/libzbxdbsyncer.a \
	dbconfig/libzbxdbconfig.a \
	discoverer/libzbxdiscoverer.a \
	pinger/libzbxpinger.a \
	poller/libzbxpoller.a \
	housekeeper/libzbxhousekeeper.a \
	timer/libzbxtimer.a \
	trapper/libzbxtrapper.a \
	snmptrapper/libzbxsnmptrapper.a \
	httppoller/libzbxhttppoller.a \
	watchdog/libzbxwatchdog.a \
	escalator/libzbxescalator.a \
	proxypoller/libzbxproxypoller.a \
	selfmon/libzbxselfmon.a \
	vmware/libzbxvmware.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_srcdir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a

zabbix_server_LDADD += @SERVER_LIBS@

zabbix_server_LDFLAGS = @SERVER_LDFLAGS@

zabbix_server_CFLAGS = \
	-DZABBIX_DAEMON \
	-DSYSCONFDIR="\"@sysconfdir@\"" \
	-DDATADIR="\"@datadir@\"" \
	-DLIBDIR="\"@libdir@\"" \
	-I@top_srcdir@/src/libs/zbxdbcache

SERVER_CONFIG = zabbix_server.conf
INCLUDE_DIR = $(DESTDIR)@sysconfdir@/$(SERVER_CONFIG).d
EXSCRIPTS_DIR = $(DESTDIR)@datadir@/zabbix/externalscripts
ALSCRIPTS_DIR = $(DESTDIR)@datadir@/zabbix/alertscripts
MODULES_DIR = $(DESTDIR)@libdir@

install-data-hook:
	$(MKDIR_P) "$(INCLUDE_DIR)"
	$(MKDIR_P) "$(EXSCRIPTS_DIR)"
	$(MKDIR_P) "$(ALSCRIPTS_DIR)"
	$(MKDIR_P) "$(MODULES_DIR)"
	test -f "$(DESTDIR)@sysconfdir@/$(SERVER_CONFIG)" || cp "../../conf/$(SERVER_CONFIG)" "$(DESTDIR)@sysconfdir@"
