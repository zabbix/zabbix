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
** but WITHOUT ANY WARRANTY; without even the envied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "log.h"
#include "zbxembed.h"

#include "httprequest.h"
#include "zabbix.h"
#include "global.h"
#include "console.h"
#include "xml.h"
#include "embed.h"

#define ZBX_ES_MEMORY_LIMIT	(1024 * 1024 * 64)
#define ZBX_ES_TIMEOUT		10

#define ZBX_ES_STACK_LIMIT	1000

/* maximum number of consequent runtime errors after which it's treated as fatal error */
#define ZBX_ES_MAX_CONSEQUENT_RT_ERROR	3

#define ZBX_ES_SCRIPT_HEADER	"function(value){"
#define ZBX_ES_SCRIPT_FOOTER	"\n}"

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

	if (SUCCEED != zbx_es_destroy_env(es, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot destroy embedded scripting engine environment: %s", error);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes embedded scripting engine environment                 *
 *                                                                            *
 * Parameters: es    - [IN] the embedded scripting engine                     *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_init_env(zbx_es_t *es, char **error)
{
	volatile int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	es->env = zbx_malloc(NULL, sizeof(zbx_es_env_t));
	memset(es->env, 0, sizeof(zbx_es_env_t));

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
	duk_push_pointer(es->env->ctx, (void *)es->env);
	if (1 != duk_put_prop_string(es->env->ctx, -2, "\xff""\xff""zbx_env"))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);
		return FAIL;
	}

	/* initialize HttpRequest and CurlHttpRequest prototypes */
	if (FAIL == zbx_es_init_httprequest(es, error))
		goto out;

	if (FAIL == zbx_es_init_xml(es, error))
		goto out;

	es->env->timeout = ZBX_ES_TIMEOUT;
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
	zbx_es_debug_disable(es);
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
int	zbx_es_execute(zbx_es_t *es, const char *script, const char *code, int size, const char *param, char **script_ret,
	char **error)
{
	void		*buffer;
	volatile int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() param:%s", __func__, param);

	zbx_timespec(&es->env->start_time);

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

				if (SUCCEED != (ret = zbx_cesu8_to_utf8(duk_safe_to_string(es->env->ctx, -1), &output)))
					*error = zbx_strdup(*error, "could not convert return value to utf8");
				else
					zabbix_log(LOG_LEVEL_DEBUG, "%s() output:'%s'", __func__, output);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __func__, zbx_result_string(ret), ZBX_NULL2EMPTY_STR(*error));

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
 * Parameters: command       - [IN] the command in form of a text             *
 *             param         - [IN] the script parameters                     *
 *             timeout       - [IN] the timeout for the execution (seconds)   *
 *             result        - [OUT] the result of an execution               *
 *             error         - [OUT] the error message                        *
 *             max_error_len - [IN] the maximum length of an error            *
 *             debug         - [OUT] the debug data (optional)                *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_execute_command(const char *command, const char *param, int timeout, char **result,
		char *error, size_t max_error_len, char **debug)
{
	int		size, ret = SUCCEED;
	char		*code = NULL, *errmsg = NULL;
	zbx_es_t	es;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_es_init(&es);
	if (FAIL == zbx_es_init_env(&es, &errmsg))
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
	duk_pop(ctx);

	return env;
}
