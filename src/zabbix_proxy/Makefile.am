## Process this file with automake to produce Makefile.in

SUBDIRS = \
	../zabbix_server/dbsyncer \
	../zabbix_server/discoverer \
	housekeeper \
	../zabbix_server/httppoller \
	proxyconfig \
	../zabbix_server/pinger \
	../zabbix_server/poller \
	../zabbix_server/trapper \
	../zabbix_server/nodewatcher

sbin_PROGRAMS = zabbix_proxy

zabbix_proxy_SOURCES = \
	events.c events.h \
	zlog.c \
	proxy.c

zabbix_proxy_LDADD = \
	$(top_srcdir)/src/zabbix_server/dbsyncer/libzbxdbsyncer.a \
	$(top_srcdir)/src/zabbix_server/discoverer/libzbxdiscoverer.a \
	housekeeper/libzbxhousekeeper.a \
	$(top_srcdir)/src/zabbix_server/httppoller/libzbxhttppoller.a \
	proxyconfig/libzbxproxyconfig.a \
	$(top_srcdir)/src/zabbix_server/pinger/libzbxpinger.a \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_srcdir)/src/zabbix_server/trapper/libzbxtrapper.a \
	$(top_srcdir)/src/zabbix_server/nodewatcher/libzbxnodewatcher.a \
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
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	@PROXY_LIBS@

zabbix_proxy_LDFLAGS = \
	@PROXY_LDFLAGS@

zabbix_proxy_CFLAGS = \
	-DZABBIX_DAEMON
