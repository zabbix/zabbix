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
#include "checks_internal.h"

/******************************************************************************
 *                                                                            *
 * Function: get_value_internal                                               *
 *                                                                            *
 * Purpose: retrieve data from ZABBIX server (internally supported intems)    *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data succesfully retrieved and stored in result    *
 *                         and result_str (as string)                         *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_value_internal(DB_ITEM *item, AGENT_RESULT *result)
{
	zbx_uint64_t	i;
	char		tmp[MAX_STRING_LEN], params[MAX_STRING_LEN],
			hostname[HOST_HOST_LEN_MAX];
	int		nparams;

	init_result(result);

	if (0 != strncmp(item->key,"zabbix[",7))
		goto not_supported;

	if (parse_command(item->key, NULL, 0, params, sizeof(params)) != 2)
		goto not_supported;
				
	if (get_param(params, 1, tmp, sizeof(tmp)) != 0)
		goto not_supported;

	nparams = num_param(params);

	if (0 == strcmp(tmp, "triggers"))		/* zabbix["triggers"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = (zbx_uint64_t)DBget_triggers_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "items"))		/* zabbix["items"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = (zbx_uint64_t)DBget_items_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "items_unsupported"))	/* zabbix["items_unsupported"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_items_unsupported_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "history"))		/* zabbix["history"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_history_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "history_str"))	/* zabbix["history_str"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_history_str_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "trends"))		/* zabbix["trends"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_trends_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "queue"))		/* zabbix["queue"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_queue_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "proxy"))		/* zabbix["proxy",<hostname>,"lastaccess"] */
	{
		if (3 != nparams)
			goto not_supported;

		if (get_param(params, 2, hostname, sizeof(hostname)) != 0)
			goto not_supported;

		if (0 != get_param(params, 3, tmp, sizeof(tmp)))
			goto not_supported;

		if (0 == strcmp(tmp, "lastaccess")) {
			if (FAIL == (i = DBget_proxy_lastaccess(hostname)))
				goto not_supported;
		} else
			goto not_supported;

		SET_UI64_RESULT(result, i);
	}
	else
		goto not_supported;

	return SUCCEED;
not_supported:
	zbx_snprintf(tmp, sizeof(tmp), "Internal check [%s] is not supported",
			item->key);
	zabbix_log(LOG_LEVEL_WARNING, "%s",
			tmp);

	SET_STR_RESULT(result, strdup(tmp));

	return NOTSUPPORTED;
}
