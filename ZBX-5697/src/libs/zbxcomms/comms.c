/*
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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

#include "common.h"
#include "comms.h"
#include "log.h"

#if defined(_WINDOWS)
#	if defined(__INT_MAX__) && __INT_MAX__ == 2147483647
		typedef int ssize_t;
#	else
		typedef long ssize_t;
#	endif /* __INT_MAX__ */

#	define ZBX_TCP_WRITE(s, b, bl)	((ssize_t)send((s), (b), (bl), 0))
#	define ZBX_TCP_READ(s, b, bl)	((ssize_t)recv((s), (b), (bl), 0))

#	define ZBX_TCP_ERROR		SOCKET_ERROR
#	define ZBX_SOCK_ERROR		INVALID_SOCKET

#	define zbx_sock_close(s)	if (ZBX_SOCK_ERROR != (s)) closesocket(s)
#	define zbx_sock_last_error()	WSAGetLastError()

#	define ZBX_SOCK_ERR_TIMEDOUT	WSAETIMEDOUT
#else
#	define ZBX_TCP_WRITE(s, b, bl)	((ssize_t)write((s), (b), (bl)))
#	define ZBX_TCP_READ(s, b, bl)	((ssize_t)read((s), (b), (bl)))

#	define ZBX_TCP_ERROR		-1
#	define ZBX_SOCK_ERROR		-1

#	define zbx_sock_close(s)	if (ZBX_SOCK_ERROR != (s)) close(s)
#	define zbx_sock_last_error()	errno

#	define ZBX_SOCK_ERR_TIMEDOUT	EINTR

#endif	/* _WINDOWS */

#if defined(HAVE_IPV6)
#	define ZBX_SOCKADDR struct sockaddr_storage
#else
#	define ZBX_SOCKADDR struct sockaddr_in
#endif

#if !defined(ZBX_SOCKLEN_T)
#	define ZBX_SOCKLEN_T socklen_t
#endif

#if !defined(SOCK_CLOEXEC)
#	define SOCK_CLOEXEC 0	/* SOCK_CLOEXEC is Linux-specific, available since 2.6.23 */
#endif

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_strerror                                                 *
 *                                                                            *
 * Purpose: return string describing tcp error                                *
 *                                                                            *
 * Return value: pointer to the null terminated string                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/

#define ZBX_TCP_MAX_STRERROR	255

static char	zbx_tcp_strerror_message[ZBX_TCP_MAX_STRERROR];

const char	*zbx_tcp_strerror()
{
	zbx_tcp_strerror_message[ZBX_TCP_MAX_STRERROR - 1] = '\0';	/* force terminate string */
	return (&zbx_tcp_strerror_message[0]);
}

#ifdef HAVE___VA_ARGS__
#	define zbx_set_tcp_strerror(fmt, ...) __zbx_zbx_set_tcp_strerror(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_set_tcp_strerror __zbx_zbx_set_tcp_strerror
#endif
static void	__zbx_zbx_set_tcp_strerror(const char *fmt, ...)
{
	va_list args;

	va_start(args, fmt);

	zbx_vsnprintf(zbx_tcp_strerror_message, sizeof(zbx_tcp_strerror_message), fmt, args);

	va_end(args);
}

#if !defined(_WINDOWS)
/******************************************************************************
 *                                                                            *
 * Function: zbx_gethost_by_ip                                                *
 *                                                                            *
 * Purpose: retrieve 'hostent' by IP address                                  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/

#if defined(HAVE_IPV6)
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
 * Function: zbx_tcp_start                                                    *
 *                                                                            *
 * Purpose: Initialize Windows Sockets APIs                                   *
 *                                                                            *
 * Return value: SUCCEED or FAIL - an error occurred                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
#if defined(_WINDOWS)

#define ZBX_TCP_START() { if (FAIL == tcp_started) tcp_started = zbx_tcp_start(); }

int	tcp_started = FAIL;	/* winXX threads require tcp_started not to be static */

static int	zbx_tcp_start()
{
	WSADATA	sockInfo;
	int	ret;

	if (0 != (ret = WSAStartup(MAKEWORD(2, 2), &sockInfo)))
	{
		zbx_set_tcp_strerror("WSAStartup() failed: %s", strerror_from_system(ret));
		return FAIL;
	}

	return SUCCEED;
}

#else
#	define ZBX_TCP_START() {}
#endif	/* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_clean                                                    *
 *                                                                            *
 * Purpose: initialize socket                                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tcp_clean(zbx_sock_t *s)
{
	assert(s);

	memset(s, 0, sizeof(zbx_sock_t));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_init                                                     *
 *                                                                            *
 * Purpose: initialize structure of zabbix socket with specified socket       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_tcp_init(zbx_sock_t *s, ZBX_SOCKET o)
{
	zbx_tcp_clean(s);

	s->socket = o;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_timeout_set                                              *
 *                                                                            *
 * Purpose: set timeout for socket operations                                 *
 *                                                                            *
 * Parameters: s       - [IN] socket descriptor                               *
 *             timeout - [IN] timeout, in seconds                             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tcp_timeout_set(zbx_sock_t *s, int timeout)
{
	s->timeout = timeout;
#if defined(_WINDOWS)
	timeout *= 1000;

	if (ZBX_TCP_ERROR == setsockopt(s->socket, SOL_SOCKET, SO_RCVTIMEO, (const char *)&timeout, sizeof(timeout)))
		zbx_set_tcp_strerror("setsockopt() failed: %s", strerror_from_system(zbx_sock_last_error()));

	if (ZBX_TCP_ERROR == setsockopt(s->socket, SOL_SOCKET, SO_SNDTIMEO, (const char *)&timeout, sizeof(timeout)))
		zbx_set_tcp_strerror("setsockopt() failed: %s", strerror_from_system(zbx_sock_last_error()));
#else
	alarm(timeout);
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_timeout_cleanup                                          *
 *                                                                            *
 * Purpose: clean up timeout for socket operations                            *
 *                                                                            *
 * Parameters: s       - [IN] socket descriptor                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tcp_timeout_cleanup(zbx_sock_t *s)
{
#if !defined(_WINDOWS)
	if (0 != s->timeout)
	{
		alarm(0);
		s->timeout = 0;
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_connect                                                  *
 *                                                                            *
 * Purpose: connect to external host                                          *
 *                                                                            *
 * Return value: sockfd - open socket                                         *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_IPV6)
int	zbx_tcp_connect(zbx_sock_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout)
{
	int		ret = FAIL;
	struct addrinfo	*ai = NULL, hints;
	struct addrinfo	*ai_bind = NULL;
	char		service[8];

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	zbx_snprintf(service, sizeof(service), "%d", port);
	memset(&hints, 0x00, sizeof(struct addrinfo));
	hints.ai_family = PF_UNSPEC;
	hints.ai_socktype = SOCK_STREAM;

	if (0 != getaddrinfo(ip, service, &hints, &ai))
	{
		zbx_set_tcp_strerror("cannot resolve [%s]", ip);
		goto out;
	}

	if (ZBX_SOCK_ERROR == (s->socket = socket(ai->ai_family, ai->ai_socktype | SOCK_CLOEXEC, ai->ai_protocol)))
	{
		zbx_set_tcp_strerror("cannot create socket [[%s]:%d]: %s", ip, port,
				strerror_from_system(zbx_sock_last_error()));
		goto out;
	}

#if !defined(_WINDOWS) && !SOCK_CLOEXEC
	fcntl(s->socket, F_SETFD, FD_CLOEXEC);
#endif

	if (NULL != source_ip)
	{
		memset(&hints, 0x00, sizeof(struct addrinfo));
		hints.ai_family = PF_UNSPEC;
		hints.ai_socktype = SOCK_STREAM;
		hints.ai_flags = AI_NUMERICHOST;

		if (0 != getaddrinfo(source_ip, NULL, &hints, &ai_bind))
		{
			zbx_set_tcp_strerror("invalid source IP address [%s]", source_ip);
			goto out;
		}

		if (ZBX_TCP_ERROR == bind(s->socket, ai_bind->ai_addr, ai_bind->ai_addrlen))
		{
			zbx_set_tcp_strerror("bind() failed: %s", strerror_from_system(zbx_sock_last_error()));
			goto out;
		}
	}

	if (0 != timeout)
		zbx_tcp_timeout_set(s, timeout);

	if (ZBX_TCP_ERROR == connect(s->socket, ai->ai_addr, ai->ai_addrlen))
	{
		zbx_set_tcp_strerror("*** Cannot connect to [[%s]:%d]: %s",
				ip, port, strerror_from_system(zbx_sock_last_error()));
		zbx_tcp_close(s);
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != ai)
		freeaddrinfo(ai);

	if (NULL != ai_bind)
		freeaddrinfo(ai_bind);

	return ret;
}
#else
int	zbx_tcp_connect(zbx_sock_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout)
{
	ZBX_SOCKADDR	servaddr_in, source_addr;
	struct hostent	*hp;

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	if (NULL == (hp = gethostbyname(ip)))
	{
#if defined(_WINDOWS)
		zbx_set_tcp_strerror("gethostbyname() failed for '%s': %s", ip,
				strerror_from_system(WSAGetLastError()));
#elif defined(HAVE_HSTRERROR)
		zbx_set_tcp_strerror("gethostbyname() failed for '%s': [%d] %s", ip, h_errno, hstrerror(h_errno));
#else
		zbx_set_tcp_strerror("gethostbyname() failed for '%s': [%d]", ip, h_errno);
#endif
		return FAIL;
	}

	servaddr_in.sin_family		= AF_INET;
	servaddr_in.sin_addr.s_addr	= ((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port		= htons(port);

	if (ZBX_SOCK_ERROR == (s->socket = socket(AF_INET, SOCK_STREAM | SOCK_CLOEXEC, 0)))
	{
		zbx_set_tcp_strerror("cannot create socket [[%s]:%d]: %s", ip, port,
				strerror_from_system(zbx_sock_last_error()));
		return FAIL;
	}

#if !defined(_WINDOWS) && !SOCK_CLOEXEC
	fcntl(s->socket, F_SETFD, FD_CLOEXEC);
#endif

	if (NULL != source_ip)
	{
		source_addr.sin_family		= AF_INET;
		source_addr.sin_addr.s_addr	= inet_addr(source_ip);
		source_addr.sin_port		= 0;

		if (ZBX_TCP_ERROR == bind(s->socket, (struct sockaddr *)&source_addr, sizeof(source_addr)))
		{
			zbx_set_tcp_strerror("bind() failed: %s", strerror_from_system(zbx_sock_last_error()));
			return FAIL;
		}
	}

	if (0 != timeout)
		zbx_tcp_timeout_set(s, timeout);

	if (ZBX_TCP_ERROR == connect(s->socket, (struct sockaddr *)&servaddr_in, sizeof(servaddr_in)))
	{
		zbx_set_tcp_strerror("cannot connect to [[%s]:%d]: %s", ip, port,
				strerror_from_system(zbx_sock_last_error()));
		zbx_tcp_close(s);
		return FAIL;
	}

	return SUCCEED;
}
#endif	/* HAVE_IPV6 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_send                                                     *
 *                                                                            *
 * Purpose: send data                                                         *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/

#define ZBX_TCP_HEADER_DATA		"ZBXD"
#define ZBX_TCP_HEADER_VERSION		"\1"
#define ZBX_TCP_HEADER			ZBX_TCP_HEADER_DATA ZBX_TCP_HEADER_VERSION
#define ZBX_TCP_HEADER_LEN		5

int	zbx_tcp_send_ext(zbx_sock_t *s, const char *data, unsigned char flags, int timeout)
{
	zbx_uint64_t	len64;

	ssize_t		i = 0, written = 0;
	int		ret = SUCCEED;

	ZBX_TCP_START();

	if (0 != timeout)
		zbx_tcp_timeout_set(s, timeout);

	if (flags & ZBX_TCP_NEW_PROTOCOL)
	{
		/* Write header */
		if (ZBX_TCP_ERROR == ZBX_TCP_WRITE(s->socket, ZBX_TCP_HEADER, ZBX_TCP_HEADER_LEN))
		{
			zbx_set_tcp_strerror("ZBX_TCP_WRITE() failed: %s", strerror_from_system(zbx_sock_last_error()));
			ret = FAIL;
			goto cleanup;
		}

		len64 = (zbx_uint64_t)strlen(data);
		len64 = zbx_htole_uint64(len64);

		/* Write data length */
		if (ZBX_TCP_ERROR == ZBX_TCP_WRITE(s->socket, (char *) &len64, sizeof(len64)))
		{
			zbx_set_tcp_strerror("ZBX_TCP_WRITE() failed: %s", strerror_from_system(zbx_sock_last_error()));
			ret = FAIL;
			goto cleanup;
		}
	}

	while (written < (ssize_t)strlen(data))
	{
		if (ZBX_TCP_ERROR == (i = ZBX_TCP_WRITE(s->socket, data+written,(int)(strlen(data)-written))))
		{
			zbx_set_tcp_strerror("ZBX_TCP_WRITE() failed: %s", strerror_from_system(zbx_sock_last_error()));
			ret = FAIL;
			goto cleanup;
		}
		written += i;
	}
cleanup:
	if (0 != timeout)
		zbx_tcp_timeout_cleanup(s);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_close                                                    *
 *                                                                            *
 * Purpose: close open socket                                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_tcp_close(zbx_sock_t *s)
{
	zbx_tcp_unaccept(s);

	zbx_tcp_free(s);

	zbx_tcp_timeout_cleanup(s);

	zbx_sock_close(s->socket);
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
#if defined(HAVE_IPV6)
int	zbx_tcp_listen(zbx_sock_t *s, const char *listen_ip, unsigned short listen_port)
{
	struct addrinfo	hints, *ai = NULL, *current_ai;
	char		port[8], *ip, *ips, *delim;
	int		i, err, on, ret = FAIL;

	ZBX_TCP_START();

	zbx_tcp_clean(s);

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
			zbx_set_tcp_strerror("cannot resolve address [[%s]:%s]: [%d] %s",
					ip ? ip : "-", port, err, gai_strerror(err));
			goto out;
		}

		for (current_ai = ai; NULL != current_ai; current_ai = current_ai->ai_next)
		{
			if (ZBX_SOCKET_COUNT == s->num_socks)
			{
				zbx_set_tcp_strerror("not enough space for socket [[%s]:%s]", ip ? ip : "-", port);
				goto out;
			}

			if (PF_INET != current_ai->ai_family && PF_INET6 != current_ai->ai_family)
				continue;

			if (ZBX_SOCK_ERROR == (s->sockets[s->num_socks] =
					socket(current_ai->ai_family, current_ai->ai_socktype | SOCK_CLOEXEC,
							current_ai->ai_protocol)))
			{
				zbx_set_tcp_strerror("socket() for [[%s]:%s] failed: %s",
						ip ? ip : "-", port, strerror_from_system(zbx_sock_last_error()));
#ifdef _WINDOWS
				if (WSAEAFNOSUPPORT == zbx_sock_last_error())
#else
				if (EAFNOSUPPORT == zbx_sock_last_error())
#endif
					continue;
				else
					goto out;
			}

#if !defined(_WINDOWS) && !SOCK_CLOEXEC
			fcntl(s->sockets[s->num_socks], F_SETFD, FD_CLOEXEC);
#endif

			/* enable address reuse */
			/* this is to immediately use the address even if it is in TIME_WAIT state */
			/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
			on = 1;
			if (ZBX_TCP_ERROR == setsockopt(s->sockets[s->num_socks], SOL_SOCKET, SO_REUSEADDR,
					(void *)&on, sizeof(on)))
			{
				zbx_set_tcp_strerror("setsockopt() with SO_REUSEADDR for [[%s]:%s] failed: %s",
						ip ? ip : "-", port, strerror_from_system(zbx_sock_last_error()));
			}

#if defined(IPPROTO_IPV6) && defined(IPV6_V6ONLY)
			if (PF_INET6 == current_ai->ai_family &&
				ZBX_TCP_ERROR == setsockopt(s->sockets[s->num_socks], IPPROTO_IPV6, IPV6_V6ONLY,
						(void *)&on, sizeof(on)))
			{
				zbx_set_tcp_strerror("setsockopt() with IPV6_V6ONLY for [[%s]:%s] failed: %s",
						ip ? ip : "-", port, strerror_from_system(zbx_sock_last_error()));
			}
#endif

			if (ZBX_TCP_ERROR == bind(s->sockets[s->num_socks], current_ai->ai_addr,
					current_ai->ai_addrlen))
			{
				zbx_set_tcp_strerror("bind() for [[%s]:%s] failed: %s",
						ip ? ip : "-", port, strerror_from_system(zbx_sock_last_error()));
				zbx_sock_close(s->sockets[s->num_socks]);
#ifdef _WINDOWS
				if (WSAEADDRINUSE == zbx_sock_last_error())
#else
				if (EADDRINUSE == zbx_sock_last_error())
#endif
					continue;
				else
					goto out;
			}

			if (ZBX_TCP_ERROR == listen(s->sockets[s->num_socks], SOMAXCONN))
			{
				zbx_set_tcp_strerror("listen() for [[%s]:%s] failed: %s",
						ip ? ip : "-", port, strerror_from_system(zbx_sock_last_error()));
				zbx_sock_close(s->sockets[s->num_socks]);
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
		zbx_set_tcp_strerror("zbx_tcp_listen() fatal error: unable to serve on any address [[%s]:%hu]",
				listen_ip, listen_port);
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
			zbx_sock_close(s->sockets[i]);
	}

	return ret;
}
#else
int	zbx_tcp_listen(zbx_sock_t *s, const char *listen_ip, unsigned short listen_port)
{
	ZBX_SOCKADDR	serv_addr;
	char		*ip, *ips, *delim;
	int		i, on, ret = FAIL;

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	ip = ips = (NULL == listen_ip ? NULL : strdup(listen_ip));

	while (1)
	{
		delim = (NULL == ip ? NULL : strchr(ip, ','));
		if (NULL != delim)
			*delim = '\0';

		if (NULL != ip && FAIL == is_ip4(ip))
		{
			zbx_set_tcp_strerror("incorrect IPv4 address [%s]", ip);
			goto out;
		}

		if (ZBX_SOCKET_COUNT == s->num_socks)
		{
			zbx_set_tcp_strerror("not enough space for socket [[%s]:%hu]",
					ip ? ip : "-", listen_port);
			goto out;
		}

		if (ZBX_SOCK_ERROR == (s->sockets[s->num_socks] = socket(AF_INET, SOCK_STREAM | SOCK_CLOEXEC, 0)))
		{
			zbx_set_tcp_strerror("socket() for [[%s]:%hu] failed: %s",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_sock_last_error()));
			goto out;
		}

#if !defined(_WINDOWS) && !SOCK_CLOEXEC
		fcntl(s->sockets[s->num_socks], F_SETFD, FD_CLOEXEC);
#endif

		/* Enable address reuse */
		/* This is to immediately use the address even if it is in TIME_WAIT state */
		/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
		on = 1;
		if (ZBX_TCP_ERROR == setsockopt(s->sockets[s->num_socks], SOL_SOCKET, SO_REUSEADDR, (void *)&on,
				sizeof(on)))
		{
			zbx_set_tcp_strerror("setsockopt() for [[%s]:%hu] failed: %s",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_sock_last_error()));
		}

		memset(&serv_addr, 0, sizeof(serv_addr));

		serv_addr.sin_family		= AF_INET;
		serv_addr.sin_addr.s_addr	= NULL != ip ? inet_addr(ip) : htonl(INADDR_ANY);
		serv_addr.sin_port		= htons((unsigned short)listen_port);

		if (ZBX_TCP_ERROR == bind(s->sockets[s->num_socks], (struct sockaddr *)&serv_addr, sizeof(serv_addr)))
		{
			zbx_set_tcp_strerror("bind() for [[%s]:%hu] failed: %s",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_sock_last_error()));
			zbx_sock_close(s->sockets[s->num_socks]);
			goto out;
		}

		if (ZBX_TCP_ERROR == listen(s->sockets[s->num_socks], SOMAXCONN))
		{
			zbx_set_tcp_strerror("listen() for [[%s]:%hu] failed: %s",
					ip ? ip : "-", listen_port, strerror_from_system(zbx_sock_last_error()));
			zbx_sock_close(s->sockets[s->num_socks]);
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
		zbx_set_tcp_strerror("zbx_tcp_listen() fatal error: unable to serve on any address [[%s]:%hu]",
				listen_ip, listen_port);
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != ips)
		zbx_free(ips);

	if (SUCCEED != ret)
	{
		for (i = 0; i < s->num_socks; i++)
			zbx_sock_close(s->sockets[i]);
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
int	zbx_tcp_accept(zbx_sock_t *s)
{
	ZBX_SOCKADDR	serv_addr;
	fd_set		sock_set;
	ZBX_SOCKET	accepted_socket;
	ZBX_SOCKLEN_T	nlen;
	int		i, n = 0;

	zbx_tcp_unaccept(s);

	FD_ZERO(&sock_set);

	for (i = 0; i < s->num_socks; i++)
	{
		FD_SET(s->sockets[i], &sock_set);
#if !defined(_WINDOWS)
		if (s->sockets[i] > n)
			n = s->sockets[i];
#endif
	}

	if (ZBX_TCP_ERROR == select(n + 1, &sock_set, NULL, NULL, NULL))
	{
		zbx_set_tcp_strerror("select() failed: %s", strerror_from_system(zbx_sock_last_error()));
		return FAIL;
	}

	for (i = 0; i < s->num_socks; i++)
	{
		if (FD_ISSET(s->sockets[i], &sock_set))
			break;
	}

	/* Since this socket was returned by select(), we know we have */
	/* a connection waiting and that this accept() will not block. */
	nlen = sizeof(serv_addr);
	if (ZBX_SOCK_ERROR ==
			(accepted_socket = (ZBX_SOCKET)accept(s->sockets[i], (struct sockaddr *)&serv_addr, &nlen)))
	{
		zbx_set_tcp_strerror("accept() failed: %s", strerror_from_system(zbx_sock_last_error()));
		return FAIL;
	}

	s->socket_orig	= s->socket;		/* remember main socket */
	s->socket	= accepted_socket;	/* replace socket to accepted */
	s->accepted	= 1;

	return SUCCEED;
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
void	zbx_tcp_unaccept(zbx_sock_t *s)
{
	if (!s->accepted) return;

	shutdown(s->socket, 2);

	zbx_sock_close(s->socket);

	s->socket	= s->socket_orig;	/* restore main socket */
	s->socket_orig	= ZBX_SOCK_ERROR;
	s->accepted	= 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_free                                                     *
 *                                                                            *
 * Purpose: close open socket                                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_tcp_free(zbx_sock_t *s)
{
	zbx_free(s->buf_dyn);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_recv                                                     *
 *                                                                            *
 * Purpose: receive data                                                      *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_tcp_recv_ext(zbx_sock_t *s, char **data, unsigned char flags, int timeout)
{
#define ZBX_BUF_LEN	ZBX_STAT_BUF_LEN*8

	ssize_t		nbytes, left;
	ssize_t		read_bytes;

	int		allocated, offset;
	int		ret = SUCCEED;
	zbx_uint64_t	expected_len;

	ZBX_TCP_START();

	if (0 != timeout)
		zbx_tcp_timeout_set(s, timeout);

	zbx_free(s->buf_dyn);

	memset(s->buf_stat, 0, sizeof(s->buf_stat));
	*data = s->buf_stat;

	read_bytes = 0;
	s->buf_type = ZBX_BUF_TYPE_STAT;

	left = ZBX_TCP_HEADER_LEN;
	nbytes = ZBX_TCP_READ(s->socket, s->buf_stat, left);

	if (ZBX_TCP_HEADER_LEN == nbytes && 0 == strncmp(s->buf_stat, ZBX_TCP_HEADER, ZBX_TCP_HEADER_LEN))
	{
		left = sizeof(zbx_uint64_t);
		nbytes = ZBX_TCP_READ(s->socket, (void *)&expected_len, left);
		expected_len = zbx_letoh_uint64(expected_len);

		if (ZBX_MAX_RECV_DATA_SIZE < expected_len)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Message size " ZBX_FS_UI64 " exceeds the maximum size "
					ZBX_FS_UI64 " bytes. Message ignored.", expected_len,
					(zbx_uint64_t)ZBX_MAX_RECV_DATA_SIZE);
			ret = FAIL;
			goto cleanup;
		}

		/* The rest was already cleared */
		memset(s->buf_stat, 0, ZBX_TCP_HEADER_LEN);

		flags |= ZBX_TCP_READ_UNTIL_CLOSE;
	}
	else if (ZBX_TCP_ERROR != nbytes)
	{
		read_bytes = nbytes;
		expected_len = 16 * ZBX_MEBIBYTE;
	}

	if (ZBX_TCP_ERROR != nbytes)
	{
		if (flags & ZBX_TCP_READ_UNTIL_CLOSE)
		{
			if (0 == nbytes)
				goto cleanup;
		}
		else
		{
			if (nbytes < left)
				goto cleanup;
		}

		left = sizeof(s->buf_stat) - read_bytes - 1;

		/* fill static buffer */
		if ('\n' != s->buf_stat[read_bytes - 1])	/* don't try to read from an empty socket */
		{
			while (read_bytes < expected_len && left > 0 &&
					ZBX_TCP_ERROR !=
					(nbytes = ZBX_TCP_READ(s->socket, s->buf_stat + read_bytes, left)))
			{
				read_bytes += nbytes;

				if (flags & ZBX_TCP_READ_UNTIL_CLOSE)
				{
					if (0 == nbytes)
						break;
				}
				else
				{
					if (nbytes < left)	/* should we stop reading? */
					{
						/* XML protocol? */
						if (0 == strncmp(s->buf_stat, "<req>", sizeof("<req>") - 1))
						{
							/* closing tag received in the last 10 bytes? */
							s->buf_stat[read_bytes] = '\0';
							if (NULL != strstr(s->buf_stat + read_bytes -
									(10 > read_bytes ? read_bytes : 10), "</req>"))
								break;
						}
						else
							break;
					}
				}

				left -= nbytes;
			}
		}

		s->buf_stat[read_bytes] = '\0';
		if ((sizeof(s->buf_stat) - 1) == read_bytes)	/* static buffer is full */
		{
			allocated = ZBX_BUF_LEN;

			s->buf_type = ZBX_BUF_TYPE_DYN;
			s->buf_dyn = zbx_malloc(s->buf_dyn, allocated);

			memset(s->buf_dyn, 0, allocated);
			memcpy(s->buf_dyn, s->buf_stat, sizeof(s->buf_stat));

			offset = read_bytes;
			/* fill dynamic buffer */
			while (read_bytes < expected_len &&
					ZBX_TCP_ERROR !=
					(nbytes = ZBX_TCP_READ(s->socket, s->buf_stat, sizeof(s->buf_stat) - 1)))
			{
				s->buf_stat[nbytes] = '\0';
				zbx_strcpy_alloc(&s->buf_dyn, &allocated, &offset, s->buf_stat);
				read_bytes += nbytes;

				if (flags & ZBX_TCP_READ_UNTIL_CLOSE)
				{
					if (0 == nbytes)
						break;
				}
				else
				{
					if (nbytes < sizeof(s->buf_stat) - 1)	/* should we stop reading? */
					{
						/* XML protocol? */
						if (0 == strncmp(s->buf_dyn, "<req>", sizeof("<req>") - 1))
						{
							/* closing tag received in the last 10 bytes? */
							if (NULL != strstr(s->buf_dyn + read_bytes - 10, "</req>"))
								break;
						}
						else
							break;
					}
				}
			}

			*data = s->buf_dyn;
		}
	}

	if (ZBX_TCP_ERROR == nbytes)
	{
		zbx_set_tcp_strerror("ZBX_TCP_READ() failed: %s", strerror_from_system(zbx_sock_last_error()));
		ret = FAIL;
	}
cleanup:
	if (0 != timeout)
		zbx_tcp_timeout_cleanup(s);

	return ret;
}

char	*get_ip_by_socket(zbx_sock_t *s)
{
	ZBX_SOCKADDR	sa;
	ZBX_SOCKLEN_T	sz;
	static char	buffer[64];

	*buffer = '\0';

	sz = sizeof(sa);
	if (ZBX_TCP_ERROR == getpeername(s->socket, (struct sockaddr*)&sa, &sz))
	{
		zbx_set_tcp_strerror("connection rejected, getpeername() failed: %s",
				strerror_from_system(zbx_sock_last_error()));
		return buffer;
	}

#if defined(HAVE_IPV6)
	if (0 != getnameinfo((struct sockaddr*)&sa, sizeof(sa), buffer, sizeof(buffer), NULL, 0, NI_NUMERICHOST))
	{
		zbx_set_tcp_strerror("connection rejected, getnameinfo() failed: %s",
				strerror_from_system(zbx_sock_last_error()));
	}
#else
	zbx_snprintf(buffer, sizeof(buffer), "%s", inet_ntoa(sa.sin_addr));
#endif

	return buffer;
}

/******************************************************************************
 *                                                                            *
 * Function: check_security                                                   *
 *                                                                            *
 * Purpose: check if connection initiator is in list of IP addresses          *
 *                                                                            *
 * Parameters: sockfd - socket descriptor                                     *
 *             ip_list - comma-delimited list of IP addresses                 *
 *             allow_if_empty - allow connection if no IP given               *
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
int	zbx_tcp_check_security(zbx_sock_t *s, const char *ip_list, int allow_if_empty)
{
#if defined(HAVE_IPV6)
	struct addrinfo	hints, *ai = NULL;
	/* Network Byte Order is ensured */

	/* IPv4-Compatible, the first 96 bits are zeros */
	unsigned char	ipv4_cmp_mask[12] = {0};

	/* IPv4-Mapped, the first 80 bits are zeros, 16 next - ones */
	unsigned char	ipv4_mpd_mask[12] = {0,0,0,0,0,0,0,0,0,0,255,255};
#else
	struct hostent	*hp;
	char		*sip;
	int		i[4], j[4];
#endif
	ZBX_SOCKADDR	name;
	ZBX_SOCKLEN_T	nlen;

	char		tmp[MAX_STRING_LEN], sname[MAX_STRING_LEN], *start = NULL, *end = NULL;

	if (1 == allow_if_empty && (NULL == ip_list || '\0' == *ip_list))
		return SUCCEED;

	nlen = sizeof(name);

	if (ZBX_TCP_ERROR == getpeername(s->socket, (struct sockaddr *)&name, &nlen))
	{
		zbx_set_tcp_strerror("connection rejected, getpeername() failed: %s",
				strerror_from_system(zbx_sock_last_error()));
		return FAIL;
	}
	else
	{
#if !defined(HAVE_IPV6)
		zbx_strlcpy(sname, inet_ntoa(name.sin_addr), sizeof(sname));

		if (4 != sscanf(sname, "%d.%d.%d.%d", &i[0], &i[1], &i[2], &i[3]))
			return FAIL;
#endif
		strscpy(tmp,ip_list);

		for (start = tmp; '\0' != *start;)
		{
			if (NULL != (end = strchr(start, ',')))
				*end = '\0';

			/* allow IP addresses or DNS names for authorization */
#if defined(HAVE_IPV6)
			memset(&hints, 0, sizeof(hints));
			hints.ai_family = PF_UNSPEC;
			if (0 == getaddrinfo(start, NULL, &hints, &ai))
			{
#ifdef HAVE_SOCKADDR_STORAGE_SS_FAMILY
				if (ai->ai_family == name.ss_family)
#else
				if (ai->ai_family == name.__ss_family)
#endif
				{
					switch (ai->ai_family)
					{
						case AF_INET  :
							if (((struct sockaddr_in*)&name)->sin_addr.s_addr ==
									((struct sockaddr_in*)ai->ai_addr)->sin_addr.s_addr)
							{
								freeaddrinfo(ai);
								return SUCCEED;
							}
							break;
						case AF_INET6 :
							if (0 == memcmp(((struct sockaddr_in6*)&name)->sin6_addr.s6_addr,
									((struct sockaddr_in6*)ai->ai_addr)->sin6_addr.s6_addr,
									sizeof(struct in6_addr)))
							{
								freeaddrinfo(ai);
								return SUCCEED;
							}
							break;
					}
				}
				else
				{
					switch (ai->ai_family)
					{
						case AF_INET  :
							/* incoming AF_INET6, must see whether it is comp or mapped */
							if ((0 == memcmp(((struct sockaddr_in6*)&name)->sin6_addr.s6_addr, ipv4_cmp_mask, 12) ||
								0 == memcmp(((struct sockaddr_in6*)&name)->sin6_addr.s6_addr, ipv4_mpd_mask, 12)) && 
								0 == memcmp(&((struct sockaddr_in6*)&name)->sin6_addr.s6_addr[12],
									(unsigned char*)&((struct sockaddr_in*)ai->ai_addr)->sin_addr.s_addr, 4))
							{
								freeaddrinfo(ai);
								return SUCCEED;
							}
							break;
						case AF_INET6 :
							/* incoming AF_INET, must see whether the given is comp or mapped */
							if ((0 == memcmp(((struct sockaddr_in6*)ai->ai_addr)->sin6_addr.s6_addr, ipv4_cmp_mask, 12) ||
								0 == memcmp(((struct sockaddr_in6*)ai->ai_addr)->sin6_addr.s6_addr, ipv4_mpd_mask, 12)) &&
								0 == memcmp(&((struct sockaddr_in6*)ai->ai_addr)->sin6_addr.s6_addr[12],
									(unsigned char*)&((struct sockaddr_in*)&name)->sin_addr.s_addr, 4))
							{
								freeaddrinfo(ai);
								return SUCCEED;
							}
							break;
					}
				}
				freeaddrinfo(ai);
			}
#else
			if (NULL != (hp = gethostbyname(start)))
			{
				sip = inet_ntoa(*((struct in_addr *)hp->h_addr));

				if (4 == sscanf(sip, "%d.%d.%d.%d", &j[0], &j[1], &j[2], &j[3]) &&
						i[0] == j[0] && i[1] == j[1] && i[2] == j[2] && i[3] == j[3])
				{
					return SUCCEED;
				}
			}
#endif	/* HAVE_IPV6 */
			if (NULL != end)
			{
				*end = ',';
				start = end + 1;
			}
			else
				break;
		}

		if (NULL != end)
			*end = ',';
	}
#if defined(HAVE_IPV6)
	if (0 == getnameinfo((struct sockaddr*)&name, sizeof(name), sname, sizeof(sname), NULL, 0, NI_NUMERICHOST))
		zbx_set_tcp_strerror("Connection from [%s] rejected. Allowed server is [%s]", sname, ip_list);
	else
		zbx_set_tcp_strerror("Connection rejected. Allowed server is [%s]", ip_list);
#else
	zbx_set_tcp_strerror("Connection from [%s] rejected. Allowed server is [%s]", sname, ip_list);
#endif
	return	FAIL;
}
