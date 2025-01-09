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

#ifndef ZABBIX_CURL_H
#define ZABBIX_CURL_H

#include "zbxcommon.h"

#ifdef HAVE_LIBCURL

#define ZBX_CURLOPT_MAXREDIRS	10L

/* Currently required cURL library version is 7.19.1 (see configure.ac). When */
/* it is increased there all the following functionality must be revised.     */

/* added in 7.20.0 (0x071400) */
#if LIBCURL_VERSION_NUM < 0x071400
#	define CURLOPT_MAIL_FROM	186L
#	define CURLOPT_MAIL_RCPT	187L
#endif

/* renamed in 7.21.6 */
#if LIBCURL_VERSION_NUM < 0x071501
#	define CURLOPT_ACCEPT_ENCODING	CURLOPT_ENCODING
#endif

/* curl_multi_wait() was added in cURL 7.28.0 (0x071c00). Since we support cURL library >= 7.19.1  */
/* we want to be able to compile against older cURL library. This is a wrapper that detects if the */
/* function is available at runtime. It should never be called for older library versions because  */
/* detect the version before. When cURL library requirement goes to >= 7.28.0 this function should */
/* be removed and curl_multi_wait() be used directly.                                              */
CURLMcode	zbx_curl_multi_wait(CURLM *multi_handle, int timeout_ms, int *numfds);

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

/* this is a wrapper for curl_easy_header() that was added in cURL 7.83.0 (0x075300) */
const char	*zbx_curl_content_type(CURL *easyhandle);

int	zbx_curl_protocol(const char *protocol, char **error);
int	zbx_curl_setopt_https(CURL *easyhandle, char **error);
int	zbx_curl_setopt_smtps(CURL *easyhandle, char **error);
int	zbx_curl_setopt_ssl_version(CURL *easyhandle, char **error);
int	zbx_curl_has_ssl(char **error);
int	zbx_curl_has_bearer(char **error);
int	zbx_curl_has_smtp_auth(char **error);
int	zbx_curl_good_for_elasticsearch(char **error);

#endif /* HAVE_LIBCURL */

#endif /* ZABBIX_CURL_H */
