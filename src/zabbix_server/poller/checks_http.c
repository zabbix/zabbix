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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "checks_http.h"
#ifdef HAVE_LIBCURL
#include "log.h"

extern void	add_headers(char *headers, struct curl_slist **headers_slist);

#define HTTPCHECK_REQUEST_GET	0
#define HTTPCHECK_REQUEST_POST	1
#define HTTPCHECK_REQUEST_PUT	2
#define HTTPCHECK_REQUEST_HEAD	3

#define HTTPCHECK_RETRIEVE_MODE_CONTENT	0
#define HTTPCHECK_RETRIEVE_MODE_HEADERS	1
#define HTTPCHECK_RETRIEVE_MODE_BOTH	2

extern char	*CONFIG_SOURCE_IP;

extern char	*CONFIG_SSL_CA_LOCATION;
extern char	*CONFIG_SSL_CERT_LOCATION;
extern char	*CONFIG_SSL_KEY_LOCATION;

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
zbx_http_response_t;

static const char	*zbx_request_string(int result)
{
	switch (result)
	{
		case HTTPCHECK_REQUEST_GET:
			return "GET";
		case HTTPCHECK_REQUEST_POST:
			return "POST";
		case HTTPCHECK_REQUEST_PUT:
			return "PUT";
		case HTTPCHECK_REQUEST_HEAD:
			return "HEAD";
		default:
			return "unknown";
	}
}

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t			r_size = size * nmemb;
	zbx_http_response_t	*response;

	response = (zbx_http_response_t*)userdata;
	zbx_strncpy_alloc(&response->data, &response->allocated, &response->offset, (const char *)ptr, r_size);

	return r_size;
}

static size_t	curl_ignore_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

static int	prepare_https(CURL *easyhandle, const char *ssl_cert_file, const char *ssl_key_file,
		const char *ssl_key_password, unsigned char verify_peer, unsigned char verify_host,
		AGENT_RESULT *result)
{
	CURLcode	err;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER, 0 == verify_peer ? 0L : 1L)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set verify the peer's SSL certificate: %s",
				curl_easy_strerror(err)));
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST, 0 == verify_host ? 0L : 2L)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set verify the certificate's name against host: %s",
				curl_easy_strerror(err)));
		return FAIL;
	}

	if (NULL != CONFIG_SOURCE_IP)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE, CONFIG_SOURCE_IP)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify source interface for outgoing"
					" traffic: %s", curl_easy_strerror(err)));
			return FAIL;
		}
	}

	if (0 != verify_peer && NULL != CONFIG_SSL_CA_LOCATION)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CAPATH, CONFIG_SSL_CA_LOCATION)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify directory holding CA certificates: %s",
					curl_easy_strerror(err)));
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
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set SSL client certificate: %s",
					curl_easy_strerror(err)));
			return FAIL;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLCERTTYPE, "PEM")))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify type of the client SSL certificate:"
					" %s", curl_easy_strerror(err)));
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
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify private keyfile for TLS and"
					" SSL client cert: %s", curl_easy_strerror(err)));
			return FAIL;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLKEYTYPE, "PEM")))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set type of the private key file: %s",
					curl_easy_strerror(err)));
			return FAIL;
		}
	}

	if ('\0' != *ssl_key_password)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_KEYPASSWD, ssl_key_password)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set passphrase to private key: %s",
					curl_easy_strerror(err)));
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	prepare_auth(CURL *easyhandle, unsigned char authtype, const char *username, const char *password,
		AGENT_RESULT *result)
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
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set HTTP server authentication method: %s",
					curl_easy_strerror(err)));
			return FAIL;
		}

		zbx_snprintf(auth, sizeof(auth), "%s:%s", username, password);
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERPWD, auth)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set user name and password: %s",
					curl_easy_strerror(err)));
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	prepare_request(CURL *easyhandle, const char *posts, unsigned char request_method, AGENT_RESULT *result)
{
	CURLcode	err;

	switch (request_method)
	{
		case HTTPCHECK_REQUEST_POST:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify data to POST: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}
			break;
		case HTTPCHECK_REQUEST_GET:
			if ('\0' == *posts)
				return SUCCEED;

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify data to POST: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CUSTOMREQUEST, "GET")))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify custom GET request: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}
			break;
		case HTTPCHECK_REQUEST_HEAD:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_NOBODY, 1L)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify HEAD request: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}
			break;
		case HTTPCHECK_REQUEST_PUT:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify data to POST: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CUSTOMREQUEST, "PUT")))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify custom GET request: %s",
						curl_easy_strerror(err)));
				return FAIL;
			}
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported request method"));
			return FAIL;
	}

	return SUCCEED;
}

int	get_value_http(const DC_ITEM *item, AGENT_RESULT *result)
{
	const char		*__function_name = "get_value_http";
	CURL			*easyhandle;
	CURLcode		err;
	char			errbuf[CURL_ERROR_SIZE];
	int			ret = NOTSUPPORTED, timeout_seconds;
	long			response_code;
	struct curl_slist	*headers_slist = NULL;
	zbx_http_response_t	body = {0}, header = {0};
	size_t			(*curl_header_cb)(void *ptr, size_t size, size_t nmemb, void *userdata);
	size_t			(*curl_body_cb)(void *ptr, size_t size, size_t nmemb, void *userdata);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() request method '%s' URL '%s' headers '%s' message body '%s'",
			__function_name, zbx_request_string(item->request_method), item->url, item->headers,
			item->posts);

	if (NULL == (easyhandle = curl_easy_init()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize cURL library"));
		goto clean;
	}

	switch (item->retrieve_mode)
	{
		case HTTPCHECK_RETRIEVE_MODE_CONTENT:
			curl_header_cb = curl_ignore_cb;
			curl_body_cb = curl_write_cb;
			break;
		case HTTPCHECK_RETRIEVE_MODE_HEADERS:
			curl_header_cb = curl_write_cb;
			curl_body_cb = curl_ignore_cb;
			break;
		case HTTPCHECK_RETRIEVE_MODE_BOTH:
			curl_header_cb = curl_write_cb;
			curl_body_cb = curl_write_cb;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid retrieve mode"));
			goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERFUNCTION, curl_header_cb)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set header function: %s",
				curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERDATA, &header)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set header callback: %s",
				curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEFUNCTION, curl_body_cb)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set write function: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEDATA, &body)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set write callback: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ERRORBUFFER, errbuf)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set error buffer: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROXY, item->http_proxy)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set proxy: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_FOLLOWLOCATION,
			0 == item->follow_redirects ? 0L : 1L)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set follow redirects: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (0 != item->follow_redirects &&
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAXREDIRS, ZBX_CURLOPT_MAXREDIRS)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set number of redirects allowed: %s",
				curl_easy_strerror(err)));
		goto clean;
	}

	if (FAIL == is_time_suffix(item->timeout, &timeout_seconds, strlen(item->timeout)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid timeout: %s", item->timeout));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, (long)timeout_seconds)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify timeout: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (SUCCEED != prepare_https(easyhandle, item->ssl_cert_file, item->ssl_key_file, item->ssl_key_password,
			item->verify_peer, item->verify_host, result))
	{
		goto clean;
	}

	if (SUCCEED != prepare_auth(easyhandle, item->authtype, item->username, item->password, result))
		goto clean;

	if (SUCCEED != prepare_request(easyhandle, item->posts, item->request_method, result))
		goto clean;

	add_headers(item->headers, &headers_slist);
	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPHEADER, headers_slist)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify headers: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, item->url)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot specify URL: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot perform request: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &response_code)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot get the response code: %s", curl_easy_strerror(err)));
		goto clean;
	}

	if ('\0' != *item->status_codes && FAIL == int_in_list(item->status_codes, response_code))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Response code \"%ld\" did not match any of the"
				" required status codes \"%s\"", response_code, item->status_codes));
		goto clean;
	}

	switch (item->retrieve_mode)
	{
		case HTTPCHECK_RETRIEVE_MODE_CONTENT:
			if (NULL == body.data)
				body.data = zbx_strdup(NULL, "");

			zabbix_log(LOG_LEVEL_TRACE, "received body '%s'", body.data);
			SET_TEXT_RESULT(result, body.data);
			body.data = NULL;
			break;
		case HTTPCHECK_RETRIEVE_MODE_HEADERS:
			if (NULL == header.data)
				header.data = zbx_strdup(NULL, "");

			zabbix_log(LOG_LEVEL_TRACE, "received header '%s'", header.data);
			SET_TEXT_RESULT(result, header.data);
			header.data = NULL;
			break;
		case HTTPCHECK_RETRIEVE_MODE_BOTH:
			zbx_strncpy_alloc(&header.data, &header.allocated, &header.offset, body.data, body.offset);
			zabbix_log(LOG_LEVEL_TRACE, "received response '%s'", header.data);
			SET_TEXT_RESULT(result, header.data);
			header.data = NULL;
			break;
	}

	ret = SUCCEED;
clean:
	curl_slist_free_all(headers_slist);	/* must be called after curl_easy_perform() */
	curl_easy_cleanup(easyhandle);
	zbx_free(body.data);
	zbx_free(header.data);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
#endif
