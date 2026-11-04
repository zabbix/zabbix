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
	const char		*key = NULL, *val, *data;
	int			expected_ret, ret;
	zbx_mock_handle_t	hvars, hvar, hmacro_values, hmv;
	zbx_mock_error_t	err;

	ZBX_UNUSED(state);

	data = (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.data"))
			? zbx_mock_get_parameter_string("in.data") : NULL;
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	memset(&ht, 0, sizeof(ht));
	zbx_vector_ptr_pair_create(&ht.macros);
	zbx_vector_ptr_pair_create(&ht.variables);
	zbx_vector_ptr_pair_create(&vars);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.variables"))
	{
		/* multi-variable form: in.variables is a sequence of {key, value} objects */
		hvars = zbx_mock_get_parameter_handle("in.variables");
		while (ZBX_MOCK_END_OF_VECTOR != (err = zbx_mock_vector_element(hvars, &hvar)))
		{
			if (ZBX_MOCK_SUCCESS != err)
				fail_msg("cannot read in.variables element: %s", zbx_mock_error_string(err));

			pair.first  = zbx_strdup(NULL, zbx_mock_get_object_member_string(hvar, "key"));
			pair.second = zbx_strdup(NULL, zbx_mock_get_object_member_string(hvar, "value"));
			zbx_vector_ptr_pair_append(&vars, pair);
		}
	}
	else
	{
		/* single-variable form: in.key / in.value */
		key = zbx_mock_get_parameter_string("in.key");
		val = zbx_mock_get_parameter_string("in.value");
#ifndef HAVE_LIBXML2
		if (NULL != strstr(val, "xmlxpath:") && NULL != data && SUCCEED == expected_ret)
			skip();
#endif
		pair.first  = zbx_strdup(NULL, key);
		pair.second = zbx_strdup(NULL, val);
		zbx_vector_ptr_pair_append(&vars, pair);
	}

	ret = http_process_variables(&ht, &vars, data, NULL);

	for (int i = 0; i < vars.values_num; i++)
	{
		zbx_free(vars.values[i].first);
		zbx_free(vars.values[i].second);
	}
	zbx_vector_ptr_pair_destroy(&vars);

	zbx_mock_assert_result_eq("http_process_variables() return value", expected_ret, ret);

	/* single macro check — only valid with the single-variable form */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.macro_value"))
	{
		zbx_mock_assert_str_eq("macro value in cache",
				zbx_mock_get_parameter_string("out.macro_value"),
				get_macro_value(&ht, key));
	}

	/* multi-macro check — sequence of {key, value} pairs */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.macro_values"))
	{
		hmacro_values = zbx_mock_get_parameter_handle("out.macro_values");
		while (ZBX_MOCK_END_OF_VECTOR != (err = zbx_mock_vector_element(hmacro_values, &hmv)))
		{
			const char	*mk, *mv;

			if (ZBX_MOCK_SUCCESS != err)
				fail_msg("cannot read out.macro_values element: %s", zbx_mock_error_string(err));

			mk = zbx_mock_get_object_member_string(hmv, "key");
			mv = zbx_mock_get_object_member_string(hmv, "value");
			zbx_mock_assert_str_eq("macro value in cache", mv, get_macro_value(&ht, mk));
		}
	}

	for (int i = 0; i < ht.macros.values_num; i++)
	{
		zbx_free(ht.macros.values[i].first);
		zbx_free(ht.macros.values[i].second);
	}
	zbx_vector_ptr_pair_destroy(&ht.macros);
	zbx_vector_ptr_pair_destroy(&ht.variables);
}
