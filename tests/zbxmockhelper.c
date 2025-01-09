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

#include "zbxcommon.h"
#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockhelper.h"

char	*zbx_yaml_assemble_binary_sequence(const char *path, size_t *expected)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	fragment, fragments;
	const char		*value;
	size_t			length, offset = 0;
	char			*buffer = NULL;

	if (0 != *expected)
		buffer = zbx_malloc(NULL, *expected);

	fragments = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(fragments, &fragment))
	{
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_binary(fragment, &value, &length)))
			fail_msg("Cannot read data '%s'", zbx_mock_error_string(error));

		if (0 != *expected && offset + length > *expected)
			fail_msg("Incorrect message size, expected:%ld actual:%ld", *expected, offset + length);

		buffer = zbx_realloc(buffer, offset + length);

		memcpy(buffer + offset, value, length);
		offset += length;
	}

	if (0 != *expected && offset != *expected)
		fail_msg("Assembled message is smaller:" ZBX_FS_UI64 " than expected:" ZBX_FS_UI64, offset, *expected);

	*expected = offset;

	return buffer;
}
