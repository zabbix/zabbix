/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

#include "common.h"
#include "log.h"
#include "zbxembed.h"

#include "duktape.h"

#define ZBX_ES_MEMORY_LIMIT	(1024 * 1024 * 10)
#define ZBX_ES_TIMEOUT		10

struct zbx_es_env
{
	duk_context	*ctx;
	size_t		total_alloc;
	time_t		start_time;

	char		*error;
	int		rt_error_num;

	jmp_buf		loc;
};

#define ZBX_ES_SCRIPT_HEADER	"function(value){"

/******************************************************************************
 *                                                                            *
 * Function: es_handle_error                                                  *
 *                                                                            *
 * Purpose: fatal error handler                                               *
 *                                                                            *
 ******************************************************************************/
static void	es_handle_error(void *udata, const char *msg)
{
	zbx_es_env_t	*env = (zbx_es_env_t *)udata;

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
			es_handle_error(udata, "memory limit exceeded");
		else
			(void)duk_fatal(env->ctx, "memory limit exceeded");

		/* never returns as longjmp is called by error handler */
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
		(void)duk_fatal(env->ctx, "memory limit exceeded");

		/* never returns as longjmp is called by error handler */
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
 * Function: zbx_es_check_timeout                                             *
 *                                                                            *
 * Purpose: timeout checking callback                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_check_timeout(void *udata)
{
	zbx_es_env_t	*env = (zbx_es_env_t *)udata;

	if (time(NULL) - env->start_time > ZBX_ES_TIMEOUT)
		return 1;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_init                                                      *
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
 * Function: zbx_es_destroy                                                   *
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
 * Function: zbx_es_init_env                                                  *
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
	const char	*__function_name = "zbx_es_init_env";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	es->env = zbx_malloc(NULL, sizeof(zbx_es_env_t));
	memset(es->env, 0, sizeof(zbx_es_env_t));

	if (0 != setjmp(es->env->loc))
	{
		ret = FAIL;
		*error = zbx_strdup(*error, es->env->error);
		goto out;
	}

	if (NULL == (es->env->ctx = duk_create_heap(es_malloc, es_realloc, es_free, es->env, es_handle_error)))
	{
		ret = FAIL;
		*error = zbx_strdup(*error, "cannot create context");
		goto out;
	}
	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		zbx_free(es->env->error);
		zbx_free(es->env);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_destroy_env                                               *
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
	const char	*__function_name = "zbx_es_destroy_env";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 != setjmp(es->env->loc))
	{
		ret = FAIL;
		*error = zbx_strdup(*error, es->env->error);
		goto out;
	}

	duk_destroy_heap(es->env->ctx);
	zbx_free(es->env->error);
	zbx_free(es->env);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_ready                                                     *
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
 * Function: zbx_es_error_num                                                 *
 *                                                                            *
 * Purpose: returns the number of consecutive runtime errors                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_get_runtime_error_num(zbx_es_t *es)
{
	return es->env->rt_error_num;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_compile                                                   *
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
 * Comments: The this function allocates the bytecode array, which must be    *
 *           freed by the caller after being used.                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_compile(zbx_es_t *es, const char *script, char **code, int *size, char **error)
{
	const char	*__function_name = "zbx_es_compile";

	unsigned char	*buffer;
	duk_size_t	sz;
	char		*func, *ptr;
	size_t		len;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 != setjmp(es->env->loc))
	{
		ret = FAIL;
		*error = zbx_strdup(*error, es->env->error);
		goto out;
	}

	/* wrap the code block into a function: function(value){<code>} */
	len = strlen(script);
	ptr = func = zbx_malloc(NULL, len + ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER) + 2);
	memcpy(ptr, ZBX_ES_SCRIPT_HEADER, ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER));
	ptr += ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER);
	memcpy(ptr, script, len);
	ptr += len;
	*ptr++ = '}';
	*ptr = '\0';

	duk_push_string(es->env->ctx, func);
	duk_push_string(es->env->ctx, "function");
	zbx_free(func);

	if (0 != duk_pcompile(es->env->ctx, DUK_COMPILE_FUNCTION))
	{
		ret = FAIL;
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		goto out;
	}

	duk_dump_function(es->env->ctx);

	buffer = (unsigned char *)duk_get_buffer(es->env->ctx, -1, &sz);
	*size = sz;
	*code = zbx_malloc(NULL, sz);
	memcpy(*code, buffer, sz);

	duk_pop(es->env->ctx);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_execute                                                   *
 *                                                                            *
 * Purpose: executes script                                                   *
 *                                                                            *
 * Parameters: es     - [IN] the embedded scripting engine                    *
 *             script - [IN] the script to execute                            *
 *             code   - [IN] the precompiled bytecode                         *
 *             size   - [IN] the size of precompiled bytecode                 *
 *             param  - [IN] the parameter to pass to the script              *
 *             output - [OUT] the result value                                *
 *             error  - [OUT] the error message                               *
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
int	zbx_es_execute(zbx_es_t *es, const char *script, const char *code, int size, const char *param, char **output,
	char **error)
{
	const char	*__function_name = "zbx_es_execute";

	void	*buffer;
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ZBX_UNUSED(script);

	if (0 != setjmp(es->env->loc))
	{
		ret = FAIL;
		es->env->rt_error_num++;
		*error = zbx_strdup(*error, es->env->error);
		goto out;
	}

	buffer = duk_push_fixed_buffer(es->env->ctx, size);
	memcpy(buffer, code, size);
	duk_load_function(es->env->ctx);
	duk_push_string(es->env->ctx, param);

	es->env->start_time = time(NULL);

	if (DUK_EXEC_SUCCESS != duk_pcall(es->env->ctx, 1))
	{
		duk_small_int_t	rc = 0;

		ret = FAIL;
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

	if (0 == duk_check_type_mask(es->env->ctx, -1, DUK_TYPE_MASK_NULL | DUK_TYPE_MASK_UNDEFINED))
		*output = zbx_strdup(NULL, duk_safe_to_string(es->env->ctx, -1));
	else
		*output = NULL;

	duk_pop(es->env->ctx);
	es->env->rt_error_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() output:'%s'", __function_name, ZBX_NULL2EMPTY_STR(*output));

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}
