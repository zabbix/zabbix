if IPV6
noinst_PROGRAMS = zbx_tcp_check_allowed_peers
else
noinst_PROGRAMS = zbx_tcp_check_allowed_peers_ipv4
endif

COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/tests/libzbxmockdata.a \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests

if IPV6
zbx_tcp_check_allowed_peers_SOURCES = \
	zbx_tcp_check_allowed_peers.c \
	$(COMMON_SRC_FILES)

zbx_tcp_check_allowed_peers_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tcp_check_allowed_peers_LDADD += @AGENT_LIBS@

zbx_tcp_check_allowed_peers_LDFLAGS = @AGENT_LDFLAGS@

zbx_tcp_check_allowed_peers_CFLAGS = $(COMMON_COMPILER_FLAGS)
else
zbx_tcp_check_allowed_peers_ipv4_SOURCES = \
	zbx_tcp_check_allowed_peers_ipv4.c \
	$(COMMON_SRC_FILES)

zbx_tcp_check_allowed_peers_ipv4_LDADD = \
	$(COMMON_LIB_FILES)

zbx_tcp_check_allowed_peers_ipv4_LDADD += @AGENT_LIBS@

zbx_tcp_check_allowed_peers_ipv4_LDFLAGS = @AGENT_LDFLAGS@

zbx_tcp_check_allowed_peers_ipv4_CFLAGS = $(COMMON_COMPILER_FLAGS)
endif

