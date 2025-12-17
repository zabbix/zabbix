include ../Makefile.include

if SERVER
SERVER_tests = prometheus_filter_init zbx_prometheus_pattern zbx_prometheus_to_json prometheus_parse_row

noinst_PROGRAMS = $(SERVER_tests)

PROMETHEUS_LIBS = \
	$(top_srcdir)/src/libs/zbxprometheus/libzbxprometheus.a \
	$(EVAL_DEPS) \
	$(EXPR_DEPS) \
	$(JSON_DEPS) \
	$(REGEXP_DEPS) \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

prometheus_filter_init_SOURCES = \
	prometheus_filter_init.c prometheus_test.c

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
	prometheus_parse_row.c prometheus_test.c

prometheus_parse_row_CFLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

prometheus_parse_row_LDADD = $(PROMETHEUS_LIBS) @SERVER_LIBS@
prometheus_parse_row_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

endif
