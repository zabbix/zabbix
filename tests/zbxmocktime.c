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

#include "zbxmocktime.h"

/* output formats */
#define ZBX_MOCK_FORMAT_DATE		"%04d-%02d-%02d"
#define ZBX_MOCK_FORMAT_TIME		"%02d:%02d:%02d"
#define ZBX_MOCK_FORMAT_DATETIME	ZBX_MOCK_FORMAT_DATE " " ZBX_MOCK_FORMAT_TIME
#define ZBX_MOCK_FORMAT_NS		".%09d"
#define ZBX_MOCK_FORMAT_TZ		" %c%02d:%02d"

#define ZBX_MOCK_TIMESTAMP_MAX_LEN	36

#define ZBX_MOCK_TIME_DATE	0x0001
#define ZBX_MOCK_TIME_TIME	0x0002
#define ZBX_MOCK_TIME_NS	0x0003
#define ZBX_MOCK_TIME_TZ	0x0004

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
zbx_mock_error_t	ts_get_date(const char *text, int *year, int *month, int *day, const char **pnext)
{
	const char	*year_end, *month_end, *day_end;

	year_end = ts_get_component_end(text);
	if (year_end == text || '-' != *year_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	month_end = ts_get_component_end(year_end + 1);
	if (month_end == year_end + 1 || '-' != *month_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	day_end = ts_get_component_end(month_end + 1);

	if (day_end == month_end + 1)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	*pnext = day_end;

	*year = atoi(text);
	*month = atoi(year_end + 1);
	*day = atoi(month_end + 1);

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
zbx_mock_error_t	ts_get_time(const char *text, int *hours, int *minutes, int *seconds, const char **pnext)
{
	const char	*hours_end, *minutes_end, *seconds_send;

	hours_end = ts_get_component_end(text);
	if (hours_end == text || ':' != *hours_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	minutes_end = ts_get_component_end(hours_end + 1);
	if (minutes_end == hours_end + 1 || ':' != *minutes_end)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	seconds_send = ts_get_component_end(minutes_end + 1);

	if (seconds_send == minutes_end + 1)
		return ZBX_MOCK_NOT_A_TIMESTAMP;

	*pnext = seconds_send;

	*hours = atoi(text);
	*minutes = atoi(hours_end + 1);
	*seconds = atoi(minutes_end + 1);

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
zbx_mock_error_t	ts_get_ns(const char *text, int *ns, const char **pnext)
{
	const char	*ns_end;
	int		pad;

	ns_end = ts_get_component_end(text + 1);
	if (ns_end == text + 1)
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
zbx_mock_error_t	ts_get_tz(const char *text, int *sec, const char **pnext)
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
	int		err, sec, ns, tz, components = 0;
	const char	*ptr, *pnext;
	struct tm	tm;

	for (ptr = strtime; '\0' != *ptr;)
	{
		if ('.' == *ptr)
		{
			if (ZBX_MOCK_TIME_NS < components)
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (ZBX_MOCK_SUCCESS != (err = ts_get_ns(ptr, &ns, &ptr)))
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			components |= ZBX_MOCK_TIME_NS;

			if (' ' == *ptr)
				ptr++;

			continue;
		}

		if ('-' == *ptr || '+' == *ptr)
		{
			if (0 != (components & ZBX_MOCK_TIME_TZ) || 0 == (components & ZBX_MOCK_TIME_DATE))
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (ZBX_MOCK_SUCCESS != (err = ts_get_tz(ptr, &tz, &ptr)))
				return ZBX_MOCK_NOT_A_TIMESTAMP;

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

			if (' ' == *ptr)
				ptr++;

			continue;
		}

		if (':' == *pnext)
		{
			if (ZBX_MOCK_TIME_TIME < components)
				return ZBX_MOCK_NOT_A_TIMESTAMP;

			if (ZBX_MOCK_SUCCESS != (err = ts_get_time(ptr, &tm.tm_hour, &tm.tm_min, &tm.tm_sec, &ptr)))
				return err;

			components |= ZBX_MOCK_TIME_TIME;

			if (' ' == *ptr)
				ptr++;

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

		if (-1 == (sec = timegm(&tm)))
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

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strtime_tz_sec                                               *
 *                                                                            *
 * Purpose: obtains timezone offset in seconds from YAML space separated      *
 *          timestamp having YYYY-MM-DD hh:mm:ss.nnnnnnnnn TZ format          *
 *                                                                            *
 * Parameters: strtime - [IN] the YAML space separated timestamp              *
 *             tz      - [OUT]  timezone offset in seconds                    *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the timestamp was parsed successfully     *
 *               ZBX_MOCK_NOT_A_TIMESTAMP - invalid timestamp format          *
 *                                                                            *
 * Comments: If timezone component is not present then zero offset is         *
 *           returned.                                                        *
 *                                                                            *
 ******************************************************************************/
zbx_mock_error_t	zbx_strtime_tz_sec(const char *strtime, int *tz_sec)
{
	int		err;
	const char	*ptr;

	for (ptr = strtime; '\0' != *ptr; ptr = ts_get_component_end(ptr + 1))
	{
		if (' ' == *ptr)
		{
			ptr++;
			if ('-' == *ptr || '+' == *ptr)
			{
				if (ZBX_MOCK_SUCCESS != (err = ts_get_tz(ptr, tz_sec, &ptr)))
					return ZBX_MOCK_NOT_A_TIMESTAMP;
				break;
			}
		}
	}

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
 *             tz_sec    - [IN] timezone offset in seconds                    *
 *             buffer    - [OUT] the output buffer                            *
 *             size      - [OUT] the size of output buffer                    *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the time was converted successfully       *
 *               ZBX_MOCK_NOT_ENOUGH_MEMORY - the output buffer size is too   *
 *                                            small                           *
 *                                                                            *
 ******************************************************************************/
zbx_mock_error_t	zbx_time_to_strtime(time_t timestamp, int tz_sec, char *buffer, size_t size)
{
	struct tm	*tm;
	int		tz_hour, tz_min;
	char		tz_sign;

	if (size < ZBX_CONST_STRLEN(ZBX_MOCK_FORMAT_DATETIME) + ZBX_CONST_STRLEN(ZBX_MOCK_FORMAT_TZ) + 1)
		return ZBX_MOCK_NOT_ENOUGH_MEMORY;

	timestamp += tz_sec;
	tm = gmtime(&timestamp);

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

	zbx_snprintf(buffer, size, ZBX_MOCK_FORMAT_DATETIME ZBX_MOCK_FORMAT_TZ,
			tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday,
			tm->tm_hour, tm->tm_min, tm->tm_sec, tz_sign, tz_hour, tz_min);

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
 *             tz_sec - [IN] timezone offset in seconds                       *
 *             buffer - [OUT] the output buffer                               *
 *             size   - [OUT] the size of output buffer                       *
 *                                                                            *
 * Return value: ZBX_MOCK_SUCCESS - the time was converted successfully       *
 *               ZBX_MOCK_NOT_ENOUGH_MEMORY - the output buffer size is too   *
 *                                            small                           *
 *                                                                            *
 ******************************************************************************/
zbx_mock_error_t	zbx_timespec_to_strtime(const zbx_timespec_t *ts, int tz_sec, char *buffer, size_t size)
{
	struct tm	*tm;
	int		tz_hour, tz_min;
	time_t		timestamp = ts->sec;
	char		tz_sign;

	if (size < ZBX_MOCK_TIMESTAMP_MAX_LEN + 1)
		return ZBX_MOCK_NOT_ENOUGH_MEMORY;

	timestamp += tz_sec;
	tm = gmtime(&timestamp);

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

	zbx_snprintf(buffer, size, ZBX_MOCK_FORMAT_DATETIME ZBX_MOCK_FORMAT_NS ZBX_MOCK_FORMAT_TZ,
			tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday,
			tm->tm_hour, tm->tm_min, tm->tm_sec, ts->ns, tz_sign, tz_hour, tz_min);

	return ZBX_MOCK_SUCCESS;
}
