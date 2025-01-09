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

void	zbx_mock_test_entry(void **state)
{
#define IPV4_MAX_LEN	16
#define IPV6_MAX_LEN	40
#define IPV4_ARR_ELM_COUNT	4
#define IPV6_ARR_ELM_COUNT	8
	unsigned char	type = (unsigned char)zbx_mock_get_parameter_uint64("in.type");
	char		*ip_str = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.ip"));
	char		ip_ipv4[IPV4_MAX_LEN], ip_ipv6[IPV6_MAX_LEN];
	int		ip[IPV6_ARR_ELM_COUNT];

	ZBX_UNUSED(state);

	if (ZBX_IPRANGE_V6 == type)
	{
		if (IPV6_ARR_ELM_COUNT == sscanf(ip_str, "%x:%x:%x:%x:%x:%x:%x:%x", (unsigned int *)&ip[0],
				(unsigned int *)&ip[1], (unsigned int *)&ip[2], (unsigned int *)&ip[3],
				(unsigned int *)&ip[4], (unsigned int *)&ip[5], (unsigned int *)&ip[6],
				(unsigned int *)&ip[7]))
		{
			zbx_iprange_ip2str(type, ip, ip_ipv6, IPV6_MAX_LEN);
			zbx_mock_assert_str_eq("return value ipv6", ip_str, ip_ipv6);
		}
	}
	else
	{
		if (IPV4_ARR_ELM_COUNT == sscanf(ip_str, "%d.%d.%d.%d", &ip[0], &ip[1], &ip[2], &ip[3]))
		{
			zbx_iprange_ip2str(type, ip, ip_ipv4, IPV4_MAX_LEN);
			zbx_mock_assert_str_eq("return value ipv4", ip_str, ip_ipv4);
		}
	}

	zbx_free(ip_str);
#undef IPV4_MAX_LEN
#undef IPV6_MAX_LEN
#undef IPV4_ARR_ELM_COUNT
#undef IPV6_ARR_ELM_COUNT
}
