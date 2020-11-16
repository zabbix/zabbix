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
	zbx_uint64_t id;
	zbx_uint64_t userid;
	char *idx;
	zbx_uint64_t idx2;
	zbx_uint64_t value_id;
	int value_int;
	char *value_str;
	char *source;
	int type;
} DBpatch_profile_t;

static void	get_key_fields(DB_ROW row, DBpatch_profile_t *profile, char **subsect, char **field)
{
	int tok_idx	= 0;
	char *token;

	ZBX_DBROW2UINT64(profile->id, row[0]);
	ZBX_DBROW2UINT64(profile->userid, row[1]);
	ZBX_DBROW2UINT64(profile->idx2, row[3]);
	ZBX_DBROW2UINT64(profile->value_id, row[4]);

	profile->value_int = atoi(row[5]);
	profile->idx = zbx_strdup(profile->idx, row[2]);
	profile->value_str = zbx_strdup(profile->value_str, row[6]);
	profile->source = zbx_strdup(profile->source, row[7]);
	profile->type = atoi(row[8]);

	token = strtok(profile->idx, ".");


	while (NULL != token) {
		token = strtok(NULL, ".");
		++tok_idx;

		if (3 == tok_idx)
		{
			*field = zbx_strdup(*field, token);
			break;
		}
		else if (1 == tok_idx)
			*subsect = zbx_strdup(*subsect, token);
	}
}

static int	split_profile_keys(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	int	i;

	const char	*profiles[] = {
		"web.hosts.php.sort", "web.hosts.php.sortorder",
		"web.triggers.php.sort", "web.triggers.php.sortorder"
	};

	for (i = 0; i < (int)ARRSIZE(profiles); i++) {
		DB_ROW			row;
		DB_RESULT		result;
		DBpatch_profile_t	profile = {0};
		char			*subsect = NULL;
		char			*field = NULL;

		result = DBselect("SELECT profileid, userid, idx, idx2, value_id, value_int, value_str, source, type"
				" FROM profiles where idx = '%s'", profiles[i]);

		row = DBfetch(result);

		if (NULL == row)
			return FAIL;

		get_key_fields(row, &profile, &subsect, &field);

		if (0 == subsect || 0 == field)
			return FAIL;

		if (ZBX_DB_OK > DBexecute("delete from profiles where profileid = %lu", profile.id))
			return FAIL;

		if (ZBX_DB_OK > DBexecute("insert into profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, source, type) "
				"values (%lu, %lu, 'web.hosts.%s.php.%s', %lu, %lu, %i, '%s', '%s', %i)",
				DBget_maxid("profiles"), profile.userid, subsect, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
			return FAIL;

		if (ZBX_DB_OK > DBexecute("insert into profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, source, type) "
				"values (%lu, %lu, 'web.templates.%s.php.%s', %lu, %lu, %i, '%s', '%s', %i)",
				DBget_maxid("profiles"), profile.userid, subsect, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030000(void)
{
	return split_profile_keys();
}

#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)

DBPATCH_END()
