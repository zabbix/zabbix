if SERVER
SERVER_tests = \
	get_trigger_expression_constant \
	evaluate_function \
	substitute_lld_macros \
	macro_fmttime \
	zbx_eval_parse_expression \
	zbx_eval_serialize \
	zbx_eval_compose_expression \
	zbx_eval_execute \
	zbx_eval_execute_ext
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
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/zabbix_server/availability/libavailability.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
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
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/zabbix_server/libzbxserver.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests

get_trigger_expression_constant_SOURCES = \
	get_trigger_expression_constant.c

get_trigger_expression_constant_LDADD = \
	$(COMMON_LIB_FILES)

get_trigger_expression_constant_LDADD += @SERVER_LIBS@

get_trigger_expression_constant_LDFLAGS = @SERVER_LDFLAGS@

get_trigger_expression_constant_CFLAGS = $(COMMON_COMPILER_FLAGS)


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
	-Wl,--wrap=time \
	-Wl,--wrap=substitute_simple_macros \
	-Wl,--wrap=DCget_data_expected_from \
	-Wl,--wrap=zbx_timespec

evaluate_function_CFLAGS = $(COMMON_COMPILER_FLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory

	
zbx_eval_parse_expression_SOURCES = \
	zbx_eval_parse_expression.c \
	mock_eval.c mock_eval.h

zbx_eval_parse_expression_LDADD = \
	$(COMMON_LIB_FILES)

zbx_eval_parse_expression_LDADD += @SERVER_LIBS@

zbx_eval_parse_expression_LDFLAGS = @SERVER_LDFLAGS@

zbx_eval_parse_expression_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_serialize_SOURCES = \
	zbx_eval_serialize.c

zbx_eval_serialize_LDADD = \
	$(COMMON_LIB_FILES)

zbx_eval_serialize_LDADD += @SERVER_LIBS@

zbx_eval_serialize_LDFLAGS = @SERVER_LDFLAGS@

zbx_eval_serialize_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_compose_expression_SOURCES = \
	zbx_eval_compose_expression.c \
	mock_eval.c mock_eval.h

zbx_eval_compose_expression_LDADD = \
	$(COMMON_LIB_FILES)

zbx_eval_compose_expression_LDADD += @SERVER_LIBS@

zbx_eval_compose_expression_LDFLAGS = @SERVER_LDFLAGS@

zbx_eval_compose_expression_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_execute_SOURCES = \
	zbx_eval_execute.c \
	mock_eval.c mock_eval.h

zbx_eval_execute_LDADD = \
	$(COMMON_LIB_FILES)

zbx_eval_execute_LDADD += @SERVER_LIBS@

zbx_eval_execute_LDFLAGS = @SERVER_LDFLAGS@

zbx_eval_execute_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_execute_ext_SOURCES = \
	zbx_eval_execute_ext.c \
	mock_eval.c mock_eval.h

zbx_eval_execute_ext_LDADD = \
	$(COMMON_LIB_FILES)

zbx_eval_execute_ext_LDADD += @SERVER_LIBS@

zbx_eval_execute_ext_LDFLAGS = @SERVER_LDFLAGS@

zbx_eval_execute_ext_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif

