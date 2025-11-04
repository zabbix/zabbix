if SERVER
SERVER_tests = zbx_lld_hgsets_test \
		lld_entry_create \
		lld_compare_entries \
		zbx_substitute_lld_macros

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h
	
LLD_WRAP_FUNCS = \
	-Wl,--wrap=zbx_preprocess_item_value \
	-Wl,--wrap=zbx_preprocessor_flush

LLD_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_builddir)/src/libs/zbxcalc/libzbxcalc.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_builddir)/src/libs/zbxpgservice/libzbxpgservice.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxevent/libzbxevent.a \
	$(top_srcdir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_builddir)/src/libs/zbxkvs/libzbxkvs.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxinterface/libzbxinterface.a \
	$(top_srcdir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_srcdir)/src/libs/zbxescalations/libzbxescalations.a \
	$(top_srcdir)/src/libs/zbxrtc/libzbxrtc_service.a \
	$(top_srcdir)/src/libs/zbxrtc/libzbxrtc.a \
	$(top_srcdir)/src/libs/zbxdiag/libzbxdiag.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxconnector/libzbxconnector.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxpreprocbase/libzbxpreprocbase.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxexport/libzbxexport.a \
	$(top_srcdir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdummy.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

zbx_lld_hgsets_test_SOURCES = \
	../../../src/zabbix_server/lld/lld_common.c \
	../../../src/zabbix_server/lld/lld_graph.c \
	../../../src/zabbix_server/lld/lld_audit.c \
	../../../src/zabbix_server/lld/lld_item.c \
	../../../src/zabbix_server/lld/lld_trigger.c \
	../../../src/zabbix_server/lld/lld_macro.c \
	../../../src/zabbix_server/lld/lld_entry.c \
	../../../src/zabbix_server/lld/lld_rule.c \
	../../../src/zabbix_server/lld/lld.c \
	zbx_lld_hgsets_test.c \
	mock_lld.c \
	../../zbxmockexit.c \
	../../zbxmockdb.c \
	../../zbxmockdata.c \
	../../zbxmocklog.c \
	../../zbxmockfile.c \
	../../zbxmockdir.c

zbx_lld_hgsets_test_LDADD = $(LLD_LIBS)
zbx_lld_hgsets_test_LDADD += @SERVER_LIBS@
zbx_lld_hgsets_test_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(LLD_WRAP_FUNCS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_lld_hgsets_test_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
	
lld_entry_create_SOURCES = \
	lld_entry_create.c \
	mock_lld.c \
	../../../src/zabbix_server/lld/lld_common.c \
	../../../src/zabbix_server/lld/lld_graph.c \
	../../../src/zabbix_server/lld/lld_audit.c \
	../../../src/zabbix_server/lld/lld_item.c \
	../../../src/zabbix_server/lld/lld_trigger.c \
	../../../src/zabbix_server/lld/lld_macro.c \
	../../../src/zabbix_server/lld/lld_host.c \
	../../../src/zabbix_server/lld/lld_rule.c \
	../../../src/zabbix_server/lld/lld.c \
	../../zbxmockexit.c \
	../../zbxmockdb.c \
	../../zbxmockdata.c \
	../../zbxmocklog.c \
	../../zbxmockfile.c \
	../../zbxmockdir.c

lld_entry_create_LDADD = $(LLD_LIBS)
lld_entry_create_LDADD += @SERVER_LIBS@
lld_entry_create_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) $(LLD_WRAP_FUNCS)

lld_entry_create_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

lld_compare_entries_SOURCES = \
	lld_compare_entries.c \
	mock_lld.c \
	../../../src/zabbix_server/lld/lld_common.c \
	../../../src/zabbix_server/lld/lld_graph.c \
	../../../src/zabbix_server/lld/lld_audit.c \
	../../../src/zabbix_server/lld/lld_item.c \
	../../../src/zabbix_server/lld/lld_trigger.c \
	../../../src/zabbix_server/lld/lld_macro.c \
	../../../src/zabbix_server/lld/lld_host.c \
	../../../src/zabbix_server/lld/lld_rule.c \
	../../../src/zabbix_server/lld/lld.c \
	../../zbxmockexit.c \
	../../zbxmockdb.c \
	../../zbxmockdata.c \
	../../zbxmocklog.c \
	../../zbxmockfile.c \
	../../zbxmockdir.c

lld_compare_entries_LDADD = $(LLD_LIBS)
lld_compare_entries_LDADD += @SERVER_LIBS@
lld_compare_entries_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) $(LLD_WRAP_FUNCS)

lld_compare_entries_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

zbx_substitute_lld_macros_SOURCES = \
	../../../src/zabbix_server/lld/lld_common.c \
	../../../src/zabbix_server/lld/lld_graph.c \
	../../../src/zabbix_server/lld/lld_audit.c \
	../../../src/zabbix_server/lld/lld_item.c \
	../../../src/zabbix_server/lld/lld_trigger.c \
	../../../src/zabbix_server/lld/lld_macro.c \
	../../../src/zabbix_server/lld/lld_host.c \
	../../../src/zabbix_server/lld/lld_rule.c \
	../../../src/zabbix_server/lld/lld.c \
	zbx_substitute_lld_macros.c \
	mock_lld.c \
	../../zbxmockexit.c \
	../../zbxmockdb.c \
	../../zbxmockdata.c \
	../../zbxmocklog.c \
	../../zbxmockfile.c \
	../../zbxmockdir.c	

zbx_substitute_lld_macros_LDADD = $(LLD_LIBS)
zbx_substitute_lld_macros_LDADD += @SERVER_LIBS@
zbx_substitute_lld_macros_LDFLAGS = @SERVER_LDFLAGS@ $(LLD_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_substitute_lld_macros_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

endif
