/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_NODESENDER_H
#define ZABBIX_NODESENDER_H

#include "mutexs.h"

extern ZBX_MUTEX	node_sync_access;

int	calculate_checksums(int nodeid, const char *tablename, const zbx_uint64_t id);
char	*DMget_config_data(int nodeid, unsigned char dest_nodetype);
int	update_checksums(int nodeid, int synked_nodetype, int synked, const char *tablename,
		const zbx_uint64_t id, const char *fields);
void	node_sync_lock(int nodeid);
void	node_sync_unlock(int nodeid);
void	process_nodes(void);

#endif
