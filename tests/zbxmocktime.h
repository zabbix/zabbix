/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#ifndef ZABBIX_MOCK_TIME_H
#define ZABBIX_MOCK_TIME_H

#include "zbxmockdata.h"

zbx_mock_error_t	zbx_strtime_to_timespec(const char *strtime, zbx_timespec_t *ts);
zbx_mock_error_t	zbx_strtime_tz_sec(const char *strtime, int *tz_sec);
zbx_mock_error_t	zbx_time_to_strtime(time_t timestamp, int tz_sec, char *buffer, size_t size);
zbx_mock_error_t	zbx_timespec_to_strtime(const zbx_timespec_t *ts, int tz_sec, char *buffer, size_t size);

#endif
