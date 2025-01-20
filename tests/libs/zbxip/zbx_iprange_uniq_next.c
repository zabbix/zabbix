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

#include "zbxdbhigh.h"
#include "zbxip.h"

#include "zbx_ip_common.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_iprange_t		iprange[2];
	zbx_vector_str_t	ip_result, ip_out;
	const int		num = zbx_mock_get_parameter_int("in.num");
	const char		*first_range = zbx_mock_get_parameter_string("in.first_range");
	const char		*second_range = zbx_mock_get_parameter_string("in.second_range");
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	ZBX_UNUSED(state);

	if (SUCCEED != zbx_iprange_parse(&iprange[0], first_range))
		fail_msg("failed to parse iprange");

	if (1 < num && SUCCEED != zbx_iprange_parse(&iprange[1], second_range))
		fail_msg("failed to parse iprange");

	zbx_vector_str_create(&ip_out);
	zbx_mock_extract_yaml_values_str("out.ip", &ip_out);

	char		ip[ZBX_INTERFACE_IP_LEN_MAX];

	*ip = '\0';
	zbx_vector_str_create(&ip_result);

	while (SUCCEED == zbx_iprange_uniq_next(iprange, num, ip, sizeof(ip)))
	{
		zbx_vector_str_append(&ip_result, zbx_strdup(NULL, ip));
	}

	zbx_mock_assert_int_eq("return value", exp_result, compare_str_vectors(&ip_result, &ip_out));

	zbx_vector_str_clear_ext(&ip_result, zbx_str_free);
	zbx_vector_str_clear_ext(&ip_out, zbx_str_free);
	zbx_vector_str_destroy(&ip_result);
	zbx_vector_str_destroy(&ip_out);
}
