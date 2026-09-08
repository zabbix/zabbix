include ../Makefile.include

if SERVER
SERVER_tests = \
	evaluate \
	evaluate_unknown \
	evaluate_function \
	evaluate_stl \
	evaluate_percentage_deviations_in_remainder \
	substitute_lld_macros \
	macro_fmttime \
	macro_functions \
	valuemaps \
	zbx_format_value \
	zbx_substitute_expression_lld_macros \
	zbx_substitute_macros_in_json_pairs

endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

EVALUATE_LIB_FILES = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

if HAVE_IPMI
EVALUATE_LIB_FILES += $(top_srcdir)/src/libs/zbxipmi/libzbxipmi.a
endif

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

evaluate_SOURCES = \
	evaluate.c \
	$(COMMON_SRC_FILES)

evaluate_LDADD = \
	$(EVALUATE_LIB_FILES) $(TLS_LIBS)

evaluate_LDADD += @SERVER_LIBS@

evaluate_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

evaluate_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)


evaluate_unknown_SOURCES = \
	evaluate_unknown.c \
	$(COMMON_SRC_FILES)

evaluate_unknown_LDADD = \
	$(EVALUATE_LIB_FILES) $(TLS_LIBS)

evaluate_unknown_LDADD += @SERVER_LIBS@

evaluate_unknown_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

evaluate_unknown_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)


substitute_lld_macros_SOURCES = \
	substitute_lld_macros.c

substitute_lld_macros_LDADD = \
	$(EVALUATE_LIB_FILES) $(TLS_LIBS)

substitute_lld_macros_LDADD += @SERVER_LIBS@

substitute_lld_macros_LDFLAGS =  @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

substitute_lld_macros_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)


macro_fmttime_SOURCES = \
	macro_fmttime.c \
	$(COMMON_SRC_FILES)

macro_fmttime_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

macro_fmttime_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

macro_fmttime_LDADD += @SERVER_LIBS@

macro_fmttime_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

macro_fmttime_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxexpression

macro_functions_SOURCES = \
	macro_functions.c \
	$(COMMON_SRC_FILES)

macro_functions_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

macro_functions_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

macro_functions_LDADD += @SERVER_LIBS@

macro_functions_LDFLAGS = @SERVER_LDFLAGS@ \
			-Wl,--wrap=expr_db_get_trigger_value \
			-Wl,--wrap=zbx_dc_open_user_macros \
			-Wl,--wrap=zbx_dc_close_user_macros \
			-Wl,--wrap=zbx_db_trigger_get_all_hostids \
			-Wl,--wrap=zbx_dc_get_user_macro \
			$(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

macro_functions_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxexpression

evaluate_function_SOURCES = \
	evaluate_function.c \
	$(COMMON_SRC_FILES)

evaluate_function_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

evaluate_function_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

evaluate_function_LDADD += @SERVER_LIBS@

evaluate_function_LDFLAGS = @SERVER_LDFLAGS@ \
	-Wl,--wrap=zbx_baseline_get_data \
	-Wl,--wrap=zbx_recalc_time_period \
	$(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

evaluate_stl_SOURCES = \
	evaluate_stl.c \
	$(COMMON_SRC_FILES)

evaluate_stl_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

evaluate_stl_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

evaluate_stl_LDADD += @SERVER_LIBS@

evaluate_stl_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

evaluate_percentage_deviations_in_remainder_SOURCES = \
	evaluate_percentage_deviations_in_remainder.c \
	$(COMMON_SRC_FILES)

evaluate_percentage_deviations_in_remainder_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

evaluate_percentage_deviations_in_remainder_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

evaluate_percentage_deviations_in_remainder_LDADD += @SERVER_LIBS@

evaluate_percentage_deviations_in_remainder_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS)  \
	$(YAML_LDFLAGS) $(TLS_LDFLAGS)

valuemaps_SOURCES = \
	valuemaps.c \
	$(COMMON_SRC_FILES)

valuemaps_LDADD = $(EVALUATE_LIB_FILES) $(TLS_LIBS)

valuemaps_LDADD += @SERVER_LIBS@

valuemaps_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

VALUECACHE_WRAP_FUNCS = \
	-Wl,--wrap=zbx_mutex_create \
	-Wl,--wrap=zbx_mutex_destroy \
	-Wl,--wrap=zbx_mem_create \
	-Wl,--wrap=__zbx_mem_malloc \
	-Wl,--wrap=__zbx_mem_realloc \
	-Wl,--wrap=__zbx_mem_free \
	-Wl,--wrap=zbx_mem_dump_stats \
	-Wl,--wrap=zbx_history_get_values \
	-Wl,--wrap=zbx_history_add_values \
	-Wl,--wrap=zbx_history_sql_init \
	-Wl,--wrap=zbx_history_elastic_init \
	-Wl,--wrap=zbx_elastic_version_extract \
	-Wl,--wrap=zbx_elastic_version_get \
	-Wl,--wrap=time \
	-Wl,--wrap=substitute_simple_macros \
	-Wl,--wrap=zbx_dc_get_data_expected_from \
	-Wl,--wrap=zbx_timespec

evaluate_function_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

evaluate_stl_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

evaluate_percentage_deviations_in_remainder_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

valuemaps_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/src/libs/zbxexpression

#zbx_format_value

zbx_format_value_SOURCES = \
	zbx_format_value.c \
	$(COMMON_SRC_FILES)

zbx_format_value_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

zbx_format_value_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_format_value_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_format_value_LDADD += @SERVER_LIBS@

zbx_format_value_LDFLAGS = @SERVER_LDFLAGS@ $(YAML_LDFLAGS) $(CMOCKA_LDFLAGS)

#zbx_substitute_expression_lld_macros

zbx_substitute_expression_lld_macros_SOURCES = \
	zbx_substitute_expression_lld_macros.c \
	lld_common.c \
	$(COMMON_SRC_FILES)

zbx_substitute_expression_lld_macros_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

zbx_substitute_expression_lld_macros_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_substitute_expression_lld_macros_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_substitute_expression_lld_macros_LDADD += @SERVER_LIBS@

zbx_substitute_expression_lld_macros_LDFLAGS = @SERVER_LDFLAGS@ $(YAML_LDFLAGS) $(CMOCKA_LDFLAGS)

#zbx_substitute_macros_in_json_pairs

zbx_substitute_macros_in_json_pairs_SOURCES = \
	zbx_substitute_macros_in_json_pairs.c \
	lld_common.c \
	$(COMMON_SRC_FILES)

zbx_substitute_macros_in_json_pairs_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

zbx_substitute_macros_in_json_pairs_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_substitute_macros_in_json_pairs_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_substitute_macros_in_json_pairs_LDADD += @SERVER_LIBS@

zbx_substitute_macros_in_json_pairs_LDFLAGS = @SERVER_LDFLAGS@ $(YAML_LDFLAGS) $(CMOCKA_LDFLAGS)

endif

