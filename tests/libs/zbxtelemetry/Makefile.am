include ../Makefile.include

BINARIES_tests = \
	tq_sql_dyn_escape_string_unquoted \
	zbx_tq_validate_numeric_condition_value \
	tq_sql_dyn_normalize_numeric_condition_value

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

zbx_tq_validate_numeric_condition_value_SOURCES = \
	zbx_tq_validate_numeric_condition_value.c \
	$(COMMON_SRC_FILES)

zbx_tq_validate_numeric_condition_value_LDADD = \
	$(TELEMETRY_LIBS)

zbx_tq_validate_numeric_condition_value_LDADD += @SERVER_LIBS@

zbx_tq_validate_numeric_condition_value_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_tq_validate_numeric_condition_value_CFLAGS = $(TELEMETRY_COMPILER_FLAGS)

tq_sql_dyn_normalize_numeric_condition_value_SOURCES = \
	tq_sql_dyn_normalize_numeric_condition_value.c \
	$(COMMON_SRC_FILES)

tq_sql_dyn_normalize_numeric_condition_value_LDADD = \
	$(TELEMETRY_LIBS)

tq_sql_dyn_normalize_numeric_condition_value_LDADD += @SERVER_LIBS@

tq_sql_dyn_normalize_numeric_condition_value_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

tq_sql_dyn_normalize_numeric_condition_value_CFLAGS = $(TELEMETRY_COMPILER_FLAGS)
