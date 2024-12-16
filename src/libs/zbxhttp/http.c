/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxcommon.h"

#ifdef HAVE_LIBCURL

#include "zbxhttp.h"

#include "zbxnum.h"
#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxthreads.h"
#include "zbxjson.h"
#include "zbxcurl.h"

#include <stddef.h>

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
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error)
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

	if (NULL != config_source_ip)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE, config_source_ip)))
		{
			*error = zbx_dsprintf(*error, "Cannot specify source interface for outgoing traffic: %s",
					curl_easy_strerror(err));
			return FAIL;
		}
	}

	if (0 != verify_peer && NULL != config_ssl_ca_location)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CAPATH, config_ssl_ca_location)))
		{
			*error = zbx_dsprintf(*error, "Cannot specify directory holding CA certificates: %s",
					curl_easy_strerror(err));
			return FAIL;
		}
	}

	if (NULL != ssl_cert_file && '\0' != *ssl_cert_file)
	{
		char	*file_name = zbx_dsprintf(NULL, "%s/%s", config_ssl_cert_location, ssl_cert_file);

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

	if (NULL != ssl_key_file && '\0' != *ssl_key_file)
	{
		char	*file_name = zbx_dsprintf(NULL, "%s/%s", config_ssl_key_location, ssl_key_file);

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
		const char *token, char **error)
{
	CURLcode	err;
	long		curlauth = 0;
	char		auth[MAX_STRING_LEN];

	if (HTTPTEST_AUTH_NONE == authtype)
		return SUCCEED;

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
			curlauth = CURLAUTH_NEGOTIATE;
			break;
		case HTTPTEST_AUTH_DIGEST:
			curlauth = CURLAUTH_DIGEST;
			break;
		case HTTPTEST_AUTH_BEARER:
			if (SUCCEED != zbx_curl_has_bearer(error))
				return FAIL;

			curlauth = CURLAUTH_BEARER;

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

	switch (authtype)
	{
		case HTTPTEST_AUTH_BEARER:
			if (NULL == token || '\0' == *token)
			{
				*error = zbx_dsprintf(*error, "cannot set empty bearer token");
				return FAIL;
			}

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_XOAUTH2_BEARER, token)))
			{
				*error = zbx_dsprintf(*error, "Cannot set bearer: %s", curl_easy_strerror(err));
				return FAIL;
			}
			break;
		default:
			zbx_snprintf(auth, sizeof(auth), "%s:%s", username, password);
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERPWD, auth)))
			{
				*error = zbx_dsprintf(*error, "Cannot set user name and password: %s",
						curl_easy_strerror(err));
				return FAIL;
			}
			break;
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

int	zbx_http_req(const char *url, const char *header, long timeout, const char *ssl_cert_file,
		const char *ssl_key_file, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, char **out,
		const char *post_data, long *response_code, char **error)
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

	if (NULL != post_data)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, post_data)))
		{
			*error = zbx_dsprintf(*error, "Cannot specify data to POST: %s",
					curl_easy_strerror(err));
			goto clean;
		}
	}

	if (SUCCEED != zbx_http_prepare_callbacks(easyhandle, &response_header, &body, zbx_curl_ignore_cb,
			zbx_curl_write_cb, errbuf, error))
	{
		goto clean;
	}

	if (SUCCEED != zbx_http_prepare_ssl(easyhandle, ssl_cert_file, ssl_key_file, "", 1, 1, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, error))
	{
		goto clean;
	}

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

	if (SUCCEED != zbx_curl_setopt_https(easyhandle, error))
		goto clean;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, url)))
	{
		*error = zbx_dsprintf(NULL, "Cannot specify URL: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ACCEPT_ENCODING, "")))
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

static const char	*zbx_request_string(int result)
{
	switch (result)
	{
		case HTTP_REQUEST_GET:
			return "GET";
		case HTTP_REQUEST_POST:
			return "POST";
		case HTTP_REQUEST_PUT:
			return "PUT";
		case HTTP_REQUEST_HEAD:
			return "HEAD";
		default:
			return "unknown";
	}
}

static int	http_prepare_request(CURL *easyhandle, const char *posts, unsigned char request_method, char **error)
{
	CURLcode	err;

	switch (request_method)
	{
		case HTTP_REQUEST_POST:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				*error = zbx_dsprintf(*error, "Cannot specify data to POST: %s",
						curl_easy_strerror(err));
				return FAIL;
			}
			break;
		case HTTP_REQUEST_GET:
			if ('\0' == *posts)
				return SUCCEED;

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				*error = zbx_dsprintf(*error, "Cannot specify data to POST: %s",
						curl_easy_strerror(err));
				return FAIL;
			}

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CUSTOMREQUEST, "GET")))
			{
				*error = zbx_dsprintf(*error, "Cannot specify custom GET request: %s",
						curl_easy_strerror(err));
				return FAIL;
			}
			break;
		case HTTP_REQUEST_HEAD:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_NOBODY, 1L)))
			{
				*error = zbx_dsprintf(*error, "Cannot specify HEAD request: %s",
						curl_easy_strerror(err));
				return FAIL;
			}
			break;
		case HTTP_REQUEST_PUT:
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, posts)))
			{
				*error = zbx_dsprintf(*error, "Cannot specify data to POST: %s",
						curl_easy_strerror(err));
				return FAIL;
			}

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CUSTOMREQUEST, "PUT")))
			{
				*error = zbx_dsprintf(*error, "Cannot specify custom GET request: %s",
						curl_easy_strerror(err));
				return FAIL;
			}
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			*error = zbx_strdup(*error, "Unsupported request method");
			return FAIL;
	}

	return SUCCEED;
}

static void	http_add_json_header(struct zbx_json *json, char *line)
{
	char	*colon;

	if (NULL != (colon = strchr(line, ':')))
	{
		zbx_ltrim(colon + 1, " \t");

		*colon = '\0';
		zbx_json_addstring(json, line, colon + 1, ZBX_JSON_TYPE_STRING);
		*colon = ':';
	}
	else
		zbx_json_addstring(json, line, "", ZBX_JSON_TYPE_STRING);
}

static void	http_output_json(unsigned char retrieve_mode, char **buffer, zbx_http_response_t *header,
		zbx_http_response_t *body)
{
	struct zbx_json		json;
	struct zbx_json_parse	jp;
	char			*headers, *line;
	unsigned char		json_content = 0;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	headers = header->data;

	if (retrieve_mode != ZBX_RETRIEVE_MODE_CONTENT)
		zbx_json_addobject(&json, "header");

	while (NULL != (line = zbx_http_parse_header(&headers)))
	{
		if (0 == json_content &&
				0 == zbx_strncasecmp(line, "Content-Type:", ZBX_CONST_STRLEN("Content-Type:")) &&
				NULL != strstr(line, "application/json"))
		{
			json_content = 1;
		}

		if (retrieve_mode != ZBX_RETRIEVE_MODE_CONTENT)
			http_add_json_header(&json, line);

		zbx_free(line);
	}

	if (retrieve_mode != ZBX_RETRIEVE_MODE_CONTENT)
		zbx_json_close(&json);

	if (NULL != body->data)
	{
		if (0 == json_content)
		{
			zbx_json_addstring(&json, "body", body->data, ZBX_JSON_TYPE_STRING);
		}
		else if (FAIL == zbx_json_open(body->data, &jp))
		{
			zbx_json_addstring(&json, "body", body->data, ZBX_JSON_TYPE_STRING);
			zabbix_log(LOG_LEVEL_DEBUG, "received invalid JSON object %s", zbx_json_strerror());
		}
		else
		{
			zbx_lrtrim(body->data, ZBX_WHITESPACE);
			zbx_json_addraw(&json, "body", body->data);
		}
	}

	*buffer = zbx_strdup(NULL, json.buffer);
	zbx_json_free(&json);
}

CURLcode	zbx_http_request_sync_perform(CURL *easyhandle, zbx_http_context_t *context, int attempt_interval,
		int check_response_code)
{
	CURLcode	err;
	char	status_codes[] = "200,201,202,203,204,400,401,403,404,405,415,422";
	long	response_code;

	/* try to retrieve page several times depending on number of retries */
	do
	{
		*context->errbuf = '\0';

		if (CURLE_OK == (err = curl_easy_perform(easyhandle)))
		{
			if (ZBX_HTTP_CHECK_RESPONSE_CODE == check_response_code)
			{
				if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE,
						&response_code)))
				{
					zabbix_log(LOG_LEVEL_INFORMATION, "cannot get the response code: %s",
							curl_easy_strerror(err));

					goto next_attempt;
				}
				else if (FAIL == zbx_int_in_list(status_codes, (int)response_code))
					goto next_attempt;

				return err;
			}

			return err;
		}
		else
		{
			if (1 != context->max_attempts)
			{
				zabbix_log(LOG_LEVEL_INFORMATION, "cannot perform request: %s",
						'\0' == *context->errbuf ? curl_easy_strerror(err) : context->errbuf);
			}
		}

next_attempt:
		context->header.offset = 0;
		context->body.offset = 0;

		if (0 != attempt_interval && 1 < context->max_attempts)
			zbx_sleep((unsigned int)attempt_interval);
	}
	while (0 < --context->max_attempts);

	return err;
}

int	zbx_http_handle_response(CURL *easyhandle, zbx_http_context_t *context, CURLcode err, long *response_code,
		char **out, char **error)
{
	if (CURLE_OK != err)
	{
		if (CURLE_WRITE_ERROR == err)
		{
			*error = zbx_strdup(NULL, "The requested value is too large");
		}
		else
		{
			*error = zbx_dsprintf(NULL, "Cannot perform request: %s",
					'\0' == *context->errbuf ? curl_easy_strerror(err) : context->errbuf);
		}

		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, response_code)))
	{
		*error = zbx_dsprintf(NULL, "Cannot get the response code: %s", curl_easy_strerror(err));
		return FAIL;
	}

	if (NULL == context->header.data)
	{
		*error = zbx_dsprintf(NULL, "Server returned empty header");
		return FAIL;
	}

	switch (context->retrieve_mode)
	{
		case ZBX_RETRIEVE_MODE_CONTENT:
			zbx_http_convert_to_utf8(easyhandle, &context->body.data, &context->body.offset,
					&context->body.allocated);

			if (HTTP_STORE_JSON == context->output_format)
			{
				http_output_json(context->retrieve_mode, out, &context->header, &context->body);
			}
			else
			{
				if (NULL != context->body.data)
				{
					*out = context->body.data;
					context->body.data = NULL;
				}
				else
					*out = zbx_strdup(NULL, "");
			}
			break;
		case ZBX_RETRIEVE_MODE_HEADERS:
			zbx_replace_invalid_utf8(context->header.data);

			if (HTTP_STORE_JSON == context->output_format)
			{
				char		*line;
				struct zbx_json	json;
				zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
				zbx_json_addobject(&json, "header");
				char	*headers_ptr = context->header.data;
				while (NULL != (line = zbx_http_parse_header(&headers_ptr)))
				{
					http_add_json_header(&json, line);
					zbx_free(line);
				}
				*out = zbx_strdup(NULL, json.buffer);
				zbx_json_free(&json);
			}
			else
			{
				*out = context->header.data;
				context->header.data = NULL;
			}
			break;
		case ZBX_RETRIEVE_MODE_BOTH:

			zbx_replace_invalid_utf8(context->header.data);

			zbx_http_convert_to_utf8(easyhandle, &context->body.data, &context->body.offset,
					&context->body.allocated);

			if (HTTP_STORE_JSON == context->output_format)
			{
				http_output_json(context->retrieve_mode, out, &context->header, &context->body);
			}
			else
			{
				if (NULL != context->body.data)
				{
					zbx_strncpy_alloc(&context->header.data, &context->header.allocated,
							&context->header.offset, context->body.data,
							context->body.offset);
				}

				*out = context->header.data;
				context->header.data = NULL;
			}
			break;
		default:
			*error = zbx_dsprintf(NULL, "invalid retrieve mode");
			return FAIL;
	}

	return SUCCEED;
}

int	zbx_handle_response_code(char *status_codes, long response_code, const char *out, char **error)
{
	if ('\0' != *status_codes && FAIL == zbx_int_in_list(status_codes, (int)response_code))
	{
		if (NULL != out)
		{
			*error = zbx_dsprintf(NULL, "Response code \"%ld\" did not match any of the required status"
					" codes \"%s\"\n%s", response_code, status_codes, out);
		}
		else
		{
			*error = zbx_dsprintf(NULL, "Response code \"%ld\" did not match any of the required status"
					" codes \"%s\"", response_code, status_codes);
		}

		return FAIL;
	}

	return SUCCEED;
}

void	zbx_http_context_create(zbx_http_context_t *context)
{
	memset(context, 0, sizeof(zbx_http_context_t));
}

void	zbx_http_context_destroy(zbx_http_context_t *context)
{
	curl_slist_free_all(context->headers_slist);	/* must be called after curl_easy_perform() */
	zbx_free(context->body.data);
	zbx_free(context->header.data);
	curl_easy_cleanup(context->easyhandle);
}

int	zbx_http_request_prepare(zbx_http_context_t *context, unsigned char request_method, const char *url,
		const char *query_fields, char *headers, const char *posts, unsigned char retrieve_mode,
		const char *http_proxy, unsigned char follow_redirects,
		int timeout, int max_attempts, const char *ssl_cert_file, const char *ssl_key_file,
		const char *ssl_key_password, unsigned char verify_peer, unsigned char verify_host,
		unsigned char authtype, const char *username, const char *password, const char *token,
		unsigned char post_type, unsigned char output_format, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error)
{
	CURLcode		err;
	char			url_buffer[ZBX_ITEM_URL_LEN_MAX], *headers_ptr, *line;
	int			ret = NOTSUPPORTED, found = FAIL;
	zbx_curl_cb_t		curl_body_cb;
	char			application_json[] = {"Content-Type: application/json"};
	char			application_ndjson[] = {"Content-Type: application/x-ndjson"};
	char			application_xml[] = {"Content-Type: application/xml"};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() request method '%s' URL '%s%s' headers '%s'",
			__func__, zbx_request_string(request_method), url, query_fields, headers);

	zabbix_log(LOG_LEVEL_TRACE, "message body '%s'", posts);

	context->max_attempts = max_attempts;
	context->output_format = output_format;
	context->retrieve_mode = retrieve_mode;

	if (NULL == (context->easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "Cannot initialize cURL library");
		goto clean;
	}

	switch (retrieve_mode)
	{
		case ZBX_RETRIEVE_MODE_CONTENT:
		case ZBX_RETRIEVE_MODE_BOTH:
			curl_body_cb = zbx_curl_write_cb;
			break;
		case ZBX_RETRIEVE_MODE_HEADERS:
			curl_body_cb = zbx_curl_ignore_cb;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			*error = zbx_strdup(NULL, "Invalid retrieve mode");
			goto clean;
	}

	if (SUCCEED != zbx_http_prepare_callbacks(context->easyhandle, &context->header, &context->body,
			zbx_curl_write_cb, curl_body_cb, context->errbuf, error))
	{
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(context->easyhandle, CURLOPT_PROXY, http_proxy)))
	{
		*error = zbx_dsprintf(NULL, "Cannot set proxy: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(context->easyhandle, CURLOPT_FOLLOWLOCATION,
			0 == follow_redirects ? 0L : 1L)))
	{
		*error = zbx_dsprintf(NULL, "Cannot set follow redirects: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (0 != follow_redirects &&
			CURLE_OK != (err = curl_easy_setopt(context->easyhandle, CURLOPT_MAXREDIRS,
			ZBX_CURLOPT_MAXREDIRS)))
	{
		*error = zbx_dsprintf(NULL, "Cannot set number of redirects allowed: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(context->easyhandle, CURLOPT_TIMEOUT, (long)timeout)))
	{
		*error = zbx_dsprintf(NULL, "Cannot specify timeout: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != zbx_http_prepare_ssl(context->easyhandle, ssl_cert_file, ssl_key_file, ssl_key_password,
			verify_peer, verify_host, config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
			config_ssl_key_location, error))
	{
		goto clean;
	}

	if (SUCCEED != zbx_http_prepare_auth(context->easyhandle, authtype, username, password, token, error))
		goto clean;

	if (SUCCEED != http_prepare_request(context->easyhandle, posts, request_method, error))
	{
		goto clean;
	}

	headers_ptr = headers;
	while (NULL != (line = zbx_http_parse_header(&headers_ptr)))
	{
		context->headers_slist = curl_slist_append(context->headers_slist, line);

		if (FAIL == found && 0 == strncmp(line, "Content-Type:", ZBX_CONST_STRLEN("Content-Type:")))
			found = SUCCEED;

		zbx_free(line);
	}

	if (FAIL == found)
	{
		if (ZBX_POSTTYPE_JSON == post_type)
			context->headers_slist = curl_slist_append(context->headers_slist, application_json);
		else if (ZBX_POSTTYPE_XML == post_type)
			context->headers_slist = curl_slist_append(context->headers_slist, application_xml);
		else if (ZBX_POSTTYPE_NDJSON == post_type)
			context->headers_slist = curl_slist_append(context->headers_slist, application_ndjson);
	}

	if (CURLE_OK != (err = curl_easy_setopt(context->easyhandle, CURLOPT_HTTPHEADER, context->headers_slist)))
	{
		*error = zbx_dsprintf(NULL, "Cannot specify headers: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != zbx_curl_setopt_https(context->easyhandle, error))
		goto clean;

	zbx_snprintf(url_buffer, sizeof(url_buffer),"%s%s", url, query_fields);
	if (CURLE_OK != (err = curl_easy_setopt(context->easyhandle, CURLOPT_URL, url_buffer)))
	{
		*error = zbx_dsprintf(NULL, "Cannot specify URL: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(context->easyhandle, CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(NULL, "Cannot set cURL encoding option: %s", curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(context->easyhandle, CURLOPT_COOKIEFILE, "")))
	{
		*error =  zbx_dsprintf(NULL, "Cannot enable cURL cookie engine: %s", curl_easy_strerror(err));
		goto clean;
	}

	ret = SUCCEED;
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

void	zbx_http_convert_to_utf8(CURL *easyhandle, char **body, size_t *size, size_t *allocated)
{
	char		*charset;
	const char	*content_type;

	if (NULL == *body)
		return;

	content_type = zbx_curl_content_type(easyhandle);

	charset = zbx_determine_charset(content_type, *body, *size);

	if (0 != strcmp(charset, "UTF-8"))
	{
		char	*converted, *error = NULL;

		zabbix_log(LOG_LEVEL_DEBUG, "converting from charset '%s'", charset);

		if (NULL == (converted = zbx_convert_to_utf8(*body, *size, charset, &error)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot convert from charset '%s': %s", charset, error);
			zbx_free(error);
		}
		else
		{
			zbx_free(*body);

			*body = converted;
			*size = strlen(converted);
			*allocated = *size;
		}
	}

	zbx_free(charset);

	zbx_replace_invalid_utf8(*body);
}

#endif
