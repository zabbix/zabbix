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

struct zbx_pdc_discovery_data
{
	zbx_pdc_state_t			state;
	zbx_vector_pdc_discovery_ptr_t	rows;
	zbx_db_insert_t			db_insert;
};

static void	pdc_discovery_free(zbx_pdc_discovery_t *row)
{
	zbx_free(row->ip);
	zbx_free(row->dns);
	zbx_free(row->value);
	zbx_free(row);
}

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

	data = (zbx_pdc_discovery_data_t *)zbx_malloc(NULL, sizeof(zbx_pdc_discovery_data_t));
	data->state = pdc_dst[pdc_cache->state];

	if (PDC_MEMORY == data->state)
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, forcing database cache");
		data->state = PDC_DATABASE_ONLY;

		/* zbx_vector_pdc_discovery_ptr_create(&data->rows); */
	}

	if (PDC_DATABASE == pdc_dst[pdc_cache->state])
	{
		zbx_db_insert_prepare(&data->db_insert, "proxy_dhistory", "id", "clock", "druleid", "ip", "port",
				"value", "status", "dcheckid", "dns", NULL);
	}

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached discovery data and free the handle               *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_discovery_close(zbx_pdc_discovery_data_t *data)
{
	if (PDC_MEMORY == data->state)
	{
		zbx_vector_pdc_discovery_ptr_clear_ext(&data->rows, pdc_discovery_free);
		zbx_vector_pdc_discovery_ptr_destroy(&data->rows);
	}
	else
	{
		zbx_db_insert_autoincrement(&data->db_insert, "id");
		(void)zbx_db_insert_execute(&data->db_insert);
		zbx_db_insert_clean(&data->db_insert);
	}

	zbx_free(data);
}

static void	pdc_discovery_write_service(zbx_pdc_discovery_data_t *data, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		const char *ip, const char *dns, int port, int status, const char *value, int clock)
{
	if (PDC_MEMORY == data->state)
	{
		zbx_pdc_discovery_t	*row;

		row = (zbx_pdc_discovery_t *)zbx_malloc(NULL, sizeof(zbx_pdc_discovery_t));
		row->druleid = druleid;
		row->dcheckid = dcheckid;
		row->ip = zbx_strdup(NULL, ip);
		row->dns = zbx_strdup(NULL, dns);
		row->port = port;
		row->status = status;
		row->value = zbx_strdup(NULL, value);
		row->clock = clock;
		zbx_vector_pdc_discovery_ptr_append(&data->rows, row);
	}
	else
	{
		zbx_db_insert_add_values(&data->db_insert, __UINT64_C(0), clock, druleid, ip, port, value, status,
				dcheckid, dns);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: write service data into discovery data cache                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_discovery_write_service(zbx_pdc_discovery_data_t *data, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		const char *ip, const char *dns, int port, int status, const char *value, int clock)
{
	pdc_discovery_write_service(data, druleid, dcheckid, ip, dns, port, status, value, clock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into discovery data cache                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_discovery_write_host(zbx_pdc_discovery_data_t *data, zbx_uint64_t druleid, const char *ip,
		const char *dns, int status, int clock)
{
	pdc_discovery_write_service(data, druleid, 0, ip, dns, 0, status, "", clock);
}

static int	pdc_get_discovery(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
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
		pdc_get_rows(j, ZBX_PROTO_TAG_DISCOVERY_DATA, &dht, lastid, &id, &records_num, more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	zbx_pdc_get_discovery(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	if (PDC_MEMORY == pdc_src[pdc_cache->state])
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, forcing database cache");
		pdc_cache->state = PDC_DATABASE_ONLY;
	}

	if (PDC_DATABASE == pdc_src[pdc_cache->state])
	{
		return pdc_get_discovery(j, lastid, more);
	}
	else
		return 0;
}

void	zbx_pdc_set_discovery_lastid(const zbx_uint64_t lastid)
{
	pdc_set_lastid(dht.table, dht.lastidfield, lastid);
}
