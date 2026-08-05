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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*list = zbx_mock_get_parameter_string("in.list");
	char			delimiter = *zbx_mock_get_parameter_string("in.delimiter");
	zbx_vector_str_t	exp_tokens, act_tokens;
	const char		*token = NULL;
	size_t			token_len;
	int			i;

	ZBX_UNUSED(state);

	zbx_vector_str_create(&exp_tokens);
	zbx_mock_extract_yaml_values_str("out.tokens", &exp_tokens);

	zbx_vector_str_create(&act_tokens);

	while (SUCCEED == zbx_str_list_next(&list, delimiter, &token, &token_len))
		zbx_vector_str_append(&act_tokens, zbx_dsprintf(NULL, "%.*s", (int)token_len, token));

	zbx_mock_assert_int_eq("number of tokens", exp_tokens.values_num, act_tokens.values_num);

	for (i = 0; i < exp_tokens.values_num && i < act_tokens.values_num; i++)
		zbx_mock_assert_str_eq("token value", exp_tokens.values[i], act_tokens.values[i]);

	zbx_vector_str_clear_ext(&exp_tokens, zbx_str_free);
	zbx_vector_str_clear_ext(&act_tokens, zbx_str_free);
	zbx_vector_str_destroy(&exp_tokens);
	zbx_vector_str_destroy(&act_tokens);
}
