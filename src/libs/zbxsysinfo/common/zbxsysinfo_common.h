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

#ifndef ZABBIX_SYSINFO_COMMON_H
#define ZABBIX_SYSINFO_COMMON_H

#include "module.h"

zbx_metric_t	*get_parameters_common(void);
zbx_metric_t	*get_parameters_common_local(void);

int	execute_user_parameter(AGENT_REQUEST *request, AGENT_RESULT *result);
int	execute_str(const char *command, AGENT_RESULT *result, int timeout);
int	execute_dbl(const char *command, AGENT_RESULT *result, int timeout);
int	execute_int(const char *command, AGENT_RESULT *result, int timeout);

#endif
