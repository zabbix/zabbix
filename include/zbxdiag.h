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


#ifndef ZABBIX_ZBXDIAG_H
#define ZABBIX_ZBXDIAG_H

#include "zbxtypes.h"
#include "zbxjson.h"

typedef enum
{
	ZBX_DIAGINFO_UNDEFINED = -1,
	ZBX_DIAGINFO_ALL,
	ZBX_DIAGINFO_HISTORYCACHE,
	ZBX_DIAGINFO_VALUECACHE,
	ZBX_DIAGINFO_PREPROCESSING,
	ZBX_DIAGINFO_LLD,
	ZBX_DIAGINFO_ALERTING,
	ZBX_DIAGINFO_LOCKS
}
zbx_diaginfo_section_t;

#define ZBX_DIAG_HISTORYCACHE	"historycache"
#define ZBX_DIAG_VALUECACHE	"valuecache"
#define ZBX_DIAG_PREPROCESSING	"preprocessing"
#define ZBX_DIAG_LLD		"lld"
#define ZBX_DIAG_ALERTING	"alerting"
#define ZBX_DIAG_LOCKS		"locks"

int	zbx_diag_get_info(const struct zbx_json_parse *jp, char **info);
void	zbx_diag_log_info(unsigned int flags, char **result);

#endif
