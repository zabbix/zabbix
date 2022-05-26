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

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

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

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

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

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		/* set ZBX_TEMPLATE_LINK_LLD as link_type */
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hosts_templates set link_type=1 where hosttemplateid=%s;\n", row[0]);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

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

#define HTTPSTEP_ITEM_TYPE_RSPCODE	0
#define HTTPSTEP_ITEM_TYPE_TIME		1
#define HTTPSTEP_ITEM_TYPE_IN		2
#define HTTPSTEP_ITEM_TYPE_LASTSTEP	3
#define HTTPSTEP_ITEM_TYPE_LASTERROR	4

static int	DBpatch_6010024(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, out_alloc = 0;
	char		*out = NULL;

	if (ZBX_PROGRAM_TYPE_SERVER != program_type)
		return SUCCEED;

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select hi.itemid,hi.type,ht.name"
			" from httptestitem hi,httptest ht"
			" where hi.httptestid=ht.httptestid");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;
		char		*esc;
		size_t		out_offset = 0;
		unsigned char	type;

		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UCHAR(type, row[1]);

		switch (type)
		{
			case HTTPSTEP_ITEM_TYPE_IN:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Download speed for scenario \"%s\".", row[2]);
				break;
			case HTTPSTEP_ITEM_TYPE_LASTSTEP:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Failed step of scenario \"%s\".", row[2]);
				break;
			case HTTPSTEP_ITEM_TYPE_LASTERROR:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Last error message of scenario \"%s\".", row[2]);
				break;
		}
		esc = DBdyn_escape_field("items", "name", out);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set name='%s' where itemid="
				ZBX_FS_UI64 ";\n", esc, itemid);
		zbx_free(esc);

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
	zbx_free(out);

	return ret;
}

static int	DBpatch_6010025(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, out_alloc = 0;
	char		*out = NULL;

	if (ZBX_PROGRAM_TYPE_SERVER != program_type)
		return SUCCEED;

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select hi.itemid,hi.type,hs.name,ht.name"
			" from httpstepitem hi,httpstep hs,httptest ht"
			" where hi.httpstepid=hs.httpstepid"
				" and hs.httptestid=ht.httptestid");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;
		char		*esc;
		size_t		out_offset = 0;
		unsigned char	type;

		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UCHAR(type, row[1]);

		switch (type)
		{
			case HTTPSTEP_ITEM_TYPE_IN:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Download speed for step \"%s\" of scenario \"%s\".", row[2], row[3]);
				break;
			case HTTPSTEP_ITEM_TYPE_TIME:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Response time for step \"%s\" of scenario \"%s\".", row[2], row[3]);
				break;
			case HTTPSTEP_ITEM_TYPE_RSPCODE:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Response code for step \"%s\" of scenario \"%s\".", row[2], row[3]);
				break;
		}
		esc = DBdyn_escape_field("items", "name", out);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set name='%s' where itemid="
				ZBX_FS_UI64 ";\n", esc, itemid);
		zbx_free(esc);

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
	zbx_free(out);

	return ret;
}

static int	DBpatch_6010026(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.auditlog.filter.action' and value_int=-1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6010028(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"delete from role_rule where value_str='trigger.adddependencies' or "
			"value_str='trigger.deletedependencies'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#define DBPATCH_HOST_STATUS_TEMPLATE		"3"
#define DBPATCH_HOSTGROUP_TYPE_HOST		0
#define DBPATCH_HOSTGROUP_TYPE_TEMPLATE		1
#define	DBPATCH_HOSTGROUP_TYPE_EMPTY		2
#define DBPATCH_HOSTGROUP_TYPE_MIXED		3
typedef struct
{
	zbx_uint64_t	groupid;
	zbx_uint64_t	newgroupid;
	char		*name;
	char		*uuid;
	int		type;
}
hstgrp_t;

ZBX_PTR_VECTOR_DECL(hstgrp, hstgrp_t *)
ZBX_PTR_VECTOR_IMPL(hstgrp, hstgrp_t *)

static int	DBpatch_6010029(void)
{
	return DBdrop_field("hstgrp", "internal");
}

static int	DBpatch_6010030(void)
{
	const ZBX_FIELD	field = {"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hstgrp", &field);
}

static int	DBpatch_6010031(void)
{
	return DBdrop_index("hstgrp", "hstgrp_1");
}

static int	DBpatch_6010032(void)
{
	return DBcreate_index("hstgrp", "hstgrp_1", "type,name", 1);
}

static void	DBpatch_6010033_hstgrp_free(hstgrp_t *hstgrp)
{
	zbx_free(hstgrp->name);
	zbx_free(hstgrp->uuid);
	zbx_free(hstgrp);
}

static int	DBpatch_6010033_update_group_type(hstgrp_t *hstgrp)
{
	if (DBPATCH_HOSTGROUP_TYPE_MIXED == hstgrp->type)
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update hstgrp set type=%d where groupid=" ZBX_FS_UI64,
			hstgrp->type, hstgrp->groupid))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6010033_starts_with(char *name, zbx_vector_hstgrp_t *hstgrps, int *type)
{
	int	i, last_type, found = 0;
	size_t	g_sz;

	g_sz = strlen(name);

	for (i = 0; i < hstgrps->values_num; i++)
	{
		size_t	t_sz;
		char	tmp[255 * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

		if (DBPATCH_HOSTGROUP_TYPE_MIXED == hstgrps->values[i]->type)
			continue;

		t_sz = strlen(hstgrps->values[i]->name);

		if (g_sz == t_sz)
			continue;

		if (g_sz > t_sz)
		{
			strscpy(tmp, hstgrps->values[i]->name);
			strscat(tmp, "/");

			if (0 == strncmp(tmp, name, strlen(tmp)))
			{
				if (0 != i && last_type != hstgrps->values[i]->type)
				{
					*type = DBPATCH_HOSTGROUP_TYPE_MIXED;
					return SUCCEED;
				}

				last_type = hstgrps->values[i]->type;
				found = 1;
			}
		}
		else
		{
			strscpy(tmp, name);
			strscat(tmp, "/");

			if (0 == strncmp(tmp, hstgrps->values[i]->name, strlen(tmp)))
			{
				if (0 != i && last_type != hstgrps->values[i]->type)
				{
					*type = DBPATCH_HOSTGROUP_TYPE_MIXED;
					return SUCCEED;
				}

				last_type = hstgrps->values[i]->type;
				found = 1;
			}
		}
	}

	if (0 == found)
		return FAIL;

	*type = last_type;
	return SUCCEED;
}

static int	DBpatch_6010033_update_empty(zbx_vector_hstgrp_t *hstgrps)
{
	int	i, ret = SUCCEED;

	for (i = 0; i < hstgrps->values_num; i++)
	{
		int	group_type;

		if (DBPATCH_HOSTGROUP_TYPE_EMPTY != hstgrps->values[i]->type)
			continue;

		if (SUCCEED != DBpatch_6010033_starts_with(hstgrps->values[i]->name, hstgrps, &group_type))
			hstgrps->values[i]->type = DBPATCH_HOSTGROUP_TYPE_HOST;
		else
			hstgrps->values[i]->type = group_type;

		if (SUCCEED != (ret = DBpatch_6010033_update_group_type(hstgrps->values[i])))
			break;
	}

	return ret;
}

static int	DBpatch_6010033_create_template_groups(zbx_vector_hstgrp_t *hstgrps)
{
	int			i, new_count = 0, permission, ret = SUCCEED;
	zbx_uint64_t		nextid, groupid;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_db_insert_t		db_insert;

	zbx_db_insert_prepare(&db_insert, "hstgrp", "groupid", "name", "type", "uuid", NULL);

	for (i = 0; i < hstgrps->values_num; i++)
	{
		if (DBPATCH_HOSTGROUP_TYPE_MIXED == hstgrps->values[i]->type)
			new_count++;
	}

	if (0 == new_count)
		goto out;

	nextid = DBget_maxid_num("hstgrp", new_count);

	for (i = 0; i < hstgrps->values_num; i++)
	{
		if (DBPATCH_HOSTGROUP_TYPE_MIXED != hstgrps->values[i]->type)
			continue;

		hstgrps->values[i]->newgroupid = nextid++;
		zbx_db_insert_add_values(&db_insert, hstgrps->values[i]->newgroupid, hstgrps->values[i]->name,
				DBPATCH_HOSTGROUP_TYPE_TEMPLATE, hstgrps->values[i]->uuid);
	}

	if (SUCCEED != (ret = zbx_db_insert_execute(&db_insert)))
		goto out;

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_prepare(&db_insert, "rights", "rightid", "groupid", "permission", "id", NULL);
	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < hstgrps->values_num; i++)
	{
		if (DBPATCH_HOSTGROUP_TYPE_MIXED != hstgrps->values[i]->type)
			continue;

		result = DBselect(
				"select groupid,permission"
				" from rights"
				" where id=" ZBX_FS_UI64,
				hstgrps->values[i]->groupid);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(groupid, row[0]);
			permission = atoi(row[1]);
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), groupid, permission,
					hstgrps->values[i]->newgroupid);
		}
		DBfree_result(result);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hosts_groups"
				" set groupid =" ZBX_FS_UI64
				" where groupid=" ZBX_FS_UI64
				" and hostid in ("
					"select hostid"
					" from hosts"
					" where status=" DBPATCH_HOST_STATUS_TEMPLATE
				");\n", hstgrps->values[i]->newgroupid, hstgrps->values[i]->groupid);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	zbx_db_insert_autoincrement(&db_insert, "rightid");

	if (SUCCEED != (ret = zbx_db_insert_execute(&db_insert)))
		goto out;

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	zbx_free(sql);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6010033_split_groups(void)
{
	int			i, has_hosts, has_templates, ret = SUCCEED;
	zbx_uint64_t		groupid;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_hstgrp_t	hstgrps;
	zbx_vector_uint64_t	host_groupids, template_groupids;

	zbx_vector_hstgrp_create(&hstgrps);
	zbx_vector_uint64_create(&host_groupids);
	zbx_vector_uint64_create(&template_groupids);

	result = DBselect("select groupid,name,uuid from hstgrp order by length(name)");

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

	result = DBselect(
			"select distinct g.groupid"
			" from hosts h,hosts_groups hg,hstgrp g"
			" where g.groupid=hg.groupid and"
				" hg.hostid=h.hostid and"
				" h.status<>" DBPATCH_HOST_STATUS_TEMPLATE
			);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&host_groupids, groupid);
	}
	DBfree_result(result);

	result = DBselect(
			"select distinct groupid"
			" from group_prototype"
			" where groupid is not null"
			);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&host_groupids, groupid);
	}
	DBfree_result(result);

	result = DBselect(
			"select distinct g.groupid"
			" from hosts h,hosts_groups hg,hstgrp g"
			" where g.groupid=hg.groupid and"
				" hg.hostid=h.hostid and"
				" h.status=" DBPATCH_HOST_STATUS_TEMPLATE
			);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&template_groupids, groupid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&template_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_sort(&host_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&host_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < hstgrps.values_num; i++)
	{
		has_hosts = zbx_vector_uint64_bsearch(&host_groupids, hstgrps.values[i]->groupid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		has_templates = zbx_vector_uint64_bsearch(&template_groupids, hstgrps.values[i]->groupid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (FAIL == has_hosts && FAIL == has_templates)
		{
			hstgrps.values[i]->type = DBPATCH_HOSTGROUP_TYPE_EMPTY;
		}
		else if (FAIL == has_templates)
		{
			hstgrps.values[i]->type = DBPATCH_HOSTGROUP_TYPE_HOST;

			if (SUCCEED != (ret = DBpatch_6010033_update_group_type(hstgrps.values[i])))
				goto out;
		}
		else if (FAIL == has_hosts)
		{
			hstgrps.values[i]->type = DBPATCH_HOSTGROUP_TYPE_TEMPLATE;

			if (SUCCEED != (ret = DBpatch_6010033_update_group_type(hstgrps.values[i])))
				goto out;
		}
		else
			hstgrps.values[i]->type = DBPATCH_HOSTGROUP_TYPE_MIXED;
	}

	if (SUCCEED == (ret = DBpatch_6010033_update_empty(&hstgrps)))
		ret = DBpatch_6010033_create_template_groups(&hstgrps);
out:
	zbx_vector_uint64_destroy(&host_groupids);
	zbx_vector_uint64_destroy(&template_groupids);
	zbx_vector_hstgrp_clear_ext(&hstgrps, DBpatch_6010033_hstgrp_free);
	zbx_vector_hstgrp_destroy(&hstgrps);

	return ret;
}

static int	DBpatch_6010033(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBpatch_6010033_split_groups();
}

static int	DBpatch_6010034(void)
{
	int		i;
	const char	*values[] = {
			"web.auditlog.filter.action", "web.auditlog.filter.actions",
			"web.hostgroups.php.sort", "web.hostgroups.sort",
			"web.hostgroups.php.sortorder", "web.hostgroups.sortorder",
			"web.groups.filter_name", "web.hostgroups.filter_name",
			"web.groups.filter.active", "web.hostgroups.filter.active",
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > DBexecute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
}

#undef DBPATCH_TPLGRP_GROUPIDS
#undef DBPATCH_HOSTGROUP_TYPE_HOST
#undef DBPATCH_HOSTGROUP_TYPE_TEMPLATE
#undef DBPATCH_HOSTGROUP_TYPE_EMPTY
#undef DBPATCH_HOSTGROUP_TYPE_MIXED

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
DBPATCH_ADD(6010028, 0, 1)
DBPATCH_ADD(6010029, 0, 1)
DBPATCH_ADD(6010030, 0, 1)
DBPATCH_ADD(6010031, 0, 1)
DBPATCH_ADD(6010032, 0, 1)
DBPATCH_ADD(6010033, 0, 1)
DBPATCH_ADD(6010034, 0, 1)

DBPATCH_END()
