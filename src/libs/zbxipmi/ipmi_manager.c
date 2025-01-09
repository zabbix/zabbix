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

#include "zbxcommon.h"

#ifdef HAVE_OPENIPMI

#include "zbxnix.h"
#include "zbxself.h"
#include "zbxlog.h"
#include "zbxipcservice.h"
#include "zbxalgo.h"
#include "ipmi_protocol.h"
#include "ipmi.h"
#include "zbxavailability.h"
#include "zbx_availability_constants.h"
#include "zbxtime.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxipmi.h"
#include "zbxpoller.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxthreads.h"
#include "zbxtimekeeper.h"

/* IPMI request queued by pollers */
typedef struct
{
	/* internal requestid */
	zbx_uint64_t		requestid;

	/* target host id */
	zbx_uint64_t		hostid;

	/* itemid, set for value requests */
	zbx_uint64_t		itemid;

	/* current item state (supported/unsupported) */
	unsigned char		item_state;

	/* current item flags (e.g. lld rule) */
	unsigned char		item_flags;

	/* request message */
	zbx_ipc_message_t	message;

	/* source client for external requests (command request) */
	zbx_ipc_client_t	*client;
}
zbx_ipmi_request_t;

/* IPMI poller data */
typedef struct
{
	/* connected IPMI poller client */
	zbx_ipc_client_t	*client;

	/* request queue */
	zbx_binary_heap_t	requests;

	/* currently processing request */
	zbx_ipmi_request_t	*request;

	/* number of hosts handled by poller */
	int			hosts_num;
}
zbx_ipmi_poller_t;

ZBX_PTR_VECTOR_DECL(ipmi_poller_ptr, zbx_ipmi_poller_t *)
ZBX_PTR_VECTOR_IMPL(ipmi_poller_ptr, zbx_ipmi_poller_t *)

/* cached host data */
typedef struct
{
	zbx_uint64_t		hostid;
	int			disable_until;
	int			lastcheck;
	zbx_ipmi_poller_t	*poller;
}
zbx_ipmi_manager_host_t;

/* IPMI manager data */
typedef struct
{
	/* IPMI poller vector, created during manager initialization */
	zbx_vector_ipmi_poller_ptr_t	pollers;

	/* IPMI pollers indexed by IPC service clients */
	zbx_hashset_t			pollers_client;

	/* IPMI pollers sorted by number of hosts being monitored */
	zbx_binary_heap_t		pollers_load;

	/* next poller index to be assigned to new IPC service clients */
	int				next_poller_index;

	/* monitored hosts cache */
	zbx_hashset_t			hosts;
}
zbx_ipmi_manager_t;

/* pollers_client hashset support */

static zbx_hash_t	poller_hash_func(const void *d)
{
	const zbx_ipmi_poller_t	*poller = *(const zbx_ipmi_poller_t **)d;
	zbx_hash_t		hash = ZBX_DEFAULT_PTR_HASH_FUNC(&poller->client);

	return hash;
}

static int	poller_compare_func(const void *d1, const void *d2)
{
	const zbx_ipmi_poller_t	*p1 = *(const zbx_ipmi_poller_t **)d1;
	const zbx_ipmi_poller_t	*p2 = *(const zbx_ipmi_poller_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->client, p2->client);

	return 0;
}

/* pollers_load binary heap support */

static int	ipmi_poller_compare_load(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const zbx_ipmi_poller_t		*p1 = (const zbx_ipmi_poller_t *)e1->data;
	const zbx_ipmi_poller_t		*p2 = (const zbx_ipmi_poller_t *)e2->data;

	return p1->hosts_num - p2->hosts_num;
}

/* pollers requests binary heap support */

static int	ipmi_request_priority(const zbx_ipmi_request_t *request)
{
	if (NULL != request->client)
		return 0;

	switch (request->message.code)
	{
		case ZBX_IPC_IPMI_VALUE_REQUEST:
			return 1;
		default:
			return INT_MAX;
	}
}

/* There can be two request types in the queue: ZBX_IPC_IPMI_VALUE_REQUEST and ZBX_IPC_IPMI_COMMAND_REQUEST. */
/* Prioritize command requests over value requests.                                                          */
static int	ipmi_request_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const zbx_ipmi_request_t	*r1 = (const zbx_ipmi_request_t *)e1->data;
	const zbx_ipmi_request_t	*r2 = (const zbx_ipmi_request_t *)e2->data;

	ZBX_RETURN_IF_NOT_EQUAL(ipmi_request_priority(r1), ipmi_request_priority(r2));
	ZBX_RETURN_IF_NOT_EQUAL(r1->requestid, r2->requestid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates IPMI request                                              *
 *                                                                            *
 * Parameters: hostid - [IN] target host id                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_request_t	*ipmi_request_create(zbx_uint64_t hostid)
{
	static zbx_uint64_t	next_requestid = 1;

	zbx_ipmi_request_t	*request = (zbx_ipmi_request_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_request_t));

	memset(request, 0, sizeof(zbx_ipmi_request_t));
	request->requestid = next_requestid++;
	request->hostid = hostid;

	return request;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees IPMI request                                                *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_request_free(zbx_ipmi_request_t *request)
{
	zbx_ipc_message_clean(&request->message);
	zbx_free(request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pops next queued request from IPMI poller request queue           *
 *                                                                            *
 * Return value: next request to process or NULL if queue is empty            *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_request_t	*ipmi_poller_pop_request(zbx_ipmi_poller_t *poller)
{
	zbx_binary_heap_elem_t	*el;
	zbx_ipmi_request_t	*request;

	if (SUCCEED == zbx_binary_heap_empty(&poller->requests))
		return NULL;

	el = zbx_binary_heap_find_min(&poller->requests);
	request = (zbx_ipmi_request_t *)el->data;
	zbx_binary_heap_remove_min(&poller->requests);

	return request;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pushes requests into IPMI poller request queue                    *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_push_request(zbx_ipmi_poller_t *poller, zbx_ipmi_request_t *request)
{
	zbx_binary_heap_elem_t	el = {0, (void *)request};

	zbx_binary_heap_insert(&poller->requests, &el);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends request to IPMI poller                                      *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_send_request(zbx_ipmi_poller_t *poller, zbx_ipmi_request_t *request)
{
	if (FAIL == zbx_ipc_client_send(poller->client, request->message.code, request->message.data,
			request->message.size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to IPMI poller");
		exit(EXIT_FAILURE);
	}

	poller->request = request;
}

/******************************************************************************
 *                                                                            *
 * Purpose: schedules request to IPMI poller                                  *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_schedule_request(zbx_ipmi_poller_t *poller, zbx_ipmi_request_t *request)
{
	if (NULL == poller->request && NULL != poller->client)
		ipmi_poller_send_request(poller, request);
	else
		ipmi_poller_push_request(poller, request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees current request processed by IPMI poller                    *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_free_request(zbx_ipmi_poller_t *poller)
{
	ipmi_request_free(poller->request);
	poller->request = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes IPMI manager                                          *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_init(zbx_ipmi_manager_t *manager, zbx_get_config_forks_f get_config_forks)
{
	zbx_binary_heap_elem_t	elem = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pollers:%d", __func__, get_config_forks(ZBX_PROCESS_TYPE_IPMIPOLLER));

	zbx_vector_ipmi_poller_ptr_create(&manager->pollers);
	zbx_hashset_create(&manager->pollers_client, 0, poller_hash_func, poller_compare_func);
	zbx_binary_heap_create(&manager->pollers_load, ipmi_poller_compare_load, 0);

	manager->next_poller_index = 0;

	for (int i = 0; i < get_config_forks(ZBX_PROCESS_TYPE_IPMIPOLLER); i++)
	{
		zbx_ipmi_poller_t	*poller = (zbx_ipmi_poller_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_poller_t));

		poller->client = NULL;
		poller->request = NULL;
		poller->hosts_num = 0;

		zbx_binary_heap_create(&poller->requests, ipmi_request_compare, 0);

		zbx_vector_ipmi_poller_ptr_append(&manager->pollers, poller);

		/* add poller to load balancing poller queue */
		elem.data = (void *)poller;
		zbx_binary_heap_insert(&manager->pollers_load, &elem);
	}

	zbx_hashset_create(&manager->hosts, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: performs cleanup of monitored hosts cache                         *
 *                                                                            *
 * Parameters: manager          - [IN]                                        *
 *             now              - [IN]                                        *
 *             get_config_forks - [IN]                                        *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_host_cleanup(zbx_ipmi_manager_t *manager, int now, zbx_get_config_forks_f get_config_forks)
{
#define ZBX_IPMI_MANAGER_HOST_TTL		SEC_PER_DAY
	zbx_hashset_iter_t	iter;
	zbx_ipmi_manager_host_t	*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pollers:%d", __func__, get_config_forks(ZBX_PROCESS_TYPE_IPMIPOLLER));

	zbx_hashset_iter_reset(&manager->hosts, &iter);
	while (NULL != (host = (zbx_ipmi_manager_host_t *)zbx_hashset_iter_next(&iter)))
	{
		if (host->lastcheck + ZBX_IPMI_MANAGER_HOST_TTL <= now)
		{
			host->poller->hosts_num--;
			zbx_hashset_iter_remove(&iter);
		}
	}

	for (int i = 0; i < manager->pollers.values_num; i++)
	{
		zbx_ipmi_poller_t	*poller = manager->pollers.values[i];

		if (NULL != poller->client)
			zbx_ipc_client_send(poller->client, ZBX_IPC_IPMI_CLEANUP_REQUEST, NULL, 0);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
#undef ZBX_IPMI_MANAGER_HOST_TTL
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers IPMI poller                                             *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             client  - [IN] connected IPMI poller                           *
 *             message - [IN]                                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_poller_t	*ipmi_manager_register_poller(zbx_ipmi_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_ipmi_poller_t	*poller = NULL;
	pid_t			ppid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memcpy(&ppid, message->data, sizeof(ppid));

	if (ppid != getppid())
	{
		zbx_ipc_client_close(client);
		zabbix_log(LOG_LEVEL_DEBUG, "refusing connection from foreign process");
	}
	else
	{
		if (manager->next_poller_index == manager->pollers.values_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		poller = manager->pollers.values[manager->next_poller_index++];
		poller->client = client;

		zbx_hashset_insert(&manager->pollers_client, &poller, sizeof(zbx_ipmi_poller_t *));
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return poller;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns IPMI poller by connected client                           *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_poller_t	*ipmi_manager_get_poller_by_client(zbx_ipmi_manager_t *manager,
		zbx_ipc_client_t *client)
{
	zbx_ipmi_poller_t	**poller, poller_local, *plocal = &poller_local;

	plocal->client = client;

	poller = (zbx_ipmi_poller_t **)zbx_hashset_search(&manager->pollers_client, &plocal);

	if (NULL == poller)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	return *poller;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: returns IPMI poller to be assigned to new host                     *
 *                                                                             *
 * Comments: This function will return IPMI poller with least monitored hosts. *
 *                                                                             *
 *******************************************************************************/
static zbx_ipmi_poller_t	*ipmi_manager_get_host_poller(zbx_ipmi_manager_t *manager)
{
	zbx_ipmi_poller_t	*poller;
	zbx_binary_heap_elem_t	el;

	el = *zbx_binary_heap_find_min(&manager->pollers_load);
	zbx_binary_heap_remove_min(&manager->pollers_load);

	poller = (zbx_ipmi_poller_t *)el.data;
	poller->hosts_num++;

	zbx_binary_heap_insert(&manager->pollers_load, &el);

	return poller;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes IPMI poller request queue                               *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             poller  - [IN]                                                 *
 *             now     - [IN] current time                                    *
 *                                                                            *
 * Comments: This function will send the next request in queue to the poller, *
 *           skipping requests for unreachable hosts for unreachable period.  *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_process_poller_queue(zbx_ipmi_manager_t *manager, zbx_ipmi_poller_t *poller, int now)
{
	zbx_ipmi_request_t	*request;
	zbx_ipmi_manager_host_t	*host;

	while (NULL != (request = ipmi_poller_pop_request(poller)))
	{
		switch (request->message.code)
		{
			case ZBX_IPC_IPMI_COMMAND_REQUEST:
			case ZBX_IPC_IPMI_CLEANUP_REQUEST:
				break;
			case ZBX_IPC_IPMI_VALUE_REQUEST:
				if (NULL != request->client)
					break;

				if (NULL == (host = (zbx_ipmi_manager_host_t *)zbx_hashset_search(&manager->hosts,
						&request->hostid)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					ipmi_request_free(request);
					continue;
				}
				if (now < host->disable_until)
				{
					zbx_dc_requeue_unreachable_items(&request->itemid, 1);
					ipmi_request_free(request);
					continue;
				}
				break;
		}

		ipmi_poller_send_request(poller, request);
		break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: caches host to keep local copy of its availability data           *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             hostid  - [IN]                                                 *
 *             now     - [IN] current time                                    *
 *                                                                            *
 * Return value: cached host.                                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_manager_host_t	*ipmi_manager_cache_host(zbx_ipmi_manager_t *manager, zbx_uint64_t hostid, int now)
{
	zbx_ipmi_manager_host_t	*host;

	if (NULL == (host = (zbx_ipmi_manager_host_t *)zbx_hashset_search(&manager->hosts, &hostid)))
	{
		zbx_ipmi_manager_host_t	host_local;

		host_local.hostid = hostid;
		host_local.disable_until = 0;
		host_local.poller = ipmi_manager_get_host_poller(manager);
		host_local.lastcheck = now;

		host = (zbx_ipmi_manager_host_t *)zbx_hashset_insert(&manager->hosts, &host_local, sizeof(host_local));
	}
	else
		host->lastcheck = now;

	return host;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates cached host                                               *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_update_host(zbx_ipmi_manager_t *manager, const zbx_dc_interface_t *interface,
		zbx_uint64_t hostid)
{
	zbx_ipmi_manager_host_t	*ipmi_host;

	if (NULL == (ipmi_host = (zbx_ipmi_manager_host_t *)zbx_hashset_search(&manager->hosts, &hostid)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	ipmi_host->disable_until = interface->disable_until;
}

/******************************************************************************
 *                                                                            *
 * Purpose: tries to activate item's interface after receiving response       *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             itemid  - [IN]                                                 *
 *             ts      - [IN] activation timestamp                            *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_activate_interface(zbx_ipmi_manager_t *manager, zbx_uint64_t itemid, zbx_timespec_t *ts)
{
	zbx_dc_item_t	item;
	int		errcode;
	unsigned char	*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;

	zbx_dc_config_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED == errcode)
	{
		zbx_activate_item_interface(ts, &item.interface, item.itemid, item.type, item.host.host, 0, &data,
				&data_alloc, &data_offset);
		ipmi_manager_update_host(manager, &item.interface, item.host.hostid);
	}

	zbx_dc_config_clean_items(&item, &errcode, 1);

	if (NULL != data)
	{
		zbx_availability_send(ZBX_IPC_AVAILABILITY_REQUEST, data, (zbx_uint32_t)data_offset, NULL);
		zbx_free(data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: tries to deactivate item's interface after receiving              *
 *          host level error                                                  *
 *                                                                            *
 * Parameters: manager            - [IN]                                      *
 *             itemid             - [IN]                                      *
 *             ts                 - [IN] deactivation timestamp               *
 *             unavailable_delay  - [IN]                                      *
 *             unreachable_period - [IN]                                      *
 *             unreachable_delay  - [IN]                                      *
 *             error              - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_deactivate_interface(zbx_ipmi_manager_t *manager, zbx_uint64_t itemid, zbx_timespec_t *ts,
		int unavailable_delay, int unreachable_period, int unreachable_delay, const char *error)
{
	zbx_dc_item_t	item;
	int		errcode;
	unsigned char	*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;

	zbx_dc_config_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED == errcode)
	{
		zbx_deactivate_item_interface(ts, &item.interface, item.itemid, item.type, item.host.host,
			item.key_orig, &data, &data_alloc, &data_offset, unavailable_delay, unreachable_period,
			unreachable_delay, error);
		ipmi_manager_update_host(manager, &item.interface, item.host.hostid);
	}

	zbx_dc_config_clean_items(&item, &errcode, 1);

	if (NULL != data)
	{
		zbx_availability_send(ZBX_IPC_AVAILABILITY_REQUEST, data, (zbx_uint32_t)data_offset, NULL);
		zbx_free(data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: serializes IPMI poll and discovery requests                       *
 *                                                                            *
 * Parameters: item      - [IN] item to poll                                  *
 *             message   - [OUT] message                                      *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_serialize_request(const zbx_dc_item_t *item, zbx_ipc_message_t *message)
{
	zbx_uint32_t	size;

	size = zbx_ipmi_serialize_request(&message->data, item->host.hostid, item->itemid, item->interface.addr,
			item->interface.port, item->host.ipmi_authtype, item->host.ipmi_privilege,
			item->host.ipmi_username, item->host.ipmi_password, item->ipmi_sensor, 0, item->key_orig);

	message->code = ZBX_IPC_IPMI_VALUE_REQUEST;

	message->size = size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: schedules request to host                                         *
 *                                                                            *
 * Parameters: manager  - [IN]                                                *
 *             hostid   - [IN] target host id                                 *
 *             request  - [IN] request to schedule                            *
 *             now      - [IN] current timestamp                              *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_schedule_request(zbx_ipmi_manager_t *manager, zbx_uint64_t hostid,
		zbx_ipmi_request_t *request, int now)
{
	zbx_ipmi_manager_host_t	*host = ipmi_manager_cache_host(manager, hostid, now);

	ipmi_poller_schedule_request(host->poller, request);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: either sends or queues IPMI poll requests from configuration         *
 *          cache IPMI poller queue                                              *
 *                                                                               *
 * Parameters: manager        - [IN]                                             *
 *             now            - [IN] current time                                *
 *             config_timeout - [IN]                                             *
 *             nextcheck      - [OUT] time when next IPMI check is scheduled     *
 *                                    in configuration cache IPMI poller queue   *
 *                                                                               *
 * Return value: number of requests scheduled                                    *
 *                                                                               *
 *********************************************************************************/
static int	ipmi_manager_schedule_requests(zbx_ipmi_manager_t *manager, int now, int config_timeout, int *nextcheck)
{
	zbx_dc_item_t		items[ZBX_MAX_POLLER_ITEMS];
	int			num = zbx_dc_config_get_ipmi_poller_items(now, ZBX_MAX_POLLER_ITEMS, config_timeout,
						items, nextcheck);

	for (int i = 0; i < num; i++)
	{
		zbx_ipmi_request_t	*request;
		char			*error = NULL;

		if (FAIL == zbx_ipmi_port_expand_macros(items[i].host.hostid, items[i].interface.port_orig,
				&items[i].interface.port, &error))
		{
			int			errcode = CONFIG_ERROR;
			unsigned char		state = ITEM_STATE_NOTSUPPORTED;
			zbx_timespec_t		ts;

			zbx_timespec(&ts);
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
					items[i].flags, NULL, &ts, state, error);
			zbx_dc_requeue_items(&items[i].itemid, &ts.sec, &errcode, 1);
			zbx_free(error);
			continue;
		}

		request = ipmi_request_create(items[i].host.hostid);
		request->itemid = items[i].itemid;
		request->item_state = items[i].state;
		request->item_flags = items[i].flags;
		ipmi_manager_serialize_request(&items[i], &request->message);
		ipmi_manager_schedule_request(manager, items[i].host.hostid, request, now);
	}

	zbx_preprocessor_flush();
	zbx_dc_config_clean_items(items, NULL, (size_t)num);

	return num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: forwards IPMI request to poller managing specified host           *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             client  - [IN] client asking to execute IPMI request           *
 *             message - [IN] request message                                 *
 *             now     - [IN] current time                                    *
 *             code    - [IN] request message code                            *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_process_client_request(zbx_ipmi_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message, int now, int code)
{
	zbx_ipmi_request_t	*request;
	zbx_uint64_t		hostid;

	zbx_ipmi_deserialize_request_objectid(message->data, &hostid);

	zbx_ipc_client_addref(client);

	request = ipmi_request_create(0);
	request->client = client;
	zbx_ipc_message_copy(&request->message, message);
	request->message.code = (zbx_uint32_t)code;

	ipmi_manager_schedule_request(manager, hostid, request, now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: forwards result of request to client                              *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             client  - [IN] IPMI poller client                              *
 *             message - [IN] command result message                          *
 *             now     - [IN] current time                                    *
 *             code    - [IN] result message code                             *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_process_client_result(zbx_ipmi_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message, int now, int code)
{
	zbx_ipmi_poller_t	*poller;

	if (NULL == (poller = ipmi_manager_get_poller_by_client(manager, client)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	if (SUCCEED == zbx_ipc_client_connected(poller->request->client))
	{
		zbx_ipc_client_send(poller->request->client, (zbx_uint32_t)code, message->data, message->size);
		zbx_ipc_client_release(poller->request->client);
	}

	ipmi_poller_free_request(poller);
	ipmi_manager_process_poller_queue(manager, poller, now);
}

/**************************************************************************************
 *                                                                                    *
 * Purpose: processes IPMI check result received from IPMI poller                     *
 *                                                                                    *
 * Parameters: manager            - [IN]                                              *
 *             client             - [IN] client (IPMI poller)                         *
 *             message            - [IN] received ZBX_IPC_IPMI_VALUE_RESULT message   *
 *             now                - [IN] current time                                 *
 *             unavailable_delay  - [IN]                                              *
 *             unreachable_period - [IN]                                              *
 *             unreachable_delay  - [IN]                                              *
 *                                                                                    *
 *************************************************************************************/
static void	ipmi_manager_process_value_result(zbx_ipmi_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message, int now, int unavailable_delay, int unreachable_period,
		int unreachable_delay)
{
	char			*value;
	zbx_timespec_t		ts;
	unsigned char		state;
	int			errcode;
	AGENT_RESULT		result;
	zbx_ipmi_poller_t	*poller;
	zbx_uint64_t		itemid;
	unsigned char		flags;

	if (NULL == (poller = ipmi_manager_get_poller_by_client(manager, client)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	if (NULL != poller->request->client)
	{
		ipmi_manager_process_client_result(manager, client, message, now, ZBX_IPC_IPMI_VALUE_RESULT);
		return;
	}

	itemid = poller->request->itemid;
	flags = poller->request->item_flags;

	zbx_ipmi_deserialize_result(message->data, &ts, &errcode, &value);

	/* update host availability */
	switch (errcode)
	{
		case SUCCEED:
		case NOTSUPPORTED:
		case AGENT_ERROR:
			ipmi_manager_activate_interface(manager, itemid, &ts);
			break;
		case NETWORK_ERROR:
		case GATEWAY_ERROR:
		case TIMEOUT_ERROR:
			ipmi_manager_deactivate_interface(manager, itemid, &ts, unavailable_delay,
					unreachable_period, unreachable_delay, value);
			break;
		case CONFIG_ERROR:
			/* nothing to do */
			break;
	}

	/* add received data to history cache */
	switch (errcode)
	{
		case SUCCEED:
			state = ITEM_STATE_NORMAL;
			if (NULL != value)
			{
				zbx_init_agent_result(&result);
				SET_TEXT_RESULT(&result, value);
				value = NULL;
				zbx_preprocess_item_value(itemid, poller->request->hostid, ITEM_VALUE_TYPE_TEXT, flags,
						&result, &ts, state, NULL);
				zbx_free_agent_result(&result);
			}
			break;

		case NOTSUPPORTED:
		case AGENT_ERROR:
		case CONFIG_ERROR:
			state = ITEM_STATE_NOTSUPPORTED;
			zbx_preprocess_item_value(itemid, poller->request->hostid, ITEM_VALUE_TYPE_TEXT, flags, NULL,
					&ts, state, value);
			break;
		default:
			/* don't change item's state when network related error occurs */
			break;
	}

	zbx_free(value);

	/* put back the item in configuration cache IPMI poller queue */
	zbx_dc_requeue_items(&itemid, &ts.sec, &errcode, 1);

	ipmi_poller_free_request(poller);
	ipmi_manager_process_poller_queue(manager, poller, now);
}

ZBX_THREAD_ENTRY(zbx_ipmi_manager_thread, args)
{
	zbx_ipc_service_t		ipmi_service;
	char				*error = NULL;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	zbx_ipmi_manager_t		ipmi_manager;
	zbx_ipmi_poller_t		*poller;
	int				ret, polled_num = 0, scheduled_num = 0, tmp;
	double				time_idle = 0, sec;
	zbx_timespec_t			timeout = {0, 0};
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_thread_ipmi_manager_args	*ipmi_manager_args_in = (zbx_thread_ipmi_manager_args *)
			((((zbx_thread_args_t *)args))->args);

#define	STAT_INTERVAL			5	/* if a process is busy and does not sleep then update status not */
						/* faster than once in STAT_INTERVAL seconds */
#define ZBX_IPMI_MANAGER_DELAY		1
#define ZBX_IPMI_MANAGER_CLEANUP_DELAY	SEC_PER_HOUR

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	if (FAIL == zbx_ipc_service_start(&ipmi_service, ZBX_IPC_SERVICE_IPMI, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start IPMI service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ipmi_manager_init(&ipmi_manager, ipmi_manager_args_in->get_config_forks);

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	double time_stat = zbx_time();
	time_t nextcleanup = (time_t)time_stat + ZBX_IPMI_MANAGER_CLEANUP_DELAY;

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		double time_now = zbx_time();
		time_t now = (time_t)time_now;

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [scheduled %d, polled %d values, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					scheduled_num, polled_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			polled_num = 0;
			scheduled_num = 0;
		}

		/* manager -> client */
		if (FAIL == zbx_vps_monitor_capped())
		{
			scheduled_num += ipmi_manager_schedule_requests(&ipmi_manager, now,
					ipmi_manager_args_in->config_timeout, &tmp);
			time_t nextcheck = (time_t)tmp;

			if (FAIL != tmp)
				timeout.sec = (nextcheck > now ? nextcheck - now : 0);
			else
				timeout.sec = ZBX_IPMI_MANAGER_DELAY;

			if (ZBX_IPMI_MANAGER_DELAY < timeout.sec)
				timeout.sec = ZBX_IPMI_MANAGER_DELAY;
		}
		else
			timeout.sec = ZBX_IPMI_MANAGER_DELAY;

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&ipmi_service, &timeout, &client, &message);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				/* poller -> manager */
				case ZBX_IPC_IPMI_REGISTER:
					if (NULL != (poller = ipmi_manager_register_poller(&ipmi_manager, client,
							message)))
					{
						ipmi_manager_process_poller_queue(&ipmi_manager, poller, now);
					}
					break;
				/* client -> manager */
				case ZBX_IPC_IPMI_VALUE_REQUEST:
					ipmi_manager_process_client_request(&ipmi_manager, client, message, now,
							ZBX_IPC_IPMI_VALUE_REQUEST);
					break;
				/* poller -> manager or poller -> manager -> client if value request sent by client */
				case ZBX_IPC_IPMI_VALUE_RESULT:
					ipmi_manager_process_value_result(&ipmi_manager, client, message, now,
							ipmi_manager_args_in->config_unavailable_delay,
							ipmi_manager_args_in->config_unreachable_period,
							ipmi_manager_args_in->config_unreachable_delay);
					polled_num++;
					break;
				/* client -> manager */
				case ZBX_IPC_IPMI_SCRIPT_REQUEST:
					ipmi_manager_process_client_request(&ipmi_manager, client, message, now,
							ZBX_IPC_IPMI_COMMAND_REQUEST);
					break;
				/* poller -> manager -> client */
				case ZBX_IPC_IPMI_COMMAND_RESULT:
					ipmi_manager_process_client_result(&ipmi_manager, client, message, now,
							ZBX_IPC_IPMI_SCRIPT_RESULT);
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (now >= nextcleanup)
		{
			ipmi_manager_host_cleanup(&ipmi_manager, now, ipmi_manager_args_in->get_config_forks);
			nextcleanup = now + ZBX_IPMI_MANAGER_CLEANUP_DELAY;
		}
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
#undef ZBX_IPMI_MANAGER_DELAY
#undef ZBX_IPMI_MANAGER_CLEANUP_DELAY
}

#endif
