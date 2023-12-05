/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include <fcntl.h>

#include "zbxfile.h"

#include "zbxcommon.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

static void	test1(uint64_t bufsz, const char *encoding, int *lines, ssize_t *result)
{
	int	f;
	char	*line, *buf;
	void	*saveptr = NULL;

	f = zbx_open("", O_RDWR); /* in.fragments */
	zbx_mock_assert_int_ne("Cannot open file:", -1, f);

	buf = malloc(bufsz);
	*lines = 0;

	while (0 < (*result = zbx_buf_readln(f, buf, bufsz, encoding, &line, &saveptr)))
	{
		if ('\n' == line[*result-1]) line[*result - 1] = 0;
		printf("Line: %.*s\n", (int)*result, line);
		*lines += 1;
	}

	zbx_free(saveptr);
	zbx_free(buf);

	close(f);
}

static void	test2(uint64_t bufsz, const char *encoding, int *lines, int *result)
{
	int	f;
	char	*buf;

	f = zbx_open("", O_RDWR); /* in.fragments */
	zbx_mock_assert_int_ne("Cannot open file:", -1, f);

	buf = malloc(bufsz);
	*lines = 0;

	while (0 < (*result = zbx_read_text_line_from_file(f, buf, bufsz, encoding)))
	{
		if ('\n' == buf[*result-1]) buf[*result - 1] = 0;
		printf("Line: %.*s\n", (int)*result, buf);
		*lines += 1;
	}

	close(f);
}

void	zbx_mock_test_entry(void **state)
{
	int		lines1, lines2, result2;
	const char	*encoding, *result;
	ssize_t		line_count, result1;
	uint64_t	bufsz;

	ZBX_UNUSED(state);

	encoding = zbx_mock_get_parameter_string("in.encoding");
	bufsz = zbx_mock_get_parameter_uint64("in.bufsz");

	line_count = (ssize_t)zbx_mock_get_parameter_uint64("out.line_count");
	result = zbx_mock_get_parameter_string("out.result");

	test1(bufsz, encoding, &lines1, &result1);
	test2(bufsz, encoding, &lines2, &result2);

	zbx_mock_assert_int_eq("Read lines (1)", (int)line_count, lines1);
	zbx_mock_assert_int_eq("Read result (1)", atoi(result), (int)result1);

	zbx_mock_assert_int_eq("Read lines (2)", (int)line_count, lines2);
	zbx_mock_assert_int_eq("Read result (2)", atoi(result), (int)result2);
}
