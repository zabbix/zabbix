/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "zbxhttp.h"

#include "comms.h"
#include "cfg.h"

#include "http.h"

#define HTTP_SCHEME_STR		"http://"

#ifndef HAVE_LIBCURL

#define ZBX_MAX_WEBPAGE_SIZE	(1 * 1024 * 1024)

#else

#define HTTPS_SCHEME_STR	"https://"

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
zbx_http_response_t;

#endif

static const char URI_PROHIBIT_CHARS[] = {0x1,0x2,0x3,0x4,0x5,0x6,0x7,0x8,0x9,0xA,0xB,0xC,0xD,0xE,0xF,0x10,0x11,0x12,\
	0x13,0x14,0x15,0x16,0x17,0x18,0x19,0x1A,0x1B,0x1C,0x1D,0x1E,0x1F,0x7F,0};

static int	detect_url(const char *host)
{
	char	*p;
	int	ret = FAIL;

	if (NULL != strpbrk(host, "/@#?[]"))
		return SUCCEED;

	if (NULL != (p = strchr(host, ':')) && NULL == strchr(++p, ':'))
		ret = SUCCEED;

	return ret;
}

static int	process_url(const char *host, const char *port, const char *path, char **url, char **error)
{
	char	*host_lcase = NULL, *p;
	int	ret = SYSINFO_RET_OK;

	/* port and path parameters must be empty */
	if ((NULL != port && '\0' != *port) || (NULL != path && '\0' != *path))
	{
		*error = zbx_strdup(*error,
				"Parameters 'path' and 'port' must be empty if URL is specified in 'host'.");
		return SYSINFO_RET_FAIL;
	}

	host_lcase = zbx_strdup(host_lcase, host);
	zbx_strlower(host_lcase);

	/* allow HTTP(S) scheme only */
#ifdef HAVE_LIBCURL
	if (0 == strncmp(host_lcase, HTTP_SCHEME_STR, ZBX_CONST_STRLEN(HTTP_SCHEME_STR)) ||
			0 == strncmp(host_lcase, HTTPS_SCHEME_STR, ZBX_CONST_STRLEN(HTTPS_SCHEME_STR)))
#else
	if (0 == strncmp(host_lcase, HTTP_SCHEME_STR, ZBX_CONST_STRLEN(HTTP_SCHEME_STR)))
#endif
	{
		*url = zbx_strdup(*url, host);
	}
	else if (NULL != (p = strstr(host, "://")))
	{
		size_t	len = (size_t)(p + 1 - host);

		p = (char*)zbx_malloc(NULL, len);
		zbx_strlcpy(p, host, len);
		*error = zbx_dsprintf(*error, "Unsupported scheme: %s.", p);
		zbx_free(p);
		ret = SYSINFO_RET_FAIL;
	}
	else
		*url = zbx_dsprintf(*url, HTTP_SCHEME_STR "%s", host);

	if (SYSINFO_RET_OK == ret)
	{
		if (SUCCEED != zbx_http_punycode_encode_url(url))
		{
			*error = zbx_strdup(*error, "Cannot encode domain name into punycode.");
			zbx_free(*url);
			ret = SYSINFO_RET_FAIL;
		}
	}

	zbx_free(host_lcase);

	return ret;
}

#ifdef HAVE_LIBCURL
static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t			r_size = size * nmemb;
	zbx_http_response_t	*response;

	response = (zbx_http_response_t*)userdata;
	zbx_str_memcpy_alloc(&response->data, &response->allocated, &response->offset, (const char *)ptr, r_size);

	return r_size;
}

static size_t	curl_ignore_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

static int	curl_page_get(char *url, char **buffer, char **error)
{
	CURLcode		err;
	zbx_http_response_t	page = {0};
	CURL			*easyhandle;
	int			ret = SYSINFO_RET_FAIL;

	if (NULL == (easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(*error, "Cannot initialize cURL library.");
		return SYSINFO_RET_FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERAGENT, "Zabbix " ZABBIX_VERSION)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_FOLLOWLOCATION, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, url)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEFUNCTION,
			NULL != buffer ? curl_write_cb : curl_ignore_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEDATA, &page)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADER, 1L)) ||
			(NULL != CONFIG_SOURCE_IP &&
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE, CONFIG_SOURCE_IP))))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option: %s.", curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK == (err = curl_easy_perform(easyhandle)))
	{
		if (NULL != buffer)
			*buffer = page.data;

		ret = SYSINFO_RET_OK;
	}
	else
		*error = zbx_dsprintf(*error, "Cannot perform cURL request: %s.", curl_easy_strerror(err));

out:
	curl_easy_cleanup(easyhandle);

	return ret;
}

static int	get_http_page(const char *host, const char *path, const char *port, char **buffer, char **error)
{
	char		*url = NULL;
	const char	*wrong_chr;
	int		ret;

	if (NULL == host || '\0' == *host)
	{
		*error = zbx_strdup(*error, "Invalid first parameter.");
		return SYSINFO_RET_FAIL;
	}

	if (NULL != (wrong_chr = strpbrk(host, URI_PROHIBIT_CHARS)))
	{
		*error = zbx_dsprintf(NULL, "Incorrect hostname expression. Check hostname part after: %.*s.",
				(int)(wrong_chr - host), host);
		return SYSINFO_RET_FAIL;
	}

	if (NULL != path && NULL != (wrong_chr = strpbrk(path, URI_PROHIBIT_CHARS)))
	{
		*error = zbx_dsprintf(NULL, "Incorrect path expression. Check path part after: %.*s.",
				(int)(wrong_chr - path), path);
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED == detect_url(host))
	{
		/* URL detected */
		if (SYSINFO_RET_OK != process_url(host, port, path, &url, error))
			return SYSINFO_RET_FAIL;
	}
	else
	{
		/* URL is not detected - compose URL using host, port and path */

		unsigned short	port_n = ZBX_DEFAULT_HTTP_PORT;
		char		*host_loc = NULL;

		if (NULL != port && '\0' != *port)
		{
			if (SUCCEED != is_ushort(port, &port_n))
			{
				*error = zbx_strdup(*error, "Invalid third parameter.");
				return SYSINFO_RET_FAIL;
			}
		}

		if (NULL != strchr(host, ':'))
			host_loc = zbx_dsprintf(host_loc, "[%s]", host);
		else
			host_loc = zbx_strdup(host_loc, host);

		if (NULL != path)
			url = zbx_dsprintf(url, "http://%s:%u%s%s", host_loc, port_n, ('/' != *path ? "/" : ""), path);
		else
			url = zbx_dsprintf(url, "http://%s:%u/", host_loc, port_n);

		zbx_free(host_loc);
	}

	ret = curl_page_get(url, buffer, error);
	zbx_free(url);

	return ret;
}
#else
static char	*find_port_sep(char *host)
{
	char	*p;
	int	in_ipv6 = 0;

	for (p = host; '\0' != *p; p++)
	{
		if (0 == in_ipv6)
		{
			if (':' == *p)
				return p;
			else if ('[' == *p)
				in_ipv6 = 1;
		}
		else if (']' == *p)
			in_ipv6 = 0;
	}

	return NULL;
}

static int	get_http_page(const char *host, const char *path, const char *port, char **buffer, char **error)
{
	char		*url = NULL, *hostname = NULL, *path_loc = NULL, *p_host;
	const char	*wrong_chr;
	int		ret = SYSINFO_RET_OK;
	unsigned short	port_num;
	zbx_socket_t	s;

	if (NULL == host || '\0' == *host)
	{
		*error = zbx_strdup(*error, "Invalid first parameter.");
		return SYSINFO_RET_FAIL;
	}

	if (NULL != (wrong_chr = strpbrk(host, URI_PROHIBIT_CHARS)))
	{
		*error = zbx_dsprintf(NULL, "Incorrect hostname expression. Check hostname part after: %.*s.",
				(int)(wrong_chr - host), host);
		return SYSINFO_RET_FAIL;
	}

	if (NULL != path && NULL != (wrong_chr = strpbrk(path, URI_PROHIBIT_CHARS)))
	{
		*error = zbx_dsprintf(NULL, "Incorrect path expression. Check path part after: %.*s.",
				(int)(wrong_chr - path), path);
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED == detect_url(host))
	{
		/* URL detected */

		char	*p;

		if (SYSINFO_RET_OK != process_url(host, port, path, &url, error))
			return SYSINFO_RET_FAIL;

		if (NULL != (p = strchr(url, '@')))
		{
			*error = zbx_dsprintf(*error, "Unsupported URL: %s.", url);
			ret = SYSINFO_RET_FAIL;
			goto out;
		}

		p_host = url + ZBX_CONST_STRLEN(HTTP_SCHEME_STR);

		if (NULL != (p = find_port_sep(p_host)))
		{
			if ('\0' != *(++p))
			{
				char	*port_str;
				size_t	len = 0, offset = 0;

				zbx_strsplit(p, '/', &port_str, &path_loc);

				if (SUCCEED != is_ushort(port_str, &port_num))
					ret = SYSINFO_RET_FAIL;
				else
					zbx_strncpy_alloc(&hostname, &len, &offset, p_host, (size_t)(p - p_host - 1));

				zbx_free(port_str);
			}
			else
				ret = SYSINFO_RET_FAIL;
		}
		else
		{
			port_num = ZBX_DEFAULT_HTTP_PORT;
			zbx_strsplit(p_host, '/', &hostname, &path_loc);
		}

		if (SYSINFO_RET_OK != ret)
		{
			*error = zbx_dsprintf(*error, "Invalid port in URL: %s.", url);
			goto out;
		}
		else if (NULL == path_loc)
		{
			path_loc = zbx_strdup(path_loc, "/");
		}

		zbx_lrtrim(hostname, "[]");
	}
	else
	{
		/* URL is not detected */

		if (NULL == port || '\0' == *port)
		{
			port_num = ZBX_DEFAULT_HTTP_PORT;
		}
		else if (FAIL == is_ushort(port, &port_num))
		{
			*error = zbx_strdup(*error, "Invalid third parameter.");
			ret = SYSINFO_RET_FAIL;
			goto out;
		}

		path_loc = zbx_strdup(path_loc, (NULL != path ? path : "/"));
		hostname = zbx_strdup(hostname, host);
	}

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, hostname, port_num, CONFIG_TIMEOUT,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL)))
	{
		char	*request = NULL;

		request = zbx_dsprintf(request,
				"GET %s%s HTTP/1.1\r\n"
				"Host: %s\r\n"
				"Connection: close\r\n"
				"\r\n",
				('/' != *path_loc ? "/" : ""), path_loc, hostname);

		if (SUCCEED == (ret = zbx_tcp_send_raw(&s, request)))
		{
			if (SUCCEED == (ret = zbx_tcp_recv_raw(&s)))
			{
				if (NULL != buffer)
				{
					*buffer = (char*)zbx_malloc(*buffer, ZBX_MAX_WEBPAGE_SIZE);
					zbx_strlcpy(*buffer, s.buffer, ZBX_MAX_WEBPAGE_SIZE);
				}
			}
		}

		zbx_free(request);
		zbx_tcp_close(&s);
	}

	if (FAIL == ret)
	{
		*error = zbx_dsprintf(NULL, "HTTP get error: %s", zbx_socket_strerror());
		ret = SYSINFO_RET_FAIL;
	}

out:
	zbx_free(url);
	zbx_free(path_loc);
	zbx_free(hostname);

	return ret;
}
#endif

int	WEB_PAGE_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*hostname, *path_str, *port_str, *buffer = NULL, *error = NULL;
	int	ret;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	hostname = get_rparam(request, 0);
	path_str = get_rparam(request, 1);
	port_str = get_rparam(request, 2);

	if (SYSINFO_RET_OK == (ret = get_http_page(hostname, path_str, port_str, &buffer, &error)))
	{
		zbx_rtrim(buffer, "\r\n");
		SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));
	}
	else
		SET_MSG_RESULT(result, error);

	return ret;
}

int	WEB_PAGE_PERF(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*hostname, *path_str, *port_str, *error = NULL;
	double	start_time;
	int	ret;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	hostname = get_rparam(request, 0);
	path_str = get_rparam(request, 1);
	port_str = get_rparam(request, 2);

	start_time = zbx_time();

	if (SYSINFO_RET_OK == (ret = get_http_page(hostname, path_str, port_str, NULL, &error)))
		SET_DBL_RESULT(result, zbx_time() - start_time);
	else
		SET_MSG_RESULT(result, error);

	return ret;
}

int	WEB_PAGE_REGEXP(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*hostname, *path_str, *port_str, *buffer = NULL, *error = NULL,
			*ptr = NULL, *str, *newline, *regexp, *length_str;
	const char	*output;
	int		length, ret;

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

	if (SYSINFO_RET_OK == (ret = get_http_page(hostname, path_str, port_str, &buffer, &error)))
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

			if (SUCCEED == zbx_regexp_sub(str, regexp, output, &ptr) && NULL != ptr)
				break;

			if (NULL != newline)
				str = newline + 1;
			else
				break;
		}

		if (NULL != ptr)
			SET_STR_RESULT(result, ptr);
		else
			SET_STR_RESULT(result, zbx_strdup(NULL, ""));

		zbx_free(buffer);
	}
	else
		SET_MSG_RESULT(result, error);

	return ret;
}
