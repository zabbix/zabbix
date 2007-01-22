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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <sys/wait.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include "common.h"
#include "comms.h"
#include "zbxsock.h"

void	zbx_tcp_init(zbx_sock_t *s)
{
	memset(s, 0, sizeof(zbx_sock_t));
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
int     zbx_tcp_connect(zbx_sock_t *s, char *ip, int port)
{
	struct	sockaddr_in myaddr_in;
	struct	sockaddr_in servaddr_in;
	struct	hostent *hp;

	memset(s, 0, sizeof(zbx_sock_t));

	servaddr_in.sin_family=AF_INET;

	if(NULL == (hp = zbx_gethost(ip)))
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Cannot resolve [%s]", ip);
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s->socket = socket(AF_INET,SOCK_STREAM,0);
	if(s->socket == -1)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Cannot create socket [%s:%d] [%s]", ip, port ,strerror(errno));
		return	FAIL;
	}

	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s->socket,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Cannot connect to [%s:%d] [%s]", ip, port, strerror(errno));
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
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int     zbx_tcp_send(zbx_sock_t *s, char *data)
{
	ssize_t	i;
	char	header[5]="ZBXD\1";
	zbx_uint64_t	len64;
	ssize_t	written = 0;

	/* Write header */
	i=write(s->socket, header, 5);
	if(i == -1)
	{
		zbx_tcp_close(s);
		return	FAIL;
	}
	len64 = (zbx_uint64_t)strlen(data);

	/* Write data length */
	i=write(s->socket, &len64, sizeof(len64));
	if(i == -1)
	{
		zbx_tcp_close(s);
		return	FAIL;
	}

	while(written<strlen(data))
	{
		i=write(s->socket, data+written,strlen(data)-written);
		if(i == -1)
		{
			zbx_tcp_close(s);
			return	FAIL;
		}
		written+=i;
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
	zbx_tcp_free(s);
	if(s->socket != 0)	close(s->socket);
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
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_tcp_recv(zbx_sock_t *s, char **data)
{
	char	*bufptr;
#define ZBX_STAT_BUF_LEN	2048
#define ZBX_BUF_LEN	ZBX_STAT_BUF_LEN*8
	ssize_t	nbytes;
	ssize_t	read_max;
	ssize_t	read_bytes;

	int	allocated, offset;
	zbx_uint64_t	expected_len;

	memset(s->buf_stat,0,ZBX_STAT_BUF_LEN);
	s->buf_type = ZBX_BUF_TYPE_STAT;
	nbytes=read(s->socket, s->buf_stat, 5);

	if(nbytes==5 && strncmp(s->buf_stat,"ZBXD",4)==0 && s->buf_stat[4] == 1)
	{
		nbytes=read(s->socket, (zbx_uint64_t *)&expected_len, sizeof(expected_len));

		read_max = ZBX_STAT_BUF_LEN -1;
		/* The rest was already cleared */
		memset(s->buf_stat,0,5);
		nbytes = read(s->socket, s->buf_stat, read_max);

		*data = s->buf_stat;
	}
	else
	{
		bufptr = s->buf_stat+strlen(s->buf_stat);

		read_max = ZBX_STAT_BUF_LEN - strlen(s->buf_stat) -1;
		nbytes = read(s->socket, bufptr, read_max);
		expected_len = 16*1024*1024;

		*data = s->buf_stat;
	}

	read_bytes = nbytes;

	if(nbytes == read_max)
	{
		s->buf_type = ZBX_BUF_TYPE_DYN;
		allocated = ZBX_BUF_LEN;
		s->buf_dyn=malloc(allocated);

		memset(s->buf_dyn,0,ZBX_BUF_LEN);
		strnscpy(s->buf_dyn, s->buf_stat, ZBX_BUF_LEN);
		offset = strlen(s->buf_dyn);
	
		while (read_bytes<expected_len && (nbytes = read(s->socket, s->buf_stat, ZBX_STAT_BUF_LEN-1-1)) != -1 && nbytes != 0)
		{
			s->buf_stat[nbytes]='\0';
			zbx_snprintf_alloc(&s->buf_dyn, &allocated, &offset, ZBX_BUF_LEN-1, "%s", s->buf_stat);
			read_bytes+=nbytes;
		}
		*data = s->buf_dyn;
	}

	if(nbytes < 0)
	{
/*		if(errno == EINTR)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Read timeout");
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "read() failed");
		}*/
		return	FAIL;
	}

	return	SUCCEED;
}
