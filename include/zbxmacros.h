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

#ifndef ZABBIX_ZBXMACROS_H
#define ZABBIX_ZBXMACROS_H

#include "common.h"

#define DB_MACRO	struct zbx_macro_type
#define DB_MACRO_HOST	struct zbx_macro_host_type
#define DB_MACROS	struct zbx_macros_type

#define MACRO_MACRO_LEN			64
#define MACRO_MACRO_LEN_MAX		MACRO_MACRO_LEN+1
#define MACRO_VALUE_LEN			255
#define MACRO_VALUE_LEN_MAX		MACRO_VALUE_LEN+1

DB_MACRO
{
	char			*macro;
	char			*value;
};

DB_MACRO_HOST
{
	zbx_uint64_t		hostid;
	time_t			tm;
	int			alloc;
	int			num;
	DB_MACRO		*macro;
	int			tmpl_alloc;
	int			tmpl_num;
	zbx_uint64_t		*tmplids;
};

DB_MACROS
{
	int			alloc;
	int			num;
	DB_MACRO_HOST		*host;
};

void	zbxmacros_init(DB_MACROS **macros);
void	zbxmacros_free(DB_MACROS **macros);
void	zbxmacros_get_value(DB_MACROS *macros, zbx_uint64_t *hostids, int host_num, const char *macro, char **replace_to);
void	zbxmacros_get_value_by_triggerid(DB_MACROS *macros, zbx_uint64_t triggerid, const char *macro, char **replace_to);

#endif
