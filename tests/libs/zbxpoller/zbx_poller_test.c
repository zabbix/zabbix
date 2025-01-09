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

#include "test_get_value_ssh.h"

#include "test_get_value_telnet.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_dc_item_t	item;
	const char	*test_type = NULL;
	int		returned_code, expected_code;
	char 		*error = NULL;

	ZBX_UNUSED(state);
	test_type =  zbx_mock_get_parameter_string("in.type");
	expected_code = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	memset((void*)&item, 0, sizeof(item));

	if (0 == zbx_strcmp_null("ZBX_TEST_GET_VALUE_SSH", test_type))
	{
#if defined(HAVE_SSH2) || defined(HAVE_SSH)
		memset((void*)&item, 0, sizeof(item));
		item.interface.addr = (char *)zbx_mock_get_parameter_string("in.item.interface");
		item.key = (char *)zbx_mock_get_parameter_string("in.item.key");
		item.timeout = 3;

		returned_code = zbx_get_value_ssh_test_run(&item, &error);
		if (SUCCEED != returned_code && NULL != error)
			printf("zbx_get_value_ssh_test_run error: %s\n", error);

		zbx_mock_assert_result_eq("Return value", expected_code, returned_code);

		zbx_free(error);
#endif	/* defined(HAVE_SSH2) || defined(HAVE_SSH) */
	}
	else if (0 == zbx_strcmp_null("ZBX_TEST_GET_VALUE_TELNET", test_type))
	{
		const char	*config_ssh_key_location = NULL;

		item.interface.addr = (char *)zbx_mock_get_parameter_string("in.item.interface");
		item.key = (char *)zbx_mock_get_parameter_string("in.item.key");
		item.timeout = 3;

		returned_code = zbx_get_value_telnet_test_run(&item, config_ssh_key_location, &error);

		if (SUCCEED != returned_code && NULL != error)
			printf("zbx_get_value_telnet_test_run error: %s\n", error);

		zbx_mock_assert_result_eq("Return value", expected_code, returned_code);

		zbx_free(error);
	}
}

