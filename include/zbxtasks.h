/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_ZBXTASKS_H
#define ZABBIX_ZBXTASKS_H

#include "zbxalgo.h"
#include "zbxjson.h"

#define ZBX_TASK_UPDATE_FREQUENCY	1

#define ZBX_REMOTE_COMMAND_TTL			(SEC_PER_MIN * 10)
#define ZBX_DATA_TTL				30
#define ZBX_DATA_ACTIVE_PROXY_CONFIG_RELOAD_TTL	(SEC_PER_MIN * 10)

/* task manager task types */
#define ZBX_TM_TASK_UNDEFINED				0
#define ZBX_TM_TASK_CLOSE_PROBLEM			1
#define ZBX_TM_TASK_REMOTE_COMMAND			2
#define ZBX_TM_TASK_REMOTE_COMMAND_RESULT		3
#define ZBX_TM_TASK_ACKNOWLEDGE				4
#define ZBX_TM_TASK_UPDATE_EVENTNAMES			5
#define ZBX_TM_TASK_CHECK_NOW				6
#define ZBX_TM_TASK_DATA				7
#define ZBX_TM_TASK_DATA_RESULT				8
#define ZBX_TM_PROXYDATA				9

/* task manager task states */
#define ZBX_TM_STATUS_NEW			1
#define ZBX_TM_STATUS_INPROGRESS		2
#define ZBX_TM_STATUS_DONE			3
#define ZBX_TM_STATUS_EXPIRED			4

/* task data type */
#define ZBX_TM_DATA_TYPE_TEST_ITEM			0
#define ZBX_TM_DATA_TYPE_DIAGINFO			1
#define ZBX_TM_DATA_TYPE_PROXY_HOSTIDS			2
#define ZBX_TM_DATA_TYPE_PROXY_HOSTNAME			3
#define ZBX_TM_DATA_TYPE_ACTIVE_PROXY_CONFIG_RELOAD	4
#define ZBX_TM_DATA_TYPE_TEMP_SUPPRESSION		5

/* the time period after which finished (done/expired) tasks are removed */
#define ZBX_TM_CLEANUP_TASK_AGE			SEC_PER_DAY

typedef struct
{
	int		command_type;
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
}
zbx_tm_remote_command_t;

typedef struct
{
	int		status;
	char		*info;
	zbx_uint64_t	parent_taskid;
}
zbx_tm_remote_command_result_t;

typedef struct
{
	zbx_uint64_t	itemid;
}
zbx_tm_check_now_t;

typedef struct
{
	zbx_uint64_t	parent_taskid;
	char		*data;
	int		type;
}
zbx_tm_data_t;

typedef struct
{
	int		status;
	char		*info;
	zbx_uint64_t	parent_taskid;
}
zbx_tm_data_result_t;

typedef struct
{
	/* the task identifier */
	zbx_uint64_t	taskid;
	/* the target proxy hostid or 0 if the task must be on server, ignored by proxy */
	zbx_uint64_t	proxy_hostid;
	/* the task type (ZBX_TM_TASK_* defines) */
	unsigned char	type;
	/* the task status (ZBX_TM_STATUS_* defines) */
	unsigned char	status;
	/* the task creation time */
	int		clock;
	/* the task expiration period in seconds */
	int		ttl;

	/* the task data, depending on task type */
	void		*data;
}
zbx_tm_task_t;


zbx_tm_task_t	*zbx_tm_task_create(zbx_uint64_t taskid, unsigned char type, unsigned char status, int clock, int ttl,
		zbx_uint64_t proxy_hostid);
void	zbx_tm_task_clear(zbx_tm_task_t *task);
void	zbx_tm_task_free(zbx_tm_task_t *task);

zbx_tm_remote_command_t	*zbx_tm_remote_command_create(int command_type, const char *command, int execute_on, int port,
		int authtype, const char *username, const char *password, const char *publickey, const char *privatekey,
		zbx_uint64_t parent_taskid, zbx_uint64_t hostid, zbx_uint64_t alertid);

zbx_tm_remote_command_result_t	*zbx_tm_remote_command_result_create(zbx_uint64_t parent_taskid, int status,
		const char *info);

zbx_tm_check_now_t	*zbx_tm_check_now_create(zbx_uint64_t itemid);

zbx_tm_data_t		*zbx_tm_data_create(zbx_uint64_t parent_taskid, const char *str, size_t len, int type);
zbx_tm_data_result_t	*zbx_tm_data_result_create(zbx_uint64_t parent_taskid, int status, const char *info);

int	zbx_tm_execute_task_data(const char *data, size_t len, zbx_uint64_t proxy_hostid, char **info);

void	zbx_tm_save_tasks(zbx_vector_ptr_t *tasks);
int	zbx_tm_save_task(zbx_tm_task_t *task);

void	zbx_tm_get_proxy_tasks(zbx_vector_ptr_t *tasks, zbx_uint64_t proxy_hostid);
void	zbx_tm_update_task_status(zbx_vector_ptr_t *tasks, int status);
void	zbx_tm_json_serialize_tasks(struct zbx_json *json, const zbx_vector_ptr_t *tasks);
void	zbx_tm_json_deserialize_tasks(const struct zbx_json_parse *jp, zbx_vector_ptr_t *tasks);

/* separate implementation for proxy and server */
void	zbx_tm_get_remote_tasks(zbx_vector_ptr_t *tasks, zbx_uint64_t proxy_hostid);

int	zbx_tm_get_diaginfo(const struct zbx_json_parse *jp, char **info);

#endif
