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

#define		MAX_ROW_NUM	64

extern char	*curr_case_name;

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

int	get_db_rows(const char *case_name, char const *data_source, DB_ROW *rows)
{
	int	row_num = -1, c, d, r;

	for (c = 0; c < case_num; c++)
	{
		if (0 != strcmp(cases[c].case_name, case_name))
			continue;

		for (d = 0; d < cases[c].datasource_num; d++)
		{
			if (0 != strcmp(cases[c].datasources[d].source_name, data_source))
				continue;

			row_num = cases[c].datasources[d].row_num;
			for (r = 0; r < row_num; r++)
				rows[r] = cases[c].datasources[d].rows[r].values;
		}
	}

	return row_num;
}

void	__wrap_DBfree_result(DB_RESULT result)
{
	if (NULL == result)
		return;

	zbx_free(result);
}

DB_RESULT __wrap_zbx_db_vselect(const char *fmt, va_list args)
{
	DB_RESULT	result = NULL;
	char		*sql = NULL, *data_source = NULL, *case_name;

	sql = zbx_dvsprintf(sql, fmt, args);
	case_name = curr_case_name;

	if (NULL != (data_source = generate_data_source(sql)))
	{
		result = zbx_malloc(NULL, sizeof(struct zbx_db_result));
		result->rows = (DB_ROW *)zbx_malloc(NULL, sizeof(DB_ROW **) * MAX_ROW_NUM);
		result->data_source = data_source;
		result->case_name = case_name;
		result->sql = sql;
		result->cur_row_idx = 0;
		result->rows_num = get_db_rows(case_name, data_source, result->rows);
	}
	else
		fail_msg("Cannot generate data source from sql!\nSQL:%s", sql);

	if (0 == result->rows_num)
	{
		zbx_free(sql);
		zbx_free(data_source);
		zbx_free(result);
	}

	return result;
}

DB_ROW __wrap_zbx_db_fetch(DB_RESULT result)
{
	DB_ROW	row = NULL;

	if (NULL == result)
		goto out;

	if (0 > result->rows_num)
	{
		fail_msg("Cannot find test suite for \"%s\" data source!\nSQL:%s\n",
				result->data_source, result->sql);
		goto out;
	}

	row = result->rows[result->cur_row_idx];
	result->cur_row_idx++;
out:
	return row;
}

int	__wrap___zbx_DBexecute(const char *fmt, ...)
{
	ZBX_UNUSED(fmt);

	return 0;
}

void	__wrap_DBbegin(void)
{
}

void	__wrap_DBcommit(void)
{
}

int	__wrap_DBexecute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids)
{
	ZBX_UNUSED(query);
	ZBX_UNUSED(field_name);
	ZBX_UNUSED(ids);

	return SUCCEED;
}
