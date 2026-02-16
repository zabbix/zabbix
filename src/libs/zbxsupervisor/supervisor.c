/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxstr.h"
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
#include "zbxlog.h"
#include "zbxnix.h"
#include "zbxtime.h"
#include "zbxtypes.h"
#include "zbxdb.h"
#include "zbxprof.h"
#include "zbxresolver.h"

#ifdef HAVE_NETSNMP
#include "zbxsnmp.h"
#endif

#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#	include <libxml/parser.h>
#endif

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
	const zbx_runlevel_index_t	*ri = (const zbx_runlevel_index_t *)d;
	zbx_uint64_t			index = (zbx_uint64_t)ri->proc_index;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&index);
}

static int	runlevel_index_compare(const void *d1, const void *d2)
{
	const zbx_runlevel_index_t	*ri1 = (const zbx_runlevel_index_t *)d1;
	const zbx_runlevel_index_t	*ri2 = (const zbx_runlevel_index_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ri1->proc_index, ri2->proc_index);
	return 0;
}

typedef struct
{
	pthread_t				handle;
	zbx_log_component_t			logger;
	_Atomic zbx_supervisor_runstate_t	runstate;
}
zbx_supervisor_unit_t;

typedef struct
{
	zbx_supervisor_unit_t	*units;
	int			unit_num;
	unsigned char		process_type;
}
zbx_supervisor_unit_set_t;

typedef struct
{
	int				runlevel;
	zbx_proc_startup_state_t	states[ZBX_RUNLEVEL_DEFAULT + 1];
	zbx_hashset_t			runlevel_index;

	zbx_vector_runlevel_sub_t	runlevel_subs;

	const zbx_proc_startup_t	*runlevels;
	zbx_supervisor_unit_set_t	unitsets[ZBX_PROCESS_TYPE_COUNT];
}
zbx_supervisor_t;

/******************************************************************************
 *                                                                            *
 * Purpose: get process owner and runlevel information for specified process  *
 *          type                                                              *
 *                                                                            *
 * Parameters: process_type - [IN]  process type                              *
 *             owner        - [OUT] process owner                             *
 *             runlevel     - [OUT] process runlevel                          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_supervisor_get_process_info(int process_type, zbx_proc_owner_t *owner, int *runlevel)
{
	*owner = PROCESS_OWNER_MAIN;
	*runlevel = ZBX_RUNLEVEL_DEFAULT;

	switch (process_type)
	{
		case ZBX_PROCESS_TYPE_POLLER:
			break;

		case ZBX_PROCESS_TYPE_UNREACHABLE:
			break;

		case ZBX_PROCESS_TYPE_IPMIPOLLER:
			break;

		case ZBX_PROCESS_TYPE_PINGER:
			break;

		case ZBX_PROCESS_TYPE_JAVAPOLLER:
			break;

		case ZBX_PROCESS_TYPE_HTTPPOLLER:
			break;

		case ZBX_PROCESS_TYPE_TRAPPER:
			break;

		case ZBX_PROCESS_TYPE_SNMPTRAPPER:
			break;

		case ZBX_PROCESS_TYPE_PROXYPOLLER:
			break;

		case ZBX_PROCESS_TYPE_ESCALATOR:
			break;

		case ZBX_PROCESS_TYPE_HISTSYNCER:
			break;

		case ZBX_PROCESS_TYPE_DISCOVERER:
			*owner = PROCESS_OWNER_UNKNOWN;
			*runlevel = ZBX_RUNLEVEL_UNKNOWN;
			break;

		case ZBX_PROCESS_TYPE_ALERTER:
			break;

		case ZBX_PROCESS_TYPE_TIMER:
			break;

		case ZBX_PROCESS_TYPE_HOUSEKEEPER:
			break;

		case ZBX_PROCESS_TYPE_DATASENDER:
			break;

		case ZBX_PROCESS_TYPE_CONFSYNCER:
			*owner = PROCESS_OWNER_SUPERVISOR;
			*runlevel = ZBX_RUNLEVEL_CACHESYNC;
			break;

		case ZBX_PROCESS_TYPE_SELFMON:
			break;

		case ZBX_PROCESS_TYPE_VMWARE:
			break;

		case ZBX_PROCESS_TYPE_COLLECTOR:
			break;

		case ZBX_PROCESS_TYPE_LISTENER:
			break;

		case ZBX_PROCESS_TYPE_ACTIVE_CHECKS:
			break;

		case ZBX_PROCESS_TYPE_TASKMANAGER:
			*runlevel = ZBX_RUNLEVEL_TASKMANAGER;
			break;

		case ZBX_PROCESS_TYPE_IPMIMANAGER:
			break;

		case ZBX_PROCESS_TYPE_ALERTMANAGER:
			break;

		case ZBX_PROCESS_TYPE_PREPROCMAN:
			*owner = PROCESS_OWNER_SUPERVISOR;
			break;

		case ZBX_PROCESS_TYPE_PREPROCESSOR:
			*owner = PROCESS_OWNER_UNKNOWN;
			*runlevel = ZBX_RUNLEVEL_UNKNOWN;
			break;

		case ZBX_PROCESS_TYPE_LLDMANAGER:
			break;

		case ZBX_PROCESS_TYPE_LLDWORKER:
			break;

		case ZBX_PROCESS_TYPE_ALERTSYNCER:
			break;

		case ZBX_PROCESS_TYPE_HISTORYPOLLER:
			break;

		case ZBX_PROCESS_TYPE_AVAILMAN:
			break;

		case ZBX_PROCESS_TYPE_REPORTMANAGER:
			break;

		case ZBX_PROCESS_TYPE_REPORTWRITER:
			break;

		case ZBX_PROCESS_TYPE_SERVICEMAN:
			*runlevel = ZBX_RUNLEVEL_CACHESYNC;
			break;

		case ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER:
			break;

		case ZBX_PROCESS_TYPE_ODBCPOLLER:
			break;

		case ZBX_PROCESS_TYPE_CONNECTORMANAGER:
			break;

		case ZBX_PROCESS_TYPE_CONNECTORWORKER:
			break;

		case ZBX_PROCESS_TYPE_DISCOVERYMANAGER:
			break;

		case ZBX_PROCESS_TYPE_HTTPAGENT_POLLER:
			break;

		case ZBX_PROCESS_TYPE_AGENT_POLLER:
			break;

		case ZBX_PROCESS_TYPE_SNMP_POLLER:
			break;

		case ZBX_PROCESS_TYPE_INTERNAL_POLLER:
			break;

		case ZBX_PROCESS_TYPE_DBCONFIGWORKER:
			break;

		case ZBX_PROCESS_TYPE_PG_MANAGER:
			break;

		case ZBX_PROCESS_TYPE_BROWSERPOLLER:
			break;

		case ZBX_PROCESS_TYPE_HA_MANAGER:
			*owner = PROCESS_OWNER_UNKNOWN;
			*runlevel = ZBX_RUNLEVEL_UNKNOWN;
			break;

		case ZBX_PROCESS_TYPE_SUPERVISOR:
			*runlevel = ZBX_RUNLEVEL_SUPERVISOR;
			break;

		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("Unknown process type: %d", process_type);
			zbx_exit(EXIT_FAILURE);
			break;
		}

}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate total count of processes with direct ownership          *
 *                                                                            *
 * Parameters: config_forks - [IN] array of configured process counts         *
 *                                                                            *
 * Return value: total process count                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_supervisor_get_process_count(const int *config_forks)
{
	int	process_count = 0;

	for (int i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		int			runlevel;
		zbx_proc_owner_t	owner;

		zbx_supervisor_get_process_info(i, &owner, &runlevel);

		if (PROCESS_OWNER_UNKNOWN != owner)
			process_count += config_forks[i];
	}

	return process_count;
}

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
			zbx_exit(EXIT_FAILURE);
		}

		zbx_supervisor_get_process_info(info.type, &info.owner, &runlevel);

		if (ZBX_RUNLEVEL_DEFAULT < runlevel)
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("process runlevel %d exceeds maximum", runlevel);
			zbx_exit(EXIT_FAILURE);
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
	sv->runlevel = 0;
	sv->runlevels = runlevels;
	memset(sv->states, 0, sizeof(sv->states));
	memset(sv->unitsets, 0, sizeof(sv->unitsets));

	for (int i = 0; i <= ZBX_RUNLEVEL_DEFAULT; i++)
	{
		for (int j = 0; j < runlevels[i].processes.values_num; j++)
		{
			zbx_proc_info_t        *proc_info = &runlevels[i].processes.values[j];

			if (PROCESS_OWNER_MAIN == proc_info->owner)
			{
				sv->states[i].pending_external_num++;
			}
			else if (PROCESS_OWNER_SUPERVISOR == proc_info->owner)
			{
				sv->states[i].pending_local_num++;
				sv->unitsets[proc_info->type].unit_num++;
			}

			zbx_runlevel_index_t	ri_local = {
					.proc_index = proc_info->index,
					.runlevel = i,
					.owner = proc_info->owner
			};

			zbx_hashset_insert(&sv->runlevel_index, &ri_local, sizeof(ri_local));
		}
	}

	for (int i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		zbx_supervisor_unit_set_t	*set = &sv->unitsets[i];

		if (0 == set->unit_num)
			continue;

		set->units = (zbx_supervisor_unit_t *)zbx_malloc(NULL, sizeof(zbx_supervisor_unit_t) *
				(size_t)set->unit_num);
		memset(set->units, 0, sizeof(zbx_supervisor_unit_t) * (size_t)set->unit_num);

		set->process_type = i;
	}

	(void)supervisor_update_runlevel(sv);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() runlevel:%d", __func__, sv->runlevel);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize supervisor instance with process startup configuration *
 *                                                                            *
 * Parameters: sv        - [OUT] supervisor instance to initialize            *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_clear(zbx_supervisor_t *sv)
{
	for (int i = 0; i < sv->runlevel_subs.values_num; i++)
		zbx_ipc_client_release(sv->runlevel_subs.values[i].client);

	for (size_t i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		zbx_supervisor_unit_set_t	*set = &sv->unitsets[i];

		for (int j = 0; j < set->unit_num; j++)
		{
			zbx_supervisor_unit_t	*unit = &set->units[j];

			if (UNIT_IDLE == unit->runstate)
				continue;

			void	*retval;
			int	err;

			if (0 != (err = pthread_join(unit->handle, &retval)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Failed to join process #%lu thread: %s", i + 1,
						zbx_strerror(err));
			}
			else if (EXIT_FAILURE == (zbx_int64_t)retval)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Thread #%lu (%s) terminated with failure", i + 1,
						ZBX_NULL2EMPTY_STR(unit->logger.name));
			}
		}

		zbx_free(set->units);
	}

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
 * Purpose: retrieve supervisor unit by process type and number               *
 *                                                                            *
 * Parameters: sv   - [IN] supervisor instance                                *
 *             type - [IN] process type                                       *
 *             num  - [IN] process number (1-based)                           *
 *                                                                            *
 * Return value: supervisor unit                                              *
 *                                                                            *
 ******************************************************************************/
static zbx_supervisor_unit_t	*supervisor_get_unit(zbx_supervisor_t *sv, unsigned char type, int num)
{
	if (ZBX_PROCESS_TYPE_COUNT <= type)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("Unknown process type: %d", type);
		zbx_exit(EXIT_FAILURE);
	}

	if (0 >= num || num > sv->unitsets[type].unit_num)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("Process number %d, is outside configured range [%d, %d] for process of"
				" type %d", num, 1, sv->unitsets[type].unit_num, type);
		zbx_exit(EXIT_FAILURE);
	}

	return &sv->unitsets[type].units[num - 1];
}

/******************************************************************************
 *                                                                            *
 * Purpose: start a unit thread with specified entry function and arguments   *
 *                                                                            *
 * Parameters: unit         - [IN] supervisor unit to start                   *
 *             thread_entry - [IN] thread entry function                      *
 *             args         - [IN] thread arguments structure                 *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_unit_start(zbx_supervisor_unit_t *unit, void *(*thread_entry)(void *),
		const zbx_thread_args_t *args)
{
	int				err;
	pthread_attr_t			attr;
	zbx_supervisor_unit_args_t	*unit_args;

	unit_args = (zbx_supervisor_unit_args_t *)zbx_malloc(NULL, sizeof(zbx_supervisor_unit_args_t));
	unit_args->args = *args;
	unit_args->logger = &unit->logger;
	unit_args->runstate = &unit->runstate;
	unit->runstate = UNIT_RUNNING;

	zbx_pthread_init_attr(&attr);
	if (0 != (err = pthread_create(&unit->handle, &attr, thread_entry, (void *)unit_args)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create thread: %s", zbx_strerror(err));
		zbx_exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: start units threads to current runlevel                           *
 *                                                                            *
 * Parameters: sv            - [IN] supervisor instance                       *
 *             args          - [IN] supervisor thread arguments               *
 *             runlevel_last - [IN] previous runlevel                         *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_start_units(zbx_supervisor_t *sv, const zbx_thread_supervisor_args_t *args,
		int runlevel_last)
{
	zbx_thread_args_t	thread_args;

	thread_args.info.program_type = args->program_type;

	for (int i = runlevel_last + 1; i <= sv->runlevel; i++)
	{
		const zbx_vector_proc_info_t	*processes = &sv->runlevels[i].processes;

		for (int j = 0; j < processes->values_num; j++)
		{
			zbx_proc_info_t	*info = &processes->values[j];

			if (PROCESS_OWNER_SUPERVISOR != info->owner)
				continue;

			if (NULL == args->unit_defs[info->type].entry)
			{
				zabbix_log(LOG_LEVEL_CRIT, "no entry function specified for process type %d",
						info->type);
				zbx_set_exiting_with_fail();
				return;
			}

			zbx_supervisor_unit_t	*unit = supervisor_get_unit(sv, info->type, info->num);

			thread_args.info.process_type = info->type;
			thread_args.info.process_num = info->num;
			thread_args.info.server_num = info->index;
			thread_args.args = args->unit_defs[info->type].args;

			supervisor_unit_start(unit, args->unit_defs[info->type].entry, &thread_args);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: change log level for a unit set representing one type of Zabbix   *
 *          processes                                                         *
 *                                                                            *
 * Parameters: set       - [IN] supervisor unit set                           *
 *             proc_num  - [IN] process number (0 for all processes)          *
 *             direction - [IN] log level change direction (1 increase,       *
 *                              -1 decrease)                                  *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_unitset_change_loglevel(zbx_supervisor_unit_set_t *set, int proc_num, int direction)
{
	if (0 == proc_num)
	{
		for (int i = 0; i < set->unit_num; i++)
			zbx_change_component_log_level(&set->units[i].logger, direction);

		return;
	}

	if (0 > proc_num || proc_num > set->unit_num)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "Cannot change log level for %s #%d:"
				" no such instance", get_process_type_string(set->process_type), proc_num);
	}
	else
		zbx_change_component_log_level(&set->units[proc_num - 1].logger, direction);
}

/******************************************************************************
 *                                                                            *
 * Purpose: change supervisor process log level                               *
 *                                                                            *
 * Parameters: direction - [IN] positive to increase, negative to decrease    *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_change_own_loglevel(int direction)
{
	if (0 < direction)
		zabbix_increase_log_level();
	else
		zabbix_decrease_log_level();
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle log level change request for supervisor managed threads    *
 *                                                                            *
 * Parameters: sv        - [IN] supervisor instance                           *
 *             direction - [IN] log level change direction (1 increase,       *
 *                              -1 decrease)                                  *
 *             data      - [IN] serialized command target specification       *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_change_loglevel(zbx_supervisor_t *sv, int direction, const unsigned char *data)
{
	char	*error = NULL;
	pid_t	pid;
	int	proc_type, proc_num;

	if (SUCCEED != zbx_rtc_get_command_target((const char *)data, &pid, &proc_type, &proc_num, NULL, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot change log level: %s", error);
		zbx_free(error);
		return;
	}

	if (0 != pid)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot change log level for thread by pid");
		return;
	}

	if (ZBX_PROCESS_TYPE_SUPERVISOR == proc_type)
	{
		supervisor_change_own_loglevel(direction);
		return;
	}

	if (ZBX_PROCESS_TYPE_UNKNOWN == proc_type)
	{
		supervisor_change_own_loglevel(direction);

		for (int i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
		{
			zbx_supervisor_unit_set_t	*set = &sv->unitsets[i];

			if (0 == set->unit_num)
				continue;

			supervisor_unitset_change_loglevel(set, proc_num, direction);
		}
	}
	else
	{
		if (0 > proc_type || ZBX_PROCESS_TYPE_COUNT <= proc_type)
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("attempting to change log level of unknown process type: %d",
					proc_type);
			return;
		}

		supervisor_unitset_change_loglevel(&sv->unitsets[proc_type], proc_num, direction);
	}
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
static void	sypervisor_get_activities(zbx_ipc_client_t *client)
{
	char	*activities;

	activities = zbx_supervisor_get_activities();

	zbx_ipc_client_send(client, ZBX_SUPERVISOR_GET_ACTIVITIES, (unsigned char *)activities, strlen(activities) + 1);
	zbx_free(activities);
}

static void	supervisor_init_libraries(const char *progname)
{
#ifndef HAVE_NETSNMP
	ZBX_UNUSED(progname);
#endif

#ifdef HAVE_LIBXML2
	xmlInitParser();
#endif

#ifdef HAVE_LIBCURL
	curl_global_init(CURL_GLOBAL_DEFAULT);
#endif

#ifdef HAVE_NETSNMP
	zbx_snmp_init(progname);
#endif

#ifdef HAVE_ARES_QUERY_CACHE
	zbx_ares_library_init();
#endif
}

static void	supervisor_clear_libraries(const char *progname)
{
#ifndef HAVE_NETSNMP
	ZBX_UNUSED(progname);
#endif

#ifdef HAVE_LIBXML2
	xmlCleanupParser();
#endif

#ifdef HAVE_LIBCURL
	curl_global_cleanup();
#endif

#ifdef HAVE_NETSNMP
	zbx_snmp_clear(progname);
#endif

#ifdef HAVE_ARES_QUERY_CACHE
	ares_library_cleanup();
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: throttled log redirect sync with log rotation                     *
 *                                                                            *
 * Parameters: time_now - [IN] current timestamp                              *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_handle_log(double time_now)
{
	static double	time_update = 0;

	/* handle /etc/resolv.conf update and log rotate less often than once a second */
	if (1.0 < time_now - time_update)
	{
		time_update = time_now;
		zbx_handle_log();
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: set run state for all units                                       *
 *                                                                            *
 ******************************************************************************/
static void	supervisor_set_unit_runstate(zbx_supervisor_t *sv, zbx_supervisor_runstate_t runstate)
{
	for (int i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		zbx_supervisor_unit_set_t	*set = &sv->unitsets[i];

		for (int j = 0; j < set->unit_num; j++)
			set->units[j].runstate = runstate;
	}
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
	int				server_num = info->server_num,
					process_num = info->process_num;
	unsigned char			process_type = info->process_type;
	zbx_thread_supervisor_args_t	*local_args = (zbx_thread_supervisor_args_t *)((zbx_thread_args_t *)args)->args;
	zbx_proc_startup_t		*runlevels = local_args->runlevels;
	zbx_ipc_service_t		service;
	char				*error = NULL;
	zbx_uint32_t			rtc_msgs[] = {ZBX_RTC_LOG_LEVEL_INCREASE, ZBX_RTC_LOG_LEVEL_DECREASE};
	zbx_timespec_t			sleeptime = {1, 0};
	zbx_supervisor_t		sv;
	int				runlevel_last = 0;
	zbx_dbconn_pool_t		*dbpool;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	supervisor_init_libraries(get_program_type_string(info->program_type));

	zbx_supervisor_worklog_init();

	if (NULL == (dbpool = zbx_dbconn_pool_create(&error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create database connection pool: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_SUPERVISOR, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start supervisor service: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	supervisor_init(&sv, runlevels);

	zbx_rtc_subscribe_service(ZBX_PROCESS_TYPE_SUPERVISOR, 0, rtc_msgs, ARRSIZE(rtc_msgs),
			local_args->config_timeout, ZBX_IPC_SERVICE_SUPERVISOR);

	for (int i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		zbx_supervisor_unit_set_t	*set = &sv.unitsets[i];

		if (0 == set->unit_num)
			continue;

		zbx_rtc_subscribe_service(i, 0, rtc_msgs, ARRSIZE(rtc_msgs), local_args->config_timeout,
				ZBX_IPC_SERVICE_SUPERVISOR);
	}

	zbx_setproctitle("%s #%d started at runlevel %d", get_process_type_string(process_type), process_num,
			sv.runlevel);

	zbx_supervisor_set_process_running(server_num);

	sigset_t	orig_mask = {0};

	zbx_block_signals(&orig_mask);

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
				case ZBX_RTC_LOG_LEVEL_INCREASE:
					supervisor_change_loglevel(&sv, 1, message->data);
					break;
				case ZBX_RTC_LOG_LEVEL_DECREASE:
					supervisor_change_loglevel(&sv, -1, message->data);
					break;
				case ZBX_SUPERVISOR_GET_ACTIVITIES:
					sypervisor_get_activities(client);
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

		if (runlevel_last != sv.runlevel)
		{
			supervisor_start_units(&sv, local_args, runlevel_last);
			runlevel_last = sv.runlevel;
		}

		double	time_now = zbx_time();

		zbx_prof_update(get_process_type_string(process_type), time_now);
		supervisor_handle_log(time_now);
	}
out:
	if (ZBX_RUNLEVEL_DEFAULT != sv.runlevel || 0 != sv.states[sv.runlevel].pending_local_num ||
		SUCCEED != ZBX_IS_NORMAL_EXIT())
	{
		zabbix_log(LOG_LEVEL_WARNING, "forced supervisor unit shutdown");
		supervisor_set_unit_runstate(&sv, UNIT_ABORTING);
	}
	else
		supervisor_set_unit_runstate(&sv, UNIT_STOPPING);

	supervisor_clear(&sv);
	zabbix_log(LOG_LEVEL_INFORMATION, "[%s #%d] stopped", get_process_type_string(process_type), process_num);

	zbx_ipc_service_close(&service);
	zbx_proc_startup_free(runlevels);

	zbx_dbconn_pool_free(dbpool);

	supervisor_clear_libraries(get_program_type_string(info->program_type));
	zbx_supervisor_worklog_clear();

	zbx_exit(SUCCEED == ZBX_IS_NORMAL_EXIT() ? EXIT_SUCCESS : EXIT_FAILURE);

#undef DEFAULT_SLEEP_TIME
}
