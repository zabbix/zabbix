/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
#include "zbxtypes.h"
#include <stddef.h>
#include "zbxalgo.h"

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

#if LIBCURL_VERSION_NUM >= 0x071304
	/* CURLOPT_PROTOCOLS is supported starting with version 7.19.4 (0x071304) */
	/* CURLOPT_PROTOCOLS was deprecated in favor of CURLOPT_PROTOCOLS_STR starting with version 7.85.0 (0x075500) */
#	if LIBCURL_VERSION_NUM >= 0x075500
	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS_STR, "HTTP,HTTPS")))
#	else
	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS)))
#	endif
	{
		*error = zbx_dsprintf(NULL, "Cannot set allowed protocols: %s", curl_easy_strerror(err));
		goto clean;
	}
#endif

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

static char	*get_media_parameter(const char *str, const char *key, size_t key_len)
{
	const char	*ptr, *next;
	char		*charset = NULL;

	for (;;str = next + 1)
	{
		if (NULL == (ptr = strchr(str, '=')))
			break;

		ptr++;

		if (NULL != (next = strchr(str, ';')))
		{
			if (next <= ptr)
				break;
		}

		for (;' ' == *str; str++);

		if (0 == zbx_strncasecmp(str, key, key_len))
		{
			if (NULL != next)
			{
				size_t	alloc_len = 0, offset = 0;

				zbx_strncpy_alloc(&charset, &alloc_len, &offset, ptr, next - ptr - 1);
			}
			else
				charset = zbx_strdup(NULL, ptr);
			break;
		}

		if (NULL == next)
			break;
	}

	return charset;
}

static int	parse_attribute_name(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr = data + pos;

	if (NULL != strchr(" \"'=<>`/", *ptr))
		return FAIL;

	while ('\0' != *(++ptr))
	{
		if (' ' == *ptr || '=' == *ptr)
			break;
	}

	loc->l = pos;
	loc->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}

static size_t	skip_spaces(const char *data, size_t pos)
{
	while (' ' == data[pos] || '\t' == data[pos])
		pos++;

	return pos;
}

static int	parse_attribute_op(const char *data, size_t pos, zbx_strloc_t *loc)
{
	if ('=' == data[pos])
	{
		loc->l = pos;
		loc->r = pos;

		return SUCCEED;
	}

	return FAIL;
}

static int	parse_attribute_value(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr;
	char		*charlist;
	unsigned char	quoted;

	ptr = data + pos;

	if ('"' == *ptr)
	{
		charlist = "\"";
		quoted = 1;
	}
	else if ('\'' == *ptr)
	{
		charlist = "'";
		quoted = 1;
	}
	else if (NULL == strchr(" \"'=<>`", *ptr))
	{
		quoted = 0;
		charlist = " \"'=<>`";
	}
	else
		return FAIL;

	loc->l = pos;

	while (NULL == strchr(charlist, *(++ptr)))
	{
		if ('\0' == *ptr)
			return FAIL;
	}

	if (1 == quoted)
		loc->r = (size_t)(ptr - data);
	else
		loc->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}

static int	parse_attribute_key_value(const char *data, size_t pos, zbx_strloc_t *loc_name, zbx_strloc_t *loc_op,
		zbx_strloc_t *loc_value)
{
	if (SUCCEED != parse_attribute_name(data, pos, loc_name))
		return FAIL;

	pos = skip_spaces(data, loc_name->r + 1);

	if (SUCCEED != parse_attribute_op(data, pos, loc_op))
	{
		*loc_value = *loc_op = *loc_name;
		return SUCCEED;
	}

	pos = skip_spaces(data, loc_op->r + 1);

	if (SUCCEED != parse_attribute_value(data, pos, loc_value))
		return FAIL;

	return SUCCEED;
}

static int	str_loc_cmp(const char *src, const zbx_strloc_t *loc, const char *text, size_t text_len)
{
	ZBX_RETURN_IF_NOT_EQUAL(loc->r - loc->l + 1, text_len);
	return zbx_strncasecmp(src + loc->l, text, text_len);
}

static char	*str_loc_dup(const char *src, const zbx_strloc_t *loc)
{
	char	*str;
	size_t	len;

	len = loc->r - loc->l + 1;
	str = zbx_malloc(NULL, len + 1);
	memcpy(str, src + loc->l, len);
	str[len] = '\0';

	return str;
}

static size_t	parse_html_attributes(const char *data, char **content, char **charset)
{
	size_t		pos = 0;
	zbx_strloc_t	loc_name, loc_op, loc_value, loc_content;
	int		http_equiv_content_found = 0, content_found = 0;

	pos = skip_spaces(data, pos);

	while ('>' != data[pos])
	{
		if (FAIL == parse_attribute_key_value(data, pos, &loc_name, &loc_op, &loc_value))
			break;

		pos = skip_spaces(data, loc_value.r + 1);

		if (0 == str_loc_cmp(data, &loc_name, "http-equiv", ZBX_CONST_STRLEN("http-equiv")) &&
				0 == str_loc_cmp(data, &loc_value, "\"content-type\"",
				ZBX_CONST_STRLEN("\"content-type\"")))
		{
			http_equiv_content_found = 1;
		}
		else if (0 == str_loc_cmp(data, &loc_name, "content", ZBX_CONST_STRLEN("content")))
		{
			loc_content = loc_value;
			content_found = 1;
		}
		else if (0 == str_loc_cmp(data, &loc_name, "charset", ZBX_CONST_STRLEN("\"charset\"")))
		{
			*charset = str_loc_dup(data, &loc_value);
			return pos;
		}
	}

	if (1 == http_equiv_content_found && 1 == content_found)
		*content = str_loc_dup(data, &loc_content);

	return pos;
}

static void	html_get_charset_content(const char *data, char **charset, char **content)
{
	while (NULL != (data = strstr(data, "<meta")) && NULL == *charset && NULL == *content)
	{
		data += ZBX_CONST_STRLEN("<meta");
		data += parse_html_attributes(data, content, charset);
	}
}

static char	*get_media_type_charset(const char *content_type, char *body, size_t size)
{
	const char	*ptr;
	char		*charset = NULL;

	if (NULL != content_type)
	{
		for (;' ' == *content_type; content_type++);

		if (NULL != (ptr = strchr(content_type, ';')))
			charset = get_media_parameter(ptr + 1, "charset", ZBX_CONST_STRLEN("charset"));
	}

	if (NULL == charset)
	{
		char	*content = NULL;

		html_get_charset_content(body, &charset, &content);

		if (NULL != content && NULL == charset)
		{
			if (NULL != (ptr = strchr(content, ';')))
			{
				charset = get_media_parameter(ptr + 1, "charset",
					ZBX_CONST_STRLEN("charset"));
			}
		}

		zbx_free(content);

		if (NULL == charset)
		{
			const char	*bom_encoding = get_bom_econding(body, size);

			if ('\0' != *bom_encoding)
				charset = zbx_strdup(NULL, bom_encoding);
			else if (SUCCEED == zbx_is_utf8(body))
				charset = zbx_strdup(NULL, "UTF-8");
			else
				charset = zbx_strdup(NULL, "WINDOWS-1252");
		}
	}

	zbx_lrtrim(charset, " ");
	zbx_strupper(charset);

	return charset;
}

void	zbx_http_convert_to_utf8(CURL *easyhandle, char **body, size_t *size, size_t *allocated)
{
	struct curl_header	*type;
	CURLHcode		h;
	char			*charset, *content_type = NULL;

	if (CURLHE_OK != (h = curl_easy_header(easyhandle, "Content-Type", 0,
			CURLH_HEADER|CURLH_TRAILER|CURLH_CONNECT|CURLH_1XX|CURLH_PSEUDO, -1, &type)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot retrieve Content-Type header:%u", h);
	}
	else
	{
		zabbix_log(LOG_LEVEL_TRACE, "name '%s' value '%s' amount:%lu index: %lu"
				" origin:%u", type->name, type->value, type->amount,
				type->index, type->origin);

		content_type = type->value;
	}

	charset = get_media_type_charset(content_type, *body, *size);

	if (0 != strcmp(charset, "UTF-8"))
	{
		char	*converted;

		zabbix_log(LOG_LEVEL_INFORMATION, "converting from charset '%s'", charset);

		converted = convert_to_utf8(*body, *size, charset);
		zbx_free(*body);

		*body = converted;
		*size = strlen(converted);
		*allocated = *size;
	}

	zbx_free(charset);
}

#endif
