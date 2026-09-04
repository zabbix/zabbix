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
#include "zbxmockdb.h"
#include "zbxhistory.h"
#include "libs/zbxhistory/history_option.h"

static int	history_option_compare(const void *d1, const void *d2)
{
	const zbx_history_option_t	*opt1 = (zbx_history_option_t *)d1;
	const zbx_history_option_t	*opt2 = (zbx_history_option_t *)d2;

	return strcmp(opt1->name, opt2->name);
}

static void	mock_get_options(const char *path, zbx_vector_history_option_t *options)
{
	zbx_mock_handle_t	hoptions, hoption;

	hoptions = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hoptions, &hoption))
	{
		zbx_history_option_t	option;

		option.name = zbx_strdup(NULL, zbx_mock_get_object_member_string(hoption, "name"));
		option.value = zbx_strdup(NULL, zbx_mock_get_object_member_string(hoption, "value"));

		zbx_vector_history_option_append(options, option);
	}

	zbx_vector_history_option_sort(options, history_option_compare);
}

static void	mock_compare_options(const zbx_vector_history_option_t *opt_exp,
		const zbx_vector_history_option_t *opt_ret)
{
	zbx_mock_assert_int_eq("number of parsed options", opt_exp->values_num, opt_ret->values_num);

	for (int i = 0; i < opt_ret->values_num; i++)
	{
		zbx_mock_assert_str_eq("parsed option name", opt_exp->values[i].name, opt_ret->values[i].name);
		zbx_mock_assert_str_eq("parsed option value", opt_exp->values[i].value, opt_ret->values[i].value);
	}
}

void	zbx_mock_test_entry(void **state)
{
	int				result_ret, result_exp;
	zbx_vector_history_option_t	options_ret, options_exp;
	const char			*conf, *name_exp;
	char				*name_ret, *error = NULL;

	ZBX_UNUSED(state);

	zbx_vector_history_option_create(&options_ret);
	zbx_vector_history_option_create(&options_exp);

	conf = zbx_mock_get_parameter_string("in.conf");
	result_exp = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	result_ret = history_provider_parse_options(conf, &name_ret, &options_ret, &error);
	if (FAIL == result_ret)
	{
		zabbix_log(LOG_LEVEL_WARNING, "error: %s", error);
		zbx_free(error);
	}

	zbx_mock_assert_result_eq("history_provider_parse_options() return value", result_exp, result_ret);

	if (FAIL != result_ret)
	{
		name_exp = zbx_mock_get_parameter_string("out.name");
		zbx_mock_assert_str_eq("provider name", name_exp, name_ret);
		zbx_free(name_ret);

		zbx_vector_history_option_sort(&options_ret, history_option_compare);
		mock_get_options("out.options", &options_exp);
		mock_compare_options(&options_exp, &options_ret);

		history_options_clear(options_ret.values, options_ret.values_num);
		history_options_clear(options_exp.values, options_exp.values_num);
	}

	zbx_vector_history_option_destroy(&options_ret);
	zbx_vector_history_option_destroy(&options_exp);
}
