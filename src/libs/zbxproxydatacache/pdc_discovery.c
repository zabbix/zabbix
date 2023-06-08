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

struct zbx_pdc_discovery_data
{
	zbx_vector_pdc_discovery_ptr_t	rows;
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
 * Purpose: flush locally cached discovery data to database                   *
 *                                                                            *
 ******************************************************************************/
static void	pdc_discovery_flush_db(zbx_pdc_discovery_data_t *data)
{
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	id;

	if (0 == data->rows.values_num)
		return;

	id = zbx_db_get_maxid_num("proxy_dhistory", data->rows.values_num);

	zbx_db_insert_prepare(&db_insert, "proxy_dhistory", "id", "clock", "druleid", "ip", "port", "value", "status",
			"dcheckid", "dns", NULL);

	for (int i = 0; i < data->rows.values_num; i++)
	{
		zbx_pdc_discovery_t	*row = data->rows.values[i];

		zbx_db_insert_add_values(&db_insert, id++, row->clock, row->druleid, row->ip, row->port, row->value,
				row->status, row->dcheckid, row->dns);
	}

	(void)zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: open discovery data cache                                         *
 *                                                                            *
 * Return value: The discovery data cache handle                              *
 *                                                                            *
 ******************************************************************************/
zbx_pdc_discovery_data_t	*pdc_discovery_open(void)
{
	zbx_pdc_discovery_data_t	*data;

	data = (zbx_pdc_discovery_data_t *)zbx_malloc(NULL, sizeof(zbx_pdc_discovery_data_t));
	zbx_vector_pdc_discovery_ptr_create(&data->rows);

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached discovery data and free the handle               *
 *                                                                            *
 ******************************************************************************/
void	pdc_discovery_close(zbx_pdc_t *pdc, zbx_pdc_discovery_data_t *data)
{
	switch (pdc->state)
	{
		case PDC_MEMORY:
		case PDC_DATABASE_MEMORY:
			zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, switching to database");
			pdc->state = PDC_DATABASE_ONLY;
			ZBX_FALLTHROUGH;
		default:
			/* TODO flush local cache */
			pdc_discovery_flush_db(data);
	}

	zbx_vector_pdc_discovery_ptr_clear_ext(&data->rows, pdc_discovery_free);
	zbx_vector_pdc_discovery_ptr_destroy(&data->rows);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write service data into discovery data cache                      *
 *                                                                            *
 ******************************************************************************/
void	pdc_discovery_write_service(zbx_pdc_discovery_data_t *data, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		const char *ip, const char *dns, int port, int status, const char *value, int clock)
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

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into discovery data cache                         *
 *                                                                            *
 ******************************************************************************/
void	pdc_discovery_write_host(zbx_pdc_discovery_data_t *data, zbx_uint64_t druleid, const char *ip, const char *dns,
		int status, int clock)
{
	zbx_pdc_discovery_t	*row;

	row = (zbx_pdc_discovery_t *)zbx_malloc(NULL, sizeof(zbx_pdc_discovery_t));
	row->druleid = druleid;
	row->dcheckid = 0;
	row->ip = zbx_strdup(NULL, ip);
	row->dns = zbx_strdup(NULL, dns);
	row->port = 0;
	row->status = status;
	row->value = zbx_strdup(NULL, "");
	row->clock = clock;
	zbx_vector_pdc_discovery_ptr_append(&data->rows, row);
}
