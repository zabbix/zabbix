/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "zbxmockutil.h"
#include "zbxmockhelper.h"

#include "common.h"
#include "comms.h"

static void	mock_accept(zbx_socket_t *s)
{
	void		*buf;
	const char	*peer;

	switch (s->peer_info.ss_family = zbx_mock_str_to_family(zbx_mock_get_parameter_string("in.family")))
	{
		case AF_INET:
			buf = &((struct sockaddr_in *)&s->peer_info)->sin_addr.s_addr;
			break;
		case AF_INET6:
			buf = ((struct sockaddr_in6 *)&s->peer_info)->sin6_addr.s6_addr;
			break;
		default:
			fail_msg("Unexpected family");
			return;
	}

	if (1 != inet_pton(s->peer_info.ss_family, (peer = zbx_mock_get_parameter_string("in.peer")), buf))
		fail_msg("failed converting address '%s' from textual to binary", zbx_mock_get_parameter_string("in.peer"));

	zbx_strlcpy(s->peer, peer, sizeof(s->peer));
}

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t	s;

	ZBX_UNUSED(state);

	mock_accept(&s);

	zbx_mock_assert_result_eq("zbx_tcp_check_allowed_peers() return code",
			zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return")),
			zbx_tcp_check_allowed_peers(&s, zbx_mock_get_parameter_string("in.allowed_peers")));
}
