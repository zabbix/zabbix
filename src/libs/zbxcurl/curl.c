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
static unsigned int	zbx_curl_version_num(void)
{
	return curl_version_info(CURLVERSION_NOW)->version_num;
}

static const char	*zbx_curl_version(void)
{
	return curl_version_info(CURLVERSION_NOW)->version;
}

static const char	*zbx_curl_ssl_version(void)
{
	return curl_version_info(CURLVERSION_NOW)->ssl_version;
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
		*error = zbx_dsprintf(*error, "the cURL library does not support \"%s\" protocol (using version %s)",
				protocol, zbx_curl_version());
	}

	return FAIL;
}

static void	setopt_error(const char *option, CURLcode err, char **error)
{
	*error = zbx_dsprintf(*error, "the cURL library returned an error when trying to enable %s: %s"
			" (using version %s)", option, curl_easy_strerror(err), zbx_curl_version());
}

int	zbx_curl_setopt_https(CURL *easyhandle, char **error)
{
	int		ret = SUCCEED;
	CURLcode	err;

	/* CURLOPT_PROTOCOLS is supported starting with version 7.19.4 (0x071304) */
	if (zbx_curl_version_num() >= 0x071304)
	{
		/* CURLOPT_PROTOCOLS was replaced by CURLOPT_PROTOCOLS_STR and deprecated in version 7.85.0 (0x075500) */
		if (zbx_curl_version_num() >= 0x075500)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS_STR, "HTTP,HTTPS")))
			{
				ret = FAIL;
				setopt_error("HTTP/HTTPS", err, error);
			}
		}
		else
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS,
					CURLPROTO_HTTP | CURLPROTO_HTTPS)))
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

	/* CURLOPT_PROTOCOLS is supported starting with version 7.19.4 (0x071304) */
	if (zbx_curl_version_num() >= 0x071304)
	{
		/* CURLOPT_PROTOCOLS was replaced by CURLOPT_PROTOCOLS_STR and deprecated in version 7.85.0 (0x075500) */
		if (zbx_curl_version_num() >= 0x075500)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS_STR, "SMTP,SMTPS")))
			{
				ret = FAIL;
				setopt_error("SMTP/SMTPS", err, error);
			}
		}
		else
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS,
					CURLPROTO_SMTP | CURLPROTO_SMTPS)))
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
	if (zbx_curl_version_num() < 0x073d00)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "the cURL library version %s does not support HTTP Bearer token"
					" authentication, 7.61.0 or newer is required", zbx_curl_version());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_has_multi_wait(char **error)
{
	/* curl_multi_wait() is supported starting with version 7.28.0 (0x071c00) */
	if (zbx_curl_version_num() < 0x071c00)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "the cURL library version %s is too old for Elasticsearch history"
					" backend, 7.28.0 or newer is required", zbx_curl_version());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_has_ssl(char **error)
{
	if (NULL == zbx_curl_ssl_version())
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "the cURL library does not support SSL/TLS (using version %s)",
					zbx_curl_version());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_has_smtp_auth(char **error)
{
	/* added in 7.20.0 */
	if (zbx_curl_version_num() < 0x071400)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "the cURL library version %s does not support SMTP authentication,"
					" 7.20.0 or newer is required", zbx_curl_version());
		}

		return FAIL;
	}

	return SUCCEED;
}
#endif /* HAVE_LIBCURL */
