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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( value1 == 1)
		{
			*result=value1;
			return SUCCEED;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if(cmp_double(value2,0) == 0)
		{
			zbx_snprintf(error,maxerrlen,"Division by zero. Cannot evaluate expression [%s/%s]", first,second);
			zabbix_log(LOG_LEVEL_WARNING, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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
		zbx_snprintf(error,maxerrlen,"Format error or unsupported operator.  Exp: [%s]", exp);
		zabbix_log(LOG_LEVEL_WARNING, error);
		zabbix_syslog(error);
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
			zbx_snprintf(error, maxerrlen, "Cannot find left bracket [(]. Expression:[%s]", exp);
			zabbix_log(LOG_LEVEL_WARNING, error);
			zabbix_syslog(error);
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
			zabbix_log( LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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

		zbx_snprintf(res,sizeof(res),exp,value);
		strcpy(exp,res);
		delete_spaces(res);
		zabbix_log(LOG_LEVEL_DEBUG, "Expression4:[%s]", res );
	}
	if( evaluate_simple( &value, res, error, maxerrlen ) != SUCCEED )
	{
		zabbix_log(LOG_LEVEL_WARNING, error);
		zabbix_syslog(error);
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
 * Parameters: data - data string                                             *
 *             trigger - trigger structure                                    *
 *             action - action structure                                      *
 *                                                                            *
 * Return value:  SUCCEED - substituted succesfully, data - updated data      *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: {DATE},{TIME},{HOSTNAME},{IPADDRESS},{STATUS},                   *
 *           {TRIGGER.DESCRIPTION}, {TRIGGER.KEY}                             *
 *                                                                            *
 ******************************************************************************/
void	substitute_simple_macros(DB_TRIGGER *trigger, DB_ACTION *action, char *data)
{
	int	found = SUCCEED;
	char	*s;
	char	sql[MAX_STRING_LEN];
	char	str[MAX_STRING_LEN];
	char	tmp[MAX_STRING_LEN];

	time_t  now;
	struct  tm      *tm;

	DB_RESULT result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In substitute_simple_macros [%s]",data);

	while (found == SUCCEED)
	{
		strscpy(str, data);


		if( (s = strstr(str,"{TRIGGER.NAME}")) != NULL )
		{
			s[0]=0;
			strcpy(data, str);
			strncat(data, trigger->description, MAX_STRING_LEN);
			strncat(data, s+strlen("{TRIGGER.NAME}"), MAX_STRING_LEN);
		}
		else if( (s = strstr(str,"{HOSTNAME}")) != NULL )
		{
/*			zbx_snprintf(sql,sizeof(sql),"select distinct t.description,h.host from triggers t, functions f,items i, hosts h where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid", trigger->triggerid);*/
			zbx_snprintf(sql,sizeof(sql),"select distinct h.host from triggers t, functions f,items i, hosts h where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid", trigger->triggerid);
			result = DBselect(sql);
			row=DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_ERR, "No hostname in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				zabbix_syslog("No hostname in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				strscpy(tmp, "*UNKNOWN*");
				DBfree_result(result);
			}
			else
			{
				strscpy(tmp,row[0]);

				DBfree_result(result);
			}

			s[0]=0;
			strcpy(data, str);
			strncat(data, tmp, MAX_STRING_LEN);
			strncat(data, s+strlen("{HOSTNAME}"), MAX_STRING_LEN);
		}
		else if( (s = strstr(str,"{TRIGGER.KEY}")) != NULL )
		{
			zbx_snprintf(sql,sizeof(sql),"select distinct i.key_ from triggers t, functions f,items i, hosts h where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid order by i.key_", trigger->triggerid);
			result = DBselect(sql);
			row=DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_ERR, "No TRIGGER.KEY in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				zabbix_syslog("No TRIGGER.KEY in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				strscpy(tmp, "");
				DBfree_result(result);
			}
			else
			{
				strscpy(tmp,row[0]);

				DBfree_result(result);
			}

			s[0]=0;
			strcpy(data, str);
			strncat(data, tmp, MAX_STRING_LEN);
			strncat(data, s+strlen("{TRIGGER.KEY}"), MAX_STRING_LEN);
		}
		else if( (s = strstr(str,"{IPADDRESS}")) != NULL )
		{
			zbx_snprintf(sql,sizeof(sql),"select distinct h.ip from triggers t, functions f,items i, hosts h where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.useip=1", trigger->triggerid);
			result = DBselect(sql);
			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_ERR, "No IP address in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				zabbix_syslog("No IP address in substitute_simple_macros. Triggerid [%d]", trigger->triggerid);
				strscpy(tmp, "*UNKNOWN IP*");
				DBfree_result(result);
			}
			else
			{
				strscpy(tmp,row[0]);

				DBfree_result(result);
			}

			s[0]=0;
			strcpy(data, str);
			strncat(data, tmp, MAX_STRING_LEN);
			strncat(data, s+strlen("{IPADDRESS}"), MAX_STRING_LEN);
		}
		else if( (s = strstr(str,"{DATE}")) != NULL )
		{
			now=time(NULL);
			tm=localtime(&now);
			zbx_snprintf(tmp,sizeof(tmp),"%.4d.%.2d.%.2d",tm->tm_year+1900,tm->tm_mon+1,tm->tm_mday);

			s[0]=0;
			strcpy(data, str);
			strncat(data, tmp, MAX_STRING_LEN);
			strncat(data, s+strlen("{DATE}"), MAX_STRING_LEN);
		}
		else if( (s = strstr(str,"{TIME}")) != NULL )
		{
			now=time(NULL);
			tm=localtime(&now);
			zbx_snprintf(tmp,sizeof(tmp),"%.2d:%.2d:%.2d",tm->tm_hour,tm->tm_min,tm->tm_sec);

			s[0]=0;
			strcpy(data, str);
			strncat(data, tmp, MAX_STRING_LEN);
			strncat(data, s+strlen("{TIME}"), MAX_STRING_LEN);
		}
		else if( (s = strstr(str,"{STATUS}")) != NULL )
		{
			/* This is old value */
			if(trigger->value == TRIGGER_VALUE_TRUE)
			{
				zbx_snprintf(tmp,sizeof(tmp),"OFF");
			}
			else
			{
				zbx_snprintf(tmp,sizeof(tmp),"ON");
			}

			s[0]=0;
			strcpy(data, str);
			strncat(data, tmp, MAX_STRING_LEN);
			strncat(data, s+strlen("{STATUS}"), MAX_STRING_LEN);
		}
		else
		{
			found = FAIL;
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Result expression [%s]", data );
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_macros                                                *
 *                                                                            *
 * Purpose: substitute macros in data string with real values                 *
 *                                                                            *
 * Parameters: data - data string                                             *
 *             trigger - trigger structure                                    *
 *             action - action structure                                      *
 *                                                                            *
 * Return value:  SUCCEED - substituted succesfully, data - updated data      *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: example: "{127.0.0.1:system[procload].last(0)}" to "1.34"        *
 *                                                                            *
 * Make this function more secure. Get rid of snprintf. Utilise substr()      *
 *                                                                            *
 ******************************************************************************/
int	substitute_macros(DB_TRIGGER *trigger, DB_ACTION *action, char *data)
{
	char	res[MAX_STRING_LEN];
	char	macro[MAX_STRING_LEN];
	char	host[MAX_STRING_LEN];
	char	key[MAX_STRING_LEN];
	char	function[MAX_STRING_LEN];
	char	parameter[MAX_STRING_LEN];
	static	char	value[MAX_STRING_LEN];
	int	i;
	int	r,l;
	int	r1,l1;

	zabbix_log(LOG_LEVEL_DEBUG, "In substitute_macros([%s])",data);

	substitute_simple_macros(trigger, action, data);

	while( find_char(data,'{') != FAIL )
	{
		l=find_char(data,'{');
		r=find_char(data,'}');

		if( r == FAIL )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Cannot find right bracket. Expression:[%s]", data );
			zabbix_syslog("Cannot find right bracket. Expression:[%s]", data );
			return	FAIL;
		}

		if( r < l )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Right bracket is before left one. Expression:[%s]", data );
			zabbix_syslog("Right bracket is before left one. Expression:[%s]", data );
			return	FAIL;
		}

		for(i=l+1;i<r;i++)
		{
			macro[i-l-1]=data[i];
		} 
		macro[r-l-1]=0;

		zabbix_log( LOG_LEVEL_DEBUG, "Macro:%s", macro );

		/* macro=="host:key.function(parameter)" */

		r1=find_char(macro,':');

		for(i=0;i<r1;i++)
		{
			host[i]=macro[i];
		} 
		host[r1]=0;

		zabbix_log( LOG_LEVEL_DEBUG, "Host:%s", host );

		r1=r1+1;
/* Doesn't work if the key contains '.' */
/*		l1=find_char(macro+r1,'.');*/

		l1=FAIL;
		for(i=0;(macro+r1)[i]!=0;i++)
		{
			if((macro+r1)[i]=='.') l1=i;
		}

		for(i=r1;i<l1+r1;i++)
		{
			key[i-r1]=macro[i];
		} 
		key[l1]=0;

		zabbix_log( LOG_LEVEL_DEBUG, "Key:%s", key );

		l1=l1+r1+1;
		r1=find_char(macro+l1,'(');

		for(i=l1;i<l1+r1;i++)
		{
			function[i-l1]=macro[i];
		} 
		function[r1]=0;

		zabbix_log( LOG_LEVEL_DEBUG, "Function:%s", function );

		l1=l1+r1+1;
		r1=find_char(macro+l1,')');

		for(i=l1;i<l1+r1;i++)
		{
			parameter[i-l1]=macro[i];
		} 
		parameter[r1]=0;

		zabbix_log( LOG_LEVEL_DEBUG, "Parameter:%s", parameter );

		i=evaluate_FUNCTION2(value,host,key,function,parameter);
		zabbix_log( LOG_LEVEL_DEBUG, "Value3 [%s]", value );


		zabbix_log( LOG_LEVEL_DEBUG, "Value4 [%s]", data );
		data[l]='%';
		data[l+1]='s';

		zabbix_log( LOG_LEVEL_DEBUG, "Value41 [%s]", data+l+2 );
		zabbix_log( LOG_LEVEL_DEBUG, "Value42 [%s]", data+r+1 );
		strcpy(data+l+2,data+r+1);

		zabbix_log( LOG_LEVEL_DEBUG, "Value5 [%s]", data );

		zbx_snprintf(res,sizeof(res),data,value);
		strcpy(data,res);
/*		delete_spaces(data); */
		zabbix_log( LOG_LEVEL_DEBUG, "Expression4:[%s]", data );
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Result expression:%s", data );

	return SUCCEED;
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
			zbx_snprintf(error,maxerrlen,"Cannot find right bracket. Expression:[%s]", exp);
			zabbix_log( LOG_LEVEL_WARNING, error);
			zabbix_syslog(error);
			return	FAIL;
		}
		if( r < l )
		{
			zbx_snprintf(error,maxerrlen, "Right bracket is before left one. Expression:[%s]", exp);
			zabbix_log( LOG_LEVEL_WARNING, error);
			zabbix_syslog(error);
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
			zbx_snprintf(error,maxerrlen, "Unable to get value for functionid [%s]", functionid);
			zabbix_log( LOG_LEVEL_DEBUG, error);
			zabbix_syslog(error);
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

		zbx_snprintf(res,sizeof(res),exp,value);
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
