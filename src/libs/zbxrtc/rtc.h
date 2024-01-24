/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_RTC_H
#define ZABBIX_RTC_H

#include "zbxtypes.h"

int	rtc_option_get_parameter(const char *param, size_t *size, char **error);
int	rtc_option_get_pid(const char *param, size_t *size, pid_t *pid, char **error);
int	rtc_option_get_process_type(const char *param, size_t *size, int *proc_type, char **error);
int	rtc_option_get_process_num(const char *param, size_t *size, int *proc_num, char **error);
int	rtc_option_get_prof_scope(const char *param, size_t pos, int *scope);

#endif
