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
#include "zbxcfg.h"
#include "zbxip.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*url = zbx_mock_get_parameter_string("in.url");
	const char	*connect_to = zbx_mock_get_parameter_string("in.connect_to");
	int		ipv = zbx_mock_get_parameter_int("in.ipv");
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	char		*curl_connect_to = NULL, *error = NULL;
	int		result;

	ZBX_UNUSED(state);

#ifndef HAVE_IPV6
	if (ZBX_IPRANGE_V4 == ipv)
#else
	if (ZBX_IPRANGE_V4 == ipv || ZBX_IPRANGE_V6 == ipv)
#endif
	{
		result = zbx_cfg_prepare_bridge_adapter_connect_to(url, connect_to, &curl_connect_to, &error);
		zbx_mock_assert_int_eq("return value", exp_result, result);

		if (SUCCEED == exp_result)
		{
			zbx_mock_assert_str_eq("curl_connect_to",
					zbx_mock_get_parameter_string("out.curl_connect_to"), curl_connect_to);
		}
	}

	zbx_free(curl_connect_to);
	zbx_free(error);
}
