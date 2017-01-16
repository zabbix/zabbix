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

#ifndef ZABBIX_ZBXTASKS_H
#define ZABBIX_ZBXTASKS_H

#include "db.h"
#include "zbxjson.h"

#define ZBX_TASK_UPDATE_FREQUENCY	1

#define ZBX_REMOTE_COMMAND_TTL		(SEC_PER_MIN * 10)

/* task manager task types */
#define ZBX_TM_TASK_UNDEFINED				0
#define ZBX_TM_TASK_CLOSE_PROBLEM			1
#define ZBX_TM_TASK_REMOTE_COMMAND			2
#define ZBX_TM_TASK_REMOTE_COMMAND_RESULT		3

/* task manager task states */
#define ZBX_TM_STATUS_NEW			1
#define ZBX_TM_STATUS_INPROGRESS		2
#define ZBX_TM_STATUS_DONE			3
#define ZBX_TM_STATUS_EXPIRED			4


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
	zbx_uint64_t    parent_taskid;
}
zbx_tm_remote_command_result_t;

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

zbx_tm_remote_command_t *zbx_tm_remote_command_create(int commandtype, const char *command, int execute_on, int port,
		int authtype, const char *username, const char *password, const char *publickey, const char *privatekey,
		zbx_uint64_t parent_taskid, zbx_uint64_t hostid, zbx_uint64_t alertid);

zbx_tm_remote_command_result_t	*zbx_tm_remote_command_result_create(zbx_uint64_t parent_taskid, int status,
		const char *error);

int	zbx_tm_save_tasks(zbx_vector_ptr_t *tasks);
int	zbx_tm_save_task(zbx_tm_task_t *task);

void	zbx_tm_get_proxy_tasks(zbx_vector_ptr_t *tasks, zbx_uint64_t proxy_hostid);
void	zbx_tm_update_task_status(zbx_vector_ptr_t *tasks, int status);
void	zbx_tm_delete_tasks(zbx_vector_ptr_t *tasks);
void	zbx_tm_json_serialize_tasks(struct zbx_json *json, const zbx_vector_ptr_t *tasks);
void	zbx_tm_json_deserialize_tasks(const struct zbx_json_parse *jp, zbx_vector_ptr_t *tasks);

/* separate implementation for proxy and server */
void	zbx_tm_get_remote_tasks(zbx_vector_ptr_t *tasks, zbx_uint64_t proxy_hostid);

/******************/
/*                */
/* remote command */
/*                */
/******************/
typedef struct zbx_task_remote_command zbx_task_remote_command_t;

struct zbx_task_remote_command	*zbx_task_remote_command_new(void);
void				zbx_task_remote_command_free(zbx_task_remote_command_t*cmd);

int	zbx_task_remote_command_init(zbx_task_remote_command_t *cmd,
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
		zbx_uint64_t	alertid);
int	zbx_task_remote_command_init_from_json(zbx_task_remote_command_t *cmd,
		zbx_uint64_t	taskid,
		const char	*opening_brace);
void	zbx_task_remote_command_clear(zbx_task_remote_command_t *cmd);
void	zbx_task_reamote_command_free(zbx_task_remote_command_t *cmd);

void	zbx_task_remote_command_db_insert_prepare(zbx_db_insert_t *db_task_insert, zbx_db_insert_t *db_task_remote_command_insert);
void	zbx_task_remote_command_db_insert_add_values(const zbx_task_remote_command_t*cmd,
		zbx_db_insert_t *db_task_insert,
		zbx_db_insert_t *db_task_remote_command_insert);
void	zbx_task_remote_command_serialize_json(const zbx_task_remote_command_t*cmd, struct zbx_json *json);

void	zbx_task_remote_command_process_task(zbx_task_remote_command_t*cmd);
void	zbx_task_remote_command_log(const zbx_task_remote_command_t*cmd);

int	zbx_task_remote_command_save(zbx_task_remote_command_t *tasks, int tasks_num);
void	zbx_task_remote_command_read(zbx_vector_ptr_t *tasks, int status);

/*************************/
/*                       */
/* remote command result */
/*                       */
/*************************/
typedef struct zbx_task_remote_command_result zbx_task_remote_command_result_t;

struct zbx_task_remote_command_result	*zbx_task_remote_command_result_new(void);
void					zbx_task_remote_command_result_free(struct zbx_task_remote_command_result *res);

int	zbx_task_remote_command_result_init(struct zbx_task_remote_command_result *res,
		zbx_uint64_t	taskid,
		int		type,
		int		task_status,
		int		clock,
		int		ttl,
		int		status,
		const char	*error,
		zbx_uint64_t	parent_taskid);
int	zbx_task_remote_command_result_init_from_json(struct zbx_task_remote_command_result *res,
		zbx_uint64_t	taskid,
		int		clock,
		const char	*opening_brace);
void	zbx_task_remote_command_result_clear(struct zbx_task_remote_command_result *res);

void	zbx_task_remote_command_result_db_insert_prepare(zbx_db_insert_t *db_task_insert,
		zbx_db_insert_t *db_task_remote_command_result_insert);
void	zbx_task_remote_command_result_db_insert_add_values(const struct zbx_task_remote_command_result *res,
		zbx_db_insert_t *db_task_insert,
		zbx_db_insert_t *db_task_remote_command_result_insert);
void	zbx_task_remote_command_result_serialize_json(const struct zbx_task_remote_command_result *res, struct zbx_json *json);

void	zbx_task_remote_command_result_process_task(struct zbx_task_remote_command_result *res);
void	zbx_task_remote_command_result_log(const struct zbx_task_remote_command_result *res);

/**********************/
/*                    */
/* remote command set */
/*                    */
/**********************/
struct zbx_task_remote_command_set;

struct zbx_task_remote_command_set	*zbx_task_remote_command_set_new(void);
void					zbx_task_remote_command_set_free(struct zbx_task_remote_command_set *set);

int	zbx_task_remote_command_set_init_from_db(struct zbx_task_remote_command_set *set, int status);
int	zbx_task_remote_command_set_init_from_json(struct zbx_task_remote_command_set *set,
		const struct zbx_json_parse *jp);
void	zbx_task_remote_command_set_clear(struct zbx_task_remote_command_set *set);

void	zbx_task_remote_command_set_insert_into_db(const struct zbx_task_remote_command_set *set);
void	zbx_task_remote_command_set_serialize_json(const struct zbx_task_remote_command_set *set, struct zbx_json *json);

void	zbx_task_remote_command_set_process_tasks(const struct zbx_task_remote_command_set *set);

/*****************************/
/*                           */
/* remote command result set */
/*                           */
/*****************************/
struct zbx_task_remote_command_result_set;

struct zbx_task_remote_command_result_set	*zbx_task_remote_command_result_set_new(void);
void						zbx_task_remote_command_result_set_free(struct zbx_task_remote_command_result_set *set);

int	zbx_task_remote_command_result_set_init_from_db(struct zbx_task_remote_command_result_set *set, int status);
int	zbx_task_remote_command_result_set_init_from_json(struct zbx_task_remote_command_result_set *set,
		const struct zbx_json_parse *jp);
void	zbx_task_remote_command_result_set_clear(struct zbx_task_remote_command_result_set *set);

void	zbx_task_remote_command_result_set_insert_into_db(const struct zbx_task_remote_command_result_set *set);
void	zbx_task_remote_command_result_set_serialize_json(const struct zbx_task_remote_command_result_set *set, struct zbx_json *json);

void	zbx_task_remote_command_result_set_process_tasks(const struct zbx_task_remote_command_result_set *set);

#endif
