#include "common.h"

#include <stdlib.h>
#include <math.h>

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
int	calculate_item_nextcheck(int itemid, int item_type, int delay, int now)
{
	int i;

/*	Old algorithm */
/*	i=delay*(int)(now/delay);*/

	/* Special processing of active items to see better view in queue */
	if(item_type == ITEM_TYPE_ZABBIX_ACTIVE)
	{
		i = now+delay;
	}
	else
	{
		i=delay*(int)(now/delay)+(itemid % delay);
		while(i<=now)	i+=delay;
	}

	return i;
}

/******************************************************************************
 *                                                                            *
 * Function: cmp_double                                                       *
 *                                                                            *
 * Purpose: compares two float values                                         *
 *                                                                            *
 * Parameters: a,b - floats to compare                                        *
 *                                                                            *
 * Return value:  0 - the values are equal                                    *
 *                1 - otherwise                                               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: equal == differs less than 0.000001                              *
 *                                                                            *
 ******************************************************************************/
int	cmp_double(double a,double b)
{
	if(fabs(a-b)<0.000001)
	{
		return	0;
	}
	return	1;
}

/******************************************************************************
 *                                                                            *
 * Function: is_double_prefix                                                 *
 *                                                                            *
 * Purpose: check if the string is float                                      *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is float                               *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: the functions support prefixes K,M,G                             *
 *                                                                            *
 ******************************************************************************/
int	is_double_prefix(char *c)
{
	int i;
	int dot=-1;

	for(i=0;c[i]!=0;i++)
	{
		if((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}

		if((c[i]=='.')&&(dot==-1))
		{
			dot=i;

			if((dot!=0)&&(dot!=(int)strlen(c)-1))
			{
				continue;
			}
		}
		/* Last digit is prefix 'K', 'M', 'G' */
		if( ((c[i]=='K')||(c[i]=='M')||(c[i]=='G')) && (i == (int)strlen(c)-1))
		{
			continue;
		}

		return FAIL;
	}
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_double                                                        *
 *                                                                            *
 * Purpose: check if the string is float                                      *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is float                               *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
/*int is_double(char *str)
{
	const char *endstr = str + strlen(str);
	char *endptr = NULL;
	double x;
       
	x = strtod(str, &endptr);

	if(endptr == str || errno != 0)
		return FAIL;
	if (endptr == endstr)
		return SUCCEED;
	return FAIL;
}*/

int	is_double(char *c)
{
	int i;
	int dot=-1;
	int len;

	for(i=0; c[i]==' ' && c[i]!=0;i++); /* trim left spaces */

	for(len=0; c[i]!=0; i++, len++)
	{
		if((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}

		if((c[i]=='.')&&(dot==-1))
		{
			dot=i;
			continue;
		}

		if(c[i]==' ') /* check right spaces */
		{
			for( ; c[i]==' ' && c[i]!=0;i++); /* trim right spaces */
			
			if(c[i]==0) break; /* SUCCEED */
		}

		return FAIL;
	}
	
	if(len <= 0) return FAIL;

	if(len == 1 && dot!=-1) return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_uint                                                          *
 *                                                                            *
 * Purpose: check if the string is unsigned integer                           *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	is_uint(char *c)
{
	int	i;
	int	len;

	for(i=0; c[i]==' ' && c[i]!=0;i++); /* trim left spaces */
	
	for(len=0; c[i]!=0; i++,len++)
	{
		if((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}
		
		if(c[i]==' ') /* check right spaces */
		{
			for( ; c[i]==' ' && c[i]!=0;i++); /* trim right spaces */
			
			if(c[i]==0) break; /* SUCCEED */
		}
		return FAIL;
	}

	if(len <= 0) return FAIL;
	
	return SUCCEED;
}

int	set_result_type(AGENT_RESULT *result, int value_type, char *c)
{
	int ret = FAIL;

	if(value_type == ITEM_VALUE_TYPE_UINT64)
	{
		del_zeroes(c);
		if(is_uint(c) == SUCCEED)
		{
#ifdef HAVE_ATOLL
			SET_UI64_RESULT(result, atoll(c));
#else
			SET_UI64_RESULT(result, atol(c));
#endif
			ret = SUCCEED;
		}
	}
	else if(value_type == ITEM_VALUE_TYPE_FLOAT)
	{
		if(is_double(c) == SUCCEED)
		{
			SET_DBL_RESULT(result, atof(c));
			ret = SUCCEED;
		}
		else if(is_uint(c) == SUCCEED)
		{
#ifdef HAVE_ATOLL
			SET_DBL_RESULT(result, (double)atoll(c));
#else
			SET_DBL_RESULT(result, (double)atol(c));
#endif
			ret = SUCCEED;
		}
	}
	else if(value_type == ITEM_VALUE_TYPE_STR)
	{
		SET_STR_RESULT(result, strdup(c));
		ret = SUCCEED;
	}
	else if(value_type == ITEM_VALUE_TYPE_TEXT)
	{
		SET_TEXT_RESULT(result, strdup(c));
		ret = SUCCEED;
	}
	else if(value_type == ITEM_VALUE_TYPE_LOG)
	{
		SET_STR_RESULT(result, strdup(c));
		ret = SUCCEED;
	}

	return ret;
}
