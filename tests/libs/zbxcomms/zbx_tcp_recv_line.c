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

#include "zbx_comms_common.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t		s;
	const char		*function_result,
				*buf_stat = zbx_mock_get_parameter_string("in.buf_stat");
	int			result = SUCCEED;
	zbx_vector_str_t	exp_result_v, result_v;
	zbx_vector_int32_t	read_return_seq;

	ZBX_UNUSED(state);

	set_nonblocking_error();
	zbx_comms_set_test_comms(SUCCEED);
	memset(&s, 0, sizeof(s));

	zbx_vector_str_create(&exp_result_v);
	zbx_vector_str_create(&result_v);
	zbx_vector_int32_create(&read_return_seq);

	zbx_mock_extract_yaml_values_int32(zbx_mock_get_parameter_handle("in.read_return"), &read_return_seq);

	if (5 < read_return_seq.values_num)
		fail_msg("Too many elements in read_return_seq: %d (max 5)", read_return_seq.values_num);

	zbx_comms_setup_read(&read_return_seq);

	zbx_strlcpy(s.buf_stat, buf_stat, ZBX_STAT_BUF_LEN - 1);
	s.buffer = s.buf_stat;
	s.read_bytes = strlen(s.buf_stat);

	if (SUCCEED == zbx_mock_parameter_exists("in.next_line_is_null"))
		s.next_line = NULL;
	else
		s.next_line = s.buf_stat;

	while (NULL != (function_result = zbx_tcp_recv_line(&s)))
	{
		zbx_vector_str_append(&result_v, zbx_strdup(NULL ,function_result));
	}

	zbx_mock_extract_yaml_values_str("out.result", &exp_result_v);

	if (exp_result_v.values_num != result_v.values_num)
	{
		fail_msg("Different number of elements in result vectors: expected %d, actual %d",
				exp_result_v.values_num, result_v.values_num);
	}

	for (int i = 0; i < exp_result_v.values_num; i++)
	{
		if (0 != strcmp(exp_result_v.values[i], result_v.values[i]))
		{
			result = FAIL;
			break;
		}
	}

	zbx_mock_assert_int_eq("return value: ", SUCCEED, result);

	zbx_tcp_close(&s);

	zbx_vector_str_clear_ext(&exp_result_v, zbx_str_free);
	zbx_vector_str_destroy(&exp_result_v);
	zbx_vector_str_clear_ext(&result_v, zbx_str_free);
	zbx_vector_str_destroy(&result_v);
	zbx_vector_int32_clear(&read_return_seq);
	zbx_vector_int32_destroy(&read_return_seq);
}
