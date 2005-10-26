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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <sys/wait.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include "common.h"
#include "cfg.h"
#include "db.h"
#include "../functions.h"
#include "log.h"
#include "zlog.h"
#include "email.h"

#include "escalator.h"

extern void    apply_actions(DB_TRIGGER *trigger,int alarmid,int trigger_value);

static int process_escalation(DB_ESCALATION_LOG *escalation_log)
{
	int	i,now;
	char	sql[MAX_STRING_LEN];

	DB_RESULT		*result;
	DB_ESCALATION_RULE	escalation_rule;

	DB_TRIGGER	trigger;

	zabbix_log( LOG_LEVEL_WARNING, "In process_escalation()");

	snprintf(sql,sizeof(sql)-1,"select escalationruleid, escalationid,level,period,delay,actiontype from escalation_rules where escalationid=%d and level>=%d order by level", escalation_log->escalationid, escalation_log->level);
	zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", sql);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		escalation_rule.escalationruleid=atoi(DBget_field(result,i,0));
		escalation_rule.escalationid=atoi(DBget_field(result,i,1));
		escalation_rule.level=atoi(DBget_field(result,i,2));
		escalation_rule.period=DBget_field(result,i,3);
		escalation_rule.delay=atoi(DBget_field(result,i,4));
		escalation_rule.actiontype=atoi(DBget_field(result,i,5));

		zabbix_log( LOG_LEVEL_WARNING, "Selected escalationrule ID [%d]", escalation_rule.escalationruleid);
		now=time(NULL);
		if(escalation_log->nextcheck <= now)
		{
			if(escalation_log->nextcheck==0 && escalation_rule.delay!=0)
			{
				escalation_log->nextcheck = escalation_rule.delay+now;
				escalation_log->level = escalation_rule.level;
				snprintf(sql,sizeof(sql)-1,"update escalation_log set nextcheck=%d,level=%d,actiontype=%d where escalationlogid=%d", escalation_log->nextcheck, escalation_log->level, escalation_rule.actiontype, escalation_log->escalationlogid);
				zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", sql);
				DBexecute(sql);
				break;
			}

			switch (escalation_rule.actiontype)
			{
				case ESCALATION_ACTION_NOTHING:
					zabbix_log( LOG_LEVEL_WARNING, "ESCALATION_ACTION_NOTHING");
					break;
				case ESCALATION_ACTION_EXEC_ACTION:
					zabbix_log( LOG_LEVEL_WARNING, "ESCALATION_ACTION_EXEC_ACTION");
					if(DBget_trigger_by_triggerid(escalation_log->triggerid,&trigger) == SUCCEED)
					{
						apply_actions(&trigger,escalation_log->alarmid,TRIGGER_VALUE_TRUE);
					}
					else
					{
						zabbix_log( LOG_LEVEL_WARNING, "Cannot execute actions. No trigger with triggerid [%d]", escalation_log->triggerid);
					}
					break;
				case ESCALATION_ACTION_INC_SEVERITY:
					zabbix_log( LOG_LEVEL_WARNING, "ESCALATION_ACTION_INC_SEVERITY");
					DBexecute(sql);
					break;
				case ESCALATION_ACTION_INC_ADMIN:
					zabbix_log( LOG_LEVEL_WARNING, "ESCALATION_ACTION_INC_ADMIN");
					DBexecute(sql);
					break;
				default:
					zabbix_log( LOG_LEVEL_ERR, "Unknow escalation action type [%d]", escalation_rule.actiontype);
			}
			snprintf(sql,sizeof(sql)-1,"update escalation_log set status=1 where escalationlogid=%d", escalation_log->escalationlogid);
			zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", sql);
			DBexecute(sql);

			snprintf(sql,sizeof(sql)-1,"insert into escalation_log (triggerid,alarmid,escalationid,level,adminlevel,nextcheck,status,actiontype) values (%d,%d,%d,%d,%d,%d,%d,%d)", escalation_log->triggerid, escalation_log->alarmid, escalation_log->escalationid, escalation_rule.level+1, escalation_log->adminlevel, 0, 0, ESCALATION_ACTION_NOTHING);
			zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", sql);
			DBexecute(sql);
			break;
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Not ready yet esc_log id [%d]", escalation_log->escalationlogid);
			break;
		}
	}

	if(DBnum_rows(result)==0)
	{
		zabbix_log( LOG_LEVEL_WARNING, "No more escalation levels");
		snprintf(sql,sizeof(sql)-1,"update escalation_log set status=1 where escalationlogid=%d", escalation_log->escalationlogid);
		zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", sql);
		DBexecute(sql);
	}
	DBfree_result(result);

	return SUCCEED;
}

/*-----------------------------------------------------------------------------
 *
 * Function   : main_escalator_loop 
 *
 * Purpose    : periodically process active escalations
 *
 * Parameters :
 *
 * Returns    : 
 *
 * Author     : Alexei Vladishev
 *
 * Comments   : Never returns
 *
 ----------------------------------------------------------------------------*/
void main_escalator_loop()
{
	char	sql[MAX_STRING_LEN];

	int	i,res;
	int	now;

	DB_RESULT	*result;

	DB_ESCALATION_LOG	escalation_log;

	for(;;)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Selecting data from escalation_log");
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("connecting to the database");
#endif

		DBconnect();

		now=time(NULL);
		snprintf(sql,sizeof(sql)-1,"select escalationlogid,triggerid, alarmid, escalationid, level, adminlevel, nextcheck, status from escalation_log where status=0 and nextcheck<=%d", now);
		result = DBselect(sql);

		for(i=0;i<DBnum_rows(result);i++)
		{
			escalation_log.escalationlogid=atoi(DBget_field(result,i,0));
			escalation_log.triggerid=atoi(DBget_field(result,i,1));
			escalation_log.alarmid=atoi(DBget_field(result,i,2));
			escalation_log.escalationid=atoi(DBget_field(result,i,3));
			escalation_log.level=atoi(DBget_field(result,i,4));
			escalation_log.adminlevel=atoi(DBget_field(result,i,5));
			escalation_log.nextcheck=atoi(DBget_field(result,i,6));
			escalation_log.status=atoi(DBget_field(result,i,7));

			res=process_escalation(&escalation_log);

			if(res==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_WARNING, "Processing escalation_log ID [%d]", escalation_log.escalationlogid);
			}
			else
			{
				zabbix_log( LOG_LEVEL_WARNING, "Processing escalation_log ID [%d] failed", escalation_log.escalationlogid);
			}

		}
		DBfree_result(result);

		DBclose();
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("escalator [sleeping for %d seconds]", 10);
#endif

		sleep(10);
	}
}
