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

#include "browser_element.h"
#include "browser_error.h"

#include "embed.h"
#include "webdriver.h"

#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxhttp.h"
#include "duktape.h"
#include "zbxalgo.h"
#include "zbxcurl.h"
#include "global.h"
#include "zbxjson.h"

typedef struct
{
	char		*id;
	zbx_webdriver_t	*wd;
}
zbx_wd_element_t;

#ifdef HAVE_LIBCURL

/******************************************************************************
 *                                                                            *
 * Purpose: return backing C structure embedded in element object         *
 *                                                                            *
 ******************************************************************************/
static zbx_wd_element_t *wd_element(duk_context *ctx)
{
	zbx_wd_element_t	*el;

	duk_push_this(ctx);
	duk_get_prop_string(ctx, -1, "\xff""\xff""d");
	el = (zbx_wd_element_t *)duk_to_pointer(ctx, -1);

	return el;
}

/******************************************************************************
 *                                                                            *
 * Purpose: element destructor                                            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	wd_element_dtor(duk_context *ctx)
{
	zbx_wd_element_t	*el;

	zabbix_log(LOG_LEVEL_TRACE, "element::~element()");

	duk_get_prop_string(ctx, 0, "\xff""\xff""d");

	if (NULL != (el = (zbx_wd_element_t *)duk_to_pointer(ctx, -1)))
	{
		webdriver_release(el->wd);
		zbx_free(el->id);
		zbx_free(el);

		duk_push_pointer(ctx, NULL);
		duk_put_prop_string(ctx, 0, "\xff""\xff""d");
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: element constructor                                               *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	wd_element_ctor(duk_context *ctx, zbx_webdriver_t *wd, const char *elementid)
{
	zbx_wd_element_t	*el;
	zbx_es_env_t		*env;

	zabbix_log(LOG_LEVEL_TRACE, "element::element()");

	if (NULL == (env = zbx_es_get_env(ctx)))
	{
		(void)browser_push_error(ctx, wd, "cannot access internal environment");

		return duk_throw(ctx);
	}

	el = (zbx_wd_element_t *)zbx_malloc(NULL, sizeof(zbx_wd_element_t));
	el->wd = webdriver_addref(wd);
	el->id = zbx_strdup(NULL, elementid);

	duk_push_object(ctx);
	duk_push_string(ctx, "\xff""\xff""d");
	duk_push_pointer(ctx, el);
	duk_def_prop(ctx, -3, DUK_DEFPROP_HAVE_VALUE | DUK_DEFPROP_CLEAR_WRITABLE | DUK_DEFPROP_HAVE_ENUMERABLE |
			DUK_DEFPROP_HAVE_CONFIGURABLE);

	duk_push_c_function(ctx, wd_element_dtor, 1);
	duk_set_finalizer(ctx, -2);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: input keys into element                                           *
 *                                                                            *
 * Stack: 0 - keys to send (string)                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	wd_element_send_keys(duk_context *ctx)
{
	zbx_wd_element_t	*el;
	char			*error = NULL, *keys = NULL;
	int			err_index = -1;

	el = wd_element(ctx);

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &keys))
	{
		err_index = browser_push_error(ctx,  el->wd, "cannot convert keys parameter to utf8");

		goto out;
	}

	if (SUCCEED != webdriver_send_keys_to_element(el->wd, el->id, keys, &error))
	{
		err_index = browser_push_error(ctx, el->wd, "cannot send keys to element: %s", error);
		zbx_free(error);
	}
out:
	zbx_free(keys);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: click on element                                                  *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	wd_element_click(duk_context *ctx)
{
	zbx_wd_element_t	*el;
	char			*error = NULL;
	int			err_index = -1;

	el = wd_element(ctx);

	if (SUCCEED != webdriver_click_element(el->wd, el->id, &error))
	{
		err_index = browser_push_error(ctx, el->wd, "cannot click element: %s", error);
		zbx_free(error);
	}

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear element                                                     *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	wd_element_clear(duk_context *ctx)
{
	zbx_wd_element_t	*el;
	char			*error = NULL;
	int			err_index = -1;

	el = wd_element(ctx);

	if (SUCCEED != webdriver_clear_element(el->wd, el->id, &error))
	{
		err_index = browser_push_error(ctx, el->wd, "cannot clear element: %s", error);
		zbx_free(error);
	}

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get element attribute value                                       *
 *                                                                            *
 * Stack: 0 - attribute name (string)                                         *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	wd_element_get_attribute(duk_context *ctx)
{
	zbx_wd_element_t	*el;
	char			*error = NULL, *name = NULL, *value = NULL;
	int			err_index = -1;

	el = wd_element(ctx);

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &name))
	{
		err_index = browser_push_error(ctx, el->wd, "cannot convert name parameter to utf8");

		goto out;
	}

	if (SUCCEED != webdriver_get_element_info(el->wd, el->id, "attribute", name, &value, &error))
	{
		err_index = browser_push_error(ctx, el->wd, "cannot get element attribute: %s", error);
		zbx_free(error);

		goto out;
	}

	duk_push_string(ctx, value);
out:
	zbx_free(value);
	zbx_free(name);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get element property value                                        *
 *                                                                            *
 * Stack: 0 - property value (string)                                         *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	wd_element_get_property(duk_context *ctx)
{
	zbx_wd_element_t	*el;
	char			*error = NULL, *name = NULL, *value = NULL;
	int			err_index = -1;

	el = wd_element(ctx);

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &name))
	{
		err_index = browser_push_error(ctx, el->wd, "cannot convert name parameter to utf8");

		goto out;
	}

	if (SUCCEED != webdriver_get_element_info(el->wd, el->id, "property", name, &value, &error))
	{
		err_index = browser_push_error(ctx, el->wd, "cannot get element property: %s", error);
		zbx_free(error);

		goto out;
	}

	duk_push_string(ctx, value);
out:
	zbx_free(value);
	zbx_free(name);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get element text                                                  *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	wd_element_get_text(duk_context *ctx)
{
	zbx_wd_element_t	*el;
	char			*error = NULL, *value = NULL;
	int			err_index = -1;

	el = wd_element(ctx);

	if (SUCCEED != webdriver_get_element_info(el->wd, el->id, "text", NULL, &value, &error))
	{
		err_index = browser_push_error(ctx, el->wd, "cannot get element property: %s", error);
		zbx_free(error);

		goto out;
	}

	duk_push_string(ctx, value);
out:
	zbx_free(value);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

static const duk_function_list_entry	element_methods[] = {
	{"sendKeys", wd_element_send_keys, 1},
	{"click", wd_element_click, 0},
	{"clear", wd_element_clear, 0},
	{"getAttribute", wd_element_get_attribute, 1},
	{"getProperty", wd_element_get_property, 1},
	{"getText", wd_element_get_text, 0},
	{0}
};

#else

static duk_ret_t	wd_element_ctor(duk_context *ctx)
{
	if (!duk_is_constructor_call(ctx))
		return DUK_RET_EVAL_ERROR;

	return duk_error(ctx, DUK_RET_EVAL_ERROR, "missing cURL library");
}

static const duk_function_list_entry	element_methods[] = {
	{NULL, NULL, 0}
};
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: create element and push it on stack                               *
 *                                                                            *
 * Parameters: ctx       - [IN] duktape context                               *
 *             wd        - [IN] webdriver object                              *
 *             elementid - [IN] element identifier returned by webdriver      *
 *                              find element(s) requests                      *
 *                                                                            *
 ******************************************************************************/
void	wd_element_create(duk_context *ctx, zbx_webdriver_t *wd, const char *elementid)
{
	wd_element_ctor(ctx, wd, elementid);
	duk_put_function_list(ctx, -1, element_methods);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create element array and push it on stack                         *
 *                                                                            *
 * Parameters: ctx      - [IN] duktape context                                *
 *             wd       - [IN] webdriver object                               *
 *             elements - [IN] vector with element identifiers returned       *
 *                             by webdriver find elements request             *
 *                                                                            *
 ******************************************************************************/
void	wd_element_create_array(duk_context *ctx, zbx_webdriver_t *wd, const zbx_vector_str_t *elements)
{
	duk_idx_t	arr;

	arr = duk_push_array(ctx);

	for (int i = 0; i < elements->values_num; i++)
	{
		wd_element_create(ctx, wd, elements->values[i]);
		duk_put_prop_index(ctx, arr, (duk_uarridx_t)i);
	}
}
