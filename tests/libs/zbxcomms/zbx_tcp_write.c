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
	zbx_socket_t	s;
	int		exp_offset = zbx_mock_get_parameter_int("out.result");
	size_t		len = zbx_mock_get_parameter_uint64("in.len");
	char		buf[ZBX_MAX_HOSTNAME_LEN];

	ZBX_UNUSED(state);

	zbx_socket_clean(&s);
	zbx_comms_mock_poll_set_mode_from_param(zbx_mock_get_parameter_string("in.poll_mode"));
	set_nonblocking_error();

	ssize_t	offset = zbx_tcp_write(&s, buf, len, NULL);

	zbx_mock_assert_int_eq("return value:", exp_offset, (int)offset);
}
