if SERVER
SERVER_tests = zbx_item_preproc
SERVER_tests += item_preproc_csv_to_json

if HAVE_LIBXML2
SERVER_tests +=	item_preproc_xpath
endif

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

JSON_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxpreproc/libzbxpreproc.a \
	$(top_srcdir)/src/libs/zbxpreprocbase/libzbxpreprocbase.a \
	$(top_srcdir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_srcdir)/src/libs/zbxescalations/libzbxescalations.a \
	$(top_srcdir)/src/libs/zbxrtc/libzbxrtc_service.a \
	$(top_srcdir)/src/libs/zbxrtc/libzbxrtc.a \
	$(top_srcdir)/src/libs/zbxdiag/libzbxdiag.a \
	$(top_srcdir)/src/libs/zbxexport/libzbxexport.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_srcdir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_srcdir)/src/libs/zbxpgservice/libzbxpgservice.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxtimekeeper/libzbxtimekeeper.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_srcdir)/src/libs/zbxconnector/libzbxconnector.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxevent/libzbxevent.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_builddir)/src/libs/zbxkvs/libzbxkvs.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxtimekeeper/libzbxtimekeeper.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxembed/libzbxembed.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxinterface/libzbxinterface.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/tests/libzbxmockdummy.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

zbx_item_preproc_SOURCES = \
	zbx_item_preproc.c \
	configcache_mock.c

zbx_item_preproc_LDADD = $(JSON_LIBS)

zbx_item_preproc_LDADD += @SERVER_LIBS@
zbx_item_preproc_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) \
	-Wl,--wrap=zbx_dc_expand_user_and_func_macros_from_cache

zbx_item_preproc_CFLAGS = -I@top_srcdir@/tests -I@top_srcdir@/src @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) \
	$(TLS_CFLAGS)

item_preproc_xpath_SOURCES = \
	item_preproc_xpath.c \
	configcache_mock.c \
	$(COMMON_SRC_FILES)

item_preproc_xpath_LDADD = $(JSON_LIBS)

item_preproc_xpath_LDADD += @SERVER_LIBS@
item_preproc_xpath_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLD_LDFLAGS) \
	-Wl,--wrap=zbx_dc_expand_user_and_func_macros_from_cache

item_preproc_xpath_CFLAGS = -I@top_srcdir@/tests -I@top_srcdir@/src @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) \
	$(TLS_CFLAGS)

item_preproc_csv_to_json_SOURCES = \
	item_preproc_csv_to_json.c \
	configcache_mock.c \
	$(COMMON_SRC_FILES)

item_preproc_csv_to_json_LDADD = $(JSON_LIBS)

item_preproc_csv_to_json_LDADD += @SERVER_LIBS@
item_preproc_csv_to_json_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) \
	-Wl,--wrap=zbx_dc_expand_user_and_func_macros_from_cache

item_preproc_csv_to_json_CFLAGS = -I@top_srcdir@/tests -I@top_srcdir@/src @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) $(TLS_CFLAGS)

endif
