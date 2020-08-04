/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "log.h"

/*
 * 5.2 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5010000(void)
{
	const ZBX_FIELD	field = {"default_lang", "en_GB", NULL, NULL, 5, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010001(void)
{
	const ZBX_FIELD	field = {"lang", "default", NULL, NULL, 7, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field, NULL);
}

static int	DBpatch_5010002(void)
{
	if (ZBX_DB_OK > DBexecute("update users set lang='default',theme='default' where alias='guest'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5010003(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ('web.latest.toggle','web.latest.toggle_other')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5010004(void)
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

static int	DBpatch_5010005(void)
{
	const ZBX_FIELD	field = {"default_timezone", "system", NULL, NULL, 50, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010006(void)
{
	const ZBX_FIELD	field = {"timezone", "default", NULL, NULL, 50, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_5010007(void)
{
	const ZBX_FIELD	field = {"session_key", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

#endif

DBPATCH_START(5010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5010000, 0, 1)
DBPATCH_ADD(5010001, 0, 1)
DBPATCH_ADD(5010002, 0, 1)
DBPATCH_ADD(5010003, 0, 1)
DBPATCH_ADD(5010004, 0, 1)
DBPATCH_ADD(5010005, 0, 1)
DBPATCH_ADD(5010006, 0, 1)
DBPATCH_ADD(5010007, 0, 1)

DBPATCH_END()
