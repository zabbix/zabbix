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

#include "pb_autoreg.h"
#include "proxybuffer.h"
#include "zbxcachehistory.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxjson.h"
#include "zbxproxybuffer.h"
#include "zbxshmem.h"
#include "zbxdbschema.h"

static zbx_history_table_t	areg = {
	"proxy_autoreg_host", "autoreg_host_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"host",		ZBX_PROTO_TAG_HOST,		ZBX_JSON_TYPE_STRING,	NULL},
		{"listen_ip",		ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_dns",		ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_INT,	"0"},
		{"host_metadata",	ZBX_PROTO_TAG_HOST_METADATA,	ZBX_JSON_TYPE_STRING,	""},
		{"flags",		ZBX_PROTO_TAG_FLAGS,		ZBX_JSON_TYPE_INT,	"0"},
		{"tls_accepted",	ZBX_PROTO_TAG_TLS_ACCEPTED,	ZBX_JSON_TYPE_INT,	"0"},
		{0}
		}
};

static void	pb_autoreg_write_host_db(zbx_uint64_t id, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, int flags, int clock)
{
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "proxy_autoreg_host", "id", "host", "listen_ip", "listen_dns", "listen_port",
			"tls_accepted", "host_metadata", "flags", "clock", (char *)NULL);

	zbx_db_insert_add_values(&db_insert, id, host, ip, dns, (int)port, (int)connection_type, host_metadata,
			flags, clock);

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into autoregistraion data cache                   *
 *                                                                            *
 ******************************************************************************/
static int	pb_autoreg_get_db(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	id = pb_get_lastid(areg.table, areg.lastidfield);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		pb_get_rows_db(j, ZBX_PROTO_TAG_AUTOREGISTRATION, &areg, lastid, &id, &records_num, more);

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
void	pb_list_free_autoreg(zbx_list_t *list, zbx_pb_autoreg_t *row)
{
	if (NULL != row->host)
		list->mem_free_func(row->host);

	if (NULL != row->listen_ip)
		list->mem_free_func(row->listen_ip);

	if (NULL != row->listen_dns)
		list->mem_free_func(row->listen_dns);

	if (NULL != row->host_metadata)
		list->mem_free_func(row->host_metadata);

	list->mem_free_func(row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: estimate approximate autoregistration row size in cache           *
 *                                                                            *
 ******************************************************************************/
size_t	pb_autoreg_estimate_row_size(const char *host, const char *host_metadata, const char *ip, const char *dns)
{
	size_t	size = 0;

	size += zbx_shmem_required_chunk_size(sizeof(zbx_pb_autoreg_t));
	size += zbx_shmem_required_chunk_size(sizeof(zbx_list_item_t));
	size += zbx_shmem_required_chunk_size(strlen(host) + 1);
	size += zbx_shmem_required_chunk_size(strlen(host_metadata) + 1);
	size += zbx_shmem_required_chunk_size(strlen(ip) + 1);
	size += zbx_shmem_required_chunk_size(strlen(dns) + 1);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add auto registration record to memory cache                      *
 *                                                                            *
 ******************************************************************************/
static int	pb_autoreg_add_row_mem(zbx_pb_t *pb, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, int flags, int clock)
{
	zbx_pb_autoreg_t	*row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() free:" ZBX_FS_SIZE_T " request:" ZBX_FS_SIZE_T, __func__,
			pb_get_free_size(), pb_autoreg_estimate_row_size(host, host_metadata, ip, dns));

	if (NULL == (row = (zbx_pb_autoreg_t *)pb_malloc(sizeof(zbx_pb_autoreg_t))))
			goto out;

	memset(row, 0, sizeof(zbx_pb_autoreg_t));

	if (NULL == (row->host = pb_strdup(host)))
		goto out;

	if (NULL == (row->listen_ip = pb_strdup(ip)))
		goto out;

	if (NULL == (row->listen_dns = pb_strdup(dns)))
		goto out;

	if (NULL == (row->host_metadata = pb_strdup(host_metadata)))
		goto out;

	row->listen_port = (int)port;
	row->tls_accepted = (int)connection_type;
	row->flags = flags;
	row->clock = clock;

	ret = zbx_list_append(&pb->autoreg, row, NULL);
out:
	if (SUCCEED == ret)
		row->id = zbx_dc_get_nextid("proxy_autoreg_host", 1);
	else if (NULL != row)
		pb_list_free_autoreg(&get_pb_data()->autoreg, row);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%s free:" ZBX_FS_SIZE_T , __func__, zbx_result_string(ret),
			pb_get_free_size());

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: write auto registration record in memory cache, discarding old    *
 *          records in memory mode to free space if necessary                 *
 *                                                                            *
 ******************************************************************************/
static int	pb_autoreg_write_host_mem(zbx_pb_t *pb, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, int flags, int clock)
{
	size_t	size = 0;

	while (FAIL == pb_autoreg_add_row_mem(pb, host, ip, dns, port, connection_type, host_metadata, flags, clock))
	{
		if (ZBX_PB_MODE_MEMORY != pb->mode)
			return FAIL;

		/* in memory mode keep discarding old records until new */
		/* one can be written in proxy memory buffer            */

		if (0 == size)
			size = pb_autoreg_estimate_row_size(host, host_metadata, ip, dns);

		if (FAIL == pb_free_space(get_pb_data(), size))
		{
			zabbix_log(LOG_LEVEL_WARNING, "auto registration record with size " ZBX_FS_SIZE_T
					" is too large for proxy memory buffer, discarding", size);
			break;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get autoregistration records from memory cache                    *
 *                                                                            *
 ******************************************************************************/
static int	pb_autoreg_get_mem(zbx_pb_t *pb, struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
#define	pb_add_json_field_helper(fld_name, type)						\
	pb_add_json_field(j, &areg, ZBX_STR(fld_name), &row->fld_name, type)

	int			records_num = 0;
	zbx_list_iterator_t	li;
	zbx_pb_autoreg_t	*row;
	void			*ptr;

	*more = ZBX_PROXY_DATA_DONE;

	if (SUCCEED == zbx_list_peek(&pb->autoreg, &ptr))
	{
		zbx_json_addarray(j, ZBX_PROTO_TAG_AUTOREGISTRATION);
		zbx_list_iterator_init(&pb->autoreg, &li);

		while (SUCCEED == zbx_list_iterator_next(&li))
		{
			if (ZBX_DATA_JSON_BATCH_LIMIT <= j->buffer_offset || records_num >= ZBX_MAX_HRECORDS_TOTAL)
			{
				*more = ZBX_PROXY_DATA_MORE;
				break;
			}

			(void)zbx_list_iterator_peek(&li, (void **)&row);

			zbx_json_addobject(j, NULL);
			pb_add_json_field_helper(clock, ZBX_TYPE_INT);
			pb_add_json_field_helper(host, ZBX_TYPE_CHAR);
			pb_add_json_field_helper(listen_ip, ZBX_TYPE_CHAR);
			pb_add_json_field_helper(listen_dns, ZBX_TYPE_CHAR);
			pb_add_json_field_helper(listen_port, ZBX_TYPE_INT);
			pb_add_json_field_helper(host_metadata, ZBX_TYPE_CHAR);
			pb_add_json_field_helper(flags, ZBX_TYPE_INT);
			pb_add_json_field_helper(tls_accepted, ZBX_TYPE_INT);
			zbx_json_close(j);

			records_num++;
			*lastid = row->id;
		}

		zbx_json_close(j);
	}

	return records_num;

#	undef	pb_add_json_field_helper
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear sent autoregistration records                               *
 *                                                                            *
 ******************************************************************************/
void	pb_autoreg_clear(zbx_pb_t *pb, zbx_uint64_t lastid)
{
	zbx_pb_autoreg_t	*row;

	while (SUCCEED == zbx_list_peek(&pb->autoreg, (void **)&row))
	{
		if (row->id > lastid)
			break;

		zbx_list_pop(&pb->autoreg, NULL);
		pb_list_free_autoreg(&pb->autoreg, row);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush cached autoregistration data to database                    *
 *                                                                            *
 ******************************************************************************/
void	pb_autoreg_flush(zbx_pb_t *pb)
{
	zbx_pb_autoreg_t	*row;
	zbx_db_insert_t		db_insert;
	void			*ptr;
	int			rows_num = 0;
	zbx_uint64_t		lastid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == zbx_list_peek(&pb->autoreg, &ptr))
	{
		zbx_list_iterator_t	li;

		zbx_db_insert_prepare(&db_insert, "proxy_autoreg_host", "id", "host", "listen_ip", "listen_dns",
				"listen_port", "tls_accepted", "host_metadata", "flags", "clock", (char *)NULL);

		zbx_list_iterator_init(&pb->autoreg, &li);

		while (SUCCEED == zbx_list_iterator_next(&li))
		{
			(void)zbx_list_iterator_peek(&li, (void **)&row);
			zbx_db_insert_add_values(&db_insert, row->id, row->host, row->listen_ip, row->listen_dns,
					row->listen_port, row->tls_accepted, row->host_metadata, row->flags, row->clock);
			rows_num++;
			lastid = row->id;
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (get_pb_data()->autoreg_lastid_db < lastid)
		get_pb_data()->autoreg_lastid_db = lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows_num:%d", __func__, rows_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if oldest record is within allowed age                      *
 *                                                                            *
 ******************************************************************************/
int	pb_autoreg_check_age(zbx_pb_t *pb)
{
	zbx_pb_autoreg_t	*row;
	int			now;

	now = (int)time(NULL);

	while (SUCCEED == zbx_list_peek(&pb->autoreg, (void **)&row))
	{
		if (now - row->clock <= pb->offline_buffer)
			break;

		zbx_list_pop(&pb->autoreg, NULL);
		pb_list_free_autoreg(&pb->autoreg, row);
	}

	if (0 == pb->max_age)
		return SUCCEED;

	if (SUCCEED != zbx_list_peek(&pb->autoreg, (void **)&row) || time(NULL) - row->clock < pb->max_age)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: write last sent auto registration record id to database           *
 *                                                                            *
 ******************************************************************************/
void	pb_autoreg_set_lastid(zbx_uint64_t lastid)
{
	pb_set_lastid(areg.table, areg.lastidfield, lastid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if auto registration rows are cached in memory buffer       *
 *                                                                            *
 ******************************************************************************/
int	pb_autoreg_has_mem_rows(zbx_pb_t *pb)
{
	void	*ptr;

	return zbx_list_peek(&pb->autoreg, &ptr);
}

/* public api */

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into autoregistration data cache                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_autoreg_write_host(const char *host, const char *ip, const char *dns, unsigned short port,
		unsigned int connection_type, const char *host_metadata, int flags, int clock)
{
	zbx_uint64_t	id;
	zbx_pb_t	*pb_data = get_pb_data();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pb_lock();

	if (PB_MEMORY == get_pb_dst(pb_data->state))
	{
		if (PB_MEMORY == pb_data->state && SUCCEED != pb_autoreg_check_age(pb_data))
		{
			pd_fallback_to_database(pb_data, "cached records are too old");
		}
		else
		{
			if (FAIL != pb_autoreg_write_host_mem(pb_data, host, ip, dns, port, connection_type,
					host_metadata, flags, clock))
			{
				pb_unlock();
				goto out;
			}

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
	}

	pb_data->db_handles_num++;
	pb_unlock();

	id = zbx_db_get_maxid("proxy_autoreg_host");

	do
	{
		zbx_db_begin();
		pb_autoreg_write_host_db(id, host, ip, dns, port, connection_type, host_metadata, flags, clock);
	}
	while (ZBX_DB_DOWN == zbx_db_commit());

	pb_lock();

	if (pb_data->autoreg_lastid_db < id)
		pb_data->autoreg_lastid_db = id;

	pb_data->db_handles_num--;

	pb_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get autoregistration rows for sending to server                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_pb_autoreg_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int	ret, state;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, *lastid);

	pb_lock();

	if (PB_MEMORY == (state = get_pb_src(get_pb_data()->state)))
		ret = pb_autoreg_get_mem(get_pb_data(), j, lastid, more);

	pb_unlock();

	if (PB_MEMORY != state)
		ret = pb_autoreg_get_db(j, lastid, more);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update last sent autoregistration record id                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_autoreg_set_lastid(const zbx_uint64_t lastid)
{
	int		state;
	zbx_pb_t	*pb_data = get_pb_data();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, lastid);

	pb_lock();

	pb_data->autoreg_lastid_sent = lastid;

	if (PB_MEMORY == (state = get_pb_src(pb_data->state)))
		pb_autoreg_clear(pb_data, lastid);

	pb_unlock();

	if (PB_DATABASE == state)
		pb_autoreg_set_lastid(lastid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
