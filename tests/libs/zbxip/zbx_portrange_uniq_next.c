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

static int	parse_port_range(const char *range_str, zbx_range_t *port_range)
{
	if (NULL != strchr(range_str, '-'))
	{
		if (2 != sscanf(range_str, "%d-%d", &port_range->from, &port_range->to))
		{
			return FAIL;
		}
	}
	else
	{
		if (1 != sscanf(range_str, "%d", &port_range->from))
		{
			return FAIL;
		}
		port_range->to = port_range->from;
	}

	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_range_t		portrange[2];
	zbx_vector_str_t	port_result, port_out;
	const int		num = zbx_mock_get_parameter_int("in.num");
	const char		*range = zbx_mock_get_parameter_string("in.range");
	const char		*range1 = zbx_mock_get_parameter_string("in.range1");

	ZBX_UNUSED(state);

	if (SUCCEED != parse_port_range(range, &portrange[0]))
		fail_msg("failed to parse portrange");

	if (1 < num && SUCCEED != parse_port_range(range1, &portrange[1]))
		fail_msg("failed to parse portrange");

	zbx_vector_str_create(&port_out);
	zbx_mock_extract_yaml_values_str("out.port", &port_out);

	int		port = ZBX_PORTRANGE_INIT_PORT;
	char		str[16];

	zbx_vector_str_create(&port_result);

	while (SUCCEED == zbx_portrange_uniq_next(portrange, num, &port))
	{
		zbx_snprintf(str, sizeof(str), "%d", port);
		zbx_vector_str_append(&port_result, zbx_strdup(NULL, str));
	}

	zbx_mock_assert_int_eq("return value", SUCCEED, compare_str_vectors(&port_result, &port_out));

	zbx_vector_str_clear_ext(&port_result, zbx_str_free);
	zbx_vector_str_clear_ext(&port_out, zbx_str_free);
	zbx_vector_str_destroy(&port_result);
	zbx_vector_str_destroy(&port_out);
}
