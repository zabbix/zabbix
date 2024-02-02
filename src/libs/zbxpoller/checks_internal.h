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

#ifndef ZABBIX_CHECKS_INTERNAL_H
#define ZABBIX_CHECKS_INTERNAL_H

#include "zbxcachehistory.h"
#include "zbxcomms.h"

#include "poller_internal.h"

int	get_value_internal(const zbx_dc_item_t *item, AGENT_RESULT *result, const zbx_config_comms_args_t *config_comms,
		int config_startup_time, const char *config_java_gateway, int config_java_gateway_port,
		zbx_get_config_forks_f get_config_forks);

#endif
