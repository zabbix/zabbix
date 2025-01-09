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

#ifndef DBCONFIG_MAINTENANCE_TEST_H
#define DBCONFIG_MAINTENANCE_TEST_H

int	dc_maintenance_match_tags_test(const zbx_dc_maintenance_t *maintenance, const zbx_vector_tags_ptr_t *tags);
int	dc_check_maintenance_period_test(const zbx_dc_maintenance_t *maintenance,
		const zbx_dc_maintenance_period_t *period, time_t now, time_t *running_since, time_t *running_until);

#endif
