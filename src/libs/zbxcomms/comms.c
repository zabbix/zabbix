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

#	define	zbx_sock_close(s)		if( ZBX_SOCK_ERROR != (s) ) closesocket(s)
#	define  zbx_sock_last_error()	WSAGetLastError()
#else

#	define ZBX_TCP_WRITE(s, b, bl)	((ssize_t)write((s), (b), (bl)))
#	define ZBX_TCP_READ(s, b, bl)	((ssize_t)read((s), (b), (bl)))

#	define ZBX_TCP_ERROR	-1
#	define ZBX_SOCK_ERROR	-1

#	define	zbx_sock_close(s)		if( ZBX_SOCK_ERROR != (s) ) close(s)
#	define  zbx_sock_last_error()	errno
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

static char zbx_tcp_strerror_message[ZBX_TCP_MAX_STRERROR];

char*	zbx_tcp_strerror(void)
{
	zbx_tcp_strerror_message[ZBX_TCP_MAX_STRERROR - 1] = '\0'; /* forse terminate string */
	return (&zbx_tcp_strerror_message[0]);
}
 
#define ZBX_TCP_ERR_START	zbx_snprintf( zbx_tcp_strerror_message, ZBX_TCP_MAX_STRERROR, 
#define ZBX_TCP_ERR_END		)

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

	if(host)        return host;

	ZBX_TCP_ERR_START "gethost() failed for address '%s' [%s]", hostname, strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;

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

	switch(WSAStartup(0x0002,&sockInfo))
	{
		case WSASYSNOTREADY:
			ZBX_TCP_ERR_START "Underlying network subsystem is not ready for network communication." ZBX_TCP_ERR_END;
			return FAIL;
		case WSAVERNOTSUPPORTED:
			ZBX_TCP_ERR_START "The version of Windows Sockets support requested is not provided." ZBX_TCP_ERR_END;
			return FAIL;
		case WSAEINPROGRESS:
			ZBX_TCP_ERR_START "A blocking Windows Sockets 1.1 operation is in progress." ZBX_TCP_ERR_END;
			return FAIL;
		case WSAEPROCLIM:
			ZBX_TCP_ERR_START "Limit on the number of tasks supported by the Windows Sockets implementation has been reached." ZBX_TCP_ERR_END;
			return FAIL;
		case WSAEFAULT:
			ZBX_TCP_ERR_START "The lpWSAData is not a valid pointer." ZBX_TCP_ERR_END;
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
int     zbx_tcp_connect(zbx_sock_t *s, const char *ip, unsigned short port)
{
	ZBX_SOCKADDR	myaddr_in;
	ZBX_SOCKADDR	servaddr_in;

	struct	hostent *hp;

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	if(NULL == (hp = zbx_gethost(ip)))
	{
		ZBX_TCP_ERR_START "Cannot resolve [%s]", ip ZBX_TCP_ERR_END;
		return	FAIL;
	}

	servaddr_in.sin_family		= AF_INET;
	servaddr_in.sin_addr.s_addr	= ((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port		= htons(port);

	if( ZBX_SOCK_ERROR == (s->socket = socket(AF_INET,SOCK_STREAM,0)) )
	{
		ZBX_TCP_ERR_START "Cannot create socket [%s:%d] [%s]", ip, port ,strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;
		return	FAIL;
	}

	myaddr_in.sin_family		= AF_INET;
	myaddr_in.sin_port			= 0;
	myaddr_in.sin_addr.s_addr	= INADDR_ANY;

	if( ZBX_TCP_ERROR == connect(s->socket,(struct sockaddr *)&servaddr_in,sizeof(ZBX_SOCKADDR)) )
	{
		ZBX_TCP_ERR_START "Cannot connect to [%s:%d] [%s]", ip, port, strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;
		zbx_tcp_close(s);
		return	FAIL;
	}

	return SUCCEED;
}

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

int     zbx_tcp_send_ext(zbx_sock_t *s, const char *data, unsigned char flags)
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
			zbx_tcp_close(s);
			return	FAIL;
		}

		len64 = (zbx_uint64_t)strlen(data);

		/* Write data length */
		if( ZBX_TCP_ERROR == ZBX_TCP_WRITE(s->socket, (char *) &len64, sizeof(len64)) )
		{
			zbx_tcp_close(s);
			return	FAIL;
		}
	}

	while(written < (ssize_t)strlen(data))
	{
		if( ZBX_TCP_ERROR == (i = ZBX_TCP_WRITE(s->socket, data+written,strlen(data)-written)) )
		{
			zbx_tcp_close(s);
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
void    zbx_tcp_close(zbx_sock_t *s)
{
	ZBX_TCP_START();

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
int zbx_tcp_listen(
	zbx_sock_t		*s,
	const char		*listen_ip,
	unsigned short	listen_port
	)
{
	ZBX_SOCKADDR serv_addr;
	int	on;

	ZBX_TCP_START();

	zbx_tcp_clean(s);

	if( ZBX_SOCK_ERROR == (s->socket = socket(AF_INET,SOCK_STREAM,0)) )
	{
		ZBX_TCP_ERR_START "Cannot create socket [%s:%u] [%s]", listen_ip, listen_port ,strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;
		return	FAIL;
	}

	/* Enable address reuse */
	/* This is to immediately use the address even if it is in TIME_WAIT state */
	/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
	on = 1;
	if( -1 == setsockopt(s->socket, SOL_SOCKET, SO_REUSEADDR, (void *)&on, sizeof(on) ))
	{
		ZBX_TCP_ERR_START "Cannot setsockopt SO_REUSEADDR [%s]", strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;
	}

	/* Create socket	Fill in local address structure */
	memset(&serv_addr, 0, sizeof(ZBX_SOCKADDR));

	serv_addr.sin_family		= AF_INET;
	serv_addr.sin_addr.s_addr	= listen_ip ? inet_addr(listen_ip) : htonl(INADDR_ANY);
	serv_addr.sin_port			= htons((unsigned short)listen_port);

	/* Bind socket */
	if (ZBX_SOCK_ERROR == bind(s->socket,(struct sockaddr *)&serv_addr,sizeof(ZBX_SOCKADDR)) )
	{
		ZBX_TCP_ERR_START "Cannot bind to port %u for server %s. Error [%s]. Another zabbix_agentd already running ?",
				listen_port,
				listen_ip ? listen_ip : "[ANY]",
				strerror_from_system(zbx_sock_last_error())
		ZBX_TCP_ERR_END;

		return	FAIL;
	}

	if( ZBX_SOCK_ERROR == listen(s->socket, SOMAXCONN) )
	{
		ZBX_TCP_ERR_START "Listen failed. [%s]", strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;
		return	FAIL;
	}
	return SUCCEED;
}

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
int	zbx_tcp_accept(zbx_sock_t *s)
{
	ZBX_SOCKADDR	serv_addr;
	ZBX_SOCKET	accepted_socket;

	socklen_t	nlen;

	nlen = sizeof(ZBX_SOCKADDR);

	zbx_tcp_unaccept(s);

	if(ZBX_TCP_ERROR == (accepted_socket = (ZBX_SOCKET)accept(s->socket, (struct sockaddr *)&serv_addr, &nlen)))
	{
		ZBX_TCP_ERR_START "accept() failed [%s]", strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;
		return	FAIL;
	}

	s->socket2	= s->socket;		/* remember main socket */
	s->socket	= accepted_socket;	/* replace socket to accepted */
	s->accepted = 1;

	return	SUCCEED;
}

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
void    zbx_tcp_free(zbx_sock_t *s)
{
	if(s->buf_type == ZBX_BUF_TYPE_DYN && s->buf_dyn!=NULL)
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

		/* The rest was already cleared */
		memset(s->buf_stat,0,ZBX_TCP_HEADER_LEN);
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
			s->buf_dyn		= zbx_malloc(allocated);

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
		ZBX_TCP_ERR_START "ZBX_TCP_READ() failed [%s]", strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;
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
	ZBX_SOCKADDR name;
	socklen_t	nlen;

	struct  hostent *hp;

	char
		tmp[MAX_STRING_LEN], 
		sname[MAX_STRING_LEN],
		*sip, 
		*host;

	zabbix_log( LOG_LEVEL_DEBUG, "In check_security()");

	if( (1 == allow_if_empty) && ( !ip_list || !*ip_list ) )
	{
		return SUCCEED;
	}

	nlen = sizeof(ZBX_SOCKADDR);
	if( ZBX_TCP_ERROR == getpeername(s->socket,  (struct sockaddr*)&name, &nlen))
	{
		ZBX_TCP_ERR_START "Connection rejected. Getpeername failed [%s]", strerror_from_system(zbx_sock_last_error()) ZBX_TCP_ERR_END;
		return FAIL;
	}
	else
	{
		strcpy(sname, inet_ntoa(name.sin_addr));

		strscpy(tmp,ip_list);

		host = (char *)strtok(tmp,",");

		while( NULL != host )
		{
			/* Allow IP addresses or DNS names for authorization */
			if( 0 != (hp = zbx_gethost(host)))
			{
				sip = inet_ntoa(*((struct in_addr *)hp->h_addr));
				if( 0 == strcmp(sname, sip))
				{
					ZBX_TCP_ERR_START "Connection from [%s] accepted. Allowed servers [%s] ",sname, ip_list ZBX_TCP_ERR_END;
					return	SUCCEED;
				}
			}
			host = (char *)strtok(NULL,",");
		}
	}
	ZBX_TCP_ERR_START "Connection from [%s] rejected. Allowed server is [%s] ",sname, ip_list ZBX_TCP_ERR_END;
	return	FAIL;
}

