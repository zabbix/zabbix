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
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "daemon.h"
#include "zbxserver.h"
#include "zbxself.h"

#include "escalator.h"
#include "../operations.h"
#include "../events.h"
#include "../actions.h"

#define CONFIG_ESCALATOR_FREQUENCY	3

typedef struct
{
	zbx_uint64_t	userid;
	zbx_uint64_t	mediatypeid;
	char		*subject;
	char		*message;
	void		*next;
}
ZBX_USER_MSG;

extern unsigned char	process_type;
extern int		process_num;

/******************************************************************************
 *                                                                            *
 * Function: check_perm2system                                                *
 *                                                                            *
 * Purpose: Checking user permissions to access system.                       *
 *                                                                            *
 * Parameters: userid - user ID                                               *
 *                                                                            *
 * Return value: SUCCEED - permission is positive, FAIL - otherwise           *
 *                                                                            *
 * Author:                                                                    *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	check_perm2system(zbx_uint64_t userid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;

	result = DBselect( "select count(*) from usrgrp g,users_groups ug where ug.userid=" ZBX_FS_UI64
			" and g.usrgrpid = ug.usrgrpid and g.users_status=%d",
			userid,
			GROUP_STATUS_DISABLED);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]) && atoi(row[0]) > 0)
		res = FAIL;

	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: get_host_permission                                              *
 *                                                                            *
 * Purpose: Return user permissions for access to the host                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 * Author:                                                                    *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_host_permission(zbx_uint64_t userid, zbx_uint64_t hostid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		user_type = -1, perm = PERM_DENY;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_host_permission()");

	result = DBselect("select type from users where userid=" ZBX_FS_UI64,
			userid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		user_type = atoi(row[0]);

	DBfree_result(result);

	if (-1 == user_type)
		goto out;

	if (USER_TYPE_SUPER_ADMIN == user_type)
	{
		perm = PERM_MAX;
		goto out;
	}

	result = DBselect("select min(r.permission) from rights r,hosts_groups hg,users_groups ug"
			" where r.groupid=ug.usrgrpid and r.id=hg.groupid"
			" and hg.hostid=" ZBX_FS_UI64 " and ug.userid=" ZBX_FS_UI64,
			hostid,
			userid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		perm = atoi(row[0]);

	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of get_host_permission():%s",
			zbx_permission_string(perm));

	return perm;
}

/******************************************************************************
 *                                                                            *
 * Function: get_trigger_permission                                           *
 *                                                                            *
 * Purpose: Return user permissions for access to trigger                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 * Author:                                                                    *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_trigger_permission(zbx_uint64_t userid, zbx_uint64_t triggerid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		perm = PERM_DENY, host_perm;
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_trigger_permission()");

	result = DBselect("select distinct i.hostid from items i,functions f"
			" where i.itemid=f.itemid and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		host_perm = get_host_permission(userid, hostid);

		if (perm < host_perm)
			perm = host_perm;
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of get_trigger_permission():%s",
			zbx_permission_string(perm));

	return perm;
}

static void	add_user_msg(int source, zbx_uint64_t userid, zbx_uint64_t mediatypeid,
		zbx_uint64_t triggerid, ZBX_USER_MSG **user_msg, char *subject, char *message)
{
	const char	*__function_name = "add_user_msg";
	ZBX_USER_MSG	*p;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != check_perm2system(userid))
		return;

	if (EVENT_SOURCE_TRIGGERS == source && PERM_READ_ONLY > get_trigger_permission(userid, triggerid))
		return;

	p = *user_msg;

	while (NULL != p)
	{
		if (p->userid == userid && p->mediatypeid == mediatypeid &&
				0 == strcmp(p->subject, subject) && 0 == strcmp(p->message, message))
			break;

		p = p->next;
	}

	if (NULL == p)
	{
		p = zbx_malloc(p, sizeof(ZBX_USER_MSG));

		p->userid = userid;
		p->mediatypeid = mediatypeid;
		p->subject = strdup(subject);
		p->message = strdup(message);
		p->next = *user_msg;

		*user_msg = p;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_object_msg(int source, zbx_uint64_t triggerid, DB_OPERATION *operation, ZBX_USER_MSG **user_msg,
		char *subject, char *message)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	mediatypeid = 0, userid;

	result = DBselect("select mediatypeid from opmediatypes where operationid=" ZBX_FS_UI64,
			operation->operationid);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(mediatypeid, row[0]);

	DBfree_result(result);

	switch (operation->object)
	{
		case OPERATION_OBJECT_USER:
			add_user_msg(source, operation->objectid, mediatypeid, triggerid, user_msg, subject, message);
			break;
		case OPERATION_OBJECT_GROUP:
			result = DBselect("select ug.userid from users_groups ug,usrgrp g"
					" where ug.usrgrpid=" ZBX_FS_UI64 " and g.usrgrpid=ug.usrgrpid and g.users_status=%d",
					operation->objectid,
					GROUP_STATUS_ACTIVE);

			while (NULL != (row = DBfetch(result)))
			{
				ZBX_STR2UINT64(userid, row[0]);
				add_user_msg(source, userid, mediatypeid, triggerid, user_msg, subject, message);
			}

			DBfree_result(result);
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "Unknown object type [%d] for operationid [" ZBX_FS_UI64 "]",
					operation->object,
					operation->operationid);
			zabbix_syslog("Unknown object type [%d] for operationid [" ZBX_FS_UI64 "]",
					operation->object,
					operation->operationid);
			break;
	}
}

static void	add_command_alert(DB_ESCALATION *escalation, DB_EVENT *event, DB_ACTION *action, char *command)
{
	const char	*__function_name = "add_command_alert";
	zbx_uint64_t	alertid;
	int		now;
	char		*command_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	alertid		= DBget_maxid("alerts");
	now		= time(NULL);
	command_esc	= DBdyn_escape_string(command);

	DBexecute("insert into alerts (alertid,actionid,eventid,clock,message,status,alerttype,esc_step)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s',%d,%d,%d)",
			alertid,
			action->actionid,
			event->eventid,
			now,
			command_esc,
			ALERT_STATUS_SENT,
			ALERT_TYPE_COMMAND,
			escalation->esc_step);

	op_run_commands(command);

	zbx_free(command_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_message_alert(DB_ESCALATION *escalation, DB_EVENT *event, DB_ACTION *action,
		zbx_uint64_t userid, zbx_uint64_t mediatypeid, char *subject, char *message)
{
	const char	*__function_name = "add_message_alert";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	alertid;
	int		now, severity, medias = 0;
	char		*sendto_esc, *subject_esc, *message_esc, *error_esc;
	char		error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now		= time(NULL);
	subject_esc	= DBdyn_escape_string_len(subject, ALERT_SUBJECT_LEN);
	message_esc	= DBdyn_escape_string(message);

	if (0 == mediatypeid)
	{
		result = DBselect("select mediatypeid,sendto,severity,period from media"
				" where active=%d and userid=" ZBX_FS_UI64,
				MEDIA_STATUS_ACTIVE,
				userid);
	}
	else
	{
		result = DBselect("select mediatypeid,sendto,severity,period from media"
				" where active=%d and userid=" ZBX_FS_UI64 " and mediatypeid=" ZBX_FS_UI64,
				MEDIA_STATUS_ACTIVE,
				userid,
				mediatypeid);
	}

	while (NULL != (row = DBfetch(result)))
	{
		medias		= 1;

		ZBX_STR2UINT64(mediatypeid, row[0]);
		severity	= atoi(row[2]);

		zabbix_log(LOG_LEVEL_DEBUG, "Trigger severity [%d] Media severity [%d] Period [%s]",
				(int)event->trigger.priority, severity, row[3]);

		if (((1 << event->trigger.priority) & severity) == 0)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Won't send message (severity)");
			continue;
		}

		if (FAIL == check_time_period(row[3], (time_t)NULL))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Won't send message (period)");
			continue;
		}

		alertid		= DBget_maxid("alerts");
		sendto_esc	= DBdyn_escape_string_len(row[1], ALERT_SENDTO_LEN);

		DBexecute("insert into alerts (alertid,actionid,eventid,userid,clock"
				",mediatypeid,sendto,subject,message,status,alerttype,esc_step)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d"
				"," ZBX_FS_UI64 ",'%s','%s','%s',%d,%d,%d)",
				alertid,
				action->actionid,
				event->eventid,
				userid,
				now,
				mediatypeid,
				sendto_esc,
				subject_esc,
				message_esc,
				ALERT_STATUS_NOT_SENT,
				ALERT_TYPE_MESSAGE,
				escalation->esc_step);

		zbx_free(sendto_esc);
	}

	DBfree_result(result);

	if (0 == medias)
	{
		zbx_snprintf(error, sizeof(error), "No media defined for user \"%s\"",
				zbx_user_string(userid));

		alertid		= DBget_maxid("alerts");
		error_esc	= DBdyn_escape_string(error);

		DBexecute("insert into alerts (alertid,actionid,eventid,userid,retries,clock"
				",subject,message,status,alerttype,error,esc_step)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d"
				",'%s','%s',%d,%d,'%s',%d)",
				alertid,
				action->actionid,
				event->eventid,
				userid,
				ALERT_MAX_RETRIES,
				now,
				subject_esc,
				message_esc,
				ALERT_STATUS_FAILED,
				ALERT_TYPE_MESSAGE,
				error_esc,
				escalation->esc_step);

		zbx_free(error_esc);
	}

	zbx_free(subject_esc);
	zbx_free(message_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: check_operation_conditions                                       *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters: event - event to check                                         *
 *             actionid - action ID for matching                              *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	check_operation_conditions(DB_EVENT *event, DB_OPERATION *operation)
{
	const char	*__function_name = "check_operation_conditions";

	DB_RESULT	result;
	DB_ROW		row;
	DB_CONDITION	condition;

	int	ret = SUCCEED; /* SUCCEED required for ACTION_EVAL_TYPE_AND_OR */
	int	cond, old_type = -1, exit = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): operationid [" ZBX_FS_UI64 "]", __function_name, operation->operationid);

	result = DBselect("select conditiontype,operator,value"
				" from opconditions"
				" where operationid=" ZBX_FS_UI64
				" order by conditiontype",
			operation->operationid);

	while (NULL != (row = DBfetch(result)) && 0 == exit)
	{
		memset(&condition, 0, sizeof(condition));
		condition.conditiontype	= atoi(row[0]);
		condition.operator	= atoi(row[1]);
		condition.value		= row[2];

		switch (operation->evaltype)
		{
			case ACTION_EVAL_TYPE_AND_OR:
				if (old_type == condition.conditiontype)	/* OR conditions */
				{
					if (SUCCEED == check_action_condition(event, &condition))
						ret = SUCCEED;
				}
				else						/* AND conditions */
				{
					/* Break if PREVIOUS AND condition is FALSE */
					if (ret == FAIL)
						exit = 1;
					else if (FAIL == check_action_condition(event, &condition))
						ret = FAIL;
				}
				old_type = condition.conditiontype;
				break;
			case ACTION_EVAL_TYPE_AND:
				cond = check_action_condition(event, &condition);
				/* Break if any of AND conditions is FALSE */
				if (cond == FAIL)
				{
					ret = FAIL;
					exit = 1;
				}
				else
					ret = SUCCEED;
				break;
			case ACTION_EVAL_TYPE_OR:
				cond = check_action_condition(event, &condition);
				/* Break if any of OR conditions is TRUE */
				if (cond == SUCCEED)
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
				break;
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	execute_operations(DB_ESCALATION *escalation, DB_EVENT *event, DB_ACTION *action)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_OPERATION	operation;
	int		esc_period = 0, operations = 0;
	ZBX_USER_MSG	*user_msg = NULL, *p;
	char		*shortdata, *longdata;

	zabbix_log(LOG_LEVEL_DEBUG, "In execute_operations()");

	if (0 == action->esc_period)
	{
		result = DBselect("select operationid,operationtype,object,objectid,default_msg,shortdata,longdata"
				",esc_period,evaltype from operations where actionid=" ZBX_FS_UI64 " and operationtype in (%d,%d)",
				action->actionid,
				OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND);
	}
	else
	{
		escalation->esc_step++;

		result = DBselect("select operationid,operationtype,object,objectid,default_msg,shortdata,longdata"
				",esc_period,evaltype from operations where actionid=" ZBX_FS_UI64 " and operationtype in (%d,%d)"
				" and esc_step_from<=%d and (esc_step_to=0 or esc_step_to>=%d)",
				action->actionid,
				OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND,
				escalation->esc_step,
				escalation->esc_step);
	}

	while (NULL != (row = DBfetch(result)))
	{
		memset(&operation, 0, sizeof(operation));
		ZBX_STR2UINT64(operation.operationid, row[0]);
		operation.actionid	= action->actionid;
		operation.operationtype	= atoi(row[1]);
		operation.object	= atoi(row[2]);
		ZBX_STR2UINT64(operation.objectid, row[3]);
		operation.default_msg	= atoi(row[4]);
		operation.shortdata	= strdup(row[5]);
		operation.longdata	= strdup(row[6]);
		operation.esc_period	= atoi(row[7]);
		operation.evaltype	= atoi(row[8]);

		if (SUCCEED == check_operation_conditions(event, &operation))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions match our event. Execute operation.");

			substitute_simple_macros(event, NULL, NULL, NULL, NULL, &operation.shortdata,
					MACRO_TYPE_MESSAGE, NULL, 0);
			substitute_simple_macros(event, NULL, NULL, NULL, NULL, &operation.longdata,
					MACRO_TYPE_MESSAGE, NULL, 0);

			if (0 == esc_period || esc_period > operation.esc_period)
				esc_period = operation.esc_period;

			switch (operation.operationtype)
			{
				case OPERATION_TYPE_MESSAGE:
					if (0 == operation.default_msg)
					{
						shortdata = operation.shortdata;
						longdata = operation.longdata;
					}
					else
					{
						shortdata = action->shortdata;
						longdata = action->longdata;
					}

					add_object_msg(event->source, escalation->triggerid, &operation, &user_msg, shortdata, longdata);
					break;
				case OPERATION_TYPE_COMMAND:
					add_command_alert(escalation, event, action, operation.longdata);
					break;
				default:
					break;
			}
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions do not match our event. Do not execute operation.");

		zbx_free(operation.shortdata);
		zbx_free(operation.longdata);

		operations = 1;
	}

	DBfree_result(result);

	while (NULL != user_msg)
	{
		p = user_msg;
		user_msg = user_msg->next;

		add_message_alert(escalation, event, action, p->userid, p->mediatypeid, p->subject, p->message);

		zbx_free(p->subject);
		zbx_free(p->message);
		zbx_free(p);
	}

	if (0 == action->esc_period)
	{
		escalation->status = (action->recovery_msg == 1) ? ESCALATION_STATUS_SLEEP : ESCALATION_STATUS_COMPLETED;
	}
       	else
	{
		if (0 == operations)
		{
			result = DBselect("select operationid from operations where actionid=" ZBX_FS_UI64 " and esc_step_from>%d",
					action->actionid,
					escalation->esc_step);

			if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
				operations = 1;

			DBfree_result(result);
		}

		if (1 == operations)
		{
			esc_period = (0 != esc_period) ? esc_period : action->esc_period;
			escalation->nextcheck = time(NULL) + esc_period;
		}
		else
			escalation->status = (action->recovery_msg == 1) ? ESCALATION_STATUS_SLEEP : ESCALATION_STATUS_COMPLETED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of execute_operations()");
}

static void	process_recovery_msg(DB_ESCALATION *escalation, DB_EVENT *r_event, DB_ACTION *action)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid, mediatypeid;

	if (1 == action->recovery_msg)
	{
		result = DBselect("select distinct userid,mediatypeid from alerts where actionid=" ZBX_FS_UI64
				" and eventid=" ZBX_FS_UI64 " and mediatypeid<>0 and alerttype=%d",
				action->actionid,
				escalation->eventid,
				ALERT_TYPE_MESSAGE);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(userid, row[0]);
			ZBX_STR2UINT64(mediatypeid, row[1]);

			escalation->esc_step = 0;
			add_message_alert(escalation, r_event, action, userid, mediatypeid, action->shortdata, action->longdata);
		}

		DBfree_result(result);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Escalation stopped: recovery message not defined",
				escalation->actionid);

	escalation->status = ESCALATION_STATUS_COMPLETED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_info                                                   *
 *                                                                            *
 * Purpose: get event and trigger info to event structure                     *
 *                                                                            *
 * Parameters: eventid - [IN] requested event id                              *
 *             event   - [OUT] event data                                     *
 *                                                                            *
 * Return value: SUCCEED if processed successfully, FAIL - otherwise          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: use 'free_event_info' function to clear allocated memory         *
 *                                                                            *
 ******************************************************************************/
static int	get_event_info(zbx_uint64_t eventid, DB_EVENT *event)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	memset(event, 0, sizeof(DB_EVENT));

	result = DBselect("select eventid,source,object,objectid,clock,value,acknowledged"
			" from events where eventid=" ZBX_FS_UI64,
			eventid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(event->eventid, row[0]);
		event->source		= atoi(row[1]);
		event->object		= atoi(row[2]);
		ZBX_STR2UINT64(event->objectid, row[3]);
		event->clock		= atoi(row[4]);
		event->value		= atoi(row[5]);
		event->acknowledged	= atoi(row[6]);

		res = SUCCEED;
	}
	DBfree_result(result);

	if (res == SUCCEED && event->object == EVENT_OBJECT_TRIGGER)
	{
		result = DBselect("select description,expression,priority,comments,url"
				" from triggers where triggerid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = DBfetch(result)))
		{
			event->trigger.triggerid = event->objectid;
			strscpy(event->trigger.description, row[0]);
			strscpy(event->trigger.expression, row[1]);
			event->trigger.priority = (unsigned char)atoi(row[2]);
			event->trigger.comments = zbx_strdup(event->trigger.comments, row[3]);
			event->trigger.url = zbx_strdup(event->trigger.url, row[4]);
		}
		DBfree_result(result);
	}
	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: free_event_info                                                  *
 *                                                                            *
 * Purpose: clean allocated memory by function 'get_event_info'               *
 *                                                                            *
 * Parameters: event - [IN] event data                                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	free_event_info(DB_EVENT *event)
{
	zbx_free(event->trigger.comments);
	zbx_free(event->trigger.url);
}

static void	execute_escalation(DB_ESCALATION *escalation)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ACTION	action;
	DB_EVENT	event;
	char		*error = NULL;
	int		source = (-1);

	zabbix_log(LOG_LEVEL_DEBUG, "In execute_escalation()");

	result = DBselect("select source from events where eventid=" ZBX_FS_UI64,
			escalation->eventid);
	if (NULL == (row = DBfetch(result)))
		error = zbx_dsprintf(error, "Event [" ZBX_FS_UI64 "] deleted.",
				escalation->eventid);
	else
		source = atoi(row[0]);
	DBfree_result(result);

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* Trigger disabled? */
		result = DBselect("select description,status from triggers where triggerid=" ZBX_FS_UI64,
				escalation->triggerid);
		if (NULL == (row = DBfetch(result)))
			error = zbx_dsprintf(error, "Trigger [" ZBX_FS_UI64 "] deleted.",
					escalation->triggerid);
		else if (TRIGGER_STATUS_DISABLED == atoi(row[1]))
			error = zbx_dsprintf(error, "Trigger '%s' disabled.",
					row[0]);
		DBfree_result(result);
	}

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* Item disabled? */
		result = DBselect("select i.description from items i,functions f,triggers t"
				" where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid"
				" and i.itemid=f.itemid and i.status=%d",
				escalation->triggerid,
				ITEM_STATUS_DISABLED);
		if (NULL != (row = DBfetch(result)))
			error = zbx_dsprintf(error, "Item '%s' disabled.",
					row[0]);
		DBfree_result(result);
	}

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* Host disabled? */
		result = DBselect("select h.host from hosts h,items i,functions f,triggers t"
				" where t.triggerid=" ZBX_FS_UI64 " and t.triggerid=f.triggerid"
				" and f.itemid=i.itemid and i.hostid=h.hostid and h.status=%d",
				escalation->triggerid,
				HOST_STATUS_NOT_MONITORED);
		if (NULL != (row = DBfetch(result)))
			error = zbx_dsprintf(error, "Host '%s' disabled.",
					row[0]);
		DBfree_result(result);
	}

	switch (escalation->status)
	{
		case ESCALATION_STATUS_ACTIVE:
			result = DBselect("select actionid,eventsource,esc_period,def_shortdata,def_longdata,recovery_msg,status,name"
					" from actions where actionid=" ZBX_FS_UI64,
					escalation->actionid);
			break;
		case ESCALATION_STATUS_RECOVERY:
			result = DBselect("select actionid,eventsource,esc_period,r_shortdata,r_longdata,recovery_msg,status,name"
					" from actions where actionid=" ZBX_FS_UI64,
					escalation->actionid);
			break;
		default:
			/* Never reached */
			return;
	}

	if (NULL != (row = DBfetch(result)))
	{
		memset(&action, 0, sizeof(action));
		ZBX_STR2UINT64(action.actionid, row[0]);
		action.eventsource	= atoi(row[1]);
		action.esc_period	= atoi(row[2]);
		action.shortdata	= strdup(row[3]);
		action.recovery_msg	= atoi(row[5]);

		if (ACTION_STATUS_ACTIVE != atoi(row[6]))
			error = zbx_dsprintf(error, "Action '%s' disabled.",
					row[7]);

		if (NULL != error)
			action.longdata = zbx_dsprintf(action.longdata, "NOTE: Escalation cancelled: %s\n%s",
					error,
					row[4]);
		else
			action.longdata = strdup(row[4]);

		switch (escalation->status)
		{
			case ESCALATION_STATUS_ACTIVE:
				if (SUCCEED == get_event_info(escalation->eventid, &event))
				{
					substitute_simple_macros(&event, NULL, NULL, NULL, NULL,
							&action.shortdata, MACRO_TYPE_MESSAGE, NULL, 0);
					substitute_simple_macros(&event, NULL, NULL, NULL, NULL,
							&action.longdata, MACRO_TYPE_MESSAGE, NULL, 0);

					execute_operations(escalation, &event, &action);
				}
				free_event_info(&event);
				break;
			case ESCALATION_STATUS_RECOVERY:
				if (SUCCEED == get_event_info(escalation->r_eventid, &event))
				{
					substitute_simple_macros(&event, NULL, NULL, NULL, escalation,
							&action.shortdata, MACRO_TYPE_MESSAGE, NULL, 0);
					substitute_simple_macros(&event, NULL, NULL, NULL, escalation,
							&action.longdata, MACRO_TYPE_MESSAGE, NULL, 0);

					process_recovery_msg(escalation, &event, &action);
				}
				free_event_info(&event);
				break;
			default:
				break;
		}

		zbx_free(action.shortdata);
		zbx_free(action.longdata);
	}
	else
		error = zbx_dsprintf(error, "Action [" ZBX_FS_UI64 "] deleted",
				escalation->actionid);
	DBfree_result(result);

	if (NULL != error)
	{
		escalation->status = ESCALATION_STATUS_COMPLETED;
		zabbix_log(LOG_LEVEL_WARNING, "Escalation cancelled: %s",
				error);
		zbx_free(error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of execute_escalation()");
}

static void	process_escalations(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ESCALATION	escalation;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_escalations()");

	result = DBselect("select escalationid,actionid,triggerid,eventid,r_eventid,esc_step,status"
			" from escalations where status in (%d,%d,%d,%d) and nextcheck<=%d" DB_NODE,
			ESCALATION_STATUS_ACTIVE,
			ESCALATION_STATUS_SUPERSEDED_ACTIVE,
			ESCALATION_STATUS_SUPERSEDED_RECOVERY,
			ESCALATION_STATUS_RECOVERY,
			now,
			DBnode_local("escalationid"));

	while (NULL != (row = DBfetch(result)))
	{
		memset(&escalation, 0, sizeof(escalation));
		ZBX_STR2UINT64(escalation.escalationid, row[0]);
		ZBX_STR2UINT64(escalation.actionid, row[1]);
		ZBX_STR2UINT64(escalation.triggerid, row[2]);
		ZBX_STR2UINT64(escalation.eventid, row[3]);
		ZBX_STR2UINT64(escalation.r_eventid, row[4]);
		escalation.esc_step	= atoi(row[5]);
		escalation.status	= atoi(row[6]);
		escalation.nextcheck	= 0;

		DBbegin();

		if (escalation.status == ESCALATION_STATUS_SUPERSEDED_ACTIVE)
		{
			escalation.status = ESCALATION_STATUS_ACTIVE;
			execute_escalation(&escalation);
			DBexecute("delete from escalations where escalationid=" ZBX_FS_UI64 " and status=%d",
					escalation.escalationid,
					ESCALATION_STATUS_SUPERSEDED_ACTIVE);
			DBexecute("update escalations set status=%d where escalationid=" ZBX_FS_UI64 " and status=%d",
					ESCALATION_STATUS_RECOVERY,
					escalation.escalationid,
					ESCALATION_STATUS_SUPERSEDED_RECOVERY);
		}
		else if (escalation.status == ESCALATION_STATUS_SUPERSEDED_RECOVERY)
		{
			escalation.status = ESCALATION_STATUS_ACTIVE;
			execute_escalation(&escalation);
			escalation.status = ESCALATION_STATUS_RECOVERY;
			execute_escalation(&escalation);
			DBremove_escalation(escalation.escalationid);
		}
		else
		{
			execute_escalation(&escalation);

			if (escalation.status == ESCALATION_STATUS_COMPLETED)
				DBremove_escalation(escalation.escalationid);
			else
				DBexecute("update escalations set status=%d,esc_step=%d,nextcheck=%d"
						" where escalationid=" ZBX_FS_UI64
							" and status=%d",
						escalation.status,
						escalation.esc_step,
						escalation.nextcheck,
						escalation.escalationid,
						ESCALATION_STATUS_ACTIVE);
		}

		DBcommit();
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of process_escalations()");
}

/******************************************************************************
 *                                                                            *
 * Function: main_escalator_loop                                              *
 *                                                                            *
 * Purpose: periodically check table escalations and generate alerts          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void	main_escalator_loop()
{
	int	now;
	double	sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_escalator_loop()");

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [processing escalations]", get_process_type_string(process_type));

		now = time(NULL);
		sec = zbx_time();
		process_escalations(now);
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s #%d spent " ZBX_FS_DBL " seconds while processing escalations",
				get_process_type_string(process_type), process_num, sec);

		zbx_sleep_loop(CONFIG_ESCALATOR_FREQUENCY);
	}
}
