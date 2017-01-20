/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#define ZBX_MODULE_FUNC_INIT		"zbx_module_init"
#define ZBX_MODULE_FUNC_API_VERSION	"zbx_module_api_version"
#define ZBX_MODULE_FUNC_ITEM_LIST	"zbx_module_item_list"
#define ZBX_MODULE_FUNC_ITEM_PROCESS	"zbx_module_item_process"
#define ZBX_MODULE_FUNC_ITEM_TIMEOUT	"zbx_module_item_timeout"
#define ZBX_MODULE_FUNC_UNINIT		"zbx_module_uninit"

static void	**modules = NULL;

/******************************************************************************
 *                                                                            *
 * Function: register_module                                                  *
 *                                                                            *
 * Purpose: Add module to the list of loaded modules (dynamic libraries).     *
 *          It skips a module if it is already registered.                    *
 *                                                                            *
 * Parameters: module - library handler                                       *
 *                                                                            *
 * Return value: SUCCEED - if module is successfully registered               *
 *               FAIL - if module is already registered                       *
 *                                                                            *
 ******************************************************************************/
static int	register_module(void *module)
{
	const char	*__function_name = "register_module";

	int		i = 0, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == modules)
	{
		modules = zbx_malloc(modules, sizeof(void *));
		modules[0] = NULL;
	}

	while (NULL != modules[i])
	{
		if (module == modules[i])	/* a module is already registered */
			goto out;
		i++;
	}

	modules = zbx_realloc(modules, (i + 2) * sizeof(void *));
	modules[i] = module;
	modules[i + 1] = NULL;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: load_modules                                                     *
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
int	load_modules(const char *path, char **file_names, int timeout, int verbose)
{
	const char	*__function_name = "load_modules";

	char		**file_name, *buffer = NULL;
	void		*lib;
	char		full_name[MAX_STRING_LEN], error[MAX_STRING_LEN];
	int		(*func_init)(), (*func_version)();
	ZBX_METRIC	*(*func_list)();
	void		(*func_timeout)();
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

		if (ZBX_MODULE_API_VERSION_ONE != (i = func_version()))
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
		func_timeout = (void (*)(void))dlsym(lib, ZBX_MODULE_FUNC_ITEM_TIMEOUT);
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

		if (SUCCEED == register_module(lib))
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
 * Function: unload_modules                                                   *
 *                                                                            *
 * Purpose: Unload already loaded loadable modules (dynamic libraries).       *
 *          It is called on process shutdown.                                 *
 *                                                                            *
 ******************************************************************************/
void	unload_modules()
{
	const char	*__function_name = "unload_modules";

	int		(*func_uninit)();
	void		**module;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* there are no registered modules */
	if (NULL == modules)
		goto out;

	for (module = modules; NULL != *module; module++)
	{
		func_uninit = (int (*)(void))dlsym(*module, ZBX_MODULE_FUNC_UNINIT);
		if (NULL == func_uninit)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_UNINIT "()\" function: %s",
					dlerror());
			dlclose(*module);
			continue;
		}

		if (ZBX_MODULE_OK != func_uninit())
			zabbix_log(LOG_LEVEL_WARNING, "uninitialization failed");

		dlclose(*module);
	}

	zbx_free(modules);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
