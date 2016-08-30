/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#define ZBX_MODULE_FUNC_INIT			"zbx_module_init"
#define ZBX_MODULE_FUNC_API_VERSION		"zbx_module_api_version"
#define ZBX_MODULE_FUNC_ITEM_LIST		"zbx_module_item_list"
#define ZBX_MODULE_FUNC_ITEM_PROCESS		"zbx_module_item_process"
#define ZBX_MODULE_FUNC_ITEM_TIMEOUT		"zbx_module_item_timeout"
#define ZBX_MODULE_FUNC_UNINIT			"zbx_module_uninit"
#define ZBX_MODULE_FUNC_HISTORY_WRITE_CBS	"zbx_module_history_write_cbs"

static zbx_module_t	*modules = NULL;

zbx_history_float_cb_t		*history_float_cbs = NULL;
zbx_history_integer_cb_t	*history_integer_cbs = NULL;
zbx_history_string_cb_t		*history_string_cbs = NULL;
zbx_history_text_cb_t		*history_text_cbs = NULL;
zbx_history_log_cb_t		*history_log_cbs = NULL;

/******************************************************************************
 *                                                                            *
 * Function: register_module                                                  *
 *                                                                            *
 * Purpose: Add module to the list of loaded modules (dynamic libraries).     *
 *          It skips a module if it is already registered.                    *
 *                                                                            *
 * Parameters: lib  - module library                                          *
 *             name - module name                                             *
 *                                                                            *
 * Return value: SUCCEED - if module is successfully registered               *
 *               FAIL - if module is already registered                       *
 *                                                                            *
 ******************************************************************************/
static int	register_module(void *lib, char *name)
{
	const char		*__function_name = "register_module";

	ZBX_HISTORY_WRITE_CBS	(*func_history_write_cbs)(void);
	ZBX_HISTORY_WRITE_CBS	history_write_cbs;
	int			i = 0, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == modules)
	{
		modules = zbx_malloc(modules, sizeof(zbx_module_t));
		modules[0].lib = NULL;
	}

	while (NULL != modules[i].lib)
	{
		if (lib == modules[i].lib)	/* a module is already registered */
			goto out;
		i++;
	}

	modules = zbx_realloc(modules, (i + 2) * sizeof(zbx_module_t));
	modules[i].lib = lib;
	modules[i].name = zbx_strdup(NULL, name);
	modules[i + 1].lib = NULL;

	ret = SUCCEED;

	/* module successfully registered, now comes optional part */

	func_history_write_cbs = (ZBX_HISTORY_WRITE_CBS (*)(void))dlsym(lib, ZBX_MODULE_FUNC_HISTORY_WRITE_CBS);

	if (NULL == func_history_write_cbs)
		goto out;

	history_write_cbs = func_history_write_cbs();

	if (NULL != history_write_cbs.history_float_cb)
	{
		int	j = 0;

		if (NULL == history_float_cbs)
		{
			history_float_cbs = zbx_malloc(history_float_cbs, sizeof(zbx_history_float_cb_t));
			history_float_cbs[0].module = NULL;
		}

		while (NULL != history_float_cbs[j].module)
			j++;

		history_float_cbs = zbx_realloc(history_float_cbs, (j + 2) * sizeof(zbx_history_float_cb_t));
		history_float_cbs[j].module = &modules[i];
		history_float_cbs[j].history_float_cb = history_write_cbs.history_float_cb;
		history_float_cbs[j + 1].module = NULL;
	}

	if (NULL != history_write_cbs.history_integer_cb)
	{
		int	j = 0;

		if (NULL == history_integer_cbs)
		{
			history_integer_cbs = zbx_malloc(history_integer_cbs, sizeof(zbx_history_integer_cb_t));
			history_integer_cbs[0].module = NULL;
		}

		while (NULL != history_integer_cbs[j].module)
			j++;

		history_integer_cbs = zbx_realloc(history_integer_cbs, (j + 2) * sizeof(zbx_history_integer_cb_t));
		history_integer_cbs[j].module = &modules[i];
		history_integer_cbs[j].history_integer_cb = history_write_cbs.history_integer_cb;
		history_integer_cbs[j + 1].module = NULL;
	}

	if (NULL != history_write_cbs.history_string_cb)
	{
		int	j = 0;

		if (NULL == history_string_cbs)
		{
			history_string_cbs = zbx_malloc(history_string_cbs, sizeof(zbx_history_string_cb_t));
			history_string_cbs[0].module = NULL;
		}

		while (NULL != history_string_cbs[j].module)
			j++;

		history_string_cbs = zbx_realloc(history_string_cbs, (j + 2) * sizeof(zbx_history_string_cb_t));
		history_string_cbs[j].module = &modules[i];
		history_string_cbs[j].history_string_cb = history_write_cbs.history_string_cb;
		history_string_cbs[j + 1].module = NULL;
	}

	if (NULL != history_write_cbs.history_text_cb)
	{
		int	j = 0;

		if (NULL == history_text_cbs)
		{
			history_text_cbs = zbx_malloc(history_text_cbs, sizeof(zbx_history_text_cb_t));
			history_text_cbs[0].module = NULL;
		}

		while (NULL != history_text_cbs[j].module)
			j++;

		history_text_cbs = zbx_realloc(history_text_cbs, (j + 2) * sizeof(zbx_history_text_cb_t));
		history_text_cbs[j].module = &modules[i];
		history_text_cbs[j].history_text_cb = history_write_cbs.history_text_cb;
		history_text_cbs[j + 1].module = NULL;
	}

	if (NULL != history_write_cbs.history_log_cb)
	{
		int	j = 0;

		if (NULL == history_log_cbs)
		{
			history_log_cbs = zbx_malloc(history_log_cbs, sizeof(zbx_history_log_cb_t));
			history_log_cbs[0].module = NULL;
		}

		while (NULL != history_log_cbs[j].module)
			j++;

		history_log_cbs = zbx_realloc(history_log_cbs, (j + 2) * sizeof(zbx_history_log_cb_t));
		history_log_cbs[j].module = &modules[i];
		history_log_cbs[j].history_log_cb = history_write_cbs.history_log_cb;
		history_log_cbs[j + 1].module = NULL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_load_modules                                                 *
 *                                                                            *
 * Purpose: load loadable modules (dynamic libraries)                         *
 *          It skips a module in case of any errors                           *
 *                                                                            *
 * Parameters: path - directory where modules are located                     *
 *             file_names - list of module names                              *
 *             timeout - timeout in seconds for processing of items by module *
 *             verbose - output list of loaded modules                        *
 *                                                                            *
 * Return value: SUCCEED - all modules is successfully loaded                 *
 *               FAIL - loading of modules failed                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_load_modules(const char *path, char **file_names, int timeout, int verbose)
{
	const char	*__function_name = "zbx_load_modules";

	char		**file_name, *buffer = NULL;
	void		*lib;
	char		full_name[MAX_STRING_LEN], error[MAX_STRING_LEN];
	int		(*func_init)(void), (*func_version)(void);
	ZBX_METRIC	*(*func_list)(void);
	void		(*func_timeout)(int);
	int		i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (file_name = file_names; NULL != *file_name; file_name++)
	{
		zbx_snprintf(full_name, sizeof(full_name), "%s/%s", path, *file_name);

		zabbix_log(LOG_LEVEL_DEBUG, "loading module \"%s\"", full_name);

		if (NULL == (lib = dlopen(full_name, RTLD_NOW)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot load module \"%s\": %s", *file_name, dlerror());
			goto fail;
		}

		func_version = (int (*)(void))dlsym(lib, ZBX_MODULE_FUNC_API_VERSION);
		if (NULL == func_version)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot find \"" ZBX_MODULE_FUNC_API_VERSION "()\""
					" function in module \"%s\": %s", *file_name, dlerror());
			dlclose(lib);
			goto fail;
		}

		if (ZBX_MODULE_API_VERSION != (i = func_version()))
		{
			zabbix_log(LOG_LEVEL_CRIT, "unsupported module \"%s\" version: %d", *file_name, i);
			dlclose(lib);
			goto fail;
		}

		func_init = (int (*)(void))dlsym(lib, ZBX_MODULE_FUNC_INIT);
		if (NULL == func_init)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot find \"" ZBX_MODULE_FUNC_INIT "()\""
					" function in module \"%s\": %s", *file_name, dlerror());
			dlclose(lib);
			goto fail;
		}

		if (ZBX_MODULE_OK != func_init())
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot initialize module \"%s\"", *file_name);
			dlclose(lib);
			goto fail;
		}

		/* the function is optional, zabbix will load the module even if it is missing */
		func_timeout = (void (*)(int))dlsym(lib, ZBX_MODULE_FUNC_ITEM_TIMEOUT);
		if (NULL == func_timeout)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_ITEM_TIMEOUT "()\""
					" function in module \"%s\": %s", *file_name, dlerror());
		}
		else
			func_timeout(timeout);

		func_list = (ZBX_METRIC *(*)(void))dlsym(lib, ZBX_MODULE_FUNC_ITEM_LIST);
		if (NULL == func_list)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot find \"" ZBX_MODULE_FUNC_ITEM_LIST "()\""
					" function in module \"%s\": %s", *file_name, dlerror());
			dlclose(lib);
			continue;
		}

		if (SUCCEED == register_module(lib, *file_name))
		{
			ZBX_METRIC	*metrics;

			metrics = func_list();

			for (i = 0; NULL != metrics[i].key; i++)
			{
				/* accept only CF_HAVEPARAMS flag from module items */
				metrics[i].flags &= CF_HAVEPARAMS;
				/* the flag means that the items comes from a loadable module */
				metrics[i].flags |= CF_MODULE;
				if (SUCCEED != add_metric(&metrics[i], error, sizeof(error)))
				{
					zabbix_log(LOG_LEVEL_CRIT, "cannot load module \"%s\": %s", *file_name, error);
					exit(EXIT_FAILURE);
				}
			}

			if (1 == verbose)
			{
				if (NULL != buffer)
					buffer = zbx_strdcat(buffer, ", ");
				buffer = zbx_strdcat(buffer, *file_name);
			}
		}
	}

	if (NULL != buffer)
		zabbix_log(LOG_LEVEL_WARNING, "loaded modules: %s", buffer);

	ret = SUCCEED;
fail:
	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_unload_modules                                               *
 *                                                                            *
 * Purpose: Unload already loaded loadable modules (dynamic libraries).       *
 *          It is called on process shutdown.                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_unload_modules()
{
	const char	*__function_name = "zbx_unload_modules";

	int		(*func_uninit)(void);
	zbx_module_t	*module;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* there are no registered modules */
	if (NULL == modules)
		goto out;

	zbx_free(history_float_cbs);
	zbx_free(history_integer_cbs);
	zbx_free(history_string_cbs);
	zbx_free(history_text_cbs);
	zbx_free(history_log_cbs);

	for (module = modules; NULL != module->lib; module++)
	{
		func_uninit = (int (*)(void))dlsym(module->lib, ZBX_MODULE_FUNC_UNINIT);

		if (NULL == func_uninit)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_UNINIT "()\" function: %s",
					dlerror());
		}
		else if (ZBX_MODULE_OK != func_uninit())
			zabbix_log(LOG_LEVEL_WARNING, "uninitialization failed");

		dlclose(module->lib);
		zbx_free(module->name);
	}

	zbx_free(modules);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
