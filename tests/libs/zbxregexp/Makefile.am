if SERVER
noinst_PROGRAMS = wildcard_match

wildcard_match_SOURCES = \
	wildcard_match.c \
	../../zbxmocktest.h

# zbxregexp depends on zbxlog, zbxstr and zbxcommon 

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

MUTEX_LIB_FILES = \
        $(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
        $(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

CFG_TEST_DEPS = \
        $(top_srcdir)/src/libs/zbxcfg/libzbxcfg.a \
        $(top_srcdir)/src/libs/zbxip/libzbxip.a \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
        $(top_srcdir)/src/libs/zbxfile/libzbxfile.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

LOG_LIB_FILES = \
        $(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
        $(MUTEX_LIB_FILES) \
        $(CFG_TEST_DEPS) \
        $(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
        $(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
        $(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

REGEXP_LIBS = \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(LOG_LIB_FILES) \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

wildcard_match_LDADD = $(REGEXP_LIBS)

wildcard_match_LDADD += @SERVER_LIBS@

wildcard_match_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

wildcard_match_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
endif
