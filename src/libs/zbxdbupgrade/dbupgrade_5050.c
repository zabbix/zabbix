/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * 6.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_5050000(void)
{
	const ZBX_TABLE table =
		{"service_tag", "servicetagid", 0,
			{
				{"servicetagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050001(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_tag", 1, &field);
}

static int	DBpatch_5050002(void)
{
	return DBdrop_field("services_links", "soft");
}

static int	DBpatch_5050003(void)
{
	if (ZBX_DB_OK > DBexecute("update role_rule set name='actions.manage_services' where name='ui.default_access'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#endif

DBPATCH_START(5050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5050000, 0, 1)
DBPATCH_ADD(5050001, 0, 1)
DBPATCH_ADD(5050002, 0, 1)
DBPATCH_ADD(5050003, 0, 1)

DBPATCH_END()
