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
	const char	*ip = zbx_mock_get_parameter_string("in.ip");
	char		*host = zbx_malloc(NULL, ZBX_MAX_HOSTNAME_LEN),
			*exp_host = zbx_strdup(NULL, zbx_mock_get_parameter_string("out.host"));
	size_t		hostlen = sizeof(host);

	ZBX_UNUSED(state);

	zbx_gethost_by_ip(ip, host, ZBX_MAX_HOSTNAME_LEN);

	zbx_mock_assert_str_eq("return value", exp_host, host);

	zbx_free(host);
}
