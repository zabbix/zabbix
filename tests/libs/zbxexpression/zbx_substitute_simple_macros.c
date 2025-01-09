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

#include "zbxexpression.h"

#include "zbxmocktest.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

zbx_vector_uint64_t	test_hostids;

int	__wrap_expr_db_get_trigger_value(const zbx_db_trigger *trigger, char **replace_to, int N_functionid,
		int request);

zbx_dc_um_handle_t	*__wrap_zbx_dc_open_user_macros(void);

void	__wrap_zbx_dc_close_user_macros(zbx_dc_um_handle_t *um_handle);
int	__wrap_zbx_db_trigger_get_all_hostids(const zbx_db_trigger *trigger, const zbx_vector_uint64_t **hostids);

void	__wrap_zbx_dc_get_user_macro(const zbx_dc_um_handle_t *um_handle, const char *macro,
		const zbx_uint64_t *hostids, int hostids_num, char **value);

int	__wrap_expr_db_get_trigger_value(const zbx_db_trigger *trigger, char **replace_to, int N_functionid,
		int request)
{
	ZBX_UNUSED(trigger);
	ZBX_UNUSED(N_functionid);
	ZBX_UNUSED(request);

	*replace_to = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.macro_value"));

	return SUCCEED;
}

zbx_dc_um_handle_t	*__wrap_zbx_dc_open_user_macros(void)
{
	return NULL;
}

void	__wrap_zbx_dc_close_user_macros(zbx_dc_um_handle_t *um_handle)
{
	ZBX_UNUSED(um_handle);
}

int	__wrap_zbx_db_trigger_get_all_hostids(const zbx_db_trigger *trigger, const zbx_vector_uint64_t **hostids)
{
	ZBX_UNUSED(trigger);
	*hostids = &test_hostids;

	return SUCCEED;
}

void	__wrap_zbx_dc_get_user_macro(const zbx_dc_um_handle_t *um_handle, const char *macro,
		const zbx_uint64_t *hostids, int hostids_num, char **value)
{
	ZBX_UNUSED(um_handle);
	ZBX_UNUSED(macro);
	ZBX_UNUSED(hostids);
	ZBX_UNUSED(hostids_num);

	*value = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.macro_value"));
}

void	zbx_mock_test_entry(void **state)
{
	int		expected_ret, returned_ret;
	char		*expression;
	const char	*expected_expression;
	zbx_db_event	event;

	ZBX_UNUSED(state);

	zbx_vector_uint64_create(&test_hostids);

	event.source = EVENT_SOURCE_TRIGGERS;
	expression = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.expression"));
	expected_expression = zbx_mock_get_parameter_string("out.expression");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	returned_ret = zbx_substitute_simple_macros(NULL, &event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			"", &expression, ZBX_MACRO_TYPE_MESSAGE_NORMAL, NULL, 0);

	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);
	zbx_mock_assert_str_eq("resulting expression", expected_expression, expression);

	zbx_free(expression);
	zbx_vector_uint64_destroy(&test_hostids);
}
