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

/******************************************************************************
 *                                                                            *
 * Purpose: create element array and push it on stack                         *
 *                                                                            *
 * Parameters: ctx    - [IN] duktape context                                  *
 *             wd     - [IN] webdriver object                                 *
 *             format - [IN] error message                                    *
 *                                                                            *
 * Return value: stack index of pushed error object                           *
 *                                                                            *
 ******************************************************************************/
int	browser_push_error(duk_context *ctx, zbx_webdriver_t *wd, const char *format, ...)
{
	va_list	args;
	int	err_index;

	va_start(args, format);
	wd->last_error_message = zbx_dvsprintf(wd->last_error_message, format, args);
	va_end(args);

	err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, wd->last_error_message);

	if (NULL != wd->error)
	{
		duk_push_object(ctx);
		duk_push_int(ctx, wd->error->http_code);
		duk_put_prop_string(ctx, -2, "httpCode");
		duk_push_string(ctx, wd->error->error);
		duk_put_prop_string(ctx, -2, "error");
		duk_push_string(ctx, wd->error->message);
		duk_put_prop_string(ctx, -2, "message");
		duk_put_prop_string(ctx, -2, "webdriver");
	}

	return err_index;
}
