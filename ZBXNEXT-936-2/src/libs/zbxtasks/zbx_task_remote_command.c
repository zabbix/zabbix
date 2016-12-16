/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include <assert.h>

#include "common.h"
#include "log.h"

#include "db.h"
#include "zbxjson.h"
#include "zbxtasks.h"

#include "zbx_task.h"

#define INT_UNSPECIFIED		0
#define UINT64_UNSPECIFIED	0
#define STRING_UNSPECIFIED	""
#define MAX_PORT_NUMBER		65535

/******************/
/*                */
/* remote command */
/*                */
/******************/
struct zbx_task_remote_command {
	struct zbx_task	task;
	int		commandtype;
	char		*command;
	int		execute_on;
	int		port;
	int		authtype;
	char		*username;
	char		*password;
	char		*publickey;
	char		*privatekey;
	zbx_uint64_t	parent_taskid;
	zbx_uint64_t	hostid;
	zbx_uint64_t	alertid;
};

struct zbx_task_remote_command	*zbx_task_remote_command_new(void)
{
	struct zbx_task_remote_command	*cmd = NULL;

	cmd = zbx_calloc(cmd, 1, sizeof(struct zbx_task_remote_command));

	return cmd;
}

void	zbx_task_remote_command_free(struct zbx_task_remote_command *cmd)
{
	assert(NULL != cmd);

	/* call zbx_task_remote_command_clear() before freeing the cmd */
	assert(NULL == cmd->command);
	assert(NULL == cmd->username);
	assert(NULL == cmd->password);
	assert(NULL == cmd->publickey);
	assert(NULL == cmd->privatekey);

	zbx_free(cmd);
}

static int	zbx_task_remote_command_validate(const struct zbx_task_remote_command *cmd)
{
	int	ret = FAIL;

	assert(NULL != cmd);
	assert(NULL != cmd->command); /* cmd is initialized */
	assert(NULL != cmd->username);
	assert(NULL != cmd->password);
	assert(NULL != cmd->publickey);
	assert(NULL != cmd->privatekey);

	if (ZBX_TM_TASK_SEND_REMOTE_COMMAND != cmd->task.type)
		goto err;

	if (0 >= cmd->task.clock)
		goto err;

	if (0 > cmd->task.ttl)
		goto err;

	switch (cmd->commandtype) {
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
		case ZBX_SCRIPT_TYPE_IPMI:
		case ZBX_SCRIPT_TYPE_SSH:
		case ZBX_SCRIPT_TYPE_TELNET:
		case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
			break;
		default:
			goto err;
	}

	if (0 == strcmp(cmd->command, STRING_UNSPECIFIED)) /* command must be specified */
		goto err;

	if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT == cmd->commandtype || ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT == cmd->commandtype)
	{
		switch (cmd->execute_on) {
			case ZBX_SCRIPT_EXECUTE_ON_AGENT:
			case ZBX_SCRIPT_EXECUTE_ON_SERVER:
				break;
			default:
				goto err;
		}
	}
	else if (ZBX_SCRIPT_TYPE_SSH == cmd->commandtype)
	{
		switch (cmd->authtype) {
			case ITEM_AUTHTYPE_PASSWORD:
			case ITEM_AUTHTYPE_PUBLICKEY:
				break;
			default:
				goto err;
		}

		if (cmd->port > MAX_PORT_NUMBER || cmd->port <= 0) /* port 0 is reserved */
			goto err;

		if (0 == strcmp(cmd->username, STRING_UNSPECIFIED))
			goto err;

		else if (ITEM_AUTHTYPE_PUBLICKEY == cmd->authtype)
		{
			if (0 == strcmp(cmd->publickey, STRING_UNSPECIFIED))
				goto err;

			if (0 == strcmp(cmd->privatekey, STRING_UNSPECIFIED))
				goto err;
		}
	}
	else if (ZBX_SCRIPT_TYPE_TELNET == cmd->commandtype)
	{
		if (cmd->port > MAX_PORT_NUMBER || cmd->port <= 0) /* port 0 is reserved */
			goto err;

		if (0 == strcmp(cmd->username, STRING_UNSPECIFIED))
			goto err;
	}

	ret = SUCCEED;

err:
	return ret;
}

int	zbx_task_remote_command_init(struct zbx_task_remote_command *cmd,
		zbx_uint64_t	taskid,
		int		type,
		int		status,
		int		clock,
		int		ttl,
		int		commandtype,
		const char	*command,
		int		execute_on,
		int		port,
		int		authtype,
		const char	*username,
		const char	*password,
		const char	*publickey,
		const char	*privatekey,
		zbx_uint64_t	parent_taskid,
		zbx_uint64_t	hostid,
		zbx_uint64_t	alertid)
{
	int	ret = FAIL;

	assert(NULL != cmd);
	assert(NULL == cmd->command); /* the cmd is cleared - detect memory leak */
	assert(NULL == cmd->username);
	assert(NULL == cmd->password);
	assert(NULL == cmd->publickey);
	assert(NULL == cmd->privatekey);

	assert(NULL != command);
	assert(NULL != username);
	assert(NULL != password);
	assert(NULL != publickey);
	assert(NULL != privatekey);

	cmd->task.taskid = taskid,
	cmd->task.type = type,
	cmd->task.status = status;
	cmd->task.clock = clock;
	cmd->task.ttl = ttl;
	cmd->commandtype = commandtype;
	cmd->command = zbx_strdup(cmd->command, command);
	cmd->execute_on = execute_on;
	cmd->port = port;
	cmd->authtype = authtype;
	cmd->username = zbx_strdup(cmd->username, username);
	cmd->password = zbx_strdup(cmd->password, password);
	cmd->publickey = zbx_strdup(cmd->publickey, publickey);
	cmd->privatekey = zbx_strdup(cmd->privatekey, privatekey);
	cmd->parent_taskid = parent_taskid;
	cmd->hostid = hostid;
	cmd->alertid = alertid;

	ret = zbx_task_remote_command_validate(cmd);

	return ret;
}

int     zbx_task_remote_command_init_from_json(struct zbx_task_remote_command *cmd,
		zbx_uint64_t	taskid,
		const char	*opening_brace)
{
	int			ret = FAIL;
	struct zbx_json_parse	jp_row;

	char			*type = NULL;
	char			*clock = NULL;
	char			*ttl = NULL;
	char			*commandtype = NULL;
	char			*command = NULL;
	char			*execute_on = NULL;
	char			*port = NULL;
	char			*authtype = NULL;
	char			*username = NULL;
	char			*password = NULL;
	char			*publickey = NULL;
	char			*privatekey = NULL;
	char			*parent_taskid = NULL;
	char			*hostid = NULL;
	size_t			tmp_alloc = 0;

	zbx_uint64_t		parent_taskid_num;
	zbx_uint64_t		hostid_num;

	assert(NULL != cmd);
	assert(NULL == cmd->command); /* cmd must be cleared - to avoid memory leaks */
	assert(NULL == cmd->username);
	assert(NULL == cmd->password);
	assert(NULL == cmd->publickey);
	assert(NULL == cmd->privatekey);

	if (FAIL == (ret = zbx_json_brackets_open(opening_brace, &jp_row)))
		goto err;

	/* get values as strings from JSON object */
	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_TYPE, &type, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_CLOCK, &clock, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_TTL, &ttl, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_COMMANDTYPE, &commandtype, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_COMMAND, &command, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_EXECUTE_ON, &execute_on, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_PORT, &port, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_AUTHTYPE, &authtype, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_USERNAME, &username, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_PASSWORD, &password, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_PUBLICKEY, &publickey, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_PRIVATEKEY, &privatekey, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_PARENT_TASKID, &parent_taskid, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_HOSTID, &hostid, &tmp_alloc))
		goto err;

	/* initialize object */
	ZBX_STR2UINT64(parent_taskid_num, parent_taskid);
	ZBX_STR2UINT64(hostid_num, hostid);

	ret = zbx_task_remote_command_init(cmd,
		/* t.taskid */		taskid,
		/* t.type */		ZBX_TM_TASK_SEND_REMOTE_COMMAND,
		/* t.status */		ZBX_TM_STATUS_NEW,
		/* t.clock */		atoi(clock),
		/* t.ttl */		atoi(ttl),
		/* c.command_type */	atoi(commandtype),
		/* c,command */		command,
		/* c.execute_on */	atoi(execute_on),
		/* c.port */		atoi(port),
		/* c.authtype */	atoi(authtype),
		/* c.username */	username,
		/* c.password */	password,
		/* c.publickey */	publickey,
		/* c.privatekey */	privatekey,
		/* c.parent_taskid */	parent_taskid_num,
		/* c.hostid */		hostid_num,
		/* c.alertid */		0);

err:
	zbx_free(type);
	zbx_free(clock);
	zbx_free(ttl);
	zbx_free(commandtype);
	zbx_free(command);
	zbx_free(execute_on);
	zbx_free(port);
	zbx_free(authtype);
	zbx_free(username);
	zbx_free(password);
	zbx_free(publickey);
	zbx_free(privatekey);
	zbx_free(parent_taskid);
	zbx_free(hostid);

	return ret;
}

void	zbx_task_remote_command_clear(struct zbx_task_remote_command *cmd)
{
	assert(NULL != cmd);
	/* not asserting that members are not NULL since partially initialized cmd can be cleared as well */

	zbx_free(cmd->command);
	zbx_free(cmd->username);
	zbx_free(cmd->password);
	zbx_free(cmd->publickey);
	zbx_free(cmd->privatekey);

	memset(cmd, 0, sizeof(struct zbx_task_remote_command));
}

/* class method */
void	zbx_task_remote_command_db_insert_prepare(zbx_db_insert_t *db_task_insert,
		zbx_db_insert_t *db_task_remote_command_insert)
{
	assert(NULL != db_task_insert);
	assert(NULL != db_task_remote_command_insert);

	zbx_db_insert_prepare(db_task_insert, "task",
		"taskid",
		"type",
		"status",
		"clock",
		"ttl",
		NULL);

	zbx_db_insert_prepare(db_task_remote_command_insert, "task_remote_command",
		"taskid",
		"command_type",
		"execute_on",
		"port",
		"authtype",
		"username",
		"password",
		"publickey",
		"privatekey",
		"command",
		"alertid",
		"parent_taskid",
		"hostid",
		NULL);
}

void	zbx_task_remote_command_db_insert_add_values(const struct zbx_task_remote_command *cmd,
		zbx_db_insert_t *db_task_insert,
		zbx_db_insert_t *db_task_remote_command_insert)
{
	assert(NULL != cmd);
	assert(NULL != cmd->command); /* cmd is initialized */
	assert(NULL != cmd->username);
	assert(NULL != cmd->password);
	assert(NULL != cmd->publickey);
	assert(NULL != cmd->privatekey);

	assert(NULL != db_task_insert);
	assert(NULL != db_task_remote_command_insert);

	zbx_db_insert_add_values(db_task_insert,
		cmd->task.taskid,
		cmd->task.type,
		cmd->task.status,
		cmd->task.clock,
		cmd->task.ttl);

	zbx_db_insert_add_values(db_task_remote_command_insert,
		cmd->task.taskid,
		cmd->commandtype,
		cmd->execute_on,
		cmd->port,
		cmd->authtype,
		cmd->username,
		cmd->password,
		cmd->publickey,
		cmd->privatekey,
		cmd->command,
		cmd->alertid,
		cmd->parent_taskid,
		cmd->hostid);
}

void	zbx_task_remote_command_serialize_json(const struct zbx_task_remote_command *cmd, struct zbx_json *json)
{
	assert(NULL != cmd);
	assert(NULL != json);

	assert(NULL != cmd->command);
	assert(NULL != cmd->username);
	assert(NULL != cmd->password);
	assert(NULL != cmd->publickey);
	assert(NULL != cmd->privatekey);

	zbx_json_addobject(json, NULL);

	zbx_json_addint64(json, ZBX_PROTO_TAG_TYPE, cmd->task.type);
	zbx_json_addint64(json, ZBX_PROTO_TAG_CLOCK, cmd->task.clock);
	zbx_json_addint64(json, ZBX_PROTO_TAG_TTL, cmd->task.ttl);
	zbx_json_addint64(json, ZBX_PROTO_TAG_COMMANDTYPE, cmd->commandtype);
	zbx_json_addstring(json, ZBX_PROTO_TAG_COMMAND, cmd->command, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(json, ZBX_PROTO_TAG_EXECUTE_ON, cmd->execute_on);
	zbx_json_addint64(json, ZBX_PROTO_TAG_PORT, cmd->port);
	zbx_json_addint64(json, ZBX_PROTO_TAG_AUTHTYPE, cmd->authtype);
	zbx_json_addstring(json, ZBX_PROTO_TAG_USERNAME, cmd->username, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, ZBX_PROTO_TAG_PASSWORD, cmd->password, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, ZBX_PROTO_TAG_PUBLICKEY, cmd->publickey, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, ZBX_PROTO_TAG_PRIVATEKEY, cmd->privatekey, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json, ZBX_PROTO_TAG_PARENT_TASKID, cmd->parent_taskid);
	zbx_json_adduint64(json, ZBX_PROTO_TAG_HOSTID, cmd->hostid);

	zbx_json_close(json);
}

static void	zbx_task_remote_command_process_new_task(struct zbx_task_remote_command *cmd)
{
	zbx_uint64_t				taskid;
	struct zbx_task_remote_command_result	*res;
	zbx_db_insert_t				db_task_insert;
	zbx_db_insert_t				db_task_remote_command_insert;
	int					status = SUCCEED;
	char					*error = "";
	int					ret = FAIL;

	assert(NULL != cmd);
	assert(NULL != cmd->command); /* cmd is initialized */
	assert(NULL != cmd->username);
	assert(NULL != cmd->password);
	assert(NULL != cmd->publickey);
	assert(NULL != cmd->privatekey);

	/* TODO: not finished yet */

	/* task in progress */
	DBexecute("update tasks set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_INPROGRESS, cmd->task.taskid);

	/* execute command */
	zabbix_log(LOG_LEVEL_ERR, "processing command  task, taskid: " ZBX_FS_UI64, cmd->task.taskid);

	/* insert result task */
	taskid = DBget_maxid("task");

	res = zbx_task_remote_command_result_new();

	ret = zbx_task_remote_command_result_init(res,
		/* t.taskid */		taskid,
		/* t.type */		ZBX_TM_TASK_SEND_REMOTE_COMMAND_RESULT,
		/* t.status */		ZBX_TM_STATUS_NEW,
		/* t.clock */		time(NULL),
		/* t.ttl */		0,
		/* r.status */		status,
		/* r.error */		error,
		/* r.parent_taskid */	cmd->parent_taskid);

	if (SUCCEED != ret)
		goto err;

	zbx_task_remote_command_result_db_insert_prepare(&db_task_insert,
		&db_task_remote_command_insert);
	zbx_task_remote_command_result_db_insert_add_values(res,
		&db_task_insert, &db_task_remote_command_insert);

	DBbegin();
	zbx_db_insert_execute(&db_task_insert);
	zbx_db_insert_execute(&db_task_remote_command_insert);
	DBcommit();

	/* task is done */
	DBexecute("update tasks set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_DONE, cmd->task.taskid);

err:
	zbx_task_remote_command_result_clear(res);
	zbx_task_remote_command_result_free(res);
}

void	zbx_task_remote_command_process_task(struct zbx_task_remote_command *cmd)
{
	/* TODO: not finished yet */

	switch (cmd->task.status) {
		case ZBX_TM_STATUS_NEW:
			zbx_task_remote_command_process_new_task(cmd);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}
}

void	zbx_task_remote_command_log(const struct zbx_task_remote_command *cmd)
{
	assert(NULL != cmd);
	assert(NULL != cmd->command); /* cmd is initialized */
	assert(NULL != cmd->username);
	assert(NULL != cmd->password);
	assert(NULL != cmd->publickey);
	assert(NULL != cmd->privatekey);

	zabbix_log(LOG_LEVEL_ERR, "cmd->task.taskid: " ZBX_FS_UI64, cmd->task.taskid);
	zabbix_log(LOG_LEVEL_ERR, "cmd->task.type: %d", cmd->task.type);
	zabbix_log(LOG_LEVEL_ERR, "cmd->task.status: %d", cmd->task.status);
	zabbix_log(LOG_LEVEL_ERR, "cmd->task.clock: %d", cmd->task.clock);
	zabbix_log(LOG_LEVEL_ERR, "cmd->task.ttl: %d", cmd->task.ttl);
	zabbix_log(LOG_LEVEL_ERR, "cmd->commandtype: %d", cmd->commandtype);
	zabbix_log(LOG_LEVEL_ERR, "cmd->command: %s", cmd->command);
	zabbix_log(LOG_LEVEL_ERR, "cmd->execute_on: %d", cmd->execute_on);
	zabbix_log(LOG_LEVEL_ERR, "cmd->port: %d", cmd->port);
	zabbix_log(LOG_LEVEL_ERR, "cmd->authtype: %d", cmd->authtype);
	zabbix_log(LOG_LEVEL_ERR, "cmd->username: %s", cmd->username);
	zabbix_log(LOG_LEVEL_ERR, "cmd->password: %s", cmd->password);
	zabbix_log(LOG_LEVEL_ERR, "cmd->publickey: %s", cmd->publickey);
	zabbix_log(LOG_LEVEL_ERR, "cmd->privatekey: %s", cmd->privatekey);
	zabbix_log(LOG_LEVEL_ERR, "cmd->parent_taskid: " ZBX_FS_UI64, cmd->parent_taskid);
	zabbix_log(LOG_LEVEL_ERR, "cmd->hostid: " ZBX_FS_UI64, cmd->hostid);
	zabbix_log(LOG_LEVEL_ERR, "cmd->alertid: " ZBX_FS_UI64, cmd->hostid);
}
