BINARIES_tests = \
	init_library_common

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

# mockdata needs zbxalgo, zbxstr and zbxnum
# mocktest needs zbxnix
#
# zbxcommon currently needs zbxnix, which also needs zbxlog
# zbxcommon should not depend on any other zabbix library
#
COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

COMMON_COMPILER_FLAGS = -I@top_srcdir@/tests

init_library_common_SOURCES = \
	init_library_common.c \
	$(COMMON_SRC_FILES)

init_library_common_LDADD = $(COMMON_LIB_FILES)

init_library_common_LDADD += @SERVER_LIBS@

init_library_common_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

init_library_common_CFLAGS = $(COMMON_COMPILER_FLAGS)

