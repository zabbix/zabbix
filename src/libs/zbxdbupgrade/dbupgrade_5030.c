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

static int	DBpatch_5030039(void)
{
	return validate_types_in_opcommand();
}

static int	DBpatch_5030040(void)
{
	const ZBX_FIELD	field = {"scope", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030041(void)
{
	const ZBX_FIELD	field = {"port", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030042(void)
{
	const ZBX_FIELD	field = {"authtype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030043(void)
{
	const ZBX_FIELD	field = {"username", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030044(void)
{
	const ZBX_FIELD	field = {"password", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030045(void)
{
	const ZBX_FIELD	field = {"publickey", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030046(void)
{
	const ZBX_FIELD	field = {"privatekey", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030047(void)
{
	const ZBX_FIELD	field = {"menu_path", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_5030048 (part of ZBXNEXT-6368)                           *
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
static int	DBpatch_5030048(void)
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
 * Function: DBpatch_5030049 (part of ZBXNEXT-6368)                           *
 *                                                                            *
 * Purpose: rename some {HOST.*} macros to {HOST.TARGET.*} in existing global *
 *          scripts which are used in actions                                 *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_5030049(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	if (NULL == (result = DBselect("select scriptid,command"
			" from scripts"
			" where scriptid in ("
			"select distinct scriptid"
			" from opcommand"
			" where scriptid is not null)")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot select from table 'scripts'", __func__);
		return FAIL;
	}

	while (NULL != (row = DBfetch(result)))
	{
		const char	*scriptid = row[0];
		const char	*command = row[1];
		char		*command_new = NULL;
		int		rc;

		command_new  = zbx_rename_host_macros(command);

		rc = DBexecute("update scripts set command='%s' where scriptid=%s",
				command_new, scriptid);

		zbx_free(command_new);

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
 * Function: DBpatch_5030050 (part of ZBXNEXT-6368)                           *
 *                                                                            *
 * Purpose: split script name between 'menu_path' and 'name' columns for      *
 *          existing global scripts                                           *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_5030050(void)
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
 * Function: DBpatch_5030051   (part of ZBXNEXT-6368)                         *
 *                                                                            *
 * Purpose: migrate remote commands from table 'opcommand' to table 'scripts' *
 *          and convert them into global scripts                              *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_5030051(void)
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

static int	DBpatch_5030052(void)
{
	const ZBX_FIELD field = {"scriptid", NULL, "scripts","scriptid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("opcommand", &field);
}

static int	DBpatch_5030053(void)
{
	return DBdrop_field("opcommand", "execute_on");
}

static int	DBpatch_5030054(void)
{
	return DBdrop_field("opcommand", "port");
}

static int	DBpatch_5030055(void)
{
	return DBdrop_field("opcommand", "authtype");
}

static int	DBpatch_5030056(void)
{
	return DBdrop_field("opcommand", "username");
}

static int	DBpatch_5030057(void)
{
	return DBdrop_field("opcommand", "password");
}

static int	DBpatch_5030058(void)
{
	return DBdrop_field("opcommand", "publickey");
}

static int	DBpatch_5030059(void)
{
	return DBdrop_field("opcommand", "privatekey");
}

static int	DBpatch_5030060(void)
{
	return DBdrop_field("opcommand", "command");
}

static int	DBpatch_5030061(void)
{
	return DBdrop_field("opcommand", "type");
}

static int	DBpatch_5030062(void)
{
	const ZBX_FIELD	old_field = {"command", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"command", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_remote_command", &field, &old_field);
}
/*  end of ZBXNEXT-6368 patches */
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

DBPATCH_END()
