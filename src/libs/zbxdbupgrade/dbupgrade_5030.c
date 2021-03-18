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
#include "log.h"
#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"

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
#endif

static int	DBpatch_5030057(void)
{
	const ZBX_FIELD	field = {"display_period", "30", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030058(void)
{
	const ZBX_FIELD	field = {"auto_start", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030059(void)
{
	const ZBX_TABLE	table =
			{"dashboard_page", "dashboard_pageid", 0,
				{
					{"dashboard_pageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"dashboardid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"display_period", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"sortorder", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030060(void)
{
	const ZBX_FIELD field = {"dashboardid", NULL, "dashboard", "dashboardid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard_page", 1, &field);
}

static int	DBpatch_5030061(void)
{
	return DBcreate_index("dashboard_page", "dashboard_page_1", "dashboardid", 0);
}

static int	DBpatch_5030062(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"insert into dashboard_page (dashboard_pageid,dashboardid)"
			" select dashboardid,dashboardid from dashboard"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030063(void)
{
	const ZBX_FIELD	field = {"dashboard_pageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	if (SUCCEED != DBadd_field("widget", &field))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030064(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update widget set dashboard_pageid=dashboardid"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030065(void)
{
	const ZBX_FIELD	field = {"dashboard_pageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("widget", &field);
}

static int	DBpatch_5030066(void)
{
	return DBdrop_foreign_key("widget", 1);
}

static int	DBpatch_5030067(void)
{
	return DBdrop_field("widget", "dashboardid");
}

static int	DBpatch_5030068(void)
{
	return DBcreate_index("widget", "widget_1", "dashboard_pageid", 0);
}

static int	DBpatch_5030069(void)
{
	const ZBX_FIELD field = {"dashboard_pageid", NULL, "dashboard_page", "dashboard_pageid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget", 1, &field);
}

typedef struct
{
	uint64_t	screenitemid;
	uint64_t	screenid;
	int		resourcetype;
	uint64_t	resourceid;
	int		width;
	int		height;
	int		x;
	int		y;
	int		colspan;
	int		rowspan;
	int		elements;
	int		style;
	char		*url;
	int		sort_triggers;
	char		*application;
	int		dynamic;
}
zbx_db_screen_item_t;

typedef struct
{
	uint64_t	widget_fieldid;
	int		type;
	char		*name;
	int		value_int;
	char		*value_str;
	uint64_t	value_itemid;
	uint64_t	value_graphid;
	uint64_t	value_groupid;
	uint64_t	value_hostid;
	uint64_t	value_sysmapid;
}
zbx_db_widget_field_t;

typedef struct
{
	int	position;
	int	span;
	int	size;
}
zbx_screen_item_dim_t;

typedef struct
{
	uint64_t	dashboardid;
	char		*name;
	uint64_t	userid;
	int		private;
	int		display_period;
}
zbx_db_dashboard_t;

typedef struct
{
	uint64_t	dashboard_pageid;
	uint64_t	dashboardid;
	char		*name;
}
zbx_db_dashboard_page_t;

typedef struct
{
	uint64_t	widgetid;
	uint64_t	dashboardid;
	char		*type;
	char		*name;
	int		x;
	int		y;
	int		width;
	int		height;
	int		view_mode;
}
zbx_db_widget_t;

#define DASHBOARD_MAX_COLS			(24)
#define DASHBOARD_MAX_ROWS			(64)
#define DASHBOARD_WIDGET_MIN_ROWS		(2)
#define DASHBOARD_WIDGET_MAX_ROWS		(32)
#define SCREEN_MAX_ROWS				(100)
#define SCREEN_MAX_COLS				(100)

#undef SCREEN_RESOURCE_CLOCK
#define SCREEN_RESOURCE_CLOCK			(7)
#undef SCREEN_RESOURCE_GRAPH
#define SCREEN_RESOURCE_GRAPH			(0)
#undef SCREEN_RESOURCE_SIMPLE_GRAPH
#define SCREEN_RESOURCE_SIMPLE_GRAPH		(1)
#undef SCREEN_RESOURCE_LLD_GRAPH
#define SCREEN_RESOURCE_LLD_GRAPH		(20)
#undef SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
#define SCREEN_RESOURCE_LLD_SIMPLE_GRAPH	(19)
#undef SCREEN_RESOURCE_PLAIN_TEXT
#define SCREEN_RESOURCE_PLAIN_TEXT		(3)
#undef SCREEN_RESOURCE_URL
#define SCREEN_RESOURCE_URL			(11)

#undef SCREEN_RESOURCE_MAP
#define SCREEN_RESOURCE_MAP			(2)
#undef SCREEN_RESOURCE_HOST_INFO
#define SCREEN_RESOURCE_HOST_INFO		(4)
#undef SCREEN_RESOURCE_TRIGGER_INFO
#define SCREEN_RESOURCE_TRIGGER_INFO		(5)
#undef SCREEN_RESOURCE_SERVER_INFO
#define SCREEN_RESOURCE_SERVER_INFO		(6)
#undef SCREEN_RESOURCE_TRIGGER_OVERVIEW
#define SCREEN_RESOURCE_TRIGGER_OVERVIEW	(9)
#undef SCREEN_RESOURCE_DATA_OVERVIEW
#define SCREEN_RESOURCE_DATA_OVERVIEW		(10)
#undef SCREEN_RESOURCE_ACTIONS
#define SCREEN_RESOURCE_ACTIONS			(12)
#undef SCREEN_RESOURCE_EVENTS
#define SCREEN_RESOURCE_EVENTS			(13)
#undef SCREEN_RESOURCE_HOSTGROUP_TRIGGERS
#define SCREEN_RESOURCE_HOSTGROUP_TRIGGERS	(14)
#undef SCREEN_RESOURCE_SYSTEM_STATUS
#define SCREEN_RESOURCE_SYSTEM_STATUS		(15)
#undef SCREEN_RESOURCE_HOST_TRIGGERS
#define SCREEN_RESOURCE_HOST_TRIGGERS		(16)

#define ZBX_WIDGET_FIELD_TYPE_INT32		(0)
#define ZBX_WIDGET_FIELD_TYPE_STR		(1)
#define ZBX_WIDGET_FIELD_TYPE_GROUP		(2)
#define ZBX_WIDGET_FIELD_TYPE_HOST		(3)
#define ZBX_WIDGET_FIELD_TYPE_ITEM		(4)
#define ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE	(5)
#define ZBX_WIDGET_FIELD_TYPE_GRAPH		(6)
#define ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE	(7)
#define ZBX_WIDGET_FIELD_TYPE_MAP		(8)

#define ZBX_WIDGET_FIELD_RESOURCE_GRAPH				(0)
#define ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH			(1)
#define ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE		(2)
#define ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE	(3)

#define ZBX_WIDGET_TYPE_CLOCK			("clock")
#define ZBX_WIDGET_TYPE_GRAPH_CLASSIC		("graph")
#define ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE		("graphprototype")
#define ZBX_WIDGET_TYPE_PLAIN_TEXT		("plaintext")
#define ZBX_WIDGET_TYPE_URL			("url")
#define ZBX_WIDGET_TYPE_ACTIONS			("actionlog")
#define ZBX_WIDGET_TYPE_DATA_OVERVIEW		("dataover")
#define ZBX_WIDGET_TYPE_PROBLEMS		("problems")
#define ZBX_WIDGET_TYPE_HOST_INFO		("hostavail")
#define ZBX_WIDGET_TYPE_MAP			("map")
#define ZBX_WIDGET_TYPE_SYSTEM_STATUS		("problemsbysv")
#define ZBX_WIDGET_TYPE_SERVER_INFO		("systeminfo")
#define ZBX_WIDGET_TYPE_TRIGGER_OVERVIEW	("trigover")

#define POS_EMPTY	(127)
#define POS_TAKEN	(1)

ZBX_VECTOR_DECL(scitem_dim2, zbx_screen_item_dim_t);
ZBX_VECTOR_IMPL(scitem_dim2, zbx_screen_item_dim_t);
ZBX_VECTOR_DECL(char2, char);
ZBX_VECTOR_IMPL(char2, char);

#define SKIP_EMPTY(vector,index)	if (POS_EMPTY == vector->values[index]) continue

#define COLLISIONS_MAX_NUMBER	(100)
#define REFERENCE_MAX_LEN	(5)
#define DASHBOARD_NAME_LEN	(255)

static int DBpatch_dashboard_name(char *name, char **new_name)
{
	int		affix = 0, ret = FAIL, trim;
	char		*affix_string = NULL;
	DB_RESULT	result = NULL;
	DB_ROW		row;

	*new_name = zbx_strdup(*new_name, name);

	do
	{
		DBfree_result(result);

		result = DBselect("select count(*)"
				" from dashboard"
				" where name='%s' and templateid is null",
				*new_name);

		if (NULL == result || NULL == (row = DBfetch(result)))
		{
			zbx_free(*new_name);
			break;
		}

		if (0 == strcmp("0", row[0]))
		{
			ret = SUCCEED;
			break;
		}

		affix_string = zbx_dsprintf(affix_string, " (%d)", affix + 1);
		trim = (int)strlen(name) + (int)strlen(affix_string) - DASHBOARD_NAME_LEN;
		if (0 < trim )
			name[(int)strlen(name) - trim] = '\0';

		*new_name = zbx_dsprintf(*new_name, "%s%s", name, affix_string);
	} while (COLLISIONS_MAX_NUMBER > affix++);

	DBfree_result(result);
	zbx_free(affix_string);

	return ret;
}

static int DBpatch_reference_name(char **ref_name)
{
	int		i = 0, j, ret = FAIL;
	char		name[REFERENCE_MAX_LEN + 1];
	const char	*pattern = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
	DB_RESULT	result = NULL;;
	DB_ROW		row;

	name[REFERENCE_MAX_LEN] = '\0';

	do
	{
		for (j = 0; j < REFERENCE_MAX_LEN; j++)
			name[j] = pattern[rand() % (int)strlen(pattern)];

		DBfree_result(result);

		result = DBselect("select count(*)"
			" from widget_field"
			" where value_str='%s' and name='reference'",
			name);

		if (NULL == result || NULL == (row = DBfetch(result)))
			break;

		if (0 == strcmp("0", row[0]))
		{
			ret = SUCCEED;
			*ref_name = zbx_strdup(NULL, name);
			break;
		}

	} while (COLLISIONS_MAX_NUMBER > i++);

	DBfree_result(result);

	return ret;
}

static int	DBpatch_init_dashboard(zbx_db_dashboard_t *dashboard, char *name, uint64_t userid,
		int private)
{
	int	ret = SUCCEED;

	memset((void *)dashboard, 0, sizeof(zbx_db_dashboard_t));

	dashboard->userid = userid;
	dashboard->private = private;
	dashboard->display_period = 30;
	ret = DBpatch_dashboard_name(name, &dashboard->name);

	return ret;
}

static void	DBpatch_widget_field_free(zbx_db_widget_field_t *field)
{
	zbx_free(field->name);
	zbx_free(field->value_str);
	zbx_free(field);
}

static void	DBpatch_screen_item_free(zbx_db_screen_item_t *si)
{
	zbx_free(si->url);
	zbx_free(si->application);
	zbx_free(si);
}

static size_t	DBpatch_array_max_used_index(char *array, size_t arr_size)
{
	size_t	i, m = 0;

	for (i = 0; i < arr_size; i++)
	{
		if (0 != array[i])
			m = i;
	}

	return m;
}

static void DBpatch_normalize_screen_items_pos(zbx_vector_ptr_t *scr_items)
{
	char	used_x[SCREEN_MAX_COLS], used_y[SCREEN_MAX_ROWS];
	char	keep_x[SCREEN_MAX_COLS], keep_y[SCREEN_MAX_ROWS];
	int	i, n, x;

	memset((void *)used_x, 0, sizeof(used_x));
	memset((void *)used_y, 0, sizeof(used_y));
	memset((void *)keep_x, 0, sizeof(keep_x));
	memset((void *)keep_y, 0, sizeof(keep_y));

	for (i = 0; i < scr_items->values_num; i++)
	{
		zbx_db_screen_item_t	*c = (zbx_db_screen_item_t *)scr_items->values[i];

		for (n = c->x; n < c->x + c->colspan && n < SCREEN_MAX_COLS; n++)
			used_x[n] = 1;
		for (n = c->y; n < c->y + c->rowspan && n < SCREEN_MAX_ROWS; n++)
			used_y[n] = 1;

		keep_x[c->x] = 1;
		if (c->x + c->colspan < SCREEN_MAX_COLS)
			keep_x[c->x + c->colspan] = 1;
		keep_y[c->y] = 1;
		if (c->y + c->rowspan < SCREEN_MAX_ROWS)
			keep_y[c->y + c->rowspan] = 1;
	}

#define COMPRESS_SCREEN_ITEMS(axis, span, a_size)							\
													\
do {													\
	for (x = DBpatch_array_max_used_index(keep_ ## axis, a_size); x >= 0; x--)			\
	{												\
		if (0 != keep_ ## axis[x] && 0 != used_ ## axis[x])					\
			continue;									\
													\
		for (i = 0; i < scr_items->values_num; i++)						\
		{											\
			zbx_db_screen_item_t	*c = (zbx_db_screen_item_t *)scr_items->values[i];	\
													\
			if (x < c->axis)								\
				c->axis--;								\
													\
			if (x > c->axis && x < c->axis + c->span)					\
				c->span--;								\
		}											\
	}												\
} while (0)

	COMPRESS_SCREEN_ITEMS(x, colspan, SCREEN_MAX_COLS);
	COMPRESS_SCREEN_ITEMS(y, rowspan, SCREEN_MAX_ROWS);

#undef COMPRESS_SCREEN_ITEMS
}

static void	DBpatch_get_preferred_widget_size(zbx_db_screen_item_t *item, int *w, int *h)
{
	*w = item->width;
	*h = item->height;

	if (SCREEN_RESOURCE_LLD_GRAPH == item->resourcetype || SCREEN_RESOURCE_LLD_SIMPLE_GRAPH == item->resourcetype ||
			SCREEN_RESOURCE_GRAPH == item->resourcetype ||
			SCREEN_RESOURCE_SIMPLE_GRAPH == item->resourcetype)
	{
		*h += 215;	/* SCREEN_LEGEND_HEIGHT */
	}

	if (SCREEN_RESOURCE_PLAIN_TEXT == item->resourcetype || SCREEN_RESOURCE_HOST_INFO == item->resourcetype ||
			SCREEN_RESOURCE_TRIGGER_INFO == item->resourcetype ||
			SCREEN_RESOURCE_SERVER_INFO == item->resourcetype ||
			SCREEN_RESOURCE_ACTIONS == item->resourcetype ||
			SCREEN_RESOURCE_EVENTS == item->resourcetype ||
			SCREEN_RESOURCE_HOSTGROUP_TRIGGERS == item->resourcetype ||
			SCREEN_RESOURCE_SYSTEM_STATUS == item->resourcetype ||
			SCREEN_RESOURCE_HOST_TRIGGERS== item->resourcetype)
	{
		*h = 2 + 2 * MIN(25, item->elements) / 5;
	}
	else
		*h = (int)round((double)*h / 70);				/* WIDGET_ROW_HEIGHT */

	*w = (int)round((double)*w / 1920 * DASHBOARD_MAX_COLS);	/* DISPLAY_WIDTH */

	*w = MIN(DASHBOARD_MAX_COLS, MAX(1, *w));
	*h = MIN(DASHBOARD_WIDGET_MAX_ROWS, MAX(DASHBOARD_WIDGET_MIN_ROWS, *h));
}

static void	DBpatch_get_min_widget_size(zbx_db_screen_item_t *item, int *w, int *h)
{
	switch (item->resourcetype)
	{
		case SCREEN_RESOURCE_CLOCK:
			*w = 1; *h = 2;
			break;
		case SCREEN_RESOURCE_GRAPH:
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
		case SCREEN_RESOURCE_LLD_GRAPH:
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
		case SCREEN_RESOURCE_MAP:
			*w = 4; *h = 4;
			break;
		case SCREEN_RESOURCE_PLAIN_TEXT:
		case SCREEN_RESOURCE_URL:
		case SCREEN_RESOURCE_TRIGGER_INFO:
		case SCREEN_RESOURCE_ACTIONS:
		case SCREEN_RESOURCE_EVENTS:
		case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
		case SCREEN_RESOURCE_HOST_TRIGGERS:
			*w = 4; *h = 2;
			break;
		case SCREEN_RESOURCE_HOST_INFO:
			*w = 4; *h = 3;
			break;
		case SCREEN_RESOURCE_SERVER_INFO:
		case SCREEN_RESOURCE_SYSTEM_STATUS:
			*w = 4; *h = 4;
			break;
		case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
			*w = 4; *h = 7;
			break;
		case SCREEN_RESOURCE_DATA_OVERVIEW:
			*w = 4; *h = 5;
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown resource type %d", __func__, item->resourcetype);
	}
}

static char	*lw_array_to_str(zbx_vector_char2_t *v)
{
	static char	str[MAX_STRING_LEN];
	char		*ptr;
	int		i, max = MAX_STRING_LEN, len;

	ptr = str;
	len = zbx_snprintf(ptr, max, "[ ");
	ptr += len;
	max -= len;

	for (i = 0; 0 < max && i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
		{
			len = zbx_snprintf(ptr, max, "%d:%d ", i, (int)v->values[i]);
			ptr += len;
			max -= len;
		}
	}

	if (max > 1)
		strcat(ptr, "]");

	return str;
}

static void	lw_array_debug(char *pfx, zbx_vector_char2_t *v)
{
	zabbix_log(LOG_LEVEL_TRACE, "%s: %s", pfx, lw_array_to_str(v));
}

static void	int_array_debug(char *pfx, int *a, int alen, int emptyval)
{
	static char	str[MAX_STRING_LEN];
	char		*ptr;
	int		i, max = MAX_STRING_LEN, len;

	ptr = str;
	len = zbx_snprintf(ptr, max, "[ ");
	ptr += len;
	max -= len;

	for (i = 0; 0 < max && i < alen; i++)
	{
		if (emptyval != a[i])
		{
			len = zbx_snprintf(ptr, max, "%d:%d ", i, a[i]);
			ptr += len;
			max -= len;
		}
	}

	if (max > 1)
		strcat(ptr, "]");

	zabbix_log(LOG_LEVEL_TRACE, "%s: %s", pfx, str);
}

static zbx_vector_char2_t	*lw_array_create(void)
{
	zbx_vector_char2_t	*v;
	static char		fill[SCREEN_MAX_ROWS];

	if (0 == fill[0])
		memset(fill, POS_EMPTY, SCREEN_MAX_ROWS);

	v = (zbx_vector_char2_t *)malloc(sizeof(zbx_vector_char2_t));

	zbx_vector_char2_create(v);
	zbx_vector_char2_append_array(v, fill, SCREEN_MAX_ROWS);

	return v;
}

static void	lw_array_free(zbx_vector_char2_t *v)
{
	if (NULL != v)
	{
		zbx_vector_char2_destroy(v);
		zbx_free(v);
	}
}

static zbx_vector_char2_t	*lw_array_create_fill(int start, size_t num)
{
	size_t			i;
	zbx_vector_char2_t	*v;

	v = lw_array_create();

	for (i = start; i < start + num && i < (size_t)v->values_num; i++)
		v->values[i] = POS_TAKEN;

	return v;
}

static zbx_vector_char2_t	*lw_array_diff(zbx_vector_char2_t *a, zbx_vector_char2_t *b)
{
	int			i;
	zbx_vector_char2_t	*v;

	v = lw_array_create();

	for (i = 0; i < a->values_num; i++)
	{
		SKIP_EMPTY(a, i);
		if (POS_EMPTY == b->values[i])
			v->values[i] = a->values[i];
	}

	return v;
}

static zbx_vector_char2_t	*lw_array_intersect(zbx_vector_char2_t *a, zbx_vector_char2_t *b)
{
	int			i;
	zbx_vector_char2_t	*v;

	v = lw_array_create();

	for (i = 0; i < a->values_num; i++)
	{
		SKIP_EMPTY(a, i);
		if (POS_EMPTY != b->values[i])
			v->values[i] = a->values[i];
	}

	return v;
}

static int	lw_array_count(zbx_vector_char2_t *v)
{
	int	i, c = 0;

	for (i = 0; i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
			c++;
	}

	return c;
}

static int	lw_array_sum(zbx_vector_char2_t *v)
{
	int	i, c = 0;

	for (i = 0; i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
			c += v->values[i];
	}

	return c;
}

typedef struct
{
	int			index;	/* index for zbx_vector_scitem_dim2_t */
	zbx_vector_char2_t	*r_block;
}
sciitem_block_t;

static zbx_vector_char2_t	*sort_dimensions;

static int	DBpatch_block_compare_func(const void *d1, const void *d2)
{
	const sciitem_block_t	*i1 = *(const sciitem_block_t **)d1;
	const sciitem_block_t	*i2 = *(const sciitem_block_t **)d2;
	zbx_vector_char2_t	*diff1, *diff2;
	int			unsized_a, unsized_b;

	diff1 = lw_array_diff(i1->r_block, sort_dimensions);
	diff2 = lw_array_diff(i2->r_block, sort_dimensions);

	unsized_a = lw_array_count(diff1);
	unsized_b = lw_array_count(diff2);

	lw_array_free(diff1);
	lw_array_free(diff2);

	ZBX_RETURN_IF_NOT_EQUAL(unsized_a, unsized_b);

	return 0;
}

static zbx_vector_char2_t	*DBpatch_get_axis_dimensions(zbx_vector_scitem_dim2_t *scitems)
{
	int			i;
	zbx_vector_ptr_t	blocks;
	sciitem_block_t		*block;
	zbx_vector_char2_t	*dimensions;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&blocks);
	dimensions = lw_array_create();

	for (i = 0; i < scitems->values_num; i++)
	{
		block = (sciitem_block_t *)malloc(sizeof(sciitem_block_t));
		block->r_block = lw_array_create_fill(scitems->values[i].position, scitems->values[i].span);
		block->index = i;
		zbx_vector_ptr_append(&blocks, (void *)block);
	}

	sort_dimensions = dimensions;

	while (0 < blocks.values_num)
	{
		zbx_vector_char2_t	*block_dimensions, *block_unsized, *r_block;
		int			block_dimensions_sum, block_unsized_count, size_overflow, n;

		zbx_vector_ptr_sort(&blocks, DBpatch_block_compare_func);
		block = blocks.values[0];
		r_block = block->r_block;

		block_dimensions = lw_array_intersect(dimensions, r_block);
		block_dimensions_sum = lw_array_sum(block_dimensions);
		lw_array_free(block_dimensions);

		block_unsized = lw_array_diff(r_block, dimensions);
		block_unsized_count = lw_array_count(block_unsized);
		size_overflow = scitems->values[block->index].size - block_dimensions_sum;

		if (0 < block_unsized_count)
		{
			for (n = 0; n < block_unsized->values_num; n++)
			{
				SKIP_EMPTY(block_unsized, n);
				dimensions->values[n] = MAX(1, size_overflow / block_unsized_count);
				size_overflow -= dimensions->values[n];
				block_unsized_count--;
			}
		}
		else if (0 < size_overflow)
		{
			for (n = 0; n < r_block->values_num; n++)
			{
				double	factor;
				int	new_dimension;

				SKIP_EMPTY(r_block, n);
				factor = (double)(size_overflow + block_dimensions_sum) / block_dimensions_sum;
				new_dimension = (int)round(factor * dimensions->values[n]);
				block_dimensions_sum -= dimensions->values[n];
				size_overflow -= new_dimension - dimensions->values[n];
				dimensions->values[n] = new_dimension;
			}
		}

		lw_array_free(block->r_block);
		zbx_free(block);
		lw_array_free(block_unsized);
		zbx_vector_ptr_remove(&blocks, 0);
	}

	zbx_vector_ptr_destroy(&blocks);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): dim:%s", __func__, lw_array_to_str(dimensions));

	return dimensions;
}

/* modifies widget units in first argument */
static void	DBpatch_adjust_axis_dimensions(zbx_vector_char2_t *d, zbx_vector_char2_t *d_min, int target)
{
	int	dimensions_sum, i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s(): d:%s", __func__, lw_array_to_str(d));
	zabbix_log(LOG_LEVEL_TRACE, "  d_min:%s", lw_array_to_str(d_min));

	dimensions_sum = lw_array_sum(d);

	while (dimensions_sum != target)
	{
		int	potential_index = -1;
		double	potential_value;

		for (i = 0; i < d->values_num; i++)
		{
			double	value;

			SKIP_EMPTY(d, i);
			value = (double)d->values[i] / d_min->values[i];

			if (0 > potential_index ||
					(dimensions_sum > target && value > potential_value) ||
					(dimensions_sum < target && value < potential_value))
			{
				potential_index = i;
				potential_value = value;
			}
		}

		zabbix_log(LOG_LEVEL_TRACE, "dim_sum:%d pot_idx/val:%d/%.2lf", dimensions_sum,
				potential_index, potential_value);

		if (dimensions_sum > target && d->values[potential_index] == d_min->values[potential_index])
			break;

		if (dimensions_sum > target)
		{
			d->values[potential_index]--;
			dimensions_sum--;
		}
		else
		{
			d->values[potential_index]++;
			dimensions_sum++;
		}
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): d:%s", __func__, lw_array_to_str(d));
}

static void	DBpatch_get_dashboard_dimensions(zbx_vector_ptr_t *scr_items, zbx_vector_char2_t **x,
		zbx_vector_char2_t **y)
{
	zbx_vector_char2_t		*dim_x_pref, *dim_x_min;
	zbx_vector_char2_t		*dim_y_pref, *dim_y_min;
	zbx_vector_scitem_dim2_t	items_x_pref, items_y_pref;
	zbx_vector_scitem_dim2_t	items_x_min, items_y_min;
	int				i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_scitem_dim2_create(&items_x_pref);
	zbx_vector_scitem_dim2_create(&items_y_pref);
	zbx_vector_scitem_dim2_create(&items_x_min);
	zbx_vector_scitem_dim2_create(&items_y_min);

	for (i = 0; i < scr_items->values_num; i++)
	{
		int			pref_size_w, pref_size_h;
		int			min_size_w, min_size_h;
		zbx_screen_item_dim_t	item;
		zbx_db_screen_item_t	*si;

		si = scr_items->values[i];
		DBpatch_get_preferred_widget_size(si, &pref_size_w, &pref_size_h);
		DBpatch_get_min_widget_size(si, &min_size_w, &min_size_h);

		item.position = si->x;
		item.span = si->colspan;
		item.size = MAX(pref_size_w, min_size_w);
		zbx_vector_scitem_dim2_append(&items_x_pref, item);

		item.position = si->y;
		item.span = si->rowspan;
		item.size = MAX(pref_size_h, min_size_h);
		zbx_vector_scitem_dim2_append(&items_y_pref, item);

		item.position = si->x;
		item.span = si->colspan;
		item.size = min_size_w;
		zbx_vector_scitem_dim2_append(&items_x_min, item);

		item.position = si->y;
		item.span = si->rowspan;
		item.size = min_size_h;
		zbx_vector_scitem_dim2_append(&items_y_min, item);
	}

	dim_x_pref = DBpatch_get_axis_dimensions(&items_x_pref);
	dim_x_min = DBpatch_get_axis_dimensions(&items_x_min);

	zabbix_log(LOG_LEVEL_TRACE, "%s: dim_x_pref:%s", __func__, lw_array_to_str(dim_x_pref));
	zabbix_log(LOG_LEVEL_TRACE, "  dim_x_min:%s", lw_array_to_str(dim_x_min));

	DBpatch_adjust_axis_dimensions(dim_x_pref, dim_x_min, DASHBOARD_MAX_COLS);

	dim_y_pref = DBpatch_get_axis_dimensions(&items_y_pref);
	dim_y_min = DBpatch_get_axis_dimensions(&items_y_min);

	if (DASHBOARD_MAX_ROWS < lw_array_sum(dim_y_pref))
		DBpatch_adjust_axis_dimensions(dim_y_pref, dim_y_min, DASHBOARD_MAX_ROWS);

	lw_array_free(dim_x_min);
	lw_array_free(dim_y_min);
	zbx_vector_scitem_dim2_destroy(&items_x_pref);
	zbx_vector_scitem_dim2_destroy(&items_y_pref);
	zbx_vector_scitem_dim2_destroy(&items_x_min);
	zbx_vector_scitem_dim2_destroy(&items_y_min);

	*x = dim_x_pref;
	*y = dim_y_pref;

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): x:%s y:%s", __func__, lw_array_to_str(*x), lw_array_to_str(*y));
}

static zbx_db_widget_field_t	*DBpatch_make_widget_field(int type, char *name, void *value)
{
	zbx_db_widget_field_t	*wf;

	wf = (zbx_db_widget_field_t *)zbx_calloc(NULL, 1, sizeof(zbx_db_widget_field_t));
	wf->name = zbx_strdup(NULL, name);
	wf->type = type;

	switch (type)
	{
		case ZBX_WIDGET_FIELD_TYPE_INT32:
			wf->value_int = *((int *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_STR:
			wf->value_str = zbx_strdup(NULL, (char *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_GROUP:
			wf->value_groupid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_HOST:
			wf->value_hostid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_ITEM:
		case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
			wf->value_itemid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_GRAPH:
		case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
			wf->value_graphid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_MAP:
			wf->value_sysmapid = *((uint64_t *)value);
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown field type: %d", __func__, type);
	}

	if (NULL == wf->value_str)
		wf->value_str = zbx_strdup(NULL, "");

	return wf;
}

static void DBpatch_widget_from_screen_item(zbx_db_screen_item_t *si, zbx_db_widget_t *w, zbx_vector_ptr_t *fields)
{
	int			tmp;
	char			*reference = NULL;
	zbx_db_widget_field_t	*f;

	w->name = zbx_strdup(NULL, "");
	w->view_mode = 0;	/* ZBX_WIDGET_VIEW_MODE_NORMAL */

#define ADD_FIELD(a, b, c)				\
							\
do {							\
	f = DBpatch_make_widget_field(a, b, c);		\
	zbx_vector_ptr_append(fields, (void *)f);	\
} while (0)

	switch (si->resourcetype)
	{
		case SCREEN_RESOURCE_CLOCK:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_CLOCK);

			/* here are below in this switch we add only those fields that are not */
			/* considered default by frontend API */

			if (0 != si->style)	/* style 0 is default, don't add */
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "time_type", (void *)&si->style);
			if (2 == si->style)	/* TIME_TYPE_HOST */
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemid", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_CLASSIC);
			/* source_type = ZBX_WIDGET_FIELD_RESOURCE_GRAPH (0); don't add because it's default */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GRAPH, "graphid", (void *)&si->resourceid);
			tmp = 1;
			if (1 == si->dynamic)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "dynamic", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_CLASSIC);
			tmp = 1;	/* source_type = ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "source_type", (void *)&tmp);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemid", (void *)&si->resourceid);
			tmp = 1;
			if (1 == si->dynamic)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "dynamic", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_LLD_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE);
			/* source_type = ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE (2); don't add because it's default */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE, "graphid", (void *)&si->resourceid);
			/* add field "columns" because the default value is 2 */
			tmp = 1;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "columns", (void *)&tmp);
			tmp = 1;
			if (1 == si->dynamic)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "dynamic", (void *)&tmp);
			/* don't add field "rows" because 1 is default */
			break;
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE);
			tmp = 3;	/* source_type = ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "source_type", (void *)&tmp);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, "itemid", (void *)&si->resourceid);
			tmp = 1;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "columns", (void *)&tmp);
			tmp = 1;
			if (1 == si->dynamic)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "dynamic", (void *)&tmp);
			/* don't add field "rows" because 1 is default */
			break;
		case SCREEN_RESOURCE_PLAIN_TEXT:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PLAIN_TEXT);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemids", (void *)&si->resourceid);
			if (0 != si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_as_html", (void *)&si->style);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			tmp = 1;
			if (1 == si->dynamic)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "dynamic", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_URL:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_URL);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "url", (void *)si->url);
			tmp = 1;
			if (1 == si->dynamic)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "dynamic", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_ACTIONS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_ACTIONS);
			if (4 != si->sort_triggers)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "sort_triggers", (void *)&si->sort_triggers);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			break;
		case SCREEN_RESOURCE_DATA_OVERVIEW:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_DATA_OVERVIEW);
			tmp = 1;
			if (1 == si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "style", (void *)&tmp);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			if ('\0' != *si->application)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "application", (void *)si->application);
			break;
		case SCREEN_RESOURCE_EVENTS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PROBLEMS);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			tmp = 2;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PROBLEMS);
			if (0 != si->sort_triggers)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "sort_triggers", (void *)&si->sort_triggers);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			if (0 != si->resourceid)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			tmp = 3;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show", (void *)&tmp);
			tmp = 0;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_timeline", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_HOST_INFO:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_HOST_INFO);
			tmp = 1;
			if (1 == si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "layout", (void *)&tmp);
			if (0 != si->resourceid)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_HOST_TRIGGERS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PROBLEMS);
			if (0 != si->sort_triggers)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "sort_triggers", (void *)&si->sort_triggers);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			if (0 != si->resourceid)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_HOST, "hostids", (void *)&si->resourceid);
			tmp = 3;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show", (void *)&tmp);
			tmp = 0;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_timeline", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_MAP:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_MAP);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_MAP, "sysmapid", (void *)&si->resourceid);
			if (SUCCEED == DBpatch_reference_name(&reference))
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "reference", (void *)reference);
			zbx_free(reference);
			break;
		case SCREEN_RESOURCE_SYSTEM_STATUS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_SYSTEM_STATUS);
			break;
		case SCREEN_RESOURCE_SERVER_INFO:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_SERVER_INFO);
			break;
		case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_TRIGGER_OVERVIEW);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			if ('\0' != *si->application)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "application", (void *)si->application);
			tmp = 1;
			if (1 == si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "style", (void *)&tmp);
			tmp = 2;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_TRIGGER_INFO:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_SYSTEM_STATUS);
			if (0 != si->resourceid)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			tmp = 1;
			if (1 == si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "layout", (void *)&tmp);
			tmp = 1;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_type", (void *)&tmp);
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown screen resource type: %d", __func__,
					si->resourcetype);
	}
#undef ADD_FIELD
}

static char	*DBpatch_resourcetype_str(int rtype)
{
	switch (rtype)
	{
		case SCREEN_RESOURCE_CLOCK:
			return "clock";
		case SCREEN_RESOURCE_GRAPH:
			return "graph";
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
			return "simplegraph";
		case SCREEN_RESOURCE_LLD_GRAPH:
			return "lldgraph";
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
			return "lldsimplegraph";
		case SCREEN_RESOURCE_PLAIN_TEXT:
			return "plaintext";
		case SCREEN_RESOURCE_URL:
			return "url";
		/* additional types */
		case SCREEN_RESOURCE_MAP:
			return "map";
		case SCREEN_RESOURCE_HOST_INFO:
			return "host info";
		case SCREEN_RESOURCE_TRIGGER_INFO:
			return "trigger info";
		case SCREEN_RESOURCE_SERVER_INFO:
			return "server info";
		case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
			return "trigger overview";
		case SCREEN_RESOURCE_DATA_OVERVIEW:
			return "data overview";
		case SCREEN_RESOURCE_ACTIONS:
			return "action";
		case SCREEN_RESOURCE_EVENTS:
			return "events";
		case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
			return "hostgroup triggers";
		case SCREEN_RESOURCE_SYSTEM_STATUS:
			return "system status";
		case SCREEN_RESOURCE_HOST_TRIGGERS:
			return "host triggers";
	}

	return "*unknown*";
}

static void	DBpatch_trace_screen_item(zbx_db_screen_item_t *item)
{
	zabbix_log(LOG_LEVEL_TRACE, "    screenitemid:" ZBX_FS_UI64 " screenid:" ZBX_FS_UI64,
			item->screenitemid, item->screenid);
	zabbix_log(LOG_LEVEL_TRACE, "        resourcetype: %s resourceid:" ZBX_FS_UI64,
			DBpatch_resourcetype_str(item->resourcetype), item->resourceid);
	zabbix_log(LOG_LEVEL_TRACE, "        w/h: %dx%d (x,y): (%d,%d) (c,rspan): (%d,%d)",
			item->width, item->height, item->x, item->y, item->colspan, item->rowspan);
}

static void	DBpatch_trace_widget(zbx_db_widget_t *w)
{
	zabbix_log(LOG_LEVEL_TRACE, "    widgetid:" ZBX_FS_UI64 " dbid:" ZBX_FS_UI64 " type:%s",
			w->widgetid, w->dashboardid, w->type);
	zabbix_log(LOG_LEVEL_TRACE, "    widget type: %s w/h: %dx%d (x,y): (%d,%d)",
			w->type, w->width, w->height, w->x, w->y);
}

/* adds new dashboard to the DB, sets new dashboardid in the struct */
static int 	DBpatch_add_dashboard(zbx_db_dashboard_t *dashboard)
{
	char	*name_esc;
	int	res;

	dashboard->dashboardid = DBget_maxid("dashboard");
	name_esc = DBdyn_escape_string(dashboard->name);

	zabbix_log(LOG_LEVEL_TRACE, "adding dashboard id:" ZBX_FS_UI64, dashboard->dashboardid);

	res = DBexecute("insert into dashboard (dashboardid,name,userid,private,display_period) values "
			"("ZBX_FS_UI64 ",'%s',"ZBX_FS_UI64 ",%d,%d)",
			dashboard->dashboardid, name_esc, dashboard->userid, dashboard->private,
			dashboard->display_period);

	zbx_free(name_esc);

	return ZBX_DB_OK > res ? FAIL : SUCCEED;
}

/* adds new dashboard page to the DB, sets new dashboard_pageid in the struct */
static int 	DBpatch_add_dashboard_page(zbx_db_dashboard_page_t *dashboard_page, uint64_t dashboardid, char *name,
		int display_period, int sortorder)
{
	int	res;

	dashboard_page->dashboard_pageid = DBget_maxid("dashboard_page");
	dashboard_page->dashboardid = dashboardid;

	zabbix_log(LOG_LEVEL_TRACE, "adding dashboard_page id:" ZBX_FS_UI64, dashboard_page->dashboard_pageid);

	res = DBexecute("insert into dashboard_page (dashboard_pageid,dashboardid,name,display_period,sortorder)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ",'%s',%d,%d)",
			dashboard_page->dashboard_pageid, dashboardid, name, display_period, sortorder);

	return ZBX_DB_OK > res ? FAIL : SUCCEED;
}

/* adds new widget and widget fields to the DB */
static int	DBpatch_add_widget(uint64_t dashboardid, zbx_db_widget_t *widget, zbx_vector_ptr_t *fields)
{
	uint64_t	new_fieldid;
	int		i, ret = SUCCEED;
	char		*name_esc;

	widget->widgetid = DBget_maxid("widget");
	widget->dashboardid = dashboardid;
	name_esc = DBdyn_escape_string(widget->name);

	zabbix_log(LOG_LEVEL_TRACE, "adding widget id: " ZBX_FS_UI64 ", type: %s", widget->widgetid, widget->type);


	if (ZBX_DB_OK > DBexecute("insert into widget (widgetid,dashboard_pageid,type,name,x,y,width,height,view_mode) "
			"values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s',%d,%d,%d,%d,%d)",
			widget->widgetid, widget->dashboardid, widget->type, name_esc,
			widget->x, widget->y, widget->width, widget->height, widget->view_mode))
	{
		ret = FAIL;
	}

	zbx_free(name_esc);

	if (SUCCEED == ret && 0 < fields->values_num)
		new_fieldid = DBget_maxid_num("widget_field", fields->values_num);

	for (i = 0; SUCCEED == ret && i < fields->values_num; i++)
	{
		char			s1[ZBX_MAX_UINT64_LEN + 1], s2[ZBX_MAX_UINT64_LEN + 1],
					s3[ZBX_MAX_UINT64_LEN + 1], s4[ZBX_MAX_UINT64_LEN + 1],
					s5[ZBX_MAX_UINT64_LEN + 1], *url_esc;
		zbx_db_widget_field_t	*f;

		f = (zbx_db_widget_field_t *)fields->values[i];
		url_esc = DBdyn_escape_string(f->value_str);

		if (0 != f->value_itemid)
			zbx_snprintf(s1, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_itemid);
		else
			zbx_snprintf(s1, ZBX_MAX_UINT64_LEN + 1, "null");

		if (0 != f->value_graphid)
			zbx_snprintf(s2, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_graphid);
		else
			zbx_snprintf(s2, ZBX_MAX_UINT64_LEN + 1, "null");

		if (0 != f->value_groupid)
			zbx_snprintf(s3, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_groupid);
		else
			zbx_snprintf(s3, ZBX_MAX_UINT64_LEN + 1, "null");

		if (0 != f->value_hostid)
			zbx_snprintf(s4, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_hostid);
		else
			zbx_snprintf(s4, ZBX_MAX_UINT64_LEN + 1, "null");

		if (0 != f->value_sysmapid)
			zbx_snprintf(s5, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_sysmapid);
		else
			zbx_snprintf(s5, ZBX_MAX_UINT64_LEN + 1, "null");

		if (ZBX_DB_OK > DBexecute("insert into widget_field (widget_fieldid,widgetid,type,name,value_int,"
				"value_str,value_itemid,value_graphid,value_groupid,value_hostid,value_sysmapid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s',%d,'%s',%s,%s,%s,%s,%s)",
				new_fieldid++, widget->widgetid, f->type, f->name, f->value_int, url_esc,
				s1, s2, s3, s4, s5))
		{
			ret = FAIL;
		}

		zbx_free(url_esc);
	}

	return ret;
}

static int DBpatch_set_permissions_screen(uint64_t dashboardid, uint64_t screenid)
{
	int		ret = SUCCEED, permission;
	uint64_t	userid, usrgrpid, dashboard_userid, dashboard_usrgrpid;
	DB_RESULT	result;
	DB_ROW		row;

	dashboard_userid = DBget_maxid("dashboard_user");

	result = DBselect("select userid, permission from screen_user where screenid=" ZBX_FS_UI64, screenid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(userid, row[0]);
		permission = atoi(row[1]);

		if (ZBX_DB_OK > DBexecute("insert into dashboard_user (dashboard_userid,dashboardid,userid,permission)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ","ZBX_FS_UI64 ", %d)",
			dashboard_userid++, dashboardid, userid, permission))
		{
			ret = FAIL;
			goto out;
		}
	}

	DBfree_result(result);

	dashboard_usrgrpid = DBget_maxid("dashboard_usrgrp");

	result = DBselect("select usrgrpid,permission from screen_usrgrp where screenid=" ZBX_FS_UI64, screenid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(usrgrpid, row[0]);
		permission = atoi(row[1]);

		if (ZBX_DB_OK > DBexecute("insert into dashboard_usrgrp (dashboard_usrgrpid,dashboardid,usrgrpid,permission)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ","ZBX_FS_UI64 ", %d)",
			dashboard_usrgrpid++, dashboardid, usrgrpid, permission))
		{
			ret = FAIL;
			goto out;
		}
	}
out:
	DBfree_result(result);

	return ret;
}

static int DBpatch_set_permissions_slideshow(uint64_t dashboardid, uint64_t slideshowid)
{
	int		ret = SUCCEED, permission;
	uint64_t	userid, usrgrpid, dashboard_userid, dashboard_usrgrpid;
	DB_RESULT	result;
	DB_ROW		row;

	dashboard_userid = DBget_maxid("dashboard_user");

	result = DBselect("select userid,permission from slideshow_user where slideshowid=" ZBX_FS_UI64, slideshowid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(userid, row[0]);
		permission = atoi(row[1]);

		if (ZBX_DB_OK > DBexecute("insert into dashboard_user (dashboard_userid,dashboardid,userid,permission)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ","ZBX_FS_UI64 ", %d)",
			dashboard_userid++, dashboardid, userid, permission))
		{
			ret = FAIL;
			goto out;
		}
	}

	DBfree_result(result);

	dashboard_usrgrpid = DBget_maxid("dashboard_usrgrp");

	result = DBselect("select usrgrpid,permission from slideshow_usrgrp where slideshowid=" ZBX_FS_UI64,
			slideshowid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(usrgrpid, row[0]);
		permission = atoi(row[1]);

		if (ZBX_DB_OK > DBexecute("insert into dashboard_usrgrp (dashboard_usrgrpid,dashboardid,usrgrpid,permission)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ","ZBX_FS_UI64 ", %d)",
			dashboard_usrgrpid++, dashboardid, usrgrpid, permission))
		{
			ret = FAIL;
			goto out;
		}
	}
out:
	DBfree_result(result);

	return ret;
}


static int	DBpatch_delete_screen(uint64_t screenid)
{
	if (ZBX_DB_OK > DBexecute("delete from screens_items where screenid=" ZBX_FS_UI64, screenid))
		return FAIL;

	if (ZBX_DB_OK > DBexecute("delete from screens where screenid=" ZBX_FS_UI64, screenid))
		return FAIL;

	return SUCCEED;
}

#define OFFSET_ARRAY_SIZE	(SCREEN_MAX_ROWS + 1)

static int	DBpatch_convert_screen_items(DB_RESULT result, uint64_t id)
{
	DB_ROW			row;
	int			i, ret = SUCCEED;
	zbx_db_screen_item_t	*scr_item;
	zbx_vector_ptr_t	screen_items;
	zbx_vector_char2_t	*dim_x, *dim_y;
	int			offsets_x[OFFSET_ARRAY_SIZE], offsets_y[OFFSET_ARRAY_SIZE];

	zbx_vector_ptr_create(&screen_items);

	while (NULL != (row = DBfetch(result)))
	{
		scr_item = (zbx_db_screen_item_t*)zbx_calloc(NULL, 1, sizeof(zbx_db_screen_item_t));

		ZBX_DBROW2UINT64(scr_item->screenitemid, row[0]);
		ZBX_DBROW2UINT64(scr_item->screenid, row[1]);
		scr_item->resourcetype = atoi(row[2]);
		ZBX_DBROW2UINT64(scr_item->resourceid, row[3]);
		scr_item->width = atoi(row[4]);
		scr_item->height = atoi(row[5]);
		scr_item->x = atoi(row[6]);
		scr_item->y = atoi(row[7]);
		scr_item->colspan = atoi(row[8]);
		scr_item->rowspan = atoi(row[9]);
		scr_item->elements = atoi(row[10]);
		scr_item->style = atoi(row[11]);
		scr_item->url = zbx_strdup(NULL, row[12]);
		scr_item->sort_triggers = atoi(row[13]);
		scr_item->application = zbx_strdup(NULL, row[14]);
		scr_item->dynamic = atoi(row[15]);

		DBpatch_trace_screen_item(scr_item);

		zbx_vector_ptr_append(&screen_items, (void *)scr_item);
	}

	if (screen_items.values_num > 0)
	{
		zabbix_log(LOG_LEVEL_TRACE, "total %d screen items", screen_items.values_num);

		DBpatch_normalize_screen_items_pos(&screen_items);
		DBpatch_get_dashboard_dimensions(&screen_items, &dim_x, &dim_y);

		lw_array_debug("dim_x", dim_x);
		lw_array_debug("dim_y", dim_y);

		offsets_x[0] = 0;
		offsets_y[0] = 0;
		for (i = 1; i < OFFSET_ARRAY_SIZE; i++)
		{
			offsets_x[i] = -1;
			offsets_y[i] = -1;
		}

		for (i = 0; i < dim_x->values_num; i++)
		{
			if (POS_EMPTY != dim_x->values[i])
				offsets_x[i + 1] = i == 0 ? dim_x->values[i] : offsets_x[i] + dim_x->values[i];
			if (POS_EMPTY != dim_y->values[i])
				offsets_y[i + 1] = i == 0 ? dim_y->values[i] : offsets_y[i] + dim_y->values[i];
		}

		int_array_debug("offsets_x", offsets_x, OFFSET_ARRAY_SIZE, -1);
		int_array_debug("offsets_y", offsets_y, OFFSET_ARRAY_SIZE, -1);
	}


	for (i = 0; SUCCEED == ret && i < screen_items.values_num; i++)
	{
		int			offset_idx_x, offset_idx_y;
		zbx_db_widget_t		w;
		zbx_vector_ptr_t	widget_fields;
		zbx_db_screen_item_t	*si;

		si = screen_items.values[i];

		offset_idx_x = si->x + si->colspan;
		if (offset_idx_x > OFFSET_ARRAY_SIZE - 1)
		{
			offset_idx_x = OFFSET_ARRAY_SIZE - 1;
			zabbix_log(LOG_LEVEL_WARNING, "config error, x screen size overflow for item " ZBX_FS_UI64,
					si->screenitemid);
		}

		offset_idx_y = si->y + si->rowspan;
		if (offset_idx_y > OFFSET_ARRAY_SIZE - 1)
		{
			offset_idx_y = OFFSET_ARRAY_SIZE - 1;
			zabbix_log(LOG_LEVEL_WARNING, "config error, y screen size overflow for item " ZBX_FS_UI64,
					si->screenitemid);
		}

		memset((void *)&w, 0, sizeof(zbx_db_widget_t));
		w.x = offsets_x[si->x];
		w.y = offsets_y[si->y];
		w.width = offsets_x[offset_idx_x] - offsets_x[si->x];
		w.height = offsets_y[offset_idx_y] - offsets_y[si->y];

		/* skip screen items not fitting on the dashboard */
		if (w.x + w.width > DASHBOARD_MAX_COLS || w.y + w.height > DASHBOARD_MAX_ROWS)
		{
			zabbix_log(LOG_LEVEL_WARNING, "skipping screenitemid " ZBX_FS_UI64
					" (too wide, tall or offscreen)", si->screenitemid);
			continue;
		}

		zbx_vector_ptr_create(&widget_fields);

		DBpatch_widget_from_screen_item(si, &w, &widget_fields);

		ret = DBpatch_add_widget(id, &w, &widget_fields);

		DBpatch_trace_widget(&w);

		zbx_vector_ptr_clear_ext(&widget_fields, (zbx_clean_func_t)DBpatch_widget_field_free);
		zbx_vector_ptr_destroy(&widget_fields);
		zbx_free(w.name);
		zbx_free(w.type);
	}

	if (screen_items.values_num > 0)
	{
		lw_array_free(dim_x);
		lw_array_free(dim_y);
	}

	zbx_vector_ptr_clear_ext(&screen_items, (zbx_clean_func_t)DBpatch_screen_item_free);
	zbx_vector_ptr_destroy(&screen_items);

	return ret;
}

static int	DBpatch_convert_screen(uint64_t screenid, char *name, uint64_t userid, int private)
{
	DB_RESULT		result;
	int			ret;
	zbx_db_dashboard_t	dashboard;
	zbx_db_dashboard_page_t	dashboard_page;

	result = DBselect(
			"select screenitemid,screenid,resourcetype,resourceid,width,height,x,y,colspan,rowspan"
			",elements,style,url,sort_triggers,application,dynamic from screens_items"
			" where screenid=" ZBX_FS_UI64, screenid);

	if (NULL == result)
		return FAIL;

	if (SUCCEED != DBpatch_init_dashboard(&dashboard, name, userid, private))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot convert screen '%s'due to name collision.", name);
		ret = FAIL;
		goto out;
	}

	ret = DBpatch_add_dashboard(&dashboard);

	if (SUCCEED == ret)
		ret = DBpatch_add_dashboard_page(&dashboard_page, dashboard.dashboardid, "", 0, 0);

	if (SUCCEED == ret)
		ret = DBpatch_convert_screen_items(result, dashboard_page.dashboard_pageid);

	if (SUCCEED == ret)
		ret = DBpatch_set_permissions_screen(dashboard.dashboardid, screenid);

	zbx_free(dashboard.name);
out:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_delay_routine(const char *screen_delay, int *dashboard_delay)
{
	int	delays[] = {10, 30, 60, 120, 600, 1800, 3600};
	int	i, imax, tmp;

	if (FAIL == is_time_suffix(screen_delay, &tmp, ZBX_LENGTH_UNLIMITED))
		return FAIL;

	imax = (int)ARRSIZE(delays);

	if (0 >= tmp)
	{
		tmp = 0;
	}
	else if (tmp <= delays[0])
	{
		tmp = delays[0];
	}
	else if (tmp >= delays[imax - 1])
	{
		tmp = delays[imax - 1];
	}
	else
	{
		for (i = 0; i < imax - 1; i++)
		{
			if (tmp >= delays[i] && tmp <= delays[i + 1])
				tmp = ((tmp - delays[i]) >= (delays[i + 1] - tmp)) ? delays[i + 1] : delays[i];
		}
	}

	*dashboard_delay = tmp;

	return SUCCEED;
}

static int	DBpatch_convert_slideshow(uint64_t slideshowid, char *name, int delay, uint64_t userid, int private)
{
	int			ret;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_db_dashboard_t	dashboard;
	DB_RESULT		result;
	DB_ROW			row;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select slideid,screenid,step,delay"
			" from slides"
			" where slideshowid=" ZBX_FS_UI64
			" order by step asc", slideshowid);

	result = DBselectN(sql, 50);
	zbx_free(sql);

	if (NULL == result)
		return FAIL;

	if (SUCCEED != DBpatch_init_dashboard(&dashboard, name, userid, private))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot convert screen '%s'due to name collision.", name);
		ret = FAIL;
		goto exit;
	}

	dashboard.display_period = delay;

	if (SUCCEED != (ret = DBpatch_add_dashboard(&dashboard)))
		goto out;

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		int			step, page_delay;
		zbx_db_dashboard_page_t	dashboard_page;
		DB_RESULT		result2, result3;
		DB_ROW			row2;
		uint64_t 		screenid;

		step = atoi(row[2]);
		page_delay = atoi(row[3]);
		ZBX_DBROW2UINT64(screenid, row[1]);

		result2 = DBselect("select name from screens where screenid=" ZBX_FS_UI64, screenid);

		if (NULL == result2 || NULL == (row2 = DBfetch(result2)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot convert screen " ZBX_FS_UI64, screenid);
			DBfree_result(result2);
			continue;
		}

		if (SUCCEED != (ret = DBpatch_add_dashboard_page(&dashboard_page, dashboard.dashboardid,
				row2[0], page_delay, step)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot convert screen " ZBX_FS_UI64, screenid);
			DBfree_result(result2);
			continue;
		}

		result3 = DBselect(
			"select screenitemid,screenid,resourcetype,resourceid,width,height,x,y,colspan,rowspan"
			",elements,style,url,sort_triggers,application,dynamic from screens_items"
			" where screenid=" ZBX_FS_UI64, screenid);

		if (NULL != result3)
			DBpatch_convert_screen_items(result3, dashboard_page.dashboard_pageid);

		DBfree_result(result2);
		DBfree_result(result3);
	}

out:
	ret = DBpatch_set_permissions_slideshow(dashboard.dashboardid, slideshowid);

	zbx_free(dashboard.name);
exit:
	DBfree_result(result);

	return ret;
}

#undef OFFSET_ARRAY_SIZE

static int	DBpatch_5030070(void)
{
	int		ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	result = DBselect("select slideshowid,name,delay,userid,private from slideshows");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		uint64_t	slideshowid, userid;
		int		private, delay;

		ZBX_DBROW2UINT64(slideshowid, row[0]);
		ZBX_DBROW2UINT64(userid, row[3]);
		private = atoi(row[4]);

		if (FAIL == (ret = DBpatch_delay_routine(row[2], &delay)))
			break;

		ret = DBpatch_convert_slideshow(slideshowid, row[1], delay, userid, private);
	}

	DBfree_result(result);

	return ret;
}

static int	DBpatch_5030071(void)
{
	int		ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	result = DBselect("select screenid,name,userid,private from screens");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		uint64_t	screenid, userid;
		int		private;

		ZBX_DBROW2UINT64(screenid, row[0]);
		ZBX_DBROW2UINT64(userid, row[2]);
		private = atoi(row[3]);

		if (SUCCEED == (ret = DBpatch_convert_screen(screenid, row[1], userid, private)))
			ret = DBpatch_delete_screen(screenid);
	}

	DBfree_result(result);

	return ret;
}

#undef DASHBOARD_MAX_COLS
#undef DASHBOARD_MAX_ROWS
#undef DASHBOARD_WIDGET_MIN_ROWS
#undef DASHBOARD_WIDGET_MAX_ROWS

#undef SCREEN_MAX_ROWS
#undef SCREEN_MAX_COLS
#undef SCREEN_RESOURCE_CLOCK
#undef SCREEN_RESOURCE_GRAPH
#undef SCREEN_RESOURCE_SIMPLE_GRAPH
#undef SCREEN_RESOURCE_LLD_GRAPH
#undef SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
#undef SCREEN_RESOURCE_PLAIN_TEXT
#undef SCREEN_RESOURCE_URL

#undef SCREEN_RESOURCE_MAP
#undef SCREEN_RESOURCE_HOST_INFO
#undef SCREEN_RESOURCE_TRIGGER_INFO
#undef SCREEN_RESOURCE_SERVER_INFO
#undef SCREEN_RESOURCE_TRIGGER_OVERVIEW
#undef SCREEN_RESOURCE_DATA_OVERVIEW
#undef SCREEN_RESOURCE_ACTIONS
#undef SCREEN_RESOURCE_EVENTS
#undef SCREEN_RESOURCE_HOSTGROUP_TRIGGERS
#undef SCREEN_RESOURCE_SYSTEM_STATUS
#undef SCREEN_RESOURCE_HOST_TRIGGERS

#undef ZBX_WIDGET_FIELD_TYPE_INT32
#undef ZBX_WIDGET_FIELD_TYPE_STR
#undef ZBX_WIDGET_FIELD_TYPE_ITEM
#undef ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE
#undef ZBX_WIDGET_FIELD_TYPE_GRAPH
#undef ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE

#undef ZBX_WIDGET_FIELD_RESOURCE_GRAPH
#undef ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH
#undef ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE
#undef ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE

#undef ZBX_WIDGET_TYPE_CLOCK
#undef ZBX_WIDGET_TYPE_GRAPH_CLASSIC
#undef ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE
#undef ZBX_WIDGET_TYPE_PLAIN_TEXT
#undef ZBX_WIDGET_TYPE_URL
#undef POS_EMPTY
#undef POS_TAKEN
#undef SKIP_EMPTY

static int	DBpatch_5030072(void)
{
	return DBdrop_table("slides");
}

static int	DBpatch_5030073(void)
{
	return DBdrop_table("slideshow_user");
}

static int	DBpatch_5030074(void)
{
	return DBdrop_table("slideshow_usrgrp");
}

static int	DBpatch_5030075(void)
{
	return DBdrop_table("slideshows");

}

static int	DBpatch_5030076(void)
{
	return DBdrop_table("screen_usrgrp");
}

static int	DBpatch_5030077(void)
{
	return DBdrop_table("screens_items");
}

static int	DBpatch_5030078(void)
{
	return DBdrop_table("screen_user");
}

static int	DBpatch_5030079(void)
{
	return DBdrop_table("screens");
}

static int	DBpatch_5030080(void)
{
	if (ZBX_DB_OK > DBexecute("delete from widget where type='favscreens'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030081(void)
{
	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.favorite.screenids'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030082(void)
{
	if (ZBX_DB_OK > DBexecute("delete from role_rule"
			" where name like 'api.method.%%'"
			" and (value_str like 'screen.%%' or value_str like 'screenitem.%%')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030083(void)
{
	if (ZBX_DB_OK > DBexecute("delete from role_rule where name='ui.monitoring.screens'"))
		return FAIL;

	return SUCCEED;
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

DBPATCH_END()
