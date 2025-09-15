/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxsupervisor.h"
#include "zbxsupervisor_client.h"
#include "zbxthreads.h"
#include "zbxcommon.h"
#include "zbxrtc.h"
#include "zbx_rtc_constants.h"
#include "zbxalgo.h"
#include "zbxserialize.h"
#include "zbxipcservice.h"
#include "zbxtimekeeper.h"
#include "zbxself.h"

ZBX_VECTOR_IMPL(proc_info, zbx_proc_info_t)

typedef struct
{
	zbx_ipc_client_t	*client;
	int			runlevel;
}
zbx_runlevel_sub_t;

ZBX_VECTOR_DECL(runlevel_sub, zbx_runlevel_sub_t)
ZBX_VECTOR_IMPL(runlevel_sub, zbx_runlevel_sub_t)

typedef struct
{
	int	pending_external_num;
	int	pending_local_num;
}
zbx_proc_startup_state_t;

typedef struct
{
	int			proc_index;
	int			runlevel;
	zbx_proc_owner_t	owner;
}
zbx_runlevel_index_t;

static zbx_hash_t	runlevel_index_hash(const void *d)
{
	const zbx_runlevel_index_t        *ri = (const zbx_runlevel_index_t *)d;

	zbx_uint64_t	index = (zbx_uint64_t)ri->proc_index;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&index);
}

static int	runlevel_index_compare(const void *d1, const void *d2)
{
	const zbx_runlevel_index_t        *ri1 = (const zbx_runlevel_index_t *)d1;
	const zbx_runlevel_index_t        *ri2 = (const zbx_runlevel_index_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ri1->proc_index, ri2->proc_index);
	return 0;
}

typedef struct
{
	int				runlevel;
	zbx_proc_startup_state_t	states[ZBX_RUNLEVEL_DEFAULT + 1];
	zbx_hashset_t			runlevel_index;

	zbx_vector_runlevel_sub_t	runlevel_subs;
}
zbx_supervisor_t;

/******************************************************************************
 *                                                                            *
 * Purpose: create process startup configuration organized by runlevels       *
 *                                                                            *
 * Parameters: threads_num                      - [IN] total number of        *
 *                                                     threads (processes)    *
 *             get_process_info_by_thread_cb    - [IN] callback function to   *
 *                                                     get process info by    *
 *                                                     thread index           *
 *                                                                            *
 * Return value: allocated process startup configuration                      *
 *                                                                            *
 ******************************************************************************/

zbx_proc_startup_t	*zbx_proc_startup_create(int threads_num,
		zbx_get_process_info_by_thread_f get_process_info_by_thread_cb)
{
	zbx_proc_startup_t	*runlevels;

	runlevels = (zbx_proc_startup_t *)zbx_malloc(NULL, sizeof(zbx_proc_startup_t) * (ZBX_RUNLEVEL_DEFAULT + 1));

	for (int i = 0; i <= ZBX_RUNLEVEL_DEFAULT; i++)
		zbx_vector_proc_info_create(&runlevels[i].processes);

	for (int i = 0; i < threads_num; i++)
	{
		zbx_proc_info_t	info = {.index = i + 1, .owner = PROCESS_OWNER_UNKNOWN};
		int		runlevel;

		if (FAIL == get_process_info_by_thread_cb(i + 1, &info.type, &info.num))
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("process index %d exceeds maximum", i + 1);
			exit(EXIT_FAILURE);
		}

		zbx_supervisor_get_process_info(info.type, &info.owner, &runlevel);

		if (ZBX_RUNLEVEL_DEFAULT < runlevel)
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("process runlevel %d exceeds maximum", runlevel);
			exit(EXIT_FAILURE);
		}

		zbx_vector_proc_info_append(&runlevels[runlevel].processes, info);
	}

	return runlevels;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free process startup configuration                                *
 *                                                                            *
 ******************************************************************************/
void        zbx_proc_startup_free(zbx_proc_startup_t *runlevels)
{
	for (int i = 0; i <= ZBX_RUNLEVEL_DEFAULT; i++)
		zbx_vector_proc_info_destroy(&runlevels[i].processes);

	zbx_free(runlevels);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update supervisor runlevel based on pending process counts        *
 *                                                                            *
 * Parameters: sv - [IN/OUT] supervisor instance                              *
 *                                                                            *
 * Return value: SUCCEED - runlevel was updated                               *
 *               FAIL    - runlevel otherwise                                 *
 *                                                                            *
 ******************************************************************************/
static int	supervisor_update_runlevel(zbx_supervisor_t *sv)
{
	int	i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() runlevel:%d", __func__, sv->runlevel);

	for (i = sv->runlevel; i <= ZBX_RUNLEVEL_DEFAULT; i++)
	{
		if (0 != sv->states[i].pending_external_num || 0 != sv->states[i].pending_local_num)
			break;
	}


	if (i > sv->runlevel)
	{
		sv->runlevel = i;
		ret = SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() runlevel:%d", __func__, sv->runlevel);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize supervisor instance with process startup configuration *
 *                                                                            *
 * Parameters: sv        - [OUT] supervisor instance to initialize            *
 *             runlevels - [IN]  process startup configuration                *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_init(zbx_supervisor_t *sv, const zbx_proc_startup_t *runlevels)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create(&sv->runlevel_index, 0, runlevel_index_hash, runlevel_index_compare);
	zbx_vector_runlevel_sub_create(&sv->runlevel_subs);
	memset(sv->states, 0, sizeof(sv->states));
	sv->runlevel = 0;

	for (int i = 0; i <= ZBX_RUNLEVEL_DEFAULT; i++)
	{
		for (int j = 0; j < runlevels[i].processes.values_num; j++)
		{
			zbx_proc_info_t        *proc_info = &runlevels[i].processes.values[j];

			if (PROCESS_OWNER_MAIN == proc_info->owner)
				sv->states[i].pending_external_num++;
			else if (PROCESS_OWNER_SUPERVISOR == proc_info->owner)
				sv->states[i].pending_local_num++;

			zbx_runlevel_index_t	ri_local = {
					.proc_index = proc_info->index,
					.runlevel = i,
					.owner = proc_info->owner
			};

			zbx_hashset_insert(&sv->runlevel_index, &ri_local, sizeof(ri_local));
		}
	}

	(void)supervisor_update_runlevel(sv);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() runlevel:%d", __func__, sv->runlevel);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize supervisor instance with process startup configuration *
 *                                                                            *
 * Parameters: sv        - [OUT] supervisor instance to initialize            *
 *             runlevels - [IN]  process startup configuration                *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_clear(zbx_supervisor_t *sv)
{
	for (int i = 0; i < sv->runlevel_subs.values_num; i++)
		zbx_ipc_client_release(sv->runlevel_subs.values[i].client);

	zbx_vector_runlevel_sub_destroy(&sv->runlevel_subs);
	zbx_hashset_destroy(&sv->runlevel_index);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify subscribers when their requested runlevel                  *
 *          is reached                                                        *
 *                                                                            *
 * Parameters: sv - [IN] supervisor instance                                  *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_notify_subs(zbx_supervisor_t *sv)
{
	for (int i = 0; i < sv->runlevel_subs.values_num;)
	{
		zbx_runlevel_sub_t	*sub = &sv->runlevel_subs.values[i];
		if (sub->runlevel <= sv->runlevel)
		{
			zbx_ipc_client_send(sub->client, ZBX_SUPERVISOR_RUNLEVEL_OK, NULL, 0);
			zbx_ipc_client_release(sub->client);

			zbx_vector_runlevel_sub_remove(&sv->runlevel_subs, i);
			continue;
		}

		i++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle process startup notification and update supervisor state   *
 *                                                                            *
 * Parameters: sv   - [IN] supervisor instance                                *
 *             data - [IN]     serialized process index                       *
 *                                                                            *
 * Return value: SUCCEED - runlevel was updated                               *
 *               FAIL    - process not found or runlevel not changed          *
 *                                                                            *
 ******************************************************************************/
static int	supervisor_handle_proc_start(zbx_supervisor_t *sv, const unsigned char *data)
{
	int			ret, proc_index;
	zbx_runlevel_index_t	ri_local, *ri;

	(void)zbx_deserialize_int(data, &proc_index);

	ri_local.proc_index = proc_index;
	if (NULL == (ri = (zbx_runlevel_index_t *)zbx_hashset_search(&sv->runlevel_index, &ri_local)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "process index %d not found in runlevel cache", proc_index);
		return FAIL;
	}

	if (PROCESS_OWNER_SUPERVISOR == ri->owner)
		sv->states[ri->runlevel].pending_local_num--;
	else if (PROCESS_OWNER_MAIN == ri->owner)
		sv->states[ri->runlevel].pending_external_num--;

	if (sv->runlevel != ri->runlevel)
		return FAIL;

	if (SUCCEED == (ret = supervisor_update_runlevel(sv)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "supervisor runlevel changed to %d", sv->runlevel);
		supervisor_notify_subs(sv);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle runlevel subscription request from client                  *
 *                                                                            *
 * Parameters: sv     - [IN] supervisor instance                              *
 *             client - [IN]     IPC client requesting runlevel subscription  *
 *             data   - [IN]     serialized runlevel to wait for              *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_handle_runlevel_sub(zbx_supervisor_t *sv, zbx_ipc_client_t *client,
		const unsigned char *data)
{
	int	runlevel;

	(void)zbx_deserialize_int(data, &runlevel);

	if (runlevel <= sv->runlevel)
	{
		zbx_ipc_client_send(client, ZBX_SUPERVISOR_RUNLEVEL_OK, NULL, 0);
		return;
	}

	zbx_ipc_client_addref(client);

	zbx_runlevel_sub_t	sub = {
			.client = client,
			.runlevel = runlevel
	};

	zbx_vector_runlevel_sub_append(&sv->runlevel_subs, sub);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process entry function                                            *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(zbx_supervisor_thread, args)
{
#define DEFAULT_SLEEP_TIME	1

	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num,
					process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_thread_supervisor_args_t	*local_args = (zbx_thread_supervisor_args_t *)((zbx_thread_args_t *)args)->args;
	zbx_proc_startup_t		*runlevels = local_args->runlevels;
	zbx_ipc_service_t		service;
	char				*error = NULL;
	zbx_uint32_t			rtc_msgs[] = {ZBX_RTC_SHUTDOWN};
	zbx_timespec_t			sleeptime = {1, 0};
	zbx_supervisor_t		sv;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_SUPERVISOR, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start supervisor service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_rtc_subscribe_service(ZBX_PROCESS_TYPE_SUPERVISOR, 0, rtc_msgs, ARRSIZE(rtc_msgs),
			local_args->config_timeout, ZBX_IPC_SERVICE_SUPERVISOR);

	supervisor_init(&sv, runlevels);

	zbx_supervisor_set_process_running(server_num);

	zbx_setproctitle("%s #%d started at runlevel %d", get_process_type_string(process_type), process_num,
			sv.runlevel);

	while (ZBX_IS_RUNNING())
	{
		zbx_ipc_client_t	*client;
		zbx_ipc_message_t	*message;

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		zbx_ipc_service_recv(&service, &sleeptime, &client, &message);

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_SUPERVISOR_PROC_RUNNING:
					if (SUCCEED == supervisor_handle_proc_start(&sv, message->data))
					{
						zbx_setproctitle("%s #%d running at runlevel %d",
								get_process_type_string(process_type), process_num,
								sv.runlevel);
					}
					break;
				case ZBX_SUPERVISOR_RUNLEVEL_WAIT:
					supervisor_handle_runlevel_sub(&sv, client, message->data);
					break;
				case ZBX_RTC_SHUTDOWN:
					goto out;
				default:
					continue;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

out:
	supervisor_clear(&sv);

	zbx_ipc_service_close(&service);
	zbx_proc_startup_free(runlevels);

	exit(EXIT_SUCCESS);

#undef DEFAULT_SLEEP_TIME
}
