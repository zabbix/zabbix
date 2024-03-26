if SERVER
SERVER_tests = \
	is_double \
	zbx_trim_float \
	zbx_trim_integer
endif

noinst_PROGRAMS = $(SERVER_tests)

if SERVER
COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

COMMON_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(TLS_CFLAGS) \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

is_double_SOURCES = \
	is_double.c \
	$(COMMON_SRC_FILES)

is_double_LDADD = \
	$(COMMON_LIB_FILES)

is_double_LDADD += @SERVER_LIBS@

is_double_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

is_double_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_trim_float_SOURCES = \
	zbx_trim_float.c \
	$(COMMON_SRC_FILES)

zbx_trim_float_LDADD = \
	$(COMMON_LIB_FILES)

zbx_trim_float_LDADD += @SERVER_LIBS@

zbx_trim_float_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_trim_float_CFLAGS = $(COMMON_COMPILER_FLAGS)


zbx_trim_integer_SOURCES = \
	zbx_trim_integer.c \
	$(COMMON_SRC_FILES)

zbx_trim_integer_LDADD = \
	$(COMMON_LIB_FILES)

zbx_trim_integer_LDADD += @SERVER_LIBS@

zbx_trim_integer_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_trim_integer_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
