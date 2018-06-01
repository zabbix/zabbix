/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "zbxmockdata.h"

/* output formats */
#define ZBX_MOCK_FORMAT_DATE		"%04d-%02d-%02d"
#define ZBX_MOCK_FORMAT_TIME		"%02d:%02d:%02d"
#define ZBX_MOCK_FORMAT_DATETIME	ZBX_MOCK_FORMAT_DATE " " ZBX_MOCK_FORMAT_TIME
#define ZBX_MOCK_FORMAT_NS		".%09d"
#define ZBX_MOCK_FORMAT_TZ		"%c%02d:%02d"

#define ZBX_MOCK_TZ_MAX		7

#define ZBX_MOCK_TIME_DATE	0x0001
#define ZBX_MOCK_TIME_TIME	0x0002
#define ZBX_MOCK_TIME_NS	0x0004
#define ZBX_MOCK_TIME_TZ	0x0008

/******************************************************************************
 *                                                                            *
 * Function: ts_get_component_end                                             *
 *                                                                            *
 * Purpose: finds the next character after numeric time component             *
 *                                                                            *
 * Parameters: text - [IN] the text                                           *
 *                                                                            *
 * Return value: text after the time component                                *
 *                                                                            *
 * Comments: If the first character is not a digit the the source text is     *
 *           returned.                                                        *
 *                                                                            *
 ******************************************************************************/
static const char	*ts_get_component_end(const char *text)
{
	while (0 != isdigit(*text))
		text++;

	return text;
}

/******************************************************************************
 *                                                                            *
 * Function: ts_get_date                                                      *
 *                                                                            *
 * Purpose: parses year, month and day from date component having             *
 *          YYYY-MM-DD format                                                 *
 *                                                                            *
 * Parameters: text  - [IN] the text                                          *
 *             year  - [OUT] the year                                         *
 *             month - [OUT] the month                                        *
 *             day   - [OUT] the day                                          *
 *             pnext - [OUT] text after date component                        *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the date was parsed successfully          *
 *               ZBX_MOCK_NOT_A_TIMESTAMP - invalid date format               *
 *                                                                            *
 * Comments: The year, month, day limits are not validated.                   *
 *                                                                            *
 ******************************************************************************/
static zbx_mock_error_t	ts_get_date(const char *text, int *year, int *month, int *day, const char **pnext)
{
	const char	*year_end, *month_end, *day_end;
	int		value_year, value_month, value_day;

	year_end = ts_get_component_end(text);
	if (year_end - text != 4 || '-' != *year_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	month_end = ts_get_component_end(year_end + 1);
	if (2 > month_end - year_end || 3 < month_end - year_end || '-' != *month_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	day_end = ts_get_component_end(month_end + 1);

	if (2 > day_end - month_end || 3 < day_end - month_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	value_year = atoi(text);
	if (1970 > value_year || 2038 < value_year)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	value_month = atoi(year_end + 1);
	if (12 < value_month)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	value_day = atoi(month_end + 1);
	if (value_day > zbx_day_in_month(value_year, value_month))
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	*pnext = day_end;
	*year = value_year;
	*month = value_month;
	*day = value_day;

	return ZBX_MOCK_SUCCESS;
}

/******************************************************************************
 *                                                                            *
 * Function: ts_get_time                                                      *
 *                                                                            *
 * Purpose: parses hours, minutes and seconds from time component having      *
 *          HH:MM:SS format                                                   *
 *                                                                            *
 * Parameters: text    - [IN] the text                                        *
 *             hours   - [OUT] the hours                                      *
 *             minutes - [OUT] the minutes                                    *
 *             seconds - [OUT] the seconds                                    *
 *             pnext   - [OUT] text after time component                      *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the time was parsed successfully          *
 *               ZBX_MOCK_NOT_A_TIMESTAMP - invalid time format               *
 *                                                                            *
 * Comments: The hours, minutes and seconds limits are not validated.         *
 *                                                                            *
 ******************************************************************************/
static zbx_mock_error_t	ts_get_time(const char *text, int *hours, int *minutes, int *seconds, const char **pnext)
{
	const char	*hours_end, *minutes_end, *seconds_end;
	int		value_hours, value_minutes, value_seconds;

	hours_end = ts_get_component_end(text);
	if (hours_end == text || ':' != *hours_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	minutes_end = ts_get_component_end(hours_end + 1);
	if (minutes_end - hours_end != 3 || ':' != *minutes_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	seconds_end = ts_get_component_end(minutes_end + 1);

	if (seconds_end - minutes_end != 3)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	value_hours = atoi(text);
	if (24 <= value_hours)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	value_minutes = atoi(hours_end + 1);
	if (60 <= value_minutes)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	value_seconds = atoi(minutes_end + 1);
	if (60 <= value_seconds)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	*pnext = seconds_end;
	*hours = value_hours;
	*minutes = value_minutes;
	*seconds = value_seconds;

	return ZBX_MOCK_SUCCESS;
}

/******************************************************************************
 *                                                                            *
 * Function: ts_get_ns                                                        *
 *                                                                            *
 * Purpose: parses nanoseconds from time component having .NNNNNNNNN format   *
 *                                                                            *
 * Parameters: text  - [IN] the text                                          *
 *             ns    - [OUT] nanoseconds                                      *
 *             pnext - [OUT] text after time component                        *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the nanoseconds was parsed successfully   *
 *               ZBX_MOCK_NOT_A_TIMESTAMP - invalid time format               *
 *                                                                            *
 * Comments: The nanoseconds limits are not validated.                        *
 *                                                                            *
 ******************************************************************************/
static zbx_mock_error_t	ts_get_ns(const char *text, int *ns, const char **pnext)
{
	const char	*ns_end;
	int		pad;

	ns_end = ts_get_component_end(text + 1);
	if (ns_end == text + 1 || 10 < ns_end - text)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	*pnext = ns_end;
	*ns = atoi(text + 1);

	pad = 9 - (ns_end - text);

	while (0 <= pad--)
		*ns *= 10;

	return ZBX_MOCK_SUCCESS;
}

/******************************************************************************
 *                                                                            *
 * Function: ts_get_tz                                                        *
 *                                                                            *
 * Purpose: parses timezone offset seconds from timezone component having     *
 *         (+|-)HH[:MM] format                                                *
 *                                                                            *
 * Parameters: text  - [IN] the text                                          *
 *             sec   - [OUT]  timezone offset seconds                         *
 *             pnext - [OUT] text after timezone component                    *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the timezone was parsed successfully      *
 *               ZBX_MOCK_NOT_A_TIMESTAMP - invalid time format               *
 *                                                                            *
 * Comments: The timezone offset limits are not validated.                    *
 *                                                                            *
 ******************************************************************************/
static zbx_mock_error_t	ts_get_tz(const char *text, int *sec, const char **pnext)
{
	const char	*tz_end;
	int		hours = 0, minutes = 0;

	tz_end = ts_get_component_end(text + 1);
	if (tz_end == text + 1)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	hours = atoi(text + 1);

	if (':' == *tz_end)
	{
		minutes = atoi(tz_end + 1);
		tz_end = ts_get_component_end(tz_end + 1);
		if (tz_end == text + 1)
			return ZBX_MOCK_NOT_A_TIMESTAMP;
	}

	*pnext = tz_end;

	*sec = hours * SEC_PER_HOUR + minutes * SEC_PER_MIN;
	if ('-' == *text)
		*sec = -*sec;

	return ZBX_MOCK_SUCCESS;
}

/******************************************************************************
 *                                                                            *
 * Function: is_leap_year                                                     *
 *                                                                            *
 * Return value:  SUCCEED - year is a leap year                               *
 *                FAIL    - year is not a leap year                           *
 *                                                                            *
 ******************************************************************************/
static int	is_leap_year(int year)
{
	return 0 == year % 4 && (0 != year % 100 || 0 == year % 400) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_time_to_localtime                                            *
 *                                                                            *
 * Purpose: converts timestamp to a broken-down time representation and       *
 *          timezone offset (in seconds).                                     *
 *                                                                            *
 * Parameters: timestamp - [IN] the number of seconds since Epoch             *
 *             local     - [OUT] broken-down time representation              *
 *             tz_offset - [OUT] timezone offset in seconds                   *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the time was converted successfully       *
 *               ZBX_MOCK_INTERNAL_ERROR - invalid timestamp was specified    *
 *                                                                            *
 ******************************************************************************/
static zbx_mock_error_t	zbx_time_to_localtime(time_t timestamp, struct tm *local, int *tz_offset)
{
	struct tm	tm_utc, tm_local;

	if (NULL == gmtime_r(&timestamp, &tm_utc))
		return ZBX_MOCK_INTERNAL_ERROR;

	if (NULL == localtime_r(&timestamp, &tm_local))
		return ZBX_MOCK_INTERNAL_ERROR;

	*tz_offset = (tm_local.tm_yday - tm_utc.tm_yday) * SEC_PER_DAY +
			(tm_local.tm_hour - tm_utc.tm_hour) * SEC_PER_HOUR +
			(tm_local.tm_min - tm_utc.tm_min) * SEC_PER_MIN;

	while (tm_local.tm_year > tm_utc.tm_year)
		*tz_offset += (SUCCEED == is_leap_year(tm_utc.tm_year++) ? SEC_PER_YEAR + SEC_PER_DAY : SEC_PER_YEAR);

	while (tm_local.tm_year < tm_utc.tm_year)
		*tz_offset -= (SUCCEED == is_leap_year(--tm_utc.tm_year) ? SEC_PER_YEAR + SEC_PER_DAY : SEC_PER_YEAR);

	*local = tm_local;

	return ZBX_MOCK_SUCCESS;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tz_format                                                    *
 *                                                                            *
 * Purpose: formats timezone to +hh:mm format                                 *
 *                                                                            *
 * Parameters: buffer - [OUT] the output buffer                               *
 *             size   - [IN] the output buffer size                           *
 *             tz_sec - [IN] the timezone offset in seconds                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tz_format(char *buffer, size_t size, int tz_sec)
{
	int	tz_hour, tz_min;
	char	tz_sign;

	if (0 > tz_sec)
	{
		tz_sec = -tz_sec;
		tz_sign = '-';
	}
	else
		tz_sign = '+';

	tz_hour = tz_sec / 60;
	tz_min = tz_hour % 60;
	tz_hour /= 60;

	zbx_snprintf(buffer, size, ZBX_MOCK_FORMAT_TZ, tz_sign, tz_hour, tz_min);
}


typedef enum
{
	ZBX_TOKEN_START,
	ZBX_TOKEN_DELIM,
	ZBX_TOKEN_COMPONENT
}
zbx_mock_time_parser_state_t;

/******************************************************************************
 *                                                                            *
 * Function: zbx_strtime_to_timespec                                          *
 *                                                                            *
 * Purpose: converts YAML space separated timestamp having                    *
 *          YYYY-MM-DD hh:mm:ss.nnnnnnnnn TZ format to zabbix timespec        *
 *                                                                            *
 * Parameters: strtime - [IN] the YAML space separated timestamp              *
 *             ts      - [OUT]  zabbix timespec                               *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the timestamp was converted successfully  *
 *               ZBX_MOCK_NOT_A_TIMESTAMP - invalid timestamp format          *
 *                                                                            *
 * Comments: Timestamp consists of 4 components - date, time, nanoseconds and *
 *           timezone. Any of those components can be omitted except timezone *
 *           component requires date component to be present. Absent          *
 *           components are treated as zero values. So for example 10:00:00   *
 *           is equal to 1970-01-01 10:00:00.000000000 +00:00 and parsing it  *
 *           would result in timespec with 36000 seconds and 0 nanoseconds.   *
 *                                                                            *
 ******************************************************************************/
zbx_mock_error_t	zbx_strtime_to_timespec(const char *strtime, zbx_timespec_t *ts)
{
	int				sec, ns, tz, components = 0;
	const char			*ptr, *pnext;
	struct tm			tm;
	zbx_mock_error_t		err;
	zbx_mock_time_parser_state_t	state = ZBX_TOKEN_START;

	for (ptr = strtime; '\0' != *ptr;)
	{
		if ('.' == *ptr)
		{
			if (ZBX_TOKEN_DELIM == state)
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (ZBX_MOCK_TIME_NS < components)
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (ZBX_MOCK_SUCCESS != (err = ts_get_ns(ptr, &ns, &ptr)))
				return err;

			components |= ZBX_MOCK_TIME_NS;
			state = ZBX_TOKEN_COMPONENT;
			continue;
		}

		if ('Z' == *ptr)
		{
			if (ZBX_TOKEN_DELIM == state)
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (0 != (components & ZBX_MOCK_TIME_TZ) || 0 == (components & ZBX_MOCK_TIME_DATE))
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			tz = 0;
			ptr++;
			components |= ZBX_MOCK_TIME_TZ;
			state = ZBX_TOKEN_COMPONENT;
			continue;
		}

		if (' ' == *ptr || '\t' == *ptr)
		{
			state = ZBX_TOKEN_DELIM;
			ptr++;
			continue;
		}

		if (ZBX_TOKEN_COMPONENT == state)
			return ZBX_MOCK_NOT_A_TIMESTAMP;

		state = ZBX_TOKEN_COMPONENT;

		if ('-' == *ptr || '+' == *ptr)
		{
			if (0 != (components & ZBX_MOCK_TIME_TZ) || 0 == (components & ZBX_MOCK_TIME_DATE))
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (ZBX_MOCK_SUCCESS != (err = ts_get_tz(ptr, &tz, &ptr)))
				return err;

			components |= ZBX_MOCK_TIME_TZ;
			continue;
		}

		pnext = ts_get_component_end(ptr);

		if (ptr == pnext)
			return ZBX_MOCK_NOT_A_TIMESTAMP;

		if ('-' == *pnext)
		{
			if (ZBX_MOCK_TIME_DATE < components)
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (ZBX_MOCK_SUCCESS != (err = ts_get_date(ptr, &tm.tm_year, &tm.tm_mon, &tm.tm_mday, &ptr)))
				return err;

			components |= ZBX_MOCK_TIME_DATE;

			tm.tm_year -= 1900;
			tm.tm_mon--;
			continue;
		}

		if (':' == *pnext)
		{
			if (ZBX_MOCK_TIME_TIME < components)
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (ZBX_MOCK_SUCCESS != (err = ts_get_time(ptr, &tm.tm_hour, &tm.tm_min, &tm.tm_sec, &ptr)))
				return err;

			components |= ZBX_MOCK_TIME_TIME;
			continue;
		}

		return ZBX_MOCK_NOT_A_TIMESTAMP;
	}

	if (0 != (components & (ZBX_MOCK_TIME_DATE | ZBX_MOCK_TIME_TIME)))
	{
		if (0 == (components & ZBX_MOCK_TIME_DATE))
		{
			tm.tm_year = 70;
			tm.tm_mon = 0;
			tm.tm_mday = 1;
		}

		if (0 == (components & ZBX_MOCK_TIME_TIME))
		{
			tm.tm_hour = 0;
			tm.tm_min = 0;
			tm.tm_sec = 0;
		}

		if (0 >  (sec = timegm(&tm)))
			return ZBX_MOCK_NOT_A_TIMESTAMP;

		if (0 != (components & ZBX_MOCK_TIME_TZ))
			sec -= tz;
	}
	else
		sec = 0;

	if (0 == (components & ZBX_MOCK_TIME_NS))
		ns = 0;

	ts->sec = sec;
	ts->ns = ns;

	return ZBX_MOCK_SUCCESS;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_time_to_strtime                                              *
 *                                                                            *
 * Purpose: converts time to YAML space separated timestamp in                *
 *          YYYY-MM-DD hh:mm:ss TZ format                                     *
 *                                                                            *
 * Parameters: timestamp - [IN] the time (seconds since Epoch)                *
 *             buffer    - [OUT] the output buffer                            *
 *             size      - [OUT] the size of output buffer                    *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the time was converted successfully       *
 *               ZBX_MOCK_NOT_ENOUGH_MEMORY - the output buffer size is too   *
 *                                            small                           *
 *               ZBX_MOCK_INTERNAL_ERROR - invalid timestamp specified        *
 *                                                                            *
 * Comments: The time is converted by using current timezone settings.        *
 *                                                                            *
 ******************************************************************************/
zbx_mock_error_t	zbx_time_to_strtime(time_t timestamp, char *buffer, size_t size)
{
	struct tm		tm;
	int			tz_sec;
	char			tz_buf[ZBX_MOCK_TZ_MAX];
	zbx_mock_error_t	err;

	/* max timestamp length minus nanosecond component */
	if (size < ZBX_MOCK_TIMESTAMP_MAX_LEN - 10)
		return ZBX_MOCK_NOT_ENOUGH_MEMORY;

	if (ZBX_MOCK_SUCCESS != (err = zbx_time_to_localtime(timestamp, &tm, &tz_sec)))
		return err;

	zbx_tz_format(tz_buf, sizeof(tz_buf), tz_sec);

	zbx_snprintf(buffer, size, ZBX_MOCK_FORMAT_DATETIME " %s",
			tm.tm_year + 1900, tm.tm_mon + 1, tm.tm_mday,
			tm.tm_hour, tm.tm_min, tm.tm_sec, tz_buf);

	return ZBX_MOCK_SUCCESS;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_timespec_to_strtime                                          *
 *                                                                            *
 * Purpose: converts timespec (seconds + nanoseconds) to YAML space separated *
 *          timestamp in YYYY-MM-DD hh:mm:ss.nnnnnnnnn TZ format              *
 *                                                                            *
 * Parameters: ts     - [IN] the zabbix timespec (seconds + nanoseconds)      *
 *             buffer - [OUT] the output buffer                               *
 *             size   - [OUT] the size of output buffer                       *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the time was converted successfully       *
 *               ZBX_MOCK_NOT_ENOUGH_MEMORY - the output buffer size is too   *
 *                                            small                           *
 *               ZBX_MOCK_INTERNAL_ERROR - invalid timestamp specified        *
 *                                                                            *
 * Comments: The time is converted by using current timezone settings.        *
 *                                                                            *
 ******************************************************************************/
zbx_mock_error_t	zbx_timespec_to_strtime(const zbx_timespec_t *ts, char *buffer, size_t size)
{
	struct tm		tm;
	int			tz_sec;
	char			tz_buf[ZBX_MOCK_TZ_MAX + 1];
	zbx_mock_error_t	err;

	if (size < ZBX_MOCK_TIMESTAMP_MAX_LEN)
		return ZBX_MOCK_NOT_ENOUGH_MEMORY;

	if (ZBX_MOCK_SUCCESS != (err = zbx_time_to_localtime(ts->sec, &tm, &tz_sec)))
		return err;

	zbx_tz_format(tz_buf, sizeof(tz_buf), tz_sec);

	zbx_snprintf(buffer, size, ZBX_MOCK_FORMAT_DATETIME ZBX_MOCK_FORMAT_NS " %s",
			tm.tm_year + 1900, tm.tm_mon + 1, tm.tm_mday,
			tm.tm_hour, tm.tm_min, tm.tm_sec, ts->ns, tz_buf);

	return ZBX_MOCK_SUCCESS;
}
