include ../Makefile.include

BINARIES_tests = \
	tq_sql_dyn_escape_string_unquoted

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

TELEMETRY_LIBS = \
	$(ANY_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

TELEMETRY_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

tq_sql_dyn_escape_string_unquoted_SOURCES = \
	tq_sql_dyn_escape_string_unquoted.c \
	$(COMMON_SRC_FILES)

tq_sql_dyn_escape_string_unquoted_LDADD = \
	$(TELEMETRY_LIBS)

tq_sql_dyn_escape_string_unquoted_LDADD += @SERVER_LIBS@

tq_sql_dyn_escape_string_unquoted_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

tq_sql_dyn_escape_string_unquoted_CFLAGS = $(TELEMETRY_COMPILER_FLAGS)
