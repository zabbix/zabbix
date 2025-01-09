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
#include "zbxtrends.h"
#include "trends.h"

#include "zbxalgo.h"
#include "zbxtime.h"
#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Purpose: get baseline data for common period/season combinations           *
 *                                                                            *
 * Parameters: itemid      - [IN] the item identifier                         *
 *             table       - [IN] the trends table name                       *
 *             now         - [IN] the current timestamp                       *
 *             period      - [IN] the data period                             *
 *             season_num  - [IN] the number of seasons                       *
 *             season_unit - [IN] the season time unit                        *
 *             skip        - [IN] how many data periods to skip               *
 *             values      - [OUT] the average data period value in each      *
 *                                 season                                     *
 *             index      - [OUT] the index of returned values, starting      *
 *                                with 0. It will have values be from 0 to    *
 *                                <seasons num> + 1 - <skip> with holes for   *
 *                                periods without data.                       *
 *             error       - [OUT] the error message if parsing failed        *
 *                                                                            *
 * Return value: SUCCEED - data were retrieved successfully                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	baseline_get_common_data(zbx_uint64_t itemid, const char *table, time_t now, const char *period,
		int season_num, zbx_time_unit_t season_unit, int skip, zbx_vector_dbl_t *values,
		zbx_vector_uint64_t *index, char **error)
{
	int			i;
	time_t			start, end;
	double			value_dbl;
	struct tm		tm, tm_now;

	tm_now = *localtime(&now);

	for (i = 0; i < season_num; i++)
	{
		if (FAIL == zbx_trends_parse_range(now, period, &start, &end, error))
			return FAIL;

		if (skip <= i)
		{
			zbx_trend_state_t	state;

			state = zbx_trends_get_avg(table, itemid, start, end, &value_dbl);

			if (ZBX_TREND_STATE_NORMAL != state)
			{
				if (0 == i || ZBX_TREND_STATE_NODATA != state)
				{
					*error = zbx_strdup(NULL, zbx_trends_error(state));
					return FAIL;
				}
			}
			else
			{
				zbx_vector_dbl_append(values, value_dbl);
				zbx_vector_uint64_append(index, (zbx_uint64_t)(i - skip));
			}
		}

		tm = tm_now;
		zbx_tm_sub(&tm, i + 1, season_unit);

		if (-1 == (now = mktime(&tm)))
		{
			*error = zbx_dsprintf(*error, "cannot convert season start time: %s", zbx_strerror(errno));
			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get baseline data for week based periods in a year                *
 *                                                                            *
 * Parameters: itemid     - [IN] the item identifier                          *
 *             table      - [IN] the trends table name                        *
 *             now        - [IN] the current timestamp                        *
 *             period     - [IN] the data period                              *
 *             season_num - [IN] the number of seasons                        *
 *             skip       - [IN] how many data periods to skip                *
 *             values     - [OUT] the average data period value in each       *
 *                                season                                      *
 *             index      - [OUT] the index of returned values, starting      *
 *                                with 0. It will have values be from 0 to    *
 *                                <seasons num> + 1 - <skip> with holes for   *
 *                                periods without data.                       *
 *             error      - [OUT] the error message if parsing failed         *
 *                                                                            *
 * Return value: SUCCEED - data were retrieved successfully                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	baseline_get_isoyear_data(zbx_uint64_t itemid, const char *table, time_t now, const char *period,
		int season_num, int skip, zbx_vector_dbl_t *values, zbx_vector_uint64_t *index, char **error)
{
	int		i, period_num;
	time_t		start, end, time_tmp;
	double		value_dbl;
	struct tm	tm_end, tm_start;
	size_t		len;
	zbx_time_unit_t	period_unit;

	if (FAIL == zbx_tm_parse_period(period, &len, &period_num, &period_unit, error))
		return FAIL;

	if (FAIL == zbx_trends_parse_range(now, period, &start, &end, error))
		return FAIL;

	time_tmp = end;
	tm_end = *localtime(&time_tmp);

	for (i = 0; i < season_num; i++)
	{
		if (skip <= i)
		{
			zbx_trend_state_t	state;

			state = zbx_trends_get_avg(table, itemid, start, end, &value_dbl);

			if (ZBX_TREND_STATE_NORMAL != state)
			{
				if (0 == i || ZBX_TREND_STATE_NODATA != state)
				{
					*error = zbx_strdup(NULL, zbx_trends_error(state));
					return FAIL;
				}
			}
			else
			{
				zbx_vector_dbl_append(values, value_dbl);
				zbx_vector_uint64_append(index, (zbx_uint64_t)(i - skip));
			}
		}

		zbx_tm_sub(&tm_end, 1, ZBX_TIME_UNIT_ISOYEAR);

		if (-1 == (end = (int)mktime(&tm_end)))
		{
			*error = zbx_dsprintf(*error, "cannot convert data period end time: %s", zbx_strerror(errno));
			return FAIL;
		}

		tm_start = tm_end;
		/* add an hour to get real end timestamp rather than last trend hour */
		zbx_tm_add(&tm_start, 1, ZBX_TIME_UNIT_HOUR);
		zbx_tm_sub(&tm_start, period_num, period_unit);

		if (-1 == (start = (int)mktime(&tm_start)))
		{
			*error = zbx_dsprintf(*error, "cannot convert data period start time: %s", zbx_strerror(errno));
			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get baseline data for the specified period                        *
 *                                                                            *
 * Parameters: itemid     - [IN] the item identifier                          *
 *             value_type - [IN] the item value type                          *
 *             now        - [IN] the current timestamp                        *
 *             period     - [IN] the data period                              *
 *             seasons    - [IN] the seasons                                  *
 *             skip       - [IN] how many data periods to skip                *
 *             values     - [OUT] the average data period value in each       *
 *                                season                                      *
 *             index      - [OUT] the index of returned values, starting      *
 *                                with 0. It will have values be from 0 to    *
 *                                <seasons num> + 1 - <skip> with holes for   *
 *                                periods without data.                       *
 *             error      - [OUT] the error message if parsing failed         *
 *                                                                            *
 * Return value: SUCCEED - data were retrieved successfully                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: By default the first period is included in the data. So the      *
 *           returned vector contains data for this period + data from        *
 *           seasons. To retrieve only data from seasons use pass 1 to skip   *
 *           parameter.                                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_baseline_get_data(zbx_uint64_t itemid, unsigned char value_type, time_t now, const char *period,
		int season_num, zbx_time_unit_t season_unit, int skip, zbx_vector_dbl_t *values,
		zbx_vector_uint64_t *index, char **error)
{
	zbx_time_unit_t	period_unit;
	int		ret = FAIL;
	const char	*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			table = "trends";
			break;
		case ITEM_VALUE_TYPE_UINT64:
			table = "trends_uint";
			break;
		default:
			*error = zbx_strdup(*error, "unsupported value type");
			goto out;
	}

	if (FAIL == zbx_trends_parse_base(period, &period_unit, error))
		goto out;

	if (ZBX_TIME_UNIT_MONTH == season_unit && ZBX_TIME_UNIT_WEEK == period_unit)
	{
		*error = zbx_strdup(*error, "weekly data periods cannot be used with month seasons");
		goto out;
	}

	if (season_unit < period_unit)
	{
		*error = zbx_strdup(*error, "season cannot be less than data period base");
		goto out;
	}

	/* include the data period which might be skipped because of 'skip' parameter */
	season_num++;

	if (ZBX_TIME_UNIT_WEEK == period_unit && ZBX_TIME_UNIT_YEAR == season_unit)
	{
		ret = baseline_get_isoyear_data(itemid, table, now, period, season_num, skip, values, index, error);
	}
	else
	{
		ret = baseline_get_common_data(itemid, table, now, period, season_num, season_unit, skip, values,
				index, error);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __func__, zbx_result_string(ret), ZBX_NULL2EMPTY_STR(*error));

	return ret;
}
