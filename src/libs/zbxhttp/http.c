/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "zbxhttp.h"

#ifdef HAVE_LIBCURL

extern char	*CONFIG_SOURCE_IP;

extern char	*CONFIG_SSL_CA_LOCATION;
extern char	*CONFIG_SSL_CERT_LOCATION;
extern char	*CONFIG_SSL_KEY_LOCATION;

size_t	zbx_curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t			r_size = size * nmemb;
	zbx_http_response_t	*response;

	response = (zbx_http_response_t*)userdata;

	if (ZBX_MAX_RECV_DATA_SIZE < response->offset + r_size)
		return 0;

	zbx_str_memcpy_alloc(&response->data, &response->allocated, &response->offset, (const char *)ptr, r_size);

	return r_size;
}

size_t	zbx_curl_ignore_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

int	zbx_http_prepare_callbacks(CURL *easyhandle, zbx_http_response_t *header, zbx_http_response_t *body,
		zbx_curl_cb_t header_cb, zbx_curl_cb_t body_cb, char *errbuf, char **error)
{
	CURLcode	err;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERFUNCTION, header_cb)))
	{
		*error = zbx_dsprintf(*error, "Cannot set header function: %s", curl_easy_strerror(err));
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERDATA, header)))
	{
		*error = zbx_dsprintf(*error, "Cannot set header callback: %s", curl_easy_strerror(err));
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEFUNCTION, body_cb)))
	{
		*error = zbx_dsprintf(*error, "Cannot set write function: %s", curl_easy_strerror(err));
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEDATA, body)))
	{
		*error = zbx_dsprintf(*error, "Cannot set write callback: %s", curl_easy_strerror(err));
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ERRORBUFFER, errbuf)))
	{
		*error = zbx_dsprintf(*error, "Cannot set error buffer: %s", curl_easy_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

int	zbx_http_prepare_ssl(CURL *easyhandle, const char *ssl_cert_file, const char *ssl_key_file,
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

int	zbx_http_prepare_auth(CURL *easyhandle, unsigned char authtype, const char *username, const char *password,
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
			case HTTPTEST_AUTH_NEGOTIATE:
#if LIBCURL_VERSION_NUM >= 0x072600
				curlauth = CURLAUTH_NEGOTIATE;
#else
				curlauth = CURLAUTH_GSSNEGOTIATE;
#endif
				break;
			case HTTPTEST_AUTH_DIGEST:
				curlauth = CURLAUTH_DIGEST;
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

char	*zbx_http_parse_header(char **headers)
{
	while ('\0' != **headers)
	{
		char	c, *p_end, *line;

		while ('\r' == **headers || '\n' == **headers)
			(*headers)++;

		p_end = *headers;

		while ('\0' != *p_end && '\r' != *p_end && '\n' != *p_end)
			p_end++;

		if (*headers == p_end)
			return NULL;

		if ('\0' != (c = *p_end))
			*p_end = '\0';
		line = zbx_strdup(NULL, *headers);
		if ('\0' != c)
			*p_end = c;

		*headers = p_end;

		zbx_lrtrim(line, " \t");
		if ('\0' == *line)
			zbx_free(line);
		else
			return line;
	}

	return NULL;
}

int	zbx_http_get(const char *url, const char *header, long timeout, char **out, long *response_code, char **error)
{
	CURL			*easyhandle;
	CURLcode		err;
	char			errbuf[CURL_ERROR_SIZE];
	int			ret = FAIL;
	struct curl_slist	*headers_slist = NULL;
	zbx_http_response_t	body = {0}, response_header = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() URL '%s'", __func__, url);

	*errbuf = '\0';

	if (NULL == (easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "Cannot initialize cURL library");
		goto clean;
	}

	if (SUCCEED != zbx_http_prepare_callbacks(easyhandle, &response_header, &body, zbx_curl_ignore_cb,
			zbx_curl_write_cb, errbuf, error))
	{
		goto clean;
	}

	if (SUCCEED != zbx_http_prepare_ssl(easyhandle, "", "", "", 1, 1, error))
		goto clean;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERAGENT, "Zabbix " ZABBIX_VERSION)))
	{
		*error = zbx_dsprintf(NULL, "Cannot set user agent: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROXY, "")))
	{
		*error = zbx_dsprintf(NULL, "Cannot set proxy: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, timeout)))
	{
		*error = zbx_dsprintf(NULL, "Cannot specify timeout: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPHEADER,
			(headers_slist = curl_slist_append(headers_slist, header)))))
	{
		*error = zbx_dsprintf(NULL, "Cannot specify headers: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, url)))
	{
		*error = zbx_dsprintf(NULL, "Cannot specify URL: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, ZBX_CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(NULL, "Cannot set cURL encoding option: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_dsprintf(NULL, "Cannot perform request: %s", '\0' == *errbuf ? curl_easy_strerror(err) :
				errbuf);
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, response_code)))
	{
		*error = zbx_dsprintf(NULL, "Cannot get the response code: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (NULL != body.data)
	{
		*out = body.data;
		body.data = NULL;
	}

	else
		*out = zbx_strdup(NULL, "");

	ret = SUCCEED;
clean:
	curl_slist_free_all(headers_slist);	/* must be called after curl_easy_perform() */
	curl_easy_cleanup(easyhandle);
	zbx_free(body.data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#endif
