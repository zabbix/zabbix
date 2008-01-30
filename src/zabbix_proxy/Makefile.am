## Process this file with automake to produce Makefile.in

SUBDIRS = \
	dbsyncer \
	discoverer \
	housekeeper \
	httppoller \
	proxyconfig \
	pinger \
	poller \
	trapper

sbin_PROGRAMS = zabbix_proxy

zabbix_proxy_SOURCES = \
	evalfunc.c evalfunc.h \
	expression.c expression.h \
	operations.c operations.h \
	events.c events.h \
	zlog.c \
	functions.c functions.h \
	proxy.c

zabbix_proxy_LDADD = \
	dbsyncer/libzbxdbsyncer.a \
	discoverer/libzbxdiscoverer.a \
	housekeeper/libzbxhousekeeper.a \
	httppoller/libzbxhttppoller.a \
	proxyconfig/libzbxproxyconfig.a \
	pinger/libzbxpinger.a \
	poller/libzbxpoller.a \
	trapper/libzbxtrapper.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	@PROXY_LIBS@

zabbix_proxy_LDFLAGS = \
	@PROXY_LDFLAGS@

zabbix_proxy_CFLAGS = -DZABBIX_DAEMON
