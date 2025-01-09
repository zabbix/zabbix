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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"

#include "config.h"
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxregexp.h"
#include "zbxdbhigh.h"

void	zbx_mock_test_entry(void **state)
{
#if defined(HAVE_SQLITE3)
#	define RESULT	"out.sqlite_regex"
#else
#	define RESULT	"out.sql_regex"
#endif
	const char		*sql_where, *sql_rgx, *field_name;
	zbx_vector_uint64_t	in_ids;
	int			i, count1, count2, shift, repeat;
	uint64_t		value_id, value;
	char			*sql;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;

	ZBX_UNUSED(state);

	sql_where = zbx_mock_get_parameter_string("in.sql_where");
	field_name = zbx_mock_get_parameter_string("in.field_name");
	value_id = zbx_mock_get_parameter_uint64("in.start_id");
	count1 = atoi(zbx_mock_get_parameter_string("in.count1"));
	count2 = atoi(zbx_mock_get_parameter_string("in.count2"));
	shift = atoi(zbx_mock_get_parameter_string("in.shift"));
	repeat = atoi(zbx_mock_get_parameter_string("in.repeat"));
	sql_rgx = zbx_mock_get_parameter_string(RESULT);
	sql = (char *)zbx_malloc(NULL, sql_alloc);
	zbx_vector_uint64_create(&in_ids);
	value = value_id;

	do
	{
		for (i = 0; i < count1; i++)
			zbx_vector_uint64_append(&in_ids, value++);

		value_id += shift;
		value = value_id;

		for (i = 0; i < count2; i++)
			zbx_vector_uint64_append(&in_ids, value++);

		if (0 != count2)
		{
			value_id += shift;
			value = value_id;
		}
	}
	while (repeat--);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_where);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, field_name, in_ids.values, in_ids.values_num);
	zbx_vector_uint64_destroy(&in_ids);

	if (NULL == zbx_regexp_match(sql, sql_rgx, NULL))
	{
		int	len;

		if (sql_offset > (len = 4 * ZBX_KIBIBYTE) * 2)
		{
			printf("Start of prepared sql (total=%zu): \"%.*s\"\n", sql_offset, len, sql);
			printf("End of prepared sql: \"%s\"\n", &sql[sql_offset - len]);
		}
		else
			printf("Prepared sql (total=%zu): \"%s\"\n", sql_offset, sql);

		zbx_free(sql);
		fail_msg("Regular expression %s=\"%s\" does not much sql", RESULT, sql_rgx);
	}
	else
		zbx_free(sql);
}
