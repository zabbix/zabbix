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

#include "zbxexpr.h"

#include "zbxnum.h"
#include "zbxtime.h"

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

typedef struct zbx_flexible_interval
{
	zbx_time_period_t		period;
	int				delay;

	struct zbx_flexible_interval	*next;
}
zbx_flexible_interval_t;

struct zbx_custom_interval
{
	zbx_flexible_interval_t		*flexible;
	zbx_scheduler_interval_t	*scheduling;
};

/******************************************************************************
 *                                                                            *
 * Purpose: checks if current time is within given period                     *
 *                                                                            *
 * Parameters: period - [IN] preprocessed time period                         *
 *             tm     - [IN] broken-down time for comparison                  *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the period            *
 *                                                                            *
 ******************************************************************************/
static int	check_time_period(const zbx_time_period_t period, const struct tm *tm)
{
	int		day, time;

	day = 0 == tm->tm_wday ? 7 : tm->tm_wday;
	time = SEC_PER_HOUR * tm->tm_hour + SEC_PER_MIN * tm->tm_min + tm->tm_sec;

	return period.start_day <= day && day <= period.end_day && period.start_time <= time && time < period.end_time ?
			SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns delay value that is currently applicable                  *
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
				SUCCEED == check_time_period(flex_intervals->period, zbx_localtime_now(&now)))
		{
			current_delay = flex_intervals->delay;
		}

		flex_intervals = flex_intervals->next;
	}

	return -1 == current_delay ? default_delay : current_delay;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns time when next delay settings take effect                 *
 *                                                                            *
 * Parameters: flex_intervals - [IN] preprocessed flexible intervals          *
 *             now            - [IN] current time                             *
 *             next_interval  - [OUT] start of next delay interval            *
 *                                                                            *
 * Return value: SUCCEED - there is next interval                             *
 *               FAIL    - otherwise (in this case, next_interval is          *
 *                         unaffected)                                        *
 *                                                                            *
 ******************************************************************************/
static int	get_next_delay_interval(const zbx_flexible_interval_t *flex_intervals, time_t now,
		time_t *next_interval)
{
	int		day, time, next = 0, candidate;
	const struct tm	*tm;

	if (NULL == flex_intervals)
		return FAIL;

	tm = zbx_localtime_now(&now);
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

/*******************************************************************************
 *                                                                             *
 * Purpose: parses time of day                                                 *
 *                                                                             *
 * Parameters: time       - [OUT] Number of seconds since the beginning of     *
 *                                the day corresponding to a given time of     *
 *                                the day.                                     *
 *             text       - [IN] text to parse                                 *
 *             len        - [IN] number of characters available for parsing    *
 *             parsed_len - [OUT] number of characters recognized as time      *
 *                                                                             *
 * Return value: SUCCEED - text was successfully parsed as time of day         *
 *               FAIL    - otherwise (time and parsed_len remain untouched)    *
 *                                                                             *
 * Comments: !!! Don't forget to sync code with PHP !!!                        *
 *           Supported formats are hh:mm, h:mm and 0h:mm; 0 <= hours <= 24;    *
 *           0 <= minutes <= 59; if hours == 24 then minutes must be 0.        *
 *                                                                             *
 *******************************************************************************/
static int	time_parse(int *time, const char *text, int len, int *parsed_len)
{
	const int	old_len = len;
	const char	*ptr;
	int		hours, minutes;

	for (ptr = text; 0 < len && 0 != isdigit(*ptr) && 2 >= ptr - text; len--, ptr++)
		;

	if (SUCCEED != zbx_is_uint_n_range(text, ptr - text, &hours, sizeof(hours), 0, 24))
		return FAIL;

	if (0 >= len-- || ':' != *ptr++)
		return FAIL;

	for (text = ptr; 0 < len && 0 != isdigit(*ptr) && 2 >= ptr - text; len--, ptr++)
		;

	if (2 != ptr - text)
		return FAIL;

	if (SUCCEED != zbx_is_uint_n_range(text, 2, &minutes, sizeof(minutes), 0, 59))
		return FAIL;

	if (24 == hours && 0 != minutes)
		return FAIL;

	*parsed_len = old_len - len;
	*time = SEC_PER_HOUR * hours + SEC_PER_MIN * minutes;
	return SUCCEED;
}

/******************************************************************************
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
 * Purpose: parses text string into scheduler filter                          *
 *                                                                            *
 * Parameters: filter  - [IN/OUT] first filter                                *
 *             text    - [IN] text to parse                                   *
 *             len     - [IN/OUT] number of characters left to parse          *
 *             min     - [IN] minimal time unit value                         *
 *             max     - [IN] maximal time unit value                         *
 *             var_len - [IN] maximum number of characters for a filter       *
 *                            variable (<from>, <to>, <step>)                 *
 *                                                                            *
 * Return value: SUCCEED - filter was successfully parsed                     *
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

		if (SUCCEED != zbx_is_uint_n_range(pstart, pend - pstart, &start, sizeof(start), min, max))
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

			if (SUCCEED != zbx_is_uint_n_range(pstart, pend - pstart, &end, sizeof(end), min, max))
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

		if (SUCCEED != zbx_is_uint_n_range(pstart, pend - pstart, &step, sizeof(step), 1, end - start))
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
 * Parameters: filter  - [IN/OUT] first filter                                *
 *             text    - [IN] text to parse                                   *
 *             len     - [IN/OUT] number of characters left to parse          *
 *             min     - [IN] minimal time unit value                         *
 *             max     - [IN] maximal time unit value                         *
 *             var_len - [IN] maximum number of characters for filter         *
 *                            variable (<from>, <to>, <step>)                 *
 *                                                                            *
 * Return value: SUCCEED - filter was successfully parsed                     *
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
 * Parameters: interval - [IN/OUT] first interval                             *
 *             text     - [IN] text to parse                                  *
 *             len      - [IN] text length                                    *
 *                                                                            *
 * Return value: SUCCEED - interval was successfully parsed                   *
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
 * Parameters: interval - [IN/OUT] first interval                             *
 *             text     - [IN] text to parse                                  *
 *             len      - [IN] text length                                    *
 *                                                                            *
 * Return value: SUCCEED - interval was successfully parsed                   *
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

	if (SUCCEED != zbx_is_time_suffix(text, &interval->delay, (int)(ptr - text)))
		return FAIL;

	if (0 >= len-- || '/' != *ptr++)
		return FAIL;

	return time_period_parse(&interval->period, ptr, len);
}

/******************************************************************************
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

	if (SUCCEED != zbx_is_time_suffix(interval_str, simple_interval,
			(int)(NULL == (delim = strchr(interval_str, ';')) ? ZBX_LENGTH_UNLIMITED :
			delim - interval_str)))
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
 * Purpose: parses user macro and finds it's length                           *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             len   - [OUT] length simple interval string until separator    *
 *             sep   - [IN] separator to calculate length                     *
 *             value - [OUT] interval value                                   *
 *                                                                            *
 * Return Value:                                                              *
 *     SUCCEED - macro was parsed successfully                                *
 *     FAIL    - Macro parsing failed, the content of output variables is not *
 *               defined.                                                     *
 *                                                                            *
 ******************************************************************************/
static int	parse_simple_interval(const char *str, int *len, char sep, int *value)
{
	const char	*delim;

	if (SUCCEED != zbx_is_time_suffix(str, value,
			(int)(NULL == (delim = strchr(str, sep)) ? ZBX_LENGTH_UNLIMITED : delim - str)))
	{
		return FAIL;
	}

	*len = NULL == delim ? (int)strlen(str) : delim - str;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses user macro and finds it's length                           *
 *                                                                            *
 * Parameters: str  - [IN] string to check                                    *
 *             len  - [OUT] length of macro                                   *
 *                                                                            *
 * Return Value:                                                              *
 *     SUCCEED - macro was parsed successfully                                *
 *     FAIL    - Macro parsing failed, the content of output variables        *
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
 * Purpose: validates update interval, flexible and scheduling intervals      *
 *                                                                            *
 * Parameters: str   - [IN]  string to check                                  *
 *             error - [OUT] validation error                                 *
 *                                                                            *
 * Return Value:                                                              *
 *     SUCCEED - parsed successfully                                          *
 *     FAIL    - parsing failed                                               *
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
 * Purpose: checks if custom interval contains scheduling interval            *
 *                                                                            *
 * Parameters: custom_intervals - [IN]                                        *
 *                                                                            *
 * Return value: SUCCEED - if custom interval contains scheduling interval    *
 *               FAIL - otherwise                                             *
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
 * Parameters: custom_intervals - [IN]                                        *
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
 * Purpose: increments struct tm value by one second                          *
 *                                                                            *
 * Parameters: tm - [IN/OUT] tm structure to increment                        *
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
 * Purpose: finds daylight saving change time inside specified time period    *
 *                                                                            *
 * Parameters: time_start - [IN] time period start                            *
 *             time_end   - [IN] time period end                              *
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
 * Parameters: year - [IN] year (>1752)                                       *
 *             mon  - [IN] month (1-12)                                       *
 *             mday - [IN] month day (1-31)                                   *
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
 * Purpose: checks if specified date satisfies week day filter                *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             tm       - [IN] date & time to validate                        *
 *                                                                            *
 * Return value: SUCCEED - input date satisfies week day filter               *
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
 * Purpose: gets next nearest value that satisfies filter chain               *
 *                                                                            *
 * Parameters: filter - [IN] filter chain                                     *
 *             value  - [IN] current value                                    *
 *                      [OUT] next nearest value (>= than input value)        *
 *                                                                            *
 * Return value: SUCCEED - next nearest value was successfully found          *
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
 * Purpose: calculates next day that satisfies week day filter                *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             tm       - [IN/OUT] input/output date & time                   *
 *                                                                            *
 * Return value: SUCCEED - next day was found                                 *
 *               FAIL    - next day satisfying week day filter was not        *
 *                         found in current month                             *
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
	return (tm->tm_mday <= zbx_day_in_month(tm->tm_year + 1900, tm->tm_mon + 1) ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates next day that satisfies month and week day filters     *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             tm       - [IN/OUT] input/output date & time                   *
 *                                                                            *
 * Return value: SUCCEED - next day was found                                 *
 *               FAIL    - next day satisfying day filters was not            *
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
		if (tm->tm_mday > zbx_day_in_month(tm->tm_year + 1900, tm->tm_mon + 1))
			break;

		if (SUCCEED == scheduler_validate_wday_filter(interval, tm))
			return SUCCEED;

		tm->tm_mday++;

		/* check if the date is still valid - we haven't run out of month days */
		if (tm->tm_mday > zbx_day_in_month(tm->tm_year + 1900, tm->tm_mon + 1))
			break;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates time/day that satisfies specified filter               *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             level    - [IN] filter level, see ZBX_SCHEDULER_FILTER_*       *
 *                             defines                                        *
 *             tm       - [IN/OUT] input/output date & time                   *
 *                                                                            *
 * Return value: SUCCEED - next time/day was found                            *
 *               FAIL    - next time/day was not found on current filter      *
 *                         level                                              *
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
 * Purpose: Applies the day filter to the specified time/day calculating the  *
 *          next scheduled check.                                             *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             tm       - [IN/OUT] input/output date & time                   *
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
 * Purpose: Applies the hour filter to the specified time/day calculating the *
 *          next scheduled check                                              *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             tm       - [IN/OUT] input/output date & time                   *
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
 * Purpose: Applies the minute filter to the specified time/day calculating   *
 *          the next scheduled check.                                         *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             tm       - [IN/OUT] input/output date & time                   *
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
 * Purpose: Applies the second filter to the specified time/day calculating   *
 *          the next scheduled check                                          *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             tm       - [IN/OUT] input/output date & time                   *
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
 * Purpose: finds next timestamp satisfying one of intervals                  *
 *                                                                            *
 * Parameters: interval - [IN] scheduler interval                             *
 *             now      - [IN] current timestamp                              *
 *                                                                            *
 * Return Value: Timestamp when the next check must be scheduled.             *
 *                                                                            *
 ******************************************************************************/
static time_t	scheduler_get_nextcheck(zbx_scheduler_interval_t *interval, time_t now)
{
	struct tm	tm_start, tm, tm_dst;
	time_t		nextcheck = 0, current_nextcheck;

	tm_start = *zbx_localtime_now(&now);

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
 * Purpose: calculates nextcheck timestamp for item                           *
 *                                                                            *
 * Parameters: seed             - [IN] seed value applied to delay to spread  *
 *                                     item checks over delay period          *
 *             item_type        - [IN]                                        *
 *             simple_interval  - [IN] default delay value, can be overridden *
 *             custom_intervals - [IN] preprocessed custom intervals          *
 *             now              - [IN] current timestamp                      *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 * Comments: If item check is forbidden with delay=0 (default and flexible),  *
 *           a timestamp very far in the future is returned.                  *
 *                                                                            *
 *           Old algorithm: now+delay                                         *
 *           New one: preserve period, if delay==5, nextcheck = 0,5,10,15,... *
 *                                                                            *
 ******************************************************************************/
int	zbx_calculate_item_nextcheck(zbx_uint64_t seed, int item_type, int simple_interval,
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
 * Purpose: calculates nextcheck timestamp for item on unreachable host       *
 *                                                                            *
 * Parameters: simple_interval  - [IN] default delay value, can be overridden *
 *             custom_intervals - [IN] preprocessed custom intervals          *
 *             disable_until    - [IN] timestamp for next check               *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_calculate_item_nextcheck_unreachable(int simple_interval, const zbx_custom_interval_t *custom_intervals,
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
 * Purpose: validates time period and check if specified time is within it    *
 *                                                                            *
 * Parameters: period - [IN]  semicolon-separated list of time periods in one *
 *                            of the following formats:                       *
 *                              d1-d2,h1:m1-h2:m2                             *
 *                              or d1,h1:m1-h2:m2                             *
 *             time   - [IN]  time to check                                   *
 *             tz     - [IN]                                                  *
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
 * Purpose: calculates item nextcheck for Zabbix agent type items             *
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

	*nextcheck = zbx_calculate_item_nextcheck(itemid, ITEM_TYPE_ZABBIX, simple_interval, custom_intervals, now);
	zbx_custom_interval_free(custom_intervals);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates report nextcheck                                       *
 *                                                                            *
 * Parameters: now        - [IN] current timestamp                            *
 *             cycle      - [IN] report cycle                                 *
 *             weekdays   - [IN] week days report should be prepared,         *
 *                               bitmask (0x01 - Monday, 0x02 - Tuesday...)   *
 *             start_time - [IN] report start time in seconds after           *
 *                               midnight                                     *
 *                                                                            *
 * Return value: The timestamp when the report must be prepared or -1 if an   *
 *               error occurred.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_report_nextcheck(int now, unsigned char cycle, unsigned char weekdays, int start_time)
{
	struct tm	*tm;
	time_t		yesterday = now - SEC_PER_DAY;
	int		nextcheck, tm_hour, tm_min, tm_sec;

	if (NULL == (tm = localtime(&yesterday)))
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
