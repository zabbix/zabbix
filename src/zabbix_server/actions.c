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
#include "security.h"

#include "actions.h"
#include "expression.h"

#include "poller/poller.h"
#include "poller/checks_agent.h"

/******************************************************************************
 *                                                                            *
 * Function: check_time_period                                                *
 *                                                                            *
 * Purpose: check if current time is within given period                      *
 *                                                                            *
 * Parameters: period - time period in format [d1-d2,hh:mm-hh:mm]*            *
 *                                                                            *
 * Return value: 0 - out of period, 1 - within the period                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static	int	check_time_period(const char *period)
{
	time_t	now;
	char	tmp[MAX_STRING_LEN];
	char	*s;
	int	d1,d2,h1,h2,m1,m2;
	int	day, hour, min;
	struct  tm      *tm;
	int	ret = 0;


	zabbix_log( LOG_LEVEL_DEBUG, "In check_time_period(%s)",period);

	now = time(NULL);
	tm = localtime(&now);

	day=tm->tm_wday;
	if(0 == day)	day=7;
	hour = tm->tm_hour;
	min = tm->tm_min;

	strscpy(tmp,period);
       	s=(char *)strtok(tmp,";");
	while(s!=NULL)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Period [%s]",s);

		if(sscanf(s,"%d-%d,%d:%d-%d:%d",&d1,&d2,&h1,&m1,&h2,&m2) == 6)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "%d-%d,%d:%d-%d:%d",d1,d2,h1,m1,h2,m2);
			if( (day>=d1) && (day<=d2) && (60*hour+min>=60*h1+m1) && (60*hour+min<=60*h2+m2))
			{
				ret = 1;
				break;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Time period format is wrong [%s]",period);
		}

       		s=(char *)strtok(NULL,";");
	}
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_to_user_medias                                              *
 *                                                                            *
 * Purpose: send notifications to user's medias (email, sms, whatever)        *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *             userid  - user id                                              *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: Cannot use action->userid as it may also be groupid              *
 *                                                                            *
 ******************************************************************************/
static	void	send_to_user_medias(DB_TRIGGER *trigger,DB_ACTION *action, int userid)
{
	DB_MEDIA media;
	char sql[MAX_STRING_LEN];
	DB_RESULT *result;

	int	i;

	snprintf(sql,sizeof(sql)-1,"select mediatypeid,sendto,active,severity,period from media where active=%d and userid=%d",MEDIA_STATUS_ACTIVE,userid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		media.mediatypeid=atoi(DBget_field(result,i,0));
		media.sendto=DBget_field(result,i,1);
		media.active=atoi(DBget_field(result,i,2));
		media.severity=atoi(DBget_field(result,i,3));
		media.period=DBget_field(result,i,4);

		zabbix_log( LOG_LEVEL_DEBUG, "Trigger severity [%d] Media severity [%d] Period [%s]",trigger->priority, media.severity, media.period);
		if(((1<<trigger->priority)&media.severity)==0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Won't send message");
			continue;
		}
		if(check_time_period(media.period) == 0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Won't send message");
			continue;
		}

		DBadd_alert(action->actionid,trigger->triggerid, userid, media.mediatypeid,media.sendto,action->subject,action->message, action->maxrepeats, action->repeatdelay);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: send_to_user                                                     *
 *                                                                            *
 * Purpose: send notifications to user or user groupd                         *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: action->recipient specifies user or group                        *
 *                                                                            *
 ******************************************************************************/
static	void	send_to_user(DB_TRIGGER *trigger,DB_ACTION *action)
{
	char sql[MAX_STRING_LEN];
	DB_RESULT *result;

	int	i;

	if(action->recipient == RECIPIENT_TYPE_USER)
	{
		send_to_user_medias(trigger, action, action->userid);
	}
	else if(action->recipient == RECIPIENT_TYPE_GROUP)
	{
		snprintf(sql,sizeof(sql)-1,"select u.userid from users u, users_groups ug where ug.usrgrpid=%d and ug.userid=u.userid", action->userid);
		result = DBselect(sql);
		for(i=0;i<DBnum_rows(result);i++)
		{
			send_to_user_medias(trigger, action, atoi(DBget_field(result,i,0)));
		}
		DBfree_result(result);
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unknown recipient type [%d] for actionid [%d]",action->recipient,action->actionid);
		zabbix_syslog("Unknown recipient type [%d] for actionid [%d]",action->recipient,action->actionid);
	}
}
/*
void	apply_actions_new(DB_TRIGGER *trigger,int alarmid,int trigger_value)
{
	int escalationid;
	char sql[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_DEBUG, "In apply_actions(triggerid:%d,alarmid:%d,trigger_value:%d)",trigger->triggerid, alarmid, trigger_value);

	if((escalationid=DBget_default_escalation_id())>0)
	{
		snprintf(sql,sizeof(sql)-1,"insert into escalation_log (triggerid,alarmid,escalationid,level,adminlevel,nextcheck,status,actiontype) values (%d,%d,%d,%d,%d,%d,%d,%d)", trigger->triggerid, alarmid, escalationid, 0, 0, 0, 0, ESCALATION_ACTION_NOTHING);
		DBexecute(sql);
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "No default escalation defined");
	}
}*/

/*
 * Apply actions if any.
 */ 
/*void	apply_actions(int triggerid,int good)*/
/*
void	apply_actions_original(DB_TRIGGER *trigger,int alarmid,int trigger_value)
{
	DB_RESULT *result,*result2,*result3;
	
	DB_ACTION action;

	char sql[MAX_STRING_LEN];

	int	i,j;
	int	now;

	zabbix_log( LOG_LEVEL_DEBUG, "In apply_actions(triggerid:%d,alarmid:%d,trigger_value:%d)",trigger->triggerid, alarmid, trigger_value);

	if(TRIGGER_VALUE_TRUE == trigger_value)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Check dependencies");

		snprintf(sql,sizeof(sql)-1,"select count(*) from trigger_depends d,triggers t where d.triggerid_down=%d and d.triggerid_up=t.triggerid and t.value=%d",trigger->triggerid, TRIGGER_VALUE_TRUE);
		result = DBselect(sql);
		if(DBnum_rows(result) == 1)
		{
			if(atoi(DBget_field(result,0,0))>0)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Will not apply actions");
				DBfree_result(result);
				return;
			}
		}
		DBfree_result(result);
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Applying actions");

	now = time(NULL);

	snprintf(sql,sizeof(sql)-1,"select actionid,userid,delay,subject,message,scope,severity,recipient,good,maxrepeats,repeatdelay from actions where (scope=%d and triggerid=%d and (good=%d or good=2) and nextcheck<=%d) or (scope=%d and (good=%d or good=2)) or (scope=%d and (good=%d or good=2))",ACTION_SCOPE_TRIGGER,trigger->triggerid,trigger_value,now,ACTION_SCOPE_HOST,trigger_value,ACTION_SCOPE_HOSTS,trigger_value);
	result = DBselect(sql);
	zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]", sql);

	for(i=0;i<DBnum_rows(result);i++)
	{

		zabbix_log( LOG_LEVEL_DEBUG, "i=[%d]",i);

		action.actionid=atoi(DBget_field(result,i,0));
		action.userid=atoi(DBget_field(result,i,1));
		action.delay=atoi(DBget_field(result,i,2));
		strscpy(action.subject,DBget_field(result,i,3));
		strscpy(action.message,DBget_field(result,i,4));
		action.scope=atoi(DBget_field(result,i,5));
		action.severity=atoi(DBget_field(result,i,6));
		action.recipient=atoi(DBget_field(result,i,7));
		action.good=atoi(DBget_field(result,i,8));
		action.maxrepeats=atoi(DBget_field(result,i,9));
		action.repeatdelay=atoi(DBget_field(result,i,10));

		if(ACTION_SCOPE_TRIGGER==action.scope)
		{

			substitute_macros(trigger, &action, action.message);
			substitute_macros(trigger, &action, action.subject);

			send_to_user(trigger,&action);
			snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
			DBexecute(sql);
		}
		else if(ACTION_SCOPE_HOST==action.scope)
		{
			if(trigger->priority<action.severity)
			{
				continue;
			}

			snprintf(sql,sizeof(sql)-1,"select distinct h.hostid from hosts h,items i,triggers t,functions f where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=%d", trigger->triggerid);
			result2 = DBselect(sql);

			for(j=0;j<DBnum_rows(result2);j++)
			{
				snprintf(sql,sizeof(sql)-1,"select distinct a.actionid from actions a,hosts h,items i,triggers t,functions f where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and a.triggerid=%d and a.scope=1 and a.actionid=%d and a.triggerid=h.hostid",atoi(DBget_field(result2,j,0)),action.actionid);
				result3 = DBselect(sql);
				if(DBnum_rows(result3)==0)
				{
					DBfree_result(result3);
					continue;
				}
				DBfree_result(result3);

				strscpy(action.subject,trigger->description);
				if(TRIGGER_VALUE_TRUE == trigger_value)
				{
					strncat(action.subject," (ON)", MAX_STRING_LEN);
				}
				else
				{
					strncat(action.subject," (OFF)", MAX_STRING_LEN);
				}
				strscpy(action.message,action.subject);

				substitute_macros(trigger, &action, action.message);
				substitute_macros(trigger, &action, action.subject);

				send_to_user(trigger,&action);
				snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
				DBexecute(sql);
			}
			DBfree_result(result2);

		}
		else if(ACTION_SCOPE_HOSTS==action.scope)
		{
			if(trigger->priority<action.severity)
			{
				continue;
			}

			strscpy(action.subject,trigger->description);
			if(TRIGGER_VALUE_TRUE == trigger_value)
			{
				strncat(action.subject," (ON)", MAX_STRING_LEN);
			}
			else
			{
				strncat(action.subject," (OFF)", MAX_STRING_LEN);
			}
			strscpy(action.message,action.subject);

			substitute_macros(trigger, &action, action.message);
			substitute_macros(trigger, &action, action.subject);

			send_to_user(trigger,&action);
			snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
			DBexecute(sql);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unsupported scope [%d] for actionid [%d]", action.scope, action.actionid);
			zabbix_syslog("Unsupported scope [%d] for actionid [%d]", action.scope, action.actionid);
		}

	}
	zabbix_log( LOG_LEVEL_DEBUG, "Actions applied for trigger %d %d", trigger->triggerid, trigger_value );
	DBfree_result(result);
}
*/


/******************************************************************************
 *                                                                            *
 * Function: run_remote_commands                                              *
 *                                                                            *
 * Purpose: run remote command on specific host                               *
 *                                                                            *
 * Parameters: host_name - host name                                          *
 *             command - remote command                                       *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

static void run_remote_command(char* host_name, char* command)
{
	int ret = 9;
	
	AGENT_RESULT	agent_result;
	DB_ITEM         item;
	DB_RESULT	*result;
	
	char sql[MAX_STRING_LEN];
	assert(host_name);
	assert(command);

	zabbix_log(LOG_LEVEL_DEBUG, "run_remote_command START [hostname: '%s', command: '%s']", host_name, command);

	snprintf(sql,sizeof(sql)-1,"select distinct host,ip,useip,port from hosts where host='%s'", host_name);
	result = DBselect(sql);
	if(DBnum_rows(result) == 1)
	{
		item.host = DBget_field(result,0,0);
		item.ip=DBget_field(result,0,1);
		item.useip=atoi(DBget_field(result,0,2));
		item.port=atoi(DBget_field(result,0,3));
		
		snprintf(item.key,ITEM_KEY_LEN_MAX-1,"system.run[%s]",command);
		
		alarm(CONFIG_TIMEOUT);
		
		ret = get_value_agent(&item, &agent_result);

		alarm(0);
	}
	DBfree_result(result);
	
	zabbix_log(LOG_LEVEL_DEBUG, "run_remote_command [result:%i]", ret);
}

/******************************************************************************
 *                                                                            *
 * Function: get_next_command                                                 *
 *                                                                            *
 * Purpose: parse action script on remote commands                            *
 *                                                                            *
 * Parameters: command_list - command list                                    *
 *             alias - (output) of host name or group name                    *
 *             is_group - (output) 0 if alias is a host name                  *
 *                               1 if alias is a group name                   *
 *             command - (output) remote command                              *
 *                                                                            *
 * Return value: 0 - correct comand is readed                                 *
 *               1 - EOL                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

#define CMD_ALIAS 0
#define CMD_REM_COMMAND 1

static int get_next_command(char** command_list, char** alias, int* is_group, char** command)
{
	int state = CMD_ALIAS;
	int len = 0;
	int i = 0;
	
	assert(alias);
	assert(is_group);
	assert(command);

	zabbix_log(LOG_LEVEL_DEBUG, "get_next_command START [command_list: '%s']", *command_list);

	*alias = NULL;
	*is_group = 0;
	*command = NULL;
	

	if((*command_list)[0] == '\0' || (*command_list)==NULL) {
		zabbix_log(LOG_LEVEL_DEBUG, "Result get_next_command [EOL]");
		return 1;
	}

	*alias = *command_list;
	len = strlen(*command_list);

	for(i=0; i < len; i++)
	{
		if(state == CMD_ALIAS)
		{
			if((*command_list)[i] == '#'){
				*is_group = 1;
				(*command_list)[i] = '\0';
				state = CMD_REM_COMMAND;
				*command = &(*command_list)[i+1];
			}else if((*command_list)[i] == ':'){
				*is_group = 0;
				(*command_list)[i] = '\0';
				state = CMD_REM_COMMAND;
				*command = &(*command_list)[i+1];
			}
		} else if(state == CMD_REM_COMMAND) {
			if((*command_list)[i] == '\r')
			{
				(*command_list)[i] = '\0';
			} else if((*command_list)[i] == '\n')
			{
				(*command_list)[i] = '\0';
				(*command_list) = &(*command_list)[i+1];
				break;
			}
		}
		if((*command_list)[i+1] == '\0')
		{
			(*command_list) = &(*command_list)[i+1];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "Result of get_next_command [alias:%s, is_group:%i, command:%s]", *alias, *is_group, *command);
	
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: run_commands                                                     *
 *                                                                            *
 * Purpose: run remote commandlist for specific action                        *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: commands devided with newline                                    *
 *                                                                            *
 ******************************************************************************/
/*static*/	void	run_commands(DB_TRIGGER *trigger,DB_ACTION *action)
{
	DB_RESULT *result;

	char sql[MAX_STRING_LEN];
	char *cmd_list = NULL;
	char *alias = NULL;
	char *command = NULL;
	int is_group = 0;
	int i = 0;
	
	assert(trigger);
	assert(action);

	cmd_list = action->scripts;
	zabbix_log( LOG_LEVEL_DEBUG, "Run remote commands START [actionid:%d]", action->actionid);
	while(get_next_command(&cmd_list,&alias,&is_group,&command)!=1)
	{
		if(!alias || !command) continue;
		if(alias == '\0' || command == '\0') continue;
		if(is_group)
		{
			snprintf(sql,sizeof(sql)-1,"select distinct h.host from hosts_groups hg,hosts h, groups g where hg.hostid=h.hostid and hg.groupid=g.groupid and g.name='%s'", alias);
	                result = DBselect(sql);
			for(i=0;i<DBnum_rows(result);i++)
			{
				run_remote_command(DBget_field(result,i,0), command);
			}
			
			DBfree_result(result);
		}
		else
		{
			run_remote_command(alias, command);
		}
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Run remote commands END");
}

static int	check_action_condition(DB_TRIGGER *trigger,int alarmid,int new_trigger_value, DB_CONDITION *condition)
{
	DB_RESULT *result;
	char sql[MAX_STRING_LEN];

	int	i;
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In check_action_condition [actionid:%d,conditionid:%d:cond.value:%s]", condition->actionid, condition->conditionid, condition->value);

	if(condition->conditiontype == CONDITION_TYPE_HOST_GROUP)
	{
		snprintf(sql,sizeof(sql)-1,"select distinct hg.groupid from hosts_groups hg,hosts h, items i, functions f, triggers t where hg.hostid=h.hostid and h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=%d", trigger->triggerid);
		result = DBselect(sql);
		for(i=0;i<DBnum_rows(result);i++)
		{
			ret = FAIL;
			if(condition->operator == CONDITION_OPERATOR_EQUAL)
			{
				if(atoi(condition->value) == atoi(DBget_field(result,i,0)))
				{
					ret = SUCCEED;
					break;
				}
			}
			else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
			{
				if(atoi(condition->value) != atoi(DBget_field(result,i,0)))
				{
					ret = SUCCEED;
					break;
				}
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]", condition->operator, condition->conditionid);
				ret = FAIL;
				break;
			}
		}
		DBfree_result(result);
	}
	else if(condition->conditiontype == CONDITION_TYPE_HOST)
	{
		ret = FAIL;
		snprintf(sql,sizeof(sql)-1,"select distinct h.hostid from hosts h, items i, functions f, triggers t where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=%d", trigger->triggerid);
		result = DBselect(sql);
		for(i=0;i<DBnum_rows(result);i++)
		{
			if(condition->operator == CONDITION_OPERATOR_EQUAL)
			{
				if(atoi(condition->value) == atoi(DBget_field(result,i,0)))
				{
					ret = SUCCEED;
					break;
				}
			}
			else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
			{
				if(atoi(condition->value) != atoi(DBget_field(result,i,0)))
				{
					ret = SUCCEED;
					break;
				}
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]", condition->operator, condition->conditionid);
				ret = FAIL;
				break;
			}
		}
		DBfree_result(result);
	}
	else if(condition->conditiontype == CONDITION_TYPE_TRIGGER)
	{
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(trigger->triggerid != atoi(condition->value))
			{
				ret = FAIL;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			if(trigger->triggerid == atoi(condition->value))
			{
				ret = FAIL;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]", condition->operator, condition->conditionid);
			ret = FAIL;
		}
	}
	else if(condition->conditiontype == CONDITION_TYPE_TRIGGER_NAME)
	{
		if(condition->operator == CONDITION_OPERATOR_LIKE)
		{
			if(strstr(trigger->description,condition->value) == NULL)
			{
				ret = FAIL;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_LIKE)
		{
			if(strstr(trigger->description,condition->value) != NULL)
			{
				ret = FAIL;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]", condition->operator, condition->conditionid);
			ret = FAIL;
		}
	}
	else if(condition->conditiontype == CONDITION_TYPE_TRIGGER_SEVERITY)
	{
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(trigger->priority != atoi(condition->value))
			{
				ret = FAIL;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			if(trigger->priority == atoi(condition->value))
			{
				ret = FAIL;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_MORE_EQUAL)
		{
			if(trigger->priority < atoi(condition->value))
			{
				ret = FAIL;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]", condition->operator, condition->conditionid);
			ret = FAIL;
		}
	}
	else if(condition->conditiontype == CONDITION_TYPE_TRIGGER_VALUE)
	{
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(new_trigger_value != atoi(condition->value))
			{
				ret = FAIL;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]", condition->operator, condition->conditionid);
			ret = FAIL;
		}
	}
	else if(condition->conditiontype == CONDITION_TYPE_TIME_PERIOD)
	{
		zabbix_log( LOG_LEVEL_ERR, "Condition type [CONDITION_TYPE_TRIGGER_VALUE] is supported");
		if(condition->operator == CONDITION_OPERATOR_IN)
		{
			if(check_time_period(condition->value)==0)
			{
				ret = FAIL;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]", condition->operator, condition->conditionid);
			ret = FAIL;
		}
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "Condition type [%d] is unknown for condition id [%d]", condition->conditiontype, condition->conditionid);
		ret = FAIL;
	}

	if(FAIL==ret)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Condition is FALSE");
	}
	else
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Condition is TRUE");
	}

	return ret;
}

static int	check_action_conditions(DB_TRIGGER *trigger,int alarmid,int new_trigger_value, int actionid)
{
	DB_RESULT *result;
	char sql[MAX_STRING_LEN];

	DB_CONDITION	condition;
	
	int	ret = SUCCEED;
	int	i;
	int	old_type = -1;
	int	ret_and = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In check_action_conditions [actionid:%d]", actionid);

	snprintf(sql,sizeof(sql)-1,"select conditionid,actionid,conditiontype,operator,value from conditions where actionid=%d order by conditiontype", actionid);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		ret = FAIL;
	}
	else
	{
		for(i=0;i<DBnum_rows(result);i++)
		{
			condition.conditionid=atoi(DBget_field(result,i,0));
			condition.actionid=atoi(DBget_field(result,i,1));
			condition.conditiontype=atoi(DBget_field(result,i,2));
			condition.operator=atoi(DBget_field(result,i,3));
			condition.value=DBget_field(result,i,4);

			/* If old AND condition is FALSE */
			if( (condition.conditiontype != old_type) && (ret_and == FAIL))
			{
				zabbix_log( LOG_LEVEL_DEBUG, "One of AND conditions is FALSE for [%d]", condition.conditionid);
				ret = FAIL;
				break;
			}

			ret_and = check_action_condition(trigger, alarmid, new_trigger_value, &condition);

			/* If last condition and AND condition is FALSE */
			if( (condition.conditiontype != old_type) && (ret_and == FAIL) && (i==DBnum_rows(result)-1) )
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Last conditions is FALSE for [%d]", condition.conditionid);
				ret = FAIL;
				break;
			}

			old_type = condition.conditiontype;
		}
	}

	DBfree_result(result);

	return ret;
}

void	apply_actions(DB_TRIGGER *trigger,int alarmid,int trigger_value)
{
	DB_RESULT *result;
	
	DB_ACTION action;

	char sql[MAX_STRING_LEN];

	int	i;
	int	now;

	zabbix_log( LOG_LEVEL_DEBUG, "In apply_actions(triggerid:%d,alarmid:%d,trigger_value:%d)",trigger->triggerid, alarmid, trigger_value);

	if(TRIGGER_VALUE_TRUE == trigger_value)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Check dependencies");

		snprintf(sql,sizeof(sql)-1,"select count(*) from trigger_depends d,triggers t where d.triggerid_down=%d and d.triggerid_up=t.triggerid and t.value=%d",trigger->triggerid, TRIGGER_VALUE_TRUE);
		result = DBselect(sql);
		if(DBnum_rows(result) == 1)
		{
			if(atoi(DBget_field(result,0,0))>0)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Will not apply actions");
				DBfree_result(result);
				return;
			}
		}
		DBfree_result(result);
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Applying actions");

	now = time(NULL);

/*	snprintf(sql,sizeof(sql)-1,"select actionid,userid,delay,subject,message,scope,severity,recipient,good from actions where (scope=%d and triggerid=%d and good=%d and nextcheck<=%d) or (scope=%d and good=%d) or (scope=%d and good=%d)",ACTION_SCOPE_TRIGGER,trigger->triggerid,trigger_value,now,ACTION_SCOPE_HOST,trigger_value,ACTION_SCOPE_HOSTS,trigger_value);*/
	snprintf(sql,sizeof(sql)-1,"select actionid,userid,delay,subject,message,recipient,maxrepeats,repeatdelay,scripts,actiontype from actions where nextcheck<=%d and status=%d", now, ACTION_STATUS_ACTIVE);
	result = DBselect(sql);
	zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]", sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		action.actionid=atoi(DBget_field(result,i,0));

		if(check_action_conditions(trigger, alarmid, trigger_value, action.actionid) == SUCCEED)
		{
			action.actiontype=atoi(DBget_field(result,i,9));
			action.userid=atoi(DBget_field(result,i,1));
			action.delay=atoi(DBget_field(result,i,2));
			
			strscpy(action.subject,DBget_field(result,i,3));
			strscpy(action.message,DBget_field(result,i,4));
			substitute_macros(trigger, &action, action.message);
			substitute_macros(trigger, &action, action.subject);

			action.recipient=atoi(DBget_field(result,i,5));
			action.maxrepeats=atoi(DBget_field(result,i,6));
			action.repeatdelay=atoi(DBget_field(result,i,7));
			strscpy(action.scripts,DBget_field(result,i,8));

			if(action.actiontype == ACTION_TYPE_MESSAGE)
				send_to_user(trigger,&action);
			else
				run_commands(trigger,&action);

			snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
			DBexecute(sql);
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Conditions do not match our trigger. Do not apply actions.");
		}
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Actions applied for trigger %d %d", trigger->triggerid, trigger_value );
	DBfree_result(result);
}
