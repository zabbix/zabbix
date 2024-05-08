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
** but WITHOUT ANY WARRANTY; without even the envied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "browser_error.h"
#include "embed.h"

/******************************************************************************
 *                                                                            *
 * Purpose: browser error constructor                                         *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_error_ctor(duk_context *ctx)
{
	if (!es_is_chained_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	zabbix_log(LOG_LEVEL_WARNING, "BrowserError::BrowserError()");

	duk_push_this(ctx);
	duk_dup(ctx, 0);
	duk_put_prop_string(ctx, -2, "message");

	duk_dup(ctx, 1);
	duk_put_prop_string(ctx, -2, "browser");

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: webdriver error constructor                                       *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_webdriver_error_ctor(duk_context *ctx)
{
	if (!duk_is_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	return es_super(ctx, "BrowserError", 2);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize browser and webdriver error objects                    *
 *                                                                            *
 ******************************************************************************/
int	es_browser_init_errors(zbx_es_t *es, char **error)
{
	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		return FAIL;
	}

	duk_push_c_function(es->env->ctx, es_browser_error_ctor, 2);

	duk_get_global_string(es->env->ctx, "Error");
	duk_new(es->env->ctx, 0);

	if (1 != duk_put_prop_string(es->env->ctx, -2, "prototype"))
		return FAIL;

	if (1 != duk_put_global_string(es->env->ctx, "BrowserError"))
		return FAIL;

	duk_push_c_function(es->env->ctx, es_webdriver_error_ctor, 2);
	duk_get_global_string(es->env->ctx, "BrowserError");
	duk_new(es->env->ctx, 0);

	if (1 != duk_put_prop_string(es->env->ctx, -2, "prototype"))
		return FAIL;

	if (1 != duk_put_global_string(es->env->ctx, "WebdriverError"))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create element array and push it on stack                         *
 *                                                                            *
 * Parameters: ctx    - [IN] duktape context                                  *
 *             wd     - [IN] webdriver object                                 *
 *             format - [IN] error message                                    *
 *                                                                            *
 * Return value: 0                                                            *
 *                                                                            *
 ******************************************************************************/
int	browser_push_error(duk_context *ctx, zbx_webdriver_t *wd, const char *format, ...)
{
	va_list	args;
	char	*message;

	va_start(args, format);
	message = zbx_dvsprintf(NULL, format, args);
	va_end(args);

	if (NULL == wd || NULL == wd->error)
		duk_get_global_string(ctx, "BrowserError");
	else
		duk_get_global_string(ctx, "WebdriverError");

	duk_push_string(ctx, message);

	if (NULL != wd)
	{
		duk_push_heapptr(ctx, wd->browser);
		zbx_free(wd->last_error_message);
		wd->last_error_message = message;
	}
	else
	{
		duk_push_null(ctx);
		zbx_free(message);
	}

	duk_new(ctx, 2);

	return 0;
}
