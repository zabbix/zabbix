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
#include "zbxstr.h"

void	*__wrap_malloc(size_t size);
void	*__real_malloc(size_t size);
void	__wrap_zbx_log_handle(int level, const char *fmt, ...);
void	new_exit(int status);

size_t		got_size;
const char	*warning[3] = {0};
int		is_testing = 0, out_of_mem = 0;

void	*__wrap_malloc(size_t size)
{
	if (1 == out_of_mem)
	{
		out_of_mem = 0;

		return NULL;
	}
	else
	{
		if (1 == is_testing)
		{
			got_size = size;
			is_testing = 0;
		}
	}

	return __real_malloc(size);
}

void	__wrap_zbx_log_handle(int level, const char *fmt, ...)
{
	ZBX_UNUSED(level);

	if (NULL == warning[0])
	{
		warning[0] = fmt;
	}
	else if (NULL == warning[1])
	{
		warning[1] = fmt;

	}
	else if (NULL == warning[2])
	{
		warning[2] = fmt;
	}
}

void	zbx_mock_test_entry(void **state)
{
	void	*old, *result;
	int	in_old_check = zbx_mock_get_parameter_int("in.old");
	size_t	in_size = zbx_mock_get_parameter_uint64("in.size");

	ZBX_UNUSED(state);

	if (SUCCEED == zbx_mock_parameter_exists("in.out_of_mem"))
		out_of_mem = 1;

	if (in_old_check == 0)
		old = NULL;
	else
		old = (void *)1;

	is_testing = 1;
	result = zbx_malloc(old, in_size);

	zbx_mock_assert_uint64_eq("malloc size", zbx_mock_get_parameter_uint64("out.size"), got_size);

	if (SUCCEED == zbx_mock_parameter_exists("out.w1"))
	{
		if (NULL == zbx_strcasestr(warning[0], zbx_mock_get_parameter_string("out.w1")))
			fail_msg("Unexpected warning message: %s", zbx_mock_get_parameter_string("out.w1"));
	}

	if (SUCCEED == zbx_mock_parameter_exists("out.w2"))
	{
		if (NULL == zbx_strcasestr(warning[1], zbx_mock_get_parameter_string("out.w2")))
			fail_msg("Unexpected warning message: %s", zbx_mock_get_parameter_string("out.w2"));
	}

	if (SUCCEED == zbx_mock_parameter_exists("out.w3"))
	{
		if (NULL == zbx_strcasestr(warning[2], zbx_mock_get_parameter_string("out.w3")))
			fail_msg("Unexpected warning message: %s", zbx_mock_get_parameter_string("out.w3"));
	}

	if(1 != out_of_mem )
		zbx_free(result);
}
