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
#include "zbxcomms.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t		s;
	const char		*function_result,
				*buf_stat = zbx_mock_get_parameter_string("in.buf_stat");
	int			result = SUCCEED;
	zbx_vector_str_t	exp_result_v, result_v;

	ZBX_UNUSED(state);

	memset(&s, 0, sizeof(s));
	zbx_vector_str_create(&exp_result_v);
	zbx_vector_str_create(&result_v);

	zbx_strlcpy(s.buf_stat, buf_stat, ZBX_STAT_BUF_LEN - 1);
	s.buffer = s.buf_stat;
	s.read_bytes = strlen(s.buf_stat);

	if (SUCCEED == zbx_mock_parameter_exists("in.next_line_is_null"))
		s.next_line = NULL;
	else
		s.next_line = s.buf_stat;

	zbx_mock_set_fragments(s.buf_stat, s.read_bytes);

	while (NULL != (function_result = zbx_tcp_recv_line(&s)))
	{
		zbx_vector_str_append(&result_v, zbx_strdup(NULL ,function_result));
	}

	zbx_mock_extract_yaml_values_str("out.result", &exp_result_v);

	for (int i = 0; exp_result_v.values_num < i; i++)
	{
		if (0 != strcmp(exp_result_v.values[i],result_v.values[i]));
		{
			result = FAIL;
			break;
		}
	}

	zbx_mock_assert_int_eq("return value: ", SUCCEED, result);

	//zbx_free(s.buffer);
	zbx_vector_str_clear_ext(&exp_result_v, zbx_str_free);
	zbx_vector_str_destroy(&exp_result_v);
	zbx_vector_str_clear_ext(&result_v, zbx_str_free);
	zbx_vector_str_destroy(&result_v);
}
