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
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxexpr.h"
#include "zbxalgo.h"

typedef struct
{
	const char	*name;
	const char	*value;
}
str_pair_t;

ZBX_PTR_VECTOR_DECL(str_pair, str_pair_t)
ZBX_PTR_VECTOR_IMPL(str_pair, str_pair_t)

static zbx_vector_str_pair_t	resolve_vector;
static int			resolve_return = SUCCEED;

static int	macro_resolv_func(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data, char *error,
		size_t maxerrlen)
{
	int	index = 0, resolved = FAIL;

	ZBX_UNUSED(args);
	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	zabbix_log(LOG_LEVEL_INFORMATION, "In %s() macro:%s indexed:%d index:%d", __func__, p->macro, p->indexed,
			p->index);

	for (int i = 0; i < resolve_vector.values_num; i++)
	{
		const char	*value = resolve_vector.values[i].value;

		if (0 == strcmp(p->macro, resolve_vector.values[i].name))
		{
			if (p->indexed)
			{
				index++;
				if (index != p->index) continue;

				if (p->raw_value)
				{
					// Remove @ at the beginning
					if ('@' == value[0])
					{
						value = &value[1];
					}
				}
				resolved = SUCCEED;
			}

			*replace_to = zbx_strdup(*replace_to, value);
		}
	}

	if (p->indexed)
		resolve_return = resolved;

	zabbix_log(LOG_LEVEL_INFORMATION, "End of %s() ret:%d", __func__, resolve_return);

	return resolve_return;
}

void	zbx_mock_test_entry(void **state)
{
	const char	*expression = NULL;
	char		*result = NULL, error[MAX_BUFFER_LEN] = {0};
	int		ret, expected_ret;
	zbx_mock_handle_t	resolve_handle;

	ZBX_UNUSED(state);

	zbx_vector_str_pair_create(&resolve_vector);

	expression = zbx_mock_get_parameter_string("in.expression");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.resolve", &resolve_handle))
	{
		zbx_mock_handle_t	resolve_data;

		resolve_return = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("in.resolve.return"));

		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.resolve.data", &resolve_data))
		{
			zbx_mock_handle_t	element_handle;

			while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(resolve_data, &element_handle))
			{
				str_pair_t	name_value;

				name_value.name = zbx_mock_get_object_member_string(element_handle, "name");
				name_value.value = zbx_mock_get_object_member_string(element_handle, "value");

				zbx_vector_str_pair_append(&resolve_vector, name_value);
			}
		}
	}

	result = strdup(expression);

	ret = zbx_substitute_macros(&result, error, sizeof(error), macro_resolv_func);

	zbx_mock_assert_result_eq("zbx_substitute_macros() return code", expected_ret, ret);

	if (SUCCEED == ret)
	{
		const char	*expected_result = zbx_mock_get_parameter_string("out.result");

		zbx_mock_assert_str_eq("zbx_substitute_macros() result", expected_result, result);
	}
	else
	{
		const char	*expected_error = zbx_mock_get_parameter_string("out.error");

		zbx_mock_assert_str_eq("zbx_substitute_macros() expected error", expected_error, error);
	}

	zbx_free(result);

	zbx_vector_str_pair_destroy(&resolve_vector);
}
