/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_HISTORY_HISTORY_SERVER_H
#define ZABBIX_HISTORY_HISTORY_SERVER_H

#include "zbxcacheconfig.h"
#include "zbxtime.h"

void	history_process_item_value_server(const zbx_history_recv_item_t *item, AGENT_RESULT *result, zbx_timespec_t *ts,
		int *h_num, char *error);

#endif
