/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_COMMS_H
#define ZABBIX_COMMS_H

typedef enum
{
	ZBX_TCP_ERR_NETWORK = 1,
	ZBX_TCP_ERR_TIMEOUT
}
zbx_tcp_errors;

#if defined(SOCKET) || defined(_WINDOWS)
	typedef SOCKET ZBX_SOCKET;
#else /* not SOCKET && not _WINDOWS */
	typedef int ZBX_SOCKET;
#endif /* SOCKET || _WINDOWS */

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

#define ZBX_TCP_NEW_PROTOCOL	0x01

#define zbx_tcp_send(s, d)		zbx_tcp_send_ext((s), (d), ZBX_TCP_NEW_PROTOCOL, 0)
#define zbx_tcp_send_to(s, d, timeout)	zbx_tcp_send_ext((s), (d), ZBX_TCP_NEW_PROTOCOL, timeout)
#define zbx_tcp_send_raw(s, d)		zbx_tcp_send_ext((s), (d), 0, 0)

int     zbx_tcp_send_ext(zbx_sock_t *s, const char *data, unsigned char flags, int timeout);

void    zbx_tcp_close(zbx_sock_t *s);

#if defined(HAVE_IPV6)
int	get_address_family(const char *addr, int *family, char *error, int max_error_len);
#endif /* HAVE_IPV6 */

int	zbx_tcp_listen(zbx_sock_t *s, const char *listen_ip, unsigned short listen_port);

int	zbx_tcp_accept(zbx_sock_t *s);
void	zbx_tcp_unaccept(zbx_sock_t *s);

void    zbx_tcp_free(zbx_sock_t *s);

#define ZBX_TCP_READ_UNTIL_CLOSE 0x01

#define	zbx_tcp_recv(s, data) 			zbx_tcp_recv_ext(s, data, 0, 0)
#define	zbx_tcp_recv_to(s, data, timeout) 	zbx_tcp_recv_ext(s, data, 0, timeout)

int	zbx_tcp_recv_ext(zbx_sock_t *s, char **data, unsigned char flags, int timeout);

char    *get_ip_by_socket(zbx_sock_t *s);
int	zbx_tcp_check_security(zbx_sock_t *s, const char *ip_list, int allow_if_empty);

#define	ZBX_DEFAULT_FTP_PORT	21
#define	ZBX_DEFAULT_SSH_PORT	22
#define	ZBX_DEFAULT_TELNET_PORT	23
#define	ZBX_DEFAULT_SMTP_PORT	25
#define	ZBX_DEFAULT_HTTP_PORT	80
#define	ZBX_DEFAULT_POP_PORT	110
#define	ZBX_DEFAULT_NNTP_PORT	119
#define	ZBX_DEFAULT_NTP_PORT	123
#define	ZBX_DEFAULT_IMAP_PORT	143
#define	ZBX_DEFAULT_LDAP_PORT	389

int	zbx_send_response(zbx_sock_t *sock, int result, const char *info, int timeout);
int	zbx_recv_response(zbx_sock_t *sock, char *info, int max_info_len, int timeout);

#endif
