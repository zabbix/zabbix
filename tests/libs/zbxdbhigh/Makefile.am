include ../Makefile.include

if SERVER
noinst_PROGRAMS = \
	DBselect_uint64 \
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

DBHIGH_LIBS =

if SERVER
DBHIGH_LIBS += $(SERVER_GROUPED_LIBS) @SERVER_LIBS@
else
if PROXY
DBHIGH_LIBS += $(PROXY_GROUPED_LIBS) @PROXY_LIBS@
endif
endif

DBHIGH_LIBS += \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

if SERVER

DBselect_uint64_SOURCES = \
	DBselect_uint64.c \
	$(COMMON_SRC)

DBselect_uint64_LDADD = $(DBHIGH_LIBS)

DBselect_uint64_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

DBselect_uint64_CFLAGS = $(COMMON_FLAGS)


DBadd_condition_alloc_SOURCES = \
	DBadd_condition_alloc.c \
	$(COMMON_SRC)

DBadd_condition_alloc_LDADD = $(DBHIGH_LIBS)

DBadd_condition_alloc_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

DBadd_condition_alloc_CFLAGS = $(COMMON_FLAGS)

zbx_merge_tags_SOURCES = \
	dbhigh_test.c \
	zbx_merge_tags.c \
	$(COMMON_SRC)

zbx_merge_tags_LDADD = $(DBHIGH_LIBS)

zbx_merge_tags_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_merge_tags_CFLAGS = $(COMMON_FLAGS)

zbx_del_tags_SOURCES = \
	dbhigh_test.c \
	zbx_del_tags.c \
	$(COMMON_SRC)

zbx_del_tags_LDADD = $(DBHIGH_LIBS)

zbx_del_tags_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_del_tags_CFLAGS = $(COMMON_FLAGS)

zbx_add_tags_SOURCES = \
	dbhigh_test.c \
	zbx_add_tags.c \
	$(COMMON_SRC)

zbx_add_tags_LDADD = $(DBHIGH_LIBS)

zbx_add_tags_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_add_tags_CFLAGS = $(COMMON_FLAGS)

else
if PROXY

DBadd_condition_alloc_SOURCES = \
	DBadd_condition_alloc.c \
	$(COMMON_SRC)

DBadd_condition_alloc_LDADD = $(DBHIGH_LIBS)

DBadd_condition_alloc_LDFLAGS = @PROXY_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

DBadd_condition_alloc_CFLAGS = $(COMMON_FLAGS)

endif
endif
