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

/* 1 - within period, 0 - out of period */
int	check_time_period(char *period)
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

/* Cannot use action->userid as it may also represent groupd id*/
void	send_to_user_medias(DB_TRIGGER *trigger,DB_ACTION *action, int userid)
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

		DBadd_alert(action->actionid,media.mediatypeid,media.sendto,action->subject,action->message);
	}
	DBfree_result(result);
}

/*
 * Send message to user. Message will be sent to all medias registered to given user.
 */ 
void	send_to_user(DB_TRIGGER *trigger,DB_ACTION *action)
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

void	apply_actions(DB_TRIGGER *trigger,int alarmid,int trigger_value)
{
	int escalationid;
	char sql[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_WARNING, "In apply_actions(triggerid:%d,alarmid:%d,trigger_value:%d)",trigger->triggerid, alarmid, trigger_value);

	if((escalationid=DBget_default_escalation_id())>0)
	{
		snprintf(sql,sizeof(sql)-1,"insert into escalation_log (triggerid,alarmid,escalationid,level,adminlevel,nextcheck,status) values (%d,%d,%d,%d,%d,%d,%d)", trigger->triggerid, alarmid, escalationid, 0, 0, 0, 0);
		DBexecute(sql);
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "No default escalation defined");
	}
}

/*
 * Apply actions if any.
 */ 
/*void	apply_actions(int triggerid,int good)*/
void	apply_actions_old(DB_TRIGGER *trigger,int alarmid,int trigger_value)
{
	DB_RESULT *result,*result2,*result3;
	
	DB_ACTION action;

	char sql[MAX_STRING_LEN];

	int	i,j;
	int	now;

	zabbix_log( LOG_LEVEL_WARNING, "In apply_actions(triggerid:%d,alarmid:%d,trigger_value:%d)",trigger->triggerid, alarmid, trigger_value);

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
	snprintf(sql,sizeof(sql)-1,"select actionid,userid,delay,subject,message,scope,severity,recipient,good from actions where (scope=%d and triggerid=%d and (good=%d or good=2) and nextcheck<=%d) or (scope=%d and (good=%d or good=2)) or (scope=%d and (good=%d or good=2))",ACTION_SCOPE_TRIGGER,trigger->triggerid,trigger_value,now,ACTION_SCOPE_HOST,trigger_value,ACTION_SCOPE_HOSTS,trigger_value);
	result = DBselect(sql);
	zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]", sql);

	for(i=0;i<DBnum_rows(result);i++)
	{

		zabbix_log( LOG_LEVEL_DEBUG, "i=[%d]",i);
/*		zabbix_log( LOG_LEVEL_ERR, "Fetched: ID [%s] %s %s %s %s\n",DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2),DBget_field(result,i,3),DBget_field(result,i,4));*/

		action.actionid=atoi(DBget_field(result,i,0));
		action.userid=atoi(DBget_field(result,i,1));
		action.delay=atoi(DBget_field(result,i,2));
		strscpy(action.subject,DBget_field(result,i,3));
		strscpy(action.message,DBget_field(result,i,4));
		action.scope=atoi(DBget_field(result,i,5));
		action.severity=atoi(DBget_field(result,i,6));
		action.recipient=atoi(DBget_field(result,i,7));
		action.good=atoi(DBget_field(result,i,8));

		if(ACTION_SCOPE_TRIGGER==action.scope)
		{
/*			substitute_hostname(trigger->triggerid,action.message);
			substitute_hostname(trigger->triggerid,action.subject);*/

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

/*			snprintf(sql,sizeof(sql)-1,"select * from actions a,triggers t,hosts h,functions f where a.triggerid=t.triggerid and f.triggerid=t.triggerid and h.hostid=a.triggerid and t.triggerid=%d and a.scope=%d",trigger->triggerid,ACTION_SCOPE_HOST);
			result2 = DBselect(sql);
			if(DBnum_rows(result2)==0)
			{
				DBfree_result(result2);
				continue;
			}
			DBfree_result(result2);

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
			substitute_macros(trigger, &action, action.subject);*/
		}
		else if(ACTION_SCOPE_HOSTS==action.scope)
		{
/* Added in Zabbix 1.0beta10 */
			if(trigger->priority<action.severity)
			{
				continue;
			}
/* -- */
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

/*			substitute_hostname(trigger->triggerid,action.message);
			substitute_hostname(trigger->triggerid,action.subject);*/

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
