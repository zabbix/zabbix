noinst_PROGRAMS = zbx_vc_get_values zbx_vc_add_values zbx_vc_get_value

VALUECACHE_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/tests/libzbxmockdata.a

zbx_vc_get_values_SOURCES = \
	zbx_vc_get_values.c \
	valuecache_mock.c \
	@top_srcdir@/src/libs/zbxdbcache/valuecache.c \
	@top_srcdir@/src/libs/zbxhistory/history.c \
	../../zbxmocktest.h

zbx_vc_get_values_WRAP_FUNCS = \
	-Wl,--wrap=zbx_mutex_create \
	-Wl,--wrap=zbx_mutex_destroy \
	-Wl,--wrap=zbx_mem_create \
	-Wl,--wrap=__zbx_mem_malloc \
	-Wl,--wrap=__zbx_mem_realloc \
	-Wl,--wrap=__zbx_mem_free \
	-Wl,--wrap=zbx_history_get_values \
	-Wl,--wrap=zbx_history_add_values \
	-Wl,--wrap=zbx_history_sql_init \
	-Wl,--wrap=zbx_history_elastic_init \
	-Wl,--wrap=time

zbx_vc_get_values_LDADD = $(VALUECACHE_LIBS)

zbx_vc_get_values_CFLAGS = \
	 $(zbx_vc_get_values_WRAP_FUNCS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/tests

zbx_vc_add_values_SOURCES = \
	zbx_vc_add_values.c \
	valuecache_mock.c \
	@top_srcdir@/src/libs/zbxdbcache/valuecache.c \
	@top_srcdir@/src/libs/zbxhistory/history.c \
	../../zbxmocktest.h

zbx_vc_add_values_WRAP_FUNCS = \
	-Wl,--wrap=zbx_mutex_create \
	-Wl,--wrap=zbx_mutex_destroy \
	-Wl,--wrap=zbx_mem_create \
	-Wl,--wrap=__zbx_mem_malloc \
	-Wl,--wrap=__zbx_mem_realloc \
	-Wl,--wrap=__zbx_mem_free \
	-Wl,--wrap=zbx_history_get_values \
	-Wl,--wrap=zbx_history_add_values \
	-Wl,--wrap=zbx_history_sql_init \
	-Wl,--wrap=zbx_history_elastic_init \
	-Wl,--wrap=time

zbx_vc_add_values_LDADD = $(VALUECACHE_LIBS)

zbx_vc_add_values_CFLAGS = \
	 $(zbx_vc_add_values_WRAP_FUNCS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/tests

zbx_vc_get_value_SOURCES = \
	zbx_vc_get_value.c \
	valuecache_mock.c \
	@top_srcdir@/src/libs/zbxdbcache/valuecache.c \
	@top_srcdir@/src/libs/zbxhistory/history.c \
	../../zbxmocktest.h

zbx_vc_get_value_WRAP_FUNCS = \
	-Wl,--wrap=zbx_mutex_create \
	-Wl,--wrap=zbx_mutex_destroy \
	-Wl,--wrap=zbx_mem_create \
	-Wl,--wrap=__zbx_mem_malloc \
	-Wl,--wrap=__zbx_mem_realloc \
	-Wl,--wrap=__zbx_mem_free \
	-Wl,--wrap=zbx_history_get_values \
	-Wl,--wrap=zbx_history_add_values \
	-Wl,--wrap=zbx_history_sql_init \
	-Wl,--wrap=zbx_history_elastic_init \
	-Wl,--wrap=time

zbx_vc_get_value_LDADD = $(VALUECACHE_LIBS)

zbx_vc_get_value_CFLAGS = \
	 $(zbx_vc_get_value_WRAP_FUNCS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/tests


