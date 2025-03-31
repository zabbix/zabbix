## Process this file with automake to produce Makefile.in

SUBDIRS = \
	audit \
	autoreg \
	actions \
	cachehistory \
	dbconfig \
	diag \
	discovery \
	events \
	housekeeper \
	poller \
	timer \
	escalator \
	operations \
	postinit \
	proxypoller \
	taskmanager \
	stats \
	preproc \
	connector \
	dbconfigworker \
	lld \
	reporter \
	service \
	trapper \
	rtc \
	ha \
	proxyconfigread \
	pgmanager

sbin_PROGRAMS = zabbix_server

zabbix_server_SOURCES = \
	db_lengths_constants.h \
	server_constants.h \
	server.c

zabbix_server_LDADD = \
	cachehistory/libzbxcachehistory_server.a \
	$(top_builddir)/src/libs/zbxdbsyncer/libzbxdbsyncer.a \
	$(top_builddir)/src/libs/zbxpinger/libzbxpinger.a \
	dbconfig/libzbxdbconfig.a \
	diag/libzbxdiag_server.a \
	$(top_builddir)/src/libs/zbxalerter/libzbxalerter.a \
	$(top_builddir)/src/libs/zbxdiscoverer/libzbxdiscoverer.a \
	events/libzbxevents.a \
	actions/libzbxactions.a \
	housekeeper/libzbxhousekeeper_server.a \
	timer/libzbxtimer.a \
	$(top_builddir)/src/libs/zbxtrapper/libzbxtrapper.a \
	$(top_builddir)/src/libs/zbxhttppoller/libzbxhttppoller.a \
	trapper/libzbxtrapper_server.a \
	$(top_builddir)/src/libs/zbxpoller/libzbxpoller.a \
	autoreg/libzbxautoreg_server.a \
	$(top_builddir)/src/libs/zbxautoreg/libzbxautoreg.a \
	reporter/libzbxreporter.a \
	$(top_builddir)/src/libs/zbxsnmptrapper/libzbxsnmptrapper.a \
	escalator/libzbxescalator.a \
	proxypoller/libzbxproxypoller.a \
	proxyconfigread/libzbxproxyconfigread.a \
	$(top_builddir)/src/libs/zbxvmware/libzbxvmware.a \
	taskmanager/libzbxtaskmanager_server.a \
	connector/libconnector.a \
	service/libservice_server.a \
	$(top_builddir)/src/libs/zbxscripts/libzbxscripts.a \
	$(top_builddir)/src/libs/zbxodbc/libzbxodbc.a \
	$(top_builddir)/src/libs/zbxembed/libzbxembed.a \
	$(top_builddir)/src/libs/zbxdiscovery/libzbxdiscovery.a \
	discovery/libzbxdiscovery_server.a \
	$(top_builddir)/src/libs/zbxexport/libzbxexport.a \
	poller/libzbxpoller_server.a \
	lld/libzbxlld.a \
	postinit/libzbxpostinit.a \
	operations/libzbxoperations.a \
	stats/libzbxstats_server.a \
	ha/libzbxha.a \
	pgmanager/libzbxpgmanager.a \
	$(top_builddir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_builddir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_builddir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_builddir)/src/libs/zbxlog/libzbxlog.a \
	$(top_builddir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_builddir)/src/libs/zbxpgservice/libzbxpgservice.a \
	$(top_builddir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_builddir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_builddir)/src/libs/zbxexpression/libzbxexpression.a \
	$(top_builddir)/src/libs/zbxevent/libzbxevent.a \
	$(top_builddir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_builddir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_builddir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	preproc/libpreproc_server.a \
	$(top_builddir)/src/libs/zbxpreproc/libzbxpreproc.a \
	$(top_builddir)/src/libs/zbxpreprocbase/libzbxpreprocbase.a \
	$(top_builddir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_builddir)/src/libs/zbxeval/libzbxeval.a \
	dbconfigworker/libdbconfigworker.a \
	$(top_builddir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_builddir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_builddir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_builddir)/src/libs/zbxstats/libzbxstats.a \
	$(top_builddir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_builddir)/src/libs/zbxself/libzbxself.a \
	$(top_builddir)/src/libs/zbxtimekeeper/libzbxtimekeeper.a \
	$(top_builddir)/src/libs/zbxescalations/libzbxescalations.a \
	$(top_builddir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_builddir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_builddir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_builddir)/src/libs/zbxprof/libzbxprof.a \
	$(top_builddir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_builddir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_builddir)/src/libs/zbxgetopt/libzbxgetopt.a \
	$(top_builddir)/src/libs/zbxhash/libzbxhash.a \
	$(top_builddir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_builddir)/src/libs/zbxcrypto/libzbxcrypto.a \
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
	$(top_builddir)/src/libs/zbxdb/libzbxdb.a \
	$(top_builddir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_builddir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_builddir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_builddir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_builddir)/src/libs/zbxdiag/libzbxdiag.a \
	$(top_builddir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_builddir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_builddir)/src/libs/zbxrtc/libzbxrtc_service.a \
	rtc/libzbxrtc_server.a \
	$(top_builddir)/src/libs/zbxrtc/libzbxrtc.a \
	$(top_builddir)/src/libs/zbxnix/libzbxnix.a \
	$(top_builddir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_builddir)/src/libs/zbxconnector/libzbxconnector.a \
	audit/libzbxaudit_server.a \
	$(top_builddir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_builddir)/src/libs/zbxservice/libzbxservice.a \
	$(top_builddir)/src/libs/zbxfile/libzbxfile.a \
	$(top_builddir)/src/libs/zbxnum/libzbxnum.a \
	$(top_builddir)/src/libs/zbxstr/libzbxstr.a \
	$(top_builddir)/src/libs/zbxparam/libzbxparam.a \
	$(top_builddir)/src/libs/zbxtime/libzbxtime.a \
	$(top_builddir)/src/libs/zbxip/libzbxip.a \
	$(top_builddir)/src/libs/zbxinterface/libzbxinterface.a \
	$(top_builddir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_builddir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_builddir)/src/libs/zbxversion/libzbxversion.a \
	$(top_builddir)/src/libs/zbxasyncpoller/libzbxasyncpoller.a \
	$(top_builddir)/src/libs/zbxasynchttppoller/libzbxasynchttppoller.a \
	$(top_builddir)/src/libs/zbxpoller/libzbxpoller.a \
	$(top_builddir)/src/libs/zbxagentget/libzbxagentget.a \
	$(top_builddir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_builddir)/src/libs/zbxbincommon/libzbxbincommon.a

if HAVE_IPMI
zabbix_server_LDADD += $(top_builddir)/src/libs/zbxipmi/libzbxipmi.a
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
	$(TLS_CFLAGS) \
	$(ARES_CFLAGS)

install-data-hook:
	$(MKDIR_P) "$(DESTDIR)$(SERVER_CONFIG_FILE).d"
	$(MKDIR_P) "$(DESTDIR)$(EXTERNAL_SCRIPTS_PATH)"
	$(MKDIR_P) "$(DESTDIR)$(ALERT_SCRIPTS_PATH)"
	$(MKDIR_P) "$(DESTDIR)$(LOAD_MODULE_PATH)"
	test -f "$(DESTDIR)$(SERVER_CONFIG_FILE)" || cp "../../conf/zabbix_server.conf" "$(DESTDIR)$(SERVER_CONFIG_FILE)"
