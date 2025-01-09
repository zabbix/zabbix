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

#ifndef ZABBIX_HOUSEKEEPER_H
#define ZABBIX_HOUSEKEEPER_H

#include "zbxthreads.h"

typedef struct
{
	struct zbx_db_version_info_t	*db_version_info;
	int				config_timeout;
	int				config_housekeeping_frequency;
	int				config_max_housekeeper_delete;
}
zbx_thread_housekeeper_args;

ZBX_THREAD_ENTRY(housekeeper_thread, args);

typedef struct
{
	int	config_timeout;
	int	config_problemhousekeeping_frequency;
}
zbx_thread_server_trigger_housekeeper_args;

ZBX_THREAD_ENTRY(trigger_housekeeper_thread, args);

#endif
