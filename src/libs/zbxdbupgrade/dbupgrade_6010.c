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

#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxstr.h"
#include "zbx_availability_constants.h"
#include "zbx_host_constants.h"

/*
 * 6.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6010000(void)
{
#define ZBX_MD5_SIZE	32
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update users set passwd='' where length(passwd)=%d", ZBX_MD5_SIZE))
		return FAIL;

	return SUCCEED;
#undef ZBX_MD5_SIZE
}

static int	DBpatch_6010001(void)
{
	const zbx_db_field_t	field = {"vault_provider", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6010002(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED;
	char		*sql = NULL, *descripton_esc;
	size_t		sql_alloc = 0, sql_offset = 0;

	result = zbx_db_select(
		"select triggerid,description"
		" from triggers"
		" where " ZBX_DB_CHAR_LENGTH(description) ">%d", 255);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		row[1][zbx_strlen_utf8_nchars(row[1], 255)] = '\0';

		descripton_esc = zbx_db_dyn_escape_field("triggers", "description", row[1]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update triggers set description='%s' where triggerid=%s;\n", descripton_esc, row[0]);
		zbx_free(descripton_esc);

		if (SUCCEED != (ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
		ret = FAIL;
out:
	zbx_db_free_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6010003(void)
{
	const zbx_db_field_t	old_field = {"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};
	const zbx_db_field_t	field = {"description", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("triggers", &field, &old_field);
}

static int	DBpatch_6010004(void)
{
	const zbx_db_field_t	field = {"link_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts_templates", &field);
}

static int	DBpatch_6010005(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	result = zbx_db_select(
		"select ht.hosttemplateid"
		" from hosts_templates ht, hosts h"
		" where ht.hostid=h.hostid and h.flags=4"); /* ZBX_FLAG_DISCOVERY_CREATED */

	while (NULL != (row = zbx_db_fetch(result)))
	{
		/* set ZBX_TEMPLATE_LINK_LLD as link_type */
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hosts_templates set link_type=1 where hosttemplateid=%s;\n", row[0]);

		if (SUCCEED != (ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
		ret = FAIL;
out:
	zbx_db_free_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6010006(void)
{
	const zbx_db_table_t	table =
			{"userdirectory", "userdirectoryid", 0,
				{
					{"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"description", "", NULL, NULL, 255, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
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
	const zbx_db_field_t	field = {"ldap_userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6010008(void)
{
	const zbx_db_field_t	field = {"ldap_userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0,
			ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("config", 3, &field);
}

static int	DBpatch_6010009(void)
{
	return DBcreate_index("config", "config_3", "ldap_userdirectoryid", 0);
}

static int	DBpatch_6010010(void)
{
	const zbx_db_field_t	field = {"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("usrgrp", &field);
}

static int	DBpatch_6010011(void)
{
	const zbx_db_field_t	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			0, 0};

	return DBadd_foreign_key("usrgrp", 2, &field);
}

static int	DBpatch_6010012(void)
{
	return DBcreate_index("usrgrp", "usrgrp_2", "userdirectoryid", 0);
}

static int	DBpatch_6010013(void)
{
	int		rc = ZBX_DB_OK;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (NULL == (result = zbx_db_select("select ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,"
			"ldap_bind_password,ldap_search_attribute"
			" from config where ldap_configured=1")))
	{
		return FAIL;
	}

	if (NULL != (row = zbx_db_fetch(result)))
	{
		char	*base_dn_esc, *bind_dn_esc, *password_esc, *search_esc;

		base_dn_esc = zbx_db_dyn_escape_string(row[2]);
		bind_dn_esc = zbx_db_dyn_escape_string(row[3]);
		password_esc = zbx_db_dyn_escape_string(row[4]);
		search_esc = zbx_db_dyn_escape_string(row[5]);

		rc = zbx_db_execute("insert into userdirectory (userdirectoryid,name,description,host,port,"
				"base_dn,bind_dn,bind_password,search_attribute,start_tls) values "
				"(1,'Default LDAP server','','%s',%s,'%s','%s','%s','%s',%d)",
				row[0], row[1], base_dn_esc, bind_dn_esc, password_esc, search_esc, 0);

		zbx_free(search_esc);
		zbx_free(password_esc);
		zbx_free(bind_dn_esc);
		zbx_free(base_dn_esc);
	}

	zbx_db_free_result(result);

	if (ZBX_DB_OK > rc)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6010014(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update config set ldap_userdirectoryid=1 where ldap_configured=1"))
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
	const zbx_db_table_t	table =
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
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_rtdata", 1, &field);
}

static int	DBpatch_6010023(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	hostid;
	zbx_db_insert_t	insert;
	int		ret;

	zbx_db_insert_prepare(&insert, "host_rtdata", "hostid", "active_available", (char *)NULL);

	result = zbx_db_select("select hostid from hosts where flags!=%i and status in (%i,%i)",
			ZBX_FLAG_DISCOVERY_PROTOTYPE, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		zbx_db_insert_add_values(&insert, hostid, ZBX_INTERFACE_AVAILABLE_UNKNOWN);
	}
	zbx_db_free_result(result);

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
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, out_alloc = 0;
	char		*out = NULL;

	if (ZBX_PROGRAM_TYPE_SERVER != DBget_program_type())
		return SUCCEED;

	result = zbx_db_select(
			"select hi.itemid,hi.type,ht.name"
			" from httptestitem hi,httptest ht"
			" where hi.httptestid=ht.httptestid");

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
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
		esc = zbx_db_dyn_escape_field("items", "name", out);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set name='%s' where itemid="
				ZBX_FS_UI64 ";\n", esc, itemid);
		zbx_free(esc);

		ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	zbx_db_free_result(result);

	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			ret = FAIL;
	}

	zbx_free(sql);
	zbx_free(out);

	return ret;
}

static int	DBpatch_6010025(void)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, out_alloc = 0;
	char		*out = NULL;

	if (ZBX_PROGRAM_TYPE_SERVER != DBget_program_type())
		return SUCCEED;

	result = zbx_db_select(
			"select hi.itemid,hi.type,hs.name,ht.name"
			" from httpstepitem hi,httpstep hs,httptest ht"
			" where hi.httpstepid=hs.httpstepid"
				" and hs.httptestid=ht.httptestid");

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
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

		esc = zbx_db_dyn_escape_field("items", "name", out);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set name='%s' where itemid="
				ZBX_FS_UI64 ";\n", esc, itemid);
		zbx_free(esc);

		ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	zbx_db_free_result(result);

	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			ret = FAIL;
	}

	zbx_free(sql);
	zbx_free(out);

	return ret;
}

#undef HTTPSTEP_ITEM_TYPE_RSPCODE
#undef HTTPSTEP_ITEM_TYPE_TIME
#undef HTTPSTEP_ITEM_TYPE_IN
#undef HTTPSTEP_ITEM_TYPE_LASTSTEP
#undef HTTPSTEP_ITEM_TYPE_LASTERROR

static int	DBpatch_6010026(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx='web.auditlog.filter.action' and value_int=-1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6010028(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & DBget_program_type()))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from role_rule where value_str='trigger.adddependencies' or "
			"value_str='trigger.deletedependencies'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#define	DBPATCH_HOSTGROUP_TYPE_EMPTY		0x00
#define DBPATCH_HOSTGROUP_TYPE_HOST		0x01
#define DBPATCH_HOSTGROUP_TYPE_TEMPLATE		0x02
#define DBPATCH_HOSTGROUP_TYPE_MIXED		(DBPATCH_HOSTGROUP_TYPE_HOST | DBPATCH_HOSTGROUP_TYPE_TEMPLATE)
typedef struct
{
	zbx_uint64_t	groupid;
	zbx_uint64_t	newgroupid;
	char		*name;
	char		*uuid;
	int		type_orig;
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
	const zbx_db_field_t	field = {"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

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

static void	DBpatch_6010033_update_nested_group(hstgrp_t *hstgrp, zbx_vector_hstgrp_t *hstgrps)
{
	int	i, parent_type = DBPATCH_HOSTGROUP_TYPE_EMPTY, child_type = DBPATCH_HOSTGROUP_TYPE_EMPTY;
	size_t	g_sz;

	g_sz = strlen(hstgrp->name);

	for (i = 0; i < hstgrps->values_num; i++)
	{
		size_t	t_sz;

		t_sz = strlen(hstgrps->values[i]->name);

		if (g_sz == t_sz || 0 != strncmp(hstgrp->name, hstgrps->values[i]->name, MIN(g_sz, t_sz)))
			continue;

		if (g_sz > t_sz)
		{
			if (hstgrp->name[t_sz] != '/')
				continue;

			if (hstgrps->values[i]->type_orig != DBPATCH_HOSTGROUP_TYPE_EMPTY)
				parent_type = hstgrps->values[i]->type_orig;
		}
		else
		{
			if (hstgrps->values[i]->name[g_sz] != '/')
				continue;

			child_type |= hstgrps->values[i]->type;
		}
	}

	if (child_type != DBPATCH_HOSTGROUP_TYPE_EMPTY)
	{
		hstgrp->type |= child_type;
	}
	else if (hstgrp->type == DBPATCH_HOSTGROUP_TYPE_EMPTY)
	{
		hstgrp->type = DBPATCH_HOSTGROUP_TYPE_EMPTY == parent_type ? DBPATCH_HOSTGROUP_TYPE_HOST : parent_type;
	}
}

static void	DBpatch_6010033_update_nested_groups(zbx_vector_hstgrp_t *hstgrps)
{
	int	i;

	for (i = 0; i < hstgrps->values_num; i++)
	{
		if (DBPATCH_HOSTGROUP_TYPE_MIXED != hstgrps->values[i]->type)
			DBpatch_6010033_update_nested_group(hstgrps->values[i], hstgrps);
	}
}

static int	DBpatch_6010033_create_template_groups(zbx_vector_hstgrp_t *hstgrps)
{
	int			i, permission, new_count = 0, ret = SUCCEED;
	zbx_uint64_t		groupid;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_insert_t		db_insert;

	for (i = 0; i < hstgrps->values_num; i++)
	{
		if (DBPATCH_HOSTGROUP_TYPE_MIXED == hstgrps->values[i]->type)
			new_count++;
	}

	if (0 == new_count)
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "hstgrp", "groupid", "name", "type", "uuid", (char *)NULL);
	groupid = zbx_db_get_maxid_num("hstgrp", new_count);

	for (i = 0; i < hstgrps->values_num; i++)
	{
		if (DBPATCH_HOSTGROUP_TYPE_MIXED != hstgrps->values[i]->type)
			continue;

		hstgrps->values[i]->newgroupid = groupid++;
		zbx_db_insert_add_values(&db_insert, hstgrps->values[i]->newgroupid, hstgrps->values[i]->name,
				1 /* HOST_GROUP_TYPE_TEMPLATE_GROUP */, hstgrps->values[i]->uuid);
	}

	if (SUCCEED != (ret = zbx_db_insert_execute(&db_insert)))
		goto out;

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_prepare(&db_insert, "rights", "rightid", "groupid", "permission", "id", (char *)NULL);

	for (i = 0; i < hstgrps->values_num; i++)
	{
		if (DBPATCH_HOSTGROUP_TYPE_MIXED != hstgrps->values[i]->type)
			continue;

		result = zbx_db_select("select groupid,permission from rights where id=" ZBX_FS_UI64,
				hstgrps->values[i]->groupid);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(groupid, row[0]);
			permission = atoi(row[1]);
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), groupid, permission,
					hstgrps->values[i]->newgroupid);
		}
		zbx_db_free_result(result);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hosts_groups"
				" set groupid=" ZBX_FS_UI64
				" where groupid=" ZBX_FS_UI64
					" and hostid in ("
						"select hostid"
						" from hosts"
						" where status=3" /* HOST_STATUS_TEMPLATE */
					");\n", hstgrps->values[i]->newgroupid, hstgrps->values[i]->groupid);

		if (SUCCEED != (ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	zbx_db_insert_autoincrement(&db_insert, "rightid");
	if (SUCCEED != (ret = zbx_db_insert_execute(&db_insert)))
		goto out;

	if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
		ret = FAIL;
out:
	zbx_free(sql);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

#define ADD_GROUPIDS_FROM_FIELD(table,field)									\
	do													\
	{													\
		result = zbx_db_select("select distinct " field " from " table " where " field " is not null");	\
														\
		while (NULL != (row = zbx_db_fetch(result)))							\
		{												\
			ZBX_STR2UINT64(groupid, row[0]);							\
			zbx_vector_uint64_append(&host_groupids, groupid);					\
		}												\
		zbx_db_free_result(result);									\
	}													\
	while(0)
#define ADD_GROUPIDS_FROM(table) ADD_GROUPIDS_FROM_FIELD(table, "groupid")

static int	DBpatch_6010033_split_groups(void)
{
	int			i, ret = FAIL;
	zbx_uint64_t		groupid;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_hstgrp_t	hstgrps;
	zbx_vector_uint64_t	host_groupids, template_groupids;

	zbx_vector_hstgrp_create(&hstgrps);
	zbx_vector_uint64_create(&host_groupids);
	zbx_vector_uint64_create(&template_groupids);

	result = zbx_db_select("select groupid,name,uuid from hstgrp order by length(name)");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		hstgrp_t	*hstgrp;

		hstgrp = (hstgrp_t *)zbx_malloc(NULL, sizeof(hstgrp_t));

		ZBX_STR2UINT64(hstgrp->groupid, row[0]);
		hstgrp->name = zbx_strdup(NULL, row[1]);
		hstgrp->uuid = zbx_strdup(NULL, row[2]);
		hstgrp->type = DBPATCH_HOSTGROUP_TYPE_EMPTY;

		zbx_vector_hstgrp_append(&hstgrps, hstgrp);
	}
	zbx_db_free_result(result);

	result = zbx_db_select(
			"select distinct g.groupid"
			" from hstgrp g,hosts_groups hg,hosts h"
			" where g.groupid=hg.groupid"
				" and hg.hostid=h.hostid"
				" and h.status<>3" /* HOST_STATUS_TEMPLATE */);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&host_groupids, groupid);
	}
	zbx_db_free_result(result);

	ADD_GROUPIDS_FROM("group_prototype");
	ADD_GROUPIDS_FROM_FIELD("config", "discovery_groupid");
	ADD_GROUPIDS_FROM("corr_condition_group");
	ADD_GROUPIDS_FROM("group_discovery");
	ADD_GROUPIDS_FROM("maintenances_groups");
	ADD_GROUPIDS_FROM("opcommand_grp");
	ADD_GROUPIDS_FROM("opgroup");
	ADD_GROUPIDS_FROM("scripts");

	/* 0 - ZBX_CONDITION_TYPE_HOST_GROUP */
	result = zbx_db_select("select distinct value from conditions where conditiontype=0");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&host_groupids, groupid);
	}
	zbx_db_free_result(result);

	/* 3 - SYSMAP_ELEMENT_TYPE_HOST_GROUP */
	result = zbx_db_select("select distinct elementid from sysmaps_elements where elementtype=3");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&host_groupids, groupid);
	}
	zbx_db_free_result(result);

	result = zbx_db_select(
			"select distinct g.groupid"
			" from hstgrp g,hosts_groups hg,hosts h"
			" where g.groupid=hg.groupid"
				" and hg.hostid=h.hostid"
				" and h.status=3" /* HOST_STATUS_TEMPLATE */);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&template_groupids, groupid);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(&template_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_sort(&host_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&host_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < hstgrps.values_num; i++)
	{
		groupid = hstgrps.values[i]->groupid;

		if (FAIL != zbx_vector_uint64_bsearch(&host_groupids, groupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			hstgrps.values[i]->type |= DBPATCH_HOSTGROUP_TYPE_HOST;

		if (FAIL != zbx_vector_uint64_bsearch(&template_groupids, groupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			hstgrps.values[i]->type |= DBPATCH_HOSTGROUP_TYPE_TEMPLATE;

		hstgrps.values[i]->type_orig = hstgrps.values[i]->type;
	}

	DBpatch_6010033_update_nested_groups(&hstgrps);

	for (i = 0; i < hstgrps.values_num; i++)
	{
		if (DBPATCH_HOSTGROUP_TYPE_TEMPLATE != hstgrps.values[i]->type)
			continue;

		/* 1 - HOST_GROUP_TYPE_TEMPLATE_GROUP */
		if (ZBX_DB_OK > zbx_db_execute("update hstgrp set type=1 where groupid=" ZBX_FS_UI64,
				hstgrps.values[i]->groupid))
		{
			goto out;
		}
	}

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
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
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

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6010035(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from tag_filter"
			" where groupid in ("
				"select groupid"
				" from hstgrp"
				" where type=1" /* HOST_GROUP_TYPE_TEMPLATE_GROUP */
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6010036(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from widget_field"
			" where value_groupid in ("
				"select groupid"
				" from hstgrp"
				" where type=1" /* HOST_GROUP_TYPE_TEMPLATE_GROUP */
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#undef DBPATCH_HOSTGROUP_TYPE_EMPTY
#undef DBPATCH_HOSTGROUP_TYPE_HOST
#undef DBPATCH_HOSTGROUP_TYPE_TEMPLATE
#undef DBPATCH_HOSTGROUP_TYPE_MIXED
#undef ADD_GROUPIDS_FROM_FIELD
#undef ADD_GROUPIDS_FROM

static int	DBpatch_6010037(void)
{
	const zbx_db_field_t	field = {"automatic", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hostmacro", &field);
}

static int	DBpatch_6010038(void)
{
	if (ZBX_DB_OK > zbx_db_execute(
			"update hostmacro"
			" set automatic=1"	/* ZBX_USERMACRO_AUTOMATIC */
			" where hostid in ("
				"select hostid"
				" from host_discovery"
				" where parent_hostid is not null"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6010039(void)
{
	const zbx_db_field_t	field = {"automatic", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_tag", &field);
}

static int	DBpatch_6010040(void)
{
	if (ZBX_DB_OK > zbx_db_execute(
			"update host_tag"
			" set automatic=1"	/* ZBX_TAG_AUTOMATIC */
			" where hostid in ("
				"select hostid"
				" from host_discovery"
				" where parent_hostid is not null"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}
static int	DBpatch_6010041(void)
{
	const zbx_db_field_t	field = {"suppress_until", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("acknowledges", &field);
}

static int	DBpatch_6010042(void)
{
	const zbx_db_field_t	field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("event_suppress", &field);
}

static int	DBpatch_6010043(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("event_suppress", 3, &field);
}

static int	DBpatch_6010044(void)
{
	const zbx_db_field_t	field = {"parent_taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("task_data", &field);
}

static int	DBpatch_6010045(void)
{
	const zbx_db_table_t	table =
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

static int	DBpatch_6010046(void)
{
	return SUCCEED;
}

static int	DBpatch_6010047(void)
{
	return SUCCEED;
}

static int	DBpatch_6010048(void)
{
	return DBcreate_index("changelog", "changelog_1", "clock", 0);
}

static int	DBpatch_6010049(void)
{
	return DBcreate_changelog_insert_trigger("hosts", "hostid");
}

static int	DBpatch_6010050(void)
{
	return DBcreate_changelog_update_trigger("hosts", "hostid");
}

static int	DBpatch_6010051(void)
{
	return DBcreate_changelog_delete_trigger("hosts", "hostid");
}

static int	DBpatch_6010052(void)
{
	return DBcreate_changelog_insert_trigger("host_tag", "hosttagid");
}

static int	DBpatch_6010053(void)
{
	return DBcreate_changelog_update_trigger("host_tag", "hosttagid");
}

static int	DBpatch_6010054(void)
{
	return DBcreate_changelog_delete_trigger("host_tag", "hosttagid");
}

static int	DBpatch_6010055(void)
{
	return DBcreate_changelog_insert_trigger("items", "itemid");
}

static int	DBpatch_6010056(void)
{
	return DBcreate_changelog_update_trigger("items", "itemid");
}

static int	DBpatch_6010057(void)
{
	return DBcreate_changelog_delete_trigger("items", "itemid");
}

static int	DBpatch_6010058(void)
{
	return DBcreate_changelog_insert_trigger("item_tag", "itemtagid");
}

static int	DBpatch_6010059(void)
{
	return DBcreate_changelog_update_trigger("item_tag", "itemtagid");
}

static int	DBpatch_6010060(void)
{
	return DBcreate_changelog_delete_trigger("item_tag", "itemtagid");
}

static int	DBpatch_6010061(void)
{
	return DBcreate_changelog_insert_trigger("triggers", "triggerid");
}

static int	DBpatch_6010062(void)
{
	return DBcreate_changelog_update_trigger("triggers", "triggerid");
}

static int	DBpatch_6010063(void)
{
	return DBcreate_changelog_delete_trigger("triggers", "triggerid");
}

static int	DBpatch_6010064(void)
{
	return DBcreate_changelog_insert_trigger("trigger_tag", "triggertagid");
}

static int	DBpatch_6010065(void)
{
	return DBcreate_changelog_update_trigger("trigger_tag", "triggertagid");
}

static int	DBpatch_6010066(void)
{
	return DBcreate_changelog_delete_trigger("trigger_tag", "triggertagid");
}

static int	DBpatch_6010067(void)
{
	return DBcreate_changelog_insert_trigger("functions", "functionid");
}

static int	DBpatch_6010068(void)
{
	return DBcreate_changelog_update_trigger("functions", "functionid");
}

static int	DBpatch_6010069(void)
{
	return DBcreate_changelog_delete_trigger("functions", "functionid");
}

static int	DBpatch_6010070(void)
{
	return DBcreate_changelog_insert_trigger("item_preproc", "item_preprocid");
}

static int	DBpatch_6010071(void)
{
	return DBcreate_changelog_update_trigger("item_preproc", "item_preprocid");
}

static int	DBpatch_6010072(void)
{
	return DBcreate_changelog_delete_trigger("item_preproc", "item_preprocid");
}

static int	DBpatch_6010073(void)
{
	return DBdrop_foreign_key("hosts", 3);
}

static int	DBpatch_6010074(void)
{
	const zbx_db_field_t	field = {"templateid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("hosts", 3, &field);
}

static int	DBpatch_6010075(void)
{
	return DBdrop_foreign_key("items", 1);
}

static int	DBpatch_6010076(void)
{
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("items", 1, &field);
}

static int	DBpatch_6010077(void)
{
	return DBdrop_foreign_key("items", 2);
}

static int	DBpatch_6010078(void)
{
	const zbx_db_field_t	field = {"templateid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("items", 2, &field);
}

static int	DBpatch_6010079(void)
{
	return DBdrop_foreign_key("items", 5);
}

static int	DBpatch_6010080(void)
{
	const zbx_db_field_t	field = {"master_itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("items", 5, &field);
}

static int	DBpatch_6010081(void)
{
	return DBdrop_foreign_key("triggers", 1);
}

static int	DBpatch_6010082(void)
{
	const zbx_db_field_t	field = {"templateid", NULL, "triggers", "triggerid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("triggers", 1, &field);
}

static int	DBpatch_6010083(void)
{
	return DBdrop_foreign_key("functions", 1);
}

static int	DBpatch_6010084(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("functions", 1, &field);
}

static int	DBpatch_6010085(void)
{
	return DBdrop_foreign_key("functions", 2);
}

static int	DBpatch_6010086(void)
{
	const zbx_db_field_t	field = {"triggerid", NULL, "triggers", "triggerid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("functions", 2, &field);
}

static int	DBpatch_6010087(void)
{
	return DBdrop_foreign_key("trigger_tag", 1);
}

static int	DBpatch_6010088(void)
{
	const zbx_db_field_t	field = {"triggerid", NULL, "triggers", "triggerid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("trigger_tag", 1, &field);
}

static int	DBpatch_6010089(void)
{
	return DBdrop_foreign_key("item_preproc", 1);
}

static int	DBpatch_6010090(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("item_preproc", 1, &field);
}

static int	DBpatch_6010091(void)
{
	return DBdrop_foreign_key("host_tag", 1);
}

static int	DBpatch_6010092(void)
{
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("host_tag", 1, &field);
}
static int	DBpatch_6010093(void)
{
	return DBdrop_foreign_key("item_tag", 1);
}

static int	DBpatch_6010094(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("item_tag", 1, &field);
}

static int	DBpatch_6010095(void)
{
	const zbx_db_field_t	field = {"lastaccess", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_rtdata", &field);
}

static int	DBpatch_6010096(void)
{
	/* status 5,6 - HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE */
	if (ZBX_DB_OK > zbx_db_execute("insert into host_rtdata (hostid,lastaccess)"
			" select hostid,lastaccess from hosts where status in (5,6)"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6010097(void)
{
	return DBdrop_field("hosts", "lastaccess");
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
DBPATCH_ADD(6010028, 0, 1)
DBPATCH_ADD(6010029, 0, 1)
DBPATCH_ADD(6010030, 0, 1)
DBPATCH_ADD(6010031, 0, 1)
DBPATCH_ADD(6010032, 0, 1)
DBPATCH_ADD(6010033, 0, 1)
DBPATCH_ADD(6010034, 0, 1)
DBPATCH_ADD(6010035, 0, 1)
DBPATCH_ADD(6010036, 0, 1)
DBPATCH_ADD(6010037, 0, 1)
DBPATCH_ADD(6010038, 0, 1)
DBPATCH_ADD(6010039, 0, 1)
DBPATCH_ADD(6010040, 0, 1)
DBPATCH_ADD(6010041, 0, 1)
DBPATCH_ADD(6010042, 0, 1)
DBPATCH_ADD(6010043, 0, 1)
DBPATCH_ADD(6010044, 0, 1)
DBPATCH_ADD(6010045, 0, 1)
DBPATCH_ADD(6010046, 0, 1)
DBPATCH_ADD(6010047, 0, 1)
DBPATCH_ADD(6010048, 0, 1)
DBPATCH_ADD(6010049, 0, 1)
DBPATCH_ADD(6010050, 0, 1)
DBPATCH_ADD(6010051, 0, 1)
DBPATCH_ADD(6010052, 0, 1)
DBPATCH_ADD(6010053, 0, 1)
DBPATCH_ADD(6010054, 0, 1)
DBPATCH_ADD(6010055, 0, 1)
DBPATCH_ADD(6010056, 0, 1)
DBPATCH_ADD(6010057, 0, 1)
DBPATCH_ADD(6010058, 0, 1)
DBPATCH_ADD(6010059, 0, 1)
DBPATCH_ADD(6010060, 0, 1)
DBPATCH_ADD(6010061, 0, 1)
DBPATCH_ADD(6010062, 0, 1)
DBPATCH_ADD(6010063, 0, 1)
DBPATCH_ADD(6010064, 0, 1)
DBPATCH_ADD(6010065, 0, 1)
DBPATCH_ADD(6010066, 0, 1)
DBPATCH_ADD(6010067, 0, 1)
DBPATCH_ADD(6010068, 0, 1)
DBPATCH_ADD(6010069, 0, 1)
DBPATCH_ADD(6010070, 0, 1)
DBPATCH_ADD(6010071, 0, 1)
DBPATCH_ADD(6010072, 0, 1)
DBPATCH_ADD(6010073, 0, 1)
DBPATCH_ADD(6010074, 0, 1)
DBPATCH_ADD(6010075, 0, 1)
DBPATCH_ADD(6010076, 0, 1)
DBPATCH_ADD(6010077, 0, 1)
DBPATCH_ADD(6010078, 0, 1)
DBPATCH_ADD(6010079, 0, 1)
DBPATCH_ADD(6010080, 0, 1)
DBPATCH_ADD(6010081, 0, 1)
DBPATCH_ADD(6010082, 0, 1)
DBPATCH_ADD(6010083, 0, 1)
DBPATCH_ADD(6010084, 0, 1)
DBPATCH_ADD(6010085, 0, 1)
DBPATCH_ADD(6010086, 0, 1)
DBPATCH_ADD(6010087, 0, 1)
DBPATCH_ADD(6010088, 0, 1)
DBPATCH_ADD(6010089, 0, 1)
DBPATCH_ADD(6010090, 0, 1)
DBPATCH_ADD(6010091, 0, 1)
DBPATCH_ADD(6010092, 0, 1)
DBPATCH_ADD(6010093, 0, 1)
DBPATCH_ADD(6010094, 0, 1)
DBPATCH_ADD(6010095, 0, 1)
DBPATCH_ADD(6010096, 0, 1)
DBPATCH_ADD(6010097, 0, 1)

DBPATCH_END()
