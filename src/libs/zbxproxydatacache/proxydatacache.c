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

#include "proxydatacache.h"
#include "zbxproxydatacache.h"
#include "pdc_discovery.h"
#include "pdc_autoreg.h"
#include "pdc_history.h"

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxmutexs.h"
#include "zbxshmem.h"
#include "zbxdbhigh.h"

ZBX_PTR_VECTOR_IMPL(pdc_history_ptr, zbx_pdc_history_t *)
ZBX_PTR_VECTOR_IMPL(pdc_discovery_ptr, zbx_pdc_discovery_t *)

zbx_pdc_t		*pdc_cache = NULL;
static zbx_shmem_info_t	*pdc_mem = NULL;

ZBX_SHMEM_FUNC_IMPL(__pdc, pdc_mem)

static void	pdc_cache_set_init_state(zbx_pdc_t *pdc);

/* remap states to incoming data destination - database or memory */
zbx_pdc_state_t	pdc_dst[] = {PDC_DATABASE, PDC_DATABASE, PDC_MEMORY, PDC_MEMORY, PDC_DATABASE};

/* remap states to outgoing data source - database or memory */
zbx_pdc_state_t	pdc_src[] = {PDC_DATABASE, PDC_DATABASE, PDC_DATABASE, PDC_MEMORY, PDC_MEMORY};

const char	*pdc_state_desc[] = {"database only", "database", "database->memory", "memory", "memory->database"};

void	pdc_lock()
{
	if (NULL != pdc_cache->mutex)
		zbx_mutex_lock(pdc_cache->mutex);
}

void	pdc_unlock()
{
	if (NULL != pdc_cache->mutex)
		zbx_mutex_unlock(pdc_cache->mutex);
}

void	*pdc_malloc(size_t size)
{
	return __pdc_shmem_malloc_func(NULL, size);
}

void	*pdc_realloc(void *ptr, size_t size)
{
	return __pdc_shmem_realloc_func(ptr, size);
}

void	pdc_free(void *ptr)
{
	__pdc_shmem_free_func(ptr);
}

char	*pdc_strdup(const char *str)
{
	size_t	len;
	char	*cpy;

	len = strlen(str) + 1;

	if (NULL == (cpy = (char *)__pdc_shmem_malloc_func(NULL, len)))
		return NULL;

	memcpy(cpy, str, len);

	return cpy;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if proxy history table has unsent data                      *
 *                                                                            *
 * Return value: SUCCEED - table has unsent data                              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	pdc_has_history(const char *table, const char *field)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	lastid;
	int		ret;
	char		sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:%s", __func__, table);

	result = zbx_db_select("select nextid from ids where table_name='%s' and field_name='%s'", table, field);

	if (NULL == (row = zbx_db_fetch(result)))
		lastid = 0;
	else
		ZBX_STR2UINT64(lastid, row[0]);

	zbx_db_free_result(result);

	zbx_snprintf(sql, sizeof(sql), "select null from %s where id>" ZBX_FS_UI64, table, lastid);

	result = zbx_db_select_n(sql, 1);

	if (NULL != (row = zbx_db_fetch(result)))
		ret = SUCCEED;
	else
		ret = FAIL;

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set cache state and log the changes                               *
 *                                                                            *
 ******************************************************************************/
void	pdc_cache_set_state(zbx_pdc_t *pdc, zbx_pdc_state_t state, const char *message)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %s => %s", __func__, pdc_state_desc[pdc->state], pdc_state_desc[state]);

	switch (state)
	{
		case PDC_DATABASE_ONLY:
			zabbix_log(LOG_LEVEL_WARNING, "proxy data cache disabled");
			break;
		case PDC_DATABASE:
			if (NULL != message)
				zabbix_log(LOG_LEVEL_WARNING, "%s", message);
			else
				zabbix_log(LOG_LEVEL_WARNING, "switched proxy data cache to database mode");
			break;
		case PDC_DATABASE_MEMORY:
			zabbix_log(LOG_LEVEL_WARNING, "initiated proxy data cache transition to memory mode: %s",
					message);
			break;
		case PDC_MEMORY:
			if (NULL != message)
				zabbix_log(LOG_LEVEL_WARNING, "%s", message);
			else
				zabbix_log(LOG_LEVEL_WARNING, "switched proxy data cache to memory mode");
			break;
		case PDC_MEMORY_DATABASE:
			zabbix_log(LOG_LEVEL_WARNING, "initiated proxy data cache transition to database mode: %s",
					message);
			break;
	}

	pdc->state = state;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set initial cache working state based on existing history data    *
 *                                                                            *
 ******************************************************************************/
static void	pdc_cache_set_init_state(zbx_pdc_t *pdc)
{
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (SUCCEED == pdc_has_history("proxy_history", "history_lastid") ||
			SUCCEED == pdc_has_history("proxy_dhistory", "dhistory_lastid") ||
			SUCCEED == pdc_has_history("proxy_autoreg_host", "autoreg_host_lastid"))
	{
		pdc_cache_set_state(pdc, PDC_DATABASE, "proxy data cache initialized in database mode");
	}
	else
		pdc_cache_set_state(pdc, PDC_MEMORY, "proxy data cache initialized in memory mode");

	zbx_db_close();
}

zbx_uint64_t	pdc_get_lastid(const char *table_name, const char *lastidfield)
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
void	pdc_get_rows_db(struct zbx_json *j, const char *proto_tag, const zbx_history_table_t *ht,
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
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
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

void	pdc_set_lastid(const char *table_name, const char *lastidfield, const zbx_uint64_t lastid)
{
	zbx_db_result_t	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s:" ZBX_FS_UI64 "]", __func__, table_name, lastidfield, lastid);

	result = zbx_db_select("select 1 from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == zbx_db_fetch(result))
	{
		zbx_db_execute("insert into ids (table_name,field_name,nextid) values ('%s','%s'," ZBX_FS_UI64 ")",
				table_name, lastidfield, lastid);
	}
	else
	{
		zbx_db_execute("update ids set nextid=" ZBX_FS_UI64 " where table_name='%s' and field_name='%s'",
				lastid, table_name, lastidfield);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	pdc_flush(zbx_pdc_t *pdc)
{
	pdc_autoreg_flush(pdc);
	pdc_discovery_flush(pdc);
	pdc_history_flush(pdc);
}

void	pdc_fallback_to_database(zbx_pdc_t *pdc)
{
	pdc_flush(pdc);

	pdc_cache_set_state(pdc, PDC_DATABASE, "aborted proxy data cache transition to memory mode: not enough space");
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy data cache state after successful upload based on    *
 *          records left and handles pending                                  *
 *                                                                            *
 ******************************************************************************/
static void	pdc_update_state(zbx_pdc_t *pdc, int more)
{
	switch (pdc->state)
	{
		case PDC_MEMORY:
			/* memory state can switch to database when:        */
			/*   1) no space left to cache data                 */
			/*   2) cached data exceeds maximum age             */
			/* Both cases ar checked when adding data to cache. */
			break;
		case PDC_MEMORY_DATABASE:
			if (ZBX_PROXY_DATA_DONE == more)
			{
				/* cache is empty, but flush also updates in database */
				pdc_flush(pdc);
				pdc_cache_set_state(pdc, PDC_DATABASE, NULL);
			}
			break;
		case PDC_DATABASE:
			if (ZBX_PROXY_DATA_DONE == more)
				pdc_cache_set_state(pdc, PDC_DATABASE_MEMORY, "no more database records to upload");
			break;
		case PDC_DATABASE_MEMORY:
			if (ZBX_PROXY_DATA_DONE == more && 0 == pdc_cache->db_handles_num &&
					pdc_cache->history_lastid_db <= pdc_cache->history_lastid_sent &&
					pdc_cache->discovery_lastid_db <= pdc_cache->discovery_lastid_sent &&
					pdc_cache->autoreg_lastid_db <= pdc_cache->autoreg_lastid_sent)
				pdc_cache_set_state(pdc, PDC_MEMORY, NULL);
			break;
		case PDC_DATABASE_ONLY:
			/* no state switching from database only mode */
			break;
	}
}

/* public api */

/******************************************************************************
 *                                                                            *
 * Purpose: initialize proxy data cache                                       *
 *                                                                            *
 * Return value: size  - [IN] the cache size in bytes                         *
 *               age   - [IN] the maximum allowed data age                    *
 *               error - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - cache was initialized successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_pdc_init(zbx_uint64_t size, int age, char **error)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == size)
	{
		pdc_cache = (zbx_pdc_t *)zbx_malloc(NULL, sizeof(zbx_pdc_t));
		memset(pdc_cache, 0, sizeof(zbx_pdc_t));

		pdc_cache->mutex = ZBX_MUTEX_NULL;
		pdc_cache_set_state(pdc_cache, PDC_DATABASE_ONLY, NULL);

		ret = SUCCEED;

		goto out;
	}

	if (SUCCEED != zbx_shmem_create(&pdc_mem, size, "proxy data cache size", "ProxyDataCacheSize", 1, error))
		goto out;

	pdc_cache = (zbx_pdc_t *)__pdc_shmem_realloc_func(NULL, sizeof(zbx_pdc_t));

	zbx_list_create_ext(&pdc_cache->history, __pdc_shmem_malloc_func, __pdc_shmem_free_func);
	zbx_list_create_ext(&pdc_cache->discovery, __pdc_shmem_malloc_func, __pdc_shmem_free_func);
	zbx_list_create_ext(&pdc_cache->autoreg, __pdc_shmem_malloc_func, __pdc_shmem_free_func);

	pdc_cache->max_age = age;

	if (SUCCEED != zbx_mutex_create(&pdc_cache->mutex, ZBX_MUTEX_PROXY_DATACACHE, error))
		goto out;

	pdc_cache_set_init_state(pdc_cache);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s state:%d", __func__, ZBX_NULL2EMPTY_STR(*error), pdc_cache->state);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy data cache state based on records left and handles   *
 *          pending                                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_update_state(int more)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() more:%d", __func__, more);

	pdc_lock();
	pdc_update_state(pdc_cache, more);
	pdc_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush cache to database                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_flush(void)
{
	zbx_db_begin();
	pdc_flush(pdc_cache);
	zbx_db_commit();

	pdc_cache->state = PDC_DATABASE_ONLY;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy data cache statistics                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_pdc_get_stats(zbx_pdc_stats_t *stats, char **error)
{
	if (ZBX_MUTEX_NULL == pdc_cache->mutex)
	{
		*error = zbx_strdup(NULL, "Proxy data cache is disabled.");
		return FAIL;
	}

	pdc_lock();

	stats->mem_total = pdc_mem->total_size;
	stats->mem_used = pdc_mem->total_size - pdc_mem->free_size;
	stats->state = pdc_cache->state;

	pdc_unlock();

	return SUCCEED;
}
