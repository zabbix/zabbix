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

#include "embed_xml.h"

#include "zbxvariant.h"
#include "embed.h"
#include "zbxxml.h"

/******************************************************************************
 *                                                                            *
 * Purpose: XML constructor                                                   *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_xml_ctor(duk_context *ctx)
{
	if (!duk_is_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	duk_push_this(ctx);

	duk_set_finalizer(ctx, -1);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: XML.query method                                                  *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_xml_query(duk_context *ctx)
{
	int		err_index = -1;
	char		*err = NULL;
	zbx_variant_t	value;
	zbx_es_env_t	*env;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	ZBX_ES_CHECK_TIMEOUT(ctx, env);

	zbx_variant_set_str(&value, zbx_strdup(NULL, duk_safe_to_string(ctx, 0)));

	if (FAIL == zbx_query_xpath(&value, duk_safe_to_string(ctx, 1), &err))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, err);
		goto out;
	}
	es_push_result_string(ctx, value.data.str, strlen(value.data.str));
out:
	zbx_variant_clear(&value);
	zbx_free(err);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: XML.fromJson method                                               *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_xml_from_json(duk_context *ctx)
{
	int		err_index = -1;
	char		*str = NULL, *error = NULL;
	zbx_es_env_t	*env;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	ZBX_ES_CHECK_TIMEOUT(ctx, env);

	if (FAIL == zbx_json_to_xml((char *)duk_safe_to_string(ctx, 0), &str, &error))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, error);
		goto out;
	}
	es_push_result_string(ctx, str, strlen(str));
out:
	zbx_free(str);
	zbx_free(error);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: XML.toJson method                                                 *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_xml_to_json(duk_context *ctx)
{
	int		err_index = -1;
	char		*str = NULL, *error = NULL;
	zbx_es_env_t	*env;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	ZBX_ES_CHECK_TIMEOUT(ctx, env);

	if (FAIL == zbx_xml_to_json((char *)duk_safe_to_string(ctx, 0), &str, &error))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, error);
		goto out;
	}
	es_push_result_string(ctx, str, strlen(str));
out:
	zbx_free(str);
	zbx_free(error);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

static const duk_function_list_entry	xml_methods[] = {
	{"query", es_xml_query, 2},
	{"fromJson", es_xml_from_json, 1},
	{"toJson", es_xml_to_json, 1},
	{NULL, NULL, 0}
};

static int	es_xml_create_object(duk_context *ctx)
{
	duk_push_c_function(ctx, es_xml_ctor, 0);
	duk_push_object(ctx);

	duk_put_function_list(ctx, -1, xml_methods);

	if (1 != duk_put_prop_string(ctx, -2, "prototype"))
		return FAIL;

	duk_new(ctx, 0);

	if (1 != duk_put_global_string(ctx, "XML"))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: init XML object                                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_init_xml(zbx_es_t *es, char **error)
{
	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		return FAIL;
	}

	if (FAIL == es_xml_create_object(es->env->ctx))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);
		return FAIL;
	}

	return SUCCEED;
}
