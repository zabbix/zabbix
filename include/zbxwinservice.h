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

#ifndef ZABBIX_SERVICE_H
#define ZABBIX_SERVICE_H

#ifndef _WINDOWS
#	error "This module is only available for Windows OS"
#endif

#include "zbxthreads.h"

#define ZBX_SERVICE_STARTUP_AUTOMATIC	"automatic"
#define ZBX_SERVICE_STARTUP_DELAYED	"delayed"
#define ZBX_SERVICE_STARTUP_MANUAL	"manual"
#define ZBX_SERVICE_STARTUP_DISABLED	"disabled"

typedef void	(*zbx_on_exit_t)(int);

void	zbx_service_init(zbx_get_config_str_f get_zbx_service_name_f, zbx_get_config_str_f get_zbx_event_source_f);
void	zbx_service_start(int flags);

int	ZabbixCreateService(const char *path, const char *config_file, unsigned int flags);
int	ZabbixRemoveService(void);
int	ZabbixStartService(void);
int	ZabbixStopService(void);
int	zbx_service_startup_type_change(unsigned int flags);

void	zbx_set_parent_signal_handler(zbx_on_exit_t zbx_on_exit_cb_arg);
int	zbx_service_startup_flags_set(const char *opt, unsigned int *flags);

int	ZBX_IS_RUNNING(void);
void	ZBX_DO_EXIT(void);

#endif /* ZABBIX_SERVICE_H */
