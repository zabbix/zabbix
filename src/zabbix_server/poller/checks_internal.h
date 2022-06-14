/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_CHECKS_INTERNAL_H
#define ZABBIX_CHECKS_INTERNAL_H

#include "dbcache.h"

extern int	CONFIG_SERVER_STARTUP_TIME;

int	get_value_internal(const DC_ITEM *item, AGENT_RESULT *result);

int	zbx_get_value_internal_ext(const char *param1, const AGENT_REQUEST *request, AGENT_RESULT *result);

#endif
