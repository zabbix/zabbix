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

#include "zbxproxydatacache.h"
#include "zbxcacheconfig.h"
#include "zbxcachehistory.h"
#include "proxydatacache.h"
#include "zbxdbhigh.h"
#include "zbx_item_constants.h"
#include "zbx_host_constants.h"

static void	pdc_history_add_rows_db(zbx_list_t *rows, zbx_list_item_t *next, zbx_uint64_t *lastid);

struct zbx_pdc_history_data
{
	zbx_pdc_state_t	state;
	zbx_list_t	rows;
	zbx_db_insert_t	db_insert;
};

static void	pdc_list_free_history(zbx_list_t *list, zbx_pdc_history_t *row)
{
	if (NULL != row->value)
		list->mem_free_func(row->value);
	if (NULL != row->source)
		list->mem_free_func(row->source);
	list->mem_free_func(row);
}

static void	pdc_history_free(zbx_pdc_history_t *row)
{
	zbx_free(row->value);
	zbx_free(row->source);
	zbx_free(row);
}

static void	pdc_history_add_value(zbx_pdc_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, zbx_uint64_t lastlogsize, int mtime, int timestamp, int logeventid,
		int severity, const char *source, time_t now)
{
	if (PDC_MEMORY == data->state)
	{
		zbx_pdc_history_t	*row;

		row = (zbx_pdc_history_t *)zbx_malloc(NULL, sizeof(zbx_pdc_history_t));

		row->id = 0;
		row->itemid = itemid;
		row->state = state;
		row->ts = *ts;
		row->flags = flags;
		row->lastlogsize = lastlogsize;
		row->mtime = mtime;
		row->timestamp = timestamp;
		row->logeventid = logeventid;
		row->severity = severity;
		row->value = zbx_strdup(NULL, value);
		row->source = zbx_strdup(NULL, source);
		row->write_clock = now;

		zbx_list_append(&data->rows, row, NULL);
	}
	else
	{
		zbx_db_insert_add_values(&data->db_insert, __UINT64_C(0), itemid, ts->sec, timestamp, source, severity,
				value, logeventid, ts->ns, state, lastlogsize, mtime, flags, (int)now);

	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: read proxy history data from the database                         *
 *                                                                            *
 * Parameters: lastid             - [IN] id of last processed proxy history   *
 *                                       record                       *
 *             rows               - [OUT] read proxy history rows              *
 *             more               - [OUT] set to ZBX_PROXY_DATA_MORE if there *
 *                                        might be more data to read          *
 *                                                                            *
 * Return value: The number of records read.                                  *
 *                                                                            *
 ******************************************************************************/
static int	pdc_history_get_rows_db(zbx_uint64_t lastid, zbx_vector_pdc_history_ptr_t *rows, int *more)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		id;
	int			retries = 1, total_retries = 10;
	struct timespec		t_sleep = { 0, 100000000L }, t_rem;
	zbx_pdc_history_t	*hist;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, lastid);

try_again:
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select id,itemid,clock,ns,timestamp,source,severity,"
			"value,logeventid,state,lastlogsize,mtime,flags"
				" from proxy_history"
				" where id>" ZBX_FS_UI64
				" order by id",
			lastid);

	result = zbx_db_select_n(sql, ZBX_MAX_HRECORDS - rows->values_num);

	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);

		if (1 < id - lastid)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				/* limit the number of total retries to avoid being stuck */
				/* in history full of 'holes' for a long time             */
				if (0 >= total_retries--)
					break;

				zbx_db_free_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__func__, id - lastid - 1,
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__func__, id - lastid - 1);
			}
		}

		retries = 1;

		hist = (zbx_pdc_history_t *)zbx_malloc(NULL, sizeof(zbx_pdc_history_t));
		hist->id = id;
		ZBX_STR2UINT64(hist->itemid, row[1]);
		ZBX_STR2UCHAR(hist->flags, row[12]);
		hist->ts.sec = atoi(row[2]);
		hist->ts.ns = atoi(row[3]);

		if (ZBX_PROXY_HISTORY_FLAG_NOVALUE != (hist->flags & ZBX_PROXY_HISTORY_MASK_NOVALUE))
		{
			ZBX_STR2UCHAR(hist->state, row[9]);

			if (0 == (hist->flags & ZBX_PROXY_HISTORY_FLAG_NOVALUE))
			{
				hist->timestamp = atoi(row[4]);
				hist->severity = atoi(row[6]);
				hist->logeventid = atoi(row[8]);
				hist->source = zbx_strdup(NULL, row[5]);
				hist->value = zbx_strdup(NULL, row[7]);
			}

			if (0 != (hist->flags & ZBX_PROXY_HISTORY_FLAG_META))
			{
				ZBX_STR2UINT64(hist->lastlogsize, row[10]);
				hist->mtime = atoi(row[11]);
			}
		}

		zbx_vector_pdc_history_ptr_append(rows, hist);

		lastid = id;
	}
	zbx_db_free_result(result);

	if (ZBX_MAX_HRECORDS != rows->values_num && 1 == retries)
		*more = ZBX_PROXY_DATA_DONE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows:" ZBX_FS_SIZE_T, __func__, rows->values_num);

	return rows->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history records to output json                                *
 *                                                                            *
 * Parameters: j             - [IN/OUT] json output buffer                    *
 *             rows          - [IN] history rows to export                    *
 *             lastid        - [OUT] the id of last added record              *
 *                                                                            *
 * Return value: The total number of records exported.                        *
 *                                                                            *
 ******************************************************************************/
static int	pdc_history_export(struct zbx_json *j, int records_num, const zbx_vector_pdc_history_ptr_t *rows,
		zbx_uint64_t *lastid)
{
	int				i, *errcodes;
	zbx_pdc_history_t		*row;
	zbx_vector_pdc_history_ptr_t	records;
	zbx_vector_uint64_t		itemids;
	zbx_hashset_t			nodata_itemids;
	zbx_dc_item_t			*dc_items;

	zbx_vector_pdc_history_ptr_create(&records);
	zbx_vector_pdc_history_ptr_reserve(&records, rows->values_num);
	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_reserve(&itemids, rows->values_num);
	zbx_hashset_create(&nodata_itemids, rows->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* filter out duplicate novalue updates */
	for (i = rows->values_num - 1; i >= 0; i--)
	{
		if (ZBX_PROXY_HISTORY_FLAG_NOVALUE == (rows->values[i]->flags & ZBX_PROXY_HISTORY_MASK_NOVALUE))
		{
			if (NULL != zbx_hashset_search(&nodata_itemids, &rows->values[i]->itemid))
				continue;

			zbx_hashset_insert(&nodata_itemids, &rows->values[i]->itemid, sizeof(rows->values[i]->itemid));
		}

		zbx_vector_pdc_history_ptr_append(&records, rows->values[i]);
		zbx_vector_uint64_append(&itemids, rows->values[i]->itemid);
	}

	dc_items = (zbx_dc_item_t *)zbx_malloc(NULL, records.values_num * sizeof(zbx_dc_item_t));
	errcodes = (int *)zbx_malloc(NULL, records.values_num * sizeof(int));

	zbx_dc_config_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);

	for (i = records.values_num - 1; i >= 0; i--)
	{
		row = records.values[i];
		*lastid = row->id;

		if (SUCCEED != errcodes[i])
			continue;

		if (ITEM_STATUS_ACTIVE != dc_items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != dc_items[i].host.status)
			continue;

		if (ZBX_PROXY_HISTORY_FLAG_NOVALUE == (row->flags & ZBX_PROXY_HISTORY_MASK_NOVALUE))
		{
			if (SUCCEED != zbx_is_counted_in_item_queue(dc_items[i].type, dc_items[i].key_orig))
				continue;
		}

		if (0 == records_num)
			zbx_json_addarray(j, ZBX_PROTO_TAG_HISTORY_DATA);

		zbx_json_addobject(j, NULL);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_ID, row->id);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, row->itemid);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, row->ts.sec);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_NS, row->ts.ns);

		if (ZBX_PROXY_HISTORY_FLAG_NOVALUE != (row->flags & ZBX_PROXY_HISTORY_MASK_NOVALUE))
		{
			if (ITEM_STATE_NORMAL != row->state)
				zbx_json_adduint64(j, ZBX_PROTO_TAG_STATE, row->state);

			if (0 == (row->flags & ZBX_PROXY_HISTORY_FLAG_NOVALUE))
			{
				if (0 != row->timestamp)
					zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGTIMESTAMP, row->timestamp);

				if ('\0' != *row->source)
				{
					zbx_json_addstring(j, ZBX_PROTO_TAG_LOGSOURCE, row->source,
							ZBX_JSON_TYPE_STRING);
				}

				if (0 != row->severity)
					zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGSEVERITY, row->severity);

				if (0 != row->logeventid)
					zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGEVENTID, row->logeventid);

				zbx_json_addstring(j, ZBX_PROTO_TAG_VALUE, row->value, ZBX_JSON_TYPE_STRING);
			}

			if (0 != (row->flags & ZBX_PROXY_HISTORY_FLAG_META))
			{
				zbx_json_adduint64(j, ZBX_PROTO_TAG_LASTLOGSIZE, row->lastlogsize);
				zbx_json_adduint64(j, ZBX_PROTO_TAG_MTIME, row->mtime);
			}
		}

		zbx_json_close(j);
		records_num++;

		/* stop gathering data to avoid exceeding the maximum packet size */
		if (ZBX_DATA_JSON_RECORD_LIMIT < j->buffer_offset)
			break;
	}

	zbx_dc_config_clean_items(dc_items, errcodes, itemids.values_num);
	zbx_free(errcodes);
	zbx_free(dc_items);

	zbx_hashset_destroy(&nodata_itemids);
	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_pdc_history_ptr_destroy(&records);

	return records_num;
}

static int	pdc_history_get_db(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int				records_num = 0;
	zbx_uint64_t			id;
	zbx_vector_pdc_history_ptr_t	rows;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_pdc_history_ptr_create(&rows);

	*more = ZBX_PROXY_DATA_MORE;
	id = pdc_get_lastid("proxy_history", "history_lastid");

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset && ZBX_MAX_HRECORDS_TOTAL > records_num &&
			0 != pdc_history_get_rows_db(id, &rows, more))
	{
		records_num = pdc_history_export(j, records_num, &rows, lastid);

		/* got less data than requested - either no more data to read or the history is full of */
		/* holes. In this case send retrieved data before attempting to read/wait for more data */
		if (ZBX_MAX_HRECORDS > rows.values_num)
			break;

		id = *lastid;
	}

	if (0 != records_num)
		zbx_json_close(j);

	zbx_vector_pdc_history_ptr_clear_ext(&rows, pdc_history_free);
	zbx_vector_pdc_history_ptr_destroy(&rows);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() lastid:" ZBX_FS_UI64 " records_num:%d size:~" ZBX_FS_SIZE_T " more:%d",
			__func__, *lastid, records_num, j->buffer_offset, *more);

	return records_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get history records from memory cache                             *
 *                                                                            *
 ******************************************************************************/
static int	pdc_history_get_mem(zbx_pdc_t *pdc, struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int			records_num = 0;
	void			*ptr;

	if (SUCCEED == zbx_list_peek(&pdc->history, &ptr))
	{
		zbx_pdc_history_t		*row;
		zbx_vector_pdc_history_ptr_t	rows;
		zbx_list_iterator_t		li;

		zbx_vector_pdc_history_ptr_create(&rows);
		zbx_list_iterator_init(&pdc->history, &li);

		while (1)
		{
			while (SUCCEED == zbx_list_iterator_next(&li) && ZBX_MAX_HRECORDS > rows.values_num)
			{
				(void)zbx_list_iterator_peek(&li, (void **)&row);
				zbx_vector_pdc_history_ptr_append(&rows, row);
			}

			records_num = pdc_history_export(j, records_num, &rows, lastid);

			if (ZBX_MAX_HRECORDS != rows.values_num)
				break;

			if (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset && records_num < ZBX_MAX_HRECORDS_TOTAL)
			{
				*more = 1;
				break;
			}

			zbx_vector_pdc_history_ptr_clear(&rows);
		}

		zbx_vector_pdc_history_ptr_destroy(&rows);

		if (0 != records_num)
			zbx_json_close(j);
	}

	return records_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history row to memory cache                                   *
 *                                                                            *
 * Parameters: pdc - [IN] proxy data cache                                    *
 *             src - [IN] row to add                                          *
 *                                                                            *
 * Return value: SUCCEED - the row was cached successfully                    *
 *               FAIL    - not enough memory in cache                         *
 *                                                                            *
 ******************************************************************************/
static int	pdc_history_add_row_mem(zbx_pdc_t *pdc, zbx_pdc_history_t *src)
{
	zbx_pdc_history_t	*row;
	int			ret = FAIL;

	if (NULL == (row = (zbx_pdc_history_t *)pdc_malloc(sizeof(zbx_pdc_history_t))))
		return FAIL;

	memcpy(row, src, sizeof(zbx_pdc_history_t));

	if (NULL == (row->value = pdc_strdup(src->value)))
	{
		row->source = NULL;
		goto out;
	}

	if (NULL == (row->source = pdc_strdup(src->source)))
		goto out;

	ret = zbx_list_append(&pdc->history, row, NULL);
out:
	if (SUCCEED != ret)
		pdc_list_free_history(&pdc->history, row);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history rows to memory cache                                *
 *                                                                            *
 * Parameters: pdc  - [IN] proxy data cache                                   *
 *             rows - [IN] rows to add                                        *
 *                                                                            *
 * Return value: NULL if all rows were added successfully. Otherwise the list *
 *               item of first failed row is returned                         *
 *                                                                            *
 ******************************************************************************/
static zbx_list_item_t	*pdc_history_add_rows_mem(zbx_pdc_t *pdc, zbx_list_t *rows)
{
	zbx_list_iterator_t	li;
	zbx_list_item_t		*next = pdc->history.tail;
	zbx_pdc_history_t	*row;
	int			rows_num = 0;
	zbx_uint64_t		id = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_list_iterator_init(rows, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		(void)zbx_list_iterator_peek(&li, (void **)&row);

		if (SUCCEED != pdc_history_add_row_mem(pdc, row))
			break;

		rows_num++;
	}

	/* set cached row ids */
	if (0 < rows_num)
	{
		zbx_list_iterator_t	li_id;

		id = zbx_dc_get_nextid("proxy_history", rows_num);

		if (NULL != next)
			next = next->next;

		zbx_list_iterator_init_with(&pdc->history, next, &li_id);

		do
		{
			(void)zbx_list_iterator_peek(&li_id, (void **)&row);
			row->id = id++;
		}
		while (SUCCEED == zbx_list_iterator_next(&li_id));
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows_num:%d next:%p" ZBX_FS_UI64, __func__, rows_num, li.current);

	return li.current;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history rows to database cache                                *
 *                                                                            *
 * Parameters: rows   - [IN] rows to add                                      *
 *             next   - [IN] next row to add                                  *
 *             lastid - [OUT] last inserted id                                *
 *
 ******************************************************************************/
static void	pdc_history_add_rows_db(zbx_list_t *rows, zbx_list_item_t *next, zbx_uint64_t *lastid)
{
	zbx_list_iterator_t	li;
	zbx_pdc_history_t	*row;
	int			rows_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() next:%p", __func__, next);

	if (SUCCEED == zbx_list_iterator_init_with(rows, next, &li))
	{
		zbx_db_insert_t	db_insert;

		zbx_db_insert_prepare(&db_insert, "proxy_history", "id", "itemid", "clock", "timestamp", "source",
				"severity", "value", "logeventid", "ns", "state", "lastlogsize", "mtime", "flags",
				"write_clock", NULL);
		do
		{
			(void)zbx_list_iterator_peek(&li, (void **)&row);
			zbx_db_insert_add_values(&db_insert, row->id, row->itemid, row->ts.sec, row->timestamp,
					row->source, row->severity, row->value, row->logeventid, row->ts.ns, row->state,
					row->lastlogsize, row->mtime, row->flags, (int)row->write_clock);
			rows_num++;
			*lastid = row->id;
		}
		while (SUCCEED == zbx_list_iterator_next(&li));

		/* when flushing local cache need to set row ids */
		if (0 == *lastid)
		{
			zbx_db_insert_autoincrement(&db_insert, "id");
			(void)zbx_db_insert_execute(&db_insert);
			*lastid = zbx_db_insert_get_lastid(&db_insert);
		}
		else
			(void)zbx_db_insert_execute(&db_insert);

		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows_num:%d", __func__, rows_num);
}


void	pdc_history_flush(zbx_pdc_t *pdc)
{
	zbx_uint64_t		lastid;
	zbx_pdc_history_t	*row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pdc_history_add_rows_db(&pdc->history, NULL, &lastid);

	while (SUCCEED == zbx_list_pop(&pdc->history, (void **)&row))
		pdc_list_free_history(&pdc->history, row);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear sent history records                                      *
 *                                                                            *
 ******************************************************************************/
static void	pdc_history_clear(zbx_pdc_t *pdc, zbx_uint64_t lastid)
{
	zbx_pdc_history_t	*row;

	while (SUCCEED == zbx_list_peek(&pdc->history, (void **)&row))
	{
		if (row->id > lastid)
			break;

		zbx_list_pop(&pdc->history, NULL);
		pdc_list_free_history(&pdc->history, row);
	}
}

static void	pdc_history_data_free(zbx_pdc_history_data_t *data)
{

	if (PDC_MEMORY == data->state)
	{
		zbx_pdc_history_t	*row;

		while (SUCCEED == zbx_list_pop(&data->rows, (void **)&row))
			pdc_list_free_history(&data->rows, row);

		zbx_list_destroy(&data->rows);
	}
	else
	{
		zbx_db_insert_clean(&data->db_insert);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if oldest record is within allowed age                      *
 *                                                                            *
 ******************************************************************************/
int	pdc_history_check_age(zbx_pdc_t *pdc)
{
	zbx_pdc_history_t	*row;

	if (SUCCEED != zbx_list_peek(&pdc->history, (void **)&row) || time(NULL) - row->write_clock < pdc->max_age)
		return SUCCEED;

	return FAIL;
}

/* public api */

/******************************************************************************
 *                                                                            *
 * Purpose: open history data cache                                           *
 *                                                                            *
 * Return value: The history data cache handle                                *
 *                                                                            *
 ******************************************************************************/
zbx_pdc_history_data_t	*zbx_pdc_history_open(void)
{
	zbx_pdc_history_data_t	*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	data = (zbx_pdc_history_data_t *)zbx_malloc(NULL, sizeof(zbx_pdc_history_data_t));

	pdc_lock();
	if (PDC_DATABASE == (data->state = pdc_dst[pdc_cache->state]))
		pdc_cache->db_handles_num++;
	pdc_unlock();

	if (PDC_MEMORY == data->state)
	{
		zbx_list_create(&data->rows);
	}

	if (PDC_DATABASE == pdc_dst[pdc_cache->state])
	{
		zbx_db_insert_prepare(&data->db_insert, "proxy_history", "id", "itemid", "clock", "timestamp", "source",
				"severity", "value", "logeventid", "ns", "state", "lastlogsize", "mtime", "flags",
				"write_clock", NULL);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached history data and free the handle                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_history_close(zbx_pdc_history_data_t *data)
{
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (PDC_MEMORY == data->state)
	{
		void	*ptr;

		if (SUCCEED == zbx_list_peek(&data->rows, &ptr))
		{
			zbx_list_item_t		*next = NULL;

			pdc_lock();

			if (PDC_MEMORY == pdc_cache->state && SUCCEED != pdc_history_check_age(pdc_cache))
			{
				pdc_fallback_to_database(pdc_cache, "cached records are too old");
			}
			else if (PDC_MEMORY == pdc_dst[pdc_cache->state])
			{
				if (NULL == (next = pdc_history_add_rows_mem(pdc_cache, &data->rows)))
				{
					pdc_unlock();
					goto out;
				}

				if (PDC_DATABASE_MEMORY == pdc_cache->state)
				{
					/* transition to memory cache failed, disable memory cache until restart */
					pdc_fallback_to_database(pdc_cache, "aborted proxy data cache transition to"
							" memory mode: not enough space");
				}
				else
				{
					/* initiate transition to database cache */
					pdc_cache_set_state(pdc_cache, PDC_MEMORY_DATABASE, "not enough space");
				}
			}

			/* not all rows were added to memory cache - flush them to database */
			pdc_cache->db_handles_num++;
			pdc_unlock();

			pdc_history_add_rows_db(&data->rows, next, &lastid);
		}
	}
	else
	{
		zbx_db_insert_autoincrement(&data->db_insert, "id");
		(void)zbx_db_insert_execute(&data->db_insert);
		lastid = zbx_db_insert_get_lastid(&data->db_insert);
	}

	pdc_lock();

	if (pdc_cache->history_lastid_db < lastid)
		pdc_cache->history_lastid_db = lastid;

	pdc_cache->db_handles_num--;

	pdc_unlock();
out:
	pdc_history_data_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write normal value into history data cache                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_history_write_value(zbx_pdc_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, time_t now)
{
	pdc_history_add_value(data, itemid, state, value, ts, flags, 0, 0, 0, 0, 0, "", now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write value with metadata into history data cache                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_history_write_meta_value(zbx_pdc_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, zbx_uint64_t lastlogsize, int mtime, int timestamp, int logeventid,
		int severity, const char *source, time_t now)
{
	pdc_history_add_value(data, itemid, state, value, ts, flags, lastlogsize, mtime, timestamp, logeventid,
			severity, source, now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get history data for sending to server                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_pdc_history_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int	state, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64 ", more:" ZBX_FS_UI64, __func__, *lastid, *more);

	pdc_lock();

	if (PDC_MEMORY == (state = pdc_src[pdc_cache->state]))
		ret = pdc_history_get_mem(pdc_cache, j, lastid, more);

	pdc_unlock();

	if (PDC_DATABASE == state)
		ret = pdc_history_get_db(j, lastid, more);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update database lastid/clear memory records                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_set_history_lastid(const zbx_uint64_t lastid)
{
	int	state;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, lastid);

	pdc_lock();

	pdc_cache->history_lastid_sent = lastid;

	if (PDC_MEMORY == (state = pdc_src[pdc_cache->state]))
		pdc_history_clear(pdc_cache, lastid);

	pdc_unlock();

	if (PDC_DATABASE == state)
		pdc_set_lastid("proxy_history", "history_lastid", lastid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
