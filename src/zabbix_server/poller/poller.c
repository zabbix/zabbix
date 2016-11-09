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

#include "db.h"
#include "dbcache.h"
#include "daemon.h"
#include "zbxserver.h"
#include "zbxself.h"
#include "../events.h"

#include "poller.h"

#include "checks_agent.h"
#include "checks_aggregate.h"
#include "checks_external.h"
#include "checks_internal.h"
#include "checks_simple.h"
#include "checks_snmp.h"
#include "checks_ipmi.h"
#include "checks_db.h"
#ifdef HAVE_SSH2
#	include "checks_ssh.h"
#endif
#include "checks_telnet.h"
#include "checks_java.h"
#include "checks_calculated.h"
#include "../../libs/zbxcrypto/tls.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: db_host_update_availability                                      *
 *                                                                            *
 * Purpose: write host availability changes into database                     *
 *                                                                            *
 * Parameters: ha    - [IN] the host availability data                        *
 *                                                                            *
 * Return value: SUCCEED - the availability changes were written into db      *
 *               FAIL    - no changes in availability data were detected      *
 *                                                                            *
 ******************************************************************************/
static int	db_host_update_availability(const zbx_host_availability_t *ha)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;

	if (SUCCEED == zbx_sql_add_host_availability(&sql, &sql_alloc, &sql_offset, ha))
	{
		DBbegin();
		DBexecute("%s", sql);
		DBcommit();

		zbx_free(sql);

		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: host_get_availability                                            *
 *                                                                            *
 * Purpose: get host availability data based on the specified item type       *
 *                                                                            *
 * Parameters: dc_host      - [IN] the host                                   *
 *             type         - [IN] the item type                              *
 *             availability - [OUT] the host availability data                *
 *                                                                            *
 * Return value: SUCCEED - the host availability data was retrieved           *
 *                         successfully                                       *
 *               FAIL    - failed to retrieve host availability data,         *
 *                         unrecognized item type was specified               *
 *                                                                            *
 ******************************************************************************/
static int	host_get_availability(const DC_HOST *dc_host, unsigned char agent, zbx_host_availability_t *ha)
{
	zbx_agent_availability_t	*availability = &ha->agents[agent];

	availability->flags = ZBX_FLAGS_AGENT_STATUS;

	switch (agent)
	{
		case ZBX_AGENT_ZABBIX:
			availability->available = dc_host->available;
			availability->error = zbx_strdup(NULL, dc_host->error);
			availability->errors_from = dc_host->errors_from;
			availability->disable_until = dc_host->disable_until;
			break;
		case ZBX_AGENT_SNMP:
			availability->available = dc_host->snmp_available;
			availability->error = zbx_strdup(NULL, dc_host->snmp_error);
			availability->errors_from = dc_host->snmp_errors_from;
			availability->disable_until = dc_host->snmp_disable_until;
			break;
		case ZBX_AGENT_IPMI:
			availability->available = dc_host->ipmi_available;
			availability->error = zbx_strdup(NULL, dc_host->ipmi_error);
			availability->errors_from = dc_host->ipmi_errors_from;
			availability->disable_until = dc_host->ipmi_disable_until;
			break;
		case ZBX_AGENT_JMX:
			availability->available = dc_host->jmx_available;
			availability->error = zbx_strdup(NULL, dc_host->jmx_error);
			availability->disable_until = dc_host->jmx_disable_until;
			availability->errors_from = dc_host->jmx_errors_from;
			break;
		default:
			return FAIL;
	}

	ha->hostid = dc_host->hostid;

	return SUCCEED;
}

static unsigned char	host_availability_agent_by_item_type(unsigned char type)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			return ZBX_AGENT_ZABBIX;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			return ZBX_AGENT_SNMP;
			break;
		case ITEM_TYPE_IPMI:
			return ZBX_AGENT_IPMI;
			break;
		case ITEM_TYPE_JMX:
			return ZBX_AGENT_JMX;
			break;
		default:
			return ZBX_AGENT_UNKNOWN;
	}
}

static void	activate_host(DC_ITEM *item, zbx_timespec_t *ts)
{
	const char		*__function_name = "activate_host";
	zbx_host_availability_t	in, out;
	unsigned char		agent_type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__function_name, item->host.hostid, item->itemid, (int)item->type);

	zbx_host_availability_init(&in, item->host.hostid);
	zbx_host_availability_init(&out,item->host.hostid);

	if (ZBX_AGENT_UNKNOWN == (agent_type = host_availability_agent_by_item_type(item->type)))
		goto out;

	if (FAIL == host_get_availability(&item->host, agent_type, &in))
		goto out;

	if (FAIL == DChost_activate(item->host.hostid, agent_type, ts, &in.agents[agent_type], &out.agents[agent_type]))
		goto out;

	if (FAIL == db_host_update_availability(&out))
		goto out;

	if (HOST_AVAILABLE_TRUE == in.agents[agent_type].available)
	{
		zabbix_log(LOG_LEVEL_WARNING, "resuming %s checks on host \"%s\": connection restored",
				zbx_agent_type_string(item->type), item->host.host);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "enabling %s checks on host \"%s\": host became available",
				zbx_agent_type_string(item->type), item->host.host);
	}
out:
	zbx_host_availability_clean(&out);
	zbx_host_availability_clean(&in);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	deactivate_host(DC_ITEM *item, zbx_timespec_t *ts, const char *error)
{
	const char		*__function_name = "deactivate_host";
	zbx_host_availability_t	in, out;
	unsigned char		agent_type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__function_name, item->host.hostid, item->itemid, (int)item->type);

	zbx_host_availability_init(&in, item->host.hostid);
	zbx_host_availability_init(&out,item->host.hostid);

	if (ZBX_AGENT_UNKNOWN == (agent_type = host_availability_agent_by_item_type(item->type)))
		goto out;

	if (FAIL == host_get_availability(&item->host, agent_type, &in))
		goto out;

	if (FAIL == DChost_deactivate(item->host.hostid, agent_type, ts, &in.agents[agent_type],
			&out.agents[agent_type], error))
	{
		goto out;
	}

	if (FAIL == db_host_update_availability(&out))
		goto out;

	if (0 == in.agents[agent_type].errors_from)
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s item \"%s\" on host \"%s\" failed:"
				" first network error, wait for %d seconds",
				zbx_agent_type_string(item->type), item->key_orig, item->host.host,
				out.agents[agent_type].disable_until - ts->sec);
	}
	else
	{
		if (HOST_AVAILABLE_FALSE != in.agents[agent_type].available)
		{
			if (HOST_AVAILABLE_FALSE != out.agents[agent_type].available)
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s item \"%s\" on host \"%s\" failed:"
						" another network error, wait for %d seconds",
						zbx_agent_type_string(item->type), item->key_orig, item->host.host,
						out.agents[agent_type].disable_until - ts->sec);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "temporarily disabling %s checks on host \"%s\":"
						" host unavailable",
						zbx_agent_type_string(item->type), item->host.host);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() errors_from:%d available:%d", __function_name,
			out.agents[agent_type].errors_from, out.agents[agent_type].available);
out:
	zbx_host_availability_clean(&out);
	zbx_host_availability_clean(&in);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void    free_result_ptr(AGENT_RESULT *result)
{
	free_result(result);
	zbx_free(result);
}



static int	get_value(DC_ITEM *item, AGENT_RESULT *result, zbx_vector_ptr_t *add_results)
{
	const char	*__function_name = "get_value";
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, item->key_orig);

	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			zbx_alarm_on(CONFIG_TIMEOUT);
			res = get_value_agent(item, result);
			zbx_alarm_off();
			break;
		case ITEM_TYPE_IPMI:
#ifdef HAVE_OPENIPMI
			res = get_value_ipmi(item, result);
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for IPMI checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_SIMPLE:
			/* simple checks use their own timeouts */
			res = get_value_simple(item, result, add_results);
			break;
		case ITEM_TYPE_INTERNAL:
			res = get_value_internal(item, result);
			break;
		case ITEM_TYPE_DB_MONITOR:
#ifdef HAVE_UNIXODBC
			zbx_alarm_on(CONFIG_TIMEOUT);
			res = get_value_db(item, result);
			zbx_alarm_off();
#else
			SET_MSG_RESULT(result,
					zbx_strdup(NULL, "Support for Database monitor checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_AGGREGATE:
			res = get_value_aggregate(item, result);
			break;
		case ITEM_TYPE_EXTERNAL:
			/* external checks use their own timeouts */
			res = get_value_external(item, result);
			break;
		case ITEM_TYPE_SSH:
#ifdef HAVE_SSH2
			zbx_alarm_on(CONFIG_TIMEOUT);
			res = get_value_ssh(item, result);
			zbx_alarm_off();
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for SSH checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_TELNET:
			zbx_alarm_on(CONFIG_TIMEOUT);
			res = get_value_telnet(item, result);
			zbx_alarm_off();
			break;
		case ITEM_TYPE_CALCULATED:
			res = get_value_calculated(item, result);
			break;
		default:
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Not supported item type:%d", item->type));
			res = CONFIG_ERROR;
	}

	if (SUCCEED != res)
	{
		if (!ISSET_MSG(result))
			SET_MSG_RESULT(result, zbx_strdup(NULL, ZBX_NOTSUPPORTED_MSG));

		zabbix_log(LOG_LEVEL_DEBUG, "Item [%s:%s] error: %s", item->host.host, item->key_orig, result->msg);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: get_values                                                       *
 *                                                                            *
 * Purpose: retrieve values of metrics from monitored hosts                   *
 *                                                                            *
 * Parameters: poller_type - [IN] poller type (ZBX_POLLER_TYPE_...)           *
 *                                                                            *
 * Return value: number of items processed                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: processes single item at a time except for Java, SNMP items,     *
 *           see DCconfig_get_poller_items()                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_values(unsigned char poller_type, int *nextcheck)
{
	const char		*__function_name = "get_values";
	DC_ITEM			items[MAX_POLLER_ITEMS];
	AGENT_RESULT		results[MAX_POLLER_ITEMS];
	int			errcodes[MAX_POLLER_ITEMS];
	zbx_timespec_t		timespec;
	char			*port = NULL, error[ITEM_ERROR_LEN_MAX];
	int			i, num, last_available = HOST_AVAILABLE_UNKNOWN;
	zbx_vector_ptr_t	add_results;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	num = DCconfig_get_poller_items(poller_type, items);

	if (0 == num)
	{
		*nextcheck = DCconfig_get_poller_nextcheck(poller_type);
		goto exit;
	}

	/* prepare items */
	for (i = 0; i < num; i++)
	{
		init_result(&results[i]);
		errcodes[i] = SUCCEED;

		ZBX_STRDUP(items[i].key, items[i].key_orig);
		if (SUCCEED != substitute_key_macros(&items[i].key, NULL, &items[i], NULL,
				MACRO_TYPE_ITEM_KEY, error, sizeof(error)))
		{
			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		switch (items[i].type)
		{
			case ITEM_TYPE_ZABBIX:
			case ITEM_TYPE_SNMPv1:
			case ITEM_TYPE_SNMPv2c:
			case ITEM_TYPE_SNMPv3:
			case ITEM_TYPE_IPMI:
			case ITEM_TYPE_JMX:
				ZBX_STRDUP(port, items[i].interface.port_orig);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &port, MACRO_TYPE_COMMON, NULL, 0);
				if (FAIL == is_ushort(port, &items[i].interface.port))
				{
					SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "Invalid port number [%s]",
								items[i].interface.port_orig));
					errcodes[i] = CONFIG_ERROR;
					continue;
				}
				break;
		}

		switch (items[i].type)
		{
			case ITEM_TYPE_SNMPv3:
				ZBX_STRDUP(items[i].snmpv3_securityname, items[i].snmpv3_securityname_orig);
				ZBX_STRDUP(items[i].snmpv3_authpassphrase, items[i].snmpv3_authpassphrase_orig);
				ZBX_STRDUP(items[i].snmpv3_privpassphrase, items[i].snmpv3_privpassphrase_orig);
				ZBX_STRDUP(items[i].snmpv3_contextname, items[i].snmpv3_contextname_orig);

				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].snmpv3_securityname, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].snmpv3_authpassphrase, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].snmpv3_privpassphrase, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].snmpv3_contextname, MACRO_TYPE_COMMON, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_SNMPv1:
			case ITEM_TYPE_SNMPv2c:
				ZBX_STRDUP(items[i].snmp_community, items[i].snmp_community_orig);
				ZBX_STRDUP(items[i].snmp_oid, items[i].snmp_oid_orig);

				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].snmp_community, MACRO_TYPE_COMMON, NULL, 0);
				if (SUCCEED != substitute_key_macros(&items[i].snmp_oid, &items[i].host.hostid, NULL,
						NULL, MACRO_TYPE_SNMP_OID, error, sizeof(error)))
				{
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
					errcodes[i] = CONFIG_ERROR;
					continue;
				}
				break;
			case ITEM_TYPE_SSH:
				ZBX_STRDUP(items[i].publickey, items[i].publickey_orig);
				ZBX_STRDUP(items[i].privatekey, items[i].privatekey_orig);

				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].publickey, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].privatekey, MACRO_TYPE_COMMON, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_TELNET:
			case ITEM_TYPE_DB_MONITOR:
				substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, &items[i],
						NULL, &items[i].params, MACRO_TYPE_PARAMS_FIELD, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_SIMPLE:
			case ITEM_TYPE_JMX:
				items[i].username = zbx_strdup(items[i].username, items[i].username_orig);
				items[i].password = zbx_strdup(items[i].password, items[i].password_orig);

				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].username, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, &items[i].password, MACRO_TYPE_COMMON, NULL, 0);
				break;
		}
	}

	zbx_free(port);

	zbx_vector_ptr_create(&add_results);

	/* retrieve item values */
	if (SUCCEED == is_snmp_type(items[0].type))
	{
#ifdef HAVE_NETSNMP
		/* SNMP checks use their own timeouts */
		get_values_snmp(items, results, errcodes, num);
#else
		for (i = 0; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "Support for SNMP checks was not compiled in."));
			errcodes[i] = CONFIG_ERROR;
		}
#endif
	}
	else if (ITEM_TYPE_JMX == items[0].type)
	{
		zbx_alarm_on(CONFIG_TIMEOUT);
		get_values_java(ZBX_JAVA_GATEWAY_REQUEST_JMX, items, results, errcodes, num);
		zbx_alarm_off();
	}
	else if (1 == num)
	{
		if (SUCCEED == errcodes[0])
			errcodes[0] = get_value(&items[0], &results[0], &add_results);
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_timespec(&timespec);

	/* process item values */
	for (i = 0; i < num; i++)
	{
		zbx_uint64_t	lastlogsize, *plastlogsize = NULL;

		switch (errcodes[i])
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
				if (HOST_AVAILABLE_TRUE != last_available)
				{
					activate_host(&items[i], &timespec);
					last_available = HOST_AVAILABLE_TRUE;
				}
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
			case TIMEOUT_ERROR:
				if (HOST_AVAILABLE_FALSE != last_available)
				{
					deactivate_host(&items[i], &timespec, results[i].msg);
					last_available = HOST_AVAILABLE_FALSE;
				}
				break;
			case CONFIG_ERROR:
				/* nothing to do */
				break;
			default:
				zbx_error("unknown response code returned: %d", errcodes[i]);
				THIS_SHOULD_NEVER_HAPPEN;
		}

		if (SUCCEED == errcodes[i])
		{
			/* remove formatting symbols from the end of the result */
			/* so it could be checked by "is_uint64" and "is_double" functions */
			/* when we try to get "int" or "float" values from "string" result */
			if (0 != ISSET_STR(&results[i]))
				zbx_rtrim(results[i].str, ZBX_WHITESPACE);
			if (0 != ISSET_TEXT(&results[i]))
				zbx_rtrim(results[i].text, ZBX_WHITESPACE);

			if (0 == add_results.values_num)
			{
				items[i].state = ITEM_STATE_NORMAL;
				dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, &results[i],
						&timespec, items[i].state, NULL);
			}
			else
			{
				/* vmware.eventlog item returns vector of AGENT_RESULT representing events */

				int		j;
				zbx_timespec_t	ts_tmp = timespec;

				for (j = 0; j < add_results.values_num; j++)
				{
					AGENT_RESULT	*add_result = add_results.values[j];

					if (ISSET_MSG(add_result))
					{
						items[i].state = ITEM_STATE_NOTSUPPORTED;
						dc_add_history(items[i].itemid, items[i].value_type, items[i].flags,
								NULL, &ts_tmp, items[i].state, add_result->msg);
					}
					else
					{
						items[i].state = ITEM_STATE_NORMAL;
						dc_add_history(items[i].itemid, items[i].value_type, items[i].flags,
								add_result, &ts_tmp, items[i].state, NULL);

						if (0 != ISSET_META(add_result))
						{
							plastlogsize = &lastlogsize;
							lastlogsize = add_result->lastlogsize;
						}
					}

					/* ensure that every log item value timestamp is unique */
					if (++ts_tmp.ns == 1000000000)
					{
						ts_tmp.sec++;
						ts_tmp.ns = 0;
					}
				}
			}
		}
		else if (NOTSUPPORTED == errcodes[i] || AGENT_ERROR == errcodes[i] || CONFIG_ERROR == errcodes[i])
		{
			items[i].state = ITEM_STATE_NOTSUPPORTED;
			dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, NULL, &timespec,
					items[i].state, results[i].msg);
		}

		DCpoller_requeue_items(&items[i].itemid, &items[i].state, &timespec.sec, plastlogsize, NULL,
				&errcodes[i], 1, poller_type, nextcheck);

		zbx_free(items[i].key);

		switch (items[i].type)
		{
			case ITEM_TYPE_SNMPv3:
				zbx_free(items[i].snmpv3_securityname);
				zbx_free(items[i].snmpv3_authpassphrase);
				zbx_free(items[i].snmpv3_privpassphrase);
				zbx_free(items[i].snmpv3_contextname);
				/* break; is not missing here */
			case ITEM_TYPE_SNMPv1:
			case ITEM_TYPE_SNMPv2c:
				zbx_free(items[i].snmp_community);
				zbx_free(items[i].snmp_oid);
				break;
			case ITEM_TYPE_SSH:
				zbx_free(items[i].publickey);
				zbx_free(items[i].privatekey);
				/* break; is not missing here */
			case ITEM_TYPE_TELNET:
			case ITEM_TYPE_DB_MONITOR:
			case ITEM_TYPE_SIMPLE:
			case ITEM_TYPE_JMX:
				zbx_free(items[i].username);
				zbx_free(items[i].password);
				break;
		}

		free_result(&results[i]);
	}

	zbx_vector_ptr_clear_ext(&add_results, (zbx_mem_free_func_t)free_result_ptr);
	zbx_vector_ptr_destroy(&add_results);

	DCconfig_clean_items(items, NULL, num);

	dc_flush_history();
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, num);

	return num;
}

ZBX_THREAD_ENTRY(poller_thread, args)
{
	int		nextcheck, sleeptime = -1, processed = 0, old_processed = 0;
	double		sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t		last_stat_time, last_ipmi_host_check;
	unsigned char	poller_type;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	poller_type = *(unsigned char *)((zbx_thread_args_t *)args)->args;
	process_type = ((zbx_thread_args_t *)args)->process_type;

	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);
#ifdef HAVE_NETSNMP
	if (ZBX_POLLER_TYPE_NORMAL == poller_type || ZBX_POLLER_TYPE_UNREACHABLE == poller_type)
		zbx_init_snmp();
#endif

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = last_ipmi_host_check = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_handle_log();

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, old_processed,
					old_total_sec);
		}

		sec = zbx_time();
		processed += get_values(poller_type, &nextcheck);
		total_sec += zbx_time() - sec;
#ifdef HAVE_OPENIPMI
		if (ZBX_POLLER_TYPE_IPMI == poller_type && SEC_PER_HOUR < time(NULL) - last_ipmi_host_check)
		{
			last_ipmi_host_check = time(NULL);
			delete_inactive_ipmi_hosts(last_ipmi_host_check);
		}
#endif
		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, processed, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, idle %d sec]",
					get_process_type_string(process_type), process_num, processed, total_sec,
					sleeptime);
				old_processed = processed;
				old_total_sec = total_sec;
			}
			processed = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(sleeptime);
	}

#undef STAT_INTERVAL
}
