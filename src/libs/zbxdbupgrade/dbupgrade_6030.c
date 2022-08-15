/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "zbxdbhigh.h"
#include "dbupgrade.h"

extern unsigned char	program_type;

/*
 * 6.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6030000(void)
{
	return DBcreate_changelog_insert_trigger("drules", "druleid");
}

static int	DBpatch_6030001(void)
{
	return DBcreate_changelog_update_trigger("drules", "druleid");
}

static int	DBpatch_6030002(void)
{
	return DBcreate_changelog_delete_trigger("drules", "druleid");
}

static int	DBpatch_6030003(void)
{
	return DBcreate_changelog_insert_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030004(void)
{
	return DBcreate_changelog_update_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030005(void)
{
	return DBcreate_changelog_delete_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030006(void)
{
	return DBcreate_changelog_insert_trigger("httptest", "httptestid");
}

static int	DBpatch_6030007(void)
{
	return DBcreate_changelog_update_trigger("httptest", "httptestid");
}

static int	DBpatch_6030008(void)
{
	return DBcreate_changelog_delete_trigger("httptest", "httptestid");
}

static int	DBpatch_6030009(void)
{
	return DBcreate_changelog_insert_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030010(void)
{
	return DBcreate_changelog_update_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030011(void)
{
	return DBcreate_changelog_delete_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030012(void)
{
	return DBcreate_changelog_insert_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030013(void)
{
	return DBcreate_changelog_update_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030014(void)
{
	return DBcreate_changelog_delete_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030015(void)
{
	return DBcreate_changelog_insert_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030016(void)
{
	return DBcreate_changelog_update_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030017(void)
{
	return DBcreate_changelog_delete_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030018(void)
{
	return DBcreate_changelog_insert_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030019(void)
{
	return DBcreate_changelog_update_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030020(void)
{
	return DBcreate_changelog_delete_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030021(void)
{
	return DBcreate_changelog_insert_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030022(void)
{
	return DBcreate_changelog_update_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030023(void)
{
	return DBcreate_changelog_delete_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030024(void)
{
	return DBdrop_field("drules", "nextcheck");
}

static int	DBpatch_6030025(void)
{
	return DBdrop_field("httptest", "nextcheck");
}

static int	DBpatch_6030026(void)
{
	const ZBX_FIELD field = {"discovery_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("config", &field);
}

#endif

DBPATCH_START(6030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6030000, 0, 1)
DBPATCH_ADD(6030001, 0, 1)
DBPATCH_ADD(6030002, 0, 1)
DBPATCH_ADD(6030003, 0, 1)
DBPATCH_ADD(6030004, 0, 1)
DBPATCH_ADD(6030005, 0, 1)
DBPATCH_ADD(6030006, 0, 1)
DBPATCH_ADD(6030007, 0, 1)
DBPATCH_ADD(6030008, 0, 1)
DBPATCH_ADD(6030009, 0, 1)
DBPATCH_ADD(6030010, 0, 1)
DBPATCH_ADD(6030011, 0, 1)
DBPATCH_ADD(6030012, 0, 1)
DBPATCH_ADD(6030013, 0, 1)
DBPATCH_ADD(6030014, 0, 1)
DBPATCH_ADD(6030015, 0, 1)
DBPATCH_ADD(6030016, 0, 1)
DBPATCH_ADD(6030017, 0, 1)
DBPATCH_ADD(6030018, 0, 1)
DBPATCH_ADD(6030019, 0, 1)
DBPATCH_ADD(6030020, 0, 1)
DBPATCH_ADD(6030021, 0, 1)
DBPATCH_ADD(6030022, 0, 1)
DBPATCH_ADD(6030023, 0, 1)
DBPATCH_ADD(6030024, 0, 1)
DBPATCH_ADD(6030025, 0, 1)
DBPATCH_ADD(6030026, 0, 1)

DBPATCH_END()
