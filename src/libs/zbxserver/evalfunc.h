/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#ifndef ZABBIX_EVALFUNC_H
#define ZABBIX_EVALFUNC_H

#include "common.h"
#include "db.h"

#define ZBX_FLAG_SEC	0
#define ZBX_FLAG_VALUES	1

int	evaluate_macro_function(char *value, const char *host, const char *key,
		const char *function, const char *parameter);
int	evaluatable_for_notsupported(const char *fn);

#endif
