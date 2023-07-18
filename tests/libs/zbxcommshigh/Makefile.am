if SERVER
ZLIB_tests = zbx_tcp_recv_ext_zlib
endif

noinst_PROGRAMS = zbx_tcp_recv_ext zbx_tcp_recv_raw_ext $(ZLIB_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libfunclistsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxxml/libzbxxml.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(top_srcdir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_srcdir)/src/libs/zbxprof/libzbxprof.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxnum/libzbxnum.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(CMOCKA_LIBS) $(YAML_LIBS)

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

zbx_tcp_recv_ext_SOURCES = \
	zbx_tcp_recv_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_ext_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tcp_recv_ext_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_ext_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_ext_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)

if SERVER
zbx_tcp_recv_ext_zlib_SOURCES = \
	zbx_tcp_recv_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_ext_zlib_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tcp_recv_ext_zlib_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_ext_zlib_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_ext_zlib_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)
endif

zbx_tcp_recv_raw_ext_SOURCES = \
	zbx_tcp_recv_raw_ext.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_raw_ext_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tcp_recv_raw_ext_LDADD += @AGENT_LIBS@ $(TLS_LIBS)

zbx_tcp_recv_raw_ext_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_recv_raw_ext_CFLAGS = $(COMMON_COMPILER_FLAGS) $(TLS_CFLAGS)
