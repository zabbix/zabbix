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

#define exit	__real_exit
#include <stdlib.h>
#undef exit

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "zbxcommon.h"

void	__wrap_exit(int status);

void	__wrap_exit(int status)
{
	zbx_mock_error_t	error;
	int			expected_status;

	if (ZBX_MOCK_NO_EXIT_CODE == (error = zbx_mock_exit_code(&expected_status)))
		expected_status = EXIT_SUCCESS;
	else
	{
		if (ZBX_MOCK_SUCCESS != error)
			fail_msg("Cannot get exit code from test case data: %s", zbx_mock_error_string(error));
	}

	switch (status)
	{
		case EXIT_SUCCESS:
		case EXIT_FAILURE:
			if (status != expected_status)
				fail_msg("exit() called with status %d, expected %d.", status, expected_status);
			__real_exit(EXIT_SUCCESS);
		default:
			fail_msg("exit() called with status %d that is neither EXIT_SUCCESS nor EXIT_FAILURE.", status);
	}
}
