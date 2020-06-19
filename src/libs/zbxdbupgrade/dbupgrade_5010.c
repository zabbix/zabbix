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
 * 5.2 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5010000(void)
{
	const ZBX_FIELD	field = {"login_attempts", "5", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010001(void)
{
	const ZBX_FIELD	field = {"login_block", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010002(void)
{
	const ZBX_FIELD	field = {"session_name", "zbx_sessionid", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010003(void)
{
	const ZBX_FIELD	field = {"show_technical_errors", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010004(void)
{
	const ZBX_FIELD	field = {"validate_uri_schemes", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010005(void)
{
	const ZBX_FIELD	field = {"uri_valid_schemes", "http,https,ftp,file,mailto,tel,ssh", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010006(void)
{
	const ZBX_FIELD	field = {"x_frame_options", "SAMEORIGIN", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010007(void)
{
	const ZBX_FIELD	field = {"history_period", "24h", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010008(void)
{
	const ZBX_FIELD	field = {"period_default", "1h", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010009(void)
{
	const ZBX_FIELD	field = {"max_period", "2y", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010010(void)
{
	const ZBX_FIELD	field = {"socket_timeout", "3s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010011(void)
{
	const ZBX_FIELD	field = {"connect_timeout", "3s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010012(void)
{
	const ZBX_FIELD	field = {"media_type_test_timeout", "65s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010013(void)
{
	const ZBX_FIELD	field = {"script_timeout", "60s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010014(void)
{
	const ZBX_FIELD	field = {"item_test_timeout", "60s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

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
DBPATCH_ADD(5010008, 0, 1)
DBPATCH_ADD(5010009, 0, 1)
DBPATCH_ADD(5010010, 0, 1)
DBPATCH_ADD(5010011, 0, 1)
DBPATCH_ADD(5010012, 0, 1)
DBPATCH_ADD(5010013, 0, 1)
DBPATCH_ADD(5010014, 0, 1)

DBPATCH_END()
