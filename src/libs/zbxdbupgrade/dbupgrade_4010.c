/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

extern unsigned char	program_type;

/*
 * 4.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_4010001(void)
{
	const ZBX_FIELD	field = {"content_type", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4010002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update media_type set content_type=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010003(void)
{
	const ZBX_FIELD	field = {"error_handler", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("item_preproc", &field);
}

static int	DBpatch_4010004(void)
{
	const ZBX_FIELD	field = {"error_handler_params", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("item_preproc", &field);
}
#endif

DBPATCH_START(4010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4010001, 0, 1)
DBPATCH_ADD(4010002, 0, 1)
DBPATCH_ADD(4010003, 0, 1)
DBPATCH_ADD(4010004, 0, 1)

DBPATCH_END()
