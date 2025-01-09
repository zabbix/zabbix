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

#ifndef ZABBIX_ZBXHTTP_H
#define ZABBIX_ZBXHTTP_H

#include "zbxcommon.h"

#define ZBX_HTTPFIELD_HEADER		0
#define ZBX_HTTPFIELD_VARIABLE		1
#define ZBX_HTTPFIELD_POST_FIELD	2
#define ZBX_HTTPFIELD_QUERY_FIELD	3

#define ZBX_POSTTYPE_RAW		0
#define ZBX_POSTTYPE_FORM		1
#define ZBX_POSTTYPE_JSON		2
#define ZBX_POSTTYPE_XML		3
#define ZBX_POSTTYPE_NDJSON		4

#define ZBX_RETRIEVE_MODE_CONTENT	0
#define ZBX_RETRIEVE_MODE_HEADERS	1
#define ZBX_RETRIEVE_MODE_BOTH		2

int	zbx_http_punycode_encode_url(char **url);
char	*zbx_determine_charset(const char *content_type, char *body, size_t size);

#ifdef HAVE_LIBCURL

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
zbx_http_response_t;

typedef struct
{
	CURL			*easyhandle;
	struct curl_slist	*headers_slist;
	zbx_http_response_t	body;
	zbx_http_response_t	header;
	char			errbuf[CURL_ERROR_SIZE];
	int			max_attempts;
	unsigned char		retrieve_mode;
	unsigned char		output_format;
}
zbx_http_context_t;

size_t	zbx_curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata);
size_t	zbx_curl_ignore_cb(void *ptr, size_t size, size_t nmemb, void *userdata);

typedef size_t	(*zbx_curl_cb_t)(void *ptr, size_t size, size_t nmemb, void *userdata);

int	zbx_http_prepare_callbacks(CURL *easyhandle, zbx_http_response_t *header, zbx_http_response_t *body,
		zbx_curl_cb_t header_cb, zbx_curl_cb_t body_cb, char *errbuf, char **error);

int	zbx_http_prepare_ssl(CURL *easyhandle, const char *ssl_cert_file, const char *ssl_key_file,
		const char *ssl_key_password, unsigned char verify_peer, unsigned char verify_host,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error);
int	zbx_http_prepare_auth(CURL *easyhandle, unsigned char authtype, const char *username, const char *password,
		const char *token, char **error);
char	*zbx_http_parse_header(char **headers);

int	zbx_http_req(const char *url, const char *header, long timeout, const char *ssl_cert_file,
		const char *ssl_key_file, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, char **out,
		const char *post_data, long *response_code, char **error);

#define HTTP_REQUEST_GET	0
#define HTTP_REQUEST_POST	1
#define HTTP_REQUEST_PUT	2
#define HTTP_REQUEST_HEAD	3

#define HTTP_STORE_RAW		0
#define HTTP_STORE_JSON		1

void	zbx_http_context_create(zbx_http_context_t *context);
void	zbx_http_context_destroy(zbx_http_context_t *context);
int	zbx_http_request_prepare(zbx_http_context_t *context, unsigned char request_method, const char *url,
		const char *query_fields, char *headers,
		const char *posts, unsigned char retrieve_mode, const char *http_proxy, unsigned char follow_redirects,
		int timeout, int max_attempts, const char *ssl_cert_file, const char *ssl_key_file,
		const char *ssl_key_password, unsigned char verify_peer, unsigned char verify_host,
		unsigned char authtype, const char *username, const char *password, const char *token,
		unsigned char post_type, unsigned char output_format, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error);

CURLcode	zbx_http_request_sync_perform(CURL *easyhandle, zbx_http_context_t *context, int attempt_interval,
		int check_response_code);
int	zbx_http_handle_response(CURL *easyhandle, zbx_http_context_t *context, CURLcode err, long *response_code,
		char **out, char **error);
int	zbx_handle_response_code(char *status_codes, long response_code, const char *out, char **error);
void	zbx_http_convert_to_utf8(CURL *easyhandle, char **data, size_t *size, size_t *allocated);

#define ZBX_HTTP_IGNORE_RESPONSE_CODE	0
#define ZBX_HTTP_CHECK_RESPONSE_CODE	1

#endif

#endif
