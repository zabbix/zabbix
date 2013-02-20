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
	int		i = 0;

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
 *             timeout - timeout in seconds for processing of items by module *
 *                                                                            *
 * Return value: 0 - success                                                  *
 *               -1 - loading of modules failed                               *
 *                                                                            *
 ******************************************************************************/
int	load_modules(const char *path, char **modules, int timeout)
{
	const char	*__function_name = "load_modules";
	char		**module;
	ZBX_METRIC	*metrics;
	void		*lib;
	char		filename[MAX_STRING_LEN];
	int		(*func_init)(), (*func_version)();
	ZBX_METRIC	*(*func_list)();
	char		**(*func_timeout)();
	int		i, ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (module = modules; NULL != *module; module++)
	{
		zbx_snprintf(filename, sizeof(filename), "%s/%s", path, *module);

		zabbix_log(LOG_LEVEL_DEBUG, "Loading module \"%s\"", filename);

		lib = dlopen(filename, RTLD_NOW);

		if (NULL == lib)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Loading module failed: %s", dlerror());
			ret = FAIL;
			goto ret;
		}

		*(void **)(&func_version) = dlsym(lib, ZBX_MODULE_FUNC_API_VERSION);
		if (NULL == func_version)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Cannot find version function: %s",
				filename, dlerror());
			dlclose(lib);
			ret = FAIL;
			goto ret;
		}

		if (ZBX_MODULE_API_VERSION_ONE != func_version())
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Unsupported module version.",
				filename);
			dlclose(lib);
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

		/* the function is optional, zabbix will load the module ieven if it is missing */
		*(void **)(&func_timeout) = dlsym(lib, ZBX_MODULE_FUNC_ITEM_TIMEOUT);
		if (NULL != func_timeout)
		{
			func_timeout(timeout);
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Module \"%s\". Cannot find item timeout function: %s",
				filename, dlerror());
		}

		*(void **)(&func_list) = dlsym(lib, ZBX_MODULE_FUNC_ITEM_LIST);
		if (NULL == func_list)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Module \"%s\". Finding item list function \"%s\" failed: %s",
				filename, ZBX_MODULE_FUNC_ITEM_LIST, dlerror());
			dlclose(lib);
			continue;
		}

		metrics = func_list();
		for (i = 0; NULL != metrics[i].key; i++)
		{
			/* Accept only CF_HAVEPARAMS flag from module items */
			metrics[i].flags &= CF_HAVEPARAMS;
			/* The flag means that the items comes from a loadable module */
			metrics[i].flags |= CF_MODULE;
			add_metric(&metrics[i]);
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
	int		(*func_uninit)();
	void		**module;

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

		if (ZBX_MODULE_OK != func_uninit())
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
