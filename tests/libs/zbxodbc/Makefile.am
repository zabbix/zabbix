include ../Makefile.include

if HAVE_UNIXODBC

BINARIES_tests = \
	odbc_pass

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

PARAM_LIBS = \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS) \
	$(top_srcdir)/src/libs/zbxodbc/libzbxodbc.a \
	$(JSON_DEPS)



PARAM_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

odbc_pass_SOURCES = \
	odbc_pass.c \
	$(COMMON_SRC_FILES)

odbc_pass_LDADD = \
	$(PARAM_LIBS)

odbc_pass_LDADD += @SERVER_LIBS@

odbc_pass_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

odbc_pass_CFLAGS = $(PARAM_COMPILER_FLAGS)

endif
