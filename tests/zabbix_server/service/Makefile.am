if SERVER
SERVER_tests = \
	service_get_status \
	service_get_main_status \
	service_get_rule_status \
	service_get_rootcause_eventids


noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/zabbix_server/service/libservice.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/zabbix_server/availability/libavailability.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends_baseline.a \
	$(top_srcdir)/src/zabbix_server/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxservice/libzbxservice.a \
	$(top_srcdir)/src/zabbix_server/service/libservice.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_builddir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/tests/libzbxmockdata.a
	
SERVICE_WRAP_FUNCS = \
	-Wl,--wrap=DCget_nextid \
	-Wl,--wrap=zbx_add_event \
	-Wl,--wrap=zbx_process_events \
	-Wl,--wrap=zbx_clean_events \
	-Wl,--wrap=zbx_interface_availability_is_set
	
# service_get_rootcause_eventids

service_get_rootcause_eventids_SOURCES = \
	service_get_rootcause_eventids.c \
	mock_service.c \
	mock_service.h

service_get_rootcause_eventids_LDADD = $(COMMON_LIBS)
service_get_rootcause_eventids_LDADD += @SERVER_LIBS@
service_get_rootcause_eventids_LDFLAGS = @SERVER_LDFLAGS@

service_get_rootcause_eventids_CFLAGS = $(SERVICE_WRAP_FUNCS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_status

service_get_status_SOURCES = \
	service_get_status.c \
	mock_service.c \
	mock_service.h

service_get_status_LDADD = $(COMMON_LIBS)
service_get_status_LDADD += @SERVER_LIBS@
service_get_status_LDFLAGS = @SERVER_LDFLAGS@

service_get_status_CFLAGS = $(SERVICE_WRAP_FUNCS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_main_status

service_get_main_status_SOURCES = \
	service_get_main_status.c \
	mock_service.c \
	mock_service.h

service_get_main_status_LDADD = $(COMMON_LIBS)
service_get_main_status_LDADD += @SERVER_LIBS@
service_get_main_status_LDFLAGS = @SERVER_LDFLAGS@

service_get_main_status_CFLAGS = $(SERVICE_WRAP_FUNCS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_rule_status

service_get_rule_status_SOURCES = \
	service_get_rule_status.c \
	mock_service.c \
	mock_service.h

service_get_rule_status_LDADD = $(COMMON_LIBS)
service_get_rule_status_LDADD += @SERVER_LIBS@
service_get_rule_status_LDFLAGS = @SERVER_LDFLAGS@

service_get_rule_status_CFLAGS = $(SERVICE_WRAP_FUNCS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

endif
