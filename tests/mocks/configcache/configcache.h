/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxserver.h"
#include "common.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "mutexs.h"

#define ZBX_DBCONFIG_IMPL
#include "dbconfig.h"

#include "configcache_mock.h"

#ifndef ZABBIX_CONFIGCACHE_H
#define ZABBIX_CONFIGCACHE_H

typedef struct
{
	ZBX_DC_CONFIG		dc;
	zbx_vector_ptr_t	host_macros;
	zbx_vector_ptr_t	global_macros;
	zbx_vector_ptr_t	hosts;

	zbx_uint64_t		initialized;

}
zbx_mock_config_t;

#define ZBX_MOCK_CONFIG_USERMACROS	0x0001
#define ZBX_MOCK_CONFIG_HOSTS		0x0002

void	free_string(const char *str);

#endif
