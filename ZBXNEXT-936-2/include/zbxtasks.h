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

#define	ZBX_REMOTE_COMMAND_TTL	600

/******************/
/*                */
/* remote command */
/*                */
/******************/
struct zbx_task_remote_command;

struct zbx_task_remote_command	*zbx_task_remote_command_new(void);
void				zbx_task_remote_command_free(struct zbx_task_remote_command *cmd);

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
		zbx_uint64_t	alertid);
int	zbx_task_remote_command_init_from_json(struct zbx_task_remote_command *cmd,
		zbx_uint64_t	taskid,
		const char	*opening_brace);
void	zbx_task_remote_command_clear(struct zbx_task_remote_command *cmd);

void	zbx_task_remote_command_db_insert_prepare(zbx_db_insert_t *db_task_insert, zbx_db_insert_t *db_task_remote_command_insert);
void	zbx_task_remote_command_db_insert_add_values(const struct zbx_task_remote_command *cmd,
		zbx_db_insert_t *db_task_insert,
		zbx_db_insert_t *db_task_remote_command_insert);
void	zbx_task_remote_command_serialize_json(const struct zbx_task_remote_command *cmd, struct zbx_json *json);

void	zbx_task_remote_command_process_task(struct zbx_task_remote_command *cmd);
void	zbx_task_remote_command_log(const struct zbx_task_remote_command *cmd);

/*************************/
/*                       */
/* remote command result */
/*                       */
/*************************/
struct zbx_task_remote_command_result;

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
