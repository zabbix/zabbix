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

/*************************/
/*                       */
/* remote command result */
/*                       */
/*************************/
struct zbx_task_remote_command_result {
	struct zbx_task	task;
	int		status;
	char		*error;
	zbx_uint64_t    parent_taskid;
};

struct zbx_task_remote_command_result	*zbx_task_remote_command_result_new(void)
{
	struct zbx_task_remote_command_result	*res = NULL;

	res = zbx_calloc(res, 1, sizeof(struct zbx_task_remote_command_result));

	return res;
}

void	zbx_task_remote_command_result_free(struct zbx_task_remote_command_result *res)
{
	assert(NULL != res);
	assert(NULL == res->error); /* error must be cleared - detecting memory leak */

	zbx_free(res);
}

static int zbx_task_remote_command_result_validate(const struct zbx_task_remote_command_result *res)
{
	int	ret = FAIL;

	if (ZBX_TM_TASK_SEND_REMOTE_COMMAND_RESULT != res->task.type)
		goto err;

	ret = SUCCEED;

err:
	return ret;
}

int	zbx_task_remote_command_result_init(struct zbx_task_remote_command_result *res,
		zbx_uint64_t	taskid,
		int		type,
		int		task_status,
		int		clock,
		int		ttl,
		int		status,
		const char	*error,
		zbx_uint64_t	parent_taskid)
{
	int	ret = FAIL;

	assert(NULL != res);
	assert(NULL != error);

	res->task.taskid = taskid;
	res->task.type = type;
	res->task.status = task_status;
	res->task.clock = clock;
	res->task.ttl = ttl;
	res->status = status;
	res->error = zbx_strdup(res->error, error);
	res->parent_taskid = parent_taskid;

	ret = zbx_task_remote_command_result_validate(res);

	return ret;
}

int	zbx_task_remote_command_result_init_from_json(struct zbx_task_remote_command_result *res,
		zbx_uint64_t	taskid,
		int		clock,
		const char	*opening_brace)
{
	struct zbx_json_parse	jp_row;
	int			ret = FAIL;
	char			*type = NULL;
	char			*status = NULL;
	char			*error = NULL;
	char			*parent_taskid = NULL;
	size_t			tmp_alloc = 0;

	zbx_uint64_t		parent_taskid_num;

	assert(NULL != res);
	assert(NULL == res->error); /* must be cleared - detecting memory leaks */

	/* get values as strings from JSON object */
	if (FAIL == (ret = zbx_json_brackets_open(opening_brace, &jp_row)))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_TYPE, &type, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_STATUS, &status, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_ERROR, &error, &tmp_alloc))
		goto err;

	tmp_alloc = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_PARENT_TASKID, &parent_taskid, &tmp_alloc))
		goto err;

	/* initialize object */
	ZBX_STR2UINT64(parent_taskid_num, parent_taskid);

	ret = zbx_task_remote_command_result_init(res,
		/* t.taskid */          taskid,
		/* t.type */		ZBX_TM_TASK_SEND_REMOTE_COMMAND_RESULT,
		/* t.status */		ZBX_TM_STATUS_NEW,
		/* t.clock */		clock,
		/* t.ttl */		0,
		/* r.status */		atoi(status),
		/* r.error */		error,
		/* r.parent_taskid */	parent_taskid_num);

err:
	zbx_free(type);
	zbx_free(status);
	zbx_free(error);
	zbx_free(parent_taskid);

	return ret;
}

void    zbx_task_remote_command_result_clear(struct zbx_task_remote_command_result *res)
{
	assert(NULL != res);
	/* not asserting that members are not NULL since partially initialized res can be cleared as well */

	zbx_free(res->error);

	memset(res, 0, sizeof(struct zbx_task_remote_command_result));
}

void	zbx_task_remote_command_result_serialize_json(const struct zbx_task_remote_command_result *res, struct zbx_json *json)
{
	assert(NULL != res);
	assert(NULL != res->error);
	assert(NULL != json);

	zbx_json_addobject(json, NULL);

	zbx_json_addint64(json, ZBX_PROTO_TAG_TYPE, res->task.type);
	zbx_json_addint64(json, ZBX_PROTO_TAG_STATUS, res->status);
	zbx_json_addstring(json, ZBX_PROTO_TAG_ERROR, res->error, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json, ZBX_PROTO_TAG_PARENT_TASKID, res->parent_taskid);

	zbx_json_close(json);
}


/* class method */
void	zbx_task_remote_command_result_db_insert_prepare(zbx_db_insert_t *db_task_insert,
		zbx_db_insert_t *db_task_remote_command_result_insert)
{
	assert(NULL != db_task_insert);
	assert(NULL != db_task_remote_command_result_insert);

	zbx_db_insert_prepare(db_task_insert, "task",
		"taskid",
		"type",
		"status",
		"clock",
		"ttl",
		NULL);

	zbx_db_insert_prepare(db_task_remote_command_result_insert, "task_remote_command_result",
		"taskid",
		"status",
		"parent_taskid",
		"error",
		NULL);
}

void	zbx_task_remote_command_result_db_insert_add_values(const struct zbx_task_remote_command_result *res,
		zbx_db_insert_t *db_task_insert,
		zbx_db_insert_t *db_task_remote_command_result_insert)
{
	assert(NULL != res);
	assert(NULL != res->error);

	assert(NULL != db_task_insert);
	assert(NULL != db_task_remote_command_result_insert);

	zbx_db_insert_add_values(db_task_insert,
		res->task.taskid,
		res->task.type,
		res->task.status,
		res->task.clock,
		res->task.ttl);

	zbx_db_insert_add_values(db_task_remote_command_result_insert,
		res->task.taskid,
		res->status,
		res->parent_taskid,
		res->error);
}

void	zbx_task_remote_command_result_process_new_task(struct zbx_task_remote_command_result *res)
{
	assert(NULL != res);
	assert(NULL != res->error);

	/* task in progress */
	DBexecute("update tasks set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_INPROGRESS, res->task.taskid);

	zabbix_log(LOG_LEVEL_ERR, "processing command result task..taskid: " ZBX_FS_UI64, res->task.taskid);

	/* update alert */

	/* parent task is done */
	DBexecute("update tasks set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_DONE, res->parent_taskid);

	/* command result task is done */
	DBexecute("update tasks set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_DONE, res->task.taskid);
}

void	zbx_task_remote_command_result_process_task(struct zbx_task_remote_command_result *res)
{
	switch (res->task.status) {
		case ZBX_TM_STATUS_NEW:
			zbx_task_remote_command_result_process_new_task(res);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}
}

void	zbx_task_remote_command_result_log(const struct zbx_task_remote_command_result *res)
{
	assert(NULL != res);
	assert(NULL != res->error);

	zabbix_log(LOG_LEVEL_ERR, "res->task.taskid: " ZBX_FS_UI64, res->task.taskid);
	zabbix_log(LOG_LEVEL_ERR, "res->task.type: %d", res->task.type);
	zabbix_log(LOG_LEVEL_ERR, "res->task.status: %d", res->task.status);
	zabbix_log(LOG_LEVEL_ERR, "res->task.clock: %d", res->task.clock);
	zabbix_log(LOG_LEVEL_ERR, "res->task.ttl: %d", res->task.ttl);
	zabbix_log(LOG_LEVEL_ERR, "res->status: %d", res->status);
	zabbix_log(LOG_LEVEL_ERR, "res->error: %s", res->error);
	zabbix_log(LOG_LEVEL_ERR, "res->parent_taskid: " ZBX_FS_UI64, res->parent_taskid);
}
