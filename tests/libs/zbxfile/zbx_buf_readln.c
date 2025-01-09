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

#include <fcntl.h>

#include "zbxfile.h"

#include "zbxcommon.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

static void print_line(const char *line, int size)
{
	printf("line: \"");
	for (int i = 0; i < size; i++)
	{
		if (isgraph(line[i]))
			printf("%c", line[i]);
		else
			printf("\\x%02X", (unsigned int)line[i]);
	}
	printf("\" size: %d\n", size);
}

static void test1(uint64_t bufsz, const char *encoding, int *lines, ssize_t *result)
{
	int	f;
	char	*line, *buf;
	void	*saveptr = NULL;

	printf("Test 1:\n");

	f = zbx_open("", O_RDWR); /* in.fragments */
	zbx_mock_assert_int_ne("Cannot open file:", -1, f);

	buf = malloc(bufsz);
	*lines = 0;

	while (0 < (*result = zbx_buf_readln(f, buf, bufsz, encoding, &line, &saveptr)))
	{
		if ('\n' == line[*result - 1])
			line[*result - 1] = 0;
		print_line(line, (int)*result);
		*lines += 1;
	}

	zbx_free(saveptr);
	zbx_free(buf);

	close(f);
}

static void test2(uint64_t bufsz, const char *encoding, int *lines, int *result)
{
	int	f;
	char	*buf;

	printf("Test 2:\n");

	f = zbx_open("", O_RDWR); /* in.fragments */
	zbx_mock_assert_int_ne("Cannot open file:", -1, f);

	buf = malloc(bufsz);
	*lines = 0;

	while (0 < (*result = zbx_read_text_line_from_file(f, buf, bufsz, encoding)))
	{
		if ('\n' == buf[*result - 1])
			buf[*result - 1] = 0;
		print_line(buf, *result);
		*lines += 1;
	}

	zbx_free(buf);

	close(f);
}

void zbx_mock_test_entry(void **state)
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
