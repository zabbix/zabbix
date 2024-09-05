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
	return DBcreate_index("auditlog", "auditlog_5", "ip", 0);
}

#endif

DBPATCH_START(7000)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7000000, 0, 1)
DBPATCH_ADD(7000001, 0, 0)
DBPATCH_ADD(7000002, 0, 0)
DBPATCH_ADD(7000003, 0, 0)
DBPATCH_ADD(7000004, 0, 0)

DBPATCH_END()
