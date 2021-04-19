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
#include "log.h"
#include "db.h"
#include "dbupgrade.h"
#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"
#include "sysinfo.h"

/*
 * 5.4 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5030000(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.queue.config'"))
		return FAIL;

	return SUCCEED;
}

typedef struct
{
	zbx_uint64_t	id;
	zbx_uint64_t	userid;
	char		*idx;
	zbx_uint64_t	idx2;
	zbx_uint64_t	value_id;
	int		value_int;
	char		*value_str;
	char		*source;
	int		type;
}
zbx_dbpatch_profile_t;

static void	DBpatch_get_key_fields(DB_ROW row, zbx_dbpatch_profile_t *profile, char **subsect, char **field, char **key)
{
	int	tok_idx = 0;
	char	*token;

	ZBX_DBROW2UINT64(profile->id, row[0]);
	ZBX_DBROW2UINT64(profile->userid, row[1]);
	profile->idx = zbx_strdup(profile->idx, row[2]);
	ZBX_DBROW2UINT64(profile->idx2, row[3]);
	ZBX_DBROW2UINT64(profile->value_id, row[4]);
	profile->value_int = atoi(row[5]);
	profile->value_str = zbx_strdup(profile->value_str, row[6]);
	profile->source = zbx_strdup(profile->source, row[7]);
	profile->type = atoi(row[8]);

	token = strtok(profile->idx, ".");

	while (NULL != token)
	{
		token = strtok(NULL, ".");
		tok_idx++;

		if (1 == tok_idx)
		{
			*subsect = zbx_strdup(*subsect, token);
		}
		else if (2 == tok_idx)
		{
			*key = zbx_strdup(*key, token);
		}
		else if (3 == tok_idx)
		{
			*field = zbx_strdup(*field, token);
			break;
		}
	}
}

static int	DBpatch_5030001(void)
{
	int		i, ret = SUCCEED;
	const char	*keys[] =
	{
		"web.items.php.sort",
		"web.items.php.sortorder",
		"web.triggers.php.sort",
		"web.triggers.php.sortorder",
		"web.graphs.php.sort",
		"web.graphs.php.sortorder",
		"web.host_discovery.php.sort",
		"web.host_discovery.php.sortorder",
		"web.httpconf.php.sort",
		"web.httpconf.php.sortorder",
		"web.disc_prototypes.php.sort",
		"web.disc_prototypes.php.sortorder",
		"web.trigger_prototypes.php.sort",
		"web.trigger_prototypes.php.sortorder",
		"web.host_prototypes.php.sort",
		"web.host_prototypes.php.sortorder"
	};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; SUCCEED == ret && i < (int)ARRSIZE(keys); i++)
	{
		char			*subsect = NULL, *field = NULL, *key = NULL;
		DB_ROW			row;
		DB_RESULT		result;
		zbx_dbpatch_profile_t	profile = {0};

		result = DBselect("select profileid,userid,idx,idx2,value_id,value_int,value_str,source,type"
				" from profiles where idx='%s'", keys[i]);

		if (NULL == (row = DBfetch(result)))
		{
			DBfree_result(result);
			continue;
		}

		DBpatch_get_key_fields(row, &profile, &subsect, &field, &key);

		DBfree_result(result);

		if (NULL == subsect || NULL == field || NULL == key)
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to parse profile key fields for key '%s'", keys[i]);
			ret = FAIL;
		}

		if (SUCCEED == ret && ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.hosts.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
		}

		if (SUCCEED == ret && ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.templates.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
		}

		if (SUCCEED == ret &&
				ZBX_DB_OK > DBexecute("delete from profiles where profileid=" ZBX_FS_UI64, profile.id))
		{
			ret = FAIL;
		}

		zbx_free(profile.idx);
		zbx_free(profile.value_str);
		zbx_free(profile.source);
		zbx_free(subsect);
		zbx_free(field);
		zbx_free(key);
	}

	return ret;
}

static int	DBpatch_5030002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where "
			"idx like 'web.items.%%filter%%' or "
			"idx like 'web.triggers.%%filter%%' or "
			"idx like 'web.graphs.%%filter%%' or "
			"idx like 'web.host_discovery.%%filter%%' or "
			"idx like 'web.httpconf.%%filter%%' or "
			"idx like 'web.disc_prototypes.%%filter%%' or "
			"idx like 'web.trigger_prototypes.%%filter%%' or "
			"idx like 'web.host_prototypes.%%filter%%'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030003(void)
{
	int			ret = SUCCEED;
	char			*subsect = NULL, *field = NULL, *key = NULL;
	DB_ROW			row;
	DB_RESULT		result;
	zbx_dbpatch_profile_t	profile = {0};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select profileid,userid,idx,idx2,value_id,value_int,value_str,source,type"
			" from profiles where idx in ('web.dashbrd.list.sort','web.dashbrd.list.sortorder')");

	while (NULL != (row = DBfetch(result)))
	{
		DBpatch_get_key_fields(row, &profile, &subsect, &field, &key);

		if (ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.templates.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2,
				profile.value_id, profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
			break;
		}
	}

	DBfree_result(result);

	zbx_free(profile.idx);
	zbx_free(profile.value_str);
	zbx_free(profile.source);
	zbx_free(subsect);
	zbx_free(field);
	zbx_free(key);

	return ret;
}

static int	DBpatch_5030004(void)
{
	const ZBX_FIELD	field = {"available", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030005(void)
{
	const ZBX_FIELD	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030006(void)
{
	const ZBX_FIELD	field = {"errors_from", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030007(void)
{
	const ZBX_FIELD	field = {"disable_until", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030008(void)
{
	return DBdrop_field("hosts", "available");
}

static int	DBpatch_5030009(void)
{
	return DBdrop_field("hosts", "ipmi_available");
}

static int	DBpatch_5030010(void)
{
	return DBdrop_field("hosts", "snmp_available");
}

static int	DBpatch_5030011(void)
{
	return DBdrop_field("hosts", "jmx_available");
}

static int	DBpatch_5030012(void)
{
	return DBdrop_field("hosts", "disable_until");
}

static int	DBpatch_5030013(void)
{
	return DBdrop_field("hosts", "ipmi_disable_until");
}

static int	DBpatch_5030014(void)
{
	return DBdrop_field("hosts", "snmp_disable_until");
}

static int	DBpatch_5030015(void)
{
	return DBdrop_field("hosts", "jmx_disable_until");
}

static int	DBpatch_5030016(void)
{
	return DBdrop_field("hosts", "errors_from");
}

static int	DBpatch_5030017(void)
{
	return DBdrop_field("hosts", "ipmi_errors_from");
}

static int	DBpatch_5030018(void)
{
	return DBdrop_field("hosts", "snmp_errors_from");
}

static int	DBpatch_5030019(void)
{
	return DBdrop_field("hosts", "jmx_errors_from");
}

static int	DBpatch_5030020(void)
{
	return DBdrop_field("hosts", "error");
}

static int	DBpatch_5030021(void)
{
	return DBdrop_field("hosts", "ipmi_error");
}

static int	DBpatch_5030022(void)
{
	return DBdrop_field("hosts", "snmp_error");
}

static int	DBpatch_5030023(void)
{
	return DBdrop_field("hosts", "jmx_error");
}

static int	DBpatch_5030024(void)
{
	return DBcreate_index("interface", "interface_3", "available", 0);
}

static int	DBpatch_5030025(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.overview.type' or idx='web.actionconf.eventsource'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030026(void)
{
	const ZBX_TABLE table =
		{"token", "tokenid", 0,
			{
				{"tokenid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
				{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"token", NULL, NULL, NULL, 128, ZBX_TYPE_CHAR, 0, 0},
				{"lastaccess", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"expires_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"created_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"creator_userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5030027(void)
{
	return DBcreate_index("token", "token_1", "name", 0);
}

static int	DBpatch_5030028(void)
{
	return DBcreate_index("token", "token_2", "userid,name", 1);
}

static int	DBpatch_5030029(void)
{
	return DBcreate_index("token", "token_3", "token", 1);
}

static int	DBpatch_5030030(void)
{
	return DBcreate_index("token", "token_4", "creator_userid", 0);
}

static int	DBpatch_5030031(void)
{
	const ZBX_FIELD field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("token", 1, &field);
}

static int	DBpatch_5030032(void)
{
	const ZBX_FIELD field = {"creator_userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("token", 2, &field);
}

static int	DBpatch_5030033(void)
{
	const ZBX_FIELD	field = {"timeout", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030034(void)
{
	const ZBX_FIELD	old_field = {"command", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"command", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("scripts", &field, &old_field);
}

static int	DBpatch_5030035(void)
{
	const ZBX_TABLE	table =
			{"script_param", "script_paramid", 0,
				{
					{"script_paramid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"scriptid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030036(void)
{
	const ZBX_FIELD	field = {"scriptid", NULL, "scripts", "scriptid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("script_param", 1, &field);
}

static int	DBpatch_5030037(void)
{
	return DBcreate_index("script_param", "script_param_1", "scriptid,name", 1);
}

static int	DBpatch_5030038(void)
{
	const ZBX_FIELD field = {"type", "5", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("scripts", &field);
}

static int	DBpatch_5030039(void)
{
	const ZBX_TABLE table =
		{"valuemap", "valuemapid", 0,
			{
				{"valuemapid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5030040(void)
{
	return DBcreate_index("valuemap", "valuemap_1", "hostid,name", 1);
}

static int	DBpatch_5030041(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("valuemap", 1, &field);
}

static int	DBpatch_5030042(void)
{
	const ZBX_TABLE table =
		{"valuemap_mapping", "valuemap_mappingid", 0,
			{
				{"valuemap_mappingid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"valuemapid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"newvalue", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5030043(void)
{
	return DBcreate_index("valuemap_mapping", "valuemap_mapping_1", "valuemapid,value", 1);
}

static int	DBpatch_5030044(void)
{
	const ZBX_FIELD	field = {"valuemapid", NULL, "valuemap", "valuemapid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("valuemap_mapping", 1, &field);
}

static int	DBpatch_5030045(void)
{
	return DBdrop_foreign_key("items", 3);
}

typedef struct
{
	zbx_uint64_t		valuemapid;
	char			*name;
	zbx_vector_ptr_pair_t	mappings;
}
zbx_valuemap_t;

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_uint64_t		valuemapid;
	zbx_vector_uint64_t	itemids;
}
zbx_host_t;

static int	host_compare_func(const void *d1, const void *d2)
{
	const zbx_host_t	*h1 = *(const zbx_host_t **)d1;
	const zbx_host_t	*h2 = *(const zbx_host_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->hostid, h2->hostid);
	ZBX_RETURN_IF_NOT_EQUAL(h1->valuemapid, h2->valuemapid);

	return 0;
}

static void	get_discovered_itemids(const zbx_vector_uint64_t *itemids, zbx_vector_uint64_t *discovered_itemids)
{
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select itemid from item_discovery where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_itemid", itemids->values, itemids->values_num);

	result = DBselect("%s", sql);
	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;

		ZBX_STR2UINT64(itemid, row[0]);

		zbx_vector_uint64_append(discovered_itemids, itemid);
	}
	DBfree_result(result);
}

static void	get_template_itemids_by_templateids(zbx_vector_uint64_t *templateids, zbx_vector_uint64_t *itemids,
		zbx_vector_uint64_t *discovered_itemids)
{
	DB_RESULT		result;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	zbx_vector_uint64_t	templateids_tmp;

	zbx_vector_uint64_create(&templateids_tmp);
	zbx_vector_uint64_append_array(&templateids_tmp, templateids->values, templateids->values_num);

	zbx_vector_uint64_clear(templateids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select i1.itemid"
			" from items i1"
				" where exists ("
					"select null"
					" from items i2"
					" where i2.templateid=i1.itemid"
				")"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i1.templateid", templateids_tmp.values,
			templateids_tmp.values_num);

	result = DBselect("%s", sql);
	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;

		ZBX_STR2UINT64(itemid, row[0]);

		zbx_vector_uint64_append(templateids, itemid);
	}
	DBfree_result(result);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select i2.itemid"
			" from items i1,item_discovery i2"
			" where i2.parent_itemid=i1.itemid"
			" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i1.templateid", templateids_tmp.values,
			templateids_tmp.values_num);

	result = DBselect("%s", sql);
	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;

		ZBX_STR2UINT64(itemid, row[0]);

		zbx_vector_uint64_append(discovered_itemids, itemid);
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&templateids_tmp);

	if (0 == templateids->values_num)
		return;

	zbx_vector_uint64_append_array(itemids, templateids->values, templateids->values_num);

	get_template_itemids_by_templateids(templateids, itemids, discovered_itemids);
}

static void	host_free(zbx_host_t *host)
{
	zbx_vector_uint64_destroy(&host->itemids);
	zbx_free(host);
}

static int	DBpatch_5030046(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_hashset_t		valuemaps;
	zbx_hashset_iter_t	iter;
	zbx_valuemap_t		valuemap_local, *valuemap;
	zbx_uint64_t		valuemapid;
	zbx_vector_ptr_t	hosts;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	templateids, discovered_itemids;
	zbx_db_insert_t		db_insert_valuemap, db_insert_valuemap_mapping;

	zbx_hashset_create(&valuemaps, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&hosts);
	zbx_vector_ptr_reserve(&hosts, 1000);

	zbx_vector_uint64_create(&templateids);
	zbx_vector_uint64_reserve(&templateids, 1000);

	zbx_vector_uint64_create(&discovered_itemids);
	zbx_vector_uint64_reserve(&discovered_itemids, 1000);

	result = DBselect(
			"select m.valuemapid,v.name,m.value,m.newvalue"
			" from valuemaps v"
			" left join mappings m on v.valuemapid=m.valuemapid");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_ptr_pair_t	pair;

		if (SUCCEED == DBis_null(row[0]))
		{
			zabbix_log(LOG_LEVEL_WARNING, "empty valuemap '%s' was removed", row[1]);
			continue;
		}

		ZBX_STR2UINT64(valuemap_local.valuemapid, row[0]);

		if (NULL == (valuemap = (zbx_valuemap_t *)zbx_hashset_search(&valuemaps, &valuemap_local)))
		{
			valuemap = zbx_hashset_insert(&valuemaps, &valuemap_local, sizeof(valuemap_local));
			valuemap->name = zbx_strdup(NULL, row[1]);
			zbx_vector_ptr_pair_create(&valuemap->mappings);
		}

		pair.first = zbx_strdup(NULL, row[2]);
		pair.second = zbx_strdup(NULL, row[3]);

		zbx_vector_ptr_pair_append(&valuemap->mappings, pair);
	}
	DBfree_result(result);

	result = DBselect("select h.flags,i.hostid,i.valuemapid,i.itemid"
			" from items i,hosts h"
			" where i.templateid is null"
				" and i.valuemapid is not null"
				" and i.flags in (0,2)"
				" and h.hostid=i.hostid");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_host_t	host_local, *host;
		zbx_uint64_t	itemid;
		unsigned char	flags;

		ZBX_STR2UCHAR(flags, row[0]);
		ZBX_STR2UINT64(host_local.hostid, row[1]);

		if (ZBX_FLAG_DISCOVERY_CREATED == flags)
			host_local.valuemapid = 0;
		else
			ZBX_STR2UINT64(host_local.valuemapid, row[2]);

		ZBX_STR2UINT64(itemid, row[3]);

		if (FAIL == (i = zbx_vector_ptr_search(&hosts, &host_local, host_compare_func)))
		{
			host = zbx_malloc(NULL, sizeof(zbx_host_t));
			host->hostid = host_local.hostid;
			host->valuemapid = host_local.valuemapid;
			zbx_vector_uint64_create(&host->itemids);

			zbx_vector_ptr_append(&hosts, host);
		}
		else
			host = (zbx_host_t *)hosts.values[i];

		zbx_vector_uint64_append(&host->itemids, itemid);
	}
	DBfree_result(result);

	zbx_db_insert_prepare(&db_insert_valuemap, "valuemap", "valuemapid", "hostid", "name", NULL);
	zbx_db_insert_prepare(&db_insert_valuemap_mapping, "valuemap_mapping", "valuemap_mappingid",
			"valuemapid", "value", "newvalue", NULL);

	for (i = 0, valuemapid = 0; i < hosts.values_num; i++)
	{
		zbx_host_t	*host;

		host = (zbx_host_t *)hosts.values[i];

		if (NULL != (valuemap = (zbx_valuemap_t *)zbx_hashset_search(&valuemaps, &host->valuemapid)))
		{
			zbx_db_insert_add_values(&db_insert_valuemap, ++valuemapid, host->hostid, valuemap->name);

			for (j = 0; j < valuemap->mappings.values_num; j++)
			{
				zbx_db_insert_add_values(&db_insert_valuemap_mapping, __UINT64_C(0), valuemapid,
						valuemap->mappings.values[j].first,
						valuemap->mappings.values[j].second);
			}
		}
	}

	zbx_db_insert_execute(&db_insert_valuemap);
	zbx_db_insert_clean(&db_insert_valuemap);

	zbx_db_insert_autoincrement(&db_insert_valuemap_mapping, "valuemap_mappingid");
	zbx_db_insert_execute(&db_insert_valuemap_mapping);
	zbx_db_insert_clean(&db_insert_valuemap_mapping);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0, valuemapid = 0; i < hosts.values_num; i++)
	{
		zbx_host_t	*host;
		char		buffer[MAX_STRING_LEN];

		host = (zbx_host_t *)hosts.values[i];

		if (NULL != zbx_hashset_search(&valuemaps, &host->valuemapid))
		{
			zbx_snprintf(buffer, sizeof(buffer), "update items set valuemapid=" ZBX_FS_UI64 " where",
					++valuemapid);
		}
		else
			zbx_strlcpy(buffer, "update items set valuemapid=null where", sizeof(buffer));

		/* update valuemapid for top level items on a template/host */
		zbx_vector_uint64_sort(&host->itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, buffer);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", host->itemids.values,
				host->itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		/* get discovered itemids for not templated item prototypes on a host */
		get_discovered_itemids(&host->itemids, &discovered_itemids);

		zbx_vector_uint64_append_array(&templateids, host->itemids.values, host->itemids.values_num);
		get_template_itemids_by_templateids(&templateids, &host->itemids, &discovered_itemids);

		/* make sure if multiple hosts are linked to same not nested template then there is only */
		/* update by templateid from template and no selection by numerous itemids               */
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, buffer);
		zbx_vector_uint64_sort(&host->itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "templateid", host->itemids.values,
				host->itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		if (0 != discovered_itemids.values_num)
		{
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, buffer);
			zbx_vector_uint64_sort(&discovered_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", discovered_itemids.values,
					discovered_itemids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
			zbx_vector_uint64_clear(&discovered_itemids);
		}
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	zbx_hashset_iter_reset(&valuemaps, &iter);
	while (NULL != (valuemap = (zbx_valuemap_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_free(valuemap->name);

		for (i = 0; i < valuemap->mappings.values_num; i++)
		{
			zbx_free(valuemap->mappings.values[i].first);
			zbx_free(valuemap->mappings.values[i].second);
		}
		zbx_vector_ptr_pair_destroy(&valuemap->mappings);
	}

	zbx_vector_ptr_clear_ext(&hosts, (zbx_clean_func_t)host_free);
	zbx_vector_ptr_destroy(&hosts);
	zbx_hashset_destroy(&valuemaps);

	zbx_vector_uint64_destroy(&templateids);
	zbx_vector_uint64_destroy(&discovered_itemids);

	return SUCCEED;
}

static int	DBpatch_5030047(void)
{
	const ZBX_FIELD	field = {"valuemapid", NULL, "valuemap", "valuemapid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("items", 3, &field);
}

static int	DBpatch_5030048(void)
{
	return DBdrop_table("mappings");
}

static int	DBpatch_5030049(void)
{
	return DBdrop_table("valuemaps");
}

static int	DBpatch_5030050(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where"
			" idx in ('web.valuemap.list.sort', 'web.valuemap.list.sortorder')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030051(void)
{
	return DBdrop_field("config", "compression_availability");
}

static int	DBpatch_5030052(void)
{
	return DBdrop_index("users", "users_1");
}

static int	DBpatch_5030053(void)
{
	const ZBX_FIELD	field = {"username", "", NULL, NULL, 100, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBrename_field("users", "alias", &field);
}

static int	DBpatch_5030054(void)
{
	return DBcreate_index("users", "users_1", "username", 1);
}

static int	DBpatch_5030055(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.user.filter_username' where idx='web.user.filter_alias'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030056(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set value_str='username' where idx='web.user.sort' and value_str like 'alias'"))
		return FAIL;

	return SUCCEED;
}

/* Patches and helper functions for ZBXNEXT-6368 */

static int	is_valid_opcommand_type(const char *type_str, const char *scriptid)
{
#define ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT	4	/* not used after upgrade */
	unsigned int	type;

	if (SUCCEED != is_uint31(type_str, &type))
		return FAIL;

	switch (type)
	{
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
		case ZBX_SCRIPT_TYPE_IPMI:
		case ZBX_SCRIPT_TYPE_SSH:
		case ZBX_SCRIPT_TYPE_TELNET:
			if (SUCCEED == DBis_null(scriptid))
				return SUCCEED;
			else
				return FAIL;
		case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
			if (FAIL == DBis_null(scriptid))
				return SUCCEED;
			else
				return FAIL;
		default:
			return FAIL;
	}
#undef ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT
}

static int	validate_types_in_opcommand(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	if (NULL == (result = DBselect("select operationid,type,scriptid from opcommand")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot select from table 'opcommand'", __func__);
		return FAIL;
	}

	while (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED != is_valid_opcommand_type(row[1], row[2]))
		{
			zabbix_log(LOG_LEVEL_CRIT, "%s(): invalid record in table \"opcommand\": operationid: %s"
					" type: %s scriptid: %s", __func__, row[0], row[1],
					(SUCCEED == DBis_null(row[2])) ? "value is NULL" : row[2]);
			ret = FAIL;
			break;
		}
	}

	DBfree_result(result);

	return ret;
}

static int	DBpatch_5030057(void)
{
	return validate_types_in_opcommand();
}

static int	DBpatch_5030058(void)
{
	const ZBX_FIELD	field = {"scope", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030059(void)
{
	const ZBX_FIELD	field = {"port", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030060(void)
{
	const ZBX_FIELD	field = {"authtype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030061(void)
{
	const ZBX_FIELD	field = {"username", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030062(void)
{
	const ZBX_FIELD	field = {"password", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030063(void)
{
	const ZBX_FIELD	field = {"publickey", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030064(void)
{
	const ZBX_FIELD	field = {"privatekey", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030065(void)
{
	const ZBX_FIELD	field = {"menu_path", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_5030066 (part of ZBXNEXT-6368)                           *
 *                                                                            *
 * Purpose: set value for 'scripts' table column 'scope' for existing global  *
 *          scripts                                                           *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments: 'scope' is set only for scripts which are NOT used in any action *
 *           operation. Otherwise the 'scope' default value is used, no need  *
 *           to modify it.                                                    *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_5030066(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update scripts set scope=%d"
			" where scriptid not in ("
			"select distinct scriptid"
			" from opcommand"
			" where scriptid is not null)", ZBX_SCRIPT_SCOPE_HOST))
	{
		return FAIL;
	}

	return SUCCEED;
}

static char	*zbx_rename_host_macros(const char *command)
{
	char	*p1, *p2, *p3, *p4, *p5, *p6, *p7;

	p1 = string_replace(command, "{HOST.CONN}", "{HOST.TARGET.CONN}");
	p2 = string_replace(p1, "{HOST.DNS}", "{HOST.TARGET.DNS}");
	p3 = string_replace(p2, "{HOST.HOST}", "{HOST.TARGET.HOST}");
	p4 = string_replace(p3, "{HOST.IP}", "{HOST.TARGET.IP}");
	p5 = string_replace(p4, "{HOST.NAME}", "{HOST.TARGET.NAME}");
	p6 = string_replace(p5, "{HOSTNAME}", "{HOST.TARGET.NAME}");
	p7 = string_replace(p6, "{IPADDRESS}", "{HOST.TARGET.IP}");

	zbx_free(p1);
	zbx_free(p2);
	zbx_free(p3);
	zbx_free(p4);
	zbx_free(p5);
	zbx_free(p6);

	return p7;
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_5030067 (part of ZBXNEXT-6368)                           *
 *                                                                            *
 * Purpose: rename some {HOST.*} macros to {HOST.TARGET.*} in existing global *
 *          scripts which are used in actions                                 *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_5030067(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	if (NULL == (result = DBselect("select scriptid,command"
			" from scripts"
			" where scriptid in (select distinct scriptid from opcommand where scriptid is not null)")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot select from table 'scripts'", __func__);
		return FAIL;
	}

	while (NULL != (row = DBfetch(result)))
	{
		char	*command, *command_esc;
		int	rc;

		command_esc = DBdyn_escape_field("scripts", "command", (command = zbx_rename_host_macros(row[1])));

		zbx_free(command);

		rc = DBexecute("update scripts set command='%s' where scriptid=%s", command_esc, row[0]);

		zbx_free(command_esc);

		if (ZBX_DB_OK > rc)
		{
			ret = FAIL;
			break;
		}
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_split_name  (part of ZBXNEXT-6368)                           *
 *                                                                            *
 * Purpose: helper function to split script name into menu_path and name      *
 *                                                                            *
 * Parameters:                                                                *
 *                name - [IN] old name                                        *
 *           menu_path - [OUT] menu path part, must be deallocated by caller  *
 *   name_without_path - [OUT] name, DO NOT deallocate in caller              *
 *                                                                            *
 ******************************************************************************/
static void	zbx_split_name(const char *name, char **menu_path, const char **name_without_path)
{
	char	*p;

	if (NULL == (p = strrchr(name, '/')))
		return;

	/* do not split if '/' is found at the beginning or at the end */
	if (name == p || '\0' == *(p + 1))
		return;

	*menu_path = zbx_strdup(*menu_path, name);

	p = *menu_path + (p - name);
	*p = '\0';
	*name_without_path = p + 1;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_make_script_name_unique  (part of ZBXNEXT-6368)              *
 *                                                                            *
 * Purpose: helper function to assist in making unique script names           *
 *                                                                            *
 * Parameters:                                                                *
 *            name - [IN] proposed name, to be tried first                    *
 *          suffix - [IN/OUT] numeric suffix to start from                    *
 *     unique_name - [OUT] unique name, must be deallocated by caller         *
 *                                                                            *
 * Return value: SUCCEED - unique name found, FAIL - DB error                 *
 *                                                                            *
 * Comments: pass initial suffix=0 to get "script ABC", "script ABC 2",       *
 *           "script ABC 3", ... .                                            *
 *           Pass initial suffix=1 to get "script ABC 1", "script ABC 2",     *
 *           "script ABC 3", ... .                                            *
 *                                                                            *
 ******************************************************************************/
static int	zbx_make_script_name_unique(const char *name, int *suffix, char **unique_name)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql, *try_name = NULL, *try_name_esc = NULL;

	while (1)
	{
		if (0 == *suffix)
		{
			try_name = zbx_strdup(NULL, name);
			(*suffix)++;
		}
		else
			try_name = zbx_dsprintf(try_name, "%s %d", name, *suffix);

		(*suffix)++;

		try_name_esc = DBdyn_escape_string(try_name);

		sql = zbx_dsprintf(NULL, "select scriptid from scripts where name='%s'", try_name_esc);

		zbx_free(try_name_esc);

		if (NULL == (result = DBselectN(sql, 1)))
		{
			zbx_free(try_name);
			zbx_free(sql);
			zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot select from table 'scripts'", __func__);
			return FAIL;
		}

		zbx_free(sql);

		if (NULL == (row = DBfetch(result)))
		{
			*unique_name = try_name;
			DBfree_result(result);
			return SUCCEED;
		}

		DBfree_result(result);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_5030068 (part of ZBXNEXT-6368)                           *
 *                                                                            *
 * Purpose: split script name between 'menu_path' and 'name' columns for      *
 *          existing global scripts                                           *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_5030068(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	if (NULL == (result = DBselect("select scriptid,name"
			" from scripts")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot select from table 'scripts'", __func__);
		return FAIL;
	}

	while (NULL != (row = DBfetch(result)))
	{
		const char	*scriptid = row[0];
		const char	*name = row[1];
		const char	*name_without_path;
		char		*menu_path = NULL, *menu_path_esc = NULL;
		char		*name_without_path_unique = NULL, *name_esc = NULL;
		int		rc, suffix = 0;

		zbx_split_name(name, &menu_path, &name_without_path);

		if (NULL == menu_path)
			continue;

		if (SUCCEED != zbx_make_script_name_unique(name_without_path, &suffix, &name_without_path_unique))
		{
			zbx_free(menu_path);
			ret = FAIL;
			break;
		}

		menu_path_esc = DBdyn_escape_string(menu_path);
		name_esc = DBdyn_escape_string(name_without_path_unique);

		rc = DBexecute("update scripts set menu_path='%s',name='%s' where scriptid=%s",
				menu_path_esc, name_esc, scriptid);

		zbx_free(name_esc);
		zbx_free(menu_path_esc);
		zbx_free(name_without_path_unique);
		zbx_free(menu_path);

		if (ZBX_DB_OK > rc)
		{
			ret = FAIL;
			break;
		}
	}

	DBfree_result(result);

	return ret;
}

typedef struct
{
	char	*command;
	char	*username;
	char	*password;
	char	*publickey;
	char	*privatekey;
	char	*type;
	char	*execute_on;
	char	*port;
	char	*authtype;
}
zbx_opcommand_parts_t;

typedef struct
{
	size_t		size;
	char		*record;
	zbx_uint64_t	scriptid;
}
zbx_opcommand_rec_t;

ZBX_VECTOR_DECL(opcommands, zbx_opcommand_rec_t)
ZBX_VECTOR_IMPL(opcommands, zbx_opcommand_rec_t)

/******************************************************************************
 *                                                                            *
 * Function: zbx_pack_record (part of ZBXNEXT-6368)                           *
 *                                                                            *
 * Purpose: helper function, packs parts of remote command into one memory    *
 *          chunk for efficient storing and comparing                         *
 *                                                                            *
 * Parameters:                                                                *
 *           parts - [IN] structure with all remote command components        *
 *   packed_record - [OUT] memory chunk with packed data. Must be deallocated *
 *                   by caller.                                               *
 *                                                                            *
 * Return value: size of memory chunk with the packed remote command          *
 *                                                                            *
 ******************************************************************************/
static size_t	zbx_pack_record(const zbx_opcommand_parts_t *parts, char **packed_record)
{
	size_t	size;
	char	*p, *p_end;

	size = strlen(parts->command) + strlen(parts->username) + strlen(parts->password) + strlen(parts->publickey) +
			strlen(parts->privatekey) + strlen(parts->type) + strlen(parts->execute_on) +
			strlen(parts->port) + strlen(parts->authtype) + 9; /* 9 terminating '\0' bytes for 9 parts */

	*packed_record = (char *)zbx_malloc(*packed_record, size);
	p = *packed_record;
	p_end = *packed_record + size;

	p += zbx_strlcpy(p, parts->command, size) + 1;
	p += zbx_strlcpy(p, parts->username, (size_t)(p_end - p)) + 1;
	p += zbx_strlcpy(p, parts->password, (size_t)(p_end - p)) + 1;
	p += zbx_strlcpy(p, parts->publickey, (size_t)(p_end - p)) + 1;
	p += zbx_strlcpy(p, parts->privatekey, (size_t)(p_end - p)) + 1;
	p += zbx_strlcpy(p, parts->type, (size_t)(p_end - p)) + 1;
	p += zbx_strlcpy(p, parts->execute_on, (size_t)(p_end - p)) + 1;
	p += zbx_strlcpy(p, parts->port, (size_t)(p_end - p)) + 1;
	p += zbx_strlcpy(p, parts->authtype, (size_t)(p_end - p)) + 1;

	return size;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_check_duplicate (part of ZBXNEXT-6368)                       *
 *                                                                            *
 * Purpose: checking if this remote command is a new one or a duplicate one   *
 *          and storing the assigned new global script id                     *
 *                                                                            *
 * Parameters:                                                                *
 *      opcommands - [IN] vector used for checking duplicates                 *
 *           parts - [IN] structure with all remote command components        *
 *           index - [OUT] index of vector element used to store information  *
 *                   about the remote command (either a new one or            *
 *                   an existing one)                                         *
 *                                                                            *
 * Return value: IS_NEW for new elements, IS_DUPLICATE for elements already   *
 *               seen                                                         *
 *                                                                            *
 ******************************************************************************/
#define IS_NEW		0
#define IS_DUPLICATE	1

static int	zbx_check_duplicate(zbx_vector_opcommands_t *opcommands,
		const zbx_opcommand_parts_t *parts, int *index)
{
	char			*packed_record = NULL;
	size_t			size;
	zbx_opcommand_rec_t	elem;
	int			i;

	size = zbx_pack_record(parts, &packed_record);

	for (i = 0; i < opcommands->values_num; i++)
	{
		if (size == opcommands->values[i].size &&
				0 == memcmp(opcommands->values[i].record, packed_record, size))
		{
			zbx_free(packed_record);
			*index = i;
			return IS_DUPLICATE;
		}
	}

	elem.size = size;
	elem.record = packed_record;
	elem.scriptid = 0;
	zbx_vector_opcommands_append(opcommands, elem);
	*index = opcommands->values_num - 1;

	return IS_NEW;
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_5030069   (part of ZBXNEXT-6368)                         *
 *                                                                            *
 * Purpose: migrate remote commands from table 'opcommand' to table 'scripts' *
 *          and convert them into global scripts                              *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_5030069(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			ret = SUCCEED, i, suffix = 1;
	zbx_vector_opcommands_t	opcommands;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	zbx_vector_opcommands_create(&opcommands);

	if (NULL == (result = DBselect("select command,username,password,publickey,privatekey,type,execute_on,port,"
			"authtype,operationid"
			" from opcommand"
			" where scriptid is null"
			" order by command,username,password,publickey,privatekey,type,execute_on,port,authtype")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot select from table 'opcommand'", __func__);
		zbx_vector_opcommands_destroy(&opcommands);

		return FAIL;
	}

	while (NULL != (row = DBfetch(result)))
	{
		char			*operationid;
		int			index;
		zbx_opcommand_parts_t	parts;

		parts.command = row[0];
		parts.username = row[1];
		parts.password = row[2];
		parts.publickey = row[3];
		parts.privatekey = row[4];
		parts.type = row[5];
		parts.execute_on = row[6];
		parts.port = row[7];
		parts.authtype = row[8];
		operationid = row[9];

		if (IS_NEW == zbx_check_duplicate(&opcommands, &parts, &index))
		{
			char		*script_name = NULL, *script_name_esc;
			char		*command_esc, *port_esc, *username_esc;
			char		*password_esc, *publickey_esc, *privatekey_esc;
			zbx_uint64_t	scriptid, type, execute_on, authtype, operationid_num;
			int		rc;

			if (SUCCEED != zbx_make_script_name_unique("Script", &suffix, &script_name))
			{
				ret = FAIL;
				break;
			}

			scriptid = DBget_maxid("scripts");

			ZBX_DBROW2UINT64(type, parts.type);
			ZBX_DBROW2UINT64(execute_on, parts.execute_on);
			ZBX_DBROW2UINT64(authtype, parts.authtype);
			ZBX_DBROW2UINT64(operationid_num, operationid);

			script_name_esc = DBdyn_escape_string(script_name);
			command_esc = DBdyn_escape_string(parts.command);
			port_esc = DBdyn_escape_string(parts.port);
			username_esc = DBdyn_escape_string(parts.username);
			password_esc = DBdyn_escape_string(parts.password);
			publickey_esc = DBdyn_escape_string(parts.publickey);
			privatekey_esc = DBdyn_escape_string(parts.privatekey);

			zbx_free(script_name);

			rc = DBexecute("insert into scripts (scriptid,name,command,description,type,execute_on,scope,"
					"port,authtype,username,password,publickey,privatekey) values ("
					ZBX_FS_UI64 ",'%s','%s',''," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s',"
					ZBX_FS_UI64 ",'%s','%s','%s','%s')",
					scriptid, script_name_esc, command_esc, type, execute_on,
					ZBX_SCRIPT_SCOPE_ACTION, port_esc, authtype,
					username_esc, password_esc, publickey_esc, privatekey_esc);

			zbx_free(privatekey_esc);
			zbx_free(publickey_esc);
			zbx_free(password_esc);
			zbx_free(username_esc);
			zbx_free(port_esc);
			zbx_free(command_esc);
			zbx_free(script_name_esc);

			if (ZBX_DB_OK > rc || ZBX_DB_OK > DBexecute("update opcommand set scriptid=" ZBX_FS_UI64
						" where operationid=" ZBX_FS_UI64, scriptid, operationid_num))
			{
				ret = FAIL;
				break;
			}

			opcommands.values[index].scriptid = scriptid;
		}
		else	/* IS_DUPLICATE */
		{
			zbx_uint64_t	scriptid;

			/* link to a previously migrated script */
			scriptid = opcommands.values[index].scriptid;

			if (ZBX_DB_OK > DBexecute("update opcommand set scriptid=" ZBX_FS_UI64
					" where operationid=%s", scriptid, operationid))
			{
				ret = FAIL;
				break;
			}
		}
	}

	DBfree_result(result);

	for (i = 0; i < opcommands.values_num; i++)
		zbx_free(opcommands.values[i].record);

	zbx_vector_opcommands_destroy(&opcommands);

	return ret;
}
#undef IS_NEW
#undef IS_DUPLICATE

static int	DBpatch_5030070(void)
{
	const ZBX_FIELD field = {"scriptid", NULL, "scripts","scriptid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("opcommand", &field);
}

static int	DBpatch_5030071(void)
{
	return DBdrop_field("opcommand", "execute_on");
}

static int	DBpatch_5030072(void)
{
	return DBdrop_field("opcommand", "port");
}

static int	DBpatch_5030073(void)
{
	return DBdrop_field("opcommand", "authtype");
}

static int	DBpatch_5030074(void)
{
	return DBdrop_field("opcommand", "username");
}

static int	DBpatch_5030075(void)
{
	return DBdrop_field("opcommand", "password");
}

static int	DBpatch_5030076(void)
{
	return DBdrop_field("opcommand", "publickey");
}

static int	DBpatch_5030077(void)
{
	return DBdrop_field("opcommand", "privatekey");
}

static int	DBpatch_5030078(void)
{
	return DBdrop_field("opcommand", "command");
}

static int	DBpatch_5030079(void)
{
	return DBdrop_field("opcommand", "type");
}

static int	DBpatch_5030080(void)
{
	const ZBX_FIELD	old_field = {"command", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"command", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_remote_command", &field, &old_field);
}
/*  end of ZBXNEXT-6368 patches */

/* trigger function conversion to new syntax */

#define ZBX_DBPATCH_FUNCTION_UPDATE_NAME		0x01
#define ZBX_DBPATCH_FUNCTION_UPDATE_PARAM		0x02
#define ZBX_DBPATCH_FUNCTION_UPDATE			(ZBX_DBPATCH_FUNCTION_UPDATE_NAME | \
							ZBX_DBPATCH_FUNCTION_UPDATE_PARAM)

#define ZBX_DBPATCH_FUNCTION_CREATE			0x40
#define ZBX_DBPATCH_FUNCTION_DELETE			0x80

#define ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION		0x01
#define ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION	0x02

#define ZBX_DBPATCH_TRIGGER_UPDATE			(ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION | \
							ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION)

/* Function argument descriptors.                                                */
/* Used in varargs list to describe following parameter mapping to old position. */
/* Terminated with ZBX_DBPATCH_ARG_NONE.                                         */
/* For example:                                                                  */
/* ..., ZBX_DBPATCH_ARG_NUM, 1, ZBX_DBPATCH_ARG_STR, 0, ZBX_DBPATCH_ARG_NONE)    */
/*  meaning first numeric parameter copied from second parameter                 */
/*          second string parameter copied from first parameter                  */
typedef enum
{
	ZBX_DBPATCH_ARG_NONE,		/* terminating descriptor, must be put at the end of the list */
	ZBX_DBPATCH_ARG_HIST,		/* history period followed by sec/num (int) and timeshift (int) indexes */
	ZBX_DBPATCH_ARG_TIME,		/* time value followed by argument index (int)  */
	ZBX_DBPATCH_ARG_NUM,		/* number value followed by argument index (int)  */
	ZBX_DBPATCH_ARG_STR,		/* string value  followed by argument index (int)  */
	ZBX_DBPATCH_ARG_TREND,		/* trend period, followed by period (int) and timeshift (int) indexes */
	ZBX_DBPATCH_ARG_CONST_STR,	/* constant,fffffff followed by string (char *) value */
}
zbx_dbpatch_arg_t;

ZBX_VECTOR_DECL(loc, zbx_strloc_t)
ZBX_VECTOR_IMPL(loc, zbx_strloc_t)

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	itemid;

	/* hostid for time based functions to track associated            */
	/* hosts when replacing with history function with common function */
	zbx_uint64_t	hostid;

	/* function location - expression|recovery expression */
	unsigned char	location;

	char		*name;
	char		*parameter;

	/* the first parameter (host:key) for calculated item */
	/* formula functions, NULL for trigger functions      */
	char		*arg0;

	unsigned char	flags;
}
zbx_dbpatch_function_t;

typedef struct
{
	zbx_uint64_t	triggerid;
	unsigned char	recovery_mode;
	unsigned char	flags;
	char		*expression;
	char		*recovery_expression;
}
zbx_dbpatch_trigger_t;

static void	dbpatch_function_free(zbx_dbpatch_function_t *func)
{
	zbx_free(func->name);
	zbx_free(func->parameter);
	zbx_free(func->arg0);
	zbx_free(func);
}

static void	dbpatch_trigger_clear(zbx_dbpatch_trigger_t *trigger)
{
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
}

static zbx_dbpatch_function_t	*dbpatch_new_function(zbx_uint64_t functionid, zbx_uint64_t itemid, const char *name,
		const char *parameter, unsigned char flags)
{
	zbx_dbpatch_function_t	*func;

	func = (zbx_dbpatch_function_t *)zbx_malloc(NULL, sizeof(zbx_dbpatch_function_t));
	func->functionid = functionid;
	func->itemid = itemid;
	func->name = (NULL != name ? zbx_strdup(NULL, name) : NULL);
	func->parameter = (NULL != parameter ? zbx_strdup(NULL, parameter) : NULL);
	func->flags = flags;
	func->arg0 = NULL;

	return func;
}

static void	dbpatch_add_function(const zbx_dbpatch_function_t *template, zbx_uint64_t functionid, const char *name,
		const char *parameter, unsigned char flags, zbx_vector_ptr_t *functions)
{
	zbx_dbpatch_function_t	*func;

	func = dbpatch_new_function(functionid, template->itemid, name, parameter, flags);
	func->arg0 = (NULL != template->arg0 ? zbx_strdup(NULL, template->arg0) : NULL);

	zbx_vector_ptr_append(functions, func);
}

static void	dbpatch_update_function(zbx_dbpatch_function_t *func, const char *name,
		const char *parameter, unsigned char flags)
{
	if (0 != (flags & ZBX_DBPATCH_FUNCTION_UPDATE_NAME))
		func->name = zbx_strdup(func->name, name);

	if (0 != (flags & ZBX_DBPATCH_FUNCTION_UPDATE_PARAM))
		func->parameter = zbx_strdup(func->parameter, parameter);

	func->flags = flags;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_update_expression                                        *
 *                                                                            *
 * Purpose: replace {functionid} occurrences in expression with the specified *
 *          replacement string                                                *
 *                                                                            *
 * Return value: SUCCEED - expression was changed                             *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	dbpatch_update_expression(char **expression, zbx_uint64_t functionid, const char *replace)
{
	int		pos = 0, last_pos = 0;
	zbx_token_t	token;
	char		*out = NULL;
	size_t		out_alloc = 0, out_offset = 0;
	zbx_uint64_t	id;

	for (; SUCCEED == zbx_token_find(*expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID); pos++)
	{
		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
				if (SUCCEED == is_uint64_n(*expression + token.data.objectid.name.l,
						token.data.objectid.name.r - token.data.objectid.name.l + 1, &id) &&
						functionid == id)
				{
					zbx_strncpy_alloc(&out, &out_alloc, &out_offset,
							*expression + last_pos, token.loc.l - last_pos);
					zbx_strcpy_alloc(&out, &out_alloc, &out_offset, replace);
					last_pos = token.loc.r + 1;
				}
				pos = token.loc.r;
				break;
			case ZBX_TOKEN_MACRO:
			case ZBX_TOKEN_USER_MACRO:
			case ZBX_TOKEN_LLD_MACRO:
				pos = token.loc.r;
				break;
		}
	}

	if (NULL == out)
		return FAIL;

	zbx_strcpy_alloc(&out, &out_alloc, &out_offset, *expression + last_pos);

	zbx_free(*expression);
	*expression = out;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_update_trigger                                           *
 *                                                                            *
 * Purpose: replace {functionid} occurrences in trigger expression and        *
 *          recovery expression with the specified replacement string         *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_update_trigger(zbx_dbpatch_trigger_t *trigger, zbx_uint64_t functionid, const char *replace)
{
	if (SUCCEED == dbpatch_update_expression(&trigger->expression, functionid, replace))
		trigger->flags |= ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION;

	if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == trigger->recovery_mode)
	{
		if (SUCCEED == dbpatch_update_expression(&trigger->recovery_expression, functionid, replace))
			trigger->flags |= ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION;
	}
}

static void	dbpatch_update_func_abschange(zbx_dbpatch_function_t *function, char **replace)
{
	dbpatch_update_function(function, "change", "", ZBX_DBPATCH_FUNCTION_UPDATE);
	*replace = zbx_dsprintf(NULL, "abs({" ZBX_FS_UI64 "})", function->functionid);
}

static void	dbpatch_update_func_delta(zbx_dbpatch_function_t *function, const char *parameter, char **replace,
		zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;

	dbpatch_update_function(function, "max", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);

	functionid2 = (NULL == function->arg0 ? DBget_maxid("functions") : (zbx_uint64_t)functions->values_num);
	dbpatch_add_function(function, functionid2, "min", parameter, ZBX_DBPATCH_FUNCTION_CREATE, functions);

	*replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}-{" ZBX_FS_UI64 "})", function->functionid, functionid2);
}

static void	dbpatch_update_func_diff(zbx_dbpatch_function_t *function, char **replace, zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;

	dbpatch_update_function(function, "last", "#1", ZBX_DBPATCH_FUNCTION_UPDATE);

	functionid2 = (NULL == function->arg0 ? DBget_maxid("functions") : (zbx_uint64_t)functions->values_num);
	dbpatch_add_function(function, functionid2, "last", "#2", ZBX_DBPATCH_FUNCTION_CREATE, functions);

	*replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}<>{" ZBX_FS_UI64 "})", function->functionid, functionid2);
}

static void	dbpatch_update_func_trenddelta(zbx_dbpatch_function_t *function, const char *parameter, char **replace,
		zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;

	dbpatch_update_function(function, "trendmax", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);

	functionid2 = (NULL == function->arg0 ? DBget_maxid("functions") : (zbx_uint64_t)functions->values_num);
	dbpatch_add_function(function, functionid2, "trendmin", parameter, ZBX_DBPATCH_FUNCTION_CREATE, functions);

	*replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}-{" ZBX_FS_UI64 "})", function->functionid, functionid2);
}

static void	dbpatch_update_func_strlen(zbx_dbpatch_function_t *function, const char *parameter, char **replace)
{
	dbpatch_update_function(function, "last", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);

	*replace = zbx_dsprintf(NULL, "length({" ZBX_FS_UI64 "})", function->functionid);
}

static void	dbpatch_update_hist2common(zbx_dbpatch_function_t *function, int extended, char **expression)
{
	char	*str  = NULL;
	size_t	str_alloc = 0, str_offset = 0;

	if (ZBX_DBPATCH_FUNCTION_DELETE == function->flags)
		dbpatch_update_function(function, "last", "$", ZBX_DBPATCH_FUNCTION_UPDATE);

	if (0 == extended)
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, '(');
	zbx_strcpy_alloc(&str, &str_alloc, &str_offset, *expression);
	if (0 == extended)
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, ')');

	zbx_snprintf_alloc(&str, &str_alloc, &str_offset, " or ({" ZBX_FS_UI64 "}<>{" ZBX_FS_UI64 "})",
			function->functionid, function->functionid);

	zbx_free(*expression);
	*expression = str;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_parse_function_params                                    *
 *                                                                            *
 * Purpose: parse function parameter string into parameter location vector    *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_parse_function_params(const char *parameter, zbx_vector_loc_t *params)
{
	const char	*ptr;
	size_t		len, pos, sep = 0, eol;
	zbx_strloc_t	loc;

	eol = strlen(parameter);

	for (ptr = parameter; ptr < parameter + eol; ptr += sep + 1)
	{
		zbx_function_param_parse(ptr, &pos, &len, &sep);

		if (0 < len)
		{
			loc.l = ptr - parameter + pos;
			loc.r = loc.l + len - 1;
		}
		else
		{
			loc.l = ptr - parameter + eol - (ptr - parameter);
			loc.r = loc.l;
		}

		zbx_vector_loc_append_ptr(params, &loc);
	}

	while (0 < params->values_num && '\0' == parameter[params->values[params->values_num - 1].l])
		--params->values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_convert_params                                           *
 *                                                                            *
 * Purpose: convert function parameters into new syntax                       *
 *                                                                            *
 * Parameters: out       - [OUT] the converted parameter string               *
 *             parameter - [IN] the original parameter string                 *
 *             params    - [IN] the parameter locations in original parameter *
 *                              string                                        *
 *             ...       - list of parameter descriptors with parameter data  *
 *                         (see zbx_dbpatch_arg_t enum for parameter list     *
 *                         description)                                       *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_convert_params(char **out, const char *parameter, const zbx_vector_loc_t *params, ...)
{
	size_t			out_alloc = 0, out_offset = 0;
	va_list 		args;
	int			index, type, param_num = 0;
	const zbx_strloc_t	*loc;
	const char		*ptr;
	char			*arg;

	va_start(args, params);

	while (ZBX_DBPATCH_ARG_NONE != (type = va_arg(args, int)))
	{
		if (0 != param_num++)
			zbx_chrcpy_alloc(out, &out_alloc, &out_offset, ',');

		switch (type)
		{
			case ZBX_DBPATCH_ARG_HIST:
				if (-1 != (index = va_arg(args, int)) && index < params->values_num)
				{
					loc = &params->values[index];
					arg = zbx_substr_unquote(parameter, loc->l, loc->r);

					if ('\0' != *arg)
					{
						zbx_strcpy_alloc(out, &out_alloc, &out_offset, arg);

						if ('#' != *arg && 0 != isdigit(arg[strlen(arg) - 1]))
							zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
					}

					zbx_free(arg);
				}

				if (-1 != (index = va_arg(args, int)) && index < params->values_num)
				{
					loc = &params->values[index];
					arg = zbx_substr_unquote(parameter, loc->l, loc->r);

					if ('\0' != *arg)
					{
						if (0 == out_offset)
							zbx_strcpy_alloc(out, &out_alloc, &out_offset, "#1");

						zbx_strcpy_alloc(out, &out_alloc, &out_offset, ":now-");
						zbx_strcpy_alloc(out, &out_alloc, &out_offset, arg);
						if (0 != isdigit(arg[strlen(arg) - 1]))
							zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
					}

					zbx_free(arg);
				}

				break;
			case ZBX_DBPATCH_ARG_TIME:
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_substr_unquote(parameter, loc->l, loc->r);
					if ('\0' != *str)
					{
						zbx_strcpy_alloc(out, &out_alloc, &out_offset, str);
						if (0 != isdigit(str[strlen(str) - 1]))
							zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
					}
					zbx_free(str);
				}
				break;
			case ZBX_DBPATCH_ARG_NUM:
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_substr_unquote(parameter, loc->l, loc->r);
					zbx_strcpy_alloc(out, &out_alloc, &out_offset, str);
					zbx_free(str);
				}
				break;
			case ZBX_DBPATCH_ARG_STR:
				if (params->values_num > (index = va_arg(args, int)))
				{
					loc = &params->values[index];
					if ('"' == parameter[loc->l])
					{
						zbx_strncpy_alloc(out, &out_alloc, &out_offset, parameter + loc->l,
								loc->r - loc->l + 1);
					}
					else if ('\0' != parameter[loc->l])
					{
						char	raw[FUNCTION_PARAM_LEN * 4 + 1], quoted[sizeof(raw)];

						zbx_strlcpy(raw, parameter + loc->l, loc->r - loc->l + 2);
						zbx_escape_string(quoted, sizeof(quoted), raw, "\"\\");
						zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
						zbx_strcpy_alloc(out, &out_alloc, &out_offset, quoted);
						zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
					}
				}
				break;
			case ZBX_DBPATCH_ARG_TREND:
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_substr_unquote(parameter, loc->l, loc->r);
					zbx_strcpy_alloc(out, &out_alloc, &out_offset, str);
					zbx_free(str);
				}
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_substr_unquote(parameter, loc->l, loc->r);
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, ':');
					zbx_strcpy_alloc(out, &out_alloc, &out_offset, str);
					zbx_free(str);
				}
				break;
			case ZBX_DBPATCH_ARG_CONST_STR:
				if (NULL != (ptr = va_arg(args, char *)))
				{
					char	quoted[MAX_STRING_LEN];

					zbx_escape_string(quoted, sizeof(quoted), ptr, "\"\\");
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
					zbx_strcpy_alloc(out, &out_alloc, &out_offset, quoted);
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
				}
				break;
		}
	}

	va_end(args);

	if (0 != out_offset)
	{
		/* trim trailing empty parameters */
		while (0 < out_offset && ',' == (*out)[out_offset - 1])
			(*out)[--out_offset] = '\0';
	}
	else
		*out = zbx_strdup(*out, "");
}

static void	dbpatch_update_func_bitand(zbx_dbpatch_function_t *function, const zbx_vector_loc_t *params,
		char **replace)
{
	char	*parameter = NULL, *mask = NULL;

	if (2 <= params->values_num && '\0' != function->parameter[params->values[1].l])
	{
		mask = zbx_substr_unquote(function->parameter, params->values[1].l, params->values[1].r);
		*replace = zbx_dsprintf(NULL, "bitand({" ZBX_FS_UI64 "},%s)", function->functionid, mask);
		zbx_free(mask);
	}
	else
		*replace = zbx_dsprintf(NULL, "bitand({" ZBX_FS_UI64 "})", function->functionid);

	dbpatch_convert_params(&parameter, function->parameter, params,
			ZBX_DBPATCH_ARG_HIST, 0, 2,
			ZBX_DBPATCH_ARG_NONE);

	dbpatch_update_function(function, "last", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);

	zbx_free(parameter);
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_convert_function                                         *
 *                                                                            *
 * Purpose: convert function to new parameter syntax/order                    *
 *                                                                            *
 * Parameters: function   - [IN/OUT] the function to convert                  *
 *             replace    - [OUT] the replacement for {functionid} in the     *
 *                          expression                                        *
 *             functions  - [IN/OUT] the functions                            *
 *                                                                            *
 * Comments: The function conversion can result in another function being     *
 *           added.                                                           *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_convert_function(zbx_dbpatch_function_t *function, char **replace, zbx_vector_ptr_t *functions)
{
	zbx_vector_loc_t	params;
	char			*parameter = NULL;

	zbx_vector_loc_create(&params);

	dbpatch_parse_function_params(function->parameter, &params);

	if (0 == strcmp(function->name, "abschange"))
	{
		dbpatch_update_func_abschange(function, replace);
	}
	else if (0 == strcmp(function->name, "change"))
	{
		dbpatch_update_function(function, NULL, "", ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "avg") || 0 == strcmp(function->name, "max") ||
			0 == strcmp(function->name, "min") || 0 == strcmp(function->name, "sum"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "delta"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_func_delta(function, parameter, replace, functions);
	}
	else if (0 == strcmp(function->name, "diff"))
	{
		dbpatch_update_func_diff(function, replace, functions);
	}
	else if (0 == strcmp(function->name, "fuzzytime"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_TIME, 0,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "nodata"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_TIME, 0,
				ZBX_DBPATCH_ARG_STR, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "percentile"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NUM, 2,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "trendavg") || 0 == strcmp(function->name, "trendmin") ||
			0 == strcmp(function->name, "trendmax") || 0 == strcmp(function->name, "trendsum") ||
			0 == strcmp(function->name, "trendcount"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_TREND, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "trenddelta"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_TREND, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_func_trenddelta(function, parameter, replace, functions);
	}
	else if (0 == strcmp(function->name, "band"))
	{
		dbpatch_update_func_bitand(function, &params, replace);
	}
	else if (0 == strcmp(function->name, "forecast"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_TIME, 2,
				ZBX_DBPATCH_ARG_STR, 3,
				ZBX_DBPATCH_ARG_STR, 4,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "timeleft"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NUM, 2,
				ZBX_DBPATCH_ARG_STR, 3,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "count"))
	{
		char	*op = NULL;

		if (2 <= params.values_num)
		{
			if (3 <= params.values_num && '\0' != function->parameter[params.values[2].l])
			{
				op = zbx_substr_unquote(function->parameter, params.values[2].l, params.values[2].r);

				if (0 == strcmp(op, "band"))
					op = zbx_strdup(op, "bitand");
				else if ('\0' == *op && '"' != function->parameter[params.values[2].l])
					zbx_free(op);
			}
		}

		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 3,
				ZBX_DBPATCH_ARG_CONST_STR, op,
				ZBX_DBPATCH_ARG_STR, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);

		zbx_free(op);
	}
	else if (0 == strcmp(function->name, "iregexp") || 0 == strcmp(function->name, "regexp"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 1, -1,
				ZBX_DBPATCH_ARG_CONST_STR, function->name,
				ZBX_DBPATCH_ARG_STR, 0,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, "find", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);
	}
	else if (0 == strcmp(function->name, "str"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 1, -1,
				ZBX_DBPATCH_ARG_CONST_STR, "like",
				ZBX_DBPATCH_ARG_STR, 0,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, "find", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);
	}
	else if (0 == strcmp(function->name, "last"))
	{
		int	secnum = 0;

		if (0 < params.values_num && '#' != function->parameter[params.values[0].l])
			secnum = -1;

		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, secnum, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "prev"))
	{
		dbpatch_update_function(function, "last", "#2", ZBX_DBPATCH_FUNCTION_UPDATE);
	}
	else if (0 == strcmp(function->name, "strlen"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_func_strlen(function, parameter, replace);
	}
	else if (0 == strcmp(function->name, "logeventid") || 0 == strcmp(function->name, "logsource"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_STR, 0,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "logseverity"))
	{
		dbpatch_update_function(function, NULL, "", ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}

	zbx_free(parameter);
	zbx_vector_loc_destroy(&params);
}

static int	dbpatch_is_time_function(const char *name, size_t len)
{
	const char	*functions[] = {"date", "dayofmonth", "dayofweek", "now", "time", NULL}, **func;
	size_t		func_len;

	for (func = functions; NULL != *func; func++)
	{
		func_len = strlen(*func);
		if (func_len == len && 0 == memcmp(*func, name, len))
			return SUCCEED;
	}

	return FAIL;
}

#define ZBX_DBPATCH_EXPRESSION			0x01
#define ZBX_DBPATCH_RECOVERY_EXPRESSION		0x02

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_find_function                                            *
 *                                                                            *
 * Purpose: check if the expression contains specified functionid             *
 *                                                                            *
 ******************************************************************************/
static	int	dbpatch_find_function(const char *expression, zbx_uint64_t functionid)
{
	int		pos = 0;
	zbx_token_t	token;
	zbx_uint64_t	id;

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID); pos++)
	{
		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
				if (SUCCEED == is_uint64_n(expression + token.data.objectid.name.l,
						token.data.objectid.name.r - token.data.objectid.name.l + 1, &id) &&
						functionid == id)
				{
					return SUCCEED;
				}
				pos = token.loc.r;
				break;
			case ZBX_TOKEN_MACRO:
			case ZBX_TOKEN_USER_MACRO:
			case ZBX_TOKEN_LLD_MACRO:
				pos = token.loc.r;
				break;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_get_function_location                                    *
 *                                                                            *
 * Purpose: return function location mask (expression | recovery expression)  *
 *                                                                            *
 ******************************************************************************/
static unsigned char	dbpatch_get_function_location(const zbx_dbpatch_trigger_t *trigger, zbx_uint64_t functionid)
{
	unsigned char	mask = 0;

	if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION != trigger->recovery_mode)
		return ZBX_DBPATCH_EXPRESSION;

	if (SUCCEED == dbpatch_find_function(trigger->expression, functionid))
		mask |= ZBX_DBPATCH_EXPRESSION;

	if (SUCCEED == dbpatch_find_function(trigger->recovery_expression, functionid))
		mask |= ZBX_DBPATCH_RECOVERY_EXPRESSION;

	return mask;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_convert_trigger                                          *
 *                                                                            *
 * Purpose: convert trigger and its functions to use new expression syntax    *
 *                                                                            *
 * Parameters: trigger   - [IN/OUT] the trigger data/updates                  *
 *             functions - [OUT] the function updates                         *
 *                                                                            *
 ******************************************************************************/
static int	dbpatch_convert_trigger(zbx_dbpatch_trigger_t *trigger, zbx_vector_ptr_t *functions)
{
	DB_ROW				row;
	DB_RESULT			result;
	int				i, index;
	zbx_uint64_t			functionid, itemid, hostid;
	zbx_vector_loc_t		params;
	zbx_vector_ptr_t		common_functions, trigger_functions;
	zbx_vector_uint64_t		hostids, r_hostids;
	zbx_dbpatch_function_t		*func;

	index = functions->values_num;

	zbx_vector_loc_create(&params);
	zbx_vector_ptr_create(&common_functions);
	zbx_vector_ptr_create(&trigger_functions);
	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&r_hostids);

	result = DBselect("select f.functionid,f.itemid,f.name,f.parameter,h.hostid"
			" from functions f"
			" join items i"
				" on f.itemid=i.itemid"
			" join hosts h"
				" on i.hostid=h.hostid"
			" where triggerid=" ZBX_FS_UI64
			" order by functionid",
			trigger->triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		char		*replace = NULL;
		unsigned char	location;

		ZBX_STR2UINT64(functionid, row[0]);
		ZBX_STR2UINT64(itemid, row[1]);
		ZBX_STR2UINT64(hostid, row[4]);

		if (SUCCEED == dbpatch_is_time_function(row[2], strlen(row[2])))
		{
			char	func_name[FUNCTION_NAME_LEN * 4 + 1];

			func = dbpatch_new_function(functionid, itemid, row[2], row[3], 0);
			func->hostid = hostid;
			func->location = dbpatch_get_function_location(trigger, functionid);
			zbx_vector_ptr_append(&common_functions, func);

			zbx_snprintf(func_name, sizeof(func_name), "%s()", row[2]);
			dbpatch_update_trigger(trigger, functionid, func_name);

			continue;
		}

		location = dbpatch_get_function_location(trigger, functionid);

		if (0 != (location & ZBX_DBPATCH_EXPRESSION))
			zbx_vector_uint64_append(&hostids, hostid);

		if (0 != (location & ZBX_DBPATCH_RECOVERY_EXPRESSION))
			zbx_vector_uint64_append(&r_hostids, hostid);

		func = dbpatch_new_function(functionid, itemid, row[2], row[3], 0);
		zbx_vector_ptr_append(functions, func);
		dbpatch_convert_function(func, &replace, functions);

		if (NULL != replace)
		{
			dbpatch_update_trigger(trigger, func->functionid, replace);
			zbx_free(replace);
		}
	}
	DBfree_result(result);

	for (i = index; i < functions->values_num; i++)
	{
		func = (zbx_dbpatch_function_t *)functions->values[i];

		if (0 == (func->flags & ZBX_DBPATCH_FUNCTION_DELETE) && NULL != func->parameter)
		{
			if ('\0' != *func->parameter)
				func->parameter = zbx_dsprintf(func->parameter, "$,%s", func->parameter);
			else
				func->parameter = zbx_strdup(func->parameter, "$");

			func->flags |= ZBX_DBPATCH_FUNCTION_UPDATE_PARAM;
		}
	}

	/* ensure that with history time functions converted to common time functions */
	/* the trigger is still linked to the same hosts                              */
	if (0 != common_functions.values_num)
	{
		int	extended = 0, r_extended = 0;

		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_vector_uint64_sort(&r_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&r_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (i = 0; i < common_functions.values_num; i++)
		{
			func = (zbx_dbpatch_function_t *)common_functions.values[i];
			func->flags = ZBX_DBPATCH_FUNCTION_DELETE;

			if (0 != (func->location & ZBX_DBPATCH_EXPRESSION) &&
					(FAIL == zbx_vector_uint64_search(&hostids, func->hostid,
							ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				dbpatch_update_hist2common(func, extended, &trigger->expression);
				extended = 1;
				zbx_vector_uint64_append(&hostids, func->hostid);
				trigger->flags |= ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION;
			}

			if (0 != (func->location & ZBX_DBPATCH_RECOVERY_EXPRESSION) &&
					(FAIL == zbx_vector_uint64_search(&r_hostids, func->hostid,
							ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				dbpatch_update_hist2common(func, r_extended, &trigger->recovery_expression);
				r_extended = 1;
				zbx_vector_uint64_append(&r_hostids, func->hostid);
				trigger->flags |= ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION;
			}

			zbx_vector_ptr_append(functions, func);
		}
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_uint64_destroy(&r_hostids);
	zbx_vector_ptr_destroy(&trigger_functions);
	zbx_vector_ptr_destroy(&common_functions);
	zbx_vector_loc_destroy(&params);

	if (0 != (trigger->flags & ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION))
	{
		if (zbx_strlen_utf8(trigger->expression) > TRIGGER_EXPRESSION_LEN)
		{
			zabbix_log(LOG_LEVEL_WARNING, "trigger \"" ZBX_FS_UI64 "\" expression is too long: %s",
					trigger->triggerid, trigger->expression);
			return FAIL;
		}
	}

	if (0 != (trigger->flags & ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION))
	{
		if (zbx_strlen_utf8(trigger->recovery_expression) > TRIGGER_EXPRESSION_LEN)
		{
			zabbix_log(LOG_LEVEL_WARNING, "trigger \"" ZBX_FS_UI64 "\" recovery expression is too long: %s",
					trigger->triggerid, trigger->recovery_expression);
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_5030081(void)
{
	int			i, ret = SUCCEED;
	DB_ROW			row;
	DB_RESULT		result;
	char			*sql;
	size_t			sql_alloc = 4096, sql_offset = 0;
	zbx_db_insert_t		db_insert_functions;
	zbx_vector_ptr_t	functions;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_ptr_create(&functions);

	sql = zbx_malloc(NULL, sql_alloc);

	zbx_db_insert_prepare(&db_insert_functions, "functions", "functionid", "itemid", "triggerid", "name",
			"parameter", NULL);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select triggerid,recovery_mode,expression,recovery_expression from triggers"
			" order by triggerid");

	while (NULL != (row = DBfetch(result)))
	{
		char			delim, *esc;
		zbx_dbpatch_trigger_t	trigger;

		ZBX_STR2UINT64(trigger.triggerid, row[0]);
		ZBX_STR2UCHAR(trigger.recovery_mode, row[1]);
		trigger.expression = zbx_strdup(NULL, row[2]);
		trigger.recovery_expression = zbx_strdup(NULL, row[3]);
		trigger.flags = 0;

		if (SUCCEED == dbpatch_convert_trigger(&trigger, &functions))
		{
			for (i = 0; i < functions.values_num; i++)
			{
				zbx_dbpatch_function_t	*func = (zbx_dbpatch_function_t *)functions.values[i];

				if (0 != (func->flags & ZBX_DBPATCH_FUNCTION_CREATE))
				{
					zbx_db_insert_add_values(&db_insert_functions, func->functionid,
							func->itemid, trigger.triggerid, func->name, func->parameter);
					continue;
				}

				if (0 != (func->flags & ZBX_DBPATCH_FUNCTION_DELETE))
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"delete from functions where functionid=" ZBX_FS_UI64 ";\n",
							func->functionid);

					if (FAIL == (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
						break;

					continue;
				}

				delim = ' ';

				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update functions set");
				if (0 != (func->flags & ZBX_DBPATCH_FUNCTION_UPDATE_NAME))
				{
					esc = DBdyn_escape_field("functions", "name", func->name);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,"%cname='%s'", delim, esc);
					zbx_free(esc);
					delim = ',';
				}

				if (0 != (func->flags & ZBX_DBPATCH_FUNCTION_UPDATE_PARAM))
				{
					esc = DBdyn_escape_field("functions", "parameter", func->parameter);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,"%cparameter='%s'", delim, esc);
					zbx_free(esc);
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where functionid=" ZBX_FS_UI64
						";\n", func->functionid);

				if (FAIL == (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
					break;
			}

			if (SUCCEED == ret && 0 != (trigger.flags & ZBX_DBPATCH_TRIGGER_UPDATE))
			{
				delim = ' ';
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set");

				if (0 != (trigger.flags & ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION))
				{
					esc = DBdyn_escape_field("triggers", "expression", trigger.expression);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,"%cexpression='%s'", delim,
							esc);
					zbx_free(esc);
					delim = ',';
				}

				if (0 != (trigger.flags & ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION))
				{
					esc = DBdyn_escape_field("triggers", "recovery_expression",
							trigger.recovery_expression);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,"%crecovery_expression='%s'",
							delim, esc);
					zbx_free(esc);
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where triggerid=" ZBX_FS_UI64
						";\n", trigger.triggerid);

				if (FAIL == (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
					break;
			}
		}

		zbx_vector_ptr_clear_ext(&functions, (zbx_clean_func_t)dbpatch_function_free);
		dbpatch_trigger_clear(&trigger);

		if (SUCCEED != ret)
			break;
	}

	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	if (SUCCEED == ret)
		zbx_db_insert_execute(&db_insert_functions);

	zbx_db_insert_clean(&db_insert_functions);
	zbx_free(sql);

	zbx_vector_ptr_destroy(&functions);

	return ret;
}

static int	DBpatch_5030082(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update trigger_queue set type=4 where type=3"))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_replace_functionids                                      *
 *                                                                            *
 * Purpose: replace functionids {<index in functions vector>} in expression   *
 *          with their string format                                          *
 *                                                                            *
 * Parameters: expression - [IN/OUT] the expression                           *
 *             functions  - [IN] the functions                                *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_replace_functionids(char **expression, const zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	index;
	int		pos = 0, last_pos = 0;
	zbx_token_t	token;
	char		*out = NULL;
	size_t		out_alloc = 0, out_offset = 0;

	for (; SUCCEED == zbx_token_find(*expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID); pos++)
	{
		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
				if (SUCCEED == is_uint64_n(*expression + token.loc.l + 1,
						token.loc.r - token.loc.l - 1, &index) &&
						(int)index < functions->values_num)
				{
					zbx_dbpatch_function_t	*func = functions->values[index];

					zbx_strncpy_alloc(&out, &out_alloc, &out_offset,
							*expression + last_pos, token.loc.l - last_pos);

					zbx_snprintf_alloc(&out, &out_alloc, &out_offset, "%s(%s",
							func->name, func->arg0);
					if ('\0' != *func->parameter)
					{
						zbx_chrcpy_alloc(&out, &out_alloc, &out_offset, ',');
						zbx_strcpy_alloc(&out, &out_alloc, &out_offset, func->parameter);
					}
					zbx_chrcpy_alloc(&out, &out_alloc, &out_offset, ')');
					last_pos = token.loc.r + 1;
				}
				pos = token.loc.r;
				break;
			case ZBX_TOKEN_MACRO:
			case ZBX_TOKEN_USER_MACRO:
			case ZBX_TOKEN_LLD_MACRO:
				pos = token.loc.r;
				break;
		}
	}

	if (0 != out_alloc)
	{
		zbx_strcpy_alloc(&out, &out_alloc, &out_offset, *expression + last_pos);
		zbx_free(*expression);
		*expression = out;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_convert_simple_macro                                     *
 *                                                                            *
 * Purpose: convert simple macro {host.key:func(params)} to the new syntax    *
 *          func(/host/key,params)                                            *
 *                                                                            *
 * Parameters: expression - [IN] the expression with simple macro             *
 *             data       - [IN] the simple macro token data                  *
 *             function   - [OUT] the simple macro replacement function       *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_convert_simple_macro(const char *expression, const zbx_token_simple_macro_t *data,
		char **function)
{
	zbx_dbpatch_function_t	*func;
	zbx_vector_ptr_t	functions;
	char			*name, *host, *key;

	name = zbx_substr(expression, data->func.l, data->func_param.l - 1);

	if (SUCCEED == dbpatch_is_time_function(name, strlen(name)))
	{
		*function = zbx_dsprintf(NULL, "%s()", name);
		zbx_free(name);
		return;
	}

	zbx_vector_ptr_create(&functions);

	func = (zbx_dbpatch_function_t *)zbx_malloc(NULL, sizeof(zbx_dbpatch_function_t));
	func->functionid = 0;
	func->itemid = 0;
	func->flags = 0;
	func->name = name;

	if (data->func_param.l + 1 == data->func_param.r)
		func->parameter = zbx_strdup(NULL, "");
	else
		func->parameter = zbx_substr(expression, data->func_param.l + 1, data->func_param.r - 1);

	host = zbx_substr(expression, data->host.l, data->host.r);
	key = zbx_substr(expression, data->key.l, data->key.r);

	if (0 == strcmp(host, "{HOST.HOST}"))
		func->arg0 = zbx_dsprintf(NULL, "//%s", key);
	else
		func->arg0 = zbx_dsprintf(NULL, "/%s/%s", host, key);

	zbx_vector_ptr_append(&functions, func);

	dbpatch_convert_function(func, function, &functions);
	if (NULL == *function)
		*function = zbx_strdup(NULL, "{0}");
	dbpatch_replace_functionids(function, &functions);

	zbx_free(key);
	zbx_free(host);
	zbx_vector_ptr_clear_ext(&functions, (zbx_clean_func_t)dbpatch_function_free);
	zbx_vector_ptr_destroy(&functions);
}

/******************************************************************************
 *                                                                            *
 * Function: dbpatch_convert_expression_macro                                 *
 *                                                                            *
 * Purpose: convert simple macros in expression macro {? } to function calls  *
 *          using new expression syntax                                       *
 *                                                                            *
 * Parameters: expression - [IN] the original expression                      *
 *             loc        - [IN] the macro location within expression         *
 *             replace    - [OUT] the expression macro replacement expression *
 *                                                                            *
 * Return value: SUCCEED - expression macro was converted                     *
 *               FAIL    - expression macro does not contain simple macros    *
 *                                                                            *
 ******************************************************************************/
static int	dbpatch_convert_expression_macro(const char *expression, const zbx_strloc_t *loc, char **replace)
{
	zbx_token_t	token;
	char		*out = NULL;
	size_t		out_alloc = 0, out_offset = 0, pos = loc->l + 2, last_pos = loc->l;

	for (; SUCCEED == zbx_token_find(expression, (int)pos, &token, ZBX_TOKEN_SEARCH_BASIC) && token.loc.r < loc->r;
			pos++)
	{
		char	*macro = NULL;

		switch (token.type)
		{
			case ZBX_TOKEN_SIMPLE_MACRO:
				dbpatch_convert_simple_macro(expression, &token.data.simple_macro, &macro);
				zbx_strncpy_alloc(&out, &out_alloc, &out_offset, expression + last_pos,
						token.loc.l - last_pos);
				zbx_strcpy_alloc(&out, &out_alloc, &out_offset, macro);
				zbx_free(macro);
				last_pos = token.loc.r + 1;
				pos = token.loc.r;
				break;
			case ZBX_TOKEN_MACRO:
			case ZBX_TOKEN_FUNC_MACRO:
			case ZBX_TOKEN_USER_MACRO:
			case ZBX_TOKEN_LLD_MACRO:
				pos = token.loc.r;
				break;
		}
	}

	if (0 == out_offset)
		return FAIL;

	if (last_pos <= loc->r)
		zbx_strncpy_alloc(&out, &out_alloc, &out_offset, expression + last_pos, loc->r - last_pos + 1);
	*replace = out;

	return SUCCEED;
}

static int	DBpatch_5030083(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	char		*sql;
	size_t		sql_alloc = 4096, sql_offset = 0;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	sql = zbx_malloc(NULL, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select triggerid,event_name from triggers order by triggerid");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_token_t	token;
		char		*out = NULL;
		size_t		out_alloc = 0, out_offset = 0, pos = 0, last_pos = 0;

		for (; SUCCEED == zbx_token_find(row[1], (int)pos, &token, ZBX_TOKEN_SEARCH_EXPRESSION_MACRO); pos++)
		{
			char		*replace = NULL;
			zbx_strloc_t	*loc = NULL;

			switch (token.type)
			{
				case ZBX_TOKEN_EXPRESSION_MACRO:
					loc = &token.loc;
					break;
				case ZBX_TOKEN_FUNC_MACRO:
					loc = &token.data.func_macro.macro;
					if ('?' != row[1][loc->l + 1])
					{
						pos = token.loc.r;
						continue;
					}
					break;
				case ZBX_TOKEN_MACRO:
				case ZBX_TOKEN_USER_MACRO:
				case ZBX_TOKEN_LLD_MACRO:
					pos = token.loc.r;
					continue;
				default:
					continue;
			}

			if (SUCCEED == dbpatch_convert_expression_macro(row[1], loc, &replace))
			{
				zbx_strncpy_alloc(&out, &out_alloc, &out_offset, row[1] + last_pos, loc->l - last_pos);
				zbx_strcpy_alloc(&out, &out_alloc, &out_offset, replace);
				zbx_free(replace);
				last_pos = loc->r + 1;
			}
			pos = token.loc.r;
		}

		if (0 == out_alloc)
			continue;

		zbx_strcpy_alloc(&out, &out_alloc, &out_offset, row[1] + last_pos);

		if (TRIGGER_EVENT_NAME_LEN < zbx_strlen_utf8(out))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert trigger \"%s\" event name: too long expression",
					row[0]);
		}
		else
		{
			char	*esc;

			esc = DBdyn_escape_field("triggers", "event_name", out);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set event_name='%s'"
					" where triggerid=%s;\n", esc, row[0]);
			zbx_free(esc);

			ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_free(out);
	}
	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);

	return ret;
}

static char	*dbpatch_formula_to_expression(zbx_uint64_t itemid, const char *formula, zbx_vector_ptr_t *functions)
{
	zbx_dbpatch_function_t	*func;
	const char		*ptr;
	char			*exp = NULL, error[128];
	size_t			exp_alloc = 0, exp_offset = 0, pos = 0, par_l, par_r;

	for (ptr = formula; SUCCEED == zbx_function_find(ptr, &pos, &par_l, &par_r, error, sizeof(error));
			ptr += par_r + 1)
	{
		size_t	param_pos, param_len, sep_pos;
		int	quoted;

		/* copy the part of the string preceding function */
		zbx_strncpy_alloc(&exp, &exp_alloc, &exp_offset, ptr, pos);

		if (SUCCEED != dbpatch_is_time_function(ptr + pos, par_l - pos))
		{
			char	*arg0, *host = NULL, *key = NULL;
			int	ret;

			zbx_function_param_parse(ptr + par_l + 1, &param_pos, &param_len, &sep_pos);

			arg0 = zbx_function_param_unquote_dyn(ptr + par_l + 1 + param_pos, param_len, &quoted);
			ret = parse_host_key(arg0, &host, &key);
			zbx_free(arg0);

			if (FAIL == ret)
			{
				zbx_vector_ptr_clear_ext(functions, (zbx_clean_func_t)dbpatch_function_free);
				zbx_free(exp);
				return NULL;
			}

			func = (zbx_dbpatch_function_t *)zbx_malloc(NULL, sizeof(zbx_dbpatch_function_t));
			func->itemid = itemid;
			func->name = zbx_substr(ptr, pos, par_l - 1);
			func->flags = 0;

			func->arg0 = zbx_dsprintf(NULL, "/%s/%s", ZBX_NULL2EMPTY_STR(host), key);
			zbx_free(host);
			zbx_free(key);

			if (')' != ptr[par_l + 1 + sep_pos])
				func->parameter = zbx_substr(ptr, par_l + sep_pos + 2, par_r - 1);
			else
				func->parameter = zbx_strdup(NULL, "");

			func->functionid = functions->values_num;
			zbx_vector_ptr_append(functions, func);

			zbx_snprintf_alloc(&exp, &exp_alloc, &exp_offset, "{" ZBX_FS_UI64 "}", func->functionid);
		}
		else
		{
			zbx_strncpy_alloc(&exp, &exp_alloc, &exp_offset, ptr + pos, par_l - pos);
			zbx_strcpy_alloc(&exp, &exp_alloc, &exp_offset, "()");
		}
	}

	if (par_l <= par_r)
		zbx_strcpy_alloc(&exp, &exp_alloc, &exp_offset, ptr);

	return exp;
}

static int	DBpatch_5030084(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_vector_ptr_t	functions;
	int			i, ret = SUCCEED;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_ptr_create(&functions);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select i.itemid,i.params"
			" from items i,hosts h"
			" where i.type=15"
				" and h.hostid=i.hostid"
			" order by i.itemid");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid, index;
		char		*expression, *out = NULL;
		int		pos = 0, last_pos = 0;
		zbx_token_t	token;
		size_t		out_alloc = 0, out_offset = 0;

		ZBX_STR2UINT64(itemid, row[0]);
		if (NULL == (expression = dbpatch_formula_to_expression(itemid, row[1], &functions)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert calculated item \"" ZBX_FS_UI64 "\"formula",
					itemid);
			continue;
		}

		for (i = 0; i < functions.values_num; i++)
		{
			char			*replace = NULL;
			zbx_dbpatch_function_t	*func = functions.values[i];

			dbpatch_convert_function(func, &replace, &functions);
			if (NULL != replace)
			{
				dbpatch_update_expression(&expression, func->functionid, replace);
				zbx_free(replace);
			}
		}

		for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID); pos++)
		{
			switch (token.type)
			{
				case ZBX_TOKEN_OBJECTID:
					if (SUCCEED == is_uint64_n(expression + token.loc.l + 1,
							token.loc.r - token.loc.l - 1, &index) &&
							(int)index < functions.values_num)
					{
						zbx_dbpatch_function_t	*func = functions.values[index];

						zbx_strncpy_alloc(&out, &out_alloc, &out_offset,
								expression + last_pos, token.loc.l - last_pos);

						zbx_snprintf_alloc(&out, &out_alloc, &out_offset, "%s(%s",
								func->name, func->arg0);
						if ('\0' != *func->parameter)
						{
							zbx_chrcpy_alloc(&out, &out_alloc, &out_offset, ',');
							zbx_strcpy_alloc(&out, &out_alloc, &out_offset, func->parameter);
						}
						zbx_chrcpy_alloc(&out, &out_alloc, &out_offset, ')');
						last_pos = token.loc.r + 1;
					}
					pos = token.loc.r;
					break;
				case ZBX_TOKEN_MACRO:
				case ZBX_TOKEN_USER_MACRO:
				case ZBX_TOKEN_LLD_MACRO:
					pos = token.loc.r;
					break;
			}
		}

		zbx_strcpy_alloc(&out, &out_alloc, &out_offset, expression + last_pos);

		if (ITEM_PARAM_LEN < zbx_strlen_utf8(out))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert calculated item \"" ZBX_FS_UI64 "\" formula:"
					" too long expression", itemid);
		}
		else
		{
			char	*esc;

			esc = DBdyn_escape_field("items", "params", out);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set params='%s' where itemid="
					ZBX_FS_UI64 ";\n", esc, itemid);
			zbx_free(esc);

			ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_vector_ptr_clear_ext(&functions, (zbx_clean_func_t)dbpatch_function_free);
		zbx_free(expression);
		zbx_free(out);
	}

	DBfree_result(result);
	zbx_vector_ptr_destroy(&functions);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);

	return ret;
}

static int	dbpatch_aggregate2formula(const AGENT_REQUEST *request, char **str, size_t *str_alloc,
		size_t *str_offset, char **error)
{
	char	*esc;

	if (3 > request->nparam)
	{
		*error = zbx_strdup(NULL, "invalid number of parameters");
		return FAIL;
	}

	if (0 == strcmp(request->key, "grpavg"))
	{
		zbx_strcpy_alloc(str, str_alloc, str_offset, "avg");
	}
	else if (0 == strcmp(request->key, "grpmax"))
	{
		zbx_strcpy_alloc(str, str_alloc, str_offset, "max");
	}
	else if (0 == strcmp(request->key, "grpmin"))
	{
		zbx_strcpy_alloc(str, str_alloc, str_offset, "min");
	}
	else if (0 == strcmp(request->key, "grpsum"))
	{
		zbx_strcpy_alloc(str, str_alloc, str_offset, "sum");
	}
	else
	{
		*error = zbx_dsprintf(NULL, "unknown group function \"%s\"", request->key);
		return FAIL;
	}

	zbx_rtrim(request->params[1], " ");
	zbx_snprintf_alloc(str, str_alloc, str_offset, "(%s_foreach(/*/%s?[", request->params[2], request->params[1]);

	if (REQUEST_PARAMETER_TYPE_ARRAY == get_rparam_type(request, 0))
	{
		int				i, groups_num;
		char				*group;
		zbx_request_parameter_type_t	type;

		groups_num = num_param(request->params[0]);

		for (i = 1; i <= groups_num; i++)
		{
			if (NULL == (group = get_param_dyn(request->params[0], i, &type)))
				continue;

			if ('[' != (*str)[*str_offset - 1])
				zbx_strcpy_alloc(str, str_alloc, str_offset, " or ");

			esc = zbx_dyn_escape_string(group, "\\\"");
			zbx_snprintf_alloc(str, str_alloc, str_offset, "group=\"%s\"", esc);
			zbx_free(esc);
			zbx_free(group);
		}
	}
	else
	{
		esc = zbx_dyn_escape_string(request->params[0], "\\\"");
		zbx_snprintf_alloc(str, str_alloc, str_offset, "group=\"%s\"", esc);
		zbx_free(esc);
	}

	zbx_chrcpy_alloc(str, str_alloc, str_offset, ']');

	if (4 == request->nparam)
		zbx_snprintf_alloc(str, str_alloc, str_offset, ",%s", request->params[3]);

	zbx_strcpy_alloc(str, str_alloc, str_offset, "))");

	if (ITEM_PARAM_LEN < zbx_strlen_utf8(*str))
	{
		*error = zbx_strdup(NULL, "resulting formula is too long");
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030085(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;
	char		*sql = NULL, *params = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, params_alloc = 0, params_offset;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* ITEM_TYPE_AGGREGATE = 8 */
	result = DBselect("select itemid,key_ from items where type=8");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		AGENT_REQUEST	request;
		char		*error = NULL, *esc;
		int		ret_formula;

		params_offset = 0;

		init_request(&request);
		parse_item_key(row[1], &request);

		ret_formula = dbpatch_aggregate2formula(&request, &params, &params_alloc, &params_offset, &error);
		free_request(&request);

		if (FAIL == ret_formula)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot convert aggregate checks item \"%s\": %s", row[0], error);
			zbx_free(error);
			continue;
		}

		esc = DBdyn_escape_field("items", "params", params);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update items set type=15,params='%s' where itemid=%s;\n", esc, row[0]);
		zbx_free(esc);

		ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(params);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_5030086(void)
{
#ifdef HAVE_MYSQL
	return DBcreate_index("items", "items_8", "key_(1024)", 0);
#else
	return DBcreate_index("items", "items_8", "key_", 0);
#endif
}

#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)
DBPATCH_ADD(5030001, 0, 1)
DBPATCH_ADD(5030002, 0, 1)
DBPATCH_ADD(5030003, 0, 1)
DBPATCH_ADD(5030004, 0, 1)
DBPATCH_ADD(5030005, 0, 1)
DBPATCH_ADD(5030006, 0, 1)
DBPATCH_ADD(5030007, 0, 1)
DBPATCH_ADD(5030008, 0, 1)
DBPATCH_ADD(5030009, 0, 1)
DBPATCH_ADD(5030010, 0, 1)
DBPATCH_ADD(5030011, 0, 1)
DBPATCH_ADD(5030012, 0, 1)
DBPATCH_ADD(5030013, 0, 1)
DBPATCH_ADD(5030014, 0, 1)
DBPATCH_ADD(5030015, 0, 1)
DBPATCH_ADD(5030016, 0, 1)
DBPATCH_ADD(5030017, 0, 1)
DBPATCH_ADD(5030018, 0, 1)
DBPATCH_ADD(5030019, 0, 1)
DBPATCH_ADD(5030020, 0, 1)
DBPATCH_ADD(5030021, 0, 1)
DBPATCH_ADD(5030022, 0, 1)
DBPATCH_ADD(5030023, 0, 1)
DBPATCH_ADD(5030024, 0, 1)
DBPATCH_ADD(5030025, 0, 1)
DBPATCH_ADD(5030026, 0, 1)
DBPATCH_ADD(5030027, 0, 1)
DBPATCH_ADD(5030028, 0, 1)
DBPATCH_ADD(5030029, 0, 1)
DBPATCH_ADD(5030030, 0, 1)
DBPATCH_ADD(5030031, 0, 1)
DBPATCH_ADD(5030032, 0, 1)
DBPATCH_ADD(5030033, 0, 1)
DBPATCH_ADD(5030034, 0, 1)
DBPATCH_ADD(5030035, 0, 1)
DBPATCH_ADD(5030036, 0, 1)
DBPATCH_ADD(5030037, 0, 1)
DBPATCH_ADD(5030038, 0, 1)
DBPATCH_ADD(5030039, 0, 1)
DBPATCH_ADD(5030040, 0, 1)
DBPATCH_ADD(5030041, 0, 1)
DBPATCH_ADD(5030042, 0, 1)
DBPATCH_ADD(5030043, 0, 1)
DBPATCH_ADD(5030044, 0, 1)
DBPATCH_ADD(5030045, 0, 1)
DBPATCH_ADD(5030046, 0, 1)
DBPATCH_ADD(5030047, 0, 1)
DBPATCH_ADD(5030048, 0, 1)
DBPATCH_ADD(5030049, 0, 1)
DBPATCH_ADD(5030050, 0, 1)
DBPATCH_ADD(5030051, 0, 1)
DBPATCH_ADD(5030052, 0, 1)
DBPATCH_ADD(5030053, 0, 1)
DBPATCH_ADD(5030054, 0, 1)
DBPATCH_ADD(5030055, 0, 1)
DBPATCH_ADD(5030056, 0, 1)
DBPATCH_ADD(5030057, 0, 1)
DBPATCH_ADD(5030058, 0, 1)
DBPATCH_ADD(5030059, 0, 1)
DBPATCH_ADD(5030060, 0, 1)
DBPATCH_ADD(5030061, 0, 1)
DBPATCH_ADD(5030062, 0, 1)
DBPATCH_ADD(5030063, 0, 1)
DBPATCH_ADD(5030064, 0, 1)
DBPATCH_ADD(5030065, 0, 1)
DBPATCH_ADD(5030066, 0, 1)
DBPATCH_ADD(5030067, 0, 1)
DBPATCH_ADD(5030068, 0, 1)
DBPATCH_ADD(5030069, 0, 1)
DBPATCH_ADD(5030070, 0, 1)
DBPATCH_ADD(5030071, 0, 1)
DBPATCH_ADD(5030072, 0, 1)
DBPATCH_ADD(5030073, 0, 1)
DBPATCH_ADD(5030074, 0, 1)
DBPATCH_ADD(5030075, 0, 1)
DBPATCH_ADD(5030076, 0, 1)
DBPATCH_ADD(5030077, 0, 1)
DBPATCH_ADD(5030078, 0, 1)
DBPATCH_ADD(5030079, 0, 1)
DBPATCH_ADD(5030080, 0, 1)
DBPATCH_ADD(5030081, 0, 1)
DBPATCH_ADD(5030082, 0, 1)
DBPATCH_ADD(5030083, 0, 1)
DBPATCH_ADD(5030084, 0, 1)
DBPATCH_ADD(5030085, 0, 1)
DBPATCH_ADD(5030086, 0, 1)

DBPATCH_END()
