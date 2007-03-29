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
	ZBX_BUF_TYPE_STAT = 0,
	ZBX_BUF_TYPE_DYN
} zbx_buf_type_t;

typedef struct zbx_sock
{
	int		socket;
	char		buf_stat[1024];
	char		*buf_dyn;
	zbx_buf_type_t	buf_type;
	char		*error;
} zbx_sock_t;

void	zbx_tcp_init(zbx_sock_t *s);
int	zbx_tcp_connect(zbx_sock_t *socket, const char *ip, int port);
int	zbx_tcp_send(zbx_sock_t *socket, char *data);
int	zbx_tcp_recv(zbx_sock_t *socket, char **data);
void	zbx_tcp_close(zbx_sock_t *socket);
void	zbx_tcp_free(zbx_sock_t *socket);

#endif
