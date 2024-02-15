/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "dbcache.h"
#define ZBX_DBCONFIG_IMPL
#include "dbconfig.h"
#include "zbxserver.h"
#include "actions.h"
#include "log.h"

static void	dc_get_history_sync_host(zbx_history_sync_host_t *dst_host, const ZBX_DC_HOST *src_host,
		unsigned int mode)
{
	const ZBX_DC_HOST_INVENTORY	*host_inventory;

	dst_host->hostid = src_host->hostid;
	dst_host->proxy_hostid = src_host->proxy_hostid;
	dst_host->status = src_host->status;

	strscpy(dst_host->host, src_host->host);

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

	dst_item->itemid = src_item->itemid;
	dst_item->type = src_item->type;
	dst_item->value_type = src_item->value_type;

	dst_item->state = src_item->state;
	dst_item->lastlogsize = src_item->lastlogsize;
	dst_item->mtime = src_item->mtime;
	dst_item->flags = src_item->flags;

	if ('\0' != *src_item->error)
		dst_item->error = zbx_strdup(NULL, src_item->error);
	else
		dst_item->error = NULL;

	dst_item->inventory_link = src_item->inventory_link;
	dst_item->valuemapid = src_item->valuemapid;
	dst_item->status = src_item->status;

	dst_item->history = src_item->history;
	dst_item->history_sec = src_item->history_sec;

	strscpy(dst_item->key_orig, src_item->key);

	switch (src_item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			numitem = src_item->numitem;

			dst_item->trends = numitem->trends;
			dst_item->trends_sec = numitem->trends_sec;

			if ('\0' != *numitem->units)
				dst_item->units = zbx_strdup(NULL, numitem->units);
			else
				dst_item->units = NULL;
			break;
		default:
			dst_item->trends = 0;
			dst_item->trends_sec = 0;
			dst_item->units = NULL;
	}

	dst_item->has_trigger = (ZBX_DC_ITEM_UPDATE_TRIGGER_NONE == src_item->update_triggers ? 0 : 1);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get item with specified ID                                        *
 *                                                                            *
 * Parameters: items    - [OUT] pointer to DC_ITEM structures                 *
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
	}

	UNLOCK_CACHE_CONFIG_HISTORY;
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
 * Parameters: functions   - [OUT] pointer to DC_FUNCTION structures          *
 *             functionids - [IN] array of function IDs                       *
 *             errcodes    - [OUT] SUCCEED if item found, otherwise FAIL      *
 *             num         - [IN] number of elements                          *
 *                                                                            *
 * Comments: Data is retrieved using history read lock that must be write     *
 *           locked only when configuration sync occurs to avoid processes    *
 *           blocking each other.                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_history_sync_get_functions_by_functionids(DC_FUNCTION *functions, zbx_uint64_t *functionids,
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
		size_t functionids_num, zbx_vector_ptr_t *item_tags)
{
	const ZBX_DC_FUNCTION	*dc_function;
	size_t			i;

	RDLOCK_CACHE_CONFIG_HISTORY;

	for (i = 0; i < functionids_num; i++)
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
		zbx_vector_ptr_t *trigger_order, const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs,
		int itemids_num)
{
	int			i, j, found;
	const ZBX_DC_ITEM	*dc_item;
	const ZBX_DC_TRIGGER	*dc_trigger;
	DC_TRIGGER		*trigger;

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
			trigger = (DC_TRIGGER *)DCfind_id(trigger_info, dc_trigger->triggerid, sizeof(DC_TRIGGER),
					&found);

			if (0 == found)
			{
				DCget_trigger(trigger, dc_trigger);
				zbx_vector_ptr_append(trigger_order, trigger);
			}

			/* copy latest change timestamp */

			if (trigger->timespec.sec < timespecs[i].sec ||
					(trigger->timespec.sec == timespecs[i].sec &&
					trigger->timespec.ns < timespecs[i].ns))
			{
				/* zbx_dc_config_history_sync_get_triggers_by_itemids() function is called during trigger processing */
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
	dst_host->proxy_hostid = src_host->proxy_hostid;
	dst_host->status = src_host->status;

	if (ZBX_ITEM_GET_HOST & mode)
		strscpy(dst_host->host, src_host->host);

	if (ZBX_ITEM_GET_HOSTNAME & mode)
		zbx_strlcpy_utf8(dst_host->name, src_host->name, sizeof(dst_host->name));

	if (ZBX_ITEM_GET_HOSTINFO & mode)
	{
		dst_host->tls_accept = src_host->tls_accept;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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

	strscpy(dst_item->key_orig, src_item->key);

	dst_item->itemid = src_item->itemid;
	dst_item->flags = src_item->flags;
	dst_item->key = NULL;

	switch (src_item->value_type)
	{
		case ITEM_VALUE_TYPE_LOG:
			if (NULL != (logitem = (ZBX_DC_LOGITEM *)zbx_hashset_search(&config->logitems,
					&src_item->itemid)))
			{
				strscpy(dst_item->logtimefmt, logitem->logtimefmt);
			}
			else
				*dst_item->logtimefmt = '\0';
			break;
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
				strscpy(dst_item->trapper_hosts, trapitem->trapper_hosts);
			}
			else
				*dst_item->trapper_hosts = '\0';
			break;
		case ITEM_TYPE_HTTPAGENT:
			if (NULL != (httpitem = (ZBX_DC_HTTPITEM *)zbx_hashset_search(&config->httpitems,
					&src_item->itemid)))
			{
				dst_item->allow_traps = httpitem->allow_traps;
				strscpy(dst_item->trapper_hosts, httpitem->trapper_hosts);
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
 * Parameters: items    - [OUT] pointer to DC_ITEM structures                 *
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
