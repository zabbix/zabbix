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

#define DBPATCH_HOST_STATUS_TEMPLATE	"3"
#define DBPATCH_GROUPIDS(cmp)									\
		"select distinct g.groupid"							\
		" from hosts h,hosts_groups hg,hstgrp g"					\
		" where g.groupid=hg.groupid and"						\
			" hg.hostid=h.hostid and"						\
			" h.status" cmp DBPATCH_HOST_STATUS_TEMPLATE " and length(g.name)>0"
#define DBPATCH_TPLGRP_GROUPIDS	DBPATCH_GROUPIDS("=")
#define DBPATCH_HSTGRP_GROUPIDS	DBPATCH_GROUPIDS("<>")

typedef struct
{
	zbx_uint64_t	groupid;
	zbx_uint64_t	newgroupid;
	char		*name;
	char		*uuid;
}
hstgrp_t;

ZBX_PTR_VECTOR_DECL(hstgrp, hstgrp_t *)
ZBX_PTR_VECTOR_IMPL(hstgrp, hstgrp_t *)

static int	DBpatch_6010006(void)
{
	return DBdrop_field("hstgrp", "internal");
}

static int	DBpatch_6010007(void)
{
	const ZBX_FIELD	field = {"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hstgrp", &field);
}

static int	DBpatch_6010008(void)
{
	return DBdrop_index("hstgrp", "hstgrp_1");
}

static int	DBpatch_6010009(void)
{
	return DBcreate_index("hstgrp", "hstgrp_1", "type,name", 1);
}

static int	DBpatch_6010010(void)
{
	return DBcreate_index("hstgrp", "hstgrp_2", "groupid,uuid", 1);
}

static void	DBpatch_6010011_hstgrp_free(hstgrp_t *hstgrp)
{
	zbx_free(hstgrp->name);
	zbx_free(hstgrp->uuid);
}

static int	DBpatch_6010011_split_groups(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, permission, ret = SUCCEED;
	zbx_uint64_t		nextid, groupid, id;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_hstgrp_t	hstgrps;
	zbx_db_insert_t		db_insert;

	zbx_vector_hstgrp_create(&hstgrps);

	result = DBselect(
			"select o.groupid,o.name,o.uuid from hstgrp o"
			" where o.groupid in (" DBPATCH_TPLGRP_GROUPIDS
			") and o.groupid in (" DBPATCH_HSTGRP_GROUPIDS
			") order by o.groupid asc");

	while (NULL != (row = DBfetch(result)))
	{
		hstgrp_t	*hstgrp;

		hstgrp = (hstgrp_t *)zbx_malloc(NULL, sizeof(hstgrp_t));
		ZBX_STR2UINT64(hstgrp->groupid, row[0]);
		hstgrp->name = zbx_strdup(NULL, row[1]);
		hstgrp->uuid = zbx_strdup(NULL, row[2]);

		zbx_vector_hstgrp_append(&hstgrps, hstgrp);
	}

	if (0 == hstgrps.values_num)
		goto out;

	zbx_vector_hstgrp_sort(&hstgrps, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	zbx_db_insert_prepare(&db_insert, "hstgrp", "groupid", "name", "type", "uuid", NULL);
	nextid = DBget_maxid_num("hstgrp", hstgrps.values_num);

	for (i = 0; i < hstgrps.values_num; i++)
	{
		hstgrps.values[i]->newgroupid = ++nextid;
		zbx_db_insert_add_values(&db_insert, hstgrps.values[i]->newgroupid, hstgrps.values[i]->name,
				HOSTGROUP_TYPE_TEMPLATE, hstgrps.values[i]->uuid);
	}

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_db_insert_prepare(&db_insert, "rights", "rightid", "groupid", "permission", "id", NULL);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	for (i = 0; i < hstgrps.values_num; i++)
	{
		result = DBselect(
				"select r.groupid,r.permission,r.id"
				" from rights r"
				" where r.id=" ZBX_FS_UI64,
				hstgrps.values[i]->groupid);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(groupid, row[0]);
			permission = atoi(row[1]);
			ZBX_STR2UINT64(id, row[2]);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), groupid,
					permission, hstgrps.values[i]->newgroupid);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hosts_groups hg"
				" set hg.groupid ="  ZBX_FS_UI64
				" where groupid=" ZBX_FS_UI64
				" and hostid in ("
				" select h.hostid"
				" from hosts h"
				" where status=" DBPATCH_HOST_STATUS_TEMPLATE ");\n",
				hstgrps.values[i]->newgroupid,
				hstgrps.values[i]->groupid);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}
	zbx_db_insert_autoincrement(&db_insert, "rightid");

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (0 == hstgrps.values_num || ZBX_DB_OK > DBexecute("%s", sql))
		goto out;
out:
	DBfree_result(result);
	zbx_free(sql);

	zbx_vector_hstgrp_clear_ext(&hstgrps, DBpatch_6010011_hstgrp_free);
	zbx_vector_hstgrp_destroy(&hstgrps);

	return ret;
}

static int	DBpatch_6010011(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_6010011_split_groups();
}

static int	DBpatch_6010012(void)
{
	int	ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		goto out;

	if (ZBX_DB_OK > DBexecute("update hstgrp set type=%d where groupid in (" DBPATCH_HSTGRP_GROUPIDS ")",
				HOSTGROUP_TYPE_HOST))
	{
		ret = FAIL;
		goto out;
	}
out:
	return ret;
}

static int	DBpatch_6010013(void)
{
	int	ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		goto out;

	if (ZBX_DB_OK > DBexecute("update hstgrp set type=%d where groupid in (" DBPATCH_TPLGRP_GROUPIDS ")",
				HOSTGROUP_TYPE_TEMPLATE))
	{
		ret = FAIL;
		goto out;
	}
out:
	return ret;
}

static int	DBpatch_6010014_update_empty_groups(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, ret = SUCCEED;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		id;
	zbx_vector_uint64_t	ids;


	result = DBselect(
			"select g.groupid from hstgrp g"
			" left join hosts_groups hg on hg.groupid=g.groupid"
			" left join group_prototype p on p.groupid=g.groupid"
			" where hg.groupid is null and p.groupid is null and"
			" exists(select null from hstgrp where type=1 and"
			" left(name, length(g.name)+1)=concat(g.name, '/'))");

	zbx_vector_uint64_create(&ids);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(&ids, id);
	}

	if (0 == ids.values_num)
		goto out;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < ids.values_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update hstgrp set type=%d"
			" where groupid=%d;\n", HOSTGROUP_TYPE_TEMPLATE, ids.values[i]);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	zbx_vector_uint64_destroy(&ids);

	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6010014(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_6010014_update_empty_groups();
}

static int	DBpatch_6010015(void)
{
	int	ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		goto out;

	if (ZBX_DB_OK > DBexecute("update hstgrp set uuid='' where type=%d",
				HOSTGROUP_TYPE_HOST))
	{
		ret = FAIL;
		goto out;
	}
out:
	return ret;
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

DBPATCH_END()
