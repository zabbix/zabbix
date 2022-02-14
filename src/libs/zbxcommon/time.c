/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

static void	tm_add(struct tm *tm, int multiplier, zbx_time_unit_t base);
static void	tm_sub(struct tm *tm, int multiplier, zbx_time_unit_t base);

static int	time_unit_seconds[ZBX_TIME_UNIT_COUNT] = {0, 1, SEC_PER_MIN, SEC_PER_HOUR, SEC_PER_DAY, SEC_PER_WEEK, 0,
		0, 0};

zbx_time_unit_t	zbx_tm_str_to_unit(const char *text)
{
	switch (*text)
	{
		case 's':
			return ZBX_TIME_UNIT_SECOND;
		case 'm':
			return ZBX_TIME_UNIT_MINUTE;
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

	if (FAIL == is_uint_n_range(period, (size_t)(ptr - period), multiplier, sizeof(*multiplier), 0, UINT32_MAX))
	{
		*error = zbx_strdup(*error, "invalid period multiplier");
		return FAIL;
	}

	if (ZBX_TIME_UNIT_UNKNOWN == (*base = zbx_tm_str_to_unit(ptr)))
	{
		*error = zbx_strdup(*error, "invalid period time unit");
		return FAIL;
	}

	*len = (size_t)(ptr - period) + 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add seconds to the time and adjust result by dst                  *
 *                                                                            *
 * Parameter: tm      - [IN/OUT] the time structure                           *
 *            seconds - [IN] the seconds to add (can be negative)             *
 *                                                                            *
 ******************************************************************************/
static void	tm_add_seconds(struct tm *tm, int seconds)
{
	time_t		time_new;
	struct tm	tm_new = *tm;

	if (-1 == (time_new = mktime(&tm_new)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	time_new += seconds;
	localtime_r(&time_new, &tm_new);

	if (tm->tm_isdst != tm_new.tm_isdst && -1 != tm->tm_isdst && -1 != tm_new.tm_isdst)
	{
		if (0 == tm_new.tm_isdst)
			tm_add(&tm_new, 1, ZBX_TIME_UNIT_HOUR);
		else
			tm_sub(&tm_new, 1, ZBX_TIME_UNIT_HOUR);
	}

	*tm = tm_new;
}

/******************************************************************************
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
	int	shift;

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
 * Purpose: add time duration                                                 *
 *                                                                            *
 * Parameter: tm         - [IN/OUT] the time structure                        *
 *            multiplier - [IN] the unit multiplier                           *
 *            base       - [IN] the time unit to add                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_add(struct tm *tm, int multiplier, zbx_time_unit_t base)
{
	if (ZBX_TIME_UNIT_MONTH == base || ZBX_TIME_UNIT_YEAR == base)
	{
		int	days_max;

		tm_add(tm, multiplier, base);

		days_max = zbx_day_in_month(tm->tm_year + 1900, tm->tm_mon + 1);
		if (tm->tm_mday > days_max)
			tm->tm_mday = days_max;
	}

	tm_add_seconds(tm, multiplier * time_unit_seconds[base]);

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert negative number to positive by wrapping around the base   *
 *                                                                            *
 * Parameter: value - [IN/OUT] the value to convert                           *
 *            base  - [IN] the wrap base                                      *
 *                                                                            *
 ******************************************************************************/
static void	neg_to_pos_wrap(int *value, int base)
{
	int	reminder = *value % base;

	*value = (0 == reminder ? 0 : base + reminder);
}

/******************************************************************************
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
				shift = -tm->tm_hour / 24;
				neg_to_pos_wrap(&tm->tm_hour, 24);
				if (0 != tm->tm_hour)
					shift++;
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
			if (0 > tm->tm_wday)
				neg_to_pos_wrap(&tm->tm_wday, 7);
			return;
		case ZBX_TIME_UNIT_WEEK:
			tm_sub(tm, multiplier * 7, ZBX_TIME_UNIT_DAY);
			return;
		case ZBX_TIME_UNIT_MONTH:
			tm->tm_mon -= multiplier;
			if (0 > tm->tm_mon)
			{
				shift = -tm->tm_mon / 12;
				neg_to_pos_wrap(&tm->tm_mon, 12);
				if (0 != tm->tm_mon)
					shift++;
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
 * Purpose: subtracts time duration                                           *
 *                                                                            *
 * Parameter: tm         - [IN/OUT] the time structure                        *
 *            multiplier - [IN] the unit multiplier                           *
 *            base       - [IN] the time unit to add                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_sub(struct tm *tm, int multiplier, zbx_time_unit_t base)
{
	if (ZBX_TIME_UNIT_ISOYEAR == base)
	{
		int	week_num, total_weeks;

		week_num = zbx_get_week_number(tm);

		/* use zbx_tm_sub instead of tm_sub to force weekday recalculation */
		zbx_tm_sub(tm, week_num, ZBX_TIME_UNIT_WEEK);

		total_weeks = zbx_get_week_number(tm);
		if (week_num > total_weeks)
			week_num--;
		tm_sub(tm, zbx_get_week_number(tm) - week_num, ZBX_TIME_UNIT_WEEK);
	}
	else if (ZBX_TIME_UNIT_MONTH == base || ZBX_TIME_UNIT_YEAR == base)
	{
		int	days_max;

		tm_sub(tm, multiplier, base);

		days_max = zbx_day_in_month(tm->tm_year + 1900, tm->tm_mon + 1);
		if (tm->tm_mday > days_max)
			tm->tm_mday = days_max;
	}

	tm_add_seconds(tm, -multiplier * time_unit_seconds[base]);

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: rounds time by the specified unit upwards                         *
 *                                                                            *
 * Parameter: tm         - [IN/OUT] the time structure                        *
 *            base       - [IN] the time unit                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_round_up(struct tm *tm, zbx_time_unit_t base)
{
	if (0 != tm->tm_sec)
	{
		tm->tm_sec = 0;
		zbx_tm_add(tm, 1, ZBX_TIME_UNIT_MINUTE);
	}

	if (ZBX_TIME_UNIT_MINUTE == base)
		return;

	if (0 != tm->tm_min)
	{
		tm->tm_min = 0;
		zbx_tm_add(tm, 1, ZBX_TIME_UNIT_HOUR);
	}

	if (ZBX_TIME_UNIT_HOUR == base)
		return;

	if (0 != tm->tm_hour)
	{
		tm->tm_hour = 0;
		zbx_tm_add(tm, 1, ZBX_TIME_UNIT_DAY);
	}

	if (ZBX_TIME_UNIT_DAY == base)
		return;

	if (ZBX_TIME_UNIT_WEEK == base)
	{
		if (1 != tm->tm_wday)
		{
			zbx_tm_add(tm, (0 == tm->tm_wday ? 1 : 8 - tm->tm_wday), ZBX_TIME_UNIT_DAY);
			tm->tm_wday = 1;
		}
		return;
	}

	if (1 != tm->tm_mday)
	{
		tm->tm_mday = 1;
		zbx_tm_add(tm, 1, ZBX_TIME_UNIT_MONTH);
	}

	if (ZBX_TIME_UNIT_MONTH == base)
		return;

	if (0 != tm->tm_mon)
	{
		tm->tm_mon = 0;
		zbx_tm_add(tm, 1, ZBX_TIME_UNIT_YEAR);
	}

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: rounds time by the specified unit downwards                       *
 *                                                                            *
 * Parameter: tm         - [IN/OUT] the time structure                        *
 *            base       - [IN] the time unit                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_round_down(struct tm *tm, zbx_time_unit_t base)
{
	switch (base)
	{
		case ZBX_TIME_UNIT_WEEK:
			if (1 != tm->tm_wday)
			{
				zbx_tm_sub(tm, (0 == tm->tm_wday ? 6 : tm->tm_wday - 1), ZBX_TIME_UNIT_DAY);
				tm->tm_wday = 1;
			}

			tm->tm_hour = 0;
			tm->tm_min = 0;
			tm->tm_sec = 0;
			break;
		case ZBX_TIME_UNIT_ISOYEAR:
			zbx_tm_round_down(tm, ZBX_TIME_UNIT_WEEK);
			zbx_tm_sub(tm, zbx_get_week_number(tm) - 1, ZBX_TIME_UNIT_WEEK);
			break;
		case ZBX_TIME_UNIT_YEAR:
			tm->tm_mon = 0;
			ZBX_FALLTHROUGH;
		case ZBX_TIME_UNIT_MONTH:
			tm->tm_mday = 1;
			ZBX_FALLTHROUGH;
		case ZBX_TIME_UNIT_DAY:
			tm->tm_hour = 0;
			ZBX_FALLTHROUGH;
		case ZBX_TIME_UNIT_HOUR:
			tm->tm_min = 0;
			ZBX_FALLTHROUGH;
		case ZBX_TIME_UNIT_MINUTE:
			tm->tm_sec = 0;
			break;
		default:
			break;
	}

	tm_add_seconds(tm, 0);

	return;
}

const char	*zbx_timespec_str(const zbx_timespec_t *ts)
{
	static ZBX_THREAD_LOCAL char	str[32];

	time_t		ts_time = ts->sec;
	struct tm	tm;

	localtime_r(&ts_time, &tm);
	zbx_snprintf(str, sizeof(str), "%04d.%02d.%02d %02d:%02d:%02d.%09d", tm.tm_year + 1900, tm.tm_mon + 1,
			tm.tm_mday, tm.tm_hour, tm.tm_min, tm.tm_sec, ts->ns);

	return str;
}

static	int	get_week_days(int yday, int wday)
{
	return yday - (yday - wday + 382) % 7 + 3;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get ISO 8061 week number (1-53)                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_week_number(const struct tm *tm)
{
	int	days;

	if (0 > (days = get_week_days(tm->tm_yday, tm->tm_wday)))
	{
		int	d = tm->tm_yday + 365;

		if (SUCCEED == zbx_is_leap_year(tm->tm_year + 1899))
			d++;

		days = get_week_days(d, tm->tm_wday);
	}
	else
	{
		int days_next, d;

		d = tm->tm_yday - 365;
		if (SUCCEED == zbx_is_leap_year(tm->tm_year + 1900))
			d--;

		if (0 <= (days_next = get_week_days(d, tm->tm_wday)))
			days = days_next;
	}

	return days / 7 + 1;
}
