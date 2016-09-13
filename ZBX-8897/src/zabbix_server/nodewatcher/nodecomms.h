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

#ifndef ZABBIX_NODECOMMS_H
#define ZABBIX_NODECOMMS_H

#include "comms.h"

extern char	*CONFIG_SOURCE_IP;

int	connect_to_node(int nodeid, zbx_sock_t *sock);
int	send_data_to_node(int nodeid, zbx_sock_t *sock, const char *data);
int	recv_data_from_node(int nodeid, zbx_sock_t *sock, char **data);
void	disconnect_node(zbx_sock_t *sock);

int	send_to_node(const char *name, int dest_nodeid, int nodeid, char *data);

#endif
