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
#include "log.h"
#include "dbcache.h"

/******************************************************************************
 *                                                                            *
 * Function: get_value_internal                                               *
 *                                                                            *
 * Purpose: retrieve data from Zabbix server (internally supported items)     *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *                         and result_str (as string)                         *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_value_internal(DC_ITEM *item, AGENT_RESULT *result)
{
	zbx_uint64_t	i;
	char		params[MAX_STRING_LEN], *error = NULL;
	char		tmp[MAX_STRING_LEN], tmp1[HOST_HOST_LEN_MAX];
	int		nparams;

	init_result(result);

	if (0 != strncmp(item->key, "zabbix[", 7))
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

		i = DBget_row_count("triggers");
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "items"))		/* zabbix["items"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_row_count("items");
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "items_unsupported"))	/* zabbix["items_unsupported"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_items_unsupported_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "history") ||			/* zabbix["history"] */
			0 == strcmp(tmp, "history_log") ||	/* zabbix["history_log"] */
			0 == strcmp(tmp, "history_str") ||	/* zabbix["history_str"] */
			0 == strcmp(tmp, "history_text") ||	/* zabbix["history_text"] */
			0 == strcmp(tmp, "history_uint"))	/* zabbix["history_uint"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_row_count(tmp);
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "trends") ||			/* zabbix["trends"] */
			0 == strcmp(tmp, "trends_uint"))	/* zabbix["trends_uint"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = DBget_row_count(tmp);
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "queue"))		/* zabbix["queue"<,from><,to>] */
	{
		int	from = 6, to = (-1);

		if (nparams > 3)
			goto not_supported;

		if (nparams >= 2)
		{
			if (get_param(params, 2, tmp, sizeof(tmp)) != 0)
				goto not_supported;
			else if (*tmp != '\0' && is_uint_prefix(tmp) == FAIL)
			{
				error = zbx_dsprintf(error, "Second argument is badly formatted");
				goto not_supported;
			}
			else
				from = (*tmp != '\0' ? str2uint(tmp) : 6);
		}

		if (nparams >= 3)
		{
			if (get_param(params, 3, tmp, sizeof(tmp)) != 0)
				goto not_supported;
			else if (*tmp != '\0' && is_uint_prefix(tmp) == FAIL)
			{
				error = zbx_dsprintf(error, "Third argument is badly formatted");
				goto not_supported;
			}
			else
				to = (*tmp != '\0' ? str2uint(tmp) : -1);
		}

		if (from > to && -1 != to)
		{
			error = zbx_dsprintf(error, "Arguments represent an invalid interval");
			goto not_supported;
		}

		i = DBget_queue_count(from, to);
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "requiredperformance"))	/* zabbix["requiredperformance"] */
	{
		if (1 != nparams)
			goto not_supported;

		SET_DBL_RESULT(result, DBget_requiredperformance());
	}
	else if (0 == strcmp(tmp, "uptime"))		/* zabbix["uptime"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = time(NULL) - CONFIG_SERVER_STARTUP_TIME;
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "boottime"))		/* zabbix["boottime"] */
	{
		if (1 != nparams)
			goto not_supported;

		i = CONFIG_SERVER_STARTUP_TIME;
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "proxy"))		/* zabbix["proxy",<hostname>,"lastaccess"] */
	{
		if (3 != nparams)
			goto not_supported;

		if (get_param(params, 2, tmp1, sizeof(tmp1)) != 0)
			goto not_supported;

		if (get_param(params, 3, tmp, sizeof(tmp)) != 0)
			goto not_supported;

		if (0 == strcmp(tmp, "lastaccess"))
		{
			if (FAIL == (i = DBget_proxy_lastaccess(tmp1)))
				goto not_supported;
		}
		else
			goto not_supported;

		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "wcache"))
	{
		if (nparams > 3)
			goto not_supported;

		if (get_param(params, 2, tmp, sizeof(tmp)) != 0)
			goto not_supported;

		if (get_param(params, 3, tmp1, sizeof(tmp1)) != 0)
			*tmp1 = '\0';

		if (0 == strcmp(tmp, "values"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "all"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_COUNTER));
			else if (0 == strcmp(tmp1, "float"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_FLOAT_COUNTER));
			else if (0 == strcmp(tmp1, "uint"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_UINT_COUNTER));
			else if (0 == strcmp(tmp1, "str"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_STR_COUNTER));
			else if (0 == strcmp(tmp1, "log"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_LOG_COUNTER));
			else if (0 == strcmp(tmp1, "text"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_TEXT_COUNTER));
			else
				goto not_supported;
		}
		else if (0 == strcmp(tmp, "history"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_HISTORY_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_FREE));
			else
				goto not_supported;
		}
		else if (0 == strcmp(tmp, "trend"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_TREND_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_FREE));
			else
				goto not_supported;
		}
		else if (0 == strcmp(tmp, "text"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_TEXT_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_FREE));
			else
				goto not_supported;
		}
		else
			goto not_supported;
	}
	else if (0 == strcmp(tmp, "rcache"))
	{
		if (nparams > 3)
			goto not_supported;

		if (get_param(params, 2, tmp, sizeof(tmp)) != 0)
			goto not_supported;

		if (get_param(params, 3, tmp1, sizeof(tmp1)) != 0)
			*tmp1 = '\0';

		if (0 == strcmp(tmp, "buffer"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_FREE));
			else
				goto not_supported;
		}
		else
			goto not_supported;
	}
	else
		goto not_supported;

	return SUCCEED;
not_supported:
	if (NULL == error)
		error = zbx_dsprintf(error, "Internal check is not supported");

	SET_MSG_RESULT(result, error);

	return NOTSUPPORTED;
}
