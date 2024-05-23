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

#include "common.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxhttp.h"
#include "zbxembed.h"
#include "httprequest.h"
#include "embed.h"
#include "duktape.h"

#ifdef HAVE_LIBCURL

#define ZBX_HTTPAUTH_NONE		CURLAUTH_NONE
#define ZBX_HTTPAUTH_BASIC		CURLAUTH_BASIC
#define ZBX_HTTPAUTH_DIGEST		CURLAUTH_DIGEST
#if LIBCURL_VERSION_NUM >= 0x072600
#	define ZBX_HTTPAUTH_NEGOTIATE	CURLAUTH_NEGOTIATE
#else
#	define ZBX_HTTPAUTH_NEGOTIATE	CURLAUTH_GSSNEGOTIATE
#endif
#define ZBX_HTTPAUTH_NTLM		CURLAUTH_NTLM

extern char	*CONFIG_SOURCE_IP;

typedef struct
{
	CURL			*handle;
	struct curl_slist	*headers;
	char			*data;
	char			*headers_in;
	size_t			data_alloc;
	size_t			data_offset;
	size_t			headers_in_alloc;
	size_t			headers_in_offset;
	unsigned char		custom_header;
	size_t			headers_sz;
}
zbx_es_httprequest_t;

/* ZBX_CURL_SETOPT() macro is a code snippet to make code shorter and facilitate resource deallocation */
/* in case of error. Be careful with using ZBX_CURL_SETOPT(), duk_push_error_object() and duk_error()  */
/* in functions - it is easy to get memory leaks because duk_error() causes longjmp().                 */
/* Note that the caller of ZBX_CURL_SETOPT() must define variable 'int err_index' and label 'out'.     */
#define ZBX_CURL_SETOPT(ctx, handle, opt, value, err)							\
	if (CURLE_OK != (err = curl_easy_setopt(handle, opt, value)))					\
	{												\
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR,					\
				"cannot set cURL option " #opt ": %s.", curl_easy_strerror(err));	\
		goto out;										\
	}

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t			r_size = size * nmemb;
	zbx_es_httprequest_t	*request = (zbx_es_httprequest_t *)userdata;

	zbx_strncpy_alloc(&request->data, &request->data_alloc, &request->data_offset, (const char *)ptr, r_size);

	return r_size;
}

static size_t	curl_header_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t			r_size = size * nmemb;
	zbx_es_httprequest_t	*request = (zbx_es_httprequest_t *)userdata;

	zbx_strncpy_alloc(&request->headers_in, &request->headers_in_alloc, &request->headers_in_offset, (const char *)ptr, r_size);

	return r_size;
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest                                                   *
 *                                                                            *
 * Purpose: return backing C structure embedded in CurlHttpRequest object     *
 *                                                                            *
 ******************************************************************************/
static zbx_es_httprequest_t *es_httprequest(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;

	duk_push_this(ctx);
	duk_get_prop_string(ctx, -1, "\xff""\xff""d");
	request = (zbx_es_httprequest_t *)duk_to_pointer(ctx, -1);
	duk_pop(ctx);

	return request;
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_dtor                                              *
 *                                                                            *
 * Purpose: CurlHttpRequest destructor                                        *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_dtor(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;

	duk_get_prop_string(ctx, 0, "\xff""\xff""d");

	if (NULL != (request = (zbx_es_httprequest_t *)duk_to_pointer(ctx, -1)))
	{
		zbx_es_env_t	*env;

		if (NULL == (env = zbx_es_get_env(ctx)))
			return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

		env->http_req_objects--;

		if (NULL != request->headers)
			curl_slist_free_all(request->headers);
		if (NULL != request->handle)
			curl_easy_cleanup(request->handle);
		zbx_free(request->data);
		zbx_free(request->headers_in);
		zbx_free(request);

		duk_push_pointer(ctx, NULL);
		duk_put_prop_string(ctx, 0, "\xff""\xff""d");
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_ctor                                              *
 *                                                                            *
 * Purpose: CurlHttpRequest constructor                                       *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_ctor(duk_context *ctx)
{
#define MAX_HTTPREQUEST_OBJECT_COUNT	10
	zbx_es_httprequest_t	*request;
	CURLcode		err;
	zbx_es_env_t		*env;
	int			err_index = -1;

	if (!duk_is_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	if (MAX_HTTPREQUEST_OBJECT_COUNT == env->http_req_objects)
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "maximum count of HttpRequest objects was reached");

	duk_push_this(ctx);

	request = (zbx_es_httprequest_t *)zbx_malloc(NULL, sizeof(zbx_es_httprequest_t));
	memset(request, 0, sizeof(zbx_es_httprequest_t));

	if (NULL == (request->handle = curl_easy_init()))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "cannot initialize cURL library");
		goto out;
	}

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_COOKIEFILE, "", err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_FOLLOWLOCATION, 1L, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_WRITEFUNCTION, curl_write_cb, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_WRITEDATA, request, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_PRIVATE, request, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_SSL_VERIFYPEER, 0L, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_SSL_VERIFYHOST, 0L, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_HEADERFUNCTION, curl_header_cb, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_HEADERDATA, request, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_INTERFACE, CONFIG_SOURCE_IP, err);

	duk_push_string(ctx, "\xff""\xff""d");
	duk_push_pointer(ctx, request);
	duk_def_prop(ctx, -3, DUK_DEFPROP_HAVE_VALUE | DUK_DEFPROP_CLEAR_WRITABLE | DUK_DEFPROP_HAVE_ENUMERABLE |
			DUK_DEFPROP_HAVE_CONFIGURABLE);

	duk_push_c_function(ctx, es_httprequest_dtor, 1);
	duk_set_finalizer(ctx, -2);
out:
	if (-1 != err_index)
	{
		if (NULL != request->handle)
			curl_easy_cleanup(request->handle);
		zbx_free(request);

		return duk_throw(ctx);
	}

	env->http_req_objects++;

	return 0;
#undef MAX_HTTPREQUEST_OBJECT_COUNT
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_add_header                                        *
 *                                                                            *
 * Purpose: CurlHttpRequest.SetHeader method                                  *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_add_header(duk_context *ctx)
{
#define ZBX_ES_MAX_HEADERS_SIZE	ZBX_KIBIBYTE * 128
	zbx_es_httprequest_t	*request;
	CURLcode		err;
	char			*utf8 = NULL;
	int			err_index = -1;
	size_t			header_sz;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "internal scripting error: null object");

	if (SUCCEED != zbx_cesu8_to_utf8(duk_to_string(ctx, 0), &utf8))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot convert header to utf8");
		goto out;
	}

	header_sz = strlen(utf8);

	if (ZBX_ES_MAX_HEADERS_SIZE < request->headers_sz + header_sz)
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "headers exceeded maximum size of "
				ZBX_FS_UI64 " bytes.", ZBX_ES_MAX_HEADERS_SIZE);

		goto out;
	}

	request->headers = curl_slist_append(request->headers, utf8);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_HTTPHEADER, request->headers, err);
	request->custom_header = 1;
	request->headers_sz += header_sz + 1;
out:
	zbx_free(utf8);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
#undef ZBX_ES_MAX_HEADERS_SIZE
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_clear_header                                      *
 *                                                                            *
 * Purpose: CurlHttpRequest.ClearHeader method                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_clear_header(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "internal scripting error: null object");

	curl_slist_free_all(request->headers);
	request->headers = NULL;
	request->custom_header = 0;
	request->headers_sz = 0;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_query                                             *
 *                                                                            *
 * Purpose: CurlHttpRequest HTTP request implementation                       *
 *                                                                            *
 * Parameters: ctx          - [IN] the scripting engine context               *
 *             http_request - [IN] the HTTP request (GET, PUT, POST, DELETE)  *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_query(duk_context *ctx, const char *http_request)
{
	zbx_es_httprequest_t	*request;
	char			*url = NULL, *contents = NULL;
	CURLcode		err;
	int			err_index = -1;
	zbx_es_env_t		*env;
	zbx_uint64_t		timeout_ms, elapsed_ms;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	elapsed_ms = zbx_get_duration_ms(&env->start_time);
	timeout_ms = (zbx_uint64_t)env->timeout * 1000;

	if (elapsed_ms >= timeout_ms)
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "script execution timeout occurred");
		goto out;
	}

	if (SUCCEED != zbx_cesu8_to_utf8(duk_to_string(ctx, 0), &url))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot convert URL to utf8");
		goto out;
	}

	if (0 == duk_is_null_or_undefined(ctx, 1))
	{
		if (SUCCEED != zbx_cesu8_to_utf8(duk_to_string(ctx, 1), &contents))
		{
			err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR,
					"cannot convert request contents to utf8");
			goto out;
		}
	}

	if (NULL == (request = es_httprequest(ctx)))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "internal scripting error: null object");
		goto out;
	}

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_URL, url, err);

	if (0 == request->custom_header)
	{
		struct zbx_json_parse	jp;

		if (NULL != request->headers)
		{
			curl_slist_free_all(request->headers);
			request->headers = NULL;
			request->headers_sz = 0;
		}

		if (NULL != contents)
		{
			if (SUCCEED == zbx_json_open(contents, &jp))
				request->headers = curl_slist_append(NULL, "Content-Type: application/json");
			else
				request->headers = curl_slist_append(NULL, "Content-Type: text/plain");
		}
	}

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_HTTPHEADER, request->headers, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_CUSTOMREQUEST, http_request, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_TIMEOUT_MS, timeout_ms - elapsed_ms, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_POSTFIELDS, ZBX_NULL2EMPTY_STR(contents), err);
	ZBX_CURL_SETOPT(ctx, request->handle, ZBX_CURLOPT_ACCEPT_ENCODING, "", err);
#if LIBCURL_VERSION_NUM >= 0x071304
	/* CURLOPT_PROTOCOLS is supported starting with version 7.19.4 (0x071304) */
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS, err);
#endif

	request->data_offset = 0;
	request->headers_in_offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(request->handle)))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "cannot get URL: %s.",
				curl_easy_strerror(err));
		goto out;
	}
out:
	zbx_free(url);
	zbx_free(contents);

	if (-1 != err_index)
		return duk_throw(ctx);

	duk_push_string(ctx, request->data);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_get                                               *
 *                                                                            *
 * Purpose: CurlHttpRequest.Get method                                        *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_get(duk_context *ctx)
{
	return es_httprequest_query(ctx, "GET");
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_put                                               *
 *                                                                            *
 * Purpose: CurlHttpRequest.Put method                                        *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_put(duk_context *ctx)
{
	return es_httprequest_query(ctx, "PUT");
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_post                                              *
 *                                                                            *
 * Purpose: CurlHttpRequest.Post method                                       *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_post(duk_context *ctx)
{
	return es_httprequest_query(ctx, "POST");
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_delete                                            *
 *                                                                            *
 * Purpose: CurlHttpRequest.Delete method                                     *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_delete(duk_context *ctx)
{
	return es_httprequest_query(ctx, "DELETE");
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_set_proxy                                         *
 *                                                                            *
 * Purpose: CurlHttpRequest.SetProxy method                                   *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_set_proxy(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;
	CURLcode		err;
	int			err_index = -1;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "internal scripting error: null object");

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_PROXY, duk_to_string(ctx, 0), err);
out:
	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_status                                            *
 *                                                                            *
 * Purpose: CurlHttpRequest.Status method                                     *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_status(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;
	long			response_code;
	CURLcode		err;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "internal scripting error: null object");

	if (CURLE_OK != (err = curl_easy_getinfo(request->handle, CURLINFO_RESPONSE_CODE, &response_code)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "cannot obtain request status: %s", curl_easy_strerror(err));

	duk_push_number(ctx, (duk_double_t)response_code);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_obj_put_http_header                                           *
 *                                                                            *
 * Purpose: puts http header <field>: <value> as object property/value        *
 *                                                                            *
 * Parameters: ctx    - [IN] the duktape context                              *
 *             idx    - [IN] the object index on duktape stack                *
 *             header - [IN] the http header to parse and put                 *
 *                                                                            *
 ******************************************************************************/
static void	es_put_header(duk_context *ctx, int idx, char *header)
{
	char	*value;

	if (NULL == (value = strchr(header, ':')))
		return;

	*value++ = '\0';
	while (' ' == *value || '\t' == *value)
		value++;

	duk_push_string(ctx, value);

	/* duk_put_prop_string() throws error on failure, no need to check return code */
	(void)duk_put_prop_string(ctx, idx, header);
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_get_headers                                       *
 *                                                                            *
 * Purpose: CurlHttpRequest.GetHeaders method                                 *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_get_headers(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;
	duk_idx_t		idx;
	char			*ptr, *header;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "internal scripting error: null object");

	idx = duk_push_object(ctx);

	if (0 == request->headers_in_offset)
		return 1;

	for (ptr = request->headers_in; NULL != (header = zbx_http_get_header(&ptr)); )
	{
		es_put_header(ctx, idx, header);
		zbx_free(header);
	}

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_httprequest_set_httpauth                                      *
 *                                                                            *
 * Purpose: CurlHttpRequest.SetHttpAuth method                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_set_httpauth(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;
	char			*username = NULL, *password = NULL;
	int			err_index = -1, mask;
	CURLcode		err;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "internal scripting error: null object");

	mask = duk_to_int32(ctx, 0);

	if (0 != (mask & ~(ZBX_HTTPAUTH_BASIC | ZBX_HTTPAUTH_DIGEST | ZBX_HTTPAUTH_NEGOTIATE | ZBX_HTTPAUTH_NTLM)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "invalid HTTP authentication mask");

	if (0 == duk_is_null_or_undefined(ctx, 1))
	{
		if (SUCCEED != zbx_cesu8_to_utf8(duk_to_string(ctx, 1), &username))
		{
			err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot convert username to utf8");
			goto out;
		}
	}

	if (0 == duk_is_null_or_undefined(ctx, 2))
	{
		if (SUCCEED != zbx_cesu8_to_utf8(duk_to_string(ctx, 2), &password))
		{
			err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot convert username to utf8");
			goto out;
		}
	}

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_HTTPAUTH, mask, err);

	if (NULL != username)
		ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_USERNAME, username, err);

	if (NULL != password)
		ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_PASSWORD, password, err);
out:
	zbx_free(password);
	zbx_free(username);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

static const duk_function_list_entry	httprequest_methods[] = {
	{"AddHeader", es_httprequest_add_header, 1},
	{"ClearHeader", es_httprequest_clear_header, 0},
	{"Get", es_httprequest_get, 2},
	{"Put", es_httprequest_put, 2},
	{"Post", es_httprequest_post, 2},
	{"Delete", es_httprequest_delete, 2},
	{"Status", es_httprequest_status, 0},
	{"SetProxy", es_httprequest_set_proxy, 1},
	{"GetHeaders", es_httprequest_get_headers, 0},
	{"SetHttpAuth", es_httprequest_set_httpauth, 3},
	{NULL, NULL, 0}
};

#else

static duk_ret_t	es_httprequest_ctor(duk_context *ctx)
{
	if (!duk_is_constructor_call(ctx))
		return DUK_RET_EVAL_ERROR;

	return duk_error(ctx, DUK_RET_EVAL_ERROR, "missing cURL library");
}

static const duk_function_list_entry	httprequest_methods[] = {
	{NULL, NULL, 0}
};
#endif

static int	es_httprequest_create_prototype(duk_context *ctx)
{
	duk_push_c_function(ctx, es_httprequest_ctor, 0);
	duk_push_object(ctx);

	duk_put_function_list(ctx, -1, httprequest_methods);

	if (1 != duk_put_prop_string(ctx, -2, "prototype"))
		return FAIL;

	if (1 != duk_put_global_string(ctx, "CurlHttpRequest"))
		return FAIL;

	return SUCCEED;
}

int	zbx_es_init_httprequest(zbx_es_t *es, char **error)
{
	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		return FAIL;
	}

	if (FAIL == es_httprequest_create_prototype(es->env->ctx))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);
		return FAIL;
	}

#ifdef HAVE_LIBCURL
	duk_push_number(es->env->ctx, ZBX_HTTPAUTH_NONE);
	duk_put_global_string(es->env->ctx, "HTTPAUTH_NONE");
	duk_push_number(es->env->ctx, ZBX_HTTPAUTH_BASIC);
	duk_put_global_string(es->env->ctx, "HTTPAUTH_BASIC");
	duk_push_number(es->env->ctx, ZBX_HTTPAUTH_DIGEST);
	duk_put_global_string(es->env->ctx, "HTTPAUTH_DIGEST");
	duk_push_number(es->env->ctx, ZBX_HTTPAUTH_NEGOTIATE);
	duk_put_global_string(es->env->ctx, "HTTPAUTH_NEGOTIATE");
	duk_push_number(es->env->ctx, ZBX_HTTPAUTH_NTLM);
	duk_put_global_string(es->env->ctx, "HTTPAUTH_NTLM");
#endif

	return SUCCEED;
}
