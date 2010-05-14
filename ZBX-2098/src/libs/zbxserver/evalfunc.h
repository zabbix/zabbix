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

#define	EVALUATE_FUNCTION_NORMAL	0
#define	EVALUATE_FUNCTION_SUFFIX	1

#define ZBX_FLAG_SEC			0
#define ZBX_FLAG_VALUES			1

extern  int     CONFIG_SERVER_STARTUP_TIME;

/*int	evaluate_function(char *value, DB_ITEM *item, char *function, char *parameter, int now);*/
int	evaluate_function2(char *value,char *host,char *key,char *function,char *parameter);
int	replace_value_by_map(char *value, zbx_uint64_t valuemapid);
int	add_value_suffix(char *value, int max_len, char *units, int value_type);

#endif
