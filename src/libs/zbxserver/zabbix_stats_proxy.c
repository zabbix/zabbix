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

#include "zabbix_stats.h"

#include "common.h"

/******************************************************************************
 *                                                                            *
 * Purpose: get program type (proxy) specific internal statistics             *
 *                                                                            *
 * Parameters: param1  - [IN/OUT] the json data                               *
 *                                                                            *
 * Comments: This function is used to gather proxy specific internal          *
 *           statistics.                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_get_zabbix_stats_ext(struct zbx_json *json)
{
	ZBX_UNUSED(json);

	return;
}
