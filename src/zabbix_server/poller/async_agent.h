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

#ifndef ZABBIX_ASYNC_AGENT_H
#define ZABBIX_ASYNC_AGENT_H

#include "zbxcacheconfig.h"
#include "httpagent_async.h"
#include "module.h"
#include "../../libs/zbxasyncpoller/asyncpoller.h"

typedef enum
{
	ZABBIX_AGENT_STEP_CONNECT_WAIT = 0,
	ZABBIX_AGENT_STEP_SEND,
	ZABBIX_AGENT_STEP_RECV
}
zbx_zabbix_agent_step_t;

typedef struct
{
	zbx_poller_config_t	*poller_config;
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	unsigned char		value_type;
	unsigned char		flags;
	unsigned char		state;
	char			*key;
	char			*key_orig;
	zbx_dc_host_t		host;
	zbx_dc_interface_t	interface;
	zbx_socket_t		s;
	zbx_zabbix_agent_step_t	step;
	char			*server_name;
	const char		*tls_arg1;
	const char		*tls_arg2;
	int			ret;
	AGENT_RESULT		result;
}
zbx_agent_context;

int	zbx_async_check_agent(zbx_dc_item_t *item, AGENT_RESULT *result, zbx_poller_config_t *poller_config,
		zbx_async_task_clear_cb_t clear_cb);
void	zbx_async_check_agent_clean(zbx_agent_context *agent_context);

#endif
