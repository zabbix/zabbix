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

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_get_row_hostids_t                                     *
 *                                                                            *
 * Purpose: retrieves host identifiers associated with the data row           *
 *                                                                            *
 * Parameter: row     - [IN] the data row                                     *
 *            hostids - [IN] the associated host identifiers                  *
 *                                                                            *
 * Comments: For example for host it would be the host identifier,            *
 *                           item - the item.hostid,                          *
 *                           trigger - host identifiers of items used         *
 *                                     in trigger expressions                 *
 *                                                                            *
 ******************************************************************************/
typedef void (*zbx_dbsync_get_row_hostids_t)(char **row, zbx_vector_uint64_t *hostids);

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
	unsigned char			mode;

	/* the number of columns in diff */
	int				columns_num;

	/* the current row */
	int				row_index;

	/* the changed rows */
	zbx_vector_ptr_t		rows;

	/* the database result set for ZBX_DBSYNC_ALL mode */
	DB_RESULT			dbresult;

	/* a list of columns with user macros that will expanded during synchronization process */
	zbx_vector_ptr_t		columns;

	/* row with expanded macros */
	char				**row;

	/* function to retrieve associated hostids to resolve user macros */
	zbx_dbsync_get_row_hostids_t	get_hostids_func;

	/* statistics */
	zbx_uint64_t	add_num;
	zbx_uint64_t	update_num;
	zbx_uint64_t	remove_num;
}
zbx_dbsync_t;

void	zbx_dbsync_init_env(ZBX_DC_CONFIG *cache);
void	zbx_dbsync_free_env();

void	zbx_dbsync_init(zbx_dbsync_t *sync, unsigned char mode);
void	zbx_dbsync_clear(zbx_dbsync_t *sync);
int	zbx_dbsync_next(zbx_dbsync_t *sync, zbx_uint64_t *rowid, char ***rows, unsigned char *tag);

int	zbx_dbsync_compare_config(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_hosts(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_inventory(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_templates(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_global_macros(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_macros(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_interfaces(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_items(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_triggers(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_trigger_dependency(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_functions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_expressions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_actions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_action_conditions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_trigger_tags(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_correlations(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_corr_conditions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_corr_operations(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_groups(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_item_preprocs(zbx_dbsync_t *sync);


#endif /* BUILD_SRC_LIBS_ZBXDBCACHE_DBSYNC_H_ */
