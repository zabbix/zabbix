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
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxstr.h"

void	*__wrap_realloc(void *old , size_t size);

void		*ret_ptr = (void *)1;
size_t		got_size;
const char	*warning[2] = {0};
int		out_of_mem = 0;

void	*__wrap_realloc(void *old , size_t size)
{
	(void)old;

	if (1 == out_of_mem)
	{
		out_of_mem = 0;

		return NULL;
	}

	got_size = size;

	return ret_ptr;
}

static void	zabbix_log_stub(int level, const char *fmt, va_list args)
{
	ZBX_UNUSED(level);
	ZBX_UNUSED(args);

	if (NULL == warning[0])
	{
		warning[0] = fmt;
	}
	else if (NULL == warning[1])
	{
		warning[1] = fmt;

	}
}

void	zbx_mock_test_entry(void **state)
{
	void	*old = NULL, *result;
	size_t	in_size = zbx_mock_get_parameter_uint64("in.size");

	ZBX_UNUSED(state);

	if (SUCCEED == zbx_mock_parameter_exists("in.out_of_mem"))
		out_of_mem = 1;

	zbx_init_library_common(zabbix_log_stub, zbx_mock_get_log_level_impl, NULL, NULL);

	result = zbx_realloc(old, in_size);

	zbx_mock_assert_uint64_eq("realloc size", zbx_mock_get_parameter_uint64("out.size"), got_size);

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

	(void)result;
}
