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

#define ZBX_MODULE_FUNC_INIT		"zbx_module_init"
#define ZBX_MODULE_FUNC_API_VERSION	"zbx_module_api_version"
#define ZBX_MODULE_FUNC_ITEM_LIST	"zbx_module_item_list"
#define ZBX_MODULE_FUNC_ITEM_PROCESS	"zbx_module_item_process"
#define ZBX_MODULE_FUNC_ITEM_TIMEOUT	"zbx_module_item_timeout"
#define ZBX_MODULE_FUNC_UNINIT		"zbx_module_uninit"
#define ZBX_MODULE_FUNC_HISTORY_WRITE	"zbx_module_history_write"

/* these function pointers will be initialized for server and proxy in runtime, agent does not need them */
int		(*next_history_index)(zbx_dc_history_t, int, int *);
void		(*get_history_field)(zbx_dc_history_t, int, zabbix_label_t, zabbix_basic_t *);
zbx_uint64_t	(*get_history_type)(zbx_dc_history_t, int);

typedef struct
{
	void		*lib;
	char		*name;
	zabbix_error_t	(*func_history_write)(zabbix_handle_t);
}
zbx_module_t;

static zbx_module_t	*modules = NULL;

/******************************************************************************
 *                                                                            *
 * Comments: public API function, see documentation for more details          *
 *                                                                            *
 ******************************************************************************/
unsigned char	zabbix_version(void)
{
	return ZABBIX_VERSION_MAJOR * 10 + ZABBIX_VERSION_MINOR;
}

/******************************************************************************
 *                                                                            *
 * Comments: public API function, see documentation for more details          *
 *                                                                            *
 ******************************************************************************/
const char	*zabbix_error_message(zabbix_error_t error)
{
/* refers to the last error in error_messages */
#define ZBX_MAX_ERROR	7

	/* the order of messages MUST match the order of return code defines in module.h */
	static const char * const	error_messages[ZBX_MAX_ERROR + 1] =
	{
		"call was successful",							/* 0 ZABBIX_SUCCESS */
		"vector iteration reached its end",					/* 1 ZABBIX_END_OF_VECTOR */
		"provided handle wasn't created properly or its lifetime has expired",	/* 2 ZABBIX_INVALID_HANDLE */
		"provided handle is not an object handle",				/* 3 ZABBIX_NOT_AN_OBJECT */
		"provided handle is not a vector handle",				/* 4 ZABBIX_NOT_A_VECTOR */
		"object has no member associated with provided label",			/* 5 ZABBIX_NO_SUCH_MEMBER */
		"internal error, please report to Zabbix developers",			/* 6 ZABBIX_INTERNAL_ERROR */
		"unknown error"								/* 7 ZBX_MAX_ERROR */
	};

	if (ZABBIX_SUCCESS <= error && error < ZBX_MAX_ERROR)
		return error_messages[error];

	return error_messages[ZBX_MAX_ERROR];

#undef ZBX_MAX_ERROR
}

#define ZBX_HANDLE_HISTORY			1
#define ZBX_HANDLE_HISTORY_RECORD		2
#define ZBX_HANDLE_HISTORY_RECORD_VALUELOG	3

typedef struct
{
	int	type;	/* one of ZBX_HANDLE_* defines */
	void	*data;	/* private handle data needed for implementation */
}
zbx_handle_t;

static zbx_vector_ptr_t	handle_pool;

typedef struct
{
	zbx_dc_history_t	history;
	int			history_num;
	int			index;
}
zbx_history_handle_data_t;

/******************************************************************************
 *                                                                            *
 * Function: register_module                                                  *
 *                                                                            *
 * Purpose: Add module to the list of loaded modules (dynamic libraries).     *
 *          It skips a module if it is already registered.                    *
 *                                                                            *
 * Parameters: lib                - library handle                            *
 *             name               - library name                              *
 *             func_history_write - library function which will be invoked    *
 *                                  to sync historical data with module       *
 *                                                                            *
 * Return value: SUCCEED - if module is successfully registered               *
 *               FAIL - if module is already registered                       *
 *                                                                            *
 ******************************************************************************/
static int	register_module(void *lib, char *name, zabbix_error_t (*func_history_write)(zabbix_handle_t))
{
	const char	*__function_name = "register_module";

	int		i = 0, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == modules)
	{
		zbx_vector_ptr_create(&handle_pool);
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
	modules[i].func_history_write = func_history_write;
	modules[i + 1].lib = NULL;

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
int	zbx_load_modules(const char *path, char **file_names, int timeout, int verbose)
{
	const char	*__function_name = "load_modules";

	char		**file_name, *buffer = NULL;
	void		*lib;
	char		full_name[MAX_STRING_LEN], error[MAX_STRING_LEN];
	int		(*func_init)(), (*func_version)();
	ZBX_METRIC	*(*func_list)();
	void		(*func_timeout)();
	zabbix_error_t	(*func_history_write)(zabbix_handle_t);
	int		i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (8 != sizeof(zabbix_basic_t))	/* all basic types for Zabbix <-> module data transfer are 64 bit */
		THIS_SHOULD_NEVER_HAPPEN;

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

		func_history_write = (zabbix_error_t (*)(zabbix_handle_t))dlsym(lib, ZBX_MODULE_FUNC_HISTORY_WRITE);
		if (NULL == func_history_write)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_HISTORY_WRITE "()\""
					" function in module \"%s\": %s", *file_name, dlerror());
		}

		if (SUCCEED == register_module(lib, *file_name, func_history_write))
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
 * Function: zbx_handle_alloc                                                 *
 *                                                                            *
 * Purpose: Creates a new handle of desired type in the handle pool, attaches *
 *          provided private handle data to it and returns a public handle    *
 *          identifier.                                                       *
 *                                                                            *
 ******************************************************************************/
static zabbix_handle_t	zbx_handle_alloc(int type, void *data)
{
	zabbix_handle_t	handleid;
	zbx_handle_t	*handle;

	handleid = (zabbix_handle_t)handle_pool.values_num;
	handle = zbx_malloc(NULL, sizeof(zbx_handle_t));
	handle->type = type;
	handle->data = data;
	zbx_vector_ptr_append(&handle_pool, handle);

	return handleid;
}

static void	zbx_handle_free(zbx_handle_t *handle)
{
	switch (handle->type)
	{
		case ZBX_HANDLE_HISTORY:
		case ZBX_HANDLE_HISTORY_RECORD:
		case ZBX_HANDLE_HISTORY_RECORD_VALUELOG:
			zbx_free(handle->data);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	zbx_free(handle);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_sync_history_with_modules                                    *
 *                                                                            *
 * Purpose: Invoke zbx_module_history_write() from every loaded module which  *
 *          exports such symbol                                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_history_with_modules(zbx_dc_history_t history, int history_num)
{
	zbx_module_t	*module;

	if (NULL == modules)
		return;

	for (module = modules; NULL != module->lib; module++)
	{
		if (NULL != module->func_history_write)
		{
			int				module_sync_start = time(NULL);
			zbx_history_handle_data_t	*history_handle_data;

			zabbix_log(LOG_LEVEL_DEBUG, "syncing history data with module %s...", module->name);

			history_handle_data = zbx_malloc(NULL, sizeof(zbx_history_handle_data_t));
			history_handle_data->history = history;
			history_handle_data->history_num = history_num;
			history_handle_data->index = 0;

			module->func_history_write(zbx_handle_alloc(ZBX_HANDLE_HISTORY, history_handle_data));

			/* handles which were used by module during zbx_module_history_write() call must be */
			/* destroyed because their lifetime ended and they must not interfere with handles */
			/* created in other module function calls */
			zbx_vector_ptr_clear_ext(&handle_pool, (zbx_clean_func_t)zbx_handle_free);

			zabbix_log(LOG_LEVEL_DEBUG, "syncing history data with module %s took %d seconds",
					module->name, time(NULL) - module_sync_start);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: unload_modules                                                   *
 *                                                                            *
 * Purpose: Unload already loaded loadable modules (dynamic libraries).       *
 *          It is called on process shutdown.                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_unload_modules()
{
	const char	*__function_name = "unload_modules";

	int		(*func_uninit)();
	zbx_module_t	*module;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* there are no registered modules */
	if (NULL == modules)
		goto out;

	for (module = modules; NULL != module->lib; module++)
	{
		func_uninit = (int (*)(void))dlsym(module->lib, ZBX_MODULE_FUNC_UNINIT);
		if (NULL == func_uninit)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot find \"" ZBX_MODULE_FUNC_UNINIT "()\" function: %s",
					dlerror());
			dlclose(module->lib);
			continue;
		}

		if (ZBX_MODULE_OK != func_uninit())
			zabbix_log(LOG_LEVEL_WARNING, "uninitialization failed");

		dlclose(module->lib);
		zbx_free(module->name);
	}

	zbx_vector_ptr_clear_ext(&handle_pool, (zbx_clean_func_t)zbx_handle_free);
	zbx_vector_ptr_destroy(&handle_pool);
	zbx_free(modules);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Comments: public API function, see documentation for more details          *
 *                                                                            *
 ******************************************************************************/
zabbix_error_t	zabbix_get_object_member(zabbix_handle_t object, zabbix_label_t label, void *buffer)
{
	zabbix_basic_t	res;
	zabbix_error_t	ret;

	if (object < (zabbix_handle_t)handle_pool.values_num)
	{
		zbx_handle_t	*handle = (zbx_handle_t *)handle_pool.values[object];

		if (ZBX_HANDLE_HISTORY_RECORD == handle->type)
		{
			zbx_history_handle_data_t	*record_data = (zbx_history_handle_data_t *)handle->data;

			if (ZABBIX_HISTORY_RECORD_ITEMID == label || ZABBIX_HISTORY_RECORD_CLOCK == label ||
					ZABBIX_HISTORY_RECORD_NS == label)
			{
				get_history_field(record_data->history, record_data->index, label, &res);
				ret = ZABBIX_SUCCESS;
			}
			else if (ZABBIX_HISTORY_RECORD_VALUETYPE == label)
			{
				res.as_uint64 = get_history_type(record_data->history, record_data->index);
				ret = ZABBIX_SUCCESS;
			}
			else if (ZABBIX_HISTORY_RECORD_VALUE == label)
			{
				zbx_uint64_t	type;

				type = get_history_type(record_data->history, record_data->index);

				if (ZABBIX_TYPE_UINT64 == type || ZABBIX_TYPE_DOUBLE == type ||
						ZABBIX_TYPE_STRING == type)
				{
					get_history_field(record_data->history, record_data->index, label, &res);
					ret = ZABBIX_SUCCESS;
				}
				else if (ZABBIX_TYPE_OBJECT == type)
				{
					zbx_history_handle_data_t	*log_data;

					log_data = zbx_malloc(NULL, sizeof(zbx_history_handle_data_t));
					log_data->history = record_data->history;
					log_data->history_num = record_data->history_num;
					log_data->index = record_data->index;
					res.as_object = zbx_handle_alloc(ZBX_HANDLE_HISTORY_RECORD_VALUELOG, log_data);
					ret = ZABBIX_SUCCESS;
				}
				else
					ret = ZABBIX_INTERNAL_ERROR;
			}
			else
				ret = ZABBIX_NO_SUCH_MEMBER;
		}
		else if (ZBX_HANDLE_HISTORY_RECORD_VALUELOG == handle->type)
		{
			zbx_history_handle_data_t	*log_data = (zbx_history_handle_data_t *)handle->data;

			switch (label)
			{
				case ZABBIX_HISTORY_RECORD_VALUELOG_VALUE:
				case ZABBIX_HISTORY_RECORD_VALUELOG_TIMESTAMP:
				case ZABBIX_HISTORY_RECORD_VALUELOG_SOURCE:
				case ZABBIX_HISTORY_RECORD_VALUELOG_LOGEVENTID:
				case ZABBIX_HISTORY_RECORD_VALUELOG_SEVERITY:
					get_history_field(log_data->history, log_data->index, label, &res);
					ret = ZABBIX_SUCCESS;
					break;
				default:
					ret = ZABBIX_NO_SUCH_MEMBER;
			}
		}
		else
			ret = ZABBIX_NOT_AN_OBJECT;
	}
	else
		ret = ZABBIX_INVALID_HANDLE;

	if (ZABBIX_SUCCESS == ret)
		memcpy(buffer, &res, sizeof(zabbix_basic_t));	/* buffer may be unaligned */

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: public API function, see documentation for more details          *
 *                                                                            *
 ******************************************************************************/
zabbix_error_t	zabbix_get_vector_element(zabbix_handle_t vector, void *buffer)
{
	zabbix_basic_t	res;
	zabbix_error_t	ret;

	if (vector < (zabbix_handle_t)handle_pool.values_num)
	{
		zbx_handle_t	*handle = (zbx_handle_t *)handle_pool.values[vector];

		if (ZBX_HANDLE_HISTORY == handle->type)
		{
			zbx_history_handle_data_t	*history_data = (zbx_history_handle_data_t *)handle->data;

			if (SUCCEED == next_history_index(history_data->history, history_data->history_num,
					&history_data->index))
			{
				zbx_history_handle_data_t	*history_record_data;

				history_record_data = zbx_malloc(NULL, sizeof(zbx_history_handle_data_t));
				history_record_data->history = history_data->history;
				history_record_data->history_num = history_data->history_num;
				history_record_data->index = history_data->index++;
				res.as_object = zbx_handle_alloc(ZBX_HANDLE_HISTORY_RECORD, history_record_data);
				ret = ZABBIX_SUCCESS;
			}
			else
				ret = ZABBIX_END_OF_VECTOR;
		}
		else
			ret = ZABBIX_NOT_A_VECTOR;
	}
	else
		ret = ZABBIX_INVALID_HANDLE;

	if (ZABBIX_SUCCESS == ret)
		memcpy(buffer, &res, sizeof(zabbix_basic_t));	/* buffer may be unaligned */

	return ret;
}
