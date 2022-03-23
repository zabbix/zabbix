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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "macrofunc.h"
#include "log.h"
#include "zbxserver.h"

#include "valuecache.h"
#include "mocks/valuecache/valuecache_mock.h"

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm,
		const DB_SERVICE *service, const char *tz, char **data, int macro_type, char *error,
		int maxerrlen);

int __wrap_DCget_data_expected_from(zbx_uint64_t itemid, int *seconds);

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm,
		const DB_SERVICE *service, const char *tz, char **data, int macro_type, char *error,
		int maxerrlen)
{
	ZBX_UNUSED(actionid);
	ZBX_UNUSED(event);
	ZBX_UNUSED(r_event);
	ZBX_UNUSED(userid);
	ZBX_UNUSED(hostid);
	ZBX_UNUSED(dc_host);
	ZBX_UNUSED(dc_item);
	ZBX_UNUSED(alert);
	ZBX_UNUSED(ack);
	ZBX_UNUSED(tz);
	ZBX_UNUSED(data);
	ZBX_UNUSED(macro_type);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);
	ZBX_UNUSED(service_alarm);
	ZBX_UNUSED(service);

	return SUCCEED;
}

int __wrap_DCget_data_expected_from(zbx_uint64_t itemid, int *seconds)
{
	ZBX_UNUSED(itemid);
	*seconds = zbx_vcmock_get_ts().sec - 600;
	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	const size_t		macro_pos = 1, macro_pos_end = 6, func_pos = 8, func_param_pos = 15;
	int			expected_ret, returned_ret, err;
	char			*returned_value = NULL, macro_expr[MAX_STRING_LEN];
	const char		*expected_value;
	zbx_token_func_macro_t	token;
	zbx_mock_handle_t	handle;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	handle = zbx_mock_get_parameter_handle("in");
	zbx_vcmock_set_time(handle, "time");

	zbx_snprintf(macro_expr, MAX_STRING_LEN, "{{TIME}.fmttime(%s, %s)}",
			zbx_mock_get_parameter_string("in.fmt"), zbx_mock_get_parameter_string("in.period"));

	token = (zbx_token_func_macro_t)
		{
			.macro		= { macro_pos, macro_pos_end },
			.func		= { func_pos, strlen(macro_expr) - 2 },
			.func_param	= { func_param_pos, strlen(macro_expr) - 2 }
		};

	returned_ret = zbx_calculate_macro_function(macro_expr, &token, &returned_value);
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		handle = zbx_mock_get_parameter_handle("out.value");

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string_ex(handle, &expected_value)))
			fail_msg("Cannot read output value: %s", zbx_mock_error_string(err));

		zbx_mock_assert_str_eq("fmttime result", expected_value, returned_value);
	}

	zbx_free(returned_value);
}
