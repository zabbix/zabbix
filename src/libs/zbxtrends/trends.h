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

#include "zbxtypes.h"

#ifndef ZABBIX_TRENDS_H
#define ZABBIX_TRENDS_H

typedef enum
{
	ZBX_TREND_FUNCTION_UNKNOWN,
	ZBX_TREND_FUNCTION_AVG,
	ZBX_TREND_FUNCTION_COUNT,
	ZBX_TREND_FUNCTION_DELTA,
	ZBX_TREND_FUNCTION_MAX,
	ZBX_TREND_FUNCTION_MIN,
	ZBX_TREND_FUNCTION_SUM
}
zbx_trend_function_t;

typedef enum
{
	ZBX_TREND_STATE_UNKNOWN,
	ZBX_TREND_STATE_NORMAL,
	ZBX_TREND_STATE_NODATA,
	ZBX_TREND_STATE_OVERFLOW,
	ZBX_TREND_STATE_COUNT
}
zbx_trend_state_t;

int	zbx_tfc_get_value(zbx_uint64_t itemid, time_t start, time_t end, zbx_trend_function_t function, double *value,
		zbx_trend_state_t *state);
void	zbx_tfc_put_value(zbx_uint64_t itemid, time_t start, time_t end, zbx_trend_function_t function, double value,
		zbx_trend_state_t state);
const char	*zbx_trends_error(zbx_trend_state_t state);
zbx_trend_state_t	zbx_trends_get_avg(const char *table, zbx_uint64_t itemid, time_t start, time_t end,
		double *value);

#endif
