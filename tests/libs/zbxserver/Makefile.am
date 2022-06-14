if SERVER
SERVER_tests = \
	evaluate_function \
	evaluate_stl \
	evaluate_percentage_deviations_in_remainder \
	substitute_lld_macros \
	macro_fmttime \
	valuemaps
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
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
	$(top_srcdir)/src/zabbix_server/selfmon/libzbxselfmon.a \
	$(top_srcdir)/src/zabbix_server/vmware/libzbxvmware.a \
	$(top_srcdir)/src/zabbix_server/taskmanager/libzbxtaskmanager.a \
	$(top_srcdir)/src/zabbix_server/ipmi/libipmi.a \
	$(top_srcdir)/src/zabbix_server/odbc/libzbxodbc.a \
	$(top_srcdir)/src/zabbix_server/scripts/libzbxscripts.a \
	$(top_srcdir)/src/zabbix_server/preprocessor/libpreprocessor.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends_baseline.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/zabbix_server/availability/libavailability.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_srcdir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends_baseline.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/zabbix_server/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxservice/libzbxservice.a \
	$(top_srcdir)/src/zabbix_server/service/libservice.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxaudit/libzbxaudit.a

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests


substitute_lld_macros_SOURCES = \
	substitute_lld_macros.c

substitute_lld_macros_LDADD = \
	$(COMMON_LIB_FILES)

substitute_lld_macros_LDADD += @SERVER_LIBS@

substitute_lld_macros_LDFLAGS =  @SERVER_LDFLAGS@

substitute_lld_macros_CFLAGS = $(COMMON_COMPILER_FLAGS)


macro_fmttime_SOURCES = \
	macro_fmttime.c \
	$(COMMON_SRC_FILES)

macro_fmttime_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

macro_fmttime_LDADD += $(COMMON_LIB_FILES)

macro_fmttime_LDADD += @SERVER_LIBS@

macro_fmttime_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS)

macro_fmttime_CFLAGS = $(COMMON_COMPILER_FLAGS) \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxserver

evaluate_function_SOURCES = \
	evaluate_function.c \
	$(COMMON_SRC_FILES)

evaluate_function_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

evaluate_function_LDADD += $(COMMON_LIB_FILES)

evaluate_function_LDADD += @SERVER_LIBS@

evaluate_function_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS)

evaluate_stl_SOURCES = \
	evaluate_stl.c \
	$(COMMON_SRC_FILES)

evaluate_stl_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

evaluate_stl_LDADD += $(COMMON_LIB_FILES)

evaluate_stl_LDADD += @SERVER_LIBS@

evaluate_stl_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS)

evaluate_percentage_deviations_in_remainder_SOURCES = \
	evaluate_percentage_deviations_in_remainder.c \
	$(COMMON_SRC_FILES)

evaluate_percentage_deviations_in_remainder_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

evaluate_percentage_deviations_in_remainder_LDADD += $(COMMON_LIB_FILES)

evaluate_percentage_deviations_in_remainder_LDADD += @SERVER_LIBS@

evaluate_percentage_deviations_in_remainder_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS)

valuemaps_SOURCES = \
	valuemaps.c \
	$(COMMON_SRC_FILES)

valuemaps_LDADD = $(COMMON_LIB_FILES)

valuemaps_LDADD += @SERVER_LIBS@

valuemaps_LDFLAGS = @SERVER_LDFLAGS@

VALUECACHE_WRAP_FUNCS = \
	-Wl,--wrap=zbx_mutex_create \
	-Wl,--wrap=zbx_mutex_destroy \
	-Wl,--wrap=zbx_mem_create \
	-Wl,--wrap=__zbx_mem_malloc \
	-Wl,--wrap=__zbx_mem_realloc \
	-Wl,--wrap=__zbx_mem_free \
	-Wl,--wrap=zbx_mem_dump_stats \
	-Wl,--wrap=zbx_history_get_values \
	-Wl,--wrap=zbx_history_add_values \
	-Wl,--wrap=zbx_history_sql_init \
	-Wl,--wrap=zbx_history_elastic_init \
	-Wl,--wrap=zbx_elastic_version_extract \
	-Wl,--wrap=zbx_elastic_version_get \
	-Wl,--wrap=time \
	-Wl,--wrap=substitute_simple_macros \
	-Wl,--wrap=DCget_data_expected_from \
	-Wl,--wrap=zbx_timespec

evaluate_function_CFLAGS = $(COMMON_COMPILER_FLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory

evaluate_stl_CFLAGS = $(COMMON_COMPILER_FLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory

evaluate_percentage_deviations_in_remainder_CFLAGS = $(COMMON_COMPILER_FLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory

valuemaps_CFLAGS = $(COMMON_COMPILER_FLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory
endif

