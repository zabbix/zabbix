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
 * 3.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3030000(void)
{
	const ZBX_FIELD	field = {"ipmi_authtype", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("hosts", &field);
}

static int	DBpatch_3030001(void)
{
	const ZBX_FIELD field = {"snmp_oid", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030002(void)
{
	const ZBX_FIELD field = {"key_", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("dchecks", &field);
}

static int	DBpatch_3030003(void)
{
	return DBdrop_field("proxy_dhistory", "type");
}

static int	DBpatch_3030004(void)
{
	return DBdrop_field("proxy_dhistory", "key_");
}

static int	DBpatch_3030005(void)
{
	return DBdrop_foreign_key("dservices", 2);
}

static int	DBpatch_3030006(void)
{
	return DBdrop_index("dservices", "dservices_1");
}

static int	DBpatch_3030007(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_vector_uint64_t	dserviceids;
	zbx_uint64_t		dserviceid;
	int			ret = SUCCEED;

	zbx_vector_uint64_create(&dserviceids);

	/* After dropping fields type and key_ from table dservices there is no guarantee that a unique
	index with fields dcheckid, ip and port can be created. To create a unique index for the same
	fields later this will delete rows where all three of them are identical only leaving the latest. */
	result = DBselect(
			"select ds.dserviceid"
			" from dservices ds"
			" where not exists ("
				"select null"
				" from dchecks dc"
				" where ds.dcheckid = dc.dcheckid"
					" and ds.type = dc.type"
					" and ds.key_ = dc.key_"
			")");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(dserviceid, row[0]);

		zbx_vector_uint64_append(&dserviceids, dserviceid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&dserviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != dserviceids.values_num)
		ret = DBexecute_multiple_query("delete from dservices where", "dserviceid", &dserviceids);

	zbx_vector_uint64_destroy(&dserviceids);

	return ret;
}

static int	DBpatch_3030008(void)
{
	return DBdrop_field("dservices", "type");
}

static int	DBpatch_3030009(void)
{
	return DBdrop_field("dservices", "key_");
}

static int	DBpatch_3030010(void)
{
	return DBcreate_index("dservices", "dservices_1", "dcheckid,ip,port", 1);
}

static int	DBpatch_3030011(void)
{
	const ZBX_FIELD	field = {"dcheckid", NULL, "dchecks", "dcheckid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dservices", 2, &field);
}

static int	DBpatch_3030012(void)
{
	const ZBX_FIELD field = {"snmp_lastsize", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("globalvars", &field);
}

static int	DBpatch_3030013(void)
{
	const ZBX_TABLE table =
			{"item_preproc", "item_preprocid", 0,
				{
					{"item_preprocid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"step", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"params", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030014(void)
{
	return DBcreate_index("item_preproc", "item_preproc_1", "itemid, step", 0);
}

static int	DBpatch_3030015(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_preproc", 1, &field);
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
DBPATCH_ADD(3030012, 0, 1)
DBPATCH_ADD(3030013, 0, 1)
DBPATCH_ADD(3030014, 0, 1)
DBPATCH_ADD(3030015, 0, 1)

DBPATCH_END()
