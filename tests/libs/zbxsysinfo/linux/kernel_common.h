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

#ifndef KERNEL_COMMON_H
#define KERNEL_COMMON_H

#define ZABBIX_MOCK_KERNEL_MAXPROC	0
#define ZABBIX_MOCK_KERNEL_MAXFILES	1

void	zbx_mock_test_entry_kernel_common(void **state, int kernel_func);
#endif
