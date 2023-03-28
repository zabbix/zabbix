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

#include "../../../src/libs/zbxicmpping/icmpping.c"

static const char	*get_source_ip(void)
{
	return "NotNull";
}

void	test_process_fping_statistics_line()
{
	const size_t	hosts_count = 1, host_idx = 0;
	char		*linebuf, host_up;
	int		i, requests_count;
	unsigned char	allow_redirect;
	ZBX_FPING_HOST	*hosts;
#ifdef HAVE_IPV6
	int 		fping_existence;
#endif
	hosts = (ZBX_FPING_HOST *)calloc(hosts_count, sizeof(ZBX_FPING_HOST));

	linebuf = strdup(zbx_mock_get_parameter_string("in.linebuf"));
	requests_count = (int)zbx_mock_get_parameter_uint64("in.requests_count");
	allow_redirect = (unsigned char)zbx_mock_get_parameter_uint64("in.allow_redirect");
#ifdef HAVE_IPV6
	fping_existence = (int)zbx_mock_get_parameter_uint64("in.fping_existence");
#endif
	host_up = (char)zbx_mock_get_parameter_uint64("in.host_up");

	hosts[host_idx].addr = strdup(zbx_mock_get_parameter_string("in.ipaddr"));
	hosts[host_idx].status = (char *)malloc(requests_count * sizeof(char));
	for (i = 0; i < requests_count; i++)
		hosts[host_idx].status[i] = (char)host_up;

#ifdef HAVE_IPV6
	process_fping_output_line(linebuf, hosts, hosts_count, requests_count, allow_redirect, fping_existence);
#else
	process_fping_output_line(linebuf, hosts, hosts_count, requests_count, allow_redirect);
#endif

	zbx_mock_assert_double_eq("min", zbx_mock_get_parameter_float("out.min"), hosts[host_idx].min);
	zbx_mock_assert_double_eq("sum", zbx_mock_get_parameter_float("out.sum"), hosts[host_idx].sum);
	zbx_mock_assert_double_eq("max", zbx_mock_get_parameter_float("out.max"), hosts[host_idx].max);
	zbx_mock_assert_int_eq("rcv", (int)zbx_mock_get_parameter_uint64("out.rcv"), hosts[host_idx].rcv);
	zbx_mock_assert_int_eq("cnt", requests_count, hosts[host_idx].cnt);

	free(hosts[host_idx].addr);
	free(hosts[host_idx].status);
	free(hosts);
	free(linebuf);
}

void test_process_response_to_individual_fping_request()
{
	const size_t	hosts_count = 1, host_idx = 0;
	char		*linebuf;
	int		i, requests_count, host_up;
	unsigned char	allow_redirect;
	ZBX_FPING_HOST	*hosts;
#ifdef HAVE_IPV6
	int 		fping_existence;
#endif
	hosts = (ZBX_FPING_HOST *)calloc(hosts_count, sizeof(ZBX_FPING_HOST));

	linebuf = strdup(zbx_mock_get_parameter_string("in.linebuf"));
	requests_count = (int)zbx_mock_get_parameter_uint64("in.requests_count");
	allow_redirect = (unsigned char)zbx_mock_get_parameter_uint64("in.allow_redirect");
#ifdef HAVE_IPV6
	fping_existence = (int)zbx_mock_get_parameter_uint64("in.fping_existence");
#endif
	host_up = (int)zbx_mock_get_parameter_uint64("out.host_up");

	hosts[host_idx].addr = strdup(zbx_mock_get_parameter_string("in.ipaddr"));
	hosts[host_idx].status = (char *)calloc(requests_count, sizeof(char));

#ifdef HAVE_IPV6
	process_fping_output_line(linebuf, hosts, hosts_count, requests_count, allow_redirect, fping_existence);
#else
	process_fping_output_line(linebuf, hosts, hosts_count, requests_count, allow_redirect);
#endif

	for (i = 0; i < requests_count; i++)
		zbx_mock_assert_int_eq("host up", host_up, (int)(hosts[host_idx].status[i]));

	free(hosts[host_idx].addr);
	free(hosts[host_idx].status);
	free(hosts);
	free(linebuf);
}

void	zbx_mock_test_entry(void **state)
{
	const char	*test_type;
	static zbx_config_icmpping_t	config_icmpping = {
		get_source_ip,
		NULL,
#ifdef HAVE_IPV6
		NULL,
#endif
		NULL};

	ZBX_UNUSED(state);

	zbx_init_library_icmpping(&config_icmpping);

	test_type = zbx_mock_get_parameter_string("in.test_type");

	if (0 == strcmp("test_process_fping_statistics_line", test_type))
		test_process_fping_statistics_line();
	else if (0 == strcmp("process_response_to_individual_fping_request", test_type))
		test_process_response_to_individual_fping_request();
	else
		fail_msg("This should never happen: undefined test type.");
}
