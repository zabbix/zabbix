/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "zbxmockutil.h"

#include "comms.h"
#include "sysinfo.h"
#include "../../../../src/libs/zbxsysinfo/common/http.h"

static const char	*http_req;
static size_t		http_len;

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT 	param_result;
	int		expected_result, actual_result;
	const char	*buffer, *init_param;
	char		*rvalue;

	ZBX_UNUSED(state);

	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	buffer = zbx_mock_get_parameter_string("out.req");

	init_request(&request);
	init_result(&param_result);
	init_param = zbx_mock_get_parameter_string("in.key");

	if (SUCCEED != parse_item_key(init_param, &request))
		fail_msg("Cannot parse item key: %s", init_param);

	if (expected_result != (actual_result = WEB_PAGE_GET(&request,&param_result)))
	{
		fail_msg("Got %s instead of %s as a result.", zbx_sysinfo_ret_string(actual_result),
			zbx_sysinfo_ret_string(expected_result));
	}

	if (SYSINFO_RET_FAIL == expected_result)
		rvalue = (NULL != GET_MSG_RESULT(&param_result)) ? *GET_MSG_RESULT(&param_result) : NULL;
	else
		rvalue = (NULL != GET_TEXT_RESULT(&param_result)) ? *GET_TEXT_RESULT(&param_result) : NULL;

	if (NULL == rvalue)
		fail_msg("Got 'NULL' response.");

	if (SYSINFO_RET_OK == expected_result)
		rvalue[strlen(rvalue) - 1] = '\0'; /* remove last char from __wrap_zbx_tcp_recv_raw_ext */

	if (0 != strcmp(buffer, rvalue))
		fail_msg("Got '%s' instead of '%s' as a value.", rvalue, buffer);

	free_request(&request);
	free_result(&param_result);
}


int	__wrap_zbx_tcp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port,
		int timeout, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2)
{
	return SUCCEED;
}

int	__wrap_zbx_tcp_send_ext(zbx_socket_t *s, const char *data, size_t len, unsigned char flags, int timeout)
{
	http_req = data;
	http_len = len;
	return SUCCEED;
}

ssize_t	__wrap_zbx_tcp_recv_raw_ext(zbx_socket_t *s, int timeout)
{
	s->buffer = (char *)http_req;
	s->buffer[http_len++] = '*'; /* workaround for zbx_rtrim */
	s->buffer[http_len++] = '\0';
	return http_len;
}

void	__wrap_zbx_tcp_close(zbx_socket_t *s)
{
}
