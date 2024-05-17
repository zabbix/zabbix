/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
#include "zbxmockhelper.h"
#include "zbxmockutil.h"
#include "zbxdbhigh.h"

#include "../../../src/libs/zbxicmpping/icmpping.c"

#define PIPE_BUFFER_SIZE	4096

char	*__wrap_zbx_fgets(char *buffer, int size, FILE *fp);
int	__wrap_zbx_execute(const char *command, char **output, char *error, size_t max_error_len, int timeout,
		unsigned char flag, const char *dir);

char	*__wrap_zbx_fgets(char *buffer, int size, FILE *fp)
{
	ZBX_UNUSED(buffer);
	ZBX_UNUSED(size);
	ZBX_UNUSED(fp);

	return "";
}

int	__wrap_zbx_execute(const char *command, char **output, char *error, size_t max_error_len, int timeout,
		unsigned char flag, const char *dir)
{
	int		ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("in.zbx_execute_ret"));
	const char	*str;

	ZBX_UNUSED(timeout);
	ZBX_UNUSED(flag);
	ZBX_UNUSED(dir);

	if (NULL != output)
		zbx_free(*output);

	*output = (char *)zbx_malloc(*output, PIPE_BUFFER_SIZE);

	switch (ret)
	{
		case SUCCEED:
			*error = '\0';
			if (NULL != strstr(command, "-i0"))
				str = zbx_mock_get_parameter_string("in.fping_out_i0");
			else if (NULL != strstr(command, "-i1"))
				str = zbx_mock_get_parameter_string("in.fping_out_i1");
			else if (NULL != strstr(command, "-i10"))
				str = zbx_mock_get_parameter_string("in.fping_out_i10");
			else
				fail_msg("This should never happen: unknown interval.");
			zbx_strlcpy(*output, str, PIPE_BUFFER_SIZE);
			break;
		case FAIL:
			zbx_snprintf(error, max_error_len, "General failure error.");
			break;
		case TIMEOUT_ERROR:
			zbx_snprintf(error, max_error_len, "Timeout error.");
			break;
		case SIG_ERROR:
			zbx_snprintf(error, max_error_len, "Signal received while executing a shell script.");
			break;
		default:
			fail_msg("This should never happen: unexpected return code in %s().", __func__);
	}

	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	const int	hosts_cnt = 1;

	int		value, ret;
	ZBX_FPING_HOST	hosts[hosts_cnt];
	char 		error[ZBX_ITEM_ERROR_LEN_MAX];
	char		status[1];

	ZBX_UNUSED(state);

	error[0] = '\0';
	status[0] = '\0';

	hosts[0].addr = (char *)zbx_mock_get_parameter_string("in.target_host_addr");
	hosts[0].min = 0.0;
	hosts[0].sum = 0.0;
	hosts[0].max = 0.0;
	hosts[0].rcv = 0;
	hosts[0].cnt = 0;
	hosts[0].status = status;

	ret = get_interval_option("/usr/bin/fping", hosts, 1, &value, error, ZBX_ITEM_ERROR_LEN_MAX);

	zbx_mock_assert_int_eq("get_interval_option() return value",
			zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return")), ret);
	zbx_mock_assert_str_eq("error message returned by get_interval_option()",
			zbx_mock_get_parameter_string("out.error_msg"), error);

	if (SUCCEED == ret)
	{
		zbx_mock_assert_int_eq("minimal detected interval", (int)zbx_mock_get_parameter_uint64("out.value"),
				value);
	}
}
