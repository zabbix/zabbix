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

#include "pdc_discovery.h"
#include "zbxproxydatacache.h"
#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxcachehistory.h"

static zbx_history_table_t	dht = {
	"proxy_dhistory", "dhistory_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"druleid",		ZBX_PROTO_TAG_DRULE,		ZBX_JSON_TYPE_INT,	NULL},
		{"dcheckid",		ZBX_PROTO_TAG_DCHECK,		ZBX_JSON_TYPE_INT,	NULL},
		{"ip",			ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	NULL},
		{"dns",			ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	NULL},
		{"port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_INT,	"0"},
		{"value",		ZBX_PROTO_TAG_VALUE,		ZBX_JSON_TYPE_STRING,	""},
		{"status",		ZBX_PROTO_TAG_STATUS,		ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

static void	pdc_discovery_add_rows_db(zbx_list_t *rows, zbx_list_item_t *next, zbx_uint64_t *lastid);

struct zbx_pdc_discovery_data
{
	zbx_pdc_state_t	state;
	zbx_list_t	rows;
	int		rows_num;
	zbx_db_insert_t	db_insert;
};

static void	pdc_list_free_discovery(zbx_list_t *list, zbx_pdc_discovery_t *row)
{
	if (NULL != row->ip)
		list->mem_free_func(row->ip);
	if (NULL != row->dns)
		list->mem_free_func(row->dns);
	if (NULL != row->value)
		list->mem_free_func(row->value);
	list->mem_free_func(row);
}

static int	pdc_get_discovery_db(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	id = pdc_get_lastid(dht.table, dht.lastidfield);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		pdc_get_rows_db(j, ZBX_PROTO_TAG_DISCOVERY_DATA, &dht, lastid, &id, &records_num, more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

static void	pdc_discovery_write_row(zbx_pdc_discovery_data_t *data, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		const char *ip, const char *dns, int port, int status, const char *value, int clock)
{
	if (PDC_MEMORY == data->state)
	{
		zbx_pdc_discovery_t	*row;

		row = (zbx_pdc_discovery_t *)zbx_malloc(NULL, sizeof(zbx_pdc_discovery_t));
		row->id = 0;
		row->druleid = druleid;
		row->dcheckid = dcheckid;
		row->ip = zbx_strdup(NULL, ip);
		row->dns = zbx_strdup(NULL, dns);
		row->port = port;
		row->status = status;
		row->value = zbx_strdup(NULL, value);
		row->clock = clock;

		zbx_list_append(&data->rows, row, NULL);
		data->rows_num++;
	}
	else
	{
		zbx_db_insert_add_values(&data->db_insert, __UINT64_C(0), clock, druleid, ip, port, value, status,
				dcheckid, dns);
	}
}

void	pdc_discovery_flush(zbx_pdc_t *pdc)
{
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pdc_discovery_add_rows_db(&pdc->discovery, NULL, &lastid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add discovery row to memory cache                                 *
 *                                                                            *
 * Parameters: pdc - [IN] proxy data cache                                    *
 *             src - [IN] row to add                                          *
 *             now - [IN] current time                                        *
 *                                                                            *
 * Return value: SUCCEED - the row was cached successfully                    *
 *               FAIL    - not enough memory in cache                         *
 *                                                                            *
 ******************************************************************************/
static int	pdc_discovery_add_row_mem(zbx_pdc_t *pdc, zbx_pdc_discovery_t *src, time_t now)
{
	zbx_pdc_discovery_t	*row;
	int			ret = FAIL;

	if (NULL == (row = (zbx_pdc_discovery_t *)pdc_malloc(sizeof(zbx_pdc_discovery_t))))
		return FAIL;

	memcpy(row, src, sizeof(zbx_pdc_discovery_t));

	if (NULL == (row->ip = pdc_strdup(src->ip)))
	{
		row->dns = NULL;
		row->value = NULL;
		goto out;
	}

	if (NULL == (row->dns = pdc_strdup(src->dns)))
	{
		row->value = NULL;
		goto out;
	}

	if (NULL == (row->value = pdc_strdup(src->value)))
		goto out;

	row->write_clock = now;

	ret = zbx_list_append(&pdc->discovery, row, NULL);
out:
	if (SUCCEED != ret)
		pdc_list_free_discovery(&pdc->discovery, row);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set ids to new discovery rows                                     *
 *                                                                            *
 ******************************************************************************/
static void	pdc_discovery_set_row_ids(zbx_list_t *rows, int rows_num)
{
	zbx_uint64_t		id;
	zbx_pdc_discovery_t	*row;
	zbx_list_iterator_t	li;

	id = zbx_dc_get_nextid("proxy_dhistory", rows_num);
	zbx_list_iterator_init(rows, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		(void)zbx_list_iterator_peek(&li, (void **)&row);
		row->id = id++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add discovery rows to memory cache                                *
 *                                                                            *
 * Parameters: pdc  - [IN] proxy data cache                                   *
 *             rows - [IN] rows to add                                        *
 *                                                                            *
 * Return value: NULL if all rows were added successfully. Otherwise the list *
 *               item of first failed row is returned                         *
 *                                                                            *
 ******************************************************************************/
static zbx_list_item_t	*pdc_discovery_add_rows_mem(zbx_pdc_t *pdc, zbx_list_t *rows)
{
	zbx_list_iterator_t	li;
	zbx_pdc_discovery_t	*row;
	int			rows_num = 0;
	time_t			now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);
	zbx_list_iterator_init(rows, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		(void)zbx_list_iterator_peek(&li, (void **)&row);

		if (SUCCEED != pdc_discovery_add_row_mem(pdc, row, now))
			break;

		rows_num++;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows_num:%d next:%p", __func__, rows_num, li.current);

	return li.current;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add discovery rows to database cache                              *
 *                                                                            *
 * Parameters: rows   - [IN] rows to add                                      *
 *             next   - [IN] next row to add                                  *
 *             lastid - [OUT] last inserted id                                *
 *                                                                            *
 ******************************************************************************/
static void	pdc_discovery_add_rows_db(zbx_list_t *rows, zbx_list_item_t *next, zbx_uint64_t *lastid)
{
	zbx_list_iterator_t	li;
	zbx_pdc_discovery_t	*row;
	zbx_db_insert_t		db_insert;
	int			rows_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() next:%p", __func__, next);

	if (SUCCEED == zbx_list_iterator_init_with(rows, next, &li))
	{
		zbx_db_insert_prepare(&db_insert, "proxy_dhistory", "id", "clock", "druleid", "ip", "port", "value", "status",
				"dcheckid", "dns", NULL);

		do
		{
			(void)zbx_list_iterator_peek(&li, (void **)&row);
			zbx_db_insert_add_values(&db_insert, row->id, row->clock, row->druleid, row->ip, row->port,
					row->value, row->status, row->dcheckid, row->dns);
			rows_num++;
			*lastid = row->id;
		}
		while (SUCCEED == zbx_list_iterator_next(&li));

		(void)zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows_num:%d", __func__, rows_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get discovery records from memory cache                           *
 *                                                                            *
 ******************************************************************************/
static int	pdc_discovery_get_mem(zbx_pdc_t *pdc, struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int			records_num = 0;
	zbx_list_iterator_t	li;
	zbx_pdc_discovery_t	*row;
	void			*ptr;

	if (SUCCEED == zbx_list_peek(&pdc->discovery, &ptr))
	{
		zbx_json_addarray(j, ZBX_PROTO_TAG_DISCOVERY_DATA);
		zbx_list_iterator_init(&pdc->discovery, &li);

		while (SUCCEED == zbx_list_iterator_next(&li))
		{
			if (ZBX_DATA_JSON_BATCH_LIMIT <= j->buffer_offset || records_num == ZBX_MAX_HRECORDS_TOTAL)
			{
				*more = 1;
				break;
			}

			(void)zbx_list_iterator_peek(&li, (void **)&row);

			zbx_json_addobject(j, NULL);
			zbx_json_addint64(j, ZBX_PROTO_TAG_CLOCK, row->clock);
			zbx_json_adduint64(j, ZBX_PROTO_TAG_DRULE, row->druleid);
			zbx_json_adduint64(j, ZBX_PROTO_TAG_DCHECK, row->dcheckid);
			zbx_json_addstring(j, ZBX_PROTO_TAG_IP, row->ip, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(j, ZBX_PROTO_TAG_DNS, row->dns, ZBX_JSON_TYPE_STRING);
			zbx_json_addint64(j, ZBX_PROTO_TAG_PORT, row->port);
			zbx_json_addstring(j, ZBX_PROTO_TAG_VALUE, row->value, ZBX_JSON_TYPE_STRING);
			zbx_json_addint64(j, ZBX_PROTO_TAG_STATUS, row->status);
			zbx_json_close(j);

			records_num++;
			*lastid = row->id;
		}

		zbx_json_close(j);
	}

	return records_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear sent discovery records                                      *
 *                                                                            *
 ******************************************************************************/
void	pdc_discovery_clear(zbx_pdc_t *pdc, zbx_uint64_t lastid)
{
	zbx_pdc_discovery_t	*row;

	while (SUCCEED == zbx_list_peek(&pdc->discovery, (void **)&row))
	{
		if (row->id > lastid)
			break;

		zbx_list_pop(&pdc->discovery, NULL);
		pdc_list_free_discovery(&pdc->discovery, row);
	}
}

static void	pdc_discovery_data_free(zbx_pdc_discovery_data_t *data)
{

	if (PDC_MEMORY == data->state)
	{
		zbx_pdc_discovery_t	*row;

		while (SUCCEED == zbx_list_pop(&data->rows, (void **)&row))
			pdc_list_free_discovery(&data->rows, row);

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
int	pdc_discovery_check_age(zbx_pdc_t *pdc)
{
	zbx_pdc_discovery_t	*row;

	if (SUCCEED != zbx_list_peek(&pdc->discovery, (void **)&row) || time(NULL) - row->write_clock < pdc->max_age)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: write discovery last sent record id to database                   *
 *                                                                            *
 ******************************************************************************/
void	pdc_discovery_set_lastid(zbx_uint64_t lastid)
{
	pdc_set_lastid(dht.table, dht.lastidfield, lastid);
}

/* public api */

/******************************************************************************
 *                                                                            *
 * Purpose: open discovery data cache                                         *
 *                                                                            *
 * Return value: The discovery data cache handle                              *
 *                                                                            *
 ******************************************************************************/
zbx_pdc_discovery_data_t	*zbx_pdc_discovery_open(void)
{
	zbx_pdc_discovery_data_t	*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	data = (zbx_pdc_discovery_data_t *)zbx_malloc(NULL, sizeof(zbx_pdc_discovery_data_t));

	pdc_lock();
	if (PDC_DATABASE == (data->state = pdc_dst[pdc_cache->state]))
		pdc_cache->db_handles_num++;
	pdc_unlock();

	if (PDC_MEMORY == data->state)
	{
		zbx_list_create(&data->rows);
		data->rows_num = 0;
	}
	else
	{
		zbx_db_insert_prepare(&data->db_insert, "proxy_dhistory", "id", "clock", "druleid", "ip", "port",
				"value", "status", "dcheckid", "dns", NULL);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached discovery data and free the handle               *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_discovery_close(zbx_pdc_discovery_data_t *data)
{
	zbx_uint64_t	lastid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (PDC_MEMORY == data->state)
	{
		zbx_list_item_t	*next = NULL;

		if (0 == data->rows_num)
			goto out;

		pdc_discovery_set_row_ids(&data->rows, data->rows_num);

		pdc_lock();

		if (PDC_MEMORY == pdc_cache->state && SUCCEED != pdc_discovery_check_age(pdc_cache))
		{
			pdc_fallback_to_database(pdc_cache, "cached records are too old");
		}
		else if (PDC_MEMORY == pdc_dst[pdc_cache->state])
		{
			if (NULL == (next = pdc_discovery_add_rows_mem(pdc_cache, &data->rows)))
			{
				pdc_unlock();
				goto out;
			}

			if (PDC_DATABASE_MEMORY == pdc_cache->state)
			{
				pdc_fallback_to_database(pdc_cache, "not enough space to complete transition to memory"
						" mode");
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

		do
		{
			zbx_db_begin();
			pdc_discovery_add_rows_db(&data->rows, next, &lastid);
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

	pdc_lock();

	if (pdc_cache->discovery_lastid_db < lastid)
		pdc_cache->discovery_lastid_db = lastid;

	pdc_cache->db_handles_num--;

	pdc_unlock();
out:
	pdc_discovery_data_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write service data into discovery data cache                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_discovery_write_service(zbx_pdc_discovery_data_t *data, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		const char *ip, const char *dns, int port, int status, const char *value, int clock)
{
	pdc_discovery_write_row(data, druleid, dcheckid, ip, dns, port, status, value, clock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into discovery data cache                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_discovery_write_host(zbx_pdc_discovery_data_t *data, zbx_uint64_t druleid, const char *ip,
		const char *dns, int status, int clock)
{
	pdc_discovery_write_row(data, druleid, 0, ip, dns, 0, status, "", clock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get discovery rows for sending to server                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_pdc_discovery_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int	state, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64 ", more:" ZBX_FS_UI64, __func__, *lastid, *more);

	pdc_lock();

	if (PDC_MEMORY == (state = pdc_src[pdc_cache->state]))
		ret = pdc_discovery_get_mem(pdc_cache, j, lastid, more);

	pdc_unlock();

	if (PDC_DATABASE == state)
		ret = pdc_get_discovery_db(j, lastid, more);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update database lastid/clear memory records                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_discovery_set_lastid(const zbx_uint64_t lastid)
{
	int	state;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, lastid);

	pdc_lock();

	pdc_cache->discovery_lastid_sent = lastid;

	if (PDC_MEMORY == (state = pdc_src[pdc_cache->state]))
		pdc_discovery_clear(pdc_cache, lastid);

	pdc_unlock();

	if (PDC_DATABASE == state)
		pdc_discovery_set_lastid(lastid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
