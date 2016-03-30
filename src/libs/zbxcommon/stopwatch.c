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

#include "stopwatch.h"

void	zbx_stopwatch_reset(zbx_stopwatch_t *sw)
{
	sw->elapsed_time = 0.0;
}

double	zbx_stopwatch_elapsed(zbx_stopwatch_t *sw)
{
	return sw->elapsed_time;
}

void	zbx_stopwatch_start(zbx_stopwatch_t *sw)
{
	sw->start_time = zbx_time();
}

void	zbx_stopwatch_stop(zbx_stopwatch_t *sw)
{
	sw->elapsed_time += zbx_time() - sw->start_time;
}
