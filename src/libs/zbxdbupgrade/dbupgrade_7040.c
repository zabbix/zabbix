/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxdb.h"

/*
 * 7.4 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7040000(void)
{
	return SUCCEED;
}

static int	DBpatch_7040001(void)
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

static int	DBpatch_7040002(void)
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

static int	DBpatch_7040003(void)
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

static int	DBpatch_7040004(void)
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

static int	DBpatch_7040005(void)
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

static int	DBpatch_7040006(void)
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

static int	DBpatch_7040007(void)
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

static int	DBpatch_7040008(void)
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

static int	DBpatch_7040009(void)
{
	/* 3 - HOST_STATUS_TEMPLATE */
	if (ZBX_DB_OK > zbx_db_execute("delete from item_rtdata"
			" where exists ("
				"select null from items i,hosts h"
				" where item_rtdata.itemid=i.itemid"
					" and i.hostid=h.hostid and h.status=3"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7040010(void)
{
	int			ret = SUCCEED;
	zbx_db_insert_t		db_insert;
	zbx_db_row_t		row;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_result_t	result = zbx_db_select("select i.itemid,t.tag,t.value from httptest_tag t"
		" join ("
			"select hsi.itemid,hs.httptestid from httpstepitem hsi"
			" join httpstep hs on hs.httpstepid=hsi.httpstepid"
			" union"
			" select hti.itemid,hti.httptestid from httptestitem hti"
		") as i"
		" on i.httptestid=t.httptestid"
		" where not exists ("
			"select null from item_tag it"
			" where it.itemid=i.itemid and it.tag=t.tag"
		")");

	if (NULL == result)
		return FAIL;

	zbx_db_insert_prepare(&db_insert, "item_tag", "itemtagid", "itemid", "tag", "value", (char *)NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	itemid;

		ZBX_DBROW2UINT64(itemid, row[0]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), itemid, row[1], row[2]);
	}

	zbx_db_insert_autoincrement(&db_insert, "itemtagid");
	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);

	zbx_db_free_result(result);

	return ret;
}

#endif

DBPATCH_START(7040)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7040000, 0, 1)
DBPATCH_ADD(7040001, 0, 0)
DBPATCH_ADD(7040002, 0, 0)
DBPATCH_ADD(7040003, 0, 0)
DBPATCH_ADD(7040004, 0, 0)
DBPATCH_ADD(7040005, 0, 0)
DBPATCH_ADD(7040006, 0, 0)
DBPATCH_ADD(7040007, 0, 0)
DBPATCH_ADD(7040008, 0, 0)
DBPATCH_ADD(7040009, 0, 0)
DBPATCH_ADD(7040010, 0, 0)

DBPATCH_END()
