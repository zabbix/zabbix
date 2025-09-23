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
#include "zbxdbschema.h"
#include "zbxdb.h"

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
	int		i;
	const char	*values[] = {
			"web.hosts.host_prototypes.php.sort", "web.hosts.host.prototype.list.sort",
			"web.hosts.host_prototypes.php.sortorder", "web.hosts.host.prototype.list.sortorder",
			"web.templates.host_prototypes.php.sort", "web.templates.host.prototype.list.sort",
			"web.templates.host_prototypes.php.sortorder", "web.templates.host.prototype.list.sortorder"
		};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
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

DBPATCH_END()
