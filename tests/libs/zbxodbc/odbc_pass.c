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
#include "zbxmockutil.h"
#include "zbxmockassert.h"
#include "../../../src/libs/zbxodbc/odbc.c"

#define CONNECTION_SIZE	1024

void	zbx_mock_test_entry(void **state)
{
	char	value[MAX_STRING_LEN], *connection = NULL;

	ZBX_UNUSED(state);

	zbx_snprintf(value, MAX_STRING_LEN, "%s", zbx_mock_get_parameter_string("in.pwd"));
	connection = zbx_malloc(value, CONNECTION_SIZE);
	zbx_snprintf(connection, MAX_STRING_LEN, "%s", zbx_mock_get_parameter_string("in.connection"));
	zbx_odbc_connection_pwd_append(&connection, value);
	zbx_mock_assert_str_eq("odbc_pass result", zbx_mock_get_parameter_string("out.value"), connection);
	zbx_free(connection);
}
