/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbx_common_trim_utf8.h"
#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "common.h"

static const char	*read_utf8(const char *path_str, const char *path_hex)
{
	const char		*data;
	size_t			len;
	zbx_mock_handle_t	hdata;

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter(path_str, &hdata))
	{
		if (ZBX_MOCK_SUCCESS != zbx_mock_string(hdata, &data))
			fail_msg("invalid string format");
	}
	else if (ZBX_MOCK_SUCCESS == zbx_mock_parameter(path_hex, &hdata))
	{
		zbx_mock_binary(hdata, &data, &len);
	}
	else
		fail_msg("cannot read %s/%s parameter", path_str, path_hex);

	return data;
}

void	zbx_mock_test_entry_common_trim_utf8(void **state, int trim_utf8_func)
{
	ZBX_UNUSED(state);

	char		*in = zbx_strdup(NULL, read_utf8("in.text.str", "in.text.hex"));
	const char	*charlist = read_utf8("in.charlist.str", "in.charlist.hex");

	if (ZABBIX_MOCK_LTRIM_UTF8 == trim_utf8_func)
		zbx_ltrim_utf8(in, charlist);
	else if (ZABBIX_MOCK_RTRIM_UTF8 == trim_utf8_func)
		zbx_rtrim_utf8(in, charlist);
	else
		fail_msg("Invalid trim_utf8_func");

	const char	*expected = read_utf8("out.str", "out.hex");

	zbx_mock_assert_str_eq("trimmed value", expected, in);
	zbx_free(in);
}
