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

#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbschema.h"

/*
 * 7.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7030000(void)
{
	const zbx_db_field_t	field = {"proxy_secrets_provider", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_7030001(void)
{
	const zbx_db_table_t	table =
			{"settings", "name", 0,
				{
					{"name", NULL, NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"type", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value_str", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"value_int", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value_usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_hostgroupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_mfaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030002(void)
{
	if (FAIL == zbx_db_index_exists("settings", "settings_2"))
		return DBcreate_index("settings", "settings_2", "value_usrgrpid", 0);

	return SUCCEED;
}

static int	DBpatch_7030003(void)
{
	if (FAIL == zbx_db_index_exists("settings", "settings_3"))
		return DBcreate_index("settings", "settings_3", "value_hostgroupid", 0);

	return SUCCEED;
}

static int	DBpatch_7030004(void)
{
	if (FAIL == zbx_db_index_exists("settings", "settings_4"))
		return DBcreate_index("settings", "settings_4", "value_userdirectoryid", 0);

	return SUCCEED;
}

static int	DBpatch_7030005(void)
{
	if (FAIL == zbx_db_index_exists("settings", "settings_5"))
		return DBcreate_index("settings", "settings_5", "value_mfaid", 0);

	return SUCCEED;
}

static int	DBpatch_7030006(void)
{
	const zbx_db_field_t	field = {"value_usrgrpid", NULL, "usrgrp", "usrgrpid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("settings", 2, &field);
}

static int	DBpatch_7030007(void)
{
	const zbx_db_field_t	field = {"value_hostgroupid", NULL, "hstgrp", "groupid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("settings", 3, &field);
}

static int	DBpatch_7030008(void)
{
	const zbx_db_field_t	field = {"value_userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0,
			ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("settings", 4, &field);
}

static int	DBpatch_7030009(void)
{
	const zbx_db_field_t	field = {"value_mfaid", NULL, "mfa", "mfaid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("settings", 5, &field);
}

const char	*target_column[ZBX_SETTING_TYPE_MAX - 1] = {
	"value_str", "value_int", "value_usrgrpid", "value_hostgroupid",
	"value_userdirectoryid", "value_mfaid"
};

static int	DBpatch_7030010(void)
{
	const zbx_setting_entry_t	*table = zbx_settings_desc_table_get();

	for (size_t i = 0; i < zbx_settings_descr_table_size(); i++)
	{
		const zbx_setting_entry_t	*e = &table[i];
		const char			*target_field = target_column[e->type - 1];

		if (ZBX_SETTING_TYPE_STR != e->type)
		{
			if (ZBX_DB_OK > zbx_db_execute("insert into settings (name, type, %s, value_str)"
					" values ('%s', %d, coalesce((select %s from config), %s), '')",
					target_field, e->name, e->type, e->name, e->default_value))
			{
				return FAIL;
			}
		}
		else if (ZBX_DB_OK > zbx_db_execute("insert into settings (name, type, %s)"
				" values ('%s', %d, coalesce((select %s from config), '%s'))",
				target_field, e->name, ZBX_SETTING_TYPE_STR, e->name, e->default_value))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_7030011(void)
{
	return DBdrop_table("config");
}

static int	DBpatch_7030012(void)
{
	const zbx_db_table_t	table =
			{"sysmap_link_threshold", "linkthresholdid", 0,
				{
					{"linkthresholdid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"linkid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"drawtype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"color", "000000", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"threshold", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"pattern", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030013(void)
{
	const zbx_db_field_t	field = {"linkid", NULL, "sysmaps_links", "linkid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_link_threshold", 1, &field);
}

static int	DBpatch_7030014(void)
{
	if (FAIL == zbx_db_index_exists("sysmap_link_threshold", "sysmap_link_threshold_1"))
		return DBcreate_index("sysmap_link_threshold", "sysmap_link_threshold_1", "linkid", 0);

	return SUCCEED;
}

static int	DBpatch_7030015(void)
{
	const zbx_db_field_t	field = {"background_scale", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_7030016(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update sysmaps set background_scale=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_7030017(void)
{
	const zbx_db_field_t	field = {"show_element_label", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_7030018(void)
{
	const zbx_db_field_t	field = {"show_link_label", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_7030019(void)
{
	const zbx_db_field_t	field = {"show_label", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_elements", &field);
}

static int	DBpatch_7030020(void)
{
	const zbx_db_field_t	field = {"show_label", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_links", &field);
}

static int	DBpatch_7030021(void)
{
	const zbx_db_field_t	field = {"indicator_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_links", &field);
}

static int	DBpatch_7030022(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update sysmaps_links sl set indicator_type = 1 "
			"where exists (select null from sysmaps_link_triggers slt where slt.linkid = sl.linkid)"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030023(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("sysmaps_links", &field);
}

static int	DBpatch_7030024(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, 0};

	return DBadd_foreign_key("sysmaps_links", 4, &field);
}

static int	DBpatch_7030025(void)
{
	if (FAIL == zbx_db_index_exists("sysmaps_links", "sysmaps_links_4"))
		return DBcreate_index("sysmaps_links", "sysmaps_links_4", "itemid", 0);

	return SUCCEED;
}

#endif

DBPATCH_START(7030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7030000, 0, 1)
DBPATCH_ADD(7030001, 0, 1)
DBPATCH_ADD(7030002, 0, 1)
DBPATCH_ADD(7030003, 0, 1)
DBPATCH_ADD(7030004, 0, 1)
DBPATCH_ADD(7030005, 0, 1)
DBPATCH_ADD(7030006, 0, 1)
DBPATCH_ADD(7030007, 0, 1)
DBPATCH_ADD(7030008, 0, 1)
DBPATCH_ADD(7030009, 0, 1)
DBPATCH_ADD(7030010, 0, 1)
DBPATCH_ADD(7030011, 0, 1)
DBPATCH_ADD(7030012, 0, 1)
DBPATCH_ADD(7030013, 0, 1)
DBPATCH_ADD(7030014, 0, 1)
DBPATCH_ADD(7030015, 0, 1)
DBPATCH_ADD(7030016, 0, 1)
DBPATCH_ADD(7030017, 0, 1)
DBPATCH_ADD(7030018, 0, 1)
DBPATCH_ADD(7030019, 0, 1)
DBPATCH_ADD(7030020, 0, 1)
DBPATCH_ADD(7030021, 0, 1)
DBPATCH_ADD(7030022, 0, 1)
DBPATCH_ADD(7030023, 0, 1)
DBPATCH_ADD(7030024, 0, 1)
DBPATCH_ADD(7030025, 0, 1)

DBPATCH_END()
