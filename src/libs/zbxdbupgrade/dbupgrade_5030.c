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
#include "zbxserver.h"
#include "zbxregexp.h"

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

#undef HOST_STATUS_TEMPLATE
#define HOST_STATUS_TEMPLATE		3
#undef ZBX_FLAG_DISCOVERY_NORMAL
#define ZBX_FLAG_DISCOVERY_NORMAL	0x00
#undef ZBX_FLAG_DISCOVERY_RULE
#define ZBX_FLAG_DISCOVERY_RULE		0x01
#undef ZBX_FLAG_DISCOVERY_PROTOTYPE
#define ZBX_FLAG_DISCOVERY_PROTOTYPE	0x02

#define ZBX_FIELD_UUID			{"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0}

static int	DBpatch_5030057(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("items", &field);
}

static int	DBpatch_5030058(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("hosts", &field);
}

static int	DBpatch_5030059(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("triggers", &field);
}

static int	DBpatch_5030060(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030061(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("graphs", &field);
}

static int	DBpatch_5030062(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("hstgrp", &field);
}

static int	DBpatch_5030063(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("httptest", &field);
}

static int	DBpatch_5030064(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("valuemap", &field);
}

static char	*update_template_name(char *old)
{
	char	*ptr, new[MAX_STRING_LEN];

#define MIN_TEMPLATE_NAME_LEN	3

	ptr = old;

	if (NULL != zbx_regexp_match(old, "Template (APP|App|DB|Module|Net|OS|SAN|Server|Tel|VM) ", NULL) &&
			1 == sscanf(old, "Template %*[^ ] %" ZBX_STR(MAX_STRING_LEN) "s", new) &&
			strlen(new) > MIN_TEMPLATE_NAME_LEN)
	{
		ptr = zbx_strdup(ptr, new);
	}

	return ptr;
}

static int	DBpatch_5030065(void)
{
	int		ret = SUCCEED;
	char		*name, *uuid, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select h.hostid,h.name"
			" from hosts h"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		name = zbx_strdup(NULL, row[1]);
		name = update_template_name(name);
		uuid = zbx_gen_uuid4(name);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set uuid='%s' where hostid=%s;\n",
				uuid, row[0]);
		zbx_free(name);
		zbx_free(uuid);

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

static int	DBpatch_5030066(void)
{
	int		ret = SUCCEED;
	char		*name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select i.itemid,i.key_,h.name"
			" from items i"
			" join hosts h on h.hostid=i.hostid"
			" where h.status=%d and i.flags in (%d,%d) and i.templateid is null",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE);

	while (NULL != (row = DBfetch(result)))
	{
		name = zbx_strdup(NULL, row[2]);
		name = update_template_name(name);
		seed = zbx_dsprintf(seed, "%s%s", name, row[1]);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set uuid='%s' where itemid=%s;\n",
				uuid, row[0]);
		zbx_free(name);
		zbx_free(uuid);
		zbx_free(seed);

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

static int	DBpatch_5030067(void)
{
	int		ret = SUCCEED;
	char		*name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select t.triggerid,t.description,t.expression,t.recovery_expression"
			" from triggers t"
			" join functions f on f.triggerid=t.triggerid"
			" join items i on i.itemid=f.itemid"
			" join hosts h on h.hostid=i.hostid and h.status=%d"
			" where t.templateid is null and t.flags=%d",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_NORMAL);

	while (NULL != (row = DBfetch(result)))
	{
		const char	*pexpr, *pexpr_f, *pexpr_s;
		char		*trigger_expr, *expression = NULL;
		int		i;
		size_t		expression_alloc = 0, expression_offset = 0;
		zbx_uint64_t	functionid;
		DB_ROW		row2;
		DB_RESULT	result2;

		name = zbx_strdup(NULL, row[1]);

		for (i = 0; i < 2; i++)
		{
			trigger_expr = zbx_strdup(NULL, row[i + 2]);
			pexpr = pexpr_f = (const char *)trigger_expr;

			while (SUCCEED == get_N_functionid(pexpr, 1, &functionid, (const char **)&pexpr_s, &pexpr_f))
			{
				trigger_expr[pexpr_s - trigger_expr] = '\0';

				result2 = DBselect(
						"select h.name,i.key_,f.name,f.parameter"
						" from functions f"
						" join items i on i.itemid=f.itemid"
						" join hosts h on h.hostid=i.hostid"
						" where f.functionid=" ZBX_FS_UI64,
						functionid);

				while (NULL != (row2 = DBfetch(result2)))
				{
					zbx_snprintf_alloc(&expression, &expression_alloc, &expression_offset,
							"%s{%s:%s.%s(%s)}",pexpr, row2[0], row2[1], row2[2], row2[3]);
					pexpr = pexpr_f;
				}

				DBfree_result(result2);
			}

			if (pexpr != trigger_expr)
				zbx_snprintf_alloc(&expression, &expression_alloc, &expression_offset, "%s", pexpr);

			zbx_free(trigger_expr);
		}

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset,"%s", name);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset,"%s", expression);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);
		zbx_free(expression);
		zbx_free(name);
		zbx_free(uuid);
		zbx_free(seed);

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

static int	DBpatch_5030068(void)
{
	int		ret = SUCCEED;
	char		*name, *host_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	result = DBselect(
			"select distinct g.graphid,g.name"
			" from graphs g"
			" join graphs_items gi on gi.graphid=g.graphid"
			" join items i on i.itemid=gi.itemid"
			" join hosts h on h.hostid=i.hostid and h.status=%d"
			" where g.templateid is null and g.flags=%d",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_NORMAL);

	while (NULL != (row = DBfetch(result)))
	{
		DB_ROW		row2;
		DB_RESULT	result2;

		name = zbx_strdup(NULL, row[1]);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset,"%s", name);

		result2 = DBselect(
				"select h.name,h.status"
				" from graphs_items gi"
				" join items i on i.itemid=gi.itemid"
				" join hosts h on h.hostid=i.hostid"
				" where gi.graphid=%s",
				row[0]);

		while (NULL != (row2 = DBfetch(result2)))
		{
			int	status;

			status = atoi(row2[1]);
			host_name = zbx_strdup(NULL, row2[0]);

			if (HOST_STATUS_TEMPLATE == status)
				host_name = update_template_name(host_name);

			zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", host_name);
			zbx_free(host_name);
		}

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set uuid='%s'"
				" where graphid=%s;\n", uuid, row[0]);
		zbx_free(name);
		zbx_free(uuid);
		zbx_free(seed);

		DBfree_result(result2);

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

static int	DBpatch_5030069(void)
{
	int		ret = SUCCEED;
	char		*dashboard_name, *dashboard, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select d.dashboardid,d.name,h.name"
			" from dashboard d"
			" join hosts h on h.hostid=d.templateid"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		dashboard_name = zbx_strdup(NULL, row[2]);
		dashboard_name = update_template_name(dashboard_name);
		dashboard = zbx_strdup(NULL, row[1]);
		seed = zbx_dsprintf(seed, "%s%s", dashboard_name, dashboard);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update dashboard set uuid='%s' where dashboardid=%s;\n", uuid, row[0]);
		zbx_free(dashboard_name);
		zbx_free(dashboard);
		zbx_free(uuid);
		zbx_free(seed);

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

static int	DBpatch_5030070(void)
{
	int		ret = SUCCEED;
	char		*template_name, *httptest, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select ht.httptestid,ht.name,h.name"
			" from httptest ht"
			" join hosts h on h.hostid=ht.hostid"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		template_name = zbx_strdup(NULL, row[2]);
		template_name = update_template_name(template_name);
		httptest = zbx_strdup(NULL, row[1]);
		seed = zbx_dsprintf(seed, "%s%s", template_name, httptest);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update httptest set uuid='%s' where httptestid=%s;\n", uuid, row[0]);
		zbx_free(template_name);
		zbx_free(httptest);
		zbx_free(uuid);
		zbx_free(seed);

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

static int	DBpatch_5030071(void)
{
	int		ret = SUCCEED;
	char		*template_name, *valuemap, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select v.valuemapid,v.name,h.name"
			" from valuemap v"
			" join hosts h on h.hostid=v.hostid"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		template_name = zbx_strdup(NULL, row[2]);
		template_name = update_template_name(template_name);
		valuemap = zbx_strdup(NULL, row[1]);
		seed = zbx_dsprintf(seed, "%s%s", template_name, valuemap);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update valuemap set uuid='%s' where valuemapid=%s;\n", uuid, row[0]);
		zbx_free(template_name);
		zbx_free(valuemap);
		zbx_free(uuid);
		zbx_free(seed);

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

static int	DBpatch_5030072(void)
{
	int		ret = SUCCEED;
	char		*group_name, *uuid, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select groupid,name from hstgrp");

	while (NULL != (row = DBfetch(result)))
	{
		group_name = zbx_strdup(NULL, row[1]);
		uuid = zbx_gen_uuid4(group_name);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hstgrp set uuid='%s' where groupid=%s;\n", uuid, row[0]);
		zbx_free(group_name);
		zbx_free(uuid);

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

static int	DBpatch_5030073(void)
{
	int		ret = SUCCEED;
	char		*template_name, *key, *key_discovery, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select i.itemid,i.key_,h.name,i2.key_"
			" from items i"
			" join hosts h on h.hostid=i.hostid"
			" join item_discovery id on id.itemid=i.itemid"
			" join items i2 on id.parent_itemid=i2.itemid"
			" where h.status=%d and i.flags=%d and i.templateid is null",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		template_name = zbx_strdup(NULL, row[2]);
		template_name = update_template_name(template_name);
		key = zbx_strdup(NULL, row[1]);
		key_discovery = zbx_strdup(NULL, row[3]);
		seed = zbx_dsprintf(seed, "%s%s%s", template_name, key_discovery, key);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set uuid='%s' where itemid=%s;\n",
				uuid, row[0]);
		zbx_free(template_name);
		zbx_free(key);
		zbx_free(key_discovery);
		zbx_free(uuid);
		zbx_free(seed);

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

static int	DBpatch_5030074(void)
{
	int		ret = SUCCEED;
	char		*trigger_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select distinct t.triggerid,t.description,t.expression,t.recovery_expression"
			" from triggers t"
			" join functions f on f.triggerid=t.triggerid"
			" join items i on i.itemid=f.itemid"
			" join hosts h on h.hostid=i.hostid and h.status=%d"
			" where t.templateid is null and t.flags=%d",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		const char	*pexpr, *pexpr_f, *pexpr_s;
		char		*trigger_expr, *total_expr = NULL;
		int		i;
		size_t		total_expr_alloc = 0, total_expr_offset = 0;
		zbx_uint64_t	functionid;
		DB_ROW		row2;
		DB_RESULT	result2;

		result2 = DBselect(
				"select distinct i2.key_"
				" from items i"
				" join item_discovery id on id.itemid=i.itemid"
				" join items i2 on id.parent_itemid=i2.itemid"
				" join functions f on i.itemid=f.itemid and f.triggerid=%s"
				" where i.flags=%d",
				row[0], ZBX_FLAG_DISCOVERY_PROTOTYPE);

		if (NULL == (row2 = DBfetch(result2)))
		{
			DBfree_result(result2);
			continue;
		}

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", row2[0]);

		DBfree_result(result2);

		trigger_name = zbx_strdup(NULL, row[1]);
		for (i = 0; i < 2; i++)
		{
			trigger_expr = zbx_strdup(NULL, row[i + 2]);
			pexpr = pexpr_f = (const char *)trigger_expr;

			while (SUCCEED == get_N_functionid(pexpr, 1, &functionid, (const char **)&pexpr_s, &pexpr_f))
			{
				trigger_expr[pexpr_s - trigger_expr] = '\0';

				result2 = DBselect(
						"select h.name,i.key_,f.name,f.parameter"
						" from functions f"
						" join items i on i.itemid=f.itemid"
						" join hosts h on h.hostid=i.hostid"
						" where f.functionid=" ZBX_FS_UI64,
						functionid);

				while (NULL != (row2 = DBfetch(result2)))
				{
					zbx_snprintf_alloc(&total_expr, &total_expr_alloc, &total_expr_offset,
							"%s{%s:%s.%s(%s)}",pexpr, row2[0], row2[1], row2[2], row2[3]);
					pexpr = pexpr_f;
				}

				DBfree_result(result2);
			}

			if (pexpr != trigger_expr)
				zbx_snprintf_alloc(&total_expr, &total_expr_alloc, &total_expr_offset, "%s", pexpr);

			zbx_free(trigger_expr);
		}

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", trigger_name);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", total_expr);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);
		zbx_free(total_expr);
		zbx_free(trigger_name);
		zbx_free(uuid);
		zbx_free(seed);

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

static int	DBpatch_5030075(void)
{
	int		ret = SUCCEED;
	char		*graph_name, *templ_name, *key, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	result = DBselect(
			"select distinct g.graphid,g.name,h.name,i2.key_"
			" from graphs g"
			" join graphs_items gi on gi.graphid=g.graphid"
			" join items i on i.itemid=gi.itemid and i.flags=%d"
			" join hosts h on h.hostid=i.hostid and h.status=%d"
			" join item_discovery id on id.itemid=i.itemid"
			" join items i2 on id.parent_itemid=i2.itemid"
			" where g.templateid is null and g.flags=%d",
			ZBX_FLAG_DISCOVERY_PROTOTYPE, HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		graph_name = zbx_strdup(NULL, row[1]);
		templ_name = zbx_strdup(NULL, row[2]);
		templ_name = update_template_name(templ_name);
		key = zbx_strdup(NULL, row[3]);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset,"%s%s%s", templ_name, key, graph_name);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set uuid='%s'"
				" where graphid=%s;\n", uuid, row[0]);
		zbx_free(graph_name);
		zbx_free(templ_name);
		zbx_free(key);
		zbx_free(uuid);
		zbx_free(seed);

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

static int	DBpatch_5030076(void)
{
	int		ret = SUCCEED;
	char		*host_name, *name_tmpl, *key, *uuid, *seed = NULL, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select h.hostid,h.name,h2.name,i.key_"
			" from hosts h"
			" join host_discovery hd on hd.hostid=h.hostid"
			" join items i on i.itemid=hd.parent_itemid"
			" join hosts h2 on h2.hostid=i.hostid and h2.status=%d"
			" where h.flags=%d and h.templateid is null",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		host_name = zbx_strdup(NULL, row[1]);
		name_tmpl = zbx_strdup(NULL, row[2]);
		name_tmpl = update_template_name(name_tmpl);
		key = zbx_strdup(NULL, row[3]);
		seed = zbx_dsprintf(seed, "%s%s%s", name_tmpl, key, host_name);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set uuid='%s' where hostid=%s;\n",
				uuid, row[0]);
		zbx_free(host_name);
		zbx_free(name_tmpl);
		zbx_free(key);
		zbx_free(seed);
		zbx_free(uuid);

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

#undef HOST_STATUS_TEMPLATE
#undef ZBX_FLAG_DISCOVERY_NORMAL
#undef ZBX_FLAG_DISCOVERY_RULE
#undef ZBX_FLAG_DISCOVERY_PROTOTYPE
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

DBPATCH_END()
