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

#ifndef ZABBIX_CHECKS_SCRIPT_H
#define ZABBIX_CHECKS_SCRIPT_H

#include "zbxcacheconfig.h"

void	scriptitem_es_engine_init(void);
void	scriptitem_es_engine_destroy(void);
int	get_value_script(zbx_dc_item_t *item, const char *config_source_ip, AGENT_RESULT *result);

#endif
