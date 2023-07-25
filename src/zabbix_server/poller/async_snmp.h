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

#ifndef ZABBIX_ASYNC_SNMP_H
#define ZABBIX_ASYNC_SNMP_H

#include "zbxcacheconfig.h"
#include "module.h"
#include "zbxasyncpoller.h"
#include "async_poller.h"



int	zbx_async_snmp_agent(zbx_dc_item_t *items, AGENT_RESULT *results, int *errcodes, int num,
		zbx_async_task_clear_cb_t clear_cb, void *arg, void *arg_action, struct event_base *base,
		int config_timeout, const char *config_source_ip);
//void	zbx_async_check_agent_clean(zbx_agent_context *agent_context);

#endif
