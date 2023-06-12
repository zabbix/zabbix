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
#include "zbxdbhigh.h"

static zbx_history_table_t	areg = {
	"proxy_autoreg_host", "autoreg_host_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"host",		ZBX_PROTO_TAG_HOST,		ZBX_JSON_TYPE_STRING,	NULL},
		{"listen_ip",		ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_dns",		ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_STRING,	"0"},
		{"host_metadata",	ZBX_PROTO_TAG_HOST_METADATA,	ZBX_JSON_TYPE_STRING,	""},
		{"flags",		ZBX_PROTO_TAG_FLAGS,		ZBX_JSON_TYPE_STRING,	"0"},
		{"tls_accepted",	ZBX_PROTO_TAG_TLS_ACCEPTED,	ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

static void	pdc_autoreg_write_host_db(const char *host, const char *ip, const char *dns, unsigned short port,
		unsigned int connection_type, const char *host_metadata, int flags, int clock)
{
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	id;

	id = zbx_db_get_maxid("proxy_autoreg_host");

	zbx_db_insert_prepare(&db_insert, "proxy_autoreg_host", "id", "host", "listen_ip", "listen_dns", "listen_port",
			"tls_accepted", "host_metadata", "flags", "clock", NULL);

	zbx_db_insert_add_values(&db_insert, id, host, ip, dns, (int)port, (int)connection_type, host_metadata, flags,
			clock);

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into autoregistration data cache                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_autoreg_write_host(const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, int flags,
		int clock)
{
	if (PDC_MEMORY == pdc_dst[pdc_cache->state])
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, switching to database");
		pdc_cache->state = PDC_DATABASE_ONLY;
		/* TODO: change to 'else' after memory cache implementation */
	}

	if (PDC_DATABASE == pdc_dst[pdc_cache->state])
		pdc_autoreg_write_host_db(host, ip, dns, port, connection_type, host_metadata, flags, clock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into autoregistraion data cache                   *
 *                                                                            *
 ******************************************************************************/
static int	pdc_get_autoreg(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	id = pdc_get_lastid(areg.table, areg.lastidfield);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		pdc_get_rows(j, ZBX_PROTO_TAG_AUTOREGISTRATION, &areg, lastid, &id, &records_num, more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	zbx_pdc_get_autoreg(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	if (PDC_MEMORY == pdc_src[pdc_cache->state])
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache not implemented, forcing database cache");
		pdc_cache->state = PDC_DATABASE_ONLY;
	}

	if (PDC_DATABASE == pdc_src[pdc_cache->state])
	{
		return pdc_get_autoreg(j, lastid, more);
	}
	else
		return 0;
}

void	zbx_pdc_set_autoreg_lastid(const zbx_uint64_t lastid)
{
	pdc_set_lastid(areg.table, areg.lastidfield, lastid);
}
