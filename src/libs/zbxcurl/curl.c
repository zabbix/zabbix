/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxcurl.h"

#ifdef HAVE_LIBCURL
static unsigned int	libcurl_version_num(void)
{
	return curl_version_info(CURLVERSION_NOW)->version_num;
}

static const char	*libcurl_version_str(void)
{
	return curl_version_info(CURLVERSION_NOW)->version;
}

static const char	*libcurl_ssl_version(void)
{
	return curl_version_info(CURLVERSION_NOW)->ssl_version;
}

/* curl_multi_wait() was added in cURL 7.28.0 (0x071c00). Since we support cURL library >= 7.19.1  */
/* we want to be able to compile against older cURL library. This is a wrapper that detects if the */
/* function is available at runtime. It should never be called for older library versions because  */
/* detect the version before. When cURL library requirement goes to >= 7.28.0 this function and    */
/* the structure declaration should be removed and curl_multi_wait() be used directly.             */
#if LIBCURL_VERSION_NUM < 0x071c00
struct curl_waitfd
{
	curl_socket_t	fd;
	short		events;
	short		revents;
};
#endif
CURLMcode	zbx_curl_multi_wait(CURLM *multi_handle, int timeout_ms, int *numfds)
{
	static CURLMcode	(*fptr)(CURLM *, struct curl_waitfd *, unsigned int, int, int *) = NULL;

	if (SUCCEED != zbx_curl_good_for_elasticsearch(NULL))
	{
		zabbix_log(LOG_LEVEL_CRIT, "zbx_curl_multi_wait() should never be called when using cURL library"
				" <= 7.28.0 (using version %s)", libcurl_version_str());
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	if (NULL == fptr)
	{
		void	*handle;

		if (NULL == (handle = dlopen(NULL, RTLD_LAZY | RTLD_NOLOAD | RTLD_GLOBAL)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot dlopen() Zabbix binary: %s", dlerror());
			exit(EXIT_FAILURE);
		}

		if (NULL == (fptr = dlsym(handle, "curl_multi_wait")))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot find cURL function curl_multi_wait(): %s", dlerror());
			exit(EXIT_FAILURE);
		}
	}

	return fptr(multi_handle, NULL, 0, timeout_ms, numfds);
}

int	zbx_curl_protocol(const char *protocol, char **error)
{
	curl_version_info_data	*ver;
	size_t			index = 0;

	ver = curl_version_info(CURLVERSION_NOW);

	while (NULL != ver->protocols[index])
	{
		if (0 == strcasecmp(protocol, ver->protocols[index]))
			return SUCCEED;

		index++;
	}

	if (NULL != error)
	{
		*error = zbx_dsprintf(*error, "cURL library does not support \"%s\" protocol (using version %s)",
				protocol, libcurl_version_str());
	}

	return FAIL;
}

static void	setopt_error(const char *option, CURLcode err, char **error)
{
	*error = zbx_dsprintf(*error, "cURL library returned error when enabling %s: %s (using version %s)",
			option, curl_easy_strerror(err), libcurl_version_str());
}

int	zbx_curl_setopt_https(CURL *easyhandle, char **error)
{
	int		ret = SUCCEED;
	CURLcode	err;

	/* CURLOPT_PROTOCOLS (181L) is supported starting with version 7.19.4 (0x071304) */
	if (libcurl_version_num() >= 0x071304)
	{
		/* CURLOPT_PROTOCOLS was replaced by CURLOPT_PROTOCOLS_STR and deprecated in 7.85.0 (0x075500) */
		if (libcurl_version_num() >= 0x075500)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS_STR, "HTTP,HTTPS")))
			{
				ret = FAIL;
				setopt_error("HTTP/HTTPS", err, error);
			}
		}
		else
		{
			/* 181L is CURLOPT_PROTOCOLS, remove when cURL requirement will become >= 7.85.0 */
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, 181L, CURLPROTO_HTTP | CURLPROTO_HTTPS)))
			{
				ret = FAIL;
				setopt_error("HTTP/HTTPS", err, error);
			}
		}
	}

	return ret;
}

int	zbx_curl_setopt_smtps(CURL *easyhandle, char **error)
{
	int		ret = SUCCEED;
	CURLcode	err;

	/* CURLOPT_PROTOCOLS (181L) is supported starting with version 7.19.4 (0x071304) */
	if (libcurl_version_num() >= 0x071304)
	{
		/* CURLOPT_PROTOCOLS was replaced by CURLOPT_PROTOCOLS_STR and deprecated in 7.85.0 (0x075500) */
		if (libcurl_version_num() >= 0x075500)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS_STR, "SMTP,SMTPS")))
			{
				ret = FAIL;
				setopt_error("SMTP/SMTPS", err, error);
			}
		}
		else
		{
			/* 181L is CURLOPT_PROTOCOLS, remove when cURL requirement will become >= 7.85.0 */
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, 181L, CURLPROTO_SMTP | CURLPROTO_SMTPS)))
			{
				ret = FAIL;
				setopt_error("SMTP/SMTPS", err, error);
			}
		}
	}

	return ret;
}

int	zbx_curl_has_bearer(char **error)
{
	/* added in 7.61.0 (0x073d00) */
	if (libcurl_version_num() < 0x073d00)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "cURL library version %s does not support HTTP Bearer token"
					" authentication, 7.61.0 or newer is required", libcurl_version_str());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_good_for_elasticsearch(char **error)
{
	/* Elasticsearch needs curl_multi_wait() which was added in 7.28.0 (0x071c00) */
	if (libcurl_version_num() < 0x071c00)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "cURL library version %s is too old for Elasticsearch history"
					" backend, 7.28.0 or newer is required", libcurl_version_str());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_has_ssl(char **error)
{
	if (NULL == libcurl_ssl_version())
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "cURL library does not support SSL/TLS (using version %s)",
					libcurl_version_str());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_has_smtp_auth(char **error)
{
	/* added in 7.20.0 */
	if (libcurl_version_num() < 0x071400)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "cURL library version %s does not support SMTP authentication,"
					" 7.20.0 or newer is required", libcurl_version_str());
		}

		return FAIL;
	}

	return SUCCEED;
}
#endif /* HAVE_LIBCURL */
