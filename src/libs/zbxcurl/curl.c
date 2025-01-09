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

#include "zbxcommon.h"

#ifdef HAVE_LIBCURL

#include "zbxcurl.h"

/* See https://curl.se/libcurl/c/symbols-by-name.html for information in which version a symbol was added. */

/* added in 7.85.0 (0x075500) */
#if LIBCURL_VERSION_NUM < 0x075500
#	define CURLOPT_PROTOCOLS_STR	10318L
#endif

static unsigned int	libcurl_version_num(void)
{
	return curl_version_info(CURLVERSION_NOW)->version_num;
}

static const char	*libcurl_version_str(void)
{
	return curl_version_info(CURLVERSION_NOW)->version;
}

static const char	*libcurl_ssl_version(void)
{
	return curl_version_info(CURLVERSION_NOW)->ssl_version;
}

/* curl_multi_wait() was added in cURL 7.28.0 (0x071c00). Since we support cURL library >= 7.19.1  */
/* we want to be able to compile against older cURL library. This is a wrapper that detects if the */
/* function is available at runtime. It should never be called for older library versions because  */
/* detect the version before. When cURL library requirement goes to >= 7.28.0 this function and    */
/* the structure declaration should be removed and curl_multi_wait() be used directly.             */
CURLMcode	zbx_curl_multi_wait(CURLM *multi_handle, int timeout_ms, int *numfds)
{
#if LIBCURL_VERSION_NUM < 0x071c00
	struct curl_waitfd
	{
		curl_socket_t	fd;
		short		events;
		short		revents;
	};
#endif
	static void		*handle;
	static CURLMcode	(*fptr)(CURLM *, struct curl_waitfd *, unsigned int, int, int *) = NULL;

	if (NULL == fptr)
	{
		/* this check must be performed before calling this function */
		if (SUCCEED != zbx_curl_good_for_elasticsearch(NULL))
		{
			zabbix_log(LOG_LEVEL_CRIT, "zbx_curl_multi_wait() should never be called when using cURL library"
					" <= 7.28.0 (using version %s)", libcurl_version_str());
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

#ifndef _WINDOWS
		if (NULL == (handle = dlopen(NULL, RTLD_LAZY | RTLD_NOLOAD)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot dlopen() Zabbix binary: %s", dlerror());
			exit(EXIT_FAILURE);
		}

		/* use *(void **)(&fptr) to silence the "-pedantic" warning */
		if (NULL == (*(void **)(&fptr) = dlsym(handle, "curl_multi_wait")))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot find cURL function curl_multi_wait(): %s", dlerror());
			dlclose(handle);
			exit(EXIT_FAILURE);
		}
#else
		fptr = curl_multi_wait;
#endif
	}

	return fptr(multi_handle, NULL, 0, timeout_ms, numfds);
}

static const char	*get_content_type(CURL *easyhandle)
{
	char		*content_type = NULL;
	CURLcode	err;

	err = curl_easy_getinfo(easyhandle, CURLINFO_CONTENT_TYPE, &content_type);

	if (CURLE_OK != err || NULL == content_type)
		zabbix_log(LOG_LEVEL_DEBUG, "cannot get content type: %s", curl_easy_strerror(err));
	else
		zabbix_log(LOG_LEVEL_DEBUG, "content_type '%s'", content_type);

	return content_type;
}

/* curl_easy_header() was added in cURL 7.83.0 (0x075300) */
const char	*zbx_curl_content_type(CURL *easyhandle)
{
#if LIBCURL_VERSION_NUM < 0x075300
#	define CURLH_HEADER	(1<<0)
#	define CURLH_TRAILER	(1<<1)
#	define CURLH_CONNECT	(1<<2)
#	define CURLH_1XX	(1<<3)
#	define CURLH_PSEUDO	(1<<4)

	typedef enum
	{
		CURLHE_OK,
		CURLHE_BADINDEX,
		CURLHE_MISSING,
		CURLHE_NOHEADERS,
		CURLHE_NOREQUEST,
		CURLHE_OUT_OF_MEMORY,
		CURLHE_BAD_ARGUMENT,
		CURLHE_NOT_BUILT_IN
	}
	CURLHcode;

	struct curl_header
	{
		char		*name;
		char		*value;
		size_t		amount;
		size_t		index;
		unsigned int	origin;
		void		*anchor;
	};
#endif
	static void		*handle;
	static CURLHcode	(*fptr)(CURL *, const char *, size_t, unsigned int, int, struct curl_header **) = NULL;

	struct curl_header	*type;
	unsigned int		origin;
	CURLHcode		h;

	if (libcurl_version_num() < 0x075300)
	{
		return get_content_type(easyhandle);
	}

	if (NULL == fptr)
	{
#ifndef _WINDOWS
		if (NULL == (handle = dlopen(NULL, RTLD_LAZY | RTLD_NOLOAD)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot dlopen() Zabbix binary: %s", dlerror());
			exit(EXIT_FAILURE);
		}

		/* use *(void **)(&fptr) to silence the "-pedantic" warning */
		if (NULL == (*(void **)(&fptr) = dlsym(handle, "curl_easy_header")))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot find cURL function curl_easy_header(): %s", dlerror());
			dlclose(handle);
			exit(EXIT_FAILURE);
		}
#else
		fptr = curl_easy_header;
#endif
	}

	origin = CURLH_HEADER | CURLH_TRAILER | CURLH_CONNECT | CURLH_1XX;

	if (libcurl_version_num() > 0x75400)
	{
		/* the CURLH_PSEUDO wasn't accepted until bug #9235 was fixed in 7.85.0 */
		origin |= CURLH_PSEUDO;
	}

	if (CURLHE_OK != (h = fptr(easyhandle, "Content-Type", 0, origin, -1, &type)))
	{
		if (CURLHE_NOT_BUILT_IN != h)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot retrieve Content-Type header:%u", h);
			return NULL;
		}

		/* the headers API is not compiled in, fall back to the old way of getting it */
		return get_content_type(easyhandle);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "name '%s' value '%s' amount:%lu index:%lu origin:%u",
			type->name, type->value, type->amount, type->index, type->origin);

	return type->value;
}

int	zbx_curl_protocol(const char *protocol, char **error)
{
	curl_version_info_data	*ver;
	size_t			index = 0;

	ver = curl_version_info(CURLVERSION_NOW);

	while (NULL != ver->protocols[index])
	{
		if (0 == strcasecmp(protocol, ver->protocols[index]))
			return SUCCEED;

		index++;
	}

	if (NULL != error)
	{
		*error = zbx_dsprintf(*error, "cURL library does not support \"%s\" protocol (using version %s)",
				protocol, libcurl_version_str());
	}

	return FAIL;
}

static void	setopt_error(const char *option, CURLcode err, char **error)
{
	*error = zbx_dsprintf(*error, "cURL library returned error when enabling %s: %s (using version %s)",
			option, curl_easy_strerror(err), libcurl_version_str());
}

int	zbx_curl_setopt_https(CURL *easyhandle, char **error)
{
	CURLcode	err;

/* added in 7.19.4 (0x071304), deprecated since 7.85.0 */
#if LIBCURL_VERSION_NUM < 0x071304
#	define CURLPROTO_HTTP		(1<<0)
#	define CURLPROTO_HTTPS		(1<<1)
#endif

	/* CURLOPT_PROTOCOLS (181L) is supported starting with version 7.19.4 (0x071304) */
	if (libcurl_version_num() >= 0x071304)
	{
		/* CURLOPT_PROTOCOLS was replaced by CURLOPT_PROTOCOLS_STR and deprecated in 7.85.0 (0x075500) */
		if (libcurl_version_num() >= 0x075500)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS_STR, "HTTP,HTTPS")))
			{
				setopt_error("HTTP/HTTPS", err, error);
				return FAIL;
			}
		}
		else
		{
			/* 181L is CURLOPT_PROTOCOLS, remove when cURL requirement will become >= 7.85.0 */
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, 181L, CURLPROTO_HTTP | CURLPROTO_HTTPS)))
			{
				setopt_error("HTTP/HTTPS", err, error);
				return FAIL;
			}
		}
	}

	return SUCCEED;
}

int	zbx_curl_setopt_smtps(CURL *easyhandle, char **error)
{
	CURLcode	err;

/* added in 7.20.0 (0x071400), deprecated since 7.85.0 */
#if LIBCURL_VERSION_NUM < 0x071400
#	define CURLPROTO_SMTP   	(1<<16)
#	define CURLPROTO_SMTPS  	(1<<17)
#endif

	/* CURLOPT_PROTOCOLS (181L) is supported starting with version 7.19.4 (0x071304) */
	if (libcurl_version_num() >= 0x071304)
	{
		/* CURLOPT_PROTOCOLS was replaced by CURLOPT_PROTOCOLS_STR and deprecated in 7.85.0 (0x075500) */
		if (libcurl_version_num() >= 0x075500)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROTOCOLS_STR, "SMTP,SMTPS")))
			{
				setopt_error("SMTP/SMTPS", err, error);
				return FAIL;
			}
		}
		else
		{
			/* 181L is CURLOPT_PROTOCOLS, remove when cURL requirement will become >= 7.85.0 */
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, 181L, CURLPROTO_SMTP | CURLPROTO_SMTPS)))
			{
				setopt_error("SMTP/SMTPS", err, error);
				return FAIL;
			}
		}
	}

	return SUCCEED;
}

int	zbx_curl_setopt_ssl_version(CURL *easyhandle, char **error)
{
	CURLcode	err;

/* CURL_SSLVERSION_TLSv1_2 (6) was added in 7.34.0 (0x072200) */
#if LIBCURL_VERSION_NUM < 0x072200
#	define CURL_SSLVERSION_TLSv1_2	6
#endif

	if (libcurl_version_num() >= 0x072200)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2)))
		{
			setopt_error("CURL_SSLVERSION_TLSv1_2", err, error);
			return FAIL;
		}
	}

	return SUCCEED;
}

int	zbx_curl_has_bearer(char **error)
{
	/* added in 7.61.0 (0x073d00) */
	if (libcurl_version_num() < 0x073d00)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "cURL library version %s does not support HTTP Bearer token"
					" authentication, 7.61.0 or newer is required", libcurl_version_str());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_has_ssl(char **error)
{
	if (NULL == libcurl_ssl_version())
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "cURL library does not support SSL/TLS (using version %s)",
					libcurl_version_str());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_has_smtp_auth(char **error)
{
	/* added in 7.20.0 */
	if (libcurl_version_num() < 0x071400)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "cURL library version %s does not support SMTP authentication,"
					" 7.20.0 or newer is required", libcurl_version_str());
		}

		return FAIL;
	}

	return SUCCEED;
}

int	zbx_curl_good_for_elasticsearch(char **error)
{
	/* Elasticsearch needs curl_multi_wait() which was added in 7.28.0 (0x071c00) */
	if (libcurl_version_num() < 0x071c00)
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "cURL library version %s is too old for Elasticsearch history"
					" backend, 7.28.0 or newer is required", libcurl_version_str());
		}

		return FAIL;
	}

	return SUCCEED;
}
#endif /* HAVE_LIBCURL */
