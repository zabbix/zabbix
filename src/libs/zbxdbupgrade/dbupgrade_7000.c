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
#include "zbxdbhigh.h"
#include "zbxalgo.h"
#include "zbxnum.h"
#include "dbupgrade_common.h"

/*
 * 7.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7000000(void)
{
	return SUCCEED;
}

static int	DBpatch_7000001(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update profiles"
				" set value_str='operating_mode'"
				" where idx='web.proxies.php.sort'"
				" and value_str like 'status'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000002(void)
{
	if (FAIL == zbx_db_index_exists("auditlog", "auditlog_4"))
		return DBcreate_index("auditlog", "auditlog_4", "recordsetid", 0);

	return SUCCEED;
}

static int	DBpatch_7000003(void)
{
	return DBdrop_index("userdirectory_usrgrp", "userdirectory_usrgrp_3");
}

static int	DBpatch_7000004(void)
{
	if (FAIL == zbx_db_index_exists("items", "items_10"))
		return DBcreate_index("items", "items_10", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000005(void)
{
	if (FAIL == zbx_db_index_exists("hosts", "hosts_9"))
		return DBcreate_index("hosts", "hosts_9", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000006(void)
{
	if (FAIL == zbx_db_index_exists("hstgrp", "hstgrp_2"))
		return DBcreate_index("hstgrp", "hstgrp_2", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000007(void)
{
	if (FAIL == zbx_db_index_exists("httptest", "httptest_5"))
		return DBcreate_index("httptest", "httptest_5", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000008(void)
{
	if (FAIL == zbx_db_index_exists("valuemap", "valuemap_2"))
		return DBcreate_index("valuemap", "valuemap_2", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000009(void)
{
	if (FAIL == zbx_db_index_exists("triggers", "triggers_4"))
		return DBcreate_index("triggers", "triggers_4", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000010(void)
{
	if (FAIL == zbx_db_index_exists("graphs", "graphs_5"))
		return DBcreate_index("graphs", "graphs_5", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000011(void)
{
	if (FAIL == zbx_db_index_exists("services", "services_1"))
		return DBcreate_index("services", "services_1", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000012(void)
{
	if (FAIL == zbx_db_index_exists("dashboard", "dashboard_3"))
		return DBcreate_index("dashboard", "dashboard_3", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7000013(void)
{
	return DBcreate_index("auditlog", "auditlog_5", "ip", 0);
}

static int	DBpatch_7000014(void)
{
	return DBcreate_index("proxy_history", "proxy_history_2", "write_clock", 0);
}

static int	DBpatch_7000015(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 0 - HOST_STATUS_MONITORED */
	/* 1 - HOST_STATUS_NOT_MONITORED */
	if (ZBX_DB_OK > zbx_db_execute(
			"update items"
				" set uuid=''"
				" where hostid in (select hostid from hosts where status in (0,1))"
					" and uuid" ZBX_SQL_STRCMP, ZBX_SQL_STRVAL_NE("")))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000016(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 1 - ZBX_FLAG_DISCOVERY */
	/* 2 - LIFETIME_TYPE_IMMEDIATELY */
	if (ZBX_DB_OK > zbx_db_execute(
			"update items"
				" set enabled_lifetime_type=2"
				" where flags=1"
					" and lifetime_type=2"
					" and enabled_lifetime_type<>2"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000017(void)
{
	int			ret = SUCCEED;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	ids, hgsetids;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_insert_t		db_insert;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_create(&hgsetids);

	/* 3 - HOST_STATUS_TEMPLATE */
	zbx_db_select_uint64("select hostid from hosts"
			" where status=3"
				" and hostid not in (select hostid from host_hgset)", &ids);

	if (0 == ids.values_num)
		goto out;

	ret = permission_hgsets_add(&ids, &hgsetids);

	if (FAIL == ret || 0 == hgsetids.values_num)
		goto out;

	zbx_vector_uint64_sort(&hgsetids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_db_insert_prepare(&db_insert, "permission", "ugsetid", "hgsetid", "permission", (char*)NULL);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "h.hgsetid", hgsetids.values, hgsetids.values_num);

	result = zbx_db_select("select u.ugsetid,h.hgsetid,max(r.permission)"
			" from hgset h"
			" join hgset_group hg"
				" on h.hgsetid=hg.hgsetid"
			" join rights r on hg.groupid=r.id"
			" join ugset_group ug"
				" on r.groupid=ug.usrgrpid"
			" join ugset u"
				" on ug.ugsetid=u.ugsetid"
			" where%s"
			" group by u.ugsetid,h.hgsetid"
			" having min(r.permission)>0"
			" order by u.ugsetid,h.hgsetid", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hgsetid, ugsetid;
		int		permission;

		ZBX_STR2UINT64(ugsetid, row[0]);
		ZBX_STR2UINT64(hgsetid, row[1]);
		permission = atoi(row[2]);

		zbx_db_insert_add_values(&db_insert, ugsetid, hgsetid, permission);
	}
	zbx_db_free_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zbx_vector_uint64_destroy(&hgsetids);
	zbx_vector_uint64_destroy(&ids);

	return ret;
}

static int	DBpatch_7000018(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 2 - SYSMAP_ELEMENT_TYPE_TRIGGER */
	if (ZBX_DB_OK > zbx_db_execute("delete from sysmaps_elements"
			" where elementtype=2"
				" and selementid not in ("
					"select distinct selementid from sysmap_element_trigger"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000019(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute("delete from item_rtdata"
			" where exists ("
				"select null from items i where item_rtdata.itemid=i.itemid and i.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000020(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from widget_field"
			" where name in ('time_size','date_size','tzone_size')"
				" and widgetid in (select widgetid from widget where type='clock')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000021(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptestitem"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000022(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstepitem"
			" where httpstepid in ("
				"select hts.httpstepid"
				" from hosts h,httptest ht,httpstep hts"
				" where h.hostid=ht.hostid"
					" and ht.httptestid=hts.httptestid and h.flags=2"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000023(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from item_tag"
			" where itemid in ("
				"select i.itemid from hosts h,items i"
				" where h.hostid=i.hostid and h.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000024(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from items"
			" where exists ("
				"select null from hosts h"
				" where h.hostid=items.hostid and h.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000025(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstep_field"
			" where httpstepid in ("
				"select hts.httpstepid"
				" from hosts h,httptest ht,httpstep hts"
				" where h.hostid=ht.hostid"
					" and ht.httptestid=hts.httptestid and h.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000026(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstep"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000027(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptest_field"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000028(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptest"
			" where exists ("
				"select null from hosts h"
				" where h.hostid=httptest.hostid and h.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7000029(void)
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

#endif

DBPATCH_START(7000)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7000000, 0, 1)
DBPATCH_ADD(7000001, 0, 0)
DBPATCH_ADD(7000002, 0, 0)
DBPATCH_ADD(7000003, 0, 0)
DBPATCH_ADD(7000004, 0, 0)
DBPATCH_ADD(7000005, 0, 0)
DBPATCH_ADD(7000006, 0, 0)
DBPATCH_ADD(7000007, 0, 0)
DBPATCH_ADD(7000008, 0, 0)
DBPATCH_ADD(7000009, 0, 0)
DBPATCH_ADD(7000010, 0, 0)
DBPATCH_ADD(7000011, 0, 0)
DBPATCH_ADD(7000012, 0, 0)
DBPATCH_ADD(7000013, 0, 0)
DBPATCH_ADD(7000014, 0, 0)
DBPATCH_ADD(7000015, 0, 0)
DBPATCH_ADD(7000016, 0, 0)
DBPATCH_ADD(7000017, 0, 0)
DBPATCH_ADD(7000018, 0, 0)
DBPATCH_ADD(7000019, 0, 0)
DBPATCH_ADD(7000020, 0, 0)
DBPATCH_ADD(7000021, 0, 0)
DBPATCH_ADD(7000022, 0, 0)
DBPATCH_ADD(7000023, 0, 0)
DBPATCH_ADD(7000024, 0, 0)
DBPATCH_ADD(7000025, 0, 0)
DBPATCH_ADD(7000026, 0, 0)
DBPATCH_ADD(7000027, 0, 0)
DBPATCH_ADD(7000028, 0, 0)
DBPATCH_ADD(7000029, 0, 0)

DBPATCH_END()
