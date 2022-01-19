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

#include "zbxtrends.h"
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

int	zbx_tfc_get_value(zbx_uint64_t itemid, int start, int end, zbx_trend_function_t function, double *value,
		zbx_trend_state_t *state);
void	zbx_tfc_put_value(zbx_uint64_t itemid, int start, int end, zbx_trend_function_t function, double value,
		zbx_trend_state_t state);

#endif
