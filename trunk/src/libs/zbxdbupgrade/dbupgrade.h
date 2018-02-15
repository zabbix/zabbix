/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef ZABBIX_DBUPGRADE_H
#define ZABBIX_DBUPGRADE_H

typedef struct
{
	int		(*function)(void);
	int		version;
	int		duplicates;
	unsigned char	mandatory;
}
zbx_dbpatch_t;

#define DBPATCH_VERSION(zabbix_version)			zbx_dbpatches_##zabbix_version

#define DBPATCH_START(zabbix_version)			zbx_dbpatch_t	DBPATCH_VERSION(zabbix_version)[] = {
#define DBPATCH_END()					{NULL}};

#ifdef HAVE_SQLITE3

#define DBPATCH_ADD(version, duplicates, mandatory)	{NULL, version, duplicates, mandatory},

#else

#define DBPATCH_ADD(version, duplicates, mandatory)	{DBpatch_##version, version, duplicates, mandatory},

int	DBcreate_table(const ZBX_TABLE *table);
int	DBdrop_table(const char *table_name);
int	DBadd_field(const char *table_name, const ZBX_FIELD *field);
int	DBrename_field(const char *table_name, const char *field_name, const ZBX_FIELD *field);
int	DBmodify_field_type(const char *table_name, const ZBX_FIELD *field, const ZBX_FIELD *old_field);
int	DBset_not_null(const char *table_name, const ZBX_FIELD *field);
int	DBset_default(const char *table_name, const ZBX_FIELD *field);
int	DBdrop_not_null(const char *table_name, const ZBX_FIELD *field);
int	DBdrop_field(const char *table_name, const char *field_name);
int	DBcreate_index(const char *table_name, const char *index_name, const char *fields, int unique);
int	DBdrop_index(const char *table_name, const char *index_name);
int	DBrename_index(const char *table_name, const char *old_name, const char *new_name, const char *fields,
		int unique);
int	DBadd_foreign_key(const char *table_name, int id, const ZBX_FIELD *field);
int	DBdrop_foreign_key(const char *table_name, int id);

#endif

#endif
