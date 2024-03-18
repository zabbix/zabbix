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

#include "cachehistory_server.h"

#include "zbxcachehistory.h"
#include "zbxmodules.h"
#include "zbxexport.h"
#include "zbxnix.h"
#include "zbxconnector.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxhistory.h"
#include "zbxshmem.h"
#include "zbxtime.h"
#include "zbxtrends.h"
#include "zbx_item_constants.h"
#include "zbx_host_constants.h"
#include "zbxexpression.h"

static void	DCmodule_sync_history(int history_float_num, int history_integer_num, int history_string_num,
		int history_text_num, int history_log_num, ZBX_HISTORY_FLOAT *history_float,
		ZBX_HISTORY_INTEGER *history_integer, ZBX_HISTORY_STRING *history_string,
		ZBX_HISTORY_TEXT *history_text, ZBX_HISTORY_LOG *history_log)
{
	if (0 != history_float_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing float history data with modules...");

		for (i = 0; NULL != history_float_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_float_cbs[i].module->name);
			history_float_cbs[i].history_float_cb(history_float, history_float_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d float values with modules", history_float_num);
	}

	if (0 != history_integer_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing integer history data with modules...");

		for (i = 0; NULL != history_integer_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_integer_cbs[i].module->name);
			history_integer_cbs[i].history_integer_cb(history_integer, history_integer_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d integer values with modules", history_integer_num);
	}

	if (0 != history_string_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing string history data with modules...");

		for (i = 0; NULL != history_string_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_string_cbs[i].module->name);
			history_string_cbs[i].history_string_cb(history_string, history_string_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d string values with modules", history_string_num);
	}

	if (0 != history_text_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing text history data with modules...");

		for (i = 0; NULL != history_text_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_text_cbs[i].module->name);
			history_text_cbs[i].history_text_cb(history_text, history_text_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d text values with modules", history_text_num);
	}

	if (0 != history_log_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing log history data with modules...");

		for (i = 0; NULL != history_log_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_log_cbs[i].module->name);
			history_log_cbs[i].history_log_cb(history_log, history_log_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d log values with modules", history_log_num);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare history data to share them with loadable modules, sort    *
 *          data by type skipping low-level discovery data, meta information  *
 *          updates and notsupported items                                    *
 *                                                                            *
 * Parameters: history            - [IN] array of history data                *
 *             history_num        - [IN] number of history structures         *
 *             history_<type>     - [OUT] array of historical data of a       *
 *                                  specific data type                        *
 *             history_<type>_num - [OUT] number of values of a specific      *
 *                                  data type                                 *
 *                                                                            *
 ******************************************************************************/
static void	DCmodule_prepare_history(zbx_dc_history_t *history, int history_num, ZBX_HISTORY_FLOAT *history_float,
		int *history_float_num, ZBX_HISTORY_INTEGER *history_integer, int *history_integer_num,
		ZBX_HISTORY_STRING *history_string, int *history_string_num, ZBX_HISTORY_TEXT *history_text,
		int *history_text_num, ZBX_HISTORY_LOG *history_log, int *history_log_num)
{
	zbx_dc_history_t	*h;
	ZBX_HISTORY_FLOAT	*h_float;
	ZBX_HISTORY_INTEGER	*h_integer;
	ZBX_HISTORY_STRING	*h_string;
	ZBX_HISTORY_TEXT	*h_text;
	ZBX_HISTORY_LOG		*h_log;
	int			i;
	const zbx_log_value_t	*log;

	*history_float_num = 0;
	*history_integer_num = 0;
	*history_string_num = 0;
	*history_text_num = 0;
	*history_log_num = 0;

	for (i = 0; i < history_num; i++)
	{
		h = &history[i];

		if (0 != (ZBX_DC_FLAGS_NOT_FOR_MODULES & h->flags))
			continue;

		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				if (NULL == history_float_cbs)
					continue;

				h_float = &history_float[(*history_float_num)++];
				h_float->itemid = h->itemid;
				h_float->clock = h->ts.sec;
				h_float->ns = h->ts.ns;
				h_float->value = h->value.dbl;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				if (NULL == history_integer_cbs)
					continue;

				h_integer = &history_integer[(*history_integer_num)++];
				h_integer->itemid = h->itemid;
				h_integer->clock = h->ts.sec;
				h_integer->ns = h->ts.ns;
				h_integer->value = h->value.ui64;
				break;
			case ITEM_VALUE_TYPE_STR:
				if (NULL == history_string_cbs)
					continue;

				h_string = &history_string[(*history_string_num)++];
				h_string->itemid = h->itemid;
				h_string->clock = h->ts.sec;
				h_string->ns = h->ts.ns;
				h_string->value = h->value.str;
				break;
			case ITEM_VALUE_TYPE_TEXT:
				if (NULL == history_text_cbs)
					continue;

				h_text = &history_text[(*history_text_num)++];
				h_text->itemid = h->itemid;
				h_text->clock = h->ts.sec;
				h_text->ns = h->ts.ns;
				h_text->value = h->value.str;
				break;
			case ITEM_VALUE_TYPE_LOG:
				if (NULL == history_log_cbs)
					continue;

				log = h->value.log;
				h_log = &history_log[(*history_log_num)++];
				h_log->itemid = h->itemid;
				h_log->clock = h->ts.sec;
				h_log->ns = h->ts.ns;
				h_log->value = log->value;
				h_log->source = ZBX_NULL2EMPTY_STR(log->source);
				h_log->timestamp = log->timestamp;
				h_log->logeventid = log->logeventid;
				h_log->severity = log->severity;
				break;
			case ITEM_VALUE_TYPE_BIN:
			case ITEM_VALUE_TYPE_NONE:
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates what item fields must be updated                       *
 *                                                                            *
 * Parameters: item         - [IN/OUT]                                        *
 *             h            - [IN] historical data to process                 *
 *             add_event_cb - [IN]                                            *
 *                                                                            *
 * Return value: The update data. This data must be freed by the caller.      *
 *                                                                            *
 * Comments: Will generate internal events when item state switches.          *
 *                                                                            *
 ******************************************************************************/
static zbx_item_diff_t	*calculate_item_update(zbx_history_sync_item_t *item, const zbx_dc_history_t *h,
		zbx_add_event_func_t add_event_cb)
{
	zbx_uint64_t	flags = 0;
	const char	*item_error = NULL;
	zbx_item_diff_t	*diff;

	if (0 != (ZBX_DC_FLAG_META & h->flags))
	{
		if (item->lastlogsize != h->lastlogsize)
			flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE;

		if (item->mtime != h->mtime)
			flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME;
	}

	if (h->state != item->state)
	{
		flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE;

		if (ITEM_STATE_NOTSUPPORTED == h->state)
		{
			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" became not supported: %s",
					item->host.host, item->key_orig, h->value.str);

			if (NULL != add_event_cb)
			{
				add_event_cb(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, item->itemid, &h->ts, h->state,
						NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL, NULL, h->value.err);
			}

			if (0 != strcmp(ZBX_NULL2EMPTY_STR(item->error), h->value.err))
				item_error = h->value.err;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" became supported",
					item->host.host, item->key_orig);

			if (NULL != add_event_cb)
			{
				/* we know it's EVENT_OBJECT_ITEM because LLDRULE that becomes */
				/* supported is handled in lld_process_discovery_rule()        */
				add_event_cb(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, item->itemid, &h->ts, h->state,
						NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL, NULL, NULL);
			}

			item_error = "";
		}
	}
	else if (ITEM_STATE_NOTSUPPORTED == h->state && 0 != strcmp(ZBX_NULL2EMPTY_STR(item->error), h->value.err))
	{
		zabbix_log(LOG_LEVEL_WARNING, "error reason for \"%s:%s\" changed: %s", item->host.host,
				item->key_orig, h->value.err);

		item_error = h->value.err;
	}

	if (NULL != item_error)
		flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;

	if (0 == flags)
		return NULL;

	diff = (zbx_item_diff_t *)zbx_malloc(NULL, sizeof(zbx_item_diff_t));
	diff->itemid = item->itemid;
	diff->flags = flags;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE & flags))
		diff->lastlogsize = h->lastlogsize;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME & flags))
		diff->mtime = h->mtime;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE & flags))
	{
		diff->state = h->state;
		item->state = h->state;
	}

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR & flags))
		diff->error = item_error;

	return diff;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets history data to notsupported                                 *
 *                                                                            *
 * Parameters: history  - [IN] the history data                               *
 *             errmsg   - [IN] the error message                              *
 *                                                                            *
 * Comments: The error message is stored directly and freed with when history *
 *           data is cleaned.                                                 *
 *                                                                            *
 ******************************************************************************/
static void	dc_history_set_error(zbx_dc_history_t *hdata, char *errmsg)
{
	zbx_dc_history_clean_value(hdata);
	hdata->value.err = errmsg;
	hdata->state = ITEM_STATE_NOTSUPPORTED;
	hdata->flags |= ZBX_DC_FLAG_UNDEF;
}



/******************************************************************************
 *                                                                            *
 * Purpose: sets history data value                                           *
 *                                                                            *
 * Parameters: hdata      - [IN/OUT] the history data                         *
 *             value_type - [IN] the item value type                          *
 *             value      - [IN] the value to set                             *
 *                                                                            *
 ******************************************************************************/
static void	dc_history_set_value(zbx_dc_history_t *hdata, unsigned char value_type, zbx_variant_t *value)
{
	char	*errmsg = NULL;

	if (FAIL == zbx_variant_to_value_type(value, value_type, &errmsg))
	{
		dc_history_set_error(hdata, errmsg);
		return;
	}

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_dc_history_clean_value(hdata);
			hdata->value.dbl = value->data.dbl;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_dc_history_clean_value(hdata);
			hdata->value.ui64 = value->data.ui64;
			break;
		case ITEM_VALUE_TYPE_STR:
			zbx_dc_history_clean_value(hdata);
			hdata->value.str = value->data.str;
			hdata->value.str[zbx_db_strlen_n(hdata->value.str, ZBX_HISTORY_STR_VALUE_LEN)] = '\0';
			break;
		case ITEM_VALUE_TYPE_TEXT:
			zbx_dc_history_clean_value(hdata);
			hdata->value.str = value->data.str;
			hdata->value.str[zbx_db_strlen_n(hdata->value.str, ZBX_HISTORY_TEXT_VALUE_LEN)] = '\0';
			break;
		case ITEM_VALUE_TYPE_BIN:
			zbx_dc_history_clean_value(hdata);
			hdata->value.str = value->data.str;
			hdata->value.str[zbx_db_strlen_n(hdata->value.str, ZBX_HISTORY_BIN_VALUE_LEN)] = '\0';
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (ITEM_VALUE_TYPE_LOG != hdata->value_type)
			{
				zbx_dc_history_clean_value(hdata);
				hdata->value.log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));
				memset(hdata->value.log, 0, sizeof(zbx_log_value_t));
			}
			hdata->value.log->value = value->data.str;
			hdata->value.str[zbx_db_strlen_n(hdata->value.str, ZBX_HISTORY_LOG_VALUE_LEN)] = '\0';
			break;
		case ITEM_VALUE_TYPE_NONE:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	hdata->value_type = value_type;
	zbx_variant_set_none(value);
}


/******************************************************************************
 *                                                                            *
 * Purpose: normalize item value by performing truncation of long text        *
 *          values and changes value format according to the item value type  *
 *                                                                            *
 * Parameters: item          - [IN] the item                                  *
 *             hdata         - [IN/OUT] the historical data to process        *
 *                                                                            *
 ******************************************************************************/
static void	normalize_item_value(const zbx_history_sync_item_t *item, zbx_dc_history_t *hdata)
{
	char		*logvalue;
	zbx_variant_t	value_var;

	if (0 != (hdata->flags & ZBX_DC_FLAG_NOVALUE))
		return;

	if (ITEM_STATE_NOTSUPPORTED == hdata->state)
		return;

	if (0 == (hdata->flags & ZBX_DC_FLAG_NOHISTORY))
		hdata->ttl = item->history_sec;

	if (item->value_type == hdata->value_type)
	{
		/* truncate text based values if necessary */
		switch (hdata->value_type)
		{
			case ITEM_VALUE_TYPE_STR:
				hdata->value.str[zbx_db_strlen_n(hdata->value.str, ZBX_HISTORY_STR_VALUE_LEN)] = '\0';
				break;
			case ITEM_VALUE_TYPE_TEXT:
				hdata->value.str[zbx_db_strlen_n(hdata->value.str, ZBX_HISTORY_TEXT_VALUE_LEN)] = '\0';
				break;
			case ITEM_VALUE_TYPE_LOG:
				logvalue = hdata->value.log->value;
				logvalue[zbx_db_strlen_n(logvalue, ZBX_HISTORY_LOG_VALUE_LEN)] = '\0';
				break;
			case ITEM_VALUE_TYPE_BIN:
				/* in history cache binary values are stored as ITEM_VALUE_TYPE_STR */
				THIS_SHOULD_NEVER_HAPPEN;
				break;
			case ITEM_VALUE_TYPE_FLOAT:
				if (FAIL == zbx_validate_value_dbl(hdata->value.dbl))
				{
					char	buffer[ZBX_MAX_DOUBLE_LEN + 1];

					dc_history_set_error(hdata, zbx_dsprintf(NULL,
							"Value %s is too small or too large.",
							zbx_print_double(buffer, sizeof(buffer), hdata->value.dbl)));
				}
				break;
		}
		return;
	}

	switch (hdata->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_variant_set_dbl(&value_var, hdata->value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_variant_set_ui64(&value_var, hdata->value.ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_variant_set_str(&value_var, hdata->value.str);
			hdata->value.str = NULL;
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_variant_set_str(&value_var, hdata->value.log->value);
			hdata->value.log->value = NULL;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return;
	}

	dc_history_set_value(hdata, item->value_type, &value_var);
	zbx_variant_clear(&value_var);
}

static void	DCinventory_value_add(zbx_vector_ptr_t *inventory_values, const zbx_history_sync_item_t *item,
		zbx_dc_history_t *h)
{
	char			value[MAX_BUFFER_LEN];
	const char		*inventory_field;
	zbx_inventory_value_t	*inventory_value;

	if (ITEM_STATE_NOTSUPPORTED == h->state)
		return;

	if (HOST_INVENTORY_AUTOMATIC != item->host.inventory_mode)
		return;

	if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags) ||
			NULL == (inventory_field = zbx_db_get_inventory_field(item->inventory_link)))
	{
		return;
	}

	switch (h->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_print_double(value, sizeof(value), h->value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(value, sizeof(value), ZBX_FS_UI64, h->value.ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_strscpy(value, h->value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_NONE:
		default:
			return;
	}

	zbx_format_value(value, sizeof(value), item->valuemapid, ZBX_NULL2EMPTY_STR(item->units), h->value_type);

	inventory_value = (zbx_inventory_value_t *)zbx_malloc(NULL, sizeof(zbx_inventory_value_t));

	inventory_value->hostid = item->host.hostid;
	inventory_value->idx = item->inventory_link - 1;
	inventory_value->field_name = inventory_field;
	inventory_value->value = zbx_strdup(NULL, value);

	zbx_vector_ptr_append(inventory_values, inventory_value);
}




/******************************************************************************
 *                                                                            *
 * Purpose: prepare history data using items from configuration cache and     *
 *          generate item changes to be applied and host inventory values to  *
 *          be added                                                          *
 *                                                                            *
 * Parameters: history             - [IN/OUT] array of history data           *
 *             itemids             - [IN] the item identifiers                *
 *                                        (used for item lookup)              *
 *             items               - [IN]                                     *
 *             errcodes            - [IN] item error codes                    *
 *             history_num         - [IN] number of history structures        *
 *             add_event_cb        - [IN]                                     *
 *             item_diff           - [OUT] the changes in item data           *
 *             inventory_values    - [OUT] the inventory values to add        *
 *             compression_age     - [IN] history compression age             *
 *             proxy_subscriptions - [IN]                                     *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_prepare_history(zbx_dc_history_t *history, zbx_history_sync_item_t *items, const int *errcodes,
		int history_num, zbx_add_event_func_t add_event_cb, zbx_vector_ptr_t *item_diff,
		zbx_vector_ptr_t *inventory_values, int compression_age, zbx_vector_uint64_pair_t *proxy_subscriptions)
{
	static time_t	last_history_discard = 0;
	time_t		now;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d", __func__, history_num);

	now = time(NULL);

	for (i = 0; i < history_num; i++)
	{
		zbx_dc_history_t	*h = &history[i];
		zbx_history_sync_item_t	*item;
		zbx_item_diff_t		*diff;

		/* discard history items that are older than compression age */
		if (0 != compression_age && h->ts.sec < compression_age)
		{
			if (SEC_PER_HOUR < (now - last_history_discard)) /* log once per hour */
			{
				zabbix_log(LOG_LEVEL_TRACE, "discarding history that is pointing to"
							" compressed history period");
				last_history_discard = now;
			}

			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		if (SUCCEED != errcodes[i])
		{
			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		item = &items[i];

		if (ITEM_STATUS_ACTIVE != item->status || HOST_STATUS_MONITORED != item->host.status)
		{
			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		if (0 == item->history)
		{
			h->flags |= ZBX_DC_FLAG_NOHISTORY;
		}
		else if (now - h->ts.sec > item->history_sec)
		{
			h->flags |= ZBX_DC_FLAG_NOHISTORY;
			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" value timestamp \"%s %s\" is outside history "
					"storage period", item->host.host, item->key_orig,
					zbx_date2str(h->ts.sec, NULL), zbx_time2str(h->ts.sec, NULL));
		}

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type || ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			if (0 == item->trends)
			{
				h->flags |= ZBX_DC_FLAG_NOTRENDS;
			}
			else if (now - h->ts.sec > item->trends_sec)
			{
				h->flags |= ZBX_DC_FLAG_NOTRENDS;
				zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" value timestamp \"%s %s\" is outside "
						"trends storage period", item->host.host, item->key_orig,
						zbx_date2str(h->ts.sec, NULL), zbx_time2str(h->ts.sec, NULL));
			}
		}
		else
			h->flags |= ZBX_DC_FLAG_NOTRENDS;

		normalize_item_value(item, h);

		/* calculate item update and update already retrieved item status for trigger calculation */
		if (NULL != (diff = calculate_item_update(item, h, add_event_cb)))
			zbx_vector_ptr_append(item_diff, diff);

		DCinventory_value_add(inventory_values, item, h);

		if (0 != item->host.proxyid && FAIL == zbx_is_item_processed_by_server(item->type, item->key_orig))
		{
			zbx_uint64_pair_t	p = {item->host.proxyid, h->ts.sec};

			zbx_vector_uint64_pair_append(proxy_subscriptions, p);
		}

		if (0 != item->has_trigger)
			h->flags |= ZBX_DC_FLAG_HASTRIGGER;
	}

	zbx_vector_ptr_sort(inventory_values, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	zbx_vector_ptr_sort(item_diff, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush history cache to database, process triggers of flushed      *
 *          and timer triggers from timer queue                               *
 *                                                                            *
 * Parameters:                                                                *
 *             values_num   - [IN/OUT] the number of synced values            *
 *             triggers_num - [IN/OUT] the number of processed timers         *
 *             events_cbs   - [IN]                                            *
 *             more         - [OUT] a flag indicating the cache emptiness:    *
 *                               ZBX_SYNC_DONE - nothing to sync, go idle     *
 *                               ZBX_SYNC_MORE - more data to sync            *
 *                                                                            *
 * Comments: This function loops syncing history values by 1k batches and     *
 *           processing timer triggers by batches of 500 triggers.            *
 *           Unless full sync is being done the loop is aborted if either     *
 *           timeout has passed or there are no more data to process.         *
 *           The last is assumed when the following is true:                  *
 *            a) history cache is empty or less than 10% of batch values were *
 *               processed (the other items were locked by triggers)          *
 *            b) less than 500 (full batch) timer triggers were processed     *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_server_history(int *values_num, int *triggers_num, const zbx_events_funcs_t *events_cbs, int *more)
{
/* the minimum processed item percentage of item candidates to continue synchronizing */
#define ZBX_HC_SYNC_MIN_PCNT	10
	static ZBX_HISTORY_FLOAT	*history_float;
	static ZBX_HISTORY_INTEGER	*history_integer;
	static ZBX_HISTORY_STRING	*history_string;
	static ZBX_HISTORY_TEXT		*history_text;
	static ZBX_HISTORY_LOG		*history_log;
	static int			module_enabled = FAIL;
	int				i, history_num, history_float_num, history_integer_num, history_string_num,
					history_text_num, history_log_num, txn_error, compression_age,
					connectors_retrieved = FAIL;
	unsigned int			item_retrieve_mode;
	time_t				sync_start;
	zbx_vector_uint64_t		triggerids ;
	zbx_vector_ptr_t		history_items, trigger_diff, item_diff, inventory_values, trigger_timers;
	zbx_vector_dc_trigger_t		trigger_order;
	zbx_vector_uint64_pair_t	trends_diff, proxy_subscriptions;
	zbx_dc_history_t		history[ZBX_HC_SYNC_MAX];
	zbx_uint64_t			trigger_itemids[ZBX_HC_SYNC_MAX];
	zbx_timespec_t			trigger_timespecs[ZBX_HC_SYNC_MAX];
	zbx_history_sync_item_t		*items = NULL;
	int				*errcodes = NULL;
	zbx_vector_uint64_t		itemids;
	zbx_hashset_t			trigger_info;
	unsigned char			*data = NULL;
	size_t				data_alloc = 0, data_offset;
	zbx_vector_connector_filter_t	connector_filters_history, connector_filters_events;

	if (NULL == history_float && NULL != history_float_cbs)
	{
		module_enabled = SUCCEED;
		history_float = (ZBX_HISTORY_FLOAT *)zbx_malloc(history_float,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_FLOAT));
	}

	if (NULL == history_integer && NULL != history_integer_cbs)
	{
		module_enabled = SUCCEED;
		history_integer = (ZBX_HISTORY_INTEGER *)zbx_malloc(history_integer,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_INTEGER));
	}

	if (NULL == history_string && NULL != history_string_cbs)
	{
		module_enabled = SUCCEED;
		history_string = (ZBX_HISTORY_STRING *)zbx_malloc(history_string,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_STRING));
	}

	if (NULL == history_text && NULL != history_text_cbs)
	{
		module_enabled = SUCCEED;
		history_text = (ZBX_HISTORY_TEXT *)zbx_malloc(history_text,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_TEXT));
	}

	if (NULL == history_log && NULL != history_log_cbs)
	{
		module_enabled = SUCCEED;
		history_log = (ZBX_HISTORY_LOG *)zbx_malloc(history_log,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_LOG));
	}

	compression_age = hc_get_history_compression_age();

	zbx_vector_connector_filter_create(&connector_filters_history);
	zbx_vector_connector_filter_create(&connector_filters_events);
	zbx_vector_ptr_create(&inventory_values);
	zbx_vector_ptr_create(&item_diff);
	zbx_vector_ptr_create(&trigger_diff);
	zbx_vector_uint64_pair_create(&trends_diff);
	zbx_vector_uint64_pair_create(&proxy_subscriptions);

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, ZBX_HC_SYNC_MAX);

	zbx_vector_ptr_create(&trigger_timers);
	zbx_vector_ptr_reserve(&trigger_timers, ZBX_HC_TIMER_MAX);

	zbx_vector_ptr_create(&history_items);
	zbx_vector_ptr_reserve(&history_items, ZBX_HC_SYNC_MAX);

	zbx_vector_dc_trigger_create(&trigger_order);
	zbx_hashset_create(&trigger_info, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_create(&itemids);

	sync_start = time(NULL);

	item_retrieve_mode = 0 == zbx_has_export_dir() ? ZBX_ITEM_GET_SYNC : ZBX_ITEM_GET_SYNC_EXPORT;

	do
	{
		int			trends_num = 0, timers_num = 0, ret = SUCCEED;
		ZBX_DC_TREND		*trends = NULL;

		*more = ZBX_SYNC_DONE;

		zbx_dbcache_lock();
		zbx_hc_pop_items(&history_items);		/* select and take items out of history cache */
		zbx_dbcache_unlock();

		if (0 != history_items.values_num)
		{
			if (0 == (history_num = zbx_dc_config_lock_triggers_by_history_items(&history_items, &triggerids)))
			{
				zbx_dbcache_lock();
				zbx_hc_push_items(&history_items);
				zbx_dbcache_unlock();
				zbx_vector_ptr_clear(&history_items);
			}
		}
		else
			history_num = 0;

		if (0 != history_num)
		{
			zbx_dc_um_handle_t	*um_handle;

			if (FAIL == connectors_retrieved)
			{
				zbx_dc_config_history_sync_get_connector_filters(&connector_filters_history,
						&connector_filters_events);

				connectors_retrieved = SUCCEED;

				if (0 != connector_filters_history.values_num)
					item_retrieve_mode = ZBX_ITEM_GET_SYNC_EXPORT;
			}

			zbx_vector_ptr_sort(&history_items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
			zbx_hc_get_item_values(history, &history_items);	/* copy item data from history cache */

			if (NULL == items)
			{
				items = (zbx_history_sync_item_t *)zbx_malloc(NULL, sizeof(zbx_history_sync_item_t) *
						(size_t)ZBX_HC_SYNC_MAX);
			}

			if (NULL == errcodes)
				errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)ZBX_HC_SYNC_MAX);

			zbx_vector_uint64_reserve(&itemids, history_num);

			for (i = 0; i < history_num; i++)
				zbx_vector_uint64_append(&itemids, history[i].itemid);

			zbx_dc_config_history_sync_get_items_by_itemids(items, itemids.values, errcodes,
					(size_t)history_num, item_retrieve_mode);

			um_handle = zbx_dc_open_user_macros();

			DCmass_prepare_history(history, items, errcodes, history_num,
					events_cbs->add_event_cb, &item_diff,
					&inventory_values, compression_age, &proxy_subscriptions);

			if (FAIL != (ret = DBmass_add_history(history, history_num)))
			{
				zbx_dc_config_items_apply_changes(&item_diff);
				DCmass_update_trends(history, history_num, &trends, &trends_num, compression_age);

				if (0 != trends_num)
					zbx_tfc_invalidate_trends(trends, trends_num);

				do
				{
					zbx_db_begin();

					DBmass_update_trends(trends, trends_num, &trends_diff);

					if (ZBX_DB_OK == (txn_error = zbx_db_commit()))
						DCupdate_trends(&trends_diff);

					zbx_vector_uint64_pair_clear(&trends_diff);
				}
				while (ZBX_DB_DOWN == txn_error);

				do
				{
					zbx_db_begin();

					DBmass_update_items(&item_diff, &inventory_values);

					if (NULL != events_cbs->process_events_cb)
					{
						/* process internal events generated by DCmass_prepare_history() */
						events_cbs->process_events_cb(NULL, NULL);
					}

					if (ZBX_DB_OK != (txn_error = zbx_db_commit()))
					{
						if (NULL != events_cbs->reset_event_recovery_cb)
							events_cbs->reset_event_recovery_cb();
					}
				}
				while (ZBX_DB_DOWN == txn_error);
			}

			zbx_dc_close_user_macros(um_handle);

			if (NULL != events_cbs->clean_events_cb)
				events_cbs->clean_events_cb();

			zbx_vector_ptr_clear_ext(&inventory_values, (zbx_clean_func_t)DCinventory_value_free);
			zbx_vector_ptr_clear_ext(&item_diff, (zbx_clean_func_t)zbx_ptr_free);
		}

		if (FAIL != ret)
		{
			/* don't process trigger timers when server is shutting down */
			if (ZBX_IS_RUNNING())
			{
				zbx_dc_get_trigger_timers(&trigger_timers, time(NULL), ZBX_HC_TIMER_SOFT_MAX,
						ZBX_HC_TIMER_MAX);
			}

			timers_num = trigger_timers.values_num;

			if (ZBX_HC_TIMER_SOFT_MAX <= timers_num)
				*more = ZBX_SYNC_MORE;

			if (0 != history_num || 0 != timers_num)
			{
				for (i = 0; i < trigger_timers.values_num; i++)
				{
					zbx_trigger_timer_t	*timer = (zbx_trigger_timer_t *)trigger_timers.values[i];

					if (0 != timer->lock)
						zbx_vector_uint64_append(&triggerids, timer->triggerid);
				}

				do
				{
					zbx_db_begin();

					recalculate_triggers(history, history_num, &itemids, items, errcodes,
							&trigger_timers, events_cbs->add_event_cb, &trigger_diff,
							trigger_itemids,
							trigger_timespecs, &trigger_info, &trigger_order);

					if (NULL != events_cbs->process_events_cb)
					{
						/* process trigger events generated by recalculate_triggers() */
						events_cbs->process_events_cb(&trigger_diff, &triggerids);
					}

					if (0 != trigger_diff.values_num)
						zbx_db_save_trigger_changes(&trigger_diff);

					if (ZBX_DB_OK == (txn_error = zbx_db_commit()))
						zbx_dc_config_triggers_apply_changes(&trigger_diff);
					else if (NULL != events_cbs->clean_events_cb)
						events_cbs->clean_events_cb();

					zbx_vector_ptr_clear_ext(&trigger_diff,
							(zbx_clean_func_t)zbx_trigger_diff_free);
				}
				while (ZBX_DB_DOWN == txn_error);

				if (ZBX_DB_OK == txn_error && NULL != events_cbs->events_update_itservices_cb)
					events_cbs->events_update_itservices_cb();
			}
		}

		if (0 != triggerids.values_num)
		{
			*triggers_num += triggerids.values_num;
			zbx_dc_config_unlock_triggers(&triggerids);
			zbx_vector_uint64_clear(&triggerids);
		}

		if (0 != trigger_timers.values_num)
		{
			zbx_dc_reschedule_trigger_timers(&trigger_timers, time(NULL));
			zbx_vector_ptr_clear(&trigger_timers);
		}

		if (0 != proxy_subscriptions.values_num)
		{
			zbx_vector_uint64_pair_sort(&proxy_subscriptions, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_dc_proxy_update_nodata(&proxy_subscriptions);
			zbx_vector_uint64_pair_clear(&proxy_subscriptions);
		}

		if (0 != history_num)
		{
			zbx_dbcache_lock();
			zbx_hc_push_items(&history_items);	/* return items to history cache */
			zbx_dbcache_set_history_num(zbx_dbcache_get_history_num() - history_num);

			if (0 != zbx_hc_queue_get_size())
			{
				/* Continue sync if enough of sync candidates were processed       */
				/* (meaning most of sync candidates are not locked by triggers).   */
				/* Otherwise better to wait a bit for other syncers to unlock      */
				/* items rather than trying and failing to sync locked items over  */
				/* and over again.                                                 */
				if (ZBX_HC_SYNC_MIN_PCNT <= history_num * 100 / history_items.values_num)
					*more = ZBX_SYNC_MORE;
			}

			zbx_dbcache_unlock();

			*values_num += history_num;
		}

		if (FAIL != ret)
		{
			int	event_export_enabled = FAIL;

			if (0 != history_num)
			{
				const zbx_dc_history_t	*phistory = NULL;
				const ZBX_DC_TREND	*ptrends = NULL;
				int			history_num_loc = 0, trends_num_loc = 0;
				int			history_export_enabled = FAIL;

				if (SUCCEED == module_enabled)
				{
					DCmodule_prepare_history(history, history_num, history_float, &history_float_num,
							history_integer, &history_integer_num, history_string,
							&history_string_num, history_text, &history_text_num, history_log,
							&history_log_num);

					DCmodule_sync_history(history_float_num, history_integer_num, history_string_num,
							history_text_num, history_log_num, history_float,
							history_integer, history_string, history_text, history_log);
				}

				if (SUCCEED == (history_export_enabled =
						zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_HISTORY)) ||
						0 != connector_filters_history.values_num)
				{
					phistory = history;
					history_num_loc = history_num;
				}

				if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS))
				{
					ptrends = trends;
					trends_num_loc = trends_num;
				}

				if (NULL != phistory || NULL != ptrends)
				{
					data_offset = 0;
					DCexport_history_and_trends(phistory, history_num_loc, &itemids, items,
							errcodes, ptrends, trends_num_loc, history_export_enabled,
							&connector_filters_history, &data, &data_alloc, &data_offset);

					if (0 != data_offset)
					{
						zbx_connector_send(ZBX_IPC_CONNECTOR_REQUEST, data,
								(zbx_uint32_t)data_offset);
					}
				}
			}
			else if (0 != timers_num)
			{
				if (FAIL == connectors_retrieved)
				{
					zbx_dc_config_history_sync_get_connector_filters(&connector_filters_history,
								&connector_filters_events);
					connectors_retrieved = SUCCEED;

					if (0 != connector_filters_history.values_num)
						item_retrieve_mode = ZBX_ITEM_GET_SYNC_EXPORT;
				}
			}

			if (SUCCEED == (event_export_enabled = zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS)) ||
					0 != connector_filters_events.values_num)
			{
				data_offset = 0;

				if (NULL != events_cbs->export_events_cb)
				{
					events_cbs->export_events_cb(event_export_enabled, &connector_filters_events,
							&data, &data_alloc, &data_offset);
				}

				if (0 != data_offset)
				{
					zbx_connector_send(ZBX_IPC_CONNECTOR_REQUEST, data,
							(zbx_uint32_t)data_offset);
				}
			}
		}

		if (0 != history_num || 0 != timers_num)
		{
			if (NULL != events_cbs->clean_events_cb)
				events_cbs->clean_events_cb();
		}

		if (0 != history_num)
		{
			zbx_free(trends);
			zbx_dc_config_clean_history_sync_items(items, errcodes, (size_t)history_num);

			zbx_vector_ptr_clear(&history_items);
			zbx_hc_free_item_values(history, history_num);
		}

		zbx_vector_uint64_clear(&itemids);

		/* Exit from sync loop if we have spent too much time here.       */
		/* This is done to allow syncer process to update its statistics. */
	}
	while (ZBX_SYNC_MORE == *more && ZBX_HC_SYNC_TIME_MAX >= time(NULL) - sync_start);

	zbx_free(items);
	zbx_free(errcodes);
	zbx_free(data);

	zbx_vector_connector_filter_clear_ext(&connector_filters_events, zbx_connector_filter_free);
	zbx_vector_connector_filter_clear_ext(&connector_filters_history, zbx_connector_filter_free);
	zbx_vector_connector_filter_destroy(&connector_filters_events);
	zbx_vector_connector_filter_destroy(&connector_filters_history);
	zbx_vector_dc_trigger_destroy(&trigger_order);
	zbx_hashset_destroy(&trigger_info);

	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_ptr_destroy(&history_items);
	zbx_vector_ptr_destroy(&inventory_values);
	zbx_vector_ptr_destroy(&item_diff);
	zbx_vector_ptr_destroy(&trigger_diff);
	zbx_vector_uint64_pair_destroy(&trends_diff);
	zbx_vector_uint64_pair_destroy(&proxy_subscriptions);

	zbx_vector_ptr_destroy(&trigger_timers);
	zbx_vector_uint64_destroy(&triggerids);
#undef ZBX_HC_SYNC_MIN_PCNT
}

/******************************************************************************
 *                                                                            *
 * Purpose: check status of a history cache usage, enqueue/dequeue proxy      *
 *          from priority list and accordingly enable or disable wait mode    *
 *                                                                            *
 * Parameters: proxyid   - [IN] the proxyid                                   *
 *                                                                            *
 * Return value: SUCCEED - proxy can be processed now                         *
 *               FAIL    - proxy cannot be processed now, it got enqueued     *
 *                                                                            *
 ******************************************************************************/
int	zbx_hc_check_proxy(zbx_uint64_t proxyid)
{
	double	hc_pused;
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxyid:"ZBX_FS_UI64, __func__, proxyid);

	zbx_dbcache_lock();

	hc_pused = 100 * (double)(zbx_dbcache_get_hc_mem()->total_size - zbx_dbcache_get_hc_mem()->free_size) /
			zbx_dbcache_get_hc_mem()->total_size;

	if (20 >= hc_pused)
	{
		zbx_dbcache_setproxyqueue_state(ZBX_HC_PROXYQUEUE_STATE_NORMAL);

		zbx_hc_proxyqueue_clear();

		ret = SUCCEED;
		goto out;
	}

	if (ZBX_HC_PROXYQUEUE_STATE_WAIT == zbx_dbcache_getproxyqueue_state())
	{
		zbx_hc_proxyqueue_enqueue(proxyid);

		if (60 < hc_pused)
		{
			ret = FAIL;
			goto out;
		}

		zbx_dbcache_setproxyqueue_state(ZBX_HC_PROXYQUEUE_STATE_NORMAL);
	}
	else
	{
		if (80 <= hc_pused)
		{
			zbx_dbcache_setproxyqueue_state(ZBX_HC_PROXYQUEUE_STATE_WAIT);
			zbx_hc_proxyqueue_enqueue(proxyid);

			ret = FAIL;
			goto out;
		}
	}

	if (0 == zbx_hc_proxyqueue_peek())
	{
		ret = SUCCEED;
		goto out;
	}

	ret = zbx_hc_proxyqueue_dequeue(proxyid);

out:
	zbx_dbcache_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
