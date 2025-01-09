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

#ifndef ZABBIX_COMMON_TRIM_UTF8_H
#define ZABBIX_COMMON_TRIM_UTF8_H

#define ZABBIX_MOCK_LTRIM_UTF8	0
#define ZABBIX_MOCK_RTRIM_UTF8	1

void	zbx_mock_test_entry_common_trim_utf8(void **state, int trim_utf8_func);
#endif
