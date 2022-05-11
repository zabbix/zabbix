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

#ifndef ZABBIX_ITEM_PREPROC_H
#define ZABBIX_ITEM_PREPROC_H

#include "dbcache.h"
#include "preproc.h"

#define ZBX_PREPROC_MAX_PACKET_SIZE	(ZBX_MEBIBYTE * 128)

typedef struct
{
	unsigned char	type;
	void		*impl;
}
zbx_preproc_cache_ref_t;

ZBX_VECTOR_DECL(ppcache, zbx_preproc_cache_ref_t)

typedef struct
{
	zbx_vector_ppcache_t	refs;
}
zbx_preproc_cache_t;

int	zbx_item_preproc(zbx_preproc_cache_t *cache, unsigned char value_type, zbx_variant_t *value,
		const zbx_timespec_t *ts, const zbx_preproc_op_t *op, zbx_variant_t *history_value,
		zbx_timespec_t *history_ts, char **error);

int	zbx_item_preproc_handle_error(zbx_variant_t *value, const zbx_preproc_op_t *op, char **error);

int	zbx_item_preproc_convert_value_to_numeric(zbx_variant_t *value_num, const zbx_variant_t *value,
		unsigned char value_type, char **errmsg);

int	zbx_item_preproc_test(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_preproc_op_t *steps, int steps_num, zbx_vector_ptr_t *history_in, zbx_vector_ptr_t *history_out,
		zbx_preproc_result_t *results, int *results_num, char **error);

void	*zbx_preproc_cache_get(zbx_preproc_cache_t *cache, unsigned char type);
void	zbx_preproc_cache_put(zbx_preproc_cache_t *cache, unsigned char type, void *impl);
void	zbx_preproc_cache_init(zbx_preproc_cache_t *cache);
void	zbx_preproc_cache_clear(zbx_preproc_cache_t *cache);

#endif
