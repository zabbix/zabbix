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
	const char	*type_in = zbx_mock_get_parameter_string("in.type"),
			*type_out = zbx_mock_get_parameter_string("out.type");
	unsigned int	type = 0;

	ZBX_UNUSED(state);

	if (SUCCEED == strcmp("ZBX_TCP_SEC_UNENCRYPTED", type_in))
		type = ZBX_TCP_SEC_UNENCRYPTED;
	else if (SUCCEED == strcmp("ZBX_TCP_SEC_TLS_CERT", type_in))
		type = ZBX_TCP_SEC_TLS_CERT;
	else if (SUCCEED == strcmp("ZBX_TCP_SEC_TLS_PSK", type_in))
		type = ZBX_TCP_SEC_TLS_PSK;

	const char	*result = zbx_tcp_connection_type_name(type);

	zbx_mock_assert_str_eq("return value", type_out, result);
}
