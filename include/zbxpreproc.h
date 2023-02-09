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

#ifndef ZABBIX_PP_PREPROC_H
#define ZABBIX_PP_PREPROC_H

#include "zbxalgo.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxtimekeeper.h"
#include "zbxipcservice.h"

typedef void (*zbx_pp_notify_cb_t)(void *data);

/* one preprocessing step history */
typedef struct
{
	int		index;
	zbx_variant_t	value;
	zbx_timespec_t	ts;
}
zbx_pp_step_history_t;

ZBX_VECTOR_DECL(pp_step_history, zbx_pp_step_history_t)

/* item preprocessing history for preprocessing steps using previous values */
typedef struct
{
	zbx_vector_pp_step_history_t	step_history;
}
zbx_pp_history_t;

zbx_pp_history_t	*zbx_pp_history_create(int history_num);
void	zbx_pp_history_init(zbx_pp_history_t *history);
void	zbx_pp_history_clear(zbx_pp_history_t *history);
void	zbx_pp_history_reserve(zbx_pp_history_t *history, int history_num);
void	zbx_pp_history_add(zbx_pp_history_t *history, int index, zbx_variant_t *value, zbx_timespec_t ts);

typedef enum
{
	ZBX_PP_PROCESS_PARALLEL,
	ZBX_PP_PROCESS_SERIAL
}
zbx_pp_process_mode_t;

typedef struct
{
	int	type;
	int	error_handler;
	char	*params;
	char	*error_handler_params;
}
zbx_pp_step_t;

void	zbx_pp_step_free(zbx_pp_step_t *step);

ZBX_PTR_VECTOR_DECL(pp_step_ptr, zbx_pp_step_t *)

typedef struct
{
	zbx_uint32_t		refcount;

	int			steps_num;
	zbx_pp_step_t		*steps;

	int			dep_itemids_num;
	zbx_uint64_t		*dep_itemids;

	unsigned char		type;
	unsigned char		value_type;
	unsigned char		flags;
	zbx_pp_process_mode_t	mode;

	zbx_pp_history_t	*history;	/* the preprocessing history */
	int			history_num;	/* the number of preprocessing steps requiring history */
}
zbx_pp_item_preproc_t;

#define ZBX_PP_VALUE_OPT_NONE		0x0000
#define ZBX_PP_VALUE_OPT_META		0x0001
#define ZBX_PP_VALUE_OPT_LOG		0x0002

typedef struct
{
	zbx_uint32_t	flags;
	int		mtime;
	int		timestamp;
	int		severity;
	int		logeventid;
	zbx_uint64_t	lastlogsize;
	char		*source;
}
zbx_pp_value_opt_t;

void	zbx_pp_value_opt_clear(zbx_pp_value_opt_t *opt);

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	zbx_uint64_t		revision;

	zbx_pp_item_preproc_t	*preproc;
}
zbx_pp_item_t;


/* preprocessing step execution result */
typedef struct
{
	zbx_variant_t	value;
	zbx_variant_t	value_raw;
	int		action;
}
zbx_pp_result_t;

ZBX_PTR_VECTOR_DECL(pp_result_ptr, zbx_pp_result_t *)

void	zbx_pp_result_free(zbx_pp_result_t *result);

typedef enum
{
	ZBX_PP_TASK_TEST = 1,
	ZBX_PP_TASK_VALUE,
	ZBX_PP_TASK_VALUE_SEQ,
	ZBX_PP_TASK_DEPENDENT,
	ZBX_PP_TASK_SEQUENCE
}
zbx_pp_task_type_t;

typedef struct
{
	zbx_pp_task_type_t	type;
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	void			*data;
}
zbx_pp_task_t;

ZBX_PTR_VECTOR_DECL(pp_task_ptr, zbx_pp_task_t *)

void	zbx_pp_value_task_get_data(zbx_pp_task_t *task, unsigned char *value_type, unsigned char *flags,
		zbx_variant_t **value, zbx_timespec_t *ts, zbx_pp_value_opt_t **value_opt);
void	zbx_pp_test_task_get_data(zbx_pp_task_t *task, zbx_ipc_client_t **client, zbx_variant_t **value,
		zbx_pp_result_t **results, int *results_num, zbx_pp_history_t **history);
void	zbx_pp_tasks_clear(zbx_vector_pp_task_ptr_t *tasks);

zbx_pp_item_preproc_t	*zbx_pp_item_preproc_create(unsigned char type, unsigned char value_type, unsigned char flags);
void	zbx_pp_item_preproc_release(zbx_pp_item_preproc_t *preproc);
int	zbx_pp_preproc_has_history(int type);

typedef struct zbx_pp_manager zbx_pp_manager_t;

zbx_pp_manager_t	*zbx_pp_manager_create(int workers_num, zbx_pp_notify_cb_t finished_cb,
		void *finished_data, char **error);
void	zbx_pp_manager_free(zbx_pp_manager_t *manager);
zbx_pp_task_t	*zbx_pp_manager_create_task(zbx_pp_manager_t *manager, zbx_uint64_t itemid, zbx_variant_t *value,
		zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt);
void	zbx_pp_manager_queue_value_preproc(zbx_pp_manager_t *manager, zbx_vector_pp_task_ptr_t *tasks);
void	zbx_pp_manager_queue_test(zbx_pp_manager_t *manager, zbx_pp_item_preproc_t *preproc, zbx_variant_t *value,
		zbx_timespec_t ts, zbx_ipc_client_t *client);
void	zbx_pp_manager_process_finished(zbx_pp_manager_t *manager, zbx_vector_pp_task_ptr_t *tasks,
		zbx_uint64_t *pending_num, zbx_uint64_t *processing_num, zbx_uint64_t *finished_num);
void	zbx_pp_manager_dump_items(zbx_pp_manager_t *manager);

zbx_uint64_t	zbx_pp_manager_get_revision(const zbx_pp_manager_t *manager);
void	zbx_pp_manager_set_revision(zbx_pp_manager_t *manager, zbx_uint64_t revision);
zbx_hashset_t	*zbx_pp_manager_items(zbx_pp_manager_t *manager);
zbx_uint64_t	zbx_pp_manager_get_pending_num(zbx_pp_manager_t *manager);
void	zbx_pp_manager_get_diag_stats(zbx_pp_manager_t *manager, zbx_uint64_t *preproc_num, zbx_uint64_t *pending_num,
		zbx_uint64_t *finished_num, zbx_uint64_t *sequences_num);

typedef struct
{
	zbx_uint64_t	itemid;
	int		tasks_num;
}
zbx_pp_sequence_stats_t;

ZBX_PTR_VECTOR_DECL(pp_sequence_stats_ptr, zbx_pp_sequence_stats_t *)

void	zbx_pp_manager_get_sequence_stats(zbx_pp_manager_t *manager, zbx_vector_pp_sequence_stats_ptr_t *sequences);

void	zbx_pp_manager_get_worker_usage(zbx_pp_manager_t *manager, zbx_vector_dbl_t *worker_usage);

#endif
