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

int	__wrap_zbx_ts_check_deadline(const zbx_timespec_t *deadline);

int	__wrap_zbx_ts_check_deadline(const zbx_timespec_t *deadline)
{
	ZBX_UNUSED(deadline);

	return FAIL;
}

void	zbx_mock_test_entry(void **state)
{
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	zbx_socket_t	s;
	char		*error;

	ZBX_UNUSED(state);

	zbx_comms_mock_poll_set_mode_from_param(zbx_mock_get_parameter_string("in.poll_mode"));
	set_nonblocking_error();

	/* 0 used for timeout - poll() is wrapped for tests */
	int	result = zbx_socket_pollout(&s, 0, &error);

	zbx_mock_assert_int_eq("return value", exp_result, result);

	if (SUCCEED != result)
	{
		char	*exp_error = zbx_strdup(NULL, zbx_mock_get_parameter_string("out.error"));

		zbx_mock_assert_str_eq("error", exp_error, error);
		zbx_free(error);
		zbx_free(exp_error);
	}
}
