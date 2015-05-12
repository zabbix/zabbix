/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include "common.h"
#include "sysinfo.h"
#include "zbxregexp.h"

#include "log.h"
#include "comms.h"
#include "cfg.h"

#include "http.h"

#define ZBX_MAX_WEBPAGE_SIZE	(1 * 1024 * 1024)

static int	get_http_page(const char *host, const char *path, unsigned short port, char *buffer, size_t max_buffer_len)
{
	int		ret;
	char		request[MAX_STRING_LEN];
	zbx_socket_t	s;

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, CONFIG_TIMEOUT)))
	{
		zbx_snprintf(request, sizeof(request),
				"GET /%s HTTP/1.1\r\n"
				"Host: %s\r\n"
				"Connection: close\r\n"
				"\r\n",
				path, host);

		if (SUCCEED == (ret = zbx_tcp_send_raw(&s, request)))
		{
			if (SUCCEED == (ret = SUCCEED_OR_FAIL(zbx_tcp_recv_ext(&s, ZBX_TCP_READ_UNTIL_CLOSE, 0))))
			{
				if (NULL != buffer)
					zbx_strlcpy(buffer, s.buffer, max_buffer_len);
			}
		}

		zbx_tcp_close(&s);
	}

	if (FAIL == ret)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "HTTP get error: %s", zbx_socket_strerror());
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	WEB_PAGE_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*hostname, *path_str, *port_str, buffer[MAX_BUFFER_LEN], path[MAX_STRING_LEN];
	unsigned short	port_number;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	hostname = get_rparam(request, 0);
	path_str = get_rparam(request, 1);
	port_str = get_rparam(request, 2);

	if (NULL == hostname || '\0' == *hostname)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == path_str)
		*path = '\0';
	else
		strscpy(path, path_str);

	if (NULL == port_str || '\0' == *port_str)
		port_number = ZBX_DEFAULT_HTTP_PORT;
	else if (FAIL == is_ushort(port_str, &port_number))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_OK == get_http_page(hostname, path, port_number, buffer, sizeof(buffer)))
	{
		zbx_rtrim(buffer, "\r\n");
		SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));
	}
	else
		SET_TEXT_RESULT(result, zbx_strdup(NULL, ""));

	return SYSINFO_RET_OK;
}

int	WEB_PAGE_PERF(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*hostname, path[MAX_STRING_LEN], *port_str, *path_str;
	double		start_time;
	unsigned short	port_number;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	hostname = get_rparam(request, 0);
	path_str = get_rparam(request, 1);
	port_str = get_rparam(request, 2);

	if (NULL == hostname || '\0' == *hostname)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == path_str || '\0' == *path_str)
		*path = '\0';
	else
		strscpy(path, path_str);

	if (NULL == port_str || '\0' == *port_str)
		port_number = ZBX_DEFAULT_HTTP_PORT;
	else if (FAIL == is_ushort(port_str, &port_number))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	start_time = zbx_time();

	if (SYSINFO_RET_OK == get_http_page(hostname, path, port_number, NULL, 0))
		SET_DBL_RESULT(result, zbx_time() - start_time);
	else
		SET_DBL_RESULT(result, 0.0);

	return SYSINFO_RET_OK;
}

int	WEB_PAGE_REGEXP(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*hostname, *path_str, *port_str, *regexp, *length_str, path[MAX_STRING_LEN],
			*buffer = NULL, *ptr = NULL, *str, *newline;
	int		length;
	const char	*output;
	unsigned short	port_number;

	if (6 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (4 > request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		return SYSINFO_RET_FAIL;
	}

	hostname = get_rparam(request, 0);
	path_str = get_rparam(request, 1);
	port_str = get_rparam(request, 2);
	regexp = get_rparam(request, 3);
	length_str = get_rparam(request, 4);
	output = get_rparam(request, 5);

	if ('\0' == *hostname)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if ('\0' == *path_str)
		*path = '\0';
	else
		strscpy(path, path_str);

	if ('\0' == *port_str)
		port_number = ZBX_DEFAULT_HTTP_PORT;
	else if (FAIL == is_ushort(port_str, &port_number))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == length_str || '\0' == *length_str)
		length = MAX_BUFFER_LEN - 1;
	else if (FAIL == is_uint31_1(length_str, &length))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* by default return the matched part of web page */
	if (NULL == output || '\0' == *output)
		output = "\\0";

	buffer = zbx_malloc(buffer, ZBX_MAX_WEBPAGE_SIZE);

	if (SYSINFO_RET_OK == get_http_page(hostname, path, port_number, buffer, ZBX_MAX_WEBPAGE_SIZE))
	{
		for (str = buffer; ;)
		{
			if (NULL != (newline = strchr(str, '\n')))
			{
				if (str != newline && '\r' == newline[-1])
					newline[-1] = '\0';
				else
					*newline = '\0';
			}

			if (NULL != (ptr = zbx_regexp_sub(str, regexp, output)))
				break;

			if (NULL != newline)
				str = newline + 1;
			else
				break;
		}
	}

	if (NULL != ptr)
		SET_STR_RESULT(result, ptr);
	else
		SET_STR_RESULT(result, zbx_strdup(NULL, ""));

	zbx_free(buffer);

	return SYSINFO_RET_OK;
}
