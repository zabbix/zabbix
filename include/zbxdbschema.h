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

#ifndef ZABBIX_DBSCHEMA_H
#define ZABBIX_DBSCHEMA_H

/* flags */
#define ZBX_NOTNULL		0x01
#define ZBX_PROXY		0x02
#define ZBX_UPPER		0x04

/* FK flags */
#define ZBX_FK_CASCADE_DELETE	0x01

/* field types */
#define	ZBX_TYPE_INT		0
#define	ZBX_TYPE_CHAR		1
#define	ZBX_TYPE_FLOAT		2
#define	ZBX_TYPE_BLOB		3
#define	ZBX_TYPE_TEXT		4
#define	ZBX_TYPE_UINT		5
#define	ZBX_TYPE_ID		6
/* ZBX_TYPE_SHORTTEXT 7 */
#define	ZBX_TYPE_LONGTEXT	8
#define ZBX_TYPE_CUID		9
#define	ZBX_TYPE_SERIAL		10

#define ZBX_MAX_FIELDS		119 /* maximum number of fields in a table plus one for null terminator in dbschema.c */
#define ZBX_TABLENAME_LEN	26
#define ZBX_TABLENAME_LEN_MAX	(ZBX_TABLENAME_LEN + 1)
#define ZBX_FIELDNAME_LEN	28
#define ZBX_FIELDNAME_LEN_MAX	(ZBX_FIELDNAME_LEN + 1)

typedef struct
{
	const char	*name;
	const char	*default_value;
	const char	*fk_table;
	const char	*fk_field;
	unsigned short	length;
	unsigned char	type;
	unsigned char	flags;
	unsigned char	fk_flags;
}
zbx_db_field_t;

typedef struct
{
	const char	*table;
	const char	*recid;
	unsigned char	flags;
	zbx_db_field_t	fields[ZBX_MAX_FIELDS];
	const char	*uniq;
}
zbx_db_table_t;

typedef struct
{
	const char	*table;
	int		object;
}
zbx_db_table_changelog_t;

zbx_db_table_t			*zbx_dbschema_get_tables(void);
const zbx_db_table_changelog_t	*zbx_dbschema_get_changelog_tables(void);
const char			*zbx_dbschema_get_schema(void);

#endif
