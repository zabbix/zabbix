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

#include "zbxmocktest.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"
#include "../../../src/libs/zbxtelemetry/telemetry_query_sql.c"

static void	check_escape_string_unquoted(tq_sql_ctx_t *ctx)
{
	char	*str_esc = tq_sql_dyn_escape_string_unquoted(zbx_mock_get_parameter_string("in.str"), ctx);

	zbx_mock_assert_str_eq("tq_sql_dyn_escape_string_unquoted result",
			zbx_mock_get_parameter_string("out.string_literal"), str_esc);

	zbx_free(str_esc);
}

static void	check_escape_string(tq_sql_ctx_t *ctx)
{
	char	*str_esc = tq_sql_dyn_escape_string(zbx_mock_get_parameter_string("in.str"), ctx);
	char	*expected = zbx_dsprintf(NULL, "'%s'", zbx_mock_get_parameter_string("out.string_literal"));

	zbx_mock_assert_str_eq("tq_sql_dyn_escape_string result", expected, str_esc);

	zbx_free(expected);
	zbx_free(str_esc);
}

static void	check_escape_name(tq_sql_ctx_t *ctx)
{
	char	*str_esc = tq_sql_dyn_escape_name(zbx_mock_get_parameter_string("in.str"), ctx);
	char	*expected = zbx_dsprintf(NULL, "\"%s\"", zbx_mock_get_parameter_string("out.name"));

	zbx_mock_assert_str_eq("tq_sql_dyn_escape_name result", expected, str_esc);

	zbx_free(expected);
	zbx_free(str_esc);
}

static void	check_escape_like_pattern(tq_sql_ctx_t *ctx)
{
	char	*str_esc = tq_sql_dyn_escape_like_pattern(zbx_mock_get_parameter_string("in.str"), ctx);

	zbx_mock_assert_str_eq("tq_sql_dyn_escape_like_pattern result",
			zbx_mock_get_parameter_string("out.like_pattern"), str_esc);

	zbx_free(str_esc);
}

void	zbx_mock_test_entry(void **state)
{
	tq_sql_ctx_t	ctx = {
		.db_type = ZBX_APM_DB_TYPE_CLICKHOUSE,
		.db = NULL,
	};

	ZBX_UNUSED(state);

	check_escape_string_unquoted(&ctx);
	check_escape_string(&ctx);
	check_escape_name(&ctx);
	check_escape_like_pattern(&ctx);
}
