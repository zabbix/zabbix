/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_TRAPPER_REQUEST_H
#define ZABBIX_TRAPPER_REQUEST_H

#include "zbxcomms.h"
#include "zbxjson.h"
#include "zbxvault.h"
#include "zbxdbhigh.h"

int	trapper_process_request(const char *request, zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_timespec_t *ts, const zbx_config_comms_args_t *config_comms,
		const zbx_config_vault_t *config_vault, int proxydata_frequency,
		zbx_get_program_type_f get_program_type_cb, const zbx_events_funcs_t *events_cbs);

int	init_proxy_history_lock(char **error);
void	free_proxy_history_lock(void);

#endif
