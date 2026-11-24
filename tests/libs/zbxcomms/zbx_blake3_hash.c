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
#include "zbxhash.h"

void	zbx_mock_test_entry(void **state)
{
	unsigned char	digest[ZBX_BLAKE3_OUT_LEN], decoded[ZBX_BLAKE3_OUT_LEN];
	char		hex[ZBX_BLAKE3_HEX_LEN];

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.pattern_len"))
	{
		size_t		len = (size_t)zbx_mock_get_parameter_uint64("in.pattern_len"), i;
		unsigned char	*buf = (unsigned char *)zbx_malloc(NULL, 0 < len ? len : 1);

		for (i = 0; i < len; i++)
			buf[i] = (unsigned char)(i % 251);

		zbx_blake3_hash(buf, len, digest);
		zbx_free(buf);
	}
	else
	{
		const char	*input = zbx_mock_get_parameter_string("in.data");

		zbx_blake3_hash(input, strlen(input), digest);
	}

	zbx_blake3_buf2hex(digest, hex);
	zbx_mock_assert_str_eq("BLAKE3 hex digest", zbx_mock_get_parameter_string("out.hex"), hex);
	zbx_mock_assert_int_eq("hex2buf decode", SUCCEED, zbx_blake3_hex2buf(hex, decoded));
	zbx_mock_assert_int_eq("decoded digest match", 0, memcmp(digest, decoded, ZBX_BLAKE3_OUT_LEN));
}
