/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "log.h"
#include "threads.h"

#include "dbcache.h"
#include "ipc.h"
#include "mutexs.h"

#include "memalloc.h"
#include "strpool.h"

#include "zbxalgo.h"

#define	LOCK_CACHE	zbx_mutex_lock(&config_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(&config_lock)

#define	ZBX_DC_ITEM		struct zbx_dc_item
#define	ZBX_DC_ITEM_HK		struct zbx_dc_item_hk
#define	ZBX_DC_SNMPITEM		struct zbx_dc_snmpitem
#define	ZBX_DC_IPMIITEM		struct zbx_dc_ipmiitem
#define	ZBX_DC_FLEXITEM		struct zbx_dc_flexitem
#define	ZBX_DC_TRAPITEM		struct zbx_dc_trapitem
#define	ZBX_DC_LOGITEM		struct zbx_dc_logitem
#define	ZBX_DC_DBITEM		struct zbx_dc_dbitem
#define	ZBX_DC_SSHITEM		struct zbx_dc_sshitem
#define	ZBX_DC_TELNETITEM	struct zbx_dc_telnetitem
#define	ZBX_DC_CALCITEM		struct zbx_dc_calcitem

#define	ZBX_DC_HOST		struct zbx_dc_host
#define	ZBX_DC_HOST_PH		struct zbx_dc_host_ph
#define	ZBX_DC_IPMIHOST		struct zbx_dc_ipmihost

#define	ZBX_DC_CONFIG		struct zbx_dc_config

#define ZBX_LOC_NOWHERE	0
#define ZBX_LOC_QUEUE	1
#define ZBX_LOC_POLLER	2

ZBX_DC_ITEM
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	hostid;
	const char	*key;			/* interned; key[ITEM_KEY_LEN_MAX];					*/
	int		delay;
	int		nextcheck;
	unsigned char	type;
	unsigned char	data_type;
	unsigned char	value_type;
	unsigned char	poller_type;
	unsigned char	status;
	unsigned char	location;
};

ZBX_DC_ITEM_HK
{
	zbx_uint64_t	hostid;
	const char	*key;			/* interned; key[ITEM_KEY_LEN_MAX];					*/
	ZBX_DC_ITEM	*item_ptr;
};

ZBX_DC_SNMPITEM
{
	zbx_uint64_t	itemid;
	const char	*snmp_community;	/* interned; snmp_community[ITEM_SNMP_COMMUNITY_LEN_MAX];		*/
	const char	*snmp_oid;		/* interned; snmp_oid[ITEM_SNMP_OID_LEN_MAX];				*/
	const char	*snmpv3_securityname;	/* interned; snmpv3_securityname[ITEM_SNMPV3_SECURITYNAME_LEN_MAX];	*/
	const char	*snmpv3_authpassphrase;	/* interned; snmpv3_authpassphrase[ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX];	*/
	const char	*snmpv3_privpassphrase;	/* interned; snmpv3_privpassphrase[ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX];	*/
	unsigned short	snmp_port;
	unsigned char	snmpv3_securitylevel;
};

ZBX_DC_IPMIITEM
{
	zbx_uint64_t	itemid;
	const char	*ipmi_sensor;		/* interned; ipmi_sensor[ITEM_IPMI_SENSOR_LEN_MAX];			*/
};

ZBX_DC_FLEXITEM
{
	zbx_uint64_t	itemid;
	const char	*delay_flex;		/* interned; delay_flex[ITEM_DELAY_FLEX_LEN_MAX];			*/
};

ZBX_DC_TRAPITEM
{
	zbx_uint64_t	itemid;
	const char	*trapper_hosts;		/* interned; trapper_hosts[ITEM_TRAPPER_HOSTS_LEN_MAX];			*/
};

ZBX_DC_LOGITEM
{
	zbx_uint64_t	itemid;
	const char	*logtimefmt;		/* interned; logtimefmt[ITEM_LOGTIMEFMT_LEN_MAX];			*/
};

ZBX_DC_DBITEM
{
	zbx_uint64_t	itemid;
	const char	*params;		/* interned; params[ITEM_PARAMS_LEN_MAX];				*/
};

ZBX_DC_SSHITEM
{
	zbx_uint64_t	itemid;
	const char	*username;		/* interned; username[ITEM_USERNAME_LEN_MAX];				*/
	const char	*publickey;		/* interned; publickey[ITEM_PUBLICKEY_LEN_MAX];				*/
	const char	*privatekey;		/* interned; privatekey[ITEM_PRIVATEKEY_LEN_MAX];			*/
	const char	*password;		/* interned; password[ITEM_PASSWORD_LEN_MAX];				*/
	const char	*params;		/* interned; params[ITEM_PARAMS_LEN_MAX];				*/
	unsigned char	authtype;
};

ZBX_DC_TELNETITEM
{
	zbx_uint64_t	itemid;
	const char	*username;		/* interned; username[ITEM_USERNAME_LEN_MAX];				*/
	const char	*password;		/* interned; password[ITEM_PASSWORD_LEN_MAX];				*/
	const char	*params;		/* interned; params[ITEM_PARAMS_LEN_MAX];				*/
};

ZBX_DC_CALCITEM
{
	zbx_uint64_t	itemid;
	const char	*params;		/* interned; params[ITEM_PARAMS_LEN_MAX];				*/
};

ZBX_DC_HOST
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxy_hostid;
	const char	*host;			/* interned; host[HOST_HOST_LEN_MAX];					*/
	const char	*ip;			/* interned; ip[HOST_IP_LEN_MAX];					*/
	const char	*dns;			/* interned; dns[HOST_DNS_LEN_MAX];					*/
	int		maintenance_from;
	int		errors_from;
	int		disable_until;		/* proxy_nextcheck for passive proxies (minimum of nextchecks below) */
	int		snmp_errors_from;
	int		snmp_disable_until;	/* proxy_config_nextcheck for passive proxies */
	int		ipmi_errors_from;
	int		ipmi_disable_until;	/* proxy_data_nextcheck for passive proxies */
	unsigned short	port;
	unsigned char	useip;
	unsigned char	maintenance_status;
	unsigned char	maintenance_type;
	unsigned char	available;
	unsigned char	snmp_available;
	unsigned char	ipmi_available;
	unsigned char	status;
	unsigned char	location;
};

ZBX_DC_HOST_PH
{
	zbx_uint64_t	proxy_hostid;
	const char	*host;			/* interned; host[HOST_HOST_LEN_MAX];					*/
	ZBX_DC_HOST	*host_ptr;
	unsigned char	status;
};

ZBX_DC_IPMIHOST
{
	zbx_uint64_t	hostid;
	const char	*ipmi_ip;		/* interned; ipmi_ip[HOST_ADDR_LEN_MAX];				*/
	const char	*ipmi_username;		/* interned; ipmi_username[HOST_IPMI_USERNAME_LEN_MAX];			*/
	const char	*ipmi_password;		/* interned; ipmi_password[HOST_IPMI_PASSWORD_LEN_MAX];			*/
	unsigned short	ipmi_port;
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
};

ZBX_DC_CONFIG
{
	zbx_hashset_t		items;
	zbx_hashset_t		items_hk;	/* hostid, key */
	zbx_hashset_t		snmpitems;
	zbx_hashset_t		ipmiitems;
	zbx_hashset_t		flexitems;
	zbx_hashset_t		trapitems;
	zbx_hashset_t		logitems;
	zbx_hashset_t		dbitems;
	zbx_hashset_t		sshitems;
	zbx_hashset_t		telnetitems;
	zbx_hashset_t		calcitems;
	zbx_hashset_t		hosts;
	zbx_hashset_t		hosts_ph;	/* proxy_hostid, host */
	zbx_hashset_t		ipmihosts;
	zbx_binary_heap_t	queues[ZBX_POLLER_TYPE_COUNT];
	zbx_binary_heap_t	pqueue;
};

static ZBX_DC_CONFIG	*config = NULL;
static ZBX_MUTEX	config_lock;
static zbx_mem_info_t	*config_mem;

static const char	*INTERNED_SERVER_STATUS_KEY;
static const char	*INTERNED_SERVER_ZABBIXLOG_KEY;

/*
 * Returns type of poller for item
 * (for normal or IPMI pollers)
 */
static void	poller_by_item(zbx_uint64_t itemid, zbx_uint64_t proxy_hostid,
				unsigned char item_type, const char *key,
				unsigned char *poller_type)
{
	if (0 != proxy_hostid && (ITEM_TYPE_INTERNAL != item_type &&
				ITEM_TYPE_AGGREGATE != item_type &&
				ITEM_TYPE_CALCULATED != item_type))
	{
		*poller_type = ZBX_NO_POLLER;
		return;
	}

	if (INTERNED_SERVER_STATUS_KEY == key || INTERNED_SERVER_ZABBIXLOG_KEY == key)
	{
		*poller_type = ZBX_NO_POLLER;
		return;
	}

	switch (item_type)
	{
		case ITEM_TYPE_SIMPLE:
			if (SUCCEED == cmp_key_id(key, SERVER_ICMPPING_KEY) ||
					SUCCEED == cmp_key_id(key, SERVER_ICMPPINGSEC_KEY) ||
					SUCCEED == cmp_key_id(key, SERVER_ICMPPINGLOSS_KEY))
			{
				if (0 == CONFIG_PINGER_FORKS)
					break;
				*poller_type = ZBX_POLLER_TYPE_PINGER;
				return;
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
			*poller_type = ZBX_POLLER_TYPE_NORMAL;
			return;
		case ITEM_TYPE_IPMI:
			if (0 == CONFIG_IPMIPOLLER_FORKS)
				break;
			*poller_type = ZBX_POLLER_TYPE_IPMI;
			return;
	}

	*poller_type = ZBX_NO_POLLER;
}

static int	DCget_reachable_nextcheck(const ZBX_DC_ITEM *item, int now)
{
	int	nextcheck;

	if (ITEM_STATUS_NOTSUPPORTED == item->status)
	{
		nextcheck = calculate_item_nextcheck(item->itemid, item->type,
				CONFIG_REFRESH_UNSUPPORTED, NULL, now, NULL);
	}
	else
	{
		const ZBX_DC_FLEXITEM	*flexitem;

		flexitem = zbx_hashset_search(&config->flexitems, &item->itemid);
		nextcheck = calculate_item_nextcheck(item->itemid, item->type,
				item->delay, flexitem ? flexitem->delay_flex : NULL, now, NULL);
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
		default:
			/* nothing to do */;
	}
}

static void	*DCfind_id(zbx_hashset_t *hashset, zbx_uint64_t id, size_t size, int *found)
{
	void		*ptr;
	zbx_uint64_t	buffer[1024];	/* should be at least the size of the largest ZBX_DC_*ITEM or ZBX_DC_*HOST */

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

static ZBX_DC_HOST	*DCfind_host(zbx_uint64_t proxy_hostid, const char *hostname)
{
	ZBX_DC_HOST_PH	*host_ph, host_ph_local;

	host_ph_local.proxy_hostid = proxy_hostid;
	host_ph_local.status = HOST_STATUS_MONITORED;
	host_ph_local.host = hostname;

	if (NULL == (host_ph = zbx_hashset_search(&config->hosts_ph, &host_ph_local)))
		return NULL;
	else
		return host_ph->host_ptr;
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

	if (ZBX_LOC_QUEUE == item->location)
		zbx_binary_heap_update_direct(&config->queues[item->poller_type], &elem);
	else
	{
		item->location = ZBX_LOC_QUEUE;
		zbx_binary_heap_insert(&config->queues[item->poller_type], &elem);
	}
}

static void	DCupdate_proxy_queue(ZBX_DC_HOST *host)
{
	zbx_binary_heap_elem_t	elem;

	if (ZBX_LOC_POLLER == host->location)
		return;

	if (HOST_STATUS_PROXY_PASSIVE != host->status)
	{
		if (ZBX_LOC_QUEUE == host->location)
		{
			host->location = ZBX_LOC_NOWHERE;
			zbx_binary_heap_remove_direct(&config->pqueue, host->hostid);
		}

		return;
	}

	elem.key = host->hostid;
	elem.data = (const void *)host;

	if (ZBX_LOC_QUEUE == host->location)
		zbx_binary_heap_update_direct(&config->pqueue, &elem);
	else
	{
		host->location = ZBX_LOC_QUEUE;
		zbx_binary_heap_insert(&config->pqueue, &elem);
	}
}

static void	DCsync_items(DB_RESULT result)
{
	const char		*__function_name = "DCsync_items";

	DB_ROW			row;

	ZBX_DC_ITEM		*item;
	ZBX_DC_SNMPITEM		*snmpitem;
	ZBX_DC_IPMIITEM		*ipmiitem;
	ZBX_DC_FLEXITEM		*flexitem;
	ZBX_DC_TRAPITEM		*trapitem;
	ZBX_DC_LOGITEM		*logitem;
	ZBX_DC_DBITEM		*dbitem;
	ZBX_DC_SSHITEM		*sshitem;
	ZBX_DC_TELNETITEM	*telnetitem;
	ZBX_DC_CALCITEM		*calcitem;

	ZBX_DC_ITEM_HK		*item_hk, item_hk_local;

	time_t			now;
	unsigned char		status, old_poller_type;
	int			delay, found;
	int			update_index, old_nextcheck;
	zbx_uint64_t		itemid, hostid, proxy_hostid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->items.num_data + 32);

	now = time(NULL);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);
		ZBX_STR2UINT64(proxy_hostid, row[2]);
		delay = atoi(row[15]);
		status = (unsigned char)atoi(row[20]);

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
		item->type = (unsigned char)atoi(row[3]);
		item->data_type = (unsigned char)atoi(row[4]);
		item->value_type = (unsigned char)atoi(row[5]);
		DCstrpool_replace(found, &item->key, row[6]);

		/* update items_hk index using new data, if not done already */

		if (1 == update_index)
		{
			item_hk_local.hostid = item->hostid;
			item_hk_local.key = zbx_strpool_acquire(item->key);
			item_hk_local.item_ptr = item;
			zbx_hashset_insert(&config->items_hk, &item_hk_local, sizeof(ZBX_DC_ITEM_HK));
		}

		if (0 == found)
		{
			item->location = ZBX_LOC_NOWHERE;
			item->poller_type = ZBX_NO_POLLER;
			old_nextcheck = 0;

			if (ITEM_STATUS_NOTSUPPORTED == status)
			{
				item->nextcheck = calculate_item_nextcheck(itemid, item->type,
						CONFIG_REFRESH_UNSUPPORTED, NULL, now, NULL);
			}
			else
			{
				item->nextcheck = calculate_item_nextcheck(itemid, item->type,
						delay, row[16], now, NULL);
			}
		}
		else
		{
			old_nextcheck = item->nextcheck;

			if (ITEM_STATUS_ACTIVE == status && (status != item->status || delay != item->delay))
			{
				item->nextcheck = calculate_item_nextcheck(itemid, item->type,
						delay, row[16], now, NULL);
			}
			else if (ITEM_STATUS_NOTSUPPORTED == status && status != item->status)
			{
				item->nextcheck = calculate_item_nextcheck(itemid, item->type,
						CONFIG_REFRESH_UNSUPPORTED, NULL, now, NULL);
			}
		}

		item->status = status;
		item->delay = delay;

		old_poller_type = item->poller_type;
		poller_by_item(itemid, proxy_hostid, item->type, item->key, &item->poller_type);

		if (ZBX_POLLER_TYPE_UNREACHABLE == old_poller_type &&
				(ZBX_POLLER_TYPE_NORMAL == item->poller_type || ZBX_POLLER_TYPE_IPMI == item->poller_type))
		{
			item->poller_type = ZBX_POLLER_TYPE_UNREACHABLE;
		}

		/* SNMP items */

		if (ITEM_TYPE_SNMPv1 == item->type || ITEM_TYPE_SNMPv2c == item->type || ITEM_TYPE_SNMPv3 == item->type)
		{
			snmpitem = DCfind_id(&config->snmpitems, itemid, sizeof(ZBX_DC_SNMPITEM), &found);

			DCstrpool_replace(found, &snmpitem->snmp_community, row[7]);
			DCstrpool_replace(found, &snmpitem->snmp_oid, row[8]);
			snmpitem->snmp_port = (unsigned short)atoi(row[9]);
			DCstrpool_replace(found, &snmpitem->snmpv3_securityname, row[10]);
			snmpitem->snmpv3_securitylevel = (unsigned char)atoi(row[11]);
			DCstrpool_replace(found, &snmpitem->snmpv3_authpassphrase, row[12]);
			DCstrpool_replace(found, &snmpitem->snmpv3_privpassphrase, row[13]);
		}
		else if (NULL != (snmpitem = zbx_hashset_search(&config->snmpitems, &itemid)))
		{
			/* remove SNMP parameters for non-SNMP item */

			zbx_strpool_release(snmpitem->snmp_community);
			zbx_strpool_release(snmpitem->snmp_oid);
			zbx_strpool_release(snmpitem->snmpv3_securityname);
			zbx_strpool_release(snmpitem->snmpv3_authpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_privpassphrase);

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

		/* items with flexible intervals */

		if ('\0' != *row[16])
		{
			flexitem = DCfind_id(&config->flexitems, itemid, sizeof(ZBX_DC_FLEXITEM), &found);

			if (SUCCEED == DCstrpool_replace(found, &flexitem->delay_flex, row[16]) &&
					ITEM_STATUS_NOTSUPPORTED != item->status)
			{
				item->nextcheck = calculate_item_nextcheck(item->itemid, item->type,
						item->delay, flexitem->delay_flex, now, NULL);
			}
		}
		else if (NULL != (flexitem = zbx_hashset_search(&config->flexitems, &itemid)))
		{
			/* remove delay_flex parameter for non-flexible item and update nextcheck */

			zbx_strpool_release(flexitem->delay_flex);
			zbx_hashset_remove(&config->flexitems, &itemid);

			if (ITEM_STATUS_NOTSUPPORTED != item->status)
			{
				item->nextcheck = calculate_item_nextcheck(item->itemid, item->type,
						item->delay, NULL, now, NULL);
			}
		}

		/* trapper items */

		if (ITEM_TYPE_TRAPPER == item->type && '\0' != *row[17])
		{
			trapitem = DCfind_id(&config->trapitems, itemid, sizeof(ZBX_DC_TRAPITEM), &found);

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
		}
		else if (NULL != (dbitem = zbx_hashset_search(&config->dbitems, &itemid)))
		{
			/* remove db item parameters */
			zbx_strpool_release(dbitem->params);
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

	/* remove deleted or disabled items from buffer */

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (item = zbx_hashset_iter_next(&iter)))
	{
		itemid = item->itemid;

		if (FAIL != zbx_vector_uint64_bsearch(&ids, itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		/* SNMP items */

		if (ITEM_TYPE_SNMPv1 == item->type ||
				ITEM_TYPE_SNMPv2c == item->type ||
				ITEM_TYPE_SNMPv3 == item->type)
		{
			snmpitem = zbx_hashset_search(&config->snmpitems, &itemid);

			zbx_strpool_release(snmpitem->snmp_community);
			zbx_strpool_release(snmpitem->snmp_oid);
			zbx_strpool_release(snmpitem->snmpv3_securityname);
			zbx_strpool_release(snmpitem->snmpv3_authpassphrase);
			zbx_strpool_release(snmpitem->snmpv3_privpassphrase);

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

		/* calculated items */

		if (ITEM_TYPE_CALCULATED == item->type)
		{
			calcitem = zbx_hashset_search(&config->calcitems, &itemid);
			zbx_strpool_release(calcitem->params);
			zbx_hashset_remove(&config->calcitems, &itemid);
		}

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
		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCsync_hosts(DB_RESULT result)
{
	const char		*__function_name = "DCsync_hosts";

	DB_ROW			row;

	ZBX_DC_HOST		*host;
	ZBX_DC_IPMIHOST		*ipmihost;

	ZBX_DC_HOST_PH		*host_ph, host_ph_local;

	int			found;
	int			update_index, update_queue;
	zbx_uint64_t		hostid, proxy_hostid;
	zbx_vector_uint64_t	ids;
	zbx_hashset_iter_t	iter;
	unsigned char		status;
	time_t			now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, config->hosts.num_data + 32);

	now = time(NULL);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_STR2UINT64(proxy_hostid, row[1]);
		status = (unsigned char)atoi(row[26]);

		/* array of selected hosts */
		zbx_vector_uint64_append(&ids, hostid);

		host = DCfind_id(&config->hosts, hostid, sizeof(ZBX_DC_HOST), &found);

		/* see whether we should and can update hosts_ph index at this point */

		update_index = 0;

		if (0 == found || host->proxy_hostid != proxy_hostid || host->status != status ||
				0 != strcmp(host->host, row[2]))
		{
			if (1 == found)
			{
				host_ph_local.proxy_hostid = host->proxy_hostid;
				host_ph_local.status = host->status;
				host_ph_local.host = host->host;
				host_ph = zbx_hashset_search(&config->hosts_ph, &host_ph_local);

				if (NULL != host_ph && host == host_ph->host_ptr)	/* see ZBX-4045 for NULL check */
				{
					zbx_strpool_release(host_ph->host);
					zbx_hashset_remove(&config->hosts_ph, &host_ph_local);
				}
			}

			host_ph_local.proxy_hostid = proxy_hostid;
			host_ph_local.status = status;
			host_ph_local.host = row[2];
			host_ph = zbx_hashset_search(&config->hosts_ph, &host_ph_local);

			if (NULL != host_ph)
				host_ph->host_ptr = host;
			else
				update_index = 1;
		}

		update_queue = (0 == found && HOST_STATUS_PROXY_PASSIVE == status)
				|| (1 == found && host->status != status);

		/* store new information in host structure */

		host->proxy_hostid = proxy_hostid;
		DCstrpool_replace(found, &host->host, row[2]);
		host->useip = (unsigned char)atoi(row[3]);
		DCstrpool_replace(found, &host->ip, row[4]);
		DCstrpool_replace(found, &host->dns, row[5]);
		host->port = (unsigned short)atoi(row[6]);
		host->maintenance_status = (unsigned char)atoi(row[14]);
		host->maintenance_type = (unsigned char)atoi(row[15]);
		host->maintenance_from = atoi(row[16]);
		host->status = status;

		if (0 == found)
		{
			host->errors_from = atoi(row[17]);
			host->available = (unsigned char)atoi(row[18]);
			host->snmp_errors_from = atoi(row[20]);
			host->snmp_available = (unsigned char)atoi(row[21]);
			host->ipmi_errors_from = atoi(row[23]);
			host->ipmi_available = (unsigned char)atoi(row[24]);
			host->location = ZBX_LOC_NOWHERE;

			if (HOST_STATUS_PROXY_PASSIVE == host->status)
			{
				host->snmp_disable_until = (int)calculate_proxy_nextcheck(
						host->hostid, CONFIG_PROXYCONFIG_FREQUENCY, now);
				host->ipmi_disable_until = (int)calculate_proxy_nextcheck(
						host->hostid, CONFIG_PROXYDATA_FREQUENCY, now);
				host->disable_until = (host->snmp_disable_until <= host->ipmi_disable_until)
						? host->snmp_disable_until : host->ipmi_disable_until;
			}
			else
			{
				host->disable_until = atoi(row[19]);
				host->snmp_disable_until = atoi(row[22]);
				host->ipmi_disable_until = atoi(row[25]);
			}
		}

		/* update hosts_ph index using new data, if not done already */

		if (1 == update_index)
		{
			host_ph_local.proxy_hostid = host->proxy_hostid;
			host_ph_local.status = host->status;
			host_ph_local.host = zbx_strpool_acquire(host->host);
			host_ph_local.host_ptr = host;
			zbx_hashset_insert(&config->hosts_ph, &host_ph_local, sizeof(ZBX_DC_HOST_PH));
		}

		if (1 == update_queue)
			DCupdate_proxy_queue(host);

		/* IPMI hosts */

		if (1 == atoi(row[7]))	/* useipmi */
		{
			ipmihost = DCfind_id(&config->ipmihosts, hostid, sizeof(ZBX_DC_IPMIHOST), &found);

			DCstrpool_replace(found, &ipmihost->ipmi_ip, row[8]);
			ipmihost->ipmi_port = (unsigned short)atoi(row[9]);
			ipmihost->ipmi_authtype = (signed char)atoi(row[10]);
			ipmihost->ipmi_privilege = (unsigned char)atoi(row[11]);
			DCstrpool_replace(found, &ipmihost->ipmi_username, row[12]);
			DCstrpool_replace(found, &ipmihost->ipmi_password, row[13]);
		}
		else if (NULL != (ipmihost = zbx_hashset_search(&config->ipmihosts, &hostid)))
		{
			/* remove IPMI connection parameters for hosts without IPMI */

			zbx_strpool_release(ipmihost->ipmi_ip);
			zbx_strpool_release(ipmihost->ipmi_username);
			zbx_strpool_release(ipmihost->ipmi_password);

			zbx_hashset_remove(&config->ipmihosts, &hostid);
		}
	}

	/* remove deleted or disabled hosts from buffer */

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
			zbx_strpool_release(ipmihost->ipmi_ip);
			zbx_strpool_release(ipmihost->ipmi_username);
			zbx_strpool_release(ipmihost->ipmi_password);

			zbx_hashset_remove(&config->ipmihosts, &hostid);
		}

		/* passive proxies */

		if (ZBX_LOC_QUEUE == host->location)
		{
			host->location = ZBX_LOC_NOWHERE;
			zbx_binary_heap_remove_direct(&config->pqueue, host->hostid);
		}

		/* hosts */

		host_ph_local.proxy_hostid = host->proxy_hostid;
		host_ph_local.status = host->status;
		host_ph_local.host = host->host;
		host_ph = zbx_hashset_search(&config->hosts_ph, &host_ph_local);

		if (NULL != host_ph && host == host_ph->host_ptr)	/* see ZBX-4045 for NULL check */
		{
			zbx_strpool_release(host_ph->host);
			zbx_hashset_remove(&config->hosts_ph, &host_ph_local);
		}

		zbx_strpool_release(host->host);
		zbx_strpool_release(host->ip);
		zbx_strpool_release(host->dns);

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
void	DCsync_configuration()
{
	const char		*__function_name = "DCsync_configuration";

	DB_RESULT		item_result;
	DB_RESULT		host_result;

	int			i;
	double			sec, isec, hsec, ssec;
	const zbx_strpool_t	*strpool;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sec = zbx_time();
	item_result = DBselect(
			"select i.itemid,i.hostid,h.proxy_hostid,i.type,i.data_type,i.value_type,i.key_,"
				"i.snmp_community,i.snmp_oid,i.snmp_port,i.snmpv3_securityname,"
				"i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,"
				"i.ipmi_sensor,i.delay,i.delay_flex,i.trapper_hosts,i.logtimefmt,i.params,"
				"i.status,i.authtype,i.username,i.password,i.publickey,i.privatekey"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status in (%d)"
				" and i.status in (%d,%d)"
				DB_NODE,
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED,
			DBnode_local("i.itemid"));
	isec = zbx_time() - sec;

	sec = zbx_time();
	host_result = DBselect(
			"select hostid,proxy_hostid,host,useip,ip,dns,port,"
				"useipmi,ipmi_ip,ipmi_port,ipmi_authtype,ipmi_privilege,ipmi_username,"
				"ipmi_password,maintenance_status,maintenance_type,maintenance_from,"
				"errors_from,available,disable_until,snmp_errors_from,snmp_available,"
				"snmp_disable_until,ipmi_errors_from,ipmi_available,ipmi_disable_until,"
				"status"
			" from hosts"
			" where status in (%d,%d,%d)"
				DB_NODE,
			HOST_STATUS_MONITORED, HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
			DBnode_local("hostid"));
	hsec = zbx_time() - sec;

	LOCK_CACHE;

	sec = zbx_time();
	DCsync_items(item_result);
	DCsync_hosts(host_result);
	ssec = zbx_time() - sec;

	strpool = zbx_strpool_info();

	zabbix_log(LOG_LEVEL_DEBUG, "%s() item sql   : " ZBX_FS_DBL " sec.", __function_name, isec);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() host sql   : " ZBX_FS_DBL " sec.", __function_name, hsec);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() sync lock  : " ZBX_FS_DBL " sec.", __function_name, ssec);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() total time : " ZBX_FS_DBL " sec.", __function_name,
			isec + hsec + ssec);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() items      : %d (%d slots)", __function_name,
			config->items.num_data, config->items.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() items_hk   : %d (%d slots)", __function_name,
			config->items_hk.num_data, config->items_hk.num_slots);
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
	zabbix_log(LOG_LEVEL_DEBUG, "%s() calcitems  : %d (%d slots)", __function_name,
			config->calcitems.num_data, config->calcitems.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts      : %d (%d slots)", __function_name,
			config->hosts.num_data, config->hosts.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts_ph   : %d (%d slots)", __function_name,
			config->hosts_ph.num_data, config->hosts_ph.num_slots);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() ipmihosts  : %d (%d slots)", __function_name,
			config->ipmihosts.num_data, config->ipmihosts.num_slots);

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

	UNLOCK_CACHE;

	DBfree_result(item_result);
	DBfree_result(host_result);

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

ZBX_MEM_FUNC_IMPL(__config, config_mem);

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

	if (item_hk_1->hostid < item_hk_2->hostid) return -1;
	if (item_hk_1->hostid > item_hk_2->hostid) return +1;
	return (item_hk_1->key == item_hk_2->key ? 0 : strcmp(item_hk_1->key, item_hk_2->key));
}

static zbx_hash_t	__config_host_ph_hash(const void *data)
{
	const ZBX_DC_HOST_PH	*host_ph = (const ZBX_DC_HOST_PH *)data;

	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&host_ph->proxy_hostid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO((char *)&host_ph->status, 1, hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(host_ph->host, strlen(host_ph->host), hash);

	return hash;
}

static int	__config_host_ph_compare(const void *d1, const void *d2)
{
	const ZBX_DC_HOST_PH	*host_ph_1 = (const ZBX_DC_HOST_PH *)d1;
	const ZBX_DC_HOST_PH	*host_ph_2 = (const ZBX_DC_HOST_PH *)d2;

	if (host_ph_1->proxy_hostid < host_ph_2->proxy_hostid) return -1;
	if (host_ph_1->proxy_hostid > host_ph_2->proxy_hostid) return +1;
	if (host_ph_1->status < host_ph_2->status) return -1;
	if (host_ph_1->status > host_ph_2->status) return +1;
	return (host_ph_1->host == host_ph_2->host ? 0 : strcmp(host_ph_1->host, host_ph_2->host));
}

static int	__config_nextcheck_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const ZBX_DC_ITEM		*i1 = (const ZBX_DC_ITEM *)e1->data;
	const ZBX_DC_ITEM		*i2 = (const ZBX_DC_ITEM *)e2->data;

	if (i1->nextcheck < i2->nextcheck) return -1;
	if (i1->nextcheck > i2->nextcheck) return +1;
	return 0;
}

static int	__config_proxy_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const ZBX_DC_HOST		*h1 = (const ZBX_DC_HOST *)e1->data;
	const ZBX_DC_HOST		*h2 = (const ZBX_DC_HOST *)e2->data;

	if (h1->disable_until < h2->disable_until) return -1;
	if (h1->disable_until > h2->disable_until) return +1;
	return 0;
}

void	init_configuration_cache()
{
	const char	*__function_name = "init_configuration_cache";

	int		i;
	key_t		shm_key;
	size_t		config_size;
	size_t		strpool_size;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() size:%d", __function_name, CONFIG_CONF_CACHE_SIZE);

	strpool_size = (size_t)(CONFIG_CONF_CACHE_SIZE * 0.15);
	config_size = CONFIG_CONF_CACHE_SIZE - strpool_size;

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_CONFIG_ID)))
	{
		zbx_error("Can't create IPC key for configuration cache");
		exit(FAIL);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&config_lock, ZBX_MUTEX_CONFIG))
	{
		zbx_error("Unable to create mutex for configuration cache");
		exit(FAIL);
	}

	zbx_mem_create(&config_mem, shm_key, ZBX_NO_MUTEX, config_size, "configuration cache", "CacheSize");

	config = __config_mem_malloc_func(NULL, sizeof(ZBX_DC_CONFIG));

#define	INIT_HASHSET_SIZE	1000 /* should be calculated dynamically based on config_size? */

#define CREATE_HASHSET(hashset)	zbx_hashset_create_ext(&hashset, INIT_HASHSET_SIZE,		\
								ZBX_DEFAULT_UINT64_HASH_FUNC,	\
								ZBX_DEFAULT_UINT64_COMPARE_FUNC,\
								__config_mem_malloc_func,	\
								__config_mem_realloc_func,	\
								__config_mem_free_func);

	CREATE_HASHSET(config->items);
	CREATE_HASHSET(config->snmpitems);
	CREATE_HASHSET(config->ipmiitems);
	CREATE_HASHSET(config->flexitems);
	CREATE_HASHSET(config->trapitems);
	CREATE_HASHSET(config->logitems);
	CREATE_HASHSET(config->dbitems);
	CREATE_HASHSET(config->sshitems);
	CREATE_HASHSET(config->telnetitems);
	CREATE_HASHSET(config->calcitems);

	CREATE_HASHSET(config->hosts);
	CREATE_HASHSET(config->ipmihosts);

	zbx_hashset_create_ext(&config->items_hk, INIT_HASHSET_SIZE,
					__config_item_hk_hash,
					__config_item_hk_compare,
					__config_mem_malloc_func,
					__config_mem_realloc_func,
					__config_mem_free_func);

	zbx_hashset_create_ext(&config->hosts_ph, INIT_HASHSET_SIZE,
					__config_host_ph_hash,
					__config_host_ph_compare,
					__config_mem_malloc_func,
					__config_mem_realloc_func,
					__config_mem_free_func);

	for (i = 0; i < ZBX_POLLER_TYPE_COUNT; i++)
	{
		zbx_binary_heap_create_ext(&config->queues[i],
						__config_nextcheck_compare,
						ZBX_BINARY_HEAP_OPTION_DIRECT,
						__config_mem_malloc_func,
						__config_mem_realloc_func,
						__config_mem_free_func);
	}

	zbx_binary_heap_create_ext(&config->pqueue,
					__config_proxy_compare,
					ZBX_BINARY_HEAP_OPTION_DIRECT,
					__config_mem_malloc_func,
					__config_mem_realloc_func,
					__config_mem_free_func);

#undef	INIT_HASHSET_SIZE

#undef	CREATE_HASHSET

	zbx_strpool_create(strpool_size);

	INTERNED_SERVER_STATUS_KEY = zbx_strpool_intern(SERVER_STATUS_KEY);
	INTERNED_SERVER_ZABBIXLOG_KEY = zbx_strpool_intern(SERVER_ZABBIXLOG_KEY);

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

static void	DCget_host(DC_HOST *dst_host, const ZBX_DC_HOST *src_host)
{
	const ZBX_DC_IPMIHOST	*ipmihost;

	dst_host->hostid = src_host->hostid;
	dst_host->proxy_hostid = src_host->proxy_hostid;
	strscpy(dst_host->host, src_host->host);
	dst_host->useip = src_host->useip;
	strscpy(dst_host->ip, src_host->ip);
	strscpy(dst_host->dns, src_host->dns);
	dst_host->port = src_host->port;
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

	if (NULL != (ipmihost = zbx_hashset_search(&config->ipmihosts, &src_host->hostid)))
	{
		strscpy(dst_host->ipmi_ip_orig, ipmihost->ipmi_ip);
		dst_host->ipmi_port = ipmihost->ipmi_port;
		dst_host->ipmi_authtype = ipmihost->ipmi_authtype;
		dst_host->ipmi_privilege = ipmihost->ipmi_privilege;
		strscpy(dst_host->ipmi_username, ipmihost->ipmi_username);
		strscpy(dst_host->ipmi_password, ipmihost->ipmi_password);
	}
	else
	{
		*dst_host->ipmi_ip_orig = '\0';
		dst_host->ipmi_port = 623;
		dst_host->ipmi_authtype = 0;
		dst_host->ipmi_privilege = 2;
		*dst_host->ipmi_username = '\0';
		*dst_host->ipmi_password = '\0';
	}
	dst_host->ipmi_ip = NULL;
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

static void	DCget_item(DC_ITEM *dst_item, const ZBX_DC_ITEM *src_item)
{
	const ZBX_DC_LOGITEM		*logitem;
	const ZBX_DC_SNMPITEM		*snmpitem;
	const ZBX_DC_TRAPITEM		*trapitem;
	const ZBX_DC_IPMIITEM		*ipmiitem;
	const ZBX_DC_DBITEM		*dbitem;
	const ZBX_DC_FLEXITEM		*flexitem;
	const ZBX_DC_SSHITEM		*sshitem;
	const ZBX_DC_TELNETITEM		*telnetitem;
	const ZBX_DC_CALCITEM		*calcitem;

	dst_item->itemid = src_item->itemid;
	dst_item->type = src_item->type;
	dst_item->data_type = src_item->data_type;
	dst_item->value_type = src_item->value_type;
	strscpy(dst_item->key_orig, src_item->key);
	dst_item->key = NULL;
	dst_item->delay = src_item->delay;
	dst_item->nextcheck = src_item->nextcheck;
	dst_item->status = src_item->status;
	*dst_item->trapper_hosts = '\0';
	*dst_item->logtimefmt = '\0';
	*dst_item->delay_flex = '\0';

	if (NULL != (flexitem = zbx_hashset_search(&config->flexitems, &src_item->itemid)))
		strscpy(dst_item->delay_flex, flexitem->delay_flex);

	if (ITEM_VALUE_TYPE_LOG == dst_item->value_type &&
			NULL != (logitem = zbx_hashset_search(&config->logitems, &src_item->itemid)))
	{
		strscpy(dst_item->logtimefmt, logitem->logtimefmt);
	}

	switch (src_item->type) {
	case ITEM_TYPE_SNMPv1:
	case ITEM_TYPE_SNMPv2c:
	case ITEM_TYPE_SNMPv3:
		if (NULL != (snmpitem = zbx_hashset_search(&config->snmpitems, &src_item->itemid)))
		{
			strscpy(dst_item->snmp_community_orig, snmpitem->snmp_community);
			strscpy(dst_item->snmp_oid_orig, snmpitem->snmp_oid);
			dst_item->snmp_port = snmpitem->snmp_port;
			strscpy(dst_item->snmpv3_securityname_orig, snmpitem->snmpv3_securityname);
			dst_item->snmpv3_securitylevel = snmpitem->snmpv3_securitylevel;
			strscpy(dst_item->snmpv3_authpassphrase_orig, snmpitem->snmpv3_authpassphrase);
			strscpy(dst_item->snmpv3_privpassphrase_orig, snmpitem->snmpv3_privpassphrase);
		}
		break;
	case ITEM_TYPE_TRAPPER:
		if (NULL != (trapitem = zbx_hashset_search(&config->trapitems, &src_item->itemid)))
			strscpy(dst_item->trapper_hosts, trapitem->trapper_hosts);
		break;
	case ITEM_TYPE_IPMI:
		if (NULL != (ipmiitem = zbx_hashset_search(&config->ipmiitems, &src_item->itemid)))
			strscpy(dst_item->ipmi_sensor, ipmiitem->ipmi_sensor);
		break;
	case ITEM_TYPE_DB_MONITOR:
		if (NULL != (dbitem = zbx_hashset_search(&config->dbitems, &src_item->itemid)))
		{
			strscpy(dst_item->params_orig, dbitem->params);
			dst_item->params = NULL;
		}
		break;
	case ITEM_TYPE_SSH:
		if (NULL != (sshitem = zbx_hashset_search(&config->sshitems, &src_item->itemid)))
		{
			dst_item->authtype = sshitem->authtype;
			strscpy(dst_item->username_orig, sshitem->username);
			strscpy(dst_item->publickey_orig, sshitem->publickey);
			strscpy(dst_item->privatekey_orig, sshitem->privatekey);
			strscpy(dst_item->password_orig, sshitem->password);
			strscpy(dst_item->params_orig, sshitem->params);
			dst_item->username = NULL;
			dst_item->publickey = NULL;
			dst_item->privatekey = NULL;
			dst_item->password = NULL;
			dst_item->params = NULL;
		}
		break;
	case ITEM_TYPE_TELNET:
		if (NULL != (telnetitem = zbx_hashset_search(&config->telnetitems, &src_item->itemid)))
		{
			strscpy(dst_item->username_orig, telnetitem->username);
			strscpy(dst_item->password_orig, telnetitem->password);
			strscpy(dst_item->params_orig, telnetitem->params);
			dst_item->username = NULL;
			dst_item->password = NULL;
			dst_item->params = NULL;
		}
		break;
	case ITEM_TYPE_CALCULATED:
		if (NULL != (calcitem = zbx_hashset_search(&config->calcitems, &src_item->itemid)))
		{
			strscpy(dst_item->params_orig, calcitem->params);
			dst_item->params = NULL;
		}
		break;
	default:
		/* nothing to do */;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_item_by_key                                         *
 *                                                                            *
 * Purpose: Locate item in configuration cache                                *
 *                                                                            *
 * Parameters: item - [OUT] pointer to DC_ITEM structure                      *
 *             proxy_hostid - [IN] proxy host ID                              *
 *             hostname - [IN] hostname                                       *
 *             key - [IN] item key                                            *
 *                                                                            *
 * Return value: SUCCEED if record located and FAIL otherwise                 *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_item_by_key(DC_ITEM *item, zbx_uint64_t proxy_hostid, const char *hostname, const char *key)
{
	int			res = FAIL;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	if (NULL == (dc_host = DCfind_host(proxy_hostid, hostname)))
		goto unlock;

	if (NULL == (dc_item = DCfind_item(dc_host->hostid, key)))
		goto unlock;

	DCget_host(&item->host, dc_host);
	DCget_item(item, dc_item);

	res = SUCCEED;
unlock:
	UNLOCK_CACHE;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_item_by_itemid                                      *
 *                                                                            *
 * Purpose: Get item with specified ID                                        *
 *                                                                            *
 * Parameters: item - [OUT] pointer to DC_ITEM structure                      *
 *             itemid - [IN] item ID                                          *
 *                                                                            *
 * Return value: SUCCEED if item found, otherwise FAIL                        *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_item_by_itemid(DC_ITEM *item, zbx_uint64_t itemid)
{
	int			res = FAIL;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	if (NULL == (dc_item = zbx_hashset_search(&config->items, &itemid)))
		goto unlock;

	if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		goto unlock;

	DCget_host(&item->host, dc_host);
	DCget_item(item, dc_item);

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
 * Comments: Items leave the queue only through this function. Pollers        *
 *           must always return the items they have taken using either        *
 *           DCrequeue_reachable_item() or DCrequeue_unreachable_item().      *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_poller_items(unsigned char poller_type, DC_ITEM *items, int max_items)
{
	const char		*__function_name = "DCconfig_get_poller_items";

	int			now, num = 0;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() poller_type:%d", __function_name, (int)poller_type);

	now = time(NULL);

	queue = &config->queues[poller_type];

	LOCK_CACHE;

	while (num < max_items && FAIL == zbx_binary_heap_empty(queue))
	{
		int				disable_until, old_nextcheck;
		unsigned char			old_poller_type;
		const zbx_binary_heap_elem_t	*min;
		ZBX_DC_ITEM			*dc_item;
		ZBX_DC_HOST			*dc_host;

		min = zbx_binary_heap_find_min(queue);
		dc_item = (ZBX_DC_ITEM *)min->data;

		if (dc_item->nextcheck > now)
			break;

		zbx_binary_heap_remove_min(queue);
		dc_item->location = ZBX_LOC_NOWHERE;

		if (CONFIG_REFRESH_UNSUPPORTED == 0 && ITEM_STATUS_NOTSUPPORTED == dc_item->status)
			continue;

		if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_MAINTENANCE_STATUS_ON == dc_host->maintenance_status &&
				MAINTENANCE_TYPE_NODATA == dc_host->maintenance_type)
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
				poller_by_item(dc_item->itemid, dc_host->proxy_hostid, dc_item->type, dc_item->key,
						&dc_item->poller_type);

				old_nextcheck = dc_item->nextcheck;
				dc_item->nextcheck = DCget_reachable_nextcheck(dc_item, now);

				DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
				continue;
			}
		}
		else
		{
			if (ZBX_POLLER_TYPE_NORMAL == poller_type || ZBX_POLLER_TYPE_IPMI == poller_type)
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

		dc_item->location = ZBX_LOC_POLLER;
		DCget_host(&items[num].host, dc_host);
		DCget_item(&items[num], dc_item);
		num++;
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, num);

	return num;
}

/******************************************************************************
 *                                                                            *
 * Function: DCconfig_get_items                                               *
 *                                                                            *
 * Purpose: Get array of items with specified key                             *
 *                                                                            *
 * Parameters: hostid - [IN] host ID (0 - keys from all hosts)                *
 *             key - [IN] key name                                            *
 *             items - [OUT] pointer to array of DC_ITEM structures           *
 *                                                                            *
 * Return value: number of items                                              *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 * Comments: only used for SERVER_ZABBIXLOG_KEY and SERVER_STATUS_KEY items   *
 *                                                                            *
 ******************************************************************************/
int	DCconfig_get_items(zbx_uint64_t hostid, const char *key, DC_ITEM **items)
{
	const char	*__function_name = "DCconfig_get_items";

	int			items_num = 0, items_alloc = 8, counter = 0;
	ZBX_DC_ITEM		*dc_item;
	ZBX_DC_HOST		*dc_host;
	zbx_hashset_iter_t	dc_iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " key:'%s'",
			__function_name, hostid, key);

	*items = zbx_malloc(*items, items_alloc * sizeof(DC_ITEM));

	LOCK_CACHE;

	if (0 == hostid)
		zbx_hashset_iter_reset(&config->hosts, &dc_iter);

	while (1)
	{
		if (0 == hostid)
		{
			if (NULL == (dc_host = zbx_hashset_iter_next(&dc_iter)))
				break;
		}
		else
		{
			if (1 != ++counter || (NULL == (dc_host = zbx_hashset_search(&config->hosts, &hostid))))
				break;
		}

		if (0 != dc_host->proxy_hostid)
			continue;

		if (HOST_MAINTENANCE_STATUS_OFF != dc_host->maintenance_status ||
				MAINTENANCE_TYPE_NORMAL != dc_host->maintenance_type)
			continue;

		if (NULL == (dc_item = DCfind_item(dc_host->hostid, key)))
			continue;

		if (CONFIG_REFRESH_UNSUPPORTED == 0 &&
				ITEM_STATUS_NOTSUPPORTED == dc_item->status)
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

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, items_num);

	return items_num;
}

void	DCrequeue_reachable_item(zbx_uint64_t itemid, unsigned char status, int now)
{
	ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST	*dc_host;
	unsigned char	old_poller_type;
	int		old_nextcheck;

	LOCK_CACHE;

	if (NULL != (dc_item = zbx_hashset_search(&config->items, &itemid)))
	{
		dc_item->status = status;

		old_poller_type = dc_item->poller_type;
		if (ZBX_POLLER_TYPE_UNREACHABLE == dc_item->poller_type &&
				NULL != (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		{
			poller_by_item(dc_item->itemid, dc_host->proxy_hostid, dc_item->type, dc_item->key,
					&dc_item->poller_type);
		}

		old_nextcheck = dc_item->nextcheck;
		dc_item->nextcheck = DCget_reachable_nextcheck(dc_item, now);

		if (ZBX_LOC_POLLER == dc_item->location)
			dc_item->location = ZBX_LOC_NOWHERE;

		DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
	}

	UNLOCK_CACHE;
}

void	DCrequeue_unreachable_item(zbx_uint64_t itemid)
{
	ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST	*dc_host;
	unsigned char	old_poller_type;
	int		old_nextcheck;

	LOCK_CACHE;

	if (NULL != (dc_item = zbx_hashset_search(&config->items, &itemid)) &&
			NULL != (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
	{
		old_poller_type = dc_item->poller_type;
		if (ZBX_POLLER_TYPE_NORMAL == dc_item->poller_type || ZBX_POLLER_TYPE_IPMI == dc_item->poller_type)
			dc_item->poller_type = ZBX_POLLER_TYPE_UNREACHABLE;

		old_nextcheck = dc_item->nextcheck;
		dc_item->nextcheck = DCget_unreachable_nextcheck(dc_item, dc_host);

		if (ZBX_LOC_POLLER == dc_item->location)
			dc_item->location = ZBX_LOC_NOWHERE;

		DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
	}

	UNLOCK_CACHE;
}

int	DCconfig_activate_host(DC_ITEM *item)
{
	int		res = FAIL;
	ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &item->host.hostid)))
		goto unlock;

	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			item->host.errors_from = dc_host->errors_from;
			item->host.available = dc_host->available;
			item->host.disable_until = dc_host->disable_until;
			dc_host->errors_from = 0;
			dc_host->available = HOST_AVAILABLE_TRUE;
			dc_host->disable_until = 0;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			item->host.snmp_errors_from = dc_host->snmp_errors_from;
			item->host.snmp_available = dc_host->snmp_available;
			item->host.snmp_disable_until = dc_host->snmp_disable_until;
			dc_host->snmp_errors_from = 0;
			dc_host->snmp_available = HOST_AVAILABLE_TRUE;
			dc_host->snmp_disable_until = 0;
			break;
		case ITEM_TYPE_IPMI:
			item->host.ipmi_errors_from = dc_host->ipmi_errors_from;
			item->host.ipmi_available = dc_host->ipmi_available;
			item->host.ipmi_disable_until = dc_host->ipmi_disable_until;
			dc_host->ipmi_errors_from = 0;
			dc_host->ipmi_available = HOST_AVAILABLE_TRUE;
			dc_host->ipmi_disable_until = 0;
			break;
		default:
			goto unlock;
	}

	res = SUCCEED;
unlock:
	UNLOCK_CACHE;

	return res;
}

int	DCconfig_deactivate_host(DC_ITEM *item, int now)
{
	int		res = FAIL;
	ZBX_DC_HOST	*dc_host;
	int		*errors_from;
	int		*disable_until;
	unsigned char	*available;

	LOCK_CACHE;

	if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &item->host.hostid)))
		goto unlock;

	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			item->host.errors_from = dc_host->errors_from;
			item->host.available = dc_host->available;
			item->host.disable_until = dc_host->disable_until;
			errors_from = &dc_host->errors_from;
			available = &dc_host->available;
			disable_until = &dc_host->disable_until;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			item->host.snmp_errors_from = dc_host->snmp_errors_from;
			item->host.snmp_available = dc_host->snmp_available;
			item->host.snmp_disable_until = dc_host->snmp_disable_until;
			errors_from = &dc_host->snmp_errors_from;
			available = &dc_host->snmp_available;
			disable_until = &dc_host->snmp_disable_until;
			break;
		case ITEM_TYPE_IPMI:
			item->host.ipmi_errors_from = dc_host->ipmi_errors_from;
			item->host.ipmi_available = dc_host->ipmi_available;
			item->host.ipmi_disable_until = dc_host->ipmi_disable_until;
			errors_from = &dc_host->ipmi_errors_from;
			available = &dc_host->ipmi_available;
			disable_until = &dc_host->ipmi_disable_until;
			break;
		default:
			goto unlock;
	}

	/* First error */
	if (*errors_from == 0)
	{
		*errors_from = now;
		*disable_until = now + CONFIG_UNREACHABLE_DELAY;
	}
	else
	{
		if (now - *errors_from <= CONFIG_UNREACHABLE_PERIOD)
		{
			/* Still unavailable, but won't change status to UNAVAILABLE yet */
			*disable_until = now + CONFIG_UNREACHABLE_DELAY;
		}
		else
		{
			*disable_until = now + CONFIG_UNAVAILABLE_DELAY;
			*available = HOST_AVAILABLE_FALSE;
		}
	}

	res = SUCCEED;
unlock:
	UNLOCK_CACHE;

	return res;
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
void	DCconfig_set_maintenance(zbx_uint64_t hostid, int maintenance_status,
				int maintenance_type, int maintenance_from)
{
	ZBX_DC_HOST	*dc_host;

	LOCK_CACHE;

	if (NULL != (dc_host = zbx_hashset_search(&config->hosts, &hostid)))
	{
		if (HOST_MAINTENANCE_STATUS_OFF == dc_host->maintenance_status ||
				HOST_MAINTENANCE_STATUS_OFF == maintenance_status)
			dc_host->maintenance_from = maintenance_from;

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
int	DCconfig_get_proxypoller_hosts(DC_HOST *hosts, int max_hosts)
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
		ZBX_DC_HOST			*dc_host;

		min = zbx_binary_heap_find_min(queue);
		dc_host = (ZBX_DC_HOST *)min->data;

		if (dc_host->disable_until > now)
			break;

		zbx_binary_heap_remove_min(queue);
		dc_host->location = ZBX_LOC_POLLER;

		DCget_host(&hosts[num], dc_host);

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
	const char			*__function_name = "DCconfig_get_proxypoller_nextcheck";

	int				nextcheck;
	zbx_binary_heap_t		*queue;
	const zbx_binary_heap_elem_t	*min;
	const ZBX_DC_HOST		*dc_host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	queue = &config->pqueue;

	LOCK_CACHE;

	if (FAIL == zbx_binary_heap_empty(queue))
	{
		min = zbx_binary_heap_find_min(queue);
		dc_host = (const ZBX_DC_HOST *)min->data;

		nextcheck = dc_host->disable_until;
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
	ZBX_DC_HOST	*dc_host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() update_nextcheck:%d", __function_name, (int)update_nextcheck);

	now = time(NULL);

	LOCK_CACHE;

	if (NULL != (dc_host = zbx_hashset_search(&config->hosts, &hostid)))
	{
		if (0 != (0x01 & update_nextcheck))
		{
			dc_host->snmp_disable_until = (int)calculate_proxy_nextcheck(
					dc_host->hostid, CONFIG_PROXYCONFIG_FREQUENCY, now);
		}
		if (0 != (0x02 & update_nextcheck))
		{
			dc_host->ipmi_disable_until = (int)calculate_proxy_nextcheck(
					dc_host->hostid, CONFIG_PROXYDATA_FREQUENCY, now);
		}
		dc_host->disable_until = (dc_host->snmp_disable_until <= dc_host->ipmi_disable_until)
				? dc_host->snmp_disable_until : dc_host->ipmi_disable_until;

		if (ZBX_LOC_POLLER == dc_host->location)
			dc_host->location = ZBX_LOC_NOWHERE;

		DCupdate_proxy_queue(dc_host);
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
