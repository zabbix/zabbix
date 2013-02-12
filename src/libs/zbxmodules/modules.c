/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

static void **modules = NULL;

/******************************************************************************
 *                                                                            *
 * Function: register_module                                                  *
 *                                                                            *
 * Purpose: add module to the list of loaded modules (dynamic libraries)      *
 *          It skips a module if it is already registered.                    *
 *                                                                            *
 * Parameters: module - library handler                                       *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 ******************************************************************************/
static void	register_module(void *module)
{
	const char	*__function_name = "register_module";
	int	i = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == modules)
	{
		modules = zbx_malloc(modules, sizeof(void *));
		modules[0] = NULL;
	}

	while (NULL != modules[i])
	{
		if (module == modules[i])
			return;
		i++;
	}

	modules = zbx_realloc(modules, (i + 2) * sizeof(void *));
	modules[i] = module;
	modules[i + 1] = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: load_modules                                                     *
 *                                                                            *
 * Purpose: load loadable modules (dynamic libraries)                         *
 *          It skips a module in case of any errors                           *
 *                                                                            *
 * Parameters: path - directory where modules are located                     *
 *             modules - list of module names                                 *
 *                                                                            *
 * Return value: 0 - success                                                  *
 *               -1 - loading of modules failed                               *
 *                                                                            *
 ******************************************************************************/
int	load_modules(const char *path, char **modules)
{
	const char	*__function_name = "load_modules";
	char	**module;
	char	**key, **keys;
	void	*lib;
	char	filename[MAX_STRING_LEN];
	int	(*func_init)();
	int	(*func_process)();
	char	**(*func_list)();
	int	ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (module = modules; NULL != *module; module++)
	{
		zbx_snprintf(filename, sizeof(filename), "%s/%s", path, *module);

		zabbix_log(LOG_LEVEL_WARNING, "Loading module \"%s\"", filename);

		lib = dlopen(filename, RTLD_NOW);

		if (NULL == lib)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Loading module failed: %s", dlerror());
			ret = FAIL;
			goto ret;
		}

		*(void **)(&func_init) = dlsym(lib, ZBX_MODULE_FUNC_INIT);
		if (NULL == func_init)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Cannot find initialization function: %s",
				filename, dlerror());
			dlclose(lib);
			ret = FAIL;
			goto ret;
		}

		if (ZBX_MODULE_OK != func_init())
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Initialization failed.",
				filename);
			dlclose(lib);
			ret = FAIL;
			goto ret;
		}

		*(void **)(&func_process) = dlsym(lib, ZBX_MODULE_FUNC_ITEM_PROCESS);
		if(NULL == func_process)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Cannot find item process function: %s",
				filename, dlerror());
			dlclose(lib);
			continue;
		}

		*(void **)(&func_list) = dlsym(lib, ZBX_MODULE_FUNC_ITEM_LIST);
		if(NULL == func_list)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Finding item list function \"%s\" failed: %s",
				filename, ZBX_MODULE_FUNC_ITEM_LIST, dlerror());
			dlclose(lib);
			continue;
		}

		keys = func_list();
		for (key = keys; NULL != *key; key++)
		{
			add_user_module(*key, func_process);
		}

		register_module(lib);
	}

ret:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: unload_modules                                                   *
 *                                                                            *
 * Purpose: unload already loaded loadable modules (dynamic libraries)        *
 *          It is called on agent shutdown.                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 ******************************************************************************/
void	unload_modules()
{
	const char	*__function_name = "unload_modules";
	int	(*func_uninit)();
	void	**module;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* there is no registered modules */
	if (NULL == modules)
	{
		return;
	}

	for (module = modules; NULL != *module; module++)
	{
		*(void **) (&func_uninit) = dlsym(*module, ZBX_MODULE_FUNC_UNINIT);
		if (NULL == func_uninit)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot find uninit function: %s",
				dlerror());
			dlclose(*module);
			continue;
		}

		if(ZBX_MODULE_OK != func_uninit())
		{
			zabbix_log(LOG_LEVEL_WARNING, "Uninitialization failed.");
			dlclose(*module);
			continue;
		}

		dlclose(*module);
	}

	zbx_free(modules);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return;
}
