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
	zbx_eval_deserialize_dyn \
	zbx_eval_substitute_macros \
	zbx_eval_query_subtitute_user_macros \
	zbx_eval_calc_kurtosis \
	zbx_eval_calc_mad \
	zbx_eval_calc_skewness \
	zbx_eval_calc_stddevpop \
	zbx_eval_calc_stddevsamp \
	zbx_eval_calc_sumofsquares \
	zbx_eval_calc_varpop \
	zbx_eval_calc_varsamp \
	zbx_eval_calc_avg \
	zbx_eval_calc_min \
	zbx_eval_calc_max \
	zbx_eval_calc_sum

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

#zbx_eval_substitute_macros

zbx_eval_substitute_macros_SOURCES = \
	zbx_eval_substitute_macros.c \
	mock_eval.c mock_eval.h

zbx_eval_substitute_macros_LDADD = $(EVAL_LIBS)

zbx_eval_substitute_macros_LDADD += @SERVER_LIBS@

zbx_eval_substitute_macros_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_substitute_macros_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_query_subtitute_user_macros

zbx_eval_query_subtitute_user_macros_SOURCES = \
	zbx_eval_query_subtitute_user_macros.c \
	mock_eval.c mock_eval.h

zbx_eval_query_subtitute_user_macros_LDADD = $(EVAL_LIBS)

zbx_eval_query_subtitute_user_macros_LDADD += @SERVER_LIBS@

zbx_eval_query_subtitute_user_macros_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_query_subtitute_user_macros_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_kurtosis

zbx_eval_calc_kurtosis_SOURCES = \
	zbx_eval_calc_kurtosis.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_kurtosis_LDADD = $(EVAL_LIBS)

zbx_eval_calc_kurtosis_LDADD += @SERVER_LIBS@

zbx_eval_calc_kurtosis_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_kurtosis_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_mad

zbx_eval_calc_mad_SOURCES = \
	zbx_eval_calc_mad.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_mad_LDADD = $(EVAL_LIBS)

zbx_eval_calc_mad_LDADD += @SERVER_LIBS@

zbx_eval_calc_mad_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_mad_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_skewness

zbx_eval_calc_skewness_SOURCES = \
	zbx_eval_calc_skewness.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_skewness_LDADD = $(EVAL_LIBS)

zbx_eval_calc_skewness_LDADD += @SERVER_LIBS@

zbx_eval_calc_skewness_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_skewness_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_stddevpop

zbx_eval_calc_stddevpop_SOURCES = \
	zbx_eval_calc_stddevpop.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_stddevpop_LDADD = $(EVAL_LIBS)

zbx_eval_calc_stddevpop_LDADD += @SERVER_LIBS@

zbx_eval_calc_stddevpop_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_stddevpop_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_stddevsamp

zbx_eval_calc_stddevsamp_SOURCES = \
	zbx_eval_calc_stddevsamp.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_stddevsamp_LDADD = $(EVAL_LIBS)

zbx_eval_calc_stddevsamp_LDADD += @SERVER_LIBS@

zbx_eval_calc_stddevsamp_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_stddevsamp_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_sumofsquares

zbx_eval_calc_sumofsquares_SOURCES = \
	zbx_eval_calc_sumofsquares.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_sumofsquares_LDADD = $(EVAL_LIBS)

zbx_eval_calc_sumofsquares_LDADD += @SERVER_LIBS@

zbx_eval_calc_sumofsquares_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_sumofsquares_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_varpop

zbx_eval_calc_varpop_SOURCES = \
	zbx_eval_calc_varpop.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_varpop_LDADD = $(EVAL_LIBS)

zbx_eval_calc_varpop_LDADD += @SERVER_LIBS@

zbx_eval_calc_varpop_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_varpop_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_varsamp

zbx_eval_calc_varsamp_SOURCES = \
	zbx_eval_calc_varsamp.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_varsamp_LDADD = $(EVAL_LIBS)

zbx_eval_calc_varsamp_LDADD += @SERVER_LIBS@

zbx_eval_calc_varsamp_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_varsamp_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_avg

zbx_eval_calc_avg_SOURCES = \
	zbx_eval_calc_avg.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_avg_LDADD = $(EVAL_LIBS)

zbx_eval_calc_avg_LDADD += @SERVER_LIBS@

zbx_eval_calc_avg_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_avg_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_min

zbx_eval_calc_min_SOURCES = \
	zbx_eval_calc_min.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_min_LDADD = $(EVAL_LIBS)

zbx_eval_calc_min_LDADD += @SERVER_LIBS@

zbx_eval_calc_min_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_min_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_max

zbx_eval_calc_max_SOURCES = \
	zbx_eval_calc_max.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_max_LDADD = $(EVAL_LIBS)

zbx_eval_calc_max_LDADD += @SERVER_LIBS@

zbx_eval_calc_max_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_max_CFLAGS = $(COMMON_COMPILER_FLAGS)

#zbx_eval_calc_sum

zbx_eval_calc_sum_SOURCES = \
	zbx_eval_calc_sum.c \
	mock_eval.c mock_eval.h

zbx_eval_calc_sum_LDADD = $(EVAL_LIBS)

zbx_eval_calc_sum_LDADD += @SERVER_LIBS@

zbx_eval_calc_sum_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_eval_calc_sum_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
