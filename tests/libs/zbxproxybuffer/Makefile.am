include ../Makefile.include

if PROXY
noinst_PROGRAMS = \
	zbx_pb_discovery_close

# ── zbx_pb_discovery_close ───────────────────────────────────────────────────
# Integration test: real shared memory via zbx_pb_create(); exercises the full
# open → write → close cycle against the actual proxy buffer library.
# DB / DC / JSON functions are stubbed in the test file (never called in the
# memory-mode close path).  The test file also defines all __wrap_* symbols
# needed when COMMON_WRAP_FUNCS is injected by "make tests_build" so that no
# objects from libzbxmockdata.a (zbxmockdb.o, zbxmockexit.o, zbxmockfile.o)
# are extracted — preventing duplicate-definition conflicts with our stubs.

CLOSE_LIBS = \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxproxybuffer/libzbxproxybuffer.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxshmem/libzbxshmem.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

zbx_pb_discovery_close_SOURCES = zbx_pb_discovery_close.c

zbx_pb_discovery_close_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxproxybuffer \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_pb_discovery_close_LDADD = $(CLOSE_LIBS) @PROXY_LIBS@
zbx_pb_discovery_close_LDFLAGS = @PROXY_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=exit \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=open
endif
