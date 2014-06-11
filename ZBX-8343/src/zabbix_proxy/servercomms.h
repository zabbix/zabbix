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

#ifndef ZABBIX_SERVERCOMMS_H
#define ZABBIX_SERVERCOMMS_H

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_SERVER;
extern int	CONFIG_SERVER_PORT;
extern char	*CONFIG_HOSTNAME;

#include "comms.h"

int	connect_to_server(zbx_sock_t *sock, int timeout, int retry_interval);
void	disconnect_server(zbx_sock_t *sock);

int	get_data_from_server(zbx_sock_t *sock, const char *request, char **data);
int	put_data_to_server(zbx_sock_t *sock, struct zbx_json *j);

#endif
