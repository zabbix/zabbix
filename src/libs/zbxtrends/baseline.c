/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "zbxtrends.h"
#include "log.h"

static int	baseline_get_common_data(zbx_uint64_t itemid, const char *table, time_t now, const char *period,
		int season_num, zbx_time_unit_t season_unit, int skip, zbx_vector_dbl_t *values, char **error)
{
	int		i, start, end;
	double		value_dbl;
	struct tm	tm, tm_now;

	tm_now = *localtime(&now);

	for (i = 0; i < season_num; i++)
	{
		if (FAIL == zbx_trends_parse_range(now, period, &start, &end, error))
			return FAIL;

		if (skip <= i)
		{
			if (FAIL == zbx_trends_eval_avg(table, itemid, start, end, &value_dbl, error))
				return FAIL;

			zbx_vector_dbl_append(values, value_dbl);
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

static int	baseline_get_isoyear_data(zbx_uint64_t itemid, const char *table, time_t now, const char *period,
		int season_num, int skip, zbx_vector_dbl_t *values, char **error)
{
	int		i, start, end, period_num;
	time_t		time_tmp;
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
		if (i <= skip)
		{
			if (FAIL == zbx_trends_eval_avg(table, itemid, start, end, &value_dbl, error))
				return FAIL;

			zbx_vector_dbl_append(values, value_dbl);
		}

		zbx_tm_sub(&tm_end, 1, ZBX_TIME_UNIT_ISOYEAR);

		if (-1 == (end = (int)mktime(&tm_end)))
		{
			*error = zbx_dsprintf(*error, "cannot convert data period end time: %s", zbx_strerror(errno));
			return FAIL;
		}

		tm_start = tm_end;
		zbx_tm_sub(&tm_start, period_num, period_unit);

		if (-1 == (end = (int)mktime(&tm_start)))
		{
			*error = zbx_dsprintf(*error, "cannot convert data period start time: %s", zbx_strerror(errno));
			return FAIL;
		}
	}

	return SUCCEED;
}

int	zbx_baseline_get_data(zbx_uint64_t itemid, unsigned char value_type, time_t now, const char *period,
		const char *seasons, int skip, zbx_vector_dbl_t *values, char **error)
{
	zbx_time_unit_t	period_unit, season_unit;
	int		season_num, ret;
	size_t		len;
	const char	*table;

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
			return FAIL;
	}

	if (FAIL == zbx_trends_parse_base(period, &period_unit, error))
		return FAIL;

	if (FAIL == zbx_tm_parse_period(seasons, &len, &season_num, &season_unit, error))
		return FAIL;

	if (ZBX_TIME_UNIT_MONTH == season_unit && ZBX_TIME_UNIT_WEEK == period_unit)
	{
		*error = zbx_strdup(*error, "weekly data periods cannot be used with month seasons");
		return FAIL;
	}

	/* include the data period which might be skipped because of 'skip' parameter */
	season_num++;

	if (ZBX_TIME_UNIT_WEEK == period_unit && ZBX_TIME_UNIT_YEAR == season_unit)
		ret = baseline_get_isoyear_data(itemid, table, now, period, season_num, skip, values, error);
	else
		ret = baseline_get_common_data(itemid, table, now, period, season_num, season_unit, skip, values, error);

	return ret;
}
