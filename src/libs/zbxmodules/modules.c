/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "module.h"
#include "zbxmodules.h"

#include "log.h"
#include "sysinfo.h"
#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"

#define ZBX_MODULE_FUNC_INIT			"zbx_module_init"
#define ZBX_MODULE_FUNC_API_VERSION		"zbx_module_api_version"
#define ZBX_MODULE_FUNC_ITEM_LIST		"zbx_module_item_list"
#define ZBX_MODULE_FUNC_ITEM_PROCESS		"zbx_module_item_process"
#define ZBX_MODULE_FUNC_ITEM_TIMEOUT		"zbx_module_item_timeout"
#define ZBX_MODULE_FUNC_UNINIT			"zbx_module_uninit"
#define ZBX_MODULE_FUNC_HISTORY_WRITE_CBS	"zbx_module_history_write_cbs"

ZBX_VECTOR_DECL(module, zbx_module_t);
ZBX_VECTOR_IMPL(module, zbx_module_t);

static zbx_vector_module_t	modules;

zbx_history_float_cb_t		*history_float_cbs = NULL;
zbx_history_integer_cb_t	*history_integer_cbs = NULL;
zbx_history_string_cb_t		*history_string_cbs = NULL;
zbx_history_text_cb_t		*history_text_cbs = NULL;
zbx_history_log_cb_t		*history_log_cbs = NULL;

/******************************************************************************
 *                                                                            *
 * Function: zbx_register_module_items                                        *
 *                                                                            *
 * Purpose: add items supported by module                                     *
 *                                                                            *
 * Parameters: metrics       - list of items supported by module              *
 *             error         - error buffer                                   *
 *             max_error_len - error buffer size                              *
 *                                                                            *
 * Return value: SUCCEED - all module items were added or there were none     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_register_module_items(ZBX_METRIC *metrics, char *error, size_t max_error_len)
{
	int	i;

	for (i = 0; NULL != metrics[i].key; i++)
	{
		/* accept only CF_HAVEPARAMS flag from module items */
		metrics[i].flags &= CF_HAVEPARAMS;
		/* the flag means that the items comes from a loadable module */
		metrics[i].flags |= CF_MODULE;

		if (SUCCEED != add_metric(&metrics[i], error, max_error_len))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_register_module                                              *
 *                                                                            *
 * Purpose: add module to the list of successfully loaded modules             *
 *                                                                            *
 ******************************************************************************/
static zbx_module_t	*zbx_register_module(void *lib, char *name)
{
	zbx_module_t	module;

	module.lib = lib;
	module.name = zbx_strdup(NULL, name);
	zbx_vector_module_append(&modules, module);

	return &modules.values[modules.values_num - 1];
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_register_history_write_cbs                                   *
 *                                                                            *
 * Purpose: registers callback functions for history export                   *
 *                                                                            *
 * Parameters: module            - module pointer for later reference         *
 *             history_write_cbs - callbacks                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_register_history_write_cbs(zbx_module_t *module, ZBX_HISTORY_WRITE_CBS history_write_cbs)
{
	if (NULL != history_write_cbs.history_float_cb)
	{
		int	j = 0;

		if (NULL == history_float_cbs)
		{
			history_float_cbs = (zbx_history_float_cb_t *)zbx_malloc(history_float_cbs, sizeof(zbx_history_float_cb_t));
			history_float_cbs[0].module = NULL;
		}

		while (NULL != history_float_cbs[j].module)
			j++;

		history_float_cbs = (zbx_history_float_cb_t *)zbx_realloc(history_float_cbs, (j + 2) * sizeof(zbx_history_float_cb_t));
		history_float_cbs[j].module = module;
		history_float_cbs[j].history_float_cb = history_write_cbs.history_float_cb;
		history_float_cbs[j + 1].module = NULL;
	}

	if (NULL != history_write_cbs.history_integer_cb)
	{
		int	j = 0;

		if (NULL == history_integer_cbs)
		{
			history_integer_cbs = (zbx_history_integer_cb_t *)zbx_malloc(history_integer_cbs, sizeof(zbx_history_integer_cb_t));
			history_integer_cbs[0].module = NULL;
		}

		while (NULL != history_integer_cbs[j].module)
			j++;

		history_integer_cbs = (zbx_history_integer_cb_t *)zbx_realloc(history_integer_cbs, (j + 2) * sizeof(zbx_history_integer_cb_t));
		history_integer_cbs[j].module = module;
		history_integer_cbs[j].history_integer_cb = history_write_cbs.history_integer_cb;
		history_integer_cbs[j + 1].module = NULL;
	}

	if (NULL != history_write_cbs.history_string_cb)
	{
		int	j = 0;

		if (NULL == history_string_cbs)
		{
			history_string_cbs = (zbx_history_string_cb_t *)zbx_malloc(history_string_cbs, sizeof(zbx_history_string_cb_t));
			history_string_cbs[0].module = NULL;
		}

		while (NULL != history_string_cbs[j].module)
			j++;

		history_string_cbs = (zbx_history_string_cb_t *)zbx_realloc(history_string_cbs, (j + 2) * sizeof(zbx_history_string_cb_t));
		history_string_cbs[j].module = module;
		history_string_cbs[j].history_string_cb = history_write_cbs.history_string_cb;
		history_string_cbs[j + 1].module = NULL;
	}

	if (NULL != history_write_cbs.history_text_cb)
	{
		int	j = 0;

		if (NULL == history_text_cbs)
		{
			history_text_cbs = (zbx_history_text_cb_t *)zbx_malloc(history_text_cbs, sizeof(zbx_history_text_cb_t));
			history_text_cbs[0].module = NULL;
		}

		while (NULL != history_text_cbs[j].module)
			j++;

		history_text_cbs = (zbx_history_text_cb_t *)zbx_realloc(history_text_cbs, (j + 2) * sizeof(zbx_history_text_cb_t));
		history_text_cbs[j].module = module;
		history_text_cbs[j].history_text_cb = history_write_cbs.history_text_cb;
		history_text_cbs[j + 1].module = NULL;
	}

	if (NULL != history_write_cbs.history_log_cb)
	{
		int	j = 0;

		if (NULL == history_log_cbs)
		{
			history_log_cbs = (zbx_history_log_cb_t *)zbx_malloc(history_log_cbs, sizeof(zbx_history_log_cb_t));
			history_log_cbs[0].module = NULL;
		}

		while (NULL != history_log_cbs[j].module)
			j++;

		history_log_cbs = (zbx_history_log_cb_t *)zbx_realloc(history_log_cbs, (j + 2) * sizeof(zbx_history_log_cb_t));
		history_log_cbs[j].module = module;
		history_log_cbs[j].history_log_cb = history_write_cbs.history_log_cb;
		history_log_cbs[j + 1].module = NULL;
	}
}

static int	zbx_module_compare_func(const void *d1, const void *d2)
{
	const zbx_module_t	*m1 = *(const zbx_module_t **)d1;
	const zbx_module_t	*m2 = *(const zbx_module_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(m1->lib, m2->lib);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_load_module                                                  *
 *                                                                            *
 * Purpose: load loadable module                                              *
 *                                                                            *
 * Parameters: path    - directory where modules are located                  *
 *             name    - module name                                          *
 *             timeout - timeout in seconds for processing of items by module *
 *                                                                            *
 * Return value: SUCCEED - module was successfully loaded or found amongst    *
 *                         previously loaded                                  *
 *               FAIL    - loading of module failed                           *
 *                                                                            *
 ******************************************************************************/
static int	zbx_load_module(const char *path, char *name, int timeout)
{
	void			*lib;
	char			full_name[MAX_STRING_LEN], error[MAX_STRING_LEN];
	int			(*func_init)(void), (*func_version)(void), version;
	ZBX_METRIC		*(*func_list)(void);
	void			(*func_timeout)(int);
	ZBX_HISTORY_WRITE_CBS	(*func_history_write_cbs)(void);
	zbx_module_t		*module, module_tmp;

	zbx_snprintf(full_name, sizeof(full_name), "%s/%s", path, name);

	zabbix_log(LOG_LEVEL_DEBUG, "loading module \"%s\"", full_name);

	if (NULL == (lib = dlopen(full_name, RTLD_NOW)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot load module \"%s\": %s", name, dlerror());
		return FAIL;
	}

	module_tmp.lib = lib;
	if (FAIL != zbx_vector_module_search(&modules, module_tmp, zbx_module_compare_func))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "module \"%s\" has already beed loaded", name);
		return SUCCEED;
	}

	if (NULL == (func_version = (int (*)(void))dlsym(lib, ZBX_MODULE_FUNC_API_VERSION)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot find \"" ZBX_MODULE_FUNC_API_VERSION "()\""
				" function in module \"%s\": %s", name, dlerror());
		goto fail;
	}

	if (ZBX_MODULE_API_VERSION != (version = func_version()))
	{
		zabbix_log(LOG_LEVEL_CRIT, "unsupported module \"%s\" version: %d", name, version);
		goto fail;
	}

	if (NULL == (func_init = (int (*)(void))dlsym(lib, ZBX_MODULE_FUNC_INIT)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_INIT "()\""
				" function in module \"%s\": %s", name, dlerror());
	}
	else if (ZBX_MODULE_OK != func_init())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize module \"%s\"", name);
		goto fail;
	}

	if (NULL == (func_list = (ZBX_METRIC *(*)(void))dlsym(lib, ZBX_MODULE_FUNC_ITEM_LIST)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_ITEM_LIST "()\""
				" function in module \"%s\": %s", name, dlerror());
	}
	else
	{
		if (SUCCEED != zbx_register_module_items(func_list(), error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot load module \"%s\": %s", name, error);
			goto fail;
		}

		if (NULL == (func_timeout = (void (*)(int))dlsym(lib, ZBX_MODULE_FUNC_ITEM_TIMEOUT)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_ITEM_TIMEOUT "()\""
					" function in module \"%s\": %s", name, dlerror());
		}
		else
			func_timeout(timeout);
	}

	/* module passed validation and can now be registered */
	module = zbx_register_module(lib, name);

	if (NULL == (func_history_write_cbs = (ZBX_HISTORY_WRITE_CBS (*)(void))dlsym(lib,
			ZBX_MODULE_FUNC_HISTORY_WRITE_CBS)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_HISTORY_WRITE_CBS "()\""
				" function in module \"%s\": %s", name, dlerror());
	}
	else
		zbx_register_history_write_cbs(module, func_history_write_cbs());

	return SUCCEED;
fail:
	dlclose(lib);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_load_modules                                                 *
 *                                                                            *
 * Purpose: load loadable modules (dynamic libraries)                         *
 *                                                                            *
 * Parameters: path - directory where modules are located                     *
 *             file_names - list of module names                              *
 *             timeout - timeout in seconds for processing of items by module *
 *             verbose - output list of loaded modules                        *
 *                                                                            *
 * Return value: SUCCEED - all modules are successfully loaded                *
 *               FAIL - loading of modules failed                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_load_modules(const char *path, char **file_names, int timeout, int verbose)
{
	const char	*__function_name = "zbx_load_modules";

	char		**file_name;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_module_create(&modules);

	if (NULL == *file_names)
		goto out;

	for (file_name = file_names; NULL != *file_name; file_name++)
	{
		if (SUCCEED != (ret = zbx_load_module(path, *file_name, timeout)))
			goto out;
	}

	if (0 != verbose)
	{
		char	*buffer;
		int	i = 0;

		/* if execution reached this point at least one module was loaded successfully */
		buffer = zbx_strdcat(NULL, modules.values[i++].name);

		while (i < modules.values_num)
		{
			buffer = zbx_strdcat(buffer, ", ");
			buffer = zbx_strdcat(buffer, modules.values[i++].name);
		}

		zabbix_log(LOG_LEVEL_WARNING, "loaded modules: %s", buffer);
		zbx_free(buffer);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_unload_module                                                *
 *                                                                            *
 * Purpose: unload module and free allocated resources                        *
 *                                                                            *
 ******************************************************************************/
static void	zbx_unload_module(zbx_module_t	*module)
{
	int		(*func_uninit)(void);

	if (NULL == (func_uninit = (int (*)(void))dlsym(module->lib, ZBX_MODULE_FUNC_UNINIT)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_UNINIT "()\""
				" function in module \"%s\": %s", module->name, dlerror());
	}
	else if (ZBX_MODULE_OK != func_uninit())
		zabbix_log(LOG_LEVEL_WARNING, "uninitialization of module \"%s\" failed", module->name);

	dlclose(module->lib);
	zbx_free(module->name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_unload_modules                                               *
 *                                                                            *
 * Purpose: Unload already loaded loadable modules (dynamic libraries).       *
 *          It is called on process shutdown.                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_unload_modules(void)
{
	const char	*__function_name = "zbx_unload_modules";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_free(history_float_cbs);
	zbx_free(history_integer_cbs);
	zbx_free(history_string_cbs);
	zbx_free(history_text_cbs);
	zbx_free(history_log_cbs);

	zbx_vector_module_clear_type(&modules, zbx_unload_module);
	zbx_vector_module_destroy(&modules);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
