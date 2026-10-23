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

void	zbx_mock_test_entry(void **state)
{
	char		*str_esc;
	tq_sql_ctx_t	ctx = {
		.db_type = ZBX_APM_DB_TYPE_CLICKHOUSE,
		.db = NULL,
	};

	ZBX_UNUSED(state);

	str_esc = tq_sql_dyn_escape_string_unquoted(zbx_mock_get_parameter_string("in.str"), &ctx);
	zbx_mock_assert_str_eq("escaping result", zbx_mock_get_parameter_string("out.value"), str_esc);
	zbx_free(str_esc);
}
