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

#include "evalfunc.h"
#include "functions.h"
#include "expression.h"

/******************************************************************************
 *                                                                            *
 * Function: del_zeroes                                                       *
 *                                                                            *
 * Purpose: delete all right '0' and '.' for the string                       *
 *                                                                            *
 * Parameters: s - string to trim '0'                                         *
 *                                                                            *
 * Return value: string without right '0'                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:  10.0100 => 10.01, 10. => 10                                                                 *
 *                                                                            *
 ******************************************************************************/
void del_zeroes(char *s)
{
	int     i;

	if(strchr(s,'.')!=NULL)
	{
		for(i=strlen(s)-1;;i--)
		{
			if(s[i]=='0')
			{
				s[i]=0;
			}
			else if(s[i]=='.')
			{
				s[i]=0;
				break;
			}
			else
			{
				break;
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: calculate_item_nextcheck                                         *
 *                                                                            *
 * Purpose: calculate nextcheck timespamp for item                            *
 *                                                                            *
 * Parameters: delay - item's refresh rate in sec                             *
 *             now - current timestamp                                        *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: Old algorithm: now+delay                                         *
 *           New one: preserve period, if delay==5, nextcheck = 0,5,10,15,... *
 *                                                                            *
 ******************************************************************************/
int	calculate_item_nextcheck(int delay, int now)
{
	int i;

	i=delay*(int)(now/delay);

	while(i<=now)	i+=delay;

	return i;
}

/******************************************************************************
 *                                                                            *
 * Function: update_functions                                                 *
 *                                                                            *
 * Purpose: re-calculate and updates values of functions related to the item  *
 *                                                                            *
 * Parameters: item - item to update functions for                            *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	update_functions(DB_ITEM *item)
{
	DB_FUNCTION	function;
	DB_RESULT	*result;
	char		sql[MAX_STRING_LEN];
	char		value[MAX_STRING_LEN];
	char		value_esc[MAX_STRING_LEN];
	char		*lastvalue;
	int		ret=SUCCEED;
	int		i;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_functions(%d)",item->itemid);

	snprintf(sql,sizeof(sql)-1,"select function,parameter,itemid,lastvalue from functions where itemid=%d group by 1,2,3 order by 1,2,3",item->itemid);

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		function.function=DBget_field(result,i,0);
		function.parameter=DBget_field(result,i,1);
		function.itemid=atoi(DBget_field(result,i,2));
		lastvalue=DBget_field(result,i,3);

		zabbix_log( LOG_LEVEL_DEBUG, "ItemId:%d Evaluating %s(%d)\n",function.itemid,function.function,function.parameter);

		ret = evaluate_FUNCTION(value,item,function.function,function.parameter, EVALUATE_FUNCTION_NORMAL);
		if( FAIL == ret)	
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Evaluation failed for function:%s\n",function.function);
			continue;
		}
		zabbix_log( LOG_LEVEL_DEBUG, "Result of evaluate_FUNCTION [%s]\n",value);
		if (ret == SUCCEED)
		{
			/* Update only if lastvalue differs from new one */
			if( (lastvalue == NULL) || (strcmp(lastvalue,value) != 0))
			{
				DBescape_string(value,value_esc,MAX_STRING_LEN);
				snprintf(sql,sizeof(sql)-1,"update functions set lastvalue='%s' where itemid=%d and function='%s' and parameter='%s'", value_esc, function.itemid, function.function, function.parameter );
				DBexecute(sql);
			}
			else
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Do not update functions, same value");
			}
		}
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: update_services_rec                                              *
 *                                                                            *
 * Purpose: re-calculate and updates status of the service and its childs     *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: recursive function                                               *
 *                                                                            *
 ******************************************************************************/
void	update_services_rec(int serviceid)
{
	char	sql[MAX_STRING_LEN];
	int	i;
	int	status;
	int	serviceupid, algorithm;
	time_t	now;

	DB_RESULT *result,*result2;

	snprintf(sql,sizeof(sql)-1,"select l.serviceupid,s.algorithm from services_links l,services s where s.serviceid=l.serviceupid and l.servicedownid=%d",serviceid);
	result=DBselect(sql);
	status=0;
	for(i=0;i<DBnum_rows(result);i++)
	{
		serviceupid=atoi(DBget_field(result,i,0));
		algorithm=atoi(DBget_field(result,i,1));
		if(SERVICE_ALGORITHM_NONE == algorithm)
		{
/* Do nothing */
		}
		else if((SERVICE_ALGORITHM_MAX == algorithm)
			||
			(SERVICE_ALGORITHM_MIN == algorithm))
		{
			/* Why it was so complex ?
			sprintf(sql,"select status from services s,services_links l where l.serviceupid=%d and s.serviceid=l.servicedownid",serviceupid);
			result2=DBselect(sql);
			for(j=0;j<DBnum_rows(result2);j++)
			{
				if(atoi(DBget_field(result2,j,0))>status)
				{
					status=atoi(DBget_field(result2,j,0));
				}
			}
			DBfree_result(result2);*/

			if(SERVICE_ALGORITHM_MAX == algorithm)
			{
				snprintf(sql,sizeof(sql)-1,"select count(*),max(status) from services s,services_links l where l.serviceupid=%d and s.serviceid=l.servicedownid",serviceupid);
			}
			/* MIN otherwise */
			else
			{
				snprintf(sql,sizeof(sql)-1,"select count(*),min(status) from services s,services_links l where l.serviceupid=%d and s.serviceid=l.servicedownid",serviceupid);
			}
			result2=DBselect(sql);
			if(atoi(DBget_field(result2,0,0))!=0)
			{
				status=atoi(DBget_field(result2,0,1));
			}
			DBfree_result(result2);

			now=time(NULL);
			DBadd_service_alarm(atoi(DBget_field(result,i,0)),status,now);
			snprintf(sql,sizeof(sql)-1,"update services set status=%d where serviceid=%d",status,atoi(DBget_field(result,i,0)));
			DBexecute(sql);
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unknown calculation algorithm of service status [%d]", algorithm);
			zabbix_syslog("Unknown calculation algorithm of service status [%d]", algorithm);
		}
	}
	DBfree_result(result);

	snprintf(sql,sizeof(sql)-1,"select serviceupid from services_links where servicedownid=%d",serviceid);
	result=DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		update_services_rec(atoi(DBget_field(result,i,0)));
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: update_services                                                  *
 *                                                                            *
 * Purpose: re-calculate and updates status of the service and its childs     *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *             status - new status of the service                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	update_services(int triggerid, int status)
{
	char	sql[MAX_STRING_LEN];
	int	i;

	DB_RESULT *result;

	snprintf(sql,sizeof(sql)-1,"update services set status=%d where triggerid=%d",status,triggerid);
	DBexecute(sql);


	snprintf(sql,sizeof(sql)-1,"select serviceid from services where triggerid=%d", triggerid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		update_services_rec(atoi(DBget_field(result,i,0)));
	}

	DBfree_result(result);
	return;
}

/******************************************************************************
 *                                                                            *
 * Function: update_triggers                                                  *
 *                                                                            *
 * Purpose: re-calculate and updates values of triggers related to the item   *
 *                                                                            *
 * Parameters: itemid - item to update trigger values for                     *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	update_triggers(int itemid)
{
	char	sql[MAX_STRING_LEN];
	char	exp[MAX_STRING_LEN];
	int	exp_value;
	time_t	now;
	DB_TRIGGER	trigger;
	DB_RESULT	*result;

	int	i;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_triggers [%d]", itemid);

/* Does not work for PostgreSQL */
/*		sprintf(sql,"select t.triggerid,t.expression,t.status,t.dep_level,t.priority,t.value from triggers t,functions f,items i where i.status<>3 and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=%d group by t.triggerid,t.expression,t.dep_level",TRIGGER_STATUS_ENABLED,server_num);*/
/* Is it correct SQL? */
	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid,t.expression,t.status,t.dep_level,t.priority,t.value,t.description from triggers t,functions f,items i where i.status<>%d and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=%d",ITEM_STATUS_NOTSUPPORTED, TRIGGER_STATUS_ENABLED, itemid);

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		trigger.triggerid=atoi(DBget_field(result,i,0));
		strscpy(trigger.expression,DBget_field(result,i,1));
		trigger.status=atoi(DBget_field(result,i,2));
		trigger.priority=atoi(DBget_field(result,i,4));
		trigger.value=atoi(DBget_field(result,i,5));
		strscpy(trigger.description,DBget_field(result,i,6));

		strscpy(exp, trigger.expression);
		if( evaluate_expression(&exp_value, exp) != 0 )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Expression [%s] cannot be evaluated.",trigger.expression);
			zabbix_syslog("Expression [%s] cannot be evaluated.",trigger.expression);
			continue;
		}

		zabbix_log( LOG_LEVEL_DEBUG, "exp_value trigger.value trigger.prevvalue [%d] [%d] [%d]", exp_value, trigger.value, trigger.prevvalue);

		now = time(NULL);
		DBupdate_trigger_value(&trigger, exp_value, now);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: process_data                                                     *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters: sockfd - descriptor of agent-server socket connection          *
 *             server - server name                                           *
 *             key - item's key                                               *
 *             value - new value of server:key                                *
 *             lastlogsize - if key=log[*], last size of log file             *
 *                                                                            *
 * Return value: SUCCEED - new value processed sucesfully                     *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: for trapper server process                                       *
 *                                                                            *
 ******************************************************************************/
int	process_data(int sockfd,char *server,char *key,char *value,char *lastlogsize, char *timestamp)
{
	char	sql[MAX_STRING_LEN];

	DB_RESULT       *result;
	DB_ITEM	item;
	char	*s;
	int	update_tr;

	zabbix_log( LOG_LEVEL_WARNING, "In process_data([%s],[%s],[%s],[%s])",server,key,value,lastlogsize);

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.value_type,i.trapper_hosts,i.delta,i.units,i.multiplier,i.formula from items i,hosts h where h.status=%d and h.hostid=i.hostid and h.host='%s' and i.key_='%s' and i.status=%d and i.type in (%d,%d)", HOST_STATUS_MONITORED, server, key, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		DBfree_result(result);
		return  FAIL;
	}

	item.itemid=atoi(DBget_field(result,0,0));
	item.key=DBget_field(result,0,1);
	item.host=DBget_field(result,0,2);
	item.type=atoi(DBget_field(result,0,7));
	item.trapper_hosts=DBget_field(result,0,16);

	if( (item.type==ITEM_TYPE_ZABBIX_ACTIVE) && (check_security(sockfd,item.trapper_hosts,1) == FAIL))
	{
		DBfree_result(result);
		return  FAIL;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Processing [%s]", value);

	if(strcmp(value,"ZBX_NOTSUPPORTED") ==0)
	{
			zabbix_log( LOG_LEVEL_WARNING, "Active parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
			zabbix_syslog("Active parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
			DBupdate_item_status_to_notsupported(item.itemid, "Not supported by agent");
	}
	
	item.port=atoi(DBget_field(result,0,3));
	item.delay=atoi(DBget_field(result,0,4));
	item.description=DBget_field(result,0,5);
	item.nextcheck=atoi(DBget_field(result,0,6));
	item.snmp_community=DBget_field(result,0,8);
	item.snmp_oid=DBget_field(result,0,9);
	item.useip=atoi(DBget_field(result,0,10));
	item.ip=DBget_field(result,0,11);
	item.history=atoi(DBget_field(result,0,12));
	s=DBget_field(result,0,13);
	if(s==NULL)
	{
		item.lastvalue_null=1;
	}
	else
	{
		item.lastvalue_null=0;
		item.lastvalue_str=s;
		item.lastvalue=atof(s);
	}
	s=DBget_field(result,0,14);
	if(s==NULL)
	{
		item.prevvalue_null=1;
	}
	else
	{
		item.prevvalue_null=0;
		item.prevvalue_str=s;
		item.prevvalue=atof(s);
	}
	item.value_type=atoi(DBget_field(result,0,15));
	item.delta=atoi(DBget_field(result,0,17));
	item.units=DBget_field(result,0,18);
	item.multiplier=atoi(DBget_field(result,0,19));
	item.formula=DBget_field(result,0,20);

	s=value;
	if(	(strncmp(item.key,"log[",4)==0) ||
		(strncmp(item.key,"eventlog[",9)==0)
	)
	{
/*		s=strchr(value,':');
		if(s == NULL)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Wrong value received for item [%s:%s]", item.host, item.key);
			DBfree_result(result);
			return FAIL;
		}
		s++;

		strncpy(lastlogsize, value, s-value-1);
		lastlogsize[s-value-1]=0;*/

		item.lastlogsize=atoi(lastlogsize);
		item.timestamp=atoi(timestamp);
		zabbix_log(LOG_LEVEL_WARNING, "Value [%s] Lastlogsize [%s] Timestamp [%s]", value, lastlogsize, timestamp);
	}

	process_new_value(&item,s,&update_tr);

	if(update_tr==1)
	{
		update_triggers(item.itemid);
	}
 
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: process_new_value                                                *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *                                                                            *
 * Return value: update_tr=0 - update of triggers is not required             *
 *               update_tr=1 - update of triggers is required                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: for trapper poller process                                       *
 *                                                                            *
 ******************************************************************************/
void	process_new_value(DB_ITEM *item,char *value, int *update_tr)
{
	time_t 	now;
	char	sql[MAX_STRING_LEN];
	char	value_esc[MAX_STRING_LEN];
	char	value_str[MAX_STRING_LEN];
	double	value_double;
	double	multiplier;
	char	*e;

	*update_tr = 1;

	now = time(NULL);

	strscpy(value_str, value);

	zabbix_log( LOG_LEVEL_DEBUG, "In process_new_value()");
	value_double=strtod(value_str,&e);

	if( (item->value_type==ITEM_VALUE_TYPE_FLOAT) && (item->multiplier == ITEM_MULTIPLIER_USE))
	{
		multiplier = strtod(item->formula,&e);
		value_double = value_double * multiplier;
		snprintf(value_str,sizeof(value_str)-1,"%f",value_double);
	}

	if(item->history>0)
	{
		if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
		{
			/* Should we store delta or original value? */
			if(item->delta == ITEM_STORE_AS_IS)
			{
				DBadd_history(item->itemid,value_double,now);
			}
			/* Delta as speed of change */
			else if(item->delta == ITEM_STORE_SPEED_PER_SECOND)
			{
				/* Save delta */
				if((item->prevorgvalue_null == 0) && (item->prevorgvalue <= value_double) )
				{
					DBadd_history(item->itemid, (value_double - item->prevorgvalue)/(now-item->lastclock), now);
				}
			}
			/* Real delta: simple difference between values */
			else if(item->delta == ITEM_STORE_SIMPLE_CHANGE)
			{
				/* Save delta */
				if((item->prevorgvalue_null == 0) && (item->prevorgvalue <= value_double) )
				{
					DBadd_history(item->itemid, (value_double - item->prevorgvalue), now);
				}
			}
			else
			{
				zabbix_log(LOG_LEVEL_ERR, "Value not stored for itemid [%d]. Unknown delta [%d]", item->itemid, item->delta);
				zabbix_syslog("Value not stored for itemid [%d]. Unknown delta [%d]", item->itemid, item->delta);
				return;
			}
		}
		else if(item->value_type==ITEM_VALUE_TYPE_STR)
		{
			DBadd_history_str(item->itemid,value_str,now);
		}
		else if(item->value_type==ITEM_VALUE_TYPE_LOG)
		{
			DBadd_history_log(item->itemid,value_str,now);
			snprintf(sql,sizeof(sql)-1,"update items set lastlogsize=%d where itemid=%d",item->lastlogsize,item->itemid);
			DBexecute(sql);
		}
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "Unknown value type [%d] for itemid [%d]", item->value_type,item->itemid);
		}
	}

	if(item->delta == ITEM_STORE_AS_IS)
	{
		if((item->prevvalue_null == 1) || (strcmp(value_str,item->lastvalue_str) != 0) || (strcmp(item->prevvalue_str,item->lastvalue_str) != 0) )
		{
			DBescape_string(value_str,value_esc,MAX_STRING_LEN);
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d where itemid=%d",now+item->delay,value_esc,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->delay,now),value_esc,(int)now,item->itemid);
			item->prevvalue=item->lastvalue;
			item->lastvalue=value_double;
			item->prevvalue_str=item->lastvalue_str;
	/* Risky !!!*/
			item->lastvalue_str=value_str;
			item->prevvalue_null=item->lastvalue_null;
			item->lastvalue_null=0;
		}
		else
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,lastclock=%d where itemid=%d",now+item->delay,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->delay,now),(int)now,item->itemid);
			*update_tr=0;
		}
	}
	/* Logic for delta as speed of change */
	else if(item->delta == ITEM_STORE_SPEED_PER_SECOND)
	{
		if((item->prevorgvalue_null == 0) && (item->prevorgvalue <= value_double) )
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",now+item->delay,value_double,(value_double - item->prevorgvalue)/(now-item->lastclock),now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->delay,now),value_double,(value_double - item->prevorgvalue)/(now-item->lastclock),(int)now,item->itemid);
		}
		else
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",now+item->delay,value_double,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->delay,now),value_double,(int)now,item->itemid);
		}

		item->prevvalue=item->lastvalue;
		item->lastvalue=(value_double - item->prevorgvalue)/(now-item->lastclock);
		item->prevvalue_str=item->lastvalue_str;
	/* Risky !!!*/
		item->lastvalue_str=value_str;
		item->prevvalue_null=item->lastvalue_null;
		item->lastvalue_null=0;
	}
	/* Real delta: simple difference between values */
	else if(item->delta == ITEM_STORE_SIMPLE_CHANGE)
	{
		if((item->prevorgvalue_null == 0) && (item->prevorgvalue <= value_double) )
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",now+item->delay,value_double,(value_double - item->prevorgvalue),now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->delay,now),value_double,(value_double - item->prevorgvalue),(int)now,item->itemid);
		}
		else
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",now+item->delay,value_double,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->delay,now),value_double,(int)now,item->itemid);
		}

		item->prevvalue=item->lastvalue;
		item->lastvalue=(value_double - item->prevorgvalue);
		item->prevvalue_str=item->lastvalue_str;
	/* Risky !!!*/
		item->lastvalue_str=value_str;
		item->prevvalue_null=item->lastvalue_null;
		item->lastvalue_null=0;
	}
	DBexecute(sql);

	if(*update_tr == 1)
	{
		update_functions( item );
	}
}
