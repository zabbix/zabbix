/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxdbwrap.h"

#include "zbxdb.h"

/* group statuses */
typedef enum
{
	GROUP_STATUS_ACTIVE = 0,
	GROUP_STATUS_DISABLED
}
zbx_group_status_type_t;

/******************************************************************************
 *                                                                            *
 * Purpose: Check user permissions to access system.                          *
 *                                                                            *
 * Parameters: userid - [IN]                                                  *
 *                                                                            *
 * Return value: SUCCEED - access allowed, FAIL - otherwise                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_check_user_perm2system(zbx_uint64_t userid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		res = SUCCEED;

	result = zbx_db_select(
			"select count(*)"
			" from usrgrp g,users_groups ug"
			" where ug.userid=" ZBX_FS_UI64
				" and g.usrgrpid=ug.usrgrpid"
				" and g.users_status=%d",
			userid, GROUP_STATUS_DISABLED);

	if (NULL != (row = zbx_db_fetch(result)) && SUCCEED != zbx_db_is_null(row[0]) && atoi(row[0]) > 0)
		res = FAIL;

	zbx_db_free_result(result);

	return res;
}

char	*zbx_db_get_user_timezone(zbx_uint64_t userid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*user_timezone;

	result = zbx_db_select("select timezone from users where userid=" ZBX_FS_UI64, userid);

	if (NULL != (row = zbx_db_fetch(result)))
		user_timezone = zbx_strdup(NULL, row[0]);
	else
		user_timezone = NULL;

	zbx_db_free_result(result);

	return user_timezone;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Checks if the user has specific or default access for             *
 *          administration actions.                                           *
 *                                                                            *
 * Return value:  SUCCEED - access is granted                                 *
 *                FAIL    - access is denied                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_user_has_administration_actions_permissions(const zbx_user_t *user, const char *role_rule_default,
		const char *role_rule)
{
	int		ret = FAIL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 " rule:%s", __func__, user->userid, role_rule);

	result = zbx_db_select(
			"select value_int,name"
			" from role_rule"
			" where roleid=" ZBX_FS_UI64
				" and (name='%s' or name='%s')",
			user->roleid, role_rule, role_rule_default);

	while (NULL != (row = zbx_db_fetch(result)))
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
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate user permissions for monitoring on server                *
 *                                                                            *
 * Parameters: user - [IN] user information                                   *
 *                                                                            *
 * Return value: SUCCEED - monitoring on server is allowed                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: USER_TYPE_ZABBIX_USER can't have server monitoring permission    *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_server_allowed_for_monitoring(const zbx_user_t *user)
{
#define ZBX_USER_ROLE_PERMISSION_ACTIONS_DEFAULT_ACCESS			"actions.default_access"
#define ZBX_USER_ROLE_PERMISSION_ACTIONS_SELECT_SERVER_FOR_MONITORING	"actions.select_server_for_monitoring"

	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64, __func__, user->userid);

	if (USER_TYPE_ZABBIX_ADMIN > user->type)
		goto out;

	if (SUCCEED == zbx_db_user_has_administration_actions_permissions(user,
			ZBX_USER_ROLE_PERMISSION_ACTIONS_DEFAULT_ACCESS,
			ZBX_USER_ROLE_PERMISSION_ACTIONS_SELECT_SERVER_FOR_MONITORING))
	{
		ret = SUCCEED;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#undef ZBX_USER_ROLE_PERMISSION_ACTIONS_DEFAULT_ACCESS
#undef ZBX_USER_ROLE_PERMISSION_ACTIONS_SELECT_SERVER_FOR_MONITORING
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate user permissions for monitoring on proxy                 *
 *                                                                            *
 * Parameters: user    - [IN] user information                                *
 *             proxyid - [IN]                                                 *
 *                                                                            *
 * Return value: SUCCEED - monitoring on proxy is allowed                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: USER_TYPE_SUPER_ADMIN is always allowed to monitor on proxies    *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_proxy_allowed_for_monitoring(const zbx_user_t *user, zbx_uint64_t proxyid)
{
#	define PROXY_MODE_DENY		"0"
#	define PROXY_MODE_ALLOW		"1"
#	define PROXY_GROUP_MODE_DENY	"0"
#	define PROXY_GROUP_MODE_ALLOW	"1"

	int			ret = FAIL;
	zbx_db_query_mask_t	old_queries;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_uint64_t		proxy_groupid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 " proxyid:" ZBX_FS_UI64,
			__func__, user->userid, proxyid);

	if (USER_TYPE_SUPER_ADMIN == user->type)
	{
		ret = SUCCEED;
		goto out;
	}

	old_queries = zbx_db_set_log_masked_values(ZBX_DB_MASK_QUERIES);

	result = zbx_db_select(
			"select p.proxy_groupid"
			" from proxy p"
			" where exists ("
				" select NULL"
				" from users_groups uug"
				" where uug.userid=" ZBX_FS_UI64
			" ) and p.proxyid=" ZBX_FS_UI64,
			user->userid, proxyid);

	if (NULL == (row = zbx_db_fetch(result)))
		goto clean;

	ZBX_DBROW2UINT64(proxy_groupid, row[0]);

	zbx_db_free_result(result);

	if (0 == proxy_groupid)
	{
		result = zbx_db_select(
				"select null"
				" from proxy p"
				" where p.proxyid=" ZBX_FS_UI64
					" and not exists ("
						" select null"
						" from usrgrp_proxy ugp"
							" join users_groups uug on ugp.usrgrpid=uug.usrgrpid"
							" join usrgrp ug on uug.usrgrpid=ug.usrgrpid"
						" where p.proxyid=ugp.proxyid"
							" and uug.userid=" ZBX_FS_UI64
							" and ug.proxy_mode=" PROXY_MODE_DENY
					") and ("
						"not exists ("
							" select null"
							" from users_groups uug"
							" join usrgrp ug on uug.usrgrpid=ug.usrgrpid"
							" where uug.userid=" ZBX_FS_UI64
								" and ug.proxy_mode=" PROXY_MODE_ALLOW
						") or exists ("
							" select null"
							" from usrgrp_proxy ugp"
								" join users_groups uug on ugp.usrgrpid=uug.usrgrpid"
								" join usrgrp ug on uug.usrgrpid=ug.usrgrpid"
							" where p.proxyid=ugp.proxyid"
								" and uug.userid=" ZBX_FS_UI64
								" and ug.proxy_mode=" PROXY_MODE_ALLOW
						")"
					")",
				proxyid, user->userid, user->userid, user->userid);
	}
	else
	{
		result = zbx_db_select(
				"select null"
				" from proxy_group pg"
				" where pg.proxy_groupid=" ZBX_FS_UI64
					" and not exists ("
						" select null"
						" from usrgrp_proxy_group ugpg"
							" join users_groups uug on ugpg.usrgrpid=uug.usrgrpid"
							" join usrgrp ug on uug.usrgrpid=ug.usrgrpid"
						" where pg.proxy_groupid=ugpg.proxy_groupid"
							" and uug.userid=" ZBX_FS_UI64
							" and ug.proxy_group_mode=" PROXY_GROUP_MODE_DENY
					") and ("
						"not exists ("
							" select null"
							" from users_groups uug"
								" join usrgrp ug on uug.usrgrpid=ug.usrgrpid"
							" where uug.userid=" ZBX_FS_UI64
								" and ug.proxy_group_mode=" PROXY_GROUP_MODE_ALLOW
						") or exists ("
							" select null"
							" from usrgrp_proxy_group ugpg"
								" join users_groups uug on ugpg.usrgrpid=uug.usrgrpid"
								" join usrgrp ug on uug.usrgrpid=ug.usrgrpid"
							" where pg.proxy_groupid=ugpg.proxy_groupid"
								" and uug.userid=" ZBX_FS_UI64
								" and ug.proxy_group_mode=" PROXY_GROUP_MODE_ALLOW
						")"
					")",
				proxy_groupid, user->userid, user->userid, user->userid);
	}

	if (NULL != zbx_db_fetch(result))
		ret = SUCCEED;
clean:
	zbx_db_free_result(result);
	zbx_db_set_log_masked_values(old_queries);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef PROXY_MODE_DENY
#	undef PROXY_MODE_ALLOW
#	undef PROXY_GROUP_MODE_DENY
#	undef PROXY_GROUP_MODE_ALLOW
}
