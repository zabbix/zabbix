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

#include "otel_exporter.h"

ZBX_PTR_VECTOR_LITE_IMPL(otel_exporter_ptr, zbx_otel_exporter_t *)

static void	otel_clickhouse_cfg_clear(zbx_otel_clickhouse_cfg_t *cfg)
{
	zbx_free(cfg->url);
	zbx_free(cfg->database);
	zbx_free(cfg->username);
	zbx_free(cfg->password);
}

static void	otel_exporter_cfg_clear(zbx_otel_exporter_cfg_t *cfg)
{
	switch (cfg->type)
	{
		case OTEL_EXPORTER_CLICKHOUSE:
			otel_clickhouse_cfg_clear(&cfg->data.clickhouse);
			break;
	}
}

zbx_otel_exporter_pool_t	*otel_exporter_pool_create(zbx_otel_exporter_cfg_t *cfg, char **error)
{
	zbx_otel_exporter_pool_t	*pool;
	int				err;

	pool = (zbx_otel_exporter_pool_t *)zbx_calloc(NULL, 1, sizeof(zbx_otel_exporter_pool_t));
	pool->cfg = *cfg;

	if (0 != (err = pthread_mutex_init(&pool->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize open telemetry exporter mutex: %s", zbx_strerror(err));
		otel_exporter_cfg_clear(cfg);
		zbx_free(pool);

		return NULL;
	}

	zbx_vector_otel_exporter_ptr_create(&pool->exporters);

	return pool;
}

static zbx_otel_exporter_t *otel_exporter_create(zbx_otel_exporter_cfg_t *cfg, char **error)
{
	zbx_otel_exporter_t	*exporter;
	int			ret = FAIL;

	exporter = (zbx_otel_exporter_t *)zbx_calloc(NULL, 1, sizeof(zbx_otel_exporter_t));
	exporter->cfg = cfg;

	switch (cfg->type)
	{
		case OTEL_EXPORTER_CLICKHOUSE:
			ret = otel_clickhouse_init(&exporter->conn.clickhouse, &cfg->data.clickhouse, error);
			break;
	}

	if (FAIL == ret)
		zbx_free(exporter);


	return exporter;
}

static void	otel_exporter_destroy(zbx_otel_exporter_t *exporter)
{
	switch (exporter->cfg->type)
	{
		case OTEL_EXPORTER_CLICKHOUSE:
			otel_clickhouse_clear(&exporter->conn.clickhouse);
			break;
	}

	zbx_free(exporter);
}

void	otel_exporter_pool_destroy(zbx_otel_exporter_pool_t *pool)
{
	for (int i = 0; i < pool->exporters.values_num; i++)
		otel_exporter_destroy(pool->exporters.values[i]);

	zbx_vector_otel_exporter_ptr_destroy(&pool->exporters);
	otel_exporter_cfg_clear(&pool->cfg);

	zbx_free(pool);
}

zbx_otel_exporter_t	*otel_exporter_acquire(zbx_otel_exporter_pool_t *pool)
{
	zbx_otel_exporter_t	*exporter;
	char			*error = NULL;

	pthread_mutex_lock(&pool->lock);

	if (0 == pool->exporters.values_num)
	{
		if (NULL == (exporter = otel_exporter_create(&pool->cfg, &error)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot create Open Telemetry exporter: %s", error);
			zbx_free(error);
			zbx_exit(EXIT_FAILURE);
		}
	}
	else
	{
		exporter = pool->exporters.values[pool->exporters.values_num - 1];
		zbx_vector_otel_exporter_ptr_remove(&pool->exporters, pool->exporters.values_num - 1);
	}

	pthread_mutex_unlock(&pool->lock);

	return exporter;
}

void	otel_exporter_release(zbx_otel_exporter_pool_t *pool, zbx_otel_exporter_t *exporter)
{
	pthread_mutex_lock(&pool->lock);
	zbx_vector_otel_exporter_ptr_append(&pool->exporters, exporter);
	pthread_mutex_unlock(&pool->lock);
}

void	otel_exporter_commit(zbx_otel_exporter_t *exporter, zbx_otel_dataset_t *ds)
{
	switch (exporter->cfg->type)
	{
		case OTEL_EXPORTER_CLICKHOUSE:
			otel_clickhouse_commit(&exporter->conn.clickhouse, &exporter->cfg->data.clickhouse, ds);
			break;
	}
}

int	otel_exporter_cfg_init(zbx_otel_exporter_cfg_t *cfg, const char *options, char **error)
{
	/* TODO: implement proper options parsing */
	cfg->type = OTEL_EXPORTER_CLICKHOUSE;
	cfg->data.clickhouse.url = zbx_strdup(NULL, "http://localhost");
	cfg->data.clickhouse.database = zbx_strdup(NULL, "zabbix");
	cfg->data.clickhouse.username = zbx_strdup(NULL, "zb");
	cfg->data.clickhouse.password = zbx_strdup(NULL, "2b");

	return SUCCEED;
}

