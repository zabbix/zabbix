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
#include "zbxjson.h"
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

static int	DBpatch_5030040(void)
{
	return DBcreate_index("item_tag", "item_tag_1", "itemid", 0);
}

static int	DBpatch_5030041(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_tag", 1, &field);
}

static int	DBpatch_5030042(void)
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

static int	DBpatch_5030043(void)
{
	return DBcreate_index("httptest_tag", "httptest_tag_1", "httptestid", 0);
}

static int	DBpatch_5030044(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest_tag", 1, &field);
}

static int	DBpatch_5030045(void)
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

static int	DBpatch_5030046(void)
{
	return DBcreate_index("sysmaps_element_tag", "sysmaps_element_tag_1", "selementid", 0);
}

static int	DBpatch_5030047(void)
{
	const ZBX_FIELD	field = {"selementid", NULL, "sysmaps_elements", "selementid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmaps_element_tag", 1, &field);
}

static int	DBpatch_5030048(void)
{
	const ZBX_FIELD	field = {"evaltype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_elements", &field);
}

static int	DBpatch_5030049(void)
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

static int	DBpatch_5030050(void)
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

static int	DBpatch_5030051(void)
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

static int	DBpatch_5030052(void)
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

static int	DBpatch_5030053(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "event_tag", "eventtagid", "eventid", "tag", "value", NULL);

	result = DBselect(
			"select distinct e.eventid,it.tag,it.value from events e"
			" join triggers t on e.objectid=t.triggerid"
			" join functions f on t.triggerid=f.triggerid"
			" join items i on i.itemid=f.itemid"
			" join item_tag it on i.itemid=it.itemid"
			" where e.source in (%d,%d) and e.object=%d and t.flags in (%d,%d) order by e.eventid",
			EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER, ZBX_FLAG_DISCOVERY_NORMAL,
			ZBX_FLAG_DISCOVERY_CREATED);

	while (NULL != (row = DBfetch(result)))
	{
		DB_ROW		rowN;
		DB_RESULT	resultN;
		zbx_uint64_t	eventid;
		char		*tag, *value, tmp[MAX_STRING_LEN];

		ZBX_DBROW2UINT64(eventid, row[0]);
		tag = DBdyn_escape_string(row[1]);
		value = DBdyn_escape_string(row[2]);
		zbx_snprintf(tmp, sizeof(tmp),
				"select null from event_tag where eventid=" ZBX_FS_UI64 " and tag='%s' and value='%s'",
				eventid, tag, value);

		resultN = DBselectN(tmp, 1);

		if (NULL == (rowN = DBfetch(resultN)))
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), eventid, tag, value);

		DBfree_result(resultN);
		zbx_free(tag);
		zbx_free(value);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "eventtagid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030054(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "event_tag", "eventtagid", "eventid", "tag", "value", NULL);

	result = DBselect(
			"select distinct e.eventid,it.tag,it.value from events e"
			" join items i on i.itemid=e.objectid"
			" join item_tag it on i.itemid=it.itemid"
			" where e.source=%d and e.object=%d and i.flags in (%d,%d)",
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, ZBX_FLAG_DISCOVERY_NORMAL,
			ZBX_FLAG_DISCOVERY_CREATED);

	while (NULL != (row = DBfetch(result)))
	{
		DB_ROW		rowN;
		DB_RESULT	resultN;
		zbx_uint64_t	eventid;
		char		*tag, *value, tmp[MAX_STRING_LEN];

		ZBX_DBROW2UINT64(eventid, row[0]);
		tag = DBdyn_escape_string(row[1]);
		value = DBdyn_escape_string(row[2]);
		zbx_snprintf(tmp, sizeof(tmp),
				"select null from event_tag where eventid=" ZBX_FS_UI64 " and tag='%s' and value='%s'",
				eventid, tag, value);

		resultN = DBselectN(tmp, 1);

		if (NULL == (rowN = DBfetch(resultN)))
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), eventid, tag, value);

		DBfree_result(resultN);
		zbx_free(tag);
		zbx_free(value);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "eventtagid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030055(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "problem_tag", "problemtagid", "eventid", "tag", "value", NULL);

	result = DBselect("select eventid,tag,value from event_tag");

	while (NULL != (row = DBfetch(result)))
	{
		DB_ROW		rowN;
		DB_RESULT	resultN;
		zbx_uint64_t	eventid;
		char		*tag, *value, tmp[MAX_STRING_LEN];

		ZBX_DBROW2UINT64(eventid, row[0]);
		tag = DBdyn_escape_string(row[1]);
		value = DBdyn_escape_string(row[2]);
		zbx_snprintf(tmp, sizeof(tmp),
				"select null from problem_tag where eventid=" ZBX_FS_UI64 " and tag='%s'"
				" and value='%s'", eventid, tag, value);

		resultN = DBselectN(tmp, 1);

		if (NULL == (rowN = DBfetch(resultN)))
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), eventid, tag, value);

		DBfree_result(resultN);
		zbx_free(tag);
		zbx_free(value);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "problemtagid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030056(void)
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

static int	DBpatch_5030057(void)
{
#define AUDIT_RESOURCE_APPLICATION	12
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from auditlog where resourcetype=%d", AUDIT_RESOURCE_APPLICATION))
		return FAIL;

	return SUCCEED;
#undef AUDIT_RESOURCE_APPLICATION
}

static int	DBpatch_5030058(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ("
			"'web.items.subfilter_apps','web.latest.filter.application',"
			"'web.overview.filter.application','web.applications.filter.active',"
			"'web.applications.filter_groups','web.applications.filter_hostids',"
			"'web.applications.php.sort','web.applications.php.sortorder')"))
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

ZBX_PTR_VECTOR_DECL(patch_filtertag, patch_filtertag_t);
ZBX_PTR_VECTOR_IMPL(patch_filtertag, patch_filtertag_t);

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
							tag.op = zbx_strdup(NULL, "1");
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

static int	DBpatch_5030059(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

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

		if (SUCCEED != (ret = zbx_json_open(row[1], &jp)) ||
				SUCCEED != (ret = DBpatch_parse_applications_json(&jp, &json, &tags, &app, 0)))
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to parse web.monitoring.problem.properties JSON");
		}

		zbx_vector_patch_filtertag_clear_ext(&tags, patch_filtertag_free);
		zbx_vector_patch_filtertag_destroy(&tags);
		zbx_free(app);

		value_str = DBdyn_escape_string(json.buffer);
		zbx_json_free(&json);

		ZBX_DBROW2UINT64(profileid, row[0]);

		if (ZBX_DB_OK > DBexecute("update profiles set value_str='%s' where profileid=" ZBX_FS_UI64,
				value_str, profileid))
		{
			ret = FAIL;
		}

		zbx_free(value_str);
	}
	DBfree_result(result);

	return ret;
}

static int	DBpatch_5030060(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_db_insert_t		db_insert;
	zbx_vector_uint64_t	widget_fieldids;
	int			ret = SUCCEED;

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
		char		*tag, *value_str;

		ZBX_DBROW2UINT64(widgetid, row[0]);
		tag = DBdyn_escape_string(row[1]);
		ZBX_DBROW2UINT64(widget_fieldid, row[2]);

		value_str = zbx_dsprintf(NULL, "Application: %s", tag);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 0, "tags.operator.0", 0, "");
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 1, "tags.tag.0", 0, value_str);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 1, "tags.operator.0", 0, "");

		zbx_vector_uint64_append(&widget_fieldids, widget_fieldid);

		zbx_free(tag);
		zbx_free(value_str);
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

static int	DBpatch_5030061(void)
{
	return DBdrop_foreign_key("httptest", 1);
}

static int	DBpatch_5030062(void)
{
	return DBdrop_index("httptest", "httptest_1");
}

static int	DBpatch_5030063(void)
{
	return DBdrop_field("httptest", "applicationid");
}

static int	DBpatch_5030064(void)
{
	return DBdrop_field("sysmaps_elements", "application");
}

static int	DBpatch_5030065(void)
{
	return DBdrop_table("application_discovery");
}

static int	DBpatch_5030066(void)
{
	return DBdrop_table("item_application_prototype");
}

static int	DBpatch_5030067(void)
{
	return DBdrop_table("application_prototype");
}

static int	DBpatch_5030068(void)
{
	return DBdrop_table("application_template");
}

static int	DBpatch_5030069(void)
{
	return DBdrop_table("items_applications");
}

static int	DBpatch_5030070(void)
{
	return DBdrop_table("applications");
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

DBPATCH_END()
