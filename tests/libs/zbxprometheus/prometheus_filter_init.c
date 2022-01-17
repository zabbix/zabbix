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


static void	test_match(const char *prefix, zbx_mock_handle_t hmatch, zbx_prometheus_condition_test_t *match)
{
	char			buffer[MAX_STRING_LEN];
	zbx_mock_handle_t	hkey;

	if (-1 != hmatch && NULL == match)
		fail_msg("expected to parse %s filter", prefix);

	if (-1 == hmatch && NULL != match)
		fail_msg("did not expect to parse %s filter", prefix);

	if (-1 == hmatch)
		return;

	zbx_snprintf(buffer, sizeof(buffer), "%s filter key", prefix);

	if (ZBX_MOCK_SUCCESS != zbx_mock_object_member(hmatch, "key", &hkey))
		hkey = -1;

	if (-1 != hkey && NULL == match->key)
		fail_msg("expected to parse %s", buffer);

	if (-1 == hkey && NULL != match->key)
		fail_msg("did not expect to parse %s", buffer);

	if (-1 != hkey)
	{
		const char	*key;

		zbx_mock_string(hkey, &key);
		zbx_mock_assert_str_eq(buffer, key, match->key);
	}

	zbx_snprintf(buffer, sizeof(buffer), "%s filter pattern", prefix);
	zbx_mock_assert_str_eq(buffer, zbx_mock_get_object_member_string(hmatch, "pattern"), match->pattern);
	zbx_snprintf(buffer, sizeof(buffer), "%s filter operation", prefix);
	zbx_mock_assert_str_eq(buffer, zbx_mock_get_object_member_string(hmatch, "op"), match->op);
}

void	zbx_mock_test_entry(void **state)
{
	const char			*filter;
	zbx_prometheus_condition_test_t	*metric = NULL, *value = NULL;
	zbx_vector_ptr_t		labels;
	int				ret, expected_ret, index;
	char				*error = NULL;
	zbx_mock_handle_t		hmetric, hvalue, hlabels, hlabel;
	zbx_mock_error_t		mock_ret;

	ZBX_UNUSED(state);

	zbx_vector_ptr_create(&labels);

	filter = zbx_mock_get_parameter_string("in.filter");

	if (SUCCEED != (ret = zbx_prometheus_filter_parse(filter, &metric, &labels, &value, &error)))
		printf("filter parsing error: %s\n", error);

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("prometheus filter parsing", expected_ret, ret);

	if (SUCCEED == ret)
	{
		if (ZBX_MOCK_SUCCESS != zbx_mock_parameter("out.metric", &hmetric))
			hmetric = -1;
		test_match("metric", hmetric, metric);

		if (ZBX_MOCK_SUCCESS != zbx_mock_parameter("out.value", &hvalue))
			hvalue = -1;
		test_match("value", hvalue, value);

		if (ZBX_MOCK_SUCCESS != zbx_mock_parameter("out.labels", &hlabels))
			hlabels = -1;

		if (-1 != hlabels && 0 == labels.values_num)
			fail_msg("expected to parse label filters");

		if (-1 == hlabels && 0 != labels.values_num)
			fail_msg("did not expect to parse label filters");

		if (-1 != hlabels)
		{
			index = 0;
			while (ZBX_MOCK_END_OF_VECTOR != (mock_ret = zbx_mock_vector_element(hlabels, &hlabel)) &&
					index < labels.values_num)
			{
				test_match("label", hlabel, labels.values[index]);
				index++;
			}

			if (ZBX_MOCK_END_OF_VECTOR != mock_ret)
				fail_msg("expected more than %d filter labels", index);

			if (index != labels.values_num)
				fail_msg("got more than the expected %d filter labels", index);
		}
	}

	if (NULL != metric)
		zbx_prometheus_condition_test_free(metric);
	if (NULL != value)
		zbx_prometheus_condition_test_free(value);

	zbx_vector_ptr_clear_ext(&labels, (zbx_clean_func_t)zbx_prometheus_condition_test_free);
	zbx_vector_ptr_destroy(&labels);

	zbx_free(error);
}

