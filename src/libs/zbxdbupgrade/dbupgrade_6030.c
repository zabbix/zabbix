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
#include "zbxdbhigh.h"
#include "dbupgrade.h"

extern unsigned char	program_type;

/*
 * 6.4 development database patches
 */

#ifndef HAVE_SQLITE3

static const char *DBpatch_6030000_convert_single_rolerule_value(const char *old_name)
{
		if (0 == strcmp(old_name, "ui.administration.media_types"))
		{
			return "ui.alerting.media_types";
		}
		else if (0 == strcmp(old_name, "ui.administration.scripts"))
		{
			return "ui.alerting.scripts";
		}
		else if (0 == strcmp(old_name, "ui.services.actions"))
		{
			return "ui.alerting.service_actions";
		}
		else if (0 == strcmp(old_name, "ui.configuration.actions"))
		{
			return "ui.alerting.trigger_actions";
		}
		else if (0 == strcmp(old_name, "ui.monitoring.dashboard"))
		{
			return "ui.dashboards.dashboards";
		}
		else if (0 == strcmp(old_name, "ui.configuration.discovery"))
		{
			return "ui.data_collection.discovery";
		}
		else if (0 == strcmp(old_name, "ui.configuration.event_correlation"))
		{
			return "ui.data_collection.event_correlation";
		}
		else if (0 == strcmp(old_name, "ui.configuration.host_groups"))
		{
			return "ui.data_collection.host_groups";
		}
		else if (0 == strcmp(old_name, "ui.configuration.hosts"))
		{
			return "ui.data_collection.hosts";
		}
		else if (0 == strcmp(old_name, "ui.configuration.maintenance"))
		{
			return "ui.data_collection.maintenance";
		}
		else if (0 == strcmp(old_name, "ui.configuration.template_groups"))
		{
			return "ui.data_collection.template_groups";
		}
		else if (0 == strcmp(old_name, "ui.configuration.templates"))
		{
			return "ui.data_collection.templates";
		}
		else if (0 == strcmp(old_name, "ui.administration.general"))
		{
			return "ui.users.api_tokens";
		}
		else if (0 == strcmp(old_name, "ui.administration.authentication"))
		{
			return "ui.users.authentication";
		}
		else if (0 == strcmp(old_name, "ui.administration.user_groups"))
		{
			return "ui.users.user_groups";
		}
		else if (0 == strcmp(old_name, "ui.administration.user_roles"))
		{
			return "ui.users.user_roles";
		}
		else if (0 == strcmp(old_name, "ui.administration.users"))
		{
			return "ui.users.users";
		}
}

static int	DBpatch_6030000(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_db_insert_t	db_insert;
	int				ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select roleid,type,name,value_int,value_str from role_rule where name in ("
			"'ui.administration.authentication',"
			"'ui.administration.general',"
			"'ui.administration.media_types',"
			"'ui.administration.scripts',"
			"'ui.administration.user_groups',"
			"'ui.administration.user_roles',"
			"'ui.administration.users',"
			"'ui.configuration.actions',"
			"'ui.configuration.discovery',"
			"'ui.configuration.event_correlation',"
			"'ui.configuration.host_groups',"
			"'ui.configuration.hosts',"
			"'ui.configuration.maintenance',"
			"'ui.configuration.template_groups',"
			"'ui.configuration.templates',"
			"'ui.monitoring.dashboard',"
			"'ui.services.actions')");

	zbx_db_insert_prepare(&db_insert, "role_rule", "role_ruleid", "roleid", "type", "name", "value_int", "value_str",
			"value_moduleid", "value_serviceid", NULL);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	roleid, type, value_int;
		char			*name, *value_str;

		ZBX_STR2UINT64(roleid, row[0]);
		ZBX_STR2UINT64(type, row[1]);
		name = row[2];
		ZBX_STR2UINT64(value_int, row[3]);
		value_str = row[4];

		if (0 == strcmp(name, "ui.administration.general"))
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type, "ui.administration.housekeeping",
					value_int, value_str, NULL, NULL);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type, "ui.administration.macros",
					value_int, value_str, NULL, NULL);
		}
		else if (0 == strcmp(name, "ui.configuration.actions"))
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type, "ui.alerting.autoregistration_actions",
					value_int, value_str, NULL, NULL);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type, "ui.alerting.discovery_actions",
					value_int, value_str, NULL, NULL);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type, "ui.alerting.internal_actions",
					value_int, value_str, NULL, NULL);
		}
		else
		{
			const char	*new_name;

			new_name = DBpatch_6030000_convert_single_rolerule_value(name);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type, new_name,
					value_int, value_str, NULL, NULL);
		}
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "role_ruleid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6030001(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from role_rule where name in ("
			"'ui.administration.authentication',"
			"'ui.administration.media_types',"
			"'ui.administration.scripts',"
			"'ui.administration.user_groups',"
			"'ui.administration.user_roles',"
			"'ui.administration.users',"
			"'ui.configuration.actions',"
			"'ui.configuration.discovery',"
			"'ui.configuration.event_correlation',"
			"'ui.configuration.host_groups',"
			"'ui.configuration.hosts',"
			"'ui.configuration.maintenance',"
			"'ui.configuration.template_groups',"
			"'ui.configuration.templates',"
			"'ui.monitoring.dashboard')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#endif

DBPATCH_START(6030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6030000, 0, 1)
DBPATCH_ADD(6030001, 0, 1)

DBPATCH_END()
