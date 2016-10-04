/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

/*
 * 3.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3030000(void)
{
	const ZBX_FIELD field = {"snmp_oid", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030001(void)
{
	const ZBX_FIELD field = {"key_", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("dchecks", &field);
}

static int	DBpatch_3030002(void)
{
	const ZBX_FIELD field = {"key_", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("proxy_dhistory", &field);
}

static int	DBpatch_3030003(void)
{
	return DBdrop_foreign_key("dservices", 1);
}

static int	DBpatch_3030004(void)
{
	return DBdrop_foreign_key("dservices", 2);
}

static int	DBpatch_3030005(void)
{
	return DBdrop_index("dservices", "dservices_1");
}

static int	DBpatch_3030006(void)
{
	return DBdrop_field("dservices", "type");
}

static int	DBpatch_3030007(void)
{
	return DBdrop_field("dservices", "key_");
}

static int	DBpatch_3030008(void)
{
	if (ZBX_DB_OK > DBexecute("delete from dservices"
				" where dserviceid not in"
					" (select max(dserviceid) as dserviceid from"
						" dservices group by dchecksid, ip, port)"))
		{
			return FAIL;
		}

		return SUCCEED;
}

static int	DBpatch_3030009(void)
{
	return DBcreate_index("dservices", "dservices_1", "dcheckid,ip,port", 1);
}

static int	DBpatch_3030010(void)
{
	const ZBX_FIELD	field = {"dhostid", NULL, "dhosts", "dhostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dservices", 1, &field);
}

static int	DBpatch_3030011(void)
{
	const ZBX_FIELD	field = {"dcheckid", NULL, "dchecks", "dcheckid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dservices", 2, &field);
}

#endif

DBPATCH_START(3030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3030000, 0, 1)
DBPATCH_ADD(3030001, 0, 1)
DBPATCH_ADD(3030002, 0, 1)
DBPATCH_ADD(3030003, 0, 1)
DBPATCH_ADD(3030004, 0, 1)
DBPATCH_ADD(3030005, 0, 1)
DBPATCH_ADD(3030006, 0, 1)
DBPATCH_ADD(3030007, 0, 1)
DBPATCH_ADD(3030008, 0, 1)
DBPATCH_ADD(3030009, 0, 1)
DBPATCH_ADD(3030010, 0, 1)
DBPATCH_ADD(3030011, 0, 1)

DBPATCH_END()
