/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "zbxmacros.h"
#include "db.h"
#include "log.h"

#define ZBX_MACRO_ALLOC_STEP		4
#define ZBX_MACRO_UPDATE_INTERVAL	300	/* refresh macros every 5min */

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_init                                                   *
 *                                                                            *
 * Purpose: initialize macros buffer                                          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbxmacros_init(DB_MACROS **macros)
{
	size_t	sz;

	sz = sizeof(DB_MACROS);
	*macros = zbx_malloc(*macros, sz);
	memset(*macros, 0, sz);

	sz = sizeof(DB_MACRO_HOST);
	(*macros)->alloc = ZBX_MACRO_ALLOC_STEP;
	(*macros)->host = zbx_malloc((*macros)->host, sz * (*macros)->alloc);
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_init_host                                              *
 *                                                                            *
 * Purpose: initialize host macros buffer                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbxmacros_init_host(DB_MACRO_HOST *host)
{
	host->tm = 0;
	host->alloc = ZBX_MACRO_ALLOC_STEP;
	host->num = 0;
	host->macro = zbx_malloc(NULL, sizeof(DB_MACRO) * host->alloc);
	host->tmpl_alloc = ZBX_MACRO_ALLOC_STEP;
	host->tmpl_num = 0;
	host->tmplids = zbx_malloc(NULL, sizeof(zbx_uint64_t) * host->tmpl_alloc);
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_clean_host                                             *
 *                                                                            *
 * Purpose: initialize host macros buffer                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbxmacros_clean_host(DB_MACRO_HOST *host)
{
	int	i;

	for (i = 0; i < host->num; i++)
	{
		zbx_free(host->macro[i].macro);
		zbx_free(host->macro[i].value);
	}

	host->num = 0;
	host->tmpl_num = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_free                                                   *
 *                                                                            *
 * Purpose: free macros buffer                                                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbxmacros_free(DB_MACROS **macros)
{
	int	i;

	for (i = 0; i < (*macros)->num; i++)
	{
		zbxmacros_clean_host(&(*macros)->host[i]);
		zbx_free((*macros)->host[i].tmplids);
		zbx_free((*macros)->host[i].macro);
	}
	zbx_free((*macros)->host);
	zbx_free(*macros);
}

static int	zbxmacros_get_host_nearestindex(DB_MACROS *macros, zbx_uint64_t hostid)
{
	int	first_index, last_index, index;

	if (macros->num == 0)
		return 0;

	first_index = 0;
	last_index = macros->num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (macros->host[index].hostid == hostid)
			return index;
		else if (last_index == first_index)
		{
			if (macros->host[index].hostid < hostid)
				index++;
			return index;
		}
		else if (macros->host[index].hostid < hostid)
			first_index = index + 1;
		else
			last_index = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_get_host                                               *
 *                                                                            *
 * Purpose: add host to buffer                                                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static DB_MACRO_HOST	*zbxmacros_get_host(DB_MACROS *macros, zbx_uint64_t hostid)
{
	int	index;

	index = zbxmacros_get_host_nearestindex(macros, hostid);
	if (index < macros->num && macros->host[index].hostid == hostid)
		return &macros->host[index];

	if (macros->alloc == macros->num)
	{
		macros->alloc += ZBX_MACRO_ALLOC_STEP;
		macros->host = zbx_realloc(macros->host, sizeof(DB_MACRO_HOST) * macros->alloc);
	}

	if (0 != macros->num - index)
		memmove(&macros->host[index + 1], &macros->host[index], sizeof(DB_MACRO_HOST) * (macros->num - index));

	zbxmacros_init_host(&macros->host[index]);
	macros->host[index].hostid = hostid;
	macros->num++;

	return &macros->host[index];
}

static int	zbxmacros_get_macro_nearestindex(DB_MACRO_HOST *host, const char *macro)
{
	int	first_index, last_index, index;

	if (host->num == 0)
		return 0;

	first_index = 0;
	last_index = host->num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (0 == strcmp(host->macro[index].macro, macro))
			return index;
		else if (last_index == first_index)
		{
			if (0 > strcmp(host->macro[index].macro, macro))
				index++;
			return index;
		}
		else if (0 > strcmp(host->macro[index].macro, macro))
			first_index = index + 1;
		else
			last_index = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_get_macro                                              *
 *                                                                            *
 * Purpose: add macro to buffer                                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static DB_MACRO	*zbxmacros_get_macro(DB_MACRO_HOST *host, const char *macro, const char *value)
{
	int	index;

	index = zbxmacros_get_macro_nearestindex(host, macro);
	if (index < host->num && 0 == strcmp(host->macro[index].macro, macro))
		return &host->macro[index];

	if (host->alloc == host->num)
	{
		host->alloc += ZBX_MACRO_ALLOC_STEP;
		host->macro = zbx_realloc(host->macro, sizeof(DB_MACRO) * host->alloc);
	}

	if (0 != host->num - index)
		memmove(&host->macro[index + 1], &host->macro[index], sizeof(DB_MACRO) * (host->num - index));

	host->macro[index].macro = strdup(macro);
	host->macro[index].value = strdup(value);
	host->num++;

	return &host->macro[index];
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_update_global                                          *
 *                                                                            *
 * Purpose: get host macros from db and add it to the buffer                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbxmacros_update_global(DB_MACRO_HOST *host)
{
	DB_RESULT	result;
	DB_ROW		row;

	zbxmacros_clean_host(host);

	result = DBselect(
			"select macro,value"
			" from globalmacro"
			" where 1=1"
				DB_NODE,
			DBnode_local("globalmacroid"));

	while (NULL != (row = DBfetch(result)))
		zbxmacros_get_macro(host, row[0], row[1]);

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_update_host                                            *
 *                                                                            *
 * Purpose: get host macros from db and add it to the buffer                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbxmacros_update_host(DB_MACRO_HOST *host)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	templateid;

	zbxmacros_clean_host(host);

	result = DBselect(
			"select macro,value"
			" from hostmacro"
			" where hostid=" ZBX_FS_UI64,
			host->hostid);

	while (NULL != (row = DBfetch(result)))
		zbxmacros_get_macro(host, row[0], row[1]);

	DBfree_result(result);

	result = DBselect(
			"select templateid"
			" from hosts_templates"
			" where hostid=" ZBX_FS_UI64,
			host->hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(templateid, row[0]);
		uint64_array_add(&host->tmplids, &host->tmpl_alloc, &host->tmpl_num, templateid, ZBX_MACRO_ALLOC_STEP);
	}

	DBfree_result(result);
}

static int	zbxmacros_get_value_hosts(DB_MACROS *macros, zbx_uint64_t *hostids, int host_num,
		const char *macro, char **replace_to, time_t tm)
{
	const char	*__function_name = "zbxmacros_get_value_hosts";
	int		index, i, ret = FAIL;
	DB_MACRO_HOST	*host;
	zbx_uint64_t	*tmplids = NULL;
	int		tmpl_alloc = ZBX_MACRO_ALLOC_STEP, tmpl_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:'%s'", __function_name, macro);

	tmplids = zbx_malloc(tmplids, sizeof(zbx_uint64_t) * tmpl_alloc);

	for (i = 0; i < host_num; i++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() hostid:" ZBX_FS_UI64, __function_name, hostids[i]);

		host = zbxmacros_get_host(macros, hostids[i]);
		if (host->tm < tm)
		{
			zbxmacros_update_host(host);
			host->tm = tm + ZBX_MACRO_UPDATE_INTERVAL;
		}

		index = zbxmacros_get_macro_nearestindex(host, macro);
		if (index < host->num && 0 == strcmp(host->macro[index].macro, macro))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%s", host->macro[index].value);
			zabbix_log(LOG_LEVEL_DEBUG, "%s() replace_to:'%s'", __function_name, *replace_to);
			ret = SUCCEED;
			break;
		}

		uint64_array_merge(&tmplids, &tmpl_alloc, &tmpl_num, host->tmplids, host->tmpl_num, ZBX_MACRO_ALLOC_STEP);
	}

	if (FAIL == ret && 0 != tmpl_num)	/* recursion */
		ret = zbxmacros_get_value_hosts(macros, tmplids, tmpl_num, macro, replace_to, tm);

	zbx_free(tmplids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	zbxmacros_get_value_global(DB_MACROS *macros, const char *macro, char **replace_to, time_t tm)
{
	const char	*__function_name = "zbxmacros_get_value_global";
	int		index;
	DB_MACRO_HOST	*global;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:'%s'", __function_name, macro);

	global = zbxmacros_get_host(macros, 0);
	if (global->tm < tm)
	{
		zbxmacros_update_global(global);
		global->tm = tm + ZBX_MACRO_UPDATE_INTERVAL;
	}

	index = zbxmacros_get_macro_nearestindex(global, macro);
	if (index < global->num && 0 == strcmp(global->macro[index].macro, macro))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%s", global->macro[index].value);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() replace_to:'%s'", __function_name, *replace_to);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_get_value_by_hostid                                    *
 *                                                                            *
 * Purpose: get host macros from db and add it to the buffer                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbxmacros_get_value(DB_MACROS *macros, zbx_uint64_t *hostids, int host_num, const char *macro, char **replace_to)
{
	const char	*__function_name = "zbxmacros_get_value";
	time_t		tm;

	if (NULL == macros)
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:'%s'", __function_name, macro);

	tm = time(NULL);

	if (FAIL == zbxmacros_get_value_hosts(macros, hostids, host_num, macro, replace_to, tm))
		zbxmacros_get_value_global(macros, macro, replace_to, tm);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_get_value_by_triggerid                                 *
 *                                                                            *
 * Purpose: get host macros from db and add it to the buffer                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbxmacros_get_value_by_triggerid(DB_MACROS *macros, zbx_uint64_t triggerid, const char *macro, char **replace_to)
{
	const char	*__function_name = "zbxmacros_get_value_by_triggerid";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostid, *hostids = NULL;
	int		host_alloc = ZBX_MACRO_ALLOC_STEP, host_num = 0;

	if (NULL == macros)
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64, __function_name, triggerid);

	hostids = zbx_malloc(hostids, sizeof(zbx_uint64_t) * host_alloc);

	result = DBselect(
			"select distinct i.hostid"
			" from items i,functions f"
			" where f.itemid=i.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		uint64_array_add(&hostids, &host_alloc, &host_num, hostid, ZBX_MACRO_ALLOC_STEP);
	}

	DBfree_result(result);

	zbxmacros_get_value(macros, hostids, host_num, macro, replace_to);

	zbx_free(hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
