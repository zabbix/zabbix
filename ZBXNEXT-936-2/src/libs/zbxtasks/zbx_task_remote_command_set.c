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

/**********************/
/*                    */
/* remote command set */
/*                    */
/**********************/
struct zbx_task_remote_command_set {
	zbx_vector_ptr_t	commands;
	int			state;
};

struct zbx_task_remote_command_set	*zbx_task_remote_command_set_new(void)
{
	struct zbx_task_remote_command_set	*set = NULL;

	set = zbx_calloc(set, 1, sizeof(struct zbx_task_remote_command_set));

	return set;
}

void	zbx_task_remote_command_set_free(struct zbx_task_remote_command_set *set)
{
	assert(NULL != set);

	/* call zbx_task_remote_command_set_clear() before freeing the set */
	assert(NULL == set->commands.values);		/* vector must be destroyed */

	zbx_free(set);
}

int	zbx_task_remote_command_set_init_from_json(struct zbx_task_remote_command_set *set, const struct zbx_json_parse *jp)
{
	int			ret = FAIL;
	struct zbx_json_parse	jp_data;
	const char		*opening_brace = NULL;

	assert(NULL != set);
	assert(NULL == set->commands.values);

	zbx_vector_ptr_create(&(set->commands));

	/* open array "data": [...] */
	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
		goto err;

	/* iterate over objects in array "data": [{obj0}, {obj1}, ...] */
	/*                                        ^                    */
	while (NULL != (opening_brace = zbx_json_next(&jp_data, opening_brace)))
	{
		zbx_uint64_t			taskid;
		struct zbx_task_remote_command  *cmd;

		cmd = zbx_task_remote_command_new();

		taskid = DBget_maxid("task");

		ret = zbx_task_remote_command_init_from_json(cmd, taskid, opening_brace);

		/* the cmd will be cleared and freed with the rest of the set */
		zbx_vector_ptr_append(&(set->commands), cmd);

		if (SUCCEED != ret)
			goto err;
	}

err:
	return ret;
}

int	zbx_task_remote_command_set_init_from_db(struct zbx_task_remote_command_set *set, int status)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	assert(NULL != set);
	assert(NULL == set->commands.values);
	assert(ZBX_TM_STATUS_NEW == status || ZBX_TM_STATUS_INPROGRESS == status ||\
		ZBX_TM_STATUS_DONE == status || ZBX_TM_STATUS_EXPIRED == status);

	zbx_vector_ptr_create(&(set->commands));

	result = DBselect(
			"select t.taskid,t.status,t.clock,t.ttl,"
				"c.command_type,c.execute_on,c.port,c.authtype,c.username,c.password,c.publickey,c.privatekey,"
				"c.command,c.alertid,c.parent_taskid,c.hostid"
			" from task t, task_remote_command c"
			" where t.taskid=c.taskid"
			" order by taskid");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t			taskid;
		zbx_uint64_t			alertid;
		zbx_uint64_t			parent_taskid;
		zbx_uint64_t			hostid;
		struct zbx_task_remote_command  *cmd;

		cmd = zbx_task_remote_command_new();

		ZBX_STR2UINT64(taskid, row[0]);
		ZBX_STR2UINT64(alertid, row[13]);
		ZBX_STR2UINT64(parent_taskid, row[14]);
		ZBX_STR2UINT64(hostid, row[15]);

		ret = zbx_task_remote_command_init(cmd,
			/* t.taskid */		taskid,
			/* t.type */		ZBX_TM_TASK_SEND_REMOTE_COMMAND,
			/* t.status */		atoi(row[1]),
			/* t.clock */		atoi(row[2]),
			/* t.ttl */		atoi(row[3]),
			/* c.command_type */	atoi(row[4]),
			/* c,command */		ZBX_NULL2EMPTY_STR(row[12]),
			/* c.execute_on */	atoi(row[5]),
			/* c.port */		atoi(row[6]),
			/* c.authtype */	atoi(row[7]),
			/* c.username */	ZBX_NULL2EMPTY_STR(row[8]),
			/* c.password */	ZBX_NULL2EMPTY_STR(row[9]),
			/* c.publickey */	ZBX_NULL2EMPTY_STR(row[10]),
			/* c.privatekey */	ZBX_NULL2EMPTY_STR(row[11]),
			/* c.parent_taskid */	parent_taskid,
			/* c.hostid */		hostid,
			/* c.alertid */		alertid);

		zbx_vector_ptr_append(&(set->commands), cmd);

		/* the cmd will be cleared and freed with the rest of the set */
		if (SUCCEED != ret)
			goto err;
	}

err:
	return ret;
}

void	zbx_task_remote_command_set_clear(struct zbx_task_remote_command_set *set)
{
	int	i;

	assert(NULL != set);
	/* not asserting that members are initialized since partially initialized set can be cleared as well */

	for (i = 0; i < set->commands.values_num; i++)
	{
		zbx_task_remote_command_clear(set->commands.values[i]);
		zbx_task_remote_command_free(set->commands.values[i]);
	}

	zbx_vector_ptr_destroy(&(set->commands));
}

void	zbx_task_remote_command_set_insert_into_db(const struct zbx_task_remote_command_set *set)
{
	zbx_db_insert_t	db_task_insert;
	zbx_db_insert_t	db_task_remote_command_insert;
	int		i;

	assert(NULL != set);
	assert(NULL != set->commands.values);

	zbx_task_remote_command_db_insert_prepare(&db_task_insert,
		&db_task_remote_command_insert);

	for (i = 0; i < set->commands.values_num; i++)
	{
		zbx_task_remote_command_log(set->commands.values[i]);
		zbx_task_remote_command_db_insert_add_values(set->commands.values[i],
			&db_task_insert, &db_task_remote_command_insert);
	}

	DBbegin();
	zbx_db_insert_execute(&db_task_insert);
	zbx_db_insert_execute(&db_task_remote_command_insert);
	DBcommit();

	zbx_db_insert_clean(&db_task_insert);
	zbx_db_insert_clean(&db_task_remote_command_insert);
}

void	zbx_task_remote_command_set_serialize_json(const struct zbx_task_remote_command_set *set, struct zbx_json *json)
{
	int	i;

	assert(NULL != set);
	assert(NULL != set->commands.values);
	assert(NULL != json);

	zbx_json_addarray(json, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < set->commands.values_num; i++)
	{
		zbx_task_remote_command_serialize_json(set->commands.values[i], json);
	}

	zbx_json_close(json);
}

void	zbx_task_remote_command_set_process_tasks(const struct zbx_task_remote_command_set *set)
{
	int	i;

	assert(NULL != set);
	assert(NULL != set->commands.values);

	for (i = 0; i < set->commands.values_num; i++)
	{
		zbx_task_remote_command_process_task(set->commands.values[i]);
	}
}
