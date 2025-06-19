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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxexpr.h"
#include "zbxparam.h"

typedef struct
{
	int	func_hit;	/* number of times substitution function was called */
	int	no_macros;	/* number of times substitution function had no macros */
	int	quoted;		/* number of times parameters were quoted */
	int	unquoted_first;	/* number of times parameters were unquoted first */
	int	quoted_after;	/* number of times parameters were quoted after */
}
subst_data_t;

static subst_data_t	subst_data = {0};

static int	resolv_func(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data, char *error,
		size_t maxerrlen)
{
	int	ret = SUCCEED;

	/* Passed parameters */
	const char	*macro = va_arg(args, const char *);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:%s", __func__, p->macro);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	/* Use a fixed macro name in tests: {$MACRO}. No need to test user macro cache. */
	if (0 == strcmp(p->macro, "{$MACRO}"))
	{
		if (NULL == macro)
			ret = FAIL;
		else
			*replace_to = zbx_strdup(*replace_to, macro);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d result:%s", __func__, ret, *replace_to);

	return ret;
}

static int	subst_func_resolved(const char *data, int level, int num, int quoted, char **param, va_list args)
{
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): data:%s level:%d num:%d quoted:%d param:%s", __func__, data, level, num,
			quoted, *param);

	subst_data.func_hit++;

	if (1 == quoted)
		subst_data.quoted++;

	/* Passed parameters */
	const char	*macro = va_arg(args, const char *);

	if (NULL == strchr(data, '{'))
	{
		subst_data.no_macros++;
		goto out;
	}

	*param = zbx_strdup(NULL, data);

	if (0 != level)
	{
		zbx_unquote_key_param(*param);
		subst_data.unquoted_first++;
	}

	zbx_substitute_macros(param, NULL, 0, resolv_func, macro);

	if (0 != level)
	{
		if (FAIL == (ret = zbx_quote_key_param(param, quoted)))
		{
			zbx_free(*param);
		}
		else
		{
			subst_data.quoted_after++;
		}
	}

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() param:%s", __func__, *param);

	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	const char		*macro = NULL;
	char			error[MAX_BUFFER_LEN];

	ZBX_UNUSED(state);

	memset(&subst_data, 0, sizeof(subst_data));

	const char	*key = zbx_mock_get_parameter_string("in.key");

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.macro"))
		macro = zbx_mock_get_parameter_string("in.macro");

	int	exp_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	char	*result = zbx_strdup(NULL, key);

	int	ret = zbx_substitute_item_key_params(&result, error, MAX_BUFFER_LEN, &subst_func_resolved, macro);

	zbx_mock_assert_int_eq("return value", exp_ret, ret);

	if (SUCCEED == ret)
	{
		const char	*expected_result = zbx_mock_get_parameter_string("out.result");

		zbx_mock_assert_str_eq("zbx_substitute_item_key_params() result", expected_result, result);
	}
	else
	{
		const char	*expected_error = zbx_mock_get_parameter_string("out.error");

		zbx_mock_assert_str_eq("zbx_substitute_item_key_params() error", expected_error, error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "func_hit:%d no_macros:%d quoted:%d unquoted_first:%d quoted_after:%d",
			subst_data.func_hit, subst_data.no_macros, subst_data.quoted, subst_data.unquoted_first,
			subst_data.quoted_after);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.func_hit"))
	{
		zbx_mock_assert_int_eq("substitution function was called", zbx_mock_get_parameter_int("out.func_hit"),
				subst_data.func_hit);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.no_macros"))
	{
		zbx_mock_assert_int_eq("substitution function had no macros",
				zbx_mock_get_parameter_int("out.no_macros"), subst_data.no_macros);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.quoted"))
	{
		zbx_mock_assert_int_eq("substitution function quoted parameters",
				zbx_mock_get_parameter_int("out.quoted"), subst_data.quoted);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.unquoted_first"))
	{
		zbx_mock_assert_int_eq("substitution function unquoted first times",
				zbx_mock_get_parameter_int("out.unquoted_first"), subst_data.unquoted_first);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.quoted_after"))
	{
		zbx_mock_assert_int_eq("substitution function quoted after times",
				zbx_mock_get_parameter_int("out.quoted_after"), subst_data.quoted_after);
	}

	zbx_free(result);
}
