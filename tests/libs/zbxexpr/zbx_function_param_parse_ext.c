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
#include "zbxalgo.h"
#include "zbxexpr.h"

#define ZBX_TOKEN_OBJECTID		0x00001
#define ZBX_TOKEN_MACRO			0x00002
#define ZBX_TOKEN_LLD_MACRO		0x00004
#define ZBX_TOKEN_USER_MACRO		0x00008
#define ZBX_TOKEN_FUNC_MACRO		0x00010
#define ZBX_TOKEN_SIMPLE_MACRO		0x00020
#define ZBX_TOKEN_REFERENCE		0x00040
#define ZBX_TOKEN_LLD_FUNC_MACRO	0x00080
#define ZBX_TOKEN_EXPRESSION_MACRO	0x00100

static zbx_uint32_t	get_allowed_macros(const char *path)
{
	zbx_uint32_t		tokens = 0;
	zbx_mock_handle_t	htokens, htoken;
	zbx_mock_error_t	err;
	int			tokens_num = 0;

	htokens = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(htokens, &htoken))))
	{
		const char	*token;

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(htoken, &token)))
			fail_msg("Cannot read token #%d: %s", tokens_num, zbx_mock_error_string(err));

		if (0 == strcmp(token, "ZBX_TOKEN_OBJECTID"))
			tokens |= ZBX_TOKEN_OBJECTID;
		else if (0 == strcmp(token, "ZBX_TOKEN_MACRO"))
			tokens |= ZBX_TOKEN_MACRO;
		else if (0 == strcmp(token, "ZBX_TOKEN_LLD_MACRO"))
			tokens |= ZBX_TOKEN_LLD_MACRO;
		else if (0 == strcmp(token, "ZBX_TOKEN_USER_MACRO"))
			tokens |= ZBX_TOKEN_USER_MACRO;
		else if (0 == strcmp(token, "ZBX_TOKEN_FUNC_MACRO"))
			tokens |= ZBX_TOKEN_FUNC_MACRO;
		else if (0 == strcmp(token, "ZBX_TOKEN_SIMPLE_MACRO"))
			tokens |= ZBX_TOKEN_SIMPLE_MACRO;
		else if (0 == strcmp(token, "ZBX_TOKEN_REFERENCE"))
			tokens |= ZBX_TOKEN_REFERENCE;
		else if (0 == strcmp(token, "ZBX_TOKEN_LLD_FUNC_MACRO"))
			tokens |= ZBX_TOKEN_LLD_FUNC_MACRO;
		else if (0 == strcmp(token, "ZBX_TOKEN_EXPRESSION_MACRO"))
			tokens |= ZBX_TOKEN_EXPRESSION_MACRO;
		else
			fail_msg("Unsupported token: %s", token);

		tokens_num++;
	}

	return tokens;
}

static void	get_parameters(const char *path, zbx_vector_str_t *params)
{
	zbx_mock_handle_t	hparams, hparam;
	zbx_mock_error_t	err;
	int			params_num = 0;

	hparams = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hparams, &hparam))))
	{
		const char	*param;

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hparam, &param)))
		{
			param = NULL;
			fail_msg("Cannot read param #%d: %s", params_num, zbx_mock_error_string(err));
		}

		zbx_vector_str_append(params, (char *)param);

		params_num++;
	}
}
void	zbx_mock_test_entry(void **state)
{
	const char		*params;
	int			esc_bs, i = 0;
	zbx_uint32_t		allowed_macros = 0;
	zbx_vector_str_t	params_out;
	size_t			pos = 0, length, sep = 0, offset = 0;
	char			*param;

	ZBX_UNUSED(state);

	zbx_vector_str_create(&params_out);
	get_parameters("out.params", &params_out);

	params = zbx_mock_get_parameter_string("in.params");
	allowed_macros = get_allowed_macros("in.macros");
	esc_bs = (int)zbx_mock_get_parameter_uint64("in.escape_bs");

	while (1)
	{
		offset += sep;

		zbx_function_param_parse_ext(params + offset, allowed_macros, esc_bs, &pos, &length, &sep);

		if (0 == length)
			break;

		param = (char *)zbx_malloc(NULL, length + 1);
		memcpy(param, params + offset + pos, length);
		param[length] = '\0';

		if (i == params_out.values_num)
			fail_msg("[%d] unexpected parameter '%s'", i, param);

		if (0 != strcmp(params_out.values[i], param))
			fail_msg("[%d] expected parameter '%s' while got '%s'", i, params_out.values[i], param);

		zbx_free(param);
		i++;

		if ('\0' == params[offset + sep] || ')' == params[offset + sep])
			break;
		sep++;
	}

	if (i != params_out.values_num)
		fail_msg("[%d] missing parameter '%s'", i, params_out.values[i]);

	zbx_vector_str_destroy(&params_out);
}
