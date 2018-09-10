/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef ZABBIX_ZBXEXEC_H
#define ZABBIX_ZBXEXEC_H

#define ZBX_EXIT_CODE_CHECKS_DISABLED	0
#define ZBX_EXIT_CODE_CHECKS_ENABLED	1

int	zbx_execute(const char *command, char **buffer, char *error, size_t max_error_len, int timeout,
		unsigned char flag);
int	zbx_execute_nowait(const char *command);

#endif
