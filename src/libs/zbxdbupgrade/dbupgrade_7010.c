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
#include "zbxdb.h"

/*
 * 7.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7010000(void)
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

static int	DBpatch_7010001(void)
{
	if (FAIL == zbx_db_index_exists("auditlog", "auditlog_4"))
		return DBcreate_index("auditlog", "auditlog_4", "recordsetid", 0);

	return SUCCEED;
}

static int	DBpatch_7010002(void)
{
	int		i;
	const char	*values[] = {
			"web.avail_report.filter.active", "web.availabilityreport.filter.active",
			"web.avail_report.filter.from", "web.availabilityreport.filter.from",
			"web.avail_report.filter.to", "web.availabilityreport.filter.to",
			"web.avail_report.mode", "web.availabilityreport.filter.mode",
			"web.avail_report.0.groupids", "web.availabilityreport.filter.0.host_groups",
			"web.avail_report.0.hostids", "web.availabilityreport.filter.0.hosts",
			"web.avail_report.1.groupid", "web.availabilityreport.filter.1.template_groups",
			"web.avail_report.1.hostid", "web.availabilityreport.filter.1.templates",
			"web.avail_report.1.tpl_triggerid", "web.availabilityreport.filter.1.triggers",
			"web.avail_report.1.hostgroupid", "web.availabilityreport.filter.1.host_groups"
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

static int	DBpatch_7010003(void)
{
	if (SUCCEED == zbx_db_index_exists("userdirectory_usrgrp", "userdirectory_usrgrp_3"))
		return DBdrop_index("userdirectory_usrgrp", "userdirectory_usrgrp_3");

	return SUCCEED;
}

static int	DBpatch_7010004(void)
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

#endif

DBPATCH_START(7010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7010000, 0, 1)
DBPATCH_ADD(7010001, 0, 1)
DBPATCH_ADD(7010002, 0, 1)
DBPATCH_ADD(7010003, 0, 1)
DBPATCH_ADD(7010004, 0, 1)

DBPATCH_END()
