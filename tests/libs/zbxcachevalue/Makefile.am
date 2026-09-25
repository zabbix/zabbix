include ../Makefile.include

if SERVER
SERVER_tests = \
	zbx_vc_get_values \
	zbx_vc_add_values \
	zbx_vc_get_value
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
VALUECACHE_LIBS = \
	$(top_srcdir)/tests/mocks/valuecache/libvaluecachemock.a \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMON_WRAP_FUNCS = \
	-Wl,--wrap=zbx_mutex_create \
	-Wl,--wrap=zbx_mutex_destroy \
	-Wl,--wrap=zbx_shmem_create \
	-Wl,--wrap=zbx_shmem_destroy \
	-Wl,--wrap=__zbx_shmem_malloc \
	-Wl,--wrap=__zbx_shmem_realloc \
	-Wl,--wrap=__zbx_shmem_free \
	-Wl,--wrap=zbx_shmem_dump_stats \
	-Wl,--wrap=zbx_history_get_values \
	-Wl,--wrap=zbx_history_add_values \
	-Wl,--wrap=history_sql_open \
	-Wl,--wrap=history_elastic_open \
	-Wl,--wrap=history_clickhouse_open \
	-Wl,--wrap=zbx_elastic_version_extract \
	-Wl,--wrap=zbx_elastic_version_get \
	-Wl,--wrap=time \
	-Wl,--wrap=zbx_db_version_check \
	-Wl,--wrap=zbx_db_verify_version_info \
	-Wl,--wrap=zbx_db_settings_set_value

zbx_vc_get_values_SOURCES = \
	zbx_vc_common.c \
	zbx_vc_get_values.c \
	valuecache_test.c \
	@top_srcdir@/src/libs/zbxhistory/history.c \
	@top_srcdir@/src/libs/zbxhistory/history.h \
	@top_srcdir@/src/libs/zbxhistory/history_option.c \
	@top_srcdir@/src/libs/zbxhistory/history_option.h \
	../../zbxmocktest.h

zbx_vc_get_values_LDADD = $(VALUECACHE_LIBS) @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)
zbx_vc_get_values_LDFLAGS = @SERVER_LDFLAGS@ $(COMMON_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_vc_get_values_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(TLS_CFLAGS)

zbx_vc_add_values_SOURCES = \
	zbx_vc_common.c \
	zbx_vc_add_values.c \
	valuecache_test.c \
	@top_srcdir@/src/libs/zbxhistory/history.c \
	@top_srcdir@/src/libs/zbxhistory/history.h \
	@top_srcdir@/src/libs/zbxhistory/history_option.c \
	@top_srcdir@/src/libs/zbxhistory/history_option.h \
	../../zbxmocktest.h

zbx_vc_add_values_LDADD = $(VALUECACHE_LIBS) @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)
zbx_vc_add_values_LDFLAGS = @SERVER_LDFLAGS@ $(COMMON_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_vc_add_values_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS) \
	$(TLS_CFLAGS)

zbx_vc_get_value_SOURCES = \
	zbx_vc_common.c \
	zbx_vc_get_value.c \
	valuecache_test.c \
	@top_srcdir@/src/libs/zbxhistory/history.c \
	@top_srcdir@/src/libs/zbxhistory/history.h \
	@top_srcdir@/src/libs/zbxhistory/history_option.c \
	@top_srcdir@/src/libs/zbxhistory/history_option.h \
	../../zbxmocktest.h

zbx_vc_get_value_LDADD = $(VALUECACHE_LIBS) @SERVER_LIBS@ $(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)
zbx_vc_get_value_LDFLAGS = @SERVER_LDFLAGS@ $(COMMON_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_vc_get_value_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)  \
	$(TLS_CFLAGS)

endif
