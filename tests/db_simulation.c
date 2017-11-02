/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "zbxtests.h"

extern zbx_test_case_t		*test_case;

char	*generate_data_source(char *sql)
{
	int	found = 0;
	char	*data_source = NULL, *ptr_sql = sql, *ptr_ds, *ptr_tmp;

	data_source = zbx_calloc(NULL, 64, sizeof(char *));
	ptr_ds = data_source;

	while ('\0' != *ptr_sql)
	{
		if (1 == found)
		{
			if (' ' == *ptr_sql || ';' == *ptr_sql || '\0' == *ptr_sql)
			{
				found = 0;
				*(ptr_ds++) = '_';
				continue;
			}
			else
			{
				*ptr_ds = *ptr_sql;
				ptr_ds++;
			}
			ptr_sql++;
		}
		else if (NULL != (ptr_tmp = strstr(ptr_sql, "from "))
				|| NULL != (ptr_tmp = strstr(ptr_sql, "join ")))
		{
			ptr_sql = ptr_tmp + 5;
			found = 1;
		}
		else
		{
			break;
		}
	}

	if (ptr_ds == data_source)
		zbx_free(data_source);		/* cannot generate data_source */
	else
		*(ptr_ds - 1) = '\0';

	return data_source;
}

DB_RESULT __wrap_zbx_db_vselect(const char *fmt, va_list args)
{
	DB_RESULT		result = NULL;
	char			*sql = NULL, *data_source = NULL;
	int			i, r;

	sql = zbx_dvsprintf(sql, fmt, args);

	if (NULL == (data_source = generate_data_source(sql)))
		fail_msg("Cannot generate data source from sql!\nSQL:%s", sql);

	for (i = 0; i < test_case->datasource_num; i++)
	{
		if (0 == strcmp(test_case->datasources[i].source_name, data_source))
			break;
	}

	if (i > test_case->datasource_num && 0 < test_case->datasources[i].row_num)
	{
		result = zbx_malloc(NULL, sizeof(struct zbx_db_result));
		result->rows = (DB_ROW *)zbx_malloc(NULL, sizeof(DB_ROW **) * TEST_MAX_ROW_NUM);
		result->data_source = data_source;
		result->sql = sql;
		result->cur_row_idx = 0;
		result->rows_num = test_case->datasources[i].row_num;

		for (r = 0; r < result->rows_num ; r++)
			result->rows[r] = test_case->datasources[i].rows[r].values;
	}
	else
	{
		zbx_free(sql);
		zbx_free(data_source);
	}

	return result;
}

DB_ROW __wrap_zbx_db_fetch(DB_RESULT result)
{
	DB_ROW			row = NULL;

	if (NULL == result)
		return NULL;

	if (0 > result->rows_num)
	{
		fail_msg("Cannot find test suite for \"%s\" data source!\nSQL:%s\n",
			result->data_source, result->sql);
	}

	row = result->rows[result->cur_row_idx];
	result->cur_row_idx++;

	return row;
}

void	__wrap_DBfree_result(DB_RESULT result)
{
	zbx_free(result);
}

int	__wrap___zbx_DBexecute(const char *fmt, ...)
{
	ZBX_UNUSED(fmt);

	return 0;
}

int	__wrap_DBexecute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids)
{
	ZBX_UNUSED(query);
	ZBX_UNUSED(field_name);
	ZBX_UNUSED(ids);

	return SUCCEED;
}

void	__wrap_DBbegin(void)
{
}

void	__wrap_DBcommit(void)
{
}
