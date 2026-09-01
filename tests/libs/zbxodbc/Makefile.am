include ../Makefile.include

if HAVE_UNIXODBC

BINARIES_tests = \
	odbc_pass

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

ODBC_LIBS = \
	$(ANY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

ODBC_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

odbc_pass_SOURCES = \
	odbc_pass.c \
	$(COMMON_SRC_FILES)

odbc_pass_LDADD = \
	$(ODBC_LIBS)

odbc_pass_LDADD += @SERVER_LIBS@

odbc_pass_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

odbc_pass_CFLAGS = $(ODBC_COMPILER_FLAGS)

endif
