/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_DBSCHEMA_H
#define ZABBIX_DBSCHEMA_H

/* Flags */
#define	ZBX_SYNC		0x01
#define ZBX_NOTNULL		0x02
#define ZBX_HISTORY		0x04
#define ZBX_HISTORY_SYNC	0x08
#define ZBX_HISTORY_TRENDS	0x10
#define ZBX_PROXY		0x20

/* FK Flags */
#define ZBX_FK_CASCADE_DELETE	0x01

/* Field types */
#define	ZBX_TYPE_INT		0
#define	ZBX_TYPE_CHAR		1
#define	ZBX_TYPE_FLOAT		2
#define	ZBX_TYPE_BLOB		3
#define	ZBX_TYPE_TEXT		4
#define	ZBX_TYPE_UINT		5
#define	ZBX_TYPE_ID		6

#define ZBX_MAX_FIELDS		64
#define ZBX_TABLENAME_LEN	64
#define ZBX_TABLENAME_LEN_MAX	ZBX_TABLENAME_LEN + 1
#define ZBX_FIELDNAME_LEN	64
#define ZBX_FIELDNAME_LEN_MAX	ZBX_FIELDNAME_LEN + 1

typedef struct
{
	const char    	*name;
	unsigned char	type;
	unsigned char	flags;
	const char	*fk_table;
	const char	*fk_field;
	unsigned char	fk_flags;
}
ZBX_FIELD;

typedef struct
{
	const char    	*table;
	const char	*recid;
	unsigned char	flags;
	ZBX_FIELD	fields[ZBX_MAX_FIELDS];
	const char	*uniq;
}
ZBX_TABLE;

extern const ZBX_TABLE	tables[];
extern const char	*const db_schema;
extern const char	*const db_schema_fkeys[];
extern const char	*const db_schema_fkeys_drop[];

#endif
