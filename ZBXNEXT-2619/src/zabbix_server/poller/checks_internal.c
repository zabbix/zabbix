/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "checks_internal.h"
#include "checks_java.h"
#include "log.h"
#include "dbcache.h"
#include "zbxself.h"
#include "valuecache.h"
#include "proxy.h"

#include "../vmware/vmware.h"

extern unsigned char	daemon_type;

/******************************************************************************
 *                                                                            *
 * Function: get_value_internal                                               *
 *                                                                            *
 * Purpose: retrieve data from Zabbix server (internally supported items)     *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	get_value_internal(DC_ITEM *item, AGENT_RESULT *result)
{
	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED, nparams;
	const char	*tmp, *tmp1;
	char		*error = NULL;

	init_result(result);
	init_request(&request);

	if (SUCCEED != parse_item_key(item->key, &request))
	{
		error = zbx_strdup(error, "Invalid item key format.");
		goto out;
	}

	if (0 != strcmp("zabbix", get_rkey(&request)))
	{
		error = zbx_strdup(error, "Unsupported item key for this item type.");
		goto out;
	}

	if (0 == (nparams = get_rparams_num(&request)))
	{
		error = zbx_strdup(error, "Invalid number of parameters.");
		goto out;
	}

	tmp = get_rparam(&request, 0);

	if (0 == strcmp(tmp, "triggers"))			/* zabbix["triggers"] */
	{
		if (0 == (daemon_type & ZBX_DAEMON_TYPE_SERVER))
			goto out;

		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, DCget_trigger_count());
	}
	else if (0 == strcmp(tmp, "items"))			/* zabbix["items"] */
	{
		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, DCget_item_count());
	}
	else if (0 == strcmp(tmp, "items_unsupported"))		/* zabbix["items_unsupported"] */
	{
		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, DCget_item_unsupported_count());
	}
	else if (0 == strcmp(tmp, "hosts"))			/* zabbix["hosts"] */
	{
		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, DCget_host_count());
	}
	else if (0 == strcmp(tmp, "history") ||			/* zabbix["history"] */
			0 == strcmp(tmp, "history_log") ||	/* zabbix["history_log"] */
			0 == strcmp(tmp, "history_str") ||	/* zabbix["history_str"] */
			0 == strcmp(tmp, "history_text") ||	/* zabbix["history_text"] */
			0 == strcmp(tmp, "history_uint"))	/* zabbix["history_uint"] */
	{
		if (0 == (daemon_type & ZBX_DAEMON_TYPE_SERVER))
			goto out;

		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, DBget_row_count(tmp));
	}
	else if (0 == strcmp(tmp, "trends") ||			/* zabbix["trends"] */
			0 == strcmp(tmp, "trends_uint"))	/* zabbix["trends_uint"] */
	{
		if (0 == (daemon_type & ZBX_DAEMON_TYPE_SERVER))
			goto out;

		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, DBget_row_count(tmp));
	}
	else if (0 == strcmp(tmp, "queue"))			/* zabbix["queue",<from>,<to>] */
	{
		unsigned int	from = 6, to = (unsigned int)-1;

		if (3 < nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		if (NULL != (tmp = get_rparam(&request, 1)) && '\0' != *tmp && FAIL == is_uint_suffix(tmp, &from))
		{
			error = zbx_strdup(error, "Invalid second parameter.");
			goto out;
		}

		if (NULL != (tmp = get_rparam(&request, 2)) && '\0' != *tmp &&  FAIL == is_uint_suffix(tmp, &to))
		{
			error = zbx_strdup(error, "Invalid third parameter.");
			goto out;
		}

		if ((unsigned int)-1 != to && from > to)
		{
			error = zbx_strdup(error, "Parameters represent an invalid interval.");
			goto out;
		}

		SET_UI64_RESULT(result, DCget_item_queue(NULL, from, to));
	}
	else if (0 == strcmp(tmp, "requiredperformance"))	/* zabbix["requiredperformance"] */
	{
		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_DBL_RESULT(result, DCget_required_performance());
	}
	else if (0 == strcmp(tmp, "uptime"))			/* zabbix["uptime"] */
	{
		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, time(NULL) - CONFIG_SERVER_STARTUP_TIME);
	}
	else if (0 == strcmp(tmp, "boottime"))			/* zabbix["boottime"] */
	{
		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, CONFIG_SERVER_STARTUP_TIME);
	}
	else if (0 == strcmp(tmp, "host"))			/* zabbix["host",*] */
	{
		if (3 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		tmp = get_rparam(&request, 2);

		if (0 == strcmp(tmp, "available"))		/* zabbix["host",<host>,"available"] */
		{
			tmp = get_rparam(&request, 1);

			if (0 == strcmp(tmp, "agent"))
				SET_UI64_RESULT(result, item->host.available);
			else if (0 == strcmp(tmp, "snmp"))
				SET_UI64_RESULT(result, item->host.snmp_available);
			else if (0 == strcmp(tmp, "ipmi"))
				SET_UI64_RESULT(result, item->host.ipmi_available);
			else if (0 == strcmp(tmp, "jmx"))
				SET_UI64_RESULT(result, item->host.jmx_available);
			else
			{
				error = zbx_strdup(error, "Invalid second parameter.");
				goto out;
			}

			result->ui64 = 2 - result->ui64;
		}
		else if (0 == strcmp(tmp, "maintenance"))	/* zabbix["host",,"maintenance"] */
		{
			/* this item always processed by server */
			if (NULL != (tmp = get_rparam(&request, 1)) && '\0' != *tmp)
			{
				error = zbx_strdup(error, "Invalid second parameter.");
				goto out;
			}

			if (HOST_MAINTENANCE_STATUS_ON == item->host.maintenance_status)
				SET_UI64_RESULT(result, item->host.maintenance_type + 1);
			else
				SET_UI64_RESULT(result, 0);
		}
		else
		{
			error = zbx_strdup(error, "Invalid third parameter.");
			goto out;
		}

	}
	else if (0 == strcmp(tmp, "proxy"))			/* zabbix["proxy",<hostname>,"lastaccess"] */
	{
		int	lastaccess;

		/* this item always processed by server */

		if (3 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		tmp = get_rparam(&request, 2);
		if ('\0' == *tmp || 0 != strcmp(tmp, "lastaccess"))
		{
			error = zbx_strdup(error, "Invalid third parameter.");
			goto out;
		}

		if (FAIL == DBget_proxy_lastaccess(get_rparam(&request, 1), &lastaccess, &error))
			goto out;

		SET_UI64_RESULT(result, lastaccess);
	}
	else if (0 == strcmp(tmp, "java"))			/* zabbix["java",...] */
	{
		int	res;

		alarm(CONFIG_TIMEOUT);
		res = get_value_java(ZBX_JAVA_GATEWAY_REQUEST_INTERNAL, item, result);
		alarm(0);

		if (SUCCEED != res)
			goto out;
	}
	else if (0 == strcmp(tmp, "process"))			/* zabbix["process",<type>,<mode>,<state>] */
	{
		unsigned char	process_type = ZBX_PROCESS_TYPE_UNKNOWN;
		int		process_forks;
		double		value;

		if (2 > nparams || nparams > 4)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		process_type = get_process_type_by_name(get_rparam(&request, 1));

		switch (process_type)
		{
			case ZBX_PROCESS_TYPE_WATCHDOG:
			case ZBX_PROCESS_TYPE_ALERTER:
			case ZBX_PROCESS_TYPE_ESCALATOR:
			case ZBX_PROCESS_TYPE_PROXYPOLLER:
			case ZBX_PROCESS_TYPE_TIMER:
				if (0 == (daemon_type & ZBX_DAEMON_TYPE_SERVER))
					process_type = ZBX_PROCESS_TYPE_UNKNOWN;
				break;
			case ZBX_PROCESS_TYPE_DATASENDER:
			case ZBX_PROCESS_TYPE_HEARTBEAT:
				if (0 == (daemon_type & ZBX_DAEMON_TYPE_PROXY))
					process_type = ZBX_PROCESS_TYPE_UNKNOWN;
				break;
		}

		if (ZBX_PROCESS_TYPE_UNKNOWN == process_type)
		{
			error = zbx_strdup(error, "Invalid second parameter.");
			goto out;
		}

		process_forks = get_process_type_forks(process_type);

		if (NULL == (tmp = get_rparam(&request, 2)))
			tmp = "";

		if (0 == strcmp(tmp, "count"))
		{
			if (4 == nparams)
			{
				error = zbx_strdup(error, "Invalid number of parameters.");
				goto out;
			}

			SET_UI64_RESULT(result, process_forks);
		}
		else
		{
			unsigned char	aggr_func, state;
			unsigned short	process_num = 0;

			if ('\0' == *tmp || 0 == strcmp(tmp, "avg"))
				aggr_func = ZBX_AGGR_FUNC_AVG;
			else if (0 == strcmp(tmp, "max"))
				aggr_func = ZBX_AGGR_FUNC_MAX;
			else if (0 == strcmp(tmp, "min"))
				aggr_func = ZBX_AGGR_FUNC_MIN;
			else if (SUCCEED == is_ushort(tmp, &process_num) && 0 < process_num)
				aggr_func = ZBX_AGGR_FUNC_ONE;
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}

			if (0 == process_forks)
			{
				error = zbx_dsprintf(error, "No \"%s\" processes started.",
						get_process_type_string(process_type));
				goto out;
			}
			else if (process_num > process_forks)
			{
				error = zbx_dsprintf(error, "Process \"%s #%d\" is not started.",
						get_process_type_string(process_type), process_num);
				goto out;
			}

			if (NULL == (tmp = get_rparam(&request, 3)) || '\0' == *tmp || 0 == strcmp(tmp, "busy"))
				state = ZBX_PROCESS_STATE_BUSY;
			else if (0 == strcmp(tmp, "idle"))
				state = ZBX_PROCESS_STATE_IDLE;
			else
			{
				error = zbx_strdup(error, "Invalid fourth parameter.");
				goto out;
			}

			get_selfmon_stats(process_type, aggr_func, process_num, state, &value);

			SET_DBL_RESULT(result, value);
		}
	}
	else if (0 == strcmp(tmp, "wcache"))			/* zabbix[wcache,<cache>,<mode>] */
	{
		if (2 > nparams || nparams > 3)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		tmp = get_rparam(&request, 1);
		tmp1 = get_rparam(&request, 2);

		if (0 == strcmp(tmp, "values"))
		{
			if (NULL == tmp1 || '\0' == *tmp1 || 0 == strcmp(tmp1, "all"))
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
			else if (0 == strcmp(tmp1, "not supported"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_NOTSUPPORTED_COUNTER));
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}
		}
		else if (0 == strcmp(tmp, "history"))
		{
			if (NULL == tmp1 || '\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_HISTORY_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_FREE));
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}
		}
		else if (0 == strcmp(tmp, "trend"))
		{
			if (0 == (daemon_type & ZBX_DAEMON_TYPE_SERVER))
			{
				error = zbx_strdup(error, "Invalid second parameter.");
				goto out;
			}

			if (NULL == tmp1 || '\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_TREND_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_FREE));
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}
		}
		else if (0 == strcmp(tmp, "text"))
		{
			if (NULL == tmp1 || '\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_TEXT_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_FREE));
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}
		}
		else
		{
			error = zbx_strdup(error, "Invalid second parameter.");
			goto out;
		}
	}
	else if (0 == strcmp(tmp, "rcache"))			/* zabbix[rcache,<cache>,<mode>] */
	{
		if (2 > nparams || nparams > 3)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		tmp = get_rparam(&request, 1);
		tmp1 = get_rparam(&request, 2);

		if (0 == strcmp(tmp, "buffer"))
		{
			if (NULL == tmp1 || '\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_FREE));
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}
		}
		else
		{
			error = zbx_strdup(error, "Invalid second parameter.");
			goto out;
		}
	}
	else if (0 == strcmp(tmp, "vcache"))
	{
		zbx_vc_stats_t	stats;

		if (0 == (daemon_type & ZBX_DAEMON_TYPE_SERVER))
			goto out;

		if (FAIL == zbx_vc_get_statistics(&stats))
		{
			error = zbx_strdup(error, "Value cache is disabled.");
			goto out;
		}

		if (2 > nparams || nparams > 3)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		tmp = get_rparam(&request, 1);
		if (NULL == (tmp1 = get_rparam(&request, 2)))
			tmp1 = "";

		if (0 == strcmp(tmp, "buffer"))
		{
			if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, stats.free_size);
			else if (0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, (double)stats.free_size / stats.total_size * 100);
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, stats.total_size);
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, stats.total_size - stats.free_size);
			else if (0 == strcmp(tmp1, "pused"))
				SET_DBL_RESULT(result, (double)(stats.total_size - stats.free_size) /
						stats.total_size * 100);
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}
		}
		else if (0 == strcmp(tmp, "cache"))
		{
			if (0 == strcmp(tmp1, "hits"))
				SET_UI64_RESULT(result, stats.hits);
			else if (0 == strcmp(tmp1, "requests"))
				SET_UI64_RESULT(result, stats.hits + stats.misses);
			else if (0 == strcmp(tmp1, "misses"))
				SET_UI64_RESULT(result, stats.misses);
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}
		}
		else
		{
			error = zbx_strdup(error, "Invalid second parameter.");
			goto out;
		}
	}
	else if (0 == strcmp(tmp, "proxy_history"))
	{
		if (0 == (daemon_type & ZBX_DAEMON_TYPE_PROXY))
			goto out;

		if (1 != nparams)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		SET_UI64_RESULT(result, proxy_get_history_count());
	}
	else if (0 == strcmp(tmp, "vmware"))
	{
		zbx_vmware_stats_t	stats;

		if (FAIL == zbx_vmware_get_statistics(&stats))
		{
			error = zbx_dsprintf(error, "No \"%s\" processes started.",
					get_process_type_string(ZBX_PROCESS_TYPE_VMWARE));
			goto out;
		}

		if (2 > nparams || nparams > 3)
		{
			error = zbx_strdup(error, "Invalid number of parameters.");
			goto out;
		}

		tmp = get_rparam(&request, 1);
		if (NULL == (tmp1 = get_rparam(&request, 2)))
			tmp1 = "";

		if (0 == strcmp(tmp, "buffer"))
		{
			if (0 == strcmp(tmp1, "free"))
			{
				SET_UI64_RESULT(result, stats.memory_total - stats.memory_used);
			}
			else if (0 == strcmp(tmp1, "pfree"))
			{
				SET_DBL_RESULT(result, (double)(stats.memory_total - stats.memory_used) /
						stats.memory_total * 100);
			}
			else if (0 == strcmp(tmp1, "total"))
			{
				SET_UI64_RESULT(result, stats.memory_total);
			}
			else if (0 == strcmp(tmp1, "used"))
			{
				SET_UI64_RESULT(result, stats.memory_used);
			}
			else if (0 == strcmp(tmp1, "pused"))
			{
				SET_DBL_RESULT(result, (double)stats.memory_used / stats.memory_total * 100);
			}
			else
			{
				error = zbx_strdup(error, "Invalid third parameter.");
				goto out;
			}
		}
		else
		{
			error = zbx_strdup(error, "Invalid second parameter.");
			goto out;
		}
	}
	else
	{
		error = zbx_strdup(error, "Invalid first parameter.");
		goto out;
	}

	ret = SUCCEED;
out:
	if (NOTSUPPORTED == ret && !ISSET_MSG(result))
	{
		if (NULL == error)
			error = zbx_strdup(error, "Internal check is not supported.");

		SET_MSG_RESULT(result, error);
	}

	free_request(&request);

	return ret;
}
