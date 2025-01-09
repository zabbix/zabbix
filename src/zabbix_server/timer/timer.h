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

#ifndef ZABBIX_TIMER_H
#define ZABBIX_TIMER_H

#include "zbxthreads.h"

typedef struct
{
	zbx_get_config_forks_f	get_process_forks_cb_arg;
}
zbx_thread_timer_args;

ZBX_THREAD_ENTRY(timer_thread, args);

#endif
