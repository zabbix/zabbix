/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


#ifndef PROMETHEUS_TEST_H
#define PROMETHEUS_TEST_H

typedef struct
{
	char	*key;
	char	*pattern;
	char	*op;
}
zbx_prometheus_condition_test_t;

int	zbx_prometheus_filter_parse(const char *data, zbx_prometheus_condition_test_t **metric,
		zbx_vector_ptr_t *labels, zbx_prometheus_condition_test_t **value, char **error);

int	zbx_prometheus_row_parse(const char *data, char **metric, zbx_vector_ptr_pair_t *labels, char **value,
		zbx_strloc_t *loc, char **error);

void	zbx_prometheus_condition_test_free(zbx_prometheus_condition_test_t *condition);

#endif
