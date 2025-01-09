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
#include "service_manager_impl.h"

#include "mock_service.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_service_t	*service;
	int		status_ret, rc_ret, rc_exp;
	const char	*service_name, *status_exp;

	ZBX_UNUSED(state);

	mock_init_service_cache("in.services");

	service_name = zbx_mock_get_parameter_string("in.service");
	if (NULL == (service = mock_get_service(service_name)))
		fail_msg("cannot find service '%s'", service_name);

	rc_ret = service_get_status(service, &status_ret);
	mock_destroy_service_cache();

	rc_exp = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("service_get_setatus() return value", rc_exp, rc_ret);

	if (SUCCEED == rc_exp)
	{
		status_exp = zbx_mock_get_parameter_string("out.status");
		zbx_mock_assert_int_eq("propagated service status", atoi(status_exp), status_ret);
	}
}
