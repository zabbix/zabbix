#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>

#include "functions.h"
#include "common.h"
#include "db.h"
#include "log.h"

/*
 * Return 0 if arguments are equal (differs less than 0.000001), 1 - otherwise
 */
int	cmp_double(double a,double b)
{
	if(fabs(a-b)<0.000001)
	{
		return	0;
	}
	return	1;
}

/*
 * Return SUCCEED if parameter has format X.X or X, where X is [0..9]{1,n}
 * In other words, parameter is float number :)
 */
int	is_float(char *c)
{
	int i;
	int dot=-1;

	zabbix_log(LOG_LEVEL_DEBUG, "Starting is_float:%s", c );
	for(i=0;i<strlen(c);i++)
	{
		if((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}

		if((c[i]=='.')&&(dot==-1))
		{
			dot=i;

			if((dot!=0)&&(dot!=strlen(c)-1))
			{
				continue;
			}
		}

		zabbix_log(LOG_LEVEL_DEBUG, "It is NOT float" );
		return FAIL;
	}
	zabbix_log(LOG_LEVEL_DEBUG, "It is float" );
	return SUCCEED;
}

/*
 * Delete all spaces from given string
 */ 
void	delete_spaces(char *c)
{
	int i,j;

	zabbix_log( LOG_LEVEL_DEBUG, "Before deleting spaces:%s", c );

	j=0;
	for(i=0;i<strlen(c);i++)
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

/*
 * Locate character in given string. FAIL - not found, otherwise character position is returned
 */
int	find_char(char *str,char c)
{
	int i;

	zabbix_log( LOG_LEVEL_DEBUG, "Before find_char:%s[%c]", str, c );

	for(i=0;i<strlen(str);i++)
	{
		if(str[i]==c) return i;
	}
	return	FAIL;
}

/*
 * Evaluate simple expression
 * Simple expression is either <float> or <float> <operator> <float>
 */ 
int	evaluate_simple (float *result,char *exp)
{
	float	value1,value2;
	char	first[MAX_STRING_LEN+1],second[MAX_STRING_LEN+1];
	int	i,j,l;

	zabbix_log( LOG_LEVEL_DEBUG, "Evaluating simple expression [%s]", exp );

	if( is_float(exp) == SUCCEED )
	{
		*result=atof(exp);
		return SUCCEED;
	}

	if( find_char(exp,'|') != FAIL )
	{
		zabbix_log( LOG_LEVEL_DEBUG, "| is found" );
		l=find_char(exp,'|');
		strncpy( first, exp, MAX_STRING_LEN );
		first[l]=0;
		j=0;
		for(i=l+1;i<strlen(exp);i++)
		{
			second[j]=exp[i];
			j++;
		}
		second[j]=0;
		if( evaluate_simple(&value1,first) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot evaluate expression [%s]", first );
			return FAIL;
		}
		if( value1 == 1)
		{
			*result=value1;
			return SUCCEED;
		}
		if( evaluate_simple(&value2,second) == FAIL )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot evaluate expression [%s]", second );
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
	else if( find_char(exp,'&') != FAIL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "& is found" );
		l=find_char(exp,'&');
		strncpy( first, exp, MAX_STRING_LEN );
		first[l]=0;
		j=0;
		for(i=l+1;i<strlen(exp);i++)
		{
			second[j]=exp[i];
			j++;
		}
		second[j]=0;
		zabbix_log(LOG_LEVEL_DEBUG, "[%s] [%s]",first,second );
		if( evaluate_simple(&value1,first) == FAIL )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot evaluate expression [%s]", first );
			return FAIL;
		}
		if( evaluate_simple(&value2,second) == FAIL )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot evaluate expression [%s]", second );
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
	else if( find_char(exp,'>') != FAIL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "> is found" );
		l=find_char(exp,'>');
		strncpy(first, exp, MAX_STRING_LEN);
		first[l]=0;
		j=0;
		for(i=l+1;i<strlen(exp);i++)
		{
			second[j]=exp[i];
			j++;
		}
		second[j]=0;
		if( evaluate_simple(&value1,first) == FAIL )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot evaluate expression [%s]", first );
			return FAIL;
		}
		if( evaluate_simple(&value2,second) == FAIL )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot evaluate expression [%s]", second );
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
	else if( find_char(exp,'<') != FAIL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "< is found" );
		l=find_char(exp,'<');
		strncpy(first, exp, MAX_STRING_LEN);
		first[l]=0;
		j=0;
		for(i=l+1;i<strlen(exp);i++)
		{
			second[j]=exp[i];
			j++;
		}
		second[j]=0;
		zabbix_log(LOG_LEVEL_DEBUG, "[%s] [%s]",first,second );
		if( evaluate_simple(&value1,first) == FAIL )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot evaluate expression [%s]", first );
			return FAIL;
		}
		if( evaluate_simple(&value2,second) == FAIL )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot evaluate expression [%s]", second );
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
	else if( find_char(exp,'=') != FAIL )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "= is found" );
		l=find_char(exp,'=');
		strncpy(first, exp, MAX_STRING_LEN);
		first[l]=0;
		j=0;
		for(i=l+1;i<strlen(exp);i++)
		{
			second[j]=exp[i];
			j++;
		}
		second[j]=0;
		if( evaluate_simple(&value1,first) == FAIL )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot evaluate expression [%s]", first );
			return FAIL;
		}
		if( evaluate_simple(&value2,second) == FAIL )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot evaluate expression [%s]", second );
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
	else
	{
			zabbix_log( LOG_LEVEL_WARNING, "Format error or unsupported operator.  Exp: [%s]", exp );
			return FAIL;
	}
	return SUCCEED;
}

/*
 * Evaluate expression. Example of input expression: ({15}>10)|({123}=1) 
 */ 
int	evaluate(int *result,char *exp)
{
	float	value;
	char	res[MAX_STRING_LEN+1];
	char	simple[MAX_STRING_LEN+1];
	int	i,l,r;

	strncpy( res,exp,MAX_STRING_LEN );

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
			zabbix_log(LOG_LEVEL_WARNING, "Cannot find left bracket [(]. Expression:%s", exp );
			return	FAIL;
		}
		for(i=l+1;i<r;i++)
		{
			simple[i-l-1]=exp[i];
		} 
		simple[r-l-1]=0;

		if( evaluate_simple( &value, simple ) != SUCCEED )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unable to evaluate simple expression [%s]", simple );
			return	FAIL;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "Expression1:%s", exp );

		exp[l]='%';
		exp[l+1]='f';
		exp[l+2]=' ';

		zabbix_log(LOG_LEVEL_DEBUG, "Expression2:%s", exp );

		for(i=l+3;i<=r;i++) exp[i]=' ';

		zabbix_log(LOG_LEVEL_DEBUG, "Expression3:%s", exp );

		sprintf(res,exp,value);
		strncpy(exp,res, MAX_STRING_LEN);
		delete_spaces(res);
		zabbix_log(LOG_LEVEL_DEBUG, "Expression4:%s", res );
	}
	if( evaluate_simple( &value, res ) != SUCCEED )
	{
		zabbix_log(LOG_LEVEL_WARNING, "Unable to evaluate simple expression [%s]", simple );
		return	FAIL;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Evaluate end:[%f]", value );
	*result=value;
	return SUCCEED;
}

/*
 * Translate "{127.0.0.1:system[procload].last(0)}" to "1.34" 
 */
int	substitute_macros(char *exp)
{
	char	res[MAX_STRING_LEN+1];
	char	macro[MAX_STRING_LEN+1];
	char	host[MAX_STRING_LEN+1];
	char	key[MAX_STRING_LEN+1];
	char	function[MAX_STRING_LEN+1];
	char	parameter[MAX_STRING_LEN+1];
	char	value[MAX_STRING_LEN+1];
	int	i,j;
	int	r,l;
	int	r1,l1;

	zabbix_log(LOG_LEVEL_DEBUG, "BEGIN substitute_macros" );

	zabbix_log( LOG_LEVEL_DEBUG, "Expression1:%s", exp );

	while( find_char(exp,'{') != FAIL )
	{
		l=find_char(exp,'{');
		r=find_char(exp,'}');

		if( r == FAIL )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Cannot find right bracket. Expression:%s", exp );
			return	FAIL;
		}

		if( r < l )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Right bracket is before left one. Expression:%s", exp );
			return	FAIL;
		}

		for(i=l+1;i<r;i++)
		{
			macro[i-l-1]=exp[i];
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
		l1=find_char(macro+r1,'.');

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

		zabbix_log( LOG_LEVEL_DEBUG, "Before get_lastvalue()" );
		i=get_lastvalue(value,host,key,function,parameter);
		zabbix_log( LOG_LEVEL_DEBUG, "After get_lastvalue(%d)", i );

		exp[l]='%';
		exp[l+1]='s';
		exp[l+2]=' ';

		zabbix_log( LOG_LEVEL_DEBUG, "Expression2:%s", exp );

		for(i=l+3;i<=r;i++) exp[i]=' ';

		j=0;
		for(i=0;i<strlen(exp);i++)
		{
			if( (i>=l+3) && (i<=r) )
				continue;
			exp[j]=exp[i];
			if(i==l)
				exp[j]='%';
			if(i==l+1)
				exp[j]='f';
			j++;
		}
		exp[j]=0;

		zabbix_log( LOG_LEVEL_DEBUG, "Expression3:%s", exp );

		sprintf(res,exp,value);
		strncpy(exp,res, MAX_STRING_LEN);
/*		delete_spaces(exp); */
		zabbix_log( LOG_LEVEL_DEBUG, "Expression4:%s", exp );
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Result expression:%s", exp );

	return SUCCEED;
}

/*
 * Translate "({15}>10)|({123}=0)" to "(6.456>10)|(0=0)" 
 */
int	substitute_functions(char *exp)
{
	float	value;
	char	functionid[MAX_STRING_LEN+1];
	char	res[MAX_STRING_LEN+1];
	int	i,l,r;

	zabbix_log(LOG_LEVEL_DEBUG, "BEGIN substitute_functions" );

	while( find_char(exp,'{') != FAIL )
	{
		l=find_char(exp,'{');
		r=find_char(exp,'}');
		if( r == FAIL )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Cannot find right bracket. Expression:%s", exp );
			return	FAIL;
		}
		if( r < l )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Right bracket is before left one. Expression:%s", exp );
			return	FAIL;
		}

		for(i=l+1;i<r;i++)
		{
			functionid[i-l-1]=exp[i];
		} 
		functionid[r-l-1]=0;

		if( DBget_function_result( &value, functionid ) != SUCCEED )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unable to get value by functionid [%s]", functionid );
			return	FAIL;
		}


		zabbix_log( LOG_LEVEL_DEBUG, "Expression1:%s", exp );

		exp[l]='%';
		exp[l+1]='f';
		exp[l+2]=' ';

		zabbix_log( LOG_LEVEL_DEBUG, "Expression2:%s", exp );

		for(i=l+3;i<=r;i++) exp[i]=' ';

		zabbix_log( LOG_LEVEL_DEBUG, "Expression3:%s", exp );

		sprintf(res,exp,value);
		strncpy(exp,res, MAX_STRING_LEN);
		delete_spaces(exp);
		zabbix_log( LOG_LEVEL_DEBUG, "Expression4:%s", exp );
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Expression:%s", exp );
	zabbix_log( LOG_LEVEL_DEBUG, "END substitute_functions" );
	return SUCCEED;
}

/*
 * Evaluate complex expression. Example: ({127.0.0.1:system[procload].last(0)}>1)|({127.0.0.1:system[procload].max(300)}>3)
 */ 
int	evaluate_expression(int *result,char *expression)
{
	delete_spaces(expression);
	if( substitute_functions(expression) == SUCCEED)
	{
		if( evaluate(result, expression) == SUCCEED)
		{
			return SUCCEED;
		}
	}
	return FAIL;
}
