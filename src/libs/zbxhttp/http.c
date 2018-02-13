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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"

#ifdef HAVE_LIBCURL

extern char	*CONFIG_SOURCE_IP;

extern char	*CONFIG_SSL_CA_LOCATION;
extern char	*CONFIG_SSL_CERT_LOCATION;
extern char	*CONFIG_SSL_KEY_LOCATION;

int	zbx_prepare_https(CURL *easyhandle, const char *ssl_cert_file, const char *ssl_key_file,
		const char *ssl_key_password, unsigned char verify_peer, unsigned char verify_host,
		char **error)
{
	CURLcode	err;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER, 0 == verify_peer ? 0L : 1L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set verify the peer's SSL certificate: %s",
				curl_easy_strerror(err));
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST, 0 == verify_host ? 0L : 2L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set verify the certificate's name against host: %s",
				curl_easy_strerror(err));
		return FAIL;
	}

	if (NULL != CONFIG_SOURCE_IP)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE, CONFIG_SOURCE_IP)))
		{
			*error = zbx_dsprintf(*error, "Cannot specify source interface for outgoing traffic: %s",
					curl_easy_strerror(err));
			return FAIL;
		}
	}

	if (0 != verify_peer && NULL != CONFIG_SSL_CA_LOCATION)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CAPATH, CONFIG_SSL_CA_LOCATION)))
		{
			*error = zbx_dsprintf(*error, "Cannot specify directory holding CA certificates: %s",
					curl_easy_strerror(err));
			return FAIL;
		}
	}

	if ('\0' != *ssl_cert_file)
	{
		char	*file_name;

		file_name = zbx_dsprintf(NULL, "%s/%s", CONFIG_SSL_CERT_LOCATION, ssl_cert_file);
		zabbix_log(LOG_LEVEL_DEBUG, "using SSL certificate file: '%s'", file_name);

		err = curl_easy_setopt(easyhandle, CURLOPT_SSLCERT, file_name);
		zbx_free(file_name);

		if (CURLE_OK != err)
		{
			*error = zbx_dsprintf(*error, "Cannot set SSL client certificate: %s", curl_easy_strerror(err));
			return FAIL;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLCERTTYPE, "PEM")))
		{
			*error = zbx_dsprintf(NULL, "Cannot specify type of the client SSL certificate: %s",
					curl_easy_strerror(err));
			return FAIL;
		}
	}

	if ('\0' != *ssl_key_file)
	{
		char	*file_name;

		file_name = zbx_dsprintf(NULL, "%s/%s", CONFIG_SSL_KEY_LOCATION, ssl_key_file);
		zabbix_log(LOG_LEVEL_DEBUG, "using SSL private key file: '%s'", file_name);

		err = curl_easy_setopt(easyhandle, CURLOPT_SSLKEY, file_name);
		zbx_free(file_name);

		if (CURLE_OK != err)
		{
			*error = zbx_dsprintf(NULL, "Cannot specify private keyfile for TLS and SSL client cert: %s",
					curl_easy_strerror(err));
			return FAIL;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLKEYTYPE, "PEM")))
		{
			*error = zbx_dsprintf(NULL, "Cannot set type of the private key file: %s",
					curl_easy_strerror(err));
			return FAIL;
		}
	}

	if ('\0' != *ssl_key_password)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_KEYPASSWD, ssl_key_password)))
		{
			*error = zbx_dsprintf(NULL, "Cannot set passphrase to private key: %s",
					curl_easy_strerror(err));
			return FAIL;
		}
	}

	return SUCCEED;
}

int	zbx_prepare_httpauth(CURL *easyhandle, unsigned char authtype, const char *username, const char *password,
		char **error)
{
	if (HTTPTEST_AUTH_NONE != authtype)
	{
		long		curlauth = 0;
		char		auth[MAX_STRING_LEN];
		CURLcode	err;

		zabbix_log(LOG_LEVEL_DEBUG, "setting HTTPAUTH [%d]", authtype);

		switch (authtype)
		{
			case HTTPTEST_AUTH_BASIC:
				curlauth = CURLAUTH_BASIC;
				break;
			case HTTPTEST_AUTH_NTLM:
				curlauth = CURLAUTH_NTLM;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPAUTH, curlauth)))
		{
			*error = zbx_dsprintf(*error, "Cannot set HTTP server authentication method: %s",
					curl_easy_strerror(err));
			return FAIL;
		}

		zbx_snprintf(auth, sizeof(auth), "%s:%s", username, password);
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERPWD, auth)))
		{
			*error = zbx_dsprintf(*error, "Cannot set user name and password: %s",
					curl_easy_strerror(err));
			return FAIL;
		}
	}

	return SUCCEED;
}

void	zbx_add_httpheaders(char *headers, struct curl_slist **headers_slist)
{
	char	*p_begin;

	p_begin = headers;

	while ('\0' != *p_begin)
	{
		char	c, *p_end, *line;

		while ('\r' == *p_begin || '\n' == *p_begin)
			p_begin++;

		p_end = p_begin;

		while ('\0' != *p_end && '\r' != *p_end && '\n' != *p_end)
			p_end++;

		if (p_begin == p_end)
			break;

		if ('\0' != (c = *p_end))
			*p_end = '\0';
		line = zbx_strdup(NULL, p_begin);
		if ('\0' != c)
			*p_end = c;

		zbx_lrtrim(line, " \t");
		if ('\0' != *line)
			*headers_slist = curl_slist_append(*headers_slist, line);
		zbx_free(line);

		p_begin = p_end;
	}
}

#endif
