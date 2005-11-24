#include "common.h"

#include <math.h>

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
int	is_double(char *c)
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

		return FAIL;
	}
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
	int i;

	for(i=0;c[i]!=0;i++)
	{
		if((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}
		return FAIL;
	}

	return SUCCEED;
}

void	set_result_type(AGENT_RESULT *result, char *c)
{
	if(is_uint(c) == SUCCEED)
	{
		result->type |= AR_UINT64;
		result->ui64 = (zbx_uint64_t)atoll(c);
	}
	else if(is_double(c) == SUCCEED)
	{
		result->type |= AR_DOUBLE;
		result->dbl = (double)atof(c);
	}
	else
	{
		result->type |= AR_STRING;
		result->str = strdup(c);
	}
}
