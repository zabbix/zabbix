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

#include "apm_config.h"
#include "zbxjson.h"

static void	apm_config_attrs_free(zbx_apm_config_attrs_t *attrs)
{
	for (int i = 0; i < attrs->metrics.values_num; i++)
	{
		zbx_free(attrs->metrics.values[i].tag);
		zbx_free(attrs->metrics.values[i].value);
	}
	zbx_vector_tag_destroy(&attrs->metrics);

	for (int i = 0; i < attrs->logs.values_num; i++)
	{
		zbx_free(attrs->logs.values[i].tag);
		zbx_free(attrs->logs.values[i].value);
	}
	zbx_vector_tag_destroy(&attrs->logs);

	for (int i = 0; i < attrs->traces.values_num; i++)
	{
		zbx_free(attrs->traces.values[i].tag);
		zbx_free(attrs->traces.values[i].value);
	}
	zbx_vector_tag_destroy(&attrs->traces);

	zbx_free(attrs);
}

static zbx_apm_config_attrs_t	*apm_config_attrs_create(const struct zbx_json_parse *jp)
{
#define APM_ATTR_SIGNAL	"signal_type"
#define APM_ATTR_KEY	"key"
#define APM_ATTR_VALUE	"value"

#define APM_SIGNAL_TRACES	"0"
#define APM_SIGNAL_METRICS	"1"
#define APM_SIGNAL_LOGS		"2"

	zbx_apm_config_attrs_t	*attrs;

	attrs = (zbx_apm_config_attrs_t *)zbx_malloc(NULL, sizeof(zbx_apm_config_attrs_t));

	zbx_vector_tag_create(&attrs->metrics);
	zbx_vector_tag_create(&attrs->logs);
	zbx_vector_tag_create(&attrs->traces);

	attrs->refcount = 1;

	if (NULL == jp)
		return attrs;

	for (const char *p = NULL; NULL != (p = zbx_json_next(jp, p));)
	{
		struct zbx_json_parse	jp_attr;
		zbx_tag_t		tag;
		zbx_vector_tag_t	*pattrs;
		char			*key = NULL, *value = NULL;
		size_t			key_alloc = 0, value_alloc = 0;
		char			buf[ZBX_MAX_UINT64_LEN];

		if (SUCCEED != zbx_json_brackets_open(p, &jp_attr))
			goto fail;

		if (SUCCEED != zbx_json_value_by_name(&jp_attr, APM_ATTR_SIGNAL, buf, sizeof(buf), NULL))
			goto fail;

		if (0 == strcmp(buf, APM_SIGNAL_TRACES))
			pattrs = &attrs->traces;
		else if (0 == strcmp(buf, APM_SIGNAL_METRICS))
			pattrs = &attrs->metrics;
		else if (0 == strcmp(buf, APM_SIGNAL_LOGS))
			pattrs = &attrs->logs;
		else
			goto fail;

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_attr, APM_ATTR_KEY, &key, &key_alloc, NULL))
			goto fail;

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_attr, APM_ATTR_VALUE, &value, &value_alloc, NULL))
		{
			zbx_free(key);
			goto fail;
		}

		tag.tag = key;
		tag.value = value;
		zbx_vector_tag_append(pattrs, tag);
	}

	return attrs;
fail:
	apm_config_attrs_free(attrs);

	return NULL;

#undef APM_ATTR_SIGNAL
#undef APM_ATTR_KEY
#undef APM_ATTR_VALUE

#undef APM_SIGNAL_TRACES
#undef APM_SIGNAL_METRICS
#undef APM_SIGNAL_LOGS
}

void	apm_config_attrs_release(zbx_apm_config_attrs_t *attrs)
{
	if (1 != atomic_fetch_sub(&attrs->refcount, 1))
		return;

	apm_config_attrs_free(attrs);
}

zbx_apm_config_attrs_t	*apm_config_attrs_acquire(zbx_apm_config_attrs_t *attrs)
{
	atomic_fetch_add(&attrs->refcount, 1);
	return attrs;
}

void	apm_config_init(zbx_apm_config_t *cfg)
{
	cfg->attrs = apm_config_attrs_create(NULL);
	cfg->status = 1;
	cfg->quota = 0;
}

void	apm_config_reset(zbx_apm_config_t *cfg)
{
	apm_config_attrs_release(cfg->attrs);
	apm_config_init(cfg);
}

int	apm_config_set(zbx_apm_config_t *cfg, char *apm_config, zbx_uint64_t revision)
{
#define APM_STATUS		"data_collection_status"
#define APM_QUOTA		"max_messages_per_second"
#define APM_ATTRIBUTES	"additional_resource_attributes"

	char			buf[ZBX_MAX_UINT64_LEN];
	struct zbx_json_parse	jp, jp_attrs;
	zbx_apm_config_attrs_t	*attrs;

	if (cfg->revision == revision)
		return SUCCEED;

	if (NULL == apm_config)
		return FAIL;

	if (0 == cfg->revision)
		apm_config_init(cfg);

	if (SUCCEED != zbx_json_open(apm_config, &jp))
		return FAIL;

	if (SUCCEED != zbx_json_value_by_name(&jp, APM_STATUS, buf, sizeof(buf), NULL) ||
			SUCCEED != zbx_is_uint31(buf, &cfg->status))
	{
		return FAIL;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, APM_QUOTA, buf, sizeof(buf), NULL) ||
			SUCCEED != zbx_is_uint64(buf, &cfg->quota))
	{
		return FAIL;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp, APM_ATTRIBUTES, &jp_attrs))
		return FAIL;

	if (NULL == (attrs = apm_config_attrs_create(&jp_attrs)))
		return FAIL;

	apm_config_attrs_release(cfg->attrs);
	cfg->attrs = attrs;
	cfg->revision = revision;

	return SUCCEED;

#undef APM_STATUS
#undef APM_QUOTA
#undef APM_ATTRIBUTES
}

void	apm_config_clear(zbx_apm_config_t *cfg)
{
	if (0 != cfg->revision)
		apm_config_attrs_release(cfg->attrs);
}

