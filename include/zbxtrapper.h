/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_TRAPPER_H
#define ZABBIX_TRAPPER_H

#include "zbxthreads.h"

#include "zbxdbhigh.h"
#include "zbxcomms.h"
#include "zbxvault.h"
#include "zbxpoller.h"
#include "zbxautoreg.h"

zbx_get_program_type_f trapper_get_program_type(void);

typedef int	(*zbx_trapper_process_request_func_t)(const char *request, zbx_socket_t *sock,
		const struct zbx_json_parse *jp, const zbx_timespec_t *ts, const zbx_config_comms_args_t *config_comms,
		const zbx_config_vault_t *config_vault, int proxydata_frequency,
		zbx_get_program_type_f get_program_type_cb, const zbx_events_funcs_t *events_cbs,
		zbx_get_config_forks_f get_config_forks);

typedef struct
{
	zbx_config_comms_args_t			*config_comms;
	zbx_config_vault_t			*config_vault;
	zbx_get_program_type_f			zbx_get_program_type_cb_arg;
	const char				*progname;
	const zbx_events_funcs_t		*events_cbs;
	zbx_socket_t				*listen_sock;
	int					config_startup_time;
	int					proxydata_frequency;
	zbx_get_config_forks_f			get_process_forks_cb_arg;
	const char				*config_stats_allowed_ip;
	const char				*config_java_gateway;
	int					config_java_gateway_port;
	const char				*config_externalscripts;
	int					config_enable_global_scripts;
	zbx_get_value_internal_ext_f		zbx_get_value_internal_ext_cb;
	const char				*config_ssh_key_location;
	const char				*config_webdriver_url;
	zbx_trapper_process_request_func_t	trapper_process_request_func_cb;
	zbx_autoreg_update_host_func_t		autoreg_update_host_cb;
}
zbx_thread_trapper_args;

ZBX_THREAD_ENTRY(zbx_trapper_thread, args);

int	zbx_get_user_from_json(const struct zbx_json_parse *jp, zbx_user_t *user, char **result);

int	zbx_trapper_item_test_run(const struct zbx_json_parse *jp_data, zbx_uint64_t proxyid, char **info,
		const zbx_config_comms_args_t *config_comms, int config_startup_time, unsigned char program_type,
		const char *progname, zbx_get_config_forks_f get_config_forks,  const char *config_java_gateway,
		int config_java_gateway_port, const char *config_externalscripts,
		zbx_get_value_internal_ext_f get_value_internal_ext_cb, const char *config_ssh_key_location,
		const char *config_webdriver_url);

int	zbx_trapper_preproc_test_run(const struct zbx_json_parse *jp_item, const struct zbx_json_parse *jp_options,
		const struct zbx_json_parse *jp_steps, char *value, size_t value_size, int state, struct zbx_json *json,
		char **error);

void	zbx_trapper_item_test_add_value(struct zbx_json *json, int ret, const char *info);

#endif
