if SERVER
SERVER_tests = \
	zbx_function_find \
	parse_key \
	replace_key_params_dyn \
	calculate_item_nextcheck
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/src/zabbix_server/escalator/libzbxescalator.a \
	$(top_srcdir)/src/zabbix_server/scripts/libzbxscripts.a \
	$(top_srcdir)/src/zabbix_server/poller/libzbxpoller.a \
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
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
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
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests

zbx_function_find_SOURCES = \
	zbx_function_find.c \
	$(COMMON_SRC_FILES)

zbx_function_find_LDADD = \
	$(COMMON_LIB_FILES)

zbx_function_find_LDADD += @SERVER_LIBS@

zbx_function_find_LDFLAGS = @SERVER_LDFLAGS@

zbx_function_find_CFLAGS = $(COMMON_COMPILER_FLAGS)

parse_key_SOURCES = \
	parse_key.c \
	$(COMMON_SRC_FILES)

parse_key_LDADD = \
	$(COMMON_LIB_FILES)

parse_key_LDADD += @SERVER_LIBS@

parse_key_LDFLAGS = @SERVER_LDFLAGS@

parse_key_CFLAGS = $(COMMON_COMPILER_FLAGS)

replace_key_params_dyn_SOURCES = \
	replace_key_params_dyn.c \
	$(COMMON_SRC_FILES)

replace_key_params_dyn_LDADD = \
	$(COMMON_LIB_FILES)

replace_key_params_dyn_LDADD += @SERVER_LIBS@

replace_key_params_dyn_LDFLAGS = @SERVER_LDFLAGS@

replace_key_params_dyn_CFLAGS = $(COMMON_COMPILER_FLAGS)

calculate_item_nextcheck_SOURCES = \
	calculate_item_nextcheck.c \
	$(COMMON_SRC_FILES)

calculate_item_nextcheck_LDADD = \
	$(COMMON_LIB_FILES)

calculate_item_nextcheck_LDADD += @SERVER_LIBS@

calculate_item_nextcheck_LDFLAGS = @SERVER_LDFLAGS@

calculate_item_nextcheck_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
