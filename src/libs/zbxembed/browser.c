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

#include "embed.h"
#include "duktape.h"

#include "zbxembed.h"

#ifdef HAVE_LIBCURL

#include "browser_alert.h"
#include "browser_element.h"
#include "browser_error.h"
#include "browser_perf.h"
#include "webdriver.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxjson.h"
#include "zbxtime.h"
#include "zbxtypes.h"
#include "zbxvariant.h"
#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Purpose: return backing C structure embedded in browser object         *
 *                                                                            *
 ******************************************************************************/
static zbx_webdriver_t *es_webdriver(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	zbx_es_env_t	*env;
	void		*objptr;

	if (NULL == (env = zbx_es_get_env(ctx)))
	{
		(void)duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "cannot access internal environment");

		return NULL;
	}

	duk_push_this(ctx);
	objptr = duk_require_heapptr(ctx, -1);
	duk_pop(ctx);

	if (NULL == (wd = (zbx_webdriver_t *)es_obj_get_data(env, objptr, ES_OBJ_BROWSER)))
		(void)duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "cannot find native data attached to object");

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
	zbx_es_env_t	*env;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");

	zabbix_log(LOG_LEVEL_TRACE, "Browser::~Browser()");

	if (NULL != (wd = (zbx_webdriver_t *)es_obj_detach_data(env, duk_require_heapptr(ctx, -1), ES_OBJ_BROWSER)))
	{
		webdriver_release(wd);
		env->browser_objects--;
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
#define BROWSER_INSTANCE_LIMIT	4

	zbx_webdriver_t	*wd = NULL;
	zbx_es_env_t	*env;
	int		err_index = -1;
	char		*error = NULL, *capabilities = NULL;
	void		*objptr = NULL;

	if (!duk_is_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	if (NULL == (env = zbx_es_get_env(ctx)))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");
		goto out;
	}

	duk_push_heapptr(ctx, env->json_stringify);
	duk_dup(ctx, 0);
	duk_pcall(ctx, 1);

	if (SUCCEED != es_duktape_string_decode(duk_safe_to_string(ctx, -1), &capabilities))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR,
				"cannot convert browser capabilities to utf8");
		goto out;
	}

	zabbix_log(LOG_LEVEL_TRACE, "Browser::Browser(%s)", capabilities);

	if (NULL == (env = zbx_es_get_env(ctx)))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot access internal environment");
		goto out;
	}

	if (BROWSER_INSTANCE_LIMIT <= env->browser_objects)
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR,
				"maximum count of Browser objects was reached");
		goto out;
	}


	if (NULL == (wd = webdriver_create(env->browser_endpoint, env->config_source_ip, &error)))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot create webdriver: %s", error);
		goto out;
	}

	wd->env = env;

	duk_push_this(ctx);
	objptr = duk_require_heapptr(ctx, -1);
	es_obj_attach_data(env, objptr, wd, ES_OBJ_BROWSER);
	wd->browser = duk_get_heapptr(ctx, -1);

	if (SUCCEED != webdriver_open_session(wd, capabilities, &error))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "cannot open webriver session: %s", error);
		goto out;
	}

	duk_push_c_function(ctx, es_browser_dtor, 1);
	duk_set_finalizer(ctx, -2);
out:
	zbx_free(capabilities);
	zbx_free(error);

	if (-1 != err_index)
	{
		if (NULL != wd)
		{
			(void)es_obj_detach_data(env, objptr, ES_OBJ_BROWSER);
			webdriver_release(wd);
		}

		return duk_throw(ctx);
	}
	else
		env->browser_objects++;

	return 0;

#undef	BROWSER_INSTANCE_LIMIT
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
	const char	*url_cesu;

	url_cesu = duk_safe_to_string(ctx, 0);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != es_duktape_string_decode(url_cesu, &url))
	{
		(void)browser_push_error(ctx, wd, "cannot get url: %s", error);

		return duk_throw(ctx);
	}

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

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != webdriver_get_url(wd, &url, &error))
	{
		(void)browser_push_error(ctx, wd, "cannot get url: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	es_push_result_string(ctx, url, strlen(url));
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
	const char	*strategy_cesu, *selector_cesu;

	strategy_cesu = duk_safe_to_string(ctx, 0);
	selector_cesu = duk_safe_to_string(ctx, 1);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != es_duktape_string_decode(strategy_cesu, &strategy))
	{
		err_index = browser_push_error(ctx, wd, "cannot convert strategy parameter to utf8");
		goto out;
	}

	if (SUCCEED != es_duktape_string_decode(selector_cesu, &selector))
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
	const char		*strategy_cesu, *selector_cesu;

	strategy_cesu = duk_safe_to_string(ctx, 0);
	selector_cesu = duk_safe_to_string(ctx, 1);

	zbx_vector_str_create(&elements);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != es_duktape_string_decode(strategy_cesu, &strategy))
	{
		err_index = browser_push_error(ctx, wd, "cannot convert strategy parameter to utf8");
		goto out;
	}

	if (SUCCEED != es_duktape_string_decode(selector_cesu, &selector))
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
static void	es_browser_get_variant(zbx_es_env_t *env, duk_context *ctx, const zbx_variant_t *var)
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
			duk_push_heapptr(ctx, env->json_parse);
			duk_push_lstring(ctx, str, len);
			duk_pcall(ctx, 1);
			break;
		case ZBX_VARIANT_VECTOR:
			idx = duk_push_array(ctx);
			for (int i = 0; i < var->data.vector->values_num; i++)
			{
				es_browser_get_variant(env, ctx, &var->data.vector->values[i]);
				duk_put_prop_index(ctx, idx, (duk_uarridx_t)i);
			}
			break;
		case ZBX_VARIANT_ERR:
			duk_push_string(ctx, var->data.err);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: push performance entry object on stack                            *
 *                                                                            *
 ******************************************************************************/
static void	es_browser_push_performance_entry(zbx_es_env_t *env, duk_context *ctx, zbx_wd_perf_entry_t *entry)
{
	zbx_hashset_iter_t		iter;
	zbx_wd_attr_t			*attr;
	duk_idx_t			idx;

	idx = duk_push_object(ctx);

	zbx_hashset_iter_reset(&entry->attrs, &iter);
	while (NULL != (attr = (zbx_wd_attr_t *)zbx_hashset_iter_next(&iter)))
	{
		es_browser_get_variant(env, ctx, &attr->value);
		duk_put_prop_string(ctx, idx, attr->name);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: push error object on stack                                        *
 *                                                                            *
 ******************************************************************************/
static void	es_browser_push_error(duk_context *ctx, zbx_webdriver_t *wd)
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
	es_push_result_string(ctx, wd->last_error_message, strlen(wd->last_error_message));
	duk_put_prop_string(ctx, -2, "message");
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
	duk_idx_t	idx_result, idx_perf, idx_details, idx_summary, idx_marks;

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	idx_result = duk_push_object(ctx);

	duk_push_number(ctx, zbx_time() - wd->create_time);
	duk_put_prop_string(ctx, -2, "duration");

	if (SUCCEED == webdriver_has_error(wd))
	{
		es_browser_push_error(ctx, wd);
		duk_put_prop_string(ctx, idx_result, "error");
	}

	if (0 < wd->perf.details.values_num)
	{
		idx_perf = duk_push_object(ctx);

		idx_details = duk_push_array(ctx);
		for (int i = 0; i < wd->perf.details.values_num; i++)
		{
			duk_idx_t		idx;
			zbx_wd_perf_details_t	*details = &wd->perf.details.values[i];

			idx = duk_push_object(ctx);

			for (int j = 0; j < wd->perf.bookmarks.values_num; j++)
			{
				zbx_wd_perf_bookmark_t	*bookmark = &wd->perf.bookmarks.values[i];

				if (bookmark->details == details)
				{
					duk_push_string(ctx, bookmark->name);
					duk_put_prop_string(ctx, idx, "mark");

					break;
				}
			}

			if (NULL != details->navigation)
			{
				es_browser_push_performance_entry(wd->env, ctx, details->navigation);
				duk_put_prop_string(ctx, idx, "navigation");
			}

			es_browser_push_performance_entry(wd->env, ctx, details->resource);
			duk_put_prop_string(ctx, idx, "resource");

			if (0 != details->user.values_num)
			{
				duk_idx_t	idx_user;

				idx_user = duk_push_array(ctx);
				for (int j = 0; j < details->user.values_num; j++)
				{
					es_browser_push_performance_entry(wd->env, ctx, details->user.values[j]);
					duk_put_prop_index(ctx, idx_user, (duk_uarridx_t)j);
				}

				duk_put_prop_string(ctx, idx, "user");
			}

			duk_put_prop_index(ctx, idx_details, (duk_uarridx_t)i);
		}

		duk_put_prop_string(ctx, idx_perf, "details");

		idx_summary = duk_push_object(ctx);
		es_browser_push_performance_entry(wd->env, ctx, wd->perf.navigation_summary);
		duk_put_prop_string(ctx, idx_summary, "navigation");
		es_browser_push_performance_entry(wd->env, ctx, wd->perf.resource_summary);
		duk_put_prop_string(ctx, idx_summary, "resource");
		duk_put_prop_string(ctx, idx_perf, "summary");

		idx_marks = duk_push_array(ctx);
		for (int i = 0; i < wd->perf.bookmarks.values_num; i++)
		{
			duk_idx_t	idx;
			zbx_wd_perf_bookmark_t	*bookmark = &wd->perf.bookmarks.values[i];

			idx = duk_push_object(ctx);
			duk_push_string(ctx, bookmark->name);
			duk_put_prop_string(ctx, idx, "name");
			duk_push_number(ctx, (double)i);
			duk_put_prop_string(ctx, idx, "index");

			duk_put_prop_index(ctx, idx_marks, (duk_uarridx_t)i);
		}
		duk_put_prop_string(ctx, idx_perf, "marks");

		duk_put_prop_string(ctx, idx_result, "performance_data");
	}

	return 1;
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

	timeout = duk_get_int(ctx, 0);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

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

	timeout = duk_get_int(ctx, 0);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

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

	timeout = duk_get_int(ctx, 0);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

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

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != webdriver_get_cookies(wd, &cookies, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot get cookies: %s", error);
		zbx_free(error);
		goto out;
	}
	duk_push_heapptr(ctx, wd->env->json_parse);
	es_push_result_string(ctx, cookies, strlen(cookies));
	duk_pcall(ctx, 1);
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
	const char	*cookie_cesu;

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	duk_push_heapptr(ctx, wd->env->json_stringify);
	duk_dup(ctx, 0);
	duk_pcall(ctx, 1);

	cookie_cesu = duk_safe_to_string(ctx, -1);

	/* to be sure that the object is not freed during argument evaluation - acquire it again */
	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != es_duktape_string_decode(cookie_cesu, &cookie_json))
	{
		(void)browser_push_error(ctx, wd, "cannot convert cookie object to JSON format");

		return duk_throw(ctx);
	}

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

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != webdriver_get_screenshot(wd, &screenshot, &error))
	{
		(void) browser_push_error(ctx, wd, "cannot capture screenshot: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	es_push_result_string(ctx, screenshot, strlen(screenshot));
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

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (0 > width || width > 8192 || 0 > height || height > 8192)
	{
		(void)browser_push_error(ctx, wd, "unsupported screen size");

		return duk_throw(ctx);
	}

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
 * Purpose: get browser error                                                 *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_error(duk_context *ctx)
{
	zbx_webdriver_t	*wd;

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED == webdriver_has_error(wd))
		es_browser_push_error(ctx, wd);
	else
		duk_push_null(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: discard browser error                                             *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_discard_error(duk_context *ctx)
{
	zbx_webdriver_t	*wd;

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

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
	const char	*message_cesu;

	message_cesu = duk_safe_to_string(ctx, 0);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != es_duktape_string_decode(message_cesu, &message))
	{
		(void)browser_push_error(ctx, wd, "cannot convert message parameter to utf8");

		return duk_throw(ctx);
	}

	webdriver_set_error(wd, message);

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

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != webdriver_get_page_source(wd, &source, &error))
	{
		(void) browser_push_error(ctx, wd, "cannot get page source: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	es_push_result_string(ctx, source, strlen(source));
	zbx_free(source);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get alert                                                         *
 *                                                                            *
 * Return value: Alert object if found or null                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_alert(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*error = NULL, *alert = NULL;
	int		err_index = -1;

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != webdriver_get_alert(wd, &alert, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot get alert: %s", error);
		zbx_free(error);

		goto out;
	}

	if (NULL != alert)
	{
		zbx_replace_invalid_utf8(alert);
		wd_alert_create(ctx, wd, alert);
		zbx_free(alert);
	}
	else
		duk_push_null(ctx);
out:

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: collect performance data                                          *
 *                                                                            *
 * Stack 0 - bookmark (string/null, optional)                                 *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_collect_perf_entries(duk_context *ctx)
{
	int		err_index = -1;
	zbx_webdriver_t	*wd;
	char		*bookmark = NULL, *error = NULL;
	const char	*bookmark_str = NULL;

	if (!duk_is_null(ctx, 0) && !duk_is_undefined(ctx, 0))
		bookmark_str = duk_safe_to_string(ctx, 0);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (NULL != bookmark_str && SUCCEED != es_duktape_string_decode(bookmark_str, &bookmark))
	{
		err_index = browser_push_error(ctx, wd, "cannot convert bookmark parameter to utf8");

		goto out;
	}

	if (SUCCEED != webdriver_collect_perf_data(wd, bookmark, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot collect performance data: %s", error);
		zbx_free(error);
	}

	zbx_free(bookmark);
out:
	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get raw performance data                                          *
 *                                                                            *
 * Return value: array of performance entry objects                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_raw_perf_entries(duk_context *ctx)
{
	zbx_webdriver_t		*wd;
	char			*error = NULL, *result = NULL;
	struct zbx_json_parse	jp;

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != webdriver_get_raw_perf_data(wd, NULL, &jp, &error))
	{
		(void)browser_push_error(ctx, wd, "cannot get performance data: %s", error);
		zbx_free(error);

		return duk_throw(ctx);
	}

	duk_push_heapptr(ctx, wd->env->json_parse);

	result = zbx_substr(wd->data, jp.start - wd->data, jp.end - wd->data);
	zbx_replace_invalid_utf8(result);
	es_push_result_string(ctx, result, strlen(result));
	zbx_free(result);

	duk_pcall(ctx, 1);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get performance data by type                                      *
 *                                                                            *
 * Stack 0 - performance entry type                                           *
 *                                                                            *
 * Return value: array of performance entry objects                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_get_raw_perf_entries_by_type(duk_context *ctx)
{
	zbx_webdriver_t		*wd;
	char			*error = NULL, *entry_type = NULL;
	struct zbx_json_parse	jp;
	int			err_index = -1;
	const char		*type_cesu;

	if (duk_is_null(ctx, 0) || duk_is_undefined(ctx, 0))
	{
		if (NULL == (wd = es_webdriver(ctx)))
			return duk_throw(ctx);

		(void)browser_push_error(ctx,  wd, "missing entry type parameter");
		return duk_throw(ctx);
	}

	type_cesu = duk_safe_to_string(ctx, 0);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != es_duktape_string_decode(type_cesu, &entry_type))
	{
		(void)browser_push_error(ctx, wd, "cannot convert entry type parameter to utf8");
		return duk_throw(ctx);
	}

	if (SUCCEED != webdriver_get_raw_perf_data(wd, entry_type, &jp, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot get performance data: %s", error);
		zbx_free(error);
	}
	else
	{
		char	*result = NULL;

		duk_push_heapptr(ctx, wd->env->json_parse);

		result = zbx_substr(wd->data, jp.start - wd->data, jp.end - wd->data);
		zbx_replace_invalid_utf8(result);
		es_push_result_string(ctx, result, strlen(result));
		zbx_free(result);

		duk_pcall(ctx, 1);
	}

	zbx_free(entry_type);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get browser element id                                            *
 *                                                                            *
 * Parameters: ctx - [IN]                                                     *
 *             idx - [IN] element object index on stack                       *
 *                                                                            *
 * Return value: Allocated element id string or NULL                          *
 *                                                                            *
 ******************************************************************************/
static char	*es_browser_get_element_id(duk_context *ctx, duk_idx_t idx)
{
	zbx_es_env_t	*env;
	void		*el;

	if (duk_get_type(ctx, 0) != DUK_TYPE_OBJECT)
		return NULL;

	if (NULL == (env = zbx_es_get_env(ctx)))
		return NULL;

	if (NULL == (el = es_obj_get_data(env, duk_require_heapptr(ctx, idx), ES_OBJ_ELEMENT)))
		return NULL;

	return zbx_strdup(NULL, wd_element_get_id(el));
}

/******************************************************************************
 *                                                                            *
 * Purpose: set custom error                                                  *
 *                                                                            *
 * Stack 0 - script                                                           *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_switch_frame(duk_context *ctx)
{
	zbx_webdriver_t	*wd;
	char		*frame = NULL, *error = NULL;
	int		err_index = -1;

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (!duk_is_null(ctx, 0) && !duk_is_undefined(ctx, 0))
	{
		if (duk_get_type(ctx, 0) == DUK_TYPE_NUMBER)
		{
			frame = zbx_dsprintf(NULL, "%.0f", duk_get_number(ctx, 0));
		}
		else if (NULL == (frame = es_browser_get_element_id(ctx, 0)))
		{
			(void)browser_push_error(ctx, wd, "invalid parameter");
			return duk_throw(ctx);
		}
	}

	if (SUCCEED != webdriver_switch_frame(wd, frame, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot switch frame: %s", error);
		zbx_free(error);
	}

	zbx_free(frame);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

#ifdef BROWSER_EXECUTE_SCRIPT
/******************************************************************************
 *                                                                            *
 * Purpose: execute custom script                                             *
 *                                                                            *
 * Stack 0 - script                                                           *
 *                                                                            *
 * Return value: script result                                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_browser_execute_script(duk_context *ctx)
{
	zbx_webdriver_t		*wd;
	char			*script = NULL, *error = NULL;
	int			err_index = -1;
	struct zbx_json_parse	jp;
	const char		*script_cesu;

	script_cesu = duk_safe_to_string(ctx, 0);

	if (NULL == (wd = es_webdriver(ctx)))
		return duk_throw(ctx);

	if (SUCCEED != es_duktape_string_decode(script_cesu, &script))
	{
		(void)browser_push_error(ctx, wd, "cannot convert script parameter to utf8");

		return duk_throw(ctx);
	}

	if (SUCCEED != webdriver_execute_script(wd, script, &jp, &error))
	{
		err_index = browser_push_error(ctx, wd, "cannot execute script: %s", error);
		zbx_free(error);
	}
	else
	{
		char	*result = NULL;
		size_t	result_alloc = 0;

		if (NULL == zbx_json_decodevalue_dyn(jp.start, &result, &result_alloc, NULL))
		{
			result = (char *)zbx_malloc(NULL, jp.end - jp.start + 2);
			memcpy(result, jp.start, jp.end - jp.start + 1);
			result[jp.end - jp.start + 1] = '\0';
		}

		duk_push_string(ctx, result);
		zbx_free(result);
	}

	zbx_free(script);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
}

#endif

static const duk_function_list_entry	browser_methods[] = {
	{"navigate", es_browser_navigate, 1},
	{"getUrl", es_browser_get_url, 0},
	{"findElement", es_browser_find_element, 2},
	{"findElements", es_browser_find_elements, 2},
	{"getResult", es_browser_get_result, 0},
	{"setScriptTimeout", es_browser_set_script_timeout, 1},
	{"setSessionTimeout", es_browser_set_session_timeout, 1},
	{"setElementWaitTimeout", es_browser_set_element_wait_timeout, 1},
	{"getCookies", es_browser_get_cookies, 0},
	{"addCookie", es_browser_add_cookie, 1},
	{"getScreenshot", es_browser_get_screenshot, 0},
	{"setScreenSize", es_browser_set_screen_size, 2},
	{"setError", es_browser_set_error, 1},
	{"getError", es_browser_get_error, 0},
	{"discardError", es_browser_discard_error, 0},
	{"collectPerfEntries", es_browser_collect_perf_entries, 1},
	{"getRawPerfEntries", es_browser_get_raw_perf_entries, 0},
	{"getRawPerfEntriesByType", es_browser_get_raw_perf_entries_by_type, 1},
	{"getPageSource", es_browser_get_page_source, 0},
	{"getAlert", es_browser_get_alert, 0},
	{"switchFrame", es_browser_switch_frame, 1},
#ifdef BROWSER_EXECUTE_SCRIPT
	{"executeScript", es_browser_execute_script, 1},
#endif
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
static duk_ret_t	es_browser_chrome_options(duk_context *ctx)
{
	duk_push_object(ctx);		/* {} */
	duk_push_object(ctx);		/* capabilities */

	duk_push_object(ctx);		/* alwaysMatch */
	duk_push_string(ctx, "chrome");
	duk_put_prop_string(ctx, -2, "browserName");
	duk_push_string(ctx, "normal");
	duk_put_prop_string(ctx, -2, "pageLoadStrategy");

	duk_push_object(ctx);		/* goog:chromeOptions */
	duk_push_array(ctx);		/* args */
	duk_push_string(ctx, "--headless=new");
	duk_put_prop_index(ctx, -2, 0);
	duk_put_prop_string(ctx, -2, "args");
	duk_put_prop_string(ctx, -2, "goog:chromeOptions");

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
static duk_ret_t	es_browser_firefox_options(duk_context *ctx)
{
	duk_push_object(ctx);		/* {} */
	duk_push_object(ctx);		/* capabilities */

	duk_push_object(ctx);		/* alwaysMatch */
	duk_push_string(ctx, "firefox");
	duk_put_prop_string(ctx, -2, "browserName");
	duk_push_string(ctx, "normal");
	duk_put_prop_string(ctx, -2, "pageLoadStrategy");

	duk_push_object(ctx);		/* moz:firefoxOptions */
	duk_push_array(ctx);		/* args */
	duk_push_string(ctx, "--headless");
	duk_put_prop_index(ctx, -2, 0);
	duk_put_prop_string(ctx, -2, "args");
	duk_put_prop_string(ctx, -2, "moz:firefoxOptions");

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
static duk_ret_t	es_browser_safari_options(duk_context *ctx)
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
static duk_ret_t	es_browser_edge_options(duk_context *ctx)
{
	duk_push_object(ctx);		/* {} */
	duk_push_object(ctx);		/* capabilities */

	duk_push_object(ctx);		/* alwaysMatch */

	duk_push_string(ctx, "MicrosoftEdge");
	duk_put_prop_string(ctx, -2, "browserName");
	duk_push_string(ctx, "normal");
	duk_put_prop_string(ctx, -2, "pageLoadStrategy");

	duk_push_object(ctx);		/* ms:edgeOptions */
	duk_push_array(ctx);		/* args */
	duk_push_string(ctx, "--headless=new");
	duk_put_prop_index(ctx, -2, 0);
	duk_put_prop_string(ctx, -2, "args");
	duk_put_prop_string(ctx, -2, "ms:edgeOptions");

	duk_put_prop_string(ctx, -2, "alwaysMatch");
	duk_put_prop_string(ctx, -2, "capabilities");

	return 1;
}

static const duk_function_list_entry	browser_static_methods[] = {
	{"chromeOptions", es_browser_chrome_options, 0},
	{"firefoxOptions", es_browser_firefox_options, 0},
	{"safariOptions", es_browser_safari_options, 0},
	{"edgeOptions", es_browser_edge_options, 0},
	{0}
};

static int	es_browser_create_prototype(duk_context *ctx)
{
	duk_push_c_function(ctx, es_browser_ctor, 1);
	duk_push_object(ctx);

	duk_put_function_list(ctx, -1, browser_methods);

	if (1 != duk_put_prop_string(ctx, -2, "prototype"))
		return FAIL;

	duk_put_function_list(ctx, -1, browser_static_methods);

	if (1 != duk_put_global_string(ctx, "Browser"))
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

	if (FAIL == es_browser_create_prototype(es->env->ctx))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);

		return FAIL;
	}

#ifdef HAVE_LIBCURL
	return es_browser_init_errors(es, error);
#else
	return SUCCEED;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize javascript environment for browser monitoring          *
 *                                                                            *
 ******************************************************************************/
int	zbx_es_init_browser_env(zbx_es_t *es, const char *endpoint, char **error)
{
	es->env->browser_endpoint = zbx_strdup(NULL, endpoint);

	/* initialize Browser prototype */
	return es_init_browser(es, error);
}
