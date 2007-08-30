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
 * Comments: format: <double> or <double> <operator> <double>                 *
 *                                                                            *
 *           It is recursive function!                                        *
 *                                                                            *
 ******************************************************************************/
int	evaluate_simple(double *result,char *exp,char *error,int maxerrlen)
{
	double	value1,value2;
	char	first[MAX_STRING_LEN],second[MAX_STRING_LEN];
	char 	*p;

	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_simple(%s)",
		exp);

/* Remove left and right spaces */
	lrtrim_spaces(exp);

/* Compress repeating - and +. Add prefix N to negative numebrs. */
	compress_signs(exp);

	/* We should process negative prefix, i.e. N123 == -123 */
	if( exp[0]=='N' && is_double_prefix(exp+1) == SUCCEED )
	{
/* str2double support prefixes */
		*result=-str2double(exp+1);
		return SUCCEED;
	}
	else if( exp[0]!='N' && is_double_prefix(exp) == SUCCEED )
	{
/* str2double support prefixes */
		*result=str2double(exp);
		return SUCCEED;
	}

	/* Operators with lowest priority come first */
	/* HIGHEST / * - + < > # = & | LOWEST */
	if( (p = strchr(exp,'|')) != NULL )
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);

		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s", error);
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
			zabbix_syslog("%s", error);
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
	if( (p = strchr(exp,'&')) != NULL )
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);

		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s", error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s", error);
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
	if((p = strchr(exp,'=')) != NULL)
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
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
	if((p = strchr(exp,'#')) != NULL)
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
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
	if((p = strchr(exp,'>')) != NULL)
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s", error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zabbix_syslog("%s", error);
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
	if((p = strchr(exp,'<')) != NULL)
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
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
		zabbix_log(LOG_LEVEL_DEBUG, "Result [" ZBX_FS_DBL "]",*result );
		return SUCCEED;
	}
	if((p = strchr(exp,'+')) != NULL)
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		*result=value1+value2;
		return SUCCEED;
	}
	if((p = strchr(exp,'-')) != NULL)
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		*result=value1-value2;
		return SUCCEED;
	}
	if((p = strchr(exp,'*')) != NULL)
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		*result=value1*value2;
		return SUCCEED;
	}
	if((p = strchr(exp,'/')) != NULL)
	{
		*p=0;
		strscpy( first, exp);
		*p='|';
		p++;
		strscpy( second, p);
		if( evaluate_simple(&value1,first,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		if( evaluate_simple(&value2,second,error,maxerrlen) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		if(cmp_double(value2,0) == 0)
		{
			zbx_snprintf(error,maxerrlen,"Division by zero. Cannot evaluate expression [%s/%s]",
				first,
				second);
			zabbix_log(LOG_LEVEL_WARNING, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return FAIL;
		}
		else
		{
			*result=value1/value2;
		}
		return SUCCEED;
	}
	else
	{
		zbx_snprintf(error,maxerrlen,"Format error or unsupported operator.  Exp: [%s]",
			exp);
		zabbix_log(LOG_LEVEL_WARNING, "%s",
			error);
		zabbix_syslog("%s",
			error);
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
int	evaluate(int *result, char *exp, char *error, int maxerrlen)
{
	double	value;
	char	*res;
	char	simple[MAX_STRING_LEN];
	char	tmp[MAX_STRING_LEN];
	char	value_str[MAX_STRING_LEN];
	int	i,l,r;
	char	c;
	int	t;

	zabbix_log(LOG_LEVEL_DEBUG, "In evaluate(%s)",
		exp);

	res = NULL;

	strscpy(tmp, exp);
	t=0;
	while( find_char( tmp, ')' ) != FAIL )
	{
		l=-1;
		r=find_char(tmp,')');
		for(i=r;i>=0;i--)
		{
			if( tmp[i] == '(' )
			{
				l=i;
				break;
			}
		}
		if( l == -1 )
		{
			zbx_snprintf(error, maxerrlen, "Cannot find left bracket [(]. Expression:[%s]",
				tmp);
			zabbix_log(LOG_LEVEL_WARNING, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return	FAIL;
		}
		for(i=l+1;i<r;i++)
		{
			simple[i-l-1]=tmp[i];
		} 
		simple[r-l-1]=0;

		if( evaluate_simple( &value, simple, error, maxerrlen ) != SUCCEED )
		{
			/* Changed to LOG_LEVEL_DEBUG */
			zabbix_log( LOG_LEVEL_DEBUG, "%s",
				error);
			zabbix_syslog("%s",
				error);
			return	FAIL;
		}

		/* res = first+simple+second */
		c=tmp[l]; tmp[l]='\0';
		res = zbx_strdcat(res, tmp);
		tmp[l]=c;

		zbx_snprintf(value_str,MAX_STRING_LEN-1,"%lf",
			value);
		res = zbx_strdcat(res, value_str);
		res = zbx_strdcat(res, tmp+r+1);

		delete_spaces(res);
		strscpy(tmp,res);

		zbx_free(res); res = NULL;
	}
	if( evaluate_simple( &value, tmp, error, maxerrlen ) != SUCCEED )
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s",
			error);
		zabbix_syslog("%s",
			error);
		return	FAIL;
	}
	if(cmp_double(value,0) == 0)
	{
		*result = TRIGGER_VALUE_FALSE;
	}
	else
	{
		*result = TRIGGER_VALUE_TRUE;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End evaluate(result:%lf)",
		value);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: extract_numbers                                                  *
 *                                                                            *
 * Purpose: Extract from string numbers with prefixes (A-Z)                   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *           Use zbx_free_numbers to free allocated memory                    *
 *                                                                            *
 ******************************************************************************/
static char**	extract_numbers(char *str, int *count)
{
	char *s = NULL;
	char *e = NULL;

	char **result = NULL;

	int	dot_founded = 0;
	int	len = 0;

	assert(count);

	*count = 0;

	/* find start of number */
	for ( s = str; *s; s++)
	{
		if ( !isdigit(*s) ) {
			continue; /* for s */
		}

		if ( s != str && '{' == *(s-1) ) {
			/* skip functions '{65432}' */
			s = strchr(s, '}');
			continue; /* for s */
		}

		dot_founded = 0;
		/* find end of number */
		for ( e = s; *e; e++ )
		{
			if ( isdigit(*e) ) {
				continue; /* for e */
			}
			else if ( '.' == *e && !dot_founded ) {
				dot_founded = 1;
				continue; /* for e */
			}
			else if ( *e >= 'A' && *e <= 'Z' )
			{
				e++;
			}
			break; /* for e */
		}

		/* number founded */
		len = e - s;
		(*count)++;
		result = zbx_realloc(result, sizeof(char*) * (*count));
		result[(*count)-1] = zbx_malloc(NULL, len + 1);
		memcpy(result[(*count)-1], s, len);
		result[(*count)-1][len] = '\0';

		s = e;
	}

	return result;
}

static void	zbx_free_numbers(char ***numbers, int count)
{
	register int i = 0;

	if ( !numbers ) return;
	if ( !*numbers ) return;

	for ( i = 0; i < count; i++ )
	{
		zbx_free((*numbers)[i]);
	}

	zbx_free(*numbers);
}

/******************************************************************************
 *                                                                            *
 * Function: expand_trigger_description_constants                             *
 *                                                                            *
 * Purpose: substitute simple macros in data string with real values          *
 *                                                                            *
 * Parameters: data - trigger description                                     *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *           replcae ONLY $1-9 macros NOT {HOSTNAME}                          *
 *                                                                            *
 ******************************************************************************/
static void	expand_trigger_description_constants(
		char **data,
		zbx_uint64_t triggerid
	)
{
	DB_RESULT db_trigger;
	DB_ROW	db_trigger_data;

	char	**numbers = NULL;
	int	numbers_cnt = 0;

	int	i = 0;

	char	*new_str = NULL;

	char	replace[3] = "$0";

	db_trigger = DBselect("select expression from triggers where triggerid=" ZBX_FS_UI64, triggerid);

	if ( (db_trigger_data = DBfetch(db_trigger)) ) {

		numbers = extract_numbers(db_trigger_data[0], &numbers_cnt);

		for ( i = 0; i < 9; i++ )
		{
			replace[1] = '0' + i + 1;
			new_str = string_replace(
					*data,
					replace, 
					i < numbers_cnt ? 
						numbers[i] :
						""
					);
			zbx_free(*data);
			*data = new_str;
		}

		zbx_free_numbers(&numbers, numbers_cnt);
	}

	DBfree_result(db_trigger);
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_simple_macros                                         *
 *                                                                            *
 * Purpose: substitute simple macros in data string with real values          *
 *                                                                            *
 * Parameters: trigger - trigger structure                                    *
 *             action - action structure (NULL if unknown)                    *
 *             data - data string                                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: {DATE},{TIME},{HOSTNAME},{IPADDRESS},{STATUS},                   *
 *           {TRIGGER.NAME}, {TRIGGER.KEY}, {TRIGGER.SEVERITY}                *
 *                                                                            *
 ******************************************************************************/
/* definition of macros variables */
#define MVAR_DATE			"{DATE}"
#define MVAR_EVENT_ID			"{EVENT.ID}"
#define MVAR_HOST_NAME			"{HOSTNAME}"
#define MVAR_IPADDRESS			"{IPADDRESS}"
#define MVAR_TIME			"{TIME}"
#define MVAR_ITEM_LASTVALUE		"{ITEM.LASTVALUE}"
#define MVAR_ITEM_NAME			"{ITEM.NAME}"
#define MVAR_TRIGGER_COMMENT		"{TRIGGER.COMMENT}"
#define MVAR_TRIGGER_ID			"{TRIGGER.ID}"
#define MVAR_TRIGGER_KEY		"{TRIGGER.KEY}"
#define MVAR_TRIGGER_NAME		"{TRIGGER.NAME}"
#define MVAR_TRIGGER_SEVERITY		"{TRIGGER.SEVERITY}"
#define MVAR_TRIGGER_STATUS		"{TRIGGER.STATUS}"
#define MVAR_TRIGGER_STATUS_OLD		"{STATUS}"
#define MVAR_TRIGGER_VALUE		"{TRIGGER.VALUE}"
#define MVAR_TRIGGER_URL		"{TRIGGER.URL}"
#define MVAR_PROFILE_DEVICETYPE		"{PROFILE.DEVICETYPE}"
#define MVAR_PROFILE_NAME		"{PROFILE.NAME}"
#define MVAR_PROFILE_OS			"{PROFILE.OS}"
#define MVAR_PROFILE_SERIALNO		"{PROFILE.SERIALNO}"
#define MVAR_PROFILE_TAG		"{PROFILE.TAG}"
#define MVAR_PROFILE_MACADDRESS		"{PROFILE.MACADDRESS}"
#define MVAR_PROFILE_HARDWARE		"{PROFILE.HARDWARE}"
#define MVAR_PROFILE_SOFTWARE		"{PROFILE.SOFTWARE}"
#define MVAR_PROFILE_CONTACT		"{PROFILE.CONTACT}"
#define MVAR_PROFILE_LOCATION		"{PROFILE.LOCATION}"
#define MVAR_PROFILE_NOTES		"{PROFILE.NOTES}"

#define STR_UNKNOWN_VARIABLE		"*UNKNOWN*"

void	substitute_simple_macros(DB_EVENT *event, DB_ACTION *action, char **data, int macro_type)
{

	char
		*pl = NULL,
		*pr = NULL,
		*str_out = NULL,
		*replace_to = NULL;

	int	var_len;

	time_t  now;
	struct  tm      *tm;

	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In substitute_simple_macros()");

	if(!data || !*data) return;
	
	zabbix_log(LOG_LEVEL_DEBUG, "In substitute_simple_macros (data:%s)",
		*data);

	if('\0' == *data[0]) return;

	if ( macro_type & MACRO_TYPE_TRIGGER_DESCRIPTION ) {
		expand_trigger_description_constants(data, event->objectid);
	}

	pl = *data;
	while((pr = strchr(pl, '{')))
	{
		pr[0] = '\0';
zabbix_log(LOG_LEVEL_DEBUG, "str_out1 [%s] pl [%s]", str_out, pl);
		str_out = zbx_strdcat(str_out, pl);
zabbix_log(LOG_LEVEL_DEBUG, "str_out1 [%s] pl [%s]", str_out, pl);
		pr[0] = '{';

		replace_to = zbx_dsprintf(replace_to, "{");
		var_len = 1;

		if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_NAME, strlen(MVAR_TRIGGER_NAME)) == 0)
		{
			var_len = strlen(MVAR_TRIGGER_NAME);

			zabbix_log(LOG_LEVEL_DEBUG, "Before replace_to [%s]", replace_to);

			replace_to = zbx_dsprintf(replace_to, "%s", event->trigger_description);
			/* Why it was here? *//* For substituting macros in trigger description :) */
			substitute_simple_macros(event, action, &replace_to, MACRO_TYPE_TRIGGER_DESCRIPTION);

			zabbix_log(LOG_LEVEL_DEBUG, "After replace_to [%s]", replace_to);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_COMMENT, strlen(MVAR_TRIGGER_COMMENT)) == 0)
		{
			var_len = strlen(MVAR_TRIGGER_COMMENT);

			replace_to = zbx_dsprintf(replace_to, "%s", event->trigger_comments);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_DEVICETYPE, strlen(MVAR_PROFILE_DEVICETYPE)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_DEVICETYPE);

			result = DBselect("select distinct p.devicetype from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.DEVECETYPE in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_NAME, strlen(MVAR_PROFILE_NAME)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_NAME);

			result = DBselect("select distinct p.name from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.NAME in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_OS, strlen(MVAR_PROFILE_OS)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_OS);

			result = DBselect("select distinct p.os from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.OS in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_SERIALNO, strlen(MVAR_PROFILE_SERIALNO)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_SERIALNO);

			result = DBselect("select distinct p.serialno from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.SERIALNO in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_TAG, strlen(MVAR_PROFILE_TAG)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_TAG);

			result = DBselect("select distinct p.tag from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.TAG in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_MACADDRESS, strlen(MVAR_PROFILE_MACADDRESS)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_MACADDRESS);

			result = DBselect("select distinct p.macaddress from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.MACADDRESS in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_HARDWARE, strlen(MVAR_PROFILE_HARDWARE)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_HARDWARE);

			result = DBselect("select distinct p.hardware from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.HARDWARE in substitute_simple_macros. Triggerid [%d]", 
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_SOFTWARE, strlen(MVAR_PROFILE_SOFTWARE)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_SOFTWARE);

			result = DBselect("select distinct p.software from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.SOFTWARE in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_CONTACT, strlen(MVAR_PROFILE_CONTACT)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_CONTACT);

			result = DBselect("select distinct p.contact from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.CONTACT in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_LOCATION, strlen(MVAR_PROFILE_LOCATION)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_LOCATION);

			result = DBselect("select distinct p.location from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.LOCATION in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_PROFILE_NOTES, strlen(MVAR_PROFILE_NOTES)) == 0)
		{
			var_len = strlen(MVAR_PROFILE_NOTES);

			result = DBselect("select distinct p.notes from triggers t, functions f,items i, hosts h, hosts_profiles p"
				" where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and p.hostid=h.hostid", 
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No PROFILE.NOTES in substitute_simple_macros. Triggerid [%d]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY | MACRO_TYPE_TRIGGER_DESCRIPTION) &&
			strncmp(pr, MVAR_HOST_NAME, strlen(MVAR_HOST_NAME)) == 0)
		{
			var_len = strlen(MVAR_HOST_NAME);

			result = DBselect("select distinct h.host from triggers t, functions f,items i, hosts h "
				"where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid",
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No hostname in substitute_simple_macros. Triggerid [" ZBX_FS_UI64 "]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_ITEM_NAME, strlen(MVAR_ITEM_NAME)) == 0)
		{
			var_len = strlen(MVAR_ITEM_NAME);

			result = DBselect("select distinct i.description from triggers t, functions f,items i, hosts h"
				" where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid"
				" order by i.description",
				event->objectid);

			row=DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No ITEM.NAME in substitute_simple_macros. Triggerid [" ZBX_FS_UI64 "]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}

			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY | MACRO_TYPE_TRIGGER_DESCRIPTION) &&
			strncmp(pr, MVAR_ITEM_LASTVALUE, strlen(MVAR_ITEM_LASTVALUE)) == 0)
		{
			var_len = strlen(MVAR_ITEM_LASTVALUE);

			result = DBselect("select distinct i.lastvalue from triggers t, functions f,items i, hosts h"
				" where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid",
				event->objectid);

			row=DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No ITEM.LASTVALUE in substitute_simple_macros. Triggerid [" ZBX_FS_UI64 "]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}

			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_KEY, strlen(MVAR_TRIGGER_KEY)) == 0)
		{
			var_len = strlen(MVAR_TRIGGER_KEY);

			result = DBselect("select distinct i.key_ from triggers t, functions f,items i, hosts h"
				" where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid"
				" order by i.key_",
				event->objectid);

			row=DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No TRIGGER.KEY in substitute_simple_macros. Triggerid [" ZBX_FS_UI64 "]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}

			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_IPADDRESS, strlen(MVAR_IPADDRESS)) == 0)
		{
			var_len = strlen(MVAR_IPADDRESS);

			result = DBselect("select distinct h.ip from triggers t, functions f,items i, hosts h"
				" where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid",
				event->objectid);

			row = DBfetch(result);

			if(!row || DBis_null(row[0])==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "No hostname in substitute_simple_macros. Triggerid [" ZBX_FS_UI64 "]",
					event->objectid);

				replace_to = zbx_dsprintf(replace_to, "%s",
					STR_UNKNOWN_VARIABLE);
			}
			else
			{
				replace_to = zbx_dsprintf(replace_to, "%s",
					row[0]);
			}
			DBfree_result(result);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_DATE, strlen(MVAR_DATE)) == 0)
		{
			var_len = strlen(MVAR_TIME);

			now	= time(NULL);
			tm	= localtime(&now);
			replace_to = zbx_dsprintf(replace_to, "%.4d.%.2d.%.2d",
				tm->tm_year+1900,
				tm->tm_mon+1,
				tm->tm_mday);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY)&&
			strncmp(pr, MVAR_TIME, strlen(MVAR_TIME)) == 0)
		{
			var_len = strlen(MVAR_TIME);

			now	= time(NULL);
			tm	= localtime(&now);
			replace_to = zbx_dsprintf(replace_to, "%.2d:%.2d:%.2d",
				tm->tm_hour,
				tm->tm_min,
				tm->tm_sec);

		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_STATUS, strlen(MVAR_TRIGGER_STATUS)) == 0)
		{
			/* NOTE: if you make changes for this bloc, don't forgot MVAR_TRIGGER_STATUS_OLD block */
			var_len = strlen(MVAR_TRIGGER_STATUS);

			replace_to = zbx_dsprintf(replace_to, "%s",
					event->value == TRIGGER_VALUE_TRUE ? "ON" : "OFF");
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) && 
			strncmp(pr, MVAR_TRIGGER_STATUS_OLD, strlen(MVAR_TRIGGER_STATUS_OLD)) == 0)
		{
			/* NOTE: if you make changes for this bloc, don't forgot MVAR_TRIGGER_STATUS block */
			var_len = strlen(MVAR_TRIGGER_STATUS_OLD);

			replace_to = zbx_dsprintf(replace_to, "%s",
					event->value == TRIGGER_VALUE_TRUE ? "ON" : "OFF");
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_ID, strlen(MVAR_TRIGGER_ID)) == 0)
		{
			/* NOTE: if you make changes for this bloc, don't forgot MVAR_TRIGGER_STATUS block */
			var_len = strlen(MVAR_TRIGGER_ID);

			replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64,
				event->objectid);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY | MACRO_TYPE_TRIGGER_EXPRESSION) &&
			strncmp(pr, MVAR_TRIGGER_VALUE, strlen(MVAR_TRIGGER_VALUE)) == 0)
		{
			var_len = strlen(MVAR_TRIGGER_VALUE);

			replace_to = zbx_dsprintf(replace_to, "%d",
				event->value);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_TRIGGER_URL, strlen(MVAR_TRIGGER_URL)) == 0)
		{
			/* NOTE: if you make changes for this bloc, don't forgot MVAR_TRIGGER_STATUS block */
			var_len = strlen(MVAR_TRIGGER_URL);

			replace_to = zbx_dsprintf(replace_to, "%s",
				event->trigger_url);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) &&
			strncmp(pr, MVAR_EVENT_ID, strlen(MVAR_EVENT_ID)) == 0)
		{
			/* NOTE: if you make changes for this bloc, don't forgot MVAR_TRIGGER_STATUS block */
			var_len = strlen(MVAR_EVENT_ID);

			replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64,
				event->eventid);
		}
		else if(macro_type & (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) && 
			strncmp(pr, MVAR_TRIGGER_SEVERITY, strlen(MVAR_TRIGGER_SEVERITY)) == 0)
		{
			var_len = strlen(MVAR_TRIGGER_SEVERITY);

			if(event->trigger_priority == 0)	replace_to = zbx_dsprintf(replace_to, "Not classified");
			else if(event->trigger_priority == 1)	replace_to = zbx_dsprintf(replace_to, "Information");
			else if(event->trigger_priority == 2)	replace_to = zbx_dsprintf(replace_to, "Warning");
			else if(event->trigger_priority == 3)	replace_to = zbx_dsprintf(replace_to, "Average");
			else if(event->trigger_priority == 4)	replace_to = zbx_dsprintf(replace_to, "High");
			else if(event->trigger_priority == 5)	replace_to = zbx_dsprintf(replace_to, "Disaster");
			else					replace_to = zbx_dsprintf(replace_to, "Unknown");
		}

zabbix_log(LOG_LEVEL_DEBUG, "str_out2 [%s] replace_to [%s]", str_out, replace_to);
		str_out = zbx_strdcat(str_out, replace_to);
zabbix_log(LOG_LEVEL_DEBUG, "str_out2 [%s] replace_to [%s]", str_out, replace_to);
		pl = pr + var_len;

		zbx_free(replace_to);
	}
zabbix_log(LOG_LEVEL_DEBUG, "str_out3 [%s] pl [%s]", str_out, pl);
	str_out = zbx_strdcat(str_out, pl);
zabbix_log(LOG_LEVEL_DEBUG, "str_out3 [%s] pl [%s]", str_out, pl);

	zbx_free(*data);

	*data = str_out;

	zabbix_log(LOG_LEVEL_DEBUG, "End substitute_simple_macros ()");
	zabbix_log(LOG_LEVEL_DEBUG, "End substitute_simple_macros (result:%s)",
		*data);
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_macros                                                *
 *                                                                            *
 * Purpose: substitute macros in data string with real values                 *
 *                                                                            *
 * Parameters: trigger - trigger structure                                    *
 *             action - action structure                                      *
 *             data - data string                                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: example: "{127.0.0.1:system[procload].last(0)}" to "1.34"        *
 *                                                                            *
 ******************************************************************************/
void	substitute_macros(DB_EVENT *event, DB_ACTION *action, char **data)
{
	char	
		*str_out = NULL,
		*replace_to = NULL,
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

	if(!data || !*data) return;

	zabbix_log(LOG_LEVEL_DEBUG, "In substitute_macros(data:%s)",
		*data);

	if('\0' == *data[0]) return;
	
	zabbix_log(LOG_LEVEL_DEBUG, "Before substitute_simple_macros(%s)", *data);
	substitute_simple_macros(event, action, data, MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY);
	zabbix_log(LOG_LEVEL_DEBUG, "After substitute_simple_macros(%s)", *data);

	pl = *data;
	while((pr = strchr(pl, '{')))
	{
		if((pme = strchr(pr, '}')) == NULL)
			break;

		pme[0] = '\0';
	
		pr = strrchr(pr, '{'); /* find '{' near '}' */	

		/* copy left side */
		pr[0] = '\0';
		str_out = zbx_strdcat(str_out, pl);
		pr[0] = '{';


		/* copy original name of variable */
		replace_to = zbx_dsprintf(replace_to, "%s}", pr);	/* in format used '}' */
									/* cose in 'pr' string symbol '}' is changed to '\0' by 'pme'*/
		pl = pr + strlen(replace_to);

		pms = pr + 1;
	
		if(NULL != (p = strchr(pms, ':')))
		{
			*p = '\0';
			zbx_snprintf(host, sizeof(host), "%s", pms);
			*p = ':';
			pms = p + 1;
			if(NULL != (p = strrchr(pms, '.')))
			{
				*p = '\0';
				zbx_snprintf(key, sizeof(key), "%s", pms);
				*p = '.';
				pms = p + 1;
				if(NULL != (p = strchr(pms, '(')))
				{
					*p = '\0';
					zbx_snprintf(function, sizeof(function), "%s", pms);
					*p = '(';
					pms = p + 1;
					if(NULL != (p = strchr(pms, ')')))
					{
						*p = '\0';
						zbx_snprintf(parameter, sizeof(parameter), "%s", pms);
						*p = ')';
						pms = p + 1;
						
						/* function 'evaluate_function2' require 'replace_to' with size 'MAX_STRING_LEN' */
						zbx_free(replace_to);
						replace_to = zbx_malloc(replace_to, MAX_STRING_LEN);

						if(evaluate_function2(replace_to,host,key,function,parameter) != SUCCEED)
							zbx_snprintf(replace_to, MAX_STRING_LEN, "%s", STR_UNKNOWN_VARIABLE);
					}
				}
			}
			
		}
		pme[0] = '}';

		str_out = zbx_strdcat(str_out, replace_to);
		zbx_free(replace_to);
	}
	str_out = zbx_strdcat(str_out, pl);

	zbx_free(*data);

	*data = str_out;

	zabbix_log( LOG_LEVEL_DEBUG, "End substitute_macros(result:%s)",
		*data );
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
int	substitute_functions(char **exp, char *error, int maxerrlen)
{
	char	*value;
	char	functionid[MAX_STRING_LEN];
	int	i,j;
	int	len;
	char	*out = NULL;
	char	c;

	zabbix_log(LOG_LEVEL_DEBUG, "In substitute_functions(%s)",
		*exp);

	i = 0;
	len = strlen(*exp);
	while(i<len)
	{
		if((*exp)[i] == '{')
		{
			for(j=i+1;((*exp)[j]!='}')&&((*exp)[j]!='\0');j++)
			{
				functionid[j-i-1]=(*exp)[j];
			}
			functionid[j-i-1]='\0';
			if( DBget_function_result( &value, functionid ) != SUCCEED )
			{
/* It may happen because of functions.lastvalue is NULL, so this is not warning  */
				zbx_snprintf(error,maxerrlen, "Unable to get value for functionid [%s]",
					functionid);
				zabbix_log( LOG_LEVEL_DEBUG, "%s",
					error);
				zabbix_syslog("%s",
					error);
				return	FAIL;
			}
			out =  zbx_strdcat(out,value);
			zbx_free(value);
			i=j+1;
		}
		else
		{
			c=(*exp)[i+1]; (*exp)[i+1]='\0';
			out = zbx_strdcat(out, (*exp+i));
			(*exp)[i+1]=c;
			i++;
		}	
	}
	zbx_free(*exp);

	*exp = out;
	zabbix_log( LOG_LEVEL_DEBUG, "End substitute_functions [%s]",
		*exp);

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
 * Comments: example: ({123}>1)|({MACROS}=1)&({75}>3)                         *
 *                                                                            *
 ******************************************************************************/
int	evaluate_expression(int *result,char **expression, int trigger_value, char *error, int maxerrlen)
{
	/* Required for substitution of macros */
	DB_EVENT	event;
	DB_ACTION	action;

	zabbix_log(LOG_LEVEL_DEBUG, "In evaluate_expression(%s)",
		*expression);

	/* Substitute macros first */
	memset(&event,0,sizeof(DB_EVENT));	
	memset(&action,0,sizeof(DB_ACTION));	
	event.value = trigger_value;

	substitute_simple_macros(&event, &action, expression, MACRO_TYPE_TRIGGER_EXPRESSION);

	/* Evaluate expression */
	delete_spaces(*expression);
	if( substitute_functions(expression, error, maxerrlen) == SUCCEED)
	{
		if( evaluate(result, *expression, error, maxerrlen) == SUCCEED)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "End evaluate_expression(result:%d)",
				*result);
			return SUCCEED;
		}
	}
	zabbix_log(LOG_LEVEL_DEBUG, "Evaluation of expression [%s] failed [%s]",
		*expression,
		error);
	zabbix_syslog("Evaluation of expression [%s] failed [%s]",
		*expression,
		error);

	zabbix_log(LOG_LEVEL_DEBUG, "End evaluate_expression(result:FAIL)");
	return FAIL;
}
