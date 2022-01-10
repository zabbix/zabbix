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

#ifndef ZABBIX_ZBXRTC_H
#define ZABBIX_ZBXRTC_H

#include "zbxipcservice.h"

typedef struct
{
	zbx_ipc_service_t	service;
}
zbx_rtc_t;

/* provider API */
int	zbx_rtc_init(zbx_rtc_t *rtc ,char **error);
void 	zbx_rtc_dispatch(zbx_ipc_client_t *client, zbx_ipc_message_t *message);
void	zbx_rtc_wait_config_sync(zbx_rtc_t *rtc);

/* client API */
int	zbx_rtc_process(const char *option, char **error);
int	zbx_rtc_open(zbx_ipc_async_socket_t *asocket, int timeout, char **error);
int	zbx_rtc_notify_config_sync(char **error);

#endif
