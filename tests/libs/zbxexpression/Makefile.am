include ../Makefile.include

if SERVER
SERVER_tests = \
	zbx_evaluate \
	zbx_evaluate_unknown \
	zbx_evaluate_function \
	zbx_STL \
	zbx_get_percentage_of_deviations_in_stl_remainder \
	zbx_calculate_macro_function \
	zbx_substitute_simple_macros \
	evaluate_value_by_map \
	zbx_format_value
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


zbx_calculate_macro_function_SOURCES = \
	zbx_calculate_macro_function.c \
	$(COMMON_SRC_FILES)

zbx_calculate_macro_function_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_calculate_macro_function_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_calculate_macro_function_LDADD += @SERVER_LIBS@

zbx_calculate_macro_function_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_calculate_macro_function_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxexpression

zbx_substitute_simple_macros_SOURCES = \
	zbx_substitute_simple_macros.c \
	$(COMMON_SRC_FILES)

zbx_substitute_simple_macros_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_substitute_simple_macros_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_substitute_simple_macros_LDADD += @SERVER_LIBS@

zbx_substitute_simple_macros_LDFLAGS = @SERVER_LDFLAGS@ \
			-Wl,--wrap=zbx_dc_open_user_macros \
			-Wl,--wrap=zbx_dc_close_user_macros \
			-Wl,--wrap=zbx_dc_get_user_macro \
			-Wl,--wrap=zbx_db_with_trigger_itemid \
			-Wl,--wrap=zbx_db_trigger_get_all_hostids \
			$(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_substitute_simple_macros_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxexpression

zbx_evaluate_function_SOURCES = \
	zbx_evaluate_function.c \
	$(COMMON_SRC_FILES)

zbx_evaluate_function_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_evaluate_function_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_evaluate_function_LDADD += @SERVER_LIBS@

zbx_evaluate_function_LDFLAGS = @SERVER_LDFLAGS@ \
	-Wl,--wrap=zbx_baseline_get_data \
	-Wl,--wrap=zbx_recalc_time_period \
	$(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_STL_SOURCES = \
	zbx_STL.c \
	$(COMMON_SRC_FILES)

zbx_STL_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_STL_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_STL_LDADD += @SERVER_LIBS@

zbx_STL_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_get_percentage_of_deviations_in_stl_remainder_SOURCES = \
	zbx_get_percentage_of_deviations_in_stl_remainder.c \
	$(COMMON_SRC_FILES)

zbx_get_percentage_of_deviations_in_stl_remainder_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_get_percentage_of_deviations_in_stl_remainder_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_get_percentage_of_deviations_in_stl_remainder_LDADD += @SERVER_LIBS@

zbx_get_percentage_of_deviations_in_stl_remainder_LDFLAGS = @SERVER_LDFLAGS@ $(VALUECACHE_WRAP_FUNCS) $(CMOCKA_LDFLAGS)\
	$(YAML_LDFLAGS) $(TLS_LDFLAGS)

evaluate_value_by_map_SOURCES = \
	evaluate_value_by_map.c \
	$(COMMON_SRC_FILES)

evaluate_value_by_map_LDADD = $(EVALUATE_LIB_FILES) $(TLS_LIBS)

evaluate_value_by_map_LDADD += @SERVER_LIBS@

evaluate_value_by_map_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

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

zbx_evaluate_function_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

zbx_STL_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

zbx_get_percentage_of_deviations_in_stl_remainder_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory

evaluate_value_by_map_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
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

endif

