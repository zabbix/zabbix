include ../Makefile.include
include ../../mocks/valuecache/symbol-wraps.mk

if SERVER
SERVER_tests = \
	zbx_evaluate \
	zbx_evaluate_unknown \
	zbx_evaluate_function \
	zbx_STL \
	zbx_get_percentage_of_deviations_in_stl_remainder \
	evaluate_value_by_map
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
EVALUATE_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxalerter/libzbxalerter.a \
	$(top_srcdir)/src/libs/zbxdbsyncer/libzbxdbsyncer.a \
	$(top_srcdir)/src/libs/zbxpoller/libzbxpoller.a \
	$(top_srcdir)/src/libs/zbxscripts/libzbxscripts.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_builddir)/src/libs/zbxpgservice/libzbxpgservice.a \
	$(top_builddir)/src/libs/zbxcalc/libzbxcalc.a \
	$(top_srcdir)/src/libs/zbxcachehistory/libzbxcachehistory.a \
	$(top_srcdir)/src/libs/zbxcachevalue/libzbxcachevalue.a \
	$(top_srcdir)/src/libs/zbxpreprocbase/libzbxpreprocbase.a \
	$(top_srcdir)/src/libs/zbxescalations/libzbxescalations.a \
	$(top_srcdir)/src/libs/zbxrtc/libzbxrtc_service.a \
	$(top_srcdir)/src/libs/zbxrtc/libzbxrtc.a \
	$(top_srcdir)/src/libs/zbxdiag/libzbxdiag.a \
	$(top_srcdir)/src/libs/zbxevent/libzbxevent.a \
	$(top_srcdir)/src/libs/zbxcacheconfig/libzbxcacheconfig.a \
	$(top_builddir)/src/libs/zbxpgservice/libzbxpgservice.a \
	$(top_srcdir)/src/libs/zbxpreprocbase/libzbxpreprocbase.a \
	$(top_srcdir)/src/libs/zbxtrends/libzbxtrends.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/src/libs/zbxtimekeeper/libzbxtimekeeper.a \
	$(top_srcdir)/src/libs/zbxmedia/libzbxmedia.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxicmpping/libzbxicmpping.a \
	$(top_srcdir)/src/libs/zbxdbupgrade/libzbxdbupgrade.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxavailability/libzbxavailability.a \
	$(top_srcdir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(top_srcdir)/src/libs/zbxconnector/libzbxconnector.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxtasks/libzbxtasks.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxservice/libzbxservice.a \
	$(top_srcdir)/src/libs/zbxexport/libzbxexport.a \
	$(top_srcdir)/src/libs/zbxdbwrap/libzbxdbwrap.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxvault/libzbxvault.a \
	$(top_builddir)/src/libs/zbxkvs/libzbxkvs.a \
	$(top_srcdir)/src/libs/zbxcurl/libzbxcurl.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxparam/libzbxparam.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxinterface/libzbxinterface.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxagentget/libzbxagentget.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

if HAVE_IPMI
EVALUATE_LIB_FILES += $(top_srcdir)/src/libs/zbxipmi/libzbxipmi.a
endif

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

zbx_evaluate_SOURCES = \
	zbx_evaluate.c \
	$(COMMON_SRC_FILES)

zbx_evaluate_LDADD = \
	$(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_evaluate_LDADD += @SERVER_LIBS@

zbx_evaluate_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_evaluate_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)


zbx_evaluate_unknown_SOURCES = \
	zbx_evaluate_unknown.c \
	$(COMMON_SRC_FILES)

zbx_evaluate_unknown_LDADD = \
	$(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_evaluate_unknown_LDADD += @SERVER_LIBS@

zbx_evaluate_unknown_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_evaluate_unknown_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)


zbx_evaluate_function_SOURCES = \
	zbx_evaluate_function.c \
	$(COMMON_SRC_FILES)

zbx_evaluate_function_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

zbx_evaluate_function_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_evaluate_function_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_evaluate_function_LDADD += @SERVER_LIBS@

zbx_evaluate_function_LDFLAGS = @SERVER_LDFLAGS@ \
	$(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)


zbx_STL_SOURCES = \
	zbx_STL.c \
	$(COMMON_SRC_FILES)

zbx_STL_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

zbx_STL_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_STL_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_STL_LDADD += @SERVER_LIBS@

zbx_STL_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)


zbx_get_percentage_of_deviations_in_stl_remainder_SOURCES = \
	zbx_get_percentage_of_deviations_in_stl_remainder.c \
	$(COMMON_SRC_FILES)

zbx_get_percentage_of_deviations_in_stl_remainder_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

zbx_get_percentage_of_deviations_in_stl_remainder_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_get_percentage_of_deviations_in_stl_remainder_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_get_percentage_of_deviations_in_stl_remainder_LDADD += @SERVER_LIBS@

zbx_get_percentage_of_deviations_in_stl_remainder_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS)\
	$(YAML_LDFLAGS) $(TLS_LDFLAGS)


evaluate_value_by_map_SOURCES = \
	evaluate_value_by_map.c \
	$(COMMON_SRC_FILES)

evaluate_value_by_map_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

evaluate_value_by_map_LDADD = $(EVALUATE_LIB_FILES) $(TLS_LIBS)

evaluate_value_by_map_LDADD += @SERVER_LIBS@

evaluate_value_by_map_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

endif
