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

#include "zbxcomms.h"
#include "zbxsysinfo.h"
#include "../../../../src/libs/zbxsysinfo/sysinfo.h"
#include "../../../../src/libs/zbxsysinfo/common/http.h"

#ifndef HAVE_LIBCURL

#define STR_TEST_TYPE	"legacy"
#define STR_FIELD_OUT	"out.req"

static const char	*http_req;
static size_t		http_len;

int	__wrap_zbx_tcp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port,
		int timeout, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2);
int	__wrap_zbx_tcp_send_ext(zbx_socket_t *s, const char *data, size_t len, unsigned char flags, int timeout);
ssize_t	__wrap_zbx_tcp_recv_raw_ext(zbx_socket_t *s, int timeout);
void	__wrap_zbx_tcp_close(zbx_socket_t *s);

#else

#define STR_TEST_TYPE	"libcurl"
#define STR_FIELD_OUT	"out.url"

static void	*page_data = NULL;
static size_t	(*cb_ptr)(void *ptr, size_t size, size_t nmemb, void *userdata);
static char	*req_url = NULL;
static int	dummy;

CURL		*__wrap_curl_easy_init(void);
CURLcode	__wrap_curl_easy_setopt(CURL *easyhandle, int opt, void *val);
CURLcode	__wrap_curl_easy_perform(CURL *easyhandle);
void		__wrap_curl_easy_cleanup(CURL *easyhandle);

#endif

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT 	param_result;
	int		expected_result, actual_result;
	const char	*buffer, *init_param, *test_type;
	char		*rvalue;

	ZBX_UNUSED(state);

	test_type = zbx_mock_get_parameter_string("in.test_type");

	if (0 != strcmp(test_type, "both") && 0 != strcmp(test_type, STR_TEST_TYPE))
		skip();

	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	zbx_init_agent_request(&request);
	zbx_init_agent_result(&param_result);

	zbx_init_library_sysinfo(get_zbx_config_timeout, NULL, NULL, NULL, get_zbx_config_source_ip, NULL, NULL, NULL,
			NULL, NULL);

	init_param = zbx_mock_get_parameter_string("in.key");

	if (SUCCEED != zbx_parse_item_key(init_param, &request))
		fail_msg("Cannot parse item key: %s", init_param);

	if (expected_result != (actual_result = web_page_get(&request, &param_result)))
	{
		fail_msg("Got %s instead of %s as a result.", zbx_sysinfo_ret_string(actual_result),
			zbx_sysinfo_ret_string(expected_result));
	}

	if (SYSINFO_RET_FAIL == expected_result)
	{
		buffer = zbx_mock_get_parameter_string("out.error");
		rvalue = (NULL != ZBX_GET_MSG_RESULT(&param_result)) ? *ZBX_GET_MSG_RESULT(&param_result) : NULL;
	}
	else
	{
		buffer = zbx_mock_get_parameter_string(STR_FIELD_OUT);
		rvalue = (NULL != ZBX_GET_TEXT_RESULT(&param_result)) ? *ZBX_GET_TEXT_RESULT(&param_result) : NULL;
	}

	if (NULL == rvalue)
		fail_msg("Got 'NULL' response.");

#ifndef HAVE_LIBCURL
	if (SYSINFO_RET_OK == expected_result)
		rvalue[strlen(rvalue) - 1] = '\0'; /* remove last char from __wrap_zbx_tcp_recv_raw_ext */
#endif

	if (0 != strcmp(buffer, rvalue))
		fail_msg("Got '%s' instead of '%s' as a value.", rvalue, buffer);

	zbx_free_agent_request(&request);
	zbx_free_agent_result(&param_result);
}

#ifdef HAVE_LIBCURL
CURL	*__wrap_curl_easy_init(void)
{
	return (CURL*)&dummy;
}

CURLcode	__wrap_curl_easy_setopt(CURL *easyhandle, int opt, void *val)
{
	ZBX_UNUSED(easyhandle);

	switch (opt)
	{
		case CURLOPT_URL:
			req_url = zbx_strdup(req_url, (char*)val);
			break;
		case CURLOPT_WRITEFUNCTION:
			*(void **)(&cb_ptr) = val;
			break;
		case CURLOPT_WRITEDATA:
			page_data = val;
			break;
	}

	return CURLE_OK;
}

CURLcode	__wrap_curl_easy_perform(CURL *easyhandle)
{
	ZBX_UNUSED(easyhandle);

	cb_ptr(req_url, 1, strlen(req_url), page_data);
	zbx_free(req_url);

	return CURLE_OK;
}

void	__wrap_curl_easy_cleanup(CURL *easyhandle)
{
	ZBX_UNUSED(easyhandle);

	return;
}
#else
int	__wrap_zbx_tcp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port,
		int timeout, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2)
{
	ZBX_UNUSED(s);
	ZBX_UNUSED(source_ip);
	ZBX_UNUSED(ip);
	ZBX_UNUSED(port);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(tls_connect);
	ZBX_UNUSED(tls_arg1);
	ZBX_UNUSED(tls_arg2);

	return SUCCEED;
}

int	__wrap_zbx_tcp_send_ext(zbx_socket_t *s, const char *data, size_t len, unsigned char flags, int timeout)
{
	ZBX_UNUSED(s);
	ZBX_UNUSED(flags);
	ZBX_UNUSED(timeout);

	http_req = data;
	http_len = len;
	return SUCCEED;
}

ssize_t	__wrap_zbx_tcp_recv_raw_ext(zbx_socket_t *s, int timeout)
{
	ZBX_UNUSED(s);
	ZBX_UNUSED(timeout);

	s->buffer = (char *)http_req;
	s->buffer[http_len++] = '*'; /* workaround for zbx_rtrim */
	s->buffer[http_len++] = '\0';
	return http_len;
}

void	__wrap_zbx_tcp_close(zbx_socket_t *s)
{
	ZBX_UNUSED(s);
}
#endif
