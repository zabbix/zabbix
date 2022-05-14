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
#include "log.h"

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
		{"userdirectory", "userdirectoryid", 0,
			{
				{"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"description", "", NULL, NULL, 255, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
				{"host", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"port", "389", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"base_dn", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"bind_dn", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"bind_password", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"search_attribute", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"start_tls", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"search_filter", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_6010007(void)
{
	const ZBX_FIELD	field = {"ldap_userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6010008(void)
{
	const ZBX_FIELD	field = {"ldap_userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("config", 3, &field);
}

static int	DBpatch_6010009(void)
{
	return DBcreate_index("config", "config_3", "ldap_userdirectoryid", 0);
}

static int	DBpatch_6010010(void)
{
	const ZBX_FIELD	field = {"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("usrgrp", &field);
}

static int	DBpatch_6010011(void)
{
	const ZBX_FIELD	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("usrgrp", 2, &field);
}

static int	DBpatch_6010012(void)
{
	return DBcreate_index("usrgrp", "usrgrp_2", "userdirectoryid", 0);
}

static int	DBpatch_6010013(void)
{
	int		rc = ZBX_DB_OK;
	DB_RESULT	result;
	DB_ROW		row;

	if (NULL == (result = DBselect("select ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,"
			"ldap_bind_password,ldap_search_attribute"
			" from config where ldap_configured=1")))
	{
		return FAIL;
	}

	if (NULL != (row = DBfetch(result)))
	{
		char	*base_dn_esc, *bind_dn_esc, *password_esc, *search_esc;

		base_dn_esc = DBdyn_escape_string(row[2]);
		bind_dn_esc = DBdyn_escape_string(row[3]);
		password_esc = DBdyn_escape_string(row[4]);
		search_esc = DBdyn_escape_string(row[5]);

		rc = DBexecute("insert into userdirectory (userdirectoryid,name,description,host,port,"
				"base_dn,bind_dn,bind_password,search_attribute,start_tls) values "
				"(1,'Default LDAP server','','%s',%s,'%s','%s','%s','%s',%d)",
				row[0], row[1], base_dn_esc, bind_dn_esc, password_esc, search_esc, 0);

		zbx_free(search_esc);
		zbx_free(password_esc);
		zbx_free(bind_dn_esc);
		zbx_free(base_dn_esc);
	}

	DBfree_result(result);

	if (ZBX_DB_OK > rc)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6010014(void)
{
	if (ZBX_DB_OK > DBexecute("update config set ldap_userdirectoryid=1 where ldap_configured=1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6010015(void)
{
	return DBdrop_field("config", "ldap_host");
}

static int	DBpatch_6010016(void)
{
	return DBdrop_field("config", "ldap_port");
}

static int	DBpatch_6010017(void)
{
	return DBdrop_field("config", "ldap_base_dn");
}

static int	DBpatch_6010018(void)
{
	return DBdrop_field("config", "ldap_bind_dn");
}

static int	DBpatch_6010019(void)
{
	return DBdrop_field("config", "ldap_bind_password");
}

static int	DBpatch_6010020(void)
{
	return DBdrop_field("config", "ldap_search_attribute");
}

static int	DBpatch_6010021(void)
{
	const ZBX_TABLE	table =
			{"host_rtdata", "hostid", 0,
				{
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"active_available", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6010022(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_rtdata", 1, &field);
}

static int	DBpatch_6010023(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostid;
	zbx_db_insert_t	insert;
	int		ret;

	zbx_db_insert_prepare(&insert, "host_rtdata", "hostid", "active_available", NULL);

	result = DBselect("select hostid from hosts where flags!=%i and status in (%i,%i)",
			ZBX_FLAG_DISCOVERY_PROTOTYPE, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		zbx_db_insert_add_values(&insert, hostid, INTERFACE_AVAILABLE_UNKNOWN);
	}
	DBfree_result(result);

	if (0 != insert.rows.values_num)
		ret = zbx_db_insert_execute(&insert);
	else
		ret = SUCCEED;

	zbx_db_insert_clean(&insert);

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

static int	DBpatch_6010024(void)
{
	return DBdrop_field("hstgrp", "internal");
}

static int	DBpatch_6010025(void)
{
	const ZBX_FIELD	field = {"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hstgrp", &field);
}

static int	DBpatch_6010026(void)
{
	return DBdrop_index("hstgrp", "hstgrp_1");
}

static int	DBpatch_6010027(void)
{
	return DBcreate_index("hstgrp", "hstgrp_1", "type,name", 1);
}

static void	DBpatch_6010028_hstgrp_free(hstgrp_t *hstgrp)
{
	zbx_free(hstgrp->name);
	zbx_free(hstgrp->uuid);
	zbx_free(hstgrp);
}

static int	DBpatch_6010028_split_groups(void)
{
	int			i, permission, ret = SUCCEED;
	zbx_uint64_t		nextid, groupid;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
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
	DBfree_result(result);

	if (0 == hstgrps.values_num)
		goto out;

	zbx_vector_hstgrp_sort(&hstgrps, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	zbx_db_insert_prepare(&db_insert, "hstgrp", "groupid", "name", "type", "uuid", NULL);
	nextid = DBget_maxid_num("hstgrp", hstgrps.values_num);

	for (i = 0; i < hstgrps.values_num; i++)
	{
		hstgrps.values[i]->newgroupid = nextid++;
		zbx_db_insert_add_values(&db_insert, hstgrps.values[i]->newgroupid, hstgrps.values[i]->name,
				HOSTGROUP_TYPE_TEMPLATE, hstgrps.values[i]->uuid);
	}

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (SUCCEED != ret)
		goto out;

	zbx_db_insert_prepare(&db_insert, "rights", "rightid", "groupid", "permission", "id", NULL);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < hstgrps.values_num; i++)
	{
		result = DBselect(
				"select r.groupid,r.permission"
				" from rights r"
				" where r.id=" ZBX_FS_UI64,
				hstgrps.values[i]->groupid);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(groupid, row[0]);
			permission = atoi(row[1]);
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), groupid, permission,
					hstgrps.values[i]->newgroupid);
		}
		DBfree_result(result);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hosts_groups hg"
				" set hg.groupid =" ZBX_FS_UI64
				" where hg.groupid=" ZBX_FS_UI64
				" and hg.hostid in ("
					"select h.hostid"
					" from hosts h"
					" where h.status=" DBPATCH_HOST_STATUS_TEMPLATE
				");\n", hstgrps.values[i]->newgroupid, hstgrps.values[i]->groupid);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	zbx_db_insert_autoincrement(&db_insert, "rightid");

	if (SUCCEED != (ret = zbx_db_insert_execute(&db_insert)))
		goto out;

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	zbx_free(sql);
	zbx_db_insert_clean(&db_insert);
	zbx_vector_hstgrp_clear_ext(&hstgrps, DBpatch_6010028_hstgrp_free);
	zbx_vector_hstgrp_destroy(&hstgrps);

	return ret;
}

static int	DBpatch_6010028(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_6010028_split_groups();
}

static int	DBpatch_6010029(void)
{
	int	ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	if (ZBX_DB_OK > DBexecute("update hstgrp p set p.type=%d where p.type<>%d and p.groupid in"
			" (select t.groupid from (" DBPATCH_TPLGRP_GROUPIDS ") as t)",
			HOSTGROUP_TYPE_TEMPLATE, HOSTGROUP_TYPE_TEMPLATE))
	{
		ret = FAIL;
	}

	return ret;
}

static int	DBpatch_6010030_startfrom(const zbx_vector_str_t *names, const char *name)
{
	int	i;

	for (i = 0; i < names->values_num; i++)
	{
		size_t	t_sz, g_sz;
		char	tmp[256 * ZBX_MAX_BYTES_IN_UTF8_CHAR];

		t_sz = strlen(names->values[i]);
		g_sz = strlen(name);

		if (g_sz == t_sz)
			continue;

		if (t_sz < g_sz)
		{
			zbx_strlcpy(tmp, names->values[i], sizeof(tmp));
			zbx_strlcat(tmp, "/", sizeof(tmp));

			if (0 == strncmp(tmp, name, strlen(tmp)))
				return SUCCEED;
		}
		else
		{
			zbx_strlcpy(tmp, name, sizeof(tmp));
			zbx_strlcat(tmp, "/", sizeof(tmp));

			if (0 == strncmp(tmp, names->values[i], strlen(tmp)))
				return SUCCEED;
		}
	}

	return FAIL;
}

static int	DBpatch_6010030_update_empty(void)
{
	int			ret = SUCCEED;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		id;
	zbx_vector_uint64_t	ids;
	zbx_vector_str_t	names;
	DB_RESULT		result;
	DB_ROW			row;

	result = DBselect(
			"select g.name from hstgrp g"
			" where g.type=%d"
			" order by length(g.name) asc", HOSTGROUP_TYPE_TEMPLATE);

	zbx_vector_str_create(&names);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_vector_str_append(&names, zbx_strdup(NULL, row[0]));
	}
	DBfree_result(result);

	result = DBselect(
			"select g.groupid,g.name from hstgrp g"
			" left join hosts_groups hg on hg.groupid=g.groupid"
			" left join group_prototype p on p.groupid=g.groupid"
			" where hg.groupid is null and p.groupid is null"
			" order by length(g.name) asc");

	zbx_vector_uint64_create(&ids);

	while (NULL != (row = DBfetch(result)))
	{
		if (FAIL == DBpatch_6010030_startfrom(&names, row[1]))
			continue;

		zbx_vector_str_append(&names, zbx_strdup(NULL, row[1]));
		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(&ids, id);
	}
	DBfree_result(result);

	if (0 == ids.values_num)
		goto out;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update hstgrp g set g.type=%d where",
			HOSTGROUP_TYPE_TEMPLATE);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.groupid", ids.values, ids.values_num);

	if (ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	zbx_vector_str_clear_ext(&names, zbx_str_free);
	zbx_vector_str_destroy(&names);
	zbx_vector_uint64_destroy(&ids);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6010030(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_6010030_update_empty();
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
