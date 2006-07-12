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

int	DBadd_trigger_to_linked_hosts(int triggerid,int hostid)
{
	DB_TRIGGER	trigger;
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_RESULT	result3;
	DB_ROW		row;
	DB_ROW		row2;
	DB_ROW		row3;
	char	sql[MAX_STRING_LEN];
	char	old[MAX_STRING_LEN];
	char	new[MAX_STRING_LEN];
	int	functionid, triggerid_new;
	char	expression_old[TRIGGER_EXPRESSION_LEN_MAX];
	char	*expression;
	char	comments_esc[TRIGGER_COMMENTS_LEN_MAX];
	char	url_esc[TRIGGER_URL_LEN_MAX];
	char	description_esc[TRIGGER_DESCRIPTION_LEN_MAX];

	zabbix_log( LOG_LEVEL_DEBUG, "In DBadd_trigger_to_linked_hosts(%d,%d)",triggerid, hostid);

	zbx_snprintf(sql,sizeof(sql),"select description, priority,status,comments,url,value,expression from triggers where triggerid=%d", triggerid);
	result2=DBselect(sql);
	row2=DBfetch(result2);

	if(!row2)
	{
		DBfree_result(result2);
		return FAIL;
	}

	trigger.triggerid = triggerid;
	strscpy(trigger.description, row2[0]);
	zabbix_log( LOG_LEVEL_DEBUG, "DESC1 [%s] [%s]", trigger.description, row2[0]);
	trigger.priority=atoi(row2[1]);
	trigger.status=atoi(row2[2]);
	strscpy(trigger.comments, row2[3]);
	strscpy(trigger.url, row2[4]);
	trigger.value=atoi(row2[5]);
	strscpy(trigger.expression, row2[6]);

	DBfree_result(result2);

	zbx_snprintf(sql,sizeof(sql),"select distinct h.hostid from hosts h,functions f, items i where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=%d", triggerid);
	result=DBselect(sql);

	row=DBfetch(result);

	if(!row)
	{
		return FAIL;
	}

	if(hostid==0)
	{
		zbx_snprintf(sql,sizeof(sql),"select hostid,templateid,triggers from hosts_templates where templateid=%d", atoi(row[0]));
	}
	/* Link to one host only */
	else
	{
		zbx_snprintf(sql,sizeof(sql),"select hostid,templateid,triggers from hosts_templates where hostid=%d and templateid=%d", hostid, atoi(row[0]));
	}
	DBfree_result(result);

	result=DBselect(sql);

	/* Loop: linked hosts */
	while((row=DBfetch(result)))
	{
		strscpy(expression_old, trigger.expression);

		if( (atoi(row[2])&1) == 0)	continue;

		DBescape_string(trigger.description,description_esc,TRIGGER_DESCRIPTION_LEN_MAX);
		zabbix_log( LOG_LEVEL_DEBUG, "DESC2 [%s] [%s]", trigger.description, description_esc);
		DBescape_string(trigger.comments,comments_esc,TRIGGER_COMMENTS_LEN_MAX);
		DBescape_string(trigger.url,url_esc,TRIGGER_URL_LEN_MAX);

		zbx_snprintf(sql,sizeof(sql),"insert into triggers  (description,priority,status,comments,url,value,expression) values ('%s',%d,%d,'%s','%s',2,'%s')",description_esc, trigger.priority, trigger.status, comments_esc, url_esc, expression_old);
		zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]",sql);

		DBexecute(sql);
		triggerid_new=DBinsert_id();

		zbx_snprintf(sql,sizeof(sql),"select i.key_,f.parameter,f.function,f.functionid from functions f,items i where i.itemid=f.itemid and f.triggerid=%d", triggerid);
		result2=DBselect(sql);
		/* Loop: functions */
		while((row2=DBfetch(result2)))
		{
			zbx_snprintf(sql,sizeof(sql),"select itemid from items where key_='%s' and hostid=%d", row2[0], atoi(row[0]));
			result3=DBselect(sql);
			row3=DBfetch(result3);
			if(!row3)
			{
				DBfree_result(result3);

				zbx_snprintf(sql,sizeof(sql),"delete from triggers where triggerid=%d", triggerid_new);
				DBexecute(sql);
				zbx_snprintf(sql,sizeof(sql),"delete from functions where triggerid=%d", triggerid_new);
				DBexecute(sql);
				break;
			}

			zbx_snprintf(sql,sizeof(sql),"insert into functions (itemid,triggerid,function,parameter) values (%d,%d,'%s','%s')", atoi(row3[0]), triggerid_new, row2[2], row2[1]);

			DBexecute(sql);
			functionid=DBinsert_id();

			zbx_snprintf(sql,sizeof(sql),"update triggers set expression='%s' where triggerid=%d", expression_old, triggerid_new );
			DBexecute(sql);

			zbx_snprintf(old, sizeof(old),"{%d}", atoi(row2[3]));
			zbx_snprintf(new, sizeof(new),"{%d}", functionid);

			/* Possible memory leak here as expression can be malloced */
			expression=string_replace(expression_old, old, new);

			strscpy(expression_old, expression);

			zbx_snprintf(sql,sizeof(sql),"update triggers set expression='%s' where triggerid=%d", expression, triggerid_new );
			free(expression);

			DBexecute(sql);

			DBfree_result(result3);
		}
		DBfree_result(result2);
	}
	DBfree_result(result);

	return SUCCEED;
}


/*-----------------------------------------------------------------------------
 *
 * Function   : DBget_trigger_by_triggerid 
 *
 * Purpose    : get trigger data from DBby triggerid
 *
 * Parameters : triggerid - ID of the trigger
 *
 * Returns    : SUCCEED - trigger data retrieved sucesfully
 *              FAIL - otherwise
 *
 * Author     : Alexei Vladishev
 *
 * Comments   :
 *
 ----------------------------------------------------------------------------*/
int	DBget_trigger_by_triggerid(int triggerid,DB_TRIGGER *trigger)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBget_trigger_by_triggerid(%d)", triggerid);

	zbx_snprintf(sql,sizeof(sql),"select triggerid, expression,description,url,comments,status,value,priority from triggers where triggerid=%d", triggerid);
	result=DBselect(sql);
	row=DBfetch(result);

	if(!row)
	{
		ret = FAIL;
	}
	else
	{
		trigger->triggerid=atoi(row[0]);
		strscpy(trigger->expression,row[1]);
		strscpy(trigger->description,row[2]);
		strscpy(trigger->url,row[3]);
		strscpy(trigger->comments,row[4]);
		trigger->status=atoi(row[5]);
		trigger->value=atoi(row[6]);
		trigger->priority=atoi(row[7]);
	}

	DBfree_result(result);

	return ret;
}
