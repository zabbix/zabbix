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
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxcomms.h"
#include "event.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t		s;
	int			result, timeout = zbx_mock_get_parameter_int("in.timeout"),
				exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	short			event;
	zbx_vector_int32_t	read_return_seq;

	ZBX_UNUSED(state);

	set_test_comms(SUCCEED);
	zbx_vector_int32_create(&read_return_seq);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		s.tls_ctx = NULL;
#endif

	zbx_mock_extract_yaml_values_int32(zbx_mock_get_parameter_handle("in.read_return"), &read_return_seq);
	setup_read(&read_return_seq);

	if (SUCCEED == zbx_mock_parameter_exists("in.event_is_null"))
		result = zbx_tcp_read_close_notify(&s, timeout, NULL);
	else
	{
		event = EV_TIMEOUT;
		result = zbx_tcp_read_close_notify(&s, timeout, &event);
	}

	zbx_mock_assert_int_eq("return value: ", exp_result, result);

	zbx_vector_int32_clear(&read_return_seq);
	zbx_vector_int32_destroy(&read_return_seq);

}
