/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
#include "zbxalgo.h"
#include "db.h"
#include "log.h"

static void	zbx_tag_filter_free(zbx_tag_filter_t *tag_filter)
{
	zbx_free(tag_filter->tag);
	zbx_free(tag_filter->value);
	zbx_free(tag_filter);
}

/******************************************************************************
 *                                                                            *
 * Function: check_perm2system                                                *
 *                                                                            *
 * Purpose: Checking user permissions to access system.                       *
 *                                                                            *
 * Parameters: userid - user ID                                               *
 *                                                                            *
 * Return value: SUCCEED - permission is positive, FAIL - otherwise           *
 *                                                                            *
 * Author:                                                                    *
 *                                                                            *
 * Comments:                                                                  *
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

static	int	get_user_type(zbx_uint64_t userid)
{
	int		user_type = -1;
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select type from users where userid=" ZBX_FS_UI64, userid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		user_type = atoi(row[0]);

	DBfree_result(result);

	return user_type;
}

/******************************************************************************
 *                                                                            *
 * Function: get_hostgroups_permission                                        *
 *                                                                            *
 * Purpose: Return user permissions for access to the host                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 * Author:                                                                    *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_hostgroups_permission(zbx_uint64_t userid, zbx_vector_uint64_t *hostgroupids)
{
	const char	*__function_name = "get_hostgroups_permission";
	int		perm = PERM_DENY;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == hostgroupids->values_num)
		goto out;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select min(r.permission)"
			" from rights r"
			" join users_groups ug on ug.usrgrpid=r.groupid"
				" where ug.userid=" ZBX_FS_UI64 " and", userid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "r.id",
			hostgroupids->values, hostgroupids->values_num);
	result = DBselect("%s", sql);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		perm = atoi(row[0]);

	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_permission_string(perm));

	return perm;
}

/******************************************************************************
 *                                                                            *
 * Function: check_tag_based_permission                                       *
 *                                                                            *
 * Purpose: Check user access to event by tags                                *
 *                                                                            *
 * Parameters: userid       - user id                                         *
 *             hostgroupids - list of host groups in which trigger was to     *
 *                            be found                                        *
 *             event        - checked event for access                        *
 *                                                                            *
 * Return value: SUCCEED - user has access                                    *
 *               FAIL    - user does not have access                          *
 *                                                                            *
 ******************************************************************************/
static int	check_tag_based_permission(zbx_uint64_t userid, zbx_vector_uint64_t *hostgroupids,
		const DB_EVENT *event)
{
	const char		*__function_name = "get_tag_based_permission";
	char			*sql = NULL, tmp[ZBX_MAX_UINT64_LEN];
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			ret = FAIL, i, n;
	zbx_vector_ptr_t	tag_filters, conditions;
	zbx_tag_filter_t	*tag_filter;
	DB_CONDITION		*condition;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&tag_filters);
	zbx_vector_ptr_sort(&tag_filters, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select tf.groupid,tf.tag,tf.value from tag_filter tf"
			" join users_groups ug on ug.usrgrpid=tf.usrgrpid"
				" where ug.userid=" ZBX_FS_UI64 " and", userid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "tf.groupid",
			hostgroupids->values, hostgroupids->values_num);
	result = DBselect("%s order by tf.groupid", sql);

	while (NULL != (row = DBfetch(result)))
	{
		tag_filter = (zbx_tag_filter_t *)zbx_malloc(NULL, sizeof(zbx_tag_filter_t));
		ZBX_STR2UINT64(tag_filter->hostgrouid, row[0]);
		tag_filter->tag = zbx_strdup(NULL, row[1]);
		tag_filter->value = zbx_strdup(NULL, row[2]);
		zbx_vector_ptr_append(&tag_filters, tag_filter);
	}

	/* check if the hostgroup does not have any tag filters, then the user has access to event */
	for (i = 0; i < hostgroupids->values_num; i++)
	{
		for (n = 0; n < tag_filters.values_num; n++)
		{
			if (hostgroupids->values[i] == ((zbx_tag_filter_t *)tag_filters.values[n])->hostgrouid)
				break;
		}
		if (tag_filters.values_num == n)
		{
			ret = SUCCEED;
			goto out;
		}
	}

	/* if all conditions at least one of tag filter is matched then user has access to event */
	for (i = 0; i < tag_filters.values_num && SUCCEED != ret; i++)
	{
		tag_filter = (zbx_tag_filter_t *)tag_filters.values[i];

		zbx_vector_ptr_create(&conditions);

		condition = (DB_CONDITION *)zbx_malloc(NULL, sizeof(DB_CONDITION));
		memset(condition, 0, sizeof(DB_CONDITION));
		condition->conditiontype = CONDITION_TYPE_HOST_GROUP;
		condition->operator = CONDITION_OPERATOR_EQUAL;
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_UI64, tag_filter->hostgrouid);
		condition->value = zbx_strdup(NULL, tmp);
		zbx_vector_ptr_append(&conditions, condition);

		condition = (DB_CONDITION *)zbx_malloc(NULL, sizeof(DB_CONDITION));
		memset(condition, 0, sizeof(DB_CONDITION));
		condition->conditiontype = CONDITION_TYPE_EVENT_TAG;
		condition->operator = CONDITION_OPERATOR_EQUAL;
		condition->value = zbx_strdup(NULL, tag_filter->tag);
		zbx_vector_ptr_append(&conditions, condition);

		if (NULL != tag_filter->value && 0 != strlen(tag_filter->value))
		{
			condition = (DB_CONDITION *)zbx_malloc(NULL, sizeof(DB_CONDITION));
			memset(condition, 0, sizeof(DB_CONDITION));
			condition->conditiontype = CONDITION_TYPE_EVENT_TAG_VALUE;
			condition->operator = CONDITION_OPERATOR_EQUAL;
			condition->value2 = zbx_strdup(NULL, tag_filter->tag);
			condition->value = zbx_strdup(NULL, tag_filter->value);
			zbx_vector_ptr_append(&conditions, condition);
		}

		for (n = 0; n < conditions.values_num; n++)
		{
			if (FAIL == check_action_condition(event, (DB_CONDITION *)conditions.values[n]))
				break;
		}

		if (n == conditions.values_num)
			ret = SUCCEED;

		for (n = 0; n < conditions.values_num; n++)
			zbx_db_condition_clean((DB_CONDITION *)conditions.values[n]);

		zbx_vector_ptr_destroy(&conditions);
	}
out:
	DBfree_result(result);

	zbx_vector_ptr_clear_ext(&tag_filters, (zbx_clean_func_t)zbx_tag_filter_free);
	zbx_vector_ptr_destroy(&tag_filters);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_trigger_permission                                           *
 *                                                                            *
 * Purpose: Return user permissions for access to trigger                     *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 ******************************************************************************/
int	get_trigger_permission(zbx_uint64_t userid, const DB_EVENT *event)
{
	const char		*__function_name = "get_trigger_permission";
	int			perm = PERM_DENY;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	hostgroupids;
	zbx_uint64_t		hostgroupid, triggerid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	triggerid = event->objectid;

	zbx_vector_uint64_create(&hostgroupids);
	zbx_vector_uint64_sort(&hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (USER_TYPE_SUPER_ADMIN == get_user_type(userid))
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	result = DBselect(
			"select distinct hg.groupid from items i"
			" join functions f on i.itemid=f.itemid"
			" join hosts_groups hg on hg.hostid = i.hostid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostgroupid, row[0]);
		zbx_vector_uint64_append(&hostgroupids, hostgroupid);
	}
	DBfree_result(result);

	if (PERM_DENY < (perm = get_hostgroups_permission(userid, &hostgroupids)) &&
			FAIL == check_tag_based_permission(userid, &hostgroupids, event))
	{
		perm = PERM_DENY;
	}
out:
	zbx_vector_uint64_destroy(&hostgroupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_permission_string(perm));

	return perm;
}

/******************************************************************************
 *                                                                            *
 * Function: get_item_permission                                              *
 *                                                                            *
 * Purpose: Return user permissions for access to item                        *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 ******************************************************************************/
int	get_item_permission(zbx_uint64_t userid, zbx_uint64_t itemid)
{
	const char		*__function_name = "get_item_permission";
	DB_RESULT		result;
	DB_ROW			row;
	int			perm = PERM_DENY;
	zbx_vector_uint64_t	hostgroupids;
	zbx_uint64_t		hostgroupid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&hostgroupids);
	zbx_vector_uint64_sort(&hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (USER_TYPE_SUPER_ADMIN == get_user_type(userid))
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	result = DBselect(
			"select hg.groupid from items i"
			" join hosts_groups hg on hg.hostid = i.hostid"
			" where i.utemid=" ZBX_FS_UI64,
			itemid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostgroupid, row[0]);
		zbx_vector_uint64_append(&hostgroupids, hostgroupid);
	}
	DBfree_result(result);

	perm = get_hostgroups_permission(userid, &hostgroupids);
out:
	zbx_vector_uint64_destroy(&hostgroupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_permission_string(perm));

	return perm;
}
