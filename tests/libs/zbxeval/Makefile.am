include ../Makefile.common

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

EVAL_LIBS = \
	$(EVAL_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

if HAVE_IPMI
	EVAL_LIBS += $(top_srcdir)/src/libs/zbxipmi/libzbxipmi.a
endif

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

zbx_eval_parse_expression_SOURCES = \
	zbx_eval_parse_expression.c \
	mock_eval.c mock_eval.h

zbx_eval_parse_expression_LDADD = $(EVAL_LIBS)

zbx_eval_parse_expression_LDADD += @SERVER_LIBS@

zbx_eval_parse_expression_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_parse_expression_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_serialize_SOURCES = \
	zbx_eval_serialize.c

zbx_eval_serialize_LDADD = $(EVAL_LIBS)

zbx_eval_serialize_LDADD += @SERVER_LIBS@

zbx_eval_serialize_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_serialize_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_compose_expression_SOURCES = \
	zbx_eval_compose_expression.c \
	mock_eval.c mock_eval.h

zbx_eval_compose_expression_LDADD = $(EVAL_LIBS)

zbx_eval_compose_expression_LDADD += @SERVER_LIBS@

zbx_eval_compose_expression_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_compose_expression_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_execute_SOURCES = \
	zbx_eval_execute.c \
	mock_eval.c mock_eval.h

zbx_eval_execute_LDADD = $(EVAL_LIBS)

zbx_eval_execute_LDADD += @SERVER_LIBS@

zbx_eval_execute_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_execute_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_execute_ext_SOURCES = \
	zbx_eval_execute_ext.c \
	mock_eval.c mock_eval.h

zbx_eval_execute_ext_LDADD = $(EVAL_LIBS)

zbx_eval_execute_ext_LDADD += @SERVER_LIBS@

zbx_eval_execute_ext_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_execute_ext_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_get_constant_SOURCES = \
	zbx_eval_get_constant.c \
	mock_eval.c mock_eval.h

zbx_eval_get_constant_LDADD = $(EVAL_LIBS)

zbx_eval_get_constant_LDADD += @SERVER_LIBS@

zbx_eval_get_constant_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_get_constant_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_prepare_filter_SOURCES = \
	zbx_eval_prepare_filter.c \
	mock_eval.c mock_eval.h

zbx_eval_prepare_filter_LDADD = $(EVAL_LIBS)

zbx_eval_prepare_filter_LDADD += @SERVER_LIBS@

zbx_eval_prepare_filter_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_prepare_filter_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_get_group_filter_SOURCES = \
	zbx_eval_get_group_filter.c \
	mock_eval.c mock_eval.h

zbx_eval_get_group_filter_LDADD = $(EVAL_LIBS)

zbx_eval_get_group_filter_LDADD += @SERVER_LIBS@

zbx_eval_get_group_filter_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_get_group_filter_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_eval_parse_query_SOURCES = \
	zbx_eval_parse_query.c \
	mock_eval.c mock_eval.h

zbx_eval_parse_query_LDADD = $(EVAL_LIBS)

zbx_eval_parse_query_LDADD += @SERVER_LIBS@

zbx_eval_parse_query_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_parse_query_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
