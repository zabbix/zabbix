include ../Makefile.include

BINARIES_tests = \
	zbx_join_hostport

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

IP_LIBS = \
	$(ANY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

IP_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

#zbx_join_hostport

zbx_join_hostport_SOURCES = \
	zbx_join_hostport.c \
	$(COMMON_SRC_FILES)

zbx_join_hostport_LDADD = \
	$(IP_LIBS)

zbx_join_hostport_LDADD += @SERVER_LIBS@

zbx_join_hostport_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_join_hostport_CFLAGS = $(IP_COMPILER_FLAGS)
