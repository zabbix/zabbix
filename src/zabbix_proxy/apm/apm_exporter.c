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
#include "zbxcfg.h"
#include "zbxcommon.h"

#define APM_EXPORTER_PROVIDER_URL	"url"
#define APM_EXPORTER_PROVIDER_USERNAME	"username"
#define APM_EXPORTER_PROVIDER_PASSWORD	"password"
#define APM_EXPORTER_PROVIDER_DB	"db"


ZBX_PTR_VECTOR_LITE_IMPL(apm_exporter_ptr, zbx_apm_exporter_t *)

static char	*apm_option_dup(const zbx_config_option_t *options, int options_num, const char *key, char **error)
{
	const char	*value;

	if (NULL == (value = zbx_config_option_value(options, options_num, key)))
	{
		*error = zbx_dsprintf(NULL, "missing mandatory ClickHouse telemetry provider option \"%s\"", key);
		return NULL;
	}

	return zbx_strdup(NULL, value);

}

static int	apm_clickhouse_cfg_init(zbx_apm_clickhouse_cfg_t *cfg, const zbx_config_option_t *options,
		int options_num, char **error)
{
#if defined(HAVE_LIBCURL)
	if (NULL == (cfg->url = apm_option_dup(options, options_num, APM_EXPORTER_PROVIDER_URL, error)))
		return FAIL;

	if (NULL == (cfg->database = apm_option_dup(options, options_num, APM_EXPORTER_PROVIDER_DB, error)))
		return FAIL;

	if (NULL == (cfg->username = apm_option_dup(options, options_num, APM_EXPORTER_PROVIDER_USERNAME, error)))
		return FAIL;

	if (NULL == (cfg->password = apm_option_dup(options, options_num, APM_EXPORTER_PROVIDER_PASSWORD, error)))
		return FAIL;

	return SUCCEED;
#else
	ZBX_UNUSED(cfg);
	ZBX_UNUSED(options);
	ZBX_UNUSED(options_num);

	*error = zbx_strdup(NULL, "ClickHouse telemetry provider requires curl library."
			" This Zabbix server binary was compiled without curl");

	return FAIL;
#endif
}

static void	apm_clickhouse_cfg_clear(zbx_apm_clickhouse_cfg_t *cfg)
{
	zbx_free(cfg->url);
	zbx_free(cfg->database);
	zbx_free(cfg->username);
	zbx_free(cfg->password);
}

static void	apm_clickhouse_cfg_copy(zbx_apm_clickhouse_cfg_t *dst, const zbx_apm_clickhouse_cfg_t *src)
{
	dst->url = zbx_strdup(NULL, src->url);
	dst->database = zbx_strdup(NULL, src->database);
	dst->username = zbx_strdup(NULL, src->username);
	dst->password = zbx_strdup(NULL, src->password);
}

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

	zbx_config_option_clear_options(cfg->options.values, cfg->options.values_num);
	zbx_vector_config_option_destroy(&cfg->options);
}

static void	apm_exporter_cfg_copy(zbx_apm_exporter_cfg_t *dst, const zbx_apm_exporter_cfg_t *src)
{
	dst->type = src->type;
	zbx_vector_config_option_create(&dst->options);

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

static zbx_apm_exporter_t *apm_exporter_create(zbx_apm_exporter_cfg_t *cfg, char **error)
{
	zbx_apm_exporter_t	*exporter;
	int			ret = FAIL;

	exporter = (zbx_apm_exporter_t *)zbx_calloc(NULL, 1, sizeof(zbx_apm_exporter_t));

	switch (cfg->type)
	{
		case APM_EXPORTER_UNKNOWN:
			break;
		case APM_EXPORTER_GLOBAL:
			/* TODO: get global configuration */
			break;
		case APM_EXPORTER_CLICKHOUSE:
			apm_exporter_cfg_copy(&exporter->cfg, cfg);
			ret = apm_clickhouse_init(&exporter->conn.clickhouse, &cfg->data.clickhouse, error);
			break;
	}

	if (FAIL == ret)
		zbx_free(exporter);


	return exporter;
}

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

void	apm_exporter_pool_destroy(zbx_apm_exporter_pool_t *pool)
{
	for (int i = 0; i < pool->exporters.values_num; i++)
		apm_exporter_destroy(pool->exporters.values[i]);

	zbx_vector_apm_exporter_ptr_destroy(&pool->exporters);
	apm_exporter_cfg_clear(&pool->cfg);

	pthread_mutex_destroy(&pool->lock);

	zbx_free(pool);
}

static int	apm_exporter_validate(const zbx_apm_exporter_t *exporter)
{
	if (APM_EXPORTER_GLOBAL != exporter->cfg.type)
		return SUCCEED;

	/* TODO: get global exporter configuration and compare credentials */

	return SUCCEED;
}

zbx_apm_exporter_t	*apm_exporter_acquire(zbx_apm_exporter_pool_t *pool)
{
	zbx_apm_exporter_t	*exporter;
	char			*error = NULL;

	pthread_mutex_lock(&pool->lock);

	do
	{
		if (0 == pool->exporters.values_num)
		{
			if (NULL == (exporter = apm_exporter_create(&pool->cfg, &error)))
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

			if (SUCCEED != apm_exporter_validate(exporter))
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

void	apm_exporter_release(zbx_apm_exporter_pool_t *pool, zbx_apm_exporter_t *exporter)
{
	pthread_mutex_lock(&pool->lock);
	zbx_vector_apm_exporter_ptr_append(&pool->exporters, exporter);
	pthread_mutex_unlock(&pool->lock);
}

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

int	apm_exporter_cfg_init(zbx_apm_exporter_cfg_t *cfg, const char *options, char **error)
{
#define	APM_PROVIDER_CLICKHOUSE		"clickhouse"
	ssize_t		len;
	const char	*ptr;
	int		ret = FAIL;

	memset(cfg, 0, sizeof(zbx_apm_exporter_cfg_t));
	zbx_vector_config_option_create(&cfg->options);

	if (NULL == options)
	{
		cfg->type = APM_EXPORTER_GLOBAL;
		ret = SUCCEED;

		goto out;
	}

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
		goto out;

	if (len == ZBX_CONST_STRLEN(APM_PROVIDER_CLICKHOUSE) && 0 == memcmp(options, APM_PROVIDER_CLICKHOUSE, len))
	{
		cfg->type = APM_EXPORTER_CLICKHOUSE;
		if (FAIL == apm_clickhouse_cfg_init(&cfg->data.clickhouse, cfg->options.values,
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
		apm_exporter_cfg_clear(cfg);

#undef APM_PROVIDER_CLICKHOUSE

	return ret;
}

