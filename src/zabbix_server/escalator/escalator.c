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

#include "escalator.h"
#include "../operations.h"
#include "../events.h"
#include "../actions.h"

#define CONFIG_ESCALATOR_FREQUENCY	3

#define ZBX_USER_MSG struct zxb_user_msg_t
ZBX_USER_MSG
{
	zbx_uint64_t	userid;
	char		*subject;
	char		*message;
	void		*next;
};

static int	check_perm2system(zbx_uint64_t userid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;

	result = DBselect( "select count(g.usrgrpid) from usrgrp g, users_groups ug where ug.userid=" ZBX_FS_UI64
			" and g.usrgrpid = ug.usrgrpid and g.users_status=%d",
			userid,
			GROUP_STATUS_DISABLED);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]) && atoi(row[0]) > 0)
		res = FAIL;

	DBfree_result(result);

	return res;
}

static void	add_user_msg(zbx_uint64_t userid, ZBX_USER_MSG **user_msg, char *subject, char *message)
{
	ZBX_USER_MSG	*p;

	if (SUCCEED != check_perm2system(userid))
		return;

	p = *user_msg;
	while (NULL != p) {
		if (p->userid == userid && 0 == strcmp(p->subject, subject)
				&& 0 == strcmp(p->message, message))
			break;

		p = p->next;
	}

	if (NULL == p) {
		p = zbx_malloc(p, sizeof(ZBX_USER_MSG));

		p->userid = userid;
		p->subject = strdup(subject);
		p->message = strdup(message);
		p->next = *user_msg;

		*user_msg = p;
	}
}

static void	add_object_msg(DB_OPERATION *operation, ZBX_USER_MSG **user_msg, char *subject, char *message)
{
	DB_RESULT	result;
	DB_ROW		row;

	switch (operation->object) {
		case OPERATION_OBJECT_USER:
			add_user_msg(operation->objectid, user_msg, subject, message);
			break;
		case OPERATION_OBJECT_GROUP:
			result = DBselect("select ug.userid from users_groups ug,usrgrp g"
					" WHERE ug.usrgrpid=" ZBX_FS_UI64 " AND g.usrgrpid=ug.usrgrpid AND g.users_status=%d",
					operation->objectid,
					GROUP_STATUS_ACTIVE);

			while (NULL != (row = DBfetch(result)))
				add_user_msg(zbx_atoui64(row[0]), user_msg, subject, message);

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
	zbx_uint64_t	alertid;
	int		now;
	char		*command_esc	= NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In add_command_alert()");
/*	zabbix_log(LOG_LEVEL_DEBUG,"----- COMMAND\n\tcommand: %s", command);*/

	alertid		= DBget_maxid("alerts", "alertid");
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
}

static void	add_message_alert(DB_ESCALATION *escalation, DB_EVENT *event, DB_ACTION *action, zbx_uint64_t eventid, zbx_uint64_t userid, char *subject, char *message)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	alertid, mediatypeid;
	int		now, severity, medias = 0;
	char		*sendto_esc	= NULL;
	char		*subject_esc	= NULL;
	char		*message_esc	= NULL;
	char		*error_esc	= NULL;
	char		error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In add_message_alert()");
/*	zabbix_log(LOG_LEVEL_DEBUG,"MESSAGE\n\tuserid : " ZBX_FS_UI64 "\n\tsubject: %s\n\tmessage: %s", userid, subject, message);*/

	now		= time(NULL);
	subject_esc	= DBdyn_escape_string(subject);
	message_esc	= DBdyn_escape_string(message);

	result = DBselect("select mediatypeid,sendto,severity,period from media"
			" where active=%d and userid=" ZBX_FS_UI64,
			MEDIA_STATUS_ACTIVE,
			userid);

	while (NULL != (row = DBfetch(result))) {
		medias		= 1;

		mediatypeid	= zbx_atoui64(row[0]);
		severity	= atoi(row[2]);

		zabbix_log( LOG_LEVEL_DEBUG, "Trigger severity [%d] Media severity [%d] Period [%s]",
			event->trigger_priority,
			severity,
			row[3]);

		if (((1 << event->trigger_priority) & severity) == 0) {
			zabbix_log( LOG_LEVEL_DEBUG, "Won't send message (severity)");
			continue;
		}

		if (check_time_period(row[3], (time_t)NULL) == 0) {
			zabbix_log( LOG_LEVEL_DEBUG, "Won't send message (period)");
			continue;
		}

		alertid		= DBget_maxid("alerts", "alertid");
		sendto_esc	= DBdyn_escape_string(row[1]);

		DBexecute("insert into alerts (alertid,actionid,eventid,userid,clock"
				",mediatypeid,sendto,subject,message,status,alerttype,esc_step)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d"
				"," ZBX_FS_UI64 ",'%s','%s','%s',%d,%d,%d)",
				alertid,
				action->actionid,
				eventid,
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

	if (0 == medias) {
		result = DBselect("select name,surname,alias from users where userid=" ZBX_FS_UI64,
				userid);

		if (NULL != (row = DBfetch(result))) {
			zbx_snprintf(error, sizeof(error), "No media defined for user %s %s (%s)",
					row[0],
					row[1],
					row[2]);
		} else
			zbx_snprintf(error, sizeof(error), "No media defined");

		DBfree_result(result);

		alertid		= DBget_maxid("alerts", "alertid");
		error_esc	= DBdyn_escape_string(error);

		DBexecute("insert into alerts (alertid,actionid,eventid,userid,retries,clock"
				",subject,message,status,alerttype,error,esc_step)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d"
				",'%s','%s',%d,%d,'%s',%d)",
				alertid,
				action->actionid,
				eventid,
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
}

/******************************************************************************
 *                                                                            *
 * Function: check_operation_conditions                                       *
 *                                                                            *
 * Purpose:                                                                   *
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
static int	check_operation_conditions(DB_EVENT *event, DB_OPERATION *operation)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_CONDITION	condition;

	/* SUCCEED required for ACTION_EVAL_TYPE_AND_OR */	
	int	ret = SUCCEED;
	int	old_type = -1;
	int	cond;
	int	num = 0;
	int	exit = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In check_opeartion_conditions (operationid:" ZBX_FS_UI64 ")",
			operation->operationid);

	result = DBselect("select conditiontype,operator,value from opconditions where operationid=" ZBX_FS_UI64 " order by conditiontype",
			operation->operationid);

	while (NULL != (row = DBfetch(result)) && (0 == exit)) {
		num++;

		memset(&condition, 0, sizeof(condition));
		condition.conditiontype	= atoi(row[0]);
		condition.operator	= atoi(row[1]);
		condition.value		= row[2];

		switch (operation->evaltype) {
			case ACTION_EVAL_TYPE_AND_OR:
				if (old_type == condition.conditiontype) {	/* OR conditions */
					if (SUCCEED == check_action_condition(event, &condition))
						ret = SUCCEED;
				} else {					/* AND conditions */
					/* Break if PREVIOUS AND condition is FALSE */
					if (ret == FAIL)
						exit	= 1;
					else if (FAIL == check_action_condition(event, &condition))
						ret	= FAIL;
				}
		
				old_type = condition.conditiontype;
				break;
			case ACTION_EVAL_TYPE_AND:
				cond = check_action_condition(event, &condition);
				/* Break if any of AND conditions is FALSE */
				if(cond == FAIL) {
					ret	= FAIL;
					exit	= 1;
				} else
					ret	= SUCCEED;
				break;
			case ACTION_EVAL_TYPE_OR:
				cond = check_action_condition(event, &condition);
				/* Break if any of OR conditions is TRUE */
				if (cond == SUCCEED) {
					ret	= SUCCEED;
					exit	= 1;
				} else
					ret	= FAIL;
				break;
			default:
				ret	= FAIL;
				exit	= 1;
				break;

		}

	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End check_opeartion_conditions():%s",
			FAIL == ret ? "FALSE" : "TRUE");

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

	while (NULL != (row = DBfetch(result))) {
		memset(&operation, 0, sizeof(operation));
		operation.operationid	= zbx_atoui64(row[0]);
		operation.actionid	= action->actionid;
		operation.operationtype	= atoi(row[1]);
		operation.object	= atoi(row[2]);
		operation.objectid	= zbx_atoui64(row[3]);
		operation.default_msg	= atoi(row[4]);
		operation.shortdata	= strdup(row[5]);
		operation.longdata	= strdup(row[6]);
		operation.esc_period	= atoi(row[7]);
		operation.evaltype	= atoi(row[8]);

		if (SUCCEED == check_operation_conditions(event, &operation)) {
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions match our event. Execute operation.");

			substitute_macros(event, action, &operation.shortdata);
			substitute_macros(event, action, &operation.longdata);

			if (0 == esc_period || esc_period > operation.esc_period)
				esc_period = operation.esc_period;

			switch (operation.operationtype) {
				case	OPERATION_TYPE_MESSAGE:
					if (0 == operation.default_msg) {
						shortdata = operation.shortdata;
						longdata = operation.longdata;
					} else {
						shortdata = action->shortdata;
						longdata = action->longdata;
					}
					
					add_object_msg(&operation, &user_msg, shortdata, longdata);
					break;
				case	OPERATION_TYPE_COMMAND:
					add_command_alert(escalation, event, action, operation.longdata);
					break;
				default:
					break;
			}
		} else
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions do not match our event. Do not execute operation.");

		zbx_free(operation.shortdata);
		zbx_free(operation.longdata);

		operations = 1;
	}

	DBfree_result(result);

	while (NULL != user_msg) {
		p = user_msg;
		user_msg = user_msg->next;

		add_message_alert(escalation, event, action, event->eventid, p->userid, p->subject, p->message);

		zbx_free(p->subject);
		zbx_free(p->message);
		zbx_free(p);
	}

	if (0 == action->esc_period) {
		escalation->status = (action->recovery_msg == 1) ? ESCALATION_STATUS_SLEEP : ESCALATION_STATUS_COMPLETED;
	} else {
		if (0 == operations) {
			result = DBselect("select operationid from operations where actionid=" ZBX_FS_UI64 " and esc_step_from>%d",
					action->actionid,
					escalation->esc_step);

			if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
				operations = 1;

			DBfree_result(result);
		}

		if (1 == operations) {
			esc_period = (0 != esc_period) ? esc_period : action->esc_period;
			escalation->nextcheck = time(NULL) + esc_period;
		} else
			escalation->status = (action->recovery_msg == 1) ? ESCALATION_STATUS_SLEEP : ESCALATION_STATUS_COMPLETED;
	}
}

static void	process_recovery_msg(DB_ESCALATION *escalation, DB_EVENT *r_event, DB_ACTION *action)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid;
	
	if (1 == action->recovery_msg) {
		result = DBselect("select distinct userid from alerts where actionid=" ZBX_FS_UI64
				" and eventid=" ZBX_FS_UI64 " and alerttype=%d",
				action->actionid,
				escalation->eventid,
				ALERT_TYPE_MESSAGE);

		while (NULL != (row = DBfetch(result))) {
			userid = zbx_atoui64(row[0]);

			add_message_alert(escalation, r_event, action, escalation->r_eventid, userid, action->shortdata, action->longdata);
		}

		DBfree_result(result);
	} else {
		zabbix_log(LOG_LEVEL_DEBUG, "Escalation stopped: recovery message not defined",
				escalation->actionid);
		DBremove_escalation(escalation->escalationid);
	}

	escalation->status = ESCALATION_STATUS_COMPLETED;
}

static int	get_event(zbx_uint64_t eventid, DB_EVENT *event)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	result = DBselect("select eventid,source,object,objectid,clock,value,acknowledged"
			" from events where eventid=" ZBX_FS_UI64,
			eventid);

	if (NULL != (row = DBfetch(result)))
	{
		memset(event, 0, sizeof(DB_EVENT));
		event->eventid		= zbx_atoui64(row[0]);
		event->source		= atoi(row[1]);
		event->object		= atoi(row[2]);
		event->objectid		= zbx_atoui64(row[3]);
		event->clock		= atoi(row[4]);
		event->value		= atoi(row[5]);
		event->acknowledged	= atoi(row[6]);

		add_trigger_info(event);

		res = SUCCEED;
	}

	DBfree_result(result);

	return res;
}

static void	execute_escalation(DB_ESCALATION *escalation)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ACTION	action;
	DB_EVENT	event;

	switch (escalation->status) {
		case ESCALATION_STATUS_ACTIVE:
			result = DBselect("select actionid,eventsource,esc_period,def_shortdata,def_longdata,recovery_msg"
					" from actions where actionid=" ZBX_FS_UI64 " and status=%d",
					escalation->actionid,
					ACTION_STATUS_ACTIVE);
			break;
		case ESCALATION_STATUS_RECOVERY:
			result = DBselect("select actionid,eventsource,esc_period,r_shortdata,r_longdata,recovery_msg"
					" from actions where actionid=" ZBX_FS_UI64 " and status=%d",
					escalation->actionid,
					ACTION_STATUS_ACTIVE);
			break;
		default:
			/* Never reached */
			return;
	}

	if (NULL != (row = DBfetch(result))) {
		memset(&action, 0, sizeof(action));
		action.actionid		= zbx_atoui64(row[0]);
		action.eventsource	= atoi(row[1]);
		action.esc_period	= atoi(row[2]);
		action.shortdata	= strdup(row[3]);
		action.longdata		= strdup(row[4]);
		action.recovery_msg	= atoi(row[5]);

		switch (escalation->status) {
			case ESCALATION_STATUS_ACTIVE:
			case ESCALATION_STATUS_RECOVERY:
			default:
				break;
		}

		switch (escalation->status) {
			case ESCALATION_STATUS_ACTIVE:
				if (SUCCEED == get_event(escalation->eventid, &event))
				{
					substitute_macros(&event, &action, &action.shortdata);
					substitute_macros(&event, &action, &action.longdata);

					execute_operations(escalation, &event, &action);
				}
				break;
			case ESCALATION_STATUS_RECOVERY:
				if (SUCCEED == get_event(escalation->r_eventid, &event))
				{
					substitute_macros(&event, &action, &action.shortdata);
					substitute_macros(&event, &action, &action.longdata);

					process_recovery_msg(escalation, &event, &action);
				}
				break;
			default:
				break;
		}

		zbx_free(action.shortdata);
		zbx_free(action.longdata);
	} else {
		zabbix_log(LOG_LEVEL_DEBUG, "Escalation canceled: action [" ZBX_FS_UI64 "] not found",
				escalation->actionid);
		DBremove_escalation(escalation->escalationid);
	}

	DBfree_result(result);
}

static void	process_escalations(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ESCALATION	escalation;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_escalations()");

	result = DBselect("select escalationid,actionid,eventid,r_eventid,esc_step,status"
			" from escalations where status in (%d,%d) and nextcheck<=%d" DB_NODE,
			ESCALATION_STATUS_ACTIVE,
			ESCALATION_STATUS_RECOVERY,
			now,
			DBnode_local("escalationid"));

	while (NULL != (row = DBfetch(result))) {
		memset(&escalation, 0, sizeof(escalation));
		escalation.escalationid		= zbx_atoui64(row[0]);
		escalation.actionid		= zbx_atoui64(row[1]);
		escalation.eventid		= zbx_atoui64(row[2]);
		escalation.r_eventid		= zbx_atoui64(row[3]);
		escalation.esc_step		= atoi(row[4]);
		escalation.status		= atoi(row[5]);
		escalation.nextcheck		= 0;

		DBbegin();

		execute_escalation(&escalation);

		if (escalation.status == ESCALATION_STATUS_COMPLETED)
			DBremove_escalation(escalation.escalationid);
		else
			DBexecute("update escalations set status=%d,esc_step=%d,nextcheck=%d"
					" where escalationid=" ZBX_FS_UI64,
					escalation.status,
					escalation.esc_step,
					escalation.nextcheck,
					escalation.escalationid);

		DBcommit();
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: get_minnextcheck                                                 *
 *                                                                            *
 * Purpose: calculate when we have to process earliest escalations            *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: timestamp of earliest check or -1 if not found               *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
/*static int get_minnextcheck()
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_minnextcheck()");

	result = DBselect("select count(*),min(nextcheck) from escalations where status in (%d,%d)" DB_NODE,
			ESCALATION_STATUS_ACTIVE,
			ESCALATION_STATUS_RECOVERY,
			DBnode_local("escalationid"));

	if (NULL == (row = DBfetch(result)) || DBis_null(row[0]) == SUCCEED || DBis_null(row[1]) == SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
		res = FAIL; 
	}
	else
	{
		if (atoi(row[0]) == 0)
		{
			res = FAIL;
		}
		else
		{
			res = atoi(row[1]);
		}
	}
	DBfree_result(result);

	return res;
}*/

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
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
int main_escalator_loop()
{
	int			now/*, nextcheck, sleeptime*/;
	double			sec;
	struct sigaction	phan;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_escalator_loop()");

	phan.sa_handler = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	zbx_setproctitle("escalator [connecting to the database]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;) {
		now = time(NULL);
		sec = zbx_time();

		zbx_setproctitle("escalator [processing escalations]");

		process_escalations(now);

		sec = zbx_time() - sec;

/*		nextcheck = get_minnextcheck();

		if (FAIL == nextcheck)
			sleeptime = CONFIG_ESCALATOR_FREQUENCY;
		else {
			sleeptime = nextcheck - time(NULL);
			if (sleeptime < 0)
				sleeptime = 0;
			else if (sleeptime > CONFIG_ESCALATOR_FREQUENCY)
				sleeptime = CONFIG_ESCALATOR_FREQUENCY;
		}*/

		zabbix_log(LOG_LEVEL_DEBUG, "Escalator spent " ZBX_FS_DBL " seconds while processing escalation items."
				" Nextcheck after %d sec.",
				sec,
				CONFIG_ESCALATOR_FREQUENCY);

		zbx_setproctitle("escalator [sleeping for %d seconds]", 
				CONFIG_ESCALATOR_FREQUENCY);

		sleep(CONFIG_ESCALATOR_FREQUENCY);
/*		if (sleeptime > 0) {
			zbx_setproctitle("escalator [sleeping for %d seconds]", 
					sleeptime);

			sleep(sleeptime);
		}*/
	}

	/* Never reached */
	DBclose();
}
