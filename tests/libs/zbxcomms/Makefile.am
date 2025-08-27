include ../Makefile.include

if IPV6
noinst_PROGRAMS = zbx_tcp_check_allowed_peers $(COMMS_tests)
else
noinst_PROGRAMS = zbx_tcp_check_allowed_peers_ipv4 $(COMMS_tests)
endif

COMMS_tests = zbx_validate_peer_list \
	zbx_tcp_connection_type_name \
	socket_poll_error \
	zbx_tcp_send_context_init \
	zbx_gethost_by_ip \
	zbx_getip_by_host \
	zbx_tcp_listen \
	zbx_udp_send

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

#zbx_tcp_send_context_init

zbx_tcp_send_context_init_SOURCES = \
	zbx_tcp_send_context_init.c \
	$(COMMON_SRC_FILES)

zbx_tcp_send_context_init_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_send_context_init_LDADD += @AGENT_LIBS@

zbx_tcp_send_context_init_LDFLAGS = @AGENT_LDFLAGS@ \
	-Wl,--wrap=zbx_compress \
	$(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_tcp_send_context_init_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_gethost_by_ip

zbx_gethost_by_ip_SOURCES = \
	zbx_gethost_by_ip.c \
	$(COMMON_SRC_FILES)

zbx_gethost_by_ip_LDADD = \
	$(COMMS_LIBS)

zbx_gethost_by_ip_LDADD += @AGENT_LIBS@

zbx_gethost_by_ip_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_gethost_by_ip_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_getip_by_host

zbx_getip_by_host_SOURCES = \
	zbx_getip_by_host.c \
	$(COMMON_SRC_FILES)

zbx_getip_by_host_LDADD = \
	$(COMMS_LIBS)

zbx_getip_by_host_LDADD += @AGENT_LIBS@

zbx_getip_by_host_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_getip_by_host_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_tcp_listen

zbx_tcp_listen_SOURCES = \
	zbx_tcp_listen.c \
	$(COMMON_SRC_FILES)

zbx_tcp_listen_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_listen_LDADD += @AGENT_LIBS@

zbx_tcp_listen_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_tcp_listen_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_udp_send

zbx_udp_send_SOURCES = \
	zbx_udp_send.c \
	$(COMMON_SRC_FILES)

zbx_udp_send_LDADD = \
	$(COMMS_LIBS)

zbx_udp_send_LDADD += @AGENT_LIBS@

zbx_udp_send_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_udp_send_CFLAGS = $(COMMS_COMPILER_FLAGS)

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

