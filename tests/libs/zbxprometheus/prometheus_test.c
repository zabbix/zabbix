/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

typedef struct
{
	char	*key;
	char	*pattern;
	char	*op;
}
zbx_prometheus_condition_test_t;


static zbx_prometheus_condition_test_t	*prometheus_condition_dup(zbx_prometheus_condition_t *condition)
{
	zbx_prometheus_condition_test_t	*test_condition;

	if (NULL == condition)
		return NULL;

	test_condition = (zbx_prometheus_condition_test_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_condition_test_t));
	memset(test_condition, 0, sizeof(zbx_prometheus_condition_test_t));
	if (NULL != condition->key)
		test_condition->key = zbx_strdup(NULL, condition->key);
	if (NULL != condition->pattern)
		test_condition->pattern = zbx_strdup(NULL, condition->pattern);

	switch (condition->op)
	{
		case ZBX_PROMETHEUS_CONDITION_OP_EQUAL:
			test_condition->op = zbx_strdup(NULL, "=");
			break;
		case ZBX_PROMETHEUS_CONDITION_OP_REGEX:
			test_condition->op = zbx_strdup(NULL, "=~");
			break;
		case ZBX_PROMETHEUS_CONDITION_OP_EQUAL_VALUE:
			test_condition->op = zbx_strdup(NULL, "==");
			break;
	}

	return test_condition;
}

int	zbx_prometheus_filter_parse(const char *data, zbx_prometheus_condition_test_t **metric,
		zbx_vector_ptr_t *labels, zbx_prometheus_condition_test_t **value, char **error)
{
	zbx_prometheus_filter_t	filter;
	int			i;

	if (FAIL == prometheus_filter_init(&filter, data, error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to parse prometheus filter: %s", *error);
		return FAIL;
	}

	*metric = prometheus_condition_dup(filter.metric);
	*value = prometheus_condition_dup(filter.value);

	for (i = 0; i < filter.labels.values_num; i++)
	{
		zbx_vector_ptr_append(labels, prometheus_condition_dup(filter.labels.values[i]));
	}

	prometheus_filter_clear(&filter);

	return SUCCEED;
}

void	zbx_prometheus_condition_test_free(zbx_prometheus_condition_test_t *condition)
{
	zbx_free(condition->key);
	zbx_free(condition->op);
	zbx_free(condition->pattern);
	zbx_free(condition);
}

