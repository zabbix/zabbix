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


#include <stdlib.h>
#include <stdio.h>

#include <string.h>
#include <strings.h>

#include "db.h"
#include "log.h"
#include "zlog.h"
#include "common.h"

int	DBadd_action(int triggerid, int userid, int delay, char *subject, char *message, int scope, int severity, int recipient, int usrgrpid)
{
	char	sql[MAX_STRING_LEN];
	int	actionid;
	char	subject_esc[ACTION_SUBJECT_LEN_MAX];
	char	message_esc[MAX_STRING_LEN];

	DBescape_string(subject,subject_esc,ACTION_SUBJECT_LEN_MAX);
	DBescape_string(message,message_esc,MAX_STRING_LEN);

	if(recipient == RECIPIENT_TYPE_GROUP)
	{
		userid = usrgrpid;
	}

	snprintf(sql, sizeof(sql)-1,"insert into actions (triggerid, userid, delay, subject, message, scope, severity, recipient) values (%d, %d, %d, '%s', '%s', %d, %d, %d)", triggerid, userid, delay, subject_esc, message_esc, scope, severity, recipient);
	if(FAIL == DBexecute(sql))
	{
		return FAIL;
	}

	actionid=DBinsert_id();

	if(actionid==0)
	{
		return FAIL;
	}

	return actionid;
}

int	DBget_action_by_actionid(int actionid,DB_ACTION *action)
{
	DB_RESULT	result;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBget_action_by_actionid(%d)", actionid);

	snprintf(sql,sizeof(sql)-1,"select userid,delay,recipient,subject,message from actions where actionid=%d", actionid);
	result=DBselect(sql);

	if(DBnum_rows(result)==0)
	{
		ret = FAIL;
	}
	else
	{
		action->actionid=actionid;
		action->userid=atoi(DBget_field(result,0,0));
		action->delay=atoi(DBget_field(result,0,1));
		action->recipient=atoi(DBget_field(result,0,2));
		strscpy(action->subject,DBget_field(result,0,3));
		strscpy(action->message,DBget_field(result,0,4));
	}

	DBfree_result(result);

	return ret;
}

/*
int	DBadd_action_to_linked_hosts(int actionid,int hostid)
{
	DB_ACTION	action;
	DB_TRIGGER	trigger;
	DB_HOST		host;
	DB_HOST		host_template;
	DB_RESULT	*result,*result2;

	char	sql[MAX_STRING_LEN];
	char	description_esc[TRIGGER_DESCRIPTION_LEN_MAX];
	char	old[MAX_STRING_LEN];
	char	new[MAX_STRING_LEN];
	char	*message;
	int	i,j;
	int	hostid_tmp;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBadd_action_to_linked_hosts(%d,%d)", actionid, hostid);

	if(DBget_action_by_actionid(actionid,&action) == FAIL)
	{
		return FAIL;
	}

	if(DBget_trigger_by_triggerid(action.triggerid,&trigger) == FAIL)
	{
		return FAIL;
	}

	snprintf(sql,sizeof(sql)-1,"select distinct h.hostid from hosts h,functions f, items i where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=%d", action.triggerid);
	zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]", sql);
	result=DBselect(sql);

	if(DBnum_rows(result)!=1)
	{
		DBfree_result(result);
		return FAIL;
	}

	hostid_tmp=atoi(DBget_field(result,0,0));

	DBfree_result(result);

	if(DBget_host_by_hostid(hostid_tmp,&host_template) == FAIL)
	{
		return FAIL;
	}
	if(hostid==0)
	{
		snprintf(sql,sizeof(sql)-1,"select hostid,templateid,actions from hosts_templates where templateid=%d", hostid_tmp);
	}
	else
	{
		snprintf(sql,sizeof(sql)-1,"select hostid,templateid,actions from hosts_templates where hostid=%d and templateid=%d", hostid, hostid_tmp);
	}
	zabbix_log( LOG_LEVEL_DEBUG, "SQL2 [%s]", sql);

	result=DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "In loop [%d]", i);
		if( (atoi(DBget_field(result,i,2))&1) == 0)	continue;

		DBescape_string(trigger.description,description_esc,TRIGGER_DESCRIPTION_LEN_MAX);

		snprintf(sql,sizeof(sql)-1,"select distinct f.triggerid from functions f,items i,triggers t where t.description='%s' and t.triggerid=f.triggerid and i.itemid=f.itemid and i.hostid=%d", description_esc, atoi(DBget_field(result,i,0)));
		zabbix_log( LOG_LEVEL_DEBUG, "SQL3 [%s]", sql);
		result2=DBselect(sql);
		for(j=0;j<DBnum_rows(result2);j++)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "In loop2 [%d]", j);
			if(DBget_host_by_hostid(atoi(DBget_field(result,i,0)),&host) == FAIL)	continue;

			snprintf(old,sizeof(sql)-1,"{%s:",host_template.host);
			snprintf(new,sizeof(sql)-1,"{%s:",host.host);

			message=string_replace(action.message, old, new);

			zabbix_log( LOG_LEVEL_DEBUG, "Before DBadd_action");

			free(message);

			DBadd_action(atoi(DBget_field(result2,j,0)), action.userid, action.good, action.delay, action.subject, message, action.scope, action.severity, action.recipient, action.userid);
		}
		DBfree_result(result2);
	}
	DBfree_result(result);

	return SUCCEED;
}
*/
