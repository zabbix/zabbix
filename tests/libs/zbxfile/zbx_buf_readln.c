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

void	zbx_mock_test_entry(void **state)
{
	int		f, lines = 0;
	const char	*encoding, *result;
	ssize_t		nbytes, line_count;
	uint64_t	bufsz;

	ZBX_UNUSED(state);

	encoding = zbx_mock_get_parameter_string("in.encoding");
	bufsz = zbx_mock_get_parameter_uint64("in.bufsz");

	line_count = (ssize_t)zbx_mock_get_parameter_uint64("out.line_count");
	result = zbx_mock_get_parameter_string("out.result");

	f = zbx_open("", O_RDWR); /* in.fragments */
	zbx_mock_assert_int_ne("Cannot open file:", -1, f);

	char	*line, *buf;
	void	*saveptr = NULL;

	buf = malloc(bufsz);

	while (0 < (nbytes = zbx_buf_readln(f, buf, bufsz, encoding, &line, &saveptr)))
	{
		if ('\n' == line[nbytes-1]) line[nbytes - 1] = 0;
		printf("Line: %.*s\n", (int)nbytes, line);
		lines++;
	}

	zbx_free(saveptr);
	zbx_free(buf);

	zbx_mock_assert_int_eq("Read lines", (int)line_count, lines);
	zbx_mock_assert_int_eq("Read result", atoi(result), (int)nbytes);

	close(f);
}
