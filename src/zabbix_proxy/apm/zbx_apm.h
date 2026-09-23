/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_ZBX_APM_H
#define ZABBIX_ZBX_APM_H

#include "zbxtelemetry.h"
typedef struct
{
	int			config_timeout;
	zbx_apm_db_config_t	*export_config;
	int			port;
	const char		*ca_file;
	const char		*cert_file;
	const char		*key_file;
	const char		*ca_location;
	const char		*source_ip;
}
zbx_thread_apm_manager_args_t;

void	*zbx_apm_manager_thread(void *args);

#endif
