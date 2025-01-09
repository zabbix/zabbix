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

#include "pb_history.h"
#include "proxybuffer.h"
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"
#include "zbxcacheconfig.h"
#include "zbxcachehistory.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxproxybuffer.h"
#include "zbxshmem.h"
#include "zbxtime.h"

static void	pb_history_add_rows_db(zbx_list_t *rows, zbx_list_item_t *next, zbx_uint64_t *lastid);

struct zbx_pb_history_data
{
	zbx_pb_state_t	state;
	zbx_list_t	rows;
	int		rows_num;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	handleid;
};

void	pb_list_free_history(zbx_list_t *list, zbx_pb_history_t *row)
{
	if (NULL != row->value)
		list->mem_free_func(row->value);
	if (NULL != row->source)
		list->mem_free_func(row->source);
	list->mem_free_func(row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: estimate approximate history row size in cache                    *
 *                                                                            *
 ******************************************************************************/
size_t	pb_history_estimate_row_size(const char *value, const char *source)
{
	size_t	size = 0;

	size += zbx_shmem_required_chunk_size(sizeof(zbx_pb_history_t));
	size += zbx_shmem_required_chunk_size(sizeof(zbx_list_item_t));
	size += zbx_shmem_required_chunk_size(strlen(value) + 1);
	size += zbx_shmem_required_chunk_size(strlen(source) + 1);

	return size;
}

static void	pb_history_free(zbx_pb_history_t *row)
{
	if (0 == (row->flags & ZBX_PROXY_HISTORY_FLAG_NOVALUE))
	{
		zbx_free(row->value);
		zbx_free(row->source);
	}

	zbx_free(row);
}

static void	pb_history_add_value(zbx_pb_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, zbx_uint64_t lastlogsize, int mtime, int timestamp, int logeventid,
		int severity, const char *source, time_t now)
{
	if (PB_MEMORY == data->state)
	{
		zbx_pb_history_t	*row;

		row = (zbx_pb_history_t *)zbx_malloc(NULL, sizeof(zbx_pb_history_t));

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
		data->rows_num++;
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
static int	pb_history_get_rows_db(zbx_uint64_t lastid, zbx_vector_pb_history_ptr_t *rows, int *more)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		id, gapid = 0;
	zbx_pb_history_t	*hist;

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

			if (id != gapid)
			{
				zbx_db_free_result(result);

				gapid = id;
				pb_wait_handles(&get_pb_data()->history_handleids);

				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__func__, id - lastid - 1);
			}
		}

		hist = (zbx_pb_history_t *)zbx_malloc(NULL, sizeof(zbx_pb_history_t));
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

		zbx_vector_pb_history_ptr_append(rows, hist);

		lastid = id;
	}
	zbx_db_free_result(result);

	if (ZBX_MAX_HRECORDS != rows->values_num)
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
 *             lastid        - [OUT] id of last added record                  *
 *                                                                            *
 * Return value: The total number of records exported.                        *
 *                                                                            *
 ******************************************************************************/
static int	pb_history_export(struct zbx_json *j, int records_num, const zbx_vector_pb_history_ptr_t *rows,
		zbx_uint64_t *lastid)
{
	int				i, *errcodes;
	zbx_pb_history_t		*row;
	zbx_vector_pb_history_ptr_t	records;
	zbx_vector_uint64_t		itemids;
	zbx_hashset_t			nodata_itemids;
	zbx_dc_item_t			*dc_items;

	zbx_vector_pb_history_ptr_create(&records);
	zbx_vector_pb_history_ptr_reserve(&records, (size_t)rows->values_num);
	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_reserve(&itemids, (size_t)rows->values_num);
	zbx_hashset_create(&nodata_itemids, (size_t)rows->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* filter out duplicate novalue updates */
	for (i = rows->values_num - 1; i >= 0; i--)
	{
		if (ZBX_PROXY_HISTORY_FLAG_NOVALUE == (rows->values[i]->flags & ZBX_PROXY_HISTORY_MASK_NOVALUE))
		{
			if (NULL != zbx_hashset_search(&nodata_itemids, &rows->values[i]->itemid))
				continue;

			zbx_hashset_insert(&nodata_itemids, &rows->values[i]->itemid, sizeof(rows->values[i]->itemid));
		}

		zbx_vector_pb_history_ptr_append(&records, rows->values[i]);
		zbx_vector_uint64_append(&itemids, rows->values[i]->itemid);
	}

	dc_items = (zbx_dc_item_t *)zbx_malloc(NULL, (size_t)records.values_num * sizeof(zbx_dc_item_t));
	errcodes = (int *)zbx_malloc(NULL, (size_t)records.values_num * sizeof(int));

	zbx_dc_config_get_items_by_itemids(dc_items, itemids.values, errcodes, (size_t)itemids.values_num);

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

		if (0 == records_num)
			zbx_json_addarray(j, ZBX_PROTO_TAG_HISTORY_DATA);

		zbx_json_addobject(j, NULL);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_ID, row->id);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, row->itemid);
		zbx_json_addint64(j, ZBX_PROTO_TAG_CLOCK, row->ts.sec);
		zbx_json_addint64(j, ZBX_PROTO_TAG_NS, row->ts.ns);

		if (ZBX_PROXY_HISTORY_FLAG_NOVALUE != (row->flags & ZBX_PROXY_HISTORY_MASK_NOVALUE))
		{
			if (ITEM_STATE_NORMAL != row->state)
				zbx_json_addint64(j, ZBX_PROTO_TAG_STATE, row->state);

			if (0 == (row->flags & ZBX_PROXY_HISTORY_FLAG_NOVALUE))
			{
				if (0 != row->timestamp)
					zbx_json_addint64(j, ZBX_PROTO_TAG_LOGTIMESTAMP, row->timestamp);

				if ('\0' != *row->source)
				{
					zbx_json_addstring(j, ZBX_PROTO_TAG_LOGSOURCE, row->source,
							ZBX_JSON_TYPE_STRING);
				}

				if (0 != row->severity)
					zbx_json_addint64(j, ZBX_PROTO_TAG_LOGSEVERITY, row->severity);

				if (0 != row->logeventid)
					zbx_json_addint64(j, ZBX_PROTO_TAG_LOGEVENTID, row->logeventid);

				zbx_json_addstring(j, ZBX_PROTO_TAG_VALUE, row->value, ZBX_JSON_TYPE_STRING);
			}

			if (0 != (row->flags & ZBX_PROXY_HISTORY_FLAG_META))
			{
				zbx_json_adduint64(j, ZBX_PROTO_TAG_LASTLOGSIZE, row->lastlogsize);
				zbx_json_addint64(j, ZBX_PROTO_TAG_MTIME, row->mtime);
			}
		}

		zbx_json_close(j);
		records_num++;

		/* stop gathering data to avoid exceeding the maximum packet size */
		if (ZBX_DATA_JSON_RECORD_LIMIT < j->buffer_offset)
			break;
	}

	zbx_dc_config_clean_items(dc_items, errcodes, (size_t)itemids.values_num);
	zbx_free(errcodes);
	zbx_free(dc_items);

	zbx_hashset_destroy(&nodata_itemids);
	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_pb_history_ptr_destroy(&records);

	return records_num;
}

static int	pb_history_get_db(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int				records_num = 0;
	zbx_uint64_t			id;
	zbx_vector_pb_history_ptr_t	rows;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_pb_history_ptr_create(&rows);

	*more = ZBX_PROXY_DATA_MORE;
	id = pb_get_lastid("proxy_history", "history_lastid");

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset && ZBX_MAX_HRECORDS_TOTAL > records_num &&
			0 != pb_history_get_rows_db(id, &rows, more))
	{
		records_num = pb_history_export(j, records_num, &rows, lastid);

		/* got less data than requested - either no more data to read or the history is full of */
		/* holes. In this case send retrieved data before attempting to read/wait for more data */
		if (ZBX_MAX_HRECORDS > rows.values_num)
			break;

		id = *lastid;

		zbx_vector_pb_history_ptr_clear_ext(&rows, pb_history_free);
	}

	if (0 != records_num)
		zbx_json_close(j);

	zbx_vector_pb_history_ptr_clear_ext(&rows, pb_history_free);
	zbx_vector_pb_history_ptr_destroy(&rows);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() lastid:" ZBX_FS_UI64 " records_num:%d size:~" ZBX_FS_SIZE_T " more:%d",
			__func__, *lastid, records_num, j->buffer_offset, *more);

	return records_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get history records from memory cache                             *
 *                                                                            *
 ******************************************************************************/
static int	pb_history_get_mem(zbx_pb_t *pb, struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int	records_num = 0;
	void	*ptr;

	*more = ZBX_PROXY_DATA_DONE;

	if (SUCCEED == zbx_list_peek(&pb->history, &ptr))
	{
		zbx_pb_history_t		*row;
		zbx_vector_pb_history_ptr_t	rows;
		zbx_list_iterator_t		li;

		zbx_vector_pb_history_ptr_create(&rows);
		zbx_list_iterator_init(&pb->history, &li);

		while (1)
		{
			while (SUCCEED == zbx_list_iterator_next(&li))
			{
				(void)zbx_list_iterator_peek(&li, (void **)&row);
				zbx_vector_pb_history_ptr_append(&rows, row);

				if (ZBX_MAX_HRECORDS <= rows.values_num)
					break;
			}

			records_num = pb_history_export(j, records_num, &rows, lastid);

			if (ZBX_MAX_HRECORDS != rows.values_num)
				break;

			if (ZBX_DATA_JSON_BATCH_LIMIT <= j->buffer_offset || records_num >= ZBX_MAX_HRECORDS_TOTAL)
			{
				*more = ZBX_PROXY_DATA_MORE;
				break;
			}

			zbx_vector_pb_history_ptr_clear(&rows);
		}

		zbx_vector_pb_history_ptr_destroy(&rows);

		if (0 != records_num)
			zbx_json_close(j);
	}

	return records_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history row to memory cache                                   *
 *                                                                            *
 * Parameters: pb  - [IN] proxy buffer                                        *
 *             src - [IN] row to add                                          *
 *                                                                            *
 * Return value: SUCCEED - the row was cached successfully                    *
 *               FAIL    - not enough memory in cache                         *
 *                                                                            *
 ******************************************************************************/
static int	pb_history_add_row_mem(zbx_pb_t *pb, zbx_pb_history_t *src)
{
	zbx_pb_history_t	*row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() free:" ZBX_FS_SIZE_T " request:" ZBX_FS_SIZE_T, __func__,
			pb_get_free_size(), pb_history_estimate_row_size(src->value, src->source));

	if (NULL == (row = (zbx_pb_history_t *)pb_malloc(sizeof(zbx_pb_history_t))))
		goto out;

	memcpy(row, src, sizeof(zbx_pb_history_t));

	if (NULL == (row->value = pb_strdup(src->value)))
	{
		row->source = NULL;
		goto out;
	}

	if (NULL == (row->source = pb_strdup(src->source)))
		goto out;

	ret = zbx_list_append(&pb->history, row, NULL);
out:
	if (SUCCEED == ret)
		pb->history_lastid_mem = row->id;
	else if (NULL != row)
		pb_list_free_history(&pb->history, row);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%s free:" ZBX_FS_SIZE_T , __func__, zbx_result_string(ret),
			pb_get_free_size());

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set ids to new history rows                                       *
 *                                                                            *
 ******************************************************************************/
static void	pb_history_set_row_ids(zbx_list_t *rows, int rows_num)
{
	zbx_uint64_t		id;
	zbx_pb_history_t	*row;
	zbx_list_iterator_t	li;

	id = zbx_dc_get_nextid("proxy_history", rows_num);
	zbx_list_iterator_init(rows, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		(void)zbx_list_iterator_peek(&li, (void **)&row);
		row->id = id++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history rows to memory cache                                  *
 *                                                                            *
 * Parameters: pb   - [IN] proxy buffer                                       *
 *             rows - [IN] rows to add                                        *
 *                                                                            *
 * Return value: NULL if all rows were added successfully. Otherwise the list *
 *               item of first failed row is returned                         *
 *                                                                            *
 ******************************************************************************/
static zbx_list_item_t	*pb_history_add_rows_mem(zbx_pb_t *pb, zbx_list_t *rows)
{
	zbx_list_iterator_t	li;
	zbx_pb_history_t	*row;
	int			rows_num = 0;
	size_t			size = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_list_iterator_init(rows, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		(void)zbx_list_iterator_peek(&li, (void **)&row);

		while (SUCCEED != pb_history_add_row_mem(pb, row))
		{
			if (ZBX_PB_MODE_MEMORY != pb->mode)
				goto out;

			/* in memory mode keep discarding old records until new */
			/* one can be written in proxy memory buffer            */

			if (0 == size)
				size = pb_history_estimate_row_size(row->value, row->source);

			if (FAIL == pb_free_space(get_pb_data(), size))
			{
				zabbix_log(LOG_LEVEL_WARNING, "history record with size " ZBX_FS_SIZE_T
						" is too large for proxy memory buffer, discarding", size);
				break;
			}
		}

		rows_num++;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows_num:%d next:%p", __func__, rows_num, li.current);

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
static void	pb_history_add_rows_db(zbx_list_t *rows, zbx_list_item_t *next, zbx_uint64_t *lastid)
{
	zbx_list_iterator_t	li;
	zbx_pb_history_t	*row;
	int			rows_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() next:%p", __func__, next);

	if (SUCCEED == zbx_list_iterator_init_with(rows, next, &li))
	{
		zbx_db_insert_t	db_insert;

		zbx_db_insert_prepare(&db_insert, "proxy_history", "id", "itemid", "clock", "timestamp", "source",
				"severity", "value", "logeventid", "ns", "state", "lastlogsize", "mtime", "flags",
				"write_clock", (char *)NULL);
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

		(void)zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows_num:%d", __func__, rows_num);
}


void	pb_history_flush(zbx_pb_t *pb)
{
	zbx_uint64_t	lastid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pb_history_add_rows_db(&pb->history, NULL, &lastid);

	if (get_pb_data()->history_lastid_db < lastid)
		get_pb_data()->history_lastid_db = lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear sent history records                                      *
 *                                                                            *
 ******************************************************************************/
void	pb_history_clear(zbx_pb_t *pb, zbx_uint64_t lastid)
{
	zbx_pb_history_t	*row;

	while (SUCCEED == zbx_list_peek(&pb->history, (void **)&row))
	{
		if (row->id > lastid)
			break;

		zbx_list_pop(&pb->history, NULL);
		pb_list_free_history(&pb->history, row);
	}
}

static void	pb_history_data_free(zbx_pb_history_data_t *data)
{

	if (PB_MEMORY == data->state)
	{
		zbx_pb_history_t	*row;

		while (SUCCEED == zbx_list_pop(&data->rows, (void **)&row))
			pb_list_free_history(&data->rows, row);

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
int	pb_history_check_age(zbx_pb_t *pb)
{
	zbx_pb_history_t	*row;
	int			now;

	now = time(NULL);

	while (SUCCEED == zbx_list_peek(&pb->history, (void **)&row))
	{
		if (now - row->write_clock <= (time_t)pb->offline_buffer)
			break;

		zbx_list_pop(&pb->history, NULL);
		pb_list_free_history(&pb->history, row);
	}

	if (0 == pb->max_age)
		return SUCCEED;

	if (SUCCEED != zbx_list_peek(&pb->history, (void **)&row) || time(NULL) - row->write_clock < pb->max_age)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: write history last sent record id to database                     *
 *                                                                            *
 ******************************************************************************/
void	pb_history_set_lastid(zbx_uint64_t lastid)
{
	pb_set_lastid("proxy_history", "history_lastid", lastid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if history rows are cached in memory buffer                 *
 *                                                                            *
 ******************************************************************************/
int	pb_history_has_mem_rows(zbx_pb_t *pb)
{
	void	*ptr;

	return zbx_list_peek(&pb->history, &ptr);
}

/* public api */

/******************************************************************************
 *                                                                            *
 * Purpose: open history data cache                                           *
 *                                                                            *
 * Return value: The history data cache handle                                *
 *                                                                            *
 ******************************************************************************/
zbx_pb_history_data_t	*zbx_pb_history_open(void)
{
	zbx_pb_history_data_t	*data;
	zbx_pb_t		*pb_data = get_pb_data();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	data = (zbx_pb_history_data_t *)zbx_malloc(NULL, sizeof(zbx_pb_history_data_t));

	pb_lock();

	data->handleid = pb_register_handle(pb_data, &(pb_data->history_handleids));

	if (PB_DATABASE == (data->state = get_pb_dst(pb_data->state)))
		pb_data->db_handles_num++;

	pb_unlock();

	if (PB_MEMORY == data->state)
	{
		zbx_list_create(&data->rows);
		data->rows_num = 0;
	}

	if (PB_DATABASE == get_pb_dst(data->state))
	{
		zbx_db_insert_prepare(&data->db_insert, "proxy_history", "id", "itemid", "clock", "timestamp", "source",
				"severity", "value", "logeventid", "ns", "state", "lastlogsize", "mtime", "flags",
				"write_clock", (char *)NULL);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached history data and free the handle                 *
 *                                                                            *
 **********************************************************************/
void	zbx_pb_history_close(zbx_pb_history_data_t *data)
{
	zbx_uint64_t	lastid = 0;
	zbx_pb_t	*pb_data = get_pb_data();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (PB_MEMORY == data->state)
	{
		zbx_list_item_t	*next = NULL;

		pb_lock();

		if (0 == data->rows_num)
			goto out;

		pb_history_set_row_ids(&data->rows, data->rows_num);

		if (PB_MEMORY == pb_data->state && SUCCEED != pb_history_check_age(pb_data))
		{
			pd_fallback_to_database(pb_data, "cached records are too old");
		}
		else if (PB_MEMORY == get_pb_dst(pb_data->state))
		{
			if (NULL == (next = pb_history_add_rows_mem(pb_data, &data->rows)))
				goto out;

			if (PB_DATABASE_MEMORY == pb_data->state)
			{
				pd_fallback_to_database(pb_data, "not enough space to complete transition to"
						" memory mode");
			}
			else
			{
				/* initiate transition to database cache */
				pb_set_state(pb_data, PB_MEMORY_DATABASE, "not enough space");
			}
		}

		/* not all rows were added to memory cache - flush them to database */
		pb_data->db_handles_num++;
		pb_unlock();

		do
		{
			zbx_db_begin();
			pb_history_add_rows_db(&data->rows, next, &lastid);
		}
		while (ZBX_DB_DOWN == zbx_db_commit());
	}
	else
	{
		zbx_db_insert_autoincrement(&data->db_insert, "id");

		do
		{
			zbx_db_begin();
			(void)zbx_db_insert_execute(&data->db_insert);
		}
		while (ZBX_DB_DOWN == zbx_db_commit());

		lastid = zbx_db_insert_get_lastid(&data->db_insert);
	}

	pb_lock();

	if (pb_data->history_lastid_db < lastid)
		pb_data->history_lastid_db = lastid;

	pb_data->db_handles_num--;
out:
	pb_deregister_handle(&(pb_data->history_handleids), data->handleid);
	pb_unlock();

	pb_history_data_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write normal value into history data cache                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_history_write_value(zbx_pb_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, time_t now)
{
	pb_history_add_value(data, itemid, state, value, ts, flags, 0, 0, 0, 0, 0, "", now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write value with metadata into history data cache                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_history_write_meta_value(zbx_pb_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, zbx_uint64_t lastlogsize, int mtime, int timestamp, int logeventid,
		int severity, const char *source, time_t now)
{
	pb_history_add_value(data, itemid, state, value, ts, flags, lastlogsize, mtime, timestamp, logeventid,
			severity, source, now);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get history data for sending to server                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_pb_history_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int	state, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, *lastid);

	pb_lock();

	if (PB_MEMORY == (state = get_pb_src(get_pb_data()->state)))
		ret = pb_history_get_mem(get_pb_data(), j, lastid, more);

	pb_unlock();

	if (PB_MEMORY != state)
		ret = pb_history_get_db(j, lastid, more);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update database lastid/clear memory records                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_set_history_lastid(const zbx_uint64_t lastid)
{
	int		state;
	zbx_pb_t	*pb_data = get_pb_data();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, lastid);

	pb_lock();

	pb_data->history_lastid_sent = lastid;

	if (PB_MEMORY == (state = get_pb_src(pb_data->state)))
		pb_history_clear(pb_data, lastid);

	pb_unlock();

	if (PB_DATABASE == state)
		pb_history_set_lastid(lastid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: return number of unsent history rows                              *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_pb_history_get_unsent_num(void)
{
	zbx_uint64_t	lastid_sent, lastid;
	zbx_pb_t	*pb_data = get_pb_data();

	pb_lock();

	lastid_sent = pb_data->history_lastid_sent;
	lastid = MAX(pb_data->history_lastid_db, pb_data->history_lastid_mem);

	pb_unlock();

	return (lastid_sent < lastid ? lastid - lastid_sent : 0);
}
