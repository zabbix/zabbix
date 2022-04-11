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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_ZBXHTTP_H
#define ZABBIX_ZBXHTTP_H

#include "common.h"

int	zbx_http_punycode_encode_url(char **url);
void	zbx_http_url_encode(const char *source, char **result);
int	zbx_http_url_decode(const char *source, char **result);

#ifdef HAVE_LIBCURL

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
zbx_http_response_t;

size_t	zbx_curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata);
size_t	zbx_curl_ignore_cb(void *ptr, size_t size, size_t nmemb, void *userdata);

typedef size_t	(*zbx_curl_cb_t)(void *ptr, size_t size, size_t nmemb, void *userdata);

int	zbx_http_prepare_callbacks(CURL *easyhandle, zbx_http_response_t *header, zbx_http_response_t *body,
		zbx_curl_cb_t header_cb, zbx_curl_cb_t body_cb, char *errbuf, char **error);
int	zbx_http_prepare_ssl(CURL *easyhandle, const char *ssl_cert_file, const char *ssl_key_file,
		const char *ssl_key_password, unsigned char verify_peer, unsigned char verify_host, char **error);
int	zbx_http_prepare_auth(CURL *easyhandle, unsigned char authtype, const char *username, const char *password,
		char **error);
char	*zbx_http_parse_header(char **headers);

int	zbx_http_get(const char *url, const char *header, long timeout, const char *ssl_cert_file,
		const char *ssl_key_file, char **out, long *response_code, char **error);
#endif

#endif
