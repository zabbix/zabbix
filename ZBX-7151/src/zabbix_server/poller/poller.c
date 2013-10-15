/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#define MAX_BUNCH_ITEMS	32

extern unsigned char	process_type;
extern int		process_num;

static int	is_bunch_poller(int poller_type)
{
	return ZBX_POLLER_TYPE_JAVA == poller_type ? SUCCEED : FAIL;
}

static void	update_triggers_status_to_unknown(zbx_uint64_t hostid, zbx_item_type_t type, zbx_timespec_t *ts, char *reason)
{
	const char	*__function_name = "update_triggers_status_to_unknown";
	DB_RESULT	result;
	DB_ROW		row;
	DC_TRIGGER	*tr = NULL, *trigger;
	int		tr_alloc = 0, tr_num = 0, i, events_num = 0;
	char		*sql = NULL, failed_type_buf[8];
	size_t		sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64, __function_name, hostid);

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

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
			"select distinct t.triggerid,t.type,t.value,t.value_flags,t.error"
			" from items i,functions f,triggers t,hosts h"
			" where i.itemid=f.itemid"
				" and f.triggerid=t.triggerid"
				" and i.hostid=h.hostid"
				" and i.status=%d"
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
					" and h2.status=%d"
			")"
			" order by t.triggerid",
			ITEM_STATUS_ACTIVE,
			failed_type_buf,
			TRIGGER_STATUS_ENABLED,
			hostid,
			HOST_STATUS_MONITORED,
			failed_type_buf,
			ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3, ITEM_TYPE_IPMI, ITEM_TYPE_JMX,
			ITEM_TYPE_ZABBIX, HOST_AVAILABLE_TRUE,
			ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3, HOST_AVAILABLE_TRUE,
			ITEM_TYPE_IPMI, HOST_AVAILABLE_TRUE,
			ITEM_TYPE_JMX, HOST_AVAILABLE_TRUE,
			ITEM_STATUS_ACTIVE,
			HOST_STATUS_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		if (tr_num == tr_alloc)
		{
			tr_alloc += 64;
			tr = zbx_realloc(tr, tr_alloc * sizeof(DC_TRIGGER));
		}

		trigger = &tr[tr_num++];

		ZBX_STR2UINT64(trigger->triggerid, row[0]);
		trigger->type = (unsigned char)atoi(row[1]);
		trigger->value = atoi(row[2]);
		trigger->value_flags = atoi(row[3]);
		trigger->new_value = TRIGGER_VALUE_UNKNOWN;
		strscpy(trigger->error, row[4]);
		trigger->timespec = *ts;

		if (SUCCEED == DBget_trigger_update_sql(&sql, &sql_alloc, &sql_offset, trigger->triggerid,
				trigger->type, trigger->value, trigger->value_flags, trigger->error, trigger->new_value, reason,
				&trigger->timespec, &trigger->add_event, &trigger->value_changed))
		{
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		if (1 == trigger->add_event)
			events_num++;
	}
	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* begin..end; is a must in case of ORACLE */
		DBexecute("%s", sql);

	zbx_free(sql);

	if (0 != events_num)
	{
		zbx_uint64_t	eventid;

		eventid = DBget_maxid_num("events", events_num);

		for (i = 0; i < tr_num; i++)
		{
			trigger = &tr[i];

			if (1 != trigger->add_event)
				continue;

			process_event(eventid++, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid,
					&trigger->timespec, trigger->new_value, trigger->value_changed, 0, 0);
		}
	}

	zbx_free(tr);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	activate_host(DC_ITEM *item, zbx_timespec_t *ts)
{
	const char		*__function_name = "activate_host";
	char			sql[MAX_STRING_LEN], error_msg[MAX_STRING_LEN];
	size_t			offset = 0;
	int			*errors_from, *disable_until;
	unsigned char		*available;
	const char		*fld_errors_from, *fld_available, *fld_disable_until, *fld_error;
	zbx_host_availability_t	availability;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__function_name, item->host.hostid, item->itemid, item->type);

	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			errors_from = &item->host.errors_from;
			available = &item->host.available;
			disable_until = &item->host.disable_until;

			fld_errors_from = "errors_from";
			fld_available = "available";
			fld_disable_until = "disable_until";
			fld_error = "error";
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			errors_from = &item->host.snmp_errors_from;
			available = &item->host.snmp_available;
			disable_until = &item->host.snmp_disable_until;

			fld_errors_from = "snmp_errors_from";
			fld_available = "snmp_available";
			fld_disable_until = "snmp_disable_until";
			fld_error = "snmp_error";
			break;
		case ITEM_TYPE_IPMI:
			errors_from = &item->host.ipmi_errors_from;
			available = &item->host.ipmi_available;
			disable_until = &item->host.ipmi_disable_until;

			fld_errors_from = "ipmi_errors_from";
			fld_available = "ipmi_available";
			fld_disable_until = "ipmi_disable_until";
			fld_error = "ipmi_error";
			break;
		case ITEM_TYPE_JMX:
			errors_from = &item->host.jmx_errors_from;
			available = &item->host.jmx_available;
			disable_until = &item->host.jmx_disable_until;

			fld_errors_from = "jmx_errors_from";
			fld_available = "jmx_available";
			fld_disable_until = "jmx_disable_until";
			fld_error = "jmx_error";
			break;
		default:
			return;
	}

	if (0 == *errors_from && HOST_AVAILABLE_TRUE == *available)
		return;

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "update hosts set ");

	if (HOST_AVAILABLE_TRUE == *available)
	{
		zbx_snprintf(error_msg, sizeof(error_msg), "resuming %s checks on host [%s]: connection restored",
				zbx_host_type_string(item->type), item->host.host);

		zabbix_log(LOG_LEVEL_WARNING, "%s", error_msg);
	}
	else if (HOST_AVAILABLE_TRUE != *available)
	{
		zbx_snprintf(error_msg, sizeof(error_msg), "enabling %s checks on host [%s]: host became available",
				zbx_host_type_string(item->type), item->host.host);

		zabbix_log(LOG_LEVEL_WARNING, "%s", error_msg);

		*available = HOST_AVAILABLE_TRUE;
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "%s=%d,", fld_available, *available);
	}

	*errors_from = 0;
	*disable_until = 0;
	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "%s=%d,%s=%d,%s='' where hostid=" ZBX_FS_UI64,
			fld_errors_from, *errors_from, fld_disable_until, *disable_until, fld_error, item->host.hostid);

	availability.hostid = item->host.hostid;
	availability.type = item->type;
	availability.available = *available;
	availability.errors_from =  *errors_from;
	availability.disable_until = *disable_until;

	if (1 == DCconfig_update_host_availability(&availability, 1))
	{
		DBbegin();
		DBexecute("%s", sql);
		DBcommit();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	deactivate_host(DC_ITEM *item, zbx_timespec_t *ts, const char *error)
{
	const char		*__function_name = "deactivate_host";
	char			sql[MAX_STRING_LEN], *error_esc, error_msg[MAX_STRING_LEN];
	size_t			offset = 0;
	int			*errors_from, *disable_until;
	unsigned char		*available;
	const char		*fld_errors_from, *fld_available, *fld_disable_until, *fld_error;
	zbx_host_availability_t	availability;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__function_name, item->host.hostid, item->itemid, item->type);

	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			errors_from = &item->host.errors_from;
			available = &item->host.available;
			disable_until = &item->host.disable_until;

			fld_errors_from = "errors_from";
			fld_available = "available";
			fld_disable_until = "disable_until";
			fld_error = "error";
			break;
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			errors_from = &item->host.snmp_errors_from;
			available = &item->host.snmp_available;
			disable_until = &item->host.snmp_disable_until;

			fld_errors_from = "snmp_errors_from";
			fld_available = "snmp_available";
			fld_disable_until = "snmp_disable_until";
			fld_error = "snmp_error";
			break;
		case ITEM_TYPE_IPMI:
			errors_from = &item->host.ipmi_errors_from;
			available = &item->host.ipmi_available;
			disable_until = &item->host.ipmi_disable_until;

			fld_errors_from = "ipmi_errors_from";
			fld_available = "ipmi_available";
			fld_disable_until = "ipmi_disable_until";
			fld_error = "ipmi_error";
			break;
		case ITEM_TYPE_JMX:
			errors_from = &item->host.jmx_errors_from;
			available = &item->host.jmx_available;
			disable_until = &item->host.jmx_disable_until;

			fld_errors_from = "jmx_errors_from";
			fld_available = "jmx_available";
			fld_disable_until = "jmx_disable_until";
			fld_error = "jmx_error";
			break;
		default:
			return;
	}

	*error_msg = '\0';

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "update hosts set ");

	if (0 == *errors_from)
	{
		zbx_snprintf(error_msg, sizeof(error_msg), "%s item [%s] on host [%s] failed:"
				" first network error, wait for %d seconds",
				zbx_host_type_string(item->type), item->key_orig, item->host.host, CONFIG_UNREACHABLE_DELAY);

		*errors_from = ts->sec;
		*disable_until = ts->sec + CONFIG_UNREACHABLE_DELAY;
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "%s=%d,", fld_errors_from, *errors_from);
	}
	else
	{
		if (ts->sec - *errors_from <= CONFIG_UNREACHABLE_PERIOD)
		{
			zbx_snprintf(error_msg, sizeof(error_msg), "%s item [%s] on host [%s] failed:"
					" another network error, wait for %d seconds",
					zbx_host_type_string(item->type), item->key_orig, item->host.host, CONFIG_UNREACHABLE_DELAY);

			*disable_until = ts->sec + CONFIG_UNREACHABLE_DELAY;
		}
		else
		{
			*disable_until = ts->sec + CONFIG_UNAVAILABLE_DELAY;

			if (HOST_AVAILABLE_FALSE != *available)
			{
				zbx_snprintf(error_msg, sizeof(error_msg), "temporarily disabling %s checks on host [%s]:"
						" host unavailable",
						zbx_host_type_string(item->type), item->host.host);

				*available = HOST_AVAILABLE_FALSE;

				offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "%s=%d,",
						fld_available, *available);

				update_triggers_status_to_unknown(item->host.hostid, item->type, ts,
						"Agent is unavailable.");
			}

			error_esc = DBdyn_escape_string_len(error, HOST_ERROR_LEN);
			offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "%s='%s',", fld_error, error_esc);
			zbx_free(error_esc);
		}
	}

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "%s=%d where hostid=" ZBX_FS_UI64,
			fld_disable_until, *disable_until, item->host.hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() errors_from:%d available:%d", __function_name, *errors_from, *available);

	availability.hostid = item->host.hostid;
	availability.type = item->type;
	availability.available = *available;
	availability.errors_from =  *errors_from;
	availability.disable_until = *disable_until;

	if (1 == DCconfig_update_host_availability(&availability, 1))
	{
		DBbegin();
		DBexecute("%s", sql);
		DBcommit();
	}

	if ('\0' != *error_msg)
		zabbix_log(LOG_LEVEL_WARNING, "%s", error_msg);

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
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
#ifdef HAVE_SNMP
			alarm(CONFIG_TIMEOUT);
			res = get_value_snmp(item, result);
			alarm(0);
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for SNMP checks was not compiled in"));
			res = NOTSUPPORTED;
#endif
			break;
		case ITEM_TYPE_IPMI:
#ifdef HAVE_OPENIPMI
			res = get_value_ipmi(item, result);
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for IPMI checks was not compiled in"));
			res = NOTSUPPORTED;
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
			alarm(CONFIG_TIMEOUT);
			res = get_value_db(item, result);
			alarm(0);
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
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for SSH checks was not compiled in"));
			res = NOTSUPPORTED;
#endif
			break;
		case ITEM_TYPE_TELNET:
			alarm(CONFIG_TIMEOUT);
			res = get_value_telnet(item, result);
			alarm(0);
			break;
		case ITEM_TYPE_JMX:
			alarm(CONFIG_TIMEOUT);
			res = get_value_java(ZBX_JAVA_GATEWAY_REQUEST_JMX, item, result);
			alarm(0);
			break;
		case ITEM_TYPE_CALCULATED:
			res = get_value_calculated(item, result);
			break;
		default:
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Not supported item type:%d", item->type));
			res = NOTSUPPORTED;
	}

	if (SUCCEED != res)
	{
		if (!ISSET_MSG(result))
			SET_MSG_RESULT(result, zbx_strdup(NULL, ZBX_NOTSUPPORTED));

		zabbix_log(LOG_LEVEL_DEBUG, "Item [%s:%s] error: %s", item->host.host, item->key_orig, result->msg);
	}

	/* remove formatting symbols from the end of the result */
	/* so it could be checked by "is_uint64" and "is_double" functions */
	/* when we try to get "int" or "float" values from "string" result */
	if (ISSET_STR(result))
		zbx_rtrim(result->str, ZBX_WHITESPACE);
	if (ISSET_TEXT(result))
		zbx_rtrim(result->text, ZBX_WHITESPACE);

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
	DC_ITEM		items[MAX_BUNCH_ITEMS];
	AGENT_RESULT	results[MAX_BUNCH_ITEMS];
	int		errcodes[MAX_BUNCH_ITEMS];
	zbx_timespec_t	timespec;
	int		i, num;
	char		*port = NULL, error[ITEM_ERROR_LEN_MAX];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	num = (SUCCEED == is_bunch_poller(poller_type) ? MAX_BUNCH_ITEMS : 1);
	num = DCconfig_get_poller_items(poller_type, items, num);

	if (0 == num)
		goto exit;

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
			errcodes[i] = NOTSUPPORTED;
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
				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&port, MACRO_TYPE_INTERFACE_PORT, NULL, 0);
				if (FAIL == is_ushort(port, &items[i].interface.port))
				{
					SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "Invalid port number [%s]",
								items[i].interface.port_orig));
					errcodes[i] = NETWORK_ERROR;
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

				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].snmpv3_securityname, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].snmpv3_authpassphrase, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].snmpv3_privpassphrase, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_SNMPv1:
			case ITEM_TYPE_SNMPv2c:
				ZBX_STRDUP(items[i].snmp_community, items[i].snmp_community_orig);
				ZBX_STRDUP(items[i].snmp_oid, items[i].snmp_oid_orig);

				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].snmp_community, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				if (SUCCEED != substitute_key_macros(&items[i].snmp_oid, &items[i].host.hostid, NULL,
						NULL, MACRO_TYPE_SNMP_OID, error, sizeof(error)))
				{
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
					errcodes[i] = NOTSUPPORTED;
					continue;
				}
				break;
			case ITEM_TYPE_SSH:
				ZBX_STRDUP(items[i].publickey, items[i].publickey_orig);
				ZBX_STRDUP(items[i].privatekey, items[i].privatekey_orig);

				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].publickey, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].privatekey, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_TELNET:
				ZBX_STRDUP(items[i].username, items[i].username_orig);
				ZBX_STRDUP(items[i].password, items[i].password_orig);

				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].username, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].password, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				/* break; is not missing here */
			case ITEM_TYPE_DB_MONITOR:
				substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i], NULL,
						&items[i].params, MACRO_TYPE_PARAMS_FIELD, NULL, 0);
				break;
			case ITEM_TYPE_JMX:
				ZBX_STRDUP(items[i].username, items[i].username_orig);
				ZBX_STRDUP(items[i].password, items[i].password_orig);

				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].username, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				substitute_simple_macros(NULL, NULL, &items[i].host.hostid, NULL, NULL, NULL,
						&items[i].password, MACRO_TYPE_ITEM_FIELD, NULL, 0);
				break;
		}
	}

	zbx_free(port);

	/* retrieve item values */
	if (SUCCEED != is_bunch_poller(poller_type))
	{
		if (SUCCEED == errcodes[0])
			errcodes[0] = get_value(&items[0], &results[0]);
	}
	else if (ZBX_POLLER_TYPE_JAVA == poller_type)
	{
		alarm(CONFIG_TIMEOUT);
		get_values_java(ZBX_JAVA_GATEWAY_REQUEST_JMX, items, results, errcodes, num);
		alarm(0);

	}

	zbx_timespec(&timespec);

	/* process item values */
	for (i = 0; i < num; i++)
	{
		switch (errcodes[i])
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
				activate_host(&items[i], &timespec);
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
				deactivate_host(&items[i], &timespec, results[i].msg);
				break;
			default:
				zbx_error("unknown response code returned: %d", errcodes[i]);
				assert(0);
		}

		if (SUCCEED == errcodes[i])
		{
			items[i].status = ITEM_STATUS_ACTIVE;
			dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, &results[i], &timespec,
					items[i].status, NULL, 0, NULL, 0, 0, 0, 0);
		}
		else if (NOTSUPPORTED == errcodes[i] || AGENT_ERROR == errcodes[i])
		{
			items[i].status = ITEM_STATUS_NOTSUPPORTED;
			dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, NULL, &timespec,
					items[i].status, results[i].msg, 0, NULL, 0, 0, 0, 0);
		}

		DCrequeue_items(&items[i].itemid, &items[i].status, &timespec.sec, &errcodes[i], 1);

		zbx_free(items[i].key);

		switch (items[i].type)
		{
			case ITEM_TYPE_SNMPv3:
				zbx_free(items[i].snmpv3_securityname);
				zbx_free(items[i].snmpv3_authpassphrase);
				zbx_free(items[i].snmpv3_privpassphrase);
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
				zbx_free(items[i].username);
				zbx_free(items[i].password);
				break;
			case ITEM_TYPE_JMX:
				zbx_free(items[i].username);
				zbx_free(items[i].password);
				break;
		}

		free_result(&results[i]);
	}

	DCconfig_clean_items(items, NULL, num);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, num);

	return num;
}

void	main_poller_loop(unsigned char poller_type)
{
	int	nextcheck, sleeptime, processed;
	double	sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_poller_loop() process_type:'%s' process_num:%d",
			get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [getting values]", get_process_type_string(process_type));

		sec = zbx_time();
		processed = get_values(poller_type);
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s #%d spent " ZBX_FS_DBL " seconds while updating %d values",
				get_process_type_string(process_type), process_num, sec, processed);

		nextcheck = DCconfig_get_poller_nextcheck(poller_type);
		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		zbx_sleep_loop(sleeptime);
	}
}
