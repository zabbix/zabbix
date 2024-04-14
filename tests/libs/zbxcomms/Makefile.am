if IPV6
noinst_PROGRAMS = zbx_tcp_check_allowed_peers
else
noinst_PROGRAMS = zbx_tcp_check_allowed_peers_ipv4
endif

COMMON_SRC_FILES = \
	../../zbxmocktest.h

MOCK_DATA_DEPS = \
	$(top_srcdir)/tests/libzbxmockdata.a \
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

CRYPTO_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxhash/libzbxhash.a \
	$(top_srcdir)/src/libs/zbxstr/libzbxstr.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a

# comms depends on zbxalgo, zbxtime, zbxcrypto, zbxstr, zbxlog, zbxmutexs, zbxcompress, zbxip and zbxcommon

COMMS_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxip/libzbxip.a \
	$(top_srcdir)/src/libs/zbxtime/libzbxtime.a \
	$(CRYPTO_LIB_FILES) \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(LOG_LIB_FILES) \
	$(MUTEX_LIB_FILES) \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMS_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

if IPV6
zbx_tcp_check_allowed_peers_SOURCES = \
	zbx_tcp_check_allowed_peers.c \
	$(COMMON_SRC_FILES)

zbx_tcp_check_allowed_peers_LDADD = \
	$(COMMS_LIB_FILES) $(TLS_LIBS)

zbx_tcp_check_allowed_peers_LDADD += @AGENT_LIBS@

zbx_tcp_check_allowed_peers_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_check_allowed_peers_CFLAGS = $(COMMS_COMPILER_FLAGS) $(TLS_CFLAGS)
else
zbx_tcp_check_allowed_peers_ipv4_SOURCES = \
	zbx_tcp_check_allowed_peers_ipv4.c \
	$(COMMON_SRC_FILES)

zbx_tcp_check_allowed_peers_ipv4_LDADD = \
	$(COMMS_LIB_FILES)

zbx_tcp_check_allowed_peers_ipv4_LDADD += @AGENT_LIBS@

zbx_tcp_check_allowed_peers_ipv4_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_tcp_check_allowed_peers_ipv4_CFLAGS = $(COMMS_COMPILER_FLAGS)
endif

