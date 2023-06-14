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
#include "proxydatacache.h"
#include "zbxproxydatacache.h"
#include "zbxdbhigh.h"
#include "zbxcachehistory.h"

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
 * Purpose: write host data into autoregistraion data cache                   *
 *                                                                            *
 ******************************************************************************/
static int	pdc_autoreg_get_db(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
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
		pdc_get_rows_db(j, ZBX_PROTO_TAG_AUTOREGISTRATION, &areg, lastid, &id, &records_num, more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free autoregistration record                                      *
 *                                                                            *
 ******************************************************************************/
static void	pdc_list_free_autoreg(zbx_list_t *list, zbx_pdc_autoreg_t *ar)
{
	if (NULL != ar->host)
		list->mem_free_func(ar->host);

	if (NULL != ar->listen_ip)
		list->mem_free_func(ar->listen_ip);

	if (NULL != ar->listen_dns)
		list->mem_free_func(ar->listen_dns);

	if (NULL != ar->host_metadata)
		list->mem_free_func(ar->host_metadata);

	list->mem_free_func(ar);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write autoregistration record into memory cache                   *
 *                                                                            *
 ******************************************************************************/
static int	pdc_autoreg_write_host_mem(zbx_pdc_t *pdc, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, int flags, int clock)
{
	zbx_pdc_autoreg_t	*row;
	int			ret = FAIL;

	if (NULL == (row = (zbx_pdc_autoreg_t *)pdc_malloc(sizeof(zbx_pdc_autoreg_t))))
			goto out;

	memset(row, 0, sizeof(zbx_pdc_autoreg_t));

	if (NULL == (row->host = pdc_strdup(host)))
		goto out;

	if (NULL == (row->listen_ip = pdc_strdup(ip)))
		goto out;

	if (NULL == (row->listen_dns = pdc_strdup(dns)))
		goto out;

	if (NULL == (row->host_metadata = pdc_strdup(host_metadata)))
		goto out;

	row->listen_port = (int)port;
	row->tls_accepted = (int)connection_type;
	row->flags = flags;
	row->clock = clock;
	row->write_clock = time(NULL);

	ret = zbx_list_append(&pdc->autoreg, row, NULL);
out:
	if (SUCCEED == ret)
		row->id = zbx_dc_get_nextid("proxy_autoreg_host", 1);
	else if (NULL != row)
		pdc_list_free_autoreg(&pdc_cache->autoreg, row);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get autoregistration records from memory cache                    *
 *                                                                            *
 ******************************************************************************/
static int	pdc_autoreg_get_mem(zbx_pdc_t *pdc, struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int			records_num = 0;
	zbx_list_iterator_t	li;
	zbx_pdc_autoreg_t	*row;

	if (SUCCEED == zbx_list_peek(&pdc->autoreg, NULL))
	{
		zbx_json_addarray(j, ZBX_PROTO_TAG_AUTOREGISTRATION);
		zbx_list_iterator_init(&pdc->autoreg, &li);

		while (SUCCEED == zbx_list_iterator_next(&li))
		{
			if (ZBX_DATA_JSON_BATCH_LIMIT <= j->buffer_offset)
			{
				*more = 1;
				break;
			}

			zbx_list_iterator_peek(&li, (void **)&row);

			zbx_json_addobject(j, NULL);
			zbx_json_addint64(j, ZBX_PROTO_TAG_CLOCK, row->clock);
			zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, row->host, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(j, ZBX_PROTO_TAG_IP, row->listen_ip, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(j, ZBX_PROTO_TAG_DNS, row->listen_dns, ZBX_JSON_TYPE_STRING);
			zbx_json_addint64(j, ZBX_PROTO_TAG_PORT, row->listen_port);
			zbx_json_addstring(j, ZBX_PROTO_TAG_HOST_METADATA, row->host_metadata, ZBX_JSON_TYPE_STRING);
			zbx_json_addint64(j, ZBX_PROTO_TAG_FLAGS, row->flags);
			zbx_json_addint64(j, ZBX_PROTO_TAG_TLS_ACCEPTED, row->tls_accepted);
			zbx_json_close(j);

			records_num++;
			*lastid = row->id;
		}

		zbx_json_close(j);
	}

	pdc->autoreg_lastid = *lastid;

	return records_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear sent autoregistration records                               *
 *                                                                            *
 ******************************************************************************/
static void	pdc_autoreg_clear(zbx_pdc_t *pdc, zbx_uint64_t lastid)
{
	zbx_pdc_autoreg_t	*row;

	while (SUCCEED == zbx_list_peek(&pdc->autoreg, (void **)&row))
	{
		if (row->id > lastid)
			break;

		zbx_list_pop(&pdc->autoreg, NULL);
		pdc_list_free_autoreg(&pdc->autoreg, row);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush cached autoregistration data to database                    *
 *                                                                            *
 ******************************************************************************/
void	pdc_autoreg_flush(zbx_pdc_t *pdc)
{
	zbx_pdc_autoreg_t	*row;
	zbx_db_insert_t		db_insert;

	if (SUCCEED == zbx_list_peek(&pdc->autoreg, NULL))
	{
		zbx_db_insert_prepare(&db_insert, "proxy_autoreg_host", "id", "host", "listen_ip", "listen_dns",
				"listen_port", "tls_accepted", "host_metadata", "flags", "clock", NULL);

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
		while (SUCCEED == zbx_list_pop(&pdc->autoreg, (void **)&row))
		{
			zbx_db_insert_add_values(&db_insert, row->id, row->host, row->listen_ip, row->listen_dns,
					row->listen_port, row->tls_accepted, row->host_metadata, row->flags, row->clock);

			pdc_list_free_autoreg(&pdc->autoreg, row);
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != pdc->autoreg_lastid)
		pdc_set_lastid("proxy_autoreg_host", "autoreg_host_lastid", pdc->autoreg_lastid);
}

/* public api */

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into autoregistration data cache                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_autoreg_write_host(const char *host, const char *ip, const char *dns, unsigned short port,
		unsigned int connection_type, const char *host_metadata, int flags, int clock)
{
	pdc_lock();

	if (PDC_MEMORY == pdc_dst[pdc_cache->state])
	{
		if (FAIL != pdc_autoreg_write_host_mem(pdc_cache, host, ip, dns, port, connection_type, host_metadata,
				flags, clock))
		{
			return;
		}

		if (PDC_DATABASE_MEMORY == pdc_cache->state)
		{
			/* transition to memory cache failed, disable memory cache until restart */
			pdc_fallback_to_database(pdc_cache);
		}
		else
		{
			/* initiate transition to database cache */
			pdc_cache_set_state(pdc_cache, PDC_MEMORY_DATABASE, "not enough space");
		}

		zabbix_log(LOG_LEVEL_WARNING, "proxy data memory cache is full, switching to database cache");
	}

	pdc_cache->db_handles_num++;
	pdc_unlock();

	pdc_autoreg_write_host_db(host, ip, dns, port, connection_type, host_metadata, flags, clock);

	pdc_lock();
	pdc_cache->db_handles_num--;
	pdc_unlock();

}

/******************************************************************************
 *                                                                            *
 * Purpose: get autoregistration rows for sending to server                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_pdc_autoreg_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int	ret, state;

	pdc_lock();

	if (PDC_MEMORY == (state = pdc_src[pdc_cache->state]))
		ret = pdc_autoreg_get_mem(pdc_cache, j, lastid, more);

	pdc_unlock();

	if (PDC_DATABASE == state)
		ret = pdc_autoreg_get_db(j, lastid, more);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update last sent autoregistration record id                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_autoreg_set_lastid(const zbx_uint64_t lastid)
{
	int	state;

	pdc_lock();

	pdc_cache->autoreg_lastid = lastid;

	if (PDC_MEMORY == (state = pdc_src[pdc_cache->state]))
		pdc_autoreg_clear(pdc_cache, lastid);

	pdc_unlock();

	if (PDC_DATABASE == state)
		pdc_set_lastid(areg.table, areg.lastidfield, lastid);
}
