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

#ifndef ZABBIX_CHECKS_BROWSER_H
#define ZABBIX_CHECKS_BROWSER_H

#include "zbxcacheconfig.h"

void	browseritem_es_engine_init(void);
void	browseritem_es_engine_destroy(void);
int	get_value_browser(zbx_dc_item_t *item, const char *config_webdriver_url, const char *config_source_ip,
		AGENT_RESULT *result);

#endif
