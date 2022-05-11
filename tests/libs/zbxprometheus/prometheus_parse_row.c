/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "zbxalgo.h"
#include "prometheus_test.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*data;
	char			*metric = NULL, *value = NULL;
	zbx_vector_ptr_pair_t	labels;
	int			ret, expected_ret, index, i;
	char			*error = NULL;
	zbx_mock_handle_t	hlabels, hlabel;
	zbx_mock_error_t	mock_ret;
	zbx_strloc_t		loc;
	char			buffer[ZBX_MAX_UINT64_LEN + 1];

	ZBX_UNUSED(state);

	zbx_vector_ptr_pair_create(&labels);

	data = zbx_mock_get_parameter_string("in.data");

	ret = zbx_prometheus_row_parse(data, &metric, &labels, &value, &loc, &error);
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("prometheus row parsing", expected_ret, ret);

	if (SUCCEED == ret)
	{
		zbx_mock_assert_str_eq("", zbx_mock_get_parameter_string("out.metric"), metric);
		zbx_mock_assert_str_eq("", zbx_mock_get_parameter_string("out.value"), value);

		if (ZBX_MOCK_SUCCESS != zbx_mock_parameter("out.labels", &hlabels))
			hlabels = -1;

		if (-1 != hlabels && 0 == labels.values_num)
			fail_msg("expected to parse metric labels");

		if (-1 == hlabels && 0 != labels.values_num)
			fail_msg("did not expect to parse metric labels");

		if (-1 != hlabels)
		{
			index = 0;
			while (ZBX_MOCK_END_OF_VECTOR != (mock_ret = zbx_mock_vector_element(hlabels, &hlabel)) &&
					index < labels.values_num)
			{
				zbx_ptr_pair_t	*pair = &labels.values[index];

				zbx_snprintf(buffer, sizeof(buffer), "%d. ", index);

				zbx_mock_assert_str_eq(buffer, zbx_mock_get_object_member_string(hlabel, "name"),
						pair->first);
				zbx_mock_assert_str_eq(buffer, zbx_mock_get_object_member_string(hlabel, "value"),
						pair->second);
				index++;
			}

			if (ZBX_MOCK_END_OF_VECTOR != mock_ret)
				fail_msg("expected more than %d metric labels", index);

			if (index != labels.values_num)
				fail_msg("got more than the expected %d metric labels", index);
		}

		zbx_mock_assert_str_eq("next row", zbx_mock_get_parameter_string("out.next"), data + loc.r + 1);
	}
	else
		zbx_mock_assert_ptr_ne("error message", NULL, error);

	zbx_free(metric);
	zbx_free(value);

	for (i = 0; i < labels.values_num; i++)
	{
		zbx_free(labels.values[i].first);
		zbx_free(labels.values[i].second);
	}
	zbx_vector_ptr_pair_destroy(&labels);

	zbx_free(error);
}

