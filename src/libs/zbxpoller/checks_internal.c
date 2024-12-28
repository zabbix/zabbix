/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "checks_internal.h"
#include "checks_java.h"

#include "zbxpoller.h"

#include "zbxalgo.h"
#include "zbxcachehistory.h"
#include "zbxjson.h"
#include "zbxtime.h"
#include "zbxstats.h"
#include "zbxself.h"
#include "zbxdiscovery.h"
#include "zbxtrends.h"
#include "zbxvmware.h"
#include "zbxavailability.h"
#include "zbxnum.h"
#include "zbxsysinfo.h"
#include "zbxpreproc.h"
#include "zbxinterface.h"
#include "zbxtimekeeper.h"

static int	compare_interfaces(const void *p1, const void *p2)
{
	const zbx_dc_interface2_t	*i1 = (const zbx_dc_interface2_t *)p1, *i2 = (const zbx_dc_interface2_t *)p2;

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
 * Purpose: Gets data of all network interfaces for a host from configuration *
 *          cache and packs it into JSON for LLD.                             *
 *                                                                            *
 * Parameter: hostid - [IN]                                                   *
 *            j      - [OUT] JSON with interface data                         *
 *            error  - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - interface data in JSON                             *
 *               FAIL    - host not found, 'error' message allocated          *
 *                                                                            *
 * Comments: If host is found but has no interfaces (should not happen) an    *
 *           empty JSON {"data":[]} is returned.                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_host_interfaces_discovery(zbx_uint64_t hostid, struct zbx_json *j, char **error)
{
	zbx_dc_interface2_t	*interfaces = NULL;
	int			n = 0;			/* number of interfaces */

	/* get interface data from configuration cache */

	if (SUCCEED != zbx_dc_get_host_interfaces(hostid, &interfaces, &n))
	{
		*error = zbx_strdup(*error, "host not found in configuration cache");

		return FAIL;
	}

	/* sort results in a predictable order */

	if (1 < n)
		qsort(interfaces, (size_t)n, sizeof(zbx_dc_interface2_t), compare_interfaces);

	/* repair 'addr' pointers broken by sorting */

	for (int i = 0; i < n; i++)
		interfaces[i].addr = (1 == interfaces[i].useip ? interfaces[i].ip_orig : interfaces[i].dns_orig);

	/* pack results into JSON */

	zbx_json_initarray(j, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < n; i++)
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

static double	get_selfmon_stat(double busy, unsigned char state)
{
	return (ZBX_PROCESS_STATE_BUSY == state ? busy : 100.0 - busy);
}

typedef int (*zbx_selfmon_stats_threads_cb_t)(zbx_vector_dbl_t*, int*, char**);

static int	get_selfmon_stats_threads(unsigned char aggr_func, zbx_selfmon_stats_threads_cb_t stats_func,
		int proc_num, unsigned char state, double *value, char **error)
{
	zbx_vector_dbl_t	usage;
	int			ret, count;

	zbx_vector_dbl_create(&usage);

	if (SUCCEED != (ret = stats_func(&usage, &count, error)))
		goto out;

	if (0 == usage.values_num)
	{
		*value = 0;
		goto out;
	}

	if (ZBX_SELFMON_AGGR_FUNC_ONE == aggr_func)
	{
		*value = get_selfmon_stat(usage.values[proc_num - 1], state);
	}
	else
	{
		double	min, max, total;

		min = max = total = usage.values[0];

		for (int i = 1; i < usage.values_num; i++)
		{
			if (usage.values[i] < min)
				min = usage.values[i];

			if (usage.values[i] > max)
				max = usage.values[i];

			total += usage.values[i];
		}

		switch (aggr_func)
		{
			case ZBX_SELFMON_AGGR_FUNC_AVG:
				*value = get_selfmon_stat(total / usage.values_num, state);
				break;
			case ZBX_SELFMON_AGGR_FUNC_MIN:
				*value = get_selfmon_stat(min, state);
				break;
			case ZBX_SELFMON_AGGR_FUNC_MAX:
				*value = get_selfmon_stat(max, state);
				break;
		}
	}
out:
	zbx_vector_dbl_destroy(&usage);

	return ret;
}

/**********************************************************************************
 *                                                                                *
 * Purpose: retrieves data from Zabbix server (internally supported items)        *
 *                                                                                *
 * Parameters: item                      - [IN] item we are interested in         *
 *             result                    - [OUT] value of requested item          *
 *             config_comms              - [IN] Zabbix server/proxy configuration *
 *                                              for communication                 *
 *             config_startup_time       - [IN] program startup time              *
 *             config_java_gateway       - [IN]                                   *
 *             config_java_gateway_port  - [IN]                                   *
 *             get_config_forks          - [IN]                                   *
 *             get_value_internal_ext_cb - [IN]                                   *
 *             program_type              - [IN]                                   *
 *                                                                                *
 * Return value: SUCCEED - data successfully retrieved and stored in result       *
 *               NOTSUPPORTED - requested item is not supported                   *
 *                                                                                *
 **********************************************************************************/
int	get_value_internal(const zbx_dc_item_t *item, AGENT_RESULT *result, const zbx_config_comms_args_t *config_comms,
		int config_startup_time, const char *config_java_gateway, int config_java_gateway_port,
		zbx_get_config_forks_f get_config_forks, zbx_get_value_internal_ext_f get_value_internal_ext_cb,
		unsigned char program_type)
{
	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED, nparams;
	const char	*tmp, *tmp1;

	zbx_init_agent_request(&request);

	if (SUCCEED != zbx_parse_item_key(item->key, &request))
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

	if (FAIL != (ret = get_value_internal_ext_cb(item, tmp, &request, result)))
		goto out;

	ret = NOTSUPPORTED;

	if (0 == strcmp(tmp, "items"))			/* zabbix["items"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, zbx_dc_get_item_count(0));
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

		SET_UI64_RESULT(result, zbx_dc_get_item_unsupported_count(0));
	}
	else if (0 == strcmp(tmp, "hosts"))			/* zabbix["hosts"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, zbx_dc_get_host_count());
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
				FAIL == zbx_is_time_suffix(tmp, &from, ZBX_LENGTH_UNLIMITED))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}

		if (NULL != (tmp = get_rparam(&request, 2)) && '\0' != *tmp &&
				FAIL == zbx_is_time_suffix(tmp, &to, ZBX_LENGTH_UNLIMITED))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}

		if (ZBX_QUEUE_TO_INFINITY != to && from > to)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Parameters represent an invalid interval."));
			goto out;
		}

		SET_UI64_RESULT(result, zbx_dc_get_item_queue(NULL, from, to));
	}
	else if (0 == strcmp(tmp, "requiredperformance"))	/* zabbix["requiredperformance"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_DBL_RESULT(result, zbx_dc_get_required_performance());
	}
	else if (0 == strcmp(tmp, "uptime"))			/* zabbix["uptime"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, time(NULL) - config_startup_time);
	}
	else if (0 == strcmp(tmp, "boottime"))			/* zabbix["boottime"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, config_startup_time);
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

			zbx_get_host_interfaces_availability(item->host.hostid, agents);

			for (int i = 0; i < ZBX_AGENT_MAX; i++)
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
			else if (0 == strcmp(tmp, "active_agent"))
			{
				SET_UI64_RESULT(result, zbx_get_active_agent_availability(item->host.hostid));
				ret = SUCCEED;
				goto out;
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			result->ui64 = 2 - result->ui64;
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
		if (SUCCEED != get_value_java(ZBX_JAVA_GATEWAY_REQUEST_INTERNAL, item, result,
				config_comms->config_timeout, config_comms->config_source_ip,
				config_java_gateway, config_java_gateway_port))
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

		process_type = (unsigned char)get_process_type_by_name(get_rparam(&request, 1));

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
				if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
					process_type = ZBX_PROCESS_TYPE_UNKNOWN;
				break;
		}

		if (ZBX_PROCESS_TYPE_UNKNOWN == process_type)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}

		process_forks = ZBX_PROCESS_TYPE_COUNT > process_type ? get_config_forks(process_type) : 0;

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
			char		*error = NULL;

			if ('\0' == *tmp || 0 == strcmp(tmp, "avg"))
				aggr_func = ZBX_SELFMON_AGGR_FUNC_AVG;
			else if (0 == strcmp(tmp, "max"))
				aggr_func = ZBX_SELFMON_AGGR_FUNC_MAX;
			else if (0 == strcmp(tmp, "min"))
				aggr_func = ZBX_SELFMON_AGGR_FUNC_MIN;
			else if (SUCCEED == zbx_is_ushort(tmp, &process_num) && 0 < process_num)
				aggr_func = ZBX_SELFMON_AGGR_FUNC_ONE;
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

			if (ZBX_PROCESS_TYPE_PREPROCESSOR == process_type ||
					ZBX_PROCESS_TYPE_DISCOVERER == process_type)
			{
				zbx_selfmon_stats_threads_cb_t	stats_func;

				stats_func = ZBX_PROCESS_TYPE_PREPROCESSOR == process_type ?
						zbx_preprocessor_get_usage_stats : zbx_discovery_get_usage_stats;

				if (SUCCEED != get_selfmon_stats_threads(aggr_func, stats_func, process_num, state,
						&value, &error))
				{
					SET_MSG_RESULT(result, error);
					goto out;
				}
			}
			else
				zbx_get_selfmon_stats(process_type, aggr_func, process_num, state, &value);

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
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)zbx_dc_get_stats(ZBX_STATS_HISTORY_COUNTER));
			}
			else if (0 == strcmp(tmp1, "float"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_FLOAT_COUNTER));
			}
			else if (0 == strcmp(tmp1, "uint"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_UINT_COUNTER));
			}
			else if (0 == strcmp(tmp1, "str"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_STR_COUNTER));
			}
			else if (0 == strcmp(tmp1, "log"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_LOG_COUNTER));
			}
			else if (0 == strcmp(tmp1, "text"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_TEXT_COUNTER));
			}
			else if (0 == strcmp(tmp1, "bin"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_BIN_COUNTER));
			}
			else if (0 == strcmp(tmp1, "not supported"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_NOTSUPPORTED_COUNTER));
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else if (0 == strcmp(tmp, "history"))
		{
			if (NULL == tmp1 || '\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)zbx_dc_get_stats(ZBX_STATS_HISTORY_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)zbx_dc_get_stats(ZBX_STATS_HISTORY_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)zbx_dc_get_stats(ZBX_STATS_HISTORY_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)zbx_dc_get_stats(ZBX_STATS_HISTORY_FREE));
			else if (0 == strcmp(tmp1, "pused"))
				SET_DBL_RESULT(result, *(double *)zbx_dc_get_stats(ZBX_STATS_HISTORY_PUSED));
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
				SET_DBL_RESULT(result, *(double *)zbx_dc_get_stats(ZBX_STATS_TREND_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)zbx_dc_get_stats(ZBX_STATS_TREND_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)zbx_dc_get_stats(ZBX_STATS_TREND_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)zbx_dc_get_stats(ZBX_STATS_TREND_FREE));
			else if (0 == strcmp(tmp1, "pused"))
				SET_DBL_RESULT(result, *(double *)zbx_dc_get_stats(ZBX_STATS_TREND_PUSED));
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else if (0 == strcmp(tmp, "index"))
		{
			if (NULL == tmp1 || '\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
			{
				SET_DBL_RESULT(result, *(double *)zbx_dc_get_stats(ZBX_STATS_HISTORY_INDEX_PFREE));
			}
			else if (0 == strcmp(tmp1, "total"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_INDEX_TOTAL));
			}
			else if (0 == strcmp(tmp1, "used"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_INDEX_USED));
			}
			else if (0 == strcmp(tmp1, "free"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_get_stats(ZBX_STATS_HISTORY_INDEX_FREE));
			}
			else if (0 == strcmp(tmp1, "pused"))
			{
				SET_DBL_RESULT(result, *(double *)zbx_dc_get_stats(ZBX_STATS_HISTORY_INDEX_PUSED));
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
			{
				SET_DBL_RESULT(result, *(double *)zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_PFREE));
			}
			else if (0 == strcmp(tmp1, "total"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_TOTAL));
			}
			else if (0 == strcmp(tmp1, "used"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_USED));
			}
			else if (0 == strcmp(tmp1, "free"))
			{
				SET_UI64_RESULT(result, *(zbx_uint64_t *)
						zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_FREE));
			}
			else if (0 == strcmp(tmp1, "pused"))
			{
				SET_DBL_RESULT(result, *(double *)zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_PUSED));
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
						(double)stats.memory_total * 100);
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
				SET_DBL_RESULT(result, (double)stats.memory_used / (double)stats.memory_total * 100);
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
		else if (SUCCEED != zbx_is_ushort(port_str, &port_number))
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

				zbx_zabbix_stats_get(&json, config_startup_time);

				zbx_json_close(&json);

				zbx_set_agent_result_type(result, ITEM_VALUE_TYPE_TEXT, json.buffer);

				zbx_json_free(&json);
			}
			else if (SUCCEED != zbx_get_remote_zabbix_stats(ip, port_number,
					config_comms->config_timeout, result))
			{
				goto out;
			}
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
							FAIL == zbx_is_time_suffix(tmp, &from, ZBX_LENGTH_UNLIMITED))
					{
						SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
						goto out;
					}

					if (NULL != tmp1 && '\0' != *tmp1 &&
							FAIL == zbx_is_time_suffix(tmp1, &to, ZBX_LENGTH_UNLIMITED))
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

					zbx_json_addint64(&json, ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE,
							zbx_dc_get_item_queue(NULL, from, to));

					zbx_set_agent_result_type(result, ITEM_VALUE_TYPE_TEXT, json.buffer);

					zbx_json_free(&json);
				}
				else if (SUCCEED != zbx_get_remote_zabbix_stats_queue(ip, port_number, tmp, tmp1,
						config_comms->config_timeout, result))
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
	else if (0 == strcmp(tmp, "discovery_queue"))			/* zabbix[discovery_queue] */
	{
		zbx_uint64_t	size;
		char		*error = NULL;

		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		if (FAIL == zbx_discovery_get_queue_size(&size, &error))
		{
			SET_MSG_RESULT(result, error);
			goto out;
		}

		SET_UI64_RESULT(result, size);
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

			SET_DBL_RESULT(result, (0 == total ? 0 : (double)stats.misses / (double)total * 100));
		}
		else if (0 == strcmp(tmp, "phits"))
		{
			zbx_uint64_t	total = stats.hits + stats.misses;

			SET_DBL_RESULT(result, (0 == total ? 0 : (double)stats.hits / (double)total * 100));
		}
		else if (0 == strcmp(tmp, "pitems"))
		{
			zbx_uint64_t	total = stats.items_num + stats.requests_num;

			SET_DBL_RESULT(result, (0 == total ? 0 : (double)stats.items_num / (double)total * 100));
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
	if (NOTSUPPORTED == ret && !ZBX_ISSET_MSG(result))
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Internal check is not supported."));

	zbx_free_agent_request(&request);

	return ret;
}
