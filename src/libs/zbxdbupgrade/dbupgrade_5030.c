/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

static void	DBpatch_5030000_get_key_fields(DB_ROW row, zbx_dbpatch_profile_t *profile, char **subsect, char **field)
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
		else if (3 == tok_idx)
		{
			*field = zbx_strdup(*field, token);
			break;
		}
	}
}

static int	DBpatch_5030000(void)
{
	int	i, ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

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
		"web.httpconf.php.sortorder"
	};

	for (i = 0; SUCCEED == ret && i < (int)ARRSIZE(keys); i++)
	{
		DB_ROW			row;
		DB_RESULT		result;
		zbx_dbpatch_profile_t	profile = {0};
		char			*subsect = NULL;
		char			*field = NULL;

		result = DBselect("SELECT profileid,userid,idx,idx2,value_id,value_int,value_str,source,type"
				" FROM profiles where idx='%s'", keys[i]);

		if (NULL == (row = DBfetch(result)))
		{
			DBfree_result(result);
			continue;
		}

		DBpatch_5030000_get_key_fields(row, &profile, &subsect, &field);

		DBfree_result(result);

		if (NULL == subsect || NULL == field)
		{
			zabbix_log(LOG_LEVEL_ERR, "Failed to parse profile key fields for key '%s'", keys[i]);
			ret = FAIL;
		}

		if (SUCCEED == ret && ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.hosts.%s.php.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
		}

		if (SUCCEED == ret && ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.templates.%s.php.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, field, profile.idx2, profile.value_id,
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
	}

	return ret;
}

static int	DBpatch_5030001(void)
{
	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ("
		"'web.items.filter_application',"
		"'web.items.filter_delay',"
		"'web.items.filter_discovery',"
		"'web.items.filter_groupids',"
		"'web.items.filter_history',"
		"'web.items.filter_hostids',"
		"'web.items.filter_ipmi_sensor',"
		"'web.items.filter_key',"
		"'web.items.filter_name',"
		"'web.items.filter_port',"
		"'web.items.filter_snmp_community',"
		"'web.items.filter_snmp_oid',"
		"'web.items.filter_snmpv3_securityname',"
		"'web.items.filter_state',"
		"'web.items.filter_status',"
		"'web.items.filter_templated_items',"
		"'web.items.filter_trends',"
		"'web.items.filter_type',"
		"'web.items.filter_value_type',"
		"'web.items.filter_with_triggers',"
		"'web.items.subfilter_apps',"
		"'web.items.subfilter_discovery',"
		"'web.items.subfilter_history',"
		"'web.items.subfilter_hosts',"
		"'web.items.subfilter_interval',"
		"'web.items.subfilter_state',"
		"'web.items.subfilter_status',"
		"'web.items.subfilter_templated_items',"
		"'web.items.subfilter_trends',"
		"'web.items.subfilter_types',"
		"'web.items.subfilter_value_types',"
		"'web.items.subfilter_with_triggers',"
		"'web.triggers.filter.evaltype',"
		"'web.triggers.filter.tags.operator',"
		"'web.triggers.filter.tags.tag',"
		"'web.triggers.filter.tags.value',"
		"'web.triggers.filter_dependent',"
		"'web.triggers.filter_discovered',"
		"'web.triggers.filter_groupids',"
		"'web.triggers.filter_hostids',"
		"'web.triggers.filter_inherited',"
		"'web.triggers.filter_name',"
		"'web.triggers.filter_priority',"
		"'web.triggers.filter_state',"
		"'web.triggers.filter_status',"
		"'web.triggers.filter_value',"
		"'web.graphs.filter_groups',"
		"'web.graphs.filter_hostids',"
		"'web.host_discovery.filter.delay',"
		"'web.host_discovery.filter.groupids',"
		"'web.host_discovery.filter.hostids',"
		"'web.host_discovery.filter.key',"
		"'web.host_discovery.filter.lifetime',"
		"'web.host_discovery.filter.name',"
		"'web.host_discovery.filter.snmp_oid',"
		"'web.host_discovery.filter.state',"
		"'web.host_discovery.filter.status',"
		"'web.host_discovery.filter.type',"
		"'web.httpconf.filter_groups',"
		"'web.httpconf.filter_hostids',"
		"'web.httpconf.filter_status')"))
		return FAIL;

	return SUCCEED;
}

#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)
DBPATCH_ADD(5030001, 0, 1)

DBPATCH_END()
