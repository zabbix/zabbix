/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

extern unsigned char	program_type;

/*
 * 6.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_5050001(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update config set default_lang='en_US' where default_lang='en_GB'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update users set lang='en_US' where lang='en_GB'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050003(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	const ZBX_FIELD	field = {"default_lang", "en_US", NULL, NULL, 5, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

#endif

DBPATCH_START(5050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5050001, 0, 1)
DBPATCH_ADD(5050002, 0, 1)
DBPATCH_ADD(5050003, 0, 1)

DBPATCH_END()
