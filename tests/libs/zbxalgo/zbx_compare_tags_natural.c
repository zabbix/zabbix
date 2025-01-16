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

#include "zbxalgo.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_tag_t	tag1, tag2;
	char		*in_tag1 = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.tag1"));
	char		*in_tag2 = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.tag2"));
	char		*in_value1 = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.value1"));
	char		*in_value2 = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.value2"));
	int		exp_result = atoi(zbx_mock_get_parameter_string("out.result"));

	ZBX_UNUSED(state);

	tag1.tag = in_tag1;
	tag1.value = in_value1;
	tag2.tag = in_tag2;
	tag2.value = in_value2;

	zbx_tag_t	*p_tag1 = &tag1;
	zbx_tag_t	*p_tag2 = &tag2;

	int		result = zbx_compare_tags_natural(&p_tag1, &p_tag2);

	zbx_mock_assert_int_eq("return value:", result, exp_result);

	zbx_free(in_tag1);
	zbx_free(in_value1);
	zbx_free(in_tag2);
	zbx_free(in_value2);
}
