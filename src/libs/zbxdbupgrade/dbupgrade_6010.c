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

#include "common.h"
#include "db.h"
#include "dbupgrade.h"

extern unsigned char	program_type;

/*
 * 6.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6010001(void)
{
#define ZBX_MD5_SIZE	32
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update users set passwd='' where length(passwd)=%d", ZBX_MD5_SIZE))
		return FAIL;

	return SUCCEED;
#undef ZBX_MD5_SIZE
}

static int	DBpatch_6010002(void)
{
	const ZBX_TABLE	table =
			{"tplgrp", "groupid", 0,
				{
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6010003(void)
{
	return DBcreate_index("tplgrp", "tplgrp_1", "name", 1);
}

static int	DBpatch_6010004(void)
{
	const ZBX_TABLE	table =
			{"template_group", "templategroupid", 0,
				{
					{"templategroupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6010005(void)
{
	return DBcreate_index("template_group", "templates_groups_1", "hostid,groupid", 1);
}

static int	DBpatch_6010006(void)
{
	return DBcreate_index("template_group", "templates_groups_2", "groupid", 0);
}

static int	DBpatch_6010007(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("template_group", 1, &field);
}

static int	DBpatch_6010008(void)
{
	const ZBX_FIELD	field = {"groupid", NULL, "tplgrp", "groupid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("template_group", 2, &field);
}

static int	DBpatch_6010009(void)
{
	const ZBX_TABLE	table =
			{"right_tplgrp", "rightid", 0,
				{
					{"rightid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", 0, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_60100010(void)
{
	return DBcreate_index("right_tplgrp", "right_tplgrp_1", "groupid", 0);
}

static int	DBpatch_60100011(void)
{
	return DBcreate_index("right_tplgrp", "right_tplgrp_2", "id", 0);
}

static int	DBpatch_60100012(void)
{
	const ZBX_FIELD	field = {"groupid", NULL, "usrgrp", "usrgrpid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("template_group", 1, &field);
}

static int	DBpatch_60100013(void)
{
	const ZBX_FIELD	field = {"groupid", NULL, "tplgrp", "groupid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("template_group", 2, &field);
}


#endif

DBPATCH_START(6010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6010001, 0, 1)
DBPATCH_ADD(6010002, 0, 1)
DBPATCH_ADD(6010003, 0, 1)
DBPATCH_ADD(6010004, 0, 1)
DBPATCH_ADD(6010005, 0, 1)
DBPATCH_ADD(6010006, 0, 1)
DBPATCH_ADD(6010007, 0, 1)
DBPATCH_ADD(6010008, 0, 1)
DBPATCH_ADD(6010009, 0, 1)
DBPATCH_ADD(60100010, 0, 1)
DBPATCH_ADD(60100011, 0, 1)
DBPATCH_ADD(60100012, 0, 1)
DBPATCH_ADD(60100013, 0, 1)

DBPATCH_END()
