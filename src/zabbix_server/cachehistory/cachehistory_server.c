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
