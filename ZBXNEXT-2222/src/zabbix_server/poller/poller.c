/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

extern unsigned char	process_type;
extern int		process_num;

static void	update_triggers_status_to_unknown(zbx_uint64_t hostid, zbx_item_type_t type, zbx_timespec_t *ts, char *reason)
{
	const char		*__function_name = "update_triggers_status_to_unknown";
	DB_RESULT		result;
	DB_ROW			row;
	char			failed_type_buf[8];
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DC_TRIGGER		trigger;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64, __function_name, hostid);

	/* determine failed item type */
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			zbx_snprintf(failed_type_buf, sizeof(failed_type_buf), "%d", ITEM_TYPE_ZABBIX);
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			zbx_snprintf(failed_type_buf, sizeof(failed_type_buf), "%d,%d,%d",
					ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3);
			break;
		case ITEM_TYPE_IPMI:
			zbx_snprintf(failed_type_buf, sizeof(failed_type_buf), "%d", ITEM_TYPE_IPMI);
			break;
		case ITEM_TYPE_JMX:
			zbx_snprintf(failed_type_buf, sizeof(failed_type_buf), "%d", ITEM_TYPE_JMX);
			break;
		default:
			/* we should never end up here */
			assert(0);
	}

	/*************************************************************************
	 * Let's say an item MYITEM returns error. There is a trigger associated *
	 * with it. We set that trigger status to UNKNOWN if ALL are true:       *
	 * - MYITEM status is ACTIVE                                             *
	 * - MYITEM state is NORMAL                                              *
	 * - trigger does not reference time-based function                      *
	 * - trigger status is ENABLED                                           *
	 * - trigger and MYITEM reference the same host                          *
	 * - trigger host status is MONITORED                                    *
	 * - trigger does NOT reference an item that has ALL true:               *
	 *   - item status is ACTIVE                                             *
	 *   - item host status is MONITORED                                     *
	 *   - item trigger references time-based function                       *
	 *     OR                                                                *
	 *     item and MYITEM types differ AND item host status is AVAILABLE    *
	 *************************************************************************/
	result = DBselect(
			"select distinct t.triggerid,t.description,t.expression,t.priority,t.type,t.value,t.state,"
				"t.error,t.lastchange"
			" from items i,functions f,triggers t,hosts h"
			" where i.itemid=f.itemid"
				" and f.triggerid=t.triggerid"
				" and i.hostid=h.hostid"
				" and i.status=%d"
				" and i.state=%d"
				" and i.type in (%s)"
				" and f.function not in (" ZBX_SQL_TIME_FUNCTIONS ")"
				" and t.status=%d"
				" and h.hostid=" ZBX_FS_UI64
				" and h.status=%d"
			" and not exists ("
				"select 1"
				" from functions f2,items i2,hosts h2"
				" where f2.triggerid=f.triggerid"
					" and f2.itemid=i2.itemid"
					" and i2.hostid=h2.hostid"
					" and ("
						"f2.function in (" ZBX_SQL_TIME_FUNCTIONS ")"
						" or ("
							"i2.type not in (%s)"
							" and ("
								"i2.type not in (%d,%d,%d,%d,%d,%d)"
								" or (i2.type in (%d) and h2.available=%d)"
								" or (i2.type in (%d,%d,%d) and h2.snmp_available=%d)"
								" or (i2.type in (%d) and h2.ipmi_available=%d)"
								" or (i2.type in (%d) and h2.jmx_available=%d)"
							")"
						")"
					")"
					" and i2.status=%d"
					" and i2.state=%d"
					" and h2.status=%d"
			")"
			" order by t.triggerid",
			ITEM_STATUS_ACTIVE,
			ITEM_STATE_NORMAL,
			failed_type_buf,
			TRIGGER_STATUS_ENABLED,
			hostid,
			HOST_STATUS_MONITORED,
			failed_type_buf,
			ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3, ITEM_TYPE_IPMI,
			ITEM_TYPE_JMX,
			ITEM_TYPE_ZABBIX, HOST_AVAILABLE_TRUE,
			ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3, HOST_AVAILABLE_TRUE,
			ITEM_TYPE_IPMI, HOST_AVAILABLE_TRUE,
			ITEM_TYPE_JMX, HOST_AVAILABLE_TRUE,
			ITEM_STATUS_ACTIVE,
			ITEM_STATE_NORMAL,
			HOST_STATUS_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(trigger.triggerid, row[0]);
		trigger.description = row[1];
		trigger.expression_orig = row[2];
		ZBX_STR2UCHAR(trigger.priority, row[3]);
		ZBX_STR2UCHAR(trigger.type, row[4]);
		trigger.value = atoi(row[5]);
		trigger.state = atoi(row[6]);
		trigger.error = row[7];
		trigger.lastchange = atoi(row[8]);
		trigger.new_value = TRIGGER_VALUE_UNKNOWN;
		trigger.new_error = reason;
		trigger.timespec = *ts;

		sql_offset = 0;

		if (SUCCEED == process_trigger(&sql, &sql_alloc, &sql_offset, &trigger))
		{
			DBbegin();
			DBexecute("%s", sql);
			DBcommit();
		}
	}

	zbx_free(sql);
	DBfree_result(result);

	DBbegin();
	process_events();
	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: db_host_update_availability                                      *
 *                                                                            *
 * Purpose: write host availability changes into database                     *
 *                                                                            *
 * Parameters: in    - [IN] the host availability data before changes         *
 *             out   - [IN] the host availability data after changes          *
 *             error - [IN] an optional error message that will be written    *
 *                          into database if availability data was changed.   *
 *                                                                            *
 * Return value: SUCCEED - the availability changes were written into db      *
 *               FAIL    - no changes in availability data were detected      *
 *                                                                            *
 ******************************************************************************/
static int	db_host_update_availability(const zbx_host_availability_t *in, const zbx_host_availability_t *out,
		const char *error)
{
	char	*sqlset = NULL, sqlset_delim = ' ', *sqlset_prefix;
	size_t	sqlset_alloc = 0, sqlset_offset = 0;

	switch (in->type)
	{
		case ITEM_TYPE_ZABBIX:
			sqlset_prefix = "";
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			sqlset_prefix = "snmp_";
			break;
		case ITEM_TYPE_IPMI:
			sqlset_prefix = "ipmi_";
			break;
		case ITEM_TYPE_JMX:
			sqlset_prefix = "jmx_";
			break;
		default:
			return FAIL;
	}

	if (in->available != out->available)
	{
		zbx_snprintf_alloc(&sqlset, &sqlset_alloc, &sqlset_offset, "%c%savailable=%d", sqlset_delim,
				sqlset_prefix, out->available);
		sqlset_delim = ',';
	}

	if (in->errors_from != out->errors_from)
	{
		zbx_snprintf_alloc(&sqlset, &sqlset_alloc, &sqlset_offset, "%c%serrors_from=%d", sqlset_delim,
				sqlset_prefix, out->errors_from);
		sqlset_delim = ',';
	}

	if (in->disable_until != out->disable_until)
	{
		zbx_snprintf_alloc(&sqlset, &sqlset_alloc, &sqlset_offset, "%c%sdisable_until=%d", sqlset_delim,
				sqlset_prefix, out->disable_until);
	}

	if (NULL != sqlset)
	{
		char	*error_esc;

		error_esc = DBdyn_escape_string_len(error, HOST_ERROR_LEN);

		DBbegin();
		DBexecute("update hosts set%s,%serror='%s' where hostid=" ZBX_FS_UI64,
				sqlset, sqlset_prefix, error_esc, out->hostid);
		DBcommit();

		zbx_free(error_esc);
		zbx_free(sqlset);

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
static int	host_get_availability(const DC_HOST *dc_host, unsigned char type, zbx_host_availability_t *availability)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			availability->errors_from = dc_host->errors_from;
			availability->available = dc_host->available;
			availability->disable_until = dc_host->disable_until;
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			availability->errors_from = dc_host->snmp_errors_from;
			availability->available = dc_host->snmp_available;
			availability->disable_until = dc_host->snmp_disable_until;
			break;
		case ITEM_TYPE_IPMI:
			availability->errors_from = dc_host->ipmi_errors_from;
			availability->available = dc_host->ipmi_available;
			availability->disable_until = dc_host->ipmi_disable_until;
			break;
		case ITEM_TYPE_JMX:
			availability->errors_from = dc_host->jmx_errors_from;
			availability->available = dc_host->jmx_available;
			availability->disable_until = dc_host->jmx_disable_until;
			break;
		default:
			return FAIL;
	}

	availability->type = type;
	availability->hostid = dc_host->hostid;

	return SUCCEED;
}

static void	activate_host(DC_ITEM *item, zbx_timespec_t *ts)
{
	const char		*__function_name = "activate_host";

	zbx_host_availability_t	in, out;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__function_name, item->host.hostid, item->itemid, (int)item->type);

	if (FAIL == host_get_availability(&item->host, item->type, &in))
		goto out;

	if (FAIL == DChost_activate(&in, &out))
		goto out;

	if (FAIL == db_host_update_availability(&in, &out, ""))
		goto out;

	if (HOST_AVAILABLE_TRUE == in.available)
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	deactivate_host(DC_ITEM *item, zbx_timespec_t *ts, const char *error)
{
	const char		*__function_name = "deactivate_host";

	zbx_host_availability_t	in, out;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__function_name, item->host.hostid, item->itemid, (int)item->type);

	if (FAIL == host_get_availability(&item->host, item->type, &in))
		goto out;

	if (FAIL == DChost_deactivate(ts, &in, &out))
		goto out;

	if (FAIL == db_host_update_availability(&in, &out, error))
		goto out;

	if (0 == in.errors_from)
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s item \"%s\" on host \"%s\" failed:"
				" first network error, wait for %d seconds",
				zbx_agent_type_string(item->type), item->key_orig, item->host.host,
				out.disable_until - ts->sec);
	}
	else
	{
		if (HOST_AVAILABLE_FALSE != in.available)
		{
			if (HOST_AVAILABLE_FALSE != out.available)
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s item \"%s\" on host \"%s\" failed:"
						" another network error, wait for %d seconds",
						zbx_agent_type_string(item->type), item->key_orig, item->host.host,
						out.disable_until - ts->sec);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "temporarily disabling %s checks on host \"%s\":"
						" host unavailable",
						zbx_agent_type_string(item->type), item->host.host);

				update_triggers_status_to_unknown(item->host.hostid, item->type, ts,
						"Agent is unavailable.");
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() errors_from:%d available:%d", __function_name, out.errors_from,
			out.available);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	get_value(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value";
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, item->key_orig);

	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			alarm(CONFIG_TIMEOUT);
			res = get_value_agent(item, result);
			alarm(0);
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
			res = get_value_simple(item, result);
			break;
		case ITEM_TYPE_INTERNAL:
			res = get_value_internal(item, result);
			break;
		case ITEM_TYPE_DB_MONITOR:
#ifdef HAVE_UNIXODBC
			alarm(CONFIG_TIMEOUT);
			res = get_value_db(item, result);
			alarm(0);
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
			alarm(CONFIG_TIMEOUT);
			res = get_value_ssh(item, result);
			alarm(0);
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for SSH checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_TELNET:
			alarm(CONFIG_TIMEOUT);
			res = get_value_telnet(item, result);
			alarm(0);
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
			SET_MSG_RESULT(result, zbx_strdup(NULL, ZBX_NOTSUPPORTED));

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
 * Parameters:                                                                *
 *                                                                            *
 * Return value: number of items processed                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_values(unsigned char poller_type)
{
	const char	*__function_name = "get_values";
	DC_ITEM		items[MAX_POLLER_ITEMS];
	AGENT_RESULT	results[MAX_POLLER_ITEMS];
	zbx_uint64_t	lastlogsizes[MAX_POLLER_ITEMS];
	int		errcodes[MAX_POLLER_ITEMS];
	zbx_timespec_t	timespec;
	int		i, num;
	char		*port = NULL, error[ITEM_ERROR_LEN_MAX];
	int		last_available = HOST_AVAILABLE_UNKNOWN;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	num = DCconfig_get_poller_items(poller_type, items);

	if (0 == num)
		goto exit;

	/* prepare items */
	for (i = 0; i < num; i++)
	{
		init_result(&results[i]);
		errcodes[i] = SUCCEED;
		lastlogsizes[i] = 0;

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
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&port, MACRO_TYPE_COMMON, NULL, 0);
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

				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].snmpv3_securityname, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].snmpv3_authpassphrase, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].snmpv3_privpassphrase, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].snmpv3_contextname, MACRO_TYPE_COMMON, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_SNMPv1:
			case ITEM_TYPE_SNMPv2c:
				ZBX_STRDUP(items[i].snmp_community, items[i].snmp_community_orig);
				ZBX_STRDUP(items[i].snmp_oid, items[i].snmp_oid_orig);

				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].snmp_community, MACRO_TYPE_COMMON, NULL, 0);
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

				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].publickey, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].privatekey, MACRO_TYPE_COMMON, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_TELNET:
			case ITEM_TYPE_DB_MONITOR:
				substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, &items[i],
						&items[i].params, MACRO_TYPE_PARAMS_FIELD, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_SIMPLE:
			case ITEM_TYPE_JMX:
				items[i].username = zbx_strdup(items[i].username, items[i].username_orig);
				items[i].password = zbx_strdup(items[i].password, items[i].password_orig);

				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].username, MACRO_TYPE_COMMON, NULL, 0);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						&items[i].password, MACRO_TYPE_COMMON, NULL, 0);
				break;
		}
	}

	zbx_free(port);

	/* retrieve item values */
	if (SUCCEED == is_snmp_type(items[0].type))
	{
#ifdef HAVE_SNMP
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
		alarm(CONFIG_TIMEOUT);
		get_values_java(ZBX_JAVA_GATEWAY_REQUEST_JMX, items, results, errcodes, num);
		alarm(0);
	}
	else if (1 == num)
	{
		if (SUCCEED == errcodes[0])
			errcodes[0] = get_value(&items[0], &results[0]);
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_timespec(&timespec);

	/* process item values */
	for (i = 0; i < num; i++)
	{
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
				assert(0);
		}

		if (SUCCEED == errcodes[i])
		{
			/* remove formatting symbols from the end of the result */
			/* so it could be checked by "is_uint64" and "is_double" functions */
			/* when we try to get "int" or "float" values from "string" result */
			if (ISSET_STR(&results[i]))
				zbx_rtrim(results[i].str, ZBX_WHITESPACE);
			if (ISSET_TEXT(&results[i]))
				zbx_rtrim(results[i].text, ZBX_WHITESPACE);

			items[i].state = ITEM_STATE_NORMAL;
			dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, &results[i], &timespec,
					items[i].state, NULL);
			lastlogsizes[i] = get_log_result_lastlogsize(&results[i]);
		}
		else if (NOTSUPPORTED == errcodes[i] || AGENT_ERROR == errcodes[i] || CONFIG_ERROR == errcodes[i])
		{
			items[i].state = ITEM_STATE_NOTSUPPORTED;
			dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, NULL, &timespec,
					items[i].state, results[i].msg);
		}

		DCrequeue_items(&items[i].itemid, &items[i].state, &timespec.sec, &lastlogsizes[i], NULL,
				&errcodes[i], 1);

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

	DCconfig_clean_items(items, NULL, num);

	dc_flush_history();
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, num);

	return num;
}

void	main_poller_loop(unsigned char poller_type)
{
	int	nextcheck, sleeptime = -1, processed = 0, old_processed = 0;
	double	sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t	last_stat_time;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, old_processed,
					old_total_sec);
		}

		sec = zbx_time();
		processed += get_values(poller_type);
		total_sec += zbx_time() - sec;

		nextcheck = DCconfig_get_poller_nextcheck(poller_type);
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
