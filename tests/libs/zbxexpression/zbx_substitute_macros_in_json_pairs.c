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

#include "zbxcommon.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxmockdb.h"

#include "zbxexpression.h"
#include "zbxcacheconfig.h"

#include "lld_common.h"

void	zbx_mock_test_entry(void **state)
{
	char				*error = NULL,
					*data = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.data"));
	const char			*expected_data = zbx_mock_get_parameter_string("out.data"),
					*buffer = zbx_mock_get_parameter_string("in.buffer");
	zbx_vector_lld_macro_path_ptr_t	macros;
	struct zbx_json_parse		parse;
	int				max_error_len = MAX_STRING_LEN;

	ZBX_UNUSED(state);

	if (SUCCEED != zbx_json_open(buffer, &parse))
		fail_msg("failed json open: %s\n", zbx_json_strerror());

	zbx_vector_lld_macro_path_ptr_create(&macros);
	get_macros("in.macros", &macros);

	int	result = zbx_substitute_macros_in_json_pairs(&data, &parse, &macros, error, max_error_len);

	zbx_mock_assert_result_eq("return value", result, SUCCEED);
	zbx_mock_assert_str_eq("data", expected_data, data);

	zbx_vector_lld_macro_path_ptr_clear_ext(&macros, zbx_lld_macro_path_free);
	zbx_vector_lld_macro_path_ptr_destroy(&macros);

	zbx_free(data);
}
