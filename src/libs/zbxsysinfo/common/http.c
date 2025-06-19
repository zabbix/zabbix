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

#include "http.h"

#include "../sysinfo.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxregexp.h"
#include "zbxhttp.h"
#include "zbxcomms.h"

#define HTTP_SCHEME_STR		"http://"

#ifndef HAVE_LIBCURL

#define ZBX_MAX_WEBPAGE_SIZE	(1 * 1024 * 1024)

#else

#include "zbxcurl.h"

#define HTTPS_SCHEME_STR	"https://"

#endif

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
	char	*p, *delim;
	int	scheme_found = 0;

	/* port and path parameters must be empty */
	if ((NULL != port && '\0' != *port) || (NULL != path && '\0' != *path))
	{
		*error = zbx_strdup(*error,
				"Parameters \"path\" and \"port\" must be empty if URL is specified in \"host\".");
		return FAIL;
	}

	/* allow HTTP(S) scheme only */
#ifdef HAVE_LIBCURL
	if (0 == zbx_strncasecmp(host, HTTP_SCHEME_STR, ZBX_CONST_STRLEN(HTTP_SCHEME_STR)) ||
			0 == zbx_strncasecmp(host, HTTPS_SCHEME_STR, ZBX_CONST_STRLEN(HTTPS_SCHEME_STR)))
#else
	if (0 == zbx_strncasecmp(host, HTTP_SCHEME_STR, ZBX_CONST_STRLEN(HTTP_SCHEME_STR)))
#endif
	{
		scheme_found = 1;
	}
	else if (NULL != (p = strstr(host, "://")) && (NULL == (delim = strpbrk(host, "/?#")) || delim > p))
	{
		*error = zbx_dsprintf(*error, "Unsupported scheme: %.*s.", (int)(p - host), host);
		return FAIL;
	}

	if (NULL != (p = strchr(host, '#')))
		*url = zbx_dsprintf(*url, "%s%.*s", (0 == scheme_found ? HTTP_SCHEME_STR : ""), (int)(p - host), host);
	else
		*url = zbx_dsprintf(*url, "%s%s", (0 == scheme_found ? HTTP_SCHEME_STR : ""), host);

	return SUCCEED;
}

static int	check_common_params(const char *host, const char *path, char **error)
{
	const char	*wrong_chr, URI_PROHIBIT_CHARS[] = {0x1,0x2,0x3,0x4,0x5,0x6,0x7,0x8,0x9,0xA,0xB,0xC,0xD,0xE,\
			0xF,0x10,0x11,0x12,0x13,0x14,0x15,0x16,0x17,0x18,0x19,0x1A,0x1B,0x1C,0x1D,0x1E,0x1F,0x7F,0};

	if (NULL == host || '\0' == *host)
	{
		*error = zbx_strdup(*error, "Invalid first parameter.");
		return FAIL;
	}

	if (NULL != (wrong_chr = strpbrk(host, URI_PROHIBIT_CHARS)))
	{
		*error = zbx_dsprintf(NULL, "Incorrect hostname expression. Check hostname part after: %.*s.",
				(int)(wrong_chr - host), host);
		return FAIL;
	}

	if (NULL != path && NULL != (wrong_chr = strpbrk(path, URI_PROHIBIT_CHARS)))
	{
		*error = zbx_dsprintf(NULL, "Incorrect path expression. Check path part after: %.*s.",
				(int)(wrong_chr - path), path);
		return FAIL;
	}

	return SUCCEED;
}

#ifdef HAVE_LIBCURL
static int	curl_page_get(char *url, int timeout, char **buffer, char **error)
{
	CURLcode		err;
	CURL			*easyhandle;
	int			ret = SYSINFO_RET_FAIL;
	zbx_http_response_t	body = {0}, header = {0};
	char			errbuf[CURL_ERROR_SIZE];
	struct curl_slist	*headers = NULL;

	if (NULL == (easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(*error, "Cannot initialize cURL library.");
		return SYSINFO_RET_FAIL;
	}

	headers = curl_slist_append(headers, "Connection: close");

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERAGENT, "Zabbix " ZABBIX_VERSION)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_FOLLOWLOCATION, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, url)) ||
			(NULL != sysinfo_get_config_source_ip() &&
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE,
					sysinfo_get_config_source_ip()))) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT,
					(long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPHEADER, headers)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option: %s.", curl_easy_strerror(err));
		goto out;
	}

	if (NULL != buffer)
	{
		if (SUCCEED != zbx_http_prepare_callbacks(easyhandle, &header, &body, zbx_curl_write_cb,
				zbx_curl_write_cb, errbuf, error))
		{
			goto out;
		}
	}
	else
	{
		if (SUCCEED != zbx_http_prepare_callbacks(easyhandle, &header, &body, zbx_curl_ignore_cb,
				zbx_curl_ignore_cb, errbuf, error))
		{
			goto out;
		}
	}

	if (SUCCEED != zbx_curl_setopt_https(easyhandle, error))
		goto out;

	*errbuf = '\0';
	if (CURLE_OK == (err = curl_easy_perform(easyhandle)))
	{
		if (NULL != buffer)
		{
			if (NULL != body.data)
			{
				zbx_http_convert_to_utf8(easyhandle, &body.data, &body.offset, &body.allocated);
				zbx_strncpy_alloc(&header.data, &header.allocated, &header.offset, body.data, body.offset);
			}
			*buffer = header.data;
			header.data = NULL;
		}

		ret = SYSINFO_RET_OK;
	}
	else
	{
		*error = zbx_dsprintf(*error, "Cannot perform request: %s", '\0' == *errbuf ?
				curl_easy_strerror(err) : errbuf);
	}
out:
	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
	zbx_free(header.data);
	zbx_free(body.data);

	return ret;
}

static int	get_http_page(const char *host, const char *path, const char *port, int timeout, char **buffer,
		char **error)
{
	char	*url = NULL;
	int	ret;

	if (SUCCEED != check_common_params(host, path, error))
		return SYSINFO_RET_FAIL;

	if (SUCCEED == detect_url(host))
	{
		/* URL detected */
		if (SUCCEED != process_url(host, port, path, &url, error))
			return SYSINFO_RET_FAIL;
	}
	else
	{
		/* URL is not detected - compose URL using host, port and path */

		unsigned short	port_n = ZBX_DEFAULT_HTTP_PORT;

		if (NULL != port && '\0' != *port)
		{
			if (SUCCEED != zbx_is_ushort(port, &port_n))
			{
				*error = zbx_strdup(*error, "Invalid third parameter.");
				return SYSINFO_RET_FAIL;
			}
		}

		if (NULL != strchr(host, ':'))
			url = zbx_dsprintf(url, HTTP_SCHEME_STR "[%s]:%u/", host, port_n);
		else
			url = zbx_dsprintf(url, HTTP_SCHEME_STR "%s:%u/", host, port_n);

		if (NULL != path)
			url = zbx_strdcat(url, path + ('/' == *path ? 1 : 0));
	}

	if (SUCCEED != zbx_http_punycode_encode_url(&url))
	{
		*error = zbx_strdup(*error, "Cannot encode domain name into punycode.");
		ret = SYSINFO_RET_FAIL;
		goto out;
	}

	ret = curl_page_get(url, timeout, buffer, error);
out:
	zbx_free(url);

	return ret;
}
#else
static char	*find_port_sep(char *host, size_t len)
{
	int	in_ipv6 = 0;

	for (; 0 < len--; host++)
	{
		if (0 == in_ipv6)
		{
			if (':' == *host)
				return host;
			else if ('[' == *host)
				in_ipv6 = 1;
		}
		else if (']' == *host)
			in_ipv6 = 0;
	}

	return NULL;
}

static int	get_http_page(const char *host, const char *path, const char *port, int timeout, char **buffer,
		char **error)
{
	char		*url = NULL, *hostname = NULL, *path_loc = NULL;
	int		ret = SYSINFO_RET_OK, ipv6_host_found = 0;
	unsigned short	port_num;
	zbx_socket_t	s;

	if (SUCCEED != check_common_params(host, path, error))
		return SYSINFO_RET_FAIL;

	if (SUCCEED == detect_url(host))
	{
		/* URL detected */

		char	*p, *p_host, *au_end;
		size_t	authority_len;

		if (SUCCEED != process_url(host, port, path, &url, error))
			return SYSINFO_RET_FAIL;

		p_host = url + ZBX_CONST_STRLEN(HTTP_SCHEME_STR);

		if (0 == (authority_len = strcspn(p_host, "/?")))
		{
			*error = zbx_dsprintf(*error, "Invalid or missing host in URL.");
			ret = SYSINFO_RET_FAIL;
			goto out;
		}

		if (NULL != memchr(p_host, '@', authority_len))
		{
			*error = zbx_strdup(*error, "Unsupported URL format.");
			ret = SYSINFO_RET_FAIL;
			goto out;
		}

		au_end = &p_host[authority_len - 1];

		if (NULL != (p = find_port_sep(p_host, authority_len)))
		{
			char	*port_str;
			int	port_len = (int)(au_end - p);

			if (0 < port_len)
			{
				port_str = zbx_dsprintf(NULL, "%.*s", port_len, p + 1);

				if (SUCCEED != zbx_is_ushort(port_str, &port_num))
					ret = SYSINFO_RET_FAIL;
				else
					hostname = zbx_dsprintf(hostname, "%.*s", (int)(p - p_host), p_host);

				zbx_free(port_str);
			}
			else
				ret = SYSINFO_RET_FAIL;
		}
		else
		{
			port_num = ZBX_DEFAULT_HTTP_PORT;
			hostname = zbx_dsprintf(hostname, "%.*s", (int)(au_end - p_host + 1), p_host);
		}

		if (SYSINFO_RET_OK != ret)
		{
			*error = zbx_dsprintf(*error, "URL using bad/illegal format.");
			goto out;
		}

		if ('[' == *hostname)
		{
			zbx_ltrim(hostname, "[");
			zbx_rtrim(hostname, "]");
			ipv6_host_found = 1;
		}

		if ('\0' == *hostname)
		{
			*error = zbx_dsprintf(*error, "Invalid or missing host in URL.");
			ret = SYSINFO_RET_FAIL;
			goto out;
		}

		path_loc = zbx_strdup(path_loc, '\0' != p_host[authority_len] ? &p_host[authority_len] : "/");
	}
	else
	{
		/* URL is not detected */

		if (NULL == port || '\0' == *port)
		{
			port_num = ZBX_DEFAULT_HTTP_PORT;
		}
		else if (FAIL == zbx_is_ushort(port, &port_num))
		{
			*error = zbx_strdup(*error, "Invalid third parameter.");
			ret = SYSINFO_RET_FAIL;
			goto out;
		}

		path_loc = zbx_strdup(path_loc, (NULL != path ? path : "/"));
		hostname = zbx_strdup(hostname, host);

		if (NULL != strchr(hostname, ':'))
			ipv6_host_found = 1;
	}

	if (SUCCEED != zbx_http_punycode_encode_url(&hostname))
	{
		*error = zbx_strdup(*error, "Cannot encode domain name into punycode.");
		ret = SYSINFO_RET_FAIL;
		goto out;
	}

	if (SUCCEED == (ret = zbx_tcp_connect(&s, sysinfo_get_config_source_ip(), hostname, port_num, timeout,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL)))
	{
		char	*request = NULL;

		request = zbx_dsprintf(request,
				"GET %s%s HTTP/1.1\r\n"
				"Host: %s%s%s\r\n"
				"Connection: close\r\n"
				"\r\n",
				('/' != *path_loc ? "/" : ""), path_loc, (1 == ipv6_host_found ? "[" : ""), hostname,
				(1 == ipv6_host_found ? "]" : ""));

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

	if (SUCCEED != ret)
	{
		*error = zbx_dsprintf(NULL, "HTTP get error: %s", zbx_socket_strerror());
		ret = SYSINFO_RET_FAIL;
	}
	else
		ret = SYSINFO_RET_OK;

out:
	zbx_free(url);
	zbx_free(path_loc);
	zbx_free(hostname);

	return ret;
}
#endif

int	web_page_get(AGENT_REQUEST *request, AGENT_RESULT *result)
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

	if (SYSINFO_RET_OK == (ret = get_http_page(hostname, path_str, port_str, request->timeout, &buffer, &error)))
	{
		zbx_rtrim(buffer, "\r\n");
		SET_TEXT_RESULT(result, buffer);
	}
	else
		SET_MSG_RESULT(result, error);

	return ret;
}

int	web_page_perf(AGENT_REQUEST *request, AGENT_RESULT *result)
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

	if (SYSINFO_RET_OK == (ret = get_http_page(hostname, path_str, port_str, request->timeout, NULL, &error)))
		SET_DBL_RESULT(result, zbx_time() - start_time);
	else
		SET_MSG_RESULT(result, error);

	return ret;
}

int	web_page_regexp(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*hostname, *path_str, *port_str, *buffer = NULL, *error = NULL, *regexp, *length_str;
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
		length = ZBX_MAX_UINT31_1;
	else if (FAIL == zbx_is_uint31_1(length_str, &length))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* by default return the matched part of web page */
	if (NULL == output || '\0' == *output)
		output = "\\0";

	if (SYSINFO_RET_OK == (ret = get_http_page(hostname, path_str, port_str, request->timeout, &buffer, &error)))
	{
		char	*ptr = NULL, *str;

		for (str = buffer; ;)
		{
			char	*newline;

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
		{
			if ((size_t)length < zbx_strlen_utf8(ptr))
				ptr[zbx_strlen_utf8_nchars(ptr, length)] = '\0';

			SET_STR_RESULT(result, ptr);
		}
		else
			SET_STR_RESULT(result, zbx_strdup(NULL, ""));

		zbx_free(buffer);
	}
	else
		SET_MSG_RESULT(result, error);

	return ret;
}
