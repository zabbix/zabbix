if SERVER
SERVER_tests = prometheus_filter_init zbx_prometheus_pattern zbx_prometheus_to_json prometheus_parse_row

noinst_PROGRAMS = $(SERVER_tests)

# zbxprometheus depends on zbxalgo, zbxeval, zbxexpr, zbxjson, zbxnum, zbxregexp, zbxstr and zbxcommon
#
# 1) mockdata needs zbxtime, zbxalgo, zbxstr, zbxnum and zbxcommon
# 2) mocktest needs zbxnix, which also needs zbxlog, zbxnum, zbxthreads, zbxcomms and zbxcommon

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

MOCK_TEST_DEPS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

EXPR_LIBS = \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

VARIANT_LIBS = \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

MUTEX_LIBS = \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

CFG_LIBS = \
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

LOG_LIBS = \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(MUTEX_LIBS) \
	$(CFG_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

REGEXP_LIBS = \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(LOG_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

JSON_LIBS = \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(REGEXP_LIBS) \
	$(VARIANT_LIBS) \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(EXPR_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a


XML_LIBS = \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(JSON_LIBS) \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

EVAL_LIBS = \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(EXPR_LIBS) \
	$(JSON_LIBS) \
	$(VARIANT_LIBS) \
	$(XML_LIBS) \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

PROMETHEUS_LIBS = \
	$(top_srcdir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(EVAL_LIBS) \
	$(EXPR_LIBS) \
	$(JSON_LIBS) \
	$(REGEXP_LIBS) \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

prometheus_filter_init_SOURCES = \
	prometheus_filter_init.c

prometheus_filter_init_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

prometheus_filter_init_LDADD = $(PROMETHEUS_LIBS) @SERVER_LIBS@
prometheus_filter_init_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_prometheus_pattern_SOURCES = \
	zbx_prometheus_pattern.c

zbx_prometheus_pattern_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

zbx_prometheus_pattern_LDADD = $(PROMETHEUS_LIBS) @SERVER_LIBS@
zbx_prometheus_pattern_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_prometheus_to_json_SOURCES = \
	zbx_prometheus_to_json.c

zbx_prometheus_to_json_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

zbx_prometheus_to_json_LDADD = $(PROMETHEUS_LIBS) @SERVER_LIBS@
zbx_prometheus_to_json_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

prometheus_parse_row_SOURCES = \
	prometheus_parse_row.c

prometheus_parse_row_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

prometheus_parse_row_LDADD = $(PROMETHEUS_LIBS) @SERVER_LIBS@
prometheus_parse_row_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

endif
