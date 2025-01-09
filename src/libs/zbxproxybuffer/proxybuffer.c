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

#include "proxybuffer.h"
#include "pb_autoreg.h"
#include "pb_discovery.h"
#include "pb_history.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxmutexs.h"
#include "zbxnum.h"
#include "zbxproxybuffer.h"
#include "zbxshmem.h"
#include "zbxstr.h"

#define PB_DB_FLUSH_DISABLED	0
#define PB_DB_FLUSH_ENABLED	1

ZBX_PTR_VECTOR_IMPL(pb_history_ptr, zbx_pb_history_t *)
ZBX_PTR_VECTOR_IMPL(pb_discovery_ptr, zbx_pb_discovery_t *)

static zbx_pb_t		*pb_data = NULL;
static zbx_shmem_info_t	*pb_mem = NULL;

ZBX_SHMEM_FUNC_IMPL(__pb, pb_mem)

static void	pb_init_state(zbx_pb_t *pb);

/* remap states to incoming data destination - database or memory */
zbx_pb_state_t	pb_dst[] = {PB_DATABASE, PB_MEMORY, PB_MEMORY, PB_DATABASE};

zbx_pb_state_t	get_pb_dst(int i)
{
	return pb_dst[i];
}

/* remap states to outgoing data source - database or memory */
zbx_pb_state_t	pb_src[] = {PB_DATABASE, PB_DATABASE, PB_MEMORY, PB_MEMORY};

zbx_pb_state_t	get_pb_src(int i)
{
	return pb_src[i];
}

const char	*pb_state_desc[] = {"database", "database->memory", "memory", "memory->database"};

void	pb_lock(void)
{
	zbx_mutex_lock(pb_data->mutex);
}

void	pb_unlock(void)
{
	zbx_mutex_unlock(pb_data->mutex);
}

void	*pb_malloc(size_t size)
{
	return __pb_shmem_malloc_func(NULL, size);
}

void	pb_free(void *ptr)
{
	__pb_shmem_free_func(ptr);
}

char	*pb_strdup(const char *str)
{
	size_t	len;
	char	*cpy;

	len = strlen(str) + 1;

	if (NULL == (cpy = (char *)__pb_shmem_malloc_func(NULL, len)))
		return NULL;

	memcpy(cpy, str, len);

	return cpy;
}

size_t	pb_get_free_size(void)
{
	return (size_t)pb_mem->free_size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free the required number of bytes in proxy memory buffer          *
 *                                                                            *
 * Return value: SUCCEED - the space was freed successfully                   *
 *               FAIL    - all records were discarded without freeing enough  *
 *                         space                                              *
 *                                                                            *
 * Comments: The space freed by discarding record is estimated and also the   *
 *           memory might be fragmented. So there is possibility that new     *
 *           record allocation will still fail. However calling this function *
 *           in loop would eventually discard all records - so that would     *
 *           indicate that cache is too small to hold the new record.         *
 *                                                                            *
 ******************************************************************************/
int	pb_free_space(zbx_pb_t *pb, size_t size)
{
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() size:" ZBX_FS_SIZE_T, __func__, size);

	ssize_t	size_left = (ssize_t)size;

	while (0 < size_left && SUCCEED == ret)
	{
		zbx_pb_history_t	*hrow;
		zbx_pb_discovery_t	*drow;
		zbx_pb_autoreg_t	*arow;
		int			clock;

		if (SUCCEED == zbx_list_peek(&pb->history, (void **)&hrow))
		{
			clock = hrow->ts.sec;
		}
		else
		{
			hrow = NULL;
			clock = INT_MAX;
		}

		if (SUCCEED == zbx_list_peek(&pb->discovery, (void **)&drow) && drow->clock < clock)
		{
			clock = drow->clock;
			hrow = NULL;
		}
		else
			drow = NULL;

		if (SUCCEED == zbx_list_peek(&pb->autoreg, (void **)&arow) && arow->clock < clock)
		{
			zabbix_log(LOG_LEVEL_TRACE, "%s() discarding auto registration record from proxy memory buffer,"
					" id:" ZBX_FS_UI64 " clock:%d", __func__, arow->id, arow->clock);

			zbx_list_pop(&pb->autoreg, NULL);
			size_left -= (ssize_t)pb_autoreg_estimate_row_size(arow->host, arow->host_metadata,
					arow->listen_ip, arow->listen_dns);
			pb_list_free_autoreg(&pb->autoreg, arow);
			continue;
		}

		if (NULL != hrow)
		{
			zabbix_log(LOG_LEVEL_TRACE, "%s() discarding history record from proxy memory buffer,"
					" id:" ZBX_FS_UI64 " clock:%d", __func__, hrow->id, hrow->ts.sec);

			zbx_list_pop(&pb->history, NULL);
			size_left -= (ssize_t)pb_history_estimate_row_size(hrow->value, hrow->source);
			pb_list_free_history(&pb->history, hrow);
			continue;
		}

		if (NULL != drow)
		{
			zbx_uint64_t	handleid = drow->handleid;

			do
			{
				zabbix_log(LOG_LEVEL_TRACE, "%s() discarding discovery record from proxy memory buffer,"
						" id:" ZBX_FS_UI64 " clock:%d", __func__, drow->id, drow->clock);

				zbx_list_pop(&pb->discovery, NULL);
				size_left -= (ssize_t)pb_discovery_estimate_row_size(drow->value, drow->ip, drow->dns,
						drow->error);
				pb_list_free_discovery(&pb->discovery, drow);
			}
			while (SUCCEED == zbx_list_peek(&pb->discovery, (void **)&drow) && drow->handleid == handleid);

			continue;
		}

		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if proxy history table has unsent data and update lastid    *
 *          if necessary                                                      *
 *                                                                            *
 * Parameters: table  - [IN] history table                                    *
 *             field  - [IN] key field name                                   *
 *             lastid - [OUT] id of the last uploaded row                     *
 *             maxid  - [OUT] max id of the row in database                   *
 *                                                                            *
 * Return value: SUCCEED - table has unsent data                              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	pb_check_unsent_rows(const char *table, const char *field, zbx_uint64_t *lastid, zbx_uint64_t *maxid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:%s", __func__, table);

	result = zbx_db_select("select nextid from ids where table_name='%s' and field_name='%s'", table, field);

	if (NULL == (row = zbx_db_fetch(result)))
		*lastid = 0;
	else
		ZBX_STR2UINT64(*lastid, row[0]);

	zbx_db_free_result(result);

	result = zbx_db_select("select max(id) from %s", table);

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_DBROW2UINT64(*maxid, row[0]);
	else
		*maxid = 0;

	zbx_db_free_result(result);

	if (*lastid < *maxid)
	{
		ret = (0 < *maxid ? SUCCEED : FAIL);
	}
	else
	{
		ret = FAIL;

		if (*lastid > *maxid)
		{
			*lastid = *maxid;
			pb_set_lastid(table, field, *maxid);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s " ZBX_FS_UI64 "/" ZBX_FS_UI64, __func__, zbx_result_string(ret),
			*lastid, *maxid);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set cache state and log the changes                               *
 *                                                                            *
 ******************************************************************************/
void	pb_set_state(zbx_pb_t *pb, zbx_pb_state_t state, const char *message)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %s => %s", __func__, pb_state_desc[pb->state], pb_state_desc[state]);

	if (PB_DATABASE == pb->state || PB_MEMORY == pb->state)
		pb->changes_num++;

	switch (state)
	{
		case PB_DATABASE:
			zabbix_log(LOG_LEVEL_DEBUG, "switched proxy buffer to database state: %s", message);
			break;
		case PB_DATABASE_MEMORY:
			zabbix_log(LOG_LEVEL_DEBUG, "initiated proxy buffer transition to memory state: %s",
					message);
			break;
		case PB_MEMORY:
			zabbix_log(LOG_LEVEL_DEBUG, "switched proxy buffer to memory state: %s", message);
			break;
		case PB_MEMORY_DATABASE:
			zabbix_log(LOG_LEVEL_DEBUG, "initiated proxy buffer transition to database state: %s",
					message);
			break;
	}

	pb->state = state;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set initial buffer working state based on existing history data   *
 *                                                                            *
 ******************************************************************************/
static void	pb_init_state(zbx_pb_t *pb)
{
	int		history_ret, discovery_ret, autoreg_ret;
	zbx_uint64_t	lastid, maxid;

	if (ZBX_PB_MODE_MEMORY == pb->mode)
	{
		pb_set_state(pb, PB_MEMORY, "proxy buffer initialized in memory mode");
		return;
	}

	history_ret = pb_check_unsent_rows("proxy_history", "history_lastid", &lastid, &maxid);
	pb->history_lastid_db = maxid;
	pb->history_lastid_sent = lastid;

	discovery_ret = pb_check_unsent_rows("proxy_dhistory", "dhistory_lastid", &lastid, &maxid);
	autoreg_ret = pb_check_unsent_rows("proxy_autoreg_host", "autoreg_host_lastid", &lastid, &maxid);

	if (ZBX_PB_MODE_DISK == pb->mode)
		pb_set_state(pb, PB_DATABASE, "proxy buffer initialized in disk mode");
	else if (SUCCEED == history_ret || SUCCEED == discovery_ret || SUCCEED == autoreg_ret)
		pb_set_state(pb, PB_DATABASE, "unsent database records found");
	else
		pb_set_state(pb, PB_MEMORY, "no unsent database records found");
}

zbx_pb_t	*get_pb_data(void)
{
	return pb_data;
}

zbx_uint64_t	pb_get_lastid(const char *table_name, const char *lastidfield)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() field:'%s.%s'", __func__, table_name, lastidfield);

	result = zbx_db_select("select nextid from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == (row = zbx_db_fetch(result)))
		lastid = 0;
	else
		ZBX_STR2UINT64(lastid, row[0]);
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64,	__func__, lastid);

	return lastid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get discovery/auto registration data from the database.           *
 *                                                                            *
 ******************************************************************************/
void	pb_get_rows_db(struct zbx_json *j, const char *proto_tag, const zbx_history_table_t *ht,
		zbx_uint64_t *lastid, zbx_uint64_t *id, int *records_num, int *more)
{
	size_t		offset = 0;
	int		f, records_num_last = *records_num, retries = 1;
	char		sql[MAX_STRING_LEN];
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	struct timespec	t_sleep = { 0, 100000000L }, t_rem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __func__, ht->table);

	*more = ZBX_PROXY_DATA_DONE;

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select id");

	for (f = 0; NULL != ht->fields[f].field; f++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",%s", ht->fields[f].field);
try_again:
	zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s where id>" ZBX_FS_UI64 " order by id",
			ht->table, *id);

	result = zbx_db_select_n(sql, ZBX_MAX_HRECORDS);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(*lastid, row[0]);

		if (1 < *lastid - *id)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				zbx_db_free_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__func__, *lastid - *id - 1,
						(double)t_sleep.tv_sec + (double)t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__func__, *lastid - *id - 1);
			}
		}

		if (0 == *records_num)
			zbx_json_addarray(j, proto_tag);

		zbx_json_addobject(j, NULL);

		for (f = 0; NULL != ht->fields[f].field; f++)
		{
			if (NULL != ht->fields[f].default_value && 0 == strcmp(row[f + 1], ht->fields[f].default_value))
				continue;

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 1], ht->fields[f].jt);
		}

		(*records_num)++;

		zbx_json_close(j);

		/* stop gathering data to avoid exceeding the maximum packet size */
		if (ZBX_DATA_JSON_RECORD_LIMIT < j->buffer_offset)
		{
			*more = ZBX_PROXY_DATA_MORE;
			break;
		}

		*id = *lastid;
	}
	zbx_db_free_result(result);

	if (ZBX_MAX_HRECORDS == *records_num - records_num_last)
		*more = ZBX_PROXY_DATA_MORE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d lastid:" ZBX_FS_UI64 " more:%d size:" ZBX_FS_SIZE_T,
			__func__, *records_num - records_num_last, *lastid, *more,
			(zbx_fs_size_t)j->buffer_offset);
}

void	pb_set_lastid(const char *table_name, const char *lastidfield, const zbx_uint64_t lastid)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s:" ZBX_FS_UI64 "]", __func__, table_name, lastidfield, lastid);

	if (0 == lastid)
	{
		zbx_db_execute("delete from ids where table_name='%s' and field_name='%s'", table_name, lastidfield);
	}
	else
	{
		zbx_db_result_t	result;

		result = zbx_db_select("select 1 from ids where table_name='%s' and field_name='%s'",
				table_name, lastidfield);

		if (NULL == zbx_db_fetch(result))
		{
			zbx_db_execute("insert into ids (table_name,field_name,nextid) values ('%s','%s'," ZBX_FS_UI64
					")", table_name, lastidfield, lastid);
		}
		else
		{
			zbx_db_execute("update ids set nextid=" ZBX_FS_UI64 " where table_name='%s' and field_name='%s'",
					lastid, table_name, lastidfield);
		}
		zbx_db_free_result(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush cached lastids to the database                              *
 *                                                                            *
 ******************************************************************************/
static void	pb_flush_lastids(zbx_pb_t *pb)
{
	if (0 != pb->history_lastid_sent)
		pb_history_set_lastid(pb->history_lastid_sent);

	if (0 != pb->discovery_lastid_sent)
		pb_discovery_set_lastid(pb->discovery_lastid_sent);

	if (0 != pb->autoreg_lastid_sent)
		pb_autoreg_set_lastid(pb->autoreg_lastid_sent);
}

static void	pb_flush(zbx_pb_t *pb)
{

	if (ZBX_PB_MODE_MEMORY != pb->mode && (SUCCEED == pb_history_has_mem_rows(pb) ||
			SUCCEED == pb_discovery_has_mem_rows(pb) || SUCCEED == pb_autoreg_has_mem_rows(pb)))
	{
		do
		{
			zbx_db_begin();

			pb_autoreg_flush(pb);
			pb_discovery_flush(pb);
			pb_history_flush(pb);

			pb_flush_lastids(pb);
		}
		while (ZBX_DB_DOWN == zbx_db_commit());
	}

	pb_history_clear(pb, UINT64_MAX);
	pb_discovery_clear(pb, UINT64_MAX);
	pb_autoreg_clear(pb, UINT64_MAX);
}

void	pd_fallback_to_database(zbx_pb_t *pb, const char *message)
{
	pb_flush(pb);
	pb_set_state(pb, PB_DATABASE, message);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy buffer state after successful upload based on        *
 *          records left and handles pending                                  *
 *                                                                            *
 ******************************************************************************/
static void pb_update_state(zbx_pb_t *pb, int more)
{
	if (ZBX_PB_MODE_HYBRID != pb->mode)
		return;

	switch (pb->state)
	{
		case PB_MEMORY:
			/* memory state can switch to database when:        */
			/*   1) no space left to cache data                 */
			/*   2) cached data exceeds maximum age             */
			/* Both cases ar checked when adding data to cache. */
			break;
		case PB_MEMORY_DATABASE:
			if (SUCCEED != pb_history_has_mem_rows(pb) &&
					SUCCEED != pb_discovery_has_mem_rows(pb) &&
					SUCCEED != pb_autoreg_has_mem_rows(pb))
			{
				zbx_db_begin();
				pb_flush_lastids(pb);
				zbx_db_commit();
				pb_set_state(pb, PB_DATABASE, "memory records have been uploaded");
			}
			break;
		case PB_DATABASE:
			if (ZBX_PROXY_DATA_DONE == more)
				pb_set_state(pb, PB_DATABASE_MEMORY, "no more database records to upload");
			break;
		case PB_DATABASE_MEMORY:
			if (0 == pb_data->db_handles_num &&
					pb_data->history_lastid_db <= pb_data->history_lastid_sent &&
					pb_data->discovery_lastid_db <= pb_data->discovery_lastid_sent &&
					pb_data->autoreg_lastid_db <= pb_data->autoreg_lastid_sent)
			{
				pb_set_state(pb, PB_MEMORY, "database records have been uploaded");
			}
			break;
	}
}

zbx_uint64_t	pb_get_next_handleid(zbx_pb_t *pb)
{
	return ++pb->handleid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: register opened data handle                                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	pb_register_handle(zbx_pb_t *pb, zbx_vector_uint64_t *handleids)
{
	zbx_uint64_t	handleid;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	handleid = pb_get_next_handleid(pb);
	zbx_vector_uint64_append(handleids, handleid);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() handleid:" ZBX_FS_UI64 " handles:%d", __func__, handleid,
			handleids->values_num);

	return handleid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deregister data handle                                            *
 *                                                                            *
 ******************************************************************************/
void	pb_deregister_handle(zbx_vector_uint64_t *handleids, zbx_uint64_t handleid)
{
	int	i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() handleid:" ZBX_FS_UI64, __func__, handleid);

	if (FAIL != (i = zbx_vector_uint64_search(handleids, handleid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(handleids, i);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() handles:%d", __func__, handleids->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for the opened data handles to be closed                     *
 *                                                                            *
 * parameters: handleids - [IN] handle list to wait on                        *
 *                                                                            *
 ******************************************************************************/
void	pb_wait_handles(const zbx_vector_uint64_t *handleids)
{
	int		i;
	zbx_uint64_t	handleid = 0;
	struct timespec	delay = { 0, 100000000L };

	if (NULL == handleids)
	{
		nanosleep(&delay, NULL);
		return;
	}

	pb_lock();

	for (i = 0; i < handleids->values_num; i++)
	{
		if (handleids->values[i] > handleid)
			handleid = handleids->values[i];
	}

	pb_unlock();

	if (0 != handleid)
	{
		int	wait_handles;

		do
		{
			wait_handles = 0;

			nanosleep(&delay, NULL);

			pb_lock();

			for (i = 0; i < handleids->values_num; i++)
			{
				if (handleids->values[i] <= handleid)
				{
					wait_handles = 1;
					break;
				}
			}

			pb_unlock();
		}
		while (0 != wait_handles);
	}
}

/* public api */

/******************************************************************************
 *                                                                            *
 * Purpose: create proxy  buffer                                              *
 *                                                                            *
 * Parameters: mode  - [IN]                                                   *
 *             size  - [IN] cache size in bytes                               *
 *             age   - [IN] maximum allowed data age                          *
 *             offline_buffer [IN] offline buffer in seconds                  *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - proxy buffer was created successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_pb_create(int mode, zbx_uint64_t size, int age, int offline_buffer, char **error)
{
	int	ret = FAIL, allow_oom;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() mode:%d", __func__, mode);

	if (ZBX_PB_MODE_DISK == mode)
	{
		/* allocate proxy buffer only to store statistics and track opened history handles */
		size = ZBX_KIBIBYTE * 16;

		allow_oom = 0;
	}
	else
		allow_oom = 1;

	if (FAIL == zbx_shmem_create(&pb_mem, size, "proxy memory buffer size", "ProxyMemoryBufferSize", allow_oom,
			error))
	{
		goto out;
	}

	pb_data = (zbx_pb_t *)__pb_shmem_malloc_func(NULL, sizeof(zbx_pb_t));
	memset(pb_data, 0, sizeof(zbx_pb_t));

	if (SUCCEED != zbx_mutex_create(&pb_data->mutex, ZBX_MUTEX_PROXY_BUFFER, error))
		goto out;

	zbx_list_create_ext(&pb_data->history, __pb_shmem_malloc_func, __pb_shmem_free_func);
	zbx_list_create_ext(&pb_data->discovery, __pb_shmem_malloc_func, __pb_shmem_free_func);
	zbx_list_create_ext(&pb_data->autoreg, __pb_shmem_malloc_func, __pb_shmem_free_func);

	zbx_vector_uint64_create_ext(&pb_data->history_handleids, __pb_shmem_malloc_func, __pb_shmem_realloc_func,
			__pb_shmem_free_func);
	/* preallocate handle tracking vector to avoid handling memory allocation errors later */
	zbx_vector_uint64_reserve(&pb_data->history_handleids, 100);

	pb_data->mode = mode;
	pb_data->max_age = age;
	pb_data->offline_buffer = offline_buffer;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __func__, ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize proxy  buffer                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_init(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pb_init_state(pb_data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): state:%d", __func__, pb_data->state);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy proxy buffer                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_destroy(void)
{
	zbx_mutex_destroy(&pb_data->mutex);
	zbx_shmem_destroy(pb_mem);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse proxy buffer mode configuration string                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_pb_parse_mode(const char *str, int *mode)
{
	if (NULL == str || '\0' == *str || 0 == strcmp(str, "disk"))
		*mode = ZBX_PB_MODE_DISK;
	else if (0 == strcmp(str, "memory"))
		*mode = ZBX_PB_MODE_MEMORY;
	else if (0 == strcmp(str, "hybrid"))
		*mode = ZBX_PB_MODE_HYBRID;
	else
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy buffer state based on records left and handles       *
 *          pending                                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_update_state(int more)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() more:%d", __func__, more);

	pb_lock();
	pb_update_state(pb_data, more);
	pb_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
}

void	zbx_pb_disable(void)
{
	pb_lock();

	if (ZBX_PB_MODE_HYBRID == pb_data->mode)
	{
		pb_data->state = PB_DATABASE;
		pb_data->mode = ZBX_PB_MODE_DISK;
	}

	pb_unlock();
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush cache to database                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_flush(void)
{
	pb_flush(pb_data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy buffer memory information                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_pb_get_mem_info(zbx_pb_mem_info_t *info, char **error)
{
	if (ZBX_PB_MODE_DISK == pb_data->mode)
	{
		*error = zbx_strdup(NULL, "Proxy memory buffer is disabled.");
		return FAIL;
	}

	pb_lock();

	info->mem_total = pb_mem->total_size;
	info->mem_used = pb_mem->total_size - pb_mem->free_size;

	pb_unlock();

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy buffer state information                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_get_state_info(zbx_pb_state_info_t *info)
{
	if (ZBX_PB_MODE_DISK == pb_data->mode)
	{
		info->changes_num = 0;
		info->state = 0;
	}
	else
	{
		pb_lock();
		info->state = (PB_MEMORY == pb_dst[pb_data->state] ? 1 : 0);
		info->changes_num = pb_data->changes_num;
		pb_unlock();
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get shared memory allocator statistics                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_pb_get_mem_stats(zbx_shmem_stats_t *stats)
{
	pb_lock();

	zbx_shmem_get_stats(pb_mem, stats);

	pb_unlock();
}

void	pb_add_json_field(struct zbx_json *j, zbx_history_table_t *history_table, const char *fld_name, void *value,
		int type)
{
	union
	{
		int		val_int;
		zbx_uint64_t	val_u64;
	}
	def;

	zbx_history_field_t	*fld = history_table->fields;

	while (NULL != fld->field && 0 != strcmp(fld->field, fld_name))
		fld++;

	if (NULL == fld->field ) {
		zabbix_log(LOG_LEVEL_WARNING, "%s() error of table:%s unknown field name:%s", __func__,
				history_table->table, fld_name);
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	switch (type)
	{
		case ZBX_TYPE_CHAR:
			if (NULL != fld->default_value && (0 == strcmp(fld->default_value, *(char**)value)))
				break;

			zbx_json_addstring(j, fld->tag, *(char**)value, ZBX_JSON_TYPE_STRING);
			break;
		case ZBX_TYPE_INT:
			if (NULL != fld->default_value && (SUCCEED == zbx_is_int(fld->default_value, &def.val_int) &&
					def.val_int == *(int*)value))
			{
				break;
			}

			zbx_json_addint64(j, fld->tag, *(int*)value);
			break;
		case ZBX_TYPE_UINT:
			if (NULL != fld->default_value && (SUCCEED == zbx_is_uint64(fld->default_value, &def.val_u64) &&
					def.val_u64 == *(zbx_uint64_t*)value))
			{
				break;
			}

			zbx_json_adduint64(j, fld->tag, *(zbx_uint64_t*)value);
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s() unsupported type:%d", __func__, type);
			THIS_SHOULD_NEVER_HAPPEN;
	}
}
