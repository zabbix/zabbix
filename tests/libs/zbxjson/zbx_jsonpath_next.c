/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "zbxjson.h"

/* 'internal' jsonpath support function prototype */
int	zbx_jsonpath_next(const char *path, const char **pnext, zbx_strloc_t *loc, int *type);

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_handle_t	components, component;
	const char		*path, *next = NULL, *component_class, *component_value, *result;
	zbx_mock_error_t	err;
	zbx_strloc_t		loc;
	int			type, ret;
	char			*buffer;

	ZBX_UNUSED(state);

	path = zbx_mock_get_parameter_string("in.path");
	result = zbx_mock_get_parameter_string("out.result");
	components = zbx_mock_get_parameter_handle("out.components");

	buffer = zbx_malloc(NULL, strlen(path) + 1);

	while (1)
	{
		if (SUCCEED != (ret = zbx_jsonpath_next(path, &next, &loc, &type)))
		{
			zbx_mock_assert_str_eq("Return value", result, "fail");
			break;
		}

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_vector_element(components, &component)))
		{
			if (ZBX_MOCK_END_OF_VECTOR == err || ZBX_MOCK_NOT_A_VECTOR == err)
				fail_msg("Too many path components parsed");
			else
				fail_msg("Cannot get vector element: %s", zbx_mock_error_string(err));
		}

		component_class = zbx_mock_get_object_member_string(component, "class");

		switch (type)
		{
			case 0: /* ZBX_JSONPATH_COMPONENT_DOT */
				zbx_mock_assert_str_eq("Component class", component_class, "dot");
				break;
			case 1: /* ZBX_JSONPATH_COMPONENT_BRACKET */
				zbx_mock_assert_str_eq("Component class", component_class, "bracket");
				break;
			case 2: /* ZBX_JSONPATH_ARRAY_INDEX */
				zbx_mock_assert_str_eq("Component class", component_class, "index");
				break;
		}

		zbx_strlcpy(buffer, path + loc.l, loc.r - loc.l + 2);

		component_value = zbx_mock_get_object_member_string(component, "value");
		zbx_mock_assert_str_eq("Component value", component_value, buffer);

		if ('\0' == *next)
		{
			zbx_mock_assert_str_eq("Return value", result, "succeed");
			break;
		}
	}

	if (ZBX_MOCK_SUCCESS == (err = zbx_mock_vector_element(components, &component)))
		fail_msg("Too many path components parsed");

	if (ZBX_MOCK_END_OF_VECTOR != err && ZBX_MOCK_NOT_A_VECTOR != err)
		fail_msg("Cannot get vector element: %s", zbx_mock_error_string(err));

	zbx_free(buffer);
}

