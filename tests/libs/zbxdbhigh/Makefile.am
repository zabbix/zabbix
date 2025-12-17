include ../Makefile.include

if SERVER
noinst_PROGRAMS = \
	DBadd_condition_alloc \
	zbx_merge_tags \
	zbx_del_tags \
	zbx_add_tags \
	zbx_sync_rowset_merge
else
if PROXY
noinst_PROGRAMS = \
	DBadd_condition_alloc
endif
endif

COMMON_SRC = \
	../../zbxmocktest.h

COMMON_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

DBHIGH_LIBS = \
	$(DBHIGH_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

if SERVER

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


zbx_sync_rowset_merge_SOURCES = \
	zbx_sync_rowset_merge.c \
	$(top_srcdir)/tests/zbxmockprimitive.c \
	$(top_srcdir)/tests/zbxmockprimitive.h 

zbx_sync_rowset_merge_CFLAGS = \
	-I@top_srcdir@/tests \
	$(COMMON_COMPILER_FLAGS)

zbx_sync_rowset_merge_LDADD =  $(DBHIGH_LIBS) @SERVER_LIBS@ $(TEST_LIBS) $(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

zbx_sync_rowset_merge_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

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


