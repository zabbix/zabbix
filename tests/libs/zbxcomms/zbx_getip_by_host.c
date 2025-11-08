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
#include "zbxcomms.h"


void	zbx_mock_test_entry(void **state)
{
	const char	*host = zbx_mock_get_parameter_string("in.host");
	char		ip[ZBX_MAX_HOSTNAME_LEN];

	ZBX_UNUSED(state);

	zbx_getip_by_host(host, ip, ZBX_MAX_HOSTNAME_LEN);

	const char	*exp_v4 = zbx_mock_get_parameter_string("out.result_v4");
	const char	*exp_v6 = zbx_mock_get_parameter_string("out.result_v6");

	if (0 != strcmp(ip, exp_v4) && 0 != strcmp(ip, exp_v6))
		fail_msg("Expected %s or %s, got %s", exp_v4, exp_v6, ip);
}
