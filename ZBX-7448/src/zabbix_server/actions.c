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
#include "log.h"
#include "zbxserver.h"

#include "actions.h"
#include "operations.h"

/******************************************************************************
 *                                                                            *
 * Function: check_trigger_condition                                          *
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - trigger event to check                                 *
 *                                  (event->source == EVENT_SOURCE_TRIGGERS)  *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	check_trigger_condition(const DB_EVENT *event, DB_CONDITION *condition)
{
	const char	*__function_name = "check_trigger_condition";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	condition_value;
	char		*tmp_str = NULL;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CONDITION_TYPE_HOST_GROUP == condition->conditiontype)
	{
		ZBX_STR2UINT64(condition_value, condition->value);

		result = DBselect(
				"select distinct hg.groupid"
				" from hosts_groups hg,hosts h,items i,functions f,triggers t"
				" where hg.hostid=h.hostid"
					" and h.hostid=i.hostid"
					" and i.itemid=f.itemid"
					" and f.triggerid=t.triggerid"
					" and t.triggerid=" ZBX_FS_UI64
					" and hg.groupid=" ZBX_FS_UI64,
				event->objectid,
				condition_value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (NULL != DBfetch(result))
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (NULL == DBfetch(result))
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
		DBfree_result(result);
	}
	else if (CONDITION_TYPE_HOST_TEMPLATE == condition->conditiontype)
	{
		zbx_uint64_t	hostid, triggerid;

		ZBX_STR2UINT64(condition_value, condition->value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
			case CONDITION_OPERATOR_NOT_EQUAL:
				triggerid = event->objectid;

				/* use parent trigger ID for generated triggers */
				result = DBselect(
						"select parent_triggerid"
						" from trigger_discovery"
						" where triggerid=" ZBX_FS_UI64,
						triggerid);

				if (NULL != (row = DBfetch(result)))
				{
					ZBX_STR2UINT64(triggerid, row[0]);

					zabbix_log(LOG_LEVEL_DEBUG, "%s() check host template condition,"
							" selecting parent triggerid:" ZBX_FS_UI64,
							__function_name, triggerid);
				}
				DBfree_result(result);

				do
				{
					result = DBselect(
							"select distinct i.hostid,t.templateid"
							" from items i,functions f,triggers t"
							" where i.itemid=f.itemid"
								" and f.triggerid=t.templateid"
								" and t.triggerid=" ZBX_FS_UI64,
							triggerid);

					triggerid = 0;

					if (NULL != (row = DBfetch(result)))
					{
						ZBX_STR2UINT64(hostid, row[0]);
						ZBX_STR2UINT64(triggerid, row[1]);

						if (hostid == condition_value)
						{
							ret = SUCCEED;
							break;
						}
					}
					DBfree_result(result);
				}
				while (SUCCEED != ret && 0 != triggerid);

				if (CONDITION_OPERATOR_NOT_EQUAL == condition->operator)
					ret = (SUCCEED == ret) ? FAIL : SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_HOST == condition->conditiontype)
	{
		ZBX_STR2UINT64(condition_value, condition->value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
			case CONDITION_OPERATOR_NOT_EQUAL:
				result = DBselect(
						"select distinct i.hostid"
						" from items i,functions f,triggers t"
						" where i.itemid=f.itemid"
							" and f.triggerid=t.triggerid"
							" and t.triggerid=" ZBX_FS_UI64
							" and i.hostid=" ZBX_FS_UI64,
						event->objectid,
						condition_value);

				if (NULL != DBfetch(result))
					ret = SUCCEED;
				DBfree_result(result);

				if (CONDITION_OPERATOR_NOT_EQUAL == condition->operator)
					ret = (SUCCEED == ret) ? FAIL : SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_TRIGGER == condition->conditiontype)
	{
		zbx_uint64_t	triggerid;

		ZBX_STR2UINT64(condition_value, condition->value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (event->objectid == condition_value)
				{
					ret = SUCCEED;
				}
				else
				{
					/* processing of templated triggers */

					for (triggerid = event->objectid; 0 != triggerid && FAIL == ret;)
					{
						result = DBselect(
								"select templateid"
								" from triggers"
								" where triggerid=" ZBX_FS_UI64,
								triggerid);

						if (NULL == (row = DBfetch(result)))
							triggerid = 0;
						else
						{
							ZBX_DBROW2UINT64(triggerid, row[0]);
							if (triggerid == condition_value)
								ret = SUCCEED;
						}
						DBfree_result(result);
					}
				}

				if (CONDITION_OPERATOR_NOT_EQUAL == condition->operator)
					ret = (SUCCEED == ret) ? FAIL : SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_TRIGGER_NAME == condition->conditiontype)
	{
		tmp_str = zbx_strdup(tmp_str, event->trigger.description);

		substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, NULL,
				&tmp_str, MACRO_TYPE_TRIGGER_DESCRIPTION, NULL, 0);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_LIKE:
				if (NULL != strstr(tmp_str, condition->value))
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_LIKE:
				if (NULL == strstr(tmp_str, condition->value))
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
		zbx_free(tmp_str);
	}
	else if (CONDITION_TYPE_TRIGGER_SEVERITY == condition->conditiontype)
	{
		condition_value = atoi(condition->value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (event->trigger.priority == condition_value)
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (event->trigger.priority != condition_value)
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_MORE_EQUAL:
				if (event->trigger.priority >= condition_value)
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_LESS_EQUAL:
				if (event->trigger.priority <= condition_value)
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_TRIGGER_VALUE == condition->conditiontype)
	{
		condition_value = atoi(condition->value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (event->value == condition_value)
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_TIME_PERIOD == condition->conditiontype)
	{
		switch (condition->operator)
		{
			case CONDITION_OPERATOR_IN:
				if (SUCCEED == check_time_period(condition->value, (time_t)NULL))
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_IN:
				if (FAIL == check_time_period(condition->value, (time_t)NULL))
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_MAINTENANCE == condition->conditiontype)
	{
		switch (condition->operator)
		{
			case CONDITION_OPERATOR_IN:
				result = DBselect(
						"select count(*)"
						" from hosts h,items i,functions f,triggers t"
						" where h.hostid=i.hostid"
							" and h.maintenance_status=%d"
							" and i.itemid=f.itemid"
							" and f.triggerid=t.triggerid"
							" and t.triggerid=" ZBX_FS_UI64,
						HOST_MAINTENANCE_STATUS_ON,
						event->objectid);

				if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]) && 0 != atoi(row[0]))
					ret = SUCCEED;
				DBfree_result(result);
				break;
			case CONDITION_OPERATOR_NOT_IN:
				result = DBselect(
						"select count(*)"
						" from hosts h,items i,functions f,triggers t"
						" where h.hostid=i.hostid"
							" and h.maintenance_status=%d"
							" and i.itemid=f.itemid"
							" and f.triggerid=t.triggerid"
							" and t.triggerid=" ZBX_FS_UI64,
						HOST_MAINTENANCE_STATUS_OFF,
						event->objectid);

				if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]) && 0 != atoi(row[0]))
					ret = SUCCEED;
				DBfree_result(result);
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_EVENT_ACKNOWLEDGED == condition->conditiontype)
	{
		result = DBselect(
				"select acknowledged"
				" from events"
				" where acknowledged=%d"
					" and eventid=" ZBX_FS_UI64,
				atoi(condition->value),
				event->eventid);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (NULL != (row = DBfetch(result)))
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
		DBfree_result(result);
	}
	else if (CONDITION_TYPE_APPLICATION == condition->conditiontype)
	{
		result = DBselect(
				"select distinct a.name"
				" from applications a,items_applications i,functions f,triggers t"
				" where a.applicationid=i.applicationid"
					" and i.itemid=f.itemid"
					" and f.triggerid=t.triggerid"
					" and t.triggerid=" ZBX_FS_UI64,
				event->objectid);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				while (NULL != (row = DBfetch(result)))
				{
					if (0 == strcmp(row[0], condition->value))
					{
						ret = SUCCEED;
						break;
					}
				}
				break;
			case CONDITION_OPERATOR_LIKE:
				while (NULL != (row = DBfetch(result)))
				{
					if (NULL != strstr(row[0], condition->value))
					{
						ret = SUCCEED;
						break;
					}
				}
				break;
			case CONDITION_OPERATOR_NOT_LIKE:
				ret = SUCCEED;
				while (NULL != (row = DBfetch(result)))
				{
					if (NULL != strstr(row[0], condition->value))
					{
						ret = FAIL;
						break;
					}
				}
				break;
			default:
				ret = NOTSUPPORTED;
		}
		DBfree_result(result);
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported condition type [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->conditiontype, condition->conditionid);
	}

	if (NOTSUPPORTED == ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->operator, condition->conditionid);
		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_discovery_condition                                        *
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - discovery event to check                               *
 *                                 (event->source == EVENT_SOURCE_DISCOVERY)  *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	check_discovery_condition(const DB_EVENT *event, DB_CONDITION *condition)
{
	const char	*__function_name = "check_discovery_condition";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	condition_value;
	int		tmp_int, now;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (CONDITION_TYPE_DRULE == condition->conditiontype)
	{
		ZBX_STR2UINT64(condition_value, condition->value);

		if (EVENT_OBJECT_DHOST == event->object)
		{
			result = DBselect(
					"select druleid"
					" from dhosts"
					" where druleid=" ZBX_FS_UI64
						" and dhostid=" ZBX_FS_UI64,
					condition_value,
					event->objectid);
		}
		else	/* EVENT_OBJECT_DSERVICE */
		{
			result = DBselect(
					"select h.druleid"
					" from dhosts h,dservices s"
					" where h.dhostid=s.dhostid"
						" and h.druleid=" ZBX_FS_UI64
						" and s.dserviceid=" ZBX_FS_UI64,
					condition_value,
					event->objectid);
		}

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (NULL != DBfetch(result))
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (NULL == DBfetch(result))
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
		DBfree_result(result);
	}
	else if (CONDITION_TYPE_DCHECK == condition->conditiontype)
	{
		if (EVENT_OBJECT_DSERVICE == event->object)
		{
			ZBX_STR2UINT64(condition_value, condition->value);

			result = DBselect(
					"select dcheckid"
					" from dservices"
					" where dcheckid=" ZBX_FS_UI64
						" and dserviceid=" ZBX_FS_UI64,
					condition_value,
					event->objectid);

			switch (condition->operator)
			{
				case CONDITION_OPERATOR_EQUAL:
					if (NULL != DBfetch(result))
						ret = SUCCEED;
					break;
				case CONDITION_OPERATOR_NOT_EQUAL:
					if (NULL == DBfetch(result))
						ret = SUCCEED;
					break;
				default:
					ret = NOTSUPPORTED;
			}
			DBfree_result(result);
		}
	}
	else if (CONDITION_TYPE_DOBJECT == condition->conditiontype)
	{
		condition_value = atoi(condition->value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (event->object == condition_value)
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_PROXY == condition->conditiontype)
	{
		ZBX_STR2UINT64(condition_value, condition->value);

		if (EVENT_OBJECT_DHOST == event->object)
		{
			result = DBselect(
					"select r.proxy_hostid"
					" from drules r,dhosts h"
					" where r.druleid=h.druleid"
						" and r.proxy_hostid=" ZBX_FS_UI64
						" and h.dhostid=" ZBX_FS_UI64,
					condition_value,
					event->objectid);
		}
		else	/* EVENT_OBJECT_DSERVICE */
		{
			result = DBselect(
					"select r.proxy_hostid"
					" from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid"
						" and h.dhostid=s.dhostid"
						" and r.proxy_hostid=" ZBX_FS_UI64
						" and s.dserviceid=" ZBX_FS_UI64,
					condition_value,
					event->objectid);
		}

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (NULL != DBfetch(result))
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (NULL == DBfetch(result))
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
		DBfree_result(result);
	}
	else if (CONDITION_TYPE_DVALUE == condition->conditiontype)
	{
		if (EVENT_OBJECT_DSERVICE == event->object)
		{
			result = DBselect(
					"select value"
					" from dservices"
					" where dserviceid=" ZBX_FS_UI64,
					event->objectid);

			if (NULL != (row = DBfetch(result)))
			{
				switch (condition->operator)
				{
					case CONDITION_OPERATOR_EQUAL:
						if (0 == strcmp(condition->value, row[0]))
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_NOT_EQUAL:
						if (0 != strcmp(condition->value, row[0]))
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_MORE_EQUAL:
						if (0 <= strcmp(row[0], condition->value))
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_LESS_EQUAL:
						if (0 >= strcmp(row[0], condition->value))
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_LIKE:
						if (NULL != strstr(row[0], condition->value))
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_NOT_LIKE:
						if (NULL == strstr(row[0], condition->value))
							ret = SUCCEED;
						break;
					default:
						ret = NOTSUPPORTED;
				}
			}
			DBfree_result(result);
		}
	}
	else if (CONDITION_TYPE_DHOST_IP == condition->conditiontype)
	{
		if (EVENT_OBJECT_DHOST == event->object)
		{
			result = DBselect(
					"select distinct ip"
					" from dservices"
					" where dhostid=" ZBX_FS_UI64,
					event->objectid);
		}
		else
		{
			result = DBselect(
					"select ip"
					" from dservices"
					" where dserviceid=" ZBX_FS_UI64,
					event->objectid);
		}

		while (NULL != (row = DBfetch(result)) && FAIL == ret)
		{
			switch (condition->operator)
			{
				case CONDITION_OPERATOR_EQUAL:
					if (SUCCEED == ip_in_list(condition->value, row[0]))
						ret = SUCCEED;
					break;
				case CONDITION_OPERATOR_NOT_EQUAL:
					if (SUCCEED != ip_in_list(condition->value, row[0]))
						ret = SUCCEED;
					break;
				default:
					ret = NOTSUPPORTED;
			}
		}
		DBfree_result(result);
	}
	else if (CONDITION_TYPE_DSERVICE_TYPE == condition->conditiontype)
	{
		if (EVENT_OBJECT_DSERVICE == event->object)
		{
			condition_value = atoi(condition->value);

			result = DBselect(
					"select type"
					" from dservices"
					" where dserviceid=" ZBX_FS_UI64,
					event->objectid);

			if (NULL != (row = DBfetch(result)))
			{
				tmp_int = atoi(row[0]);

				switch (condition->operator)
				{
					case CONDITION_OPERATOR_EQUAL:
						if (condition_value == tmp_int)
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_NOT_EQUAL:
						if (condition_value != tmp_int)
							ret = SUCCEED;
						break;
					default:
						ret = NOTSUPPORTED;
				}
			}
			DBfree_result(result);
		}
	}
	else if (CONDITION_TYPE_DSTATUS == condition->conditiontype)
	{
		condition_value = atoi(condition->value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (condition_value == event->value)
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (condition_value != event->value)
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_DUPTIME == condition->conditiontype)
	{
		condition_value = atoi(condition->value);

		if (EVENT_OBJECT_DHOST == event->object)
		{
			result = DBselect(
					"select status,lastup,lastdown"
					" from dhosts"
					" where dhostid=" ZBX_FS_UI64,
					event->objectid);
		}
		else
		{
			result = DBselect(
					"select status,lastup,lastdown"
					" from dservices"
					" where dserviceid=" ZBX_FS_UI64,
					event->objectid);
		}

		if (NULL != (row = DBfetch(result)))
		{
			tmp_int = DOBJECT_STATUS_UP == atoi(row[0]) ? atoi(row[1]) : atoi(row[2]);
			now = time(NULL);

			switch (condition->operator)
			{
				case CONDITION_OPERATOR_LESS_EQUAL:
					if (0 != tmp_int && (now - tmp_int) <= condition_value)
						ret = SUCCEED;
					break;
				case CONDITION_OPERATOR_MORE_EQUAL:
					if (0 != tmp_int && (now - tmp_int) >= condition_value)
						ret = SUCCEED;
					break;
				default:
					ret = NOTSUPPORTED;
			}
		}
		DBfree_result(result);
	}
	else if (CONDITION_TYPE_DSERVICE_PORT == condition->conditiontype)
	{
		if (EVENT_OBJECT_DSERVICE == event->object)
		{
			result = DBselect(
					"select port"
					" from dservices"
					" where dserviceid=" ZBX_FS_UI64,
					event->objectid);

			if (NULL != (row = DBfetch(result)))
			{
				switch (condition->operator)
				{
					case CONDITION_OPERATOR_EQUAL:
						if (SUCCEED == int_in_list(condition->value, atoi(row[0])))
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_NOT_EQUAL:
						if (SUCCEED != int_in_list(condition->value, atoi(row[0])))
							ret = SUCCEED;
						break;
					default:
						ret = NOTSUPPORTED;
				}
			}
			DBfree_result(result);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported condition type [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->conditiontype, condition->conditionid);
	}

	if (NOTSUPPORTED == ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->operator, condition->conditionid);
		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_auto_registration_condition                                *
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - auto registration event to check                       *
 *                         (event->source == EVENT_SOURCE_AUTO_REGISTRATION)  *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	check_auto_registration_condition(const DB_EVENT *event, DB_CONDITION *condition)
{
	const char	*__function_name = "check_auto_registration_condition";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	condition_value, id;
	int		ret = FAIL;
	const char	*condition_field;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	switch (condition->conditiontype)
	{
		case CONDITION_TYPE_HOST_NAME:
		case CONDITION_TYPE_HOST_METADATA:
			if (CONDITION_TYPE_HOST_NAME == condition->conditiontype)
				condition_field = "host";
			else
				condition_field = "host_metadata";

			result = DBselect(
					"select %s"
					" from autoreg_host"
					" where autoreg_hostid=" ZBX_FS_UI64,
					condition_field, event->objectid);

			if (NULL != (row = DBfetch(result)))
			{
				switch (condition->operator)
				{
					case CONDITION_OPERATOR_LIKE:
						if (NULL != strstr(row[0], condition->value))
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_NOT_LIKE:
						if (NULL == strstr(row[0], condition->value))
							ret = SUCCEED;
						break;
					default:
						ret = NOTSUPPORTED;
				}
			}
			DBfree_result(result);

			break;
		case CONDITION_TYPE_PROXY:
			ZBX_STR2UINT64(condition_value, condition->value);

			result = DBselect(
					"select proxy_hostid"
					" from autoreg_host"
					" where autoreg_hostid=" ZBX_FS_UI64,
					event->objectid);

			if (NULL != (row = DBfetch(result)))
			{
				ZBX_DBROW2UINT64(id, row[0]);

				switch (condition->operator)
				{
					case CONDITION_OPERATOR_EQUAL:
						if (id == condition_value)
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_NOT_EQUAL:
						if (id != condition_value)
							ret = SUCCEED;
						break;
					default:
						ret = NOTSUPPORTED;
				}
			}
			DBfree_result(result);

			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unsupported condition type [%d] for condition id [" ZBX_FS_UI64 "]",
					(int)condition->conditiontype, condition->conditionid);
	}

	if (NOTSUPPORTED == ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->operator, condition->conditionid);
		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_internal_condition                                         *
 *                                                                            *
 * Purpose: check if internal event matches single condition                  *
 *                                                                            *
 * Parameters: event     - [IN] trigger event to check                        *
 *             condition - [IN] condition for matching                        *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static int	check_internal_condition(const DB_EVENT *event, DB_CONDITION *condition)
{
	const char	*__function_name = "check_internal_condition";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	condition_value;
	int		ret = FAIL;
	char		sql[256];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (EVENT_OBJECT_TRIGGER != event->object && EVENT_OBJECT_ITEM != event->object &&
			EVENT_OBJECT_LLDRULE != event->object)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported event object [%d] for condition id [" ZBX_FS_UI64 "]",
				event->object, condition->conditionid);
		goto out;
	}

	if (CONDITION_TYPE_EVENT_TYPE == condition->conditiontype)
	{
		condition_value = atoi(condition->value);

		switch (condition_value)
		{
			case EVENT_TYPE_ITEM_NORMAL:
				if (EVENT_OBJECT_ITEM == event->object && ITEM_STATE_NORMAL == event->value)
					ret = SUCCEED;
				break;
			case EVENT_TYPE_ITEM_NOTSUPPORTED:
				if (EVENT_OBJECT_ITEM == event->object && ITEM_STATE_NOTSUPPORTED == event->value)
					ret = SUCCEED;
				break;
			case EVENT_TYPE_TRIGGER_NORMAL:
				if (EVENT_OBJECT_TRIGGER == event->object && TRIGGER_STATE_NORMAL == event->value)
					ret = SUCCEED;
				break;
			case EVENT_TYPE_TRIGGER_UNKNOWN:
				if (EVENT_OBJECT_TRIGGER == event->object && TRIGGER_STATE_UNKNOWN == event->value)
					ret = SUCCEED;
				break;
			case EVENT_TYPE_LLDRULE_NORMAL:
				if (EVENT_OBJECT_LLDRULE == event->object && ITEM_STATE_NORMAL == event->value)
					ret = SUCCEED;
				break;
			case EVENT_TYPE_LLDRULE_NOTSUPPORTED:
				if (EVENT_OBJECT_LLDRULE == event->object && ITEM_STATE_NOTSUPPORTED == event->value)
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_HOST_GROUP == condition->conditiontype)
	{
		ZBX_STR2UINT64(condition_value, condition->value);

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				zbx_snprintf(sql, sizeof(sql),
						"select null"
						" from hosts_groups hg,hosts h,items i,functions f,triggers t"
						" where hg.hostid=h.hostid"
							" and h.hostid=i.hostid"
							" and i.itemid=f.itemid"
							" and f.triggerid=t.triggerid"
							" and t.triggerid=" ZBX_FS_UI64
							" and hg.groupid=" ZBX_FS_UI64,
						event->objectid, condition_value);
				break;
			default:
				zbx_snprintf(sql, sizeof(sql),
						"select null"
						" from hosts_groups hg,hosts h,items i"
						" where hg.hostid=h.hostid"
							" and h.hostid=i.hostid"
							" and i.itemid=" ZBX_FS_UI64
							" and hg.groupid=" ZBX_FS_UI64,
						event->objectid, condition_value);
		}

		result = DBselectN(sql, 1);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (NULL != DBfetch(result))
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (NULL == DBfetch(result))
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
		DBfree_result(result);
	}
	else if (CONDITION_TYPE_HOST_TEMPLATE == condition->conditiontype)
	{
		zbx_uint64_t	hostid, objectid;

		ZBX_STR2UINT64(condition_value, condition->value);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
			case CONDITION_OPERATOR_NOT_EQUAL:
				objectid = event->objectid;

				/* use parent object ID for generated objects */
				switch (event->object)
				{
					case EVENT_OBJECT_TRIGGER:
						result = DBselect(
								"select parent_triggerid"
								" from trigger_discovery"
								" where triggerid=" ZBX_FS_UI64,
								objectid);
						break;
					default:
						result = DBselect(
								"select id.parent_itemid"
								" from item_discovery id,items i"
								" where id.itemid=i.itemid"
									" and i.itemid=" ZBX_FS_UI64
									" and i.flags=%d",
								objectid, ZBX_FLAG_DISCOVERY_CREATED);
				}

				if (NULL != (row = DBfetch(result)))
				{
					ZBX_STR2UINT64(objectid, row[0]);

					zabbix_log(LOG_LEVEL_DEBUG, "%s() check host template condition,"
							" selecting parent objectid:" ZBX_FS_UI64,
							__function_name, objectid);
				}
				DBfree_result(result);

				do
				{
					switch (event->object)
					{
						case EVENT_OBJECT_TRIGGER:
							result = DBselect(
									"select distinct i.hostid,t.templateid"
									" from items i,functions f,triggers t"
									" where i.itemid=f.itemid"
										" and f.triggerid=t.templateid"
										" and t.triggerid=" ZBX_FS_UI64,
									objectid);
							break;
						default:
							result = DBselect(
									"select t.hostid,t.itemid"
									" from items t,items h"
									" where t.itemid=h.templateid"
										" and h.itemid=" ZBX_FS_UI64,
									objectid);
					}

					objectid = 0;

					while (NULL != (row = DBfetch(result)))
					{
						ZBX_STR2UINT64(hostid, row[0]);
						ZBX_STR2UINT64(objectid, row[1]);

						if (hostid == condition_value)
						{
							ret = SUCCEED;
							break;
						}
					}
					DBfree_result(result);
				}
				while (SUCCEED != ret && 0 != objectid);

				if (CONDITION_OPERATOR_NOT_EQUAL == condition->operator)
					ret = (SUCCEED == ret) ? FAIL : SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
	}
	else if (CONDITION_TYPE_HOST == condition->conditiontype)
	{
		ZBX_STR2UINT64(condition_value, condition->value);

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				zbx_snprintf(sql, sizeof(sql),
						"select null"
						" from items i,functions f,triggers t"
						" where i.itemid=f.itemid"
							" and f.triggerid=t.triggerid"
							" and t.triggerid=" ZBX_FS_UI64
							" and i.hostid=" ZBX_FS_UI64,
						event->objectid, condition_value);
				break;
			default:
				zbx_snprintf(sql, sizeof(sql),
						"select null"
						" from items"
						" where itemid=" ZBX_FS_UI64
							" and hostid=" ZBX_FS_UI64,
						event->objectid, condition_value);
		}

		result = DBselectN(sql, 1);

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (NULL != DBfetch(result))
					ret = SUCCEED;
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (NULL == DBfetch(result))
					ret = SUCCEED;
				break;
			default:
				ret = NOTSUPPORTED;
		}
		DBfree_result(result);
	}
	else if (CONDITION_TYPE_APPLICATION == condition->conditiontype)
	{
		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				result = DBselect(
						"select distinct a.name"
						" from applications a,items_applications i,functions f,triggers t"
						" where a.applicationid=i.applicationid"
							" and i.itemid=f.itemid"
							" and f.triggerid=t.triggerid"
							" and t.triggerid=" ZBX_FS_UI64,
						event->objectid);
				break;
			default:
				result = DBselect(
						"select distinct a.name"
						" from applications a,items_applications i"
						" where a.applicationid=i.applicationid"
							" and i.itemid=" ZBX_FS_UI64,
						event->objectid);
		}

		switch (condition->operator)
		{
			case CONDITION_OPERATOR_EQUAL:
				while (NULL != (row = DBfetch(result)))
				{
					if (0 == strcmp(row[0], condition->value))
					{
						ret = SUCCEED;
						break;
					}
				}
				break;
			case CONDITION_OPERATOR_LIKE:
				while (NULL != (row = DBfetch(result)))
				{
					if (NULL != strstr(row[0], condition->value))
					{
						ret = SUCCEED;
						break;
					}
				}
				break;
			case CONDITION_OPERATOR_NOT_LIKE:
				ret = SUCCEED;
				while (NULL != (row = DBfetch(result)))
				{
					if (NULL != strstr(row[0], condition->value))
					{
						ret = FAIL;
						break;
					}
				}
				break;
			default:
				ret = NOTSUPPORTED;
		}
		DBfree_result(result);
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported condition type [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->conditiontype, condition->conditionid);
	}

	if (NOTSUPPORTED == ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->operator, condition->conditionid);
		ret = FAIL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_action_condition                                           *
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - event to check                                         *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	check_action_condition(const DB_EVENT *event, DB_CONDITION *condition)
{
	const char	*__function_name = "check_action_condition";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64 " conditionid:" ZBX_FS_UI64 " cond.value:'%s'",
			__function_name, condition->actionid, condition->conditionid, condition->value);

	switch (event->source)
	{
		case EVENT_SOURCE_TRIGGERS:
			ret = check_trigger_condition(event, condition);
			break;
		case EVENT_SOURCE_DISCOVERY:
			ret = check_discovery_condition(event, condition);
			break;
		case EVENT_SOURCE_AUTO_REGISTRATION:
			ret = check_auto_registration_condition(event, condition);
			break;
		case EVENT_SOURCE_INTERNAL:
			ret = check_internal_condition(event, condition);
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unsupported event source [%d] for condition id [" ZBX_FS_UI64 "]",
					event->source, condition->conditionid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_action_conditions                                          *
 *                                                                            *
 * Purpose: check if actions have to be processed for the event               *
 *          (check all conditions of the action)                              *
 *                                                                            *
 * Parameters: event - event to check                                         *
 *             actionid - action ID for matching                              *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	check_action_conditions(const DB_EVENT *event, zbx_uint64_t actionid, unsigned char evaltype)
{
	const char	*__function_name = "check_action_conditions";

	DB_RESULT	result;
	DB_ROW		row;
	DB_CONDITION	condition;
	int		cond, exit = 0, ret = SUCCEED;	/* SUCCEED required for CONDITION_EVAL_TYPE_AND_OR */
	unsigned char	old_type = 0xff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64, __function_name, actionid);

	result = DBselect(
			"select conditionid,conditiontype,operator,value"
			" from conditions"
			" where actionid=" ZBX_FS_UI64
			" order by conditiontype",
			actionid);

	while (NULL != (row = DBfetch(result)) && 0 == exit)
	{
		ZBX_STR2UINT64(condition.conditionid, row[0]);
		condition.actionid = actionid;
		condition.conditiontype = (unsigned char)atoi(row[1]);
		condition.operator = (unsigned char)atoi(row[2]);
		condition.value = row[3];

		switch (evaltype)
		{
			case CONDITION_EVAL_TYPE_AND_OR:
				if (old_type == condition.conditiontype)	/* OR conditions */
				{
					if (SUCCEED == check_action_condition(event, &condition))
						ret = SUCCEED;
				}
				else						/* AND conditions */
				{
					if (FAIL == ret)	/* break if PREVIOUS AND condition is FALSE */
						exit = 1;
					else if (FAIL == check_action_condition(event, &condition))
						ret = FAIL;
				}

				old_type = condition.conditiontype;

				break;
			case CONDITION_EVAL_TYPE_AND:
				cond = check_action_condition(event, &condition);

				if (FAIL == cond)	/* break if any AND condition is FALSE */
				{
					ret = FAIL;
					exit = 1;
				}
				else
					ret = SUCCEED;

				break;
			case CONDITION_EVAL_TYPE_OR:
				cond = check_action_condition(event, &condition);

				if (SUCCEED == cond)	/* break if any OR condition is TRUE */
				{
					ret = SUCCEED;
					exit = 1;
				}
				else
					ret = FAIL;

				break;
			default:
				ret = FAIL;
				exit = 1;
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: execute_operations                                               *
 *                                                                            *
 * Purpose: execute all operations linked to the action                       *
 *                                                                            *
 * Parameters: action - action to execute operations for                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	execute_operations(const DB_EVENT *event, zbx_uint64_t actionid)
{
	const char		*__function_name = "execute_operations";

	DB_RESULT		result;
	DB_ROW			row;
	unsigned char		operationtype;
	zbx_uint64_t		groupid, templateid;
	zbx_vector_uint64_t	lnk_templateids, del_templateids,
				new_groupids, del_groupids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64, __function_name, actionid);

	zbx_vector_uint64_create(&lnk_templateids);
	zbx_vector_uint64_create(&del_templateids);
	zbx_vector_uint64_create(&new_groupids);
	zbx_vector_uint64_create(&del_groupids);

	result = DBselect(
			"select o.operationtype,g.groupid,t.templateid"
			" from operations o"
				" left join opgroup g on g.operationid=o.operationid"
				" left join optemplate t on t.operationid=o.operationid"
			" where o.actionid=" ZBX_FS_UI64,
			actionid);

	while (NULL != (row = DBfetch(result)))
	{
		operationtype = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(groupid, row[1]);
		ZBX_DBROW2UINT64(templateid, row[2]);

		switch (operationtype)
		{
			case OPERATION_TYPE_HOST_ADD:
				op_host_add(event);
				break;
			case OPERATION_TYPE_HOST_REMOVE:
				op_host_del(event);
				break;
			case OPERATION_TYPE_HOST_ENABLE:
				op_host_enable(event);
				break;
			case OPERATION_TYPE_HOST_DISABLE:
				op_host_disable(event);
				break;
			case OPERATION_TYPE_GROUP_ADD:
				if (0 != groupid)
					zbx_vector_uint64_append(&new_groupids, groupid);
				break;
			case OPERATION_TYPE_GROUP_REMOVE:
				if (0 != groupid)
					zbx_vector_uint64_append(&del_groupids, groupid);
				break;
			case OPERATION_TYPE_TEMPLATE_ADD:
				if (0 != templateid)
					zbx_vector_uint64_append(&lnk_templateids, templateid);
				break;
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				if (0 != templateid)
					zbx_vector_uint64_append(&del_templateids, templateid);
				break;
			default:
				;
		}
	}
	DBfree_result(result);

	if (0 != lnk_templateids.values_num)
	{
		zbx_vector_uint64_sort(&lnk_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&lnk_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		op_template_add(event, &lnk_templateids);
	}

	if (0 != del_templateids.values_num)
	{
		zbx_vector_uint64_sort(&del_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&del_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		op_template_del(event, &del_templateids);
	}

	if (0 != new_groupids.values_num)
	{
		zbx_vector_uint64_sort(&new_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&new_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		op_groups_add(event, &new_groupids);
	}

	if (0 != del_groupids.values_num)
	{
		zbx_vector_uint64_sort(&del_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&del_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		op_groups_del(event, &del_groupids);
	}

	zbx_vector_uint64_destroy(&del_groupids);
	zbx_vector_uint64_destroy(&new_groupids);
	zbx_vector_uint64_destroy(&del_templateids);
	zbx_vector_uint64_destroy(&lnk_templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	escalation_add_values(zbx_db_insert_t *db_insert, int escalations_num, zbx_uint64_t actionid,
		const DB_EVENT *event, unsigned char recovery)
{
	zbx_uint64_t	escalationid, triggerid = 0, itemid = 0, eventid = 0, r_eventid = 0;

	if (0 == escalations_num)
	{
		zbx_db_insert_prepare(db_insert, "escalations", "escalationid", "actionid", "status", "triggerid",
				"itemid", "eventid", "r_eventid", NULL);
	}

	escalationid = DBget_maxid("escalations");

	switch (event->object)
	{
		case EVENT_OBJECT_TRIGGER:
			triggerid = event->objectid;
			break;
		case EVENT_OBJECT_ITEM:
		case EVENT_OBJECT_LLDRULE:
			itemid = event->objectid;
			break;
	}

	if (0 == recovery)
		eventid = event->eventid;
	else
		r_eventid = event->eventid;

	zbx_db_insert_add_values(db_insert, escalationid, actionid, (int)ESCALATION_STATUS_ACTIVE, triggerid, itemid,
			eventid, r_eventid);
}

/******************************************************************************
 *                                                                            *
 * Function: process_actions                                                  *
 *                                                                            *
 * Purpose: process all actions of each event in a list                       *
 *                                                                            *
 * Parameters: events     - [IN] events to apply actions for                  *
 *             events_num - [IN] number of events                             *
 *                                                                            *
 ******************************************************************************/
void	process_actions(const DB_EVENT *events, size_t events_num)
{
	const char			*__function_name = "process_actions";

	DB_RESULT			result;
	DB_ROW				row;
	zbx_uint64_t			actionid;
	unsigned char			evaltype;
	size_t				i;
	zbx_vector_uint64_t		rec_actionids;	/* actionids of possible recovery events */
	zbx_vector_uint64_pair_t	rec_mapping;	/* which action is possibly recovered by which event */
	const DB_EVENT			*event;
	zbx_db_insert_t			db_insert;
	int				escalations_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)events_num);

	zbx_vector_uint64_create(&rec_actionids);
	zbx_vector_uint64_pair_create(&rec_mapping);

	for (i = 0; i < events_num; i++)
	{
		event = &events[i];

		result = DBselect("select actionid,evaltype"
				" from actions"
				" where status=%d"
					" and eventsource=%d",
				ACTION_STATUS_ACTIVE, event->source);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(actionid, row[0]);
			evaltype = (unsigned char)atoi(row[1]);

			if (SUCCEED == check_action_conditions(event, actionid, evaltype))
			{
				escalation_add_values(&db_insert, escalations_num++, actionid, event, 0);

				if (EVENT_SOURCE_DISCOVERY == event->source ||
						EVENT_SOURCE_AUTO_REGISTRATION == event->source)
				{
					execute_operations(event, actionid);
				}
			}
			else if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
			{
				/* Action conditions evaluated to false, but it could be a recovery */
				/* event for this action. Remember this and check escalations later. */

				zbx_uint64_pair_t	pair;

				pair.first = actionid;
				pair.second = (zbx_uint64_t)i;

				zbx_vector_uint64_pair_append(&rec_mapping, pair);

				zbx_vector_uint64_append(&rec_actionids, actionid);
			}
		}
		DBfree_result(result);
	}

	if (0 != rec_actionids.values_num)
	{
		char		*sql = NULL;
		size_t		sql_alloc = 0, sql_offset = 0;
		zbx_uint64_t	triggerid, itemid;

		zbx_vector_uint64_sort(&rec_actionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&rec_actionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		/* list of ongoing escalations matching actionids collected before */
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select actionid,triggerid,itemid"
				" from escalations"
				" where eventid is not null"
					" and");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "actionid",
				rec_actionids.values, rec_actionids.values_num);
		result = DBselect("%s", sql);
		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(actionid, row[0]);
			ZBX_DBROW2UINT64(triggerid, row[1]);
			ZBX_DBROW2UINT64(itemid, row[2]);

			for (i = 0; i < rec_mapping.values_num; i++)
			{
				if (actionid != rec_mapping.values[i].first)
					continue;

				event = &events[(int)rec_mapping.values[i].second];

				/* only add recovery if it matches event */
				switch (event->source)
				{
					case EVENT_SOURCE_TRIGGERS:
						if (triggerid != event->objectid)
							continue;
						break;
					case EVENT_SOURCE_INTERNAL:
						switch (event->object)
						{
							case EVENT_OBJECT_TRIGGER:
								if (triggerid != event->objectid)
									continue;
								break;
							case EVENT_OBJECT_ITEM:
							case EVENT_OBJECT_LLDRULE:
								if (itemid != event->objectid)
									continue;
								break;
							default:
								THIS_SHOULD_NEVER_HAPPEN;
						}

						break;
					default:
						continue;
				}

				escalation_add_values(&db_insert, escalations_num++, actionid, event, 1);

				break;
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_pair_destroy(&rec_mapping);
	zbx_vector_uint64_destroy(&rec_actionids);

	if (0 != escalations_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
