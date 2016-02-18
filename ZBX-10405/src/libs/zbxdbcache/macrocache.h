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

#ifndef ZABBIX_MACROCACHE_H
#define ZABBIX_MACROCACHE_H

#include "zbxtypes.h"
#include "zbxalgo.h"

/* user macro structure used to store user macros in user macro cache */
typedef struct
{
	char	*name;
	char	*context;
	char	*value;
}
zbx_umc_macro_t;

/* user macro cache object */
typedef struct
{
	/* the object id, for example trigger id */
	zbx_uint64_t		objectid;
	/* the macro source hosts */
	zbx_vector_uint64_t	hostids;
	/* the macro:value pairs */
	zbx_vector_ptr_t	macros;
}
zbx_umc_object_t;

int	zbx_umc_compare_macro(const void *d1, const void *d2);

#endif
