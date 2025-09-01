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

#include "httprequest.h"
#include "embed.h"

#include "duktape.h"

#ifdef HAVE_LIBCURL

#include "global.h"

#include "zbxtime.h"
#include "zbxjson.h"
#include "zbxstr.h"
#include "zbxhttp.h"
#include "zbxcurl.h"
#include "zbxalgo.h"

#define ZBX_HTTPAUTH_NONE	CURLAUTH_NONE
#define ZBX_HTTPAUTH_BASIC	CURLAUTH_BASIC
#define ZBX_HTTPAUTH_DIGEST	CURLAUTH_DIGEST
#define ZBX_HTTPAUTH_NEGOTIATE	CURLAUTH_NEGOTIATE
#define ZBX_HTTPAUTH_NTLM	CURLAUTH_NTLM

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
#define ZBX_CURL_SETOPT(ctx, handle, opt, value, err)								\
	do													\
	{													\
		if (CURLE_OK != (err = curl_easy_setopt(handle, opt, value)))					\
		{												\
			err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR,				\
					"cannot set cURL option " #opt ": %s.", curl_easy_strerror(err));	\
			goto out;										\
		}												\
	}													\
	while(0)

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t			r_size = size * nmemb;
	zbx_es_httprequest_t	*request = (zbx_es_httprequest_t *)userdata;

	zbx_str_memcpy_alloc(&request->data, &request->data_alloc, &request->data_offset, (const char *)ptr, r_size);

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
 * Purpose: return backing C structure embedded in HttpRequest object         *
 *                                                                            *
 ******************************************************************************/
static zbx_es_httprequest_t *es_httprequest(duk_context *ctx)
{
	zbx_es_env_t		*env;
	zbx_es_httprequest_t	*request;
	void			*objptr;

	if (NULL == (env = zbx_es_get_env(ctx)))
	{
		(void)duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "cannot access internal environment");

		return NULL;
	}

	duk_push_this(ctx);
	objptr = duk_require_heapptr(ctx, -1);
	duk_pop(ctx);

	if (NULL == (request = (zbx_es_httprequest_t *)es_obj_get_data(env, objptr, ES_OBJ_HTTPREQUEST)))
		(void)duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "cannot find native data attached to object");

	return request;
}

void	es_httprequest_free(void *data)
{
	zbx_es_httprequest_t	*request = (zbx_es_httprequest_t *)data;

	if (NULL != request->headers)
		curl_slist_free_all(request->headers);
	if (NULL != request->handle)
		curl_easy_cleanup(request->handle);
	zbx_free(request->data);
	zbx_free(request->headers_in);
	zbx_free(request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest destructor                                            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_dtor(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;
	zbx_es_env_t		*env;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	if (NULL != (request = (zbx_es_httprequest_t *)es_obj_detach_data(env, duk_require_heapptr(ctx, -1),
			ES_OBJ_HTTPREQUEST)))
	{
		env->http_req_objects--;
		es_httprequest_free(request);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest constructor                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_ctor(duk_context *ctx)
{
#define MAX_HTTPREQUEST_OBJECT_COUNT	10
	zbx_es_httprequest_t	*request;
	CURLcode		err;
	zbx_es_env_t		*env;
	int			err_index = -1;
	void			*objptr;

	if (!duk_is_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	if (MAX_HTTPREQUEST_OBJECT_COUNT == env->http_req_objects)
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "maximum count of HttpRequest objects was reached");

	duk_push_this(ctx);
	objptr = duk_require_heapptr(ctx, -1);

	request = (zbx_es_httprequest_t *)zbx_malloc(NULL, sizeof(zbx_es_httprequest_t));
	memset(request, 0, sizeof(zbx_es_httprequest_t));
	es_obj_attach_data(env, objptr, request, ES_OBJ_HTTPREQUEST);

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

	if (NULL != env->config_source_ip)
		ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_INTERFACE, env->config_source_ip, err);

	duk_push_c_function(ctx, es_httprequest_dtor, 1);
	duk_set_finalizer(ctx, -2);
out:
	if (-1 != err_index)
	{
		(void)es_obj_detach_data(env, objptr, ES_OBJ_HTTPREQUEST);

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
 * Purpose: HttpRequest.SetHeader method                                      *
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

	if (SUCCEED != es_duktape_string_decode(duk_safe_to_string(ctx, 0), &utf8))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot convert header to utf8");
		goto out;
	}

	if (NULL == (request = es_httprequest(ctx)))
	{
		err_index = 0;
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
 * Purpose: HttpRequest.ClearHeader method                                    *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_clear_header(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_throw(ctx);

	curl_slist_free_all(request->headers);
	request->headers = NULL;
	request->custom_header = 0;
	request->headers_sz = 0;

	return 0;
}

typedef enum
{
	CONTENT_TYPE_UNKNOWN,
	CONTENT_TYPE_APPLICATION_JSON,
	CONTENT_TYPE_TEXT_PLAIN
}
zbx_es_content_type_t;

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest HTTP request implementation                           *
 *                                                                            *
 * Parameters: ctx          - [IN] the scripting engine context               *
 *             http_request - [IN] the HTTP request (GET, PUT, POST, DELETE)  *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_query(duk_context *ctx, const char *http_request)
{
	zbx_es_httprequest_t	*request;
	char			*url = NULL, *contents = NULL, *error = NULL;
	CURLcode		err;
	int			err_index = -1;
	zbx_es_env_t		*env;
	zbx_uint64_t		timeout_ms, elapsed_ms;
	duk_size_t		contents_len = 0;
	zbx_es_content_type_t	content_type = CONTENT_TYPE_UNKNOWN;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	elapsed_ms = zbx_get_duration_ms(&env->start_time);
	timeout_ms = (zbx_uint64_t)env->timeout * 1000;

	if (elapsed_ms >= timeout_ms)
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "script execution timeout occurred");
		goto out;
	}

	if (SUCCEED != es_duktape_string_decode(duk_safe_to_string(ctx, 0), &url))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot convert URL to utf8");
		goto out;
	}

	if (0 == duk_is_null_or_undefined(ctx, 1))
	{
		if (NULL == (contents = es_get_buffer_dyn(ctx, 1, &contents_len)))
		{
			err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot obtain second parameter");
			goto out;
		}

		if (DUK_TYPE_STRING == duk_get_type(ctx, 1))
		{
			struct zbx_json_parse	jp;

			if (SUCCEED == zbx_json_open(contents, &jp))
				content_type = CONTENT_TYPE_APPLICATION_JSON;
			else
				content_type = CONTENT_TYPE_TEXT_PLAIN;
		}
	}

	if (NULL == (request = es_httprequest(ctx)))
	{
		err_index = duk_get_top_index(ctx);
		goto out;
	}

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_URL, url, err);

	if (0 == request->custom_header)
	{
		if (NULL != request->headers)
		{
			curl_slist_free_all(request->headers);
			request->headers = NULL;
			request->headers_sz = 0;
		}

		/* the post parameter will be converted to string and have terminating zero */
		/* unless it had buffer or object type                                      */
		switch (content_type)
		{
			case CONTENT_TYPE_APPLICATION_JSON:
				request->headers = curl_slist_append(NULL, "Content-Type: application/json");
				break;
			case CONTENT_TYPE_TEXT_PLAIN:
				request->headers = curl_slist_append(NULL, "Content-Type: text/plain");
				break;
			default:
				break;
		}
	}

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_HTTPHEADER, request->headers, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_CUSTOMREQUEST, http_request, err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_TIMEOUT_MS, timeout_ms - elapsed_ms, err);

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_POSTFIELDS, ZBX_NULL2EMPTY_STR(contents), err);
	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_POSTFIELDSIZE, (long)contents_len, err);

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_ACCEPT_ENCODING, "", err);

	if (SUCCEED != zbx_curl_setopt_https(request->handle, &error))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "%s", error);
		goto out;
	}

	request->data_offset = 0;
	request->headers_in_offset = 0;

	if (CURLE_OK != (err = curl_easy_perform(request->handle)))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "cannot get URL: %s.",
				curl_easy_strerror(err));
		goto out;
	}

	if (NULL != request->data)
		zbx_http_convert_to_utf8(request->handle, &request->data, &request->data_offset, &request->data_alloc);
out:
	zbx_free(url);
	zbx_free(contents);
	zbx_free(error);

	if (-1 != err_index)
		return duk_throw(ctx);

	/* request->data already contains valid utf-8 string, sto it can be pushed directly */
	duk_push_lstring(ctx, request->data, request->data_offset);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.Get method                                            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_get(duk_context *ctx)
{
	return es_httprequest_query(ctx, "GET");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.Put method                                            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_put(duk_context *ctx)
{
	return es_httprequest_query(ctx, "PUT");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.Post method                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_post(duk_context *ctx)
{
	return es_httprequest_query(ctx, "POST");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.Delete method                                         *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_delete(duk_context *ctx)
{
	return es_httprequest_query(ctx, "DELETE");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.head method                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_head(duk_context *ctx)
{
	return es_httprequest_query(ctx, "HEAD");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.patch method                                          *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_patch(duk_context *ctx)
{
	return es_httprequest_query(ctx, "PATCH");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.options method                                        *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_options(duk_context *ctx)
{
	return es_httprequest_query(ctx, "OPTIONS");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.trace method                                          *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_trace(duk_context *ctx)
{
	return es_httprequest_query(ctx, "TRACE");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.connect method                                        *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_connect(duk_context *ctx)
{
	return es_httprequest_query(ctx, "CONNECT");
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.customRequest method                                  *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_customrequest(duk_context *ctx)
{
	const char	*method;

	if (0 != duk_is_null_or_undefined(ctx, 0))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "HTTP method cannot be undefined or null");

	method = duk_safe_to_string(ctx, 0);
	duk_remove(ctx, 0);

	return es_httprequest_query(ctx, method);
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.SetProxy method                                       *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_set_proxy(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;
	CURLcode		err;
	int			err_index = -1;
	const char		*proxy;

	proxy = duk_safe_to_string(ctx, 0);

	if (NULL == (request = es_httprequest(ctx)))
		return duk_throw(ctx);

	ZBX_CURL_SETOPT(ctx, request->handle, CURLOPT_PROXY, proxy, err);
out:
	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.Status method                                         *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_status(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;
	long			response_code;
	CURLcode		err;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_throw(ctx);

	if (CURLE_OK != (err = curl_easy_getinfo(request->handle, CURLINFO_RESPONSE_CODE, &response_code)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "cannot obtain request status: %s", curl_easy_strerror(err));

	duk_push_number(ctx, (duk_double_t)response_code);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves value of a header                                       *
 *                                                                            *
 * Parameters: header    - [IN] the http header to extract value from         *
 *             value_out - [OUT] the value                                    *
 *                                                                            *
 ******************************************************************************/
static int	parse_header(char *header, char **value_out)
{
	char *value;

	if (NULL == (value = strchr(header, ':')))
		return FAIL;

	*value++ = '\0';
	while (' ' == *value || '\t' == *value)
		value++;

	*value_out = value;

	return SUCCEED;
}

/******************************************************************************
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

	if (FAIL == parse_header(header, &value))
		return;

	es_push_result_string(ctx, value, strlen(value));

	/* duk_put_prop_string() throws error on failure, no need to check return code */
	(void)duk_put_prop_string(ctx, idx, header);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve headers from request in form of strings                  *
 *                                                                            *
 * Parameters: ctx     - [IN] the duktape context                             *
 *             request - [IN] the request to retrieve headers from            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	get_headers_as_strings(duk_context *ctx, zbx_es_httprequest_t *request)
{
	char			*ptr, *header;
	duk_idx_t		idx;

	idx = duk_push_object(ctx);

	if (0 == request->headers_in_offset)
		return 1;

	for (ptr = request->headers_in; NULL != (header = zbx_http_parse_header(&ptr)); )
	{
		es_put_header(ctx, idx, header);
		zbx_free(header);
	}

	return 1;
}

typedef struct
{
	char			*name;
	zbx_vector_str_t	values;
}
zbx_cached_header_t;

static void	cached_headers_free(zbx_cached_header_t *header)
{
	zbx_vector_str_clear_ext(&header->values, zbx_str_free);
	zbx_vector_str_destroy(&header->values);
	zbx_free(header->name);
	zbx_free(header);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve headers from request in form of arrays                   *
 *                                                                            *
 * Parameters: ctx     - [IN] the duktape context                             *
 *             request - [IN] the request to retrieve headers from            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	get_headers_as_arrays(duk_context *ctx, zbx_es_httprequest_t *request)
{
	char			*ptr, *header;
	zbx_vector_ptr_t	headers;
	duk_idx_t		idx;
	int			i, j;

	zbx_vector_ptr_create(&headers);

	idx = duk_push_object(ctx);

	if (0 == request->headers_in_offset)
		goto out;

	for (ptr = request->headers_in; NULL != (header = zbx_http_parse_header(&ptr)); )
	{
		char			*value;
		zbx_cached_header_t	*existing_header = NULL;

		if (FAIL == parse_header(header, &value))
		{
			zbx_free(header);
			continue;
		}

		for (j = 0; j < headers.values_num; j++)
		{
			zbx_cached_header_t *h = (zbx_cached_header_t*)headers.values[j];

			if (0 == strcmp(header, h->name))
			{
				existing_header = h;
				zbx_vector_str_append(&existing_header->values, zbx_strdup(NULL, value));
				zbx_free(header);

				break;
			}
		}

		if (NULL == existing_header)
		{
			zbx_cached_header_t	*cached_header;

			cached_header = zbx_malloc(NULL, sizeof(zbx_cached_header_t));

			cached_header->name = header;
			zbx_vector_str_create(&cached_header->values);
			zbx_vector_str_append(&cached_header->values, zbx_strdup(NULL, value));
			zbx_vector_ptr_append(&headers, cached_header);
		}
	}

	for (i = 0; i < headers.values_num; i++) {
		zbx_cached_header_t	*h = (zbx_cached_header_t*)headers.values[i];
		duk_idx_t		arr_idx;

		arr_idx = duk_push_array(ctx);

		for (j = 0; j < h->values.values_num; j++)
		{
			es_push_result_string(ctx, h->values.values[j], strlen(h->values.values[j]));
			duk_put_prop_index(ctx, arr_idx, (duk_uarridx_t)j);
		}

		(void)duk_put_prop_string(ctx, idx, h->name);
	}

out:
	zbx_vector_ptr_clear_ext(&headers, (zbx_mem_free_func_t)cached_headers_free);
	zbx_vector_ptr_destroy(&headers);
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.getHeaders method                                     *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_get_headers(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;

	if (NULL == (request = es_httprequest(ctx)))
		return duk_throw(ctx);

	if (0 == duk_is_null_or_undefined(ctx, 0))
	{
		duk_bool_t	as_array;

		as_array = duk_to_boolean(ctx, 0);

		if (0 != as_array)
			return get_headers_as_arrays(ctx, request);
	}

	return get_headers_as_strings(ctx, request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: HttpRequest.SetHttpAuth method                                    *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_httprequest_set_httpauth(duk_context *ctx)
{
	zbx_es_httprequest_t	*request;
	char			*username = NULL, *password = NULL;
	int			err_index = -1, mask;
	CURLcode		err;

	mask = duk_to_int32(ctx, 0);

	if (0 != (mask & ~(ZBX_HTTPAUTH_BASIC | ZBX_HTTPAUTH_DIGEST | ZBX_HTTPAUTH_NEGOTIATE | ZBX_HTTPAUTH_NTLM)))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "invalid HTTP authentication mask");

	if (0 == duk_is_null_or_undefined(ctx, 1))
	{
		if (SUCCEED != es_duktape_string_decode(duk_safe_to_string(ctx, 1), &username))
		{
			err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot convert username to utf8");
			goto out;
		}
	}

	if (0 == duk_is_null_or_undefined(ctx, 2))
	{
		if (SUCCEED != es_duktape_string_decode(duk_safe_to_string(ctx, 2), &password))
		{
			err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot convert username to utf8");
			goto out;
		}
	}

	if (NULL == (request = es_httprequest(ctx)))
	{
		err_index = 0;
		goto out;
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
	{"addHeader", es_httprequest_add_header, 1},
	{"clearHeader", es_httprequest_clear_header, 0},
	{"get", es_httprequest_get, 2},
	{"put", es_httprequest_put, 2},
	{"post", es_httprequest_post, 2},
	{"delete", es_httprequest_delete, 2},
	{"head", es_httprequest_head, 2},
	{"patch", es_httprequest_patch, 2},
	{"options", es_httprequest_options, 2},
	{"trace", es_httprequest_trace, 2},
	{"connect", es_httprequest_connect, 2},
	{"getStatus", es_httprequest_status, 0},
	{"setProxy", es_httprequest_set_proxy, 1},
	{"getHeaders", es_httprequest_get_headers, 1},
	{"setHttpAuth", es_httprequest_set_httpauth, 3},
	{"customRequest", es_httprequest_customrequest, 3},
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

static int	es_httprequest_create_prototype(duk_context *ctx, const char *obj_name,
		const duk_function_list_entry *methods)
{
	duk_push_c_function(ctx, es_httprequest_ctor, 0);
	duk_push_object(ctx);

	es_put_function_list(ctx, -1, methods);

	if (1 != duk_put_prop_string(ctx, -2, "prototype"))
		return FAIL;

	if (1 != duk_put_global_string(ctx, obj_name))
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

	if (FAIL == es_httprequest_create_prototype(es->env->ctx, "HttpRequest", httprequest_methods))
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
