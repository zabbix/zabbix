if SERVER
SERVER_tests = zbx_trapper_preproc_test_run

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

TRAPPER_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxpreproc/libzbxpreproc.a \
	$(top_srcdir)/src/libs/zbxtimekeeper/libzbxtimekeeper.a \
	$(top_srcdir)/src/libs/zbxembed/libzbxembed.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxipcservice/libzbxipcservice.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxembed/libzbxembed.a \
	$(top_srcdir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
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
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

zbx_trapper_preproc_test_run_SOURCES = \
	zbx_trapper_preproc_test_run.c \
	../../../src/zabbix_server/trapper/trapper_preproc.c  \
	../../../src/zabbix_server/trapper/trapper_auth.c  \
	../../zbxmockjson.c

zbx_trapper_preproc_test_run_LDADD = $(TRAPPER_LIBS)
zbx_trapper_preproc_test_run_LDADD += @SERVER_LIBS@
zbx_trapper_preproc_test_run_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS) \
	-Wl,--wrap=zbx_preprocessor_test \
	-Wl,--wrap=zbx_db_get_user_by_active_session \
	-Wl,--wrap=zbx_db_get_user_by_auth_token \
	-Wl,--wrap=zbx_user_init \
	-Wl,--wrap=zbx_user_free \
	-Wl,--wrap=zbx_init_agent_result \
	-Wl,--wrap=zbx_free_agent_result

zbx_trapper_preproc_test_run_CFLAGS = \
	-I@top_srcdir@/tests -I@top_srcdir@/src  @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif

