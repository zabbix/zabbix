/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "log.h"

#ifdef _WINDOWS
char	ZABBIX_SERVICE_NAME[ZBX_SERVICE_NAME_LEN] = APPLICATION_NAME;
char	ZABBIX_EVENT_SOURCE[ZBX_SERVICE_NAME_LEN] = APPLICATION_NAME;
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
 * Function: get_nodeid_by_id                                                 *
 *                                                                            *
 * Purpose: Get Node ID by resource ID                                        *
 *                                                                            *
 * Return value: Node ID                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	get_nodeid_by_id(zbx_uint64_t id)
{
	return (int)(id / __UINT64_C(100000000000000)) % 1000;
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
double	zbx_time()
{
#if defined(_WINDOWS)
	struct _timeb current;

	_ftime(&current);

	return (((double)current.time) + 1.0e-3 * ((double)current.millitm));
#else
	struct timeval current;

	gettimeofday(&current, NULL);

	return (((double)current.tv_sec) + 1.0e-6 * ((double)current.tv_usec));
#endif
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
double	zbx_current_time()
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
		/* exit if defined DEBUG, ignore otherwise */
		zbx_dbg_assert(0);
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

	exit(FAIL);
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
		/* exit if defined DEBUG, ignore otherwise */
		zbx_dbg_assert(0);
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

	exit(FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_realloc2                                                     *
 *                                                                            *
 * Purpose: changes the size of the memory block pointed to by src            *
 *          to size bytes                                                     *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void    *zbx_realloc2(const char *filename, int line, void *src, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	for (
		max_attempts = 10, size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = realloc(src, size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_realloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(FAIL);
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

	exit(FAIL);
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
#ifdef HAVE_FUNCTION_SETPROCTITLE
	char	title[MAX_STRING_LEN];
	va_list	args;

	va_start(args, fmt);
	zbx_vsnprintf(title, sizeof(title), fmt, args);
	va_end(args);

	setproctitle(title);
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
			sec2 = SEC_PER_HOUR * h2 + SEC_PER_MIN * m2 - 1;	/* do not include upper bound */

			if (day >= d1 && day <= d2 && sec >= sec1 && sec <= sec2)
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
	int		flex_delay, current_delay = SEC_PER_YEAR;

	if (NULL == flex_intervals || '\0' == *flex_intervals)
		return delay;

	for (s = flex_intervals; '\0' != *s; s = delim + 1)
	{
		delim = strchr(s, ';');

		zabbix_log(LOG_LEVEL_DEBUG, "delay period [%s]", s);

		if (2 == sscanf(s, "%d/%29[^;]s", &flex_delay, flex_period))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%d sec at %s", flex_delay, flex_period);
			
			if (flex_delay < current_delay && SUCCEED == check_time_period(flex_period, now))
				current_delay = flex_delay;
		}
		else
			zabbix_log(LOG_LEVEL_ERR, "wrong delay period format [%s]", s);

		if (NULL == delim)
			break;
	}
	
	if (SEC_PER_YEAR == current_delay)
		return delay;

	return current_delay == 0 ? SEC_PER_YEAR : current_delay;
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
			sec2 = SEC_PER_HOUR * h2 + SEC_PER_MIN * m2 - 1;	/* do not include upper bound */

			if (day >= d1 && day <= d2 && sec >= sec1 && sec <= sec2)	/* current period */
			{
				if (0 == next || next > now - sec + sec2)
					next = now - sec + sec2;
			}
			else if (day >= d1 && day <= d2 && sec < sec1)			/* will be active today */
			{
				if (0 == next || next > now - sec + sec1)
					next = now - sec + sec1;
			}
			else
			{
				int	next_day;

				next_day = (day + 1 <= 7 ? day + 1 : 1);

				if (next_day >= d1 && next_day <= d2)                   /* will be active tomorrow */
				{
					if (0 == next || next > now - sec + SEC_PER_DAY + sec1)
						next = now - sec + SEC_PER_DAY + sec1;
				}
				else                                                    /* later in the future */
				{
					int day_diff = -1;

					if (day < d1)
						day_diff = d1 - day;
					if (day >= d2)
						day_diff = (d1 + 7) - day;
					if (day >= d1 && day < d2)
					{
						/* should never happen */
						zabbix_log(LOG_LEVEL_ERR,
								"could not deduce day difference [%s, day=%d, time=%02d:%02d:%02d]",
								s, day, tm->tm_hour, tm->tm_min, tm->tm_sec);
						day_diff = -1;
					}

					if (-1 != day_diff && (0 == next || next > now - sec + SEC_PER_DAY * day_diff + sec1))
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
 * Parameters: delay - [IN] default delay value, can be overridden            *
 *             flex_intervals - [IN] descriptions of flexible intervals       *
 *                                   in the form [dd/d1-d2,hh:mm-hh:mm;]      *
 *             now - current timestamp                                        *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 * Comments: if item check is forbidden with delay=0 (default and flexible),  *
 *           a timestamp approximately one year in the future is returned     *
 *                                                                            *
 *           Old algorithm: now+delay                                         *
 *           New one: preserve period, if delay==5, nextcheck = 0,5,10,15,... *
 *           !!! Don't forget to sync code with PHP !!!                       *
 *                                                                            *
 ******************************************************************************/
int	calculate_item_nextcheck(zbx_uint64_t itemid, int item_type, int delay,
		const char *flex_intervals, time_t now, int *effective_delay)
{
	const char	*__function_name = "calculate_item_nextcheck";
	int		nextcheck;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " delay:%d flex_intervals:'%s' now:%d",
			__function_name, itemid, delay, NULL == flex_intervals ? "" : flex_intervals, now);

	if (0 == delay)
		delay = SEC_PER_YEAR;

	/* special processing of active items to see better view in queue */
	if (item_type == ITEM_TYPE_ZABBIX_ACTIVE)
	{
		nextcheck = (int)now + delay;
	}
	else
	{
		int	current_delay;
		time_t	next_interval;

		current_delay = get_current_delay(delay, flex_intervals, now);

		if (FAIL != get_next_delay_interval(flex_intervals, now, &next_interval) && now + current_delay > next_interval)
		{
			/* next check falls out of the current interval */
			do
			{
				current_delay = get_current_delay(delay, flex_intervals, next_interval + 1);

				/* as soon as item check in the interval is not forbidden with delay=0, use it */
				if (SEC_PER_YEAR != current_delay)
					break;

				get_next_delay_interval(flex_intervals, next_interval + 1, &next_interval);
			}
			while (next_interval - now < SEC_PER_WEEK);	/* checking the nearest week for delay!=0 */

			now = next_interval;
		}

		delay = current_delay;
		nextcheck = delay * (int)(now / (time_t)delay) + (int)(itemid % (zbx_uint64_t)delay);

		while (nextcheck <= now)
			nextcheck += delay;
	}

	if (NULL != effective_delay)
		*effective_delay = delay;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() nextcheck:%d delay:%d", __function_name, nextcheck, delay);

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
	int		nums, dots, res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __function_name, ip);

	nums = 0;
	dots = 0;
	while ('\0' != *p)
	{
		if ('0' <= *p && *p <= '9')
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
static int	is_ip6(const char *ip)
{
	const char	*__function_name = "is_ip6";
	const char	*p = ip;
	int		nums = 0, is_nums = 0, colons = 0, dcolons = 0, res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __function_name, ip);

	while ('\0' != *p)
	{
		if ((*p >= '0' && *p <= '9') || (*p >= 'a' && *p <= 'f') || (*p >= 'A' && *p <= 'F'))
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

#if defined(HAVE_IPV6)
/******************************************************************************
 *                                                                            *
 * Function: expand_ipv6                                                      *
 *                                                                            *
 * Purpose: convert short ipv6 addresses to expanded type                     *
 *                                                                            *
 * Parameters: ip - IPv6 IPs [12fc::2]                                        *
 *             buf - result value [12fc:0000:0000:0000:0000:0000:0000:0002]   *
 *                                                                            *
 * Return value: FAIL - invalid IP address, SUCCEED - conversion OK           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	expand_ipv6(const char *ip, char *str, size_t str_len )
{
	unsigned int	i[8]; /* x:x:x:x:x:x:x:x */
	char		buf[5], *ptr;
	int		c, dc, pos = 0, j, len, ip_len;

	c = 0; /* colons count */
	for (ptr = strchr(ip, ':'); ptr != NULL; ptr = strchr(ptr + 1, ':'))
	{
		c ++;
	}

	if (c < 2 || c > 7)
	{
		return FAIL;
	}

	ip_len = strlen(ip);
	if ((ip[0] == ':' && ip[1] != ':') || (ip[ip_len - 1] == ':' && ip[ip_len - 2] != ':'))
	{
		return FAIL;
	}

	memset(i, 0x00, sizeof(i));

	dc  = 0; /* double colon flag */
	len = 0;
	for (j = 0; j<ip_len; j++)
	{
		if ((ip[j] >= '0' && ip[j] <= '9') || (ip[j] >= 'A' && ip[j] <= 'F') || (ip[j] >= 'a' && ip[j] <= 'f'))
		{
			if (len > 3)
				return FAIL;
			buf[len ++] = ip[j];
		}
		else if (ip[j] != ':')
			return FAIL;

		if (ip[j] == ':' || ip[j + 1] == '\0')
		{
			if (len)
			{
				buf[len] = 0x00;
				sscanf(buf, "%x", &i[pos]);
				pos ++;
				len = 0;
			}

			if (ip[j + 1] == ':')
			{
				if (dc == 0)
				{
					dc = 1;
					pos = ( 8 - c ) + pos + (j == 0 ? 1 : 0);
				}
				else
					return FAIL;
			}
		}
	}

	zbx_snprintf(str, str_len, "%04x:%04x:%04x:%04x:%04x:%04x:%04x:%04x", i[0], i[1], i[2], i[3], i[4], i[5], i[6], i[7]);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: collapse_ipv6                                                    *
 *                                                                            *
 * Purpose: convert array to IPv6 collapsed type                              *
 *                                                                            *
 * Parameters: ip - [IN] full IPv6 address [12fc:0:0:0:0:0:0:2]               *
 *                  [OUT] short IPv6 address [12fc::0]                        *
 *             ip_len - [IN] ip buffer len                                    *
 *                                                                            *
 * Return value: pointer to result buffer                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
char	*collapse_ipv6(char *str, size_t str_len)
{
	int		i, c = 0, m = 0, idx = -1, idx2 = -1, offset = 0;
	unsigned int	j[8];

	if (8 != sscanf(str, "%x:%x:%x:%x:%x:%x:%x:%x", &j[0], &j[1], &j[2], &j[3], &j[4], &j[5], &j[6], &j[7]))
		return str;

	for (i = 0; i <= 8; i++)
	{
		if (i < 8 && j[i] == 0)
		{
			if (idx2 == -1)
				idx2 = i;
			c++;
		}
		else
		{
			if (c != 0 && c > m)
			{
				m = c;
				idx = idx2;
			}
			c = 0;
			idx2 = -1;
		}
	}

	for (i = 0; i < 8; i++)
	{
		if (j[i] != 0 || idx == -1 || i < idx)
		{
			offset += zbx_snprintf(str + offset, str_len - offset, "%x", j[i]);
			if (i > idx)
				idx = -1;
			if (i < 7)
				offset += zbx_snprintf(str + offset, str_len - offset, ":");
		}
		else if (idx == i)
		{
			offset += zbx_snprintf(str + offset, str_len - offset, ":");
			if (idx == 0)
				offset += zbx_snprintf(str + offset, str_len - offset, ":");
		}
	}

	return str;
}

/******************************************************************************
 *                                                                            *
 * Function: ip6_in_list                                                      *
 *                                                                            *
 * Purpose: check if ip matches range of ip addresses                         *
 *                                                                            *
 * Parameters: list -  IPs [12fc::2-55,::45]                                  *
 *                                                                            *
 * Return value: FAIL - out of range, SUCCEED - within the range              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	ip6_in_list(char *list, char *ip)
{
	char	*start, *comma = NULL, *dash = NULL, buffer[MAX_STRING_LEN];
	int	i[8], j[9], ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In ip6_in_list(list:%s,ip:%s)", list, ip);

	if (FAIL == expand_ipv6(ip, buffer, sizeof(buffer)))
	{
		goto out;
	}

	if (sscanf(buffer, "%x:%x:%x:%x:%x:%x:%x:%x", &i[0], &i[1], &i[2], &i[3], &i[4], &i[5], &i[6], &i[7]) != 8)
	{
		goto out;
	}

	for (start = list; start[0] != '\0';)
	{

		if (NULL != (comma = strchr(start, ',')))
		{
			comma[0] = '\0';
		}

		if (NULL != (dash = strchr(start, '-')))
		{
			dash[0] = '\0';
			if (sscanf(dash + 1, "%x", &j[8]) != 1)
			{
				goto next;
			}
		}

		if (FAIL == expand_ipv6(start, buffer, sizeof(buffer)))
		{
			goto next;
		}

		if (sscanf(buffer, "%x:%x:%x:%x:%x:%x:%x:%x", &j[0], &j[1], &j[2], &j[3], &j[4], &j[5], &j[6], &j[7]) != 8)
		{
			goto next;
		}

		if (dash == NULL)
		{
			j[8] = j[7];
		}

		if (i[0] == j[0] && i[1] == j[1] && i[2] == j[2] && i[3] == j[3] &&
			i[4] == j[4] && i[5] == j[5] && i[6] == j[6] &&
			i[7] >= j[7] && i[7] <= j[8])
		{
			ret = SUCCEED;
			break;
		}
next:
		if (dash != NULL)
		{
			dash[0] = '-';
			dash = NULL;
		}

		if (comma != NULL)
		{
			comma[0] = ',';
			start = comma + 1;
			comma = NULL;
		}
		else
		{
			break;
		}
	}
out:
	if (dash != NULL)
	{
		dash[0] = '-';
	}

	if (comma != NULL)
	{
		comma[0] = ',';
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of ip6_in_list():%s",
			zbx_result_string(ret));

	return ret;
}
#endif /*HAVE_IPV6*/
/******************************************************************************
 *                                                                            *
 * Function: ip_in_list                                                       *
 *                                                                            *
 * Purpose: check if ip matches range of ip addresses                         *
 *                                                                            *
 * Parameters: list -  IPs [192.168.1.1-244,192.168.1.250]                    *
 *                                                                            *
 * Return value: FAIL - out of range, SUCCEED - within the range              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	ip_in_list(char *list, char *ip)
{
	int	i[4], j[5];
	int	ret = FAIL;
	char	*start = NULL, *comma = NULL, *dash = NULL;

	zabbix_log( LOG_LEVEL_DEBUG, "In ip_in_list(list:%s,ip:%s)", list, ip);

	if (sscanf(ip, "%d.%d.%d.%d", &i[0], &i[1], &i[2], &i[3]) != 4)
	{
#if defined(HAVE_IPV6)
		ret = ip6_in_list(list, ip);
#endif /*HAVE_IPV6*/
		goto out;
	}

	for (start = list; start[0] != '\0';)
	{
		if (NULL != (comma = strchr(start, ',')))
		{
			comma[0] = '\0';
		}

		if (NULL != (dash = strchr(start, '-')))
		{
			dash[0] = '\0';
			if (sscanf(dash + 1, "%d", &j[4]) != 1)
			{
				goto next;
			}
		}

		if (sscanf(start, "%d.%d.%d.%d", &j[0], &j[1], &j[2], &j[3]) != 4)
		{
			goto next;
		}

		if (dash == NULL)
		{
			j[4] = j[3];
		}

		if (i[0] == j[0] && i[1] == j[1] && i[2] == j[2] && i[3] >= j[3] && i[3] <= j[4])
		{
			ret = SUCCEED;
			break;
		}
next:
		if (dash != NULL)
		{
			dash[0] = '-';
			dash = NULL;
		}

		if (comma != NULL)
		{
			comma[0] = ',';
			start = comma + 1;
			comma = NULL;
		}
		else
		{
			break;
		}
	}

out:
	if (dash != NULL)
	{
		dash[0] = '-';
	}

	if (comma != NULL)
	{
		comma[0] = ',';
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of ip_in_list():%s",
			zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: int_in_list                                                      *
 *                                                                            *
 * Purpose: check if integer matches a list of integers                       *
 *                                                                            *
 * Parameters: list -  integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699          *
 *             value-  value                                                  *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the period            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	int_in_list(char *list, int value)
{
	char	*start = NULL, *end = NULL;
	int	i1,i2;
	int	ret = FAIL;
	char	c = '\0';

	zabbix_log( LOG_LEVEL_DEBUG, "In int_in_list(list:%s,value:%d)", list, value);

	for (start = list; start[0] != '\0';)
	{
		end=strchr(start, ',');

		if (end != NULL)
		{
			c=end[0];
			end[0]='\0';
		}

		if (sscanf(start,"%d-%d",&i1,&i2) == 2)
		{
			if (value>=i1 && value<=i2)
			{
				ret = SUCCEED;
				break;
			}
		}
		else
		{
			if (atoi(start) == value)
			{
				ret = SUCCEED;
				break;
			}
		}

		if (end != NULL)
		{
			end[0]=c;
			start=end+1;
		}
		else
		{
			break;
		}
	}

	if (end != NULL)
	{
		end[0]=c;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of int_in_list():%s",
			zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: is_double_prefix                                                 *
 *                                                                            *
 * Purpose: check if the string is double                                     *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is double                              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: the function supports suffixes K,M,G,T,s,m,h,d,w                 *
 *                                                                            *
 ******************************************************************************/
int	is_double_prefix(const char *c)
{
	int i;
	int dot=-1;

	for (i=0;c[i]!=0;i++)
	{
		/* Negative number? */
		if (c[i]=='-' && i==0)
		{
			continue;
		}

		if ((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}

		if ((c[i]=='.')&&(dot==-1))
		{
			dot=i;
			continue;
		}
		/* Last character is suffix 'K', 'M', 'G', 'T', 's', 'm', 'h', 'd', 'w' */
		if (strchr("KMGTsmhdw", c[i])!=NULL && c[i+1]=='\0')
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
 * Purpose: check if the string is double                                     *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is double                              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	is_double(const char *c)
{
	int i;
	int dot=-1;
	int len;

	for (i=0; c[i]==' ' && c[i]!=0;i++); /* trim left spaces */

	for (len=0; c[i]!=0; i++, len++)
	{
		/* Negative number? */
		if (c[i]=='-' && i==0)
		{
			continue;
		}

		if ((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}

		if ((c[i]=='.')&&(dot==-1))
		{
			dot=i;
			continue;
		}

		if (c[i]==' ') /* check right spaces */
		{
			for ( ; c[i]==' ' && c[i]!=0;i++); /* trim right spaces */

			if (c[i]==0) break; /* SUCCEED */
		}

		return FAIL;
	}

	if (len <= 0) return FAIL;

	if (len == 1 && dot!=-1) return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_uint_prefix                                                   *
 *                                                                            *
 * Purpose: check if the string is unsigned integer                           *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: the function supports suffixes 's', 'm', 'h', 'd', and 'w'       *
 *                                                                            *
 ******************************************************************************/
int	is_uint_prefix(const char *c)
{
	int	i = 0;

	while (c[i]==' ') /* trim left spaces */
		i++;
	
	if (!isdigit(c[i]))
		return FAIL;
	else
		do
			i++;
		while (isdigit(c[i]));

	if (c[i]=='s' || c[i]=='m' || c[i]=='h' || c[i]=='d' || c[i]=='w') /* check suffix */
		i++;

	while (c[i]==' ') /* trim right spaces */
		i++;

	return c[i]=='\0' ? SUCCEED : FAIL;
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
 ******************************************************************************/
int	is_uint(const char *c)
{
	int	i;
	int	len;

	for (i=0; c[i]==' ' && c[i]!=0;i++); /* trim left spaces */

	for (len=0; c[i]!=0; i++,len++)
	{
		if ((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}

		if (c[i]==' ') /* check right spaces */
		{
			for ( ; c[i]==' ' && c[i]!=0;i++); /* trim right spaces */

			if (c[i]==0) break; /* SUCCEED */
		}
		return FAIL;
	}

	if (len <= 0) return FAIL;

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
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the beginning of string is a signed integer       *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
int	is_int_prefix(const char *c)
{
	int	i = 0;

	while (c[i]==' ') /* trim left spaces */
		i++;

	if (c[i]=='-' || c[i]=='+')
		i++;
	
	if (!isdigit(c[i]))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_uint64                                                        *
 *                                                                            *
 * Purpose: check if the string is 64bit unsigned integer                     *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - the string is not number or overflow                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	is_uint64(const char *str, zbx_uint64_t *value)
{
	register zbx_uint64_t	max_uint64 = ~(zbx_uint64_t)__UINT64_C(0);
	register zbx_uint64_t	value_uint64 = 0, c;

	while ('\0' != *str)
	{
		if (*str >= '0' && *str <= '9')
		{
			c = (zbx_uint64_t)(unsigned char)(*str - '0');
			if ((max_uint64 - c) / 10 >= value_uint64)
				value_uint64 = value_uint64 * 10 + c;
			else
				return FAIL;	/* overflow */
			str++;
		}
		else
			return FAIL;	/* not a digit */
	}

	if (NULL != value)
		*value = value_uint64;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_ushort                                                        *
 *                                                                            *
 * Purpose: check if the string is 16bit unsigned integer                     *
 *                                                                            *
 * Parameters: str - string to check                                          *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - the string is not number or overflow                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	is_ushort(const char *str, unsigned short *value)
{
	register unsigned short	max_ushort = 0xffff;
	register unsigned short	value_ushort = 0, c;

	if ('\0' == *str)
		return FAIL;

	while ('\0' != *str)
	{
		if (*str >= '0' && *str <= '9')
		{
			c = (unsigned short)(unsigned char)(*str - '0');
			if ((max_ushort - c) / 10 >= value_ushort)
				value_ushort = value_ushort * 10 + c;
			else
				return FAIL;	/* overflow */
			str++;
		}
		else
			return FAIL;	/* not a digit */
	}

	if (NULL != value)
		*value = value_ushort;

	return SUCCEED;
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
		if ((*str < '0' || *str > '9') && (*str < 'a' || *str > 'f') && (*str < 'A' || *str > 'F'))
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
		if (!isxdigit(*str))
			return FAIL;
		if (!isxdigit(*(str + 1)))
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
 * Parameters: list -  integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699          *
 *             value-  value                                                  *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the list              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	uint64_in_list(char *list, zbx_uint64_t value)
{
	char		*start = NULL, *end = NULL;
	zbx_uint64_t	i1,i2,tmp_uint64;
	int		ret = FAIL;
	char		c = '\0';

	zabbix_log( LOG_LEVEL_DEBUG, "In int_in_list(list:%s,value:" ZBX_FS_UI64 ")", list, value);

	for (start = list; start[0] != '\0';)
	{
		end=strchr(start, ',');

		if (end != NULL)
		{
			c=end[0];
			end[0]='\0';
		}

		if (sscanf(start,ZBX_FS_UI64 "-" ZBX_FS_UI64,&i1,&i2) == 2)
		{
			if (value>=i1 && value<=i2)
			{
				ret = SUCCEED;
				break;
			}
		}
		else
		{
			ZBX_STR2UINT64(tmp_uint64,start);
			if (tmp_uint64 == value)
			{
				ret = SUCCEED;
				break;
			}
		}

		if (end != NULL)
		{
			end[0]=c;
			start=end+1;
		}
		else
		{
			break;
		}
	}

	if (end != NULL)
	{
		end[0]=c;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of int_in_list():%s",
			zbx_result_string(ret));

	return ret;
}

/*
 * Get nearest index of sorted elements in array.
 *     p - pointer to array of elements
 *     sz - size of one element in array
 *     num - number of elements
 */
int	get_nearestindex(void *p, size_t sz, int num, zbx_uint64_t id)
{
	int		first_index, last_index, index;
	zbx_uint64_t	element_id;

	if (num == 0)
		return 0;

	first_index = 0;
	last_index = num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		element_id = *(zbx_uint64_t *)((char *)p + index * sz);
		if (element_id == id)
			return index;
		else if (last_index == first_index)
		{
			if (element_id < id)
				index++;
			return index;
		}
		else if (element_id < id)
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
int	uint64_array_exists(zbx_uint64_t *values, int num, zbx_uint64_t value)
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
void	uint64_array_remove(zbx_uint64_t *values, int *num, zbx_uint64_t *rm_values, int rm_num)
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
 * Function: str2uint                                                         *
 *                                                                            *
 * Purpose: convert string to unsigned integer (currently 31bit uint)         *
 *                                                                            *
 * Parameters: str - string to convert                                        *
 *                                                                            *
 * Return value: converted unsigned integer                                   *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: the function automatically processes suffixes s, m, h, d, w      *
 *                                                                            *
 ******************************************************************************/
int	str2uint(const char *str)
{
	size_t	sz = strlen(str) - 1;
	int	factor = 1;

	switch (str[sz])
	{
		case 's': factor = 1;			break;
		case 'm': factor = SEC_PER_MIN;		break;
		case 'h': factor = SEC_PER_HOUR;	break;
		case 'd': factor = SEC_PER_DAY;		break;
		case 'w': factor = SEC_PER_WEEK;	break;
	}

	return atoi(str) * factor;
}

/******************************************************************************
 *                                                                            *
 * Function: str2uint64                                                       *
 *                                                                            *
 * Purpose: convert string to 64bit unsigned integer                          *
 *                                                                            *
 * Parameters: str - string to convert                                        *
 *             value - pointer to returned value                              *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: the function automatically processes suffixes 'K','M','G','T'    *
 *                                                                            *
 ******************************************************************************/
int	str2uint64(char *str, zbx_uint64_t *value)
{
	size_t		sz;
	int		ret;
	zbx_uint64_t	factor = 1;
	char		c = '\0';

	sz = strlen(str) - 1;

	if (str[sz] == 'K')
	{
		c = str[sz];
		factor = 1024;
	}
	else if (str[sz] == 'M')
	{
		c = str[sz];
		factor = 1024 * 1024;
	}
	else if (str[sz] == 'G')
	{
		c = str[sz];
		factor = 1024 * 1024 * 1024;
	}
	else if (str[sz] == 'T')
	{
		c = str[sz];
		factor = 1024 * 1024 * 1024;
		factor *= 1024;
	}

	if ('\0' != c)
		str[sz] = '\0';

	if (SUCCEED == (ret = is_uint64(str, value)))
		*value *= factor;

	if ('\0' != c)
		str[sz] = c;

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
 * Comments: the function automatically processes suffixes 'K','M','G','T'    *
 *           and 's','m','h','d','w'                                          *
 *                                                                            *
 ******************************************************************************/
double	str2double(const char *str)
{
	size_t	sz = strlen(str) - 1;
	double	factor = 1;

	switch (str[sz])
	{
		case 'K': factor = 1024;			break;
		case 'M': factor = 1024*1024;			break;
		case 'G': factor = 1024*1024*1024;		break;
		case 'T': factor = 1024*1024*1024*(double)1024;	break;
		case 's': factor = 1;				break;
		case 'm': factor = SEC_PER_MIN;			break;
		case 'h': factor = SEC_PER_HOUR;		break;
		case 'd': factor = SEC_PER_DAY;			break;
		case 'w': factor = SEC_PER_WEEK;		break;
	}

	return atof(str) * factor;
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
 * Comments: in host name allowed characters: '0-9a-zA-Z. _-]'                *
 *           !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
int	is_hostname_char(char c)
{
	if (c >= 'a' && c <= 'z')
		return SUCCEED;

	if (c >= 'A' && c <= 'Z')
		return SUCCEED;

	if (c == '.' || c == ' ' || c == '_' || c == '-')
		return SUCCEED;

	if (c >= '0' && c <= '9')
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
 * Comments: in key allowed characters: '0-9a-zA-Z.,_-]'                      *
 *           !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
int	is_key_char(char c)
{
	if (c >= 'a' && c <= 'z')
		return SUCCEED;

	if (c == '.' || c == ',' || c == '_' || c == '-')
		return SUCCEED;

	if (c >= 'A' && c <= 'Z')
		return SUCCEED;

	if (c >= '0' && c <= '9')
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
 *           !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
int	is_function_char(char c)
{
	if (c >= 'a' && c <= 'z')
		return SUCCEED;

	return FAIL;
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
