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

#ifndef ZABBIX_PID_H
#define ZABBIX_PID_H

#include "zbxsysinc.h"

#ifdef _WINDOWS
#	error "This module allowed only for Unix OS"
#endif

int	create_pid_file(const char *pidfile);
int	read_pid_file(const char *pidfile, pid_t *pid, char *error, size_t max_error_len);
void	drop_pid_file(const char *pidfile);
#endif /* ZABBIX_PID_H */
