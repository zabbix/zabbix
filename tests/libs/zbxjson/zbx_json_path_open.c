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
	const char		*path, *next = NULL, *component_class, *component_value;
	zbx_mock_error_t	err;
	zbx_strloc_t		loc;
	int			type, ret;
	char			*buffer;

	ZBX_UNUSED(state);

	zbx_mock_get_parameter_string("in.path", &path);
	zbx_mock_get_parameter_handle("out.components", &components);

	buffer = zbx_malloc(NULL, strlen(path) + 1);

	do
	{
		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_vector_element(components, &component)))
			fail_msg("Too many path components parsed");

		zbx_mock_get_object_member_string(component, "class", &component_class);

		if (SUCCEED != (ret = zbx_jsonpath_next(path, &next, &loc, &type)))
		{
			zbx_mock_assert_streq("Return value", component_class, "fail");
			break;
		}
		else
			zbx_mock_assert_strne("Return value", component_class, "fail");

		switch (type)
		{
			case 0: /* ZBX_JSONPATH_COMPONENT_DOT */
				zbx_mock_assert_streq("Component class", component_class, "dot");
				break;
			case 1: /* ZBX_JSONPATH_COMPONENT_BRACKET */
				zbx_mock_assert_streq("Component class", component_class, "bracket");
				break;

			case 2: /* ZBX_JSONPATH_ARRAY_INDEX */
				zbx_mock_assert_streq("Component class", component_class, "index");
				break;
		}

		zbx_strlcpy(buffer, path + loc.l, loc.r - loc.l + 2);

		zbx_mock_get_object_member_string(component, "value", &component_value);
		zbx_mock_assert_streq("Component value", component_value, buffer);
	}
	while ('\0' != *next);

	if (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(components, &component))
		fail_msg("Not enough path components parsed");

	zbx_free(buffer);
}

