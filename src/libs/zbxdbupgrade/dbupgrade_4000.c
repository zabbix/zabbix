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

/*
 * 4.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char program_type;

static int	DBpatch_4000000(void)
{
	return SUCCEED;
}

static int	DBpatch_4000001(void)
{
	DB_RESULT	result;
	int		ret;
	zbx_field_len_t	fields[] = {
			{"def_shortdata", 0},
			{"def_longdata", 0},
			{"r_shortdata", 0},
			{"r_longdata", 0},
			{"ack_shortdata", 0},
			{"ack_longdata", 0}
	};

	/* 0 - EVENT_SOURCE_TRIGGERS */
	result = DBselect("select actionid,def_shortdata,def_longdata,r_shortdata,r_longdata,ack_shortdata,"
			"ack_longdata from actions where eventsource=0");

	ret = db_rename_macro(result, "actions", "actionid", fields, ARRSIZE(fields), "{TRIGGER.NAME}",
			"{EVENT.NAME}");

	DBfree_result(result);

	return ret;
}

static int	DBpatch_4000002(void)
{
	DB_RESULT	result;
	int		ret;
	zbx_field_len_t	fields[] = {
			{"subject", 0},
			{"message", 0}
	};

	/* 0 - EVENT_SOURCE_TRIGGERS */
	result = DBselect("select om.operationid,om.subject,om.message"
			" from opmessage om,operations o,actions a"
			" where om.operationid=o.operationid"
				" and o.actionid=a.actionid"
				" and a.eventsource=0");

	ret = db_rename_macro(result, "opmessage", "operationid", fields, ARRSIZE(fields), "{TRIGGER.NAME}",
			"{EVENT.NAME}");

	DBfree_result(result);

	return ret;
}

static int	DBpatch_4000003(void)
{
	DB_RESULT	result;
	int		ret;
	zbx_field_len_t	fields[] = {{"command", 0}};

	/* 0 - EVENT_SOURCE_TRIGGERS */
	result = DBselect("select oc.operationid,oc.command"
			" from opcommand oc,operations o,actions a"
			" where oc.operationid=o.operationid"
				" and o.actionid=a.actionid"
				" and a.eventsource=0");

	ret = db_rename_macro(result, "opcommand", "operationid", fields, ARRSIZE(fields), "{TRIGGER.NAME}",
			"{EVENT.NAME}");

	DBfree_result(result);

	return ret;
}

#endif

DBPATCH_START(4000)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4000000, 0, 1)
DBPATCH_ADD(4000001, 0, 0)
DBPATCH_ADD(4000002, 0, 0)
DBPATCH_ADD(4000003, 0, 0)

DBPATCH_END()
