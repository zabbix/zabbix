include ../Makefile.common

BINARIES_tests = \
	init_library_common

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

# zbxcommon MUST not depend on any other zabbix library

COMMON_LIBS = \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)


COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests

init_library_common_SOURCES = \
	init_library_common.c \
	$(COMMON_SRC_FILES)

init_library_common_LDADD = $(COMMON_LIBS)

init_library_common_LDADD += @SERVER_LIBS@

init_library_common_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

init_library_common_CFLAGS = $(COMMON_COMPILER_FLAGS)

