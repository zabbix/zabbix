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

#include "zbxembed.h"
#include "embed_xml.h"
#include "embed.h"

#include "httprequest.h"
#include "zabbix.h"
#include "global.h"
#include "console.h"

#include "zbxjson.h"
#include "zbxstr.h"
#include "webdriver.h"
#include "browser_element.h"
#include "browser_alert.h"

#define ZBX_ES_MEMORY_LIMIT	(1024 * 1024 * 512)
#define ZBX_ES_STACK_LIMIT	1000

/* maximum number of consequent runtime errors after which it's treated as fatal error */
#define ZBX_ES_MAX_CONSEQUENT_RT_ERROR	3

#define ZBX_ES_SCRIPT_HEADER	"function(value){"
#define ZBX_ES_SCRIPT_FOOTER	"\n}"

typedef struct
{
	const void		*heapptr;	/* js object heap ptr */
	void			*data;
	zbx_es_obj_type_t	type;
}
zbx_es_obj_data_t;

/******************************************************************************
 *                                                                            *
 * Purpose: fatal error handler                                               *
 *                                                                            *
 ******************************************************************************/
static void	es_handle_error(void *udata, const char *msg)
{
	zbx_es_env_t	*env = (zbx_es_env_t *)udata;

	zabbix_log(LOG_LEVEL_WARNING, "Cannot process javascript, fatal error: %s", msg);

	env->fatal_error = 1;
	env->error = zbx_strdup(env->error, msg);
	longjmp(env->loc, 1);
}

/*
 * Memory allocation routines to track and limit script memory usage.
 */

static void	*es_malloc(void *udata, duk_size_t size)
{
	zbx_es_env_t	*env = (zbx_es_env_t *)udata;
	uint64_t	*uptr;

	if (env->total_alloc + size + 8 > env->max_total_alloc)
		env->max_total_alloc = env->total_alloc + size + 8;

	if (env->total_alloc + size + 8 > ZBX_ES_MEMORY_LIMIT)
	{
		if (NULL == env->ctx)
			env->error = zbx_strdup(env->error, "cannot allocate memory");

		return NULL;
	}

	env->total_alloc += (size + 8);
	uptr = zbx_malloc(NULL, size + 8);
	*uptr++ = size;

	return uptr;
}

static void	*es_realloc(void *udata, void *ptr, duk_size_t size)
{
	zbx_es_env_t	*env = (zbx_es_env_t *)udata;
	uint64_t	*uptr = ptr;
	size_t		old_size;

	if (NULL != uptr)
	{
		--uptr;
		old_size = *uptr + 8;
	}
	else
		old_size = 0;

	if (env->total_alloc + size + 8 - old_size > env->max_total_alloc)
		env->max_total_alloc = env->total_alloc + size + 8 - old_size;

	if (env->total_alloc + size + 8 - old_size > ZBX_ES_MEMORY_LIMIT)
	{
		if (NULL == env->ctx)
			env->error = zbx_strdup(env->error, "cannot allocate memory");

		return NULL;
	}

	env->total_alloc += size + 8 - old_size;
	uptr = zbx_realloc(uptr, size + 8);
	*uptr++ = size;

	return uptr;
}

static void	es_free(void *udata, void *ptr)
{
	zbx_es_env_t	*env = (zbx_es_env_t *)udata;
	uint64_t	*uptr = ptr;

	if (NULL != ptr)
	{
		env->total_alloc -= (*(--uptr) + 8);
		zbx_free(uptr);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decodes 3-byte utf-8 sequence                                     *
 *                                                                            *
 * Parameters: ptr - [IN] pointer to the 3 byte sequence                      *
 *             out - [OUT] decoded value                                      *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
static int	utf8_decode_3byte_sequence(const char *ptr, zbx_uint32_t *out)
{
	*out = ((unsigned char)*ptr++ & 0xFu) << 12;
	if (0x80 != (*ptr & 0xC0))
		return FAIL;

	*out |= ((unsigned char)*ptr++ & 0x3Fu) << 6;
	if (0x80 != (*ptr & 0xC0))
		return FAIL;

	*out |= ((unsigned char)*ptr & 0x3Fu);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: decodes duktape string into utf-8                                 *
 *                                                                            *
 * Parameters: duk_str - [IN] pointer to the first char of NULL terminated    *
 *                       Duktape string                                       *
 *             out_str - [OUT] on success, pointer to pointer to the first    *
 *                       char of allocated NULL terminated UTF8 string        *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
int	es_duktape_string_decode(const char *duk_str, char **out_str)
{
	const char	*in, *end;
	char		*out;
	size_t		len;

	len = strlen(duk_str);
	out = *out_str = zbx_malloc(*out_str, len + 1);
	end = duk_str + len;

	for (in = duk_str; in < end;)
	{
		if (0x7f >= (unsigned char)*in)
		{
			*out++ = *in++;
			continue;
		}

		if (0xdf >= (unsigned char)*in)
		{
			if (2 > end - in)
				goto fail;

			*out++ = *in++;
			*out++ = *in++;
			continue;
		}

		if (0xef >= (unsigned char)*in)
		{
			zbx_uint32_t	c1, c2, u;

			if (3 > end - in || FAIL == utf8_decode_3byte_sequence(in, &c1))
				goto fail;

			if (0xd800 > c1 || 0xdbff < c1)
			{
				/* normal 3-byte sequence */
				*out++ = *in++;
				*out++ = *in++;
				*out++ = *in++;
				continue;
			}

			/* decode unicode supplementary character represented as surrogate pair */
			in += 3;
			if (3 > end - in || FAIL == utf8_decode_3byte_sequence(in, &c2) || 0xdc00 > c2 || 0xdfff < c2)
				goto fail;

			u = 0x10000 + ((((zbx_uint32_t)c1 & 0x3ff) << 10) | (c2 & 0x3ff));
			*out++ = (char)(0xf0 |  u >> 18);
			*out++ = (char)(0x80 | (u >> 12 & 0x3f));
			*out++ = (char)(0x80 | (u >> 6 & 0x3f));
			*out++ = (char)(0x80 | (u & 0x3f));
			in += 3;
			continue;
		}

		/* duktape can use the four-byte UTF-8 style supplementary character sequence */
		if (0xf0 >= (unsigned char)*in)
		{
			if (4 > end - in)
				goto fail;

			*out++ = *in++;
			*out++ = *in++;
			*out++ = *in++;
			*out++ = *in++;
			continue;
		}

		goto fail;
	}
	*out = '\0';

	return SUCCEED;
fail:
	zbx_free(*out_str);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: push result string on duktape value stack                         *
 *                                                                            *
 * Comments: The string might be modified by this function.                   *
 *                                                                            *
 ******************************************************************************/
void	es_push_result_string(duk_context *ctx, char *str, size_t size)
{
	zbx_replace_invalid_utf8(str);
	duk_push_lstring(ctx, str, size);
}

/******************************************************************************
 *                                                                            *
 * Purpose: timeout checking callback                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_check_timeout(void *udata)
{
	zbx_es_env_t	*env = (zbx_es_env_t *)udata;

	if (time(NULL) - env->start_time.sec > env->timeout)
		return 1;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes embedded scripting engine                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_es_init(zbx_es_t *es)
{
	es->env = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys embedded scripting engine                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_es_destroy(zbx_es_t *es)
{
	char	*error = NULL;

	if (SUCCEED == zbx_es_is_env_initialized(es) && SUCCEED != zbx_es_destroy_env(es, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot destroy embedded scripting engine environment: %s", error);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes embedded scripting engine environment                 *
 *                                                                            *
 * Parameters: es               - [IN] embedded scripting engine              *
 *             config_source_ip - [IN]                                        *
 *             error            - [OUT] error message                         *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_init_env(zbx_es_t *es, const char *config_source_ip, char **error)
{
	volatile int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	es->env = zbx_malloc(NULL, sizeof(zbx_es_env_t));
	memset(es->env, 0, sizeof(zbx_es_env_t));
	es->env->max_total_alloc = 0;

	es->env->config_source_ip = config_source_ip;

	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		goto out;
	}

	if (NULL == (es->env->ctx = duk_create_heap(es_malloc, es_realloc, es_free, es->env, es_handle_error)))
	{
		*error = zbx_strdup(*error, "cannot create context");
		goto out;
	}

	/* initialize Zabbix object */
	zbx_es_init_zabbix(es, error);

	/* initialize console object */
	zbx_es_init_console(es, error);

	/* remove Duktape object */
	duk_push_global_object(es->env->ctx);
	duk_del_prop_string(es->env->ctx, -1, "Duktape");
	duk_pop(es->env->ctx);

	es_init_global_functions(es);

	/* put environment object to be accessible from duktape C calls */
	duk_push_global_stash(es->env->ctx);

	duk_push_string(es->env->ctx, "\xff""\xff""zbx_env");
	duk_push_pointer(es->env->ctx, (void *)es->env);
	duk_def_prop(es->env->ctx, -3, DUK_DEFPROP_HAVE_VALUE | DUK_DEFPROP_CLEAR_WRITABLE | DUK_DEFPROP_HAVE_ENUMERABLE |
			DUK_DEFPROP_HAVE_CONFIGURABLE);

	/* JSON parse/stringify is used internally, store them into stash to prevent them */
	/* from being freed when assigning null to them in scripts                        */
	duk_get_global_string(es->env->ctx, "JSON");			/* [stash,JSON] */
	duk_push_string(es->env->ctx, "\xff""\xff""duk_json_parse");	/* [stash,JSON,"_parse"] */
	duk_get_prop_string(es->env->ctx, -2, "parse");			/* [stash,JSON,"_parse",JSON.parse] */
	es->env->json_parse = duk_get_heapptr(es->env->ctx, -1);

	duk_def_prop(es->env->ctx, -4, DUK_DEFPROP_HAVE_VALUE | DUK_DEFPROP_CLEAR_WRITABLE | DUK_DEFPROP_HAVE_ENUMERABLE |
			DUK_DEFPROP_HAVE_CONFIGURABLE);	/* [stash,JSON] */

	duk_push_string(es->env->ctx, "\xff""\xff""duk_json_stringify");	/* [stash,JSON,"_stringify"] */
	duk_get_prop_string(es->env->ctx, -2, "stringify");	/* [stash,JSON,"_stringify",JSON.stringify] */
	es->env->json_stringify = duk_get_heapptr(es->env->ctx, -1);

	duk_def_prop(es->env->ctx, -4, DUK_DEFPROP_HAVE_VALUE | DUK_DEFPROP_CLEAR_WRITABLE | DUK_DEFPROP_HAVE_ENUMERABLE |
			DUK_DEFPROP_HAVE_CONFIGURABLE);	/* [stash,JSON] */

	duk_pop(es->env->ctx);

	/* initialize HttpRequest prototype */
	if (FAIL == zbx_es_init_httprequest(es, error))
		goto out;

	if (FAIL == zbx_es_init_xml(es, error))
		goto out;

	es->env->timeout = ZBX_ES_TIMEOUT;

	zbx_hashset_create(&es->env->objmap, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		zbx_es_debug_disable(es);
		zbx_free(es->env->error);
		zbx_free(es->env);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

static void	es_objmap_destroy(zbx_hashset_t *objmap)
{
#ifdef HAVE_LIBCURL
	zbx_hashset_iter_t	iter;
	zbx_es_obj_data_t	*obj;

	zbx_hashset_iter_reset(objmap, &iter);
	while (NULL != (obj = (zbx_es_obj_data_t *)zbx_hashset_iter_next(&iter)))
	{
		switch (obj->type)
		{
			case ES_OBJ_HTTPREQUEST:
				es_httprequest_free(obj->data);
				break;
			case ES_OBJ_BROWSER:
				webdriver_release((zbx_webdriver_t *)obj->data);
				break;
			case ES_OBJ_ELEMENT:
				wd_element_free((zbx_wd_element_t *)obj->data);
				break;
			case ES_OBJ_ALERT:
				wd_alert_free((zbx_wd_alert_t *)obj->data);
				break;
		}
	}
#endif

	zbx_hashset_destroy(objmap);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys initialized embedded scripting engine environment        *
 *                                                                            *
 * Parameters: es    - [IN] the embedded scripting engine                     *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_destroy_env(zbx_es_t *es, char **error)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != setjmp(es->env->loc))
	{
		ret = FAIL;
		*error = zbx_strdup(*error, es->env->error);
		goto out;
	}

	duk_destroy_heap(es->env->ctx);
	es_objmap_destroy(&es->env->objmap);

	zbx_es_debug_disable(es);

	zbx_free(es->env->browser_endpoint);
	zbx_free(es->env->error);
	zbx_free(es->env);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __func__, zbx_result_string(ret),
		ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the scripting engine environment is initialized         *
 *                                                                            *
 * Parameters: es    - [IN] the embedded scripting engine                     *
 *                                                                            *
 * Return value: SUCCEED - the scripting engine is initialized                *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_is_env_initialized(zbx_es_t *es)
{
	return (NULL == es->env ? FAIL : SUCCEED);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if fatal error has occurred                                *
 *                                                                            *
 * Comments: Fatal error may put the scripting engine in unknown state, it's  *
 *           safer to destroy it instead of continuing to work with it.       *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_fatal_error(zbx_es_t *es)
{
	if (0 != es->env->fatal_error || ZBX_ES_MAX_CONSEQUENT_RT_ERROR < es->env->rt_error_num)
		return SUCCEED;

	if (ZBX_ES_STACK_LIMIT < duk_get_top(es->env->ctx))
	{
		zabbix_log(LOG_LEVEL_WARNING, "embedded scripting engine stack exceeded limits,"
				" resetting scripting environment");
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compiles script into bytecode                                     *
 *                                                                            *
 * Parameters: es     - [IN] the embedded scripting engine                    *
 *             script - [IN] the script to compile                            *
 *             code   - [OUT] the bytecode                                    *
 *             size   - [OUT] the size of compiled bytecode                   *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 * Comments: this function allocates the bytecode array, which must be        *
 *           freed by the caller after being used.                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_compile(zbx_es_t *es, const char *script, char **code, int *size, char **error)
{
	unsigned char	*buffer;
	duk_size_t	sz;
	size_t		len;
	char		* volatile func = NULL, *ptr;
	volatile int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == zbx_es_fatal_error(es))
	{
		*error = zbx_strdup(*error, "cannot continue javascript processing after fatal scripting engine error");
		goto out;
	}

	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		goto out;
	}

	/* wrap the code block into a function: function(value){<code>\n} */
	len = strlen(script);
	ptr = func = zbx_malloc(NULL, len + ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER) +
			ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_FOOTER) + 1);
	memcpy(ptr, ZBX_ES_SCRIPT_HEADER, ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER));
	ptr += ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER);
	memcpy(ptr, script, len);
	ptr += len;
	memcpy(ptr, ZBX_ES_SCRIPT_FOOTER, ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_FOOTER));
	ptr += ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_FOOTER);
	*ptr = '\0';

	duk_push_lstring(es->env->ctx, func, ptr - func);
	duk_push_lstring(es->env->ctx, "function", ZBX_CONST_STRLEN("function"));

	if (0 != duk_pcompile(es->env->ctx, DUK_COMPILE_FUNCTION))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);
		goto out;
	}

	duk_dump_function(es->env->ctx);

	if (NULL != (buffer = (unsigned char *)duk_get_buffer(es->env->ctx, -1, &sz)))
	{
		*size = sz;
		*code = zbx_malloc(NULL, sz);
		memcpy(*code, buffer, sz);
		ret = SUCCEED;
	}
	else
		*error = zbx_strdup(*error, "empty function compilation result");

	duk_pop(es->env->ctx);
out:
	zbx_free(func);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __func__, zbx_result_string(ret), ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes script                                                   *
 *                                                                            *
 * Parameters: es         - [IN] the embedded scripting engine                *
 *             script     - [IN] the script to execute                        *
 *             code       - [IN] the precompiled bytecode                     *
 *             size       - [IN] the size of precompiled bytecode             *
 *             param      - [IN] the parameter to pass to the script          *
 *             script_ret - [OUT] the result value                            *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 * Comments: Some scripting engines cannot compile into bytecode, but can     *
 *           cache some compilation data that can be reused for the next      *
 *           compilation. Because of that execute function accepts script and *
 *           bytecode parameters.                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_execute(zbx_es_t *es, const char *script, const char *code, int size, const char *param,
	char **script_ret, char **error)
{
	void		*buffer;
	volatile int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() param:%s", __func__, param);

	zbx_timespec(&es->env->start_time);
	es->env->http_req_objects = 0;
	es->env->logged_msgs = 0;

	if (NULL != es->env->json)
	{
		zbx_json_clean(es->env->json);
		zbx_json_addarray(es->env->json, "logs");
	}

	if (SUCCEED == zbx_es_fatal_error(es))
	{
		*error = zbx_strdup(*error, "cannot continue javascript processing after fatal scripting engine error");
		goto out;
	}

	ZBX_UNUSED(script);

	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		goto out;
	}

	buffer = duk_push_fixed_buffer(es->env->ctx, size);
	memcpy(buffer, code, size);
	duk_load_function(es->env->ctx);
	duk_push_string(es->env->ctx, param);

	if (DUK_EXEC_SUCCESS != duk_pcall(es->env->ctx, 1))
	{
		duk_small_int_t	rc = 0;

		es->env->rt_error_num++;

		if (0 != duk_is_object(es->env->ctx, -1))
		{
			/* try to get 'stack' property of the object on stack, assuming it's an Error object */
			if (0 != (rc = duk_get_prop_string(es->env->ctx, -1, "stack")))
				*error = zbx_strdup(*error, duk_get_string(es->env->ctx, -1));

			duk_pop(es->env->ctx);
		}

		/* If the object does not have stack property, return the object itself as error. */
		/* This allows to simply throw "error message" from scripts                       */
		if (0 == rc)
			*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));

		duk_pop(es->env->ctx);

		goto out;
	}

	if (NULL != script_ret || SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		if (0 == duk_check_type(es->env->ctx, -1, DUK_TYPE_UNDEFINED))
		{
			if (0 != duk_check_type(es->env->ctx, -1, DUK_TYPE_NULL))
			{
				ret = SUCCEED;

				if (NULL != script_ret)
					*script_ret = NULL;

				zabbix_log(LOG_LEVEL_DEBUG, "%s() output: null", __func__);
			}
			else
			{
				char	*output = NULL;

				if (SUCCEED != (ret = es_duktape_string_decode(
						duk_safe_to_string(es->env->ctx, -1), &output)))
				{
					*error = zbx_strdup(*error, "could not convert return value to utf8");
				}
				else
				{
					if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
					{
						zabbix_log(LOG_LEVEL_DEBUG, "%s() output:'%s'", __func__, output);
					}
					else
					{
						zabbix_log(LOG_LEVEL_DEBUG, "%s() output:'%.*s'", __func__, 512,
								output);
					}
				}

				if (SUCCEED == ret && NULL != script_ret)
					*script_ret = output;
				else
					zbx_free(output);
			}
		}
		else
		{
			if (NULL == script_ret)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): undefined return value", __func__);
				ret = SUCCEED;
			}
			else
				*error = zbx_strdup(*error, "undefined return value");
		}
	}
	else
		ret = SUCCEED;

	duk_pop(es->env->ctx);
	es->env->rt_error_num = 0;
out:
	if (NULL != es->env->json)
	{
		zbx_json_close(es->env->json);
		zbx_json_adduint64(es->env->json, "ms", zbx_get_duration_ms(&es->env->start_time));
	}

	/* Duktape documentation recommends calling duk_gc() twice, see https://duktape.org/api#duk_gc */
	duk_gc(es->env->ctx, 0);
	duk_gc(es->env->ctx, 0);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s allocated memory: " ZBX_FS_SIZE_T
			" max allocated or requested memory: " ZBX_FS_SIZE_T " max allowed memory: %d",
			__func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error), (zbx_fs_size_t)es->env->total_alloc,
			(zbx_fs_size_t)es->env->max_total_alloc, ZBX_ES_MEMORY_LIMIT);
	es->env->max_total_alloc = 0;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets script execution timeout                                     *
 *                                                                            *
 * Parameters: es      - [IN] the embedded scripting engine                   *
 *             timeout - [IN] the script execution timeout in seconds         *
 *                                                                            *
 ******************************************************************************/
void	zbx_es_set_timeout(zbx_es_t *es, int timeout)
{
	es->env->timeout = timeout;
}

void	zbx_es_debug_enable(zbx_es_t *es)
{
	if (NULL == es->env->json)
	{
		es->env->json = zbx_malloc(NULL, sizeof(struct zbx_json));
		zbx_json_init(es->env->json, ZBX_JSON_STAT_BUF_LEN);
	}
}

const char	*zbx_es_debug_info(const zbx_es_t *es)
{
	if (NULL == es->env->json)
		return NULL;

	return es->env->json->buffer;
}

void	zbx_es_debug_disable(zbx_es_t *es)
{
	if (NULL == es->env->json)
		return;

	zbx_json_free(es->env->json);
	zbx_free(es->env->json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes command (script in form of a text)                       *
 *                                                                            *
 * Parameters: command          - [IN] command in form of a text              *
 *             param            - [IN] script parameters                      *
 *             timeout          - [IN] timeout for the execution (seconds)    *
 *             config_source_ip - [IN]                                        *
 *             result           - [OUT] result of an execution                *
 *             error            - [OUT] error message                         *
 *             max_error_len    - [IN] maximum length of an error             *
 *             debug            - [OUT] debug data (optional)                 *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_execute_command(const char *command, const char *param, int timeout, const char *config_source_ip,
		char **result, char *error, size_t max_error_len, char **debug)
{
	int		size, ret = SUCCEED;
	char		*code = NULL, *errmsg = NULL;
	zbx_es_t	es;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_es_init(&es);
	if (FAIL == zbx_es_init_env(&es, config_source_ip, &errmsg))
	{
		zbx_snprintf(error, max_error_len, "cannot initialize scripting environment: %s", errmsg);
		zbx_free(errmsg);
		ret = FAIL;
		goto failure;
	}

	if (NULL != debug)
		zbx_es_debug_enable(&es);

	if (FAIL == zbx_es_compile(&es, command, &code, &size, &errmsg))
	{
		zbx_snprintf(error, max_error_len, "cannot compile script: %s", errmsg);
		zbx_free(errmsg);
		ret = FAIL;
		goto out;
	}

	if (0 != timeout)
		zbx_es_set_timeout(&es, timeout);

	if (FAIL == zbx_es_execute(&es, NULL, code, size, param, result, &errmsg))
	{
		zbx_snprintf(error, max_error_len, "cannot execute script: %s", errmsg);
		zbx_free(errmsg);
		ret = FAIL;
		goto out;
	}
out:
	if (NULL != debug)
		*debug = zbx_strdup(NULL, zbx_es_debug_info(&es));

	if (FAIL == zbx_es_destroy_env(&es, &errmsg))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot destroy embedded scripting engine environment: %s", errmsg);
		zbx_free(errmsg);
	}

	zbx_free(code);
	zbx_free(errmsg);
failure:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

zbx_es_env_t	*zbx_es_get_env(duk_context *ctx)
{
	zbx_es_env_t	*env;

	duk_push_global_stash(ctx);

	if (1 != duk_get_prop_string(ctx, -1, "\xff""\xff""zbx_env"))
		return NULL;

	env = (zbx_es_env_t *)duk_to_pointer(ctx, -1);
	duk_pop_2(ctx);

	return env;
}

/******************************************************************************
 *                                                                            *
 * Purpose: call base class prototype with arguments                          *
 *                                                                            *
 * Parameters: ctx  - [IN] duktape context                                    *
 *             base - [IN] base class name                                    *
 *             args - [IN] number of prototype arguments on stack             *
 *                                                                            *
 * Return value: 0                                                            *
 *                                                                            *
 ******************************************************************************/
duk_ret_t	es_super(duk_context *ctx, const char *base, int args)
{
	zbx_es_env_t	*env;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	duk_get_global_string(ctx, base);
	duk_push_this(ctx);

	for (int i = 0; i < args; i++)
		duk_dup(ctx, i);

	env->constructor_chain = 1;
	duk_call_method(ctx, args);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the method is called from constructor chain              *
 *                                                                            *
 * Parameters: ctx  - [IN] duktape context                                    *
 *                                                                            *
 * Return value: !0 - method is called from constructor chain                 *
 *                0 - otherwise                                               *
 *                                                                            *
 ******************************************************************************/
int	es_is_chained_constructor_call(duk_context *ctx)
{
	zbx_es_env_t	*env;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	int	constructor_chain = env->constructor_chain;

	env->constructor_chain = 0;

	return constructor_chain || duk_is_constructor_call(env->ctx);
}

/******************************************************************************
 *                                                                            *
 * Purpose: attach data pointer to current object                             *
 *                                                                            *
 * Comments: This function must be used only from object constructor          *
 *                                                                            *
 ******************************************************************************/
void	es_obj_attach_data(zbx_es_env_t *env, void *objptr, void *data, zbx_es_obj_type_t type)
{
	zbx_es_obj_data_t	obj_local;

	duk_push_this(env->ctx);
	obj_local.heapptr = objptr;
	duk_pop(env->ctx);

	obj_local.data = data;
	obj_local.type = type;
	zbx_hashset_insert(&env->objmap, &obj_local, sizeof(obj_local));
}

/******************************************************************************
 *                                                                            *
 * Purpose: get data pointer attached to current object                       *
 *                                                                            *
 * Parameters: env    - [IN]                                                  *
 *             objptr - [IN] js object heap pointer                           *
 *             type   - [IN] object type                                      *
 *                                                                            *
 * Comments: This function must be used only from object methods.             *
 *                                                                            *
 ******************************************************************************/
void	*es_obj_get_data(zbx_es_env_t *env, const void *objptr, zbx_es_obj_type_t type)
{
	zbx_es_obj_data_t	obj_local, *obj;

	obj_local.heapptr = objptr;

	if (NULL != (obj = zbx_hashset_search(&env->objmap, &obj_local)) && obj->type == type)
		return obj->data;

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: detach data pointer from current object                           *
 *                                                                            *
 * Parameters: env    - [IN]                                                  *
 *             objptr - [IN] object js heap pointer                           *
 *                                                                            *
 * Return value: detached data pointer                                        *
 *                                                                            *
 * Comments: The finalizing object must be on the top of the stack (-1).      *
 *           If the pointer contains allocated data it must be freed by the   *
 *           caller.                                                          *
 *           This function must be used only from object destructor.          *
 *                                                                            *
 ******************************************************************************/
void	*es_obj_detach_data(zbx_es_env_t *env, void *objptr, zbx_es_obj_type_t type)
{
	if (NULL != objptr)
	{
		zbx_es_obj_data_t	obj_local, *obj;
		void			*data;

		obj_local.heapptr = objptr;

		if (NULL == (obj = zbx_hashset_search(&env->objmap, &obj_local)) || obj->type != type)
			return NULL;

		data = obj->data;
		zbx_hashset_remove_direct(&env->objmap, obj);

		return data;
	}
	else
		return NULL;
}
