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

#include "preproc_worker.h"

#include "../db_lengths.h"
#include "common.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "preprocessing.h"
#include "zbxembed.h"
#include "item_preproc.h"
#include "preproc_history.h"

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

#define ZBX_PREPROC_VALUE_PREVIEW_LEN		100

typedef struct
{
	zbx_preproc_dep_t	*deps;
	int			deps_alloc;
	int			deps_offset;
	zbx_variant_t		value;
	zbx_timespec_t		ts;
}
zbx_preproc_dep_request_t;

zbx_es_t	es_engine;

/******************************************************************************
 *                                                                            *
 * Purpose: formats value in text format                                      *
 *                                                                            *
 * Parameters: value     - [IN] the value to format                           *
 *             value_str - [OUT] the formatted value                          *
 *                                                                            *
 * Comments: Control characters are replaced with '.' and truncated if it's   *
 *           larger than ZBX_PREPROC_VALUE_PREVIEW_LEN characters.            *
 *                                                                            *
 ******************************************************************************/
static void	worker_format_value(const zbx_variant_t *value, char **value_str)
{
	const char	*value_desc;
	size_t		i, len;

	value_desc = zbx_variant_value_desc(value);

	if (ZBX_PREPROC_VALUE_PREVIEW_LEN < zbx_strlen_utf8(value_desc))
	{
		/* truncate value and append '...' */
		len = zbx_strlen_utf8_nchars(value_desc, ZBX_PREPROC_VALUE_PREVIEW_LEN - ZBX_CONST_STRLEN("..."));
		*value_str = zbx_malloc(NULL, len + ZBX_CONST_STRLEN("...") + 1);
		memcpy(*value_str, value_desc, len);
		memcpy(*value_str + len, "...", ZBX_CONST_STRLEN("...") + 1);
	}
	else
	{
		*value_str = zbx_malloc(NULL, (len = strlen(value_desc)) + 1);
		memcpy(*value_str, value_desc, len + 1);
	}

	/* replace control characters */
	for (i = 0; i < len; i++)
	{
		if (0 != iscntrl((*value_str)[i]))
			(*value_str)[i] = '.';
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: formats one preprocessing step result                             *
 *                                                                            *
 * Parameters: step   - [IN] the preprocessing step number                    *
 *             result - [IN] the preprocessing step result                    *
 *             error  - [IN] the preprocessing step error (can be NULL)       *
 *             out    - [OUT] the formatted string                            *
 *                                                                            *
 ******************************************************************************/
static void	worker_format_result(int step, const zbx_preproc_result_t *result, const char *error, char **out)
{
	char	*actions[] = {"", " (discard value)", " (set value)", " (set error)"};

	if (NULL == error)
	{
		char	*value_str;

		worker_format_value(&result->value, &value_str);
		*out = zbx_dsprintf(NULL, "%d. Result%s: %s\n", step, actions[result->action], value_str);
		zbx_free(value_str);
	}
	else
	{
		*out = zbx_dsprintf(NULL, "%d. Failed%s: %s\n", step, actions[result->action], error);
		zbx_rtrim(*out, ZBX_WHITESPACE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: formats preprocessing error message                               *
 *                                                                            *
 * Parameters: value        - [IN] the input value                            *
 *             results      - [IN] the preprocessing step results             *
 *             results_num  - [IN] the number of executed steps               *
 *             errmsg       - [IN] the error message of last executed step    *
 *             error        - [OUT] the formatted error message               *
 *                                                                            *
 ******************************************************************************/
static void	worker_format_error(const zbx_variant_t *value, zbx_preproc_result_t *results, int results_num,
		const char *errmsg, char **error)
{
	char			*value_str, *err_step;
	int			i;
	size_t			error_alloc = 512, error_offset = 0;
	zbx_vector_str_t	results_str;
	zbx_db_mock_field_t	field;

	zbx_vector_str_create(&results_str);

	/* add header to error message */
	*error = zbx_malloc(NULL, error_alloc);
	worker_format_value(value, &value_str);
	zbx_snprintf_alloc(error, &error_alloc, &error_offset, "Preprocessing failed for: %s\n", value_str);
	zbx_free(value_str);

	zbx_db_mock_field_init(&field, ZBX_TYPE_CHAR, ZBX_ITEM_ERROR_LEN);

	zbx_db_mock_field_append(&field, *error);
	zbx_db_mock_field_append(&field, "...\n");

	/* format the last (failed) step */
	worker_format_result(results_num, &results[results_num - 1], errmsg, &err_step);
	zbx_vector_str_append(&results_str, err_step);

	if (SUCCEED == zbx_db_mock_field_append(&field, err_step))
	{
		/* format the first steps */
		for (i = results_num - 2; i >= 0; i--)
		{
			worker_format_result(i + 1, &results[i], NULL, &err_step);

			if (SUCCEED != zbx_db_mock_field_append(&field, err_step))
			{
				zbx_free(err_step);
				break;
			}

			zbx_vector_str_append(&results_str, err_step);
		}
	}

	/* add steps to error message */

	if (results_str.values_num < results_num)
		zbx_strcpy_alloc(error, &error_alloc, &error_offset, "...\n");

	for (i = results_str.values_num - 1; i >= 0; i--)
		zbx_strcpy_alloc(error, &error_alloc, &error_offset, results_str.values[i]);

	/* truncate formatted error if necessary */
	if (ZBX_ITEM_ERROR_LEN < zbx_strlen_utf8(*error))
	{
		char	*ptr;

		ptr = (*error) + zbx_db_strlen_n(*error, ZBX_ITEM_ERROR_LEN - 3);
		for (i = 0; i < 3; i++)
			*ptr++ = '.';
		*ptr = '\0';
	}

	zbx_vector_str_clear_ext(&results_str, zbx_str_free);
	zbx_vector_str_destroy(&results_str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute preprocessing steps                                       *
 *                                                                            *
 * Parameters: cache         - [IN/OUT] the preprocessing cache               *
 *             value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             steps         - [IN] the preprocessing steps to execute        *
 *             steps_num     - [IN] the number of preprocessing steps         *
 *             history_in    - [IN] the preprocessing history                 *
 *             history_out   - [OUT] the new preprocessing history            *
 *             results       - [OUT] the preprocessing step results           *
 *             results_num   - [OUT] the number of step results               *
 *             error         - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing steps finished successfully      *
 *               FAIL - otherwise, error contains the error message           *
 *                                                                            *
 ******************************************************************************/
static int	worker_item_preproc_execute(zbx_preproc_cache_t *cache, unsigned char value_type,
		zbx_variant_t *value_in, zbx_variant_t *value_out, const zbx_timespec_t *ts,
		zbx_preproc_op_t *steps, int steps_num, zbx_vector_ptr_t *history_in, zbx_vector_ptr_t *history_out,
		zbx_preproc_result_t *results, int *results_num, char **error)
{
	int		i, ret = SUCCEED;

	if (value_in != value_out)
	{
		if (0 == steps_num || NULL == cache || NULL == zbx_preproc_cache_get(cache, steps[0].type))
			zbx_variant_copy(value_out, value_in);
	}

	for (i = 0; i < steps_num; i++)
	{
		zbx_preproc_op_t	*op = &steps[i];
		zbx_variant_t		history_value;
		zbx_timespec_t		history_ts;
		zbx_preproc_cache_t	*pcache = (0 == i ? cache : NULL);

		zbx_preproc_history_pop_value(history_in, i, &history_value, &history_ts);

		if (FAIL == (ret = zbx_item_preproc(pcache, value_type, value_out, ts, op, &history_value, &history_ts,
				error)))
		{
			results[i].action = op->error_handler;
			ret = zbx_item_preproc_handle_error(value_out, op, error);
			zbx_variant_clear(&history_value);
		}
		else
			results[i].action = ZBX_PREPROC_FAIL_DEFAULT;

		if (SUCCEED == ret)
		{
			if (NULL == *error)
			{
				/* result history is kept to report results of steps before failing step, */
				/* which means it can be omitted for the last step.                       */
				if (i != steps_num - 1)
					zbx_variant_copy(&results[i].value, value_out);
				else
					zbx_variant_set_none(&results[i].value);
			}
			else
			{
				/* preprocessing step successfully extracted error, set it */
				results[i].action = ZBX_PREPROC_FAIL_FORCE_ERROR;
				ret = FAIL;
			}
		}

		if (SUCCEED != ret)
		{
			break;
		}

		if (ZBX_VARIANT_NONE != history_value.type)
		{
			/* the value is byte copied to history_out vector and doesn't have to be cleared */
			zbx_preproc_history_add_value(history_out, i, &history_value, &history_ts);
		}

		if (ZBX_VARIANT_NONE == value_out->type)
			break;
	}

	*results_num = (i == steps_num ? i : i + 1);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle item value preprocessing task                              *
 *                                                                            *
 * Parameters: socket  - [IN] IPC socket                                      *
 *             message - [IN] packed preprocessing task                       *
 *                                                                            *
 ******************************************************************************/
static void	worker_preprocess_value(zbx_ipc_socket_t *socket, zbx_ipc_message_t *message)
{
	zbx_uint32_t		size = 0;
	unsigned char		*data = NULL, value_type;
	zbx_uint64_t		itemid;
	zbx_variant_t		value, value_start;
	int			i, steps_num, results_num, ret;
	char			*errmsg = NULL, *error = NULL;
	zbx_timespec_t		*ts;
	zbx_preproc_op_t	*steps;
	zbx_vector_ptr_t	history_in, history_out;
	zbx_preproc_result_t	*results;

	zbx_vector_ptr_create(&history_in);
	zbx_vector_ptr_create(&history_out);

	zbx_preprocessor_unpack_task(&itemid, &value_type, &ts, &value, &history_in, &steps, &steps_num,
			message->data);

	zbx_variant_copy(&value_start, &value);
	results = (zbx_preproc_result_t *)zbx_malloc(NULL, sizeof(zbx_preproc_result_t) * (size_t)steps_num);
	memset(results, 0, sizeof(zbx_preproc_result_t) * (size_t)steps_num);

	if (FAIL == (ret = worker_item_preproc_execute(NULL, value_type, &value, &value, ts, steps, steps_num, &history_in,
			&history_out, results, &results_num, &errmsg)) && 0 != results_num)
	{
		int action = results[results_num - 1].action;

		if (ZBX_PREPROC_FAIL_SET_ERROR != action && ZBX_PREPROC_FAIL_FORCE_ERROR != action)
		{
			worker_format_error(&value_start, results, results_num, errmsg, &error);
			zbx_free(errmsg);
		}
		else
			error = errmsg;
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		const char	*result;

		result = (SUCCEED == ret ? zbx_variant_value_desc(&value) : error);
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): %s", __func__, zbx_variant_value_desc(&value_start));
		zabbix_log(LOG_LEVEL_DEBUG, "%s: %s %s",__func__, zbx_result_string(ret), result);
	}

	size = zbx_preprocessor_pack_result(&data, &value, &history_out, error);
	zbx_variant_clear(&value);
	zbx_free(error);
	zbx_free(ts);
	zbx_preprocessor_free_steps(steps, steps_num);

	if (FAIL == zbx_ipc_socket_write(socket, ZBX_IPC_PREPROCESSOR_RESULT, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send preprocessing result");
		exit(EXIT_FAILURE);
	}

	zbx_free(data);

	zbx_variant_clear(&value_start);

	for (i = 0; i < results_num; i++)
		zbx_variant_clear(&results[i].value);
	zbx_free(results);

	zbx_vector_ptr_clear_ext(&history_out, (zbx_clean_func_t)zbx_preproc_op_history_free);
	zbx_vector_ptr_destroy(&history_out);

	zbx_vector_ptr_clear_ext(&history_in, (zbx_clean_func_t)zbx_preproc_op_history_free);
	zbx_vector_ptr_destroy(&history_in);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle item value test preprocessing task                         *
 *                                                                            *
 * Parameters: socket  - [IN] IPC socket                                      *
 *             message - [IN] packed preprocessing task                       *
 *                                                                            *
 ******************************************************************************/
static void	worker_test_value(zbx_ipc_socket_t *socket, zbx_ipc_message_t *message)
{
	zbx_uint32_t		size;
	unsigned char		*data, value_type;
	zbx_variant_t		value, value_start;
	int			i, steps_num, results_num;
	char			*error = NULL, *value_str;
	zbx_timespec_t		ts;
	zbx_preproc_op_t	*steps;
	zbx_vector_ptr_t	history_in, history_out;
	zbx_preproc_result_t	*results;

	zbx_vector_ptr_create(&history_in);
	zbx_vector_ptr_create(&history_out);

	zbx_preprocessor_unpack_test_request(&value_type, &value_str, &ts, &history_in, &steps, &steps_num,
			message->data);

	zbx_variant_set_str(&value, value_str);
	zbx_variant_copy(&value_start, &value);

	results = (zbx_preproc_result_t *)zbx_malloc(NULL, sizeof(zbx_preproc_result_t) * (size_t)steps_num);
	memset(results, 0, sizeof(zbx_preproc_result_t) * (size_t)steps_num);

	zbx_item_preproc_test(value_type, &value, &ts, steps, steps_num, &history_in, &history_out, results,
			&results_num, &error);

	size = zbx_preprocessor_pack_test_result(&data, results, results_num, &history_out, error);

	if (FAIL == zbx_ipc_socket_write(socket, ZBX_IPC_PREPROCESSOR_TEST_RESULT, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send preprocessing result");
		exit(EXIT_FAILURE);
	}

	zbx_variant_clear(&value);
	zbx_free(error);
	zbx_preprocessor_free_steps(steps, steps_num);
	zbx_free(data);

	zbx_variant_clear(&value_start);

	for (i = 0; i < results_num; i++)
	{
		zbx_variant_clear(&results[i].value);
		zbx_free(results[i].error);
	}
	zbx_free(results);

	zbx_vector_ptr_clear_ext(&history_out, (zbx_clean_func_t)zbx_preproc_op_history_free);
	zbx_vector_ptr_destroy(&history_out);

	zbx_vector_ptr_clear_ext(&history_in, (zbx_clean_func_t)zbx_preproc_op_history_free);
	zbx_vector_ptr_destroy(&history_in);
}

static void	worker_dep_request_clear(zbx_preproc_dep_request_t *request)
{
	zbx_variant_clear(&request->value);
	zbx_preprocessor_free_deps(request->deps, request->deps_alloc);
	memset(request, 0, sizeof(zbx_preproc_dep_request_t));
}

/******************************************************************************
 *                                                                            *
 * Purpose: preprocess dependent items                                        *
 *                                                                            *
 * Parameters: socket  - [IN] IPC socket                                      *
 *             request - [IN] the dependent item preprocessing request        *
 *                                                                            *
 ******************************************************************************/
static void	worker_preprocess_dep_items(zbx_ipc_socket_t *socket, zbx_preproc_dep_request_t *request)
{
	int				i, results_alloc = 10;
	zbx_preproc_result_t		*results;
	zbx_preproc_cache_t		cache;
	zbx_vector_ptr_t		history_out;
	zbx_preproc_result_buffer_t	buf;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): items:%d/%d", __func__, request->deps_offset, request->deps_alloc);

	if (request->deps_alloc != request->deps_offset)
	{
		if (FAIL == zbx_ipc_socket_write(socket, ZBX_IPC_PREPROCESSOR_DEP_NEXT, NULL, 0))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot send preprocessing result");
			exit(EXIT_FAILURE);
		}

		goto out;
	}

	results = (zbx_preproc_result_t *)zbx_malloc(NULL, (size_t)results_alloc * sizeof(zbx_preproc_result_t));
	zbx_vector_ptr_create(&history_out);

	zbx_preprocessor_result_init(&buf, request->deps_alloc);
	zbx_preproc_cache_init(&cache);

	for (i = 0; i < request->deps_alloc; i++)
	{
		zbx_preproc_dep_t		*dep = request->deps + i;
		char				*errmsg = NULL, *error = NULL;
		int				j, step_results_num, ret;
		zbx_variant_t			value;

		zbx_variant_set_none(&value);

		if (dep->steps_num > results_alloc)
		{
			results_alloc = dep->steps_num * 1.5;
			results = (zbx_preproc_result_t *)zbx_realloc(results,
					(size_t)results_alloc * sizeof(zbx_preproc_result_t));
		}

		if (0 != dep->steps_num)
			memset(results, 0, (size_t)dep->steps_num * sizeof(zbx_preproc_result_t));

		if (FAIL == (ret = worker_item_preproc_execute(&cache, dep->value_type, &request->value, &value,
				&request->ts, dep->steps, dep->steps_num, &dep->history, &history_out, results,
				&step_results_num, &errmsg)) && 0 != step_results_num)
		{
			int action = results[step_results_num - 1].action;

			if (ZBX_PREPROC_FAIL_SET_ERROR != action && ZBX_PREPROC_FAIL_FORCE_ERROR != action)
			{
				worker_format_error(&request->value, results, step_results_num, errmsg, &error);
				zbx_free(errmsg);
			}
			else
				error = errmsg;
		}

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		{
			const char	*result_msg;

			result_msg = (SUCCEED == ret ? zbx_variant_value_desc(&value) : error);
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): %s", __func__, zbx_variant_value_desc(&request->value));
			zabbix_log(LOG_LEVEL_DEBUG, "%s: %s %s",__func__, zbx_result_string(ret), result_msg);
		}

		zbx_preprocessor_result_append(&buf, dep->itemid, dep->flags, dep->value_type, &value, error,
				&history_out, socket);

		zbx_variant_clear(&value);

		for (j = 0; j < step_results_num; j++)
			zbx_variant_clear(&results[j].value);

		zbx_vector_ptr_clear_ext(&history_out, (zbx_clean_func_t)zbx_preproc_op_history_free);
		zbx_free(error);
	}

	zbx_preprocessor_result_flush(&buf, socket);
	zbx_preprocessor_result_clear(&buf);

	zbx_preproc_cache_clear(&cache);

	zbx_free(results);

	worker_dep_request_clear(request);
	zbx_vector_ptr_destroy(&history_out);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle item value preprocessing request                           *
 *                                                                            *
 * Parameters: socket  - [IN] IPC socket                                      *
 *             message - [IN] packed preprocessing request                    *
 *             request - [IN/OUT] the unpacked preprocessing request          *
 *                                                                            *
 ******************************************************************************/
static void	worker_process_dep_request(zbx_ipc_socket_t *socket, zbx_ipc_message_t *message,
		zbx_preproc_dep_request_t *request)
{
	zbx_preprocessor_unpack_dep_task(&request->ts, &request->value, &request->deps_alloc, &request->deps,
			&request->deps_offset, message->data);

	worker_preprocess_dep_items(socket, request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle following item value preprocessing request                 *
 *                                                                            *
 * Parameters: socket  - [IN] IPC socket                                      *
 *             message - [IN] packed preprocessing request                    *
 *             request - [IN/OUT] the unpacked preprocessing request          *
 *                                                                            *
 ******************************************************************************/
static void	worker_process_dep_request_cont(zbx_ipc_socket_t *socket, zbx_ipc_message_t *message,
		zbx_preproc_dep_request_t *request)
{
	zbx_preprocessor_unpack_dep_task_cont(request->deps + request->deps_offset, &request->deps_offset,
			message->data);

	worker_preprocess_dep_items(socket, request);
}

ZBX_THREAD_ENTRY(preprocessing_worker_thread, args)
{
	pid_t				ppid;
	char				*error = NULL;
	zbx_ipc_socket_t		socket;
	zbx_ipc_message_t		message;
	zbx_preproc_dep_request_t	dep_request;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zbx_es_init(&es_engine);

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_PREPROCESSING, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to preprocessing service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ppid = getppid();
	zbx_ipc_socket_write(&socket, ZBX_IPC_PREPROCESSOR_WORKER, (unsigned char *)&ppid, sizeof(ppid));

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	memset(&dep_request, 0, sizeof(dep_request));
	zbx_variant_set_none(&dep_request.value);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED != zbx_ipc_socket_read(&socket, &message))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot read preprocessing service request");
			exit(EXIT_FAILURE);
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		zbx_update_env(zbx_time());

		switch (message.code)
		{
			case ZBX_IPC_PREPROCESSOR_REQUEST:
				worker_preprocess_value(&socket, &message);
				break;
			case ZBX_IPC_PREPROCESSOR_TEST_REQUEST:
				worker_test_value(&socket, &message);
				break;
			case ZBX_IPC_PREPROCESSOR_DEP_REQUEST:
				worker_dep_request_clear(&dep_request);
				worker_process_dep_request(&socket, &message, &dep_request);
				break;
			case ZBX_IPC_PREPROCESSOR_DEP_REQUEST_CONT:
				worker_process_dep_request_cont(&socket, &message, &dep_request);
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_es_destroy(&es_engine);
}
