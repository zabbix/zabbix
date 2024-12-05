/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "checks_snmp.h"

#ifdef HAVE_NETSNMP

#define SNMP_NO_DEBUGGING

#include "zbxasyncpoller.h"
#include "zbxpoller.h"

#include "zbxtimekeeper.h"
#include "zbxcacheconfig.h"
#include "zbxip.h"
#include "zbxcomms.h"
#include "zbxalgo.h"
#include "zbxjson.h"
#include "zbxparam.h"
#include "zbxsysinfo.h"
#include "zbxdbhigh.h"
#include "zbxexpr.h"
#include "zbxstr.h"

#include <event2/event.h>
#include <event2/util.h>
#include <event2/dns.h>
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>
#include <net-snmp/library/large_fd_set.h>
#include "zbxself.h"

#ifndef EVDNS_BASE_INITIALIZE_NAMESERVERS
#	define EVDNS_BASE_INITIALIZE_NAMESERVERS	1
#endif

/*
 * SNMP Dynamic Index Cache
 * ========================
 *
 * Description
 * -----------
 *
 * Zabbix caches the whole index table for the particular OID separately based on:
 *   * IP address;
 *   * port;
 *   * community string (SNMPv2c);
 *   * context, security name (SNMPv3).
 *
 * Zabbix revalidates each index before using it to get a value and rebuilds the index cache for the OID if the
 * index is invalid.
 *
 * Example
 * -------
 *
 * OID for getting memory usage of process by PID (index):
 *   HOST-RESOURCES-MIB::hrSWRunPerfMem:<PID>
 *
 * OID for getting PID (index) by process name (value):
 *   HOST-RESOURCES-MIB::hrSWRunPath:<PID> <NAME>
 *
 * SNMP OID as configured in Zabbix to get memory usage of "snmpd" process:
 *   HOST-RESOURCES-MIB::hrSWRunPerfMem["index","HOST-RESOURCES-MIB::hrSWRunPath","snmpd"]
 *
 * 1. Zabbix walks hrSWRunPath table and caches all <PID> and <NAME> pairs of particular SNMP agent/user.
 * 2. Before each GET request Zabbix revalidates the cached <PID> by getting its <NAME> from hrSWRunPath table.
 * 3. If the names match then Zabbix uses the cached <PID> in the GET request for the hrSWRunPerfMem.
 *    Otherwise Zabbix rebuilds the hrSWRunPath cache for the particular agent/user (see 1.).
 *
 * Implementation
 * --------------
 *
 * The cache is implemented using hash tables. In ERD:
 * zbx_snmpidx_main_key_t -------------------------------------------0< zbx_snmpidx_mapping_t
 * (OID, host, <v2c: community|v3: (context, security name)>)           (index, value)
 */

/******************************************************************************
 *                                                                            *
 * This is zbx_snmp_walk() callback function prototype.                       *
 *                                                                            *
 * Parameters: arg      - [IN] user argument passed to zbx_snmp_walk()        *
 *             snmp_oid - [IN] OID walk function is looking for               *
 *             index    - [IN] index of found OID                             *
 *             value    - [IN] OID value                                      *
 *                                                                            *
 ******************************************************************************/
typedef void (zbx_snmp_walk_cb_func)(void *arg, const char *snmp_oid, const char *index, const char *value);

typedef enum
{
	ZBX_ASN_OCTET_STR_UTF_8,
	ZBX_ASN_OCTET_STR_HEX
}
zbx_snmp_asn_octet_str_t;

typedef struct
{
	char		*addr;
	unsigned short	port;
	char		*oid;
	char		*community_context;	/* community (SNMPv1 or v2c) or contextName (SNMPv3) */
	char		*security_name;		/* only SNMPv3, empty string in case of other versions */
	zbx_hashset_t	*mappings;
}
zbx_snmpidx_main_key_t;

typedef struct
{
	char		*value;
	char		*index;
}
zbx_snmpidx_mapping_t;

typedef struct
{
	oid	root_oid[MAX_OID_LEN];
	size_t	root_oid_len;
	char	*str_oid;
}
zbx_snmp_oid_t;

ZBX_PTR_VECTOR_DECL(snmp_oid, zbx_snmp_oid_t *)
ZBX_PTR_VECTOR_IMPL(snmp_oid, zbx_snmp_oid_t *)

typedef void*	zbx_snmp_sess_t;

typedef struct
{
	int			reqid;
	int			waiting;
	int			pdu_type;
	int			operation;
	zbx_snmp_oid_t		*p_oid;
	oid			name[MAX_OID_LEN];
	size_t			name_length;
	int			running;
	int			vars_num;
	void			*arg;
	char			*error;
	netsnmp_large_fd_set	fdset;
}
zbx_bulkwalk_context_t;

ZBX_PTR_VECTOR_DECL(bulkwalk_context, zbx_bulkwalk_context_t*)
ZBX_PTR_VECTOR_IMPL(bulkwalk_context, zbx_bulkwalk_context_t*)

struct zbx_snmp_context
{
	void				*arg;
	void				*arg_action;
	zbx_dc_item_context_t		item;
	zbx_snmp_sess_t			ssp;
	int				snmp_max_repetitions;
	int				retries;
	char				*results;
	size_t				results_alloc;
	size_t				results_offset;
	zbx_vector_snmp_oid_t		param_oids;
	zbx_vector_bulkwalk_context_t	bulkwalk_contexts;
	int				i;
	int				config_timeout;
	int				probe;
	unsigned char			snmp_version;
	char				*snmp_community;
	char				*snmpv3_securityname;
	char				*snmpv3_contextname;
	unsigned char			snmpv3_securitylevel;
	unsigned char			snmpv3_authprotocol;
	char				*snmpv3_authpassphrase;
	unsigned char			snmpv3_privprotocol;
	char				*snmpv3_privpassphrase;
	const char			*config_source_ip;
	unsigned char			snmp_oid_type;
	zbx_async_resolve_reverse_dns_t	resolve_reverse_dns;
	zbx_async_rdns_step_t		step;
	char				*reverse_dns;
};

typedef struct
{
	AGENT_RESULT		*result;
	int			errcode;
	struct event_base	*base;
	int			finished;
}
zbx_snmp_result_t;

static ZBX_THREAD_LOCAL zbx_hashset_t	snmpidx;		/* Dynamic Index Cache */
static char				zbx_snmp_init_done;
static char				zbx_snmp_init_bulkwalk_done;
static pthread_rwlock_t			snmp_exec_rwlock;
static char				snmp_rwlock_init_done;
static zbx_hashset_t	engineid_cache;
static int		engineid_cache_initialized = 0;

#define ZBX_SNMP_GET	0
#define ZBX_SNMP_WALK	1

#define	SNMP_MT_EXECLOCK					\
	if (0 != snmp_rwlock_init_done)				\
		pthread_rwlock_rdlock(&snmp_exec_rwlock)
#define	SNMP_MT_INITLOCK					\
	if (0 != snmp_rwlock_init_done)				\
		pthread_rwlock_wrlock(&snmp_exec_rwlock)
#define	SNMP_MT_UNLOCK						\
	if (0 != snmp_rwlock_init_done)				\
		pthread_rwlock_unlock(&snmp_exec_rwlock)

static void	zbx_init_snmp(const char *progname);

#define ZBX_SNMP_MAX_ENGINEID_LEN	32

typedef struct
{
	char	*address;
	char	*hostname;
	u_int	engineboots;
}
zbx_snmp_engineid_device_t;

ZBX_VECTOR_DECL(engineid_device, zbx_snmp_engineid_device_t)
ZBX_VECTOR_IMPL(engineid_device, zbx_snmp_engineid_device_t)

typedef struct
{
	unsigned char			engineid[ZBX_SNMP_MAX_ENGINEID_LEN];
	size_t				engineid_len;
	zbx_vector_engineid_device_t	devices;
	time_t				lastlog;
}
zbx_snmp_engineid_record_t;

static zbx_hash_t	snmp_engineid_cache_hash(const void *data)
{
	const zbx_snmp_engineid_record_t	*hv = (const zbx_snmp_engineid_record_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(hv->engineid, hv->engineid_len, ZBX_DEFAULT_HASH_SEED);
}

static int	snmp_engineid_cache_compare(const void *d1, const void *d2)
{
	const zbx_snmp_engineid_record_t	*hv1 = (const zbx_snmp_engineid_record_t *)d1;
	const zbx_snmp_engineid_record_t	*hv2 = (const zbx_snmp_engineid_record_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(hv1->engineid_len, hv2->engineid_len);

	return memcmp(hv1->engineid, hv2->engineid, hv1->engineid_len);
}

static void	zbx_clear_snmp_engineid_devices(zbx_vector_engineid_device_t *d)
{
	for (int i = 0; i < d->values_num; i++)
	{
		zbx_free(d->values[i].address);
		zbx_free(d->values[i].hostname);
	}

	zbx_vector_engineid_device_clear(d);
	zbx_vector_engineid_device_destroy(d);
}

void	zbx_clear_snmp_engineid_cache(void)
{
	zbx_hashset_iter_t		iter;
	zbx_snmp_engineid_record_t	*engineid;

	zbx_hashset_iter_reset(&engineid_cache, &iter);
	while (NULL != (engineid = (zbx_snmp_engineid_record_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_clear_snmp_engineid_devices(&engineid->devices);
		zbx_hashset_iter_remove(&iter);
	}
}

void	zbx_destroy_snmp_engineid_cache(void)
{
	zbx_clear_snmp_engineid_cache();
	zbx_hashset_destroy(&engineid_cache);
}

static int	zbx_snmp_cache_handle_engineid(netsnmp_session *session, zbx_dc_item_context_t *item_context)
{
	zbx_snmp_engineid_record_t	*ptr, local_record;
	zbx_snmp_engineid_device_t	d;
	u_int				current_engineboots = 0;
	int				ret = SUCCEED;

	if (0 == engineid_cache_initialized)
		return SUCCEED;

	if (ZBX_SNMP_MAX_ENGINEID_LEN < session->securityEngineIDLen)
	{
		ret = FAIL;
		item_context->ret = NOTSUPPORTED;
		SET_MSG_RESULT(&item_context->result, zbx_dsprintf(NULL, "Invalid SNMP engineId length "
				"\"" ZBX_FS_UI64 "\"", session->securityEngineIDLen));

		goto out;
	}

	local_record.engineid_len = session->securityEngineIDLen;
	memcpy(&local_record.engineid, session->securityEngineID, session->securityEngineIDLen);

	if (0 == (current_engineboots = session->engineBoots))
	{
		Enginetime et;

		et = search_enginetime_list(session->securityEngineID, (u_int)session->securityEngineIDLen);

		while (NULL != et)
		{
			current_engineboots = et->engineBoot;
			et = et->next;
		}
	}

	if (NULL == (ptr = zbx_hashset_search(&engineid_cache, &local_record)))
	{
		zbx_vector_engineid_device_create(&local_record.devices);
		d.address = zbx_strdup(NULL, item_context->interface.addr);
		d.hostname = zbx_strdup(NULL, item_context->host);
		d.engineboots = current_engineboots;

		zbx_vector_engineid_device_append(&local_record.devices, d);
		local_record.lastlog = 0;
		zbx_hashset_insert(&engineid_cache, &local_record, sizeof(local_record));

		goto out;
	}
	else
	{
		char	*hosts = NULL;
		size_t	hosts_alloc = 0, hosts_offset = 0;
		int	diff_engineboots = 0, found = 0;

		for (int i = 0; i < ptr->devices.values_num; i++)
		{
			if ((0 == strcmp(item_context->interface.addr, ptr->devices.values[i].address) &&
					0 == strcmp(item_context->host, ptr->devices.values[i].hostname)))
			{
				ptr->devices.values[i].engineboots = current_engineboots;
				found = 1;
				continue;
			}

			if (ptr->devices.values[i].engineboots != current_engineboots)
			{
				diff_engineboots = 1;

				if (0 != hosts_alloc)
					zbx_snprintf_alloc(&hosts, &hosts_alloc, &hosts_offset, ", ");

				zbx_snprintf_alloc(&hosts, &hosts_alloc, &hosts_offset, "%s (%s)",
						ptr->devices.values[i].address, ptr->devices.values[i].hostname);
			}
		}

		if (0 == found)
		{
			d.address = zbx_strdup(NULL, item_context->interface.addr);
			d.hostname = zbx_strdup(NULL, item_context->host);
			d.engineboots = current_engineboots;

			zbx_vector_engineid_device_append(&ptr->devices, d);
		}

		if (1 == diff_engineboots)
		{
#define	ZBX_SNMP_ENGINEID_WARNING_PERIOD	300
			time_t	now = time(NULL);

			if (now >= ptr->lastlog + ZBX_SNMP_ENGINEID_WARNING_PERIOD)
			{
				zabbix_log(LOG_LEVEL_WARNING, "SNMP engineId is not unique across following "
						"interfaces: %s (%s), %s", item_context->interface.addr,
						item_context->host, hosts);

				ptr->lastlog = now;
			}

			zbx_free(hosts);

			ret = FAIL;
			item_context->ret = NOTSUPPORTED;
			SET_MSG_RESULT(&item_context->result, zbx_dsprintf(NULL, "SNMP engineId is not unique"));

			goto out;
#undef	ZBX_SNMP_ENGINEID_WARNING_PERIOD
		}
	}
out:
	return ret;
}

#undef ZBX_SNMP_MAX_ENGINEID_LEN

void	zbx_housekeep_snmp_engineid_cache(void)
{
#define	ZBX_SNMP_ENGINEID_RETENTION_PERIOD	86400 + 3600
	zbx_hashset_iter_t		iter;
	zbx_snmp_engineid_record_t	*engineid;

	zbx_hashset_iter_reset(&engineid_cache, &iter);
	while (NULL != (engineid = (zbx_snmp_engineid_record_t *)zbx_hashset_iter_next(&iter)))
	{
		if (engineid->lastlog + ZBX_SNMP_ENGINEID_RETENTION_PERIOD <= time(NULL))
		{
			zbx_clear_snmp_engineid_devices(&engineid->devices);
			zbx_hashset_iter_remove(&iter);
		}
	}
#undef	ZBX_SNMP_ENGINEID_RETENTION_PERIOD
}

void	zbx_init_snmp_engineid_cache(void)
{
	zbx_hashset_create(&engineid_cache, 100, snmp_engineid_cache_hash, snmp_engineid_cache_compare);
	engineid_cache_initialized = 1;
}

static zbx_hash_t	__snmpidx_main_key_hash(const void *data)
{
	const zbx_snmpidx_main_key_t	*main_key = (const zbx_snmpidx_main_key_t *)data;

	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_STRING_HASH_FUNC(main_key->addr);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(&main_key->port, sizeof(main_key->port), hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(main_key->oid, strlen(main_key->oid), hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(main_key->community_context, strlen(main_key->community_context), hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(main_key->security_name, strlen(main_key->security_name), hash);

	return hash;
}

static int	__snmpidx_main_key_compare(const void *d1, const void *d2)
{
	const zbx_snmpidx_main_key_t	*main_key1 = (const zbx_snmpidx_main_key_t *)d1;
	const zbx_snmpidx_main_key_t	*main_key2 = (const zbx_snmpidx_main_key_t *)d2;

	int				ret;

	if (0 != (ret = strcmp(main_key1->addr, main_key2->addr)))
		return ret;

	ZBX_RETURN_IF_NOT_EQUAL(main_key1->port, main_key2->port);

	if (0 != (ret = strcmp(main_key1->community_context, main_key2->community_context)))
		return ret;

	if (0 != (ret = strcmp(main_key1->security_name, main_key2->security_name)))
		return ret;

	return strcmp(main_key1->oid, main_key2->oid);
}

static void	__snmpidx_main_key_clean(void *data)
{
	zbx_snmpidx_main_key_t	*main_key = (zbx_snmpidx_main_key_t *)data;

	zbx_free(main_key->addr);
	zbx_free(main_key->oid);
	zbx_free(main_key->community_context);
	zbx_free(main_key->security_name);
	zbx_hashset_destroy(main_key->mappings);
	zbx_free(main_key->mappings);
}

static zbx_hash_t	__snmpidx_mapping_hash(const void *data)
{
	const zbx_snmpidx_mapping_t	*mapping = (const zbx_snmpidx_mapping_t *)data;

	return ZBX_DEFAULT_STRING_HASH_FUNC(mapping->value);
}

static int	__snmpidx_mapping_compare(const void *d1, const void *d2)
{
	const zbx_snmpidx_mapping_t	*mapping1 = (const zbx_snmpidx_mapping_t *)d1;
	const zbx_snmpidx_mapping_t	*mapping2 = (const zbx_snmpidx_mapping_t *)d2;

	return strcmp(mapping1->value, mapping2->value);
}

static void	__snmpidx_mapping_clean(void *data)
{
	zbx_snmpidx_mapping_t	*mapping = (zbx_snmpidx_mapping_t *)data;

	zbx_free(mapping->value);
	zbx_free(mapping->index);
}

static int	zbx_snmp_oid_compare(const zbx_snmp_oid_t **s1, const zbx_snmp_oid_t **s2)
{
	return strcmp((*s1)->str_oid, (*s2)->str_oid);
}

static void	vector_snmp_oid_free(zbx_snmp_oid_t *ptr)
{
	zbx_free(ptr->str_oid);
	zbx_free(ptr);
}

static char	*get_item_community_context(const zbx_dc_item_t *item)
{
	if (ZBX_IF_SNMP_VERSION_1 == item->snmp_version || ZBX_IF_SNMP_VERSION_2 == item->snmp_version)
		return item->snmp_community;
	else if (ZBX_IF_SNMP_VERSION_3 == item->snmp_version)
		return item->snmpv3_contextname;

	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}

static char	*get_item_security_name(const zbx_dc_item_t *item)
{
	if (ZBX_IF_SNMP_VERSION_3 == item->snmp_version)
		return item->snmpv3_securityname;

	return "";
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves index that matches value from relevant index cache      *
 *                                                                            *
 * Parameters: item      - [IN] Configuration of Zabbix item, contains        *
 *                              IP address, port, community string, context,  *
 *                              security name.                                *
 *             snmp_oid  - [IN] OID of table which contains indexes           *
 *             value     - [IN] value for which to look up index              *
 *             idx       - [IN/OUT] destination pointer for                   *
 *                                  heap-(re)allocated index                  *
 *             idx_alloc - [IN/OUT] size of (re)allocated index               *
 *                                                                            *
 * Return value: FAIL    - dynamic index cache is empty or cache does not     *
 *                         contain index matching value                       *
 *               SUCCEED - idx contains found index,                          *
 *                         idx_alloc contains current size of                 *
 *                         heap-(re)allocated idx                             *
 *                                                                            *
 ******************************************************************************/
static int	cache_get_snmp_index(const zbx_dc_item_t *item, const char *snmp_oid, const char *value, char **idx,
		size_t *idx_alloc)
{
	int			ret = FAIL;
	zbx_snmpidx_main_key_t	*main_key, main_key_local;
	zbx_snmpidx_mapping_t	*mapping;
	size_t			idx_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() OID:'%s' value:'%s'", __func__, snmp_oid, value);

	if (NULL == snmpidx.slots)
		goto end;

	main_key_local.addr = item->interface.addr;
	main_key_local.port = item->interface.port;
	main_key_local.oid = (char *)snmp_oid;

	main_key_local.community_context = get_item_community_context(item);
	main_key_local.security_name = get_item_security_name(item);

	if (NULL == (main_key = (zbx_snmpidx_main_key_t *)zbx_hashset_search(&snmpidx, &main_key_local)))
		goto end;

	if (NULL == (mapping = (zbx_snmpidx_mapping_t *)zbx_hashset_search(main_key->mappings, &value)))
		goto end;

	zbx_strcpy_alloc(idx, idx_alloc, &idx_offset, mapping->index);
	ret = SUCCEED;
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s idx:'%s'", __func__, zbx_result_string(ret),
			SUCCEED == ret ? *idx : "");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: stores index-value pair in relevant index cache                   *
 *                                                                            *
 * Parameters: item      - [IN] Configuration of Zabbix item, contains        *
 *                              IP address, port, community string, context,  *
 *                              security name.                                *
 *             snmp_oid  - [IN] OID of table which contains indexes           *
 *             index     - [IN] index part of index-value pair                *
 *             value     - [IN] value part of index-value pair                *
 *                                                                            *
 ******************************************************************************/
static void	cache_put_snmp_index(const zbx_dc_item_t *item, const char *snmp_oid, const char *index,
		const char *value)
{
	zbx_snmpidx_main_key_t	*main_key, main_key_local;
	zbx_snmpidx_mapping_t	*mapping, mapping_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() OID:'%s' index:'%s' value:'%s'", __func__, snmp_oid, index, value);

	if (NULL == snmpidx.slots)
	{
		zbx_hashset_create_ext(&snmpidx, 100,
				__snmpidx_main_key_hash, __snmpidx_main_key_compare, __snmpidx_main_key_clean,
				ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	}

	main_key_local.addr = item->interface.addr;
	main_key_local.port = item->interface.port;
	main_key_local.oid = (char *)snmp_oid;

	main_key_local.community_context = get_item_community_context(item);
	main_key_local.security_name = get_item_security_name(item);

	if (NULL == (main_key = (zbx_snmpidx_main_key_t *)zbx_hashset_search(&snmpidx, &main_key_local)))
	{
		main_key_local.addr = zbx_strdup(NULL, item->interface.addr);
		main_key_local.oid = zbx_strdup(NULL, snmp_oid);

		main_key_local.community_context = zbx_strdup(NULL, get_item_community_context(item));
		main_key_local.security_name = zbx_strdup(NULL, get_item_security_name(item));

		main_key_local.mappings = (zbx_hashset_t *)zbx_malloc(NULL, sizeof(zbx_hashset_t));
		zbx_hashset_create_ext(main_key_local.mappings, 100,
				__snmpidx_mapping_hash, __snmpidx_mapping_compare, __snmpidx_mapping_clean,
				ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

		main_key = (zbx_snmpidx_main_key_t *)zbx_hashset_insert(&snmpidx, &main_key_local,
				sizeof(main_key_local));
	}

	if (NULL == (mapping = (zbx_snmpidx_mapping_t *)zbx_hashset_search(main_key->mappings, &value)))
	{
		mapping_local.value = zbx_strdup(NULL, value);
		mapping_local.index = zbx_strdup(NULL, index);

		zbx_hashset_insert(main_key->mappings, &mapping_local, sizeof(mapping_local));
	}
	else if (0 != strcmp(mapping->index, index))
	{
		zbx_free(mapping->index);
		mapping->index = zbx_strdup(NULL, index);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes index-value mappings from specified index cache           *
 *                                                                            *
 * Parameters: item      - [IN] Configuration of Zabbix item, contains        *
 *                              IP address, port, community string, context,  *
 *                              security name.                                *
 *             snmp_oid  - [IN] OID of table which contains indexes           *
 *                                                                            *
 * Comments: Does nothing if the index cache is empty or if it does not       *
 *           contain the cache for the specified OID.                         *
 *                                                                            *
 ******************************************************************************/
static void	cache_del_snmp_index_subtree(const zbx_dc_item_t *item, const char *snmp_oid)
{
	zbx_snmpidx_main_key_t	*main_key, main_key_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() OID:'%s'", __func__, snmp_oid);

	if (NULL == snmpidx.slots)
		goto end;

	main_key_local.addr = item->interface.addr;
	main_key_local.port = item->interface.port;
	main_key_local.oid = (char *)snmp_oid;

	main_key_local.community_context = get_item_community_context(item);
	main_key_local.security_name = get_item_security_name(item);

	if (NULL == (main_key = (zbx_snmpidx_main_key_t *)zbx_hashset_search(&snmpidx, &main_key_local)))
		goto end;

	zbx_hashset_clear(main_key->mappings);
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	zbx_snmpv3_set_auth_protocol(unsigned char snmpv3_authprotocol, struct snmp_session *session)
{
/* item snmpv3 authentication protocol */
/* SYNC WITH PHP!                      */
#define ITEM_SNMPV3_AUTHPROTOCOL_MD5		0
#define ITEM_SNMPV3_AUTHPROTOCOL_SHA1		1
#define ITEM_SNMPV3_AUTHPROTOCOL_SHA224		2
#define ITEM_SNMPV3_AUTHPROTOCOL_SHA256		3
#define ITEM_SNMPV3_AUTHPROTOCOL_SHA384		4
#define ITEM_SNMPV3_AUTHPROTOCOL_SHA512		5
	int	ret = SUCCEED;

	switch (snmpv3_authprotocol)
	{
		case ITEM_SNMPV3_AUTHPROTOCOL_MD5:
			session->securityAuthProto = usmHMACMD5AuthProtocol;
			session->securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
			break;
		case ITEM_SNMPV3_AUTHPROTOCOL_SHA1:
			session->securityAuthProto = usmHMACSHA1AuthProtocol;
			session->securityAuthProtoLen = USM_AUTH_PROTO_SHA_LEN;
			break;
#ifdef HAVE_NETSNMP_STRONG_AUTH
		case ITEM_SNMPV3_AUTHPROTOCOL_SHA224:
			session->securityAuthProto = usmHMAC128SHA224AuthProtocol;
			session->securityAuthProtoLen = OID_LENGTH(usmHMAC128SHA224AuthProtocol);
			break;
		case ITEM_SNMPV3_AUTHPROTOCOL_SHA256:
			session->securityAuthProto = usmHMAC192SHA256AuthProtocol;
			session->securityAuthProtoLen = OID_LENGTH(usmHMAC192SHA256AuthProtocol);
			break;
		case ITEM_SNMPV3_AUTHPROTOCOL_SHA384:
			session->securityAuthProto = usmHMAC256SHA384AuthProtocol;
			session->securityAuthProtoLen = OID_LENGTH(usmHMAC256SHA384AuthProtocol);
			break;
		case ITEM_SNMPV3_AUTHPROTOCOL_SHA512:
			session->securityAuthProto = usmHMAC384SHA512AuthProtocol;
			session->securityAuthProtoLen = OID_LENGTH(usmHMAC384SHA512AuthProtocol);
			break;
#endif
		default:
			ret = FAIL;
	}

	return ret;
#undef ITEM_SNMPV3_AUTHPROTOCOL_MD5
#undef ITEM_SNMPV3_AUTHPROTOCOL_SHA1
#undef ITEM_SNMPV3_AUTHPROTOCOL_SHA224
#undef ITEM_SNMPV3_AUTHPROTOCOL_SHA256
#undef ITEM_SNMPV3_AUTHPROTOCOL_SHA384
#undef ITEM_SNMPV3_AUTHPROTOCOL_SHA512
}

static char	*zbx_get_snmp_type_error(u_char type)
{
	switch (type)
	{
		case SNMP_NOSUCHOBJECT:
			return zbx_strdup(NULL, "No Such Object available on this agent at this OID");
		case SNMP_NOSUCHINSTANCE:
			return zbx_strdup(NULL, "No Such Instance currently exists at this OID");
		case SNMP_ENDOFMIBVIEW:
			return zbx_strdup(NULL, "No more variables left in this MIB View"
					" (it is past the end of the MIB tree)");
		default:
			return zbx_dsprintf(NULL, "Value has unknown type 0x%02X", (unsigned int)type);
	}
}

static int	zbx_get_snmp_response_error(const zbx_snmp_sess_t ssp, const zbx_dc_interface_t *interface, int status,
		const struct snmp_pdu *response, char *error, size_t max_error_len)
{
	int	ret;

	if (STAT_SUCCESS == status)
	{
		zbx_snprintf(error, max_error_len, "SNMP error: %s", snmp_errstring(response->errstat));
		ret = NOTSUPPORTED;
	}
	else if (STAT_ERROR == status)
	{
		char	*tmp_err_str;
		int	snmp_err;

		snmp_sess_error(ssp, NULL, &snmp_err, &tmp_err_str);

		if (SNMPERR_AUTHENTICATION_FAILURE == snmp_err)
		{
			tmp_err_str = zbx_strdup(tmp_err_str, "Authentication failure (incorrect password, community, "
					"key or duplicate engineID)");
		}

		zbx_snprintf(error, max_error_len, "Cannot connect to \"%s:%hu\": %s.",
				interface->addr, interface->port, tmp_err_str);
		zbx_free(tmp_err_str);
		ret = NETWORK_ERROR;
	}
	else if (STAT_TIMEOUT == status)
	{
		zbx_snprintf(error, max_error_len, "Timeout while connecting to \"%s:%hu\".",
				interface->addr, interface->port);
		ret = NETWORK_ERROR;
	}
	else
	{
		zbx_snprintf(error, max_error_len, "SNMP error: [%d]", status);
		ret = NOTSUPPORTED;
	}

	return ret;
}

static zbx_snmp_sess_t	zbx_snmp_open_session(unsigned char snmp_version, const char *ip, unsigned short port,
		char *snmp_community, char *snmpv3_securityname, char *snmpv3_contextname,
		unsigned char snmpv3_securitylevel, unsigned char snmpv3_authprotocol, char *snmpv3_authpassphrase,
		unsigned char snmpv3_privprotocol, char *snmpv3_privpassphrase, char *error, size_t max_error_len,
		int timeout, const char *config_source_ip)
{
/* item snmpv3 privacy protocol */
/* SYNC WITH PHP!               */
#define ITEM_SNMPV3_PRIVPROTOCOL_DES		0
#define ITEM_SNMPV3_PRIVPROTOCOL_AES128		1
#define ITEM_SNMPV3_PRIVPROTOCOL_AES192		2
#define ITEM_SNMPV3_PRIVPROTOCOL_AES256		3
#define ITEM_SNMPV3_PRIVPROTOCOL_AES192C	4
#define ITEM_SNMPV3_PRIVPROTOCOL_AES256C	5
	struct snmp_session	session;
	zbx_snmp_sess_t		ssp = NULL;
	char			addr[128];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	snmp_sess_init(&session);

	/* Allow using sub-OIDs higher than MAX_INT, like in 'snmpwalk -Ir'. */
	/* Disables the validation of varbind values against the MIB definition for the relevant OID. */
	if (SNMPERR_SUCCESS != netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DONT_CHECK_RANGE, 1))
	{
		/* This error is not fatal and should never happen (see netsnmp_ds_set_boolean() implementation). */
		/* Only items with sub-OIDs higher than MAX_INT will be unsupported. */
		zabbix_log(LOG_LEVEL_WARNING, "cannot set \"DontCheckRange\" option for Net-SNMP");
	}

	switch (snmp_version)
	{
		case ZBX_IF_SNMP_VERSION_1:
			session.version = SNMP_VERSION_1;
			break;
		case ZBX_IF_SNMP_VERSION_2:
			session.version = SNMP_VERSION_2c;
			break;
		case ZBX_IF_SNMP_VERSION_3:
			session.version = SNMP_VERSION_3;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			break;
	}

	session.timeout = timeout * 1000 * 1000;	/* timeout of one attempt in microseconds */
							/* (net-snmp default = 1 second) */
	if (SUCCEED == zbx_is_ip4(ip))
		zbx_snprintf(addr, sizeof(addr), "%s:%hu", ip, port);
	else
		zbx_snprintf(addr, sizeof(addr), "udp6:[%s]:%hu", ip, port);

	session.peername = addr;

	if (SNMP_VERSION_1 == session.version || SNMP_VERSION_2c == session.version)
	{
		session.community = (u_char *)snmp_community;
		session.community_len = strlen((char *)session.community);
		zabbix_log(LOG_LEVEL_DEBUG, "SNMP [%s@%s]", session.community, session.peername);
	}
	else if (SNMP_VERSION_3 == session.version)
	{
		/* set SNMPv3 user name */
		session.securityName = snmpv3_securityname;
		session.securityNameLen = strlen(session.securityName);

		/* set SNMPv3 context if specified */
		if ('\0' != *snmpv3_contextname)
		{
			session.contextName = snmpv3_contextname;
			session.contextNameLen = strlen(session.contextName);
		}

		/* set security level to authenticated, but not encrypted */
		switch (snmpv3_securitylevel)
		{
			case ZBX_ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV:
				session.securityLevel = SNMP_SEC_LEVEL_NOAUTH;
				break;
			case ZBX_ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV:
				session.securityLevel = SNMP_SEC_LEVEL_AUTHNOPRIV;

				if (FAIL == zbx_snmpv3_set_auth_protocol(snmpv3_authprotocol, &session))
				{
					zbx_snprintf(error, max_error_len, "Unsupported authentication protocol [%d]",
							snmpv3_authprotocol);
					goto end;
				}

				session.securityAuthKeyLen = USM_AUTH_KU_LEN;

				if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
						session.securityAuthProtoLen, (u_char *)snmpv3_authpassphrase,
						strlen(snmpv3_authpassphrase), session.securityAuthKey,
						&session.securityAuthKeyLen))
				{
					zbx_strlcpy(error, "Error generating Ku from authentication pass phrase",
							max_error_len);
					goto end;
				}
				break;
			case ZBX_ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV:
				session.securityLevel = SNMP_SEC_LEVEL_AUTHPRIV;

				if (FAIL == zbx_snmpv3_set_auth_protocol(snmpv3_authprotocol, &session))
				{
					zbx_snprintf(error, max_error_len, "Unsupported authentication protocol [%d]",
							snmpv3_authprotocol);
					goto end;
				}

				session.securityAuthKeyLen = USM_AUTH_KU_LEN;

				if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
						session.securityAuthProtoLen, (u_char *)snmpv3_authpassphrase,
						strlen(snmpv3_authpassphrase), session.securityAuthKey,
						&session.securityAuthKeyLen))
				{
					zbx_strlcpy(error, "Error generating Ku from authentication pass phrase",
							max_error_len);
					goto end;
				}

				switch (snmpv3_privprotocol)
				{
#ifdef HAVE_NETSNMP_SESSION_DES
					case ITEM_SNMPV3_PRIVPROTOCOL_DES:
						/* set privacy protocol to DES */
						session.securityPrivProto = usmDESPrivProtocol;
						session.securityPrivProtoLen = USM_PRIV_PROTO_DES_LEN;
						break;
#endif
					case ITEM_SNMPV3_PRIVPROTOCOL_AES128:
						/* set privacy protocol to AES128 */
						session.securityPrivProto = usmAESPrivProtocol;
						session.securityPrivProtoLen = USM_PRIV_PROTO_AES_LEN;
						break;
#ifdef HAVE_NETSNMP_STRONG_PRIV
					case ITEM_SNMPV3_PRIVPROTOCOL_AES192:
						/* set privacy protocol to AES192 */
						session.securityPrivProto = usmAES192PrivProtocol;
						session.securityPrivProtoLen = OID_LENGTH(usmAES192PrivProtocol);
						break;
					case ITEM_SNMPV3_PRIVPROTOCOL_AES256:
						/* set privacy protocol to AES256 */
						session.securityPrivProto = usmAES256PrivProtocol;
						session.securityPrivProtoLen = OID_LENGTH(usmAES256PrivProtocol);
						break;
					case ITEM_SNMPV3_PRIVPROTOCOL_AES192C:
						/* set privacy protocol to AES192 (Cisco version) */
						session.securityPrivProto = usmAES192CiscoPrivProtocol;
						session.securityPrivProtoLen = OID_LENGTH(usmAES192CiscoPrivProtocol);
						break;
					case ITEM_SNMPV3_PRIVPROTOCOL_AES256C:
						/* set privacy protocol to AES256 (Cisco version) */
						session.securityPrivProto = usmAES256CiscoPrivProtocol;
						session.securityPrivProtoLen = OID_LENGTH(usmAES256CiscoPrivProtocol);
						break;
#endif
					default:
						zbx_snprintf(error, max_error_len,
								"Unsupported privacy protocol [%d]",
								snmpv3_privprotocol);
						goto end;
				}

				session.securityPrivKeyLen = USM_PRIV_KU_LEN;

				if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
						session.securityAuthProtoLen, (u_char *)snmpv3_privpassphrase,
						strlen(snmpv3_privpassphrase), session.securityPrivKey,
						&session.securityPrivKeyLen))
				{
					zbx_strlcpy(error, "Error generating Ku from privacy pass phrase",
							max_error_len);
					goto end;
				}
				break;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "SNMPv3 [%s@%s]", session.securityName, session.peername);
	}

#ifdef HAVE_NETSNMP_SESSION_LOCALNAME
	if (NULL != config_source_ip)
	{
		/* In some cases specifying just local host (without local port) is not enough. We do */
		/* not care about the port number though so we let the OS select one by specifying 0. */
		/* See marc.info/?l=net-snmp-bugs&m=115624676507760 for details. */

		static ZBX_THREAD_LOCAL char	localname[64];

		zbx_snprintf(localname, sizeof(localname), "%s:0", config_source_ip);
		session.localname = localname;
	}
#endif

	SOCK_STARTUP;

	if (NULL == (ssp = snmp_sess_open(&session)))
	{
		SOCK_CLEANUP;

		zbx_strlcpy(error, "Cannot open SNMP session", max_error_len);
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ssp;
#undef ITEM_SNMPV3_PRIVPROTOCOL_DES
#undef ITEM_SNMPV3_PRIVPROTOCOL_AES128
#undef ITEM_SNMPV3_PRIVPROTOCOL_AES192
#undef ITEM_SNMPV3_PRIVPROTOCOL_AES256
#undef ITEM_SNMPV3_PRIVPROTOCOL_AES192C
#undef ITEM_SNMPV3_PRIVPROTOCOL_AES256C
}

static void	zbx_snmp_close_session(zbx_snmp_sess_t	session)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	snmp_sess_close(session);
	SOCK_CLEANUP;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static char	*zbx_sprint_asn_octet_str_dyn(const struct variable_list *var)
{
#define ZBX_MAC_ADDRESS_LEN	6
	/* don't guess output format if length is equal to MAC address to avoid false positive UTF-8 */
	if (var->type != ASN_OCTET_STR || NULL != memchr(var->val.string, '\0', var->val_len) ||
			ZBX_MAC_ADDRESS_LEN == var->val_len)
	{
		return NULL;
	}

	char	*strval_dyn = (char *)zbx_malloc(NULL, var->val_len + 1);

	memcpy(strval_dyn, var->val.string, var->val_len);
	strval_dyn[var->val_len] = '\0';

	if (FAIL == zbx_is_ascii_printable(strval_dyn) || FAIL == zbx_is_utf8(strval_dyn))
		zbx_free(strval_dyn);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "%s() full value:'%s'", __func__, strval_dyn);

	return strval_dyn;
#undef ZBX_MAC_ADDRESS_LEN
}

static char	*zbx_snmp_get_octet_string(const struct variable_list *var, unsigned char *string_type,
		zbx_snmp_asn_octet_str_t snmp_asn_octet_str)
{
	struct tree	*subtree;
	const char	*hint;
	char		buffer[MAX_BUFFER_LEN];
	char		*strval_dyn = NULL;
	unsigned char	type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* find the subtree to get display hint */
	subtree = get_tree(var->name, var->name_length, get_tree_head());
	hint = (NULL != subtree ? subtree->hint : NULL);

	if (ZBX_ASN_OCTET_STR_UTF_8 == snmp_asn_octet_str && NULL == hint)
	{
		/* avoid conversion to Hex-STRING for valid UTF-8 strings without hints */
		if (NULL != (strval_dyn = zbx_sprint_asn_octet_str_dyn(var)))
		{
			type = ZBX_SNMP_STR_ASCII;
			goto end;
		}
	}

	/* we will decide if we want the value from var->val or what snprint_value() returned later */
	if (-1 == snprint_value(buffer, sizeof(buffer), var->name, var->name_length, var))
		goto end;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() full value:'%s' hint:'%s'", __func__, buffer, ZBX_NULL2STR(hint));

	if (0 == strncmp(buffer, "Hex-STRING: ", 12))
	{
		strval_dyn = zbx_strdup(strval_dyn, buffer + 12);
		type = ZBX_SNMP_STR_HEX;
	}
	else if (NULL != hint && 0 == strncmp(buffer, "STRING: ", 8))
	{
		strval_dyn = zbx_strdup(strval_dyn, buffer + 8);
		type = ZBX_SNMP_STR_STRING;
	}
	else if (0 == strncmp(buffer, "OID: ", 5))
	{
		strval_dyn = zbx_strdup(strval_dyn, buffer + 5);
		type = ZBX_SNMP_STR_OID;
	}
	else if (0 == strncmp(buffer, "BITS: ", 6))
	{
		strval_dyn = zbx_strdup(strval_dyn, buffer + 6);
		type = ZBX_SNMP_STR_BITS;
	}
	else
	{
		/* snprint_value() escapes hintless ASCII strings, so */
		/* we are copying the raw unescaped value in this case */

		strval_dyn = (char *)zbx_malloc(strval_dyn, var->val_len + 1);
		memcpy(strval_dyn, var->val.string, var->val_len);
		strval_dyn[var->val_len] = '\0';
		type = ZBX_SNMP_STR_ASCII;
	}
end:
	if (NULL != string_type && NULL != strval_dyn)
		*string_type = type;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():'%s'", __func__, ZBX_NULL2STR(strval_dyn));

	return strval_dyn;
}

static int	zbx_snmp_set_result(const struct variable_list *var, AGENT_RESULT *result, unsigned char *string_type,
		zbx_snmp_asn_octet_str_t snmp_asn_octet_str)
{
	char		*strval_dyn;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%d", __func__, (int)var->type);

	*string_type = ZBX_SNMP_STR_UNDEFINED;

	if (ASN_OCTET_STR == var->type || ASN_OBJECT_ID == var->type)
	{
		if (NULL == (strval_dyn = zbx_snmp_get_octet_string(var, string_type, snmp_asn_octet_str)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot receive string value: out of memory."));
			ret = NOTSUPPORTED;
		}
		else
		{
			zbx_set_agent_result_type(result, ITEM_VALUE_TYPE_TEXT, strval_dyn);
			zbx_free(strval_dyn);
		}
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_UINTEGER == var->type || ASN_COUNTER == var->type || ASN_OPAQUE_U64 == var->type ||
			ASN_TIMETICKS == var->type || ASN_GAUGE == var->type)
#else
	else if (ASN_UINTEGER == var->type || ASN_COUNTER == var->type ||
			ASN_TIMETICKS == var->type || ASN_GAUGE == var->type)
#endif
	{
		SET_UI64_RESULT(result, (unsigned long)*var->val.integer);
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_COUNTER64 == var->type || ASN_OPAQUE_COUNTER64 == var->type)
#else
	else if (ASN_COUNTER64 == var->type)
#endif
	{
		SET_UI64_RESULT(result, (((zbx_uint64_t)var->val.counter64->high) << 32) +
				(zbx_uint64_t)var->val.counter64->low);
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_INTEGER == var->type || ASN_OPAQUE_I64 == var->type)
#else
	else if (ASN_INTEGER == var->type)
#endif
	{
		char	buffer[21];

		zbx_snprintf(buffer, sizeof(buffer), "%ld", *var->val.integer);

		zbx_set_agent_result_type(result, ITEM_VALUE_TYPE_TEXT, buffer);
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_OPAQUE_FLOAT == var->type)
	{
		SET_DBL_RESULT(result, *var->val.floatVal);
	}
	else if (ASN_OPAQUE_DOUBLE == var->type)
	{
		SET_DBL_RESULT(result, *var->val.doubleVal);
	}
#endif
	else if (ASN_IPADDRESS == var->type)
	{
		SET_STR_RESULT(result, zbx_dsprintf(NULL, "%u.%u.%u.%u",
				(unsigned int)var->val.string[0],
				(unsigned int)var->val.string[1],
				(unsigned int)var->val.string[2],
				(unsigned int)var->val.string[3]));
	}
	else if (ASN_NULL == var->type)
	{
		SET_STR_RESULT(result, zbx_strdup(NULL, "NULL"));
	}
	else
	{
		SET_MSG_RESULT(result, zbx_get_snmp_type_error(var->type));
		ret = NOTSUPPORTED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	zbx_snmp_dump_oid(char *buffer, size_t buffer_len, const oid *objid, size_t objid_len)
{
	size_t	offset = 0;

	*buffer = '\0';

	for (size_t i = 0; i < objid_len; i++)
		offset += zbx_snprintf(buffer + offset, buffer_len - offset, ".%lu", (unsigned long)objid[i]);
}

#define ZBX_OID_INDEX_STRING	0
#define ZBX_OID_INDEX_NUMERIC	1

static int	zbx_snmp_print_oid(char *buffer, size_t buffer_len, const oid *objid, size_t objid_len, int format)
{
	if (SNMPERR_SUCCESS != netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DONT_BREAKDOWN_OIDS,
			format))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set \"dontBreakdownOids\" option to %d for Net-SNMP", format);
		return -1;
	}

	return snprint_objid(buffer, buffer_len, objid, objid_len);
}

static int	zbx_snmp_choose_index(char *buffer, size_t buffer_len, const oid *objid, size_t objid_len,
		size_t root_string_len, size_t root_numeric_len, char *root_oid)
{
	oid	parsed_oid[MAX_OID_LEN];
	size_t	parsed_oid_len = MAX_OID_LEN;
	char	printed_oid[MAX_STRING_LEN];
	char	*printed_oid_escaped;

	/**************************************************************************************************************/
	/*                                                                                                            */
	/* When we are providing a value for {#SNMPINDEX}, we would like to provide a pretty value. This is only a    */
	/* concern for OIDs with string indices. For instance, suppose we are walking the following OID:              */
	/*                                                                                                            */
	/*   SNMP-VIEW-BASED-ACM-MIB::vacmGroupName                                                                   */
	/*                                                                                                            */
	/* Suppose also that we are currently looking at this OID:                                                    */
	/*                                                                                                            */
	/*   SNMP-VIEW-BASED-ACM-MIB::vacmGroupName.3."authOnlyUser"                                                  */
	/*                                                                                                            */
	/* Then, we would like to provide {#SNMPINDEX} with this value:                                               */
	/*                                                                                                            */
	/*   3."authOnlyUser"                                                                                         */
	/*                                                                                                            */
	/* An alternative approach would be to provide {#SNMPINDEX} with numeric value. While it is equivalent to the */
	/* string representation above, the string representation is more readable and thus more useful to users:     */
	/*                                                                                                            */
	/*   3.12.97.117.116.104.79.110.108.121.85.115.101.114                                                        */
	/*                                                                                                            */
	/* Here, 12 is the length of "authOnlyUser" and the rest is the string encoding using ASCII characters.       */
	/*                                                                                                            */
	/* There are two problems with always providing {#SNMPINDEX} that has an index representation as a string.    */
	/*                                                                                                            */
	/* The first problem is indices of type InetAddress. The Net-SNMP library has code for pretty-printing IP     */
	/* addresses, but no way to parse them back. As an example, consider the following OID:                       */
	/*                                                                                                            */
	/*   .1.3.6.1.2.1.4.34.1.4.1.4.192.168.3.255                                                                  */
	/*                                                                                                            */
	/* Its pretty representation is like this:                                                                    */
	/*                                                                                                            */
	/*   IP-MIB::ipAddressType.ipv4."192.168.3.255"                                                               */
	/*                                                                                                            */
	/* However, when trying to parse it, it turns into this OID:                                                  */
	/*                                                                                                            */
	/*   .1.3.6.1.2.1.4.34.1.4.1.13.49.57.50.46.49.54.56.46.51.46.50.53.53                                        */
	/*                                                                                                            */
	/* Apparently, this is different than the original.                                                           */
	/*                                                                                                            */
	/* The second problem is indices of type OCTET STRING, which might contain unprintable characters:            */
	/*                                                                                                            */
	/*   1.3.6.1.2.1.17.4.3.1.1.0.0.240.122.113.21                                                                */
	/*                                                                                                            */
	/* Its pretty representation is like this (note the single quotes which stand for a fixed-length string):     */
	/*                                                                                                            */
	/*   BRIDGE-MIB::dot1dTpFdbAddress.'...zq.'                                                                   */
	/*                                                                                                            */
	/* Here, '...zq.' stands for 0.0.240.122.113.21, where only 'z' (122) and 'q' (113) are printable.            */
	/*                                                                                                            */
	/* Apparently, this cannot be turned back into the numeric representation.                                    */
	/*                                                                                                            */
	/* So what we try to do is first print it pretty. If there is no string-looking index, return it as output.   */
	/* If there is such an index, we check that it can be parsed and that the result is the same as the original. */
	/*                                                                                                            */
	/**************************************************************************************************************/

	if (-1 == zbx_snmp_print_oid(printed_oid, sizeof(printed_oid), objid, objid_len, ZBX_OID_INDEX_STRING))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot print OID with string indices", __func__);
		goto numeric;
	}

	if (NULL == strchr(printed_oid, '"') && NULL == strchr(printed_oid, '\''))
	{
		if (0 != strncmp(printed_oid, root_oid, strlen(root_oid)))
		{
			size_t	offset = 0;
			char	*sep;

			if (NULL != (sep = strstr(printed_oid, "::")))
				offset = sep - printed_oid + 2;

			zbx_strlcpy(buffer, printed_oid + offset, buffer_len);
		}
		else
			zbx_strlcpy(buffer, printed_oid + root_string_len + 1, buffer_len);

		return SUCCEED;
	}

	printed_oid_escaped = zbx_dyn_escape_string(printed_oid, "\\");

	if (NULL == snmp_parse_oid(printed_oid_escaped, parsed_oid, &parsed_oid_len))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot parse OID '%s'", __func__, printed_oid_escaped);
		zbx_free(printed_oid_escaped);
		goto numeric;
	}
	zbx_free(printed_oid_escaped);

	if (parsed_oid_len == objid_len && 0 == memcmp(parsed_oid, objid, parsed_oid_len * sizeof(oid)))
	{
		zbx_strlcpy(buffer, printed_oid + root_string_len + 1, buffer_len);
		return SUCCEED;
	}
numeric:
	if (-1 == zbx_snmp_print_oid(printed_oid, sizeof(printed_oid), objid, objid_len, ZBX_OID_INDEX_NUMERIC))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot print OID with numeric indices", __func__);
		return FAIL;
	}

	zbx_strlcpy(buffer, printed_oid + root_numeric_len + 1, buffer_len);
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Functions for detecting looping in SNMP OID sequence using hashset         *
 *                                                                            *
 * Once there is a possibility of looping we start putting OIDs into hashset. *
 * We do it until a duplicate OID shows up or ZBX_OIDS_MAX_NUM OIDs have been *
 * collected.                                                                 *
 *                                                                            *
 * The hashset key is array of elements of type 'oid'. Element 0 holds the    *
 * number of OID components (sub-OIDs), element 1 and so on - OID components  *
 * themselves.                                                                *
 *                                                                            *
 * OIDs may contain up to 128 sub-OIDs, so 1 byte is sufficient to keep the   *
 * number of them. On the other hand, sub-OIDs are of type 'oid' which can be *
 * defined in NetSNMP as 'uint8_t' or 'u_long'. Sub-OIDs are compared as      *
 * numbers, so some platforms may require they to be properly aligned in      *
 * memory. To ensure proper alignment we keep number of elements in element 0 *
 * instead of using a separate structure element for it.                      *
 *                                                                            *
 ******************************************************************************/

static zbx_hash_t	__oids_seen_key_hash(const void *data)
{
	const oid	*key = (const oid *)data;

	return ZBX_DEFAULT_HASH_ALGO(key, (key[0] + 1) * sizeof(oid), ZBX_DEFAULT_HASH_SEED);
}

static int	__oids_seen_key_compare(const void *d1, const void *d2)
{
	const oid	*k1 = (const oid *)d1;
	const oid	*k2 = (const oid *)d2;

	if (d1 == d2)
		return 0;

	return snmp_oid_compare(k1 + 1, k1[0], k2 + 1, k2[0]);
}

static void	zbx_detect_loop_init(zbx_hashset_t *hs)
{
#define ZBX_OIDS_SEEN_INIT_SIZE	500		/* minimum initial number of slots in hashset */

	zbx_hashset_create(hs, ZBX_OIDS_SEEN_INIT_SIZE, __oids_seen_key_hash, __oids_seen_key_compare);

#undef ZBX_OIDS_SEEN_INIT_SIZE
}

static int	zbx_oid_is_new(zbx_hashset_t *hs, size_t root_len, const oid *p_oid, size_t oid_len)
{
#define ZBX_OIDS_MAX_NUM	1000000		/* max number of OIDs to store for checking duplicates */

	const oid	*var_oid;		/* points to the first element in the variable part */
	size_t		var_len;		/* number of elements in the variable part */
	oid		oid_k[MAX_OID_LEN + 1];	/* array for constructing a hashset key */

	/* OIDs share a common initial part. Save space by storing only the variable part. */

	var_oid = p_oid + root_len;
	var_len = oid_len - root_len;

	if (ZBX_OIDS_MAX_NUM == hs->num_data)
		return FAIL;

	oid_k[0] = var_len;
	memcpy(oid_k + 1, var_oid, var_len * sizeof(oid));

	if (NULL != zbx_hashset_search(hs, oid_k))
		return FAIL;					/* OID already seen */

	if (NULL != zbx_hashset_insert(hs, oid_k, (var_len + 1) * sizeof(oid)))
		return SUCCEED;					/* new OID */

	THIS_SHOULD_NEVER_HAPPEN;
	return FAIL;						/* hashset fail */

#undef ZBX_OIDS_MAX_NUM
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves information by walking OID tree                         *
 *                                                                            *
 * Parameters: ssp           - [IN] SNMP session handle                       *
 *             item          - [IN] configuration of Zabbix item              *
 *             snmp_oid      - [IN] OID of table with values of interest      *
 *             error         - [OUT] buffer to store error message            *
 *             max_error_len - [IN] maximum error message length              *
 *             max_succeed   - [OUT] value of "max_repetitions" that succeeded*
 *             min_fail      - [OUT] value of "max_repetitions" that failed   *
 *             max_vars      - [IN] suggested value of "max_repetitions"      *
 *             bulk          - [IN] whether GetBulkRequest-PDU should be used *
 *             walk_cb_func  - [IN] callback function to process discovered   *
 *                                  OIDs and their values                     *
 *             walk_cb_arg   - [IN] argument to pass to callback function     *
 *                                                                            *
 * Return value: NOTSUPPORTED - OID does not exist, any other critical error  *
 *               NETWORK_ERROR - recoverable network error                    *
 *               CONFIG_ERROR - item configuration error                      *
 *               SUCCEED - if function successfully completed                 *
 *                                                                            *
 ******************************************************************************/
static int	zbx_snmp_walk(zbx_snmp_sess_t ssp, const zbx_dc_item_t *item, const char *snmp_oid, char *error,
		size_t max_error_len, int *max_succeed, int *min_fail, int max_vars, int bulk,
		zbx_snmp_walk_cb_func walk_cb_func, void *walk_cb_arg)
{
	struct snmp_pdu		*pdu, *response;
	oid			anOID[MAX_OID_LEN], rootOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN, rootOID_len = MAX_OID_LEN, root_string_len, root_numeric_len;
	char			oid_index[MAX_STRING_LEN], root_oid[MAX_STRING_LEN];
	struct variable_list	*var;
	int			status, level, running, num_vars, check_oid_increase = 1, ret = SUCCEED;
	AGENT_RESULT		snmp_result;
	zbx_hashset_t		oids_seen;
	struct snmp_session	*ss;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%d OID:'%s' bulk:%d", __func__, (int)item->type, snmp_oid, bulk);

	if (ZBX_IF_SNMP_VERSION_1 == item->snmp_version)	/* GetBulkRequest-PDU available since SNMPv2 */
		bulk = SNMP_BULK_DISABLED;

	/* create OID from string */
	if (NULL == snmp_parse_oid(snmp_oid, rootOID, &rootOID_len))
	{
		zbx_snprintf(error, max_error_len, "snmp_parse_oid(): cannot parse OID \"%s\".", snmp_oid);
		ret = CONFIG_ERROR;
		goto out;
	}

	if (-1 == zbx_snmp_print_oid(oid_index, sizeof(oid_index), rootOID, rootOID_len, ZBX_OID_INDEX_STRING))
	{
		zbx_snprintf(error, max_error_len, "zbx_snmp_print_oid(): cannot print OID \"%s\" with string indices.",
				snmp_oid);
		ret = CONFIG_ERROR;
		goto out;
	}

	root_string_len = strlen(oid_index);

	if (-1 == zbx_snmp_print_oid(oid_index, sizeof(oid_index), rootOID, rootOID_len, ZBX_OID_INDEX_NUMERIC))
	{
		zbx_snprintf(error, max_error_len, "zbx_snmp_print_oid(): cannot print OID \"%s\""
				" with numeric indices.", snmp_oid);
		ret = CONFIG_ERROR;
		goto out;
	}

	root_numeric_len = strlen(oid_index);

	zbx_strlcpy(root_oid, oid_index, sizeof(root_oid));

	/* copy rootOID to anOID */
	memcpy(anOID, rootOID, rootOID_len * sizeof(oid));
	anOID_len = rootOID_len;

	/* initialize variables */
	level = 0;
	running = 1;

	while (1 == running)
	{
		/* create PDU */
		if (NULL == (pdu = snmp_pdu_create(SNMP_BULK_ENABLED == bulk ? SNMP_MSG_GETBULK : SNMP_MSG_GETNEXT)))
		{
			zbx_strlcpy(error, "snmp_pdu_create(): cannot create PDU object.", max_error_len);
			ret = CONFIG_ERROR;
			break;
		}

		if (NULL == snmp_add_null_var(pdu, anOID, anOID_len))	/* add OID as variable to PDU */
		{
			zbx_strlcpy(error, "snmp_add_null_var(): cannot add null variable.", max_error_len);
			ret = CONFIG_ERROR;
			snmp_free_pdu(pdu);
			break;
		}

		if (SNMP_BULK_ENABLED == bulk)
		{
			pdu->non_repeaters = 0;
			pdu->max_repetitions = max_vars;
		}

		ss = snmp_sess_session(ssp);
		ss->retries = (0 == bulk || (1 == max_vars && 0 == level) ? 1 : 0);

		/* communicate with agent */
		status = snmp_sess_synch_response(ssp, pdu, &response);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_sess_synch_response() status:%d s_snmp_errno:%d errstat:%ld"
				" max_vars:%d", __func__, status, ss->s_snmp_errno,
				NULL == response ? (long)-1 : response->errstat, max_vars);

		if (1 < max_vars &&
			((STAT_SUCCESS == status && SNMP_ERR_TOOBIG == response->errstat) || STAT_TIMEOUT == status))
		{
			/* The logic of iteratively reducing request size here is the same as in function */
			/* zbx_snmp_get_values(). Please refer to the description there for explanation.  */
reduce_max_vars:
			if (*min_fail > max_vars)
				*min_fail = max_vars;

			if (0 == level)
			{
				max_vars /= 2;
			}
			else if (1 == level)
			{
				max_vars = 1;
			}

			level++;

			goto next;
		}
		else if (STAT_SUCCESS != status || SNMP_ERR_NOERROR != response->errstat)
		{
			if (1 >= level && 1 < max_vars)
				goto reduce_max_vars;

			ret = zbx_get_snmp_response_error(ssp, &item->interface, status, response, error,
					max_error_len);
			running = 0;
			goto next;
		}

		if (NULL == response->variables)
		{
			if (1 >= level && 1 < max_vars)
				goto reduce_max_vars;

			zbx_strlcpy(error, "No values received.", max_error_len);
			ret = NOTSUPPORTED;
			running = 0;
			goto next;
		}

		/* process response */
		for (num_vars = 0, var = response->variables; NULL != var; num_vars++, var = var->next_variable)
		{
			char		**str_res;
			unsigned char	val_type;

			/* verify if we are in the same subtree */
			if (SNMP_ENDOFMIBVIEW == var->type || var->name_length < rootOID_len ||
					0 != memcmp(rootOID, var->name, rootOID_len * sizeof(oid)))
			{
				/* reached the end or past this subtree */
				running = 0;
				break;
			}
			else if (SNMP_NOSUCHOBJECT != var->type && SNMP_NOSUCHINSTANCE != var->type)
			{
				/* not an exception value */

				if (1 == check_oid_increase)	/* typical case */
				{
					int	res;

					/* normally devices return OIDs in increasing order, */
					/* snmp_oid_compare() will return -1 in this case */

					if (-1 != (res = snmp_oid_compare(anOID, anOID_len, var->name,
							var->name_length)))
					{
						if (0 == res)	/* got the same OID */
						{
							zbx_strlcpy(error, "OID not changing.", max_error_len);
							ret = NOTSUPPORTED;
							running = 0;
							break;
						}
						else	/* 1 == res */
						{
							/* OID decreased. Disable further checks of increasing */
							/* and set up a protection against endless looping. */

							check_oid_increase = 0;
							zbx_detect_loop_init(&oids_seen);
						}
					}
				}

				if (0 == check_oid_increase && FAIL == zbx_oid_is_new(&oids_seen, rootOID_len,
						var->name, var->name_length))
				{
					zbx_strlcpy(error, "OID loop detected or too many OIDs.", max_error_len);
					ret = NOTSUPPORTED;
					running = 0;
					break;
				}

				if (SUCCEED != zbx_snmp_choose_index(oid_index, sizeof(oid_index), var->name,
						var->name_length, root_string_len, root_numeric_len, root_oid))
				{
					zbx_snprintf(error, max_error_len, "zbx_snmp_choose_index():"
							" cannot choose appropriate index while walking for"
							" OID \"%s\".", snmp_oid);
					ret = NOTSUPPORTED;
					running = 0;
					break;
				}

				str_res = NULL;
				zbx_init_agent_result(&snmp_result);

				if (SUCCEED == zbx_snmp_set_result(var, &snmp_result, &val_type, ZBX_ASN_OCTET_STR_HEX))
				{
					if (ZBX_ISSET_TEXT(&snmp_result) && ZBX_SNMP_STR_HEX == val_type)
						zbx_remove_chars(snmp_result.text, "\r\n");

					str_res = ZBX_GET_STR_RESULT(&snmp_result);
				}

				if (NULL == str_res)
				{
					char	**msg;

					msg = ZBX_GET_MSG_RESULT(&snmp_result);

					zabbix_log(LOG_LEVEL_DEBUG, "cannot get index '%s' string value: %s",
							oid_index, NULL != msg && NULL != *msg ? *msg : "(null)");
				}
				else
					walk_cb_func(walk_cb_arg, snmp_oid, oid_index, snmp_result.str);

				zbx_free_agent_result(&snmp_result);

				/* go to next variable */
				memcpy((char *)anOID, (char *)var->name, var->name_length * sizeof(oid));
				anOID_len = var->name_length;
			}
			else
			{
				/* an exception value, so stop */
				char	*errmsg;

				errmsg = zbx_get_snmp_type_error(var->type);
				zbx_strlcpy(error, errmsg, max_error_len);
				zbx_free(errmsg);
				ret = NOTSUPPORTED;
				running = 0;
				break;
			}
		}

		if (*max_succeed < num_vars)
			*max_succeed = num_vars;
next:
		if (NULL != response)
			snmp_free_pdu(response);
	}

	if (0 == check_oid_increase)
		zbx_hashset_destroy(&oids_seen);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#undef ZBX_OID_INDEX_STRING
#undef ZBX_OID_INDEX_NUMERIC

static int	zbx_snmp_get_values(zbx_snmp_sess_t ssp, const zbx_dc_item_t *items,
		char oids[][ZBX_ITEM_SNMP_OID_LEN_MAX], AGENT_RESULT *results, int *errcodes,
		unsigned char *query_and_ignore_type, int num, int level, char *error, size_t max_error_len,
		int *max_succeed, int *min_fail, unsigned char poller_type)
{
	int			status, ret = SUCCEED, mapping_num = 0;
	int			mapping[ZBX_MAX_SNMP_ITEMS];
	oid			parsed_oids[ZBX_MAX_SNMP_ITEMS][MAX_OID_LEN];
	size_t			parsed_oid_lens[ZBX_MAX_SNMP_ITEMS];
	struct snmp_pdu		*pdu, *response;
	struct snmp_session	*ss;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() num:%d level:%d", __func__, num, level);

	if (NULL == (pdu = snmp_pdu_create(SNMP_MSG_GET)))
	{
		zbx_strlcpy(error, "snmp_pdu_create(): cannot create PDU object.", max_error_len);
		ret = CONFIG_ERROR;
		goto out;
	}

	for (int i = 0; i < num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (NULL != query_and_ignore_type && 0 == query_and_ignore_type[i])
			continue;

		parsed_oid_lens[i] = MAX_OID_LEN;

		if (NULL == snmp_parse_oid(oids[i], parsed_oids[i], &parsed_oid_lens[i]))
		{
			SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "snmp_parse_oid(): cannot parse OID \"%s\".",
					oids[i]));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		if (NULL == snmp_add_null_var(pdu, parsed_oids[i], parsed_oid_lens[i]))
		{
			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "snmp_add_null_var(): cannot add null variable."));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		mapping[mapping_num++] = i;
	}

	if (0 == mapping_num)
	{
		snmp_free_pdu(pdu);
		goto out;
	}

	ss = snmp_sess_session(ssp);
	ss->retries = (1 == mapping_num && 0 == level && ZBX_POLLER_TYPE_UNREACHABLE != poller_type ? 1 : 0);
retry:
	status = snmp_sess_synch_response(ssp, pdu, &response);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_sess_synch_response() status:%d s_snmp_errno:%d errstat:%ld "
			"mapping_num:%d", __func__, status, ss->s_snmp_errno, NULL == response ? (long)-1 :
			response->errstat, mapping_num);

	if (STAT_SUCCESS == status && SNMP_ERR_NOERROR == response->errstat)
	{
		struct variable_list	*var;
		int			i;

		for (i = 0, var = response->variables;; i++, var = var->next_variable)
		{
			/* check that response variable binding matches the request variable binding */

			if (i == mapping_num)
			{
				if (NULL != var)
				{
					zabbix_log(LOG_LEVEL_WARNING, "SNMP response from host \"%s\" contains"
							" too many variable bindings", items[0].host.host);

					if (1 != mapping_num)	/* give device a chance to handle a smaller request */
						goto halve;

					zbx_strlcpy(error, "Invalid SNMP response: too many variable bindings.",
							max_error_len);

					ret = NOTSUPPORTED;
				}

				break;
			}

			if (NULL == var)
			{
				zabbix_log(LOG_LEVEL_WARNING, "SNMP response from host \"%s\" contains"
						" too few variable bindings", items[0].host.host);

				if (1 != mapping_num)	/* give device a chance to handle a smaller request */
					goto halve;

				zbx_strlcpy(error, "Invalid SNMP response: too few variable bindings.", max_error_len);

				ret = NOTSUPPORTED;
				break;
			}

			int	j = mapping[i];

			if (parsed_oid_lens[j] != var->name_length ||
					0 != memcmp(parsed_oids[j], var->name, parsed_oid_lens[j] * sizeof(oid)))
			{
				char	sent_oid[ZBX_ITEM_SNMP_OID_LEN_MAX], received_oid[ZBX_ITEM_SNMP_OID_LEN_MAX];

				zbx_snmp_dump_oid(sent_oid, sizeof(sent_oid), parsed_oids[j], parsed_oid_lens[j]);
				zbx_snmp_dump_oid(received_oid, sizeof(received_oid), var->name, var->name_length);

				if (1 != mapping_num)
				{
					zabbix_log(LOG_LEVEL_WARNING, "SNMP response from host \"%s\" contains"
							" variable bindings that do not match the request:"
							" sent \"%s\", received \"%s\"",
							items[0].host.host, sent_oid, received_oid);

					goto halve;	/* give device a chance to handle a smaller request */
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "SNMP response from host \"%s\" contains"
							" variable bindings that do not match the request:"
							" sent \"%s\", received \"%s\"",
							items[0].host.host, sent_oid, received_oid);
				}
			}

			/* process received data */
			unsigned char	val_type;

			if (NULL != query_and_ignore_type && 1 == query_and_ignore_type[j])
				(void)zbx_snmp_set_result(var, &results[j], &val_type, ZBX_ASN_OCTET_STR_HEX);
			else
				errcodes[j] = zbx_snmp_set_result(var, &results[j], &val_type, ZBX_ASN_OCTET_STR_HEX);

			if (ZBX_ISSET_TEXT(&results[j]) && ZBX_SNMP_STR_HEX == val_type)
				zbx_remove_chars(results[j].text, "\r\n");
		}

		if (SUCCEED == ret)
		{
			if (*max_succeed < mapping_num)
				*max_succeed = mapping_num;
		}
		/* min_fail value is updated when bulk request is halved in the case of failure */
	}
	else if (STAT_SUCCESS == status && SNMP_ERR_NOSUCHNAME == response->errstat && 0 != response->errindex)
	{
		/* If a request PDU contains a bad variable, the specified behavior is different between SNMPv1 and */
		/* later versions. In SNMPv1, the whole PDU is rejected and "response->errindex" is set to indicate */
		/* the bad variable. In SNMPv2 and later, the SNMP agent processes the PDU by filling values for the */
		/* known variables and marking unknown variables individually in the variable binding list. However, */
		/* SNMPv2 allows SNMPv1 behavior, too. So regardless of the SNMP version used, if we get this error, */
		/* then we fix the PDU by removing the bad variable and retry the request. */

		int	i = response->errindex - 1;

		if (0 > i || i >= mapping_num)
		{
			zabbix_log(LOG_LEVEL_WARNING, "SNMP response from host \"%s\" contains"
					" an out of bounds error index: %ld", items[0].host.host, response->errindex);

			zbx_strlcpy(error, "Invalid SNMP response: error index out of bounds.", max_error_len);

			ret = NOTSUPPORTED;
			goto exit;
		}

		int	j = mapping[i];

		zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_sess_synch_response() errindex:%ld OID:'%s'", __func__,
				response->errindex, oids[j]);

		if (NULL == query_and_ignore_type || 0 == query_and_ignore_type[j])
		{
			errcodes[j] = zbx_get_snmp_response_error(ssp, &items[0].interface, status, response, error,
					max_error_len);
			SET_MSG_RESULT(&results[j], zbx_strdup(NULL, error));
			*error = '\0';
		}

		if (1 < mapping_num)
		{
			if (NULL != (pdu = snmp_fix_pdu(response, SNMP_MSG_GET)))
			{
				memmove(mapping + i, mapping + i + 1, sizeof(int) * (mapping_num - i - 1));
				mapping_num--;

				snmp_free_pdu(response);
				goto retry;
			}
			else
			{
				zbx_strlcpy(error, "snmp_fix_pdu(): cannot fix PDU object.", max_error_len);
				ret = NOTSUPPORTED;
			}
		}
	}
	else if (1 < mapping_num &&
			((STAT_SUCCESS == status && SNMP_ERR_TOOBIG == response->errstat) || STAT_TIMEOUT == status ||
			(STAT_ERROR == status && SNMPERR_TOO_LONG == ss->s_snmp_errno)))
	{
		/* Since we are trying to obtain multiple values from the SNMP agent, the response that it has to  */
		/* generate might be too big. It seems to be required by the SNMP standard that in such cases the  */
		/* error status should be set to "tooBig(1)". However, some devices simply do not respond to such  */
		/* queries and we get a timeout. Moreover, some devices exhibit both behaviors - they either send  */
		/* "tooBig(1)" or do not respond at all. So what we do is halve the number of variables to query - */
		/* it should work in the vast majority of cases, because, since we are now querying "num" values,  */
		/* we know that querying "num/2" values succeeded previously. The case where it can still fail due */
		/* to exceeded maximum response size is if we are now querying values that are unusually large. So */
		/* if querying with half the number of the last values does not work either, we resort to querying */
		/* values one by one, and the next time configuration cache gives us items to query, it will give  */
		/* us less. */

		/* The explanation above is for the first two conditions. The third condition comes from SNMPv3, */
		/* where the size of the request that we are trying to send exceeds device's "msgMaxSize" limit. */
halve:
		if (*min_fail > mapping_num)
			*min_fail = mapping_num;

		if (0 == level)
		{
			/* halve the number of items */

			ret = zbx_snmp_get_values(ssp, items, oids, results, errcodes, query_and_ignore_type, num / 2,
					level + 1, error, max_error_len, max_succeed, min_fail, poller_type);

			if (SUCCEED != ret)
				goto exit;

			int	base = num / 2;

			ret = zbx_snmp_get_values(ssp, items + base, oids + base, results + base, errcodes + base,
					NULL == query_and_ignore_type ? NULL : query_and_ignore_type + base, num - base,
					level + 1, error, max_error_len, max_succeed, min_fail, poller_type);
		}
		else if (1 == level)
		{
			/* resort to querying items one by one */

			for (int i = 0; i < num; i++)
			{
				if (SUCCEED != errcodes[i])
					continue;

				ret = zbx_snmp_get_values(ssp, items + i, oids + i, results + i, errcodes + i,
						NULL == query_and_ignore_type ? NULL : query_and_ignore_type + i, 1,
						level + 1, error, max_error_len, max_succeed, min_fail, poller_type);

				if (SUCCEED != ret)
					goto exit;
			}
		}
	}
	else
	{
		if (1 <= level)
			goto halve;

		ret = zbx_get_snmp_response_error(ssp, &items[0].interface, status, response, error, max_error_len);
	}
exit:
	if (NULL != response)
		snmp_free_pdu(response);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: translates well-known object identifiers into numeric form        *
 *                                                                            *
 ******************************************************************************/
static void	zbx_snmp_translate(char *oid_translated, const char *snmp_oid, size_t max_oid_len)
{
	typedef struct
	{
		const size_t	sz;
		const char	*mib;
		const char	*replace;
	}
	zbx_mib_norm_t;

#define LEN_STR(x)	ZBX_CONST_STRLEN(x), x
	static ZBX_THREAD_LOCAL zbx_mib_norm_t	mibs[] =
	{
		/* the most popular items first */
		{LEN_STR("ifDescr"),		".1.3.6.1.2.1.2.2.1.2"},
		{LEN_STR("ifInOctets"),		".1.3.6.1.2.1.2.2.1.10"},
		{LEN_STR("ifOutOctets"),	".1.3.6.1.2.1.2.2.1.16"},
		{LEN_STR("ifAdminStatus"),	".1.3.6.1.2.1.2.2.1.7"},
		{LEN_STR("ifOperStatus"),	".1.3.6.1.2.1.2.2.1.8"},
		{LEN_STR("ifIndex"),		".1.3.6.1.2.1.2.2.1.1"},
		{LEN_STR("ifType"),		".1.3.6.1.2.1.2.2.1.3"},
		{LEN_STR("ifMtu"),		".1.3.6.1.2.1.2.2.1.4"},
		{LEN_STR("ifSpeed"),		".1.3.6.1.2.1.2.2.1.5"},
		{LEN_STR("ifPhysAddress"),	".1.3.6.1.2.1.2.2.1.6"},
		{LEN_STR("ifInUcastPkts"),	".1.3.6.1.2.1.2.2.1.11"},
		{LEN_STR("ifInNUcastPkts"),	".1.3.6.1.2.1.2.2.1.12"},
		{LEN_STR("ifInDiscards"),	".1.3.6.1.2.1.2.2.1.13"},
		{LEN_STR("ifInErrors"),		".1.3.6.1.2.1.2.2.1.14"},
		{LEN_STR("ifInUnknownProtos"),	".1.3.6.1.2.1.2.2.1.15"},
		{LEN_STR("ifOutUcastPkts"),	".1.3.6.1.2.1.2.2.1.17"},
		{LEN_STR("ifOutNUcastPkts"),	".1.3.6.1.2.1.2.2.1.18"},
		{LEN_STR("ifOutDiscards"),	".1.3.6.1.2.1.2.2.1.19"},
		{LEN_STR("ifOutErrors"),	".1.3.6.1.2.1.2.2.1.20"},
		{LEN_STR("ifOutQLen"),		".1.3.6.1.2.1.2.2.1.21"},
		{0}
	};
#undef LEN_STR

	int	found = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() OID:'%s'", __func__, snmp_oid);

	for (int i = 0; 0 != mibs[i].sz; i++)
	{
		if (0 == strncmp(mibs[i].mib, snmp_oid, mibs[i].sz))
		{
			found = 1;
			zbx_snprintf(oid_translated, max_oid_len, "%s%s", mibs[i].replace, snmp_oid + mibs[i].sz);
			break;
		}
	}

	if (0 == found)
		zbx_strlcpy(oid_translated, snmp_oid, max_oid_len);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() oid_translated:'%s'", __func__, oid_translated);
}

/* discovered SNMP object, identified by its index */
typedef struct
{
	/* object index returned by zbx_snmp_walk */
	char	*index;

	/* an array of OID values stored in the same order as defined in OID key */
	char	**values;
}
zbx_snmp_dobject_t;

ZBX_PTR_VECTOR_DECL(snmp_dobject_ptr, zbx_snmp_dobject_t*)
ZBX_PTR_VECTOR_IMPL(snmp_dobject_ptr, zbx_snmp_dobject_t*)

/* helper data structure used by snmp discovery */
typedef struct
{
	/* index of OID being currently processed (walked) */
	int			num;

	/* discovered SNMP objects */
	zbx_hashset_t		objects;

	/* index (order) of discovered SNMP objects */
	zbx_vector_snmp_dobject_ptr_t	index;

	/* request data structure used to parse discovery OID key */
	AGENT_REQUEST		request;
}
zbx_snmp_ddata_t;

/* discovery objects hashset support */
static zbx_hash_t	zbx_snmp_dobject_hash(const void *data)
{
	const char	*index = *(const char **)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(index, strlen(index), ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_snmp_dobject_compare(const void *d1, const void *d2)
{
	const char	*i1 = *(const char **)d1;
	const char	*i2 = *(const char **)d2;

	return strcmp(i1, i2);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes snmp discovery data object                            *
 *                                                                            *
 * Parameters: data          - [IN] snmp discovery data object                *
 *             key           - [IN] discovery OID key                         *
 *             error         - [OUT] buffer to store error message            *
 *             max_error_len - [IN] maximum error message length              *
 *                                                                            *
 * Return value: CONFIG_ERROR - OID key configuration error                   *
 *               SUCCEED - if function successfully completed                 *
 *                                                                            *
 ******************************************************************************/
static int	zbx_snmp_ddata_init(zbx_snmp_ddata_t *data, const char *key, char *error, size_t max_error_len)
{
	int	ret = CONFIG_ERROR;

	zbx_init_agent_request(&data->request);

	if (SUCCEED != zbx_parse_item_key(key, &data->request))
	{
		zbx_strlcpy(error, "Invalid SNMP OID: cannot parse expression.", max_error_len);
		goto out;
	}

	if (0 == data->request.nparam || 0 != (data->request.nparam & 1))
	{
		zbx_strlcpy(error, "Invalid SNMP OID: pairs of macro and OID are expected.", max_error_len);
		goto out;
	}

	for (int i = 0; i < data->request.nparam; i += 2)
	{
		if (SUCCEED != zbx_is_discovery_macro(data->request.params[i]))
		{
			zbx_snprintf(error, max_error_len, "Invalid SNMP OID: macro \"%s\" is invalid",
					data->request.params[i]);
			goto out;
		}

		if (0 == strcmp(data->request.params[i], "{#SNMPINDEX}"))
		{
			zbx_strlcpy(error, "Invalid SNMP OID: macro \"{#SNMPINDEX}\" is not allowed.", max_error_len);
			goto out;
		}
	}

	for (int i = 2; i < data->request.nparam; i += 2)
	{
		for (int j = 0; j < i; j += 2)
		{
			if (0 == strcmp(data->request.params[i], data->request.params[j]))
			{
				zbx_strlcpy(error, "Invalid SNMP OID: unique macros are expected.", max_error_len);
				goto out;
			}
		}
	}

	zbx_hashset_create(&data->objects, 10, zbx_snmp_dobject_hash, zbx_snmp_dobject_compare);
	zbx_vector_snmp_dobject_ptr_create(&data->index);

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_free_agent_request(&data->request);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases data allocated by snmp discovery                         *
 *                                                                            *
 * Parameters: data - [IN] snmp discovery data object                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_snmp_ddata_clean(zbx_snmp_ddata_t *data)
{
	zbx_hashset_iter_t	iter;
	zbx_snmp_dobject_t	*obj;

	zbx_vector_snmp_dobject_ptr_destroy(&data->index);

	zbx_hashset_iter_reset(&data->objects, &iter);
	while (NULL != (obj = (zbx_snmp_dobject_t *)zbx_hashset_iter_next(&iter)))
	{
		for (int i = 0; i < data->request.nparam / 2; i++)
			zbx_free(obj->values[i]);

		zbx_free(obj->index);
		zbx_free(obj->values);
	}

	zbx_hashset_destroy(&data->objects);

	zbx_free_agent_request(&data->request);
}

static void	zbx_snmp_walk_discovery_cb(void *arg, const char *snmp_oid, const char *index, const char *value)
{
	zbx_snmp_ddata_t	*data = (zbx_snmp_ddata_t *)arg;
	zbx_snmp_dobject_t	*obj;

	ZBX_UNUSED(snmp_oid);

	if (NULL == (obj = (zbx_snmp_dobject_t *)zbx_hashset_search(&data->objects, &index)))
	{
		zbx_snmp_dobject_t	new_obj;

		new_obj.index = zbx_strdup(NULL, index);
		new_obj.values = (char **)zbx_malloc(NULL, sizeof(char *) * data->request.nparam / 2);
		memset(new_obj.values, 0, sizeof(char *) * data->request.nparam / 2);

		obj = (zbx_snmp_dobject_t *)zbx_hashset_insert(&data->objects, &new_obj, sizeof(new_obj));
		zbx_vector_snmp_dobject_ptr_append(&data->index, obj);
	}

	obj->values[data->num] = zbx_strdup(NULL, value);
}

static int	zbx_snmp_process_discovery(zbx_snmp_sess_t ssp, const zbx_dc_item_t *item, AGENT_RESULT *result,
		int *errcode, char *error, size_t max_error_len, int *max_succeed, int *min_fail, int max_vars,
		int bulk)
{
	int			ret;
	char			oid_translated[ZBX_ITEM_SNMP_OID_LEN_MAX];
	struct zbx_json		js;
	zbx_snmp_ddata_t	data;
	zbx_snmp_dobject_t	*obj;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (ret = zbx_snmp_ddata_init(&data, item->snmp_oid, error, max_error_len)))
		goto out;

	for (data.num = 0; data.num < data.request.nparam / 2; data.num++)
	{
		zbx_snmp_translate(oid_translated, data.request.params[data.num * 2 + 1], sizeof(oid_translated));

		if (SUCCEED != (ret = zbx_snmp_walk(ssp, item, oid_translated, error, max_error_len,
				max_succeed, min_fail, max_vars, bulk, zbx_snmp_walk_discovery_cb, (void *)&data)))
		{
			goto clean;
		}
	}

	zbx_json_initarray(&js, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < data.index.values_num; i++)
	{
		obj = (zbx_snmp_dobject_t *)data.index.values[i];

		zbx_json_addobject(&js, NULL);
		zbx_json_addstring(&js, "{#SNMPINDEX}", obj->index, ZBX_JSON_TYPE_STRING);

		for (int j = 0; j < data.request.nparam / 2; j++)
		{
			if (NULL == obj->values[j])
				continue;

			zbx_json_addstring(&js, data.request.params[j * 2], obj->values[j], ZBX_JSON_TYPE_STRING);
		}
		zbx_json_close(&js);
	}

	zbx_json_close(&js);

	SET_TEXT_RESULT(result, zbx_strdup(NULL, js.buffer));

	zbx_json_free(&js);
clean:
	zbx_snmp_ddata_clean(&data);
out:
	if (SUCCEED != (*errcode = ret))
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	zbx_snmp_walk_cache_cb(void *arg, const char *snmp_oid, const char *index, const char *value)
{
	cache_put_snmp_index((const zbx_dc_item_t *)arg, snmp_oid, index, value);
}

typedef struct
{
	int	numeric_oids;
	int	numeric_enum;
	int	numeric_ts;
	int	oid_format;
	int	no_print_units;
}
zbx_snmp_format_opts_t;

static void	snmp_bulkwalk_get_options(zbx_snmp_format_opts_t *opts)
{
	opts->numeric_oids = netsnmp_ds_get_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_PRINT_NUMERIC_OIDS);
	opts->numeric_enum = netsnmp_ds_get_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_PRINT_NUMERIC_ENUM);
	opts->numeric_ts = netsnmp_ds_get_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_NUMERIC_TIMETICKS);
	opts->oid_format = netsnmp_ds_get_int(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_OID_OUTPUT_FORMAT);
	opts->no_print_units = netsnmp_ds_get_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DONT_PRINT_UNITS);
}

static void	snmp_bulkwalk_set_options(zbx_snmp_format_opts_t *opts)
{
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_PRINT_NUMERIC_OIDS, opts->numeric_oids);
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_PRINT_NUMERIC_ENUM, opts->numeric_enum);
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_NUMERIC_TIMETICKS, opts->numeric_ts);
	netsnmp_ds_set_int(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_OID_OUTPUT_FORMAT, opts->oid_format);
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DONT_PRINT_UNITS, opts->no_print_units);
}

static void	snmp_bulkwalk_remove_matching_oids(zbx_vector_snmp_oid_t *oids)
{
	zbx_vector_snmp_oid_sort(oids, (zbx_compare_func_t)zbx_snmp_oid_compare);

	for (int i = 1; i < oids->values_num; i++)
	{
		size_t len = strlen(oids->values[i - 1]->str_oid);

		while (0 == strncmp(oids->values[i - 1]->str_oid, oids->values[i]->str_oid, len))
		{
			if ('.' != oids->values[i]->str_oid[len] && '\0' != oids->values[i]->str_oid[len])
				break;

			vector_snmp_oid_free(oids->values[i]);
			zbx_vector_snmp_oid_remove(oids, i);

			if (i == oids->values_num)
				return;
		}
	}
}

static int	snmp_bulkwalk_parse_param(const char *snmp_oid, zbx_vector_snmp_oid_t *oids_out,
		char *error, size_t max_error_len)
{
	char		oid_translated[ZBX_ITEM_SNMP_OID_LEN_MAX], buffer[MAX_OID_LEN];
	zbx_snmp_oid_t	*root_oid;

	zbx_snmp_translate(oid_translated, snmp_oid, sizeof(oid_translated));

	root_oid = (zbx_snmp_oid_t *)zbx_malloc(NULL, sizeof(zbx_snmp_oid_t));
	root_oid->root_oid_len = MAX_OID_LEN;

	if (NULL == snmp_parse_oid(oid_translated, root_oid->root_oid, &root_oid->root_oid_len))
	{
		zbx_free(root_oid);
		zbx_snprintf(error, max_error_len, "snmp_parse_oid(): cannot parse OID \"%s\".",
				oid_translated);
		return FAIL;
	}

	snprint_objid(buffer, sizeof(buffer), root_oid->root_oid, root_oid->root_oid_len);
	root_oid->str_oid = zbx_strdup(NULL, buffer);
	zbx_vector_snmp_oid_append(oids_out, root_oid);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() oids_num:%d", __func__, oids_out->values_num);

	return SUCCEED;
}

static int	snmp_bulkwalk_parse_params(AGENT_REQUEST *request, zbx_vector_snmp_oid_t *oids_out,
		char *error, size_t max_error_len)
{
	for (int i = 0; i < request->nparam; i++)
	{
		if (FAIL == snmp_bulkwalk_parse_param(request->params[i], oids_out, error, max_error_len))
			return FAIL;
	}

	if (1 < oids_out->values_num)
	{
		zbx_vector_snmp_oid_sort(oids_out, (zbx_compare_func_t)zbx_snmp_oid_compare);
		snmp_bulkwalk_remove_matching_oids(oids_out);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() oids_num:%d", __func__, oids_out->values_num);

	return SUCCEED;
}

static int	snmp_get_value_from_var(struct variable_list *var, char **results, size_t *results_alloc,
		size_t *results_offset, char *error, size_t max_error_len)
{
	char		**str_res = NULL;
	AGENT_RESULT	result;
	unsigned char	val_type;
	int		ret = SUCCEED;

	zbx_init_agent_result(&result);

	if (SUCCEED == zbx_snmp_set_result(var, &result, &val_type, ZBX_ASN_OCTET_STR_UTF_8))
	{
		if (ZBX_ISSET_TEXT(&result) && ZBX_SNMP_STR_HEX == val_type)
			zbx_remove_chars(result.text, "\r\n");

		str_res = ZBX_GET_STR_RESULT(&result);
	}

	if (NULL == str_res)
	{
		char	**msg;

		msg = ZBX_GET_MSG_RESULT(&result);

		zbx_snprintf(error, max_error_len, "cannot get SNMP result: %s", *msg);
		ret = NOTSUPPORTED;
	}
	else
		zbx_strcpy_alloc(results, results_alloc, results_offset, *str_res);

	zbx_free_agent_result(&result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: quotes string value same way Net-SNMP library does it             *
 *                                                                            *
 * Parameters: buffer      - [OUT] output buffer                              *
 *             buffer_size - [IN] output buffer size                          *
 *             str         - [IN] SNMP variable                               *
 *             len         - [IN]                                             *
 *                                                                            *
 * Return value: SUCCEED - string was quoted successfully                     *
 *               FAIL    - output buffer is too small                         *
 *                                                                            *
 ******************************************************************************/
static int	snmp_native_quote_string_value(char *buffer, size_t buffer_size, char *str, size_t len)
{
	size_t output_len = 0;

	if (!snmp_cstrcat((unsigned char **)&buffer, &buffer_size, &output_len, 0, "\""))
	{
		return FAIL;
	}
	if (!sprint_realloc_asciistring((unsigned char **)&buffer, &buffer_size, &output_len, 0, (unsigned char *)str,
			len))
	{
		return FAIL;
	}
	if (!snmp_cstrcat((unsigned char **)&buffer, &buffer_size, &output_len, 0, "\""))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: quotes string value if Net-SNMP library hasn't quoted it          *
 *                                                                            *
 * Parameters: buffer      - [OUT]                                            *
 *             buffer_size - [IN] output buffer size                          *
 *             var         - [IN] SNMP variable                               *
 *                                                                            *
 * Return value: SUCCEED - string was quoted successfully                     *
 *               FAIL    - output buffer is too small                         *
 *                                                                            *
 * Comments: When producing the output, Net-SNMP library sometimes quotes     *
 *           string values, sometimes it doesn't quote them. This makes it    *
 *           impossible to correctly parse string values, because there is    *
 *           no way for parser to tell if string value was quoted - quote     *
 *           character in the beginning of the value can either indicate that *
 *           the value was quoted, or be a part of the actual value. To deal  *
 *           with this issue, we can analyze the output that was produced by  *
 *           the library, compare it to raw value and try to guess if the     *
 *           value was quoted. If it wasn't, we can manually quote the value. *
 *           This way, string values will always be quoted and parser should  *
 *           not have issues with parsing them.                               *
 *                                                                            *
 *           When formatting string values, Net-SNMP library checks if        *
 *           display hint is available for specific OID. If yes, then value   *
 *           is not quoted. If hint is not used, then library outputs it      *
 *           either as unoquoted Hex-STRING value, or as quoted STRING value, *
 *           or as quoted empty string without specifying the type.           *
 *           See sprint_realloc_octet_string() for exact implementation.      *
 *                                                                            *
 ******************************************************************************/
static int	snmp_quote_string_value(char *buffer, size_t buffer_size, struct variable_list *var)
{
#define TYPE_STR_HEX_STRING	"Hex-STRING: "
#define TYPE_STR_STRING		"STRING: "
	int	ret;
	char	*buf;
	char	quoted_buf[MAX_STRING_LEN];

	buf = strstr(buffer, " = ");
	if (NULL == buf)
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() ' = ' not found in buffer '%s'", __func__, buffer);
		THIS_SHOULD_NEVER_HAPPEN;
		ret = FAIL;
		goto out;
	}
	buf += 3;

	if (0 == var->val_len && 0 == strcmp(buf, "\"\""))
	{
		ret = SUCCEED;
		goto out;
	}

	if (0 == strncmp(buf, TYPE_STR_HEX_STRING, sizeof(TYPE_STR_HEX_STRING) - 1))
	{
		char		*strval_dyn;
		struct tree	*subtree;
		const char	*hint;

		subtree = get_tree(var->name, var->name_length, get_tree_head());
		hint = (NULL != subtree ? subtree->hint : NULL);

		if (NULL == hint && NULL != (strval_dyn = zbx_sprint_asn_octet_str_dyn(var)))
		{
			char	*str = NULL;
			size_t	str_alloc = 0, str_offset = 0;

			zbx_strncpy_alloc(&str, &str_alloc, &str_offset, buffer, buf - buffer);
			zbx_strcpy_alloc(&str, &str_alloc, &str_offset, TYPE_STR_STRING);
			zbx_strquote_alloc_opt(&str, &str_alloc, &str_offset, strval_dyn, ZBX_STRQUOTE_DEFAULT);

			zbx_strlcpy(buffer, str, buffer_size);

			zbx_free(strval_dyn);
			zbx_free(str);
		}

		ret = SUCCEED;
		goto out;
	}

	if (0 != strncmp(buf, TYPE_STR_STRING, sizeof(TYPE_STR_STRING) - 1))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() expected 'STRING' type in buffer '%s'", __func__, buffer);
		THIS_SHOULD_NEVER_HAPPEN;
		ret = FAIL;
		goto out;
	}
	buf += sizeof(TYPE_STR_STRING) - 1;

	buffer_size -= (size_t)(buf - buffer);

	if ('"' == *buf)
	{
		if (SUCCEED != snmp_native_quote_string_value(quoted_buf, buffer_size, (char *) var->val.string,
				var->val_len))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() quoting value failed, buffer too small", __func__);
			ret = FAIL;
			goto out;
		}

		/* If value produced by Net-SNMP library is the same as "manually quoted string", then we can assume  */
		/* that the library quoted the value and no further actions are needed.                               */
		/* If values are different, quote character is probably part of the value, but we cannot just use     */
		/* quoted_buf as the final result, because value might have been altered to produce the output        */
		/* because of the display hints. We have to quote the value that was produced by Net-SNMP library,    */
		/* not the raw value.                                                                                 */
		if (0 == strcmp(buf, quoted_buf))
		{
			ret = SUCCEED;
			goto out;
		}
	}

	if (SUCCEED != snmp_native_quote_string_value(quoted_buf, buffer_size, buf, strlen(buf)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() quoting value failed, buffer too small", __func__);
		ret = FAIL;
		goto out;
	}

	zbx_strlcpy(buf, quoted_buf, buffer_size);

	ret = SUCCEED;
out:
	return ret;
#undef TYPE_STR_HEX_STRING
#undef TYPE_STR_STRING
}

static int	snmp_bulkwalk_handle_response(int status, struct snmp_pdu *response,
		zbx_bulkwalk_context_t *bulkwalk_context, char **results, size_t *results_alloc,
		size_t *results_offset, const zbx_snmp_sess_t ssp, const zbx_dc_interface_t *interface,
		unsigned char snmp_oid_type, char *error, size_t max_error_len)
{
	struct variable_list	*var;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (STAT_SUCCESS != status || SNMP_ERR_NOERROR != response->errstat)
	{
		ret = zbx_get_snmp_response_error(ssp, interface, status, response, error, max_error_len);
		bulkwalk_context->running = 0;
		goto out;
	}

	if (NULL == response->variables)
	{
		if (ZBX_SNMP_GET == snmp_oid_type)
		{
			zbx_snprintf(error, max_error_len, "No variables");
			ret = NOTSUPPORTED;
		}

		bulkwalk_context->running = 0;

		goto out;
	}

	for (var = response->variables; NULL != var; var = var->next_variable)
	{
		if (var->name_length < bulkwalk_context->p_oid->root_oid_len ||
				0 != memcmp(bulkwalk_context->p_oid->root_oid, var->name,
				bulkwalk_context->p_oid->root_oid_len * sizeof(oid)))
		{
			if (ZBX_SNMP_GET == snmp_oid_type)
			{
				if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
				{
					char	oid_resp[MAX_OID_LEN], oid_req[MAX_OID_LEN];

					snprint_objid(oid_resp, sizeof(oid_req), var->name, var->name_length);
					snprint_objid(oid_req, sizeof(oid_resp), bulkwalk_context->name,
							bulkwalk_context->name_length);

					zabbix_log(LOG_LEVEL_DEBUG, "OID mismatch: GET response OID (%s) doesn't"
							" match  request OID (%s)", oid_resp, oid_req);
				}
			}
			else
			{
				bulkwalk_context->running = 0;
				break;
			}
		}

		if (ZBX_SNMP_GET == snmp_oid_type)
		{
			ret = snmp_get_value_from_var(var, results, results_alloc, results_offset, error,
					max_error_len);
			bulkwalk_context->running = 0;
			break;
		}

		if (SNMP_ENDOFMIBVIEW != var->type && SNMP_NOSUCHOBJECT != var->type &&
				SNMP_NOSUCHINSTANCE != var->type)
		{
			char	buffer[MAX_STRING_LEN];

			bulkwalk_context->vars_num++;

			if (SNMP_MSG_GET != bulkwalk_context->pdu_type)
			{
				if (0 <= snmp_oid_compare(bulkwalk_context->name, bulkwalk_context->name_length,
						var->name, var->name_length))
				{
					bulkwalk_context->running = 0;
					break;
				}
			}
			else
				bulkwalk_context->running = 0;

			snprint_variable(buffer, sizeof(buffer), var->name, var->name_length, var);

			if (ASN_OCTET_STR == var->type)
				snmp_quote_string_value(buffer, sizeof(buffer), var);

			if (NULL != *results)
				zbx_chrcpy_alloc(results, results_alloc, results_offset, '\n');

			zbx_strcpy_alloc(results, results_alloc, results_offset, buffer);

			if (NULL == var->next_variable)
			{
				memcpy(bulkwalk_context->name, var->name, var->name_length * sizeof(oid));
				bulkwalk_context->name_length = var->name_length;
			}
		}
		else
		{
			bulkwalk_context->running = 0;
			break;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s running:%d", __func__, zbx_result_string(ret),
			bulkwalk_context->running);

	return ret;
}

static int	asynch_response(int operation, struct snmp_session *sp, int reqid, struct snmp_pdu *pdu, void *magic)
{
	zbx_bulkwalk_context_t	*bulkwalk_context;
	zbx_snmp_context_t	*snmp_context;
	int			stat, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()",__func__);

	ZBX_UNUSED(sp);

	bulkwalk_context = (zbx_bulkwalk_context_t *)magic;
	snmp_context = (zbx_snmp_context_t *)bulkwalk_context->arg;

	bulkwalk_context->operation = operation;

	if (reqid != bulkwalk_context->reqid && NULL != pdu && SNMP_MSG_REPORT != pdu->command)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "unexpected response request id:%d expected request id:%d command:%d"
				" operation:%d", reqid, bulkwalk_context->reqid, pdu->command, operation);

		zbx_free(bulkwalk_context->error);
		bulkwalk_context->error = zbx_dsprintf(bulkwalk_context->error, "unexpected response");
		return 0;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "operation:%d response id:%d command:%d probe:%d",operation, reqid,
			pdu ? pdu->command : -1, snmp_context->probe);

	bulkwalk_context->waiting = 0;

	if (1 == snmp_context->probe)
	{
		ret = SUCCEED;
		goto out;
	}

	switch (operation)
	{
		case NETSNMP_CALLBACK_OP_RECEIVED_MESSAGE:
			stat = STAT_SUCCESS;
			break;
		case NETSNMP_CALLBACK_OP_TIMED_OUT:
			stat = STAT_TIMEOUT;
			break;
		case NETSNMP_CALLBACK_OP_SEND_FAILED:
		case NETSNMP_CALLBACK_OP_DISCONNECT:
		case NETSNMP_CALLBACK_OP_SEC_ERROR:
			stat = STAT_ERROR;
			break;
		case NETSNMP_CALLBACK_OP_RESEND:
			bulkwalk_context->reqid = reqid;
		case NETSNMP_CALLBACK_OP_CONNECT:
		default:
			goto out;
	}

	if (NULL != pdu)
	{
		char	error[MAX_STRING_LEN];

		if (SUCCEED != (ret = snmp_bulkwalk_handle_response(stat, pdu, bulkwalk_context, &snmp_context->results,
				&snmp_context->results_alloc, &snmp_context->results_offset, snmp_context->ssp,
				&snmp_context->item.interface, snmp_context->snmp_oid_type, error, sizeof(error))))
		{
			bulkwalk_context->error = zbx_strdup(bulkwalk_context->error, error);
		}
	}
	else
	{
		zbx_free(bulkwalk_context->error);
		bulkwalk_context->error = zbx_dsprintf(bulkwalk_context->error, "SNMP error: [%d]", stat);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return 1;
}

static netsnmp_pdu	*usm_probe_pdu_create(void)
{
	netsnmp_pdu	*pdu;

	if (NULL == (pdu = snmp_pdu_create(SNMP_MSG_GET)))
		return NULL;

	pdu->version = SNMP_VERSION_3;
	pdu->securityName = strdup("");
	pdu->securityNameLen = 0;
	pdu->securityLevel = SNMP_SEC_LEVEL_NOAUTH;
	pdu->securityModel = SNMP_SEC_MODEL_USM;

	return pdu;
}

static zbx_bulkwalk_context_t	*snmp_bulkwalk_context_create(zbx_snmp_context_t *snmp_context,
		int pdu_type, zbx_snmp_oid_t *p_oid)
{
	zbx_bulkwalk_context_t	*bulkwalk_context;

	bulkwalk_context = zbx_malloc(NULL, sizeof(zbx_bulkwalk_context_t));

	bulkwalk_context->p_oid = p_oid;
	memcpy(bulkwalk_context->name, p_oid->root_oid, p_oid->root_oid_len * sizeof(oid));
	bulkwalk_context->name_length = p_oid->root_oid_len;
	bulkwalk_context->pdu_type = pdu_type;
	bulkwalk_context->running = 1;
	bulkwalk_context->vars_num = 0;
	bulkwalk_context->arg = snmp_context;
	bulkwalk_context->error = NULL;

	netsnmp_large_fd_set_init(&bulkwalk_context->fdset, FD_SETSIZE);

	return bulkwalk_context;
}

static void	snmp_bulkwalk_context_free(zbx_bulkwalk_context_t *bulkwalk_context)
{
	netsnmp_large_fd_set_cleanup(&bulkwalk_context->fdset);
	zbx_free(bulkwalk_context->error);
	zbx_free(bulkwalk_context);
}

static int	snmp_bulkwalk_add(zbx_snmp_context_t *snmp_context, int *fd, char *error, size_t max_error_len)
{
	struct snmp_pdu			*pdu;
	zbx_bulkwalk_context_t		*bulkwalk_context = snmp_context->bulkwalk_contexts.values[snmp_context->i];
	struct netsnmp_transport_s	*transport;
	int				ret, numfds = 0, block = 0;
	struct timeval			timeout = {0};
	fd_set				fdset;

	if (1 == snmp_context->probe)
	{
		netsnmp_session	*session = snmp_sess_session(snmp_context->ssp);

		session->flags |= SNMP_FLAGS_DONT_PROBE;

		if (NULL == (pdu = usm_probe_pdu_create()))
		{
			zbx_strlcpy(error, "snmp_pdu_create(): cannot create PDU object.", max_error_len);
			ret = CONFIG_ERROR;
			goto out;
		}
	}
	else
	{
		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		{
			char	buffer[MAX_OID_LEN];

			snprint_objid(buffer, sizeof(buffer), bulkwalk_context->name,  bulkwalk_context->name_length);

			zabbix_log(LOG_LEVEL_DEBUG, "In %s() OID: '%s'",__func__, buffer);
		}

		/* create PDU */
		if (NULL == (pdu = snmp_pdu_create(bulkwalk_context->pdu_type)))
		{
			zbx_strlcpy(error, "snmp_pdu_create(): cannot create PDU object.", max_error_len);
			ret = CONFIG_ERROR;
			goto out;
		}

		if (SNMP_MSG_GETBULK == bulkwalk_context->pdu_type)
		{
			pdu->non_repeaters = 0;
			pdu->max_repetitions = snmp_context->snmp_max_repetitions;
		}

		if (NULL == snmp_add_null_var(pdu, bulkwalk_context->name, bulkwalk_context->name_length))
		{
			zbx_strlcpy(error, "snmp_add_null_var(): cannot add null variable.", max_error_len);
			ret = CONFIG_ERROR;
			snmp_free_pdu(pdu);
			goto out;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sending", __func__);

	bulkwalk_context->reqid = -1;
	bulkwalk_context->operation = 0;

	if (0 == (bulkwalk_context->reqid = snmp_sess_async_send(snmp_context->ssp, pdu, asynch_response,
			bulkwalk_context)))
	{
		ret = zbx_get_snmp_response_error(snmp_context->ssp, &snmp_context->item.interface, STAT_ERROR, NULL,
				error, max_error_len);
		snmp_free_pdu(pdu);
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() send completed reqid:%d", __func__, bulkwalk_context->reqid);

	FD_ZERO(&fdset);

	netsnmp_copy_fd_set_to_large_fd_set(&bulkwalk_context->fdset, &fdset);

	if (1 > snmp_sess_select_info2(snmp_context->ssp, &numfds, &bulkwalk_context->fdset, &timeout, &block))
	{
		zbx_strlcpy(error, "snmp_sess_select_info2(): cannot get socket.", max_error_len);
		ret = NETWORK_ERROR;
		snmp_sess_timeout(snmp_context->ssp);
		goto out;
	}

	if (NULL == (transport = snmp_sess_transport(snmp_context->ssp)) || -1 == transport->sock)
	{
		zbx_strlcpy(error, "snmp_sess_transport(): cannot get socket.", max_error_len);
		ret = NETWORK_ERROR;
		snmp_sess_timeout(snmp_context->ssp);
		goto out;
	}

	*fd = transport->sock;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s fd:%d", __func__, zbx_result_string(ret), *fd);

	return ret;
}

static ZBX_THREAD_LOCAL zbx_snmp_format_opts_t	default_opts;

void	zbx_set_snmp_bulkwalk_options(const char *progname)
{
	zbx_snmp_format_opts_t	bulk_opts;

	zbx_init_snmp(progname);

	if (1 == zbx_snmp_init_bulkwalk_done)
		return;

	zbx_snmp_init_bulkwalk_done = 1;

	snmp_bulkwalk_get_options(&default_opts);

	bulk_opts.numeric_oids = 1;
	bulk_opts.numeric_enum = 1;
	bulk_opts.numeric_ts = 1;
	bulk_opts.no_print_units = 1;
	bulk_opts.oid_format = NETSNMP_OID_OUTPUT_NUMERIC;

	snmp_bulkwalk_set_options(&bulk_opts);
}

void	zbx_unset_snmp_bulkwalk_options(void)
{
	if (0 == zbx_snmp_init_bulkwalk_done)
		return;

	zbx_snmp_init_bulkwalk_done = 0;
	snmp_bulkwalk_set_options(&default_opts);
}

static int	snmp_task_process(short event, void *data, int *fd, const char *addr, char *dnserr,
		struct event *timeout_event)
{
	zbx_bulkwalk_context_t	*bulkwalk_context;
	zbx_snmp_context_t	*snmp_context = (zbx_snmp_context_t *)data;
	char			error[MAX_STRING_LEN];
	int			ret, task_ret = ZBX_ASYNC_TASK_STOP;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)snmp_context->arg_action;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %s event:%d fd:%d itemid:" ZBX_FS_UI64, __func__,
			zbx_get_event_string(event), event, *fd, snmp_context->item.itemid);

	bulkwalk_context = snmp_context->bulkwalk_contexts.values[snmp_context->i];

	if (NULL != poller_config && ZBX_PROCESS_STATE_IDLE == poller_config->state)
	{
		zbx_update_selfmon_counter(poller_config->info, ZBX_PROCESS_STATE_BUSY);
		poller_config->state = ZBX_PROCESS_STATE_BUSY;
	}

	if (ZABBIX_ASYNC_STEP_REVERSE_DNS == snmp_context->step)
	{
		if (NULL != addr)
			snmp_context->reverse_dns = zbx_strdup(NULL, addr);

		goto stop;
	}

	if (0 != (event & EV_TIMEOUT))
	{
		if (NULL != dnserr)
		{
			SET_MSG_RESULT(&snmp_context->item.result, zbx_dsprintf(NULL,
					"cannot resolve address [[%s]:%hu]: timed out: %s",
					snmp_context->item.interface.addr, snmp_context->item.interface.port,
					dnserr));
			snmp_context->item.ret = TIMEOUT_ERROR;
			goto stop;
		}

		if (0 < snmp_context->retries--)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot receive response for itemid:" ZBX_FS_UI64
					" from [[%s]:%hu]: timed out, retrying",
					snmp_context->item.itemid, snmp_context->item.interface.addr,
					snmp_context->item.interface.port);

			snmp_sess_timeout(snmp_context->ssp);

			if (NETSNMP_CALLBACK_OP_RESEND == bulkwalk_context->operation)
			{
				/* reset timeout and retry if read is requested after timeout */
				struct timeval	tv = {snmp_context->config_timeout, 0};

				evtimer_add(timeout_event, &tv);

				task_ret = ZBX_ASYNC_TASK_READ;
				goto stop;
			}
		}

		char	buffer[MAX_OID_LEN];

		snprint_objid(buffer, sizeof(buffer), bulkwalk_context->name, bulkwalk_context->name_length);

		if (ZBX_IF_SNMP_VERSION_3 == snmp_context->snmp_version && 0 == snmp_context->probe)
		{
			SET_MSG_RESULT(&snmp_context->item.result, zbx_dsprintf(NULL,
					"Probe successful, cannot retrieve OID: '%s' from [[%s]:%hu]:"
					" timed out", buffer, snmp_context->item.interface.addr,
					snmp_context->item.interface.port));
			snmp_context->item.ret = TIMEOUT_ERROR;
		}
		else
		{
			SET_MSG_RESULT(&snmp_context->item.result, zbx_dsprintf(NULL,
					"cannot retrieve OID: '%s' from [[%s]:%hu]:"
					" timed out", buffer, snmp_context->item.interface.addr,
					snmp_context->item.interface.port));
			snmp_context->item.ret = TIMEOUT_ERROR;
		}

		goto stop;
	}
	else if (0 != event)
	{
		bulkwalk_context->waiting = 1;

		if (0 != snmp_sess_read2(snmp_context->ssp, &bulkwalk_context->fdset))
		{
			char		*tmp_err_str = NULL;

			snmp_context->item.ret = NOTSUPPORTED;

			snmp_sess_error(snmp_context->ssp, NULL, NULL, &tmp_err_str);
			if (NULL != snmp_context->ssp)
			{
				SET_MSG_RESULT(&snmp_context->item.result, zbx_dsprintf(NULL, "cannot read from"
						" session: %s", tmp_err_str));
			}
			else
			{
				SET_MSG_RESULT(&snmp_context->item.result, zbx_dsprintf(NULL, "cannot read from"
						" session"));
			}

			zbx_free(tmp_err_str);
			goto stop;
		}

		/* socket became readable but callback was not invoked, this can mean that response to previous */
		/* request arrived after retry and was ignored by the library, continue waiting for response    */
		if (1 == bulkwalk_context->waiting)
		{
			int		numfds = 0, block = 0;
			struct timeval	timeout = {0};

			zabbix_log(LOG_LEVEL_DEBUG, "cannot process PDU result for itemid:" ZBX_FS_UI64,
					snmp_context->item.itemid);

			if (1 > snmp_sess_select_info2(snmp_context->ssp, &numfds, &bulkwalk_context->fdset, &timeout,
					&block))
			{
				snmp_context->item.ret = NOTSUPPORTED;
				SET_MSG_RESULT(&snmp_context->item.result,
						zbx_strdup(NULL, "snmp_sess_select_info2(): cannot get socket."));
				goto stop;
			}

			task_ret = ZBX_ASYNC_TASK_READ;
			goto stop;
		}

		if (1 == snmp_context->probe)
		{
			netsnmp_session	*session = snmp_sess_session(snmp_context->ssp);

			if (0 != session->engineBoots || 0 != session->engineTime)
			{
				set_enginetime(session->securityEngineID, (u_int)session->securityEngineIDLen,
						session->engineBoots, session->engineTime, TRUE);
			}

			if (FAIL == zbx_snmp_cache_handle_engineid(session, &snmp_context->item))
				goto stop;

			if (SNMPERR_SUCCESS != create_user_from_session(session))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot process probing result for itemid:"
						ZBX_FS_UI64, snmp_context->item.itemid);
			}

			snmp_context->probe = 0;
		}

		if (NULL != bulkwalk_context->error)
		{
			snmp_context->item.ret = NOTSUPPORTED;
			SET_MSG_RESULT(&snmp_context->item.result, bulkwalk_context->error);
			bulkwalk_context->error = NULL;
			goto stop;
		}

		if (0 == bulkwalk_context->running)
		{
			if (0 == bulkwalk_context->vars_num && SNMP_MSG_GETBULK == bulkwalk_context->pdu_type)
			{
				bulkwalk_context->pdu_type = SNMP_MSG_GET;
			}
			else
			{
				snmp_context->i++;

				if (snmp_context->i >= snmp_context->bulkwalk_contexts.values_num)
				{
					if (NULL == snmp_context->results)
						SET_TEXT_RESULT(&snmp_context->item.result, zbx_strdup(NULL, ""));
					else
						SET_TEXT_RESULT(&snmp_context->item.result, snmp_context->results);

					snmp_context->results = NULL;
					snmp_context->item.ret = SUCCEED;

					if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == snmp_context->resolve_reverse_dns)
					{
						task_ret = ZBX_ASYNC_TASK_RESOLVE_REVERSE;
						snmp_context->step = ZABBIX_ASYNC_STEP_REVERSE_DNS;
					}

					goto stop;
				}
			}
		}
	}
	else
	{
		if (NULL == (snmp_context->ssp = zbx_snmp_open_session(snmp_context->snmp_version, addr,
				snmp_context->item.interface.port, snmp_context->snmp_community,
				snmp_context->snmpv3_securityname, snmp_context->snmpv3_contextname,
				snmp_context->snmpv3_securitylevel, snmp_context->snmpv3_authprotocol,
				snmp_context->snmpv3_authpassphrase, snmp_context->snmpv3_privprotocol,
				snmp_context->snmpv3_privpassphrase, error, sizeof(error),
				0, snmp_context->config_source_ip)))
		{
			snmp_context->item.ret = NOTSUPPORTED;
			SET_MSG_RESULT(&snmp_context->item.result, zbx_dsprintf(NULL,
					"zbx_snmp_open_session() failed"));
			goto stop;
		}
	}

	if (SUCCEED != (ret = snmp_bulkwalk_add(snmp_context, fd, error, sizeof(error))))
	{
		snmp_context->item.ret = ret;
		SET_MSG_RESULT(&snmp_context->item.result, zbx_dsprintf(NULL, "Get value failed: %s", error));
	}
	else
		task_ret = ZBX_ASYNC_TASK_READ;
stop:
	if (ZBX_ASYNC_TASK_STOP == task_ret && ZBX_ISSET_MSG(&snmp_context->item.result))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() %s event:%d fd:%d itemid:" ZBX_FS_UI64 " error:%s",
				__func__, zbx_get_event_string(event), event, *fd, snmp_context->item.itemid,
				snmp_context->item.result.msg);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() %s event:%d fd:%d itemid:" ZBX_FS_UI64 " state:%s",
				__func__, zbx_get_event_string(event), event, *fd, snmp_context->item.itemid,
				zbx_task_state_to_str(task_ret));
	}

	return task_ret;
}

zbx_dc_item_context_t	*zbx_async_check_snmp_get_item_context(zbx_snmp_context_t *snmp_context)
{
	return &snmp_context->item;
}

char	*zbx_async_check_snmp_get_reverse_dns(zbx_snmp_context_t *snmp_context)
{
	return snmp_context->reverse_dns;
}

void	*zbx_async_check_snmp_get_arg(zbx_snmp_context_t *snmp_context)
{
	return snmp_context->arg;
}

void	zbx_async_check_snmp_clean(zbx_snmp_context_t *snmp_context)
{
	if (NULL != snmp_context->ssp)
		zbx_snmp_close_session(snmp_context->ssp);

	zbx_free(snmp_context->snmp_community);
	zbx_free(snmp_context->snmpv3_securityname);
	zbx_free(snmp_context->snmpv3_contextname);
	zbx_free(snmp_context->snmpv3_authpassphrase);
	zbx_free(snmp_context->snmpv3_privpassphrase);

	zbx_free(snmp_context->item.key);
	zbx_free(snmp_context->item.key_orig);
	zbx_free(snmp_context->results);
	zbx_free(snmp_context->reverse_dns);
	zbx_free_agent_result(&snmp_context->item.result);

	zbx_vector_bulkwalk_context_clear_ext(&snmp_context->bulkwalk_contexts, snmp_bulkwalk_context_free);
	zbx_vector_bulkwalk_context_destroy(&snmp_context->bulkwalk_contexts);
	zbx_vector_snmp_oid_clear_ext(&snmp_context->param_oids, vector_snmp_oid_free);
	zbx_vector_snmp_oid_destroy(&snmp_context->param_oids);
	zbx_free(snmp_context);
}

int	zbx_async_check_snmp(zbx_dc_item_t *item, AGENT_RESULT *result, zbx_async_task_clear_cb_t clear_cb,
		void *arg, void *arg_action, struct event_base *base, struct evdns_base *dnsbase,
		const char *config_source_ip, zbx_async_resolve_reverse_dns_t resolve_reverse_dns, int retries)
{
	int			ret = SUCCEED, pdu_type, is_oid_plain = 0;
	AGENT_REQUEST		request;
	zbx_snmp_context_t	*snmp_context;
	char			error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s'", __func__, item->key,
			item->host.host, item->interface.addr);

	snmp_context = zbx_malloc(NULL, sizeof(zbx_snmp_context_t));

	snmp_context->resolve_reverse_dns = resolve_reverse_dns;
	snmp_context->step = ZABBIX_ASYNC_STEP_DEFAULT;
	snmp_context->reverse_dns = NULL;

	snmp_context->ssp = NULL;
	snmp_context->item.interface = item->interface;
	snmp_context->item.interface.addr = (item->interface.addr == item->interface.dns_orig ?
			snmp_context->item.interface.dns_orig : snmp_context->item.interface.ip_orig);
	zbx_strlcpy(snmp_context->item.host, item->host.host, sizeof(snmp_context->item.host));
	snmp_context->item.itemid = item->itemid;
	snmp_context->item.hostid = item->host.hostid;
	snmp_context->item.value_type = item->value_type;
	snmp_context->item.flags = item->flags;
	snmp_context->item.key_orig = zbx_strdup(NULL, item->key_orig);

	if (item->key != item->key_orig)
	{
		snmp_context->item.key = item->key;
		item->key = NULL;
	}
	else
		snmp_context->item.key = zbx_strdup(NULL, item->key);

	snmp_context->item.version = item->interface.version;

	zbx_init_agent_result(&snmp_context->item.result);

	snmp_context->config_timeout = item->timeout;

	snmp_context->snmp_max_repetitions = item->snmp_max_repetitions;
	snmp_context->retries = retries;
	snmp_context->arg = arg;
	snmp_context->arg_action = arg_action;
	snmp_context->results = NULL;
	snmp_context->results_alloc = 0;
	snmp_context->results_offset = 0;
	snmp_context->i = 0;

	snmp_context->snmp_version = item->snmp_version;
	snmp_context->snmp_community = item->snmp_community;
	item->snmp_community = NULL;
	snmp_context->snmpv3_securityname = item->snmpv3_securityname;
	item->snmpv3_securityname = NULL;
	snmp_context->snmpv3_contextname = item->snmpv3_contextname;
	item->snmpv3_contextname = NULL;
	snmp_context->snmpv3_securitylevel = item->snmpv3_securitylevel;
	snmp_context->snmpv3_authprotocol = item->snmpv3_authprotocol;
	snmp_context->snmpv3_authpassphrase = item->snmpv3_authpassphrase;
	item->snmpv3_authpassphrase = NULL;
	snmp_context->snmpv3_privprotocol = item->snmpv3_privprotocol;
	snmp_context->snmpv3_privpassphrase = item->snmpv3_privpassphrase;
	item->snmpv3_privpassphrase = NULL;
	snmp_context->config_source_ip = config_source_ip;

	zbx_vector_bulkwalk_context_create(&snmp_context->bulkwalk_contexts);

	zbx_init_agent_request(&request);
	zbx_vector_snmp_oid_create(&snmp_context->param_oids);

	if (0 == strncmp(item->snmp_oid, "walk[", ZBX_CONST_STRLEN("walk[")))
	{
		snmp_context->snmp_oid_type = ZBX_SNMP_WALK;
		pdu_type = ZBX_IF_SNMP_VERSION_1 == item->snmp_version ? SNMP_MSG_GETNEXT : SNMP_MSG_GETBULK;
	}
	else if (0 == strncmp(item->snmp_oid, "get[", ZBX_CONST_STRLEN("get[")))
	{
		snmp_context->snmp_oid_type = ZBX_SNMP_GET;
		pdu_type = SNMP_MSG_GET;
	}
	else if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == resolve_reverse_dns)
	{
		/* OIDs without key are supported in case of network discovery */
		snmp_context->snmp_oid_type = ZBX_SNMP_GET;
		pdu_type = SNMP_MSG_GET;
		is_oid_plain = 1;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid SNMP OID: unsupported parameter."));
		ret = CONFIG_ERROR;
		goto out;
	}

	snmp_context->probe = ZBX_IF_SNMP_VERSION_3 == item->snmp_version ? 1 : 0;

	if (SNMP_MSG_GETBULK == pdu_type && 1 > item->snmp_max_repetitions)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid max repetition count: it should be at least 1."));
		ret = CONFIG_ERROR;
		goto out;
	}

	if (0 == is_oid_plain && SUCCEED != zbx_parse_item_key(item->snmp_oid, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid SNMP OID: cannot parse parameter."));
		ret = CONFIG_ERROR;
		goto out;
	}

	if (0 == request.nparam || (1 == request.nparam && '\0' == *(request.params[0])))
	{
		if (ZBX_SNMP_WALK == snmp_context->snmp_oid_type)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid parameters: at least one OID is expected."));
			ret = CONFIG_ERROR;
			goto out;
		}

		if (SUCCEED != snmp_bulkwalk_parse_param(item->snmp_oid, &snmp_context->param_oids, error,
				sizeof(error)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, error));
			ret = CONFIG_ERROR;
			goto out;
		}
	}
	else if (SUCCEED != snmp_bulkwalk_parse_params(&request, &snmp_context->param_oids, error, sizeof(error)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
		ret = CONFIG_ERROR;
		goto out;
	}

	for (int i = 0; i < snmp_context->param_oids.values_num; i++)
	{
		zbx_bulkwalk_context_t	*bulkwalk_context;

		bulkwalk_context = snmp_bulkwalk_context_create(snmp_context, pdu_type,
				snmp_context->param_oids.values[i]);

		zbx_vector_bulkwalk_context_append(&snmp_context->bulkwalk_contexts, bulkwalk_context);
	}

	zbx_async_poller_add_task(base, dnsbase, snmp_context->item.interface.addr, snmp_context, item->timeout,
			snmp_task_process, clear_cb);

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_async_check_snmp_clean(snmp_context);

	zbx_free_agent_request(&request);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	zbx_snmp_process_dynamic(zbx_snmp_sess_t ssp, const zbx_dc_item_t *items, AGENT_RESULT *results,
		int *errcodes, int num, char *error, size_t max_error_len, int *max_succeed, int *min_fail, int bulk,
		unsigned char poller_type)
{
	int		ret, to_walk[ZBX_MAX_SNMP_ITEMS], to_walk_num = 0, to_verify[ZBX_MAX_SNMP_ITEMS],
			to_verify_num = 0;
	unsigned char	query_and_ignore_type[ZBX_MAX_SNMP_ITEMS];
	char		to_verify_oids[ZBX_MAX_SNMP_ITEMS][ZBX_ITEM_SNMP_OID_LEN_MAX],
			index_oids[ZBX_MAX_SNMP_ITEMS][ZBX_ITEM_SNMP_OID_LEN_MAX],
			index_values[ZBX_MAX_SNMP_ITEMS][ZBX_ITEM_SNMP_OID_LEN_MAX],
			oids_translated[ZBX_MAX_SNMP_ITEMS][ZBX_ITEM_SNMP_OID_LEN_MAX];
	char		*idx = NULL;
	size_t		idx_alloc = 32;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	idx = (char *)zbx_malloc(idx, idx_alloc);

	/* perform initial item validation */

	for (int i = 0; i < num; i++)
	{
		char	method[8];

		if (SUCCEED != errcodes[i])
			continue;

		if (3 != zbx_num_key_param(items[i].snmp_oid))
		{
			SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "OID \"%s\" contains unsupported parameters.",
					items[i].snmp_oid));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		zbx_get_key_param(items[i].snmp_oid, 1, method, sizeof(method));
		zbx_get_key_param(items[i].snmp_oid, 2, index_oids[i], sizeof(index_oids[i]));
		zbx_get_key_param(items[i].snmp_oid, 3, index_values[i], sizeof(index_values[i]));

		if (0 != strcmp("index", method))
		{
			SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "Unsupported method \"%s\" in the OID \"%s\".",
					method, items[i].snmp_oid));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		zbx_snmp_translate(oids_translated[i], index_oids[i], sizeof(oids_translated[i]));

		if (SUCCEED == cache_get_snmp_index(&items[i], oids_translated[i], index_values[i], &idx, &idx_alloc))
		{
			zbx_snprintf(to_verify_oids[i], sizeof(to_verify_oids[i]), "%s.%s", oids_translated[i], idx);

			to_verify[to_verify_num++] = i;
			query_and_ignore_type[i] = 1;
		}
		else
		{
			to_walk[to_walk_num++] = i;
			query_and_ignore_type[i] = 0;
		}
	}

	/* verify that cached indices are still valid */

	if (0 != to_verify_num)
	{
		ret = zbx_snmp_get_values(ssp, items, to_verify_oids, results, errcodes, query_and_ignore_type, num, 0,
				error, max_error_len, max_succeed, min_fail, poller_type);

		if (SUCCEED != ret && NOTSUPPORTED != ret)
			goto exit;

		for (int i = 0; i < to_verify_num; i++)
		{
			int	j = to_verify[i];

			if (SUCCEED != errcodes[j])
				continue;

			if (NULL == ZBX_GET_STR_RESULT(&results[j]) || 0 != strcmp(results[j].str, index_values[j]))
			{
				to_walk[to_walk_num++] = j;
			}
			else
			{
				/* ready to construct the final OID with index */
				size_t	len = strlen(oids_translated[j]);

				char	*pl = strchr(items[j].snmp_oid, '[');

				*pl = '\0';
				zbx_snmp_translate(oids_translated[j], items[j].snmp_oid, sizeof(oids_translated[j]));
				*pl = '[';

				zbx_strlcat(oids_translated[j], to_verify_oids[j] + len, sizeof(oids_translated[j]));
			}

			zbx_free_agent_result(&results[j]);
		}
	}

	/* walk OID trees to build index cache for cache misses */

	if (0 != to_walk_num)
	{
		for (int i = 0; i < to_walk_num; i++)
		{
			int	k, j = to_walk[i];

			/* see whether this OID tree was already walked for another item */

			for (k = 0; k < i; k++)
			{
				if (0 == strcmp(oids_translated[to_walk[k]], oids_translated[j]))
					break;
			}

			if (k != i)
				continue;

			/* walk */

			cache_del_snmp_index_subtree(&items[j], oids_translated[j]);

			int	errcode = zbx_snmp_walk(ssp, &items[j], oids_translated[j], error, max_error_len,
					max_succeed, min_fail, num, bulk, zbx_snmp_walk_cache_cb, (void *)&items[j]);

			if (NETWORK_ERROR == errcode)
			{
				/* consider a network error as relating to all items passed to */
				/* this function, including those we did not just try to walk for */

				ret = NETWORK_ERROR;
				goto exit;
			}

			if (CONFIG_ERROR == errcode || NOTSUPPORTED == errcode)
			{
				/* consider a configuration or "not supported" error as */
				/* relating only to the items we have just tried to walk for */

				for (k = i; k < to_walk_num; k++)
				{
					if (0 == strcmp(oids_translated[to_walk[k]], oids_translated[j]))
					{
						SET_MSG_RESULT(&results[to_walk[k]], zbx_strdup(NULL, error));
						errcodes[to_walk[k]] = errcode;
					}
				}
			}
		}

		for (int i = 0; i < to_walk_num; i++)
		{
			int	j = to_walk[i];

			if (SUCCEED != errcodes[j])
				continue;

			if (SUCCEED == cache_get_snmp_index(&items[j], oids_translated[j], index_values[j], &idx,
						&idx_alloc))
			{
				/* ready to construct the final OID with index */

				char	*pl = strchr(items[j].snmp_oid, '[');

				*pl = '\0';
				zbx_snmp_translate(oids_translated[j], items[j].snmp_oid, sizeof(oids_translated[j]));
				*pl = '[';

				zbx_strlcat(oids_translated[j], ".", sizeof(oids_translated[j]));
				zbx_strlcat(oids_translated[j], idx, sizeof(oids_translated[j]));
			}
			else
			{
				SET_MSG_RESULT(&results[j], zbx_dsprintf(NULL,
						"Cannot find index of \"%s\" in \"%s\".",
						index_values[j], index_oids[j]));
				errcodes[j] = NOTSUPPORTED;
			}
		}
	}

	/* query values based on the indices verified and/or determined above */

	ret = zbx_snmp_get_values(ssp, items, oids_translated, results, errcodes, NULL, num, 0, error, max_error_len,
			max_succeed, min_fail, poller_type);
exit:
	zbx_free(idx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	zbx_snmp_process_standard(struct snmp_session *ss, const zbx_dc_item_t *items, AGENT_RESULT *results,
		int *errcodes, int num, char *error, size_t max_error_len, int *max_succeed, int *min_fail,
		unsigned char poller_type)
{
	int	ret;
	char	oids_translated[ZBX_MAX_SNMP_ITEMS][ZBX_ITEM_SNMP_OID_LEN_MAX];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (0 != zbx_num_key_param(items[i].snmp_oid))
		{
			SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "OID \"%s\" contains unsupported parameters.",
					items[i].snmp_oid));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		zbx_snmp_translate(oids_translated[i], items[i].snmp_oid, sizeof(oids_translated[i]));
	}

	ret = zbx_snmp_get_values(ss, items, oids_translated, results, errcodes, NULL, num, 0, error, max_error_len,
			max_succeed, min_fail, poller_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/*******************************************************************************************
 *                                                                                         *
 * Comment: Actually this could be called by discoverer, without poller being initialized, *
 *          so cannot call poller_get_progname(), need progname to be passed directly.     *
 *                                                                                         *
 *******************************************************************************************/
static void	zbx_init_snmp(const char *progname)
{
	sigset_t	mask, orig_mask;

	if (1 == zbx_snmp_init_done)
		return;

	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGQUIT);
	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);

	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DISABLE_PERSISTENT_LOAD, 1);
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DISABLE_PERSISTENT_SAVE, 1);

	init_snmp(progname);
	zbx_snmp_init_done = 1;

	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);
}

/*******************************************************************************************
 *                                                                                         *
 * Comment: Actually this could be called by discoverer, without poller being initialized, *
 *          so cannot call poller_get_progname(), need progname to be passed directly.     *
 *                                                                                         *
 *******************************************************************************************/
static void	zbx_shutdown_snmp(const char *progname)
{
	sigset_t	mask, orig_mask;

	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGQUIT);
	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);

	snmp_shutdown(progname);

	zbx_snmp_init_done = 0;

	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes snmp and loads mibs files for multithread environment *
 *                                                                            *
 ******************************************************************************/
void	zbx_init_library_mt_snmp(const char *progname)
{
	zbx_init_snmp(progname);

	if (0 == snmp_rwlock_init_done)
	{
		int	err;

		if (0 != (err = pthread_rwlock_init(&snmp_exec_rwlock, NULL)))
			zabbix_log(LOG_LEVEL_WARNING, "cannot initialize snmp execute mutex: %s", zbx_strerror(err));
		else
			snmp_rwlock_init_done = 1;
	}
}

void	zbx_shutdown_library_mt_snmp(const char *progname)
{
	if (1 == snmp_rwlock_init_done)
	{
		int	err;

		pthread_rwlock_wrlock(&snmp_exec_rwlock);

		if (0 != (err = pthread_rwlock_destroy(&snmp_exec_rwlock)))
			zabbix_log(LOG_LEVEL_WARNING, "cannot destroy snmp execute mutex: %s", zbx_strerror(err));
		else
			snmp_rwlock_init_done = 0;
	}
	zbx_shutdown_snmp(progname);
}

static void	process_snmp_result(void *data)
{
	zbx_snmp_context_t	*snmp_context = (zbx_snmp_context_t *)data;
	zbx_snmp_result_t	*snmp_result = (zbx_snmp_result_t *)snmp_context->arg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s'", __func__, snmp_context->item.key,
			snmp_context->item.host, snmp_context->item.interface.addr);
	*snmp_result->result = snmp_context->item.result;
	zbx_init_agent_result(&snmp_context->item.result);
	snmp_result->errcode = snmp_context->item.ret;
	event_base_loopbreak(snmp_result->base);
	snmp_result->finished = 1;

	zbx_async_check_snmp_clean(snmp_context);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	get_values_snmp(zbx_dc_item_t *items, AGENT_RESULT *results, int *errcodes, int num,
		unsigned char poller_type, const char *config_source_ip, const int config_timeout,
		const char *progname)
{
	zbx_snmp_sess_t		ssp;
	char			error[MAX_STRING_LEN];
	int			i, j, err = SUCCEED, max_succeed = 0, min_fail = ZBX_MAX_SNMP_ITEMS + 1,
				bulk = SNMP_BULK_ENABLED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' addr:'%s' num:%d",
			__func__, items[0].host.host, items[0].interface.addr, num);

	zbx_init_snmp(progname);	/* avoid high CPU usage by only initializing SNMP once used */

	for (j = 0; j < num; j++)	/* locate first supported item to use as a reference */
	{
		if (SUCCEED == errcodes[j])
			break;
	}

	if (j == num)	/* all items already NOTSUPPORTED (with invalid key, port or SNMP parameters) */
		goto out;

	SNMP_MT_EXECLOCK;

	if (0 == strncmp(items[j].snmp_oid, "walk[", ZBX_CONST_STRLEN("walk[")) ||
			(0 == strncmp(items[j].snmp_oid, "get[", ZBX_CONST_STRLEN("get["))))
	{
		struct evdns_base	*dnsbase;
		zbx_snmp_result_t	snmp_result = {.result = &results[j]};

		if (NULL == (snmp_result.base = event_base_new()))
		{
			SET_MSG_RESULT(&results[j], zbx_strdup(NULL, "cannot initialize event base"));
			errcodes[j] = CONFIG_ERROR;
			goto out;
		}

		if (NULL == (dnsbase = evdns_base_new(snmp_result.base, EVDNS_BASE_INITIALIZE_NAMESERVERS)))
		{
			int	ret;

			zabbix_log(LOG_LEVEL_ERR, "cannot initialize asynchronous DNS library with resolv.conf");

			if (NULL == (dnsbase = evdns_base_new(snmp_result.base, 0)))
			{
				event_base_free(snmp_result.base);
				SET_MSG_RESULT(&results[j], zbx_strdup(NULL,
						"cannot initialize asynchronous DNS library"));
				errcodes[j] = CONFIG_ERROR;
				goto out;
			}

			if (0 != (ret = evdns_base_resolv_conf_parse(dnsbase, DNS_OPTIONS_ALL, ZBX_RES_CONF_FILE)))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot parse resolv.conf result: %s",
						zbx_resolv_conf_errstr(ret));
			}
		}

		zbx_set_snmp_bulkwalk_options(progname);

		if (SUCCEED == (errcodes[j] = zbx_async_check_snmp(&items[j], &results[j], process_snmp_result,
				&snmp_result, NULL, snmp_result.base, dnsbase, config_source_ip,
				ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_NO, ZBX_SNMP_DEFAULT_NUMBER_OF_RETRIES)))
		{
			if (1 == snmp_result.finished || -1 != event_base_dispatch(snmp_result.base))
			{
				errcodes[j] = snmp_result.errcode;
			}
			else
			{
				SET_MSG_RESULT(&results[j], zbx_strdup(NULL, "cannot process event base"));
				errcodes[j] = CONFIG_ERROR;
			}
		}

		evdns_base_free(dnsbase, 0);
		event_base_free(snmp_result.base);

		zbx_unset_snmp_bulkwalk_options();
		goto out;
	}
	else if (0 != (ZBX_FLAG_DISCOVERY_RULE & items[j].flags) || 0 == strncmp(items[j].snmp_oid, "discovery[", 10))
	{
		int		max_vars;
		zbx_dc_item_t	*item = &items[j];
		char		ip_addr[ZBX_INTERFACE_IP_LEN_MAX];

		zbx_getip_by_host(item->interface.addr, ip_addr, sizeof(ip_addr));

		if (NULL == (ssp = zbx_snmp_open_session(item->snmp_version, ip_addr, item->interface.port,
			item->snmp_community, item->snmpv3_securityname, item->snmpv3_contextname,
			item->snmpv3_securitylevel, item->snmpv3_authprotocol, item->snmpv3_authpassphrase,
			item->snmpv3_privprotocol, item->snmpv3_privpassphrase, error, sizeof(error),
			config_timeout, config_source_ip)))
		{
			err = NETWORK_ERROR;
			goto exit;
		}

		max_vars = zbx_dc_config_get_suggested_snmp_vars(items[j].interface.interfaceid, &bulk);

		err = zbx_snmp_process_discovery(ssp, &items[j], &results[j], &errcodes[j], error, sizeof(error),
				&max_succeed, &min_fail, max_vars, bulk);

		zbx_snmp_close_session(ssp);
	}
	else if (NULL != strchr(items[j].snmp_oid, '['))
	{
		zbx_dc_item_t	*item = &items[j];
		char		ip_addr[ZBX_INTERFACE_IP_LEN_MAX];

		zbx_getip_by_host(item->interface.addr, ip_addr, sizeof(ip_addr));

		if (NULL == (ssp = zbx_snmp_open_session(item->snmp_version, ip_addr, item->interface.port,
			item->snmp_community, item->snmpv3_securityname, item->snmpv3_contextname,
			item->snmpv3_securitylevel, item->snmpv3_authprotocol, item->snmpv3_authpassphrase,
			item->snmpv3_privprotocol, item->snmpv3_privpassphrase, error, sizeof(error),
			config_timeout, config_source_ip)))
		{
			err = NETWORK_ERROR;
			goto exit;
		}

		(void)zbx_dc_config_get_suggested_snmp_vars(items[j].interface.interfaceid, &bulk);

		err = zbx_snmp_process_dynamic(ssp, items + j, results + j, errcodes + j, num - j, error, sizeof(error),
				&max_succeed, &min_fail, bulk, poller_type);

		zbx_snmp_close_session(ssp);
	}
	else
	{
		zbx_dc_item_t	*item = &items[j];
		char		ip_addr[ZBX_INTERFACE_IP_LEN_MAX];

		zbx_getip_by_host(item->interface.addr, ip_addr, sizeof(ip_addr));

		if (NULL == (ssp = zbx_snmp_open_session(item->snmp_version, ip_addr, item->interface.port,
			item->snmp_community, item->snmpv3_securityname, item->snmpv3_contextname,
			item->snmpv3_securitylevel, item->snmpv3_authprotocol, item->snmpv3_authpassphrase,
			item->snmpv3_privprotocol, item->snmpv3_privpassphrase, error, sizeof(error),
			config_timeout, config_source_ip)))
		{
			err = NETWORK_ERROR;
			goto exit;
		}

		err = zbx_snmp_process_standard(ssp, items + j, results + j, errcodes + j, num - j, error,
				sizeof(error), &max_succeed, &min_fail, poller_type);

		zbx_snmp_close_session(ssp);
	}
exit:
	if (SUCCEED != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "getting SNMP values failed: %s", error);

		for (i = j; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
			errcodes[i] = err;
		}
	}
	else if (SNMP_BULK_ENABLED == bulk && (0 != max_succeed || ZBX_MAX_SNMP_ITEMS + 1 != min_fail))
	{
		zbx_dc_config_update_interface_snmp_stats(items[j].interface.interfaceid, max_succeed, min_fail);
	}
out:
	SNMP_MT_UNLOCK;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clears snmpv3 user authentication cache                           *
 *                                                                            *
 * Parameters: process_type - [IN]                                            *
 *             process_num  - [IN] unique id of process                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_clear_cache_snmp(unsigned char process_type, int process_num)
{
	if (FAIL != process_num)
	{
		zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the snmp cache on [%s #%d]",
				get_process_type_string(process_type), process_num);
	}

	if (0 == zbx_snmp_init_done)
		return;

	SNMP_MT_INITLOCK;

	shutdown_usm();

	if (ZBX_PROCESS_TYPE_SNMP_POLLER == process_type)
		zbx_clear_snmp_engineid_cache();

	SNMP_MT_UNLOCK;
}

#endif	/* HAVE_NETSNMP */
