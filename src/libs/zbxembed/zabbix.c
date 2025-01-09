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

#include "zabbix.h"
#include "embed.h"

#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxtime.h"

#include "duktape.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Zabbix destructor                                                 *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_zabbix_dtor(duk_context *ctx)
{
	ZBX_UNUSED(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Zabbix constructor                                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_zabbix_ctor(duk_context *ctx)
{
	if (!duk_is_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	duk_push_this(ctx);

	duk_push_c_function(ctx, es_zabbix_dtor, 1);
	duk_set_finalizer(ctx, -2);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Zabbix.Status method                                              *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_zabbix_log(duk_context *ctx)
{
	zbx_es_env_t		*env;
	char			*message = NULL;
	int			level, err_index = -1;
	duk_memory_functions	out_funcs;

	level = duk_to_int(ctx, 0);

	if (SUCCEED != es_duktape_string_decode(duk_safe_to_string(ctx, 1), &message))
	{
		message = zbx_strdup(message, duk_safe_to_string(ctx, 1));
		zbx_replace_invalid_utf8(message);
	}

	duk_get_memory_functions(ctx, &out_funcs);
	env = (zbx_es_env_t *)out_funcs.udata;

	if (ZBX_ES_LOG_MSG_LIMIT <= env->logged_msgs)
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR,
				"maximum count of logged messages was reached");
		goto out;
	}

	zabbix_log(level, "%s", message);

	if (NULL == env->json)
		goto out;

	if (ZBX_ES_LOG_MEMORY_LIMIT < env->json->buffer_size)	/* approximate limit */
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "log exceeds the maximum size of "
				ZBX_FS_UI64 " bytes.", ZBX_ES_LOG_MEMORY_LIMIT);
		goto out;
	}

	zbx_json_addobject(env->json, NULL);
	zbx_json_adduint64(env->json, "level", (zbx_uint64_t)level);
	zbx_json_adduint64(env->json, "ms", zbx_get_duration_ms(&env->start_time));
	zbx_json_addstring(env->json, "message", message, ZBX_JSON_TYPE_STRING);
	zbx_json_close(env->json);
out:
	env->logged_msgs++;
	zbx_free(message);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sleep for given duration in milliseconds                          *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack cannot be converted to *
 *                 unsigned integer                                           *
 *               - if the sleep duration is longer than timeout               *
 *               - if the sleep duration is longer than time left for JS      *
 *                 execution before timeout occurs                            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_zabbix_sleep(duk_context *ctx)
{
	zbx_es_env_t	*env;
	struct timespec	ts_sleep;
	zbx_uint64_t	timeout, duration;
	unsigned int	sleep_ms;
	double		sleep_dbl;
	duk_idx_t	err_idx = -1;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_ERR_ERROR, "cannot access internal environment");

	/* use duk_to_number() instead of duk_to_uint() to distinguish between zero value and error */
	sleep_dbl = duk_to_number(ctx, 0);

	if (FP_NAN == fpclassify((float)sleep_dbl) || 0.0 > sleep_dbl)
		return duk_error(ctx, DUK_ERR_EVAL_ERROR, "invalid Zabbix.sleep() duration");

	if (DUK_UINT_MAX < sleep_dbl)
		sleep_ms = DUK_UINT_MAX;
	else
		sleep_ms = (unsigned int)sleep_dbl;

	timeout = env->timeout <= 0 ? 0 : (zbx_uint64_t)env->timeout * 1000;

	if (sleep_ms > timeout)
	{
		return duk_error(ctx, DUK_ERR_EVAL_ERROR,
				"Zabbix.sleep(%u) duration is longer than JS execution timeout(" ZBX_FS_UI64 ")",
				sleep_ms, timeout);
	}

	duration = zbx_get_duration_ms(&env->start_time);

	if (timeout < duration)
		return duk_error(ctx, DUK_ERR_RANGE_ERROR, "execution timeout");

	timeout -= duration;

	if (sleep_ms > timeout)
	{
		err_idx = duk_push_error_object(ctx, DUK_ERR_RANGE_ERROR, "execution timeout");
		sleep_ms = (unsigned int)timeout;
	}

	ts_sleep.tv_sec = sleep_ms / 1000;
	ts_sleep.tv_nsec = sleep_ms % 1000 * 1000000;
	nanosleep(&ts_sleep, NULL);

	if (-1 != err_idx)
		return duk_throw(ctx);

	return 0;
}

static const duk_function_list_entry	zabbix_methods[] = {
	{"Log",		es_zabbix_log,		2},
	{"log",		es_zabbix_log, 		2},
	{"sleep",	es_zabbix_sleep,	1},
	{NULL, NULL, 0}
};

static int	es_zabbix_create_object(duk_context *ctx)
{
	duk_push_c_function(ctx, es_zabbix_ctor, 0);
	duk_push_object(ctx);

	duk_put_function_list(ctx, -1, zabbix_methods);

	if (1 != duk_put_prop_string(ctx, -2, "prototype"))
		return FAIL;

	duk_new(ctx, 0);
	duk_put_global_string(ctx, "Zabbix");

	return SUCCEED;
}

int	zbx_es_init_zabbix(zbx_es_t *es, char **error)
{
	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		return FAIL;
	}

	if (FAIL == es_zabbix_create_object(es->env->ctx))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);
		return FAIL;
	}

	return SUCCEED;
}
