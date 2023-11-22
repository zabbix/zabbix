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

#ifndef ZABBIX_ZBXIPMI_H
#define ZABBIX_ZBXIPMI_H

#ifdef HAVE_OPENIPMI

#include "zbxcacheconfig.h"

typedef struct
{
	int	config_timeout;
	int	config_unavailable_delay;
	int	config_unreachable_period;
	int	config_unreachable_delay;
}
zbx_thread_ipmi_manager_args;

ZBX_THREAD_ENTRY(zbx_ipmi_manager_thread, args);
ZBX_THREAD_ENTRY(zbx_ipmi_poller_thread, args);

int	zbx_ipmi_test_item(const zbx_dc_item_t *item, char **info);

int	zbx_ipmi_execute_command(const zbx_dc_host_t *host, const char *command, char *error, size_t max_error_len);

#endif /* HAVE_OPENIPMI */

#endif
