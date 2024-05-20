include ../Makefile.common

if IPV6
noinst_PROGRAMS = zbx_tcp_check_allowed_peers
else
noinst_PROGRAMS = zbx_tcp_check_allowed_peers_ipv4
endif

COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMS_LIBS = \
	$(COMMS_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMS_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

if IPV6
zbx_tcp_check_allowed_peers_SOURCES = \
	zbx_tcp_check_allowed_peers.c \
	$(COMMON_SRC_FILES)

zbx_tcp_check_allowed_peers_LDADD = \
	$(COMMS_LIBS) $(TLS_LIBS)

zbx_tcp_check_allowed_peers_LDADD += @AGENT_LIBS@

zbx_tcp_check_allowed_peers_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

zbx_tcp_check_allowed_peers_CFLAGS = $(COMMS_COMPILER_FLAGS) $(TLS_CFLAGS)
else
zbx_tcp_check_allowed_peers_ipv4_SOURCES = \
	zbx_tcp_check_allowed_peers_ipv4.c \
	$(COMMON_SRC_FILES)

zbx_tcp_check_allowed_peers_ipv4_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_check_allowed_peers_ipv4_LDADD += @AGENT_LIBS@

zbx_tcp_check_allowed_peers_ipv4_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_tcp_check_allowed_peers_ipv4_CFLAGS = $(COMMS_COMPILER_FLAGS)
endif

