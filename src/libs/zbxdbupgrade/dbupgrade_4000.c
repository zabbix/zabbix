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
#include "dbupgrade_macros.h"

#include "zbxdb.h"

/*
 * 4.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_4000000(void)
{
	return SUCCEED;
}

static int	DBpatch_4000001(void)
{
	zbx_db_result_t	result;
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
	result = zbx_db_select("select actionid,def_shortdata,def_longdata,r_shortdata,r_longdata,ack_shortdata,"
			"ack_longdata from actions where eventsource=0");

	ret = db_rename_macro(result, "actions", "actionid", fields, ARRSIZE(fields), "{TRIGGER.NAME}",
			"{EVENT.NAME}");

	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_4000002(void)
{
	zbx_db_result_t	result;
	int		ret;
	zbx_field_len_t	fields[] = {
			{"subject", 0},
			{"message", 0}
	};

	/* 0 - EVENT_SOURCE_TRIGGERS */
	result = zbx_db_select("select om.operationid,om.subject,om.message"
			" from opmessage om,operations o,actions a"
			" where om.operationid=o.operationid"
				" and o.actionid=a.actionid"
				" and a.eventsource=0");

	ret = db_rename_macro(result, "opmessage", "operationid", fields, ARRSIZE(fields), "{TRIGGER.NAME}",
			"{EVENT.NAME}");

	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_4000003(void)
{
	zbx_db_result_t	result;
	int		ret;
	zbx_field_len_t	fields[] = {{"command", 0}};

	/* 0 - EVENT_SOURCE_TRIGGERS */
	result = zbx_db_select("select oc.operationid,oc.command"
			" from opcommand oc,operations o,actions a"
			" where oc.operationid=o.operationid"
				" and o.actionid=a.actionid"
				" and a.eventsource=0");

	ret = db_rename_macro(result, "opcommand", "operationid", fields, ARRSIZE(fields), "{TRIGGER.NAME}",
			"{EVENT.NAME}");

	zbx_db_free_result(result);

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
