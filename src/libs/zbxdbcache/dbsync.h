/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#ifndef ZABBIX_DBSYNC_H
#define ZABBIX_DBSYNC_H

#include "common.h"

/* no changes */
#define ZBX_DBSYNC_ROW_NONE	0
/*  a new object must be added to configuration cache */
#define ZBX_DBSYNC_ROW_ADD	1
/* a cached object must be updated in configuration cache */
#define ZBX_DBSYNC_ROW_UPDATE	2
/* a cached object must be removed from configuration cache */
#define ZBX_DBSYNC_ROW_REMOVE	3

#define ZBX_DBSYNC_UPDATE_HOSTS			__UINT64_C(0x0001)
#define ZBX_DBSYNC_UPDATE_ITEMS			__UINT64_C(0x0002)
#define ZBX_DBSYNC_UPDATE_FUNCTIONS		__UINT64_C(0x0004)
#define ZBX_DBSYNC_UPDATE_MACROS		__UINT64_C(0x0008)
#define ZBX_DBSYNC_UPDATE_HOST_TEMPLATES	__UINT64_C(0x0010)
#define ZBX_DBSYNC_UPDATE_TRIGGERS		__UINT64_C(0x0020)
#define ZBX_DBSYNC_UPDATE_TRIGGER_DEPENDENCY	__UINT64_C(0x0040)
#define ZBX_DBSYNC_UPDATE_HOST_GROUPS		__UINT64_C(0x0080)

typedef struct
{
	/* a row tag, describing the changes (see ZBX_DBSYNC_ROW_* defines) */
	unsigned char	tag;

	/* the identifier of the object represented by the row */
	zbx_uint64_t	rowid;

	/* the column values, NULL if the tag is ZBX_DBSYNC_ROW_REMOVE */
	char		**row;
}
zbx_dbsync_row_t;

typedef struct
{
	/* the synchronization mode (see ZBX_DBSYNC_* defines) */
	unsigned char		mode;

	/* the number of columns in diff */
	int			columns_num;

	/* the current row */
	int			row_index;

	/* the changed rows */
	zbx_vector_ptr_t	rows;

	/* the database result set for ZBX_DBSYNC_ALL mode */
	DB_RESULT		dbresult;

	/* statistics */
	zbx_uint64_t	add_num;
	zbx_uint64_t	update_num;
	zbx_uint64_t	remove_num;
}
zbx_dbsync_t;

void	zbx_dbsync_init(zbx_dbsync_t *sync, unsigned char mode);
void	zbx_dbsync_clear(zbx_dbsync_t *sync);
void	zbx_dbsync_reset(zbx_dbsync_t *sync);
int	zbx_dbsync_next(zbx_dbsync_t *sync, zbx_uint64_t *rowid, char ***rows, unsigned char *tag);

int	zbx_dbsync_compare_config(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_hosts(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_inventory(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_templates(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_global_macros(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_macros(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_interfaces(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_items(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_triggers(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_trigger_dependency(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_functions(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_expressions(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_actions(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_action_conditions(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_trigger_tags(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_correlations(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_corr_conditions(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_corr_operations(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_groups(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_item_preprocs(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);


#endif /* BUILD_SRC_LIBS_ZBXDBCACHE_DBSYNC_H_ */
