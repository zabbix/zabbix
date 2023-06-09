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
	zbx_vector_pdc_history_ptr_t	rows;
};

static void	pdc_history_free(zbx_pdc_history_t *row)
{
	zbx_free(row);
}

static void	pdc_history_flush_db(zbx_pdc_history_data_t *data)
{
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	id;
	int		now;

	if (0 == data->rows.values_num)
		return;

	now = (int)time(NULL);

	id = zbx_db_get_maxid_num("proxy_history", data->rows.values_num);

	zbx_db_insert_prepare(&db_insert, "proxy_history", "id", "itemid", "clock", "timestamp", "source", "serverity",
			"value", "logeventid", "ns", "state", "lastlogsize", "mtime", "flags", "write_clock", NULL);

	for (int i = 0; i < data->rows.values_num; i++)
	{
		zbx_pdc_history_t	*row = data->rows.values[i];

		zbx_db_insert_add_values(&db_insert, id++, row->itemid, row->ts.sec, row->timestamp, row->source,
				row->severity, row->value, row->logeventid, row->ts.ns, row->state, row->lastlogsize,
				row->mtime, row->flags, now);
	}

	(void)zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
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

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached history data and free the handle                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_history_close(zbx_pdc_history_data_t *data)
{
	if (PDC_MEMORY == pdc_dst[pdc_cache->state])
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, switching to database");
		pdc_cache->state = PDC_DATABASE_ONLY;

		/* TODO: change to 'else' after memory cache implementation */
	}

	if (PDC_DATABASE == pdc_dst[pdc_cache->state])
			pdc_history_flush_db(data);

	zbx_vector_pdc_history_ptr_clear_ext(&data->rows, pdc_history_free);
	zbx_vector_pdc_history_ptr_destroy(&data->rows);
	zbx_free(data);
}

static void	pdc_history_add_value(zbx_pdc_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, zbx_uint64_t lastlogsize, int mtime, int timestamp, int logeventid,
		int severity, const char *source)
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

