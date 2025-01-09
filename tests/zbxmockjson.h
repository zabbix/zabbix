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

#ifndef ZABBIX_MOCK_JSON_H
#define ZABBIX_MOCK_JSON_H

void	__zbx_mock_assert_json_eq(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *returned_value);

#define zbx_mock_assert_json_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_json_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#endif
