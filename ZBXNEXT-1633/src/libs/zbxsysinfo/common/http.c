/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"

#include "log.h"
#include "comms.h"
#include "cfg.h"

#include "http.h"

#define ZBX_MAX_WEBPAGE_SIZE	(1 * 1024 * 1024)

static int	get_http_page(const char *host, const char *path, unsigned short port, char *buffer, int max_buffer_len)
{
	int		ret;
	char		*recv_buffer;
	char		request[MAX_STRING_LEN];
	zbx_sock_t	s;

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
			if (SUCCEED == (ret = SUCCEED_OR_FAIL(zbx_tcp_recv_ext(&s, &recv_buffer, ZBX_TCP_READ_UNTIL_CLOSE, 0))))
			{
				if (NULL != buffer)
					zbx_strlcpy(buffer, recv_buffer, max_buffer_len);
			}
		}

		zbx_tcp_close(&s);
	}

	if (FAIL == ret)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "HTTP get error: %s", zbx_tcp_strerror());
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	WEB_PAGE_GET(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	hostname[MAX_STRING_LEN];
	char	path[MAX_STRING_LEN];
	char	port_str[8];
	char	buffer[MAX_BUFFER_LEN];

        if (num_param(param) > 3)
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, hostname, sizeof(hostname)))
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, path, sizeof(path)))
		*path = '\0';

	if (0 != get_param(param, 3, port_str, sizeof(port_str)) || '\0' == *port_str)
		zbx_snprintf(port_str, sizeof(port_str), "%d", ZBX_DEFAULT_HTTP_PORT);
	else if (FAIL == is_uint(port_str))
		return SYSINFO_RET_FAIL;

	if (SYSINFO_RET_OK == get_http_page(hostname, path, (unsigned short)atoi(port_str), buffer, sizeof(buffer)))
	{
		zbx_rtrim(buffer, "\r\n");
		SET_TEXT_RESULT(result, strdup(buffer));
	}
	else
		SET_TEXT_RESULT(result, strdup("EOF"));

	return SYSINFO_RET_OK;
}

int	WEB_PAGE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	hostname[MAX_STRING_LEN];
	char	path[MAX_STRING_LEN];
	char	port_str[8];
	double	start_time;

        if (num_param(param) > 3)
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, hostname, sizeof(hostname)))
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, path, sizeof(path)))
		*path = '\0';

	if (0 != get_param(param, 3, port_str, sizeof(port_str)) || '\0' == *port_str)
		zbx_snprintf(port_str, sizeof(port_str), "%d", ZBX_DEFAULT_HTTP_PORT);
	else if (FAIL == is_uint(port_str))
		return SYSINFO_RET_FAIL;

	start_time = zbx_time();

	if (SYSINFO_RET_OK == get_http_page(hostname, path, (unsigned short)atoi(port_str), NULL, 0))
	{
		SET_DBL_RESULT(result, zbx_time() - start_time);
	}
	else
		SET_DBL_RESULT(result, 0.0);

	return SYSINFO_RET_OK;
}

int	WEB_PAGE_REGEXP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	hostname[MAX_STRING_LEN];
	char	path[MAX_STRING_LEN];
	char	port_str[8];
	char	regexp[MAX_STRING_LEN];
	char	len_str[16];
	char	back[MAX_BUFFER_LEN];
	char	*buffer = NULL, *found;
	int	len, found_len;

        if (num_param(param) > 5)
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, hostname, sizeof(hostname)))
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, path, sizeof(path)))
		*path = '\0';

	if (0 != get_param(param, 3, port_str, sizeof(port_str)) || '\0' == *port_str)
		zbx_snprintf(port_str, sizeof(port_str), "%d", ZBX_DEFAULT_HTTP_PORT);
	else if (FAIL == is_uint(port_str))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 4, regexp, sizeof(regexp)))
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 5, len_str, sizeof(len_str)) || '\0' == *len_str)
		zbx_snprintf(len_str, sizeof(len_str), "%d", MAX_BUFFER_LEN - 1);
	else if (FAIL == is_uint(len_str))
		return SYSINFO_RET_FAIL;

	buffer = zbx_malloc(buffer, ZBX_MAX_WEBPAGE_SIZE);

	if (SYSINFO_RET_OK == get_http_page(hostname, path, (unsigned short)atoi(port_str), buffer, ZBX_MAX_WEBPAGE_SIZE))
	{
		if (NULL != (found = zbx_regexp_match(buffer, regexp, &found_len)))
		{
			len = atoi(len_str) + 1;
			len = MIN(len, found_len + 1);
			len = MIN(len, sizeof(back));

			zbx_strlcpy(back, found, len);
			SET_STR_RESULT(result, strdup(back));
		}
		else
			SET_STR_RESULT(result, strdup("EOF"));
	}
	else
		SET_STR_RESULT(result, strdup("EOF"));

	zbx_free(buffer);

	return SYSINFO_RET_OK;
}
