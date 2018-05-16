/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#define fopen	__real_fopen
#define fclose	__real_fclose
#define fgets	__real_fgets
#include <stdio.h>
#undef fopen
#undef fclose
#undef fgets

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "common.h"

#define ZBX_MOCK_MAX_FILES	16

void	*mock_streams[ZBX_MOCK_MAX_FILES];

struct zbx_mock_IO_FILE
{
	const char	*contents;
};

static int	is_profiler_path(const char *path)
{
	size_t	len;

	len = strlen(path);

	if (ZBX_CONST_STRLEN(".gcda") < len && 0 == strcmp(path + len - ZBX_CONST_STRLEN(".gcda"), ".gcda") ||
			ZBX_CONST_STRLEN(".gcno") < len && 0 == strcmp(path + len - ZBX_CONST_STRLEN(".gcno"), ".gcno"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	is_mock_stream(FILE *stream)
{
	int	i;

	for (i = 0; i < ZBX_MOCK_MAX_FILES && NULL != mock_streams[i]; i++)
	{
		if (stream == mock_streams[i])
			return SUCCEED;
	}

	return FAIL;
}

FILE	*__wrap_fopen(const char *path, const char *mode)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	file_contents;
	const char		*contents;
	struct zbx_mock_IO_FILE	*file = NULL;

	if (SUCCEED == is_profiler_path(path))
		return __real_fopen(path, mode);

	if (0 != strcmp(mode, "r"))
	{
		fail_msg("fopen() modes other than \"r\" are not supported.");
	}
	else if (ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_file(path, &file_contents)))
	{
		errno = ENOENT;	/* No such file or directory */
	}
	else if (ZBX_MOCK_SUCCESS != error)
	{
		fail_msg("Error while trying to open path \"%s\" from test case data: %s", path,
				zbx_mock_error_string(error));
	}
	else if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(file_contents, &contents)))
	{
		fail_msg("Error while trying to get contents of file \"%s\" from test case data: %s", path,
				zbx_mock_error_string(error));
	}
	else
	{
		int	i;

		for (i = 0; i < ZBX_MOCK_MAX_FILES && NULL != mock_streams[i]; i++)
			;

		if (i < ZBX_MOCK_MAX_FILES)
		{
			file = zbx_malloc(file, sizeof(struct zbx_mock_IO_FILE));
			file->contents = contents;
			mock_streams[i] = file;
		}
		else
			errno = EMFILE;	/* The per-process limit on the number of open file descriptors has been reached */
	}

	return (FILE *)file;
}

int	__wrap_fclose(FILE *stream)
{
	if (SUCCEED != is_mock_stream(stream))
		return __real_fclose(stream);

	zbx_free(stream);
	return 0;
}

char	*__wrap_fgets(char *s, int size, FILE *stream)
{
	struct zbx_mock_IO_FILE	*file = (struct zbx_mock_IO_FILE *)stream;
	int			length;
	const char		*newline;

	if (SUCCEED != is_mock_stream(stream))
		return __real_fgets(s, size, stream);

	assert_non_null(s);
	assert_true(0 < size);

	if ('\0' == *file->contents)
		return NULL;

	if (size - 1 < (length = strlen(file->contents)))
		length = size - 1;

	if (NULL != (newline = strchr(file->contents, '\n')) && newline - file->contents + 1 < length)
		length = newline - file->contents + 1;

	assert_int_equal(length, zbx_snprintf(s, size, "%.*s", length, file->contents));
	file->contents += length;
	return s;
}
