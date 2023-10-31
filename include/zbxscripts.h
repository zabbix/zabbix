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

#ifndef ZABBIX_SCRIPTS_H
#define ZABBIX_SCRIPTS_H

#include "zbxcacheconfig.h"

typedef struct
{
	unsigned char	type;
	unsigned char	execute_on;
	char		*port;
	unsigned char	authtype;
	char		*username;
	char		*password;
	char		*publickey;
	char		*privatekey;
	char		*command;
	char		*command_orig;
	zbx_uint64_t	scriptid;
	unsigned char	host_access;
	int		timeout;
}
zbx_script_t;

void	zbx_script_init(zbx_script_t *script);
void	zbx_script_clean(zbx_script_t *script);
int	zbx_check_script_permissions(zbx_uint64_t groupid, zbx_uint64_t hostid);
int	zbx_check_script_user_permissions(zbx_uint64_t userid, zbx_uint64_t hostid, zbx_script_t *script);
int	zbx_db_fetch_webhook_params(zbx_uint64_t scriptid, zbx_vector_ptr_pair_t *params, char *error,
		size_t error_len);
void	zbx_webhook_params_pack_json(const zbx_vector_ptr_pair_t *params, char **params_json);
int	zbx_script_prepare(zbx_script_t *script, const zbx_uint64_t *hostid, char *error, size_t max_error_len);
int	zbx_script_execute(const zbx_script_t *script, const zbx_dc_host_t *host, const char *params,
		int config_timeout, int config_trapper_timeout, const char *config_source_ip,
		zbx_get_config_forks_f get_config_forks, unsigned char program_type, char **result, char *error,
		size_t max_error_len, char **debug);
zbx_uint64_t	zbx_script_create_task(const zbx_script_t *script, const zbx_dc_host_t *host, zbx_uint64_t alertid,
		int now);
int	zbx_init_remote_commands_cache(char **error);
void	zbx_deinit_remote_commands_cache(void);
void	zbx_process_command_results(struct zbx_json_parse *jp);
void	zbx_remote_commans_prepare_to_send(struct zbx_json *json, zbx_uint64_t hostid);

#endif
