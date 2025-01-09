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

#include "zbxcommon.h"

#include "zbxexpression.h"
#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxeval.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_item_query_t	query;
	const char		*itemquery, *host, *key, *filter;
	size_t			len;

	ZBX_UNUSED(state);

	itemquery = zbx_mock_get_parameter_string("in.query");
	len = zbx_eval_parse_query(itemquery, strlen(itemquery), &query);


	key = zbx_mock_get_parameter_string("out.key");

	if (0 == len)
	{
		if ('\0' != *key)
			fail_msg("failed to parse query");
	}
	else
	{
		zbx_mock_assert_uint64_eq("returned value", strlen(itemquery), len);
		host = zbx_mock_get_parameter_string("out.host");
		filter = zbx_mock_get_parameter_string("out.filter");

		zbx_mock_assert_str_eq("key", key, query.key);

		if (NULL == query.host)
		{
			if ('\0' != *host)
				fail_msg("expected host value '%s' while got null", host);
		}
		else
			zbx_mock_assert_str_eq("host", host, query.host);

		if (NULL == query.filter)
		{
			if ('\0' != *filter)
				fail_msg("expected filter value '%s' while got null", filter);
		}
		else
			zbx_mock_assert_str_eq("filter", filter, query.filter);

		zbx_eval_clear_query(&query);
	}
}
