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
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxcommon.h"

#include "zbxicmpping.h"

#include "../../../src/libs/zbxpinger/pinger.c"

#define MAX_ERR_LEN 256

void	zbx_mock_test_entry(void **state)
{
	icmpping_t		icmpping;
	icmppingsec_type_t	type;
	const char		*expected_addr = NULL, *interface = NULL, *key = NULL;
	char			error[MAX_ERR_LEN];
	int			ret;
	int			count, interval, size, timeout;
	unsigned char		allow_redirect;
	char			*returned_addr = NULL;

	ZBX_UNUSED(state);
	expected_addr = zbx_mock_get_parameter_string("out.address");
	interface = zbx_mock_get_parameter_string("in.interface");
	key =  zbx_mock_get_parameter_string("in.key");

	ret = zbx_parse_key_params(key, interface, &icmpping, &returned_addr, &count,
			&interval, &size, &timeout, &type, &allow_redirect, error, MAX_ERR_LEN);
	if (SUCCEED != ret)
		printf("zbx_pinger_test error: %s\n", error);

	if (NULL == returned_addr || '\0' == *returned_addr)
	{
		printf("zbx_pinger_test debug: address is NULL\n");
		if (NULL != expected_addr && '\0' != *expected_addr)
			fail_msg("Expected value \"%s\" while got NULL", expected_addr);
	}
	else
	{
		printf("zbx_pinger_test debug: address is [%s]\n", returned_addr);
		zbx_mock_assert_str_eq("Returned address", expected_addr, returned_addr);
	}

	zbx_free(returned_addr);
}
