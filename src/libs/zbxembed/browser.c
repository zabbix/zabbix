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

#include "embed.h"
#include "webdriver.h"
#include "browser_element.h"
#include "browser_error.h"
#include "zbxembed.h"

#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxhttp.h"
#include "duktape.h"
#include "zbxalgo.h"
#include "zbxcurl.h"
#include "global.h"
#include "zbxjson.h"

#ifdef HAVE_LIBCURL

/******************************************************************************
 *                                                                            *
 * Purpose: return backing C structure embedded in browser object         *
 *                                                                            *
 ******************************************************************************/
static zbx_webdriver_t *es_webdriver(duk_context *ctx)
{
	zbx_webdriver_t	*wd;

	duk_push_this(ctx);
	duk_get_prop_string(ctx, -1, "\xff""\xff""d");
	wd = (zbx_webdriver_t *)duk_to_pointer(ctx, -1);

	return wd;
}

/******************************************************************************
 *                                                                            *
 * Purpose: browser destructor                                            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_dtor(duk_context *ctx)
{
	zbx_webdriver_t	*wd;

	zabbix_log(LOG_LEVEL_DEBUG, "browser::~browser()");

	duk_get_prop_string(ctx, 0, "\xff""\xff""d");

	if (NULL != (wd = (zbx_webdriver_t *)duk_to_pointer(ctx, -1)))
	{
		webdriver_release(wd);
		duk_push_pointer(ctx, NULL);
		duk_put_prop_string(ctx, 0, "\xff""\xff""d");
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: browser constructor                                               *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_ctor(duk_context *ctx)
{
	zbx_webdriver_t	*wd = NULL;
	zbx_es_env_t	*env;
	int		err_index = -1;
	char		*error = NULL, *capabilities = NULL;

	if (!duk_is_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	duk_get_global_string(ctx, "JSON");
	duk_push_string(ctx, "stringify");
	duk_dup(ctx, 1);
	duk_pcall_prop(ctx, -3, 1);

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, -1), &capabilities))
	{
		err_index = browser_push_error(ctx, NULL, "cannot convert browser capabilities to utf8");
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "browser::browser(%s)", capabilities);

	if (NULL == (env = zbx_es_get_env(ctx)))
	{
		err_index = browser_push_error(ctx, NULL, "cannot access internal environment");
		goto out;
	}

	if (NULL == (wd = webdriver_create(env->browser_endpoint, env->config_source_ip, &error)))
	{
		err_index = browser_push_error(ctx, NULL, "cannot create webdriver: %s", error);
		goto out;
	}

	duk_push_this(ctx);
	wd->browser = duk_get_heapptr(ctx, -1);

	duk_push_string(ctx, "\xff""\xff""d");
	duk_push_pointer(ctx, wd);
	duk_def_prop(ctx, -3, DUK_DEFPROP_HAVE_VALUE | DUK_DEFPROP_CLEAR_WRITABLE | DUK_DEFPROP_HAVE_ENUMERABLE |
			DUK_DEFPROP_HAVE_CONFIGURABLE);

	duk_push_c_function(ctx, es_browser_dtor, 1);
	duk_set_finalizer(ctx, -2);

	if (SUCCEED != webdriver_open_session(wd, capabilities, &error))
		err_index = browser_push_error(ctx, wd, "cannot open webriver session: %s", error);
out:
	zbx_free(capabilities);
	zbx_free(error);

	if (-1 != err_index)
	{
		if (NULL != wd)
			webdriver_destroy(wd);

		return duk_throw(ctx);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: open URL                                                          *
 *                                                                            *
 * Stack: 0 - url to open (string)                                            *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_navigate(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL, *url = NULL;
	int		ret;

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &url))
		return duk_error(ctx, DUK_RET_TYPE_ERROR,  "cannot convert URL parameter to utf8");

	wd = es_webdriver(ctx);

	ret = webdriver_url(wd, url, &error);
	zbx_free(url);

	if (SUCCEED != ret)
	{
		(void)browser_push_error(ctx, wd, "cannot open url: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get currently opened URL                                          *
 *                                                                            *
 * Return value: URL (string)                                                 *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_url(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL, *url = NULL;

	wd = es_webdriver(ctx);

	if (SUCCEED != webdriver_get_url(wd, &url, &error))
	{
		(void)browser_push_error(ctx, wd, "cannot get url: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	duk_push_string(ctx, url);
	zbx_free(url);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find element                                                      *
 *                                                                            *
 * Stack: 0 - strategy (string)                                               *
 *        1 - selector (string)                                               *
 *                                                                            *
 * Return value: Element object if found or null                              *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_find_element(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL, *strategy = NULL, *selector = NULL, *element = NULL;
	int		err_index = -1;

	wd = es_webdriver(ctx);

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &strategy))
	{
		err_index = browser_push_error(ctx, wd, "cannot convert strategy parameter to utf8");
		goto out;
	}

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 1), &selector))
	{
		err_index = browser_push_error(ctx, wd, "cannot convert selector parameter to utf8");
		goto out;
	}

	if (SUCCEED != webdriver_find_element(wd, strategy, selector, &element, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot find element: %s", error);
		zbx_free(error);
		goto out;
	}

	if (NULL != element)
	{
		wd_element_create(ctx, wd, element);
		zbx_free(element);
	}
	else
		duk_push_null(ctx);
out:
	zbx_free(strategy);
	zbx_free(selector);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find elements                                                     *
 *                                                                            *
 * Stack: 0 - strategy (string)                                               *
 *        1 - selector (string)                                               *
 *                                                                            *
 * Return value: array of Element objects. The array can be empty if no       *
 *               elements matching specified strategy and selector were found *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_find_elements(duk_context *ctx)
{
	zbx_webdriver_t		*wd;
	char			*error = NULL, *strategy = NULL, *selector = NULL;
	int			err_index = -1;
	zbx_vector_str_t	elements;

	zbx_vector_str_create(&elements);

	wd = es_webdriver(ctx);

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &strategy))
	{
		err_index = browser_push_error(ctx, wd, "cannot convert strategy parameter to utf8");
		goto out;
	}

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 1), &selector))
	{
		err_index = browser_push_error(ctx, wd, "cannot convert selector parameter to utf8");
		goto out;
	}

	if (SUCCEED != webdriver_find_elements(wd, strategy, selector, &elements, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot find element: %s", error);
		zbx_free(error);

		goto out;
	}

	wd_element_create_array(ctx, wd, &elements);
out:
	zbx_vector_str_clear_ext(&elements, zbx_str_free);
	zbx_vector_str_destroy(&elements);

	zbx_free(strategy);
	zbx_free(selector);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get variant value                                                 *
 *                                                                            *
 * Return value: value matching the variant type                              *
 *                                                                            *
 ******************************************************************************/
static void	es_browser_get_variant(duk_context *ctx, const zbx_variant_t *var)
{
	const char	*str;
	duk_idx_t	idx;
	zbx_uint32_t	len;

	switch (var->type)
	{
		case ZBX_VARIANT_NONE:
			duk_push_null(ctx);
			break;
		case ZBX_VARIANT_STR:
			duk_push_string(ctx, var->data.str);
			break;
		case ZBX_VARIANT_UI64:
			duk_push_number(ctx, (double)var->data.ui64);
			break;
		case ZBX_VARIANT_DBL:
			duk_push_number(ctx, var->data.dbl);
			break;
		case ZBX_VARIANT_BIN:
			len = zbx_variant_data_bin_get(var->data.bin, (const void **)&str);
			duk_get_global_string(ctx, "JSON");
			duk_push_string(ctx, "parse");
			duk_push_lstring(ctx, str, len);
			duk_pcall_prop(ctx, -3, 1);
			duk_remove(ctx, -2);	/* remove global JSON object from stack */
			break;
		case ZBX_VARIANT_VECTOR:
			idx = duk_push_array(ctx);
			for (int i = 0; i < var->data.vector->values_num; i++)
			{
				es_browser_get_variant(ctx, &var->data.vector->values[i]);
				duk_put_prop_index(ctx, idx, i);
			}
			break;
		case ZBX_VARIANT_ERR:
			duk_push_string(ctx, var->data.err);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get performance entry object                                      *
 *                                                                            *
 * Return value: performance entry object object                              *
 *                                                                            *
 ******************************************************************************/
static void	es_browser_get_performance_entry(duk_context *ctx, zbx_wd_perf_entry_t *entry)
{
	zbx_hashset_iter_t		iter;
	zbx_wd_attr_t			*attr;
	duk_idx_t			idx;

	idx = duk_push_object(ctx);

	zbx_hashset_iter_reset(&entry->attrs, &iter);
	while (NULL != (attr = (zbx_wd_attr_t *)zbx_hashset_iter_next(&iter)))
	{
		es_browser_get_variant(ctx, &attr->value);
		duk_put_prop_string(ctx, idx, attr->name);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get browser result                                                *
 *                                                                            *
 * Return value: result object                                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_result(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	duk_idx_t	idx_result, idx_perf, idx_details, idx_bookmark, idx_summary;

	wd = es_webdriver(ctx);

	idx_result = duk_push_object(ctx);

	duk_push_number(ctx, zbx_time() - wd->create_time);
	duk_put_prop_string(ctx, -2, "duration");

	if (NULL != wd->last_error_message)
	{
		const char	*error_code;
		int		http_status;

		duk_push_object(ctx);

		if (NULL != wd->error)
		{
			error_code = wd->error->error;
			http_status = wd->error->http_code;
		}
		else
		{
			error_code = "";
			http_status = 0;
		}

		duk_push_number(ctx, http_status);
		duk_put_prop_string(ctx, -2, "http_status");
		duk_push_string(ctx, error_code);
		duk_put_prop_string(ctx, -2, "code");
		duk_push_string(ctx, wd->last_error_message);
		duk_put_prop_string(ctx, -2, "message");

		duk_put_prop_string(ctx, idx_result, "error");
	}

	idx_perf = duk_push_object(ctx);

	idx_details = duk_push_array(ctx);
	for (int i = 0; i < wd->perf.details.values_num; i++)
	{
		duk_idx_t		idx;
		zbx_wd_perf_details_t	*details = &wd->perf.details.values[i];

		idx = duk_push_object(ctx);
		es_browser_get_performance_entry(ctx, details->navigation);
		duk_put_prop_string(ctx, idx, "navigation");
		es_browser_get_performance_entry(ctx, details->resource);
		duk_put_prop_string(ctx, idx, "resource");

		if (0 != details->user.values_num)
		{
			duk_idx_t	idx_user;

			idx_user = duk_push_array(ctx);
			for (int j = 0; j < details->user.values_num; j++)
			{
				es_browser_get_performance_entry(ctx, details->user.values[j]);
				duk_put_prop_index(ctx, idx_user, j);
			}

			duk_put_prop_string(ctx, idx, "user");
		}

		duk_put_prop_index(ctx, idx_details, i);
	}

	duk_put_prop_string(ctx, idx_perf, "details");

	idx_bookmark = duk_push_object(ctx);
	for (int i = 0; i < wd->perf.bookmarks.values_num; i++)
	{
		duk_idx_t	idx;
		zbx_wd_perf_bookmark_t	*bookmark = &wd->perf.bookmarks.values[i];

		idx = duk_push_object(ctx);
		es_browser_get_performance_entry(ctx, bookmark->details->navigation);
		duk_put_prop_string(ctx, idx, "navigation");
		es_browser_get_performance_entry(ctx, bookmark->details->resource);
		duk_put_prop_string(ctx, idx, "resource");
		duk_put_prop_string(ctx, idx_bookmark, bookmark->name);
	}
	duk_put_prop_string(ctx, idx_perf, "details_marked");

	idx_summary = duk_push_object(ctx);
	es_browser_get_performance_entry(ctx, wd->perf.navigation_summary);
	duk_put_prop_string(ctx, idx_summary, "navigation");
	es_browser_get_performance_entry(ctx, wd->perf.resource_summary);
	duk_put_prop_string(ctx, idx_summary, "resource");
	duk_put_prop_string(ctx, idx_perf, "summary");

	duk_put_prop_string(ctx, idx_result, "performance_data");

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set performance data bookmark                                     *
 *                                                                            *
 * Comments: Performance data collected during next navigation will be marked *
 *           with the specified bookmark and will be added to result data in  *
 *           separate section.                                                *
 *           After navigation the bookmark is reset.                          *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_set_bookmark(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*bookmark = NULL;

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &bookmark))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot convert bookmark parameter to utf8");

	wd = es_webdriver(ctx);
	zbx_free(wd->bookmark);
	wd->bookmark = bookmark;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set script timeout                                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_set_script_timeout(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL;
	int		timeout;

	wd = es_webdriver(ctx);

	timeout = duk_get_int(ctx, 0);

	if (SUCCEED != webdriver_set_timeouts(wd, timeout, -1, -1, &error))
	{
		(void)browser_push_error(ctx, wd, "cannot set script timeout: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set script timeout                                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_set_session_timeout(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL;
	int		timeout;

	wd = es_webdriver(ctx);

	timeout = duk_get_int(ctx, 0);

	if (SUCCEED != webdriver_set_timeouts(wd, -1, timeout, -1, &error))
	{
		(void)browser_push_error(ctx, wd, "cannot set page load timeout timeout: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set script timeout                                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_set_element_wait_timeout(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL;
	int		timeout;

	wd = es_webdriver(ctx);

	timeout = duk_get_int(ctx, 0);

	if (SUCCEED != webdriver_set_timeouts(wd, -1, -1, timeout, &error))
	{
		(void)browser_push_error(ctx, wd, "cannot set implicit timeout: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get cookies                                                       *
 *                                                                            *
 * Return value: array of Cookie objects                                      *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_cookies(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL;
	int		err_index = -1;
	char		*cookies = NULL;

	wd = es_webdriver(ctx);

	if (SUCCEED != webdriver_get_cookies(wd, &cookies, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot get cookies: %s", error);
		zbx_free(error);
		goto out;
	}
	duk_get_global_string(ctx, "JSON");
	duk_push_string(ctx, "parse");
	duk_push_string(ctx, cookies);
	duk_pcall_prop(ctx, -3, 1);
out:
	zbx_free(cookies);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add cookie                                                        *
 *                                                                            *
 * Stack 0 - Cookie object                                                    *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_add_cookie(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL,  *cookie_json = NULL;
	int		err_index = -1;

	duk_get_global_string(ctx, "JSON");
	duk_push_string(ctx, "stringify");
	duk_dup(ctx, 0);
	duk_pcall_prop(ctx, -3, 1);

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, -1), &cookie_json))
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "cannot convert cookie object to JSON format");

	wd = es_webdriver(ctx);

	if (SUCCEED != webdriver_add_cookie(wd, cookie_json, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot open url: %s", error);
		zbx_free(error);
	}

	zbx_free(cookie_json);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: configure automatic screenshot taking functionality               *
 *                                                                            *
 * Return value: base64 encoded screenshot (string)                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_screenshot(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*screenshot = NULL, *error = NULL;

	wd = es_webdriver(ctx);
	if (SUCCEED != webdriver_get_screenshot(wd, &screenshot, &error))
	{
		(void) browser_push_error(ctx, wd, "cannot capture screenshot: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	duk_push_string(ctx, screenshot);
	zbx_free(screenshot);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set browser screen size                                           *
 *                                                                            *
 * Stack 0 - width                                                            *
 *       1 - height                                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_set_screen_size(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	int		width, height;
	char		*error = NULL;

	width = duk_get_int(ctx, 0);
	height = duk_get_int(ctx, 1);

	if (width > 8192 || height > 8192)
		return duk_error(ctx, DUK_RET_EVAL_ERROR, "unsupported screen size");

	wd = es_webdriver(ctx);

	if (SUCCEED != webdriver_set_screen_size(wd, width, height, &error))
	{
		browser_push_error(ctx, wd, "cannot set screen size: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set browser screen size                                           *
 *                                                                            *
 * Stack 0 - width                                                            *
 *       1 - height                                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_discard_error(duk_context *ctx)
{
	zbx_webdriver_t	*wd;

	wd = es_webdriver(ctx);
	webdriver_discard_error(wd);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set custom error                                                  *
 *                                                                            *
 * Stack 0 - script                                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_set_error(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*message = NULL;

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &message))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot convert message parameter to utf8");

	wd = es_webdriver(ctx);
	zbx_free(wd->last_error_message);
	wd->last_error_message = message;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute script on browser                                         *
 *                                                                            *
 * Stack 0 - script                                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_execute_script(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*script = NULL, *error = NULL;

	if (SUCCEED != es_duktape_string_decode(duk_to_string(ctx, 0), &script))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot convert bookmark parameter to utf8");

	wd = es_webdriver(ctx);

	if (SUCCEED != webdriver_execute_script(wd, script, &error))
	{
		browser_push_error(ctx, wd, "cannot execute script: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get opened page source                                            *
 *                                                                            *
 * Return value: page source (string)                                         *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_page_source(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*source = NULL, *error = NULL;

	wd = es_webdriver(ctx);
	if (SUCCEED != webdriver_get_page_source(wd, &source, &error))
	{
		(void) browser_push_error(ctx, wd, "cannot get page source: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	duk_push_string(ctx, source);
	zbx_free(source);

	return 1;
}


static const duk_function_list_entry	browser_methods[] = {
	{"navigate", es_browser_navigate, 1},
	{"getUrl", es_browser_get_url, 0},
	{"findElement", es_browser_find_element, 2},
	{"findElements", es_browser_find_elements, 2},
	{"getResult", es_browser_get_result, 0},
	{"setMark", es_browser_set_bookmark, 1},
	{"setScriptTimeout", es_browser_set_script_timeout, 1},
	{"setSessionTimeout", es_browser_set_session_timeout, 1},
	{"setElementWaitTimeout", es_browser_set_element_wait_timeout, 1},
	{"getCookies", es_browser_get_cookies, 0},
	{"addCookie", es_browser_add_cookie, 1},
	{"getScreenshot", es_browser_get_screenshot, 0},
	{"setScreenSize", es_browser_set_screen_size, 2},
	{"setError", es_browser_set_error, 1},
	{"discardError", es_browser_discard_error, 0},
	{"executeScript", es_browser_execute_script, 1},
	{"getPageSource", es_browser_get_page_source, 0},
	{0}
};

#else

static duk_ret_t	es_browser_ctor(duk_context *ctx)
{
	if (!duk_is_constructor_call(ctx))
		return DUK_RET_EVAL_ERROR;

	return duk_error(ctx, DUK_RET_EVAL_ERROR, "missing cURL library");
}

static const duk_function_list_entry	browser_methods[] = {
	{NULL, NULL, 0}
};
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: get default chrome browser options                                *
 *                                                                            *
 * Return value: chrome browser options (object)                              *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_webdriver_chrome_options(duk_context *ctx)
{
	duk_push_object(ctx);
	duk_push_object(ctx);
	duk_push_object(ctx);
	duk_push_string(ctx, "chrome");
	duk_put_prop_string(ctx, -2, "browserName");
	duk_push_string(ctx, "normal");
	duk_put_prop_string(ctx, -2, "pageLoadStrategy");
	duk_put_prop_string(ctx, -2, "alwaysMatch");
	duk_put_prop_string(ctx, -2, "capabilities");

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get default firefox browser options                               *
 *                                                                            *
 * Return value: firefox browser options (object)                             *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_webdriver_firefox_options(duk_context *ctx)
{
	duk_push_object(ctx);
	duk_push_object(ctx);
	duk_push_object(ctx);
	duk_push_string(ctx, "firefox");
	duk_put_prop_string(ctx, -2, "browserName");
	duk_push_string(ctx, "normal");
	duk_put_prop_string(ctx, -2, "pageLoadStrategy");
	duk_put_prop_string(ctx, -2, "alwaysMatch");
	duk_put_prop_string(ctx, -2, "capabilities");

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get default safari browser options                                *
 *                                                                            *
 * Return value: safari browser options (object)                              *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_webdriver_safari_options(duk_context *ctx)
{
	duk_push_object(ctx);
	duk_push_object(ctx);
	duk_push_object(ctx);
	duk_push_string(ctx, "safari");
	duk_put_prop_string(ctx, -2, "browserName");
	duk_push_string(ctx, "normal");
	duk_put_prop_string(ctx, -2, "pageLoadStrategy");
	duk_put_prop_string(ctx, -2, "alwaysMatch");
	duk_put_prop_string(ctx, -2, "capabilities");

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get default edge browser options                                  *
 *                                                                            *
 * Return value: edge browser options (object)                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_webdriver_edge_options(duk_context *ctx)
{
	duk_push_object(ctx);
	duk_push_object(ctx);
	duk_push_object(ctx);
	duk_push_string(ctx, "edge");
	duk_put_prop_string(ctx, -2, "browserName");
	duk_push_string(ctx, "normal");
	duk_put_prop_string(ctx, -2, "pageLoadStrategy");
	duk_put_prop_string(ctx, -2, "alwaysMatch");
	duk_put_prop_string(ctx, -2, "capabilities");

	return 1;
}

static const duk_function_list_entry	webdriver_methods[] = {
	{"chromeOptions", es_webdriver_chrome_options, 0},
	{"firefoxOptions", es_webdriver_firefox_options, 0},
	{"safariOptions", es_webdriver_safari_options, 0},
	{"edgeOptions", es_webdriver_edge_options, 0},
	{0}
};

static int	es_browser_create_prototype(duk_context *ctx, const char *obj_name,
		const duk_function_list_entry *methods)
{
	duk_push_c_function(ctx, es_browser_ctor, 2);
	duk_push_object(ctx);

	duk_put_function_list(ctx, -1, methods);

	if (1 != duk_put_prop_string(ctx, -2, "prototype"))
		return FAIL;

	if (1 != duk_put_global_string(ctx, obj_name))
		return FAIL;

	return SUCCEED;
}

static int	es_browser_create_webdriver(duk_context *ctx)
{
	duk_push_object(ctx);
	duk_put_function_list(ctx, -1, webdriver_methods);

	if (1 != duk_put_global_string(ctx, "webdriver"))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize Browser class                                          *
 *                                                                            *
 ******************************************************************************/
static int	es_init_browser(zbx_es_t *es, char **error)
{
	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		return FAIL;
	}

	if (FAIL == es_browser_create_prototype(es->env->ctx, "Browser", browser_methods))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);

		return FAIL;
	}

	if (FAIL == es_browser_create_webdriver(es->env->ctx))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);

		return FAIL;
	}

	return es_browser_init_errors(es, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize javascript environment for browser monitoring          *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_env_init_browser(zbx_es_t *es, const char *endpoint, char **error)
{
	es->env->browser_endpoint = zbx_strdup(NULL, endpoint);

	/* initialize Browser prototype */
	return es_init_browser(es, error);
}

