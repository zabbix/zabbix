if HAVE_UNIXODBC

BINARIES_tests = \
	odbc_pass

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS) $(TLS_LIBS)

MOCK_TEST_DEPS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

MUTEX_LIBS = \
        $(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
        $(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

CFG_LIBS = \
        $(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
        $(top_srcdir)/src/libs/zbxip/libzbxip.a \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
        $(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

LOG_LIBS = \
        $(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
        $(MUTEX_LIBS) \
        $(CFG_LIBS) \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

REGEXP_LIBS = \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(LOG_LIBS) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

VARIANT_LIBS = \
        $(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
        $(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

EXPR_LIBS = \
	$(top_srcdir)/src/libs/zbxexpr/libzbxexpr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

JSON_LIBS = \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(REGEXP_LIBS) \
	$(VARIANT_LIBS) \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(EXPR_LIBS) \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a


PARAM_LIBS = \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS) \
	$(top_srcdir)/src/libs/zbxodbc/libzbxodbc.a \
	$(JSON_LIBS)



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
