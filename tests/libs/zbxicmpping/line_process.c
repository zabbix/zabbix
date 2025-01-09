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

#include "../../../src/libs/zbxicmpping/icmpping.c"

const char	*mock_get_source_ip(void);
void		test_process_fping_statistics_line(void);
void		test_process_response_to_individual_fping_request(void);
char		*__wrap_zbx_fgets(char *buffer, int size, FILE *fp);

const char	*mock_get_source_ip(void)
{
	return "NotNull";
}

char	*__wrap_zbx_fgets(char *buffer, int size, FILE *fp)
{
	ZBX_UNUSED(buffer);
	ZBX_UNUSED(size);
	ZBX_UNUSED(fp);

	return "";
}

void	test_process_fping_statistics_line(void)
{
	const size_t	hosts_count = 1, host_idx = 0;
	int		i;
	char		host_up;
	zbx_fping_args	fping_args;
	zbx_fping_resp	fping_resp;

	memset(&fping_args, 0, sizeof(fping_args));
	memset(&fping_resp, 0, sizeof(fping_resp));

	host_up = (char)zbx_mock_get_parameter_uint64("in.host_up");

	fping_resp.linebuf = strdup(zbx_mock_get_parameter_string("in.linebuf"));

	fping_args.hosts = (zbx_fping_host_t *)calloc(hosts_count, sizeof(zbx_fping_host_t));
	fping_args.hosts_count = (int)hosts_count;
	fping_args.requests_count = (int)zbx_mock_get_parameter_uint64("in.requests_count");
	fping_args.allow_redirect = (unsigned char)zbx_mock_get_parameter_uint64("in.allow_redirect");
	fping_args.rdns = 0;
#ifdef HAVE_IPV6
	fping_args.fping_existence = (int)zbx_mock_get_parameter_uint64("in.fping_existence");
#endif

	fping_args.hosts[host_idx].addr = strdup(zbx_mock_get_parameter_string("in.ipaddr"));
	fping_args.hosts[host_idx].status = (char *)malloc((size_t)fping_args.requests_count * sizeof(char));
	for (i = 0; i < fping_args.requests_count; i++)
		fping_args.hosts[host_idx].status[i] = host_up;

	line_process(&fping_resp, &fping_args);

	zbx_mock_assert_double_eq("min", zbx_mock_get_parameter_float("out.min"), fping_args.hosts[host_idx].min);
	zbx_mock_assert_double_eq("sum", zbx_mock_get_parameter_float("out.sum"), fping_args.hosts[host_idx].sum);
	zbx_mock_assert_double_eq("max", zbx_mock_get_parameter_float("out.max"), fping_args.hosts[host_idx].max);
	zbx_mock_assert_int_eq("rcv", (int)zbx_mock_get_parameter_uint64("out.rcv"), fping_args.hosts[host_idx].rcv);
	zbx_mock_assert_int_eq("cnt", fping_args.requests_count, fping_args.hosts[host_idx].cnt);

	free(fping_args.hosts[host_idx].addr);
	free(fping_args.hosts[host_idx].status);
	free(fping_args.hosts);
	free(fping_resp.linebuf);
}

void test_process_response_to_individual_fping_request(void)
{
	const size_t	hosts_count = 1, host_idx = 0;
	int		i;
	char		host_up;
	zbx_fping_args	fping_args;
	zbx_fping_resp	fping_resp;

	memset(&fping_args, 0, sizeof(fping_args));
	memset(&fping_resp, 0, sizeof(fping_resp));

	host_up = (char)zbx_mock_get_parameter_uint64("out.host_up");

	fping_resp.linebuf = strdup(zbx_mock_get_parameter_string("in.linebuf"));

	fping_args.hosts = (zbx_fping_host_t *)calloc(hosts_count, sizeof(zbx_fping_host_t));
	fping_args.hosts_count = (int)hosts_count;
	fping_args.requests_count = (int)zbx_mock_get_parameter_uint64("in.requests_count");
	fping_args.allow_redirect = (unsigned char)zbx_mock_get_parameter_uint64("in.allow_redirect");
	fping_args.rdns = 0;
#ifdef HAVE_IPV6
	fping_args.fping_existence = (int)zbx_mock_get_parameter_uint64("in.fping_existence");
#endif
	fping_args.hosts[host_idx].addr = strdup(zbx_mock_get_parameter_string("in.ipaddr"));
	fping_args.hosts[host_idx].status = (char *)calloc(fping_args.requests_count, sizeof(char));

	line_process(&fping_resp, &fping_args);

	for (i = 0; i < fping_args.requests_count; i++)
		zbx_mock_assert_int_eq("host up", (int)host_up, (int)(fping_args.hosts[host_idx].status[i]));

	free(fping_args.hosts[host_idx].addr);
	free(fping_args.hosts[host_idx].status);
	free(fping_args.hosts);
	free(fping_resp.linebuf);
}

void	zbx_mock_test_entry(void **state)
{
	const char	*test_type;
	static zbx_config_icmpping_t	mock_config_icmpping = {
		mock_get_source_ip,
		NULL,
		NULL,
		NULL,
		NULL};

	ZBX_UNUSED(state);

	zbx_init_library_icmpping(&mock_config_icmpping);

	test_type = zbx_mock_get_parameter_string("in.test_type");

	if (0 == strcmp("test_process_fping_statistics_line", test_type))
		test_process_fping_statistics_line();
	else if (0 == strcmp("process_response_to_individual_fping_request", test_type))
		test_process_response_to_individual_fping_request();
	else
		fail_msg("This should never happen: undefined test type.");
}
