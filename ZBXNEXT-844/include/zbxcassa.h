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

int	zbx_cassandra_connect(const char *host, const char *keyspace, int port);
void	zbx_cassandra_close();

int	zbx_cassandra_set_value(const zbx_uint64_pair_t *key, char *column_family, const char *column, const char *value);
char	*zbx_cassandra_get_value(const zbx_uint64_pair_t *key, char *column_family, const char *column);

#endif

#endif
