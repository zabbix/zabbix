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

#ifndef ZABBIX_ZBX_POLLER_H
#define ZABBIX_ZBX_POLLER_H

#include "zbxcacheconfig.h"
#include "module.h"

void	zbx_activate_item_interface(zbx_timespec_t *ts, zbx_dc_interface_t *interface, zbx_uint64_t itemid, int type,
		char *host, int version, unsigned char **data, size_t *data_alloc, size_t *data_offset);
void	zbx_deactivate_item_interface(zbx_timespec_t *ts, zbx_dc_interface_t *interface, zbx_uint64_t itemid, int type,
		char *host, char *key_orig, unsigned char **data, size_t *data_alloc, size_t *data_offset,
		int unavailable_delay, int unreachable_period, int unreachable_delay, const char *error);

int	zbx_telnet_get_value(zbx_dc_item_t *item, const char *config_source_ip, AGENT_RESULT *result);
int	zbx_agent_get_value(const zbx_dc_item_t *item, const char *config_source_ip, unsigned char program_type,
		AGENT_RESULT *result, int *version);
#if defined(HAVE_SSH2) || defined(HAVE_SSH)
int	zbx_ssh_get_value(zbx_dc_item_t *item, const char *config_source_ip, AGENT_RESULT *result);
#endif

typedef int	(*zbx_get_value_internal_ext_f)(const char *param1, const AGENT_REQUEST *request, AGENT_RESULT *result);

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
	zbx_get_config_forks_f	get_config_forks;
	const char		*config_java_gateway;
	int			config_java_gateway_port;
	const char		*config_externalscripts;
	zbx_get_value_internal_ext_f	zbx_get_value_internal_ext_cb;
}
zbx_thread_poller_args;

ZBX_THREAD_ENTRY(poller_thread, args);

ZBX_THREAD_ENTRY(async_poller_thread, args);

void	zbx_prepare_items(zbx_dc_item_t *items, int *errcodes, int num, AGENT_RESULT *results,
		unsigned char expand_macros);
void	zbx_check_items(zbx_dc_item_t *items, int *errcodes, int num, AGENT_RESULT *results,
		zbx_vector_ptr_t *add_results, unsigned char poller_type, const zbx_config_comms_args_t *config_comms,
		int config_startup_time, unsigned char program_type, const char *progname,
		zbx_get_config_forks_f get_config_forks, const char *config_java_gateway, int config_java_gateway_port,
		const char *config_externalscripts, zbx_get_value_internal_ext_f get_value_internal_ext_cb);
void	zbx_clean_items(zbx_dc_item_t *items, int num, AGENT_RESULT *results);
void	zbx_free_agent_result_ptr(AGENT_RESULT *result);

int	zbx_get_value_snmp(zbx_dc_item_t *item, AGENT_RESULT *result, unsigned char poller_type,
		const char *config_source_ip, const char *progname);
void	zbx_init_library_mt_snmp(const char *progname);

void	zbx_shutdown_library_mt_snmp(const char *progname);

void	zbx_clear_cache_snmp(unsigned char process_type, int process_num, const char *progname);

#endif /* ZABBIX_ZBX_POLLER_H*/
