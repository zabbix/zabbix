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

static void	baseline_season_diff(const struct tm *first, const struct tm *second, zbx_tm_diff_t *diff)
{
	struct tm	tm = *second;

	if (0 > (diff->hours = second->tm_hour - first->tm_hour))
	{
		diff->hours += 24;
		zbx_tm_sub(&tm, 1, ZBX_TIME_UNIT_DAY);
	}

	if (0 > (diff->days = second->tm_mday - first->tm_mday))
	{
		zbx_tm_sub(&tm, 1, ZBX_TIME_UNIT_MONTH);
		diff->days += zbx_day_in_month(tm.tm_year + 1900, tm.tm_mon + 1);
	}

	diff->months = tm.tm_mon - first->tm_mon;
}

void	zbx_baseline_season_diff(const struct tm *season, zbx_time_unit_t season_unit, const struct tm *period,
		zbx_time_unit_t period_unit, zbx_tm_diff_t *diff)
{
	if (ZBX_TIME_UNIT_WEEK == period_unit)
	{
		struct tm	tm = *period;

		diff->months = 0;
		diff->weeks = ZBX_TIME_UNIT_YEAR == season_unit ? zbx_get_week_number(period) : 0;

		zbx_tm_round_down(&tm, ZBX_TIME_UNIT_WEEK);
		baseline_season_diff(&tm, period, diff);
	}
	else
	{
		diff->weeks = 0;
		baseline_season_diff(season, period, diff);
	}
}
