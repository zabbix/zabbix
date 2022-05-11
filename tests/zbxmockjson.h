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

#ifndef ZABBIX_MOCK_JSON_H
#define ZABBIX_MOCK_JSON_H

void	__zbx_mock_assert_json_eq(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *returned_value);

#define zbx_mock_assert_json_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_json_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#endif
