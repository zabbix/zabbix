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
	DB_ROW			row;
	DB_RESULT		result;
	zbx_uint64_t		dserviceid;

	/* After dropping fields type and key_ from table dservices there is no guarantee that a unique
	index with fields dcheckid, ip and port can be created. To create a unique index for the same
	fields later this will delete rows where all three of them are identical only leaving the latest. */
	result = DBselect("select dserviceid from dservices"
			" where dserviceid not in (select max(dserviceid) from dservices group by dcheckid, ip, port)");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(dserviceid, row[0]);

		if (ZBX_DB_OK > DBexecute("delete from dservices where dserviceid=" ZBX_FS_UI64, dserviceid))
		{
			return FAIL;
		}
	}
	DBfree_result(result);

	return SUCCEED;
}

static int	DBpatch_3030004(void)
{
	return DBdrop_field("dservices", "type");
}

static int	DBpatch_3030005(void)
{
	return DBdrop_field("dservices", "key_");
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

DBPATCH_END()
