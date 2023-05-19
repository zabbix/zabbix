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

#include "scripts.h"
#include "zbxserver.h"

#include "../poller/checks_agent.h"
#include "../ipmi/ipmi.h"
#include "../poller/checks_ssh.h"
#include "../poller/checks_telnet.h"
#include "zbxexec.h"
#include "zbxdbhigh.h"
#include "log.h"
#include "zbxtasks.h"
#include "zbxembed.h"
#include "zbxnum.h"
#include "zbxsysinfo.h"
#include "zbxparam.h"
#include "zbxmutexs.h"
#include "zbxshmem.h"
#include "zbx_availability_constants.h"

#define REMOTE_COMMAND_SENT		1
#define REMOTE_COMMAND_RESULT_OOM	2
#define REMOTE_COMMAND_RESULT_WAIT	4

extern int	CONFIG_TRAPPER_TIMEOUT;
extern int	CONFIG_FORKS[ZBX_PROCESS_TYPE_COUNT];

static zbx_uint64_t	remote_command_cache_size = 256 * ZBX_KIBIBYTE;

static zbx_mutex_t	remote_commands_lock = ZBX_MUTEX_NULL;
static zbx_shmem_info_t	*remote_commands_mem = NULL;
ZBX_SHMEM_FUNC_IMPL(__remote_commands, remote_commands_mem)
typedef struct
{
	zbx_uint64_t		maxid;
	int			commands_num;
	zbx_hashset_t		commands;
	zbx_hashset_t		results;
	zbx_hashset_t		strpool; ///<- not use strpool
	zbx_uint64_t		strpool_sz;

}
zbx_remote_commands_t;

zbx_remote_commands_t	*remote_commands = NULL;

typedef struct
{
	zbx_uint64_t	id;
	zbx_uint64_t	hostid;
	char		*command;
	unsigned char	flag;
	/////<--add result here

}
zbx_rc_command_t;

typedef struct
{
	zbx_uint64_t	id;
	char		*value;
	char		*error;
}
zbx_remote_commands_result_t;

static zbx_hash_t	remote_commands_commands_hash_func(const void *data)
{
	return ZBX_DEFAULT_UINT64_HASH_FUNC(&((const zbx_rc_command_t *)data)->id);
}

static int	remote_commands_commands_compare_func(const void *d1, const void *d2)
{
	const zbx_rc_command_t	*command1 = (const zbx_rc_command_t *)d1;
	const zbx_rc_command_t	*command2 = (const zbx_rc_command_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(command1->id, command2->id);

	return 0;
}

static zbx_hash_t	remote_commands_results_hash_func(const void *data)
{
	return ZBX_DEFAULT_UINT64_HASH_FUNC(&((const zbx_remote_commands_result_t *)data)->id);
}

static int	remote_commands_results_compare_func(const void *d1, const void *d2)
{
	const zbx_remote_commands_result_t	*result1 = (const zbx_remote_commands_result_t *)d1;
	const zbx_remote_commands_result_t	*result2 = (const zbx_remote_commands_result_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(result1->id, result2->id);

	return 0;
}

static zbx_hash_t	remote_commands_strpool_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((const char *)data);
}

static int	remote_commands_strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp((const char *)d1, (const char *)d2);
}
/******************************************************************************
 *                                                                            *
 * Purpose: initializes active remote commands cache                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_init_remote_commands_cache(char **error)
{
#define	REMOTE_COMMANS_INITIAL_HASH_SIZE	100
	int		ret = FAIL;
	zbx_uint64_t	size_reserved;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_mutex_create(&remote_commands_lock, ZBX_MUTEX_REMOTE_COMMANDS, error))
		goto out;

	size_reserved = zbx_shmem_required_size(1, "commands cache size", "CommandsCacheSize");

	remote_command_cache_size -= size_reserved;

	if (SUCCEED != zbx_shmem_create(&remote_commands_mem, remote_command_cache_size, "commands cache size",
			"CommandsCacheSize",1, error))
	{
		goto out;
	}

	remote_commands = (zbx_remote_commands_t *)__remote_commands_shmem_malloc_func(NULL,
			sizeof(zbx_remote_commands_t));
	memset(remote_commands, 0, sizeof(zbx_remote_commands_t));

	remote_commands->maxid = 0;
	remote_commands->commands_num = 0;

	zbx_hashset_create_ext(&remote_commands->commands, REMOTE_COMMANS_INITIAL_HASH_SIZE,
			remote_commands_commands_hash_func,remote_commands_commands_compare_func, NULL,
			__remote_commands_shmem_malloc_func, __remote_commands_shmem_realloc_func,
			__remote_commands_shmem_free_func);

	zbx_hashset_create_ext(&remote_commands->results, REMOTE_COMMANS_INITIAL_HASH_SIZE,
			remote_commands_results_hash_func, remote_commands_results_compare_func, NULL,
			__remote_commands_shmem_malloc_func, __remote_commands_shmem_realloc_func,
			__remote_commands_shmem_free_func);

	zbx_hashset_create_ext(&remote_commands->strpool, REMOTE_COMMANS_INITIAL_HASH_SIZE,
			remote_commands_strpool_hash_func, remote_commands_strpool_compare_func,NULL,
			__remote_commands_shmem_malloc_func, __remote_commands_shmem_realloc_func,
			__remote_commands_shmem_free_func);

	remote_commands->strpool_sz = 0;
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
#undef	REMOTE_COMMANS_INITIAL_HASH_SIZE
}

static char	*remote_commands_shared_strdup(const char *str)
{
	char		*strdup;
	zbx_uint64_t	len;

	len = strlen(str) + 1;
	strdup = zbx_hashset_insert(&remote_commands->strpool, str, len);

	if(NULL != strdup)
	{
		len += ZBX_HASHSET_ENTRY_OFFSET;
		remote_commands->strpool_sz += zbx_shmem_required_chunk_size(len);
	}

	return strdup;
}

static void	remote_commands_shared_strfree(char *str)
{
	zbx_uint64_t	len;

	len = strlen(str) + 1 + ZBX_HASHSET_ENTRY_OFFSET;
	zbx_hashset_remove_direct(&remote_commands->strpool, str);
	remote_commands->strpool_sz -= zbx_shmem_required_chunk_size(len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: de-initializes active remote commands cache                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_deinit_remote_commands_cache(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != remote_commands_mem)
	{
		zbx_hashset_destroy(&remote_commands->commands);

		zbx_hashset_destroy(&remote_commands->results);
		zbx_hashset_destroy(&remote_commands->strpool);

		zbx_shmem_destroy(remote_commands_mem);
		remote_commands_mem = NULL;
		zbx_mutex_destroy(&remote_commands_lock);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks remote_commands cache                                       *
 *                                                                            *
 ******************************************************************************/
static void	commands_lock(void)
{
	zbx_mutex_lock(remote_commands_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlocks remote_commands cache                                     *
 *                                                                            *
 ******************************************************************************/
static void	commands_unlock(void)
{
	zbx_mutex_unlock(remote_commands_lock);
}

static int	remote_commands_insert_result(zbx_uint64_t id, char *value, char *err_msg, char **error)
{
	int			ret = SUCCEED;
	zbx_rc_command_t	*command, command_loc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	commands_lock();
	command_loc.id = id;

	if (NULL != (command = (zbx_rc_command_t *)zbx_hashset_search(&remote_commands->commands,
			&command_loc)))
	{
		zbx_remote_commands_result_t	res_loc, *pres;

		res_loc.id = id;

		if (NULL == (pres = zbx_hashset_insert(&remote_commands->results, &res_loc, sizeof(res_loc))))
		{
			*error = zbx_strdup(*error, "cannot allocate memory for remote command result");
			command->flag |= REMOTE_COMMAND_RESULT_OOM;
			ret = FAIL;
		}
		else
		{
			pres->value = NULL == value ? NULL : remote_commands_shared_strdup(value);
			pres->error = NULL == err_msg ? NULL : remote_commands_shared_strdup(err_msg);
		}
	}

	commands_unlock();
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), ret %d", __func__, ret);

	return ret;
}

int	zbx_process_agent_commands(struct zbx_json_parse *jp, char **info)
{
	int			ret = FAIL, values_num = 0, parsed_num = 0, results_num = 0;
	const char		*pnext = NULL;
	struct zbx_json_parse	jp_commands, jp_command;
	char 			*str = NULL, *value = NULL, *error = NULL;
	size_t 			str_alloc = 0;
	zbx_uint64_t		id;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_COMMANDS, &jp_commands)))
	{
		ret = SUCCEED;
		goto out;
	}

	if (NULL == pnext)
	{
		if (NULL == (pnext = zbx_json_next(&jp_commands, pnext)))
		{
			ret = SUCCEED;
			goto out;
		}
	}

	do
	{
		if (FAIL == zbx_json_brackets_open(pnext, &jp_command))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s", zbx_json_strerror());
			goto out;
		}

		parsed_num++;
		zbx_free(str);

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_command, ZBX_PROTO_TAG_ID, &str, &str_alloc, NULL))
			continue;

		if (SUCCEED != zbx_is_uint64(str, &id))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Wrong command id '%s'", str);
			goto out;
		}

		str_alloc = 0;
		zbx_free(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_command, ZBX_PROTO_TAG_VALUE, &value, &str_alloc, NULL))
		{
			zbx_free(error);

			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_command, ZBX_PROTO_TAG_ERROR, &error, &str_alloc,
					NULL))
			{
				continue;
			}
		}

		values_num++;
		if (SUCCEED == remote_commands_insert_result(id, value, error, info))
			results_num++;
	}
	while (NULL != (pnext = zbx_json_next(&jp_command, pnext)));

	ret = SUCCEED;
out:
	zbx_free(str);
	zbx_free(value);
	zbx_free(error);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), ret %d parsed %d values received %d results inserted %d", __func__,
			ret, parsed_num, values_num, results_num);

	return ret;
}

void	zbx_remote_commans_prepare_to_send(struct zbx_json *json, zbx_uint64_t hostid)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != remote_commands_mem)
	{
		zbx_hashset_iter_t	iter_comands;
		zbx_rc_command_t	*command;

		commands_lock();
		zbx_hashset_iter_reset(&remote_commands->commands, &iter_comands);

		while (NULL != (command = (zbx_rc_command_t *)zbx_hashset_iter_next(&iter_comands)))
		{
			if (0 == (command->flag & REMOTE_COMMAND_SENT) && hostid == command->hostid)
			{
				int	wait = 0;

				zbx_json_addobject(json, NULL);
				zbx_json_addstring(json, ZBX_PROTO_TAG_COMMAND, command->command,
						ZBX_JSON_TYPE_STRING);
				zbx_json_adduint64(json, ZBX_PROTO_TAG_ID, command->id);

				if (0 != (command->flag & REMOTE_COMMAND_RESULT_WAIT))
					wait = 1;

				zbx_json_adduint64(json, ZBX_PROTO_TAG_WAIT, (zbx_uint64_t)wait);
				zbx_json_close(json);

				command->flag |= REMOTE_COMMAND_SENT;
				remote_commands->commands_num--;
			}
		}

		commands_unlock();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

int	zbx_get_unsent_commands_num(void)
{
	int	num = 0;

	if (NULL != remote_commands_mem)
	{
		commands_lock();
		num = remote_commands->commands_num;
		commands_unlock();
	}

	return num;
}

static int active_command_send_and_result_fetch(const zbx_dc_host_t *host, const char *command, char **result,
		int config_timeout, char *error, size_t max_error_len)
{
	int				ret = FAIL, remote_oom = 0;
	zbx_rc_command_t		cmd, *pcmd;
	zbx_remote_commands_result_t	*res = NULL, res_loc;
	time_t				time_start;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > CONFIG_FORKS[ZBX_PROCESS_TYPE_TRAPPER] && NULL != result)
	{
		zbx_snprintf(error, max_error_len, "cannot execute remote command on active agent, increase number"
				" of trappers up to 2");
		goto out;
	}

	*error = '\0';
	commands_lock();

	cmd.id = remote_commands->maxid++;
	res_loc.id = cmd.id;

	if (NULL == (pcmd = zbx_hashset_insert(&remote_commands->commands, &cmd, sizeof(cmd))))
	{
		commands_unlock();
		zbx_snprintf(error, max_error_len, "cannot allocate memory for remote command");
		goto out;
	}

	pcmd->flag = 0;
	pcmd->hostid = host->hostid;
	pcmd->command = remote_commands_shared_strdup(command);

	if (NULL != result)
		pcmd->flag |= REMOTE_COMMAND_RESULT_WAIT;

	if (NULL == pcmd->command)
	{
		zbx_hashset_remove(&remote_commands->commands, &cmd);
		zbx_snprintf(error, max_error_len, "cannot allocate memory for remote command");
		commands_unlock();
		goto out;
	}

	remote_commands->commands_num++;
	commands_unlock();

	for (time_start = time(NULL); config_timeout > time(NULL) - time_start; sleep(1))
	{
		commands_lock();

		if  (0 != (REMOTE_COMMAND_RESULT_OOM & pcmd->flag))
		{
			remote_oom = 1;
			commands_unlock();
			break;
		}

		if (NULL != (res = (zbx_remote_commands_result_t *)zbx_hashset_search(&remote_commands->results,
				&res_loc)))
		{
			if (NULL != res->value)
			{
				if (NULL != result)
					*result = zbx_strdup(*result, res->value);

				remote_commands_shared_strfree(res->value);
				ret = SUCCEED;
			}
			else if (NULL != res->error)
			{
				zbx_strlcpy(error, res->error, max_error_len);
				remote_commands_shared_strfree(res->error);
			}

			zbx_hashset_remove(&remote_commands->results, &res_loc);

			commands_unlock();
			break;
		}

		commands_unlock();
	}

	if (0 != remote_oom)
		zbx_snprintf(error, max_error_len, "cannot allocate memory for remote command result");
	else if (NULL == res)
		zbx_snprintf(error, max_error_len, "timeout while retrieving result for remote command");

	commands_lock();
	remote_commands_shared_strfree(pcmd->command);
	zbx_hashset_remove(&remote_commands->commands, &cmd);
	commands_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	passive_command_send_and_result_fetch(const zbx_dc_host_t *host, const char *command, char **result,
		int config_timeout, char *error, size_t max_error_len)
{
	int		ret;
	AGENT_RESULT	agent_result;
	char		*param = NULL, *port = NULL;
	zbx_dc_item_t	item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*error = '\0';
	memset(&item, 0, sizeof(item));
	memcpy(&item.host, host, sizeof(item.host));

	if (SUCCEED != (ret = zbx_dc_config_get_interface_by_type(&item.interface, host->hostid, INTERFACE_TYPE_AGENT)))
	{
		zbx_snprintf(error, max_error_len, "Zabbix agent interface is not defined for host [%s]", host->host);
		goto fail;
	}

	port = zbx_strdup(port, item.interface.port_orig);
	zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			&port, MACRO_TYPE_COMMON, NULL, 0);

	if (SUCCEED != (ret = zbx_is_ushort(port, &item.interface.port)))
	{
		zbx_snprintf(error, max_error_len, "Invalid port number [%s]", item.interface.port_orig);
		goto fail;
	}

	param = zbx_strdup(param, command);
	if (SUCCEED != (ret = zbx_quote_key_param(&param, 0)))
	{
		zbx_snprintf(error, max_error_len, "Invalid param [%s]", param);
		goto fail;
	}

	item.key = zbx_dsprintf(item.key, "system.run[%s%s]", param, NULL == result ? ",nowait" : "");
	item.value_type = ITEM_VALUE_TYPE_TEXT;

	zbx_init_agent_result(&agent_result);

	if (SUCCEED != (ret = get_value_agent(&item, config_timeout, &agent_result)))
	{
		if (ZBX_ISSET_MSG(&agent_result))
			zbx_strlcpy(error, agent_result.msg, max_error_len);
		ret = FAIL;
	}
	else if (NULL != result && ZBX_ISSET_TEXT(&agent_result))
		*result = zbx_strdup(*result, agent_result.text);

	zbx_free_agent_result(&agent_result);

	zbx_free(item.key);
fail:
	zbx_free(port);
	zbx_free(param);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	zbx_execute_script_on_agent(const zbx_dc_host_t *host, const char *command, char **result,
		int config_timeout, char *error, size_t max_error_len)
{
	int				i;
	zbx_agent_availability_t	agents[ZBX_AGENT_MAX];

	zbx_get_host_interfaces_availability(host->hostid, agents);

	for (i = 0; i < ZBX_AGENT_MAX; i++)
		zbx_free(agents[i].error);

	if (ZBX_INTERFACE_AVAILABLE_TRUE != agents[ZBX_AGENT_ZABBIX].available &&
			ZBX_INTERFACE_AVAILABLE_TRUE == zbx_get_active_agent_availability(host->hostid))
	{
		return active_command_send_and_result_fetch(host, command, result, config_timeout, error,
				max_error_len);
	}

	return passive_command_send_and_result_fetch(host, command, result, config_timeout, error, max_error_len);
}

static int	zbx_execute_script_on_terminal(const zbx_dc_host_t *host, const zbx_script_t *script, char **result,
		int config_timeout, char *error, size_t max_error_len)
{
	int		ret = FAIL, i;
	AGENT_RESULT	agent_result;
	zbx_dc_item_t	item;
	int             (*function)(zbx_dc_item_t *, int timeout, AGENT_RESULT *);

#if defined(HAVE_SSH2) || defined(HAVE_SSH)
	assert(ZBX_SCRIPT_TYPE_SSH == script->type || ZBX_SCRIPT_TYPE_TELNET == script->type);
#else
	assert(ZBX_SCRIPT_TYPE_TELNET == script->type);
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*error = '\0';
	memset(&item, 0, sizeof(item));
	memcpy(&item.host, host, sizeof(item.host));

	for (i = 0; INTERFACE_TYPE_COUNT > i; i++)
	{
		if (SUCCEED == (ret = zbx_dc_config_get_interface_by_type(&item.interface, host->hostid,
				INTERFACE_TYPE_PRIORITY[i])))
		{
			break;
		}
	}

	if (FAIL == ret)
	{
		zbx_snprintf(error, max_error_len, "No interface defined for host [%s]", host->host);
		goto fail;
	}

	switch (script->type)
	{
		case ZBX_SCRIPT_TYPE_SSH:
			item.authtype = script->authtype;
			item.publickey = script->publickey;
			item.privatekey = script->privatekey;
			ZBX_FALLTHROUGH;
		case ZBX_SCRIPT_TYPE_TELNET:
			item.username = script->username;
			item.password = script->password;
			break;
	}

#if defined(HAVE_SSH2) || defined(HAVE_SSH)
	if (ZBX_SCRIPT_TYPE_SSH == script->type)
	{
		item.key = zbx_dsprintf(item.key, "ssh.run[,,%s]", script->port);
		function = get_value_ssh;
	}
	else
	{
#endif
		item.key = zbx_dsprintf(item.key, "telnet.run[,,%s]", script->port);
		function = get_value_telnet;
#if defined(HAVE_SSH2) || defined(HAVE_SSH)
	}
#endif
	item.value_type = ITEM_VALUE_TYPE_TEXT;
	item.params = zbx_strdup(item.params, script->command);

	zbx_init_agent_result(&agent_result);

	if (SUCCEED != (ret = function(&item, config_timeout, &agent_result)))
	{
		if (ZBX_ISSET_MSG(&agent_result))
			zbx_strlcpy(error, agent_result.msg, max_error_len);
		ret = FAIL;
	}
	else if (NULL != result && ZBX_ISSET_TEXT(&agent_result))
		*result = zbx_strdup(*result, agent_result.text);

	zbx_free_agent_result(&agent_result);

	zbx_free(item.params);
	zbx_free(item.key);
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	zbx_check_script_permissions(zbx_uint64_t groupid, zbx_uint64_t hostid)
{
	zbx_db_result_t		result;
	int			ret = SUCCEED;
	zbx_vector_uint64_t	groupids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groupid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64, __func__, groupid, hostid);

	if (0 == groupid)
		goto exit;

	zbx_vector_uint64_create(&groupids);
	zbx_dc_get_nested_hostgroupids(&groupid, 1, &groupids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hostid"
			" from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids.values,
			groupids.values_num);

	result = zbx_db_select("%s", sql);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&groupids);

	if (NULL == zbx_db_fetch(result))
		ret = FAIL;

	zbx_db_free_result(result);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	zbx_check_script_user_permissions(zbx_uint64_t userid, zbx_uint64_t hostid, zbx_script_t *script)
{
	int		ret = SUCCEED;
	zbx_db_result_t	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " scriptid:" ZBX_FS_UI64,
			__func__, userid, hostid, script->scriptid);

	result = zbx_db_select(
		"select null"
			" from hosts_groups hg,rights r,users_groups ug"
		" where hg.groupid=r.id"
			" and r.groupid=ug.usrgrpid"
			" and hg.hostid=" ZBX_FS_UI64
			" and ug.userid=" ZBX_FS_UI64
		" group by hg.hostid"
		" having min(r.permission)>%d"
			" and max(r.permission)>=%d",
		hostid,
		userid,
		PERM_DENY,
		script->host_access);

	if (NULL == zbx_db_fetch(result))
		ret = FAIL;

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

void	zbx_script_init(zbx_script_t *script)
{
	memset(script, 0, sizeof(zbx_script_t));
}

void	zbx_script_clean(zbx_script_t *script)
{
	zbx_free(script->port);
	zbx_free(script->username);
	zbx_free(script->publickey);
	zbx_free(script->privatekey);
	zbx_free(script->password);
	zbx_free(script->command);
	zbx_free(script->command_orig);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack webhook script parameters into JSON                          *
 *                                                                            *
 * Parameters: params      - [IN] vector of pairs of pointers to parameter    *
 *                                names and values                            *
 *             params_json - [OUT] JSON string                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_webhook_params_pack_json(const zbx_vector_ptr_pair_t *params, char **params_json)
{
	struct zbx_json	json_data;
	int		i;

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < params->values_num; i++)
	{
		zbx_ptr_pair_t	pair = params->values[i];

		zbx_json_addstring(&json_data, pair.first, pair.second, ZBX_JSON_TYPE_STRING);
	}

	zbx_json_close(&json_data);
	*params_json = zbx_strdup(*params_json, json_data.buffer);
	zbx_json_free(&json_data);
}

/***********************************************************************************
 *                                                                                 *
 * Purpose: prepares user script                                                   *
 *                                                                                 *
 * Parameters: script        - [IN] the script to prepare                          *
 *             host          - [IN] the host the script will be executed on        *
 *             error         - [OUT] the error message buffer                      *
 *             max_error_len - [IN] the size of error message output buffer        *
 *                                                                                 *
 * Return value:  SUCCEED - the script has been prepared successfully              *
 *                FAIL    - otherwise, error contains error message                *
 *                                                                                 *
 * Comments: This function prepares script for execution by loading global         *
 *           script/expanding macros (except in script body).                      *
 *           Prepared scripts must be always freed with zbx_script_clean()         *
 *           function.                                                             *
 *                                                                                 *
 ***********************************************************************************/
int	zbx_script_prepare(zbx_script_t *script, const zbx_uint64_t *hostid, char *error, size_t max_error_len)
{
	int			ret = FAIL;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	um_handle = zbx_dc_open_user_macros();

	switch (script->type)
	{
		case ZBX_SCRIPT_TYPE_SSH:
			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, hostid, NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, &script->publickey, MACRO_TYPE_COMMON, NULL, 0);
			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, hostid, NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, &script->privatekey, MACRO_TYPE_COMMON, NULL, 0);
			ZBX_FALLTHROUGH;
		case ZBX_SCRIPT_TYPE_TELNET:
			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, hostid, NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, &script->port, MACRO_TYPE_COMMON, NULL, 0);

			if ('\0' != *script->port && SUCCEED != (ret = zbx_is_ushort(script->port, NULL)))
			{
				zbx_snprintf(error, max_error_len, "Invalid port number \"%s\"", script->port);
				goto out;
			}

			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, hostid, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, &script->username, MACRO_TYPE_COMMON, NULL, 0);
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, hostid, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, &script->password, MACRO_TYPE_COMMON, NULL, 0);
			break;
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
			zbx_dos2unix(script->command);	/* CR+LF (Windows) => LF (Unix) */
			break;
		case ZBX_SCRIPT_TYPE_WEBHOOK:
		case ZBX_SCRIPT_TYPE_IPMI:
			break;
		default:
			zbx_snprintf(error, max_error_len, "Invalid command type \"%d\".", (int)script->type);
			goto out;
	}

	zbx_dc_close_user_macros(um_handle);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch webhook parameters                                          *
 *                                                                            *
 * Parameters:  scriptid  - [IN] the id of script to be executed              *
 *              params    - [OUT] parameters name-value pairs                 *
 *              error     - [IN/OUT] the error message                        *
 *              error_len - [IN] the maximum error length                     *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	DBfetch_webhook_params(zbx_uint64_t scriptid, zbx_vector_ptr_pair_t *params, char *error, size_t error_len)
{
	int		ret = SUCCEED;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_ptr_pair_t	pair;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() scriptid:" ZBX_FS_UI64, __func__, scriptid);

	result = zbx_db_select("select name,value from script_param where scriptid=" ZBX_FS_UI64, scriptid);

	if (NULL == result)
	{
		zbx_strlcpy(error, "Database error, cannot get webhook script parameters.", error_len);
		ret = FAIL;
		goto out;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		pair.first = zbx_strdup(NULL, row[0]);
		pair.second = zbx_strdup(NULL, row[1]);
		zbx_vector_ptr_pair_append(params, pair);
	}

	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: executing user scripts or remote commands                         *
 *                                                                            *
 * Parameters:  script         - [IN] the script to be executed               *
 *              host           - [IN] the host the script will be executed on *
 *              params         - [IN] parameters for the script               *
 *              config_timeout - [IN]                                         *
 *              result         - [OUT] the result of a script execution       *
 *              error          - [OUT] the error reported by the script       *
 *              max_error_len  - [IN] the maximum error length                *
 *              debug          - [OUT] the debug data (optional)              *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                TIMEOUT_ERROR - a timeout occurred                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_script_execute(const zbx_script_t *script, const zbx_dc_host_t *host, const char *params, int config_timeout,
		char **result, char *error, size_t max_error_len, char **debug)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*error = '\0';

	switch (script->type)
	{
		case ZBX_SCRIPT_TYPE_WEBHOOK:
			ret = zbx_es_execute_command(script->command, params, script->timeout, result, error,
					max_error_len, debug);
			break;
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
			switch (script->execute_on)
			{
				case ZBX_SCRIPT_EXECUTE_ON_AGENT:
					ret = zbx_execute_script_on_agent(host, script->command, result, config_timeout,
							error, max_error_len);
					break;
				case ZBX_SCRIPT_EXECUTE_ON_SERVER:
				case ZBX_SCRIPT_EXECUTE_ON_PROXY:
					if (SUCCEED != (ret = zbx_execute(script->command, result, error, max_error_len,
							CONFIG_TRAPPER_TIMEOUT, ZBX_EXIT_CODE_CHECKS_ENABLED, NULL)))
					{
						ret = FAIL;
					}
					break;
				default:
					zbx_snprintf(error, max_error_len, "Invalid 'Execute on' option \"%d\".",
							(int)script->execute_on);
			}
			break;
		case ZBX_SCRIPT_TYPE_IPMI:
#ifdef HAVE_OPENIPMI
			if (0 == CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIPOLLER])
			{
				zbx_strlcpy(error, "Cannot perform IPMI request: configuration parameter"
						" \"StartIPMIPollers\" is 0.", max_error_len);
				break;
			}

			if (SUCCEED == (ret = zbx_ipmi_execute_command(host, script->command, error, max_error_len)))
			{
				if (NULL != result)
					*result = zbx_strdup(*result, "IPMI command successfully executed.");
			}
#else
			zbx_strlcpy(error, "Support for IPMI commands was not compiled in.", max_error_len);
#endif
			break;
		case ZBX_SCRIPT_TYPE_SSH:
#if !defined(HAVE_SSH2) && !defined(HAVE_SSH)
			zbx_strlcpy(error, "Support for SSH script was not compiled in.", max_error_len);
			break;
#endif
		case ZBX_SCRIPT_TYPE_TELNET:
			ret = zbx_execute_script_on_terminal(host, script, result, config_timeout, error,
					max_error_len);
			break;
		default:
			zbx_snprintf(error, max_error_len, "Invalid command type \"%d\".", (int)script->type);
	}

	if (SUCCEED != ret && NULL != result)
		*result = zbx_strdup(*result, "");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates remote command task from a script                         *
 *                                                                            *
 * Return value:  the identifier of the created task or 0 in the case of      *
 *                error                                                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_script_create_task(const zbx_script_t *script, const zbx_dc_host_t *host, zbx_uint64_t alertid,
		int now)
{
	zbx_tm_task_t	*task;
	unsigned short	port;
	zbx_uint64_t	taskid;

	if (NULL != script->port && '\0' != script->port[0])
		zbx_is_ushort(script->port, &port);
	else
		port = 0;

	zbx_db_begin();

	taskid = zbx_db_get_maxid("task");

	task = zbx_tm_task_create(taskid, ZBX_TM_TASK_REMOTE_COMMAND, ZBX_TM_STATUS_NEW, now,
			ZBX_REMOTE_COMMAND_TTL, host->proxy_hostid);

	task->data = zbx_tm_remote_command_create(script->type, script->command, script->execute_on, port,
			script->authtype, script->username, script->password, script->publickey, script->privatekey,
			taskid, host->hostid, alertid);

	if (FAIL == zbx_tm_save_task(task))
		taskid = 0;

	zbx_db_commit();

	zbx_tm_task_free(task);

	return taskid;
}
