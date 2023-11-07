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

#ifndef ZABBIX_POLLER_H
#define ZABBIX_POLLER_H

#include "zbxcacheconfig.h"
#include "module.h"

void	zbx_activate_item_interface(zbx_timespec_t *ts, zbx_dc_interface_t *interface, zbx_uint64_t itemid, int type,
		char *host, unsigned char **data, size_t *data_alloc, size_t *data_offset);
void	zbx_deactivate_item_interface(zbx_timespec_t *ts, zbx_dc_interface_t *interface, zbx_uint64_t itemid, int type,
		char *host, char *key_orig, unsigned char **data, size_t *data_alloc, size_t *data_offset,
		int unavailable_delay, int unreachable_period, int unreachable_delay, const char *error);

void	zbx_agent_handle_response(zbx_socket_t *s, ssize_t received_len, int *ret, char *addr, AGENT_RESULT *result);

int	zbx_telnet_get_value(zbx_dc_item_t *item, const char *config_source_ip, AGENT_RESULT *result);
int	zbx_agent_get_value(const zbx_dc_item_t *item, const char *config_source_ip, unsigned char program_type,
		AGENT_RESULT *result);
#if defined(HAVE_SSH2) || defined(HAVE_SSH)
int	zbx_ssh_get_value(zbx_dc_item_t *item, const char *config_source_ip, AGENT_RESULT *result);
#endif

#endif
