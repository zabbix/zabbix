if SERVER
SERVER_tests = \
	zbx_eval_parse_expression \
	zbx_eval_serialize \
	zbx_eval_compose_expression \
	zbx_eval_execute \
	zbx_eval_execute_ext \
	zbx_eval_get_constant \
	zbx_eval_prepare_filter \
	zbx_eval_get_group_filter \
	zbx_eval_parse_query
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxeval/libzbxeval.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

if HAVE_IPMI
COMMON_LIB_FILES += $(top_srcdir)/src/libs/zbxipmi/libzbxipmi.a
endif

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

zbx_eval_parse_expression_SOURCES = \
	zbx_eval_parse_expression.c \
	mock_eval.c mock_eval.h

zbx_eval_parse_expression_LDADD = $(COMMON_LIB_FILES)

zbx_eval_parse_expression_LDADD += @SERVER_LIBS@

zbx_eval_parse_expression_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_parse_expression_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_serialize_SOURCES = \
	zbx_eval_serialize.c

zbx_eval_serialize_LDADD = $(COMMON_LIB_FILES)

zbx_eval_serialize_LDADD += @SERVER_LIBS@

zbx_eval_serialize_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_serialize_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_compose_expression_SOURCES = \
	zbx_eval_compose_expression.c \
	mock_eval.c mock_eval.h

zbx_eval_compose_expression_LDADD = $(COMMON_LIB_FILES)

zbx_eval_compose_expression_LDADD += @SERVER_LIBS@

zbx_eval_compose_expression_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_compose_expression_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_execute_SOURCES = \
	zbx_eval_execute.c \
	mock_eval.c mock_eval.h

zbx_eval_execute_LDADD = $(COMMON_LIB_FILES)

zbx_eval_execute_LDADD += @SERVER_LIBS@

zbx_eval_execute_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_execute_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_execute_ext_SOURCES = \
	zbx_eval_execute_ext.c \
	mock_eval.c mock_eval.h

zbx_eval_execute_ext_LDADD = $(COMMON_LIB_FILES)

zbx_eval_execute_ext_LDADD += @SERVER_LIBS@

zbx_eval_execute_ext_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_execute_ext_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_get_constant_SOURCES = \
	zbx_eval_get_constant.c \
	mock_eval.c mock_eval.h

zbx_eval_get_constant_LDADD = $(COMMON_LIB_FILES)

zbx_eval_get_constant_LDADD += @SERVER_LIBS@

zbx_eval_get_constant_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_get_constant_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_prepare_filter_SOURCES = \
	zbx_eval_prepare_filter.c \
	mock_eval.c mock_eval.h

zbx_eval_prepare_filter_LDADD = $(COMMON_LIB_FILES)

zbx_eval_prepare_filter_LDADD += @SERVER_LIBS@

zbx_eval_prepare_filter_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_prepare_filter_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_get_group_filter_SOURCES = \
	zbx_eval_get_group_filter.c \
	mock_eval.c mock_eval.h

zbx_eval_get_group_filter_LDADD = $(COMMON_LIB_FILES)

zbx_eval_get_group_filter_LDADD += @SERVER_LIBS@

zbx_eval_get_group_filter_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_get_group_filter_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_parse_query_SOURCES = \
	zbx_eval_parse_query.c \
	mock_eval.c mock_eval.h

zbx_eval_parse_query_LDADD = $(COMMON_LIB_FILES)

zbx_eval_parse_query_LDADD += @SERVER_LIBS@

zbx_eval_parse_query_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_parse_query_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
