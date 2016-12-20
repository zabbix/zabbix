/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_COMMS_H
#define ZABBIX_COMMS_H

#if defined(_WINDOWS)
#	if defined(__INT_MAX__) && __INT_MAX__ == 2147483647
typedef int	ssize_t;
#	else
typedef long	ssize_t;
#	endif
#endif

#if defined(_WINDOWS)
#	define ZBX_TCP_WRITE(s, b, bl)	((ssize_t)send((s), (b), (bl), 0))
#	define ZBX_TCP_READ(s, b, bl)	((ssize_t)recv((s), (b), (bl), 0))
#	define zbx_socket_close(s)	if (ZBX_SOCKET_ERROR != (s)) closesocket(s)
#	define zbx_socket_last_error()	WSAGetLastError()

#	define ZBX_PROTO_AGAIN		WSAEINTR
#	define ZBX_PROTO_ERROR		SOCKET_ERROR
#	define ZBX_SOCKET_ERROR		INVALID_SOCKET
#else
#	define ZBX_TCP_WRITE(s, b, bl)	((ssize_t)write((s), (b), (bl)))
#	define ZBX_TCP_READ(s, b, bl)	((ssize_t)read((s), (b), (bl)))
#	define zbx_socket_close(s)	if (ZBX_SOCKET_ERROR != (s)) close(s)
#	define zbx_socket_last_error()	errno

#	define ZBX_PROTO_AGAIN		EINTR
#	define ZBX_PROTO_ERROR		-1
#	define ZBX_SOCKET_ERROR		-1
#endif

#if defined(SOCKET) || defined(_WINDOWS)
typedef SOCKET	ZBX_SOCKET;
#	define ZBX_SOCKET_TO_INT(s)	((int)(s))
#else
typedef int	ZBX_SOCKET;
#	define ZBX_SOCKET_TO_INT(s)	(s)
#endif

typedef enum
{
	ZBX_BUF_TYPE_STAT = 0,
	ZBX_BUF_TYPE_DYN
}
zbx_buf_type_t;

#define ZBX_SOCKET_COUNT	256
#define ZBX_STAT_BUF_LEN	2048
#define ZBX_SOCKET_PEER_BUF_LEN	129

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
typedef struct zbx_tls_context	zbx_tls_context_t;
#endif

typedef struct
{
	ZBX_SOCKET			socket;
	ZBX_SOCKET			socket_orig;
	size_t				read_bytes;
	char				*buffer;
	char				*next_line;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_context_t		*tls_ctx;
#endif
	unsigned int 			connection_type;	/* type of connection actually established: */
								/* ZBX_TCP_SEC_UNENCRYPTED, ZBX_TCP_SEC_TLS_PSK or */
								/* ZBX_TCP_SEC_TLS_CERT */
	int				timeout;
	zbx_buf_type_t			buf_type;
	unsigned char			accepted;
	int				num_socks;
	ZBX_SOCKET			sockets[ZBX_SOCKET_COUNT];
	char				buf_stat[ZBX_STAT_BUF_LEN];
	/* Peer hostname or IP address for diagnostics (after TCP connection is established). */
	/* TLS connection may be shut down at any time and it will not be possible to get peer IP address anymore. */
	char				peer[ZBX_SOCKET_PEER_BUF_LEN];
}
zbx_socket_t;

const char	*zbx_socket_strerror(void);

#if !defined(_WINDOWS)
void	zbx_gethost_by_ip(const char *ip, char *host, size_t hostlen);
#endif

int	zbx_tcp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout,
		unsigned int tls_connect, char *tls_arg1, char *tls_arg2);

#define ZBX_TCP_PROTOCOL	0x01

#define ZBX_TCP_SEC_UNENCRYPTED		1		/* do not use encryption with this socket */
#define ZBX_TCP_SEC_TLS_PSK		2		/* use TLS with pre-shared key (PSK) with this socket */
#define ZBX_TCP_SEC_TLS_CERT		4		/* use TLS with certificate with this socket */
#define ZBX_TCP_SEC_UNENCRYPTED_TXT	"unencrypted"
#define ZBX_TCP_SEC_TLS_PSK_TXT		"psk"
#define ZBX_TCP_SEC_TLS_CERT_TXT	"cert"

const char	*zbx_tcp_connection_type_name(unsigned int type);

#define zbx_tcp_send(s, d)				zbx_tcp_send_ext((s), (d), strlen(d), ZBX_TCP_PROTOCOL, 0)
#define zbx_tcp_send_to(s, d, timeout)			zbx_tcp_send_ext((s), (d), strlen(d), ZBX_TCP_PROTOCOL, timeout)
#define zbx_tcp_send_bytes_to(s, d, len, timeout)	zbx_tcp_send_ext((s), (d), len, ZBX_TCP_PROTOCOL, timeout)
#define zbx_tcp_send_raw(s, d)				zbx_tcp_send_ext((s), (d), strlen(d), 0, 0)

int	zbx_tcp_send_ext(zbx_socket_t *s, const char *data, size_t len, unsigned char flags, int timeout);

void	zbx_tcp_close(zbx_socket_t *s);

#if defined(HAVE_IPV6)
int	get_address_family(const char *addr, int *family, char *error, int max_error_len);
#endif

int	zbx_tcp_listen(zbx_socket_t *s, const char *listen_ip, unsigned short listen_port);

int	zbx_tcp_accept(zbx_socket_t *s, unsigned int tls_accept);
void	zbx_tcp_unaccept(zbx_socket_t *s);

#define ZBX_TCP_READ_UNTIL_CLOSE 0x01

#define	zbx_tcp_recv(s) 		SUCCEED_OR_FAIL(zbx_tcp_recv_ext(s, 0, 0))
#define	zbx_tcp_recv_to(s, timeout) 	SUCCEED_OR_FAIL(zbx_tcp_recv_ext(s, 0, timeout))

ssize_t		zbx_tcp_recv_ext(zbx_socket_t *s, unsigned char flags, int timeout);
const char	*zbx_tcp_recv_line(zbx_socket_t *s);

int	zbx_tcp_check_security(zbx_socket_t *s, const char *ip_list, int allow_if_empty);

int	zbx_udp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout);
int	zbx_udp_send(zbx_socket_t *s, const char *data, size_t data_len, int timeout);
int	zbx_udp_recv(zbx_socket_t *s, int timeout);
void	zbx_udp_close(zbx_socket_t *s);

#define ZBX_DEFAULT_FTP_PORT		21
#define ZBX_DEFAULT_SSH_PORT		22
#define ZBX_DEFAULT_TELNET_PORT		23
#define ZBX_DEFAULT_SMTP_PORT		25
#define ZBX_DEFAULT_DNS_PORT		53
#define ZBX_DEFAULT_HTTP_PORT		80
#define ZBX_DEFAULT_POP_PORT		110
#define ZBX_DEFAULT_NNTP_PORT		119
#define ZBX_DEFAULT_NTP_PORT		123
#define ZBX_DEFAULT_IMAP_PORT		143
#define ZBX_DEFAULT_LDAP_PORT		389
#define ZBX_DEFAULT_HTTPS_PORT		443
#define ZBX_DEFAULT_AGENT_PORT		10050
#define ZBX_DEFAULT_SERVER_PORT		10051
#define ZBX_DEFAULT_GATEWAY_PORT	10052

#define ZBX_DEFAULT_AGENT_PORT_STR	"10050"
#define ZBX_DEFAULT_SERVER_PORT_STR	"10051"

int	zbx_send_response_ext(zbx_socket_t *sock, int result, const char *info, int protocol, int timeout);

#define zbx_send_response(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, ZBX_TCP_PROTOCOL, timeout)

#define zbx_send_response_raw(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, 0, timeout)

int	zbx_recv_response(zbx_socket_t *sock, int timeout, char **error);

#if defined(HAVE_IPV6)
#define zbx_getnameinfo(sa, host, hostlen, serv, servlen, flags)						\
	getnameinfo(sa, AF_INET == (sa)->sa_family ? sizeof(struct sockaddr_in) : sizeof(struct sockaddr_in6),	\
		host, hostlen, serv, servlen, flags)
#endif

#endif
