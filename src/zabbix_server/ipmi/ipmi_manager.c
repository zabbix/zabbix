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

#include "common.h"

#ifdef HAVE_OPENIPMI

#include "ipmi_manager.h"

#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "zbxalgo.h"
#include "preproc.h"
#include "ipmi_protocol.h"
#include "ipmi.h"
#include "../poller/poller.h"
#include "zbxavailability.h"

#define ZBX_IPMI_MANAGER_DELAY	1

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

extern int	CONFIG_IPMIPOLLER_FORKS;

#define ZBX_IPMI_POLLER_INIT		0
#define ZBX_IPMI_POLLER_READY		1
#define ZBX_IPMI_POLLER_BUSY		2

#define ZBX_IPMI_MANAGER_CLEANUP_DELAY		SEC_PER_HOUR
#define ZBX_IPMI_MANAGER_HOST_TTL		SEC_PER_DAY

/* IPMI request queued by pollers */
typedef struct
{
	/* internal requestid */
	zbx_uint64_t		requestid;

	/* target host id */
	zbx_uint64_t		hostid;

	/* itemid, set for value requests */
	zbx_uint64_t		itemid;

	/* the current item state (supported/unsupported) */
	unsigned char		item_state;

	/* the current item flags ( e.g. lld rule) */
	unsigned char		item_flags;

	/* the request message */
	zbx_ipc_message_t	message;

	/* the source client for external requests (command request) */
	zbx_ipc_client_t	*client;
}
zbx_ipmi_request_t;

/* IPMI poller data */
typedef struct
{
	/* the connected IPMI poller client */
	zbx_ipc_client_t	*client;

	/* the request queue */
	zbx_binary_heap_t	requests;

	/* the currently processing request */
	zbx_ipmi_request_t	*request;

	/* the number of hosts handled by the poller */
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

/* There can be two request types in the queue - ZBX_IPC_IPMI_VALUE_REQUEST and ZBX_IPC_IPMI_COMMAND_REQUEST. */
/* Prioritize command requests over value requests.                                                           */
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
 * Purpose: creates an IPMI request                                           *
 *                                                                            *
 * Parameters: hostid - [IN] the target hostid                                *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_request_t	*ipmi_request_create(zbx_uint64_t hostid)
{
	static zbx_uint64_t	next_requestid = 1;
	zbx_ipmi_request_t	*request;

	request = (zbx_ipmi_request_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_request_t));
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
 * Purpose: pops the next queued request from IPMI poller request queue       *
 *                                                                            *
 * Parameters: poller - [IN] the IPMI poller                                  *
 *                                                                            *
 * Return value: The next request to process or NULL if the queue is empty.   *
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
 * Purpose: pushes the requests into IPMI poller request queue                *
 *                                                                            *
 * Parameters: poller  - [IN] the IPMI poller                                 *
 *             request - [IN] the IPMI request to push                        *
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
 * Parameters: poller  - [IN] the IPMI poller                                 *
 *             message - [IN] the message to send                             *
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
 * Parameters: poller  - [IN] the IPMI poller                                 *
 *             request - [IN] the request to send                             *
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
 * Purpose: frees the current request processed by IPMI poller                *
 *                                                                            *
 * Parameters: poller  - [IN] the IPMI poller                                 *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_free_request(zbx_ipmi_poller_t *poller)
{
	ipmi_request_free(poller->request);
	poller->request = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees IPMI poller                                                 *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_free(zbx_ipmi_poller_t *poller)
{
	zbx_ipmi_request_t	*request;

	zbx_ipc_client_close(poller->client);

	while (NULL != (request = ipmi_poller_pop_request(poller)))
		ipmi_request_free(request);

	zbx_binary_heap_destroy(&poller->requests);

	zbx_free(poller);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes IPMI manager                                          *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_init(zbx_ipmi_manager_t *manager)
{
	int			i;
	zbx_ipmi_poller_t	*poller;
	zbx_binary_heap_elem_t	elem = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pollers:%d", __func__, CONFIG_IPMIPOLLER_FORKS);

	zbx_vector_ptr_create(&manager->pollers);
	zbx_hashset_create(&manager->pollers_client, 0, poller_hash_func, poller_compare_func);
	zbx_binary_heap_create(&manager->pollers_load, ipmi_poller_compare_load, 0);

	manager->next_poller_index = 0;

	for (i = 0; i < CONFIG_IPMIPOLLER_FORKS; i++)
	{
		poller = (zbx_ipmi_poller_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_poller_t));

		poller->client = NULL;
		poller->request = NULL;
		poller->hosts_num = 0;

		zbx_binary_heap_create(&poller->requests, ipmi_request_compare, 0);

		zbx_vector_ptr_append(&manager->pollers, poller);

		/* add poller to load balancing poller queue */
		elem.data = (const void *)poller;
		zbx_binary_heap_insert(&manager->pollers_load, &elem);
	}

	zbx_hashset_create(&manager->hosts, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
	zbx_ipmi_poller_t	*poller;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pollers:%d", __func__, CONFIG_IPMIPOLLER_FORKS);

	zbx_hashset_iter_reset(&manager->hosts, &iter);
	while (NULL != (host = (zbx_ipmi_manager_host_t *)zbx_hashset_iter_next(&iter)))
	{
		if (host->lastcheck + ZBX_IPMI_MANAGER_HOST_TTL <= now)
		{
			host->poller->hosts_num--;
			zbx_hashset_iter_remove(&iter);
		}
	}

	for (i = 0; i < manager->pollers.values_num; i++)
	{
		poller = (zbx_ipmi_poller_t *)manager->pollers.values[i];

		if (NULL != poller->client)
			zbx_ipc_client_send(poller->client, ZBX_IPC_IPMI_CLEANUP_REQUEST, NULL, 0);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers IPMI poller                                             *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected IPMI poller                       *
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

		poller = (zbx_ipmi_poller_t *)manager->pollers.values[manager->next_poller_index++];
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
 * Purpose: processes IPMI poller request queue                               *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             poller  - [IN] the IPMI poller                                 *
 *             now     - [IN] the current time                                *
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
		host = (zbx_ipmi_manager_host_t *)zbx_hashset_insert(&manager->hosts, &host_local, sizeof(host_local));

		host->disable_until = 0;
		host->poller = ipmi_manager_get_host_poller(manager);
	}

	host->lastcheck = now;

	return host;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates cached host                                               *
 *                                                                            *
 * Parameters: manager   - [IN] the IPMI manager                              *
 *             interface - [IN] the interface                                 *
 *             hostid    - [IN] the host                                        *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_update_host(zbx_ipmi_manager_t *manager, const DC_INTERFACE *interface,
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
 * Parameters: manager - [IN] the IPMI manager                                *
 *             itemid  - [IN] the item identifier                             *
 *             ts      - [IN] the activation timestamp                        *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_activate_interface(zbx_ipmi_manager_t *manager, zbx_uint64_t itemid, zbx_timespec_t *ts)
{
	DC_ITEM		item;
	int		errcode;
	unsigned char	*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	zbx_activate_item_interface(ts, &item, &data, &data_alloc, &data_offset);
	ipmi_manager_update_host(manager, &item.interface, item.host.hostid);

	DCconfig_clean_items(&item, &errcode, 1);

	if (NULL != data)
	{
		zbx_availability_flush(data, data_offset);
		zbx_free(data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: tries to deactivate item's interface after receiving              *
 *          host level error                                                  *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             itemid  - [IN] the item identifier                             *
 *             ts      - [IN] the deactivation timestamp                      *
 *             error   - [IN] the error                                       *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_deactivate_interface(zbx_ipmi_manager_t *manager, zbx_uint64_t itemid, zbx_timespec_t *ts,
		const char *error)
{
	DC_ITEM		item;
	int		errcode;
	unsigned char	*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	zbx_deactivate_item_interface(ts, &item, &data, &data_alloc, &data_offset, error);
	ipmi_manager_update_host(manager, &item.interface, item.host.hostid);

	DCconfig_clean_items(&item, &errcode, 1);

	if (NULL != data)
	{
		zbx_availability_flush(data, data_offset);
		zbx_free(data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: serializes IPMI poll and discovery requests                       *
 *                                                                            *
 * Parameters: item      - [IN] the item to poll                              *
 *             command   - [IN] the command to execute                        *
 *             key       - [IN] a valid item key                              *
 *             message   - [OUT] the message                                  *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_serialize_request(const DC_ITEM *item, zbx_ipc_message_t *message)
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
 * Purpose: schedules request to the host                                     *
 *                                                                            *
 * Parameters: manager  - [IN] the IPMI manager                               *
 *             hostid   - [IN] the target host id                             *
 *             request  - [IN] the request to schedule                        *
 *             now      - [IN] the current timestamp                          *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_schedule_request(zbx_ipmi_manager_t *manager, zbx_uint64_t hostid,
		zbx_ipmi_request_t *request, int now)
{
	zbx_ipmi_manager_host_t	*host;

	host = ipmi_manager_cache_host(manager, hostid, now);
	ipmi_poller_schedule_request(host->poller, request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: either sends or queues IPMI poll requests from configuration      *
 *          cache IPMI poller queue                                           *
 *                                                                            *
 * Parameters: manager   - [IN] the IPMI manager                              *
 *             now       - [IN] current time                                  *
 *             nextcheck - [OUT] time when the next IPMI check is scheduled   *
 *                         in configuration cache IPMI poller queue           *
 *                                                                            *
 * Return value: The number of requests scheduled.                            *
 *                                                                            *
 ******************************************************************************/
static int	ipmi_manager_schedule_requests(zbx_ipmi_manager_t *manager, int now, int *nextcheck)
{
	int			i, num;
	DC_ITEM			items[MAX_POLLER_ITEMS];
	zbx_ipmi_request_t	*request;
	char			*error = NULL;

	num = DCconfig_get_ipmi_poller_items(now, items, MAX_POLLER_ITEMS, nextcheck);

	for (i = 0; i < num; i++)
	{
		zbx_timespec_t	ts;
		unsigned char	state = ITEM_STATE_NOTSUPPORTED;
		int		errcode = CONFIG_ERROR;

		if (FAIL == zbx_ipmi_port_expand_macros(items[i].host.hostid, items[i].interface.port_orig,
				&items[i].interface.port, &error))
		{

			zbx_timespec(&ts);
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
					items[i].flags, NULL, &ts, state, error);
			DCrequeue_items(&items[i].itemid, &ts.sec, &errcode, 1);
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
	DCconfig_clean_items(items, NULL, num);

	return num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: forwards IPMI request to the poller managing the specified host   *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             client  - [IN] the client asking to execute IPMI request       *
 *             message - [IN] the request message                             *
 *             now     - [IN] the current time                                *
 *             code    - [IN] the request message code                        *
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
	request->message.code = code;

	ipmi_manager_schedule_request(manager, hostid, request, now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: forwards result of request to the client                          *
 *                                                                            *
 * Parameters: manager - [IN] the IPMI manager                                *
 *             client  - [IN] the IPMI poller client                          *
 *             message - [IN] the command result message                      *
 *             now     - [IN] the current time                                *
 *             code    - [IN] the result message code                         *
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
		zbx_ipc_client_send(poller->request->client, code, message->data, message->size);
		zbx_ipc_client_release(poller->request->client);
	}

	ipmi_poller_free_request(poller);
	ipmi_manager_process_poller_queue(manager, poller, now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes IPMI check result received from IPMI poller             *
 *                                                                            *
 * Parameters: manager   - [IN] the IPMI manager                              *
 *             client    - [IN] the client (IPMI poller)                      *
 *             message   - [IN] the received ZBX_IPC_IPMI_VALUE_RESULT message*
 *             now       - [IN] the current time                              *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_manager_process_value_result(zbx_ipmi_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message, int now)
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
			ipmi_manager_deactivate_interface(manager, itemid, &ts, value);
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
				init_result(&result);
				SET_TEXT_RESULT(&result, value);
				value = NULL;
				zbx_preprocess_item_value(itemid, poller->request->hostid, ITEM_VALUE_TYPE_TEXT, flags,
						&result, &ts, state, NULL);
				free_result(&result);
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
	DCrequeue_items(&itemid, &ts.sec, &errcode, 1);

	ipmi_poller_free_request(poller);
	ipmi_manager_process_poller_queue(manager, poller, now);
}

ZBX_THREAD_ENTRY(ipmi_manager_thread, args)
{
	zbx_ipc_service_t	ipmi_service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	zbx_ipmi_manager_t	ipmi_manager;
	zbx_ipmi_poller_t	*poller;
	int			ret, nextcheck, nextcleanup, polled_num = 0, scheduled_num = 0, now;
	double			time_stat, time_idle = 0, time_now, sec;
	zbx_timespec_t		timeout = {0, 0};

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	if (FAIL == zbx_ipc_service_start(&ipmi_service, ZBX_IPC_SERVICE_IPMI, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start IPMI service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ipmi_manager_init(&ipmi_manager);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	nextcleanup = time(NULL) + ZBX_IPMI_MANAGER_CLEANUP_DELAY;

	time_stat = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();
		now = time_now;

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
		scheduled_num += ipmi_manager_schedule_requests(&ipmi_manager, now, &nextcheck);

		if (FAIL != nextcheck)
			timeout.sec = (nextcheck > now ? nextcheck - now : 0);
		else
			timeout.sec = ZBX_IPMI_MANAGER_DELAY;

		if (ZBX_IPMI_MANAGER_DELAY < timeout.sec)
			timeout.sec = ZBX_IPMI_MANAGER_DELAY;

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&ipmi_service, &timeout, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(sec);

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
					ipmi_manager_process_value_result(&ipmi_manager, client, message, now);
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
			ipmi_manager_host_cleanup(&ipmi_manager, now);
			nextcleanup = now + ZBX_IPMI_MANAGER_CLEANUP_DELAY;
		}
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_service_close(&ipmi_service);
	ipmi_manager_destroy(&ipmi_manager);
#undef STAT_INTERVAL
}

#endif
