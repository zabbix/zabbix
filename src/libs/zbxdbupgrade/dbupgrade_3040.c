/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

/*
 * 3.4 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3040000(void)
{
	return SUCCEED;
}

extern int	DBpatch_3020001(void);

static int	DBpatch_3040001(void)
{
	return DBpatch_3020001();
}

#endif

DBPATCH_START(3040)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3040000, 0, 1)
DBPATCH_ADD(3040001, 0, 0)

DBPATCH_END()
