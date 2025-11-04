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
	$(top_srcdir)/src/zabbix_server/service/libservice_server.a \
	$(top_builddir)/src/libs/zbxcalc/libzbxcalc.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_builddir)/src/libs/zbxpgservice/libzbxpgservice.a \
	$(top_srcdir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_builddir)/src/libs/zbxexport/libzbxexport.a \
	$(top_builddir)/src/libs/zbxinterface/libzbxinterface.a \
	$(top_srcdir)/src/libs/zbxpreprocbase/libzbxpreprocbase.a \
	$(top_srcdir)/src/libs/zbxescalations/libzbxescalations.a \
	$(top_srcdir)/src/libs/zbxrtc/libzbxrtc_service.a \
	$(top_srcdir)/src/libs/zbxrtc/libzbxrtc.a \
	$(top_srcdir)/src/libs/zbxdiag/libzbxdiag.a \
	$(top_srcdir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_srcdir)/src/libs/zbxconnector/libzbxconnector.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxservice/libzbxservice.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_builddir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_builddir)/src/libs/zbxkvs/libzbxkvs.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/tests/libzbxmockdummy.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

SERVICE_WRAP_FUNCS = \
	-Wl,--wrap=zbx_dc_get_nextid \
	-Wl,--wrap=zbx_add_event \
	-Wl,--wrap=zbx_process_events \
	-Wl,--wrap=zbx_clean_events \
	-Wl,--wrap=zbx_interface_availability_is_set \
	-Wl,--wrap=zbx_rtc_subscribe \
	-Wl,--wrap=zbx_rtc_notify_finished_sync \
	-Wl,--wrap=zbx_dc_set_itservices_num
	
# service_get_rootcause_eventids

service_get_rootcause_eventids_SOURCES = \
	service_get_rootcause_eventids.c \
	mock_service.c \
	mock_service.h

service_get_rootcause_eventids_LDADD = $(COMMON_LIBS)
service_get_rootcause_eventids_LDADD += @SERVER_LIBS@
service_get_rootcause_eventids_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

service_get_rootcause_eventids_CFLAGS = $(SERVICE_WRAP_FUNCS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_status

service_get_status_SOURCES = \
	service_get_status.c \
	mock_service.c \
	mock_service.h

service_get_status_LDADD = $(COMMON_LIBS)
service_get_status_LDADD += @SERVER_LIBS@
service_get_status_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

service_get_status_CFLAGS = $(SERVICE_WRAP_FUNCS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_main_status

service_get_main_status_SOURCES = \
	service_get_main_status.c \
	mock_service.c \
	mock_service.h

service_get_main_status_LDADD = $(COMMON_LIBS)
service_get_main_status_LDADD += @SERVER_LIBS@
service_get_main_status_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

service_get_main_status_CFLAGS = $(SERVICE_WRAP_FUNCS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

# service_get_rule_status

service_get_rule_status_SOURCES = \
	service_get_rule_status.c \
	mock_service.c \
	mock_service.h

service_get_rule_status_LDADD = $(COMMON_LIBS)
service_get_rule_status_LDADD += @SERVER_LIBS@
service_get_rule_status_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

service_get_rule_status_CFLAGS = $(SERVICE_WRAP_FUNCS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/tests \
	-I@top_srcdir@/src/zabbix_server/service

endif
