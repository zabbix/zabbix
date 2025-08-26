include ../Makefile.include

if IPV6
noinst_PROGRAMS = zbx_tcp_check_allowed_peers $(COMMS_tests)
else
noinst_PROGRAMS = zbx_tcp_check_allowed_peers_ipv4 $(COMMS_tests)
endif

COMMS_tests = zbx_validate_peer_list \
	zbx_tcp_connection_type_name \
	socket_poll_error

COMMON_SRC_FILES = \
	../../zbxmocktest.h

COMMS_LIBS = \
	$(COMMS_DEPS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMS_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS)

#zbx_validate_peer_list

zbx_validate_peer_list_SOURCES = \
	zbx_validate_peer_list.c \
	$(COMMON_SRC_FILES)

zbx_validate_peer_list_LDADD = \
	$(COMMS_LIBS)

zbx_validate_peer_list_LDADD += @AGENT_LIBS@

zbx_validate_peer_list_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_validate_peer_list_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_tcp_connection_type_name

zbx_tcp_connection_type_name_SOURCES = \
	zbx_tcp_connection_type_name.c \
	$(COMMON_SRC_FILES)

zbx_tcp_connection_type_name_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_connection_type_name_LDADD += @AGENT_LIBS@

zbx_tcp_connection_type_name_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_tcp_connection_type_name_CFLAGS = $(COMMS_COMPILER_FLAGS)

#socket_poll_error

socket_poll_error_SOURCES = \
	socket_poll_error.c \
	$(COMMON_SRC_FILES)

socket_poll_error_LDADD = \
	$(COMMS_LIBS)

socket_poll_error_LDADD += @AGENT_LIBS@

socket_poll_error_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

socket_poll_error_CFLAGS = $(COMMS_COMPILER_FLAGS)

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

