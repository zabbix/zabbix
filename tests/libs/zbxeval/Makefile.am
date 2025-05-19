include ../Makefile.include

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
	zbx_eval_parse_query \
	zbx_eval_get_functionids \
	zbx_eval_get_functionids_ordered \
	zbx_eval_check_timer_functions \
	zbx_eval_extract_item_refs \
	zbx_eval_replace_functionid \
	zbx_eval_copy \
	zbx_eval_format_function_error \
	zbx_get_serialized_expression_functionids \
	zbx_eval_deserialize_dyn

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

#zbx_eval_get_functionids

zbx_eval_get_functionids_SOURCES = \
	zbx_eval_get_functionids.c \
	mock_eval.c mock_eval.h

zbx_eval_get_functionids_LDADD = $(EVAL_LIBS)

zbx_eval_get_functionids_LDADD += @SERVER_LIBS@

zbx_eval_get_functionids_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_get_functionids_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_get_functionids_ordered

zbx_eval_get_functionids_ordered_SOURCES = \
	zbx_eval_get_functionids_ordered.c \
	mock_eval.c mock_eval.h

zbx_eval_get_functionids_ordered_LDADD = $(EVAL_LIBS)

zbx_eval_get_functionids_ordered_LDADD += @SERVER_LIBS@

zbx_eval_get_functionids_ordered_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_get_functionids_ordered_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_check_timer_functions

zbx_eval_check_timer_functions_SOURCES = \
	zbx_eval_check_timer_functions.c \
	mock_eval.c mock_eval.h

zbx_eval_check_timer_functions_LDADD = $(EVAL_LIBS)

zbx_eval_check_timer_functions_LDADD += @SERVER_LIBS@

zbx_eval_check_timer_functions_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_check_timer_functions_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_extract_item_refs

zbx_eval_extract_item_refs_SOURCES = \
	zbx_eval_extract_item_refs.c \
	mock_eval.c mock_eval.h

zbx_eval_extract_item_refs_LDADD = $(EVAL_LIBS)

zbx_eval_extract_item_refs_LDADD += @SERVER_LIBS@

zbx_eval_extract_item_refs_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_extract_item_refs_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_replace_functionid

zbx_eval_replace_functionid_SOURCES = \
	zbx_eval_replace_functionid.c \
	mock_eval.c mock_eval.h

zbx_eval_replace_functionid_LDADD = $(EVAL_LIBS)

zbx_eval_replace_functionid_LDADD += @SERVER_LIBS@

zbx_eval_replace_functionid_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_replace_functionid_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_copy

zbx_eval_copy_SOURCES = \
	zbx_eval_copy.c \
	mock_eval.c mock_eval.h

zbx_eval_copy_LDADD = $(EVAL_LIBS)

zbx_eval_copy_LDADD += @SERVER_LIBS@

zbx_eval_copy_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_copy_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_format_function_error

zbx_eval_format_function_error_SOURCES = \
	zbx_eval_format_function_error.c \
	mock_eval.c mock_eval.h

zbx_eval_format_function_error_LDADD = $(EVAL_LIBS)

zbx_eval_format_function_error_LDADD += @SERVER_LIBS@

zbx_eval_format_function_error_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_format_function_error_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_get_serialized_expression_functionids

zbx_get_serialized_expression_functionids_SOURCES = \
	zbx_get_serialized_expression_functionids.c \
	mock_eval.c mock_eval.h

zbx_get_serialized_expression_functionids_LDADD = $(EVAL_LIBS)

zbx_get_serialized_expression_functionids_LDADD += @SERVER_LIBS@

zbx_get_serialized_expression_functionids_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_get_serialized_expression_functionids_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_deserialize_dyn

zbx_eval_deserialize_dyn_SOURCES = \
	zbx_eval_deserialize_dyn.c \
	mock_eval.c mock_eval.h

zbx_eval_deserialize_dyn_LDADD = $(EVAL_LIBS)

zbx_eval_deserialize_dyn_LDADD += @SERVER_LIBS@

zbx_eval_deserialize_dyn_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_deserialize_dyn_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
