include ../Makefile.include

if SERVER
SERVER_tests = \
	zbx_db_get_expression_macro_result

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

EVALUATE_LIB_FILES = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_db_get_expression_macro_result_SOURCES = \
	zbx_db_get_expression_macro_result.c \
	$(COMMON_SRC_FILES)

zbx_db_get_expression_macro_result_LDADD = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a

zbx_db_get_expression_macro_result_LDADD += $(EVALUATE_LIB_FILES) $(TLS_LIBS)

zbx_db_get_expression_macro_result_LDADD += @SERVER_LIBS@

zbx_db_get_expression_macro_result_LDFLAGS = @SERVER_LDFLAGS@ \
			-Wl,--wrap=zbx_dc_open_user_macros \
			-Wl,--wrap=zbx_dc_close_user_macros \
			-Wl,--wrap=zbx_dc_get_user_macro \
			-Wl,--wrap=zbx_db_with_trigger_itemid \
			-Wl,--wrap=zbx_db_trigger_get_all_hostids \
			$(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_db_get_expression_macro_result_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS) \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue

endif

