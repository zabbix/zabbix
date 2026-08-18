include ../../libs/Makefile.include

if SERVER
SERVER_tests = zbx_pinger_test

noinst_PROGRAMS = $(SERVER_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

PINGER_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

zbx_pinger_test_SOURCES = \
	zbx_pinger_test.c \
	../../zbxmockexit.c \
	../../zbxmockdb.c \
	../../zbxmockfile.c \
	../../zbxmocklog.c \
	../../zbxmockdir.c

zbx_pinger_test_LDADD = $(PINGER_LIBS)
zbx_pinger_test_LDADD += @SERVER_LIBS@
zbx_pinger_test_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_pinger_test_CFLAGS = \
	-I@top_srcdir@/tests @LIBXML2_CFLAGS@ $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)
endif
