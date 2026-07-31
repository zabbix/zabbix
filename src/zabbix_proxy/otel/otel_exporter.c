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
#include "zbxcfg.h"
#include "zbxcommon.h"

#define OTEL_EXPORTER_PROVIDER_URL	"url"
#define OTEL_EXPORTER_PROVIDER_USERNAME	"username"
#define OTEL_EXPORTER_PROVIDER_PASSWORD	"password"
#define OTEL_EXPORTER_PROVIDER_DB	"db"


ZBX_PTR_VECTOR_LITE_IMPL(otel_exporter_ptr, zbx_otel_exporter_t *)

static char	*otel_option_dup(const zbx_config_option_t *options, int options_num, const char *key, char **error)
{
	const char	*value;

	if (NULL == (value = zbx_config_option_value(options, options_num, key)))
	{
		*error = zbx_dsprintf(NULL, "missing mandatory ClickHouse telemetry provider option \"%s\"", key);
		return NULL;
	}

	return zbx_strdup(NULL, value);

}

static int	otel_clickhouse_cfg_init(zbx_otel_clickhouse_cfg_t *cfg, const zbx_config_option_t *options,
		int options_num, char **error)
{
	if (NULL == (cfg->url = otel_option_dup(options, options_num, OTEL_EXPORTER_PROVIDER_URL, error)))
		return FAIL;

	if (NULL == (cfg->database = otel_option_dup(options, options_num, OTEL_EXPORTER_PROVIDER_DB, error)))
		return FAIL;

	if (NULL == (cfg->username = otel_option_dup(options, options_num, OTEL_EXPORTER_PROVIDER_USERNAME, error)))
		return FAIL;

	if (NULL == (cfg->password = otel_option_dup(options, options_num, OTEL_EXPORTER_PROVIDER_PASSWORD, error)))
		return FAIL;

	return SUCCEED;
}

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
		case OTEL_EXPORTER_UNKNOWN:
			break;
		case OTEL_EXPORTER_CLICKHOUSE:
			otel_clickhouse_cfg_clear(&cfg->data.clickhouse);
			break;
	}

	zbx_config_option_clear_options(cfg->options.values, cfg->options.values_num);
	zbx_vector_config_option_destroy(&cfg->options);
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

int	otel_exporter_commit(zbx_otel_exporter_t *exporter, zbx_otel_dataset_t *ds)
{
	int	ret = SUCCEED;

	switch (exporter->cfg->type)
	{
		case OTEL_EXPORTER_CLICKHOUSE:
			ret = otel_clickhouse_commit(&exporter->conn.clickhouse, &exporter->cfg->data.clickhouse, ds);
			break;
	}

	return ret;
}

int	otel_exporter_cfg_init(zbx_otel_exporter_cfg_t *cfg, const char *options, char **error)
{
#define	OTEL_EXPORTER_PROVIDER		"clickhouse"
	ssize_t		len;
	const char	*ptr;
	int		ret = FAIL;

	memset(cfg, 0, sizeof(zbx_otel_exporter_cfg_t));
	zbx_vector_config_option_create(&cfg->options);

	len = zbx_config_option_parse_param(options);
	ptr = options + len;
	while (' ' == *ptr)
		ptr++;

	if (0 == len || ';' != *ptr)
	{
		*error = zbx_dsprintf(NULL, "invalid TelemetryProvider value \"%s\"", options);
		goto out;
	}

	while (' ' == *(++ptr))
		;

	if (SUCCEED != zbx_config_option_parse_options(ptr, &cfg->options, error))
		return FAIL;

	if (0 == strncmp(options, OTEL_EXPORTER_PROVIDER, ZBX_CONST_STRLEN(OTEL_EXPORTER_PROVIDER)))
	{
		cfg->type = OTEL_EXPORTER_CLICKHOUSE;
		if (FAIL == otel_clickhouse_cfg_init(&cfg->data.clickhouse, cfg->options.values,
				cfg->options.values_num, error))
		{
			goto out;
		}
	}
	else
	{
		*error = zbx_dsprintf(NULL, "invalid TelemetryProvider\"%s\"", options);
		goto out;
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		otel_exporter_cfg_clear(cfg);

#undef OTEL_EXPORTER_PROVIDER

	return ret;
}

