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

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxmutexs.h"
#include "zbxshmem.h"
#include "zbxdbhigh.h"

ZBX_PTR_VECTOR_IMPL(pdc_history_ptr, zbx_pdc_history_t *)
ZBX_PTR_VECTOR_IMPL(pdc_discovery_ptr, zbx_pdc_discovery_t *)
ZBX_PTR_VECTOR_IMPL(pdc_autoreg_ptr, zbx_pdc_autoreg_t *)

static zbx_pdc_t	*pdc_cache = NULL;
static zbx_shmem_info_t	*pdc_mem = NULL;

ZBX_SHMEM_FUNC_IMPL(__pdc, pdc_mem)

static void	pdc_cache_set_init_state(zbx_pdc_t *pdc);

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
	zbx_uint64_t	size_actual, size_entry;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == size)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): proxy data cache disabled", __func__);

		pdc_cache = (zbx_pdc_t *)zbx_malloc(NULL, sizeof(zbx_pdc_t));
		memset(pdc_cache, 0, sizeof(zbx_pdc_t));

		pdc_cache->state = PDC_DATABASE_ONLY;
		pdc_cache->mutex = ZBX_MUTEX_NULL;

		ret = SUCCEED;

		goto out;
	}

	if (SUCCEED != zbx_shmem_create(&pdc_mem, size, "proxy data cache size", "ProxyDataCacheSize", 1, error))
		goto out;

	pdc_cache = (zbx_pdc_t *)__pdc_shmem_realloc_func(NULL, sizeof(zbx_pdc_t));

	zbx_vector_pdc_history_ptr_create_ext(&pdc_cache->history, __pdc_shmem_malloc_func, __pdc_shmem_realloc_func,
			__pdc_shmem_free_func);
	zbx_vector_pdc_discovery_ptr_create_ext(&pdc_cache->discovery, __pdc_shmem_malloc_func, __pdc_shmem_realloc_func,
			__pdc_shmem_free_func);
	zbx_vector_pdc_autoreg_ptr_create_ext(&pdc_cache->autoreg, __pdc_shmem_malloc_func, __pdc_shmem_realloc_func,
			__pdc_shmem_free_func);

	pdc_cache->max_age = age;

	if (SUCCEED != zbx_mutex_create(&pdc_cache->mutex, ZBX_MUTEX_PROXY_DATACACHE, error))
		goto out;

	pdc_cache_set_init_state(pdc_cache);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __func__, ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

static void	pdc_lock(zbx_pdc_t *pdc)
{
	if (NULL != pdc->mutex)
		zbx_mutex_lock(pdc->mutex);
}

static void	pdc_unlock(zbx_pdc_t *pdc)
{
	if (NULL != pdc->mutex)
		zbx_mutex_unlock(pdc->mutex);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if proxy history table has unsent data                      *
 *                                                                            *
 * Return value: SUCCEED - table has unsent data                              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	pdc_has_history(const char *table_name)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	lastid;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:%s", __func__, table_name);

	result = zbx_db_select("select nextid from ids where table_name='%s' and field_name='id'", table_name);

	if (NULL == (row = zbx_db_fetch(result)))
		lastid = 0;
	else
		ZBX_STR2UINT64(lastid, row[0]);

	zbx_db_free_result(result);

	result = zbx_db_select_n("select null from %s where id>" ZBX_FS_UI64, 1);

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
 * Purpose: set initial cache working state based on existing history data    *
 *                                                                            *
 ******************************************************************************/
static void	pdc_cache_set_init_state(zbx_pdc_t *pdc)
{
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (SUCCEED == pdc_has_history("proxy_history") || SUCCEED == pdc_has_history("proxy_dhistory") ||
			SUCCEED == pdc_has_history("proxy_autoreg_host"))
	{
		pdc->state = PDC_DATABASE;
	}
	else
		pdc->state = PDC_MEMORY;

	zbx_db_close();
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
	return pdc_discovery_open();
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush the cached discovery data and free the handle               *
 *                                                                            *
 ******************************************************************************/
void	zbx_pdc_discovery_close(zbx_pdc_discovery_data_t *data)
{
	pdc_discovery_close(pdc_cache, data);
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
	pdc_discovery_write_host(data, druleid, ip, dns, status, clock);
}



