/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include <stddef.h>

#include "common.h"
#include "log.h"
#include "threads.h"
#include "dbcache.h"
#include "ipc.h"
#include "mutexs.h"
#include "memalloc.h"
#include "zbxserver.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "zbxregexp.h"
#include "cfg.h"
#include "zbxtasks.h"
#include "../zbxcrypto/tls_tcp_active.h"
#include "dbcache.h"

#define ZBX_DBCONFIG_IMPL
#include "dbconfig.h"
#include "dbsync.h"

static int	sync_in_progress = 0;

#define	LOCK_CACHE	if (0 == sync_in_progress) zbx_mutex_lock(&config_lock)
#define	UNLOCK_CACHE	if (0 == sync_in_progress) zbx_mutex_unlock(&config_lock)
#define START_SYNC	LOCK_CACHE; sync_in_progress = 1
#define FINISH_SYNC	sync_in_progress = 0; UNLOCK_CACHE

#define ZBX_LOC_NOWHERE	0
#define ZBX_LOC_QUEUE	1
#define ZBX_LOC_POLLER	2

#define ZBX_SNMP_OID_TYPE_NORMAL	0
#define ZBX_SNMP_OID_TYPE_DYNAMIC	1
#define ZBX_SNMP_OID_TYPE_MACRO		2

/* trigger is functional unless its expression contains disabled or not monitored items */
#define TRIGGER_FUNCTIONAL_TRUE		0
#define TRIGGER_FUNCTIONAL_FALSE	1

/* item priority in poller queue */
#define ZBX_QUEUE_PRIORITY_HIGH		0
#define ZBX_QUEUE_PRIORITY_NORMAL	1
#define ZBX_QUEUE_PRIORITY_LOW		2

/* shorthand macro for calling in_maintenance_without_data_collection() */
#define DCin_maintenance_without_data_collection(dc_host, dc_item)			\
		in_maintenance_without_data_collection(dc_host->maintenance_status,	\
				dc_host->maintenance_type, dc_item->type)

/******************************************************************************
 *                                                                            *
 * Function: zbx_value_validator_func_t                                       *
 *                                                                            *
 * Purpose: validate macro value when expanding user macros                   *
 *                                                                            *
 * Parameters: macro   - [IN] the user macro                                  *
 *             value   - [IN] the macro value                                 *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the value is valid                                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
typedef int (*zbx_value_validator_func_t)(const char *macro, const char *value, char **error);

static ZBX_DC_CONFIG	*config = NULL;
static ZBX_MUTEX	config_lock = ZBX_MUTEX_NULL;
static zbx_mem_info_t	*config_mem;

extern unsigned char	program_type;
extern int		CONFIG_TIMER_FORKS;

ZBX_MEM_FUNC_IMPL(__config, config_mem)

/******************************************************************************
 *                                                                            *
 * Function: is_item_processed_by_server                                      *
 *                                                                            *
 * Parameters: type - [IN] item type [ITEM_TYPE_* flag]                       *
 *             key  - [IN] item key                                           *
 *                                                                            *
 * Return value: SUCCEED when an item should be processed by server           *
 *               FAIL otherwise                                               *
 *                                                                            *
 * Comments: list of the items, always processed by server                    *
 *           ,------------------+--------------------------------------,      *
 *           | type             | key                                  |      *
 *           +------------------+--------------------------------------+      *
 *           | Zabbix internal  | zabbix[host,,items]                  |      *
 *           | Zabbix internal  | zabbix[host,,items_unsupported]      |      *
 *           | Zabbix internal  | zabbix[host,discovery,interfaces]    |      *
 *           | Zabbix internal  | zabbix[host,,maintenance]            |      *
 *           | Zabbix internal  | zabbix[proxy,<proxyname>,lastaccess] |      *
 *           | Zabbix aggregate | *                                    |      *
 *           | Calculated       | *                                    |      *
 *           '------------------+--------------------------------------'      *
 *                                                                            *
 ******************************************************************************/
int	is_item_processed_by_server(unsigned char type, const char *key)
{
	int	ret = FAIL;

	switch (type)
	{
		case ITEM_TYPE_AGGREGATE:
		case ITEM_TYPE_CALCULATED:
			ret = SUCCEED;
			break;

		case ITEM_TYPE_INTERNAL:
			if (0 == strncmp(key, "zabbix[", 7))
			{
				AGENT_REQUEST	request;
				char		*arg1, *arg2, *arg3;

				init_request(&request);

				if (SUCCEED != parse_item_key(key, &request) || 3 != request.nparam)
					goto clean;

				arg1 = get_rparam(&request, 0);
				arg2 = get_rparam(&request, 1);
				arg3 = get_rparam(&request, 2);

				if (0 == strcmp(arg1, "host"))
				{
					if ('\0' == *arg2)
					{
						if (0 == strcmp(arg3, "maintenance") || 0 == strcmp(arg3, "items") ||
								0 == strcmp(arg3, "items_unsupported"))
						{
							ret = SUCCEED;
						}
					}
					else if (0 == strcmp(arg2, "discovery") && 0 == strcmp(arg3, "interfaces"))
						ret = SUCCEED;
				}
				else if (0 == strcmp(arg1, "proxy") && 0 == strcmp(arg3, "lastaccess"))
					ret = SUCCEED;
clean:
				free_request(&request);
			}
			break;
	}

	return ret;
}

static unsigned char	poller_by_item(unsigned char type, const char *key)
{
	switch (type)
	{
		case ITEM_TYPE_SIMPLE:
			if (SUCCEED == cmp_key_id(key, SERVER_ICMPPING_KEY) ||
					SUCCEED == cmp_key_id(key, SERVER_ICMPPINGSEC_KEY) ||
					SUCCEED == cmp_key_id(key, SERVER_ICMPPINGLOSS_KEY))
			{
				if (0 == CONFIG_PINGER_FORKS)
					break;

				return ZBX_POLLER_TYPE_PINGER;
			}
			/* break; is not missing here */
		case ITEM_TYPE_ZABBIX:
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
		case ITEM_TYPE_INTERNAL:
		case ITEM_TYPE_AGGREGATE:
		case ITEM_TYPE_EXTERNAL:
		case ITEM_TYPE_DB_MONITOR:
		case ITEM_TYPE_SSH:
		case ITEM_TYPE_TELNET:
		case ITEM_TYPE_CALCULATED:
			if (0 == CONFIG_POLLER_FORKS)
				break;

			return ZBX_POLLER_TYPE_NORMAL;
		case ITEM_TYPE_IPMI:
			if (0 == CONFIG_IPMIPOLLER_FORKS)
				break;

			return ZBX_POLLER_TYPE_IPMI;
		case ITEM_TYPE_JMX:
			if (0 == CONFIG_JAVAPOLLER_FORKS)
				break;

			return ZBX_POLLER_TYPE_JAVA;
	}

	return ZBX_NO_POLLER;
}

/******************************************************************************
 *                                                                            *
 * Function: is_counted_in_item_queue                                         *
 *                                                                            *
 * Purpose: determine whether the given item type is counted in item queue    *
 *                                                                            *
 * Return value: SUCCEED if item is counted in the queue, FAIL otherwise      *
 *                                                                            *
 ******************************************************************************/
static int	is_counted_in_item_queue(unsigned char type, const char *key)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX_ACTIVE:
			if (0 == strncmp(key, "log[", 4) ||
					0 == strncmp(key, "logrt[", 6) ||
					0 == strncmp(key, "eventlog[", 9))
			{
				return FAIL;
			}
			break;
		case ITEM_TYPE_TRAPPER:
		case ITEM_TYPE_DEPENDENT:
		case ITEM_TYPE_HTTPTEST:
		case ITEM_TYPE_SNMPTRAP:
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_item_nextcheck_seed                                          *
 *                                                                            *
 * Purpose: get the seed value to be used for item nextcheck calculations     *
 *                                                                            *
 * Return value: the seed for nextcheck calculations                          *
 *                                                                            *
 * Comments: The seed value is used to spread multiple item nextchecks over   *
 *           the item delay period to even the system load.                   *
 *           Items with the same delay period and seed value will have the    *
 *           same nextcheck values.                                           *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	get_item_nextcheck_seed(zbx_uint64_t itemid, zbx_uint64_t interfaceid, unsigned char type,
		const char *key)
{
	if (ITEM_TYPE_JMX == type)
		return interfaceid;

	if (SUCCEED == is_snmp_type(type))
	{
		ZBX_DC_INTERFACE	*interface;

		if (NULL == (interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid)) ||
				SNMP_BULK_ENABLED != interface->bulk)
		{
			return itemid;
		}

		return interfaceid;
	}

	if (ITEM_TYPE_SIMPLE == type)
	{
		if (SUCCEED == cmp_key_id(key, SERVER_ICMPPING_KEY) ||
				SUCCEED == cmp_key_id(key, SERVER_ICMPPINGSEC_KEY) ||
				SUCCEED == cmp_key_id(key, SERVER_ICMPPINGLOSS_KEY))
		{
			return interfaceid;
		}
	}

	return itemid;
}

static int	DCget_disable_until(const ZBX_DC_ITEM *item, const ZBX_DC_HOST *host);

#define ZBX_ITEM_COLLECTED		0x01	/* force item rescheduling after new value collection */
#define ZBX_HOST_UNREACHABLE		0x02
#define ZBX_ITEM_KEY_CHANGED		0x04
#define ZBX_ITEM_TYPE_CHANGED		0x08
#define ZBX_ITEM_DELAY_CHANGED		0x10
#define ZBX_REFRESH_UNSUPPORTED_CHANGED	0x20

static void	DCitem_nextcheck_update(ZBX_DC_ITEM *item, const ZBX_DC_HOST *host, unsigned char new_state,
		int flags, int now)
{
	ZBX_DC_PROXY	*proxy = NULL;
	zbx_uint64_t	seed;

	if (0 == (flags & ZBX_ITEM_COLLECTED) && 0 != item->nextcheck &&
			0 == (flags & ZBX_ITEM_KEY_CHANGED) && 0 == (flags & ZBX_ITEM_TYPE_CHANGED) &&
			((ITEM_STATE_NORMAL == new_state && 0 == (flags & ZBX_ITEM_DELAY_CHANGED)) ||
			(ITEM_STATE_NOTSUPPORTED == new_state && 0 == (flags & (0 == item->schedulable ?
					ZBX_ITEM_DELAY_CHANGED : ZBX_REFRESH_UNSUPPORTED_CHANGED)))))
	{
		return;	/* avoid unnecessary nextcheck updates when syncing items in cache */
	}

	if (0 != (flags & ZBX_HOST_UNREACHABLE) && 0 != (item->nextcheck = DCget_disable_until(item, host)))
		return;

	if (0 != host->proxy_hostid && NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &host->proxy_hostid)))
		now -= proxy->timediff;

	seed = get_item_nextcheck_seed(item->itemid, item->interfaceid, item->type, item->key);

	/* for new items, supported items and items that are notsupported due to invalid update interval try to parse */
	/* interval first and then decide whether it should become/remain supported/notsupported */
	if (0 == item->nextcheck || ITEM_STATE_NORMAL == new_state || 0 == item->schedulable)
	{
		int			simple_interval;
		zbx_custom_interval_t	*custom_intervals;
		char			*error = NULL;

		if (SUCCEED != zbx_interval_preproc(item->delay, &simple_interval, &custom_intervals, &error))
		{
			zbx_timespec_t	ts = {now, 0};

			/* Usual way for an item to become not supported is to receive an error instead of value. */
			/* Item state and error will be updated by history syncer during history sync following a */
			/* regular procedure with item update in database and config cache, logging etc. There is */
			/* no need to set ITEM_STATE_NOTSUPPORTED here. */

			dc_add_history(item->itemid, item->value_type, 0, NULL, &ts, ITEM_STATE_NOTSUPPORTED, error);
			zbx_free(error);

			/* Polling items with invalid update intervals repeatedly does not make sense because they */
			/* can only be healed by editing configuration (either update interval or macros involved) */
			/* and such changes will be detected during configuration synchronization. DCsync_items()  */
			/* detects item configuration changes affecting check scheduling and passes them in flags. */

			item->nextcheck = ZBX_JAN_2038;
			item->schedulable = 0;
			return;
		}

		if (ITEM_STATE_NORMAL == new_state || 0 == item->schedulable)
		{
			/* supported items and items that could not have been scheduled previously, but had their */
			/* update interval fixed, should be scheduled using their update intervals */
			item->nextcheck = calculate_item_nextcheck(seed, item->type, simple_interval, custom_intervals,
					now);
		}
		else
		{
			/* use refresh_unsupported interval for new items that have a valid update interval of their */
			/* own, but were synced from the database in ITEM_STATE_NOTSUPPORTED state */
			item->nextcheck = calculate_item_nextcheck(seed, item->type, config->config->refresh_unsupported,
					NULL, now);
		}

		zbx_custom_interval_free(custom_intervals);
	}
	else	/* for items notsupported for other reasons use refresh_unsupported interval */
	{
		item->nextcheck = calculate_item_nextcheck(seed, item->type, config->config->refresh_unsupported, NULL,
				now);
	}

	item->schedulable = 1;

	if (NULL != proxy)
		item->nextcheck += proxy->timediff + 1;
}

static void	DCitem_poller_type_update(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host, int flags)
{
	unsigned char	poller_type;

	if (0 != dc_host->proxy_hostid && SUCCEED != is_item_processed_by_server(dc_item->type, dc_item->key))
	{
		dc_item->poller_type = ZBX_NO_POLLER;
		return;
	}

	poller_type = poller_by_item(dc_item->type, dc_item->key);

	if (0 != (flags & ZBX_HOST_UNREACHABLE))
	{
		if (ZBX_POLLER_TYPE_NORMAL == poller_type || ZBX_POLLER_TYPE_JAVA == poller_type)
			poller_type = ZBX_POLLER_TYPE_UNREACHABLE;

		dc_item->poller_type = poller_type;
		return;
	}

	if (0 != (flags & ZBX_ITEM_COLLECTED))
	{
		dc_item->poller_type = poller_type;
		return;
	}

	if (ZBX_POLLER_TYPE_UNREACHABLE != dc_item->poller_type ||
			(ZBX_POLLER_TYPE_NORMAL != poller_type && ZBX_POLLER_TYPE_JAVA != poller_type))
	{
		dc_item->poller_type = poller_type;
	}
}

static int	DCget_disable_until(const ZBX_DC_ITEM *item, const ZBX_DC_HOST *host)
{
	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			if (0 != host->errors_from)
				return host->disable_until;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			if (0 != host->snmp_errors_from)
				return host->snmp_disable_until;
			break;
		case ITEM_TYPE_IPMI:
			if (0 != host->ipmi_errors_from)
				return host->ipmi_disable_until;
			break;
		case ITEM_TYPE_JMX:
			if (0 != host->jmx_errors_from)
				return host->jmx_disable_until;
			break;
		default:
			/* nothing to do */;
	}

	return 0;
}

static void	DCincrease_disable_until(const ZBX_DC_ITEM *item, ZBX_DC_HOST *host, int now)
{
	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			if (0 != host->errors_from)
				host->disable_until = now + CONFIG_TIMEOUT;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			if (0 != host->snmp_errors_from)
				host->snmp_disable_until = now + CONFIG_TIMEOUT;
			break;
		case ITEM_TYPE_IPMI:
			if (0 != host->ipmi_errors_from)
				host->ipmi_disable_until = now + CONFIG_TIMEOUT;
			break;
		case ITEM_TYPE_JMX:
			if (0 != host->jmx_errors_from)
				host->jmx_disable_until = now + CONFIG_TIMEOUT;
			break;
		default:
			/* nothing to do */;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCfind_id                                                        *
 *                                                                            *
 * Purpose: Find an element in a hashset by its 'id' or create the element if *
 *          it does not exist                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *     hashset - [IN] hashset to search                                       *
 *     id      - [IN] id of element to search for                             *
 *     size    - [IN] size of element to search for                           *
 *     found   - [OUT flag. 0 - element did not exist, it was created.        *
 *                          1 - existing element was found.                   *
 *                                                                            *
 * Return value: pointer to the found or created element                      *
 *                                                                            *
 ******************************************************************************/
static void	*DCfind_id(zbx_hashset_t *hashset, zbx_uint64_t id, size_t size, int *found)
{
	void		*ptr;
	zbx_uint64_t	buffer[1024];	/* adjust buffer size to accommodate any type DCfind_id() can be called for */

	if (NULL == (ptr = zbx_hashset_search(hashset, &id)))
	{
		*found = 0;

		buffer[0] = id;
		ptr = zbx_hashset_insert(hashset, &buffer[0], size);
	}
	else
		*found = 1;

	return ptr;
}

static ZBX_DC_ITEM	*DCfind_item(zbx_uint64_t hostid, const char *key)
{
	ZBX_DC_ITEM_HK	*item_hk, item_hk_local;

	item_hk_local.hostid = hostid;
	item_hk_local.key = key;

	if (NULL == (item_hk = (ZBX_DC_ITEM_HK *)zbx_hashset_search(&config->items_hk, &item_hk_local)))
		return NULL;
	else
		return item_hk->item_ptr;
}

static ZBX_DC_HOST	*DCfind_host(const char *host)
{
	ZBX_DC_HOST_H	*host_h, host_h_local;

	host_h_local.host = host;

	if (NULL == (host_h = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_h, &host_h_local)))
		return NULL;
	else
		return host_h->host_ptr;
}

/******************************************************************************
 *                                                                            *
 * Function: DCfind_proxy                                                     *
 *                                                                            *
 * Purpose: Find a record with proxy details in configuration cache using the *
 *          proxy name                                                        *
 *                                                                            *
 * Parameters: host - [IN] proxy name                                         *
 *                                                                            *
 * Return value: pointer to record if found or NULL otherwise                 *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_HOST	*DCfind_proxy(const char *host)
{
	ZBX_DC_HOST_H	*host_p, host_p_local;

	host_p_local.host = host;

	if (NULL == (host_p = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_p, &host_p_local)))
		return NULL;
	else
		return host_p->host_ptr;
}

/* private strpool functions */

#define	REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

static zbx_hash_t	__config_strpool_hash(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((char *)data + REFCOUNT_FIELD_SIZE);
}

static int	__config_strpool_compare(const void *d1, const void *d2)
{
	return strcmp((char *)d1 + REFCOUNT_FIELD_SIZE, (char *)d2 + REFCOUNT_FIELD_SIZE);
}

static const char	*zbx_strpool_intern(const char *str)
{
	void		*record;
	zbx_uint32_t	*refcount;

	record = zbx_hashset_search(&config->strpool, str - REFCOUNT_FIELD_SIZE);

	if (NULL == record)
	{
		record = zbx_hashset_insert_ext(&config->strpool, str - REFCOUNT_FIELD_SIZE,
				REFCOUNT_FIELD_SIZE + strlen(str) + 1, REFCOUNT_FIELD_SIZE);
		*(zbx_uint32_t *)record = 0;
	}

	refcount = (zbx_uint32_t *)record;
	(*refcount)++;

	return (char *)record + REFCOUNT_FIELD_SIZE;
}

static void	zbx_strpool_release(const char *str)
{
	zbx_uint32_t	*refcount;

	refcount = (zbx_uint32_t *)(str - REFCOUNT_FIELD_SIZE);
	if (0 == --(*refcount))
		zbx_hashset_remove(&config->strpool, str - REFCOUNT_FIELD_SIZE);
}

static const char	*zbx_strpool_acquire(const char *str)
{
	zbx_uint32_t	*refcount;

	refcount = (zbx_uint32_t *)(str - REFCOUNT_FIELD_SIZE);
	(*refcount)++;

	return str;
}

static int	DCstrpool_replace(int found, const char **curr, const char *new_str)
{
	if (1 == found)
	{
		if (0 == strcmp(*curr, new_str))
			return FAIL;

		zbx_strpool_release(*curr);
	}

	*curr = zbx_strpool_intern(new_str);

	return SUCCEED;	/* indicate that the string has been replaced */
}

static void	DCupdate_item_queue(ZBX_DC_ITEM *item, unsigned char old_poller_type, int old_nextcheck)
{
	zbx_binary_heap_elem_t	elem;

	if (ZBX_LOC_POLLER == item->location)
		return;

	if (ZBX_LOC_QUEUE == item->location && old_poller_type != item->poller_type)
	{
		item->location = ZBX_LOC_NOWHERE;
		zbx_binary_heap_remove_direct(&config->queues[old_poller_type], item->itemid);
	}

	if (item->poller_type == ZBX_NO_POLLER)
		return;

	if (ZBX_LOC_QUEUE == item->location && old_nextcheck == item->nextcheck)
		return;

	elem.key = item->itemid;
	elem.data = (const void *)item;

	if (ZBX_LOC_QUEUE != item->location)
	{
		item->location = ZBX_LOC_QUEUE;
		zbx_binary_heap_insert(&config->queues[item->poller_type], &elem);
	}
	else
		zbx_binary_heap_update_direct(&config->queues[item->poller_type], &elem);
}

static void	DCupdate_proxy_queue(ZBX_DC_PROXY *proxy)
{
	zbx_binary_heap_elem_t	elem;

	if (ZBX_LOC_POLLER == proxy->location)
		return;

	proxy->nextcheck = proxy->proxy_tasks_nextcheck;
	if (proxy->proxy_data_nextcheck < proxy->nextcheck)
		proxy->nextcheck = proxy->proxy_data_nextcheck;
	if (proxy->proxy_config_nextcheck < proxy->nextcheck)
		proxy->nextcheck = proxy->proxy_config_nextcheck;

	elem.key = proxy->hostid;
	elem.data = (const void *)proxy;

	if (ZBX_LOC_QUEUE != proxy->location)
	{
		proxy->location = ZBX_LOC_QUEUE;
		zbx_binary_heap_insert(&config->pqueue, &elem);
	}
	else
		zbx_binary_heap_update_direct(&config->pqueue, &elem);
}

/******************************************************************************
 *                                                                            *
 * Function: config_gmacro_add_index                                          *
 *                                                                            *
 * Purpose: adds global macro index                                           *
 *                                                                            *
 * Parameters: gmacro_index - [IN/OUT] a global macro index hashset           *
 *             gmacro       - [IN] the macro to index                         *
 *                                                                            *
 ******************************************************************************/
static void	config_gmacro_add_index(zbx_hashset_t *gmacro_index, ZBX_DC_GMACRO *gmacro)
{
	ZBX_DC_GMACRO_M	*gmacro_m, gmacro_m_local;

	gmacro_m_local.macro = gmacro->macro;

	if (NULL == (gmacro_m = (ZBX_DC_GMACRO_M *)zbx_hashset_search(gmacro_index, &gmacro_m_local)))
	{
		gmacro_m_local.macro = zbx_strpool_acquire(gmacro->macro);
		zbx_vector_ptr_create_ext(&gmacro_m_local.gmacros, __config_mem_malloc_func, __config_mem_realloc_func,
				__config_mem_free_func);

		gmacro_m = (ZBX_DC_GMACRO_M *)zbx_hashset_insert(gmacro_index, &gmacro_m_local, sizeof(ZBX_DC_GMACRO_M));
	}

	zbx_vector_ptr_append(&gmacro_m->gmacros, gmacro);
}

/******************************************************************************
 *                                                                            *
 * Function: config_gmacro_remove_index                                       *
 *                                                                            *
 * Purpose: removes global macro index                                        *
 *                                                                            *
 * Parameters: gmacro_index - [IN/OUT] a global macro index hashset           *
 *             gmacro       - [IN] the macro to remove                        *
 *                                                                            *
 ******************************************************************************/
static void	config_gmacro_remove_index(zbx_hashset_t *gmacro_index, ZBX_DC_GMACRO *gmacro)
{
	ZBX_DC_GMACRO_M	*gmacro_m, gmacro_m_local;
	int		index;

	gmacro_m_local.macro = gmacro->macro;

	if (NULL != (gmacro_m = (ZBX_DC_GMACRO_M *)zbx_hashset_search(gmacro_index, &gmacro_m_local)))
	{
		if (FAIL != (index = zbx_vector_ptr_search(&gmacro_m->gmacros, gmacro, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			zbx_vector_ptr_remove(&gmacro_m->gmacros, index);

		if (0 == gmacro_m->gmacros.values_num)
		{
			zbx_strpool_release(gmacro_m->macro);
			zbx_vector_ptr_destroy(&gmacro_m->gmacros);
			zbx_hashset_remove(gmacro_index, &gmacro_m_local);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: config_hmacro_add_index                                          *
 *                                                                            *
 * Purpose: adds host macro index                                             *
 *                                                                            *
 * Parameters: hmacro_index - [IN/OUT] a host macro index hashset             *
 *             hmacro       - [IN] the macro to index                         *
 *                                                                            *
 ******************************************************************************/
static void	config_hmacro_add_index(zbx_hashset_t *hmacro_index, ZBX_DC_HMACRO *hmacro)
{
	ZBX_DC_HMACRO_HM	*hmacro_hm, hmacro_hm_local;

	hmacro_hm_local.hostid = hmacro->hostid;
	hmacro_hm_local.macro = hmacro->macro;

	if (NULL == (hmacro_hm = (ZBX_DC_HMACRO_HM *)zbx_hashset_search(hmacro_index, &hmacro_hm_local)))
	{
		hmacro_hm_local.macro = zbx_strpool_acquire(hmacro->macro);
		zbx_vector_ptr_create_ext(&hmacro_hm_local.hmacros, __config_mem_malloc_func, __config_mem_realloc_func,
				__config_mem_free_func);

		hmacro_hm = (ZBX_DC_HMACRO_HM *)zbx_hashset_insert(hmacro_index, &hmacro_hm_local, sizeof(ZBX_DC_HMACRO_HM));
	}

	zbx_vector_ptr_append(&hmacro_hm->hmacros, hmacro);
}

/******************************************************************************
 *                                                                            *
 * Function: config_hmacro_remove_index                                       *
 *                                                                            *
 * Purpose: removes host macro index                                          *
 *                                                                            *
 * Parameters: hmacro_index - [IN/OUT] a host macro index hashset             *
 *             hmacro       - [IN] the macro name to remove                   *
 *                                                                            *
 ******************************************************************************/
static void	config_hmacro_remove_index(zbx_hashset_t *hmacro_index, ZBX_DC_HMACRO *hmacro)
{
	ZBX_DC_HMACRO_HM	*hmacro_hm, hmacro_hm_local;
	int			index;

	hmacro_hm_local.hostid = hmacro->hostid;
	hmacro_hm_local.macro = hmacro->macro;

	if (NULL != (hmacro_hm = (ZBX_DC_HMACRO_HM *)zbx_hashset_search(hmacro_index, &hmacro_hm_local)))
	{
		if (FAIL != (index = zbx_vector_ptr_search(&hmacro_hm->hmacros, hmacro, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			zbx_vector_ptr_remove(&hmacro_hm->hmacros, index);

		if (0 == hmacro_hm->hmacros.values_num)
		{
			zbx_strpool_release(hmacro_hm->macro);
			zbx_vector_ptr_destroy(&hmacro_hm->hmacros);
			zbx_hashset_remove(hmacro_index, &hmacro_hm_local);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: set_hk_opt                                                       *
 *                                                                            *
 * Purpose: sets and validates global housekeeping option                     *
 *                                                                            *
 * Parameters: value     - [OUT] housekeeping setting                         *
 *             non_zero  - [IN] 0 if value is allowed to be zero, 1 otherwise *
 *             value_min - [IN] minimal acceptable setting value              *
 *             value_raw - [IN] setting value to validate                     *
 *                                                                            *
 ******************************************************************************/
static int	set_hk_opt(int *value, int non_zero, int value_min, const char *value_raw)
{
	if (SUCCEED != is_time_suffix(value_raw, value, ZBX_LENGTH_UNLIMITED))
		return FAIL;

	if (0 != non_zero && 0 == *value)
		return FAIL;

	if (0 != *value && value_min > *value)
		return FAIL;

	return SUCCEED;
}

static int	DCsync_config(zbx_dbsync_t *sync, int *flags)
{
#define SELECTED_FIELD_COUNT	27

	const char	*__function_name = "DCsync_config";
	const ZBX_TABLE	*config_table;
	const char	*selected_fields[] = {"refresh_unsupported", "discovery_groupid", "snmptrap_logging",
					"severity_name_0", "severity_name_1", "severity_name_2", "severity_name_3",
					"severity_name_4", "severity_name_5", "hk_events_mode", "hk_events_trigger",
					"hk_events_internal", "hk_events_discovery", "hk_events_autoreg",
					"hk_services_mode", "hk_services", "hk_audit_mode", "hk_audit",
					"hk_sessions_mode", "hk_sessions", "hk_history_mode", "hk_history_global",
					"hk_history", "hk_trends_mode", "hk_trends_global", "hk_trends",
					"default_inventory_mode"};	/* sync with zbx_dbsync_compare_config() */
	const char	*row[SELECTED_FIELD_COUNT];
	size_t		i;
	int		j, found = 1, refresh_unsupported, ret;
	char		**db_row;
	zbx_uint64_t	rowid;
	unsigned char	tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	*flags = 0;

	if (NULL == config->config)
	{
		found = 0;
		config->config = (ZBX_DC_CONFIG_TABLE *)__config_mem_malloc_func(NULL, sizeof(ZBX_DC_CONFIG_TABLE));
	}

	if (SUCCEED != (ret = zbx_dbsync_next(sync, &rowid, &db_row, &tag)))
	{
		/* load default config data */

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			zabbix_log(LOG_LEVEL_ERR, "no records in table 'config'");

		config_table = DBget_table("config");

		for (i = 0; i < SELECTED_FIELD_COUNT; i++)
			row[i] = DBget_field(config_table, selected_fields[i])->default_value;
	}
	else
	{
		for (i = 0; i < SELECTED_FIELD_COUNT; i++)
			row[i] = db_row[i];
	}

	/* store the config data */

	if (SUCCEED != is_time_suffix(row[0], &refresh_unsupported, ZBX_LENGTH_UNLIMITED))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid unsupported item refresh interval, restoring default");

		config_table = DBget_table("config");

		if (SUCCEED != is_time_suffix(DBget_field(config_table, "refresh_unsupported")->default_value,
				&refresh_unsupported, ZBX_LENGTH_UNLIMITED))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			refresh_unsupported = 0;
		}
	}

	if (0 == found || config->config->refresh_unsupported != refresh_unsupported)
		*flags |= ZBX_REFRESH_UNSUPPORTED_CHANGED;

	config->config->refresh_unsupported = refresh_unsupported;

	if (NULL != row[1])
		ZBX_STR2UINT64(config->config->discovery_groupid, row[1]);
	else
		config->config->discovery_groupid = ZBX_DISCOVERY_GROUPID_UNDEFINED;

	config->config->snmptrap_logging = (unsigned char)atoi(row[2]);
	config->config->default_inventory_mode = atoi(row[26]);

	for (j = 0; TRIGGER_SEVERITY_COUNT > j; j++)
		DCstrpool_replace(found, &config->config->severity_name[j], row[3 + j]);

#if TRIGGER_SEVERITY_COUNT != 6
#	error "row indexes below are based on assumption of six trigger severity levels"
#endif

	/* read housekeeper configuration */

	if (ZBX_HK_OPTION_ENABLED == (config->config->hk.events_mode = atoi(row[9])) &&
			(SUCCEED != set_hk_opt(&config->config->hk.events_trigger, 1, SEC_PER_DAY, row[10]) ||
			SUCCEED != set_hk_opt(&config->config->hk.events_internal, 1, SEC_PER_DAY, row[11]) ||
			SUCCEED != set_hk_opt(&config->config->hk.events_discovery, 1, SEC_PER_DAY, row[12]) ||
			SUCCEED != set_hk_opt(&config->config->hk.events_autoreg, 1, SEC_PER_DAY, row[13])))
	{
		zabbix_log(LOG_LEVEL_WARNING, "trigger, internal, network discovery and auto-registration data"
				" housekeeping will be disabled due to invalid settings");
		config->config->hk.events_mode = ZBX_HK_OPTION_DISABLED;
	}

	if (ZBX_HK_OPTION_ENABLED == (config->config->hk.services_mode = atoi(row[14])) &&
			SUCCEED != set_hk_opt(&config->config->hk.services, 1, SEC_PER_DAY, row[15]))
	{
		zabbix_log(LOG_LEVEL_WARNING, "IT services data housekeeping will be disabled due to invalid"
				" settings");
		config->config->hk.services_mode = ZBX_HK_OPTION_DISABLED;
	}

	if (ZBX_HK_OPTION_ENABLED == (config->config->hk.audit_mode = atoi(row[16])) &&
			SUCCEED != set_hk_opt(&config->config->hk.audit, 1, SEC_PER_DAY, row[17]))
	{
		zabbix_log(LOG_LEVEL_WARNING, "audit data housekeeping will be disabled due to invalid"
				" settings");
		config->config->hk.audit_mode = ZBX_HK_OPTION_DISABLED;
	}

	if (ZBX_HK_OPTION_ENABLED == (config->config->hk.sessions_mode = atoi(row[18])) &&
			SUCCEED != set_hk_opt(&config->config->hk.sessions, 1, SEC_PER_DAY, row[19]))
	{
		zabbix_log(LOG_LEVEL_WARNING, "user sessions data housekeeping will be disabled due to invalid"
				" settings");
		config->config->hk.sessions_mode = ZBX_HK_OPTION_DISABLED;
	}

	config->config->hk.history_mode = atoi(row[20]);
	if (ZBX_HK_OPTION_ENABLED == (config->config->hk.history_global = atoi(row[21])) &&
			SUCCEED != set_hk_opt(&config->config->hk.history, 0, ZBX_HK_HISTORY_MIN, row[22]))
	{
		zabbix_log(LOG_LEVEL_WARNING, "history data housekeeping will be disabled and all items will"
				" store their history due to invalid global override settings");
		config->config->hk.history_mode = ZBX_HK_OPTION_DISABLED;
		config->config->hk.history = 1;	/* just enough to make 0 == items[i].history condition fail */
	}

	config->config->hk.trends_mode = atoi(row[23]);
	if (ZBX_HK_OPTION_ENABLED == (config->config->hk.trends_global = atoi(row[24])) &&
			SUCCEED != set_hk_opt(&config->config->hk.trends, 0, ZBX_HK_TRENDS_MIN, row[25]))
	{
		zabbix_log(LOG_LEVEL_WARNING, "trends data housekeeping will be disabled and all numeric items"
				" will store their history due to invalid global override settings");
		config->config->hk.trends_mode = ZBX_HK_OPTION_DISABLED;
		config->config->hk.trends = 1;	/* just enough to make 0 == items[i].trends condition fail */
	}

	if (SUCCEED == ret && SUCCEED == zbx_dbsync_next(sync, &rowid, &db_row, &tag))	/* table must have */
		zabbix_log(LOG_LEVEL_ERR, "table 'config' has multiple records");	/* only one record */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;

#undef SELECTED_FIELD_COUNT
}

static void	DCsync_hosts(zbx_dbsync_t *sync)
{
	const char	*__function_name = "DCsync_hosts";

	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;

	ZBX_DC_HOST	*host;
	ZBX_DC_IPMIHOST	*ipmihost;
	ZBX_DC_PROXY	*proxy;
	ZBX_DC_HOST_H	*host_h, host_h_local, *host_p, host_p_local;

	int		found;
	int		update_index_h, update_index_p, ret;
	zbx_uint64_t	hostid, proxy_hostid;
	unsigned char	status;
	time_t		now;
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	ZBX_DC_PSK	*psk_i, psk_i_local;
	zbx_ptr_pair_t	*psk_owner, psk_owner_local;
	zbx_hashset_t	psk_owners;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_hashset_create(&psk_owners, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);
#endif
	now = time(NULL);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_DBROW2UINT64(proxy_hostid, row[1]);
		ZBX_STR2UCHAR(status, row[22]);

		host = (ZBX_DC_HOST *)DCfind_id(&config->hosts, hostid, sizeof(ZBX_DC_HOST), &found);

		/* see whether we should and can update 'hosts_h' and 'hosts_p' indexes at this point */

		update_index_h = 0;
		update_index_p = 0;

		if ((HOST_STATUS_MONITORED == status || HOST_STATUS_NOT_MONITORED == status) &&
				(0 == found || 0 != strcmp(host->host, row[2])))
		{
			if (1 == found)
			{
				host_h_local.host = host->host;
				host_h = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_h, &host_h_local);

				if (NULL != host_h && host == host_h->host_ptr)	/* see ZBX-4045 for NULL check */
				{
					zbx_strpool_release(host_h->host);
					zbx_hashset_remove_direct(&config->hosts_h, host_h);
				}
			}

			host_h_local.host = row[2];
			host_h = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_h, &host_h_local);

			if (NULL != host_h)
				host_h->host_ptr = host;
			else
				update_index_h = 1;
		}
		else if ((HOST_STATUS_PROXY_ACTIVE == status || HOST_STATUS_PROXY_PASSIVE == status) &&
				(0 == found || 0 != strcmp(host->host, row[2])))
		{
			if (1 == found)
			{
				host_p_local.host = host->host;
				host_p = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_p, &host_p_local);

				if (NULL != host_p && host == host_p->host_ptr)
				{
					zbx_strpool_release(host_p->host);
					zbx_hashset_remove_direct(&config->hosts_p, host_p);
				}
			}

			host_p_local.host = row[2];
			host_p = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_p, &host_p_local);

			if (NULL != host_p)
				host_p->host_ptr = host;
			else
				update_index_p = 1;
		}

		/* store new information in host structure */

		DCstrpool_replace(found, &host->host, row[2]);
		DCstrpool_replace(found, &host->name, row[23]);
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		DCstrpool_replace(found, &host->proxy_address, row[35]);
		DCstrpool_replace(found, &host->tls_issuer, row[31]);
		DCstrpool_replace(found, &host->tls_subject, row[32]);

		/* maintain 'config->psks' in configuration cache */

		/*****************************************************************************/
		/*                                                                           */
		/* cases to cover (PSKid means PSK identity):                                */
		/*                                                                           */
		/*                                  Incoming data record                     */
		/*                                  /                   \                    */
		/*                                new                   new                  */
		/*                               PSKid                 PSKid                 */
		/*                             non-empty               empty                 */
		/*                             /      \                /    \                */
		/*                            /        \              /      \               */
		/*                       'host'        'host'      'host'    'host'          */
		/*                       record        record      record    record          */
		/*                        has           has         has       has            */
		/*                     non-empty       empty     non-empty  empty PSK        */
		/*                        PSK           PSK         PSK      |     \         */
		/*                       /   \           |           |       |      \        */
		/*                      /     \          |           |       |       \       */
		/*                     /       \         |           |       |        \      */
		/*            new PSKid       new PSKid  |           |   existing     new    */
		/*             same as         differs   |           |    record     record  */
		/*            old PSKid         from     |           |      |          |     */
		/*           /    |           old PSKid  |           |     done        |     */
		/*          /     |              |       |           |                 |     */
		/*   new PSK    new PSK        delete    |        delete               |     */
		/*    value      value        old PSKid  |       old PSKid             |     */
		/*   same as    differs       and value  |       and value             |     */
		/*     old       from         from psks  |       from psks             |     */
		/*      |        old          hashset    |        hashset              |     */
		/*     done       /           (if ref    |        (if ref              |     */
		/*               /            count=0)   |        count=0)             |     */
		/*              /              /     \  /|           \                /      */
		/*             /              /--------- |            \              /       */
		/*            /              /         \ |             \            /        */
		/*       delete          new PSKid   new PSKid         set pointer in        */
		/*       old PSK          already     not in           'hosts' record        */
		/*        value           in psks      psks             to NULL PSK          */
		/*        from            hashset     hashset                |               */
		/*       string            /   \          \                 done             */
		/*        pool            /     \          \                                 */
		/*         |             /       \          \                                */
		/*       change    PSK value   PSK value    insert                           */
		/*      PSK value  in hashset  in hashset  new PSKid                         */
		/*      for this    same as     differs    and value                         */
		/*       PSKid      new PSK     from new   into psks                         */
		/*         |        value      PSK value    hashset                          */
		/*        done        \           |            /                             */
		/*                     \       replace        /                              */
		/*                      \      PSK value     /                               */
		/*                       \     in hashset   /                                */
		/*                        \    with new    /                                 */
		/*                         \   PSK value  /                                  */
		/*                          \     |      /                                   */
		/*                           \    |     /                                    */
		/*                            set pointer                                    */
		/*                            in 'host'                                      */
		/*                            record to                                      */
		/*                            new PSKid                                      */
		/*                                |                                          */
		/*                               done                                        */
		/*                                                                           */
		/*****************************************************************************/

		psk_owner = NULL;

		if ('\0' == *row[33] || '\0' == *row[34])	/* new PSKid or value empty */
		{
			/* In case of "impossible" errors ("PSK value without identity" or "PSK identity without */
			/* value") assume empty PSK identity and value. These errors should have been prevented */
			/* by validation in frontend/API. Be prepared when making a connection requiring PSK - */
			/* the PSK might not be available. */

			if (1 == found)
			{
				if (NULL == host->tls_dc_psk)	/* 'host' record has empty PSK */
					goto done;

				/* 'host' record has non-empty PSK. Unlink and delete PSK. */

				psk_i_local.tls_psk_identity = host->tls_dc_psk->tls_psk_identity;

				if (NULL != (psk_i = (ZBX_DC_PSK *)zbx_hashset_search(&config->psks, &psk_i_local)) &&
						0 == --(psk_i->refcount))
				{
					zbx_strpool_release(psk_i->tls_psk_identity);
					zbx_strpool_release(psk_i->tls_psk);
					zbx_hashset_remove_direct(&config->psks, psk_i);
				}
			}

			host->tls_dc_psk = NULL;
			goto done;
		}

		/* new PSKid and value non-empty */

		zbx_strlower(row[34]);

		if (1 == found && NULL != host->tls_dc_psk)	/* 'host' record has non-empty PSK */
		{
			if (0 == strcmp(host->tls_dc_psk->tls_psk_identity, row[33]))	/* new PSKid same as */
											/* old PSKid */
			{
				if (0 != strcmp(host->tls_dc_psk->tls_psk, row[34]))	/* new PSK value */
											/* differs from old */
				{
					if (NULL == (psk_owner = (zbx_ptr_pair_t *)zbx_hashset_search(&psk_owners,
							&host->tls_dc_psk->tls_psk_identity)))
					{
						/* change underlying PSK value and 'config->psks' is updated, too */
						DCstrpool_replace(1, &host->tls_dc_psk->tls_psk, row[34]);
					}
					else
					{
						zabbix_log(LOG_LEVEL_WARNING, "conflicting PSK values for PSK identity"
								" \"%s\" on hosts \"%s\" and \"%s\" (and maybe others)",
								psk_owner->first, psk_owner->second, host->host);
					}
				}

				goto done;
			}

			/* New PSKid differs from old PSKid. Unlink and delete old PSK. */

			psk_i_local.tls_psk_identity = host->tls_dc_psk->tls_psk_identity;

			if (NULL != (psk_i = (ZBX_DC_PSK *)zbx_hashset_search(&config->psks, &psk_i_local)) &&
					0 == --(psk_i->refcount))
			{
				zbx_strpool_release(psk_i->tls_psk_identity);
				zbx_strpool_release(psk_i->tls_psk);
				zbx_hashset_remove_direct(&config->psks, psk_i);
			}

			host->tls_dc_psk = NULL;
		}

		/* new PSK identity already stored? */

		psk_i_local.tls_psk_identity = row[33];

		if (NULL != (psk_i = (ZBX_DC_PSK *)zbx_hashset_search(&config->psks, &psk_i_local)))
		{
			/* new PSKid already in psks hashset */

			if (0 != strcmp(psk_i->tls_psk, row[34]))	/* PSKid stored but PSK value is different */
			{
				if (NULL == (psk_owner = (zbx_ptr_pair_t *)zbx_hashset_search(&psk_owners, &psk_i->tls_psk_identity)))
				{
					DCstrpool_replace(1, &psk_i->tls_psk, row[34]);
				}
				else
				{
					zabbix_log(LOG_LEVEL_WARNING, "conflicting PSK values for PSK identity"
							" \"%s\" on hosts \"%s\" and \"%s\" (and maybe others)",
							psk_owner->first, psk_owner->second, host->host);
				}
			}

			host->tls_dc_psk = psk_i;
			psk_i->refcount++;
			goto done;
		}

		/* insert new PSKid and value into psks hashset */

		DCstrpool_replace(0, &psk_i_local.tls_psk_identity, row[33]);
		DCstrpool_replace(0, &psk_i_local.tls_psk, row[34]);
		psk_i_local.refcount = 1;
		host->tls_dc_psk = zbx_hashset_insert(&config->psks, &psk_i_local, sizeof(ZBX_DC_PSK));
done:
		if (NULL != host->tls_dc_psk && NULL == psk_owner)
		{
			if (NULL == (psk_owner = (zbx_ptr_pair_t *)zbx_hashset_search(&psk_owners, &host->tls_dc_psk->tls_psk_identity)))
			{
				/* register this host as the PSK identity owner, against which to report conflicts */

				psk_owner_local.first = (char *)host->tls_dc_psk->tls_psk_identity;
				psk_owner_local.second = (char *)host->host;

				zbx_hashset_insert(&psk_owners, &psk_owner_local, sizeof(psk_owner_local));
			}
		}
#else
		DCstrpool_replace(found, &host->proxy_address, row[31]);
#endif
		ZBX_STR2UCHAR(host->tls_connect, row[29]);
		ZBX_STR2UCHAR(host->tls_accept, row[30]);

		if (0 == found)
		{
			host->maintenance_status = (unsigned char)atoi(row[7]);
			host->maintenance_type = (unsigned char)atoi(row[8]);
			host->maintenance_from = atoi(row[9]);
			host->data_expected_from = now;
			host->update_items = 0;

			host->errors_from = atoi(row[10]);
			host->available = (unsigned char)atoi(row[11]);
			host->disable_until = atoi(row[12]);
			host->snmp_errors_from = atoi(row[13]);
			host->snmp_available = (unsigned char)atoi(row[14]);
			host->snmp_disable_until = atoi(row[15]);
			host->ipmi_errors_from = atoi(row[16]);
			host->ipmi_available = (unsigned char)atoi(row[17]);
			host->ipmi_disable_until = atoi(row[18]);
			host->jmx_errors_from = atoi(row[19]);
			host->jmx_available = (unsigned char)atoi(row[20]);
			host->jmx_disable_until = atoi(row[21]);
			host->availability_ts = now;

			DCstrpool_replace(0, &host->error, row[25]);
			DCstrpool_replace(0, &host->snmp_error, row[26]);
			DCstrpool_replace(0, &host->ipmi_error, row[27]);
			DCstrpool_replace(0, &host->jmx_error, row[28]);

			host->items_num = 0;
			host->snmp_items_num = 0;
			host->ipmi_items_num = 0;
			host->jmx_items_num = 0;

			host->reset_availability = 0;

			zbx_vector_ptr_create_ext(&host->interfaces_v, __config_mem_malloc_func,
					__config_mem_realloc_func, __config_mem_free_func);
		}
		else
		{
			if (HOST_STATUS_MONITORED == status && HOST_STATUS_MONITORED != host->status)
				host->data_expected_from = now;

			/* reset host status if host proxy assignment has been changed */
			if (proxy_hostid != host->proxy_hostid)
				host->reset_availability = 1;
		}

		host->proxy_hostid = proxy_hostid;

		/* update 'hosts_h' and 'hosts_p' indexes using new data, if not done already */

		if (1 == update_index_h)
		{
			host_h_local.host = zbx_strpool_acquire(host->host);
			host_h_local.host_ptr = host;
			zbx_hashset_insert(&config->hosts_h, &host_h_local, sizeof(ZBX_DC_HOST_H));
		}

		if (1 == update_index_p)
		{
			host_p_local.host = zbx_strpool_acquire(host->host);
			host_p_local.host_ptr = host;
			zbx_hashset_insert(&config->hosts_p, &host_p_local, sizeof(ZBX_DC_HOST_H));
		}

		/* IPMI hosts */

		ipmi_authtype = (signed char)atoi(row[3]);
		ipmi_privilege = (unsigned char)atoi(row[4]);

		if (ZBX_IPMI_DEFAULT_AUTHTYPE != ipmi_authtype || ZBX_IPMI_DEFAULT_PRIVILEGE != ipmi_privilege ||
				'\0' != *row[5] || '\0' != *row[6])	/* useipmi */
		{
			ipmihost = (ZBX_DC_IPMIHOST *)DCfind_id(&config->ipmihosts, hostid, sizeof(ZBX_DC_IPMIHOST), &found);

			ipmihost->ipmi_authtype = ipmi_authtype;
			ipmihost->ipmi_privilege = ipmi_privilege;
			DCstrpool_replace(found, &ipmihost->ipmi_username, row[5]);
			DCstrpool_replace(found, &ipmihost->ipmi_password, row[6]);
		}
		else if (NULL != (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_search(&config->ipmihosts, &hostid)))
		{
			/* remove IPMI connection parameters for hosts without IPMI */

			zbx_strpool_release(ipmihost->ipmi_username);
			zbx_strpool_release(ipmihost->ipmi_password);

			zbx_hashset_remove_direct(&config->ipmihosts, ipmihost);
		}

		/* proxies */

		if (HOST_STATUS_PROXY_ACTIVE == status || HOST_STATUS_PROXY_PASSIVE == status)
		{
			proxy = (ZBX_DC_PROXY *)DCfind_id(&config->proxies, hostid, sizeof(ZBX_DC_PROXY), &found);

			if (0 == found)
			{
				proxy->timediff = 0;
				proxy->location = ZBX_LOC_NOWHERE;
				proxy->version = 0;
				proxy->lastaccess = atoi(row[24]);
				proxy->last_cfg_error_time = 0;
			}

			if (HOST_STATUS_PROXY_PASSIVE == status && (0 == found || status != host->status))
			{
				proxy->proxy_config_nextcheck = (int)calculate_proxy_nextcheck(
						hostid, CONFIG_PROXYCONFIG_FREQUENCY, now);
				proxy->proxy_data_nextcheck = (int)calculate_proxy_nextcheck(
						hostid, CONFIG_PROXYDATA_FREQUENCY, now);
				proxy->proxy_tasks_nextcheck = (int)calculate_proxy_nextcheck(
						hostid, ZBX_TASK_UPDATE_FREQUENCY, now);

				DCupdate_proxy_queue(proxy);
			}
			else if (HOST_STATUS_PROXY_ACTIVE == status && ZBX_LOC_QUEUE == proxy->location)
			{
				zbx_binary_heap_remove_direct(&config->pqueue, proxy->hostid);
				proxy->location = ZBX_LOC_NOWHERE;
			}
		}
		else if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &hostid)))
		{
			if (ZBX_LOC_QUEUE == proxy->location)
			{
				zbx_binary_heap_remove_direct(&config->pqueue, proxy->hostid);
				proxy->location = ZBX_LOC_NOWHERE;
			}

			zbx_hashset_remove_direct(&config->proxies, proxy);
		}

		host->status = status;
	}

	/* remove deleted hosts from buffer */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &rowid)))
			continue;

		hostid = host->hostid;

		/* IPMI hosts */

		if (NULL != (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_search(&config->ipmihosts, &hostid)))
		{
			zbx_strpool_release(ipmihost->ipmi_username);
			zbx_strpool_release(ipmihost->ipmi_password);

			zbx_hashset_remove_direct(&config->ipmihosts, ipmihost);
		}

		/* proxies */

		if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &hostid)))
		{
			if (ZBX_LOC_QUEUE == proxy->location)
			{
				zbx_binary_heap_remove_direct(&config->pqueue, proxy->hostid);
				proxy->location = ZBX_LOC_NOWHERE;
			}

			zbx_hashset_remove_direct(&config->proxies, proxy);
		}

		/* hosts */

		if (HOST_STATUS_MONITORED == host->status || HOST_STATUS_NOT_MONITORED == host->status)
		{
			host_h_local.host = host->host;
			host_h = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_h, &host_h_local);

			if (NULL != host_h && host == host_h->host_ptr)	/* see ZBX-4045 for NULL check */
			{
				zbx_strpool_release(host_h->host);
				zbx_hashset_remove_direct(&config->hosts_h, host_h);
			}
		}
		else if (HOST_STATUS_PROXY_ACTIVE == host->status || HOST_STATUS_PROXY_PASSIVE == host->status)
		{
			host_p_local.host = host->host;
			host_p = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_p, &host_p_local);

			if (NULL != host_p && host == host_p->host_ptr)
			{
				zbx_strpool_release(host_p->host);
				zbx_hashset_remove_direct(&config->hosts_p, host_p);
			}
		}

		zbx_strpool_release(host->host);
		zbx_strpool_release(host->name);
		zbx_strpool_release(host->proxy_address);

		zbx_strpool_release(host->error);
		zbx_strpool_release(host->snmp_error);
		zbx_strpool_release(host->ipmi_error);
		zbx_strpool_release(host->jmx_error);
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_strpool_release(host->tls_issuer);
		zbx_strpool_release(host->tls_subject);

		/* Maintain 'psks' index. Unlink and delete the PSK identity. */
		if (NULL != host->tls_dc_psk)
		{
			psk_i_local.tls_psk_identity = host->tls_dc_psk->tls_psk_identity;

			if (NULL != (psk_i = (ZBX_DC_PSK *)zbx_hashset_search(&config->psks, &psk_i_local)) &&
					0 == --(psk_i->refcount))
			{
				zbx_strpool_release(psk_i->tls_psk_identity);
				zbx_strpool_release(psk_i->tls_psk);
				zbx_hashset_remove_direct(&config->psks, psk_i);
			}
		}
#endif
		zbx_vector_ptr_destroy(&host->interfaces_v);
		zbx_hashset_remove_direct(&config->hosts, host);
	}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_hashset_destroy(&psk_owners);
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_host_inventory(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_host_inventory";

	ZBX_DC_HOST_INVENTORY	*host_inventory, *host_inventory_auto;
	zbx_uint64_t		rowid, hostid;
	int			found, ret, i;
	char			**row;
	unsigned char		tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostid, row[0]);

		host_inventory = (ZBX_DC_HOST_INVENTORY *)DCfind_id(&config->host_inventories, hostid, sizeof(ZBX_DC_HOST_INVENTORY), &found);

		ZBX_STR2UCHAR(host_inventory->inventory_mode, row[1]);

		/* store new information in host_inventory structure */
		for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
			DCstrpool_replace(found, &(host_inventory->values[i]), row[i + 2]);

		host_inventory_auto = (ZBX_DC_HOST_INVENTORY *)DCfind_id(&config->host_inventories_auto, hostid, sizeof(ZBX_DC_HOST_INVENTORY),
				&found);

		host_inventory_auto->inventory_mode = host_inventory->inventory_mode;

		if (1 == found)
		{
			for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
			{
				if (NULL == host_inventory_auto->values[i])
					continue;

				zbx_strpool_release(host_inventory_auto->values[i]);
				host_inventory_auto->values[i] = NULL;
			}
		}
		else
		{
			for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
				host_inventory_auto->values[i] = NULL;
		}
	}

	/* remove deleted host inventory from cache */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (host_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories, &rowid)))
			continue;

		for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
			zbx_strpool_release(host_inventory->values[i]);

		zbx_hashset_remove_direct(&config->host_inventories, host_inventory);

		if (NULL == (host_inventory_auto = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories_auto, &rowid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
		{
			if (NULL != host_inventory_auto->values[i])
				zbx_strpool_release(host_inventory_auto->values[i]);
		}

		zbx_hashset_remove_direct(&config->host_inventories_auto, host_inventory_auto);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_htmpls(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_htmpls";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_HTMPL		*htmpl = NULL;

	int			found, i, index, ret;
	zbx_uint64_t		_hostid = 0, hostid, templateid;
	zbx_vector_ptr_t	sort;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&sort);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_STR2UINT64(templateid, row[1]);

		if (_hostid != hostid || 0 == _hostid)
		{
			_hostid = hostid;

			htmpl = (ZBX_DC_HTMPL *)DCfind_id(&config->htmpls, hostid, sizeof(ZBX_DC_HTMPL), &found);

			if (0 == found)
			{
				zbx_vector_uint64_create_ext(&htmpl->templateids,
						__config_mem_malloc_func,
						__config_mem_realloc_func,
						__config_mem_free_func);
				zbx_vector_uint64_reserve(&htmpl->templateids, 1);
			}

			zbx_vector_ptr_append(&sort, htmpl);
		}

		zbx_vector_uint64_append(&htmpl->templateids, templateid);
	}

	/* remove deleted host templates from cache */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		if (NULL == (htmpl = (ZBX_DC_HTMPL *)zbx_hashset_search(&config->htmpls, &hostid)))
			continue;

		ZBX_STR2UINT64(templateid, row[1]);

		if (-1 == (index = zbx_vector_uint64_search(&htmpl->templateids, templateid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			continue;
		}

		if (1 == htmpl->templateids.values_num)
		{
			zbx_vector_uint64_destroy(&htmpl->templateids);
			zbx_hashset_remove_direct(&config->htmpls, htmpl);
		}
		else
		{
			zbx_vector_uint64_remove_noorder(&htmpl->templateids, index);
			zbx_vector_ptr_append(&sort, htmpl);
		}
	}

	/* sort the changed template lists */

	zbx_vector_ptr_sort(&sort, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(&sort, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < sort.values_num; i++)
	{
		htmpl = (ZBX_DC_HTMPL *)sort.values[i];
		zbx_vector_uint64_sort(&htmpl->templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_ptr_destroy(&sort);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_gmacros(zbx_dbsync_t *sync)
{
	const char	*__function_name = "DCsync_gmacros";

	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;

	ZBX_DC_GMACRO	*gmacro;

	int		found, context_existed, update_index, ret;
	zbx_uint64_t	globalmacroid;
	char		*macro = NULL, *context = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(globalmacroid, row[0]);

		if (SUCCEED != zbx_user_macro_parse_dyn(row[1], &macro, &context, NULL))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse user macro \"%s\"", row[1]);
			continue;
		}

		gmacro = (ZBX_DC_GMACRO *)DCfind_id(&config->gmacros, globalmacroid, sizeof(ZBX_DC_GMACRO), &found);

		/* see whether we should and can update gmacros_m index at this point */
		update_index = 0;

		if (0 == found || 0 != strcmp(gmacro->macro, macro) || 0 != zbx_strcmp_null(gmacro->context, context))
		{
			if (1 == found)
				config_gmacro_remove_index(&config->gmacros_m, gmacro);

			update_index = 1;
		}

		/* store new information in macro structure */
		DCstrpool_replace(found, &gmacro->macro, macro);
		DCstrpool_replace(found, &gmacro->value, row[2]);

		context_existed = (1 == found && NULL != gmacro->context);

		if (NULL == context)
		{
			/* release the context if it was removed from the macro */
			if (1 == context_existed)
				zbx_strpool_release(gmacro->context);

			gmacro->context = NULL;
		}
		else
		{
			/* replace the existing context (1) or add context to macro (0) */
			DCstrpool_replace(context_existed, &gmacro->context, context);
		}

		/* update gmacros_m index using new data */
		if (1 == update_index)
			config_gmacro_add_index(&config->gmacros_m, gmacro);
	}

	/* remove deleted globalmacros from buffer */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (gmacro = (ZBX_DC_GMACRO *)zbx_hashset_search(&config->gmacros, &rowid)))
			continue;

		config_gmacro_remove_index(&config->gmacros_m, gmacro);

		zbx_strpool_release(gmacro->macro);
		zbx_strpool_release(gmacro->value);

		if (NULL != gmacro->context)
			zbx_strpool_release(gmacro->context);

		zbx_hashset_remove_direct(&config->gmacros, gmacro);
	}

	zbx_free(context);
	zbx_free(macro);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_hmacros(zbx_dbsync_t *sync)
{
	const char	*__function_name = "DCsync_hmacros";

	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;

	ZBX_DC_HMACRO	*hmacro;

	int		found, context_existed, update_index, ret;
	zbx_uint64_t	hostmacroid, hostid;
	char		*macro = NULL, *context = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostmacroid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);

		if (SUCCEED != zbx_user_macro_parse_dyn(row[2], &macro, &context, NULL))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse host \"%s\" macro \"%s\"", row[1], row[2]);
			continue;
		}

		hmacro = (ZBX_DC_HMACRO *)DCfind_id(&config->hmacros, hostmacroid, sizeof(ZBX_DC_HMACRO), &found);

		/* see whether we should and can update hmacros_hm index at this point */
		update_index = 0;

		if (0 == found || hmacro->hostid != hostid || 0 != strcmp(hmacro->macro, macro) ||
				0 != zbx_strcmp_null(hmacro->context, context))
		{
			if (1 == found)
				config_hmacro_remove_index(&config->hmacros_hm, hmacro);

			update_index = 1;
		}

		/* store new information in macro structure */
		hmacro->hostid = hostid;
		DCstrpool_replace(found, &hmacro->macro, macro);
		DCstrpool_replace(found, &hmacro->value, row[3]);

		context_existed = (1 == found && NULL != hmacro->context);

		if (NULL == context)
		{
			/* release the context if it was removed from the macro */
			if (1 == context_existed)
				zbx_strpool_release(hmacro->context);

			hmacro->context = NULL;
		}
		else
		{
			/* replace the existing context (1) or add context to macro (0) */
			DCstrpool_replace(context_existed, &hmacro->context, context);
		}

		/* update hmacros_hm index using new data */
		if (1 == update_index)
			config_hmacro_add_index(&config->hmacros_hm, hmacro);
	}

	/* remove deleted host macros from buffer */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (hmacro = (ZBX_DC_HMACRO *)zbx_hashset_search(&config->hmacros, &rowid)))
			continue;

		config_hmacro_remove_index(&config->hmacros_hm, hmacro);

		zbx_strpool_release(hmacro->macro);
		zbx_strpool_release(hmacro->value);

		if (NULL != hmacro->context)
			zbx_strpool_release(hmacro->context);

		zbx_hashset_remove_direct(&config->hmacros, hmacro);
	}

	zbx_free(context);
	zbx_free(macro);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_host_interface_macros                                 *
 *                                                                            *
 * Purpose: trying to resolve the macros in host inteface                     *
 *                                                                            *
 ******************************************************************************/
static void	substitute_host_interface_macros(ZBX_DC_INTERFACE *interface)
{
	int	macros;
	char	*addr;
	DC_HOST	host;

	macros = STR_CONTAINS_MACROS(interface->ip) ? 0x01 : 0;
	macros |= STR_CONTAINS_MACROS(interface->dns) ? 0x02 : 0;

	if (0 != macros)
	{
		DCget_host_by_hostid(&host, interface->hostid);

		if (0 != (macros & 0x01))
		{
			addr = zbx_strdup(NULL, interface->ip);
			substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL, NULL,
					&addr, MACRO_TYPE_INTERFACE_ADDR, NULL, 0);
			DCstrpool_replace(1, &interface->ip, addr);
			zbx_free(addr);
		}

		if (0 != (macros & 0x02))
		{
			addr = zbx_strdup(NULL, interface->dns);
			substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL, NULL,
					&addr, MACRO_TYPE_INTERFACE_ADDR, NULL, 0);
			DCstrpool_replace(1, &interface->dns, addr);
			zbx_free(addr);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dc_interface_snmpaddrs_remove                                    *
 *                                                                            *
 * Purpose: remove interface from SNMP address -> interfaceid index           *
 *                                                                            *
 * Parameters: interface - [IN] the interface                                 *
 *                                                                            *
 ******************************************************************************/
static void	dc_interface_snmpaddrs_remove(ZBX_DC_INTERFACE *interface)
{
	ZBX_DC_INTERFACE_ADDR	*ifaddr, ifaddr_local;
	int			index;

	ifaddr_local.addr = (0 != interface->useip ? interface->ip : interface->dns);

	if ('\0' == *ifaddr_local.addr)
		return;

	if (NULL == (ifaddr = (ZBX_DC_INTERFACE_ADDR *)zbx_hashset_search(&config->interface_snmpaddrs, &ifaddr_local)))
		return;

	if (FAIL == (index = zbx_vector_uint64_search(&ifaddr->interfaceids, interface->interfaceid,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		return;
	}

	zbx_vector_uint64_remove_noorder(&ifaddr->interfaceids, index);

	if (0 == ifaddr->interfaceids.values_num)
	{
		zbx_strpool_release(ifaddr->addr);
		zbx_vector_uint64_destroy(&ifaddr->interfaceids);
		zbx_hashset_remove_direct(&config->interface_snmpaddrs, ifaddr);
	}
}

static void	DCsync_interfaces(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_interfaces";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_INTERFACE	*interface;
	ZBX_DC_INTERFACE_HT	*interface_ht, interface_ht_local;
	ZBX_DC_INTERFACE_ADDR	*interface_snmpaddr, interface_snmpaddr_local;

	int			found, update_index, ret, i;
	zbx_uint64_t		interfaceid, hostid;
	unsigned char		type, main_, useip;
	unsigned char		bulk, reset_snmp_stats;
	zbx_vector_ptr_t	interfaces;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&interfaces);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(interfaceid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);
		ZBX_STR2UCHAR(type, row[2]);
		ZBX_STR2UCHAR(main_, row[3]);
		ZBX_STR2UCHAR(useip, row[4]);
		ZBX_STR2UCHAR(bulk, row[8]);

		interface = (ZBX_DC_INTERFACE *)DCfind_id(&config->interfaces, interfaceid, sizeof(ZBX_DC_INTERFACE), &found);
		zbx_vector_ptr_append(&interfaces, interface);

		/* remove old address->interfaceid index */
		if (0 != found && INTERFACE_TYPE_SNMP == interface->type)
			dc_interface_snmpaddrs_remove(interface);

		/* see whether we should and can update interfaces_ht index at this point */

		update_index = 0;

		if (0 == found || interface->hostid != hostid || interface->type != type || interface->main != main_)
		{
			if (1 == found && 1 == interface->main)
			{
				interface_ht_local.hostid = interface->hostid;
				interface_ht_local.type = interface->type;
				interface_ht = (ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht, &interface_ht_local);

				if (NULL != interface_ht && interface == interface_ht->interface_ptr)
				{
					/* see ZBX-4045 for NULL check in the conditional */
					zbx_hashset_remove(&config->interfaces_ht, &interface_ht_local);
				}
			}

			if (1 == main_)
			{
				interface_ht_local.hostid = hostid;
				interface_ht_local.type = type;
				interface_ht = (ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht, &interface_ht_local);

				if (NULL != interface_ht)
					interface_ht->interface_ptr = interface;
				else
					update_index = 1;
			}
		}

		/* store new information in interface structure */

		reset_snmp_stats = (0 == found || interface->hostid != hostid || interface->type != type ||
						interface->useip != useip || interface->bulk != bulk);

		interface->hostid = hostid;
		interface->type = type;
		interface->main = main_;
		interface->useip = useip;
		interface->bulk = bulk;
		reset_snmp_stats |= (SUCCEED == DCstrpool_replace(found, &interface->ip, row[5]));
		reset_snmp_stats |= (SUCCEED == DCstrpool_replace(found, &interface->dns, row[6]));
		reset_snmp_stats |= (SUCCEED == DCstrpool_replace(found, &interface->port, row[7]));

		/* update interfaces_ht index using new data, if not done already */

		if (1 == update_index)
		{
			interface_ht_local.hostid = interface->hostid;
			interface_ht_local.type = interface->type;
			interface_ht_local.interface_ptr = interface;
			zbx_hashset_insert(&config->interfaces_ht, &interface_ht_local, sizeof(ZBX_DC_INTERFACE_HT));
		}

		/* update interface_snmpaddrs for SNMP traps or reset bulk request statistics */

		if (INTERFACE_TYPE_SNMP == interface->type)
		{
			interface_snmpaddr_local.addr = (0 != interface->useip ? interface->ip : interface->dns);

			if ('\0' != *interface_snmpaddr_local.addr)
			{
				if (NULL == (interface_snmpaddr = (ZBX_DC_INTERFACE_ADDR *)zbx_hashset_search(&config->interface_snmpaddrs,
						&interface_snmpaddr_local)))
				{
					zbx_strpool_acquire(interface_snmpaddr_local.addr);

					interface_snmpaddr = (ZBX_DC_INTERFACE_ADDR *)zbx_hashset_insert(&config->interface_snmpaddrs,
							&interface_snmpaddr_local, sizeof(ZBX_DC_INTERFACE_ADDR));
					zbx_vector_uint64_create_ext(&interface_snmpaddr->interfaceids,
							__config_mem_malloc_func,
							__config_mem_realloc_func,
							__config_mem_free_func);
				}

				zbx_vector_uint64_append(&interface_snmpaddr->interfaceids, interfaceid);
			}

			if (1 == reset_snmp_stats)
			{
				interface->max_snmp_succeed = 0;
				interface->min_snmp_fail = MAX_SNMP_ITEMS + 1;
			}
		}

		/* first resolve macros for ip and dns fields in main agent interface  */
		/* because other interfaces might reference main interfaces ip and dns */
		/* with {HOST.IP} and {HOST.DNS} macros                                */
		if (1 == interface->main && INTERFACE_TYPE_AGENT == interface->type)
			substitute_host_interface_macros(interface);

		if (0 == found)
		{
			/* new interface - add it to a list of host interfaces in 'config->hosts' hashset */

			ZBX_DC_HOST	*host;

			if (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &interface->hostid)))
			{
				int	exists = 0;

				/* It is an error if the pointer is already in the list. Detect it. */

				for (i = 0; i < host->interfaces_v.values_num; i++)
				{
					if (interface == host->interfaces_v.values[i])
					{
						exists = 1;
						break;
					}
				}

				if (0 == exists)
					zbx_vector_ptr_append(&host->interfaces_v, interface);
				else
					THIS_SHOULD_NEVER_HAPPEN;
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	/* resolve macros in other interfaces */

	for (i = 0; i < interfaces.values_num; i++)
	{
		interface = (ZBX_DC_INTERFACE *)interfaces.values[i];

		if (1 != interface->main || INTERFACE_TYPE_AGENT != interface->type)
			substitute_host_interface_macros(interface);
	}

	/* remove deleted interfaces from buffer */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_DC_HOST	*host;

		if (NULL == (interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &rowid)))
			continue;

		/* remove interface from the list of host interfaces in 'config->hosts' hashset */

		if (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &interface->hostid)))
		{
			for (i = 0; i < host->interfaces_v.values_num; i++)
			{
				if (interface == host->interfaces_v.values[i])
				{
					zbx_vector_ptr_remove(&host->interfaces_v, i);
					break;
				}
			}
		}

		if (INTERFACE_TYPE_SNMP == interface->type)
			dc_interface_snmpaddrs_remove(interface);

		if (1 == interface->main)
		{
			interface_ht_local.hostid = interface->hostid;
			interface_ht_local.type = interface->type;
			interface_ht = (ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht, &interface_ht_local);

			if (NULL != interface_ht && interface == interface_ht->interface_ptr)
			{
				/* see ZBX-4045 for NULL check in the conditional */
				zbx_hashset_remove(&config->interfaces_ht, &interface_ht_local);
			}
		}

		zbx_strpool_release(interface->ip);
		zbx_strpool_release(interface->dns);
		zbx_strpool_release(interface->port);

		zbx_hashset_remove_direct(&config->interfaces, interface);
	}

	zbx_vector_ptr_destroy(&interfaces);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_interface_snmpitems_remove                                    *
 *                                                                            *
 * Purpose: remove item from interfaceid -> itemid index                      *
 *                                                                            *
 * Parameters: interface - [IN] the item                                      *
 *                                                                            *
 ******************************************************************************/
static void	dc_interface_snmpitems_remove(ZBX_DC_ITEM *item)
{
	ZBX_DC_INTERFACE_ITEM	*ifitem;
	int			index;
	zbx_uint64_t		interfaceid;

	if (0 == (interfaceid = item->interfaceid))
		return;

	if (NULL == (ifitem = (ZBX_DC_INTERFACE_ITEM *)zbx_hashset_search(&config->interface_snmpitems, &interfaceid)))
		return;

	if (FAIL == (index = zbx_vector_uint64_search(&ifitem->itemids, item->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		return;

	zbx_vector_uint64_remove_noorder(&ifitem->itemids, index);

	if (0 == ifitem->itemids.values_num)
	{
		zbx_vector_uint64_destroy(&ifitem->itemids);
		zbx_hashset_remove_direct(&config->interface_snmpitems, ifitem);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dc_masteritem_remove_depitem                                     *
 *                                                                            *
 * Purpose: remove itemid from master item dependent itemid vector            *
 *                                                                            *
 * Parameters: master_itemid - [IN] the master item identifier                *
 *             dep_itemid    - [IN] the dependent item identifier             *
 *                                                                            *
 ******************************************************************************/
static void	dc_masteritem_remove_depitem(zbx_uint64_t master_itemid, zbx_uint64_t dep_itemid)
{
	ZBX_DC_MASTERITEM	*masteritem;
	int			index;

	if (NULL == (masteritem = (ZBX_DC_MASTERITEM *)zbx_hashset_search(&config->masteritems, &master_itemid)))
		return;

	if (FAIL == (index = zbx_vector_uint64_search(&masteritem->dep_itemids, dep_itemid,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		return;
	}

	zbx_vector_uint64_remove_noorder(&masteritem->dep_itemids, index);

	if (0 == masteritem->dep_itemids.values_num)
	{
		zbx_vector_uint64_destroy(&masteritem->dep_itemids);
		zbx_hashset_remove_direct(&config->masteritems, masteritem);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dc_host_update_agent_stats                                       *
 *                                                                            *
 * Purpose: update number of items per agent statistics                       *
 *                                                                            *
 * Parameters: host - [IN] the host                                           *
 *             type - [IN] the item type (ITEM_TYPE_*)                        *
 *             num  - [IN] the number of items (+) added, (-) removed         *
 *                                                                            *
 ******************************************************************************/
static void	dc_host_update_agent_stats(ZBX_DC_HOST *host, unsigned char type, int num)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			host->items_num += num;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			host->snmp_items_num += num;
			break;
		case ITEM_TYPE_IPMI:
			host->ipmi_items_num += num;
			break;
		case ITEM_TYPE_JMX:
			host->jmx_items_num += num;
	}
}

static void	DCsync_items(zbx_dbsync_t *sync, int flags)
{
	const char		*__function_name = "DCsync_items";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_HOST		*host;

	ZBX_DC_ITEM		*item;
	ZBX_DC_NUMITEM		*numitem;
	ZBX_DC_SNMPITEM		*snmpitem;
	ZBX_DC_IPMIITEM		*ipmiitem;
	ZBX_DC_TRAPITEM		*trapitem;
	ZBX_DC_DEPENDENTITEM	*depitem;
	ZBX_DC_LOGITEM		*logitem;
	ZBX_DC_DBITEM		*dbitem;
	ZBX_DC_SSHITEM		*sshitem;
	ZBX_DC_TELNETITEM	*telnetitem;
	ZBX_DC_SIMPLEITEM	*simpleitem;
	ZBX_DC_JMXITEM		*jmxitem;
	ZBX_DC_CALCITEM		*calcitem;
	ZBX_DC_INTERFACE_ITEM	*interface_snmpitem;
	ZBX_DC_MASTERITEM	*master;
	ZBX_DC_PREPROCITEM	*preprocitem;
	ZBX_DC_ITEM_HK		*item_hk, item_hk_local;

	time_t			now;
	unsigned char		status, type, value_type, old_poller_type;
	int			found, update_index, ret, i,  old_nextcheck;
	zbx_uint64_t		itemid, hostid;
	zbx_vector_ptr_t	dep_items;

	zbx_vector_ptr_create(&dep_items);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		flags &= ZBX_REFRESH_UNSUPPORTED_CHANGED;

		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);
		ZBX_STR2UCHAR(status, row[2]);
		ZBX_STR2UCHAR(type, row[3]);

		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
			continue;

		item = (ZBX_DC_ITEM *)DCfind_id(&config->items, itemid, sizeof(ZBX_DC_ITEM), &found);

		if (0 != found && ITEM_TYPE_SNMPTRAP == item->type)
			dc_interface_snmpitems_remove(item);

		/* see whether we should and can update items_hk index at this point */

		update_index = 0;

		if (0 == found || item->hostid != hostid || 0 != strcmp(item->key, row[5]))
		{
			if (1 == found)
			{
				item_hk_local.hostid = item->hostid;
				item_hk_local.key = item->key;
				item_hk = (ZBX_DC_ITEM_HK *)zbx_hashset_search(&config->items_hk, &item_hk_local);

				if (item == item_hk->item_ptr)
				{
					zbx_strpool_release(item_hk->key);
					zbx_hashset_remove_direct(&config->items_hk, item_hk);
				}
			}

			item_hk_local.hostid = hostid;
			item_hk_local.key = row[5];
			item_hk = (ZBX_DC_ITEM_HK *)zbx_hashset_search(&config->items_hk, &item_hk_local);

			if (NULL != item_hk)
				item_hk->item_ptr = item;
			else
				update_index = 1;
		}

		/* store new information in item structure */

		item->hostid = hostid;
		DCstrpool_replace(found, &item->port, row[8]);
		item->flags = (unsigned char)atoi(row[24]);
		ZBX_DBROW2UINT64(item->interfaceid, row[25]);

		if (ZBX_HK_OPTION_ENABLED == config->config->hk.history_global)
		{
			item->history_sec = config->config->hk.history;
			item->history = (0 != config->config->hk.history);
		}
		else
		{
			is_time_suffix(row[31], &(item->history_sec), ZBX_LENGTH_UNLIMITED);
			item->history = zbx_time2bool(row[31]);
		}

		ZBX_STR2UCHAR(item->inventory_link, row[33]);
		ZBX_DBROW2UINT64(item->valuemapid, row[34]);

		if (0 != (ZBX_FLAG_DISCOVERY_RULE & item->flags))
			value_type = ITEM_VALUE_TYPE_TEXT;
		else
			ZBX_STR2UCHAR(value_type, row[4]);

		if (SUCCEED == DCstrpool_replace(found, &item->key, row[5]))
			flags |= ZBX_ITEM_KEY_CHANGED;

		if (0 == found)
		{
			item->triggers = NULL;
			item->update_triggers = 0;
			item->nextcheck = 0;
			item->lastclock = 0;
			item->state = (unsigned char)atoi(row[18]);
			ZBX_STR2UINT64(item->lastlogsize, row[29]);
			item->mtime = atoi(row[30]);
			DCstrpool_replace(found, &item->error, row[36]);
			item->data_expected_from = now;
			item->location = ZBX_LOC_NOWHERE;
			item->poller_type = ZBX_NO_POLLER;
			item->queue_priority = ZBX_QUEUE_PRIORITY_NORMAL;
			item->schedulable = 1;
		}
		else
		{
			if (item->type != type)
				flags |= ZBX_ITEM_TYPE_CHANGED;

			if (ITEM_STATUS_ACTIVE == status && ITEM_STATUS_ACTIVE != item->status)
				item->data_expected_from = now;

			if (ITEM_STATUS_ACTIVE == item->status)
				dc_host_update_agent_stats(host, item->type, -1);
		}

		if (ITEM_STATUS_ACTIVE == status)
			dc_host_update_agent_stats(host, type, 1);

		item->type = type;
		item->status = status;
		item->value_type = value_type;

		/* update items_hk index using new data, if not done already */

		if (1 == update_index)
		{
			item_hk_local.hostid = item->hostid;
			item_hk_local.key = zbx_strpool_acquire(item->key);
			item_hk_local.item_ptr = item;
			zbx_hashset_insert(&config->items_hk, &item_hk_local, sizeof(ZBX_DC_ITEM_HK));
		}

		/* process item intervals and update item nextcheck */

		if (SUCCEED == DCstrpool_replace(found, &item->delay, row[14]))
			flags |= ZBX_ITEM_DELAY_CHANGED;

		/* numeric items */

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type || ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			numitem = (ZBX_DC_NUMITEM *)DCfind_id(&config->numitems, itemid, sizeof(ZBX_DC_NUMITEM), &found);

			if (ZBX_HK_OPTION_ENABLED == config->config->hk.trends_global)
				numitem->trends = (0 != config->config->hk.trends);
			else
				numitem->trends = zbx_time2bool(row[32]);

			DCstrpool_replace(found, &numitem->units, row[35]);
		}
		else if (NULL != (numitem = (ZBX_DC_NUMITEM *)zbx_hashset_search(&config->numitems, &itemid)))
		{
			/* remove parameters for non-numeric item */

			zbx_strpool_release(numitem->units);

			zbx_hashset_remove_direct(&config->numitems, numitem);
		}

		/* SNMP items */

		if (SUCCEED == is_snmp_type(item->type))
		{
			snmpitem = (ZBX_DC_SNMPITEM *)DCfind_id(&config->snmpitems, itemid, sizeof(ZBX_DC_SNMPITEM), &found);

			DCstrpool_replace(found, &snmpitem->snmp_community, row[6]);
			DCstrpool_replace(found, &snmpitem->snmpv3_securityname, row[9]);
			snmpitem->snmpv3_securitylevel = (unsigned char)atoi(row[10]);
			DCstrpool_replace(found, &snmpitem->snmpv3_authpassphrase, row[11]);
			DCstrpool_replace(found, &snmpitem->snmpv3_privpassphrase, row[12]);
			snmpitem->snmpv3_authprotocol = (unsigned char)atoi(row[26]);
			snmpitem->snmpv3_privprotocol = (unsigned char)atoi(row[27]);
			DCstrpool_replace(found, &snmpitem->snmpv3_contextname, row[28]);

			if (SUCCEED == DCstrpool_replace(found, &snmpitem->snmp_oid, row[7]))
			{
				if (NULL != strchr(snmpitem->snmp_oid, '{'))
					snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_MACRO;
				else if (NULL != strchr(snmpitem->snmp_oid, '['))
					snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_DYNAMIC;
				else
					snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_NORMAL;
			}
		}
		else if (NULL != (snmpitem = (ZBX_DC_SNMPITEM *)zbx_hashset_search(&config->snmpitems, &itemid)))
		{
			/* remove SNMP parameters for non-SNMP item */

			zbx_strpool_release(snmpitem->snmp_community);
			zbx_strpool_release(snmpitem->snmp_oid);
			zbx_strpool_release(snmpitem->snmpv3_securityname);
			zbx_strpool_release(snmpitem->snmpv3_authpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_privpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_contextname);

			zbx_hashset_remove_direct(&config->snmpitems, snmpitem);
		}

		/* IPMI items */

		if (ITEM_TYPE_IPMI == item->type)
		{
			ipmiitem = (ZBX_DC_IPMIITEM *)DCfind_id(&config->ipmiitems, itemid, sizeof(ZBX_DC_IPMIITEM), &found);

			DCstrpool_replace(found, &ipmiitem->ipmi_sensor, row[13]);
		}
		else if (NULL != (ipmiitem = (ZBX_DC_IPMIITEM *)zbx_hashset_search(&config->ipmiitems, &itemid)))
		{
			/* remove IPMI parameters for non-IPMI item */
			zbx_strpool_release(ipmiitem->ipmi_sensor);
			zbx_hashset_remove_direct(&config->ipmiitems, ipmiitem);
		}

		/* trapper items */

		if (ITEM_TYPE_TRAPPER == item->type && '\0' != *row[15])
		{
			trapitem = (ZBX_DC_TRAPITEM *)DCfind_id(&config->trapitems, itemid, sizeof(ZBX_DC_TRAPITEM), &found);
			zbx_trim_str_list(row[15], ',');
			DCstrpool_replace(found, &trapitem->trapper_hosts, row[15]);
		}
		else if (NULL != (trapitem = (ZBX_DC_TRAPITEM *)zbx_hashset_search(&config->trapitems, &itemid)))
		{
			/* remove trapper_hosts parameter */
			zbx_strpool_release(trapitem->trapper_hosts);
			zbx_hashset_remove_direct(&config->trapitems, trapitem);
		}

		/* dependent items */

		if (ITEM_TYPE_DEPENDENT == item->type && SUCCEED != DBis_null(row[38]))
		{
			depitem = (ZBX_DC_DEPENDENTITEM *)DCfind_id(&config->dependentitems, itemid, sizeof(ZBX_DC_DEPENDENTITEM), &found);

			if (1 == found)
				depitem->last_master_itemid = depitem->master_itemid;
			else
				depitem->last_master_itemid = 0;

			ZBX_STR2UINT64(depitem->master_itemid, row[38]);

			if (depitem->last_master_itemid != depitem->master_itemid)
				zbx_vector_ptr_append(&dep_items, depitem);
		}
		else if (NULL != (depitem = (ZBX_DC_DEPENDENTITEM *)zbx_hashset_search(&config->dependentitems, &itemid)))
		{
			dc_masteritem_remove_depitem(depitem->master_itemid, itemid);
			zbx_hashset_remove_direct(&config->dependentitems, depitem);
		}

		/* log items */

		if (ITEM_VALUE_TYPE_LOG == item->value_type && '\0' != *row[16])
		{
			logitem = (ZBX_DC_LOGITEM *)DCfind_id(&config->logitems, itemid, sizeof(ZBX_DC_LOGITEM), &found);

			DCstrpool_replace(found, &logitem->logtimefmt, row[16]);
		}
		else if (NULL != (logitem = (ZBX_DC_LOGITEM *)zbx_hashset_search(&config->logitems, &itemid)))
		{
			/* remove logtimefmt parameter */
			zbx_strpool_release(logitem->logtimefmt);
			zbx_hashset_remove_direct(&config->logitems, logitem);
		}

		/* db items */

		if (ITEM_TYPE_DB_MONITOR == item->type && '\0' != *row[17])
		{
			dbitem = (ZBX_DC_DBITEM *)DCfind_id(&config->dbitems, itemid, sizeof(ZBX_DC_DBITEM), &found);

			DCstrpool_replace(found, &dbitem->params, row[17]);
			DCstrpool_replace(found, &dbitem->username, row[20]);
			DCstrpool_replace(found, &dbitem->password, row[21]);
		}
		else if (NULL != (dbitem = (ZBX_DC_DBITEM *)zbx_hashset_search(&config->dbitems, &itemid)))
		{
			/* remove db item parameters */
			zbx_strpool_release(dbitem->params);
			zbx_strpool_release(dbitem->username);
			zbx_strpool_release(dbitem->password);

			zbx_hashset_remove_direct(&config->dbitems, dbitem);
		}

		/* SSH items */

		if (ITEM_TYPE_SSH == item->type)
		{
			sshitem = (ZBX_DC_SSHITEM *)DCfind_id(&config->sshitems, itemid, sizeof(ZBX_DC_SSHITEM), &found);

			sshitem->authtype = (unsigned short)atoi(row[19]);
			DCstrpool_replace(found, &sshitem->username, row[20]);
			DCstrpool_replace(found, &sshitem->password, row[21]);
			DCstrpool_replace(found, &sshitem->publickey, row[22]);
			DCstrpool_replace(found, &sshitem->privatekey, row[23]);
			DCstrpool_replace(found, &sshitem->params, row[17]);
		}
		else if (NULL != (sshitem = (ZBX_DC_SSHITEM *)zbx_hashset_search(&config->sshitems, &itemid)))
		{
			/* remove SSH item parameters */

			zbx_strpool_release(sshitem->username);
			zbx_strpool_release(sshitem->password);
			zbx_strpool_release(sshitem->publickey);
			zbx_strpool_release(sshitem->privatekey);
			zbx_strpool_release(sshitem->params);

			zbx_hashset_remove_direct(&config->sshitems, sshitem);
		}

		/* TELNET items */

		if (ITEM_TYPE_TELNET == item->type)
		{
			telnetitem = (ZBX_DC_TELNETITEM *)DCfind_id(&config->telnetitems, itemid, sizeof(ZBX_DC_TELNETITEM), &found);

			DCstrpool_replace(found, &telnetitem->username, row[20]);
			DCstrpool_replace(found, &telnetitem->password, row[21]);
			DCstrpool_replace(found, &telnetitem->params, row[17]);
		}
		else if (NULL != (telnetitem = (ZBX_DC_TELNETITEM *)zbx_hashset_search(&config->telnetitems, &itemid)))
		{
			/* remove TELNET item parameters */

			zbx_strpool_release(telnetitem->username);
			zbx_strpool_release(telnetitem->password);
			zbx_strpool_release(telnetitem->params);

			zbx_hashset_remove_direct(&config->telnetitems, telnetitem);
		}

		/* simple items */

		if (ITEM_TYPE_SIMPLE == item->type)
		{
			simpleitem = (ZBX_DC_SIMPLEITEM *)DCfind_id(&config->simpleitems, itemid, sizeof(ZBX_DC_SIMPLEITEM), &found);

			DCstrpool_replace(found, &simpleitem->username, row[20]);
			DCstrpool_replace(found, &simpleitem->password, row[21]);
		}
		else if (NULL != (simpleitem = (ZBX_DC_SIMPLEITEM *)zbx_hashset_search(&config->simpleitems, &itemid)))
		{
			/* remove simple item parameters */

			zbx_strpool_release(simpleitem->username);
			zbx_strpool_release(simpleitem->password);

			zbx_hashset_remove_direct(&config->simpleitems, simpleitem);
		}

		/* JMX items */

		if (ITEM_TYPE_JMX == item->type)
		{
			jmxitem = (ZBX_DC_JMXITEM *)DCfind_id(&config->jmxitems, itemid, sizeof(ZBX_DC_JMXITEM), &found);

			DCstrpool_replace(found, &jmxitem->username, row[20]);
			DCstrpool_replace(found, &jmxitem->password, row[21]);
			DCstrpool_replace(found, &jmxitem->jmx_endpoint, row[37]);
		}
		else if (NULL != (jmxitem = (ZBX_DC_JMXITEM *)zbx_hashset_search(&config->jmxitems, &itemid)))
		{
			/* remove JMX item parameters */

			zbx_strpool_release(jmxitem->username);
			zbx_strpool_release(jmxitem->password);
			zbx_strpool_release(jmxitem->jmx_endpoint);

			zbx_hashset_remove_direct(&config->jmxitems, jmxitem);
		}

		/* SNMP trap items for current server/proxy */

		if (ITEM_TYPE_SNMPTRAP == item->type && 0 == host->proxy_hostid)
		{
			interface_snmpitem = (ZBX_DC_INTERFACE_ITEM *)DCfind_id(&config->interface_snmpitems,
					item->interfaceid, sizeof(ZBX_DC_INTERFACE_ITEM), &found);

			if (0 == found)
			{
				zbx_vector_uint64_create_ext(&interface_snmpitem->itemids,
						__config_mem_malloc_func,
						__config_mem_realloc_func,
						__config_mem_free_func);
			}

			zbx_vector_uint64_append(&interface_snmpitem->itemids, itemid);
		}

		/* calculated items */

		if (ITEM_TYPE_CALCULATED == item->type)
		{
			calcitem = (ZBX_DC_CALCITEM *)DCfind_id(&config->calcitems, itemid, sizeof(ZBX_DC_CALCITEM), &found);

			DCstrpool_replace(found, &calcitem->params, row[17]);
		}
		else if (NULL != (calcitem = (ZBX_DC_CALCITEM *)zbx_hashset_search(&config->calcitems, &itemid)))
		{
			/* remove calculated item parameters */

			zbx_strpool_release(calcitem->params);
			zbx_hashset_remove_direct(&config->calcitems, calcitem);
		}

		/* it is crucial to update type specific (config->snmpitems, config->ipmiitems, etc.) hashsets before */
		/* attempting to requeue an item because type specific properties are used to arrange items in queues */

		old_poller_type = item->poller_type;
		old_nextcheck = item->nextcheck;

		if (ITEM_STATUS_ACTIVE == item->status && HOST_STATUS_MONITORED == host->status)
		{
			DCitem_poller_type_update(item, host, flags);

			if (SUCCEED == is_counted_in_item_queue(item->type, item->key))
				DCitem_nextcheck_update(item, host, item->state, flags, now);
		}
		else
		{
			item->nextcheck = 0;
			item->queue_priority = ZBX_QUEUE_PRIORITY_NORMAL;
			item->poller_type = ZBX_NO_POLLER;
		}

		DCupdate_item_queue(item, old_poller_type, old_nextcheck);
	}

	/* update dependent item vectors within master items */

	for (i = 0; i < dep_items.values_num; i++)
	{
		depitem = (ZBX_DC_DEPENDENTITEM *)dep_items.values[i];
		itemid = depitem->itemid;
		dc_masteritem_remove_depitem(depitem->last_master_itemid, itemid);

		/* append item to dependent item vector of master item */
		if (NULL == (master = (ZBX_DC_MASTERITEM *)zbx_hashset_search(&config->masteritems, &depitem->master_itemid)))
		{
			ZBX_DC_MASTERITEM	master_local;

			master_local.itemid = depitem->master_itemid;
			master = (ZBX_DC_MASTERITEM *)zbx_hashset_insert(&config->masteritems, &master_local, sizeof(master_local));

			zbx_vector_uint64_create_ext(&master->dep_itemids, __config_mem_malloc_func,
					__config_mem_realloc_func, __config_mem_free_func);
		}

		zbx_vector_uint64_append(&master->dep_itemids, itemid);
	}

	zbx_vector_ptr_destroy(&dep_items);

	/* remove deleted items from buffer */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &rowid)))
			continue;

		if (ITEM_STATUS_ACTIVE == item->status &&
				NULL != (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &item->hostid)))
		{
			dc_host_update_agent_stats(host, item->type, -1);
		}

		itemid = item->itemid;

		if (ITEM_TYPE_SNMPTRAP == item->type)
			dc_interface_snmpitems_remove(item);

		/* numeric items */

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type || ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			numitem = (ZBX_DC_NUMITEM *)zbx_hashset_search(&config->numitems, &itemid);

			zbx_strpool_release(numitem->units);

			zbx_hashset_remove_direct(&config->numitems, numitem);
		}

		/* SNMP items */

		if (SUCCEED == is_snmp_type(item->type))
		{
			snmpitem = (ZBX_DC_SNMPITEM *)zbx_hashset_search(&config->snmpitems, &itemid);

			zbx_strpool_release(snmpitem->snmp_community);
			zbx_strpool_release(snmpitem->snmp_oid);
			zbx_strpool_release(snmpitem->snmpv3_securityname);
			zbx_strpool_release(snmpitem->snmpv3_authpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_privpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_contextname);

			zbx_hashset_remove_direct(&config->snmpitems, snmpitem);
		}

		/* IPMI items */

		if (ITEM_TYPE_IPMI == item->type)
		{
			ipmiitem = (ZBX_DC_IPMIITEM *)zbx_hashset_search(&config->ipmiitems, &itemid);
			zbx_strpool_release(ipmiitem->ipmi_sensor);
			zbx_hashset_remove_direct(&config->ipmiitems, ipmiitem);
		}

		/* trapper items */

		if (ITEM_TYPE_TRAPPER == item->type &&
				NULL != (trapitem = (ZBX_DC_TRAPITEM *)zbx_hashset_search(&config->trapitems, &itemid)))
		{
			zbx_strpool_release(trapitem->trapper_hosts);
			zbx_hashset_remove_direct(&config->trapitems, trapitem);
		}

		/* dependent items */

		if (NULL != (depitem = (ZBX_DC_DEPENDENTITEM *)zbx_hashset_search(&config->dependentitems, &itemid)))
		{
			dc_masteritem_remove_depitem(depitem->master_itemid, itemid);
			zbx_hashset_remove_direct(&config->dependentitems, depitem);
		}

		/* log items */

		if (ITEM_VALUE_TYPE_LOG == item->value_type &&
				NULL != (logitem = (ZBX_DC_LOGITEM *)zbx_hashset_search(&config->logitems, &itemid)))
		{
			zbx_strpool_release(logitem->logtimefmt);
			zbx_hashset_remove_direct(&config->logitems, logitem);
		}

		/* db items */

		if (ITEM_TYPE_DB_MONITOR == item->type &&
				NULL != (dbitem = (ZBX_DC_DBITEM *)zbx_hashset_search(&config->dbitems, &itemid)))
		{
			zbx_strpool_release(dbitem->params);
			zbx_strpool_release(dbitem->username);
			zbx_strpool_release(dbitem->password);

			zbx_hashset_remove_direct(&config->dbitems, dbitem);
		}

		/* SSH items */

		if (ITEM_TYPE_SSH == item->type)
		{
			sshitem = (ZBX_DC_SSHITEM *)zbx_hashset_search(&config->sshitems, &itemid);

			zbx_strpool_release(sshitem->username);
			zbx_strpool_release(sshitem->password);
			zbx_strpool_release(sshitem->publickey);
			zbx_strpool_release(sshitem->privatekey);
			zbx_strpool_release(sshitem->params);

			zbx_hashset_remove_direct(&config->sshitems, sshitem);
		}

		/* TELNET items */

		if (ITEM_TYPE_TELNET == item->type)
		{
			telnetitem = (ZBX_DC_TELNETITEM *)zbx_hashset_search(&config->telnetitems, &itemid);

			zbx_strpool_release(telnetitem->username);
			zbx_strpool_release(telnetitem->password);
			zbx_strpool_release(telnetitem->params);

			zbx_hashset_remove_direct(&config->telnetitems, telnetitem);
		}

		/* simple items */

		if (ITEM_TYPE_SIMPLE == item->type)
		{
			simpleitem = (ZBX_DC_SIMPLEITEM *)zbx_hashset_search(&config->simpleitems, &itemid);

			zbx_strpool_release(simpleitem->username);
			zbx_strpool_release(simpleitem->password);

			zbx_hashset_remove_direct(&config->simpleitems, simpleitem);
		}

		/* JMX items */

		if (ITEM_TYPE_JMX == item->type)
		{
			jmxitem = (ZBX_DC_JMXITEM *)zbx_hashset_search(&config->jmxitems, &itemid);

			zbx_strpool_release(jmxitem->username);
			zbx_strpool_release(jmxitem->password);
			zbx_strpool_release(jmxitem->jmx_endpoint);

			zbx_hashset_remove_direct(&config->jmxitems, jmxitem);
		}

		/* calculated items */

		if (ITEM_TYPE_CALCULATED == item->type)
		{
			calcitem = (ZBX_DC_CALCITEM *)zbx_hashset_search(&config->calcitems, &itemid);
			zbx_strpool_release(calcitem->params);
			zbx_hashset_remove_direct(&config->calcitems, calcitem);
		}

		/* items */

		item_hk_local.hostid = item->hostid;
		item_hk_local.key = item->key;
		item_hk = (ZBX_DC_ITEM_HK *)zbx_hashset_search(&config->items_hk, &item_hk_local);

		if (item == item_hk->item_ptr)
		{
			zbx_strpool_release(item_hk->key);
			zbx_hashset_remove_direct(&config->items_hk, item_hk);
		}

		if (ZBX_LOC_QUEUE == item->location)
			zbx_binary_heap_remove_direct(&config->queues[item->poller_type], item->itemid);

		zbx_strpool_release(item->key);
		zbx_strpool_release(item->port);
		zbx_strpool_release(item->error);

		if (NULL != item->triggers)
			config->items.mem_free_func(item->triggers);

		if (NULL != (preprocitem = (ZBX_DC_PREPROCITEM *)zbx_hashset_search(&config->preprocitems, &item->itemid)))
		{
			zbx_vector_ptr_destroy(&preprocitem->preproc_ops);
			zbx_hashset_remove_direct(&config->preprocitems, preprocitem);
		}

		zbx_hashset_remove_direct(&config->items, item);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_triggers(zbx_dbsync_t *sync)
{
	const char	*__function_name = "DCsync_triggers";

	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;

	ZBX_DC_TRIGGER	*trigger;

	int		found, ret;
	zbx_uint64_t	triggerid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(triggerid, row[0]);

		trigger = (ZBX_DC_TRIGGER *)DCfind_id(&config->triggers, triggerid, sizeof(ZBX_DC_TRIGGER), &found);

		/* store new information in trigger structure */

		DCstrpool_replace(found, &trigger->description, row[1]);
		DCstrpool_replace(found, &trigger->expression, row[2]);
		DCstrpool_replace(found, &trigger->recovery_expression, row[11]);
		DCstrpool_replace(found, &trigger->correlation_tag, row[13]);
		ZBX_STR2UCHAR(trigger->priority, row[4]);
		ZBX_STR2UCHAR(trigger->type, row[5]);
		ZBX_STR2UCHAR(trigger->status, row[9]);
		ZBX_STR2UCHAR(trigger->recovery_mode, row[10]);
		ZBX_STR2UCHAR(trigger->correlation_mode, row[12]);

		if (0 == found)
		{
			DCstrpool_replace(found, &trigger->error, row[3]);
			ZBX_STR2UCHAR(trigger->value, row[6]);
			ZBX_STR2UCHAR(trigger->state, row[7]);
			trigger->lastchange = atoi(row[8]);
			trigger->locked = 0;

			zbx_vector_ptr_create_ext(&trigger->tags, __config_mem_malloc_func, __config_mem_realloc_func,
					__config_mem_free_func);
			trigger->topoindex = 1;
		}
	}

	/* remove deleted triggers from buffer */
	if (SUCCEED == ret)
	{
		zbx_vector_uint64_t	functionids;
		int			i;
		ZBX_DC_ITEM		*item;
		ZBX_DC_FUNCTION		*function;

		zbx_vector_uint64_create(&functionids);

		for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
		{
			if (NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &rowid)))
				continue;

			/* force trigger list update for items used in removed trigger */

			get_functionids(&functionids, trigger->expression);

			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == trigger->recovery_mode)
				get_functionids(&functionids, trigger->recovery_expression);

			for (i = 0; i < functionids.values_num; i++)
			{
				if (NULL == (function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions, &functionids.values[i])))
					continue;

				if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &function->itemid)))
					continue;

				item->update_triggers = 1;
				if (NULL != item->triggers)
				{
					config->items.mem_free_func(item->triggers);
					item->triggers = NULL;
				}
			}
			zbx_vector_uint64_clear(&functionids);

			zbx_strpool_release(trigger->description);
			zbx_strpool_release(trigger->expression);
			zbx_strpool_release(trigger->error);
			zbx_strpool_release(trigger->correlation_tag);

			zbx_vector_ptr_destroy(&trigger->tags);

			zbx_hashset_remove_direct(&config->triggers, trigger);
		}
		zbx_vector_uint64_destroy(&functionids);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCconfig_sort_triggers_topologically(void);

/******************************************************************************
 *                                                                            *
 * Function: dc_trigger_deplist_release                                       *
 *                                                                            *
 * Purpose: releases trigger dependency list, removing it if necessary        *
 *                                                                            *
 ******************************************************************************/
static int	dc_trigger_deplist_release(ZBX_DC_TRIGGER_DEPLIST *trigdep)
{
	if (0 == --trigdep->refcount)
	{
		zbx_vector_ptr_destroy(&trigdep->dependencies);
		zbx_hashset_remove_direct(&config->trigdeps, trigdep);
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_trigger_deplist_init                                          *
 *                                                                            *
 * Purpose: initializes trigger dependency list                               *
 *                                                                            *
 ******************************************************************************/
static void	dc_trigger_deplist_init(ZBX_DC_TRIGGER_DEPLIST *trigdep, ZBX_DC_TRIGGER *trigger)
{
	trigdep->refcount = 1;
	trigdep->trigger = trigger;
	zbx_vector_ptr_create_ext(&trigdep->dependencies, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_trigger_deplist_reset                                         *
 *                                                                            *
 * Purpose: resets trigger dependency list to release memory allocated by     *
 *          dependencies vector                                               *
 *                                                                            *
 ******************************************************************************/
static void	dc_trigger_deplist_reset(ZBX_DC_TRIGGER_DEPLIST *trigdep)
{
	zbx_vector_ptr_destroy(&trigdep->dependencies);
	zbx_vector_ptr_create_ext(&trigdep->dependencies, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);
}

static void	DCsync_trigdeps(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_trigdeps";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_TRIGGER_DEPLIST	*trigdep_down, *trigdep_up;

	int			found, index, ret;
	zbx_uint64_t		triggerid_down, triggerid_up;
	ZBX_DC_TRIGGER		*trigger_up, *trigger_down;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		/* find trigdep_down pointer */

		ZBX_STR2UINT64(triggerid_down, row[0]);
		if (NULL == (trigger_down = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &triggerid_down)))
			continue;

		ZBX_STR2UINT64(triggerid_up, row[1]);
		if (NULL == (trigger_up = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &triggerid_up)))
			continue;

		trigdep_down = (ZBX_DC_TRIGGER_DEPLIST *)DCfind_id(&config->trigdeps, triggerid_down, sizeof(ZBX_DC_TRIGGER_DEPLIST), &found);
		if (0 == found)
			dc_trigger_deplist_init(trigdep_down, trigger_down);
		else
			trigdep_down->refcount++;

		trigdep_up = (ZBX_DC_TRIGGER_DEPLIST *)DCfind_id(&config->trigdeps, triggerid_up, sizeof(ZBX_DC_TRIGGER_DEPLIST), &found);
		if (0 == found)
			dc_trigger_deplist_init(trigdep_up, trigger_up);
		else
			trigdep_up->refcount++;

		zbx_vector_ptr_append(&trigdep_down->dependencies, trigdep_up);
	}

	/* remove deleted trigger dependencies from buffer */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_STR2UINT64(triggerid_down, row[0]);
		if (NULL == (trigdep_down = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_search(&config->trigdeps,
				&triggerid_down)))
		{
			continue;
		}

		ZBX_STR2UINT64(triggerid_up, row[1]);
		if (NULL != (trigdep_up = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_search(&config->trigdeps,
				&triggerid_up)))
		{
			dc_trigger_deplist_release(trigdep_up);
		}

		if (SUCCEED != dc_trigger_deplist_release(trigdep_down))
		{
			if (FAIL == (index = zbx_vector_ptr_search(&trigdep_down->dependencies, &triggerid_up,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				continue;
			}

			if (1 == trigdep_down->dependencies.values_num)
				dc_trigger_deplist_reset(trigdep_down);
			else
				zbx_vector_ptr_remove_noorder(&trigdep_down->dependencies, index);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_functions(zbx_dbsync_t *sync)
{
	const char	*__function_name = "DCsync_functions";

	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;

	ZBX_DC_ITEM	*item;
	ZBX_DC_FUNCTION	*function;

	int		found, ret;
	zbx_uint64_t	itemid, functionid, triggerid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UINT64(functionid, row[1]);
		ZBX_STR2UINT64(triggerid, row[4]);

		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
		{
			/* Item could have been created after we have selected them in the             */
			/* previous queries. However, we shall avoid the check for functions being the */
			/* same as in the trigger expression, because that is somewhat expensive, not  */
			/* 100% (think functions keeping their functionid, but changing their function */
			/* or parameters), and even if there is an inconsistency, we can live with it. */

			continue;
		}

		/* process function information */

		function = (ZBX_DC_FUNCTION *)DCfind_id(&config->functions, functionid, sizeof(ZBX_DC_FUNCTION), &found);

		function->triggerid = triggerid;
		function->itemid = itemid;
		DCstrpool_replace(found, &function->function, row[2]);
		DCstrpool_replace(found, &function->parameter, row[3]);

		function->timer = (SUCCEED == is_time_function(function->function) ? 1 : 0);

		item->update_triggers = 1;
		if (NULL != item->triggers)
			item->triggers[0] = NULL;
	}

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions, &rowid)))
			continue;

		if (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &function->itemid)))
		{
			item->update_triggers = 1;
			if (NULL != item->triggers)
			{
				config->items.mem_free_func(item->triggers);
				item->triggers = NULL;
			}
		}

		zbx_strpool_release(function->function);
		zbx_strpool_release(function->parameter);

		zbx_hashset_remove_direct(&config->functions, function);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_regexp_remove_expression                                      *
 *                                                                            *
 * Purpose: removes expression from regexp                                    *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_REGEXP	*dc_regexp_remove_expression(const char *regexp_name, zbx_uint64_t expressionid)
{
	ZBX_DC_REGEXP	*regexp, regexp_local;
	int		index;

	regexp_local.name = regexp_name;

	if (NULL == (regexp = (ZBX_DC_REGEXP *)zbx_hashset_search(&config->regexps, &regexp_local)))
		return NULL;

	if (FAIL == (index = zbx_vector_uint64_search(&regexp->expressionids, expressionid,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		return NULL;
	}

	zbx_vector_uint64_remove_noorder(&regexp->expressionids, index);

	return regexp;
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_expressions                                               *
 *                                                                            *
 * Purpose: Updates expressions configuration cache                           *
 *                                                                            *
 * Parameters: result - [IN] the result of expressions database select        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_expressions(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_expressions";
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_hashset_iter_t	iter;
	ZBX_DC_EXPRESSION	*expression;
	ZBX_DC_REGEXP		*regexp, regexp_local;
	zbx_uint64_t		expressionid;
	int			found, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(expressionid, row[1]);
		expression = (ZBX_DC_EXPRESSION *)DCfind_id(&config->expressions, expressionid, sizeof(ZBX_DC_EXPRESSION), &found);

		if (0 != found)
			dc_regexp_remove_expression(expression->regexp, expressionid);

		DCstrpool_replace(found, &expression->regexp, row[0]);
		DCstrpool_replace(found, &expression->expression, row[2]);
		ZBX_STR2UCHAR(expression->type, row[3]);
		ZBX_STR2UCHAR(expression->case_sensitive, row[5]);
		expression->delimiter = *row[4];

		regexp_local.name = row[0];

		if (NULL == (regexp = (ZBX_DC_REGEXP *)zbx_hashset_search(&config->regexps, &regexp_local)))
		{
			DCstrpool_replace(0, &regexp_local.name, row[0]);
			zbx_vector_uint64_create_ext(&regexp_local.expressionids,
					__config_mem_malloc_func,
					__config_mem_realloc_func,
					__config_mem_free_func);

			regexp = (ZBX_DC_REGEXP *)zbx_hashset_insert(&config->regexps, &regexp_local, sizeof(ZBX_DC_REGEXP));
		}

		zbx_vector_uint64_append(&regexp->expressionids, expressionid);
	}

	/* remove regexps with no expressions related to it */
	zbx_hashset_iter_reset(&config->regexps, &iter);

	while (NULL != (regexp = (ZBX_DC_REGEXP *)zbx_hashset_iter_next(&iter)))
	{
		if (0 < regexp->expressionids.values_num)
			continue;

		zbx_strpool_release(regexp->name);
		zbx_vector_uint64_destroy(&regexp->expressionids);
		zbx_hashset_iter_remove(&iter);
	}

	/* remove unused expressions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (expression = (ZBX_DC_EXPRESSION *)zbx_hashset_search(&config->expressions, &rowid)))
			continue;

		if (NULL != (regexp = dc_regexp_remove_expression(expression->regexp, expression->expressionid)))
		{
			if (0 == regexp->expressionids.values_num)
			{
				zbx_strpool_release(regexp->name);
				zbx_vector_uint64_destroy(&regexp->expressionids);
				zbx_hashset_remove_direct(&config->regexps, regexp);
			}
		}

		zbx_strpool_release(expression->expression);
		zbx_strpool_release(expression->regexp);
		zbx_hashset_remove_direct(&config->expressions, expression);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_actions                                                   *
 *                                                                            *
 * Purpose: Updates actions configuration cache                               *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - actionid                                                     *
 *           1 - eventsource                                                  *
 *           2 - evaltype                                                     *
 *           3 - formula                                                      *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_actions(zbx_dbsync_t *sync)
{
	const char	*__function_name = "DCsync_actions";

	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	zbx_uint64_t	actionid;
	zbx_dc_action_t	*action;
	int		found, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(actionid, row[0]);
		action = (zbx_dc_action_t *)DCfind_id(&config->actions, actionid, sizeof(zbx_dc_action_t), &found);

		if (0 == found)
		{
			zbx_vector_ptr_create_ext(&action->conditions, __config_mem_malloc_func,
					__config_mem_realloc_func, __config_mem_free_func);

			zbx_vector_ptr_reserve(&action->conditions, 1);

			action->opflags = ZBX_ACTION_OPCLASS_NONE;
		}

		ZBX_STR2UCHAR(action->eventsource, row[1]);
		ZBX_STR2UCHAR(action->evaltype, row[2]);

		DCstrpool_replace(found, &action->formula, row[3]);
	}

	/* remove deleted actions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&config->actions, &rowid)))
			continue;

		zbx_strpool_release(action->formula);
		zbx_vector_ptr_destroy(&action->conditions);

		zbx_hashset_remove_direct(&config->actions, action);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_action_ops                                                *
 *                                                                            *
 * Purpose: Updates action operation class flags in configuration cache       *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - actionid                                                     *
 *           1 - action operation class flags                                 *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_action_ops(zbx_dbsync_t *sync)
{
	const char	*__function_name = "DCsync_action_opss";

	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	zbx_uint64_t	actionid;
	zbx_dc_action_t	*action;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_STR2UINT64(actionid, row[0]);

		if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&config->actions, &actionid)))
			continue;

		action->opflags = atoi(row[1]);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_compare_action_conditions_by_type                             *
 *                                                                            *
 * Purpose: compare two action conditions by their type                       *
 *                                                                            *
 * Comments: This function is used to sort action conditions by type.         *
 *                                                                            *
 ******************************************************************************/
static int	dc_compare_action_conditions_by_type(const void *d1, const void *d2)
{
	zbx_dc_action_condition_t	*c1 = *(zbx_dc_action_condition_t **)d1;
	zbx_dc_action_condition_t	*c2 = *(zbx_dc_action_condition_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(c1->conditiontype, c2->conditiontype);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_action_conditions                                         *
 *                                                                            *
 * Purpose: Updates action conditions configuration cache                     *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - conditionid                                                  *
 *           1 - actionid                                                     *
 *           2 - conditiontype                                                *
 *           3 - operator                                                     *
 *           4 - value                                                        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_action_conditions(zbx_dbsync_t *sync)
{
	const char			*__function_name = "DCsync_action_conditions";

	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;
	zbx_uint64_t			actionid, conditionid;
	zbx_dc_action_t			*action;
	zbx_dc_action_condition_t	*condition;
	int				found, i, index, ret;
	zbx_vector_ptr_t		actions;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&actions);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(actionid, row[1]);

		if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&config->actions, &actionid)))
			continue;

		ZBX_STR2UINT64(conditionid, row[0]);

		condition = (zbx_dc_action_condition_t *)DCfind_id(&config->action_conditions, conditionid, sizeof(zbx_dc_action_condition_t),
				&found);

		ZBX_STR2UCHAR(condition->conditiontype, row[2]);
		ZBX_STR2UCHAR(condition->op, row[3]);

		DCstrpool_replace(found, &condition->value, row[4]);
		DCstrpool_replace(found, &condition->value2, row[5]);

		if (0 == found)
		{
			condition->actionid = actionid;
			zbx_vector_ptr_append(&action->conditions, condition);
		}

		if (CONDITION_EVAL_TYPE_AND_OR == action->evaltype)
			zbx_vector_ptr_append(&actions, action);
	}

	/* remove deleted conditions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (condition = (zbx_dc_action_condition_t *)zbx_hashset_search(&config->action_conditions, &rowid)))
			continue;

		if (NULL != (action = (zbx_dc_action_t *)zbx_hashset_search(&config->actions, &condition->actionid)))
		{
			if (FAIL != (index = zbx_vector_ptr_search(&action->conditions, condition,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_ptr_remove_noorder(&action->conditions, index);

				if (CONDITION_EVAL_TYPE_AND_OR == action->evaltype)
					zbx_vector_ptr_append(&actions, action);
			}
		}

		zbx_strpool_release(condition->value);
		zbx_strpool_release(condition->value2);

		zbx_hashset_remove_direct(&config->action_conditions, condition);
	}

	/* sort conditions by type */

	zbx_vector_ptr_sort(&actions, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(&actions, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < actions.values_num; i++)
	{
		action = (zbx_dc_action_t *)actions.values[i];

		if (CONDITION_EVAL_TYPE_AND_OR == action->evaltype)
			zbx_vector_ptr_sort(&action->conditions, dc_compare_action_conditions_by_type);
	}

	zbx_vector_ptr_destroy(&actions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_correlations                                              *
 *                                                                            *
 * Purpose: Updates correlations configuration cache                          *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - correlationid                                                *
 *           1 - name                                                         *
 *           2 - evaltype                                                     *
 *           3 - formula                                                      *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_correlations(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_correlations";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		correlationid;
	zbx_dc_correlation_t	*correlation;
	int			found, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(correlationid, row[0]);

		correlation = (zbx_dc_correlation_t *)DCfind_id(&config->correlations, correlationid, sizeof(zbx_dc_correlation_t), &found);

		if (0 == found)
		{
			zbx_vector_ptr_create_ext(&correlation->conditions, __config_mem_malloc_func,
					__config_mem_realloc_func, __config_mem_free_func);

			zbx_vector_ptr_create_ext(&correlation->operations, __config_mem_malloc_func,
					__config_mem_realloc_func, __config_mem_free_func);
		}

		DCstrpool_replace(found, &correlation->name, row[1]);
		DCstrpool_replace(found, &correlation->formula, row[3]);

		ZBX_STR2UCHAR(correlation->evaltype, row[2]);
	}

	/* remove deleted correlations */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations, &rowid)))
			continue;

		zbx_strpool_release(correlation->name);
		zbx_strpool_release(correlation->formula);

		zbx_vector_ptr_destroy(&correlation->conditions);
		zbx_vector_ptr_destroy(&correlation->operations);

		zbx_hashset_remove_direct(&config->correlations, correlation);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_corr_condition_get_size                                       *
 *                                                                            *
 * Purpose: get the actual size of correlation condition data depending on    *
 *          its type                                                          *
 *                                                                            *
 * Parameters: type - [IN] the condition type                                 *
 *                                                                            *
 * Return value: the size                                                     *
 *                                                                            *
 ******************************************************************************/
static size_t	dc_corr_condition_get_size(unsigned char type)
{
	switch (type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			return offsetof(zbx_dc_corr_condition_t, data) + sizeof(zbx_dc_corr_condition_tag_t);
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			return offsetof(zbx_dc_corr_condition_t, data) + sizeof(zbx_dc_corr_condition_group_t);
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			return offsetof(zbx_dc_corr_condition_t, data) + sizeof(zbx_dc_corr_condition_tag_pair_t);
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			return offsetof(zbx_dc_corr_condition_t, data) + sizeof(zbx_dc_corr_condition_tag_value_t);
	}

	THIS_SHOULD_NEVER_HAPPEN;
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_corr_condition_init_data                                      *
 *                                                                            *
 * Purpose: initializes correlation condition data from database row          *
 *                                                                            *
 * Parameters: condition - [IN] the condition to initialize                   *
 *             found     - [IN] 0 - new condition, 1 - cached condition       *
 *             row       - [IN] the database row containing condition data    *
 *                                                                            *
 ******************************************************************************/
static void	dc_corr_condition_init_data(zbx_dc_corr_condition_t *condition, int found,  DB_ROW row)
{
	if (ZBX_CORR_CONDITION_OLD_EVENT_TAG == condition->type || ZBX_CORR_CONDITION_NEW_EVENT_TAG == condition->type)
	{
		DCstrpool_replace(found, &condition->data.tag.tag, row[0]);
		return;
	}

	row++;

	if (ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE == condition->type ||
			ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE == condition->type)
	{
		DCstrpool_replace(found, &condition->data.tag_value.tag, row[0]);
		DCstrpool_replace(found, &condition->data.tag_value.value, row[1]);
		ZBX_STR2UCHAR(condition->data.tag_value.op, row[2]);
		return;
	}

	row += 3;

	if (ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP == condition->type)
	{
		ZBX_STR2UINT64(condition->data.group.groupid, row[0]);
		ZBX_STR2UCHAR(condition->data.group.op, row[1]);
		return;
	}

	row += 2;

	if (ZBX_CORR_CONDITION_EVENT_TAG_PAIR == condition->type)
	{
		DCstrpool_replace(found, &condition->data.tag_pair.oldtag, row[0]);
		DCstrpool_replace(found, &condition->data.tag_pair.newtag, row[1]);
		return;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: corr_condition_free_data                                         *
 *                                                                            *
 * Purpose: frees correlation condition data                                  *
 *                                                                            *
 * Parameters: condition - [IN] the condition                                 *
 *                                                                            *
 ******************************************************************************/
static void	corr_condition_free_data(zbx_dc_corr_condition_t *condition)
{
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			zbx_strpool_release(condition->data.tag.tag);
			break;
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			zbx_strpool_release(condition->data.tag_pair.oldtag);
			zbx_strpool_release(condition->data.tag_pair.newtag);
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			zbx_strpool_release(condition->data.tag_value.tag);
			zbx_strpool_release(condition->data.tag_value.value);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dc_compare_corr_conditions_by_type                               *
 *                                                                            *
 * Purpose: compare two correlation conditions by their type                  *
 *                                                                            *
 * Comments: This function is used to sort correlation conditions by type.    *
 *                                                                            *
 ******************************************************************************/
static int	dc_compare_corr_conditions_by_type(const void *d1, const void *d2)
{
	zbx_dc_corr_condition_t	*c1 = *(zbx_dc_corr_condition_t **)d1;
	zbx_dc_corr_condition_t	*c2 = *(zbx_dc_corr_condition_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(c1->type, c2->type);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_corr_conditions                                           *
 *                                                                            *
 * Purpose: Updates correlation conditions configuration cache                *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - corr_conditionid                                             *
 *           1 - correlationid                                                *
 *           2 - type                                                         *
 *           3 - corr_condition_tag.tag                                       *
 *           4 - corr_condition_tagvalue.tag                                  *
 *           5 - corr_condition_tagvalue.value                                *
 *           6 - corr_condition_tagvalue.operator                             *
 *           7 - corr_condition_group.groupid                                 *
 *           8 - corr_condition_group.operator                                *
 *           9 - corr_condition_tagpair.oldtag                                *
 *          10 - corr_condition_tagpair.newtag                                *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_corr_conditions(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_corr_conditions";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		conditionid, correlationid;
	zbx_dc_corr_condition_t	*condition;
	zbx_dc_correlation_t	*correlation;
	int			found, ret, i, index;
	unsigned char		type;
	size_t			condition_size;
	zbx_vector_ptr_t	correlations;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&correlations);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(correlationid, row[1]);

		if (NULL == (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations, &correlationid)))
			continue;

		ZBX_STR2UINT64(conditionid, row[0]);
		ZBX_STR2UCHAR(type, row[2]);

		condition_size = dc_corr_condition_get_size(type);
		condition = (zbx_dc_corr_condition_t *)DCfind_id(&config->corr_conditions, conditionid, condition_size, &found);

		condition->correlationid = correlationid;
		condition->type = type;
		dc_corr_condition_init_data(condition, found, row + 3);

		if (0 == found)
			zbx_vector_ptr_append(&correlation->conditions, condition);

		/* sort the conditions later */
		if (CONDITION_EVAL_TYPE_AND_OR == correlation->evaltype)
			zbx_vector_ptr_append(&correlations, correlation);
	}

	/* remove deleted correlation conditions */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (condition = (zbx_dc_corr_condition_t *)zbx_hashset_search(&config->corr_conditions, &rowid)))
			continue;

		/* remove condition from correlation->conditions vector */
		if (NULL != (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations, &condition->correlationid)))
		{
			if (FAIL != (index = zbx_vector_ptr_search(&correlation->conditions, condition,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				/* sort the conditions later */
				if (CONDITION_EVAL_TYPE_AND_OR == correlation->evaltype)
					zbx_vector_ptr_append(&correlations, correlation);

				zbx_vector_ptr_remove_noorder(&correlation->conditions, index);
			}
		}

		corr_condition_free_data(condition);
		zbx_hashset_remove_direct(&config->corr_conditions, condition);
	}

	/* sort conditions by type */

	zbx_vector_ptr_sort(&correlations, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(&correlations, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < correlations.values_num; i++)
	{
		correlation = (zbx_dc_correlation_t *)correlations.values[i];
		zbx_vector_ptr_sort(&correlation->conditions, dc_compare_corr_conditions_by_type);
	}

	zbx_vector_ptr_destroy(&correlations);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_corr_operations                                           *
 *                                                                            *
 * Purpose: Updates correlation operations configuration cache                *
 *                                                                            *
 * Parameters: result - [IN] the result of correlation operations database    *
 *                           select                                           *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - corr_operationid                                             *
 *           1 - correlationid                                                *
 *           2 - type                                                         *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_corr_operations(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_corr_operations";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		operationid, correlationid;
	zbx_dc_corr_operation_t	*operation;
	zbx_dc_correlation_t	*correlation;
	int			found, ret, index;
	unsigned char		type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(correlationid, row[1]);

		if (NULL == (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations, &correlationid)))
			continue;

		ZBX_STR2UINT64(operationid, row[0]);
		ZBX_STR2UCHAR(type, row[2]);

		operation = (zbx_dc_corr_operation_t *)DCfind_id(&config->corr_operations, operationid, sizeof(zbx_dc_corr_operation_t), &found);

		operation->type = type;

		if (0 == found)
		{
			operation->correlationid = correlationid;
			zbx_vector_ptr_append(&correlation->operations, operation);
		}
	}

	/* remove deleted correlation operations */

	/* remove deleted actions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (operation = (zbx_dc_corr_operation_t *)zbx_hashset_search(&config->corr_operations, &rowid)))
			continue;

		/* remove operation from correlation->conditions vector */
		if (NULL != (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations, &operation->correlationid)))
		{
			if (FAIL != (index = zbx_vector_ptr_search(&correlation->operations, operation,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_ptr_remove_noorder(&correlation->operations, index);
			}
		}
		zbx_hashset_remove_direct(&config->corr_operations, operation);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	dc_compare_hgroups(const void *d1, const void *d2)
{
	const zbx_dc_hostgroup_t	*g1 = *((const zbx_dc_hostgroup_t **)d1);
	const zbx_dc_hostgroup_t	*g2 = *((const zbx_dc_hostgroup_t **)d2);

	return strcmp(g1->name, g2->name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_hostgroups                                                *
 *                                                                            *
 * Purpose: Updates host groups configuration cache                           *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - groupid                                                      *
 *           1 - name                                                         *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_hostgroups(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_hostgroups";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		groupid;
	zbx_dc_hostgroup_t	*group;
	int			found, ret, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(groupid, row[0]);

		group = (zbx_dc_hostgroup_t *)DCfind_id(&config->hostgroups, groupid, sizeof(zbx_dc_hostgroup_t), &found);

		if (0 == found)
		{
			group->flags = ZBX_DC_HOSTGROUP_FLAGS_NONE;
			zbx_vector_ptr_append(&config->hostgroups_name, group);
		}

		DCstrpool_replace(found, &group->name, row[1]);
	}

	/* remove deleted host groups */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups, &rowid)))
			continue;

		if (FAIL != (index = zbx_vector_ptr_search(&config->hostgroups_name, group, dc_compare_hgroups)))
			zbx_vector_ptr_remove_noorder(&config->hostgroups_name, index);

		if (ZBX_DC_HOSTGROUP_FLAGS_NONE != group->flags)
			zbx_vector_uint64_destroy(&group->nested_groupids);

		zbx_strpool_release(group->name);
		zbx_hashset_remove_direct(&config->hostgroups, group);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_trigger_tags                                              *
 *                                                                            *
 * Purpose: Updates trigger tags in configuration cache                       *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - triggertagid                                                 *
 *           1 - triggerid                                                    *
 *           2 - tag                                                          *
 *           3 - value                                                        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_trigger_tags(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_trigger_tags";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	int			found, ret, index;
	zbx_uint64_t		triggerid, triggertagid;
	ZBX_DC_TRIGGER		*trigger;
	zbx_dc_trigger_tag_t	*trigger_tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(triggerid, row[1]);

		if (NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &triggerid)))
			continue;

		ZBX_STR2UINT64(triggertagid, row[0]);

		trigger_tag = (zbx_dc_trigger_tag_t *)DCfind_id(&config->trigger_tags, triggertagid, sizeof(zbx_dc_trigger_tag_t), &found);
		DCstrpool_replace(found, &trigger_tag->tag, row[2]);
		DCstrpool_replace(found, &trigger_tag->value, row[3]);

		if (0 == found)
		{
			trigger_tag->triggerid = triggerid;
			zbx_vector_ptr_append(&trigger->tags, trigger_tag);
		}
	}

	/* remove unused trigger tags */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (trigger_tag = (zbx_dc_trigger_tag_t *)zbx_hashset_search(&config->trigger_tags, &rowid)))
			continue;

		if (NULL != (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &trigger_tag->triggerid)))
		{
			if (FAIL != (index = zbx_vector_ptr_search(&trigger->tags, trigger_tag,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_ptr_remove_noorder(&trigger->tags, index);

				/* recreate empty tags vector to release used memory */
				if (0 == trigger->tags.values_num)
				{
					zbx_vector_ptr_destroy(&trigger->tags);
					zbx_vector_ptr_create_ext(&trigger->tags, __config_mem_malloc_func,
							__config_mem_realloc_func, __config_mem_free_func);
				}
			}
		}

		zbx_strpool_release(trigger_tag->tag);
		zbx_strpool_release(trigger_tag->value);

		zbx_hashset_remove_direct(&config->trigger_tags, trigger_tag);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_compare_item_preproc_by_step                                  *
 *                                                                            *
 * Purpose: compare two item preprocessing operations by step                 *
 *                                                                            *
 * Comments: This function is used to sort correlation conditions by type.    *
 *                                                                            *
 ******************************************************************************/
static int	dc_compare_preprocops_by_step(const void *d1, const void *d2)
{
	zbx_dc_preproc_op_t	*p1 = *(zbx_dc_preproc_op_t **)d1;
	zbx_dc_preproc_op_t	*p2 = *(zbx_dc_preproc_op_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->step, p2->step);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_item_preproc                                              *
 *                                                                            *
 * Purpose: Updates item preprocessing steps in configuration cache           *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - item_preprocid                                               *
 *           1 - itemid                                                       *
 *           2 - type                                                         *
 *           3 - params                                                       *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_item_preproc(zbx_dbsync_t *sync)
{
	const char		*__function_name = "DCsync_item_preproc";

	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		item_preprocid, itemid;
	int			found, ret, i, index;
	ZBX_DC_PREPROCITEM	*preprocitem = NULL;
	zbx_dc_preproc_op_t	*op;
	zbx_vector_ptr_t	items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&items);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[1]);

		if (NULL == preprocitem || itemid != preprocitem->itemid)
		{
			if (NULL == (preprocitem = (ZBX_DC_PREPROCITEM *)zbx_hashset_search(&config->preprocitems, &itemid)))
			{
				ZBX_DC_PREPROCITEM	preprocitem_local;

				preprocitem_local.itemid = itemid;

				preprocitem = (ZBX_DC_PREPROCITEM *)zbx_hashset_insert(&config->preprocitems, &preprocitem_local,
						sizeof(preprocitem_local));

				zbx_vector_ptr_create_ext(&preprocitem->preproc_ops, __config_mem_malloc_func,
						__config_mem_realloc_func, __config_mem_free_func);
			}
		}

		ZBX_STR2UINT64(item_preprocid, row[0]);

		op = (zbx_dc_preproc_op_t *)DCfind_id(&config->preprocops, item_preprocid, sizeof(zbx_dc_preproc_op_t), &found);

		ZBX_STR2UCHAR(op->type, row[2]);
		DCstrpool_replace(found, &op->params, row[3]);
		op->step = atoi(row[4]);

		if (0 == found)
		{
			op->itemid = itemid;
			zbx_vector_ptr_append(&preprocitem->preproc_ops, op);
		}

		zbx_vector_ptr_append(&items, preprocitem);
	}

	/* remove deleted item preprocessing operations */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (op = (zbx_dc_preproc_op_t *)zbx_hashset_search(&config->preprocops, &rowid)))
			continue;

		if (NULL != (preprocitem = (ZBX_DC_PREPROCITEM *)zbx_hashset_search(&config->preprocitems, &op->itemid)))
		{
			if (FAIL != (index = zbx_vector_ptr_search(&preprocitem->preproc_ops, op,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_ptr_remove_noorder(&preprocitem->preproc_ops, index);

				if (0 == preprocitem->preproc_ops.values_num)
				{
					zbx_vector_ptr_destroy(&preprocitem->preproc_ops);
					zbx_hashset_remove_direct(&config->preprocitems, preprocitem);
				}
				else
					zbx_vector_ptr_append(&items, preprocitem);
			}
		}

		zbx_hashset_remove_direct(&config->preprocops, op);
	}

	/* sort item  preprocessing operations by step */

	zbx_vector_ptr_sort(&items, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(&items, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < items.values_num; i++)
	{
		preprocitem = (ZBX_DC_PREPROCITEM *)items.values[i];
		zbx_vector_ptr_sort(&preprocitem->preproc_ops, dc_compare_preprocops_by_step);
	}

	zbx_vector_ptr_destroy(&items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_trigger_update_topology                                       *
 *                                                                            *
 * Purpose: updates trigger topology after trigger dependency changes         *
 *                                                                            *
 ******************************************************************************/
static void	dc_trigger_update_topology(void)
{
	zbx_hashset_iter_t	iter;
	ZBX_DC_TRIGGER		*trigger;

	zbx_hashset_iter_reset(&config->triggers, &iter);
	while (NULL != (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
		trigger->topoindex = 1;

	DCconfig_sort_triggers_topologically();
}

static int	zbx_default_ptr_pair_ptr_compare_func(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t	*p1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t	*p2 = (const zbx_ptr_pair_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->first, p2->first);
	ZBX_RETURN_IF_NOT_EQUAL(p1->second, p2->second);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_trigger_update_cache                                          *
 *                                                                            *
 * Purpose: updates trigger related cache data;                               *
 *              1) time triggers assigned to timer processes                  *
 *              2) trigger functionality (if it uses contain disabled         *
 *                 items/hosts)                                               *
 *              3) list of triggers each item is used by                      *
 *                                                                            *
 ******************************************************************************/
static void	dc_trigger_update_cache(void)
{
	zbx_hashset_iter_t	iter;
	ZBX_DC_TRIGGER		*trigger;
	ZBX_DC_FUNCTION		*function;
	ZBX_DC_ITEM		*item;
	int			i, j, k;
	zbx_ptr_pair_t		itemtrig;
	zbx_vector_ptr_pair_t	itemtrigs;
	ZBX_DC_HOST		*host;

	zbx_hashset_iter_reset(&config->triggers, &iter);
	while (NULL != (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
		trigger->functional = TRIGGER_FUNCTIONAL_TRUE;

	for (i = 0; i < CONFIG_TIMER_FORKS; i++)
		zbx_vector_ptr_clear(&config->time_triggers[i]);

	zbx_vector_ptr_pair_create(&itemtrigs);
	zbx_hashset_iter_reset(&config->functions, &iter);
	while (NULL != (function = (ZBX_DC_FUNCTION *)zbx_hashset_iter_next(&iter)))
	{

		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &function->itemid)) ||
				NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &function->triggerid)))
		{
			continue;
		}

		/* cache item - trigger link */
		if (0 != item->update_triggers)
		{
			itemtrig.first = item;
			itemtrig.second = trigger;
			zbx_vector_ptr_pair_append(&itemtrigs, itemtrig);
		}

		/* spread triggers with time-based functions between timer processes (load balancing) */
		if (1 == function->timer)
		{
			i = function->triggerid % CONFIG_TIMER_FORKS;
			zbx_vector_ptr_append(&config->time_triggers[i], trigger);
		}

		/* disable functionality for triggers with expression containing */
		/* disabled or not monitored items                               */

		if (TRIGGER_FUNCTIONAL_FALSE == trigger->functional)
			continue;

		if (ITEM_STATUS_DISABLED == item->status ||
				(NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &item->hostid)) ||
						HOST_STATUS_NOT_MONITORED == host->status))
		{
			trigger->functional = TRIGGER_FUNCTIONAL_FALSE;
		}
	}

	for (i = 0; i < CONFIG_TIMER_FORKS; i++)
	{
		zbx_vector_ptr_sort(&config->time_triggers[i], ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_ptr_uniq(&config->time_triggers[i], ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	zbx_vector_ptr_pair_sort(&itemtrigs, zbx_default_ptr_pair_ptr_compare_func);
	zbx_vector_ptr_pair_uniq(&itemtrigs, zbx_default_ptr_pair_ptr_compare_func);

	/* update links from items to triggers */
	for (i = 0; i < itemtrigs.values_num; i++)
	{
		for (j = i + 1; j < itemtrigs.values_num; j++)
		{
			if (itemtrigs.values[i].first != itemtrigs.values[j].first)
				break;
		}

		item = (ZBX_DC_ITEM *)itemtrigs.values[i].first;
		item->update_triggers = 0;
		item->triggers = (ZBX_DC_TRIGGER **)config->items.mem_realloc_func(item->triggers, (j - i + 1) * sizeof(ZBX_DC_TRIGGER *));

		for (k = i; k < j; k++)
			item->triggers[k - i] = (ZBX_DC_TRIGGER *)itemtrigs.values[k].second;

		item->triggers[j - i] = NULL;

		i = j - 1;
	}

	zbx_vector_ptr_pair_destroy(&itemtrigs);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_hostgroups_update_cache                                       *
 *                                                                            *
 * Purpose: updates hostgroup name index and resets nested group lists        *
 *                                                                            *
 ******************************************************************************/
static void	dc_hostgroups_update_cache(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_hostgroup_t	*group;

	zbx_vector_ptr_sort(&config->hostgroups_name, dc_compare_hgroups);

	zbx_hashset_iter_reset(&config->hostgroups, &iter);
	while (NULL != (group = (zbx_dc_hostgroup_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_DC_HOSTGROUP_FLAGS_NONE != group->flags)
		{
			group->flags = ZBX_DC_HOSTGROUP_FLAGS_NONE;
			zbx_vector_uint64_destroy(&group->nested_groupids);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_configuration                                             *
 *                                                                            *
 * Purpose: Synchronize configuration data from database                      *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
void	DCsync_configuration(unsigned char mode)
{
	const char		*__function_name = "DCsync_configuration";

	int			i, flags;
	double			sec, csec, hsec, hisec, htsec, gmsec, hmsec, ifsec, isec, tsec, dsec, fsec, expr_sec,
				csec2, hsec2, hisec2, htsec2, gmsec2, hmsec2, ifsec2, isec2, tsec2, dsec2, fsec2,
				expr_sec2, action_sec, action_sec2, action_op_sec, action_op_sec2, action_condition_sec,
				action_condition_sec2, trigger_tag_sec, trigger_tag_sec2, correlation_sec,
				correlation_sec2, corr_condition_sec, corr_condition_sec2, corr_operation_sec,
				corr_operation_sec2, hgroups_sec, hgroups_sec2, itempp_sec, itempp_sec2, total, total2,
				update_sec;

	zbx_dbsync_t		config_sync, hosts_sync, hi_sync, htmpl_sync, gmacro_sync, hmacro_sync, if_sync,
				items_sync, triggers_sync, tdep_sync, func_sync, expr_sync, action_sync, action_op_sync,
				action_condition_sync, trigger_tag_sync, correlation_sync, corr_condition_sync,
				corr_operation_sync, hgroups_sync, itempp_sync;
	zbx_uint64_t		update_flags = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_dbsync_init_env(config);

	/* global configuration must be synchronized directly with database */
	zbx_dbsync_init(&config_sync, ZBX_DBSYNC_INIT);

	zbx_dbsync_init(&hosts_sync, mode);
	zbx_dbsync_init(&hi_sync, mode);
	zbx_dbsync_init(&htmpl_sync, mode);
	zbx_dbsync_init(&gmacro_sync, mode);
	zbx_dbsync_init(&hmacro_sync, mode);
	zbx_dbsync_init(&if_sync, mode);
	zbx_dbsync_init(&items_sync, mode);
	zbx_dbsync_init(&triggers_sync, mode);
	zbx_dbsync_init(&tdep_sync, mode);
	zbx_dbsync_init(&func_sync, mode);
	zbx_dbsync_init(&expr_sync, mode);
	zbx_dbsync_init(&action_sync, mode);

	/* Action operation sync produces virtual rows with two columns - actionid, opflags. */
	/* Because of this it cannot return the original database select and must always be  */
	/* initialized in update mode.                                                       */
	zbx_dbsync_init(&action_op_sync, ZBX_DBSYNC_UPDATE);

	zbx_dbsync_init(&action_condition_sync, mode);
	zbx_dbsync_init(&trigger_tag_sync, mode);
	zbx_dbsync_init(&correlation_sync, mode);
	zbx_dbsync_init(&corr_condition_sync, mode);
	zbx_dbsync_init(&corr_operation_sync, mode);
	zbx_dbsync_init(&hgroups_sync, mode);
	zbx_dbsync_init(&itempp_sync, mode);

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_config(&config_sync))
		goto out;
	csec = zbx_time() - sec;

	/* sync global configuration settings */
	START_SYNC;
	sec = zbx_time();
	DCsync_config(&config_sync, &flags);
	csec2 = zbx_time() - sec;
	FINISH_SYNC;

	/* sync macro related data, to support macro resolving during configuration sync */

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_host_templates(&htmpl_sync))
		goto out;
	htsec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_global_macros(&gmacro_sync))
		goto out;
	gmsec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_host_macros(&hmacro_sync))
		goto out;
	hmsec = zbx_time() - sec;

	START_SYNC;
	sec = zbx_time();
	DCsync_htmpls(&htmpl_sync);
	htsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_gmacros(&gmacro_sync);
	gmsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_hmacros(&hmacro_sync);
	hmsec2 = zbx_time() - sec;
	FINISH_SYNC;

	/* sync host data to support host lookups when resolving macros during configuration sync */

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_hosts(&hosts_sync))
		goto out;
	hsec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_host_inventory(&hi_sync))
		goto out;
	hisec = zbx_time() - sec;

	START_SYNC;
	sec = zbx_time();
	DCsync_hosts(&hosts_sync);
	hsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_host_inventory(&hi_sync);
	hisec2 = zbx_time() - sec;
	FINISH_SYNC;

	/* sync item data to support item lookups when resolving macros during configuration sync */

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_interfaces(&if_sync))
		goto out;
	ifsec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_items(&items_sync))
		goto out;
	isec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_item_preprocs(&itempp_sync))
		goto out;
	itempp_sec = zbx_time() - sec;

	START_SYNC;
	sec = zbx_time();
	/* resolves macros for interface_snmpaddrs, must be after DCsync_hmacros() */
	DCsync_interfaces(&if_sync);
	ifsec2 = zbx_time() - sec;

	sec = zbx_time();
	/* relies on hosts, proxies and interfaces, must be after DCsync_{hosts,interfaces}() */
	DCsync_items(&items_sync, flags);
	isec2 = zbx_time() - sec;

	sec = zbx_time();
	/* relies on items, must be after DCsync_items() */
	DCsync_item_preproc(&itempp_sync);
	itempp_sec2 = zbx_time() - sec;
	config->item_sync_ts = time(NULL);
	FINISH_SYNC;

	/* sync function data to support function lookups when resolving macros during configuration sync */

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_functions(&func_sync))
		goto out;
	fsec = zbx_time() - sec;

	START_SYNC;
	sec = zbx_time();
	DCsync_functions(&func_sync);
	fsec2 = zbx_time() - sec;
	FINISH_SYNC;

	/* sync rest of the data */

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_triggers(&triggers_sync))
		goto out;
	tsec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_trigger_dependency(&tdep_sync))
		goto out;
	dsec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_expressions(&expr_sync))
		goto out;
	expr_sec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_actions(&action_sync))
		goto out;
	action_sec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_action_ops(&action_op_sync))
		goto out;
	action_op_sec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_action_conditions(&action_condition_sync))
		goto out;
	action_condition_sec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_trigger_tags(&trigger_tag_sync))
		goto out;
	trigger_tag_sec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_correlations(&correlation_sync))
		goto out;
	correlation_sec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_corr_conditions(&corr_condition_sync))
		goto out;
	corr_condition_sec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_corr_operations(&corr_operation_sync))
		goto out;
	corr_operation_sec = zbx_time() - sec;

	sec = zbx_time();
	if (FAIL == zbx_dbsync_compare_host_groups(&hgroups_sync))
		goto out;
	hgroups_sec = zbx_time() - sec;

	START_SYNC;

	sec = zbx_time();
	DCsync_triggers(&triggers_sync);
	tsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_trigdeps(&tdep_sync);
	dsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_expressions(&expr_sync);
	expr_sec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_actions(&action_sync);
	action_sec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_action_ops(&action_op_sync);
	action_op_sec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_action_conditions(&action_condition_sync);
	action_condition_sec2 = zbx_time() - sec;

	sec = zbx_time();
	/* relies on triggers, must be after DCsync_triggers() */
	DCsync_trigger_tags(&trigger_tag_sync);
	trigger_tag_sec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_correlations(&correlation_sync);
	correlation_sec2 = zbx_time() - sec;

	sec = zbx_time();
	/* relies on correlation rules, must be after DCsync_correlations() */
	DCsync_corr_conditions(&corr_condition_sync);
	corr_condition_sec2 = zbx_time() - sec;

	sec = zbx_time();
	/* relies on correlation rules, must be after DCsync_correlations() */
	DCsync_corr_operations(&corr_operation_sync);
	corr_operation_sec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_hostgroups(&hgroups_sync);
	hgroups_sec2 = zbx_time() - sec;

	sec = zbx_time();

	if (0 != hosts_sync.add_num + hosts_sync.update_num + hosts_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_HOSTS;

	if (0 != items_sync.add_num + items_sync.update_num + items_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_ITEMS;

	if (0 != htmpl_sync.add_num + htmpl_sync.update_num + htmpl_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_HOST_TEMPLATES;

	if (0 != func_sync.add_num + func_sync.update_num + func_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_FUNCTIONS;

	if (0 != gmacro_sync.add_num + gmacro_sync.update_num + gmacro_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_MACROS;

	if (0 != hmacro_sync.add_num + hmacro_sync.update_num + hmacro_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_MACROS;

	if (0 != triggers_sync.add_num + triggers_sync.update_num + triggers_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_TRIGGERS;

	if (0 != tdep_sync.add_num + tdep_sync.update_num + tdep_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_TRIGGER_DEPENDENCY;

	if (0 != hgroups_sync.add_num + hgroups_sync.update_num + hgroups_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_HOST_GROUPS;

	/* update trigger topology if trigger dependency was changed */
	if (0 != (update_flags & ZBX_DBSYNC_UPDATE_TRIGGER_DEPENDENCY))
		dc_trigger_update_topology();

	/* update various trigger related links in cache */
	if (0 != (update_flags & (ZBX_DBSYNC_UPDATE_HOSTS | ZBX_DBSYNC_UPDATE_ITEMS | ZBX_DBSYNC_UPDATE_FUNCTIONS |
			ZBX_DBSYNC_UPDATE_TRIGGERS)))
	{
		dc_trigger_update_cache();
	}

	if (0 != (update_flags & ZBX_DBSYNC_UPDATE_HOST_GROUPS))
		dc_hostgroups_update_cache();

	update_sec = zbx_time() - sec;

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
	{
		total = csec + hsec + hisec + htsec + gmsec + hmsec + ifsec + isec + tsec + dsec + fsec + expr_sec +
				action_sec + action_op_sec + action_condition_sec + trigger_tag_sec + correlation_sec +
				corr_condition_sec + corr_operation_sec + hgroups_sec + itempp_sec;
		total2 = csec2 + hsec2 + hisec2 + htsec2 + gmsec2 + hmsec2 + ifsec2 + isec2 + tsec2 + dsec2 + fsec2 +
				expr_sec2 + action_op_sec2 + action_sec2 + action_condition_sec2 + trigger_tag_sec2 +
				correlation_sec2 + corr_condition_sec2 + corr_operation_sec2 + hgroups_sec2 +
				itempp_sec2 + update_sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() config     : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, csec, csec2, config_sync.add_num, config_sync.update_num,
				config_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts      : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, hsec, hsec2, hosts_sync.add_num, hosts_sync.update_num,
				hosts_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() host_invent: sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, hisec, hisec2, hi_sync.add_num, hi_sync.update_num,
				hi_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() templates  : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, htsec, htsec2, htmpl_sync.add_num, htmpl_sync.update_num,
				htmpl_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() globmacros : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, gmsec, gmsec2, gmacro_sync.add_num, gmacro_sync.update_num,
				gmacro_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hostmacros : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, hmsec, hmsec2, hmacro_sync.add_num, hmacro_sync.update_num,
				hmacro_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() interfaces : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, ifsec, ifsec2, if_sync.add_num, if_sync.update_num,
				if_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() items      : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, isec, isec2, items_sync.add_num, items_sync.update_num,
				items_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() triggers   : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, tsec, tsec2, triggers_sync.add_num, triggers_sync.update_num,
				triggers_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigdeps   : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, dsec, dsec2, tdep_sync.add_num, tdep_sync.update_num,
				tdep_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trig. tags : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, trigger_tag_sec, trigger_tag_sec2, trigger_tag_sync.add_num,
				trigger_tag_sync.update_num, trigger_tag_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() functions  : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, fsec, fsec2, func_sync.add_num, func_sync.update_num,
				func_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() expressions: sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, expr_sec, expr_sec2, expr_sync.add_num, expr_sync.update_num,
				expr_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() actions    : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, action_sec, action_sec2, action_sync.add_num, action_sync.update_num,
				action_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() operations : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, action_op_sec, action_op_sec2, action_op_sync.add_num,
				action_op_sync.update_num, action_op_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() conditions : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, action_condition_sec, action_condition_sec2,
				action_condition_sync.add_num, action_condition_sync.update_num,
				action_condition_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr       : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, correlation_sec, correlation_sec2, correlation_sync.add_num,
				correlation_sync.update_num, correlation_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr_cond  : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, corr_condition_sec, corr_condition_sec2, corr_condition_sync.add_num,
				corr_condition_sync.update_num, corr_condition_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr_op    : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, corr_operation_sec, corr_operation_sec2, corr_operation_sync.add_num,
				corr_operation_sync.update_num, corr_operation_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hgroups    : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, hgroups_sec, hgroups_sec2, hgroups_sync.add_num,
				hgroups_sync.update_num, hgroups_sync.remove_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() item pproc : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec (%d/%d/%d).",
				__function_name, itempp_sec, itempp_sec2, itempp_sync.add_num, itempp_sync.update_num,
				itempp_sync.remove_num);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() reindex    : " ZBX_FS_DBL " sec.", __function_name, update_sec);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() total sql  : " ZBX_FS_DBL " sec.", __function_name, total);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() total sync : " ZBX_FS_DBL " sec.", __function_name, total2);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() proxies    : %d (%d slots)", __function_name,
				config->proxies.num_data, config->proxies.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts      : %d (%d slots)", __function_name,
				config->hosts.num_data, config->hosts.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts_h    : %d (%d slots)", __function_name,
				config->hosts_h.num_data, config->hosts_h.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts_p    : %d (%d slots)", __function_name,
				config->hosts_p.num_data, config->hosts_p.num_slots);
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() psks       : %d (%d slots)", __function_name,
				config->psks.num_data, config->psks.num_slots);
#endif
		zabbix_log(LOG_LEVEL_DEBUG, "%s() ipmihosts  : %d (%d slots)", __function_name,
				config->ipmihosts.num_data, config->ipmihosts.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() host_invent: %d (%d slots)", __function_name,
				config->host_inventories.num_data, config->host_inventories.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() htmpls     : %d (%d slots)", __function_name,
				config->htmpls.num_data, config->htmpls.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() gmacros    : %d (%d slots)", __function_name,
				config->gmacros.num_data, config->gmacros.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() gmacros_m  : %d (%d slots)", __function_name,
				config->gmacros_m.num_data, config->gmacros_m.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hmacros    : %d (%d slots)", __function_name,
				config->hmacros.num_data, config->hmacros.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hmacros_hm : %d (%d slots)", __function_name,
				config->hmacros_hm.num_data, config->hmacros_hm.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() interfaces : %d (%d slots)", __function_name,
				config->interfaces.num_data, config->interfaces.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() interfac_ht: %d (%d slots)", __function_name,
				config->interfaces_ht.num_data, config->interfaces_ht.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() if_snmpitms: %d (%d slots)", __function_name,
				config->interface_snmpitems.num_data, config->interface_snmpitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() if_snmpaddr: %d (%d slots)", __function_name,
				config->interface_snmpaddrs.num_data, config->interface_snmpaddrs.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() items      : %d (%d slots)", __function_name,
				config->items.num_data, config->items.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() items_hk   : %d (%d slots)", __function_name,
				config->items_hk.num_data, config->items_hk.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() numitems   : %d (%d slots)", __function_name,
				config->numitems.num_data, config->numitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() snmpitems  : %d (%d slots)", __function_name,
				config->snmpitems.num_data, config->snmpitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() ipmiitems  : %d (%d slots)", __function_name,
				config->ipmiitems.num_data, config->ipmiitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trapitems  : %d (%d slots)", __function_name,
				config->trapitems.num_data, config->trapitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() dependentitems  : %d (%d slots)", __function_name,
				config->dependentitems.num_data, config->dependentitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() logitems   : %d (%d slots)", __function_name,
				config->logitems.num_data, config->logitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() dbitems    : %d (%d slots)", __function_name,
				config->dbitems.num_data, config->dbitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() sshitems   : %d (%d slots)", __function_name,
				config->sshitems.num_data, config->sshitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() telnetitems: %d (%d slots)", __function_name,
				config->telnetitems.num_data, config->telnetitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() simpleitems: %d (%d slots)", __function_name,
				config->simpleitems.num_data, config->simpleitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() jmxitems   : %d (%d slots)", __function_name,
				config->jmxitems.num_data, config->jmxitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() calcitems  : %d (%d slots)", __function_name,
				config->calcitems.num_data, config->calcitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() functions  : %d (%d slots)", __function_name,
				config->functions.num_data, config->functions.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() triggers   : %d (%d slots)", __function_name,
				config->triggers.num_data, config->triggers.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigdeps   : %d (%d slots)", __function_name,
				config->trigdeps.num_data, config->trigdeps.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trig. tags : %d (%d slots)", __function_name,
				config->trigger_tags.num_data, config->trigger_tags.num_slots);
		for (i = 0; i < CONFIG_TIMER_FORKS; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() t_trigs[%d] : %d (%d allocated)", __function_name,
					i, config->time_triggers[i].values_num, config->time_triggers[i].values_alloc);
		}
		zabbix_log(LOG_LEVEL_DEBUG, "%s() expressions: %d (%d slots)", __function_name,
				config->expressions.num_data, config->expressions.num_slots);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() actions    : %d (%d slots)", __function_name,
				config->actions.num_data, config->actions.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() conditions : %d (%d slots)", __function_name,
				config->action_conditions.num_data, config->action_conditions.num_slots);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr.      : %d (%d slots)", __function_name,
				config->correlations.num_data, config->correlations.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr. conds: %d (%d slots)", __function_name,
				config->corr_conditions.num_data, config->corr_conditions.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr. ops  : %d (%d slots)", __function_name,
				config->corr_operations.num_data, config->corr_operations.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hgroups    : %d (%d slots)", __function_name,
				config->hostgroups.num_data, config->hostgroups.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() item procs : %d (%d slots)", __function_name,
				config->preprocops.num_data, config->preprocops.num_slots);

		for (i = 0; ZBX_POLLER_TYPE_COUNT > i; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() queue[%d]   : %d (%d allocated)", __function_name,
					i, config->queues[i].elems_num, config->queues[i].elems_alloc);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() pqueue     : %d (%d allocated)", __function_name,
				config->pqueue.elems_num, config->pqueue.elems_alloc);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() configfree : " ZBX_FS_DBL "%%", __function_name,
				100 * ((double)config_mem->free_size / config_mem->orig_size));

		zabbix_log(LOG_LEVEL_DEBUG, "%s() strings    : %d (%d slots)", __function_name,
				config->strpool.num_data, config->strpool.num_slots);

		zbx_mem_dump_stats(config_mem);
	}

	config->status->last_update = 0;
	config->sync_ts = time(NULL);

	FINISH_SYNC;
out:
	zbx_dbsync_clear(&config_sync);
	zbx_dbsync_clear(&hosts_sync);
	zbx_dbsync_clear(&hi_sync);
	zbx_dbsync_clear(&htmpl_sync);
	zbx_dbsync_clear(&gmacro_sync);
	zbx_dbsync_clear(&hmacro_sync);
	zbx_dbsync_clear(&if_sync);
	zbx_dbsync_clear(&items_sync);
	zbx_dbsync_clear(&triggers_sync);
	zbx_dbsync_clear(&tdep_sync);
	zbx_dbsync_clear(&func_sync);
	zbx_dbsync_clear(&expr_sync);
	zbx_dbsync_clear(&action_sync);
	zbx_dbsync_clear(&action_op_sync);
	zbx_dbsync_clear(&action_condition_sync);
	zbx_dbsync_clear(&trigger_tag_sync);
	zbx_dbsync_clear(&correlation_sync);
	zbx_dbsync_clear(&corr_condition_sync);
	zbx_dbsync_clear(&corr_operation_sync);
	zbx_dbsync_clear(&hgroups_sync);
	zbx_dbsync_clear(&itempp_sync);

	zbx_dbsync_free_env();

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_TRACE))
		DCdump_configuration(config);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Helper functions for configuration cache data structure element comparison *
 * and hash value calculation.                                                *
 *                                                                            *
 * The __config_mem_XXX_func(), __config_XXX_hash and __config_XXX_compare    *
 * functions are used only inside init_configuration_cache() function to      *
 * initialize internal data structures.                                       *
 *                                                                            *
 ******************************************************************************/

static zbx_hash_t	__config_item_hk_hash(const void *data)
{
	const ZBX_DC_ITEM_HK	*item_hk = (const ZBX_DC_ITEM_HK *)data;

	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&item_hk->hostid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(item_hk->key, strlen(item_hk->key), hash);

	return hash;
}

static int	__config_item_hk_compare(const void *d1, const void *d2)
{
	const ZBX_DC_ITEM_HK	*item_hk_1 = (const ZBX_DC_ITEM_HK *)d1;
	const ZBX_DC_ITEM_HK	*item_hk_2 = (const ZBX_DC_ITEM_HK *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(item_hk_1->hostid, item_hk_2->hostid);

	return item_hk_1->key == item_hk_2->key ? 0 : strcmp(item_hk_1->key, item_hk_2->key);
}

static zbx_hash_t	__config_host_h_hash(const void *data)
{
	const ZBX_DC_HOST_H	*host_h = (const ZBX_DC_HOST_H *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(host_h->host, strlen(host_h->host), ZBX_DEFAULT_HASH_SEED);
}

static int	__config_host_h_compare(const void *d1, const void *d2)
{
	const ZBX_DC_HOST_H	*host_h_1 = (const ZBX_DC_HOST_H *)d1;
	const ZBX_DC_HOST_H	*host_h_2 = (const ZBX_DC_HOST_H *)d2;

	return host_h_1->host == host_h_2->host ? 0 : strcmp(host_h_1->host, host_h_2->host);
}

static zbx_hash_t	__config_gmacro_m_hash(const void *data)
{
	const ZBX_DC_GMACRO_M	*gmacro_m = (const ZBX_DC_GMACRO_M *)data;

	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_STRING_HASH_FUNC(gmacro_m->macro);

	return hash;
}

static int	__config_gmacro_m_compare(const void *d1, const void *d2)
{
	const ZBX_DC_GMACRO_M	*gmacro_m_1 = (const ZBX_DC_GMACRO_M *)d1;
	const ZBX_DC_GMACRO_M	*gmacro_m_2 = (const ZBX_DC_GMACRO_M *)d2;

	return gmacro_m_1->macro == gmacro_m_2->macro ? 0 : strcmp(gmacro_m_1->macro, gmacro_m_2->macro);
}

static zbx_hash_t	__config_hmacro_hm_hash(const void *data)
{
	const ZBX_DC_HMACRO_HM	*hmacro_hm = (const ZBX_DC_HMACRO_HM *)data;

	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&hmacro_hm->hostid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(hmacro_hm->macro, strlen(hmacro_hm->macro), hash);

	return hash;
}

static int	__config_hmacro_hm_compare(const void *d1, const void *d2)
{
	const ZBX_DC_HMACRO_HM	*hmacro_hm_1 = (const ZBX_DC_HMACRO_HM *)d1;
	const ZBX_DC_HMACRO_HM	*hmacro_hm_2 = (const ZBX_DC_HMACRO_HM *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(hmacro_hm_1->hostid, hmacro_hm_2->hostid);

	return hmacro_hm_1->macro == hmacro_hm_2->macro ? 0 : strcmp(hmacro_hm_1->macro, hmacro_hm_2->macro);
}

static zbx_hash_t	__config_interface_ht_hash(const void *data)
{
	const ZBX_DC_INTERFACE_HT	*interface_ht = (const ZBX_DC_INTERFACE_HT *)data;

	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&interface_ht->hostid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO((char *)&interface_ht->type, 1, hash);

	return hash;
}

static int	__config_interface_ht_compare(const void *d1, const void *d2)
{
	const ZBX_DC_INTERFACE_HT	*interface_ht_1 = (const ZBX_DC_INTERFACE_HT *)d1;
	const ZBX_DC_INTERFACE_HT	*interface_ht_2 = (const ZBX_DC_INTERFACE_HT *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(interface_ht_1->hostid, interface_ht_2->hostid);
	ZBX_RETURN_IF_NOT_EQUAL(interface_ht_1->type, interface_ht_2->type);

	return 0;
}

static zbx_hash_t	__config_interface_addr_hash(const void *data)
{
	const ZBX_DC_INTERFACE_ADDR	*interface_addr = (const ZBX_DC_INTERFACE_ADDR *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(interface_addr->addr, strlen(interface_addr->addr), ZBX_DEFAULT_HASH_SEED);
}

static int	__config_interface_addr_compare(const void *d1, const void *d2)
{
	const ZBX_DC_INTERFACE_ADDR	*interface_addr_1 = (const ZBX_DC_INTERFACE_ADDR *)d1;
	const ZBX_DC_INTERFACE_ADDR	*interface_addr_2 = (const ZBX_DC_INTERFACE_ADDR *)d2;

	return (interface_addr_1->addr == interface_addr_2->addr ? 0 : strcmp(interface_addr_1->addr, interface_addr_2->addr));
}

static int	__config_snmp_item_compare(const ZBX_DC_ITEM *i1, const ZBX_DC_ITEM *i2)
{
	const ZBX_DC_SNMPITEM	*s1;
	const ZBX_DC_SNMPITEM	*s2;

	unsigned char		f1;
	unsigned char		f2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->interfaceid, i2->interfaceid);
	ZBX_RETURN_IF_NOT_EQUAL(i1->port, i2->port);
	ZBX_RETURN_IF_NOT_EQUAL(i1->type, i2->type);

	f1 = ZBX_FLAG_DISCOVERY_RULE & i1->flags;
	f2 = ZBX_FLAG_DISCOVERY_RULE & i2->flags;

	ZBX_RETURN_IF_NOT_EQUAL(f1, f2);

	s1 = (ZBX_DC_SNMPITEM *)zbx_hashset_search(&config->snmpitems, &i1->itemid);
	s2 = (ZBX_DC_SNMPITEM *)zbx_hashset_search(&config->snmpitems, &i2->itemid);

	ZBX_RETURN_IF_NOT_EQUAL(s1->snmp_community, s2->snmp_community);
	ZBX_RETURN_IF_NOT_EQUAL(s1->snmpv3_securityname, s2->snmpv3_securityname);
	ZBX_RETURN_IF_NOT_EQUAL(s1->snmpv3_authpassphrase, s2->snmpv3_authpassphrase);
	ZBX_RETURN_IF_NOT_EQUAL(s1->snmpv3_privpassphrase, s2->snmpv3_privpassphrase);
	ZBX_RETURN_IF_NOT_EQUAL(s1->snmpv3_contextname, s2->snmpv3_contextname);
	ZBX_RETURN_IF_NOT_EQUAL(s1->snmpv3_securitylevel, s2->snmpv3_securitylevel);
	ZBX_RETURN_IF_NOT_EQUAL(s1->snmpv3_authprotocol, s2->snmpv3_authprotocol);
	ZBX_RETURN_IF_NOT_EQUAL(s1->snmpv3_privprotocol, s2->snmpv3_privprotocol);
	ZBX_RETURN_IF_NOT_EQUAL(s1->snmp_oid_type, s2->snmp_oid_type);

	return 0;
}

static int	__config_heap_elem_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const ZBX_DC_ITEM		*i1 = (const ZBX_DC_ITEM *)e1->data;
	const ZBX_DC_ITEM		*i2 = (const ZBX_DC_ITEM *)e2->data;

	ZBX_RETURN_IF_NOT_EQUAL(i1->nextcheck, i2->nextcheck);
	ZBX_RETURN_IF_NOT_EQUAL(i1->queue_priority, i2->queue_priority);

	if (SUCCEED != is_snmp_type(i1->type))
	{
		if (SUCCEED != is_snmp_type(i2->type))
			return 0;

		return -1;
	}
	else
	{
		if (SUCCEED != is_snmp_type(i2->type))
			return +1;

		return __config_snmp_item_compare(i1, i2);
	}
}

static int	__config_pinger_elem_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const ZBX_DC_ITEM		*i1 = (const ZBX_DC_ITEM *)e1->data;
	const ZBX_DC_ITEM		*i2 = (const ZBX_DC_ITEM *)e2->data;

	ZBX_RETURN_IF_NOT_EQUAL(i1->nextcheck, i2->nextcheck);
	ZBX_RETURN_IF_NOT_EQUAL(i1->queue_priority, i2->queue_priority);
	ZBX_RETURN_IF_NOT_EQUAL(i1->interfaceid, i2->interfaceid);

	return 0;
}

static int	__config_java_item_compare(const ZBX_DC_ITEM *i1, const ZBX_DC_ITEM *i2)
{
	const ZBX_DC_JMXITEM	*j1;
	const ZBX_DC_JMXITEM	*j2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->interfaceid, i2->interfaceid);

	j1 = (ZBX_DC_JMXITEM *)zbx_hashset_search(&config->jmxitems, &i1->itemid);
	j2 = (ZBX_DC_JMXITEM *)zbx_hashset_search(&config->jmxitems, &i2->itemid);

	ZBX_RETURN_IF_NOT_EQUAL(j1->username, j2->username);
	ZBX_RETURN_IF_NOT_EQUAL(j1->password, j2->password);
	ZBX_RETURN_IF_NOT_EQUAL(j1->jmx_endpoint, j2->jmx_endpoint);

	return 0;
}

static int	__config_java_elem_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const ZBX_DC_ITEM		*i1 = (const ZBX_DC_ITEM *)e1->data;
	const ZBX_DC_ITEM		*i2 = (const ZBX_DC_ITEM *)e2->data;

	ZBX_RETURN_IF_NOT_EQUAL(i1->nextcheck, i2->nextcheck);
	ZBX_RETURN_IF_NOT_EQUAL(i1->queue_priority, i2->queue_priority);

	return __config_java_item_compare(i1, i2);
}

static int	__config_proxy_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const ZBX_DC_PROXY		*p1 = (const ZBX_DC_PROXY *)e1->data;
	const ZBX_DC_PROXY		*p2 = (const ZBX_DC_PROXY *)e2->data;

	ZBX_RETURN_IF_NOT_EQUAL(p1->nextcheck, p2->nextcheck);

	return 0;
}

/* hash and compare functions for expressions hashset */

static zbx_hash_t	__config_regexp_hash(const void *data)
{
	const ZBX_DC_REGEXP	*regexp = (const ZBX_DC_REGEXP *)data;

	return ZBX_DEFAULT_STRING_HASH_FUNC(regexp->name);
}

static int	__config_regexp_compare(const void *d1, const void *d2)
{
	const ZBX_DC_REGEXP	*r1 = (const ZBX_DC_REGEXP *)d1;
	const ZBX_DC_REGEXP	*r2 = (const ZBX_DC_REGEXP *)d2;

	return r1->name == r2->name ? 0 : strcmp(r1->name, r2->name);
}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
static zbx_hash_t	__config_psk_hash(const void *data)
{
	const ZBX_DC_PSK	*psk_i = (const ZBX_DC_PSK *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(psk_i->tls_psk_identity, strlen(psk_i->tls_psk_identity),
			ZBX_DEFAULT_HASH_SEED);
}

static int	__config_psk_compare(const void *d1, const void *d2)
{
	const ZBX_DC_PSK	*psk_1 = (const ZBX_DC_PSK *)d1;
	const ZBX_DC_PSK	*psk_2 = (const ZBX_DC_PSK *)d2;

	return psk_1->tls_psk_identity == psk_2->tls_psk_identity ? 0 : strcmp(psk_1->tls_psk_identity,
			psk_2->tls_psk_identity);
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: init_configuration_cache                                         *
 *                                                                            *
 * Purpose: Allocate shared memory for configuration cache                    *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
int	init_configuration_cache(char **error)
{
	const char	*__function_name = "init_configuration_cache";

	int		i, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() size:" ZBX_FS_UI64, __function_name, CONFIG_CONF_CACHE_SIZE);

	if (SUCCEED != (ret = zbx_mutex_create(&config_lock, ZBX_MUTEX_CONFIG, error)))
		goto out;

	if (SUCCEED != (ret = zbx_mem_create(&config_mem, CONFIG_CONF_CACHE_SIZE, "configuration cache",
			"CacheSize", 0, error)))
	{
		goto out;
	}

	config = (ZBX_DC_CONFIG *)__config_mem_malloc_func(NULL, sizeof(ZBX_DC_CONFIG) +
			CONFIG_TIMER_FORKS * sizeof(zbx_vector_ptr_t));
	config->time_triggers = (zbx_vector_ptr_t *)(config + 1);

#define CREATE_HASHSET(hashset, hashset_size)									\
														\
	CREATE_HASHSET_EXT(hashset, hashset_size, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC)

#define CREATE_HASHSET_EXT(hashset, hashset_size, hash_func, compare_func)					\
														\
	zbx_hashset_create_ext(&hashset, hashset_size, hash_func, compare_func, NULL,				\
			__config_mem_malloc_func, __config_mem_realloc_func, __config_mem_free_func)

	CREATE_HASHSET(config->items, 100);
	CREATE_HASHSET(config->numitems, 0);
	CREATE_HASHSET(config->snmpitems, 0);
	CREATE_HASHSET(config->ipmiitems, 0);
	CREATE_HASHSET(config->trapitems, 0);
	CREATE_HASHSET(config->dependentitems, 0);
	CREATE_HASHSET(config->logitems, 0);
	CREATE_HASHSET(config->dbitems, 0);
	CREATE_HASHSET(config->sshitems, 0);
	CREATE_HASHSET(config->telnetitems, 0);
	CREATE_HASHSET(config->simpleitems, 0);
	CREATE_HASHSET(config->jmxitems, 0);
	CREATE_HASHSET(config->calcitems, 0);
	CREATE_HASHSET(config->masteritems, 0);
	CREATE_HASHSET(config->preprocitems, 0);
	CREATE_HASHSET(config->functions, 100);
	CREATE_HASHSET(config->triggers, 100);
	CREATE_HASHSET(config->trigdeps, 0);
	CREATE_HASHSET(config->hosts, 10);
	CREATE_HASHSET(config->proxies, 0);
	CREATE_HASHSET(config->host_inventories, 0);
	CREATE_HASHSET(config->host_inventories_auto, 0);
	CREATE_HASHSET(config->ipmihosts, 0);
	CREATE_HASHSET(config->htmpls, 0);
	CREATE_HASHSET(config->gmacros, 0);
	CREATE_HASHSET(config->hmacros, 0);
	CREATE_HASHSET(config->interfaces, 10);
	CREATE_HASHSET(config->interface_snmpitems, 0);
	CREATE_HASHSET(config->expressions, 0);
	CREATE_HASHSET(config->actions, 0);
	CREATE_HASHSET(config->action_conditions, 0);
	CREATE_HASHSET(config->trigger_tags, 0);
	CREATE_HASHSET(config->correlations, 0);
	CREATE_HASHSET(config->corr_conditions, 0);
	CREATE_HASHSET(config->corr_operations, 0);
	CREATE_HASHSET(config->hostgroups, 0);
	zbx_vector_ptr_create_ext(&config->hostgroups_name, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);

	CREATE_HASHSET(config->preprocops, 0);

	CREATE_HASHSET_EXT(config->items_hk, 100, __config_item_hk_hash, __config_item_hk_compare);
	CREATE_HASHSET_EXT(config->hosts_h, 10, __config_host_h_hash, __config_host_h_compare);
	CREATE_HASHSET_EXT(config->hosts_p, 0, __config_host_h_hash, __config_host_h_compare);
	CREATE_HASHSET_EXT(config->gmacros_m, 0, __config_gmacro_m_hash, __config_gmacro_m_compare);
	CREATE_HASHSET_EXT(config->hmacros_hm, 0, __config_hmacro_hm_hash, __config_hmacro_hm_compare);
	CREATE_HASHSET_EXT(config->interfaces_ht, 10, __config_interface_ht_hash, __config_interface_ht_compare);
	CREATE_HASHSET_EXT(config->interface_snmpaddrs, 0, __config_interface_addr_hash, __config_interface_addr_compare);
	CREATE_HASHSET_EXT(config->regexps, 0, __config_regexp_hash, __config_regexp_compare);

	CREATE_HASHSET_EXT(config->strpool, 100, __config_strpool_hash, __config_strpool_compare);

	zbx_vector_uint64_create_ext(&config->locked_lld_ruleids,
			__config_mem_malloc_func,
			__config_mem_realloc_func,
			__config_mem_free_func);

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	CREATE_HASHSET_EXT(config->psks, 0, __config_psk_hash, __config_psk_compare);
#endif
	for (i = 0; i < CONFIG_TIMER_FORKS; i++)
	{
		zbx_vector_ptr_create_ext(&config->time_triggers[i],
				__config_mem_malloc_func,
				__config_mem_realloc_func,
				__config_mem_free_func);
	}

	for (i = 0; i < ZBX_POLLER_TYPE_COUNT; i++)
	{
		switch (i)
		{
			case ZBX_POLLER_TYPE_JAVA:
				zbx_binary_heap_create_ext(&config->queues[i],
						__config_java_elem_compare,
						ZBX_BINARY_HEAP_OPTION_DIRECT,
						__config_mem_malloc_func,
						__config_mem_realloc_func,
						__config_mem_free_func);
				break;
			case ZBX_POLLER_TYPE_PINGER:
				zbx_binary_heap_create_ext(&config->queues[i],
						__config_pinger_elem_compare,
						ZBX_BINARY_HEAP_OPTION_DIRECT,
						__config_mem_malloc_func,
						__config_mem_realloc_func,
						__config_mem_free_func);
				break;
			default:
				zbx_binary_heap_create_ext(&config->queues[i],
						__config_heap_elem_compare,
						ZBX_BINARY_HEAP_OPTION_DIRECT,
						__config_mem_malloc_func,
						__config_mem_realloc_func,
						__config_mem_free_func);
				break;
		}
	}

	zbx_binary_heap_create_ext(&config->pqueue,
					__config_proxy_compare,
					ZBX_BINARY_HEAP_OPTION_DIRECT,
					__config_mem_malloc_func,
					__config_mem_realloc_func,
					__config_mem_free_func);

	config->config = NULL;

	config->status = (ZBX_DC_STATUS *)__config_mem_malloc_func(NULL, sizeof(ZBX_DC_STATUS));
	config->status->last_update = 0;

	config->availability_diff_ts = 0;
	config->sync_ts = 0;
	config->item_sync_ts = 0;
	config->proxy_lastaccess_ts = time(NULL);

#undef CREATE_HASHSET
#undef CREATE_HASHSET_EXT
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: free_configuration_cache                                         *
 *                                                                            *
 * Purpose: Free memory allocated for configuration cache                     *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
void	free_configuration_cache(void)
{
	const char	*__function_name = "free_configuration_cache";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_CACHE;

	config = NULL;

	UNLOCK_CACHE;

	zbx_mutex_destroy(&config_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: in_maintenance_without_data_collection                           *
 *                                                                            *
 * Parameters: maintenance_status - [IN] maintenance status                   *
 *                                       HOST_MAINTENANCE_STATUS_* flag       *
 *             maintenance_type   - [IN] maintenance type                     *
 *                                       MAINTENANCE_TYPE_* flag              *
 *             type               - [IN] item type                            *
 *                                       ITEM_TYPE_* flag                     *
 *                                                                            *
 * Return value: SUCCEED if host in maintenance without data collection       *
 *               FAIL otherwise                                               *
 *                                                                            *
 ******************************************************************************/
int	in_maintenance_without_data_collection(unsigned char maintenance_status, unsigned char maintenance_type,
		unsigned char type)
{
	if (HOST_MAINTENANCE_STATUS_ON != maintenance_status)
		return FAIL;

	if (MAINTENANCE_TYPE_NODATA != maintenance_type)
		return FAIL;

	if (ITEM_TYPE_INTERNAL == type)
		return FAIL;

	return SUCCEED;
}

static void	DCget_host(DC_HOST *dst_host, const ZBX_DC_HOST *src_host)
{
	const ZBX_DC_IPMIHOST		*ipmihost;
	const ZBX_DC_HOST_INVENTORY	*host_inventory;

	dst_host->hostid = src_host->hostid;
	dst_host->proxy_hostid = src_host->proxy_hostid;
	strscpy(dst_host->host, src_host->host);
	strscpy(dst_host->name, src_host->name);
	dst_host->maintenance_status = src_host->maintenance_status;
	dst_host->maintenance_type = src_host->maintenance_type;
	dst_host->maintenance_from = src_host->maintenance_from;
	dst_host->errors_from = src_host->errors_from;
	dst_host->available = src_host->available;
	dst_host->disable_until = src_host->disable_until;
	dst_host->snmp_errors_from = src_host->snmp_errors_from;
	dst_host->snmp_available = src_host->snmp_available;
	dst_host->snmp_disable_until = src_host->snmp_disable_until;
	dst_host->ipmi_errors_from = src_host->ipmi_errors_from;
	dst_host->ipmi_available = src_host->ipmi_available;
	dst_host->ipmi_disable_until = src_host->ipmi_disable_until;
	dst_host->jmx_errors_from = src_host->jmx_errors_from;
	dst_host->jmx_available = src_host->jmx_available;
	dst_host->jmx_disable_until = src_host->jmx_disable_until;
	dst_host->status = src_host->status;
	strscpy(dst_host->error, src_host->error);
	strscpy(dst_host->snmp_error, src_host->snmp_error);
	strscpy(dst_host->ipmi_error, src_host->ipmi_error);
	strscpy(dst_host->jmx_error, src_host->jmx_error);
	dst_host->tls_connect = src_host->tls_connect;
	dst_host->tls_accept = src_host->tls_accept;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	strscpy(dst_host->tls_issuer, src_host->tls_issuer);
	strscpy(dst_host->tls_subject, src_host->tls_subject);

	if (NULL == src_host->tls_dc_psk)
	{
		*dst_host->tls_psk_identity = '\0';
		*dst_host->tls_psk = '\0';
	}
	else
	{
		strscpy(dst_host->tls_psk_identity, src_host->tls_dc_psk->tls_psk_identity);
		strscpy(dst_host->tls_psk, src_host->tls_dc_psk->tls_psk);
	}
#endif
	if (NULL != (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_search(&config->ipmihosts, &src_host->hostid)))
	{
		dst_host->ipmi_authtype = ipmihost->ipmi_authtype;
		dst_host->ipmi_privilege = ipmihost->ipmi_privilege;
		strscpy(dst_host->ipmi_username, ipmihost->ipmi_username);
		strscpy(dst_host->ipmi_password, ipmihost->ipmi_password);
	}
	else
	{
		dst_host->ipmi_authtype = ZBX_IPMI_DEFAULT_AUTHTYPE;
		dst_host->ipmi_privilege = ZBX_IPMI_DEFAULT_PRIVILEGE;
		*dst_host->ipmi_username = '\0';
		*dst_host->ipmi_password = '\0';
	}

	if (NULL != (host_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories, &src_host->hostid)))
		dst_host->inventory_mode = (char)host_inventory->inventory_mode;
	else
		dst_host->inventory_mode = HOST_INVENTORY_DISABLED;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_host_by_hostid                                             *
 *                                                                            *
 * Purpose: Locate host in configuration cache                                *
 *                                                                            *
 * Parameters: host - [OUT] pointer to DC_HOST structure                      *
 *             hostid - [IN] host ID from database                            *
 *                                                                            *
 * Return value: SUCCEED if record located and FAIL otherwise                 *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
int	DCget_host_by_hostid(DC_HOST *host, zbx_uint64_t hostid)
{
	int			ret = FAIL;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
	{
		DCget_host(host, dc_host);
		ret = SUCCEED;
	}

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DCcheck_proxy_permissions                                        *
 *                                                                            *
 * Purpose:                                                                   *
 *     Check access rights for an active proxy and get the proxy ID           *
 *                                                                            *
 * Parameters:                                                                *
 *     host   - [IN] proxy name                                               *
 *     sock   - [IN] connection socket context                                *
 *     hostid - [OUT] proxy ID found in configuration cache                   *
 *     error  - [OUT] error message why access was denied                     *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - access is allowed, FAIL - access denied                      *
 *                                                                            *
 * Comments:                                                                  *
 *     Generating of error messages is done outside of configuration cache    *
 *     locking.                                                               *
 *                                                                            *
 ******************************************************************************/
int	DCcheck_proxy_permissions(const char *host, const zbx_socket_t *sock, zbx_uint64_t *hostid, char **error)
{
	const ZBX_DC_HOST	*dc_host;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_conn_attr_t	attr;

	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_cert(sock, &attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_psk(sock, &attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
	else if (ZBX_TCP_SEC_UNENCRYPTED != sock->connection_type)
	{
		*error = zbx_strdup(*error, "internal error: invalid connection type");
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}
#endif
	LOCK_CACHE;

	if (NULL == (dc_host = DCfind_proxy(host)))
	{
		UNLOCK_CACHE;
		*error = zbx_dsprintf(*error, "proxy \"%s\" not found", host);
		return FAIL;
	}

	if (HOST_STATUS_PROXY_ACTIVE != dc_host->status)
	{
		UNLOCK_CACHE;
		*error = zbx_dsprintf(*error, "proxy \"%s\" is configured in passive mode", host);
		return FAIL;
	}

	if (0 == ((unsigned int)dc_host->tls_accept & sock->connection_type))
	{
		UNLOCK_CACHE;
		*error = zbx_dsprintf(NULL, "connection of type \"%s\" is not allowed for proxy \"%s\"",
				zbx_tcp_connection_type_name(sock->connection_type), host);
		return FAIL;
	}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		/* simplified match, not compliant with RFC 4517, 4518 */
		if ('\0' != *dc_host->tls_issuer && 0 != strcmp(dc_host->tls_issuer, attr.issuer))
		{
			UNLOCK_CACHE;
			*error = zbx_dsprintf(*error, "proxy \"%s\" certificate issuer does not match", host);
			return FAIL;
		}

		/* simplified match, not compliant with RFC 4517, 4518 */
		if ('\0' != *dc_host->tls_subject && 0 != strcmp(dc_host->tls_subject, attr.subject))
		{
			UNLOCK_CACHE;
			*error = zbx_dsprintf(*error, "proxy \"%s\" certificate subject does not match", host);
			return FAIL;
		}
	}
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (NULL != dc_host->tls_dc_psk)
		{
			if (strlen(dc_host->tls_dc_psk->tls_psk_identity) != attr.psk_identity_len ||
					0 != memcmp(dc_host->tls_dc_psk->tls_psk_identity, attr.psk_identity,
					attr.psk_identity_len))
			{
				UNLOCK_CACHE;
				*error = zbx_dsprintf(*error, "proxy \"%s\" is using false PSK identity", host);
				return FAIL;
			}
		}
		else
		{
			UNLOCK_CACHE;
			*error = zbx_dsprintf(*error, "active proxy \"%s\" is connecting with PSK but there is no PSK"
					" in the database for this proxy", host);
			return FAIL;
		}
	}
#endif
	*hostid = dc_host->hostid;

	UNLOCK_CACHE;

	return SUCCEED;
}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Function: DCget_psk_by_identity                                            *
 *                                                                            *
 * Purpose:                                                                   *
 *     Find PSK with the specified identity in configuration cache            *
 *                                                                            *
 * Parameters:                                                                *
 *     psk_identity - [IN] PSK identity to search for ('\0' terminated)       *
 *     psk_buf      - [OUT] output buffer for PSK value                       *
 *     psk_buf_len  - [IN] output buffer size                                 *
 *                                                                            *
 * Return value:                                                              *
 *     PSK length in bytes if PSK found. 0 - if PSK not found.                *
 *                                                                            *
 * Comments:                                                                  *
 *     ATTENTION! This function's address and arguments are described and     *
 *     used in file src/libs/zbxcrypto/tls.c for calling this function by     *
 *     pointer. If you ever change this DCget_psk_by_identity() function      *
 *     arguments or return value do not forget to synchronize changes with    *
 *     the src/libs/zbxcrypto/tls.c.                                          *
 *                                                                            *
 ******************************************************************************/
size_t	DCget_psk_by_identity(const unsigned char *psk_identity, unsigned char *psk_buf, size_t psk_buf_len)
{
	const ZBX_DC_PSK	*psk_i;
	ZBX_DC_PSK		psk_i_local;
	size_t			psk_len = 0;

	LOCK_CACHE;

	psk_i_local.tls_psk_identity = (const char *)psk_identity;

	if (NULL != (psk_i = (ZBX_DC_PSK *)zbx_hashset_search(&config->psks, &psk_i_local)))
		psk_len = zbx_strlcpy((char *)psk_buf, psk_i->tls_psk, psk_buf_len);

	UNLOCK_CACHE;

	return psk_len;
}
#endif

static void	DCget_interface(DC_INTERFACE *dst_interface, const ZBX_DC_INTERFACE *src_interface)
{
	if (NULL != src_interface)
	{
		dst_interface->interfaceid = src_interface->interfaceid;
		strscpy(dst_interface->ip_orig, src_interface->ip);
		strscpy(dst_interface->dns_orig, src_interface->dns);
		strscpy(dst_interface->port_orig, src_interface->port);
		dst_interface->useip = src_interface->useip;
		dst_interface->type = src_interface->type;
		dst_interface->main = src_interface->main;
	}
	else
	{
		dst_interface->interfaceid = 0;
		*dst_interface->ip_orig = '\0';
		*dst_interface->dns_orig = '\0';
		*dst_interface->port_orig = '\0';
		dst_interface->useip = 1;
		dst_interface->type = INTERFACE_TYPE_UNKNOWN;
		dst_interface->main = 0;
	}

	dst_interface->addr = (1 == dst_interface->useip ? dst_interface->ip_orig : dst_interface->dns_orig);
	dst_interface->port = 0;
}

static void	DCget_item(DC_ITEM *dst_item, const ZBX_DC_ITEM *src_item)
{
	const ZBX_DC_NUMITEM		*numitem;
	const ZBX_DC_LOGITEM		*logitem;
	const ZBX_DC_SNMPITEM		*snmpitem;
	const ZBX_DC_TRAPITEM		*trapitem;
	const ZBX_DC_IPMIITEM		*ipmiitem;
	const ZBX_DC_DBITEM		*dbitem;
	const ZBX_DC_SSHITEM		*sshitem;
	const ZBX_DC_TELNETITEM		*telnetitem;
	const ZBX_DC_SIMPLEITEM		*simpleitem;
	const ZBX_DC_JMXITEM		*jmxitem;
	const ZBX_DC_CALCITEM		*calcitem;
	const ZBX_DC_INTERFACE		*dc_interface;

	dst_item->itemid = src_item->itemid;
	dst_item->type = src_item->type;
	dst_item->value_type = src_item->value_type;
	strscpy(dst_item->key_orig, src_item->key);
	dst_item->key = NULL;
	dst_item->delay = zbx_strdup(NULL, src_item->delay);
	dst_item->nextcheck = src_item->nextcheck;
	dst_item->state = src_item->state;
	dst_item->lastclock = src_item->lastclock;
	dst_item->flags = src_item->flags;
	dst_item->lastlogsize = src_item->lastlogsize;
	dst_item->mtime = src_item->mtime;
	dst_item->history = src_item->history;
	dst_item->inventory_link = src_item->inventory_link;
	dst_item->valuemapid = src_item->valuemapid;
	dst_item->status = src_item->status;
	dst_item->history_sec = src_item->history_sec;

	dst_item->error = zbx_strdup(NULL, src_item->error);

	switch (src_item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			numitem = (ZBX_DC_NUMITEM *)zbx_hashset_search(&config->numitems, &src_item->itemid);

			dst_item->trends = numitem->trends;
			dst_item->units = zbx_strdup(NULL, numitem->units);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (NULL != (logitem = (ZBX_DC_LOGITEM *)zbx_hashset_search(&config->logitems, &src_item->itemid)))
				strscpy(dst_item->logtimefmt, logitem->logtimefmt);
			else
				*dst_item->logtimefmt = '\0';
			break;
	}

	switch (src_item->type)
	{
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			snmpitem = (ZBX_DC_SNMPITEM *)zbx_hashset_search(&config->snmpitems, &src_item->itemid);

			strscpy(dst_item->snmp_community_orig, snmpitem->snmp_community);
			strscpy(dst_item->snmp_oid_orig, snmpitem->snmp_oid);
			strscpy(dst_item->snmpv3_securityname_orig, snmpitem->snmpv3_securityname);
			dst_item->snmpv3_securitylevel = snmpitem->snmpv3_securitylevel;
			strscpy(dst_item->snmpv3_authpassphrase_orig, snmpitem->snmpv3_authpassphrase);
			strscpy(dst_item->snmpv3_privpassphrase_orig, snmpitem->snmpv3_privpassphrase);
			dst_item->snmpv3_authprotocol = snmpitem->snmpv3_authprotocol;
			dst_item->snmpv3_privprotocol = snmpitem->snmpv3_privprotocol;
			strscpy(dst_item->snmpv3_contextname_orig, snmpitem->snmpv3_contextname);

			dst_item->snmp_community = NULL;
			dst_item->snmp_oid = NULL;
			dst_item->snmpv3_securityname = NULL;
			dst_item->snmpv3_authpassphrase = NULL;
			dst_item->snmpv3_privpassphrase = NULL;
			dst_item->snmpv3_contextname = NULL;
			break;
		case ITEM_TYPE_TRAPPER:
			if (NULL != (trapitem = (ZBX_DC_TRAPITEM *)zbx_hashset_search(&config->trapitems, &src_item->itemid)))
				strscpy(dst_item->trapper_hosts, trapitem->trapper_hosts);
			else
				*dst_item->trapper_hosts = '\0';
			break;
		case ITEM_TYPE_IPMI:
			if (NULL != (ipmiitem = (ZBX_DC_IPMIITEM *)zbx_hashset_search(&config->ipmiitems, &src_item->itemid)))
				strscpy(dst_item->ipmi_sensor, ipmiitem->ipmi_sensor);
			else
				*dst_item->ipmi_sensor = '\0';
			break;
		case ITEM_TYPE_DB_MONITOR:
			if (NULL != (dbitem = (ZBX_DC_DBITEM *)zbx_hashset_search(&config->dbitems, &src_item->itemid)))
			{
				dst_item->params = zbx_strdup(NULL, dbitem->params);
				strscpy(dst_item->username_orig, dbitem->username);
				strscpy(dst_item->password_orig, dbitem->password);
			}
			else
			{
				dst_item->params = zbx_strdup(NULL, "");
				*dst_item->username_orig = '\0';
				*dst_item->password_orig = '\0';
			}
			dst_item->username = NULL;
			dst_item->password = NULL;

			break;
		case ITEM_TYPE_SSH:
			if (NULL != (sshitem = (ZBX_DC_SSHITEM *)zbx_hashset_search(&config->sshitems, &src_item->itemid)))
			{
				dst_item->authtype = sshitem->authtype;
				strscpy(dst_item->username_orig, sshitem->username);
				strscpy(dst_item->publickey_orig, sshitem->publickey);
				strscpy(dst_item->privatekey_orig, sshitem->privatekey);
				strscpy(dst_item->password_orig, sshitem->password);
				dst_item->params = zbx_strdup(NULL, sshitem->params);
			}
			else
			{
				dst_item->authtype = 0;
				*dst_item->username_orig = '\0';
				*dst_item->publickey_orig = '\0';
				*dst_item->privatekey_orig = '\0';
				*dst_item->password_orig = '\0';
				dst_item->params = zbx_strdup(NULL, "");
			}
			dst_item->username = NULL;
			dst_item->publickey = NULL;
			dst_item->privatekey = NULL;
			dst_item->password = NULL;
			break;
		case ITEM_TYPE_TELNET:
			if (NULL != (telnetitem = (ZBX_DC_TELNETITEM *)zbx_hashset_search(&config->telnetitems, &src_item->itemid)))
			{
				strscpy(dst_item->username_orig, telnetitem->username);
				strscpy(dst_item->password_orig, telnetitem->password);
				dst_item->params = zbx_strdup(NULL, telnetitem->params);
			}
			else
			{
				*dst_item->username_orig = '\0';
				*dst_item->password_orig = '\0';
				dst_item->params = zbx_strdup(NULL, "");
			}
			dst_item->username = NULL;
			dst_item->password = NULL;
			break;
		case ITEM_TYPE_SIMPLE:
			if (NULL != (simpleitem = (ZBX_DC_SIMPLEITEM *)zbx_hashset_search(&config->simpleitems, &src_item->itemid)))
			{
				strscpy(dst_item->username_orig, simpleitem->username);
				strscpy(dst_item->password_orig, simpleitem->password);
			}
			else
			{
				*dst_item->username_orig = '\0';
				*dst_item->password_orig = '\0';
			}
			dst_item->username = NULL;
			dst_item->password = NULL;
			break;
		case ITEM_TYPE_JMX:
			if (NULL != (jmxitem = (ZBX_DC_JMXITEM *)zbx_hashset_search(&config->jmxitems, &src_item->itemid)))
			{
				strscpy(dst_item->username_orig, jmxitem->username);
				strscpy(dst_item->password_orig, jmxitem->password);
				strscpy(dst_item->jmx_endpoint_orig, jmxitem->jmx_endpoint);
			}
			else
			{
				*dst_item->username_orig = '\0';
				*dst_item->password_orig = '\0';
				*dst_item->jmx_endpoint_orig = '\0';
			}
			dst_item->username = NULL;
			dst_item->password = NULL;
			dst_item->jmx_endpoint = NULL;
			break;
		case ITEM_TYPE_CALCULATED:
			calcitem = (ZBX_DC_CALCITEM *)zbx_hashset_search(&config->calcitems, &src_item->itemid);
			dst_item->params = zbx_strdup(NULL, NULL != calcitem ? calcitem->params : "");
			break;
		default:
			/* nothing to do */;
	}

	dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &src_item->interfaceid);

	DCget_interface(&dst_item->interface, dc_interface);

	if ('\0' != *src_item->port)
	{
		switch (src_item->type)
		{
			case ITEM_TYPE_SNMPv1:
			case ITEM_TYPE_SNMPv2c:
			case ITEM_TYPE_SNMPv3:
				strscpy(dst_item->interface.port_orig, src_item->port);
				break;
			default:
				/* nothing to do */;
		}
	}
}

void	DCconfig_clean_items(DC_ITEM *items, int *errcodes, size_t num)
{
	size_t	i;

	for (i = 0; i < num; i++)
	{
		if (NULL != errcodes && SUCCEED != errcodes[i])
			continue;

		if (ITEM_VALUE_TYPE_FLOAT == items[i].value_type || ITEM_VALUE_TYPE_UINT64 == items[i].value_type)
		{
			zbx_free(items[i].units);
		}

		switch (items[i].type)
		{
			case ITEM_TYPE_DB_MONITOR:
			case ITEM_TYPE_SSH:
			case ITEM_TYPE_TELNET:
			case ITEM_TYPE_CALCULATED:
				zbx_free(items[i].params);
				break;
		}

		zbx_free(items[i].delay);
		zbx_free(items[i].error);
	}
}

static void	DCget_function(DC_FUNCTION *dst_function, const ZBX_DC_FUNCTION *src_function)
{
	size_t	sz_function, sz_parameter;

	dst_function->functionid = src_function->functionid;
	dst_function->triggerid = src_function->triggerid;
	dst_function->itemid = src_function->itemid;

	sz_function = strlen(src_function->function) + 1;
	sz_parameter = strlen(src_function->parameter) + 1;
	dst_function->function = (char *)zbx_malloc(NULL, sz_function + sz_parameter);
	dst_function->parameter = dst_function->function + sz_function;
	memcpy(dst_function->function, src_function->function, sz_function);
	memcpy(dst_function->parameter, src_function->parameter, sz_parameter);
}

static void	DCget_trigger(DC_TRIGGER *dst_trigger, ZBX_DC_TRIGGER *src_trigger)
{
	int	i;

	dst_trigger->triggerid = src_trigger->triggerid;
	dst_trigger->description = zbx_strdup(NULL, src_trigger->description);
	dst_trigger->expression_orig = zbx_strdup(NULL, src_trigger->expression);
	dst_trigger->recovery_expression_orig = zbx_strdup(NULL, src_trigger->recovery_expression);
	dst_trigger->error = zbx_strdup(NULL, src_trigger->error);
	dst_trigger->timespec.sec = 0;
	dst_trigger->timespec.ns = 0;
	dst_trigger->priority = src_trigger->priority;
	dst_trigger->type = src_trigger->type;
	dst_trigger->value = src_trigger->value;
	dst_trigger->state = src_trigger->state;
	dst_trigger->new_value = TRIGGER_VALUE_UNKNOWN;
	dst_trigger->lastchange = src_trigger->lastchange;
	dst_trigger->topoindex = src_trigger->topoindex;
	dst_trigger->status = src_trigger->status;
	dst_trigger->recovery_mode = src_trigger->recovery_mode;
	dst_trigger->correlation_mode = src_trigger->correlation_mode;
	dst_trigger->correlation_tag = zbx_strdup(NULL, src_trigger->correlation_tag);
	dst_trigger->flags = 0;

	dst_trigger->expression = NULL;
	dst_trigger->recovery_expression = NULL;
	dst_trigger->new_error = NULL;

	dst_trigger->expression = zbx_strdup(NULL, src_trigger->expression);
	dst_trigger->recovery_expression = zbx_strdup(NULL, src_trigger->recovery_expression);

	zbx_vector_ptr_create(&dst_trigger->tags);

	if (0 != src_trigger->tags.values_num)
	{
		zbx_vector_ptr_reserve(&dst_trigger->tags, src_trigger->tags.values_num);

		for (i = 0; i < src_trigger->tags.values_num; i++)
		{
			zbx_dc_trigger_tag_t	*dc_trigger_tag = (zbx_dc_trigger_tag_t *)src_trigger->tags.values[i];
			zbx_tag_t		*tag;

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, dc_trigger_tag->tag);
			tag->value = zbx_strdup(NULL, dc_trigger_tag->value);

			zbx_vector_ptr_append(&dst_trigger->tags, tag);
		}
	}
}

void	zbx_free_tag(zbx_tag_t *tag)
{
	zbx_free(tag->tag);
	zbx_free(tag->value);
	zbx_free(tag);
}

static void	DCclean_trigger(DC_TRIGGER *trigger)
{
	zbx_free(trigger->new_error);
	zbx_free(trigger->error);
	zbx_free(trigger->expression_orig);
	zbx_free(trigger->recovery_expression_orig);
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
	zbx_free(trigger->description);
	zbx_free(trigger->correlation_tag);

	zbx_vector_ptr_clear_ext(&trigger->tags, (zbx_clean_func_t)zbx_free_tag);
	zbx_vector_ptr_destroy(&trigger->tags);
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_items_by_keys                                       *
 *                                                                            *
 * Purpose: locate item in configuration cache by host and key                *
 *                                                                            *
 * Parameters: items    - [OUT] pointer to array of DC_ITEM structures        *
 *             keys     - [IN] list of item keys with host names              *
 *             errcodes - [OUT] SUCCEED if record located and FAIL otherwise  *
 *             num      - [IN] number of elements in items, keys, errcodes    *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_get_items_by_keys(DC_ITEM *items, zbx_host_key_t *keys, int *errcodes, size_t num)
{
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_host = DCfind_host(keys[i].host)) ||
				NULL == (dc_item = DCfind_item(dc_host->hostid, keys[i].key)))
		{
			errcodes[i] = FAIL;
			continue;
		}

		DCget_host(&items[i].host, dc_host);
		DCget_item(&items[i], dc_item);
		errcodes[i] = SUCCEED;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_items_by_itemids                                    *
 *                                                                            *
 * Purpose: Get item with specified ID                                        *
 *                                                                            *
 * Parameters: items    - [OUT] pointer to DC_ITEM structures                 *
 *             itemids  - [IN] array of item IDs                              *
 *             errcodes - [OUT] SUCCEED if item found, otherwise FAIL         *
 *             num      - [IN] number of elements                             *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_get_items_by_itemids(DC_ITEM *items, const zbx_uint64_t *itemids, int *errcodes, size_t num)
{
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids[i])) ||
				NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		{
			errcodes[i] = FAIL;
			continue;
		}

		DCget_host(&items[i].host, dc_host);
		DCget_item(&items[i], dc_item);
		errcodes[i] = SUCCEED;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_preproc_item_init                                             *
 *                                                                            *
 * Purpose: initialize new preprocessor item from configuration cache         *
 *                                                                            *
 * Parameters: item   - [OUT] the item to initialize                          *
 *             itemid - [IN] the item identifier                              *
 *                                                                            *
 * Return value: SUCCEED - the item was initialized successfully              *
 *               FAIL    - item with the specified itemid is not cached or    *
 *                         monitored                                          *
 *                                                                            *
 ******************************************************************************/
static int	dc_preproc_item_init(zbx_preproc_item_t *item, zbx_uint64_t itemid)
{
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	if (NULL == (dc_item = (const ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
		return FAIL;

	if (ITEM_STATUS_ACTIVE != dc_item->status)
		return FAIL;

	if (NULL == (dc_host = (const ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		return FAIL;

	if (HOST_STATUS_MONITORED != dc_host->status)
		return FAIL;

	item->itemid = itemid;
	item->type = dc_item->type;
	item->value_type = dc_item->value_type;

	item->dep_itemids = NULL;
	item->dep_itemids_num = 0;

	item->preproc_ops = NULL;
	item->preproc_ops_num = 0;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_preprocessable_items                                *
 *                                                                            *
 * Purpose: get preprocessable items:                                         *
 *              * items with preprocessing steps                              *
 *              * items with dependent items                                  *
 *              * internal items                                              *
 *                                                                            *
 * Parameters: items       - [IN/OUT] hashset with DC_ITEMs                   *
 *             timestamp   - [IN/OUT] timestamp of a last update              *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_get_preprocessable_items(zbx_hashset_t *items, int *timestamp)
{
	const char		*__function_name = "DCconfig_get_preprocessable_items";

	const ZBX_DC_PREPROCITEM	*dc_preprocitem;
	const ZBX_DC_MASTERITEM		*dc_masteritem;
	const ZBX_DC_ITEM		*dc_item;
	const zbx_dc_preproc_op_t	*dc_op;
	zbx_preproc_item_t		*item, item_local;
	zbx_hashset_iter_t		iter;
	zbx_preproc_op_t		*op;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* no changes */
	if (0 != *timestamp && *timestamp == config->item_sync_ts)
		goto out;

	zbx_hashset_clear(items);
	*timestamp = config->item_sync_ts;

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->preprocitems, &iter);
	while (NULL != (dc_preprocitem = (const ZBX_DC_PREPROCITEM *)zbx_hashset_iter_next(&iter)))
	{
		if (FAIL == dc_preproc_item_init(&item_local, dc_preprocitem->itemid))
			continue;

		item = (zbx_preproc_item_t *)zbx_hashset_insert(items, &item_local, sizeof(item_local));

		item->preproc_ops_num = dc_preprocitem->preproc_ops.values_num;
		item->preproc_ops = (zbx_preproc_op_t *)zbx_malloc(NULL, sizeof(zbx_preproc_op_t) * item->preproc_ops_num);

		for (i = 0; i < dc_preprocitem->preproc_ops.values_num; i++)
		{
			dc_op = (const zbx_dc_preproc_op_t *)dc_preprocitem->preproc_ops.values[i];
			op = &item->preproc_ops[i];
			op->type = dc_op->type;
			op->params = zbx_strdup(NULL, dc_op->params);
		}
	}

	zbx_hashset_iter_reset(&config->masteritems, &iter);
	while (NULL != (dc_masteritem = (const ZBX_DC_MASTERITEM *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == (item = (zbx_preproc_item_t *)zbx_hashset_search(items, &dc_masteritem->itemid)))
		{
			if (FAIL == dc_preproc_item_init(&item_local, dc_masteritem->itemid))
				continue;

			item = (zbx_preproc_item_t *)zbx_hashset_insert(items, &item_local, sizeof(item_local));
		}

		item->dep_itemids_num = dc_masteritem->dep_itemids.values_num;
		item->dep_itemids = (zbx_uint64_t *)zbx_malloc(NULL, sizeof(zbx_uint64_t) * item->dep_itemids_num);
		memcpy(item->dep_itemids, dc_masteritem->dep_itemids.values,
				sizeof(zbx_uint64_t) * item->dep_itemids_num);
	}

	zbx_hashset_iter_reset(&config->items, &iter);
	while (NULL != (dc_item = (const ZBX_DC_ITEM *)zbx_hashset_iter_next(&iter)))
	{
		if (ITEM_TYPE_INTERNAL != dc_item->type)
			continue;

		if (NULL == (item = (zbx_preproc_item_t *)zbx_hashset_search(items, &dc_item->itemid)))
		{
			if (FAIL == dc_preproc_item_init(&item_local, dc_item->itemid))
				continue;

			zbx_hashset_insert(items, &item_local, sizeof(item_local));
		}
	}

	UNLOCK_CACHE;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() items:%d", __function_name, items->num_data);
}

void	DCconfig_get_hosts_by_itemids(DC_HOST *hosts, const zbx_uint64_t *itemids, int *errcodes, size_t num)
{
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids[i])) ||
				NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		{
			errcodes[i] = FAIL;
			continue;
		}

		DCget_host(&hosts[i], dc_host);
		errcodes[i] = SUCCEED;
	}

	UNLOCK_CACHE;
}

void	DCconfig_get_triggers_by_triggerids(DC_TRIGGER *triggers, const zbx_uint64_t *triggerids, int *errcode,
		size_t num)
{
	size_t		i;
	ZBX_DC_TRIGGER	*dc_trigger;

	LOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &triggerids[i])))
		{
			errcode[i] = FAIL;
			continue;
		}

		DCget_trigger(&triggers[i], dc_trigger);
		errcode[i] = SUCCEED;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_functions_by_functionids                            *
 *                                                                            *
 * Purpose: Get functions by IDs                                              *
 *                                                                            *
 * Parameters: functions   - [OUT] pointer to DC_FUNCTION structures          *
 *             functionids - [IN] array of function IDs                       *
 *             errcodes    - [OUT] SUCCEED if item found, otherwise FAIL      *
 *             num         - [IN] number of elements                          *
 *                                                                            *
 * Author: Aleksandrs Saveljevs, Alexander Vladishev                          *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_get_functions_by_functionids(DC_FUNCTION *functions, zbx_uint64_t *functionids, int *errcodes,
		size_t num)
{
	size_t			i;
	const ZBX_DC_FUNCTION	*dc_function;

	LOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions, &functionids[i])))
		{
			errcodes[i] = FAIL;
			continue;
		}

		DCget_function(&functions[i], dc_function);
		errcodes[i] = SUCCEED;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_clean_functions                                         *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_clean_functions(DC_FUNCTION *functions, int *errcodes, size_t num)
{
	size_t	i;

	for (i = 0; i < num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		zbx_free(functions[i].function);
	}
}

void	DCconfig_clean_triggers(DC_TRIGGER *triggers, int *errcodes, size_t num)
{
	size_t	i;

	for (i = 0; i < num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		DCclean_trigger(&triggers[i]);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_lock_triggers_by_history_items                          *
 *                                                                            *
 * Purpose: Lock triggers for specified items so that multiple processes do   *
 *          not process one trigger simultaneously. Otherwise, this leads to  *
 *          problems like multiple successive OK events or escalations being  *
 *          started and not cancelled, because they are not seen in parallel  *
 *          transactions.                                                     *
 *                                                                            *
 * Parameters: history_items - [IN/OUT] list of history items history syncer  *
 *                                    wishes to take for processing; on       *
 *                                    output, the item locked field is set    *
 *                                    to 0 if the corresponding item cannot   *
 *                                    be taken                                *
 *             triggerids  - [OUT] list of trigger IDs that this function has *
 *                                 locked for processing; unlock those using  *
 *                                 DCconfig_unlock_triggers() function        *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: This does not solve the problem fully (e.g., ZBX-7484). There is *
 *           a significant time period between the place where we lock the    *
 *           triggers and the place where we process them. So it could happen *
 *           that a configuration cache update happens after we have locked   *
 *           the triggers and it turns out that in the updated configuration  *
 *           there is a new trigger for two of the items that two different   *
 *           history syncers have taken for processing. In that situation,    *
 *           the problem we are solving here might still happen. However,     *
 *           locking triggers makes this problem much less likely and only in *
 *           case configuration changes. On a stable configuration, it should *
 *           work without any problems.                                       *
 *                                                                            *
 *           Also see function DCconfig_get_time_based_triggers(), which      *
 *           timer processes use to lock and unlock triggers.                 *
 *                                                                            *
 * Return value: the number of items available for processing (unlocked).     *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_lock_triggers_by_history_items(zbx_vector_ptr_t *history_items, zbx_vector_uint64_t *triggerids)
{
	int			i, j, locked_num = 0;
	const ZBX_DC_ITEM	*dc_item;
	ZBX_DC_TRIGGER		*dc_trigger;
	zbx_hc_item_t		*history_item;

	LOCK_CACHE;

	for (i = 0; i < history_items->values_num; i++)
	{
		history_item = (zbx_hc_item_t *)history_items->values[i];

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &history_item->itemid)))
			continue;

		if (NULL == dc_item->triggers)
			continue;

		for (j = 0; NULL != (dc_trigger = dc_item->triggers[j]); j++)
		{
			if (TRIGGER_STATUS_ENABLED != dc_trigger->status)
				continue;

			if (1 == dc_trigger->locked)
			{
				locked_num++;
				history_item->status = ZBX_HC_ITEM_STATUS_BUSY;
				goto next;
			}
		}

		for (j = 0; NULL != (dc_trigger = dc_item->triggers[j]); j++)
		{
			if (TRIGGER_STATUS_ENABLED != dc_trigger->status)
				continue;

			dc_trigger->locked = 1;
			zbx_vector_uint64_append(triggerids, dc_trigger->triggerid);
		}
next:;
	}

	UNLOCK_CACHE;

	return history_items->values_num - locked_num;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_lock_triggers_by_triggerids                             *
 *                                                                            *
 * Purpose: Lock triggers so that multiple processes do not process one       *
 *          trigger simultaneously.                                           *
 *                                                                            *
 * Parameters: triggerids_in  - [IN] ids of triggers to lock                  *
 *             triggerids_out - [OUT] ids of locked triggers                  *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_lock_triggers_by_triggerids(zbx_vector_uint64_t *triggerids_in, zbx_vector_uint64_t *triggerids_out)
{
	int		i;
	ZBX_DC_TRIGGER	*dc_trigger;

	if (0 == triggerids_in->values_num)
		return;

	LOCK_CACHE;

	for (i = 0; i < triggerids_in->values_num; i++)
	{
		if (NULL == (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &triggerids_in->values[i])))
			continue;

		if (1 == dc_trigger->locked)
			continue;

		dc_trigger->locked = 1;
		zbx_vector_uint64_append(triggerids_out, dc_trigger->triggerid);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_unlock_triggers                                         *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_unlock_triggers(const zbx_vector_uint64_t *triggerids)
{
	int		i;
	ZBX_DC_TRIGGER	*dc_trigger;

	LOCK_CACHE;

	for (i = 0; i < triggerids->values_num; i++)
	{
		if (NULL == (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &triggerids->values[i])))
			continue;

		dc_trigger->locked = 0;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_unlock_all_triggers                                     *
 *                                                                            *
 * Purpose: Unlocks all locked triggers before doing full history sync at     *
 *          program exit                                                      *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_unlock_all_triggers(void)
{
	ZBX_DC_TRIGGER		*dc_trigger;
	zbx_hashset_iter_t	iter;

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->triggers, &iter);

	while (NULL != (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
		dc_trigger->locked = 0;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_lock_lld_rule                                           *
 *                                                                            *
 * Purpose: Lock lld rule to avoid parallel processing of a same lld rule     *
 *          that was causing deadlocks.                                       *
 *                                                                            *
 * Parameters: lld_ruleid - [IN] discovery rule id                            *
 *                                                                            *
 * Return value: Returns FAIL if lock failed and SUCCEED on successful lock.  *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_lock_lld_rule(zbx_uint64_t lld_ruleid)
{
	int	ret = FAIL;

	LOCK_CACHE;

	if (FAIL == zbx_vector_uint64_search(&config->locked_lld_ruleids, lld_ruleid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
	{
		zbx_vector_uint64_append(&config->locked_lld_ruleids, lld_ruleid);
		ret = SUCCEED;
	}

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_unlock_lld_rule                                         *
 *                                                                            *
 * Purpose: Unlock (make it available for processing) lld rule.               *
 *                                                                            *
 * Parameters: lld_ruleid - [IN] discovery rule id                            *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_unlock_lld_rule(zbx_uint64_t lld_ruleid)
{
	int	i;

	LOCK_CACHE;

	if (FAIL != (i = zbx_vector_uint64_search(&config->locked_lld_ruleids, lld_ruleid,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		zbx_vector_uint64_remove_noorder(&config->locked_lld_ruleids, i);
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;	/* attempt to unlock lld rule that is not locked */

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_triggers_by_itemids                                 *
 *                                                                            *
 * Purpose: get enabled triggers for specified items                          *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_get_triggers_by_itemids(zbx_hashset_t *trigger_info, zbx_vector_ptr_t *trigger_order,
		const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs, int itemids_num)
{
	int			i, j, found;
	const ZBX_DC_ITEM	*dc_item;
	ZBX_DC_TRIGGER		*dc_trigger;
	DC_TRIGGER		*trigger;

	LOCK_CACHE;

	for (i = 0; i < itemids_num; i++)
	{
		/* skip items which are not in configuration cache and items without triggers */

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids[i])) || NULL == dc_item->triggers)
			continue;

		/* process all triggers for the specified item */

		for (j = 0; NULL != (dc_trigger = dc_item->triggers[j]); j++)
		{
			if (TRIGGER_STATUS_ENABLED != dc_trigger->status)
				continue;

			/* find trigger by id or create a new record in hashset if not found */
			trigger = (DC_TRIGGER *)DCfind_id(trigger_info, dc_trigger->triggerid, sizeof(DC_TRIGGER), &found);

			if (0 == found)
			{
				DCget_trigger(trigger, dc_trigger);
				zbx_vector_ptr_append(trigger_order, trigger);
			}

			/* copy latest change timestamp and error message */

			if (trigger->timespec.sec < timespecs[i].sec ||
					(trigger->timespec.sec == timespecs[i].sec &&
					trigger->timespec.ns < timespecs[i].ns))
			{
				trigger->timespec = timespecs[i];
			}
		}
	}

	UNLOCK_CACHE;

	zbx_vector_ptr_sort(trigger_order, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Comments: helper function for DCconfig_get_time_based_triggers()           *
 *                                                                            *
 ******************************************************************************/
static int	DCconfig_find_active_time_function(const char *expression)
{
	zbx_uint64_t		functionid;
	const ZBX_DC_FUNCTION	*dc_function;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	while (SUCCEED == get_N_functionid(expression, 1, &functionid, &expression))
	{
		if (NULL == (dc_function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions, &functionid)))
			continue;

		if (SUCCEED != is_time_function(dc_function->function))
			continue;

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &dc_function->itemid)))
			continue;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
			continue;

		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_time_based_triggers                                 *
 *                                                                            *
 * Purpose: get triggers that have time-based functions (sorted by triggerid) *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: A trigger should have at least one function that is time-based   *
 *           and which does not have its host in no-data maintenance.         *
 *                                                                            *
 *           This function is meant to be called multiple times, each time    *
 *           yielding up to max_triggers in return. When called the function  *
 *           starts where it left off to yield the next bunch of triggers.    *
 *                                                                            *
 *           Also see function DCconfig_lock_triggers_by_history_items(),     *
 *           which history syncer processes use to lock triggers.             *
 *                                                                            *
 *           Note that the caller must unlock all returned triggers by        *
 *           DCconfig_unlock_triggers() function                              *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_time_based_triggers(DC_TRIGGER *trigger_info, zbx_vector_ptr_t *trigger_order, int max_triggers,
		zbx_uint64_t start_triggerid, int process_num)
{
	int			i, start;
	unsigned char		flags;
	ZBX_DC_TRIGGER		*dc_trigger;
	DC_TRIGGER		*trigger;

	LOCK_CACHE;

	start = zbx_vector_ptr_nearestindex(&config->time_triggers[process_num - 1], &start_triggerid,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = start; i < config->time_triggers[process_num - 1].values_num; i++)
	{
		flags = 0;

		dc_trigger = (ZBX_DC_TRIGGER *)config->time_triggers[process_num - 1].values[i];

		if (TRIGGER_STATUS_DISABLED == dc_trigger->status || 1 == dc_trigger->locked)
			continue;

		if (SUCCEED != DCconfig_find_active_time_function(dc_trigger->expression))
		{
			/* We trigger the evaluation of the recovery expression only if the trigger have a value of */
			/* TRIGGER_VALUE_PROBLEM and if the recovery mode use the recovery expression */
			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION != dc_trigger->recovery_mode ||
					TRIGGER_VALUE_PROBLEM != dc_trigger->value ||
					SUCCEED != DCconfig_find_active_time_function(dc_trigger->recovery_expression))
			{
				continue;
			}
		}
		else
		{
			/* Remember that trigger is chosen for evaluation because of time-based function in problem */
			/* expression. This information is later used in evaluate_expressions() to avoid generation */
			/* of duplicate PROBLEM events if recovery expression remains to be false. */
			flags |= ZBX_DC_TRIGGER_PROBLEM_EXPRESSION;
		}

		dc_trigger->locked = 1;
		trigger = &trigger_info[trigger_order->values_num];

		DCget_trigger(trigger, dc_trigger);
		zbx_timespec(&trigger->timespec);
		trigger->flags = flags;

		zbx_vector_ptr_append(trigger_order, trigger);

		if (trigger_order->values_num == max_triggers)
			break;
	}

	UNLOCK_CACHE;

	return trigger_order->values_num;
}

void	DCfree_triggers(zbx_vector_ptr_t *triggers)
{
	int	i;

	for (i = 0; i < triggers->values_num; i++)
		DCclean_trigger((DC_TRIGGER *)triggers->values[i]);

	zbx_vector_ptr_clear(triggers);
}

void	DCconfig_update_interface_snmp_stats(zbx_uint64_t interfaceid, int max_snmp_succeed, int min_snmp_fail)
{
	ZBX_DC_INTERFACE	*dc_interface;

	LOCK_CACHE;

	if (NULL != (dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid)) &&
			SNMP_BULK_ENABLED == dc_interface->bulk)
	{
		if (dc_interface->max_snmp_succeed < max_snmp_succeed)
			dc_interface->max_snmp_succeed = (unsigned char)max_snmp_succeed;

		if (dc_interface->min_snmp_fail > min_snmp_fail)
			dc_interface->min_snmp_fail = (unsigned char)min_snmp_fail;
	}

	UNLOCK_CACHE;
}

static int	DCconfig_get_suggested_snmp_vars_nolock(zbx_uint64_t interfaceid, int *bulk)
{
	int			num;
	ZBX_DC_INTERFACE	*dc_interface;

	dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid);

	if (NULL != bulk)
		*bulk = (NULL == dc_interface ? SNMP_BULK_DISABLED : dc_interface->bulk);

	if (NULL == dc_interface || SNMP_BULK_ENABLED != dc_interface->bulk)
		return 1;

	/* The general strategy is to multiply request size by 3/2 in order to approach the limit faster. */
	/* However, once we are over the limit, we change the strategy to increasing the value by 1. This */
	/* is deemed better than going backwards from the error because less timeouts are going to occur. */

	if (1 >= dc_interface->max_snmp_succeed || MAX_SNMP_ITEMS + 1 != dc_interface->min_snmp_fail)
		num = dc_interface->max_snmp_succeed + 1;
	else
		num = dc_interface->max_snmp_succeed * 3 / 2;

	if (num < dc_interface->min_snmp_fail)
		return num;

	/* If we have already found the optimal number of variables to query, we wish to base our suggestion on that */
	/* number. If we occasionally get a timeout in this area, it can mean two things: either the device's actual */
	/* limit is a bit lower than that (it can process requests above it, but only sometimes) or a UDP packet in  */
	/* one of the directions was lost. In order to account for the former, we allow ourselves to lower the count */
	/* of variables, but only up to two times. Otherwise, performance will gradually degrade due to the latter.  */

	return MAX(dc_interface->max_snmp_succeed - 2, dc_interface->min_snmp_fail - 1);
}

int	DCconfig_get_suggested_snmp_vars(zbx_uint64_t interfaceid, int *bulk)
{
	int	ret;

	LOCK_CACHE;

	ret = DCconfig_get_suggested_snmp_vars_nolock(interfaceid, bulk);

	UNLOCK_CACHE;

	return ret;
}

static int	dc_get_interface_by_type(DC_INTERFACE *interface, zbx_uint64_t hostid, unsigned char type)
{
	int			res = FAIL;
	const ZBX_DC_INTERFACE	*dc_interface;
	ZBX_DC_INTERFACE_HT	*interface_ht, interface_ht_local;

	interface_ht_local.hostid = hostid;
	interface_ht_local.type = type;

	if (NULL != (interface_ht = (ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht, &interface_ht_local)))
	{
		dc_interface = interface_ht->interface_ptr;
		DCget_interface(interface, dc_interface);
		res = SUCCEED;
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_interface_by_type                                   *
 *                                                                            *
 * Purpose: Locate main interface of specified type in configuration cache    *
 *                                                                            *
 * Parameters: interface - [OUT] pointer to DC_INTERFACE structure            *
 *             hostid - [IN] host ID                                          *
 *             type - [IN] interface type                                     *
 *                                                                            *
 * Return value: SUCCEED if record located and FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_interface_by_type(DC_INTERFACE *interface, zbx_uint64_t hostid, unsigned char type)
{
	int	res;

	LOCK_CACHE;

	res = dc_get_interface_by_type(interface, hostid, type);

	UNLOCK_CACHE;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_interface                                           *
 *                                                                            *
 * Purpose: Locate interface in configuration cache                           *
 *                                                                            *
 * Parameters: interface - [OUT] pointer to DC_INTERFACE structure            *
 *             hostid - [IN] host ID                                          *
 *             itemid - [IN] item ID                                          *
 *                                                                            *
 * Return value: SUCCEED if record located and FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_interface(DC_INTERFACE *interface, zbx_uint64_t hostid, zbx_uint64_t itemid)
{
	int			res = FAIL, i;
	ZBX_DC_ITEM		*dc_item;
	const ZBX_DC_INTERFACE	*dc_interface;

	LOCK_CACHE;

	if (0 != itemid)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
			goto unlock;

		if (0 != dc_item->interfaceid)
		{
			if (NULL == (dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &dc_item->interfaceid)))
				goto unlock;

			DCget_interface(interface, dc_interface);
			res = SUCCEED;
			goto unlock;
		}

		hostid = dc_item->hostid;
	}

	if (0 == hostid)
		goto unlock;

	for (i = 0; i < (int)ARRSIZE(INTERFACE_TYPE_PRIORITY); i++)
	{
		if (SUCCEED == (res = dc_get_interface_by_type(interface, hostid, INTERFACE_TYPE_PRIORITY[i])))
			break;
	}

unlock:
	UNLOCK_CACHE;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_config_get_queue_nextcheck                                    *
 *                                                                            *
 * Purpose: Get nextcheck for selected queue                                  *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *                                                                            *
 * Return value: nextcheck or FAIL if no items for the specified queue        *
 *                                                                            *
 ******************************************************************************/
static int	dc_config_get_queue_nextcheck(zbx_binary_heap_t *queue)
{
	int				nextcheck;
	const zbx_binary_heap_elem_t	*min;
	const ZBX_DC_ITEM		*dc_item;

	if (FAIL == zbx_binary_heap_empty(queue))
	{
		min = zbx_binary_heap_find_min(queue);
		dc_item = (const ZBX_DC_ITEM *)min->data;

		nextcheck = dc_item->nextcheck;
	}
	else
		nextcheck = FAIL;

	return nextcheck;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_poller_nextcheck                                    *
 *                                                                            *
 * Purpose: Get nextcheck for selected poller                                 *
 *                                                                            *
 * Parameters: poller_type - [IN] poller type (ZBX_POLLER_TYPE_...)           *
 *                                                                            *
 * Return value: nextcheck or FAIL if no items for selected poller            *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_poller_nextcheck(unsigned char poller_type)
{
	const char		*__function_name = "DCconfig_get_poller_nextcheck";

	int			nextcheck;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() poller_type:%d", __function_name, (int)poller_type);

	queue = &config->queues[poller_type];

	LOCK_CACHE;

	nextcheck = dc_config_get_queue_nextcheck(queue);

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, nextcheck);

	return nextcheck;
}

static void	dc_requeue_item(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host, unsigned char new_state, int flags,
		int lastclock)
{
	unsigned char	old_poller_type;
	int		old_nextcheck;

	old_nextcheck = dc_item->nextcheck;
	DCitem_nextcheck_update(dc_item, dc_host, new_state, flags, lastclock);

	old_poller_type = dc_item->poller_type;
	DCitem_poller_type_update(dc_item, dc_host, flags);

	DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_requeue_item_at                                               *
 *                                                                            *
 * Purpose: requeues items at the specified time                              *
 *                                                                            *
 * Parameters: dc_item   - [IN] the item to reque                             *
 *             dc_host   - [IN] item's host                                   *
 *             nextcheck - [IN] the scheduled time                            *
 *                                                                            *
 ******************************************************************************/
static void	dc_requeue_item_at(ZBX_DC_ITEM *dc_item, ZBX_DC_HOST *dc_host, int nextcheck)
{
	unsigned char	old_poller_type;
	int		old_nextcheck;

	dc_item->queue_priority = ZBX_QUEUE_PRIORITY_HIGH;

	old_nextcheck = dc_item->nextcheck;
	dc_item->nextcheck = nextcheck;

	old_poller_type = dc_item->poller_type;
	DCitem_poller_type_update(dc_item, dc_host, ZBX_ITEM_COLLECTED);

	DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_poller_items                                        *
 *                                                                            *
 * Purpose: Get array of items for selected poller                            *
 *                                                                            *
 * Parameters: poller_type - [IN] poller type (ZBX_POLLER_TYPE_...)           *
 *             items       - [OUT] array of items                             *
 *                                                                            *
 * Return value: number of items in items array                               *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 * Comments: Items leave the queue only through this function. Pollers must   *
 *           always return the items they have taken using DCrequeue_items()  *
 *           or DCpoller_requeue_items().                                     *
 *                                                                            *
 *           Currently batch polling is supported only for JMX, SNMP and      *
 *           icmpping* simple checks. In other cases only single item is      *
 *           retrieved.                                                       *
 *                                                                            *
 *           IPMI poller queue are handled by DCconfig_get_ipmi_poller_items()*
 *           function.                                                        *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_poller_items(unsigned char poller_type, DC_ITEM *items)
{
	const char		*__function_name = "DCconfig_get_poller_items";

	int			now, num = 0, max_items;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() poller_type:%d", __function_name, (int)poller_type);

	now = time(NULL);

	queue = &config->queues[poller_type];

	switch (poller_type)
	{
		case ZBX_POLLER_TYPE_JAVA:
			max_items = MAX_JAVA_ITEMS;
			break;
		case ZBX_POLLER_TYPE_PINGER:
			max_items = MAX_PINGER_ITEMS;
			break;
		default:
			max_items = 1;
	}

	LOCK_CACHE;

	while (num < max_items && FAIL == zbx_binary_heap_empty(queue))
	{
		int				disable_until;
		const zbx_binary_heap_elem_t	*min;
		ZBX_DC_HOST			*dc_host;
		ZBX_DC_ITEM			*dc_item;
		static const ZBX_DC_ITEM	*dc_item_prev = NULL;

		min = zbx_binary_heap_find_min(queue);
		dc_item = (ZBX_DC_ITEM *)min->data;

		if (dc_item->nextcheck > now)
			break;

		if (0 != num)
		{
			if (SUCCEED == is_snmp_type(dc_item_prev->type))
			{
				if (0 != __config_snmp_item_compare(dc_item_prev, dc_item))
					break;
			}
			else if (ITEM_TYPE_JMX == dc_item_prev->type)
			{
				if (0 != __config_java_item_compare(dc_item_prev, dc_item))
					break;
			}
		}

		zbx_binary_heap_remove_min(queue);
		dc_item->location = ZBX_LOC_NOWHERE;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
		{
			dc_requeue_item(dc_item, dc_host, dc_item->state, ZBX_ITEM_COLLECTED, now);
			continue;
		}

		/* don't apply unreachable item/host throttling for prioritized items */
		if (ZBX_QUEUE_PRIORITY_HIGH != dc_item->queue_priority)
		{
			if (0 == (disable_until = DCget_disable_until(dc_item, dc_host)))
			{
				/* move reachable items on reachable hosts to normal pollers */
				if (ZBX_POLLER_TYPE_UNREACHABLE == poller_type &&
						ZBX_QUEUE_PRIORITY_LOW != dc_item->queue_priority)
				{
					dc_requeue_item(dc_item, dc_host, dc_item->state, ZBX_ITEM_COLLECTED, now);
					continue;
				}
			}
			else
			{
				/* move items on unreachable hosts to unreachable pollers or    */
				/* postpone checks on hosts that have been checked recently and */
				/* are still unreachable                                        */
				if (ZBX_POLLER_TYPE_NORMAL == poller_type || ZBX_POLLER_TYPE_JAVA == poller_type ||
						disable_until > now)
				{
					dc_requeue_item(dc_item, dc_host, dc_item->state,
							ZBX_ITEM_COLLECTED | ZBX_HOST_UNREACHABLE, now);
					continue;
				}

				DCincrease_disable_until(dc_item, dc_host, now);
			}
		}

		dc_item_prev = dc_item;
		dc_item->location = ZBX_LOC_POLLER;
		DCget_host(&items[num].host, dc_host);
		DCget_item(&items[num], dc_item);
		num++;

		if (1 == num && ZBX_POLLER_TYPE_NORMAL == poller_type && SUCCEED == is_snmp_type(dc_item->type) &&
				0 == (ZBX_FLAG_DISCOVERY_RULE & dc_item->flags))
		{
			ZBX_DC_SNMPITEM	*snmpitem;

			snmpitem = (ZBX_DC_SNMPITEM *)zbx_hashset_search(&config->snmpitems, &dc_item->itemid);

			if (ZBX_SNMP_OID_TYPE_NORMAL == snmpitem->snmp_oid_type ||
					ZBX_SNMP_OID_TYPE_DYNAMIC == snmpitem->snmp_oid_type)
			{
				max_items = DCconfig_get_suggested_snmp_vars_nolock(dc_item->interfaceid, NULL);
			}
		}
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, num);

	return num;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_ipmi_poller_items                                   *
 *                                                                            *
 * Purpose: Get array of items for IPMI poller                                *
 *                                                                            *
 * Parameters: now       - [IN] current timestamp                             *
 *             items     - [OUT] array of items                               *
 *             items_num - [IN] the number of items to get                    *
 *             nextcheck - [OUT] the next scheduled check                     *
 *                                                                            *
 * Return value: number of items in items array                               *
 *                                                                            *
 * Comments: IPMI items leave the queue only through this function. IPMI      *
 *           manager must always return the items they have taken using       *
 *           DCrequeue_items() or DCpoller_requeue_items().                   *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_ipmi_poller_items(int now, DC_ITEM *items, int items_num, int *nextcheck)
{
	const char		*__function_name = "DCconfig_get_ipmi_poller_items";

	int			num = 0;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	queue = &config->queues[ZBX_POLLER_TYPE_IPMI];

	LOCK_CACHE;

	while (num < items_num && FAIL == zbx_binary_heap_empty(queue))
	{
		int				disable_until;
		const zbx_binary_heap_elem_t	*min;
		ZBX_DC_HOST			*dc_host;
		ZBX_DC_ITEM			*dc_item;

		min = zbx_binary_heap_find_min(queue);
		dc_item = (ZBX_DC_ITEM *)min->data;

		if (dc_item->nextcheck > now)
			break;

		zbx_binary_heap_remove_min(queue);
		dc_item->location = ZBX_LOC_NOWHERE;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
		{
			dc_requeue_item(dc_item, dc_host, dc_item->state, ZBX_ITEM_COLLECTED, now);
			continue;
		}

		/* don't apply unreachable item/host throttling for prioritized items */
		if (ZBX_QUEUE_PRIORITY_HIGH != dc_item->queue_priority)
		{
			if (0 != (disable_until = DCget_disable_until(dc_item, dc_host)))
			{
				if (disable_until > now)
				{
					dc_requeue_item(dc_item, dc_host, dc_item->state,
							ZBX_ITEM_COLLECTED | ZBX_HOST_UNREACHABLE, now);
					continue;
				}

				DCincrease_disable_until(dc_item, dc_host, now);
			}
		}

		dc_item->location = ZBX_LOC_POLLER;
		DCget_host(&items[num].host, dc_host);
		DCget_item(&items[num], dc_item);
		num++;
	}

	*nextcheck = dc_config_get_queue_nextcheck(&config->queues[ZBX_POLLER_TYPE_IPMI]);

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, num);

	return num;
}


/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_snmp_interfaceids_by_addr                           *
 *                                                                            *
 * Purpose: get array of interface IDs for the specified address              *
 *                                                                            *
 * Return value: number of interface IDs returned                             *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_snmp_interfaceids_by_addr(const char *addr, zbx_uint64_t **interfaceids)
{
	const char		*__function_name = "DCconfig_get_snmp_interfaceids_by_addr";

	int			count = 0, i;
	ZBX_DC_INTERFACE_ADDR	*dc_interface_snmpaddr, dc_interface_snmpaddr_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s'", __function_name, addr);

	dc_interface_snmpaddr_local.addr = addr;

	LOCK_CACHE;

	if (NULL == (dc_interface_snmpaddr = (ZBX_DC_INTERFACE_ADDR *)zbx_hashset_search(&config->interface_snmpaddrs, &dc_interface_snmpaddr_local)))
		goto unlock;

	*interfaceids = (zbx_uint64_t *)zbx_malloc(*interfaceids, dc_interface_snmpaddr->interfaceids.values_num * sizeof(zbx_uint64_t));

	for (i = 0; i < dc_interface_snmpaddr->interfaceids.values_num; i++)
		(*interfaceids)[i] = dc_interface_snmpaddr->interfaceids.values[i];

	count = i;
unlock:
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, count);

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_snmp_items_by_interfaceid                           *
 *                                                                            *
 * Purpose: get array of snmp trap items for the specified interfaceid        *
 *                                                                            *
 * Return value: number of items returned                                     *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
size_t	DCconfig_get_snmp_items_by_interfaceid(zbx_uint64_t interfaceid, DC_ITEM **items)
{
	const char		*__function_name = "DCconfig_get_snmp_items_by_interface";

	size_t			items_num = 0, items_alloc = 8;
	int			i;
	ZBX_DC_ITEM		*dc_item;
	ZBX_DC_INTERFACE_ITEM	*dc_interface_snmpitem;
	ZBX_DC_INTERFACE	*dc_interface;
	ZBX_DC_HOST		*dc_host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() interfaceid:" ZBX_FS_UI64, __function_name, interfaceid);

	LOCK_CACHE;

	if (NULL == (dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid)))
		goto unlock;

	if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_interface->hostid)))
		goto unlock;

	if (HOST_STATUS_MONITORED != dc_host->status)
		goto unlock;

	if (NULL == (dc_interface_snmpitem = (ZBX_DC_INTERFACE_ITEM *)zbx_hashset_search(&config->interface_snmpitems, &interfaceid)))
		goto unlock;

	*items = (DC_ITEM *)zbx_malloc(*items, items_alloc * sizeof(DC_ITEM));

	for (i = 0; i < dc_interface_snmpitem->itemids.values_num; i++)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &dc_interface_snmpitem->itemids.values[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
			continue;

		if (0 == config->config->refresh_unsupported && ITEM_STATE_NOTSUPPORTED == dc_item->state)
			continue;

		if (items_num == items_alloc)
		{
			items_alloc += 8;
			*items = (DC_ITEM *)zbx_realloc(*items, items_alloc * sizeof(DC_ITEM));
		}

		DCget_host(&(*items)[items_num].host, dc_host);
		DCget_item(&(*items)[items_num], dc_item);
		items_num++;
	}
unlock:
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)items_num);

	return items_num;
}

static void	dc_requeue_items(const zbx_uint64_t *itemids, const unsigned char *states, const int *lastclocks,
		const int *errcodes, size_t num)
{
	size_t		i;
	ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST	*dc_host;

	for (i = 0; i < num; i++)
	{
		if (FAIL == errcodes[i])
			continue;

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids[i])))
			continue;

		if (ZBX_LOC_POLLER == dc_item->location)
			dc_item->location = ZBX_LOC_NOWHERE;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		if (SUCCEED != is_counted_in_item_queue(dc_item->type, dc_item->key))
			continue;

		switch (errcodes[i])
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
			case CONFIG_ERROR:
				dc_item->queue_priority = ZBX_QUEUE_PRIORITY_NORMAL;
				dc_requeue_item(dc_item, dc_host, states[i], ZBX_ITEM_COLLECTED, lastclocks[i]);
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
			case TIMEOUT_ERROR:
				dc_item->queue_priority = ZBX_QUEUE_PRIORITY_LOW;
				dc_requeue_item(dc_item, dc_host, states[i], ZBX_ITEM_COLLECTED | ZBX_HOST_UNREACHABLE,
						time(NULL));
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
}

void	DCrequeue_items(const zbx_uint64_t *itemids, const unsigned char *states, const int *lastclocks,
		const int *errcodes, size_t num)
{
	LOCK_CACHE;

	dc_requeue_items(itemids, states, lastclocks, errcodes, num);

	UNLOCK_CACHE;
}

void	DCpoller_requeue_items(const zbx_uint64_t *itemids, const unsigned char *states, const int *lastclocks,
		const int *errcodes, size_t num, unsigned char poller_type, int *nextcheck)
{
	LOCK_CACHE;

	dc_requeue_items(itemids, states, lastclocks, errcodes, num);
	*nextcheck = dc_config_get_queue_nextcheck(&config->queues[poller_type]);

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_requeue_unreachable_items                                 *
 *                                                                            *
 * Purpose: requeue unreachable items                                         *
 *                                                                            *
 * Parameters: itemids     - [IN] the item id array                           *
 *             itemids_num - [IN] the number of values in itemids array       *
 *                                                                            *
 * Comments: This function is used when items must be put back in the queue   *
 *           without polling them. For example if a poller has taken a batch  *
 *           of items from queue, host becomes unreachable during while       *
 *           polling the items, so the unpolled items of the same host must   *
 *           be returned to queue without updating their status.              *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_requeue_unreachable_items(zbx_uint64_t *itemids, size_t itemids_num)
{
	size_t		i;
	ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	for (i = 0; i < itemids_num; i++)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids[i])))
			continue;

		if (ZBX_LOC_POLLER == dc_item->location)
			dc_item->location = ZBX_LOC_NOWHERE;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		dc_requeue_item(dc_item, dc_host, dc_item->state, ZBX_ITEM_COLLECTED | ZBX_HOST_UNREACHABLE,
				time(NULL));
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DChost_get_agent_availability                                    *
 *                                                                            *
 * Purpose: get host availability data for the specified agent                *
 *                                                                            *
 * Parameters: dc_host      - [IN] the host                                   *
 *             agent        - [IN] the agent (see ZBX_FLAGS_AGENT_STATUS_*    *
 *                                 defines                                    *
 *             availability - [OUT] the host availability data                *
 *                                                                            *
 * Comments: The configuration cache must be locked already.                  *
 *                                                                            *
 ******************************************************************************/
static void	DChost_get_agent_availability(const ZBX_DC_HOST *dc_host, unsigned char agent_type,
		zbx_agent_availability_t *agent)
{

	agent->flags = ZBX_FLAGS_AGENT_STATUS;

	switch (agent_type)
	{
		case ZBX_AGENT_ZABBIX:
			agent->available = dc_host->available;
			agent->error = zbx_strdup(agent->error, dc_host->error);
			agent->errors_from = dc_host->errors_from;
			agent->disable_until = dc_host->disable_until;
			break;
		case ZBX_AGENT_SNMP:
			agent->available = dc_host->snmp_available;
			agent->error = zbx_strdup(agent->error, dc_host->snmp_error);
			agent->errors_from = dc_host->snmp_errors_from;
			agent->disable_until = dc_host->snmp_disable_until;
			break;
		case ZBX_AGENT_IPMI:
			agent->available = dc_host->ipmi_available;
			agent->error = zbx_strdup(agent->error, dc_host->ipmi_error);
			agent->errors_from = dc_host->ipmi_errors_from;
			agent->disable_until = dc_host->ipmi_disable_until;
			break;
		case ZBX_AGENT_JMX:
			agent->available = dc_host->jmx_available;
			agent->error = zbx_strdup(agent->error, dc_host->jmx_error);
			agent->errors_from = dc_host->jmx_errors_from;
			agent->disable_until = dc_host->jmx_disable_until;
			break;
	}
}

static void	DCagent_set_availability(zbx_agent_availability_t *av,  unsigned char *available, const char **error,
		int *errors_from, int *disable_until)
{
#define AGENT_AVAILABILITY_ASSIGN(flags, mask, dst, src)	\
	if (0 != (flags & mask))				\
	{							\
		if (dst != src)					\
			dst = src;				\
		else						\
			flags &= (~(mask));			\
	}

#define AGENT_AVAILABILITY_ASSIGN_STR(flags, mask, dst, src)	\
	if (0 != (flags & mask))				\
	{							\
		if (0 != strcmp(dst, src))			\
			DCstrpool_replace(1, &dst, src);	\
		else						\
			flags &= (~(mask));			\
	}

	AGENT_AVAILABILITY_ASSIGN(av->flags, ZBX_FLAGS_AGENT_STATUS_AVAILABLE, *available, av->available);
	AGENT_AVAILABILITY_ASSIGN_STR(av->flags, ZBX_FLAGS_AGENT_STATUS_ERROR, *error, av->error);
	AGENT_AVAILABILITY_ASSIGN(av->flags, ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM, *errors_from, av->errors_from);
	AGENT_AVAILABILITY_ASSIGN(av->flags, ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL, *disable_until, av->disable_until);

#undef AGENT_AVAILABILITY_ASSIGN_STR
#undef AGENT_AVAILABILITY_ASSIGN
}

/******************************************************************************
 *                                                                            *
 * Function: DChost_set_agent_availability                                    *
 *                                                                            *
 * Purpose: set host availability data in configuration cache                 *
 *                                                                            *
 * Parameters: dc_host      - [OUT] the host                                  *
 *             availability - [IN/OUT] the host availability data             *
 *                                                                            *
 * Return value: SUCCEED - at least one availability field was updated        *
 *               FAIL    - no availability fields were updated                *
 *                                                                            *
 * Comments: The configuration cache must be locked already.                  *
 *                                                                            *
 *           This function clears availability flags of non updated fields    *
 *           updated leaving only flags identifying changed fields.           *
 *                                                                            *
 ******************************************************************************/
static int	DChost_set_agent_availability(ZBX_DC_HOST *dc_host, int now, unsigned char agent_type,
		zbx_agent_availability_t *agent)
{
	switch (agent_type)
	{
		case ZBX_AGENT_ZABBIX:
			DCagent_set_availability(agent, &dc_host->available,
					&dc_host->error, &dc_host->errors_from, &dc_host->disable_until);
			break;
		case ZBX_AGENT_SNMP:
			DCagent_set_availability(agent, &dc_host->snmp_available,
					&dc_host->snmp_error, &dc_host->snmp_errors_from, &dc_host->snmp_disable_until);
			break;
		case ZBX_AGENT_IPMI:
			DCagent_set_availability(agent, &dc_host->ipmi_available,
					&dc_host->ipmi_error, &dc_host->ipmi_errors_from, &dc_host->ipmi_disable_until);
			break;
		case ZBX_AGENT_JMX:
			DCagent_set_availability(agent, &dc_host->jmx_available,
					&dc_host->jmx_error, &dc_host->jmx_errors_from, &dc_host->jmx_disable_until);
			break;
	}

	if (ZBX_FLAGS_AGENT_STATUS_NONE == agent->flags)
		return FAIL;

	if (0 != (agent->flags & (ZBX_FLAGS_AGENT_STATUS_AVAILABLE | ZBX_FLAGS_AGENT_STATUS_ERROR)))
		dc_host->availability_ts = now;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DChost_set_availability                                          *
 *                                                                            *
 * Purpose: set host availability data in configuration cache                 *
 *                                                                            *
 * Parameters: dc_host      - [OUT] the host                                  *
 *             availability - [IN/OUT] the host availability data             *
 *                                                                            *
 * Return value: SUCCEED - at least one availability field was updated        *
 *               FAIL    - no availability fields were updated                *
 *                                                                            *
 * Comments: The configuration cache must be locked already.                  *
 *                                                                            *
 *           This function clears availability flags of non updated fields    *
 *           updated leaving only flags identifying changed fields.           *
 *                                                                            *
 ******************************************************************************/
static int	DChost_set_availability(ZBX_DC_HOST *dc_host, int now, zbx_host_availability_t *ha)
{
	int		i;
	unsigned char	flags = ZBX_FLAGS_AGENT_STATUS_NONE;

	DCagent_set_availability(&ha->agents[ZBX_AGENT_ZABBIX], &dc_host->available, &dc_host->error,
			&dc_host->errors_from, &dc_host->disable_until);
	DCagent_set_availability(&ha->agents[ZBX_AGENT_SNMP], &dc_host->snmp_available, &dc_host->snmp_error,
			&dc_host->snmp_errors_from, &dc_host->snmp_disable_until);
	DCagent_set_availability(&ha->agents[ZBX_AGENT_IPMI], &dc_host->ipmi_available, &dc_host->ipmi_error,
			&dc_host->ipmi_errors_from, &dc_host->ipmi_disable_until);
	DCagent_set_availability(&ha->agents[ZBX_AGENT_JMX], &dc_host->jmx_available, &dc_host->jmx_error,
			&dc_host->jmx_errors_from, &dc_host->jmx_disable_until);

	for (i = 0; i < ZBX_AGENT_MAX; i++)
		flags |= ha->agents[i].flags;

	if (ZBX_FLAGS_AGENT_STATUS_NONE == flags)
		return FAIL;

	if (0 != (flags & (ZBX_FLAGS_AGENT_STATUS_AVAILABLE | ZBX_FLAGS_AGENT_STATUS_ERROR)))
		dc_host->availability_ts = now;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_availability_init                                       *
 *                                                                            *
 * Purpose: initializes host availability data                                *
 *                                                                            *
 * Parameters: availability - [IN/OUT] host availability data                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_host_availability_init(zbx_host_availability_t *availability, zbx_uint64_t hostid)
{
	memset(availability, 0, sizeof(zbx_host_availability_t));
	availability->hostid = hostid;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_availability_clean                                      *
 *                                                                            *
 * Purpose: releases resources allocated to store host availability data      *
 *                                                                            *
 * Parameters: availability - [IN] host availability data                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_host_availability_clean(zbx_host_availability_t *ha)
{
	int	i;

	for (i = 0; i < ZBX_AGENT_MAX; i++)
		zbx_free(ha->agents[i].error);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_availability_free                                       *
 *                                                                            *
 * Purpose: frees host availability data                                      *
 *                                                                            *
 * Parameters: availability - [IN] host availability data                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_host_availability_free(zbx_host_availability_t *availability)
{
	zbx_host_availability_clean(availability);
	zbx_free(availability);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_agent_availability_init                                      *
 *                                                                            *
 * Purpose: initializes agent availability with the specified data            *
 *                                                                            *
 * Parameters: availability  - [IN/OUT] agent availability data               *
 *             hostid        - [IN] the host identifier                       *
 *             flags         - [IN] the availability flags indicating which   *
 *                                  availability fields to set                *
 *             available     - [IN] the availability data                     *
 *             error         - [IN]                                           *
 *             errors_from   - [IN]                                           *
 *             disable_until - [IN]                                           *
 *                                                                            *
 ******************************************************************************/
static void	zbx_agent_availability_init(zbx_agent_availability_t *agent, unsigned char available, const char *error,
		int errors_from, int disable_until)
{
	agent->flags = ZBX_FLAGS_AGENT_STATUS;
	agent->available = available;
	agent->error = zbx_strdup(agent->error, error);
	agent->errors_from = errors_from;
	agent->disable_until = disable_until;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_availability_is_set                                     *
 *                                                                            *
 * Purpose: checks host availability if any agent availability field is set   *
 *                                                                            *
 * Parameters: availability - [IN] host availability data                     *
 *                                                                            *
 * Return value: SUCCEED - an agent availability field is set                 *
 *               FAIL - no agent availability fields are set                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_host_availability_is_set(const zbx_host_availability_t *ha)
{
	int	i;

	for (i = 0; i < ZBX_AGENT_MAX; i++)
	{
		if (ZBX_FLAGS_AGENT_STATUS_NONE != ha->agents[i].flags)
			return SUCCEED;
	}

	return FAIL;
}

/**************************************************************************************
 *                                                                                    *
 * Host availability update example                                                   *
 *                                                                                    *
 *                                                                                    *
 *               |            UnreachablePeriod                                       *
 *               |               (conf file)                                          *
 *               |              ______________                                        *
 *               |             /              \                                       *
 *               |             p     p     p     p       p       p                    *
 *               |             o     o     o     o       o       o                    *
 *               |             l     l     l     l       l       l                    *
 *               |             l     l     l     l       l       l                    *
 *               | n                                                                  *
 *               | e           e     e     e     e       e       e                    *
 *     agent     | w   p   p   r     r     r     r       r       r       p   p   p    *
 *       polls   |     o   o   r     r     r     r       r       r       o   o   o    *
 *               | h   l   l   o     o     o     o       o       o       l   l   l    *
 *               | o   l   l   r     r     r     r       r       r       l   l   l    *
 *               | s                                                                  *
 *               | t   ok  ok  E1    E1    E2    E1      E1      E2      ok  ok  ok   *
 *  --------------------------------------------------------------------------------  *
 *  available    | 0   1   1   1     1     1     2       2       2       0   0   0    *
 *               |                                                                    *
 *  error        | ""  ""  ""  ""    ""    ""    E1      E1      E2      ""  ""  ""   *
 *               |                                                                    *
 *  errors_from  | 0   0   0   T4    T4    T4    T4      T4      T4      0   0   0    *
 *               |                                                                    *
 *  disable_until| 0   0   0   T5    T6    T7    T8      T9      T10     0   0   0    *
 *  --------------------------------------------------------------------------------  *
 *   timestamps  | T1  T2  T3  T4    T5    T6    T7      T8      T9     T10 T11 T12   *
 *               |  \_/ \_/ \_/ \___/ \___/ \___/ \_____/ \_____/ \_____/ \_/ \_/     *
 *               |   |   |   |    |     |     |      |       |       |     |   |      *
 *  polling      |  item delay   UnreachableDelay    UnavailableDelay     item |      *
 *      periods  |                 (conf file)         (conf file)         delay      *
 *                                                                                    *
 *                                                                                    *
 **************************************************************************************/

/******************************************************************************
 *                                                                            *
 * Function: DChost_activate                                                  *
 *                                                                            *
 * Purpose: set host as available based on the agent availability data        *
 *                                                                            *
 * Parameters: hostid     - [IN] the host identifier                          *
 *             agent_type - [IN] the agent type (see ZBX_AGENT_* defines)     *
 *             ts         - [IN] the last timestamp                           *
 *             in         - [IN/OUT] IN: the caller's agent availability data *
 *                                  OUT: the agent availability data in cache *
 *                                       before changes                       *
 *             out        - [OUT] the agent availability data after changes   *
 *                                                                            *
 * Return value: SUCCEED - the host was activated successfully                *
 *               FAIL    - the host was already activated or activation       *
 *                         failed                                             *
 *                                                                            *
 * Comments: The host availability fields are updated according to the above  *
 *           schema.                                                          *
 *                                                                            *
 ******************************************************************************/
int	DChost_activate(zbx_uint64_t hostid, unsigned char agent_type, const zbx_timespec_t *ts,
		zbx_agent_availability_t *in, zbx_agent_availability_t *out)
{
	int		ret = FAIL;
	ZBX_DC_HOST	*dc_host;

	/* don't try activating host if there were no errors detected */
	if (0 == in->errors_from && HOST_AVAILABLE_TRUE == in->available)
		goto out;

	LOCK_CACHE;

	if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
		goto unlock;

	/* Don't try activating host if:                  */
	/* - (server, proxy) it's not monitored any more; */
	/* - (server) it's monitored by proxy.            */
	if ((0 != (program_type & ZBX_PROGRAM_TYPE_SERVER) && 0 != dc_host->proxy_hostid) ||
			HOST_STATUS_MONITORED != dc_host->status)
	{
		goto unlock;
	}

	DChost_get_agent_availability(dc_host, agent_type, in);
	zbx_agent_availability_init(out, HOST_AVAILABLE_TRUE, "", 0, 0);
	DChost_set_agent_availability(dc_host, ts->sec, agent_type, out);

	if (ZBX_FLAGS_AGENT_STATUS_NONE != out->flags)
		ret = SUCCEED;
unlock:
	UNLOCK_CACHE;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DChost_deactivate                                                *
 *                                                                            *
 * Purpose: attempt to set host as unavailable based on agent availability    *
 *                                                                            *
 * Parameters: hostid     - [IN] the host identifier                          *
 *             agent_type - [IN] the agent type (see ZBX_AGENT_* defines)     *
 *             ts         - [IN] the last timestamp                           *
 *             in         - [IN/OUT] IN: the caller's host availability data  *
 *                                  OUT: the host availability data in cache  *
 *                                       before changes                       *
 *             out        - [OUT] the host availability data after changes    *
 *             error      - [IN] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the host was deactivated successfully              *
 *               FAIL    - the host was already deactivated or deactivation   *
 *                         failed                                             *
 *                                                                            *
 * Comments: The host availability fields are updated according to the above  *
 *           schema.                                                          *
 *                                                                            *
 ******************************************************************************/
int	DChost_deactivate(zbx_uint64_t hostid, unsigned char agent_type, const zbx_timespec_t *ts,
		zbx_agent_availability_t *in, zbx_agent_availability_t *out, const char *error_msg)
{
	int		ret = FAIL, errors_from,disable_until;
	const char	*error;
	unsigned char	available;
	ZBX_DC_HOST	*dc_host;


	/* don't try deactivating host if the unreachable delay has not passed since the first error */
	if (CONFIG_UNREACHABLE_DELAY > ts->sec - in->errors_from)
		goto out;

	LOCK_CACHE;

	if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
		goto unlock;

	/* Don't try deactivating host if:                */
	/* - (server, proxy) it's not monitored any more; */
	/* - (server) it's monitored by proxy.            */
	if ((0 != (program_type & ZBX_PROGRAM_TYPE_SERVER) && 0 != dc_host->proxy_hostid) ||
			HOST_STATUS_MONITORED != dc_host->status)
	{
		goto unlock;
	}

	DChost_get_agent_availability(dc_host, agent_type, in);

	available = in->available;
	error = in->error;

	if (0 == in->errors_from)
	{
		/* first error, schedule next unreachable check */
		errors_from = ts->sec;
		disable_until = ts->sec + CONFIG_UNREACHABLE_DELAY;
	}
	else
	{
		errors_from = in->errors_from;
		disable_until = in->disable_until;

		/* Check if other pollers haven't already attempted deactivating host. */
		/* In that case should wait the initial unreachable delay before       */
		/* trying to make it unavailable.                                      */
		if (CONFIG_UNREACHABLE_DELAY <= ts->sec - errors_from)
		{
			/* repeating error */
			if (CONFIG_UNREACHABLE_PERIOD > ts->sec - errors_from)
			{
				/* leave host available, schedule next unreachable check */
				disable_until = ts->sec + CONFIG_UNREACHABLE_DELAY;
			}
			else
			{
				/* make host unavailable, schedule next unavailable check */
				disable_until = ts->sec + CONFIG_UNAVAILABLE_DELAY;
				available = HOST_AVAILABLE_FALSE;
				error = error_msg;
			}
		}
	}

	zbx_agent_availability_init(out, available, error, errors_from, disable_until);
	DChost_set_agent_availability(dc_host, ts->sec, agent_type, out);

	if (ZBX_FLAGS_AGENT_STATUS_NONE != out->flags)
		ret = SUCCEED;
unlock:
	UNLOCK_CACHE;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DCset_hosts_availability                                         *
 *                                                                            *
 * Purpose: update availability of hosts in configuration cache and return    *
 *          the updated field flags                                           *
 *                                                                            *
 * Parameters: availabilities - [IN/OUT] the hosts availability data          *
 *                                                                            *
 * Return value: SUCCEED - at least one host availability data was updated    *
 *               FAIL    - no hosts were updated                              *
 *                                                                            *
 ******************************************************************************/
int	DCset_hosts_availability(zbx_vector_ptr_t *availabilities)
{
	int			i;
	ZBX_DC_HOST		*dc_host;
	zbx_host_availability_t	*ha;
	int			ret = FAIL, now;

	now = time(NULL);

	LOCK_CACHE;

	for (i = 0; i < availabilities->values_num; i++)
	{
		ha = (zbx_host_availability_t *)availabilities->values[i];

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &ha->hostid)))
		{
			int	j;

			/* reset availability flags so this host is ignored when saving availability diff to DB */
			for (j = 0; j < ZBX_AGENT_MAX; j++)
				ha->agents[j].flags = ZBX_FLAGS_AGENT_STATUS_NONE;

			continue;
		}

		if (SUCCEED == DChost_set_availability(dc_host, now, ha))
			ret = SUCCEED;
	}

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: helper function for trigger dependency checking                  *
 *                                                                            *
 * Parameters: trigdep        - [IN] the trigger dependency data              *
 *             level          - [IN] the trigger dependency level             *
 *             triggerids     - [IN] the currently processing trigger ids     *
 *                                   for bulk trigger operations              *
 *                                   (optional, can be NULL)                  *
 *             master_triggerids - [OUT] unresolved master trigger ids        *
 *                                   for bulk trigger operations              *
 *                                   (optional together with triggerids       *
 *                                   parameter)                               *
 *                                                                            *
 * Return value: SUCCEED - trigger dependency check succeed / was unresolved  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: With bulk trigger processing a master trigger can be in the same *
 *           batch as dependent trigger. In this case it might be impossible  *
 *           to perform dependency check based on cashed trigger values. The  *
 *           unresolved master trigger ids will be added to master_triggerids *
 *           vector, so the dependency check can be performed after a new     *
 *           master trigger value has been calculated.                        *
 *                                                                            *
 ******************************************************************************/
static int	DCconfig_check_trigger_dependencies_rec(const ZBX_DC_TRIGGER_DEPLIST *trigdep, int level,
		const zbx_vector_uint64_t *triggerids, zbx_vector_uint64_t *master_triggerids)
{
	int				i;
	const ZBX_DC_TRIGGER		*next_trigger;
	const ZBX_DC_TRIGGER_DEPLIST	*next_trigdep;

	if (ZBX_TRIGGER_DEPENDENCY_LEVELS_MAX < level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "recursive trigger dependency is too deep (triggerid:" ZBX_FS_UI64 ")",
				trigdep->triggerid);
		return SUCCEED;
	}

	if (0 != trigdep->dependencies.values_num)
	{
		for (i = 0; i < trigdep->dependencies.values_num; i++)
		{
			next_trigdep = (const ZBX_DC_TRIGGER_DEPLIST *)trigdep->dependencies.values[i];

			if (NULL != (next_trigger = next_trigdep->trigger) &&
					TRIGGER_STATUS_ENABLED == next_trigger->status &&
					TRIGGER_FUNCTIONAL_TRUE == next_trigger->functional)
			{

				if (NULL == triggerids || FAIL == zbx_vector_uint64_bsearch(triggerids,
						next_trigger->triggerid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					if (TRIGGER_VALUE_PROBLEM == next_trigger->value)
						return FAIL;
				}
				else
					zbx_vector_uint64_append(master_triggerids, next_trigger->triggerid);
			}

			if (FAIL == DCconfig_check_trigger_dependencies_rec(next_trigdep, level + 1, triggerids,
					master_triggerids))
			{
				return FAIL;
			}
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_check_trigger_dependencies                              *
 *                                                                            *
 * Purpose: check whether any of trigger dependencies have value PROBLEM      *
 *                                                                            *
 * Return value: SUCCEED - trigger can change its value                       *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_check_trigger_dependencies(zbx_uint64_t triggerid)
{
	int				ret = SUCCEED;
	const ZBX_DC_TRIGGER_DEPLIST	*trigdep;

	LOCK_CACHE;

	if (NULL != (trigdep = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_search(&config->trigdeps, &triggerid)))
		ret = DCconfig_check_trigger_dependencies_rec(trigdep, 0, NULL, NULL);

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: helper function for DCconfig_sort_triggers_topologically()       *
 *                                                                            *
 ******************************************************************************/
static unsigned char	DCconfig_sort_triggers_topologically_rec(const ZBX_DC_TRIGGER_DEPLIST *trigdep, int level)
{
	int				i;
	unsigned char			topoindex = 2, next_topoindex;
	const ZBX_DC_TRIGGER_DEPLIST	*next_trigdep;

	if (32 < level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "recursive trigger dependency is too deep (triggerid:" ZBX_FS_UI64 ")",
				trigdep->triggerid);
		goto exit;
	}

	if (0 == trigdep->trigger->topoindex)
	{
		zabbix_log(LOG_LEVEL_CRIT, "trigger dependencies contain a cycle (triggerid:" ZBX_FS_UI64 ")",
				trigdep->triggerid);
		goto exit;
	}

	trigdep->trigger->topoindex = 0;

	for (i = 0; i < trigdep->dependencies.values_num; i++)
	{
		next_trigdep = (const ZBX_DC_TRIGGER_DEPLIST *)trigdep->dependencies.values[i];

		if (1 < (next_topoindex = next_trigdep->trigger->topoindex))
			goto next;

		if (0 == next_trigdep->dependencies.values_num)
			continue;

		next_topoindex = DCconfig_sort_triggers_topologically_rec(next_trigdep, level + 1);
next:
		if (topoindex < next_topoindex + 1)
			topoindex = next_topoindex + 1;
	}

	trigdep->trigger->topoindex = topoindex;
exit:
	return topoindex;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_sort_triggers_topologically                             *
 *                                                                            *
 * Purpose: assign each trigger an index based on trigger dependency topology *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
static void	DCconfig_sort_triggers_topologically(void)
{
	zbx_hashset_iter_t		iter;
	ZBX_DC_TRIGGER			*trigger;
	const ZBX_DC_TRIGGER_DEPLIST	*trigdep;

	zbx_hashset_iter_reset(&config->trigdeps, &iter);

	while (NULL != (trigdep = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_iter_next(&iter)))
	{
		trigger = trigdep->trigger;

		if (NULL == trigger || 1 < trigger->topoindex || 0 == trigdep->dependencies.values_num)
			continue;

		DCconfig_sort_triggers_topologically_rec(trigdep, 0);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_triggers_apply_changes                                  *
 *                                                                            *
 * Purpose: apply trigger value,state,lastchange or error changes to          *
 *          configuration cache after committed to database                   *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_triggers_apply_changes(zbx_vector_ptr_t *trigger_diff)
{
	int			i;
	zbx_trigger_diff_t	*diff;
	ZBX_DC_TRIGGER		*dc_trigger;

	if (0 == trigger_diff->values_num)
		return;

	LOCK_CACHE;

	for (i = 0; i < trigger_diff->values_num; i++)
	{
		diff = (zbx_trigger_diff_t *)trigger_diff->values[i];

		if (NULL == (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &diff->triggerid)))
			continue;

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE))
			dc_trigger->lastchange = diff->lastchange;

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE))
			dc_trigger->value = diff->value;

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE))
			dc_trigger->state = diff->state;

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR))
			DCstrpool_replace(1, &dc_trigger->error, diff->error);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_set_maintenance                                         *
 *                                                                            *
 * Purpose: set host maintenance status                                       *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_set_maintenance(const zbx_uint64_t *hostids, int hostids_num, int maintenance_status,
		int maintenance_type, int maintenance_from)
{
	int		i, now;
	ZBX_DC_HOST	*dc_host;

	now = time(NULL);

	LOCK_CACHE;

	for (i = 0; i < hostids_num; i++)
	{
		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostids[i])))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status && HOST_STATUS_NOT_MONITORED != dc_host->status)
			continue;

		if (dc_host->maintenance_status != maintenance_status)
			dc_host->maintenance_from = maintenance_from;

		if (MAINTENANCE_TYPE_NODATA == dc_host->maintenance_type && MAINTENANCE_TYPE_NODATA != maintenance_type)
		{
			/* Store time at which no-data maintenance ended for the host (either */
			/* because no-data maintenance ended or because maintenance type was */
			/* changed to normal), this is needed for nodata() trigger function. */
			dc_host->data_expected_from = now;
		}

		dc_host->maintenance_status = maintenance_status;
		dc_host->maintenance_type = maintenance_type;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_stats                                               *
 *                                                                            *
 * Purpose: get statistics of the database cache                              *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
void	*DCconfig_get_stats(int request)
{
	static zbx_uint64_t	value_uint;
	static double		value_double;

	switch (request)
	{
		case ZBX_CONFSTATS_BUFFER_TOTAL:
			value_uint = config_mem->orig_size;
			return &value_uint;
		case ZBX_CONFSTATS_BUFFER_USED:
			value_uint = config_mem->orig_size - config_mem->free_size;
			return &value_uint;
		case ZBX_CONFSTATS_BUFFER_FREE:
			value_uint = config_mem->free_size;
			return &value_uint;
		case ZBX_CONFSTATS_BUFFER_PFREE:
			value_double = 100.0 * ((double)(config_mem->free_size) / (config_mem->orig_size));
			return &value_double;
		default:
			return NULL;
	}
}

static void	DCget_proxy(DC_PROXY *dst_proxy, ZBX_DC_PROXY *src_proxy)
{
	ZBX_DC_HOST		*host;
	ZBX_DC_INTERFACE_HT	*interface_ht, interface_ht_local;

	dst_proxy->hostid = src_proxy->hostid;
	dst_proxy->proxy_config_nextcheck = src_proxy->proxy_config_nextcheck;
	dst_proxy->proxy_data_nextcheck = src_proxy->proxy_data_nextcheck;
	dst_proxy->proxy_tasks_nextcheck = src_proxy->proxy_tasks_nextcheck;
	dst_proxy->last_cfg_error_time = src_proxy->last_cfg_error_time;
	dst_proxy->version = src_proxy->version;

	if (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &src_proxy->hostid)))
	{
		strscpy(dst_proxy->host, host->host);
		strscpy(dst_proxy->proxy_address, host->proxy_address);

		dst_proxy->tls_connect = host->tls_connect;
		dst_proxy->tls_accept = host->tls_accept;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		strscpy(dst_proxy->tls_issuer, host->tls_issuer);
		strscpy(dst_proxy->tls_subject, host->tls_subject);

		if (NULL == host->tls_dc_psk)
		{
			*dst_proxy->tls_psk_identity = '\0';
			*dst_proxy->tls_psk = '\0';
		}
		else
		{
			strscpy(dst_proxy->tls_psk_identity, host->tls_dc_psk->tls_psk_identity);
			strscpy(dst_proxy->tls_psk, host->tls_dc_psk->tls_psk);
		}
#endif
	}
	else
	{
		/* DCget_proxy() is called only from DCconfig_get_proxypoller_hosts(), which is called only from */
		/* process_proxy(). So, this branch should never happen. */
		*dst_proxy->host = '\0';
		*dst_proxy->proxy_address = '\0';
		dst_proxy->tls_connect = ZBX_TCP_SEC_TLS_PSK;	/* set PSK to deliberately fail in this case */
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		*dst_proxy->tls_psk_identity = '\0';
		*dst_proxy->tls_psk = '\0';
#endif
		THIS_SHOULD_NEVER_HAPPEN;
	}

	interface_ht_local.hostid = src_proxy->hostid;
	interface_ht_local.type = INTERFACE_TYPE_UNKNOWN;

	if (NULL != (interface_ht = (ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht, &interface_ht_local)))
	{
		const ZBX_DC_INTERFACE	*interface = interface_ht->interface_ptr;

		strscpy(dst_proxy->addr_orig, interface->useip ? interface->ip : interface->dns);
		strscpy(dst_proxy->port_orig, interface->port);
	}
	else
	{
		*dst_proxy->addr_orig = '\0';
		*dst_proxy->port_orig = '\0';
	}

	dst_proxy->addr = NULL;
	dst_proxy->port = 0;
}

int	DCconfig_get_last_sync_time(void)
{
	return config->sync_ts;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_proxypoller_hosts                                   *
 *                                                                            *
 * Purpose: Get array of proxies for proxy poller                             *
 *                                                                            *
 * Parameters: hosts - [OUT] array of hosts                                   *
 *             max_hosts - [IN] elements in hosts array                       *
 *                                                                            *
 * Return value: number of proxies in hosts array                             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: Proxies leave the queue only through this function. Pollers must *
 *           always return the proxies they have taken using DCrequeue_proxy. *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_proxypoller_hosts(DC_PROXY *proxies, int max_hosts)
{
	const char		*__function_name = "DCconfig_get_proxypoller_hosts";
	int			now, num = 0;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	queue = &config->pqueue;

	LOCK_CACHE;

	while (num < max_hosts && FAIL == zbx_binary_heap_empty(queue))
	{
		const zbx_binary_heap_elem_t	*min;
		ZBX_DC_PROXY			*dc_proxy;

		min = zbx_binary_heap_find_min(queue);
		dc_proxy = (ZBX_DC_PROXY *)min->data;

		if (dc_proxy->nextcheck > now)
			break;

		zbx_binary_heap_remove_min(queue);
		dc_proxy->location = ZBX_LOC_POLLER;

		DCget_proxy(&proxies[num], dc_proxy);
		num++;
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, num);

	return num;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_proxypoller_nextcheck                               *
 *                                                                            *
 * Purpose: Get nextcheck for passive proxies                                 *
 *                                                                            *
 * Return value: nextcheck or FAIL if no passive proxies in queue             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_proxypoller_nextcheck(void)
{
	const char		*__function_name = "DCconfig_get_proxypoller_nextcheck";

	int			nextcheck;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	queue = &config->pqueue;

	LOCK_CACHE;

	if (FAIL == zbx_binary_heap_empty(queue))
	{
		const zbx_binary_heap_elem_t	*min;
		const ZBX_DC_PROXY		*dc_proxy;

		min = zbx_binary_heap_find_min(queue);
		dc_proxy = (const ZBX_DC_PROXY *)min->data;

		nextcheck = dc_proxy->nextcheck;
	}
	else
		nextcheck = FAIL;

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, nextcheck);

	return nextcheck;
}

void	DCrequeue_proxy(zbx_uint64_t hostid, unsigned char update_nextcheck, int proxy_conn_err)
{
	const char	*__function_name = "DCrequeue_proxy";

	time_t		now;
	ZBX_DC_HOST	*dc_host;
	ZBX_DC_PROXY	*dc_proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() update_nextcheck:%d", __function_name, (int)update_nextcheck);

	now = time(NULL);

	LOCK_CACHE;

	if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)) &&
			NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &hostid)))
	{
		if (ZBX_LOC_POLLER == dc_proxy->location)
			dc_proxy->location = ZBX_LOC_NOWHERE;

		/* set or clear passive proxy misconfiguration error timestamp */
		if (SUCCEED == proxy_conn_err)
			dc_proxy->last_cfg_error_time = 0;
		else if (CONFIG_ERROR == proxy_conn_err)
			dc_proxy->last_cfg_error_time = (int)now;

		if (HOST_STATUS_PROXY_PASSIVE == dc_host->status)
		{
			if (0 != (update_nextcheck & ZBX_PROXY_CONFIG_NEXTCHECK))
			{
				dc_proxy->proxy_config_nextcheck = (int)calculate_proxy_nextcheck(
						hostid, CONFIG_PROXYCONFIG_FREQUENCY, now);
			}

			if (0 != (update_nextcheck & ZBX_PROXY_DATA_NEXTCHECK))
			{
				dc_proxy->proxy_data_nextcheck = (int)calculate_proxy_nextcheck(
						hostid, CONFIG_PROXYDATA_FREQUENCY, now);
			}
			if (0 != (update_nextcheck & ZBX_PROXY_TASKS_NEXTCHECK))
			{
				dc_proxy->proxy_tasks_nextcheck = (int)calculate_proxy_nextcheck(
						hostid, ZBX_TASK_UPDATE_FREQUENCY, now);
			}

			DCupdate_proxy_queue(dc_proxy);
		}
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_set_proxy_timediff                                      *
 *                                                                            *
 * Purpose: set rounded time difference between server clock and proxy clock  *
 *                                                                            *
 * Comments: When we calculate "nextcheck" for items on proxied hosts, we     *
 *           take the time difference between server and proxy into account.  *
 *                                                                            *
 *           For instance, suppose an item was processed on the proxy at 10   *
 *           seconds and there is a time difference between server and proxy  *
 *           of -2 seconds (that is, proxy's time is 2 seconds in front). In  *
 *           the server's database, we will store the timestamp of 8 seconds. *
 *           However, when we calculate "nextcheck" on the server side, we    *
 *           should calculate it from 10 seconds. Otherwise, if we calculate  *
 *           it from 8 seconds, we will probably get those 10 seconds as the  *
 *           "nextcheck". Finally, after calculating "nextcheck", we should   *
 *           utilize the time difference again to get Zabbix server's time.   *
 *                                                                            *
 *           Now, suppose we have a non-integer time difference, say, of -1.5 *
 *           seconds. Suppose also that one item on the proxy was checked at  *
 *           4.7 seconds, whereas a second item was checked at 5.2 seconds,   *
 *           which corresponds to server times of 3.2 and 3.7 seconds. Since  *
 *           "nextcheck" calculation only uses the integer part, server will  *
 *           use 3 seconds as the basis, before using the time difference.    *
 *           However, it is very likely that the first item was scheduled for *
 *           4 seconds on the proxy and the second item for 5 seconds, so in  *
 *           order to be precise we would need to store time differences for  *
 *           each individual item. That would lead to a significant increase  *
 *           in memory consumption, which we rather avoid. For this reason    *
 *           we only store a single time difference between server and proxy, *
 *           and use it for all items. However, the way time difference is    *
 *           used is different before and after "nextcheck" calculation.      *
 *                                                                            *
 *           Consider the example above. Server uses 3 seconds as the basis,  *
 *           and subtracts a unified difference of -2 (rounded down) to both  *
 *           items, yielding 5. It then calculates "nextcheck" for these two  *
 *           items, yielding 4 and 5 (assuming a one-minute delay). It then   *
 *           adds a unified difference of -1 (rounded up), to get Zabbix      *
 *           server's time of 3 and 4.                                        *
 *                                                                            *
 *           This has the effect that items will get into the delayed item    *
 *           queue at most one second later, but this is feasible: in proxy   *
 *           to server communication there is a communication latency, which  *
 *           also has the effect of putting items into the delayed item queue *
 *           a bit later, and we do not account for that anyway.              *
 *                                                                            *
 *           As described above, we subtract a rounded down difference before *
 *           "nextcheck" calculation and a rounded up difference after. Since *
 *           we have a 1/10^9 chance of hitting an integer "timediff" value,  *
 *           which would yield the same value rounded down and rounded up, in *
 *           the proxy structure we only store the difference rounded down to *
 *           save space. This is achieved by discarding the "ns" part of the  *
 *           "timediff" structure. We then assume that the rounded down value *
 *           and the rounded up values are different.                         *
 *                                                                            *
 *           Calculations of "nextchecks" themselves are done in functions    *
 *           DCget_reachable_nextcheck() and DCsync_items() functions.        *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_set_proxy_timediff(zbx_uint64_t hostid, const zbx_timespec_t *timediff)
{
	ZBX_DC_PROXY	*dc_proxy;

	LOCK_CACHE;

	if (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &hostid)))
		dc_proxy->timediff = timediff->sec;

	UNLOCK_CACHE;
}

static void	dc_get_host_macro(const zbx_uint64_t *hostids, int host_num, const char *macro, const char *context,
		char **value, char **value_default)
{
	int			i, j;
	ZBX_DC_HMACRO_HM	*hmacro_hm, hmacro_hm_local;
	ZBX_DC_HTMPL		*htmpl;
	zbx_vector_uint64_t	templateids;

	if (0 == host_num)
		return;

	hmacro_hm_local.macro = macro;

	for (i = 0; i < host_num; i++)
	{
		hmacro_hm_local.hostid = hostids[i];

		if (NULL != (hmacro_hm = (ZBX_DC_HMACRO_HM *)zbx_hashset_search(&config->hmacros_hm, &hmacro_hm_local)))
		{
			for (j = 0; j < hmacro_hm->hmacros.values_num; j++)
			{
				ZBX_DC_HMACRO	*hmacro = (ZBX_DC_HMACRO *)hmacro_hm->hmacros.values[j];

				if (0 == strcmp(hmacro->macro, macro))
				{
					if (0 == zbx_strcmp_null(hmacro->context, context))
					{
						*value = zbx_strdup(*value, hmacro->value);
						return;
					}

					/* check for the default (without parameters) macro value */
					if (NULL == *value_default && NULL != context && NULL == hmacro->context)
						*value_default = zbx_strdup(*value_default, hmacro->value);
				}
			}
		}
	}

	zbx_vector_uint64_create(&templateids);
	zbx_vector_uint64_reserve(&templateids, 32);

	for (i = 0; i < host_num; i++)
	{
		if (NULL != (htmpl = (ZBX_DC_HTMPL *)zbx_hashset_search(&config->htmpls, &hostids[i])))
		{
			for (j = 0; j < htmpl->templateids.values_num; j++)
				zbx_vector_uint64_append(&templateids, htmpl->templateids.values[j]);
		}
	}

	if (0 != templateids.values_num)
	{
		zbx_vector_uint64_sort(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		dc_get_host_macro(templateids.values, templateids.values_num, macro, context, value, value_default);
	}

	zbx_vector_uint64_destroy(&templateids);
}

static void	dc_get_global_macro(const char *macro, const char *context, char **value, char **value_default)
{
	int		i;
	ZBX_DC_GMACRO_M	*gmacro_m, gmacro_m_local;

	gmacro_m_local.macro = macro;

	if (NULL != (gmacro_m = (ZBX_DC_GMACRO_M *)zbx_hashset_search(&config->gmacros_m, &gmacro_m_local)))
	{
		for (i = 0; i < gmacro_m->gmacros.values_num; i++)
		{
			ZBX_DC_GMACRO	*gmacro = (ZBX_DC_GMACRO *)gmacro_m->gmacros.values[i];

			if (0 == strcmp(gmacro->macro, macro))
			{
				if (0 == zbx_strcmp_null(gmacro->context, context))
				{
					*value = zbx_strdup(*value, gmacro->value);
					break;
				}

				/* check for the default (without parameters) macro value */
				if (NULL == *value_default && NULL != context && NULL == gmacro->context)
					*value_default = zbx_strdup(*value_default, gmacro->value);
			}
		}
	}
}

static void	dc_get_user_macro(const zbx_uint64_t *hostids, int hostids_num, const char *macro, const char *context,
		char **replace_to)
{
	char	*value = NULL, *value_default = NULL;

	/* User macros should be expanded according to the following priority: */
	/*                                                                     */
	/*  1) host context macro                                              */
	/*  2) global context macro                                            */
	/*  3) host base (default) macro                                       */
	/*  4) global base (default) macro                                     */
	/*                                                                     */
	/* We try to expand host macros first. If there is no perfect match on */
	/* the host level, we try to expand global macros, passing the default */
	/* macro value found on the host level, if any.                        */

	dc_get_host_macro(hostids, hostids_num, macro, context, &value, &value_default);

	if (NULL == value)
		dc_get_global_macro(macro, context, &value, &value_default);

	if (NULL != value)
	{
		zbx_free(*replace_to);
		*replace_to = value;

		zbx_free(value_default);
	}
	else if (NULL != value_default)
	{
		zbx_free(*replace_to);
		*replace_to = value_default;
	}
}

void	DCget_user_macro(const zbx_uint64_t *hostids, int hostids_num, const char *macro, char **replace_to)
{
	const char	*__function_name = "DCget_user_macro";
	char		*name = NULL, *context = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:'%s'", __function_name, macro);

	if (SUCCEED != zbx_user_macro_parse_dyn(macro, &name, &context, NULL))
		goto out;

	LOCK_CACHE;

	dc_get_user_macro(hostids, hostids_num, name, context, replace_to);

	UNLOCK_CACHE;

	zbx_free(context);
	zbx_free(name);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_expression_user_macro_validator                               *
 *                                                                            *
 * Purpose: validate user macro values in trigger expressions                 *
 *                                                                            *
 * Parameters: value   - [IN] the macro value                                 *
 *                                                                            *
 * Return value: SUCCEED - the macro value can be used in expression          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dc_expression_user_macro_validator(const char *value)
{
	if (SUCCEED == is_double_suffix(value, ZBX_FLAG_DOUBLE_SUFFIX))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_expand_user_macros                                        *
 *                                                                            *
 * Purpose: expand user macros in the specified text value                    *
 *                                                                            *
 * Parameters: text           - [IN] the text value to expand                 *
 *             hostids        - [IN] an array of related hostids              *
 *             hostids_num    - [IN] the number of hostids                    *
 *             validator_func - [IN] an optional validator function           *
 *                                                                            *
 * Return value: The text value with expanded user macros. Uknown or invalid  *
 *               macros will be left unresolved.                              *
 *                                                                            *
 * Comments: The returned value must be freed by the caller.                  *
 *           This function must be used only by configuration syncer          *
 *                                                                            *
 ******************************************************************************/
char	*zbx_dc_expand_user_macros(const char *text, zbx_uint64_t *hostids, int hostids_num,
		zbx_macro_value_validator_func_t validator_func)
{
	zbx_token_t	token;
	int		pos = 0, len, last_pos = 0;
	char		*str = NULL, *name = NULL, *context = NULL, *value = NULL;
	size_t		str_alloc = 0, str_offset = 0;

	if ('\0' == *text)
		return zbx_strdup(NULL, text);

	for (; SUCCEED == zbx_token_find(text, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		if (ZBX_TOKEN_USER_MACRO != token.type)
			continue;

		if (SUCCEED != zbx_user_macro_parse_dyn(text + token.token.l, &name, &context, &len))
			continue;

		zbx_strncpy_alloc(&str, &str_alloc, &str_offset, text + last_pos, token.token.l - last_pos);
		dc_get_user_macro(hostids, hostids_num, name, context, &value);

		if (NULL != value && NULL != validator_func && FAIL == validator_func(value))
			zbx_free(value);

		if (NULL != value)
		{
			zbx_strcpy_alloc(&str, &str_alloc, &str_offset, value);
			zbx_free(value);

		}
		else
		{
			zbx_strncpy_alloc(&str, &str_alloc, &str_offset, text + token.token.l,
					token.token.r - token.token.l + 1);
		}

		zbx_free(name);
		zbx_free(context);

		pos = token.token.r;
		last_pos = pos + 1;
	}

	zbx_strcpy_alloc(&str, &str_alloc, &str_offset, text + last_pos);

	return str;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_expression_expand_user_macros                                 *
 *                                                                            *
 * Purpose: expand user macros in trigger expression                          *
 *                                                                            *
 * Parameters: expression - [IN] the expression to expand                     *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: The expanded expression or NULL in the case of error.        *
 *               If NULL is returned the error message is set.                *
 *                                                                            *
 * Comments: The returned expression must be freed by the caller.             *
 *                                                                            *
 ******************************************************************************/
static char	*dc_expression_expand_user_macros(const char *expression, char **error)
{
	zbx_vector_uint64_t	functionids, hostids;
	char			*out;

	zbx_vector_uint64_create(&functionids);
	zbx_vector_uint64_create(&hostids);

	get_functionids(&functionids, expression);
	zbx_dc_get_hostids_by_functionids(functionids.values, functionids.values_num, &hostids);

	out = zbx_dc_expand_user_macros(expression, hostids.values, hostids.values_num,
			dc_expression_user_macro_validator);

	if (NULL != strstr(out, "{$"))
	{
		*error = zbx_strdup(*error, "cannot evaluate expression: invalid macro value");
		zbx_free(out);
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_uint64_destroy(&functionids);

	return out;
}

/******************************************************************************
 *                                                                            *
 * Function: DCexpression_expand_user_macros                                  *
 *                                                                            *
 * Purpose: expand user macros in trigger expression                          *
 *                                                                            *
 * Parameters: expression - [IN] the expression to expand                     *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: The expanded expression or NULL in the case of error.        *
 *               If NULL is returned the error message is set.                *
 *                                                                            *
 * Comments: The returned expression must be freed by the caller.             *
 *           This function is a locking wrapper of                            *
 *           dc_expression_expand_user_macros() function for external usage.  *
 *                                                                            *
 ******************************************************************************/
char	*DCexpression_expand_user_macros(const char *expression, char **error)
{
	char	*expression_ex;

	LOCK_CACHE;

	expression_ex = dc_expression_expand_user_macros(expression, error);

	UNLOCK_CACHE;

	return expression_ex;
}

/******************************************************************************
 *                                                                            *
 * Function: DCfree_item_queue                                                *
 *                                                                            *
 * Purpose: frees the item queue data vector created by DCget_item_queue()    *
 *                                                                            *
 * Parameters: queue - [IN] the item queue data vector to free                *
 *                                                                            *
 ******************************************************************************/
void	DCfree_item_queue(zbx_vector_ptr_t *queue)
{
	int	i;

	for (i = 0; i < queue->values_num; i++)
		zbx_free(queue->values[i]);
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_item_queue                                                 *
 *                                                                            *
 * Purpose: retrieves vector of delayed items                                 *
 *                                                                            *
 * Parameters: queue - [OUT] the vector of delayed items (optional)           *
 *             from  - [IN] the minimum delay time in seconds (non-negative)  *
 *             to    - [IN] the maximum delay time in seconds or              *
 *                          ZBX_QUEUE_TO_INFINITY if there is no limit        *
 *                                                                            *
 * Return value: the number of delayed items                                  *
 *                                                                            *
 ******************************************************************************/
int	DCget_item_queue(zbx_vector_ptr_t *queue, int from, int to)
{
	zbx_hashset_iter_t	iter;
	const ZBX_DC_ITEM	*dc_item;
	int			now, nitems = 0, data_expected_from, delay;
	zbx_queue_item_t	*queue_item;

	now = time(NULL);

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (dc_item = (ZBX_DC_ITEM *)zbx_hashset_iter_next(&iter)))
	{
		const ZBX_DC_HOST	*dc_host;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (SUCCEED != is_counted_in_item_queue(dc_item->type, dc_item->key))
			continue;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
			continue;

		switch (dc_item->type)
		{
			case ITEM_TYPE_ZABBIX:
				if (HOST_AVAILABLE_TRUE != dc_host->available)
					continue;
				break;
			case ITEM_TYPE_ZABBIX_ACTIVE:
				if (dc_host->data_expected_from > (data_expected_from = dc_item->data_expected_from))
					data_expected_from = dc_host->data_expected_from;
				if (SUCCEED != zbx_interval_preproc(dc_item->delay, &delay, NULL, NULL))
					continue;
				if (data_expected_from + delay > now)
					continue;
				break;
			case ITEM_TYPE_SNMPv1:
			case ITEM_TYPE_SNMPv2c:
			case ITEM_TYPE_SNMPv3:
				if (HOST_AVAILABLE_TRUE != dc_host->snmp_available)
					continue;
				break;
			case ITEM_TYPE_IPMI:
				if (HOST_AVAILABLE_TRUE != dc_host->ipmi_available)
					continue;
				break;
			case ITEM_TYPE_JMX:
				if (HOST_AVAILABLE_TRUE != dc_host->jmx_available)
					continue;
				break;
		}

		if (now - dc_item->nextcheck < from || (ZBX_QUEUE_TO_INFINITY != to && now - dc_item->nextcheck >= to))
			continue;

		if (NULL != queue)
		{
			queue_item = (zbx_queue_item_t *)zbx_malloc(NULL, sizeof(zbx_queue_item_t));
			queue_item->itemid = dc_item->itemid;
			queue_item->type = dc_item->type;
			queue_item->nextcheck = dc_item->nextcheck;
			queue_item->proxy_hostid = dc_host->proxy_hostid;

			zbx_vector_ptr_append(queue, queue_item);
		}
		nitems++;
	}

	UNLOCK_CACHE;

	return nitems;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_trigger_items_hosts_enabled                                   *
 *                                                                            *
 * Purpose: check that functionids in trigger (recovery) expression           *
 *          correspond to enabled items and hosts                             *
 *                                                                            *
 * Parameters: expression - [IN] trigger (recovery) expression                *
 *                                                                            *
 * Return value: SUCCEED - all functionids correspond to enabled items and    *
 *                           enabled hosts                                    *
 *               FAIL    - at least one item or host is disabled              *
 *                                                                            *
 ******************************************************************************/
static int	dc_trigger_items_hosts_enabled(const char *expression)
{
	zbx_uint64_t		functionid;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_FUNCTION	*dc_function;
	const ZBX_DC_HOST	*dc_host;
	const char		*p, *q;

	for (p = expression; '\0' != *p; p++)
	{
		if ('{' != *p)
			continue;

		if ('$' == p[1])
		{
			int	macro_r, context_l, context_r;

			if (SUCCEED == zbx_user_macro_parse(p, &macro_r, &context_l, &context_r))
				p += macro_r;
			else
				p++;

			continue;
		}

		if (NULL == (q = strchr(p + 1, '}')))
			return FAIL;

		if (SUCCEED != is_uint64_n(p + 1, q - p - 1, &functionid))
			continue;

		if (NULL == (dc_function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions, &functionid)) ||
				NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &dc_function->itemid)) ||
				ITEM_STATUS_ACTIVE != dc_item->status ||
				NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)) ||
				HOST_STATUS_MONITORED != dc_host->status)
		{
			return FAIL;
		}

		p = q;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_status_update                                                 *
 *                                                                            *
 * Purpose: check when status information stored in configuration cache was   *
 *          updated last time and update it if necessary                      *
 *                                                                            *
 * Comments: This function gathers the following information:                 *
 *             - number of enabled hosts (total and per proxy)                *
 *             - number of disabled hosts (total and per proxy)               *
 *             - number of enabled and supported items (total, per host and   *
 *                                                                 per proxy) *
 *             - number of enabled and not supported items (total, per host   *
 *                                                             and per proxy) *
 *             - number of disabled items (total and per proxy)               *
 *             - number of enabled triggers with value OK                     *
 *             - number of enabled triggers with value PROBLEM                *
 *             - number of disabled triggers                                  *
 *             - required performance (total and per proxy)                   *
 *           Gathered information can then be displayed in the frontend (see  *
 *           "status.get" request) and used in calculation of zabbix[] items. *
 *                                                                            *
 * NOTE: Always call this function before accessing information stored in     *
 *       config->status as well as host and required performance counters     *
 *       stored in elements of config->proxies and item counters in elements  *
 *       of config->hosts.                                                    *
 *                                                                            *
 ******************************************************************************/
static void	dc_status_update(void)
{
#define ZBX_STATUS_LIFETIME	SEC_PER_MIN

	zbx_hashset_iter_t	iter;
	ZBX_DC_PROXY		*dc_proxy;
	ZBX_DC_HOST		*dc_host, *dc_proxy_host;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_TRIGGER	*dc_trigger;

	if (0 != config->status->last_update && config->status->last_update + ZBX_STATUS_LIFETIME > time(NULL))
		return;

	/* reset global counters */

	config->status->hosts_monitored = 0;
	config->status->hosts_not_monitored = 0;
	config->status->items_active_normal = 0;
	config->status->items_active_notsupported = 0;
	config->status->items_disabled = 0;
	config->status->triggers_enabled_ok = 0;
	config->status->triggers_enabled_problem = 0;
	config->status->triggers_disabled = 0;
	config->status->required_performance = 0.0;

	/* loop over proxies to reset per-proxy host and required performance counters */

	zbx_hashset_iter_reset(&config->proxies, &iter);

	while (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_iter_next(&iter)))
	{
		dc_proxy->hosts_monitored = 0;
		dc_proxy->hosts_not_monitored = 0;
		dc_proxy->required_performance = 0.0;
	}

	/* loop over hosts */

	zbx_hashset_iter_reset(&config->hosts, &iter);

	while (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		/* reset per-host/per-proxy item counters */

		dc_host->items_active_normal = 0;
		dc_host->items_active_notsupported = 0;
		dc_host->items_disabled = 0;

		/* gather per-proxy statistics of enabled and disabled hosts */
		switch (dc_host->status)
		{
			case HOST_STATUS_MONITORED:
				config->status->hosts_monitored++;
				if (0 == dc_host->proxy_hostid)
					break;
				if (NULL == (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &dc_host->proxy_hostid)))
					break;
				dc_proxy->hosts_monitored++;
				break;
			case HOST_STATUS_NOT_MONITORED:
				config->status->hosts_not_monitored++;
				if (0 == dc_host->proxy_hostid)
					break;
				if (NULL == (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &dc_host->proxy_hostid)))
					break;
				dc_proxy->hosts_not_monitored++;
				break;
		}
	}

	/* loop over items to gather per-host and per-proxy statistics */

	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (dc_item = (ZBX_DC_ITEM *)zbx_hashset_iter_next(&iter)))
	{
		dc_proxy = NULL;
		dc_proxy_host = NULL;

		if (ZBX_FLAG_DISCOVERY_NORMAL != dc_item->flags && ZBX_FLAG_DISCOVERY_CREATED != dc_item->flags)
			continue;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (0 != dc_host->proxy_hostid)
		{
			dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &dc_host->proxy_hostid);
			dc_proxy_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_host->proxy_hostid);
		}

		switch (dc_item->status)
		{
			case ITEM_STATUS_ACTIVE:
				if (HOST_STATUS_MONITORED == dc_host->status)
				{
					int	delay;

					if (SUCCEED == zbx_interval_preproc(dc_item->delay, &delay, NULL, NULL) &&
							0 != delay)
					{
						config->status->required_performance += 1.0 / delay;

						if (NULL != dc_proxy)
							dc_proxy->required_performance += 1.0 / delay;
					}

					switch (dc_item->state)
					{
						case ITEM_STATE_NORMAL:
							config->status->items_active_normal++;
							dc_host->items_active_normal++;
							if (NULL != dc_proxy_host)
								dc_proxy_host->items_active_normal++;
							break;
						case ITEM_STATE_NOTSUPPORTED:
							config->status->items_active_notsupported++;
							dc_host->items_active_notsupported++;
							if (NULL != dc_proxy_host)
								dc_proxy_host->items_active_notsupported++;
							break;
						default:
							THIS_SHOULD_NEVER_HAPPEN;
					}

					break;
				}
				/* break; is not missing here, item on disabled host counts as disabled */
			case ITEM_STATUS_DISABLED:
				config->status->items_disabled++;
				if (NULL != dc_proxy_host)
					dc_proxy_host->items_disabled++;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	/* loop over triggers to gather enabled and disabled trigger statistics */

	zbx_hashset_iter_reset(&config->triggers, &iter);

	while (NULL != (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
	{
		switch (dc_trigger->status)
		{
			case TRIGGER_STATUS_ENABLED:
				if (SUCCEED == dc_trigger_items_hosts_enabled(dc_trigger->expression) &&
						(TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION != dc_trigger->recovery_mode ||
						SUCCEED == dc_trigger_items_hosts_enabled(dc_trigger->recovery_expression)))
				{
					switch (dc_trigger->value)
					{
						case TRIGGER_VALUE_OK:
							config->status->triggers_enabled_ok++;
							break;
						case TRIGGER_VALUE_PROBLEM:
							config->status->triggers_enabled_problem++;
							break;
						default:
							THIS_SHOULD_NEVER_HAPPEN;
					}

					break;
				}
				/* break; is not missing here, trigger with disabled items/hosts counts as disabled */
			case TRIGGER_STATUS_DISABLED:
				config->status->triggers_disabled++;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	config->status->last_update = time(NULL);

#undef ZBX_STATUS_LIFETIME
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_item_count                                                 *
 *                                                                            *
 * Purpose: return the number of active items                                 *
 *                                                                            *
 * Parameters: hostid - [IN] the host id, pass 0 to specify all hosts         *
 *                                                                            *
 * Return value: the number of active items                                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_item_count(zbx_uint64_t hostid)
{
	zbx_uint64_t		count;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	dc_status_update();

	if (0 == hostid)
		count = config->status->items_active_normal + config->status->items_active_notsupported;
	else if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
		count = dc_host->items_active_normal + dc_host->items_active_notsupported;
	else
		count = 0;

	UNLOCK_CACHE;

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_item_unsupported_count                                     *
 *                                                                            *
 * Purpose: return the number of active unsupported items                     *
 *                                                                            *
 * Parameters: hostid - [IN] the host id, pass 0 to specify all hosts         *
 *                                                                            *
 * Return value: the number of active unsupported items                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_item_unsupported_count(zbx_uint64_t hostid)
{
	zbx_uint64_t		count;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	dc_status_update();

	if (0 == hostid)
		count = config->status->items_active_notsupported;
	else if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
		count = dc_host->items_active_notsupported;
	else
		count = 0;

	UNLOCK_CACHE;

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_trigger_count                                              *
 *                                                                            *
 * Purpose: count active triggers                                             *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_trigger_count(void)
{
	zbx_uint64_t	count;

	LOCK_CACHE;

	dc_status_update();

	count = config->status->triggers_enabled_ok + config->status->triggers_enabled_problem;

	UNLOCK_CACHE;

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_host_count                                                 *
 *                                                                            *
 * Purpose: count monitored and not monitored hosts                           *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_host_count(void)
{
	zbx_uint64_t	nhosts;

	LOCK_CACHE;

	dc_status_update();

	nhosts = config->status->hosts_monitored;

	UNLOCK_CACHE;

	return nhosts;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_required_performance                                       *
 *                                                                            *
 * Return value: the required nvps number                                     *
 *                                                                            *
 ******************************************************************************/
double	DCget_required_performance(void)
{
	double	nvps;

	LOCK_CACHE;

	dc_status_update();

	nvps = config->status->required_performance;

	UNLOCK_CACHE;

	return nvps;
}

static void	proxy_counter_ui64_push(zbx_vector_ptr_t *vector, zbx_uint64_t proxyid, zbx_uint64_t counter)
{
	zbx_proxy_counter_t	*proxy_counter;

	proxy_counter = (zbx_proxy_counter_t *)zbx_malloc(NULL, sizeof(zbx_proxy_counter_t));
	proxy_counter->proxyid = proxyid;
	proxy_counter->counter_value.ui64 = counter;
	zbx_vector_ptr_append(vector, proxy_counter);
}

static void	proxy_counter_dbl_push(zbx_vector_ptr_t *vector, zbx_uint64_t proxyid, double counter)
{
	zbx_proxy_counter_t	*proxy_counter;

	proxy_counter = (zbx_proxy_counter_t *)zbx_malloc(NULL, sizeof(zbx_proxy_counter_t));
	proxy_counter->proxyid = proxyid;
	proxy_counter->counter_value.dbl = counter;
	zbx_vector_ptr_append(vector, proxy_counter);
}

void	DCget_status(zbx_vector_ptr_t *hosts_monitored, zbx_vector_ptr_t *hosts_not_monitored,
		zbx_vector_ptr_t *items_active_normal, zbx_vector_ptr_t *items_active_notsupported,
		zbx_vector_ptr_t *items_disabled, zbx_uint64_t *triggers_enabled_ok,
		zbx_uint64_t *triggers_enabled_problem, zbx_uint64_t *triggers_disabled,
		zbx_vector_ptr_t *required_performance)
{
	zbx_hashset_iter_t	iter;
	const ZBX_DC_PROXY	*dc_proxy;
	const ZBX_DC_HOST	*dc_proxy_host;

	LOCK_CACHE;

	dc_status_update();

	proxy_counter_ui64_push(hosts_monitored, 0, config->status->hosts_monitored);
	proxy_counter_ui64_push(hosts_not_monitored, 0, config->status->hosts_not_monitored);
	proxy_counter_ui64_push(items_active_normal, 0, config->status->items_active_normal);
	proxy_counter_ui64_push(items_active_notsupported, 0, config->status->items_active_notsupported);
	proxy_counter_ui64_push(items_disabled, 0, config->status->items_disabled);
	*triggers_enabled_ok = config->status->triggers_enabled_ok;
	*triggers_enabled_problem = config->status->triggers_enabled_problem;
	*triggers_disabled = config->status->triggers_disabled;
	proxy_counter_dbl_push(required_performance, 0, config->status->required_performance);

	zbx_hashset_iter_reset(&config->proxies, &iter);

	while (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_iter_next(&iter)))
	{
		proxy_counter_ui64_push(hosts_monitored, dc_proxy->hostid, dc_proxy->hosts_monitored);
		proxy_counter_ui64_push(hosts_not_monitored, dc_proxy->hostid, dc_proxy->hosts_not_monitored);

		if (NULL != (dc_proxy_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_proxy->hostid)))
		{
			proxy_counter_ui64_push(items_active_normal, dc_proxy->hostid,
					dc_proxy_host->items_active_normal);
			proxy_counter_ui64_push(items_active_notsupported, dc_proxy->hostid,
					dc_proxy_host->items_active_notsupported);
			proxy_counter_ui64_push(items_disabled, dc_proxy->hostid, dc_proxy_host->items_disabled);
		}

		proxy_counter_dbl_push(required_performance, dc_proxy->hostid, dc_proxy->required_performance);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_expressions_by_names                                       *
 *                                                                            *
 * Purpose: retrieves global expression data from cache                       *
 *                                                                            *
 * Parameters: expressions  - [OUT] a vector of expression data pointers      *
 *             names        - [IN] a vector containing expression names       *
 *             names_num    - [IN] the number of items in names vector        *
 *                                                                            *
 * Comment: The expressions vector contains allocated data, which must be     *
 *          freed afterwards with zbx_regexp_clean_expressions() function.    *
 *                                                                            *
 ******************************************************************************/
void	DCget_expressions_by_names(zbx_vector_ptr_t *expressions, const char * const *names, int names_num)
{
	int			i, iname;
	ZBX_DC_EXPRESSION	*expression;
	ZBX_DC_REGEXP		*regexp, search_regexp;

	LOCK_CACHE;

	for (iname = 0; iname < names_num; iname++)
	{
		search_regexp.name = names[iname];

		if (NULL != (regexp = (ZBX_DC_REGEXP *)zbx_hashset_search(&config->regexps, &search_regexp)))
		{
			for (i = 0; i < regexp->expressionids.values_num; i++)
			{
				zbx_uint64_t		expressionid = regexp->expressionids.values[i];
				zbx_expression_t	*rxp;

				if (NULL == (expression = (ZBX_DC_EXPRESSION *)zbx_hashset_search(&config->expressions, &expressionid)))
					continue;

				rxp = (zbx_expression_t *)zbx_malloc(NULL, sizeof(zbx_expression_t));
				rxp->name = zbx_strdup(NULL, regexp->name);
				rxp->expression = zbx_strdup(NULL, expression->expression);
				rxp->exp_delimiter = expression->delimiter;
				rxp->case_sensitive = expression->case_sensitive;
				rxp->expression_type = expression->type;

				zbx_vector_ptr_append(expressions, rxp);
			}
		}
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_expression                                                 *
 *                                                                            *
 * Purpose: retrieves regular expression data from cache                      *
 *                                                                            *
 * Parameters: expressions  - [OUT] a vector of expression data pointers      *
 *             name         - [IN] the regular expression name                *
 *                                                                            *
 * Comment: The expressions vector contains allocated data, which must be     *
 *          freed afterwards with zbx_regexp_clean_expressions() function.    *
 *                                                                            *
 ******************************************************************************/
void	DCget_expressions_by_name(zbx_vector_ptr_t *expressions, const char *name)
{
	DCget_expressions_by_names(expressions, &name, 1);
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_data_expected_from                                         *
 *                                                                            *
 * Purpose: Returns time since which data is expected for the given item. We  *
 *          would not mind not having data for the item before that time, but *
 *          since that time we expect data to be coming.                      *
 *                                                                            *
 * Parameters: itemid  - [IN] the item id                                     *
 *             seconds - [OUT] the time data is expected as a Unix timestamp  *
 *                                                                            *
 ******************************************************************************/
int	DCget_data_expected_from(zbx_uint64_t itemid, int *seconds)
{
	ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST	*dc_host;
	int		ret = FAIL;

	LOCK_CACHE;

	if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
		goto unlock;

	if (ITEM_STATUS_ACTIVE != dc_item->status)
		goto unlock;

	if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		goto unlock;

	if (HOST_STATUS_MONITORED != dc_host->status)
		goto unlock;

	*seconds = MAX(dc_item->data_expected_from, dc_host->data_expected_from);

	ret = SUCCEED;
unlock:
	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_get_hostids_by_functionids                                    *
 *                                                                            *
 * Purpose: get host identifiers for the specified list of functions          *
 *                                                                            *
 * Parameters: functionids     - [IN] the function ids                        *
 *             functionids_num - [IN] the number of function ids              *
 *             hostids         - [OUT] the host ids                           *
 *                                                                            *
 * Comments: this function must be used only by configuration syncer          *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_hostids_by_functionids(const zbx_uint64_t *functionids, int functionids_num,
		zbx_vector_uint64_t *hostids)
{
	ZBX_DC_FUNCTION	*function;
	ZBX_DC_ITEM	*item;
	int		i;

	for (i = 0; i < functionids_num; i++)
	{
		if (NULL == (function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions, &functionids[i])))
				continue;

		if (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &function->itemid)))
			zbx_vector_uint64_append(hostids, item->hostid);
	}

	zbx_vector_uint64_sort(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_hostids_by_functionids                                     *
 *                                                                            *
 * Purpose: get function host ids grouped by an object (trigger) id           *
 *                                                                            *
 * Parameters: functionids - [IN] the function ids                            *
 *             hostids     - [OUT] the host ids                               *
 *                                                                            *
 ******************************************************************************/
void	DCget_hostids_by_functionids(zbx_vector_uint64_t *functionids, zbx_vector_uint64_t *hostids)
{
	const char	*__function_name = "DCget_hostids_by_functionids";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_CACHE;

	zbx_dc_get_hostids_by_functionids(functionids->values, functionids->values_num, hostids);

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): found %d hosts", __function_name, hostids->values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_config_get                                                   *
 *                                                                            *
 * Purpose: get global configuration data                                     *
 *                                                                            *
 * Parameters: cfg   - [OUT] the global configuration data                    *
 *             flags - [IN] the flags specifying fields to set,               *
 *                          see ZBX_CONFIG_FLAGS_ defines                     *
 *                                                                            *
 * Comments: It's recommended to cleanup this structure with zbx_config_clean *
 *           function even if only simple fields are requested.               *
 *                                                                            *
 ******************************************************************************/
void	zbx_config_get(zbx_config_t *cfg, zbx_uint64_t flags)
{
	LOCK_CACHE;

	if (0 != (flags & ZBX_CONFIG_FLAGS_SEVERITY_NAME))
	{
		int	i;

		cfg->severity_name = (char **)zbx_malloc(NULL, TRIGGER_SEVERITY_COUNT * sizeof(char *));

		for (i = 0; i < TRIGGER_SEVERITY_COUNT; i++)
			cfg->severity_name[i] = zbx_strdup(NULL, config->config->severity_name[i]);
	}

	if (0 != (flags & ZBX_CONFIG_FLAGS_DISCOVERY_GROUPID))
		cfg->discovery_groupid = config->config->discovery_groupid;

	if (0 != (flags & ZBX_CONFIG_FLAGS_DEFAULT_INVENTORY_MODE))
		cfg->default_inventory_mode = config->config->default_inventory_mode;

	if (0 != (flags & ZBX_CONFIG_FLAGS_REFRESH_UNSUPPORTED))
		cfg->refresh_unsupported = config->config->refresh_unsupported;

	if (0 != (flags & ZBX_CONFIG_FLAGS_SNMPTRAP_LOGGING))
		cfg->snmptrap_logging = config->config->snmptrap_logging;

	if (0 != (flags & ZBX_CONFIG_FLAGS_HOUSEKEEPER))
		cfg->hk = config->config->hk;

	UNLOCK_CACHE;

	cfg->flags = flags;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_config_clean                                                 *
 *                                                                            *
 * Purpose: cleans global configuration data structure filled                 *
 *          by zbx_config_get() function                                      *
 *                                                                            *
 * Parameters: cfg   - [IN] the global configuration data                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_config_clean(zbx_config_t *cfg)
{
	if (0 != (cfg->flags & ZBX_CONFIG_FLAGS_SEVERITY_NAME))
	{
		int	i;

		for (i = 0; i < TRIGGER_SEVERITY_COUNT; i++)
			zbx_free(cfg->severity_name[i]);

		zbx_free(cfg->severity_name);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCreset_hosts_availability                                       *
 *                                                                            *
 * Purpose: resets host availability for disabled hosts and hosts without     *
 *          enabled items for the corresponding interface                     *
 *                                                                            *
 * Parameters: hosts - [OUT] changed host availability data                   *
 *                                                                            *
 * Return value: SUCCEED - host availability was reset for at least one host  *
 *               FAIL    - no hosts required availability reset               *
 *                                                                            *
 * Comments: This function resets host availability in configuration cache.   *
 *           The caller must perform corresponding database updates based     *
 *           on returned host availability reset data. On server the function *
 *           skips hosts handled by proxies.                                  *
 *                                                                            *
 ******************************************************************************/
int	DCreset_hosts_availability(zbx_vector_ptr_t *hosts)
{
	const char		*__function_name = "DCreset_hosts_availability";
	ZBX_DC_HOST		*host;
	zbx_hashset_iter_t	iter;
	zbx_host_availability_t	*ha = NULL;
	int			now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->hosts, &iter);

	while (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		int	items_num = 0, snmp_items_num = 0, ipmi_items_num = 0, jmx_items_num = 0;

		/* On server skip hosts handled by proxies. They are handled directly */
		/* when receiving hosts' availability data from proxies.              */
		/* Unless a host was just (re)assigned to a proxy or the proxy has    */
		/* not updated its status during the maximum proxy heartbeat period.  */
		/* In this case reset all interfaces to unknown status.               */
		if (0 == host->reset_availability &&
				0 != (program_type & ZBX_PROGRAM_TYPE_SERVER) && 0 != host->proxy_hostid)
		{
			ZBX_DC_PROXY	*proxy;

			if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &host->proxy_hostid)))
			{
				/* SEC_PER_MIN is a tolerance interval, it was chosen arbitrarily */
				if (ZBX_PROXY_HEARTBEAT_FREQUENCY_MAX + SEC_PER_MIN >= now - proxy->lastaccess)
					continue;
			}

			host->reset_availability = 1;
		}

		if (NULL == ha)
			ha = (zbx_host_availability_t *)zbx_malloc(NULL, sizeof(zbx_host_availability_t));

		zbx_host_availability_init(ha, host->hostid);

		if (0 == host->reset_availability)
		{
			items_num = host->items_num;
			snmp_items_num = host->snmp_items_num;
			ipmi_items_num = host->ipmi_items_num;
			jmx_items_num = host->jmx_items_num;
		}

		if (0 == items_num && HOST_AVAILABLE_UNKNOWN != host->available)
			zbx_agent_availability_init(&ha->agents[ZBX_AGENT_ZABBIX], HOST_AVAILABLE_UNKNOWN, "", 0, 0);

		if (0 == snmp_items_num && HOST_AVAILABLE_UNKNOWN != host->snmp_available)
			zbx_agent_availability_init(&ha->agents[ZBX_AGENT_SNMP], HOST_AVAILABLE_UNKNOWN, "", 0, 0);

		if (0 == ipmi_items_num && HOST_AVAILABLE_UNKNOWN != host->ipmi_available)
			zbx_agent_availability_init(&ha->agents[ZBX_AGENT_IPMI], HOST_AVAILABLE_UNKNOWN, "", 0, 0);

		if (0 == jmx_items_num && HOST_AVAILABLE_UNKNOWN != host->jmx_available)
			zbx_agent_availability_init(&ha->agents[ZBX_AGENT_JMX], HOST_AVAILABLE_UNKNOWN, "", 0, 0);

		if (SUCCEED == zbx_host_availability_is_set(ha))
		{
			if (SUCCEED == DChost_set_availability(host, now, ha))
			{
				zbx_vector_ptr_append(hosts, ha);
				ha = NULL;
			}
			else
				zbx_host_availability_clean(ha);
		}

		host->reset_availability = 0;
	}
	UNLOCK_CACHE;

	zbx_free(ha);

	zbx_vector_ptr_sort(hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() hosts:%d", __function_name, hosts->values_num);

	return 0 == hosts->values_num ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_hosts_availability                                         *
 *                                                                            *
 * Purpose: gets availability data for hosts with availability data changed   *
 *          in period from last availability update to the specified          *
 *          timestamp                                                         *
 *                                                                            *
 * Parameters: hosts - [OUT] changed host availability data                   *
 *             ts    - [OUT] the availability diff timestamp                  *
 *                                                                            *
 * Return value: SUCCEED - availability was changed for at least one host     *
 *               FAIL    - no host availability was changed                   *
 *                                                                            *
 ******************************************************************************/
int	DCget_hosts_availability(zbx_vector_ptr_t *hosts, int *ts)
{
	const char		*__function_name = "DCget_hosts_availability";
	ZBX_DC_HOST		*host;
	zbx_hashset_iter_t	iter;
	zbx_host_availability_t	*ha = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_CACHE;

	*ts = time(NULL);

	zbx_hashset_iter_reset(&config->hosts, &iter);

	while (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		if (config->availability_diff_ts <= host->availability_ts && host->availability_ts < *ts)
		{
			ha = (zbx_host_availability_t *)zbx_malloc(NULL, sizeof(zbx_host_availability_t));
			zbx_host_availability_init(ha, host->hostid);

			zbx_agent_availability_init(&ha->agents[ZBX_AGENT_ZABBIX], host->available, host->error,
					host->errors_from, host->disable_until);
			zbx_agent_availability_init(&ha->agents[ZBX_AGENT_SNMP], host->snmp_available, host->snmp_error,
					host->snmp_errors_from, host->snmp_disable_until);
			zbx_agent_availability_init(&ha->agents[ZBX_AGENT_IPMI], host->ipmi_available, host->ipmi_error,
					host->ipmi_errors_from, host->ipmi_disable_until);
			zbx_agent_availability_init(&ha->agents[ZBX_AGENT_JMX], host->jmx_available, host->jmx_error,
					host->jmx_errors_from, host->jmx_disable_until);

			zbx_vector_ptr_append(hosts, ha);
		}
	}

	UNLOCK_CACHE;

	zbx_vector_ptr_sort(hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() hosts:%d", __function_name, hosts->values_num);

	return 0 == hosts->values_num ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_condition_clean                                            *
 *                                                                            *
 * Purpose: cleans condition data structure                                    *
 *                                                                            *
 * Parameters: condition - [IN] the condition data to free                    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_db_condition_clean(DB_CONDITION *condition)
{
	zbx_free(condition->value2);
	zbx_free(condition->value);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_conditions_eval_clean                                        *
 *                                                                            *
 * Purpose: cleans condition data structures from hashset                     *
 *                                                                            *
 * Parameters: uniq_conditions - [IN] hashset with data structures to clean   *
 *                                                                            *
 ******************************************************************************/
void	zbx_conditions_eval_clean(zbx_hashset_t *uniq_conditions)
{
	zbx_hashset_iter_t	iter;
	DB_CONDITION		*condition;

	zbx_hashset_iter_reset(uniq_conditions, &iter);

	while (NULL != (condition = (DB_CONDITION *)zbx_hashset_iter_next(&iter)))
		zbx_db_condition_clean(condition);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_action_eval_free                                             *
 *                                                                            *
 * Purpose: frees action evaluation data structure                            *
 *                                                                            *
 * Parameters: action - [IN] the action evaluation to free                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_action_eval_free(zbx_action_eval_t *action)
{
	zbx_free(action->formula);

	zbx_vector_ptr_destroy(&action->conditions);

	zbx_free(action);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_action_copy_conditions                                        *
 *                                                                            *
 * Purpose: copies configuration cache action conditions to the specified     *
 *          vector                                                            *
 *                                                                            *
 * Parameters: dc_action  - [IN] the source action                            *
 *             conditions - [OUT] the conditions vector                       *
 *                                                                            *
 ******************************************************************************/
static void	dc_action_copy_conditions(const zbx_dc_action_t *dc_action, zbx_vector_ptr_t *conditions)
{
	int				i;
	DB_CONDITION			*condition;
	zbx_dc_action_condition_t	*dc_condition;

	zbx_vector_ptr_reserve(conditions, dc_action->conditions.values_num);

	for (i = 0; i < dc_action->conditions.values_num; i++)
	{
		dc_condition = (zbx_dc_action_condition_t *)dc_action->conditions.values[i];

		condition = (DB_CONDITION *)zbx_malloc(NULL, sizeof(DB_CONDITION));

		condition->conditionid = dc_condition->conditionid;
		condition->actionid = dc_action->actionid;
		condition->conditiontype = dc_condition->conditiontype;
		condition->op = dc_condition->op;
		condition->value = zbx_strdup(NULL, dc_condition->value);
		condition->value2 = zbx_strdup(NULL, dc_condition->value2);

		zbx_vector_ptr_append(conditions, condition);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dc_action_eval_create                                            *
 *                                                                            *
 * Purpose: creates action evaluation data from configuration cache action    *
 *                                                                            *
 * Parameters: dc_action - [IN] the source action                             *
 *                                                                            *
 * Return value: the action evaluation data                                   *
 *                                                                            *
 * Comments: The returned value must be freed with zbx_action_eval_free()     *
 *           function later.                                                  *
 *                                                                            *
 ******************************************************************************/
static zbx_action_eval_t	*dc_action_eval_create(const zbx_dc_action_t *dc_action)
{
	zbx_action_eval_t		*action;

	action = (zbx_action_eval_t *)zbx_malloc(NULL, sizeof(zbx_action_eval_t));

	action->actionid = dc_action->actionid;
	action->eventsource = dc_action->eventsource;
	action->evaltype = dc_action->evaltype;
	action->opflags = dc_action->opflags;
	action->formula = zbx_strdup(NULL, dc_action->formula);
	zbx_vector_ptr_create(&action->conditions);

	dc_action_copy_conditions(dc_action, &action->conditions);

	return action;
}

/******************************************************************************
 *                                                                            *
 * Function: prepare_actions_eval                                             *
 *                                                                            *
 * Purpose: make actions to point, to conditions from hashset, where all      *
 *          conditions are unique, this ensures that we don't double check    *
 *          same conditions.                                                  *
 *                                                                            *
 * Parameters: actions         - [IN/OUT] all conditions are added to hashset *
 *                                        then cleaned, actions will now      *
 *                                        point to conditions from hashset.   *
 *                                        for custom expression also          *
 *                                        replaces formula                    *
 *             uniq_conditions - [OUT]    unique conditions that actions      *
 *                                        point to (several sources)          *
 *                                                                            *
 * Comments: The returned conditions must be freed with                       *
 *           zbx_conditions_eval_clean() function later.                      *
 *                                                                            *
 ******************************************************************************/
static void	prepare_actions_eval(zbx_vector_ptr_t *actions, zbx_hashset_t *uniq_conditions)
{
	int	i, j;

	for (i = 0; i < actions->values_num; i++)
	{
		zbx_action_eval_t	*action = (zbx_action_eval_t *)actions->values[i];

		for (j = 0; j < action->conditions.values_num; j++)
		{
			DB_CONDITION	*uniq_condition = NULL, *condition = (DB_CONDITION *)action->conditions.values[j];

			if (EVENT_SOURCE_COUNT <= action->eventsource)
			{
				zbx_db_condition_clean(condition);
			}
			else if (NULL == (uniq_condition = (DB_CONDITION *)zbx_hashset_search(&uniq_conditions[action->eventsource],
					condition)))
			{
				uniq_condition = (DB_CONDITION *)zbx_hashset_insert(&uniq_conditions[action->eventsource],
						condition, sizeof(DB_CONDITION));
			}
			else
			{
				if (CONDITION_EVAL_TYPE_EXPRESSION == action->evaltype)
				{
					char	search[ZBX_MAX_UINT64_LEN + 2];
					char	replace[ZBX_MAX_UINT64_LEN + 2];
					char	*old_formula;

					zbx_snprintf(search, sizeof(search), "{" ZBX_FS_UI64 "}",
							condition->conditionid);
					zbx_snprintf(replace, sizeof(replace), "{" ZBX_FS_UI64 "}",
							uniq_condition->conditionid);

					old_formula = action->formula;
					action->formula = string_replace(action->formula, search, replace);
					zbx_free(old_formula);
				}

				zbx_db_condition_clean(condition);
			}

			zbx_free(action->conditions.values[j]);
			action->conditions.values[j] = uniq_condition;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_get_actions_eval                                          *
 *                                                                            *
 * Purpose: gets action evaluation data                                       *
 *                                                                            *
 * Parameters: actions         - [OUT] the action evaluation data             *
 *             uniq_conditions - [OUT] unique conditions that actions         *
 *                                     point to (several sources)             *
 *             opflags         - [IN] flags specifying which actions to get   *
 *                                    based on their operation classes        *
 *                                    (see ZBX_ACTION_OPCLASS_* defines)      *
 *                                                                            *
 * Comments: The returned actions and conditions must be freed with           *
 *           zbx_action_eval_free() and zbx_conditions_eval_clean()           *
 *           functions later.                                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_actions_eval(zbx_vector_ptr_t *actions, zbx_hashset_t *uniq_conditions, unsigned char opflags)
{
	const char			*__function_name = "zbx_dc_get_actions_eval";
	zbx_dc_action_t			*dc_action;
	zbx_hashset_iter_t		iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->actions, &iter);

	while (NULL != (dc_action = (zbx_dc_action_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 != (opflags & dc_action->opflags))
			zbx_vector_ptr_append(actions, dc_action_eval_create(dc_action));
	}

	UNLOCK_CACHE;

	prepare_actions_eval(actions, uniq_conditions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() actions:%d", __function_name, actions->values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_set_availability_update_ts                                   *
 *                                                                            *
 * Purpose: sets timestamp of the last availability update                    *
 *                                                                            *
 * Parameter: ts - [IN] the last availability update timestamp                *
 *                                                                            *
 * Comments: This function is used only by proxies when preparing host        *
 *           availability data to be sent to server.                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_set_availability_diff_ts(int ts)
{
	/* this data can't be accessed simultaneously from multiple processes - locking is not necessary */
	config->availability_diff_ts = ts;
}

/******************************************************************************
 *                                                                            *
 * Function: corr_condition_clean                                             *
 *                                                                            *
 * Purpose: frees correlation condition                                       *
 *                                                                            *
 * Parameter: condition - [IN] the condition to free                          *
 *                                                                            *
 ******************************************************************************/
static void	corr_condition_clean(zbx_corr_condition_t *condition)
{
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			zbx_free(condition->data.tag.tag);
			break;
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			zbx_free(condition->data.tag_pair.oldtag);
			zbx_free(condition->data.tag_pair.newtag);
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			zbx_free(condition->data.tag_value.tag);
			zbx_free(condition->data.tag_value.value);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dc_correlation_free                                              *
 *                                                                            *
 * Purpose: frees global correlation rule                                     *
 *                                                                            *
 * Parameter: condition - [IN] the condition to free                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_correlation_free(zbx_correlation_t *correlation)
{
	zbx_free(correlation->name);
	zbx_free(correlation->formula);

	zbx_vector_ptr_clear_ext(&correlation->operations, zbx_ptr_free);
	zbx_vector_ptr_destroy(&correlation->operations);
	zbx_vector_ptr_destroy(&correlation->conditions);

	zbx_free(correlation);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_corr_condition_copy                                           *
 *                                                                            *
 * Purpose: copies cached correlation condition to memory                     *
 *                                                                            *
 * Parameter: dc_condition - [IN] the condition to copy                       *
 *            condition    - [OUT] the destination condition                  *
 *                                                                            *
 * Return value: The cloned correlation condition.                            *
 *                                                                            *
 ******************************************************************************/
static void	dc_corr_condition_copy(const zbx_dc_corr_condition_t *dc_condition, zbx_corr_condition_t *condition)
{
	condition->type = dc_condition->type;

	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			condition->data.tag.tag = zbx_strdup(NULL, dc_condition->data.tag.tag);
			break;
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			condition->data.tag_pair.oldtag = zbx_strdup(NULL, dc_condition->data.tag_pair.oldtag);
			condition->data.tag_pair.newtag = zbx_strdup(NULL, dc_condition->data.tag_pair.newtag);
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			condition->data.tag_value.tag = zbx_strdup(NULL, dc_condition->data.tag_value.tag);
			condition->data.tag_value.value = zbx_strdup(NULL, dc_condition->data.tag_value.value);
			condition->data.tag_value.op = dc_condition->data.tag_value.op;
			break;
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			condition->data.group.groupid = dc_condition->data.group.groupid;
			condition->data.group.op = dc_condition->data.group.op;
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_corr_operation_dup                                        *
 *                                                                            *
 * Purpose: clones cached correlation operation to memory                     *
 *                                                                            *
 * Parameter: operation - [IN] the operation to clone                         *
 *                                                                            *
 * Return value: The cloned correlation operation.                            *
 *                                                                            *
 ******************************************************************************/
static zbx_corr_operation_t	*zbx_dc_corr_operation_dup(zbx_dc_corr_operation_t *dc_operation)
{
	zbx_corr_operation_t	*operation;

	operation = (zbx_corr_operation_t *)zbx_malloc(NULL, sizeof(zbx_corr_operation_t));
	operation->type = dc_operation->type;

	return operation;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_correlation_formula_dup                                       *
 *                                                                            *
 * Purpose: clones cached correlation formula, generating it if necessary     *
 *                                                                            *
 * Parameter: correlation - [IN] the correlation                              *
 *                                                                            *
 * Return value: The cloned correlation formula.                              *
 *                                                                            *
 ******************************************************************************/
static char	*dc_correlation_formula_dup(const zbx_dc_correlation_t *dc_correlation)
{
#define ZBX_OPERATION_TYPE_UNKNOWN	0
#define ZBX_OPERATION_TYPE_OR		1
#define ZBX_OPERATION_TYPE_AND		2

	char				*formula = NULL;
	const char			*op = NULL;
	size_t				formula_alloc = 0, formula_offset = 0;
	int				i, last_type = -1, last_op = ZBX_OPERATION_TYPE_UNKNOWN;
	const zbx_dc_corr_condition_t	*dc_condition;
	zbx_uint64_t			last_id;

	if (CONDITION_EVAL_TYPE_EXPRESSION == dc_correlation->evaltype || 0 == dc_correlation->conditions.values_num)
		return zbx_strdup(NULL, dc_correlation->formula);

	dc_condition = (const zbx_dc_corr_condition_t *)dc_correlation->conditions.values[0];

	switch (dc_correlation->evaltype)
	{
		case CONDITION_EVAL_TYPE_OR:
			op = " or";
			break;
		case CONDITION_EVAL_TYPE_AND:
			op = " and";
			break;
	}

	if (NULL != op)
	{
		zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, "{" ZBX_FS_UI64 "}",
				dc_condition->corr_conditionid);

		for (i = 1; i < dc_correlation->conditions.values_num; i++)
		{
			dc_condition = (const zbx_dc_corr_condition_t *)dc_correlation->conditions.values[i];

			zbx_strcpy_alloc(&formula, &formula_alloc, &formula_offset, op);
			zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, " {" ZBX_FS_UI64 "}",
					dc_condition->corr_conditionid);
		}

		return formula;
	}

	last_id = dc_condition->corr_conditionid;
	last_type = dc_condition->type;

	for (i = 1; i < dc_correlation->conditions.values_num; i++)
	{
		dc_condition = (const zbx_dc_corr_condition_t *)dc_correlation->conditions.values[i];

		if (last_type == dc_condition->type)
		{
			if (last_op != ZBX_OPERATION_TYPE_OR)
				zbx_chrcpy_alloc(&formula, &formula_alloc, &formula_offset, '(');

			zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, "{" ZBX_FS_UI64 "} or ", last_id);
			last_op = ZBX_OPERATION_TYPE_OR;
		}
		else
		{
			zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, "{" ZBX_FS_UI64 "}", last_id);

			if (last_op == ZBX_OPERATION_TYPE_OR)
				zbx_chrcpy_alloc(&formula, &formula_alloc, &formula_offset, ')');

			zbx_strcpy_alloc(&formula, &formula_alloc, &formula_offset, " and ");

			last_op = ZBX_OPERATION_TYPE_AND;
		}

		last_type = dc_condition->type;
		last_id = dc_condition->corr_conditionid;
	}

	zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, "{" ZBX_FS_UI64 "}", last_id);

	if (last_op == ZBX_OPERATION_TYPE_OR)
		zbx_chrcpy_alloc(&formula, &formula_alloc, &formula_offset, ')');

	return formula;

#undef ZBX_OPERATION_TYPE_UNKNOWN
#undef ZBX_OPERATION_TYPE_OR
#undef ZBX_OPERATION_TYPE_AND
}

void	zbx_dc_correlation_rules_init(zbx_correlation_rules_t *rules)
{
	zbx_vector_ptr_create(&rules->correlations);
	zbx_hashset_create_ext(&rules->conditions, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			(zbx_clean_func_t)corr_condition_clean, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	rules->sync_ts = 0;
}

void	zbx_dc_correlation_rules_clean(zbx_correlation_rules_t *rules)
{
	zbx_vector_ptr_clear_ext(&rules->correlations, (zbx_clean_func_t)dc_correlation_free);
	zbx_hashset_clear(&rules->conditions);
}

void	zbx_dc_correlation_rules_free(zbx_correlation_rules_t *rules)
{
	zbx_dc_correlation_rules_clean(rules);
	zbx_vector_ptr_destroy(&rules->correlations);
	zbx_hashset_destroy(&rules->conditions);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_correlation_get_rules                                     *
 *                                                                            *
 * Purpose: gets correlation rules from configuration cache                   *
 *                                                                            *
 * Parameter: rules   - [IN/OUT] the correlation rules                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_correlation_rules_get(zbx_correlation_rules_t *rules)
{
	int			i;
	zbx_hashset_iter_t	iter;
	zbx_dc_correlation_t	*dc_correlation;
	zbx_dc_corr_condition_t	*dc_condition;
	zbx_correlation_t	*correlation;
	zbx_corr_condition_t	*condition, condition_local;

	LOCK_CACHE;

	/* The correlation rules are refreshed only if the sync timestamp   */
	/* does not match current configuration cache sync timestamp. This  */
	/* allows to locally cache the correlation rules.                   */
	if (config->sync_ts == rules->sync_ts)
	{
		UNLOCK_CACHE;
		return;
	}

	zbx_dc_correlation_rules_clean(rules);

	zbx_hashset_iter_reset(&config->correlations, &iter);
	while (NULL != (dc_correlation = (zbx_dc_correlation_t *)zbx_hashset_iter_next(&iter)))
	{
		correlation = (zbx_correlation_t *)zbx_malloc(NULL, sizeof(zbx_correlation_t));
		correlation->correlationid = dc_correlation->correlationid;
		correlation->evaltype = dc_correlation->evaltype;
		correlation->name = zbx_strdup(NULL, dc_correlation->name);
		correlation->formula = dc_correlation_formula_dup(dc_correlation);
		zbx_vector_ptr_create(&correlation->conditions);
		zbx_vector_ptr_create(&correlation->operations);

		for (i = 0; i < dc_correlation->conditions.values_num; i++)
		{
			dc_condition = (zbx_dc_corr_condition_t *)dc_correlation->conditions.values[i];
			condition_local.corr_conditionid = dc_condition->corr_conditionid;
			condition = (zbx_corr_condition_t *)zbx_hashset_insert(&rules->conditions, &condition_local, sizeof(condition_local));
			dc_corr_condition_copy(dc_condition, condition);
			zbx_vector_ptr_append(&correlation->conditions, condition);
		}

		for (i = 0; i < dc_correlation->operations.values_num; i++)
		{
			zbx_vector_ptr_append(&correlation->operations,
					zbx_dc_corr_operation_dup((zbx_dc_corr_operation_t *)dc_correlation->operations.values[i]));
		}

		zbx_vector_ptr_append(&rules->correlations, correlation);
	}

	rules->sync_ts = config->sync_ts;

	UNLOCK_CACHE;

	zbx_vector_ptr_sort(&rules->correlations, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_get_nested_hostgroupids                                       *
 *                                                                            *
 * Purpose: gets nested group ids for the specified host group                *
 *          (including the target group id)                                   *
 *                                                                            *
 * Parameter: groupid         - [IN] the parent group identifier              *
 *            nested_groupids - [OUT] the nested + parent group ids           *
 *                                                                            *
 ******************************************************************************/
static void	dc_get_nested_hostgroupids(zbx_uint64_t groupid, zbx_vector_uint64_t *nested_groupids)
{
	zbx_dc_hostgroup_t	*parent_group, *group;

	zbx_vector_uint64_append(nested_groupids, groupid);

	/* The target group id will not be found in the configuration cache if target group was removed */
	/* between call to this function and the configuration cache look-up below. The target group id */
	/* is nevertheless returned so that the SELECT statements of the callers work even if no group  */
	/* was found.                                                                                   */

	if (NULL != (parent_group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups, &groupid)))
	{
		if (0 == (parent_group->flags & ZBX_DC_HOSTGROUP_FLAGS_NESTED_GROUPIDS))
		{
			int	index, len;

			zbx_vector_uint64_create_ext(&parent_group->nested_groupids, __config_mem_malloc_func,
					__config_mem_realloc_func, __config_mem_free_func);

			index = zbx_vector_ptr_bsearch(&config->hostgroups_name, parent_group, dc_compare_hgroups);
			len = strlen(parent_group->name);

			while (++index < config->hostgroups_name.values_num)
			{
				group = (zbx_dc_hostgroup_t *)config->hostgroups_name.values[index];

				if (0 != strncmp(group->name, parent_group->name, len))
					break;

				if ('\0' == group->name[len] || '/' == group->name[len])
					zbx_vector_uint64_append(&parent_group->nested_groupids, group->groupid);
			}

			parent_group->flags |= ZBX_DC_HOSTGROUP_FLAGS_NESTED_GROUPIDS;
		}

		zbx_vector_uint64_append_array(nested_groupids, parent_group->nested_groupids.values,
				parent_group->nested_groupids.values_num);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_get_nested_hostgroupids                                   *
 *                                                                            *
 * Purpose: gets nested group ids for the specified host groups               *
 *                                                                            *
 * Parameter: groupids        - [IN] the parent group identifiers             *
 *            groupids_num    - [IN] the number of parent groups              *
 *            nested_groupids - [OUT] the nested + parent group ids           *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_nested_hostgroupids(zbx_uint64_t *groupids, int groupids_num, zbx_vector_uint64_t *nested_groupids)
{
	int	i;

	LOCK_CACHE;

	for (i = 0; i < groupids_num; i++)
		dc_get_nested_hostgroupids(groupids[i], nested_groupids);

	UNLOCK_CACHE;

	zbx_vector_uint64_sort(nested_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(nested_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_get_nested_hostgroupids_by_names                          *
 *                                                                            *
 * Purpose: gets nested group ids for the specified host groups               *
 *                                                                            *
 * Parameter: names           - [IN] the parent group names                   *
 *            names_num       - [IN] the number of parent groups              *
 *            nested_groupids - [OUT] the nested + parent group ids           *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_nested_hostgroupids_by_names(char **names, int names_num, zbx_vector_uint64_t *nested_groupids)
{
	int	i, index;

	LOCK_CACHE;

	for (i = 0; i < names_num; i++)
	{
		zbx_dc_hostgroup_t	group_local, *group;

		group_local.name = names[i];

		if (FAIL != (index = zbx_vector_ptr_bsearch(&config->hostgroups_name, &group_local,
				dc_compare_hgroups)))
		{
			group = (zbx_dc_hostgroup_t *)config->hostgroups_name.values[index];
			dc_get_nested_hostgroupids(group->groupid, nested_groupids);
		}
	}

	UNLOCK_CACHE;

	zbx_vector_uint64_sort(nested_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(nested_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_get_active_proxy_by_name                                  *
 *                                                                            *
 * Purpose: gets active proxy data by its name from configuration cache       *
 *                                                                            *
 * Parameters:                                                                *
 *     name  - [IN] the proxy name                                            *
 *     proxy - [OUT] the proxy data                                           *
 *     error - [OUT] error message                                            *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - proxy data were retrieved successfully                       *
 *     FAIL    - failed to retrieve proxy data, error message is set          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_active_proxy_by_name(const char *name, DC_PROXY *proxy, char **error)
{
	int		ret = FAIL;
	ZBX_DC_HOST	*dc_host;
	ZBX_DC_PROXY	*dc_proxy;

	LOCK_CACHE;

	if (NULL == (dc_host = DCfind_proxy(name)))
	{
		*error = zbx_dsprintf(*error, "proxy \"%s\" not found", name);
		goto out;
	}

	if (HOST_STATUS_PROXY_ACTIVE != dc_host->status)
	{
		*error = zbx_dsprintf(*error, "proxy \"%s\" is configured for passive mode", name);
		goto out;
	}

	if (NULL == (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &dc_host->hostid)))
	{
		*error = zbx_dsprintf(*error, "proxy \"%s\" not found in configuration cache", name);
		goto out;
	}

	DCget_proxy(proxy, dc_proxy);
	ret = SUCCEED;
out:
	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_update_proxy_version                                      *
 *                                                                            *
 * Purpose: updates proxy version in configuration cache                      *
 *                                                                            *
 * Parameters:                                                                *
 *     hostid  - [IN] the proxy identifier                                    *
 *     version - [IN] the new proxy version                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_update_proxy_version(zbx_uint64_t hostid, int version)
{
	ZBX_DC_PROXY	*dc_proxy;

	LOCK_CACHE;

	if (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &hostid)))
		dc_proxy->version = version;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_items_update_nextcheck                                    *
 *                                                                            *
 * Purpose: updates item nextcheck values in configuration cache              *
 *                                                                            *
 * Parameters: items      - [IN] the items to update                          *
 *             values     - [IN] the items values containing new properties   *
 *             errcodes   - [IN] item error codes. Update only items with     *
 *                               SUCCEED code                                 *
 *             values_num - [IN] the number of elements in items,values and   *
 *                               errcodes arrays                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_items_update_nextcheck(DC_ITEM *items, zbx_agent_value_t *values, int *errcodes, size_t values_num)
{
	size_t		i;
	ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	for (i = 0; i < values_num; i++)
	{
		if (FAIL == errcodes[i])
			continue;

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &items[i].itemid)))
			continue;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		/* update nextcheck for items that are counted in queue for monitoring purposes */
		if (SUCCEED == is_counted_in_item_queue(dc_item->type, dc_item->key))
			DCitem_nextcheck_update(dc_item, dc_host, items[i].state, ZBX_ITEM_COLLECTED, values[i].ts.sec);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_update_proxy_lastaccess                                   *
 *                                                                            *
 * Purpose: updates proxy last access timestamp in configuration cache        *
 *                                                                            *
 * Parameter: hostid     - [IN] the proxy identifier (hostid)                 *
 *            lastaccess - [IN] the last time proxy data was received/sent    *
 *            proxy_diff - [OUT] last access updates for proxies that need    *
 *                               to be synced with database                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_update_proxy_lastaccess(zbx_uint64_t hostid, int lastaccess, zbx_vector_uint64_pair_t *proxy_diff)
{
	ZBX_DC_PROXY	*proxy;
	int		now;

	LOCK_CACHE;

	if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &hostid)))
	{
		if (lastaccess < config->proxy_lastaccess_ts)
			proxy->lastaccess = config->proxy_lastaccess_ts;
		else
			proxy->lastaccess = lastaccess;
	}

	if (ZBX_PROXY_LASTACCESS_UPDATE_FREQUENCY < (now = time(NULL)) - config->proxy_lastaccess_ts)
	{
		zbx_hashset_iter_t	iter;

		zbx_hashset_iter_reset(&config->proxies, &iter);

		while (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_iter_next(&iter)))
		{
			if (proxy->lastaccess >= config->proxy_lastaccess_ts)
			{
				zbx_uint64_pair_t	pair = {proxy->hostid, proxy->lastaccess};

				zbx_vector_uint64_pair_append(proxy_diff, pair);
			}
		}

		config->proxy_lastaccess_ts = now;
	}

	UNLOCK_CACHE;

	zbx_vector_uint64_pair_sort(proxy_diff, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_get_host_interfaces                                       *
 *                                                                            *
 * Purpose: get data of all network interfaces for a host in configuration    *
 *          cache                                                             *
 *                                                                            *
 * Parameter: hostid     - [IN] the host identifier                           *
 *            interfaces - [OUT] array with interface data                    *
 *            n          - [OUT] number of allocated 'interfaces' elements    *
 *                                                                            *
 * Return value: SUCCEED - interface data retrieved successfully              *
 *               FAIL    - host not found                                     *
 *                                                                            *
 * Comments: if host is found but has no interfaces (should not happen) this  *
 *           function sets 'n' to 0 and no memory is allocated for            *
 *           'interfaces'. It is a caller responsibility to deallocate        *
 *           memory of 'interfaces' and its components.                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_host_interfaces(zbx_uint64_t hostid, DC_INTERFACE2 **interfaces, int *n)
{
	ZBX_DC_HOST	*host;
	int		i, ret = FAIL;

	if (0 == hostid)
		return FAIL;

	LOCK_CACHE;

	/* find host entry in 'config->hosts' hashset */

	if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
		goto unlock;

	/* allocate memory for results */

	if (0 < (*n = host->interfaces_v.values_num))
		*interfaces = (DC_INTERFACE2 *)zbx_malloc(NULL, sizeof(DC_INTERFACE2) * (size_t)*n);

	/* copy data about all host interfaces */

	for (i = 0; i < *n; i++)
	{
		const ZBX_DC_INTERFACE	*src = (ZBX_DC_INTERFACE *)host->interfaces_v.values[i];
		DC_INTERFACE2		*dst = *interfaces + i;

		dst->interfaceid = src->interfaceid;
		dst->type = src->type;
		dst->main = src->main;
		dst->bulk = src->bulk;
		dst->useip = src->useip;
		strscpy(dst->ip_orig, src->ip);
		strscpy(dst->dns_orig, src->dns);
		strscpy(dst->port_orig, src->port);
		dst->addr = (1 == src->useip ? dst->ip_orig : dst->dns_orig);
	}

	ret = SUCCEED;
unlock:
	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_items_apply_changes                                     *
 *                                                                            *
 * Purpose: apply item state, error, mtime, lastlogsize changes to            *
 *          configuration cache                                               *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_items_apply_changes(const zbx_vector_ptr_t *item_diff)
{
	int			i;
	const zbx_item_diff_t	*diff;
	ZBX_DC_ITEM		*dc_item;

	if (0 == item_diff->values_num)
		return;

	LOCK_CACHE;

	for (i = 0; i < item_diff->values_num; i++)
	{
		diff = (const zbx_item_diff_t *)item_diff->values[i];

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &diff->itemid)))
			continue;

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE & diff->flags))
			dc_item->lastlogsize = diff->lastlogsize;

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME & diff->flags))
			dc_item->mtime = diff->mtime;

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR & diff->flags))
			DCstrpool_replace(1, &dc_item->error, diff->error);

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE & diff->flags))
			dc_item->state = diff->state;

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTCLOCK & diff->flags))
			dc_item->lastclock = diff->lastclock;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_update_inventory_values                                 *
 *                                                                            *
 * Purpose: update automatic inventory in configuration cache                 *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_update_inventory_values(const zbx_vector_ptr_t *inventory_values)
{
	ZBX_DC_HOST_INVENTORY	*host_inventory = NULL;
	int			i;

	LOCK_CACHE;

	for (i = 0; i < inventory_values->values_num; i++)
	{
		const zbx_inventory_value_t	*inventory_value = (zbx_inventory_value_t *)inventory_values->values[i];
		const char			**value;

		if (NULL == host_inventory || inventory_value->hostid != host_inventory->hostid)
		{
			host_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories_auto, &inventory_value->hostid);

			if (NULL == host_inventory)
				continue;
		}

		value = &host_inventory->values[inventory_value->idx];

		DCstrpool_replace((NULL != *value ? 1 : 0), value, inventory_value->value);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_host_inventory_value_by_itemid                             *
 *                                                                            *
 * Purpose: find inventory value in automatically populated cache, if not     *
 *          found then look in main inventory cache                           *
 *                                                                            *
 ******************************************************************************/
int	DCget_host_inventory_value_by_itemid(zbx_uint64_t itemid, char **replace_to, int value_idx)
{
	ZBX_DC_ITEM		*dc_item;
	ZBX_DC_HOST_INVENTORY	*dc_inventory;
	int			ret = FAIL;

	LOCK_CACHE;

	if (NULL != (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
	{
		if (NULL != (dc_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories_auto, &dc_item->hostid)) &&
				NULL != dc_inventory->values[value_idx])
		{
			*replace_to = zbx_strdup(*replace_to, dc_inventory->values[value_idx]);
			ret = SUCCEED;
		}
		else if (NULL != (dc_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories, &dc_item->hostid)))
		{
			*replace_to = zbx_strdup(*replace_to, dc_inventory->values[value_idx]);
			ret = SUCCEED;
		}
	}

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_get_trigger_dependencies                                  *
 *                                                                            *
 * Purpose: checks/returns trigger dependencies for a set of triggers         *
 *                                                                            *
 * Parameter: triggerids  - [IN] the currently processing trigger ids         *
 *            deps        - [OUT] list of dependency check results for failed *
 *                                or unresolved dependencies                  *
 *                                                                            *
 * Comments: This function returns list of zbx_trigger_dep_t structures       *
 *           for failed or unresolved dependency checks.                      *
 *           Dependency check is failed if any of the master triggers that    *
 *           are not being processed in this batch (present in triggerids     *
 *           vector) has a problem value.                                     *
 *           Dependency check is unresolved if a master trigger is being      *
 *           processed in this batch (present in triggerids vector) and no    *
 *           other master triggers have problem value.                        *
 *           Dependency check is successful if all master triggers (if any)   *
 *           have OK value and are not being processed in this batch.         *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_trigger_dependencies(const zbx_vector_uint64_t *triggerids, zbx_vector_ptr_t *deps)
{
	int				i, ret;
	const ZBX_DC_TRIGGER_DEPLIST	*trigdep;
	zbx_vector_uint64_t		masterids;
	zbx_trigger_dep_t		*dep;

	zbx_vector_uint64_create(&masterids);
	zbx_vector_uint64_reserve(&masterids, 64);

	LOCK_CACHE;

	for (i = 0; i < triggerids->values_num; i++)
	{
		if (NULL == (trigdep = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_search(&config->trigdeps, &triggerids->values[i])))
			continue;

		if (FAIL == (ret = DCconfig_check_trigger_dependencies_rec(trigdep, 0, triggerids, &masterids)) ||
				0 != masterids.values_num)
		{
			dep = (zbx_trigger_dep_t *)zbx_malloc(NULL, sizeof(zbx_trigger_dep_t));
			dep->triggerid = triggerids->values[i];
			zbx_vector_uint64_create(&dep->masterids);

			if (SUCCEED == ret)
			{
				dep->status = ZBX_TRIGGER_DEPENDENCY_UNRESOLVED;
				zbx_vector_uint64_append_array(&dep->masterids, masterids.values, masterids.values_num);
			}
			else
				dep->status = ZBX_TRIGGER_DEPENDENCY_FAIL;

			zbx_vector_ptr_append(deps, dep);
		}

		zbx_vector_uint64_clear(&masterids);
	}

	UNLOCK_CACHE;

	zbx_vector_uint64_destroy(&masterids);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dc_process_check_now_tasks                                   *
 *                                                                            *
 * Purpose: process check now tasks by requeueing items monitored by server   *
 *          at current time                                                   *
 *                                                                            *
 * Parameter: tasks - [IN/OUT] the tasks                                      *
 *                                                                            *
 * Comments: This function will set corresponding task->proxy_hostid values   *
 *           for items monitored by proxies.                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_process_check_now_tasks(zbx_vector_ptr_t *tasks)
{
	int			i, now;
	ZBX_DC_ITEM		*dc_item;
	ZBX_DC_HOST		*dc_host;
	zbx_tm_task_t		*task;
	zbx_tm_check_now_t	*data;

	now = time(NULL);

	LOCK_CACHE;

	for (i = 0; i < tasks->values_num; i++)
	{
		task = (zbx_tm_task_t *)tasks->values[i];

		if (ZBX_TM_STATUS_NEW != task->status)
			continue;


		data = (zbx_tm_check_now_t *)task->data;

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &data->itemid)) ||
				NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		{
			continue;
		}

		if (0 == dc_host->proxy_hostid)
			dc_requeue_item_at(dc_item, dc_host, now);
		else
			task->proxy_hostid = dc_host->proxy_hostid;
	}

	UNLOCK_CACHE;
}

