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

#include "pb_discovery.h"
#include "zbxcachehistory.h"
#include "zbxstr.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxjson.h"
#include "zbxproxybuffer.h"
#include "zbxshmem.h"
#include "zbxdbschema.h"

static zbx_history_table_t	dht = {
	"proxy_dhistory", "dhistory_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"druleid",		ZBX_PROTO_TAG_DRULE,		ZBX_JSON_TYPE_INT,	NULL},
		{"dcheckid",		ZBX_PROTO_TAG_DCHECK,		ZBX_JSON_TYPE_INT,	NULL},
		{"ip",			ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	NULL},
		{"dns",			ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	""},
		{"port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_INT,	"0"},
		{"value",		ZBX_PROTO_TAG_VALUE,		ZBX_JSON_TYPE_STRING,	""},
		{"status",		ZBX_PROTO_TAG_STATUS,		ZBX_JSON_TYPE_INT,	"0"},
		{"error",		ZBX_PROTO_TAG_ERROR,		ZBX_JSON_TYPE_STRING,	""},
		{0}
		}
};

static void	pb_discovery_add_rows_db(zbx_list_t *rows, zbx_list_item_t *next, zbx_uint64_t *lastid);

struct zbx_pb_discovery_data
{
	zbx_pb_state_t	state;
	zbx_list_t	rows;
	int		rows_num;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	handleid;
};

void	pb_list_free_discovery(zbx_list_t *list, zbx_pb_discovery_t *row)
{
	if (NULL != row->ip)
		list->mem_free_func(row->ip);
	if (NULL != row->dns)
		list->mem_free_func(row->dns);
	if (NULL != row->value)
		list->mem_free_func(row->value);
	if (NULL != row->error)
		list->mem_free_func(row->error);
	list->mem_free_func(row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: estimate approximate discovery row size in cache                  *
 *                                                                            *
 ******************************************************************************/
size_t	pb_discovery_estimate_row_size(const char *value, const char *ip, const char *dns, const char *error)
{
	size_t	size = 0;

	size += zbx_shmem_required_chunk_size(sizeof(zbx_pb_discovery_t));
	size += zbx_shmem_required_chunk_size(sizeof(zbx_list_item_t));
	size += zbx_shmem_required_chunk_size(strlen(value) + 1);
	size += zbx_shmem_required_chunk_size(strlen(ip) + 1);
	size += zbx_shmem_required_chunk_size(strlen(dns) + 1);
	size += zbx_shmem_required_chunk_size(strlen(error) + 1);

	return size;
}

static int	pb_get_discovery_db(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	id = pb_get_lastid(dht.table, dht.lastidfield);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		pb_get_rows_db(j, ZBX_PROTO_TAG_DISCOVERY_DATA, &dht, lastid, &id, &records_num, more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

static void	pb_discovery_write_row(zbx_pb_discovery_data_t *data, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		const char *ip, const char *dns, int port, int status, const char *value, int clock, const char *error)
{
	if (PB_MEMORY == data->state)
	{
		zbx_pb_discovery_t	*row;

		row = (zbx_pb_discovery_t *)zbx_malloc(NULL, sizeof(zbx_pb_discovery_t));
		row->id = 0;
		row->druleid = druleid;
		row->dcheckid = dcheckid;
		row->ip = zbx_strdup(NULL, ip);
		row->dns = zbx_strdup(NULL, dns);
		row->port = port;
		row->status = status;
		row->value = zbx_strdup(NULL, value);
		row->clock = clock;
		row->error = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(error));

		zbx_list_append(&data->rows, row, NULL);
		data->rows_num++;
	}
	else
	{
		zbx_db_insert_add_values(&data->db_insert, __UINT64_C(0), clock, druleid, ip, port, value, status,
				dcheckid, dns, ZBX_NULL2EMPTY_STR(error));
	}
}

void	pb_discovery_flush(zbx_pb_t *pb)
{
	zbx_uint64_t	lastid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pb_discovery_add_rows_db(&pb->discovery, NULL, &lastid);

	if (get_pb_data()->discovery_lastid_db < lastid)
		get_pb_data()->discovery_lastid_db = lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add discovery row to memory cache                                 *
 *                                                                            *
 * Parameters: pb  - [IN] proxy buffer                                        *
 *             src - [IN] row to add                                          *
 *                                                                            *
 * Return value: SUCCEED - the row was cached successfully                    *
 *               FAIL    - not enough memory in cache                         *
 *                                                                            *
 ******************************************************************************/
static int	pb_discovery_add_row_mem(zbx_pb_t *pb, zbx_pb_discovery_t *src)
{
	zbx_pb_discovery_t	*row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() free:" ZBX_FS_SIZE_T " request:" ZBX_FS_SIZE_T, __func__,
			pb_get_free_size(), pb_discovery_estimate_row_size(src->value, src->ip, src->dns, src->error));

	if (NULL == (row = (zbx_pb_discovery_t *)pb_malloc(sizeof(zbx_pb_discovery_t))))
		goto out;

	memcpy(row, src, sizeof(zbx_pb_discovery_t));

	if (NULL == (row->ip = pb_strdup(src->ip)))
	{
		row->dns = NULL;
		row->value = NULL;
		goto out;
	}

	if (NULL == (row->dns = pb_strdup(src->dns)))
	{
		row->value = NULL;
		goto out;
	}

	if (NULL == (row->value = pb_strdup(src->value)))
		goto out;

	if (NULL == (row->error = pb_strdup(src->error)))
		goto out;

	ret = zbx_list_append(&pb->discovery, row, NULL);
out:
	if (SUCCEED != ret && NULL != row)
		pb_list_free_discovery(&pb->discovery, row);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%s free:" ZBX_FS_SIZE_T, __func__, zbx_result_string(ret),
			pb_get_free_size());

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set ids to new discovery rows                                     *
 *                                                                            *
 ******************************************************************************/
static void	pb_discovery_set_row_ids(zbx_list_t *rows, int rows_num, zbx_uint64_t handleid)
{
	zbx_uint64_t		id;
	zbx_pb_discovery_t	*row;
	zbx_list_iterator_t	li;

	id = zbx_dc_get_nextid("proxy_dhistory", rows_num);
	zbx_list_iterator_init(rows, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		(void)zbx_list_iterator_peek(&li, (void **)&row);
		row->id = id++;
		row->handleid = handleid;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add discovery rows to memory cache                                *
 *                                                                            *
 * Parameters: pb   - [IN] proxy buffer                                       *
 *             rows - [IN] rows to add                                        *
 *                                                                            *
 * Return value: NULL if all rows were added successfully. Otherwise the list *
 *               item of first failed row is returned                         *
 *                                                                            *
 ******************************************************************************/
static zbx_list_item_t	*pb_discovery_add_rows_mem(zbx_pb_t *pb, zbx_list_t *rows)
{
	zbx_list_iterator_t	li;
	zbx_pb_discovery_t	*row;
	int			rows_num = 0;
	size_t			size = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_list_iterator_init(rows, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		(void)zbx_list_iterator_peek(&li, (void **)&row);

		while (SUCCEED != pb_discovery_add_row_mem(pb, row))
		{
			if (ZBX_PB_MODE_MEMORY != pb->mode)
				goto out;

			/* in memory mode keep discarding old records until new */
			/* one can be written in proxy memory buffer            */

			if (0 == size)
				size = pb_discovery_estimate_row_size(row->value, row->ip, row->dns, row->error);

			if (FAIL == pb_free_space(get_pb_data(), size))
			{
				zabbix_log(LOG_LEVEL_WARNING, "discovery record with size " ZBX_FS_SIZE_T
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
 * Purpose: add discovery rows to database cache                              *
 *                                                                            *
 * Parameters: rows   - [IN] rows to add                                      *
 *             next   - [IN] next row to add                                  *
 *             lastid - [OUT] last inserted id                                *
 *                                                                            *
 ******************************************************************************/
static void	pb_discovery_add_rows_db(zbx_list_t *rows, zbx_list_item_t *next, zbx_uint64_t *lastid)
{
	zbx_list_iterator_t	li;
	zbx_pb_discovery_t	*row;
	zbx_db_insert_t		db_insert;
	int			rows_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() next:%p", __func__, next);

	if (SUCCEED == zbx_list_iterator_init_with(rows, next, &li))
	{
		zbx_db_insert_prepare(&db_insert, "proxy_dhistory", "id", "clock", "druleid", "ip", "port", "value",
				"status", "dcheckid", "dns", "error", (char *)NULL);

		do
		{
			(void)zbx_list_iterator_peek(&li, (void **)&row);
			zbx_db_insert_add_values(&db_insert, row->id, row->clock, row->druleid, row->ip, row->port,
					row->value, row->status, row->dcheckid, row->dns, row->error);
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
static int	pb_discovery_get_mem(zbx_pb_t *pb, struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
#define	pb_add_json_field_helper(fld_name, type)						\
	pb_add_json_field(j, &dht, ZBX_STR(fld_name), &row->fld_name, type)

	int			records_num = 0;
	zbx_list_iterator_t	li;
	zbx_pb_discovery_t	*row;
	void			*ptr;

	*more = ZBX_PROXY_DATA_DONE;

	if (SUCCEED == zbx_list_peek(&pb->discovery, &ptr))
	{
		zbx_json_addarray(j, ZBX_PROTO_TAG_DISCOVERY_DATA);
		zbx_list_iterator_init(&pb->discovery, &li);

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
			pb_add_json_field_helper(druleid, ZBX_TYPE_UINT);
			pb_add_json_field_helper(dcheckid, ZBX_TYPE_UINT);
			pb_add_json_field_helper(ip, ZBX_TYPE_CHAR);
			pb_add_json_field_helper(dns, ZBX_TYPE_CHAR);
			pb_add_json_field_helper(port, ZBX_TYPE_INT);
			pb_add_json_field_helper(value, ZBX_TYPE_CHAR);
			pb_add_json_field_helper(status, ZBX_TYPE_INT);
			pb_add_json_field_helper(error, ZBX_TYPE_CHAR);
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
 * Purpose: clear sent discovery records                                      *
 *                                                                            *
 ******************************************************************************/
void	pb_discovery_clear(zbx_pb_t *pb, zbx_uint64_t lastid)
{
	zbx_pb_discovery_t	*row;

	while (SUCCEED == zbx_list_peek(&pb->discovery, (void **)&row))
	{
		if (row->id > lastid)
			break;

		zbx_list_pop(&pb->discovery, NULL);
		pb_list_free_discovery(&pb->discovery, row);
	}
}

static void	pb_discovery_data_free(zbx_pb_discovery_data_t *data)
{
	if (PB_MEMORY == data->state)
	{
		zbx_pb_discovery_t	*row;

		while (SUCCEED == zbx_list_pop(&data->rows, (void **)&row))
			pb_list_free_discovery(&data->rows, row);

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
int	pb_discovery_check_age(zbx_pb_t *pb)
{
	zbx_pb_discovery_t	*row;
	int			now;

	now = (int)time(NULL);

	while (SUCCEED == zbx_list_peek(&pb->discovery, (void **)&row))
	{
		if (now - row->clock <= pb->offline_buffer)
			break;

		zbx_list_pop(&pb->discovery, NULL);
		pb_list_free_discovery(&pb->discovery, row);
	}

	if (0 == pb->max_age)
		return SUCCEED;

	if (SUCCEED != zbx_list_peek(&pb->discovery, (void **)&row) || time(NULL) - row->clock < pb->max_age)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: write discovery last sent record id to database                   *
 *                                                                            *
 ******************************************************************************/
void	pb_discovery_set_lastid(zbx_uint64_t lastid)
{
	pb_set_lastid(dht.table, dht.lastidfield, lastid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if discovery rows are cached in memory buffer               *
 *                                                                            *
 ******************************************************************************/
int	pb_discovery_has_mem_rows(zbx_pb_t *pb)
{
	void	*ptr;

	return zbx_list_peek(&pb->discovery, &ptr);
}

/* public api */

/******************************************************************************
 *                                                                            *
 * Purpose: open discovery data cache                                         *
 *                                                                            *
 * Return value: The discovery data cache handle                              *
 *                                                                            *
 ******************************************************************************/
zbx_pb_discovery_data_t	*zbx_pb_discovery_open(void)
{
	zbx_pb_discovery_data_t	*data;
	zbx_pb_t		*pb_data = get_pb_data();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	data = (zbx_pb_discovery_data_t *)zbx_malloc(NULL, sizeof(zbx_pb_discovery_data_t));

	pb_lock();

	data->handleid = pb_get_next_handleid(pb_data);

	if (PB_DATABASE == (data->state = get_pb_dst(pb_data->state)))
		pb_data->db_handles_num++;

	pb_unlock();

	if (PB_MEMORY == data->state)
	{
		zbx_list_create(&data->rows);
		data->rows_num = 0;
	}
	else
	{
		zbx_db_insert_prepare(&data->db_insert, "proxy_dhistory", "id", "clock", "druleid", "ip", "port",
				"value", "status", "dcheckid", "dns", "error", (char *)NULL);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached discovery data and free the handle               *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_discovery_close(zbx_pb_discovery_data_t *data)
{
	zbx_uint64_t	lastid = 0;
	zbx_pb_t	*pb_data = get_pb_data();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (PB_MEMORY == data->state)
	{
		zbx_list_item_t	*next = NULL;

		if (0 == data->rows_num)
			goto out;

		pb_lock();

		pb_discovery_set_row_ids(&data->rows, data->rows_num, data->handleid);

		if (PB_MEMORY == pb_data->state && SUCCEED != pb_discovery_check_age(pb_data))
		{
			pd_fallback_to_database(pb_data, "cached records are too old");
		}
		else if (PB_MEMORY == get_pb_dst(pb_data->state))
		{
			if (NULL == (next = pb_discovery_add_rows_mem(pb_data, &data->rows)))
			{
				pb_unlock();
				goto out;
			}

			if (PB_DATABASE_MEMORY == pb_data->state)
			{
				pd_fallback_to_database(pb_data, "not enough space to complete transition to memory"
						" mode");
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
			pb_discovery_add_rows_db(&data->rows, next, &lastid);
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

	if (pb_data->discovery_lastid_db < lastid)
		pb_data->discovery_lastid_db = lastid;

	pb_data->db_handles_num--;

	pb_unlock();
out:
	pb_discovery_data_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write service data into discovery data cache                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_discovery_write_service(zbx_pb_discovery_data_t *data, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		const char *ip, const char *dns, int port, int status, const char *value, int clock)
{
	pb_discovery_write_row(data, druleid, dcheckid, ip, dns, port, status, value, clock, "");
}

/******************************************************************************
 *                                                                            *
 * Purpose: write host data into discovery data cache                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_discovery_write_host(zbx_pb_discovery_data_t *data, zbx_uint64_t druleid, const char *ip,
		const char *dns, int status, int clock, const char *error)
{
	pb_discovery_write_row(data, druleid, 0, ip, dns, 0, status, "", clock, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get discovery rows for sending to server                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_pb_discovery_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int	state, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, *lastid);

	pb_lock();

	if (PB_MEMORY == (state = get_pb_src(get_pb_data()->state)))
		ret = pb_discovery_get_mem(get_pb_data(), j, lastid, more);

	pb_unlock();

	if (PB_MEMORY != state)
		ret = pb_get_discovery_db(j, lastid, more);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update database lastid/clear memory records                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_discovery_set_lastid(const zbx_uint64_t lastid)
{
	int		state;
	zbx_pb_t	*pb_data = get_pb_data();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, lastid);

	pb_lock();

	pb_data->discovery_lastid_sent = lastid;

	if (PB_MEMORY == (state = get_pb_src(pb_data->state)))
		pb_discovery_clear(pb_data, lastid);

	pb_unlock();

	if (PB_DATABASE == state)
		pb_discovery_set_lastid(lastid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
