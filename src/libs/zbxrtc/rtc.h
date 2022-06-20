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

#ifndef ZABBIX_RTC_H
#define ZABBIX_RTC_H

#include "zbxtypes.h"
#include "zbxrtc.h"

#define ZBX_IPC_SERVICE_RTC	"rtc"

int	zbx_rtc_parse_loglevel_option(const char *opt, size_t len, pid_t *pid, int *proc_type, int *proc_num,
		char **error);

int	rtc_parse_options_ex(const char *opt, zbx_uint32_t *code, char **data, char **error);
int	rtc_process_request_ex(zbx_rtc_t *rtc, int code, const unsigned char *data, char **result);

void	rtc_notify(zbx_rtc_t *rtc, unsigned char process_type, int process_num, zbx_uint32_t code,
		const unsigned char *data, zbx_uint32_t size);

#endif
