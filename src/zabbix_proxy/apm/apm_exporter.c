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
 * Parameters: cfg - [IN] exporter configuration                              *
 *                                                                            *
 ******************************************************************************/
static void	apm_exporter_cfg_clear(zbx_apm_exporter_cfg_t *cfg)
{
	switch (cfg->type)
	{
		case APM_EXPORTER_UNKNOWN:
		case APM_EXPORTER_GLOBAL:
			break;
		case APM_EXPORTER_CLICKHOUSE:
			apm_clickhouse_cfg_clear(&cfg->data.clickhouse);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy exporter configuration                                       *
 *                                                                            *
 * Parameters: dst - [OUT] destination configuration                          *
 *             src - [IN] source configuration                                *
 *                                                                            *
 ******************************************************************************/
static void	apm_exporter_cfg_copy(zbx_apm_exporter_cfg_t *dst, const zbx_apm_exporter_cfg_t *src)
{
	dst->type = src->type;

	switch (src->type)
	{
		case APM_EXPORTER_UNKNOWN:
		case APM_EXPORTER_GLOBAL:
			break;
		case APM_EXPORTER_CLICKHOUSE:
			apm_clickhouse_cfg_copy(&dst->data.clickhouse, &src->data.clickhouse);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: create an exporter pool with the specified configuration          *
 *                                                                            *
 * Parameters: cfg   - [IN] exporter configuration; ownership is              *
 *                          transferred to the pool on success, freed on      *
 *                          failure                                           *
 *             error - [OUT] error message if the operation fails             *
 *                                                                            *
 * Return value: created exporter pool, or NULL on error                      *
 *                                                                            *
 ******************************************************************************/
zbx_apm_exporter_pool_t	*apm_exporter_pool_create(zbx_apm_exporter_cfg_t *cfg, char **error)
{
	zbx_apm_exporter_pool_t	*pool;
	int				err;

	pool = (zbx_apm_exporter_pool_t *)zbx_calloc(NULL, 1, sizeof(zbx_apm_exporter_pool_t));
	pool->cfg = *cfg;

	if (0 != (err = pthread_mutex_init(&pool->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize open telemetry exporter mutex: %s", zbx_strerror(err));
		apm_exporter_cfg_clear(cfg);
		zbx_free(pool);

		return NULL;
	}

	zbx_vector_apm_exporter_ptr_create(&pool->exporters);

	return pool;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy global apm db config into exporter config for its db type    *
 *                                                                            *
 * Parameters: dst   - [OUT] exporter config to populate                      *
 *             src   - [IN] source apm db config                              *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - config copied, FAIL - unsupported db type          *
 *                                                                            *
 ******************************************************************************/
static int	apm_exporter_cfg_copy_global(zbx_apm_exporter_cfg_t *dst, const zbx_apm_db_config_t *src, char **error)
{
	switch (src->db_type)
	{
		case ZBX_APM_DB_TYPE_CLICKHOUSE:
			dst->type = APM_EXPORTER_CLICKHOUSE;
			apm_clickhouse_cfg_copy_global(&dst->data.clickhouse, src);
			break;
		default:
			*error = zbx_dsprintf(NULL, "unsupported telemetry provider type \"%u\"", src->db_type);
			return FAIL;
	}

	return SUCCEED;
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

	switch (pool->cfg.type)
	{
		case APM_EXPORTER_UNKNOWN:
			*error = zbx_dsprintf(NULL, "unknown exporter type \"%u\"", pool->cfg.type);
			break;
		case APM_EXPORTER_GLOBAL:
			if (FAIL != apm_exporter_cfg_copy_global(&exporter->cfg, &pool->cfg_global, error))
			{
				ret = apm_clickhouse_init(&exporter->conn.clickhouse, &exporter->cfg.data.clickhouse,
						error);
			}
			break;
		case APM_EXPORTER_CLICKHOUSE:
			apm_exporter_cfg_copy(&exporter->cfg, &pool->cfg);
			ret = apm_clickhouse_init(&exporter->conn.clickhouse, &exporter->cfg.data.clickhouse, error);
			break;
	}

	if (FAIL == ret)
		zbx_free(exporter);


	return exporter;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by an exporter pool                      *
 *                                                                            *
 * Parameters: pool - [IN] exporter pool to destroy                           *
 *                                                                            *
 ******************************************************************************/
void	apm_exporter_pool_destroy(zbx_apm_exporter_pool_t *pool)
{
	for (int i = 0; i < pool->exporters.values_num; i++)
		apm_exporter_destroy(pool->exporters.values[i]);

	zbx_vector_apm_exporter_ptr_destroy(&pool->exporters);
	apm_exporter_cfg_clear(&pool->cfg);

	pthread_mutex_destroy(&pool->lock);

	zbx_free(pool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace global apm exporter pool configuration                    *
 *                                                                            *
 * Parameters: pool - [IN/OUT] apm exporter pool                              *
 *             cfg  - [IN] new global configuration to apply                  *
 *                                                                            *
 ******************************************************************************/
void	apm_exporter_pool_set_global_config(zbx_apm_exporter_pool_t *pool, zbx_apm_db_config_t *cfg)
{
	pthread_mutex_lock(&pool->lock);

	zbx_apm_db_config_clear(&pool->cfg_global);
	zbx_apm_db_config_copy(&pool->cfg_global, cfg);

	pthread_mutex_unlock(&pool->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by an exporter connection                *
 *                                                                            *
 * Parameters: exporter - [IN] exporter to destroy                            *
 *                                                                            *
 ******************************************************************************/
static void	apm_exporter_destroy(zbx_apm_exporter_t *exporter)
{
	switch (exporter->cfg.type)
	{
		case APM_EXPORTER_UNKNOWN:
		case APM_EXPORTER_GLOBAL:
			break;
		case APM_EXPORTER_CLICKHOUSE:
			apm_clickhouse_clear(&exporter->conn.clickhouse);
			break;
	}

	apm_exporter_cfg_clear(&exporter->cfg);
	zbx_free(exporter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether a pooled exporter connection can still be used      *
 *                                                                            *
 * Parameters: pool - [IN/OUT] exporter pool                                  *
 *             exporter - [IN] exporter to validate                           *
 *                                                                            *
 * Return value: SUCCEED if the exporter can be reused, FAIL otherwise        *
 *                                                                            *
 ******************************************************************************/
static int	apm_exporter_validate(zbx_apm_exporter_pool_t *pool, const zbx_apm_exporter_t *exporter)
{
	if (APM_EXPORTER_GLOBAL != pool->cfg.type)
		return SUCCEED;

	switch (pool->cfg_global.db_type)
	{
		case ZBX_APM_DB_TYPE_CLICKHOUSE:
			if (APM_EXPORTER_CLICKHOUSE != exporter->cfg.type)
				return FAIL;
			return apm_clickhouse_cfg_compare_global(&exporter->cfg.data.clickhouse, &pool->cfg_global);
		default:
			break;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire an exporter connection from the pool, creating one        *
 *          if none is available                                              *
 *                                                                            *
 * Parameters: pool - [IN/OUT] exporter pool to acquire from                  *
 *                                                                            *
 * Return value: acquired exporter connection                                 *
 *                                                                            *
 * Comments: Terminates the process if a new exporter connection cannot       *
 *           be created.                                                      *
 *                                                                            *
 ******************************************************************************/
zbx_apm_exporter_t	*apm_exporter_acquire(zbx_apm_exporter_pool_t *pool)
{
	zbx_apm_exporter_t	*exporter;
	char			*error = NULL;

	pthread_mutex_lock(&pool->lock);

	do
	{
		if (0 == pool->exporters.values_num)
		{
			if (NULL == (exporter = apm_exporter_create(pool, &error)))
			{
				zabbix_log(LOG_LEVEL_ERR, "Cannot create Open Telemetry exporter: %s", error);
				zbx_free(error);
				zbx_exit(EXIT_FAILURE);
			}
		}
		else
		{
			exporter = pool->exporters.values[pool->exporters.values_num - 1];
			zbx_vector_apm_exporter_ptr_remove(&pool->exporters, pool->exporters.values_num - 1);

			if (SUCCEED != apm_exporter_validate(pool, exporter))
			{
				apm_exporter_destroy(exporter);
				exporter = NULL;
			}
		}
	}
	while (NULL == exporter);

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

	switch (exporter->cfg.type)
	{
		case APM_EXPORTER_UNKNOWN:
		case APM_EXPORTER_GLOBAL:
			break;
		case APM_EXPORTER_CLICKHOUSE:
			ret = apm_clickhouse_commit(&exporter->conn.clickhouse, &exporter->cfg.data.clickhouse, ds);
			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse TelemetryProvider configuration into exporter               *
 *          configuration                                                     *
 *                                                                            *
 * Parameters: cfg           - [OUT] exporter configuration                   *
 *             export_config - [IN] TelemetryProvider configuration           *
 *             error         - [OUT] error message if the operation fails     *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	apm_exporter_cfg_init(zbx_apm_exporter_cfg_t *cfg, const zbx_apm_db_config_t *export_config, char **error)
{
	int		ret = FAIL;

	memset(cfg, 0, sizeof(zbx_apm_exporter_cfg_t));

	if (0 == export_config->status)
	{
		cfg->type = APM_EXPORTER_GLOBAL;
		ret = SUCCEED;

		goto out;
	}

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == export_config->db_type)
	{
		cfg->type = APM_EXPORTER_CLICKHOUSE;
		if (FAIL == apm_clickhouse_cfg_init(&cfg->data.clickhouse, export_config, error))
		{
			goto out;
		}
	}
	else
	{
		*error = zbx_dsprintf(NULL, "unknown telemetry provoder type \"%u\"", export_config->db_type);
		goto out;
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		apm_exporter_cfg_clear(cfg);

	return ret;
}

