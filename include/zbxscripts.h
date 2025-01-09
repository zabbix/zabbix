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
	char		*name;
	char		*command;
	char		*command_orig;
	zbx_uint64_t	scriptid;
	unsigned char	host_access;
	int		timeout;
	unsigned char	manualinput;
	char		*manualinput_validator;
	unsigned char	manualinput_validator_type;
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
		const char *config_ssh_key_location, int config_enable_global_scripts,
		zbx_get_config_forks_f get_config_forks, unsigned char program_type, char **result, char *error,
		size_t max_error_len, char **debug);
zbx_uint64_t	zbx_script_create_task(const zbx_script_t *script, const zbx_dc_host_t *host, zbx_uint64_t alertid,
		int now);
int	zbx_init_remote_commands_cache(char **error);
void	zbx_deinit_remote_commands_cache(void);
void	zbx_process_command_results(struct zbx_json_parse *jp);
void	zbx_remote_commands_prepare_to_send(struct zbx_json *json, zbx_uint64_t hostid, int config_timeout);

#endif
