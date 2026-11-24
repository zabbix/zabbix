/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcommon.h"
#include "zbxcommshigh.h"
#include "zbxjson.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*payload = zbx_mock_get_parameter_string("in.payload");
	int			exp_verify = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.verify")),
				has_blake3 = 0, ret;
	struct zbx_json_parse	jp;
	unsigned char		digest[ZBX_BLAKE3_OUT_LEN];
	char			hex[ZBX_BLAKE3_HEX_LEN], *error = NULL;

	ZBX_UNUSED(state);

	zbx_mock_assert_int_eq("zbx_json_open()", SUCCEED, zbx_json_open(payload, &jp));

	ret = zbx_proxy_batch_verify_blake3(&jp, digest, hex, &has_blake3, &error);
	zbx_mock_assert_int_eq("zbx_proxy_batch_verify_blake3()", exp_verify, ret);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.has_blake3"))
		zbx_mock_assert_int_eq("has_blake3", (int)zbx_mock_get_parameter_uint64("out.has_blake3"), has_blake3);

	zbx_free(error);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.ack"))
	{
		const char	*ack = zbx_mock_get_parameter_string("in.ack");
		const char	*expected_hex = zbx_mock_get_parameter_string("in.expected_hex");
		int		peer_proto = (int)zbx_mock_get_parameter_uint64("in.peer_protocol");
		int		exp_ack = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.ack_result"));

		ret = zbx_verify_batch_blake3_ack_ext(ack, peer_proto, expected_hex, &error);
		zbx_mock_assert_int_eq("zbx_verify_batch_blake3_ack_ext()", exp_ack, ret);
		zbx_free(error);
	}
}
