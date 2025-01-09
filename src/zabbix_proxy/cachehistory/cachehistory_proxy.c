/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "cachehistory_proxy.h"
#include "zbxcachehistory.h"

#include "zbxdb.h"
#include "zbxhistory.h"
#include "zbxtypes.h"
#include "zbxvariant.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbx_item_constants.h"
#include "zbxproxybuffer.h"

static char	*sql = NULL;
static size_t	sql_alloc = 4 * ZBX_KIBIBYTE;

/******************************************************************************
 *                                                                            *
 * Purpose: update items info after new value is received                     *
 *                                                                            *
 * Parameters: item_diff - diff of items to be updated                        *
 *                                                                            *
 ******************************************************************************/
static void	DBmass_proxy_update_items(zbx_vector_item_diff_ptr_t *item_diff)
{
	size_t	sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_item_diff_ptr_sort(item_diff, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zbx_db_save_item_changes(&sql, &sql_alloc, &sql_offset, item_diff, ZBX_FLAGS_ITEM_DIFF_UPDATE_DB);

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type other than       *
 *          ITEM_VALUE_TYPE_LOG not containing meta information in result     *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history(zbx_pb_history_data_t *handle, const zbx_dc_history_t *h, time_t now)
{
	int		flags;
	char		buffer[64];
	const char	*pvalue;

	if (0 == (h->flags & ZBX_DC_FLAG_NOVALUE))
	{
		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL64, h->value.dbl);
				pvalue = buffer;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, h->value.ui64);
				pvalue = buffer;
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				pvalue = h->value.str;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				return;
		}
		flags = 0;
	}
	else
	{
		flags = ZBX_PROXY_HISTORY_FLAG_NOVALUE;
		pvalue = "";
	}

	zbx_pb_history_write_value(handle, h->itemid, h->state, pvalue, &h->ts, flags, now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type other than       *
 *          ITEM_VALUE_TYPE_LOG containing meta information in result         *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_meta(zbx_pb_history_data_t *handle, const zbx_dc_history_t *h, time_t now)
{
	char		buffer[64];
	const char	*pvalue;
	int		flags = ZBX_PROXY_HISTORY_FLAG_META;

	if (0 == (h->flags & ZBX_DC_FLAG_NOVALUE))
	{
		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL64, h->value.dbl);
				pvalue = buffer;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, h->value.ui64);
				pvalue = buffer;
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				pvalue = h->value.str;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				return;
		}
	}
	else
	{
		flags |= ZBX_PROXY_HISTORY_FLAG_NOVALUE;
		pvalue = "";
	}

	zbx_pb_history_write_meta_value(handle, h->itemid, h->state, pvalue, &h->ts, flags, h->lastlogsize,
			h->mtime, 0, 0, 0, "", now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type                  *
 *          ITEM_VALUE_TYPE_LOG                                               *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_log(zbx_pb_history_data_t *handle, const zbx_dc_history_t *h, time_t now)
{
	zbx_uint64_t	lastlogsize;
	int		mtime, flags;

	if (0 == (h->flags & ZBX_DC_FLAG_NOVALUE))
	{
		zbx_log_value_t *log = h->value.log;

		if (0 != (h->flags & ZBX_DC_FLAG_META))
		{
			flags = ZBX_PROXY_HISTORY_FLAG_META;
			lastlogsize = h->lastlogsize;
			mtime = h->mtime;
		}
		else
		{
			flags = 0;
			lastlogsize = 0;
			mtime = 0;
		}

		zbx_pb_history_write_meta_value(handle, h->itemid, h->state, log->value, &h->ts, flags, lastlogsize,
				mtime, log->timestamp, log->logeventid, log->severity, ZBX_NULL2EMPTY_STR(log->source),
				now);
	}
	else
	{
		/* sent to server only if not 0, see proxy_get_history_data() */

		flags = ZBX_PROXY_HISTORY_FLAG_META | ZBX_PROXY_HISTORY_FLAG_NOVALUE;

		zbx_pb_history_write_meta_value(handle, h->itemid, h->state, "", &h->ts, flags, h->lastlogsize,
				h->mtime, 0, 0, 0, "", now);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_notsupported(zbx_pb_history_data_t *handle, const zbx_dc_history_t *h, time_t now)
{
	zbx_pb_history_write_value(handle, h->itemid, h->state, ZBX_NULL2EMPTY_STR(h->value.err), &h->ts, 0, now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: inserting new history data after new value is received            *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 ******************************************************************************/
static void	DBmass_proxy_add_history(zbx_dc_history_t *history, int history_num)
{
	int			i;
	zbx_pb_history_data_t	*handle;
	time_t			now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);

	handle = zbx_pb_history_open();

	for (i = 0; i < history_num; i++)
	{
		const zbx_dc_history_t	*h = &history[i];

		if (ITEM_STATE_NOTSUPPORTED == h->state)
		{
			dc_add_proxy_history_notsupported(handle, h, now);
			continue;
		}

		if (0 != (h->flags & ZBX_DC_FLAG_UNDEF))
			continue;

		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_LOG:
				dc_add_proxy_history_log(handle, h, now);
				break;
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				if (0 != (h->flags & ZBX_DC_FLAG_META))
					dc_add_proxy_history_meta(handle, h, now);
				else
					dc_add_proxy_history(handle, h, now);
				break;
			case ITEM_VALUE_TYPE_NONE:
				dc_add_proxy_history(handle, h, now);
				break;
			case ITEM_VALUE_TYPE_BIN:
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

	}

	zbx_pb_history_close(handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepares history update by checking which values must be stored   *
 *                                                                            *
 * Parameters: history     - [IN/OUT] the history values                      *
 *             history_num - [IN] the number of history values                *
 *             item_diff   - vector to store prepared diff                    *
 *                                                                            *
 ******************************************************************************/
static void	proxy_prepare_history(zbx_dc_history_t *history, int history_num, zbx_vector_item_diff_ptr_t *item_diff)
{
	int			i, *errcodes;
	zbx_history_sync_item_t	*items;
	zbx_vector_uint64_t	itemids;

	zbx_vector_item_diff_ptr_reserve(item_diff, history_num);

	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_reserve(&itemids, (size_t)history_num);

	for (i = 0; i < history_num; i++)
		zbx_vector_uint64_append(&itemids, history[i].itemid);

	items = (zbx_history_sync_item_t *)zbx_malloc(NULL, sizeof(zbx_history_sync_item_t) * (size_t)history_num);
	errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)history_num);

	zbx_dc_config_history_sync_get_items_by_itemids(items, itemids.values, errcodes, (size_t)itemids.values_num,
			ZBX_ITEM_GET_SYNC);

	for (i = 0; i < history_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		zbx_item_diff_t		*diff = (zbx_item_diff_t *)zbx_malloc(NULL, sizeof(zbx_item_diff_t));
		zbx_dc_history_t	*h = &history[i];

		diff->itemid = h->itemid;
		diff->flags = ZBX_FLAGS_ITEM_DIFF_UNSET;

		if (items[i].state != h->state)
		{
			diff->state = h->state;
			diff->error = (ITEM_STATE_NOTSUPPORTED == h->state ? h->value.err : "");
			diff->flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE | ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;
		}
		else if (ITEM_STATE_NOTSUPPORTED == h->state &&
				0 != strcmp(ZBX_NULL2EMPTY_STR(items[i].error), h->value.err))
		{
			diff->error = h->value.err;
			diff->flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;
		}

		if (0 != (ZBX_DC_FLAG_META & history[i].flags))
		{
			diff->lastlogsize = history[i].lastlogsize;
			diff->mtime = history[i].mtime;
			diff->flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE | ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME;
		}

		zbx_vector_item_diff_ptr_append(item_diff, diff);

		/* store numeric items to handle data conversion errors on server and trends */
		if (ITEM_VALUE_TYPE_FLOAT == items[i].value_type || ITEM_VALUE_TYPE_UINT64 == items[i].value_type)
			continue;

		/* store discovery rules */
		if (0 != (items[i].flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		/* store errors */
		if (ITEM_STATE_NOTSUPPORTED == history[i].state)
			continue;

		/* store items linked to host inventory */
		if (0 != items[i].inventory_link)
			continue;

		/* values of items without history storage or any use of this history must be discarded */
		if (0 == items[i].history)
		{
			zbx_dc_history_clean_value(history + i);
			history[i].flags |= ZBX_DC_FLAG_NOVALUE;
		}
	}

	zbx_dc_config_clean_history_sync_items(items, errcodes, (size_t)history_num);
	zbx_free(items);
	zbx_free(errcodes);
	zbx_vector_uint64_destroy(&itemids);
}

void	zbx_sync_proxy_history(int *values_num, int *triggers_num, const zbx_events_funcs_t *events_cbs,
		zbx_ipc_async_socket_t *rtc, int config_history_storage_pipelines, int *more)
{
	ZBX_UNUSED(triggers_num);
	ZBX_UNUSED(events_cbs);
	ZBX_UNUSED(rtc);

	int				history_num, txn_rc = ZBX_DB_OK;
	time_t				sync_start;
	zbx_vector_hc_item_ptr_t	history_items;
	zbx_vector_item_diff_ptr_t	item_diff;
	zbx_dc_history_t		history[ZBX_HC_SYNC_MAX];

	ZBX_UNUSED(config_history_storage_pipelines);
	zbx_vector_hc_item_ptr_create(&history_items);
	zbx_vector_hc_item_ptr_reserve(&history_items, ZBX_HC_SYNC_MAX);
	zbx_vector_item_diff_ptr_create(&item_diff);

	sync_start = time(NULL);

	do
	{
		*more = ZBX_SYNC_DONE;

		zbx_dbcache_lock();

		zbx_hc_pop_items(&history_items);		/* select and take items out of history cache */
		history_num = history_items.values_num;

		zbx_dbcache_unlock();

		if (0 == history_num)
			break;

		zbx_hc_get_item_values(history, &history_items);	/* copy item data from history cache */
		proxy_prepare_history(history, history_items.values_num, &item_diff);

		DBmass_proxy_add_history(history, history_num);

		if (0 != item_diff.values_num)
		{
			do
			{
				zbx_db_begin();
				DBmass_proxy_update_items(&item_diff);
			}
			while (ZBX_DB_DOWN == (txn_rc = zbx_db_commit()));
		}

		zbx_dbcache_lock();

		zbx_hc_push_items(&history_items);	/* return items to history cache */

		if (ZBX_DB_FAIL != txn_rc)
		{
			if (0 != item_diff.values_num)
				zbx_dc_config_items_apply_changes(&item_diff);

			zbx_dbcache_set_history_num(zbx_dbcache_get_history_num() - history_num);

			if (0 != zbx_hc_queue_get_size())
				*more = ZBX_SYNC_MORE;

			zbx_dbcache_unlock();

			*values_num += history_num;

			zbx_hc_free_item_values(history, history_num);
		}
		else
		{
			*more = ZBX_SYNC_MORE;
			zbx_dbcache_unlock();
		}

		zbx_vector_hc_item_ptr_clear(&history_items);
		zbx_vector_item_diff_ptr_clear_ext(&item_diff, zbx_item_diff_free);

		/* Exit from sync loop if we have spent too much time here */
		/* unless we are doing full sync. This is done to allow    */
		/* syncer process to update their statistics.              */
	}
	while (ZBX_SYNC_MORE == *more && ZBX_HC_SYNC_TIME_MAX >= time(NULL) - sync_start);

	zbx_vector_item_diff_ptr_destroy(&item_diff);
	zbx_vector_hc_item_ptr_destroy(&history_items);
}
