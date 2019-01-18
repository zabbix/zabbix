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
#include "zbxembed.h"


#include "duktape.h"

#define ZBX_ES_MEMORY_LIMIT	(1024 * 1024 * 10)
#define ZBX_ES_TIMEOUT		10

struct zbx_es_impl
{
	duk_context	*ctx;
	size_t		total_alloc;
	jmp_buf	env;
	time_t		start_time;
	char		*error;
	int		rt_error_num;
};

#define ZBX_ES_SCRIPT_HEADER	"func(value){"

/*
 * Memory allocation routines to track and limit script memory usage.
 */

static void	*es_malloc(void *udata, duk_size_t size)
{
	zbx_es_impl_t	*impl = (zbx_es_impl_t *)udata;
	uint64_t	*uptr;

	if (impl->total_alloc + size + 8 > ZBX_ES_MEMORY_LIMIT)
	{
		(void)duk_fatal(impl->ctx, "memory limit exceeded");

		/* never returns as longjmp is called by error handler */
		return NULL;
	}

	impl->total_alloc += (size + 8);
	uptr = zbx_malloc(NULL, size + 8);
	*uptr++ = size;

	return uptr;
}

static void	*es_realloc(void *udata, void *ptr, duk_size_t size)
{
	zbx_es_impl_t	*impl = (zbx_es_impl_t *)udata;
	uint64_t	*uptr = ptr;
	size_t		old_size;

	if (NULL != uptr)
	{
		--uptr;
		old_size = *uptr + 8;
	}
	else
		old_size = 0;

	if (impl->total_alloc + size + 8 - old_size > ZBX_ES_MEMORY_LIMIT)
	{
		(void)duk_fatal(impl->ctx, "memory limit exceeded");

		/* never returns as longjmp is called by error handler */
		return NULL;
	}

	impl->total_alloc += size + 8 - old_size;
	uptr = zbx_realloc(uptr, size + 8);
	*uptr++ = size;

	return uptr;
}

static void	es_free(void *udata, void *ptr)
{
	zbx_es_impl_t	*impl = (zbx_es_impl_t *)udata;
	uint64_t	*uptr = ptr;

	if (NULL != ptr)
	{
		impl->total_alloc -= (*(--uptr) + 8);
		zbx_free(uptr);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: es_handle_error                                                  *
 *                                                                            *
 * Purpose: fatal error handler                                               *
 *                                                                            *
 ******************************************************************************/
static void	es_handle_error(void *udata, const char *msg)
{
	zbx_es_impl_t	*impl = (zbx_es_impl_t *)udata;

	impl->error = zbx_strdup(impl->error, msg);
	longjmp(impl->env, 1);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_timeout                                                   *
 *                                                                            *
 * Purpose: timeout check callbacj                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_timeout(void *udata)
{
	zbx_es_impl_t	*impl = (zbx_es_impl_t *)udata;

	if (time(NULL) - impl->start_time > ZBX_ES_TIMEOUT)
		return 1;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_init                                                      *
 *                                                                            *
 * Purpose: initialize embedded scripting engine                              *
 *                                                                            *
 * Parameters: es    - [IN] the embedded scripting engine                     *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_init(zbx_es_t *es, char **error)
{
	es->impl = zbx_malloc(NULL, sizeof(zbx_es_impl_t));
	memset(es->impl, 0, sizeof(zbx_es_impl_t));

	if (0 != setjmp(es->impl->env))
	{
		*error = zbx_strdup(*error, es->impl->error);
		return FAIL;
	}

	if (NULL == (es->impl->ctx = duk_create_heap(es_malloc, es_realloc, es_free, es->impl, es_handle_error)))
	{
		*error = zbx_strdup(*error, "cannot create context");
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_destroy                                                   *
 *                                                                            *
 * Purpose: destroy initialized embedded scripting engine                     *
 *                                                                            *
 * Parameters: es    - [IN] the embedded scripting engine                     *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_destroy(zbx_es_t *es, char **error)
{
	ZBX_UNUSED(error);

	duk_destroy_heap(es->impl->ctx);
	zbx_free(es->impl->error);
	zbx_free(es->impl);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_es_initialized                                               *
 *                                                                            *
 * Purpose: checks if the scripting engine is initialized                     *
 *                                                                            *
 * Parameters: es    - [IN] the embedded scripting engine                     *
 *                                                                            *
 * Return value: SUCCEED - the scripting engine is initialized                *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_initialized(zbx_es_t *es)
{
	return (NULL == es->impl ? FAIL : SUCCEED);
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
	return es->impl->rt_error_num;
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
	unsigned char	*buffer;
	duk_size_t	sz;
	char		*func, *ptr;
	size_t		len;

	if (0 != setjmp(es->impl->env))
	{
		*error = zbx_strdup(*error, es->impl->error);
		return FAIL;
	}

	/* wrap the code block into a function: func(value){<code>} */
	len = strlen(script);
	ptr = func = zbx_malloc(NULL, len + ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER) + 2);
	memcpy(ptr, ZBX_ES_SCRIPT_HEADER, ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER));
	ptr += ZBX_CONST_STRLEN(ZBX_ES_SCRIPT_HEADER);
	memcpy(ptr, script, len);
	ptr += len;
	*ptr++ = '}';
	*ptr = '\0';

	duk_push_string(es->impl->ctx, func);
	duk_push_string(es->impl->ctx, "function");
	zbx_free(func);

	if (0 != duk_pcompile(es->impl->ctx, DUK_COMPILE_FUNCTION))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->impl->ctx, -1));
		return FAIL;
	}

	duk_dump_function(es->impl->ctx);

	buffer = (unsigned char *)duk_get_buffer(es->impl->ctx, -1, &sz);
	*size = sz;
	*code = zbx_malloc(NULL, sz);
	memcpy(*code, buffer, sz);

	duk_pop(es->impl->ctx);

	return SUCCEED;
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
	void	*buffer;

	ZBX_UNUSED(script);

	if (0 != setjmp(es->impl->env))
	{
		es->impl->rt_error_num++;
		*error = zbx_strdup(*error, es->impl->error);
		return FAIL;
	}

	buffer = duk_push_fixed_buffer(es->impl->ctx, size);
	memcpy(buffer, code, size);
	duk_load_function(es->impl->ctx);
	duk_push_string(es->impl->ctx, param);

	es->impl->start_time = time(NULL);

	if (DUK_EXEC_SUCCESS != duk_pcall(es->impl->ctx, 1))
	{
		es->impl->rt_error_num++;
		duk_get_prop_string(es->impl->ctx, -1, "stack");
		*error = zbx_strdup(*error, duk_get_string(es->impl->ctx, -1));
		duk_pop(es->impl->ctx);
		duk_pop(es->impl->ctx);
		return FAIL;
	}

	*output = zbx_strdup(NULL, duk_safe_to_string(es->impl->ctx, -1));
	duk_pop(es->impl->ctx);
	es->impl->rt_error_num = 0;

	return SUCCEED;
}
