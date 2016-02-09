/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_EVALFUNC_H
#define ZABBIX_EVALFUNC_H

#include "common.h"
#include "db.h"

#define ZBX_FLAG_SEC	0
#define ZBX_FLAG_VALUES	1

extern int     CONFIG_SERVER_STARTUP_TIME;

int	cmp_double(double a, double b);

int	evaluate_macro_function(char *value, const char *host, const char *key, const char *function, const char *parameter);

int	replace_value_by_map(char *value, int max_len, zbx_uint64_t valuemapid);
int	add_value_suffix(char *value, int max_len, const char *units, int value_type);

#endif
