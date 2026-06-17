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
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "httpmacro.h"
#include "zbxalgo.h"
#include "zbxstr.h"

static const char	*get_macro_value(const zbx_httptest_t *ht, const char *key)
{
	int	i;

	for (i = 0; i < ht->macros.values_num; i++)
	{
		if (0 == strcmp((char *)ht->macros.values[i].first, key))
			return (const char *)ht->macros.values[i].second;
	}

	return NULL;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_httptest_t		ht;
	zbx_vector_ptr_pair_t	vars;
	zbx_ptr_pair_t		pair;
	const char		*key, *val, *data;
	int			expected_ret, ret;

	ZBX_UNUSED(state);

	key = zbx_mock_get_parameter_string("in.key");
	val = zbx_mock_get_parameter_string("in.value");
	data = (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.data"))
			? zbx_mock_get_parameter_string("in.data") : NULL;
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
#ifndef HAVE_LIBXML2
	if (NULL != strstr(val, "xmlxpath:") && NULL != data && SUCCEED == expected_ret)
		skip();
#endif

	memset(&ht, 0, sizeof(ht));
	zbx_vector_ptr_pair_create(&ht.macros);
	zbx_vector_ptr_pair_create(&ht.variables);

	pair.first = zbx_strdup(NULL, key);
	pair.second = zbx_strdup(NULL, val);
	zbx_vector_ptr_pair_create(&vars);
	zbx_vector_ptr_pair_append(&vars, pair);

	ret = http_process_variables(&ht, &vars, data, NULL);

	zbx_free(vars.values[0].first);
	zbx_free(vars.values[0].second);
	zbx_vector_ptr_pair_destroy(&vars);

	zbx_mock_assert_result_eq("http_process_variables() return value", expected_ret, ret);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.macro_value"))
	{
		zbx_mock_assert_str_eq("macro value in cache",
				zbx_mock_get_parameter_string("out.macro_value"),
				get_macro_value(&ht, key));
	}

	for (int i = 0; i < ht.macros.values_num; i++)
	{
		zbx_free(ht.macros.values[i].first);
		zbx_free(ht.macros.values[i].second);
	}

	zbx_vector_ptr_pair_destroy(&ht.macros);
	zbx_vector_ptr_pair_destroy(&ht.variables);
}
