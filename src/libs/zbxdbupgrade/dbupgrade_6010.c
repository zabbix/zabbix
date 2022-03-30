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
#include "zbxalgo.h"

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
	const ZBX_TABLE	table =
			{"tplgrp", "groupid", 0,
				{
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6010005(void)
{
	return DBcreate_index("tplgrp", "tplgrp_1", "name", 1);
}

static int	DBpatch_6010006(void)
{
	const ZBX_TABLE	table =
			{"template_group", "templategroupid", 0,
				{
					{"templategroupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6010007(void)
{
	return DBcreate_index("template_group", "templates_groups_1", "hostid,groupid", 1);
}

static int	DBpatch_6010008(void)
{
	return DBcreate_index("template_group", "templates_groups_2", "groupid", 0);
}

static int	DBpatch_6010009(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("template_group", 1, &field);
}

static int	DBpatch_6010010(void)
{
	const ZBX_FIELD	field = {"groupid", NULL, "tplgrp", "groupid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("template_group", 2, &field);
}

static int	DBpatch_6010011(void)
{
	const ZBX_TABLE	table =
			{"right_tplgrp", "rightid", 0,
				{
					{"rightid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", 0, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6010012(void)
{
	return DBcreate_index("right_tplgrp", "right_tplgrp_1", "groupid", 0);
}

static int	DBpatch_6010013(void)
{
	return DBcreate_index("right_tplgrp", "right_tplgrp_2", "id", 0);
}

static int	DBpatch_6010014(void)
{
	const ZBX_FIELD	field = {"groupid", NULL, "usrgrp", "usrgrpid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("right_tplgrp", 1, &field);
}

static int	DBpatch_6010015(void)
{
	const ZBX_FIELD	field = {"id", NULL, "tplgrp", "groupid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("right_tplgrp", 2, &field);
}

#define DBPATCH_HOST_STATUS_TEMPLATE	"3"
#define DBPATCH_GROUPIDS(cmp)									\
		"select distinct g.groupid"							\
		" from hosts h,hosts_groups hg,hstgrp g"					\
		" where g.groupid=hg.groupid and"						\
			" hg.hostid=h.hostid and"						\
			" h.status" cmp DBPATCH_HOST_STATUS_TEMPLATE " and length(g.name)>0"
#define DBPATCH_TPLGRP_GROUPIDS	DBPATCH_GROUPIDS("=")
#define DBPATCH_HSTGRP_GROUPIDS	DBPATCH_GROUPIDS("<>")


static int	DBpatch_hstgrp2tplgrp_copy(void)
{

	zbx_db_insert_t	db_insert;
	DB_RESULT	result;
	DB_ROW		row;
	int		ret;

	zbx_db_insert_prepare(&db_insert, "tplgrp", "groupid", "name", "uuid", NULL);

	result = DBselect(
			"select o.name,o.uuid from hstgrp o"
			" where o.groupid in (" DBPATCH_TPLGRP_GROUPIDS
			") order by o.groupid asc");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), row[0], row[1]);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "groupid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_hosts_groups2template_group_move(void)
{

	zbx_db_insert_t		db_insert;
	DB_RESULT		result;
	DB_ROW			row;
	int			ret;
	zbx_vector_uint64_t	del_ids;

	zbx_vector_uint64_create(&del_ids);

	zbx_db_insert_prepare(&db_insert, "template_group", "templategroupid", "hostid", "groupid", NULL);

	result = DBselect(
			"select o.hostgroupid,o.hostid,t.groupid"
			" from hosts_groups o,hosts h2,hstgrp hg2,tplgrp t"
			" where o.groupid in (" DBPATCH_TPLGRP_GROUPIDS ") and"
				" o.hostid=h2.hostid and"
				" h2.status=" DBPATCH_HOST_STATUS_TEMPLATE " and"
				" o.groupid=hg2.groupid and"
				" hg2.uuid=t.uuid"
			" order by o.hostgroupid asc");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	hostgroupid, hostid, groupid;

		ZBX_STR2UINT64(hostgroupid, row[0]);
		ZBX_STR2UINT64(hostid, row[1]);
		ZBX_STR2UINT64(groupid, row[2]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), hostid, groupid);
		zbx_vector_uint64_append(&del_ids, hostgroupid);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "templategroupid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (SUCCEED == ret && 0 != del_ids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_vector_uint64_sort(&del_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts_groups where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostgroupid",del_ids.values, del_ids.values_num);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&del_ids);

	return ret;
}

static int	DBpatch_rights2right_tplgrp_copy(void)
{

	zbx_db_insert_t		db_insert;
	DB_RESULT		result;
	DB_ROW			row;
	int			ret;

	zbx_db_insert_prepare(&db_insert, "right_tplgrp", "rightid", "groupid", "permission", "id", NULL);

	result = DBselect(
			"select o.groupid,o.permission,t.groupid"
			" from rights o,hstgrp hg2,tplgrp t"
			" where o.id in (" DBPATCH_TPLGRP_GROUPIDS ") and"
				" o.id=hg2.groupid and"
				" hg2.uuid=t.uuid"
			" order by o.rightid asc");

	while (NULL != (row = DBfetch(result)))
	{
		int		permission;
		zbx_uint64_t	groupid, id;

		ZBX_STR2UINT64(groupid, row[0]);
		permission = atoi(row[1]);
		ZBX_STR2UINT64(id, row[2]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), groupid, permission, id);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "rightid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_hstgrp_del(void)
{

	DB_RESULT		result;
	DB_ROW			row;
	int			ret = SUCCEED;
	zbx_vector_uint64_t	del_ids;

	zbx_vector_uint64_create(&del_ids);

	result = DBselect(
			"select o.groupid from hstgrp o"
			" where o.uuid in (select t.uuid from tplgrp t"
				") and o.groupid not in (" DBPATCH_HSTGRP_GROUPIDS
				") and o.groupid not in ("
					"select distinct p.groupid FROM group_prototype p"
					" where p.groupid is not null"
			") order by o.groupid asc");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	groupid;

		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&del_ids, groupid);
	}
	DBfree_result(result);

	if (0 != del_ids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_vector_uint64_sort(&del_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hstgrp where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", del_ids.values, del_ids.values_num);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;

		zbx_free(sql);
	}
	else
		ret = SUCCEED;

	zbx_vector_uint64_destroy(&del_ids);

	return ret;
}

static int	DBpatch_6010016(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_hstgrp2tplgrp_copy();
}

static int	DBpatch_6010017(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_rights2right_tplgrp_copy();
}

static int	DBpatch_6010018(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_hosts_groups2template_group_move();
}

static int	DBpatch_6010019(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_hstgrp_del();
}

static int	DBpatch_6010020(void)
{
	return DBdrop_field("hstgrp", "internal");
}

static int	DBpatch_6010021(void)
{
	return DBdrop_index("hstgrp", "hstgrp_1");
}

static int	DBpatch_6010022(void)
{
	return DBcreate_index("hstgrp", "hstgrp_1", "name", 1);
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

DBPATCH_END()
