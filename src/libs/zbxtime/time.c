/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxtime.h"

#include "zbxnum.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Gets the current time.                                            *
 *                                                                            *
 * Return value: Time in seconds                                              *
 *                                                                            *
 * Comments: Time in seconds since midnight (00:00:00),                       *
 *           January 1, 1970, coordinated universal time (UTC).               *
 *                                                                            *
 ******************************************************************************/
double	zbx_time(void)
{
	zbx_timespec_t	ts;

	zbx_timespec(&ts);

	return (double)ts.sec + 1.0e-9 * (double)ts.ns;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Gets the current time.                                            *
 *                                                                            *
 * Comments: Time in seconds since midnight (00:00:00),                       *
 *           January 1, 1970, coordinated universal time (UTC).               *
 *                                                                            *
 ******************************************************************************/
void	zbx_timespec(zbx_timespec_t *ts)
{
	static ZBX_THREAD_LOCAL zbx_timespec_t	last_ts = {0, 0};
	static ZBX_THREAD_LOCAL int		corr = 0;
#if defined(_WINDOWS) || defined(__MINGW32__)
	static ZBX_THREAD_LOCAL LARGE_INTEGER	tickPerSecond = {0};
	struct _timeb				tb;
	int					sec_diff;
#else
	struct timeval	tv;
	int		rc = -1;
#	ifdef HAVE_TIME_CLOCK_GETTIME
	struct timespec	tp;
#	endif
#endif
#if defined(_WINDOWS) || defined(__MINGW32__)

	if (0 == tickPerSecond.QuadPart)
		QueryPerformanceFrequency(&tickPerSecond);

	_ftime(&tb);

	ts->sec = (int)tb.time;
	ts->ns = tb.millitm * 1000000;

	if (0 != tickPerSecond.QuadPart)
	{
		LARGE_INTEGER	tick;

		if (TRUE == QueryPerformanceCounter(&tick))
		{
			static ZBX_THREAD_LOCAL LARGE_INTEGER	last_tick = {0};

			if (0 < last_tick.QuadPart)
			{
				LARGE_INTEGER	qpc_tick = {0}, ntp_tick = {0};

				/* _ftime () returns precision in milliseconds, but 'ns' could be increased up to 1ms */
				if (last_ts.sec == ts->sec && last_ts.ns > ts->ns && 1000000 > (last_ts.ns - ts->ns))
				{
					ts->ns = last_ts.ns;
				}
				else
				{
					ntp_tick.QuadPart = tickPerSecond.QuadPart * (ts->sec - last_ts.sec) +
							tickPerSecond.QuadPart * (ts->ns - last_ts.ns) / 1000000000;
				}

				/* host system time can shift backwards, then correction is not reasonable */
				if (0 <= ntp_tick.QuadPart)
					qpc_tick.QuadPart = tick.QuadPart - last_tick.QuadPart - ntp_tick.QuadPart;

				if (0 < qpc_tick.QuadPart && qpc_tick.QuadPart < tickPerSecond.QuadPart)
				{
					int	ns = (int)(1000000000 * qpc_tick.QuadPart / tickPerSecond.QuadPart);

					if (1000000 > ns)	/* value less than 1 millisecond */
					{
						ts->ns += ns;

						while (ts->ns >= 1000000000)
						{
							ts->sec++;
							ts->ns -= 1000000000;
						}
					}
				}
			}

			last_tick = tick;
		}
	}
#else	/* not _WINDOWS */
#ifdef HAVE_TIME_CLOCK_GETTIME
	if (0 == (rc = clock_gettime(CLOCK_REALTIME, &tp)))
	{
		ts->sec = (int)tp.tv_sec;
		ts->ns = (int)tp.tv_nsec;
	}
#endif	/* HAVE_TIME_CLOCK_GETTIME */

	if (0 != rc && 0 == (rc = gettimeofday(&tv, NULL)))
	{
		ts->sec = (int)tv.tv_sec;
		ts->ns = (int)tv.tv_usec * 1000;
	}

	if (0 != rc)
	{
		ts->sec = (int)time(NULL);
		ts->ns = 0;
	}
#endif	/* not _WINDOWS */

#if defined(_WINDOWS) || defined(__MINGW32__)
	sec_diff = ts->sec - last_ts.sec;

	/* correction window is 1 sec before the corrected last _ftime clock reading */
	if ((0 == sec_diff && ts->ns <= last_ts.ns) || (-1 == sec_diff && ts->ns > last_ts.ns))
#else
	if (last_ts.ns == ts->ns && last_ts.sec == ts->sec)
#endif
	{
		ts->ns = last_ts.ns + (++corr);

		while (ts->ns >= 1000000000)
		{
			ts->sec++;
			ts->ns -= 1000000000;
		}
	}
	else
	{
		last_ts.sec = ts->sec;
		last_ts.ns = ts->ns;
		corr = 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Gets the current time including UTC offset                        *
 *                                                                            *
 * Return value: Time in seconds                                              *
 *                                                                            *
 ******************************************************************************/
double	zbx_current_time(void)
{
	return zbx_time() + ZBX_JAN_1970_IN_SEC;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - year is a leap year                               *
 *                FAIL    - year is not a leap year                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_leap_year(int year)
{
	return 0 == year % 4 && (0 != year % 100 || 0 == year % 400) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     get current time and store it in memory locations provided by caller   *
 *                                                                            *
 * Parameters:                                                                *
 *     tm           - [OUT] broken-down representation of the current time    *
 *     milliseconds - [OUT] milliseconds since the previous second            *
 *     tz           - [OUT] local time offset from UTC (optional)             *
 *                                                                            *
 * Comments:                                                                  *
 *     On Windows localtime() and gmtime() return pointers to static,         *
 *     thread-local storage locations. On Unix localtime() and gmtime() are   *
 *     not thread-safe and re-entrant as they return pointers to static       *
 *     storage locations which can be overwritten by localtime(), gmtime()    *
 *     or other time functions in other threads or signal handlers. To avoid  *
 *     this we use localtime_r() and gmtime_r().                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_get_time(struct tm *tm, long *milliseconds, zbx_timezone_t *tz)
{
#if defined(_WINDOWS) || defined(__MINGW32__)
	struct _timeb	current_time;

	_ftime(&current_time);
	*tm = *localtime(&current_time.time);	/* localtime() cannot return NULL if called with valid parameter */
	*milliseconds = current_time.millitm;
#else
	struct timeval	current_time;

	gettimeofday(&current_time, NULL);
	localtime_r(&current_time.tv_sec, tm);
	*milliseconds = current_time.tv_usec / 1000;
#endif
	if (NULL != tz)
	{
		long	offset;
#if defined(_WINDOWS) || defined(__MINGW32__)
		offset = zbx_get_timezone_offset(current_time.time, tm);
#else
		offset = zbx_get_timezone_offset(current_time.tv_sec, tm);
#endif
		tz->tz_sign = (0 <= offset ? '+' : '-');
		tz->tz_hour = labs(offset) / SEC_PER_HOUR;
		tz->tz_min = (labs(offset) - tz->tz_hour * SEC_PER_HOUR) / SEC_PER_MIN;
		/* assuming no remaining seconds like in historic Asia/Riyadh87, Asia/Riyadh88 and Asia/Riyadh89 */
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get time offset from UTC                                          *
 *                                                                            *
 * Parameters: t  - [IN] input time to calculate offset with                  *
 *             tm - [OUT] broken-down representation of the current time      *
 *                                                                            *
 * Return value: Time offset from UTC in seconds                              *
 *                                                                            *
 ******************************************************************************/
long	zbx_get_timezone_offset(time_t t, struct tm *tm)
{
	long		offset;
#ifndef HAVE_TM_TM_GMTOFF
	struct tm	tm_utc;
#endif

	*tm = *localtime(&t);

#ifdef HAVE_TM_TM_GMTOFF
	offset = tm->tm_gmtoff;
#else
#if defined(_WINDOWS) || defined(__MINGW32__)
	tm_utc = *gmtime(&t);
#else
	gmtime_r(&t, &tm_utc);
#endif
	offset = (tm->tm_yday - tm_utc.tm_yday) * SEC_PER_DAY +
			(tm->tm_hour - tm_utc.tm_hour) * SEC_PER_HOUR +
			(tm->tm_min - tm_utc.tm_min) * SEC_PER_MIN;	/* assuming seconds are equal */

	while (tm->tm_year > tm_utc.tm_year)
		offset += (SUCCEED == zbx_is_leap_year(tm_utc.tm_year++) ? SEC_PER_YEAR + SEC_PER_DAY : SEC_PER_YEAR);

	while (tm->tm_year < tm_utc.tm_year)
		offset -= (SUCCEED == zbx_is_leap_year(--tm_utc.tm_year) ? SEC_PER_YEAR + SEC_PER_DAY : SEC_PER_YEAR);
#endif

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get broken-down representation of the time in specified time zone *
 *                                                                            *
 * Parameters: time - [IN] input time                                         *
 *             tz   - [IN] time zone                                          *
 *                                                                            *
 * Return value: broken-down representation of the time in specified time zone*
 *                                                                            *
 ******************************************************************************/
struct tm	*zbx_localtime(const time_t *time, const char *tz)
{
#if defined(HAVE_GETENV) && defined(HAVE_UNSETENV) && defined(HAVE_TZSET) && \
		!defined(_WINDOWS) && !defined(__MINGW32__)
	char		*old_tz;
	struct tm	*tm;

	if (NULL == tz || 0 == strcmp(tz, "system"))
		return localtime(time);

	if (NULL != (old_tz = getenv("TZ")))
		old_tz = zbx_strdup(NULL, old_tz);

	setenv("TZ", tz, 1);

	tzset();
	tm = localtime(time);

	if (NULL != old_tz)
	{
		setenv("TZ", old_tz, 1);
		zbx_free(old_tz);
	}
	else
		unsetenv("TZ");

	tzset();

	return tm;
#else
	ZBX_UNUSED(tz);
	return localtime(time);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: get broken-down representation of the time and cache result       *
 *                                                                            *
 * Parameters: time - [IN] input time                                         *
 *                                                                            *
 * Return value: broken-down representation of the time                       *
 *                                                                            *
 ******************************************************************************/
const struct tm	*zbx_localtime_now(const time_t *time)
{
	static ZBX_THREAD_LOCAL struct tm	tm_last;
	static ZBX_THREAD_LOCAL time_t		time_last;

	if (time_last != *time)
	{
		time_last = *time;
		localtime_r(time, &tm_last);
	}

	return &tm_last;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get UTC time from broken down time elements                       *
 *                                                                            *
 * Parameters:                                                                *
 *     year  - [IN] year (1970-...)                                           *
 *     month - [IN] month (1-12)                                              *
 *     mday  - [IN] day of month (1-..., depending on month and year)         *
 *     hour  - [IN] hours (0-23)                                              *
 *     min   - [IN] minutes (0-59)                                            *
 *     sec   - [IN] seconds (0-61, leap seconds are not strictly validated)   *
 *     t     - [OUT] Epoch timestamp                                          *
 *                                                                            *
 * Return value:  SUCCEED - date is valid and resulting timestamp is positive *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_utc_time(int year, int mon, int mday, int hour, int min, int sec, int *t)
{
/* number of leap years before but not including year */
#define ZBX_LEAP_YEARS(year)	(((year) - 1) / 4 - ((year) - 1) / 100 + ((year) - 1) / 400)

	/* days since the beginning of non-leap year till the beginning of the month */
	static const int	month_day[12] = { 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 };
	static const int	epoch_year = 1970;

	if (epoch_year <= year && 1 <= mon && mon <= 12 && 1 <= mday && mday <= zbx_day_in_month(year, mon) &&
			0 <= hour && hour <= 23 && 0 <= min && min <= 59 && 0 <= sec && sec <= 61 &&
			0 <= (*t = (year - epoch_year) * SEC_PER_YEAR +
			(ZBX_LEAP_YEARS(2 < mon ? year + 1 : year) - ZBX_LEAP_YEARS(epoch_year)) * SEC_PER_DAY +
			(month_day[mon - 1] + mday - 1) * SEC_PER_DAY + hour * SEC_PER_HOUR + min * SEC_PER_MIN + sec))
	{
		return SUCCEED;
	}

	return FAIL;
#undef ZBX_LEAP_YEARS
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns number of days in a month                                 *
 *                                                                            *
 * Parameters:                                                                *
 *     year  - [IN]                                                           *
 *     mon   - [IN] month (1-12)                                              *
 *                                                                            *
 * Return value: 28-31 depending on number of days in the month, defaults to  *
 *               30 if the month is outside of allowed range                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_day_in_month(int year, int mon)
{
	/* number of days in the month of a non-leap year */
	static const unsigned char	month[12] = { 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 };

	if (1 <= mon && mon <= 12)	/* add one day in February of a leap year */
		return month[mon - 1] + (2 == mon && SUCCEED == zbx_is_leap_year(year) ? 1 : 0);

	return 30;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get duration in milliseconds since time stamp till current time   *
 *                                                                            *
 * Parameters:                                                                *
 *     ts - [IN] time from when duration should be counted                    *
 *                                                                            *
 * Return value: duration in milliseconds since time stamp till current time  *
 *                                                                            *
 * Comments:                                                                  *
 *     Timestamp value 'ts' must be before or equal to current time.          *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_get_duration_ms(const zbx_timespec_t *ts)
{
	zbx_timespec_t	now;

	zbx_timespec(&now);

	return (zbx_uint64_t)((now.sec - ts->sec) * 1e3 + (now.ns - ts->ns) / 1e6);
}

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

	if (FAIL == zbx_is_uint_n_range(period, (size_t)(ptr - period), multiplier, sizeof(*multiplier), 0, UINT32_MAX))
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
 *            tz      - [IN] time zone                                        *
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

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is a non-negative integer with or without     *
 *          supported time suffix                                             *
 *                                                                            *
 * Parameters: str    - [IN] string to check                                  *
 *             value  - [OUT] a pointer to converted value (optional)         *
 *             length - [IN] number of characters to validate, pass           *
 *                      ZBX_LENGTH_UNLIMITED to validate full string          *
 *                                                                            *
 * Return value: SUCCEED - the string is valid and within reasonable limits   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: the function automatically processes suffixes s, m, h, d, w      *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_time_suffix(const char *str, int *value, int length)
{
	const int	max = 0x7fffffff;	/* minimum acceptable value for INT_MAX is 2 147 483 647 */
	int		len = length;
	int		value_tmp = 0, c, factor = 1;

	if ('\0' == *str || 0 >= len || 0 == isdigit(*str))
		return FAIL;

	while ('\0' != *str && 0 < len && 0 != isdigit(*str))
	{
		c = (int)(unsigned char)(*str - '0');

		if ((max - c) / 10 < value_tmp)
			return FAIL;	/* overflow */

		value_tmp = value_tmp * 10 + c;

		str++;
		len--;
	}

	if ('\0' != *str && 0 < len)
	{
		switch (*str)
		{
			case 's':
				break;
			case 'm':
				factor = SEC_PER_MIN;
				break;
			case 'h':
				factor = SEC_PER_HOUR;
				break;
			case 'd':
				factor = SEC_PER_DAY;
				break;
			case 'w':
				factor = SEC_PER_WEEK;
				break;
			default:
				return FAIL;
		}

		str++;
		len--;
	}

	if ((ZBX_LENGTH_UNLIMITED == length && '\0' != *str) || (ZBX_LENGTH_UNLIMITED != length && 0 != len))
		return FAIL;

	if (max / factor < value_tmp)
		return FAIL;	/* overflow */

	if (NULL != value)
		*value = value_tmp * factor;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate sleep time for Zabbix processes                         *
 *                                                                            *
 * Parameters: nextcheck     - [IN] next check or -1 (FAIL) if nothing to do  *
 *             max_sleeptime - [IN] maximum sleep time, in seconds            *
 *                                                                            *
 * Return value: sleep time, in seconds                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_calculate_sleeptime(int nextcheck, int max_sleeptime)
{
	int	sleeptime;

	if (FAIL == nextcheck)
		return max_sleeptime;

	sleeptime = nextcheck - (int)time(NULL);

	if (sleeptime < 0)
		return 0;

	if (sleeptime > max_sleeptime)
		return max_sleeptime;

	return sleeptime;
}

char	*zbx_age2str(time_t age)
{
	size_t		offset = 0;
	int		days, hours, minutes, seconds;
	static char	buffer[32];

	days = (int)((double)age / SEC_PER_DAY);
	hours = (int)((double)(age - days * SEC_PER_DAY) / SEC_PER_HOUR);
	minutes = (int)((double)(age - days * SEC_PER_DAY - hours * SEC_PER_HOUR) / SEC_PER_MIN);
	seconds = (int)((double)(age - days * SEC_PER_DAY - hours * SEC_PER_HOUR - minutes * SEC_PER_MIN));

	if (0 != days)
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%dd ", days);
	if (0 != days || 0 != hours)
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%dh ", hours);
	if (0 != days || 0 != hours || 0 != minutes)
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%dm ", minutes);

	zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%ds", seconds);

	return buffer;
}

char	*zbx_date2str(time_t date, const char *tz)
{
	static char	buffer[11];
	struct tm	*tm;

	tm = zbx_localtime(&date, tz);
	zbx_snprintf(buffer, sizeof(buffer), "%.4d.%.2d.%.2d",
			tm->tm_year + 1900,
			tm->tm_mon + 1,
			tm->tm_mday);

	return buffer;
}

char	*zbx_time2str(time_t time, const char *tz)
{
	static char	buffer[9];
	struct tm	*tm;

	tm = zbx_localtime(&time, tz);
	zbx_snprintf(buffer, sizeof(buffer), "%.2d:%.2d:%.2d",
			tm->tm_hour,
			tm->tm_min,
			tm->tm_sec);
	return buffer;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert string from iso8601 timezone info to offset in seconds    *
 *                                                                            *
 * Parameters: zone    - [IN] iso8601 timezone string                         *
 *             offset  - [OUT] offset value                                   *
 *                                                                            *
 * Return value: SUCCEED   - the operation has completed successfully         *
 *               FAIL      - the operation has failed                         *
 *                                                                            *
 ******************************************************************************/
static int zbx_iso8601_timezone(const char *zone, long int *offset)
{
	int		m, h, sign = 0;
	char		c;
	const char	*ptr = zone;

	if ('.' == *zone)	/* skip milliseconds */
	{
		for (ptr++; 0 != isdigit(*ptr); ptr++)
			;
	}

	for (; ' ' == *ptr; ptr++)
		;

	*offset = 0;
	c = *ptr;

	if ('\0' == c || 'Z' == c || 'z' == c)
		return SUCCEED;
	else if ('-' == c)
		sign = -1;
	else if ('+' == c)
		sign = +1;
	else
		return FAIL;

	ptr++;

	if (ZBX_CONST_STRLEN("0000") > strlen(ptr))
		return FAIL;

	if (SUCCEED != zbx_is_uint_n_range(ptr, 2, &h, sizeof(h), 0, 23))
		return FAIL;

	ptr += 2;

	if (':' == *ptr)
		ptr++;

	if (0 == isdigit(*ptr) || 59 < (m = atoi(ptr)))
		return FAIL;

	*offset = sign * (m + h * 60) * 60;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse string from iso8601 datetime (xml base) to UTC              *
 *          without millisecond, supported formats:                           *
 *              yyyy-mm-ddThh:mm:ss                                           *
 *              yyyy-mm-ddThh:mm:ssZ                                          *
 *              yyyy-mm-ddThh:mm:ss+hh:mm                                     *
 *              yyyy-mm-ddThh:mm:ss-hh:mm                                     *
 *              yyyy-mm-ddThh:mm:ss +hh:mm                                    *
 *              yyyy-mm-ddThh:mm:ss -hh:mm                                    *
 *              yyyy-mm-ddThh:mm:ss+hhmm                                      *
 *              yyyy-mm-ddThh:mm:ss-hhmm                                      *
 *              yyyy-mm-ddThh:mm:ss +hhmm                                     *
 *              yyyy-mm-ddThh:mm:ss -hhmm                                     *
 *              yyyy-mm-ddThh:mm:ss.ccc                                       *
 *              yyyy-mm-ddThh:mm:ss.cccZ                                      *
 *              yyyy-mm-ddThh:mm:ss.ccc+hh:mm                                 *
 *              yyyy-mm-ddThh:mm:ss.ccc-hh:mm                                 *
 *              yyyy-mm-ddThh:mm:ss.ccc +hh:mm                                *
 *              yyyy-mm-ddThh:mm:ss.ccc -hh:mm                                *
 *              yyyy-mm-ddThh:mm:ss.ccc+hhmm                                  *
 *              yyyy-mm-ddThh:mm:ss.ccc-hhmm                                  *
 *              yyyy-mm-ddThh:mm:ss.ccc +hhmm                                 *
 *              yyyy-mm-ddThh:mm:ss.ccc -hhmm                                 *
 *              yyyy-mm-dd hh:mm:ss                                           *
 *              yyyy-mm-dd hh:mm:ssZ                                          *
 *              yyyy-mm-dd hh:mm:ss+hh:mm                                     *
 *              yyyy-mm-dd hh:mm:ss-hh:mm                                     *
 *              yyyy-mm-dd hh:mm:ss +hh:mm                                    *
 *              yyyy-mm-dd hh:mm:ss -hh:mm                                    *
 *              yyyy-mm-dd hh:mm:ss+hhmm                                      *
 *              yyyy-mm-dd hh:mm:ss-hhmm                                      *
 *              yyyy-mm-dd hh:mm:ss +hhmm                                     *
 *              yyyy-mm-dd hh:mm:ss -hhmm                                     *
 *              yyyy-mm-dd hh:mm:ss.ccc                                       *
 *              yyyy-mm-dd hh:mm:ss.cccZ                                      *
 *              yyyy-mm-dd hh:mm:ss.ccc+hh:mm                                 *
 *              yyyy-mm-dd hh:mm:ss.ccc-hh:mm                                 *
 *              yyyy-mm-dd hh:mm:ss.ccc +hh:mm                                *
 *              yyyy-mm-dd hh:mm:ss.ccc -hh:mm                                *
 *              yyyy-mm-dd hh:mm:ss.ccc+hhmm                                  *
 *              yyyy-mm-dd hh:mm:ss.ccc-hhmm                                  *
 *              yyyy-mm-dd hh:mm:ss.ccc +hhmm                                 *
 *              yyyy-mm-dd hh:mm:ss.ccc -hhmm                                 *
 *                                                                            *
 * Parameters: str  - [IN] iso8601 datetime string                            *
 *             time - [OUT] parsed tm value                                   *
 *                                                                            *
 * Return value: SUCCEED   - the operation has completed successfully         *
 *               FAIL      - the operation has failed                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_iso8601_utc(const char *str, time_t *time)
{
	long int	offset;
	struct tm	tm;

	if (0 == isdigit(*str) || ZBX_CONST_STRLEN("1234-12-12T12:12:12") > strlen(str) ||
			('T' != str[10] && ' ' != str[10]) ||
			'-' != str[4] || '-' != str[7] || ':' != str[13] || ':' != str[16])
	{
		return FAIL;
	}

	memset(&tm, 0 , sizeof (struct tm));
	tm.tm_year = atoi(str);

	if (0 == isdigit(str[5]) || 12 < (tm.tm_mon = atoi(&str[5])))
		return FAIL;

	if (0 == isdigit(str[8]) || 31 < (tm.tm_mday = atoi(&str[8])))
		return FAIL;

	if (0 == isdigit(str[11]) || 23 < (tm.tm_hour = atoi(&str[11])))
		return FAIL;

	if (0 == isdigit(str[14]) || 59 < (tm.tm_min = atoi(&str[14])))
		return FAIL;

	if (0 == isdigit(str[17]) || 59 < (tm.tm_sec = atoi(&str[17])))
		return FAIL;

	tm.tm_isdst = 0;

	if (FAIL == zbx_iso8601_timezone(&str[19], &offset))
		return FAIL;

	if (NULL != time)
	{
		int	t;

		if (FAIL == zbx_utc_time(tm.tm_year, tm.tm_mon, tm.tm_mday, tm.tm_hour, tm.tm_min, tm.tm_sec, &t))
			return FAIL;

		*time = t - offset;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get time deadline                                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_ts_get_deadline(zbx_timespec_t *ts, int sec)
{
	zbx_timespec(ts);
	ts->sec += sec;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if deadline has not been reached                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_ts_check_deadline(const zbx_timespec_t *deadline)
{
	zbx_timespec_t	ts;

	zbx_timespec(&ts);

	if (0 < zbx_timespec_compare(&ts, deadline))
		return FAIL;

	return SUCCEED;
}
