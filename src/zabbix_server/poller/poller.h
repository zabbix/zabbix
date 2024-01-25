/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_SERVER_POLLER_H
#define ZABBIX_SERVER_POLLER_H

#include "zbxthreads.h"
#include "zbxcacheconfig.h"
#include "zbxcomms.h"

typedef struct
{
	zbx_config_comms_args_t	*config_comms;
	zbx_get_program_type_f	zbx_get_program_type_cb_arg;
	zbx_get_progname_f	zbx_get_progname_cb_arg;
	unsigned char		poller_type;
	int			config_startup_time;
	int			config_unavailable_delay;
	int			config_unreachable_period;
	int			config_unreachable_delay;
	int			config_max_concurrent_checks_per_poller;
	const char		*config_externalscripts;
	const char		*config_java_gateway;
	int			config_java_gateway_port;
}
zbx_thread_poller_args;

ZBX_THREAD_ENTRY(poller_thread, args);

ZBX_THREAD_ENTRY(async_poller_thread, args);

zbx_get_program_type_f  poller_get_program_type(void);
zbx_get_progname_f	poller_get_progname(void);

void	zbx_prepare_items(zbx_dc_item_t *items, int *errcodes, int num, AGENT_RESULT *results,
		unsigned char expand_macros);
void	zbx_check_items(zbx_dc_item_t *items, int *errcodes, int num, AGENT_RESULT *results,
		zbx_vector_ptr_t *add_results, unsigned char poller_type, const zbx_config_comms_args_t *config_comms,
		int config_startup_time, unsigned char program_type, const char *progname,
		const char *config_externalscripts, const char *config_java_gateway, int config_java_gateway_port);
void	zbx_clean_items(zbx_dc_item_t *items, int num, AGENT_RESULT *results);
void	zbx_free_agent_result_ptr(AGENT_RESULT *result);

#endif
