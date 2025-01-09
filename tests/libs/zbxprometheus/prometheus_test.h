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


#ifndef PROMETHEUS_TEST_H
#define PROMETHEUS_TEST_H

#include "zbxalgo.h"
#include "zbxexpr.h"

typedef struct
{
	char	*key;
	char	*pattern;
	char	*op;
}
zbx_prometheus_condition_test_t;

ZBX_PTR_VECTOR_DECL(prometheus_condition_test, zbx_prometheus_condition_test_t *)

int	zbx_prometheus_filter_parse(const char *data, zbx_prometheus_condition_test_t **metric,
		zbx_vector_prometheus_condition_test_t *labels, zbx_prometheus_condition_test_t **value, char **error);

int	zbx_prometheus_row_parse(const char *data, char **metric, zbx_vector_ptr_pair_t *labels, char **value,
		zbx_strloc_t *loc, char **error);

void	zbx_prometheus_condition_test_free(zbx_prometheus_condition_test_t *condition);

#endif
