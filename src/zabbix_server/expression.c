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
#include <string.h>
#include <math.h>

#include "expression.h"
#include "functions.h"
#include "evalfunc.h"
#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"



/******************************************************************************
 *                                                                            *
 * Function: str2double                                                       *
 *                                                                            *
 * Purpose: convert string to double                                          *
 *                                                                            *
 * Parameters: str - string to convert                                        *
 *                                                                            *
 * Return value: converted double value                                       *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: the function automatically processes prefixes 'K','M','G'        *
 *                                                                            *
 ******************************************************************************/
double	str2double(char *str)
{
	if(str[strlen(str)-1] == 'K')
	{
		str[strlen(str)-1] = 0;
		return (double)1024*atof(str);
	}
	else if(str[strlen(str)-1] == 'M')
	{
		str[strlen(str)-1] = 0;
		return (double)1024*1024*atof(str);
	}
	else if(str[strlen(str)-1] == 'G')
	{
		str[strlen(str)-1] = 0;
		return (double)1024*1024*1024*atof(str);
	}
	return atof(str);
}


/******************************************************************************
 *                                                                            *
 * Function: delete_spaces                                                    *
 *                                                                            *
 * Purpose: delete all spaces                                                 *
 *                                                                            *
 * Parameters: c - string to delete spaces                                    *
 *                                                                            *
 * Return value:  the string wtihout spaces                                   *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	delete_spaces(char *c)
{
	int i,j;

	zabbix_log( LOG_LEVEL_DEBUG, "Before deleting spaces:%s", c );

	j=0;
	for(i=0;c[i]!=0;i++)
	{
		if( c[i] != ' ')
		{
			c[j]=c[i];
			j++;
		}
	}
	c[j]=0;

	zabbix_log(LOG_LEVEL_DEBUG, "After deleting spaces:%s", c );
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_simple                                                  *
 *                                                                            *
 * Purpose: evaluate simple expression                                        *
 *                                                                            *
 * Parameters: exp - expression string                                        *
 *                                                                            *
 * Return value:  SUCCEED - evaluated succesfully, result - value of the exp  *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: format: <float> or <float> <operator> <float>                    *
 *                                                                            *
 *           It is recursive function!                                        *
 *                                                                            *
 ******************************************************************************/
int	evaluate_simple (double *result,char *exp,char *error,int maxerrlen)
{
	double	value1,value2;
	char	first[MAX_STRING_LEN],second[MAX_STRING_LEN];
	char 	*p;

	zabbix_log( LOG_LEVEL_DEBUG, "Evaluating simple expression [%s]", exp );

/* Remove left and right spaces */
	lrtrim_spaces(exp);

	if( is_double_prefix(exp) == SUCCEED )
	{
/*		*result=atof(exp);*/
/* str2double support prefixes */
		*result=str2double(exp);
		return SUCCEED;
	}

	if( (p = strstr(exp,"|")) != NULL )
	{
		zabbix_log( LOG_LEVEL_DEBUG, "| is found" );

		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);

/*		l=find_char(exp,'|');
		strscpy( first, exp );
		first[l]=0;
		j=0;
		for(i=l+1;exp[i]!=0;i++)
		{
			second[j]=exp[i];
			j++;
		}
		second[j]=0;*/
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( value1 == 1)
		{
			*result=value1;
			return SUCCEED;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( value2 == 1)
		{
			*result=value2;
			return SUCCEED;
		}
		*result=0;
		return SUCCEED;
	}
	if( (p = strstr(exp,"&")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "& is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);

		zabbix_log(LOG_LEVEL_DEBUG, "[%s] [%s]",first,second );
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( (value1 == 1) && (value2 == 1) )
		{
			*result=1;
		}
		else
		{
			*result=0;
		}
		return SUCCEED;
	}
	if( (p = strstr(exp,">")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "> is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( value1 > value2 )
		{
			*result=1;
		}
		else
		{
			*result=0;
		}
		return SUCCEED;
	}
	if( (p = strstr(exp,"<")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "< is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		zabbix_log(LOG_LEVEL_DEBUG, "[%s] [%s]",first,second );
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( value1 < value2 )
		{
			*result=1;
		}
		else
		{
			*result=0;
		}
		zabbix_log(LOG_LEVEL_DEBUG, "Result [%f]",*result );
		return SUCCEED;
	}
	if( (p = strstr(exp,"*")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "* is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		*result=value1*value2;
		return SUCCEED;
	}
	if( (p = strstr(exp,"/")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "/ is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if(cmp_double(value2,0) == 0)
		{
			snprintf(error,maxerrlen-1,"Division by zero. Cannot evaluate expression [%s/%s]", first,second);
			zabbix_log(LOG_LEVEL_WARNING, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		else
		{
			*result=value1/value2;
		}
		return SUCCEED;
	}
	if( (p = strstr(exp,"+")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "+ is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		*result=value1+value2;
		return SUCCEED;
	}
	if( (p = strstr(exp,"-")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "- is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		*result=value1-value2;
		return SUCCEED;
	}
	if( (p = strstr(exp,"=")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "= is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( cmp_double(value1,value2) ==0 )
		{
			*result=1;
		}
		else
		{
			*result=0;
		}
		return SUCCEED;
	}
	if( (p = strstr(exp,"#")) != NULL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "# is found" );
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return FAIL;
		}
		if( cmp_double(value1,value2) != 0 )
		{
			*result=1;
		}
		else
		{
			*result=0;
		}
		return SUCCEED;
	}
	else
	{
		snprintf(error,maxerrlen-1,"Format error or unsupported operator.  Exp: [%s]", exp);
		zabbix_log(LOG_LEVEL_WARNING, "%s", error);
		zabbix_syslog("%s",error);
		return FAIL;
	}
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate                                                         *
 *                                                                            *
 * Purpose: evaluate simplified expression                                    *
 *                                                                            *
 * Parameters: exp - expression string                                        *
 *                                                                            *
 * Return value:  SUCCEED - evaluated succesfully, result - value of the exp  *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: example: ({15}>10)|({123}=1)                                     *
 *                                                                            *
 ******************************************************************************/
int	evaluate(int *result,char *exp, char *error, int maxerrlen)
{
	double	value;
	char	res[MAX_STRING_LEN];
	char	simple[MAX_STRING_LEN];
	int	i,l,r;

	zabbix_log(LOG_LEVEL_DEBUG, "In evaluate([%s])",exp);

	strscpy( res,exp );

	while( find_char( exp, ')' ) != FAIL )
	{
		l=-1;
		r=find_char(exp,')');
		for(i=r;i>=0;i--)
		{
			if( exp[i] == '(' )
			{
				l=i;
				break;
			}
		}
		if( r == -1 )
		{
			snprintf(error, maxerrlen-1, "Cannot find left bracket [(]. Expression:[%s]", exp);
			zabbix_log(LOG_LEVEL_WARNING, "%s", error);
			zabbix_syslog("%s",error);
			return	FAIL;
		}
		for(i=l+1;i<r;i++)
		{
			simple[i-l-1]=exp[i];
		} 
		simple[r-l-1]=0;

		if( evaluate_simple( &value, simple, error, maxerrlen ) != SUCCEED )
		{
			/* Changed to LOG_LEVEL_DEBUG */
			zabbix_log( LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return	FAIL;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "Expression1:[%s]", exp );

		exp[l]='%';
		exp[l+1]='l';
		exp[l+2]='f';
/*		exp[l]='%';
		exp[l+1]='f';
		exp[l+2]=' ';*/

		for(i=l+3;i<=r;i++) exp[i]=' ';

		snprintf(res,sizeof(res)-1,exp,value);
		strcpy(exp,res);
		delete_spaces(res);
		zabbix_log(LOG_LEVEL_DEBUG, "Expression4:[%s]", res );
	}
	if( evaluate_simple( &value, res, error, maxerrlen ) != SUCCEED )
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s", error);
		zabbix_syslog("%s",error);
		return	FAIL;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Evaluate end:[%lf]", value );
	*result=value;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_simple_macros                                         *
 *                                                                            *
 * Purpose: substitute simple macros in data string with real values          *
 *                                                                            *
 * Parameters: trigger - trigger structure                                    *
 *             action - action structure (NULL if uncnown)                    *
 *             data - data string                                             *
 *             dala_max_len - max length of data string,include '\0'          *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: {DATE},{TIME},{HOSTNAME},{IPADDRESS},{STATUS},                   *
 *           {TRIGGER.NAME}, {TRIGGER.KEY}, {TRIGGER.SEVERITY}                *
 *           {TRIGGER.ID}                                                     *
 *                                                                            *
 ******************************************************************************/
/* definition of macros variables */
#define MVAR_DATE			"{DATE}"
#define MVAR_TIME			"{TIME}"
#define MVAR_HOST_NAME			"{HOSTNAME}"
#define MVAR_IPADDRESS			"{IPADDRESS}"
#define MVAR_TRIGGER_NAME		"{TRIGGER.NAME}"
#define MVAR_TRIGGER_KEY		"{TRIGGER.KEY}"
#define MVAR_TRIGGER_STATUS		"{TRIGGER.STATUS}"
#define MVAR_TRIGGER_STATUS_OLD		"{STATUS}"
#define MVAR_TRIGGER_SEVERITY		"{TRIGGER.SEVERITY}"
#define MVAR_TRIGGER_ID			"{TRIGGER.ID}"

#define STR_UNKNOWN_VARIAVLE		"*UNKNOWN*"

void	substitute_simple_macros(DB_TRIGGER *trigger, DB_ACTION *action, char *data, int dala_max_len, int macro_type)
{
	char	sql[MAX_STRING_LEN];

	char
		*pl = NULL,
		*pr = NULL,
		str_out[MAX_STRING_LEN],
		replace_to[MAX_STRING_LEN];
	int	
		outlen,
		var_len;

	time_t  now;
	struct  tm      *tm;

	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In substitute_simple_macros [%s]",data);

	*str_out = '\0';
	outlen = sizeof(str_out) - 1;
	pl = data;
	while((pr = strchr(pl, '{')) && outlen > 0)
	{
		pr[0] = '\0';
		zbx_strlcat(str_out, pl, outlen);
		outlen -= MIN(strlen(pl), outlen);
		pr[0] = '{';

		snprintf(replace_to, sizeof(replace_to), "{");
		var_len = 1;

		if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_NAME, strlen(MVAR_TRIGGER_NAME)) == 0)
		{
			var_len = strlen(MVAR_TRIGGER_NAME);

			snprintf(replace_to, sizeof(replace_to), "%s", trigger->description);
			substitute_simple_macros(trigger, action, replace_to, sizeof(replace_to), MACRO_TYPE_TRIGGER_DESCRIPTION);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY | MACRO_TYPE_TRIGGER_DESCRIPTION) &&
			strncmp(pr, MVAR_HOST_NAME, strlen(MVAR_HOST_NAME)) == 0)
		{
			var_len = strlen(MVAR_HOST_NAME);

			snprintf(sql,sizeof(sql)-1,"select distinct h.host from triggers t, functions f,items i, hosts h"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid", 
				trigger->triggerid);

			result = DBselect(sql);
			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_ERR, "No hostname in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				zabbix_syslog("No hostname in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);

				snprintf(replace_to, sizeof(replace_to), "%s", STR_UNKNOWN_VARIAVLE);
			}
			else
			{
				snprintf(replace_to, sizeof(replace_to), "%s", row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_KEY, strlen(MVAR_TRIGGER_KEY)) == 0)
		{
			var_len = strlen(MVAR_TRIGGER_KEY);

			snprintf(sql,sizeof(sql)-1,"select distinct i.key_ from triggers t, functions f,items i, hosts h"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid"
				" order by i.key_", trigger->triggerid);

			result = DBselect(sql);
			row=DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_ERR, "No TRIGGER.KEY in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				zabbix_syslog("No TRIGGER.KEY in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				/* remove variable */
				*replace_to = '\0';
			}
			else
			{
				snprintf(replace_to, sizeof(replace_to), "%s", row[0]);
			}

			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_IPADDRESS, strlen(MVAR_IPADDRESS)) == 0)
		{
			var_len = strlen(MVAR_IPADDRESS);

			snprintf(sql,sizeof(sql)-1,"select distinct h.ip from triggers t, functions f,items i, hosts h"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.useip=1",
				trigger->triggerid);

			result = DBselect(sql);
			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_ERR, "No hostname in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				zabbix_syslog("No hostname in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);

				snprintf(replace_to, sizeof(replace_to), "%s", STR_UNKNOWN_VARIAVLE);
			}
			else
			{
				snprintf(replace_to, sizeof(replace_to), "%s", row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_DATE, strlen(MVAR_DATE)) == 0)
		{
			var_len = strlen(MVAR_TIME);

			now	= time(NULL);
			tm	= localtime(&now);
			snprintf(replace_to, sizeof(replace_to)-1, "%.4d.%.2d.%.2d", tm->tm_year+1900, tm->tm_mon+1, tm->tm_mday);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY)&&
			strncmp(pr, MVAR_TIME, strlen(MVAR_TIME)) == 0)
		{
			var_len = strlen(MVAR_TIME);

			now	= time(NULL);
			tm	= localtime(&now);
			snprintf(replace_to, sizeof(replace_to), "%.2d:%.2d:%.2d",tm->tm_hour,tm->tm_min,tm->tm_sec);

		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_STATUS, strlen(MVAR_TRIGGER_STATUS)) == 0)
		{
			/* NOTE: if you make changes for this bloc, don't forgot MVAR_TRIGGER_STATUS_OLD block */
			var_len = strlen(MVAR_TRIGGER_STATUS);

			if(trigger->value == TRIGGER_VALUE_TRUE)
				snprintf(replace_to, sizeof(replace_to), "OFF");
			else
				snprintf(replace_to, sizeof(replace_to), "ON");
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) && 
			strncmp(pr, MVAR_TRIGGER_STATUS_OLD, strlen(MVAR_TRIGGER_STATUS_OLD)) == 0)
		{
			/* NOTE: if you make changes for this bloc, don't forgot MVAR_TRIGGER_STATUS block */
			var_len = strlen(MVAR_TRIGGER_STATUS_OLD);

			if(trigger->value == TRIGGER_VALUE_TRUE)
				snprintf(replace_to, sizeof(replace_to), "OFF");
			else
				snprintf(replace_to, sizeof(replace_to), "ON");
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) && 
			strncmp(pr, MVAR_TRIGGER_ID, strlen(MVAR_TRIGGER_ID)) == 0)
		{
			/* NOTE: if you make changes for this bloc, don't forgot MVAR_TRIGGER_STATUS block */
			var_len = strlen(MVAR_TRIGGER_ID);

			snprintf(replace_to, sizeof(replace_to), "%d", trigger->triggerid);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) && 
			strncmp(pr, MVAR_TRIGGER_SEVERITY, strlen(MVAR_TRIGGER_SEVERITY)) == 0)
		{
			var_len = strlen(MVAR_TRIGGER_SEVERITY);

			if(trigger->priority == 0)	snprintf(replace_to, sizeof(replace_to), "Not classified");
                        else if(trigger->priority == 1)	snprintf(replace_to, sizeof(replace_to), "Information");
                        else if(trigger->priority == 2)	snprintf(replace_to, sizeof(replace_to), "Warning");
                        else if(trigger->priority == 3)	snprintf(replace_to, sizeof(replace_to), "Average");
                        else if(trigger->priority == 4)	snprintf(replace_to, sizeof(replace_to), "High");
                        else if(trigger->priority == 5)	snprintf(replace_to, sizeof(replace_to), "Disaster");
                        else				snprintf(replace_to, sizeof(replace_to), "Unknown");
		}

		zbx_strlcat(str_out, replace_to, outlen);
		outlen -= MIN(strlen(replace_to), outlen);
		pl = pr + var_len;
	}
	zbx_strlcat(str_out, pl, outlen);
	outlen -= MIN(strlen(pl), outlen);

	snprintf(data, dala_max_len, "%s", str_out);

	zabbix_log( LOG_LEVEL_DEBUG, "Result expression [%s]", data );
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_macros                                                *
 *                                                                            *
 * Purpose: substitute macros in data string with real values                 *
 *                                                                            *
 * Parameters: trigger - trigger structure                                    *
 *             action - action structure                                      *
 *             dala_max_len - max length of data string,include '\0'          *
 *             data - data string                                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: example: "{127.0.0.1:system[procload].last(0)}" to "1.34"        *
 *                                                                            *
 ******************************************************************************/
void	substitute_macros(DB_TRIGGER *trigger, DB_ACTION *action, char *data, int dala_max_len)
{
	char	
		str_out[MAX_STRING_LEN],
		replace_to[MAX_STRING_LEN],
		*pl = NULL,
		*pr = NULL,
		*pms = NULL,
		*pme = NULL,
		*p = NULL;
	char
		host[MAX_STRING_LEN],
		key[MAX_STRING_LEN],
		function[MAX_STRING_LEN],
		parameter[MAX_STRING_LEN];

	int
		outlen,
		var_len;


	zabbix_log(LOG_LEVEL_DEBUG, "In substitute_macros([%s])",data);

	substitute_simple_macros(trigger, action, data, dala_max_len, MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY);

	*str_out = '\0';
	outlen = sizeof(str_out) - 1;
	pl = data;
	while((pr = strchr(pl, '{')) && outlen > 0)
	{
		if((pme = strchr(pr, '}')) == NULL)
			break;

		pme[0] = '\0';
	
		pr = strrchr(pr, '{'); /* find '{' near '}' */	

		/* copy left side */
		pr[0] = '\0';
		zbx_strlcat(str_out, pl, outlen);
		outlen -= MIN(strlen(pl), outlen);
		pr[0] = '{';


		/* copy original name of variable */
		snprintf(replace_to, sizeof(replace_to), "%s}", pr);	/* in format used '}' */
									/* cose in 'pr' string symbol '}' is changed to '\0' by 'pme'*/
		var_len = strlen(replace_to);
		
		pms = pr + 1;
	
		if(NULL != (p = strchr(pms, ':')))
		{
			*p = '\0';
			snprintf(host, sizeof(host), "%s", pms);
			*p = ':';
			pms = p + 1;
			if(NULL != (p = strrchr(pms, '.')))
			{
				*p = '\0';
				snprintf(key, sizeof(key), "%s", pms);
				*p = '.';
				pms = p + 1;
				if(NULL != (p = strchr(pms, '(')))
				{
					*p = '\0';
					snprintf(function, sizeof(function), "%s", pms);
					*p = '(';
					pms = p + 1;
					if(NULL != (p = strchr(pms, ')')))
					{
						*p = '\0';
						snprintf(parameter, sizeof(parameter), "%s", pms);
						*p = ')';
						pms = p + 1;
						
						if(evaluate_FUNCTION2(replace_to,host,key,function,parameter) != SUCCEED)
							snprintf(replace_to, sizeof(replace_to), "%s", STR_UNKNOWN_VARIAVLE);
					}
				}
			}
			
		}
		pme[0] = '}';

		zbx_strlcat(str_out, replace_to, outlen);
		outlen -= MIN(strlen(replace_to), outlen);
		pl = pr + var_len;
	}
	zbx_strlcat(str_out, pl, outlen);
	outlen -= MIN(strlen(pl), outlen);

	snprintf(data, dala_max_len, "%s", str_out);

	zabbix_log( LOG_LEVEL_DEBUG, "Result expression:%s", data );
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_functions                                             *
 *                                                                            *
 * Purpose: substitute expression functions with theirs values                *
 *                                                                            *
 * Parameters: exp - expression string                                        *
 *             error - place error message here if any                        *
 *             maxerrlen - max length of error msg                            *
 *                                                                            *
 * Return value:  SUCCEED - evaluated succesfully, exp - updated expression   *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: example: "({15}>10)|({123}=0)" => "(6.456>10)|(0=0)              *
 *                                                                            *
 ******************************************************************************/
int	substitute_functions(char *exp, char *error, int maxerrlen)
{
	double	value;
	char	functionid[MAX_STRING_LEN];
	char	res[MAX_STRING_LEN];
	int	i,l,r;

	zabbix_log(LOG_LEVEL_DEBUG, "BEGIN substitute_functions (%s)", exp);

	while( find_char(exp,'{') != FAIL )
	{
		l=find_char(exp,'{');
		r=find_char(exp,'}');
		if( r == FAIL )
		{
			snprintf(error,maxerrlen-1,"Cannot find right bracket. Expression:[%s]", exp);
			zabbix_log( LOG_LEVEL_WARNING, "%s", error);
			zabbix_syslog("%s",error);
			return	FAIL;
		}
		if( r < l )
		{
			snprintf(error,maxerrlen-1, "Right bracket is before left one. Expression:[%s]", exp);
			zabbix_log( LOG_LEVEL_WARNING, "%s", error);
			zabbix_syslog("%s",error);
			return	FAIL;
		}

		for(i=l+1;i<r;i++)
		{
			functionid[i-l-1]=exp[i];
		} 
		functionid[r-l-1]=0;

		if( DBget_function_result( &value, functionid ) != SUCCEED )
		{
/* It may happen because of functions.lastvalue is NULL, so this is not warning  */
			snprintf(error,maxerrlen-1, "Unable to get value for functionid [%s]", functionid);
			zabbix_log( LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s",error);
			return	FAIL;
		}


		zabbix_log( LOG_LEVEL_DEBUG, "Expression1:[%s]", exp );

		exp[l]='%';
		exp[l+1]='l';
		exp[l+2]='f';
/*		exp[l]='%';
		exp[l+1]='f';
		exp[l+2]=' ';*/

		zabbix_log( LOG_LEVEL_DEBUG, "Expression2:[%s]", exp );

		for(i=l+3;i<=r;i++) exp[i]=' ';

		zabbix_log( LOG_LEVEL_DEBUG, "Expression3:[%s]", exp );

		snprintf(res,sizeof(res)-1,exp,value);
		strcpy(exp,res);
		delete_spaces(exp);
		zabbix_log( LOG_LEVEL_DEBUG, "Expression4:[%s]", exp );
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Expression:[%s]", exp );
	zabbix_log( LOG_LEVEL_DEBUG, "END substitute_functions" );

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_expression                                              *
 *                                                                            *
 * Purpose: evaluate expression                                               *
 *                                                                            *
 * Parameters: exp - expression string                                        *
 *             error - place rrror message if any                             *
 *             maxerrlen - max length of error message                        *
 *                                                                            *
 * Return value:  SUCCEED - evaluated succesfully, result - value of the exp  *
 *                FAIL - otherwise                                            *
 *                error - error message                                       *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: example: ({a0:system[procload].last(0)}>1)|                      *
 *                    ({a0:system[procload].max(300)}>3)                      *
 *                                                                            *
 ******************************************************************************/
int	evaluate_expression(int *result,char *expression, char *error, int maxerrlen)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In evaluate_expression(%s)", expression );

	delete_spaces(expression);
	if( substitute_functions(expression, error, maxerrlen) == SUCCEED)
	{
		if( evaluate(result, expression, error, maxerrlen) == SUCCEED)
		{
			return SUCCEED;
		}
	}
	zabbix_log(LOG_LEVEL_WARNING, "Evaluation of expression [%s] failed [%s]", expression, error );
	zabbix_syslog("Evaluation of expression [%s] failed [%s]", expression, error );

	return FAIL;
}
