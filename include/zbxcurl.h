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

#ifndef ZABBIX_CURL_H
#define ZABBIX_CURL_H

#include "zbxcommon.h"

#ifdef HAVE_LIBCURL

#define ZBX_CURLOPT_MAXREDIRS	10L

/* Currently required cURL library version is 7.19.1 (see configure.ac). When */
/* it is increased there all the following functionality must be revised.     */

/* added in 7.19.4 (0x071304), deprecated since 7.85.0 */
#if LIBCURL_VERSION_NUM < 0x071304
#	define CURLOPT_PROTOCOLS	181L
#	define CURLPROTO_HTTP		(1<<0)
#	define CURLPROTO_HTTPS		(1<<1)
#	define CURLPROTO_SMTP   	(1<<16)
#	define CURLPROTO_SMTPS  	(1<<17)
#endif

/* added in 7.20.0 (0x071400) */
#if LIBCURL_VERSION_NUM < 0x071400
#	define CURLOPT_MAIL_FROM	186L
#	define CURLOPT_MAIL_RCPT	187L
#endif

/* renamed in 7.21.6 */
#if LIBCURL_VERSION_NUM < 0x071501
#	define CURLOPT_ACCEPT_ENCODING	CURLOPT_ENCODING
#endif

/* added in 7.28.0 (0x071c00) */
#if LIBCURL_VERSION_NUM < 0x071c00
CURLMcode	curl_multi_wait(CURLM *multi_handle, void *, unsigned int extra_nfds, int timeout_ms, int *numfds);
#endif

/* added in 7.33.0 (0x072100) */
#if LIBCURL_VERSION_NUM < 0x072100
#	define CURLOPT_XOAUTH2_BEARER	220L
#endif

/* added in 7.38.0 (0x072600) */
#if LIBCURL_VERSION_NUM < 0x072600
#	define CURLAUTH_NEGOTIATE	(((unsigned long)1)<<2)
#endif

/* added in 7.55.0 (0x073700) */
#if LIBCURL_VERSION_NUM < 0x073700
#	define CURLINFO_SPEED_DOWNLOAD_T	CURLINFO_SPEED_DOWNLOAD
#	define curl_off_t			double
#endif

/* added in 7.61.0 (0x073d00) */
#if LIBCURL_VERSION_NUM < 0x073d00
#	define CURLAUTH_BEARER		(((unsigned long)1)<<6)
#endif

/* added in 7.85.0 (0x075500) */
#if LIBCURL_VERSION_NUM < 0x075500
#	define CURLOPT_PROTOCOLS_STR	318L
#endif

int	zbx_curl_protocol(const char *protocol, char **error);
int	zbx_curl_setopt_https(CURL *easyhandle, char **error);
int	zbx_curl_setopt_smtps(CURL *easyhandle, char **error);
int	zbx_curl_has_bearer(char **error);
int	zbx_curl_has_multi_wait(char **error);
int	zbx_curl_has_ssl(char **error);
int	zbx_curl_has_smtp_auth(char **error);

#endif /* HAVE_LIBCURL */

#endif /* ZABBIX_CURL_H */
