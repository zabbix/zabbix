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


#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <string.h>

#include <time.h>

#include <sys/socket.h>
#include <errno.h>

/* Functions: pow(), round() */
#include <math.h>

#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "actions.h"
#include "operations.h"
#include "expression.h"

#include "poller/poller.h"
#include "poller/checks_agent.h"

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
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	check_action_condition(DB_EVENT *event, DB_CONDITION *condition)
{
	DB_RESULT result;
	DB_ROW	row;
	zbx_uint64_t	groupid;
	zbx_uint64_t	hostid;
	zbx_uint64_t	condition_value;
	int		value_int;
	int		now;
	int		tmp_int;

	char		*tmp_str = NULL;

	int	ret = FAIL;

	zabbix_log( LOG_LEVEL_DEBUG, "In check_action_condition [actionid:" ZBX_FS_UI64 ",conditionid:" ZBX_FS_UI64 ",cond.value:%s]",
		condition->actionid,
		condition->conditionid,
		condition->value);

	if(event->source == EVENT_SOURCE_TRIGGERS && condition->conditiontype == CONDITION_TYPE_HOST_GROUP)
	{
		ZBX_STR2UINT64(condition_value, condition->value);
		result = DBselect("select distinct hg.groupid from hosts_groups hg,hosts h, items i, functions f, triggers t where hg.hostid=h.hostid and h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=" ZBX_FS_UI64,
			event->objectid);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			while((row=DBfetch(result)))
			{
				ZBX_STR2UINT64(groupid, row[0]);
				if(condition_value == groupid)
				{
					ret = SUCCEED;
					break;
				}
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			ret = SUCCEED;
			while((row=DBfetch(result)))
			{
				ZBX_STR2UINT64(groupid, row[0]);
				if(condition_value == groupid)
				{
					ret = FAIL;
					break;
				}
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
		DBfree_result(result);
	}
	else if(event->source == EVENT_SOURCE_TRIGGERS && condition->conditiontype == CONDITION_TYPE_HOST)
	{
		ZBX_STR2UINT64(condition_value, condition->value);
		result = DBselect("select distinct h.hostid from hosts h, items i, functions f, triggers t where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=" ZBX_FS_UI64,
			event->objectid);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			while((row=DBfetch(result)))
			{
				ZBX_STR2UINT64(hostid, row[0]);
				if(condition_value == hostid)
				{
					ret = SUCCEED;
					break;
				}
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			ret = SUCCEED;
			while((row=DBfetch(result)))
			{
				ZBX_STR2UINT64(hostid, row[0]);
				if(condition_value == hostid)
				{
					ret = FAIL;
					break;
				}
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
			condition->operator,
			condition->conditionid);
		}
		DBfree_result(result);
	}
	else if(event->source == EVENT_SOURCE_TRIGGERS && condition->conditiontype == CONDITION_TYPE_TRIGGER)
	{
		ZBX_STR2UINT64(condition_value, condition->value);
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_TRIGGER [" ZBX_FS_UI64 ":%s]",
			condition_value,
			condition->value);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(event->objectid == condition_value)
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			if(event->objectid != condition_value)
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]",
				condition->operator,
				condition->conditionid);
		}
	}
	else if(event->source == EVENT_SOURCE_TRIGGERS && condition->conditiontype == CONDITION_TYPE_TRIGGER_NAME)
	{
		tmp_str = zbx_dsprintf(tmp_str, "%s", event->trigger_description);
		
		substitute_simple_macros(event, NULL, &tmp_str, MACRO_TYPE_TRIGGER_DESCRIPTION);
		
		if(condition->operator == CONDITION_OPERATOR_LIKE)
		{
			if(strstr(tmp_str, condition->value) != NULL)
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_LIKE)
		{
			if(strstr(tmp_str, condition->value) == NULL)
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
		zbx_free(tmp_str);
	}
	else if(event->source == EVENT_SOURCE_TRIGGERS && condition->conditiontype == CONDITION_TYPE_TRIGGER_SEVERITY)
	{
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(event->trigger_priority == atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			if(event->trigger_priority != atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_MORE_EQUAL)
		{
			if(event->trigger_priority >= atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_LESS_EQUAL)
		{
			if(event->trigger_priority <= atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
	}
	else if(event->source == EVENT_SOURCE_TRIGGERS && condition->conditiontype == CONDITION_TYPE_TRIGGER_VALUE)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_TRIGGER_VALUE [%d:%s]",
			event->value,
			condition->value);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(event->value == atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
	}
	else if(event->source == EVENT_SOURCE_TRIGGERS && condition->conditiontype == CONDITION_TYPE_TIME_PERIOD)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_TRIGGER_VALUE [%d:%s]",
			event->value,
			condition->value);
		if(condition->operator == CONDITION_OPERATOR_IN)
		{
			if(check_time_period(condition->value, (time_t)NULL)==1)
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
	}
	else if(event->source == EVENT_SOURCE_DISCOVERY &&
		event->object == EVENT_OBJECT_DSERVICE &&
		condition->conditiontype == CONDITION_TYPE_DVALUE)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_DVALUE [%d:%s]",
			event->value,
			condition->value);

		result = DBselect("select value from dservices where dserviceid=" ZBX_FS_UI64,
				event->objectid);
		row = DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(condition->operator == CONDITION_OPERATOR_EQUAL)
			{
				if(strcmp(condition->value, row[0]) == 0)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
			{
				if(strcmp(condition->value, row[0]) != 0)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_MORE_EQUAL)
			{
				if(strcmp(row[0], condition->value) >= 0)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_LESS_EQUAL)
			{
				if(strcmp(row[0], condition->value) <= 0)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_LIKE)
			{
				if(strstr(row[0], condition->value) != NULL)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_NOT_LIKE)
			{
				if(strstr(row[0], condition->value) == NULL)	ret = SUCCEED;
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
					condition->operator,
					condition->conditionid);
			}
		}
		DBfree_result(result);
	}
	else if(event->source == EVENT_SOURCE_DISCOVERY &&
		(event->object == EVENT_OBJECT_DHOST || event->object == EVENT_OBJECT_DSERVICE) &&
		condition->conditiontype == CONDITION_TYPE_DHOST_IP)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_DHOST_IP [%d:%s]",
			event->value,
			condition->value);

		if(event->object == EVENT_OBJECT_DHOST)
		{
			result = DBselect("select ip from dhosts where dhostid=" ZBX_FS_UI64,
				event->objectid);
		}
		else
		{
			result = DBselect("select h.ip from dhosts h,dservices s where h.dhostid=s.dhostid and s.dserviceid=" ZBX_FS_UI64,
				event->objectid);
		}
		row = DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(condition->operator == CONDITION_OPERATOR_EQUAL)
			{
				if(ip_in_list(condition->value, row[0]) == SUCCEED)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
			{
				if(ip_in_list(condition->value, row[0]) == FAIL)	ret = SUCCEED;
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
					condition->operator,
					condition->conditionid);
			}
		}
		DBfree_result(result);
	}
	else if(event->source == EVENT_SOURCE_DISCOVERY && condition->conditiontype == CONDITION_TYPE_DSERVICE_TYPE)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_DSERVICE_TYPE [%d:%s]",
			event->value,
			condition->value);
		value_int = atoi(condition->value);
		result = DBselect("select dserviceid from dservices where type=%d and dserviceid=" ZBX_FS_UI64,
			value_int,
			event->objectid);
		row = DBfetch(result);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(row && DBis_null(row[0]) != SUCCEED)
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
		DBfree_result(result);
	}
	else if(event->source == EVENT_SOURCE_DISCOVERY &&
		(event->object == EVENT_OBJECT_DHOST || event->object == EVENT_OBJECT_DSERVICE) &&
		condition->conditiontype == CONDITION_TYPE_DSTATUS)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_DSTATUS [%d:%s]",
			event->value,
			condition->value);
		value_int = atoi(condition->value);
		if(event->object == EVENT_OBJECT_DHOST)
		{
			result = DBselect("select status from dhosts where dhostid=" ZBX_FS_UI64,
				event->objectid);
		}
		else
		{
			result = DBselect("select status from dservices where dserviceid=" ZBX_FS_UI64,
				event->objectid);
		}
		row = DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(condition->operator == CONDITION_OPERATOR_EQUAL)
			{
				if(value_int == atoi(row[0]))	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
			{
				if(atoi(row[0]) != value_int)	ret = SUCCEED;
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
					condition->operator,
					condition->conditionid);
			}
		}
		DBfree_result(result);
	}
	else if(event->source == EVENT_SOURCE_DISCOVERY &&
		(event->object == EVENT_OBJECT_DHOST || event->object == EVENT_OBJECT_DSERVICE) &&
		condition->conditiontype == CONDITION_TYPE_DUPTIME)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_DUPTIME [event->value:%d:condition->value:%s]",
			event->value,
			condition->value);
		value_int = atoi(condition->value);
		if(event->object == EVENT_OBJECT_DHOST)
		{
			result = DBselect("select status,lastup,lastdown from dhosts where dhostid=" ZBX_FS_UI64,
				event->objectid);
		}
		else
		{
			result = DBselect("select status,lastup,lastdown from dservices where dserviceid=" ZBX_FS_UI64,
				event->objectid);
		}
		row = DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			tmp_int = (atoi(row[0]) == DOBJECT_STATUS_UP)?atoi(row[1]):atoi(row[2]);
			now = time(NULL);
			if(condition->operator == CONDITION_OPERATOR_LESS_EQUAL)
			{
				if(tmp_int != 0 && (now-tmp_int)<=value_int)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_MORE_EQUAL)
			{
				if(tmp_int != 0 && (now-tmp_int)>=value_int)	ret = SUCCEED;
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
					condition->operator,
					condition->conditionid);
			}
		}
		DBfree_result(result);
	}
	else if(event->source == EVENT_SOURCE_DISCOVERY &&
		event->object == EVENT_OBJECT_DSERVICE &&
		condition->conditiontype == CONDITION_TYPE_DSERVICE_PORT)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_DSERVICE_PORT [%d:%s]",
			event->value,
			condition->value);
		result = DBselect("select port from dservices where dserviceid=" ZBX_FS_UI64,
			event->objectid);
		row = DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(condition->operator == CONDITION_OPERATOR_EQUAL)
			{
				if(int_in_list(condition->value, atoi(row[0])) == SUCCEED)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
			{
				if(int_in_list(condition->value, atoi(row[0])) == FAIL)	ret = SUCCEED;
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
					condition->operator,
					condition->conditionid);
			}
		}
		DBfree_result(result);
	}
	else
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Event source [%d] and condition type [%d] is unknown for condition id [" ZBX_FS_UI64 "]",
			event->source,
			condition->conditiontype,
			condition->conditionid);
	}

	if(FAIL==ret)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Condition is FALSE");
	}
	else
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Condition is TRUE");
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End check_action_condition()");
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_action_conditions                                          *
 *                                                                            *
 * Purpose: check if actions has to be processed for the event                *
 *          (check all condition of the action)                               *
 *                                                                            *
 * Parameters: event - event to check                                         *
 *             actionid - action ID for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	check_action_conditions(DB_EVENT *event, DB_ACTION *action)
{
	DB_RESULT result;
	DB_ROW row;

	DB_CONDITION	condition;

	/* SUCCEED required for ACTION_EVAL_TYPE_AND_OR */	
	int	ret = SUCCEED;
	int	old_type = -1;
	int	cond;
	int	num = 0;
	int	exit = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In check_action_conditions (actionid:" ZBX_FS_UI64 ")",
		action->actionid);

	result = DBselect("select conditionid,actionid,conditiontype,operator,value from conditions where actionid=" ZBX_FS_UI64 " order by conditiontype",
		action->actionid);

	while((row=DBfetch(result)))
	{
		num++;

		ZBX_STR2UINT64(condition.conditionid, row[0]);
		ZBX_STR2UINT64(condition.actionid, row[1]);
		condition.conditiontype=atoi(row[2]);
		condition.operator=atoi(row[3]);
		condition.value=row[4];

		switch (action->evaltype) {
			case ACTION_EVAL_TYPE_AND_OR:
				/* OR conditions */
				if(old_type == condition.conditiontype)
				{
					if(check_action_condition(event, &condition) == SUCCEED)
						ret = SUCCEED;
				}
				/* AND conditions */
				else
				{
					/* Break if PREVIOUS AND condition is FALSE */
					if(ret == FAIL)
					{
						exit = 1;
					}
					else if(check_action_condition(event, &condition) == FAIL)
					{
						ret = FAIL;
					}
				}
		
				old_type = condition.conditiontype;
				break;
			case ACTION_EVAL_TYPE_AND:
				cond = check_action_condition(event, &condition);
				/* Break if any of AND conditions is FALSE */
				if(cond == FAIL)
				{
					ret = FAIL; exit = 1;
				}
				else
				{
					ret = SUCCEED;
				}
				break;
			case ACTION_EVAL_TYPE_OR:
				cond = check_action_condition(event, &condition);
				/* Break if any of OR conditions is TRUE */
				if(cond == SUCCEED)
				{
					ret = SUCCEED; exit = 1;
				}
				else
				{
					ret = FAIL;
				}
				break;
			default:
				zabbix_log( LOG_LEVEL_DEBUG, "End check_action_conditions (result:%s)",
					(FAIL==ret)?"FALSE":"TRUE");
				ret = FAIL;
				break;

		}

	}
	DBfree_result(result);

	/* Ifnot conditions defined, return SUCCEED*/ 
	if(num == 0)	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "End check_action_conditions (result:%s)",
		(FAIL==ret)?"FALSE":"TRUE");

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
 * Return value: -                                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	execute_operations(DB_EVENT *event, DB_ACTION *action)
{
	DB_RESULT	result;
	DB_ROW		row;
	
	DB_OPERATION	operation;

	zabbix_log( LOG_LEVEL_DEBUG, "In execute_operations(actionid:" ZBX_FS_UI64 ")",
		action->actionid);

	result = DBselect("select operationid,actionid,operationtype,object,objectid,shortdata,longdata from operations where actionid=" ZBX_FS_UI64,
			action->actionid);
	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(operation.operationid,	row[0]);
		ZBX_STR2UINT64(operation.actionid,	row[1]);
		operation.operationtype			= atoi(row[2]);
		operation.object			= atoi(row[3]);
		ZBX_STR2UINT64(operation.objectid,	row[4]);

		operation.shortdata		= strdup(row[5]);
		operation.longdata		= strdup(row[6]);

		substitute_macros(event, action, &operation.shortdata);
		substitute_macros(event, action, &operation.longdata);

		switch(operation.operationtype)
		{
			case	OPERATION_TYPE_MESSAGE:
				op_notify_user(event,action,&operation);
				break;
			case	OPERATION_TYPE_COMMAND:
				op_run_commands(event,&operation);
				break;
			case	OPERATION_TYPE_HOST_ADD:
				op_host_add(event);
				break;
			case	OPERATION_TYPE_HOST_REMOVE:
				op_host_del(event);
				break;
			case	OPERATION_TYPE_GROUP_ADD:
				op_group_add(event,action,&operation);
				break;
			case	OPERATION_TYPE_GROUP_REMOVE:
				op_group_del(event,action,&operation);
				break;
			case	OPERATION_TYPE_TEMPLATE_ADD:
				op_template_add(event,action,&operation);
				break;
			case	OPERATION_TYPE_TEMPLATE_REMOVE:
				op_template_del(event,action,&operation);
				break;
			default:
				break;
		}
	
		zbx_free(operation.shortdata);
		zbx_free(operation.longdata);
	}
	DBfree_result(result);
}


/******************************************************************************
 *                                                                            *
 * Function: process_actions                                                  *
 *                                                                            *
 * Purpose: process all actions that match single event                       *
 *                                                                            *
 * Parameters: event - event to apply actions for                             *
 *                                                                            *
 * Return value: -                                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: we check also trigger dependencies                               *
 *                                                                            *
 ******************************************************************************/
void	process_actions(DB_EVENT *event)
{
	DB_RESULT result;
	DB_ROW row;
	
	DB_ACTION action;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_actions(source:%s,eventid:" ZBX_FS_UI64 ")",
		(event->source == EVENT_SOURCE_TRIGGERS)?"TRIGGERS":"DISCOVERY",
		event->eventid);

	if(TRIGGER_VALUE_TRUE == event->value)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Check dependencies");

		result = DBselect("select count(*) from trigger_depends d,triggers t where d.triggerid_down=" ZBX_FS_UI64 " and d.triggerid_up=t.triggerid and t.value=%d",
			event->objectid,
			TRIGGER_VALUE_TRUE);
		row=DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(atoi(row[0])>0)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Will not apply actions");
				DBfree_result(result);
				return;
			}
		}
		DBfree_result(result);
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Processing actions");

	result = DBselect("select actionid,evaltype,status,eventsource from actions where status=%d and eventsource=%d and" ZBX_COND_NODEID,
		ACTION_STATUS_ACTIVE,
		event->source,
		LOCAL_NODE("actionid"));

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(action.actionid, row[0]);
		action.evaltype		= atoi(row[1]);
		action.status		= atoi(row[2]);
		action.eventsource	= atoi(row[3]);

		if(check_action_conditions(event, &action) == SUCCEED)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Conditions match our event. Execute operations.");

			execute_operations(event, &action);

		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Conditions do not match our event. Do not execute operations.");
		}
	}
	DBfree_result(result);
	zabbix_log( LOG_LEVEL_DEBUG, "End process_actions()");
}
