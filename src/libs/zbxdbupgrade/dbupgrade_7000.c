/*
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxdbhigh.h"

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

DBPATCH_END()
