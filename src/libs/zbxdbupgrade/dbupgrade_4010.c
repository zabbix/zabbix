/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

#include "common.h"
#include "db.h"
#include "dbupgrade.h"
#include "log.h"

extern unsigned char	program_type;

/*
 * 4.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_4010001(void)
{
	const ZBX_FIELD	field = {"content_type", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4010002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update media_type set content_type=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010003(void)
{
	const ZBX_FIELD	field = {"error_handler", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("item_preproc", &field);
}

static int	DBpatch_4010004(void)
{
	const ZBX_FIELD	field = {"error_handler_params", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("item_preproc", &field);
}

static int	DBpatch_4010005(void)
{
	const ZBX_TABLE table =
			{"lld_macro_path", "lld_macro_pathid", 0,
				{
					{"lld_macro_pathid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"lld_macro", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"path", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_4010006(void)
{
	return DBcreate_index("lld_macro_path", "lld_macro_path_1", "itemid,lld_macro", 1);
}

static int	DBpatch_4010007(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_macro_path", 1, &field);
}

static int	DBpatch_4010008(void)
{
	const ZBX_FIELD	field = {"host_source", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_4010009(void)
{
	const ZBX_FIELD	field = {"name_source", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_4010010(void)
{
	return DBdrop_foreign_key("dchecks", 1);
}

static int	DBpatch_4010011(void)
{
	return DBdrop_index("dchecks", "dchecks_1");
}

static int	DBpatch_4010012(void)
{
	const ZBX_FIELD	field = {"druleid", NULL, "drules", "druleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dchecks", 1, &field);
}

static int	DBpatch_4010013(void)
{
	return DBcreate_index("proxy_dhistory", "proxy_dhistory_2", "druleid", 0);
}

#endif

DBPATCH_START(4010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4010001, 0, 1)
DBPATCH_ADD(4010002, 0, 1)
DBPATCH_ADD(4010003, 0, 1)
DBPATCH_ADD(4010004, 0, 1)
DBPATCH_ADD(4010005, 0, 1)
DBPATCH_ADD(4010006, 0, 1)
DBPATCH_ADD(4010007, 0, 1)
DBPATCH_ADD(4010008, 0, 1)
DBPATCH_ADD(4010009, 0, 1)
DBPATCH_ADD(4010010, 0, 1)
DBPATCH_ADD(4010011, 0, 1)
DBPATCH_ADD(4010012, 0, 1)
DBPATCH_ADD(4010013, 0, 1)

DBPATCH_END()
