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

#include "dbconfig_maintenance_test.h"

int	dc_maintenance_match_tags_test(const zbx_dc_maintenance_t *maintenance, const zbx_vector_tags_ptr_t *tags)
{
	return dc_maintenance_match_tags(maintenance, tags);
}

int	dc_check_maintenance_period_test(const zbx_dc_maintenance_t *maintenance,
		const zbx_dc_maintenance_period_t *period, time_t now, time_t *running_since, time_t *running_until)
{
	return dc_check_maintenance_period(maintenance, period, now, running_since, running_until);
}
