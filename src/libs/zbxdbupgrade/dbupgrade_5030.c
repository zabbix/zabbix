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

	int		i;
	DB_ROW		row;
	DB_RESULT	result;

	const char	*profiles[] = {
		"web.hosts.php.sort", "web.hosts.php.sortorder"
	};

	for (i = 0; i < (int)ARRSIZE(profiles); i++) {
		zbx_uint64_t	profileid;
		zbx_uint64_t	userid;
		char		*idx = zbx_strdup(idx, profiles[i]);
		zbx_uint64_t	idx2 = 0;
		zbx_uint64_t	value_id = 0;
		int	value_int = 0;
		char		*value_str = NULL;
		char		*source = NULL;
		int	type = 0;

		result = DBselect("SELECT profileid, userid, idx, idx2, value_id, value_int, value_str, source, type"
				" FROM profiles where idx = '%s'", profiles[i]);

		row = DBfetch(result);

		if (NULL == row)
			return FAIL;

		ZBX_DBROW2UINT64(profileid, row[0]);
		ZBX_DBROW2UINT64(userid, row[1]);
		ZBX_DBROW2UINT64(idx2, row[3]);
		ZBX_DBROW2UINT64(value_id, row[4]);
		value_int = atoi(row[5]);
		value_str = zbx_strdup(value_str, row[6]);
		source = zbx_strdup(source, row[7]);
		type = atoi(row[8]);

		int tok_idx	= 0;
		char *token	= strtok(idx, ".");
		char *subsect	= 0;
		char *field	= 0;

		while (NULL != token) {
			token = strtok(NULL, ".");
			++tok_idx;
			if (1 == tok_idx) {
				subsect = zbx_strdup(subsect, token);
			} else if (3 == tok_idx) {
				field = zbx_strdup(field, token);
				break;
			}
		}

		if (0 == subsect || 0 == field)
			return FAIL;

		zbx_db_insert_t db_insert_section;

		zbx_db_insert_prepare(&db_insert_section, "profiles", "profileid", "userid",
			"idx", "idx2", "value_id", "value_int", "value_str", "source", "type", NULL);


		char idx_hosts[MAX_STRING_LEN];
		char idx_templates[MAX_STRING_LEN];

		zbx_snprintf(idx_hosts, MAX_STRING_LEN, "web.hosts.%s.%s", subsect, field);
		zbx_snprintf(idx_templates, MAX_STRING_LEN, "web.templates.%s.%s", subsect, field);


		zbx_db_insert_add_values(&db_insert_section, DBget_maxid("profiles"),
			userid, idx_hosts, idx2, value_id, value_int, value_str, source, type);
		zbx_db_insert_add_values(&db_insert_section, DBget_maxid("profiles"),
			userid, idx_templates, idx2, value_id, value_int, value_str, source, type);

		if (SUCCEED != zbx_db_insert_execute(&db_insert_section))
			return FAIL;

		zbx_db_insert_clean(&db_insert_section);

	}

	return SUCCEED;
}

#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)

DBPATCH_END()
