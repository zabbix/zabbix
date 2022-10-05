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

#ifndef ZABBIX_HA_H
#define ZABBIX_HA_H

#include "zbxrtc.h"

typedef struct
{
	char	str[CUID_LEN];
}
zbx_cuid_t;

#define zbx_cuid_empty(a)	('\0' == *(a).str ? SUCCEED : FAIL)
#define zbx_cuid_compare(a, b)	(0 == memcmp((a).str, (b).str, CUID_LEN) ? SUCCEED : FAIL)
#define zbx_cuid_clear(a)	memset((a).str, 0, CUID_LEN)

int	zbx_ha_start(zbx_rtc_t *rtc, int ha_status, char **error);
int	zbx_ha_pause(char **error);
int	zbx_ha_stop(char **error);
void	zbx_ha_kill(void);
int	zbx_ha_get_status(int *ha_status, int *ha_failover_delay, char **error);
int	zbx_ha_dispatch_message(zbx_ipc_message_t *message, int *ha_status, int *ha_failover_delay, char **error);

int	zbx_ha_check_pid(pid_t pid);

#endif
