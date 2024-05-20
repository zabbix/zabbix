include ../Makefile.common

if SERVER
noinst_PROGRAMS = wildcard_match

wildcard_match_SOURCES = \
	wildcard_match.c \
	../../zbxmocktest.h

# zbxregexp depends on zbxlog, zbxstr and zbxcommon 

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
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

wildcard_match_LDADD = $(REGEXP_LIBS)

wildcard_match_LDADD += @SERVER_LIBS@

wildcard_match_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

wildcard_match_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
endif
