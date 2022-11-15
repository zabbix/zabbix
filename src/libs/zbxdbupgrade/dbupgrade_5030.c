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
#include "zbxeval.h"
#include "log.h"
#include "db.h"
#include "dbupgrade.h"
#include "dbupgrade_macros.h"
#include "zbxregexp.h"
#include "zbxalgo.h"
#include "zbxjson.h"
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
		DBprepare_multiple_query(buffer, "itemid", &host->itemids, &sql, &sql_alloc, &sql_offset);
		/* get discovered itemids for not templated item prototypes on a host */
		get_discovered_itemids(&host->itemids, &discovered_itemids);

		zbx_vector_uint64_append_array(&templateids, host->itemids.values, host->itemids.values_num);
		get_template_itemids_by_templateids(&templateids, &host->itemids, &discovered_itemids);

		/* make sure if multiple hosts are linked to same not nested template then there is only */
		/* update by templateid from template and no selection by numerous itemids               */
		zbx_vector_uint64_sort(&host->itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBprepare_multiple_query(buffer, "templateid", &host->itemids, &sql, &sql_alloc, &sql_offset);

		if (0 != discovered_itemids.values_num)
		{
			zbx_vector_uint64_sort(&discovered_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			DBprepare_multiple_query(buffer, "itemid", &discovered_itemids, &sql, &sql_alloc, &sql_offset);
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

		if (NULL == DBfetch(result))
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
	zbx_strlcpy(p, parts->authtype, (size_t)(p_end - p));

	return size;
}

/******************************************************************************
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

static int	DBpatch_5030081(void)
{
	const ZBX_FIELD	field = {"display_period", "30", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030082(void)
{
	const ZBX_FIELD	field = {"auto_start", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030083(void)
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

static int	DBpatch_5030084(void)
{
	const ZBX_FIELD field = {"dashboardid", NULL, "dashboard", "dashboardid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard_page", 1, &field);
}

static int	DBpatch_5030085(void)
{
	return DBcreate_index("dashboard_page", "dashboard_page_1", "dashboardid", 0);
}

static int	DBpatch_5030086(void)
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

static int	DBpatch_5030087(void)
{
	const ZBX_FIELD	field = {"dashboard_pageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	if (SUCCEED != DBadd_field("widget", &field))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030088(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update widget set dashboard_pageid=dashboardid"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030089(void)
{
	const ZBX_FIELD	field = {"dashboard_pageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("widget", &field);
}

static int	DBpatch_5030090(void)
{
	return DBdrop_foreign_key("widget", 1);
}

static int	DBpatch_5030091(void)
{
	return DBdrop_field("widget", "dashboardid");
}

static int	DBpatch_5030092(void)
{
	return DBcreate_index("widget", "widget_1", "dashboard_pageid", 0);
}

static int	DBpatch_5030093(void)
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

/* #define ZBX_WIDGET_FIELD_RESOURCE_GRAPH				(0) */
/* #define ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH			(1) */
/* #define ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE		(2) */
/* #define ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE	(3) */

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

ZBX_VECTOR_DECL(scitem_dim2, zbx_screen_item_dim_t)
ZBX_VECTOR_IMPL(scitem_dim2, zbx_screen_item_dim_t)
ZBX_VECTOR_DECL(char2, char)
ZBX_VECTOR_IMPL(char2, char)

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
		char	*name_esc;

		DBfree_result(result);

		name_esc = DBdyn_escape_string(*new_name);

		result = DBselect("select count(*)"
				" from dashboard"
				" where name='%s' and templateid is null",
				name_esc);

		zbx_free(name_esc);

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
	DB_RESULT	result = NULL;
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
	for (x = (int)DBpatch_array_max_used_index(keep_ ## axis, a_size); x >= 0; x--)			\
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
	len = (int)zbx_snprintf(ptr, (size_t)max, "[ ");
	ptr += len;
	max -= len;

	for (i = 0; 0 < max && i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
		{
			len = (int)zbx_snprintf(ptr, (size_t)max, "%d:%d ", i, (int)v->values[i]);
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
	len = (int)zbx_snprintf(ptr, (size_t)max, "[ ");
	ptr += len;
	max -= len;

	for (i = 0; 0 < max && i < alen; i++)
	{
		if (emptyval != a[i])
		{
			len = (int)zbx_snprintf(ptr, (size_t)max, "%d:%d ", i, a[i]);
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

	for (i = (size_t)start; i < (size_t)start + num && i < (size_t)v->values_num; i++)
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
		block->r_block = lw_array_create_fill(scitems->values[i].position, (size_t)scitems->values[i].span);
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
				dimensions->values[n] = (char)MAX(1, size_overflow / block_unsized_count);
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
				dimensions->values[n] = (char)new_dimension;
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

		if (0 <= potential_index)
		{
			zabbix_log(LOG_LEVEL_TRACE, "dim_sum:%d pot_idx/val:%d/%.2lf", dimensions_sum,
					potential_index, potential_value);
		}

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
	char	*name_esc;

	dashboard_page->dashboard_pageid = DBget_maxid("dashboard_page");
	dashboard_page->dashboardid = dashboardid;

	zabbix_log(LOG_LEVEL_TRACE, "adding dashboard_page id:" ZBX_FS_UI64, dashboard_page->dashboard_pageid);

	name_esc = DBdyn_escape_string(name);
	res = DBexecute("insert into dashboard_page (dashboard_pageid,dashboardid,name,display_period,sortorder)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ",'%s',%d,%d)",
			dashboard_page->dashboard_pageid, dashboardid, name_esc, display_period, sortorder);
	zbx_free(name_esc);

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
		char			*url_esc;
		zbx_db_widget_field_t	*f;

		f = (zbx_db_widget_field_t *)fields->values[i];
		url_esc = DBdyn_escape_string(f->value_str);

		if (ZBX_DB_OK > DBexecute("insert into widget_field (widget_fieldid,widgetid,type,name,value_int,"
				"value_str,value_itemid,value_graphid,value_groupid,value_hostid,value_sysmapid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s',%d,'%s',%s,%s,%s,%s,%s)",
				new_fieldid++, widget->widgetid, f->type, f->name, f->value_int, url_esc,
				DBsql_id_ins(f->value_itemid), DBsql_id_ins(f->value_graphid),
				DBsql_id_ins(f->value_groupid), DBsql_id_ins(f->value_hostid),
				DBsql_id_ins(f->value_sysmapid)))
		{
			ret = FAIL;
		}

		zbx_free(url_esc);
	}

	return ret;
}

static int DBpatch_set_permissions_screen(uint64_t dashboardid, uint64_t screenid)
{
	int		ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select userid,permission from screen_user where screenid=" ZBX_FS_UI64, screenid);

	while (NULL != (row = DBfetch(result)))
	{
		if (ZBX_DB_OK > DBexecute("insert into dashboard_user (dashboard_userid,dashboardid,userid,permission)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%s,%s)",
			DBget_maxid("dashboard_user"), dashboardid, row[0], row[1]))
		{
			ret = FAIL;
			goto out;
		}
	}
	DBfree_result(result);

	result = DBselect("select usrgrpid,permission from screen_usrgrp where screenid=" ZBX_FS_UI64, screenid);

	while (NULL != (row = DBfetch(result)))
	{
		if (ZBX_DB_OK > DBexecute("insert into dashboard_usrgrp"
			" (dashboard_usrgrpid,dashboardid,usrgrpid,permission)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%s,%s)",
			DBget_maxid("dashboard_usrgrp"), dashboardid, row[0], row[1]))
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
	int		ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select userid,permission from slideshow_user where slideshowid=" ZBX_FS_UI64, slideshowid);

	while (NULL != (row = DBfetch(result)))
	{
		if (ZBX_DB_OK > DBexecute("insert into dashboard_user (dashboard_userid,dashboardid,userid,permission)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ",%s,%s)",
			DBget_maxid("dashboard_user"), dashboardid, row[0], row[1]))
		{
			ret = FAIL;
			goto out;
		}
	}
	DBfree_result(result);

	result = DBselect("select usrgrpid,permission from slideshow_usrgrp where slideshowid=" ZBX_FS_UI64,
			slideshowid);

	while (NULL != (row = DBfetch(result)))
	{
		if (ZBX_DB_OK > DBexecute("insert into dashboard_usrgrp"
			" (dashboard_usrgrpid,dashboardid,usrgrpid,permission)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%s,%s)",
			DBget_maxid("dashboard_usrgrp"), dashboardid, row[0], row[1]))
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

		if (0 == scr_item->colspan)
		{
			scr_item->colspan = 1;
			zabbix_log(LOG_LEVEL_WARNING, "warning: colspan is 0, converted to 1 for item " ZBX_FS_UI64,
					scr_item->screenitemid);
		}

		if (0 == scr_item->rowspan)
		{
			scr_item->rowspan = 1;
			zabbix_log(LOG_LEVEL_WARNING, "warning: rowspan is 0, converted to 1 for item " ZBX_FS_UI64,
					scr_item->screenitemid);
		}

		if (SCREEN_MAX_COLS <= scr_item->x)
		{
			scr_item->x = SCREEN_MAX_COLS - 1;
			zabbix_log(LOG_LEVEL_WARNING, "warning: x is more than %d, limited for item " ZBX_FS_UI64,
					scr_item->x, scr_item->screenitemid);
		}

		if (0 > scr_item->x)
		{
			scr_item->x = 0;
			zabbix_log(LOG_LEVEL_WARNING, "warning: x is negative, set to 0 for item " ZBX_FS_UI64,
					scr_item->screenitemid);
		}

		if (SCREEN_MAX_ROWS <= scr_item->y)
		{
			scr_item->y = SCREEN_MAX_ROWS - 1;
			zabbix_log(LOG_LEVEL_WARNING, "warning: y is more than %d, limited for item " ZBX_FS_UI64,
					scr_item->y, scr_item->screenitemid);
		}

		if (0 > scr_item->y)
		{
			scr_item->y = 0;
			zabbix_log(LOG_LEVEL_WARNING, "warning: y is negative, set to 0 for item " ZBX_FS_UI64,
					scr_item->screenitemid);
		}

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

static int	DBpatch_5030094(void)
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

static int	DBpatch_5030095(void)
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

/* #undef ZBX_WIDGET_FIELD_RESOURCE_GRAPH */
/* #undef ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH */
/* #undef ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE */
/* #undef ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE */

#undef ZBX_WIDGET_TYPE_CLOCK
#undef ZBX_WIDGET_TYPE_GRAPH_CLASSIC
#undef ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE
#undef ZBX_WIDGET_TYPE_PLAIN_TEXT
#undef ZBX_WIDGET_TYPE_URL
#undef POS_EMPTY
#undef POS_TAKEN
#undef SKIP_EMPTY

static int	DBpatch_5030096(void)
{
	return DBdrop_table("slides");
}

static int	DBpatch_5030097(void)
{
	return DBdrop_table("slideshow_user");
}

static int	DBpatch_5030098(void)
{
	return DBdrop_table("slideshow_usrgrp");
}

static int	DBpatch_5030099(void)
{
	return DBdrop_table("slideshows");

}

static int	DBpatch_5030100(void)
{
	return DBdrop_table("screen_usrgrp");
}

static int	DBpatch_5030101(void)
{
	return DBdrop_table("screens_items");
}

static int	DBpatch_5030102(void)
{
	return DBdrop_table("screen_user");
}

static int	DBpatch_5030103(void)
{
	return DBdrop_table("screens");
}

static int	DBpatch_5030104(void)
{
	if (ZBX_DB_OK > DBexecute("delete from widget where type='favscreens'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030105(void)
{
	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ("
			"'web.favorite.screenids', "
			"'web.screenconf.filter.active', "
			"'web.screenconf.filter_name', "
			"'web.screenconf.php.sort', "
			"'web.screenconf.php.sortorder', "
			"'web.screens.elementid', "
			"'web.screens.filter.active', "
			"'web.screens.filter.from', "
			"'web.screens.filter.to', "
			"'web.screens.hostid', "
			"'web.screens.tr_groupid', "
			"'web.screens.tr_hostid', "
			"'web.slideconf.filter.active', "
			"'web.slideconf.filter_name', "
			"'web.slideconf.php.sort', "
			"'web.slideconf.php.sortorder', "
			"'web.slides.elementid', "
			"'web.slides.filter.active', "
			"'web.slides.filter.from', "
			"'web.slides.filter.to', "
			"'web.slides.hostid', "
			"'web.slides.rf_rate.hat_slides', "
			"'web.favorite.screenids')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030106(void)
{
	if (ZBX_DB_OK > DBexecute("delete from role_rule"
			" where name like 'api.method.%%'"
			" and (value_str like 'screen.%%' or value_str like 'screenitem.%%')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030107(void)
{
	if (ZBX_DB_OK > DBexecute("delete from role_rule where name='ui.monitoring.screens'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030108(void)
{
	int		i;
	const char	*values[] = {
			"web.dashbrd.dashboardid", "web.dashboard.dashboardid",
			"web.dashbrd.hostid", "web.dashboard.hostid",
			"web.dashbrd.list.sort", "web.dashboard.list.sort",
			"web.dashbrd.list.sortorder", "web.dashboard.list.sortorder",
			"web.dashbrd.list_was_opened", "web.dashboard.list_was_opened",
			"web.dashbrd.filter", "web.dashboard.filter",
			"web.dashbrd.filter.active", "web.dashboard.filter.active",
			"web.dashbrd.filter.from", "web.dashboard.filter.from",
			"web.dashbrd.filter.to", "web.dashboard.filter.to",
			"web.dashbrd.filter_name", "web.dashboard.filter_name",
			"web.dashbrd.filter_show", "web.dashboard.filter_show",
			"web.dashbrd.last_widget_type", "web.dashboard.last_widget_type",
			"web.dashbrd.navtree.item.selected", "web.dashboard.widget.navtree.item.selected",
			"web.dashbrd.widget.rf_rate", "web.dashboard.widget.rf_rate",
			"web.templates.dashbrd.list.sort", "web.templates.dashboard.list.sort",
			"web.templates.dashbrd.list.sortorder", "web.templates.dashboard.list.sortorder"
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

static int	DBpatch_5030109(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx=CONCAT('web.dashboard.widget.navtree.item-', SUBSTR(idx, 21))"
			" where idx like 'web.dashbrd.navtree-%%.toggle'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030110(void)
{
	const ZBX_TABLE table =
			{"item_tag", "itemtagid", 0,
				{
					{"itemtagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030111(void)
{
	return DBcreate_index("item_tag", "item_tag_1", "itemid", 0);
}

static int	DBpatch_5030112(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_tag", 1, &field);
}

static int	DBpatch_5030113(void)
{
	const ZBX_TABLE table =
			{"httptest_tag", "httptesttagid", 0,
				{
					{"httptesttagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"httptestid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030114(void)
{
	return DBcreate_index("httptest_tag", "httptest_tag_1", "httptestid", 0);
}

static int	DBpatch_5030115(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest_tag", 1, &field);
}

static int	DBpatch_5030116(void)
{
	const ZBX_TABLE table =
			{"sysmaps_element_tag", "selementtagid", 0,
				{
					{"selementtagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"selementid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"operator", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030117(void)
{
	return DBcreate_index("sysmaps_element_tag", "sysmaps_element_tag_1", "selementid", 0);
}

static int	DBpatch_5030118(void)
{
	const ZBX_FIELD	field = {"selementid", NULL, "sysmaps_elements", "selementid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmaps_element_tag", 1, &field);
}

static int	DBpatch_5030119(void)
{
	const ZBX_FIELD	field = {"evaltype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_elements", &field);
}

static int	DBpatch_5030120(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	itemid, itemtagid = 1;
	int		ret;
	char		*value;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "item_tag", "itemtagid", "itemid", "tag", "value", NULL);
	result = DBselect(
			"select i.itemid,a.name from items i"
			" join items_applications ip on i.itemid=ip.itemid"
			" join applications a on ip.applicationid=a.applicationid");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(itemid, row[0]);
		value = DBdyn_escape_string(row[1]);
		zbx_db_insert_add_values(&db_insert, itemtagid++, itemid, "Application", value);
		zbx_free(value);
	}
	DBfree_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030121(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	itemid;
	int		ret;
	char		*value;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "item_tag", "itemtagid", "itemid", "tag", "value", NULL);

	result = DBselect(
			"select i.itemid,ap.name from items i"
			" join item_application_prototype ip on i.itemid=ip.itemid"
			" join application_prototype ap on ip.application_prototypeid=ap.application_prototypeid");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(itemid, row[0]);
		value = DBdyn_escape_string(row[1]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), itemid, "Application", value);
		zbx_free(value);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "itemtagid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030122(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	httptestid, httptesttagid = 1;
	int		ret;
	char		*value;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "httptest_tag", "httptesttagid", "httptestid", "tag", "value", NULL);
	result = DBselect(
			"select h.httptestid,a.name from httptest h"
			" join applications a on h.applicationid=a.applicationid");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(httptestid, row[0]);
		value = DBdyn_escape_string(row[1]);
		zbx_db_insert_add_values(&db_insert, httptesttagid++, httptestid, "Application", value);
		zbx_free(value);
	}
	DBfree_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030123(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	selementid, selementtagid = 1;
	int		ret;
	char		*value;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "sysmaps_element_tag", "selementtagid", "selementid", "tag", "value", NULL);
	result = DBselect(
			"select selementid,application from sysmaps_elements"
			" where elementtype in (0,3) and application<>''");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(selementid, row[0]);
		value = DBdyn_escape_string(row[1]);
		zbx_db_insert_add_values(&db_insert, selementtagid++, selementid, "Application", value);
		zbx_free(value);
	}
	DBfree_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030127(void)
{
#define CONDITION_TYPE_APPLICATION	15
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update conditions set conditiontype=%d,value2='Application' where conditiontype=%d",
			CONDITION_TYPE_EVENT_TAG_VALUE, CONDITION_TYPE_APPLICATION))
	{
		return FAIL;
	}

	return SUCCEED;
#undef CONDITION_TYPE_APPLICATION
}

static int	DBpatch_5030128(void)
{
#define AUDIT_RESOURCE_APPLICATION	12
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from auditlog where resourcetype=%d", AUDIT_RESOURCE_APPLICATION))
		return FAIL;

	return SUCCEED;
#undef AUDIT_RESOURCE_APPLICATION
}

static int	DBpatch_5030129(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ("
			"'web.applications.filter','web.latest.filter.application',"
			"'web.overview.filter.application','web.applications.filter.active',"
			"'web.applications.filter_groups','web.applications.filter_hostids',"
			"'web.applications.php.sort','web.applications.php.sortorder',"
			"'web.hosts.items.subfilter_apps','web.templates.items.subfilter_apps',"
			"'web.latest.toggle','web.latest.toggle_other','web.items.filter_application')"))
		return FAIL;

	return SUCCEED;
}

typedef struct
{
	char	*tag;
	char	*op;
	char	*value;
}
patch_filtertag_t;

ZBX_PTR_VECTOR_DECL(patch_filtertag, patch_filtertag_t)
ZBX_PTR_VECTOR_IMPL(patch_filtertag, patch_filtertag_t)

static void	patch_filtertag_free(patch_filtertag_t tag)
{
	zbx_free(tag.tag);
	zbx_free(tag.op);
	zbx_free(tag.value);
}

static int	DBpatch_parse_tags_json(struct zbx_json_parse *jp, zbx_vector_patch_filtertag_t *tags)
{
	const char		*p = NULL;
	struct zbx_json_parse	jp_data;
	patch_filtertag_t	tag;
	size_t			tag_alloc, op_alloc, val_alloc;

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		if (SUCCEED != zbx_json_brackets_open(p, &jp_data))
			return FAIL;

		tag.tag = NULL;
		tag.op = NULL;
		tag.value = NULL;

		tag_alloc = 0;
		op_alloc = 0;
		val_alloc = 0;

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_data, "tag", &tag.tag, &tag_alloc, NULL) ||
				SUCCEED != zbx_json_value_by_name_dyn(&jp_data, "operator", &tag.op, &op_alloc, NULL) ||
				SUCCEED != zbx_json_value_by_name_dyn(&jp_data, "value", &tag.value, &val_alloc, NULL))
		{
			patch_filtertag_free(tag);
			return FAIL;
		}

		zbx_vector_patch_filtertag_append(tags, tag);
	}

	return SUCCEED;
}

static int	DBpatch_parse_applications_json(struct zbx_json_parse *jp, struct zbx_json *json_dest,
		zbx_vector_patch_filtertag_t *tags, char **app, int depth)
{
	struct zbx_json_parse	jp_sub;
	const char		*p = NULL, *prev = NULL;
	char			name[MAX_STRING_LEN], value[MAX_STRING_LEN];
	zbx_json_type_t		type;

	do
	{
		if (NULL != (p = zbx_json_pair_next(jp, p, name, sizeof(name))))
		{
			if (NULL == zbx_json_decodevalue(p, value, sizeof(value), NULL))
			{
				type = zbx_json_valuetype(p);

				if (type == ZBX_JSON_TYPE_ARRAY)
				{
					if (0 == depth && 0 == strcmp(name, "tags"))
					{
						if (SUCCEED != zbx_json_brackets_open(p, &jp_sub) ||
								SUCCEED != DBpatch_parse_tags_json(&jp_sub, tags))
						{
							return FAIL;
						}

						continue;
					}

					zbx_json_addarray(json_dest, name);
				}
				else if (type == ZBX_JSON_TYPE_OBJECT)
				{
					zbx_json_addobject(json_dest, name);
				}
			}
			else
			{
				if (0 == depth && 0 == strcmp(name, "application"))
					*app = zbx_strdup(*app, value);
				else
					zbx_json_addstring(json_dest, name, value, ZBX_JSON_TYPE_STRING);
			}
		}
		else
		{
			p = prev;

			if (NULL == (p = zbx_json_next_value(jp, p, value, sizeof(value), NULL)))
			{
				p = prev;

				if (NULL != (p = zbx_json_next(jp, p)))
				{
					type = zbx_json_valuetype(p);

					if (type == ZBX_JSON_TYPE_OBJECT)
						zbx_json_addobject(json_dest, NULL);
					else if (type == ZBX_JSON_TYPE_ARRAY)
						zbx_json_addarray(json_dest, NULL);
				}
				else
				{
					if (0 == depth)
					{
						if (NULL != *app)
						{
							patch_filtertag_t	tag;

							tag.tag = zbx_strdup(NULL, "Application");
							tag.op = zbx_strdup(NULL, "0");
							tag.value = zbx_strdup(NULL, *app);

							zbx_vector_patch_filtertag_append(tags, tag);
						}

						if (0 < tags->values_num)
						{
							int	i;

							zbx_json_addarray(json_dest, "tags");

							for (i = 0; i < tags->values_num; i++)
							{
								zbx_json_addobject(json_dest, NULL);
								zbx_json_addstring(json_dest, "tag",
										tags->values[i].tag,
										ZBX_JSON_TYPE_STRING);
								zbx_json_addstring(json_dest, "operator",
										tags->values[i].op,
										ZBX_JSON_TYPE_STRING);
								zbx_json_addstring(json_dest, "value",
										tags->values[i].value,
										ZBX_JSON_TYPE_STRING);
								zbx_json_close(json_dest);
							}
						}

						zbx_json_close(json_dest);
					}

					zbx_json_close(json_dest);
				}
			}
			else
				zbx_json_addstring(json_dest, NULL, value, ZBX_JSON_TYPE_STRING);
		}

		if (NULL != p && SUCCEED == zbx_json_brackets_open(p, &jp_sub))
		{
			if (SUCCEED != DBpatch_parse_applications_json(&jp_sub, json_dest, tags, app, depth + 1))
				return FAIL;
		}

		prev = p;
	} while (NULL != p);

	return SUCCEED;
}

static int	DBpatch_5030130(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select profileid,value_str from profiles"
			" where idx='web.monitoring.problem.properties'");

	while (NULL != (row = DBfetch(result)) && SUCCEED == ret)
	{
		struct zbx_json_parse		jp;
		struct zbx_json			json;
		zbx_vector_patch_filtertag_t	tags;
		char				*app, *value_str;
		zbx_uint64_t			profileid;

		app = NULL;
		zbx_vector_patch_filtertag_create(&tags);
		zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

		if (SUCCEED == (ret = zbx_json_open(row[1], &jp)) &&
				SUCCEED == (ret = DBpatch_parse_applications_json(&jp, &json, &tags, &app, 0)))
		{
			ZBX_DBROW2UINT64(profileid, row[0]);

			value_str = DBdyn_escape_string(json.buffer);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update profiles set value_str='%s' where profileid=" ZBX_FS_UI64 ";\n",
					value_str, profileid);
			zbx_free(value_str);

			ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
		else
			zabbix_log(LOG_LEVEL_ERR, "failed to parse web.monitoring.problem.properties JSON");

		zbx_vector_patch_filtertag_clear_ext(&tags, patch_filtertag_free);
		zbx_vector_patch_filtertag_destroy(&tags);
		zbx_free(app);
		zbx_json_free(&json);
	}
	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;

	zbx_free(sql);

	return ret;
}

static int	DBpatch_5030131(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_db_insert_t		db_insert;
	zbx_vector_uint64_t	widget_fieldids;
	int			ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name", "value_int",
			"value_str", NULL);

	zbx_vector_uint64_create(&widget_fieldids);

	result = DBselect(
			"select w.widgetid,wf.value_str,wf.widget_fieldid from widget w"
			" join widget_field wf on wf.widgetid=w.widgetid"
			" where w.type in ('dataover','trigover') and wf.type=1 and wf.name='application'");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	widgetid, widget_fieldid;
		char		*val;

		ZBX_DBROW2UINT64(widgetid, row[0]);
		val = DBdyn_escape_string(row[1]);
		ZBX_DBROW2UINT64(widget_fieldid, row[2]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 0, "tags.operator.0", 0, "");
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 1, "tags.tag.0", 0, "Application");
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 1, "tags.value.0", 0, val);

		zbx_vector_uint64_append(&widget_fieldids, widget_fieldid);

		zbx_free(val);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");

	if (SUCCEED != (ret = zbx_db_insert_execute(&db_insert)))
		goto out;

	if (0 < widget_fieldids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from widget_field where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "widget_fieldid", widget_fieldids.values,
				widget_fieldids.values_num);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;

		zbx_free(sql);
	}
out:
	zbx_db_insert_clean(&db_insert);
	zbx_vector_uint64_destroy(&widget_fieldids);

	return ret;
}

static int	DBpatch_5030132(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from role_rule where name like 'api.method.%%'"
			" and value_str like 'application.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030133(void)
{
	return DBdrop_foreign_key("httptest", 1);
}

static int	DBpatch_5030134(void)
{
	return DBdrop_index("httptest", "httptest_1");
}

static int	DBpatch_5030135(void)
{
	return DBdrop_field("httptest", "applicationid");
}

static int	DBpatch_5030136(void)
{
	return DBdrop_field("sysmaps_elements", "application");
}

static int	DBpatch_5030137(void)
{
	return DBdrop_table("application_discovery");
}

static int	DBpatch_5030138(void)
{
	return DBdrop_table("item_application_prototype");
}

static int	DBpatch_5030139(void)
{
	return DBdrop_table("application_prototype");
}

static int	DBpatch_5030140(void)
{
	return DBdrop_table("application_template");
}

static int	DBpatch_5030141(void)
{
	return DBdrop_table("items_applications");
}

static int	DBpatch_5030142(void)
{
	return DBdrop_table("applications");
}

static int	DBpatch_5030143(void)
{
	DB_RESULT		result;
	int			ret;
	zbx_field_len_t		fields[] = {
			{"subject", 255},
			{"message", 65535}
	};

	result = DBselect("select om.operationid,om.subject,om.message"
			" from opmessage om,operations o,actions a"
			" where om.operationid=o.operationid"
				" and o.actionid=a.actionid"
				" and a.eventsource=0 and o.operationtype=11");

	ret = db_rename_macro(result, "opmessage", "operationid", fields, ARRSIZE(fields), "{EVENT.NAME}",
			"{EVENT.RECOVERY.NAME}");

	DBfree_result(result);

	return ret;
}

static int	DBpatch_5030144(void)
{
	DB_RESULT		result;
	int			ret;
	zbx_field_len_t		fields[] = {
			{"subject", 255},
			{"message", 65535}
	};

	result = DBselect("select mediatype_messageid,subject,message from media_type_message where recovery=1");

	ret = db_rename_macro(result, "media_type_message", "mediatype_messageid", fields, ARRSIZE(fields),
			"{EVENT.NAME}", "{EVENT.RECOVERY.NAME}");

	DBfree_result(result);

	return ret;
}

static int	DBpatch_5030145(void)
{
	const ZBX_TABLE	table =
			{"report", "reportid", 0,
				{
					{"reportid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"description", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"dashboardid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"period", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"cycle", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"weekdays", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"start_time", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"active_since", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"active_till", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"state", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"lastsent", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"info", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030146(void)
{
	return DBcreate_index("report", "report_1", "name", 1);
}

static int	DBpatch_5030147(void)
{
	const ZBX_FIELD field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("report", 1, &field);
}

static int	DBpatch_5030148(void)
{
	const ZBX_FIELD field = {"dashboardid", NULL, "dashboard", "dashboardid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("report", 2, &field);
}

static int	DBpatch_5030149(void)
{
	const ZBX_TABLE	table =
			{"report_param", "reportparamid", 0,
				{
					{"reportparamid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"reportid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030150(void)
{
	return DBcreate_index("report_param", "report_param_1", "reportid", 0);
}

static int	DBpatch_5030151(void)
{
	const ZBX_FIELD field = {"reportid", NULL, "report", "reportid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("report_param", 1, &field);
}

static int	DBpatch_5030152(void)
{
	const ZBX_TABLE	table =
			{"report_user", "reportuserid", 0,
				{
					{"reportuserid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"reportid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"exclude", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"access_userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030153(void)
{
	return DBcreate_index("report_user", "report_user_1", "reportid", 0);
}

static int	DBpatch_5030154(void)
{
	const ZBX_FIELD field = {"reportid", NULL, "report", "reportid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("report_user", 1, &field);
}

static int	DBpatch_5030155(void)
{
	const ZBX_FIELD field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("report_user", 2, &field);
}

static int	DBpatch_5030156(void)
{
	const ZBX_FIELD field = {"access_userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("report_user", 3, &field);
}

static int	DBpatch_5030157(void)
{
	const ZBX_TABLE	table =
			{"report_usrgrp", "reportusrgrpid", 0,
				{
					{"reportusrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"reportid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"access_userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030158(void)
{
	return DBcreate_index("report_usrgrp", "report_usrgrp_1", "reportid", 0);
}

static int	DBpatch_5030159(void)
{
	const ZBX_FIELD field = {"reportid", NULL, "report", "reportid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("report_usrgrp", 1, &field);
}

static int	DBpatch_5030160(void)
{
	const ZBX_FIELD field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("report_usrgrp", 2, &field);
}

static int	DBpatch_5030161(void)
{
	const ZBX_FIELD field = {"access_userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("report_usrgrp", 3, &field);
}

static int	DBpatch_5030162(void)
{
	const ZBX_FIELD	field = {"url", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5030163(void)
{
	const ZBX_FIELD	field = {"report_test_timeout", "60s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5030164(void)
{
	const ZBX_FIELD	field = {"dbversion_status", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

/* trigger function conversion to new syntax */

#define ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION		0x01
#define ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION	0x02

#define ZBX_DBPATCH_TRIGGER_UPDATE			(ZBX_DBPATCH_TRIGGER_UPDATE_EXPRESSION | \
							ZBX_DBPATCH_TRIGGER_UPDATE_RECOVERY_EXPRESSION)

ZBX_VECTOR_DECL(loc, zbx_strloc_t)
ZBX_VECTOR_IMPL(loc, zbx_strloc_t)

typedef struct
{
	zbx_uint64_t	triggerid;
	unsigned char	recovery_mode;
	unsigned char	flags;
	char		*expression;
	char		*recovery_expression;
}
zbx_dbpatch_trigger_t;

static void	dbpatch_trigger_clear(zbx_dbpatch_trigger_t *trigger)
{
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
}

/******************************************************************************
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

	for (; SUCCEED == zbx_token_find(*expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID |
			ZBX_TOKEN_SEARCH_SIMPLE_MACRO); pos++)
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

#define ZBX_DBPATCH_EXPRESSION			0x01
#define ZBX_DBPATCH_RECOVERY_EXPRESSION		0x02

/******************************************************************************
 *                                                                            *
 * Purpose: check if the expression contains specified functionid             *
 *                                                                            *
 ******************************************************************************/
static int	dbpatch_find_function(const char *expression, zbx_uint64_t functionid)
{
	int		pos = 0;
	zbx_token_t	token;
	zbx_uint64_t	id;

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID |
			ZBX_TOKEN_SEARCH_SIMPLE_MACRO); pos++)
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

static int	DBpatch_5030165(void)
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

static int	DBpatch_5030166(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* When upgrading from version 5.0 or less trigger_queue will be created later. */
	/* Only when upgrading from version 5.2 there will be trigger queue table which */
	/* must be updated.                                                             */
	if (SUCCEED != DBtable_exists("trigger_queue"))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update trigger_queue set type=4 where type=3"))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
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

	for (; SUCCEED == zbx_token_find(expression, (int)pos, &token, ZBX_TOKEN_SEARCH_BASIC |
			ZBX_TOKEN_SEARCH_SIMPLE_MACRO) && token.loc.r < loc->r; pos++)
	{
		char	*macro = NULL;

		switch (token.type)
		{
			case ZBX_TOKEN_SIMPLE_MACRO:
				dbpatch_convert_simple_macro(expression, &token.data.simple_macro, 0, &macro);
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

static int	DBpatch_5030167(void)
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

		for (; SUCCEED == zbx_token_find(row[1], (int)pos, &token, ZBX_TOKEN_SEARCH_EXPRESSION_MACRO |
				ZBX_TOKEN_SEARCH_SIMPLE_MACRO); pos++)
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

static int	dbpatch_validate_key_macro(const char *key)
{
	char	*params, *macro;

	if (NULL == (macro = strchr(key, '{')))
		return SUCCEED;

	if (NULL != (params = strchr(key, '[')) && params < macro)
		return SUCCEED;

	return FAIL;
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
			size_t	arg0_len;

			zbx_function_param_parse(ptr + par_l + 1, &param_pos, &param_len, &sep_pos);

			arg0 = zbx_function_param_unquote_dyn(ptr + par_l + 1 + param_pos, param_len, &quoted);
			arg0_len = strlen(arg0);
			zbx_remove_chars(arg0, "\t\n\r");
			if (strlen(arg0) != arg0_len)
			{
				zabbix_log(LOG_LEVEL_WARNING, "control characters were removed from calculated item \""
						ZBX_FS_UI64 "\" formula host:key parameter at %s", itemid, ptr);
			}

			ret = parse_host_key(arg0, &host, &key);
			zbx_free(arg0);

			if (FAIL == ret)
			{
				zbx_vector_ptr_clear_ext(functions, (zbx_clean_func_t)dbpatch_function_free);
				zbx_free(exp);
				return NULL;
			}

			if (SUCCEED != dbpatch_validate_key_macro(key))
			{
				zabbix_log(LOG_LEVEL_WARNING, "invalid key parameter \"%s\" in calculated item \""
						ZBX_FS_UI64 "\" formula: using macro within item key is not supported"
								" anymore", key, itemid);
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

static int	DBpatch_5030168(void)
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

		for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID |
				ZBX_TOKEN_SEARCH_SIMPLE_MACRO); pos++)
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

static int	dbpatch_aggregate2formula(const char *itemid, const AGENT_REQUEST *request, char **str,
		size_t *str_alloc, size_t *str_offset, char **error)
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

	if (SUCCEED != dbpatch_validate_key_macro(request->params[1]))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid key parameter \"%s\" when converting aggregate check \"%s\""
				" to calculated item: using macro within item key is not supported anymore",
				request->params[1], itemid);
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
	{
		zbx_chrcpy_alloc(str, str_alloc, str_offset, ',');

		if (SUCCEED == dbpatch_is_composite_constant(request->params[3]))
		{
			dbpatch_strcpy_alloc_quoted(str, str_alloc, str_offset, request->params[3]);
		}
		else
		{
			zbx_strcpy_alloc(str, str_alloc, str_offset, request->params[3]);

			if (0 != isdigit((*str)[*str_offset - 1]))
				zbx_chrcpy_alloc(str, str_alloc, str_offset, 's');
		}
	}

	zbx_strcpy_alloc(str, str_alloc, str_offset, "))");

	if (ITEM_PARAM_LEN < zbx_strlen_utf8(*str))
	{
		*error = zbx_strdup(NULL, "resulting formula is too long");
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030169(void)
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

		if (SUCCEED != parse_item_key(row[1], &request))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot parse aggregate checks item key \"%s\"", row[1]);
			continue;
		}

		ret_formula = dbpatch_aggregate2formula(row[0], &request, &params, &params_alloc, &params_offset,
				&error);
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

static int	DBpatch_5030170(void)
{
#ifdef HAVE_MYSQL
	return DBcreate_index("items", "items_8", "key_(1024)", 0);
#else
	return DBcreate_index("items", "items_8", "key_", 0);
#endif
}

static int	DBpatch_5030171(void)
{
	/* When upgrading from version 5.0 or less the trigger_queue table will be created */
	/* later (DBpatch_5030172).                                                        */
	/* Only when upgrading from version 5.2 there will be existing trigger_queue table */
	/* to which primary key must be added. This is done by following steps:            */
	/*   1) rename existing table (DBpatch_5030171)                                    */
	/*   2) create new table with the primary key (DBpatch_5030172)                    */
	/*   2) copy data from old table into new table (DBpatch_5030173)                  */
	/*   2) delete the old (renamed) table (DBpatch_5030174)                           */
	if (SUCCEED != DBtable_exists("trigger_queue"))
		return SUCCEED;

	return DBrename_table("trigger_queue", "trigger_queue_tmp");
}

static int	DBpatch_5030172(void)
{
	const ZBX_TABLE	table =
			{"trigger_queue", "trigger_queueid", 0,
				{
					{"trigger_queueid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"objectid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030173(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	objectid, type, clock, ns;
	int		ret;

	if (SUCCEED != DBtable_exists("trigger_queue_tmp"))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "trigger_queue", "trigger_queueid", "objectid", "type", "clock", "ns", NULL);

	result = DBselect("select objectid,type,clock,ns from trigger_queue_tmp");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(objectid, row[0]);
		ZBX_STR2UINT64(type, row[1]);
		ZBX_STR2UINT64(clock, row[2]);
		ZBX_STR2UINT64(ns, row[3]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), objectid, type, clock, ns);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "trigger_queueid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030174(void)
{
	if (SUCCEED != DBtable_exists("trigger_queue_tmp"))
		return SUCCEED;

	return DBdrop_table("trigger_queue_tmp");
}

static int	DBpatch_5030175(void)
{
	const ZBX_FIELD	field = {"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("valuemap_mapping", &field);
}

static int	DBpatch_5030176(void)
{
	return DBdrop_foreign_key("valuemap_mapping", 1);
}

static int	DBpatch_5030177(void)
{
	return DBdrop_index("valuemap_mapping", "valuemap_mapping_1");
}

static int	DBpatch_5030178(void)
{
	return DBcreate_index("valuemap_mapping", "valuemap_mapping_1", "valuemapid,value,type", 1);
}

static int	DBpatch_5030179(void)
{
	const ZBX_FIELD	field = {"valuemapid", NULL, "valuemap", "valuemapid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("valuemap_mapping", 1, &field);
}

static int	DBpatch_5030180(void)
{
	const ZBX_FIELD	field = {"sortorder", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("valuemap_mapping", &field);
}

static int	DBpatch_5030181(void)
{
	int		ret = SUCCEED;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	result = DBselect("select valuemapid from valuemap order by valuemapid asc");

	while (NULL != (row = DBfetch(result)))
	{
		int		i = 0;
		char		*sql = NULL;
		zbx_uint64_t	valuemapid;
		size_t		sql_alloc = 0, sql_offset = 0;
		DB_ROW		in_row;
		DB_RESULT	in_result;

		ZBX_DBROW2UINT64(valuemapid, row[0]);

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		in_result = DBselect("select valuemap_mappingid"
				" from valuemap_mapping"
				" where valuemapid=" ZBX_FS_UI64
				" order by valuemap_mappingid asc", valuemapid);

		while (NULL != (in_row = DBfetch(in_result)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update valuemap_mapping set sortorder=%d where valuemap_mappingid=%s;\n",
					i, in_row[0]);
			i++;

			if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
				goto out;
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
out:
		DBfree_result(in_result);
		zbx_free(sql);

		if (FAIL == ret)
			break;
	}

	DBfree_result(result);

	return ret;
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

static int	DBpatch_5030182(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("items", &field);
}

static int	DBpatch_5030183(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("hosts", &field);
}

static int	DBpatch_5030184(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("triggers", &field);
}

static int	DBpatch_5030185(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030186(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("graphs", &field);
}

static int	DBpatch_5030187(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("hstgrp", &field);
}

static int	DBpatch_5030188(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("httptest", &field);
}

static int	DBpatch_5030189(void)
{
	const ZBX_FIELD	field = ZBX_FIELD_UUID;

	return DBadd_field("valuemap", &field);
}

static char	*update_template_name(char *old)
{
	char	*ptr, new[MAX_STRING_LEN + 1], *ptr_snmp;

#define MIN_TEMPLATE_NAME_LEN	3

	ptr = old;

	if (NULL != zbx_regexp_match(old, "Template (APP|App|DB|Module|Net|OS|SAN|Server|Tel|VM) ", NULL) &&
			1 == sscanf(old, "Template %*[^ ] %" ZBX_STR(MAX_STRING_LEN) "[^\n]s", new) &&
			MIN_TEMPLATE_NAME_LEN <= strlen(new))
	{
		ptr = zbx_strdup(ptr, new);
	}

	ptr_snmp = string_replace(ptr, "SNMPv2", "SNMP");
	zbx_free(ptr);

	return ptr_snmp;
}

static char	*DBpatch_make_trigger_function(const char *name, const char *tpl, const char *key, const char *param)
{
	char	*template_name, *func = NULL;
	size_t	func_alloc = 0, func_offset = 0;

	template_name = zbx_strdup(NULL, tpl);
	template_name = update_template_name(template_name);

	zbx_snprintf_alloc(&func, &func_alloc, &func_offset, "%s(/%s/%s", name, template_name, key);

	if ('$' == *param && ',' == *++param)
		param++;

	if ('\0' != *param)
		zbx_snprintf_alloc(&func, &func_alloc, &func_offset, ",%s", param);

	zbx_chrcpy_alloc(&func, &func_alloc, &func_offset, ')');

	zbx_free(template_name);

	return func;
}

static int	DBpatch_5030190(void)
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

static int	DBpatch_5030191(void)
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
		seed = zbx_dsprintf(seed, "%s/%s", name, row[1]);
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

static int	DBpatch_5030192(void)
{
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
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
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_NORMAL);

	while (NULL != (row = DBfetch(result)))
	{
		char		*trigger_expr, *uuid, *seed = NULL;
		char		*composed_expr[] = { NULL, NULL };
		int		i;
		size_t		seed_alloc = 0, seed_offset = 0;
		DB_ROW		row2;
		DB_RESULT	result2;

		for (i = 0; i < 2; i++)
		{
			int			j;
			char			*error = NULL;
			zbx_eval_context_t	ctx;

			trigger_expr = row[i + 2];

			if ('\0' == *trigger_expr)
			{
				if (0 == i)
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s: empty expression for trigger %s",
							__func__, row[0]);
				}
				continue;
			}

			if (FAIL == zbx_eval_parse_expression(&ctx, trigger_expr, ZBX_EVAL_PARSE_TRIGGER_EXPRESSION,
					&error))
			{
				zabbix_log(LOG_LEVEL_CRIT, "%s: error parsing trigger expression for %s: %s",
						__func__, row[0], error);
				zbx_free(error);
				DBfree_result(result);
				return FAIL;
			}

			for (j = 0; j < ctx.stack.values_num; j++)
			{
				zbx_eval_token_t	*token = &ctx.stack.values[j];
				zbx_uint64_t		functionid;

				if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
					continue;

				if (SUCCEED != is_uint64_n(ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					zabbix_log(LOG_LEVEL_CRIT, "%s: error parsing trigger expression %s,"
							" is_uint64_n error", __func__, row[0]);
					DBfree_result(result);
					return FAIL;
				}

				result2 = DBselect(
						"select h.host,i.key_,f.name,f.parameter"
						" from functions f"
						" join items i on i.itemid=f.itemid"
						" join hosts h on h.hostid=i.hostid"
						" where f.functionid=" ZBX_FS_UI64,
						functionid);

				if (NULL != (row2 = DBfetch(result2)))
				{
					char	*func;

					func = DBpatch_make_trigger_function(row2[2], row2[0], row2[1], row2[3]);
					zbx_variant_clear(&token->value);
					zbx_variant_set_str(&token->value, func);
				}

				DBfree_result(result2);
			}

			zbx_eval_compose_expression(&ctx, &composed_expr[i]);
			zbx_eval_clear(&ctx);
		}

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s/", row[1]);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", composed_expr[0]);
		if (NULL != composed_expr[1])
			zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "/%s", composed_expr[1]);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);

		zbx_free(composed_expr[0]);
		zbx_free(composed_expr[1]);
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

static int	DBpatch_5030193(void)
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

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", row[1]);

		result2 = DBselect(
				"select h.host"
				" from graphs_items gi"
				" join items i on i.itemid=gi.itemid"
				" join hosts h on h.hostid=i.hostid"
				" where gi.graphid=%s"
				" order by h.host",
				row[0]);

		while (NULL != (row2 = DBfetch(result2)))
		{
			host_name = zbx_strdup(NULL, row2[0]);
			host_name = update_template_name(host_name);

			zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "/%s", host_name);
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

static int	DBpatch_5030194(void)
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
		seed = zbx_dsprintf(seed, "%s/%s", template_name, row[1]);
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

static int	DBpatch_5030195(void)
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
		seed = zbx_dsprintf(seed, "%s/%s", template_name, row[1]);
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

static int	DBpatch_5030196(void)
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
		seed = zbx_dsprintf(seed, "%s/%s", template_name, row[1]);
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

static int	DBpatch_5030197(void)
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

static int	DBpatch_5030198(void)
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
		seed = zbx_dsprintf(seed, "%s/%s/%s", template_name, row[3], row[1]);
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

static int	DBpatch_5030199(void)
{
	int			ret = SUCCEED;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

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
		char		*trigger_expr, *uuid, *seed = NULL;
		char		*composed_expr[] = { NULL, NULL };
		int		i;
		size_t		seed_alloc = 0, seed_offset = 0;
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
			int			j;
			char			*error = NULL;
			zbx_eval_context_t	ctx;

			trigger_expr = row[i + 2];

			if ('\0' == *trigger_expr)
			{
				if (0 == i)
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s: empty expression for trigger %s",
							__func__, row[0]);
				}
				continue;
			}

			if (FAIL == zbx_eval_parse_expression(&ctx, trigger_expr, ZBX_EVAL_TRIGGER_EXPRESSION_LLD,
					&error))
			{
				zabbix_log(LOG_LEVEL_CRIT, "%s: error parsing trigger expression for %s: %s",
						__func__, row[0], error);
				zbx_free(error);
				DBfree_result(result);
				return FAIL;
			}

			for (j = 0; j < ctx.stack.values_num; j++)
			{
				zbx_eval_token_t	*token = &ctx.stack.values[j];
				zbx_uint64_t		functionid;

				if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
					continue;

				if (SUCCEED != is_uint64_n(ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					zabbix_log(LOG_LEVEL_CRIT, "%s: error parsing trigger expression %s,"
							" is_uint64_n error", __func__, row[0]);
					DBfree_result(result);
					return FAIL;
				}

				result2 = DBselect(
						"select h.host,i.key_,f.name,f.parameter"
						" from functions f"
						" join items i on i.itemid=f.itemid"
						" join hosts h on h.hostid=i.hostid"
						" where f.functionid=" ZBX_FS_UI64,
						functionid);

				if (NULL != (row2 = DBfetch(result2)))
				{
					char	*func;

					func = DBpatch_make_trigger_function(row2[2], row2[0], row2[1], row2[3]);
					zbx_variant_clear(&token->value);
					zbx_variant_set_str(&token->value, func);
				}

				DBfree_result(result2);
			}

			zbx_eval_compose_expression(&ctx, &composed_expr[i]);
			zbx_eval_clear(&ctx);
		}

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "/%s/", row[1]);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", composed_expr[0]);
		if (NULL != composed_expr[1])
			zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "/%s", composed_expr[1]);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);

		zbx_free(composed_expr[0]);
		zbx_free(composed_expr[1]);
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

static int	DBpatch_5030200(void)
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
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s/%s/%s", templ_name, row[3], row[1]);

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

static int	DBpatch_5030201(void)
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
			"select h.hostid,h.host,h2.host,i.key_"
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
		seed = zbx_dsprintf(seed, "%s/%s/%s", name_tmpl, row[3], row[1]);
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
DBPATCH_ADD(5030101, 0, 1)
DBPATCH_ADD(5030102, 0, 1)
DBPATCH_ADD(5030103, 0, 1)
DBPATCH_ADD(5030104, 0, 1)
DBPATCH_ADD(5030105, 0, 1)
DBPATCH_ADD(5030106, 0, 1)
DBPATCH_ADD(5030107, 0, 1)
DBPATCH_ADD(5030108, 0, 1)
DBPATCH_ADD(5030109, 0, 1)
DBPATCH_ADD(5030110, 0, 1)
DBPATCH_ADD(5030111, 0, 1)
DBPATCH_ADD(5030112, 0, 1)
DBPATCH_ADD(5030113, 0, 1)
DBPATCH_ADD(5030114, 0, 1)
DBPATCH_ADD(5030115, 0, 1)
DBPATCH_ADD(5030116, 0, 1)
DBPATCH_ADD(5030117, 0, 1)
DBPATCH_ADD(5030118, 0, 1)
DBPATCH_ADD(5030119, 0, 1)
DBPATCH_ADD(5030120, 0, 1)
DBPATCH_ADD(5030121, 0, 1)
DBPATCH_ADD(5030122, 0, 1)
DBPATCH_ADD(5030123, 0, 1)
DBPATCH_ADD(5030127, 0, 1)
DBPATCH_ADD(5030128, 0, 1)
DBPATCH_ADD(5030129, 0, 1)
DBPATCH_ADD(5030130, 0, 1)
DBPATCH_ADD(5030131, 0, 1)
DBPATCH_ADD(5030132, 0, 1)
DBPATCH_ADD(5030133, 0, 1)
DBPATCH_ADD(5030134, 0, 1)
DBPATCH_ADD(5030135, 0, 1)
DBPATCH_ADD(5030136, 0, 1)
DBPATCH_ADD(5030137, 0, 1)
DBPATCH_ADD(5030138, 0, 1)
DBPATCH_ADD(5030139, 0, 1)
DBPATCH_ADD(5030140, 0, 1)
DBPATCH_ADD(5030141, 0, 1)
DBPATCH_ADD(5030142, 0, 1)
DBPATCH_ADD(5030143, 0, 1)
DBPATCH_ADD(5030144, 0, 1)
DBPATCH_ADD(5030145, 0, 1)
DBPATCH_ADD(5030146, 0, 1)
DBPATCH_ADD(5030147, 0, 1)
DBPATCH_ADD(5030148, 0, 1)
DBPATCH_ADD(5030149, 0, 1)
DBPATCH_ADD(5030150, 0, 1)
DBPATCH_ADD(5030151, 0, 1)
DBPATCH_ADD(5030152, 0, 1)
DBPATCH_ADD(5030153, 0, 1)
DBPATCH_ADD(5030154, 0, 1)
DBPATCH_ADD(5030155, 0, 1)
DBPATCH_ADD(5030156, 0, 1)
DBPATCH_ADD(5030157, 0, 1)
DBPATCH_ADD(5030158, 0, 1)
DBPATCH_ADD(5030159, 0, 1)
DBPATCH_ADD(5030160, 0, 1)
DBPATCH_ADD(5030161, 0, 1)
DBPATCH_ADD(5030162, 0, 1)
DBPATCH_ADD(5030163, 0, 1)
DBPATCH_ADD(5030164, 0, 1)
DBPATCH_ADD(5030165, 0, 1)
DBPATCH_ADD(5030166, 0, 1)
DBPATCH_ADD(5030167, 0, 1)
DBPATCH_ADD(5030168, 0, 1)
DBPATCH_ADD(5030169, 0, 1)
DBPATCH_ADD(5030170, 0, 1)
DBPATCH_ADD(5030171, 0, 1)
DBPATCH_ADD(5030172, 0, 1)
DBPATCH_ADD(5030173, 0, 1)
DBPATCH_ADD(5030174, 0, 1)
DBPATCH_ADD(5030175, 0, 1)
DBPATCH_ADD(5030176, 0, 1)
DBPATCH_ADD(5030177, 0, 1)
DBPATCH_ADD(5030178, 0, 1)
DBPATCH_ADD(5030179, 0, 1)
DBPATCH_ADD(5030180, 0, 1)
DBPATCH_ADD(5030181, 0, 1)
DBPATCH_ADD(5030182, 0, 1)
DBPATCH_ADD(5030183, 0, 1)
DBPATCH_ADD(5030184, 0, 1)
DBPATCH_ADD(5030185, 0, 1)
DBPATCH_ADD(5030186, 0, 1)
DBPATCH_ADD(5030187, 0, 1)
DBPATCH_ADD(5030188, 0, 1)
DBPATCH_ADD(5030189, 0, 1)
DBPATCH_ADD(5030190, 0, 1)
DBPATCH_ADD(5030191, 0, 1)
DBPATCH_ADD(5030192, 0, 1)
DBPATCH_ADD(5030193, 0, 1)
DBPATCH_ADD(5030194, 0, 1)
DBPATCH_ADD(5030195, 0, 1)
DBPATCH_ADD(5030196, 0, 1)
DBPATCH_ADD(5030197, 0, 1)
DBPATCH_ADD(5030198, 0, 1)
DBPATCH_ADD(5030199, 0, 1)
DBPATCH_ADD(5030200, 0, 1)
DBPATCH_ADD(5030201, 0, 1)

DBPATCH_END()
