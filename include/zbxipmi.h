/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_ZBXIPMI_H
#define ZABBIX_ZBXIPMI_H

#ifdef HAVE_OPENIPMI

#include "zbxcommon.h"
#include "zbxcacheconfig.h"
#include "zbxthreads.h"

typedef struct
{
	int			config_timeout;
	int			config_unavailable_delay;
	int			config_unreachable_period;
	int			config_unreachable_delay;
	zbx_get_config_forks_f	get_config_forks;
}
zbx_thread_ipmi_manager_args;

ZBX_THREAD_ENTRY(zbx_ipmi_manager_thread, args);
ZBX_THREAD_ENTRY(zbx_ipmi_poller_thread, args);

int	zbx_ipmi_test_item(const zbx_dc_item_t *item, char **info);

int	zbx_ipmi_execute_command(const zbx_dc_host_t *host, const char *command, char *error, size_t max_error_len);

#endif /* HAVE_OPENIPMI */

#endif
