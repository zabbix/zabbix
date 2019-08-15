if SERVER
SERVER_tests = \
	zbx_vc_get_values \
	zbx_vc_add_values \
	zbx_vc_get_value \
	dc_maintenance_match_tags \
	is_item_processed_by_server \
	dc_item_poller_type_update
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
VALUECACHE_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/tests/libzbxmockdata.a

CACHE_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxdbcache/libzbxdbcache.a \
	$(top_srcdir)/src/zabbix_server/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxserver/libzbxserver.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxserversysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxhistory/libzbxhistory.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxmemory/libzbxmemory.a \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

COMMON_WRAP_FUNCS = \
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

zbx_vc_get_values_SOURCES = \
	zbx_vc_get_values.c \
	valuecache_mock.c \
	@top_srcdir@/src/libs/zbxdbcache/valuecache.c \
	@top_srcdir@/src/libs/zbxhistory/history.c \
	../../zbxmocktest.h

zbx_vc_get_values_LDADD = $(VALUECACHE_LIBS) @SERVER_LIBS@
zbx_vc_get_values_LDFLAGS = @SERVER_LDFLAGS@

zbx_vc_get_values_CFLAGS = \
	$(COMMON_WRAP_FUNCS) \
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

zbx_vc_add_values_LDADD = $(VALUECACHE_LIBS) @SERVER_LIBS@
zbx_vc_add_values_LDFLAGS = @SERVER_LDFLAGS@

zbx_vc_add_values_CFLAGS = \
	 $(COMMON_WRAP_FUNCS) \
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

zbx_vc_get_value_LDADD = $(VALUECACHE_LIBS) @SERVER_LIBS@
zbx_vc_get_value_LDFLAGS = @SERVER_LDFLAGS@

zbx_vc_get_value_CFLAGS = \
	 $(COMMON_WRAP_FUNCS) \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/tests

dc_maintenance_match_tags_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxdbcache \
	-I@top_srcdir@/tests

dc_maintenance_match_tags_SOURCES = dc_maintenance_match_tags.c
dc_maintenance_match_tags_LDADD = $(CACHE_LIBS) @SERVER_LIBS@
dc_maintenance_match_tags_LDFLAGS = @SERVER_LDFLAGS@

is_item_processed_by_server_SOURCES = is_item_processed_by_server.c
is_item_processed_by_server_LDADD = $(CACHE_LIBS) @SERVER_LIBS@
is_item_processed_by_server_LDFLAGS = @SERVER_LDFLAGS@
is_item_processed_by_server_CFLAGS = -I@top_srcdir@/tests

dc_item_poller_type_update_SOURCES = dc_item_poller_type_update.c
dc_item_poller_type_update_LDADD = $(CACHE_LIBS) @SERVER_LIBS@
dc_item_poller_type_update_LDFLAGS = @SERVER_LDFLAGS@
dc_item_poller_type_update_CFLAGS = -I@top_srcdir@/tests -I@top_srcdir@/src/libs/zbxdbcache
endif
