/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

static void	tm_sub(struct tm *tm, int multiplier, zbx_time_unit_t base);

zbx_time_unit_t	zbx_tm_str_to_unit(const char *text)
{
	switch (*text)
	{
		case 'h':
			return ZBX_TIME_UNIT_HOUR;
		case 'd':
			return ZBX_TIME_UNIT_DAY;
		case 'w':
			return ZBX_TIME_UNIT_WEEK;
		case 'M':
			return ZBX_TIME_UNIT_MONTH;
		case 'y':
			return ZBX_TIME_UNIT_YEAR;
		default:
			return ZBX_TIME_UNIT_UNKNOWN;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_parse_period                                              *
 *                                                                            *
 * Purpose: parse time period in format <multiplier><time unit>               *
 *                                                                            *
 * Parameters: period     - [IN] the time period                              *
 *             len        - [OUT] the length of parsed time period            *
 *             multiplier - [OUT] the parsed multiplier                       *
 *             base       - [OUT] the parsed time unit                        *
 *             error      - [OUT] the error message if parsing failed         *
 *                                                                            *
 * Return value: SUCCEED - period was parsed successfully                     *
 *               FAIL    - invalid time period was specified                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_tm_parse_period(const char *period, size_t *len, int *multiplier, zbx_time_unit_t *base, char **error)
{
	const char	*ptr;

	for (ptr = period; 0 != isdigit(*ptr); ptr++)
		;

	if (FAIL == is_uint_n_range(period, ptr - period, multiplier, sizeof(*multiplier), 1, UINT32_MAX))
	{
		*error = zbx_strdup(*error, "invalid period multiplier");
		return FAIL;
	}

	if (ZBX_TIME_UNIT_UNKNOWN == (*base = zbx_tm_str_to_unit(ptr)))
	{
		*error = zbx_strdup(*error, "invalid period time unit");
		return FAIL;
	}

	*len = ptr - period + 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_add                                                           *
 *                                                                            *
 * Purpose: add time duration without adjusting DST clocks                    *
 *                                                                            *
 * Parameter: tm         - [IN/OUT] the time structure                        *
 *            multiplier - [IN] the unit multiplier                           *
 *            base       - [IN] the time unit to add                          *
 *                                                                            *
 ******************************************************************************/
static void	tm_add(struct tm *tm, int multiplier, zbx_time_unit_t base)
{
	int		shift;

	switch (base)
	{
		case ZBX_TIME_UNIT_HOUR:
			tm->tm_hour += multiplier;
			if (24 <= tm->tm_hour)
			{
				shift = tm->tm_hour / 24;
				tm->tm_hour %= 24;
				tm_add(tm, shift, ZBX_TIME_UNIT_DAY);
			}
			break;
		case ZBX_TIME_UNIT_DAY:
			tm->tm_mday += multiplier;
			while (tm->tm_mday > (shift = zbx_day_in_month(tm->tm_year + 1900, tm->tm_mon + 1)))
			{
				tm->tm_mday -= shift;
				tm_add(tm, 1, ZBX_TIME_UNIT_MONTH);
			}
			tm->tm_wday += multiplier;
			tm->tm_wday %= 7;
			break;
		case ZBX_TIME_UNIT_WEEK:
			tm_add(tm, multiplier * 7, ZBX_TIME_UNIT_DAY);
			break;
		case ZBX_TIME_UNIT_MONTH:
			tm->tm_mon += multiplier;
			if (12 <= tm->tm_mon)
			{
				shift = tm->tm_mon / 12;
				tm->tm_mon %= 12;
				tm_add(tm, shift, ZBX_TIME_UNIT_YEAR);
			}
			break;
		case ZBX_TIME_UNIT_YEAR:
			tm->tm_year += multiplier;
			break;
		default:
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_add                                                       *
 *                                                                            *
 * Purpose: add time duration                                                 *
 *                                                                            *
 * Parameter: tm         - [IN/OUT] the time structure                        *
 *            multiplier - [IN] the unit multiplier                           *
 *            base       - [IN] the time unit to add                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_add(struct tm *tm, int multiplier, zbx_time_unit_t base)
{
	time_t		time_new;
	struct	tm	tm_new;

	tm_add(tm, multiplier, base);

	/* adjust clock if DST changes were in effect */

	tm_new = *tm;

	if (-1 != (time_new = mktime(&tm_new)))
	{
		tm_new = *localtime(&time_new);
		if (tm->tm_isdst != tm_new.tm_isdst && -1 != tm->tm_isdst && -1 != tm_new.tm_isdst)
		{
			*tm = tm_new;
			if (0 == tm->tm_isdst)
				tm_add(tm, 1, ZBX_TIME_UNIT_HOUR);
			else
				tm_sub(tm, 1, ZBX_TIME_UNIT_HOUR);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: tm_sub                                                           *
 *                                                                            *
 * Purpose: subtracts time duration without adjusting DST clocks              *
 *                                                                            *
 * Parameter: tm         - [IN/OUT] the time structure                        *
 *            multiplier - [IN] the unit multiplier                           *
 *            base       - [IN] the time unit to add                          *
 *                                                                            *
 ******************************************************************************/
static void	tm_sub(struct tm *tm, int multiplier, zbx_time_unit_t base)
{
	int	shift;

	switch (base)
	{
		case ZBX_TIME_UNIT_HOUR:
			tm->tm_hour -= multiplier;
			if (0 > tm->tm_hour)
			{
				shift = -tm->tm_hour / 24 + 1;
				tm->tm_hour = 24 + tm->tm_hour % 24;
				tm_sub(tm, shift, ZBX_TIME_UNIT_DAY);
			}
			return;
		case ZBX_TIME_UNIT_DAY:
			tm->tm_mday -= multiplier;
			while (0 >= tm->tm_mday)
			{
				int	prev_mon;

				if (0 > (prev_mon = tm->tm_mon - 1))
					prev_mon = 11;
				prev_mon++;

				tm->tm_mday += zbx_day_in_month(tm->tm_year + 1900, prev_mon);
				tm_sub(tm, 1, ZBX_TIME_UNIT_MONTH);
			}
			tm->tm_wday -= multiplier;
			if (0 < tm->tm_wday)
				tm->tm_wday = 7 + tm->tm_wday % 7;
			return;
		case ZBX_TIME_UNIT_WEEK:
			tm_sub(tm, multiplier * 7, ZBX_TIME_UNIT_DAY);
			return;
		case ZBX_TIME_UNIT_MONTH:
			tm->tm_mon -= multiplier;
			if (0 > tm->tm_mon)
			{
				shift = -tm->tm_mon / 12 + 1;
				tm->tm_mon = 12 + tm->tm_mon % 12;
				tm_sub(tm, shift, ZBX_TIME_UNIT_YEAR);
			}
			return;
		case ZBX_TIME_UNIT_YEAR:
			tm->tm_year -= multiplier;
			return;
		default:
			return;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_sub                                                       *
 *                                                                            *
 * Purpose: subtracts time duration                                           *
 *                                                                            *
 * Parameter: tm         - [IN/OUT] the time structure                        *
 *            multiplier - [IN] the unit multiplier                           *
 *            base       - [IN] the time unit to add                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_sub(struct tm *tm, int multiplier, zbx_time_unit_t base)
{
	time_t		time_new;
	struct	tm	tm_new;

	tm_sub(tm, multiplier, base);

	/* adjust clock if DST changes were in effect */

	tm_new = *tm;

	if (-1 != (time_new = mktime(&tm_new)))
	{
		tm_new = *localtime(&time_new);

		if (tm->tm_isdst != tm_new.tm_isdst && -1 != tm->tm_isdst && -1 != tm_new.tm_isdst)
		{
			*tm = tm_new;
			if (0 == tm->tm_isdst)
				tm_add(tm, 1, ZBX_TIME_UNIT_HOUR);
			else
				tm_sub(tm, 1, ZBX_TIME_UNIT_HOUR);

		}
	}
}
