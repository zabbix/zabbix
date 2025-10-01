/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_DBCONFIG_LOCAL_H
#define ZABBIX_DBCONFIG_LOCAL_H

#include "zbxtypes.h"
#include "dbconfig.h"

typedef struct
{
	zbx_uint64_t	itemtagid;
	zbx_uint64_t	itemid;
}
zbx_dc_item_tag_link;

typedef struct
{
	zbx_vector_dc_preproc_op_ptr_t	ops;
	zbx_uint64_t			revision;
}
zbx_dcl_preproc_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_dcl_preproc_t	*preproc;
}
zbx_dcl_item_t;

ZBX_PTR_VECTOR_DECL(dcl_item_ptr, zbx_dcl_item_t *)

typedef struct
{
	zbx_hashset_t			items;
	pthread_mutex_t			mu;
	zbx_dc_um_shared_handle_t	*um_handle;
}
zbx_dcl_preproc_cache_t;

typedef struct
{
	zbx_hashset_t		strpool;
	zbx_hashset_t		item_tag_links;
	zbx_hashset_t		preprocops;
	zbx_hashset_t		items;
	zbx_dcl_preproc_cache_t	preproc;
}
zbx_dcl_config_t;

zbx_dcl_config_t	*dcl_config(void);

void	dcl_config_init(void);
void	dcl_config_clear(void);

/* string pool */
const char	*dcl_strpool_intern(const char *str);
void	dcl_strpool_release(const char *str);
const char	*dcl_strpool_acquire(const char *str);
int	dc_strpool_replace(int found, const char **curr, const char *new_str);

void	dcl_sync_item_preproc(zbx_dbsync_t *sync, zbx_uint64_t revision);
void	dcl_dump_item(zbx_uint64_t itemid);

#endif
