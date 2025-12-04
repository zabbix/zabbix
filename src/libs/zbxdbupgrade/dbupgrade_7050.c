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

#include "dbupgrade.h"

#include "dbupgrade_common.h"

#include "zbxdbschema.h"
#include "zbxdb.h"

/*
 * 8.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7050000(void)
{
	const zbx_db_field_t	field = {"idp_certificate", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_saml", &field);
}

static int	DBpatch_7050001(void)
{
	const zbx_db_field_t	field = {"sp_certificate", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_saml", &field);
}

static int	DBpatch_7050002(void)
{
	const zbx_db_field_t	field = {"sp_private_key", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_saml", &field);
}

static int	DBpatch_7050003(void)
{
	return DBdrop_foreign_key("event_recovery", 2);
}

static int	DBpatch_7050004(void)
{
	const zbx_db_field_t	field = {"r_eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBadd_foreign_key("event_recovery", 2, &field);
}

static int	DBpatch_7050005(void)
{
	return DBdrop_foreign_key("problem", 2);
}

static int	DBpatch_7050006(void)
{
	const zbx_db_field_t	field = {"r_eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBadd_foreign_key("problem", 2, &field);
}

static int	DBpatch_7050007(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
		" (" ZBX_FS_UI64 ",'scatterplot','widgets/scatterplot',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050008(void)
{
	int		i;
	const char	*values[] = {
			"web.hosts.host_prototypes.php.sort", "web.hosts.host.prototype.list.sort",
			"web.hosts.host_prototypes.php.sortorder", "web.hosts.host.prototype.list.sortorder",
			"web.templates.host_prototypes.php.sort", "web.templates.host.prototype.list.sort",
			"web.templates.host_prototypes.php.sortorder", "web.templates.host.prototype.list.sortorder"
		};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050009(void)
{
	const zbx_db_table_t	table =
			{"host_template_cache", "hostid, link_hostid", 0,
				{
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"link_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050010(void)
{
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_template_cache", 1, &field);
}

static int	DBpatch_7050011(void)
{
	const zbx_db_field_t	field = {"link_hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_template_cache", 2, &field);
}

static int	DBpatch_7050012(void)
{
	return DBcreate_index("host_template_cache", "host_template_cache_1", "link_hostid", 0);
}

static int	DBpatch_7050013(void)
{
	if (ZBX_DB_OK > zbx_db_execute(
			"insert into host_template_cache ("
			"	with recursive cte as ("
					"select h0.templateid,h0.hostid from hosts_templates h0"
					" union all "
					"select h1.templateid,c.hostid from cte c"
					" join hosts_templates h1 on c.templateid=h1.hostid"
				")"
				" select hostid,templateid from cte"
			")"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > zbx_db_execute("insert into host_template_cache (select hostid,hostid from hosts)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_7050014(void)
{
	const zbx_db_table_t	table =
			{"item_template_cache", "itemid, link_hostid", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"link_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050015(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_template_cache", 1, &field);
}

static int	DBpatch_7050016(void)
{
	const zbx_db_field_t	field = {"link_hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_template_cache", 2, &field);
}

static int	DBpatch_7050017(void)
{
	return DBcreate_index("item_template_cache", "item_template_cache_1", "link_hostid", 0);
}

static int	DBpatch_7050018(void)
{
	/* 0 - ZBX_FLAG_DISCOVERY_NORMAL */
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 4 - ZBX_FLAG_DISCOVERY_CREATED */

	if (ZBX_DB_OK > zbx_db_execute(
			"insert into item_template_cache ("
				"with recursive cte as ("
					"select i0.templateid,i0.itemid from items i0"
					" where i0.flags in (0,2,4)"
					" union all "
					"select i1.templateid,c.itemid from cte c"
					" join items i1 on c.templateid=i1.itemid"
					" where i1.templateid is not null"
				")"
				" select cte.itemid,h.hostid from cte,hosts h,items i"
				" where cte.templateid=i.itemid and i.hostid=h.hostid"
			")"))
	{
		return FAIL;
	}


	if (ZBX_DB_OK > zbx_db_execute(
			"insert into item_template_cache ("
				"select i.itemid,h.hostid from items i,hosts h"
				" where i.hostid=h.hostid and i.flags in (0,2,4)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050019(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptestitem"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050020(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstepitem"
			" where httpstepid in ("
				"select hts.httpstepid"
				" from hosts h,httptest ht,httpstep hts"
				" where h.hostid=ht.hostid"
					" and ht.httptestid=hts.httptestid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050021(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from item_tag"
			" where itemid in ("
				"select i.itemid from hosts h,items i"
				" where h.hostid=i.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050022(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from items"
			" where exists ("
				"select null from hosts h"
				" where h.hostid=items.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050023(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstep_field"
			" where httpstepid in ("
				"select hts.httpstepid"
				" from hosts h,httptest ht,httpstep hts"
				" where h.hostid=ht.hostid"
					" and ht.httptestid=hts.httptestid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050024(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstep"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050025(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptest_field"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050026(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptest"
			" where exists ("
				"select null from hosts h"
				" where h.hostid=httptest.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#endif

DBPATCH_START(7050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7050000, 0, 1)
DBPATCH_ADD(7050001, 0, 1)
DBPATCH_ADD(7050002, 0, 1)
DBPATCH_ADD(7050003, 0, 1)
DBPATCH_ADD(7050004, 0, 1)
DBPATCH_ADD(7050005, 0, 1)
DBPATCH_ADD(7050006, 0, 1)
DBPATCH_ADD(7050007, 0, 1)
DBPATCH_ADD(7050008, 0, 1)
DBPATCH_ADD(7050009, 0, 1)
DBPATCH_ADD(7050010, 0, 1)
DBPATCH_ADD(7050011, 0, 1)
DBPATCH_ADD(7050012, 0, 1)
DBPATCH_ADD(7050013, 0, 1)
DBPATCH_ADD(7050014, 0, 1)
DBPATCH_ADD(7050015, 0, 1)
DBPATCH_ADD(7050016, 0, 1)
DBPATCH_ADD(7050017, 0, 1)
DBPATCH_ADD(7050018, 0, 1)
DBPATCH_ADD(7050019, 0, 1)
DBPATCH_ADD(7050020, 0, 1)
DBPATCH_ADD(7050021, 0, 1)
DBPATCH_ADD(7050022, 0, 1)
DBPATCH_ADD(7050023, 0, 1)
DBPATCH_ADD(7050024, 0, 1)
DBPATCH_ADD(7050025, 0, 1)
DBPATCH_ADD(7050026, 0, 1)

DBPATCH_END()
