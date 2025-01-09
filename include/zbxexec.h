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

#ifndef ZABBIX_ZBXEXEC_H
#define ZABBIX_ZBXEXEC_H

#include "zbxsysinc.h"

#define ZBX_EXIT_CODE_CHECKS_DISABLED	0
#define ZBX_EXIT_CODE_CHECKS_ENABLED	1

int	zbx_execute(const char *command, char **output, char *error, size_t max_error_len, int timeout,
		unsigned char flag, const char *dir);
int	zbx_execute_nowait(const char *command);

#endif
