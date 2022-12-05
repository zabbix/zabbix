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
#include "log.h"
#include "setproctitle.h"

/* scheduler support */

#define ZBX_SCHEDULER_FILTER_DAY	1
#define ZBX_SCHEDULER_FILTER_HOUR	2
#define ZBX_SCHEDULER_FILTER_MINUTE	3
#define ZBX_SCHEDULER_FILTER_SECOND	4

typedef struct
{
	int	start_day;	/* day of week when period starts */
	int	end_day;	/* day of week when period ends, included */
	int	start_time;	/* number of seconds from the beginning of the day when period starts */
	int	end_time;	/* number of seconds from the beginning of the day when period ends, not included */
}
zbx_time_period_t;

typedef struct zbx_flexible_interval
{
	zbx_time_period_t		period;
	int				delay;

	struct zbx_flexible_interval	*next;
}
zbx_flexible_interval_t;

typedef struct zbx_scheduler_filter
{
	int				start;
	int				end;
	int				step;

	struct zbx_scheduler_filter	*next;
}
zbx_scheduler_filter_t;

typedef struct zbx_scheduler_interval
{
	zbx_scheduler_filter_t		*mdays;
	zbx_scheduler_filter_t		*wdays;
	zbx_scheduler_filter_t		*hours;
	zbx_scheduler_filter_t		*minutes;
	zbx_scheduler_filter_t		*seconds;

	int				filter_level;

	struct zbx_scheduler_interval	*next;
}
zbx_scheduler_interval_t;

struct zbx_custom_interval
{
	zbx_flexible_interval_t		*flexible;
	zbx_scheduler_interval_t	*scheduling;
};

const int	INTERFACE_TYPE_PRIORITY[INTERFACE_TYPE_COUNT] =
{
	INTERFACE_TYPE_AGENT,
	INTERFACE_TYPE_SNMP,
	INTERFACE_TYPE_JMX,
	INTERFACE_TYPE_IPMI
};

static ZBX_THREAD_LOCAL volatile sig_atomic_t	zbx_timed_out;	/* 0 - no timeout occurred, 1 - SIGALRM took place */

double	ZBX_DOUBLE_EPSILON = 2.22e-16;

#ifdef _WINDOWS

char	ZABBIX_SERVICE_NAME[ZBX_SERVICE_NAME_LEN] = APPLICATION_NAME;
char	ZABBIX_EVENT_SOURCE[ZBX_SERVICE_NAME_LEN] = APPLICATION_NAME;

int	__zbx_stat(const char *path, zbx_stat_t *buf)
{
	int	ret, fd;
	wchar_t	*wpath;

	wpath = zbx_utf8_to_unicode(path);

	if (-1 == (ret = _wstat64(wpath, buf)))
		goto out;

	if (0 != S_ISDIR(buf->st_mode) || 0 != buf->st_size)
		goto out;

	/* In the case of symlinks _wstat64 returns zero file size.   */
	/* Try to work around it by opening the file and using fstat. */

	ret = -1;

	if (-1 != (fd = _wopen(wpath, O_RDONLY)))
	{
		ret = _fstat64(fd, buf);
		_close(fd);
	}
out:
	zbx_free(wpath);

	return ret;
}

#endif

/******************************************************************************
 *                                                                            *
 * Purpose: return program name without path                                  *
 *                                                                            *
 * Return value: program name without path                                    *
 *                                                                            *
 ******************************************************************************/
const char	*get_program_name(const char *path)
{
	const char	*filename = NULL;

	for (filename = path; path && *path; path++)
	{
		if ('\\' == *path || '/' == *path)
			filename = path + 1;
	}

	return filename;
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

	if (last_ts.ns == ts->ns && last_ts.sec == ts->sec)
	{
		ts->ns += ++corr;

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
#if defined(HAVE_GETENV) && defined(HAVE_PUTENV) && defined(HAVE_UNSETENV) && defined(HAVE_TZSET) && \
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
 ******************************************************************************/
zbx_uint64_t	zbx_get_duration_ms(const zbx_timespec_t *ts)
{
	zbx_timespec_t	now;

	zbx_timespec(&now);

	return (now.sec - ts->sec) * 1e3 + (now.ns - ts->ns) / 1e6;
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocates nmemb * size bytes of memory and fills it with zeros    *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_calloc2(const char *filename, int line, void *old, size_t nmemb, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	/* old pointer must be NULL */
	if (NULL != old)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_calloc: allocating already allocated memory. "
				"Please report this to Zabbix developers.",
				filename, line);
	}

	for (
		max_attempts = 10, nmemb = MAX(nmemb, 1), size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = calloc(nmemb, size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_calloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocates size bytes of memory                                    *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_malloc2(const char *filename, int line, void *old, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	/* old pointer must be NULL */
	if (NULL != old)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_malloc: allocating already allocated memory. "
				"Please report this to Zabbix developers.",
				filename, line);
	}

	for (
		max_attempts = 10, size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = malloc(size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_malloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: changes the size of the memory block pointed to by old            *
 *          to size bytes                                                     *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_realloc2(const char *filename, int line, void *old, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	for (
		max_attempts = 10, size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = realloc(old, size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_realloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

char	*zbx_strdup2(const char *filename, int line, char *old, const char *str)
{
	int	retry;
	char	*ptr = NULL;

	zbx_free(old);

	for (retry = 10; 0 < retry && NULL == ptr; ptr = strdup(str), retry--)
		;

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_strdup: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)(strlen(str) + 1));

	exit(EXIT_FAILURE);
}

/****************************************************************************************
 *                                                                                      *
 * Purpose: For overwriting sensitive data in memory.                                   *
 *          Similar to memset() but should not be optimized out by a compiler.          *
 *                                                                                      *
 * Derived from:                                                                        *
 *   http://www.dwheeler.com/secure-programs/Secure-Programs-HOWTO/protect-secrets.html *
 * See also:                                                                            *
 *   http://www.open-std.org/jtc1/sc22/wg14/www/docs/n1381.pdf on secure_memset()       *
 *                                                                                      *
 ****************************************************************************************/
void	*zbx_guaranteed_memset(void *v, int c, size_t n)
{
	volatile signed char	*p = (volatile signed char *)v;

	while (0 != n--)
		*p++ = (signed char)c;

	return v;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set process title                                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_setproctitle(const char *fmt, ...)
{
#if defined(HAVE_FUNCTION_SETPROCTITLE) || defined(PS_OVERWRITE_ARGV) || defined(PS_PSTAT_ARGV)
	char	title[MAX_STRING_LEN];
	va_list	args;

	va_start(args, fmt);
	zbx_vsnprintf(title, sizeof(title), fmt, args);
	va_end(args);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() title:'%s'", __func__, title);
#endif

#if defined(HAVE_FUNCTION_SETPROCTITLE)
	setproctitle("%s", title);
#elif defined(PS_OVERWRITE_ARGV) || defined(PS_PSTAT_ARGV)
	setproctitle_set_status(title);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if current time is within given period                      *
 *                                                                            *
 * Parameters: period - [IN] preprocessed time period                         *
 *             tm     - [IN] broken-down time for comparison                  *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the period            *
 *                                                                            *
 ******************************************************************************/
static int	check_time_period(const zbx_time_period_t period, struct tm *tm)
{
	int		day, time;

	day = 0 == tm->tm_wday ? 7 : tm->tm_wday;
	time = SEC_PER_HOUR * tm->tm_hour + SEC_PER_MIN * tm->tm_min + tm->tm_sec;

	return period.start_day <= day && day <= period.end_day && period.start_time <= time && time < period.end_time ?
			SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return delay value that is currently applicable                   *
 *                                                                            *
 * Parameters: default_delay  - [IN] default delay value, can be overridden   *
 *             flex_intervals - [IN] preprocessed flexible intervals          *
 *             now            - [IN] current time                             *
 *                                                                            *
 * Return value: delay value - either default or minimum delay value          *
 *                             out of all applicable intervals                *
 *                                                                            *
 ******************************************************************************/
static int	get_current_delay(int default_delay, const zbx_flexible_interval_t *flex_intervals, time_t now)
{
	int		current_delay = -1;

	while (NULL != flex_intervals)
	{
		if ((-1 == current_delay || flex_intervals->delay < current_delay) &&
				SUCCEED == check_time_period(flex_intervals->period, localtime(&now)))
		{
			current_delay = flex_intervals->delay;
		}

		flex_intervals = flex_intervals->next;
	}

	return -1 == current_delay ? default_delay : current_delay;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return time when next delay settings take effect                  *
 *                                                                            *
 * Parameters: flex_intervals - [IN] preprocessed flexible intervals          *
 *             now            - [IN] current time                             *
 *             next_interval  - [OUT] start of next delay interval            *
 *                                                                            *
 * Return value: SUCCEED - there is a next interval                           *
 *               FAIL - otherwise (in this case, next_interval is unaffected) *
 *                                                                            *
 ******************************************************************************/
static int	get_next_delay_interval(const zbx_flexible_interval_t *flex_intervals, time_t now, time_t *next_interval)
{
	int		day, time, next = 0, candidate;
	struct tm	*tm;

	if (NULL == flex_intervals)
		return FAIL;

	tm = localtime(&now);
	day = 0 == tm->tm_wday ? 7 : tm->tm_wday;
	time = SEC_PER_HOUR * tm->tm_hour + SEC_PER_MIN * tm->tm_min + tm->tm_sec;

	for (; NULL != flex_intervals; flex_intervals = flex_intervals->next)
	{
		const zbx_time_period_t	*p = &flex_intervals->period;

		if (p->start_day <= day && day <= p->end_day && time < p->end_time)	/* will be active today */
		{
			if (time < p->start_time)	/* hasn't been active today yet */
				candidate = p->start_time;
			else	/* currently active */
				candidate = p->end_time;
		}
		else if (day < p->end_day)	/* will be active this week */
		{
			if (day < p->start_day)	/* hasn't been active this week yet */
				candidate = SEC_PER_DAY * (p->start_day - day) + p->start_time;
			else	/* has been active this week and will be active at least once more by the end of it */
				candidate = SEC_PER_DAY + p->start_time;	/* therefore will be active tomorrow */
		}
		else	/* will be active next week */
			candidate = SEC_PER_DAY * (p->start_day + 7 - day) + p->start_time;

		if (0 == next || next > candidate)
			next = candidate;
	}

	if (0 == next)
		return FAIL;

	*next_interval = now - time + next;
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses time of day                                                *
 *                                                                            *
 * Parameters: time       - [OUT] number of seconds since the beginning of    *
 *                            the day corresponding to a given time of day    *
 *             text       - [IN] text to parse                                *
 *             len        - [IN] number of characters available for parsing   *
 *             parsed_len - [OUT] number of characters recognized as time     *
 *                                                                            *
 * Return value: SUCCEED - text was successfully parsed as time of day        *
 *               FAIL    - otherwise (time and parsed_len remain untouched)   *
 *                                                                            *
 * Comments: !!! Don't forget to sync code with PHP !!!                       *
 *           Supported formats are hh:mm, h:mm and 0h:mm; 0 <= hours <= 24;   *
 *           0 <= minutes <= 59; if hours == 24 then minutes must be 0.       *
 *                                                                            *
 ******************************************************************************/
static int	time_parse(int *time, const char *text, int len, int *parsed_len)
{
	const int	old_len = len;
	const char	*ptr;
	int		hours, minutes;

	for (ptr = text; 0 < len && 0 != isdigit(*ptr) && 2 >= ptr - text; len--, ptr++)
		;

	if (SUCCEED != is_uint_n_range(text, ptr - text, &hours, sizeof(hours), 0, 24))
		return FAIL;

	if (0 >= len-- || ':' != *ptr++)
		return FAIL;

	for (text = ptr; 0 < len && 0 != isdigit(*ptr) && 2 >= ptr - text; len--, ptr++)
		;

	if (2 != ptr - text)
		return FAIL;

	if (SUCCEED != is_uint_n_range(text, 2, &minutes, sizeof(minutes), 0, 59))
		return FAIL;

	if (24 == hours && 0 != minutes)
		return FAIL;

	*parsed_len = old_len - len;
	*time = SEC_PER_HOUR * hours + SEC_PER_MIN * minutes;
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses time period                                                *
 *                                                                            *
 * Parameters: period - [OUT] time period structure                           *
 *             text   - [IN] text to parse                                    *
 *             len    - [IN] number of characters available for parsing       *
 *                                                                            *
 * Return value: SUCCEED - text was successfully parsed as time period        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: !!! Don't forget to sync code with PHP !!!                       *
 *           Supported format is d[-d],time-time where 1 <= d <= 7            *
 *                                                                            *
 ******************************************************************************/
static int	time_period_parse(zbx_time_period_t *period, const char *text, int len)
{
	int	parsed_len;

	if (0 >= len-- || '1' > *text || '7' < *text)
		return FAIL;

	period->start_day = *text++ - '0';

	if (0 >= len)
		return FAIL;

	if ('-' == *text)
	{
		text++;
		len--;

		if (0 >= len-- || '1' > *text || '7' < *text)
			return FAIL;

		period->end_day = *text++ - '0';

		if (period->start_day > period->end_day)
			return FAIL;
	}
	else
		period->end_day = period->start_day;

	if (0 >= len-- || ',' != *text++)
		return FAIL;

	if (SUCCEED != time_parse(&period->start_time, text, len, &parsed_len))
		return FAIL;

	text += parsed_len;
	len -= parsed_len;

	if (0 >= len-- || '-' != *text++)
		return FAIL;

	if (SUCCEED != time_parse(&period->end_time, text, len, &parsed_len))
		return FAIL;

	if (period->start_time >= period->end_time)
		return FAIL;

	if (0 != (len - parsed_len))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate time period and check if specified time is within it     *
 *                                                                            *
 * Parameters: period - [IN] semicolon-separated list of time periods in one  *
 *                           of the following formats:                        *
 *                             d1-d2,h1:m1-h2:m2                              *
 *                             or d1,h1:m1-h2:m2                              *
 *             time   - [IN] time to check                                    *
 *             res    - [OUT] check result:                                   *
 *                              SUCCEED - if time is within period            *
 *                              FAIL    - otherwise                           *
 *                                                                            *
 * Return value: validation result (SUCCEED - valid, FAIL - invalid)          *
 *                                                                            *
 * Comments:   !!! Don't forget to sync code with PHP !!!                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_time_period(const char *period, time_t time, const char *tz, int *res)
{
	int			res_total = FAIL;
	const char		*next;
	struct tm		*tm;
	zbx_time_period_t	tp;

	tm = zbx_localtime(&time, tz);

	next = strchr(period, ';');
	while  (SUCCEED == time_period_parse(&tp, period, (NULL == next ? (int)strlen(period) : (int)(next - period))))
	{
		if (SUCCEED == check_time_period(tp, tm))
			res_total = SUCCEED;	/* no short-circuits, validate all periods before return */

		if (NULL == next)
		{
			*res = res_total;
			return SUCCEED;
		}

		period = next + 1;
		next = strchr(period, ';');
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees flexible interval                                           *
 *                                                                            *
 * Parameters: interval - [IN] flexible interval                              *
 *                                                                            *
 ******************************************************************************/
static void	flexible_interval_free(zbx_flexible_interval_t *interval)
{
	zbx_flexible_interval_t	*interval_next;

	for (; NULL != interval; interval = interval_next)
	{
		interval_next = interval->next;
		zbx_free(interval);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses flexible interval                                          *
 *                                                                            *
 * Parameters: interval - [IN/OUT] the first interval                         *
 *             text     - [IN] the text to parse                              *
 *             len      - [IN] the text length                                *
 *                                                                            *
 * Return value: SUCCEED - the interval was successfully parsed               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: !!! Don't forget to sync code with PHP !!!                       *
 *           Supported format is delay/period                                 *
 *                                                                            *
 ******************************************************************************/
static int	flexible_interval_parse(zbx_flexible_interval_t *interval, const char *text, int len)
{
	const char	*ptr;

	for (ptr = text; 0 < len && '\0' != *ptr && '/' != *ptr; len--, ptr++)
		;

	if (SUCCEED != is_time_suffix(text, &interval->delay, (int)(ptr - text)))
		return FAIL;

	if (0 >= len-- || '/' != *ptr++)
		return FAIL;

	return time_period_parse(&interval->period, ptr, len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates day of week                                            *
 *                                                                            *
 * Parameters: year - [IN] the year (>1752)                                   *
 *             mon  - [IN] the month (1-12)                                   *
 *             mday - [IN] the month day (1-31)                               *
 *                                                                            *
 * Return value: The day of week: 1 - Monday, 2 - Tuesday, ...                *
 *                                                                            *
 ******************************************************************************/
static int	calculate_dayofweek(int year, int mon, int mday)
{
	static int	mon_table[] = {0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4};

	if (mon < 3)
		year--;

	return (year + year / 4 - year / 100 + year / 400 + mon_table[mon - 1] + mday - 1) % 7 + 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees scheduler interval filter                                   *
 *                                                                            *
 * Parameters: filter - [IN] scheduler interval filter                        *
 *                                                                            *
 ******************************************************************************/
static void	scheduler_filter_free(zbx_scheduler_filter_t *filter)
{
	zbx_scheduler_filter_t	*filter_next;

	for (; NULL != filter; filter = filter_next)
	{
		filter_next = filter->next;
		zbx_free(filter);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees scheduler interval                                          *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *                                                                            *
 ******************************************************************************/
static void	scheduler_interval_free(zbx_scheduler_interval_t *interval)
{
	zbx_scheduler_interval_t	*interval_next;

	for (; NULL != interval; interval = interval_next)
	{
		interval_next = interval->next;

		scheduler_filter_free(interval->mdays);
		scheduler_filter_free(interval->wdays);
		scheduler_filter_free(interval->hours);
		scheduler_filter_free(interval->minutes);
		scheduler_filter_free(interval->seconds);

		zbx_free(interval);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses text string into scheduler filter                          *
 *                                                                            *
 * Parameters: filter  - [IN/OUT] the first filter                            *
 *             text    - [IN] the text to parse                               *
 *             len     - [IN/OUT] the number of characters left to parse      *
 *             min     - [IN] the minimal time unit value                     *
 *             max     - [IN] the maximal time unit value                     *
 *             var_len - [IN] the maximum number of characters for a filter   *
 *                       variable (<from>, <to>, <step>)                      *
 *                                                                            *
 * Return value: SUCCEED - the filter was successfully parsed                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function recursively calls itself for each filter fragment. *
 *                                                                            *
 ******************************************************************************/
static int	scheduler_parse_filter_r(zbx_scheduler_filter_t **filter, const char *text, int *len, int min, int max,
		int var_len)
{
	int			start = 0, end = 0, step = 1;
	const char		*pstart, *pend;
	zbx_scheduler_filter_t	*filter_new;

	pstart = pend = text;
	while (0 != isdigit(*pend) && 0 < *len)
	{
		pend++;
		(*len)--;
	}

	if (pend != pstart)
	{
		if (pend - pstart > var_len)
			return FAIL;

		if (SUCCEED != is_uint_n_range(pstart, pend - pstart, &start, sizeof(start), min, max))
			return FAIL;

		if ('-' == *pend)
		{
			pstart = pend + 1;

			do
			{
				pend++;
				(*len)--;
			}
			while (0 != isdigit(*pend) && 0 < *len);

			/* empty or too long value, fail */
			if (pend == pstart || pend - pstart > var_len)
				return FAIL;

			if (SUCCEED != is_uint_n_range(pstart, pend - pstart, &end, sizeof(end), min, max))
				return FAIL;

			if (end < start)
				return FAIL;
		}
		else
		{
			/* step is valid only for defined range */
			if ('/' == *pend)
				return FAIL;

			end = start;
		}
	}
	else
	{
		start = min;
		end = max;
	}

	if ('/' == *pend)
	{
		pstart = pend + 1;

		do
		{
			pend++;
			(*len)--;
		}
		while (0 != isdigit(*pend) && 0 < *len);

		/* empty or too long step, fail */
		if (pend == pstart || pend - pstart > var_len)
			return FAIL;

		if (SUCCEED != is_uint_n_range(pstart, pend - pstart, &step, sizeof(step), 1, end - start))
			return FAIL;
	}
	else
	{
		if (pend == text)
			return FAIL;
	}

	if (',' == *pend)
	{
		/* no next filter after ',' */
		if (0 == --(*len))
			return FAIL;

		pend++;

		if (SUCCEED != scheduler_parse_filter_r(filter, pend, len, min, max, var_len))
			return FAIL;
	}

	filter_new = (zbx_scheduler_filter_t *)zbx_malloc(NULL, sizeof(zbx_scheduler_filter_t));
	filter_new->start = start;
	filter_new->end = end;
	filter_new->step = step;
	filter_new->next = *filter;
	*filter = filter_new;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses text string into scheduler filter                          *
 *                                                                            *
 * Parameters: filter  - [IN/OUT] the first filter                            *
 *             text    - [IN] the text to parse                               *
 *             len     - [IN/OUT] the number of characters left to parse      *
 *             min     - [IN] the minimal time unit value                     *
 *             max     - [IN] the maximal time unit value                     *
 *             var_len - [IN] the maximum number of characters for a filter   *
 *                       variable (<from>, <to>, <step>)                      *
 *                                                                            *
 * Return value: SUCCEED - the filter was successfully parsed                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function will fail if a filter already exists. This         *
 *           user from defining multiple filters of the same time unit in a   *
 *           single interval. For example: h0h12 is invalid filter and its    *
 *           parsing must fail.                                               *
 *                                                                            *
 ******************************************************************************/
static int	scheduler_parse_filter(zbx_scheduler_filter_t **filter, const char *text, int *len, int min, int max,
		int var_len)
{
	if (NULL != *filter)
		return FAIL;

	return scheduler_parse_filter_r(filter, text, len, min, max, var_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses scheduler interval                                         *
 *                                                                            *
 * Parameters: interval - [IN/OUT] the first interval                         *
 *             text     - [IN] the text to parse                              *
 *             len      - [IN] the text length                                *
 *                                                                            *
 * Return value: SUCCEED - the interval was successfully parsed               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	scheduler_interval_parse(zbx_scheduler_interval_t *interval, const char *text, int len)
{
	int	ret = SUCCEED;

	if (0 == len)
		return FAIL;

	while (SUCCEED == ret && 0 != len)
	{
		int	old_len = len--;

		switch (*text)
		{
			case '\0':
				return FAIL;
			case 'h':
				if (ZBX_SCHEDULER_FILTER_HOUR < interval->filter_level)
					return FAIL;

				ret = scheduler_parse_filter(&interval->hours, text + 1, &len, 0, 23, 2);
				interval->filter_level = ZBX_SCHEDULER_FILTER_HOUR;

				break;
			case 's':
				if (ZBX_SCHEDULER_FILTER_SECOND < interval->filter_level)
					return FAIL;

				ret = scheduler_parse_filter(&interval->seconds, text + 1, &len, 0, 59, 2);
				interval->filter_level = ZBX_SCHEDULER_FILTER_SECOND;

				break;
			case 'w':
				if ('d' != text[1])
					return FAIL;

				if (ZBX_SCHEDULER_FILTER_DAY < interval->filter_level)
					return FAIL;

				len--;
				ret = scheduler_parse_filter(&interval->wdays, text + 2, &len, 1, 7, 1);
				interval->filter_level = ZBX_SCHEDULER_FILTER_DAY;

				break;
			case 'm':
				if ('d' == text[1])
				{
					if (ZBX_SCHEDULER_FILTER_DAY < interval->filter_level ||
							NULL != interval->wdays)
					{
						return FAIL;
					}

					len--;
					ret = scheduler_parse_filter(&interval->mdays, text + 2, &len, 1, 31, 2);
					interval->filter_level = ZBX_SCHEDULER_FILTER_DAY;
				}
				else
				{
					if (ZBX_SCHEDULER_FILTER_MINUTE < interval->filter_level)
						return FAIL;

					ret = scheduler_parse_filter(&interval->minutes, text + 1, &len, 0, 59, 2);
					interval->filter_level = ZBX_SCHEDULER_FILTER_MINUTE;
				}

				break;
			default:
				return FAIL;
		}

		text += old_len - len;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the next nearest value that satisfies the filter chain       *
 *                                                                            *
 * Parameters: filter - [IN] the filter chain                                 *
 *             value  - [IN] the current value                                *
 *                      [OUT] the next nearest value (>= than input value)    *
 *                                                                            *
 * Return value: SUCCEED - the next nearest value was successfully found      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	scheduler_get_nearest_filter_value(const zbx_scheduler_filter_t *filter, int *value)
{
	const zbx_scheduler_filter_t	*filter_next = NULL;

	for (; NULL != filter; filter = filter->next)
	{
		/* find matching filter */
		if (filter->start <= *value && *value <= filter->end)
		{
			int	next = *value, offset;

			/* apply step */
			offset = (next - filter->start) % filter->step;
			if (0 != offset)
				next += filter->step - offset;

			/* succeed if the calculated value is still in filter range */
			if (next <= filter->end)
			{
				*value = next;
				return SUCCEED;
			}
		}

		/* find the next nearest filter */
		if (filter->start > *value && (NULL == filter_next || filter_next->start > filter->start))
			filter_next = filter;
	}

	/* The value is not in a range of any filters, but we have next nearest filter. */
	if (NULL != filter_next)
	{
		*value = filter_next->start;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates the next day that satisfies the week day filter        *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             tm       - [IN/OUT] the input/output date & time               *
 *                                                                            *
 * Return value: SUCCEED - the next day was found                             *
 *               FAIL    - the next day satisfying week day filter was not    *
 *                         found in the current month                         *
 *                                                                            *
 ******************************************************************************/
static int	scheduler_get_wday_nextcheck(const zbx_scheduler_interval_t *interval, struct tm *tm)
{
	int	value_now, value_next;

	if (NULL == interval->wdays)
		return SUCCEED;

	value_now = value_next = calculate_dayofweek(tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday);

	/* get the nearest week day from the current week day*/
	if (SUCCEED != scheduler_get_nearest_filter_value(interval->wdays, &value_next))
	{
		/* in the case of failure move month day to the next week, reset week day and try again */
		tm->tm_mday += 7 - value_now + 1;
		value_now = value_next = 1;

		if (SUCCEED != scheduler_get_nearest_filter_value(interval->wdays, &value_next))
		{
			/* a valid week day filter must always match some day of a new week */
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}

	/* adjust the month day by the week day offset */
	tm->tm_mday += value_next - value_now;

	/* check if the resulting month day is valid */
	return (tm->tm_mday <= zbx_day_in_month(tm->tm_year + 1970, tm->tm_mon + 1) ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the specified date satisfies week day filter            *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             tm       - [IN] the date & time to validate                    *
 *                                                                            *
 * Return value: SUCCEED - the input date satisfies week day filter           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	scheduler_validate_wday_filter(const zbx_scheduler_interval_t *interval, struct tm *tm)
{
	const zbx_scheduler_filter_t	*filter;
	int				value;

	if (NULL == interval->wdays)
		return SUCCEED;

	value = calculate_dayofweek(tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday);

	/* check if the value match week day filter */
	for (filter = interval->wdays; NULL != filter; filter = filter->next)
	{
		if (filter->start <= value && value <= filter->end)
		{
			int	next = value, offset;

			/* apply step */
			offset = (next - filter->start) % filter->step;
			if (0 != offset)
				next += filter->step - offset;

			/* succeed if the calculated value is still in filter range */
			if (next <= filter->end)
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates the next day that satisfies month and week day filters *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             tm       - [IN/OUT] the input/output date & time               *
 *                                                                            *
 * Return value: SUCCEED - the next day was found                             *
 *               FAIL    - the next day satisfying day filters was not        *
 *                         found in the current month                         *
 *                                                                            *
 ******************************************************************************/
static int	scheduler_get_day_nextcheck(const zbx_scheduler_interval_t *interval, struct tm *tm)
{
	int	tmp;

	/* first check if the provided tm structure has valid date format */
	if (FAIL == zbx_utc_time(tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday, tm->tm_hour, tm->tm_min, tm->tm_sec,
			&tmp))
	{
		return FAIL;
	}

	if (NULL == interval->mdays)
		return scheduler_get_wday_nextcheck(interval, tm);

	/* iterate through month days until week day filter matches or we have run out of month days */
	while (SUCCEED == scheduler_get_nearest_filter_value(interval->mdays, &tm->tm_mday))
	{
		/* check if the date is still valid - we haven't run out of month days */
		if (tm->tm_mday > zbx_day_in_month(tm->tm_year + 1970, tm->tm_mon + 1))
			break;

		if (SUCCEED == scheduler_validate_wday_filter(interval, tm))
			return SUCCEED;

		tm->tm_mday++;

		/* check if the date is still valid - we haven't run out of month days */
		if (tm->tm_mday > zbx_day_in_month(tm->tm_year + 1970, tm->tm_mon + 1))
			break;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates the time/day that satisfies the specified filter       *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             level    - [IN] the filter level, see ZBX_SCHEDULER_FILTER_*   *
 *                        defines                                             *
 *             tm       - [IN/OUT] the input/output date & time               *
 *                                                                            *
 * Return value: SUCCEED - the next time/day was found                        *
 *               FAIL    - the next time/day was not found on the current     *
 *                         filter level                                       *
 *                                                                            *
 ******************************************************************************/
static int	scheduler_get_filter_nextcheck(const zbx_scheduler_interval_t *interval, int level, struct tm *tm)
{
	const zbx_scheduler_filter_t	*filter;
	int				max, *value;

	/* initialize data depending on filter level */
	switch (level)
	{
		case ZBX_SCHEDULER_FILTER_DAY:
			return scheduler_get_day_nextcheck(interval, tm);
		case ZBX_SCHEDULER_FILTER_HOUR:
			max = 23;
			filter = interval->hours;
			value = &tm->tm_hour;
			break;
		case ZBX_SCHEDULER_FILTER_MINUTE:
			max = 59;
			filter = interval->minutes;
			value = &tm->tm_min;
			break;
		case ZBX_SCHEDULER_FILTER_SECOND:
			max = 59;
			filter = interval->seconds;
			value = &tm->tm_sec;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	if (max < *value)
		return FAIL;

	/* handle unspecified (default) filter */
	if (NULL == filter)
	{
		/* Empty filter matches all valid values if the filter level is less than        */
		/* interval filter level. For example if interval filter level is minutes - m30, */
		/* then hour filter matches all hours.                                           */
		if (interval->filter_level > level)
			return SUCCEED;

		/* If the filter level is greater than interval filter level, then filter       */
		/* matches only 0 value. For example if interval filter level is minutes - m30, */
		/* then seconds filter matches the 0th second.                                  */
		return 0 == *value ? SUCCEED : FAIL;
	}

	return scheduler_get_nearest_filter_value(filter, value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: applies day filter to the specified time/day calculating the next *
 *          scheduled check                                                   *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             tm       - [IN/OUT] the input/output date & time               *
 *                                                                            *
 ******************************************************************************/
static void	scheduler_apply_day_filter(zbx_scheduler_interval_t *interval, struct tm *tm)
{
	int	day = tm->tm_mday, mon = tm->tm_mon, year = tm->tm_year;

	while (SUCCEED != scheduler_get_filter_nextcheck(interval, ZBX_SCHEDULER_FILTER_DAY, tm))
	{
		if (11 < ++tm->tm_mon)
		{
			tm->tm_mon = 0;
			tm->tm_year++;
		}

		tm->tm_mday = 1;
	}

	/* reset hours, minutes and seconds if the day has been changed */
	if (tm->tm_mday != day || tm->tm_mon != mon || tm->tm_year != year)
	{
		tm->tm_hour = 0;
		tm->tm_min = 0;
		tm->tm_sec = 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: applies hour filter to the specified time/day calculating the     *
 *          next scheduled check                                              *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             tm       - [IN/OUT] the input/output date & time               *
 *                                                                            *
 ******************************************************************************/
static void	scheduler_apply_hour_filter(zbx_scheduler_interval_t *interval, struct tm *tm)
{
	int	hour = tm->tm_hour;

	while (SUCCEED != scheduler_get_filter_nextcheck(interval, ZBX_SCHEDULER_FILTER_HOUR, tm))
	{
		tm->tm_mday++;
		tm->tm_hour = 0;

		/* day has been changed, we have to reapply day filter */
		scheduler_apply_day_filter(interval, tm);
	}

	/* reset minutes and seconds if hours has been changed */
	if (tm->tm_hour != hour)
	{
		tm->tm_min = 0;
		tm->tm_sec = 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: applies minute filter to the specified time/day calculating the   *
 *          next scheduled check                                              *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             tm       - [IN/OUT] the input/output date & time               *
 *                                                                            *
 ******************************************************************************/
static void	scheduler_apply_minute_filter(zbx_scheduler_interval_t *interval, struct tm *tm)
{
	int	min = tm->tm_min;

	while (SUCCEED != scheduler_get_filter_nextcheck(interval, ZBX_SCHEDULER_FILTER_MINUTE, tm))
	{
		tm->tm_hour++;
		tm->tm_min = 0;

		/* hours have been changed, we have to reapply hour filter */
		scheduler_apply_hour_filter(interval, tm);
	}

	/* reset seconds if minutes has been changed */
	if (tm->tm_min != min)
		tm->tm_sec = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: applies second filter to the specified time/day calculating the   *
 *          next scheduled check                                              *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             tm       - [IN/OUT] the input/output date & time               *
 *                                                                            *
 ******************************************************************************/
static void	scheduler_apply_second_filter(zbx_scheduler_interval_t *interval, struct tm *tm)
{
	while (SUCCEED != scheduler_get_filter_nextcheck(interval, ZBX_SCHEDULER_FILTER_SECOND, tm))
	{
		tm->tm_min++;
		tm->tm_sec = 0;

		/* minutes have been changed, we have to reapply minute filter */
		scheduler_apply_minute_filter(interval, tm);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: finds daylight saving change time inside specified time period    *
 *                                                                            *
 * Parameters: time_start - [IN] the time period start                        *
 *             time_end   - [IN] the time period end                          *
 *                                                                            *
 * Return Value: Time when the daylight saving changes should occur.          *
 *                                                                            *
 * Comments: The calculated time is cached and reused if it first the         *
 *           specified period.                                                *
 *                                                                            *
 ******************************************************************************/
static time_t	scheduler_find_dst_change(time_t time_start, time_t time_end)
{
	static time_t	time_dst = 0;
	struct tm	*tm;
	time_t		time_mid;
	int		start, end, mid, dst_start;

	if (time_dst < time_start || time_dst > time_end)
	{
		/* assume that daylight saving will change only on 0 seconds */
		start = time_start / 60;
		end = time_end / 60;

		tm = localtime(&time_start);
		dst_start = tm->tm_isdst;

		while (end > start + 1)
		{
			mid = (start + end) / 2;
			time_mid = mid * 60;

			tm = localtime(&time_mid);

			if (tm->tm_isdst == dst_start)
				start = mid;
			else
				end = mid;
		}

		time_dst = end * 60;
	}

	return time_dst;
}

/******************************************************************************
 *                                                                            *
 * Purpose: increment struct tm value by one second                           *
 *                                                                            *
 * Parameters: tm - [IN/OUT] the tm structure to increment                    *
 *                                                                            *
 ******************************************************************************/
static void	scheduler_tm_inc(struct tm *tm)
{
	if (60 > ++tm->tm_sec)
		return;

	tm->tm_sec = 0;
	if (60 > ++tm->tm_min)
		return;

	tm->tm_min = 0;
	if (24 > ++tm->tm_hour)
		return;

	tm->tm_hour = 0;
	if (zbx_day_in_month(tm->tm_year + 1900, tm->tm_mon + 1) >= ++tm->tm_mday)
		return;

	tm->tm_mday = 1;
	if (12 > ++tm->tm_mon)
		return;

	tm->tm_mon = 0;
	tm->tm_year++;
	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: finds the next timestamp satisfying one of intervals.             *
 *                                                                            *
 * Parameters: interval - [IN] the scheduler interval                         *
 *             now      - [IN] the current timestamp                          *
 *                                                                            *
 * Return Value: Timestamp when the next check must be scheduled.             *
 *                                                                            *
 ******************************************************************************/
static time_t	scheduler_get_nextcheck(zbx_scheduler_interval_t *interval, time_t now)
{
	struct tm	tm_start, tm, tm_dst;
	time_t		nextcheck = 0, current_nextcheck;

	tm_start = *(localtime(&now));

	for (; NULL != interval; interval = interval->next)
	{
		tm = tm_start;

		do
		{
			scheduler_tm_inc(&tm);
			scheduler_apply_day_filter(interval, &tm);
			scheduler_apply_hour_filter(interval, &tm);
			scheduler_apply_minute_filter(interval, &tm);
			scheduler_apply_second_filter(interval, &tm);

			tm.tm_isdst = tm_start.tm_isdst;
		}
		while (-1 == (current_nextcheck = mktime(&tm)));

		tm_dst = *(localtime(&current_nextcheck));
		if (tm_dst.tm_isdst != tm_start.tm_isdst)
		{
			int	dst = tm_dst.tm_isdst;
			time_t	time_dst;

			time_dst = scheduler_find_dst_change(now, current_nextcheck);
			tm_dst = *localtime(&time_dst);

			scheduler_apply_day_filter(interval, &tm_dst);
			scheduler_apply_hour_filter(interval, &tm_dst);
			scheduler_apply_minute_filter(interval, &tm_dst);
			scheduler_apply_second_filter(interval, &tm_dst);

			tm_dst.tm_isdst = dst;
			current_nextcheck = mktime(&tm_dst);
		}

		if (0 == nextcheck || current_nextcheck < nextcheck)
			nextcheck = current_nextcheck;
	}

	return nextcheck;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses user macro and finds it's length                           *
 *                                                                            *
 * Parameters: str  - [IN] string to check                                    *
 *             len  - [OUT] length of macro                                   *
 *                                                                            *
 * Return Value:                                                              *
 *     SUCCEED - the macro was parsed successfully.                           *
 *     FAIL    - the macro parsing failed, the content of output variables    *
 *               is not defined.                                              *
 *                                                                            *
 ******************************************************************************/
static int	parse_user_macro(const char *str, int *len)
{
	int	macro_r, context_l, context_r;

	if ('{' != *str || '$' != *(str + 1) || SUCCEED != zbx_user_macro_parse(str, &macro_r, &context_l, &context_r,
			NULL))
	{
		return FAIL;
	}

	*len = macro_r + 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses user macro and finds it's length                           *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             len   - [OUT] length simple interval string until separator    *
 *             sep   - [IN] separator to calculate length                     *
 *             value - [OUT] interval value                                   *
 *                                                                            *
 * Return Value:                                                              *
 *     SUCCEED - the macro was parsed successfully.                           *
 *     FAIL    - the macro parsing failed, the content of output variables    *
 *               is not defined.                                              *
 *                                                                            *
 ******************************************************************************/
static int	parse_simple_interval(const char *str, int *len, char sep, int *value)
{
	const char	*delim;

	if (SUCCEED != is_time_suffix(str, value,
			(int)(NULL == (delim = strchr(str, sep)) ? ZBX_LENGTH_UNLIMITED : delim - str)))
	{
		return FAIL;
	}

	*len = NULL == delim ? (int)strlen(str) : delim - str;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate update interval, flexible and scheduling intervals       *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             error - [OUT] validation error                                 *
 *                                                                            *
 * Return Value:                                                              *
 *     SUCCEED - parsed successfully.                                         *
 *     FAIL    - parsing failed.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_validate_interval(const char *str, char **error)
{
	int		simple_interval, interval, len, custom = 0, macro;
	const char	*delim;

	if (SUCCEED == parse_user_macro(str, &len) && ('\0' == *(delim = str + len) || ';' == *delim))
	{
		if ('\0' == *delim)
			delim = NULL;

		simple_interval = 1;
	}
	else if (SUCCEED == parse_simple_interval(str, &len, ';', &simple_interval))
	{
		if ('\0' == *(delim = str + len))
			delim = NULL;
	}
	else
	{
		*error = zbx_dsprintf(*error, "Invalid update interval \"%.*s\".",
				NULL == (delim = strchr(str, ';')) ? (int)strlen(str) : (int)(delim - str), str);
		return FAIL;
	}

	while (NULL != delim)
	{
		str = delim + 1;

		if ((SUCCEED == (macro = parse_user_macro(str, &len)) ||
				SUCCEED == parse_simple_interval(str, &len, '/', &interval)) &&
				'/' == *(delim = str + len))
		{
			zbx_time_period_t period;

			custom = 1;

			if (SUCCEED == macro)
				interval = 1;

			if (0 == interval && 0 == simple_interval)
			{
				*error = zbx_dsprintf(*error, "Invalid flexible interval \"%.*s\".", (int)(delim - str),
						str);
				return FAIL;
			}

			str = delim + 1;

			if (SUCCEED == parse_user_macro(str, &len) && ('\0' == *(delim = str + len) || ';' == *delim))
			{
				if ('\0' == *delim)
					delim = NULL;

				continue;
			}

			if (SUCCEED == time_period_parse(&period, str,
					NULL == (delim = strchr(str, ';')) ? (int)strlen(str) : (int)(delim - str)))
			{
				continue;
			}

			*error = zbx_dsprintf(*error, "Invalid flexible period \"%.*s\".",
					NULL == delim ? (int)strlen(str) : (int)(delim - str), str);
			return FAIL;
		}
		else
		{
			zbx_scheduler_interval_t	*new_interval;

			custom = 1;

			if (SUCCEED == macro && ('\0' == *(delim = str + len) || ';' == *delim))
			{
				if ('\0' == *delim)
					delim = NULL;

				continue;
			}

			new_interval = (zbx_scheduler_interval_t *)zbx_malloc(NULL, sizeof(zbx_scheduler_interval_t));
			memset(new_interval, 0, sizeof(zbx_scheduler_interval_t));

			if (SUCCEED == scheduler_interval_parse(new_interval, str,
					NULL == (delim = strchr(str, ';')) ? (int)strlen(str) : (int)(delim - str)))
			{
				scheduler_interval_free(new_interval);
				continue;
			}
			scheduler_interval_free(new_interval);

			*error = zbx_dsprintf(*error, "Invalid custom interval \"%.*s\".",
					NULL == delim ? (int)strlen(str) : (int)(delim - str), str);

			return FAIL;
		}
	}

	if ((0 == custom && 0 == simple_interval) || SEC_PER_DAY < simple_interval)
	{
		*error = zbx_dsprintf(*error, "Invalid update interval \"%d\"", simple_interval);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses item and low-level discovery rule update intervals         *
 *                                                                            *
 * Parameters: interval_str     - [IN] update interval string to parse        *
 *             simple_interval  - [OUT] simple update interval                *
 *             custom_intervals - [OUT] flexible and scheduling intervals     *
 *             error            - [OUT] error message                         *
 *                                                                            *
 * Return value: SUCCEED - intervals are valid                                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: !!! Don't forget to sync code with PHP !!!                       *
 *           Supported format:                                                *
 *             SimpleInterval, {";", FlexibleInterval | SchedulingInterval};  *
 *                                                                            *
 ******************************************************************************/
int	zbx_interval_preproc(const char *interval_str, int *simple_interval, zbx_custom_interval_t **custom_intervals,
		char **error)
{
	zbx_flexible_interval_t		*flexible = NULL;
	zbx_scheduler_interval_t	*scheduling = NULL;
	const char			*delim, *interval_type;

	if (SUCCEED != is_time_suffix(interval_str, simple_interval,
			(int)(NULL == (delim = strchr(interval_str, ';')) ? ZBX_LENGTH_UNLIMITED : delim - interval_str)))
	{
		interval_type = "update";
		goto fail;
	}

	if (NULL == custom_intervals)	/* caller wasn't interested in custom intervals, don't parse them */
		return SUCCEED;

	while (NULL != delim)
	{
		interval_str = delim + 1;
		delim = strchr(interval_str, ';');

		if (0 != isdigit(*interval_str))
		{
			zbx_flexible_interval_t	*new_interval;

			new_interval = (zbx_flexible_interval_t *)zbx_malloc(NULL, sizeof(zbx_flexible_interval_t));

			if (SUCCEED != flexible_interval_parse(new_interval, interval_str,
					(NULL == delim ? (int)strlen(interval_str) : (int)(delim - interval_str))) ||
					(0 == *simple_interval && 0 == new_interval->delay))
			{
				zbx_free(new_interval);
				interval_type = "flexible";
				goto fail;
			}

			new_interval->next = flexible;
			flexible = new_interval;
		}
		else
		{
			zbx_scheduler_interval_t	*new_interval;

			new_interval = (zbx_scheduler_interval_t *)zbx_malloc(NULL, sizeof(zbx_scheduler_interval_t));
			memset(new_interval, 0, sizeof(zbx_scheduler_interval_t));

			if (SUCCEED != scheduler_interval_parse(new_interval, interval_str,
					(NULL == delim ? (int)strlen(interval_str) : (int)(delim - interval_str))))
			{
				scheduler_interval_free(new_interval);
				interval_type = "scheduling";
				goto fail;
			}

			new_interval->next = scheduling;
			scheduling = new_interval;
		}
	}

	if ((NULL == flexible && NULL == scheduling && 0 == *simple_interval) || SEC_PER_DAY < *simple_interval)
	{
		interval_type = "update";
		goto fail;
	}

	*custom_intervals = (zbx_custom_interval_t *)zbx_malloc(NULL, sizeof(zbx_custom_interval_t));
	(*custom_intervals)->flexible = flexible;
	(*custom_intervals)->scheduling = scheduling;

	return SUCCEED;
fail:
	if (NULL != error)
	{
		*error = zbx_dsprintf(*error, "Invalid %s interval \"%.*s\".", interval_type,
				(NULL == delim ? (int)strlen(interval_str) : (int)(delim - interval_str)),
				interval_str);
	}

	flexible_interval_free(flexible);
	scheduler_interval_free(scheduling);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if custom interval contains scheduling interval             *
 *                                                                            *
 * Parameters: custom_intervals - [IN] custom intervals                       *
 *                                                                            *
 * Return value: SUCCEED if custom interval contains scheduling interval      *
 *               FAIL otherwise                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_custom_interval_is_scheduling(const zbx_custom_interval_t *custom_intervals)
{
	return NULL == custom_intervals->scheduling ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees custom update intervals                                     *
 *                                                                            *
 * Parameters: custom_intervals - [IN] custom intervals                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_custom_interval_free(zbx_custom_interval_t *custom_intervals)
{
	flexible_interval_free(custom_intervals->flexible);
	scheduler_interval_free(custom_intervals->scheduling);
	zbx_free(custom_intervals);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate nextcheck timestamp for item                            *
 *                                                                            *
 * Parameters: seed             - [IN] the seed value applied to delay to     *
 *                                     spread item checks over the delay      *
 *                                     period                                 *
 *             item_type        - [IN] the item type                          *
 *             simple_interval  - [IN] default delay value, can be overridden *
 *             custom_intervals - [IN] preprocessed custom intervals          *
 *             now              - [IN] current timestamp                      *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 * Comments: if item check is forbidden with delay=0 (default and flexible),  *
 *           a timestamp very far in the future is returned                   *
 *                                                                            *
 *           Old algorithm: now+delay                                         *
 *           New one: preserve period, if delay==5, nextcheck = 0,5,10,15,... *
 *                                                                            *
 ******************************************************************************/
int	calculate_item_nextcheck(zbx_uint64_t seed, int item_type, int simple_interval,
		const zbx_custom_interval_t *custom_intervals, time_t now)
{
	int	nextcheck = 0;

	/* special processing of active items to see better view in queue */
	if (ITEM_TYPE_ZABBIX_ACTIVE == item_type)
	{
		if (0 != simple_interval)
			nextcheck = (int)now + simple_interval;
		else
			nextcheck = ZBX_JAN_2038;
	}
	else
	{
		int	current_delay, attempt = 0;
		time_t	next_interval, t, tmax, scheduled_check = 0;

		/* first try to parse out and calculate scheduled intervals */
		if (NULL != custom_intervals)
			scheduled_check = scheduler_get_nextcheck(custom_intervals->scheduling, now);

		/* Try to find the nearest 'nextcheck' value with condition */
		/* 'now' < 'nextcheck' < 'now' + SEC_PER_YEAR. If it is not */
		/* possible to check the item within a year, fail. */

		t = now;
		tmax = now + SEC_PER_YEAR;

		while (t < tmax)
		{
			/* calculate 'nextcheck' value for the current interval */
			if (NULL != custom_intervals)
				current_delay = get_current_delay(simple_interval, custom_intervals->flexible, t);
			else
				current_delay = simple_interval;

			if (0 != current_delay)
			{
				nextcheck = current_delay * (int)(t / (time_t)current_delay) +
						(int)(seed % (zbx_uint64_t)current_delay);

				if (0 == attempt)
				{
					while (nextcheck <= t)
						nextcheck += current_delay;
				}
				else
				{
					while (nextcheck < t)
						nextcheck += current_delay;
				}
			}
			else
				nextcheck = ZBX_JAN_2038;

			if (NULL == custom_intervals)
				break;

			/* 'nextcheck' < end of the current interval ? */
			/* the end of the current interval is the beginning of the next interval - 1 */
			if (FAIL != get_next_delay_interval(custom_intervals->flexible, t, &next_interval) &&
					nextcheck >= next_interval)
			{
				/* 'nextcheck' is beyond the current interval */
				t = next_interval;
				attempt++;
			}
			else
				break;	/* nextcheck is within the current interval */
		}

		if (0 != scheduled_check && scheduled_check < nextcheck)
			nextcheck = (int)scheduled_check;
	}

	return nextcheck;
}
/******************************************************************************
 *                                                                            *
 * Purpose: calculate nextcheck timestamp for item on unreachable host        *
 *                                                                            *
 * Parameters: simple_interval  - [IN] default delay value, can be overridden *
 *             custom_intervals - [IN] preprocessed custom intervals          *
 *             disable_until    - [IN] timestamp for next check               *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 ******************************************************************************/
int	calculate_item_nextcheck_unreachable(int simple_interval, const zbx_custom_interval_t *custom_intervals,
		time_t disable_until)
{
	int	nextcheck = 0;
	time_t	next_interval, tmax, scheduled_check = 0;

	/* first try to parse out and calculate scheduled intervals */
	if (NULL != custom_intervals)
		scheduled_check = scheduler_get_nextcheck(custom_intervals->scheduling, disable_until);

	/* Try to find the nearest 'nextcheck' value with condition */
	/* 'now' < 'nextcheck' < 'now' + SEC_PER_YEAR. If it is not */
	/* possible to check the item within a year, fail. */

	nextcheck = disable_until;
	tmax = disable_until + SEC_PER_YEAR;

	if (NULL != custom_intervals)
	{
		while (nextcheck < tmax)
		{
			if (0 != get_current_delay(simple_interval, custom_intervals->flexible, nextcheck))
				break;

			/* find the flexible interval change */
			if (FAIL == get_next_delay_interval(custom_intervals->flexible, nextcheck, &next_interval))
			{
				nextcheck = ZBX_JAN_2038;
				break;
			}
			nextcheck = next_interval;
		}
	}

	if (0 != scheduled_check && scheduled_check < nextcheck)
		nextcheck = (int)scheduled_check;

	return nextcheck;
}
/******************************************************************************
 *                                                                            *
 * Purpose: calculate nextcheck timestamp for passive proxy                   *
 *                                                                            *
 * Parameters: hostid - [IN] host identifier from database                    *
 *             delay  - [IN] default delay value, can be overridden           *
 *             now    - [IN] current timestamp                                *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 ******************************************************************************/
time_t	calculate_proxy_nextcheck(zbx_uint64_t hostid, unsigned int delay, time_t now)
{
	time_t	nextcheck;

	nextcheck = delay * (now / delay) + (unsigned int)(hostid % delay);

	while (nextcheck <= now)
		nextcheck += delay;

	return nextcheck;
}

/******************************************************************************
 *                                                                            *
 * Purpose: is string IPv4 address                                            *
 *                                                                            *
 * Parameters: ip - string                                                    *
 *                                                                            *
 * Return value: SUCCEED - is IPv4 address                                    *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	is_ip4(const char *ip)
{
	const char	*p = ip;
	int		digits = 0, dots = 0, res = FAIL, octet = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __func__, ip);

	while ('\0' != *p)
	{
		if (0 != isdigit(*p))
		{
			octet = octet * 10 + (*p - '0');
			digits++;
		}
		else if ('.' == *p)
		{
			if (0 == digits || 3 < digits || 255 < octet)
				break;

			digits = 0;
			octet = 0;
			dots++;
		}
		else
		{
			digits = 0;
			break;
		}

		p++;
	}
	if (3 == dots && 1 <= digits && 3 >= digits && 255 >= octet)
		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: is string IPv6 address                                            *
 *                                                                            *
 * Parameters: ip - string                                                    *
 *                                                                            *
 * Return value: SUCCEED - is IPv6 address                                    *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	is_ip6(const char *ip)
{
	const char	*p = ip, *last_colon;
	int		xdigits = 0, only_xdigits = 0, colons = 0, dbl_colons = 0, res;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __func__, ip);

	while ('\0' != *p)
	{
		if (0 != isxdigit(*p))
		{
			xdigits++;
			only_xdigits = 1;
		}
		else if (':' == *p)
		{
			if (0 == xdigits && 0 < colons)
			{
				/* consecutive sections of zeros are replaced with a double colon */
				only_xdigits = 1;
				dbl_colons++;
			}

			if (4 < xdigits || 1 < dbl_colons)
				break;

			xdigits = 0;
			colons++;
		}
		else
		{
			only_xdigits = 0;
			break;
		}

		p++;
	}

	if (2 > colons || 7 < colons || 1 < dbl_colons || 4 < xdigits)
		res = FAIL;
	else if (1 == only_xdigits)
		res = SUCCEED;
	else if (7 > colons && (last_colon = strrchr(ip, ':')) < p)
		res = is_ip4(last_colon + 1);	/* past last column is ipv4 mapped address */
	else
		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: is string IP address of supported version                         *
 *                                                                            *
 * Parameters: ip - string                                                    *
 *                                                                            *
 * Return value: SUCCEED - is IP address                                      *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	is_supported_ip(const char *ip)
{
	if (SUCCEED == is_ip4(ip))
		return SUCCEED;
#ifdef HAVE_IPV6
	if (SUCCEED == is_ip6(ip))
		return SUCCEED;
#endif
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: is string IP address                                              *
 *                                                                            *
 * Parameters: ip - string                                                    *
 *                                                                            *
 * Return value: SUCCEED - is IP address                                      *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	is_ip(const char *ip)
{
	return SUCCEED == is_ip4(ip) ? SUCCEED : is_ip6(ip);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if string is a valid internet hostname                      *
 *                                                                            *
 * Parameters: hostname - [IN] hostname string to be checked                  *
 *                                                                            *
 * Return value: SUCCEED - could be a valid hostname,                         *
 *               FAIL - definitely not a valid hostname                       *
 * Comments:                                                                  *
 *     Validation is not strict. Restrictions not checked:                    *
 *         - individual label (component) length 1-63,                        *
 *         - hyphens ('-') allowed only as interior characters in labels,     *
 *         - underscores ('_') allowed in domain name, but not in hostname.   *
 *                                                                            *
 ******************************************************************************/
int	zbx_validate_hostname(const char *hostname)
{
	int		component;	/* periods ('.') are only allowed when they serve to delimit components */
	int		len = ZBX_MAX_DNSNAME_LEN;
	const char	*p;

	/* the first character must be an alphanumeric character */
	if (0 == isalnum(*hostname))
		return FAIL;

	/* check only up to the first 'len' characters, the 1st character is already successfully checked */
	for (p = hostname + 1, component = 1; '\0' != *p; p++)
	{
		if (0 == --len)				/* hostname too long */
			return FAIL;

		/* check for allowed characters */
		if (0 != isalnum(*p) || '-' == *p || '_' == *p)
			component = 1;
		else if ('.' == *p && 1 == component)
			component = 0;
		else
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if ip matches range of ip addresses                         *
 *                                                                            *
 * Parameters: list - [IN] comma-separated list of ip ranges                  *
 *                         192.168.0.1-64,192.168.0.128,10.10.0.0/24,12fc::21 *
 *             ip   - [IN] ip address                                         *
 *                                                                            *
 * Return value: FAIL - out of range, SUCCEED - within the range              *
 *                                                                            *
 ******************************************************************************/
int	ip_in_list(const char *list, const char *ip)
{
	int		ipaddress[8];
	zbx_iprange_t	iprange;
	char		*address = NULL;
	size_t		address_alloc = 0, address_offset;
	const char	*ptr;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() list:'%s' ip:'%s'", __func__, list, ip);

	if (SUCCEED != iprange_parse(&iprange, ip))
		goto out;
#ifndef HAVE_IPV6
	if (ZBX_IPRANGE_V6 == iprange.type)
		goto out;
#endif
	iprange_first(&iprange, ipaddress);

	for (ptr = list; '\0' != *ptr; list = ptr + 1)
	{
		if (NULL == (ptr = strchr(list, ',')))
			ptr = list + strlen(list);

		address_offset = 0;
		zbx_strncpy_alloc(&address, &address_alloc, &address_offset, list, ptr - list);

		if (SUCCEED != iprange_parse(&iprange, address))
			continue;
#ifndef HAVE_IPV6
		if (ZBX_IPRANGE_V6 == iprange.type)
			continue;
#endif
		if (SUCCEED == iprange_validate(&iprange, ipaddress))
		{
			ret = SUCCEED;
			break;
		}
	}

	zbx_free(address);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if integer matches a list of integers                       *
 *                                                                            *
 * Parameters: list  - integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699)         *
 *             value - integer to check                                       *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the period            *
 *                                                                            *
 ******************************************************************************/
int	int_in_list(char *list, int value)
{
	char	*start = NULL, *end = NULL, c = '\0';
	int	i1, i2, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() list:'%s' value:%d", __func__, list, value);

	for (start = list; '\0' != *start;)
	{
		if (NULL != (end = strchr(start, ',')))
		{
			c = *end;
			*end = '\0';
		}

		if (2 == sscanf(start, "%d-%d", &i1, &i2))
		{
			if (i1 <= value && value <= i2)
			{
				ret = SUCCEED;
				break;
			}
		}
		else
		{
			if (value == atoi(start))
			{
				ret = SUCCEED;
				break;
			}
		}

		if (NULL != end)
		{
			*end = c;
			start = end + 1;
		}
		else
			break;
	}

	if (NULL != end)
		*end = c;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	zbx_double_compare(double a, double b)
{
	return fabs(a - b) <= ZBX_DOUBLE_EPSILON ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is double                                     *
 *                                                                            *
 * Parameters: str   - string to check                                        *
 *             flags - extra options including:                               *
 *                       ZBX_FLAG_DOUBLE_SUFFIX - allow suffixes              *
 *                                                                            *
 * Return value:  SUCCEED - the string is double                              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: the function automatically processes suffixes K, M, G, T and     *
 *           s, m, h, d, w                                                    *
 *                                                                            *
 ******************************************************************************/
int	is_double_suffix(const char *str, unsigned char flags)
{
	int	len;

	if ('-' == *str)	/* check leading sign */
		str++;

	if (FAIL == zbx_number_parse(str, &len))
		return FAIL;

	if ('\0' != *(str += len) && 0 != (flags & ZBX_FLAG_DOUBLE_SUFFIX) && NULL != strchr(ZBX_UNIT_SYMBOLS, *str))
		str++;		/* allow valid suffix if flag is enabled */

	return '\0' == *str ? SUCCEED : FAIL;
}

static int	is_double_valid_syntax(const char *str)
{
	int	len;

	/* Valid syntax is a decimal number optionally followed by a decimal exponent. */
	/* Leading and trailing white space, NAN, INF and hexadecimal notation are not allowed. */

	if ('-' == *str || '+' == *str)		/* check leading sign */
		str++;

	if (FAIL == zbx_number_parse(str, &len))
		return FAIL;

	return '\0' == *(str + len) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate and optionally convert a string to a number of type      *
 *         'double'                                                           *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             value - [OUT] output buffer where to write the converted value *
 *                     (optional, can be NULL)                                *
 *                                                                            *
 * Return value:  SUCCEED - the string can be converted to 'double' and       *
 *                          was converted if 'value' is not NULL              *
 *                FAIL - the string does not represent a valid 'double' or    *
 *                       its value is outside of valid range                  *
 *                                                                            *
 ******************************************************************************/
int	is_double(const char *str, double *value)
{
	double	tmp;
	char	*endptr;

	/* Not all strings accepted by strtod() can be accepted in Zabbix. */
	/* Therefore additional, more strict syntax check is used before strtod(). */

	if (SUCCEED != is_double_valid_syntax(str))
		return FAIL;

	errno = 0;
	tmp = strtod(str, &endptr);

	if ('\0' != *endptr || HUGE_VAL == tmp || -HUGE_VAL == tmp || EDOM == errno)
		return FAIL;

	if (NULL != value)
		*value = tmp;

	return SUCCEED;
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
int	is_time_suffix(const char *str, int *value, int length)
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

#if defined(_WINDOWS) || defined(__MINGW32__)
int	_wis_uint(const wchar_t *wide_string)
{
	const wchar_t	*wide_char = wide_string;

	if (L'\0' == *wide_char)
		return FAIL;

	while (L'\0' != *wide_char)
	{
		if (0 != iswdigit(*wide_char))
		{
			wide_char++;
			continue;
		}
		return FAIL;
	}

	return SUCCEED;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is unsigned integer within the specified      *
 *          range and optionally store it into value parameter                *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             n     - [IN] string length or ZBX_MAX_UINT64_LEN               *
 *             value - [OUT] a pointer to output buffer where the converted   *
 *                     value is to be written (optional, can be NULL)         *
 *             size  - [IN] size of the output buffer (optional)              *
 *             min   - [IN] the minimum acceptable value                      *
 *             max   - [IN] the maximum acceptable value                      *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - the string is not a number or its value is outside   *
 *                       the specified range                                  *
 *                                                                            *
 ******************************************************************************/
int	is_uint_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max)
{
	zbx_uint64_t		value_uint64 = 0, c;
	const zbx_uint64_t	max_uint64 = ~__UINT64_C(0);

	if ('\0' == *str || 0 == n || sizeof(zbx_uint64_t) < size || (0 == size && NULL != value))
		return FAIL;

	while ('\0' != *str && 0 < n--)
	{
		if (0 == isdigit(*str))
			return FAIL;	/* not a digit */

		c = (zbx_uint64_t)(unsigned char)(*str - '0');

		if ((max_uint64 - c) / 10 < value_uint64)
			return FAIL;	/* maximum value exceeded */

		value_uint64 = value_uint64 * 10 + c;

		str++;
	}

	if (min > value_uint64 || value_uint64 > max)
		return FAIL;

	if (NULL != value)
	{
		/* On little endian architecture the output value will be stored starting from the first bytes */
		/* of 'value' buffer while on big endian architecture it will be stored starting from the last */
		/* bytes. We handle it by storing the offset in the most significant byte of short value and   */
		/* then use the first byte as source offset.                                                   */
		unsigned short	value_offset = (unsigned short)((sizeof(zbx_uint64_t) - size) << 8);

		memcpy(value, (unsigned char *)&value_uint64 + *((unsigned char *)&value_offset), size);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is unsigned hexadecimal integer within the    *
 *          specified range and optionally store it into value parameter      *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             n     - [IN] string length                                     *
 *             value - [OUT] a pointer to output buffer where the converted   *
 *                     value is to be written (optional, can be NULL)         *
 *             size  - [IN] size of the output buffer (optional)              *
 *             min   - [IN] the minimum acceptable value                      *
 *             max   - [IN] the maximum acceptable value                      *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - the string is not a hexadecimal number or its value  *
 *                       is outside the specified range                       *
 *                                                                            *
 ******************************************************************************/
int	is_hex_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max)
{
	zbx_uint64_t		value_uint64 = 0, c;
	const zbx_uint64_t	max_uint64 = ~__UINT64_C(0);
	int			len = 0;

	if ('\0' == *str || 0 == n || sizeof(zbx_uint64_t) < size || (0 == size && NULL != value))
		return FAIL;

	while ('\0' != *str && 0 < n--)
	{
		if ('0' <= *str && *str <= '9')
			c = *str - '0';
		else if ('a' <= *str && *str <= 'f')
			c = 10 + (*str - 'a');
		else if ('A' <= *str && *str <= 'F')
			c = 10 + (*str - 'A');
		else
			return FAIL;	/* not a hexadecimal digit */

		if (16 < ++len && (max_uint64 >> 4) < value_uint64)
			return FAIL;	/* maximum value exceeded */

		value_uint64 = (value_uint64 << 4) + c;

		str++;
	}
	if (min > value_uint64 || value_uint64 > max)
		return FAIL;

	if (NULL != value)
	{
		/* On little endian architecture the output value will be stored starting from the first bytes */
		/* of 'value' buffer while on big endian architecture it will be stored starting from the last */
		/* bytes. We handle it by storing the offset in the most significant byte of short value and   */
		/* then use the first byte as source offset.                                                   */
		unsigned short	value_offset = (unsigned short)((sizeof(zbx_uint64_t) - size) << 8);

		memcpy(value, (unsigned char *)&value_uint64 + *((unsigned char *)&value_offset), size);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is boolean                                    *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is boolean                             *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
int	is_boolean(const char *str, zbx_uint64_t *value)
{
	double	dbl_tmp;
	int	res;

	if (SUCCEED == (res = is_double(str, &dbl_tmp)))
		*value = (0 != dbl_tmp);
	else
	{
		char	tmp[16];

		strscpy(tmp, str);
		zbx_strlower(tmp);

		if (SUCCEED == (res = str_in_list("true,t,yes,y,on,up,running,enabled,available,ok,master", tmp, ',')))
		{
			*value = 1;
		}
		else if (SUCCEED == (res = str_in_list("false,f,no,n,off,down,unused,disabled,unavailable,err,slave",
				tmp, ',')))
		{
			*value = 0;
		}
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is unsigned octal                             *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned octal                      *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
int	is_uoct(const char *str)
{
	int	res = FAIL;

	while (' ' == *str)	/* trim left spaces */
		str++;

	for (; '\0' != *str; str++)
	{
		if (*str < '0' || *str > '7')
			break;

		res = SUCCEED;
	}

	while (' ' == *str)	/* check right spaces */
		str++;

	if ('\0' != *str)
		return FAIL;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is unsigned hexadecimal representation of     *
 *          data in the form "0-9, a-f or A-F"                                *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned hexadecimal                *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
int	is_uhex(const char *str)
{
	int	res = FAIL;

	while (' ' == *str)	/* trim left spaces */
		str++;

	for (; '\0' != *str; str++)
	{
		if (0 == isxdigit(*str))
			break;

		res = SUCCEED;
	}

	while (' ' == *str)	/* check right spaces */
		str++;

	if ('\0' != *str)
		return FAIL;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is a hexadecimal representation of data in    *
 *          the form "F4 CE 46 01 0C 44 8B F4\nA0 2C 29 74 5D 3F 13 49\n"     *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is formatted like the example above    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
int	is_hex_string(const char *str)
{
	if ('\0' == *str)
		return FAIL;

	while ('\0' != *str)
	{
		if (0 == isxdigit(*str))
			return FAIL;

		if (0 == isxdigit(*(str + 1)))
			return FAIL;

		if ('\0' == *(str + 2))
			break;

		if (' ' != *(str + 2) && '\n' != *(str + 2))
			return FAIL;

		str += 3;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get nearest index position of sorted elements in array            *
 *                                                                            *
 * Parameters: p   - pointer to array of elements                             *
 *             sz  - element size                                             *
 *             num - number of elements                                       *
 *             id  - index to look for                                        *
 *                                                                            *
 * Return value: index at which it would be possible to insert the element so *
 *               that the array is still sorted                               *
 *                                                                            *
 ******************************************************************************/
int	get_nearestindex(const void *p, size_t sz, int num, zbx_uint64_t id)
{
	int		first_index, last_index, index;
	zbx_uint64_t	element_id;

	if (0 == num)
		return 0;

	first_index = 0;
	last_index = num - 1;

	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (id == (element_id = *(const zbx_uint64_t *)((const char *)p + index * sz)))
			return index;

		if (last_index == first_index)
		{
			if (element_id < id)
				index++;
			return index;
		}

		if (element_id < id)
			first_index = index + 1;
		else
			last_index = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add uint64 value to dynamic array                                 *
 *                                                                            *
 ******************************************************************************/
int	uint64_array_add(zbx_uint64_t **values, int *alloc, int *num, zbx_uint64_t value, int alloc_step)
{
	int	index;

	index = get_nearestindex(*values, sizeof(zbx_uint64_t), *num, value);
	if (index < (*num) && (*values)[index] == value)
		return index;

	if (*alloc == *num)
	{
		if (0 == alloc_step)
		{
			zbx_error("Unable to reallocate buffer");
			assert(0);
		}

		*alloc += alloc_step;
		*values = (zbx_uint64_t *)zbx_realloc(*values, *alloc * sizeof(zbx_uint64_t));
	}

	memmove(&(*values)[index + 1], &(*values)[index], sizeof(zbx_uint64_t) * (*num - index));

	(*values)[index] = value;
	(*num)++;

	return index;
}

int	uint64_array_exists(const zbx_uint64_t *values, int num, zbx_uint64_t value)
{
	int	index;

	index = get_nearestindex(values, sizeof(zbx_uint64_t), num, value);
	if (index < num && values[index] == value)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove uint64 values from array                                   *
 *                                                                            *
 ******************************************************************************/
void	uint64_array_remove(zbx_uint64_t *values, int *num, const zbx_uint64_t *rm_values, int rm_num)
{
	int	rindex, index;

	for (rindex = 0; rindex < rm_num; rindex++)
	{
		index = get_nearestindex(values, sizeof(zbx_uint64_t), *num, rm_values[rindex]);
		if (index == *num || values[index] != rm_values[rindex])
			continue;

		memmove(&values[index], &values[index + 1], sizeof(zbx_uint64_t) * ((*num) - index - 1));
		(*num)--;
	}
}

zbx_uint64_t	suffix2factor(char c)
{
	switch (c)
	{
		case 'K':
			return ZBX_KIBIBYTE;
		case 'M':
			return ZBX_MEBIBYTE;
		case 'G':
			return ZBX_GIBIBYTE;
		case 'T':
			return ZBX_TEBIBYTE;
		case 's':
			return 1;
		case 'm':
			return SEC_PER_MIN;
		case 'h':
			return SEC_PER_HOUR;
		case 'd':
			return SEC_PER_DAY;
		case 'w':
			return SEC_PER_WEEK;
		default:
			return 1;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert string to 64bit unsigned integer                          *
 *                                                                            *
 * Parameters: str   - string to convert                                      *
 *             value - a pointer to converted value                           *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: the function automatically processes suffixes K, M, G, T         *
 *                                                                            *
 ******************************************************************************/
int	str2uint64(const char *str, const char *suffixes, zbx_uint64_t *value)
{
	size_t		sz;
	const char	*p;
	int		ret;
	zbx_uint64_t	factor = 1;

	sz = strlen(str);
	p = str + sz - 1;

	if (NULL != strchr(suffixes, *p))
	{
		factor = suffix2factor(*p);

		sz--;
	}

	if (SUCCEED == (ret = is_uint64_n(str, sz, value)))
		*value *= factor;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert string to double                                          *
 *                                                                            *
 * Parameters: str - string to convert                                        *
 *                                                                            *
 * Return value: converted double value                                       *
 *                                                                            *
 * Comments: the function automatically processes suffixes K, M, G, T and     *
 *           s, m, h, d, w                                                    *
 *                                                                            *
 ******************************************************************************/
double	str2double(const char *str)
{
	size_t	sz;

	sz = strlen(str) - 1;

	return atof(str) * suffix2factor(str[sz]);
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the host name              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: in host name allowed characters: '0-9a-zA-Z. _-'                 *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_hostname_char(unsigned char c)
{
	if (0 != isalnum(c))
		return SUCCEED;

	if (c == '.' || c == ' ' || c == '_' || c == '-')
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the item key               *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: in key allowed characters: '0-9a-zA-Z._-'                        *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_key_char(unsigned char c)
{
	if (0 != isalnum(c))
		return SUCCEED;

	if (c == '.' || c == '_' || c == '-')
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the trigger function       *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: in trigger function allowed characters: 'a-z'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_function_char(unsigned char c)
{
	if (0 != islower(c))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the macro name             *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: allowed characters in macro names: '0-9A-Z._'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_macro_char(unsigned char c)
{
	if (0 != isupper(c))
		return SUCCEED;

	if ('.' == c || '_' == c)
		return SUCCEED;

	if (0 != isdigit(c))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the name is a valid discovery macro                     *
 *                                                                            *
 * Return value:  SUCCEED - the name is a valid discovery macro               *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
int	is_discovery_macro(const char *name)
{
	if ('{' != *name++ || '#' != *name++)
		return FAIL;

	do
	{
		if (SUCCEED != is_macro_char(*name++))
			return FAIL;

	} while ('}' != *name);

	if ('\0' != name[1])
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Returns function type based on its name                           *
 *                                                                            *
 * Return value:  Function type.                                              *
 *                                                                            *
 ******************************************************************************/
zbx_function_type_t	zbx_get_function_type(const char *func)
{
	if (0 == strncmp(func, "trend", 5))
		return ZBX_FUNCTION_TYPE_TRENDS;

	if (0 == strncmp(func, "baseline", 8))
		return ZBX_FUNCTION_TYPE_TRENDS;

	if (0 == strcmp(func, "nodata"))
		return ZBX_FUNCTION_TYPE_TIMER;

	return ZBX_FUNCTION_TYPE_HISTORY;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace all not-allowed hostname characters in the string         *
 *                                                                            *
 * Parameters: host - the target C-style string                               *
 *                                                                            *
 * Comments: the string must be null-terminated, otherwise not secure!        *
 *                                                                            *
 ******************************************************************************/
void	make_hostname(char *host)
{
	char	*c;

	assert(host);

	for (c = host; '\0' != *c; ++c)
	{
		if (FAIL == is_hostname_char(*c))
			*c = '_';
	}
}

/******************************************************************************
 *                                                                            *
 * Return value: Interface type                                               *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
unsigned char	get_interface_type_by_item_type(unsigned char type)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			return INTERFACE_TYPE_AGENT;
		case ITEM_TYPE_SNMP:
		case ITEM_TYPE_SNMPTRAP:
			return INTERFACE_TYPE_SNMP;
		case ITEM_TYPE_IPMI:
			return INTERFACE_TYPE_IPMI;
		case ITEM_TYPE_JMX:
			return INTERFACE_TYPE_JMX;
		case ITEM_TYPE_SIMPLE:
		case ITEM_TYPE_EXTERNAL:
		case ITEM_TYPE_SSH:
		case ITEM_TYPE_TELNET:
		case ITEM_TYPE_SCRIPT:
			return INTERFACE_TYPE_ANY;
		case ITEM_TYPE_HTTPAGENT:
			return INTERFACE_TYPE_OPT;
		default:
			return INTERFACE_TYPE_UNKNOWN;
	}
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
int	calculate_sleeptime(int nextcheck, int max_sleeptime)
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

/******************************************************************************
 *                                                                            *
 * Purpose: parse a ServerActive element like "IP<:port>" or "[IPv6]<:port>"  *
 *                                                                            *
 ******************************************************************************/
int	parse_serveractive_element(char *str, char **host, unsigned short *port, unsigned short port_default)
{
#ifdef HAVE_IPV6
	char	*r1 = NULL;
#endif
	char	*r2 = NULL;
	int	res = FAIL;

	*port = port_default;

#ifdef HAVE_IPV6
	if ('[' == *str)
	{
		str++;

		if (NULL == (r1 = strchr(str, ']')))
			goto fail;

		if (':' != r1[1] && '\0' != r1[1])
			goto fail;

		if (':' == r1[1] && SUCCEED != is_ushort(r1 + 2, port))
			goto fail;

		*r1 = '\0';

		if (SUCCEED != is_ip6(str))
			goto fail;

		*host = zbx_strdup(*host, str);
	}
	else if (SUCCEED == is_ip6(str))
	{
		*host = zbx_strdup(*host, str);
	}
	else
	{
#endif
		if (NULL != (r2 = strchr(str, ':')))
		{
			if (SUCCEED != is_ushort(r2 + 1, port))
				goto fail;

			*r2 = '\0';
		}

		*host = zbx_strdup(NULL, str);
#ifdef HAVE_IPV6
	}
#endif

	res = SUCCEED;
fail:
#ifdef HAVE_IPV6
	if (NULL != r1)
		*r1 = ']';
#endif
	if (NULL != r2)
		*r2 = ':';

	return res;
}

void	zbx_alarm_flag_set(void)
{
	zbx_timed_out = 1;
}

void	zbx_alarm_flag_clear(void)
{
	zbx_timed_out = 0;
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
unsigned int	zbx_alarm_on(unsigned int seconds)
{
	zbx_alarm_flag_clear();

	return alarm(seconds);
}

unsigned int	zbx_alarm_off(void)
{
	unsigned int	ret;

	ret = alarm(0);
	zbx_alarm_flag_clear();
	return ret;
}
#endif

int	zbx_alarm_timed_out(void)
{
	return (0 == zbx_timed_out ? FAIL : SUCCEED);
}

/* Since 2.26 the GNU C Library will detect when /etc/resolv.conf has been modified and reload the changed */
/* configuration. For performance reasons manual reloading should be avoided when unnecessary. */
#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H) && defined(__GLIBC__) && __GLIBC__ == 2 && __GLIBC_MINOR__ < 26
/******************************************************************************
 *                                                                            *
 * Purpose: react to "/etc/resolv.conf" update                                *
 *                                                                            *
 * Comments: it is intended to call this function in the end of each process  *
 *           main loop. The purpose of calling it at the end (instead of the  *
 *           beginning of main loop) is to let the first initialization of    *
 *           libc resolver proceed internally.                                *
 *                                                                            *
 ******************************************************************************/
static void	update_resolver_conf(void)
{
#define ZBX_RESOLV_CONF_FILE	"/etc/resolv.conf"

	static time_t	mtime = 0;
	zbx_stat_t	buf;

	if (0 == zbx_stat(ZBX_RESOLV_CONF_FILE, &buf) && mtime != buf.st_mtime)
	{
		mtime = buf.st_mtime;

		if (0 != res_init())
			zabbix_log(LOG_LEVEL_WARNING, "update_resolver_conf(): res_init() failed");
	}

#undef ZBX_RESOLV_CONF_FILE
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: throttling of update "/etc/resolv.conf" and "stdio" to the new    *
 *          log file after rotation                                           *
 *                                                                            *
 * Parameters: time_now - [IN] the time for compare in seconds                *
 *                                                                            *
 ******************************************************************************/
void	zbx_update_env(double time_now)
{
	static double	time_update = 0;

	/* handle /etc/resolv.conf update and log rotate less often than once a second */
	if (1.0 < time_now - time_update)
	{
		time_update = time_now;
		zbx_handle_log();
#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H) && defined(__GLIBC__) && __GLIBC__ == 2 && __GLIBC_MINOR__ < 26
		update_resolver_conf();
#endif
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate item nextcheck for Zabbix agent type items              *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_agent_item_nextcheck(zbx_uint64_t itemid, const char *delay, int now,
		int *nextcheck, int *scheduling, char **error)
{
	int			simple_interval;
	zbx_custom_interval_t	*custom_intervals;

	if (SUCCEED != zbx_interval_preproc(delay, &simple_interval, &custom_intervals, error))
	{
		*nextcheck = ZBX_JAN_2038;
		return FAIL;
	}

	if (NULL != custom_intervals->scheduling)
		*scheduling = SUCCEED;
	else
		*scheduling = FAIL;

	*nextcheck = calculate_item_nextcheck(itemid, ITEM_TYPE_ZABBIX, simple_interval, custom_intervals, now);
	zbx_custom_interval_free(custom_intervals);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate report nextcheck                                        *
 *                                                                            *
 * Parameters: now        - [IN] the current timestamp                        *
 *             cycle      - [IN] the report cycle                             *
 *             weekdays   - [IN] the week days report should be prepared,     *
 *                               bitmask (0x01 - Monday, 0x02 - Tuesday...)   *
 *             start_time - [IN] the report start time in seconds after       *
 *                               midnight                                     *
 *             tz         - [IN] the report starting timezone                 *
 *                                                                            *
 * Return value: The timestamp when the report must be prepared or -1 if an   *
 *               error occurred.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_report_nextcheck(int now, unsigned char cycle, unsigned char weekdays, int start_time,
		const char *tz)
{
	struct tm	*tm;
	time_t		yesterday = now - SEC_PER_DAY;
	int		nextcheck, tm_hour, tm_min, tm_sec;

	if (NULL == (tm = zbx_localtime(&yesterday, tz)))
		return -1;

	tm_sec = start_time % 60;
	start_time /= 60;
	tm_min = start_time % 60;
	start_time /= 60;
	tm_hour = start_time;

	do
	{
		/* handle midnight startup times */
		if (0 == tm->tm_sec && 0 == tm->tm_min && 0 == tm->tm_hour)
			zbx_tm_add(tm, 1, ZBX_TIME_UNIT_DAY);

		switch (cycle)
		{
			case ZBX_REPORT_CYCLE_YEARLY:
				zbx_tm_round_up(tm, ZBX_TIME_UNIT_YEAR);
				break;
			case ZBX_REPORT_CYCLE_MONTHLY:
				zbx_tm_round_up(tm, ZBX_TIME_UNIT_MONTH);
				break;
			case ZBX_REPORT_CYCLE_WEEKLY:
				if (0 == weekdays)
					return -1;
				zbx_tm_round_up(tm, ZBX_TIME_UNIT_DAY);

				while (0 == (weekdays & (1 << (tm->tm_wday + 6) % 7)))
					zbx_tm_add(tm, 1, ZBX_TIME_UNIT_DAY);

				break;
			case ZBX_REPORT_CYCLE_DAILY:
				zbx_tm_round_up(tm, ZBX_TIME_UNIT_DAY);
				break;
		}

		tm->tm_sec = tm_sec;
		tm->tm_min = tm_min;
		tm->tm_hour = tm_hour;

		nextcheck = (int)mktime(tm);
	}
	while (-1 != nextcheck && nextcheck <= now);

	return nextcheck;
}

void	zbx_free_tag(zbx_tag_t *tag)
{
	zbx_free(tag->tag);
	zbx_free(tag->value);
	zbx_free(tag);
}
