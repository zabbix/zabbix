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

extern unsigned char	program_type;

/*
 * 6.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6010000(void)
{
#define ZBX_MD5_SIZE	32
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update users set passwd='' where length(passwd)=%d", ZBX_MD5_SIZE))
		return FAIL;

	return SUCCEED;
#undef ZBX_MD5_SIZE
}

static int	DBpatch_6010001(void)
{
	const ZBX_FIELD	field = {"vault_provider", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6010002(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;
	char		*sql = NULL, *descripton_esc;
	size_t		sql_alloc = 0, sql_offset = 0;

	result = DBselect(
		"select triggerid,description"
		" from triggers"
		" where " ZBX_DB_CHAR_LENGTH(description) ">%d", 255);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		row[1][zbx_strlen_utf8_nchars(row[1], 255)] = '\0';

		descripton_esc = DBdyn_escape_field("triggers", "description", row[1]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update triggers set description='%s' where triggerid=%s;\n", descripton_esc, row[0]);
		zbx_free(descripton_esc);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6010003(void)
{
	const ZBX_FIELD	old_field = {"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"description", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("triggers", &field, &old_field);
}

static int	DBpatch_6010004(void)
{
	const ZBX_FIELD	field = {"link_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts_templates", &field);
}

static int	DBpatch_6010005(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	result = DBselect(
		"select ht.hosttemplateid"
		" from hosts_templates ht, hosts h"
		" where ht.hostid=h.hostid and h.flags=4"); /* ZBX_FLAG_DISCOVERY_CREATED */

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		/* set TEMPLATE_LINK_LLD as link_type */
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hosts_templates set link_type=1 where hosttemplateid=%s;\n", row[0]);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6010006(void)
{
	const ZBX_TABLE	table =
			{"changelog", "changelogid", 0,
				{
					{"changelogid", NULL, NULL, NULL, 0, ZBX_TYPE_SERIAL, ZBX_NOTNULL, 0},
					{"object", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"objectid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"operation", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6010007(void)
{
#ifdef HAVE_ORACLE
	return DBcreate_serial_sequence("changelog");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_6010008(void)
{
#ifdef HAVE_ORACLE
	return DBcreate_serial_trigger("changelog", "changelogid");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_6010009(void)
{
	return DBcreate_index("changelog", "changelog_1", "clock", 0);
}

static int	DBpatch_6010010(void)
{
	return DBcreate_changelog_insert_trigger("hosts", "hostid");
}

static int	DBpatch_6010011(void)
{
	return DBcreate_changelog_update_trigger("hosts", "hostid");
}

static int	DBpatch_6010012(void)
{
	return DBcreate_changelog_delete_trigger("hosts", "hostid");
}

static int	DBpatch_6010013(void)
{
	return DBcreate_changelog_insert_trigger("host_tag", "hosttagid");
}

static int	DBpatch_6010014(void)
{
	return DBcreate_changelog_update_trigger("host_tag", "hosttagid");
}

static int	DBpatch_6010015(void)
{
	return DBcreate_changelog_delete_trigger("host_tag", "hosttagid");
}

static int	DBpatch_6010016(void)
{
	return DBcreate_changelog_insert_trigger("items", "itemid");
}

static int	DBpatch_6010017(void)
{
	return DBcreate_changelog_update_trigger("items", "itemid");
}

static int	DBpatch_6010018(void)
{
	return DBcreate_changelog_delete_trigger("items", "itemid");
}

static int	DBpatch_6010019(void)
{
	return DBcreate_changelog_insert_trigger("item_tag", "itemtagid");
}

static int	DBpatch_6010020(void)
{
	return DBcreate_changelog_update_trigger("item_tag", "itemtagid");
}

static int	DBpatch_6010021(void)
{
	return DBcreate_changelog_delete_trigger("item_tag", "itemtagid");
}

static int	DBpatch_6010022(void)
{
	return DBcreate_changelog_insert_trigger("triggers", "triggerid");
}

static int	DBpatch_6010023(void)
{
	return DBcreate_changelog_update_trigger("triggers", "triggerid");
}

static int	DBpatch_6010024(void)
{
	return DBcreate_changelog_delete_trigger("triggers", "triggerid");
}

static int	DBpatch_6010025(void)
{
	return DBcreate_changelog_insert_trigger("trigger_tag", "triggertagid");
}

static int	DBpatch_6010026(void)
{
	return DBcreate_changelog_update_trigger("trigger_tag", "triggertagid");
}

static int	DBpatch_6010027(void)
{
	return DBcreate_changelog_delete_trigger("trigger_tag", "triggertagid");
}

static int	DBpatch_6010028(void)
{
	return DBcreate_changelog_insert_trigger("functions", "functionid");
}

static int	DBpatch_6010029(void)
{
	return DBcreate_changelog_update_trigger("functions", "functionid");
}

static int	DBpatch_6010030(void)
{
	return DBcreate_changelog_delete_trigger("functions", "functionid");
}
#endif

DBPATCH_START(6010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6010000, 0, 1)
DBPATCH_ADD(6010001, 0, 1)
DBPATCH_ADD(6010002, 0, 1)
DBPATCH_ADD(6010003, 0, 1)
DBPATCH_ADD(6010004, 0, 1)
DBPATCH_ADD(6010005, 0, 1)
DBPATCH_ADD(6010006, 0, 1)
DBPATCH_ADD(6010007, 0, 1)
DBPATCH_ADD(6010008, 0, 1)
DBPATCH_ADD(6010009, 0, 1)
DBPATCH_ADD(6010010, 0, 1)
DBPATCH_ADD(6010011, 0, 1)
DBPATCH_ADD(6010012, 0, 1)
DBPATCH_ADD(6010013, 0, 1)
DBPATCH_ADD(6010014, 0, 1)
DBPATCH_ADD(6010015, 0, 1)
DBPATCH_ADD(6010016, 0, 1)
DBPATCH_ADD(6010017, 0, 1)
DBPATCH_ADD(6010018, 0, 1)
DBPATCH_ADD(6010019, 0, 1)
DBPATCH_ADD(6010020, 0, 1)
DBPATCH_ADD(6010021, 0, 1)
DBPATCH_ADD(6010022, 0, 1)
DBPATCH_ADD(6010023, 0, 1)
DBPATCH_ADD(6010024, 0, 1)
DBPATCH_ADD(6010025, 0, 1)
DBPATCH_ADD(6010026, 0, 1)
DBPATCH_ADD(6010027, 0, 1)
DBPATCH_ADD(6010028, 0, 1)
DBPATCH_ADD(6010029, 0, 1)
DBPATCH_ADD(6010030, 0, 1)

DBPATCH_END()
