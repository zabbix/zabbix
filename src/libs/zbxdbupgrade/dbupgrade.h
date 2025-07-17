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

#ifndef ZABBIX_DBUPGRADE_H
#define ZABBIX_DBUPGRADE_H

#include "zbxcommon.h"
#include "zbxdbschema.h"

typedef struct
{
	int		(*function)(void);
	int		version;
	int		duplicates;
	unsigned char	mandatory;
}
zbx_dbpatch_t;

#define ZBX_DBPATCH_FUNCTION_PARAM_LEN			255

#define DBPATCH_VERSION(zabbix_version)			zbx_dbpatches_##zabbix_version
#define DBPATCHES_ARRAY_DECL(zabbix_version)		extern zbx_dbpatch_t	zbx_dbpatches_##zabbix_version[]

#define DBPATCH_START(zabbix_version)			zbx_dbpatch_t	DBPATCH_VERSION(zabbix_version)[] = {
#define DBPATCH_END()					{0}};

#ifdef HAVE_SQLITE3

#define DBPATCH_ADD(version, duplicates, mandatory)	{NULL, version, duplicates, mandatory},

#else

#define DBPATCH_ADD(version, duplicates, mandatory)	{DBpatch_##version, version, duplicates, mandatory},

#ifdef HAVE_MYSQL
#define ZBX_FS_SQL_NAME "`%s`"
#else
#define ZBX_FS_SQL_NAME "%s"
#endif

int	DBcreate_table(const zbx_db_table_t *table);
int	DBrename_table(const char *table_name, const char *new_name);
int	DBdrop_table(const char *table_name);
int	DBadd_field(const char *table_name, const zbx_db_field_t *field);
int	DBrename_field(const char *table_name, const char *field_name, const zbx_db_field_t *field);
int	DBmodify_field_type(const char *table_name, const zbx_db_field_t *field, const zbx_db_field_t *old_field);
int	DBdrop_field_autoincrement(const char *table_name, const zbx_db_field_t *field);
int	DBset_not_null(const char *table_name, const zbx_db_field_t *field);
int	DBset_default(const char *table_name, const zbx_db_field_t *field);
int	DBdrop_default(const char *table_name, const zbx_db_field_t *field);
int	DBdrop_not_null(const char *table_name, const zbx_db_field_t *field);
int	DBdrop_field(const char *table_name, const char *field_name);
int	DBcreate_index(const char *table_name, const char *index_name, const char *fields, int unique);
int	DBdrop_index(const char *table_name, const char *index_name);
int	DBrename_index(const char *table_name, const char *old_name, const char *new_name, const char *fields,
		int unique);
int	DBadd_foreign_key(const char *table_name, int id, const zbx_db_field_t *field);
int	DBdrop_foreign_key(const char *table_name, int id);

#if defined(HAVE_POSTGRESQL)
int	DBcheck_field_type(const char *table_name, const zbx_db_field_t *field);
#endif

int	DBcreate_changelog_insert_trigger(const char *table_name, const char *field_name);
int	DBcreate_changelog_update_trigger(const char *table_name, const char *field_name);
int	DBcreate_changelog_delete_trigger(const char *table_name, const char *field_name);

int	zbx_dbupgrade_attach_trigger_with_function_on_insert(const char *table_name,
		const char *original_column_name, const char *indexed_column_name, const char *function,
		const char *idname);
int	zbx_dbupgrade_drop_trigger_on_insert(const char *table_name, const char *indexed_column_name);
int	zbx_dbupgrade_drop_trigger_function_on_insert(const char *table_name, const char *indexed_column_name,
		const char *function);

int	zbx_dbupgrade_attach_trigger_with_function_on_update(const char *table_name,
		const char *original_column_name, const char *indexed_column_name, const char *function,
		const char *idname);
int	zbx_dbupgrade_drop_trigger_on_update(const char *table_name, const char *indexed_column_name);
int	zbx_dbupgrade_drop_trigger_function_on_update(const char *table_name, const char *indexed_column_name,
		const char *function);

#endif /* !HAVE_SQLITE3 */

unsigned char	DBget_program_type(void);
int		DBget_config_timeout(void);

#endif
