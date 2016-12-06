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
#include "log.h"
#include "zbxserver.h"

#include "actions.h"
#include "operations.h"
#include "events.h"

static int	is_recovery_event(const DB_EVENT *event);
static int	is_escalation_event(const DB_EVENT *event);

/******************************************************************************
 *                                                                            *
 * Function: check_condition_event_tag                                        *
 *                                                                            *
 * Purpose: check event tag condition                                         *
 *                                                                            *
 * Parameters: event     - the event                                          *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static int	check_condition_event_tag(const DB_EVENT *event, const DB_CONDITION *condition)
{
	int	i, ret, ret_continue;

	if (CONDITION_OPERATOR_NOT_EQUAL == condition->operator || CONDITION_OPERATOR_NOT_LIKE == condition->operator)
		ret_continue = SUCCEED;
	else
		ret_continue = FAIL;

	ret = ret_continue;

	for (i = 0; i < event->tags.values_num && ret == ret_continue; i++)
	{
		zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[i];

		ret = zbx_strmatch_condition(tag->tag, condition->value, condition->operator);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_condition_event_tag_value                                  *
 *                                                                            *
 * Purpose: check event tag value condition                                   *
 *                                                                            *
 * Parameters: event     - the event                                          *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static int	check_condition_event_tag_value(const DB_EVENT *event, DB_CONDITION *condition)
{
	int	i, ret, ret_continue;

	if (CONDITION_OPERATOR_NOT_EQUAL == condition->operator || CONDITION_OPERATOR_NOT_LIKE == condition->operator)
		ret_continue = SUCCEED;
	else
		ret_continue = FAIL;

	ret = ret_continue;

	for (i = 0; i < event->tags.values_num && ret == ret_continue; i++)
	{
		zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[i];

		if (0 == strcmp(condition->value2, tag->tag))
			ret = zbx_strmatch_condition(tag->value, condition->value, condition->operator);
	}

	return ret;
}

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
static int	check_trigger_condition(const DB_EVENT *events, size_t events_num, unsigned char source,
		DB_CONDITION *condition)
{
	const char	*__function_name = "check_trigger_condition";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	condition_value;
	char		*tmp_str = NULL;
	int		ret, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < events_num; i++)
	{
		const DB_EVENT	*event = &events[i];

		ret = FAIL;

		if (FAIL == is_escalation_event(event) || source != event->source)
			continue;

		if (CONDITION_TYPE_HOST_GROUP == condition->conditiontype)
		{
			char			*sql = NULL;
			size_t			sql_alloc = 256, sql_offset;
			zbx_vector_uint64_t	triggerids;

			ZBX_STR2UINT64(condition_value, condition->value);

			zbx_vector_uint64_create(&triggerids);
			sql = zbx_malloc(sql, sql_alloc);

			for (; i < events_num; i++)
			{
				const DB_EVENT	*event = &events[i];

				if (FAIL == is_escalation_event(event) || source != event->source)
					continue;

				zbx_vector_uint64_append(&triggerids, event->objectid);
			}

			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct t.triggerid"
				" from hosts_groups hg,hosts h,items i,functions f,triggers t"
				" where hg.hostid=h.hostid"
					" and h.hostid=i.hostid"
					" and i.itemid=f.itemid"
					" and f.triggerid=t.triggerid"
					" and hg.groupid=" ZBX_FS_UI64
					" and",
				condition_value);

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, " t.triggerid",
						triggerids.values, triggerids.values_num);

			result = DBselect("%s", sql);

			switch (condition->operator)
			{

				case CONDITION_OPERATOR_EQUAL:
					while (NULL != (row = DBfetch(result)))
					{
						zbx_uint64_t		objectid;

						ZBX_STR2UINT64(objectid, row[0]);
						zbx_vector_uint64_append(&condition->objectids, objectid);

						ret = SUCCEED;
					}
					break;
				case CONDITION_OPERATOR_NOT_EQUAL:
					while (NULL == (row = DBfetch(result)))
					{
						zbx_uint64_t		objectid;

						ZBX_STR2UINT64(objectid, row[0]);
						zbx_vector_uint64_append(&condition->objectids, objectid);

						ret = SUCCEED;
					}
					break;
				default:
					ret = NOTSUPPORTED;
			}

			DBfree_result(result);
			zbx_vector_uint64_destroy(&triggerids);
			zbx_free(sql);

			break;	/* optimized to bulk select, don't loop further */
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

						while (NULL != (row = DBfetch(result)))
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

			substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL,
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
		else if (CONDITION_TYPE_TIME_PERIOD == condition->conditiontype)
		{
			switch (condition->operator)
			{
				case CONDITION_OPERATOR_IN:
					if (SUCCEED == check_time_period(condition->value, (time_t)event->clock))
						ret = SUCCEED;
					break;
				case CONDITION_OPERATOR_NOT_IN:
					if (FAIL == check_time_period(condition->value, (time_t)event->clock))
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

					if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]) &&
						0 != atoi(row[0]))
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

					if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]) &&
						0 != atoi(row[0]))
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
		else if (CONDITION_TYPE_EVENT_TAG == condition->conditiontype)
		{
			ret = check_condition_event_tag(event, condition);
		}
		else if (CONDITION_TYPE_EVENT_TAG_VALUE == condition->conditiontype)
		{
			ret = check_condition_event_tag_value(event, condition);
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

		if (SUCCEED == ret)
			zbx_vector_uint64_append(&condition->objectids, event->objectid);
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
static int	check_discovery_condition(const DB_EVENT *events, size_t events_num, unsigned char source,
		DB_CONDITION *condition)
{
	const char	*__function_name = "check_discovery_condition";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	condition_value;
	int		tmp_int, ret, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < events_num; i++)
	{
		const DB_EVENT	*event = &events[i];

		ret = FAIL;

		if (FAIL == is_escalation_event(event) || source != event->source)
			continue;

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
			int	condition_value_i = atoi(condition->value);

			switch (condition->operator)
			{
				case CONDITION_OPERATOR_EQUAL:
					if (event->object == condition_value_i)
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
				int	condition_value_i = atoi(condition->value);

				result = DBselect(
						"select dc.type"
						" from dservices ds,dchecks dc"
						" where ds.dcheckid=dc.dcheckid"
							" and ds.dserviceid=" ZBX_FS_UI64,
						event->objectid);

				if (NULL != (row = DBfetch(result)))
				{
					tmp_int = atoi(row[0]);

					switch (condition->operator)
					{
						case CONDITION_OPERATOR_EQUAL:
							if (condition_value_i == tmp_int)
								ret = SUCCEED;
							break;
						case CONDITION_OPERATOR_NOT_EQUAL:
							if (condition_value_i != tmp_int)
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
			int	condition_value_i = atoi(condition->value);

			switch (condition->operator)
			{
				case CONDITION_OPERATOR_EQUAL:
					if (condition_value_i == event->value)
						ret = SUCCEED;
					break;
				case CONDITION_OPERATOR_NOT_EQUAL:
					if (condition_value_i != event->value)
						ret = SUCCEED;
					break;
				default:
					ret = NOTSUPPORTED;
			}
		}
		else if (CONDITION_TYPE_DUPTIME == condition->conditiontype)
		{
			int	condition_value_i = atoi(condition->value);

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
				int	now;

				now = time(NULL);
				tmp_int = DOBJECT_STATUS_UP == atoi(row[0]) ? atoi(row[1]) : atoi(row[2]);

				switch (condition->operator)
				{
					case CONDITION_OPERATOR_LESS_EQUAL:
						if (0 != tmp_int && (now - tmp_int) <= condition_value_i)
							ret = SUCCEED;
						break;
					case CONDITION_OPERATOR_MORE_EQUAL:
						if (0 != tmp_int && (now - tmp_int) >= condition_value_i)
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

		if (SUCCEED == ret)
			zbx_vector_uint64_append(&condition->objectids, event->objectid);
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
static int	check_auto_registration_condition(const DB_EVENT *events, size_t events_num, unsigned char source,
		DB_CONDITION *condition)
{
	const char	*__function_name = "check_auto_registration_condition";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	condition_value, id;
	int		ret, i;
	const char	*condition_field;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < events_num; i++)
	{
		const DB_EVENT	*event = &events[i];

		ret = FAIL;

		if (FAIL == is_escalation_event(event) || source != event->source)
			continue;

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

		if (SUCCEED == ret)
			zbx_vector_uint64_append(&condition->objectids, event->objectid);
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
static int	check_internal_condition(const DB_EVENT *events, size_t events_num, unsigned char source,
		DB_CONDITION *condition)
{
	const char	*__function_name = "check_internal_condition";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	condition_value;
	int		ret, i;
	char		sql[256];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < events_num; i++)
	{
		const DB_EVENT	*event = &events[i];

		ret = FAIL;

		if (FAIL == is_escalation_event(event) || source != event->source)
			continue;

		if (EVENT_OBJECT_TRIGGER != event->object && EVENT_OBJECT_ITEM != event->object &&
				EVENT_OBJECT_LLDRULE != event->object)
		{
			zabbix_log(LOG_LEVEL_ERR, "unsupported event object [%d] for condition id [" ZBX_FS_UI64 "]",
					event->object, condition->conditionid);
			continue;
		}

		if (CONDITION_TYPE_EVENT_TYPE == condition->conditiontype)
		{
			condition_value = atoi(condition->value);

			switch (condition_value)
			{
				case EVENT_TYPE_ITEM_NOTSUPPORTED:
					if (EVENT_OBJECT_ITEM == event->object && ITEM_STATE_NOTSUPPORTED == event->value)
						ret = SUCCEED;
					break;
				case EVENT_TYPE_TRIGGER_UNKNOWN:
					if (EVENT_OBJECT_TRIGGER == event->object && TRIGGER_STATE_UNKNOWN == event->value)
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

		if (SUCCEED == ret)
			zbx_vector_uint64_append(&condition->objectids, event->objectid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_events_condition                                           *
 *                                                                            *
 * Purpose: check if multiple event matches single condition                  *
 *                                                                            *
 * Parameters: events [IN] - events to check                                  *
 *             events_num [IN] - events count                                 *
 *             source [IN] - specific event source that need checking         *
 *                                                                            *
 *             condition [IN/OUT] - condition for matching, outputs event ids *
 *                                  that match condition                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	check_events_condition(const DB_EVENT *events, size_t events_num, unsigned char source,
		DB_CONDITION *condition)
{
	const char	*__function_name = "check_action_condition";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64 " conditionid:" ZBX_FS_UI64 " cond.value:'%s'"
			" cond.value2:'%s'", __function_name, condition->actionid, condition->conditionid,
			condition->value, condition->value2);

	switch (source)
	{
		case EVENT_SOURCE_TRIGGERS:
			check_trigger_condition(events, events_num, source, condition);
			break;
		case EVENT_SOURCE_DISCOVERY:
			check_discovery_condition(events, events_num, source, condition);
			break;
		case EVENT_SOURCE_AUTO_REGISTRATION:
			check_auto_registration_condition(events, events_num, source, condition);
			break;
		case EVENT_SOURCE_INTERNAL:
			check_internal_condition(events, events_num, source, condition);
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unsupported event source [%d] for condition id [" ZBX_FS_UI64 "]",
					source, condition->conditionid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	get_events_conditions_results(const DB_EVENT *events, size_t events_num,
		zbx_hashset_t *uniq_conditions)
{
	int	i;

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		zbx_hashset_iter_t	iter;
		DB_CONDITION		*condition;

		zbx_hashset_iter_reset(&uniq_conditions[i], &iter);

		while (NULL != (condition = (DB_CONDITION *)zbx_hashset_iter_next(&iter)))
		{
			zbx_vector_uint64_create(&condition->objectids);
			check_events_condition(events, events_num, i, condition);
		}
	}
}

static void	clear_events_conditions_results(const DB_EVENT *events, zbx_hashset_t *uniq_conditions)
{
	int	i;

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		zbx_hashset_iter_t	iter;
		DB_CONDITION		*condition;

		zbx_hashset_iter_reset(&uniq_conditions[i], &iter);

		while (NULL != (condition = (DB_CONDITION *)zbx_hashset_iter_next(&iter)))
			zbx_vector_uint64_destroy(&condition->objectids);
	}
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
	int	ret = FAIL;

	zbx_vector_uint64_create(&condition->objectids);

	check_events_condition(event, 1, event->source, condition);

	if (1 == condition->objectids.values_num && condition->objectids.values[0] == event->objectid)
		ret = SUCCEED;

	zbx_vector_uint64_destroy(&condition->objectids);

	return ret;
}

int	event_matches_condition(const DB_EVENT *event, const DB_CONDITION *condition)
{
	int	i = 0;

	for (i = 0; i < condition->objectids.values_num; i++)
	{
		if (condition->objectids.values[i] == event->objectid)
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: check_action_conditions                                          *
 *                                                                            *
 * Purpose: check if actions have to be processed for the event               *
 *          (check all conditions of the action)                              *
 *                                                                            *
 * Parameters: actionid - action ID for matching                              *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	check_action_conditions(const DB_EVENT *event, zbx_action_eval_t *action)
{
	const char	*__function_name = "check_action_conditions";

	DB_CONDITION	*condition;
	int		condition_result, ret = SUCCEED, id_len, i;
	unsigned char	old_type = 0xff;
	char		*expression = NULL, tmp[ZBX_MAX_UINT64_LEN + 2], *ptr, error[256];
	double		eval_result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64, __function_name, action->actionid);

	if (action->evaltype == CONDITION_EVAL_TYPE_EXPRESSION)
		expression = zbx_strdup(expression, action->formula);

	for (i = 0; i < action->conditions.values_num; i++)
	{
		condition = (DB_CONDITION *)action->conditions.values[i];

		if (CONDITION_EVAL_TYPE_AND_OR == action->evaltype && old_type == condition->conditiontype &&
				SUCCEED == ret)
		{
			continue;	/* short-circuit true OR condition block to the next AND condition */
		}

		condition_result = event_matches_condition(event, condition);

		switch (action->evaltype)
		{
			case CONDITION_EVAL_TYPE_AND_OR:
				if (old_type == condition->conditiontype)	/* assume conditions are sorted by type */
				{
					if (SUCCEED == condition_result)
						ret = SUCCEED;
				}
				else
				{
					if (FAIL == ret)
						goto clean;

					ret = condition_result;
					old_type = condition->conditiontype;
				}

				break;
			case CONDITION_EVAL_TYPE_AND:
				if (FAIL == condition_result)	/* break if any AND condition is FALSE */
				{
					ret = FAIL;
					goto clean;
				}

				break;
			case CONDITION_EVAL_TYPE_OR:
				if (SUCCEED == condition_result)	/* break if any OR condition is TRUE */
				{
					ret = SUCCEED;
					goto clean;
				}
				ret = FAIL;

				break;
			case CONDITION_EVAL_TYPE_EXPRESSION:
				zbx_snprintf(tmp, sizeof(tmp), "{" ZBX_FS_UI64 "}", condition->conditionid);
				id_len = strlen(tmp);

				for (ptr = expression; NULL != (ptr = strstr(ptr, tmp)); ptr += id_len)
				{
					*ptr = (SUCCEED == condition_result ? '1' : '0');
					memset(ptr + 1, ' ', id_len - 1);
				}

				break;
			default:
				ret = FAIL;
				goto clean;
		}
	}

	if (action->evaltype == CONDITION_EVAL_TYPE_EXPRESSION)
	{
		if (SUCCEED == evaluate(&eval_result, expression, error, sizeof(error), NULL))
			ret = (SUCCEED != zbx_double_compare(eval_result, 0) ? SUCCEED : FAIL);

		zbx_free(expression);
	}
clean:

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: execute_operations                                               *
 *                                                                            *
 * Purpose: execute host, group, template operations linked to the action     *
 *                                                                            *
 * Parameters: action - action to execute operations for                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: for message, command operations see                              *
 *           escalation_execute_operations(),                                 *
 *           escalation_execute_recovery_operations().                        *
 *                                                                            *
 ******************************************************************************/
static void	execute_operations(const DB_EVENT *event, zbx_uint64_t actionid)
{
	const char		*__function_name = "execute_operations";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		groupid, templateid;
	zbx_vector_uint64_t	lnk_templateids, del_templateids,
				new_groupids, del_groupids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64, __function_name, actionid);

	zbx_vector_uint64_create(&lnk_templateids);
	zbx_vector_uint64_create(&del_templateids);
	zbx_vector_uint64_create(&new_groupids);
	zbx_vector_uint64_create(&del_groupids);

	result = DBselect(
			"select o.operationtype,g.groupid,t.templateid,oi.inventory_mode"
			" from operations o"
				" left join opgroup g on g.operationid=o.operationid"
				" left join optemplate t on t.operationid=o.operationid"
				" left join opinventory oi on oi.operationid=o.operationid"
			" where o.actionid=" ZBX_FS_UI64,
			actionid);

	while (NULL != (row = DBfetch(result)))
	{
		int		inventory_mode;
		unsigned char	operationtype;

		operationtype = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(groupid, row[1]);
		ZBX_DBROW2UINT64(templateid, row[2]);
		inventory_mode = (SUCCEED == DBis_null(row[3]) ? 0 : atoi(row[3]));

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
			case OPERATION_TYPE_HOST_INVENTORY:
				op_host_inventory_mode(event, inventory_mode);
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

/* data structures used to create new and recover existing escalations */

typedef struct
{
	zbx_uint64_t	actionid;
	const DB_EVENT	*event;
}
zbx_escalation_new_t;

typedef struct
{
	zbx_uint64_t		r_eventid;
	zbx_vector_uint64_t	escalationids;
}
zbx_escalation_rec_t;

static int	is_escalation_event(const DB_EVENT *event)
{
	/* OK events can't start escalations - skip them */
	if (SUCCEED == is_recovery_event(event))
		return FAIL;

	if (0 != (event->flags & ZBX_FLAGS_DB_EVENT_NO_ACTION) ||
			0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_recovery_event                                                *
 *                                                                            *
 * Purpose: checks if the event is recovery event                             *
 *                                                                            *
 * Parameters: event - [IN] the event to check                                *
 *                                                                            *
 * Return value: SUCCEED - the event is recovery event                        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	is_recovery_event(const DB_EVENT *event)
{
	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		if (EVENT_OBJECT_TRIGGER == event->object && TRIGGER_VALUE_OK == event->value)
			return SUCCEED;
	}
	else if (EVENT_SOURCE_INTERNAL == event->source)
	{
		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (TRIGGER_STATE_NORMAL == event->value)
					return SUCCEED;
				break;
			case EVENT_OBJECT_ITEM:
				if (ITEM_STATE_NORMAL == event->value)
					return SUCCEED;
				break;
			case EVENT_OBJECT_LLDRULE:
				if (ITEM_STATE_NORMAL == event->value)
					return SUCCEED;
				break;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: uniq_conditions_compare_func                                     *
 *                                                                            *
 * Purpose: compare to find equal conditions                                  *
 *                                                                            *
 * Parameters: d1 - [IN] condition structure to compare to d2                 *
 *             d2 - [IN] condition structure to compare to d1                 *
 *                                                                            *
 * Return value: 0 - equal                                                    *
 *               not 0 - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	uniq_conditions_compare_func(const void *d1, const void *d2)
{
	const DB_CONDITION	*condition1 = d1, *condition2 = d2;
	int			ret;

	ZBX_RETURN_IF_NOT_EQUAL(condition1->conditiontype, condition2->conditiontype);
	ZBX_RETURN_IF_NOT_EQUAL(condition1->operator, condition2->operator);

	if (0 != (ret = strcmp(condition1->value, condition2->value)))
		return ret;

	if (0 != (ret = strcmp(condition1->value2, condition2->value2)))
		return ret;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: uniq_conditions_hash_func                                        *
 *                                                                            *
 * Purpose: generate hash based on condition values                           *
 *                                                                            *
 * Parameters: data - [IN] condition structure                                *
 *                                                                            *
 *                                                                            *
 * Return value: hash is generated                                            *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	uniq_conditions_hash_func(const void *data)
{
	const DB_CONDITION	*condition = data;
	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_STRING_HASH_ALGO(condition->value, strlen(condition->value), ZBX_DEFAULT_HASH_SEED);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(condition->value2, strlen(condition->value2), hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO((char *)&condition->conditiontype, 1, hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO((char *)&condition->operator, 1, hash);

	return hash;
}

/******************************************************************************
 *                                                                            *
 * Function: process_actions                                                  *
 *                                                                            *
 * Purpose: process all actions of each event in a list                       *
 *                                                                            *
 * Parameters: events        - [IN] events to apply actions for               *
 *             events_num    - [IN] number of events                          *
 *             closed_events - [IN] a vector of closed event data -           *
 *                                  (PROBLEM eventid, OK eventid) pairs.      *
 *                                                                            *
 ******************************************************************************/
void	process_actions(const DB_EVENT *events, size_t events_num, zbx_vector_uint64_pair_t *closed_events)
{
	const char			*__function_name = "process_actions";

	size_t				i;
	zbx_vector_ptr_t		actions;
	zbx_vector_ptr_t 		new_escalations;
	zbx_hashset_t			rec_escalations;
	zbx_hashset_t			uniq_conditions[EVENT_SOURCE_COUNT];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)events_num);

	zbx_vector_ptr_create(&new_escalations);
	zbx_hashset_create(&rec_escalations, events_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		zbx_hashset_create(&uniq_conditions[i], 0, uniq_conditions_hash_func,
				uniq_conditions_compare_func);
	}

	zbx_vector_ptr_create(&actions);
	zbx_dc_get_actions_eval(&actions, uniq_conditions);

	get_events_conditions_results(events, events_num, uniq_conditions);

	/* 1. All event sources: match PROBLEM events to action conditions, add them to 'new_escalations' list.      */
	/* 2. EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTO_REGISTRATION: execute operations (except command and message */
	/*    operations) for events that match action conditions.                                                   */
	for (i = 0; i < events_num; i++)
	{
		int		j;
		const DB_EVENT	*event;

		event = &events[i];

		if (FAIL == is_escalation_event(event))
			continue;

		for (j = 0; j < actions.values_num; j++)
		{
			zbx_action_eval_t	*action = (zbx_action_eval_t *)actions.values[j];

			if (action->eventsource != event->source)
				continue;

			if (SUCCEED == check_action_conditions(event, action))
			{
				zbx_escalation_new_t	*new_escalation;

				/* command and message operations handled by escalators even for    */
				/* EVENT_SOURCE_DISCOVERY and EVENT_SOURCE_AUTO_REGISTRATION events */
				new_escalation = zbx_malloc(NULL, sizeof(zbx_escalation_new_t));
				new_escalation->actionid = action->actionid;
				new_escalation->event = event;
				zbx_vector_ptr_append(&new_escalations, new_escalation);

				if (EVENT_SOURCE_DISCOVERY == event->source ||
						EVENT_SOURCE_AUTO_REGISTRATION == event->source)
				{
					execute_operations(event, action->actionid);
				}
			}
		}
	}

	clear_events_conditions_results(events, uniq_conditions);

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		zbx_conditions_eval_clean(&uniq_conditions[i]);
		zbx_hashset_destroy(&uniq_conditions[i]);
	}

	zbx_vector_ptr_clear_ext(&actions, (zbx_clean_func_t)zbx_action_eval_free);
	zbx_vector_ptr_destroy(&actions);

	/* 3. Find recovered escalations and store escalationids in 'rec_escalation' by OK eventids. */
	if (0 != closed_events->values_num)
	{
		char			*sql = NULL;
		size_t			sql_alloc = 0, sql_offset = 0;
		zbx_vector_uint64_t	eventids;
		DB_ROW			row;
		DB_RESULT		result;
		zbx_uint64_t		actionid, r_eventid;
		int			j, index;

		zbx_vector_uint64_create(&eventids);

		/* 3.1. Store PROBLEM eventids of recovered events in 'eventids'. */
		for (j = 0; j < closed_events->values_num; j++)
			zbx_vector_uint64_append(&eventids, closed_events->values[j].first);

		/* 3.2. Select escalations that must be recovered. */
		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select actionid,eventid,escalationid"
				" from escalations"
				" where");

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);
		result = DBselect("%s", sql);

		/* 3.3. Store the escalationids corresponding to the OK events in 'rec_escalations'. */
		while (NULL != (row = DBfetch(result)))
		{
			zbx_escalation_rec_t	*rec_escalation;
			zbx_uint64_t		escalationid;
			zbx_uint64_pair_t	event_pair;

			ZBX_STR2UINT64(actionid, row[0]);
			ZBX_STR2UINT64(event_pair.first, row[1]);

			if (FAIL == (index = zbx_vector_uint64_pair_bsearch(closed_events, event_pair,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			r_eventid = closed_events->values[index].second;

			if (NULL == (rec_escalation = zbx_hashset_search(&rec_escalations, &r_eventid)))
			{
				zbx_escalation_rec_t	esc_rec_local;

				esc_rec_local.r_eventid = r_eventid;
				rec_escalation = zbx_hashset_insert(&rec_escalations, &esc_rec_local,
						sizeof(esc_rec_local));

				zbx_vector_uint64_create(&rec_escalation->escalationids);
			}

			ZBX_DBROW2UINT64(escalationid, row[2]);
			zbx_vector_uint64_append(&rec_escalation->escalationids, escalationid);

		}

		DBfree_result(result);
		zbx_free(sql);
		zbx_vector_uint64_destroy(&eventids);
	}

	/* 4. Create new escalations in DB. */
	if (0 != new_escalations.values_num)
	{
		zbx_db_insert_t	db_insert;
		int		i;

		zbx_db_insert_prepare(&db_insert, "escalations", "escalationid", "actionid", "status", "triggerid",
					"itemid", "eventid", "r_eventid", NULL);

		for (i = 0; i < new_escalations.values_num; i++)
		{
			zbx_uint64_t		triggerid = 0, itemid = 0;
			zbx_escalation_new_t	*new_escalation;

			new_escalation = (zbx_escalation_new_t *)new_escalations.values[i];

			switch (new_escalation->event->object)
			{
				case EVENT_OBJECT_TRIGGER:
					triggerid = new_escalation->event->objectid;
					break;
				case EVENT_OBJECT_ITEM:
				case EVENT_OBJECT_LLDRULE:
					itemid = new_escalation->event->objectid;
					break;
			}

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), new_escalation->actionid,
					(int)ESCALATION_STATUS_ACTIVE, triggerid, itemid,
					new_escalation->event->eventid, __UINT64_C(0));

			zbx_free(new_escalation);
		}

		zbx_db_insert_autoincrement(&db_insert, "escalationid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	/* 5. Modify recovered escalations in DB. */
	if (0 != rec_escalations.num_data)
	{
		char			*sql = NULL;
		size_t			sql_alloc = 0, sql_offset = 0;
		zbx_hashset_iter_t	iter;
		zbx_escalation_rec_t	*rec_escalation;

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		zbx_hashset_iter_reset(&rec_escalations, &iter);

		while (NULL != (rec_escalation = (zbx_escalation_rec_t *)zbx_hashset_iter_next(&iter)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update escalations set r_eventid="
					ZBX_FS_UI64 " where", rec_escalation->r_eventid);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "escalationid",
					rec_escalation->escalationids.values,
					rec_escalation->escalationids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			zbx_vector_uint64_destroy(&rec_escalation->escalationids);
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)	/* in ORACLE always present begin..end; */
			DBexecute("%s", sql);

		zbx_free(sql);
	}

	zbx_hashset_destroy(&rec_escalations);
	zbx_vector_ptr_destroy(&new_escalations);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
