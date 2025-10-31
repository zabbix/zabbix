include ../Makefile.include

COMMS_tests = zbx_validate_peer_list \
	zbx_tcp_connection_type_name \
	socket_poll_error \
	zbx_tcp_send_context_init \
	zbx_gethost_by_ip \
	zbx_getip_by_host \
	zbx_tcp_listen \
	zbx_udp_send \
	zbx_tcp_recv_line \
	zbx_socket_pollout \
	zbx_tcp_send_context \
	zbx_tcp_accept \
	zbx_udp_recv \
	zbx_tcp_write \
	zbx_tcp_read_close_notify

if IPV6
noinst_PROGRAMS = zbx_tcp_check_allowed_peers $(COMMS_tests)
else
noinst_PROGRAMS = zbx_tcp_check_allowed_peers_ipv4 $(COMMS_tests)
endif

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
	zbx_comms_common.c \
	$(COMMON_SRC_FILES)

zbx_udp_send_LDADD = \
	$(COMMS_LIBS)

zbx_udp_send_LDADD += @AGENT_LIBS@

zbx_udp_send_LDFLAGS = @AGENT_LDFLAGS@ \
	-Wl,--wrap=sendto \
	$(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_udp_send_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_tcp_recv_line

zbx_tcp_recv_line_SOURCES = \
	zbx_tcp_recv_line.c \
	zbx_comms_common.c \
	$(COMMON_SRC_FILES)

zbx_tcp_recv_line_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_recv_line_LDADD += @AGENT_LIBS@

zbx_tcp_recv_line_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_tcp_recv_line_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_socket_pollout

zbx_socket_pollout_SOURCES = \
	zbx_socket_pollout.c \
	zbx_comms_common.c \
	$(COMMON_SRC_FILES)

zbx_socket_pollout_LDADD = \
	$(COMMS_LIBS)

zbx_socket_pollout_LDADD += @AGENT_LIBS@

zbx_socket_pollout_LDFLAGS = @AGENT_LDFLAGS@ \
	$(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=zbx_ts_check_deadline

zbx_socket_pollout_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_tcp_write

zbx_tcp_write_SOURCES = \
	zbx_tcp_write.c \
	zbx_comms_common.c \
	$(COMMON_SRC_FILES)

zbx_tcp_write_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_write_LDADD += @AGENT_LIBS@

zbx_tcp_write_LDFLAGS = @AGENT_LDFLAGS@ \
	$(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=SSL_write \
	-Wl,--wrap=write

zbx_tcp_write_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_tcp_read_close_notify

zbx_tcp_read_close_notify_SOURCES = \
	zbx_tcp_read_close_notify.c \
	$(COMMON_SRC_FILES)

zbx_tcp_read_close_notify_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_read_close_notify_LDADD += @AGENT_LIBS@

zbx_tcp_read_close_notify_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_tcp_read_close_notify_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_tcp_send_context

zbx_tcp_send_context_SOURCES = \
	zbx_tcp_send_context.c \
	zbx_comms_common.c \
	$(COMMON_SRC_FILES)

zbx_tcp_send_context_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_send_context_LDADD += @AGENT_LIBS@

zbx_tcp_send_context_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=SSL_write \
	-Wl,--wrap=write

zbx_tcp_send_context_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_tcp_accept

zbx_tcp_accept_SOURCES = \
	zbx_tcp_accept.c \
	zbx_comms_common.c \
	$(COMMON_SRC_FILES)

zbx_tcp_accept_LDADD = \
	$(COMMS_LIBS)

zbx_tcp_accept_LDADD += @AGENT_LIBS@

zbx_tcp_accept_LDFLAGS = @AGENT_LDFLAGS@ \
	$(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=accept \
	-Wl,--wrap=fcntl \
	-Wl,--wrap=getpeername \
	-Wl,--wrap=recv

zbx_tcp_accept_CFLAGS = $(COMMS_COMPILER_FLAGS)

#zbx_udp_recv

zbx_udp_recv_SOURCES = \
	zbx_udp_recv.c \
	zbx_comms_common.c \
	$(COMMON_SRC_FILES)

zbx_udp_recv_LDADD = \
	$(COMMS_LIBS)

zbx_udp_recv_LDADD += @AGENT_LIBS@

zbx_udp_recv_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	-Wl,--wrap=recvfrom

zbx_udp_recv_CFLAGS = $(COMMS_COMPILER_FLAGS)

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

