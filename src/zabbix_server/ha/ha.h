/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#define ZABBIX_HAH_H

#define ZBX_IPC_SERVICE_HA	"haservice"

#define ZBX_IPC_SERVICE_HA_REGISTER	0
#define ZBX_IPC_SERVICE_HA_PAUSE	1
#define ZBX_IPC_SERVICE_HA_STOP		2
#define ZBX_IPC_SERVICE_HA_REPORT	3
#define ZBX_IPC_SERVICE_HA_STATUS	4

#define ZBX_NODE_STATUS_ERROR		-2
#define ZBX_NODE_STATUS_UNKNOWN		-1
#define ZBX_NODE_STATUS_STANDBY		0
#define ZBX_NODE_STATUS_STOPPED		1
#define ZBX_NODE_STATUS_UNAVAILABLE	2
#define ZBX_NODE_STATUS_ACTIVE		3

int	zbx_ha_start(char **error, int ha_status);
int	zbx_ha_pause(char **error);
int	zbx_ha_stop(char **error);
void	zbx_ha_kill(void);
int	zbx_ha_get_status(char **error);
int	zbx_ha_recv_status(int timeout, int *status, char **error);
int	zbx_ha_report_status(char **error);

const char	*zbx_ha_status_str(int ha_status);

#endif


