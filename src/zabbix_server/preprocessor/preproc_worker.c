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
#include "zbxembed.h"

#include "sysinfo.h"
#include "preproc_worker.h"
#include "item_preproc.h"
#include "preproc_history.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

zbx_es_t	es_engine;

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
	zbx_variant_t			value;
	int				i, steps_num;
	char				*error = NULL;
	zbx_timespec_t			*ts;
	zbx_preproc_op_t		*steps;
	zbx_vector_ptr_t		history_in, history_out;
	zbx_preproc_op_history_t	*ophistory;

	zbx_vector_ptr_create(&history_in);
	zbx_vector_ptr_create(&history_out);

	zbx_preprocessor_unpack_task(&itemid, &value_type, &ts, &value, &history_in, &steps, &steps_num,
			message->data);

	for (i = 0; i < steps_num; i++)
	{
		zbx_preproc_op_t	*op = &steps[i];
		zbx_variant_t		history_value;
		zbx_timespec_t		history_ts;

		if (NULL != (ophistory = zbx_preproc_history_get_value(&history_in, i)))
		{
			history_value = ophistory->value;
			zbx_variant_set_none(&ophistory->value);
			history_ts = ophistory->ts;
		}
		else
		{
			zbx_variant_set_none(&history_value);
			history_ts.sec = 0;
			history_ts.ns = 0;
		}

		if (SUCCEED != zbx_item_preproc(i + 1, value_type, &value, ts, op, &history_value, &history_ts, &error))
		{
			zbx_variant_clear(&history_value);
			break;
		}

		if (ZBX_VARIANT_NONE != history_value.type)
		{
			/* the value is byte copied to history_out vector and doesn't have to be cleared */
			zbx_preproc_history_add_value(&history_out, i, &history_value, &history_ts);
		}

		if (ZBX_VARIANT_NONE == value.type)
			break;
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

	zbx_es_init(&es_engine);

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

	zbx_es_destroy(&es_engine);

	return 0;
}
