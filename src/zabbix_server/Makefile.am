## Process this file with automake to produce Makefile.in

SUBDIRS = \
	alerter \
	dbsyncer \
	dbconfig \
	diag \
	discoverer \
	housekeeper \
	httppoller \
	pinger \
	poller \
	snmptrapper \
	timer \
	trapper \
	escalator \
	proxypoller \
	vmware \
	taskmanager \
	ipmi \
	odbc \
	scripts \
	stats \
	preprocessor \
	availability \
	connector \
	lld \
	reporter \
	service \
	rtc \
	ha \
	proxyconfigread

sbin_PROGRAMS = zabbix_server

noinst_LIBRARIES = libzbxserver.a

libzbxserver_a_SOURCES = \
	db_lengths.h \
	actions.c \
	actions.h \
	events.c \
	events.h \
	operations.c \
	operations.h \
	postinit.c \
	postinit.h \
	zbxreport.h

libzbxserver_a_CFLAGS = \
	-DZABBIX_DAEMON \
	-I$(top_srcdir)/src/libs/zbxcacheconfig \
	-I$(top_srcdir)/src/libs/zbxcachehistory \
	-I$(top_srcdir)/src/libs/zbxcachevalue \
	$(TLS_CFLAGS)

zabbix_server_SOURCES = \
	server.h \
	server.c

zabbix_server_LDADD = \
	dbsyncer/libzbxdbsyncer.a \
	dbconfig/libzbxdbconfig.a \
	diag/libzbxdiag_server.a \
	alerter/libzbxalerter.a \
	discoverer/libzbxdiscoverer.a \
	pinger/libzbxpinger.a \
	poller/libzbxpoller.a \
	housekeeper/libzbxhousekeeper.a \
	timer/libzbxtimer.a \
	trapper/libzbxtrapper.a \
	trapper/libzbxtrapper_server.a \
	reporter/libzbxreporter.a \
	snmptrapper/libzbxsnmptrapper.a \
	httppoller/libzbxhttppoller.a \
	escalator/libzbxescalator.a \
	proxypoller/libzbxproxypoller.a \
	proxyconfigread/libzbxproxyconfigread.a \
	vmware/libzbxvmware.a \
	taskmanager/libzbxtaskmanager.a \
	odbc/libzbxodbc.a \
	scripts/libzbxscripts.a \
	availability/libavailability.a \
	connector/libconnector.a \
	service/libservice.a \
	$(top_builddir)/src/libs/zbxembed/libzbxembed.a \
	$(top_builddir)/src/libs/zbxdiscovery/libzbxdiscovery.a \
	$(top_builddir)/src/libs/zbxexport/libzbxexport.a \
	poller/libzbxpoller_server.a \
	lld/libzbxlld.a \
	libzbxserver.a \
	stats/libzbxserver_server.a \
	ha/libzbxha.a \
	$(top_builddir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_builddir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_builddir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_builddir)/src/libs/zbxlog/libzbxlog.a \
	$(top_builddir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_builddir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_builddir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_builddir)/src/libs/zbxserver/libzbxserver.a \
	$(top_builddir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_builddir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_builddir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	preprocessor/libpreprocessor.a \
	$(top_builddir)/src/libs/zbxpreproc/libzbxpreproc.a \
	$(top_builddir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_builddir)/src/libs/zbxeval/libzbxeval.a \
	$(top_builddir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_builddir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_builddir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_builddir)/src/libs/zbxstats/libzbxstats.a \
	$(top_builddir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_builddir)/src/libs/zbxself/libzbxself.a \
	$(top_builddir)/src/libs/zbxtimekeeper/libzbxtimekeeper.a \
	$(top_builddir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_builddir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_builddir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_builddir)/src/libs/zbxprof/libzbxprof.a \
	$(top_builddir)/src/libs/zbxconf/libzbxconf.a \
	$(top_builddir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_builddir)/src/libs/zbxgetopt/libzbxgetopt.a \
	$(top_builddir)/src/libs/zbxhash/libzbxhash.a \
	$(top_builddir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_builddir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_builddir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_builddir)/src/libs/zbxxml/libzbxxml.a \
	$(top_builddir)/src/libs/zbxjson/libzbxjson.a \
	$(top_builddir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_builddir)/src/libs/zbxvault/libzbxvault.a \
	$(top_builddir)/src/libs/zbxkvs/libzbxkvs.a \
	$(top_builddir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_builddir)/src/libs/zbxexec/libzbxexec.a \
	$(top_builddir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_builddir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_builddir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_builddir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_builddir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_builddir)/src/libs/zbxdb/libzbxdb.a \
	$(top_builddir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_builddir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_builddir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_builddir)/src/libs/zbxdiag/libzbxdiag.a \
	$(top_builddir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_builddir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_builddir)/src/libs/zbxha/libzbxha.a \
	$(top_builddir)/src/libs/zbxrtc/libzbxrtc_service.a \
	rtc/libzbxrtc_server.a \
	$(top_builddir)/src/libs/zbxrtc/libzbxrtc.a \
	$(top_builddir)/src/libs/zbxnix/libzbxnix.a \
	$(top_builddir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_builddir)/src/libs/zbxconnector/libzbxconnector.a \
	$(top_builddir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_builddir)/src/libs/zbxservice/libzbxservice.a \
	$(top_builddir)/src/libs/zbxnum/libzbxnum.a \
	$(top_builddir)/src/libs/zbxstr/libzbxstr.a \
	$(top_builddir)/src/libs/zbxparam/libzbxparam.a \
	$(top_builddir)/src/libs/zbxtime/libzbxtime.a \
	$(top_builddir)/src/libs/zbxip/libzbxip.a \
	$(top_builddir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_builddir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_builddir)/src/libs/zbxversion/libzbxversion.a
if HAVE_IPMI
zabbix_server_LDADD += ipmi/libipmi.a
endif

zabbix_server_LDADD += $(SERVER_LIBS)

zabbix_server_LDFLAGS = $(SERVER_LDFLAGS)

zabbix_server_CFLAGS = \
	-DDEFAULT_SSL_CERT_LOCATION="\"$(CURL_SSL_CERT_LOCATION)\"" \
	-DDEFAULT_SSL_KEY_LOCATION="\"$(CURL_SSL_KEY_LOCATION)\"" \
	-DDEFAULT_CONFIG_FILE="\"$(SERVER_CONFIG_FILE)\"" \
	-DDEFAULT_EXTERNAL_SCRIPTS_PATH="\"$(EXTERNAL_SCRIPTS_PATH)\"" \
	-DDEFAULT_ALERT_SCRIPTS_PATH="\"$(ALERT_SCRIPTS_PATH)\"" \
	-DDEFAULT_LOAD_MODULE_PATH="\"$(LOAD_MODULE_PATH)\"" \
	$(TLS_CFLAGS)

install-data-hook:
	$(MKDIR_P) "$(DESTDIR)$(SERVER_CONFIG_FILE).d"
	$(MKDIR_P) "$(DESTDIR)$(EXTERNAL_SCRIPTS_PATH)"
	$(MKDIR_P) "$(DESTDIR)$(ALERT_SCRIPTS_PATH)"
	$(MKDIR_P) "$(DESTDIR)$(LOAD_MODULE_PATH)"
	test -f "$(DESTDIR)$(SERVER_CONFIG_FILE)" || cp "../../conf/zabbix_server.conf" "$(DESTDIR)$(SERVER_CONFIG_FILE)"
