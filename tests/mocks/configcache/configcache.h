/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_CONFIGCACHE_H
#define ZABBIX_CONFIGCACHE_H

#include "zbxalgo.h"
#include "dbconfig.h"
#include "zbxalgo.h"

#include "../../../src/libs/zbxcacheconfig/user_macro.h"

typedef struct
{
	zbx_dc_config_t		dc;
	zbx_vector_um_host_t	um_hosts;
	zbx_vector_ptr_t	hosts;

	zbx_uint64_t		initialized;

}
zbx_mock_config_t;

zbx_mock_config_t	*get_mock_config(void);

#define ZBX_MOCK_CONFIG_USERMACROS	0x0001
#define ZBX_MOCK_CONFIG_HOSTS		0x0002

void	free_string(const char *str);

#endif
