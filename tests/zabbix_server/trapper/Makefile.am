include ../../libs/Makefile.include

if SERVER
SERVER_tests = zbx_trapper_preproc_test_run

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

TRAPPER_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_trapper_preproc_test_run_SOURCES = \
	zbx_trapper_preproc_test_run.c \
	../../../src/libs/zbxtrapper/trapper_preproc.c \
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
	-Wl,--wrap=zbx_free_agent_result \
	-Wl,--wrap=zbx_dc_expand_user_and_func_macros_from_cache

zbx_trapper_preproc_test_run_CFLAGS = \
	-I@top_srcdir@/tests -I@top_srcdir@/src  @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif

