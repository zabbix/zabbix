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

#include <dlfcn.h>

#include "common.h"
#include "modules.h"

#include "log.h"
#include "sysinfo.h"

void **modules = NULL;

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
void	register_module(void *module)
{
	const char	*__function_name = "register_module";
/*	int	i = 0;*/

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

/*	while (NULL != modules[i])
	{
	zabbix_log(LOG_LEVEL_WARNING, "In2 %s()", __function_name);
		if(module == modules[i])
		{
			return;
		}
		i++;
	}
	zabbix_log(LOG_LEVEL_WARNING, "In2 %s()", __function_name);

	modules = zbx_realloc(modules, (i+1)*sizeof(void *));
	modules[i] = module;
	modules[i+1] = NULL;*/

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
 * Return value:                                                              *
 *                                                                            *
 ******************************************************************************/
void	load_modules(const char *path, char **modules)
{
	const char	*__function_name = "load_modules";
	char	**module;
	char	**key, **keys;
	void	*lib;
	char	filename[MAX_STRING_LEN];
	int	(*func_init)();
	int	(*func_process)();
	char	**(*func_list)();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (module = modules; NULL != *module; module++)
	{
		zbx_snprintf(filename, sizeof(filename), "%s/%s", path, *module);

		zabbix_log(LOG_LEVEL_DEBUG, "Loading module \"%s\"", filename);

		lib = dlopen(filename, RTLD_NOW);

		if (NULL == lib)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Loading module failed: %s", dlerror());
			continue;
		}

		*(void **) (&func_init) = dlsym(lib, ZBX_MODULE_FUNC_INIT);
		if (NULL == func_init)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot find init function: %s", dlerror());
			dlclose(lib);
			continue;
		}

		if(ZBX_MODULE_OK != func_init())
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Initialization failed.",
				filename);
			dlclose(lib);
			continue;
		}

		*(void **) (&func_process) = dlsym(lib, ZBX_MODULE_FUNC_PROCESS);
		if(NULL == func_process)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Cannot find process function: %s",
				filename, dlerror());
			dlclose(lib);
			continue;
		}

		*(void **) (&func_list) = dlsym(lib, ZBX_MODULE_FUNC_LIST);
		if(NULL == func_list)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Finding list function \"%s\" failed: %s",
				filename, ZBX_MODULE_FUNC_LIST, dlerror());
			dlclose(lib);
			continue;
		}

		keys = func_list();
		for (key = keys; NULL != *key; key++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Added new key \"%s\"", *key);
			add_user_module(*key, func_process);
		}

		register_module(lib);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
/*	int	(*func_uninit)();
	void	**module;*/

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

/*	for (module = modules; NULL != *module; module++)
	{
		*(void **) (&func_uninit) = dlsym(*module, ZBX_MODULE_FUNC_UNINIT);
		if (NULL == func_uninit)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot find uninit function: %s",
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
	}*/

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return;
}
