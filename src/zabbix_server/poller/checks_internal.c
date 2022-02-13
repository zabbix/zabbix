/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "checks_internal.h"

#include "checks_java.h"
#include "zbxself.h"
#include "preproc.h"
#include "zbxtrends.h"
#include "../vmware/vmware.h"
#include "../../libs/zbxserver/zabbix_stats.h"
#include "../../libs/zbxsysinfo/common/zabbix_stats.h"

extern unsigned char	program_type;

static int	compare_interfaces(const void *p1, const void *p2)
{
	const DC_INTERFACE2	*i1 = (DC_INTERFACE2 *)p1, *i2 = (DC_INTERFACE2 *)p2;

	if (i1->type > i2->type)		/* 1st criterion: 'type' in ascending order */
		return 1;

	if (i1->type < i2->type)
		return -1;

	if (i1->main > i2->main)		/* 2nd criterion: 'main' in descending order */
		return -1;

	if (i1->main < i2->main)
		return 1;

	if (i1->interfaceid > i2->interfaceid)	/* 3rd criterion: 'interfaceid' in ascending order */
		return 1;

	if (i1->interfaceid < i2->interfaceid)
		return -1;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get data of all network interfaces for a host from configuration  *
 *          cache and pack into JSON for LLD                                  *
 *                                                                            *
 * Parameter: hostid - [IN] the host identifier                               *
 *            j      - [OUT] JSON with interface data                         *
 *            error  - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - interface data in JSON                             *
 *               FAIL    - host not found, 'error' message allocated          *
 *                                                                            *
 * Comments: if host is found but has no interfaces (should not happen) an    *
 *           empty JSON {"data":[]} is returned                               *
 *                                                                            *
 ******************************************************************************/
static int	zbx_host_interfaces_discovery(zbx_uint64_t hostid, struct zbx_json *j, char **error)
{
	DC_INTERFACE2	*interfaces = NULL;
	int		n = 0;			/* number of interfaces */
	int		i;

	/* get interface data from configuration cache */

	if (SUCCEED != zbx_dc_get_host_interfaces(hostid, &interfaces, &n))
	{
		*error = zbx_strdup(*error, "host not found in configuration cache");

		return FAIL;
	}

	/* sort results in a predictable order */

	if (1 < n)
		qsort(interfaces, (size_t)n, sizeof(DC_INTERFACE2), compare_interfaces);

	/* repair 'addr' pointers broken by sorting */

	for (i = 0; i < n; i++)
		interfaces[i].addr = (1 == interfaces[i].useip ? interfaces[i].ip_orig : interfaces[i].dns_orig);

	/* pack results into JSON */

	zbx_json_initarray(j, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < n; i++)
	{
		const char	*p;
		char		buf[16];

		zbx_json_addobject(j, NULL);
		zbx_json_addstring(j, "{#IF.CONN}", interfaces[i].addr, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, "{#IF.IP}", interfaces[i].ip_orig, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, "{#IF.DNS}", interfaces[i].dns_orig, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, "{#IF.PORT}", interfaces[i].port_orig, ZBX_JSON_TYPE_STRING);

		switch (interfaces[i].type)
		{
			case INTERFACE_TYPE_AGENT:
				p = "AGENT";
				break;
			case INTERFACE_TYPE_SNMP:
				p = "SNMP";
				break;
			case INTERFACE_TYPE_IPMI:
				p = "IPMI";
				break;
			case INTERFACE_TYPE_JMX:
				p = "JMX";
				break;
			case INTERFACE_TYPE_UNKNOWN:
			default:
				p = "UNKNOWN";
		}
		zbx_json_addstring(j, "{#IF.TYPE}", p, ZBX_JSON_TYPE_STRING);

		zbx_snprintf(buf, sizeof(buf), "%hhu", interfaces[i].main);
		zbx_json_addstring(j, "{#IF.DEFAULT}", buf, ZBX_JSON_TYPE_INT);

		if (INTERFACE_TYPE_SNMP == interfaces[i].type)
		{
			zbx_snprintf(buf, sizeof(buf), "%hhu", interfaces[i].bulk);
			zbx_json_addstring(j, "{#IF.SNMP.BULK}", buf, ZBX_JSON_TYPE_INT);

			switch (interfaces[i].snmp_version)
			{
				case ZBX_IF_SNMP_VERSION_1:
					p = "SNMPv1";
					break;
				case ZBX_IF_SNMP_VERSION_2:
					p = "SNMPv2c";
					break;
				case ZBX_IF_SNMP_VERSION_3:
					p = "SNMPv3";
					break;
				default:
					p = "UNKNOWN";
			}

			zbx_json_addstring(j, "{#IF.SNMP.VERSION}", p, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_close(j);
	}

	zbx_json_close(j);

	zbx_free(interfaces);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve data from Zabbix server (internally supported items)     *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 ******************************************************************************/
int	get_value_internal(const DC_ITEM *item, AGENT_RESULT *result)
{
	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED, nparams;
	const char	*tmp, *tmp1;

	init_request(&request);

	if (SUCCEED != parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key format."));
		goto out;
	}

	if (0 != strcmp("zabbix", get_rkey(&request)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key for this item type."));
		goto out;
	}

	/* NULL check to silence analyzer warning */
	if (0 == (nparams = get_rparams_num(&request)) || NULL == (tmp = get_rparam(&request, 0)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	if (FAIL != (ret = zbx_get_value_internal_ext(tmp, &request, result)))
		goto out;

	ret = NOTSUPPORTED;

	if (0 == strcmp(tmp, "items"))			/* zabbix["items"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, DCget_item_count(0));
	}
	else if (0 == strcmp(tmp, "version"))			/* zabbix["version"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_STR_RESULT(result, zbx_strdup(NULL, ZABBIX_VERSION));
	}
	else if (0 == strcmp(tmp, "items_unsupported"))		/* zabbix["items_unsupported"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, DCget_item_unsupported_count(0));
	}
	else if (0 == strcmp(tmp, "hosts"))			/* zabbix["hosts"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, DCget_host_count());
	}
	else if (0 == strcmp(tmp, "queue"))			/* zabbix["queue",<from>,<to>] */
	{
		int	from = ZBX_QUEUE_FROM_DEFAULT, to = ZBX_QUEUE_TO_INFINITY;

		if (3 < nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		if (NULL != (tmp = get_rparam(&request, 1)) && '\0' != *tmp &&
				FAIL == is_time_suffix(tmp, &from, ZBX_LENGTH_UNLIMITED))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}

		if (NULL != (tmp = get_rparam(&request, 2)) && '\0' != *tmp &&
				FAIL == is_time_suffix(tmp, &to, ZBX_LENGTH_UNLIMITED))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}

		if (ZBX_QUEUE_TO_INFINITY != to && from > to)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Parameters represent an invalid interval."));
			goto out;
		}

		SET_UI64_RESULT(result, DCget_item_queue(NULL, from, to));
	}
	else if (0 == strcmp(tmp, "requiredperformance"))	/* zabbix["requiredperformance"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_DBL_RESULT(result, DCget_required_performance());
	}
	else if (0 == strcmp(tmp, "uptime"))			/* zabbix["uptime"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, time(NULL) - CONFIG_SERVER_STARTUP_TIME);
	}
	else if (0 == strcmp(tmp, "boottime"))			/* zabbix["boottime"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, CONFIG_SERVER_STARTUP_TIME);
	}
	else if (0 == strcmp(tmp, "host"))			/* zabbix["host",*] */
	{
		if (3 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		tmp = get_rparam(&request, 2);

		if (0 == strcmp(tmp, "available"))		/* zabbix["host",<type>,"available"] */
		{
			zbx_agent_availability_t	agents[ZBX_AGENT_MAX];
			int				i;

			zbx_get_host_interfaces_availability(item->host.hostid, agents);

			for (i = 0; i < ZBX_AGENT_MAX; i++)
				zbx_free(agents[i].error);

			tmp = get_rparam(&request, 1);

			if (0 == strcmp(tmp, "agent"))
				SET_UI64_RESULT(result, agents[ZBX_AGENT_ZABBIX].available);
			else if (0 == strcmp(tmp, "snmp"))
				SET_UI64_RESULT(result, agents[ZBX_AGENT_SNMP].available);
			else if (0 == strcmp(tmp, "ipmi"))
				SET_UI64_RESULT(result, agents[ZBX_AGENT_IPMI].available);
			else if (0 == strcmp(tmp, "jmx"))
				SET_UI64_RESULT(result, agents[ZBX_AGENT_JMX].available);
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			result->ui64 = 2 - result->ui64;
		}
		else if (0 == strcmp(tmp, "maintenance"))	/* zabbix["host",,"maintenance"] */
		{
			/* this item is always processed by server */
			if (NULL != (tmp = get_rparam(&request, 1)) && '\0' != *tmp)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			if (HOST_MAINTENANCE_STATUS_ON == item->host.maintenance_status)
				SET_UI64_RESULT(result, item->host.maintenance_type + 1);
			else
				SET_UI64_RESULT(result, 0);
		}
		else if (0 == strcmp(tmp, "items"))	/* zabbix["host",,"items"] */
		{
			/* this item is always processed by server */
			if (NULL != (tmp = get_rparam(&request, 1)) && '\0' != *tmp)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			SET_UI64_RESULT(result, DCget_item_count(item->host.hostid));
		}
		else if (0 == strcmp(tmp, "items_unsupported"))	/* zabbix["host",,"items_unsupported"] */
		{
			/* this item is always processed by server */
			if (NULL != (tmp = get_rparam(&request, 1)) && '\0' != *tmp)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			SET_UI64_RESULT(result, DCget_item_unsupported_count(item->host.hostid));
		}
		else if (0 == strcmp(tmp, "interfaces"))	/* zabbix["host","discovery","interfaces"] */
		{
			struct zbx_json	j;
			char		*error = NULL;

			/* this item is always processed by server */
			if (NULL == (tmp = get_rparam(&request, 1)) || 0 != strcmp(tmp, "discovery"))
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			if (SUCCEED != zbx_host_interfaces_discovery(item->host.hostid, &j, &error))
			{
				SET_MSG_RESULT(result, error);
				goto out;
			}

			SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

			zbx_json_free(&j);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}
	}
	else if (0 == strcmp(tmp, "java"))			/* zabbix["java",...] */
	{
		int	res;

		zbx_alarm_on(CONFIG_TIMEOUT);
		res = get_value_java(ZBX_JAVA_GATEWAY_REQUEST_INTERNAL, item, result);
		zbx_alarm_off();

		if (SUCCEED != res)
		{
			tmp1 = get_rparam(&request, 2);
			/* the default error code "NOTSUPPORTED" renders nodata() trigger function nonfunctional */
			if (NULL != tmp1 && 0 == strcmp(tmp1, "ping"))
				ret = GATEWAY_ERROR;
			goto out;
		}
	}
	else if (0 == strcmp(tmp, "process"))			/* zabbix["process",<type>,<mode>,<state>] */
	{
		unsigned char	process_type = ZBX_PROCESS_TYPE_UNKNOWN;
		int		process_forks;
		double		value;

		if (2 > nparams || nparams > 4)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		process_type = get_process_type_by_name(get_rparam(&request, 1));

		switch (process_type)
		{
			case ZBX_PROCESS_TYPE_ALERTMANAGER:
			case ZBX_PROCESS_TYPE_ALERTER:
			case ZBX_PROCESS_TYPE_ESCALATOR:
			case ZBX_PROCESS_TYPE_PROXYPOLLER:
			case ZBX_PROCESS_TYPE_TIMER:
				if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
					process_type = ZBX_PROCESS_TYPE_UNKNOWN;
				break;
			case ZBX_PROCESS_TYPE_DATASENDER:
			case ZBX_PROCESS_TYPE_HEARTBEAT:
				if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
					process_type = ZBX_PROCESS_TYPE_UNKNOWN;
				break;
		}

		if (ZBX_PROCESS_TYPE_UNKNOWN == process_type)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}

		process_forks = get_process_type_forks(process_type);

		if (NULL == (tmp = get_rparam(&request, 2)))
			tmp = "";

		if (0 == strcmp(tmp, "count"))
		{
			if (4 == nparams)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
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
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}

			if (0 == process_forks)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "No \"%s\" processes started.",
						get_process_type_string(process_type)));
				goto out;
			}
			else if (process_num > process_forks)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Process \"%s #%d\" is not started.",
						get_process_type_string(process_type), process_num));
				goto out;
			}

			if (NULL == (tmp = get_rparam(&request, 3)) || '\0' == *tmp || 0 == strcmp(tmp, "busy"))
				state = ZBX_PROCESS_STATE_BUSY;
			else if (0 == strcmp(tmp, "idle"))
				state = ZBX_PROCESS_STATE_IDLE;
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
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
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
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
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
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
			else if (0 == strcmp(tmp1, "pused"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_HISTORY_PUSED));
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else if (0 == strcmp(tmp, "trend"))
		{
			if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
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
			else if (0 == strcmp(tmp1, "pused"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_TREND_PUSED));
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else if (0 == strcmp(tmp, "index"))
		{
			if (NULL == tmp1 || '\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_HISTORY_INDEX_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_INDEX_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_INDEX_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_INDEX_FREE));
			else if (0 == strcmp(tmp1, "pused"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_HISTORY_INDEX_PUSED));
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}
	}
	else if (0 == strcmp(tmp, "rcache"))			/* zabbix[rcache,<cache>,<mode>] */
	{
		if (2 > nparams || nparams > 3)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
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
			else if (0 == strcmp(tmp1, "pused"))
				SET_DBL_RESULT(result, *(double *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_PUSED));
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}
	}
	else if (0 == strcmp(tmp, "vmware"))
	{
		zbx_vmware_stats_t	stats;

		if (FAIL == zbx_vmware_get_statistics(&stats))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "No \"%s\" processes started.",
					get_process_type_string(ZBX_PROCESS_TYPE_VMWARE)));
			goto out;
		}

		if (2 > nparams || nparams > 3)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
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
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}
	}
	else if (0 == strcmp(tmp, "stats"))			/* zabbix[stats,...] */
	{
		const char	*ip_str, *port_str, *ip;
		unsigned short	port_number;
		struct zbx_json	json;

		if (6 < nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		if (NULL == (ip_str = get_rparam(&request, 1)) || '\0' == *ip_str)
			ip = "127.0.0.1";
		else
			ip = ip_str;

		if (NULL == (port_str = get_rparam(&request, 2)) || '\0' == *port_str)
		{
			port_number = ZBX_DEFAULT_SERVER_PORT;
		}
		else if (SUCCEED != is_ushort(port_str, &port_number))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}

		if (3 >= nparams)
		{
			if ((NULL == ip_str || '\0' == *ip_str) && (NULL == port_str || '\0' == *port_str))
			{
				zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

				/* Adding "data" object to JSON structure to make identical JSONPath expressions */
				/* work for both data received from internal and external source. */
				zbx_json_addobject(&json, ZBX_PROTO_TAG_DATA);

				zbx_get_zabbix_stats(&json);

				zbx_json_close(&json);

				set_result_type(result, ITEM_VALUE_TYPE_TEXT, json.buffer);

				zbx_json_free(&json);
			}
			else if (SUCCEED != zbx_get_remote_zabbix_stats(ip, port_number, result))
				goto out;
		}
		else
		{
			tmp1 = get_rparam(&request, 3);

			if (0 == strcmp(tmp1, ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE))
			{
				tmp = get_rparam(&request, 4);		/* from */
				tmp1 = get_rparam(&request, 5);		/* to */

				if ((NULL == ip_str || '\0' == *ip_str) && (NULL == port_str || '\0' == *port_str))
				{
					int	from = ZBX_QUEUE_FROM_DEFAULT, to = ZBX_QUEUE_TO_INFINITY;

					if (NULL != tmp && '\0' != *tmp &&
							FAIL == is_time_suffix(tmp, &from, ZBX_LENGTH_UNLIMITED))
					{
						SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
						goto out;
					}

					if (NULL != tmp1 && '\0' != *tmp1 &&
							FAIL == is_time_suffix(tmp1, &to, ZBX_LENGTH_UNLIMITED))
					{
						SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid sixth parameter."));
						goto out;
					}

					if (ZBX_QUEUE_TO_INFINITY != to && from > to)
					{
						SET_MSG_RESULT(result, zbx_strdup(NULL, "Parameters represent an"
								" invalid interval."));
						goto out;
					}

					zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

					zbx_json_adduint64(&json, ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE,
							DCget_item_queue(NULL, from, to));

					set_result_type(result, ITEM_VALUE_TYPE_TEXT, json.buffer);

					zbx_json_free(&json);
				}
				else if (SUCCEED != zbx_get_remote_zabbix_stats_queue(ip, port_number, tmp, tmp1,
						result))
				{
					goto out;
				}
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid forth parameter."));
				goto out;
			}
		}
	}
	else if (0 == strcmp(tmp, "preprocessing_queue"))
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, zbx_preprocessor_get_queue_size());
	}
	else if (0 == strcmp(tmp, "tcache"))			/* zabbix[tcache,cache,<parameter>] */
	{
		char		*error = NULL;
		zbx_tfc_stats_t	stats;

		if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
			goto out;
		}

		if (2 > nparams || 3 < nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		tmp1 = get_rparam(&request, 1);

		if (0 != strcmp(tmp1, "cache"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}

		tmp = get_rparam(&request, 2);

		if (FAIL == zbx_tfc_get_stats(&stats, &error))
		{
			SET_MSG_RESULT(result, error);
			goto out;
		}

		if (NULL == tmp || 0 == strcmp(tmp, "all"))
		{
			SET_UI64_RESULT(result, stats.hits + stats.misses);
		}
		else if (0 == strcmp(tmp, "hits"))
		{
			SET_UI64_RESULT(result, stats.hits);
		}
		else if (0 == strcmp(tmp, "misses"))
		{
			SET_UI64_RESULT(result, stats.misses);
		}
		else if (0 == strcmp(tmp, "items"))
		{
			SET_UI64_RESULT(result, stats.items_num);
		}
		else if (0 == strcmp(tmp, "requests"))
		{
			SET_UI64_RESULT(result, stats.requests_num);
		}
		else if (0 == strcmp(tmp, "pmisses"))
		{
			zbx_uint64_t	total = stats.hits + stats.misses;

			SET_DBL_RESULT(result, (0 == total ? 0 : (double)stats.misses / total * 100));
		}
		else if (0 == strcmp(tmp, "phits"))
		{
			zbx_uint64_t	total = stats.hits + stats.misses;

			SET_DBL_RESULT(result, (0 == total ? 0 : (double)stats.hits / total * 100));
		}
		else if (0 == strcmp(tmp, "pitems"))
		{
			zbx_uint64_t	total = stats.items_num + stats.requests_num;

			SET_DBL_RESULT(result, (0 == total ? 0 : (double)stats.items_num / total * 100));
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto out;
	}

	ret = SUCCEED;
out:
	if (NOTSUPPORTED == ret && !ISSET_MSG(result))
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Internal check is not supported."));

	free_request(&request);

	return ret;
}
