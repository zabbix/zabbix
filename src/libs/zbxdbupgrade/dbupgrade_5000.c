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
#include "db.h"
#include "dbupgrade.h"
#include "dbupgrade_macros.h"

extern unsigned char	program_type;

/*
 * 5.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_5000000(void)
{
	return SUCCEED;
}

static int	DBpatch_5000001(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ('web.latest.toggle','web.latest.toggle_other')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5000002(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select userid from profiles where idx='web.latest.sort' and value_str='lastclock'");

	while (NULL != (row = DBfetch(result)))
	{
		if (ZBX_DB_OK > DBexecute(
			"delete from profiles"
			" where userid='%s'"
				" and idx in ('web.latest.sort','web.latest.sortorder')", row[0]))
		{
			ret = FAIL;
			break;
		}
	}
	DBfree_result(result);

	return ret;
}

static int	DBpatch_5000003(void)
{
	DB_RESULT		result;
	int			ret;
	zbx_field_len_t		fields[] = {
			{"subject", 255},
			{"message", 65535}
	};

	result = DBselect("select om.operationid,om.subject,om.message"
			" from opmessage om,operations o,actions a"
			" where om.operationid=o.operationid"
				" and o.actionid=a.actionid"
				" and a.eventsource=0 and o.operationtype=11");

	ret = db_rename_macro(result, "opmessage", "operationid", fields, ARRSIZE(fields), "{EVENT.NAME}",
			"{EVENT.RECOVERY.NAME}");

	DBfree_result(result);

	return ret;
}

static int	DBpatch_5000004(void)
{
	DB_RESULT		result;
	int			ret;
	zbx_field_len_t		fields[] = {
			{"subject", 255},
			{"message", 65535}
	};

	result = DBselect("select mediatype_messageid,subject,message from media_type_message where recovery=1");

	ret = db_rename_macro(result, "media_type_message", "mediatype_messageid", fields, ARRSIZE(fields),
			"{EVENT.NAME}", "{EVENT.RECOVERY.NAME}");

	DBfree_result(result);

	return ret;
}

static int	DBpatch_5000005(void)
{
	return DBcreate_index("alerts", "alerts_8", "acknowledgeid", 0);
}

static int	DBpatch_5000006(void)
{
	const ZBX_FIELD	field = {"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("group_discovery", &field, NULL);
}

static int	DBpatch_5000007(void)
{
#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
#	define ZBX_DB_CHAR_LENGTH(str)	"char_length(" #str ")"
#else /* HAVE_ORACLE */
#	define ZBX_DB_CHAR_LENGTH(str)	"length(" #str ")"
#endif
	if (ZBX_DB_OK > DBexecute(
			"update group_discovery gd"
			" set name=("
				"select gp.name"
				" from group_prototype gp"
				" where gd.parent_group_prototypeid=gp.group_prototypeid"
			")"
			" where " ZBX_DB_CHAR_LENGTH(gd.name) "=64"))
	{
		return FAIL;
	}

	return SUCCEED;
#undef ZBX_DB_CHAR_LENGTH
}

#endif

DBPATCH_START(5000)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5000000, 0, 1)
DBPATCH_ADD(5000001, 0, 0)
DBPATCH_ADD(5000002, 0, 0)
DBPATCH_ADD(5000003, 0, 0)
DBPATCH_ADD(5000004, 0, 0)
DBPATCH_ADD(5000005, 0, 0)
DBPATCH_ADD(5000006, 0, 0)
DBPATCH_ADD(5000007, 0, 0)

DBPATCH_END()
