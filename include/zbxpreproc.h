/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_PP_PREPROC_H
#define ZABBIX_PP_PREPROC_H

#include "zbxpreprocbase.h"
#include "zbxalgo.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxtimekeeper.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"
#include "zbxjson.h"
#include "zbxstats.h"
#include "zbxcachehistory.h"

#define ZBX_PREPROCESSING_BATCH_SIZE	256

typedef void (*zbx_pp_notify_cb_t)(void *data);

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

typedef struct
{
	int		workers_num;
	int		config_timeout;
	const char	*config_source_ip;
}
zbx_thread_pp_manager_args;

typedef struct zbx_pp_manager	zbx_pp_manager_t;

typedef void(*zbx_flush_value_func_t)(zbx_pp_manager_t *manager, zbx_uint64_t itemid, unsigned char value_type,
	unsigned char flags, zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_value_opt_t *value_opt);

typedef int(*zbx_prepare_value_func_t)(const zbx_variant_t *value, const zbx_pp_value_opt_t *value_opt);

void	zbx_init_library_preproc(zbx_prepare_value_func_t prepare_value_cb, zbx_flush_value_func_t flush_value_cb,
		zbx_get_progname_f get_progname_cb);

void	zbx_pp_value_task_get_data(zbx_pp_task_t *task, unsigned char *value_type, unsigned char *flags,
		zbx_variant_t **value, zbx_timespec_t *ts, zbx_pp_value_opt_t **value_opt);
void	zbx_pp_test_task_get_data(zbx_pp_task_t *task, zbx_ipc_client_t **client, zbx_variant_t **value,
		zbx_pp_result_t **results, int *results_num, zbx_pp_history_t **history);
void	zbx_pp_tasks_clear(zbx_vector_pp_task_ptr_t *tasks);

zbx_hashset_t	*zbx_pp_manager_items(zbx_pp_manager_t *manager);

typedef struct
{
	zbx_uint64_t	itemid;
	int		tasks_num;
}
zbx_pp_top_stats_t;

ZBX_PTR_VECTOR_DECL(pp_top_stats_ptr, zbx_pp_top_stats_t *)

int	zbx_diag_add_preproc_info(const struct zbx_json_parse *jp, struct zbx_json *json, char **error);
void zbx_preproc_stats_ext_get(struct zbx_json *json, const void *arg);
zbx_uint64_t	zbx_preprocessor_get_queue_size(void);
void	zbx_preprocessor_get_worker_info(zbx_process_info_t *info);
void	zbx_preprocess_item_value(zbx_uint64_t itemid, zbx_uint64_t hostid, unsigned char item_value_type,
		unsigned char item_flags, AGENT_RESULT *result, zbx_timespec_t *ts, unsigned char state, char *error);
void	zbx_preprocessor_flush(void);
int	zbx_preprocessor_get_diag_stats(zbx_uint64_t *preproc_num, zbx_uint64_t *pending_num,
		zbx_uint64_t *finished_num, zbx_uint64_t *sequences_num, char **error);
int	zbx_preprocessor_get_top_sequences(int limit, zbx_vector_pp_top_stats_ptr_t *stats, char **error);
int	zbx_preprocessor_get_top_peak(int limit, zbx_vector_pp_top_stats_ptr_t *stats, char **error);
int	zbx_preprocessor_test(unsigned char value_type, const char *value, const zbx_timespec_t *ts,
		unsigned char state, const zbx_vector_pp_step_ptr_t *steps, zbx_vector_pp_result_ptr_t *results,
		zbx_pp_history_t *history, char **error);
int	zbx_preprocessor_get_usage_stats(zbx_vector_dbl_t *usage, int *count, char **error);

ZBX_THREAD_ENTRY(zbx_pp_manager_thread, args);

#endif
