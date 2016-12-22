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

#include "common.h"
#include "dbcache.h"
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "zbxalgo.h"

#include "../poller/poller.h"

#include "ipmi_manager.h"
#include "ipmi_protocol.h"

#define ZBX_IPMI_MANAGER_DELAY	1

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

extern int	CONFIG_IPMIPOLLER_FORKS;

#define ZBX_IPMI_POLLER_INIT		0
#define ZBX_IPMI_POLLER_READY		1
#define ZBX_IPMI_POLLER_BUSY		2

#define ZBX_IPMI_MANAGER_CLEANUP_DELAY		SEC_PER_DAY
#define ZBX_IPMI_MANAGER_HOST_TTL		SEC_PER_DAY

/* IPMI request queued by pollers */
typedef struct
{
	zbx_uint64_t		hostid;
	zbx_uint64_t		itemid;
	zbx_ipc_message_t	message;
}
zbx_ipmi_request_t;

/* IPMI poller data */
typedef struct
{
	unsigned char		state;
	zbx_ipc_client_t	*client;
	zbx_queue_ptr_t		requests;
	int			hosts_num;
}
zbx_ipmi_poller_t;

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
	zbx_vector_ptr_t	pollers;

	/* IPMI pollers indexed by IPC service clients */
	zbx_hashset_t		pollers_client;

	/* IPMI pollers sorted by number of hosts being monitored */
	zbx_binary_heap_t	pollers_load;

	/* the next poller index to be assigned to new IPC service clients */
	int			next_poller_index;

	/* monitored hosts cache */
	zbx_hashset_t		hosts;
}
zbx_ipmi_manager_t;

/* pollers_client hashset support */

static zbx_hash_t	poller_hash_func(const void *d)
{
	const zbx_ipmi_poller_t	*poller = *(const zbx_ipmi_poller_t **)d;

	zbx_hash_t hash =  ZBX_DEFAULT_PTR_HASH_FUNC(&poller->client);

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

/******************************************************************************
 *                                                                            *
 * Function: ipmi_request_free                                                *
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
 * Function: ipmi_poller_free                                                 *
 *                                                                            *
 * Purpose: frees IPMI poller                                                 *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_free(zbx_ipmi_poller_t *poller)
{
	zbx_ipmi_request_t	*request;

	zbx_ipc_client_close(poller->client);

	while (NULL != (request = (zbx_ipmi_request_t *)zbx_queue_ptr_pop(&poller->requests)))
		ipmi_request_free(request);

	zbx_queue_ptr_destroy(&poller->requests);

	zbx_free(poller);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_init                                                *
 *                                                                            *
 * Purpose: initializes IPMI manager                                          *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_init(zbx_ipmi_manager_t *manager)
{
	const char		*__function_name = "ipmi_manager_init";
	int			i;
	zbx_ipmi_poller_t	*poller;
	zbx_binary_heap_elem_t	elem = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pollers;%d", __function_name, CONFIG_IPMIPOLLER_FORKS);

	zbx_vector_ptr_create(&manager->pollers);
	zbx_hashset_create(&manager->pollers_client, 0, poller_hash_func, poller_compare_func);
	zbx_binary_heap_create(&manager->pollers_load, ipmi_poller_compare_load, 0);

	manager->next_poller_index = 0;

	for (i = 0; i < CONFIG_IPMIPOLLER_FORKS; i++)
	{
		poller = (zbx_ipmi_poller_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_poller_t));

		poller->state = ZBX_IPMI_POLLER_INIT;
		poller->client = NULL;
		zbx_queue_ptr_create(&poller->requests);

		zbx_vector_ptr_append(&manager->pollers, poller);

		/* add poller to load balancing poller queue */
		elem.data = (const void *)poller;
		zbx_binary_heap_insert(&manager->pollers_load, &elem);
	}

	zbx_hashset_create(&manager->hosts, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_destroy                                             *
 *                                                                            *
 * Purpose: destroys IPMI manager                                             *
 *                                                                            *
 * Parameters: manager - [IN] the manager to destroy                          *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_destroy(zbx_ipmi_manager_t *manager)
{
	zbx_hashset_destroy(&manager->hosts);
	zbx_binary_heap_destroy(&manager->pollers_load);
	zbx_hashset_destroy(&manager->pollers_client);
	zbx_vector_ptr_clear_ext(&manager->pollers, (zbx_clean_func_t)ipmi_poller_free);
	zbx_vector_ptr_destroy(&manager->pollers);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_host_cleanup                                        *
 *                                                                            *
 * Purpose: performs cleanup of monitored hosts cache                         *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             now     - [IN] the current time                                *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_host_cleanup(zbx_ipmi_manager_t *manager, int now)
{
	zbx_hashset_iter_t	iter;
	zbx_ipmi_manager_host_t	*host;

	zbx_hashset_iter_reset(&manager->hosts, &iter);
	while (NULL != (host = (zbx_ipmi_manager_host_t *)zbx_hashset_iter_next(&iter)))
	{
		if (host->lastcheck + ZBX_IPMI_MANAGER_HOST_TTL <= now)
		{
			host->poller->hosts_num--;
			zbx_hashset_iter_remove(&iter);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_register_poller                                     *
 *                                                                            *
 * Purpose: registers IPMI poller                                             *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected IPMI poller                       *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_register_poller(zbx_ipmi_manager_t *manager, zbx_ipc_client_t *client)
{
	const char		*__function_name = "ipmi_manager_register_poller";
	zbx_ipmi_poller_t	*poller;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (manager->next_poller_index == manager->pollers.values_num)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	poller = (zbx_ipmi_poller_t *)manager->pollers.values[manager->next_poller_index++];

	poller->state = ZBX_IPMI_POLLER_READY;
	poller->client = client;
	poller->hosts_num = 0;

	zbx_hashset_insert(&manager->pollers_client, &poller, sizeof(poller));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_get_poller_by_client                                *
 *                                                                            *
 * Purpose: returns IPMI poller by connected client                           *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected IPMI poller                       *
 *                                                                            *
 * Return value: The IPMI poller                                              *
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

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_get_host_poller                                     *
 *                                                                            *
 * Purpose: returns IPMI poller to be assigned to a new host                  *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *                                                                            *
 * Return value: The IPMI poller                                              *
 *                                                                            *
 * Comments: This function will return IPMI poller with least monitored hosts.*
 *                                                                            *
 ******************************************************************************/
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
 * Function: ipmi_poller_send_message                                         *
 *                                                                            *
 * Purpose: sends message to IPMI poller                                      *
 *                                                                            *
 * Parameters: poller  - [IN] the IPMI poller                                 *
 *             message - [IN] the message to send                             *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_send_message(zbx_ipmi_poller_t *poller, const zbx_ipc_message_t *message)
{
	if (FAIL == zbx_ipc_client_send(poller->client, message->header[ZBX_IPC_MESSAGE_CODE], message->data,
			message->header[ZBX_IPC_MESSAGE_SIZE]))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to IPMI poller");
		exit(EXIT_FAILURE);
	}

	poller->state = ZBX_IPMI_POLLER_BUSY;
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_process_request_queue                               *
 *                                                                            *
 * Purpose: processes IPMI poller request queue                               *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             client  - [IN] the client (IPMI poller)                        *
 *             now     - [IN] the current time                                *
 *                                                                            *
 * Comments: This function will send the next request in queue to the poller, *
 *           skipping requests for unreachable hosts for unreachable period.  *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_process_request_queue(zbx_ipmi_manager_t *manager, zbx_ipc_client_t *client, int now)
{
	zbx_ipmi_poller_t	*poller;
	zbx_ipmi_request_t	*request;
	zbx_ipmi_manager_host_t	*host;

	if (NULL == (poller = ipmi_manager_get_poller_by_client(manager, client)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	while (NULL != (request = (zbx_ipmi_request_t *)zbx_queue_ptr_pop(&poller->requests)))
	{
		if (NULL == (host = zbx_hashset_search(&manager->hosts, &request->hostid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			ipmi_request_free(request);
			continue;
		}

		/* if host can be polled - send the next request to the IPMI poller */
		if (now >= host->disable_until)
		{
			ipmi_poller_send_message(poller, &request->message);
			ipmi_request_free(request);
			return;
		}

		/* items on unreachable hosts must be requeued to be checked when hosts is enabled again */
		zbx_dc_requeue_unreachable_items(&request->itemid, 1);
	}

	/* no more requests, set poller state so the next request is sent directly instead of being queued */
	poller->state = ZBX_IPMI_POLLER_READY;
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_cache_host                                          *
 *                                                                            *
 * Purpose: caches host to keep local copy of its availability data           *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             hostid  - [IN] the host identifier                             *
 *             now     - [IN] the current time                                *
 *                                                                            *
 * Return value: The cached host.                                             *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_manager_host_t	*ipmi_manager_cache_host(zbx_ipmi_manager_t *manager, zbx_uint64_t hostid, int now)
{
	zbx_ipmi_manager_host_t	*host;

	if (NULL == (host = (zbx_ipmi_manager_host_t *)zbx_hashset_search(&manager->hosts, &hostid)))
	{
		zbx_ipmi_manager_host_t	host_local;

		host_local.hostid = hostid;
		host = zbx_hashset_insert(&manager->hosts, &host_local, sizeof(host_local));

		host->disable_until = 0;
		host->poller = ipmi_manager_get_host_poller(manager);
	}

	host->lastcheck = now;

	return host;
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_update_host                                         *
 *                                                                            *
 * Purpose: updates cached host                                               *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             hostid  - [IN] the host identifier                             *
 *             now     - [IN] the current time                                *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_update_host(zbx_ipmi_manager_t *manager, zbx_uint64_t hostid, const DC_HOST *host)
{
	zbx_ipmi_manager_host_t	*ipmi_host;

	if (NULL == (ipmi_host = (zbx_ipmi_manager_host_t *)zbx_hashset_search(&manager->hosts, &hostid)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	ipmi_host->disable_until = host->ipmi_disable_until;
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_activate_host                                       *
 *                                                                            *
 * Purpose: tries to activate item's host after receiving response            *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             itemid  - [IN] the item identifier                             *
 *             ts      - [IN] the activation timestamp                        *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_activate_host(zbx_ipmi_manager_t *manager, zbx_uint64_t itemid, zbx_timespec_t *ts)
{
	DC_ITEM	item;
	int	errcode;

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	zbx_activate_item_host(&item, ts);
	ipmi_manager_update_host(manager, item.host.hostid, &item.host);

	DCconfig_clean_items(&item, &errcode, 1);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_deactivate_host                                     *
 *                                                                            *
 * Purpose: tries to deactivate item's host after receiving host level error  *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             itemid  - [IN] the item identifier                             *
 *             ts      - [IN] the deactivation timestamp                      *
 *             error   - [IN] the error                                       *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_deactivate_host(zbx_ipmi_manager_t *manager, zbx_uint64_t itemid, zbx_timespec_t *ts,
		const char *error)
{
	DC_ITEM	item;
	int	errcode;

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	zbx_deactivate_item_host(&item, ts, error);
	ipmi_manager_update_host(manager, item.host.hostid, &item.host);

	DCconfig_clean_items(&item, &errcode, 1);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_process_result                                      *
 *                                                                            *
 * Purpose: processes IPMI check result received from IPMI poller             *
 *                                                                            *
 * Parameters: manager   - [IN] the IPMI manager                              *
 *             client    - [IN] the client (IPMI poller)                      *
 *             message   - [IN] the received ZBX_IPC_IPMI_RESULT message      *
 *             now       - [IN] the current time                              *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_process_result(zbx_ipmi_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message, int now)
{
	char		*value;
	zbx_uint64_t	itemid;
	zbx_timespec_t	ts;
	unsigned char	state;
	int		errcode;
	AGENT_RESULT	result;

	zbx_ipmi_deserialize_value_response(message->data, &itemid, &ts, &errcode, &value);

	/* update host availability */
	switch (errcode)
	{
		case SUCCEED:
		case NOTSUPPORTED:
		case AGENT_ERROR:
			ipmi_manager_activate_host(manager, itemid, &ts);
			break;
		case NETWORK_ERROR:
		case GATEWAY_ERROR:
		case TIMEOUT_ERROR:
			ipmi_manager_deactivate_host(manager, itemid, &ts, value);
			break;
		case CONFIG_ERROR:
			/* nothing to do */
			break;
	}

	/* TODO: use ITEM_VALUE_TYPE_TEXT when ZBXNEXT-1443 is merged (item preprocessing) */
	/* add received data to history cache */
	switch (errcode)
	{
		case SUCCEED:
			state = ITEM_STATE_NORMAL;
			/* reusing exiting value, so free_result() shouldn't be used */
			init_result(&result);
			SET_STR_RESULT(&result, value);
			dc_add_history(itemid, ITEM_VALUE_TYPE_STR, 0, &result, &ts, state, NULL);
			break;

		case NOTSUPPORTED:
		case AGENT_ERROR:
		case CONFIG_ERROR:
			state = ITEM_STATE_NOTSUPPORTED;
			dc_add_history(itemid, ITEM_VALUE_TYPE_STR, 0, NULL, &ts, state, value);
	}

	dc_flush_history();
	zbx_free(value);

	/* put back the item in configuration cache IPMI poller queue */
	DCrequeue_items(&itemid, &state, &ts.sec, NULL, NULL, &errcode, 1);

	ipmi_manager_process_request_queue(manager, client, now);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_serialize_value_request                             *
 *                                                                            *
 * Purpose: serializes IPMI poll request (ZBX_IPC_IPMI_REQUEST)               *
 *                                                                            *
 * Parameters: item      - [IN] the item to poll                              *
 *             message   - [OUT] the message                                  *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_serialize_value_request(const DC_ITEM *item, zbx_ipc_message_t *message)
{
	zbx_uint32_t	size;

	size = zbx_ipmi_serialize_value_request(&message->data, item->itemid, item->interface.addr,
			item->interface.port, item->host.ipmi_privilege, item->host.ipmi_authtype,
			item->host.ipmi_username, item->host.ipmi_password, item->ipmi_sensor);

	message->header[ZBX_IPC_MESSAGE_CODE] = ZBX_IPC_IPMI_REQUEST;
	message->header[ZBX_IPC_MESSAGE_SIZE] = size;
}


/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_send_request                                        *
 *                                                                            *
 * Purpose: sends IPMI poll request (ZBX_IPC_IPMI_REQUEST) to IPMI poller     *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             poller  - [IN] the IPMI poller                                 *
 *             item    - [IN] the item to poll                                *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_send_request(zbx_ipmi_manager_t *manager, zbx_ipmi_poller_t *poller, const DC_ITEM *item)
{
	zbx_ipc_message_t	message;

	ipmi_manager_serialize_value_request(item, &message);
	ipmi_poller_send_message(poller, &message);
	zbx_ipc_message_clean(&message);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_queue_request                                       *
 *                                                                            *
 * Purpose: queues IPMI poll request (ZBX_IPC_IPMI_REQUEST)                   *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             poller  - [IN] the IPMI poller                                 *
 *             item    - [IN] the item to poll                                *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_queue_request(zbx_ipmi_manager_t *manager, zbx_ipmi_poller_t *poller, const DC_ITEM *item)
{
	zbx_ipmi_request_t	*request;

	request = (zbx_ipmi_request_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_request_t));
	request->hostid = item->host.hostid;
	request->itemid = item->itemid;
	ipmi_manager_serialize_value_request(item, &request->message);

	zbx_queue_ptr_push(&poller->requests, request);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_manager_schedule_requests                                   *
 *                                                                            *
 * Purpose: either sends or queues IPMI poll requests from configuration      *
 *          cache IPMI poller queue                                           *
 *                                                                            *
 * Parameters: manager   - [IN] the IPMI manager                              *
 *             now       - [IN] current time                                  *
 *             nextcheck - [OUT] time when the next IPMI check is scheduled   *
 *                         in configuration cache IPMI poller queue           *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_schedule_requests(zbx_ipmi_manager_t *manager, int now, int *nextcheck)
{
	int			i, num;
	DC_ITEM			items[MAX_POLLER_ITEMS];
	zbx_ipmi_manager_host_t	*host;

	num = DCconfig_get_ipmi_poller_items(now, items, MAX_POLLER_ITEMS, nextcheck);

	for (i = 0; i < num; i++)
	{
		host = ipmi_manager_cache_host(manager, items[i].host.hostid, now);

		if (ZBX_IPMI_POLLER_READY == host->poller->state)
			ipmi_manager_send_request(manager, host->poller, &items[i]);
		else
			ipmi_manager_queue_request(manager, host->poller, &items[i]);
	}

	DCconfig_clean_items(items, NULL, num);
}

ZBX_THREAD_ENTRY(ipmi_manager_thread, args)
{
	zbx_ipc_service_t	ipmi_service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	zbx_ipmi_manager_t	ipmi_manager;
	int			now, nextcheck, timeout, nextcleanup;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;

	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	if (FAIL == zbx_ipc_service_start(&ipmi_service, ZBX_IPC_SERVICE_IPMI, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start IPMI service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	ipmi_manager_init(&ipmi_manager);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	nextcleanup = time(NULL) + ZBX_IPMI_MANAGER_CLEANUP_DELAY;

	for (;;)
	{
		zbx_handle_log();

		now = time(NULL);

		ipmi_manager_schedule_requests(&ipmi_manager, now, &nextcheck);

		if (FAIL != nextcheck)
			timeout = (nextcheck > now ? nextcheck - now : 0);
		else
			timeout = ZBX_IPMI_MANAGER_DELAY;

		zbx_ipc_service_recv(&ipmi_service, timeout, &client, &message);

		if (NULL == client)
		{
			/* TODO: update statistics in process title */
			continue;
		}

		if (NULL == message)
		{
			zabbix_log(LOG_LEVEL_CRIT, "IPMI service client connection closed unexpectedly", error);
			break;
		}

		switch (message->header[ZBX_IPC_MESSAGE_CODE])
		{
			case ZBX_IPC_IPMI_REGISTER:
				ipmi_manager_register_poller(&ipmi_manager, client);
				ipmi_manager_process_request_queue(&ipmi_manager, client, now);
				break;
			case ZBX_IPC_IPMI_RESULT:
				ipmi_manager_process_result(&ipmi_manager, client, message, now);
				break;
		}

		zbx_ipc_message_free(message);

		if (now >= nextcleanup)
		{
			ipmi_manager_host_cleanup(&ipmi_manager, now);
			nextcleanup = now + ZBX_IPMI_MANAGER_CLEANUP_DELAY;
		}
	}

	zbx_ipc_service_close(&ipmi_service);
	ipmi_manager_destroy(&ipmi_manager);

	return 0;
#undef STAT_INTERVAL
}
