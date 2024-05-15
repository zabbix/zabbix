if SERVER
noinst_PROGRAMS = \
	zbx_db_select_uint64 \
	DBadd_condition_alloc \
	zbx_merge_tags \
	zbx_del_tags \
	zbx_add_tags
else
if PROXY
noinst_PROGRAMS = \
	DBadd_condition_alloc
endif
endif

COMMON_SRC = \
	../../zbxmocktest.h

COMMON_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)


# zbxdbhigh depends on zbxdb, zbxdbschema, zbxtagfilter, zbxserialize, zbxcrypto, zbxcfg, zbxthreads, zbxlog,
#			zbxversion, zbxalgo, zbxnum, zbxstr, zbxtime and zbxcommon

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

MOCK_TEST_DEPS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

MUTEX_LIBS = \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

CFG_LIBS = \
	$(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

LOG_LIBS = \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(MUTEX_LIBS) \
	$(CFG_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

REGEXP_LIBS = \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(LOG_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

VARIANT_LIBS = \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

EXPR_LIBS = \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

JSON_LIBS = \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(REGEXP_LIBS) \
	$(VARIANT_LIBS) \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(EXPR_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

DB_LIBS = \
	$(top_srcdir)/src/libs/zbxdb/libzbxdb.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(JSON_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

TAGFILTER_LIBS = \
	$(top_srcdir)/src/libs/zbxtagfilter/libzbxtagfilter.a \
	$(EXPR_LIBS) \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a

CRYPTO_LIBS = \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

DBHIGH_LIBS = \
	$(top_srcdir)/src/libs/zbxdbhigh/libzbxdbhigh.a \
	$(DB_LIBS) \
	$(top_srcdir)/src/libs/zbxdbschema/libzbxdbschema.a \
	$(TAGFILTER_LIBS) \
	$(top_srcdir)/src/libs/zbxserialize/libzbxserialize.a \
	$(CRYPTO_LIBS) \
	$(CFG_LIBS) \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(LOG_LIBS) \
	$(top_srcdir)/src/libs/zbxversion/libzbxversion.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

if SERVER

zbx_db_select_uint64_SOURCES = \
	zbx_db_select_uint64.c \
	$(COMMON_SRC)

zbx_db_select_uint64_LDADD = $(DBHIGH_LIBS)


zbx_db_select_uint64_LDADD += @SERVER_LIBS@

zbx_db_select_uint64_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_db_select_uint64_CFLAGS = $(COMMON_FLAGS)


DBadd_condition_alloc_SOURCES = \
	DBadd_condition_alloc.c \
	$(COMMON_SRC)

DBadd_condition_alloc_LDADD = $(DBHIGH_LIBS)

DBadd_condition_alloc_LDADD += @SERVER_LIBS@

DBadd_condition_alloc_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

DBadd_condition_alloc_CFLAGS = $(COMMON_FLAGS)

zbx_merge_tags_SOURCES = \
	dbhigh_test.c \
	zbx_merge_tags.c \
	$(COMMON_SRC)

zbx_merge_tags_LDADD = $(DBHIGH_LIBS)

zbx_merge_tags_LDADD += @SERVER_LIBS@

zbx_merge_tags_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_merge_tags_CFLAGS = $(COMMON_FLAGS)

zbx_del_tags_SOURCES = \
	dbhigh_test.c \
	zbx_del_tags.c \
	$(COMMON_SRC)

zbx_del_tags_LDADD = $(DBHIGH_LIBS)

zbx_del_tags_LDADD += @SERVER_LIBS@

zbx_del_tags_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_del_tags_CFLAGS = $(COMMON_FLAGS)

zbx_add_tags_SOURCES = \
	dbhigh_test.c \
	zbx_add_tags.c \
	$(COMMON_SRC)

zbx_add_tags_LDADD = $(DBHIGH_LIBS)

zbx_add_tags_LDADD += @SERVER_LIBS@

zbx_add_tags_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_add_tags_CFLAGS = $(COMMON_FLAGS)

else
if PROXY

DBadd_condition_alloc_SOURCES = \
	DBadd_condition_alloc.c \
	$(COMMON_SRC)

DBadd_condition_alloc_LDADD = \
	$(DBHIGH_LIBS)

DBadd_condition_alloc_LDADD += @PROXY_LIBS@

DBadd_condition_alloc_LDFLAGS = @PROXY_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

DBadd_condition_alloc_CFLAGS = $(COMMON_FLAGS)

endif
endif
