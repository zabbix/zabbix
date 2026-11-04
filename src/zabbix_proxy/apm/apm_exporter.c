/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "apm_exporter.h"
#include "apm_clickhouse.h"
#include "apm_dataset.h"
#include "zbxcommon.h"
#include "zbxtelemetry.h"
#include "zbxtypes.h"
#include "config.h"

ZBX_PTR_VECTOR_LITE_IMPL(apm_exporter_ptr, zbx_apm_exporter_t *)

static void	apm_exporter_destroy(zbx_apm_exporter_t *exporter);

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by exporter configuration                *
 *                                                                            *
 ******************************************************************************/
static void	apm_exporter_cfg_clear(zbx_apm_exporter_cfg_t *cfg)
{
	switch (cfg->type)
	{
		case APM_EXPORTER_UNKNOWN:
			break;
		case APM_EXPORTER_CLICKHOUSE:
			apm_clickhouse_cfg_clear(&cfg->data.clickhouse);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: release exporter configuration reference, freeing configuration   *
 *          when the last reference is released                               *
 *                                                                            *
 ******************************************************************************/
static void	apm_exporter_cfg_release(zbx_apm_exporter_cfg_t *cfg)
{
	if (1 != atomic_fetch_sub(&cfg->refcount, 1))
		return;

	apm_exporter_cfg_clear(cfg);
	zbx_free(cfg);
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert apm db configuration into exporter configuration          *
 *                                                                            *
 * Parameters: cfg        - [OUT] exporter configuration                      *
 *             apm_config - [IN] apm db configuration                         *
 *             error      - [OUT] error message if the operation fails        *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	apm_exporter_cfg_init(zbx_apm_exporter_cfg_t *cfg, const zbx_apm_db_config_t *apm_config, char **error)
{
	int	ret;

	switch (apm_config->db_type)
	{
		case ZBX_APM_DB_TYPE_CLICKHOUSE:
			if (SUCCEED == (ret = apm_clickhouse_cfg_init(&cfg->data.clickhouse, apm_config, error)))
				cfg->type = APM_EXPORTER_CLICKHOUSE;
			break;
		default:
			*error = zbx_dsprintf(NULL, "unknown telemetry provider type \"%u\"", apm_config->db_type);
			ret = FAIL;
			break;
	}

	if (FAIL == ret)
		apm_exporter_cfg_clear(cfg);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create exporter configuration                                     *
 *                                                                            *
 * Parameters: apm_config - [IN] apm db configuration                         *
 *             error      - [OUT] error message if the operation fails        *
 *                                                                            *
 * Return value: created exporter configuration with single reference, or     *
 *               NULL on error                                                *
 *                                                                            *
 ******************************************************************************/
static zbx_apm_exporter_cfg_t	*apm_exporter_cfg_create(const zbx_apm_db_config_t *apm_config, char **error)
{
	zbx_apm_exporter_cfg_t	*cfg;

	cfg = (zbx_apm_exporter_cfg_t *)zbx_calloc(NULL, 1, sizeof(zbx_apm_exporter_cfg_t));
	cfg->refcount = 1;

	if (SUCCEED != apm_exporter_cfg_init(cfg, apm_config, error))
	{
		zbx_free(cfg);
		return NULL;
	}

	return cfg;
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire exporter configuration reference                          *
 *                                                                            *
 * Parameters: cfg - [IN] exporter configuration                              *
 *                                                                            *
 * Return value: acquired exporter configuration                              *
 *                                                                            *
 ******************************************************************************/
static zbx_apm_exporter_cfg_t	*apm_exporter_cfg_acquire(zbx_apm_exporter_cfg_t *cfg)
{
	atomic_fetch_add(&cfg->refcount, 1);
	return cfg;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create exporter pool                                              *
 *                                                                            *
 * Parameters: apm_config - [IN] apm db configuration                         *
 *             error      - [OUT] error message if the operation fails        *
 *                                                                            *
 * Return value: created exporter pool, or NULL on error                      *
 *                                                                            *
 * Comments: When apm db configuration is disabled the pool is created        *
 *           without configuration, it must be set later with                 *
 *           apm_exporter_pool_update_config().                               *
 *                                                                            *
 ******************************************************************************/
zbx_apm_exporter_pool_t	*apm_exporter_pool_create(const zbx_apm_db_config_t *apm_config, char **error)
{
	zbx_apm_exporter_pool_t	*pool;
	int			err;
	zbx_apm_exporter_cfg_t	*cfg = NULL;

	if (0 != apm_config->status)
	{
		if (NULL == (cfg = apm_exporter_cfg_create(apm_config, error)))
			return NULL;
	}

	pool = (zbx_apm_exporter_pool_t *)zbx_calloc(NULL, 1, sizeof(zbx_apm_exporter_pool_t));

	if (0 != (err = pthread_mutex_init(&pool->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize open telemetry exporter mutex: %s", zbx_strerror(err));

		if (NULL != cfg)
			apm_exporter_cfg_release(cfg);

		zbx_free(pool);

		return NULL;
	}

	pool->cfg = cfg;

	zbx_vector_apm_exporter_ptr_create(&pool->exporters);

	return pool;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a new exporter connection using the specified              *
 *          configuration                                                     *
 *                                                                            *
 * Parameters: pool - [IN/OUT] exporter pool                                  *
 *             error - [OUT] error message if the operation fails             *
 *                                                                            *
 * Return value: created exporter, or NULL on error                           *
 *                                                                            *
 ******************************************************************************/
static zbx_apm_exporter_t	*apm_exporter_create(zbx_apm_exporter_pool_t *pool, char **error)
{
	zbx_apm_exporter_t	*exporter;
	int			ret = FAIL;

	exporter = (zbx_apm_exporter_t *)zbx_calloc(NULL, 1, sizeof(zbx_apm_exporter_t));

	switch (pool->cfg->type)
	{
		case APM_EXPORTER_UNKNOWN:
			*error = zbx_dsprintf(NULL, "unknown exporter type \"%u\"", pool->cfg->type);
			break;
		case APM_EXPORTER_CLICKHOUSE:
			ret = apm_clickhouse_init(&exporter->conn.clickhouse, &pool->cfg->data.clickhouse, error);
			break;
	}

	if (SUCCEED == ret)
		exporter->cfg = apm_exporter_cfg_acquire(pool->cfg);
	else
		zbx_free(exporter);

	return exporter;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by an exporter pool                      *
 *                                                                            *
 ******************************************************************************/
void	apm_exporter_pool_destroy(zbx_apm_exporter_pool_t *pool)
{
	for (int i = 0; i < pool->exporters.values_num; i++)
		apm_exporter_destroy(pool->exporters.values[i]);

	zbx_vector_apm_exporter_ptr_destroy(&pool->exporters);

	if (NULL != pool->cfg)
		apm_exporter_cfg_release(pool->cfg);

	pthread_mutex_destroy(&pool->lock);

	zbx_free(pool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether exporter configuration matches apm db configuration *
 *                                                                            *
 * Parameters: cfg        - [IN] exporter configuration                       *
 *             apm_config - [IN] apm db configuration                         *
 *                                                                            *
 * Return value: SUCCEED - configs match, FAIL - configs differ               *
 *                                                                            *
 ******************************************************************************/
static int	apm_exporter_cfg_compare(const zbx_apm_exporter_cfg_t *cfg, const zbx_apm_db_config_t *apm_config)
{
	switch (apm_config->db_type)
	{
		case ZBX_APM_DB_TYPE_CLICKHOUSE:
			if (APM_EXPORTER_CLICKHOUSE != cfg->type)
				return FAIL;
			return apm_clickhouse_cfg_compare(&cfg->data.clickhouse, apm_config);
		default:
			return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace exporter pool configuration                               *
 *                                                                            *
 * Parameters: pool - [IN/OUT] exporter pool                                  *
 *             cfg  - [IN] new exporter configuration, can be NULL            *
 *                                                                            *
 ******************************************************************************/
static void	apm_exporter_pool_set_config(zbx_apm_exporter_pool_t *pool, zbx_apm_exporter_cfg_t *cfg)
{
	zbx_apm_exporter_cfg_t	*cfg_old;

	pthread_mutex_lock(&pool->lock);
	cfg_old = pool->cfg;
	pool->cfg = cfg;
	pthread_mutex_unlock(&pool->lock);

	if (NULL != cfg_old)
		apm_exporter_cfg_release(cfg_old);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update exporter pool configuration if apm db configuration has    *
 *          changed                                                           *
 *                                                                            *
 * Parameters: pool       - [IN/OUT] exporter pool                            *
 *             apm_config - [IN] apm db configuration                         *
 *             error      - [OUT] error message if the operation fails        *
 *                                                                            *
 * Return value: SUCCEED - configuration was updated or has not changed       *
 *               FAIL    - invalid configuration                              *
 *                                                                            *
 * Comments: On failure the pool configuration is cleared, making the pool    *
 *           inactive.                                                        *
 *                                                                            *
 ******************************************************************************/
int	apm_exporter_pool_update_config(zbx_apm_exporter_pool_t *pool, const zbx_apm_db_config_t *apm_config,
		char **error)
{
	zbx_apm_exporter_cfg_t	*cfg;
	int			ret = FAIL;

	pthread_mutex_lock(&pool->lock);
	cfg = (NULL != pool->cfg ? apm_exporter_cfg_acquire(pool->cfg) : NULL);
	pthread_mutex_unlock(&pool->lock);

	if (NULL != cfg)
	{
		ret = apm_exporter_cfg_compare(cfg, apm_config);
		apm_exporter_cfg_release(cfg);
	}

	if (SUCCEED == ret)
		return SUCCEED;

	cfg = apm_exporter_cfg_create(apm_config, error);
	apm_exporter_pool_set_config(pool, cfg);

	return (NULL == cfg ? FAIL : SUCCEED);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear exporter pool configuration, making the pool inactive       *
 *                                                                            *
 ******************************************************************************/
void	apm_exporter_pool_clear_config(zbx_apm_exporter_pool_t *pool)
{
	apm_exporter_pool_set_config(pool, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether exporter pool has valid configuration               *
 *                                                                            *
 * Parameters: pool - [IN] exporter pool                                      *
 *                                                                            *
 * Return value: SUCCEED - pool is configured, FAIL - otherwise               *
 *                                                                            *
 ******************************************************************************/
int	apm_exporter_pool_is_active(zbx_apm_exporter_pool_t *pool)
{
	int	ret;

	pthread_mutex_lock(&pool->lock);
	ret = (NULL == pool->cfg ? FAIL : SUCCEED);
	pthread_mutex_unlock(&pool->lock);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by an exporter connection                *
 *                                                                            *
 ******************************************************************************/
static void	apm_exporter_destroy(zbx_apm_exporter_t *exporter)
{
	switch (exporter->cfg->type)
	{
		case APM_EXPORTER_UNKNOWN:
			break;
		case APM_EXPORTER_CLICKHOUSE:
			apm_clickhouse_clear(&exporter->conn.clickhouse);
			break;
	}

	apm_exporter_cfg_release(exporter->cfg);
	zbx_free(exporter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire an exporter connection from the pool, creating one        *
 *          if none is available                                              *
 *                                                                            *
 * Parameters: pool - [IN/OUT] exporter pool to acquire from                  *
 *                                                                            *
 * Return value: acquired exporter connection, or NULL if pool is not         *
 *               configured                                                   *
 *                                                                            *
 * Comments: If a new exporter connection cannot be created, the pool         *
 *           configuration is cleared, making the pool inactive.              *
 *                                                                            *
 ******************************************************************************/
zbx_apm_exporter_t	*apm_exporter_acquire(zbx_apm_exporter_pool_t *pool)
{
	zbx_apm_exporter_t	*exporter = NULL;
	char			*error = NULL;

	pthread_mutex_lock(&pool->lock);

	if (NULL == pool->cfg)
		goto out;

	do
	{
		if (0 == pool->exporters.values_num)
		{
			if (NULL == (exporter = apm_exporter_create(pool, &error)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot create Open Telemetry exporter: %s", error);
				zbx_free(error);
				apm_exporter_cfg_release(pool->cfg);
				pool->cfg = NULL;
				break;
			}
		}
		else
		{
			exporter = pool->exporters.values[pool->exporters.values_num - 1];
			zbx_vector_apm_exporter_ptr_remove(&pool->exporters, pool->exporters.values_num - 1);

			if (exporter->cfg != pool->cfg)
			{
				apm_exporter_destroy(exporter);
				exporter = NULL;
			}
		}
	}
	while (NULL == exporter);
out:
	pthread_mutex_unlock(&pool->lock);

	return exporter;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return an exporter connection back to the pool                    *
 *                                                                            *
 * Parameters: pool     - [IN/OUT] exporter pool                              *
 *             exporter - [IN] exporter connection to return                  *
 *                                                                            *
 ******************************************************************************/
void	apm_exporter_release(zbx_apm_exporter_pool_t *pool, zbx_apm_exporter_t *exporter)
{
	pthread_mutex_lock(&pool->lock);
	zbx_vector_apm_exporter_ptr_append(&pool->exporters, exporter);
	pthread_mutex_unlock(&pool->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: commit dataset contents using the specified exporter              *
 *          connection                                                        *
 *                                                                            *
 * Parameters: exporter - [IN] exporter connection to commit through          *
 *             ds       - [IN/OUT] dataset to commit                          *
 *                                                                            *
 * Return value: combined APM_COMMIT_* flags returned by the exporter         *
 *                                                                            *
 ******************************************************************************/
int	apm_exporter_commit(zbx_apm_exporter_t *exporter, zbx_apm_dataset_t *ds)
{
	int	ret = APM_COMMIT_OK;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		apm_dataset_dump(ds);

	switch (exporter->cfg->type)
	{
		case APM_EXPORTER_UNKNOWN:
			break;
		case APM_EXPORTER_CLICKHOUSE:
			ret = apm_clickhouse_commit(&exporter->conn.clickhouse, &exporter->cfg->data.clickhouse, ds);
			break;
	}

	return ret;
}


