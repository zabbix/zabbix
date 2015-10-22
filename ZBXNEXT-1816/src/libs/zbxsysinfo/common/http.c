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

#ifdef HAVE_LIBCURL
struct MemoryStruct
{
	char *memory;
	size_t size;
};

static size_t write_memory_callback(void *contents, size_t size, size_t nmemb, void *userp)
{
	const char	*__function_name = "write_memory_callback";
	size_t		real_size = size * nmemb;
	struct		MemoryStruct *mem = (struct MemoryStruct *) userp;

	mem->memory = zbx_realloc(mem->memory, mem->size + real_size + 1);
	if (mem->memory == NULL)
	{
		/* out of memory! */
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() not enough memory (realloc returned NULL)",__function_name);
		return FAIL;
	}

	memcpy(&(mem->memory[mem->size]), contents, real_size);
	mem->size += real_size;
	mem->memory[mem->size] = 0;

	return real_size;
}
#endif /* HAVE_LIBCURL */

static int 	get_http_page(const char *host, const char *path, unsigned short port, const char *headers,
		char *buffer, size_t max_buffer_len)
{
#ifdef HAVE_LIBCURL
	CURL			*curl_handle;
	CURLcode		res;
	char			*header_params[MAX_STRING_LEN];
	long			size = ZBX_MAX_WEBPAGE_SIZE;
	int			param_count = 0, ret = SYSINFO_RET_FAIL, i;

	struct MemoryStruct	chunk;
	struct curl_slist	*slist = NULL;

	chunk.memory = zbx_malloc(chunk.memory, size); /* will be grown as needed by the realloc above */
	chunk.size = 0; /* no data at this point */

	/* init the curl session */
	curl_handle = curl_easy_init();
	if (curl_handle)
	{
		/* set verbose mode on for debugging */
		if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
			curl_easy_setopt(curl_handle, CURLOPT_VERBOSE, 1L);

		/* pass headers to the data stream */
		curl_easy_setopt(curl_handle, CURLOPT_HEADER, 1L);

		if (NULL != headers)
		{
			/* Removing a header that curl is adding by itself */
			slist = curl_slist_append(slist, "Accept:");

			if (0 == (param_count = num_param(headers)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "headers are badly formatted");
				goto out;
			}

			for (i = 0; i < param_count; i++)
				header_params[i] = get_param_dyn(headers, i + 1);

			/* Add all custom headers */
			for (i = 0; i < param_count; i++)
				slist = curl_slist_append(slist, header_params[i]);

			/* set our custom set of headers */
			res = curl_easy_setopt(curl_handle, CURLOPT_HTTPHEADER, slist);
		}

		/* specify URL to get */
		curl_easy_setopt(curl_handle, CURLOPT_URL, host);

		/* follow locations specified by the response header */
		curl_easy_setopt(curl_handle, CURLOPT_FOLLOWLOCATION, 1);

		/* send all data to this function  */
		curl_easy_setopt(curl_handle, CURLOPT_WRITEFUNCTION, write_memory_callback);

		/* we pass our 'chunk' struct to the callback function */
		curl_easy_setopt(curl_handle, CURLOPT_WRITEDATA, (void * )&chunk);

		/* some servers don't like requests that are made without a user-agent field, so we provide one */
		curl_easy_setopt(curl_handle, CURLOPT_USERAGENT, ZABBIX_VERSION);

		/* get it! */
		res = curl_easy_perform(curl_handle);

		/* check for errors */
		if (res != CURLE_OK)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "curl_easy_perform() failed: %s", curl_easy_strerror(ret));
			ret = SYSINFO_RET_FAIL;
		}
		else
		{
			zbx_strlcpy(buffer, chunk.memory, max_buffer_len);
			ret = SYSINFO_RET_OK;
		}

		/* cleanup curl stuff */
		curl_easy_cleanup(curl_handle);

		/* free the custom headers */
		curl_slist_free_all(slist);
	}
out:
	zbx_free(chunk.memory);

	return ret;
#else
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
#endif /* HAVE_LIBCURL */
}

int	WEB_PAGE_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*hostname, *path_str, *port_str, *header_str, buffer[MAX_BUFFER_LEN],
			path[MAX_STRING_LEN], header[MAX_STRING_LEN];
	unsigned short	port_number;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	hostname = get_rparam(request, 0);
	path_str = get_rparam(request, 1);
	port_str = get_rparam(request, 2);
	header_str = get_rparam(request, 3);

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

	if (NULL == header_str)
		*header = '\0';
	else
		strscpy(header, header_str);

	if (SYSINFO_RET_OK == get_http_page(hostname, path, port_number, header, buffer, sizeof(buffer)))
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

	if (SYSINFO_RET_OK == get_http_page(hostname, path, port_number, NULL, NULL, 0))
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

	if (SYSINFO_RET_OK == get_http_page(hostname, path, port_number, NULL, buffer, ZBX_MAX_WEBPAGE_SIZE))
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
