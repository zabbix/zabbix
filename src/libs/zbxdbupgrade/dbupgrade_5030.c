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

DBPATCH_END()
