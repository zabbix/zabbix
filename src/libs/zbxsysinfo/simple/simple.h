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

#ifndef ZABBIX_SYSINFO_SIMPLE_H
#define ZABBIX_SYSINFO_SIMPLE_H

#include "module.h"

int	check_service_perf(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_service(AGENT_REQUEST *request, AGENT_RESULT *result);
zbx_metric_t	*get_parameters_simple(void);

#endif /* ZABBIX_SYSINFO_SIMPLE_H */
