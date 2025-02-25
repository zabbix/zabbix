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

#include "zbxcommon.h"
#include "zbxeval.h"
#include "mock_eval.h"

static int	macro_resolver(zbx_token_type_t type, char **value, char **error, va_list args)
{
	if (*value == NULL)
		return FAIL;

	const char *host_name = va_arg(args, const char *);
	const char *host_name_replace = va_arg(args, const char *);

	if (strcmp(*value, host_name) == 0)
	{
		*value = zbx_strdup(NULL ,host_name_replace);
	}
	else
	{
		*value = zbx_strdup(NULL ,"error");
	}

	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{

	const char	*itemsq = zbx_mock_get_parameter_string("in.itemquery"),
			*macro = zbx_mock_get_parameter_string("in.macro"),
			*macro_replace = zbx_mock_get_parameter_string("in.macro_replace");
	char		*error = NULL, *out = NULL;
	int		result , exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	ZBX_UNUSED(state);

	result = zbx_eval_query_subtitute_user_macros(itemsq, strlen(itemsq),&out, &error, macro_resolver, macro,
			macro_replace);

	if (FAIL == result)
		printf("error: %s\n", error);

	if(SUCCEED == result)
	{
		char *exp_out = zbx_strdup(NULL, zbx_mock_get_parameter_string("out.out"));

		zbx_mock_assert_str_eq("return out: ", exp_out, out);
		zbx_free(exp_out);
	}

	if(NULL != out)
		zbx_free(out);

	if(NULL != error)
		zbx_free(error);

	zbx_mock_assert_int_eq("return value: ", exp_result, result);
}
