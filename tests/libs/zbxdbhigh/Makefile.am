if SERVER
noinst_PROGRAMS = \
	DBselect_uint64 \
	DBadd_condition_alloc
else
if PROXY
noinst_PROGRAMS = \
	DBadd_condition_alloc
endif
endif

COMMON_SRC = \
	../../zbxmocktest.h

COMMON_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

COMMON_LIB = \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxexport/libzbxexport.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_builddir)/src/libs/zbxkvs/libzbxkvs.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

if SERVER
SERVER_COMMON_LIB = \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/zabbix_server/libzbxserver.a \
	$(top_srcdir)/src/zabbix_server/alerter/libzbxalerter.a \
	$(top_srcdir)/src/zabbix_server/dbsyncer/libzbxdbsyncer.a \
	$(top_srcdir)/src/zabbix_server/dbconfig/libzbxdbconfig.a \
	$(top_srcdir)/src/zabbix_server/discoverer/libzbxdiscoverer.a \
	$(top_srcdir)/src/zabbix_server/pinger/libzbxpinger.a \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
	$(top_srcdir)/src/zabbix_server/housekeeper/libzbxhousekeeper.a \
	$(top_srcdir)/src/zabbix_server/timer/libzbxtimer.a \
	$(top_srcdir)/src/zabbix_server/trapper/libzbxtrapper.a \
	$(top_srcdir)/src/zabbix_server/snmptrapper/libzbxsnmptrapper.a \
	$(top_srcdir)/src/zabbix_server/httppoller/libzbxhttppoller.a \
	$(top_srcdir)/src/zabbix_server/escalator/libzbxescalator.a \
	$(top_srcdir)/src/zabbix_server/proxypoller/libzbxproxypoller.a \
	$(top_srcdir)/src/zabbix_server/vmware/libzbxvmware.a \
	$(top_srcdir)/src/zabbix_server/taskmanager/libzbxtaskmanager.a \
	$(top_srcdir)/src/zabbix_server/ipmi/libipmi.a \
	$(top_srcdir)/src/zabbix_server/odbc/libzbxodbc.a \
	$(top_srcdir)/src/zabbix_server/scripts/libzbxscripts.a \
	$(top_srcdir)/src/zabbix_server/preprocessor/libpreprocessor.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_srcdir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_srcdir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_srcdir)/src/libs/zbxpreproc/libzbxpreproc.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/zabbix_server/availability/libavailability.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_srcdir)/src/libs/zbxconnector/libzbxconnector.a \
	$(top_srcdir)/src/libs/zbxservice/libzbxservice.a \
	$(top_srcdir)/src/zabbix_server/service/libservice.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(COMMON_LIB)

DBselect_uint64_SOURCES = \
	DBselect_uint64.c \
	$(COMMON_SRC)

DBselect_uint64_LDADD = \
	$(SERVER_COMMON_LIB)

DBselect_uint64_LDADD += @SERVER_LIBS@

DBselect_uint64_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

DBselect_uint64_CFLAGS = $(COMMON_FLAGS)


DBadd_condition_alloc_SOURCES = \
	DBadd_condition_alloc.c \
	$(COMMON_SRC)

DBadd_condition_alloc_LDADD = \
	$(SERVER_COMMON_LIB)

DBadd_condition_alloc_LDADD += @SERVER_LIBS@

DBadd_condition_alloc_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

DBadd_condition_alloc_CFLAGS = $(COMMON_FLAGS)

else
if PROXY

PROXY_COMMON_LIB = \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/zabbix_proxy/libzbxproxy.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxproxysysinfo.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_srcdir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_srcdir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxproxysysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxpreproc/libzbxpreproc.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_srcdir)/src/libs/zbxconnector/libzbxconnector.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxsysinfo/linux/libspechostnamesysinfo.a \
	$(COMMON_LIB) \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a

DBadd_condition_alloc_SOURCES = \
	DBadd_condition_alloc.c \
	$(COMMON_SRC)

DBadd_condition_alloc_LDADD = \
	$(PROXY_COMMON_LIB)

DBadd_condition_alloc_LDADD += @PROXY_LIBS@

DBadd_condition_alloc_LDFLAGS = @PROXY_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

DBadd_condition_alloc_CFLAGS = $(COMMON_FLAGS)

endif
endif
