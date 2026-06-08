include ../Makefile.include

if PROXY
noinst_PROGRAMS = \
	zbx_pb_discovery_close \
	zbx_pb_parse_mode \
	zbx_pb_discovery_write_host \
	zbx_pb_autoreg_write_host \
	zbx_pb_create_error

# All proxybuffer tests use the same library set and LDFLAGS:
#   - DB / DC / nix functions are stubbed inline (prevents libzbxmockdata.a
#     objects from being extracted and causing duplicate-symbol conflicts).
#   - -Wl,--wrap=open is required so __real_open exists at link time and the
#     __wrap_open passthrough for .gcda files (coverage) works correctly.

PROXYBUFFER_LIBS = \
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

# AM_LDFLAGS applies to all targets in this file; per-target *_LDFLAGS would
# also work but using AM_LDFLAGS avoids automake's "PB has no canonical name"
# warning that fires when a FOO_LDFLAGS variable has no matching target FOO.
AM_LDFLAGS = @PROXY_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=exit \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=open

PROXYBUFFER_FLAGS = \
	-I@top_srcdir@/src/libs/zbxproxybuffer \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) $(YAML_CFLAGS)

# keep CLOSE_LIBS for backwards compatibility (referenced by name in some scripts)
CLOSE_LIBS = $(PROXYBUFFER_LIBS)

# zbx_pb_mock_stubs.c is compiled once per binary (automake renames objects
# with the per-target prefix due to per-target _CFLAGS).
STUBS = zbx_pb_mock_stubs.c

zbx_pb_discovery_close_SOURCES = zbx_pb_discovery_close.c $(STUBS)
zbx_pb_discovery_close_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_discovery_close_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_parse_mode_SOURCES = zbx_pb_parse_mode.c $(STUBS)
zbx_pb_parse_mode_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_parse_mode_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_discovery_write_host_SOURCES = zbx_pb_discovery_write_host.c $(STUBS)
zbx_pb_discovery_write_host_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_discovery_write_host_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_autoreg_write_host_SOURCES = zbx_pb_autoreg_write_host.c $(STUBS)
zbx_pb_autoreg_write_host_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_autoreg_write_host_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@

zbx_pb_create_error_SOURCES = zbx_pb_create_error.c $(STUBS)
zbx_pb_create_error_CFLAGS = $(PROXYBUFFER_FLAGS)
zbx_pb_create_error_LDADD = $(PROXYBUFFER_LIBS) @PROXY_LIBS@
endif
