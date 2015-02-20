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
#include "log.h"
#include "setproctitle.h"

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
 * Function: get_program_name                                                 *
 *                                                                            *
 * Purpose: return program name without path                                  *
 *                                                                            *
 * Parameters: path                                                           *
 *                                                                            *
 * Return value: program name without path                                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
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
 * Function: zbx_timespec                                                     *
 *                                                                            *
 * Purpose: Gets the current time.                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: Time in seconds since midnight (00:00:00),                       *
 *           January 1, 1970, coordinated universal time (UTC).               *
 *                                                                            *
 ******************************************************************************/
void	zbx_timespec(zbx_timespec_t *ts)
{
	static zbx_timespec_t	*last_ts = NULL;
	static int		corr = 0;
#ifdef _WINDOWS
	LARGE_INTEGER	tickPerSecond, tick;
	static int	boottime = 0;
	BOOL		rc = FALSE;
#else
	struct timeval	tv;
	int		rc = -1;
#	ifdef HAVE_TIME_CLOCK_GETTIME
	struct timespec	tp;
#	endif
#endif

	if (NULL == last_ts)
		last_ts = zbx_calloc(last_ts, 1, sizeof(zbx_timespec_t));

#ifdef _WINDOWS
	if (TRUE == (rc = QueryPerformanceFrequency(&tickPerSecond)))
	{
		if (TRUE == (rc = QueryPerformanceCounter(&tick)))
		{
			ts->ns = (int)(1000000000 * (tick.QuadPart % tickPerSecond.QuadPart) / tickPerSecond.QuadPart);

			tick.QuadPart = tick.QuadPart / tickPerSecond.QuadPart;

			if (0 == boottime)
				boottime = (int)(time(NULL) - tick.QuadPart);

			ts->sec = (int)(tick.QuadPart + boottime);
		}
	}

	if (TRUE != rc)
	{
		struct _timeb   tb;

		_ftime(&tb);

		ts->sec = (int)tb.time;
		ts->ns = tb.millitm * 1000000;
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

	if (last_ts->ns == ts->ns && last_ts->sec == ts->sec)
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
		last_ts->sec = ts->sec;
		last_ts->ns = ts->ns;
		corr = 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_time                                                         *
 *                                                                            *
 * Purpose: Gets the current time.                                            *
 *                                                                            *
 * Return value: Time in seconds                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
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
 * Function: zbx_current_time                                                 *
 *                                                                            *
 * Purpose: Gets the current time including UTC offset                        *
 *                                                                            *
 * Return value: Time in seconds                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
double	zbx_current_time(void)
{
	return zbx_time() + ZBX_JAN_1970_IN_SEC;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_calloc2                                                      *
 *                                                                            *
 * Purpose: allocates nmemb * size bytes of memory and fills it with zeros    *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 * Author: Eugene Grigorjev, Rudolfs Kreicbergs                               *
 *                                                                            *
 ******************************************************************************/
void    *zbx_calloc2(const char *filename, int line, void *old, size_t nmemb, size_t size)
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
 * Function: zbx_malloc2                                                      *
 *                                                                            *
 * Purpose: allocates size bytes of memory                                    *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void    *zbx_malloc2(const char *filename, int line, void *old, size_t size)
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
 * Function: zbx_realloc2                                                     *
 *                                                                            *
 * Purpose: changes the size of the memory block pointed to by old            *
 *          to size bytes                                                     *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void    *zbx_realloc2(const char *filename, int line, void *old, size_t size)
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

char    *zbx_strdup2(const char *filename, int line, char *old, const char *str)
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

/******************************************************************************
 *                                                                            *
 * Function: zbx_setproctitle                                                 *
 *                                                                            *
 * Purpose: set process title                                                 *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	__zbx_zbx_setproctitle(const char *fmt, ...)
{
#if defined(HAVE_FUNCTION_SETPROCTITLE) || defined(PS_OVERWRITE_ARGV) || defined(PS_PSTAT_ARGV)
	char	title[MAX_STRING_LEN];
	va_list	args;

	va_start(args, fmt);
	zbx_vsnprintf(title, sizeof(title), fmt, args);
	va_end(args);

	zabbix_log(LOG_LEVEL_DEBUG, "%s", title);
#endif

#if defined(HAVE_FUNCTION_SETPROCTITLE)
	setproctitle(title);
#elif defined(PS_OVERWRITE_ARGV) || defined(PS_PSTAT_ARGV)
	setproctitle_set_status(title);
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: check_time_period                                                *
 *                                                                            *
 * Purpose: check if current time is within given period                      *
 *                                                                            *
 * Parameters: period - time period in format [d1-d2,hh:mm-hh:mm]*            *
 *             now    - timestamp for comparison                              *
 *                      if NULL - use current timestamp.                      *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the period            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:   !!! Don't forget to sync code with PHP !!!                     *
 *                                                                            *
 ******************************************************************************/
int	check_time_period(const char *period, time_t now)
{
	const char	*__function_name = "check_time_period";
	const char	*s, *delim;
	int		d1, d2, h1, h2, m1, m2, flag, day, sec, sec1, sec2, ret = FAIL;
	struct tm	*tm;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() period:'%s'", __function_name, period);

	if (now == (time_t)NULL)
		now = time(NULL);

	tm = localtime(&now);
	day = 0 == tm->tm_wday ? 7 : tm->tm_wday;
	sec = SEC_PER_HOUR * tm->tm_hour + SEC_PER_MIN * tm->tm_min + tm->tm_sec;

	zabbix_log(LOG_LEVEL_DEBUG, "%d,%d:%d", day, (int)tm->tm_hour, (int)tm->tm_min);

	for (s = period; '\0' != *s;)
	{
		delim = strchr(s, ';');

		zabbix_log(LOG_LEVEL_DEBUG, "period [%s]", s);

		flag = (6 == sscanf(s, "%d-%d,%d:%d-%d:%d", &d1, &d2, &h1, &m1, &h2, &m2));

		if (0 == flag)
		{
			flag = (5 == sscanf(s, "%d,%d:%d-%d:%d", &d1, &h1, &m1, &h2, &m2));
			d2 = d1;
		}

		if (0 != flag)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%d-%d,%d:%d-%d:%d", d1, d2, h1, m1, h2, m2);

			sec1 = SEC_PER_HOUR * h1 + SEC_PER_MIN * m1;
			sec2 = SEC_PER_HOUR * h2 + SEC_PER_MIN * m2;	/* do not include upper bound */

			if (d1 <= day && day <= d2 && sec1 <= sec && sec < sec2)
			{
				ret = SUCCEED;
				break;
			}
		}
		else
			zabbix_log(LOG_LEVEL_ERR, "wrong time period format [%s]", period);

		if (NULL != delim)
			s = delim + 1;
		else
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_current_delay                                                *
 *                                                                            *
 * Purpose: return delay value that is currently applicable                   *
 *                                                                            *
 * Parameters: delay - [IN] default delay value, can be overridden            *
 *             flex_intervals - [IN] descriptions of flexible intervals       *
 *                                   in the form [dd/d1-d2,hh:mm-hh:mm;]      *
 *             now - [IN] current time                                        *
 *                                                                            *
 * Return value: delay value - either default or minimum delay value          *
 *                             out of all applicable intervals                *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev, Aleksandrs Saveljevs        *
 *                                                                            *
 ******************************************************************************/
static int	get_current_delay(int delay, const char *flex_intervals, time_t now)
{
	const char	*s, *delim;
	char		flex_period[30];
	int		flex_delay, current_delay = -1;

	if (NULL == flex_intervals || '\0' == *flex_intervals)
		return delay;

	for (s = flex_intervals; '\0' != *s; s = delim + 1)
	{
		delim = strchr(s, ';');

		zabbix_log(LOG_LEVEL_DEBUG, "delay period [%s]", s);

		if (2 == sscanf(s, "%d/%29[^;]s", &flex_delay, flex_period))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%d sec at %s", flex_delay, flex_period);

			if ((-1 == current_delay || flex_delay < current_delay) &&
					SUCCEED == check_time_period(flex_period, now))
			{
				current_delay = flex_delay;
			}
		}
		else
			zabbix_log(LOG_LEVEL_ERR, "wrong delay period format [%s]", s);

		if (NULL == delim)
			break;
	}

	if (-1 == current_delay)
		return delay;

	return current_delay;
}

/******************************************************************************
 *                                                                            *
 * Function: get_next_delay_interval                                          *
 *                                                                            *
 * Purpose: return time when next delay settings take effect                  *
 *                                                                            *
 * Parameters: flex_intervals - [IN] descriptions of flexible intervals       *
 *                                   in the form [dd/d1-d2,hh:mm-hh:mm;]      *
 *             now = [IN] current time                                        *
 *             next_interval = [OUT] start of next delay interval             *
 *                                                                            *
 * Return value: SUCCEED - there is a next interval                           *
 *               FAIL - otherwise (in this case, next_interval is unaffected) *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev, Aleksandrs Saveljevs        *
 *                                                                            *
 ******************************************************************************/
static int	get_next_delay_interval(const char *flex_intervals, time_t now, time_t *next_interval)
{
	const char	*s, *delim;
	struct tm	*tm;
	int		day, sec, sec1, sec2, delay, d1, d2, h1, h2, m1, m2, flag;
	time_t		next = 0;

	if (NULL == flex_intervals || '\0' == *flex_intervals)
		return FAIL;

	tm = localtime(&now);
	day = 0 == tm->tm_wday ? 7 : tm->tm_wday;
	sec = SEC_PER_HOUR * tm->tm_hour + SEC_PER_MIN * tm->tm_min + tm->tm_sec;

	for (s = flex_intervals; '\0' != *s;)
	{
		delim = strchr(s, ';');

		zabbix_log(LOG_LEVEL_DEBUG, "delay period [%s]", s);

		flag = (7 == sscanf(s, "%d/%d-%d,%d:%d-%d:%d", &delay, &d1, &d2, &h1, &m1, &h2, &m2));

		if (0 == flag)
		{
			flag = (6 == sscanf(s, "%d/%d,%d:%d-%d:%d", &delay, &d1, &h1, &m1, &h2, &m2));
			d2 = d1;
		}

		if (0 != flag)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%d/%d-%d,%d:%d-%d:%d", delay, d1, d2, h1, m1, h2, m2);

			sec1 = SEC_PER_HOUR * h1 + SEC_PER_MIN * m1;
			sec2 = SEC_PER_HOUR * h2 + SEC_PER_MIN * m2;	/* do not include upper bound */

			if (d1 <= day && day <= d2 && sec1 <= sec && sec < sec2)	/* current period */
			{
				if (0 == next || next > now - sec + sec2)
					next = now - sec + sec2;	/* the next second after the */
									/* current interval's upper bound */
			}
			else if (d1 <= day && day <= d2 && sec < sec1)			/* will be active today */
			{
				if (0 == next || next > now - sec + sec1)
					next = now - sec + sec1;
			}
			else
			{
				int	next_day;

				next_day = (7 >= day + 1 ? day + 1 : 1);

				if (d1 <= next_day && next_day <= d2)			/* will be active tomorrow */
				{
					if (0 == next || next > now - sec + SEC_PER_DAY + sec1)
						next = now - sec + SEC_PER_DAY + sec1;
				}
				else							/* later in the future */
				{
					int	day_diff = -1;

					if (day < d1)
						day_diff = d1 - day;
					if (day >= d2)
						day_diff = (d1 + 7) - day;
					if (d1 <= day && day < d2)
					{
						/* should never happen */
						zabbix_log(LOG_LEVEL_ERR, "could not deduce day difference"
								" [%s, day=%d, time=%02d:%02d:%02d]",
								s, day, tm->tm_hour, tm->tm_min, tm->tm_sec);
						day_diff = -1;
					}

					if (-1 != day_diff &&
							(0 == next || next > now - sec + SEC_PER_DAY * day_diff + sec1))
						next = now - sec + SEC_PER_DAY * day_diff + sec1;
				}
			}
		}
		else
			zabbix_log(LOG_LEVEL_ERR, "wrong delay period format [%s]", s);

		if (NULL != delim)
			s = delim + 1;
		else
			break;
	}

	if (0 != next && NULL != next_interval)
		*next_interval = next;

	return 0 != next ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: calculate_item_nextcheck                                         *
 *                                                                            *
 * Purpose: calculate nextcheck timestamp for item                            *
 *                                                                            *
 * Parameters: seed      - [IN] the seed value applied to delay to spread     *
 *                              item checks over the delay period             *
 *             item_type - [IN] the item type                                 *
 *             delay     - [IN] default delay value, can be overridden        *
 *             flex_intervals - [IN] descriptions of flexible intervals       *
 *                                   in the form [dd/d1-d2,hh:mm-hh:mm;]      *
 *             now       - [IN] current timestamp                             *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 * Comments: if item check is forbidden with delay=0 (default and flexible),  *
 *           a timestamp very far in the future is returned                   *
 *                                                                            *
 *           Old algorithm: now+delay                                         *
 *           New one: preserve period, if delay==5, nextcheck = 0,5,10,15,... *
 *           !!! Don't forget to sync code with PHP !!!                       *
 *                                                                            *
 ******************************************************************************/
int	calculate_item_nextcheck(zbx_uint64_t seed, int item_type, int delay, const char *flex_intervals, time_t now)
{
	int	nextcheck = 0;

	/* special processing of active items to see better view in queue */
	if (ITEM_TYPE_ZABBIX_ACTIVE == item_type)
	{
		if (0 != delay)
			nextcheck = (int)now + delay;
		else
			nextcheck = ZBX_JAN_2038;
	}
	else
	{
		int	current_delay = 0, try = 0;
		time_t	next_interval, t, tmax;

		/* Try to find the nearest 'nextcheck' value with condition */
		/* 'now' < 'nextcheck' < 'now' + SEC_PER_YEAR. If it is not */
		/* possible to check the item within a year, fail. */

		t = now;
		tmax = now + SEC_PER_YEAR;

		while (t < tmax)
		{
			/* calculate 'nextcheck' value for the current interval */
			current_delay = get_current_delay(delay, flex_intervals, t);

			if (0 != current_delay)
			{
				nextcheck = current_delay * (int)(t / (time_t)current_delay) +
						(int)(seed % (zbx_uint64_t)current_delay);

				if (0 == try)
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

			/* 'nextcheck' < end of the current interval ? */
			/* the end of the current interval is the beginning of the next interval - 1 */
			if (FAIL != get_next_delay_interval(flex_intervals, t, &next_interval) &&
					nextcheck >= next_interval)
			{
				/* 'nextcheck' is beyond the current interval */
				t = next_interval;
				try++;
			}
			else
				break;	/* nextcheck is within the current interval */
		}
	}

	return nextcheck;
}

/******************************************************************************
 *                                                                            *
 * Function: calculate_proxy_nextcheck                                        *
 *                                                                            *
 * Purpose: calculate nextcheck timestamp for passive proxy                   *
 *                                                                            *
 * Parameters: hostid - [IN] host identificator from database                 *
 *             delay  - [IN] default delay value, can be overridden           *
 *             now    - [IN] current timestamp                                *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Function: is_ip4                                                           *
 *                                                                            *
 * Purpose: is string IPv4 address                                            *
 *                                                                            *
 * Parameters: ip - string                                                    *
 *                                                                            *
 * Return value: SUCCEED - is IPv4 address                                    *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 ******************************************************************************/
int	is_ip4(const char *ip)
{
	const char	*__function_name = "is_ip4";
	const char	*p = ip;
	int		nums = 0, dots = 0, res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __function_name, ip);

	while ('\0' != *p)
	{
		if (0 != isdigit(*p))
		{
			nums++;
		}
		else if ('.' == *p)
		{
			if (0 == nums || 3 < nums)
				break;
			nums = 0;
			dots++;
		}
		else
		{
			nums = 0;
			break;
		}

		p++;
	}
	if (dots == 3 && 1 <= nums && nums <= 3)
		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

#if defined(HAVE_IPV6)
/******************************************************************************
 *                                                                            *
 * Function: is_ip6                                                           *
 *                                                                            *
 * Purpose: is string IPv6 address                                            *
 *                                                                            *
 * Parameters: ip - string                                                    *
 *                                                                            *
 * Return value: SUCCEED - is IPv6 address                                    *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: could be improved (not supported x:x:x:x:x:x:d.d.d.d addresses)  *
 *                                                                            *
 ******************************************************************************/
int	is_ip6(const char *ip)
{
	const char	*__function_name = "is_ip6";
	const char	*p = ip;
	int		nums = 0, is_nums = 0, colons = 0, dcolons = 0, res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __function_name, ip);

	while ('\0' != *p)
	{
		if (0 != isxdigit(*p))
		{
			nums++;
			is_nums = 1;
		}
		else if (':' == *p)
		{
			if (0 == nums && 0 < colons)
				dcolons++;
			if (4 < nums || 1 < dcolons)
				break;
			nums = 0;
			colons++;
		}
		else
		{
			is_nums = 0;
			break;
		}

		p++;
	}

	if (2 <= colons && colons <= 7 && 4 >= nums && 1 == is_nums)
		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}
#endif	/*HAVE_IPV6*/

/******************************************************************************
 *                                                                            *
 * Function: is_ip                                                            *
 *                                                                            *
 * Purpose: is string IP address                                              *
 *                                                                            *
 * Parameters: ip - string                                                    *
 *                                                                            *
 * Return value: SUCCEED - is IP address                                      *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	is_ip(const char *ip)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In is_ip() ip:'%s'", ip);

	if (SUCCEED == is_ip4(ip))
		return SUCCEED;
#if defined(HAVE_IPV6)
	if (SUCCEED == is_ip6(ip))
		return SUCCEED;
#endif
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: ip_in_list                                                       *
 *                                                                            *
 * Purpose: check if ip matches range of ip addresses                         *
 *                                                                            *
 * Parameters: list - [IN] comma-separated list of ip ranges                  *
 *                    192.168.0.1-64,192.168.0.128,10.10.0.0/24,12fc::21      *
 *             ip   - [IN] ip address                                         *
 *                                                                            *
 * Return value: FAIL - out of range, SUCCEED - within the range              *
 *                                                                            *
 ******************************************************************************/
int	ip_in_list(const char *list, const char *ip)
{
	const char	*__function_name = "ip_in_list";

	int		ipaddress[8];
	zbx_iprange_t	iprange;
	char		*address = NULL;
	size_t		address_alloc = 0, address_offset;
	const char	*ptr;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() list:'%s' ip:'%s'", __function_name, list, ip);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: int_in_list                                                      *
 *                                                                            *
 * Purpose: check if integer matches a list of integers                       *
 *                                                                            *
 * Parameters: list  - integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699)         *
 *             value - integer to check                                       *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the period            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	int_in_list(char *list, int value)
{
	const char	*__function_name = "int_in_list";
	char		*start = NULL, *end = NULL, c = '\0';
	int		i1, i2, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() list:'%s' value:%d", __function_name, list, value);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	zbx_double_compare(double a, double b)
{
	return fabs(a - b) < ZBX_DOUBLE_EPSILON ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: is_double_suffix                                                 *
 *                                                                            *
 * Purpose: check if the string is double                                     *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is double                              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: the function automatically processes suffixes K, M, G, T and     *
 *           s, m, h, d, w                                                    *
 *                                                                            *
 ******************************************************************************/
int	is_double_suffix(const char *str)
{
	size_t	i;
	char	dot = 0;

	for (i = 0; '\0' != str[i]; i++)
	{
		/* negative number? */
		if ('-' == str[i] && 0 == i)
			continue;

		if (0 != isdigit(str[i]))
			continue;

		if ('.' == str[i] && 0 == dot)
		{
			dot = 1;
			continue;
		}

		/* last character is suffix */
		if (NULL != strchr("KMGTsmhdw", str[i]) && '\0' == str[i + 1])
			continue;

		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_double                                                        *
 *                                                                            *
 * Purpose: check if the string is double                                     *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is double                              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
int	is_double(const char *str)
{
	int	i = 0, digits = 0;

	while (' ' == str[i])				/* trim left spaces */
		i++;

	if ('-' == str[i] || '+' == str[i])		/* check leading sign */
		i++;

	while (0 != isdigit(str[i]))			/* check digits before dot */
	{
		i++;
		digits = 1;
	}

	if ('.' == str[i])				/* check decimal dot */
		i++;

	while (0 != isdigit(str[i]))			/* check digits after dot */
	{
		i++;
		digits = 1;
	}

	if (0 == digits)				/* 1., .1, and 1.1 are good, just . is not */
		return FAIL;

	if ('e' == str[i] || 'E' == str[i])		/* check exponential part */
	{
		i++;

		if ('-' == str[i] || '+' == str[i])	/* check exponent sign */
			i++;

		if (0 == isdigit(str[i]))		/* check exponent */
			return FAIL;

		while (0 != isdigit(str[i]))
			i++;
	}

	while (' ' == str[i])				/* trim right spaces */
		i++;

	return '\0' == str[i] ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: is_uint_suffix                                                   *
 *                                                                            *
 * Purpose: check if the string is unsigned integer                           *
 *                                                                            *
 * Parameters: str   - string to check                                        *
 *             value - a pointer to converted value (optional)                *
 *                                                                            *
 * Return value: SUCCEED - the string is unsigned integer                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Author: Aleksandrs Saveljevs, Vladimir Levijev                             *
 *                                                                            *
 * Comments: the function automatically processes suffixes s, m, h, d, w      *
 *                                                                            *
 ******************************************************************************/
int	is_uint_suffix(const char *str, unsigned int *value)
{
	const unsigned int	max_uint = ~0U;
	unsigned int		value_uint = 0, c, factor = 1;

	if ('\0' == *str || '0' > *str || *str > '9')
		return FAIL;

	while ('\0' != *str && 0 != isdigit(*str))
	{
		c = (unsigned int)(unsigned char)(*str - '0');

		if ((max_uint - c) / 10 < value_uint)
			return FAIL;	/* overflow */

		value_uint = value_uint * 10 + c;

		str++;
	}

	if ('\0' != *str)
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
	}

	if ('\0' != *str)
		return FAIL;

	if (max_uint / factor < value_uint)
		return FAIL;	/* overflow */

	if (NULL != value)
		*value = value_uint * factor;

	return SUCCEED;
}

#if defined(_WINDOWS)
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
 * Function: is_int_prefix                                                    *
 *                                                                            *
 * Purpose: check if the beginning of string is a signed integer              *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the beginning of string is a signed integer       *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
int	is_int_prefix(const char *str)
{
	size_t	i = 0;

	while (' ' == str[i])	/* trim left spaces */
		i++;

	if ('-' == str[i] || '+' == str[i])
		i++;

	if (0 == isdigit(str[i]))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_uint_n_range                                                  *
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
 * Author: Alexander Vladishev, Andris Zeila                                  *
 *                                                                            *
 ******************************************************************************/
int	is_uint_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max)
{
	zbx_uint64_t		value_uint64 = 0, c;
	const zbx_uint64_t	max_uint64 = ~(zbx_uint64_t)__UINT64_C(0);
	int			len = 0;

	if ('\0' == *str || 0 == n || sizeof(zbx_uint64_t) < size || (0 == size && NULL != value))
		return FAIL;

	while ('\0' != *str && 0 < n--)
	{
		if (0 == isdigit(*str))
			return FAIL;	/* not a digit */

		c = (zbx_uint64_t)(unsigned char)(*str - '0');

		if (20 <= ++len && (max_uint64 - c) / 10 < value_uint64)
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
 * Function: is_hex_n_range                                                   *
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
	const zbx_uint64_t	max_uint64 = ~(zbx_uint64_t)__UINT64_C(0);
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
 * Function: is_boolean                                                       *
 *                                                                            *
 * Purpose: check if the string is boolean                                    *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is boolean                             *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	is_boolean(const char *str, zbx_uint64_t *value)
{
	int	res;

	if (SUCCEED == (res = is_double(str)))
		*value = (0 != atof(str));
	else
	{
		char	tmp[16];

		strscpy(tmp, str);
		zbx_strlower(tmp);

		if (SUCCEED == (res = str_in_list("true,t,yes,y,on,up,running,enabled,available", tmp, ',')))
			*value = 1;
		else if (SUCCEED == (res = str_in_list("false,f,no,n,off,down,unused,disabled,unavailable", tmp, ',')))
			*value = 0;
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: is_uoct                                                          *
 *                                                                            *
 * Purpose: check if the string is unsigned octal                             *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned octal                      *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Function: is_uhex                                                          *
 *                                                                            *
 * Purpose: check if the string is unsigned hexadecimal                       *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned hexadecimal                *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Function: is_hex_string                                                    *
 *                                                                            *
 * Purpose: check if the string is a hexadecimal representation of data in    *
 *          the form "F4 CE 46 01 0C 44 8B F4\nA0 2C 29 74 5D 3F 13 49\n"     *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is formatted like the example above    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
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
 * Function: uint64_in_list                                                   *
 *                                                                            *
 * Purpose: check if uin64 integer matches a list of integers                 *
 *                                                                            *
 * Parameters: list  - integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699)         *
 *             value - value                                                  *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the list              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	uint64_in_list(char *list, zbx_uint64_t value)
{
	const char	*__function_name = "uint64_in_list";
	char		*start = NULL, *end = NULL;
	zbx_uint64_t	i1, i2, tmp_uint64;
	int		ret = FAIL;
	char		c = '\0';

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() list:'%s' value:" ZBX_FS_UI64, __function_name, list, value);

	for (start = list; start[0] != '\0';)
	{
		if (NULL != (end = strchr(start, ',')))
		{
			c=end[0];
			end[0]='\0';
		}

		if (sscanf(start,ZBX_FS_UI64 "-" ZBX_FS_UI64,&i1,&i2) == 2)
		{
			if (i1 <= value && value <= i2)
			{
				ret = SUCCEED;
				break;
			}
		}
		else
		{
			sscanf(start, ZBX_FS_UI64, &tmp_uint64);
			if (tmp_uint64 == value)
			{
				ret = SUCCEED;
				break;
			}
		}

		if (end != NULL)
		{
			*end = c;
			start = end + 1;
		}
		else
			break;
	}

	if (NULL != end)
		*end = c;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_nearestindex                                                 *
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
 * Function: uint64_array_add                                                 *
 *                                                                            *
 * Purpose: add uint64 value to dynamic array                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
		*values = zbx_realloc(*values, *alloc * sizeof(zbx_uint64_t));
	}

	memmove(&(*values)[index + 1], &(*values)[index], sizeof(zbx_uint64_t) * (*num - index));

	(*values)[index] = value;
	(*num)++;

	return index;
}

/******************************************************************************
 *                                                                            *
 * Function: uint64_array_merge                                               *
 *                                                                            *
 * Purpose: merge two uint64 arrays                                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	uint64_array_merge(zbx_uint64_t **values, int *alloc, int *num, zbx_uint64_t *value, int value_num, int alloc_step)
{
	int	i;

	for (i = 0; i < value_num; i++)
		uint64_array_add(values, alloc, num, value[i], alloc_step);
}

/******************************************************************************
 *                                                                            *
 * Function: uint64_array_exists                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
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
 * Function: uint64_array_remove                                              *
 *                                                                            *
 * Purpose: remove uint64 values from array                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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

/******************************************************************************
 *                                                                            *
 * Function: uint64_array_remove_both                                         *
 *                                                                            *
 * Purpose: remove equal values from both arrays                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	uint64_array_remove_both(zbx_uint64_t *values, int *num, zbx_uint64_t *rm_values, int *rm_num)
{
	int	rindex, index;

	for (rindex = 0; rindex < *rm_num; rindex++)
	{
		index = get_nearestindex(values, sizeof(zbx_uint64_t), *num, rm_values[rindex]);
		if (index == *num || values[index] != rm_values[rindex])
			continue;

		memmove(&values[index], &values[index + 1], sizeof(zbx_uint64_t) * ((*num) - index - 1));
		(*num)--;
		memmove(&rm_values[rindex], &rm_values[rindex + 1], sizeof(zbx_uint64_t) * ((*rm_num) - rindex - 1));
		(*rm_num)--; rindex--;
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
 * Function: str2uint64                                                       *
 *                                                                            *
 * Purpose: convert string to 64bit unsigned integer                          *
 *                                                                            *
 * Parameters: str   - string to convert                                      *
 *             value - a pointer to converted value                           *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Function: is_hostname_char                                                 *
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the host name              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: in host name allowed characters: '0-9a-zA-Z. _-'                 *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_hostname_char(char c)
{
	if (0 != isalnum(c))
		return SUCCEED;

	if (c == '.' || c == ' ' || c == '_' || c == '-')
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: is_key_char                                                      *
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the item key               *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: in key allowed characters: '0-9a-zA-Z._-'                        *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_key_char(char c)
{
	if (0 != isalnum(c))
		return SUCCEED;

	if (c == '.' || c == '_' || c == '-')
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: is_function_char                                                 *
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the trigger function       *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: in trigger function allowed characters: 'a-z'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_function_char(char c)
{
	if (0 != islower(c))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: is_macro_char                                                    *
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the macro name             *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: allowed characters in macro names: '0-9A-Z._'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_macro_char(char c)
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
 * Function: is_time_function                                                 *
 *                                                                            *
 * Return value:  SUCCEED - given function is time-based                      *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
int	is_time_function(const char *func)
{
	return str_in_list("nodata,date,dayofmonth,dayofweek,time,now", func, ',');
}

/******************************************************************************
 *                                                                            *
 * Function: is_snmp_type                                                     *
 *                                                                            *
 * Return value:  SUCCEED  - the given type is one of regular SNMP types      *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
int	is_snmp_type(unsigned char type)
{
	return ITEM_TYPE_SNMPv1 == type || ITEM_TYPE_SNMPv2c == type || ITEM_TYPE_SNMPv3 == type ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: make_hostname                                                    *
 *                                                                            *
 * Purpose: replace all not-allowed hostname characters in the string         *
 *                                                                            *
 * Parameters: host - the target C-style string                               *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
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
 * Function: get_interface_type_by_item_type                                  *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: Interface type                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
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
			return INTERFACE_TYPE_ANY;
		default:
			return INTERFACE_TYPE_UNKNOWN;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: calculate_sleeptime                                              *
 *                                                                            *
 * Purpose: calculate sleep time for Zabbix processes                         *
 *                                                                            *
 * Parameters: nextcheck     - [IN] next check or -1 (FAIL) if nothing to do  *
 *             max_sleeptime - [IN] maximum sleep time, in seconds            *
 *                                                                            *
 * Return value: sleep time, in seconds                                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Function: parse_serveractive_element                                       *
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
