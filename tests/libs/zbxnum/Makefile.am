BINARIES_tests = \
	is_double \
	zbx_trim_float \
	zbx_trim_integer

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

# 1) zbxnum depends only on zbxcommon
# 2) zbxcommon currently needs zbxnix, which also needs zbxlog, zbxnum, zbxthreads, zbxcomms and zbxcommon
#
# 1) mockdata needs zbxalgo, zbxstr, zbxnum and zbxcommon
# 2) mocktest needs zbxnix, which also needs zbxlog, zbxnum, zbxthreads, zbxcomms and zbxcommon

COMMON_LIB_DEPS = \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(COMMON_LIB_DEPS) \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

MOCK_TEST_DEPS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(COMMON_LIB_DEPS)

NUM_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(COMMON_LIB_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

NUM_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS)

is_double_SOURCES = \
	is_double.c \
	$(COMMON_SRC_FILES)

is_double_LDADD = \
	$(NUM_LIB_FILES)

is_double_LDADD += @SERVER_LIBS@

is_double_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

is_double_CFLAGS = $(NUM_COMPILER_FLAGS)


zbx_trim_float_SOURCES = \
	zbx_trim_float.c \
	$(COMMON_SRC_FILES)

zbx_trim_float_LDADD = \
	$(NUM_LIB_FILES)

zbx_trim_float_LDADD += @SERVER_LIBS@

zbx_trim_float_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_trim_float_CFLAGS = $(NUM_COMPILER_FLAGS)


zbx_trim_integer_SOURCES = \
	zbx_trim_integer.c \
	$(COMMON_SRC_FILES)

zbx_trim_integer_LDADD = \
	$(NUM_LIB_FILES)

zbx_trim_integer_LDADD += @SERVER_LIBS@

zbx_trim_integer_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS)

zbx_trim_integer_CFLAGS = $(NUM_COMPILER_FLAGS)
