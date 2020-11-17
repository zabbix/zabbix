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

/*
 * 5.4 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5030000(void)
{
	const ZBX_FIELD	field = {"available", 0, NULL, NULL, 5, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030001(void)
{
	const ZBX_FIELD	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030002(void)
{
	const ZBX_FIELD	field = {"errors_from", 0, NULL, NULL, 5, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030003(void)
{
	const ZBX_FIELD	field = {"disable_until", 0, NULL, NULL, 5, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030004(void)
{
	return DBdrop_field("hosts", "available");
}

static int	DBpatch_5030005(void)
{
	return DBdrop_field("hosts", "ipmi_available");
}

static int	DBpatch_5030006(void)
{
	return DBdrop_field("hosts", "snmp_available");
}

static int	DBpatch_5030007(void)
{
	return DBdrop_field("hosts", "jmx_available");
}

static int	DBpatch_5030008(void)
{
	return DBdrop_field("hosts", "disable_until");
}

static int	DBpatch_5030009(void)
{
	return DBdrop_field("hosts", "ipmi_disable_until");
}

static int	DBpatch_5030010(void)
{
	return DBdrop_field("hosts", "snmp_disable_until");
}

static int	DBpatch_5030011(void)
{
	return DBdrop_field("hosts", "jmx_disable_until");
}

static int	DBpatch_5030012(void)
{
	return DBdrop_field("hosts", "errors_from");
}

static int	DBpatch_5030013(void)
{
	return DBdrop_field("hosts", "ipmi_errors_from");
}

static int	DBpatch_5030014(void)
{
	return DBdrop_field("hosts", "snmp_errors_from");
}

static int	DBpatch_5030015(void)
{
	return DBdrop_field("hosts", "jmx_errors_from");
}

static int	DBpatch_5030016(void)
{
	return DBdrop_field("hosts", "error");
}

static int	DBpatch_5030017(void)
{
	return DBdrop_field("hosts", "ipmi_error");
}

static int	DBpatch_5030018(void)
{
	return DBdrop_field("hosts", "snmp_error");
}

static int	DBpatch_5030019(void)
{
	return DBdrop_field("hosts", "jmx_error");
}

static int	DBpatch_5030020(void)
{
	return DBcreate_index("interface", "interface_3", "available", 0);
}
#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)
DBPATCH_ADD(5030001, 0, 1)
DBPATCH_ADD(5030002, 0, 1)
DBPATCH_ADD(5030003, 0, 1)
DBPATCH_ADD(5030004, 0, 1)
DBPATCH_ADD(5030005, 0, 1)
DBPATCH_ADD(5030006, 0, 1)
DBPATCH_ADD(5030007, 0, 1)
DBPATCH_ADD(5030008, 0, 1)
DBPATCH_ADD(5030009, 0, 1)
DBPATCH_ADD(5030010, 0, 1)
DBPATCH_ADD(5030011, 0, 1)
DBPATCH_ADD(5030012, 0, 1)
DBPATCH_ADD(5030013, 0, 1)
DBPATCH_ADD(5030014, 0, 1)
DBPATCH_ADD(5030015, 0, 1)
DBPATCH_ADD(5030016, 0, 1)
DBPATCH_ADD(5030017, 0, 1)
DBPATCH_ADD(5030018, 0, 1)
DBPATCH_ADD(5030019, 0, 1)
DBPATCH_ADD(5030020, 0, 1)

DBPATCH_END()
