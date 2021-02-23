/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "log.h"
#include "zbxself.h"
#include "zbxipcservice.h"
#include "daemon.h"

#include "report_manager.h"
#include "report_protocol.h"


extern unsigned char	process_type, program_type;
extern int		server_num, process_num, CONFIG_REPORTWRITER_FORKS;

typedef struct
{
	/* the connected report writer client */
	zbx_ipc_client_t	*client;
}
zbx_rm_writer_t;


typedef struct
{
	/* the IPC service */
	zbx_ipc_service_t	ipc;

	/* the next writer index to be assigned to new IPC service clients */
	int			next_writer_index;

	/* report writer vector, created during manager initialization */
	zbx_vector_ptr_t	writers;
	zbx_queue_ptr_t		free_writers;

}
zbx_rm_t;

/******************************************************************************
 *                                                                            *
 * Function: rm_writer_free                                                   *
 *                                                                            *
 * Purpose: frees report writer                                               *
 *                                                                            *
 ******************************************************************************/
static void	rm_writer_free(zbx_rm_writer_t *writer)
{
	zbx_ipc_client_close(writer->client);
	zbx_free(writer);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_init                                                          *
 *                                                                            *
 * Purpose: initializes report manager                                        *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static int	rm_init(zbx_rm_t *manager, char **error)
{
	int		i, ret;
	zbx_rm_writer_t	*writer;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() writers:%d", __func__, CONFIG_REPORTWRITER_FORKS);

	if (FAIL == (ret = zbx_ipc_service_start(&manager->ipc, ZBX_IPC_SERVICE_REPORTER, error)))
		goto out;

	zbx_vector_ptr_create(&manager->writers);
	zbx_queue_ptr_create(&manager->free_writers);

	manager->next_writer_index = 0;

	for (i = 0; i < CONFIG_REPORTWRITER_FORKS; i++)
	{
		writer = (zbx_rm_writer_t *)zbx_malloc(NULL, sizeof(zbx_rm_writer_t));
		writer->client = NULL;
		zbx_vector_ptr_append(&manager->writers, writer);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_destroy                                                       *
 *                                                                            *
 * Purpose: destroys report manager                                           *
 *                                                                            *
 * Parameters: manager - [IN] the manager to destroy                          *
 *                                                                            *
 ******************************************************************************/
static void	rm_destroy(zbx_rm_t *manager)
{
	zbx_queue_ptr_destroy(&manager->free_writers);
	zbx_vector_ptr_clear_ext(&manager->writers, (zbx_mem_free_func_t)rm_writer_free);
	zbx_vector_ptr_destroy(&manager->writers);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_register_writer                                               *
 *                                                                            *
 * Purpose: registers report writer                                           *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected writer                            *
 *             message - [IN] the received message                            *
 *                                                                            *
 ******************************************************************************/
static void	rm_register_writer(zbx_rm_t *manager, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	zbx_rm_writer_t	*writer = NULL;
	pid_t		ppid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memcpy(&ppid, message->data, sizeof(ppid));

	if (ppid != getppid())
	{
		zbx_ipc_client_close(client);
		zabbix_log(LOG_LEVEL_DEBUG, "refusing connection from foreign process");
	}
	else
	{
		if (manager->next_writer_index == manager->writers.values_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		writer = (zbx_rm_writer_t *)manager->writers.values[manager->next_writer_index++];
		writer->client = client;

		zbx_queue_ptr_push(&manager->free_writers, writer);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	rm_read_reports(zbx_rm_t *manager)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(manager);
// TODO: sync report cache with database

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}


static void	rm_process_queue(zbx_rm_t *manager, int now)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(manager);
	ZBX_UNUSED(now);
// TODO: process reports

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_test_report                                                   *
 *                                                                            *
 * Purpose: test report                                                       *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected writer                            *
 *             message - [IN] the received message                            *
 *                                                                            *
 ******************************************************************************/
static void	rm_test_report(zbx_rm_t *manager, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	zbx_uint64_t		dashboardid, userid, writer_userid;
	zbx_vector_ptr_pair_t	params;
	int			i;
	unsigned char		*data = NULL;
	zbx_uint64_t		size;

	zbx_vector_ptr_pair_create(&params);

	report_deserialize_test_request(message->data, &dashboardid, &userid, &writer_userid, &params);

	// TODO: enqueue request

	size = report_serialize_test_response(&data, -1, "Not implemented.");

	zbx_ipc_client_send(client, ZBX_IPC_REPORTER_TEST_REPORT_RESPONSE, data, size);
	zbx_ipc_client_close(client);

	zbx_free(data);
	report_destroy_params(&params);
}

ZBX_THREAD_ENTRY(report_manager_thread, args)
{
#define	ZBX_STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
					/* once in STAT_INTERVAL seconds */
#define ZBX_SYNC_INTERVAL	60	/* report configuration refresh interval */

	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	double			time_stat, time_idle = 0, time_now, sec, time_sync;
	int			ret, sent_num = 0, failed_num = 0, delay;
	zbx_rm_t		manager;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	if (FAIL == rm_init(&manager, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize alert manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	/* initialize statistics */
	time_stat = zbx_time();
	time_sync = 0;

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

		if (ZBX_STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [sent %d, failed %d reports, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					sent_num, failed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			sent_num = 0;
			failed_num = 0;
		}

		if (time_now - time_sync >= ZBX_SYNC_INTERVAL)
		{
			rm_read_reports(&manager);
			time_sync = time_now;
		}

		sec = zbx_time();
		delay = (sec - time_now > 0.5 ? 0 : 1);
		time_now = sec;

		rm_process_queue(&manager, time(NULL));

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&manager.ipc, delay, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

		sec = zbx_time();
		zbx_update_env(sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_REPORTER_REGISTER:
					rm_register_writer(&manager, client, message);
					break;
				case ZBX_IPC_REPORTER_TEST_REPORT:
					rm_test_report(&manager, client, message);
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_service_close(&manager.ipc);
	rm_destroy(&manager);
}
