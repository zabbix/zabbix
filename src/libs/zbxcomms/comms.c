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

#	define ZBX_TCP_ERROR	SOCKET_ERROR
#	define ZBX_SOCK_ERROR	INVALID_SOCKET

#	define zbx_sock_close(s)		if( ZBX_SOCK_ERROR != (s) ) closesocket(s)
#	define zbx_sock_last_error()	WSAGetLastError()

#	define ZBX_SOCK_ERR_TIMEDOUT	WSAETIMEDOUT
#else
#	define ZBX_TCP_WRITE(s, b, bl)	((ssize_t)write((s), (b), (bl)))
#	define ZBX_TCP_READ(s, b, bl)	((ssize_t)read((s), (b), (bl)))

#	define ZBX_TCP_ERROR	-1
#	define ZBX_SOCK_ERROR	-1

#	define	zbx_sock_close(s)		if( ZBX_SOCK_ERROR != (s) ) close(s)
#	define  zbx_sock_last_error()	errno

#	define ZBX_SOCK_ERR_TIMEDOUT	EINTR

#endif /* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_strerror                                                 *
 *                                                                            *
 * Purpose: return string describing of tcp error                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: pointer to the null terminated string                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
#define ZBX_TCP_MAX_STRERROR	255

int	zbx_tcp_error(void)
{
	if( ZBX_SOCK_ERR_TIMEDOUT == zbx_sock_last_error() )
	{
		return ZBX_TCP_ERR_TIMEOUT;
	}

	return ZBX_TCP_ERR_NETWORK;
}

static char zbx_tcp_strerror_message[ZBX_TCP_MAX_STRERROR];

char*	zbx_tcp_strerror(void)
{
	zbx_tcp_strerror_message[ZBX_TCP_MAX_STRERROR - 1] = '\0'; /* forse terminate string */
	return (&zbx_tcp_strerror_message[0]);
}

#ifdef HAVE___VA_ARGS__
#	define zbx_set_tcp_strerror(fmt, ...) __zbx_zbx_set_tcp_strerror(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_set_tcp_strerror __zbx_zbx_set_tcp_strerror
#endif /* HAVE___VA_ARGS__ */
static void __zbx_zbx_set_tcp_strerror(const char *fmt, ...)
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
 * Purpose: retrive 'hostent' by IP address                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

#if defined(HAVE_IPV6)
void	zbx_gethost_by_ip(const char *ip, char *host, size_t hostlen)
{
	struct	addrinfo hints, *ai = NULL;
	
	assert(ip);

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = PF_UNSPEC;
	if(0 != getaddrinfo(ip, NULL, &hints, &ai))
	{
		host[0] = '\0';
		goto out;
	}
	if(0 != getnameinfo(ai->ai_addr, ai->ai_addrlen, host, hostlen, NULL, 0, NI_NAMEREQD))
	{
		host[0] = '\0';
		goto out;
	}
out:
	freeaddrinfo(ai);
}
#else
void	zbx_gethost_by_ip(const char *ip, char *host, size_t hostlen)
{
	struct in_addr	addr;
	struct hostent  *hst;

	assert(ip);

	if(inet_aton(ip, &addr) == 0)
	{
		host[0] = '\0';
		return;
	}

	if(NULL == (hst = gethostbyaddr((char *)&addr, sizeof(addr), AF_INET)))
	{
		host[0] = '\0';
		return;
	}

	zbx_strlcpy(host, hst->h_name, hostlen);
}
#endif /*HAVE_IPV6*/
#endif /* WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: zbx_gethost                                                      *
 *                                                                            *
 * Purpose: retrive 'hostent' by host name and IP                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: hostent or NULL - an error occured                           *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
struct hostent	*zbx_gethost(const char *hostname)
{
	unsigned int	addr;
	struct hostent*	host;

	assert(hostname);

	host = gethostbyname(hostname);
	if(host)	return host;

	addr = inet_addr(hostname);

	host = gethostbyaddr((char *)&addr, 4, AF_INET);

	if(host)	return host;


	zbx_set_tcp_strerror("gethost() failed for address '%s' [%s]", hostname, strerror_from_system(zbx_sock_last_error()));

	return (struct hostent*) NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_start                                                    *
 *                                                                            *
 * Purpose: Initialize Windows Sockets APIa                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED or FAIL - an error occured                           *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
#if defined(_WINDOWS)

#define ZBX_TCP_START() { if( FAIL == tcp_started ) tcp_started = zbx_tcp_start(); }

/* static (winXX threads require OFF) */ int	tcp_started = FAIL;

static int	zbx_tcp_start(void)
{
	WSADATA sockInfo;

	switch(WSAStartup(MAKEWORD(2, 2),&sockInfo))
	{
		case WSASYSNOTREADY:
			zbx_set_tcp_strerror("Underlying network subsystem is not ready for network communication.");
			return FAIL;
		case WSAVERNOTSUPPORTED:
			zbx_set_tcp_strerror("The version of Windows Sockets support requested is not provided.");
			return FAIL;
		case WSAEINPROGRESS:
			zbx_set_tcp_strerror("A blocking Windows Sockets 1.1 operation is in progress.");
			return FAIL;
		case WSAEPROCLIM:
			zbx_set_tcp_strerror("Limit on the number of tasks supported by the Windows Sockets implementation has been reached.");
			return FAIL;
		case WSAEFAULT:
			zbx_set_tcp_strerror("The lpWSAData is not a valid pointer.");
			return FAIL;
	}

	return SUCCEED;
}

#else
#	define ZBX_TCP_START() {}
#endif /* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_clean                                                    *
 *                                                                            *
 * Purpose: initialize socket                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
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
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_tcp_init(zbx_sock_t *s, ZBX_SOCKET o)
{
	zbx_tcp_clean(s);

	s->socket = o;
}
/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_connect                                                  *
 *                                                                            *
 * Purpose: connect to external host                                          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: sockfd - open socket                                         *
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_IPV6)
int	zbx_tcp_connect(zbx_sock_t *s, const char *ip, unsigned short port)
{
	int	ret=SUCCEED;
	struct	addrinfo *ai, hints;
	char	service[MAX_STRING_LEN];

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	zbx_snprintf(service, sizeof(service), "%d", port);
	memset(&hints, 0x00, sizeof(struct addrinfo));
	hints.ai_family = PF_UNSPEC;
	hints.ai_socktype = SOCK_STREAM;

	if( 0 != getaddrinfo (ip, service, &hints, &ai)) {
		zbx_set_tcp_strerror("Cannot resolve [%s]", ip);
		ret=FAIL;
		goto out;
	}

	if( ZBX_SOCK_ERROR == (s->socket = socket (ai->ai_family, ai->ai_socktype, ai->ai_protocol)))
	{
		zbx_set_tcp_strerror("Cannot create socket [%s]:%d [%s]", ip, port ,strerror_from_system(zbx_sock_last_error()));
		ret=FAIL;
		goto out;
	}

	if( ZBX_TCP_ERROR == connect(s->socket, ai->ai_addr, ai->ai_addrlen)) {
		zbx_set_tcp_strerror("*** Cannot connect to [%s]:%d [%s]", ip, port, strerror_from_system(zbx_sock_last_error()));
		zbx_tcp_close(s);
		ret=FAIL;
		goto out;
	}
out:
	freeaddrinfo (ai);
	return ret;
}
#else
int	zbx_tcp_connect(zbx_sock_t *s, const char *ip, unsigned short port)
{
	ZBX_SOCKADDR	servaddr_in;

	struct	hostent *hp;

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	if(NULL == (hp = zbx_gethost(ip)))
	{
		zbx_set_tcp_strerror("Cannot resolve [%s]", ip);
		return	FAIL;
	}
	servaddr_in.sin_family		= AF_INET;
	servaddr_in.sin_addr.s_addr	= ((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port		= htons(port);

	if( ZBX_SOCK_ERROR == (s->socket = socket(AF_INET,SOCK_STREAM,0)) )
	{
		zbx_set_tcp_strerror("Cannot create socket [%s:%d] [%s]", ip, port ,strerror_from_system(zbx_sock_last_error()));
		return	FAIL;
	}

	if( ZBX_TCP_ERROR == connect(s->socket,(struct sockaddr *)&servaddr_in,sizeof(ZBX_SOCKADDR)) )
	{
		zbx_set_tcp_strerror("Cannot connect to [%s:%d] [%s]", ip, port, strerror_from_system(zbx_sock_last_error()));
		zbx_tcp_close(s);
		return	FAIL;
	}

	return SUCCEED;
}
#endif /*HAVE_IPV6*/
/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_send                                                     *
 *                                                                            *
 * Purpose: send data                                                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

#define ZBX_TCP_HEADER_DATA		"ZBXD"
#define ZBX_TCP_HEADER_VERSION	"\1"
#define ZBX_TCP_HEADER			ZBX_TCP_HEADER_DATA ZBX_TCP_HEADER_VERSION
#define ZBX_TCP_HEADER_LEN		5

int	zbx_tcp_send_ext(zbx_sock_t *s, const char *data, unsigned char flags)
{
	zbx_uint64_t	len64;

	ssize_t	i = 0,
			written = 0;

	ZBX_TCP_START();

	if( flags & ZBX_TCP_NEW_PROTOCOL )
	{
		/* Write header */
		if( ZBX_TCP_ERROR == ZBX_TCP_WRITE(s->socket, ZBX_TCP_HEADER, ZBX_TCP_HEADER_LEN))
		{
			zbx_set_tcp_strerror("ZBX_TCP_WRITE() failed [%s]", strerror_from_system(zbx_sock_last_error()));
			return	FAIL;
		}

		len64 = (zbx_uint64_t)strlen(data);
		len64 = zbx_htole_uint64(len64);

		/* Write data length */
		if( ZBX_TCP_ERROR == ZBX_TCP_WRITE(s->socket, (char *) &len64, sizeof(len64)) )
		{
			zbx_set_tcp_strerror("ZBX_TCP_WRITE() failed [%s]", strerror_from_system(zbx_sock_last_error()));
			return	FAIL;
		}
	}

	while(written < (ssize_t)strlen(data))
	{
		if( ZBX_TCP_ERROR == (i = ZBX_TCP_WRITE(s->socket, data+written,strlen(data)-written)) )
		{
			zbx_set_tcp_strerror("ZBX_TCP_WRITE() failed [%s]", strerror_from_system(zbx_sock_last_error()));
			return	FAIL;
		}
		written += i;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_close                                                    *
 *                                                                            *
 * Purpose: close open socket                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_tcp_close(zbx_sock_t *s)
{
	zbx_tcp_unaccept(s);
	
	zbx_tcp_free(s);

	zbx_sock_close(s->socket);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_listen                                                   *
 *                                                                            *
 * Purpose: create socket for listening                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_IPV6)
int zbx_tcp_listen(
	zbx_sock_t	*s,
	const char	*listen_ip,
	unsigned short	listen_port
	)
{
	struct		addrinfo hints, *ai = NULL, *current_ai;
	char		port[MAX_STRING_LEN];
	int		e, on;

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = PF_UNSPEC;
	hints.ai_flags = AI_NUMERICHOST | AI_PASSIVE;
	hints.ai_socktype = SOCK_STREAM;
	zbx_snprintf(port, sizeof(port), "%d", listen_port);
	if(0 != (e = getaddrinfo(listen_ip, port, &hints, &ai)))
	{
		zbx_set_tcp_strerror("Cannot resolve address [[%s]:%u], error %d: %s", listen_ip, listen_port, e, gai_strerror(e));
		return FAIL;
	}

	for(s->num_socks = 0, current_ai = ai; current_ai != NULL; current_ai = current_ai->ai_next)
	{
		if(s->num_socks == FD_SETSIZE) {
			break;
		}
		/* This example only supports PF_INET and PF_INET6. */
		if((current_ai->ai_family != PF_INET) && (current_ai->ai_family != PF_INET6))
			continue;

		if((s->sockets[s->num_socks] = socket(current_ai->ai_family, current_ai->ai_socktype, current_ai->ai_protocol)) == ZBX_SOCK_ERROR) {
			zbx_set_tcp_strerror("socket() failed with error %d: %s", zbx_sock_last_error(), strerror_from_system(zbx_sock_last_error()));
			continue;
		}

		/* Enable address reuse */
		/* This is to immediately use the address even if it is in TIME_WAIT state */
		/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
		on = 1;
		if(setsockopt(s->sockets[s->num_socks], SOL_SOCKET, SO_REUSEADDR | SO_ACCEPTCONN, (void *)&on, sizeof(on)) == ZBX_TCP_ERROR)
		{
			zbx_set_tcp_strerror("setsockopt() failed with error %d: %s", zbx_sock_last_error(), strerror_from_system(zbx_sock_last_error()));
		}

		/* Create socket	Fill in local address structure */
		if(bind(s->sockets[s->num_socks], current_ai->ai_addr, current_ai->ai_addrlen) == ZBX_TCP_ERROR) {
			zbx_set_tcp_strerror("bind() failed with error %d: %s", zbx_sock_last_error(), strerror_from_system(zbx_sock_last_error()));
			zbx_sock_close(s->sockets[s->num_socks]);
			continue;
		}

		if(ZBX_SOCK_ERROR == listen(s->sockets[s->num_socks], SOMAXCONN) )
		{
			zbx_set_tcp_strerror("listen() failed with error %d: %s", zbx_sock_last_error(), strerror_from_system(zbx_sock_last_error()));
			zbx_sock_close(s->sockets[s->num_socks]);
			continue;
		}

		s->num_socks++;
	}
	freeaddrinfo(ai);

	if(s->num_socks == 0) {
		zbx_set_tcp_strerror("zbx_tcp_listen() Fatal error: unable to serve on any address. [[%s]:%u]", listen_ip, listen_port);
		return FAIL;
	}
	return SUCCEED;
}
#else
int zbx_tcp_listen(
	zbx_sock_t	*s,
	const char	*listen_ip,
	unsigned short	listen_port
	)
{
	ZBX_SOCKADDR	serv_addr;
	int		on, res = FAIL;

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	if(ZBX_SOCK_ERROR == (s->socket = socket(AF_INET,SOCK_STREAM,0)))
	{
		zbx_set_tcp_strerror("Cannot create socket [%s:%u] [%s]", listen_ip, listen_port, strerror_from_system(zbx_sock_last_error()));
		goto out;
	}

	/* Enable address reuse */
	/* This is to immediately use the address even if it is in TIME_WAIT state */
	/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
	on = 1;
	if( -1 == setsockopt(s->socket, SOL_SOCKET, SO_REUSEADDR | SO_ACCEPTCONN, (void *)&on, sizeof(on) ))
	{
		zbx_set_tcp_strerror("Cannot setsockopt SO_REUSEADDR [%s]", strerror_from_system(zbx_sock_last_error()));
	}

	/* Create socket	Fill in local address structure */
	memset(&serv_addr, 0, sizeof(ZBX_SOCKADDR));

	serv_addr.sin_family		= AF_INET;
	serv_addr.sin_addr.s_addr	= listen_ip ? inet_addr(listen_ip) : htonl(INADDR_ANY);
	serv_addr.sin_port		= htons((unsigned short)listen_port);

	/* Bind socket */
	if (ZBX_SOCK_ERROR == bind(s->socket,(struct sockaddr *)&serv_addr,sizeof(ZBX_SOCKADDR)) )
	{
		zbx_set_tcp_strerror("Cannot bind to port %u for server %s. Error [%s]. Another zabbix_agentd already running ?",
				listen_port,
				listen_ip ? listen_ip : "[ANY]",
				strerror_from_system(zbx_sock_last_error()));

		goto out;
	}

	if( ZBX_SOCK_ERROR == listen(s->socket, SOMAXCONN) )
	{
		zbx_set_tcp_strerror("Listen failed. [%s]", strerror_from_system(zbx_sock_last_error()));
		goto out;
	}
	res = SUCCEED;
out:
	return res;
}
#endif /*HAVE_IPV6*/
/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_accept                                                   *
 *                                                                            *
 * Purpose: permits an incoming connection attempt on a socket                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - success                                            * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_IPV6)
int	zbx_tcp_accept(zbx_sock_t *s)
{
	struct sockaddr_storage	serv_addr;
	fd_set			sock_set;
	ZBX_SOCKET		accepted_socket;
	socklen_t		nlen;
	int			i, n = 0;

	zbx_tcp_unaccept(s);

	FD_ZERO(&sock_set);

	/* For connection orientated protocols, we will handle the
	   packets comprising a connection collectively.  For datagram
	   protocols, we have to handle each datagram individually.

	   Check to see if we have any sockets remaining to be served
	   from previous time through this loop.  If not, call select()
	   to wait for a connection request or a datagram to arrive. */

	for(i = 0; i < s->num_socks; i++) {
		if(s->sockets[i] > n)
			n = s->sockets[i];
		if(FD_ISSET(s->sockets[i], &sock_set))
			break;
	}

	if(i == s->num_socks) {
		for (i = 0; i < s->num_socks; i++)
			FD_SET(s->sockets[i], &sock_set);
		if(select(n + 1, &sock_set, 0, 0, 0) == ZBX_TCP_ERROR) {
			zbx_set_tcp_strerror("select() failed with error %d: %s", zbx_sock_last_error(), strerror_from_system(zbx_sock_last_error()));
			return	FAIL;
		}
	}
	for (i = 0; i < s->num_socks; i++) {
		if (FD_ISSET(s->sockets[i], &sock_set)) {
			FD_CLR(s->sockets[i], &sock_set);
			break;
		}
	}

	/* Since this socket was returned by the select(), we know we
	   have a connection waiting and that this accept() won't block.*/
	nlen = sizeof(serv_addr);
	if((accepted_socket = (ZBX_SOCKET)accept(s->sockets[i], (struct sockaddr *)&serv_addr, &nlen)) == ZBX_SOCK_ERROR) {
		zbx_set_tcp_strerror("accept() failed with error %d: %s", zbx_sock_last_error(), strerror_from_system(zbx_sock_last_error()));
		return FAIL;
	}

	s->socket2	= s->socket;		/* remember main socket */
	s->socket	= accepted_socket;	/* replace socket to accepted */
	s->accepted	= 1;

	return SUCCEED;
}
#else
int	zbx_tcp_accept(zbx_sock_t *s)
{
	ZBX_SOCKADDR	serv_addr;
	ZBX_SOCKET	accepted_socket;
	socklen_t	nlen;

	nlen = sizeof(serv_addr);

	zbx_tcp_unaccept(s);

	if(ZBX_TCP_ERROR == (accepted_socket = (ZBX_SOCKET)accept(s->socket, (struct sockaddr *)&serv_addr, &nlen)))
	{
		zbx_set_tcp_strerror("accept() failed [%s]", strerror_from_system(zbx_sock_last_error()));
		return	FAIL;
	}

	s->socket2	= s->socket;		/* remember main socket */
	s->socket	= accepted_socket;	/* replace socket to accepted */
	s->accepted	= 1;

	return	SUCCEED;
}
#endif /* HAVE_IPV6 */
/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_unaccept                                                 *
 *                                                                            *
 * Purpose: close accepted connection                                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_tcp_unaccept(zbx_sock_t *s)
{
	if( !s->accepted ) return;

	shutdown(s->socket,2);

	zbx_sock_close(s->socket);

	s->socket	= s->socket2;		/* restore main socket */
	s->socket2	= ZBX_SOCK_ERROR;
	s->accepted = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tcp_close                                                    *
 *                                                                            *
 * Purpose: close open socket                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
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
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - success                                            *
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_tcp_recv_ext(zbx_sock_t *s, char **data, unsigned char flags)
{
#define ZBX_BUF_LEN			ZBX_STAT_BUF_LEN*8

	ssize_t	nbytes, left;
	ssize_t	read_bytes;

	int	allocated, offset;
	zbx_uint64_t	expected_len;


	ZBX_TCP_START();

	zbx_free(s->buf_dyn);

	memset(s->buf_stat, 0, sizeof(s->buf_stat));
	*data = s->buf_stat;

	read_bytes = 0;
	s->buf_type = ZBX_BUF_TYPE_STAT;


	left = ZBX_TCP_HEADER_LEN;
	nbytes = ZBX_TCP_READ(s->socket, s->buf_stat, left);

	if( ZBX_TCP_HEADER_LEN == nbytes && 0 == strncmp(s->buf_stat, ZBX_TCP_HEADER, ZBX_TCP_HEADER_LEN) )
	{

		left = sizeof(zbx_uint64_t);
		nbytes = ZBX_TCP_READ(s->socket, (void *)&expected_len, left);
		expected_len = zbx_letoh_uint64(expected_len);

		/* The rest was already cleared */
		memset(s->buf_stat,0,ZBX_TCP_HEADER_LEN);

		flags |= ZBX_TCP_READ_UNTIL_CLOSE;
	}
	else if( ZBX_TCP_ERROR != nbytes )
	{
		read_bytes		= nbytes;
		expected_len	= 16*1024*1024;		
	}

	if( ZBX_TCP_ERROR != nbytes )
	{
		if( flags & ZBX_TCP_READ_UNTIL_CLOSE ) {
			if(nbytes == 0)		return	SUCCEED;
		} else {
			if(nbytes < left)	return	SUCCEED;
		}

		left = sizeof(s->buf_stat) - read_bytes - 1;


		/* fill static buffer */
		while(	read_bytes < expected_len && left > 0
			&& ZBX_TCP_ERROR != (nbytes = ZBX_TCP_READ( s->socket, s->buf_stat + read_bytes, left)))
		{

			read_bytes += nbytes;

			if( flags & ZBX_TCP_READ_UNTIL_CLOSE ) {
				if(nbytes == 0)	break;
			} else {
				if(nbytes < left) break;
			}

			left -= nbytes;
		}

		s->buf_stat[read_bytes] = '\0';
		if( (sizeof(s->buf_stat) - 1) == read_bytes) /* static buffer is full */
		{
			allocated		= ZBX_BUF_LEN;

			s->buf_type		= ZBX_BUF_TYPE_DYN;
			s->buf_dyn		= zbx_malloc(s->buf_dyn, allocated);

			memset(s->buf_dyn,0,allocated);
			memcpy(s->buf_dyn, s->buf_stat, sizeof(s->buf_stat));


			offset = read_bytes;
			/* fill dynamic buffer */
			while( read_bytes < expected_len && ZBX_TCP_ERROR != (nbytes = ZBX_TCP_READ(s->socket, s->buf_stat, sizeof(s->buf_stat)-1)) )
			{
				s->buf_stat[nbytes] = '\0';
				zbx_snprintf_alloc(&(s->buf_dyn), &allocated, &offset, sizeof(s->buf_stat), "%s", s->buf_stat);
				read_bytes += nbytes;

				if( flags & ZBX_TCP_READ_UNTIL_CLOSE ) {
					if(nbytes == 0)	break;
				} else {
					if(nbytes < sizeof(s->buf_stat) - 1) break;
				}
			}

			*data = s->buf_dyn;
		}
	}

	if( ZBX_TCP_ERROR == nbytes )
	{
		zbx_set_tcp_strerror("ZBX_TCP_READ() failed [%s]", strerror_from_system(zbx_sock_last_error()));
		return	FAIL;
	}

	return	SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Function: check_security                                                   *
 *                                                                            *
 * Purpose: check if connection initiator is in list of IP addresses          *
 *                                                                            *
 * Parameters: sockfd - socker descriptor                                     *
 *             ip_list - comma-delimited list of IP addresses                 *
 *             allow_if_empty - allow connection if no IP given               *
 *                                                                            *
 * Return value: SUCCEED - connection allowed                                 *
 *               FAIL - connection is not allowed                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int	zbx_tcp_check_security(
	zbx_sock_t *s, 
	const char *ip_list, 
	int allow_if_empty
	)
{
#if defined(HAVE_IPV6)
	struct		sockaddr_storage name;
	struct		addrinfo hints, *ai = NULL;
#else
	ZBX_SOCKADDR	name;
	struct		hostent *hp;
	char		*sip;
	int		i[4], j[4];
#endif
	socklen_t	nlen;

	char	tmp[MAX_STRING_LEN], 
		sname[MAX_STRING_LEN],
		*start = NULL,
		*end = NULL,
		c = '\0';

	zabbix_log( LOG_LEVEL_DEBUG, "In check_security()");

	if( (1 == allow_if_empty) && ( !ip_list || !*ip_list ) )
	{
		return SUCCEED;
	}
	nlen = sizeof(name);
	if( ZBX_TCP_ERROR == getpeername(s->socket, (struct sockaddr*)&name, &nlen))
	{
		zbx_set_tcp_strerror("Connection rejected. Getpeername failed [%s]", strerror_from_system(zbx_sock_last_error()));
		return FAIL;
	}
	else
	{
#if !defined(HAVE_IPV6)
		strcpy(sname, inet_ntoa(name.sin_addr));

		if(sscanf(sname, "%d.%d.%d.%d", &i[0], &i[1], &i[2], &i[3]) != 4)
		{
			return FAIL;
		}
#endif /*HAVE_IPV6*/
		strscpy(tmp,ip_list);

		for(start = tmp; start[0] != '\0';)
		{
			end = strchr(start, ',');

			if(end != NULL)
			{
				c = end[0];
				end[0] = '\0';
			}

			/* Allow IP addresses or DNS names for authorization */
#if defined(HAVE_IPV6)
			memset(&hints, 0, sizeof(hints));
			hints.ai_family = PF_UNSPEC;
			if(0 == getaddrinfo(start, NULL, &hints, &ai))
			{
				if(ai->ai_family == name.ss_family)
				{
					switch(ai->ai_family)
					{
						case AF_INET  :
							if(((struct sockaddr_in*)&name)->sin_addr.s_addr == ((struct sockaddr_in*)ai->ai_addr)->sin_addr.s_addr)
							{
								freeaddrinfo(ai);
								return SUCCEED;
							}
						case AF_INET6 :
							if(0 == memcmp(((struct sockaddr_in6*)&name)->sin6_addr.s6_addr,
									((struct sockaddr_in6*)ai->ai_addr)->sin6_addr.s6_addr,
									sizeof(struct in6_addr)))
							{
								freeaddrinfo(ai);
								return SUCCEED;
							}
					}
				}
			}
			freeaddrinfo(ai);
#else
			if( 0 != (hp = zbx_gethost(start)))
			{
				sip = inet_ntoa(*((struct in_addr *)hp->h_addr));
				if(sscanf(sip, "%d.%d.%d.%d", &j[0], &j[1], &j[2], &j[3]) == 4)
				{
					if(i[0] == j[0] && i[1] == j[1] && i[2] == j[2] && i[3] == j[3])
					{
						return SUCCEED;
					}
				}
			}
#endif /*HAVE_IPV6*/
			if(end != NULL)
			{
				end[0] = c;
				start = end + 1;
			}
			else
			{
				break;
			}
		}

		if(end != NULL)
		{
			end[0] = c;
		}
	}
#if defined(HAVE_IPV6)
	if(0 == getnameinfo((struct sockaddr*)&name, sizeof(name), sname, sizeof(sname), NULL, 0, NI_NUMERICHOST))
	{
		zbx_set_tcp_strerror("Connection from [%s] rejected. Allowed server is [%s] ",sname, ip_list);
	}
	else
	{
		zbx_set_tcp_strerror("Connection rejected. Allowed server is [%s] ", ip_list);
	}
#else
	zbx_set_tcp_strerror("Connection from [%s] rejected. Allowed server is [%s] ",sname, ip_list);
#endif /*HAVE_IPV6*/
	return	FAIL;
}
