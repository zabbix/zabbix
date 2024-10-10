include ../Makefile.include

if SERVER
noinst_PROGRAMS = \
	zbx_dbconn_select_uint64
endif

COMMON_SRC = \
	../../zbxmocktest.h

COMMON_FLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

DB_LIBS = \
	$(DB_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

if SERVER

zbx_dbconn_select_uint64_SOURCES = \
	zbx_dbconn_select_uint64.c \
	$(COMMON_SRC)

zbx_dbconn_select_uint64_LDADD = $(DB_LIBS)


zbx_dbconn_select_uint64_LDADD += @SERVER_LIBS@

zbx_dbconn_select_uint64_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_dbconn_select_uint64_CFLAGS = $(COMMON_FLAGS)

endif
