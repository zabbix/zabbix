/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#	define ZBX_TCP_WRITE(s, b, bl)	((ssize_t)send((s), (b), (bl), 0))
#	define ZBX_TCP_READ(s, b, bl)	((ssize_t)recv((s), (b), (bl), 0))
#	define zbx_sock_close(s)	if (ZBX_SOCK_ERROR != (s)) closesocket(s)
#	define zbx_sock_last_error()	WSAGetLastError()

#	define ZBX_TCP_ERROR		SOCKET_ERROR
#	define ZBX_SOCK_ERROR		INVALID_SOCKET
#else
#	define ZBX_TCP_WRITE(s, b, bl)	((ssize_t)write((s), (b), (bl)))
#	define ZBX_TCP_READ(s, b, bl)	((ssize_t)read((s), (b), (bl)))
#	define zbx_sock_close(s)	if (ZBX_SOCK_ERROR != (s)) close(s)
#	define zbx_sock_last_error()	errno

#	define ZBX_TCP_ERROR		-1
#	define ZBX_SOCK_ERROR		-1
#endif

#if defined(SOCKET) || defined(_WINDOWS)
typedef SOCKET	ZBX_SOCKET;
#else
typedef int	ZBX_SOCKET;
#endif

typedef enum
{
	ZBX_TCP_ERR_NETWORK = 1,
	ZBX_TCP_ERR_TIMEOUT
}
zbx_tcp_errors;

typedef enum
{
	ZBX_BUF_TYPE_STAT = 0,
	ZBX_BUF_TYPE_DYN
}
zbx_buf_type_t;

#define ZBX_SOCKET_COUNT	256
#define ZBX_STAT_BUF_LEN	2048

typedef struct
{
	int		num_socks;
	ZBX_SOCKET	sockets[ZBX_SOCKET_COUNT];
	ZBX_SOCKET	socket;
	ZBX_SOCKET	socket_orig;
	char		buf_stat[ZBX_STAT_BUF_LEN];
	char		*buf_dyn;
	zbx_buf_type_t	buf_type;
	unsigned char	accepted;
	char		*error;
	int		timeout;
}
zbx_sock_t;

const char	*zbx_tcp_strerror();

#if !defined(_WINDOWS)
void	zbx_gethost_by_ip(const char *ip, char *host, size_t hostlen);
#endif

void	zbx_tcp_init(zbx_sock_t *s, ZBX_SOCKET o);
int     zbx_tcp_connect(zbx_sock_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout);

#define ZBX_TCP_PROTOCOL	0x01

#define zbx_tcp_send(s, d)		zbx_tcp_send_ext((s), (d), ZBX_TCP_PROTOCOL, 0)
#define zbx_tcp_send_to(s, d, timeout)	zbx_tcp_send_ext((s), (d), ZBX_TCP_PROTOCOL, timeout)
#define zbx_tcp_send_raw(s, d)		zbx_tcp_send_ext((s), (d), 0, 0)

int     zbx_tcp_send_ext(zbx_sock_t *s, const char *data, unsigned char flags, int timeout);

void    zbx_tcp_close(zbx_sock_t *s);

#if defined(HAVE_IPV6)
int	get_address_family(const char *addr, int *family, char *error, int max_error_len);
#endif

int	zbx_tcp_listen(zbx_sock_t *s, const char *listen_ip, unsigned short listen_port);

int	zbx_tcp_accept(zbx_sock_t *s);
void	zbx_tcp_unaccept(zbx_sock_t *s);

void    zbx_tcp_free(zbx_sock_t *s);

#define ZBX_TCP_READ_UNTIL_CLOSE 0x01

#define	zbx_tcp_recv(s, data) 			SUCCEED_OR_FAIL(zbx_tcp_recv_ext(s, data, 0, 0))
#define	zbx_tcp_recv_to(s, data, timeout) 	SUCCEED_OR_FAIL(zbx_tcp_recv_ext(s, data, 0, timeout))

ssize_t	zbx_tcp_recv_ext(zbx_sock_t *s, char **data, unsigned char flags, int timeout);

char    *get_ip_by_socket(zbx_sock_t *s);
int	zbx_tcp_check_security(zbx_sock_t *s, const char *ip_list, int allow_if_empty);

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

int	zbx_send_response_ext(zbx_sock_t *sock, int result, const char *info, int protocol, int timeout);

#define zbx_send_response(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, ZBX_TCP_PROTOCOL, timeout)

#define zbx_send_response_raw(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, 0, timeout)

int	zbx_recv_response(zbx_sock_t *sock, char **info, int timeout, char **error);

#if defined(HAVE_IPV6)
#define zbx_getnameinfo(sa, host, hostlen, serv, servlen, flags)						\
	getnameinfo(sa, AF_INET == (sa)->sa_family ? sizeof(struct sockaddr_in) : sizeof(struct sockaddr_in6),	\
		host, hostlen, serv, servlen, flags)
#endif

#endif
