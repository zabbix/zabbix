## Process this file with automake to produce Makefile.in

SUBDIRS = \
	heart \
	housekeeper \
	proxyconfig \
	datasender \
	taskmanager

sbin_PROGRAMS = zabbix_proxy

zabbix_proxy_SOURCES = \
	servercomms.c servercomms.h \
	events.c \
	proxy.c \
	scripts.c

zabbix_proxy_LDADD = \
	heart/libzbxheart.a \
	$(top_srcdir)/src/zabbix_server/dbsyncer/libzbxdbsyncer.a \
	$(top_srcdir)/src/zabbix_server/discoverer/libzbxdiscoverer.a \
	housekeeper/libzbxhousekeeper.a \
	$(top_srcdir)/src/zabbix_server/httppoller/libzbxhttppoller.a \
	proxyconfig/libzbxproxyconfig.a \
	$(top_srcdir)/src/zabbix_server/pinger/libzbxpinger.a \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_srcdir)/src/zabbix_server/trapper/libzbxtrapper.a \
	$(top_srcdir)/src/zabbix_server/snmptrapper/libzbxsnmptrapper.a \
	datasender/libzbxdatasender.a \
	taskmanager/libzbxtaskmanager.a \
	$(top_srcdir)/src/zabbix_server/selfmon/libzbxselfmon.a \
	$(top_srcdir)/src/zabbix_server/vmware/libzbxvmware.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxproxysysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
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
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxtasks/libzbxtasks.a

zabbix_proxy_LDADD += @PROXY_LIBS@

zabbix_proxy_LDFLAGS = @PROXY_LDFLAGS@

zabbix_proxy_CFLAGS = \
	-DZABBIX_DAEMON \
	-DSYSCONFDIR="\"@sysconfdir@\"" \
	-DDATADIR="\"@datadir@\"" \
	-DLIBDIR="\"@libdir@\""

PROXY_CONFIG = zabbix_proxy.conf
INCLUDE_DIR = $(DESTDIR)@sysconfdir@/$(PROXY_CONFIG).d
EXSCRIPTS_DIR = $(DESTDIR)@datadir@/zabbix/externalscripts
MODULES_DIR = $(DESTDIR)@libdir@

install-data-hook:
	$(MKDIR_P) "$(INCLUDE_DIR)"
	$(MKDIR_P) "$(EXSCRIPTS_DIR)"
	$(MKDIR_P) "$(MODULES_DIR)"
	test -f "$(DESTDIR)@sysconfdir@/$(PROXY_CONFIG)" || cp "../../conf/$(PROXY_CONFIG)" "$(DESTDIR)@sysconfdir@"
