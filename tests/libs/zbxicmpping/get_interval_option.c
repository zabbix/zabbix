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
#include "zbxmockhelper.h"
#include "zbxmockutil.h"
#include "zbxdbhigh.h"

#include "../../../src/libs/zbxicmpping/icmpping.c"

int	__wrap_mkstemp(void);
FILE	*__wrap_popen(const char *command, const char *type);
ssize_t	__wrap_write(int fd, const void *buf, size_t n);
const char	*mock_get_tmpdir(void);
const char	*mock_get_progname(void);

int	__wrap_mkstemp(void)
{
	return INT_MAX;
}

FILE *__wrap_popen(const char *command, const char *type)
{
	const char	*str = NULL;
	size_t		f_size;

	ZBX_UNUSED(type);

	if (NULL != strstr(command, "-i0"))
		str = zbx_mock_get_parameter_string("in.fping_out_i0");
	else if (NULL != strstr(command, "-i1"))
		str = zbx_mock_get_parameter_string("in.fping_out_i1");
	else if (NULL != strstr(command, "-i10"))
		str = zbx_mock_get_parameter_string("in.fping_out_i10");
	else
		fail_msg("This should never happen: unexpected command '%s' in %s().", command, __func__);

	f_size = strlen(str);

	return fmemopen((void *)str, f_size * sizeof(char), "r");
}

ssize_t	__wrap_write(int fd, const void *buf, size_t n)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(buf);

	return n;
}

const char	*mock_get_tmpdir(void)
{
	return "";
}

const char	*mock_get_progname(void)
{
	return "";
}

void	zbx_mock_test_entry(void **state)
{
	const int	hosts_cnt = 1;

	int			value, ret;
	zbx_fping_host_t	hosts[hosts_cnt];
	char 			error[ZBX_ITEM_ERROR_LEN_MAX];
	char			status[1];

	static zbx_config_icmpping_t	mock_config_icmpping = {
		NULL,
		NULL,
		NULL,
		mock_get_tmpdir,
		mock_get_progname};

	ZBX_UNUSED(state);

	zbx_init_library_icmpping(&mock_config_icmpping);

	error[0] = '\0';
	status[0] = '\0';

	memset(hosts, 0, sizeof(zbx_fping_host_t) * hosts_cnt);

	hosts[0].addr = (char *)zbx_mock_get_parameter_string("in.target_host_addr");
	hosts[0].status = status;

	ret = get_interval_option("/usr/bin/fping", hosts, hosts_cnt, &value, error, ZBX_ITEM_ERROR_LEN_MAX);

	zbx_mock_assert_int_eq("get_interval_option() return value", zbx_mock_str_to_return_code("SUCCEED"), ret);
	zbx_mock_assert_str_eq("error message returned by get_interval_option()", "", error);
	zbx_mock_assert_int_eq("minimal detected interval", (int)zbx_mock_get_parameter_uint64("out.value"), value);
}
