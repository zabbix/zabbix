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

#ifndef ZABBIX_RTC_H
#define ZABBIX_RTC_H

#include "zbxtypes.h"

int	rtc_option_get_parameter(const char *param, size_t *size, char **error);
int	rtc_option_get_pid(const char *param, size_t *size, pid_t *pid, char **error);
int	rtc_option_get_process_type(const char *param, size_t *size, int *proc_type, char **error);
int	rtc_option_get_process_num(const char *param, size_t *size, int *proc_num, char **error);
int	rtc_option_get_prof_scope(const char *param, size_t pos, int *scope);

#endif
