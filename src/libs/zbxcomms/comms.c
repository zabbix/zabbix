/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"
#include "comms.h"
#include "log.h"
#include "../zbxcrypto/tls_tcp.h"
#include "zbxcompress.h"

#define IPV4_MAX_CIDR_PREFIX	32	/* max number of bits in IPv4 CIDR prefix */
#define IPV6_MAX_CIDR_PREFIX	128	/* max number of bits in IPv6 CIDR prefix */

#ifndef ZBX_SOCKLEN_T
#	define ZBX_SOCKLEN_T socklen_t
#endif

#ifndef SOCK_CLOEXEC
#	define SOCK_CLOEXEC 0	/* SOCK_CLOEXEC is Linux-specific, available since 2.6.23 */
#endif

#ifdef HAVE_OPENSSL
extern ZBX_THREAD_LOCAL char	info_buf[256];
#endif

extern int	CONFIG_TIMEOUT;

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_strerror                                              *
 *                                                                            *
 * Purpose: return string describing tcp error                                *
 *                                                                            *
 * Return value: pointer to the null terminated string                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/

#define ZBX_SOCKET_STRERROR_LEN	512

static char	zbx_socket_strerror_message[ZBX_SOCKET_STRERROR_LEN];

const char	*zbx_socket_strerror(void)
{
	zbx_socket_strerror_message[ZBX_SOCKET_STRERROR_LEN - 1] = '\0';	/* force null termination */
	return zbx_socket_strerror_message;
}

#ifdef HAVE___VA_ARGS__
#	define zbx_set_socket_strerror(fmt, ...) __zbx_zbx_set_socket_strerror(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_set_socket_strerror __zbx_zbx_set_socket_strerror
#endif
static void	__zbx_zbx_set_socket_strerror(const char *fmt, ...)
{
	va_list args;

	va_start(args, fmt);

	zbx_vsnprintf(zbx_socket_strerror_message, sizeof(zbx_socket_strerror_message), fmt, args);

	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_peer_ip_save                                          *
 *                                                                            *
 * Purpose: get peer IP address info from a socket early while it is          *
 *          connected. Connection can be terminated due to various errors at  *
 *          any time and peer IP address will not be available anymore.       *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_socket_peer_ip_save(zbx_socket_t *s)
{
	ZBX_SOCKADDR	sa;
	ZBX_SOCKLEN_T	sz = sizeof(sa);
	char		*error_message = NULL;

	if (ZBX_PROTO_ERROR == getpeername(s->socket, (struct sockaddr *)&sa, &sz))
	{
		error_message = strerror_from_system(zbx_socket_last_error());
		zbx_set_socket_strerror("connection rejected, getpeername() failed: %s", error_message);
		return FAIL;
	}

	/* store getpeername() result to have IP address in numerical form for security check */
	memcpy(&s->peer_info, &sa, (size_t)sz);

	/* store IP address as a text string for error reporting */

#ifdef HAVE_IPV6
	if (0 != zbx_getnameinfo((struct sockaddr *)&sa, s->peer, sizeof(s->peer), NULL, 0, NI_NUMERICHOST))
	{
		error_message = strerror_from_system(zbx_socket_last_error());
		zbx_set_socket_strerror("connection rejected, getnameinfo() failed: %s", error_message);
		return FAIL;
	}
#else
	strscpy(s->peer, inet_ntoa(sa.sin_addr));
#endif
	return SUCCEED;
}

#ifndef _WINDOWS
/******************************************************************************
 *                                                                            *
 * Function: zbx_gethost_by_ip                                                *
 *                                                                            *
 * Purpose: retrieve 'hostent' by IP address                                  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
#ifdef HAVE_IPV6
void	zbx_gethost_by_ip(const char *ip, char *host, size_t hostlen)
{
	struct addrinfo	hints, *ai = NULL;

	assert(ip);

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = PF_UNSPEC;

	if (0 != getaddrinfo(ip, NULL, &hints, &ai))
	{
		host[0] = '\0';
		goto out;
	}

	if (0 != getnameinfo(ai->ai_addr, ai->ai_addrlen, host, hostlen, NULL, 0, NI_NAMEREQD))
	{
		host[0] = '\0';
		goto out;
	}
out:
	if (NULL != ai)
		freeaddrinfo(ai);
}
#else
void	zbx_gethost_by_ip(const char *ip, char *host, size_t hostlen)
{
	struct in_addr	addr;
	struct hostent  *hst;

	assert(ip);

	if (0 == inet_aton(ip, &addr))
	{
		host[0] = '\0';
		return;
	}

	if (NULL == (hst = gethostbyaddr((char *)&addr, sizeof(addr), AF_INET)))
	{
		host[0] = '\0';
		return;
	}

	zbx_strlcpy(host, hst->h_name, hostlen);
}
#endif	/* HAVE_IPV6 */
#endif	/* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_start                                                 *
 *                                                                            *
 * Purpose: Initialize Windows Sockets APIs                                   *
 *                                                                            *
 * Return value: SUCCEED or FAIL - an error occurred                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
#ifdef _WINDOWS

#define ZBX_SOCKET_START()	if (FAIL == socket_started) socket_started = zbx_socket_start()

int	socket_started = FAIL;	/* winXX threads require socket_started not to be static */

static int	zbx_socket_start()
{
	WSADATA	sockInfo;
	int	ret;

	if (0 != (ret = WSAStartup(MAKEWORD(2, 2), &sockInfo)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "WSAStartup() failed: %s", strerror_from_system(ret));
		return FAIL;
	}

	return SUCCEED;
}

#else
#	define ZBX_SOCKET_START()
#endif

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_clean                                                 *
 *                                                                            *
 * Purpose: initialize socket                                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_socket_clean(zbx_socket_t *s)
{
	memset(s, 0, sizeof(zbx_socket_t));

	s->buf_type = ZBX_BUF_TYPE_STAT;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_free                                                  *
 *                                                                            *
 * Purpose: free socket's dynamic buffer                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_socket_free(zbx_socket_t *s)
{
	if (ZBX_BUF_TYPE_DYN == s->buf_type)
		zbx_free(s->buffer);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_timeout_set                                           *
 *                                                                            *
 * Purpose: set timeout for socket operations                                 *
 *                                                                            *
 * Parameters: s       - [IN] socket descriptor                               *
 *             timeout - [IN] timeout, in seconds                             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	zbx_socket_timeout_set(zbx_socket_t *s, int timeout)
{
	s->timeout = timeout;
#ifdef _WINDOWS
	timeout *= 1000;

	if (ZBX_PROTO_ERROR == setsockopt(s->socket, SOL_SOCKET, SO_RCVTIMEO, (const char *)&timeout, sizeof(timeout)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "setsockopt() failed for SO_RCVTIMEO: %s",
				strerror_from_system(zbx_socket_last_error()));
	}

	if (ZBX_PROTO_ERROR == setsockopt(s->socket, SOL_SOCKET, SO_SNDTIMEO, (const char *)&timeout, sizeof(timeout)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "setsockopt() failed for SO_SNDTIMEO: %s",
				strerror_from_system(zbx_socket_last_error()));
	}
#else
	zbx_alarm_on(timeout);
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_timeout_cleanup                                       *
 *                                                                            *
 * Purpose: clean up timeout for socket operations                            *
 *                                                                            *
 * Parameters: s - [OUT] socket descriptor                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	zbx_socket_timeout_cleanup(zbx_socket_t *s)
{
#ifndef _WINDOWS
	if (0 != s->timeout)
	{
		zbx_alarm_off();
		s->timeout = 0;
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_connect                                               *
 *                                                                            *
 * Purpose: connect to the specified address with an optional timeout value   *
 *                                                                            *
 * Parameters: s       - [IN] socket descriptor                               *
 *             addr    - [IN] the address                                     *
 *             addrlen - [IN] the length of addr structure                    *
 *             timeout - [IN] the connection timeout (0 - system default)     *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - connected successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Comments: Windows connect implementation uses internal timeouts which      *
 *           cannot be changed. Because of that in Windows use nonblocking    *
 *           connect, then wait for connection the specified timeout period   *
 *           and if successful change socket back to blocking mode.           *
 *                                                                            *
 ******************************************************************************/
static int	zbx_socket_connect(zbx_socket_t *s, const struct sockaddr *addr, socklen_t addrlen, int timeout,
		char **error)
{
#ifdef _WINDOWS
	u_long		mode = 1;
	FD_SET		fdw, fde;
	int		res;
	struct timeval	tv, *ptv;
#endif
	if (0 != timeout)
		zbx_socket_timeout_set(s, timeout);

#ifdef _WINDOWS
	if (0 != ioctlsocket(s->socket, FIONBIO, &mode))
	{
		*error = zbx_strdup(*error, strerror_from_system(zbx_socket_last_error()));
		return FAIL;
	}

	FD_ZERO(&fdw);
	FD_SET(s->socket, &fdw);

	FD_ZERO(&fde);
	FD_SET(s->socket, &fde);

	if (0 != timeout)
	{
		tv.tv_sec = timeout;
		tv.tv_usec = 0;
		ptv = &tv;
	}
	else
		ptv = NULL;

	if (ZBX_PROTO_ERROR == connect(s->socket, addr, addrlen) && WSAEWOULDBLOCK != zbx_socket_last_error())
	{
		*error = zbx_strdup(*error, strerror_from_system(zbx_socket_last_error()));
		return FAIL;
	}

	if (-1 == (res = select(0, NULL, &fdw, &fde, ptv)))
	{
		*error = zbx_strdup(*error, strerror_from_system(zbx_socket_last_error()));
		return FAIL;
	}

	if (0 == FD_ISSET(s->socket, &fdw))
	{
		if (0 != FD_ISSET(s->socket, &fde))
		{
			int socket_error = 0;
			int socket_error_len = sizeof(int);

			if (ZBX_PROTO_ERROR != getsockopt(s->socket, SOL_SOCKET,
				SO_ERROR, (char *)&socket_error, &socket_error_len))
			{
				if (socket_error == WSAECONNREFUSED)
					*error = zbx_strdup(*error, "Connection refused.");
				else if (socket_error == WSAETIMEDOUT)
					*error = zbx_strdup(*error, "A connection timeout occurred.");
				else
					*error = zbx_strdup(*error, strerror_from_system(socket_error));
			}
			else
			{
				*error = zbx_dsprintf(*error, "Cannot obtain error code: %s",
						strerror_from_system(zbx_socket_last_error()));
			}
		}

		return FAIL;
	}

	mode = 0;
	if (0 != ioctlsocket(s->socket, FIONBIO, &mode))
	{
		*error = zbx_strdup(*error, strerror_from_system(zbx_socket_last_error()));
		return FAIL;
	}
#else
	if (ZBX_PROTO_ERROR == connect(s->socket, addr, addrlen))
	{
		*error = zbx_strdup(*error, strerror_from_system(zbx_socket_last_error()));
		return FAIL;
	}
#endif
	s->connection_type = ZBX_TCP_SEC_UNENCRYPTED;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_create                                                *
 *                                                                            *
 * Purpose: connect the socket of the specified type to external host         *
 *                                                                            *
 * Parameters: s - [OUT] socket descriptor                                    *
 *                                                                            *
 * Return value: SUCCEED - connected successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
#ifdef HAVE_IPV6
static int	zbx_socket_create(zbx_socket_t *s, int type, const char *source_ip, const char *ip, unsigned short port,
		int timeout, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2)
{
	int		ret = FAIL;
	struct addrinfo	*ai = NULL, hints;
	struct addrinfo	*ai_bind = NULL;
	char		service[8], *error = NULL;
	void		(*func_socket_close)(zbx_socket_t *s);

	if (SOCK_DGRAM == type && (ZBX_TCP_SEC_TLS_CERT == tls_connect || ZBX_TCP_SEC_TLS_PSK == tls_connect))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_PSK == tls_connect && '\0' == *tls_arg1)
	{
		zbx_set_socket_strerror("cannot connect with PSK: PSK not available");
		return FAIL;
	}
#else
	if (ZBX_TCP_SEC_TLS_CERT == tls_connect || ZBX_TCP_SEC_TLS_PSK == tls_connect)
	{
		zbx_set_socket_strerror("support for TLS was not compiled in");
		return FAIL;
	}
#endif
	ZBX_SOCKET_START();

	zbx_socket_clean(s);

	zbx_snprintf(service, sizeof(service), "%hu", port);
	memset(&hints, 0x00, sizeof(struct addrinfo));
	hints.ai_family = PF_UNSPEC;
	hints.ai_socktype = type;

	if (0 != getaddrinfo(ip, service, &hints, &ai))
	{
		zbx_set_socket_strerror("cannot resolve [%s]", ip);
		goto out;
	}

	if (ZBX_SOCKET_ERROR == (s->socket = socket(ai->ai_family, ai->ai_socktype | SOCK_CLOEXEC, ai->ai_protocol)))
	{
		zbx_set_socket_strerror("cannot create socket [[%s]:%hu]: %s",
				ip, port, strerror_from_system(zbx_socket_last_error()));
		goto out;
	}

#if !defined(_WINDOWS) && !SOCK_CLOEXEC
	fcntl(s->socket, F_SETFD, FD_CLOEXEC);
#endif
	func_socket_close = (SOCK_STREAM == type ? zbx_tcp_close : zbx_udp_close);

	if (NULL != source_ip)
	{
		memset(&hints, 0x00, sizeof(struct addrinfo));

		hints.ai_family = PF_UNSPEC;
		hints.ai_socktype = type;
		hints.ai_flags = AI_NUMERICHOST;

		if (0 != getaddrinfo(source_ip, NULL, &hints, &ai_bind))
		{
			zbx_set_socket_strerror("invalid source IP address [%s]", source_ip);
			func_socket_close(s);
			goto out;
		}

		if (ZBX_PROTO_ERROR == zbx_bind(s->socket, ai_bind->ai_addr, ai_bind->ai_addrlen))
		{
			zbx_set_socket_strerror("bind() failed: %s", strerror_from_system(zbx_socket_last_error()));
			func_socket_close(s);
			goto out;
		}
	}

	if (SUCCEED != zbx_socket_connect(s, ai->ai_addr, (socklen_t)ai->ai_addrlen, timeout, &error))
	{
		func_socket_close(s);
		zbx_set_socket_strerror("cannot connect to [[%s]:%hu]: %s", ip, port, error);
		zbx_free(error);
		goto out;
	}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if ((ZBX_TCP_SEC_TLS_CERT == tls_connect || ZBX_TCP_SEC_TLS_PSK == tls_connect) &&
			SUCCEED != zbx_tls_connect(s, tls_connect, tls_arg1, tls_arg2, &error))
	{
		zbx_tcp_close(s);
		zbx_set_socket_strerror("TCP successful, cannot establish TLS to [[%s]:%hu]: %s", ip, port, error);
		zbx_free(error);
		goto out;
	}
#else
	ZBX_UNUSED(tls_arg1);
	ZBX_UNUSED(tls_arg2);
#endif
	zbx_strlcpy(s->peer, ip, sizeof(s->peer));

	ret = SUCCEED;
out:
	if (NULL != ai)
		freeaddrinfo(ai);

	if (NULL != ai_bind)
		freeaddrinfo(ai_bind);

	return ret;
}
#else
static int	zbx_socket_create(zbx_socket_t *s, int type, const char *source_ip, const char *ip, unsigned short port,
		int timeout, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2)
{
	ZBX_SOCKADDR	servaddr_in;
	struct hostent	*hp;
	char		*error = NULL;
	void		(*func_socket_close)(zbx_socket_t *s);

	if (SOCK_DGRAM == type && (ZBX_TCP_SEC_TLS_CERT == tls_connect || ZBX_TCP_SEC_TLS_PSK == tls_connect))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_PSK == tls_connect && '\0' == *tls_arg1)
	{
		zbx_set_socket_strerror("cannot connect with PSK: PSK not available");
		return FAIL;
	}
#else
	if (ZBX_TCP_SEC_TLS_CERT == tls_connect || ZBX_TCP_SEC_TLS_PSK == tls_connect)
	{
		zbx_set_socket_strerror("support for TLS was not compiled in");
		return FAIL;
	}
#endif
	ZBX_SOCKET_START();

	zbx_socket_clean(s);

	if (NULL == (hp = gethostbyname(ip)))
	{
#ifdef _WINDOWS
		zbx_set_socket_strerror("gethostbyname() failed for '%s': %s",
				ip, strerror_from_system(WSAGetLastError()));
#else
#ifdef HAVE_HSTRERROR
		zbx_set_socket_strerror("gethostbyname() failed for '%s': [%d] %s",
				ip, h_errno, hstrerror(h_errno));
#else
		zbx_set_socket_strerror("gethostbyname() failed for '%s': [%d]",
				ip, h_errno);
#endif
#endif
		return FAIL;
	}

	servaddr_in.sin_family = AF_INET;
	servaddr_in.sin_addr.s_addr = ((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port = htons(port);

	if (ZBX_SOCKET_ERROR == (s->socket = socket(AF_INET, type | SOCK_CLOEXEC, 0)))
	{
		zbx_set_socket_strerror("cannot create socket [[%s]:%hu]: %s",
				ip, port, strerror_from_system(zbx_socket_last_error()));
		return FAIL;
	}

#if !defined(_WINDOWS) && !SOCK_CLOEXEC
	fcntl(s->socket, F_SETFD, FD_CLOEXEC);
#endif
	func_socket_close = (SOCK_STREAM == type ? zbx_tcp_close : zbx_udp_close);

	if (NULL != source_ip)
	{
		ZBX_SOCKADDR	source_addr;

		memset(&source_addr, 0, sizeof(source_addr));

		source_addr.sin_family = AF_INET;
		source_addr.sin_addr.s_addr = inet_addr(source_ip);
		source_addr.sin_port = 0;

		if (ZBX_PROTO_ERROR == bind(s->socket, (struct sockaddr *)&source_addr, sizeof(source_addr)))
		{
			zbx_set_socket_strerror("bind() failed: %s", strerror_from_system(zbx_socket_last_error()));
			func_socket_close(s);
			return FAIL;
		}
	}

	if (SUCCEED != zbx_socket_connect(s, (struct sockaddr *)&servaddr_in, sizeof(servaddr_in), timeout, &error))
	{
		func_socket_close(s);
		zbx_set_socket_strerror("cannot connect to [[%s]:%hu]: %s", ip, port, error);
		zbx_free(error);
		return FAIL;
	}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if ((ZBX_TCP_SEC_TLS_CERT == tls_connect || ZBX_TCP_SEC_TLS_PSK == tls_connect) &&
			SUCCEED != zbx_tls_connect(s, tls_connect, tls_arg1, tls_arg2, &error))
	{
		zbx_tcp_close(s);
		zbx_set_socket_strerror("TCP successful, cannot establish TLS to [[%s]:%hu]: %s", ip, port, error);
		zbx_free(error);
		return FAIL;
	}
#else
	ZBX_UNUSED(tls_arg1);
	ZBX_UNUSED(tls_arg2);
#endif
	zbx_strlcpy(s->peer, ip, sizeof(s->peer));

	return SUCCEED;
}
#endif	/* HAVE_IPV6 */

int	zbx_tcp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout,
		unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2)
{
	if (ZBX_TCP_SEC_UNENCRYPTED != tls_connect && ZBX_TCP_SEC_TLS_CERT != tls_connect &&
			ZBX_TCP_SEC_TLS_PSK != tls_connect)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	return zbx_socket_create(s, SOCK_STREAM, source_ip, ip, port, timeout, tls_connect, tls_arg1, tls_arg2);
}

static ssize_t	zbx_tcp_write(zbx_socket_t *s, const char *buf, size_t len)
{
	ssize_t	res;
	int	err;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char	*error = NULL;
#endif
#ifdef _WINDOWS
	double	sec;
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (NULL != s->tls_ctx)	/* TLS connection */
	{
		if (ZBX_PROTO_ERROR == (res = zbx_tls_write(s, buf, len, &error)))
		{
			zbx_set_socket_strerror("%s", error);
			zbx_free(error);
		}

		return res;
	}
#endif
#ifdef _WINDOWS
	zbx_alarm_flag_clear();
	sec = zbx_time();
#endif
	do
	{
		res = ZBX_TCP_WRITE(s->socket, buf, len);
#ifdef _WINDOWS
		if (s->timeout < zbx_time() - sec)
			zbx_alarm_flag_set();
#endif
		if (SUCCEED == zbx_alarm_timed_out())
		{
			zbx_set_socket_strerror("ZBX_TCP_WRITE() timed out");
			return ZBX_PROTO_ERROR;
		}
	}
	while (ZBX_PROTO_ERROR == res && ZBX_PROTO_AGAIN == (err = zbx_socket_last_error()));

	if (ZBX_PROTO_ERROR == res)
		zbx_set_socket_strerror("ZBX_TCP_WRITE() failed: %s", strerror_from_system(err));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_send_ext                                                 *
 *                                                                            *
 * Purpose: send data                                                         *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *     RFC 5246 "The Transport Layer Security (TLS) Protocol. Version 1.2"    *
 *     says: "The record layer fragments information blocks into TLSPlaintext *
 *     records carrying data in chunks of 2^14 bytes or less.".               *
 *                                                                            *
 *     This function combines sending of Zabbix protocol header (5 bytes),    *
 *     data length (8 bytes) and at least part of the message into one block  *
 *     of up to 16384 bytes for efficiency. The same is applied for sending   *
 *     unencrypted messages.                                                  *
 *                                                                            *
 ******************************************************************************/

#define ZBX_TCP_HEADER_DATA	"ZBXD"
#define ZBX_TCP_HEADER		ZBX_TCP_HEADER_DATA ZBX_TCP_HEADER_VERSION
#define ZBX_TCP_HEADER_LEN	ZBX_CONST_STRLEN(ZBX_TCP_HEADER_DATA)

int	zbx_tcp_send_ext(zbx_socket_t *s, const char *data, size_t len, unsigned char flags, int timeout)
{
#define ZBX_TLS_MAX_REC_LEN	16384

	ssize_t		bytes_sent = 0, written = 0;
	size_t		send_bytes, offset, send_len = len, reserved = 0;
	int		ret = SUCCEED;
	char		*compressed_data = NULL;
	zbx_uint32_t	len32_le;

	if (0 != timeout)
		zbx_socket_timeout_set(s, timeout);

	if (0 != (flags & ZBX_TCP_PROTOCOL))
	{
		size_t	take_bytes;
		char	header_buf[ZBX_TLS_MAX_REC_LEN];	/* Buffer is allocated on stack with a hope that it   */
								/* will be short-lived in CPU cache. Static buffer is */
								/* not used on purpose.				      */

		if (0 != (flags & ZBX_TCP_COMPRESS))
		{
			if (SUCCEED != zbx_compress(data, len, &compressed_data, &send_len))
			{
				zbx_set_socket_strerror("cannot compress data: %s", zbx_compress_strerror());
				ret = FAIL;
				goto cleanup;
			}

			data = compressed_data;
			reserved = len;
		}

		memcpy(header_buf, ZBX_TCP_HEADER_DATA, ZBX_CONST_STRLEN(ZBX_TCP_HEADER_DATA));
		offset = ZBX_CONST_STRLEN(ZBX_TCP_HEADER_DATA);

		header_buf[offset++] = flags;

		len32_le = zbx_htole_uint32((zbx_uint32_t)send_len);
		memcpy(header_buf + offset, &len32_le, sizeof(len32_le));
		offset += sizeof(len32_le);

		len32_le = zbx_htole_uint32((zbx_uint32_t)reserved);
		memcpy(header_buf + offset, &len32_le, sizeof(len32_le));
		offset += sizeof(len32_le);

		take_bytes = MIN(send_len, ZBX_TLS_MAX_REC_LEN - offset);
		memcpy(header_buf + offset, data, take_bytes);

		send_bytes = offset + take_bytes;

		while (written < (ssize_t)send_bytes)
		{
			if (ZBX_PROTO_ERROR == (bytes_sent = zbx_tcp_write(s, header_buf + written,
					send_bytes - (size_t)written)))
			{
				ret = FAIL;
				goto cleanup;
			}
			written += bytes_sent;
		}

		written -= offset;
	}

	while (written < (ssize_t)send_len)
	{
		if (ZBX_TCP_SEC_UNENCRYPTED == s->connection_type)
			send_bytes = send_len - (size_t)written;
		else
			send_bytes = MIN(ZBX_TLS_MAX_REC_LEN, send_len - (size_t)written);

		if (ZBX_PROTO_ERROR == (bytes_sent = zbx_tcp_write(s, data + written, send_bytes)))
		{
			ret = FAIL;
			goto cleanup;
		}
		written += bytes_sent;
	}
cleanup:
	zbx_free(compressed_data);

	if (0 != timeout)
		zbx_socket_timeout_cleanup(s);

	return ret;

#undef ZBX_TLS_MAX_REC_LEN
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_close                                                    *
 *                                                                            *
 * Purpose: close open TCP socket                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_tcp_close(zbx_socket_t *s)
{
	zbx_tcp_unaccept(s);

	zbx_socket_timeout_cleanup(s);

	zbx_socket_free(s);
	zbx_socket_close(s->socket);
}

/******************************************************************************
 *                                                                            *
 * Function: get_address_family                                               *
 *                                                                            *
 * Purpose: return address family                                             *
 *                                                                            *
 * Parameters: addr - [IN] address or hostname                                *
 *             family - [OUT] address family                                  *
 *             error - [OUT] error string                                     *
 *             max_error_len - [IN] error string length                       *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
#ifdef HAVE_IPV6
int	get_address_family(const char *addr, int *family, char *error, int max_error_len)
{
	struct addrinfo	hints, *ai = NULL;
	int		err, res = FAIL;

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = PF_UNSPEC;
	hints.ai_flags = 0;
	hints.ai_socktype = SOCK_STREAM;

	if (0 != (err = getaddrinfo(addr, NULL, &hints, &ai)))
	{
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", addr, err, gai_strerror(err));
		goto out;
	}

	if (PF_INET != ai->ai_family && PF_INET6 != ai->ai_family)
	{
		zbx_snprintf(error, max_error_len, "%s: unsupported address family", addr);
		goto out;
	}

	*family = (int)ai->ai_family;

	res = SUCCEED;
out:
	if (NULL != ai)
		freeaddrinfo(ai);

	return res;
}
#endif	/* HAVE_IPV6 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_listen                                                   *
 *                                                                            *
 * Purpose: create socket for listening                                       *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
#ifdef HAVE_IPV6
int	zbx_tcp_listen(zbx_socket_t *s, const char *listen_ip, unsigned short listen_port)
{
	struct addrinfo	hints, *ai = NULL, *current_ai;
	char		port[8], *ip, *ips, *delim;
	int		i, err, on, ret = FAIL;

	ZBX_SOCKET_START();

	zbx_socket_clean(s);

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = PF_UNSPEC;
	hints.ai_flags = AI_NUMERICHOST | AI_PASSIVE;
	hints.ai_socktype = SOCK_STREAM;
	zbx_snprintf(port, sizeof(port), "%hu", listen_port);

	ip = ips = (NULL == listen_ip ? NULL : strdup(listen_ip));

	while (1)
	{
		delim = (NULL == ip ? NULL : strchr(ip, ','));
		if (NULL != delim)
			*delim = '\0';

		if (0 != (err = getaddrinfo(ip, port, &hints, &ai)))
		{
			zbx_set_socket_strerror("cannot resolve address [[%s]:%s]: [%d] %s",
					ip ? ip : "-", port, err, gai_strerror(err));
			goto out;
		}

		for (current_ai = ai; NULL != current_ai; current_ai = current_ai->ai_next)
		{
			if (ZBX_SOCKET_COUNT == s->num_socks)
			{
				zbx_set_socket_strerror("not enough space for socket [[%s]:%s]",
						ip ? ip : "-", port);
				goto out;
			}

			if (PF_INET != current_ai->ai_family && PF_INET6 != current_ai->ai_family)
				continue;

			if (ZBX_SOCKET_ERROR == (s->sockets[s->num_socks] =
					socket(current_ai->ai_family, current_ai->ai_socktype | SOCK_CLOEXEC,
					current_ai->ai_protocol)))
			{
				zbx_set_socket_strerror("socket() for [[%s]:%s] failed: %s",
						ip ? ip : "-", port, strerror_from_system(zbx_socket_last_error()));
#ifdef _WINDOWS
				if (WSAEAFNOSUPPORT == zbx_socket_last_error())
#else
				if (EAFNOSUPPORT == zbx_socket_last_error())
#endif
					continue;
				else
					goto out;
			}

#if !defined(_WINDOWS) && !SOCK_CLOEXEC
			fcntl(s->sockets[s->num_socks], F_SETFD, FD_CLOEXEC);
#endif
			on = 1;
#ifdef _WINDOWS
			/* prevent other processes from binding to the same port */
			/* SO_EXCLUSIVEADDRUSE is mutually exclusive with SO_REUSEADDR */
			/* on Windows SO_REUSEADDR has different semantics than on Unix */
			/* https://msdn.microsoft.com/en-us/library/windows/desktop/ms740621(v=vs.85).aspx */
			if (ZBX_PROTO_ERROR == setsockopt(s->sockets[s->num_socks], SOL_SOCKET, SO_EXCLUSIVEADDRUSE,
					(void *)&on, sizeof(on)))
			{
				zbx_set_socket_strerror("setsockopt() with %s for [[%s]:%s] failed: %s",
						"SO_EXCLUSIVEADDRUSE", ip ? ip : "-", port,
						strerror_from_system(zbx_socket_last_error()));
			}
#else
			/* enable address reuse */
			/* this is to immediately use the address even if it is in TIME_WAIT state */
			/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
			if (ZBX_PROTO_ERROR == setsockopt(s->sockets[s->num_socks], SOL_SOCKET, SO_REUSEADDR,
					(void *)&on, sizeof(on)))
			{
				zbx_set_socket_strerror("setsockopt() with %s for [[%s]:%s] failed: %s",
						"SO_REUSEADDR", ip ? ip : "-", port,
						strerror_from_system(zbx_socket_last_error()));
			}
#endif

#if defined(IPPROTO_IPV6) && defined(IPV6_V6ONLY)
			if (PF_INET6 == current_ai->ai_family &&
					ZBX_PROTO_ERROR == setsockopt(s->sockets[s->num_socks], IPPROTO_IPV6,
					IPV6_V6ONLY, (void *)&on, sizeof(on)))
			{
				zbx_set_socket_strerror("setsockopt() with %s for [[%s]:%s] failed: %s",
						"IPV6_V6ONLY", ip ? ip : "-", port,
						strerror_from_system(zbx_socket_last_error()));
			}
#endif
			if (ZBX_PROTO_ERROR == zbx_bind(s->sockets[s->num_socks], current_ai->ai_addr,
					current_ai->ai_addrlen))
			{
				zbx_set_socket_strerror("bind() for [[%s]:%s] failed: %s",
						ip ? ip : "-", port, strerror_from_system(zbx_socket_last_error()));
				zbx_socket_close(s->sockets[s->num_socks]);
#ifdef _WINDOWS
				if (WSAEADDRINUSE == zbx_socket_last_error())
#else
				if (EADDRINUSE == zbx_socket_last_error())
#endif
					continue;
				else
					goto out;
			}

			if (ZBX_PROTO_ERROR == listen(s->sockets[s->num_socks], SOMAXCONN))
			{
				zbx_set_socket_strerror("listen() for [[%s]:%s] failed: %s",
						ip ? ip : "-", port, strerror_from_system(zbx_socket_last_error()));
				zbx_socket_close(s->sockets[s->num_socks]);
				goto out;
			}

			s->num_socks++;
		}

		if (NULL != ai)
		{
			freeaddrinfo(ai);
			ai = NULL;
		}

		if (NULL == ip || NULL == delim)
			break;

		*delim = ',';
		ip = delim + 1;
	}

	if (0 == s->num_socks)
	{
		zbx_set_socket_strerror("zbx_tcp_listen() fatal error: unable to serve on any address [[%s]:%hu]",
				listen_ip ? listen_ip : "-", listen_port);
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != ips)
		zbx_free(ips);

	if (NULL != ai)
		freeaddrinfo(ai);

	if (SUCCEED != ret)
	{
		for (i = 0; i < s->num_socks; i++)
			zbx_socket_close(s->sockets[i]);
	}

	return ret;
}
#else
int	zbx_tcp_listen(zbx_socket_t *s, const char *listen_ip, unsigned short listen_port)
{
	ZBX_SOCKADDR	serv_addr;
	char		*ip, *ips, *delim;
	int		i, on, ret = FAIL;

	ZBX_SOCKET_START();

	zbx_socket_clean(s);

	ip = ips = (NULL == listen_ip ? NULL : strdup(listen_ip));

	while (1)
	{
		delim = (NULL == ip ? NULL : strchr(ip, ','));
		if (NULL != delim)
			*delim = '\0';

		if (NULL != ip && FAIL == is_ip4(ip))
		{
			zbx_set_socket_strerror("incorrect IPv4 address [%s]", ip);
			goto out;
		}

		if (ZBX_SOCKET_COUNT == s->num_socks)
		{
			zbx_set_socket_strerror("not enough space for socket [[%s]:%hu]",
					ip ? ip : "-", listen_port);
			goto out;
		}

		if (ZBX_SOCKET_ERROR == (s->sockets[s->num_socks] = socket(AF_INET, SOCK_STREAM | SOCK_CLOEXEC, 0)))
		{
			zbx_set_socket_strerror("socket() for [[%s]:%hu] failed: %s",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_socket_last_error()));
			goto out;
		}

#if !defined(_WINDOWS) && !SOCK_CLOEXEC
		fcntl(s->sockets[s->num_socks], F_SETFD, FD_CLOEXEC);
#endif
		on = 1;
#ifdef _WINDOWS
		/* prevent other processes from binding to the same port */
		/* SO_EXCLUSIVEADDRUSE is mutually exclusive with SO_REUSEADDR */
		/* on Windows SO_REUSEADDR has different semantics than on Unix */
		/* https://msdn.microsoft.com/en-us/library/windows/desktop/ms740621(v=vs.85).aspx */
		if (ZBX_PROTO_ERROR == setsockopt(s->sockets[s->num_socks], SOL_SOCKET, SO_EXCLUSIVEADDRUSE,
				(void *)&on, sizeof(on)))
		{
			zbx_set_socket_strerror("setsockopt() with %s for [[%s]:%hu] failed: %s", "SO_EXCLUSIVEADDRUSE",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_socket_last_error()));
		}
#else
		/* enable address reuse */
		/* this is to immediately use the address even if it is in TIME_WAIT state */
		/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
		if (ZBX_PROTO_ERROR == setsockopt(s->sockets[s->num_socks], SOL_SOCKET, SO_REUSEADDR,
				(void *)&on, sizeof(on)))
		{
			zbx_set_socket_strerror("setsockopt() with %s for [[%s]:%hu] failed: %s", "SO_REUSEADDR",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_socket_last_error()));
		}
#endif
		memset(&serv_addr, 0, sizeof(serv_addr));

		serv_addr.sin_family = AF_INET;
		serv_addr.sin_addr.s_addr = (NULL != ip ? inet_addr(ip) : htonl(INADDR_ANY));
		serv_addr.sin_port = htons((unsigned short)listen_port);

		if (ZBX_PROTO_ERROR == bind(s->sockets[s->num_socks], (struct sockaddr *)&serv_addr, sizeof(serv_addr)))
		{
			zbx_set_socket_strerror("bind() for [[%s]:%hu] failed: %s",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_socket_last_error()));
			zbx_socket_close(s->sockets[s->num_socks]);
			goto out;
		}

		if (ZBX_PROTO_ERROR == listen(s->sockets[s->num_socks], SOMAXCONN))
		{
			zbx_set_socket_strerror("listen() for [[%s]:%hu] failed: %s",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_socket_last_error()));
			zbx_socket_close(s->sockets[s->num_socks]);
			goto out;
		}

		s->num_socks++;

		if (NULL == ip || NULL == delim)
			break;
		*delim = ',';
		ip = delim + 1;
	}

	if (0 == s->num_socks)
	{
		zbx_set_socket_strerror("zbx_tcp_listen() fatal error: unable to serve on any address [[%s]:%hu]",
				listen_ip ? listen_ip : "-", listen_port);
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != ips)
		zbx_free(ips);

	if (SUCCEED != ret)
	{
		for (i = 0; i < s->num_socks; i++)
			zbx_socket_close(s->sockets[i]);
	}

	return ret;
}
#endif	/* HAVE_IPV6 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_accept                                                   *
 *                                                                            *
 * Purpose: permits an incoming connection attempt on a socket                *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Eugene Grigorjev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_tcp_accept(zbx_socket_t *s, unsigned int tls_accept)
{
	ZBX_SOCKADDR	serv_addr;
	fd_set		sock_set;
	ZBX_SOCKET	accepted_socket;
	ZBX_SOCKLEN_T	nlen;
	int		i, n = 0, ret = FAIL;
	ssize_t		res;
	unsigned char	buf;	/* 1 byte buffer */

	zbx_tcp_unaccept(s);

	FD_ZERO(&sock_set);

	for (i = 0; i < s->num_socks; i++)
	{
		FD_SET(s->sockets[i], &sock_set);
#ifndef _WINDOWS
		if (s->sockets[i] > n)
			n = s->sockets[i];
#endif
	}

	if (ZBX_PROTO_ERROR == select(n + 1, &sock_set, NULL, NULL, NULL))
	{
		zbx_set_socket_strerror("select() failed: %s", strerror_from_system(zbx_socket_last_error()));
		return ret;
	}

	for (i = 0; i < s->num_socks; i++)
	{
		if (FD_ISSET(s->sockets[i], &sock_set))
			break;
	}

	/* Since this socket was returned by select(), we know we have */
	/* a connection waiting and that this accept() will not block. */
	nlen = sizeof(serv_addr);
	if (ZBX_SOCKET_ERROR == (accepted_socket = (ZBX_SOCKET)accept(s->sockets[i], (struct sockaddr *)&serv_addr,
			&nlen)))
	{
		zbx_set_socket_strerror("accept() failed: %s", strerror_from_system(zbx_socket_last_error()));
		return ret;
	}

	s->socket_orig = s->socket;	/* remember main socket */
	s->socket = accepted_socket;	/* replace socket to accepted */
	s->accepted = 1;

	if (SUCCEED != zbx_socket_peer_ip_save(s))
	{
		/* cannot get peer IP address */
		zbx_tcp_unaccept(s);
		goto out;
	}

	zbx_socket_timeout_set(s, CONFIG_TIMEOUT);

	if (ZBX_SOCKET_ERROR == (res = recv(s->socket, &buf, 1, MSG_PEEK)))
	{
		zbx_set_socket_strerror("from %s: reading first byte from connection failed: %s", s->peer,
				strerror_from_system(zbx_socket_last_error()));
		zbx_tcp_unaccept(s);
		goto out;
	}

	/* if the 1st byte is 0x16 then assume it's a TLS connection */
	if (1 == res && '\x16' == buf)
	{
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		if (0 != (tls_accept & (ZBX_TCP_SEC_TLS_CERT | ZBX_TCP_SEC_TLS_PSK)))
		{
			char	*error = NULL;

			if (SUCCEED != zbx_tls_accept(s, tls_accept, &error))
			{
				zbx_set_socket_strerror("from %s: %s", s->peer, error);
				zbx_tcp_unaccept(s);
				zbx_free(error);
				goto out;
			}
		}
		else
		{
			zbx_set_socket_strerror("from %s: TLS connections are not allowed", s->peer);
			zbx_tcp_unaccept(s);
			goto out;
		}
#else
		zbx_set_socket_strerror("from %s: support for TLS was not compiled in", s->peer);
		zbx_tcp_unaccept(s);
		goto out;
#endif
	}
	else
	{
		if (0 == (tls_accept & ZBX_TCP_SEC_UNENCRYPTED))
		{
			zbx_set_socket_strerror("from %s: unencrypted connections are not allowed", s->peer);
			zbx_tcp_unaccept(s);
			goto out;
		}

		s->connection_type = ZBX_TCP_SEC_UNENCRYPTED;
	}

	ret = SUCCEED;
out:
	zbx_socket_timeout_cleanup(s);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_unaccept                                                 *
 *                                                                            *
 * Purpose: close accepted connection                                         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_tcp_unaccept(zbx_socket_t *s)
{
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_close(s);
#endif
	if (!s->accepted) return;

	shutdown(s->socket, 2);

	zbx_socket_close(s->socket);

	s->socket = s->socket_orig;	/* restore main socket */
	s->socket_orig = ZBX_SOCKET_ERROR;
	s->accepted = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_socket_find_line                                             *
 *                                                                            *
 * Purpose: finds the next line in socket data buffer                         *
 *                                                                            *
 * Parameters: s - [IN] the socket                                            *
 *                                                                            *
 * Return value: A pointer to the next line or NULL if the socket data buffer *
 *               contains no more lines.                                      *
 *                                                                            *
 ******************************************************************************/
static const char	*zbx_socket_find_line(zbx_socket_t *s)
{
	char	*ptr, *line = NULL;

	if (NULL == s->next_line)
		return NULL;

	/* check if the buffer contains the next line */
	if ((size_t)(s->next_line - s->buffer) <= s->read_bytes && NULL != (ptr = strchr(s->next_line, '\n')))
	{
		line = s->next_line;
		s->next_line = ptr + 1;

		if (ptr > line && '\r' == *(ptr - 1))
			ptr--;

		*ptr = '\0';
	}

	return line;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_recv_line                                                *
 *                                                                            *
 * Purpose: reads next line from a socket                                     *
 *                                                                            *
 * Parameters: s - [IN] the socket                                            *
 *                                                                            *
 * Return value: a pointer to the line in socket buffer or NULL if there are  *
 *               no more lines (socket was closed or an error occurred)       *
 *                                                                            *
 * Comments: Lines larger than 64KB are truncated.                            *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_tcp_recv_line(zbx_socket_t *s)
{
#define ZBX_TCP_LINE_LEN	(64 * ZBX_KIBIBYTE)

	char		buffer[ZBX_STAT_BUF_LEN], *ptr = NULL;
	const char	*line;
	ssize_t		nbytes;
	size_t		alloc = 0, offset = 0, line_length, left;

	/* check if the buffer already contains the next line */
	if (NULL != (line = zbx_socket_find_line(s)))
		return line;

	/* Find the size of leftover data from the last read line operation and copy */
	/* the leftover data to the static buffer and reset the dynamic buffer.      */
	/* Because we are reading data in ZBX_STAT_BUF_LEN chunks the leftover       */
	/* data will always fit the static buffer.                                   */
	if (NULL != s->next_line)
	{
		left = s->read_bytes - (s->next_line - s->buffer);
		memmove(s->buf_stat, s->next_line, left);
	}
	else
		left = 0;

	s->read_bytes = left;
	s->next_line = s->buf_stat;

	zbx_socket_free(s);
	s->buf_type = ZBX_BUF_TYPE_STAT;
	s->buffer = s->buf_stat;

	/* read more data into static buffer */
	if (ZBX_PROTO_ERROR == (nbytes = ZBX_TCP_READ(s->socket, s->buf_stat + left, ZBX_STAT_BUF_LEN - left - 1)))
		goto out;

	s->buf_stat[left + nbytes] = '\0';

	if (0 == nbytes)
	{
		/* Socket was closed before newline was found. If we have data in buffer  */
		/* return it with success. Otherwise return failure.                      */
		line = 0 != s->read_bytes ? s->next_line : NULL;
		s->next_line += s->read_bytes;

		goto out;
	}

	s->read_bytes += nbytes;

	/* check if the static buffer now contains the next line */
	if (NULL != (line = zbx_socket_find_line(s)))
		goto out;

	/* copy the static buffer data into dynamic buffer */
	s->buf_type = ZBX_BUF_TYPE_DYN;
	s->buffer = NULL;
	zbx_strncpy_alloc(&s->buffer, &alloc, &offset, s->buf_stat, s->read_bytes);
	line_length = s->read_bytes;

	/* Read data into dynamic buffer until newline has been found. */
	/* Lines larger than ZBX_TCP_LINE_LEN bytes will be truncated. */
	do
	{
		if (ZBX_PROTO_ERROR == (nbytes = ZBX_TCP_READ(s->socket, buffer, ZBX_STAT_BUF_LEN - 1)))
			goto out;

		if (0 == nbytes)
		{
			/* socket was closed before newline was found, just return the data we have */
			line = 0 != s->read_bytes ? s->buffer : NULL;
			s->next_line = s->buffer + s->read_bytes;

			goto out;
		}

		buffer[nbytes] = '\0';
		ptr = strchr(buffer, '\n');

		if (s->read_bytes + nbytes < ZBX_TCP_LINE_LEN && s->read_bytes == line_length)
		{
			zbx_strncpy_alloc(&s->buffer, &alloc, &offset, buffer, nbytes);
			s->read_bytes += nbytes;
		}
		else
		{
			if (0 != (left = MIN(ZBX_TCP_LINE_LEN - s->read_bytes, (size_t)(ptr - buffer))))
			{
				/* fill the string to the defined limit */
				zbx_strncpy_alloc(&s->buffer, &alloc, &offset, buffer, left);
				s->read_bytes += left;
			}

			/* if the line exceeds the defined limit then truncate it by skipping data until the newline */
			if (NULL != ptr)
			{
				zbx_strncpy_alloc(&s->buffer, &alloc, &offset, ptr, nbytes - (ptr - buffer));
				s->read_bytes += nbytes - (ptr - buffer);
			}
		}

		line_length += nbytes;

	}
	while (NULL == ptr);

	s->next_line = s->buffer;
	line = zbx_socket_find_line(s);
out:
	return line;
}

static ssize_t	zbx_tcp_read(zbx_socket_t *s, char *buf, size_t len)
{
	ssize_t	res;
	int	err;
#ifdef _WINDOWS
	double	sec;
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (NULL != s->tls_ctx)	/* TLS connection */
	{
		char	*error = NULL;

		if (ZBX_PROTO_ERROR == (res = zbx_tls_read(s, buf, len, &error)))
		{
			zbx_set_socket_strerror("%s", error);
			zbx_free(error);
		}

		return res;
	}
#endif
#ifdef _WINDOWS
	zbx_alarm_flag_clear();
	sec = zbx_time();
#endif
	do
	{
		res = ZBX_TCP_READ(s->socket, buf, len);
#ifdef _WINDOWS
		if (s->timeout < zbx_time() - sec)
			zbx_alarm_flag_set();
#endif
		if (SUCCEED == zbx_alarm_timed_out())
		{
			zbx_set_socket_strerror("ZBX_TCP_READ() timed out");
			return ZBX_PROTO_ERROR;
		}
	}
	while (ZBX_PROTO_ERROR == res && ZBX_PROTO_AGAIN == (err = zbx_socket_last_error()));

	if (ZBX_PROTO_ERROR == res)
		zbx_set_socket_strerror("ZBX_TCP_READ() failed: %s", strerror_from_system(err));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_recv_ext                                                 *
 *                                                                            *
 * Purpose: receive data                                                      *
 *                                                                            *
 * Return value: number of bytes received - success,                          *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
ssize_t	zbx_tcp_recv_ext(zbx_socket_t *s, int timeout)
{
#define ZBX_TCP_EXPECT_HEADER		1
#define ZBX_TCP_EXPECT_VERSION		2
#define ZBX_TCP_EXPECT_VERSION_VALIDATE	3
#define ZBX_TCP_EXPECT_LENGTH		4
#define ZBX_TCP_EXPECT_SIZE		5

	const char	*__function_name = "zbx_tcp_recv_ext";

	ssize_t		nbytes;
	size_t		allocated, buf_dyn_bytes = 0, buf_stat_bytes = 0, offset = 0;
	zbx_uint32_t	expected_len = 16 * ZBX_MEBIBYTE, reserved = 0;
	unsigned char	expect = ZBX_TCP_EXPECT_HEADER;
	int		protocol_version;

	if (0 != timeout)
		zbx_socket_timeout_set(s, timeout);

	zbx_socket_free(s);

	s->buf_type = ZBX_BUF_TYPE_STAT;
	s->buffer = s->buf_stat;

	while (0 != (nbytes = zbx_tcp_read(s, s->buf_stat + buf_stat_bytes, sizeof(s->buf_stat) - buf_stat_bytes)))
	{
		if (ZBX_PROTO_ERROR == nbytes)
			goto out;

		if (ZBX_BUF_TYPE_STAT == s->buf_type)
			buf_stat_bytes += nbytes;
		else
		{
			if (buf_dyn_bytes + nbytes <= expected_len)
				memcpy(s->buffer + buf_dyn_bytes, s->buf_stat, nbytes);
			buf_dyn_bytes += nbytes;
		}

		if (buf_stat_bytes + buf_dyn_bytes >= expected_len)
			break;

		if (ZBX_TCP_EXPECT_HEADER == expect)
		{
			if (ZBX_TCP_HEADER_LEN > buf_stat_bytes)
			{
				if (0 == strncmp(s->buf_stat, ZBX_TCP_HEADER_DATA, buf_stat_bytes))
					continue;

				break;
			}
			else
			{
				if (0 != strncmp(s->buf_stat, ZBX_TCP_HEADER_DATA, ZBX_TCP_HEADER_LEN))
				{
					/* invalid header, abort receiving */
					break;
				}

				expect = ZBX_TCP_EXPECT_VERSION;
				offset += ZBX_TCP_HEADER_LEN;
			}
		}

		if (ZBX_TCP_EXPECT_VERSION == expect)
		{
			if (offset + 1 > buf_stat_bytes)
				continue;

			expect = ZBX_TCP_EXPECT_VERSION_VALIDATE;
			protocol_version = s->buf_stat[ZBX_TCP_HEADER_LEN];

			if (0 == (protocol_version & ZBX_TCP_PROTOCOL) ||
					protocol_version > (ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS))
			{
				/* invalid protocol version, abort receiving */
				break;
			}
			s->protocol = protocol_version;
			expect = ZBX_TCP_EXPECT_LENGTH;
			offset++;
		}

		if (ZBX_TCP_EXPECT_LENGTH == expect)
		{
			if (offset + 2 * sizeof(zbx_uint32_t) > buf_stat_bytes)
				continue;

			memcpy(&expected_len, s->buf_stat + offset, sizeof(zbx_uint32_t));
			offset += sizeof(zbx_uint32_t);
			expected_len = zbx_letoh_uint32(expected_len);

			memcpy(&reserved, s->buf_stat + offset, sizeof(zbx_uint32_t));
			offset += sizeof(zbx_uint32_t);
			reserved = zbx_letoh_uint32(reserved);

			if (ZBX_MAX_RECV_DATA_SIZE < expected_len)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Message size " ZBX_FS_UI64 " from %s exceeds the "
						"maximum size " ZBX_FS_UI64 " bytes. Message ignored.", expected_len,
						s->peer, (zbx_uint64_t)ZBX_MAX_RECV_DATA_SIZE);
				nbytes = ZBX_PROTO_ERROR;
				goto out;
			}

			/* compressed protocol stores uncompressed packet size in the reserved data */
			if (0 != (protocol_version & ZBX_TCP_COMPRESS) && ZBX_MAX_RECV_DATA_SIZE < reserved)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Uncompressed message size " ZBX_FS_UI64
						" from %s exceeds the maximum size " ZBX_FS_UI64
						" bytes. Message ignored.", expected_len,
						s->peer, (zbx_uint64_t)ZBX_MAX_RECV_DATA_SIZE);
				nbytes = ZBX_PROTO_ERROR;
				goto out;
			}

			if (sizeof(s->buf_stat) > expected_len)
			{
				buf_stat_bytes -= offset;
				memmove(s->buf_stat, s->buf_stat + offset, buf_stat_bytes);
			}
			else
			{
				s->buf_type = ZBX_BUF_TYPE_DYN;
				allocated = expected_len + 1;
				s->buffer = (char *)zbx_malloc(NULL, allocated);
				buf_dyn_bytes = buf_stat_bytes - offset;
				buf_stat_bytes = 0;
				memcpy(s->buffer, s->buf_stat + offset, buf_dyn_bytes);
			}

			expect = ZBX_TCP_EXPECT_SIZE;

			if (buf_stat_bytes + buf_dyn_bytes >= expected_len)
				break;
		}
	}

	if (ZBX_TCP_EXPECT_SIZE == expect)
	{
		if (buf_stat_bytes + buf_dyn_bytes == expected_len)
		{
			if (0 != (protocol_version & ZBX_TCP_COMPRESS))
			{
				char	*out;
				size_t	out_size = reserved;

				out = (char *)zbx_malloc(NULL, reserved + 1);
				if (FAIL == zbx_uncompress(s->buffer, buf_stat_bytes + buf_dyn_bytes, out, &out_size))
				{
					zbx_free(out);
					zbx_set_socket_strerror("cannot uncompress data: %s", zbx_compress_strerror());
					nbytes = ZBX_PROTO_ERROR;
					goto out;
				}

				if (out_size != reserved)
				{
					zbx_free(out);
					zbx_set_socket_strerror("size of uncompressed data is less than expected",
							zbx_compress_strerror());
					nbytes = ZBX_PROTO_ERROR;
					goto out;
				}

				if (ZBX_BUF_TYPE_DYN == s->buf_type)
					zbx_free(s->buffer);

				s->buf_type = ZBX_BUF_TYPE_DYN;
				s->buffer = out;
				s->read_bytes = reserved;

				zabbix_log(LOG_LEVEL_TRACE, "%s(): received " ZBX_FS_SIZE_T " bytes with"
						" compression ratio %.1f", __function_name,
						buf_stat_bytes + buf_dyn_bytes,
						(double)reserved / (buf_stat_bytes + buf_dyn_bytes));
			}
			else
				s->read_bytes = buf_stat_bytes + buf_dyn_bytes;

			s->buffer[s->read_bytes] = '\0';
		}
		else
		{
			if (buf_stat_bytes + buf_dyn_bytes < expected_len)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Message from %s is shorter than expected " ZBX_FS_UI64
						" bytes. Message ignored.", s->peer, expected_len);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "Message from %s is longer than expected " ZBX_FS_UI64
						" bytes. Message ignored.", s->peer, expected_len);
			}

			nbytes = ZBX_PROTO_ERROR;
		}
	}
	else if (ZBX_TCP_EXPECT_LENGTH == expect)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Message from %s is missing data length. Message ignored.", s->peer);
		nbytes = ZBX_PROTO_ERROR;
	}
	else if (ZBX_TCP_EXPECT_VERSION == expect)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Message from %s is missing protocol version. Message ignored.",
				s->peer);
		nbytes = ZBX_PROTO_ERROR;
	}
	else if (ZBX_TCP_EXPECT_VERSION_VALIDATE == expect)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Message from %s is using unsupported protocol version \"%d\"."
				" Message ignored.", s->peer, protocol_version);
		nbytes = ZBX_PROTO_ERROR;
	}
	else if (0 != buf_stat_bytes)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Message from %s is missing header. Message ignored.", s->peer);
		nbytes = ZBX_PROTO_ERROR;
	}
out:
	if (0 != timeout)
		zbx_socket_timeout_cleanup(s);

	return (ZBX_PROTO_ERROR == nbytes ? FAIL : (ssize_t)(s->read_bytes + offset));

#undef ZBX_TCP_EXPECT_HEADER
#undef ZBX_TCP_EXPECT_LENGTH
#undef ZBX_TCP_EXPECT_SIZE
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_recv_raw_ext                                             *
 *                                                                            *
 * Purpose: receive data till connection is closed                            *
 *                                                                            *
 * Return value: number of bytes received - success,                          *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
ssize_t	zbx_tcp_recv_raw_ext(zbx_socket_t *s, int timeout)
{
	ssize_t		nbytes;
	size_t		allocated = 8 * ZBX_STAT_BUF_LEN, buf_dyn_bytes = 0, buf_stat_bytes = 0;
	zbx_uint64_t	expected_len = 16 * ZBX_MEBIBYTE;

	if (0 != timeout)
		zbx_socket_timeout_set(s, timeout);

	zbx_socket_free(s);

	s->buf_type = ZBX_BUF_TYPE_STAT;
	s->buffer = s->buf_stat;

	while (0 != (nbytes = zbx_tcp_read(s, s->buf_stat + buf_stat_bytes, sizeof(s->buf_stat) - buf_stat_bytes)))
	{
		if (ZBX_PROTO_ERROR == nbytes)
			goto out;

		if (ZBX_BUF_TYPE_STAT == s->buf_type)
			buf_stat_bytes += nbytes;
		else
		{
			if (buf_dyn_bytes + nbytes >= allocated)
			{
				while (buf_dyn_bytes + nbytes >= allocated)
					allocated *= 2;
				s->buffer = (char *)zbx_realloc(s->buffer, allocated);
			}

			memcpy(s->buffer + buf_dyn_bytes, s->buf_stat, nbytes);
			buf_dyn_bytes += nbytes;
		}

		if (buf_stat_bytes + buf_dyn_bytes >= expected_len)
			break;

		if (sizeof(s->buf_stat) == buf_stat_bytes)
		{
			s->buf_type = ZBX_BUF_TYPE_DYN;
			s->buffer = (char *)zbx_malloc(NULL, allocated);
			buf_dyn_bytes = sizeof(s->buf_stat);
			buf_stat_bytes = 0;
			memcpy(s->buffer, s->buf_stat, sizeof(s->buf_stat));
		}
	}

	if (buf_stat_bytes + buf_dyn_bytes >= expected_len)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Message from %s is longer than " ZBX_FS_UI64 " bytes allowed for"
				" plain text. Message ignored.", s->peer, expected_len);
		nbytes = ZBX_PROTO_ERROR;
		goto out;
	}

	s->read_bytes = buf_stat_bytes + buf_dyn_bytes;
	s->buffer[s->read_bytes] = '\0';
out:
	if (0 != timeout)
		zbx_socket_timeout_cleanup(s);

	return (ZBX_PROTO_ERROR == nbytes ? FAIL : (ssize_t)(s->read_bytes));
}

static int	subnet_match(int af, unsigned int prefix_size, const void *address1, const void *address2)
{
	unsigned char	netmask[16] = {0};
	int		i, j, bytes;

	if (af == AF_INET)
	{
		if (prefix_size > IPV4_MAX_CIDR_PREFIX)
			return FAIL;
		bytes = 4;
	}
	else
	{
		if (prefix_size > IPV6_MAX_CIDR_PREFIX)
			return FAIL;
		bytes = 16;
	}

	/* CIDR notation to subnet mask */
	for (i = (int)prefix_size, j = 0; i > 0 && j < bytes; i -= 8, j++)
		netmask[j] = i >= 8 ? 0xFF : ~((1 << (8 - i)) - 1);

	/* The result of the bitwise AND operation of IP address and the subnet mask is the network prefix. */
	/* All hosts on a subnetwork have the same network prefix. */
	for (i = 0; i < bytes; i++)
	{
		if ((((const unsigned char *)address1)[i] & netmask[i]) !=
				(((const unsigned char *)address2)[i] & netmask[i]))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

#ifdef HAVE_IPV6
static int	zbx_ip_cmp(unsigned int prefix_size, const struct addrinfo *current_ai, ZBX_SOCKADDR name)
{
	/* Network Byte Order is ensured */
	/* IPv4-compatible, the first 96 bits are zeros */
	const unsigned char	ipv4_compat_mask[12] = {0};
	/* IPv4-mapped, the first 80 bits are zeros, 16 next - ones */
	const unsigned char	ipv4_mapped_mask[12] = {0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 255, 255};

	struct sockaddr_in	*name4 = (struct sockaddr_in *)&name,
				*ai_addr4 = (struct sockaddr_in *)current_ai->ai_addr;
	struct sockaddr_in6	*name6 = (struct sockaddr_in6 *)&name,
				*ai_addr6 = (struct sockaddr_in6 *)current_ai->ai_addr;

#ifdef HAVE_SOCKADDR_STORAGE_SS_FAMILY
	if (current_ai->ai_family == name.ss_family)
#else
	if (current_ai->ai_family == name.__ss_family)
#endif
	{
		switch (current_ai->ai_family)
		{
			case AF_INET:
				if (SUCCEED == subnet_match(current_ai->ai_family, prefix_size, &name4->sin_addr.s_addr,
						&ai_addr4->sin_addr.s_addr))
				{
					return SUCCEED;
				}
				break;
			case AF_INET6:
				if (SUCCEED == subnet_match(current_ai->ai_family, prefix_size, name6->sin6_addr.s6_addr,
						ai_addr6->sin6_addr.s6_addr))
				{
					return SUCCEED;
				}
				break;
		}
	}
	else
	{
		unsigned char	ipv6_compat_address[16], ipv6_mapped_address[16];

		switch (current_ai->ai_family)
		{
			case AF_INET:
				/* incoming AF_INET6, must see whether it is compatible or mapped */
				if ((0 == memcmp(name6->sin6_addr.s6_addr, ipv4_compat_mask, 12) ||
						0 == memcmp(name6->sin6_addr.s6_addr, ipv4_mapped_mask, 12)) &&
						SUCCEED == subnet_match(AF_INET, prefix_size,
						&name6->sin6_addr.s6_addr[12], &ai_addr4->sin_addr.s_addr))
				{
					return SUCCEED;
				}
				break;
			case AF_INET6:
				/* incoming AF_INET, must see whether the given is compatible or mapped */
				memcpy(ipv6_compat_address, ipv4_compat_mask, sizeof(ipv4_compat_mask));
				memcpy(&ipv6_compat_address[sizeof(ipv4_compat_mask)], &name4->sin_addr.s_addr, 4);

				memcpy(ipv6_mapped_address, ipv4_mapped_mask, sizeof(ipv4_mapped_mask));
				memcpy(&ipv6_mapped_address[sizeof(ipv4_mapped_mask)], &name4->sin_addr.s_addr, 4);

				if (SUCCEED == subnet_match(AF_INET6, prefix_size,
						&ai_addr6->sin6_addr.s6_addr, ipv6_compat_address) ||
						SUCCEED == subnet_match(AF_INET6, prefix_size,
						&ai_addr6->sin6_addr.s6_addr, ipv6_mapped_address))
				{
					return SUCCEED;
				}
				break;
		}
	}

	return FAIL;
}
#endif

static int	validate_cidr(const char *ip, const char *cidr, void *value)
{
	if (SUCCEED == is_ip4(ip))
		return is_uint_range(cidr, value, 0, IPV4_MAX_CIDR_PREFIX);
#ifdef HAVE_IPV6
	if (SUCCEED == is_ip6(ip))
		return is_uint_range(cidr, value, 0, IPV6_MAX_CIDR_PREFIX);
#endif
	return FAIL;
}

int	zbx_validate_peer_list(const char *peer_list, char **error)
{
	char	*start, *end, *cidr_sep;
	char	tmp[MAX_STRING_LEN];

	strscpy(tmp, peer_list);

	for (start = tmp; '\0' != *start;)
	{
		if (NULL != (end = strchr(start, ',')))
			*end = '\0';

		if (NULL != (cidr_sep = strchr(start, '/')))
		{
			*cidr_sep = '\0';

			if (FAIL == validate_cidr(start, cidr_sep + 1, NULL))
			{
				*cidr_sep = '/';
				*error = zbx_dsprintf(NULL, "\"%s\"", start);
				return FAIL;
			}
		}
		else if (FAIL == is_supported_ip(start) && FAIL == zbx_validate_hostname(start))
		{
			*error = zbx_dsprintf(NULL, "\"%s\"", start);
			return FAIL;
		}

		if (NULL != end)
			start = end + 1;
		else
			break;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_check_allowed_peers                                      *
 *                                                                            *
 * Purpose: check if connection initiator is in list of peers                 *
 *                                                                            *
 * Parameters: s         - [IN] socket descriptor                             *
 *             peer_list - [IN] comma-delimited list of allowed peers.        *
 *                              NULL not allowed. Empty string results in     *
 *                              return value FAIL.                            *
 *                                                                            *
 * Return value: SUCCEED - connection allowed                                 *
 *               FAIL - connection is not allowed                             *
 *                                                                            *
 * Author: Alexei Vladishev, Dmitry Borovikov                                 *
 *                                                                            *
 * Comments: standard, compatible and IPv4-mapped addresses are treated       *
 *           the same: 127.0.0.1 == ::127.0.0.1 == ::ffff:127.0.0.1           *
 *                                                                            *
 ******************************************************************************/
int	zbx_tcp_check_allowed_peers(const zbx_socket_t *s, const char *peer_list)
{
	char	*start = NULL, *end = NULL, *cidr_sep, tmp[MAX_STRING_LEN];

	/* examine list of allowed peers which may include DNS names, IPv4/6 addresses and addresses in CIDR notation */

	strscpy(tmp, peer_list);

	for (start = tmp; '\0' != *start;)
	{
#ifdef HAVE_IPV6
#ifdef HAVE_SOCKADDR_STORAGE_SS_FAMILY
		int	ai_family = s->peer_info.ss_family;
#else
		int	ai_family = s->peer_info.__ss_family;
#endif
		unsigned int	prefix_size = (ai_family == AF_INET ? IPV4_MAX_CIDR_PREFIX : IPV6_MAX_CIDR_PREFIX);
		struct addrinfo	hints, *ai = NULL, *current_ai;
#else
		unsigned int	prefix_size = IPV4_MAX_CIDR_PREFIX;
		struct hostent	*hp;
#endif /* HAVE_IPV6 */

		if (NULL != (end = strchr(start, ',')))
			*end = '\0';

		if (NULL != (cidr_sep = strchr(start, '/')))
		{
			*cidr_sep = '\0';

			/* validate_cidr() may overwrite 'prefix_size' */
			if (SUCCEED != validate_cidr(start, cidr_sep + 1, &prefix_size))
				*cidr_sep = '/';	/* CIDR is only supported for IP */
		}

		/* When adding IPv6 support it was decided to leave current implementation   */
		/* (based on gethostbyname()) for handling non-IPv6-enabled components. In   */
		/* the future it should be considered to switch completely to getaddrinfo(). */

#ifdef HAVE_IPV6
		memset(&hints, 0, sizeof(hints));
		hints.ai_family = ai_family;
		hints.ai_socktype = SOCK_STREAM;
		hints.ai_protocol = IPPROTO_TCP;

		if (0 == getaddrinfo(start, NULL, &hints, &ai))
		{
			for (current_ai = ai; NULL != current_ai; current_ai = current_ai->ai_next)
			{
				if (SUCCEED == zbx_ip_cmp(prefix_size, current_ai, s->peer_info))
				{
					freeaddrinfo(ai);
					return SUCCEED;
				}
			}
			freeaddrinfo(ai);
		}
#else
		if (NULL != (hp = gethostbyname(start)))
		{
			int	i;

			for (i = 0; NULL != hp->h_addr_list[i]; i++)
			{
				if (SUCCEED == subnet_match(AF_INET, prefix_size,
						&((struct in_addr *)hp->h_addr_list[i])->s_addr,
						&s->peer_info.sin_addr.s_addr))
				{
					return SUCCEED;
				}
			}
		}
#endif	/* HAVE_IPV6 */
		if (NULL != end)
			start = end + 1;
		else
			break;
	}

	zbx_set_socket_strerror("connection from \"%s\" rejected, allowed hosts: \"%s\"", s->peer, peer_list);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_connection_type_name                                     *
 *                                                                            *
 * Purpose: translate connection type code to name                            *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_tcp_connection_type_name(unsigned int type)
{
	switch (type)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			return "unencrypted";
		case ZBX_TCP_SEC_TLS_CERT:
			return "TLS with certificate";
		case ZBX_TCP_SEC_TLS_PSK:
			return "TLS with PSK";
		default:
			return "unknown";
	}
}

int	zbx_udp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout)
{
	return zbx_socket_create(s, SOCK_DGRAM, source_ip, ip, port, timeout, ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL);
}

int	zbx_udp_send(zbx_socket_t *s, const char *data, size_t data_len, int timeout)
{
	int	ret = SUCCEED;

	if (0 != timeout)
		zbx_socket_timeout_set(s, timeout);

	if (ZBX_PROTO_ERROR == zbx_sendto(s->socket, data, data_len, 0, NULL, 0))
	{
		zbx_set_socket_strerror("sendto() failed: %s", strerror_from_system(zbx_socket_last_error()));
		ret = FAIL;
	}

	if (0 != timeout)
		zbx_socket_timeout_cleanup(s);

	return ret;
}

int	zbx_udp_recv(zbx_socket_t *s, int timeout)
{
	char	buffer[65508];	/* maximum payload for UDP over IPv4 is 65507 bytes */
	ssize_t	read_bytes;

	zbx_socket_free(s);

	if (0 != timeout)
		zbx_socket_timeout_set(s, timeout);

	if (ZBX_PROTO_ERROR == (read_bytes = recvfrom(s->socket, buffer, sizeof(buffer) - 1, 0, NULL, NULL)))
		zbx_set_socket_strerror("recvfrom() failed: %s", strerror_from_system(zbx_socket_last_error()));

	if (0 != timeout)
		zbx_socket_timeout_cleanup(s);

	if (ZBX_PROTO_ERROR == read_bytes)
		return FAIL;

	if (sizeof(s->buf_stat) > (size_t)read_bytes)
	{
		s->buf_type = ZBX_BUF_TYPE_STAT;
		s->buffer = s->buf_stat;
	}
	else
	{
		s->buf_type = ZBX_BUF_TYPE_DYN;
		s->buffer = (char *)zbx_malloc(s->buffer, read_bytes + 1);
	}

	buffer[read_bytes] = '\0';
	memcpy(s->buffer, buffer, read_bytes + 1);

	s->read_bytes = (size_t)read_bytes;

	return SUCCEED;
}

void	zbx_udp_close(zbx_socket_t *s)
{
	zbx_socket_timeout_cleanup(s);

	zbx_socket_free(s);
	zbx_socket_close(s->socket);
}

#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
/******************************************************************************
 *                                                                            *
 * Function: zbx_update_resolver_conf                                         *
 *                                                                            *
 * Purpose: react to "/etc/resolv.conf" update                                *
 *                                                                            *
 * Comments: it is intended to call this function in the end of each process  *
 *           main loop. The purpose of calling it at the end (instead of the  *
 *           beginning of main loop) is to let the first initialization of    *
 *           libc resolver proceed internally.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_update_resolver_conf(void)
{
#define ZBX_RESOLV_CONF_FILE	"/etc/resolv.conf"

	static time_t	mtime = 0;
	zbx_stat_t	buf;

	if (0 == zbx_stat(ZBX_RESOLV_CONF_FILE, &buf) && mtime != buf.st_mtime)
	{
		mtime = buf.st_mtime;

		if (0 != res_init())
			zabbix_log(LOG_LEVEL_WARNING, "zbx_update_resolver_conf(): res_init() failed");
	}

#undef ZBX_RESOLV_CONF_FILE
}
#endif
