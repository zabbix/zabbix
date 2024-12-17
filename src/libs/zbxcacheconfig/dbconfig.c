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

#include "dbconfig.h"

#include "proxy_group.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxtasks.h"
#include "zbxshmem.h"
#include "zbxregexp.h"
#include "zbxcfg.h"
#include "zbxcrypto.h"
#include "zbxtypes.h"
#include "zbxvault.h"
#include "zbxdbhigh.h"
#include "dbsync.h"
#include "zbxtrends.h"
#include "zbxserialize.h"
#include "user_macro.h"
#include "zbxavailability.h"
#include "zbx_availability_constants.h"
#include "zbxexpr.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxstr.h"
#include "zbxip.h"
#include "zbxsysinfo.h"
#include "zbx_host_constants.h"
#include "zbx_trigger_constants.h"
#include "zbx_item_constants.h"
#include "zbxpreprocbase.h"
#include "zbxcachehistory.h"
#include "zbxconnector.h"
#include "zbx_discoverer_constants.h"
#include "zbxdbschema.h"
#include "zbxeval.h"
#include "zbxipcservice.h"
#include "zbxjson.h"
#include "zbxkvs.h"
#include "zbxcachevalue.h"
#include "zbxcomms.h"
#include "zbxdb.h"
#include "zbxmutexs.h"
#include "zbxautoreg.h"
#include "zbxpgservice.h"
#include "zbxinterface.h"
#include "zbxhistory.h"
#include "zbx_expression_constants.h"

#define	ZBX_VECTOR_ARRAY_RESERVE	3

ZBX_PTR_VECTOR_IMPL(inventory_value_ptr, zbx_inventory_value_t *)
ZBX_PTR_VECTOR_IMPL(hc_item_ptr, zbx_hc_item_t *)
ZBX_PTR_VECTOR_IMPL(dc_corr_condition_ptr, zbx_dc_corr_condition_t *)
ZBX_PTR_VECTOR_IMPL(dc_corr_operation_ptr, zbx_dc_corr_operation_t *)
ZBX_PTR_VECTOR_IMPL(corr_condition_ptr, zbx_corr_condition_t *)
ZBX_PTR_VECTOR_IMPL(corr_operation_ptr, zbx_corr_operation_t *)
ZBX_PTR_VECTOR_IMPL(correlation_ptr, zbx_correlation_t *)
ZBX_PTR_VECTOR_IMPL(trigger_dep_ptr, zbx_trigger_dep_t *)
ZBX_PTR_VECTOR_IMPL(trigger_timer_ptr, zbx_trigger_timer_t *)

ZBX_VECTOR_IMPL(dc_item_tag, zbx_dc_item_tag_t)

typedef struct
{
	zbx_uint64_t	itemtagid;
	zbx_uint64_t	itemid;
}
zbx_dc_item_tag_link;

typedef struct
{
	zbx_hashset_t	item_tag_links;
}
zbx_dc_config_private_t;

void	zbx_corr_operation_free(zbx_corr_operation_t *corr_operation)
{
	zbx_free(corr_operation);
}

int	zbx_dc_corr_condition_compare_func(const void *d1, const void *d2)
{
	const zbx_dc_corr_condition_t	*corr_cond_1 = *(zbx_dc_corr_condition_t **)d1;
	const zbx_dc_corr_condition_t	*corr_cond_2 = *(zbx_dc_corr_condition_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(corr_cond_1->corr_conditionid, corr_cond_2->corr_conditionid);

	return 0;
}

int	zbx_dc_corr_operation_compare_func(const void *d1, const void *d2)
{
	const zbx_dc_corr_operation_t	*corr_oper_1 = *(zbx_dc_corr_operation_t **)d1;
	const zbx_dc_corr_operation_t	*corr_oper_2 = *(zbx_dc_corr_operation_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(corr_oper_1->corr_operationid, corr_oper_2->corr_operationid);

	return 0;
}

int	zbx_correlation_compare_func(const void *d1, const void *d2)
{
	const zbx_correlation_t	*corr_1 = *(zbx_correlation_t **)d1;
	const zbx_correlation_t	*corr_2 = *(zbx_correlation_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(corr_1->correlationid, corr_2->correlationid);

	return 0;
}

int	zbx_trigger_dep_compare_func(const void *d1, const void *d2)
{
	const zbx_trigger_dep_t	*trigger_dep_1 = *(zbx_trigger_dep_t **)d1;
	const zbx_trigger_dep_t	*trigger_dep_2 = *(zbx_trigger_dep_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(trigger_dep_1->triggerid, trigger_dep_2->triggerid);

	return 0;
}

/* item reference hashset support */
static zbx_hash_t	dc_item_ref_hash(const void *data)
{
	const ZBX_DC_ITEM_REF	*ref = (const ZBX_DC_ITEM_REF *)data;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&ref->item->itemid);
}

static int	dc_item_ref_compare(const void *d1, const void *d2)
{
	const ZBX_DC_ITEM_REF	*ref1 = (const ZBX_DC_ITEM_REF *)d1;
	const ZBX_DC_ITEM_REF	*ref2 = (const ZBX_DC_ITEM_REF *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ref1->item->itemid, ref2->item->itemid);
	return 0;
}

int	sync_in_progress = 0;

#define START_SYNC	do { WRLOCK_CACHE_CONFIG_HISTORY; WRLOCK_CACHE; sync_in_progress = 1; } while(0)
#define FINISH_SYNC	do { sync_in_progress = 0; UNLOCK_CACHE; UNLOCK_CACHE_CONFIG_HISTORY; } while(0)

#define ZBX_SNMP_OID_TYPE_NORMAL	0
#define ZBX_SNMP_OID_TYPE_DYNAMIC	1
#define ZBX_SNMP_OID_TYPE_MACRO		2
#define ZBX_SNMP_OID_TYPE_WALK		3
#define ZBX_SNMP_OID_TYPE_GET		4

/* trigger is functional unless its expression contains disabled or not monitored items */
#define TRIGGER_FUNCTIONAL_TRUE		0
#define TRIGGER_FUNCTIONAL_FALSE	1

/* trigger contains time functions and is also scheduled by timer queue */
#define ZBX_TRIGGER_TIMER_UNKNOWN	0
#define ZBX_TRIGGER_TIMER_QUEUE		1

/* item priority in poller queue */
#define ZBX_QUEUE_PRIORITY_HIGH		0
#define ZBX_QUEUE_PRIORITY_NORMAL	1
#define ZBX_QUEUE_PRIORITY_LOW		2

#define ZBX_DEFAULT_ITEM_UPDATE_INTERVAL	60

#define ZBX_TRIGGER_POLL_INTERVAL		(SEC_PER_MIN * 10)

#define ZBX_STATUS_LIFETIME		SEC_PER_MIN

/* shorthand macro for calling zbx_in_maintenance_without_data_collection() */
#define DCin_maintenance_without_data_collection(dc_host, dc_item)			\
		zbx_in_maintenance_without_data_collection(dc_host->maintenance_status,	\
				dc_host->maintenance_type, dc_item->type)

ZBX_PTR_VECTOR_IMPL(cached_proxy_ptr, zbx_cached_proxy_t *)
ZBX_PTR_VECTOR_IMPL(dc_httptest_ptr, zbx_dc_httptest_t *)
ZBX_PTR_VECTOR_IMPL(dc_host_ptr, ZBX_DC_HOST *)
ZBX_PTR_VECTOR_IMPL(dc_item_ptr, ZBX_DC_ITEM *)
ZBX_VECTOR_IMPL(host_rev, zbx_host_rev_t)
ZBX_PTR_VECTOR_IMPL(dc_connector_tag, zbx_dc_connector_tag_t *)
ZBX_PTR_VECTOR_IMPL(dc_dcheck_ptr, zbx_dc_dcheck_t *)
ZBX_PTR_VECTOR_IMPL(dc_drule_ptr, zbx_dc_drule_t *)
ZBX_PTR_VECTOR_IMPL(item_tag, zbx_item_tag_t *)
ZBX_PTR_VECTOR_IMPL(dc_item, zbx_dc_item_t *)
ZBX_PTR_VECTOR_IMPL(dc_trigger, zbx_dc_trigger_t *)
ZBX_VECTOR_IMPL(host_key, zbx_host_key_t)
ZBX_PTR_VECTOR_IMPL(proxy_counter_ptr, zbx_proxy_counter_t *)

void	zbx_proxy_counter_ptr_free(zbx_proxy_counter_t *proxy_counter)
{
	zbx_free(proxy_counter);
}

static zbx_get_program_type_f	get_program_type_cb = NULL;
static zbx_get_config_forks_f	get_config_forks_cb = NULL;

zbx_dc_config_t		*config = NULL;
zbx_dc_config_private_t	config_private;

zbx_dc_config_t	*get_dc_config(void)
{
	return config;
}

void	set_dc_config(zbx_dc_config_t *in)
{
	config = in;
}

zbx_rwlock_t		config_lock = ZBX_RWLOCK_NULL;

void	rdlock_cache(void)
{
	if (0 == sync_in_progress)
		zbx_rwlock_rdlock(config_lock);
}

void	wrlock_cache(void)
{
	if (0 == sync_in_progress)
		zbx_rwlock_wrlock(config_lock);
}

void	unlock_cache(void)
{
	if (0 == sync_in_progress)
		zbx_rwlock_unlock(config_lock);
}

zbx_rwlock_t		config_history_lock = ZBX_RWLOCK_NULL;

void	rdlock_cache_config_history(void)
{
	zbx_rwlock_rdlock(config_history_lock);
}

void	wrlock_cache_config_history(void)
{
	zbx_rwlock_wrlock(config_history_lock);
}

void	unlock_cache_config_history(void)
{
	zbx_rwlock_unlock(config_history_lock);
}

static zbx_shmem_info_t	*config_mem;

ZBX_SHMEM_FUNC_IMPL(__config, config_mem)

void	dbconfig_shmem_free_func(void *ptr)
{
	__config_shmem_free_func(ptr);
}

void	*dbconfig_shmem_realloc_func(void *old, size_t size)
{
	return __config_shmem_realloc_func(old, size);
}

void	*dbconfig_shmem_malloc_func(void *old, size_t size)
{
	return __config_shmem_malloc_func(old, size);
}

zbx_uint64_t	dbconfig_used_size(void)
{
	if (NULL != config_mem)
		return config_mem->used_size;
	else
		return 0;
}

static void	dc_maintenance_precache_nested_groups(void);
static void	dc_item_reset_triggers(ZBX_DC_ITEM *item, ZBX_DC_TRIGGER *trigger_exclude);

static void	dc_reschedule_items(const zbx_hashset_t *activated_hosts);
static void	dc_reschedule_httptests(zbx_hashset_t *activated_hosts);

static int	dc_host_update_revision(ZBX_DC_HOST *host, zbx_uint64_t revision);
static int	dc_item_update_revision(ZBX_DC_ITEM *item, zbx_uint64_t revision);

typedef struct
{
	zbx_uint64_t	id;
	uint64_t	items_active_normal;
	uint64_t	items_active_notsupported;
}
zbx_dc_status_diff_host_t;

ZBX_VECTOR_DECL(status_diff_host, zbx_dc_status_diff_host_t)
ZBX_VECTOR_IMPL(status_diff_host, zbx_dc_status_diff_host_t)

typedef struct
{
	int				reset;
	zbx_uint64_t			hosts_monitored;
	zbx_uint64_t			hosts_not_monitored;
	zbx_uint64_t			items_active_normal;
	zbx_uint64_t			items_active_notsupported;
	zbx_uint64_t			items_disabled;
	zbx_uint64_t			triggers_enabled_ok;
	zbx_uint64_t			triggers_enabled_problem;
	zbx_uint64_t			triggers_disabled;
	double				required_performance;

	zbx_vector_status_diff_host_t	hosts;
	zbx_hashset_t			proxies;
}
zbx_dc_status_diff_t;

/******************************************************************************
 *                                                                            *
 * Purpose: copies string into configuration cache shared memory              *
 *                                                                            *
 ******************************************************************************/
static char	*dc_strdup(const char *source)
{
	char	*dst;
	size_t	len;

	len = strlen(source) + 1;
	dst = (char *)__config_shmem_malloc_func(NULL, len);
	memcpy(dst, source, len);
	return dst;
}

/* user macro cache */

struct zbx_dc_um_handle_t
{
	zbx_dc_um_handle_t	*prev;
	zbx_um_cache_t		**cache;
	unsigned char		macro_env;
};

static zbx_dc_um_handle_t	*dc_um_handle = NULL;

/******************************************************************************
 *                                                                            *
 * Parameters: type - [IN] item type [ITEM_TYPE_* flag]                       *
 *             key  - [IN] item key                                           *
 *                                                                            *
 * Return value: SUCCEED when an item should be processed by server           *
 *               FAIL otherwise                                               *
 *                                                                            *
 * Comments: list of the items, always processed by server                    *
 * ,------------------+-----------------------------------------------------, *
 * | type             | key                                                 | *
 * +------------------+-----------------------------------------------------+ *
 * | Zabbix internal  | zabbix[host,,items]                                 | *
 * | Zabbix internal  | zabbix[host,,items_unsupported]                     | *
 * | Zabbix internal  | zabbix[host,discovery,interfaces]                   | *
 * | Zabbix internal  | zabbix[host,,maintenance]                           | *
 * | Zabbix internal  | zabbix[proxy,discovery]                             | *
 * | Zabbix internal  | zabbix[proxy,<proxyname>,lastaccess]                | *
 * | Zabbix internal  | zabbix[proxy,<proxyname>,delay]                     | *
 * | Zabbix internal  | zabbix[proxy group,discovery]                       | *
 * | Zabbix internal  | zabbix[proxy group,<groupname>,state]               | *
 * | Zabbix internal  | zabbix[proxy group,<groupname>,available]           | *
 * | Zabbix internal  | zabbix[proxy group,<groupname>,pavailable]          | *
 * | Zabbix internal  | zabbix[proxy group,<groupname>,proxies]             | *
 * | Zabbix aggregate | *                                                   | *
 * | Calculated       | *                                                   | *
 * '------------------+-----------------------------------------------------' *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_item_processed_by_server(unsigned char type, const char *key)
{
	int	ret = FAIL;

	switch (type)
	{
		case ITEM_TYPE_CALCULATED:
			ret = SUCCEED;
			break;

		case ITEM_TYPE_INTERNAL:
			if (0 == strncmp(key, "zabbix[", 7))
			{
				AGENT_REQUEST	request;
				char		*arg1, *arg2, *arg3;

				zbx_init_agent_request(&request);

				if (SUCCEED != zbx_parse_item_key(key, &request) || 2 > request.nparam ||
						3 < request.nparam)
				{
					goto clean;
				}

				arg1 = get_rparam(&request, 0);
				arg2 = get_rparam(&request, 1);

				if (0 == strcmp(arg1, "vps"))
				{
					ret = SUCCEED;
					goto clean;
				}

				if (2 == request.nparam)
				{
					if ((0 == strcmp(arg1, "proxy") && 0 == strcmp(arg2, "discovery")) ||
							0 == strcmp(arg1, "proxy group"))
					{
						ret = SUCCEED;
					}

					goto clean;
				}

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
				else if (0 == strcmp(arg1, "proxy group"))
				{
					ret = SUCCEED;
				}
				else if (0 == strcmp(arg1, "proxy") && (0 == strcmp(arg3, "lastaccess") ||
						0 == strcmp(arg3, "delay")))
				{
					ret = SUCCEED;
				}

clean:
				zbx_free_agent_request(&request);
			}
			break;
	}

	return ret;
}

static int	cmp_key_id(const char *key_1, const char *key_2)
{
	const char	*p, *q;

	for (p = key_1, q = key_2; *p == *q && '\0' != *q && '[' != *q; p++, q++)
		;

	return ('\0' == *p || '[' == *p) && ('\0' == *q || '[' == *q) ? SUCCEED : FAIL;
}

static unsigned char	poller_by_item(unsigned char type, const char *key, unsigned char snmp_oid_type)
{
	switch (type)
	{
		case ITEM_TYPE_SIMPLE:
			if (SUCCEED == cmp_key_id(key, ZBX_SERVER_ICMPPING_KEY) ||
					SUCCEED == cmp_key_id(key, ZBX_SERVER_ICMPPINGSEC_KEY) ||
					SUCCEED == cmp_key_id(key, ZBX_SERVER_ICMPPINGLOSS_KEY))
			{
				if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_PINGER))
					break;

				return ZBX_POLLER_TYPE_PINGER;
			}
			ZBX_FALLTHROUGH;
		case ITEM_TYPE_EXTERNAL:
		case ITEM_TYPE_SSH:
		case ITEM_TYPE_TELNET:
		case ITEM_TYPE_SCRIPT:
			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_POLLER))
				break;

			return ZBX_POLLER_TYPE_NORMAL;
		case ITEM_TYPE_BROWSER:
			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_BROWSERPOLLER))
				break;

			return ZBX_POLLER_TYPE_BROWSER;
		case ITEM_TYPE_INTERNAL:
			return ZBX_POLLER_TYPE_INTERNAL;
		case ITEM_TYPE_DB_MONITOR:
			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_ODBCPOLLER))
				break;

			return ZBX_POLLER_TYPE_ODBC;
		case ITEM_TYPE_CALCULATED:
			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_HISTORYPOLLER))
				break;

			return ZBX_POLLER_TYPE_HISTORY;
		case ITEM_TYPE_IPMI:
			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_IPMIPOLLER))
				break;

			return ZBX_POLLER_TYPE_IPMI;
		case ITEM_TYPE_JMX:
			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_JAVAPOLLER))
				break;

			return ZBX_POLLER_TYPE_JAVA;
		case ITEM_TYPE_HTTPAGENT:
			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_HTTPAGENT_POLLER))
				break;

			return ZBX_POLLER_TYPE_HTTPAGENT;
		case ITEM_TYPE_ZABBIX:
			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_AGENT_POLLER))
				break;

			return ZBX_POLLER_TYPE_AGENT;
		case ITEM_TYPE_SNMP:
			if (ZBX_SNMP_OID_TYPE_WALK == snmp_oid_type || ZBX_SNMP_OID_TYPE_GET == snmp_oid_type)
			{
				if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_SNMP_POLLER))
					break;

				return ZBX_POLLER_TYPE_SNMP;
			}

			if (0 == get_config_forks_cb(ZBX_PROCESS_TYPE_POLLER))
				break;

			return ZBX_POLLER_TYPE_NORMAL;
	}

	return ZBX_NO_POLLER;
}

/******************************************************************************
 *                                                                            *
 * Purpose: determine whether the given item type is counted in item queue    *
 *                                                                            *
 * Return value: SUCCEED if item is counted in the queue, FAIL otherwise      *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_counted_in_item_queue(unsigned char type, const char *key)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX_ACTIVE:
			if (0 == strncmp(key, "log[", 4) ||
					0 == strncmp(key, "logrt[", 6) ||
					0 == strncmp(key, "eventlog[", 9) ||
					0 == strncmp(key, "mqtt.get[", ZBX_CONST_STRLEN("mqtt.get[")))
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
static zbx_uint64_t	get_item_nextcheck_seed(ZBX_DC_ITEM *item, zbx_uint64_t interfaceid, unsigned char type,
		const char *key)
{
	if (ITEM_TYPE_JMX == type)
		return interfaceid;

	if (ITEM_TYPE_SNMP == type)
	{
		ZBX_DC_SNMPINTERFACE	*snmp;

		if (ZBX_SNMP_OID_TYPE_WALK == item->itemtype.snmpitem->snmp_oid_type ||
				ZBX_SNMP_OID_TYPE_GET == item->itemtype.snmpitem->snmp_oid_type)
		{
			return item->itemid;
		}

		if (NULL == (snmp = (ZBX_DC_SNMPINTERFACE *)zbx_hashset_search(&config->interfaces_snmp, &interfaceid))
				|| SNMP_BULK_ENABLED != snmp->bulk)
		{
			return item->itemid;
		}

		return interfaceid;
	}

	if (ITEM_TYPE_SIMPLE == type)
	{
		if (SUCCEED == cmp_key_id(key, ZBX_SERVER_ICMPPING_KEY) ||
				SUCCEED == cmp_key_id(key, ZBX_SERVER_ICMPPINGSEC_KEY) ||
				SUCCEED == cmp_key_id(key, ZBX_SERVER_ICMPPINGLOSS_KEY))
		{
			return interfaceid;
		}
	}

	return item->itemid;
}

static int	DCget_disable_until(const ZBX_DC_ITEM *item, const ZBX_DC_INTERFACE *interface)
{
	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
		case ITEM_TYPE_SNMP:
		case ITEM_TYPE_IPMI:
		case ITEM_TYPE_JMX:
			return (NULL == interface) ? 0 : interface->disable_until;
		default:
			return 0;
	}
}
/******************************************************************************
 *                                                                            *
 * Purpose: expand user and function macros in string returning new string    *
 *          with resolved macros                                              *
 *                                                                            *
 ******************************************************************************/
char	*dc_expand_user_and_func_macros_dyn(const char *text, const zbx_uint64_t *hostids, int hostids_num, int env)
{
	zbx_token_t	token;
	int		pos = 0, last_pos = 0;
	char		*str = NULL;
	size_t		str_alloc = 0, str_offset = 0;

	if ('\0' == *text)
		return zbx_strdup(NULL, text);

	for (; SUCCEED == zbx_token_find(text, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		const char	*value = NULL;
		char		*out = NULL;
		zbx_token_t	inner_token;

		if (ZBX_TOKEN_USER_MACRO != token.type && ZBX_TOKEN_USER_FUNC_MACRO != token.type)
			continue;

		zbx_strncpy_alloc(&str, &str_alloc, &str_offset, text + last_pos, token.loc.l - (size_t)last_pos);

		switch (token.type)
		{
			case ZBX_TOKEN_USER_FUNC_MACRO:
				um_cache_resolve_const(config->um_cache, hostids, hostids_num, text + token.loc.l + 1,
						env, &value);

				if (NULL != value)
					out = zbx_strdup(NULL, value);

				if (SUCCEED == zbx_token_find(text + token.loc.l, 0, &inner_token,
						ZBX_TOKEN_SEARCH_BASIC))
				{
					(void)zbx_calculate_macro_function(text + token.loc.l,
							&inner_token.data.func_macro, &out);
					value = out;
				}
				break;
			case ZBX_TOKEN_USER_MACRO:
				um_cache_resolve_const(config->um_cache, hostids, hostids_num, text + token.loc.l, env,
						&value);
				break;
		}

		if (NULL != value)
		{
			zbx_strcpy_alloc(&str, &str_alloc, &str_offset, value);
		}
		else
		{
			zbx_strncpy_alloc(&str, &str_alloc, &str_offset, text + token.loc.l,
					token.loc.r - token.loc.l + 1);
		}

		pos = (int)token.loc.r;
		last_pos = pos + 1;
		zbx_free(out);
	}

	zbx_strcpy_alloc(&str, &str_alloc, &str_offset, text + last_pos);

	return str;
}

int	DCitem_nextcheck_update(ZBX_DC_ITEM *item, const ZBX_DC_INTERFACE *interface, int flags, int now,
		char **error)
{
	zbx_uint64_t		seed;
	int			simple_interval, disable_until, ret;
	zbx_custom_interval_t	*custom_intervals;

	if (0 == (flags & ZBX_ITEM_COLLECTED) && 0 != item->nextcheck &&
			0 == (flags & ZBX_ITEM_KEY_CHANGED) && 0 == (flags & ZBX_ITEM_TYPE_CHANGED) &&
			0 == (flags & ZBX_ITEM_DELAY_CHANGED))
	{
		return SUCCEED;	/* avoid unnecessary nextcheck updates when syncing items in cache */
	}

	seed = get_item_nextcheck_seed(item, item->interfaceid, item->type, item->key);

	if (NULL != strstr(item->delay, "{$"))
	{
		char	*delay_s;

		delay_s = dc_expand_user_and_func_macros_dyn(item->delay, &item->hostid, 1, ZBX_MACRO_ENV_NONSECURE);
		ret = zbx_interval_preproc(delay_s, &simple_interval, &custom_intervals, error);
		zbx_free(delay_s);
	}
	else
		ret = zbx_interval_preproc(item->delay, &simple_interval, &custom_intervals, error);

	if (SUCCEED != ret)
	{
		/* Polling items with invalid update intervals repeatedly does not make sense because they */
		/* can only be healed by editing configuration (either update interval or macros involved) */
		/* and such changes will be detected during configuration synchronization. DCsync_items()  */
		/* detects item configuration changes affecting check scheduling and passes them in flags. */

		item->nextcheck = ZBX_JAN_2038;
		return FAIL;
	}

	if (0 != (flags & ZBX_HOST_UNREACHABLE) && NULL != interface && 0 != (disable_until =
			DCget_disable_until(item, interface)))
	{
		item->nextcheck = zbx_calculate_item_nextcheck_unreachable(simple_interval,
				custom_intervals, disable_until);
	}
	else
	{
		if (0 != (flags & ZBX_ITEM_NEW) &&
				FAIL == zbx_custom_interval_is_scheduling(custom_intervals) &&
				ITEM_TYPE_ZABBIX_ACTIVE != item->type &&
				ZBX_DEFAULT_ITEM_UPDATE_INTERVAL < simple_interval)
		{
			item->nextcheck = zbx_calculate_item_nextcheck(seed, item->type,
					ZBX_DEFAULT_ITEM_UPDATE_INTERVAL, NULL, now);
		}
		else
		{
			/* supported items and items that could not have been scheduled previously, but had */
			/* their update interval fixed, should be scheduled using their update intervals */
			item->nextcheck = zbx_calculate_item_nextcheck(seed, item->type, simple_interval,
					custom_intervals, now);
		}
	}

	zbx_custom_interval_free(custom_intervals);

	return SUCCEED;
}

static void	DCitem_poller_type_update(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host, int flags)
{
	unsigned char	poller_type;
	unsigned char	snmp_oid_type = ZBX_SNMP_OID_TYPE_MACRO; /* oid type is only used by ITEM_TYPE_SNMP*/

	if (HOST_MONITORED_BY_SERVER != dc_host->monitored_by &&
			SUCCEED != zbx_is_item_processed_by_server(dc_item->type, dc_item->key))
	{
		dc_item->poller_type = ZBX_NO_POLLER;
		return;
	}

	if (ITEM_TYPE_SNMP == dc_item->type)
		snmp_oid_type = dc_item->itemtype.snmpitem->snmp_oid_type;

	poller_type = poller_by_item(dc_item->type, dc_item->key, snmp_oid_type);

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

static void	DCincrease_disable_until(ZBX_DC_INTERFACE *interface, int now, int config_timeout)
{
	if (NULL != interface && 0 != interface->errors_from)
		interface->disable_until = now + config_timeout;
}

/******************************************************************************
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
 *     uniq    - [IN] flag.  ZBX_HASHSET_UNIQ_FALSE - search before insert.   *
 *                           ZBX_HASHSET_UNIQ_TRUE  - skip search.            *
 *                                                                            *
 * Return value: pointer to the found or created element                      *
 *                                                                            *
 ******************************************************************************/
void	*DCfind_id_ext(zbx_hashset_t *hashset, zbx_uint64_t id, size_t size, int *found, zbx_hashset_uniq_t uniq)
{
	void	*ptr;
	int	num_data = hashset->num_data;

	ptr = zbx_hashset_insert_ext(hashset, &id, size, 0, sizeof(id), uniq);

	if (num_data != hashset->num_data)
		*found = 0;
	else
		*found = 1;

	return ptr;
}

void	*DCfind_id(zbx_hashset_t *hashset, zbx_uint64_t id, size_t size, int *found)
{
	return DCfind_id_ext(hashset, id, size, found, ZBX_HASHSET_UNIQ_FALSE);
}

ZBX_DC_ITEM	*DCfind_item(zbx_uint64_t hostid, const char *key)
{
	ZBX_DC_ITEM_HK	*item_hk, item_hk_local;

	item_hk_local.hostid = hostid;
	item_hk_local.key = key;

	if (NULL == (item_hk = (ZBX_DC_ITEM_HK *)zbx_hashset_search(&config->items_hk, &item_hk_local)))
		return NULL;
	else
		return item_hk->item_ptr;
}

ZBX_DC_HOST	*DCfind_host(const char *host)
{
	ZBX_DC_HOST_H	*host_h, host_h_local;

	host_h_local.host = host;

	if (NULL == (host_h = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_h, &host_h_local)))
		return NULL;
	else
		return host_h->host_ptr;
}

static ZBX_DC_AUTOREG_HOST	*DCfind_autoreg_host(const char *host)
{
	ZBX_DC_AUTOREG_HOST	autoreg_host_local;

	autoreg_host_local.host = host;

	return (ZBX_DC_AUTOREG_HOST *)zbx_hashset_search(&config->autoreg_hosts, &autoreg_host_local);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Find a record with proxy details in configuration cache using the *
 *          proxy name                                                        *
 *                                                                            *
 * Parameters: name - [IN] proxy name                                         *
 *                                                                            *
 * Return value: pointer to record if found or NULL otherwise                 *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_PROXY	*DCfind_proxy(const char *name)
{
	zbx_dc_proxy_name_t	*proxy_p, proxy_p_local;

	proxy_p_local.name = name;

	if (NULL == (proxy_p = (zbx_dc_proxy_name_t *)zbx_hashset_search(&config->proxies_p, &proxy_p_local)))
		return NULL;
	else
		return proxy_p->proxy_ptr;
}

/* private strpool functions */

#define	REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

static zbx_hash_t	__config_strpool_hash(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((const char *)data + REFCOUNT_FIELD_SIZE);
}

static int	__config_strpool_compare(const void *d1, const void *d2)
{
	return strcmp((const char *)d1 + REFCOUNT_FIELD_SIZE, (const char *)d2 + REFCOUNT_FIELD_SIZE);
}

const char	*dc_strpool_intern(const char *str)
{
	void		*record;
	zbx_uint32_t	*refcount;
	size_t		size;

	if (NULL == str)
		return NULL;

	size = REFCOUNT_FIELD_SIZE + strlen(str) + 1;
	record = zbx_hashset_insert_ext(&config->strpool, str - REFCOUNT_FIELD_SIZE, size, REFCOUNT_FIELD_SIZE, size,
			ZBX_HASHSET_UNIQ_FALSE);

	refcount = (zbx_uint32_t *)record;
	(*refcount)++;

	return (char *)record + REFCOUNT_FIELD_SIZE;
}

void	dc_strpool_release(const char *str)
{
	zbx_uint32_t	*refcount;

	refcount = (zbx_uint32_t *)(str - REFCOUNT_FIELD_SIZE);
	if (0 == --(*refcount))
		zbx_hashset_remove(&config->strpool, str - REFCOUNT_FIELD_SIZE);
}

const char	*dc_strpool_acquire(const char *str)
{
	zbx_uint32_t	*refcount;

	if (NULL == str)
		return NULL;

	refcount = (zbx_uint32_t *)(str - REFCOUNT_FIELD_SIZE);
	(*refcount)++;

	return str;
}

int	dc_strpool_replace(int found, const char **curr, const char *new_str)
{
	if (1 == found)
	{
		if (0 == strcmp(*curr, new_str))
			return FAIL;

		dc_strpool_release(*curr);
	}

	*curr = dc_strpool_intern(new_str);

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
	elem.data = (void *)item;

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

	elem.key = proxy->proxyid;
	elem.data = (void *)proxy;

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
 * Purpose: sets and validates global housekeeping option                     *
 *                                                                            *
 * Parameters: value     - [OUT] housekeeping setting                         *
 *             non_zero  - [IN] 0 if value is allowed to be zero, 1 otherwise *
 *             value_min - [IN] minimal acceptable setting value              *
 *             value_raw - [IN] setting value to validate                     *
 *                                                                            *
 ******************************************************************************/
static int	set_hk_opt(int *value, int non_zero, int value_min, const char *value_raw, zbx_uint64_t revision)
{
	int	value_int;

	if (SUCCEED != zbx_is_time_suffix(value_raw, &value_int, ZBX_LENGTH_UNLIMITED))
		return FAIL;

	if (0 != non_zero && 0 == value_int)
		return FAIL;

	if (0 != *value && (value_min > value_int || ZBX_HK_PERIOD_MAX < value_int))
		return FAIL;

	if (*value != value_int)
	{
		*value = value_int;
		config->revision.config_table = revision;
	}

	return SUCCEED;
}

static int	DCsync_config(zbx_dbsync_t *sync, zbx_uint64_t revision, int *flags)
{
	const zbx_db_table_t	*config_table;

	/* sync with zbx_dbsync_compare_config() */
	const char	*selected_fields[] = {"discovery_groupid", "snmptrap_logging",
					"severity_name_0", "severity_name_1", "severity_name_2", "severity_name_3",
					"severity_name_4", "severity_name_5", "hk_events_mode", "hk_events_trigger",
					"hk_events_internal", "hk_events_discovery", "hk_events_autoreg",
					"hk_services_mode", "hk_services", "hk_audit_mode", "hk_audit",
					"hk_sessions_mode", "hk_sessions", "hk_history_mode", "hk_history_global",
					"hk_history", "hk_trends_mode", "hk_trends_global", "hk_trends",
					"default_inventory_mode", "db_extension", "autoreg_tls_accept",
					"compression_status", "compress_older", "instanceid",
					"default_timezone", "hk_events_service", "auditlog_enabled",
					"timeout_zabbix_agent", "timeout_simple_check", "timeout_snmp_agent",
					"timeout_external_check", "timeout_db_monitor", "timeout_http_agent",
					"timeout_ssh_agent", "timeout_telnet_agent", "timeout_script", "auditlog_mode",
					"timeout_browser"};

	const char	*row[ARRSIZE(selected_fields)];
	size_t		i;
	int		j, found = 1, ret, value_int;
	unsigned char	value_uchar;
	char		**db_row;
	zbx_uint64_t	rowid, value_uint64;
	unsigned char	tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	*flags = 0;

	if (NULL == config->config)
	{
		found = 0;
		config->config = (zbx_dc_config_table_t *)__config_shmem_malloc_func(NULL, sizeof(zbx_dc_config_table_t));
		memset(config->config, 0, sizeof(zbx_dc_config_table_t));
	}

	if (SUCCEED != (ret = zbx_dbsync_next(sync, &rowid, &db_row, &tag)))
	{
		/* load default config data */

		if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
			zabbix_log(LOG_LEVEL_ERR, "no records in table 'config'");

		config_table = zbx_db_get_table("config");

		for (i = 0; i < ARRSIZE(selected_fields); i++)
			row[i] = zbx_db_get_field(config_table, selected_fields[i])->default_value;
	}
	else
	{
		for (i = 0; i < ARRSIZE(selected_fields); i++)
			row[i] = db_row[i];
	}

	/* store the config data */

	if (NULL != row[0])
		ZBX_STR2UINT64(value_uint64, row[0]);
	else
		value_uint64 = ZBX_DISCOVERY_GROUPID_UNDEFINED;

	if (config->config->discovery_groupid != value_uint64)
	{
		config->config->discovery_groupid = value_uint64;
		config->revision.config_table = revision;
	}

	ZBX_STR2UCHAR(value_uchar, row[1]);
	if (config->config->snmptrap_logging != value_uchar)
	{
		config->config->snmptrap_logging = value_uchar;
		config->revision.config_table = revision;
	}

	if (config->config->default_inventory_mode != (value_int = atoi(row[25])))
	{
		config->config->default_inventory_mode = value_int;
		config->revision.config_table = revision;
	}

	if (NULL == config->config->db.extension || 0 != strcmp(config->config->db.extension, row[26]))
	{
		dc_strpool_replace(found, (const char **)&config->config->db.extension, row[26]);
		config->revision.config_table = revision;
	}

	ZBX_STR2UCHAR(value_uchar, row[27]);
	if (config->config->autoreg_tls_accept != value_uchar)
	{
		config->config->autoreg_tls_accept = value_uchar;
		config->revision.config_table = revision;
	}

	ZBX_STR2UCHAR(value_uchar, row[28]);
	if (config->config->db.history_compression_status != value_uchar)
	{
		config->config->db.history_compression_status = value_uchar;
		config->revision.config_table = revision;
	}

	if (SUCCEED != zbx_is_time_suffix(row[29], &value_int, ZBX_LENGTH_UNLIMITED))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid history compression age: %s", row[29]);
		value_int = 0;
	}

	if (config->config->db.history_compress_older != value_int)
	{
		config->config->db.history_compress_older = value_int;
		config->revision.config_table = revision;
	}

	for (j = 0; TRIGGER_SEVERITY_COUNT > j; j++)
	{
		if (NULL == config->config->severity_name[j] || 0 != strcmp(config->config->severity_name[j], row[2 + j]))
		{
			dc_strpool_replace(found, (const char **)&config->config->severity_name[j], row[2 + j]);
			config->revision.config_table = revision;
		}
	}

	/* instance id cannot be changed - update it only at first sync to avoid read locks later */
	if (0 == found)
		dc_strpool_replace(found, &config->config->instanceid, row[30]);

#if TRIGGER_SEVERITY_COUNT != 6
#	error "row indexes below are based on assumption of six trigger severity levels"
#endif

	/* read housekeeper configuration */
	if (ZBX_HK_OPTION_ENABLED == (value_int = atoi(row[8])) &&
			(SUCCEED != set_hk_opt(&config->config->hk.events_trigger, 1, SEC_PER_DAY, row[9], revision) ||
			SUCCEED != set_hk_opt(&config->config->hk.events_internal, 1, SEC_PER_DAY, row[10], revision) ||
			SUCCEED != set_hk_opt(&config->config->hk.events_discovery, 1, SEC_PER_DAY, row[11], revision) ||
			SUCCEED != set_hk_opt(&config->config->hk.events_autoreg, 1, SEC_PER_DAY, row[12], revision) ||
			SUCCEED != set_hk_opt(&config->config->hk.events_service, 1, SEC_PER_DAY, row[32], revision)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "trigger, internal, network discovery and auto-registration data"
				" housekeeping will be disabled due to invalid settings");
		value_int = ZBX_HK_OPTION_DISABLED;
	}
	if (config->config->hk.events_mode != value_int)
	{
		config->config->hk.events_mode = value_int;
		config->revision.config_table = revision;
	}

	if (ZBX_HK_OPTION_ENABLED == (value_int = atoi(row[13])) &&
			SUCCEED != set_hk_opt(&config->config->hk.services, 1, SEC_PER_DAY, row[14], revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "IT services data housekeeping will be disabled due to invalid"
				" settings");
		value_int = ZBX_HK_OPTION_DISABLED;
	}
	if (config->config->hk.services_mode != value_int)
	{
		config->config->hk.services_mode = value_int;
		config->revision.config_table = revision;
	}

	if (ZBX_HK_OPTION_ENABLED == (value_int = atoi(row[15])) &&
			SUCCEED != set_hk_opt(&config->config->hk.audit, 1, SEC_PER_DAY, row[16], revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "audit data housekeeping will be disabled due to invalid"
				" settings");
		value_int = ZBX_HK_OPTION_DISABLED;
	}
	if (config->config->hk.audit_mode != value_int)
	{
		config->config->hk.audit_mode = value_int;
		config->revision.config_table = revision;
	}

#ifdef HAVE_POSTGRESQL
	if (ZBX_HK_MODE_DISABLED != config->config->hk.audit_mode &&
			0 == zbx_strcmp_null(config->config->db.extension, ZBX_DB_EXTENSION_TIMESCALEDB))
	{
		if (ZBX_HK_MODE_PARTITION != config->config->hk.audit_mode)
		{
			config->config->hk.audit_mode = ZBX_HK_MODE_PARTITION;
			config->revision.config_table = revision;
		}
	}
#endif

	if (ZBX_HK_OPTION_ENABLED == (value_int = atoi(row[17])) &&
			SUCCEED != set_hk_opt(&config->config->hk.sessions, 1, SEC_PER_DAY, row[18], revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "user sessions data housekeeping will be disabled due to invalid"
				" settings");
		value_int = ZBX_HK_OPTION_DISABLED;
	}
	if (config->config->hk.sessions_mode != value_int)
	{
		config->config->hk.sessions_mode = value_int;
		config->revision.config_table = revision;
	}

	if (config->config->hk.history_mode != (value_int = atoi(row[19])))
	{
		config->config->hk.history_mode = value_int;
		config->revision.config_table = revision;
	}

	if (ZBX_HK_OPTION_ENABLED == (value_int = atoi(row[20])) &&
			SUCCEED != set_hk_opt(&config->config->hk.history, 0, ZBX_HK_HISTORY_MIN, row[21], revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "history data housekeeping will be disabled and all items will"
				" store their history due to invalid global override settings");
		if (ZBX_HK_MODE_DISABLED != config->config->hk.history_mode)
		{
			config->config->hk.history_mode = ZBX_HK_MODE_DISABLED;
			config->revision.config_table = revision;
		}

		if (1 != config->config->hk.history)
		{
			config->config->hk.history = 1;	/* just enough to make 0 == items[i].history condition fail */
			config->revision.config_table = revision;
		}
	}
	if (config->config->hk.history_global != value_int)
	{
		config->config->hk.history_global = value_int;
		config->revision.config_table = revision;
	}

#ifdef HAVE_POSTGRESQL
	if (ZBX_HK_MODE_DISABLED != config->config->hk.history_mode &&
			ZBX_HK_OPTION_ENABLED == config->config->hk.history_global &&
			0 == zbx_strcmp_null(config->config->db.extension, ZBX_DB_EXTENSION_TIMESCALEDB))
	{
		if (ZBX_HK_MODE_PARTITION != config->config->hk.history_mode)
		{
			config->config->hk.history_mode = ZBX_HK_MODE_PARTITION;
			config->revision.config_table = revision;
		}
	}
#endif

	if (config->config->hk.trends_mode != (value_int = atoi(row[22])))
	{
		config->config->hk.trends_mode = value_int;
		config->revision.config_table = revision;
	}

	if (ZBX_HK_OPTION_ENABLED == (value_int = atoi(row[23])) &&
			SUCCEED != set_hk_opt(&config->config->hk.trends, 0, ZBX_HK_TRENDS_MIN, row[24], revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "trends data housekeeping will be disabled and all numeric items"
				" will store their history due to invalid global override settings");
		if (ZBX_HK_MODE_DISABLED != config->config->hk.trends_mode)
		{
			config->config->hk.trends_mode = ZBX_HK_MODE_DISABLED;
			config->revision.config_table = revision;
		}
		if (1 != config->config->hk.trends)
		{
			config->config->hk.trends = 1;	/* just enough to make 0 == items[i].trends condition fail */
			config->revision.config_table = revision;
		}
	}
	if (config->config->hk.trends_global != value_int)
	{
		config->config->hk.trends_global = value_int;
		config->revision.config_table = revision;
	}

#ifdef HAVE_POSTGRESQL
	if (ZBX_HK_MODE_DISABLED != config->config->hk.trends_mode &&
			ZBX_HK_OPTION_ENABLED == config->config->hk.trends_global &&
			0 == zbx_strcmp_null(config->config->db.extension, ZBX_DB_EXTENSION_TIMESCALEDB))
	{
		if (ZBX_HK_MODE_PARTITION != config->config->hk.trends_mode)
		{
			config->config->hk.trends_mode = ZBX_HK_MODE_PARTITION;
			config->revision.config_table = revision;
		}
	}
#endif

	if (NULL == config->config->default_timezone || 0 != strcmp(config->config->default_timezone, row[31]))
	{
		dc_strpool_replace(found, (const char **)&config->config->default_timezone, row[31]);
		config->revision.config_table = revision;
	}

	if (config->config->auditlog_enabled != (value_int = atoi(row[33])))
	{
		config->config->auditlog_enabled = value_int;
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.agent || 0 != strcmp(config->config->item_timeouts.agent, row[34]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.agent, row[34]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.simple || 0 != strcmp(config->config->item_timeouts.simple, row[35]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.simple, row[35]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.snmp || 0 != strcmp(config->config->item_timeouts.snmp, row[36]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.snmp, row[36]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.external || 0 != strcmp(config->config->item_timeouts.external,
			row[37]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.external, row[37]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.odbc || 0 != strcmp(config->config->item_timeouts.odbc, row[38]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.odbc, row[38]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.http || 0 != strcmp(config->config->item_timeouts.http, row[39]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.http, row[39]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.ssh || 0 != strcmp(config->config->item_timeouts.ssh, row[40]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.ssh, row[40]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.telnet || 0 != strcmp(config->config->item_timeouts.telnet, row[41]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.telnet, row[41]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.script || 0 != strcmp(config->config->item_timeouts.script, row[42]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.script, row[42]);
		config->revision.config_table = revision;
	}

	if (NULL == config->config->item_timeouts.browser || 0 != strcmp(config->config->item_timeouts.browser,
			row[44]))
	{
		dc_strpool_replace(found, (const char **)&config->config->item_timeouts.browser, row[44]);
		config->revision.config_table = revision;
	}

	if (config->config->auditlog_mode != (value_int = atoi(row[43])))
	{
		config->config->auditlog_mode = value_int;
		config->revision.config_table = revision;
	}

	if (SUCCEED == ret && SUCCEED == zbx_dbsync_next(sync, &rowid, &db_row, &tag))	/* table must have */
		zabbix_log(LOG_LEVEL_ERR, "table 'config' has multiple records");	/* only one record */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate nextcheck timestamp for passive proxy                   *
 *                                                                            *
 * Parameters: hostid - [IN] host identifier from database                    *
 *             delay  - [IN] default delay value, can be overridden           *
 *             now    - [IN] current timestamp                                *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 ******************************************************************************/
static time_t	calculate_proxy_nextcheck(zbx_uint64_t hostid, unsigned int delay, time_t now)
{
	time_t	nextcheck;

	nextcheck = delay * (now / delay) + (unsigned int)(hostid % delay);

	while (nextcheck <= now)
		nextcheck += delay;

	return nextcheck;
}

static void	DCsync_autoreg_config(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	/* sync this function with zbx_dbsync_compare_autoreg_psk() */
	char		**db_row;
	zbx_uint64_t	rowid;
	unsigned char	tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	if (SUCCEED == zbx_dbsync_next(sync, &rowid, &db_row, &tag))
	{
		switch (tag)
		{
			case ZBX_DBSYNC_ROW_ADD:
			case ZBX_DBSYNC_ROW_UPDATE:
				zbx_strlcpy(config->autoreg_psk_identity, db_row[0],
						sizeof(config->autoreg_psk_identity));
				zbx_strlcpy(config->autoreg_psk, db_row[1], sizeof(config->autoreg_psk));
				break;
			case ZBX_DBSYNC_ROW_REMOVE:
				config->autoreg_psk_identity[0] = '\0';
				zbx_guaranteed_memset(config->autoreg_psk, 0, sizeof(config->autoreg_psk));
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		config->revision.autoreg_tls = revision;
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	DCsync_autoreg_host(zbx_dbsync_t *sync)
{
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_DC_AUTOREG_HOST	*autoreg_host, autoreg_host_local = {.host = row[0]};
		int			found;

		autoreg_host = (ZBX_DC_AUTOREG_HOST *)zbx_hashset_search(&config->autoreg_hosts, &autoreg_host_local);
		if (NULL == autoreg_host)
		{
			found = 0;
			autoreg_host = zbx_hashset_insert(&config->autoreg_hosts, &autoreg_host_local,
					sizeof(ZBX_DC_AUTOREG_HOST));
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot process duplicate host '%s' in autoreg_host table",
					row[0]);
			found = 1;
		}

		dc_strpool_replace(found, &autoreg_host->host, row[0]);
		dc_strpool_replace(found, &autoreg_host->listen_ip, row[1]);
		dc_strpool_replace(found, &autoreg_host->listen_dns, row[2]);
		dc_strpool_replace(found, &autoreg_host->host_metadata, row[3]);
		autoreg_host->flags = atoi(row[4]);
		autoreg_host->listen_port = atoi(row[5]);
		autoreg_host->timestamp = 0;
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
void	dc_psk_unlink(ZBX_DC_PSK *tls_dc_psk)
{
	/* Maintain 'psks' index. Unlink and delete the PSK identity. */
	if (NULL != tls_dc_psk)
	{
		ZBX_DC_PSK	*psk_i, psk_i_local;

		psk_i_local.tls_psk_identity = tls_dc_psk->tls_psk_identity;

		if (NULL != (psk_i = (ZBX_DC_PSK *)zbx_hashset_search(&config->psks, &psk_i_local)) &&
				0 == --(psk_i->refcount))
		{
			dc_strpool_release(psk_i->tls_psk_identity);
			dc_strpool_release(psk_i->tls_psk);
			zbx_hashset_remove_direct(&config->psks, psk_i);
		}
	}
}

ZBX_DC_PSK	*dc_psk_sync(char *tls_psk_identity, char *tls_psk, const char *name, int found,
		zbx_hashset_t *psk_owners, ZBX_DC_PSK *tls_dc_psk)
{
	ZBX_DC_PSK	*psk_i, psk_i_local;
	zbx_ptr_pair_t	*psk_owner = NULL, psk_owner_local;

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
	/*                    'host/proxy' 'host/proxy' 'host/proxy' 'host/proxy'    */
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
	/*       old PSK          already     not in           'hosts/proxy' record  */
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
	/*                            in 'host/proxy'                                */
	/*                            record to                                      */
	/*                            new PSKid                                      */
	/*                                |                                          */
	/*                               done                                        */
	/*                                                                           */
	/*****************************************************************************/

	if ('\0' == *tls_psk_identity || '\0' == *tls_psk)	/* new PSKid or value empty */
	{
		/* In case of "impossible" errors ("PSK value without identity" or "PSK identity without */
		/* value") assume empty PSK identity and value. These errors should have been prevented */
		/* by validation in frontend/API. Be prepared when making a connection requiring PSK - */
		/* the PSK might not be available. */

		if (1 == found)
		{
			if (NULL == tls_dc_psk)	/* 'host/proxy' record has empty PSK */
				goto done;

			/* 'host/proxy' record has non-empty PSK. Unlink and delete PSK. */
			dc_psk_unlink(tls_dc_psk);
		}

		tls_dc_psk = NULL;
		goto done;
	}

	/* new PSKid and value non-empty */

	zbx_strlower(tls_psk);

	if (1 == found && NULL != tls_dc_psk)	/* 'host/proxy' record has non-empty PSK */
	{
		if (0 == strcmp(tls_dc_psk->tls_psk_identity, tls_psk_identity))	/* new PSKid same as */
										/* old PSKid */
		{
			if (0 != strcmp(tls_dc_psk->tls_psk, tls_psk))	/* new PSK value */
										/* differs from old */
			{
				if (NULL == (psk_owner = (zbx_ptr_pair_t *)zbx_hashset_search(psk_owners,
						&tls_dc_psk->tls_psk_identity)))
				{
					/* change underlying PSK value and 'config->psks' is updated, too */
					dc_strpool_replace(1, &tls_dc_psk->tls_psk, tls_psk);
				}
				else
				{
					zabbix_log(LOG_LEVEL_WARNING, "conflicting PSK values for PSK identity"
							" \"%s\" on \"%s\" and \"%s\" (and maybe others)",
							(char *)psk_owner->first, (char *)psk_owner->second,
							name);
				}
			}

			goto done;
		}

		/* New PSKid differs from old PSKid. Unlink and delete old PSK. */

		dc_psk_unlink(tls_dc_psk);
	}

	psk_i_local.tls_psk_identity = tls_psk_identity;

	/* new PSK identity already stored? */
	if (NULL != (psk_i = (ZBX_DC_PSK *)zbx_hashset_search(&config->psks, &psk_i_local)))
	{
		/* new PSKid already in psks hashset */

		if (0 != strcmp(psk_i->tls_psk, tls_psk))	/* PSKid stored but PSK value is different */
		{
			if (NULL == (psk_owner = (zbx_ptr_pair_t *)zbx_hashset_search(psk_owners,
					&psk_i->tls_psk_identity)))
			{
				dc_strpool_replace(1, &psk_i->tls_psk, tls_psk);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "conflicting PSK values for PSK identity"
						" \"%s\" on \"%s\" and \"%s\" (and maybe others)",
						(char *)psk_owner->first, (char *)psk_owner->second,
						name);
			}
		}

		tls_dc_psk = psk_i;
		psk_i->refcount++;
		goto done;
	}

	/* insert new PSKid and value into psks hashset */

	dc_strpool_replace(0, &psk_i_local.tls_psk_identity, tls_psk_identity);
	dc_strpool_replace(0, &psk_i_local.tls_psk, tls_psk);
	psk_i_local.refcount = 1;
	tls_dc_psk = zbx_hashset_insert(&config->psks, &psk_i_local, sizeof(ZBX_DC_PSK));
done:
	if (NULL != tls_dc_psk && NULL == psk_owner)
	{
		if (NULL == zbx_hashset_search(psk_owners, &tls_dc_psk->tls_psk_identity))
		{
			/* register this host/proxy as the PSK identity owner, against which to report conflicts */

			psk_owner_local.first = (char *)tls_dc_psk->tls_psk_identity;
			psk_owner_local.second = (char *)name;

			zbx_hashset_insert(psk_owners, &psk_owner_local, sizeof(psk_owner_local));
		}
	}

	return tls_dc_psk;
}
#endif
static void	DCsync_proxy_remove(ZBX_DC_PROXY *proxy)
{
	zbx_dc_proxy_name_t	*proxy_p, proxy_p_local;

	if (ZBX_LOC_QUEUE == proxy->location)
	{
		zbx_binary_heap_remove_direct(&config->pqueue, proxy->proxyid);
		proxy->location = ZBX_LOC_NOWHERE;
	}

	dc_strpool_release(proxy->allowed_addresses);
	dc_strpool_release(proxy->address);
	dc_strpool_release(proxy->port);
	dc_strpool_release(proxy->local_address);
	dc_strpool_release(proxy->local_port);
	dc_strpool_release(proxy->version_str);
	dc_strpool_release(proxy->item_timeouts.agent);
	dc_strpool_release(proxy->item_timeouts.simple);
	dc_strpool_release(proxy->item_timeouts.snmp);
	dc_strpool_release(proxy->item_timeouts.external);
	dc_strpool_release(proxy->item_timeouts.odbc);
	dc_strpool_release(proxy->item_timeouts.http);
	dc_strpool_release(proxy->item_timeouts.ssh);
	dc_strpool_release(proxy->item_timeouts.telnet);
	dc_strpool_release(proxy->item_timeouts.script);
	dc_strpool_release(proxy->item_timeouts.browser);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	dc_strpool_release(proxy->tls_issuer);
	dc_strpool_release(proxy->tls_subject);

	/* Maintain 'psks' index. Unlink and delete the PSK identity. */
	dc_psk_unlink(proxy->tls_dc_psk);
#endif
	zbx_vector_dc_host_ptr_destroy(&proxy->hosts);
	zbx_vector_host_rev_destroy(&proxy->removed_hosts);

	proxy_p_local.name = proxy->name;
	proxy_p = (zbx_dc_proxy_name_t *)zbx_hashset_search(&config->proxies_p, &proxy_p_local);

	if (NULL != proxy_p && proxy == proxy_p->proxy_ptr)
	{
		dc_strpool_release(proxy_p->name);
		zbx_hashset_remove_direct(&config->proxies_p, proxy_p);
	}

	dc_strpool_release(proxy->name);

	zbx_hashset_remove_direct(&config->proxies, proxy);
}

void	dc_host_deregister_proxy(ZBX_DC_HOST *host, zbx_uint64_t proxyid, zbx_uint64_t revision)
{
	ZBX_DC_PROXY	*proxy;
	int		i;
	zbx_host_rev_t	rev;

	if (NULL == (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxyid)))
		return;

	rev.hostid = host->hostid;
	rev.revision = revision;
	zbx_vector_host_rev_append(&proxy->removed_hosts, rev);
	proxy->revision = revision;

	if (FAIL == (i = zbx_vector_dc_host_ptr_search(&proxy->hosts, host, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
		return;

	zbx_vector_dc_host_ptr_remove_noorder(&proxy->hosts, i);
}

static int	dc_compare_host_rev_by_hostid(const void *d1, const void *d2)
{
	const zbx_host_rev_t	*r1 = (const zbx_host_rev_t *)d1;
	const zbx_host_rev_t	*r2 = (const zbx_host_rev_t *)d2;

	ZBX_RETURN_IF_DBL_NOT_EQUAL(r1->hostid, r2->hostid);
	return 0;
}

void	dc_host_register_proxy(ZBX_DC_HOST *host, zbx_uint64_t proxyid, zbx_uint64_t revision)
{
	ZBX_DC_PROXY	*proxy;

	if (NULL == (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxyid)))
		return;

	zbx_vector_dc_host_ptr_append(&proxy->hosts, host);
	proxy->revision = revision;

	zbx_host_rev_t	rev = {.hostid = host->hostid};
	int		i;

	if (FAIL != (i = zbx_vector_host_rev_search(&proxy->removed_hosts, rev, dc_compare_host_rev_by_hostid)))
		zbx_vector_host_rev_remove_noorder(&proxy->removed_hosts, i);
}

static void	dc_host_set_proxy_group(ZBX_DC_HOST *host, zbx_uint64_t proxy_groupid, zbx_vector_objmove_t *pg_reloc)
{
	if (NULL != pg_reloc && (0 != proxy_groupid || 0 != host->proxy_groupid))
	{
		zbx_objmove_t	move = {
				.objid = host->hostid,
				.srcid = host->proxy_groupid,
				.dstid = proxy_groupid
		};

		zbx_vector_objmove_append_ptr(pg_reloc, &move);
	}

	host->proxy_groupid = proxy_groupid;
}

static void	DCsync_hosts(zbx_dbsync_t *sync, zbx_uint64_t revision, zbx_vector_uint64_t *active_avail_diff,
		zbx_hashset_t *activated_hosts, zbx_hashset_t *psk_owners, zbx_vector_objmove_t *pg_host_reloc)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;

	ZBX_DC_HOST			*host;
	ZBX_DC_IPMIHOST			*ipmihost;
	ZBX_DC_PROXY			*proxy;
	ZBX_DC_HOST_H			*host_h, host_h_local;

	int				i, found;
	int				update_index_h, ret;
	zbx_uint64_t			hostid, proxyid, proxy_groupid;
	unsigned char			status;
	time_t				now;
	signed char			ipmi_authtype;
	unsigned char			ipmi_privilege, monitored_by;
	zbx_vector_dc_host_ptr_t	proxy_hosts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_dc_host_ptr_create(&proxy_hosts);

	now = time(NULL);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_STR2UCHAR(monitored_by, row[20]);

		if (HOST_MONITORED_BY_PROXY == monitored_by)
			ZBX_DBROW2UINT64(proxyid, row[1]);
		else
			proxyid = 0;

		if (HOST_MONITORED_BY_PROXY_GROUP == monitored_by)
			ZBX_DBROW2UINT64(proxy_groupid, row[19]);
		else
			proxy_groupid = 0;

		ZBX_STR2UCHAR(status, row[10]);

		host = (ZBX_DC_HOST *)DCfind_id(&config->hosts, hostid, sizeof(ZBX_DC_HOST), &found);
		host->revision = revision;

		/* see whether we should and can update 'hosts_h' and 'proxies_p' indexes at this point */

		update_index_h = 0;

		if (0 == found || 0 != strcmp(host->host, row[2]))
		{
			if (1 == found)
			{
				host_h_local.host = host->host;
				host_h = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_h, &host_h_local);

				if (NULL != host_h && host == host_h->host_ptr)	/* see ZBX-4045 for NULL check */
				{
					dc_strpool_release(host_h->host);
					zbx_hashset_remove_direct(&config->hosts_h, host_h);
				}

				/* update host proxy index if host was renamed */
				dc_update_host_proxy(host->host, row[2]);
			}

			host_h_local.host = row[2];
			host_h = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_h, &host_h_local);

			if (NULL != host_h)
				host_h->host_ptr = host;
			else
				update_index_h = 1;
		}

		/* store new information in host structure */

		dc_strpool_replace(found, &host->host, row[2]);
		dc_strpool_replace(found, &host->name, row[11]);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		dc_strpool_replace(found, &host->tls_issuer, row[14]);
		dc_strpool_replace(found, &host->tls_subject, row[15]);

		/* maintain 'config->psks' in configuration cache */
		host->tls_dc_psk = dc_psk_sync(row[16], row[17], host->name, found, psk_owners, host->tls_dc_psk);
#else
		ZBX_UNUSED(psk_owners);
#endif
		ZBX_STR2UCHAR(host->tls_connect, row[12]);
		ZBX_STR2UCHAR(host->tls_accept, row[13]);

		if (0 == found)
		{
			ZBX_DBROW2UINT64(host->maintenanceid, row[18]);
			host->maintenance_status = (unsigned char)atoi(row[7]);
			host->maintenance_type = (unsigned char)atoi(row[8]);
			host->maintenance_from = atoi(row[9]);
			host->data_expected_from = now;
			host->proxyid = 0;
			host->proxy_groupid = 0;

			zbx_vector_ptr_create_ext(&host->interfaces_v, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);

			zbx_vector_dc_httptest_ptr_create_ext(&host->httptests, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);

			zbx_hashset_create_ext(&host->items, 0, dc_item_ref_hash, dc_item_ref_compare, NULL,
					__config_shmem_malloc_func, __config_shmem_realloc_func,
					__config_shmem_free_func);
		}
		else
		{
			int reset_availability = 0;

			if (HOST_STATUS_MONITORED == status && HOST_STATUS_MONITORED != host->status)
				host->data_expected_from = now;

			/* reset host status if host status has been changed (e.g., if host has been disabled) */
			if (status != host->status)
			{
				zbx_vector_uint64_append(active_avail_diff, host->hostid);

				reset_availability = 1;
			}

			/* reset host status if host proxy assignment has been changed */
			if (proxyid != host->proxyid)
			{
				zbx_vector_uint64_append(active_avail_diff, host->hostid);

				reset_availability = 1;
			}

			if (0 != reset_availability)
			{
				ZBX_DC_INTERFACE	*interface;

				for (i = 0; i < host->interfaces_v.values_num; i++)
				{
					interface = (ZBX_DC_INTERFACE *)host->interfaces_v.values[i];
					interface->reset_availability = 1;
				}
			}

			/* gather hosts that must restart monitoring either by being re-enabled or */
			/* assigned from proxy to server                                           */
			if ((HOST_STATUS_MONITORED == status && HOST_STATUS_MONITORED != host->status) ||
					(0 == proxyid && 0 != host->proxyid))
			{
				zbx_hashset_insert(activated_hosts, &host->hostid, sizeof(host->hostid));
			}
		}

		if (0 != found && 0 != host->proxyid && host->proxyid != proxyid && 0 == proxy_groupid)
			dc_host_deregister_proxy(host, host->proxyid, revision);

		/* hosts assigned to proxy groups have NULL proxyid in database,              */
		/* so the proxy updates are done only for hosts directly monitored by proxies */
		if (0 != proxyid)
		{
			if (0 == found || host->proxyid != proxyid)
			{
				zbx_vector_dc_host_ptr_append(&proxy_hosts, host);
			}
			else
			{
				if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxyid)))
					proxy->revision = revision;
			}
		}

		/* when monitored by proxy group the proxyid in cache is updated */
		/* during host_proxy table sync                                  */
		if (0 == proxy_groupid)
			host->proxyid = proxyid;

		/* update 'hosts_h' indexes using new data, if not done already */

		if (1 == update_index_h)
		{
			host_h_local.host = dc_strpool_acquire(host->host);
			host_h_local.host_ptr = host;
			zbx_hashset_insert(&config->hosts_h, &host_h_local, sizeof(ZBX_DC_HOST_H));
		}

		/* IPMI hosts */

		ipmi_authtype = (signed char)atoi(row[3]);
		ipmi_privilege = (unsigned char)atoi(row[4]);

		if (ZBX_IPMI_DEFAULT_AUTHTYPE != ipmi_authtype || ZBX_IPMI_DEFAULT_PRIVILEGE != ipmi_privilege ||
				'\0' != *row[5] || '\0' != *row[6])	/* useipmi */
		{
			ipmihost = (ZBX_DC_IPMIHOST *)DCfind_id(&config->ipmihosts, hostid, sizeof(ZBX_DC_IPMIHOST),
					&found);

			ipmihost->ipmi_authtype = ipmi_authtype;
			ipmihost->ipmi_privilege = ipmi_privilege;
			dc_strpool_replace(found, &ipmihost->ipmi_username, row[5]);
			dc_strpool_replace(found, &ipmihost->ipmi_password, row[6]);
		}
		else if (NULL != (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_search(&config->ipmihosts, &hostid)))
		{
			/* remove IPMI connection parameters for hosts without IPMI */

			dc_strpool_release(ipmihost->ipmi_username);
			dc_strpool_release(ipmihost->ipmi_password);

			zbx_hashset_remove_direct(&config->ipmihosts, ipmihost);
		}

		host->status = status;
		host->monitored_by = monitored_by;

		dc_host_set_proxy_group(host, proxy_groupid, pg_host_reloc);
	}

	for (i = 0; i < proxy_hosts.values_num; i++)
		dc_host_register_proxy(proxy_hosts.values[i], proxy_hosts.values[i]->proxyid, revision);

	/* remove deleted hosts from buffer */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &rowid)))
			continue;

		hostid = host->hostid;

		/* IPMI hosts */

		if (NULL != (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_search(&config->ipmihosts, &hostid)))
		{
			dc_strpool_release(ipmihost->ipmi_username);
			dc_strpool_release(ipmihost->ipmi_password);

			zbx_hashset_remove_direct(&config->ipmihosts, ipmihost);
		}

		/* hosts */

		/* clear proxy group and update tracking info */
		dc_host_set_proxy_group(host, 0, pg_host_reloc);

		if (HOST_STATUS_MONITORED == host->status || HOST_STATUS_NOT_MONITORED == host->status)
		{
			host_h_local.host = host->host;
			host_h = (ZBX_DC_HOST_H *)zbx_hashset_search(&config->hosts_h, &host_h_local);

			if (NULL != host_h && host == host_h->host_ptr)	/* see ZBX-4045 for NULL check */
			{
				dc_strpool_release(host_h->host);
				zbx_hashset_remove_direct(&config->hosts_h, host_h);
			}

			zbx_vector_uint64_append(active_avail_diff, host->hostid);

			if (0 != host->proxyid && 0 == host->proxy_groupid)
				dc_host_deregister_proxy(host, host->proxyid, revision);
		}

		dc_strpool_release(host->host);
		dc_strpool_release(host->name);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		dc_strpool_release(host->tls_issuer);
		dc_strpool_release(host->tls_subject);
		dc_psk_unlink(host->tls_dc_psk);
#endif
		zbx_vector_ptr_destroy(&host->interfaces_v);
		zbx_hashset_destroy(&host->items);
		zbx_hashset_remove_direct(&config->hosts, host);

		zbx_vector_dc_httptest_ptr_destroy(&host->httptests);
	}

	zbx_vector_dc_host_ptr_destroy(&proxy_hosts);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	DCsync_host_inventory(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	ZBX_DC_HOST_INVENTORY	*host_inventory, *host_inventory_auto;
	zbx_uint64_t		rowid, hostid;
	int			found, ret, i;
	char			**row;
	unsigned char		tag;
	ZBX_DC_HOST		*dc_host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostid, row[0]);

		host_inventory = (ZBX_DC_HOST_INVENTORY *)DCfind_id(&config->host_inventories, hostid,
				sizeof(ZBX_DC_HOST_INVENTORY), &found);

		ZBX_STR2UCHAR(host_inventory->inventory_mode, row[1]);

		/* store new information in host_inventory structure */
		for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
			dc_strpool_replace(found, &(host_inventory->values[i]), row[i + 2]);

		host_inventory_auto = (ZBX_DC_HOST_INVENTORY *)DCfind_id(&config->host_inventories_auto, hostid,
				sizeof(ZBX_DC_HOST_INVENTORY), &found);

		host_inventory_auto->inventory_mode = host_inventory->inventory_mode;

		if (1 == found)
		{
			for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
			{
				if (NULL == host_inventory_auto->values[i])
					continue;

				dc_strpool_release(host_inventory_auto->values[i]);
				host_inventory_auto->values[i] = NULL;
			}
		}
		else
		{
			for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
				host_inventory_auto->values[i] = NULL;
		}

		if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
			dc_host_update_revision(dc_host, revision);
	}

	/* remove deleted host inventory from cache */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (host_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories,
				&rowid)))
		{
			continue;
		}

		if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &host_inventory->hostid)))
			dc_host_update_revision(dc_host, revision);

		for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
			dc_strpool_release(host_inventory->values[i]);

		zbx_hashset_remove_direct(&config->host_inventories, host_inventory);

		if (NULL == (host_inventory_auto = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(
				&config->host_inventories_auto, &rowid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		for (i = 0; i < HOST_INVENTORY_FIELD_COUNT; i++)
		{
			if (NULL != host_inventory_auto->values[i])
				dc_strpool_release(host_inventory_auto->values[i]);
		}

		zbx_hashset_remove_direct(&config->host_inventories_auto, host_inventory_auto);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_dc_sync_kvs_paths(const struct zbx_json_parse *jp_kvs_paths, const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location)
{
	zbx_dc_kvs_path_t	*dc_kvs_path;
	zbx_dc_kv_t		*dc_kv;
	zbx_kvs_t		kvs;
	zbx_hashset_iter_t	iter;
	int			i, j;
	zbx_vector_ptr_pair_t	diff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_pair_create(&diff);
	zbx_kvs_create(&kvs, 100);

	for (i = 0; i < config->kvs_paths.values_num; i++)
	{
		char	*error = NULL;

		dc_kvs_path = (zbx_dc_kvs_path_t *)config->kvs_paths.values[i];

		if (NULL != jp_kvs_paths)
		{
			if (FAIL == zbx_kvs_from_json_by_path_get(dc_kvs_path->path, jp_kvs_paths, &kvs, &error))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot get secrets for path \"%s\": %s",
						dc_kvs_path->path, error);
				zbx_free(error);
				continue;
			}

		}
		else if (FAIL == zbx_vault_kvs_get(dc_kvs_path->path, &kvs, config_vault, config_source_ip,
				config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get secrets for path \"%s\": %s", dc_kvs_path->path,
					error);
			zbx_free(error);
			continue;
		}

		zbx_hashset_iter_reset(&dc_kvs_path->kvs, &iter);
		while (NULL != (dc_kv = (zbx_dc_kv_t *)zbx_hashset_iter_next(&iter)))
		{
			zbx_kv_t	*kv, kv_local;
			zbx_ptr_pair_t	pair;

			kv_local.key = (char *)dc_kv->key;
			if (NULL != (kv = zbx_kvs_search(&kvs, &kv_local)))
			{
				if (0 == zbx_strcmp_null(dc_kv->value, kv->value) && 0 == dc_kv->update)
					continue;
			}
			else if (NULL == dc_kv->value)
				continue;

			pair.first = dc_kv;
			pair.second = kv;
			zbx_vector_ptr_pair_append(&diff, pair);
		}

		if (0 != diff.values_num)
		{
			START_SYNC;

			config->revision.config++;

			for (j = 0; j < diff.values_num; j++)
			{
				zbx_kv_t	*kv;

				dc_kv = (zbx_dc_kv_t *)diff.values[j].first;
				kv = (zbx_kv_t *)diff.values[j].second;

				if (NULL != kv)
				{
					dc_strpool_replace(dc_kv->value != NULL ? 1 : 0, &dc_kv->value, kv->value);
				}
				else
				{
					dc_strpool_release(dc_kv->value);
					dc_kv->value = NULL;
				}

				config->um_cache = um_cache_set_value_to_macros(config->um_cache,
						config->revision.config, &dc_kv->macros, dc_kv->value);

				dc_kv->update = 0;
			}

			FINISH_SYNC;
		}

		zbx_vector_ptr_pair_clear(&diff);
		zbx_kvs_clear(&kvs);
	}

	zbx_vector_ptr_pair_destroy(&diff);
	zbx_kvs_destroy(&kvs);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

#define EXPAND_INTERFACE_MACROS		1

/******************************************************************************
 *                                                                            *
 * Purpose: expand host macros in string                                      *
 *                                                                            *
 * Parameters: text    - [IN] input string                                    *
 *             dc_host - [IN]                                                 *
 *             flags   - [IN] specifies if interface related macros must      *
 *                            be resolved (EXPAND_INTERFACE_MACROS)           *
 *                                                                            *
 * Return value: text with resolved macros or NULL if there were no macros    *
 *                                                                            *
 ******************************************************************************/
static char	*dc_expand_host_macros_dyn(const char *text, const ZBX_DC_HOST *dc_host, int flags)
{
#define IF_MACRO_HOST		"{HOST."
#define IF_MACRO_HOST_HOST	IF_MACRO_HOST "HOST}"
#define IF_MACRO_HOST_NAME	IF_MACRO_HOST "NAME}"
#define IF_MACRO_HOST_IP	IF_MACRO_HOST "IP}"
#define IF_MACRO_HOST_DNS	IF_MACRO_HOST "DNS}"
#define IF_MACRO_HOST_CONN	IF_MACRO_HOST "CONN}"
/* deprecated macros */
#define IF_MACRO_HOSTNAME	"{HOSTNAME}"
#define IF_MACRO_IPADDRESS	"{IPADDRESS}"

#define IF_MACRO_HOST_HOST_LEN	ZBX_CONST_STRLEN(IF_MACRO_HOST_HOST)
#define IF_MACRO_HOST_NAME_LEN	ZBX_CONST_STRLEN(IF_MACRO_HOST_NAME)
#define IF_MACRO_HOST_IP_LEN	ZBX_CONST_STRLEN(IF_MACRO_HOST_IP)
#define IF_MACRO_HOST_DNS_LEN	ZBX_CONST_STRLEN(IF_MACRO_HOST_DNS)
#define IF_MACRO_HOST_CONN_LEN	ZBX_CONST_STRLEN(IF_MACRO_HOST_CONN)
#define IF_MACRO_HOSTNAME_LEN	ZBX_CONST_STRLEN(IF_MACRO_HOSTNAME)
#define IF_MACRO_IPADDRESS_LEN	ZBX_CONST_STRLEN(IF_MACRO_IPADDRESS)

	zbx_token_t		token;
	int			pos = 0, last_pos = 0;
	char			*str = NULL;
	size_t			str_alloc = 0, str_offset = 0;
	zbx_dc_interface_t	interface;

	if ('\0' == *text)
		return NULL;

	for (; SUCCEED == zbx_token_find(text, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		const char	*value = NULL;

		if (ZBX_TOKEN_MACRO != token.type)
			continue;

		zbx_strncpy_alloc(&str, &str_alloc, &str_offset, text + last_pos, token.loc.l - (size_t)last_pos);

		if (SUCCEED == zbx_strloc_cmp(text, &token.loc, IF_MACRO_HOST_HOST, IF_MACRO_HOST_HOST_LEN) ||
				SUCCEED == zbx_strloc_cmp(text, &token.loc, IF_MACRO_HOSTNAME, IF_MACRO_HOSTNAME_LEN))
		{
			value = dc_host->host;
		}
		else if (SUCCEED == zbx_strloc_cmp(text, &token.loc, IF_MACRO_HOST_NAME, IF_MACRO_HOST_NAME_LEN))
		{
			value = dc_host->name;
		}
		else if (0 != (flags & EXPAND_INTERFACE_MACROS))
		{
			if (SUCCEED == zbx_strloc_cmp(text, &token.loc, IF_MACRO_HOST_IP, IF_MACRO_HOST_IP_LEN) ||
					SUCCEED == zbx_strloc_cmp(text, &token.loc, IF_MACRO_IPADDRESS,
							IF_MACRO_IPADDRESS_LEN))
			{
				if (SUCCEED == zbx_dc_config_get_interface_by_type(&interface, dc_host->hostid,
						INTERFACE_TYPE_AGENT))
				{
					value = interface.ip_orig;
				}
			}
			else if (SUCCEED == zbx_strloc_cmp(text, &token.loc, IF_MACRO_HOST_DNS, IF_MACRO_HOST_DNS_LEN))
			{
				if (SUCCEED == zbx_dc_config_get_interface_by_type(&interface, dc_host->hostid,
						INTERFACE_TYPE_AGENT))
				{
					value = interface.dns_orig;
				}
			}
			else if (SUCCEED == zbx_strloc_cmp(text, &token.loc, IF_MACRO_HOST_CONN,
					IF_MACRO_HOST_CONN_LEN))
			{
				if (SUCCEED == zbx_dc_config_get_interface_by_type(&interface, dc_host->hostid,
						INTERFACE_TYPE_AGENT))
				{
					value = interface.addr;
				}
			}
		}

		if (NULL != value)
		{
			zbx_strcpy_alloc(&str, &str_alloc, &str_offset, value);
		}
		else
		{
			zbx_strncpy_alloc(&str, &str_alloc, &str_offset, text + token.loc.l,
					token.loc.r - token.loc.l + 1);
		}

		pos = (int)token.loc.r;
		last_pos = pos + 1;
	}

	/* if no macros were found then str will be NULL, which should be returned */
	if (NULL != str)
		zbx_strcpy_alloc(&str, &str_alloc, &str_offset, text + last_pos);

	return str;

#undef IF_MACRO_HOSTNAME_LEN
#undef IF_MACRO_HOST_CONN_LEN
#undef IF_MACRO_HOST_DNS_LEN
#undef IF_MACRO_HOST_IP_LEN
#undef IF_MACRO_HOST_NAME_LEN
#undef IF_MACRO_HOST_HOST_LEN
#undef IF_MACRO_IPADDRESS
#undef IF_MACRO_HOSTNAME
#undef IF_MACRO_HOST_CONN
#undef IF_MACRO_HOST_DNS
#undef IF_MACRO_HOST_IP
#undef IF_MACRO_HOST_NAME
#undef IF_MACRO_HOST_HOST
#undef IF_MACRO_HOST
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove interface from SNMP address -> interfaceid index           *
 *                                                                            *
 * Parameters: interface - [IN]                                               *
 *                                                                            *
 ******************************************************************************/
static void	dc_interface_snmpaddrs_remove(ZBX_DC_INTERFACE *interface)
{
	ZBX_DC_INTERFACE_ADDR	*ifaddr, ifaddr_local;
	int			index;

	ifaddr_local.addr = (0 != interface->useip ? interface->ip : interface->dns);

	if ('\0' == *ifaddr_local.addr)
		return;

	if (NULL == (ifaddr = (ZBX_DC_INTERFACE_ADDR *)zbx_hashset_search(&config->interface_snmpaddrs,
			&ifaddr_local)))
	{
		return;
	}

	if (FAIL == (index = zbx_vector_uint64_search(&ifaddr->interfaceids, interface->interfaceid,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		return;
	}

	zbx_vector_uint64_remove_noorder(&ifaddr->interfaceids, index);

	if (0 == ifaddr->interfaceids.values_num)
	{
		dc_strpool_release(ifaddr->addr);
		zbx_vector_uint64_destroy(&ifaddr->interfaceids);
		zbx_hashset_remove_direct(&config->interface_snmpaddrs, ifaddr);
	}
}

static void	dc_interface_snmpaddrs_update(ZBX_DC_INTERFACE *interface)
{
	ZBX_DC_INTERFACE_ADDR	*ifaddr, ifaddr_local;

	ifaddr_local.addr = (0 != interface->useip ? interface->ip : interface->dns);

	if ('\0' != *ifaddr_local.addr)
	{
		if (NULL == (ifaddr = (ZBX_DC_INTERFACE_ADDR *)zbx_hashset_search(&config->interface_snmpaddrs,
				&ifaddr_local)))
		{
			dc_strpool_acquire(ifaddr_local.addr);

			ifaddr = (ZBX_DC_INTERFACE_ADDR *)zbx_hashset_insert(
					&config->interface_snmpaddrs, &ifaddr_local,
					sizeof(ZBX_DC_INTERFACE_ADDR));
			zbx_vector_uint64_create_ext(&ifaddr->interfaceids,
					__config_shmem_malloc_func,
					__config_shmem_realloc_func,
					__config_shmem_free_func);
		}

		zbx_vector_uint64_append(&ifaddr->interfaceids, interface->interfaceid);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: setup SNMP attributes for interface with interfaceid index        *
 *                                                                            *
 * Parameters: interfaceid  - [IN]                                            *
 *             row          - [IN] the row data from DB                       *
 *             modified     - [OUT] 1 if SNMP data were modified, untouched   *
 *                                  otherwise                                 *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_SNMPINTERFACE	*dc_interface_snmp_set(zbx_uint64_t interfaceid, const char **row, int *modified)
{
	int			found;
	ZBX_DC_SNMPINTERFACE	*snmp;
	unsigned char		bulk, version, securitylevel, authprotocol, privprotocol, max_repetitions;

	snmp = (ZBX_DC_SNMPINTERFACE *)DCfind_id(&config->interfaces_snmp, interfaceid, sizeof(ZBX_DC_SNMPINTERFACE),
			&found);

	ZBX_STR2UCHAR(bulk, row[13]);
	ZBX_STR2UCHAR(version, row[12]);
	ZBX_STR2UCHAR(securitylevel, row[16]);
	ZBX_STR2UCHAR(authprotocol, row[19]);
	ZBX_STR2UCHAR(privprotocol, row[20]);
	ZBX_STR2UCHAR(max_repetitions, row[22]);

	if (0 != found)
	{
		if (snmp->bulk != bulk || version != snmp->version || securitylevel != snmp->securitylevel ||
				authprotocol != snmp->authprotocol || privprotocol != snmp->privprotocol ||
				max_repetitions != snmp->max_repetitions)
		{
			*modified = 1;
		}
	}

	snmp->bulk = bulk;
	snmp->version = version;
	snmp->securitylevel = securitylevel;
	snmp->authprotocol = authprotocol;
	snmp->privprotocol = privprotocol;
	snmp->max_repetitions = max_repetitions;

	if (SUCCEED == dc_strpool_replace(found, &snmp->community, row[14]))
		*modified = 1;
	if (SUCCEED == dc_strpool_replace(found, &snmp->securityname, row[15]))
		*modified = 1;
	if (SUCCEED == dc_strpool_replace(found, &snmp->authpassphrase, row[17]))
		*modified = 1;
	if (SUCCEED == dc_strpool_replace(found, &snmp->privpassphrase, row[18]))
		*modified = 1;
	if (SUCCEED == dc_strpool_replace(found, &snmp->contextname, row[21]))
		*modified = 1;

	return snmp;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove interface from SNMP address -> interfaceid index           *
 *                                                                            *
 * Parameters: interfaceid - [IN]                                             *
 *                                                                            *
 ******************************************************************************/
static void	dc_interface_snmp_remove(zbx_uint64_t interfaceid)
{
	ZBX_DC_SNMPINTERFACE	*snmp;

	if (NULL == (snmp = (ZBX_DC_SNMPINTERFACE *)zbx_hashset_search(&config->interfaces_snmp, &interfaceid)))
		return;

	dc_strpool_release(snmp->community);
	dc_strpool_release(snmp->securityname);
	dc_strpool_release(snmp->authpassphrase);
	dc_strpool_release(snmp->privpassphrase);
	dc_strpool_release(snmp->contextname);

	zbx_hashset_remove_direct(&config->interfaces_snmp, snmp);

	return;
}

typedef struct
{
	ZBX_DC_INTERFACE	*interface;
	ZBX_DC_SNMPINTERFACE	*snmp;
	ZBX_DC_HOST		*host;
	char			*ip;
	char			*dns;
	int			modified;
	int			found;
}
zbx_dc_if_update_t;

ZBX_PTR_VECTOR_DECL(dc_if_update_ptr, zbx_dc_if_update_t *)
ZBX_PTR_VECTOR_IMPL(dc_if_update_ptr, zbx_dc_if_update_t *)

static void	dc_if_update_free(zbx_dc_if_update_t *update)
{
	zbx_free(update->ip);
	zbx_free(update->dns);
	zbx_free(update);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve host macros in host interface update                      *
 *                                                                            *
 ******************************************************************************/
static void	dc_if_update_substitute_host_macros(zbx_dc_if_update_t *update, const ZBX_DC_HOST *host, int flags)
{
	char	*addr;

	if (NULL != (addr = dc_expand_host_macros_dyn(update->ip, host, flags)))
	{
		if (SUCCEED == zbx_is_ip(addr))
		{
			zbx_free(update->ip);
			update->ip = addr;
		}
		else
			zbx_free(addr);
	}

	if (NULL != (addr = dc_expand_host_macros_dyn(update->dns, host, flags)))
	{
		if (SUCCEED == zbx_is_ip(addr) || SUCCEED == zbx_validate_hostname(addr))
		{
			zbx_free(update->dns);
			update->dns = addr;
		}
		else
			zbx_free(addr);
	}
}

static void	DCsync_interfaces(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_INTERFACE_HT	*interface_ht, interface_ht_local;
	ZBX_DC_HOST		*host;

	int			found, update_index, ret, i;
	zbx_uint64_t		interfaceid, hostid;
	unsigned char		type, main_, useip;

	zbx_vector_dc_if_update_ptr_t	updates;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_dc_if_update_ptr_create(&updates);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_dc_if_update_t	*update;
		ZBX_DC_INTERFACE	*interface;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(interfaceid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);
		ZBX_STR2UCHAR(type, row[2]);
		ZBX_STR2UCHAR(main_, row[3]);
		ZBX_STR2UCHAR(useip, row[4]);

		/* If there is no host for this interface, skip it. */
		/* This may be possible if the host was added after we synced config for hosts. */
		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
			continue;

		interface = (ZBX_DC_INTERFACE *)DCfind_id(&config->interfaces, interfaceid, sizeof(ZBX_DC_INTERFACE),
				&found);

		update = (zbx_dc_if_update_t *)zbx_malloc(NULL, sizeof(zbx_dc_if_update_t));
		memset(update, 0, sizeof(zbx_dc_if_update_t));

		update->interface = interface;
		update->host = host;

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
				interface_ht = (ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht,
						&interface_ht_local);

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
				interface_ht = (ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht,
						&interface_ht_local);

				if (NULL != interface_ht)
					interface_ht->interface_ptr = interface;
				else
					update_index = 1;
			}

			update->modified = 1;
		}
		else if (interface->useip != useip)
			update->modified = 1;

		interface->hostid = hostid;
		interface->type = type;
		interface->main = main_;
		interface->useip = useip;

		update->ip = zbx_strdup(NULL, row[5]);
		update->dns = zbx_strdup(NULL, row[6]);

		if (SUCCEED == dc_strpool_replace(found, &interface->port, row[7]))
			update->modified = 1;

		if (0 == found)
		{
			interface->errors_from = atoi(row[11]);
			interface->available = (unsigned char)atoi(row[8]);
			interface->disable_until = atoi(row[9]);
			interface->availability_ts = time(NULL);
			interface->reset_availability = 0;
			interface->items_num = 0;
			dc_strpool_replace(found, &interface->error, row[10]);
			interface->version = 0;
		}

		/* update interfaces_ht index using new data, if not done already */

		if (1 == update_index)
		{
			interface_ht_local.hostid = interface->hostid;
			interface_ht_local.type = interface->type;
			interface_ht_local.interface_ptr = interface;
			zbx_hashset_insert(&config->interfaces_ht, &interface_ht_local, sizeof(ZBX_DC_INTERFACE_HT));
		}

		/* update SNMP data  */
		if (INTERFACE_TYPE_SNMP == interface->type)
		{
			if (FAIL == zbx_db_is_null(row[12]))
			{
				update->snmp = dc_interface_snmp_set(interfaceid, (const char **)row,
						&update->modified);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;
		}

		/* first resolve macros for ip and dns fields in main agent interface  */
		/* because other interfaces might reference main interfaces ip and dns */
		/* with {HOST.IP} and {HOST.DNS} macros                                */
		if (1 == interface->main && INTERFACE_TYPE_AGENT == interface->type)
		{
			dc_if_update_substitute_host_macros(update, host, 0);

			if (SUCCEED == dc_strpool_replace(found, &interface->ip, update->ip))
				update->modified = 1;

			if (SUCCEED == dc_strpool_replace(found, &interface->dns, update->dns))
				update->modified = 1;
		}
		update->found = found;
		zbx_vector_dc_if_update_ptr_append(&updates, update);

		if (0 == found)
		{
			/* new interface - add it to a list of host interfaces in 'config->hosts' hashset */

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
	}

	/* resolve macros in other interfaces and handle interface modify status */
	for (i = 0; i < updates.values_num; i++)
	{
		zbx_dc_if_update_t	*update = updates.values[i];

		if (1 != update->interface->main || INTERFACE_TYPE_AGENT != update->interface->type)
		{
			dc_if_update_substitute_host_macros(update, update->host, EXPAND_INTERFACE_MACROS);

			if (SUCCEED == dc_strpool_replace(update->found, &update->interface->ip, update->ip))
				update->modified = 1;

			if (SUCCEED == dc_strpool_replace(update->found, &update->interface->dns, update->dns))
				update->modified = 1;
		}

		if (0 != update->modified)
		{
			if (INTERFACE_TYPE_AGENT == update->interface->type &&
					update->interface->version < ZBX_COMPONENT_VERSION(7, 0, 0))
			{
				update->interface->version = ZBX_COMPONENT_VERSION(7, 0, 0);
			}

			dc_host_update_revision(update->host, revision);

			if (NULL != update->snmp)
			{
				update->snmp->max_succeed = 0;
				update->snmp->min_fail = ZBX_MAX_SNMP_ITEMS + 1;
			}
		}

		if (INTERFACE_TYPE_SNMP == update->interface->type)
			dc_interface_snmpaddrs_update(update->interface);
	}

	/* remove deleted interfaces from buffer */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_DC_INTERFACE	*interface;

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

			dc_host_update_revision(host, revision);
		}

		if (INTERFACE_TYPE_SNMP == interface->type)
		{
			dc_interface_snmpaddrs_remove(interface);
			dc_interface_snmp_remove(interface->interfaceid);
		}

		if (1 == interface->main)
		{
			interface_ht_local.hostid = interface->hostid;
			interface_ht_local.type = interface->type;
			interface_ht = (ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht,
					&interface_ht_local);

			if (NULL != interface_ht && interface == interface_ht->interface_ptr)
			{
				/* see ZBX-4045 for NULL check in the conditional */
				zbx_hashset_remove(&config->interfaces_ht, &interface_ht_local);
			}
		}

		dc_strpool_release(interface->ip);
		dc_strpool_release(interface->dns);
		dc_strpool_release(interface->port);
		dc_strpool_release(interface->error);

		zbx_hashset_remove_direct(&config->interfaces, interface);
	}

	zbx_vector_dc_if_update_ptr_clear_ext(&updates, dc_if_update_free);
	zbx_vector_dc_if_update_ptr_destroy(&updates);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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

	if (FAIL == (index = zbx_vector_uint64_search(&ifitem->itemids, item->itemid,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		return;
	}

	zbx_vector_uint64_remove_noorder(&ifitem->itemids, index);

	if (0 == ifitem->itemids.values_num)
	{
		zbx_vector_uint64_destroy(&ifitem->itemids);
		zbx_hashset_remove_direct(&config->interface_snmpitems, ifitem);
	}
}

static void	dc_masteritem_free(ZBX_DC_MASTERITEM *masteritem)
{
	zbx_hashset_destroy(&masteritem->dep_itemids);
	__config_shmem_free_func(masteritem);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove itemid from master item dependent itemid vector            *
 *                                                                            *
 * Parameters: master_itemid - [IN] the master item identifier                *
 *             dep_itemid    - [IN] the dependent item identifier             *
 *             revision      - [IN] the configuration revision                *
 *                                                                            *
 ******************************************************************************/
static void	dc_masteritem_remove_depitem(zbx_uint64_t master_itemid, zbx_uint64_t dep_itemid, zbx_uint64_t revision)
{
	ZBX_DC_MASTERITEM	*masteritem;
	ZBX_DC_ITEM		*item;

	if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &master_itemid)))
		return;

	if (NULL == (masteritem = item->master_item))
		return;

	zbx_hashset_remove(&masteritem->dep_itemids, &dep_itemid);

	if (0 == masteritem->dep_itemids.num_data)
	{
		dc_masteritem_free(item->master_item);
		item->master_item = NULL;
	}
	else
		masteritem->revision = revision;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update number of items per agent statistics                       *
 *                                                                            *
 * Parameters: interface - [IN/OUT] the interface                             *
 * *           type      - [IN] the item type (ITEM_TYPE_*)                   *
 *             num       - [IN] the number of items (+) added, (-) removed    *
 *                                                                            *
 ******************************************************************************/
static void	dc_interface_update_agent_stats(ZBX_DC_INTERFACE *interface, unsigned char type, int num)
{
	if ((NULL != interface) && ((ITEM_TYPE_ZABBIX == type && INTERFACE_TYPE_AGENT == interface->type) ||
			(ITEM_TYPE_SNMP == type && INTERFACE_TYPE_SNMP == interface->type) ||
			(ITEM_TYPE_JMX == type && INTERFACE_TYPE_JMX == interface->type) ||
			(ITEM_TYPE_IPMI == type && INTERFACE_TYPE_IPMI == interface->type)))
		interface->items_num += num;
}

static unsigned char	*dup_serialized_expression(const unsigned char *src)
{
	zbx_uint32_t	offset, len;
	unsigned char	*dst;

	if (NULL == src || '\0' == *src)
		return NULL;

	offset = zbx_deserialize_uint31_compact(src, &len);
	if (0 == len)
		return NULL;

	dst = (unsigned char *)zbx_malloc(NULL, offset + len);
	memcpy(dst, src, offset + len);

	return dst;
}

static unsigned char	*config_decode_serialized_expression(const char *src)
{
	unsigned char	*dst;
	size_t		data_len, src_len;

	if (NULL == src || '\0' == *src)
		return NULL;

	src_len = strlen(src) * 3 / 4;
	dst = __config_shmem_malloc_func(NULL, src_len);
	zbx_base64_decode(src, (char *)dst, src_len, &data_len);

	return dst;
}

static void	dc_preprocitem_free(ZBX_DC_PREPROCITEM *preprocitem)
{
	zbx_vector_ptr_destroy(&preprocitem->preproc_ops);
	__config_shmem_free_func(preprocitem);
}

static const char	*dc_get_global_item_type_timeout(unsigned char item_type)
{
	const char	*global_timeout;

	switch (item_type)
	{
		case ITEM_TYPE_ZABBIX:
		case ITEM_TYPE_ZABBIX_ACTIVE:
			global_timeout = config->config->item_timeouts.agent;
			break;
		case ITEM_TYPE_SNMP:
			global_timeout = config->config->item_timeouts.snmp;
			break;
		case ITEM_TYPE_SSH:
			global_timeout = config->config->item_timeouts.ssh;
			break;
		case ITEM_TYPE_TELNET:
			global_timeout = config->config->item_timeouts.telnet;
			break;
		case ITEM_TYPE_EXTERNAL:
			global_timeout = config->config->item_timeouts.external;
			break;
		case ITEM_TYPE_DB_MONITOR:
			global_timeout = config->config->item_timeouts.odbc;
			break;
		case ITEM_TYPE_SIMPLE:
			global_timeout = config->config->item_timeouts.simple;
			break;
		case ITEM_TYPE_SCRIPT:
			global_timeout = config->config->item_timeouts.script;
			break;
		case ITEM_TYPE_BROWSER:
			global_timeout = config->config->item_timeouts.browser;
			break;
		case ITEM_TYPE_HTTPAGENT:
			global_timeout = config->config->item_timeouts.http;
			break;
		default:
			global_timeout = "";
			break;
	}

	return global_timeout;
}

char	*zbx_dc_get_global_item_type_timeout(unsigned char item_type)
{
	const char	*cached_tmt;
	char		*tmt;

	RDLOCK_CACHE;

	cached_tmt = dc_get_global_item_type_timeout(item_type);
	tmt = zbx_strdup(NULL, cached_tmt);

	UNLOCK_CACHE;

	return tmt;
}

#define DUMP_HASHMAP(source_hashmap, hash_type, search_key)						\
do													\
{													\
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))						\
	{												\
		zbx_hashset_iter_t      iter;								\
		hash_type		*next;								\
		zbx_hashset_iter_reset(&source_hashmap, &iter);						\
		THIS_SHOULD_NEVER_HAPPEN;								\
		zabbix_log(LOG_LEVEL_DEBUG, "cannot find id in map: " ZBX_FS_UI64, search_key);		\
		while (NULL != (next = (hash_type *)zbx_hashset_iter_next(&iter)))			\
			zabbix_log(LOG_LEVEL_DEBUG, "map key: " ZBX_FS_UI64, next->search_key);		\
	}												\
}													\
while(0)

static zbx_vector_ptr_t	*dc_item_parameters(const ZBX_DC_ITEM *item, unsigned char type)
{
	switch (type)
	{
		case ITEM_TYPE_SCRIPT:
			return &item->itemtype.scriptitem->params;
		case ITEM_TYPE_BROWSER:
			return &item->itemtype.browseritem->params;
		default:
			return NULL;
	}
}

static void	dc_item_type_free(ZBX_DC_ITEM *item, zbx_item_type_t type, zbx_uint64_t revision)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			break;
		case ITEM_TYPE_TRAPPER:
			if (NULL != item->itemtype.trapitem)
			{
				dc_strpool_release(item->itemtype.trapitem->trapper_hosts);
				__config_shmem_free_func(item->itemtype.trapitem);
			}
			break;
		case ITEM_TYPE_SIMPLE:
			dc_strpool_release(item->itemtype.simpleitem->username);
			dc_strpool_release(item->itemtype.simpleitem->password);

			__config_shmem_free_func(item->itemtype.simpleitem);
			break;
		case ITEM_TYPE_INTERNAL:
			break;
		case ITEM_TYPE_ZABBIX_ACTIVE:
			break;
		case ITEM_TYPE_HTTPTEST:
			break;
		case ITEM_TYPE_EXTERNAL:
			break;
		case ITEM_TYPE_DB_MONITOR:
			dc_strpool_release(item->itemtype.dbitem->params);
			dc_strpool_release(item->itemtype.dbitem->username);
			dc_strpool_release(item->itemtype.dbitem->password);

			__config_shmem_free_func(item->itemtype.dbitem);
			break;
		case ITEM_TYPE_IPMI:
			dc_strpool_release(item->itemtype.ipmiitem->ipmi_sensor);

			__config_shmem_free_func(item->itemtype.ipmiitem);
			break;
		case ITEM_TYPE_SSH:
			dc_strpool_release(item->itemtype.sshitem->username);
			dc_strpool_release(item->itemtype.sshitem->password);
			dc_strpool_release(item->itemtype.sshitem->publickey);
			dc_strpool_release(item->itemtype.sshitem->privatekey);
			dc_strpool_release(item->itemtype.sshitem->params);

			__config_shmem_free_func(item->itemtype.sshitem);
			break;
		case ITEM_TYPE_TELNET:
			dc_strpool_release(item->itemtype.telnetitem->username);
			dc_strpool_release(item->itemtype.telnetitem->password);
			dc_strpool_release(item->itemtype.telnetitem->params);

			__config_shmem_free_func(item->itemtype.telnetitem);
			break;
		case ITEM_TYPE_CALCULATED:
			if (NULL != item->itemtype.calcitem->formula_bin)
				__config_shmem_free_func((void *)item->itemtype.calcitem->formula_bin);
			dc_strpool_release(item->itemtype.calcitem->params);

			__config_shmem_free_func(item->itemtype.calcitem);
			break;
		case ITEM_TYPE_JMX:
			dc_strpool_release(item->itemtype.jmxitem->username);
			dc_strpool_release(item->itemtype.jmxitem->password);
			dc_strpool_release(item->itemtype.jmxitem->jmx_endpoint);

			__config_shmem_free_func(item->itemtype.jmxitem);
			break;
		case ITEM_TYPE_SNMPTRAP:
			break;
		case ITEM_TYPE_DEPENDENT:
			dc_masteritem_remove_depitem(item->itemtype.depitem->master_itemid, item->itemid, revision);

			__config_shmem_free_func(item->itemtype.depitem);
			break;
		case ITEM_TYPE_HTTPAGENT:
			dc_strpool_release(item->itemtype.httpitem->url);
			dc_strpool_release(item->itemtype.httpitem->query_fields);
			dc_strpool_release(item->itemtype.httpitem->posts);
			dc_strpool_release(item->itemtype.httpitem->status_codes);
			dc_strpool_release(item->itemtype.httpitem->http_proxy);
			dc_strpool_release(item->itemtype.httpitem->headers);
			dc_strpool_release(item->itemtype.httpitem->ssl_cert_file);
			dc_strpool_release(item->itemtype.httpitem->ssl_key_file);
			dc_strpool_release(item->itemtype.httpitem->ssl_key_password);
			dc_strpool_release(item->itemtype.httpitem->username);
			dc_strpool_release(item->itemtype.httpitem->password);
			dc_strpool_release(item->itemtype.httpitem->trapper_hosts);

			__config_shmem_free_func(item->itemtype.httpitem);
			break;
		case ITEM_TYPE_SNMP:
			/* remove SNMP parameters for non-SNMP item */
			dc_strpool_release(item->itemtype.snmpitem->snmp_oid);

			__config_shmem_free_func(item->itemtype.snmpitem);
			break;
		case ITEM_TYPE_SCRIPT:
			dc_strpool_release(item->itemtype.scriptitem->script);

			__config_shmem_free_func(item->itemtype.scriptitem);
			break;
		case ITEM_TYPE_BROWSER:
			dc_strpool_release(item->itemtype.browseritem->script);

			__config_shmem_free_func(item->itemtype.browseritem);
			break;
	}
}

static void	dc_item_type_update(int found, ZBX_DC_ITEM *item, zbx_item_type_t *old_type,
		zbx_vector_ptr_t *dep_items, char **row, zbx_uint64_t revision)
{
	zbx_vector_ptr_t	parameters_local, *parameters = NULL;

	if (1 == found && *old_type != item->type)
	{
		if (NULL != (parameters = dc_item_parameters(item, *old_type)))
			parameters_local = *parameters;

		dc_item_type_free(item, *old_type, revision);
		found = 0;
	}

	switch ((zbx_item_type_t)item->type)
	{
		case ITEM_TYPE_ZABBIX:
			break;
		case ITEM_TYPE_TRAPPER:
			if ('\0' == *row[9])
			{
				if (1 == found)
				{
					if (NULL != (parameters = dc_item_parameters(item, item->type)))
						parameters_local = *parameters;

					dc_item_type_free(item, item->type, revision);
				}

				item->itemtype.trapitem = NULL;
				break;
			}

			if (1 == found && NULL == item->itemtype.trapitem)
				found = 0;

			if (0 == found)
			{
				item->itemtype.trapitem = (ZBX_DC_TRAPITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_TRAPITEM));
			}

			zbx_trim_str_list(row[9], ',');
			dc_strpool_replace(found, &item->itemtype.trapitem->trapper_hosts, row[9]);
			break;
		case ITEM_TYPE_SIMPLE:
			if (0 == found)
			{
				item->itemtype.simpleitem = (ZBX_DC_SIMPLEITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_SIMPLEITEM));
			}

			dc_strpool_replace(found, &item->itemtype.simpleitem->username, row[14]);
			dc_strpool_replace(found, &item->itemtype.simpleitem->password, row[15]);
			break;
		case ITEM_TYPE_INTERNAL:
			break;
		case ITEM_TYPE_ZABBIX_ACTIVE:
			break;
		case ITEM_TYPE_HTTPTEST:
			break;
		case ITEM_TYPE_EXTERNAL:
			break;
		case ITEM_TYPE_DB_MONITOR:
			if (0 == found)
			{
				item->itemtype.dbitem = (ZBX_DC_DBITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_DBITEM));
			}

			dc_strpool_replace(found, &item->itemtype.dbitem->params, row[11]);
			dc_strpool_replace(found, &item->itemtype.dbitem->username, row[14]);
			dc_strpool_replace(found, &item->itemtype.dbitem->password, row[15]);
			break;
		case ITEM_TYPE_IPMI:
			if (0 == found)
			{
				item->itemtype.ipmiitem = (ZBX_DC_IPMIITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_IPMIITEM));
			}

			dc_strpool_replace(found, &item->itemtype.ipmiitem->ipmi_sensor, row[7]);
			break;
		case ITEM_TYPE_SSH:
			if (0 == found)
			{
				item->itemtype.sshitem = (ZBX_DC_SSHITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_SSHITEM));
			}

			item->itemtype.sshitem->authtype = (unsigned short)atoi(row[13]);
			dc_strpool_replace(found, &item->itemtype.sshitem->username, row[14]);
			dc_strpool_replace(found, &item->itemtype.sshitem->password, row[15]);
			dc_strpool_replace(found, &item->itemtype.sshitem->publickey, row[16]);
			dc_strpool_replace(found, &item->itemtype.sshitem->privatekey, row[17]);
			dc_strpool_replace(found, &item->itemtype.sshitem->params, row[11]);
			break;
		case ITEM_TYPE_TELNET:
			if (0 == found)
			{
				item->itemtype.telnetitem = (ZBX_DC_TELNETITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_TELNETITEM));
			}

			dc_strpool_replace(found, &item->itemtype.telnetitem->username, row[14]);
			dc_strpool_replace(found, &item->itemtype.telnetitem->password, row[15]);
			dc_strpool_replace(found, &item->itemtype.telnetitem->params, row[11]);
			break;
		case ITEM_TYPE_CALCULATED:
			if (0 == found)
			{
				item->itemtype.calcitem = (ZBX_DC_CALCITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_CALCITEM));
			}

			dc_strpool_replace(found, &item->itemtype.calcitem->params, row[11]);

			if (1 == found && NULL != item->itemtype.calcitem->formula_bin)
				__config_shmem_free_func((void *)item->itemtype.calcitem->formula_bin);

			item->itemtype.calcitem->formula_bin = config_decode_serialized_expression(row[49]);
			break;
		case ITEM_TYPE_JMX:
			if (0 == found)
			{
				item->itemtype.jmxitem = (ZBX_DC_JMXITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_JMXITEM));
			}

			dc_strpool_replace(found, &item->itemtype.jmxitem->username, row[14]);
			dc_strpool_replace(found, &item->itemtype.jmxitem->password, row[15]);
			dc_strpool_replace(found, &item->itemtype.jmxitem->jmx_endpoint, row[28]);
			break;
		case ITEM_TYPE_SNMPTRAP:
			break;
		case ITEM_TYPE_DEPENDENT:
			if (0 == found)
			{
				item->itemtype.depitem = (ZBX_DC_DEPENDENTITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_DEPENDENTITEM));
			}

			if (1 == found)
				item->itemtype.depitem->last_master_itemid = item->itemtype.depitem->master_itemid;
			else
				item->itemtype.depitem->last_master_itemid = 0;

			ZBX_DBROW2UINT64(item->itemtype.depitem->master_itemid, row[29]);

			if (item->itemtype.depitem->last_master_itemid != item->itemtype.depitem->master_itemid)
				zbx_vector_ptr_append(dep_items, item);
			break;
		case ITEM_TYPE_HTTPAGENT:
			if (0 == found)
			{
				item->itemtype.httpitem = (ZBX_DC_HTTPITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_HTTPITEM));
			}

			dc_strpool_replace(found, &item->itemtype.httpitem->url, row[31]);
			dc_strpool_replace(found, &item->itemtype.httpitem->query_fields, row[32]);
			dc_strpool_replace(found, &item->itemtype.httpitem->posts, row[33]);
			dc_strpool_replace(found, &item->itemtype.httpitem->status_codes, row[34]);
			item->itemtype.httpitem->follow_redirects = (unsigned char)atoi(row[35]);
			item->itemtype.httpitem->post_type = (unsigned char)atoi(row[36]);
			dc_strpool_replace(found, &item->itemtype.httpitem->http_proxy, row[37]);
			dc_strpool_replace(found, &item->itemtype.httpitem->headers, row[38]);
			item->itemtype.httpitem->retrieve_mode = (unsigned char)atoi(row[39]);
			item->itemtype.httpitem->request_method = (unsigned char)atoi(row[40]);
			item->itemtype.httpitem->output_format = (unsigned char)atoi(row[41]);
			dc_strpool_replace(found, &item->itemtype.httpitem->ssl_cert_file, row[42]);
			dc_strpool_replace(found, &item->itemtype.httpitem->ssl_key_file, row[43]);
			dc_strpool_replace(found, &item->itemtype.httpitem->ssl_key_password, row[44]);
			item->itemtype.httpitem->verify_peer = (unsigned char)atoi(row[45]);
			item->itemtype.httpitem->verify_host = (unsigned char)atoi(row[46]);
			item->itemtype.httpitem->allow_traps = (unsigned char)atoi(row[47]);

			item->itemtype.httpitem->authtype = (unsigned char)atoi(row[13]);
			dc_strpool_replace(found, &item->itemtype.httpitem->username, row[14]);
			dc_strpool_replace(found, &item->itemtype.httpitem->password, row[15]);
			zbx_trim_str_list(row[9], ',');
			dc_strpool_replace(found, &item->itemtype.httpitem->trapper_hosts, row[9]);
			break;
		case ITEM_TYPE_SNMP:
			if (0 == found)
			{
				item->itemtype.snmpitem = (ZBX_DC_SNMPITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_SNMPITEM));
			}

			if (SUCCEED == dc_strpool_replace(found, &item->itemtype.snmpitem->snmp_oid, row[6]))
			{
				if (0 == strncmp(item->itemtype.snmpitem->snmp_oid, "walk[", ZBX_CONST_STRLEN("walk[")))
					item->itemtype.snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_WALK;
				else if (0 == strncmp(item->itemtype.snmpitem->snmp_oid, "get[", ZBX_CONST_STRLEN("get[")))
					item->itemtype.snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_GET;
				else if (NULL != strchr(item->itemtype.snmpitem->snmp_oid, '{'))
					item->itemtype.snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_MACRO;
				else if (NULL != strchr(item->itemtype.snmpitem->snmp_oid, '['))
					item->itemtype.snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_DYNAMIC;
				else
					item->itemtype.snmpitem->snmp_oid_type = ZBX_SNMP_OID_TYPE_NORMAL;
			}
			break;
		case ITEM_TYPE_SCRIPT:
			if (0 == found)
			{
				item->itemtype.scriptitem = (ZBX_DC_SCRIPTITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_SCRIPTITEM));
			}

			dc_strpool_replace(found, &item->itemtype.scriptitem->script, row[11]);

			if (0 == found)
			{
				if (NULL == parameters)
				{
					zbx_vector_ptr_create_ext(&item->itemtype.scriptitem->params,
							__config_shmem_malloc_func, __config_shmem_realloc_func,
							__config_shmem_free_func);
				}
				else
				{
					item->itemtype.scriptitem->params = parameters_local;
					parameters = NULL;
				}
			}
			break;
		case ITEM_TYPE_BROWSER:
			if (0 == found)
			{
				item->itemtype.browseritem = (ZBX_DC_BROWSERITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_BROWSERITEM));
			}

			dc_strpool_replace(found, &item->itemtype.browseritem->script, row[11]);

			if (0 == found)
			{
				if (NULL == parameters)
				{
					zbx_vector_ptr_create_ext(&item->itemtype.browseritem->params,
							__config_shmem_malloc_func, __config_shmem_realloc_func,
							__config_shmem_free_func);
				}
				else
				{
					item->itemtype.browseritem->params = parameters_local;
					parameters = NULL;
				}
			}
			break;
	}

	if (NULL != parameters)
		zbx_vector_ptr_destroy(&parameters_local);
}

static void	dc_item_value_type_free(ZBX_DC_ITEM *item, zbx_item_value_type_t type)
{
	switch (type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			dc_strpool_release(item->itemvaluetype.numitem->units);
			dc_strpool_release(item->itemvaluetype.numitem->trends_period);

			__config_shmem_free_func(item->itemvaluetype.numitem);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (NULL != item->itemvaluetype.logitem)
			{
				dc_strpool_release(item->itemvaluetype.logitem->logtimefmt);
				__config_shmem_free_func(item->itemvaluetype.logitem);
			}
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_NONE:
			break;
	}
}

static void	dc_item_value_type_update(int found, ZBX_DC_ITEM *item, zbx_item_value_type_t *old_value_type,
		char **row)
{
	if (1 == found && *old_value_type != item->value_type)
	{
		dc_item_value_type_free(item, *old_value_type);
		found = 0;
	}

	switch ((zbx_item_value_type_t)item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			if (0 == found)
			{
				item->itemvaluetype.numitem = (ZBX_DC_NUMITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_NUMITEM));
			}

			dc_strpool_replace(found, &item->itemvaluetype.numitem->trends_period, row[23]);
			dc_strpool_replace(found, &item->itemvaluetype.numitem->units, row[26]);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if ('\0' == *row[10])
			{
				if (1 == found)
					dc_item_value_type_free(item, item->value_type);

				item->itemvaluetype.logitem = NULL;
				break;
			}

			if (1 == found && NULL == item->itemvaluetype.logitem)
				found = 0;

			if (0 == found)
			{
				item->itemvaluetype.logitem = (ZBX_DC_LOGITEM *)__config_shmem_malloc_func(NULL,
						sizeof(ZBX_DC_LOGITEM));
			}

			dc_strpool_replace(found, &item->itemvaluetype.logitem->logtimefmt, row[10]);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_NONE:
			break;
	}
}

static void	make_item_unsupported_if_zero_pollers(ZBX_DC_ITEM *item, unsigned char poller_type,
		const char *start_poller_config_name)
{
	if (0 == get_config_forks_cb(poller_type))
	{
		time_t		now = time(NULL);
		zbx_timespec_t	ts = {now, 0};
		char		*msg = zbx_dsprintf(NULL, "%s are disabled in configuration", start_poller_config_name);

		zbx_dc_add_history(item->itemid, item->value_type, 0, NULL, &ts, ITEM_STATE_NOTSUPPORTED, msg);

		zbx_free(msg);
	}
}

static void	process_zero_pollers_items(ZBX_DC_ITEM *item)
{
	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			make_item_unsupported_if_zero_pollers(item, ZBX_PROCESS_TYPE_AGENT_POLLER, "Agent pollers");
			break;
		case ITEM_TYPE_JMX:
			make_item_unsupported_if_zero_pollers(item, ZBX_PROCESS_TYPE_JAVAPOLLER, "Java pollers");
			break;
		case ITEM_TYPE_DB_MONITOR:
			make_item_unsupported_if_zero_pollers(item, ZBX_PROCESS_TYPE_ODBCPOLLER, "ODBC pollers");
			break;
		case ITEM_TYPE_HTTPAGENT:
			make_item_unsupported_if_zero_pollers(item, ZBX_PROCESS_TYPE_HTTPAGENT_POLLER,
					"HTTPAgent pollers");
			break;
		case ITEM_TYPE_SNMP:
			make_item_unsupported_if_zero_pollers(item, ZBX_PROCESS_TYPE_SNMP_POLLER, "SNMP pollers");
			break;
		case ITEM_TYPE_BROWSER:
			make_item_unsupported_if_zero_pollers(item, ZBX_PROCESS_TYPE_BROWSERPOLLER, "Browser pollers");
			break;
		case ITEM_TYPE_SCRIPT:
			make_item_unsupported_if_zero_pollers(item, ZBX_PROCESS_TYPE_POLLER, "pollers");
			break;
		case ITEM_TYPE_IPMI:
			make_item_unsupported_if_zero_pollers(item, ZBX_PROCESS_TYPE_IPMIPOLLER, "IPMI pollers");
			break;
		default:
			return;
	}
}

static void	DCsync_items(zbx_dbsync_t *sync, zbx_uint64_t revision, int flags, zbx_synced_new_config_t synced,
		zbx_vector_uint64_t *deleted_itemids, zbx_vector_dc_item_ptr_t *new_items)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_HOST		*host = NULL;

	ZBX_DC_ITEM		*item = NULL;
	ZBX_DC_TEMPLATE_ITEM	*template_item;
	ZBX_DC_ITEM		*depitem;
	ZBX_DC_INTERFACE_ITEM	*interface_snmpitem;
	ZBX_DC_ITEM_HK		*item_hk, item_hk_local;
	ZBX_DC_INTERFACE	*interface = NULL;
	zbx_item_value_type_t	old_value_type;
	zbx_item_type_t		old_type;

	time_t			now;
	zbx_hashset_uniq_t	uniq = ZBX_HASHSET_UNIQ_FALSE;
	unsigned char		status, type, value_type, old_poller_type, item_flags;
	int			found, ret, i,  old_nextcheck;
	zbx_uint64_t		itemid, hostid, interfaceid, templateid;
	zbx_vector_ptr_t	dep_items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_ptr_create(&dep_items);

	now = time(NULL);

	if (0 == config->items.num_slots)
	{
		int	row_num = zbx_dbsync_get_row_num(sync);

		zbx_hashset_reserve(&config->items, MAX(row_num, 100));
		zbx_hashset_reserve(&config->items_hk, MAX(row_num, 100));
		uniq = ZBX_HASHSET_UNIQ_TRUE;
	}

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);
		ZBX_STR2UCHAR(status, row[2]);
		ZBX_STR2UCHAR(type, row[3]);
		ZBX_DBROW2UINT64(templateid, row[48]);

		if (SUCCEED == zbx_db_is_null(row[12]))
		{
			/* template items should include both template items and prototypes */
			template_item = (ZBX_DC_TEMPLATE_ITEM *)DCfind_id(&config->template_items, itemid,
					sizeof(ZBX_DC_TEMPLATE_ITEM), &found);

			template_item->hostid = hostid;
			template_item->templateid = templateid;

			continue;
		}

		if (NULL == host || host->hostid != hostid)
		{
			if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
				continue;
		}

		item_flags = (unsigned char)atoi(row[18]);

		/* item prototype does not have item_rtdata and shouldn't be present in sync */
		if (item_flags != ZBX_FLAG_DISCOVERY_NORMAL && item_flags != ZBX_FLAG_DISCOVERY_CREATED &&
				item_flags != ZBX_FLAG_DISCOVERY_RULE)
		{
			continue;
		}

		item = (ZBX_DC_ITEM *)DCfind_id_ext(&config->items, itemid, sizeof(ZBX_DC_ITEM), &found, uniq);

		/* template item */
		ZBX_DBROW2UINT64(item->templateid, row[48]);

		if (0 != found && ITEM_TYPE_SNMPTRAP == item->type)
			dc_interface_snmpitems_remove(item);

		/* see whether we should and can update items_hk index at this point */

		if (0 == found || 0 != strcmp(item->key, row[5]))
		{
			if (1 == found)
			{
				item_hk_local.hostid = item->hostid;
				item_hk_local.key = item->key;

				if (NULL == (item_hk = (ZBX_DC_ITEM_HK *)zbx_hashset_search(&config->items_hk,
						&item_hk_local)))
				{
					/* item keys should be unique for items within a host, otherwise items with  */
					/* same key share index and removal of last added item already cleared index */
					THIS_SHOULD_NEVER_HAPPEN;
				}
				else if (item == item_hk->item_ptr)
				{
					dc_strpool_release(item_hk->key);
					zbx_hashset_remove_direct(&config->items_hk, item_hk);
				}
			}

			if (SUCCEED == dc_strpool_replace(found, &item->key, row[5]))
				flags |= ZBX_ITEM_KEY_CHANGED;

			item_hk_local.hostid = hostid;
			item_hk_local.key = item->key;
			item_hk_local.item_ptr = NULL;

			item_hk = (ZBX_DC_ITEM_HK *)zbx_hashset_insert(&config->items_hk, &item_hk_local,
					sizeof(ZBX_DC_ITEM_HK));

			if (NULL == item_hk->item_ptr)
				dc_strpool_acquire(item->key);
			item_hk->item_ptr = item;
		}
		else
		{
			if (SUCCEED == dc_strpool_replace(found, &item->key, row[5]))
				flags |= ZBX_ITEM_KEY_CHANGED;
		}

		/* store new information in item structure */

		item->hostid = hostid;
		item->flags = item_flags;
		ZBX_DBROW2UINT64(interfaceid, row[19]);

		dc_strpool_replace(found, &item->history_period, row[22]);

		ZBX_STR2UCHAR(item->inventory_link, row[24]);
		ZBX_DBROW2UINT64(item->valuemapid, row[25]);

		if (0 != (ZBX_FLAG_DISCOVERY_RULE & item->flags))
			value_type = ITEM_VALUE_TYPE_TEXT;
		else
			ZBX_STR2UCHAR(value_type, row[4]);

		if (0 == found)
		{
			ZBX_DC_ITEM_REF	ref_local = {.item = item};

			item->triggers = NULL;
			item->update_triggers = 0;
			item->nextcheck = 0;
			item->state = (unsigned char)atoi(row[12]);
			ZBX_STR2UINT64(item->lastlogsize, row[20]);
			item->mtime = atoi(row[21]);
			dc_strpool_replace(found, &item->error, row[27]);
			item->data_expected_from = now;
			item->location = ZBX_LOC_NOWHERE;
			item->poller_type = ZBX_NO_POLLER;
			item->queue_priority = ZBX_QUEUE_PRIORITY_NORMAL;
			item->delay_ex = NULL;

			if (ZBX_SYNCED_NEW_CONFIG_YES == synced && 0 == host->proxyid)
				flags |= ZBX_ITEM_NEW;

			zbx_vector_dc_item_tag_create_ext(&item->tags, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);

			zbx_hashset_insert_ext(&host->items, &ref_local, sizeof(ref_local), 0, sizeof(ref_local), uniq);

			item->preproc_item = NULL;
			item->master_item = NULL;

			if (NULL != new_items)
				zbx_vector_dc_item_ptr_append(new_items, item);
		}
		else
		{
			if (item->type != type)
				flags |= ZBX_ITEM_TYPE_CHANGED;

			if (ITEM_STATUS_ACTIVE == status && ITEM_STATUS_ACTIVE != item->status)
				item->data_expected_from = now;

			if (ITEM_STATUS_ACTIVE == item->status)
			{
				ZBX_DC_INTERFACE	*interface_old;

				interface_old = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces,
						&item->interfaceid);
				dc_interface_update_agent_stats(interface_old, item->type, -1);
			}
		}

		item->revision = revision;
		dc_host_update_revision(host, revision);

		if (ITEM_STATUS_ACTIVE == status)
		{
			if (NULL == interface || interface->interfaceid != interfaceid)
				interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid);
			dc_interface_update_agent_stats(interface, type, 1);
		}

		if (1 == found)
		{
			old_type = item->type;
			old_value_type = item->value_type;
		}

		item->type = type;
		item->status = status;
		item->value_type = value_type;
		item->interfaceid = interfaceid;

		dc_item_value_type_update(found, item, &old_value_type, row);
		dc_item_type_update(found, item, &old_type, &dep_items, row, revision);

		/* process item intervals and update item nextcheck */

		if (SUCCEED == dc_strpool_replace(found, &item->delay, row[8]))
		{
			flags |= ZBX_ITEM_DELAY_CHANGED;

			/* reset expanded delay if raw value was changed */
			if (NULL != item->delay_ex)
			{
				dc_strpool_release(item->delay_ex);
				item->delay_ex = NULL;
			}
		}

		dc_strpool_replace(found, &item->timeout, row[30]);

		/* SNMP trap items for current server/proxy */

		if (ITEM_TYPE_SNMPTRAP == item->type && 0 == host->proxyid)
		{
			interface_snmpitem = (ZBX_DC_INTERFACE_ITEM *)DCfind_id(&config->interface_snmpitems,
					item->interfaceid, sizeof(ZBX_DC_INTERFACE_ITEM), &found);

			if (0 == found)
			{
				zbx_vector_uint64_create_ext(&interface_snmpitem->itemids,
						__config_shmem_malloc_func,
						__config_shmem_realloc_func,
						__config_shmem_free_func);
			}

			zbx_vector_uint64_append(&interface_snmpitem->itemids, itemid);
		}

		/* it is crucial to update type specific (snmpitems, ipmiitems, etc.) before */
		/* attempting to requeue an item because type specific properties are used to arrange items in queues */

		old_poller_type = item->poller_type;
		old_nextcheck = item->nextcheck;

		if (ITEM_STATUS_ACTIVE == item->status && HOST_STATUS_MONITORED == host->status)
		{
			DCitem_poller_type_update(item, host, flags);

			if (SUCCEED == zbx_is_counted_in_item_queue(item->type, item->key))
			{
				char	*error = NULL;

				if ((0 != (flags & ZBX_ITEM_TYPE_CHANGED)) || (0 != (flags & ZBX_ITEM_NEW)))
					process_zero_pollers_items(item);

				if (FAIL == DCitem_nextcheck_update(item, interface, flags, now, &error))
				{
					zbx_timespec_t	ts = {now, 0};

					/* Usual way for an item to become not supported is to receive an error     */
					/* instead of value. Item state and error will be updated by history syncer */
					/* during history sync following a regular procedure with item update in    */
					/* database and config cache, logging etc. There is no need to set          */
					/* ITEM_STATE_NOTSUPPORTED here.                                            */

					if (0 == host->proxyid)
					{
						zbx_dc_add_history(item->itemid, item->value_type, 0, NULL, &ts,
								ITEM_STATE_NOTSUPPORTED, error);
					}
					zbx_free(error);
				}
			}
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
		depitem = (ZBX_DC_ITEM *)dep_items.values[i];
		dc_masteritem_remove_depitem(depitem->itemtype.depitem->last_master_itemid, depitem->itemid, revision);

		if (NULL == item || item->itemid != depitem->itemtype.depitem->master_itemid)
		{
			if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items,
					&depitem->itemtype.depitem->master_itemid)))
			{
				continue;
			}
		}
		if (NULL == item->master_item)
		{
			item->master_item = (ZBX_DC_MASTERITEM *)__config_shmem_malloc_func(NULL,
					sizeof(ZBX_DC_MASTERITEM));

			zbx_hashset_create_ext(&item->master_item->dep_itemids, 3, ZBX_DEFAULT_UINT64_HASH_FUNC,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);
		}

		item->master_item->revision = revision;

		zbx_hashset_insert_ext(&item->master_item->dep_itemids, &depitem->itemid, sizeof(depitem->itemid), 0,
				sizeof(depitem->itemid), uniq);

		/* Update master item revision for preprocessing configuration refresh.     */
		/* No need to update host revision as it was already updated when dependent */
		/* item revision was updated.                                               */
		item->revision = revision;
	}

	zbx_vector_ptr_destroy(&dep_items);

	if (NULL != deleted_itemids)
		zbx_vector_uint64_reserve(deleted_itemids, sync->remove_num);

	/* remove deleted items from cache */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL != (template_item = (ZBX_DC_TEMPLATE_ITEM *)zbx_hashset_search(&config->template_items,
				&rowid)))
		{
			zbx_hashset_remove_direct(&config->template_items, template_item);
		}

		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &rowid)))
			continue;

		if (NULL != deleted_itemids)
			zbx_vector_uint64_append(deleted_itemids, rowid);

		if (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &item->hostid)))
		{
			ZBX_DC_ITEM_REF	ref_local = {.item = item};

			zbx_hashset_remove(&host->items, &ref_local);
			dc_host_update_revision(host, revision);
		}

		if (ITEM_STATUS_ACTIVE == item->status)
		{
			interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &item->interfaceid);
			dc_interface_update_agent_stats(interface, item->type, -1);
		}

		itemid = item->itemid;

		if (ITEM_TYPE_SNMPTRAP == item->type)
			dc_interface_snmpitems_remove(item);

		dc_item_value_type_free(item, item->value_type);

		zbx_vector_ptr_t	*parameters;

		if (NULL != (parameters = dc_item_parameters(item, item->type)))
			zbx_vector_ptr_destroy(parameters);

		dc_item_type_free(item, item->type, revision);

		/* items */

		item_hk_local.hostid = item->hostid;
		item_hk_local.key = item->key;

		if (NULL == (item_hk = (ZBX_DC_ITEM_HK *)zbx_hashset_search(&config->items_hk, &item_hk_local)))
		{
			/* item keys should be unique for items within a host, otherwise items with  */
			/* same key share index and removal of last added item already cleared index */
			THIS_SHOULD_NEVER_HAPPEN;

			if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
			{
				zbx_hashset_iter_t	iter;
				ZBX_DC_ITEM_HK		*next;
				zbx_hashset_iter_reset(&config->items_hk, &iter);
				zabbix_log(LOG_LEVEL_DEBUG, "cannot find id in map, hostid: " ZBX_FS_UI64 ", key: %s",
						item->hostid, item->key);
				while (NULL != (next = (ZBX_DC_ITEM_HK *)zbx_hashset_iter_next(&iter)))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "map hostid: " ZBX_FS_UI64 ", key: %s",
							next->hostid, next->key);
				}
			}
		}
		else if (item == item_hk->item_ptr)
		{
			dc_strpool_release(item_hk->key);
			zbx_hashset_remove_direct(&config->items_hk, item_hk);
		}

		if (ZBX_LOC_QUEUE == item->location)
			zbx_binary_heap_remove_direct(&config->queues[item->poller_type], item->itemid);

		dc_strpool_release(item->key);
		dc_strpool_release(item->error);
		dc_strpool_release(item->delay);
		dc_strpool_release(item->history_period);

		if (NULL != item->delay_ex)
			dc_strpool_release(item->delay_ex);

		if (NULL != item->triggers)
			config->items.mem_free_func(item->triggers);

		for (i = 0; i < item->tags.values_num; i++)
		{
			dc_strpool_release(item->tags.values[i].tag);
			dc_strpool_release(item->tags.values[i].value);
		}

		zbx_vector_dc_item_tag_destroy(&item->tags);

		if (NULL != item->preproc_item)
			dc_preprocitem_free(item->preproc_item);

		if (NULL != item->master_item)
			dc_masteritem_free(item->master_item);

		zbx_hashset_remove_direct(&config->items, item);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
#undef DUMP_HASHMAP

static void	DCsync_item_discovery(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid, itemid;
	unsigned char		tag;
	zbx_hashset_uniq_t	uniq = ZBX_HASHSET_UNIQ_FALSE;
	int			ret, found;
	ZBX_DC_ITEM_DISCOVERY	*item_discovery;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	if (0 == config->item_discovery.num_slots)
	{
		int	row_num = zbx_dbsync_get_row_num(sync);

		zbx_hashset_reserve(&config->item_discovery, MAX(row_num, 100));
		uniq = ZBX_HASHSET_UNIQ_TRUE;
	}

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[0]);
		item_discovery = (ZBX_DC_ITEM_DISCOVERY *)DCfind_id_ext(&config->item_discovery, itemid,
				sizeof(ZBX_DC_ITEM_DISCOVERY), &found, uniq);

		/* LLD item prototype */
		ZBX_STR2UINT64(item_discovery->parent_itemid, row[1]);
	}

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (item_discovery = (ZBX_DC_ITEM_DISCOVERY *)zbx_hashset_search(&config->item_discovery,
				&rowid)))
		{
			continue;
		}

		zbx_hashset_remove_direct(&config->item_discovery, item_discovery);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	DCsync_triggers(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_TRIGGER		*trigger;

	zbx_hashset_uniq_t	uniq = ZBX_HASHSET_UNIQ_FALSE;
	int			found, ret;
	zbx_uint64_t		triggerid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	if (0 == config->triggers.num_slots)
	{
		int	row_num = zbx_dbsync_get_row_num(sync);

		zbx_hashset_reserve(&config->triggers, MAX(row_num, 100));
		uniq = ZBX_HASHSET_UNIQ_TRUE;
	}

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(triggerid, row[0]);

		trigger = (ZBX_DC_TRIGGER *)DCfind_id_ext(&config->triggers, triggerid, sizeof(ZBX_DC_TRIGGER),
				&found, uniq);

		/* store new information in trigger structure */

		ZBX_STR2UCHAR(trigger->flags, row[19]);

		if (ZBX_FLAG_DISCOVERY_PROTOTYPE == trigger->flags)
			continue;

		dc_strpool_replace(found, &trigger->description, row[1]);
		dc_strpool_replace(found, &trigger->expression, row[2]);
		dc_strpool_replace(found, &trigger->recovery_expression, row[11]);
		dc_strpool_replace(found, &trigger->correlation_tag, row[13]);
		dc_strpool_replace(found, &trigger->opdata, row[14]);
		dc_strpool_replace(found, &trigger->event_name, row[15]);
		ZBX_STR2UCHAR(trigger->priority, row[4]);
		ZBX_STR2UCHAR(trigger->type, row[5]);
		ZBX_STR2UCHAR(trigger->status, row[9]);
		ZBX_STR2UCHAR(trigger->recovery_mode, row[10]);
		ZBX_STR2UCHAR(trigger->correlation_mode, row[12]);

		if (0 == found)
		{
			dc_strpool_replace(found, &trigger->error, row[3]);
			ZBX_STR2UCHAR(trigger->value, row[6]);
			ZBX_STR2UCHAR(trigger->state, row[7]);
			trigger->lastchange = atoi(row[8]);
			trigger->locked = 0;
			trigger->timer_revision = 0;

			zbx_vector_ptr_create_ext(&trigger->tags, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);
			trigger->topoindex = 1;
			trigger->itemids = NULL;
		}
		else
		{
			if (NULL != trigger->expression_bin)
				__config_shmem_free_func((void *)trigger->expression_bin);
			if (NULL != trigger->recovery_expression_bin)
				__config_shmem_free_func((void *)trigger->recovery_expression_bin);
		}

		trigger->expression_bin = config_decode_serialized_expression(row[16]);
		trigger->recovery_expression_bin = config_decode_serialized_expression(row[17]);
		trigger->timer = atoi(row[18]);
		trigger->revision = revision;
	}

	/* remove deleted triggers from buffer */
	if (SUCCEED == ret)
	{
		ZBX_DC_ITEM	*item;
		zbx_uint64_t	*itemid;

		for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
		{
			if (NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &rowid)))
				continue;

			if (ZBX_FLAG_DISCOVERY_PROTOTYPE != trigger->flags)
			{
				/* force trigger list update for items used in removed trigger */
				if (NULL != trigger->itemids)
				{
					for (itemid = trigger->itemids; 0 != *itemid; itemid++)
					{
						if (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items,
								itemid)))
						{
							dc_item_reset_triggers(item, trigger);
						}
					}
				}

				dc_strpool_release(trigger->description);
				dc_strpool_release(trigger->expression);
				dc_strpool_release(trigger->recovery_expression);
				dc_strpool_release(trigger->error);
				dc_strpool_release(trigger->correlation_tag);
				dc_strpool_release(trigger->opdata);
				dc_strpool_release(trigger->event_name);

				zbx_vector_ptr_destroy(&trigger->tags);

				if (NULL != trigger->expression_bin)
					__config_shmem_free_func((void *)trigger->expression_bin);
				if (NULL != trigger->recovery_expression_bin)
					__config_shmem_free_func((void *)trigger->recovery_expression_bin);

				if (NULL != trigger->itemids)
					__config_shmem_free_func((void *)trigger->itemids);
			}

			zbx_hashset_remove_direct(&config->triggers, trigger);
		}
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	DCconfig_sort_triggers_topologically(void);

/******************************************************************************
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
 * Purpose: initializes trigger dependency list                               *
 *                                                                            *
 ******************************************************************************/
static void	dc_trigger_deplist_init(ZBX_DC_TRIGGER_DEPLIST *trigdep, ZBX_DC_TRIGGER *trigger)
{
	trigdep->refcount = 1;
	trigdep->trigger = trigger;
	zbx_vector_ptr_create_ext(&trigdep->dependencies, __config_shmem_malloc_func, __config_shmem_realloc_func,
			__config_shmem_free_func);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resets trigger dependency list to release memory allocated by     *
 *          dependencies vector                                               *
 *                                                                            *
 ******************************************************************************/
static void	dc_trigger_deplist_reset(ZBX_DC_TRIGGER_DEPLIST *trigdep)
{
	zbx_vector_ptr_destroy(&trigdep->dependencies);
	zbx_vector_ptr_create_ext(&trigdep->dependencies, __config_shmem_malloc_func, __config_shmem_realloc_func,
			__config_shmem_free_func);
}

static void	DCsync_trigdeps(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_TRIGGER_DEPLIST	*trigdep_down, *trigdep_up;

	int			found, index, ret;
	zbx_uint64_t		triggerid_down, triggerid_up;
	ZBX_DC_TRIGGER		*trigger_up, *trigger_down;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

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

		trigdep_down = (ZBX_DC_TRIGGER_DEPLIST *)DCfind_id(&config->trigdeps, triggerid_down,
				sizeof(ZBX_DC_TRIGGER_DEPLIST), &found);

		if (0 == found)
			dc_trigger_deplist_init(trigdep_down, trigger_down);
		else
			trigdep_down->refcount++;

		trigdep_up = (ZBX_DC_TRIGGER_DEPLIST *)DCfind_id(&config->trigdeps, triggerid_up,
				sizeof(ZBX_DC_TRIGGER_DEPLIST), &found);

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

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	dc_function_calculate_trends_nextcheck(const zbx_dc_um_handle_t *um_handle,
		const zbx_trigger_timer_t *timer, zbx_uint64_t seed, time_t *nextcheck, char **error)
{
	unsigned int	offsets[ZBX_TIME_UNIT_COUNT] = {0, 0, 0, SEC_PER_MIN * 10,
			SEC_PER_HOUR + SEC_PER_MIN * 10, SEC_PER_HOUR + SEC_PER_MIN * 10,
			SEC_PER_HOUR + SEC_PER_MIN * 10, SEC_PER_HOUR + SEC_PER_MIN * 10,
			SEC_PER_HOUR + SEC_PER_MIN * 10};
	unsigned int	periods[ZBX_TIME_UNIT_COUNT] = {0, 0, 0, SEC_PER_MIN * 10, SEC_PER_HOUR,
			SEC_PER_HOUR * 11, SEC_PER_DAY - SEC_PER_HOUR, SEC_PER_DAY - SEC_PER_HOUR,
			SEC_PER_DAY - SEC_PER_HOUR};

	time_t		next;
	struct tm	tm;
	char		*param, *period_shift;
	int		ret = FAIL;
	zbx_time_unit_t trend_base;

	if (NULL == (param = zbx_function_get_param_dyn(timer->parameter, 1)))
	{
		*error = zbx_strdup(NULL, "no first parameter");
		return FAIL;
	}

	if (NULL != um_handle)
	{
		(void)zbx_dc_expand_user_and_func_macros(um_handle, &param, &timer->hostid, 1, NULL);
	}
	else
	{
		char	*tmp;

		tmp = dc_expand_user_and_func_macros_dyn(param, &timer->hostid, 1, ZBX_MACRO_ENV_NONSECURE);
		zbx_free(param);
		param = tmp;
	}

	if (FAIL == zbx_trends_parse_base(param, &trend_base, error))
		goto out;

	if (trend_base < ZBX_TIME_UNIT_HOUR)
	{
		*error = zbx_strdup(NULL, "invalid first parameter");
		goto out;
	}

	localtime_r(&timer->lastcheck, &tm);

	if (ZBX_TIME_UNIT_HOUR == trend_base)
	{
		zbx_tm_round_up(&tm, trend_base);

		if (-1 == (*nextcheck = mktime(&tm)))
		{
			*error = zbx_strdup(NULL, zbx_strerror(errno));
			goto out;
		}

		ret = SUCCEED;
		goto out;
	}

	if (NULL == (period_shift = strchr(param, ':')))
	{
		*error = zbx_strdup(NULL, "invalid first parameter");
		goto out;

	}

	period_shift++;
	next = timer->lastcheck;

	while (SUCCEED == zbx_trends_parse_nextcheck(next, period_shift, nextcheck, error))
	{
		if (*nextcheck > timer->lastcheck)
		{
			ret = SUCCEED;
			break;
		}

		zbx_tm_add(&tm, 1, trend_base);
		if (-1 == (next = mktime(&tm)))
		{
			*error = zbx_strdup(*error, zbx_strerror(errno));
			break;
		}
	}
out:
	if (SUCCEED == ret)
		*nextcheck += (time_t)(offsets[trend_base] + seed % periods[trend_base]);

	zbx_free(param);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate nextcheck for trigger timer                             *
 *                                                                            *
 * Parameters: um_handle - [IN] user macro cache handle (optional)            *
 *             timer     - [IN] the timer                                     *
 *             from      - [IN] the time from which the nextcheck must be     *
 *                              calculated                                    *
 *             seed      - [IN] timer seed to spread out the nextchecks       *
 *                                                                            *
 * Comments: When called within configuration cache lock pass NULL um_handle  *
 *           to directly use user macro cache                                 *
 *                                                                            *
 ******************************************************************************/
static time_t	dc_function_calculate_nextcheck(const zbx_dc_um_handle_t *um_handle, const zbx_trigger_timer_t *timer,
		time_t from, zbx_uint64_t seed)
{
#define ZBX_TRIGGER_TIMER_DELAY	30
	if (ZBX_TRIGGER_TIMER_FUNCTION_TIME == timer->type || ZBX_TRIGGER_TIMER_TRIGGER == timer->type)
	{
		int	nextcheck;

		nextcheck = ZBX_TRIGGER_TIMER_DELAY * (int)(from / (time_t)ZBX_TRIGGER_TIMER_DELAY) +
				(int)(seed % (zbx_uint64_t)ZBX_TRIGGER_TIMER_DELAY);

		while (nextcheck <= from)
			nextcheck += ZBX_TRIGGER_TIMER_DELAY;

		return nextcheck;
	}
	else if (ZBX_TRIGGER_TIMER_FUNCTION_TREND == timer->type)
	{
		time_t	nextcheck;
		char	*error = NULL;

		if (SUCCEED != dc_function_calculate_trends_nextcheck(um_handle, timer, seed, &nextcheck, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot calculate trend function \"" ZBX_FS_UI64
					"\" schedule: %s", timer->objectid, error);
			zbx_free(error);

			return 0;
		}

		return nextcheck;
	}

	THIS_SHOULD_NEVER_HAPPEN;

	return 0;
#undef ZBX_TRIGGER_TIMER_DELAY
}

/******************************************************************************
 *                                                                            *
 * Purpose: create trigger timer based on the trend function                  *
 *                                                                            *
 * Return value:  Created timer or NULL in the case of error.                 *
 *                                                                            *
 ******************************************************************************/
static zbx_trigger_timer_t	*dc_trigger_function_timer_create(ZBX_DC_FUNCTION *function, int now)
{
	zbx_trigger_timer_t	*timer;
	zbx_uint32_t		type;
	ZBX_DC_ITEM		*item;

	if (ZBX_FUNCTION_TYPE_TRENDS == function->type)
	{
		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &function->itemid)))
			return NULL;

		type = ZBX_TRIGGER_TIMER_FUNCTION_TREND;
	}
	else
	{
		type = ZBX_TRIGGER_TIMER_FUNCTION_TIME;
	}

	timer = (zbx_trigger_timer_t *)__config_shmem_malloc_func(NULL, sizeof(zbx_trigger_timer_t));

	timer->objectid = function->functionid;
	timer->triggerid = function->triggerid;
	timer->revision = function->revision;
	timer->lock = 0;
	timer->type = type;
	timer->lastcheck = (time_t)now;

	function->timer_revision = function->revision;

	if (ZBX_FUNCTION_TYPE_TRENDS == function->type)
	{
		dc_strpool_replace(0, &timer->parameter, function->parameter);
		timer->hostid = item->hostid;
	}
	else
	{
		timer->parameter = NULL;
		timer->hostid = 0;
	}

	return timer;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create trigger timer based on the specified trigger               *
 *                                                                            *
 * Return value:  Created timer or NULL in the case of error.                 *
 *                                                                            *
 ******************************************************************************/
static zbx_trigger_timer_t	*dc_trigger_timer_create(ZBX_DC_TRIGGER *trigger)
{
	zbx_trigger_timer_t	*timer;

	timer = (zbx_trigger_timer_t *)__config_shmem_malloc_func(NULL, sizeof(zbx_trigger_timer_t));
	timer->type = ZBX_TRIGGER_TIMER_TRIGGER;
	timer->objectid = trigger->triggerid;
	timer->triggerid = trigger->triggerid;
	timer->revision = trigger->revision;
	timer->lock = 0;
	timer->parameter = NULL;

	trigger->timer_revision = trigger->revision;

	return timer;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free trigger timer                                                *
 *                                                                            *
 ******************************************************************************/
static void	dc_trigger_timer_free(zbx_trigger_timer_t *timer)
{
	if (NULL != timer->parameter)
		dc_strpool_release(timer->parameter);

	__config_shmem_free_func(timer);
}

/******************************************************************************
 *                                                                            *
 * Purpose: schedule trigger timer to be executed at the specified time       *
 *                                                                            *
 * Parameter: timer   - [IN] the timer to schedule                            *
 *            now     - [IN] current time                                     *
 *            eval_ts - [IN] the history snapshot time, by default (NULL)     *
 *                           execution time will be used.                     *
 *            exec_ts - [IN] the tiemer execution time                        *
 *                                                                            *
 ******************************************************************************/
static void	dc_schedule_trigger_timer(zbx_trigger_timer_t *timer, int now, const zbx_timespec_t *eval_ts,
		const zbx_timespec_t *exec_ts)
{
	zbx_binary_heap_elem_t	elem;

	if (NULL == eval_ts)
		timer->eval_ts = *exec_ts;
	else
		timer->eval_ts = *eval_ts;

	timer->exec_ts = *exec_ts;
	timer->check_ts.sec = MIN(exec_ts->sec, now + ZBX_TRIGGER_POLL_INTERVAL);
	timer->check_ts.ns = 0;

	elem.key = 0;
	elem.data = (void *)timer;
	zbx_binary_heap_insert(&config->trigger_queue, &elem);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set timer schedule and evaluation times based on functions and    *
 *          old trend function queue                                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_schedule_trigger_timers(zbx_hashset_t *trend_queue, int now)
{
	ZBX_DC_FUNCTION		*function;
	ZBX_DC_TRIGGER		*trigger;
	zbx_trigger_timer_t	*timer, *old;
	zbx_timespec_t		ts;
	zbx_hashset_iter_t	iter;

	ts.ns = 0;

	zbx_hashset_iter_reset(&config->functions, &iter);
	while (NULL != (function = (ZBX_DC_FUNCTION *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_FUNCTION_TYPE_TIMER != function->type && ZBX_FUNCTION_TYPE_TRENDS != function->type)
			continue;

		if (function->timer_revision == function->revision)
			continue;

		if (NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &function->triggerid)))
			continue;

		if (ZBX_FLAG_DISCOVERY_PROTOTYPE == trigger->flags)
			continue;

		if (TRIGGER_STATUS_ENABLED != trigger->status || TRIGGER_FUNCTIONAL_TRUE != trigger->functional)
			continue;

		if (NULL == (timer = dc_trigger_function_timer_create(function, now)))
			continue;

		if (NULL != trend_queue && NULL != (old = (zbx_trigger_timer_t *)zbx_hashset_search(trend_queue,
				&timer->objectid)) && old->eval_ts.sec < now + 10 * SEC_PER_MIN)
		{
			/* if the trigger was scheduled during next 10 minutes         */
			/* schedule its evaluation later to reduce server startup load */
			if (old->eval_ts.sec < now + 10 * SEC_PER_MIN)
				ts.sec = now + 10 * SEC_PER_MIN + (int)(timer->triggerid % (10 * SEC_PER_MIN));
			else
				ts.sec = old->eval_ts.sec;

			dc_schedule_trigger_timer(timer, now, &old->eval_ts, &ts);
		}
		else
		{
			if (0 == (ts.sec = (int)dc_function_calculate_nextcheck(NULL, timer, now, timer->triggerid)))
			{
				dc_trigger_timer_free(timer);
				function->timer_revision = 0;
			}
			else
				dc_schedule_trigger_timer(timer, now, NULL, &ts);
		}
	}

	zbx_hashset_iter_reset(&config->triggers, &iter);
	while (NULL != (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_FLAG_DISCOVERY_PROTOTYPE == trigger->flags)
			continue;

		if (NULL == trigger->itemids)
			continue;

		if (ZBX_TRIGGER_TIMER_DEFAULT == trigger->timer)
			continue;

		if (trigger->timer_revision == trigger->revision)
			continue;

		if (NULL == (timer = dc_trigger_timer_create(trigger)))
			continue;

		if (0 == (ts.sec = (int)dc_function_calculate_nextcheck(NULL, timer, now, timer->triggerid)))
		{
			dc_trigger_timer_free(timer);
			trigger->timer_revision = 0;
		}
		else
			dc_schedule_trigger_timer(timer, now, NULL, &ts);
	}
}

static void	DCsync_functions(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_ITEM		*item;
	ZBX_DC_FUNCTION		*function;

	zbx_hashset_uniq_t	uniq = ZBX_HASHSET_UNIQ_FALSE;
	int			found, ret;
	zbx_uint64_t		itemid, functionid, triggerid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	if (0 == config->functions.num_slots)
	{
		int	row_num = zbx_dbsync_get_row_num(sync);

		zbx_hashset_reserve(&config->functions, MAX(row_num, 100));
		uniq = ZBX_HASHSET_UNIQ_TRUE;
	}

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[1]);
		ZBX_STR2UINT64(functionid, row[0]);
		ZBX_STR2UINT64(triggerid, row[4]);

		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
			continue;

		/* process function information */

		function = (ZBX_DC_FUNCTION *)DCfind_id_ext(&config->functions, functionid, sizeof(ZBX_DC_FUNCTION),
				&found, uniq);

		if (1 == found)
		{
			if (function->itemid != itemid)
			{
				ZBX_DC_ITEM	*item_last;

				if (NULL != (item_last = zbx_hashset_search(&config->items, &function->itemid)))
					dc_item_reset_triggers(item_last, NULL);
			}
		}
		else
			function->timer_revision = 0;

		function->triggerid = triggerid;
		function->itemid = itemid;
		dc_strpool_replace(found, &function->function, row[2]);
		dc_strpool_replace(found, &function->parameter, row[3]);

		function->type = zbx_get_function_type(function->function);
		function->revision = revision;

		dc_item_reset_triggers(item, NULL);
	}

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions, &rowid)))
			continue;

		if (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &function->itemid)))
			dc_item_reset_triggers(item, NULL);

		dc_strpool_release(function->function);
		dc_strpool_release(function->parameter);

		zbx_hashset_remove_direct(&config->functions, function);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
 * Purpose: Updates expressions configuration cache                           *
 *                                                                            *
 * Parameters: result - [IN] the result of expressions database select        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_expressions(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_hashset_iter_t	iter;
	ZBX_DC_EXPRESSION	*expression;
	ZBX_DC_REGEXP		*regexp, regexp_local;
	zbx_uint64_t		expressionid;
	int			found, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(expressionid, row[1]);
		expression = (ZBX_DC_EXPRESSION *)DCfind_id(&config->expressions, expressionid,
				sizeof(ZBX_DC_EXPRESSION), &found);

		if (0 != found)
			dc_regexp_remove_expression(expression->regexp, expressionid);

		dc_strpool_replace(found, &expression->regexp, row[0]);
		dc_strpool_replace(found, &expression->expression, row[2]);
		ZBX_STR2UCHAR(expression->type, row[3]);
		ZBX_STR2UCHAR(expression->case_sensitive, row[5]);
		expression->delimiter = *row[4];

		regexp_local.name = row[0];

		if (NULL == (regexp = (ZBX_DC_REGEXP *)zbx_hashset_search(&config->regexps, &regexp_local)))
		{
			dc_strpool_replace(0, &regexp_local.name, row[0]);
			zbx_vector_uint64_create_ext(&regexp_local.expressionids,
					__config_shmem_malloc_func,
					__config_shmem_realloc_func,
					__config_shmem_free_func);

			regexp = (ZBX_DC_REGEXP *)zbx_hashset_insert(&config->regexps, &regexp_local,
					sizeof(ZBX_DC_REGEXP));
		}

		zbx_vector_uint64_append(&regexp->expressionids, expressionid);

		config->revision.expression = revision;
	}

	/* remove regexps with no expressions related to it */
	zbx_hashset_iter_reset(&config->regexps, &iter);

	while (NULL != (regexp = (ZBX_DC_REGEXP *)zbx_hashset_iter_next(&iter)))
	{
		if (0 < regexp->expressionids.values_num)
			continue;

		dc_strpool_release(regexp->name);
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
				dc_strpool_release(regexp->name);
				zbx_vector_uint64_destroy(&regexp->expressionids);
				zbx_hashset_remove_direct(&config->regexps, regexp);
			}
		}

		dc_strpool_release(expression->expression);
		dc_strpool_release(expression->regexp);
		zbx_hashset_remove_direct(&config->expressions, expression);
	}

	if (0 != sync->add_num || 0 != sync->update_num || 0 != sync->remove_num)
		config->revision.expression = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	zbx_uint64_t	actionid;
	zbx_dc_action_t	*action;
	int		found, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(actionid, row[0]);
		action = (zbx_dc_action_t *)DCfind_id(&config->actions, actionid, sizeof(zbx_dc_action_t), &found);

		ZBX_STR2UCHAR(action->eventsource, row[1]);
		ZBX_STR2UCHAR(action->evaltype, row[2]);

		dc_strpool_replace(found, &action->formula, row[3]);

		if (0 == found)
		{
			if (EVENT_SOURCE_INTERNAL == action->eventsource)
				config->internal_actions++;

			if (EVENT_SOURCE_AUTOREGISTRATION == action->eventsource)
				config->auto_registration_actions++;

			zbx_vector_dc_action_condition_ptr_create_ext(&action->conditions, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);

			zbx_vector_dc_action_condition_ptr_reserve(&action->conditions, 1);

			action->opflags = ZBX_ACTION_OPCLASS_NONE;
		}
	}

	/* remove deleted actions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&config->actions, &rowid)))
			continue;

		if (EVENT_SOURCE_INTERNAL == action->eventsource)
			config->internal_actions--;

		if (EVENT_SOURCE_AUTOREGISTRATION == action->eventsource)
			config->auto_registration_actions--;

		dc_strpool_release(action->formula);
		zbx_vector_dc_action_condition_ptr_destroy(&action->conditions);

		zbx_hashset_remove_direct(&config->actions, action);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	zbx_uint64_t	actionid;
	zbx_dc_action_t	*action;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_STR2UINT64(actionid, row[0]);

		if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&config->actions, &actionid)))
			continue;

		action->opflags = atoi(row[1]);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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

ZBX_PTR_VECTOR_DECL(dc_action_ptr, zbx_dc_action_t *)
ZBX_PTR_VECTOR_IMPL(dc_action_ptr, zbx_dc_action_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: updates action conditions configuration cache                     *
 *                                                                            *
 * Parameters: sync - [IN] db synchronization data                            *
 *                                                                            *
 * Comments: result contains the following fields:                            *
 *           0 - conditionid                                                  *
 *           1 - actionid                                                     *
 *           2 - conditiontype                                                *
 *           3 - operator                                                     *
 *           4 - value                                                        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_action_conditions(zbx_dbsync_t *sync)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;
	zbx_uint64_t			actionid, conditionid;
	zbx_dc_action_t			*action;
	zbx_dc_action_condition_t	*condition;
	int				found, index, ret;
	zbx_vector_dc_action_ptr_t	actions;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_dc_action_ptr_create(&actions);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(actionid, row[1]);

		if (NULL == (action = (zbx_dc_action_t *)zbx_hashset_search(&config->actions, &actionid)))
			continue;

		ZBX_STR2UINT64(conditionid, row[0]);

		condition = (zbx_dc_action_condition_t *)DCfind_id(&config->action_conditions, conditionid,
				sizeof(zbx_dc_action_condition_t), &found);

		ZBX_STR2UCHAR(condition->conditiontype, row[2]);
		ZBX_STR2UCHAR(condition->op, row[3]);

		dc_strpool_replace(found, &condition->value, row[4]);
		dc_strpool_replace(found, &condition->value2, row[5]);

		if (0 == found)
		{
			condition->actionid = actionid;
			zbx_vector_dc_action_condition_ptr_append(&action->conditions, condition);
		}

		if (ZBX_CONDITION_EVAL_TYPE_AND_OR == action->evaltype)
			zbx_vector_dc_action_ptr_append(&actions, action);
	}

	/* remove deleted conditions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (condition = (zbx_dc_action_condition_t *)zbx_hashset_search(&config->action_conditions,
				&rowid)))
		{
			continue;
		}

		if (NULL != (action = (zbx_dc_action_t *)zbx_hashset_search(&config->actions, &condition->actionid)))
		{
			if (FAIL != (index = zbx_vector_dc_action_condition_ptr_search(&action->conditions, condition,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_dc_action_condition_ptr_remove_noorder(&action->conditions, index);

				if (ZBX_CONDITION_EVAL_TYPE_AND_OR == action->evaltype)
					zbx_vector_dc_action_ptr_append(&actions, action);
			}
		}

		dc_strpool_release(condition->value);
		dc_strpool_release(condition->value2);

		zbx_hashset_remove_direct(&config->action_conditions, condition);
	}

	/* sort conditions by type */

	zbx_vector_dc_action_ptr_sort(&actions, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_dc_action_ptr_uniq(&actions, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (int i = 0; i < actions.values_num; i++)
	{
		action = actions.values[i];

		if (ZBX_CONDITION_EVAL_TYPE_AND_OR == action->evaltype)
		{
			zbx_vector_dc_action_condition_ptr_sort(&action->conditions,
					dc_compare_action_conditions_by_type);
		}
	}

	zbx_vector_dc_action_ptr_destroy(&actions);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		correlationid;
	zbx_dc_correlation_t	*correlation;
	int			found, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(correlationid, row[0]);

		correlation = (zbx_dc_correlation_t *)DCfind_id(&config->correlations, correlationid,
				sizeof(zbx_dc_correlation_t), &found);

		if (0 == found)
		{
			zbx_vector_dc_corr_condition_ptr_create_ext(&correlation->conditions,
					__config_shmem_malloc_func, __config_shmem_realloc_func,
					__config_shmem_free_func);

			zbx_vector_dc_corr_operation_ptr_create_ext(&correlation->operations,
					__config_shmem_malloc_func, __config_shmem_realloc_func,
					__config_shmem_free_func);
		}

		dc_strpool_replace(found, &correlation->name, row[1]);
		dc_strpool_replace(found, &correlation->formula, row[3]);

		ZBX_STR2UCHAR(correlation->evaltype, row[2]);
	}

	/* remove deleted correlations */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations, &rowid)))
			continue;

		dc_strpool_release(correlation->name);
		dc_strpool_release(correlation->formula);

		zbx_vector_dc_corr_condition_ptr_destroy(&correlation->conditions);
		zbx_vector_dc_corr_operation_ptr_destroy(&correlation->operations);

		zbx_hashset_remove_direct(&config->correlations, correlation);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the actual size of correlation condition data depending on    *
 *          its type                                                          *
 *                                                                            *
 * Parameters: type - [IN] the condition type                                 *
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
 * Purpose: initializes correlation condition data from database row          *
 *                                                                            *
 * Parameters: condition - [IN] the condition to initialize                   *
 *             found     - [IN] 0 - new condition, 1 - cached condition       *
 *             row       - [IN] the database row containing condition data    *
 *                                                                            *
 ******************************************************************************/
static void	dc_corr_condition_init_data(zbx_dc_corr_condition_t *condition, int found,  zbx_db_row_t row)
{
	if (ZBX_CORR_CONDITION_OLD_EVENT_TAG == condition->type || ZBX_CORR_CONDITION_NEW_EVENT_TAG == condition->type)
	{
		dc_strpool_replace(found, &condition->data.tag.tag, row[0]);
		return;
	}

	row++;

	if (ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE == condition->type ||
			ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE == condition->type)
	{
		dc_strpool_replace(found, &condition->data.tag_value.tag, row[0]);
		dc_strpool_replace(found, &condition->data.tag_value.value, row[1]);
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
		dc_strpool_replace(found, &condition->data.tag_pair.oldtag, row[0]);
		dc_strpool_replace(found, &condition->data.tag_pair.newtag, row[1]);
		return;
	}
}

/******************************************************************************
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
			dc_strpool_release(condition->data.tag.tag);
			break;
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			dc_strpool_release(condition->data.tag_pair.oldtag);
			dc_strpool_release(condition->data.tag_pair.newtag);
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			/* break; is not missing here */
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			dc_strpool_release(condition->data.tag_value.tag);
			dc_strpool_release(condition->data.tag_value.value);
			break;
	}
}

/******************************************************************************
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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_ptr_create(&correlations);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(correlationid, row[1]);

		if (NULL == (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations,
				&correlationid)))
		{
			continue;
		}

		ZBX_STR2UINT64(conditionid, row[0]);
		ZBX_STR2UCHAR(type, row[2]);

		condition_size = dc_corr_condition_get_size(type);
		condition = (zbx_dc_corr_condition_t *)DCfind_id(&config->corr_conditions, conditionid, condition_size,
				&found);

		condition->correlationid = correlationid;
		condition->type = type;
		dc_corr_condition_init_data(condition, found, row + 3);

		if (0 == found)
			zbx_vector_dc_corr_condition_ptr_append(&correlation->conditions, condition);

		/* sort the conditions later */
		if (ZBX_CONDITION_EVAL_TYPE_AND_OR == correlation->evaltype)
			zbx_vector_ptr_append(&correlations, correlation);
	}

	/* remove deleted correlation conditions */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (condition = (zbx_dc_corr_condition_t *)zbx_hashset_search(&config->corr_conditions,
				&rowid)))
		{
			continue;
		}

		/* remove condition from correlation->conditions vector */
		if (NULL != (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations,
				&condition->correlationid)))
		{
			if (FAIL != (index = zbx_vector_dc_corr_condition_ptr_search(&correlation->conditions,
					condition, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				/* sort the conditions later */
				if (ZBX_CONDITION_EVAL_TYPE_AND_OR == correlation->evaltype)
					zbx_vector_ptr_append(&correlations, correlation);

				zbx_vector_dc_corr_condition_ptr_remove_noorder(&correlation->conditions, index);
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
		zbx_vector_dc_corr_condition_ptr_sort(&correlation->conditions, dc_compare_corr_conditions_by_type);
	}

	zbx_vector_ptr_destroy(&correlations);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		operationid, correlationid;
	zbx_dc_corr_operation_t	*operation;
	zbx_dc_correlation_t	*correlation;
	int			found, ret, index;
	unsigned char		type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(correlationid, row[1]);

		if (NULL == (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations,
				&correlationid)))
		{
			continue;
		}

		ZBX_STR2UINT64(operationid, row[0]);
		ZBX_STR2UCHAR(type, row[2]);

		operation = (zbx_dc_corr_operation_t *)DCfind_id(&config->corr_operations, operationid,
				sizeof(zbx_dc_corr_operation_t), &found);

		operation->type = type;

		if (0 == found)
		{
			operation->correlationid = correlationid;
			zbx_vector_dc_corr_operation_ptr_append(&correlation->operations, operation);
		}
	}

	/* remove deleted correlation operations */

	/* remove deleted actions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (operation = (zbx_dc_corr_operation_t *)zbx_hashset_search(&config->corr_operations,
				&rowid)))
		{
			continue;
		}

		/* remove operation from correlation->conditions vector */
		if (NULL != (correlation = (zbx_dc_correlation_t *)zbx_hashset_search(&config->correlations,
				&operation->correlationid)))
		{
			if (FAIL != (index = zbx_vector_dc_corr_operation_ptr_search(&correlation->operations,
					operation, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_dc_corr_operation_ptr_remove_noorder(&correlation->operations, index);
			}
		}
		zbx_hashset_remove_direct(&config->corr_operations, operation);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	dc_compare_hgroups(const void *d1, const void *d2)
{
	const zbx_dc_hostgroup_t	*g1 = *((const zbx_dc_hostgroup_t **)d1);
	const zbx_dc_hostgroup_t	*g2 = *((const zbx_dc_hostgroup_t **)d2);

	return strcmp(g1->name, g2->name);
}

/******************************************************************************
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
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		groupid;
	zbx_dc_hostgroup_t	*group;
	int			found, ret, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(groupid, row[0]);

		group = (zbx_dc_hostgroup_t *)DCfind_id(&config->hostgroups, groupid, sizeof(zbx_dc_hostgroup_t),
				&found);

		if (0 == found)
		{
			group->flags = ZBX_DC_HOSTGROUP_FLAGS_NONE;
			zbx_vector_ptr_append(&config->hostgroups_name, group);

			zbx_hashset_create_ext(&group->hostids, 0, ZBX_DEFAULT_UINT64_HASH_FUNC,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);
		}

		dc_strpool_replace(found, &group->name, row[1]);
	}

	/* remove deleted host groups */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups, &rowid)))
			continue;

		if (FAIL != (index = zbx_vector_ptr_search(&config->hostgroups_name, group,
				ZBX_DEFAULT_PTR_COMPARE_FUNC)))
		{
			zbx_vector_ptr_remove_noorder(&config->hostgroups_name, index);
		}

		if (ZBX_DC_HOSTGROUP_FLAGS_NONE != group->flags)
			zbx_vector_uint64_destroy(&group->nested_groupids);

		dc_strpool_release(group->name);
		zbx_hashset_destroy(&group->hostids);
		zbx_hashset_remove_direct(&config->hostgroups, group);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	int			found, ret, index;
	zbx_uint64_t		triggerid, triggertagid;
	ZBX_DC_TRIGGER		*trigger;
	zbx_dc_trigger_tag_t	*trigger_tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(triggerid, row[1]);

		if (NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &triggerid)))
			continue;

		ZBX_STR2UINT64(triggertagid, row[0]);

		trigger_tag = (zbx_dc_trigger_tag_t *)DCfind_id(&config->trigger_tags, triggertagid,
				sizeof(zbx_dc_trigger_tag_t), &found);
		dc_strpool_replace(found, &trigger_tag->tag, row[2]);
		dc_strpool_replace(found, &trigger_tag->value, row[3]);

		if (0 == found)
		{
			trigger_tag->triggerid = triggerid;
			if (ZBX_FLAG_DISCOVERY_PROTOTYPE != trigger->flags)
			{
				zbx_vector_ptr_reserve(&trigger->tags, ZBX_VECTOR_ARRAY_RESERVE);
				zbx_vector_ptr_append(&trigger->tags, trigger_tag);
			}
		}
	}

	/* remove unused trigger tags */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (trigger_tag = (zbx_dc_trigger_tag_t *)zbx_hashset_search(&config->trigger_tags, &rowid)))
			continue;

		if (NULL != (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &trigger_tag->triggerid)))
		{
			if (ZBX_FLAG_DISCOVERY_PROTOTYPE != trigger->flags)
			{
				if (FAIL != (index = zbx_vector_ptr_search(&trigger->tags, trigger_tag,
						ZBX_DEFAULT_PTR_COMPARE_FUNC)))
				{
					zbx_vector_ptr_remove_noorder(&trigger->tags, index);

					/* recreate empty tags vector to release used memory */
					if (0 == trigger->tags.values_num)
					{
						zbx_vector_ptr_destroy(&trigger->tags);
						zbx_vector_ptr_create_ext(&trigger->tags, __config_shmem_malloc_func,
								__config_shmem_realloc_func, __config_shmem_free_func);
					}
				}
			}
		}

		dc_strpool_release(trigger_tag->tag);
		dc_strpool_release(trigger_tag->value);

		zbx_hashset_remove_direct(&config->trigger_tags, trigger_tag);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates item tags in configuration cache                          *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - itemtagid                                                    *
 *           1 - itemid                                                       *
 *           2 - tag                                                          *
 *           3 - value                                                        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_item_tags(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_hashset_uniq_t	uniq = ZBX_HASHSET_UNIQ_FALSE;
	int			found, ret, index;
	ZBX_DC_ITEM		*item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	if (0 == config_private.item_tag_links.num_slots)
	{
		int	row_num = zbx_dbsync_get_row_num(sync);

		zbx_hashset_reserve(&config_private.item_tag_links, MAX(row_num, 100));
		uniq = ZBX_HASHSET_UNIQ_TRUE;
	}

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_dc_item_tag_t	item_tag_local = {0};
		zbx_dc_item_tag_link	*item_tag_link;
		zbx_uint64_t		itemid;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[1]);

		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
			continue;

		ZBX_STR2UINT64(item_tag_local.itemtagid, row[0]);

		item_tag_link = (zbx_dc_item_tag_link *)DCfind_id_ext(&config_private.item_tag_links,
				item_tag_local.itemtagid, sizeof(zbx_dc_item_tag_link), &found, uniq);

		if (0 == found || FAIL == (index = zbx_vector_dc_item_tag_search(&item->tags, item_tag_local,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			found = 0;
			item_tag_link->itemid = itemid;
			zbx_vector_dc_item_tag_reserve(&item->tags, item->tags.values_alloc + 1);
			zbx_vector_dc_item_tag_append(&item->tags, item_tag_local);
			index = item->tags.values_num - 1;
		}

		dc_strpool_replace(found, &item->tags.values[index].tag, row[2]);
		dc_strpool_replace(found, &item->tags.values[index].value, row[3]);
	}

	/* remove unused item tags */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_dc_item_tag_link	*item_tag_link;

		if (NULL == (item_tag_link = (zbx_dc_item_tag_link *)zbx_hashset_search(&config_private.item_tag_links,
				&rowid)))
		{
			continue;
		}

		if (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &item_tag_link->itemid)))
		{
			zbx_dc_item_tag_t	item_tag_local = {.itemtagid = item_tag_link->itemtagid};

			if (FAIL != (index = zbx_vector_dc_item_tag_search(&item->tags, item_tag_local,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				dc_strpool_release(item->tags.values[index].tag);
				dc_strpool_release(item->tags.values[index].value);

				zbx_vector_dc_item_tag_remove_noorder(&item->tags, index);

				if (0 == item->tags.values_num)
				{
					zbx_vector_dc_item_tag_destroy(&item->tags);
					zbx_vector_dc_item_tag_create_ext(&item->tags, __config_shmem_malloc_func,
							__config_shmem_realloc_func, __config_shmem_free_func);
				}
			}
		}

		zbx_hashset_remove_direct(&config_private.item_tag_links, item_tag_link);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates host tags in configuration cache                          *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - hosttagid                                                    *
 *           1 - hostid                                                       *
 *           2 - tag                                                          *
 *           3 - value                                                        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_host_tags(zbx_dbsync_t *sync)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;

	zbx_dc_host_tag_t		*host_tag;
	zbx_dc_host_tag_index_t		*host_tag_index_entry;

	int		found, index, ret;
	zbx_uint64_t	hosttagid, hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hosttagid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);

		host_tag = (zbx_dc_host_tag_t *)DCfind_id(&config->host_tags, hosttagid,
				sizeof(zbx_dc_host_tag_t), &found);

		/* store new information in host_tag structure */
		host_tag->hostid = hostid;
		dc_strpool_replace(found, &host_tag->tag, row[2]);
		dc_strpool_replace(found, &host_tag->value, row[3]);

		/* update host_tags_index*/
		if (tag == ZBX_DBSYNC_ROW_ADD)
		{
			host_tag_index_entry = (zbx_dc_host_tag_index_t *)DCfind_id(&config->host_tags_index, hostid,
					sizeof(zbx_dc_host_tag_index_t), &found);

			if (0 == found)
			{
				zbx_vector_ptr_create_ext(&host_tag_index_entry->tags, __config_shmem_malloc_func,
						__config_shmem_realloc_func, __config_shmem_free_func);
			}

			zbx_vector_ptr_append(&host_tag_index_entry->tags, host_tag);
		}
	}

	/* remove deleted host tags from buffer */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (host_tag = (zbx_dc_host_tag_t *)zbx_hashset_search(&config->host_tags, &rowid)))
			continue;

		/* update host_tags_index*/
		host_tag_index_entry = (zbx_dc_host_tag_index_t *)zbx_hashset_search(&config->host_tags_index,
				&host_tag->hostid);

		if (NULL != host_tag_index_entry)
		{
			if (FAIL != (index = zbx_vector_ptr_search(&host_tag_index_entry->tags, host_tag,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_ptr_remove(&host_tag_index_entry->tags, index);
			}

			/* remove index entry if it's empty */
			if (0 == host_tag_index_entry->tags.values_num)
			{
				zbx_vector_ptr_destroy(&host_tag_index_entry->tags);
				zbx_hashset_remove_direct(&config->host_tags_index, host_tag_index_entry);
			}
		}

		/* clear host_tag structure */
		dc_strpool_release(host_tag->tag);
		dc_strpool_release(host_tag->value);

		zbx_hashset_remove_direct(&config->host_tags, host_tag);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two item parameters                                       *
 *                                                                            *
 ******************************************************************************/
static int	dc_compare_items_param(const void *d1, const void *d2)
{
	zbx_dc_item_param_t	*p1 = *(zbx_dc_item_param_t **)d1;
	zbx_dc_item_param_t	*p2 = *(zbx_dc_item_param_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->name, p2->name);
	ZBX_RETURN_IF_NOT_EQUAL(p1->value, p2->value);

	return 0;
}

/******************************************************************************
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

	if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == p1->type && ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == p2->type)
	{
		if (p1->step < p2->step)
			return -1;

		if (p1->step > p2->step)
			return 1;
	}

	if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == p1->type)
		return -1;

	if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == p2->type)
		return 1;

	ZBX_RETURN_IF_NOT_EQUAL(p1->step, p2->step);

	return 0;
}

/******************************************************************************
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
static void	DCsync_item_preproc(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;
	zbx_uint64_t			item_preprocid, itemid;
	zbx_hashset_uniq_t		uniq = ZBX_HASHSET_UNIQ_FALSE;
	int				found, ret, i, index;
	ZBX_DC_PREPROCITEM		*preprocitem = NULL;
	zbx_dc_preproc_op_t		*op;
	ZBX_DC_ITEM			*item;
	zbx_vector_dc_item_ptr_t	items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	if (0 == config->preprocops.num_slots)
	{
		int	row_num = zbx_dbsync_get_row_num(sync);

		zbx_hashset_reserve(&config->preprocops, MAX(row_num, 100));
		uniq = ZBX_HASHSET_UNIQ_TRUE;
	}

	zbx_vector_dc_item_ptr_create(&items);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[1]);

		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
			continue;

		if (NULL == (preprocitem = item->preproc_item))
		{
			preprocitem = (ZBX_DC_PREPROCITEM *)__config_shmem_malloc_func(NULL, sizeof(ZBX_DC_PREPROCITEM));

			zbx_vector_ptr_create_ext(&preprocitem->preproc_ops, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);

			item->preproc_item = preprocitem;
		}
		zbx_vector_dc_item_ptr_append(&items, item);

		ZBX_STR2UINT64(item_preprocid, row[0]);

		op = (zbx_dc_preproc_op_t *)DCfind_id_ext(&config->preprocops, item_preprocid,
				sizeof(zbx_dc_preproc_op_t), &found, uniq);

		ZBX_STR2UCHAR(op->type, row[2]);
		dc_strpool_replace(found, &op->params, row[3]);
		op->step = atoi(row[4]);
		op->error_handler = atoi(row[5]);
		dc_strpool_replace(found, &op->error_handler_params, row[6]);

		if (0 == found)
		{
			op->itemid = itemid;
			zbx_vector_ptr_reserve(&preprocitem->preproc_ops, ZBX_VECTOR_ARRAY_RESERVE);
			zbx_vector_ptr_append(&preprocitem->preproc_ops, op);
		}
	}

	/* remove deleted item preprocessing operations */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (op = (zbx_dc_preproc_op_t *)zbx_hashset_search(&config->preprocops, &rowid)))
			continue;

		if (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &op->itemid)) &&
				NULL != (preprocitem = item->preproc_item))
		{
			if (FAIL != (index = zbx_vector_ptr_search(&preprocitem->preproc_ops, op,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_ptr_remove_noorder(&preprocitem->preproc_ops, index);
				zbx_vector_dc_item_ptr_append(&items, item);
			}
		}

		dc_strpool_release(op->params);
		dc_strpool_release(op->error_handler_params);
		zbx_hashset_remove_direct(&config->preprocops, op);
	}

	/* sort item preprocessing operations by step */

	zbx_vector_dc_item_ptr_sort(&items, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_dc_item_ptr_uniq(&items, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < items.values_num; i++)
	{
		item = items.values[i];

		if (NULL == (preprocitem = item->preproc_item))
			continue;

		dc_item_update_revision(item, revision);

		if (0 == preprocitem->preproc_ops.values_num)
		{
			dc_preprocitem_free(preprocitem);
			item->preproc_item = NULL;
		}
		else
		{
			zbx_vector_ptr_sort(&preprocitem->preproc_ops, dc_compare_preprocops_by_step);
			preprocitem->revision = revision;
		}
	}

	zbx_vector_dc_item_ptr_destroy(&items);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates item parameters in configuration cache                    *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - item_paramid                                                 *
 *           1 - itemid                                                       *
 *           2 - name                                                         *
 *           3 - value                                                        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_items_param(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		item_paramid, itemid;
	int			found, ret, i, index;
	zbx_dc_item_param_t	*item_param;
	zbx_vector_ptr_t	items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_ptr_create(&items);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_vector_ptr_t	*params;
		ZBX_DC_ITEM		*item;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[1]);

		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)) ||
				NULL == (params = dc_item_parameters(item, item->type)))
		{
			zabbix_log(LOG_LEVEL_DEBUG,
					"cannot find parent item for item parameters (itemid=" ZBX_FS_UI64")", itemid);
			continue;
		}

		ZBX_STR2UINT64(item_paramid, row[0]);
		item_param = (zbx_dc_item_param_t *)DCfind_id(&config->items_params, item_paramid,
				sizeof(zbx_dc_item_param_t), &found);

		dc_strpool_replace(found, &item_param->name, row[2]);
		dc_strpool_replace(found, &item_param->value, row[3]);

		if (0 == found)
		{
			item_param->itemid = itemid;
			zbx_vector_ptr_append(params, item_param);
		}

		zbx_vector_ptr_append(&items, item);
	}

	/* remove deleted item script parameters */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_vector_ptr_t	*params;
		ZBX_DC_ITEM		*item;

		if (NULL == (item_param =
				(zbx_dc_item_param_t *)zbx_hashset_search(&config->items_params, &rowid)))
		{
			continue;
		}

		if (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &item_param->itemid)) &&
				NULL != (params = dc_item_parameters(item, item->type)))
		{
			if (FAIL != (index = zbx_vector_ptr_search(params, item_param, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_ptr_remove_noorder(params, index);
				zbx_vector_ptr_append(&items, item);
			}
		}

		dc_strpool_release(item_param->name);
		dc_strpool_release(item_param->value);
		zbx_hashset_remove_direct(&config->items_params, item_param);
	}

	/* sort item script parameters */

	zbx_vector_ptr_sort(&items, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(&items, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < items.values_num; i++)
	{
		zbx_vector_ptr_t	*params;
		ZBX_DC_ITEM		*item;

		item = (ZBX_DC_ITEM *)items.values[i];
		dc_item_update_revision(item, revision);

		params = dc_item_parameters(item, item->type);

		if (NULL != params && 0 < params->values_num)
			zbx_vector_ptr_sort(params, dc_compare_items_param);
	}

	zbx_vector_ptr_destroy(&items);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates group hosts in configuration cache                        *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - groupid                                                      *
 *           1 - hostid                                                       *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_hostgroup_hosts(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	zbx_dc_hostgroup_t	*group = NULL;

	int			ret;
	zbx_uint64_t		last_groupid = 0, groupid, hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		config->maintenance_update |= ZBX_FLAG_MAINTENANCE_UPDATE_MAINTENANCE;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(groupid, row[0]);

		if (last_groupid != groupid)
		{
			group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups, &groupid);
			last_groupid = groupid;
		}

		if (NULL == group)
			continue;

		ZBX_STR2UINT64(hostid, row[1]);
		zbx_hashset_insert(&group->hostids, &hostid, sizeof(hostid));
	}

	/* remove deleted group hostids from cache */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_STR2UINT64(groupid, row[0]);

		if (NULL == (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups, &groupid)))
			continue;

		ZBX_STR2UINT64(hostid, row[1]);
		zbx_hashset_remove(&group->hostids, &hostid);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate nextcheck timestamp                                     *
 *                                                                            *
 * Parameters: seend - [IN] the seed                                          *
 *             delay - [IN] the delay in seconds                              *
 *             now   - [IN] current timestamp                                 *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 ******************************************************************************/
static time_t	dc_calculate_nextcheck(zbx_uint64_t seed, int delay, time_t now)
{
	time_t	nextcheck;

	if (0 == delay)
		return ZBX_JAN_2038;

	nextcheck = delay * (now / delay) + (unsigned int)(seed % (unsigned int)delay);

	while (nextcheck <= now)
		nextcheck += delay;

	return nextcheck;
}

static void	dc_drule_queue(zbx_dc_drule_t *drule)
{
	zbx_binary_heap_elem_t	elem;

	elem.key = drule->druleid;
	elem.data = (void *)drule;

	if (ZBX_LOC_QUEUE != drule->location)
	{
		zbx_binary_heap_insert(&config->drule_queue, &elem);
		drule->location = ZBX_LOC_QUEUE;
	}
	else
		zbx_binary_heap_update_direct(&config->drule_queue, &elem);
}

static void	dc_drule_dequeue(zbx_dc_drule_t *drule)
{
	if (ZBX_LOC_QUEUE == drule->location)
	{
		zbx_binary_heap_remove_direct(&config->drule_queue, drule->druleid);
		drule->location = ZBX_LOC_NOWHERE;
	}
}

static void	dc_sync_drules(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char		**row, *delay_str;
	zbx_uint64_t	rowid, druleid, proxyid;
	unsigned char	tag;
	int 		found, ret, delay = 0;
	ZBX_DC_PROXY	*proxy;
	zbx_dc_drule_t	*drule;
	time_t		now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	now = time(NULL);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(druleid, row[0]);
		ZBX_DBROW2UINT64(proxyid, row[1]);

		drule = (zbx_dc_drule_t *)DCfind_id(&config->drules, druleid, sizeof(zbx_dc_drule_t), &found);

		ZBX_STR2UCHAR(drule->status, row[5]);
		drule->concurrency_max = atoi(row[6]);

		if (0 == found)
		{
			drule->location = ZBX_LOC_NOWHERE;
			drule->nextcheck = 0;
		}
		else
		{
			if (0 != drule->proxyid && proxyid != drule->proxyid &&
				NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies,
						&drule->proxyid)))
			{
				proxy->revision = revision;
			}
		}

		drule->proxyid = proxyid;
		if (0 != drule->proxyid)
		{
			if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &drule->proxyid)))
				proxy->revision = revision;
		}

		dc_strpool_replace(found, (const char **)&drule->delay_str, row[2]);

		delay_str = dc_expand_user_and_func_macros_dyn(row[2], NULL, 0, ZBX_MACRO_ENV_NONSECURE);
		if (SUCCEED != zbx_is_time_suffix(delay_str, &delay, ZBX_LENGTH_UNLIMITED))
			delay = ZBX_DEFAULT_INTERVAL;
		zbx_free(delay_str);

		dc_strpool_replace(found, (const char **)&drule->name, row[3]);
		dc_strpool_replace(found, (const char **)&drule->iprange, row[4]);

		if (DRULE_STATUS_MONITORED == drule->status && 0 == drule->proxyid)
		{
			int	delay_new = 0;

			if (0 == found && 0 < config->revision.config)
				delay_new = delay > SEC_PER_MIN ? SEC_PER_MIN : delay;
			else if (ZBX_LOC_NOWHERE == drule->location || delay != drule->delay)
				delay_new = delay;

			if (0 != delay_new)
			{
				drule->nextcheck = dc_calculate_nextcheck(drule->druleid, delay_new, now);
				dc_drule_queue(drule);
			}
		}
		else
			dc_drule_dequeue(drule);

		drule->delay = delay;
		drule->revision = revision;

		if (config->revision.drules != revision)
			config->revision.drules = revision;
	}

	/* remove deleted discovery rules from cache and update proxy revision */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (drule = (zbx_dc_drule_t *)zbx_hashset_search(&config->drules, &rowid)))
			continue;

		if (0 != drule->proxyid)
		{
			if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &drule->proxyid)))
				proxy->revision = revision;
		}

		dc_drule_dequeue(drule);
		dc_strpool_release(drule->iprange);
		dc_strpool_release(drule->delay_str);
		dc_strpool_release(drule->name);
		zbx_hashset_remove_direct(&config->drules, drule);

		if (config->revision.drules != revision)
			config->revision.drules = revision;
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	dc_sync_dchecks(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char		**row;
	zbx_uint64_t	rowid, druleid, dcheckid;
	unsigned char	tag;
	int 		found, ret;
	ZBX_DC_PROXY	*proxy;
	zbx_dc_drule_t	*drule;
	zbx_dc_dcheck_t	*dcheck;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(dcheckid, row[0]);
		ZBX_STR2UINT64(druleid, row[1]);

		if (NULL == (drule = (zbx_dc_drule_t *)zbx_hashset_search(&config->drules, &druleid)))
			continue;

		dcheck = (zbx_dc_dcheck_t *)DCfind_id(&config->dchecks, dcheckid, sizeof(zbx_dc_dcheck_t), &found);

		dcheck->druleid = druleid;
		ZBX_STR2UCHAR(dcheck->type, row[2]);
		dc_strpool_replace(found, (const char **)&dcheck->key_, row[3]);
		dc_strpool_replace(found, (const char **)&dcheck->snmp_community, row[4]);
		dc_strpool_replace(found, (const char **)&dcheck->ports, row[5]);
		dc_strpool_replace(found, (const char **)&dcheck->snmpv3_securityname, row[6]);
		ZBX_STR2UCHAR(dcheck->snmpv3_securitylevel, row[7]);
		dc_strpool_replace(found, (const char **)&dcheck->snmpv3_authpassphrase, row[8]);
		dc_strpool_replace(found, (const char **)&dcheck->snmpv3_privpassphrase, row[9]);
		ZBX_STR2UCHAR(dcheck->uniq, row[10]);
		ZBX_STR2UCHAR(dcheck->snmpv3_authprotocol, row[11]);
		ZBX_STR2UCHAR(dcheck->snmpv3_privprotocol, row[12]);
		dc_strpool_replace(found, (const char **)&dcheck->snmpv3_contextname, row[13]);
		ZBX_STR2UCHAR(dcheck->allow_redirect, row[14]);

		if (drule->revision == revision)
			continue;

		drule->revision = revision;

		if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &drule->proxyid)))
			proxy->revision = revision;

		if (config->revision.drules != revision)
			config->revision.drules = revision;
	}

	/* remove deleted discovery checks from cache and update proxy revision */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (dcheck = (zbx_dc_dcheck_t *)zbx_hashset_search(&config->dchecks, &rowid)))
			continue;

		if (NULL != (drule = (zbx_dc_drule_t *)zbx_hashset_search(&config->drules, &dcheck->druleid)) &&
				0 != drule->proxyid && drule->revision != revision)
		{
			if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &drule->proxyid)))
				proxy->revision = revision;

			drule->revision = revision;

			if (config->revision.drules != revision)
				config->revision.drules = revision;
		}

		dc_strpool_release(dcheck->key_);
		dc_strpool_release(dcheck->snmp_community);
		dc_strpool_release(dcheck->ports);
		dc_strpool_release(dcheck->snmpv3_securityname);
		dc_strpool_release(dcheck->snmpv3_authpassphrase);
		dc_strpool_release(dcheck->snmpv3_privpassphrase);
		dc_strpool_release(dcheck->snmpv3_contextname);

		zbx_hashset_remove_direct(&config->dchecks, dcheck);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update host and its proxy revision                                *
 *                                                                            *
 ******************************************************************************/
static int	dc_host_update_revision(ZBX_DC_HOST *host, zbx_uint64_t revision)
{
	ZBX_DC_PROXY	*proxy;

	if (host->revision == revision)
		return SUCCEED;

	host->revision = revision;

	if (0 == host->proxyid)
		return SUCCEED;

	if (NULL == (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &host->proxyid)))
		return FAIL;

	proxy->revision = revision;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update item, host and its proxy revision                          *
 *                                                                            *
 ******************************************************************************/
static int	dc_item_update_revision(ZBX_DC_ITEM *item, zbx_uint64_t revision)
{
	ZBX_DC_HOST	*host;

	if (item->revision == revision)
		return SUCCEED;

	item->revision = revision;

	if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &item->hostid)))
		return FAIL;

	dc_host_update_revision(host, revision);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update httptest and its parent object revision                    *
 *                                                                            *
 ******************************************************************************/
static int	dc_httptest_update_revision(zbx_dc_httptest_t *httptest, zbx_uint64_t revision)
{
	ZBX_DC_HOST	*host;

	if (httptest->revision == revision)
		return SUCCEED;

	httptest->revision = revision;

	if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &httptest->hostid)))
		return FAIL;

	dc_host_update_revision(host, revision);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update httptest step and its parent object revision               *
 *                                                                            *
 ******************************************************************************/
static int	dc_httpstep_update_revision(zbx_dc_httpstep_t *httpstep, zbx_uint64_t revision)
{
	zbx_dc_httptest_t	*httptest;

	if (httpstep->revision == revision)
		return SUCCEED;

	httpstep->revision = revision;

	if (NULL == (httptest = (zbx_dc_httptest_t *)zbx_hashset_search(&config->httptests, &httpstep->httptestid)))
		return FAIL;

	return dc_httptest_update_revision(httptest, revision);
}

static void	dc_httptest_queue(zbx_dc_httptest_t *httptest)
{
	zbx_binary_heap_elem_t	elem;

	elem.key = httptest->httptestid;
	elem.data = (void *)httptest;

	if (ZBX_LOC_QUEUE != httptest->location)
	{
		zbx_binary_heap_insert(&config->httptest_queue, &elem);
		httptest->location = ZBX_LOC_QUEUE;
	}
	else
		zbx_binary_heap_update_direct(&config->httptest_queue, &elem);
}

static void	dc_httptest_dequeue(zbx_dc_httptest_t *httptest)
{
	if (ZBX_LOC_QUEUE == httptest->location)
	{
		zbx_binary_heap_remove_direct(&config->httptest_queue, httptest->httptestid);
		httptest->location = ZBX_LOC_NOWHERE;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update httpstep and its parent object revision                    *
 *                                                                            *
 ******************************************************************************/
static void	dc_sync_httptests(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row, *delay_str;
	zbx_uint64_t		rowid, httptestid, hostid;
	unsigned char		tag;
	int 			found, ret, delay;
	ZBX_DC_HOST		*host;
	zbx_dc_httptest_t	*httptest;
	time_t			now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	now = time(NULL);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostid, row[1]);

		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
			continue;

		dc_host_update_revision(host, revision);

		ZBX_STR2UINT64(httptestid, row[0]);

		httptest = (zbx_dc_httptest_t *)DCfind_id(&config->httptests, httptestid, sizeof(zbx_dc_httptest_t),
				&found);

		ZBX_STR2UCHAR(httptest->status, row[3]);

		if (0 == found)
		{
			httptest->location = ZBX_LOC_NOWHERE;
			httptest->nextcheck = 0;
			zbx_vector_dc_httptest_ptr_append(&host->httptests, httptest);
		}

		delay_str = dc_expand_user_and_func_macros_dyn(row[2], &hostid, 1, ZBX_MACRO_ENV_NONSECURE);
		if (SUCCEED != zbx_is_time_suffix(delay_str, &delay, ZBX_LENGTH_UNLIMITED))
			delay = ZBX_DEFAULT_INTERVAL;
		zbx_free(delay_str);

		if (HTTPTEST_STATUS_MONITORED == httptest->status && HOST_STATUS_MONITORED == host->status &&
				0 == host->proxyid)
		{
			int	delay_new = 0;

			if (0 == found && 0 < config->revision.config)
				delay_new = delay > SEC_PER_MIN ? SEC_PER_MIN : delay;
			else if (ZBX_LOC_NOWHERE == httptest->location || delay != httptest->delay)
				delay_new = delay;

			if (0 != delay_new)
			{
				httptest->nextcheck = dc_calculate_nextcheck(httptest->httptestid, delay_new, now);
				dc_httptest_queue(httptest);
			}
		}
		else
			dc_httptest_dequeue(httptest);

		httptest->hostid = hostid;
		httptest->delay = delay;
		httptest->revision = revision;
	}

	/* remove deleted httptest rules from cache and update host revision */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		int	index;

		if (NULL == (httptest = (zbx_dc_httptest_t *)zbx_hashset_search(&config->httptests, &rowid)))
			continue;

		if (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &httptest->hostid)))
		{
			dc_host_update_revision(host, revision);

			if (FAIL != (index = zbx_vector_dc_httptest_ptr_search(&host->httptests, httptest,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_dc_httptest_ptr_remove(&host->httptests, index);
			}
		}

		dc_httptest_dequeue(httptest);
		zbx_hashset_remove_direct(&config->httptests, httptest);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	dc_sync_httptest_fields(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid, httptestid, httptest_fieldid;
	unsigned char		tag;
	int 			found, ret;
	zbx_dc_httptest_t	*httptest;
	zbx_dc_httptest_field_t	*httptest_field;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(httptestid, row[1]);

		if (NULL == (httptest = (zbx_dc_httptest_t *)zbx_hashset_search(&config->httptests, &httptestid)))
			continue;

		dc_httptest_update_revision(httptest, revision);

		ZBX_STR2UINT64(httptest_fieldid, row[0]);

		httptest_field = (zbx_dc_httptest_field_t *)DCfind_id(&config->httptest_fields, httptest_fieldid,
				sizeof(zbx_dc_httptest_field_t), &found);

		httptest_field->httptestid = httptestid;

	}

	/* remove deleted httptest fields from cache and update host revision */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (httptest_field = (zbx_dc_httptest_field_t *)zbx_hashset_search(&config->httptest_fields,
				&rowid)))
		{
			continue;
		}

		if (NULL != (httptest = (zbx_dc_httptest_t *)zbx_hashset_search(&config->httptests,
				&httptest_field->httptestid)))
		{
			dc_httptest_update_revision(httptest, revision);
		}

		zbx_hashset_remove_direct(&config->httptest_fields, httptest_field);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	dc_sync_httpsteps(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid, httptestid, httpstepid;
	unsigned char		tag;
	int 			found, ret;
	zbx_dc_httptest_t	*httptest;
	zbx_dc_httpstep_t	*httpstep;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(httptestid, row[1]);

		if (NULL == (httptest = (zbx_dc_httptest_t *)zbx_hashset_search(&config->httptests, &httptestid)))
			continue;

		dc_httptest_update_revision(httptest, revision);

		httptest->revision = revision;

		ZBX_STR2UINT64(httpstepid, row[0]);

		httpstep = (zbx_dc_httpstep_t *)DCfind_id(&config->httpsteps, httpstepid,
				sizeof(zbx_dc_httpstep_t), &found);

		httpstep->httptestid = httptestid;
		httpstep->revision = revision;
	}

	/* remove deleted httptest fields from cache and update host revision */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (httpstep = (zbx_dc_httpstep_t *)zbx_hashset_search(&config->httpsteps,
				&rowid)))
		{
			continue;
		}

		if (NULL != (httptest = (zbx_dc_httptest_t *)zbx_hashset_search(&config->httptests,
				&httpstep->httptestid)))
		{
			dc_httptest_update_revision(httptest, revision);
		}

		zbx_hashset_remove_direct(&config->httpsteps, httpstep);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	dc_sync_httpstep_fields(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid, httpstep_fieldid, httpstepid;
	unsigned char		tag;
	int 			found, ret;
	zbx_dc_httpstep_t	*httpstep;
	zbx_dc_httpstep_field_t	*httpstep_field;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(httpstepid, row[1]);

		if (NULL == (httpstep = (zbx_dc_httpstep_t *)zbx_hashset_search(&config->httpsteps, &httpstepid)))
			continue;

		dc_httpstep_update_revision(httpstep, revision);

		ZBX_STR2UINT64(httpstep_fieldid, row[0]);

		httpstep_field = (zbx_dc_httpstep_field_t *)DCfind_id(&config->httpstep_fields, httpstep_fieldid,
				sizeof(zbx_dc_httpstep_field_t), &found);

		httpstep_field->httpstepid = httpstep_fieldid;

	}

	/* remove deleted httpstep fields from cache and update host revision */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (httpstep_field = (zbx_dc_httpstep_field_t *)zbx_hashset_search(&config->httpstep_fields,
				&rowid)))
		{
			continue;
		}

		if (NULL != (httpstep = (zbx_dc_httpstep_t *)zbx_hashset_search(&config->httpsteps,
				&httpstep_field->httpstepid)))
		{
			dc_httpstep_update_revision(httpstep, revision);
		}

		zbx_hashset_remove_direct(&config->httpstep_fields, httpstep_field);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
	{
		if (ZBX_FLAG_DISCOVERY_PROTOTYPE == trigger->flags)
			continue;

		trigger->topoindex = 1;
	}

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

static int	zbx_default_ptr_pair_ptr_second_compare_func(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t	*p1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t	*p2 = (const zbx_ptr_pair_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->second, p2->second);
	ZBX_RETURN_IF_NOT_EQUAL(p1->first, p2->first);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new itemids into trigger itemids array                        *
 *                                                                            *
 * Comments: If trigger is already linked to an item and a new function       *
 *           linking the trigger to that item is being added, then the item   *
 *           triggers will be reset causing itemid to be removed from trigger.*
 *           Because of that itemids always can be simply appended to the     *
 *           existing list without checking for duplicates.                   *
 *                                                                            *
 ******************************************************************************/
static void	dc_trigger_add_itemids(ZBX_DC_TRIGGER *trigger, const zbx_vector_uint64_t *itemids)
{
	zbx_uint64_t	*itemid;
	int		i;

	if (NULL != trigger->itemids)
	{
		int	itemids_num = 0;

		for (itemid = trigger->itemids; 0 != *itemid; itemid++)
			itemids_num++;

		trigger->itemids = (zbx_uint64_t *)__config_shmem_realloc_func(trigger->itemids,
				sizeof(zbx_uint64_t) * (size_t)(itemids->values_num + itemids_num + 1));
	}
	else
	{
		trigger->itemids = (zbx_uint64_t *)__config_shmem_malloc_func(trigger->itemids,
				sizeof(zbx_uint64_t) * (size_t)(itemids->values_num + 1));
		trigger->itemids[0] = 0;
	}

	for (itemid = trigger->itemids; 0 != *itemid; itemid++)
		;

	for (i = 0; i < itemids->values_num; i++)
		*itemid++ = itemids->values[i];

	*itemid = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reset item trigger links and remove corresponding itemids from    *
 *          affected triggers                                                 *
 *                                                                            *
 * Parameters: item            - the item to reset                            *
 *             trigger_exclude - the trigger to exclude                       *
 *                                                                            *
 ******************************************************************************/
static void	dc_item_reset_triggers(ZBX_DC_ITEM *item, ZBX_DC_TRIGGER *trigger_exclude)
{
	ZBX_DC_TRIGGER	**trigger;

	item->update_triggers = 1;

	if (NULL == item->triggers)
		return;

	for (trigger = item->triggers; NULL != *trigger; trigger++)
	{
		zbx_uint64_t	*itemid;

		if (*trigger == trigger_exclude)
			continue;

		if (NULL != (*trigger)->itemids)
		{
			for (itemid = (*trigger)->itemids; 0 != *itemid; itemid++)
			{
				if (item->itemid == *itemid)
				{
					while (0 != (*itemid = itemid[1]))
						itemid++;

					break;
				}
			}
		}
	}

	config->items.mem_free_func(item->triggers);
	item->triggers = NULL;
}

/******************************************************************************
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

	zbx_vector_ptr_pair_create(&itemtrigs);
	zbx_hashset_iter_reset(&config->functions, &iter);
	while (NULL != (function = (ZBX_DC_FUNCTION *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &function->itemid)) ||
				NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers,
				&function->triggerid)))
		{
			continue;
		}

		if (ZBX_FLAG_DISCOVERY_PROTOTYPE == trigger->flags)
		{
			trigger->functional = TRIGGER_FUNCTIONAL_FALSE;
			continue;
		}

		/* cache item - trigger link */
		if (0 != item->update_triggers)
		{
			itemtrig.first = item;
			itemtrig.second = trigger;
			zbx_vector_ptr_pair_append(&itemtrigs, itemtrig);
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

	if (0 != itemtrigs.values_num)
	{
		zbx_vector_uint64_t	itemids;

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
			item->triggers = (ZBX_DC_TRIGGER **)config->items.mem_realloc_func(item->triggers,
					(size_t)(j - i + 1) * sizeof(ZBX_DC_TRIGGER *));

			for (k = i; k < j; k++)
				item->triggers[k - i] = (ZBX_DC_TRIGGER *)itemtrigs.values[k].second;

			item->triggers[j - i] = NULL;

			i = j - 1;
		}

		/* update reverse links from trigger to items */

		zbx_vector_uint64_create(&itemids);
		zbx_vector_ptr_pair_sort(&itemtrigs, zbx_default_ptr_pair_ptr_second_compare_func);

		trigger = (ZBX_DC_TRIGGER *)itemtrigs.values[0].second;
		for (i = 0; i < itemtrigs.values_num; i++)
		{
			if (trigger != itemtrigs.values[i].second)
			{
				dc_trigger_add_itemids(trigger, &itemids);
				trigger = (ZBX_DC_TRIGGER *)itemtrigs.values[i].second;
				zbx_vector_uint64_clear(&itemids);
			}

			item = (ZBX_DC_ITEM *)itemtrigs.values[i].first;
			zbx_vector_uint64_append(&itemids, item->itemid);
		}

		if (0 != itemids.values_num)
			dc_trigger_add_itemids(trigger, &itemids);

		zbx_vector_uint64_destroy(&itemids);
	}

	zbx_vector_ptr_pair_destroy(&itemtrigs);
}

/******************************************************************************
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
 * Purpose: load trigger queue from database                                  *
 *                                                                            *
 * Comments: This function is called when syncing configuration cache for the *
 *           first time after server start. After loading trigger queue it    *
 *           will clear the corresponding data in database.                   *
 *                                                                            *
 ******************************************************************************/
static void	dc_load_trigger_queue(zbx_hashset_t *trend_functions)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	result = zbx_db_select("select objectid,type,clock,ns from trigger_queue");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_trigger_timer_t	timer_local, *timer;

		if (ZBX_TRIGGER_TIMER_FUNCTION_TREND != atoi(row[1]))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_STR2UINT64(timer_local.objectid, row[0]);

		timer_local.eval_ts.sec = atoi(row[2]);
		timer_local.eval_ts.ns =  atoi(row[3]);
		timer = zbx_hashset_insert(trend_functions, &timer_local, sizeof(timer_local));

		/* in the case function was scheduled multiple times use the latest data */
		if (0 > zbx_timespec_compare(&timer->eval_ts, &timer_local.eval_ts))
			timer->eval_ts = timer_local.eval_ts;

	}
	zbx_db_free_result(result);
}

void	zbx_dbsync_process_active_avail_diff(zbx_vector_uint64_t *diff)
{
	zbx_ipc_message_t	message;
	unsigned char		*data = NULL;
	zbx_uint32_t		data_len = 0;

	if (0 == diff->values_num)
		return;

	zbx_ipc_message_init(&message);
	data_len = zbx_availability_serialize_hostids(&data, diff);
	zbx_availability_send(ZBX_IPC_AVAILMAN_CONFSYNC_DIFF, data, data_len, NULL);

	zbx_ipc_message_clean(&message);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates connectors in configuration cache                         *
 *                                                                            *
 * Parameters: sync     - [IN] the db synchronization data                    *
 *             revision - [IN] updated configuration revision                 *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_connectors(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		connectorid;
	zbx_dc_connector_t	*connector;
	int			found, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(connectorid, row[0]);

		connector = (zbx_dc_connector_t *)DCfind_id(&config->connectors, connectorid,
				sizeof(zbx_dc_connector_t), &found);

		if (0 == found)
		{
			zbx_vector_dc_connector_tag_create_ext(&connector->tags, config->connectors.mem_malloc_func,
					config->connectors.mem_realloc_func, config->connectors.mem_free_func);
		}

		ZBX_STR2UCHAR(connector->protocol, row[1]);
		ZBX_STR2UCHAR(connector->data_type, row[2]);
		dc_strpool_replace(found, &connector->url, row[3]);
		connector->max_records = atoi(row[4]);
		connector->max_senders = atoi(row[5]);
		dc_strpool_replace(found, &connector->timeout, row[6]);
		ZBX_STR2UCHAR(connector->max_attempts, row[7]);
		dc_strpool_replace(found, &connector->token, row[8]);
		dc_strpool_replace(found, &connector->http_proxy, row[9]);
		ZBX_STR2UCHAR(connector->authtype, row[10]);
		dc_strpool_replace(found, &connector->username, row[11]);
		dc_strpool_replace(found, &connector->password, row[12]);
		ZBX_STR2UCHAR(connector->verify_peer, row[13]);
		ZBX_STR2UCHAR(connector->verify_host, row[14]);
		dc_strpool_replace(found, &connector->ssl_cert_file, row[15]);
		dc_strpool_replace(found, &connector->ssl_key_file, row[16]);
		dc_strpool_replace(found, &connector->ssl_key_password, row[17]);
		ZBX_STR2UCHAR(connector->status, row[18]);
		ZBX_STR2UCHAR(connector->tags_evaltype, row[19]);
		connector->item_value_type = atoi(row[20]);
		dc_strpool_replace(found, &connector->attempt_interval, row[21]);
	}

	/* remove deleted connectors */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (connector = (zbx_dc_connector_t *)zbx_hashset_search(&config->connectors, &rowid)))
			continue;

		zbx_vector_dc_connector_tag_destroy(&connector->tags);
		dc_strpool_release(connector->url);
		dc_strpool_release(connector->timeout);
		dc_strpool_release(connector->token);
		dc_strpool_release(connector->http_proxy);
		dc_strpool_release(connector->username);
		dc_strpool_release(connector->password);
		dc_strpool_release(connector->ssl_cert_file);
		dc_strpool_release(connector->ssl_key_file);
		dc_strpool_release(connector->ssl_key_password);
		dc_strpool_release(connector->attempt_interval);

		zbx_hashset_remove_direct(&config->connectors, connector);
	}

	if (0 != sync->add_num || 0 != sync->update_num || 0 != sync->remove_num)
		config->revision.connector = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare connector tags by tag name for sorting                    *
 *                                                                            *
 ******************************************************************************/
static int	dc_compare_connector_tags(const void *d1, const void *d2)
{
	const zbx_dc_connector_tag_t	*tag1 = *(const zbx_dc_connector_tag_t * const *)d1;
	const zbx_dc_connector_tag_t	*tag2 = *(const zbx_dc_connector_tag_t * const *)d2;

	return strcmp(tag1->tag, tag2->tag);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates connector tags in configuration cache                     *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_connector_tags(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		connectortagid, connectorid;
	zbx_dc_connector_tag_t	*connector_tag;
	zbx_dc_connector_t	*connector;
	zbx_vector_ptr_t	connectors;
	int			found, ret, index, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_ptr_create(&connectors);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(connectorid, row[1]);
		if (NULL == (connector = (zbx_dc_connector_t *)zbx_hashset_search(&config->connectors,
				&connectorid)))
		{
			continue;
		}

		ZBX_STR2UINT64(connectortagid, row[0]);
		connector_tag = (zbx_dc_connector_tag_t *)DCfind_id(&config->connector_tags, connectortagid,
				sizeof(zbx_dc_connector_tag_t), &found);

		connector_tag->connectorid = connectorid;
		ZBX_STR2UCHAR(connector_tag->op, row[2]);
		dc_strpool_replace(found, &connector_tag->tag, row[3]);
		dc_strpool_replace(found, &connector_tag->value, row[4]);

		if (0 == found)
			zbx_vector_dc_connector_tag_append(&connector->tags, connector_tag);

		zbx_vector_ptr_append(&connectors, connector);
	}

	/* remove deleted connector tags */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (connector_tag = (zbx_dc_connector_tag_t *)zbx_hashset_search(&config->connector_tags,
				&rowid)))
		{
			continue;
		}

		if (NULL != (connector = (zbx_dc_connector_t *)zbx_hashset_search(&config->connectors,
				&connector_tag->connectorid)))
		{
			index = zbx_vector_dc_connector_tag_search(&connector->tags, connector_tag,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL != index)
				zbx_vector_dc_connector_tag_remove_noorder(&connector->tags, index);

			zbx_vector_ptr_append(&connectors, connector);
		}

		dc_strpool_release(connector_tag->tag);
		dc_strpool_release(connector_tag->value);

		zbx_hashset_remove_direct(&config->connector_tags, connector_tag);
	}

	/* sort connector tags */

	zbx_vector_ptr_sort(&connectors, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(&connectors, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < connectors.values_num; i++)
	{
		connector = (zbx_dc_connector_t *)connectors.values[i];
		zbx_vector_dc_connector_tag_sort(&connector->tags, dc_compare_connector_tags);
	}

	zbx_vector_ptr_destroy(&connectors);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	DCsync_proxies(zbx_dbsync_t *sync, zbx_uint64_t revision, const zbx_config_vault_t *config_vault,
		int proxyconfig_frequency, zbx_hashset_t *psk_owners)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;

	ZBX_DC_PROXY		*proxy;
	zbx_dc_proxy_name_t	proxy_p_local, *proxy_p;

	int			found, update_index_p, ret;
	zbx_uint64_t		proxyid, proxy_groupid;
	unsigned char		mode, custom_timeouts;
	time_t			now;
	char			*version_str;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	now = time(NULL);

	version_str = zbx_strdup(NULL, ZBX_VERSION_UNDEFINED_STR);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(proxyid, row[0]);
		ZBX_STR2UCHAR(mode, row[2]);
		ZBX_STR2UCHAR(custom_timeouts, row[22]);

		proxy = (ZBX_DC_PROXY *)DCfind_id(&config->proxies, proxyid, sizeof(ZBX_DC_PROXY), &found);

		ZBX_DBROW2UINT64(proxy_groupid, row[23]);

		/* see whether we should and can update 'proxies_p' indexes at this point */

		update_index_p = 0;

		if (0 == found || 0 != strcmp(proxy->name, row[1]))
		{
			if (1 == found)
			{
				proxy_p_local.name = proxy->name;
				proxy_p = (zbx_dc_proxy_name_t *)zbx_hashset_search(&config->proxies_p, &proxy_p_local);

				if (NULL != proxy_p && proxy == proxy_p->proxy_ptr)
				{
					dc_strpool_release(proxy_p->name);
					zbx_hashset_remove_direct(&config->proxies_p, proxy_p);
				}
			}

			proxy_p_local.name = row[1];
			proxy_p = (zbx_dc_proxy_name_t *)zbx_hashset_search(&config->proxies_p, &proxy_p_local);

			if (NULL != proxy_p)
				proxy_p->proxy_ptr = proxy;
			else
				update_index_p = 1;
		}

		/* store new information in proxy structure */

		dc_strpool_replace(found, &proxy->name, row[1]);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		dc_strpool_replace(found, &proxy->tls_issuer, row[5]);
		dc_strpool_replace(found, &proxy->tls_subject, row[6]);

		proxy->tls_dc_psk = dc_psk_sync(row[7], row[8], proxy->name, found, psk_owners, proxy->tls_dc_psk);
#else
		ZBX_UNUSED(psk_owners);
#endif
		ZBX_STR2UCHAR(proxy->tls_connect, row[3]);
		ZBX_STR2UCHAR(proxy->tls_accept, row[4]);

		if ((PROXY_OPERATING_MODE_PASSIVE == mode && 0 != (ZBX_TCP_SEC_UNENCRYPTED & proxy->tls_connect)) ||
				(PROXY_OPERATING_MODE_ACTIVE == mode && 0 != (ZBX_TCP_SEC_UNENCRYPTED & proxy->tls_accept)))
		{
			if (NULL != config_vault->token || NULL != config_vault->name)
			{
				zabbix_log(LOG_LEVEL_WARNING, "connection with Zabbix proxy \"%s\" should not be"
						" unencrypted when using Vault", proxy->name);
			}
		}

		proxy->proxyid = proxyid;

		if (1 == update_index_p)
		{
			proxy_p_local.name = dc_strpool_acquire(proxy->name);
			proxy_p_local.proxy_ptr = proxy;
			zbx_hashset_insert(&config->proxies_p, &proxy_p_local, sizeof(zbx_dc_proxy_name_t));
		}

		if (0 == found)
		{
			proxy->location = ZBX_LOC_NOWHERE;
			proxy->version_int = ZBX_COMPONENT_VERSION_UNDEFINED;
			dc_strpool_replace(found, &proxy->version_str, version_str);
			proxy->compatibility = ZBX_PROXY_VERSION_UNDEFINED;
			proxy->lastaccess = (SUCCEED != zbx_db_is_null(row[12]) ? atoi(row[12]) : 0);
			proxy->last_cfg_error_time = 0;
			proxy->proxy_delay = 0;
			proxy->nodata_win.flags = ZBX_PROXY_SUPPRESS_DISABLE;
			proxy->nodata_win.values_num = 0;
			proxy->nodata_win.period_end = 0;
			proxy->proxy_groupid = 0;

			zbx_vector_dc_host_ptr_create_ext(&proxy->hosts, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);
			zbx_vector_host_rev_create_ext(&proxy->removed_hosts, __config_shmem_malloc_func,
					__config_shmem_realloc_func, __config_shmem_free_func);

		}

		proxy->custom_timeouts = custom_timeouts;

		dc_strpool_replace(found, &proxy->allowed_addresses, row[9]);
		dc_strpool_replace(found, &proxy->address, row[10]);
		dc_strpool_replace(found, &proxy->port, row[11]);

		/* in the case of local address change for proxy in a proxy group */
		/* force re-sync of proxy lists to group proxies                  */
		if (0 != proxy_groupid && 0 != found && (0 != strcmp(proxy->local_address, row[24]) ||
				0 != strcmp(proxy->local_port, row[25])))
		{
			zbx_dc_proxy_group_t	*pg;

			if (NULL != (pg = (zbx_dc_proxy_group_t *)zbx_hashset_search(&config->proxy_groups,
					&proxy_groupid)))
			{
				pg->revision = revision;
				config->revision.proxy_group = revision;
			}
		}

		dc_strpool_replace(found, &proxy->local_address, row[24]);
		dc_strpool_replace(found, &proxy->local_port, row[25]);

		dc_strpool_replace(found, &proxy->item_timeouts.agent, row[13]);
		dc_strpool_replace(found, &proxy->item_timeouts.simple, row[14]);
		dc_strpool_replace(found, &proxy->item_timeouts.snmp, row[15]);
		dc_strpool_replace(found, &proxy->item_timeouts.external, row[16]);
		dc_strpool_replace(found, &proxy->item_timeouts.odbc, row[17]);
		dc_strpool_replace(found, &proxy->item_timeouts.http, row[18]);
		dc_strpool_replace(found, &proxy->item_timeouts.ssh, row[19]);
		dc_strpool_replace(found, &proxy->item_timeouts.telnet, row[20]);
		dc_strpool_replace(found, &proxy->item_timeouts.script, row[21]);
		dc_strpool_replace(found, &proxy->item_timeouts.browser, row[26]);

		if (PROXY_OPERATING_MODE_PASSIVE == mode && (0 == found || mode != proxy->mode))
		{
			proxy->proxy_config_nextcheck = (int)calculate_proxy_nextcheck(proxyid, proxyconfig_frequency,
					now);
			proxy->proxy_data_nextcheck = (int)calculate_proxy_nextcheck(proxyid, proxyconfig_frequency,
					now);
			proxy->proxy_tasks_nextcheck = (int)calculate_proxy_nextcheck(proxyid,
					ZBX_TASK_UPDATE_FREQUENCY, now);

			DCupdate_proxy_queue(proxy);
		}
		else if (PROXY_OPERATING_MODE_ACTIVE == mode && ZBX_LOC_QUEUE == proxy->location)
		{
			zbx_binary_heap_remove_direct(&config->pqueue, proxy->proxyid);
			proxy->location = ZBX_LOC_NOWHERE;
		}

		proxy->last_version_error_time = time(NULL);

		proxy->mode = mode;
		proxy->proxy_groupid = proxy_groupid;

		proxy->revision = revision;
	}

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &rowid)))
			continue;

		DCsync_proxy_remove(proxy);
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		config->revision.proxy = revision;

	zbx_free(version_str);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_dc_config_get_hostids_by_revision(zbx_uint64_t new_revision, zbx_vector_uint64_t *hostids)
{
	zbx_hashset_iter_t	iter;
	const ZBX_DC_HOST	*dc_host;
	zbx_uint64_t		global_revision = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == um_cache_get_host_revision(config->um_cache, 0, &global_revision) &&
			global_revision >= new_revision)
	{
		zbx_vector_uint64_append(hostids, 0);
	}

	zbx_hashset_iter_reset(&config->hosts, &iter);
	while (NULL != (dc_host = (const ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		zbx_uint64_t	revision = 0;

		if (dc_host->revision >= new_revision)
		{
			zbx_vector_uint64_append(hostids, dc_host->hostid);
			continue;
		}

		if (SUCCEED == um_cache_get_host_revision(config->um_cache, dc_host->hostid, &revision) &&
				revision >= new_revision)
		{
			zbx_vector_uint64_append(hostids, dc_host->hostid);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new items with triggers to value cache                        *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_new_items_to_valuecache(const zbx_vector_dc_item_ptr_t *items)
{
	if (0 != items->values_num)
	{
		zbx_vector_uint64_pair_t	vc_items;
		int				i;

		zbx_vector_uint64_pair_create(&vc_items);
		zbx_vector_uint64_pair_reserve(&vc_items, (size_t)items->values_num);

		for (i = 0; i < items->values_num; i++)
		{
			if (0 != items->values[i]->update_triggers)
			{
				zbx_uint64_pair_t	pair = {
						.first = items->values[i]->itemid,
						.second = (zbx_uint64_t)items->values[i]->value_type
				};

				zbx_vector_uint64_pair_append_ptr(&vc_items, &pair);
			}
		}

		if (0 != vc_items.values_num)
			zbx_vc_add_new_items(&vc_items);

		zbx_vector_uint64_pair_destroy(&vc_items);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new items with triggers to trend cache                        *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_new_items_to_trends(const zbx_vector_dc_item_ptr_t *items)
{
	if (0 != items->values_num)
	{
		zbx_vector_uint64_t	itemids;
		int			i;

		zbx_vector_uint64_create(&itemids);
		zbx_vector_uint64_reserve(&itemids, (size_t)items->values_num);

		for (i = 0; i < items->values_num; i++)
		{
			ZBX_DC_ITEM	*item = items->values[i];

			if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
				continue;

			ZBX_DC_NUMITEM	*numitem;

			numitem = item->itemvaluetype.numitem;

			if (NULL == numitem)
				continue;

			const char	*value = numitem->trends_period;

			if (0 == strncmp(numitem->trends_period, "{$", ZBX_CONST_STRLEN("{$")))
			{
				um_cache_resolve_const(config->um_cache, &item->hostid, 1, numitem->trends_period,
						ZBX_MACRO_ENV_NONSECURE, &value);
			}

			if (0 == zbx_dc_config_history_get_trends_sec(value, config->config->hk.trends_global,
					config->config->hk.trends))
			{
				continue;
			}

			zbx_vector_uint64_append(&itemids, items->values[i]->itemid);
		}

		if (0 != itemids.values_num)
			zbx_trend_add_new_items(&itemids);

		zbx_vector_uint64_destroy(&itemids);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Synchronize configuration data from database                      *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dc_sync_configuration(unsigned char mode, zbx_synced_new_config_t synced,
		zbx_vector_uint64_t *deleted_itemids, const zbx_config_vault_t *config_vault, int proxyconfig_frequency)
{
	static int	sync_status = ZBX_DBSYNC_STATUS_UNKNOWN;

	int		i, flags, changelog_num, dberr = ZBX_DB_FAIL;
	double		sec, update_sec, queues_sec, changelog_sec;

	zbx_dbsync_t	config_sync, hosts_sync, hi_sync, htmpl_sync, gmacro_sync, hmacro_sync, if_sync, items_sync,
			item_discovery_sync, triggers_sync, tdep_sync,
			func_sync, expr_sync, action_sync, action_op_sync, action_condition_sync, trigger_tag_sync,
			item_tag_sync, host_tag_sync, correlation_sync, corr_condition_sync, corr_operation_sync,
			hgroups_sync, itempp_sync, itemscrp_sync, maintenance_sync, maintenance_period_sync,
			maintenance_tag_sync, maintenance_group_sync, maintenance_host_sync, hgroup_host_sync,
			drules_sync, dchecks_sync, httptest_sync, httptest_field_sync, httpstep_sync,
			httpstep_field_sync, autoreg_host_sync, connector_sync, connector_tag_sync, proxy_sync,
			proxy_group_sync, hp_sync, autoreg_config_sync;
	zbx_uint64_t	update_flags = 0;
	zbx_int64_t	used_size, update_size;
	unsigned char	changelog_sync_mode = mode;	/* sync mode for objects using incremental sync */

	zbx_hashset_t			trend_queue;
	zbx_vector_uint64_t		active_avail_diff;
	zbx_hashset_t			activated_hosts;
	zbx_uint64_t			new_revision = config->revision.config + 1;
	int				connectors_num = 0;
	zbx_hashset_t			psk_owners;
	zbx_vector_objmove_t		pg_host_reloc, *pg_host_reloc_ref;
	zbx_vector_dc_item_ptr_t	new_items, *pnew_items = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create(&activated_hosts, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (ZBX_DBSYNC_INIT == mode)
	{
		zbx_hashset_create(&trend_queue, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		dc_load_trigger_queue(&trend_queue);
	}
	else if (ZBX_DBSYNC_STATUS_INITIALIZED != sync_status)
	{
		changelog_sync_mode = ZBX_DBSYNC_INIT;
	}
	else if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
	{
		zbx_vector_dc_item_ptr_create(&new_items);
		pnew_items = &new_items;
	}

	if (ZBX_DBSYNC_INIT != changelog_sync_mode && 0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
	{
		/* track host - proxy group relocations only during incremental sync */
		zbx_vector_objmove_create(&pg_host_reloc);
		pg_host_reloc_ref = &pg_host_reloc;

	}
	else
		pg_host_reloc_ref = NULL;

	sec = zbx_time();
	changelog_num = zbx_dbsync_env_prepare(changelog_sync_mode);
	changelog_sec = zbx_time() - sec;

	/* global configuration must be synchronized directly with database */
	zbx_dbsync_init(&config_sync, "config", ZBX_DBSYNC_INIT);

	zbx_dbsync_init(&autoreg_config_sync, "config_autoreg_tls", mode);
	zbx_dbsync_init(&autoreg_host_sync, "autoreg_host", mode);
	zbx_dbsync_init_changelog(&proxy_group_sync, "proxy_group", changelog_sync_mode);
	zbx_dbsync_init_changelog(&hosts_sync, "hosts", changelog_sync_mode);
	zbx_dbsync_init_changelog(&hp_sync, "host_proxy", changelog_sync_mode);
	zbx_dbsync_init(&hi_sync, "host_inventory", mode);
	zbx_dbsync_init(&htmpl_sync, "hosts_templates", mode);
	zbx_dbsync_init(&gmacro_sync, "globalmacro", mode);
	zbx_dbsync_init(&hmacro_sync, "hostmacro", mode);
	zbx_dbsync_init(&if_sync, "interface", mode);
	zbx_dbsync_init_changelog(&items_sync, "items", changelog_sync_mode);
	zbx_dbsync_init(&item_discovery_sync, "item_discovery", mode);
	zbx_dbsync_init_changelog(&triggers_sync, "triggers", changelog_sync_mode);
	zbx_dbsync_init(&tdep_sync, "trigger_depends", mode);
	zbx_dbsync_init_changelog(&func_sync, "functions", changelog_sync_mode);
	zbx_dbsync_init(&expr_sync, "regexps", mode);
	zbx_dbsync_init(&action_sync, "actions", mode);

	/* Action operation sync produces virtual rows with two columns - actionid, opflags. */
	/* Because of this it cannot return the original database select and must always be  */
	/* initialized in update mode.                                                       */
	zbx_dbsync_init(&action_op_sync, "operations", ZBX_DBSYNC_UPDATE);

	zbx_dbsync_init(&action_condition_sync, "conditions", mode);
	zbx_dbsync_init_changelog(&trigger_tag_sync, "trigger_tag", changelog_sync_mode);
	zbx_dbsync_init_changelog(&item_tag_sync, "item_tag", changelog_sync_mode);
	zbx_dbsync_init_changelog(&host_tag_sync, "host_tag", changelog_sync_mode);
	zbx_dbsync_init(&correlation_sync, "correlation", mode);
	zbx_dbsync_init(&corr_condition_sync, "corr_condition", mode);
	zbx_dbsync_init(&corr_operation_sync, "corr_operation", mode);
	zbx_dbsync_init(&hgroups_sync, "hstgrp", mode);
	zbx_dbsync_init(&hgroup_host_sync, "hosts_groups", mode);
	zbx_dbsync_init_changelog(&itempp_sync, "item_preproc", changelog_sync_mode);
	zbx_dbsync_init(&itemscrp_sync, "item_parameter", mode);

	zbx_dbsync_init(&maintenance_sync, "maintenances", mode);
	zbx_dbsync_init(&maintenance_period_sync, "maintenances_windows", mode);
	zbx_dbsync_init(&maintenance_tag_sync, "maintenance_tag",  mode);
	zbx_dbsync_init(&maintenance_group_sync, "maintenances_groups", mode);
	zbx_dbsync_init(&maintenance_host_sync, "maintenances_hosts", mode);

	zbx_dbsync_init_changelog(&drules_sync, "drules", changelog_sync_mode);
	zbx_dbsync_init_changelog(&dchecks_sync, "dchecks", changelog_sync_mode);

	zbx_dbsync_init_changelog(&httptest_sync, "httptest", changelog_sync_mode);
	zbx_dbsync_init_changelog(&httptest_field_sync, "httptest_field", changelog_sync_mode);
	zbx_dbsync_init_changelog(&httpstep_sync, "httpstep", changelog_sync_mode);
	zbx_dbsync_init_changelog(&httpstep_field_sync, "httpstep_field", changelog_sync_mode);

	zbx_dbsync_init_changelog(&connector_sync, "connector", changelog_sync_mode);
	zbx_dbsync_init_changelog(&connector_tag_sync, "connector_tag", changelog_sync_mode);

	zbx_dbsync_init_changelog(&proxy_sync, "proxy", changelog_sync_mode);

	if (FAIL == zbx_dbsync_compare_config(&config_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_autoreg_psk(&autoreg_config_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_autoreg_host(&autoreg_host_sync))
		goto out;

	if (FAIL == zbx_dbsync_prepare_proxy_group(&proxy_group_sync))
		goto out;

	/* sync global configuration settings */
	START_SYNC;

	DCsync_config(&config_sync, new_revision, &flags);

	/* must be done in the same cache locking with config sync */
	DCsync_autoreg_config(&autoreg_config_sync, new_revision);

	DCsync_autoreg_host(&autoreg_host_sync);

	dc_sync_proxy_group(&proxy_group_sync, new_revision);

	FINISH_SYNC;

	/* sync macro related data, to support macro resolving during configuration sync */

	if (FAIL == zbx_dbsync_compare_host_templates(&htmpl_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_global_macros(&gmacro_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_host_macros(&hmacro_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_host_tags(&host_tag_sync))
		goto out;

	START_SYNC;

	config->um_cache = um_cache_sync(config->um_cache, new_revision, &gmacro_sync, &hmacro_sync, &htmpl_sync,
			config_vault, get_program_type_cb());

	DCsync_host_tags(&host_tag_sync);

	FINISH_SYNC;

	/* postpone configuration sync until macro secrets are received from Zabbix server */
	if (0 == (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER) && 0 != config->kvs_paths.values_num &&
			ZBX_DBSYNC_INIT == mode)
	{
		goto clean;
	}

	/* sync host data to support host lookups when resolving macros during configuration sync */
	if (FAIL == zbx_dbsync_compare_proxies(&proxy_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_hosts(&hosts_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_host_inventory(&hi_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_host_groups(&hgroups_sync))
		goto out;
	if (FAIL == zbx_dbsync_compare_host_group_hosts(&hgroup_host_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_maintenances(&maintenance_sync))
		goto out;
	if (FAIL == zbx_dbsync_compare_maintenance_tags(&maintenance_tag_sync))
		goto out;
	if (FAIL == zbx_dbsync_compare_maintenance_periods(&maintenance_period_sync))
		goto out;
	if (FAIL == zbx_dbsync_compare_maintenance_groups(&maintenance_group_sync))
		goto out;
	if (FAIL == zbx_dbsync_compare_maintenance_hosts(&maintenance_host_sync))
		goto out;

	if (FAIL == zbx_dbsync_prepare_drules(&drules_sync))
		goto out;
	if (FAIL == zbx_dbsync_prepare_dchecks(&dchecks_sync))
		goto out;

	if (FAIL == zbx_dbsync_prepare_httptests(&httptest_sync))
		goto out;
	if (FAIL == zbx_dbsync_prepare_httptest_fields(&httptest_field_sync))
		goto out;
	if (FAIL == zbx_dbsync_prepare_httpsteps(&httpstep_sync))
		goto out;
	if (FAIL == zbx_dbsync_prepare_httpstep_fields(&httpstep_field_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_connectors(&connector_sync))
		goto out;
	if (FAIL == zbx_dbsync_compare_connector_tags(&connector_tag_sync))
		goto out;

	if (FAIL == zbx_dbsync_prepare_host_proxy(&hp_sync))
		goto out;

	zbx_hashset_create(&psk_owners, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	START_SYNC;

	DCsync_proxies(&proxy_sync, new_revision, config_vault, proxyconfig_frequency, &psk_owners);

	zbx_vector_uint64_create(&active_avail_diff);
	DCsync_hosts(&hosts_sync, new_revision, &active_avail_diff, &activated_hosts, &psk_owners, pg_host_reloc_ref);
	zbx_dbsync_clear_user_macros();

	DCsync_host_inventory(&hi_sync, new_revision);

	DCsync_hostgroups(&hgroups_sync);
	DCsync_hostgroup_hosts(&hgroup_host_sync);

	DCsync_maintenances(&maintenance_sync);
	DCsync_maintenance_tags(&maintenance_tag_sync);
	DCsync_maintenance_groups(&maintenance_group_sync);
	DCsync_maintenance_hosts(&maintenance_host_sync);
	DCsync_maintenance_periods(&maintenance_period_sync);

	if (0 != hgroups_sync.add_num + hgroups_sync.update_num + hgroups_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_HOST_GROUPS;

	if (0 != maintenance_group_sync.add_num + maintenance_group_sync.update_num + maintenance_group_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_MAINTENANCE_GROUPS;

	if (0 != (update_flags & ZBX_DBSYNC_UPDATE_HOST_GROUPS))
		dc_hostgroups_update_cache();

	/* pre-cache nested groups used in maintenances to allow read lock */
	/* during host maintenance update calculations                     */
	if (0 != (update_flags & (ZBX_DBSYNC_UPDATE_HOST_GROUPS | ZBX_DBSYNC_UPDATE_MAINTENANCE_GROUPS)))
		dc_maintenance_precache_nested_groups();

	DCsync_connectors(&connector_sync, new_revision);
	DCsync_connector_tags(&connector_tag_sync);

	dc_sync_host_proxy(&hp_sync, new_revision);

	FINISH_SYNC;

	zbx_hashset_destroy(&psk_owners);

	zbx_dbsync_process_active_avail_diff(&active_avail_diff);
	zbx_vector_uint64_destroy(&active_avail_diff);

	/* sync item data to support item lookups when resolving macros during configuration sync */

	if (FAIL == zbx_dbsync_compare_interfaces(&if_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_items(&items_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_item_discovery(&item_discovery_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_item_preprocs(&itempp_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_item_script_param(&itemscrp_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_functions(&func_sync))
		goto out;

	START_SYNC;

	/* resolves macros for interface_snmpaddrs, must be after DCsync_hmacros() */
	DCsync_interfaces(&if_sync, new_revision);

	/* relies on hosts, proxies and interfaces, must be after DCsync_{hosts,interfaces}() */
	DCsync_items(&items_sync, new_revision, flags, synced, deleted_itemids, pnew_items);
	DCsync_item_discovery(&item_discovery_sync);

	/* relies on items, must be after DCsync_items() */
	DCsync_item_preproc(&itempp_sync, new_revision);

	/* relies on items, must be after DCsync_items() */
	DCsync_items_param(&itemscrp_sync, new_revision);

	DCsync_functions(&func_sync, new_revision);

	FINISH_SYNC;

	if (NULL != pnew_items)
	{
		dc_add_new_items_to_valuecache(pnew_items);
		dc_add_new_items_to_trends(pnew_items);
	}

	zbx_dc_flush_history();	/* misconfigured items generate pseudo-historic values to become notsupported */

	/* sync rest of the data */
	if (FAIL == zbx_dbsync_compare_triggers(&triggers_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_trigger_dependency(&tdep_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_expressions(&expr_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_actions(&action_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_action_ops(&action_op_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_action_conditions(&action_condition_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_trigger_tags(&trigger_tag_sync))
		goto out;

	/* relies on items, must be after DCsync_items() */
	if (FAIL == zbx_dbsync_compare_item_tags(&item_tag_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_correlations(&correlation_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_corr_conditions(&corr_condition_sync))
		goto out;

	if (FAIL == zbx_dbsync_compare_corr_operations(&corr_operation_sync))
		goto out;

	START_SYNC;

	DCsync_triggers(&triggers_sync, new_revision);
	DCsync_trigdeps(&tdep_sync);

	DCsync_expressions(&expr_sync, new_revision);

	DCsync_actions(&action_sync);
	DCsync_action_ops(&action_op_sync);
	DCsync_action_conditions(&action_condition_sync);

	/* relies on triggers, must be after DCsync_triggers() */
	DCsync_trigger_tags(&trigger_tag_sync);

	DCsync_item_tags(&item_tag_sync);

	DCsync_correlations(&correlation_sync);

	/* relies on correlation rules, must be after DCsync_correlations() */
	DCsync_corr_conditions(&corr_condition_sync);
	/* relies on correlation rules, must be after DCsync_correlations() */
	DCsync_corr_operations(&corr_operation_sync);

	dc_sync_drules(&drules_sync, new_revision);
	dc_sync_dchecks(&dchecks_sync, new_revision);

	dc_sync_httptests(&httptest_sync, new_revision);
	dc_sync_httptest_fields(&httptest_field_sync, new_revision);
	dc_sync_httpsteps(&httpstep_sync, new_revision);
	dc_sync_httpstep_fields(&httpstep_field_sync, new_revision);

	sec = zbx_time();
	used_size = dbconfig_used_size();

	if (0 != hosts_sync.add_num + hosts_sync.update_num + hosts_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_HOSTS;

	if (0 != items_sync.add_num + items_sync.update_num + items_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_ITEMS;

	if (0 != func_sync.add_num + func_sync.update_num + func_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_FUNCTIONS;

	if (0 != triggers_sync.add_num + triggers_sync.update_num + triggers_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_TRIGGERS;

	if (0 != tdep_sync.add_num + tdep_sync.update_num + tdep_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_TRIGGER_DEPENDENCY;

	if (0 != gmacro_sync.add_num + gmacro_sync.update_num + gmacro_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_MACROS;

	if (0 != hmacro_sync.add_num + hmacro_sync.update_num + hmacro_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_MACROS;

	if (0 != htmpl_sync.add_num + htmpl_sync.update_num + htmpl_sync.remove_num)
		update_flags |= ZBX_DBSYNC_UPDATE_MACROS;

	if (0 != connector_sync.add_num + connector_sync.update_num + connector_sync.remove_num +
			connector_tag_sync.add_num + connector_tag_sync.update_num + connector_tag_sync.remove_num)
	{
		connectors_num = config->connectors.num_data;
	}

	/* update trigger topology if trigger dependency was changed */
	if (0 != (update_flags & ZBX_DBSYNC_UPDATE_TRIGGER_DEPENDENCY))
		dc_trigger_update_topology();

	/* update various trigger related links in cache */
	if (0 != (update_flags & (ZBX_DBSYNC_UPDATE_HOSTS | ZBX_DBSYNC_UPDATE_ITEMS | ZBX_DBSYNC_UPDATE_FUNCTIONS |
			ZBX_DBSYNC_UPDATE_TRIGGERS | ZBX_DBSYNC_UPDATE_MACROS)))
	{
		dc_trigger_update_cache();
		dc_schedule_trigger_timers((ZBX_DBSYNC_INIT == mode ? &trend_queue : NULL), time(NULL));
	}

	update_sec = zbx_time() - sec;
	update_size = dbconfig_used_size() - used_size;

	config->revision.config = new_revision;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() changelog  : sql:" ZBX_FS_DBL " sec (%d records)",
				__func__, changelog_sec, changelog_num);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() reindex    : " ZBX_FS_DBL " sec " ZBX_FS_I64 " bytes.", __func__,
				update_sec, update_size);

		zbx_dcsync_stats_dump(__func__);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() proxies    : %d (%d slots)", __func__,
				config->proxies.num_data, config->proxies.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() proxies_p    : %d (%d slots)", __func__,
				config->proxies_p.num_data, config->proxies_p.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts      : %d (%d slots)", __func__,
				config->hosts.num_data, config->hosts.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hosts_h    : %d (%d slots)", __func__,
				config->hosts_h.num_data, config->hosts_h.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() autoreg_hosts: %d (%d slots)", __func__,
				config->autoreg_hosts.num_data, config->autoreg_hosts.num_slots);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() psks       : %d (%d slots)", __func__,
				config->psks.num_data, config->psks.num_slots);
#endif
		zabbix_log(LOG_LEVEL_DEBUG, "%s() ipmihosts  : %d (%d slots)", __func__,
				config->ipmihosts.num_data, config->ipmihosts.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() host_invent: %d (%d slots)", __func__,
				config->host_inventories.num_data, config->host_inventories.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() glob macros: %d (%d slots)", __func__,
				config->gmacros.num_data, config->gmacros.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() host macros: %d (%d slots)", __func__,
				config->hmacros.num_data, config->hmacros.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() kvs_paths : %d", __func__, config->kvs_paths.values_num);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() interfaces : %d (%d slots)", __func__,
				config->interfaces.num_data, config->interfaces.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() interfaces_snmp : %d (%d slots)", __func__,
				config->interfaces_snmp.num_data, config->interfaces_snmp.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() interfac_ht: %d (%d slots)", __func__,
				config->interfaces_ht.num_data, config->interfaces_ht.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() if_snmpitms: %d (%d slots)", __func__,
				config->interface_snmpitems.num_data, config->interface_snmpitems.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() if_snmpaddr: %d (%d slots)", __func__,
				config->interface_snmpaddrs.num_data, config->interface_snmpaddrs.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() item_discovery : %d (%d slots)", __func__,
				config->item_discovery.num_data, config->item_discovery.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() items      : %d (%d slots)", __func__,
				config->items.num_data, config->items.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() items_hk   : %d (%d slots)", __func__,
				config->items_hk.num_data, config->items_hk.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() template_items   : %d (%d slots)", __func__,
				config->template_items.num_data, config->template_items.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() preprocitems: %d (%d slots)", __func__,
				config->preprocops.num_data, config->preprocops.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() item_tag_links: %d (%d slots)", __func__,
				config_private.item_tag_links.num_data, config_private.item_tag_links.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() functions  : %d (%d slots)", __func__,
				config->functions.num_data, config->functions.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() triggers   : %d (%d slots)", __func__,
				config->triggers.num_data, config->triggers.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigdeps   : %d (%d slots)", __func__,
				config->trigdeps.num_data, config->trigdeps.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trig. tags : %d (%d slots)", __func__,
				config->trigger_tags.num_data, config->trigger_tags.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() expressions: %d (%d slots)", __func__,
				config->expressions.num_data, config->expressions.num_slots);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() actions    : %d (%d slots)", __func__,
				config->actions.num_data, config->actions.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() conditions : %d (%d slots)", __func__,
				config->action_conditions.num_data, config->action_conditions.num_slots);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr.      : %d (%d slots)", __func__,
				config->correlations.num_data, config->correlations.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr. conds: %d (%d slots)", __func__,
				config->corr_conditions.num_data, config->corr_conditions.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() corr. ops  : %d (%d slots)", __func__,
				config->corr_operations.num_data, config->corr_operations.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hgroups    : %d (%d slots)", __func__,
				config->hostgroups.num_data, config->hostgroups.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() item procs : %d (%d slots)", __func__,
				config->preprocops.num_data, config->preprocops.num_slots);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() maintenance: %d (%d slots)", __func__,
				config->maintenances.num_data, config->maintenances.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() maint tags : %d (%d slots)", __func__,
				config->maintenance_tags.num_data, config->maintenance_tags.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() maint time : %d (%d slots)", __func__,
				config->maintenance_periods.num_data, config->maintenance_periods.num_slots);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() drules     : %d (%d slots)", __func__,
				config->drules.num_data, config->drules.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() dchecks    : %d (%d slots)", __func__,
				config->dchecks.num_data, config->dchecks.num_slots);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() httptests  : %d (%d slots)", __func__,
				config->httptests.num_data, config->httptests.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() httptestfld: %d (%d slots)", __func__,
				config->httptest_fields.num_data, config->httptest_fields.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() httpsteps  : %d (%d slots)", __func__,
				config->httpsteps.num_data, config->httpsteps.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() httpstepfld: %d (%d slots)", __func__,
				config->httpstep_fields.num_data, config->httpstep_fields.num_slots);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() connector: %d (%d slots)", __func__,
				config->connectors.num_data, config->connectors.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() connector tags : %d (%d slots)", __func__,
				config->connector_tags.num_data, config->connector_tags.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() proxy groups : %d (%d slots)", __func__,
				config->proxy_groups.num_data, config->proxy_groups.num_slots);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() host proxy   : %d (%d slots)", __func__,
				config->host_proxy.num_data, config->host_proxy.num_slots);

		for (i = 0; ZBX_POLLER_TYPE_COUNT > i; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() queue[%d]   : %d (%d allocated)", __func__,
					i, config->queues[i].elems_num, config->queues[i].elems_alloc);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() pqueue     : %d (%d allocated)", __func__,
				config->pqueue.elems_num, config->pqueue.elems_alloc);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() timer queue: %d (%d allocated)", __func__,
				config->trigger_queue.elems_num, config->trigger_queue.elems_alloc);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() changelog  : %d", __func__, zbx_dbsync_env_changelog_num());

		zabbix_log(LOG_LEVEL_DEBUG, "%s() configfree : " ZBX_FS_DBL "%%", __func__,
				100 * ((double)config_mem->free_size / config_mem->orig_size));

		zabbix_log(LOG_LEVEL_DEBUG, "%s() strings    : %d (%d slots)", __func__,
				config->strpool.num_data, config->strpool.num_slots);

		zbx_shmem_dump_stats(LOG_LEVEL_DEBUG, config_mem);
	}

	dberr = ZBX_DB_OK;
out:
	if (0 == sync_in_progress)
		START_SYNC;

	config->status->last_update = 0;
	config->sync_ts = time(NULL);

	if (0 == (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
		dc_update_proxy_failover_delay();

	FINISH_SYNC;

	switch (dberr)
	{
		case ZBX_DB_OK:
			if (ZBX_DBSYNC_INIT != changelog_sync_mode)
			{
				zbx_dbsync_env_flush_changelog();
			}
			else
			{
				/* set changelog initialized only if database records were synced and */
				/* next time differential sync must be used                           */
				if (SUCCEED == zbx_dbsync_env_changelog_dbsyncs_new_records())
				{
					sync_status = ZBX_DBSYNC_STATUS_INITIALIZED;
					zabbix_log(LOG_LEVEL_DEBUG, "initialized changelog support");
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "skipped changelog support initialization"
							" because of empty database");
				}

			}
			break;
		case ZBX_DB_FAIL:
			/* non recoverable database error is encountered */
			THIS_SHOULD_NEVER_HAPPEN;
			break;
		case ZBX_DB_DOWN:
			zabbix_log(LOG_LEVEL_WARNING, "Configuration cache has not been fully initialized because of"
					" database connection problems. Full database scan will be attempted on next"
					" sync.");
			break;
	}

	if (0 != (update_flags & (ZBX_DBSYNC_UPDATE_HOSTS | ZBX_DBSYNC_UPDATE_ITEMS | ZBX_DBSYNC_UPDATE_MACROS)))
	{
		sec = zbx_time();

		dc_reschedule_items(&activated_hosts);

		if (0 != activated_hosts.num_data)
			dc_reschedule_httptests(&activated_hosts);

		queues_sec = zbx_time() - sec;
		zabbix_log(LOG_LEVEL_DEBUG, "%s() reschedule : " ZBX_FS_DBL " sec.", __func__, queues_sec);
	}

	if (0 != connectors_num && FAIL == zbx_connector_initialized())
	{
		zabbix_log(LOG_LEVEL_WARNING, "connectors cannot be used without connector workers:"
				" please check \"StartConnectors\" configuration parameter");
	}
clean:
	zbx_dbsync_clear(&config_sync);
	zbx_dbsync_clear(&autoreg_config_sync);
	zbx_dbsync_clear(&autoreg_host_sync);
	zbx_dbsync_clear(&hosts_sync);
	zbx_dbsync_clear(&hi_sync);
	zbx_dbsync_clear(&htmpl_sync);
	zbx_dbsync_clear(&gmacro_sync);
	zbx_dbsync_clear(&hmacro_sync);
	zbx_dbsync_clear(&host_tag_sync);
	zbx_dbsync_clear(&if_sync);
	zbx_dbsync_clear(&items_sync);
	zbx_dbsync_clear(&item_discovery_sync);
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
	zbx_dbsync_clear(&itemscrp_sync);
	zbx_dbsync_clear(&item_tag_sync);
	zbx_dbsync_clear(&maintenance_sync);
	zbx_dbsync_clear(&maintenance_period_sync);
	zbx_dbsync_clear(&maintenance_tag_sync);
	zbx_dbsync_clear(&maintenance_group_sync);
	zbx_dbsync_clear(&maintenance_host_sync);
	zbx_dbsync_clear(&hgroup_host_sync);
	zbx_dbsync_clear(&drules_sync);
	zbx_dbsync_clear(&dchecks_sync);
	zbx_dbsync_clear(&httptest_sync);
	zbx_dbsync_clear(&httptest_field_sync);
	zbx_dbsync_clear(&httpstep_sync);
	zbx_dbsync_clear(&httpstep_field_sync);
	zbx_dbsync_clear(&connector_sync);
	zbx_dbsync_clear(&connector_tag_sync);
	zbx_dbsync_clear(&proxy_sync);
	zbx_dbsync_clear(&proxy_group_sync);
	zbx_dbsync_clear(&hp_sync);

	if (ZBX_DBSYNC_INIT == mode)
		zbx_hashset_destroy(&trend_queue);

	if (NULL != pnew_items)
		zbx_vector_dc_item_ptr_destroy(pnew_items);

	zbx_dbsync_env_clear();

	if (NULL != pg_host_reloc_ref)
	{
		zbx_pg_update_object_relocations(ZBX_IPC_PGM_HOST_PGROUP_UPDATE, pg_host_reloc_ref);
		zbx_vector_objmove_destroy(pg_host_reloc_ref);
	}

	zbx_hashset_destroy(&activated_hosts);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		DCdump_configuration();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return new_revision;
}

/******************************************************************************
 *                                                                            *
 * Helper functions for configuration cache data structure element comparison *
 * and hash value calculation.                                                *
 *                                                                            *
 * The __config_shmem_XXX_func(), __config_XXX_hash and __config_XXX_compare  *
 * functions are used only inside zbx_init_configuration_cache() function to  *
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

static zbx_hash_t	__config_proxy_h_hash(const void *data)
{
	const zbx_dc_proxy_name_t	*proxy_h = (const zbx_dc_proxy_name_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(proxy_h->name, strlen(proxy_h->name), ZBX_DEFAULT_HASH_SEED);
}

static int	__config_proxy_h_compare(const void *d1, const void *d2)
{
	const zbx_dc_proxy_name_t	*proxy_h_1 = (const zbx_dc_proxy_name_t *)d1;
	const zbx_dc_proxy_name_t	*proxy_h_2 = (const zbx_dc_proxy_name_t *)d2;

	return proxy_h_1->name == proxy_h_2->name ? 0 : strcmp(proxy_h_1->name, proxy_h_2->name);
}

static zbx_hash_t	__config_autoreg_host_h_hash(const void *data)
{
	const ZBX_DC_AUTOREG_HOST	*autoreg_host = (const ZBX_DC_AUTOREG_HOST *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(autoreg_host->host, strlen(autoreg_host->host), ZBX_DEFAULT_HASH_SEED);
}

static int	__config_autoreg_host_h_compare(const void *d1, const void *d2)
{
	const ZBX_DC_AUTOREG_HOST	*autoreg_host_1 = (const ZBX_DC_AUTOREG_HOST *)d1;
	const ZBX_DC_AUTOREG_HOST	*autoreg_host_2 = (const ZBX_DC_AUTOREG_HOST *)d2;

	return strcmp(autoreg_host_1->host, autoreg_host_2->host);
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

	return (interface_addr_1->addr == interface_addr_2->addr ? 0 : strcmp(interface_addr_1->addr,
			interface_addr_2->addr));
}

static int	__config_snmp_item_compare(const ZBX_DC_ITEM *i1, const ZBX_DC_ITEM *i2)
{
	unsigned char		f1;
	unsigned char		f2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->interfaceid, i2->interfaceid);
	ZBX_RETURN_IF_NOT_EQUAL(i1->type, i2->type);

	f1 = ZBX_FLAG_DISCOVERY_RULE & i1->flags;
	f2 = ZBX_FLAG_DISCOVERY_RULE & i2->flags;

	ZBX_RETURN_IF_NOT_EQUAL(f1, f2);

	ZBX_RETURN_IF_NOT_EQUAL(i1->itemtype.snmpitem->snmp_oid_type, i2->itemtype.snmpitem->snmp_oid_type);

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

	if (ITEM_TYPE_SNMP != i1->type)
	{
		if (ITEM_TYPE_SNMP != i2->type)
			return 0;

		return -1;
	}
	else
	{
		if (ITEM_TYPE_SNMP != i2->type)
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

	j1 = i1->itemtype.jmxitem;
	j2 = i2->itemtype.jmxitem;

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

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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

static int	__config_timer_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const zbx_trigger_timer_t	*t1 = (const zbx_trigger_timer_t *)e1->data;
	const zbx_trigger_timer_t	*t2 = (const zbx_trigger_timer_t *)e2->data;

	int	ret;

	if (0 != (ret = zbx_timespec_compare(&t1->check_ts, &t2->check_ts)))
		return ret;

	ZBX_RETURN_IF_NOT_EQUAL(t1->triggerid, t2->triggerid);

	if (0 != (ret = zbx_timespec_compare(&t1->eval_ts, &t2->eval_ts)))
		return ret;

	return 0;
}

static int	__config_drule_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const zbx_dc_drule_t	*r1 = (const zbx_dc_drule_t *)e1->data;
	const zbx_dc_drule_t	*r2 = (const zbx_dc_drule_t *)e2->data;

	return (int)(r1->nextcheck - r2->nextcheck);
}

static int	__config_httptest_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const zbx_dc_httptest_t	*ht1 = (const zbx_dc_httptest_t *)e1->data;
	const zbx_dc_httptest_t	*ht2 = (const zbx_dc_httptest_t *)e2->data;

	return (int)(ht1->nextcheck - ht2->nextcheck);
}

static zbx_hash_t	__config_session_hash(const void *data)
{
	const zbx_session_t	*session = (const zbx_session_t *)data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&session->hostid);
	return ZBX_DEFAULT_STRING_HASH_ALGO(session->token, strlen(session->token), hash);
}

static int	__config_session_compare(const void *d1, const void *d2)
{
	const zbx_session_t	*s1 = (const zbx_session_t *)d1;
	const zbx_session_t	*s2 = (const zbx_session_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(s1->hostid, s2->hostid);
	return strcmp(s1->token, s2->token);
}

static zbx_hash_t	dc_host_proxy_hash(const void *data)
{
	const zbx_dc_host_proxy_index_t	*hp = (const zbx_dc_host_proxy_index_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(hp->host, strlen(hp->host), ZBX_DEFAULT_HASH_SEED);
}

static int	dc_host_proxy_compare(const void *d1, const void *d2)
{
	const zbx_dc_host_proxy_index_t	*hp1 = (const zbx_dc_host_proxy_index_t *)d1;
	const zbx_dc_host_proxy_index_t	*hp2 = (const zbx_dc_host_proxy_index_t *)d2;

	return hp1->host == hp2->host ? 0 : strcmp(hp1->host, hp2->host);
}

int	cacheconfig_get_config_forks(unsigned char proc_type)
{
	return get_config_forks_cb(proc_type);
}

size_t	zbx_maintenance_update_flags_num(void)
{
	return ((((size_t)get_config_forks_cb(ZBX_PROCESS_TYPE_TIMER)) + sizeof(uint64_t) * 8 - 1)
			/ (sizeof(uint64_t) * 8));
}

/******************************************************************************
 *                                                                            *
 * Purpose: Allocate shared memory for configuration cache                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_init_configuration_cache(zbx_get_program_type_f get_program_type, zbx_get_config_forks_f get_config_forks,
		zbx_uint64_t conf_cache_size, const char *hostname, char **error)
{
	int	i, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() size:" ZBX_FS_UI64, __func__, conf_cache_size);

	get_program_type_cb = get_program_type;
	get_config_forks_cb = get_config_forks;

	if (SUCCEED != (ret = zbx_rwlock_create(&config_lock, ZBX_RWLOCK_CONFIG, error)))
		goto out;

	if (SUCCEED != (ret = zbx_rwlock_create(&config_history_lock, ZBX_RWLOCK_CONFIG_HISTORY, error)))
		goto out;

	if (SUCCEED != (ret = zbx_shmem_create(&config_mem, conf_cache_size, "configuration cache",
			"CacheSize", 0, error)))
	{
		goto out;
	}

	config = (zbx_dc_config_t *)__config_shmem_malloc_func(NULL, sizeof(zbx_dc_config_t) +
			(size_t)get_config_forks_cb(ZBX_PROCESS_TYPE_TIMER) * sizeof(zbx_vector_ptr_t));

	if (SUCCEED != vps_monitor_create(&config->vps_monitor, error))
		goto out;

#define CREATE_HASHSET(hashset, hashset_size)									\
														\
	CREATE_HASHSET_EXT(hashset, hashset_size, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC)

#define CREATE_HASHSET_EXT(hashset, hashset_size, hash_func, compare_func)					\
														\
	zbx_hashset_create_ext(&hashset, hashset_size, hash_func, compare_func, NULL,				\
			__config_shmem_malloc_func, __config_shmem_realloc_func, __config_shmem_free_func)

	CREATE_HASHSET(config->items, 0);
	CREATE_HASHSET(config->items_params, 0);
	CREATE_HASHSET(config->template_items, 0);
	CREATE_HASHSET(config->item_discovery, 0);
	CREATE_HASHSET(config->functions, 0);
	CREATE_HASHSET(config->triggers, 0);
	CREATE_HASHSET(config->trigdeps, 0);
	CREATE_HASHSET(config->hosts, 10);
	CREATE_HASHSET(config->proxies, 0);
	CREATE_HASHSET(config->host_inventories, 0);
	CREATE_HASHSET(config->host_inventories_auto, 0);
	CREATE_HASHSET(config->ipmihosts, 0);

	CREATE_HASHSET_EXT(config->gmacros, 0, um_macro_hash, um_macro_compare);
	CREATE_HASHSET_EXT(config->hmacros, 0, um_macro_hash, um_macro_compare);

	CREATE_HASHSET(config->interfaces, 10);
	CREATE_HASHSET(config->interfaces_snmp, 0);
	CREATE_HASHSET(config->interface_snmpitems, 0);
	CREATE_HASHSET(config->expressions, 0);
	CREATE_HASHSET(config->actions, 0);
	CREATE_HASHSET(config->action_conditions, 0);
	CREATE_HASHSET(config->trigger_tags, 0);
	CREATE_HASHSET(config->host_tags, 0);
	CREATE_HASHSET(config->host_tags_index, 0);
	CREATE_HASHSET(config->correlations, 0);
	CREATE_HASHSET(config->corr_conditions, 0);
	CREATE_HASHSET(config->corr_operations, 0);
	CREATE_HASHSET(config->hostgroups, 0);
	zbx_vector_ptr_create_ext(&config->hostgroups_name, __config_shmem_malloc_func, __config_shmem_realloc_func,
			__config_shmem_free_func);

	zbx_vector_ptr_create_ext(&config->kvs_paths, __config_shmem_malloc_func, __config_shmem_realloc_func,
			__config_shmem_free_func);
	CREATE_HASHSET(config->gmacro_kv, 0);
	CREATE_HASHSET(config->hmacro_kv, 0);

	CREATE_HASHSET(config->preprocops, 0);

	CREATE_HASHSET(config->maintenances, 0);
	CREATE_HASHSET(config->maintenance_periods, 0);
	CREATE_HASHSET(config->maintenance_tags, 0);

	CREATE_HASHSET_EXT(config->items_hk, 0, __config_item_hk_hash, __config_item_hk_compare);
	CREATE_HASHSET_EXT(config->hosts_h, 10, __config_host_h_hash, __config_host_h_compare);
	CREATE_HASHSET_EXT(config->proxies_p, 0, __config_proxy_h_hash, __config_proxy_h_compare);
	CREATE_HASHSET_EXT(config->autoreg_hosts, 10, __config_autoreg_host_h_hash, __config_autoreg_host_h_compare);
	CREATE_HASHSET_EXT(config->interfaces_ht, 10, __config_interface_ht_hash, __config_interface_ht_compare);
	CREATE_HASHSET_EXT(config->interface_snmpaddrs, 0, __config_interface_addr_hash,
			__config_interface_addr_compare);
	CREATE_HASHSET_EXT(config->regexps, 0, __config_regexp_hash, __config_regexp_compare);

	CREATE_HASHSET_EXT(config->strpool, 100, __config_strpool_hash, __config_strpool_compare);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	CREATE_HASHSET_EXT(config->psks, 0, __config_psk_hash, __config_psk_compare);
#endif

	CREATE_HASHSET(config->connectors, 0);
	CREATE_HASHSET(config->connector_tags, 0);

	CREATE_HASHSET(config->proxy_groups, 0);
	CREATE_HASHSET(config->host_proxy, 0);
	CREATE_HASHSET_EXT(config->host_proxy_index, 0, dc_host_proxy_hash, dc_host_proxy_compare);

	for (i = 0; i < ZBX_POLLER_TYPE_COUNT; i++)
	{
		switch (i)
		{
			case ZBX_POLLER_TYPE_JAVA:
				zbx_binary_heap_create_ext(&config->queues[i],
						__config_java_elem_compare,
						ZBX_BINARY_HEAP_OPTION_DIRECT,
						__config_shmem_malloc_func,
						__config_shmem_realloc_func,
						__config_shmem_free_func);
				break;
			case ZBX_POLLER_TYPE_PINGER:
				zbx_binary_heap_create_ext(&config->queues[i],
						__config_pinger_elem_compare,
						ZBX_BINARY_HEAP_OPTION_DIRECT,
						__config_shmem_malloc_func,
						__config_shmem_realloc_func,
						__config_shmem_free_func);
				break;
			default:
				zbx_binary_heap_create_ext(&config->queues[i],
						__config_heap_elem_compare,
						ZBX_BINARY_HEAP_OPTION_DIRECT,
						__config_shmem_malloc_func,
						__config_shmem_realloc_func,
						__config_shmem_free_func);
				break;
		}
	}

	zbx_binary_heap_create_ext(&config->pqueue,
					__config_proxy_compare,
					ZBX_BINARY_HEAP_OPTION_DIRECT,
					__config_shmem_malloc_func,
					__config_shmem_realloc_func,
					__config_shmem_free_func);

	zbx_binary_heap_create_ext(&config->trigger_queue,
					__config_timer_compare,
					ZBX_BINARY_HEAP_OPTION_EMPTY,
					__config_shmem_malloc_func,
					__config_shmem_realloc_func,
					__config_shmem_free_func);

	zbx_binary_heap_create_ext(&config->drule_queue,
					__config_drule_compare,
					ZBX_BINARY_HEAP_OPTION_DIRECT,
					__config_shmem_malloc_func,
					__config_shmem_realloc_func,
					__config_shmem_free_func);

	zbx_binary_heap_create_ext(&config->httptest_queue,
					__config_httptest_compare,
					ZBX_BINARY_HEAP_OPTION_DIRECT,
					__config_shmem_malloc_func,
					__config_shmem_realloc_func,
					__config_shmem_free_func);

	CREATE_HASHSET(config->drules, 0);
	CREATE_HASHSET(config->dchecks, 0);

	CREATE_HASHSET(config->httptests, 0);
	CREATE_HASHSET(config->httptest_fields, 0);
	CREATE_HASHSET(config->httpsteps, 0);
	CREATE_HASHSET(config->httpstep_fields, 0);
	for (i = 0; i < ZBX_SESSION_TYPE_COUNT; i++)
		CREATE_HASHSET_EXT(config->sessions[i], 0, __config_session_hash, __config_session_compare);

	config->config = NULL;

	config->status = (ZBX_DC_STATUS *)__config_shmem_malloc_func(NULL, sizeof(ZBX_DC_STATUS));
	config->status->last_update = 0;
	config->status->sync_ts = 0;

	config->availability_diff_ts = 0;
	config->sync_ts = 0;

	config->internal_actions = 0;
	config->auto_registration_actions = 0;

	memset(&config->revision, 0, sizeof(config->revision));

	config->um_cache = um_cache_create();

	/* maintenance data are used only when timers are defined (server) */
	if (0 != get_config_forks_cb(ZBX_PROCESS_TYPE_TIMER))
	{
		config->maintenance_update = ZBX_FLAG_MAINTENANCE_UPDATE_NONE;
		config->maintenance_update_flags = (zbx_uint64_t *)__config_shmem_malloc_func(NULL,
				sizeof(zbx_uint64_t) * zbx_maintenance_update_flags_num());
		memset(config->maintenance_update_flags, 0, sizeof(zbx_uint64_t) * zbx_maintenance_update_flags_num());
	}

	config->proxy_lastaccess_ts = time(NULL);

	/* create data session token for proxies */
	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY))
	{
		char	*token;

		token = zbx_create_token(0);
		config->session_token = dc_strdup(token);
		zbx_free(token);
	}
	else
		config->session_token = NULL;

	config->itservices_num = 0;
	config->proxy_hostname = (NULL != hostname ? dc_strdup(hostname) : NULL);
	config->proxy_failover_delay_raw = NULL;
	config->proxy_failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY;
	config->proxy_lastonline = 0;

	zbx_dbsync_env_init(config);
	zbx_hashset_create(&config_private.item_tag_links, 0, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

#undef CREATE_HASHSET
#undef CREATE_HASHSET_EXT
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Free memory allocated for configuration cache                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_free_configuration_cache(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	WRLOCK_CACHE;

	config = NULL;

	UNLOCK_CACHE;

	vps_monitor_destroy();

	zbx_shmem_destroy(config_mem);
	config_mem = NULL;
	zbx_rwlock_destroy(&config_history_lock);
	zbx_rwlock_destroy(&config_lock);

	zbx_hashset_destroy(&config_private.item_tag_links);
	memset(&config_private, 0, sizeof(config_private));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
int	zbx_in_maintenance_without_data_collection(unsigned char maintenance_status, unsigned char maintenance_type,
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

static void	DCget_host(zbx_dc_host_t *dst_host, const ZBX_DC_HOST *src_host)
{
	const ZBX_DC_IPMIHOST		*ipmihost;

	dst_host->hostid = src_host->hostid;
	dst_host->proxyid = src_host->proxyid;
	dst_host->proxy_groupid = src_host->proxy_groupid;
	dst_host->status = src_host->status;
	dst_host->monitored_by = src_host->monitored_by;

	zbx_strscpy(dst_host->host, src_host->host);

	zbx_strlcpy_utf8(dst_host->name, src_host->name, sizeof(dst_host->name));

	dst_host->maintenance_status = src_host->maintenance_status;
	dst_host->maintenance_type = src_host->maintenance_type;
	dst_host->maintenance_from = src_host->maintenance_from;


	dst_host->tls_connect = src_host->tls_connect;
	dst_host->tls_accept = src_host->tls_accept;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_strscpy(dst_host->tls_issuer, src_host->tls_issuer);
	zbx_strscpy(dst_host->tls_subject, src_host->tls_subject);

	if (NULL == src_host->tls_dc_psk)
	{
		*dst_host->tls_psk_identity = '\0';
		*dst_host->tls_psk = '\0';
	}
	else
	{
		zbx_strscpy(dst_host->tls_psk_identity, src_host->tls_dc_psk->tls_psk_identity);
		zbx_strscpy(dst_host->tls_psk, src_host->tls_dc_psk->tls_psk);
	}
#endif
	if (NULL != (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_search(&config->ipmihosts, &src_host->hostid)))
	{
		dst_host->ipmi_authtype = ipmihost->ipmi_authtype;
		dst_host->ipmi_privilege = ipmihost->ipmi_privilege;
		zbx_strscpy(dst_host->ipmi_username, ipmihost->ipmi_username);
		zbx_strscpy(dst_host->ipmi_password, ipmihost->ipmi_password);
	}
	else
	{
		dst_host->ipmi_authtype = ZBX_IPMI_DEFAULT_AUTHTYPE;
		dst_host->ipmi_privilege = ZBX_IPMI_DEFAULT_PRIVILEGE;
		*dst_host->ipmi_username = '\0';
		*dst_host->ipmi_password = '\0';
	}

}

/******************************************************************************
 *                                                                            *
 * Purpose: Locate host in configuration cache                                *
 *                                                                            *
 * Parameters: host - [OUT] pointer to zbx_dc_host_t structure                *
 *             hostid - [IN] host ID from database                            *
 *                                                                            *
 * Return value: SUCCEED if record located and FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_host_by_hostid(zbx_dc_host_t *host, zbx_uint64_t hostid)
{
	int			ret = FAIL;
	const ZBX_DC_HOST	*dc_host;

	RDLOCK_CACHE;

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
 * Purpose:                                                                   *
 *     Check connection access rights for host and get host data              *
 *                                                                            *
 * Parameters:                                                                *
 *     host         - [IN] host name                                          *
 *     sock         - [IN] connection socket context                          *
 *     hostid       - [OUT] host ID found in configuration cache              *
 *     status       - [OUT] host status                                       *
 *     monitored_by - [OUT]                                                   *
 *     revision     - [OUT] host configuration revision                       *
 *     redirect     - [OUT] host redirection information (optional)           *
 *     error        - [OUT] error message why access was denied               *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - access is allowed or host not found, FAIL - access denied or *
 *               host redirection error if redirection data is requested      *
 *                                                                            *
 * Comments:                                                                  *
 *     Generating of error messages is done outside of configuration cache    *
 *     locking.                                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_check_host_conn_permissions(const char *host, const zbx_socket_t *sock, zbx_uint64_t *hostid,
		unsigned char *status, unsigned char *monitored_by, zbx_uint64_t *revision,
		zbx_comms_redirect_t *redirect, char **error)
{
	const ZBX_DC_HOST	*dc_host = NULL;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_conn_attr_t	attr;

	if (FAIL == zbx_tls_get_attr(sock, &attr, error))
		return FAIL;
#else
	zbx_tls_conn_attr_t	attr = {.connection_type = ZBX_TCP_SEC_UNENCRYPTED};

#endif
	RDLOCK_CACHE;

	if (NULL != redirect && SUCCEED == dc_get_host_redirect(host, &attr, redirect))
	{
		UNLOCK_CACHE;

		if (ZBX_REDIRECT_NONE == redirect->reset)
			*error = zbx_dsprintf(NULL, "host \"%s\" is monitored by another proxy", host);
		else
			*error = zbx_dsprintf(NULL, "host \"%s\" redirected address is being reset", host);

		return FAIL;
	}

	if (NULL == (dc_host = DCfind_host(host)))
	{
		UNLOCK_CACHE;
		*hostid = 0;

		return SUCCEED;
	}

	if (0 == ((unsigned int)dc_host->tls_accept & sock->connection_type))
	{
		UNLOCK_CACHE;
		*error = zbx_dsprintf(NULL, "connection of type \"%s\" is not allowed for host \"%s\"",
				zbx_tcp_connection_type_name(sock->connection_type), host);
		return FAIL;
	}
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	const char	*msg;

	if (FAIL == zbx_tls_validate_attr(&attr, dc_host->tls_issuer, dc_host->tls_subject,
			NULL == dc_host->tls_dc_psk ? NULL : dc_host->tls_dc_psk->tls_psk_identity, &msg))
	{
		UNLOCK_CACHE;
		*error = zbx_dsprintf(NULL, "host \"%s\": %s", host, msg);
		return FAIL;
	}
#endif
	*status = dc_host->status;
	*monitored_by = dc_host->monitored_by;
	*hostid = dc_host->hostid;
	*revision = MAX(dc_host->revision, config->revision.expression);

	um_cache_get_host_revision(config->um_cache, ZBX_UM_CACHE_GLOBAL_MACRO_HOSTID, revision);
	um_cache_get_host_revision(config->um_cache, *hostid, revision);

	/* configuration is not yet fully synced */
	if (*revision > config->revision.config)
		*revision = config->revision.config;

	UNLOCK_CACHE;

	return SUCCEED;
}

int	zbx_dc_is_autoreg_host_changed(const char *host, unsigned short port, const char *host_metadata,
		zbx_conn_flags_t flag, const char *interface, int now)
{
#define AUTO_REGISTRATION_HEARTBEAT	120

	const ZBX_DC_AUTOREG_HOST	*dc_autoreg_host;
	int				ret;

	RDLOCK_CACHE;

	if (NULL == (dc_autoreg_host = DCfind_autoreg_host(host)))
	{
		ret = SUCCEED;
	}
	else if (0 != strcmp(dc_autoreg_host->host_metadata, host_metadata))
	{
		ret = SUCCEED;
	}
	else if (dc_autoreg_host->flags != (int)flag)
	{
		ret = SUCCEED;
	}
	else if (ZBX_CONN_IP == flag && (0 != strcmp(dc_autoreg_host->listen_ip, interface) ||
			dc_autoreg_host->listen_port != port))
	{
		ret = SUCCEED;
	}
	else if (ZBX_CONN_DNS == flag && (0 != strcmp(dc_autoreg_host->listen_dns, interface) ||
			dc_autoreg_host->listen_port != port))
	{
		ret = SUCCEED;
	}
	else if (AUTO_REGISTRATION_HEARTBEAT < now - dc_autoreg_host->timestamp)
	{
		ret = SUCCEED;
	}
	else
		ret = FAIL;

	UNLOCK_CACHE;

	return ret;
}

void	zbx_dc_config_update_autoreg_host(const char *host, const char *listen_ip, const char *listen_dns,
		unsigned short listen_port, const char *host_metadata, zbx_conn_flags_t flags, int now)
{
	ZBX_DC_AUTOREG_HOST	*dc_autoreg_host, dc_autoreg_host_local = {.host = host};
	int			found;

	WRLOCK_CACHE;

	dc_autoreg_host = (ZBX_DC_AUTOREG_HOST *)zbx_hashset_search(&config->autoreg_hosts, &dc_autoreg_host_local);
	if (NULL == dc_autoreg_host)
	{
		found = 0;
		dc_autoreg_host = zbx_hashset_insert(&config->autoreg_hosts, &dc_autoreg_host_local,
				sizeof(ZBX_DC_AUTOREG_HOST));
	}
	else

		found = 1;

	dc_strpool_replace(found, &dc_autoreg_host->host, host);
	dc_strpool_replace(found, &dc_autoreg_host->listen_ip, listen_ip);
	dc_strpool_replace(found, &dc_autoreg_host->listen_dns, listen_dns);
	dc_strpool_replace(found, &dc_autoreg_host->host_metadata, host_metadata);
	dc_autoreg_host->flags = flags;
	dc_autoreg_host->timestamp = now;
	dc_autoreg_host->listen_port = listen_port;

	UNLOCK_CACHE;
}

static void	autoreg_host_free_data(ZBX_DC_AUTOREG_HOST *autoreg_host)
{
	dc_strpool_release(autoreg_host->host);
	dc_strpool_release(autoreg_host->listen_ip);
	dc_strpool_release(autoreg_host->listen_dns);
	dc_strpool_release(autoreg_host->host_metadata);
}

void	zbx_dc_config_delete_autoreg_host(const zbx_vector_str_t *autoreg_hosts)
{
	int	cached = 0, i;

	/* hosts monitored by Zabbix proxy shouldn't be changed too frequently */
	if (0 == autoreg_hosts->values_num)
		return;

	/* hosts monitored by Zabbix proxy shouldn't be in cache */
	RDLOCK_CACHE;
	for (i = 0; i < autoreg_hosts->values_num; i++)
	{
		if (NULL != DCfind_autoreg_host(autoreg_hosts->values[i]))
			cached++;
	}
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "moved:%d hosts from Zabbix server to Zabbix proxy", cached);

	if (0 == cached)
		return;

	WRLOCK_CACHE;

	for (i = 0; i < autoreg_hosts->values_num; i++)
	{
		ZBX_DC_AUTOREG_HOST	*autoreg_host;

		autoreg_host = DCfind_autoreg_host(autoreg_hosts->values[i]);
		if (NULL != autoreg_host)
		{
			autoreg_host_free_data(autoreg_host);
			zbx_hashset_remove_direct(&config->autoreg_hosts, autoreg_host);
		}
	}

	UNLOCK_CACHE;
}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Find PSK with the specified identity in configuration cache            *
 *                                                                            *
 * Parameters:                                                                *
 *     psk_identity - [IN] PSK identity to search for ('\0' terminated)       *
 *     psk_buf      - [OUT] output buffer for PSK value with size             *
 *                    HOST_TLS_PSK_LEN_MAX                                    *
 *     psk_usage    - [OUT] 0 - PSK not found, 1 - found in host PSKs,        *
 *                          2 - found in autoregistration PSK, 3 - found in   *
 *                          both                                              *
 * Return value:                                                              *
 *     PSK length in bytes if PSK found. 0 - if PSK not found.                *
 *                                                                            *
 * Comments:                                                                  *
 *     ATTENTION! This function's address and arguments are described and     *
 *     used in file src/libs/zbxcrypto/tls.c for calling this function by     *
 *     pointer. If you ever change this zbx_dc_get_psk_by_identity() function *
 *     arguments or return value do not forget to synchronize changes with    *
 *     the src/libs/zbxcrypto/tls.c.                                          *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_dc_get_psk_by_identity(const unsigned char *psk_identity, unsigned char *psk_buf, unsigned int *psk_usage)
{
	const ZBX_DC_PSK	*psk_i;
	ZBX_DC_PSK		psk_i_local;
	size_t			psk_len = 0;
	unsigned char		autoreg_psk_tmp[HOST_TLS_PSK_LEN_MAX];

	*psk_usage = 0;

	psk_i_local.tls_psk_identity = (const char *)psk_identity;

	RDLOCK_CACHE;

	/* Is it among host PSKs? */
	if (NULL != (psk_i = (ZBX_DC_PSK *)zbx_hashset_search(&config->psks, &psk_i_local)))
	{
		psk_len = zbx_strlcpy((char *)psk_buf, psk_i->tls_psk, HOST_TLS_PSK_LEN_MAX);
		*psk_usage |= ZBX_PSK_FOR_HOST;
	}

	/* Does it match autoregistration PSK? */
	if (0 != strcmp(config->autoreg_psk_identity, (const char *)psk_identity))
	{
		UNLOCK_CACHE;
		return psk_len;
	}

	if (0 == *psk_usage)	/* only as autoregistration PSK */
	{
		psk_len = zbx_strlcpy((char *)psk_buf, config->autoreg_psk, HOST_TLS_PSK_LEN_MAX);
		UNLOCK_CACHE;
		*psk_usage |= ZBX_PSK_FOR_AUTOREG;

		return psk_len;
	}

	/* the requested PSK is used as host PSK and as autoregistration PSK */
	zbx_strlcpy((char *)autoreg_psk_tmp, config->autoreg_psk, sizeof(autoreg_psk_tmp));

	UNLOCK_CACHE;

	if (0 == strcmp((const char *)psk_buf, (const char *)autoreg_psk_tmp))
	{
		*psk_usage |= ZBX_PSK_FOR_AUTOREG;
		return psk_len;
	}

	zabbix_log(LOG_LEVEL_WARNING, "host PSK and autoregistration PSK have the same identity \"%s\" but"
			" different PSK values, autoregistration will not be allowed", psk_identity);
	return psk_len;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Copy autoregistration PSK identity and value from configuration cache  *
 *     into caller's buffers                                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     psk_identity_buf     - [OUT] buffer for PSK identity                   *
 *     psk_identity_buf_len - [IN] buffer length for PSK identity             *
 *     psk_buf              - [OUT] buffer for PSK value                      *
 *     psk_buf_len          - [IN] buffer length for PSK value                *
 *                                                                            *
 * Comments: if autoregistration PSK is not configured then empty strings     *
 *           will be copied into buffers                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_autoregistration_psk(char *psk_identity_buf, size_t psk_identity_buf_len,
		unsigned char *psk_buf, size_t psk_buf_len)
{
	RDLOCK_CACHE;

	zbx_strlcpy((char *)psk_identity_buf, config->autoreg_psk_identity, psk_identity_buf_len);
	zbx_strlcpy((char *)psk_buf, config->autoreg_psk, psk_buf_len);

	UNLOCK_CACHE;
}

void	DCget_interface(zbx_dc_interface_t *dst_interface, const ZBX_DC_INTERFACE *src_interface)
{
	if (NULL != src_interface)
	{
		dst_interface->interfaceid = src_interface->interfaceid;
		zbx_strscpy(dst_interface->ip_orig, src_interface->ip);
		zbx_strscpy(dst_interface->dns_orig, src_interface->dns);
		zbx_strscpy(dst_interface->port_orig, src_interface->port);
		dst_interface->useip = src_interface->useip;
		dst_interface->type = src_interface->type;
		dst_interface->main = src_interface->main;
		dst_interface->available = src_interface->available;
		dst_interface->disable_until = src_interface->disable_until;
		dst_interface->errors_from = src_interface->errors_from;
		zbx_strscpy(dst_interface->error, src_interface->error);
		dst_interface->version = src_interface->version;
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
		dst_interface->available = ZBX_INTERFACE_AVAILABLE_UNKNOWN;
		dst_interface->disable_until = 0;
		dst_interface->errors_from = 0;
		*dst_interface->error = '\0';
		dst_interface->version = ZBX_COMPONENT_VERSION(7, 0, 0);
	}

	dst_interface->addr = (1 == dst_interface->useip ? dst_interface->ip_orig : dst_interface->dns_orig);
	dst_interface->port = 0;
}

static void	DCget_item(zbx_dc_item_t *dst_item, const ZBX_DC_ITEM *src_item)
{
	const ZBX_DC_LOGITEM		*logitem;
	const ZBX_DC_SNMPINTERFACE	*snmp;
	const ZBX_DC_TRAPITEM		*trapitem;
	const ZBX_DC_INTERFACE		*dc_interface;
	int				i;

	dst_item->type = src_item->type;
	dst_item->value_type = src_item->value_type;

	dst_item->state = src_item->state;
	dst_item->lastlogsize = src_item->lastlogsize;
	dst_item->mtime = src_item->mtime;

	dst_item->status = src_item->status;

	zbx_strscpy(dst_item->key_orig, src_item->key);

	dst_item->itemid = src_item->itemid;
	dst_item->flags = src_item->flags;
	dst_item->key = NULL;
	dst_item->timeout = 0;

	dst_item->delay = zbx_strdup(NULL, src_item->delay);	/* not used, should be initialized */

	if ('\0' != *src_item->error)
		dst_item->error = zbx_strdup(NULL, src_item->error);
	else
		dst_item->error = NULL;

	switch (src_item->value_type)
	{
		case ITEM_VALUE_TYPE_LOG:
			if (NULL != (logitem = src_item->itemvaluetype.logitem))
			{
				zbx_strscpy(dst_item->logtimefmt, logitem->logtimefmt);
			}
			else
				*dst_item->logtimefmt = '\0';
			break;
	}

	dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &src_item->interfaceid);

	DCget_interface(&dst_item->interface, dc_interface);

	if ('\0' == *src_item->timeout)
		zbx_strscpy(dst_item->timeout_orig, dc_get_global_item_type_timeout(src_item->type));
	else
		zbx_strscpy(dst_item->timeout_orig, src_item->timeout);

	switch (src_item->type)
	{
		case ITEM_TYPE_SNMP:
			snmp = (ZBX_DC_SNMPINTERFACE *)zbx_hashset_search(&config->interfaces_snmp,
					&src_item->interfaceid);

			if (NULL != snmp)
			{
				zbx_strscpy(dst_item->snmp_community_orig, snmp->community);
				zbx_strscpy(dst_item->snmp_oid_orig, src_item->itemtype.snmpitem->snmp_oid);
				zbx_strscpy(dst_item->snmpv3_securityname_orig, snmp->securityname);
				dst_item->snmpv3_securitylevel = snmp->securitylevel;
				zbx_strscpy(dst_item->snmpv3_authpassphrase_orig, snmp->authpassphrase);
				zbx_strscpy(dst_item->snmpv3_privpassphrase_orig, snmp->privpassphrase);
				dst_item->snmpv3_authprotocol = snmp->authprotocol;
				dst_item->snmpv3_privprotocol = snmp->privprotocol;
				zbx_strscpy(dst_item->snmpv3_contextname_orig, snmp->contextname);
				dst_item->snmp_version = snmp->version;
				dst_item->snmp_max_repetitions = snmp->max_repetitions;
			}
			else
			{
				*dst_item->snmp_community_orig = '\0';
				*dst_item->snmp_oid_orig = '\0';
				*dst_item->snmpv3_securityname_orig = '\0';
				dst_item->snmpv3_securitylevel = ZBX_ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV;
				*dst_item->snmpv3_authpassphrase_orig = '\0';
				*dst_item->snmpv3_privpassphrase_orig = '\0';
				dst_item->snmpv3_authprotocol = 0;
				dst_item->snmpv3_privprotocol = 0;
				*dst_item->snmpv3_contextname_orig = '\0';
				dst_item->snmp_version = ZBX_IF_SNMP_VERSION_2;
				dst_item->snmp_max_repetitions = 0;
				dst_item->timeout = 0;
			}

			dst_item->snmp_community = NULL;
			dst_item->snmp_oid = NULL;
			dst_item->snmpv3_securityname = NULL;
			dst_item->snmpv3_authpassphrase = NULL;
			dst_item->snmpv3_privpassphrase = NULL;
			dst_item->snmpv3_contextname = NULL;
			break;
		case ITEM_TYPE_TRAPPER:
			if (NULL != (trapitem = src_item->itemtype.trapitem))
			{
				zbx_strscpy(dst_item->trapper_hosts, trapitem->trapper_hosts);
			}
			else
			{
				*dst_item->trapper_hosts = '\0';
			}
			break;
		case ITEM_TYPE_IPMI:
			zbx_strscpy(dst_item->ipmi_sensor, src_item->itemtype.ipmiitem->ipmi_sensor);
			break;
		case ITEM_TYPE_DB_MONITOR:
			dst_item->params = zbx_strdup(NULL, src_item->itemtype.dbitem->params);
			zbx_strscpy(dst_item->username_orig, src_item->itemtype.dbitem->username);
			zbx_strscpy(dst_item->password_orig, src_item->itemtype.dbitem->password);

			dst_item->username = NULL;
			dst_item->password = NULL;

			break;
		case ITEM_TYPE_SSH:
			dst_item->authtype = src_item->itemtype.sshitem->authtype;
			zbx_strscpy(dst_item->username_orig, src_item->itemtype.sshitem->username);
			zbx_strscpy(dst_item->publickey_orig, src_item->itemtype.sshitem->publickey);
			zbx_strscpy(dst_item->privatekey_orig, src_item->itemtype.sshitem->privatekey);
			zbx_strscpy(dst_item->password_orig, src_item->itemtype.sshitem->password);
			dst_item->params = zbx_strdup(NULL, src_item->itemtype.sshitem->params);

			dst_item->username = NULL;
			dst_item->publickey = NULL;
			dst_item->privatekey = NULL;
			dst_item->password = NULL;
			break;
		case ITEM_TYPE_HTTPAGENT:
			zbx_strscpy(dst_item->url_orig, src_item->itemtype.httpitem->url);
			zbx_strscpy(dst_item->query_fields_orig, src_item->itemtype.httpitem->query_fields);
			zbx_strscpy(dst_item->status_codes_orig, src_item->itemtype.httpitem->status_codes);
			dst_item->follow_redirects = src_item->itemtype.httpitem->follow_redirects;
			dst_item->post_type = src_item->itemtype.httpitem->post_type;
			zbx_strscpy(dst_item->http_proxy_orig, src_item->itemtype.httpitem->http_proxy);
			dst_item->headers = zbx_strdup(NULL, src_item->itemtype.httpitem->headers);
			dst_item->retrieve_mode = src_item->itemtype.httpitem->retrieve_mode;
			dst_item->request_method = src_item->itemtype.httpitem->request_method;
			dst_item->output_format = src_item->itemtype.httpitem->output_format;
			zbx_strscpy(dst_item->ssl_cert_file_orig, src_item->itemtype.httpitem->ssl_cert_file);
			zbx_strscpy(dst_item->ssl_key_file_orig, src_item->itemtype.httpitem->ssl_key_file);
			zbx_strscpy(dst_item->ssl_key_password_orig, src_item->itemtype.httpitem->ssl_key_password);
			dst_item->verify_peer = src_item->itemtype.httpitem->verify_peer;
			dst_item->verify_host = src_item->itemtype.httpitem->verify_host;
			dst_item->authtype = src_item->itemtype.httpitem->authtype;
			zbx_strscpy(dst_item->username_orig, src_item->itemtype.httpitem->username);
			zbx_strscpy(dst_item->password_orig, src_item->itemtype.httpitem->password);
			dst_item->posts = zbx_strdup(NULL, src_item->itemtype.httpitem->posts);
			dst_item->allow_traps = src_item->itemtype.httpitem->allow_traps;
			zbx_strscpy(dst_item->trapper_hosts, src_item->itemtype.httpitem->trapper_hosts);

			dst_item->timeout = 0;
			dst_item->url = NULL;
			dst_item->query_fields = NULL;
			dst_item->status_codes = NULL;
			dst_item->http_proxy = NULL;
			dst_item->ssl_cert_file = NULL;
			dst_item->ssl_key_file = NULL;
			dst_item->ssl_key_password = NULL;
			dst_item->username = NULL;
			dst_item->password = NULL;
			break;
		case ITEM_TYPE_SCRIPT:
			dst_item->params = zbx_strdup(NULL, src_item->itemtype.scriptitem->script);

			zbx_vector_ptr_pair_create(&dst_item->script_params);
			for (i = 0; i < src_item->itemtype.scriptitem->params.values_num; i++)
			{
				zbx_dc_item_param_t	*params =
						(zbx_dc_item_param_t*)(src_item->itemtype.scriptitem->params.values[i]);
				zbx_ptr_pair_t	pair;

				pair.first = zbx_strdup(NULL, params->name);
				pair.second = zbx_strdup(NULL, params->value);
				zbx_vector_ptr_pair_append(&dst_item->script_params, pair);
			}

			dst_item->timeout = 0;

			break;
		case ITEM_TYPE_BROWSER:
			dst_item->params = zbx_strdup(NULL, src_item->itemtype.browseritem->script);

			zbx_vector_ptr_pair_create(&dst_item->script_params);
			for (i = 0; i < src_item->itemtype.browseritem->params.values_num; i++)
			{
				zbx_dc_item_param_t	*params =
						(zbx_dc_item_param_t*)(src_item->itemtype.browseritem->params.values[i]);
				zbx_ptr_pair_t	pair;

				pair.first = zbx_strdup(NULL, params->name);
				pair.second = zbx_strdup(NULL, params->value);
				zbx_vector_ptr_pair_append(&dst_item->script_params, pair);
			}

			dst_item->timeout = 0;

			break;
		case ITEM_TYPE_TELNET:
			zbx_strscpy(dst_item->username_orig, src_item->itemtype.telnetitem->username);
			zbx_strscpy(dst_item->password_orig, src_item->itemtype.telnetitem->password);
			dst_item->params = zbx_strdup(NULL, src_item->itemtype.telnetitem->params);

			dst_item->username = NULL;
			dst_item->password = NULL;
			break;
		case ITEM_TYPE_SIMPLE:
			zbx_strscpy(dst_item->username_orig, src_item->itemtype.simpleitem->username);
			zbx_strscpy(dst_item->password_orig, src_item->itemtype.simpleitem->password);

			dst_item->username = NULL;
			dst_item->password = NULL;
			break;
		case ITEM_TYPE_JMX:
			zbx_strscpy(dst_item->username_orig, src_item->itemtype.jmxitem->username);
			zbx_strscpy(dst_item->password_orig, src_item->itemtype.jmxitem->password);
			zbx_strscpy(dst_item->jmx_endpoint_orig, src_item->itemtype.jmxitem->jmx_endpoint);

			dst_item->username = NULL;
			dst_item->password = NULL;
			dst_item->jmx_endpoint = NULL;
			break;
		case ITEM_TYPE_CALCULATED:
			dst_item->params = zbx_strdup(NULL, src_item->itemtype.calcitem->params);
			dst_item->formula_bin = dup_serialized_expression(src_item->itemtype.calcitem->formula_bin);
			break;
		case ITEM_TYPE_ZABBIX:
		case ITEM_TYPE_ZABBIX_ACTIVE:
			dst_item->timeout = 0;

			break;
		default:
			/* nothing to do */;
	}
}

void	zbx_dc_config_clean_items(zbx_dc_item_t *items, int *errcodes, size_t num)
{
	size_t	i;

	for (i = 0; i < num; i++)
	{
		if (NULL != errcodes && SUCCEED != errcodes[i])
			continue;

		switch (items[i].type)
		{
			case ITEM_TYPE_HTTPAGENT:
				zbx_free(items[i].headers);
				zbx_free(items[i].posts);
				break;
			case ITEM_TYPE_SCRIPT:
			case ITEM_TYPE_BROWSER:
				for (int j = 0; j < items[i].script_params.values_num; j++)
				{
					zbx_free(items[i].script_params.values[j].first);
					zbx_free(items[i].script_params.values[j].second);
				}
				zbx_vector_ptr_pair_destroy(&items[i].script_params);
				ZBX_FALLTHROUGH;
			case ITEM_TYPE_DB_MONITOR:
			case ITEM_TYPE_SSH:
			case ITEM_TYPE_TELNET:
				zbx_free(items[i].params);
				break;
			case ITEM_TYPE_CALCULATED:
				zbx_free(items[i].params);
				zbx_free(items[i].formula_bin);
				break;
		}

		zbx_free(items[i].delay);
		zbx_free(items[i].error);
	}
}

void	DCget_function(zbx_dc_function_t *dst_function, const ZBX_DC_FUNCTION *src_function)
{
	size_t	sz_function, sz_parameter;

	dst_function->functionid = src_function->functionid;
	dst_function->triggerid = src_function->triggerid;
	dst_function->itemid = src_function->itemid;
	dst_function->type = src_function->type;

	sz_function = strlen(src_function->function) + 1;
	sz_parameter = strlen(src_function->parameter) + 1;
	dst_function->function = (char *)zbx_malloc(NULL, sz_function + sz_parameter);
	dst_function->parameter = dst_function->function + sz_function;
	memcpy(dst_function->function, src_function->function, sz_function);
	memcpy(dst_function->parameter, src_function->parameter, sz_parameter);
}

void	DCget_trigger(zbx_dc_trigger_t *dst_trigger, const ZBX_DC_TRIGGER *src_trigger, unsigned int flags)
{
	int	i;

	dst_trigger->triggerid = src_trigger->triggerid;
	dst_trigger->description = zbx_strdup(NULL, src_trigger->description);
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
	dst_trigger->opdata = zbx_strdup(NULL, src_trigger->opdata);
	dst_trigger->event_name = ('\0' != *src_trigger->event_name ? zbx_strdup(NULL, src_trigger->event_name) : NULL);
	dst_trigger->flags = 0;
	dst_trigger->new_error = NULL;

	dst_trigger->expression = zbx_strdup(NULL, src_trigger->expression);
	dst_trigger->recovery_expression = zbx_strdup(NULL, src_trigger->recovery_expression);

	dst_trigger->expression_bin = dup_serialized_expression(src_trigger->expression_bin);
	dst_trigger->recovery_expression_bin = dup_serialized_expression(src_trigger->recovery_expression_bin);

	dst_trigger->eval_ctx = NULL;
	dst_trigger->eval_ctx_r = NULL;

	zbx_vector_tags_ptr_create(&dst_trigger->tags);

	if (0 != src_trigger->tags.values_num)
	{
		zbx_vector_tags_ptr_reserve(&dst_trigger->tags, src_trigger->tags.values_num);

		for (i = 0; i < src_trigger->tags.values_num; i++)
		{
			const zbx_dc_trigger_tag_t	*dc_trigger_tag = (const zbx_dc_trigger_tag_t *)
										src_trigger->tags.values[i];
			zbx_tag_t			*tag;

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, dc_trigger_tag->tag);
			tag->value = zbx_strdup(NULL, dc_trigger_tag->value);

			zbx_vector_tags_ptr_append(&dst_trigger->tags, tag);
		}
	}

	zbx_vector_uint64_create(&dst_trigger->itemids);

	if (0 != (flags & ZBX_TRIGGER_GET_ITEMIDS) && NULL != src_trigger->itemids)
	{
		zbx_uint64_t	*itemid;

		for (itemid = src_trigger->itemids; 0 != *itemid; itemid++)
			;

		zbx_vector_uint64_append_array(&dst_trigger->itemids, src_trigger->itemids,
				(int)(itemid - src_trigger->itemids));
	}
}

void	zbx_free_item_tag(zbx_item_tag_t *item_tag)
{
	zbx_free(item_tag->tag.tag);
	zbx_free(item_tag->tag.value);
	zbx_free(item_tag);
}

static void	DCclean_trigger(zbx_dc_trigger_t *trigger)
{
	zbx_free(trigger->new_error);
	zbx_free(trigger->error);
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
	zbx_free(trigger->description);
	zbx_free(trigger->correlation_tag);
	zbx_free(trigger->opdata);
	zbx_free(trigger->event_name);
	zbx_free(trigger->expression_bin);
	zbx_free(trigger->recovery_expression_bin);

	zbx_vector_tags_ptr_clear_ext(&trigger->tags, zbx_free_tag);
	zbx_vector_tags_ptr_destroy(&trigger->tags);

	if (NULL != trigger->eval_ctx)
	{
		zbx_eval_clear(trigger->eval_ctx);
		zbx_free(trigger->eval_ctx);
	}

	if (NULL != trigger->eval_ctx_r)
	{
		zbx_eval_clear(trigger->eval_ctx_r);
		zbx_free(trigger->eval_ctx_r);
	}

	zbx_vector_uint64_destroy(&trigger->itemids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: locate item in configuration cache by host and key                *
 *                                                                            *
 * Parameters: items    - [OUT] pointer to array of zbx_dc_item_t structures  *
 *             keys     - [IN] list of item keys with host names              *
 *             errcodes - [OUT] SUCCEED if record located and FAIL otherwise  *
 *             num      - [IN] number of elements in items, keys, errcodes    *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_get_items_by_keys(zbx_dc_item_t *items, zbx_host_key_t *keys, int *errcodes, size_t num)
{
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	RDLOCK_CACHE;

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
 * Purpose: Get item with specified ID                                        *
 *                                                                            *
 * Parameters: items    - [OUT] pointer to zbx_dc_item_t structures           *
 *             itemids  - [IN] array of item IDs                              *
 *             errcodes - [OUT] SUCCEED if item found, otherwise FAIL         *
 *             num      - [IN] number of elements                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_get_items_by_itemids(zbx_dc_item_t *items, const zbx_uint64_t *itemids, int *errcodes, size_t num)
{
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	RDLOCK_CACHE;

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

int	zbx_dc_config_get_active_items_count_by_hostid(zbx_uint64_t hostid)
{
	ZBX_DC_HOST		*dc_host;
	int			num = 0;
	zbx_hashset_iter_t	iter;
	ZBX_DC_ITEM_REF		*ref;

	RDLOCK_CACHE;

	if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
	{
		zbx_hashset_iter_reset(&dc_host->items, &iter);
		while (NULL != (ref = (ZBX_DC_ITEM_REF *)zbx_hashset_iter_next(&iter)))
		{
			if (ITEM_TYPE_ZABBIX_ACTIVE == ref->item->type)
				num++;
		}
	}

	UNLOCK_CACHE;

	return num;
}

void	zbx_dc_config_get_active_items_by_hostid(zbx_dc_item_t *items, zbx_uint64_t hostid, int *errcodes, size_t num)
{
	ZBX_DC_HOST		*dc_host;
	size_t			j = 0;
	zbx_hashset_iter_t	iter;
	ZBX_DC_ITEM_REF		*ref;

	RDLOCK_CACHE;

	if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)) &&
			0 != dc_host->items.num_data)
	{
		zbx_hashset_iter_reset(&dc_host->items, &iter);
		while (NULL != (ref = (ZBX_DC_ITEM_REF *)zbx_hashset_iter_next(&iter)))
		{
			if (ITEM_TYPE_ZABBIX_ACTIVE != ref->item->type)
				continue;

			DCget_item(&items[j], ref->item);
			errcodes[j++] = SUCCEED;
		}

		if (0 != j)
		{
			DCget_host(&items[0].host, dc_host);

			for (size_t i = 1; i < j; i++)
				items[i].host = items[0].host;
		}
	}

	UNLOCK_CACHE;

	for (; j < num; j++)
		errcodes[j] = FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync item preprocessing steps with preprocessing manager cache,   *
 *          updating preprocessing revision if any changes were detected      *
 *                                                                            *
 ******************************************************************************/
static void	dc_preproc_sync_preprocitem(zbx_pp_item_preproc_t *preproc, const ZBX_DC_PREPROCITEM *preprocitem)
{
	preproc->steps = (zbx_pp_step_t *)zbx_malloc(NULL, sizeof(zbx_pp_step_t) *
			(size_t)preprocitem->preproc_ops.values_num);

	for (int i = 0; i < preprocitem->preproc_ops.values_num; i++)
	{
		zbx_dc_preproc_op_t	*op = (zbx_dc_preproc_op_t *)preprocitem->preproc_ops.values[i];

		preproc->steps[i].type = op->type;
		preproc->steps[i].error_handler = op->error_handler;

		preproc->steps[i].params =  zbx_strdup(NULL, op->params);
		preproc->steps[i].error_handler_params = zbx_strdup(NULL, op->error_handler_params);
	}

	preproc->steps_num = preprocitem->preproc_ops.values_num;

	preproc->pp_revision = preprocitem->revision;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync mater-dependent item links                                   *
 *                                                                            *
 ******************************************************************************/
static void	dc_preproc_sync_masteritem(zbx_pp_item_preproc_t *preproc, ZBX_DC_MASTERITEM *masteritem)
{
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		*pitemid;
	int			i = 0;

	preproc->dep_itemids = (zbx_uint64_t *)zbx_malloc(NULL,
			sizeof(zbx_uint64_t) * (size_t)masteritem->dep_itemids.num_data);

	zbx_hashset_iter_reset(&masteritem->dep_itemids, &iter);
	while (NULL != (pitemid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
		preproc->dep_itemids[i++] = *pitemid;

	qsort(preproc->dep_itemids, (size_t)preproc->dep_itemids_num, sizeof(zbx_uint64_t),
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	preproc->dep_itemids_num = masteritem->dep_itemids.num_data;
}

static void	dc_preproc_sync_item(zbx_hashset_t *items, ZBX_DC_ITEM *dc_item, zbx_uint64_t revision)
{
	zbx_pp_item_t		*pp_item;
	zbx_pp_history_t	*history = NULL;

	if (NULL == (pp_item = (zbx_pp_item_t *)zbx_hashset_search(items, &dc_item->itemid)))
	{
		zbx_pp_item_t	pp_item_local = {.itemid = dc_item->itemid};

		pp_item = (zbx_pp_item_t *)zbx_hashset_insert(items, &pp_item_local, sizeof(pp_item_local));
	}
	else
	{
		if (NULL != dc_item->preproc_item && pp_item->preproc->pp_revision == dc_item->preproc_item->revision)
			history = zbx_pp_history_clone(pp_item->preproc->history);

		zbx_pp_item_preproc_release(pp_item->preproc);
	}

	pp_item->preproc = zbx_pp_item_preproc_create(dc_item->hostid, dc_item->type, dc_item->value_type, dc_item->flags);
	pp_item->revision = revision;

	if (NULL != dc_item->master_item)
		dc_preproc_sync_masteritem(pp_item->preproc, dc_item->master_item);

	if (NULL != dc_item->preproc_item)
		dc_preproc_sync_preprocitem(pp_item->preproc, dc_item->preproc_item);

	for (int i = 0; i < pp_item->preproc->steps_num; i++)
	{
		if (SUCCEED == zbx_pp_preproc_has_history(pp_item->preproc->steps[i].type))
		{
			pp_item->preproc->history_num++;
			pp_item->preproc->mode = ZBX_PP_PROCESS_SERIAL;
		}
	}

	pp_item->preproc->history = history;
}

static void	dc_preproc_add_item_rec(ZBX_DC_ITEM *dc_item, zbx_vector_dc_item_ptr_t *items_sync)
{
	zbx_vector_dc_item_ptr_append(items_sync, dc_item);

	if (NULL != dc_item->master_item)
	{
		zbx_hashset_iter_t	iter;
		zbx_uint64_t		*pitemid;

		zbx_hashset_iter_reset(&dc_item->master_item->dep_itemids, &iter);
		while (NULL != (pitemid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
		{
			ZBX_DC_ITEM	*dep_item;

			if (NULL == (dep_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, pitemid)) ||
					ITEM_STATUS_ACTIVE != dep_item->status)
			{
				continue;
			}

			dc_preproc_add_item_rec(dep_item, items_sync);
		}
	}
}

static int	dc_preproc_item_changed(ZBX_DC_ITEM *dc_item, zbx_pp_item_t *pp_item)
{
	if (dc_item->value_type != pp_item->preproc->value_type)
		return SUCCEED;

	if (dc_item->type != pp_item->preproc->type)
		return SUCCEED;

	if (NULL != dc_item->master_item)
	{
		if (dc_item->master_item->revision > pp_item->revision)
			return SUCCEED;
	}
	else if (0 < pp_item->preproc->dep_itemids_num)
			return SUCCEED;

	if (NULL != dc_item->preproc_item)
	{
		if (dc_item->preproc_item->revision > pp_item->revision)
			return SUCCEED;
	}
	else if (0 < pp_item->preproc->steps_num)
			return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get preprocessable items:                                         *
 *              * items with preprocessing steps                              *
 *              * items with dependent items                                  *
 *              * internal items                                              *
 *                                                                            *
 * Parameters: items       - [IN/OUT] hashset with DC_ITEMs                   *
 *             um_handle   - [IN/OUT] shared user macro cache handle          *
 *             timestamp   - [IN/OUT] timestamp of a last update              *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_get_preprocessable_items(zbx_hashset_t *items, zbx_dc_um_shared_handle_t **um_handle,
		zbx_uint64_t *revision)
{
	ZBX_DC_HOST			*dc_host;
	zbx_pp_item_t			*pp_item;
	zbx_hashset_iter_t		iter;
	int				i;
	zbx_vector_dc_item_ptr_t	items_sync;
	zbx_dc_um_shared_handle_t	*um_handle_new = NULL;

	if (config->revision.config == *revision)
		return;

	zbx_vector_dc_item_ptr_create(&items_sync);
	zbx_vector_dc_item_ptr_reserve(&items_sync, 100);

	RDLOCK_CACHE;

	um_handle_new = zbx_dc_um_shared_handle_update(*um_handle);

	zbx_hashset_iter_reset(&config->hosts, &iter);
	while (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		zbx_hashset_iter_t	item_iter;
		ZBX_DC_ITEM_REF		*ref;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		zbx_hashset_iter_reset(&dc_host->items, &item_iter);
		while (NULL != (ref = (ZBX_DC_ITEM_REF *)zbx_hashset_iter_next(&item_iter)))
		{
			ZBX_DC_ITEM	*dc_item = ref->item;

			if (ITEM_STATUS_ACTIVE != dc_item->status || ITEM_TYPE_DEPENDENT == dc_item->type)
				continue;

			if (NULL == dc_item->preproc_item && NULL == dc_item->master_item &&
					ITEM_TYPE_INTERNAL != dc_item->type &&
					ZBX_FLAG_DISCOVERY_RULE != dc_item->flags)
			{
				continue;
			}

			if (HOST_MONITORED_BY_SERVER == dc_host->monitored_by ||
					SUCCEED == zbx_is_item_processed_by_server(dc_item->type, dc_item->key) ||
					ITEM_TYPE_TRAPPER == dc_item->type || (ITEM_TYPE_HTTPAGENT == dc_item->type &&
					1 == dc_item->itemtype.httpitem->allow_traps))
			{
				dc_preproc_add_item_rec(dc_item, &items_sync);
			}
		}

		if (0 == items_sync.values_num)
			continue;

		for (i = 0; i < items_sync.values_num; )
		{
			/* Update unchanged item preprocessing revision and remove from sync list if already synced,  */
			/* dependent items might have been unchanged but need to be added if their master is enabled. */
			ZBX_DC_ITEM	*dci = items_sync.values[i];

			if (NULL != (pp_item = (zbx_pp_item_t *)zbx_hashset_search(items,
						&dci->itemid)))
			{
				if (FAIL == dc_preproc_item_changed(dci, pp_item))
				{
					pp_item->revision = config->revision.config;
					zbx_vector_dc_item_ptr_remove_noorder(&items_sync, i);
					continue;
				}
			}

			i++;
		}

		for (i = 0; i < items_sync.values_num; i++)
			dc_preproc_sync_item(items, items_sync.values[i], config->revision.config);

		zbx_vector_dc_item_ptr_clear(&items_sync);
	}

	*revision = config->revision.config;

	UNLOCK_CACHE;

	/* remove items without preprocessing */

	zbx_hashset_iter_reset(items, &iter);
	while (NULL != (pp_item = (zbx_pp_item_t *)zbx_hashset_iter_next(&iter)))
	{
		if (pp_item->revision == *revision)
			continue;

		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_dc_item_ptr_destroy(&items_sync);

	if (SUCCEED == zbx_dc_um_shared_handle_reacquire(*um_handle, um_handle_new))
		*um_handle = um_handle_new;
}

int	zbx_dc_get_host_value(zbx_uint64_t itemid, char **replace_to, int request)
{
	int		ret;
	zbx_dc_host_t	host;

	zbx_dc_config_get_hosts_by_itemids(&host, &itemid, &ret, 1);

	if (FAIL == ret)
		return FAIL;

	switch (request)
	{
		case ZBX_REQUEST_HOST_ID:
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, host.hostid);
			break;
		case ZBX_REQUEST_HOST_HOST:
			*replace_to = zbx_strdup(*replace_to, host.host);
			break;
		case ZBX_REQUEST_HOST_NAME:
			*replace_to = zbx_strdup(*replace_to, host.name);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
	}

	return ret;
}

void	zbx_dc_config_get_hosts_by_itemids(zbx_dc_host_t *hosts, const zbx_uint64_t *itemids, int *errcodes, size_t num)
{
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	RDLOCK_CACHE;

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

void	zbx_dc_config_get_hosts_by_hostids(zbx_dc_host_t *hosts, const zbx_uint64_t *hostids, int *errcodes, int num)
{
	int			i;
	const ZBX_DC_HOST	*dc_host;

	RDLOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostids[i])))
		{
			errcodes[i] = FAIL;
			continue;
		}

		DCget_host(&hosts[i], dc_host);
		errcodes[i] = SUCCEED;
	}

	UNLOCK_CACHE;
}

int	zbx_dc_config_trigger_exists(zbx_uint64_t triggerid)
{
	int	ret = SUCCEED;

	RDLOCK_CACHE;

	if (NULL == zbx_hashset_search(&config->triggers, &triggerid))
		ret = FAIL;

	UNLOCK_CACHE;

	return ret;
}

void	zbx_dc_config_get_triggers_by_triggerids(zbx_dc_trigger_t *triggers, const zbx_uint64_t *triggerids,
		int *errcode, size_t num)
{
	size_t			i;
	const ZBX_DC_TRIGGER	*dc_trigger;

	RDLOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_trigger = (const ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &triggerids[i])))
		{
			errcode[i] = FAIL;
			continue;
		}

		DCget_trigger(&triggers[i], dc_trigger, ZBX_TRIGGER_GET_DEFAULT);
		errcode[i] = SUCCEED;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get functions by IDs                                              *
 *                                                                            *
 * Parameters: functions   - [OUT] pointer to zbx_dc_function_t structures    *
 *             functionids - [IN] array of function IDs                       *
 *             errcodes    - [OUT] SUCCEED if item found, otherwise FAIL      *
 *             num         - [IN] number of elements                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_get_functions_by_functionids(zbx_dc_function_t *functions, zbx_uint64_t *functionids, int *errcodes,
		size_t num)
{
	size_t			i;
	const ZBX_DC_FUNCTION	*dc_function;

	RDLOCK_CACHE;

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

void	zbx_dc_config_clean_functions(zbx_dc_function_t *functions, int *errcodes, size_t num)
{
	size_t	i;

	for (i = 0; i < num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		zbx_free(functions[i].function);
	}
}

void	zbx_dc_config_clean_triggers(zbx_dc_trigger_t *triggers, int *errcodes, size_t num)
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
 *                                 zbx_dc_config_unlock_triggers() function   *
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
 * Return value: the number of items available for processing (unlocked).     *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_lock_triggers_by_history_items(zbx_vector_hc_item_ptr_t *history_items,
		zbx_vector_uint64_t *triggerids)
{
	int			i, j, locked_num = 0;
	const ZBX_DC_ITEM	*dc_item;
	ZBX_DC_TRIGGER		*dc_trigger;
	zbx_hc_item_t		*history_item;

	WRLOCK_CACHE;

	for (i = 0; i < history_items->values_num; i++)
	{
		history_item = history_items->values[i];

		if (0 != (ZBX_DC_FLAG_NOVALUE & history_item->tail->flags))
			continue;

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
 * Purpose: Lock triggers so that multiple processes do not process one       *
 *          trigger simultaneously.                                           *
 *                                                                            *
 * Parameters: triggerids_in  - [IN] ids of triggers to lock                  *
 *             triggerids_out - [OUT] ids of locked triggers                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_lock_triggers_by_triggerids(zbx_vector_uint64_t *triggerids_in,
		zbx_vector_uint64_t *triggerids_out)
{
	int		i;
	ZBX_DC_TRIGGER	*dc_trigger;

	if (0 == triggerids_in->values_num)
		return;

	WRLOCK_CACHE;

	for (i = 0; i < triggerids_in->values_num; i++)
	{
		if (NULL == (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers,
				&triggerids_in->values[i])))
		{
			continue;
		}

		if (1 == dc_trigger->locked)
			continue;

		dc_trigger->locked = 1;
		zbx_vector_uint64_append(triggerids_out, dc_trigger->triggerid);
	}

	UNLOCK_CACHE;
}

void	zbx_dc_config_unlock_triggers(const zbx_vector_uint64_t *triggerids)
{
	int		i;
	ZBX_DC_TRIGGER	*dc_trigger;

	/* no other process can modify already locked triggers without write lock */
	RDLOCK_CACHE;

	for (i = 0; i < triggerids->values_num; i++)
	{
		if (NULL == (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers,
				&triggerids->values[i])))
		{
			continue;
		}

		dc_trigger->locked = 0;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Unlocks all locked triggers before doing full history sync at     *
 *          program exit                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_unlock_all_triggers(void)
{
	ZBX_DC_TRIGGER		*dc_trigger;
	zbx_hashset_iter_t	iter;

	WRLOCK_CACHE;

	zbx_hashset_iter_reset(&config->triggers, &iter);

	while (NULL != (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
		dc_trigger->locked = 0;

	UNLOCK_CACHE;
}


/******************************************************************************
 *                                                                            *
 * Purpose: check if the expression contains time based functions             *
 *                                                                            *
 * Parameters: expression    - [IN] the original expression                   *
 *             data          - [IN] the parsed and serialized expression      *
 *             trigger_timer - [IN] the trigger time function flags           *
 *                                                                            *
 ******************************************************************************/
static int	DCconfig_find_active_time_function(const char *expression, const unsigned char *data,
		unsigned char trigger_timer)
{
	int			i, ret = SUCCEED;
	const ZBX_DC_FUNCTION	*dc_function;
	const ZBX_DC_HOST	*dc_host;
	const ZBX_DC_ITEM	*dc_item;
	zbx_vector_uint64_t	functionids;

	zbx_vector_uint64_create(&functionids);
	zbx_get_serialized_expression_functionids(expression, data, &functionids);

	for (i = 0; i < functionids.values_num; i++)
	{
		if (NULL == (dc_function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions,
				&functionids.values[i])))
		{
			continue;
		}

		if (ZBX_TRIGGER_TIMER_DEFAULT != trigger_timer || ZBX_FUNCTION_TYPE_TRENDS == dc_function->type ||
				ZBX_FUNCTION_TYPE_TIMER == dc_function->type)
		{
			if (NULL == (dc_item = zbx_hashset_search(&config->items, &dc_function->itemid)))
				continue;

			if (NULL == (dc_host = zbx_hashset_search(&config->hosts, &dc_item->hostid)))
				continue;

			if (SUCCEED != DCin_maintenance_without_data_collection(dc_host, dc_item))
				goto out;
		}
	}

	ret = (ZBX_TRIGGER_TIMER_DEFAULT != trigger_timer ? SUCCEED : FAIL);
out:
	zbx_vector_uint64_destroy(&functionids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets timer triggers from cache                                    *
 *                                                                            *
 * Parameters: trigger_info  - [IN/OUT] triggers                              *
 *             trigger_order - [IN/OUT] triggers in processing order          *
 *             timers        - [IN] timers of triggers to retrieve            *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_triggers_by_timers(zbx_hashset_t *trigger_info, zbx_vector_dc_trigger_t *trigger_order,
		const zbx_vector_trigger_timer_ptr_t *timers)
{
	int		i;
	ZBX_DC_TRIGGER	*dc_trigger;

	RDLOCK_CACHE;

	for (i = 0; i < timers->values_num; i++)
	{
		zbx_trigger_timer_t	*timer = timers->values[i];

		/* skip timers of 'busy' (being processed) triggers */
		if (0 == timer->lock)
			continue;

		if (NULL != (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &timer->triggerid)))
		{
			zbx_dc_trigger_t	*trigger, trigger_local;
			unsigned char	flags;

			if (SUCCEED == DCconfig_find_active_time_function(dc_trigger->expression,
					dc_trigger->expression_bin, dc_trigger->timer & ZBX_TRIGGER_TIMER_EXPRESSION))
			{
				flags = ZBX_DC_TRIGGER_PROBLEM_EXPRESSION;
			}
			else
			{
				if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION != dc_trigger->recovery_mode)
					continue;

				if (TRIGGER_VALUE_PROBLEM != dc_trigger->value)
					continue;

				if (SUCCEED != DCconfig_find_active_time_function(dc_trigger->recovery_expression,
						dc_trigger->recovery_expression_bin,
						dc_trigger->timer & ZBX_TRIGGER_TIMER_RECOVERY_EXPRESSION))
				{
					continue;
				}

				flags = 0;
			}

			trigger_local.triggerid = dc_trigger->triggerid;
			trigger = (zbx_dc_trigger_t *)zbx_hashset_insert(trigger_info, &trigger_local, sizeof(trigger_local));
			DCget_trigger(trigger, dc_trigger, ZBX_TRIGGER_GET_ALL);

			trigger->timespec = timer->eval_ts;
			trigger->flags = flags;

			zbx_vector_dc_trigger_append(trigger_order, trigger);
		}
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate trigger timer                                            *
 *                                                                            *
 * Parameters: timer      - [IN] trigger timer                                *
 *             dc_trigger - [OUT] the trigger data                            *
 *                                                                            *
 * Return value: SUCCEED - the timer is valid                                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	trigger_timer_validate(zbx_trigger_timer_t *timer, ZBX_DC_TRIGGER **dc_trigger)
{
	ZBX_DC_FUNCTION		*dc_function;

	*dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &timer->triggerid);

	if (0 != (timer->type & ZBX_TRIGGER_TIMER_FUNCTION))
	{
		if (NULL == (dc_function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions, &timer->objectid)))
			return FAIL;

		if (dc_function->revision > timer->revision ||
				NULL == *dc_trigger ||
				TRIGGER_STATUS_ENABLED != (*dc_trigger)->status ||
				TRIGGER_FUNCTIONAL_TRUE != (*dc_trigger)->functional)
		{
			if (dc_function->timer_revision == timer->revision)
				dc_function->timer_revision = 0;
			return FAIL;
		}
	}
	else
	{
		if (NULL == (*dc_trigger))
			return FAIL;

		if ((*dc_trigger)->revision > timer->revision ||
				TRIGGER_STATUS_ENABLED != (*dc_trigger)->status ||
				TRIGGER_FUNCTIONAL_TRUE != (*dc_trigger)->functional)
		{
			if ((*dc_trigger)->timer_revision == timer->revision)
				(*dc_trigger)->timer_revision = 0;
			return FAIL;
		}
	}

	return SUCCEED;
}

static void	dc_remove_invalid_timer(zbx_trigger_timer_t *timer)
{
	if (0 != (timer->type & ZBX_TRIGGER_TIMER_FUNCTION))
	{
		ZBX_DC_FUNCTION	*function;

		if (NULL != (function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions,
				&timer->objectid)) && function->timer_revision == timer->revision)
		{
			function->timer_revision = 0;
		}
	}
	else if (ZBX_TRIGGER_TIMER_TRIGGER == timer->type)
	{
		ZBX_DC_TRIGGER	*trigger;

		if (NULL != (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers,
				&timer->objectid)) && trigger->timer_revision == timer->revision)
		{
			trigger->timer_revision = 0;
		}
	}

	dc_trigger_timer_free(timer);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets timers from trigger queue                                    *
 *                                                                            *
 * Parameters: timers     - [OUT] the timer triggers that must be processed   *
 *             now        - [IN] current time                                 *
 *             soft_limit - [IN] the number of timers to return unless timers *
 *                               of the same trigger are split over multiple  *
 *                               batches.                                     *
 *                                                                            *
 *             hard_limit - [IN] the maximum number of timers to return       *
 *                                                                            *
 * Comments: This function locks corresponding triggers in configuration      *
 *           cache.                                                           *
 *           If the returned timer has lock field set, then trigger is        *
 *           already being processed and should not be recalculated.          *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_trigger_timers(zbx_vector_trigger_timer_ptr_t *timers, int now, int soft_limit, int hard_limit)
{
	zbx_trigger_timer_t	*first_timer = NULL, *timer;
	int			found = 0;
	zbx_binary_heap_elem_t	*elem;

	RDLOCK_CACHE;

	if (SUCCEED != zbx_binary_heap_empty(&config->trigger_queue))
	{
		elem = zbx_binary_heap_find_min(&config->trigger_queue);
		timer = (zbx_trigger_timer_t *)elem->data;

		if (timer->check_ts.sec <= now)
			found = 1;
	}

	UNLOCK_CACHE;

	if (0 == found)
		return;

	WRLOCK_CACHE;

	while (SUCCEED != zbx_binary_heap_empty(&config->trigger_queue) && timers->values_num < hard_limit)
	{
		ZBX_DC_TRIGGER	*dc_trigger;

		elem = zbx_binary_heap_find_min(&config->trigger_queue);
		timer = (zbx_trigger_timer_t *)elem->data;

		if (timer->check_ts.sec > now)
			break;

		/* first_timer stores the first timer from a list of timers of the same trigger with the same */
		/* evaluation timestamp. Reset first_timer if the conditions do not apply.                    */
		if (NULL != first_timer && (timer->triggerid != first_timer->triggerid ||
				0 != zbx_timespec_compare(&timer->eval_ts, &first_timer->eval_ts)))
		{
			first_timer = NULL;
		}

		/* use soft limit to avoid (mostly) splitting multiple functions of the same trigger */
		/* over consequent batches                                                           */
		if (timers->values_num >= soft_limit && NULL == first_timer)
			break;

		zbx_binary_heap_remove_min(&config->trigger_queue);

		if (SUCCEED != trigger_timer_validate(timer, &dc_trigger))
		{
			dc_remove_invalid_timer(timer);
			continue;
		}

		zbx_vector_trigger_timer_ptr_append(timers, timer);

		/* timers scheduled to executed in future are taken from queue only */
		/* for rescheduling later - skip locking                            */
		if (timer->exec_ts.sec > now)
		{
			/* recalculate next execution time only for timers */
			/* scheduled to evaluate future data period        */
			if (timer->eval_ts.sec > now)
				timer->check_ts.sec = 0;
			continue;
		}

		/* Trigger expression must be calculated using function evaluation time. If a trigger is locked   */
		/* keep rescheduling its timer until trigger is unlocked and can be calculated using the required */
		/* evaluation time. However there are exceptions when evaluation time of a locked trigger is      */
		/* acceptable to evaluate other functions:                                                        */
		/*  1) time functions uses current time, so trigger evaluation time does not affect their results */
		/*  2) trend function of the same trigger with the same evaluation timestamp is being             */
		/*     evaluated by the same process                                                              */
		if (0 == dc_trigger->locked || ZBX_TRIGGER_TIMER_FUNCTION_TREND != timer->type ||
				(NULL != first_timer && 1 == first_timer->lock))
		{
			/* resetting execution timer will cause a new execution time to be set */
			/* when timer is put back into queue                                   */
			timer->check_ts.sec = 0;

			timer->lastcheck = (time_t)now;
		}

		/* remember if the timer locked trigger, so it would unlock during rescheduling */
		if (0 == dc_trigger->locked)
			dc_trigger->locked = timer->lock = 1;

		if (NULL == first_timer)
			first_timer = timer;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reschedule trigger timers                                         *
 *                                                                            *
 * Comments: Triggers are unlocked by zbx_dc_config_unlock_triggers()         *
 *                                                                            *
 ******************************************************************************/
static void	dc_reschedule_trigger_timers(zbx_vector_trigger_timer_ptr_t *timers, int now)
{
	int	i;

	for (i = 0; i < timers->values_num; i++)
	{
		zbx_trigger_timer_t	*timer = timers->values[i];

		timer->lock = 0;

		/* schedule calculation error can result in 0 execution time */
		if (0 == timer->check_ts.sec)
			dc_remove_invalid_timer(timer);
		else
			dc_schedule_trigger_timer(timer, now, &timer->eval_ts, &timer->check_ts);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: reschedule trigger timers while locking configuration cache       *
 *                                                                            *
 * Comments: Triggers are unlocked by zbx_dc_config_unlock_triggers()         *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_reschedule_trigger_timers(zbx_vector_trigger_timer_ptr_t *timers, int now)
{
	int			i;
	zbx_dc_um_handle_t	*um_handle;

	um_handle = zbx_dc_open_user_macros();

	/* calculate new execution/evaluation time for the evaluated triggers */
	/* (timers with reset execution time)                                 */
	for (i = 0; i < timers->values_num; i++)
	{
		zbx_trigger_timer_t	*timer = timers->values[i];

		if (0 == timer->check_ts.sec)
		{
			if (0 != (timer->check_ts.sec = (int)dc_function_calculate_nextcheck(um_handle, timer, now,
					timer->triggerid)))
			{
				timer->eval_ts = timer->check_ts;
			}
		}
	}

	zbx_dc_close_user_macros(um_handle);

	WRLOCK_CACHE;
	dc_reschedule_trigger_timers(timers, now);
	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clears timer trigger queue                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_clear_timer_queue(zbx_vector_trigger_timer_ptr_t *timers)
{
	ZBX_DC_FUNCTION	*function;
	int		i;

	zbx_vector_trigger_timer_ptr_reserve(timers, config->trigger_queue.elems_num);

	WRLOCK_CACHE;

	for (i = 0; i < config->trigger_queue.elems_num; i++)
	{
		zbx_trigger_timer_t	*timer = config->trigger_queue.elems[i].data;

		if (ZBX_TRIGGER_TIMER_FUNCTION_TREND == timer->type &&
				NULL != (function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions,
						&timer->objectid)) &&
				function->timer_revision == timer->revision)
		{
			zbx_vector_trigger_timer_ptr_append(timers, timer);
		}
		else
			dc_trigger_timer_free(timer);
	}

	zbx_binary_heap_clear(&config->trigger_queue);

	UNLOCK_CACHE;
}

void	zbx_dc_free_timers(zbx_vector_trigger_timer_ptr_t *timers)
{
	int	i;

	WRLOCK_CACHE;

	for (i = 0; i < timers->values_num; i++)
		dc_trigger_timer_free(timers->values[i]);

	UNLOCK_CACHE;
}

void	zbx_dc_free_triggers(zbx_vector_dc_trigger_t *triggers)
{
	int	i;

	for (i = 0; i < triggers->values_num; i++)
		DCclean_trigger(triggers->values[i]);

	zbx_vector_dc_trigger_clear(triggers);
}

void	zbx_dc_config_update_interface_snmp_stats(zbx_uint64_t interfaceid, int max_snmp_succeed, int min_snmp_fail)
{
	ZBX_DC_SNMPINTERFACE	*dc_snmp;

	WRLOCK_CACHE;

	if (NULL != (dc_snmp = (ZBX_DC_SNMPINTERFACE *)zbx_hashset_search(&config->interfaces_snmp, &interfaceid)) &&
			SNMP_BULK_ENABLED == dc_snmp->bulk)
	{
		if (dc_snmp->max_succeed < max_snmp_succeed)
			dc_snmp->max_succeed = (unsigned char)max_snmp_succeed;

		if (dc_snmp->min_fail > min_snmp_fail)
			dc_snmp->min_fail = (unsigned char)min_snmp_fail;
	}

	UNLOCK_CACHE;
}

static int	DCconfig_get_suggested_snmp_vars_nolock(zbx_uint64_t interfaceid, int *bulk)
{
	int				num;
	const ZBX_DC_SNMPINTERFACE	*dc_snmp;

	dc_snmp = (const ZBX_DC_SNMPINTERFACE *)zbx_hashset_search(&config->interfaces_snmp, &interfaceid);

	if (NULL != bulk)
		*bulk = (NULL == dc_snmp ? SNMP_BULK_DISABLED : dc_snmp->bulk);

	if (NULL == dc_snmp || SNMP_BULK_ENABLED != dc_snmp->bulk)
		return 1;

	/* The general strategy is to multiply request size by 3/2 in order to approach the limit faster. */
	/* However, once we are over the limit, we change the strategy to increasing the value by 1. This */
	/* is deemed better than going backwards from the error because less timeouts are going to occur. */

	if (1 >= dc_snmp->max_succeed || ZBX_MAX_SNMP_ITEMS + 1 != dc_snmp->min_fail)
		num = dc_snmp->max_succeed + 1;
	else
		num = dc_snmp->max_succeed * 3 / 2;

	if (num < dc_snmp->min_fail)
		return num;

	/* If we have already found the optimal number of variables to query, we wish to base our suggestion on that */
	/* number. If we occasionally get a timeout in this area, it can mean two things: either the device's actual */
	/* limit is a bit lower than that (it can process requests above it, but only sometimes) or a UDP packet in  */
	/* one of the directions was lost. In order to account for the former, we allow ourselves to lower the count */
	/* of variables, but only up to two times. Otherwise, performance will gradually degrade due to the latter.  */

	return MAX(dc_snmp->max_succeed - 2, dc_snmp->min_fail - 1);
}

int	zbx_dc_config_get_suggested_snmp_vars(zbx_uint64_t interfaceid, int *bulk)
{
	int	ret;

	RDLOCK_CACHE;

	ret = DCconfig_get_suggested_snmp_vars_nolock(interfaceid, bulk);

	UNLOCK_CACHE;

	return ret;
}

static int	dc_get_interface_by_type(zbx_dc_interface_t *interface, zbx_uint64_t hostid, unsigned char type)
{
	int				res = FAIL;
	const ZBX_DC_INTERFACE		*dc_interface;
	const ZBX_DC_INTERFACE_HT	*interface_ht;
	ZBX_DC_INTERFACE_HT		interface_ht_local;

	interface_ht_local.hostid = hostid;
	interface_ht_local.type = type;

	if (NULL != (interface_ht = (const ZBX_DC_INTERFACE_HT *)zbx_hashset_search(&config->interfaces_ht,
			&interface_ht_local)))
	{
		dc_interface = interface_ht->interface_ptr;
		DCget_interface(interface, dc_interface);
		res = SUCCEED;
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Locate main interface of specified type in configuration cache    *
 *                                                                            *
 * Parameters: interface - [OUT] pointer to zbx_dc_interface_t structure      *
 *             hostid - [IN] host ID                                          *
 *             type - [IN] interface type                                     *
 *                                                                            *
 * Return value: SUCCEED if record located and FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_get_interface_by_type(zbx_dc_interface_t *interface, zbx_uint64_t hostid, unsigned char type)
{
	int	res;

	RDLOCK_CACHE;

	res = dc_get_interface_by_type(interface, hostid, type);

	UNLOCK_CACHE;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Locate interface in configuration cache                           *
 *                                                                            *
 * Parameters: interface - [OUT] pointer to zbx_dc_interface_t structure      *
 *             hostid - [IN] host ID                                          *
 *             itemid - [IN] item ID                                          *
 *                                                                            *
 * Return value: SUCCEED if record located and FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_get_interface(zbx_dc_interface_t *interface, zbx_uint64_t hostid, zbx_uint64_t itemid)
{
	int			res = FAIL, i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_INTERFACE	*dc_interface;

	RDLOCK_CACHE;

	if (0 != itemid)
	{
		if (NULL == (dc_item = (const ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
			goto unlock;

		if (0 != dc_item->interfaceid)
		{
			if (NULL == (dc_interface = (const ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces,
					&dc_item->interfaceid)))
			{
				goto unlock;
			}

			DCget_interface(interface, dc_interface);
			res = SUCCEED;
			goto unlock;
		}

		hostid = dc_item->hostid;
	}

	if (0 == hostid)
		goto unlock;

	for (i = 0; i < INTERFACE_TYPE_COUNT; i++)
	{
		if (SUCCEED == (res = dc_get_interface_by_type(interface, hostid, zbx_get_interface_type_priority(i))))
			break;
	}

unlock:
	UNLOCK_CACHE;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a particular value associated with the interface.        *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_interface_value(zbx_uint64_t hostid, zbx_uint64_t itemid, char **replace_to, int request)
{
	int			res;
	zbx_dc_interface_t	interface;

	if (SUCCEED != (res = zbx_dc_config_get_interface(&interface, hostid, itemid)))
	{
		*replace_to = zbx_strdup(*replace_to, STR_UNKNOWN_VARIABLE);
		return SUCCEED;
	}

	switch (request)
	{
		case ZBX_REQUEST_HOST_IP:
			if ('\0' != *interface.ip_orig && FAIL == zbx_is_ip(interface.ip_orig))
				return FAIL;

			*replace_to = zbx_strdup(*replace_to, interface.ip_orig);
			break;
		case ZBX_REQUEST_HOST_DNS:
			if ('\0' != *interface.dns_orig && FAIL == zbx_is_ip(interface.dns_orig) &&
					FAIL == zbx_validate_hostname(interface.dns_orig))
			{
				return FAIL;
			}

			*replace_to = zbx_strdup(*replace_to, interface.dns_orig);
			break;
		case ZBX_REQUEST_HOST_CONN:
			if (FAIL == zbx_is_ip(interface.addr) &&
					FAIL == zbx_validate_hostname(interface.addr))
			{
				return FAIL;
			}

			*replace_to = zbx_strdup(*replace_to, interface.addr);
			break;
		case ZBX_REQUEST_HOST_PORT:
			*replace_to = zbx_strdup(*replace_to, interface.port_orig);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			res = FAIL;
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get nextcheck for selected queue                                  *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
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
 * Purpose: Get nextcheck for selected poller                                 *
 *                                                                            *
 * Parameters: poller_type - [IN] poller type (ZBX_POLLER_TYPE_...)           *
 *                                                                            *
 * Return value: nextcheck or FAIL if no items for selected poller            *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_get_poller_nextcheck(unsigned char poller_type)
{
	int			nextcheck;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() poller_type:%d", __func__, (int)poller_type);

	queue = &config->queues[poller_type];

	RDLOCK_CACHE;

	nextcheck = dc_config_get_queue_nextcheck(queue);

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, nextcheck);

	return nextcheck;
}

static void	dc_requeue_item(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host, const ZBX_DC_INTERFACE *dc_interface,
		int flags, int lastclock)
{
	unsigned char	old_poller_type;
	int		old_nextcheck;

	old_nextcheck = dc_item->nextcheck;
	DCitem_nextcheck_update(dc_item, dc_interface, flags, lastclock, NULL);

	old_poller_type = dc_item->poller_type;
	DCitem_poller_type_update(dc_item, dc_host, flags);

	DCupdate_item_queue(dc_item, old_poller_type, old_nextcheck);
}

/******************************************************************************
 *                                                                            *
 * Purpose: requeues items at the specified time                              *
 *                                                                            *
 * Parameters: dc_item   - [IN] the item to reque                             *
 *             dc_host   - [IN] item's host                                   *
 *             nextcheck - [IN] the scheduled time                            *
 *                                                                            *
 ******************************************************************************/
static void	dc_requeue_item_at(ZBX_DC_ITEM *dc_item, ZBX_DC_HOST *dc_host, time_t nextcheck)
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
 * Purpose: Get array of items for selected poller                            *
 *                                                                            *
 * Parameters: poller_type                  - [IN] poller type                *
 *             config_timeout               - [IN] timeout                    *
 *             processing                   - [IN] count of items in progress *
 *             config_max_concurrent_checks - [IN] max conncurect checks      *
 *             items                        - [OUT] array of items            *
 *                                                                            *
 * Return value: number of items in items array                               *
 *                                                                            *
 * Comments: Items leave the queue only through this function. Pollers must   *
 *           always return the items they have taken using                    *
 *           zbx_dc_requeue_items() or zbx_dc_poller_requeue_items().         *
 *                                                                            *
 *           Currently batch polling is supported only for JMX, SNMP and      *
 *           icmpping* simple checks. In other cases only single item is      *
 *           retrieved.                                                       *
 *                                                                            *
 *           IPMI poller queue are handled by                                 *
 *           zbx_dc_config_get_ipmi_poller_items() function.                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_get_poller_items(unsigned char poller_type, int config_timeout, int processing,
		int config_max_concurrent_checks, zbx_dc_item_t **items)
{
	int			now, num = 0, max_items, items_alloc = 0;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() poller_type:%d", __func__, (int)poller_type);

	now = time(NULL);

	queue = &config->queues[poller_type];

	switch (poller_type)
	{
		case ZBX_POLLER_TYPE_JAVA:
			max_items = ZBX_MAX_JAVA_ITEMS;
			break;
		case ZBX_POLLER_TYPE_PINGER:
			max_items = ZBX_MAX_PINGER_ITEMS;
			break;
		case ZBX_POLLER_TYPE_HTTPAGENT:
		case ZBX_POLLER_TYPE_AGENT:
		case ZBX_POLLER_TYPE_SNMP:
			if (0 == (max_items = config_max_concurrent_checks - processing))
				goto out;

			items_alloc = max_items;
			*items = zbx_malloc(NULL, sizeof(zbx_dc_item_t) * items_alloc);
			break;
		default:
			max_items = 1;
	}

	WRLOCK_CACHE;

	while (num < max_items && FAIL == zbx_binary_heap_empty(queue))
	{
		int				disable_until;
		const zbx_binary_heap_elem_t	*min;
		ZBX_DC_HOST			*dc_host;
		ZBX_DC_INTERFACE		*dc_interface;
		ZBX_DC_ITEM			*dc_item;
		static const ZBX_DC_ITEM	*dc_item_prev = NULL;

		min = zbx_binary_heap_find_min(queue);
		dc_item = (ZBX_DC_ITEM *)min->data;

		if (dc_item->nextcheck > now)
			break;

		if (0 != num)
		{
			if (ITEM_TYPE_SNMP == dc_item_prev->type)
			{
				if (ZBX_POLLER_TYPE_NORMAL == poller_type)
				{
					if (0 != __config_snmp_item_compare(dc_item_prev, dc_item))
						break;
				}
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

		dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &dc_item->interfaceid);

		if (HOST_STATUS_MONITORED != dc_host->status ||
				(HOST_MONITORED_BY_SERVER != dc_host->monitored_by &&
				SUCCEED != zbx_is_item_processed_by_server(dc_item->type, dc_item->key)))
		{
			continue;
		}

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
		{
			dc_requeue_item(dc_item, dc_host, dc_interface, ZBX_ITEM_COLLECTED, now);
			continue;
		}

		/* don't apply unreachable item/host throttling for prioritized items */
		if (ZBX_QUEUE_PRIORITY_HIGH != dc_item->queue_priority)
		{
			if (0 == (disable_until = DCget_disable_until(dc_item, dc_interface)))
			{
				/* move reachable items on reachable hosts to normal pollers */
				if (ZBX_POLLER_TYPE_UNREACHABLE == poller_type &&
						ZBX_QUEUE_PRIORITY_LOW != dc_item->queue_priority)
				{
					dc_requeue_item(dc_item, dc_host, dc_interface, ZBX_ITEM_COLLECTED, now);
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
					dc_requeue_item(dc_item, dc_host, dc_interface,
							ZBX_ITEM_COLLECTED | ZBX_HOST_UNREACHABLE, now);
					continue;
				}

				DCincrease_disable_until(dc_interface, now, config_timeout);
			}
		}

		if (0 == num)
		{
			if (ZBX_POLLER_TYPE_NORMAL == poller_type && ITEM_TYPE_SNMP == dc_item->type &&
					0 == (ZBX_FLAG_DISCOVERY_RULE & dc_item->flags))
			{
				if (ZBX_SNMP_OID_TYPE_NORMAL == dc_item->itemtype.snmpitem->snmp_oid_type ||
						ZBX_SNMP_OID_TYPE_DYNAMIC == dc_item->itemtype.snmpitem->snmp_oid_type)
				{
					max_items = DCconfig_get_suggested_snmp_vars_nolock(dc_item->interfaceid, NULL);
				}
			}

			if (1 < max_items && 0 == items_alloc)
				*items = zbx_malloc(NULL, sizeof(zbx_dc_item_t) * max_items);
		}

		dc_item_prev = dc_item;
		dc_item->location = ZBX_LOC_POLLER;
		DCget_host(&(*items)[num].host, dc_host);
		DCget_item(&(*items)[num], dc_item);
		num++;
	}

	UNLOCK_CACHE;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, num);

	return num;
}

#ifdef HAVE_OPENIPMI
/******************************************************************************
 *                                                                            *
 * Purpose: Get array of items for IPMI poller                                *
 *                                                                            *
 * Parameters: now            - [IN] current timestamp                        *
 *             items_num      - [IN] the number of items to get               *
 *             config_timeout - [IN]                                          *
 *             items          - [OUT] array of items                          *
 *             nextcheck      - [OUT] the next scheduled check                *
 *                                                                            *
 * Return value: number of items in items array                               *
 *                                                                            *
 * Comments: IPMI items leave the queue only through this function. IPMI      *
 *           manager must always return the items they have taken using       *
 *           zbx_dc_requeue_items() or zbx_dc_poller_requeue_items().         *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_get_ipmi_poller_items(int now, int items_num, int config_timeout, zbx_dc_item_t *items,
		int *nextcheck)
{
	int			num = 0;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	queue = &config->queues[ZBX_POLLER_TYPE_IPMI];

	WRLOCK_CACHE;

	while (num < items_num && FAIL == zbx_binary_heap_empty(queue))
	{
		int				disable_until;
		const zbx_binary_heap_elem_t	*min;
		ZBX_DC_HOST			*dc_host;
		ZBX_DC_INTERFACE		*dc_interface;
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

		if (NULL == (dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces,
				&dc_item->interfaceid)))
		{
			continue;
		}

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
		{
			dc_requeue_item(dc_item, dc_host, dc_interface, ZBX_ITEM_COLLECTED, now);
			continue;
		}

		/* don't apply unreachable item/host throttling for prioritized items */
		if (ZBX_QUEUE_PRIORITY_HIGH != dc_item->queue_priority)
		{
			if (0 != (disable_until = DCget_disable_until(dc_item, dc_interface)))
			{
				if (disable_until > now)
				{
					dc_requeue_item(dc_item, dc_host, dc_interface,
							ZBX_ITEM_COLLECTED | ZBX_HOST_UNREACHABLE, now);
					continue;
				}

				DCincrease_disable_until(dc_interface, now, config_timeout);
			}
		}

		dc_item->location = ZBX_LOC_POLLER;
		DCget_host(&items[num].host, dc_host);
		DCget_item(&items[num], dc_item);
		num++;
	}

	*nextcheck = dc_config_get_queue_nextcheck(&config->queues[ZBX_POLLER_TYPE_IPMI]);

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, num);

	return num;
}
#endif /* HAVE_OPENIPMI */

/******************************************************************************
 *                                                                            *
 * Purpose: get array of interface IDs for the specified address              *
 *                                                                            *
 * Return value: number of interface IDs returned                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_get_snmp_interfaceids_by_addr(const char *addr, zbx_uint64_t **interfaceids)
{
	int				count = 0, i;
	const ZBX_DC_INTERFACE_ADDR	*dc_interface_snmpaddr;
	ZBX_DC_INTERFACE_ADDR		dc_interface_snmpaddr_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s'", __func__, addr);

	dc_interface_snmpaddr_local.addr = addr;

	RDLOCK_CACHE;

	if (NULL == (dc_interface_snmpaddr = (const ZBX_DC_INTERFACE_ADDR *)zbx_hashset_search(
			&config->interface_snmpaddrs, &dc_interface_snmpaddr_local)))
	{
		goto unlock;
	}

	*interfaceids = (zbx_uint64_t *)zbx_malloc(*interfaceids, dc_interface_snmpaddr->interfaceids.values_num *
			sizeof(zbx_uint64_t));

	for (i = 0; i < dc_interface_snmpaddr->interfaceids.values_num; i++)
		(*interfaceids)[i] = dc_interface_snmpaddr->interfaceids.values[i];

	count = i;
unlock:
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, count);

	return count;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get array of snmp trap items for the specified interfaceid        *
 *                                                                            *
 * Return value: number of items returned                                     *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_dc_config_get_snmp_items_by_interfaceid(zbx_uint64_t interfaceid, zbx_dc_item_t **items)
{
	size_t				items_num = 0, items_alloc = 8;
	int				i;
	const ZBX_DC_ITEM		*dc_item;
	const ZBX_DC_INTERFACE_ITEM	*dc_interface_snmpitem;
	const ZBX_DC_INTERFACE		*dc_interface;
	const ZBX_DC_HOST		*dc_host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() interfaceid:" ZBX_FS_UI64, __func__, interfaceid);

	RDLOCK_CACHE;

	if (NULL == (dc_interface = (const ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid)))
		goto unlock;

	if (NULL == (dc_host = (const ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_interface->hostid)))
		goto unlock;

	if (HOST_STATUS_MONITORED != dc_host->status)
		goto unlock;

	if (NULL == (dc_interface_snmpitem = (const ZBX_DC_INTERFACE_ITEM *)zbx_hashset_search(
			&config->interface_snmpitems, &interfaceid)))
	{
		goto unlock;
	}

	*items = (zbx_dc_item_t *)zbx_malloc(*items, items_alloc * sizeof(zbx_dc_item_t));

	for (i = 0; i < dc_interface_snmpitem->itemids.values_num; i++)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items,
				&dc_interface_snmpitem->itemids.values[i])))
		{
			continue;
		}

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
			continue;

		if (items_num == items_alloc)
		{
			items_alloc += 8;
			*items = (zbx_dc_item_t *)zbx_realloc(*items, items_alloc * sizeof(zbx_dc_item_t));
		}

		DCget_host(&(*items)[items_num].host, dc_host);
		DCget_item(&(*items)[items_num], dc_item);
		items_num++;
	}
unlock:
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)items_num);

	return items_num;
}

static void	dc_requeue_items(const zbx_uint64_t *itemids, const int *lastclocks, const int *errcodes, size_t num)
{
	size_t			i;
	ZBX_DC_ITEM		*dc_item;
	ZBX_DC_HOST		*dc_host;
	ZBX_DC_INTERFACE	*dc_interface;

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

		if (SUCCEED != zbx_is_counted_in_item_queue(dc_item->type, dc_item->key))
			continue;

		dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &dc_item->interfaceid);

		switch (errcodes[i])
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
			case CONFIG_ERROR:
			case SIG_ERROR:
				dc_item->queue_priority = ZBX_QUEUE_PRIORITY_NORMAL;
				dc_requeue_item(dc_item, dc_host, dc_interface, ZBX_ITEM_COLLECTED, lastclocks[i]);
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
			case TIMEOUT_ERROR:
				dc_item->queue_priority = ZBX_QUEUE_PRIORITY_LOW;
				dc_requeue_item(dc_item, dc_host, dc_interface,
						ZBX_ITEM_COLLECTED | ZBX_HOST_UNREACHABLE, time(NULL));
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
}

void	zbx_dc_requeue_items(const zbx_uint64_t *itemids, const int *lastclocks, const int *errcodes, size_t num)
{
	WRLOCK_CACHE;

	dc_requeue_items(itemids, lastclocks, errcodes, num);

	UNLOCK_CACHE;
}

void	zbx_dc_poller_requeue_items(const zbx_uint64_t *itemids, const int *lastclocks,
		const int *errcodes, size_t num, unsigned char poller_type, int *nextcheck)
{
	WRLOCK_CACHE;

	dc_requeue_items(itemids, lastclocks, errcodes, num);
	*nextcheck = dc_config_get_queue_nextcheck(&config->queues[poller_type]);

	UNLOCK_CACHE;
}

#ifdef HAVE_OPENIPMI
/******************************************************************************
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
	size_t			i;
	ZBX_DC_ITEM		*dc_item;
	ZBX_DC_HOST		*dc_host;
	ZBX_DC_INTERFACE	*dc_interface;

	WRLOCK_CACHE;

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

		dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &dc_item->interfaceid);

		dc_requeue_item(dc_item, dc_host, dc_interface, ZBX_ITEM_COLLECTED | ZBX_HOST_UNREACHABLE,
				time(NULL));
	}

	UNLOCK_CACHE;
}
#endif /* HAVE_OPENIPMI */

/******************************************************************************
 *                                                                            *
 * Purpose: get interface availability data for the specified agent           *
 *                                                                            *
 * Parameters: dc_interface - [IN] the interface                              *
 *             availability - [OUT] the interface availability data           *
 *                                                                            *
 * Comments: The configuration cache must be locked already.                  *
 *                                                                            *
 ******************************************************************************/
static void	DCinterface_get_agent_availability(const ZBX_DC_INTERFACE *dc_interface,
		zbx_agent_availability_t *agent)
{

	agent->flags = ZBX_FLAGS_AGENT_STATUS;

	agent->available = dc_interface->available;
	agent->error = zbx_strdup(agent->error, dc_interface->error);
	agent->errors_from = dc_interface->errors_from;
	agent->disable_until = dc_interface->disable_until;
}

static void	DCagent_set_availability(zbx_agent_availability_t *av,  unsigned char *available, const char **error,
		int *errors_from, int *disable_until)
{
#define AGENT_AVAILABILITY_ASSIGN(flags, mask, dst, src)		\
	do								\
	{								\
		if (0 != (flags & mask))				\
		{							\
			if (dst != src)					\
				dst = src;				\
			else						\
				flags &= (unsigned char)(~(mask));	\
		}							\
	}								\
	while(0)

#define AGENT_AVAILABILITY_ASSIGN_STR(flags, mask, dst, src)		\
	do								\
	{								\
		if (0 != (flags & mask))				\
		{							\
			if (0 != strcmp(dst, src))			\
				dc_strpool_replace(1, &dst, src);	\
			else						\
				flags &= (unsigned char)(~(mask));	\
		}							\
	}								\
	while(0)

	AGENT_AVAILABILITY_ASSIGN(av->flags, ZBX_FLAGS_AGENT_STATUS_AVAILABLE, *available, av->available);
	AGENT_AVAILABILITY_ASSIGN_STR(av->flags, ZBX_FLAGS_AGENT_STATUS_ERROR, *error, av->error);
	AGENT_AVAILABILITY_ASSIGN(av->flags, ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM, *errors_from, av->errors_from);
	AGENT_AVAILABILITY_ASSIGN(av->flags, ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL, *disable_until, av->disable_until);

#undef AGENT_AVAILABILITY_ASSIGN_STR
#undef AGENT_AVAILABILITY_ASSIGN
}

/******************************************************************************
 *                                                                            *
 * Purpose: set interface availability data in configuration cache            *
 *                                                                            *
 * Parameters: dc_interface - [OUT] the interface                             *
 *             now          - [IN] current timestamp                          *
 *             agent        - [IN/OUT] the agent availability data            *
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
static int	DCinterface_set_agent_availability(ZBX_DC_INTERFACE *dc_interface, int now,
		zbx_agent_availability_t *agent)
{
	DCagent_set_availability(agent, &dc_interface->available, &dc_interface->error,
			&dc_interface->errors_from, &dc_interface->disable_until);

	if (ZBX_FLAGS_AGENT_STATUS_NONE == agent->flags)
		return FAIL;

	if (0 != (agent->flags & (ZBX_FLAGS_AGENT_STATUS_AVAILABLE | ZBX_FLAGS_AGENT_STATUS_ERROR)))
		dc_interface->availability_ts = now;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set interface availability data in configuration cache            *
 *                                                                            *
 * Parameters: dc_interface - [OUT] the interface                             *
 *             ia           - [IN/OUT] the interface availability data        *
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
static int	DCinterface_set_availability(ZBX_DC_INTERFACE *dc_interface, int now, zbx_interface_availability_t *ia)
{
	unsigned char	flags = ZBX_FLAGS_AGENT_STATUS_NONE;

	DCagent_set_availability(&ia->agent, &dc_interface->available, &dc_interface->error,
			&dc_interface->errors_from, &dc_interface->disable_until);

	flags |= ia->agent.flags;

	if (ZBX_FLAGS_AGENT_STATUS_NONE == flags)
		return FAIL;

	if (0 != (flags & (ZBX_FLAGS_AGENT_STATUS_AVAILABLE | ZBX_FLAGS_AGENT_STATUS_ERROR)))
		dc_interface->availability_ts = now;

	return SUCCEED;
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

/*******************************************************************************
 *                                                                             *
 * Purpose: set interface as available based on the agent availability data    *
 *                                                                             *
 * Parameters: interfaceid - [IN] the interface identifier                     *
 *             ts          - [IN] the last timestamp                           *
 *             in          - [IN/OUT] IN: the caller's agent availability data *
 *                                   OUT: the agent availability data in cache *
 *                                        before changes                       *
 *             out         - [OUT] the agent availability data after changes   *
 *                                                                             *
 * Return value: SUCCEED - the interface was activated successfully            *
 *               FAIL    - the interface was already activated or activation   *
 *                         failed                                              *
 *                                                                             *
 * Comments: The interface availability fields are updated according to the    *
 *           above schema.                                                     *
 *                                                                             *
 *******************************************************************************/
int	zbx_dc_interface_activate(zbx_uint64_t interfaceid, const zbx_timespec_t *ts,
		zbx_agent_availability_t *in, zbx_agent_availability_t *out)
{
	int			ret = FAIL;
	ZBX_DC_HOST		*dc_host;
	ZBX_DC_INTERFACE	*dc_interface;

	/* don't try activating interface if there were no errors detected */
	if (0 == in->errors_from && ZBX_INTERFACE_AVAILABLE_TRUE == in->available)
		goto out;

	WRLOCK_CACHE;

	if (NULL == (dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid)))
		goto unlock;

	if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_interface->hostid)))
		goto unlock;

	/* Don't try activating interface if:                */
	/* - (server, proxy) host is not monitored any more; */
	/* - (server) host is monitored by proxy.            */
	if ((0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER) && 0 != dc_host->proxyid) ||
			HOST_STATUS_MONITORED != dc_host->status)
	{
		goto unlock;
	}

	DCinterface_get_agent_availability(dc_interface, in);
	zbx_agent_availability_init(out, ZBX_INTERFACE_AVAILABLE_TRUE, "", 0, 0);

	if (SUCCEED == DCinterface_set_agent_availability(dc_interface, ts->sec, out) &&
			ZBX_FLAGS_AGENT_STATUS_NONE != out->flags)
	{
		ret = SUCCEED;
	}
unlock:
	UNLOCK_CACHE;
out:
	return ret;
}

void	zbx_dc_set_interface_version(zbx_uint64_t interfaceid, int version)
{
	ZBX_DC_INTERFACE	*dc_interface;

	WRLOCK_CACHE;

	if (NULL != (dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid)))
		dc_interface->version = version;

	UNLOCK_CACHE;
}

/***************************************************************************************
 *                                                                                     *
 * Purpose: attempt to set interface as unavailable based on agent availability        *
 *                                                                                     *
 * Parameters: interfaceid        - [IN] interface identifier                          *
 *             ts                 - [IN] last timestamp                                *
 *             unavailable_delay  - [IN]                                               *
 *             unreachable_period - [IN]                                               *
 *             unreachable_delay  - [IN]                                               *
 *             in                 - [IN/OUT] IN: caller's interface availability data  *
 *                                          OUT: interface availability data in cache  *
 *                                               before changes                        *
 *             out               - [OUT] interface availability data after changes     *
 *             error_msg         - [IN] error message                                  *
 *                                                                                     *
 * Return value: SUCCEED - the interface was deactivated successfully                  *
 *               FAIL    - the interface was already deactivated or deactivation       *
 *                         failed                                                      *
 *                                                                                     *
 * Comments: The interface availability fields are updated according to the above      *
 *           schema.                                                                   *
 *                                                                                     *
 ***************************************************************************************/
int	zbx_dc_interface_deactivate(zbx_uint64_t interfaceid, const zbx_timespec_t *ts, int unavailable_delay,
		int unreachable_period, int unreachable_delay, zbx_agent_availability_t *in,
		zbx_agent_availability_t *out, const char *error_msg)
{
	int			ret = FAIL, errors_from, disable_until;
	const char		*error;
	unsigned char		available;
	ZBX_DC_HOST		*dc_host;
	ZBX_DC_INTERFACE	*dc_interface;

	/* don't try deactivating interface if the unreachable delay has not passed since the first error */
	if (unreachable_delay > ts->sec - in->errors_from)
		goto out;

	WRLOCK_CACHE;

	if (NULL == (dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &interfaceid)))
		goto unlock;

	if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_interface->hostid)))
		goto unlock;

	/* Don't try deactivating interface if:               */
	/* - (server, proxy) host is not monitored any more;  */
	/* - (server) host is monitored by proxy.             */
	if ((0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER) && 0 != dc_host->proxyid) ||
			HOST_STATUS_MONITORED != dc_host->status)
	{
		goto unlock;
	}

	DCinterface_get_agent_availability(dc_interface, in);

	available = in->available;
	error = in->error;

	if (0 == in->errors_from)
	{
		/* first error, schedule next unreachable check */
		errors_from = ts->sec;
		disable_until = ts->sec + unreachable_delay;
	}
	else
	{
		errors_from = in->errors_from;
		disable_until = in->disable_until;

		/* Check if other pollers haven't already attempted deactivating host. */
		/* In that case should wait the initial unreachable delay before       */
		/* trying to make it unavailable.                                      */
		if (unreachable_delay <= ts->sec - errors_from)
		{
			/* repeating error */
			if (unreachable_period > ts->sec - errors_from)
			{
				/* leave host available, schedule next unreachable check */
				disable_until = ts->sec + unreachable_delay;
			}
			else
			{
				/* make host unavailable, schedule next unavailable check */
				disable_until = ts->sec + unavailable_delay;
				available = ZBX_INTERFACE_AVAILABLE_FALSE;
				error = error_msg;
			}
		}
	}

	zbx_agent_availability_init(out, available, error, errors_from, disable_until);

	if (SUCCEED == DCinterface_set_agent_availability(dc_interface, ts->sec, out) &&
			ZBX_FLAGS_AGENT_STATUS_NONE != out->flags)
	{
		ret = SUCCEED;
	}
unlock:
	UNLOCK_CACHE;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update availability of interfaces in configuration cache and      *
 *          return the updated field flags                                    *
 *                                                                            *
 * Parameters: availabilities - [IN/OUT] the interfaces availability data     *
 *                                                                            *
 * Return value: SUCCEED - at least one interface availability data           *
 *                         was updated                                        *
 *               FAIL    - no interfaces were updated                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_set_interfaces_availability(zbx_vector_availability_ptr_t *availabilities)
{
	int				i;
	ZBX_DC_INTERFACE		*dc_interface;
	zbx_interface_availability_t	*ia;
	int				ret = FAIL, now;

	now = (int)time(NULL);

	WRLOCK_CACHE;

	for (i = 0; i < availabilities->values_num; i++)
	{
		ia = availabilities->values[i];

		if (NULL == (dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces,
				&ia->interfaceid)))
		{
			int	j;

			/* reset availability flag so this host is ignored when saving availability diff to DB */
			for (j = 0; j < ZBX_AGENT_MAX; j++)
				ia->agent.flags = ZBX_FLAGS_AGENT_STATUS_NONE;

			continue;
		}

		if (SUCCEED == DCinterface_set_availability(dc_interface, now, ia))
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
 * Purpose: check whether any of trigger dependencies have value PROBLEM      *
 *                                                                            *
 * Return value: SUCCEED - trigger can change its value                       *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_check_trigger_dependencies(zbx_uint64_t triggerid)
{
	int				ret = SUCCEED;
	const ZBX_DC_TRIGGER_DEPLIST	*trigdep;

	RDLOCK_CACHE;

	if (NULL != (trigdep = (const ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_search(&config->trigdeps, &triggerid)))
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
 * Purpose: assign each trigger an index based on trigger dependency topology *
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

		if (NULL == trigger || ZBX_FLAG_DISCOVERY_PROTOTYPE == trigger->flags || 1 < trigger->topoindex ||
				0 == trigdep->dependencies.values_num)
		{
			continue;
		}

		DCconfig_sort_triggers_topologically_rec(trigdep, 0);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply trigger value,state,lastchange or error changes to          *
 *          configuration cache after committed to database                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_triggers_apply_changes(zbx_vector_trigger_diff_ptr_t *trigger_diff)
{
	int			i;
	zbx_trigger_diff_t	*diff;
	ZBX_DC_TRIGGER		*dc_trigger;

	if (0 == trigger_diff->values_num)
		return;

	WRLOCK_CACHE;

	for (i = 0; i < trigger_diff->values_num; i++)
	{
		diff = trigger_diff->values[i];

		if (NULL == (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&config->triggers, &diff->triggerid)))
			continue;

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE))
			dc_trigger->lastchange = diff->lastchange;

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE))
			dc_trigger->value = diff->value;

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE))
			dc_trigger->state = diff->state;

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR))
			dc_strpool_replace(1, &dc_trigger->error, diff->error);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get statistics of the database cache                              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_dc_config_get_stats(int request)
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
		case ZBX_CONFSTATS_BUFFER_PUSED:
			value_double = 100 * (double)(config_mem->orig_size - config_mem->free_size) /
					config_mem->orig_size;
			return &value_double;
		case ZBX_CONFSTATS_BUFFER_PFREE:
			value_double = 100 * (double)config_mem->free_size / config_mem->orig_size;
			return &value_double;
		default:
			return NULL;
	}
}

static void	DCget_proxy(zbx_dc_proxy_t *dst_proxy, const ZBX_DC_PROXY *src_proxy)
{
	dst_proxy->proxyid = src_proxy->proxyid;
	dst_proxy->proxy_groupid = src_proxy->proxy_groupid;
	dst_proxy->proxy_config_nextcheck = src_proxy->proxy_config_nextcheck;
	dst_proxy->proxy_data_nextcheck = src_proxy->proxy_data_nextcheck;
	dst_proxy->proxy_tasks_nextcheck = src_proxy->proxy_tasks_nextcheck;
	dst_proxy->last_cfg_error_time = src_proxy->last_cfg_error_time;
	zbx_strlcpy(dst_proxy->version_str, src_proxy->version_str, sizeof(dst_proxy->version_str));
	dst_proxy->version_int = src_proxy->version_int;
	dst_proxy->compatibility = src_proxy->compatibility;
	dst_proxy->lastaccess = src_proxy->lastaccess;
	dst_proxy->last_version_error_time = src_proxy->last_version_error_time;

	dst_proxy->revision = src_proxy->revision;
	dst_proxy->macro_revision = config->um_cache->revision;

	zbx_strscpy(dst_proxy->name, src_proxy->name);
	zbx_strscpy(dst_proxy->allowed_addresses, src_proxy->allowed_addresses);

	dst_proxy->tls_connect = src_proxy->tls_connect;
	dst_proxy->tls_accept = src_proxy->tls_accept;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_strscpy(dst_proxy->tls_issuer, src_proxy->tls_issuer);
	zbx_strscpy(dst_proxy->tls_subject, src_proxy->tls_subject);

	if (NULL == src_proxy->tls_dc_psk)
	{
		*dst_proxy->tls_psk_identity = '\0';
		*dst_proxy->tls_psk = '\0';
	}
	else
	{
		zbx_strscpy(dst_proxy->tls_psk_identity, src_proxy->tls_dc_psk->tls_psk_identity);
		zbx_strscpy(dst_proxy->tls_psk, src_proxy->tls_dc_psk->tls_psk);
	}
#endif

	if (PROXY_OPERATING_MODE_PASSIVE == src_proxy->mode)
	{
		zbx_strscpy(dst_proxy->addr_orig, src_proxy->address);
		zbx_strscpy(dst_proxy->port_orig, src_proxy->port);
	}
	else
	{
		*dst_proxy->addr_orig = '\0';
		*dst_proxy->port_orig = '\0';
	}

	dst_proxy->addr = NULL;
	dst_proxy->port = 0;
}

int	zbx_dc_config_get_last_sync_time(void)
{
	return config->sync_ts;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get array of proxies for proxy poller                             *
 *                                                                            *
 * Parameters: hosts - [OUT] array of hosts                                   *
 *             max_hosts - [IN] elements in hosts array                       *
 *                                                                            *
 * Return value: number of proxies in hosts array                             *
 *                                                                            *
 * Comments: Proxies leave the queue only through this function. Pollers must *
 *           always return the proxies they have taken using                  *
 *           zbx_dc_requeue_proxy.                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_get_proxypoller_hosts(zbx_dc_proxy_t *proxies, int max_hosts)
{
	time_t			now;
	int			num = 0;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);

	queue = &config->pqueue;

	WRLOCK_CACHE;

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, num);

	return num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get nextcheck for passive proxies                                 *
 *                                                                            *
 * Return value: nextcheck or FAIL if no passive proxies in queue             *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_config_get_proxypoller_nextcheck(void)
{
	int			nextcheck;
	zbx_binary_heap_t	*queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	queue = &config->pqueue;

	RDLOCK_CACHE;

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, nextcheck);

	return nextcheck;
}

void	zbx_dc_requeue_proxy(zbx_uint64_t proxyid, unsigned char update_nextcheck, int proxy_conn_err,
		int proxyconfig_frequency, int proxydata_frequency)
{
	time_t		now;
	ZBX_DC_PROXY	*dc_proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() update_nextcheck:%d", __func__, (int)update_nextcheck);

	now = time(NULL);

	WRLOCK_CACHE;

	if (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxyid)))
	{
		if (ZBX_LOC_POLLER == dc_proxy->location)
			dc_proxy->location = ZBX_LOC_NOWHERE;

		/* set or clear passive proxy misconfiguration error timestamp */
		if (SUCCEED == proxy_conn_err)
			dc_proxy->last_cfg_error_time = 0;
		else if (CONFIG_ERROR == proxy_conn_err)
			dc_proxy->last_cfg_error_time = now;

		if (PROXY_OPERATING_MODE_PASSIVE == dc_proxy->mode)
		{
			if (0 != (update_nextcheck & ZBX_PROXY_CONFIG_NEXTCHECK))
			{
				dc_proxy->proxy_config_nextcheck = calculate_proxy_nextcheck(
						proxyid, proxyconfig_frequency, now);
			}

			if (0 != (update_nextcheck & ZBX_PROXY_DATA_NEXTCHECK))
			{
				dc_proxy->proxy_data_nextcheck = calculate_proxy_nextcheck(
						proxyid, proxydata_frequency, now);
			}
			if (0 != (update_nextcheck & ZBX_PROXY_TASKS_NEXTCHECK))
			{
				dc_proxy->proxy_tasks_nextcheck = calculate_proxy_nextcheck(
						proxyid, ZBX_TASK_UPDATE_FREQUENCY, now);
			}

			DCupdate_proxy_queue(dc_proxy);
		}
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/********************************************************************************
 *                                                                              *
 * Purpose: frees item queue data vector created by zbx_dc_get_item_queue()     *
 *                                                                              *
 * Parameters: queue - [IN] item queue data vector to free                      *
 *                                                                              *
 *******************************************************************************/
void	zbx_dc_free_item_queue(zbx_vector_queue_item_ptr_t *queue)
{
	for (int i = 0; i < queue->values_num; i++)
		zbx_free(queue->values[i]);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves vector of delayed items                                 *
 *                                                                            *
 * Parameters: queue - [OUT] vector of delayed items (optional)               *
 *             from  - [IN] minimum delay time in seconds (non-negative)      *
 *             to    - [IN] maximum delay time in seconds or                  *
 *                          ZBX_QUEUE_TO_INFINITY if there is no limit        *
 *                                                                            *
 * Return value: number of delayed items                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_item_queue(zbx_vector_queue_item_ptr_t *queue, int from, int to)
{
	zbx_hashset_iter_t	iter;
	const ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST		*dc_host;
	int			now, nitems = 0, data_expected_from, delay;
	zbx_queue_item_t	*queue_item;

	now = (int)time(NULL);

	RDLOCK_CACHE_CONFIG_HISTORY;

	zbx_hashset_iter_reset(&config->hosts, &iter);

	while (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		const ZBX_DC_INTERFACE	*dc_interface = NULL;
		zbx_hashset_iter_t	item_iter;
		ZBX_DC_ITEM_REF		*ref;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		zbx_hashset_iter_reset(&dc_host->items, &item_iter);
		while (NULL != (ref = (ZBX_DC_ITEM_REF *)zbx_hashset_iter_next(&item_iter)))
		{
			char	*delay_s;
			int	ret;

			dc_item = ref->item;

			if (ITEM_STATUS_ACTIVE != dc_item->status)
				continue;

			if (SUCCEED != zbx_is_counted_in_item_queue(dc_item->type, dc_item->key))
				continue;

			if (SUCCEED == DCin_maintenance_without_data_collection(dc_host, dc_item))
				continue;

			if (now - dc_item->nextcheck < from || (ZBX_QUEUE_TO_INFINITY != to &&
					now - dc_item->nextcheck >= to))
			{
				continue;
			}

			switch (dc_item->type)
			{
				case ITEM_TYPE_ZABBIX:
				case ITEM_TYPE_SNMP:
				case ITEM_TYPE_IPMI:
				case ITEM_TYPE_JMX:
					if (NULL == dc_interface || dc_interface->interfaceid != dc_item->interfaceid)
					{
						if (NULL == (dc_interface = (const ZBX_DC_INTERFACE *)zbx_hashset_search(
								&config->interfaces, &dc_item->interfaceid)))
						{
							continue;
						}
					}

					if (ZBX_INTERFACE_AVAILABLE_TRUE != dc_interface->available)
						continue;
					break;
				case ITEM_TYPE_ZABBIX_ACTIVE:
					if (dc_host->data_expected_from >
						(data_expected_from = dc_item->data_expected_from))
					{
						data_expected_from = dc_host->data_expected_from;
					}

					delay_s = dc_expand_user_and_func_macros_dyn(dc_item->delay, &dc_item->hostid,
							1, ZBX_MACRO_ENV_NONSECURE);
					ret = zbx_interval_preproc(delay_s, &delay, NULL, NULL);
					zbx_free(delay_s);

					if (SUCCEED != ret)
						continue;
					if (data_expected_from + delay > now)
						continue;
					break;

			}

			if (NULL != queue)
			{
				queue_item = (zbx_queue_item_t *)zbx_malloc(NULL, sizeof(zbx_queue_item_t));
				queue_item->itemid = dc_item->itemid;
				queue_item->type = dc_item->type;
				queue_item->nextcheck = dc_item->nextcheck;
				queue_item->proxyid = dc_host->proxyid;

				zbx_vector_queue_item_ptr_append(queue, queue_item);
			}
			nitems++;
		}
	}

	UNLOCK_CACHE_CONFIG_HISTORY;

	return nitems;
}

typedef struct
{
	zbx_uint64_t	id;
	uint64_t	items_active_normal;
	uint64_t	items_active_normal_old;
	uint64_t	items_active_notsupported;
	uint64_t	items_active_notsupported_old;
	uint64_t	items_disabled;
	uint64_t	items_disabled_old;
	zbx_uint64_t	hosts_monitored;
	zbx_uint64_t	hosts_monitored_old;
	zbx_uint64_t	hosts_not_monitored;
	zbx_uint64_t	hosts_not_monitored_old;
	double		required_performance;
	double		required_performance_old;
}
zbx_dc_status_diff_proxy_t;

static void	dc_status_diff_init(zbx_dc_status_diff_t *diff)
{
	memset(diff, 0, sizeof(zbx_dc_status_diff_t));

	zbx_vector_status_diff_host_create(&diff->hosts);

	zbx_hashset_create(&diff->proxies, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

static void	dc_status_diff_destroy(zbx_dc_status_diff_t *diff)
{
	zbx_vector_status_diff_host_destroy(&diff->hosts);
	zbx_hashset_destroy(&diff->proxies);
}

static void	dc_status_update_apply_diff(zbx_dc_status_diff_t *diff)
{
	ZBX_DC_PROXY			*dc_proxy;
	ZBX_DC_HOST			*dc_host;
	zbx_dc_status_diff_proxy_t	*proxy_diff;
	zbx_hashset_iter_t		iter;

	if (0 != config->status->last_update && config->status->last_update + ZBX_STATUS_LIFETIME > time(NULL))
		return;

	config->status->sync_ts = config->sync_ts;

	zbx_hashset_iter_reset(&diff->proxies, &iter);

	while (NULL != (proxy_diff = (zbx_dc_status_diff_proxy_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxy_diff->id)))
		{
			continue;
		}

		dc_proxy->hosts_monitored = proxy_diff->hosts_monitored;
		dc_proxy->hosts_not_monitored = proxy_diff->hosts_not_monitored;
		dc_proxy->required_performance = proxy_diff->required_performance;
		dc_proxy->items_active_normal = proxy_diff->items_active_normal;
		dc_proxy->items_active_notsupported = proxy_diff->items_active_notsupported;
		dc_proxy->items_disabled = proxy_diff->items_disabled;
	}

	for (int i = 0; i < diff->hosts.values_num; i++)
	{
		zbx_uint64_t	hostid = diff->hosts.values[i].id;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
			continue;

		dc_host->items_active_normal = diff->hosts.values[i].items_active_normal;
		dc_host->items_active_notsupported = diff->hosts.values[i].items_active_notsupported;
	}

	config->status->required_performance = diff->required_performance;
	config->status->triggers_disabled = diff->triggers_disabled;
	config->status->triggers_enabled_ok = diff->triggers_enabled_ok;
	config->status->triggers_enabled_problem = diff->triggers_enabled_problem;
	config->status->hosts_monitored = diff->hosts_monitored;
	config->status->hosts_not_monitored = diff->hosts_not_monitored;
	config->status->items_active_normal = diff->items_active_normal;
	config->status->items_active_notsupported = diff->items_active_notsupported;
	config->status->items_disabled = diff->items_disabled;

	config->status->last_update = time(NULL);
}

static int	get_active_item_count_rec(const ZBX_DC_ITEM *dc_item)
{
	int	count = 1;

	if (NULL != dc_item->master_item)
	{
		zbx_hashset_iter_t	iter;
		zbx_uint64_t		*pitemid;

		zbx_hashset_iter_reset(&dc_item->master_item->dep_itemids, &iter);
		while (NULL != (pitemid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
		{
			ZBX_DC_ITEM	*dep_item;

			if (NULL != (dep_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, pitemid)) &&
					ITEM_STATUS_ACTIVE == dep_item->status)
			{
				count += get_active_item_count_rec(dep_item);
			}
		}
	}

	return count;
}

static void	update_required_performance(const ZBX_DC_ITEM *dc_item, zbx_dc_status_diff_proxy_t *proxy_diff,
		zbx_dc_status_diff_t *diff)
{
	int	delay;
	char	*delay_s;

	delay_s = dc_expand_user_and_func_macros_dyn(dc_item->delay, &dc_item->hostid, 1, ZBX_MACRO_ENV_NONSECURE);

	if (SUCCEED == zbx_interval_preproc(delay_s, &delay, NULL, NULL) && 0 != delay)
	{
		int	item_count;

		if (0 < (item_count = get_active_item_count_rec(dc_item)))
		{
			diff->required_performance += 1.0 / delay * item_count;

			if (NULL != proxy_diff)
				proxy_diff->required_performance += 1.0 / delay * item_count;
		}
	}

	zbx_free(delay_s);
}

static void	get_host_statistics(ZBX_DC_HOST *dc_host, zbx_dc_status_diff_host_t *host_diff,
		zbx_dc_status_diff_proxy_t *proxy_diff, zbx_dc_status_diff_t *diff)
{
	const ZBX_DC_ITEM	*dc_item;
	zbx_hashset_iter_t	iter;
	ZBX_DC_ITEM_REF		*ref;

	/* loop over items to gather per-host and per-proxy statistics */
	zbx_hashset_iter_reset(&dc_host->items, &iter);
	while (NULL != (ref = (ZBX_DC_ITEM_REF *)zbx_hashset_iter_next(&iter)))
	{
		dc_item = ref->item;

		if (ZBX_FLAG_DISCOVERY_NORMAL != dc_item->flags && ZBX_FLAG_DISCOVERY_CREATED != dc_item->flags)
			continue;

		switch (dc_item->status)
		{
			case ITEM_STATUS_ACTIVE:
				if (HOST_STATUS_MONITORED == dc_host->status)
				{
					if (SUCCEED == diff->reset && ITEM_TYPE_DEPENDENT != dc_item->type)
						update_required_performance(dc_item, proxy_diff, diff);

					switch (dc_item->state)
					{
						case ITEM_STATE_NORMAL:
							diff->items_active_normal++;
							host_diff->items_active_normal++;
							if (NULL != proxy_diff)
								proxy_diff->items_active_normal++;
							break;
						case ITEM_STATE_NOTSUPPORTED:
							diff->items_active_notsupported++;
							host_diff->items_active_notsupported++;
							if (NULL != proxy_diff)
								proxy_diff->items_active_notsupported++;
							break;
						default:
							zabbix_log(LOG_LEVEL_DEBUG, "%s() failed to count statistics "
									"for itemid " ZBX_FS_UI64, __func__,
									dc_item->itemid);
					}

					break;
				}
				ZBX_FALLTHROUGH;
			case ITEM_STATUS_DISABLED:
				diff->items_disabled++;
				if (NULL != proxy_diff)
					proxy_diff->items_disabled++;
				break;
			default:
				zabbix_log(LOG_LEVEL_DEBUG, "%s() failed to count statistics for "
						"itemid " ZBX_FS_UI64, __func__, dc_item->itemid);
		}
	}
}

static void	get_trigger_statistics(zbx_hashset_t *triggers, zbx_dc_status_diff_t *diff)
{
	zbx_hashset_iter_t	iter;
	ZBX_DC_TRIGGER		*dc_trigger;

	zbx_hashset_iter_reset(triggers, &iter);
	/* loop over triggers to gather enabled and disabled trigger statistics */
	while (NULL != (dc_trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_FLAG_DISCOVERY_PROTOTYPE == dc_trigger->flags || NULL == dc_trigger->itemids)
			continue;

		switch (dc_trigger->status)
		{
			case TRIGGER_STATUS_ENABLED:
				if (TRIGGER_FUNCTIONAL_TRUE == dc_trigger->functional)
				{
					switch (dc_trigger->value)
					{
						case TRIGGER_VALUE_OK:
							diff->triggers_enabled_ok++;
							break;
						case TRIGGER_VALUE_PROBLEM:
							diff->triggers_enabled_problem++;
							break;
						default:
							zabbix_log(LOG_LEVEL_DEBUG, "%s() failed to count statistics "
									"for triggerid " ZBX_FS_UI64, __func__,
									dc_trigger->triggerid);
					}

					break;
				}
				ZBX_FALLTHROUGH;
			case TRIGGER_STATUS_DISABLED:
				diff->triggers_disabled++;
				break;
			default:
				zabbix_log(LOG_LEVEL_DEBUG, "%s() failed to count statistics for "
						"triggerid " ZBX_FS_UI64, __func__, dc_trigger->triggerid);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve status diff from configuration cache.                    *
 *          To be called under read lock.                                     *
 *                                                                            *
 * Parameters: diff - [OUT]                                                   *
 *                                                                            *
 ******************************************************************************/
static int	dc_status_update_get_diff(zbx_dc_status_diff_t *diff)
{
	zbx_hashset_iter_t	iter;
	ZBX_DC_HOST		*dc_host;
	ZBX_DC_PROXY		*dc_proxy;

	if (0 != config->status->last_update && config->status->last_update + ZBX_STATUS_LIFETIME > time(NULL))
		return FAIL;

	if (config->status->sync_ts != config->sync_ts)
		diff->reset = SUCCEED;
	else
		diff->reset = FAIL;

	if (SUCCEED != diff->reset)
		diff->required_performance = config->status->required_performance;

	zbx_hashset_iter_reset(&config->proxies, &iter);

	while (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_iter_next(&iter)))
	{
		zbx_dc_status_diff_proxy_t	proxy_diff_local;

		memset(&proxy_diff_local, 0, sizeof(zbx_dc_status_diff_proxy_t));

		proxy_diff_local.id = dc_proxy->proxyid;

		proxy_diff_local.items_active_normal_old = dc_proxy->items_active_normal;
		proxy_diff_local.items_active_notsupported_old = dc_proxy->items_active_notsupported;
		proxy_diff_local.items_disabled_old = dc_proxy->items_disabled;
		proxy_diff_local.hosts_monitored_old = dc_proxy->hosts_monitored;
		proxy_diff_local.hosts_not_monitored_old = dc_proxy->hosts_not_monitored;
		proxy_diff_local.required_performance_old = dc_proxy->required_performance;

		if (SUCCEED != diff->reset)
			proxy_diff_local.required_performance = dc_proxy->required_performance;

		zbx_hashset_insert(&diff->proxies, &proxy_diff_local, sizeof(proxy_diff_local));
	}

	/* loop over hosts */

	zbx_hashset_iter_reset(&config->hosts, &iter);

	while (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		zbx_dc_status_diff_host_t	host_diff_local;
		zbx_dc_status_diff_proxy_t	*proxy_status_diff = NULL;

		memset(&host_diff_local, 0, sizeof(zbx_dc_status_diff_host_t));
		host_diff_local.id = dc_host->hostid;

		/* gather per-proxy statistics of enabled and disabled hosts */
		switch (dc_host->status)
		{
			case HOST_STATUS_MONITORED:
				diff->hosts_monitored++;
				if (0 == dc_host->proxyid)
					break;

				if (NULL == (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies,
						&dc_host->proxyid)))
				{
					break;
				}

				if (NULL != (proxy_status_diff = (zbx_dc_status_diff_proxy_t *)zbx_hashset_search(&diff->proxies,
						&dc_proxy->proxyid)))
				{
					proxy_status_diff->hosts_monitored++;
				}
				break;
			case HOST_STATUS_NOT_MONITORED:
				diff->hosts_not_monitored++;
				if (0 == dc_host->proxyid)
					break;

				if (NULL == (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies,
						&dc_host->proxyid)))
				{
					break;
				}

				if (NULL != (proxy_status_diff = (zbx_dc_status_diff_proxy_t *)zbx_hashset_search(&diff->proxies,
						&dc_proxy->proxyid)))
				{
					proxy_status_diff->hosts_not_monitored++;
				}
				break;
		}

		get_host_statistics(dc_host, &host_diff_local, proxy_status_diff, diff);

		if (dc_host->items_active_normal == host_diff_local.items_active_normal &&
			dc_host->items_active_notsupported == host_diff_local.items_active_notsupported)
		{
			continue;
		}

		zbx_vector_status_diff_host_append(&diff->hosts, host_diff_local);
	}

	get_trigger_statistics(&config->triggers, diff);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove unchanged proxy entries from status update diff            *
 *                                                                            *
 * Parameters: diff - [OUT]                                                   *
 *                                                                            *
 ******************************************************************************/
static void	dc_status_update_remove_unchanged_proxies(zbx_dc_status_diff_t *diff)
{
	zbx_hashset_iter_t		iter;
	zbx_dc_status_diff_proxy_t	*proxy_diff;

	zbx_hashset_iter_reset(&diff->proxies, &iter);

	while (NULL != (proxy_diff = (zbx_dc_status_diff_proxy_t *)zbx_hashset_iter_next(&iter)))
	{
		if (proxy_diff->hosts_monitored_old == proxy_diff->hosts_monitored &&
			proxy_diff->hosts_not_monitored_old == proxy_diff->hosts_not_monitored &&
			proxy_diff->items_active_normal_old == proxy_diff->items_active_normal &&
			proxy_diff->items_active_notsupported_old == proxy_diff->items_active_notsupported &&
			proxy_diff->items_disabled_old == proxy_diff->items_disabled &&
			proxy_diff->required_performance_old == proxy_diff->required_performance)
		{
			zbx_hashset_iter_remove(&iter);
		}
	}

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
	zbx_dc_status_diff_t		diff;
	int				diff_updated;

	dc_status_diff_init(&diff);

	RDLOCK_CACHE_CONFIG_HISTORY;

	diff_updated = dc_status_update_get_diff(&diff);

	UNLOCK_CACHE_CONFIG_HISTORY;

	if (SUCCEED == diff_updated)
	{
		dc_status_update_remove_unchanged_proxies(&diff);

		WRLOCK_CACHE;

		dc_status_update_apply_diff(&diff);

		UNLOCK_CACHE;
	}

	dc_status_diff_destroy(&diff);
}

/******************************************************************************
 *                                                                            *
 * Purpose: return the number of active items                                 *
 *                                                                            *
 * Parameters: hostid - [IN] the host id, pass 0 to specify all hosts         *
 *                                                                            *
 * Return value: the number of active items                                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dc_get_item_count(zbx_uint64_t hostid)
{
	zbx_uint64_t		count;
	const ZBX_DC_HOST	*dc_host;

	dc_status_update();

	RDLOCK_CACHE;

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
 * Purpose: return the number of active unsupported items                     *
 *                                                                            *
 * Parameters: hostid - [IN] the host id, pass 0 to specify all hosts         *
 *                                                                            *
 * Return value: the number of active unsupported items                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dc_get_item_unsupported_count(zbx_uint64_t hostid)
{
	zbx_uint64_t		count;
	const ZBX_DC_HOST	*dc_host;

	dc_status_update();

	RDLOCK_CACHE;

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
 * Purpose: count active triggers                                             *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dc_get_trigger_count(void)
{
	zbx_uint64_t	count;

	dc_status_update();

	RDLOCK_CACHE;
	count = config->status->triggers_enabled_ok + config->status->triggers_enabled_problem;
	UNLOCK_CACHE;

	return count;
}

/******************************************************************************
 *                                                                            *
 * Purpose: count monitored and not monitored hosts                           *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dc_get_host_count(void)
{
	zbx_uint64_t	nhosts;

	dc_status_update();

	RDLOCK_CACHE;
	nhosts = config->status->hosts_monitored;
	UNLOCK_CACHE;

	return nhosts;
}

/******************************************************************************
 *                                                                            *
 * Return value: the required nvps number                                     *
 *                                                                            *
 ******************************************************************************/
double	zbx_dc_get_required_performance(void)
{
	double	nvps;

	dc_status_update();

	RDLOCK_CACHE;
	nvps = config->status->required_performance;
	UNLOCK_CACHE;

	return nvps;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves all internal metrics of the configuration cache         *
 *                                                                            *
 * Parameters: stats - [OUT] the configuration cache statistics               *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_count_stats_all(zbx_config_cache_info_t *stats)
{
	dc_status_update();

	RDLOCK_CACHE;

	stats->hosts = config->status->hosts_monitored;
	stats->items = config->status->items_active_normal + config->status->items_active_notsupported;
	stats->items_unsupported = config->status->items_active_notsupported;
	stats->requiredperformance = config->status->required_performance;

	UNLOCK_CACHE;
}

static void	proxy_counter_ui64_push(zbx_vector_proxy_counter_ptr_t *v, zbx_uint64_t proxyid, zbx_uint64_t counter)
{
	zbx_proxy_counter_t	*proxy_counter;

	proxy_counter = (zbx_proxy_counter_t *)zbx_malloc(NULL, sizeof(zbx_proxy_counter_t));
	proxy_counter->proxyid = proxyid;
	proxy_counter->counter_value.ui64 = counter;
	zbx_vector_proxy_counter_ptr_append(v, proxy_counter);
}

static void	proxy_counter_dbl_push(zbx_vector_proxy_counter_ptr_t *v, zbx_uint64_t proxyid, double counter)
{
	zbx_proxy_counter_t	*proxy_counter;

	proxy_counter = (zbx_proxy_counter_t *)zbx_malloc(NULL, sizeof(zbx_proxy_counter_t));
	proxy_counter->proxyid = proxyid;
	proxy_counter->counter_value.dbl = counter;
	zbx_vector_proxy_counter_ptr_append(v, proxy_counter);
}


void	zbx_dc_get_status(zbx_vector_proxy_counter_ptr_t *hosts_monitored,
		zbx_vector_proxy_counter_ptr_t *hosts_not_monitored,
		zbx_vector_proxy_counter_ptr_t *items_active_normal,
		zbx_vector_proxy_counter_ptr_t *items_active_notsupported,
		zbx_vector_proxy_counter_ptr_t *items_disabled, uint64_t *triggers_enabled_ok,
		zbx_uint64_t *triggers_enabled_problem, zbx_uint64_t *triggers_disabled,
		zbx_vector_proxy_counter_ptr_t *required_performance)
{
	zbx_hashset_iter_t	iter;
	const ZBX_DC_PROXY	*dc_proxy;

	dc_status_update();

	RDLOCK_CACHE;

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
		proxy_counter_ui64_push(hosts_monitored, dc_proxy->proxyid, dc_proxy->hosts_monitored);
		proxy_counter_ui64_push(hosts_not_monitored, dc_proxy->proxyid, dc_proxy->hosts_not_monitored);
		proxy_counter_ui64_push(items_active_normal, dc_proxy->proxyid,
				dc_proxy->items_active_normal);
		proxy_counter_ui64_push(items_active_notsupported, dc_proxy->proxyid,
				dc_proxy->items_active_notsupported);
		proxy_counter_ui64_push(items_disabled, dc_proxy->proxyid, dc_proxy->items_disabled);
		proxy_counter_dbl_push(required_performance, dc_proxy->proxyid, dc_proxy->required_performance);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
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
void	zbx_dc_get_expressions_by_names(zbx_vector_expression_t *expressions, const char * const *names, int names_num)
{
	int			iname;
	const ZBX_DC_EXPRESSION	*expression;
	const ZBX_DC_REGEXP	*regexp;
	ZBX_DC_REGEXP		search_regexp;

	RDLOCK_CACHE;

	for (iname = 0; iname < names_num; iname++)
	{
		search_regexp.name = names[iname];

		if (NULL != (regexp = (const ZBX_DC_REGEXP *)zbx_hashset_search(&config->regexps, &search_regexp)))
		{
			for (int i = 0; i < regexp->expressionids.values_num; i++)
			{
				zbx_uint64_t		expressionid = regexp->expressionids.values[i];
				zbx_expression_t	*rxp;

				if (NULL == (expression = (const ZBX_DC_EXPRESSION *)zbx_hashset_search(
						&config->expressions, &expressionid)))
				{
					continue;
				}

				rxp = (zbx_expression_t *)zbx_malloc(NULL, sizeof(zbx_expression_t));
				rxp->name = zbx_strdup(NULL, regexp->name);
				rxp->expression = zbx_strdup(NULL, expression->expression);
				rxp->exp_delimiter = expression->delimiter;
				rxp->case_sensitive = expression->case_sensitive;
				rxp->expression_type = expression->type;

				zbx_vector_expression_append(expressions, rxp);
			}
		}
	}

	UNLOCK_CACHE;
}

/******************************************************************************
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
void	zbx_dc_get_expressions_by_name(zbx_vector_expression_t *expressions, const char *name)
{
	zbx_dc_get_expressions_by_names(expressions, &name, 1);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Returns time since which data is expected for the given item. We  *
 *          would not mind not having data for the item before that time, but *
 *          since that time we expect data to be coming.                      *
 *                                                                            *
 * Parameters: itemid  - [IN]                                                 *
 *             seconds - [OUT] the time data is expected as a Unix timestamp  *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds)
{
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;
	int			ret = FAIL;

	RDLOCK_CACHE;

	if (NULL == (dc_item = (const ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
		goto unlock;

	if (ITEM_STATUS_ACTIVE != dc_item->status)
		goto unlock;

	if (NULL == (dc_host = (const ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
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
 * Purpose: get host identifiers for the specified list of functions          *
 *                                                                            *
 * Parameters: functionids     - [IN]                                         *
 *             functionids_num - [IN]                                         *
 *             hostids         - [OUT]                                        *
 *                                                                            *
 * Comments: this function must be used only by configuration syncer          *
 *                                                                            *
 ******************************************************************************/
void	dc_get_hostids_by_functionids(const zbx_uint64_t *functionids, int functionids_num,
		zbx_vector_uint64_t *hostids)
{
	const ZBX_DC_FUNCTION	*function;
	const ZBX_DC_ITEM	*item;
	int			i;

	for (i = 0; i < functionids_num; i++)
	{
		if (NULL == (function = (const ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions,
				&functionids[i])))
		{
				continue;
		}

		if (NULL != (item = (const ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &function->itemid)))
			zbx_vector_uint64_append(hostids, item->hostid);
	}

	zbx_vector_uint64_sort(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get function host ids grouped by an object (trigger) id           *
 *                                                                            *
 * Parameters: functionids - [IN]                                             *
 *             hostids     - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_hostids_by_functionids(zbx_vector_uint64_t *functionids, zbx_vector_uint64_t *hostids)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	RDLOCK_CACHE;

	dc_get_hostids_by_functionids(functionids->values, functionids->values_num, hostids);

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): found %d hosts", __func__, hostids->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get hosts for the specified list of functions                     *
 *                                                                            *
 * Parameters: functionids     - [IN]                                         *
 *             functionids_num - [IN]                                         *
 *             hosts           - [OUT]                                        *
 *                                                                            *
 ******************************************************************************/
static void	dc_get_hosts_by_functionids(const zbx_uint64_t *functionids, int functionids_num, zbx_hashset_t *hosts)
{
	const ZBX_DC_FUNCTION	*dc_function;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;
	zbx_dc_host_t		host;
	int			i;

	for (i = 0; i < functionids_num; i++)
	{
		if (NULL == (dc_function = (const ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions,
				&functionids[i])))
		{
			continue;
		}

		if (NULL == (dc_item = (const ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &dc_function->itemid)))
			continue;

		if (NULL == (dc_host = (const ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		DCget_host(&host, dc_host);
		zbx_hashset_insert(hosts, &host, sizeof(host));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get hosts for the specified list of functions                     *
 *                                                                            *
 * Parameters: functionids - [IN]                                             *
 *             hosts       - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_hosts_by_functionids(const zbx_vector_uint64_t *functionids, zbx_hashset_t *hosts)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	RDLOCK_CACHE;

	dc_get_hosts_by_functionids(functionids->values, functionids->values_num, hosts);

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): found %d hosts", __func__, hosts->num_data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get number of enabled internal actions                            *
 *                                                                            *
 * Return value: number of enabled internal actions                           *
 *                                                                            *
 ******************************************************************************/
unsigned int	zbx_dc_get_internal_action_count(void)
{
	unsigned int count;

	RDLOCK_CACHE;

	count = config->internal_actions;

	UNLOCK_CACHE;

	return count;
}

unsigned int	zbx_dc_get_auto_registration_action_count(void)
{
	unsigned int count;

	RDLOCK_CACHE;

	count = config->auto_registration_actions;

	UNLOCK_CACHE;

	return count;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get global configuration data                                     *
 *                                                                            *
 * Parameters: cfg   - [OUT] the global configuration data                    *
 *             flags - [IN] the flags specifying fields to get,               *
 *                          see ZBX_CONFIG_FLAGS_ defines                     *
 *                                                                            *
 * Comments: It's recommended to cleanup 'cfg' structure after use with       *
 *           zbx_config_clean() function even if only simple fields were      *
 *           requested.                                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_config_get(zbx_config_t *cfg, zbx_uint64_t flags)
{
	RDLOCK_CACHE;

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

	if (0 != (flags & ZBX_CONFIG_FLAGS_SNMPTRAP_LOGGING))
		cfg->snmptrap_logging = config->config->snmptrap_logging;

	if (0 != (flags & ZBX_CONFIG_FLAGS_HOUSEKEEPER))
		cfg->hk = config->config->hk;

	if (0 != (flags & ZBX_CONFIG_FLAGS_DB_EXTENSION))
	{
		cfg->db.extension = zbx_strdup(NULL, config->config->db.extension);
		cfg->db.history_compression_status = config->config->db.history_compression_status;
		cfg->db.history_compress_older = config->config->db.history_compress_older;
	}

	if (0 != (flags & ZBX_CONFIG_FLAGS_AUTOREG_TLS_ACCEPT))
		cfg->autoreg_tls_accept = config->config->autoreg_tls_accept;

	if (0 != (flags & ZBX_CONFIG_FLAGS_DEFAULT_TIMEZONE))
		cfg->default_timezone = zbx_strdup(NULL, config->config->default_timezone);

	if (0 != (flags & ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED))
		cfg->auditlog_enabled = config->config->auditlog_enabled;

	if (0 != (flags & ZBX_CONFIG_FLAGS_AUDITLOG_MODE))
		cfg->auditlog_mode = config->config->auditlog_mode;

	UNLOCK_CACHE;

	cfg->flags = flags;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get housekeeping mode for history and trends tables               *
 *                                                                            *
 * Parameters: history_mode - [OUT] history housekeeping mode, can be either  *
 *                                  disabled, enabled or partitioning         *
 *             trends_mode  - [OUT] trends housekeeping mode, can be either   *
 *                                  disabled, enabled or partitioning         *
 *                                                                            *
 ******************************************************************************/
void	zbx_config_get_hk_mode(unsigned char *history_mode, unsigned char *trends_mode)
{
	RDLOCK_CACHE;
	*history_mode = config->config->hk.history_mode;
	*trends_mode = config->config->hk.trends_mode;
	UNLOCK_CACHE;
}

/******************************************************************************
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

	if (0 != (cfg->flags & ZBX_CONFIG_FLAGS_DB_EXTENSION))
		zbx_free(cfg->db.extension);

	if (0 != (cfg->flags & ZBX_CONFIG_FLAGS_DEFAULT_TIMEZONE))
		zbx_free(cfg->default_timezone);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: resets interfaces availability for disabled hosts and hosts          *
 *          without enabled items for the corresponding interface                *
 *                                                                               *
 * Parameters: interfaces - [OUT] changed interface availability data            *
 *                                                                               *
 * Return value: SUCCEED - interface availability was reset for at least one     *
 *                         interface                                             *
 *               FAIL    - no interfaces required availability reset             *
 *                                                                               *
 * Comments: This function resets interface availability in configuration cache. *
 *           The caller must perform corresponding database updates based on     *
 *           returned interface availability reset data. On server the function  *
 *           skips hosts handled by proxies.                                     *
 *                                                                               *
 ********************************************************************************/
int	zbx_dc_reset_interfaces_availability(zbx_vector_availability_ptr_t *interfaces)
{
#define ZBX_INTERFACE_MOVE_TOLERANCE_INTERVAL	(10 * SEC_PER_MIN)
#define ZBX_INTERFACE_VERSION_RESET_INTERVAL	(SEC_PER_HOUR)

	ZBX_DC_HOST			*host;
	ZBX_DC_INTERFACE		*interface;
	zbx_hashset_iter_t		iter;
	zbx_interface_availability_t	*ia = NULL;
	int				now;
	static int			last_version_reset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);

	if (last_version_reset + ZBX_INTERFACE_VERSION_RESET_INTERVAL < now)
		last_version_reset = now;

	WRLOCK_CACHE;

	zbx_hashset_iter_reset(&config->interfaces, &iter);

	while (NULL != (interface = (ZBX_DC_INTERFACE *)zbx_hashset_iter_next(&iter)))
	{
		int	items_num = 0;

		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &interface->hostid)))
			continue;

		if (last_version_reset == now)
		{
			if (interface->version < ZBX_COMPONENT_VERSION(7, 0, 0))
				interface->version = ZBX_COMPONENT_VERSION(7, 0, 0);
		}

		/* On server skip hosts handled by proxies. They are handled directly */
		/* when receiving hosts' availability data from proxies.              */
		/* Unless a host was just (re)assigned to a proxy or the proxy has    */
		/* not updated its status during the maximum proxy heartbeat period.  */
		/* In this case reset all interfaces to unknown status.               */
		if (0 == interface->reset_availability &&
				0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER) && 0 != host->proxyid)
		{
			ZBX_DC_PROXY	*proxy;

			if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &host->proxyid)))
			{
				/* SEC_PER_MIN is a tolerance interval, it was chosen arbitrarily */
				if (ZBX_INTERFACE_MOVE_TOLERANCE_INTERVAL >= now - proxy->lastaccess)
					continue;
			}

			interface->reset_availability = 1;
		}

		if (NULL == ia)
			ia = (zbx_interface_availability_t *)zbx_malloc(NULL, sizeof(zbx_interface_availability_t));

		zbx_interface_availability_init(ia, interface->interfaceid);

		if (0 == interface->reset_availability)
			items_num = interface->items_num;

		if (0 == items_num && ZBX_INTERFACE_AVAILABLE_UNKNOWN != interface->available)
			zbx_agent_availability_init(&ia->agent, ZBX_INTERFACE_AVAILABLE_UNKNOWN, "", 0, 0);

		if (SUCCEED == zbx_interface_availability_is_set(ia))
		{
			if (SUCCEED == DCinterface_set_availability(interface, now, ia))
			{
				zbx_vector_availability_ptr_append(interfaces, ia);
				ia = NULL;
			}
			else
				zbx_interface_availability_clean(ia);
		}

		interface->reset_availability = 0;
	}
	UNLOCK_CACHE;

	zbx_free(ia);

	zbx_vector_availability_ptr_sort(interfaces, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() interfaces:%d", __func__, interfaces->values_num);

	return 0 == interfaces->values_num ? FAIL : SUCCEED;
#undef ZBX_INTERFACE_VERSION_RESET_INTERVAL
#undef ZBX_INTERFACE_MOVE_TOLERANCE_INTERVAL
}

/*******************************************************************************
 *                                                                             *
 * Purpose: gets availability data for interfaces with availability data       *
 *          changed in period from last availability update to the specified   *
 *          timestamp                                                          *
 *                                                                             *
 * Parameters: interfaces - [OUT] changed interfaces availability data         *
 *             ts    - [OUT] the availability diff timestamp                   *
 *                                                                             *
 * Return value: SUCCEED - availability was changed for at least one interface *
 *               FAIL    - no interface availability was changed               *
 *                                                                             *
 *******************************************************************************/
int	zbx_dc_get_interfaces_availability(zbx_vector_availability_ptr_t *interfaces, int *ts)
{
	const ZBX_DC_INTERFACE		*interface;
	zbx_hashset_iter_t		iter;
	zbx_interface_availability_t	*ia = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	RDLOCK_CACHE;

	*ts = time(NULL);

	zbx_hashset_iter_reset(&config->interfaces, &iter);

	while (NULL != (interface = (const ZBX_DC_INTERFACE *)zbx_hashset_iter_next(&iter)))
	{
		if (config->availability_diff_ts <= interface->availability_ts && interface->availability_ts < *ts)
		{
			ia = (zbx_interface_availability_t *)zbx_malloc(NULL, sizeof(zbx_interface_availability_t));
			zbx_interface_availability_init(ia, interface->interfaceid);

			zbx_agent_availability_init(&ia->agent, interface->available, interface->error,
					interface->errors_from, interface->disable_until);

			zbx_vector_availability_ptr_append(interfaces, ia);
		}
	}

	UNLOCK_CACHE;

	zbx_vector_availability_ptr_sort(interfaces, zbx_interface_availability_compare_func);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() interfaces:%d", __func__, interfaces->values_num);

	return 0 == interfaces->values_num ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets availability timestamp to current time for the specified     *
 *          interfaces                                                        *
 *                                                                            *
 * Parameters: interfaceids - [IN] the interfaces identifiers                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_touch_interfaces_availability(const zbx_vector_uint64_t *interfaceids)
{
	ZBX_DC_INTERFACE	*dc_interface;
	int			i, now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() interfaceids:%d", __func__, interfaceids->values_num);

	now = time(NULL);

	WRLOCK_CACHE;

	for (i = 0; i < interfaceids->values_num; i++)
	{
		if (NULL != (dc_interface = zbx_hashset_search(&config->interfaces, &interfaceids->values[i])))
			dc_interface->availability_ts = now;
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
 * Purpose: frees global correlation rule                                     *
 *                                                                            *
 * Parameter: condition - [IN] the condition to free                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_correlation_free(zbx_correlation_t *correlation)
{
	zbx_free(correlation->name);
	zbx_free(correlation->formula);

	zbx_vector_corr_operation_ptr_clear_ext(&correlation->operations, zbx_corr_operation_free);
	zbx_vector_corr_operation_ptr_destroy(&correlation->operations);
	zbx_vector_corr_condition_ptr_destroy(&correlation->conditions);

	zbx_free(correlation);
}

/******************************************************************************
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
 * Purpose: clones cached correlation operation to memory                     *
 *                                                                            *
 * Parameter: operation - [IN] the operation to clone                         *
 *                                                                            *
 * Return value: The cloned correlation operation.                            *
 *                                                                            *
 ******************************************************************************/
static zbx_corr_operation_t	*zbx_dc_corr_operation_dup(const zbx_dc_corr_operation_t *dc_operation)
{
	zbx_corr_operation_t	*operation;

	operation = (zbx_corr_operation_t *)zbx_malloc(NULL, sizeof(zbx_corr_operation_t));
	operation->type = dc_operation->type;

	return operation;
}

/******************************************************************************
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

	if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION == dc_correlation->evaltype || 0 ==
			dc_correlation->conditions.values_num)
	{
		return zbx_strdup(NULL, dc_correlation->formula);
	}

	dc_condition = (const zbx_dc_corr_condition_t *)dc_correlation->conditions.values[0];

	switch (dc_correlation->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_OR:
			op = " or";
			break;
		case ZBX_CONDITION_EVAL_TYPE_AND:
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
	zbx_vector_correlation_ptr_create(&rules->correlations);
	zbx_hashset_create_ext(&rules->conditions, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			(zbx_clean_func_t)corr_condition_clean, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	rules->sync_ts = 0;
}

void	zbx_dc_correlation_rules_clean(zbx_correlation_rules_t *rules)
{
	zbx_vector_correlation_ptr_clear_ext(&rules->correlations, dc_correlation_free);
	zbx_hashset_clear(&rules->conditions);
}

void	zbx_dc_correlation_rules_free(zbx_correlation_rules_t *rules)
{
	zbx_dc_correlation_rules_clean(rules);
	zbx_vector_correlation_ptr_destroy(&rules->correlations);
	zbx_hashset_destroy(&rules->conditions);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets correlation rules from configuration cache                   *
 *                                                                            *
 * Parameter: rules   - [IN/OUT] the correlation rules                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_correlation_rules_get(zbx_correlation_rules_t *rules)
{
	int				i;
	zbx_hashset_iter_t		iter;
	const zbx_dc_correlation_t	*dc_correlation;
	const zbx_dc_corr_condition_t	*dc_condition;
	zbx_correlation_t		*correlation;
	zbx_corr_condition_t		*condition, condition_local;

	RDLOCK_CACHE;

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
	while (NULL != (dc_correlation = (const zbx_dc_correlation_t *)zbx_hashset_iter_next(&iter)))
	{
		correlation = (zbx_correlation_t *)zbx_malloc(NULL, sizeof(zbx_correlation_t));
		correlation->correlationid = dc_correlation->correlationid;
		correlation->evaltype = dc_correlation->evaltype;
		correlation->name = zbx_strdup(NULL, dc_correlation->name);
		correlation->formula = dc_correlation_formula_dup(dc_correlation);
		zbx_vector_corr_condition_ptr_create(&correlation->conditions);
		zbx_vector_corr_operation_ptr_create(&correlation->operations);

		for (i = 0; i < dc_correlation->conditions.values_num; i++)
		{
			dc_condition = (const zbx_dc_corr_condition_t *)dc_correlation->conditions.values[i];
			condition_local.corr_conditionid = dc_condition->corr_conditionid;
			condition = (zbx_corr_condition_t *)zbx_hashset_insert(&rules->conditions, &condition_local,
					sizeof(condition_local));
			dc_corr_condition_copy(dc_condition, condition);
			zbx_vector_corr_condition_ptr_append(&correlation->conditions, condition);
		}

		for (i = 0; i < dc_correlation->operations.values_num; i++)
		{
			zbx_vector_corr_operation_ptr_append(&correlation->operations, zbx_dc_corr_operation_dup(
					(const zbx_dc_corr_operation_t *)dc_correlation->operations.values[i]));
		}

		zbx_vector_correlation_ptr_append(&rules->correlations, correlation);
	}

	rules->sync_ts = config->sync_ts;

	UNLOCK_CACHE;

	zbx_vector_correlation_ptr_sort(&rules->correlations, zbx_correlation_compare_func);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cache nested group identifiers                                    *
 *                                                                            *
 ******************************************************************************/
void	dc_hostgroup_cache_nested_groupids(zbx_dc_hostgroup_t *parent_group)
{
	zbx_dc_hostgroup_t	*group;

	if (0 == (parent_group->flags & ZBX_DC_HOSTGROUP_FLAGS_NESTED_GROUPIDS))
	{
		int	index, len;

		zbx_vector_uint64_create_ext(&parent_group->nested_groupids, __config_shmem_malloc_func,
				__config_shmem_realloc_func, __config_shmem_free_func);

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
}

/******************************************************************************
 *                                                                            *
 * Purpose: pre-caches nested groups for groups used in running maintenances  *
 *                                                                            *
 ******************************************************************************/
static void	dc_maintenance_precache_nested_groups(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_maintenance_t	*maintenance;
	zbx_vector_uint64_t	groupids;
	int			i;
	zbx_dc_hostgroup_t	*group;

	if (0 == config->maintenances.num_data)
		return;

	zbx_vector_uint64_create(&groupids);
	zbx_hashset_iter_reset(&config->maintenances, &iter);
	while (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_MAINTENANCE_RUNNING != maintenance->state)
			continue;

		zbx_vector_uint64_append_array(&groupids, maintenance->groupids.values,
				maintenance->groupids.values_num);
	}

	zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < groupids.values_num; i++)
	{
		if (NULL != (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups,
				&groupids.values[i])))
		{
			dc_hostgroup_cache_nested_groupids(group);
		}
	}

	zbx_vector_uint64_destroy(&groupids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets nested group ids for the specified host group                *
 *          (including the target group id)                                   *
 *                                                                            *
 * Parameter: groupid         - [IN] the parent group identifier              *
 *            nested_groupids - [OUT] the nested + parent group ids           *
 *                                                                            *
 ******************************************************************************/
void	dc_get_nested_hostgroupids(zbx_uint64_t groupid, zbx_vector_uint64_t *nested_groupids)
{
	zbx_dc_hostgroup_t	*parent_group;

	zbx_vector_uint64_append(nested_groupids, groupid);

	/* The target group id will not be found in the configuration cache if target group was removed */
	/* between call to this function and the configuration cache look-up below. The target group id */
	/* is nevertheless returned so that the SELECT statements of the callers work even if no group  */
	/* was found.                                                                                   */

	if (NULL != (parent_group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups, &groupid)))
	{
		dc_hostgroup_cache_nested_groupids(parent_group);

		if (0 != parent_group->nested_groupids.values_num)
		{
			zbx_vector_uint64_append_array(nested_groupids, parent_group->nested_groupids.values,
					parent_group->nested_groupids.values_num);
		}
	}
}

/******************************************************************************
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

	WRLOCK_CACHE;

	for (i = 0; i < groupids_num; i++)
		dc_get_nested_hostgroupids(groupids[i], nested_groupids);

	UNLOCK_CACHE;

	zbx_vector_uint64_sort(nested_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(nested_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets hostids belonging to the group and its nested groups         *
 *                                                                            *
 * Parameter: name    - [IN] the group name                                   *
 *            hostids - [OUT] the hostids                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_hostids_by_group_name(const char *name, zbx_vector_uint64_t *hostids)
{
	int			i;
	zbx_vector_uint64_t	groupids;
	zbx_dc_hostgroup_t	group_local, *group;

	zbx_vector_uint64_create(&groupids);

	group_local.name = name;

	WRLOCK_CACHE;

	if (FAIL != (i = zbx_vector_ptr_bsearch(&config->hostgroups_name, &group_local, dc_compare_hgroups)))
	{
		group = (zbx_dc_hostgroup_t *)config->hostgroups_name.values[i];
		dc_get_nested_hostgroupids(group->groupid, &groupids);
	}

	UNLOCK_CACHE;

	zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	RDLOCK_CACHE;

	for (i = 0; i < groupids.values_num; i++)
	{
		zbx_hashset_iter_t	iter;
		zbx_uint64_t		*phostid;

		if (NULL == (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups,
				&groupids.values[i])))
		{
			continue;
		}

		zbx_hashset_iter_reset(&group->hostids, &iter);

		while (NULL != (phostid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
			zbx_vector_uint64_append(hostids, *phostid);
	}

	UNLOCK_CACHE;

	zbx_vector_uint64_destroy(&groupids);

	zbx_vector_uint64_sort(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
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
int	zbx_dc_get_active_proxy_by_name(const char *name, zbx_dc_proxy_t *proxy, char **error)
{
	int			ret = FAIL;
	const ZBX_DC_PROXY	*dc_proxy;

	RDLOCK_CACHE;

	if (NULL == (dc_proxy = DCfind_proxy(name)))
	{
		*error = zbx_dsprintf(*error, "proxy \"%s\" not found", name);
		goto out;
	}

	if (PROXY_OPERATING_MODE_ACTIVE != dc_proxy->mode)
	{
		*error = zbx_dsprintf(*error, "proxy \"%s\" is configured for passive mode", name);
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
 * Purpose: find proxyid and type for given proxy name                        *
 *                                                                            *
 * Parameters:                                                                *
 *     name    - [IN] the proxy name                                          *
 *     proxyid - [OUT] the proxyid                                            *
 *     type    - [OUT] the type of a proxy                                    *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - id/type were retrieved successfully                          *
 *     FAIL    - failed to find proxy in cache                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_proxyid_by_name(const char *name, zbx_uint64_t *proxyid, unsigned char *type)
{
	int			ret = FAIL;
	const ZBX_DC_PROXY	*dc_proxy;

	RDLOCK_CACHE;

	if (NULL != (dc_proxy = DCfind_proxy(name)))
	{
		if (NULL != type)
			*type = dc_proxy->mode;

		*proxyid = dc_proxy->proxyid;

		ret = SUCCEED;
	}

	UNLOCK_CACHE;

	return ret;
}

int	zbx_dc_update_passive_proxy_nextcheck(zbx_uint64_t proxyid)
{
	int		ret = SUCCEED;
	ZBX_DC_PROXY	*dc_proxy;

	WRLOCK_CACHE;

	if (NULL == (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxyid)))
		ret = FAIL;
	else
		dc_proxy->proxy_config_nextcheck = time(NULL);

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve proxyids for all cached proxies                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_all_proxies(zbx_vector_cached_proxy_ptr_t *proxies)
{
	zbx_dc_proxy_name_t	*proxy;
	zbx_hashset_iter_t	iter;

	RDLOCK_CACHE;

	zbx_vector_cached_proxy_ptr_reserve(proxies, (size_t)config->proxies_p.num_data);
	zbx_hashset_iter_reset(&config->proxies_p, &iter);

	while (NULL != (proxy = (zbx_dc_proxy_name_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_cached_proxy_t	*cached_proxy;

		cached_proxy = (zbx_cached_proxy_t *)zbx_malloc(NULL, sizeof(zbx_cached_proxy_t));

		cached_proxy->name = zbx_strdup(NULL, proxy->proxy_ptr->name);
		cached_proxy->proxyid = proxy->proxy_ptr->proxyid;
		cached_proxy->mode = proxy->proxy_ptr->mode;

		zbx_vector_cached_proxy_ptr_append(proxies, cached_proxy);
	}

	UNLOCK_CACHE;
}

void	zbx_cached_proxy_free(zbx_cached_proxy_t *proxy)
{
	zbx_free(proxy->name);
	zbx_free(proxy);
}

int	zbx_dc_get_proxy_name_type_by_id(zbx_uint64_t proxyid, int *status, char **name)
{
	int		ret = SUCCEED;
	ZBX_DC_PROXY	*dc_proxy;

	RDLOCK_CACHE;

	if (NULL == (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxyid)))
		ret = FAIL;
	else
	{
		*status = dc_proxy->mode;
		*name = zbx_strdup(NULL, dc_proxy->name);
	}

	UNLOCK_CACHE;

	return ret;
}


/******************************************************************************
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
int	zbx_dc_get_host_interfaces(zbx_uint64_t hostid, zbx_dc_interface2_t **interfaces, int *n)
{
	const ZBX_DC_HOST	*host;
	int			i, ret = FAIL;

	if (0 == hostid)
		return FAIL;

	RDLOCK_CACHE;

	/* find host entry in 'config->hosts' hashset */

	if (NULL == (host = (const ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &hostid)))
		goto unlock;

	/* allocate memory for results */

	if (0 < (*n = host->interfaces_v.values_num))
		*interfaces = (zbx_dc_interface2_t *)zbx_malloc(NULL, sizeof(zbx_dc_interface2_t) * (size_t)*n);

	/* copy data about all host interfaces */

	for (i = 0; i < *n; i++)
	{
		const ZBX_DC_INTERFACE	*src = (const ZBX_DC_INTERFACE *)host->interfaces_v.values[i];
		zbx_dc_interface2_t	*dst = *interfaces + i;

		dst->interfaceid = src->interfaceid;
		dst->type = src->type;
		dst->main = src->main;
		dst->useip = src->useip;
		zbx_strscpy(dst->ip_orig, src->ip);
		zbx_strscpy(dst->dns_orig, src->dns);
		zbx_strscpy(dst->port_orig, src->port);
		dst->addr = (1 == src->useip ? dst->ip_orig : dst->dns_orig);

		if (INTERFACE_TYPE_SNMP == dst->type)
		{
			ZBX_DC_SNMPINTERFACE *snmp;

			if (NULL == (snmp = (ZBX_DC_SNMPINTERFACE *)zbx_hashset_search(&config->interfaces_snmp,
					&dst->interfaceid)))
			{
				zbx_free(*interfaces);
				goto unlock;
			}

			dst->bulk = snmp->bulk;
			dst->snmp_version= snmp->version;
		}
	}

	ret = SUCCEED;
unlock:
	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply item state, error, mtime, lastlogsize changes to            *
 *          configuration cache                                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_items_apply_changes(const zbx_vector_item_diff_ptr_t *item_diff)
{
	int			i;
	const zbx_item_diff_t	*diff;
	ZBX_DC_ITEM		*dc_item;

	if (0 == item_diff->values_num)
		return;

	WRLOCK_CACHE;

	for (i = 0; i < item_diff->values_num; i++)
	{
		diff = item_diff->values[i];

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &diff->itemid)))
			continue;

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE & diff->flags))
			dc_item->lastlogsize = diff->lastlogsize;

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME & diff->flags))
			dc_item->mtime = diff->mtime;

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR & diff->flags))
			dc_strpool_replace(1, &dc_item->error, diff->error);

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE & diff->flags))
			dc_item->state = diff->state;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update automatic inventory in configuration cache                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_update_inventory_values(const zbx_vector_inventory_value_ptr_t *inventory_values)
{
	ZBX_DC_HOST_INVENTORY	*host_inventory = NULL;
	int			i;

	WRLOCK_CACHE;

	for (i = 0; i < inventory_values->values_num; i++)
	{
		const zbx_inventory_value_t	*inventory_value = inventory_values->values[i];
		const char			**value;

		if (NULL == host_inventory || inventory_value->hostid != host_inventory->hostid)
		{
			host_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories_auto,
					&inventory_value->hostid);

			if (NULL == host_inventory)
				continue;
		}

		value = &host_inventory->values[inventory_value->idx];

		dc_strpool_replace((NULL != *value ? 1 : 0), value, inventory_value->value);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find inventory value in automatically populated cache, if not     *
 *          found then look in main inventory cache                           *
 *                                                                            *
 * Comments: This function must be called inside configuration cache read     *
 *           (or write) lock.                                                 *
 *                                                                            *
 ******************************************************************************/
static int	dc_get_host_inventory_value_by_hostid(zbx_uint64_t hostid, char **replace_to, int value_idx)
{
	const ZBX_DC_HOST_INVENTORY	*dc_inventory;

	if (NULL != (dc_inventory = (const ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories_auto,
			&hostid)) && NULL != dc_inventory->values[value_idx])
	{
		*replace_to = zbx_strdup(*replace_to, dc_inventory->values[value_idx]);
		return SUCCEED;
	}

	if (NULL != (dc_inventory = (const ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories,
			&hostid)))
	{
		*replace_to = zbx_strdup(*replace_to, dc_inventory->values[value_idx]);
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find inventory value in automatically populated cache, if not     *
 *          found then look in main inventory cache                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_host_inventory_value_by_itemid(zbx_uint64_t itemid, char **replace_to, int value_idx)
{
	const ZBX_DC_ITEM	*dc_item;
	int			ret = FAIL;

	RDLOCK_CACHE;

	if (NULL != (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
		ret = dc_get_host_inventory_value_by_hostid(dc_item->hostid, replace_to, value_idx);

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find inventory value in automatically populated cache, if not     *
 *          found then look in main inventory cache                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_host_inventory_value_by_hostid(zbx_uint64_t hostid, char **replace_to, int value_idx)
{
	int	ret;

	RDLOCK_CACHE;

	ret = dc_get_host_inventory_value_by_hostid(hostid, replace_to, value_idx);

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
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
void	zbx_dc_get_trigger_dependencies(const zbx_vector_uint64_t *triggerids, zbx_vector_trigger_dep_ptr_t *deps)
{
	int				i, ret;
	const ZBX_DC_TRIGGER_DEPLIST	*trigdep;
	zbx_vector_uint64_t		masterids;
	zbx_trigger_dep_t		*dep;

	zbx_vector_uint64_create(&masterids);
	zbx_vector_uint64_reserve(&masterids, 64);

	RDLOCK_CACHE;

	for (i = 0; i < triggerids->values_num; i++)
	{
		if (NULL == (trigdep = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_search(&config->trigdeps,
				&triggerids->values[i])))
		{
			continue;
		}

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

			zbx_vector_trigger_dep_ptr_append(deps, dep);
		}

		zbx_vector_uint64_clear(&masterids);
	}

	UNLOCK_CACHE;

	zbx_vector_uint64_destroy(&masterids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reschedules items that are processed by the target daemon         *
 *                                                                            *
 * Parameter: itemids       - [IN]  the item identifiers                      *
 *            nextcheck     - [IN]  the scheduled time                        *
 *            proxyids      - [OUT] the proxyids of the given itemids         *
 *                                  (optional, can be NULL)                   *
 *                                                                            *
 * Comments: On server this function reschedules items monitored by server.   *
 *           On proxy only items monitored by the proxy is accessible, so     *
 *           all items can be safely rescheduled.                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_reschedule_items(const zbx_vector_uint64_t *itemids, time_t nextcheck, zbx_uint64_t *proxyids)
{
	int		i;
	ZBX_DC_ITEM	*dc_item;
	ZBX_DC_HOST	*dc_host;
	zbx_uint64_t	proxyid;

	WRLOCK_CACHE;

	for (i = 0; i < itemids->values_num; i++)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids->values[i])) ||
				NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot perform check now for itemid [" ZBX_FS_UI64 "]"
					": item is not in cache", itemids->values[i]);

			proxyid = 0;
		}
		else if (ZBX_JAN_2038 == dc_item->nextcheck)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot perform check now for item \"%s\" on host \"%s\""
					": item configuration error", dc_item->key, dc_host->host);

			proxyid = 0;
		}
		else if (HOST_MONITORED_BY_SERVER == dc_host->monitored_by ||
				SUCCEED == zbx_is_item_processed_by_server(dc_item->type, dc_item->key))
		{
			dc_requeue_item_at(dc_item, dc_host, nextcheck);
			proxyid = 0;
		}
		else
			proxyid = dc_host->proxyid;

		if (NULL != proxyids)
			proxyids[i] = proxyid;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: stop suppress mode of the nodata() trigger                        *
 *                                                                            *
 * Parameter: subscriptions - [IN] the array of trigger id and time of values *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_proxy_update_nodata(zbx_vector_uint64_pair_t *subscriptions)
{
	ZBX_DC_PROXY		*proxy = NULL;
	int			i;
	zbx_uint64_pair_t	p;

	WRLOCK_CACHE;

	for (i = 0; i < subscriptions->values_num; i++)
	{
		p = subscriptions->values[i];

		if ((NULL == proxy || p.first != proxy->proxyid) &&
				NULL == (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &p.first)))
		{
			continue;
		}

		if (0 == (proxy->nodata_win.flags & ZBX_PROXY_SUPPRESS_ACTIVE))
			continue;

		if (0 != (proxy->nodata_win.flags & ZBX_PROXY_SUPPRESS_MORE) &&
				(int)p.second > proxy->nodata_win.period_end)
		{
			continue;
		}

		proxy->nodata_win.values_num --;

		if (0 < proxy->nodata_win.values_num || 0 != (proxy->nodata_win.flags & ZBX_PROXY_SUPPRESS_MORE))
			continue;

		proxy->nodata_win.flags = ZBX_PROXY_SUPPRESS_DISABLE;
		proxy->nodata_win.period_end = 0;
		proxy->nodata_win.values_num = 0;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates changed proxy data in configuration cache and updates     *
 *          diff flags to reflect the updated data                            *
 *                                                                            *
 * Parameter: diff - [IN/OUT] the properties to update                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_update_proxy(zbx_proxy_diff_t *diff)
{
	ZBX_DC_PROXY	*proxy;
	int		lastaccess, version, notify = 0;

	WRLOCK_CACHE;

	if (diff->lastaccess < config->proxy_lastaccess_ts)
		diff->lastaccess = config->proxy_lastaccess_ts;

	if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &diff->hostid)))
	{
		if (0 != (diff->flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTACCESS))
		{
			int	lost = 0;	/* communication lost */

			if (0 != (diff->flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_CONFIG))
			{
				int	delay = diff->lastaccess - proxy->lastaccess;

				if (NET_DELAY_MAX < delay)
					lost = 1;
			}

			if (0 == lost && proxy->lastaccess != diff->lastaccess)
			{
				proxy->lastaccess = diff->lastaccess;
				notify = 1;
			}

			/* proxy last access in database is updated separately in  */
			/* every ZBX_PROXY_LASTACCESS_UPDATE_FREQUENCY seconds     */
			diff->flags &= (~ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTACCESS);
		}

		if (0 != (diff->flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION))
		{
			if (0 != strcmp(proxy->version_str, diff->version_str))
				dc_strpool_replace(1, &proxy->version_str, diff->version_str);

			if (proxy->version_int != diff->version_int)
			{
				proxy->version_int = diff->version_int;
				proxy->compatibility = diff->compatibility;
				notify = 1;
			}
			else
				diff->flags &= (~ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION);
		}

		if (0 != (diff->flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTERROR))
		{
			proxy->last_version_error_time = diff->last_version_error_time;
			diff->flags &= (~ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTERROR);
		}

		if (0 != (diff->flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_PROXYDELAY))
		{
			proxy->proxy_delay = diff->proxy_delay;
			diff->flags &= (~ZBX_FLAGS_PROXY_DIFF_UPDATE_PROXYDELAY);
		}

		if (0 != (diff->flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_SUPPRESS_WIN))
		{
			zbx_proxy_suppress_t	*ps_win = &proxy->nodata_win, *ds_win = &diff->nodata_win;

			if ((ps_win->flags & ZBX_PROXY_SUPPRESS_ACTIVE) != (ds_win->flags & ZBX_PROXY_SUPPRESS_ACTIVE))
			{
				ps_win->period_end = ds_win->period_end;
			}

			ps_win->flags = ds_win->flags;

			if (0 > ps_win->values_num)	/* some new values were processed faster than old */
				ps_win->values_num = 0;	/* we will suppress more                          */

			ps_win->values_num += ds_win->values_num;
			diff->flags &= (~ZBX_FLAGS_PROXY_DIFF_UPDATE_SUPPRESS_WIN);
		}

		if (0 != notify)
		{
			lastaccess = proxy->lastaccess;
			version = proxy->version_int;
		}
	}

	UNLOCK_CACHE;

	if (0 != notify)
		zbx_pg_update_proxy_rtdata(diff->hostid, lastaccess, version);
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns proxy lastaccess changes since last lastaccess request    *
 *                                                                            *
 * Parameter: lastaccess - [OUT] last access updates for proxies that need    *
 *                               to be synced with database, sorted by        *
 *                               hostid                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_proxy_lastaccess(zbx_vector_uint64_pair_t *lastaccess)
{
	ZBX_DC_PROXY	*proxy;
	time_t		now;

	if (ZBX_PROXY_LASTACCESS_UPDATE_FREQUENCY < (now = time(NULL)) - config->proxy_lastaccess_ts)
	{
		zbx_hashset_iter_t	iter;

		WRLOCK_CACHE;

		zbx_hashset_iter_reset(&config->proxies, &iter);

		while (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_iter_next(&iter)))
		{
			if (proxy->lastaccess >= config->proxy_lastaccess_ts)
			{
				zbx_uint64_pair_t	pair = {proxy->proxyid, proxy->lastaccess};

				zbx_vector_uint64_pair_append(lastaccess, pair);
			}
		}

		config->proxy_lastaccess_ts = now;

		UNLOCK_CACHE;

		zbx_vector_uint64_pair_sort(lastaccess, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns session token                                             *
 *                                                                            *
 * Return value: pointer to session token (NULL for server).                  *
 *                                                                            *
 * Comments: The session token is generated during configuration cache        *
 *           initialization and is not changed later. Therefore no locking    *
 *           is required.                                                     *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_dc_get_session_token(void)
{
	return config->session_token;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return session, create a new session if none found                *
 *                                                                            *
 * Parameter: hostid - [IN] the host (proxy) identifier                       *
 *            token  - [IN] the session token (not NULL)                      *
 *                                                                            *
 * Return value: pointer to data session.                                     *
 *                                                                            *
 * Comments: The last_valueid property of the returned session object can be  *
 *           updated directly without locking cache because only one data     *
 *           session is updated at the same time and after retrieving the     *
 *           session object will not be deleted for 24 hours.                 *
 *                                                                            *
 ******************************************************************************/
zbx_session_t	*zbx_dc_get_or_create_session(zbx_uint64_t hostid, const char *token,
		zbx_session_type_t session_type)
{
	zbx_session_t	*session, session_local;
	time_t		now;

	now = time(NULL);
	session_local.hostid = hostid;
	session_local.token = token;

	RDLOCK_CACHE;
	session = (zbx_session_t *)zbx_hashset_search(&config->sessions[session_type], &session_local);
	UNLOCK_CACHE;

	if (NULL == session)
	{
		session_local.last_id = 0;
		session_local.lastaccess = now;

		WRLOCK_CACHE;
		session_local.token = dc_strdup(token);
		session = (zbx_session_t *)zbx_hashset_insert(&config->sessions[session_type], &session_local,
				sizeof(session_local));
		UNLOCK_CACHE;
	}
	else
		session->lastaccess = now;

	return session;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update session revision/lastaccess in cache or create new session *
 *          if necessary                                                      *
 *                                                                            *
 * Parameter: hostid - [IN] the host (proxy) identifier                       *
 *            token  - [IN] the session token (not NULL)                      *
 *            session_config_revision - [IN] the session configuration        *
 *                          revision                                          *
 *            dc_revision - [OUT] - the cached configuration revision         *
 *                                                                            *
 * Return value: The number of created sessions                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_register_config_session(zbx_uint64_t hostid, const char *token, zbx_uint64_t session_config_revision,
		zbx_dc_revision_t *dc_revision)
{
	zbx_session_t	*session, session_local;
	time_t		now;

	now = time(NULL);
	session_local.hostid = hostid;
	session_local.token = token;

	RDLOCK_CACHE;
	if (NULL != (session = (zbx_session_t *)zbx_hashset_search(&config->sessions[ZBX_SESSION_TYPE_CONFIG],
			&session_local)))
	{
		/* one session cannot be updated at the same time by different processes,            */
		/* so updating its properties without reallocating memory can be done with read lock */
		session->last_id = session_config_revision;
		session->lastaccess = now;
	}
	*dc_revision = config->revision;
	UNLOCK_CACHE;

	if (NULL != session)
		return 0;

	session_local.last_id = session_config_revision;
	session_local.lastaccess = now;

	WRLOCK_CACHE;
	session_local.token = dc_strdup(token);
	zbx_hashset_insert(&config->sessions[ZBX_SESSION_TYPE_CONFIG], &session_local, sizeof(session_local));
	UNLOCK_CACHE;

	return 1;	/* a session was created */
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes data sessions not accessed for 25 hours                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_cleanup_sessions(void)
{
	zbx_session_t		*session;
	zbx_hashset_iter_t	iter;
	time_t			now;
	int			i;

	now = time(NULL);

	WRLOCK_CACHE;

	for (i = 0; i < ZBX_SESSION_TYPE_COUNT; i++)
	{
		zbx_hashset_iter_reset(&config->sessions[i], &iter);
		while (NULL != (session = (zbx_session_t *)zbx_hashset_iter_next(&iter)))
		{
			/* should be more than MAX_ACTIVE_CHECKS_REFRESH_FREQUENCY */
			if (session->lastaccess + SEC_PER_DAY + SEC_PER_HOUR <= now)
			{
				__config_shmem_free_func((char *)session->token);
				zbx_hashset_iter_remove(&iter);
			}
		}
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes autoreg hosts not accessed for 25 hours                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_cleanup_autoreg_host(void)
{
	ZBX_DC_AUTOREG_HOST	*autoreg_host;
	zbx_hashset_iter_t	iter;
	time_t			now;

	now = time(NULL);

	WRLOCK_CACHE;

	zbx_hashset_iter_reset(&config->autoreg_hosts, &iter);
	while (NULL != (autoreg_host = (ZBX_DC_AUTOREG_HOST *)zbx_hashset_iter_next(&iter)))
	{
		/* should be more than MAX_ACTIVE_CHECKS_REFRESH_FREQUENCY */
		if (autoreg_host->timestamp + SEC_PER_DAY + SEC_PER_HOUR <= now)
		{
			autoreg_host_free_data(autoreg_host);
			zbx_hashset_remove_direct(&config->autoreg_hosts, autoreg_host);
		}
	}

	UNLOCK_CACHE;
}

static void	zbx_gather_item_tags(ZBX_DC_ITEM *item, zbx_vector_item_tag_t *item_tags)
{
	for (int i = 0; i < item->tags.values_num; i++)
	{
		zbx_dc_item_tag_t	*dc_tag = &item->tags.values[i];
		zbx_item_tag_t		*tag = (zbx_item_tag_t *) zbx_malloc(NULL, sizeof(zbx_item_tag_t));

		tag->tag.tag = zbx_strdup(NULL, dc_tag->tag);
		tag->tag.value = zbx_strdup(NULL, dc_tag->value);

		zbx_vector_item_tag_append(item_tags, tag);
	}
}

static void	zbx_gather_tags_from_host(zbx_uint64_t hostid, zbx_vector_item_tag_t *item_tags)
{
	zbx_dc_host_tag_index_t 	*dc_tag_index;

	if (NULL != (dc_tag_index = zbx_hashset_search(&config->host_tags_index, &hostid)))
	{
		for (int i = 0; i < dc_tag_index->tags.values_num; i++)
		{
			zbx_dc_host_tag_t	*dc_tag = (zbx_dc_host_tag_t *)dc_tag_index->tags.values[i];
			zbx_item_tag_t		*tag = (zbx_item_tag_t *) zbx_malloc(NULL, sizeof(zbx_item_tag_t));

			tag->tag.tag = zbx_strdup(NULL, dc_tag->tag);
			tag->tag.value = zbx_strdup(NULL, dc_tag->value);

			zbx_vector_item_tag_append(item_tags, tag);
		}
	}
}

static void	zbx_gather_tags_from_template_chain(zbx_uint64_t itemid, zbx_vector_item_tag_t *item_tags)
{
	ZBX_DC_TEMPLATE_ITEM	*item;

	if (NULL != (item = (ZBX_DC_TEMPLATE_ITEM *)zbx_hashset_search(&config->template_items, &itemid)))
	{
		zbx_gather_tags_from_host(item->hostid, item_tags);

		if (0 != item->templateid)
			zbx_gather_tags_from_template_chain(item->templateid, item_tags);
	}
}

void	zbx_get_item_tags(zbx_uint64_t itemid, zbx_vector_item_tag_t *item_tags)
{
	ZBX_DC_ITEM		*item;
	zbx_item_tag_t		*tag;
	int			n;

	if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemid)))
		return;

	n = item_tags->values_num;

	zbx_gather_item_tags(item, item_tags);

	zbx_gather_tags_from_host(item->hostid, item_tags);

	if (0 != item->templateid)
		zbx_gather_tags_from_template_chain(item->templateid, item_tags);

	/* check for discovered item */
	if (ZBX_FLAG_DISCOVERY_CREATED == item->flags)
	{
		ZBX_DC_ITEM_DISCOVERY	*item_discovery;

		if (NULL != (item_discovery = (ZBX_DC_ITEM_DISCOVERY *)zbx_hashset_search(&config->item_discovery,
				&itemid)))
		{
			ZBX_DC_TEMPLATE_ITEM	*prototype_item;

			if (NULL != (prototype_item = (ZBX_DC_TEMPLATE_ITEM *)zbx_hashset_search(
					&config->template_items, &item_discovery->parent_itemid)))
			{
				if (0 != prototype_item->templateid)
					zbx_gather_tags_from_template_chain(prototype_item->templateid, item_tags);
			}
		}
	}

	/* assign hostid and itemid values to newly gathered tags */
	for (int i = n; i < item_tags->values_num; i++)
	{
		tag = (zbx_item_tag_t *)item_tags->values[i];
		tag->hostid = item->hostid;
		tag->itemid = item->itemid;
	}
}

void	zbx_dc_get_item_tags(zbx_uint64_t itemid, zbx_vector_item_tag_t *item_tags)
{
	RDLOCK_CACHE;

	zbx_get_item_tags(itemid, item_tags);

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves proxy suppress window data from the cache               *
 *                                                                            *
 * Parameters: hostid     - [IN] proxy host id                                *
 *             nodata_win - [OUT] suppress window data                        *
 *             lastaccess - [OUT] proxy last access time                      *
 *                                                                            *
 * Return value: SUCCEED - the data is retrieved                              *
 *               FAIL    - the data cannot be retrieved, proxy not found in   *
 *                         configuration cache                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_proxy_nodata_win(zbx_uint64_t hostid, zbx_proxy_suppress_t *nodata_win, int *lastaccess)
{
	const ZBX_DC_PROXY	*dc_proxy;
	int			ret;

	RDLOCK_CACHE;

	if (NULL != (dc_proxy = (const ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &hostid)))
	{
		const zbx_proxy_suppress_t	*proxy_nodata_win = &dc_proxy->nodata_win;

		nodata_win->period_end = proxy_nodata_win->period_end;
		nodata_win->values_num = proxy_nodata_win->values_num;
		nodata_win->flags = proxy_nodata_win->flags;
		*lastaccess = dc_proxy->lastaccess;
		ret = SUCCEED;
	}
	else
		ret = FAIL;

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves proxy delay from the cache                              *
 *                                                                            *
 * Parameters: name  - [IN] proxy host name                                   *
 *             delay - [OUT] proxy delay                                      *
 *             error - [OUT]                                                  *
 *                                                                            *
 * Return value: SUCCEED - proxy delay is retrieved                           *
 *               FAIL    - proxy delay cannot be retrieved                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_proxy_delay_by_name(const char *name, int *delay, char **error)
{
	const ZBX_DC_PROXY	*dc_proxy;
	int			ret;

	RDLOCK_CACHE;

	if (NULL == (dc_proxy = DCfind_proxy(name)))
	{
		*error = zbx_dsprintf(*error, "Proxy \"%s\" not found in configuration cache.", name);
		ret = FAIL;
	}
	else
	{
		*delay = dc_proxy->proxy_delay;
		ret = SUCCEED;
	}

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves proxy lastaccess from the cache by name                 *
 *                                                                            *
 * Parameters: name       - [IN] proxy host name                              *
 *             lastaccess - [OUT] proxy lastaccess                            *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - proxy lastaccess is retrieved                      *
 *               FAIL    - proxy lastaccess cannot be retrieved               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_proxy_lastaccess_by_name(const char *name, time_t *lastaccess, char **error)
{
	const ZBX_DC_PROXY	*dc_proxy;
	int			ret;

	RDLOCK_CACHE;

	if (NULL == (dc_proxy = DCfind_proxy(name)))
	{
		*error = zbx_dsprintf(*error, "Proxy \"%s\" not found in configuration cache.", name);
		ret = FAIL;
	}
	else
	{
		*lastaccess = dc_proxy->lastaccess;
		ret = SUCCEED;
	}

	UNLOCK_CACHE;

	return ret;
}

void	zbx_dc_get_proxy_timeouts(zbx_uint64_t proxy_hostid, zbx_dc_item_type_timeouts_t *timeouts)
{
	ZBX_DC_PROXY			*proxy;
	zbx_config_item_type_timeouts_t	*timeouts_src;

	RDLOCK_CACHE;

	if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxy_hostid)))
	{
		timeouts_src = (0 == proxy->custom_timeouts ? &config->config->item_timeouts : &proxy->item_timeouts);

		zbx_strscpy(timeouts->agent, timeouts_src->agent);
		zbx_strscpy(timeouts->simple, timeouts_src->simple);
		zbx_strscpy(timeouts->snmp, timeouts_src->snmp);
		zbx_strscpy(timeouts->external, timeouts_src->external);
		zbx_strscpy(timeouts->odbc, timeouts_src->odbc);
		zbx_strscpy(timeouts->http, timeouts_src->http);
		zbx_strscpy(timeouts->ssh, timeouts_src->ssh);
		zbx_strscpy(timeouts->telnet, timeouts_src->telnet);
		zbx_strscpy(timeouts->script, timeouts_src->script);
		zbx_strscpy(timeouts->browser, timeouts_src->browser);
	}

	UNLOCK_CACHE;
}

static void	proxy_discovery_add_item_type_timeout(const char *key, struct zbx_json *j, const char *raw_timeout,
		zbx_uint64_t proxyid)
{
	char	*expanded_value;
	int	tm_seconds = 0;

	expanded_value = dc_expand_user_and_func_macros_dyn(raw_timeout, &proxyid, 1, ZBX_MACRO_ENV_NONSECURE);

	if (SUCCEED == zbx_is_time_suffix(expanded_value, &tm_seconds, ZBX_LENGTH_UNLIMITED))
	{
		zbx_json_adduint64(j, key, tm_seconds);
	}
	else
		zbx_json_addstring(j, key, expanded_value, ZBX_JSON_TYPE_STRING);

	zbx_free(expanded_value);
}

static void	proxy_discovery_get_timeouts(const ZBX_DC_PROXY *proxy, struct zbx_json *json)
{
	const zbx_config_item_type_timeouts_t	*timeouts;

	timeouts = (0 == proxy->custom_timeouts ? &config->config->item_timeouts : &proxy->item_timeouts);

	zbx_json_addobject(json, "timeouts");

	proxy_discovery_add_item_type_timeout("zabbix_agent", json, timeouts->agent, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("simple_check", json, timeouts->simple, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("snmp_check", json, timeouts->snmp, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("external_check", json, timeouts->external, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("db_monitor", json, timeouts->odbc, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("http_agent", json, timeouts->http, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("ssh_agent", json, timeouts->ssh, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("telnet_agent", json, timeouts->telnet, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("script", json, timeouts->script, proxy->proxyid);
	proxy_discovery_add_item_type_timeout("browser", json, timeouts->browser, proxy->proxyid);

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add proxy discovery row                                           *
 *                                                                            *
 ******************************************************************************/
static void	dc_proxy_discovery_add_row(struct zbx_json *json, const ZBX_DC_PROXY *dc_proxy, int now)
{
	zbx_json_addobject(json, NULL);

	zbx_json_addstring(json, "name", dc_proxy->name, ZBX_JSON_TYPE_STRING);

	if (PROXY_OPERATING_MODE_PASSIVE == dc_proxy->mode)
		zbx_json_addstring(json, "passive", "true", ZBX_JSON_TYPE_INT);
	else
		zbx_json_addstring(json, "passive", "false", ZBX_JSON_TYPE_INT);

	unsigned int	encryption;

	if (PROXY_OPERATING_MODE_PASSIVE == dc_proxy->mode)
		encryption = dc_proxy->tls_connect;
	else
		encryption = dc_proxy->tls_accept;

	if (0 < (encryption & ZBX_TCP_SEC_UNENCRYPTED))
		zbx_json_addstring(json, "unencrypted", "true", ZBX_JSON_TYPE_INT);
	else
		zbx_json_addstring(json, "unencrypted", "false", ZBX_JSON_TYPE_INT);

	if (0 < (encryption & ZBX_TCP_SEC_TLS_PSK))
		zbx_json_addstring(json, "psk", "true", ZBX_JSON_TYPE_INT);
	else
		zbx_json_addstring(json, "psk", "false", ZBX_JSON_TYPE_INT);

	if (0 < (encryption & ZBX_TCP_SEC_TLS_CERT))
		zbx_json_addstring(json, "cert", "true", ZBX_JSON_TYPE_INT);
	else
		zbx_json_addstring(json, "cert", "false", ZBX_JSON_TYPE_INT);

	zbx_json_adduint64(json, "items", dc_proxy->items_active_normal +
			dc_proxy->items_active_notsupported);

	zbx_json_addstring(json, "compression", "true", ZBX_JSON_TYPE_INT);

	zbx_json_addstring(json, "version", dc_proxy->version_str, ZBX_JSON_TYPE_STRING);

	zbx_json_adduint64(json, "compatibility", dc_proxy->compatibility);

	if (0 < dc_proxy->lastaccess)
		zbx_json_addint64(json, "last_seen", time(NULL) - dc_proxy->lastaccess);
	else
		zbx_json_addint64(json, "last_seen", -1);

	zbx_json_adduint64(json, "hosts", dc_proxy->hosts_monitored);

	zbx_json_addfloat(json, "requiredperformance", dc_proxy->required_performance);

	proxy_discovery_get_timeouts(dc_proxy, json);

	int	failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY;

	if (0 != dc_proxy->proxy_groupid)
	{
		zbx_dc_proxy_group_t	*pg;

		pg = (zbx_dc_proxy_group_t *)zbx_hashset_search(&config->proxy_groups,
				&dc_proxy->proxy_groupid);

		zbx_json_addstring(json, "proxy_group", pg->name, ZBX_JSON_TYPE_STRING);

		const char	*ptr = pg->failover_delay;

		if ('{' == *ptr)
		{
			um_cache_resolve_const(config->um_cache, NULL, 0, pg->failover_delay, ZBX_MACRO_ENV_NONSECURE,
					&ptr);
		}

		(void)zbx_is_time_suffix(ptr, &failover_delay, ZBX_LENGTH_UNLIMITED);
	}

	const char	*state = (now - dc_proxy->lastaccess >= failover_delay ? "offline" : "online");

	zbx_json_addstring(json, ZBX_PROTO_TAG_STATE, state, ZBX_JSON_TYPE_STRING);

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add proxy group configuration data row                            *
 *                                                                            *
 ******************************************************************************/
static void	dc_proxy_group_discovery_add_group_cfg(struct zbx_json *json, const zbx_dc_proxy_group_t *pg)
{
#define INVALID_VALUE	-1
	const char	*ptr;
	int		failover_delay, min_online;

	zbx_json_addobject(json, NULL);

	zbx_json_addstring(json, "name", pg->name, ZBX_JSON_TYPE_STRING);

	ptr = pg->failover_delay;

	if ('{' == *ptr)
		um_cache_resolve_const(config->um_cache, NULL, 0, pg->failover_delay, ZBX_MACRO_ENV_NONSECURE, &ptr);

	if (FAIL == zbx_is_time_suffix(ptr, &failover_delay, ZBX_LENGTH_UNLIMITED))
		failover_delay = INVALID_VALUE;

	zbx_json_addint64(json, "failover_delay", failover_delay);

	ptr = pg->min_online;

	if ('{' == *ptr)
		um_cache_resolve_const(config->um_cache, NULL, 0, pg->min_online, ZBX_MACRO_ENV_NONSECURE, &ptr);

	min_online = atoi(ptr);

	if (ZBX_PG_PROXY_MIN_ONLINE_MIN > min_online || ZBX_PG_PROXY_MIN_ONLINE_MAX < min_online)
		min_online = INVALID_VALUE;

	zbx_json_addint64(json, "min_online", min_online);

	zbx_json_close(json);
#undef INVALID_VALUE
}

/******************************************************************************
 *                                                                            *
 * Purpose: add proxy group real-time statistics row                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_proxy_group_discovery_add_group_rtdata(const zbx_dc_proxy_group_t *pg, zbx_hashset_t *pgroups_rtdata,
		struct zbx_json *json)
{
	zbx_pg_rtdata_t		*rtdata;

	zbx_json_addobject(json, pg->name);

	if (NULL == (rtdata = (zbx_pg_rtdata_t *)zbx_hashset_search(pgroups_rtdata, &pg->proxy_groupid)))
		goto out;

	zbx_json_addint64(json, "state", rtdata->status);
	zbx_json_addint64(json, "available", rtdata->proxy_online_num);

	double	perc;

	if (0 != rtdata->proxy_num)
		perc = (double)rtdata->proxy_online_num / rtdata->proxy_num * 100;
	else
		perc = 0;

	zbx_json_adddouble(json, "pavailable", perc);
out:
	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get data of all proxies from configuration cache and pack into    *
 *          JSON for LLD                                                      *
 *                                                                            *
 * Parameter: data   - [OUT] JSON with proxy data                             *
 *                                                                            *
 * Comments: Allocates memory.                                                *
 *           If there are no proxies, an empty JSON {"data":[]} is returned.  *
 *                                                                            *
 ******************************************************************************/
void	zbx_proxy_discovery_get(char **data)
{
	int		now;
	struct zbx_json	json;

	dc_status_update();

	now = (int)time(NULL);

	zbx_hashset_iter_t	iter;
	ZBX_DC_PROXY		*dc_proxy;

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);

	RDLOCK_CACHE;

	zbx_hashset_iter_reset(&config->proxies, &iter);
	while (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_iter_next(&iter)))
		dc_proxy_discovery_add_row(&json, dc_proxy, now);

	UNLOCK_CACHE;

	zbx_json_close(&json);
	*data = zbx_strdup(NULL, json.buffer);

	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get configuration and realtime data of all proxy groups and pack  *
 *          into JSON                                                         *
 *                                                                            *
 * Parameter: data - [OUT] JSON with proxy group data                         *
 *                                                                            *
 * Comments: Allocates memory.                                                *
 *           Configuration data is taken from configuration cache.            *
 *           Real-time data is taken from proxy group manager via IPC.        *
 *                                                                            *
 * Output JSON example:                                                       *
 * {                                                                          *
 *     "data": [                                                              *
 *        { "name": "Riga", "failover_delay": 60, "min_online": 1 },          *
 *        { "name": "Tokyo", "failover_delay": 60, "min_online": 2 },         *
 *        { "name": "Porto Alegre", "failover_delay": 60, "min_online": 3 }   *
 *     ],                                                                     *
 *     "rtdata": {                                                            *
 *         "Riga": { "state": 3, "available": 10, "pavailable": 20 },         *
 *         "Tokyo": { "state": 3, "available": 10, "pavailable": 20 },        *
 *         "Porto Alegre": { "state": 1, "available": 0, "pavailable": 0 }    *
 *     }                                                                      *
 * }                                                                          *
 *                                                                            *
 * If an error happened while retrieving rtdata for some of the proxy groups: *
 * {                                                                          *
 * // ...                                                                     *
 *     "rtdata": {                                                            *
 * // ...                                                                     *
 *         "Tokyo": {},                                                       *
 * // ...                                                                     *
 *     }                                                                      *
 * }                                                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_proxy_group_discovery_get(char **data)
{
	char			*error = NULL;
	struct zbx_json		json;
	zbx_hashset_iter_t	iter;
	zbx_dc_proxy_group_t	*dc_proxy_group;
	zbx_hashset_t		pgroups_rtdata;

	zbx_hashset_create(&pgroups_rtdata, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&json, "data");

	RDLOCK_CACHE;

	zbx_hashset_iter_reset(&config->proxy_groups, &iter);
	while (NULL != (dc_proxy_group = (zbx_dc_proxy_group_t *)zbx_hashset_iter_next(&iter)))
		dc_proxy_group_discovery_add_group_cfg(&json, dc_proxy_group);

	zbx_json_close(&json);

	zbx_json_addobject(&json, "rtdata");

	if (FAIL == zbx_pg_get_all_rtdata(&pgroups_rtdata, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot obtain real-time data for proxy groups: %s", error);
		zbx_free(error);
	}

	zbx_hashset_iter_reset(&config->proxy_groups, &iter);
	while (NULL != (dc_proxy_group = (zbx_dc_proxy_group_t *)zbx_hashset_iter_next(&iter)))
		dc_proxy_group_discovery_add_group_rtdata(dc_proxy_group, &pgroups_rtdata, &json);

	UNLOCK_CACHE;

	zbx_hashset_destroy(&pgroups_rtdata);

	zbx_json_close(&json);
	zbx_json_close(&json);
	*data = zbx_strdup(NULL, json.buffer);

	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get data of specified proxies from configuration cache and pack   *
 *          into JSON for LLD                                                 *
 *                                                                            *
 * Parameter: proxyids - [IN] target proxyids                                 *
 *            data     - [OUT] JSON with proxy data                           *
 *            error    - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - interface data in JSON, 'data' is allocated        *
 *               FAIL    - proxy not found, 'error' message is allocated      *
 *                                                                            *
 * Comments: Allocates memory.                                                *
 *           If there are no proxies, an empty JSON {"data":[]} is returned.  *
 *                                                                            *
 ******************************************************************************/
int	zbx_proxy_proxy_list_discovery_get(const zbx_vector_uint64_t *proxyids, char **data, char **error)
{
	int				ret = FAIL, now;
	struct zbx_json			json;

	dc_status_update();

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);
	now = (int)time(NULL);

	RDLOCK_CACHE;

	for (int i = 0; i < proxyids->values_num; i++)
	{
		const ZBX_DC_PROXY	*dc_proxy;

		if (NULL == (dc_proxy = (const ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies,
				&proxyids->values[i])))
		{
			*error = zbx_dsprintf(*error, "Proxy with identifier \"" ZBX_FS_UI64
					"\" not found in configuration cache.", proxyids->values[i]);
			goto out;
		}

		dc_proxy_discovery_add_row(&json, dc_proxy, now);
	}

	ret = SUCCEED;
out:
	UNLOCK_CACHE;

	if (SUCCEED == ret)
	{
		zbx_json_close(&json);
		*data = zbx_strdup(NULL, json.buffer);
	}

	zbx_json_free(&json);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns server/proxy instance id                                  *
 *                                                                            *
 * Return value: the instance id                                              *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_dc_get_instanceid(void)
{
	/* instanceid is initialized during the first configuration cache synchronization */
	/* and is never updated - so it can be accessed without locking cache             */
	return config->config->instanceid;
}

/******************************************************************************
 *                                                                            *
 * Parameters: params - [IN] the function parameters                          *
 *             hostid - [IN] host of the item used in function                *
 *                                                                            *
 * Return value: The function parameters with expanded user macros.           *
 *                                                                            *
 ******************************************************************************/
char	*zbx_dc_expand_user_macros_in_func_params(const char *params, zbx_uint64_t hostid)
{
	const char		*ptr;
	size_t			params_len;
	char			*buf;
	size_t			buf_alloc, buf_offset = 0, sep_pos;
	zbx_dc_um_handle_t	*um_handle;

	if ('\0' == *params)
		return zbx_strdup(NULL, "");

	buf_alloc = params_len = strlen(params);
	buf = zbx_malloc(NULL, buf_alloc);

	um_handle = zbx_dc_open_user_macros();

	for (ptr = params; ptr < params + params_len; ptr += sep_pos + 1)
	{
		size_t	param_pos, param_len;
		int	quoted;
		char	*param;

		zbx_trigger_function_param_parse(ptr, &param_pos, &param_len, &sep_pos);

		param = zbx_function_param_unquote_dyn(ptr + param_pos, param_len, &quoted);
		(void)zbx_dc_expand_user_and_func_macros(um_handle, &param, &hostid, 1, NULL);

		if (SUCCEED == zbx_function_param_quote(&param, quoted, 1))
			zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, param);
		else
			zbx_strncpy_alloc(&buf, &buf_alloc, &buf_offset, ptr + param_pos, param_len);

		if (',' == ptr[sep_pos])
			zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ',');

		zbx_free(param);
	}

	zbx_dc_close_user_macros(um_handle);

	return buf;
}

/*********************************************************************************
 *                                                                               *
 * Parameters: hostid               - [IN]                                       *
 *             agents               - [OUT] Zabbix agent availability            *
 *                                                                               *
 ********************************************************************************/
void	zbx_get_host_interfaces_availability(zbx_uint64_t hostid, zbx_agent_availability_t *agents)
{
	const ZBX_DC_INTERFACE		*interface;
	zbx_hashset_iter_t		iter;
	int				i;

	for (i = 0; i < ZBX_AGENT_MAX; i++)
		zbx_agent_availability_init(&agents[i], ZBX_INTERFACE_AVAILABLE_UNKNOWN, "", 0, 0);

	RDLOCK_CACHE;

	zbx_hashset_iter_reset(&config->interfaces, &iter);

	while (NULL != (interface = (const ZBX_DC_INTERFACE *)zbx_hashset_iter_next(&iter)))
	{
		if (1 != interface->main)
			continue;

		if (hostid != interface->hostid)
			continue;

		i = ZBX_AGENT_UNKNOWN;

		if (INTERFACE_TYPE_AGENT == interface->type)
			i = ZBX_AGENT_ZABBIX;
		else if (INTERFACE_TYPE_IPMI == interface->type)
			i = ZBX_AGENT_IPMI;
		else if (INTERFACE_TYPE_JMX == interface->type)
			i = ZBX_AGENT_JMX;
		else if (INTERFACE_TYPE_SNMP == interface->type)
			i = ZBX_AGENT_SNMP;

		if (ZBX_AGENT_UNKNOWN != i)
			DCinterface_get_agent_availability(interface, &agents[i]);
	}

	UNLOCK_CACHE;

}

int	zbx_dc_maintenance_has_tags(void)
{
	int	ret;

	RDLOCK_CACHE;
	ret = config->maintenance_tags.num_data != 0 ? SUCCEED : FAIL;
	UNLOCK_CACHE;

	return ret;
}

/* external user macro cache API */

/******************************************************************************
 *                                                                            *
 * Purpose: open handle for user macro resolving in the specified security    *
 *          level                                                             *
 *                                                                            *
 * Parameters: macro_env - [IN] - the macro resolving environment:            *
 *                                  ZBX_MACRO_ENV_NONSECURE                   *
 *                                  ZBX_MACRO_ENV_SECURE                      *
 *                                  ZBX_MACRO_ENV_DEFAULT (last opened or     *
 *                                    non-secure environment)                 *
 *                                                                            *
 * Return value: the handle for macro resolving, must be closed with          *
 *        zbx_dc_close_user_macros()                                          *
 *                                                                            *
 * Comments: First handle will lock user macro cache in configuration cache.  *
 *           Consequent openings within the same process without closing will *
 *           reuse the locked cache until all opened caches are closed.       *
 *                                                                            *
 ******************************************************************************/
static zbx_dc_um_handle_t	*dc_open_user_macros(unsigned char macro_env)
{
	zbx_dc_um_handle_t	*handle;
	static zbx_um_cache_t	*um_cache = NULL;

	handle = (zbx_dc_um_handle_t *)zbx_malloc(NULL, sizeof(zbx_dc_um_handle_t));

	if (NULL != dc_um_handle)
	{
		if (ZBX_MACRO_ENV_DEFAULT == macro_env)
			macro_env = dc_um_handle->macro_env;
	}
	else
	{
		if (ZBX_MACRO_ENV_DEFAULT == macro_env)
			macro_env = ZBX_MACRO_ENV_NONSECURE;
	}

	handle->macro_env = macro_env;
	handle->prev = dc_um_handle;
	handle->cache = &um_cache;

	dc_um_handle = handle;

	return handle;
}

zbx_dc_um_handle_t	*zbx_dc_open_user_macros(void)
{
	return dc_open_user_macros(ZBX_MACRO_ENV_DEFAULT);
}

zbx_dc_um_handle_t	*zbx_dc_open_user_macros_secure(void)
{
	return dc_open_user_macros(ZBX_MACRO_ENV_SECURE);
}

zbx_dc_um_handle_t	*zbx_dc_open_user_macros_masked(void)
{
	return dc_open_user_macros(ZBX_MACRO_ENV_NONSECURE);
}

static const zbx_um_cache_t	*dc_um_get_cache(const zbx_dc_um_handle_t *um_handle)
{
	if (NULL == *um_handle->cache)
	{
		WRLOCK_CACHE;
		*um_handle->cache = config->um_cache;
		config->um_cache->refcount++;
		UNLOCK_CACHE;
	}

	return *um_handle->cache;
}

/******************************************************************************
 *                                                                            *
 * Purpose: closes user macro resolving handle                                *
 *                                                                            *
 * Comments: Closing the last opened handle within process will release locked*
 *           user macro cache in the configuration cache.                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_close_user_macros(zbx_dc_um_handle_t *um_handle)
{
	if (NULL == um_handle->prev && NULL != *um_handle->cache)
	{
		WRLOCK_CACHE;
		um_cache_release(*um_handle->cache);
		UNLOCK_CACHE;

		*um_handle->cache = NULL;
	}

	dc_um_handle = um_handle->prev;
	zbx_free(um_handle);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get user macro using the specified hosts                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_user_macro(const zbx_dc_um_handle_t *um_handle, const char *macro, const zbx_uint64_t *hostids,
		int hostids_num, char **value)
{
	um_cache_resolve(dc_um_get_cache(um_handle), hostids, hostids_num, macro, um_handle->macro_env, value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: expand user and function  macros in the specified text value      *
 *                                                                            *
 * Parameters: um_handle   - [IN] the user macro cache handle                 *
 *             text        - [IN/OUT] the text value with macros to expand    *
 *             hostids     - [IN] an array of host identifiers                *
 *             hostids_num - [IN] the number of host identifiers              *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_expand_user_and_func_macros(const zbx_dc_um_handle_t *um_handle, char **text,
		const zbx_uint64_t *hostids, int hostids_num, char **error)
{
	zbx_token_t	token;
	int		pos = 0, ret = FAIL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() '%s'", __func__, *text);

	for (; SUCCEED == zbx_token_find(*text, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		const char	*value = NULL;
		char		*out = NULL;
		zbx_token_t	inner_token;

		switch(token.type)
		{
			case ZBX_TOKEN_USER_FUNC_MACRO:
				um_cache_resolve_const(dc_um_get_cache(um_handle), hostids, hostids_num, *text +
						token.loc.l + 1, um_handle->macro_env, &value);

				if (NULL != value)
					out = zbx_strdup(NULL, value);

				if (SUCCEED == zbx_token_find(*text + token.loc.l, 0, &inner_token,
						ZBX_TOKEN_SEARCH_BASIC))
				{
					ret = zbx_calculate_macro_function(*text + token.loc.l,
							&inner_token.data.func_macro, &out);
					value = out;
				}

				break;
			case ZBX_TOKEN_USER_MACRO:
				um_cache_resolve_const(dc_um_get_cache(um_handle), hostids, hostids_num, *text +
						token.loc.l, um_handle->macro_env, &value);
				break;
			default:
				continue;
		}

		if (NULL == value)
		{
			if (NULL != error)
			{
				*error = zbx_dsprintf(NULL, "unknown user macro \"%.*s\"",
						(int)(token.loc.r - token.loc.l + 1), *text +
						token.loc.l);
				goto out;
			}
		}
		else
			zbx_replace_string(text, token.loc.l, &token.loc.r, value);

		pos = (int)token.loc.r;
		zbx_free(out);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s() '%s'", __func__, *text);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: expand user and func macros in the specified text value           *
 *                                                                            *
 * Parameters: um_cache    - [IN] the user macro cache                        *
 *             text        - [IN/OUT] the text value with macros to expand    *
 *             hostids     - [IN] an array of host identifiers                *
 *             hostids_num - [IN] the number of host identifiers              *
 *             env         - [IN] security environment                        *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_expand_user_and_func_macros_from_cache(zbx_um_cache_t *um_cache, char **text,
		const zbx_uint64_t *hostids, int hostids_num, unsigned char env, char **error)
{
	/* wrap the passed user macro and func macro cache into user macro handle structure */
	zbx_dc_um_handle_t	um_handle = {.cache = &um_cache, .macro_env = env, .prev = NULL};

	return zbx_dc_expand_user_and_func_macros(&um_handle, text, hostids, hostids_num, error);
}

typedef struct
{
	ZBX_DC_ITEM	*item;
	ZBX_DC_HOST	*host;
	char		*delay_ex;
	zbx_uint64_t	proxyid;
}
zbx_item_delay_t;

ZBX_PTR_VECTOR_DECL(item_delay, zbx_item_delay_t *)
ZBX_PTR_VECTOR_IMPL(item_delay, zbx_item_delay_t *)

static void	zbx_item_delay_free(zbx_item_delay_t *item_delay)
{
	zbx_free(item_delay->delay_ex);
	zbx_free(item_delay);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if item must be activated because its host has changed      *
 *          monitoring status to 'active' or unassigned from proxy            *
 *                                                                            *
 * Parameters: item            - [IN] the item to check                       *
 *             host            - [IN] the item's host                         *
 *             activated hosts - [IN] the activated host identifiers          *
 *             activated_items - [OUT] items to be rescheduled because host   *
 *                                     being activated                        *
 *                                                                            *
 ******************************************************************************/
static void	dc_check_item_activation(ZBX_DC_ITEM *item, ZBX_DC_HOST *host,
		const zbx_hashset_t *activated_hosts, zbx_vector_ptr_pair_t *activated_items)
{
	zbx_ptr_pair_t	pair;

	if (ZBX_LOC_NOWHERE != item->location)
		return;

	if (HOST_MONITORED_BY_SERVER != host->monitored_by &&
			SUCCEED != zbx_is_item_processed_by_server(item->type, item->key))
	{
		return;
	}

	if (NULL == zbx_hashset_search(activated_hosts, &host->hostid))
		return;

	pair.first = item;
	pair.second = host;

	zbx_vector_ptr_pair_append(activated_items, pair);
}
/******************************************************************************
 *                                                                            *
 * Purpose: get items with changed expanded delay value                       *
 *                                                                            *
 * Parameters: activated_hosts - [IN]                                         *
 *             items           - [OUT] items to be rescheduled because of     *
 *                                     delay changes                          *
 *             activated_items - [OUT] items to be rescheduled because host   *
 *                                     being activated                        *
 *                                                                            *
 * Comments: This function is used only by configuration syncer, so it cache  *
 *           locking is not needed to access data changed only by the syncer  *
 *           itself.                                                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_get_items_to_reschedule(const zbx_hashset_t *activated_hosts, zbx_vector_item_delay_t *items,
		zbx_vector_ptr_pair_t *activated_items)
{
	zbx_hashset_iter_t	iter;
	ZBX_DC_ITEM		*item;
	ZBX_DC_HOST		*host;
	char			*delay_ex;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_iter_reset(&config->items, &iter);
	while (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_iter_next(&iter)))
	{
		if (ITEM_STATUS_ACTIVE != item->status ||
				SUCCEED != zbx_is_counted_in_item_queue(item->type, item->key))
		{
			continue;
		}

		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != host->status)
			continue;

		if (NULL == strstr(item->delay, "{$"))
		{
			/* neither new item revision or the last one had macro in delay */
			if (NULL == item->delay_ex)
			{
				dc_check_item_activation(item, host, activated_hosts, activated_items);
				continue;
			}

			delay_ex = NULL;
		}
		else
		{
			delay_ex = dc_expand_user_and_func_macros_dyn(item->delay, &item->hostid, 1,
					ZBX_MACRO_ENV_NONSECURE);
		}

		if (0 != zbx_strcmp_null(item->delay_ex, delay_ex))
		{
			zbx_item_delay_t	*item_delay;

			item_delay = (zbx_item_delay_t *)zbx_malloc(NULL, sizeof(zbx_item_delay_t));
			item_delay->item = item;
			item_delay->host = host;
			item_delay->delay_ex = delay_ex;
			item_delay->proxyid = host->proxyid;

			zbx_vector_item_delay_append(items, item_delay);
		}
		else
		{
			zbx_free(delay_ex);
			dc_check_item_activation(item, host, activated_hosts, activated_items);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() items:%d", __func__, items->values_num);
}

static void	dc_reschedule_item(ZBX_DC_ITEM *item, const ZBX_DC_HOST *host, int now)
{
	int	old_nextcheck = item->nextcheck;
	char	*error = NULL;

	if (SUCCEED == DCitem_nextcheck_update(item, NULL, ZBX_ITEM_DELAY_CHANGED, now, &error))
	{
		if (ZBX_LOC_NOWHERE == item->location)
			DCitem_poller_type_update(item, host, ZBX_ITEM_COLLECTED);

		DCupdate_item_queue(item, item->poller_type, old_nextcheck);
	}
	else
	{
		zbx_timespec_t	ts = {now, 0};
		zbx_dc_add_history(item->itemid, item->value_type, 0, NULL, &ts, ITEM_STATE_NOTSUPPORTED, error);
		zbx_free(error);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: reschedule items with macros in delay/period that will not be     *
 *          checked in next minute                                            *
 *                                                                            *
 * Comments: This must be done after configuration cache sync to ensure that  *
 *           user macro changes affects item queues.                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_reschedule_items(const zbx_hashset_t *activated_hosts)
{
	zbx_vector_item_delay_t		items;
	zbx_vector_ptr_pair_t		activated_items;

	zbx_vector_item_delay_create(&items);
	zbx_vector_ptr_pair_create(&activated_items);

	dc_get_items_to_reschedule(activated_hosts, &items, &activated_items);

	if (0 != items.values_num || 0 != activated_items.values_num)
	{
		int	i, now;

		now = (int)time(NULL);

		WRLOCK_CACHE;

		for (i = 0; i < items.values_num; i++)
		{
			ZBX_DC_ITEM	*item = items.values[i]->item;

			if (NULL == items.values[i]->delay_ex)
			{
				/* Macro is removed form item delay, which means item was already */
				/* rescheduled by syncer. Just reset the delay_ex in cache.       */
				dc_strpool_release(item->delay_ex);
				item->delay_ex = NULL;
				continue;
			}

			if (0 != items.values[i]->proxyid)
			{
				/* update nextcheck for active and monitored by proxy items */
				/* for queue requests by frontend.                          */
				if (NULL != item->delay_ex)
					(void)DCitem_nextcheck_update(item, NULL, ZBX_ITEM_DELAY_CHANGED, now, NULL);
			}
			else if (NULL != item->delay_ex)
				dc_reschedule_item(item, items.values[i]->host, now);

			dc_strpool_replace(NULL != item->delay_ex, &item->delay_ex, items.values[i]->delay_ex);
		}

		for (i = 0; i < activated_items.values_num; i++)
			dc_reschedule_item(activated_items.values[i].first, activated_items.values[i].second, now);

		UNLOCK_CACHE;

		zbx_dc_flush_history();
	}

	zbx_vector_ptr_pair_destroy(&activated_items);
	zbx_vector_item_delay_clear_ext(&items, zbx_item_delay_free);
	zbx_vector_item_delay_destroy(&items);
}


/******************************************************************************
 *                                                                            *
 * Purpose: reschedule httptests on hosts that were re-enabled or unassigned  *
 *          from proxy                                                        *
 *                                                                            *
 * Comments: Cache is not locked for read access because this function is     *
 *           called from configuration syncer and nobody else can add/remove  *
 *           objects or change their configuration.                           *
 *                                                                            *
 ******************************************************************************/
static void	dc_reschedule_httptests(zbx_hashset_t *activated_hosts)
{
	zbx_vector_dc_httptest_ptr_t	httptests;
	zbx_hashset_iter_t		iter;
	int				i;
	zbx_uint64_t			*phostid;
	ZBX_DC_HOST			*host;
	time_t				now;

	zbx_vector_dc_httptest_ptr_create(&httptests);

	now = time(NULL);

	zbx_hashset_iter_reset(activated_hosts, &iter);
	while (NULL != (phostid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, phostid)))
			continue;

		for (i = 0; i < host->httptests.values_num; i++)
		{
			if (ZBX_LOC_NOWHERE != host->httptests.values[i]->location)
				continue;

			zbx_vector_dc_httptest_ptr_append(&httptests, host->httptests.values[i]);
		}
	}

	if (0 != httptests.values_num)
	{
		WRLOCK_CACHE;

		for (i = 0; i < httptests.values_num; i++)
		{
			zbx_dc_httptest_t	*httptest = httptests.values[i];

			httptest->nextcheck = dc_calculate_nextcheck(httptest->httptestid, httptest->delay, now);
			dc_httptest_queue(httptest);
		}

		UNLOCK_CACHE;
	}

	zbx_vector_dc_httptest_ptr_destroy(&httptests);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get drules ready to be processed                                  *
 *                                                                            *
 * Parameter: now       - [IN] the current timestamp                          *
 *            drules    - [IN/OUT] drules ready to be processed               *
 *            nextcheck - [OUT] the timestamp of next drule to be processed,  *
 *                              if there is no rule to be processed now and   *
 *                              the queue is not empty. 0 otherwise           *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_drules_get(time_t now, zbx_vector_dc_drule_ptr_t *drules, time_t *nextcheck)
{
	zbx_binary_heap_elem_t	*elem;
	zbx_dc_drule_t		*drule, *drule_out = NULL;

	*nextcheck = 0;

	WRLOCK_CACHE;

	while (FAIL == zbx_binary_heap_empty(&config->drule_queue))
	{
		elem = zbx_binary_heap_find_min(&config->drule_queue);
		drule = (zbx_dc_drule_t *)elem->data;

		if (drule->nextcheck <= now)
		{
			zbx_hashset_iter_t	iter;
			zbx_dc_dcheck_t		*dcheck, *dheck_out;

			zbx_binary_heap_remove_min(&config->drule_queue);
			drule->location = ZBX_LOC_POLLER;

			drule_out = zbx_malloc(NULL, sizeof(zbx_dc_drule_t));
			drule_out->druleid = drule->druleid;
			drule_out->proxyid = drule->proxyid;
			drule_out->nextcheck = drule->nextcheck;
			drule_out->delay = drule->delay;
			drule_out->delay_str = zbx_strdup(NULL, drule->delay_str);
			drule_out->name = zbx_strdup(NULL, drule->name);
			drule_out->iprange = zbx_strdup(NULL, drule->iprange);
			drule_out->status = drule->status;
			drule_out->location = drule->location;
			drule_out->revision = drule->revision;
			drule_out->unique_dcheckid = 0;
			drule_out->concurrency_max = drule->concurrency_max;

			zbx_vector_dc_dcheck_ptr_create(&drule_out->dchecks);
			zbx_hashset_iter_reset(&config->dchecks, &iter);

			while (NULL != (dcheck = (zbx_dc_dcheck_t *)zbx_hashset_iter_next(&iter)))
			{
				if (dcheck->druleid != drule->druleid)
					continue;

				dheck_out = zbx_malloc(NULL, sizeof(zbx_dc_dcheck_t));
				dheck_out->druleid = dcheck->druleid;
				dheck_out->dcheckid = dcheck->dcheckid;
				dheck_out->key_ = zbx_strdup(NULL, dcheck->key_);
				dheck_out->ports = zbx_strdup(NULL, dcheck->ports);
				dheck_out->uniq = dcheck->uniq;
				dheck_out->type = dcheck->type;
				dheck_out->allow_redirect = dcheck->allow_redirect;
				dheck_out->timeout = 0;

				if (SVC_SNMPv1 == dheck_out->type || SVC_SNMPv2c == dheck_out->type ||
						SVC_SNMPv3 == dheck_out->type)
				{
					dheck_out->snmp_community = zbx_strdup(NULL, dcheck->snmp_community);
					dheck_out->snmpv3_securityname = zbx_strdup(NULL, dcheck->snmpv3_securityname);
					dheck_out->snmpv3_securitylevel = dcheck->snmpv3_securitylevel;
					dheck_out->snmpv3_authpassphrase = zbx_strdup(NULL,
							dcheck->snmpv3_authpassphrase);
					dheck_out->snmpv3_privpassphrase = zbx_strdup(NULL,
							dcheck->snmpv3_privpassphrase);
					dheck_out->snmpv3_authprotocol = dcheck->snmpv3_authprotocol;
					dheck_out->snmpv3_privprotocol = dcheck->snmpv3_privprotocol;
					dheck_out->snmpv3_contextname = zbx_strdup(NULL, dcheck->snmpv3_contextname);
				}

				zbx_vector_dc_dcheck_ptr_append(&drule_out->dchecks, dheck_out);
			}

			zbx_vector_dc_dcheck_ptr_sort(&drule_out->dchecks, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
			zbx_vector_dc_drule_ptr_append(drules, drule_out);
		}
		else
		{
			*nextcheck = drule->nextcheck;
			break;
		}
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue drule to be processed according to the delay                *
 *                                                                            *
 * Parameter: now      - [IN] the current timestamp                           *
 *            druleid  - [IN] the id of drule to be queued                    *
 *            delay    - [IN] the number of seconds between drule processing  *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_drule_queue(time_t now, zbx_uint64_t druleid, int delay)
{
	zbx_dc_drule_t	*drule;

	WRLOCK_CACHE;

	if (NULL != (drule = (zbx_dc_drule_t *)zbx_hashset_search(&config->drules, &druleid)))
	{
		drule->delay = delay;
		drule->nextcheck = dc_calculate_nextcheck(drule->druleid, drule->delay, now);
		dc_drule_queue(drule);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get discovery rules IDs with revisions in pairs                   *
 *                                                                            *
 * Parameters: rev_last   - [IN/OUT] discovery rules global revision          *
 *             revisions  - [IN/OUT] discovery rules ID/revisions pairs       *
 *                                                                            *
 * Return value: SUCCEED - if discovery rules global revision differs from    *
 *                            revision provided in argument rev_last          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: In case of FAIL the resulting ID/revisions pairs vector and      *
 *              rev_last will not be updated                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_drule_revisions_get(zbx_uint64_t *rev_last, zbx_vector_uint64_pair_t *revisions)
{
	int	ret;

	RDLOCK_CACHE;

	if (config->revision.drules != *rev_last)
	{
		zbx_hashset_iter_t	iter;
		zbx_uint64_pair_t	revision;
		zbx_dc_drule_t		*drule;

		zbx_hashset_iter_reset(&config->drules, &iter);

		while (NULL != (drule = (zbx_dc_drule_t *)zbx_hashset_iter_next(&iter)))
		{
			revision.first = drule->druleid;
			revision.second = drule->revision;
			zbx_vector_uint64_pair_append(revisions, revision);
		}

		*rev_last = config->revision.drules;
		zbx_vector_uint64_pair_sort(revisions, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		ret = SUCCEED;
	}
	else
		ret = FAIL;

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get next httptest to be processed                                 *
 *                                                                            *
 * Parameter: now        - [IN] the current timestamp                         *
 *            httptestid - [OUT] the id of httptest to be processed           *
 *            nextcheck  - [OUT] the timestamp of next httptest to be         *
 *                               processed, if there is no httptest to be     *
 *                               processed now and the queue is not empty.    *
 *                               0 - otherwise                                *
 *                                                                            *
 * Return value: SUCCEED - the httptest id was returned successfully          *
 *               FAIL    - no httptests are scheduled at current time         *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_httptest_next(time_t now, zbx_uint64_t *httptestid, time_t *nextcheck)
{
	zbx_binary_heap_elem_t	*elem;
	zbx_dc_httptest_t	*httptest;
	int			ret = FAIL;
	ZBX_DC_HOST		*dc_host;

	*nextcheck = 0;

	WRLOCK_CACHE;

	while (FAIL == zbx_binary_heap_empty(&config->httptest_queue))
	{
		elem = zbx_binary_heap_find_min(&config->httptest_queue);
		httptest = (zbx_dc_httptest_t *)elem->data;

		if (httptest->nextcheck <= now)
		{
			zbx_binary_heap_remove_min(&config->httptest_queue);
			httptest->location = ZBX_LOC_NOWHERE;

			if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &httptest->hostid)))
				continue;

			if (HOST_STATUS_MONITORED != dc_host->status || 0 != dc_host->proxyid)
				continue;

			if (HOST_MAINTENANCE_STATUS_ON == dc_host->maintenance_status &&
					MAINTENANCE_TYPE_NODATA == dc_host->maintenance_type)
			{
				httptest->nextcheck = dc_calculate_nextcheck(httptest->httptestid, httptest->delay, now);
				dc_httptest_queue(httptest);

				continue;
			}

			httptest->location = ZBX_LOC_POLLER;
			*httptestid = httptest->httptestid;

			ret = SUCCEED;
		}
		else
			*nextcheck = httptest->nextcheck;

		break;
	}

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue httptest to be processed according to the delay             *
 *                                                                            *
 * Parameter: now        - [IN] the current timestamp                         *
 *            httptestid - [IN] the id of httptest to be queued               *
 *            delay      - [IN] the number of seconds between httptest        *
 *                              processing                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_httptest_queue(time_t now, zbx_uint64_t httptestid, int delay)
{
	zbx_dc_httptest_t	*httptest;

	WRLOCK_CACHE;

	if (NULL != (httptest = (zbx_dc_httptest_t *)zbx_hashset_search(&config->httptests, &httptestid)))
	{
		httptest->delay = delay;
		httptest->nextcheck = dc_calculate_nextcheck(httptest->httptestid, httptest->delay, now);
		dc_httptest_queue(httptest);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the configuration revision received from server               *
 *                                                                            *
 * Comments: The revision is accessed without locking because no other process*
 *           can access it at the same time.                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_upstream_revision(zbx_uint64_t *config_revision, zbx_uint64_t *hostmap_revision)
{
	*config_revision = config->revision.upstream;
	*hostmap_revision = config->revision.upstream_hostmap;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cache the configuration revision received from server             *
 *                                                                            *
 * Comments: The revision is updated without locking because no other process *
 *           can access it at the same time.                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_set_upstream_revision(zbx_uint64_t config_revision, zbx_uint64_t hostmap_revision)
{
	config->revision.upstream = config_revision;
	config->revision.upstream_hostmap = hostmap_revision;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get hosts/httptests for proxy configuration update                *
 *                                                                            *
 * Parameters: proxyid         - [IN]                                         *
 *             revision        - [IN] the current proxy configuration revision*
 *             hostids         - [OUT] the monitored hosts                    *
 *             updated_hostids - [OUT] the hosts updated since specified      *
 *                                     configuration revision, sorted         *
 *             removed_hostids - [OUT] the hosts removed since specified      *
 *                                     configuration revision, sorted         *
 *             httptestids     - [OUT] the web scenarios monitored by proxy   *
 *             proxy_group_revision - [OUT] proxy group revision if proxy is  *
 *                                     a part of proxy group                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_proxy_config_updates(zbx_uint64_t proxyid, zbx_uint64_t revision, zbx_vector_uint64_t *hostids,
		zbx_vector_uint64_t *updated_hostids, zbx_vector_uint64_t *removed_hostids,
		zbx_vector_uint64_t *httptestids, zbx_uint64_t *proxy_group_revision)
{
	ZBX_DC_PROXY	*proxy;

	RDLOCK_CACHE;

	if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxyid)))
	{
		int	i, j;

		zbx_vector_uint64_reserve(hostids, (size_t)proxy->hosts.values_num);

		for (i = 0; i < proxy->hosts.values_num; i++)
		{
			ZBX_DC_HOST	*host = proxy->hosts.values[i];

			zbx_vector_uint64_append(hostids, host->hostid);

			if (host->revision > revision)
			{
				zbx_vector_uint64_append(updated_hostids, host->hostid);

				for (j = 0; j < host->httptests.values_num; j++)
					zbx_vector_uint64_append(httptestids, host->httptests.values[j]->httptestid);
			}
		}

		/* skip when full sync */
		if (0 != revision)
		{
			for (i = 0; i < proxy->removed_hosts.values_num; )
			{
				if (proxy->removed_hosts.values[i].revision > revision)
				{
					zbx_vector_uint64_append(removed_hostids, proxy->removed_hosts.values[i].hostid);

					/* this operation can be done with read lock:                  */
					/*   - removal from vector does not allocate/free memory       */
					/*   - two configuration requests for the same proxy cannot be */
					/*     processed at the same time                              */
					/*   - configuration syncer uses write lock to update          */
					/*     removed hosts on proxy                                  */
					zbx_vector_host_rev_remove_noorder(&proxy->removed_hosts, i);
				}
				else
					i++;
			}
		}

		if (0 != proxy->proxy_groupid)
		{
			zbx_dc_proxy_group_t	*pg;

			if (NULL != (pg = (zbx_dc_proxy_group_t *)zbx_hashset_search(&config->proxy_groups,
					&proxy->proxy_groupid)))
			{
				*proxy_group_revision = pg->revision;
			}
		}
	}

	UNLOCK_CACHE;

	zbx_vector_uint64_sort(hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_sort(updated_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_sort(removed_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_sort(httptestids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

void	zbx_dc_get_macro_updates(const zbx_vector_uint64_t *hostids, const zbx_vector_uint64_t *updated_hostids,
		zbx_uint64_t revision, zbx_vector_uint64_t *macro_hostids, int *global,
		zbx_vector_uint64_t *del_macro_hostids)
{
	zbx_vector_uint64_t	hostids_tmp, globalids;
	zbx_uint64_t		globalhostid = 0;

	/* force full sync for updated hosts (in the case host was assigned to proxy) */
	/* and revision based sync for the monitored hosts (except updated hosts that */
	/* were already synced)                                                       */

	zbx_vector_uint64_create(&hostids_tmp);
	if (0 != hostids->values_num)
	{
		zbx_vector_uint64_append_array(&hostids_tmp, hostids->values, hostids->values_num);
		zbx_vector_uint64_setdiff(&hostids_tmp, updated_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_uint64_create(&globalids);

	RDLOCK_CACHE;

	/* check revision of global macro 'host' (hostid 0) */
	um_cache_get_macro_updates(config->um_cache, &globalhostid, 1, revision, &globalids, del_macro_hostids);

	if (0 != hostids_tmp.values_num)
	{
		um_cache_get_macro_updates(config->um_cache, hostids_tmp.values, hostids_tmp.values_num, revision,
				macro_hostids, del_macro_hostids);
	}

	if (0 != updated_hostids->values_num)
	{
		um_cache_get_macro_updates(config->um_cache, updated_hostids->values, updated_hostids->values_num, 0,
				macro_hostids, del_macro_hostids);
	}

	UNLOCK_CACHE;

	*global = (0 < globalids.values_num ? SUCCEED : FAIL);

	if (0 != macro_hostids->values_num)
		zbx_vector_uint64_sort(macro_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != del_macro_hostids->values_num)
		zbx_vector_uint64_sort(del_macro_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_destroy(&globalids);
	zbx_vector_uint64_destroy(&hostids_tmp);
}

void	zbx_dc_get_unused_macro_templates(zbx_hashset_t *templates, const zbx_vector_uint64_t *hostids,
		zbx_vector_uint64_t *templateids)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	RDLOCK_CACHE;

	um_cache_get_unused_templates(config->um_cache, templates, hostids, templateids);

	UNLOCK_CACHE;

	if (0 != templateids->values_num)
		zbx_vector_uint64_sort(templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() templateids_num:%d", __func__, templateids->values_num);
}

#ifdef HAVE_TESTS
#	include "../../../tests/libs/zbxcacheconfig/dc_item_poller_type_update_test.c"
#	include "../../../tests/libs/zbxcacheconfig/dc_function_calculate_nextcheck_test.c"
#endif

void	zbx_recalc_time_period(time_t *ts_from, int table_group)
{
#define HK_CFG_UPDATE_INTERVAL	5
	time_t			least_ts = 0, now;
	zbx_config_t		cfg;
	static time_t		last_cfg_retrieval = 0;
	static zbx_config_hk_t	hk;

	now = time(NULL);

	if (HK_CFG_UPDATE_INTERVAL < now - last_cfg_retrieval)
	{
		last_cfg_retrieval = now;

		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_HOUSEKEEPER);
		hk = cfg.hk;
	}

	if (ZBX_RECALC_TIME_PERIOD_HISTORY == table_group)
	{
		if (1 != hk.history_global)
			return;

		least_ts = now - hk.history;
	}
	else if (ZBX_RECALC_TIME_PERIOD_TRENDS == table_group)
	{
		if (1 != hk.trends_global)
			return;

		least_ts = now - hk.trends + 1;
	}

	if (least_ts > *ts_from)
		*ts_from = least_ts;
#undef HK_CFG_UPDATE_INTERVAL
}

/******************************************************************************
 *                                                                            *
 * Purpose: update shared user macro cache handle acquiring new handle if     *
 *          old was null or its revision was less than user macro cache       *
 *          revision                                                          *
 *                                                                            *
 * Parameters: handle - [IN] shared user macro cache handle, can be null      *
 *                                                                            *
 * Return value: The shared user macro handle.                                *
 *                                                                            *
 ******************************************************************************/
zbx_dc_um_shared_handle_t	*zbx_dc_um_shared_handle_update(zbx_dc_um_shared_handle_t *handle)
{
	if (NULL != handle)
	{
		if (handle->um_cache == config->um_cache)
			return handle;
	}

	handle = (zbx_dc_um_shared_handle_t *)zbx_malloc(NULL, sizeof(zbx_dc_um_shared_handle_t));
	handle->refcount = 1;
	handle->um_cache = NULL;

	return handle;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reacquire user macro cache if it has been updated                 *
 *                                                                            *
 * Return value: SUCCEED - a new user macro cache handle was acquired         *
 *               FAIL    - no need to acquire new user macro cache handle     *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_um_shared_handle_reacquire(zbx_dc_um_shared_handle_t *old_handle, zbx_dc_um_shared_handle_t *new_handle)
{
	if (old_handle == new_handle)
		return FAIL;

	WRLOCK_CACHE;

	if (NULL != old_handle)
	{
		if (0 == --old_handle->refcount)
		{
			um_cache_release(old_handle->um_cache);
			zbx_free(old_handle);
		}
	}

	config->um_cache->refcount++;
	new_handle->um_cache = config->um_cache;

	UNLOCK_CACHE;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release shared user macro cache handle                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_um_shared_handle_release(zbx_dc_um_shared_handle_t *handle)
{
	if (NULL != handle)
	{
		if (0 == --handle->refcount)
		{
			WRLOCK_CACHE;

			um_cache_release(handle->um_cache);
			zbx_free(handle);

			UNLOCK_CACHE;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy shared user macro cache handle                               *
 *                                                                            *
 ******************************************************************************/
zbx_dc_um_shared_handle_t	*zbx_dc_um_shared_handle_copy(zbx_dc_um_shared_handle_t *handle)
{
	handle->refcount++;

	return handle;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update number of IT services in configuration cache               *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_set_itservices_num(int num)
{
	WRLOCK_CACHE;
	config->itservices_num = num;
	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get number of IT services in configuration cache                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_itservices_num(void)
{
	int	num;

	RDLOCK_CACHE;
	num = config->itservices_num;
	UNLOCK_CACHE;

	return num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy version from cache                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_proxy_version(zbx_uint64_t proxyid)
{
	ZBX_DC_PROXY	*dc_proxy;
	int		version;

	RDLOCK_CACHE;

	if (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&config->proxies, &proxyid)))
		version = dc_proxy->version_int;
	else
		version = 0;

	UNLOCK_CACHE;

	return version;
}
