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

#include "zabbix_users.h"

#include "db.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Check user permissions to access system                           *
 *                                                                            *
 * Parameters: userid - user ID                                               *
 *                                                                            *
 * Return value: SUCCEED - access allowed, FAIL - otherwise                   *
 *                                                                            *
 ******************************************************************************/
int	check_perm2system(zbx_uint64_t userid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;

	result = DBselect(
			"select count(*)"
			" from usrgrp g,users_groups ug"
			" where ug.userid=" ZBX_FS_UI64
				" and g.usrgrpid=ug.usrgrpid"
				" and g.users_status=%d",
			userid, GROUP_STATUS_DISABLED);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]) && atoi(row[0]) > 0)
		res = FAIL;

	DBfree_result(result);

	return res;
}

char	*get_user_timezone(zbx_uint64_t userid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*user_timezone;

	result = DBselect("select timezone from users where userid=" ZBX_FS_UI64, userid);

	if (NULL != (row = DBfetch(result)))
		user_timezone = zbx_strdup(NULL, row[0]);
	else
		user_timezone = NULL;

	DBfree_result(result);

	return user_timezone;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the user has specific or default access for              *
 *          administration actions                                            *
 *                                                                            *
 * Return value:  SUCCEED - the access is granted                             *
 *                FAIL    - the access is denied                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_user_administration_actions_permissions(const zbx_user_t *user, const char *role_rule_default,
		const char *role_rule)
{
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 , __func__, user->userid);

	result = DBselect("select value_int,name from role_rule where roleid=" ZBX_FS_UI64
			" and (name='%s' or name='%s')", user->roleid, role_rule,
			role_rule_default);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 == strcmp(role_rule, row[1]))
		{
			if (ROLE_PERM_ALLOW == atoi(row[0]))
				ret = SUCCEED;
			else
				ret = FAIL;
			break;
		}
		else if (0 == strcmp(role_rule_default, row[1]))
		{
			if (ROLE_PERM_ALLOW == atoi(row[0]))
				ret = SUCCEED;
		}
		else
			THIS_SHOULD_NEVER_HAPPEN;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
