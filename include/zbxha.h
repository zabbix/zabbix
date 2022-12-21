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

#ifndef ZABBIX_ZBXHA_H
#define ZABBIX_ZBXHA_H

#define ZBX_IPC_SERVICE_HA	"haservice"

#define ZBX_IPC_SERVICE_HA_PAUSE		1
#define ZBX_IPC_SERVICE_HA_STOP			2
#define ZBX_IPC_SERVICE_HA_STATUS		3
#define ZBX_IPC_SERVICE_HA_GET_NODES		4
#define ZBX_IPC_SERVICE_HA_REMOVE_NODE		5
#define ZBX_IPC_SERVICE_HA_SET_FAILOVER_DELAY	6
#define ZBX_IPC_SERVICE_HA_GET_FAILOVER_DELAY	7
#define ZBX_IPC_SERVICE_HA_LOGLEVEL_INCREASE	8
#define ZBX_IPC_SERVICE_HA_LOGLEVEL_DECREASE	9

#define ZBX_IPC_SERVICE_HA_RTC_FIRST		(ZBX_IPC_RTC_MAX + 1)

#define ZBX_IPC_SERVICE_HA_REGISTER		ZBX_IPC_SERVICE_HA_RTC_FIRST
#define ZBX_IPC_SERVICE_HA_HEARTBEAT		(ZBX_IPC_SERVICE_HA_RTC_FIRST + 1)
#define ZBX_IPC_SERVICE_HA_STATUS_UPDATE	(ZBX_IPC_SERVICE_HA_RTC_FIRST + 2)

#define ZBX_HA_SERVICE_TIMEOUT	10

#define ZBX_NODE_STATUS_HATIMEOUT	-3
#define ZBX_NODE_STATUS_ERROR		-2
#define ZBX_NODE_STATUS_UNKNOWN		-1
#define ZBX_NODE_STATUS_STANDBY		0
#define ZBX_NODE_STATUS_STOPPED		1
#define ZBX_NODE_STATUS_UNAVAILABLE	2
#define ZBX_NODE_STATUS_ACTIVE		3

#define ZBX_HA_DEFAULT_FAILOVER_DELAY	SEC_PER_MIN

#define ZBX_HA_IS_CLUSTER()	(NULL != CONFIG_HA_NODE_NAME && '\0' != *CONFIG_HA_NODE_NAME)

int	zbx_ha_get_nodes(char **nodes, char **error);
int	zbx_ha_remove_node(const char *node, char **result, char **error);
int	zbx_ha_set_failover_delay(int delay, char **error);
int	zbx_ha_get_failover_delay(int *delay, char **error);
int	zbx_ha_change_loglevel(int direction, char **error);
const char	*zbx_ha_status_str(int ha_status);

#endif
