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

#ifndef ZABBIX_RTC_SERVER_H
#define ZABBIX_RTC_SERVER_H

#include "zbxrtc.h"
#include "zbxipcservice.h"

int	rtc_process_request_ex_server(zbx_rtc_t *rtc, zbx_uint32_t code, const unsigned char *data, char **result);
int	rtc_process(const char *option, int config_timeout, char **error);
void	rtc_reset(zbx_rtc_t *rtc);
int	rtc_open(zbx_ipc_async_socket_t *asocket, int timeout, char **error);
#endif
