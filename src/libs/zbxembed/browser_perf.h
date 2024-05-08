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

#ifndef ZABBIX_BROWSER_PERF_H
#define ZABBIX_BROWSER_PERF_H

#include "zbxvariant.h"
#include "zbxjson.h"
#include "zbxalgo.h"

typedef struct
{
	char		*name;
	zbx_variant_t	value;
}
zbx_wd_attr_t;

ZBX_PTR_VECTOR_DECL(wd_attr_ptr, zbx_wd_attr_t *)

typedef struct
{
	zbx_hashset_t	attrs;
}
zbx_wd_perf_entry_t;

ZBX_PTR_VECTOR_DECL(wd_perf_entry_ptr, zbx_wd_perf_entry_t *)

typedef struct
{
	zbx_wd_perf_entry_t		*navigation;
	zbx_wd_perf_entry_t		*resource;
	zbx_vector_wd_perf_entry_ptr_t	user;
}
zbx_wd_perf_details_t;

ZBX_VECTOR_DECL(wd_perf_details, zbx_wd_perf_details_t)

typedef struct
{
	char			*name;
	zbx_wd_perf_details_t	*details;
}
zbx_wd_perf_bookmark_t;

ZBX_VECTOR_DECL(wd_perf_bookmark, zbx_wd_perf_bookmark_t)

typedef struct
{
	zbx_vector_wd_perf_details_t	details;
	zbx_vector_wd_perf_bookmark_t	bookmarks;

	zbx_wd_perf_entry_t		*navigation_summary;
	zbx_wd_perf_entry_t		*resource_summary;
}
zbx_wd_perf_t;

void	wd_perf_init(zbx_wd_perf_t *perf);
void	wd_perf_destroy(zbx_wd_perf_t *perf);
void	wd_perf_collect(zbx_wd_perf_t *perf, const char *bookmark_name, const struct zbx_json_parse *jp);

#endif

