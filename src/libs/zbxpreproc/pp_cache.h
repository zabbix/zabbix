/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_PP_CACHE_H
#define ZABBIX_PP_CACHE_H

#include "pp_item.h"
#include "zbxvariant.h"

typedef struct
{
	zbx_uint32_t	refcount;

	zbx_variant_t	value;

	int		type;
	void		*data;
}
zbx_pp_cache_t;

zbx_pp_cache_t	*pp_cache_create(const zbx_pp_item_preproc_t *preproc, const zbx_variant_t *value);
void	pp_cache_release(zbx_pp_cache_t *cache);
zbx_pp_cache_t	*pp_cache_copy(zbx_pp_cache_t *cache);

void	pp_cache_copy_value(zbx_pp_cache_t *cache, int step_type, zbx_variant_t *value);
int	pp_cache_is_supported(zbx_pp_item_preproc_t *preproc);

#endif
