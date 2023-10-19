/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxcacheconfig.h"
#include "dbconfig.h"
#include "zbxexpression.h"
#include "actions.h"
#include "zbx_item_constants.h"
#include "zbxdbhigh.h"
#include "zbxtagfilter.h"

ZBX_PTR_VECTOR_IMPL(connector_filter, zbx_connector_filter_t)

static void	dc_get_history_sync_host(zbx_history_sync_host_t *dst_host, const ZBX_DC_HOST *src_host,
		unsigned int mode)
{
	const ZBX_DC_HOST_INVENTORY	*host_inventory;

	dst_host->hostid = src_host->hostid;
	dst_host->proxyid = src_host->proxyid;
	dst_host->status = src_host->status;

	zbx_strscpy(dst_host->host, src_host->host);

	if (ZBX_ITEM_GET_HOSTNAME & mode)
		zbx_strlcpy_utf8(dst_host->name, src_host->name, sizeof(dst_host->name));

	if (NULL != (host_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&config->host_inventories,
			&src_host->hostid)))
	{
		dst_host->inventory_mode = (signed char)host_inventory->inventory_mode;
	}
	else
		dst_host->inventory_mode = HOST_INVENTORY_DISABLED;
}

static void	dc_get_history_sync_item(zbx_history_sync_item_t *dst_item, const ZBX_DC_ITEM *src_item)
{
	const ZBX_DC_NUMITEM	*numitem;

	dst_item->type = src_item->type;
	dst_item->value_type = src_item->value_type;

	dst_item->state = src_item->state;
	dst_item->lastlogsize = src_item->lastlogsize;
	dst_item->mtime = src_item->mtime;

	if ('\0' != *src_item->error)
		dst_item->error = zbx_strdup(NULL, src_item->error);
	else
		dst_item->error = NULL;

	dst_item->inventory_link = src_item->inventory_link;
	dst_item->valuemapid = src_item->valuemapid;
	dst_item->status = src_item->status;

	dst_item->history_period = zbx_strdup(NULL, src_item->history_period);
	dst_item->flags = src_item->flags;

	zbx_strscpy(dst_item->key_orig, src_item->key);

	switch (src_item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			numitem = (ZBX_DC_NUMITEM *)zbx_hashset_search(&config->numitems, &src_item->itemid);

			dst_item->trends_period = zbx_strdup(NULL, numitem->trends_period);

			if ('\0' != *numitem->units)
				dst_item->units = zbx_strdup(NULL, numitem->units);
			else
				dst_item->units = NULL;
			break;
		default:
			dst_item->trends_period = NULL;
			dst_item->units = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert item history/trends housekeeping period to numeric values *
 *          expanding user macros and applying global housekeeping settings   *
 *                                                                            *
 ******************************************************************************/
static void	dc_items_convert_hk_periods(const zbx_config_hk_t *config_hk, zbx_history_sync_item_t *item)
{
	if (NULL != item->trends_period)
	{
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &item->host.hostid, NULL, NULL, NULL, NULL, NULL,
				NULL, NULL, &item->trends_period, ZBX_MACRO_TYPE_COMMON, NULL, 0);

		if (SUCCEED != zbx_is_time_suffix(item->trends_period, &item->trends_sec, ZBX_LENGTH_UNLIMITED))
			item->trends_sec = ZBX_HK_PERIOD_MAX;

		if (0 != item->trends_sec && ZBX_HK_OPTION_ENABLED == config_hk->trends_global)
			item->trends_sec = config_hk->trends;

		item->trends = (0 != item->trends_sec);
	}

	if (NULL != item->history_period)
	{
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &item->host.hostid, NULL, NULL, NULL, NULL, NULL,
				NULL, NULL, &item->history_period, ZBX_MACRO_TYPE_COMMON, NULL, 0);

		if (SUCCEED != zbx_is_time_suffix(item->history_period, &item->history_sec, ZBX_LENGTH_UNLIMITED))
			item->history_sec = ZBX_HK_PERIOD_MAX;

		if (0 != item->history_sec && ZBX_HK_OPTION_ENABLED == config_hk->history_global)
			item->history_sec = config_hk->history;

		item->history = (0 != item->history_sec);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get item with specified ID                                        *
 *                                                                            *
 * Parameters: items    - [OUT] pointer to zbx_dc_item_t structures           *
 *             itemids  - [IN] array of item IDs                              *
 *             errcodes - [OUT] SUCCEED if item found, otherwise FAIL         *
 *             num      - [IN] number of elements                             *
 *             mode     - [IN] specify whether hostname should be retrieved   *
 *                                                                            *
 * Comments: Item and host is retrieved using history read lock that must be  *
 *           write locked only when configuration sync occurs to avoid        *
 *           processes blocking each other. Item can only be processed by     *
 *           one history syncer at a time, thus it is safe to read dynamic    *
 *           data such as error as no other process will update it.           *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_history_sync_get_items_by_itemids(zbx_history_sync_item_t *items, const zbx_uint64_t *itemids,
		int *errcodes, size_t num, unsigned int mode)
{
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host = NULL;
	zbx_config_hk_t		config_hk;
	zbx_dc_um_handle_t	*um_handle;

	memset(errcodes, 0, sizeof(int) * num);

	RDLOCK_CACHE_CONFIG_HISTORY;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids[i])))
		{
			errcodes[i] = FAIL;
			continue;
		}

		if (NULL == dc_host || dc_host->hostid != dc_item->hostid)
		{
			if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			{
				errcodes[i] = FAIL;
				continue;
			}
		}

		dc_get_history_sync_host(&items[i].host, dc_host, mode);
		dc_get_history_sync_item(&items[i], dc_item);

		config_hk = config->config->hk;
	}

	UNLOCK_CACHE_CONFIG_HISTORY;

	um_handle = zbx_dc_open_user_macros();

	/* avoid unnecessary allocations inside lock if there are no error or units */
	for (i = 0; i < num; i++)
	{
		if (FAIL == errcodes[i])
			continue;

		items[i].itemid = itemids[i];

		dc_items_convert_hk_periods(&config_hk, &items[i]);
	}

	zbx_dc_close_user_macros(um_handle);
}

void	zbx_dc_config_clean_history_sync_items(zbx_history_sync_item_t *items, int *errcodes, size_t num)
{
	size_t	i;

	for (i = 0; i < num; i++)
	{
		if (NULL != errcodes && SUCCEED != errcodes[i])
			continue;

		if (ITEM_VALUE_TYPE_FLOAT == items[i].value_type || ITEM_VALUE_TYPE_UINT64 == items[i].value_type)
			zbx_free(items[i].units);

		zbx_free(items[i].error);
		zbx_free(items[i].history_period);
		zbx_free(items[i].trends_period);
	}
}

void	zbx_dc_config_history_sync_unset_existing_itemids(zbx_vector_uint64_t *itemids)
{
	int	i;

	RDLOCK_CACHE_CONFIG_HISTORY;

	for (i = 0; i < itemids->values_num; i++)
	{
		if (NULL != zbx_hashset_search(&config->items, &itemids->values[i]))
			zbx_vector_uint64_remove_noorder(itemids, i--);
	}

	UNLOCK_CACHE_CONFIG_HISTORY;
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
 * Comments: Data is retrieved using history read lock that must be write     *
 *           locked only when configuration sync occurs to avoid processes    *
 *           blocking each other.                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_history_sync_get_functions_by_functionids(zbx_dc_function_t *functions, zbx_uint64_t *functionids,
		int *errcodes, size_t num)
{
	size_t			i;
	const ZBX_DC_FUNCTION	*dc_function;

	RDLOCK_CACHE_CONFIG_HISTORY;

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

	UNLOCK_CACHE_CONFIG_HISTORY;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get item tags by function IDs                                     *
 *                                                                            *
 * Parameters: functionids     - [IN] array of function IDs                   *
 *             functionids_num - [IN] number of elements                      *
 *             item_tags       - [OUT] item tags                              *
 *                                                                            *
 * Comments: Data is retrieved using history read lock that must be write     *
 *           locked only when configuration sync occurs to avoid processes    *
 *           blocking each other.                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_history_sync_get_item_tags_by_functionids(const zbx_uint64_t *functionids,
		size_t functionids_num, zbx_vector_item_tag_t *item_tags)
{
	const ZBX_DC_FUNCTION	*dc_function;

	RDLOCK_CACHE_CONFIG_HISTORY;

	for (size_t i = 0; i < functionids_num; i++)
	{
		if (NULL == (dc_function = (const ZBX_DC_FUNCTION *)zbx_hashset_search(&config->functions,
				&functionids[i])))
		{
			continue;
		}

		zbx_get_item_tags(dc_function->itemid, item_tags);
	}

	UNLOCK_CACHE_CONFIG_HISTORY;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get enabled triggers for specified items                          *
 *                                                                            *
 * Comments: Trigger is retrieved using history read lock that must be        *
 *           write locked only when configuration sync occurs to avoid        *
 *           processes blocking each other. Trigger can only be processed by  *
 *           one process at a time, thus it is safe to read dynamic data such *
 *           as error as no other process will update it.                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_history_sync_get_triggers_by_itemids(zbx_hashset_t *trigger_info,
		zbx_vector_dc_trigger_t *trigger_order, const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs,
		int itemids_num)
{
	int			i, j, found;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_TRIGGER	*dc_trigger;
	zbx_dc_trigger_t	*trigger;

	RDLOCK_CACHE_CONFIG_HISTORY;

	for (i = 0; i < itemids_num; i++)
	{
		/* skip items which are not in configuration cache and items without triggers */

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids[i])) ||
				NULL == dc_item->triggers)
		{
			continue;
		}

		/* process all triggers for the specified item */

		for (j = 0; NULL != (dc_trigger = dc_item->triggers[j]); j++)
		{
			if (TRIGGER_STATUS_ENABLED != dc_trigger->status)
				continue;

			/* find trigger by id or create a new record in hashset if not found */
			trigger = (zbx_dc_trigger_t *)DCfind_id(trigger_info, dc_trigger->triggerid, sizeof(zbx_dc_trigger_t),
					&found);

			if (0 == found)
			{
				DCget_trigger(trigger, dc_trigger, ZBX_TRIGGER_GET_ALL);
				zbx_vector_dc_trigger_append(trigger_order, trigger);
			}

			/* copy latest change timestamp */

			if (trigger->timespec.sec < timespecs[i].sec ||
					(trigger->timespec.sec == timespecs[i].sec &&
					trigger->timespec.ns < timespecs[i].ns))
			{
				/* DCconfig_get_triggers_by_itemids() function is called during trigger processing */
				/* when syncing history cache. A trigger cannot be processed by two syncers at the */
				/* same time, so it is safe to update trigger timespec within read lock.           */
				trigger->timespec = timespecs[i];
			}
		}
	}

	UNLOCK_CACHE_CONFIG_HISTORY;
}

/******************************************************************************
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
	zbx_condition_t			*condition;
	zbx_dc_action_condition_t	*dc_condition;

	zbx_vector_ptr_reserve(conditions, (size_t)dc_action->conditions.values_num);

	for (i = 0; i < dc_action->conditions.values_num; i++)
	{
		dc_condition = (zbx_dc_action_condition_t *)dc_action->conditions.values[i];

		condition = (zbx_condition_t *)zbx_malloc(NULL, sizeof(zbx_condition_t));

		condition->conditionid = dc_condition->conditionid;
		condition->actionid = dc_action->actionid;
		condition->conditiontype = dc_condition->conditiontype;
		condition->op = dc_condition->op;
		condition->value = zbx_strdup(NULL, dc_condition->value);
		condition->value2 = zbx_strdup(NULL, dc_condition->value2);
		zbx_vector_uint64_create(&condition->eventids);

		zbx_vector_ptr_append(conditions, condition);
	}
}

/******************************************************************************
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
 *           zbx_action_eval_free() function later.                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_history_sync_get_actions_eval(zbx_vector_ptr_t *actions, unsigned char opflags)
{
	const zbx_dc_action_t		*dc_action;
	zbx_hashset_iter_t		iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	RDLOCK_CACHE_CONFIG_HISTORY;

	zbx_hashset_iter_reset(&config->actions, &iter);

	while (NULL != (dc_action = (const zbx_dc_action_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 != (opflags & dc_action->opflags))
			zbx_vector_ptr_append(actions, dc_action_eval_create(dc_action));
	}

	UNLOCK_CACHE_CONFIG_HISTORY;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() actions:%d", __func__, actions->values_num);
}

/*
 *
 * The following functions are used to get data from configuration cache
 * using CONFIG_HISTORY lock when handling incoming data from active agent,
 * sender or proxy.
 *
 */

static void	dc_get_history_recv_host(zbx_history_recv_host_t *dst_host, const ZBX_DC_HOST *src_host,
		unsigned int mode)
{
	dst_host->hostid = src_host->hostid;
	dst_host->proxyid = src_host->proxyid;
	dst_host->status = src_host->status;

	if (ZBX_ITEM_GET_HOST & mode)
		zbx_strscpy(dst_host->host, src_host->host);

	if (ZBX_ITEM_GET_HOSTNAME & mode)
		zbx_strlcpy_utf8(dst_host->name, src_host->name, sizeof(dst_host->name));

	if (ZBX_ITEM_GET_HOSTINFO & mode)
	{
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
	}
}

static void	dc_get_history_recv_item(zbx_history_recv_item_t *dst_item, const ZBX_DC_ITEM *src_item,
		unsigned int mode)
{
	const ZBX_DC_LOGITEM	*logitem;
	const ZBX_DC_TRAPITEM	*trapitem;
	const ZBX_DC_HTTPITEM	*httpitem;

	dst_item->type = src_item->type;
	dst_item->value_type = src_item->value_type;
	dst_item->state = ITEM_STATE_NORMAL;
	dst_item->status = src_item->status;

	zbx_strscpy(dst_item->key_orig, src_item->key);

	dst_item->itemid = src_item->itemid;
	dst_item->flags = src_item->flags;
	dst_item->key = NULL;

	if (ITEM_VALUE_TYPE_LOG == src_item->value_type)
	{
		if (NULL != (logitem = (ZBX_DC_LOGITEM *)zbx_hashset_search(&config->logitems, &src_item->itemid)))
			zbx_strscpy(dst_item->logtimefmt, logitem->logtimefmt);
		else
			*dst_item->logtimefmt = '\0';
	}

	if (ZBX_ITEM_GET_INTERFACE & mode)
	{
		const ZBX_DC_INTERFACE		*dc_interface;

		dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &src_item->interfaceid);

		DCget_interface(&dst_item->interface, dc_interface);
	}

	if (0 == (ZBX_ITEM_GET_TRAPPER & mode))
		return;

	switch (src_item->type)
	{
		case ITEM_TYPE_TRAPPER:
			if (NULL != (trapitem = (ZBX_DC_TRAPITEM *)zbx_hashset_search(&config->trapitems,
					&src_item->itemid)))
			{
				zbx_strscpy(dst_item->trapper_hosts, trapitem->trapper_hosts);
			}
			else
				*dst_item->trapper_hosts = '\0';
			break;
		case ITEM_TYPE_HTTPAGENT:
			if (NULL != (httpitem = (ZBX_DC_HTTPITEM *)zbx_hashset_search(&config->httpitems,
					&src_item->itemid)))
			{
				dst_item->allow_traps = httpitem->allow_traps;
				zbx_strscpy(dst_item->trapper_hosts, httpitem->trapper_hosts);
			}
			else
			{
				dst_item->allow_traps = 0;
				*dst_item->trapper_hosts = '\0';
			}
			break;
		default:
			/* nothing to do */;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve item maintenances information from configuration cache   *
 *                                                                            *
 * Parameters: items            - [OUT] pointer to array of structures        *
 *             errcodes         - [IN/OUT] SUCCEED if record located, FAIL    *
 *                                         otherwise                          *
 *             num              - [IN] number of elements in items, keys,     *
 *                                     errcodes                               *
 *             maintenances_num - [IN] maintenances count                     *
 *                                                                            *
 * Comments: Maintenances can be dynamically updated by timer processes that  *
 *           currently only lock configuration cache.                         *
 *                                                                            *
 ******************************************************************************/
static void	dc_get_history_recv_item_maintenances(zbx_history_recv_item_t *items, int *errcodes, size_t num,
		int maintenances_num)
{
	size_t			i;
	const ZBX_DC_HOST	*dc_host = NULL;

	if (0 == maintenances_num)
	{
		for (i = 0; i < num; i++)
		{
			if (FAIL == errcodes[i])
				continue;

			items[i].host.maintenance_status = HOST_MAINTENANCE_STATUS_OFF;
			items[i].host.maintenance_type = 0;
			items[i].host.maintenance_from = 0;
		}

		return;
	}

	RDLOCK_CACHE;

	for (i = 0; i < num; i++)
	{
		if (FAIL == errcodes[i])
			continue;

		if (NULL == dc_host || dc_host->hostid != items[i].host.hostid)
		{
			if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts,
					&items[i].host.hostid)))
			{
				errcodes[i] = FAIL;
				continue;
			}
		}

		items[i].host.maintenance_status = dc_host->maintenance_status;
		items[i].host.maintenance_type = dc_host->maintenance_type;
		items[i].host.maintenance_from = dc_host->maintenance_from;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: locate item in configuration cache by host and key                *
 *                                                                            *
 * Parameters: items    - [OUT] pointer to array of structures                *
 *             keys     - [IN] list of item keys with host names              *
 *             errcodes - [OUT] SUCCEED if record located and FAIL otherwise  *
 *             num      - [IN] number of elements in items, keys, errcodes    *
 *                                                                            *
 * Comments: Item and host is retrieved using history read lock that must be  *
 *           write locked only when configuration sync occurs to avoid        *
 *           processes blocking each other.                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_history_recv_get_items_by_keys(zbx_history_recv_item_t *items, const zbx_host_key_t *keys,
		int *errcodes, size_t num)
{
	int			maintenances_num;
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host;

	memset(errcodes, 0, sizeof(int) * num);

	RDLOCK_CACHE_CONFIG_HISTORY;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_host = DCfind_host(keys[i].host)) ||
				NULL == (dc_item = DCfind_item(dc_host->hostid, keys[i].key)))
		{
			errcodes[i] = FAIL;
			continue;
		}

		dc_get_history_recv_host(&items[i].host, dc_host, ZBX_ITEM_GET_DEFAULT);
		dc_get_history_recv_item(&items[i], dc_item, ZBX_ITEM_GET_DEFAULT);
	}

	maintenances_num = config->maintenances.num_data;
	UNLOCK_CACHE_CONFIG_HISTORY;

	dc_get_history_recv_item_maintenances(items, errcodes, num, maintenances_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get item with specified ID                                        *
 *                                                                            *
 * Parameters: items    - [OUT] pointer to zbx_dc_item_t structures           *
 *             itemids  - [IN] array of item IDs                              *
 *             errcodes - [OUT] SUCCEED if item found, otherwise FAIL         *
 *             num      - [IN] number of elements                             *
 *             mode     - [IN] mode of usage to avoid retrieving unnecessary  *
 *                             information                                    *
 *                                                                            *
 * Comments: Item and host is retrieved using history read lock that must be  *
 *           write locked only when configuration sync occurs to avoid        *
 *           processes blocking each other.                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_history_recv_get_items_by_itemids(zbx_history_recv_item_t *items, const zbx_uint64_t *itemids,
		int *errcodes, size_t num, unsigned int mode)
{
	int			maintenances_num;
	size_t			i;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_HOST	*dc_host = NULL;

	memset(errcodes, 0, sizeof(int) * num);

	RDLOCK_CACHE_CONFIG_HISTORY;

	for (i = 0; i < num; i++)
	{
		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &itemids[i])))
		{
			errcodes[i] = FAIL;
			continue;
		}

		if (NULL == dc_host || dc_host->hostid != dc_item->hostid)
		{
			if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			{
				errcodes[i] = FAIL;
				continue;
			}
		}

		dc_get_history_recv_host(&items[i].host, dc_host, mode);
		dc_get_history_recv_item(&items[i], dc_item, mode);
	}

	maintenances_num = config->maintenances.num_data;
	UNLOCK_CACHE_CONFIG_HISTORY;

	dc_get_history_recv_item_maintenances(items, errcodes, num, maintenances_num);
}

/******************************************************************************
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
void	zbx_dc_items_update_nextcheck(zbx_history_recv_item_t *items, zbx_agent_value_t *values, int *errcodes,
		size_t values_num)
{
	size_t			i;
	ZBX_DC_ITEM		*dc_item;
	ZBX_DC_HOST		*dc_host;
	ZBX_DC_INTERFACE	*dc_interface;

	RDLOCK_CACHE;

	for (i = 0; i < values_num; i++)
	{
		if (FAIL == errcodes[i])
			continue;

		/* update nextcheck for items that are counted in queue for monitoring purposes */
		if (FAIL == zbx_is_counted_in_item_queue(items[i].type, items[i].key_orig))
			continue;

		if (NULL == (dc_item = (ZBX_DC_ITEM *)zbx_hashset_search(&config->items, &items[i].itemid)))
			continue;

		if (ITEM_STATUS_ACTIVE != dc_item->status)
			continue;

		if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
			continue;

		if (HOST_STATUS_MONITORED != dc_host->status)
			continue;

		if (ZBX_LOC_NOWHERE != dc_item->location)
			continue;

		dc_interface = (ZBX_DC_INTERFACE *)zbx_hashset_search(&config->interfaces, &dc_item->interfaceid);

		/* update nextcheck for items that are counted in queue for monitoring purposes */
		DCitem_nextcheck_update(dc_item, dc_interface, ZBX_ITEM_COLLECTED, values[i].ts.sec,
				NULL);
	}

	UNLOCK_CACHE;
}

#define ZBX_CONNECTOR_STATUS_ENABLED	1

void	zbx_dc_config_history_sync_get_connector_filters(zbx_vector_connector_filter_t *connector_filters_history,
		zbx_vector_connector_filter_t *connector_filters_events)
{
#define ZBX_CONNECTOR_DATA_TYPE_HISTORY	0

	zbx_dc_connector_t	*dc_connector;
	zbx_hashset_iter_t	iter;

	RDLOCK_CACHE_CONFIG_HISTORY;

	zbx_hashset_iter_reset(&config->connectors, &iter);
	while (NULL != (dc_connector = (zbx_dc_connector_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_connector_filter_t		connector_filter;
		zbx_vector_connector_filter_t	*connector_filter_dest;
		int				i;

		if (ZBX_CONNECTOR_STATUS_ENABLED != dc_connector->status)
			continue;

		if (dc_connector->data_type == ZBX_CONNECTOR_DATA_TYPE_HISTORY)
		{
			if (NULL == connector_filters_history)
				continue;

			connector_filter_dest = connector_filters_history;
		}
		else
			connector_filter_dest = connector_filters_events;

		connector_filter.connectorid = dc_connector->connectorid;
		connector_filter.tags_evaltype = dc_connector->tags_evaltype;
		zbx_vector_match_tags_create(&connector_filter.connector_tags);

		if (0 != dc_connector->tags.values_num)
		{
			zbx_vector_match_tags_reserve(&connector_filter.connector_tags,
					(size_t)dc_connector->tags.values_num);

			for (i = 0; i < dc_connector->tags.values_num; i++)
			{
				zbx_match_tag_t	*connector_tag;

				connector_tag = (zbx_match_tag_t *)zbx_malloc(NULL, sizeof(*connector_tag));

				connector_tag->tag = zbx_strdup(NULL, dc_connector->tags.values[i]->tag);
				connector_tag->value = zbx_strdup(NULL, dc_connector->tags.values[i]->value);
				connector_tag->op = dc_connector->tags.values[i]->op;

				zbx_vector_match_tags_append(&connector_filter.connector_tags, connector_tag);
			}
		}

		zbx_vector_match_tags_sort(&connector_filter.connector_tags, zbx_compare_match_tags);

		zbx_vector_connector_filter_append(connector_filter_dest, connector_filter);
	}

	UNLOCK_CACHE_CONFIG_HISTORY;
#undef ZBX_CONNECTOR_DATA_TYPE_HISTORY
}

void	zbx_connector_filter_free(zbx_connector_filter_t connector_filter)
{
	zbx_vector_match_tags_clear_ext(&connector_filter.connector_tags, zbx_match_tag_free);
	zbx_vector_match_tags_destroy(&connector_filter.connector_tags);
}

static void	substitute_orig_unmasked(const char *orig, char **data)
{
	if (NULL == strstr(orig, "{$"))
		return;

	*data = zbx_strdup(*data, orig);
	zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			data, ZBX_MACRO_TYPE_COMMON, NULL, 0);
}

static void	substitute_orig(const char *orig, char **data)
{
	if (NULL == strstr(orig, "{$"))
		return;

	*data = zbx_strdup(*data, orig);
	zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, data,
			ZBX_MACRO_TYPE_COMMON, NULL, 0);
}

void	zbx_dc_config_history_sync_get_connectors(zbx_hashset_t *connectors, zbx_hashset_iter_t *connector_iter,
		zbx_uint64_t *config_revision, zbx_uint64_t *connector_revision, zbx_clean_func_t data_point_link_clean)
{
	zbx_dc_connector_t	*dc_connector;
	zbx_connector_t		*connector;
	zbx_hashset_iter_t	iter;
	int			connectors_updated = FAIL, global_macro_updated = FAIL;
	zbx_uint64_t		global_revision;

	if (config->revision.config == *config_revision)
		return;

	global_revision = *config_revision;

	RDLOCK_CACHE_CONFIG_HISTORY;

	if (config->revision.connector != *connector_revision)
	{
		zbx_hashset_iter_reset(&config->connectors, &iter);
		while (NULL != (dc_connector = (zbx_dc_connector_t *)zbx_hashset_iter_next(&iter)))
		{
			if (ZBX_CONNECTOR_STATUS_ENABLED != dc_connector->status)
				continue;

			if (NULL == (connector = (zbx_connector_t *)zbx_hashset_search(connectors,
					&dc_connector->connectorid)))
			{
				zbx_connector_t	connector_local = {.connectorid = dc_connector->connectorid};

				connector = (zbx_connector_t *)zbx_hashset_insert(connectors, &connector_local,
						sizeof(connector_local));
				zbx_list_create(&connector->data_point_link_queue);

				zbx_hashset_create_ext(&connector->data_point_links, 0, ZBX_DEFAULT_UINT64_HASH_FUNC,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC, data_point_link_clean,
						ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
						ZBX_DEFAULT_MEM_FREE_FUNC);

				connector->senders = 0;
				connector->time_flush = 0;
			}

			connector->revision = config->revision.connector;
			connector->protocol = dc_connector->protocol;
			connector->data_type = dc_connector->data_type;
			connector->url_orig = zbx_strdup(connector->url_orig, dc_connector->url);
			connector->url = zbx_strdup(connector->url, dc_connector->url);
			connector->max_records = dc_connector->max_records;
			connector->max_senders = dc_connector->max_senders;
			connector->timeout_orig = zbx_strdup(connector->timeout_orig, dc_connector->timeout);
			connector->timeout = zbx_strdup(connector->timeout, dc_connector->timeout);
			connector->max_attempts = dc_connector->max_attempts;
			connector->token_orig = zbx_strdup(connector->token_orig, dc_connector->token);
			connector->token = zbx_strdup(connector->token, dc_connector->token);
			connector->http_proxy_orig = zbx_strdup(connector->http_proxy_orig, dc_connector->http_proxy);
			connector->http_proxy = zbx_strdup(connector->http_proxy, dc_connector->http_proxy);
			connector->authtype = dc_connector->authtype;
			connector->username_orig = zbx_strdup(connector->username_orig, dc_connector->username);
			connector->username = zbx_strdup(connector->username, dc_connector->username);
			connector->password_orig = zbx_strdup(connector->password_orig, dc_connector->password);
			connector->password = zbx_strdup(connector->password, dc_connector->password);
			connector->verify_peer = dc_connector->verify_peer;
			connector->verify_host = dc_connector->verify_host;
			connector->ssl_cert_file_orig = zbx_strdup(connector->ssl_cert_file_orig,
					dc_connector->ssl_cert_file);
			connector->ssl_cert_file = zbx_strdup(connector->ssl_cert_file, dc_connector->ssl_cert_file);
			connector->ssl_key_file_orig = zbx_strdup(connector->ssl_key_file_orig,
					dc_connector->ssl_key_file);
			connector->ssl_key_file = zbx_strdup(connector->ssl_key_file, dc_connector->ssl_key_file);
			connector->ssl_key_password_orig = zbx_strdup(connector->ssl_key_password_orig,
					dc_connector->ssl_key_password);
			connector->ssl_key_password = zbx_strdup(connector->ssl_key_password,
					dc_connector->ssl_key_password);
		}

		*connector_revision = config->revision.connector;
		connectors_updated = SUCCEED;
	}

	if (SUCCEED != um_cache_get_host_revision(config->um_cache, 0, &global_revision))
		global_revision = 0;

	if (global_revision > *config_revision)
		global_macro_updated = SUCCEED;

	*config_revision = config->revision.config;

	UNLOCK_CACHE_CONFIG_HISTORY;

	if (SUCCEED == connectors_updated)
	{
		zbx_hashset_iter_reset(connectors, &iter);
		while (NULL != (connector = (zbx_connector_t *)zbx_hashset_iter_next(&iter)))
		{
			if (connector->revision != *connector_revision)
				zbx_hashset_iter_remove(&iter);
		}

		zbx_hashset_iter_reset(connectors, connector_iter);
	}

	if (SUCCEED == global_macro_updated || SUCCEED == connectors_updated)
	{
		zbx_dc_um_handle_t	*um_handle;

		um_handle = zbx_dc_open_user_macros();

		zbx_hashset_iter_reset(connectors, &iter);
		while (NULL != (connector = (zbx_connector_t *)zbx_hashset_iter_next(&iter)))
		{
			substitute_orig_unmasked(connector->url_orig, &connector->url);
			substitute_orig_unmasked(connector->token_orig, &connector->token);
			substitute_orig_unmasked(connector->http_proxy_orig, &connector->http_proxy);
			substitute_orig_unmasked(connector->username_orig, &connector->username);
			substitute_orig_unmasked(connector->password_orig, &connector->password);
			substitute_orig_unmasked(connector->ssl_cert_file_orig, &connector->ssl_cert_file);
			substitute_orig_unmasked(connector->ssl_key_file_orig, &connector->ssl_key_file);
			substitute_orig_unmasked(connector->ssl_key_password_orig, &connector->ssl_key_password);

			substitute_orig(connector->timeout_orig, &connector->timeout);
		}

		zbx_dc_close_user_macros(um_handle);
	}
}
#undef ZBX_CONNECTOR_STATUS_ENABLED
