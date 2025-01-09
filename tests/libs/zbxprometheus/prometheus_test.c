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

#include "prometheus_test.h"

#include "zbxalgo.h"

ZBX_PTR_VECTOR_IMPL(prometheus_condition_test, zbx_prometheus_condition_test_t *)

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
		case ZBX_PROMETHEUS_CONDITION_OP_NOT_EQUAL:
			test_condition->op = zbx_strdup(NULL, "!=");
			break;
		case ZBX_PROMETHEUS_CONDITION_OP_REGEX_NOT_MATCHED:
			test_condition->op = zbx_strdup(NULL, "!~");
			break;
	}

	return test_condition;
}

int	zbx_prometheus_filter_parse(const char *data, zbx_prometheus_condition_test_t **metric,
		zbx_vector_prometheus_condition_test_t *labels, zbx_prometheus_condition_test_t **value, char **error)
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
		zbx_vector_prometheus_condition_test_append(labels, prometheus_condition_dup(filter.labels.values[i]));

	prometheus_filter_clear(&filter);

	return SUCCEED;
}

int	zbx_prometheus_row_parse(const char *data, char **metric, zbx_vector_ptr_pair_t *labels, char **value,
		zbx_strloc_t *loc, char **error)
{
	zbx_prometheus_filter_t	filter;
	int			i;
	zbx_prometheus_row_t	*prow;

	if (FAIL == prometheus_filter_init(&filter, "", error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to parse prometheus filter: %s", *error);
		return FAIL;
	}

	if (FAIL == prometheus_parse_row(&filter, data, 0, &prow, loc, error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to parse prometheus row: %s", *error);
		return FAIL;
	}

	*metric = prow->metric;
	prow->metric = NULL;
	*value = prow->value;
	prow->value = NULL;

	for (i = 0; i < prow->labels.values_num; i++)
	{
		zbx_prometheus_label_t	*label = prow->labels.values[i];
		zbx_ptr_pair_t		pair = {label->name, label->value};

		zbx_vector_ptr_pair_append_ptr(labels, &pair);
	}

	/* free only label structure not internals - they're used */
	zbx_vector_prometheus_label_clear_ext(&prow->labels, (zbx_prometheus_label_free_func_t)zbx_ptr_free);
	prometheus_row_free(prow);

	return SUCCEED;
}

void	zbx_prometheus_condition_test_free(zbx_prometheus_condition_test_t *condition)
{
	zbx_free(condition->key);
	zbx_free(condition->op);
	zbx_free(condition->pattern);
	zbx_free(condition);
}

