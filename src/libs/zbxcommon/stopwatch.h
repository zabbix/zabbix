/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#ifndef ZABBIX_STOPWATCH_H
#define ZABBIX_STOPWATCH_H

#include "common.h"

typedef struct
{
	double	start_time;
	double	elapsed_time;
}
zbx_stopwatch_t;

void	zbx_stopwatch_reset(zbx_stopwatch_t *sw);	/* always call this before first use */
double	zbx_stopwatch_elapsed(zbx_stopwatch_t *sw);	/* returns elapsed time in seconds, with nanoseconds in */
							/* fractional part */
void	zbx_stopwatch_start(zbx_stopwatch_t *sw);	/* start counting time */
void	zbx_stopwatch_stop(zbx_stopwatch_t *sw);	/* stop counting time and add the last measured interval */
							/* to elapsed time */
#endif
