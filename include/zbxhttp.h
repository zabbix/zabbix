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

#ifndef ZABBIX_ZBXHTTP_H
#define ZABBIX_ZBXHTTP_H

#include "common.h"

int	zbx_http_punycode_encode(const char *text, char **output);
void	zbx_http_url_encode(const char *source, char **result);
int	zbx_http_url_decode(const char *source, char **result);

#ifdef HAVE_LIBCURL
int	zbx_prepare_https(CURL *easyhandle, const char *ssl_cert_file, const char *ssl_key_file,
		const char *ssl_key_password, unsigned char verify_peer, unsigned char verify_host, char **error);
int	zbx_prepare_httpauth(CURL *easyhandle, unsigned char authtype, const char *username, const char *password,
		char **error);
char	*zbx_get_httpheader(char **headers);
void	zbx_add_httpheaders(char *headers, struct curl_slist **headers_slist);
#endif

#endif

