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
#include "proxydatacache.h"
#include "zbxdbhigh.h"

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
		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, forcing database cache");
		data->state = PDC_DATABASE_ONLY;

		/* zbx_vector_pdc_history_ptr_create(&data->rows); */
	}

	if (PDC_DATABASE == pdc_dst[pdc_cache->state])
	{
		zbx_db_insert_prepare(&data->db_insert, "proxy_history", "id", "itemid", "clock", "timestamp", "source",
				"serverity", "value", "logeventid", "ns", "state", "lastlogsize", "mtime", "flags",
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
	if (PDC_MEMORY == data->state)
	{
		zbx_vector_pdc_history_ptr_clear_ext(&data->rows, pdc_history_free);
		zbx_vector_pdc_history_ptr_destroy(&data->rows);
	}
	else
	{
		zbx_db_insert_autoincrement(&data->db_insert, "id");
		(void)zbx_db_insert_execute(&data->db_insert);
		zbx_db_insert_clean(&data->db_insert);
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
		row->value = value;
		row->ts = *ts;
		row->flags = flags;
		row->lastlogsize = lastlogsize;
		row->mtime = mtime;
		row->timestamp = timestamp;
		row->logeventid = logeventid;
		row->severity = severity;
		row->source = source;

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

