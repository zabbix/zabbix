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

#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "dbupgrade.h"

extern unsigned char	program_type;

/*
 * 6.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6030000(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_db_insert_t		db_insert;
	int			ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select roleid,type,name,value_int from role_rule where name in ("
			"'ui.configuration.actions',"
			"'ui.services.actions',"
			"'ui.administration.general')");

	zbx_db_insert_prepare(&db_insert, "role_rule", "role_ruleid", "roleid", "type", "name", "value_int", NULL);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	roleid;
		int		value_int, type;

		ZBX_STR2UINT64(roleid, row[0]);
		type = atoi(row[1]);
		value_int = atoi(row[3]);

		if (0 == strcmp(row[2], "ui.configuration.actions"))
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.autoregistration_actions", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.discovery_actions", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.internal_actions", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.trigger_actions", value_int);
		}
		else if (0 == strcmp(row[2], "ui.administration.general"))
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.administration.housekeeping", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.administration.macros", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.administration.api_tokens", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.administration.audit_log", value_int);
		}
		else
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.service_actions", value_int);
		}
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "role_ruleid");

	if (SUCCEED == (ret = zbx_db_insert_execute(&db_insert)))
	{
		if (ZBX_DB_OK > DBexecute("delete from role_rule where name in ("
			"'ui.configuration.actions',"
			"'ui.services.actions')"))
		{
			ret = FAIL;
		}
	}

	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6030001(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql;
	size_t			sql_alloc = 4096, sql_offset = 0;
	int			ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	sql = zbx_malloc(NULL, sql_alloc);

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select moduleid,relative_path from module");

	while (NULL != (row = DBfetch(result)))
	{
		const char	*rel_path = row[1];
		char		*updated_path, *updated_path_esc;

		if (NULL == rel_path || '\0' == *rel_path)
			continue;

		updated_path = zbx_dsprintf(NULL, "modules/%s", rel_path);

		updated_path_esc = DBdyn_escape_string(updated_path);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update module set relative_path='%s' "
				"where moduleid=%s;\n", updated_path_esc, row[0]);

		zbx_free(updated_path);
		zbx_free(updated_path_esc);

		ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	DBfree_result(result);

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);

	return ret;
}

static int	DBpatch_6030002(void)
{
	zbx_db_insert_t	db_insert;
	int		i, ret = FAIL;

	const char	*modules[] = {
			"actionlog", "clock", "dataover", "discovery", "favgraphs", "favmaps", "geomap", "graph",
			"graphprototype", "hostavail", "item", "map", "navtree", "plaintext", "problemhosts",
			"problems", "problemsbysv", "slareport", "svggraph", "systeminfo", "tophosts", "trigover",
			"url", "web"
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "module", "moduleid", "id", "relative_path", "status", "config", NULL);

	for (i = 0; i < (int)ARRSIZE(modules); i++)
	{
		char	*path;

		path = zbx_dsprintf(NULL, "widgets/%s", modules[i]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), modules[i], path, 1, "[]");
		zbx_free(path);
	}

	zbx_db_insert_autoincrement(&db_insert, "moduleid");
	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);

	return ret;
}

#endif

DBPATCH_START(6030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6030000, 0, 1)
DBPATCH_ADD(6030001, 0, 1)
DBPATCH_ADD(6030002, 0, 1)

DBPATCH_END()
