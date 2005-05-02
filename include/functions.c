/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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

#include "functions.h"
#include "expression.h"

/* Delete trailing zeroes */
/* 10.0100 -> 10.01, 10. -> 10 */
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

/* Calculate nextcheck for items.
  Old algorithm: now+delay
  New one: preserve period over time, If delay==5, nextcheck = 0,5,10,15,20...
*/
int	calculate_item_nextcheck(int nextcheck, int delay, int now)
{
	int i;

	i=delay*(int)(now/delay);

	while(i<=now)	i+=delay;

	return i;
}

#ifdef ZABBIX_THREADS
/*
 * Evaluate function COUNT
 */ 
int	evaluate_COUNT_thread(MYSQL *database, char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select count(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect_thread(database, sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for COUNT is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
	}
	DBfree_result(result);

	return res;
}
#endif

/*
 * Evaluate function COUNT
 */ 
int	evaluate_COUNT(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select count(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for COUNT is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
	}
	DBfree_result(result);

	return res;
}

#ifdef ZABBIX_THREADS
/*
 * Evaluate function SUM
 */ 
int	evaluate_SUM_thread(MYSQL *database, char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select sum(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect_thread(database, sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for SUM is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
	}
	DBfree_result(result);

	return res;
}
#endif

/*
 * Evaluate function SUM
 */ 
int	evaluate_SUM(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select sum(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for SUM is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
	}
	DBfree_result(result);

	return res;
}

#ifdef ZABBIX_THREADS
/*
 * Evaluate function AVG
 */ 
int	evaluate_AVG_thread(MYSQL *database,char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select avg(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect_thread(database,sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for AVG is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
		del_zeroes(value);
	}
	DBfree_result(result);

	return res;
}
#endif

/*
 * Evaluate function AVG
 */ 
int	evaluate_AVG(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select avg(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for AVG is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
		del_zeroes(value);
	}
	DBfree_result(result);

	return res;
}

#ifdef ZABBIX_THREADS
/*
 * Evaluate function MIN
 */ 
int	evaluate_MIN_thread(MYSQL *database, char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select min(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect_thread(database,sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MIN is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
		del_zeroes(value);
	}
	DBfree_result(result);

	return res;
}
#endif

/*
 * Evaluate function MIN
 */ 
int	evaluate_MIN(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select min(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MIN is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
		del_zeroes(value);
	}
	DBfree_result(result);

	return res;
}

#ifdef ZABBIX_THREADS
/*
 * Evaluate function MAX
 */ 
int	evaluate_MAX_thread(MYSQL *database, char *value,DB_ITEM *item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select max(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect_thread(database, sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
		del_zeroes(value);
	}
	DBfree_result(result);

	return res;
}
#endif

/*
 * Evaluate function MAX
 */ 
int	evaluate_MAX(char *value,DB_ITEM *item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select max(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
		del_zeroes(value);
	}
	DBfree_result(result);

	return res;
}

#ifdef ZABBIX_THREADS
/*
 * Evaluate function DELTA
 */ 
int	evaluate_DELTA_thread(MYSQL *database,char *value,DB_ITEM *item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select max(value)-min(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect_thread(database,sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for DELTA is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
		del_zeroes(value);
	}
	DBfree_result(result);

	return res;
}
#endif


/*
 * Evaluate function DELTA
 */ 
int	evaluate_DELTA(char *value,DB_ITEM *item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select max(value)-min(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for DELTA is empty" );
		res = FAIL;
	}
	else
	{
		strcpy(value,DBget_field(result,0,0));
		del_zeroes(value);
	}
	DBfree_result(result);

	return res;
}

#ifdef ZABBIX_THREADS
/*
 * Evaluate function (avg,min,max,prev,last,diff,str,change,abschange,delta,time,date)
 */ 
int	evaluate_FUNCTION_thread(MYSQL *database, char *value,DB_ITEM *item,char *function,char *parameter, int flag)
{
	int	ret  = SUCCEED;
	time_t  now;
	struct  tm      *tm;
	
	float	value_float;
	float	value_float_abs;
	char	suffix[MAX_STRING_LEN];

	int	day;

	zabbix_log( LOG_LEVEL_DEBUG, "Function [%s]",function);

	if(strcmp(function,"last")==0)
	{
		if(item->lastvalue_null==1)
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 1");
				snprintf(value,MAX_STRING_LEN-1,"%f",item->lastvalue);
				del_zeroes(value);
				zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 2");
			}
			else
			{
/*				*value=strdup(item->lastvalue_str);*/
				zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 3 [%s] [%s]",value,item->lastvalue_str);
				strcpy(value,item->lastvalue_str);
				zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 4");
			}
		}
	}
	else if(strcmp(function,"prev")==0)
	{
		if(item->prevvalue_null==1)
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				snprintf(value,MAX_STRING_LEN-1,"%f",item->prevvalue);
				del_zeroes(value);
			}
			else
			{
				strcpy(value,item->prevvalue_str);
			}
		}
	}
	else if(strcmp(function,"min")==0)
	{
		ret = evaluate_MIN_thread(database,value,item,atoi(parameter));
	}
	else if(strcmp(function,"max")==0)
	{
		ret = evaluate_MAX_thread(database,value,item,atoi(parameter));
	}
	else if(strcmp(function,"avg")==0)
	{
		ret = evaluate_AVG_thread(database,value,item,atoi(parameter));
	}
	else if(strcmp(function,"sum")==0)
	{
		ret = evaluate_SUM_thread(database,value,item,atoi(parameter));
	}
	else if(strcmp(function,"count")==0)
	{
		ret = evaluate_COUNT_thread(database,value,item,atoi(parameter));
	}
	else if(strcmp(function,"delta")==0)
	{
		ret = evaluate_DELTA_thread(database,value,item,atoi(parameter));
	}
	else if(strcmp(function,"nodata")==0)
	{
		strcpy(value,"0");
	}
	else if(strcmp(function,"date")==0)
	{
		now=time(NULL);
                tm=localtime(&now);
                snprintf(value,MAX_STRING_LEN-1,"%.4d%.2d%.2d",tm->tm_year+1900,tm->tm_mon+1,tm->tm_mday);
	}
	else if(strcmp(function,"dayofweek")==0)
	{
		now=time(NULL);
                tm=localtime(&now);
		/* The number of days since Sunday, in the range 0 to 6. */
		day=tm->tm_wday;
		if(0 == day)	day=7;
                snprintf(value,MAX_STRING_LEN-1,"%d", day);
	}
	else if(strcmp(function,"time")==0)
	{
		now=time(NULL);
                tm=localtime(&now);
                snprintf(value,MAX_STRING_LEN-1,"%.2d%.2d%.2d",tm->tm_hour,tm->tm_min,tm->tm_sec);
	}
	else if(strcmp(function,"abschange")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				snprintf(value,MAX_STRING_LEN-1,"%f",(float)abs(item->lastvalue-item->prevvalue));
				del_zeroes(value);
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"change")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				snprintf(value,MAX_STRING_LEN-1,"%f",item->lastvalue-item->prevvalue);
				del_zeroes(value);
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"diff")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				if(cmp_double(item->lastvalue, item->prevvalue) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"str")==0)
	{
		if(item->value_type==ITEM_VALUE_TYPE_STR)
		{
			if(strstr(item->lastvalue_str, parameter) == NULL)
			{
				strcpy(value,"0");
			}
			else
			{
				strcpy(value,"1");
			}

		}
		else
		{
			ret = FAIL;
		}
	}
	else if(strcmp(function,"now")==0)
	{
		now=time(NULL);
                snprintf(value,MAX_STRING_LEN-1,"%d",(int)now);
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unsupported function:%s",function);
		zabbix_syslog("Unsupported function:%s",function);
		ret = FAIL;
	}

	/* Add suffix: 1000000 -> 1 MB */
	if( (EVALUATE_FUNCTION_SUFFIX == flag) && (ITEM_VALUE_TYPE_FLOAT == item->value_type) &&
		(SUCCEED == ret) && strlen(item->units)>0)
	{
		value_float=atof(value);
		/* Use multiplier ? */
/*		if(item->multiplier == 1)
		{
			zabbix_log( LOG_LEVEL_WARNING, "HMM [%s] [%f] [%f]",item->formula, value_float, atof(item->formula));
			value_float=value_float*atof(item->formula);
		}*/
		value_float_abs=abs(value_float);
		if(value_float_abs<1024)
		{
			strscpy(suffix,"");
		}
		else if(value_float_abs<1024*1024)
		{
			strscpy(suffix,"K");
			value_float=value_float/1024;
		}
		else if(value_float_abs<1024*1024*1024)
		{
			strscpy(suffix,"M");
			value_float=value_float/(1024*1024);
		}
		else
		{
			strscpy(suffix,"G");
			value_float=value_float/(1024*1024*1024);
		}
		zabbix_log( LOG_LEVEL_DEBUG, "Value [%s] [%f] Suffix [%s] Units [%s]",value,value_float,suffix,item->units);
//		if(cmp_double((double)round(value_float), value_float) == 0)
		if(cmp_double((int)(value_float+0.5), value_float) == 0)
		{
                	snprintf(value, MAX_STRING_LEN-1, "%.0f %s%s", value_float, suffix, item->units);
		}
		else
		{
                	snprintf(value, MAX_STRING_LEN-1, "%.2f %s%s", value_float, suffix, item->units);
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End of evaluate_FUNCTION. Result [%s]",value);
	return ret;
}
#endif

/*
 * Evaluate function (avg,min,max,prev,last,diff,str,change,abschange,delta,time,date)
 */ 
int	evaluate_FUNCTION(char *value,DB_ITEM *item,char *function,char *parameter, int flag)
{
	int	ret  = SUCCEED;
	time_t  now;
	struct  tm      *tm;
	
	float	value_float;
	float	value_float_abs;
	char	suffix[MAX_STRING_LEN];

	int	day;

	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() Function [%s] flag [%d]",function,flag);

	if(strcmp(function,"last")==0)
	{
		if(item->lastvalue_null==1)
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 1");
				snprintf(value,MAX_STRING_LEN-1,"%f",item->lastvalue);
				del_zeroes(value);
				zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 2 value [%s]", value);
			}
			else
			{
/*				*value=strdup(item->lastvalue_str);*/
				zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 3 [%s] [%s]",value,item->lastvalue_str);
				strcpy(value,item->lastvalue_str);
				zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 4");
			}
		}
	}
	else if(strcmp(function,"prev")==0)
	{
		if(item->prevvalue_null==1)
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				snprintf(value,MAX_STRING_LEN-1,"%f",item->prevvalue);
				del_zeroes(value);
			}
			else
			{
				strcpy(value,item->prevvalue_str);
			}
		}
	}
	else if(strcmp(function,"min")==0)
	{
		ret = evaluate_MIN(value,item,atoi(parameter));
	}
	else if(strcmp(function,"max")==0)
	{
		ret = evaluate_MAX(value,item,atoi(parameter));
	}
	else if(strcmp(function,"avg")==0)
	{
		ret = evaluate_AVG(value,item,atoi(parameter));
	}
	else if(strcmp(function,"sum")==0)
	{
		ret = evaluate_SUM(value,item,atoi(parameter));
	}
	else if(strcmp(function,"count")==0)
	{
		ret = evaluate_COUNT(value,item,atoi(parameter));
	}
	else if(strcmp(function,"delta")==0)
	{
		ret = evaluate_DELTA(value,item,atoi(parameter));
	}
	else if(strcmp(function,"nodata")==0)
	{
		strcpy(value,"0");
	}
	else if(strcmp(function,"date")==0)
	{
		now=time(NULL);
                tm=localtime(&now);
                snprintf(value,MAX_STRING_LEN-1,"%.4d%.2d%.2d",tm->tm_year+1900,tm->tm_mon+1,tm->tm_mday);
	}
	else if(strcmp(function,"dayofweek")==0)
	{
		now=time(NULL);
                tm=localtime(&now);
		/* The number of days since Sunday, in the range 0 to 6. */
		day=tm->tm_wday;
		if(0 == day)	day=7;
                snprintf(value,MAX_STRING_LEN-1,"%d", day);
	}
	else if(strcmp(function,"time")==0)
	{
		now=time(NULL);
                tm=localtime(&now);
                snprintf(value,MAX_STRING_LEN-1,"%.2d%.2d%.2d",tm->tm_hour,tm->tm_min,tm->tm_sec);
	}
	else if(strcmp(function,"abschange")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				snprintf(value,MAX_STRING_LEN-1,"%f",(float)abs(item->lastvalue-item->prevvalue));
				del_zeroes(value);
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"change")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				snprintf(value,MAX_STRING_LEN-1,"%f",item->lastvalue-item->prevvalue);
				del_zeroes(value);
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"diff")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				if(cmp_double(item->lastvalue, item->prevvalue) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"str")==0)
	{
		if(item->value_type==ITEM_VALUE_TYPE_STR)
		{
			if(strstr(item->lastvalue_str, parameter) == NULL)
			{
				strcpy(value,"0");
			}
			else
			{
				strcpy(value,"1");
			}

		}
		else
		{
			ret = FAIL;
		}
	}
	else if(strcmp(function,"now")==0)
	{
		now=time(NULL);
                snprintf(value,MAX_STRING_LEN-1,"%d",(int)now);
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unsupported function:%s",function);
		zabbix_syslog("Unsupported function:%s",function);
		ret = FAIL;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() pre-7");
	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 7 Formula [%s]", item->formula);
	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 7 Value [%s]", value);
	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 7 Units [%s]", item->units);
	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 7 Value [%s] Units [%s] Formula [%s]", value, item->units, item->formula);

	/* Add suffix: 1000000 -> 1 MB */
	if( (EVALUATE_FUNCTION_SUFFIX == flag) && (ITEM_VALUE_TYPE_FLOAT == item->value_type) &&
		(SUCCEED == ret) && strlen(item->units)>0)
	{
		value_float=atof(value);
		/* Custom multiplier? */
/*
		if(item->multiplier == 1)
		{
			value_float=value_float*atof(item->formula);
		}*/

		value_float_abs=abs(value_float);

		if(value_float_abs<1024)
		{
			strscpy(suffix,"");
		}
		else if(value_float_abs<1024*1024)
		{
			strscpy(suffix,"K");
			value_float=value_float/1024;
		}
		else if(value_float_abs<1024*1024*1024)
		{
			strscpy(suffix,"M");
			value_float=value_float/(1024*1024);
		}
		else
		{
			strscpy(suffix,"G");
			value_float=value_float/(1024*1024*1024);
		}
		zabbix_log( LOG_LEVEL_DEBUG, "Value [%s] [%f] Suffix [%s] Units [%s]",value,value_float,suffix,item->units);
//		if(cmp_double((double)round(value_float), value_float) == 0)
		if(cmp_double((int)(value_float+0.5), value_float) == 0)
		{
                	snprintf(value, MAX_STRING_LEN-1, "%.0f %s%s", value_float, suffix, item->units);
		}
		else
		{
                	snprintf(value, MAX_STRING_LEN-1, "%.2f %s%s", value_float, suffix, item->units);
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End of evaluate_FUNCTION. Result [%s]",value);
	return ret;
}

#ifdef ZABBIX_THREADS
/*
 * Re-calculate values of functions related to given ITEM
 */ 
void	update_functions_thread(MYSQL *database, DB_ITEM *item)
{
	DB_FUNCTION	function;
	DB_RESULT	*result;
	char		sql[MAX_STRING_LEN];
	char		value[MAX_STRING_LEN];
	char		value_esc[MAX_STRING_LEN];
	int		ret=SUCCEED;
	int		i;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_functions(%d)",item->itemid);

	snprintf(sql,sizeof(sql)-1,"select function,parameter,itemid from functions where itemid=%d group by 1,2,3 order by 1,2,3",item->itemid);

	result = DBselect_thread(database, sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		function.function=DBget_field(result,i,0);
		function.parameter=DBget_field(result,i,1);
		function.itemid=atoi(DBget_field(result,i,2));

		zabbix_log( LOG_LEVEL_DEBUG, "ItemId:%d Evaluating %s(%d)\n",function.itemid,function.function,function.parameter);

		ret = evaluate_FUNCTION_thread(database,value,item,function.function,function.parameter, EVALUATE_FUNCTION_NORMAL);
		if( FAIL == ret)	
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Evaluation failed for function:%s\n",function.function);
			continue;
		}
		zabbix_log( LOG_LEVEL_DEBUG, "Result of evaluate_FUNCTION [%s]\n",value);
		if (ret == SUCCEED)
		{
			DBescape_string(value,value_esc,MAX_STRING_LEN);
			snprintf(sql,sizeof(sql)-1,"update functions set lastvalue='%s' where itemid=%d and function='%s' and parameter='%s'", value_esc, function.itemid, function.function, function.parameter );
			DBexecute_thread(database, sql);
		}
	}

	DBfree_result(result);
}
#endif

/*
 * Re-calculate values of functions related to given ITEM
 */ 
void	update_functions(DB_ITEM *item)
{
	DB_FUNCTION	function;
	DB_RESULT	*result;
	char		sql[MAX_STRING_LEN];
	char		value[MAX_STRING_LEN];
	char		value_esc[MAX_STRING_LEN];
	int		ret=SUCCEED;
	int		i;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_functions(%d)",item->itemid);

	snprintf(sql,sizeof(sql)-1,"select function,parameter,itemid from functions where itemid=%d group by 1,2,3 order by 1,2,3",item->itemid);

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		function.function=DBget_field(result,i,0);
		function.parameter=DBget_field(result,i,1);
		function.itemid=atoi(DBget_field(result,i,2));

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
			DBescape_string(value,value_esc,MAX_STRING_LEN);
			snprintf(sql,sizeof(sql)-1,"update functions set lastvalue='%s' where itemid=%d and function='%s' and parameter='%s'", value_esc, function.itemid, function.function, function.parameter );
			DBexecute(sql);
		}
	}

	DBfree_result(result);
}

#ifdef ZABBIX_THREADS
/* Cannot use action->userid as it may also represent groupd id*/
void	send_to_user_medias_thread(MYSQL *database, DB_TRIGGER *trigger,DB_ACTION *action, int userid)
{
	DB_MEDIA media;
	char sql[MAX_STRING_LEN];
	DB_RESULT *result;

	int	i;

	snprintf(sql,sizeof(sql)-1,"select mediatypeid,sendto,active,severity from media where active=%d and userid=%d",MEDIA_STATUS_ACTIVE,userid);
	result = DBselect_thread(database, sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		media.mediatypeid=atoi(DBget_field(result,i,0));
		media.sendto=DBget_field(result,i,1);
		media.active=atoi(DBget_field(result,i,2));
		media.severity=atoi(DBget_field(result,i,3));

		zabbix_log( LOG_LEVEL_DEBUG, "Trigger severity [%d] Media severity [%d]",trigger->priority, media.severity);
		if(((1<<trigger->priority)&media.severity)==0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Won't send message");
			continue;
		}

		DBadd_alert_thread(database, action->actionid,media.mediatypeid,media.sendto,action->subject,action->message);
	}
	DBfree_result(result);
}
#endif

/* Cannot use action->userid as it may also represent groupd id*/
void	send_to_user_medias(DB_TRIGGER *trigger,DB_ACTION *action, int userid)
{
	DB_MEDIA media;
	char sql[MAX_STRING_LEN];
	DB_RESULT *result;

	int	i;

	snprintf(sql,sizeof(sql)-1,"select mediatypeid,sendto,active,severity from media where active=%d and userid=%d",MEDIA_STATUS_ACTIVE,userid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		media.mediatypeid=atoi(DBget_field(result,i,0));
		media.sendto=DBget_field(result,i,1);
		media.active=atoi(DBget_field(result,i,2));
		media.severity=atoi(DBget_field(result,i,3));

		zabbix_log( LOG_LEVEL_DEBUG, "Trigger severity [%d] Media severity [%d]",trigger->priority, media.severity);
		if(((1<<trigger->priority)&media.severity)==0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Won't send message");
			continue;
		}

		DBadd_alert(action->actionid,media.mediatypeid,media.sendto,action->subject,action->message);
	}
	DBfree_result(result);
}

#ifdef ZABBIX_THREADS
/*
 * Send message to user. Message will be sent to all medias registered to given user.
 */ 
void	send_to_user_thread(MYSQL *database, DB_TRIGGER *trigger,DB_ACTION *action)
{
	char sql[MAX_STRING_LEN];
	DB_RESULT *result;

	int	i;

	if(action->recipient == RECIPIENT_TYPE_USER)
	{
		send_to_user_medias_thread(database,trigger, action, action->userid);
	}
	else if(action->recipient == RECIPIENT_TYPE_GROUP)
	{
		snprintf(sql,sizeof(sql)-1,"select u.userid from users u, users_groups ug where ug.usrgrpid=%d and ug.userid=u.userid", action->userid);
		result = DBselect_thread(database, sql);
		for(i=0;i<DBnum_rows(result);i++)
		{
			send_to_user_medias_thread(database, trigger, action, atoi(DBget_field(result,i,0)));
		}
		DBfree_result(result);
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unknown recipient type [%d] for actionid [%d]",action->recipient,action->actionid);
		zabbix_syslog("Unknown recipient type [%d] for actionid [%d]",action->recipient,action->actionid);
	}
}
#endif

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

#ifdef ZABBIX_THREADS
/*
 * Apply actions if any.
 */ 
/*void	apply_actions(int triggerid,int good)*/
void	apply_actions_thread(MYSQL *database, DB_TRIGGER *trigger,int trigger_value)
{
	DB_RESULT *result,*result2,*result3;
	
	DB_ACTION action;

	char sql[MAX_STRING_LEN];

	int	i,j;
	int	now;

	zabbix_log( LOG_LEVEL_DEBUG, "In apply_actions(%d,%d)",trigger->triggerid, trigger_value);

	if(TRIGGER_VALUE_TRUE == trigger_value)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Check dependencies");

		snprintf(sql,sizeof(sql)-1,"select count(*) from trigger_depends d,triggers t where d.triggerid_down=%d and d.triggerid_up=t.triggerid and t.value=%d",trigger->triggerid, TRIGGER_VALUE_TRUE);
		result = DBselect_thread(database, sql);
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
	result = DBselect_thread(database, sql);

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

			substitute_macros_thread(database,trigger, &action, action.message);
			substitute_macros_thread(database,trigger, &action, action.subject);

			send_to_user_thread(database,trigger,&action);
			snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
			DBexecute_thread(database,sql);
		}
		else if(ACTION_SCOPE_HOST==action.scope)
		{
			if(trigger->priority<action.severity)
			{
				continue;
			}

			snprintf(sql,sizeof(sql)-1,"select distinct h.hostid from hosts h,items i,triggers t,functions f where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=%d", trigger->triggerid);
			result2 = DBselect_thread(database,sql);

			for(j=0;j<DBnum_rows(result2);j++)
			{
				snprintf(sql,sizeof(sql)-1,"select distinct a.actionid from actions a,hosts h,items i,triggers t,functions f where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and a.triggerid=%d and a.scope=1 and a.actionid=%d and a.triggerid=h.hostid",atoi(DBget_field(result2,j,0)),action.actionid);
				result3 = DBselect_thread(database,sql);
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

				substitute_macros_thread(database,trigger, &action, action.message);
				substitute_macros_thread(database,trigger, &action, action.subject);

				send_to_user_thread(database,trigger,&action);
				snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
				DBexecute_thread(database,sql);
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

			substitute_macros_thread(database,trigger, &action, action.message);
			substitute_macros_thread(database,trigger, &action, action.subject);

/*			substitute_hostname(trigger->triggerid,action.message);
			substitute_hostname(trigger->triggerid,action.subject);*/

			send_to_user_thread(database,trigger,&action);
			snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
			DBexecute_thread(database,sql);
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
#endif

/*
 * Apply actions if any.
 */ 
/*void	apply_actions(int triggerid,int good)*/
void	apply_actions(DB_TRIGGER *trigger,int trigger_value)
{
	DB_RESULT *result,*result2,*result3;
	
	DB_ACTION action;

	char sql[MAX_STRING_LEN];

	int	i,j;
	int	now;

	zabbix_log( LOG_LEVEL_DEBUG, "In apply_actions(%d,%d)",trigger->triggerid, trigger_value);

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

#ifdef ZABBIX_THREADS
/*
 * Recursive function!
 */
void	update_serv_thread(MYSQL *database,int serviceid)
{
	char	sql[MAX_STRING_LEN];
	int	i;
	int	status;
	int	serviceupid, algorithm;
	int	now;

	DB_RESULT *result,*result2;

	snprintf(sql,sizeof(sql)-1,"select l.serviceupid,s.algorithm from services_links l,services s where s.serviceid=l.serviceupid and l.servicedownid=%d",serviceid);
	result=DBselect_thread(database, sql);
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
			result2=DBselect_thread(database, sql);
			if(atoi(DBget_field(result2,0,0))!=0)
			{
				status=atoi(DBget_field(result2,0,1));
			}
			DBfree_result(result2);

			now=time(NULL);
			DBadd_service_alarm_thread(database, atoi(DBget_field(result,i,0)),status,now);
			snprintf(sql,sizeof(sql)-1,"update services set status=%d where serviceid=%d",status,atoi(DBget_field(result,i,0)));
			DBexecute_thread(database, sql);
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unknown calculation algorithm of service status [%d]", algorithm);
			zabbix_syslog("Unknown calculation algorithm of service status [%d]", algorithm);
		}
	}
	DBfree_result(result);

	snprintf(sql,sizeof(sql)-1,"select serviceupid from services_links where servicedownid=%d",serviceid);
	result=DBselect_thread(database, sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		update_serv_thread(database,atoi(DBget_field(result,i,0)));
	}
	DBfree_result(result);
}
#endif

/*
 * Recursive function!
 */
void	update_serv(int serviceid)
{
	char	sql[MAX_STRING_LEN];
	int	i;
	int	status;
	int	serviceupid, algorithm;
	int	now;

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
		update_serv(atoi(DBget_field(result,i,0)));
	}
	DBfree_result(result);
}

#ifdef ZABBIX_THREADS
void	update_services_thread(MYSQL *database, int triggerid, int status)
{
	char	sql[MAX_STRING_LEN];
	int	i;

	DB_RESULT *result;

	snprintf(sql,sizeof(sql)-1,"update services set status=%d where triggerid=%d",status,triggerid);
	DBexecute_thread(database, sql);


	snprintf(sql,sizeof(sql)-1,"select serviceid from services where triggerid=%d", triggerid);
	result = DBselect_thread(database, sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		update_serv_thread(database, atoi(DBget_field(result,i,0)));
	}

	DBfree_result(result);
	return;
}
#endif

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
		update_serv(atoi(DBget_field(result,i,0)));
	}

	DBfree_result(result);
	return;
}

#ifdef ZABBIX_THREADS
/*
* Re-calculate values of triggers
*/ 
void	update_triggers_thread(MYSQL *database, int itemid)
{
	char	sql[MAX_STRING_LEN];
	char	exp[MAX_STRING_LEN];
	int	b;
	int	now;
	DB_TRIGGER	trigger;
	DB_RESULT	*result;

	int	i;
	int	prevvalue;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_triggers [%d]", itemid);

/* Does not work for PostgreSQL */
/*		sprintf(sql,"select t.triggerid,t.expression,t.status,t.dep_level,t.priority,t.value from triggers t,functions f,items i where i.status<>3 and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=%d group by t.triggerid,t.expression,t.dep_level",TRIGGER_STATUS_ENABLED,sucker_num);*/
/* Is it correct SQL? */
	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid,t.expression,t.status,t.dep_level,t.priority,t.value,t.description from triggers t,functions f,items i where i.status<>%d and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=%d",ITEM_STATUS_NOTSUPPORTED, TRIGGER_STATUS_ENABLED, itemid);

	result = DBselect_thread(database, sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		trigger.triggerid=atoi(DBget_field(result,i,0));
		trigger.expression=DBget_field(result,i,1);
		trigger.status=atoi(DBget_field(result,i,2));
		trigger.priority=atoi(DBget_field(result,i,4));

		trigger.value=atoi(DBget_field(result,i,5));
		trigger.description=DBget_field(result,i,6);
		strscpy(exp, trigger.expression);
		if( evaluate_expression_thread(database, &b, exp) != 0 )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Expression [%s] cannot be evaluated.",trigger.expression);
			zabbix_syslog("Expression [%s] cannot be evaluated.",trigger.expression);
			continue;
		}

/* Oprimise a little bit */
		prevvalue=DBget_prev_trigger_value_thread(database, trigger.triggerid);

		zabbix_log( LOG_LEVEL_DEBUG, "b trigger.value prevvalue [%d] [%d] [%d]", b, trigger.value, prevvalue);

		if(TRIGGER_VALUE_TRUE == b)
		{
			if(trigger.value != TRIGGER_VALUE_TRUE)
			{
				now = time(NULL);
				DBupdate_trigger_value_thread(database, trigger.triggerid,TRIGGER_VALUE_TRUE,now);
			}
			if((trigger.value == TRIGGER_VALUE_FALSE)
			||
			(
			 (trigger.value == TRIGGER_VALUE_UNKNOWN) &&
/* Optimise a little bit. This optimisation does not work because DBupdate_trigger_value may add alarm! */
			 (prevvalue == TRIGGER_VALUE_FALSE)
/*			 (DBget_prev_trigger_value(trigger.triggerid) == TRIGGER_VALUE_FALSE)*/
			))
			{
				now = time(NULL);
/*				apply_actions(trigger.triggerid,1);*/
				apply_actions_thread(database, &trigger,1);
	
				snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=0 where triggerid=%d and good=0",trigger.triggerid);
				DBexecute_thread(database,sql);

				update_services_thread(database,trigger.triggerid, trigger.priority);
			}

		}

		if(TRIGGER_VALUE_FALSE == b)
		{
			if(trigger.value != TRIGGER_VALUE_FALSE)
			{
				now = time(NULL);
				DBupdate_trigger_value_thread(database,trigger.triggerid,TRIGGER_VALUE_FALSE,now);
			}
			if((trigger.value == TRIGGER_VALUE_TRUE)
			||
			(
			 (trigger.value == TRIGGER_VALUE_UNKNOWN) &&
/* Optimise a little bit. This optimisation does not work because DBupdate_trigger_value may add alarm! */
			 (prevvalue == TRIGGER_VALUE_TRUE)
/*			 (DBget_prev_trigger_value(trigger.triggerid) == TRIGGER_VALUE_TRUE)*/
			))
			{
/*				apply_actions(trigger.triggerid,0);*/
				apply_actions_thread(database,&trigger,0);

				snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=0 where triggerid=%d and good=1",trigger.triggerid);
				DBexecute_thread(database,sql);

				update_services_thread(database,trigger.triggerid, 0);
			}
		}
	}
	DBfree_result(result);
}
#endif

/*
* Re-calculate values of triggers
*/ 
void	update_triggers(int itemid)
{
	char	sql[MAX_STRING_LEN];
	char	exp[MAX_STRING_LEN];
	int	b;
	int	now;
	DB_TRIGGER	trigger;
	DB_RESULT	*result;

	int	i;
	int	prevvalue;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_triggers [%d]", itemid);

/* Does not work for PostgreSQL */
/*		sprintf(sql,"select t.triggerid,t.expression,t.status,t.dep_level,t.priority,t.value from triggers t,functions f,items i where i.status<>3 and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=%d group by t.triggerid,t.expression,t.dep_level",TRIGGER_STATUS_ENABLED,sucker_num);*/
/* Is it correct SQL? */
	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid,t.expression,t.status,t.dep_level,t.priority,t.value,t.description from triggers t,functions f,items i where i.status<>%d and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=%d",ITEM_STATUS_NOTSUPPORTED, TRIGGER_STATUS_ENABLED, itemid);

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		trigger.triggerid=atoi(DBget_field(result,i,0));
		trigger.expression=DBget_field(result,i,1);
		trigger.status=atoi(DBget_field(result,i,2));
		trigger.priority=atoi(DBget_field(result,i,4));

		trigger.value=atoi(DBget_field(result,i,5));
		trigger.description=DBget_field(result,i,6);
		strscpy(exp, trigger.expression);
		if( evaluate_expression(&b, exp) != 0 )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Expression [%s] cannot be evaluated.",trigger.expression);
			zabbix_syslog("Expression [%s] cannot be evaluated.",trigger.expression);
			continue;
		}

/* Oprimise a little bit */
		prevvalue=DBget_prev_trigger_value(trigger.triggerid);

		zabbix_log( LOG_LEVEL_DEBUG, "b trigger.value prevvalue [%d] [%d] [%d]", b, trigger.value, prevvalue);

		if(TRIGGER_VALUE_TRUE == b)
		{
			if(trigger.value != TRIGGER_VALUE_TRUE)
			{
				now = time(NULL);
				DBupdate_trigger_value(trigger.triggerid,TRIGGER_VALUE_TRUE,now);
			}
			if((trigger.value == TRIGGER_VALUE_FALSE)
			||
			(
			 (trigger.value == TRIGGER_VALUE_UNKNOWN) &&
/* Optimise a little bit. This optimisation does not work because DBupdate_trigger_value may add alarm! */
			 (prevvalue == TRIGGER_VALUE_FALSE)
/*			 (DBget_prev_trigger_value(trigger.triggerid) == TRIGGER_VALUE_FALSE)*/
			))
			{
				now = time(NULL);
/*				apply_actions(trigger.triggerid,1);*/
				apply_actions(&trigger,1);
	
				snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=0 where triggerid=%d and good=0",trigger.triggerid);
				DBexecute(sql);

				update_services(trigger.triggerid, trigger.priority);
			}

		}

		if(TRIGGER_VALUE_FALSE == b)
		{
			if(trigger.value != TRIGGER_VALUE_FALSE)
			{
				now = time(NULL);
				DBupdate_trigger_value(trigger.triggerid,TRIGGER_VALUE_FALSE,now);
			}
			if((trigger.value == TRIGGER_VALUE_TRUE)
			||
			(
			 (trigger.value == TRIGGER_VALUE_UNKNOWN) &&
/* Optimise a little bit. This optimisation does not work because DBupdate_trigger_value may add alarm! */
			 (prevvalue == TRIGGER_VALUE_TRUE)
/*			 (DBget_prev_trigger_value(trigger.triggerid) == TRIGGER_VALUE_TRUE)*/
			))
			{
/*				apply_actions(trigger.triggerid,0);*/
				apply_actions(&trigger,0);

				snprintf(sql,sizeof(sql)-1,"update actions set nextcheck=0 where triggerid=%d and good=1",trigger.triggerid);
				DBexecute(sql);

				update_services(trigger.triggerid, 0);
			}
		}
	}
	DBfree_result(result);
}

#ifdef ZABBIX_THREADS
/*
 The fuction is used to evaluate macros for email notifications
*/
int	get_lastvalue_thread(MYSQL *database, char *value,char *host,char *key,char *function,char *parameter)
{
	DB_ITEM	item;
	DB_RESULT *result;

        char	sql[MAX_STRING_LEN];
	char	*s;
	int	res;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_lastvalue()" );

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.prevvalue,i.lastvalue,i.value_type,i.multiplier,i.units from items i,hosts h where h.host='%s' and h.hostid=i.hostid and i.key_='%s'", host, key );
	result = DBselect_thread(database,sql);

	if(DBnum_rows(result) == 0)
	{
        	DBfree_result(result);
		zabbix_log(LOG_LEVEL_WARNING, "Query [%s] returned empty result", sql );
		zabbix_syslog("Query [%s] returned empty result", sql );
		return FAIL;	
	}

        item.itemid=atoi(DBget_field(result,0,0));
	s=DBget_field(result,0,1);
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
	s=DBget_field(result,0,2);
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
        item.value_type=atoi(DBget_field(result,0,3));

        item.multiplier=atoi(DBget_field(result,0,4));
        item.units=DBget_field(result,0,5);

	zabbix_log(LOG_LEVEL_DEBUG, "Itemid:%d", item.itemid );

	zabbix_log(LOG_LEVEL_DEBUG, "Before evaluate_FUNCTION()" );

	res = evaluate_FUNCTION_thread(database, value,&item,function,parameter, EVALUATE_FUNCTION_SUFFIX);

/* Cannot call DBfree_result until evaluate_FUNC */
	DBfree_result(result);
	return res;
}
#endif

/*
 The fuction is used to evaluate macros for email notifications
*/
int	get_lastvalue(char *value,char *host,char *key,char *function,char *parameter)
{
	DB_ITEM	item;
	DB_RESULT *result;

        char	sql[MAX_STRING_LEN];
	int	res;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_lastvalue()" );

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,h.available from items i,hosts h where h.host='%s' and h.hostid=i.hostid and i.key_='%s'", host, key );
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
        	DBfree_result(result);
		zabbix_log(LOG_LEVEL_WARNING, "Query [%s] returned empty result", sql );
		zabbix_syslog("Query [%s] returned empty result", sql );
		return FAIL;
	}

	DBget_item_from_db(&item,result, 0);

/*	item.itemid=atoi(DBget_field(result,0,0));
	s=DBget_field(result,0,1);
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
	s=DBget_field(result,0,2);
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
        item.value_type=atoi(DBget_field(result,0,3));

        item.multiplier=atoi(DBget_field(result,0,4));
        item.units=DBget_field(result,0,5);
*/

	zabbix_log(LOG_LEVEL_DEBUG, "Itemid:%d", item.itemid );

	zabbix_log(LOG_LEVEL_DEBUG, "Before evaluate_FUNCTION()" );

	res = evaluate_FUNCTION(value,&item,function,parameter, EVALUATE_FUNCTION_SUFFIX);

/* Cannot call DBfree_result until evaluate_FUNC */
	DBfree_result(result);
	return res;
}

/* For zabbix_trapper(d) */
int	send_list_of_active_checks(int sockfd, char *host)
{
	char	sql[MAX_STRING_LEN];
	char	s[MAX_STRING_LEN];
	DB_RESULT *result;

	int 	i;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_list_of_active_checks()");

	snprintf(sql,sizeof(sql)-1,"select i.key_,i.delay from items i,hosts h where i.hostid=h.hostid and h.status=%d and i.status=%d and i.type=%d and h.host='%s'", HOST_STATUS_MONITORED, ITEM_STATUS_ACTIVE, ITEM_TYPE_ZABBIX_ACTIVE, host);

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		snprintf(s,sizeof(s)-1,"%s:%s\n",DBget_field(result,i,0),DBget_field(result,i,1));
		zabbix_log( LOG_LEVEL_DEBUG, "Sending [%s]", s);
		if( write(sockfd,s,strlen(s)) == -1 )
		{
			switch (errno)
			{
				case EINTR:
					zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending list of active checks");
					break;
				default:
					zabbix_log( LOG_LEVEL_WARNING, "Error while sending list of active checks [%s]", strerror(errno));
			}
			close(sockfd);
			return  FAIL;
		}
	}
	DBfree_result(result);

	snprintf(s,sizeof(s)-1,"%s\n","ZBX_EOF");
	zabbix_log( LOG_LEVEL_DEBUG, "Sending [%s]", s);
	if( write(sockfd,s,strlen(s)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending list of active checks");
				break;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Error while sending list of active checks [%s]", strerror(errno));
		}
		close(sockfd);
		return  FAIL;
	}

	return  SUCCEED;
}

/* For zabbix_trapper(d) */
/* int	process_data(char *server,char *key, double value)*/
int	process_data(int sockfd,char *server,char *key,char *value)
{
	char	sql[MAX_STRING_LEN];

	DB_RESULT       *result;
	DB_ITEM	item;
	char	*s;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_data()");

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.value_type,i.trapper_hosts,i.delta,i.units,i.multiplier,i.formula from items i,hosts h where h.status=%d and h.hostid=i.hostid and h.host='%s' and i.key_='%s' and i.status=%d and i.type in (%d,%d)", HOST_STATUS_MONITORED, server, key, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		DBfree_result(result);
		return  FAIL;
	}

	item.trapper_hosts=DBget_field(result,0,16);
	if(check_security(sockfd,item.trapper_hosts,1) == FAIL)
	{
		DBfree_result(result);
		return  FAIL;
	}
	
	item.itemid=atoi(DBget_field(result,0,0));
	item.key=DBget_field(result,0,1);
	item.host=DBget_field(result,0,2);
	item.port=atoi(DBget_field(result,0,3));
	item.delay=atoi(DBget_field(result,0,4));
	item.description=DBget_field(result,0,5);
	item.nextcheck=atoi(DBget_field(result,0,6));
	item.type=atoi(DBget_field(result,0,7));
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

	process_new_value(&item,value);

	update_triggers(item.itemid);
 
	DBfree_result(result);

	return SUCCEED;
}

#ifdef ZABBIX_THREADS
void	process_new_value_thread(MYSQL *database, DB_ITEM *item,char *value)
{
	int 	now;
	char	sql[MAX_STRING_LEN];
	char	value_str[MAX_STRING_LEN];
	char	value_esc[MAX_STRING_LEN];
	double	value_double;
	double	multiplier;
	char	*e;

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
				DBadd_history_thread(database, item->itemid,value_double,now);
			}
			/* Delta as speed of change */
			else if(item->delta == ITEM_STORE_SPEED_PER_SECOND)
			{
				/* Save delta */
				if((item->prevorgvalue_null == 0) && (item->prevorgvalue <= value_double) )
				{
					DBadd_history_thread(database, item->itemid, (value_double - item->prevorgvalue)/(now-item->lastclock), now);
				}
			}
			/* Real delta: simple difference between values */
			else if(item->delta == ITEM_STORE_SIMPLE_CHANGE)
			{
				/* Save delta */
				if((item->prevorgvalue_null == 0) && (item->prevorgvalue <= value_double) )
				{
					DBadd_history_thread(database, item->itemid, (value_double - item->prevorgvalue), now);
				}
			}
			else
			{
				zabbix_log(LOG_LEVEL_ERR, "Value not stored for itemid [%d]. Unknown delta [%d]", item->itemid, item->delta);
				zabbix_syslog("Value not stored for itemid [%d]. Unknown delta [%d]", item->itemid, item->delta);
				return;
			}
		}
		else
		{
			DBadd_history_str_thread(database, item->itemid,value_str,now);
		}
	}


	if(item->delta == ITEM_STORE_AS_IS)
	{
		if((item->prevvalue_null == 1) || (strcmp(value_str,item->lastvalue_str) != 0) || (strcmp(item->prevvalue_str,item->lastvalue_str) != 0) )
		{
			DBescape_string(value_str,value_esc,MAX_STRING_LEN);
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d where itemid=%d",now+item->delay,value_esc,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_esc,now,item->itemid);
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
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),now,item->itemid);
		}
	}
	/* Logic for delta as speed of change */
	else if(item->delta == ITEM_STORE_SPEED_PER_SECOND)
	{
		if((item->prevorgvalue_null == 0) && (item->prevorgvalue <= value_double) )
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",now+item->delay,value_double,(value_double - item->prevorgvalue)/(now-item->lastclock),now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_double,(value_double - item->prevorgvalue)/(now-item->lastclock),now,item->itemid);
		}
		else
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",now+item->delay,value_double,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_double,now,item->itemid);
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
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_double,(value_double - item->prevorgvalue),now,item->itemid);
		}
		else
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",now+item->delay,value_double,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_double,now,item->itemid);
		}

		item->prevvalue=item->lastvalue;
		item->lastvalue=(value_double - item->prevorgvalue);
		item->prevvalue_str=item->lastvalue_str;
	/* Risky !!!*/
		item->lastvalue_str=value_str;
		item->prevvalue_null=item->lastvalue_null;
		item->lastvalue_null=0;
	}
	DBexecute_thread(database,sql);

	update_functions_thread(database, item );
}
#endif

void	process_new_value(DB_ITEM *item,char *value)
{
	int 	now;
	char	sql[MAX_STRING_LEN];
	char	value_esc[MAX_STRING_LEN];
	char	value_str[MAX_STRING_LEN];
	double	value_double;
	double	multiplier;
	char	*e;

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
		else
		{
			DBadd_history_str(item->itemid,value_str,now);
		}
	}

	if(item->delta == ITEM_STORE_AS_IS)
	{
		if((item->prevvalue_null == 1) || (strcmp(value_str,item->lastvalue_str) != 0) || (strcmp(item->prevvalue_str,item->lastvalue_str) != 0) )
		{
			DBescape_string(value_str,value_esc,MAX_STRING_LEN);
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d where itemid=%d",now+item->delay,value_esc,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_esc,now,item->itemid);
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
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),now,item->itemid);
		}
	}
	/* Logic for delta as speed of change */
	else if(item->delta == ITEM_STORE_SPEED_PER_SECOND)
	{
		if((item->prevorgvalue_null == 0) && (item->prevorgvalue <= value_double) )
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",now+item->delay,value_double,(value_double - item->prevorgvalue)/(now-item->lastclock),now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_double,(value_double - item->prevorgvalue)/(now-item->lastclock),now,item->itemid);
		}
		else
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",now+item->delay,value_double,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_double,now,item->itemid);
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
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue=%f,lastvalue='%f',lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_double,(value_double - item->prevorgvalue),now,item->itemid);
		}
		else
		{
/*			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",now+item->delay,value_double,now,item->itemid);*/
			snprintf(sql,sizeof(sql)-1,"update items set nextcheck=%d,prevorgvalue=%f,lastclock=%d where itemid=%d",calculate_item_nextcheck(item->nextcheck,item->delay,now),value_double,now,item->itemid);
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

	update_functions( item );
}
