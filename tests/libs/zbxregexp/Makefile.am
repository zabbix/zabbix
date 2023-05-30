if SERVER
noinst_PROGRAMS = wildcard_match

wildcard_match_SOURCES = \
	wildcard_match.c \
	../../zbxmocktest.h

wildcard_match_LDADD = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxaudit/libzbxaudit.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

wildcard_match_LDADD += @SERVER_LIBS@

wildcard_match_LDFLAGS = @SERVER_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

wildcard_match_CFLAGS = -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
endif
