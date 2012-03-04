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

#ifndef ZABBIX_ZBXCASSA_H
#define ZABBIX_ZBXCASSA_H

#include "common.h"

#ifdef HAVE_CASSANDRA

#include "zbxalgo.h"

#define ZBX_CASSANDRA_CONNECT_NORMAL	0
#define ZBX_CASSANDRA_CONNECT_EXIT	1

typedef struct
{
	char	*host;
	int	port;
}
zbx_cassandra_host_t;

typedef struct
{
	zbx_cassandra_host_t	*hosts;
	int			count;
}
zbx_cassandra_hosts_t;

void	zbx_cassandra_parse_hosts(char *hosts, zbx_cassandra_hosts_t *h);
void	zbx_cassandra_connect(int flag, zbx_cassandra_hosts_t *h, const char *keyspace);
void	zbx_cassandra_close();

void	zbx_cassandra_add_history_value(zbx_uint64_t itemid, zbx_uint64_t clock, const char *value);
void	zbx_cassandra_save_history_values();

void	zbx_cassandra_fetch_history_values(zbx_vector_str_t *values, zbx_uint64_t itemid,
		zbx_uint64_t clock_from, zbx_uint64_t clock_to, int last_n);

#endif

#endif
