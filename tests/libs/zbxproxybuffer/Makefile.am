include ../Makefile.include

if PROXY
noinst_PROGRAMS = \
	zbx_pb_discovery_close \
	zbx_pb_parse_mode \
	zbx_pb_discovery_write_host \
	zbx_pb_autoreg_write_host \
	zbx_pb_create_error

# All proxybuffer tests use the same library set and LDFLAGS.
#
# libzbxmocktest.a/libzbxmockdata.a are listed both before and after the real
# libraries: ld only searches an archive for symbols that are already
# undefined at that point on the command line, and libzbxproxybuffer.a (and
# the libs after it) are what actually reference zbx_db_*()/exit() - so the
# mock archives need a second pass after them to be extracted. The same
# pattern is used by tests/libs/zbxcacheconfig/Makefile.am's CACHE_LIBS.

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
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

# AM_LDFLAGS applies to all targets in this file; per-target *_LDFLAGS would
# also work but using AM_LDFLAGS avoids automake's "PB has no canonical name"
# warning that fires when a FOO_LDFLAGS variable has no matching target FOO.
AM_LDFLAGS = @PROXY_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=exit \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=zbx_db_select \
	-Wl,--wrap=zbx_db_vselect \
	-Wl,--wrap=zbx_db_select_n \
	-Wl,--wrap=zbx_db_execute \
	-Wl,--wrap=zbx_db_begin \
	-Wl,--wrap=zbx_db_commit \
	-Wl,--wrap=zbx_db_execute_multiple_query \
	-Wl,--wrap=__zbx_shmem_malloc

PROXYBUFFER_FLAGS = \
	-I@top_srcdir@/src/libs/zbxproxybuffer \
	-I@top_srcdir@/tests \
	$(CMOCKA_CFLAGS) $(YAML_CFLAGS)

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
