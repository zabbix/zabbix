include ../Makefile.common

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

DBHIGH_LIBS = \
	$(DBHIGH_DEPS) \
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
