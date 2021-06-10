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

static int	DBpatch_5050000(void)
{
	const ZBX_TABLE table =
		{"service_tag", "servicetagid", 0,
			{
				{"servicetagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050001(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_tag", 1, &field);
}

static int	DBpatch_5050002(void)
{
	return DBdrop_field("services_links", "soft");
}

static int	DBpatch_5050003(void)
{
	return DBcreate_index("service_tag", "service_tag_1", "serviceid", 0);
}

static void	DBpatch_get_role_rules(zbx_uint64_t roleid, int *ui_default_access, int *actions_default_access,
		int *ui_conf_services)
{
	DB_ROW		row;
	DB_RESULT	result;

	result = DBselect("select value_int,name from role_rule where roleid=" ZBX_FS_UI64 " and name in "
			"('ui.default_access', 'actions.default_access', 'ui.configuration.services')", roleid);

	while (NULL != (row = DBfetch(result)))
	{
		int	value;
		char	*name;

		value = atoi(row[0]);
		name = row[1];

		if (0 == strcmp(name, "ui.default_access"))
		{
			*ui_default_access = value;
		}
		else if (0 == strcmp(name, "actions.default_access"))
		{
			*actions_default_access = value;
		}
		else if (0 == strcmp(name, "ui.configuration.services"))
		{
			*ui_conf_services = value;
		}
	}

	DBfree_result(result);
}

static int	DBpatch_5050004(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;

	result = DBselect("select distinct roleid from role_rule");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_int64_t	roleid;
		int ui_def_access = -1, act_def_access = -1, ui_conf_services = -1;

		ZBX_STR2UINT64(roleid, row[0]);

		DBpatch_get_role_rules(roleid, &ui_def_access, &act_def_access, &ui_conf_services);

		if (ui_def_access == act_def_access && -1 != ui_conf_services)
		{
			if (ZBX_DB_OK > DBexecute("update role_rule set name='actions.manage_services' where"
					" roleid=" ZBX_FS_UI64 " and name='ui.configuration.services'", roleid))
			{
				ret = FAIL;
				goto out;
			}
		}
		else if (1 == ui_def_access && -1 == ui_conf_services && 0 == act_def_access)
		{
			if (ZBX_DB_OK > DBexecute("insert into role_rule (role_ruleid,roleid,type,name,value_int,"
					"value_str,value_moduleid) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",0,"
					"'actions.manage_services',1,'',NULL)", DBget_maxid("role_rule"), roleid))
			{
				ret = FAIL;
				goto out;
			}
		}
		else if (0 == ui_def_access && 1 == ui_conf_services && 1 == act_def_access)
		{
			if (ZBX_DB_OK > DBexecute("delete from role_rule where roleid=" ZBX_FS_UI64 " and "
					"name='ui.configuration.services'", roleid))
			{
				ret = FAIL;
				goto out;
			}
		}
	}
out:
	DBfree_result(result);
	return ret;
}

#endif

DBPATCH_START(5050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5050000, 0, 1)
DBPATCH_ADD(5050001, 0, 1)
DBPATCH_ADD(5050002, 0, 1)
DBPATCH_ADD(5050003, 0, 1)
DBPATCH_ADD(5050004, 0, 1)

DBPATCH_END()
