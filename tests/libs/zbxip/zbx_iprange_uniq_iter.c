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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxip.h"

#include "zbx_ip_common.h"

void	zbx_mock_test_entry(void **state)
{
#define IP_LEN_MAX	40
#define IPV4_ARR_ELM_COUNT	4
#define IPV6_ARR_ELM_COUNT	8
	zbx_iprange_t		iprange[2];
	zbx_vector_str_t	ip_result, ip_out;
	int			num = zbx_mock_get_parameter_int("in.num");
	int			idx = zbx_mock_get_parameter_int("in.idx");
	int			type = zbx_mock_get_parameter_int("in.type");
	const char		*range1 = zbx_mock_get_parameter_string("in.range1");
	const char		*range2 = zbx_mock_get_parameter_string("in.range2");
	const char		*address = zbx_mock_get_parameter_string("in.address");

	ZBX_UNUSED(state);

	if (SUCCEED != zbx_iprange_parse(&iprange[0], range1))
		fail_msg("failed to parse iprange1");

	if (SUCCEED != zbx_iprange_parse(&iprange[1], range2))
		fail_msg("failed to parse iprange2");

	zbx_vector_str_create(&ip_out);
	zbx_mock_extract_yaml_values_str("out.ip", &ip_out);

	int			ip[IPV6_ARR_ELM_COUNT];

	if (ZBX_IPRANGE_V6 == type)
	{
		if (IPV6_ARR_ELM_COUNT != sscanf(address, "%x:%x:%x:%x:%x:%x:%x:%x", (unsigned int *)&ip[0],
				(unsigned int *)&ip[1], (unsigned int *)&ip[2], (unsigned int *)&ip[3],
				(unsigned int *)&ip[4], (unsigned int *)&ip[5], (unsigned int *)&ip[6],
				(unsigned int *)&ip[7]))
			fail_msg("failed to read ipv6");
	}
	else
	{
		if (IPV4_ARR_ELM_COUNT != sscanf(address, "%d.%d.%d.%d", &ip[0], &ip[1], &ip[2], &ip[3]))
			fail_msg("failed to read ipv4");
	}

	char			ip_str[IP_LEN_MAX];
	zbx_vector_str_create(&ip_result);

	while (SUCCEED == zbx_iprange_uniq_iter(iprange, num, &idx, ip))
	{
		zbx_iprange_ip2str(type, ip, ip_str, IP_LEN_MAX);
		zbx_vector_str_append(&ip_result, zbx_strdup(NULL, ip_str));
	}

	zbx_mock_assert_int_eq("return value", SUCCEED, compare_str_vectors(&ip_result, &ip_out));

	zbx_vector_str_clear_ext(&ip_result, zbx_str_free);
	zbx_vector_str_clear_ext(&ip_out, zbx_str_free);
	zbx_vector_str_destroy(&ip_result);
	zbx_vector_str_destroy(&ip_out);
#undef IP_LEN_MAX
#undef IPV4_ARR_ELM_COUNT
#undef IPV6_ARR_ELM_COUNT
}
