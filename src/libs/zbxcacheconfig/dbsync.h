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

#ifndef ZABBIX_DBSYNC_H
#define ZABBIX_DBSYNC_H

#include "dbconfig.h"

#include "zbxalgo.h"
#include "zbxdb.h"

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
#define ZBX_DBSYNC_UPDATE_TRIGGERS		__UINT64_C(0x0008)
#define ZBX_DBSYNC_UPDATE_TRIGGER_DEPENDENCY	__UINT64_C(0x0010)
#define ZBX_DBSYNC_UPDATE_HOST_GROUPS		__UINT64_C(0x0020)
#define ZBX_DBSYNC_UPDATE_MAINTENANCE_GROUPS	__UINT64_C(0x0040)
#define ZBX_DBSYNC_UPDATE_MACROS		__UINT64_C(0x0080)

#define ZBX_DBSYNC_TRIGGER_ERROR	0x80


#define ZBX_DBSYNC_TYPE_DIFF		0
#define ZBX_DBSYNC_TYPE_CHANGELOG	1

/******************************************************************************
 *                                                                            *
 * Purpose: applies necessary preprocessing before row is compared/used       *
 *                                                                            *
 * Parameter: row - [IN] the row to preprocess                                *
 *                                                                            *
 * Return value: the preprocessed row                                         *
 *                                                                            *
 * Comments: The row preprocessing can be used to expand user macros in       *
 *           some columns.                                                    *
 *                                                                            *
 ******************************************************************************/
typedef char **(*zbx_dbsync_preproc_row_func_t)(zbx_dbsync_t *sync, char **row);

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

struct zbx_dbsync
{
	/* the synchronization mode (see ZBX_DBSYNC_* defines) */
	unsigned char			mode;


	unsigned char			type;

	/* the number of columns in diff */
	int				columns_num;

	/* the current row */
	int				row_index;

	/* the changed rows */
	zbx_vector_ptr_t		rows;

	/* the database result set for ZBX_DBSYNC_ALL mode */
	zbx_db_result_t			dbresult;

	/* the row preprocessing function */
	zbx_dbsync_preproc_row_func_t	preproc_row_func;

	/* the pre-processed row */
	char				**row;

	/* the preprocessed columns  */
	zbx_vector_ptr_t		columns;

	/* statistics */
	const char	*from;
	zbx_uint64_t	add_num;
	zbx_uint64_t	update_num;
	zbx_uint64_t	remove_num;
	double		start;
	double		sql_time;
	double		sync_time;
	zbx_uint64_t	used;
	zbx_int64_t	sync_size;
};

void	zbx_dbsync_env_init(zbx_dc_config_t *cache);
int	zbx_dbsync_env_prepare(unsigned char mode);
void	zbx_dbsync_env_flush_changelog(void);
void	zbx_dbsync_env_clear(void);
int	zbx_dbsync_env_changelog_num(void);
int	zbx_dbsync_env_changelog_dbsyncs_new_records(void);

void	zbx_dbsync_init(zbx_dbsync_t *sync, const char *name, unsigned char mode);
void	zbx_dbsync_init_changelog(zbx_dbsync_t *sync, const char *name, unsigned char mode);
void	zbx_dbsync_clear(zbx_dbsync_t *sync);
int	zbx_dbsync_get_row_num(const zbx_dbsync_t *sync);
int	zbx_dbsync_next(zbx_dbsync_t *sync, zbx_uint64_t *rowid, char ***row, unsigned char *tag);

int	zbx_dbsync_compare_config(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_autoreg_psk(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_autoreg_host(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_hosts(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_inventory(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_templates(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_global_macros(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_macros(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_interfaces(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_item_discovery(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_items(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_template_items(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_triggers(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_trigger_dependency(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_functions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_expressions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_actions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_action_ops(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_action_conditions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_trigger_tags(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_item_tags(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_tags(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_correlations(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_corr_conditions(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_corr_operations(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_groups(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_item_preprocs(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_item_script_param(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_maintenances(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_maintenance_tags(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_maintenance_periods(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_maintenance_groups(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_maintenance_hosts(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_group_hosts(zbx_dbsync_t *sync);

int	zbx_dbsync_prepare_drules(zbx_dbsync_t *sync);
int	zbx_dbsync_prepare_dchecks(zbx_dbsync_t *sync);

int	zbx_dbsync_prepare_httptests(zbx_dbsync_t *sync);
int	zbx_dbsync_prepare_httptest_fields(zbx_dbsync_t *sync);
int	zbx_dbsync_prepare_httpsteps(zbx_dbsync_t *sync);
int	zbx_dbsync_prepare_httpstep_fields(zbx_dbsync_t *sync);
void	zbx_dbsync_clear_user_macros(void);

int	zbx_dbsync_compare_connectors(zbx_dbsync_t *sync);
int	zbx_dbsync_compare_connector_tags(zbx_dbsync_t *sync);

int	zbx_dbsync_compare_proxies(zbx_dbsync_t *sync);

int	zbx_dbsync_prepare_proxy_group(zbx_dbsync_t *sync);
int	zbx_dbsync_prepare_host_proxy(zbx_dbsync_t *sync);
void	zbx_dcsync_sql_start(zbx_dbsync_t *sync);
void	zbx_dcsync_sql_end(zbx_dbsync_t *sync);
void	zbx_dcsync_sync_start(zbx_dbsync_t *sync, zbx_uint64_t used_size);
void	zbx_dcsync_sync_end(zbx_dbsync_t *sync, zbx_uint64_t used_size);
void	zbx_dcsync_stats_dump(const char *function_name);

#endif /* BUILD_SRC_LIBS_ZBXDBCACHE_DBSYNC_H_ */
