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
