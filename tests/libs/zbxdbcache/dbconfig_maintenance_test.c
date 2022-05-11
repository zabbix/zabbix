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

#include "dbconfig_maintenance_test.h"

int	dc_maintenance_match_tags_test(const zbx_dc_maintenance_t *maintenance, const zbx_vector_ptr_t *tags)
{
	return dc_maintenance_match_tags(maintenance, tags);
}

int	dc_check_maintenance_period_test(const zbx_dc_maintenance_t *maintenance,
		const zbx_dc_maintenance_period_t *period, time_t now, time_t *running_since, time_t *running_until)
{
	return dc_check_maintenance_period(maintenance, period, now, running_since, running_until);
}
