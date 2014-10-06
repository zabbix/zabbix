
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#ifndef ZABBIX_DISCOVERY_H
#define ZABBIX_DISCOVERY_H

#include "zbxjson.h"
#include "comms.h"

void	discovery_update_host(DB_DHOST *dhost, const char *ip, int status, int now);
void	discovery_update_service(DB_DRULE *drule, DB_DCHECK *dcheck, DB_DHOST *dhost,
		const char *ip, const char *dns, int port, int status, const char *value, int now);

#endif
