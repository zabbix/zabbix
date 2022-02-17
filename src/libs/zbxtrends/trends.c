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

#include "trends.h"

#include "common.h"
#include "db.h"
#include "log.h"

static char	*trends_errors[ZBX_TREND_STATE_COUNT] = {
		"unknown error",
		NULL,
		"not enough data",
		"value is too large"
};

/******************************************************************************
 *                                                                            *
 * Purpose: parse largest period base from function parameters                *
 *                                                                            *
 * Parameters: shift  - [IN] the period shift parameter                       *
 *             base   - [OUT] the period shift base (now/?)                   *
 *             error  - [OUT] the error message if parsing failed             *
 *                                                                            *
 * Return value: SUCCEED - period was parsed successfully                     *
 *               FAIL    - invalid time period was specified                  *
 *                                                                            *
 ******************************************************************************/
static int	trends_parse_base(const char *period_shift, zbx_time_unit_t *base, char **error)
{
	zbx_time_unit_t	time_unit = ZBX_TIME_UNIT_UNKNOWN;
	const char	*ptr;

	for (ptr = period_shift; NULL != (ptr = strchr(ptr, '/'));)
	{
		zbx_time_unit_t	tu;

		if (ZBX_TIME_UNIT_UNKNOWN == (tu = zbx_tm_str_to_unit(++ptr)))
		{
			*error = zbx_strdup(*error, "invalid period shift cycle");
			return FAIL;
		}

		if (tu > time_unit)
			time_unit = tu;
	}

	if (ZBX_TIME_UNIT_UNKNOWN == time_unit)
	{
		*error = zbx_strdup(*error, "invalid period shift expression");
		return FAIL;
	}

	*base = time_unit;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse largest period base from function parameters                *
 *                                                                            *
 * Parameters: params - [IN] the function parameters                          *
 *             base   - [OUT] the period shift base (now/?)                   *
 *             error  - [OUT] the error message if parsing failed             *
 *                                                                            *
 * Return value: SUCCEED - period was parsed successfully                     *
 *               FAIL    - invalid time period was specified                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_trends_parse_base(const char *params, zbx_time_unit_t *base, char **error)
{
	const char	*period_shift;

	if (NULL == (period_shift = strchr(params, ':')))
	{
		*error = zbx_strdup(*error, "missing period shift parameter");
		return FAIL;
	}

	return trends_parse_base(period_shift + 1, base, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse timeshift                                                   *
 *                                                                            *
 * Parameters: from          - [IN] the start time                            *
 *             timeshift     - [IN] the timeshift string                      *
 *             min_time_unit - [IN] minimum time unit that can be used        *
 *             tm            - [IN] the shifted time                          *
 *             error         - [OUT] the error message if parsing failed      *
 *                                                                            *
 * Return value: SUCCEED - time shift was parsed successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	trends_parse_timeshift(time_t from, const char *timeshift, zbx_time_unit_t min_time_unit, struct tm *tm,
		char **error)
{
	size_t		len;
	const char	*p;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() shift:%s", __func__,  timeshift);

	p = timeshift;

	if (0 != strncmp(p, "now", ZBX_CONST_STRLEN("now")))
	{
		*error = zbx_strdup(*error, "time shift must begin with \"now\"");
		return FAIL;
	}

	p += ZBX_CONST_STRLEN("now");

	localtime_r(&from, tm);

	while ('\0' != *p)
	{
		zbx_time_unit_t	unit;

		if ('/' == *p)
		{
			if (ZBX_TIME_UNIT_UNKNOWN == (unit = zbx_tm_str_to_unit(++p)))
			{
				*error = zbx_dsprintf(*error, "unexpected character starting with \"%s\"", p);
				return FAIL;
			}

			if (unit < min_time_unit)
			{
				*error = zbx_dsprintf(*error, "time units in time shift must be greater or equal"
						" to period time unit");
				return FAIL;
			}

			zbx_tm_round_down(tm, unit);

			/* unit is single character */
			p++;
		}
		else if ('+' == *p || '-' == *p)
		{
			int	num;
			char	op = *(p++);

			if (FAIL == zbx_tm_parse_period(p, &len, &num, &unit, error))
				return FAIL;

			if (unit < min_time_unit)
			{
				*error = zbx_dsprintf(*error, "time units in period shift must be greater or equal"
						" to period time unit");
				return FAIL;
			}

			if ('+' == op)
				zbx_tm_add(tm, num, unit);
			else
				zbx_tm_sub(tm, num, unit);

			p += len;
		}
		else
		{
			*error = zbx_dsprintf(*error, "unexpected character starting with \"%s\"", p);
			return FAIL;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() %04d.%02d.%02d %02d:%02d:%02d", __func__, tm->tm_year + 1900,
			tm->tm_mon + 1, tm->tm_mday, tm->tm_hour, tm->tm_min, tm->tm_sec);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse timeshift                                                   *
 *                                                                            *
 * Parameters: from          - [IN] the start time                            *
 *             timeshift     - [IN] the timeshift string                      *
 *             tm            - [IN] the shifted time                          *
 *             error         - [OUT] the error message if parsing failed      *
 *                                                                            *
 * Return value: SUCCEED - time shift was parsed successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_parse_timeshift(time_t from, const char *timeshift, struct tm *tm, char **error)
{
	return trends_parse_timeshift(from, timeshift, ZBX_TIME_UNIT_UNKNOWN, tm, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse trend function period arguments into time range             *
 *                                                                            *
 * Parameters: from         - [IN] the time the period shift is calculated    *
 *                                 from                                       *
 *             param        - [IN] the history period parameter:              *
 *                                     <period>:<period_shift>                *
 *             start        - [OUT] the period start time in seconds since    *
 *                                  Epoch                                     *
 *             end          - [OUT] the period end time in seconds since      *
 *                                  Epoch                                     *
 *             error        - [OUT] the error message if parsing failed       *
 *                                                                            *
 * Return value: SUCCEED - period was parsed successfully                     *
 *               FAIL    - invalid time period was specified                  *
 *                                                                            *
 * Comments: Daylight saving changes are applied when parsing ranges with     *
 *           day+ used as period base (now/?).                                *
 *                                                                            *
 *           Example period_shift values:                                     *
 *             now/d                                                          *
 *             now/d-1h                                                       *
 *             now/d+1h                                                       *
 *             now/d+1h/w                                                     *
 *             now/d/w/h+1h+2h                                                *
 *             now-1d/h                                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_trends_parse_range(time_t from, const char *param, int *start, int *end, char **error)
{
	int		period_num;
	int		period_hours[ZBX_TIME_UNIT_COUNT] = {0, 0, 0, 1, 24, 24 * 7, 24 * 30, 24 * 365, 24 * 7 * 53};
	zbx_time_unit_t	period_unit;
	size_t		len;
	struct tm	tm_end, tm_start;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() param:%s", __func__, param);

	/* parse period */

	if (SUCCEED != zbx_tm_parse_period(param, &len, &period_num, &period_unit, error))
		return FAIL;

	if ('\0' != param[len] && ':' != param[len])
	{
		*error = zbx_dsprintf(*error, "unexpected character[s] in period \"%s\"", param + len);
		return FAIL;
	}

	if (0 == period_num)
	{
		*error = zbx_strdup(*error, "period cannot be zero");
		return FAIL;
	}

	if (period_hours[period_unit] * period_num > 24 * 366)
	{
		*error = zbx_strdup(*error, "period is too large");
		return FAIL;
	}

	/* parse period shift */

	if (SUCCEED != trends_parse_timeshift(from, param + len + 1, period_unit, &tm_end, error))
		return FAIL;

	tm_start = tm_end;

	/* trends clock refers to the beginning of the hourly interval - subtract */
	/* one hour to get the trends clock for the last hourly interval          */
	zbx_tm_sub(&tm_end, 1, ZBX_TIME_UNIT_HOUR);

	if (-1 == (*end = mktime(&tm_end)))
	{
		*error = zbx_dsprintf(*error, "cannot calculate the period end time: %s", zbx_strerror(errno));
		return FAIL;
	}

	if (abs((int)from - *end) > SEC_PER_YEAR * 26)
	{
		*error = zbx_strdup(*error, "period shift is too large");
		return FAIL;
	}

	zbx_tm_sub(&tm_start, period_num, period_unit);
	if (-1 == (*start = mktime(&tm_start)))
	{
		*error = zbx_dsprintf(*error, "cannot calculate the period start time: %s", zbx_strerror(errno));
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() start:%d end:%d", __func__, *start, *end);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate possible nextcheck based on trend function parameters   *
 *                                                                            *
 * Parameters: from         - [IN] the time the period shift is calculated    *
 *                                 from                                       *
 *             p            - [IN] the history period shift                   *
 *             nextcheck    - [OUT] the time starting from which the period   *
 *                                  will end in future                        *
 *             error        - [OUT] the error message if parsing failed       *
 *                                                                            *
 * Return value: SUCCEED - period was parsed successfully                     *
 *               FAIL    - invalid time period was specified                  *
 *                                                                            *
 * Comments: Daylight saving changes are applied when parsing ranges with     *
 *           day+ used as period base (now/?).                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_trends_parse_nextcheck(time_t from, const char *period_shift, time_t *nextcheck, char **error)
{
	struct tm	tm;
	zbx_time_unit_t	base;

	if (SUCCEED != trends_parse_base(period_shift, &base, error) || ZBX_TIME_UNIT_HOUR > base)
		return FAIL;

	/* parse period shift */

	if (0 != strncmp(period_shift, "now", ZBX_CONST_STRLEN("now")))
	{
		*error = zbx_strdup(*error, "period shift must begin with \"now\"");
		return FAIL;
	}

	period_shift += ZBX_CONST_STRLEN("now");

	localtime_r(&from, &tm);

	while ('\0' != *period_shift)
	{
		zbx_time_unit_t	unit;

		if ('/' == *period_shift)
		{
			if (ZBX_TIME_UNIT_UNKNOWN == (unit = zbx_tm_str_to_unit(++period_shift)))
			{
				*error = zbx_dsprintf(*error, "unexpected character starting with \"%s\"",
						period_shift);
				return FAIL;
			}

			zbx_tm_round_down(&tm, unit);

			/* unit is single character */
			period_shift++;
		}
		else if ('+' == *period_shift || '-' == *period_shift)
		{
			int	num;
			char	op = *(period_shift++);
			size_t	len;

			if (FAIL == zbx_tm_parse_period(period_shift, &len, &num, &unit, error))
				return FAIL;

			/* nextcheck calculation is based on the largest rounding unit, */
			/* so shifting larger or equal time units will not affect it    */
			if (unit < base)
			{
				if ('+' == op)
					zbx_tm_add(&tm, num, unit);
				else
					zbx_tm_sub(&tm, num, unit);
			}

			period_shift += len;
		}
		else if (',' == *period_shift)
		{
			break;
		}
		else
		{
			*error = zbx_dsprintf(*error, "unexpected character starting with \"%s\"", period_shift);
			return FAIL;
		}
	}

	/* trends clock refers to the beginning of the hourly interval - subtract */
	/* one hour to get the trends clock for the last hourly interval          */
	zbx_tm_sub(&tm, 1, ZBX_TIME_UNIT_HOUR);

	if (-1 == (*nextcheck = mktime(&tm)))
	{
		*error = zbx_dsprintf(*error, "cannot calculate the period start time: %s", zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate expression with trends data                              *
 *                                                                            *
 * Parameters: table       - [IN] the trends table name                       *
 *             itemid      - [IN] the itemid                                  *
 *             start       - [OUT] the period start time in seconds since     *
 *                                  Epoch                                     *
 *             end         - [OUT] the period end time in seconds since       *
 *                                  Epoch                                     *
 *             eval_single - [IN] sql expression to evaluate for single       *
 *                                 record                                     *
 *             eval_multi  - [IN] sql expression to evaluate for multiple     *
 *                                 records                                    *
 *             value       - [OUT] the evaluation result                      *
 *                                                                            *
 * Return value: Trend value state of the specified period and function.      *
 *                                                                            *
 ******************************************************************************/
static zbx_trend_state_t	trends_eval(const char *table, zbx_uint64_t itemid, int start, int end,
		const char *eval_single, const char *eval_multi, double *value)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_trend_state_t	state;

	if (start != end)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select %s from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock>=%d"
					" and clock<=%d",
				eval_multi, table, itemid, start, end);
	}
	else
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select %s from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock=%d",
				eval_single, table, itemid, start);
	}

	result = DBselect("%s", sql);
	zbx_free(sql);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		*value = atof(row[0]);
		state = ZBX_TREND_STATE_NORMAL;
	}
	else
		state = ZBX_TREND_STATE_NODATA;

	DBfree_result(result);

	return state;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate avg function with trends data                            *
 *                                                                            *
 * Parameters: table       - [IN] the trends table name                       *
 *             itemid      - [IN] the itemid                                  *
 *             start       - [OUT] the period start time in seconds since     *
 *                                  Epoch                                     *
 *             end         - [OUT] the period end time in seconds since       *
 *                                  Epoch                                     *
 *             value       - [OUT] the evaluation result                      *
 *                                                                            *
 * Return value: Trend value state of the specified period and function.      *
 *                                                                            *
 ******************************************************************************/
static zbx_trend_state_t	trends_eval_avg(const char *table, zbx_uint64_t itemid, int start, int end,
		double *value)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_trend_state_t	state;
	double			avg, num, num2, avg2;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select value_avg,num from %s where itemid=" ZBX_FS_UI64,
			table, itemid);
	if (start != end)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>=%d and clock<=%d", start, end);
	else
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock=%d", start);

	result = DBselect("%s", sql);
	zbx_free(sql);

	if (NULL != (row = DBfetch(result)))
	{
		avg = atof(row[0]);
		num = atof(row[1]);

		while (NULL != (row = DBfetch(result)))
		{
			avg2 = atof(row[0]);
			num2 = atof(row[1]);
			avg = avg / (num + num2) * num + avg2 / (num + num2) * num2;
			num += num2;
		}

		*value = avg;
		state = ZBX_TREND_STATE_NORMAL;
	}
	else
		state = ZBX_TREND_STATE_NODATA;

	DBfree_result(result);

	return state;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate sum function with trends data                            *
 *                                                                            *
 * Parameters: table       - [IN] the trends table name                       *
 *             itemid      - [IN] the itemid                                  *
 *             start       - [OUT] the period start time in seconds since     *
 *                                  Epoch                                     *
 *             end         - [OUT] the period end time in seconds since       *
 *                                  Epoch                                     *
 *             value       - [OUT] the evaluation result                      *
 *                                                                            *
 * Return value: Trend value state of the specified period and function.      *
 *                                                                            *
 ******************************************************************************/
static zbx_trend_state_t	trends_eval_sum(const char *table, zbx_uint64_t itemid, int start, int end,
		double *value)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	double		sum = 0;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select value_avg,num from %s where itemid=" ZBX_FS_UI64,
			table, itemid);
	if (start != end)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>=%d and clock<=%d", start, end);
	else
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock=%d", start);

	result = DBselect("%s", sql);
	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
		sum += atof(row[0]) * atof(row[1]);

	DBfree_result(result);

	if (ZBX_INFINITY == sum)
		return ZBX_TREND_STATE_OVERFLOW;

	*value = sum;

	return ZBX_TREND_STATE_NORMAL;
}

int	zbx_trends_eval_avg(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error)
{
	zbx_trend_state_t	state;

	if (FAIL == zbx_tfc_get_value(itemid, start, end, ZBX_TREND_FUNCTION_AVG, value, &state))
	{
		state = trends_eval_avg(table, itemid, start, end, value);
		zbx_tfc_put_value(itemid, start, end, ZBX_TREND_FUNCTION_AVG, *value, state);
	}

	if (ZBX_TREND_STATE_NORMAL == state)
		return SUCCEED;

	if (NULL != error)
		*error = zbx_strdup(*error, trends_errors[state]);

	return FAIL;
}

int	zbx_trends_eval_count(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error)
{
	zbx_trend_state_t	state;

	ZBX_UNUSED(error);

	if (FAIL == zbx_tfc_get_value(itemid, start, end, ZBX_TREND_FUNCTION_COUNT, value, &state))
	{
		if (ZBX_TREND_STATE_NORMAL != (state = trends_eval(table, itemid, start, end, "num", "sum(num)", value)))
		{
			state = ZBX_TREND_STATE_NORMAL;
			*value = 0;
		}

		zbx_tfc_put_value(itemid, start, end, ZBX_TREND_FUNCTION_COUNT, *value, state);
	}

	return SUCCEED;
}

int	zbx_trends_eval_max(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error)
{
	zbx_trend_state_t	state;

	if (FAIL == zbx_tfc_get_value(itemid, start, end, ZBX_TREND_FUNCTION_MAX, value, &state))
	{
		state = trends_eval(table, itemid, start, end, "value_max", "max(value_max)", value);
		zbx_tfc_put_value(itemid, start, end, ZBX_TREND_FUNCTION_MAX, *value, state);
	}

	if (ZBX_TREND_STATE_NORMAL == state)
		return SUCCEED;

	if (NULL != error)
		*error = zbx_strdup(*error, trends_errors[state]);

	return FAIL;
}

int	zbx_trends_eval_min(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error)
{
	zbx_trend_state_t	state;

	if (FAIL == zbx_tfc_get_value(itemid, start, end, ZBX_TREND_FUNCTION_MIN, value, &state))
	{
		state = trends_eval(table, itemid, start, end, "value_min", "min(value_min)", value);
		zbx_tfc_put_value(itemid, start, end, ZBX_TREND_FUNCTION_MIN, *value, state);
	}

	if (ZBX_TREND_STATE_NORMAL == state)
		return SUCCEED;

	if (NULL != error)
		*error = zbx_strdup(*error, trends_errors[state]);

	return FAIL;
}

int	zbx_trends_eval_sum(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error)
{
	zbx_trend_state_t	state;

	if (FAIL == zbx_tfc_get_value(itemid, start, end, ZBX_TREND_FUNCTION_SUM, value, &state))
	{
		state = trends_eval_sum(table, itemid, start, end, value);
		zbx_tfc_put_value(itemid, start, end, ZBX_TREND_FUNCTION_SUM, *value, state);
	}

	if (ZBX_TREND_STATE_NORMAL == state)
		return SUCCEED;

	if (NULL != error)
		*error = zbx_strdup(*error, trends_errors[state]);

	return FAIL;
}

zbx_trend_state_t	zbx_trends_get_avg(const char *table, zbx_uint64_t itemid, int start, int end, double *value)
{
	zbx_trend_state_t	state;

	if (FAIL == zbx_tfc_get_value(itemid, start, end, ZBX_TREND_FUNCTION_AVG, value, &state))
	{
		state = trends_eval_avg(table, itemid, start, end, value);
		zbx_tfc_put_value(itemid, start, end, ZBX_TREND_FUNCTION_AVG, *value, state);
	}

	return state;
}

const char	*zbx_trends_error(zbx_trend_state_t state)
{
	if (0 > state || state >= ZBX_TREND_STATE_COUNT)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return "unknown trend cache state";
	}

	return trends_errors[state];
}
