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
#include "zbxdb.h"
#include "zbxdbschema.h"

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
#define ZBX_COLORPALETTE_LIGHT	"1A7C11,F63100,2774A4,A54F10,FC6EA3,6C59DC,AC8C14,611F27,F230E0,5CCD18,BB2A02,"	\
				"5A2B57,89ABF8,7EC25C,274482,2B5429,8048B4,FD5434,790E1F,87AC4D,E89DF4"

	if (0 == (ZBX_PROGRAM_TYPE_SERVER & DBget_program_type()))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute(
			"insert into graph_theme"
			" values (5,'blue-classic-theme','FFFFFF','FFFFFF','CCD5D9','ACBBC2','ACBBC2','1F2C33',"
				"'E33734','429E47','E33734','EBEBEB','" ZBX_COLORPALETTE_LIGHT "')"))
	{
		return SUCCEED;
	}
#undef ZBX_COLORPALETTE_LIGHT

	return FAIL;
}

static int	DBpatch_7050008(void)
{
#define ZBX_COLORPALETTE_DARK	"199C0D,F63100,2774A4,F7941D,FC6EA3,6C59DC,C7A72D,BA2A5D,F230E0,5CCD18,BB2A02,"	\
				"AC41A5,89ABF8,7EC25C,3165D5,79A277,AA73DE,FD5434,F21C3E,87AC4D,E89DF4"

	if (0 == (ZBX_PROGRAM_TYPE_SERVER & DBget_program_type()))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute(
			"insert into graph_theme"
			" values (6,'dark-classic-theme','2B2B2B','2B2B2B','454545','4F4F4F','4F4F4F','F2F2F2',"
				"'E45959','59DB8F','E45959','333333','" ZBX_COLORPALETTE_DARK "')"))
	{
		return SUCCEED;
	}
#undef ZBX_COLORPALETTE_DARK

	return FAIL;
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

DBPATCH_END()
