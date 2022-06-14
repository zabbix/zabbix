## Process this file with automake to produce Makefile.in

SUBDIRS = \
	heart \
	housekeeper \
	proxyconfig \
	datasender \
	taskmanager

sbin_PROGRAMS = zabbix_proxy

noinst_LIBRARIES = libzbxproxy.a

libzbxproxy_a_SOURCES = \
	events.c \
	proxy_lld.c

libzbxproxy_a_CFLAGS = \
	-DZABBIX_DAEMON

zabbix_proxy_SOURCES = proxy.c

zabbix_proxy_LDADD = \
	heart/libzbxheart.a \
	$(top_builddir)/src/zabbix_server/dbsyncer/libzbxdbsyncer.a \
	$(top_builddir)/src/zabbix_server/discoverer/libzbxdiscoverer.a \
	housekeeper/libzbxhousekeeper.a \
	$(top_builddir)/src/zabbix_server/httppoller/libzbxhttppoller.a \
	proxyconfig/libzbxproxyconfig.a \
	$(top_builddir)/src/zabbix_server/pinger/libzbxpinger.a \
	$(top_builddir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_builddir)/src/zabbix_server/poller/libzbxpoller_proxy.a \
	$(top_builddir)/src/zabbix_server/trapper/libzbxtrapper.a \
	$(top_builddir)/src/zabbix_server/trapper/libzbxtrapper_proxy.a \
	$(top_builddir)/src/zabbix_server/snmptrapper/libzbxsnmptrapper.a \
	$(top_builddir)/src/zabbix_server/odbc/libzbxodbc.a \
	datasender/libzbxdatasender.a \
	$(top_builddir)/src/zabbix_server/preprocessor/libpreprocessor.a \
	$(top_builddir)/src/libs/zbxxml/libzbxxml.a \
	$(top_builddir)/src/libs/zbxembed/libzbxembed.a \
	taskmanager/libzbxtaskmanager.a \
	$(top_builddir)/src/zabbix_server/selfmon/libzbxselfmon.a \
	$(top_builddir)/src/zabbix_server/vmware/libzbxvmware.a \
	$(top_builddir)/src/zabbix_server/scripts/libzbxscripts.a \
	$(top_builddir)/src/zabbix_server/availability/libavailability.a \
	$(top_builddir)/src/libs/zbxsysinfo/libzbxproxysysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_builddir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_builddir)/src/libs/zbxlog/libzbxlog.a \
	$(top_builddir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_builddir)/src/libs/zbxserver/libzbxserver.a \
	$(top_builddir)/src/libs/zbxserver/libzbxserver_proxy.a \
	$(top_builddir)/src/libs/zbxeval/libzbxeval.a \
	$(top_builddir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_builddir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_builddir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_builddir)/src/libs/zbxself/libzbxself.a \
	$(top_builddir)/src/libs/zbxself/libzbxself_proxy.a \
	$(top_builddir)/src/libs/zbxnix/libzbxnix.a \
	$(top_builddir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_builddir)/src/libs/zbxsys/libzbxsys.a \
	$(top_builddir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_builddir)/src/libs/zbxconf/libzbxconf.a \
	$(top_builddir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_builddir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_builddir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_builddir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_builddir)/src/libs/zbxjson/libzbxjson.a \
	$(top_builddir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_builddir)/src/libs/zbxexec/libzbxexec.a \
	$(top_builddir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_builddir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_builddir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_builddir)/src/libs/zbxdb/libzbxdb.a \
	$(top_builddir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_builddir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_builddir)/src/libs/zbxrtc/libzbxrtc_service.a \
	$(top_builddir)/src/libs/zbxrtc/libzbxrtc_proxy.a \
	$(top_builddir)/src/libs/zbxrtc/libzbxrtc.a \
	$(top_builddir)/src/libs/zbxdiag/libzbxdiag_proxy.a \
	$(top_builddir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_builddir)/src/libs/zbxtrends/libzbxtrends_baseline.a \
	$(top_builddir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_builddir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_builddir)/src/libs/zbxvault/libzbxvault.a \
	$(top_builddir)/src/libs/zbxavailability/libzbxavailability.a \
	libzbxproxy.a

zabbix_proxy_LDADD += $(top_builddir)/src/libs/zbxalgo/libzbxalgo.a

if HAVE_IPMI
zabbix_proxy_LDADD += $(top_builddir)/src/zabbix_server/ipmi/libipmi.a
endif

zabbix_proxy_LDADD += $(PROXY_LIBS)

zabbix_proxy_LDFLAGS = $(PROXY_LDFLAGS)

zabbix_proxy_CFLAGS = \
	-DDEFAULT_SSL_CERT_LOCATION="\"$(CURL_SSL_CERT_LOCATION)\"" \
	-DDEFAULT_SSL_KEY_LOCATION="\"$(URL_SSL_KEY_LOCATION)\"" \
	-DDEFAULT_CONFIG_FILE="\"$(PROXY_CONFIG_FILE)\"" \
	-DDEFAULT_EXTERNAL_SCRIPTS_PATH="\"$(EXTERNAL_SCRIPTS_PATH)\"" \
	-DDEFAULT_LOAD_MODULE_PATH="\"$(LOAD_MODULE_PATH)\""

install-data-hook:
	$(MKDIR_P) "$(DESTDIR)$(PROXY_CONFIG_FILE).d"
	$(MKDIR_P) "$(DESTDIR)$(EXTERNAL_SCRIPTS_PATH)"
	$(MKDIR_P) "$(DESTDIR)$(LOAD_MODULE_PATH)"
	test -f "$(DESTDIR)$(PROXY_CONFIG_FILE)" || cp "../../conf/zabbix_proxy.conf" "$(DESTDIR)$(PROXY_CONFIG_FILE)"
