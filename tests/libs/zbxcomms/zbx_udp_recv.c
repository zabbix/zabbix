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

static int	recvfrom_return[2], recvfrom_iter;

ssize_t	__wrap_recvfrom(int fd, void *__restrict buf, size_t n, int flags, __SOCKADDR_ARG addr,
					socklen_t *__restrict addr_len);

ssize_t	__wrap_recvfrom(int fd, void *__restrict buf, size_t n, int flags, __SOCKADDR_ARG addr,
					socklen_t *__restrict addr_len)
{

	ZBX_UNUSED(fd);
	ZBX_UNUSED(buf);
	ZBX_UNUSED(n);
	ZBX_UNUSED(flags);
	ZBX_UNUSED(addr);
	ZBX_UNUSED(addr_len);

	int	ret = recvfrom_return[recvfrom_iter];

	recvfrom_iter++;

	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t		s;
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	zbx_vector_int32_t	recvfrom_return_seq;

	ZBX_UNUSED(state);

	zbx_vector_int32_create(&recvfrom_return_seq);
	zbx_mock_extract_yaml_values_int32(zbx_mock_get_parameter_handle("in.recvfrom_return"), &recvfrom_return_seq);

	if (2 > recvfrom_return_seq.values_num)
		fail_msg("Too many elements in recvfrom_return_seq: %d (max 2)", recvfrom_return_seq.values_num);

	for (int i = 0; i < recvfrom_return_seq.values_num; i++)
		recvfrom_return[i] = recvfrom_return_seq.values[i];

	zbx_comms_mock_poll_set_mode_from_param(zbx_mock_get_parameter_string("in.poll_mode"));
	set_nonblocking_error();

	/* 0 used for timeout - poll() is wrapped for tests */
	int	result = zbx_udp_recv(&s, 0);

	zbx_mock_assert_int_eq("return value:", exp_result, result);

	if (ZBX_BUF_TYPE_DYN == s.buf_type)
		zbx_free(s.buffer);

	zbx_socket_clean(&s);
	zbx_vector_int32_clear(&recvfrom_return_seq);
	zbx_vector_int32_destroy(&recvfrom_return_seq);
}
