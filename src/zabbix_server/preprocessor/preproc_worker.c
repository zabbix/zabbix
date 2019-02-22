/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

#include "common.h"
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "preprocessing.h"

#include "sysinfo.h"
#include "preproc_worker.h"
#include "item_preproc.h"
#include "preproc_history.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/* successful preprocessing step execution result */
typedef struct
{
	zbx_variant_t	value;
	int		action;
}
zbx_preproc_result_t;

#define ZBX_PREPROC_VALUE_PREVIEW_LEN		100

/******************************************************************************
 *                                                                            *
 * Function: worker_format_value                                              *
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
	int		len, i;
	const char	*value_desc;

	value_desc = zbx_variant_value_desc(value);

	if (ZBX_PREPROC_VALUE_PREVIEW_LEN < zbx_strlen_utf8(value_desc))
	{
		char	*ptr;

		/* truncate value and append '...' */
		len = zbx_db_strlen_n(value_desc, ZBX_PREPROC_VALUE_PREVIEW_LEN - 3);
		*value_str = zbx_malloc(NULL, len + 4);
		memcpy(*value_str, value_desc, len);

		ptr = *value_str + len;

		for (i = 0; i < 3; i++)
			*ptr++ = '.';

		*ptr = '\0';
	}
	else
	{
		*value_str = zbx_malloc(NULL, (len = strlen(value_desc)) + 1);
		memcpy(*value_str, value_desc, len);
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
 * Function: worker_format_result                                             *
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
		*out = zbx_dsprintf(NULL, "%d. Failed%s: %s\n", step, actions[result->action], error);
}

/******************************************************************************
 *                                                                            *
 * Function: worker_format_error                                              *
 *                                                                            *
 * Purpose: formats preprocessing error message                               *
 *                                                                            *
 * Parameters: value        - [IN] the input value                            *
 *             results      - [IN] the preprocessing step results             *
 *             results_num  - [IN] the number of executed steps               *
 *             errmsg       - [IN] the error message of last executed step    *
 *             out          - [OUT] the formatted error message               *
 *                                                                            *
 ******************************************************************************/
static void	worker_format_error(const zbx_variant_t *value, zbx_preproc_result_t *results, int results_num,
		const char *errmsg, char **error)
{
	char			*value_str, *err_step;
	int			err_max = ITEM_ERROR_LEN, i, len;
	size_t			error_alloc = 512, error_offset = 0;
	zbx_vector_str_t	results_str;

	zbx_vector_str_create(&results_str);

	/* add header to error message */
	*error = zbx_malloc(NULL, error_alloc);
	worker_format_value(value, &value_str);
	zbx_snprintf_alloc(error, &error_alloc, &error_offset, "Preprocessing failed for: %s\n", value_str);
	zbx_free(value_str);

	err_max -= zbx_strlen_utf8(*error);
	/* reserve space for '...\n' in the case results from the first steps have to be truncated */
	err_max -= 4;

	/* format the last (failed) step */
	worker_format_result(results_num, &results[results_num - 1], errmsg, &err_step);
	err_max -= zbx_strlen_utf8(err_step);
	zbx_vector_str_append(&results_str, err_step);

	/* format the first steps */
	for (i = results_num - 2; i >= 0; i--)
	{
		worker_format_result(i + 1, &results[i], NULL, &err_step);
		len = zbx_strlen_utf8(err_step);

		if (0 > err_max - len)
		{
			zbx_free(err_step);
			break;
		}
		zbx_vector_str_append(&results_str, err_step);
		err_max -= len;
	}

	/* add steps to error message */

	if (results_str.values_num < results_num - 1)
		zbx_strcpy_alloc(error, &error_alloc, &error_offset, "...\n");

	for (i = results_str.values_num - 1; i >= 0; i--)
		zbx_strcpy_alloc(error, &error_alloc, &error_offset, results_str.values[i]);

	/* truncate formatted error if necessary */
	if (ITEM_ERROR_LEN < zbx_strlen_utf8(*error))
	{
		char	*ptr;

		ptr = (*error) + zbx_db_strlen_n(*error, ITEM_ERROR_LEN - 3);
		for (i = 0; i < 3; i++)
			*ptr++ = '.';
		*ptr = '\0';
	}

	zbx_rtrim(*error, ZBX_WHITESPACE);

	zbx_vector_str_clear_ext(&results_str, zbx_str_free);
	zbx_vector_str_destroy(&results_str);
}

/******************************************************************************
 *                                                                            *
 * Function: worker_item_preproc_execute                                      *
 *                                                                            *
 * Purpose: execute preprocessing steps                                       *
 *                                                                            *
 * Parameters: value_type    - [IN] the item value type                       *
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
static int	worker_item_preproc_execute(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_preproc_op_t *steps, int steps_num, zbx_vector_ptr_t *history_in, zbx_vector_ptr_t *history_out,
		zbx_preproc_result_t *results, int *results_num, char **error)
{
	int	i, ret = SUCCEED, action;

	for (i = 0; i < steps_num; i++)
	{
		zbx_preproc_op_t		*op = &steps[i];
		zbx_variant_t			history_value;
		zbx_timespec_t			history_ts;
		const zbx_preproc_op_history_t	*ophistory;

		if (NULL != (ophistory = zbx_preproc_history_get_value(history_in, op->type)))
		{
			history_value = ophistory->value;
			history_ts = ophistory->ts;
		}
		else
		{
			zbx_variant_set_none(&history_value);
			history_ts.sec = 0;
			history_ts.ns = 0;
		}

		ret = zbx_item_preproc(value_type, value, ts, op, &history_value, &history_ts, &action, error);

		/* store result history */
		results[i].action = action;

		/* value is stored to report previous step results a step fails, */
		/* which means it can be omitted for the last step               */
		if (i != steps_num - 1)
			zbx_variant_set_variant(&results[i].value, value);
		else
			zbx_variant_set_none(&results[i].value);

		if (SUCCEED != ret)
			break;

		if (ZBX_VARIANT_NONE != history_value.type)
			zbx_preproc_history_set_value(history_out, op->type, &history_value, &history_ts);

		zbx_variant_clear(&history_value);

		if (ZBX_VARIANT_NONE == value->type)
			break;
	}

	*results_num = (i == steps_num ? i : i + 1);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: worker_preprocess_value                                          *
 *                                                                            *
 * Purpose: handle item value preprocessing task                              *
 *                                                                            *
 * Parameters: socket  - [IN] IPC socket                                      *
 *             message - [IN] packed preprocessing task                       *
 *                                                                            *
 ******************************************************************************/
static void	worker_preprocess_value(zbx_ipc_socket_t *socket, zbx_ipc_message_t *message)
{
	zbx_uint32_t			size = 0;
	unsigned char			*data = NULL, value_type;
	zbx_uint64_t			itemid;
	zbx_variant_t			value, value_start;
	int				i, steps_num, results_num;
	char				*error = NULL, *errmsg = NULL;
	zbx_timespec_t			*ts;
	zbx_preproc_op_t		*steps;
	zbx_vector_ptr_t		history_in, history_out;
	zbx_preproc_result_t		*results;

	zbx_vector_ptr_create(&history_in);
	zbx_vector_ptr_create(&history_out);

	zbx_preprocessor_unpack_task(&itemid, &value_type, &ts, &value, &history_in, &steps, &steps_num,
			message->data);


	zbx_variant_set_variant(&value_start, &value);
	results = (zbx_preproc_result_t *)zbx_malloc(NULL, sizeof(zbx_preproc_result_t) * steps_num);

	if (FAIL == worker_item_preproc_execute(value_type, &value, ts, steps, steps_num, &history_in, &history_out,
			results, &results_num, &error) && 0 != results_num)
	{
		int action = results[results_num - 1].action;

		if (ZBX_PREPROC_FAIL_SET_ERROR != action && ZBX_PREPROC_FAIL_FORCE_ERROR != action)
		{
			worker_format_error(&value_start, results, results_num, error, &errmsg);
			zbx_free(error);
			error = errmsg;
		}
	}

	size = zbx_preprocessor_pack_result(&data, &value, &history_out, error);
	zbx_variant_clear(&value);
	zbx_free(error);
	zbx_free(ts);
	zbx_free(steps);

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

ZBX_THREAD_ENTRY(preprocessing_worker_thread, args)
{
	pid_t			ppid;
	char			*error = NULL;
	zbx_ipc_socket_t	socket;
	zbx_ipc_message_t	message;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_PREPROCESSING, 10, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to preprocessing service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ppid = getppid();
	zbx_ipc_socket_write(&socket, ZBX_IPC_PREPROCESSOR_WORKER, (unsigned char *)&ppid, sizeof(ppid));

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	for (;;)
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
		}

		zbx_ipc_message_clean(&message);
	}

	return 0;
}
