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

#undef HOST_STATUS_TEMPLATE
#define HOST_STATUS_TEMPLATE		3
#undef ZBX_FLAG_DISCOVERY_NORMAL
#define ZBX_FLAG_DISCOVERY_NORMAL	0x00
#undef ZBX_FLAG_DISCOVERY_RULE
#define ZBX_FLAG_DISCOVERY_RULE		0x01
#undef ZBX_FLAG_DISCOVERY_PROTOTYPE
#define ZBX_FLAG_DISCOVERY_PROTOTYPE	0x02

#define ZBX_FIELD_UUID			{"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0}

static int	DBpatch_5030081(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("items", &field);
}

static int	DBpatch_5030082(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("hosts", &field);
}

static int	DBpatch_5030083(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("triggers", &field);
}

static int	DBpatch_5030084(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030085(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("graphs", &field);
}

static int	DBpatch_5030086(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("hstgrp", &field);
}

static int	DBpatch_5030087(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("httptest", &field);
}

static int	DBpatch_5030088(void)
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
			MIN_TEMPLATE_NAME_LEN <= strlen(new))
	{
		ptr = zbx_strdup(ptr, new);
	}

	return ptr;
}

static int	DBpatch_5030089(void)
{
	int		ret = SUCCEED;
	char		*name, *uuid, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select h.hostid,h.host"
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

static int	DBpatch_5030090(void)
{
	int		ret = SUCCEED;
	char		*name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select i.itemid,i.key_,h.host"
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

static int	DBpatch_5030091(void)
{
	int		ret = SUCCEED;
	char		*uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

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

		for (i = 0; i < 2; i++)
		{
			trigger_expr = zbx_strdup(NULL, row[i + 2]);
			pexpr = pexpr_f = (const char *)trigger_expr;

			while (SUCCEED == get_N_functionid(pexpr, 1, &functionid, (const char **)&pexpr_s, &pexpr_f))
			{
				trigger_expr[pexpr_s - trigger_expr] = '\0';

				result2 = DBselect(
						"select h.host,i.key_,f.name,f.parameter"
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

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset,"%s", row[1]);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset,"%s", expression);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);
		zbx_free(expression);
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

static int	DBpatch_5030092(void)
{
	int		ret = SUCCEED;
	char		*host_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

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

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset,"%s", row[1]);

		result2 = DBselect(
				"select h.host"
				" from graphs_items gi"
				" join items i on i.itemid=gi.itemid"
				" join hosts h on h.hostid=i.hostid"
				" where gi.graphid=%s",
				row[0]);

		while (NULL != (row2 = DBfetch(result2)))
		{
			host_name = zbx_strdup(NULL, row2[0]);
			host_name = update_template_name(host_name);

			zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", host_name);
			zbx_free(host_name);
		}

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set uuid='%s'"
				" where graphid=%s;\n", uuid, row[0]);
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

static int	DBpatch_5030093(void)
{
	int		ret = SUCCEED;
	char		*template_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select d.dashboardid,d.name,h.host"
			" from dashboard d"
			" join hosts h on h.hostid=d.templateid"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		template_name = zbx_strdup(NULL, row[2]);
		template_name = update_template_name(template_name);
		seed = zbx_dsprintf(seed, "%s%s", template_name, row[1]);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update dashboard set uuid='%s' where dashboardid=%s;\n", uuid, row[0]);
		zbx_free(template_name);
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

static int	DBpatch_5030094(void)
{
	int		ret = SUCCEED;
	char		*template_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select ht.httptestid,ht.name,h.host"
			" from httptest ht"
			" join hosts h on h.hostid=ht.hostid and h.status=%d"
			" where ht.templateid is null",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		template_name = zbx_strdup(NULL, row[2]);
		template_name = update_template_name(template_name);
		seed = zbx_dsprintf(seed, "%s%s", template_name, row[1]);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update httptest set uuid='%s' where httptestid=%s;\n", uuid, row[0]);
		zbx_free(template_name);
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

static int	DBpatch_5030095(void)
{
	int		ret = SUCCEED;
	char		*template_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select v.valuemapid,v.name,h.host"
			" from valuemap v"
			" join hosts h on h.hostid=v.hostid"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		template_name = zbx_strdup(NULL, row[2]);
		template_name = update_template_name(template_name);
		seed = zbx_dsprintf(seed, "%s%s", template_name, row[1]);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update valuemap set uuid='%s' where valuemapid=%s;\n", uuid, row[0]);
		zbx_free(template_name);
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

static int	DBpatch_5030096(void)
{
	int		ret = SUCCEED;
	char		*uuid, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select groupid,name from hstgrp");

	while (NULL != (row = DBfetch(result)))
	{
		uuid = zbx_gen_uuid4(row[1]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hstgrp set uuid='%s' where groupid=%s;\n", uuid, row[0]);
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

static int	DBpatch_5030097(void)
{
	int		ret = SUCCEED;
	char		*template_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select i.itemid,i.key_,h.host,i2.key_"
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
		seed = zbx_dsprintf(seed, "%s%s%s", template_name, row[3], row[1]);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set uuid='%s' where itemid=%s;\n",
				uuid, row[0]);
		zbx_free(template_name);
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

static int	DBpatch_5030098(void)
{
	int		ret = SUCCEED;
	char		*uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

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

		for (i = 0; i < 2; i++)
		{
			trigger_expr = zbx_strdup(NULL, row[i + 2]);
			pexpr = pexpr_f = (const char *)trigger_expr;

			while (SUCCEED == get_N_functionid(pexpr, 1, &functionid, (const char **)&pexpr_s, &pexpr_f))
			{
				trigger_expr[pexpr_s - trigger_expr] = '\0';

				result2 = DBselect(
						"select h.host,i.key_,f.name,f.parameter"
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

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", row[1]);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", total_expr);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);
		zbx_free(total_expr);
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

static int	DBpatch_5030099(void)
{
	int		ret = SUCCEED;
	char		*templ_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	result = DBselect(
			"select distinct g.graphid,g.name,h.host,i2.key_"
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
		templ_name = zbx_strdup(NULL, row[2]);
		templ_name = update_template_name(templ_name);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset,"%s%s%s", templ_name, row[3], row[1]);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set uuid='%s'"
				" where graphid=%s;\n", uuid, row[0]);
		zbx_free(templ_name);
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

static int	DBpatch_5030100(void)
{
	int		ret = SUCCEED;
	char		*name_tmpl, *uuid, *seed = NULL, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select h.hostid,h.host,h2.name,i.key_"
			" from hosts h"
			" join host_discovery hd on hd.hostid=h.hostid"
			" join items i on i.itemid=hd.parent_itemid"
			" join hosts h2 on h2.hostid=i.hostid and h2.status=%d"
			" where h.flags=%d and h.templateid is null",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		name_tmpl = zbx_strdup(NULL, row[2]);
		name_tmpl = update_template_name(name_tmpl);
		seed = zbx_dsprintf(seed, "%s%s%s", name_tmpl, row[3], row[1]);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set uuid='%s' where hostid=%s;\n",
				uuid, row[0]);
		zbx_free(name_tmpl);
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
DBPATCH_ADD(5030087, 0, 1)
DBPATCH_ADD(5030088, 0, 1)
DBPATCH_ADD(5030089, 0, 1)
DBPATCH_ADD(5030090, 0, 1)
DBPATCH_ADD(5030091, 0, 1)
DBPATCH_ADD(5030092, 0, 1)
DBPATCH_ADD(5030093, 0, 1)
DBPATCH_ADD(5030094, 0, 1)
DBPATCH_ADD(5030095, 0, 1)
DBPATCH_ADD(5030096, 0, 1)
DBPATCH_ADD(5030097, 0, 1)
DBPATCH_ADD(5030098, 0, 1)
DBPATCH_ADD(5030099, 0, 1)
DBPATCH_ADD(5030100, 0, 1)

DBPATCH_END()
