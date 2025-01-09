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

#ifndef ZABBIX_SERVICE_MANAGER_H
#define ZABBIX_SERVICE_MANAGER_H

#include "zbxthreads.h"

typedef struct
{
	int	config_timeout;
	int	config_service_manager_sync_frequency;
}
zbx_thread_service_manager_args;

ZBX_THREAD_ENTRY(service_manager_thread, args);

#endif
