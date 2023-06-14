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

#include "pdc_autoreg.h"
#include "zbxproxydatacache.h"
#include "zbxcacheconfig.h"
#include "proxydatacache.h"
#include "zbxdbhigh.h"
#include "zbx_item_constants.h"
#include "zbx_host_constants.h"

struct zbx_pdc_history_data
{
	zbx_pdc_state_t			state;
	zbx_vector_pdc_history_ptr_t	rows;
	zbx_db_insert_t			db_insert;
};

static void	pdc_history_free(zbx_pdc_history_t *row)
{
	zbx_free(row);
}

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

	data = (zbx_pdc_history_data_t *)zbx_malloc(NULL, sizeof(zbx_pdc_history_data_t));
	zbx_vector_pdc_history_ptr_create(&data->rows);
	data->state = pdc_dst[pdc_cache->state];

	if (PDC_MEMORY == data->state)
	{
		/*
		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, forcing database cache");
		data->state = PDC_DATABASE_ONLY;
		*/

		/* zbx_vector_pdc_history_ptr_create(&data->rows); */
	}

	if (PDC_DATABASE == pdc_dst[pdc_cache->state])
	{
		zbx_db_insert_prepare(&data->db_insert, "proxy_history", "id", "itemid", "clock", "timestamp", "source",
				"severity", "value", "logeventid", "ns", "state", "lastlogsize", "mtime", "flags",
				"write_clock", NULL);
	}

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached history data and free the handle                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_history_close(zbx_pdc_history_data_t *data)
{
	int	state;

	pdc_lock();

	if (PDC_MEMORY == (state = data->state))
	{
		if (PDC_MEMORY == (state = pdc_dst[pdc_cache->state]))
		{
			/* TODO: flush into cache */
		}
		else
			pdc_cache->db_handles_num++;

		pdc_unlock();

		if (PDC_DATABASE == state)
		{
			/* TODO: flush into database */
		}

		zbx_vector_pdc_history_ptr_clear_ext(&data->rows, pdc_history_free);
		zbx_vector_pdc_history_ptr_destroy(&data->rows);
	}
	else
	{
		pdc_unlock();

		zbx_db_insert_autoincrement(&data->db_insert, "id");
		(void)zbx_db_insert_execute(&data->db_insert);
		zbx_db_insert_clean(&data->db_insert);
	}

	if (PDC_DATABASE == state)
	{
		pdc_lock();
		pdc_cache->db_handles_num--;
		pdc_unlock();
	}

	zbx_free(data);
}

static void	pdc_history_add_value(zbx_pdc_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, zbx_uint64_t lastlogsize, int mtime, int timestamp, int logeventid,
		int severity, const char *source)
{
	if (PDC_MEMORY == data->state)
	{
		zbx_pdc_history_t	*row;

		row = (zbx_pdc_history_t *)zbx_malloc(NULL, sizeof(zbx_pdc_history_t));

		row->itemid = itemid;
		row->state = state;
		row->ts = *ts;
		row->flags = flags;
		row->lastlogsize = lastlogsize;
		row->mtime = mtime;
		row->timestamp = timestamp;
		row->logeventid = logeventid;
		row->severity = severity;

		/* TODO: use string pool
		row->value = value;
		row->source = source;
		*/

		zbx_vector_pdc_history_ptr_append(&data->rows, row);
	}
	else
	{
		zbx_db_insert_add_values(&data->db_insert, __UINT64_C(0), itemid, ts->sec, timestamp, source, severity,
				value, logeventid, ts->ns, state, lastlogsize, mtime, flags, time(NULL));

	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: write normal value into history data cache                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_history_write_value(zbx_pdc_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags)
{
	pdc_history_add_value(data, itemid, state, value, ts, flags, 0, 0, 0, 0, 0, "");
}

/******************************************************************************
 *                                                                            *
 * Purpose: write value with metadata into history data cache                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_history_write_meta_value(zbx_pdc_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, zbx_uint64_t lastlogsize, int mtime, int timestamp, int logeventid,
		int severity, const char *source)
{
	pdc_history_add_value(data, itemid, state, value, ts, flags, lastlogsize, mtime, timestamp, logeventid,
			severity, source);
}

/******************************************************************************
 *                                                                            *
 * Purpose: read proxy history data from the database                         *
 *                                                                            *
 * Parameters: lastid             - [IN] the id of last processed proxy       *
 *                                       history record                       *
 *             data               - [IN/OUT] the proxy history data buffer    *
 *             data_alloc         - [IN/OUT] the size of proxy history data   *
 *                                           buffer                           *
 *             string_buffer      - [IN/OUT] the string buffer                *
 *             string_buffer_size - [IN/OUT] the size of string buffer        *
 *             more               - [OUT] set to ZBX_PROXY_DATA_MORE if there *
 *                                        might be more data to read          *
 *                                                                            *
 * Return value: The number of records read.                                  *
 *                                                                            *
 ******************************************************************************/
static int	pdc_get_history_rows(zbx_uint64_t lastid, zbx_pdc_history_t **rows, size_t *data_alloc,
		char **string_buffer, size_t *string_buffer_alloc, int *more)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0, data_num = 0;
	size_t			string_buffer_offset = 0;
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

	result = zbx_db_select_n(sql, ZBX_MAX_HRECORDS - data_num);

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

		if (*data_alloc == data_num)
		{
			*data_alloc *= 2;
			*rows = (zbx_pdc_history_t *)zbx_realloc(*rows, sizeof(zbx_pdc_history_t) * *data_alloc);
		}

		hist = *rows + data_num++;
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
				size_t	len1, len2;

				hist->timestamp = atoi(row[4]);
				hist->severity = atoi(row[6]);
				hist->logeventid = atoi(row[8]);

				len1 = strlen(row[5]) + 1;
				len2 = strlen(row[7]) + 1;

				if (*string_buffer_alloc < string_buffer_offset + len1 + len2)
				{
					while (*string_buffer_alloc < string_buffer_offset + len1 + len2)
						*string_buffer_alloc += ZBX_KIBIBYTE;

					*string_buffer = (char *)zbx_realloc(*string_buffer, *string_buffer_alloc);
				}

				hist->source.offset = string_buffer_offset;
				memcpy(*string_buffer + hist->source.offset, row[5], len1);
				string_buffer_offset += len1;

				hist->value.offset = string_buffer_offset;
				memcpy(*string_buffer + hist->value.offset, row[7], len2);
				string_buffer_offset += len2;
			}

			if (0 != (hist->flags & ZBX_PROXY_HISTORY_FLAG_META))
			{
				ZBX_STR2UINT64(hist->lastlogsize, row[10]);
				hist->mtime = atoi(row[11]);
			}
		}

		lastid = id;
	}
	zbx_db_free_result(result);

	if (ZBX_MAX_HRECORDS != data_num && 1 == retries)
		*more = ZBX_PROXY_DATA_DONE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() data_num:" ZBX_FS_SIZE_T, __func__, data_num);

	return data_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history records to output json                                *
 *                                                                            *
 * Parameters: j             - [IN] the json output buffer                    *
 *             records_num   - [IN] the total number of records added         *
 *             dc_items      - [IN] the item configuration data               *
 *             errcodes      - [IN] the item configuration status codes       *
 *             records       - [IN] the records to add                        *
 *             string_buffer - [IN] the string buffer holding string values   *
 *             lastid        - [OUT] the id of last added record              *
 *                                                                            *
 * Return value: The total number of records added.                           *
 *                                                                            *
 ******************************************************************************/
static int	pdc_export_history(struct zbx_json *j, int records_num, const zbx_dc_item_t *dc_items,
		const int *errcodes, const zbx_vector_pdc_history_ptr_t *records, const char *string_buffer,
		zbx_uint64_t *lastid)
{
	int			i;
	const zbx_pdc_history_t	*row;

	for (i = records->values_num - 1; i >= 0; i--)
	{
		row = records->values[i];
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

				if ('\0' != string_buffer[row->source.offset])
				{
					zbx_json_addstring(j, ZBX_PROTO_TAG_LOGSOURCE,
							string_buffer + row->source.offset, ZBX_JSON_TYPE_STRING);
				}

				if (0 != row->severity)
					zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGSEVERITY, row->severity);

				if (0 != row->logeventid)
					zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGEVENTID, row->logeventid);

				zbx_json_addstring(j, ZBX_PROTO_TAG_VALUE, string_buffer + row->value.offset,
						ZBX_JSON_TYPE_STRING);
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

	return records_num;
}

static int	pdc_get_history(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int				records_num = 0, data_num, i, *errcodes = NULL, items_alloc = 0;
	zbx_uint64_t			id;
	zbx_hashset_t			itemids_added;
	zbx_pdc_history_t		*rows;
	char				*string_buffer;
	size_t				data_alloc = 16, string_buffer_alloc = ZBX_KIBIBYTE;
	zbx_vector_uint64_t		itemids;
	zbx_vector_pdc_history_ptr_t	records;
	zbx_dc_item_t			*dc_items = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);
	zbx_vector_pdc_history_ptr_create(&records);
	rows = (zbx_pdc_history_t *)zbx_malloc(NULL, data_alloc * sizeof(zbx_pdc_history_t));
	string_buffer = (char *)zbx_malloc(NULL, string_buffer_alloc);

	*more = ZBX_PROXY_DATA_MORE;
	id = pdc_get_lastid("proxy_history", "history_lastid");

	zbx_hashset_create(&itemids_added, data_alloc, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset && ZBX_MAX_HRECORDS_TOTAL > records_num &&
			0 != (data_num = pdc_get_history_rows(id, &rows, &data_alloc, &string_buffer,
					&string_buffer_alloc, more)))
	{
		zbx_vector_uint64_reserve(&itemids, data_num);
		zbx_vector_pdc_history_ptr_reserve(&records, data_num);

		/* filter out duplicate novalue updates */
		for (i = data_num - 1; i >= 0; i--)
		{
			if (ZBX_PROXY_HISTORY_FLAG_NOVALUE == (rows[i].flags & ZBX_PROXY_HISTORY_MASK_NOVALUE))
			{
				if (NULL != zbx_hashset_search(&itemids_added, &rows[i].itemid))
					continue;

				zbx_hashset_insert(&itemids_added, &rows[i].itemid, sizeof(rows[i].itemid));
			}

			zbx_vector_pdc_history_ptr_append(&records, &rows[i]);
			zbx_vector_uint64_append(&itemids, rows[i].itemid);
		}

		/* append history records to json */

		if (itemids.values_num > items_alloc)
		{
			items_alloc = itemids.values_num;
			dc_items = (zbx_dc_item_t *)zbx_realloc(dc_items, items_alloc * sizeof(zbx_dc_item_t));
			errcodes = (int *)zbx_realloc(errcodes, items_alloc * sizeof(int));
		}

		zbx_dc_config_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);

		records_num = pdc_export_history(j, records_num, dc_items, errcodes, &records, string_buffer, lastid);
		zbx_dc_config_clean_items(dc_items, errcodes, itemids.values_num);

		/* got less data than requested - either no more data to read or the history is full of */
		/* holes. In this case send retrieved data before attempting to read/wait for more data */
		if (ZBX_MAX_HRECORDS > data_num)
			break;

		zbx_vector_uint64_clear(&itemids);
		zbx_vector_pdc_history_ptr_clear(&records);
		zbx_hashset_clear(&itemids_added);
		id = *lastid;
	}

	if (0 != records_num)
		zbx_json_close(j);

	zbx_hashset_destroy(&itemids_added);

	zbx_free(dc_items);
	zbx_free(errcodes);
	zbx_free(rows);
	zbx_free(string_buffer);
	zbx_vector_pdc_history_ptr_destroy(&records);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() lastid:" ZBX_FS_UI64 " records_num:%d size:~" ZBX_FS_SIZE_T " more:%d",
			__func__, *lastid, records_num, j->buffer_offset, *more);

	return records_num;
}

int	zbx_pdc_get_history(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	if (PDC_MEMORY == pdc_src[pdc_cache->state])
	{
		/*
		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, forcing database cache");
		pdc_cache->state = PDC_DATABASE_ONLY;
		*/
	}

	if (PDC_DATABASE == pdc_src[pdc_cache->state])
	{
		return pdc_get_history(j, lastid, more);
	}
	else
		return 0;
}

void	zbx_pdc_set_history_lastid(const zbx_uint64_t lastid)
{
	pdc_set_lastid("proxy_history", "history_lastid", lastid);
}

void	pdc_history_flush(zbx_pdc_t *pdc)
{
	if (0 != pdc->history_lastid)
		pdc_set_lastid("proxy_history", "history_lastid", pdc->history_lastid);
}
