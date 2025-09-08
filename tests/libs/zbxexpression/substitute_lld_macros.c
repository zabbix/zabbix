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

#include "zbxcommon.h"

#include "zbxjson.h"
#include "zbxexpression.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"


static void	get_macros(const char *path, zbx_vector_lld_macro_path_ptr_t *macros)
{
	zbx_lld_macro_path_t	*macro;
	zbx_mock_handle_t	hmacros, hmacro;
	int			macros_num = 1;
	zbx_mock_error_t	err;

	hmacros = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hmacros, &hmacro))))
	{
		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("Cannot read macro #%d: %s", macros_num, zbx_mock_error_string(err));

		macro = (zbx_lld_macro_path_t *)zbx_malloc(NULL, sizeof(zbx_lld_macro_path_t));
		macro->lld_macro = zbx_strdup(NULL, zbx_mock_get_object_member_string(hmacro, "macro"));
		macro->path = zbx_strdup(NULL, zbx_mock_get_object_member_string(hmacro, "path"));
		zbx_vector_lld_macro_path_ptr_append(macros, macro);

		macros_num++;
	}
}

static int	get_flags(const char *path)
{
	zbx_mock_handle_t	hflags, hflag;
	int			flags_num = 1, flags = 0;
	zbx_mock_error_t	err;

	hflags = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hflags, &hflag))))
	{
		const char	*flag;

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hflag, &flag)))
			fail_msg("Cannot read flag #%d: %s", flags_num, zbx_mock_error_string(err));
		else if (0 == strcmp(flag, "ZBX_TOKEN_JSON"))
			flags |= ZBX_TOKEN_JSON;
		else if (0 == strcmp(flag, "ZBX_TOKEN_REGEXP"))
			flags |= ZBX_TOKEN_REGEXP;
		else if (0 == strcmp(flag, "ZBX_TOKEN_XPATH"))
			flags |= ZBX_TOKEN_XPATH;
		else if (0 == strcmp(flag, "ZBX_TOKEN_REGEXP_OUTPUT"))
			flags |= ZBX_TOKEN_REGEXP_OUTPUT;
		else if (0 == strcmp(flag, "ZBX_TOKEN_PROMETHEUS"))
			flags |= ZBX_TOKEN_PROMETHEUS;
		else if (0 == strcmp(flag, "ZBX_TOKEN_JSONPATH"))
			flags |= ZBX_TOKEN_JSONPATH;
		else if (0 == strcmp(flag, "ZBX_TOKEN_STR_REPLACE"))
			flags |= ZBX_TOKEN_STR_REPLACE;
		else if (0 == strcmp(flag, "ZBX_MACRO_ANY"))
			flags |= ZBX_MACRO_ANY;
		else if (0 == strcmp(flag, "ZBX_MACRO_JSON"))
			flags |= ZBX_MACRO_JSON;
		else if (0 == strcmp(flag, "ZBX_MACRO_FUNC"))
			flags |= ZBX_MACRO_FUNC;
		else if (0 == strcmp(flag, "ZBX_TOKEN_EXPRESSION_MACRO"))
			flags |= ZBX_TOKEN_EXPRESSION_MACRO;

		flags_num++;
	}

	return flags;
}

void	zbx_mock_test_entry(void **state)
{
	int				expected_ret, returned_ret, flags;
	char				*expression;
	const char			*expected_expression, *lld_row;
	zbx_vector_lld_macro_path_ptr_t	macros;
	struct zbx_json_parse		jp;

	ZBX_UNUSED(state);

	zbx_vector_lld_macro_path_ptr_create(&macros);
	get_macros("in.macros", &macros);
	flags = get_flags("in.flags");

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	expression = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.expression"));

	lld_row = zbx_mock_get_parameter_string("in.lld");
	if (SUCCEED != zbx_json_open(lld_row, &jp))
		fail_msg("invalid lld row parameter: %s", zbx_json_strerror());

	returned_ret = zbx_substitute_lld_macros(&expression, &jp, &macros, flags, NULL, 0);

	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		expected_expression = zbx_mock_get_parameter_string("out.expression");
		zbx_mock_assert_str_eq("resulting expression", expected_expression, expression);
	}

	zbx_free(expression);
	zbx_vector_lld_macro_path_ptr_clear_ext(&macros, zbx_lld_macro_path_free);
	zbx_vector_lld_macro_path_ptr_destroy(&macros);
}
