/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "threads.h"
#include "dbcache.h"
#include "ipc.h"
#include "mutexs.h"
#include "memalloc.h"
#include "strpool.h"
#include "zbxserver.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "zbxregexp.h"

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

typedef struct
{
	zbx_uint64_t	triggerid;
	const char	*description;
	const char	*expression;
	const char	*error;
	int		lastchange;
	unsigned char	topoindex;
	unsigned char	priority;
	unsigned char	type;
	unsigned char	value;
	unsigned char	state;
	unsigned char	locked;
	unsigned char	status;
}
ZBX_DC_TRIGGER;

typedef struct zbx_dc_trigger_deplist_s
{
	zbx_uint64_t				triggerid;
	ZBX_DC_TRIGGER				*trigger;
	const struct zbx_dc_trigger_deplist_s	**dependencies;
}
ZBX_DC_TRIGGER_DEPLIST;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	triggerid;
	zbx_uint64_t	itemid;
	const char	*function;
	const char	*parameter;
}
ZBX_DC_FUNCTION;

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	hostid;
	zbx_uint64_t	interfaceid;
	zbx_uint64_t	lastlogsize;
	zbx_uint64_t	valuemapid;
	const char	*key;
	const char	*port;
	const char	*units;
	const char	*db_error;
	ZBX_DC_TRIGGER	**triggers;
	int		delay;
	int		nextcheck;
	int		lastclock;
	int		mtime;
	int		data_expected_from;
	int		history;
	unsigned char	type;
	unsigned char	data_type;
	unsigned char	value_type;
	unsigned char	poller_type;
	unsigned char	state;
	unsigned char	db_state;
	unsigned char	inventory_link;
	unsigned char	location;
	unsigned char	flags;
	unsigned char	status;
}
ZBX_DC_ITEM;

typedef struct
{
	zbx_uint64_t	hostid;
	const char	*key;
	ZBX_DC_ITEM	*item_ptr;
}
ZBX_DC_ITEM_HK;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*formula;
	const char	*units;
	int		trends;
	unsigned char	delta;
	unsigned char	multiplier;
}
ZBX_DC_NUMITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*snmp_oid;
	const char	*snmp_community;
	const char	*snmpv3_securityname;
	const char	*snmpv3_authpassphrase;
	const char	*snmpv3_privpassphrase;
	const char	*snmpv3_contextname;
	unsigned char	snmpv3_securitylevel;
	unsigned char	snmpv3_authprotocol;
	unsigned char	snmpv3_privprotocol;
	unsigned char	snmp_oid_type;
}
ZBX_DC_SNMPITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*ipmi_sensor;
}
ZBX_DC_IPMIITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*delay_flex;
}
ZBX_DC_FLEXITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*trapper_hosts;
}
ZBX_DC_TRAPITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*logtimefmt;
}
ZBX_DC_LOGITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*params;
	const char	*username;
	const char	*password;
}
ZBX_DC_DBITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*username;
	const char	*publickey;
	const char	*privatekey;
	const char	*password;
	const char	*params;
	unsigned char	authtype;
}
ZBX_DC_SSHITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*username;
	const char	*password;
	const char	*params;
}
ZBX_DC_TELNETITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*username;
	const char	*password;
}
ZBX_DC_SIMPLEITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*username;
	const char	*password;
}
ZBX_DC_JMXITEM;

typedef struct
{
	zbx_uint64_t	itemid;
	const char	*params;
}
ZBX_DC_CALCITEM;

typedef zbx_item_history_value_t	ZBX_DC_DELTAITEM;

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxy_hostid;
	const char	*host;
	const char	*name;
	int		maintenance_from;
	int		data_expected_from;
	int		errors_from;
	int		disable_until;
	int		snmp_errors_from;
	int		snmp_disable_until;
	int		ipmi_errors_from;
	int		ipmi_disable_until;
	int		jmx_errors_from;
	int		jmx_disable_until;
	unsigned char	maintenance_status;
	unsigned char	maintenance_type;
	unsigned char	available;
	unsigned char	snmp_available;
	unsigned char	ipmi_available;
	unsigned char	jmx_available;
	unsigned char	status;
}
ZBX_DC_HOST;

typedef struct
{
	zbx_uint64_t	hostid;
	unsigned char	inventory_mode;
}
ZBX_DC_HOST_INVENTORY;

typedef struct
{
	const char	*host;
	ZBX_DC_HOST	*host_ptr;
}
ZBX_DC_HOST_H;

typedef struct
{
	zbx_uint64_t	hostid;
	int		proxy_config_nextcheck;
	int		proxy_data_nextcheck;
	unsigned char	location;
}
ZBX_DC_PROXY;

typedef struct
{
	zbx_uint64_t	hostid;
	const char	*ipmi_username;
	const char	*ipmi_password;
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
}
ZBX_DC_IPMIHOST;

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_vector_uint64_t	templateids;
}
ZBX_DC_HTMPL;

typedef struct
{
	zbx_uint64_t	globalmacroid;
	const char	*macro;
	const char	*value;
}
ZBX_DC_GMACRO;

typedef struct
{
	const char	*macro;
	ZBX_DC_GMACRO	*gmacro_ptr;
}
ZBX_DC_GMACRO_M;

typedef struct
{
	zbx_uint64_t	hostmacroid;
	zbx_uint64_t	hostid;
	const char	*macro;
	const char	*value;
}
ZBX_DC_HMACRO;

typedef struct
{
	zbx_uint64_t	hostid;
	const char	*macro;
	ZBX_DC_HMACRO	*hmacro_ptr;
}
ZBX_DC_HMACRO_HM;

typedef struct
{
	zbx_uint64_t	interfaceid;
	zbx_uint64_t	hostid;
	const char	*ip;
	const char	*dns;
	const char	*port;
	unsigned char	type;
	unsigned char	main;
	unsigned char	useip;
	unsigned char	bulk;
	unsigned char	max_snmp_succeed;
	unsigned char	min_snmp_fail;
}
ZBX_DC_INTERFACE;

typedef struct
{
	zbx_uint64_t		hostid;
	ZBX_DC_INTERFACE	*interface_ptr;
	unsigned char		type;
}
ZBX_DC_INTERFACE_HT;

typedef struct
{
	const char		*addr;
	zbx_vector_uint64_t	interfaceids;
}
ZBX_DC_INTERFACE_ADDR;

typedef struct
{
	zbx_uint64_t		interfaceid;
	zbx_vector_uint64_t	itemids;
}
ZBX_DC_INTERFACE_ITEM;

typedef struct
{
	const char		*name;
	zbx_vector_uint64_t	expressionids;
}
ZBX_DC_REGEXP;

typedef struct
{
	zbx_uint64_t	expressionid;
	const char	*expression;
	char		delimiter;
	unsigned char	type;
	unsigned char	case_sensitive;
}
ZBX_DC_EXPRESSION;

typedef struct
{
	const char	*severity_name[TRIGGER_SEVERITY_COUNT];
	zbx_uint64_t	discovery_groupid;
	int		refresh_unsupported;
	unsigned char	snmptrap_logging;
	/* housekeeping related configuration data */
	zbx_config_hk_t	hk;
}
ZBX_DC_CONFIG_TABLE;

typedef struct
{
	zbx_hashset_t		items;
	zbx_hashset_t		items_hk;		/* hostid, key */
	zbx_hashset_t		numitems;
	zbx_hashset_t		snmpitems;
	zbx_hashset_t		ipmiitems;
	zbx_hashset_t		flexitems;
	zbx_hashset_t		trapitems;
	zbx_hashset_t		logitems;
	zbx_hashset_t		dbitems;
	zbx_hashset_t		sshitems;
	zbx_hashset_t		telnetitems;
	zbx_hashset_t		simpleitems;
	zbx_hashset_t		jmxitems;
	zbx_hashset_t		calcitems;
	zbx_hashset_t		deltaitems;		/* history data for delta value calculations */
	zbx_hashset_t		functions;
	zbx_hashset_t		triggers;
	zbx_hashset_t		trigdeps;
	zbx_vector_ptr_t	*time_triggers;
	zbx_hashset_t		hosts;
	zbx_hashset_t		hosts_h;		/* host */
	zbx_hashset_t		proxies;
	zbx_hashset_t		host_inventories;
	zbx_hashset_t		ipmihosts;
	zbx_hashset_t		htmpls;
	zbx_hashset_t		gmacros;
	zbx_hashset_t		gmacros_m;		/* macro */
	zbx_hashset_t		hmacros;
	zbx_hashset_t		hmacros_hm;		/* hostid, macro */
	zbx_hashset_t		interfaces;
	zbx_hashset_t		interfaces_ht;		/* hostid, type */
	zbx_hashset_t		interface_snmpaddrs;	/* addr, interfaceids for SNMP interfaces */
	zbx_hashset_t		interface_snmpitems;	/* interfaceid, itemids for SNMP trap items */
	zbx_hashset_t		regexps;
	zbx_hashset_t		expressions;
	zbx_binary_heap_t	queues[ZBX_POLLER_TYPE_COUNT];
	zbx_binary_heap_t	pqueue;
	ZBX_DC_CONFIG_TABLE	*config;
}
ZBX_DC_CONFIG;

static ZBX_DC_CONFIG	*config = NULL;
static ZBX_MUTEX	config_lock;
static zbx_mem_info_t	*config_mem;

extern unsigned char	daemon_type;
extern int		CONFIG_TIMER_FORKS;

ZBX_MEM_FUNC_IMPL(__config, config_mem);

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

				if (0 == strcmp(arg1, "host"))
				{
					arg2 = get_rparam(&request, 1);
					arg3 = get_rparam(&request, 2);

					if (0 != strcmp(arg3, "maintenance") || '\0' != *arg2)
						goto clean;
				}
				else if (0 == strcmp(arg1, "proxy"))
				{
					arg3 = get_rparam(&request, 2);

					if (0 != strcmp(arg3, "lastaccess"))
						goto clean;
				}
				else
					goto clean;

				ret = SUCCEED;
clean:
				free_request(&request);
			}
			break;
	}

	return ret;
}

static unsigned char	poller_by_item(zbx_uint64_t proxy_hostid, unsigned char type, const char *key)
{
	if (0 != proxy_hostid && SUCCEED != is_item_processed_by_server(type, key))
		return ZBX_NO_POLLER;

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

		if (NULL == (interface = zbx_hashset_search(&config->interfaces, &interfaceid)) ||
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

static int	DCget_reachable_nextcheck(const ZBX_DC_ITEM *item, int now)
{
	int		nextcheck;
	zbx_uint64_t	seed;

	seed = get_item_nextcheck_seed(item->itemid, item->interfaceid, item->type, item->key);

	if (ITEM_STATE_NOTSUPPORTED == item->state)
	{
		nextcheck = calculate_item_nextcheck(seed, item->type, config->config->refresh_unsupported, NULL, now);
	}
	else
	{
		const ZBX_DC_FLEXITEM	*flexitem;
		const char		*delay_flex;

		flexitem = zbx_hashset_search(&config->flexitems, &item->itemid);
		delay_flex = (NULL != flexitem ? flexitem->delay_flex : NULL);

		nextcheck = calculate_item_nextcheck(seed, item->type, item->delay, delay_flex, now);
	}

	return nextcheck;
}

static int	DCget_unreachable_nextcheck(const ZBX_DC_ITEM *item, const ZBX_DC_HOST *host)
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

	return DCget_reachable_nextcheck(item, time(NULL));
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

	if (NULL == (item_hk = zbx_hashset_search(&config->items_hk, &item_hk_local)))
		return NULL;
	else
		return item_hk->item_ptr;
}

static ZBX_DC_HOST	*DCfind_host(const char *host)
{
	ZBX_DC_HOST_H	*host_h, host_h_local;

	host_h_local.host = host;

	if (NULL == (host_h = zbx_hashset_search(&config->hosts_h, &host_h_local)))
		return NULL;
	else
		return host_h->host_ptr;
}

static int	DCstrpool_replace(int found, const char **curr, const char *new)
{
	if (1 == found)
	{
		if (0 == strcmp(*curr, new))
			return FAIL;

		zbx_strpool_release(*curr);
	}

	*curr = zbx_strpool_intern(new);

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

	if (item->poller_type >= ZBX_POLLER_TYPE_COUNT)
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

#define DEFAULT_REFRESH_UNSUPPORTED	600
static char	*default_severity_names[] = {"Not classified", "Information", "Warning", "Average", "High", "Disaster"};

static int	DCsync_config(DB_RESULT result, int *refresh_unsupported_changed)
{
	const char	*__function_name = "DCsync_config";
	DB_ROW		row;
	int		i, found = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	*refresh_unsupported_changed = 0;

	if (NULL == config->config)
	{
		found = 0;
		config->config = __config_mem_malloc_func(NULL, sizeof(ZBX_DC_CONFIG_TABLE));
	}

	if (NULL == (row = DBfetch(result)))
	{
		if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
			zabbix_log(LOG_LEVEL_ERR, "no records in table 'config'");

		if (0 == found)
		{
			/* load default config data */

			*refresh_unsupported_changed = 1;

			config->config->refresh_unsupported = DEFAULT_REFRESH_UNSUPPORTED;
			config->config->discovery_groupid = 0;

			for (i = 0; TRIGGER_SEVERITY_COUNT > i; i++)
				DCstrpool_replace(found, &config->config->severity_name[i], default_severity_names[i]);

			/* set default housekeeper configuration */
			config->config->hk.events_mode = ZBX_HK_OPTION_ENABLED;
			config->config->hk.events_trigger = 365;
			config->config->hk.events_internal = 365;
			config->config->hk.events_autoreg = 365;
			config->config->hk.events_discovery = 365;

			config->config->hk.audit_mode = ZBX_HK_OPTION_ENABLED;
			config->config->hk.audit = 365;

			config->config->hk.services_mode = ZBX_HK_OPTION_ENABLED;
			config->config->hk.services = 365;

			config->config->hk.sessions_mode = ZBX_HK_OPTION_ENABLED;
			config->config->hk.sessions = 365;

			config->config->hk.history_mode = ZBX_HK_OPTION_ENABLED;
			config->config->hk.history_global = ZBX_HK_OPTION_DISABLED;
			config->config->hk.history = 90;

			config->config->hk.trends_mode = ZBX_HK_OPTION_ENABLED;
			config->config->hk.trends_global = ZBX_HK_OPTION_DISABLED;
			config->config->hk.trends = 365;
		}
	}
	else
	{
		int	refresh_unsupported;

		/* store the config data */

		refresh_unsupported = atoi(row[0]);

		if (0 == found || config->config->refresh_unsupported != refresh_unsupported)
			*refresh_unsupported_changed = 1;

		config->config->refresh_unsupported = refresh_unsupported;
		ZBX_STR2UINT64(config->config->discovery_groupid, row[1]);
		config->config->snmptrap_logging = (unsigned char)atoi(row[2]);

		for (i = 0; TRIGGER_SEVERITY_COUNT > i; i++)
			DCstrpool_replace(found, &config->config->severity_name[i], row[3 + i]);

		/* read housekeeper configuration */
		config->config->hk.events_mode = atoi(row[9]);
		config->config->hk.events_trigger = atoi(row[10]);
		config->config->hk.events_internal = atoi(row[11]);
		config->config->hk.events_discovery = atoi(row[12]);
		config->config->hk.events_autoreg = atoi(row[13]);

		config->config->hk.services_mode = atoi(row[14]);
		config->config->hk.services = atoi(row[15]);

		config->config->hk.audit_mode = atoi(row[16]);
		config->config->hk.audit = atoi(row[17]);

		config->config->hk.sessions_mode = atoi(row[18]);
		config->config->hk.sessions = atoi(row[19]);

		config->config->hk.history_mode = atoi(row[20]);
		config->config->hk.history = atoi(row[22]);

		if (ZBX_HK_OPTION_ENABLED == config->config->hk.history_mode)
			config->config->hk.history_global = atoi(row[21]);
		else
			config->config->hk.history_global = ZBX_HK_OPTION_DISABLED;

		config->config->hk.trends_mode = atoi(row[23]);
		config->config->hk.trends = atoi(row[25]);

		if (ZBX_HK_OPTION_ENABLED == config->config->hk.trends_mode)
			config->config->hk.trends_global = atoi(row[24]);
		else
			config->config->hk.trends_global = ZBX_HK_OPTION_DISABLED;

		if (NULL != (row = DBfetch(result)))	/* config table should have only one record */
			zabbix_log(LOG_LEVEL_ERR, "table 'config' has multiple records");
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}

static void	DCsync_hosts(DB_RESULT result)
{
	const char		*__function_name = "DCsync_hosts";

	DB_ROW			row;

	ZBX_DC_HOST		*host;
	ZBX_DC_IPMIHOST		*ipmihost;
	ZBX_DC_PROXY		*proxy;
	ZBX_DC_HOST_H		*host_h, host_h_local;

	int			found;
	int			update_index;
	zbx_uint64_t		hostid, proxy_hostid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;
	unsigned char		status;
	time_t			now;
	signed char		ipmi_authtype;
	unsigned char		ipmi_privilege;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->hosts.num_data + 32);

	now = time(NULL);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_DBROW2UINT64(proxy_hostid, row[1]);
		ZBX_STR2UCHAR(status, row[22]);

		/* array of selected hosts */
		zbx_vector_uint64_append(&ids, hostid);

		host = DCfind_id(&config->hosts, hostid, sizeof(ZBX_DC_HOST), &found);

		/* see whether we should and can update hosts_h index at this point */

		update_index = 0;

		if ((HOST_STATUS_MONITORED == status || HOST_STATUS_NOT_MONITORED == status) &&
				(0 == found || 0 != strcmp(host->host, row[2])))
		{
			if (1 == found)
			{
				host_h_local.host = host->host;
				host_h = zbx_hashset_search(&config->hosts_h, &host_h_local);

				if (NULL != host_h && host == host_h->host_ptr)	/* see ZBX-4045 for NULL check */
				{
					zbx_strpool_release(host_h->host);
					zbx_hashset_remove(&config->hosts_h, &host_h_local);
				}
			}

			host_h_local.host = row[2];
			host_h = zbx_hashset_search(&config->hosts_h, &host_h_local);

			if (NULL != host_h)
				host_h->host_ptr = host;
			else
				update_index = 1;
		}

		/* store new information in host structure */

		host->proxy_hostid = proxy_hostid;
		DCstrpool_replace(found, &host->host, row[2]);
		DCstrpool_replace(found, &host->name, row[23]);

		if (0 == found)
		{
			host->maintenance_status = (unsigned char)atoi(row[7]);
			host->maintenance_type = (unsigned char)atoi(row[8]);
			host->maintenance_from = atoi(row[9]);
			host->data_expected_from = now;

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
		}
		else
		{
			if (HOST_STATUS_MONITORED == status && HOST_STATUS_MONITORED != host->status)
				host->data_expected_from = now;
		}

		host->status = status;

		/* update hosts_h index using new data, if not done already */

		if (1 == update_index)
		{
			host_h_local.host = zbx_strpool_acquire(host->host);
			host_h_local.host_ptr = host;
			zbx_hashset_insert(&config->hosts_h, &host_h_local, sizeof(ZBX_DC_HOST_H));
		}

		/* IPMI hosts */

		ipmi_authtype = (signed char)atoi(row[3]);
		ipmi_privilege = (unsigned char)atoi(row[4]);

		if (0 != ipmi_authtype || 2 != ipmi_privilege || '\0' != *row[5] || '\0' != *row[6])	/* useipmi */
		{
			ipmihost = DCfind_id(&config->ipmihosts, hostid, sizeof(ZBX_DC_IPMIHOST), &found);

			ipmihost->ipmi_authtype = ipmi_authtype;
			ipmihost->ipmi_privilege = ipmi_privilege;
			DCstrpool_replace(found, &ipmihost->ipmi_username, row[5]);
			DCstrpool_replace(found, &ipmihost->ipmi_password, row[6]);
		}
		else if (NULL != (ipmihost = zbx_hashset_search(&config->ipmihosts, &hostid)))
		{
			/* remove IPMI connection parameters for hosts without IPMI */

			zbx_strpool_release(ipmihost->ipmi_username);
			zbx_strpool_release(ipmihost->ipmi_password);

			zbx_hashset_remove(&config->ipmihosts, &hostid);
		}

		/* passive proxies */

		if (HOST_STATUS_PROXY_PASSIVE == host->status)
		{
			proxy = DCfind_id(&config->proxies, hostid, sizeof(ZBX_DC_PROXY), &found);

			if (0 == found)
			{
				proxy->proxy_config_nextcheck = (int)calculate_proxy_nextcheck(
						hostid, CONFIG_PROXYCONFIG_FREQUENCY, now);
				proxy->proxy_data_nextcheck = (int)calculate_proxy_nextcheck(
						hostid, CONFIG_PROXYDATA_FREQUENCY, now);
				proxy->location = ZBX_LOC_NOWHERE;

				DCupdate_proxy_queue(proxy);
			}
		}
		else if (NULL != (proxy = zbx_hashset_search(&config->proxies, &hostid)))
		{
			if (ZBX_LOC_QUEUE == proxy->location)
			{
				zbx_binary_heap_remove_direct(&config->pqueue, proxy->hostid);
				proxy->location = ZBX_LOC_NOWHERE;
			}

			zbx_hashset_remove(&config->proxies, &hostid);
		}
	}

	/* remove deleted hosts from buffer */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->hosts, &iter);

	while (NULL != (host = zbx_hashset_iter_next(&iter)))
	{
		hostid = host->hostid;

		if (FAIL != zbx_vector_uint64_bsearch(&ids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		/* IPMI hosts */

		if (NULL != (ipmihost = zbx_hashset_search(&config->ipmihosts, &hostid)))
		{
			zbx_strpool_release(ipmihost->ipmi_username);
			zbx_strpool_release(ipmihost->ipmi_password);

			zbx_hashset_remove(&config->ipmihosts, &hostid);
		}

		/* passive proxies */

		if (NULL != (proxy = zbx_hashset_search(&config->proxies, &hostid)))
		{
			if (ZBX_LOC_QUEUE == proxy->location)
			{
				zbx_binary_heap_remove_direct(&config->pqueue, proxy->hostid);
				proxy->location = ZBX_LOC_NOWHERE;
			}

			zbx_hashset_remove(&config->proxies, &hostid);
		}

		/* hosts */

		if (HOST_STATUS_MONITORED == host->status || HOST_STATUS_NOT_MONITORED == host->status)
		{
			host_h_local.host = host->host;
			host_h = zbx_hashset_search(&config->hosts_h, &host_h_local);

			if (NULL != host_h && host == host_h->host_ptr)	/* see ZBX-4045 for NULL check */
			{
				zbx_strpool_release(host_h->host);
				zbx_hashset_remove(&config->hosts_h, &host_h_local);
			}
		}

		zbx_strpool_release(host->host);
		zbx_strpool_release(host->name);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_host_inventory(DB_RESULT result)
{
	const char		*__function_name = "DCsync_host_inventory";

	DB_ROW			row;

	ZBX_DC_HOST_INVENTORY	*host_inventory;

	int			found;
	zbx_uint64_t		hostid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->host_inventories.num_data + 32);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		/* array of selected hosts */
		zbx_vector_uint64_append(&ids, hostid);

		host_inventory = DCfind_id(&config->host_inventories, hostid, sizeof(ZBX_DC_HOST_INVENTORY), &found);

		/* store new information in host_inventory structure */

		ZBX_STR2UCHAR(host_inventory->inventory_mode, row[1]);
	}

	/* remove deleted or disabled hosts from buffer */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->host_inventories, &iter);

	while (NULL != (host_inventory = zbx_hashset_iter_next(&iter)))
	{
		hostid = host_inventory->hostid;

		if (FAIL != zbx_vector_uint64_bsearch(&ids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		/* host_inventories */

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_htmpls(DB_RESULT result)
{
	const char		*__function_name = "DCsync_htmpls";

	DB_ROW			row;

	ZBX_DC_HTMPL		*htmpl = NULL;

	int			found;
	zbx_uint64_t		_hostid = 0, hostid, templateid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->htmpls.num_data + 32);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_STR2UINT64(templateid, row[1]);

		if (_hostid != hostid || 0 == _hostid)
		{
			_hostid = hostid;

			/* array of selected hosts */
			zbx_vector_uint64_append(&ids, hostid);

			htmpl = DCfind_id(&config->htmpls, hostid, sizeof(ZBX_DC_HTMPL), &found);

			if (0 == found)
			{
				zbx_vector_uint64_create_ext(&htmpl->templateids,
						__config_mem_malloc_func,
						__config_mem_realloc_func,
						__config_mem_free_func);
			}
			else
				zbx_vector_uint64_clear(&htmpl->templateids);
		}

		zbx_vector_uint64_append(&htmpl->templateids, templateid);
	}

	/* remove deleted hosts from buffer */

	zbx_hashset_iter_reset(&config->htmpls, &iter);

	while (NULL != (htmpl = zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(&ids, htmpl->hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		zbx_vector_uint64_destroy(&htmpl->templateids);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_gmacros(DB_RESULT result)
{
	const char		*__function_name = "DCsync_gmacros";

	DB_ROW			row;

	ZBX_DC_GMACRO		*gmacro;
	ZBX_DC_GMACRO_M		*gmacro_m, gmacro_m_local;

	int			found, update_index;
	zbx_uint64_t		globalmacroid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->gmacros.num_data + 32);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(globalmacroid, row[0]);

		/* array of selected globalmacros */
		zbx_vector_uint64_append(&ids, globalmacroid);

		gmacro = DCfind_id(&config->gmacros, globalmacroid, sizeof(ZBX_DC_GMACRO), &found);

		/* see whether we should and can update gmacros_m index at this point */

		update_index = 0;

		if (0 == found || 0 != strcmp(gmacro->macro, row[1]))
		{
			if (1 == found)
			{
				gmacro_m_local.macro = gmacro->macro;
				gmacro_m = zbx_hashset_search(&config->gmacros_m, &gmacro_m_local);

				if (NULL != gmacro_m && gmacro == gmacro_m->gmacro_ptr)	/* see ZBX-4045 for NULL check */
				{
					zbx_strpool_release(gmacro_m->macro);
					zbx_hashset_remove(&config->gmacros_m, &gmacro_m_local);
				}
			}

			gmacro_m_local.macro = row[1];
			gmacro_m = zbx_hashset_search(&config->gmacros_m, &gmacro_m_local);

			if (NULL != gmacro_m)
				gmacro_m->gmacro_ptr = gmacro;
			else
				update_index = 1;
		}

		/* store new information in macro structure */

		DCstrpool_replace(found, &gmacro->macro, row[1]);
		DCstrpool_replace(found, &gmacro->value, row[2]);

		/* update gmacros_m index using new data, if not done already */

		if (1 == update_index)
		{
			gmacro_m_local.macro = zbx_strpool_acquire(gmacro->macro);
			gmacro_m_local.gmacro_ptr = gmacro;
			zbx_hashset_insert(&config->gmacros_m, &gmacro_m_local, sizeof(ZBX_DC_GMACRO_M));
		}
	}

	/* remove deleted globalmacros from buffer */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->gmacros, &iter);

	while (NULL != (gmacro = zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(&ids, gmacro->globalmacroid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		gmacro_m_local.macro = gmacro->macro;
		gmacro_m = zbx_hashset_search(&config->gmacros_m, &gmacro_m_local);

		if (NULL != gmacro_m && gmacro == gmacro_m->gmacro_ptr)	/* see ZBX-4045 for NULL check */
		{
			zbx_strpool_release(gmacro_m->macro);
			zbx_hashset_remove(&config->gmacros_m, &gmacro_m_local);
		}

		zbx_strpool_release(gmacro->macro);
		zbx_strpool_release(gmacro->value);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_hmacros(DB_RESULT result)
{
	const char		*__function_name = "DCsync_hmacros";

	DB_ROW			row;

	ZBX_DC_HMACRO		*hmacro;
	ZBX_DC_HMACRO_HM	*hmacro_hm, hmacro_hm_local;

	int			found, update_index;
	zbx_uint64_t		hostmacroid, hostid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->hmacros.num_data + 32);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostmacroid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);

		/* array of selected hostmacros */
		zbx_vector_uint64_append(&ids, hostmacroid);

		hmacro = DCfind_id(&config->hmacros, hostmacroid, sizeof(ZBX_DC_HMACRO), &found);

		/* see whether we should and can update hmacros_hm index at this point */

		update_index = 0;

		if (0 == found || hmacro->hostid != hostid || 0 != strcmp(hmacro->macro, row[2]))
		{
			if (1 == found)
			{
				hmacro_hm_local.hostid = hmacro->hostid;
				hmacro_hm_local.macro = hmacro->macro;
				hmacro_hm = zbx_hashset_search(&config->hmacros_hm, &hmacro_hm_local);

				if (hmacro == hmacro_hm->hmacro_ptr)
				{
					zbx_strpool_release(hmacro_hm->macro);
					zbx_hashset_remove(&config->hmacros_hm, &hmacro_hm_local);
				}
			}

			hmacro_hm_local.hostid = hostid;
			hmacro_hm_local.macro = row[2];
			hmacro_hm = zbx_hashset_search(&config->hmacros_hm, &hmacro_hm_local);

			if (NULL != hmacro_hm)
				hmacro_hm->hmacro_ptr = hmacro;
			else
				update_index = 1;
		}

		/* store new information in macro structure */

		hmacro->hostid = hostid;
		DCstrpool_replace(found, &hmacro->macro, row[2]);
		DCstrpool_replace(found, &hmacro->value, row[3]);

		/* update hmacros_hm index using new data, if not done already */

		if (1 == update_index)
		{
			hmacro_hm_local.hostid = hmacro->hostid;
			hmacro_hm_local.macro = zbx_strpool_acquire(hmacro->macro);
			hmacro_hm_local.hmacro_ptr = hmacro;
			zbx_hashset_insert(&config->hmacros_hm, &hmacro_hm_local, sizeof(ZBX_DC_HMACRO_HM));
		}
	}

	/* remove deleted hostmacros from buffer */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->hmacros, &iter);

	while (NULL != (hmacro = zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(&ids, hmacro->hostmacroid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		hmacro_hm_local.hostid = hmacro->hostid;
		hmacro_hm_local.macro = hmacro->macro;
		hmacro_hm = zbx_hashset_search(&config->hmacros_hm, &hmacro_hm_local);

		if (hmacro == hmacro_hm->hmacro_ptr)
		{
			zbx_strpool_release(hmacro_hm->macro);
			zbx_hashset_remove(&config->hmacros_hm, &hmacro_hm_local);
		}

		zbx_strpool_release(hmacro->macro);
		zbx_strpool_release(hmacro->value);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_interfaces(DB_RESULT result)
{
	const char		*__function_name = "DCsync_interfaces";

	DB_ROW			row;

	ZBX_DC_INTERFACE	*interface;
	ZBX_DC_INTERFACE_HT	*interface_ht, interface_ht_local;
	ZBX_DC_INTERFACE_ADDR	*interface_snmpaddr, interface_snmpaddr_local;

	int			found, update_index;
	zbx_uint64_t		interfaceid, hostid;
	unsigned char		type, main_, useip;
	unsigned char		bulk, reset_snmp_stats;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* clear interface_snmpaddrs list */

	zbx_hashset_iter_reset(&config->interface_snmpaddrs, &iter);

	while (NULL != (interface_snmpaddr = zbx_hashset_iter_next(&iter)))
	{
		zbx_strpool_release(interface_snmpaddr->addr);
		zbx_vector_uint64_destroy(&interface_snmpaddr->interfaceids);
		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->interfaces.num_data + 32);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(interfaceid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);
		ZBX_STR2UCHAR(type, row[2]);
		ZBX_STR2UCHAR(main_, row[3]);
		ZBX_STR2UCHAR(useip, row[4]);
		ZBX_STR2UCHAR(bulk, row[8]);

		/* array of selected interfaces */
		zbx_vector_uint64_append(&ids, interfaceid);

		interface = DCfind_id(&config->interfaces, interfaceid, sizeof(ZBX_DC_INTERFACE), &found);

		/* see whether we should and can update interfaces_ht index at this point */

		update_index = 0;

		if (0 == found || interface->hostid != hostid || interface->type != type || interface->main != main_)
		{
			if (1 == found && 1 == interface->main)
			{
				interface_ht_local.hostid = interface->hostid;
				interface_ht_local.type = interface->type;
				interface_ht = zbx_hashset_search(&config->interfaces_ht, &interface_ht_local);

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
				interface_ht = zbx_hashset_search(&config->interfaces_ht, &interface_ht_local);

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
				if (NULL == (interface_snmpaddr = zbx_hashset_search(&config->interface_snmpaddrs,
						&interface_snmpaddr_local)))
				{
					zbx_strpool_acquire(interface_snmpaddr_local.addr);

					interface_snmpaddr = zbx_hashset_insert(&config->interface_snmpaddrs,
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
	}

	/* remove deleted interfaces from buffer and resolve macros for ip and dns fields */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->interfaces, &iter);

	while (NULL != (interface = zbx_hashset_iter_next(&iter)))
	{
		if (FAIL == zbx_vector_uint64_bsearch(&ids, interface->interfaceid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			/* remove from buffer */

			if (1 == interface->main)
			{
				interface_ht_local.hostid = interface->hostid;
				interface_ht_local.type = interface->type;
				interface_ht = zbx_hashset_search(&config->interfaces_ht, &interface_ht_local);

				if (NULL != interface_ht && interface == interface_ht->interface_ptr)
				{
					/* see ZBX-4045 for NULL check in the conditional */
					zbx_hashset_remove(&config->interfaces_ht, &interface_ht_local);
				}
			}

			zbx_strpool_release(interface->ip);
			zbx_strpool_release(interface->dns);
			zbx_strpool_release(interface->port);

			zbx_hashset_iter_remove(&iter);
		}
		else if (1 != interface->main || INTERFACE_TYPE_AGENT != interface->type)
		{
			/* macros are not supported for the main agent interface, resolve for other interfaces */

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
					substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, &addr,
							MACRO_TYPE_INTERFACE_ADDR, NULL, 0);
					DCstrpool_replace(1, &interface->ip, addr);
					zbx_free(addr);
				}

				if (0 != (macros & 0x02))
				{
					addr = zbx_strdup(NULL, interface->dns);
					substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, &addr,
							MACRO_TYPE_INTERFACE_ADDR, NULL, 0);
					DCstrpool_replace(1, &interface->dns, addr);
					zbx_free(addr);
				}
			}
		}
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_items(DB_RESULT result, int refresh_unsupported_changed)
{
	const char		*__function_name = "DCsync_items";

	DB_ROW			row;

	ZBX_DC_HOST		*host;

	ZBX_DC_ITEM		*item;
	ZBX_DC_NUMITEM		*numitem;
	ZBX_DC_SNMPITEM		*snmpitem;
	ZBX_DC_IPMIITEM		*ipmiitem;
	ZBX_DC_FLEXITEM		*flexitem;
	ZBX_DC_TRAPITEM		*trapitem;
	ZBX_DC_LOGITEM		*logitem;
	ZBX_DC_DBITEM		*dbitem;
	ZBX_DC_SSHITEM		*sshitem;
	ZBX_DC_TELNETITEM	*telnetitem;
	ZBX_DC_SIMPLEITEM	*simpleitem;
	ZBX_DC_JMXITEM		*jmxitem;
	ZBX_DC_CALCITEM		*calcitem;
	ZBX_DC_INTERFACE_ITEM	*interface_snmpitem;
	ZBX_DC_ITEM_HK		*item_hk, item_hk_local;
	ZBX_DC_DELTAITEM	*deltaitem;

	time_t			now;
	unsigned char		old_poller_type, status, type;
	int			old_nextcheck, delay, delay_flex_changed, key_changed, found, update_index;
	zbx_uint64_t		itemid, hostid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* clear interface_snmpitems list */
	zbx_hashset_iter_reset(&config->interface_snmpitems, &iter);

	while (NULL != (interface_snmpitem = zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_destroy(&interface_snmpitem->itemids);
		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->items.num_data + 32);

	now = time(NULL);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);
		ZBX_STR2UCHAR(status, row[2]);
		ZBX_STR2UCHAR(type, row[3]);

		if (NULL == (host = zbx_hashset_search(&config->hosts, &hostid)))
			continue;

		/* array of selected items */
		zbx_vector_uint64_append(&ids, itemid);

		item = DCfind_id(&config->items, itemid, sizeof(ZBX_DC_ITEM), &found);

		/* see whether we should and can update items_hk index at this point */

		update_index = 0;

		if (0 == found || item->hostid != hostid || 0 != strcmp(item->key, row[6]))
		{
			if (1 == found)
			{
				item_hk_local.hostid = item->hostid;
				item_hk_local.key = item->key;
				item_hk = zbx_hashset_search(&config->items_hk, &item_hk_local);

				if (item == item_hk->item_ptr)
				{
					zbx_strpool_release(item_hk->key);
					zbx_hashset_remove(&config->items_hk, &item_hk_local);
				}
			}

			item_hk_local.hostid = hostid;
			item_hk_local.key = row[6];
			item_hk = zbx_hashset_search(&config->items_hk, &item_hk_local);

			if (NULL != item_hk)
				item_hk->item_ptr = item;
			else
				update_index = 1;
		}

		/* store new information in item structure */

		item->hostid = hostid;
		item->data_type = (unsigned char)atoi(row[4]);
		DCstrpool_replace(found, &item->port, row[9]);
		item->flags = (unsigned char)atoi(row[26]);
		ZBX_DBROW2UINT64(item->interfaceid, row[27]);
		if (ZBX_HK_OPTION_ENABLED == config->config->hk.history_global)
			item->history = config->config->hk.history;
		else
			item->history = atoi(row[36]);
		ZBX_STR2UCHAR(item->inventory_link, row[38]);
		ZBX_DBROW2UINT64(item->valuemapid, row[39]);

		if (0 != (ZBX_FLAG_DISCOVERY_RULE & item->flags))
			item->value_type = ITEM_VALUE_TYPE_TEXT;
		else
			item->value_type = (unsigned char)atoi(row[5]);

		key_changed = (SUCCEED == DCstrpool_replace(found, &item->key, row[6]));

		if (0 == found)
		{
			item->triggers = NULL;
			item->nextcheck = 0;
			item->lastclock = 0;
			item->state = (unsigned char)atoi(row[20]);
			item->db_state = item->state;
			ZBX_STR2UINT64(item->lastlogsize, row[31]);
			item->mtime = atoi(row[32]);
			DCstrpool_replace(found, &item->db_error, row[41]);
			item->data_expected_from = now;
			item->location = ZBX_LOC_NOWHERE;
			old_poller_type = ZBX_NO_POLLER;
		}
		else
		{
			if (ITEM_STATUS_ACTIVE == status && ITEM_STATUS_ACTIVE != item->status)
				item->data_expected_from = now;

			old_poller_type = item->poller_type;

			if (NULL != item->triggers)
			{
				if (NULL == item->triggers[0])
				{
					/* free the memory if no triggers were found during last sync */
					config->items.mem_free_func(item->triggers);
					item->triggers = NULL;
				}
				else
				{
					/* we can reuse the same memory if the trigger list has not changed */
					item->triggers[0] = NULL;
				}
			}
		}

		item->status = status;
		old_nextcheck = item->nextcheck;

		/* update items_hk index using new data, if not done already */

		if (1 == update_index)
		{
			item_hk_local.hostid = item->hostid;
			item_hk_local.key = zbx_strpool_acquire(item->key);
			item_hk_local.item_ptr = item;
			zbx_hashset_insert(&config->items_hk, &item_hk_local, sizeof(ZBX_DC_ITEM_HK));
		}

		/* update item nextcheck and process items with flexible intervals */

		delay = atoi(row[15]);

		if ('\0' != *row[16])
		{
			int	found;

			flexitem = DCfind_id(&config->flexitems, itemid, sizeof(ZBX_DC_FLEXITEM), &found);

			delay_flex_changed = (SUCCEED == DCstrpool_replace(found, &flexitem->delay_flex, row[16]));
		}
		else if (NULL != (flexitem = zbx_hashset_search(&config->flexitems, &itemid)))
		{
			/* remove delay_flex parameter for non-flexible item */

			zbx_strpool_release(flexitem->delay_flex);
			zbx_hashset_remove(&config->flexitems, &itemid);

			delay_flex_changed = 1;
		}
		else
			delay_flex_changed = 0;

		if (ITEM_STATUS_ACTIVE == item->status && HOST_STATUS_MONITORED == host->status)
		{
			item->poller_type = poller_by_item(host->proxy_hostid, type, item->key);

			if (ZBX_POLLER_TYPE_UNREACHABLE == old_poller_type &&
					(ZBX_POLLER_TYPE_NORMAL == item->poller_type ||
					ZBX_POLLER_TYPE_IPMI == item->poller_type ||
					ZBX_POLLER_TYPE_JAVA == item->poller_type))
			{
				item->poller_type = ZBX_POLLER_TYPE_UNREACHABLE;
			}

			if (SUCCEED == is_counted_in_item_queue(type, item->key) &&
					(0 == item->nextcheck || 0 != key_changed || item->type != type ||
					(ITEM_STATE_NOTSUPPORTED == item->state && 1 == refresh_unsupported_changed) ||
					(ITEM_STATE_NORMAL == item->state &&
					(item->delay != delay || 0 != delay_flex_changed))))
			{
				zbx_uint64_t	seed;

				seed = get_item_nextcheck_seed(item->itemid, item->interfaceid, type, item->key);

				if (ITEM_STATE_NOTSUPPORTED == item->state)
				{
					item->nextcheck = calculate_item_nextcheck(seed, type,
							config->config->refresh_unsupported, NULL, now);
				}
				else
					item->nextcheck = calculate_item_nextcheck(seed, type, delay, row[16], now);
			}
		}
		else
		{
			item->poller_type = ZBX_NO_POLLER;
			item->nextcheck = 0;
		}

		item->delay = delay;
		item->type = type;

		/* numeric items */

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type || ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			numitem = DCfind_id(&config->numitems, itemid, sizeof(ZBX_DC_NUMITEM), &found);

			ZBX_STR2UCHAR(numitem->delta, row[33]);
			ZBX_STR2UCHAR(numitem->multiplier, row[34]);
			DCstrpool_replace(found, &numitem->formula, row[35]);
			if (ZBX_HK_OPTION_ENABLED == config->config->hk.trends_global)
				numitem->trends = config->config->hk.trends;
			else
				numitem->trends = atoi(row[37]);
			DCstrpool_replace(found, &numitem->units, row[40]);
		}
		else if (NULL != (numitem = zbx_hashset_search(&config->numitems, &itemid)))
		{
			/* remove parameters for non-numeric item */

			zbx_strpool_release(numitem->formula);
			zbx_strpool_release(numitem->units);

			zbx_hashset_remove(&config->numitems, &itemid);
		}

		/* SNMP items */

		if (SUCCEED == is_snmp_type(item->type))
		{
			snmpitem = DCfind_id(&config->snmpitems, itemid, sizeof(ZBX_DC_SNMPITEM), &found);

			DCstrpool_replace(found, &snmpitem->snmp_community, row[7]);
			DCstrpool_replace(found, &snmpitem->snmpv3_securityname, row[10]);
			snmpitem->snmpv3_securitylevel = (unsigned char)atoi(row[11]);
			DCstrpool_replace(found, &snmpitem->snmpv3_authpassphrase, row[12]);
			DCstrpool_replace(found, &snmpitem->snmpv3_privpassphrase, row[13]);
			snmpitem->snmpv3_authprotocol = (unsigned char)atoi(row[28]);
			snmpitem->snmpv3_privprotocol = (unsigned char)atoi(row[29]);
			DCstrpool_replace(found, &snmpitem->snmpv3_contextname, row[30]);

			if (SUCCEED == DCstrpool_replace(found, &snmpitem->snmp_oid, row[8]))
			{
				if (NULL != strchr(snmpitem->snmp_oid, '{'))
					snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_MACRO;
				else if (NULL != strchr(snmpitem->snmp_oid, '['))
					snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_DYNAMIC;
				else
					snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_NORMAL;
			}
		}
		else if (NULL != (snmpitem = zbx_hashset_search(&config->snmpitems, &itemid)))
		{
			/* remove SNMP parameters for non-SNMP item */

			zbx_strpool_release(snmpitem->snmp_community);
			zbx_strpool_release(snmpitem->snmp_oid);
			zbx_strpool_release(snmpitem->snmpv3_securityname);
			zbx_strpool_release(snmpitem->snmpv3_authpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_privpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_contextname);

			zbx_hashset_remove(&config->snmpitems, &itemid);
		}

		/* IPMI items */

		if (ITEM_TYPE_IPMI == item->type)
		{
			ipmiitem = DCfind_id(&config->ipmiitems, itemid, sizeof(ZBX_DC_IPMIITEM), &found);

			DCstrpool_replace(found, &ipmiitem->ipmi_sensor, row[14]);
		}
		else if (NULL != (ipmiitem = zbx_hashset_search(&config->ipmiitems, &itemid)))
		{
			/* remove IPMI parameters for non-IPMI item */
			zbx_strpool_release(ipmiitem->ipmi_sensor);
			zbx_hashset_remove(&config->ipmiitems, &itemid);
		}

		/* trapper items */

		if (ITEM_TYPE_TRAPPER == item->type && '\0' != *row[17])
		{
			trapitem = DCfind_id(&config->trapitems, itemid, sizeof(ZBX_DC_TRAPITEM), &found);
			zbx_trim_str_list(row[17], ',');
			DCstrpool_replace(found, &trapitem->trapper_hosts, row[17]);
		}
		else if (NULL != (trapitem = zbx_hashset_search(&config->trapitems, &itemid)))
		{
			/* remove trapper_hosts parameter */
			zbx_strpool_release(trapitem->trapper_hosts);
			zbx_hashset_remove(&config->trapitems, &itemid);
		}

		/* log items */

		if (ITEM_VALUE_TYPE_LOG == item->value_type && '\0' != *row[18])
		{
			logitem = DCfind_id(&config->logitems, itemid, sizeof(ZBX_DC_LOGITEM), &found);

			DCstrpool_replace(found, &logitem->logtimefmt, row[18]);
		}
		else if (NULL != (logitem = zbx_hashset_search(&config->logitems, &itemid)))
		{
			/* remove logtimefmt parameter */
			zbx_strpool_release(logitem->logtimefmt);
			zbx_hashset_remove(&config->logitems, &itemid);
		}

		/* db items */

		if (ITEM_TYPE_DB_MONITOR == item->type && '\0' != *row[19])
		{
			dbitem = DCfind_id(&config->dbitems, itemid, sizeof(ZBX_DC_DBITEM), &found);

			DCstrpool_replace(found, &dbitem->params, row[19]);
			DCstrpool_replace(found, &dbitem->username, row[22]);
			DCstrpool_replace(found, &dbitem->password, row[23]);
		}
		else if (NULL != (dbitem = zbx_hashset_search(&config->dbitems, &itemid)))
		{
			/* remove db item parameters */
			zbx_strpool_release(dbitem->params);
			zbx_strpool_release(dbitem->username);
			zbx_strpool_release(dbitem->password);

			zbx_hashset_remove(&config->dbitems, &itemid);
		}

		/* SSH items */

		if (ITEM_TYPE_SSH == item->type)
		{
			sshitem = DCfind_id(&config->sshitems, itemid, sizeof(ZBX_DC_SSHITEM), &found);

			sshitem->authtype = (unsigned short)atoi(row[21]);
			DCstrpool_replace(found, &sshitem->username, row[22]);
			DCstrpool_replace(found, &sshitem->password, row[23]);
			DCstrpool_replace(found, &sshitem->publickey, row[24]);
			DCstrpool_replace(found, &sshitem->privatekey, row[25]);
			DCstrpool_replace(found, &sshitem->params, row[19]);
		}
		else if (NULL != (sshitem = zbx_hashset_search(&config->sshitems, &itemid)))
		{
			/* remove SSH item parameters */

			zbx_strpool_release(sshitem->username);
			zbx_strpool_release(sshitem->password);
			zbx_strpool_release(sshitem->publickey);
			zbx_strpool_release(sshitem->privatekey);
			zbx_strpool_release(sshitem->params);

			zbx_hashset_remove(&config->sshitems, &itemid);
		}

		/* TELNET items */

		if (ITEM_TYPE_TELNET == item->type)
		{
			telnetitem = DCfind_id(&config->telnetitems, itemid, sizeof(ZBX_DC_TELNETITEM), &found);

			DCstrpool_replace(found, &telnetitem->username, row[22]);
			DCstrpool_replace(found, &telnetitem->password, row[23]);
			DCstrpool_replace(found, &telnetitem->params, row[19]);
		}
		else if (NULL != (telnetitem = zbx_hashset_search(&config->telnetitems, &itemid)))
		{
			/* remove TELNET item parameters */

			zbx_strpool_release(telnetitem->username);
			zbx_strpool_release(telnetitem->password);
			zbx_strpool_release(telnetitem->params);

			zbx_hashset_remove(&config->telnetitems, &itemid);
		}

		/* simple items */

		if (ITEM_TYPE_SIMPLE == item->type)
		{
			simpleitem = DCfind_id(&config->simpleitems, itemid, sizeof(ZBX_DC_SIMPLEITEM), &found);

			DCstrpool_replace(found, &simpleitem->username, row[22]);
			DCstrpool_replace(found, &simpleitem->password, row[23]);
		}
		else if (NULL != (simpleitem = zbx_hashset_search(&config->simpleitems, &itemid)))
		{
			/* remove simple item parameters */

			zbx_strpool_release(simpleitem->username);
			zbx_strpool_release(simpleitem->password);

			zbx_hashset_remove(&config->simpleitems, &itemid);
		}

		/* JMX items */

		if (ITEM_TYPE_JMX == item->type)
		{
			jmxitem = DCfind_id(&config->jmxitems, itemid, sizeof(ZBX_DC_JMXITEM), &found);

			DCstrpool_replace(found, &jmxitem->username, row[22]);
			DCstrpool_replace(found, &jmxitem->password, row[23]);
		}
		else if (NULL != (jmxitem = zbx_hashset_search(&config->jmxitems, &itemid)))
		{
			/* remove JMX item parameters */

			zbx_strpool_release(jmxitem->username);
			zbx_strpool_release(jmxitem->password);

			zbx_hashset_remove(&config->jmxitems, &itemid);
		}

		/* SNMP trap items for current server/proxy */

		if (ITEM_TYPE_SNMPTRAP == item->type && 0 == host->proxy_hostid)
		{
			interface_snmpitem = DCfind_id(&config->interface_snmpitems,
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
			calcitem = DCfind_id(&config->calcitems, itemid, sizeof(ZBX_DC_CALCITEM), &found);

			DCstrpool_replace(found, &calcitem->params, row[19]);
		}
		else if (NULL != (calcitem = zbx_hashset_search(&config->calcitems, &itemid)))
		{
			/* remove calculated item parameters */

			zbx_strpool_release(calcitem->params);
			zbx_hashset_remove(&config->calcitems, &itemid);
		}

		DCupdate_item_queue(item, old_poller_type, old_nextcheck);
	}

	/* remove deleted items from buffer */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (item = zbx_hashset_iter_next(&iter)))
	{
		itemid = item->itemid;

		if (FAIL != zbx_vector_uint64_bsearch(&ids, itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		/* numeric items */

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type || ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			numitem = zbx_hashset_search(&config->numitems, &itemid);

			zbx_strpool_release(numitem->formula);
			zbx_strpool_release(numitem->units);

			zbx_hashset_remove(&config->numitems, &itemid);
		}

		/* SNMP items */

		if (SUCCEED == is_snmp_type(item->type))
		{
			snmpitem = zbx_hashset_search(&config->snmpitems, &itemid);

			zbx_strpool_release(snmpitem->snmp_community);
			zbx_strpool_release(snmpitem->snmp_oid);
			zbx_strpool_release(snmpitem->snmpv3_securityname);
			zbx_strpool_release(snmpitem->snmpv3_authpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_privpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_contextname);

			zbx_hashset_remove(&config->snmpitems, &itemid);
		}

		/* IPMI items */

		if (ITEM_TYPE_IPMI == item->type)
		{
			ipmiitem = zbx_hashset_search(&config->ipmiitems, &itemid);
			zbx_strpool_release(ipmiitem->ipmi_sensor);
			zbx_hashset_remove(&config->ipmiitems, &itemid);
		}

		/* items with flexible intervals */

		if (NULL != (flexitem = zbx_hashset_search(&config->flexitems, &itemid)))
		{
			zbx_strpool_release(flexitem->delay_flex);
			zbx_hashset_remove(&config->flexitems, &itemid);
		}

		/* trapper items */

		if (ITEM_TYPE_TRAPPER == item->type &&
				NULL != (trapitem = zbx_hashset_search(&config->trapitems, &itemid)))
		{
			zbx_strpool_release(trapitem->trapper_hosts);
			zbx_hashset_remove(&config->trapitems, &itemid);
		}

		/* log items */

		if (ITEM_VALUE_TYPE_LOG == item->value_type &&
				NULL != (logitem = zbx_hashset_search(&config->logitems, &itemid)))
		{
			zbx_strpool_release(logitem->logtimefmt);
			zbx_hashset_remove(&config->logitems, &itemid);
		}

		/* db items */

		if (ITEM_TYPE_DB_MONITOR == item->type &&
				NULL != (dbitem = zbx_hashset_search(&config->dbitems, &itemid)))
		{
			zbx_strpool_release(dbitem->params);
			zbx_strpool_release(dbitem->username);
			zbx_strpool_release(dbitem->password);

			zbx_hashset_remove(&config->dbitems, &itemid);
		}

		/* SSH items */

		if (ITEM_TYPE_SSH == item->type)
		{
			sshitem = zbx_hashset_search(&config->sshitems, &itemid);

			zbx_strpool_release(sshitem->username);
			zbx_strpool_release(sshitem->password);
			zbx_strpool_release(sshitem->publickey);
			zbx_strpool_release(sshitem->privatekey);
			zbx_strpool_release(sshitem->params);

			zbx_hashset_remove(&config->sshitems, &itemid);
		}

		/* TELNET items */

		if (ITEM_TYPE_TELNET == item->type)
		{
			telnetitem = zbx_hashset_search(&config->telnetitems, &itemid);

			zbx_strpool_release(telnetitem->username);
			zbx_strpool_release(telnetitem->password);
			zbx_strpool_release(telnetitem->params);

			zbx_hashset_remove(&config->telnetitems, &itemid);
		}

		/* simple items */

		if (ITEM_TYPE_SIMPLE == item->type)
		{
			simpleitem = zbx_hashset_search(&config->simpleitems, &itemid);

			zbx_strpool_release(simpleitem->username);
			zbx_strpool_release(simpleitem->password);

			zbx_hashset_remove(&config->simpleitems, &itemid);
		}

		/* JMX items */

		if (ITEM_TYPE_JMX == item->type)
		{
			jmxitem = zbx_hashset_search(&config->jmxitems, &itemid);

			zbx_strpool_release(jmxitem->username);
			zbx_strpool_release(jmxitem->password);

			zbx_hashset_remove(&config->jmxitems, &itemid);
		}

		/* calculated items */

		if (ITEM_TYPE_CALCULATED == item->type)
		{
			calcitem = zbx_hashset_search(&config->calcitems, &itemid);
			zbx_strpool_release(calcitem->params);
			zbx_hashset_remove(&config->calcitems, &itemid);
		}

		/* delta items */

		if (NULL != (deltaitem = zbx_hashset_search(&config->deltaitems, &itemid)))
			zbx_hashset_remove(&config->deltaitems, &itemid);

		/* items */

		item_hk_local.hostid = item->hostid;
		item_hk_local.key = item->key;
		item_hk = zbx_hashset_search(&config->items_hk, &item_hk_local);

		if (item == item_hk->item_ptr)
		{
			zbx_strpool_release(item_hk->key);
			zbx_hashset_remove(&config->items_hk, &item_hk_local);
		}

		if (ZBX_LOC_QUEUE == item->location)
			zbx_binary_heap_remove_direct(&config->queues[item->poller_type], item->itemid);

		zbx_strpool_release(item->key);
		zbx_strpool_release(item->port);
		zbx_strpool_release(item->db_error);

		if (NULL != item->triggers)
			config->items.mem_free_func(item->triggers);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_triggers(DB_RESULT trig_result)
{
	const char		*__function_name = "DCsync_triggers";

	DB_ROW			row;

	ZBX_DC_TRIGGER		*trigger;

	int			found;
	zbx_uint64_t		triggerid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->triggers.num_data + 32);

	while (NULL != (row = DBfetch(trig_result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		/* array of selected triggers */
		zbx_vector_uint64_append(&ids, triggerid);

		trigger = DCfind_id(&config->triggers, triggerid, sizeof(ZBX_DC_TRIGGER), &found);

		/* store new information in trigger structure */

		DCstrpool_replace(found, &trigger->description, row[1]);
		DCstrpool_replace(found, &trigger->expression, row[2]);
		ZBX_STR2UCHAR(trigger->priority, row[4]);
		ZBX_STR2UCHAR(trigger->type, row[5]);
		ZBX_STR2UCHAR(trigger->status, row[9]);

		if (0 == found)
		{
			DCstrpool_replace(found, &trigger->error, row[3]);
			ZBX_STR2UCHAR(trigger->value, row[6]);
			ZBX_STR2UCHAR(trigger->state, row[7]);
			trigger->lastchange = atoi(row[8]);
			trigger->locked = 0;
		}

		trigger->topoindex = 1;
	}

	/* remove deleted triggers from buffer */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->triggers, &iter);

	while (NULL != (trigger = zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(&ids, trigger->triggerid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		zbx_strpool_release(trigger->description);
		zbx_strpool_release(trigger->expression);
		zbx_strpool_release(trigger->error);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCconfig_sort_triggers_topologically();

static void	DCsync_trigdeps(DB_RESULT tdep_result)
{
	const char		*__function_name = "DCsync_trigdeps";

	DB_ROW			row;

	ZBX_DC_TRIGGER_DEPLIST	*trigdep, *trigdep_down, *trigdep_up;

	int			found;
	zbx_uint64_t		triggerid, triggerid_down, triggerid_up;
	zbx_vector_ptr_t	dependencies;
	zbx_vector_uint64_t	ids_down;
	zbx_vector_uint64_t	ids_up;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&dependencies);

	zbx_vector_uint64_create(&ids_down);
	zbx_vector_uint64_create(&ids_up);

	triggerid = 0;
	trigdep_down = NULL;

	while (NULL != (row = DBfetch(tdep_result)))
	{
		/* find trigdep_down pointer */

		ZBX_STR2UINT64(triggerid_down, row[0]);

		if (triggerid != triggerid_down)
		{
			if (NULL != trigdep_down)
			{
				trigdep_down->dependencies = config->trigdeps.mem_realloc_func(trigdep_down->dependencies,
						(dependencies.values_num + 1) * sizeof(const ZBX_DC_TRIGGER_DEPLIST *));
				memcpy(trigdep_down->dependencies, dependencies.values,
						dependencies.values_num * sizeof(const ZBX_DC_TRIGGER_DEPLIST *));
				trigdep_down->dependencies[dependencies.values_num] = NULL;

				dependencies.values_num = 0;
			}

			triggerid = triggerid_down;

			trigdep_down = DCfind_id(&config->trigdeps, triggerid_down, sizeof(ZBX_DC_TRIGGER_DEPLIST), &found);

			trigdep_down->trigger = zbx_hashset_search(&config->triggers, &triggerid_down);

			if (0 == found)
				trigdep_down->dependencies = NULL;

			zbx_vector_uint64_append(&ids_down, triggerid_down);
		}

		/* find trigdep_up pointer */

		ZBX_STR2UINT64(triggerid_up, row[1]);

		trigdep_up = DCfind_id(&config->trigdeps, triggerid_up, sizeof(ZBX_DC_TRIGGER_DEPLIST), &found);

		if (0 == found)
			trigdep_up->dependencies = NULL;

		zbx_vector_uint64_append(&ids_up, triggerid_up);

		zbx_vector_ptr_append(&dependencies, trigdep_up);
	}

	if (NULL != trigdep_down)
	{
		trigdep_down->dependencies = config->trigdeps.mem_realloc_func(trigdep_down->dependencies,
				(dependencies.values_num + 1) * sizeof(const ZBX_DC_TRIGGER_DEPLIST *));
		memcpy(trigdep_down->dependencies, dependencies.values,
				dependencies.values_num * sizeof(const ZBX_DC_TRIGGER_DEPLIST *));
		trigdep_down->dependencies[dependencies.values_num] = NULL;
	}

	zbx_vector_ptr_destroy(&dependencies);

	/* remove deleted trigger dependencies from buffer */

	zbx_vector_uint64_sort(&ids_up, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->trigdeps, &iter);

	while (NULL != (trigdep = zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(&ids_down, trigdep->triggerid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		if (FAIL != zbx_vector_uint64_bsearch(&ids_up, trigdep->triggerid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			trigdep->trigger = zbx_hashset_search(&config->triggers, &trigdep->triggerid);

			if (NULL != trigdep->dependencies)
			{
				config->trigdeps.mem_free_func(trigdep->dependencies);
				trigdep->dependencies = NULL;
			}

			continue;
		}

		if (NULL != trigdep->dependencies)
			config->trigdeps.mem_free_func(trigdep->dependencies);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids_down);
	zbx_vector_uint64_destroy(&ids_up);

	DCconfig_sort_triggers_topologically();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_functions(DB_RESULT result)
{
	const char		*__function_name = "DCsync_functions";

	DB_ROW			row;

	ZBX_DC_ITEM		*item;
	ZBX_DC_FUNCTION		*function;
	ZBX_DC_TRIGGER		*trigger;

	int			i, j, k, found;
	zbx_uint64_t		itemid, functionid, triggerid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zbx_ptr_pair_t		itemtrig;
	zbx_vector_ptr_pair_t	itemtrigs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < CONFIG_TIMER_FORKS; i++)
		config->time_triggers[i].values_num = 0;

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->functions.num_data + 32);

	zbx_vector_ptr_pair_create(&itemtrigs);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UINT64(functionid, row[1]);
		ZBX_STR2UINT64(triggerid, row[4]);

		if (NULL == (item = zbx_hashset_search(&config->items, &itemid)) ||
				NULL == (trigger = zbx_hashset_search(&config->triggers, &triggerid)))
		{
			/* Item and trigger could have been created after we have selected them in the */
			/* previous queries. However, we shall avoid the check for functions being the */
			/* same as in the trigger expression, because that is somewhat expensive, not */
			/* 100% (think functions keeping their functionid, but changing their function */
			/* or parameters), and even if there is an inconsistency, we can live with it. */

			continue;
		}

		/* process item information */

		itemtrig.first = item;
		itemtrig.second = trigger;

		zbx_vector_ptr_pair_append(&itemtrigs, itemtrig);

		/* process function information */

		zbx_vector_uint64_append(&ids, functionid);

		function = DCfind_id(&config->functions, functionid, sizeof(ZBX_DC_FUNCTION), &found);

		function->triggerid = triggerid;
		function->itemid = itemid;
		DCstrpool_replace(found, &function->function, row[2]);
		DCstrpool_replace(found, &function->parameter, row[3]);

		/* spread triggers with time based-fuctions between timer processes (load balancing) */

		if (SUCCEED == is_time_function(function->function))
		{
			i = function->triggerid % CONFIG_TIMER_FORKS;
			zbx_vector_ptr_append(&config->time_triggers[i], trigger);
		}
	}

	for (i = 0; i < CONFIG_TIMER_FORKS; i++)
	{
		zbx_vector_ptr_sort(&config->time_triggers[i], ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_ptr_uniq(&config->time_triggers[i], ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	/* update links from items to triggers */

	zbx_vector_ptr_pair_sort(&itemtrigs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < itemtrigs.values_num; i++)
	{
		for (j = i + 1; j < itemtrigs.values_num; j++)
		{
			if (itemtrigs.values[i].first != itemtrigs.values[j].first)
				break;
		}

		item = (ZBX_DC_ITEM *)itemtrigs.values[i].first;

		item->triggers = config->items.mem_realloc_func(item->triggers, (j - i + 1) * sizeof(ZBX_DC_TRIGGER *));

		for (k = i; k < j; k++)
			item->triggers[k - i] = (ZBX_DC_TRIGGER *)itemtrigs.values[k].second;

		item->triggers[j - i] = NULL;

		i = j - 1;
	}

	zbx_vector_ptr_pair_destroy(&itemtrigs);

	/* remove deleted functions from buffer */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->functions, &iter);

	while (NULL != (function = zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(&ids, function->functionid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		zbx_strpool_release(function->function);
		zbx_strpool_release(function->parameter);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static void	DCsync_expressions(DB_RESULT result)
{
	const char		*__function_name = "DCsync_expressions";
	DB_ROW			row;
	zbx_hashset_iter_t	iter;
	ZBX_DC_EXPRESSION	*expression;
	ZBX_DC_REGEXP		*regexp;
	zbx_vector_uint64_t	ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);

	zbx_hashset_iter_reset(&config->regexps, &iter);

	/* reset regexp -> expressions mapping */
	while (NULL != (regexp = zbx_hashset_iter_next(&iter)))
		regexp->expressionids.values_num = 0;

	/* update expressions from db */
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DC_REGEXP	new_regexp = {row[0]};
		zbx_uint64_t	expressionid;
		int 		found;

		if (NULL == (regexp = zbx_hashset_search(&config->regexps, &new_regexp)))
		{
			DCstrpool_replace(0, &new_regexp.name, row[0]);
			zbx_vector_uint64_create_ext(&new_regexp.expressionids,
					__config_mem_malloc_func,
					__config_mem_realloc_func,
					__config_mem_free_func);

			regexp = zbx_hashset_insert(&config->regexps, &new_regexp, sizeof(ZBX_DC_REGEXP));
		}

		ZBX_STR2UINT64(expressionid, row[1]);
		zbx_vector_uint64_append(&regexp->expressionids, expressionid);

		expression = DCfind_id(&config->expressions, expressionid, sizeof(ZBX_DC_EXPRESSION), &found);
		DCstrpool_replace(found, &expression->expression, row[2]);
		expression->type = (unsigned char)atoi(row[3]);
		expression->delimiter = *row[4];
		expression->case_sensitive = (unsigned char)atoi(row[5]);

		zbx_vector_uint64_append(&ids, expressionid);
	}

	/* remove regexps with no expressions related to it */
	zbx_hashset_iter_reset(&config->regexps, &iter);

	while (NULL != (regexp = zbx_hashset_iter_next(&iter)))
	{
		if (0 < regexp->expressionids.values_num)
			continue;

		zbx_strpool_release(regexp->name);
		zbx_vector_uint64_destroy(&regexp->expressionids);
		zbx_hashset_iter_remove(&iter);
	}

	/* remove unused expressions */
	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_iter_reset(&config->expressions, &iter);

	while (NULL != (expression = zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(&ids, expression->expressionid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		zbx_strpool_release(expression->expression);
		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_config_select                                             *
 *                                                                            *
 * Purpose: Executes SQL select statement used to synchronize configuration   *
 *          data with DCsync_config()                                         *
 *                                                                            *
 ******************************************************************************/
static DB_RESULT	DCsync_config_select()
{
	return DBselect(
			"select refresh_unsupported,discovery_groupid,snmptrap_logging,"
				"severity_name_0,severity_name_1,severity_name_2,"
				"severity_name_3,severity_name_4,severity_name_5,"
				"hk_events_mode,hk_events_trigger,hk_events_internal,"
				"hk_events_discovery,hk_events_autoreg,hk_services_mode,"
				"hk_services,hk_audit_mode,hk_audit,hk_sessions_mode,hk_sessions,"
				"hk_history_mode,hk_history_global,hk_history,hk_trends_mode,"
				"hk_trends_global,hk_trends"
			" from config");
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
void	DCsync_configuration(void)
{
	const char		*__function_name = "DCsync_configuration";

	DB_RESULT		conf_result = NULL;
	DB_RESULT		host_result = NULL;
	DB_RESULT		hi_result = NULL;
	DB_RESULT		htmpl_result = NULL;
	DB_RESULT		gmacro_result = NULL;
	DB_RESULT		hmacro_result = NULL;
	DB_RESULT		if_result = NULL;
	DB_RESULT		item_result = NULL;
	DB_RESULT		trig_result = NULL;
	DB_RESULT		tdep_result = NULL;
	DB_RESULT		func_result = NULL;
	DB_RESULT		expr_result = NULL;

	int			i, refresh_unsupported_changed;
	double			sec, csec, hsec, hisec, htsec, gmsec, hmsec, ifsec, isec, tsec, dsec, fsec, expr_sec,
				csec2, hsec2, hisec2, htsec2, gmsec2, hmsec2, ifsec2, isec2, tsec2, dsec2, fsec2,
				expr_sec2, total, total2;
	const zbx_strpool_t	*strpool;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sec = zbx_time();
	if (NULL == (conf_result = DCsync_config_select()))
		goto out;
	csec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (host_result = DBselect(
			"select hostid,proxy_hostid,host,ipmi_authtype,ipmi_privilege,ipmi_username,"
				"ipmi_password,maintenance_status,maintenance_type,maintenance_from,"
				"errors_from,available,disable_until,snmp_errors_from,"
				"snmp_available,snmp_disable_until,ipmi_errors_from,ipmi_available,"
				"ipmi_disable_until,jmx_errors_from,jmx_available,jmx_disable_until,"
				"status,name"
			" from hosts"
			" where status in (%d,%d,%d,%d)"
				" and flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		goto out;
	}
	hsec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (hi_result = DBselect(
			"select hostid,inventory_mode"
			" from host_inventory")))
	{
		goto out;
	}
	hisec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (htmpl_result = DBselect(
			"select hostid,templateid"
			" from hosts_templates"
			" order by hostid,templateid")))
	{
		goto out;
	}
	htsec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (gmacro_result = DBselect(
			"select globalmacroid,macro,value"
			" from globalmacro")))
	{
		goto out;
	}
	gmsec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (hmacro_result = DBselect(
			"select hostmacroid,hostid,macro,value"
			" from hostmacro")))
	{
		goto out;
	}
	hmsec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (if_result = DBselect(
			"select interfaceid,hostid,type,main,useip,ip,dns,port,bulk"
			" from interface")))
	{
		goto out;
	}
	ifsec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (item_result = DBselect(
			"select i.itemid,i.hostid,i.status,i.type,i.data_type,i.value_type,i.key_,"
				"i.snmp_community,i.snmp_oid,i.port,i.snmpv3_securityname,i.snmpv3_securitylevel,"
				"i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.ipmi_sensor,i.delay,i.delay_flex,"
				"i.trapper_hosts,i.logtimefmt,i.params,i.state,i.authtype,i.username,i.password,"
				"i.publickey,i.privatekey,i.flags,i.interfaceid,i.snmpv3_authprotocol,"
				"i.snmpv3_privprotocol,i.snmpv3_contextname,i.lastlogsize,i.mtime,i.delta,i.multiplier,"
				"i.formula,i.history,i.trends,i.inventory_link,i.valuemapid,i.units,i.error"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status in (%d,%d)"
				" and i.flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		goto out;
	}
	isec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (trig_result = DBselect(
			"select distinct t.triggerid,t.description,t.expression,t.error,"
				"t.priority,t.type,t.value,t.state,t.lastchange,t.status"
			" from hosts h,items i,functions f,triggers t"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=t.triggerid"
				" and h.status in (%d,%d)"
				" and t.flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		goto out;
	}
	tsec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (tdep_result = DBselect(
			"select d.triggerid_down,d.triggerid_up"
			" from trigger_depends d"
			" order by d.triggerid_down")))
	{
		goto out;
	}
	dsec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (func_result = DBselect(
			"select i.itemid,f.functionid,f.function,f.parameter,t.triggerid"
			" from hosts h,items i,functions f,triggers t"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=t.triggerid"
				" and h.status in (%d,%d)"
				" and t.flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		goto out;
	}
	fsec = zbx_time() - sec;

	sec = zbx_time();
	if (NULL == (expr_result = DBselect(
			"select r.name,e.expressionid,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
			" from regexps r,expressions e"
			" where r.regexpid=e.regexpid")))
	{
		goto out;
	}
	expr_sec = zbx_time() - sec;

	START_SYNC;

	sec = zbx_time();
	DCsync_config(conf_result, &refresh_unsupported_changed);
	csec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_hosts(host_result);
	hsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_host_inventory(hi_result);
	hisec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_htmpls(htmpl_result);
	htsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_gmacros(gmacro_result);
	gmsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_hmacros(hmacro_result);
	hmsec2 = zbx_time() - sec;

	sec = zbx_time();
	/* resolves macros for interface_snmpaddrs, must be after DCsync_hmacros() */
	DCsync_interfaces(if_result);
	ifsec2 = zbx_time() - sec;

	sec = zbx_time();
	/* relies on hosts and interfaces, must be after DCsync_{hosts,interfaces}() */
	DCsync_items(item_result, refresh_unsupported_changed);
	isec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_triggers(trig_result);
	tsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_trigdeps(tdep_result);
	dsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_functions(func_result);
	fsec2 = zbx_time() - sec;

	sec = zbx_time();
	DCsync_expressions(expr_result);
	expr_sec2 = zbx_time() - sec;

	strpool = zbx_strpool_info();

	total = csec + hsec + hisec + htsec + gmsec + hmsec + ifsec + isec + tsec + dsec + fsec + expr_sec;
	total2 = csec2 + hsec2 + hisec2 + htsec2 + gmsec2 + hmsec2 + ifsec2 + isec2 + tsec2 + dsec2 + fsec2 + expr_sec2;
	zabbix_log(LOG_LEVEL_DEBUG, "%s() config     : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			csec, csec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts      : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			hsec, hsec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() host_invent: sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			hisec, hisec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() templates  : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			htsec, htsec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() globmacros : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			gmsec, gmsec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() hostmacros : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			hmsec, hmsec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() interfaces : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			ifsec, ifsec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() items      : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			isec, isec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() triggers   : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			tsec, tsec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() trigdeps   : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			dsec, dsec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() functions  : sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			fsec, fsec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() expressions: sql:" ZBX_FS_DBL " sync:" ZBX_FS_DBL " sec.", __function_name,
			expr_sec, expr_sec2);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() total sql  : " ZBX_FS_DBL " sec.", __function_name, total);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() total sync : " ZBX_FS_DBL " sec.", __function_name, total2);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() proxies    : %d (%d slots)", __function_name,
			config->proxies.num_data, config->proxies.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts      : %d (%d slots)", __function_name,
			config->hosts.num_data, config->hosts.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts_h    : %d (%d slots)", __function_name,
			config->hosts_h.num_data, config->hosts_h.num_slots);
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
	zabbix_log(LOG_LEVEL_DEBUG, "%s() flexitems  : %d (%d slots)", __function_name,
			config->flexitems.num_data, config->flexitems.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() trapitems  : %d (%d slots)", __function_name,
			config->trapitems.num_data, config->trapitems.num_slots);
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
	zabbix_log(LOG_LEVEL_DEBUG, "%s() deltaitems : %d (%d slots)", __function_name,
			config->deltaitems.num_data, config->deltaitems.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() functions  : %d (%d slots)", __function_name,
			config->functions.num_data, config->functions.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() triggers   : %d (%d slots)", __function_name,
			config->triggers.num_data, config->triggers.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() trigdeps   : %d (%d slots)", __function_name,
			config->trigdeps.num_data, config->trigdeps.num_slots);
	for (i = 0; i < CONFIG_TIMER_FORKS; i++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() t_trigs[%d] : %d (%d allocated)", __function_name,
				i, config->time_triggers[i].values_num, config->time_triggers[i].values_alloc);
	}
	zabbix_log(LOG_LEVEL_DEBUG, "%s() expressions: %d (%d slots)", __function_name,
			config->expressions.num_data, config->expressions.num_slots);

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
			strpool->hashset->num_data, strpool->hashset->num_slots);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() strpoolfree: " ZBX_FS_DBL "%%", __function_name,
			100 * ((double)strpool->mem_info->free_size / strpool->mem_info->orig_size));

	zbx_mem_dump_stats(config_mem);
	zbx_mem_dump_stats(strpool->mem_info);

	FINISH_SYNC;
out:
	DBfree_result(conf_result);
	DBfree_result(host_result);
	DBfree_result(hi_result);
	DBfree_result(htmpl_result);
	DBfree_result(gmacro_result);
	DBfree_result(hmacro_result);
	DBfree_result(if_result);
	DBfree_result(item_result);
	DBfree_result(trig_result);
	DBfree_result(tdep_result);
	DBfree_result(func_result);
	DBfree_result(expr_result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: init_configuration_cache                                         *
 *                                                                            *
 * Purpose: Allocate shared memory for configuration cache                    *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 * Comments: helper functions __config_mem_XXX_func(), __config_XXX_hash,     *
 *           and __config_XXX_compare are only used inside this function      *
 *           for initializing hashset, vector, and heap data structures       *
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

	s1 = zbx_hashset_search(&config->snmpitems, &i1->itemid);
	s2 = zbx_hashset_search(&config->snmpitems, &i2->itemid);

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
	ZBX_RETURN_IF_NOT_EQUAL(i1->interfaceid, i2->interfaceid);

	return 0;
}

static int	__config_java_item_compare(const ZBX_DC_ITEM *i1, const ZBX_DC_ITEM *i2)
{
	const ZBX_DC_JMXITEM	*j1;
	const ZBX_DC_JMXITEM	*j2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->interfaceid, i2->interfaceid);

	j1 = zbx_hashset_search(&config->jmxitems, &i1->itemid);
	j2 = zbx_hashset_search(&config->jmxitems, &i2->itemid);

	ZBX_RETURN_IF_NOT_EQUAL(j1->username, j2->username);
	ZBX_RETURN_IF_NOT_EQUAL(j1->password, j2->password);

	return 0;
}

static int	__config_java_elem_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const ZBX_DC_ITEM		*i1 = (const ZBX_DC_ITEM *)e1->data;
	const ZBX_DC_ITEM		*i2 = (const ZBX_DC_ITEM *)e2->data;

	ZBX_RETURN_IF_NOT_EQUAL(i1->nextcheck, i2->nextcheck);

	return __config_java_item_compare(i1, i2);
}

static int	__config_proxy_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const ZBX_DC_PROXY		*p1 = (const ZBX_DC_PROXY *)e1->data;
	const ZBX_DC_PROXY		*p2 = (const ZBX_DC_PROXY *)e2->data;

	int				nextcheck1, nextcheck2;

	nextcheck1 = (p1->proxy_config_nextcheck < p1->proxy_data_nextcheck) ?
			p1->proxy_config_nextcheck : p1->proxy_data_nextcheck;
	nextcheck2 = (p2->proxy_config_nextcheck < p2->proxy_data_nextcheck) ?
			p2->proxy_config_nextcheck : p2->proxy_data_nextcheck;

	ZBX_RETURN_IF_NOT_EQUAL(nextcheck1, nextcheck2);

	return 0;
}

/* hash and compare functitions for expressions hashset */
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

void	init_configuration_cache()
{
	const char	*__function_name = "init_configuration_cache";

	int		i;
	key_t		shm_key;
	size_t		config_size;
	size_t		strpool_size;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() size:" ZBX_FS_UI64, __function_name, CONFIG_CONF_CACHE_SIZE);

	strpool_size = (size_t)(CONFIG_CONF_CACHE_SIZE * 0.15);
	config_size = CONFIG_CONF_CACHE_SIZE - strpool_size;

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_CONFIG_ID)))
	{
		zbx_error("Can't create IPC key for configuration cache");
		exit(EXIT_FAILURE);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&config_lock, ZBX_MUTEX_CONFIG))
	{
		zbx_error("Unable to create mutex for configuration cache");
		exit(EXIT_FAILURE);
	}

	zbx_mem_create(&config_mem, shm_key, ZBX_NO_MUTEX, config_size, "configuration cache", "CacheSize", 0);

	config = __config_mem_malloc_func(NULL, sizeof(ZBX_DC_CONFIG) +
			CONFIG_TIMER_FORKS * sizeof(zbx_vector_ptr_t));
	config->time_triggers = (zbx_vector_ptr_t *)(config + 1);

#define	INIT_HASHSET_SIZE	1000	/* should be calculated dynamically based on config_size? */

#define CREATE_HASHSET(hashset)	CREATE_HASHSET_EXT(hashset, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC)

#define CREATE_HASHSET_EXT(hashset, hash_func, compare_func)								\
															\
	zbx_hashset_create_ext(&hashset, INIT_HASHSET_SIZE,								\
				hash_func, compare_func,								\
				__config_mem_malloc_func,								\
				__config_mem_realloc_func,								\
				__config_mem_free_func)

	CREATE_HASHSET(config->items);
	CREATE_HASHSET(config->numitems);
	CREATE_HASHSET(config->snmpitems);
	CREATE_HASHSET(config->ipmiitems);
	CREATE_HASHSET(config->flexitems);
	CREATE_HASHSET(config->trapitems);
	CREATE_HASHSET(config->logitems);
	CREATE_HASHSET(config->dbitems);
	CREATE_HASHSET(config->sshitems);
	CREATE_HASHSET(config->telnetitems);
	CREATE_HASHSET(config->simpleitems);
	CREATE_HASHSET(config->jmxitems);
	CREATE_HASHSET(config->calcitems);
	CREATE_HASHSET(config->deltaitems);
	CREATE_HASHSET(config->functions);
	CREATE_HASHSET(config->triggers);
	CREATE_HASHSET(config->trigdeps);
	CREATE_HASHSET(config->hosts);
	CREATE_HASHSET(config->proxies);
	CREATE_HASHSET(config->host_inventories);
	CREATE_HASHSET(config->ipmihosts);
	CREATE_HASHSET(config->htmpls);
	CREATE_HASHSET(config->gmacros);
	CREATE_HASHSET(config->hmacros);
	CREATE_HASHSET(config->interfaces);
	CREATE_HASHSET(config->interface_snmpitems);
	CREATE_HASHSET(config->expressions);

	CREATE_HASHSET_EXT(config->items_hk, __config_item_hk_hash, __config_item_hk_compare);
	CREATE_HASHSET_EXT(config->hosts_h, __config_host_h_hash, __config_host_h_compare);
	CREATE_HASHSET_EXT(config->gmacros_m, __config_gmacro_m_hash, __config_gmacro_m_compare);
	CREATE_HASHSET_EXT(config->hmacros_hm, __config_hmacro_hm_hash, __config_hmacro_hm_compare);
	CREATE_HASHSET_EXT(config->interfaces_ht, __config_interface_ht_hash, __config_interface_ht_compare);
	CREATE_HASHSET_EXT(config->interface_snmpaddrs, __config_interface_addr_hash, __config_interface_addr_compare);
	CREATE_HASHSET_EXT(config->regexps, __config_regexp_hash, __config_regexp_compare);

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

#undef	INIT_HASHSET_SIZE

#undef	CREATE_HASHSET
#undef	CREATE_HASHSET_EXT

	zbx_strpool_create(strpool_size);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
void	free_configuration_cache()
{
	const char	*__function_name = "free_configuration_cache";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_CACHE;

	config = NULL;
	zbx_mem_destroy(config_mem);

	zbx_strpool_destroy();

	UNLOCK_CACHE;

	zbx_mutex_destroy(&config_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCload_config                                                    *
 *                                                                            *
 * Purpose: load 'config' table in cache                                      *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 * Comments: !! SQL statement must be synced with DCsync_configuration() !!   *
 *                                                                            *
 ******************************************************************************/
void	DCload_config()
{
	const char	*__function_name = "DCload_config";

	DB_RESULT	result;
	int		refresh_unsupported_changed;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DCsync_config_select();

	LOCK_CACHE;

	DCsync_config(result, &refresh_unsupported_changed);

	UNLOCK_CACHE;

	DBfree_result(result);

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
#define DCin_maintenance_without_data_collection(dc_host, dc_item)			\
		in_maintenance_without_data_collection(dc_host->maintenance_status,	\
				dc_host->maintenance_type, dc_item->type)
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

	if (NULL != (ipmihost = zbx_hashset_search(&config->ipmihosts, &src_host->hostid)))
	{
		dst_host->ipmi_authtype = ipmihost->ipmi_authtype;
		dst_host->ipmi_privilege = ipmihost->ipmi_privilege;
		strscpy(dst_host->ipmi_username, ipmihost->ipmi_username);
		strscpy(dst_host->ipmi_password, ipmihost->ipmi_password);
	}
	else
	{
		dst_host->ipmi_authtype = 0;
		dst_host->ipmi_privilege = 2;
		*dst_host->ipmi_username = '\0';
		*dst_host->ipmi_password = '\0';
	}

	if (NULL != (host_inventory = zbx_hashset_search(&config->host_inventories, &src_host->hostid)))
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
	int			res = FAIL;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	if (NULL != (dc_host = zbx_hashset_search(&config->hosts, &hostid)))
	{
		DCget_host(host, dc_host);
		res = SUCCEED;
	}

	UNLOCK_CACHE;

	return res;
}

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
	const ZBX_DC_FLEXITEM		*flexitem;
	const ZBX_DC_SSHITEM		*sshitem;
	const ZBX_DC_TELNETITEM		*telnetitem;
	const ZBX_DC_SIMPLEITEM		*simpleitem;
	const ZBX_DC_JMXITEM		*jmxitem;
	const ZBX_DC_CALCITEM		*calcitem;
	const ZBX_DC_INTERFACE		*dc_interface;

	dst_item->itemid = src_item->itemid;
	dst_item->type = src_item->type;
	dst_item->data_type = src_item->data_type;
	dst_item->value_type = src_item->value_type;
	strscpy(dst_item->key_orig, src_item->key);
	dst_item->key = NULL;
	dst_item->delay = src_item->delay;
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

	dst_item->db_state = src_item->db_state;
	dst_item->db_error = zbx_strdup(NULL, src_item->db_error);

	if (NULL != (flexitem = zbx_hashset_search(&config->flexitems, &src_item->itemid)))
		strscpy(dst_item->delay_flex, flexitem->delay_flex);
	else
		*dst_item->delay_flex = '\0';

	switch (src_item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			numitem = zbx_hashset_search(&config->numitems, &src_item->itemid);

			dst_item->delta = numitem->delta;
			dst_item->multiplier = numitem->multiplier;
			dst_item->formula = zbx_strdup(NULL, numitem->formula);
			dst_item->trends = numitem->trends;
			dst_item->units = zbx_strdup(NULL, numitem->units);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (NULL != (logitem = zbx_hashset_search(&config->logitems, &src_item->itemid)))
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
			snmpitem = zbx_hashset_search(&config->snmpitems, &src_item->itemid);

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
			if (NULL != (trapitem = zbx_hashset_search(&config->trapitems, &src_item->itemid)))
				strscpy(dst_item->trapper_hosts, trapitem->trapper_hosts);
			else
				*dst_item->trapper_hosts = '\0';
			break;
		case ITEM_TYPE_IPMI:
			if (NULL != (ipmiitem = zbx_hashset_search(&config->ipmiitems, &src_item->itemid)))
				strscpy(dst_item->ipmi_sensor, ipmiitem->ipmi_sensor);
			else
				*dst_item->ipmi_sensor = '\0';
			break;
		case ITEM_TYPE_DB_MONITOR:
			if (NULL != (dbitem = zbx_hashset_search(&config->dbitems, &src_item->itemid)))
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
			if (NULL != (sshitem = zbx_hashset_search(&config->sshitems, &src_item->itemid)))
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
			if (NULL != (telnetitem = zbx_hashset_search(&config->telnetitems, &src_item->itemid)))
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
			if (NULL != (simpleitem = zbx_hashset_search(&config->simpleitems, &src_item->itemid)))
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
			if (NULL != (jmxitem = zbx_hashset_search(&config->jmxitems, &src_item->itemid)))
			{
				strscpy(dst_item->username_orig, jmxitem->username);
				strscpy(dst_item->password_orig, jmxitem->password);
			}
			else
			{
				*dst_item->username_orig = '\0';
				*dst_item->password_orig = '\0';
			}
			dst_item->username = NULL;
			dst_item->password = NULL;
			break;
		case ITEM_TYPE_CALCULATED:
			calcitem = zbx_hashset_search(&config->calcitems, &src_item->itemid);
			dst_item->params = zbx_strdup(NULL, NULL != calcitem ? calcitem->params : "");
			break;
		default:
			/* nothing to do */;
	}

	dc_interface = zbx_hashset_search(&config->interfaces, &src_item->interfaceid);

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
			zbx_free(items[i].formula);
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

		zbx_free(items[i].db_error);
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
	dst_function->function = zbx_malloc(NULL, sz_function + sz_parameter);
	dst_function->parameter = dst_function->function + sz_function;
	memcpy(dst_function->function, src_function->function, sz_function);
	memcpy(dst_function->parameter, src_function->parameter, sz_parameter);
}

static void	DCget_trigger(DC_TRIGGER *dst_trigger, const ZBX_DC_TRIGGER *src_trigger)
{
	dst_trigger->triggerid = src_trigger->triggerid;
	dst_trigger->description = zbx_strdup(NULL, src_trigger->description);
	dst_trigger->expression_orig = zbx_strdup(NULL, src_trigger->expression);
	dst_trigger->expression = NULL;
	dst_trigger->error = zbx_strdup(NULL, src_trigger->error);
	dst_trigger->new_error = NULL;
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
}

static void	DCclean_trigger(DC_TRIGGER *trigger)
{
	zbx_free(trigger->new_error);
	zbx_free(trigger->error);
	zbx_free(trigger->expression_orig);
	zbx_free(trigger->description);
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
		if (NULL == (dc_item = zbx_hashset_search(&config->items, &itemids[i])) ||
				NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
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

void	DCconfig_get_triggers_by_triggerids(DC_TRIGGER *triggers, const zbx_uint64_t *triggerids, int *errcode,
		size_t num)
{
	size_t		i;
	ZBX_DC_TRIGGER	*dc_trigger;

	LOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_trigger = zbx_hashset_search(&config->triggers, &triggerids[i])))
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
 * Function: DCconfig_set_item_db_state                                       *
 *                                                                            *
 * Purpose: set item db_state and db_error                                    *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_set_item_db_state(zbx_uint64_t itemid, unsigned char state, const char *error)
{
	ZBX_DC_ITEM	*dc_item;

	LOCK_CACHE;

	if (NULL != (dc_item = zbx_hashset_search(&config->items, &itemid)))
	{
		dc_item->db_state = state;
		DCstrpool_replace(1, &dc_item->db_error, error);
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
void	DCconfig_get_functions_by_functionids(DC_FUNCTION *functions, zbx_uint64_t *functionids, int *errcodes, size_t num)
{
	size_t			i;
	const ZBX_DC_FUNCTION	*dc_function;

	LOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_function = zbx_hashset_search(&config->functions, &functionids[i])))
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
 * Function: DCconfig_lock_triggers_by_itemids                                *
 *                                                                            *
 * Purpose: Lock triggers for specified items so that multiple processes do   *
 *          not process one trigger simultaneously. Otherwise, this leads to  *
 *          problems like multiple successive OK events or escalations being  *
 *          started and not cancelled, because they are not seen in parallel  *
 *          transactions.                                                     *
 *                                                                            *
 * Parameters: itemids     - [IN/OUT] list of item IDs a history syncer       *
 *                                    wishes to take for processing; on       *
 *                                    output, the elements are set to 0 if    *
 *                                    the corresponding item cannot be taken  *
 *             itemids_num - [IN] number of such item IDs                     *
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
 ******************************************************************************/
void	DCconfig_lock_triggers_by_itemids(zbx_uint64_t *itemids, int itemids_num, zbx_vector_uint64_t *triggerids)
{
	int			i, j;
	const ZBX_DC_ITEM	*dc_item;
	ZBX_DC_TRIGGER		*dc_trigger;

	triggerids->values_num = 0;

	LOCK_CACHE;

	for (i = 0; i < itemids_num; i++)
	{
		if (NULL == (dc_item = zbx_hashset_search(&config->items, &itemids[i])) || NULL == dc_item->triggers)
			continue;

		for (j = 0; NULL != (dc_trigger = dc_item->triggers[j]); j++)
		{
			if (TRIGGER_STATUS_ENABLED != dc_trigger->status)
				continue;

			if (1 == dc_trigger->locked)
			{
				itemids[i] = 0;
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
		if (NULL == (dc_trigger = zbx_hashset_search(&config->triggers, &triggerids->values[i])))
			continue;

		dc_trigger->locked = 0;
	}

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
		const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs, char **errors, int itemids_num)
{
	int			i, j, found;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_TRIGGER	*dc_trigger;
	DC_TRIGGER		*trigger;

	LOCK_CACHE;

	for (i = 0; i < itemids_num; i++)
	{
		if (NULL == (dc_item = zbx_hashset_search(&config->items, &itemids[i])) || NULL == dc_item->triggers)
			continue;

		for (j = 0; NULL != (dc_trigger = dc_item->triggers[j]); j++)
		{
			if (TRIGGER_STATUS_ENABLED != dc_trigger->status)
				continue;

			trigger = DCfind_id(trigger_info, dc_trigger->triggerid, sizeof(DC_TRIGGER), &found);

			if (0 == found)
			{
				DCget_trigger(trigger, dc_trigger);
				zbx_vector_ptr_append(trigger_order, trigger);
			}

			if (trigger->timespec.sec < timespecs[i].sec ||
					(trigger->timespec.sec == timespecs[i].sec &&
					trigger->timespec.ns < timespecs[i].ns))
			{
				trigger->timespec = timespecs[i];

				if (NULL != errors)
					trigger->new_error = zbx_strdup(trigger->new_error, errors[i]);
			}
		}
	}

	UNLOCK_CACHE;

	zbx_vector_ptr_sort(trigger_order, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
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
 *           yielding up to max_triggers in return. When called, the function *
 *           first unlocks triggers locked the previous time (if any), then   *
 *           starts where it left off to yield the next bunch of triggers.    *
 *                                                                            *
 *           Also see function DCconfig_lock_triggers_by_itemids(), which     *
 *           history syncer processes use to lock triggers.                   *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_get_time_based_triggers(DC_TRIGGER **trigger_info, zbx_vector_ptr_t *trigger_order, int max_triggers,
		int process_num)
{
	int			i, j, lo, hi, found;
	zbx_uint64_t		functionid;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_FUNCTION	*dc_function;
	ZBX_DC_TRIGGER		*dc_trigger;
	const ZBX_DC_HOST	*dc_host;
	DC_TRIGGER		*trigger;
	const char		*p, *q;

	LOCK_CACHE;

	if (0 == trigger_order->values_num)
	{
		*trigger_info = zbx_malloc(*trigger_info, max_triggers * sizeof(DC_TRIGGER));
		zbx_vector_ptr_reserve(trigger_order, max_triggers);

		hi = 0;
	}
	else
	{
		zbx_uint64_t	last_triggerid;

		trigger = (DC_TRIGGER *)trigger_order->values[0];
		lo = zbx_vector_ptr_nearestindex(&config->time_triggers[process_num - 1], &trigger->triggerid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		trigger = (DC_TRIGGER *)trigger_order->values[trigger_order->values_num - 1];
		last_triggerid = trigger->triggerid + 1;
		hi = zbx_vector_ptr_nearestindex(&config->time_triggers[process_num - 1], &last_triggerid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		for (i = 0, j = lo; i < trigger_order->values_num; i++)
		{
			trigger = (DC_TRIGGER *)trigger_order->values[i];

			while (j < hi)
			{
				dc_trigger = (ZBX_DC_TRIGGER *)config->time_triggers[process_num - 1].values[j];

				if (dc_trigger->triggerid >= trigger->triggerid)
					break;

				j++;
			}

			if (j < hi && dc_trigger->triggerid == trigger->triggerid)
				dc_trigger->locked = 0;
		}

		DCfree_triggers(trigger_order);
	}

	for (i = hi; i < config->time_triggers[process_num - 1].values_num; i++)
	{
		dc_trigger = (ZBX_DC_TRIGGER *)config->time_triggers[process_num - 1].values[i];

		if (TRIGGER_STATUS_ENABLED != dc_trigger->status || 1 == dc_trigger->locked)
			continue;

		found = 0;

		for (p = dc_trigger->expression; '\0' != *p; p++)
		{
			if ('{' != *p)
				continue;

			for (q = p + 1; '}' != *q && '\0' != *q; q++)
			{
				if ('0' > *q || '9' < *q)
					break;
			}

			if ('}' != *q)
				continue;

			sscanf(p + 1, ZBX_FS_UI64, &functionid);

			if (NULL != (dc_function = zbx_hashset_search(&config->functions, &functionid)) &&
					SUCCEED == is_time_function(dc_function->function) &&
					NULL != (dc_item = zbx_hashset_search(&config->items, &dc_function->itemid)) &&
					ITEM_STATUS_ACTIVE == dc_item->status &&
					NULL != (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)) &&
					HOST_STATUS_MONITORED == dc_host->status &&
					SUCCEED != DCin_maintenance_without_data_collection(dc_host, dc_item))
			{
				found = 1;
				break;
			}

			p = q;
		}

		if (1 == found)
		{
			dc_trigger->locked = 1;

			trigger = &(*trigger_info)[trigger_order->values_num];

			DCget_trigger(trigger, dc_trigger);
			zbx_timespec(&trigger->timespec);

			zbx_vector_ptr_append(trigger_order, trigger);

			if (trigger_order->values_num == max_triggers)
				break;
		}
	}

	UNLOCK_CACHE;
}

void	DCfree_triggers(zbx_vector_ptr_t *triggers)
{
	int	i;

	for (i = 0; i < triggers->values_num; i++)
		DCclean_trigger((DC_TRIGGER *)triggers->values[i]);

	triggers->values_num = 0;
}

void	DCconfig_update_interface_snmp_stats(zbx_uint64_t interfaceid, int max_snmp_succeed, int min_snmp_fail)
{
	ZBX_DC_INTERFACE	*dc_interface;

	LOCK_CACHE;

	if (NULL != (dc_interface = zbx_hashset_search(&config->interfaces, &interfaceid)) &&
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

	dc_interface = zbx_hashset_search(&config->interfaces, &interfaceid);

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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_interface_by_type(DC_INTERFACE *interface, zbx_uint64_t hostid, unsigned char type)
{
	int			res = FAIL;
	const ZBX_DC_INTERFACE	*dc_interface;
	ZBX_DC_INTERFACE_HT	*interface_ht, interface_ht_local;

	interface_ht_local.hostid = hostid;
	interface_ht_local.type = type;

	LOCK_CACHE;

	if (NULL == (interface_ht = zbx_hashset_search(&config->interfaces_ht, &interface_ht_local)))
		goto unlock;

	dc_interface = interface_ht->interface_ptr;

	DCget_interface(interface, dc_interface);

	res = SUCCEED;
unlock:
	UNLOCK_CACHE;

	return res;
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
	const char			*__function_name = "DCconfig_get_poller_nextcheck";

	int				nextcheck;
	zbx_binary_heap_t		*queue;
	const zbx_binary_heap_elem_t	*min;
	const ZBX_DC_ITEM		*dc_item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() poller_type:%d", __function_name, (int)poller_type);

	queue = &config->queues[poller_type];

	LOCK_CACHE;

	if (FAIL == zbx_binary_heap_empty(queue))
	{
		min = zbx_binary_heap_find_min(queue);
		dc_item = (const ZBX_DC_ITEM *)min->data;

		nextcheck = dc_item->nextcheck;
	}
	else
		nextcheck = FAIL;

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, nextcheck);

	return nextcheck;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_poller_items                                        *
 *                                                                            *
 * Purpose: Get array of items for selected poller                            *
 *                                                                            *
 * Parameters: poller_type - [IN] poller type (ZBX_POLLER_TYPE_...)           *
 *             items - [OUT] array of items                                   *
 *             max_items - [IN] elements in items array                       *
 *                                                                            *
 * Return value: number of items in items array                               *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 * Comments: Items leave the queue only through this function. Pollers must   *
 *           always return the items they have taken using DCrequeue_items(). *
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
		int				disable_until, old_nextcheck;
		unsigned char			old_poller_type;
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

		if (0 == config->config->refresh_unsupported && ITEM_STATE_NOTSUPPORTED == dc_item->state)
			continue;

		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
		{
			old_nextcheck = dc_item->nextcheck;
			dc_item->nextcheck = DCget_reachable_nextcheck(dc_item, now);

			DCupdate_item_queue(dc_item, dc_item->poller_type, old_nextcheck);
			continue;
		}

		if (0 == (disable_until = DCget_disable_until(dc_item, dc_host)))
		{
			if (ZBX_POLLER_TYPE_UNREACHABLE == poller_type)
			{
				old_poller_type = dc_item->poller_type;
				dc_item->poller_type = poller_by_item(dc_host->proxy_hostid, dc_item->type,
						dc_item->key);

				old_nextcheck = dc_item->nextcheck;
				dc_item->nextcheck = DCget_reachable_nextcheck(dc_item, now);

				DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
				continue;
			}
		}
		else
		{
			if (ZBX_POLLER_TYPE_NORMAL == poller_type ||
					ZBX_POLLER_TYPE_IPMI == poller_type ||
					ZBX_POLLER_TYPE_JAVA == poller_type)
			{
				old_poller_type = dc_item->poller_type;
				dc_item->poller_type = ZBX_POLLER_TYPE_UNREACHABLE;

				old_nextcheck = dc_item->nextcheck;
				if (disable_until > now)
					dc_item->nextcheck = DCget_unreachable_nextcheck(dc_item, dc_host);

				DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
				continue;
			}
			else if (disable_until > now)
			{
				old_nextcheck = dc_item->nextcheck;
				dc_item->nextcheck = DCget_unreachable_nextcheck(dc_item, dc_host);

				DCupdate_item_queue(dc_item, dc_item->poller_type, old_nextcheck);
				continue;
			}

			DCincrease_disable_until(dc_item, dc_host, now);
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

			snmpitem = zbx_hashset_search(&config->snmpitems, &dc_item->itemid);

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

	if (NULL == (dc_interface_snmpaddr = zbx_hashset_search(&config->interface_snmpaddrs, &dc_interface_snmpaddr_local)))
		goto unlock;

	*interfaceids = zbx_malloc(*interfaceids, dc_interface_snmpaddr->interfaceids.values_num * sizeof(zbx_uint64_t));

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

	if (NULL == (dc_interface = zbx_hashset_search(&config->interfaces, &interfaceid)))
		goto unlock;

	if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_interface->hostid)))
		goto unlock;

	if (HOST_STATUS_MONITORED != dc_host->status)
		goto unlock;

	if (NULL == (dc_interface_snmpitem = zbx_hashset_search(&config->interface_snmpitems, &interfaceid)))
		goto unlock;

	*items = zbx_malloc(*items, items_alloc * sizeof(DC_ITEM));

	for (i = 0; i < dc_interface_snmpitem->itemids.values_num; i++)
	{
		if (NULL == (dc_item = zbx_hashset_search(&config->items, &dc_interface_snmpitem->itemids.values[i])))
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
			*items = zbx_realloc(*items, items_alloc * sizeof(DC_ITEM));
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

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_config_data                                         *
 *                                                                            *
 * Purpose: get config table data                                             *
 *                                                                            *
 * Return value: pointer to the returned data                                 *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 * Comments: data must be of correct data type                                *
 *                                                                            *
 ******************************************************************************/
void	*DCconfig_get_config_data(void *data, int type)
{
	LOCK_CACHE;

	switch (type)
	{
		case CONFIG_REFRESH_UNSUPPORTED:
			*(int *)data = config->config->refresh_unsupported;
			break;
		case CONFIG_DISCOVERY_GROUPID:
			*(zbx_uint64_t *)data = config->config->discovery_groupid;
			break;
		case CONFIG_SNMPTRAP_LOGGING:
			*(unsigned char *)data = config->config->snmptrap_logging;
			break;
	}

	UNLOCK_CACHE;

	return data;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_config_hk                                           *
 *                                                                            *
 * Purpose: get housekeeping configuration data                               *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_get_config_hk(zbx_config_hk_t *data)
{
	LOCK_CACHE;

	*data = config->config->hk;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_trigger_severity_name                                      *
 *                                                                            *
 * Purpose: get trigger severity name                                         *
 *                                                                            *
 * Parameters: trigger    - [IN] a trigger data with priority field;          *
 *                               TRIGGER_SEVERITY_*                           *
 *             replace_to - [OUT] pointer to a buffer that will receive       *
 *                          a null-terminated trigger severity string         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev, Rudolfs Kreicbergs                            *
 *                                                                            *
 ******************************************************************************/
int	DCget_trigger_severity_name(unsigned char priority, char **replace_to)
{
	if (TRIGGER_SEVERITY_COUNT <= priority)
		return FAIL;

	LOCK_CACHE;

	*replace_to = zbx_strdup(*replace_to, config->config->severity_name[priority]);

	UNLOCK_CACHE;

	return SUCCEED;
}

static void	DCrequeue_reachable_item(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host, int lastclock)
{
	unsigned char	old_poller_type;
	int		old_nextcheck;

	old_nextcheck = dc_item->nextcheck;
	dc_item->nextcheck = DCget_reachable_nextcheck(dc_item, lastclock);

	if (ZBX_NO_POLLER == dc_item->poller_type)
		return;

	if (ZBX_LOC_POLLER == dc_item->location)
		dc_item->location = ZBX_LOC_NOWHERE;

	old_poller_type = dc_item->poller_type;

	if (ZBX_POLLER_TYPE_UNREACHABLE == dc_item->poller_type)
		dc_item->poller_type = poller_by_item(dc_host->proxy_hostid, dc_item->type, dc_item->key);

	DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
}

static void	DCrequeue_unreachable_item(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host)
{
	unsigned char	old_poller_type;
	int		old_nextcheck;

	old_nextcheck = dc_item->nextcheck;
	dc_item->nextcheck = DCget_unreachable_nextcheck(dc_item, dc_host);

	if (ZBX_NO_POLLER == dc_item->poller_type)
		return;

	if (ZBX_LOC_POLLER == dc_item->location)
		dc_item->location = ZBX_LOC_NOWHERE;

	old_poller_type = dc_item->poller_type;

	if (ZBX_POLLER_TYPE_NORMAL == dc_item->poller_type ||
			ZBX_POLLER_TYPE_IPMI == dc_item->poller_type ||
			ZBX_POLLER_TYPE_JAVA == dc_item->poller_type)
	{
		dc_item->poller_type = ZBX_POLLER_TYPE_UNREACHABLE;
	}

	DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
}

void	DCrequeue_items(zbx_uint64_t *itemids, unsigned char *states, int *lastclocks, zbx_uint64_t *lastlogsizes,
		int *mtimes, int *errcodes, size_t num)
{
	size_t		i;
	ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (FAIL == errcodes[i])
			continue;

		if (NULL == (dc_item = zbx_hashset_search(&config->items, &itemids[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		dc_item->state = states[i];
		dc_item->lastclock = lastclocks[i];
		if (NULL != lastlogsizes)
			dc_item->lastlogsize = lastlogsizes[i];
		if (NULL != mtimes)
			dc_item->mtime = mtimes[i];

		if (SUCCEED != is_counted_in_item_queue(dc_item->type, dc_item->key))
			continue;

		switch (errcodes[i])
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
			case CONFIG_ERROR:
				DCrequeue_reachable_item(dc_item, dc_host, lastclocks[i]);
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
				DCrequeue_unreachable_item(dc_item, dc_host);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DChost_get_availability                                          *
 *                                                                            *
 * Purpose: get host availability data based on the specified item type       *
 *                                                                            *
 * Parameters: dc_host      - [IN] the host                                   *
 *             type         - [IN] the item type                              *
 *             availability - [OUT] the host availability data                *
 *                                                                            *
 * Return value: SUCCEED - the host availability data was retrieved           *
 *                         successfully                                       *
 *               FAIL    - failed to retrieve host availability data,         *
 *                         unrecognized item type was specified               *
 *                                                                            *
 ******************************************************************************/
static int	DChost_get_availability(const ZBX_DC_HOST *dc_host, unsigned char type,
		zbx_host_availability_t *availability)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			availability->errors_from = dc_host->errors_from;
			availability->available = dc_host->available;
			availability->disable_until = dc_host->disable_until;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			availability->errors_from = dc_host->snmp_errors_from;
			availability->available = dc_host->snmp_available;
			availability->disable_until = dc_host->snmp_disable_until;
			break;
		case ITEM_TYPE_IPMI:
			availability->errors_from = dc_host->ipmi_errors_from;
			availability->available = dc_host->ipmi_available;
			availability->disable_until = dc_host->ipmi_disable_until;
			break;
		case ITEM_TYPE_JMX:
			availability->errors_from = dc_host->jmx_errors_from;
			availability->available = dc_host->jmx_available;
			availability->disable_until = dc_host->jmx_disable_until;
			break;
		default:
			return FAIL;
	}

	availability->type = type;
	availability->hostid = dc_host->hostid;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DChost_set_availability                                          *
 *                                                                            *
 * Purpose: set host availability data based on the specified item type       *
 *                                                                            *
 * Parameters: dc_host      - [OUT] the host                                  *
 *             availability - [IN] the host availability data                 *
 *                                                                            *
 * Return value: SUCCEED - the host availability data was set successfully    *
 *               FAIL    - failed to set host availability data,              *
 *                         unrecognized item type was specified               *
 *                                                                            *
 ******************************************************************************/
static int	DChost_set_availability(ZBX_DC_HOST *dc_host, const zbx_host_availability_t *availability)
{
	switch (availability->type)
	{
		case ITEM_TYPE_ZABBIX:
			dc_host->errors_from = availability->errors_from;
			dc_host->available = availability->available;
			dc_host->disable_until = availability->disable_until;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			dc_host->snmp_errors_from = availability->errors_from;
			dc_host->snmp_available = availability->available;
			dc_host->snmp_disable_until = availability->disable_until;
			break;
		case ITEM_TYPE_IPMI:
			dc_host->ipmi_errors_from = availability->errors_from;
			dc_host->ipmi_available = availability->available;
			dc_host->ipmi_disable_until = availability->disable_until;
			break;
		case ITEM_TYPE_JMX:
			dc_host->jmx_errors_from = availability->errors_from;
			dc_host->jmx_available = availability->available;
			dc_host->jmx_disable_until = availability->disable_until;
			break;
		default:
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DChost_activate                                                  *
 *                                                                            *
 * Purpose: set host as available for the checks of the specified item type   *
 *                                                                            *
 * Parameters: in  - [IN/OUT] IN: the caller's host availability data         *
 *                            OUT: the host availability data in cache        *
 *                            before changes                                  *
 *             out - [OUT] the host availability data after changes           *
 *                                                                            *
 * Return value: SUCCEED - the host was activated successfully                *
 *               FAIL    - failed to activate host, host not found            *
 *                                                                            *
 ******************************************************************************/
int	DChost_activate(zbx_host_availability_t *in, zbx_host_availability_t *out)
{
	int		ret = FAIL;
	ZBX_DC_HOST	*dc_host;

	/* don't try activating host if there were no errors detected */
	if (0 == in->errors_from && HOST_AVAILABLE_TRUE == in->available)
		goto out;

	LOCK_CACHE;

	if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &in->hostid)))
		goto unlock;

	if (HOST_STATUS_MONITORED != dc_host->status)
		goto unlock;

	DChost_get_availability(dc_host, in->type, in);

	out->hostid = in->hostid;
	out->type = in->type;
	out->errors_from = 0;
	out->available = HOST_AVAILABLE_TRUE;
	out->disable_until = 0;

	DChost_set_availability(dc_host, out);

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
 * Purpose: attempt to set host as unavailable for the checks of the          *
 *          specified item type based on the time the checks are failing      *
 *                                                                            *
 * Parameters: ts  - [IN] the failure timestamp                               *
 *             in  - [IN/OUT] IN: the caller's host availability data         *
 *                            OUT: the host availability data in cache        *
 *                            before changes                                  *
 *             out - [OUT] the host availability data after changes           *
 *                                                                            *
 * Return value: SUCCEED - the host was deactivated successfully              *
 *               FAIL    - failed to activate host, host not found            *
 *                                                                            *
 ******************************************************************************/
int	DChost_deactivate(const zbx_timespec_t *ts, zbx_host_availability_t *in, zbx_host_availability_t *out)
{
	int		ret = FAIL;
	ZBX_DC_HOST	*dc_host;

	/* don't try deactivating host if the unreachable delay has not passed since the first error */
	if (CONFIG_UNREACHABLE_DELAY > ts->sec - in->errors_from)
		goto out;

	LOCK_CACHE;

	if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &in->hostid)))
		goto unlock;

	if (HOST_STATUS_MONITORED != dc_host->status)
		goto unlock;

	DChost_get_availability(dc_host, in->type, in);
	*out = *in;

	if (0 == out->errors_from)
	{
		/* first error, schedule next unreachable check */
		out->errors_from = ts->sec;
		out->disable_until = ts->sec + CONFIG_UNREACHABLE_DELAY;
	}
	else
	{
		/* Check if other pollers haven't already attempted deactivating host. */
		/* In that case should wait the initial unreachable delay before       */
		/* trying to make it unavailable.                                      */
		if (CONFIG_UNREACHABLE_DELAY <= ts->sec - out->errors_from)
		{
			/* repeating error */
			if (CONFIG_UNREACHABLE_PERIOD > ts->sec - out->errors_from)
			{
				/* leave host available, schedule next unreachable check */
				out->disable_until = ts->sec + CONFIG_UNREACHABLE_DELAY;
			}
			else
			{
				/* make host unavailable, schedule next unavailable check */
				out->disable_until = ts->sec + CONFIG_UNAVAILABLE_DELAY;
				out->available = HOST_AVAILABLE_FALSE;
			}
		}
	}

	DChost_set_availability(dc_host, out);

	ret = SUCCEED;
unlock:
	UNLOCK_CACHE;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DChost_update_availability                                       *
 *                                                                            *
 * Purpose: update hosts availability in configuration cache                  *
 *                                                                            *
 * Parameters: availability     - [IN] an array containing host availability  *
 *                                data                                        *
 *             availability_num - [IN] the number of items in availability    *
 *                                array                                       *
 *                                                                            *
 ******************************************************************************/
void	DChost_update_availability(const zbx_host_availability_t *availability, int availability_num)
{
	int		i;
	ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	for (i = 0; i < availability_num; i++)
	{
		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &availability[i].hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		DChost_set_availability(dc_host, &availability[i]);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Comments: helper function for DCconfig_check_trigger_dependencies()        *
 *                                                                            *
 ******************************************************************************/
static int	DCconfig_check_trigger_dependencies_rec(const ZBX_DC_TRIGGER_DEPLIST *trigdep, int level)
{
	int				i;
	const ZBX_DC_TRIGGER		*next_trigger;
	const ZBX_DC_TRIGGER_DEPLIST	*next_trigdep;

	if (32 < level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "recursive trigger dependency is too deep (triggerid:" ZBX_FS_UI64 ")",
				trigdep->triggerid);
		return SUCCEED;
	}

	if (NULL != trigdep->dependencies)
	{
		for (i = 0; NULL != (next_trigdep = trigdep->dependencies[i]); i++)
		{
			if (NULL != (next_trigger = next_trigdep->trigger) &&
					TRIGGER_VALUE_PROBLEM == next_trigger->value &&
					TRIGGER_STATE_NORMAL == next_trigger->state)
			{
				return FAIL;
			}

			if (FAIL == DCconfig_check_trigger_dependencies_rec(next_trigdep, level + 1))
				return FAIL;
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

	if (NULL != (trigdep = zbx_hashset_search(&config->trigdeps, &triggerid)))
		ret = DCconfig_check_trigger_dependencies_rec(trigdep, 0);

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
	ZBX_DC_TRIGGER			*trigger;
	const ZBX_DC_TRIGGER		*next_trigger;
	const ZBX_DC_TRIGGER_DEPLIST	*next_trigdep;

	if (32 < level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "recursive trigger dependency is too deep (triggerid:" ZBX_FS_UI64 ")",
				trigdep->triggerid);
		goto exit;
	}

	trigger = trigdep->trigger;

	if (NULL != trigger && 0 == trigger->topoindex)
	{
		zabbix_log(LOG_LEVEL_CRIT, "trigger dependencies contain a cycle (triggerid:" ZBX_FS_UI64 ")",
				trigdep->triggerid);
		goto exit;
	}

	if (NULL != trigger)
		trigger->topoindex = 0;

	for (i = 0; NULL != (next_trigdep = trigdep->dependencies[i]); i++)
	{
		if (NULL != (next_trigger = next_trigdep->trigger) && 1 < (next_topoindex = next_trigger->topoindex))
			goto next;

		if (NULL == next_trigdep->dependencies)
			continue;

		next_topoindex = DCconfig_sort_triggers_topologically_rec(next_trigdep, level + 1);
next:
		if (topoindex < next_topoindex + 1)
			topoindex = next_topoindex + 1;
	}

	if (NULL != trigger)
		trigger->topoindex = topoindex;
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
static void	DCconfig_sort_triggers_topologically()
{
	zbx_hashset_iter_t		iter;
	ZBX_DC_TRIGGER			*trigger;
	const ZBX_DC_TRIGGER_DEPLIST	*trigdep;

	zbx_hashset_iter_reset(&config->trigdeps, &iter);

	while (NULL != (trigdep = zbx_hashset_iter_next(&iter)))
	{
		trigger = trigdep->trigger;

		if ((NULL != trigger && 1 < trigger->topoindex) || NULL == trigdep->dependencies)
			continue;

		DCconfig_sort_triggers_topologically_rec(trigdep, 0);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_set_trigger_value                                       *
 *                                                                            *
 * Purpose: set trigger value, value flags, and error                         *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
void	DCconfig_set_trigger_value(zbx_uint64_t triggerid, unsigned char value,
		unsigned char state, const char *error, int *lastchange)
{
	ZBX_DC_TRIGGER	*dc_trigger;

	LOCK_CACHE;

	if (NULL != (dc_trigger = zbx_hashset_search(&config->triggers, &triggerid)))
	{
		DCstrpool_replace(1, &dc_trigger->error, error);
		dc_trigger->value = value;
		dc_trigger->state = state;
		if (NULL != lastchange)
			dc_trigger->lastchange = *lastchange;
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
		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &hostids[i])))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		if (dc_host->maintenance_status != maintenance_status)
			dc_host->maintenance_from = maintenance_from;

		if (MAINTENANCE_TYPE_NODATA == dc_host->maintenance_type && MAINTENANCE_TYPE_NODATA != maintenance_type)
		{
			/* Store time at which no-data maintenance ended for the host (either */
			/* because no-data maintenance ended or because maintenance type was */
			/* changed to normal), this is needed for nodata() trigger function. */
			/* Also, recalculate "nextcheck" time for items for which we usually */
			/* update it upon receiving a value (i.e., items without a poller). */

			ZBX_DC_ITEM		*dc_item;
			zbx_hashset_iter_t	iter;

			dc_host->data_expected_from = now;

			zbx_hashset_iter_reset(&config->items, &iter);

			while (NULL != (dc_item = zbx_hashset_iter_next(&iter)))
			{
				if (SUCCEED != uint64_array_exists(hostids, hostids_num, dc_item->hostid))
					continue;

				if (ITEM_STATUS_ACTIVE != dc_item->status || ZBX_NO_POLLER != dc_item->poller_type)
					continue;

				dc_item->nextcheck = DCget_reachable_nextcheck(dc_item, now);
			}
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

	const zbx_mem_info_t	*strpool_mem;

	strpool_mem = zbx_strpool_info()->mem_info;

	switch (request)
	{
		case ZBX_CONFSTATS_BUFFER_TOTAL:
			value_uint = config_mem->orig_size + strpool_mem->orig_size;
			return &value_uint;
		case ZBX_CONFSTATS_BUFFER_USED:
			value_uint = (config_mem->orig_size + strpool_mem->orig_size) -
					(config_mem->free_size + strpool_mem->free_size);
			return &value_uint;
		case ZBX_CONFSTATS_BUFFER_FREE:
			value_uint = config_mem->free_size + strpool_mem->free_size;
			return &value_uint;
		case ZBX_CONFSTATS_BUFFER_PFREE:
			value_double = 100.0 * ((double)(config_mem->free_size + strpool_mem->free_size) /
							(config_mem->orig_size + strpool_mem->orig_size));
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

	if (NULL != (host = zbx_hashset_search(&config->hosts, &src_proxy->hostid)))
		strscpy(dst_proxy->host, host->host);
	else
		*dst_proxy->host = '\0';

	interface_ht_local.hostid = src_proxy->hostid;
	interface_ht_local.type = INTERFACE_TYPE_UNKNOWN;

	if (NULL != (interface_ht = zbx_hashset_search(&config->interfaces_ht, &interface_ht_local)))
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

		if (dc_proxy->proxy_config_nextcheck > now && dc_proxy->proxy_data_nextcheck > now)
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
int	DCconfig_get_proxypoller_nextcheck()
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

		nextcheck = (dc_proxy->proxy_config_nextcheck < dc_proxy->proxy_data_nextcheck) ?
				dc_proxy->proxy_config_nextcheck : dc_proxy->proxy_data_nextcheck;
	}
	else
		nextcheck = FAIL;

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, nextcheck);

	return nextcheck;
}

void	DCrequeue_proxy(zbx_uint64_t hostid, unsigned char update_nextcheck)
{
	const char	*__function_name = "DCrequeue_proxy";
	time_t		now;
	ZBX_DC_PROXY	*dc_proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() update_nextcheck:%d", __function_name, (int)update_nextcheck);

	now = time(NULL);

	LOCK_CACHE;

	if (NULL != (dc_proxy = zbx_hashset_search(&config->proxies, &hostid)))
	{
		if (0 != (0x01 & update_nextcheck))
		{
			dc_proxy->proxy_config_nextcheck = (int)calculate_proxy_nextcheck(
					hostid, CONFIG_PROXYCONFIG_FREQUENCY, now);
		}
		if (0 != (0x02 & update_nextcheck))
		{
			dc_proxy->proxy_data_nextcheck = (int)calculate_proxy_nextcheck(
					hostid, CONFIG_PROXYDATA_FREQUENCY, now);
		}

		if (ZBX_LOC_POLLER == dc_proxy->location)
			dc_proxy->location = ZBX_LOC_NOWHERE;

		DCupdate_proxy_queue(dc_proxy);
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	DCget_host_macro(zbx_uint64_t *hostids, int host_num, const char *macro, char **replace_to)
{
	const char	*__function_name = "DCget_host_macro";

	int			i, j, ret = FAIL;
	zbx_vector_uint64_t	templateids;
	ZBX_DC_HMACRO_HM	*hmacro_hm, hmacro_hm_local;
	ZBX_DC_HTMPL		*htmpl;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:'%s'", __function_name, macro);

	if (0 == host_num)
		goto clean;

	zbx_vector_uint64_create(&templateids);
	zbx_vector_uint64_reserve(&templateids, 32);

	hmacro_hm_local.macro = macro;

	for (i = 0; i < host_num; i++)
	{
		hmacro_hm_local.hostid = hostids[i];

		if (NULL != (hmacro_hm = zbx_hashset_search(&config->hmacros_hm, &hmacro_hm_local)))
		{
			zbx_free(*replace_to);
			*replace_to = strdup(hmacro_hm->hmacro_ptr->value);
			ret = SUCCEED;
			break;
		}

		if (NULL != (htmpl = zbx_hashset_search(&config->htmpls, &hostids[i])))
		{
			for (j = 0; j < htmpl->templateids.values_num; j++)
				zbx_vector_uint64_append(&templateids, htmpl->templateids.values[j]);
		}
	}

	if (FAIL == ret && 0 != templateids.values_num)
	{
		zbx_vector_uint64_sort(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		ret = DCget_host_macro(templateids.values, templateids.values_num, macro, replace_to);	/* recursion */
	}

	zbx_vector_uint64_destroy(&templateids);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	DCget_global_macro(const char *macro, char **replace_to)
{
	const char	*__function_name = "DCget_global_macro";

	ZBX_DC_GMACRO_M	*gmacro_m, gmacro_m_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:'%s'", __function_name, macro);

	gmacro_m_local.macro = macro;

	if (NULL != (gmacro_m = zbx_hashset_search(&config->gmacros_m, &gmacro_m_local)))
	{
		zbx_free(*replace_to);
		*replace_to = strdup(gmacro_m->gmacro_ptr->value);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	DCget_user_macro(zbx_uint64_t *hostids, int host_num, const char *macro, char **replace_to)
{
	const char	*__function_name = "DCget_user_macro";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:'%s'", __function_name, macro);

	LOCK_CACHE;

	if (FAIL == DCget_host_macro(hostids, host_num, macro, replace_to))
		DCget_global_macro(macro, replace_to);

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_delta_items                                                *
 *                                                                            *
 * Purpose: Get a copy of delta item history stored in configuration cache    *
 *                                                                            *
 * Parameters: items - [OUT] the copy of item history                         *
 *             ids   - [IN] a vector of item ids to get the history for       *
 *                                                                            *
 * Comments: The hashset must be created by the caller like:                  *
 *            zbx_hashset_create(items, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,   *
 *                               ZBX_DEFAULT_UINT64_COMPARE_FUNC)             *
 *                                                                            *
 ******************************************************************************/
void	DCget_delta_items(zbx_hashset_t *items, const zbx_vector_uint64_t *ids)
{
	ZBX_DC_DELTAITEM	*deltaitem;
	int			i;

	LOCK_CACHE;

	/* only FLOAT and UINT64 value types can be used for delta calculations, */
	/* so just copying data is safe                                          */
	for (i = 0; i < ids->values_num; i++)
	{
		if (NULL != (deltaitem = zbx_hashset_search(&config->deltaitems, &ids->values[i])))
			zbx_hashset_insert(items, deltaitem, sizeof(ZBX_DC_DELTAITEM));
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCset_delta_items                                                *
 *                                                                            *
 * Purpose: Updates delta item history data in cache                          *
 *                                                                            *
 * Parameters: items - [IN] the new delta item history data. If the timestamp *
 *                          seconds is set to 0 then item history data is     *
 *                          removed from cache.                               *
 *                                                                            *
 ******************************************************************************/
void	DCset_delta_items(zbx_hashset_t *items)
{
	zbx_hashset_iter_t	iter;
	ZBX_DC_DELTAITEM	*deltaitem, *item;

	zbx_hashset_iter_reset(items, &iter);

	LOCK_CACHE;

	while (NULL != (item = zbx_hashset_iter_next(&iter)))
	{
		if (NULL == (deltaitem = zbx_hashset_search(&config->deltaitems, &item->itemid)))
		{
			if (0 != item->timestamp.sec)
				zbx_hashset_insert(&config->deltaitems, item, sizeof(ZBX_DC_DELTAITEM));
		}
		else
		{
			if (0 != item->timestamp.sec)
			{
				if (0 < zbx_timespec_compare(&item->timestamp, &deltaitem->timestamp))
				{
					deltaitem->timestamp = item->timestamp;
					deltaitem->value = item->value;
				}
			}
			else
				zbx_hashset_remove(&config->deltaitems, &item->itemid);
		}
	}

	UNLOCK_CACHE;
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
 *             from  - [IN] the minimum delay time in seconds or -1 if there  *
 *                          is no minimum limit                               *
 *             to    - [IN] the maximum delay time in seconds or -1 if there  *
 *                          is no maximum limit                               *
 *                                                                            *
 * Return value: the number of delayed items                                  *
 *                                                                            *
 ******************************************************************************/
int	DCget_item_queue(zbx_vector_ptr_t *queue, int from, int to)
{
	zbx_hashset_iter_t	iter;
	const ZBX_DC_ITEM	*dc_item;
	int			now, nitems = 0;
	zbx_queue_item_t	*queue_item;

	now = time(NULL);

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (dc_item = zbx_hashset_iter_next(&iter)))
	{
		const ZBX_DC_HOST	*dc_host;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (SUCCEED != is_counted_in_item_queue(dc_item->type, dc_item->key))
			continue;

		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
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

		if ((-1 != from && from > now - dc_item->nextcheck) || (-1 != to && now - dc_item->nextcheck >= to))
			continue;

		if (NULL != queue)
		{
			queue_item = zbx_malloc(NULL, sizeof(zbx_queue_item_t));
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
 * Function: DCget_item_count                                                 *
 *                                                                            *
 * Purpose: return the number of active items                                 *
 *                                                                            *
 * Return value: the number of active items                                   *
 *                                                                            *
 ******************************************************************************/
int	DCget_item_count()
{
	int			count = 0;
	zbx_hashset_iter_t	iter;
	const ZBX_DC_ITEM	*dc_item;

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (dc_item = zbx_hashset_iter_next(&iter)))
	{
		const ZBX_DC_HOST	*dc_host;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (ZBX_FLAG_DISCOVERY_NORMAL != dc_item->flags && ZBX_FLAG_DISCOVERY_CREATED != dc_item->flags)
			continue;

		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		count++;
	}

	UNLOCK_CACHE;

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_item_unsupported_count                                     *
 *                                                                            *
 * Purpose: return the number of active unsupported items                     *
 *                                                                            *
 * Return value: the number of active unsupported items                       *
 *                                                                            *
 ******************************************************************************/
int	DCget_item_unsupported_count()
{
	int			count = 0;
	zbx_hashset_iter_t	iter;
	const ZBX_DC_ITEM	*dc_item;

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (dc_item = zbx_hashset_iter_next(&iter)))
	{
		const ZBX_DC_HOST	*dc_host;

		if (ITEM_STATUS_ACTIVE != dc_item->status || ITEM_STATE_NOTSUPPORTED != dc_item->state)
			continue;

		if (ZBX_FLAG_DISCOVERY_NORMAL != dc_item->flags && ZBX_FLAG_DISCOVERY_CREATED != dc_item->flags)
			continue;

		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		count++;
	}

	UNLOCK_CACHE;

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_trigger_count                                              *
 *                                                                            *
 * Purpose: return the number of active triggers                              *
 *                                                                            *
 * Return value: the number of active triggers                                *
 *                                                                            *
 ******************************************************************************/
int	DCget_trigger_count()
{
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		functionid;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_FUNCTION	*dc_function;
	const ZBX_DC_TRIGGER	*dc_trigger;
	const ZBX_DC_HOST	*dc_host;
	const char		*p, *q;
	int			count = 0;

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->triggers, &iter);

	while (NULL != (dc_trigger = zbx_hashset_iter_next(&iter)))
	{
		if (TRIGGER_STATUS_ENABLED != dc_trigger->status)
			continue;

		for (p = dc_trigger->expression; '\0' != *p; p++)
		{
			if ('{' != *p)
				continue;

			for (q = p + 1; '}' != *q && '\0' != *q; q++)
			{
				if ('0' > *q || '9' < *q)
					break;
			}

			if ('}' != *q)
				continue;

			sscanf(p + 1, ZBX_FS_UI64, &functionid);

			if (NULL == (dc_function = zbx_hashset_search(&config->functions, &functionid)) ||
					NULL == (dc_item = zbx_hashset_search(&config->items, &dc_function->itemid)) ||
					ITEM_STATUS_ACTIVE != dc_item->status ||
					NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)) ||
					HOST_STATUS_MONITORED != dc_host->status)
			{
				goto next;
			}

			p = q;
		}

		count++;
next:;
	}

	UNLOCK_CACHE;

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_host_count                                                 *
 *                                                                            *
 * Purpose: return the number of monitored hosts                              *
 *                                                                            *
 * Return value: the number of monitored hosts                                *
 *                                                                            *
 ******************************************************************************/
int	DCget_host_count()
{
	int			nhosts = 0;
	zbx_hashset_iter_t	iter;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->hosts, &iter);

	while (NULL != (dc_host = zbx_hashset_iter_next(&iter)))
	{
		if (HOST_STATUS_MONITORED == dc_host->status)
			nhosts++;
	}

	UNLOCK_CACHE;

	return nhosts;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_required_performance                                       *
 *                                                                            *
 * Purpose: calculate the required server performance (values per second)     *
 *                                                                            *
 * Return value: the required nvps number                                     *
 *                                                                            *
 ******************************************************************************/
double	DCget_required_performance()
{
	double			nvps = 0;
	zbx_hashset_iter_t	iter;
	const ZBX_DC_ITEM	*dc_item;

	LOCK_CACHE;

	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (dc_item = zbx_hashset_iter_next(&iter)))
	{
		const ZBX_DC_HOST	*dc_host;

		if (ITEM_STATUS_ACTIVE != dc_item->status || 0 == dc_item->delay)
			continue;

		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		nvps += 1.0 / dc_item->delay;
	}

	UNLOCK_CACHE;

	return nvps;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_functions_hostids                                          *
 *                                                                            *
 * Purpose: get hosts with items associated with a set of functions           *
 *                                                                            *
 * Parameters: hostids     - [OUT] a vector of host identifiers               *
 *             functionids - [IN] a vector containing source function ids     *
 *                                                                            *
 ******************************************************************************/
void	DCget_functions_hostids(zbx_vector_uint64_t *hostids, const zbx_vector_uint64_t *functionids)
{
	ZBX_DC_FUNCTION		*dc_function;
	ZBX_DC_ITEM		*dc_item;
	int			i;

	LOCK_CACHE;

	for (i = 0; i < functionids->values_num; i++)
	{
		if (NULL == (dc_function = zbx_hashset_search(&config->functions, &functionids->values[i])))
			continue;

		if (NULL != (dc_item = zbx_hashset_search(&config->items, &dc_function->itemid)))
			zbx_vector_uint64_append(hostids, dc_item->hostid);
	}

	UNLOCK_CACHE;

	zbx_vector_uint64_sort(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_expressions                                                *
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

		if (NULL != (regexp = zbx_hashset_search(&config->regexps, &search_regexp)))
		{
			for (i = 0; i < regexp->expressionids.values_num; i++)
			{
				zbx_uint64_t		expressionid = regexp->expressionids.values[i];
				zbx_expression_t	*rxp;

				if (NULL == (expression = zbx_hashset_search(&config->expressions, &expressionid)))
					continue;

				rxp = zbx_malloc(NULL, sizeof(zbx_expression_t));
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

	if (NULL == (dc_item = zbx_hashset_search(&config->items, &itemid)))
		goto unlock;

	if (ITEM_STATUS_ACTIVE != dc_item->status)
		goto unlock;

	if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		goto unlock;

	if (HOST_STATUS_MONITORED != dc_host->status)
		goto unlock;

	*seconds = MAX(dc_item->data_expected_from, dc_host->data_expected_from);

	ret = SUCCEED;
unlock:
	UNLOCK_CACHE;

	return ret;
}
