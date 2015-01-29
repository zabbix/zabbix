/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#include <sys/time.h>
#include <time.h>

/******************************************************************************
 *                                                                            *
 * Function: time_diff                                                        *
 *                                                                            *
 * Purpose: calculate time difference in seconds                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
double time_diff(struct timeval *from, struct timeval *to)
{
	double msec;
	double diff;

	/* from<=to */
	if( (from->tv_sec < to->tv_sec) || (from->tv_sec == to->tv_sec && from->tv_usec <= to->tv_usec))
	{
		msec = (double)(to->tv_usec-from->tv_usec)/1000000;

		if(msec >= 0)
		{
			diff = to->tv_sec - from->tv_sec + msec;
		}
		else
		{
			diff = to->tv_sec - from->tv_sec - (msec + 1);
		}
	}
	/* from>to */
	else
	{
		msec = (double)(from->tv_usec-to->tv_usec)/1000000;

		if(msec >= 0)
		{
			diff = from->tv_sec - to->tv_sec + msec;
		}
		else
		{
			diff = from->tv_sec - to->tv_sec - (msec + 1);
		}
		diff = 0.0 - diff;
	}

	return diff;
}
