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

#include "escalator.h"

#include "../db_lengths.h"
#include "zbxnix.h"
#include "zbxserver.h"
#include "zbxself.h"
#include "../actions.h"
#include "../scripts/scripts.h"
#include "zbxcrypto.h"
#include "../../libs/zbxserver/get_host_from_event.h"
#include "../../libs/zbxserver/zabbix_users.h"
#include "zbxservice.h"
#include "zbxcomms.h"

extern int	CONFIG_ESCALATOR_FORKS;

#define CONFIG_ESCALATOR_FREQUENCY	3

#define ZBX_ESCALATION_SOURCE_DEFAULT	0
#define ZBX_ESCALATION_SOURCE_ITEM	1
#define ZBX_ESCALATION_SOURCE_TRIGGER	2
#define ZBX_ESCALATION_SOURCE_SERVICE	4

#define ZBX_ESCALATION_UNSET		-1
#define ZBX_ESCALATION_CANCEL		0
#define ZBX_ESCALATION_DELETE		1
#define ZBX_ESCALATION_SKIP		2
#define ZBX_ESCALATION_PROCESS		3
#define ZBX_ESCALATION_SUPPRESS		4

#define ZBX_ESCALATIONS_PER_STEP	1000

#define ZBX_ALERT_MESSAGE_ERR_NONE	0
#define ZBX_ALERT_MESSAGE_ERR_USR	1
#define ZBX_ALERT_MESSAGE_ERR_MSG	2

#define ZBX_ROLE_RULE_TYPE_INT		0
#define ZBX_ROLE_RULE_TYPE_STR		1
#define ZBX_ROLE_RULE_TYPE_SERVICEID	3

#define ZBX_SERVICES_RULE_PREFIX	"services."

typedef struct
{
	zbx_uint64_t	userid;
	zbx_uint64_t	mediatypeid;
	char		*subject;
	char		*message;
	char		*tz;
	int		err;
	void		*next;
}
ZBX_USER_MSG;

typedef struct
{
	zbx_uint64_t	hostgroupid;
	char		*tag;
	char		*value;
}
zbx_tag_filter_t;

typedef struct
{
	/* the role identifier */
	zbx_uint64_t		roleid;

	/* 0 if services.read is set to 0 and services.write is either 0 or absent. 1 otherwise. */
	unsigned char		global_read;

	/* the service identifiers listed by services.read.id.* and services.write.id.* */
	zbx_vector_uint64_t	serviceids;

	/* the service.read.tag.* and service.write.tag.* rules */
	zbx_vector_tags_t	tags;
}
zbx_service_role_t;

ZBX_VECTOR_DECL(service_alarm, zbx_service_alarm_t)
ZBX_VECTOR_IMPL(service_alarm, zbx_service_alarm_t)

typedef enum
{
	ZBX_VC_UPDATE_STATS,
	ZBX_VC_UPDATE_RANGE
}
zbx_vc_item_update_type_t;

static void	zbx_tag_filter_free(zbx_tag_filter_t *tag_filter)
{
	zbx_free(tag_filter->tag);
	zbx_free(tag_filter->value);
	zbx_free(tag_filter);
}

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

static void	add_message_alert(const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event, zbx_uint64_t actionid,
		int esc_step, zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *subject, const char *message,
		const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service,
		int err_type, const char *tz);

static int	get_user_info(zbx_uint64_t userid, zbx_uint64_t *roleid, char **user_timezone)
{
	int		user_type = -1;
	DB_RESULT	result;
	DB_ROW		row;

	*user_timezone = NULL;

	result = DBselect("select r.type,u.roleid,u.timezone from users u,role r where u.roleid=r.roleid and"
			" userid=" ZBX_FS_UI64, userid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
	{
		user_type = atoi(row[0]);
		ZBX_STR2UINT64(*roleid, row[1]);
		*user_timezone = zbx_strdup(NULL, row[2]);
	}

	DBfree_result(result);

	return user_type;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Return user permissions for access to the host                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_hostgroups_permission(zbx_uint64_t userid, zbx_vector_uint64_t *hostgroupids)
{
	int		perm = PERM_DENY;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_permission_string(perm));

	return perm;
}

/******************************************************************************
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
		const ZBX_DB_EVENT *event)
{
	char			*sql = NULL, hostgroupid[ZBX_MAX_UINT64_LEN + 1];
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			ret = FAIL, i;
	zbx_vector_ptr_t	tag_filters;
	zbx_tag_filter_t	*tag_filter;
	zbx_condition_t		condition;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&tag_filters);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select tf.groupid,tf.tag,tf.value from tag_filter tf"
			" join users_groups ug on ug.usrgrpid=tf.usrgrpid"
				" where ug.userid=" ZBX_FS_UI64, userid);
	result = DBselect("%s order by tf.groupid", sql);

	while (NULL != (row = DBfetch(result)))
	{
		tag_filter = (zbx_tag_filter_t *)zbx_malloc(NULL, sizeof(zbx_tag_filter_t));
		ZBX_STR2UINT64(tag_filter->hostgroupid, row[0]);
		tag_filter->tag = zbx_strdup(NULL, row[1]);
		tag_filter->value = zbx_strdup(NULL, row[2]);
		zbx_vector_ptr_append(&tag_filters, tag_filter);
	}
	zbx_free(sql);
	DBfree_result(result);

	if (0 < tag_filters.values_num)
		condition.op = CONDITION_OPERATOR_EQUAL;
	else
		ret = SUCCEED;

	for (i = 0; i < tag_filters.values_num && SUCCEED != ret; i++)
	{
		tag_filter = (zbx_tag_filter_t *)tag_filters.values[i];

		if (FAIL == zbx_vector_uint64_search(hostgroupids, tag_filter->hostgroupid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			continue;
		}

		if (NULL != tag_filter->tag && 0 != strlen(tag_filter->tag))
		{
			zbx_snprintf(hostgroupid, sizeof(hostgroupid), ZBX_FS_UI64, tag_filter->hostgroupid);

			if (NULL != tag_filter->value && 0 != strlen(tag_filter->value))
			{
				condition.conditiontype = CONDITION_TYPE_EVENT_TAG_VALUE;
				condition.value2 = tag_filter->tag;
				condition.value = tag_filter->value;
			}
			else
			{
				condition.conditiontype = CONDITION_TYPE_EVENT_TAG;
				condition.value = tag_filter->tag;
			}

			zbx_vector_uint64_create(&condition.eventids);
			ret = check_action_condition(event, &condition);
			zbx_vector_uint64_destroy(&condition.eventids);
		}
		else
			ret = SUCCEED;
	}
	zbx_vector_ptr_clear_ext(&tag_filters, (zbx_clean_func_t)zbx_tag_filter_free);
	zbx_vector_ptr_destroy(&tag_filters);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Return user permissions for access to trigger                     *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_trigger_permission(zbx_uint64_t userid, const ZBX_DB_EVENT *event, char **user_timezone)
{
	int			perm = PERM_DENY;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	hostgroupids;
	zbx_uint64_t		hostgroupid, roleid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (USER_TYPE_SUPER_ADMIN == get_user_info(userid, &roleid, user_timezone))
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	zbx_vector_uint64_create(&hostgroupids);

	result = DBselect(
			"select distinct hg.groupid from items i"
			" join functions f on i.itemid=f.itemid"
			" join hosts_groups hg on hg.hostid = i.hostid"
				" and f.triggerid=" ZBX_FS_UI64,
			event->objectid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostgroupid, row[0]);
		zbx_vector_uint64_append(&hostgroupids, hostgroupid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (PERM_DENY < (perm = get_hostgroups_permission(userid, &hostgroupids)) &&
			FAIL == check_tag_based_permission(userid, &hostgroupids, event))
	{
		perm = PERM_DENY;
	}

	zbx_vector_uint64_destroy(&hostgroupids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_permission_string(perm));

	return perm;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Return user permissions for access to item                        *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_item_permission(zbx_uint64_t userid, zbx_uint64_t itemid, char **user_timezone)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			perm = PERM_DENY;
	zbx_vector_uint64_t	hostgroupids;
	zbx_uint64_t		hostgroupid, roleid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostgroupids);
	zbx_vector_uint64_sort(&hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (USER_TYPE_SUPER_ADMIN == get_user_info(userid, &roleid, user_timezone))
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	result = DBselect(
			"select hg.groupid from items i"
			" join hosts_groups hg on hg.hostid=i.hostid"
			" where i.itemid=" ZBX_FS_UI64,
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_permission_string(perm));

	return perm;
}

static int	check_parent_service_intersection(zbx_vector_uint64_t *parent_ids, zbx_vector_uint64_t *role_ids)
{
	int	i;

	for (i = 0; i < parent_ids->values_num; i++)
	{
		if (SUCCEED == zbx_vector_uint64_bsearch(role_ids, parent_ids->values[i], ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			return PERM_READ;
	}

	return PERM_DENY;
}

static int	check_db_parent_rule_tag_match(zbx_vector_uint64_t *parent_ids, zbx_vector_tags_t *tags)
{
	DB_RESULT	result;
	char		*sql = NULL;
	int		i, perm = PERM_DENY;
	size_t		sql_alloc = 0, sql_offset = 0;

	if (0 == parent_ids->values_num || 0 == tags->values_num)
		return PERM_DENY;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select null from service_tag where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "serviceid", parent_ids->values,
			parent_ids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and (");

	for (i = 0; i < tags->values_num; i++)
	{
		zbx_tag_t	*tag = tags->values[i];
		char		*tag_esc;

		tag_esc = DBdyn_escape_string(tag->tag);

		if (i > 0)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " or ");

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "tag='%s'", tag_esc);

		if (NULL != tag->value)
		{
			char	*value_esc;

			value_esc = DBdyn_escape_string(tag->value);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and value='%s'", value_esc);
			zbx_free(value_esc);
		}

		zbx_free(tag_esc);
	}

	result = DBselect("%s) limit 1", sql);

	if (NULL != DBfetch(result))
	{
		perm = PERM_READ;
	}

	DBfree_result(result);
	zbx_free(sql);

	return perm;
}

static int	check_service_tags_rule_match(const zbx_vector_tags_t *service_tags, const zbx_vector_tags_t *role_tags)
{
	int	i, j;

	for (i = 0; i < role_tags->values_num; i++)
	{
		zbx_tag_t *role_tag = role_tags->values[i];

		for (j = 0; j < service_tags->values_num; j++)
		{
			zbx_tag_t *service_tag = service_tags->values[j];

			if (0 == strcmp(service_tag->tag, role_tag->tag))
			{
				if (NULL == role_tag->value || 0 == strcmp(service_tag->value, role_tag->value))
				{
					return PERM_READ;
				}
			}
		}
	}

	return PERM_DENY;
}

static void	zbx_db_cache_service_role(zbx_service_role_t *role)
{
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	services_read = 1, services_write = 0;

	result = DBselect("select name,roleid,value_int,value_str,value_serviceid,type from role_rule where roleid="
			ZBX_FS_UI64 " and name like 'services.%%' order by name", role->roleid);

	while (NULL != (row = DBfetch(result)))
	{
		int		type;
		char		*name;

		name = row[0] + ZBX_CONST_STRLEN(ZBX_SERVICES_RULE_PREFIX);
		type = atoi(row[5]);

		if (ZBX_ROLE_RULE_TYPE_INT == type) /* services.read or services.write */
		{
			char	*value_int = row[2];

			if (0 == strcmp("read", name))
				ZBX_STR2UCHAR(services_read, value_int);
			else if (0 == strcmp("write", name))
				ZBX_STR2UCHAR(services_write, value_int);
		}
		else if (ZBX_ROLE_RULE_TYPE_STR == type) /* services.read.tag.* / services.write.tag.* */
		{
			char		*value_str = row[3];
			zbx_tag_t	*tag;

			/* As the field 'name' is sorted, its 'tag.value' record always follows its corresponding */
			/* 'tag.name' record */
			if (0 == strcmp("read.tag.name", name) || 0 == strcmp("write.tag.name", name))
			{
				tag = (zbx_tag_t*)zbx_malloc(NULL, sizeof(zbx_tag_t));
				tag->tag = zbx_strdup(NULL, value_str);
				tag->value = NULL;
				zbx_vector_tags_append(&role->tags, tag);
			}
			else if (0 == strcmp("read.tag.value", name) || 0 == strcmp("write.tag.value", name))
			{
				if (role->tags.values_num == 0)
					continue;

				tag = role->tags.values[role->tags.values_num - 1];
				tag->value = zbx_strdup(NULL, value_str);
			}

		}
		else if (ZBX_ROLE_RULE_TYPE_SERVICEID == type) /* services.read.id.<idx> / services.write.id.<idx>*/
		{
			char		*value_serviceid = row[4];
			zbx_uint64_t	serviceid;

			if (SUCCEED == DBis_null(value_serviceid))
				continue;

			if (0 != strncmp(name, "read.id", ZBX_CONST_STRLEN("read.id")) &&
					0 != strncmp(name, "write.id", ZBX_CONST_STRLEN("write.id")))
			{
				continue;
			}

			ZBX_STR2UINT64(serviceid, value_serviceid);

			zbx_vector_uint64_append(&role->serviceids, serviceid);
		}
	}

	if (0 == services_read && 0 == services_write)
		role->global_read = 0;
	else
		role->global_read = 1;

	if (role->serviceids.values_num > 0)
	{
		zbx_vector_uint64_sort(&role->serviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&role->serviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Return user permissions for access to services                    *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_service_permission(zbx_uint64_t userid, char **user_timezone, const ZBX_DB_SERVICE *service,
		zbx_hashset_t *roles)
{
	int			perm = PERM_DENY;
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;
	zbx_user_t		user = {.userid = userid};
	zbx_ipc_message_t	response;
	zbx_vector_uint64_t	parent_ids;
	zbx_service_role_t	role_local, *role;

	user.type = get_user_info(userid, &user.roleid, user_timezone);

	role_local.roleid = user.roleid;

	if (NULL == (role = zbx_hashset_search(roles, &role_local)))
	{
		zbx_vector_uint64_create(&role_local.serviceids);
		zbx_vector_tags_create(&role_local.tags);
		zbx_db_cache_service_role(&role_local);
		role = zbx_hashset_insert(roles, &role_local, sizeof(role_local));
	}

	/* Check if global read rights are not disabled (services.read:0). */

	/* In this case individual role rules can be skipped.              */
	if (1 == role->global_read)
		return PERM_READ;

	/* check if the target service has read permission */

	/* check read/write rule rights */
	/* this function is called only when processing service event escalations, service will never hold NULL value */
	if (SUCCEED == zbx_vector_uint64_bsearch(&role->serviceids, service->serviceid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		return PERM_READ;

	/* check if service tags do not match tag rules */
	if (PERM_DENY < (perm = check_service_tags_rule_match(&service->service_tags, &role->tags)))
		return perm;

	/* check if any parent service has read permission */

	/* get service parent ids from service manager */
	zbx_service_serialize_id(&data, &data_alloc, &data_offset, service->serviceid);

	if (NULL == data)
		goto out2;

	zbx_ipc_message_init(&response);
	zbx_service_send(ZBX_IPC_SERVICE_SERVICE_PARENT_LIST, data, (zbx_uint32_t)data_offset, &response);
	zbx_vector_uint64_create(&parent_ids);
	zbx_service_deserialize_parentids(response.data, &parent_ids);
	zbx_ipc_message_clean(&response);

	/* check if the returned vector doesn't intersect rule serviceids vector */
	if (PERM_DENY < (perm = check_parent_service_intersection(&parent_ids, &role->serviceids)))
		goto out;

	if (PERM_DENY < (perm = check_db_parent_rule_tag_match(&parent_ids, &role->tags)))
		goto out;

out:
	zbx_vector_uint64_destroy(&parent_ids);
out2:
	zbx_free(data);

	return perm;
}

static void	add_user_msg(zbx_uint64_t userid, zbx_uint64_t mediatypeid, ZBX_USER_MSG **user_msg, const char *subj,
		const char *msg, zbx_uint64_t actionid, const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event,
		const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service,
		int expand_macros, int macro_type, int err_type, const char *tz)
{
	ZBX_USER_MSG	*p, **pnext;
	char		*subject, *message, *tz_tmp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	subject = zbx_strdup(NULL, subj);
	message = zbx_strdup(NULL, msg);
	tz_tmp = zbx_strdup(NULL, tz);

	if (MACRO_EXPAND_YES == expand_macros)
	{
		zbx_substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL, NULL, ack,
				service_alarm, service, tz, &subject, macro_type, NULL, 0);
		zbx_substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL, NULL, ack,
				service_alarm, service, tz, &message, macro_type, NULL, 0);
	}

	if (0 == mediatypeid)
	{
		for (pnext = user_msg, p = *user_msg; NULL != p; p = *pnext)
		{
			if (p->userid == userid && 0 == strcmp(p->subject, subject) && p->err == err_type &&
					0 == strcmp(p->message, message) && 0 != p->mediatypeid)
			{
				*pnext = (ZBX_USER_MSG *)p->next;

				zbx_free(p->subject);
				zbx_free(p->message);
				zbx_free(p->tz);
				zbx_free(p);
			}
			else
				pnext = (ZBX_USER_MSG **)&p->next;
		}
	}

	for (p = *user_msg; NULL != p; p = (ZBX_USER_MSG *)p->next)
	{
		if (p->userid == userid && 0 == strcmp(p->subject, subject) && p->err == err_type &&
				0 == strcmp(p->message, message) &&
				(0 == p->mediatypeid || mediatypeid == p->mediatypeid))
		{
			break;
		}
	}

	if (NULL == p)
	{
		p = (ZBX_USER_MSG *)zbx_malloc(p, sizeof(ZBX_USER_MSG));

		p->userid = userid;
		p->mediatypeid = mediatypeid;
		p->err = err_type;
		p->subject = subject;
		p->message = message;
		p->tz = tz_tmp;
		p->next = *user_msg;

		*user_msg = p;
	}
	else
	{
		zbx_free(subject);
		zbx_free(message);
		zbx_free(tz_tmp);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	add_user_msgs(zbx_uint64_t userid, zbx_uint64_t operationid, zbx_uint64_t mediatypeid,
		ZBX_USER_MSG **user_msg, zbx_uint64_t actionid, const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event,
		const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service,
		int macro_type, unsigned char evt_src, unsigned char op_mode, const char *default_timezone,
		const char *user_timezone)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	mtid;
	const char	*tz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == user_timezone || 0 == strcmp(user_timezone, ZBX_TIMEZONE_DEFAULT_VALUE))
		tz = default_timezone;
	else
		tz = user_timezone;

	result = DBselect(
			"select mediatypeid,default_msg,subject,message from opmessage where operationid=" ZBX_FS_UI64,
			operationid);

	if (NULL != (row = DBfetch(result)))
	{
		if (0 == mediatypeid)
		{
			ZBX_DBROW2UINT64(mediatypeid, row[0]);
		}

		if (1 != atoi(row[1]))
		{
			add_user_msg(userid, mediatypeid, user_msg, row[2], row[3], actionid, event, r_event, ack,
					service_alarm, service, MACRO_EXPAND_YES, macro_type,
					ZBX_ALERT_MESSAGE_ERR_NONE, tz);
			goto out;
		}

		DBfree_result(result);
	}
	else
		goto out;

	mtid = mediatypeid;

	if (0 != mediatypeid)
	{
		result = DBselect("select mediatype_messageid,subject,message,mediatypeid from media_type_message"
				" where eventsource=%d and recovery=%d and mediatypeid=" ZBX_FS_UI64,
				evt_src, op_mode, mediatypeid);

		mediatypeid = 0;
	}
	else
	{
		result = DBselect(
				"select mm.mediatype_messageid,mm.subject,mm.message,mt.mediatypeid from media_type mt"
				" left join (select mediatypeid,subject,message,mediatype_messageid"
				" from media_type_message where eventsource=%d and recovery=%d) mm"
				" on mt.mediatypeid=mm.mediatypeid"
				" join (select distinct mediatypeid from media where userid=" ZBX_FS_UI64 ") m"
				" on mt.mediatypeid=m.mediatypeid",
				evt_src, op_mode, userid);
	}

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	mtmid;

		ZBX_DBROW2UINT64(mtmid, row[0]);
		ZBX_STR2UINT64(mediatypeid, row[3]);

		if (0 != mtmid)
		{
			add_user_msg(userid, mediatypeid, user_msg, row[1], row[2], actionid, event, r_event, ack,
					service_alarm, service, MACRO_EXPAND_YES, macro_type, ZBX_ALERT_MESSAGE_ERR_NONE, tz);
		}
		else
		{
			add_user_msg(userid, mediatypeid, user_msg, "", "", actionid, event, r_event, ack,
					service_alarm, service, MACRO_EXPAND_NO, 0, ZBX_ALERT_MESSAGE_ERR_MSG, tz);
		}
	}

	if (0 == mediatypeid)
	{
		add_user_msg(userid, mtid, user_msg, "", "", actionid, event, r_event, ack, service_alarm, service,
				MACRO_EXPAND_NO, 0, 0 == mtid ? ZBX_ALERT_MESSAGE_ERR_USR : ZBX_ALERT_MESSAGE_ERR_MSG,
						tz);
	}

out:
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	add_object_msg(zbx_uint64_t actionid, zbx_uint64_t operationid, ZBX_USER_MSG **user_msg,
		const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event, const DB_ACKNOWLEDGE *ack,
		const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service, int macro_type,
		unsigned char evt_src, unsigned char op_mode, const char *default_timezone, zbx_hashset_t *roles)
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select userid"
			" from opmessage_usr"
			" where operationid=" ZBX_FS_UI64
			" union "
			"select g.userid"
			" from opmessage_grp m,users_groups g"
			" where m.usrgrpid=g.usrgrpid"
				" and m.operationid=" ZBX_FS_UI64,
			operationid, operationid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	userid;
		char		*user_timezone = NULL;

		ZBX_STR2UINT64(userid, row[0]);

		/* exclude acknowledgment author from the recipient list */
		if (NULL != ack && ack->userid == userid)
			continue;

		if (SUCCEED != check_perm2system(userid))
			continue;

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (PERM_READ > get_trigger_permission(userid, event, &user_timezone))
					goto clean;
				break;
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				if (PERM_READ > get_item_permission(userid, event->objectid, &user_timezone))
					goto clean;
				break;
			case EVENT_OBJECT_SERVICE:
				if (PERM_READ > get_service_permission(userid, &user_timezone, service, roles))
					goto clean;
				break;
			default:
				user_timezone = get_user_timezone(userid);
		}

		add_user_msgs(userid, operationid, 0, user_msg, actionid, event, r_event, ack, service_alarm, service,
				macro_type, evt_src, op_mode, default_timezone, user_timezone);
clean:
		zbx_free(user_timezone);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds message to be sent to all recipients of messages previously  *
 *          generated by action operations or acknowledgment operations,      *
 *          which is related with an event or recovery event                  *
 *                                                                            *
 * Parameters: user_msg    - [IN/OUT] the message list                        *
 *             actionid    - [IN] the action identifier                       *
 *             operationid - [IN] the operation identifier                    *
 *             event       - [IN] the event                                   *
 *             r_event     - [IN] the recover event (optional, can be NULL)   *
 *             ack         - [IN] (optional, can be NULL)                     *
 *             evt_src     - [IN] the action event source                     *
 *             op_mode     - [IN] the operation mode                          *
 *                                                                            *
 ******************************************************************************/
static void	add_sentusers_msg(ZBX_USER_MSG **user_msg, zbx_uint64_t actionid, zbx_uint64_t operationid,
		const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event, const DB_ACKNOWLEDGE *ack,
		const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service, unsigned char evt_src,
		unsigned char op_mode,
		const char *default_timezone, zbx_hashset_t *roles)
{
	char		*sql = NULL;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid, mediatypeid;
	int		message_type;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct userid,mediatypeid"
			" from alerts"
			" where actionid=" ZBX_FS_UI64
				" and mediatypeid is not null"
				" and alerttype=%d"
				" and acknowledgeid is null"
				" and (eventid=" ZBX_FS_UI64,
				actionid, ALERT_TYPE_MESSAGE, event->eventid);

	if (NULL != r_event)
	{
		message_type = MACRO_TYPE_MESSAGE_RECOVERY;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " or eventid=" ZBX_FS_UI64, r_event->eventid);
	}
	else
		message_type = MACRO_TYPE_MESSAGE_NORMAL;

	zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');

	if (NULL != ack)
		message_type = MACRO_TYPE_MESSAGE_UPDATE;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		char	*user_timezone = NULL;

		ZBX_DBROW2UINT64(userid, row[0]);

		/* exclude acknowledgment author from the recipient list */
		if (NULL != ack && ack->userid == userid)
			continue;

		if (SUCCEED != check_perm2system(userid))
			continue;

		ZBX_STR2UINT64(mediatypeid, row[1]);

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (PERM_READ > get_trigger_permission(userid, event, &user_timezone))
					goto clean;
				break;
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				if (PERM_READ > get_item_permission(userid, event->objectid, &user_timezone))
					goto clean;
				break;
			case EVENT_OBJECT_SERVICE:
				if (PERM_READ > get_service_permission(userid, &user_timezone, service, roles))
					goto clean;
				break;
			default:
				user_timezone = get_user_timezone(userid);
		}

		add_user_msgs(userid, operationid, mediatypeid, user_msg, actionid, event, r_event, ack, service_alarm,
				service, message_type, evt_src, op_mode, default_timezone, user_timezone);
clean:
		zbx_free(user_timezone);
	}
	DBfree_result(result);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds message for the canceled escalation to be sent to all        *
 *          recipients of messages previously generated by action operations  *
 *          or acknowledgment operations, which is related with an event or   *
 *          recovery event                                                    *
 *                                                                            *
 * Parameters: user_msg         - [IN/OUT] the message list                   *
 *             actionid         - [IN] the action identifier                  *
 *             event            - [IN] the event                              *
 *             error            - [IN] the error message                      *
 *             default_timezone - [IN] the default timezone                   *
 *                                                                            *
 ******************************************************************************/
static void	add_sentusers_msg_esc_cancel(ZBX_USER_MSG **user_msg, zbx_uint64_t actionid, const ZBX_DB_EVENT *event,
		const char *error, const char *default_timezone, const ZBX_DB_SERVICE *service, zbx_hashset_t *roles)
{
	char		*message_dyn, *sql = NULL;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid, mediatypeid, userid_prev = 0, mediatypeid_prev = 0;
	int		esc_step, esc_step_prev = 0;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select userid,mediatypeid,subject,message,esc_step"
			" from alerts"
			" where alertid in (select max(alertid)"
				" from alerts"
				" where actionid=" ZBX_FS_UI64
					" and mediatypeid is not null"
					" and alerttype=%d"
					" and acknowledgeid is null"
					" and eventid=" ZBX_FS_UI64
					" group by userid,mediatypeid,esc_step)"
			" order by userid,mediatypeid,esc_step desc",
			actionid, ALERT_TYPE_MESSAGE, event->eventid);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		char		*user_timezone = NULL;
		const char	*tz;

		ZBX_DBROW2UINT64(userid, row[0]);
		ZBX_STR2UINT64(mediatypeid, row[1]);
		esc_step = atoi(row[4]);

		if (userid == userid_prev && mediatypeid == mediatypeid_prev && esc_step < esc_step_prev)
			continue;

		userid_prev = userid;
		mediatypeid_prev = mediatypeid;
		esc_step_prev = esc_step;

		if (SUCCEED != check_perm2system(userid))
			continue;

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (PERM_READ > get_trigger_permission(userid, event, &user_timezone))
					goto clean;
				break;
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				if (PERM_READ > get_item_permission(userid, event->objectid, &user_timezone))
					goto clean;
				break;
			case EVENT_OBJECT_SERVICE:
				if (PERM_READ > get_service_permission(userid, &user_timezone, service, roles))
					goto clean;
				break;
			default:
				user_timezone = get_user_timezone(userid);
		}

		message_dyn = zbx_dsprintf(NULL, "NOTE: Escalation canceled: %s\nLast message sent:\n%s", error,
				row[3]);

		tz = NULL == user_timezone || 0 == strcmp(user_timezone, "default") ? default_timezone : user_timezone;

		add_user_msg(userid, mediatypeid, user_msg, row[2], message_dyn, actionid, event, NULL, NULL,
				NULL, NULL, MACRO_EXPAND_NO, 0, ZBX_ALERT_MESSAGE_ERR_NONE, tz);

		zbx_free(message_dyn);
clean:
		zbx_free(user_timezone);
	}
	DBfree_result(result);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds message to be sent to all who added acknowledgment and are   *
 *          involved in discussion                                            *
 *                                                                            *
 * Parameters: user_msg    - [IN/OUT] the message list                        *
 *             actionid    - [IN] the action identifier                       *
 *             operationid - [IN]                                             *
 *             event       - [IN]                                             *
 *             ack         - [IN]                                             *
 *             evt_src     - [IN] the action event source                     *
 *                                                                            *
 ******************************************************************************/
static void	add_sentusers_ack_msg(ZBX_USER_MSG **user_msg, zbx_uint64_t actionid, zbx_uint64_t operationid,
		const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event, const DB_ACKNOWLEDGE *ack, unsigned char evt_src,
		const char *default_timezone)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select distinct userid"
			" from acknowledges"
			" where eventid=" ZBX_FS_UI64,
			event->eventid);

	while (NULL != (row = DBfetch(result)))
	{
		char	*user_timezone = NULL;

		ZBX_DBROW2UINT64(userid, row[0]);

		/* exclude acknowledgment author from the recipient list */
		if (ack->userid == userid)
			continue;

		if (SUCCEED != check_perm2system(userid))
			continue;

		if (PERM_READ > get_trigger_permission(userid, event, &user_timezone))
			goto clean;

		add_user_msgs(userid, operationid, 0, user_msg, actionid, event, r_event, ack, NULL, NULL,
				MACRO_TYPE_MESSAGE_UPDATE, evt_src, ZBX_OPERATION_MODE_UPDATE, default_timezone,
				user_timezone);
clean:
		zbx_free(user_timezone);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	flush_user_msg(ZBX_USER_MSG **user_msg, int esc_step, const ZBX_DB_EVENT *event,
		const ZBX_DB_EVENT *r_event, zbx_uint64_t actionid, const DB_ACKNOWLEDGE *ack,
		const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service)
{
	ZBX_USER_MSG		*p;

	while (NULL != *user_msg)
	{
		p = *user_msg;
		*user_msg = (ZBX_USER_MSG *)(*user_msg)->next;

		add_message_alert(event, r_event, actionid, esc_step, p->userid, p->mediatypeid, p->subject,
					p->message, ack, service_alarm, service, p->err, p->tz);

		zbx_free(p->subject);
		zbx_free(p->message);
		zbx_free(p->tz);
		zbx_free(p);
	}
}

static void	add_command_alert(zbx_db_insert_t *db_insert, int alerts_num, zbx_uint64_t alertid, const char *host,
		const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event, zbx_uint64_t actionid, int esc_step,
		const char *message, zbx_alert_status_t status, const char *error)
{
	int	now, alerttype = ALERT_TYPE_COMMAND, alert_status = status;
	char	*tmp = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == alerts_num)
	{
		zbx_db_insert_prepare(db_insert, "alerts", "alertid", "actionid", "eventid", "clock", "message",
				"status", "error", "esc_step", "alerttype", (NULL != r_event ? "p_eventid" : NULL),
				NULL);
	}

	now = (int)time(NULL);

	tmp = zbx_dsprintf(tmp, "%s:%s", host, message);

	if (NULL == r_event)
	{
		zbx_db_insert_add_values(db_insert, alertid, actionid, event->eventid, now, tmp, alert_status,
				error, esc_step, (int)alerttype);
	}
	else
	{
		zbx_db_insert_add_values(db_insert, alertid, actionid, r_event->eventid, now, tmp, alert_status,
				error, esc_step, (int)alerttype, event->eventid);
	}

	zbx_free(tmp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get groups (including nested groups) used by an operation         *
 *                                                                            *
 * Parameters: operationid - [IN] the operation id                            *
 *             groupids    - [OUT] the group ids                              *
 *                                                                            *
 ******************************************************************************/
static void	get_operation_groupids(zbx_uint64_t operationid, zbx_vector_uint64_t *groupids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	parent_groupids;

	zbx_vector_uint64_create(&parent_groupids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select groupid from opcommand_grp where operationid=" ZBX_FS_UI64, operationid);

	DBselect_uint64(sql, &parent_groupids);

	zbx_dc_get_nested_hostgroupids(parent_groupids.values, parent_groupids.values_num, groupids);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&parent_groupids);
}

#ifdef HAVE_OPENIPMI
#	define ZBX_IPMI_FIELDS_NUM	4	/* number of selected IPMI-related fields in function */
						/* execute_commands() */
#else
#	define ZBX_IPMI_FIELDS_NUM	0
#endif

static void	execute_commands(const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event, const DB_ACKNOWLEDGE *ack,
		const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service, zbx_uint64_t actionid,
		zbx_uint64_t operationid, int esc_step, int macro_type, const char *default_timezone)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_db_insert_t		db_insert;
	int			alerts_num = 0;
	char			*buffer = NULL;
	size_t			buffer_alloc = 2 * ZBX_KIBIBYTE, buffer_offset = 0;
	zbx_vector_uint64_t	executed_on_hosts, groupids;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	buffer = (char *)zbx_malloc(buffer, buffer_alloc);

	/* get hosts operation's hosts */

	zbx_vector_uint64_create(&groupids);
	get_operation_groupids(operationid, &groupids);

	if (0 != groupids.values_num)
	{
		zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
				/* the 1st 'select' works if remote command target is "Host group" */
				"select h.hostid,h.proxy_hostid,h.host,s.type,s.scriptid,s.execute_on,s.port"
					",s.authtype,s.username,s.password,s.publickey,s.privatekey,s.command,s.groupid"
					",s.scope,s.timeout,s.name,h.tls_connect"
#ifdef HAVE_OPENIPMI
				/* do not forget to update ZBX_IPMI_FIELDS_NUM if number of selected IPMI fields changes */
				",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				",h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk"
#endif
				);

		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
				" from opcommand o,hosts_groups hg,hosts h,scripts s"
				" where o.operationid=" ZBX_FS_UI64
					" and o.scriptid=s.scriptid"
					" and hg.hostid=h.hostid"
					" and h.status=%d"
					" and",
				operationid, HOST_STATUS_MONITORED);

		DBadd_condition_alloc(&buffer, &buffer_alloc, &buffer_offset, "hg.groupid", groupids.values,
				groupids.values_num);

		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, " union all ");
	}

	zbx_vector_uint64_destroy(&groupids);

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
			/* the 2nd 'select' works if remote command target is "Host" */
			"select h.hostid,h.proxy_hostid,h.host,s.type,s.scriptid,s.execute_on,s.port"
				",s.authtype,s.username,s.password,s.publickey,s.privatekey,s.command,s.groupid"
				",s.scope,s.timeout,s.name,h.tls_connect"
#ifdef HAVE_OPENIPMI
			",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			",h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk"
#endif
			);
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
			" from opcommand o,opcommand_hst oh,hosts h,scripts s"
			" where o.operationid=oh.operationid"
				" and o.scriptid=s.scriptid"
				" and oh.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union all "
			/* the 3rd 'select' works if remote command target is "Current host" */
			"select 0,0,null,s.type,s.scriptid,s.execute_on,s.port"
				",s.authtype,s.username,s.password,s.publickey,s.privatekey,s.command,s.groupid"
				",s.scope,s.timeout,s.name,%d",
			operationid, HOST_STATUS_MONITORED, ZBX_TCP_SEC_UNENCRYPTED);
#ifdef HAVE_OPENIPMI
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
				",0,2,null,null");
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
				",null,null,null,null");
#endif
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
			" from opcommand o"
			" join scripts s"
				" on o.scriptid=s.scriptid"
			" left join opcommand_hst oh"
				" on o.operationid=oh.operationid"
			" where  o.operationid=" ZBX_FS_UI64
				" and oh.hostid is null",
			operationid);

	result = DBselect("%s", buffer);

	zbx_free(buffer);
	zbx_vector_uint64_create(&executed_on_hosts);

	um_handle = zbx_dc_open_user_macros();

	while (NULL != (row = DBfetch(result)))
	{
		int			scope, i, rc = SUCCEED;
		DC_HOST			host;
		zbx_script_t		script;
		zbx_alert_status_t	status = ALERT_STATUS_NOT_SENT;
		zbx_uint64_t		alertid, groupid;
		char			*webhook_params_json = NULL, *script_name = NULL;
		zbx_vector_ptr_pair_t	webhook_params;
		char			error[ALERT_ERROR_LEN_MAX];

		*error = '\0';
		memset(&host, 0, sizeof(host));
		zbx_script_init(&script);
		zbx_vector_ptr_pair_create(&webhook_params);

		/* fill 'script' elements */

		ZBX_STR2UCHAR(script.type, row[3]);

		if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT == script.type)
			ZBX_STR2UCHAR(script.execute_on, row[5]);

		if (ZBX_SCRIPT_TYPE_SSH == script.type)
		{
			ZBX_STR2UCHAR(script.authtype, row[7]);
			script.publickey = zbx_strdup(script.publickey, row[10]);
			script.privatekey = zbx_strdup(script.privatekey, row[11]);
		}

		if (ZBX_SCRIPT_TYPE_SSH == script.type || ZBX_SCRIPT_TYPE_TELNET == script.type)
		{
			script.port = zbx_strdup(script.port, row[6]);
			script.username = zbx_strdup(script.username, row[8]);
			script.password = zbx_strdup(script.password, row[9]);
		}

		script.command = zbx_strdup(script.command, row[12]);
		script.command_orig = zbx_strdup(script.command_orig, row[12]);

		ZBX_DBROW2UINT64(script.scriptid, row[4]);

		if (SUCCEED != is_time_suffix(row[15], &script.timeout, ZBX_LENGTH_UNLIMITED))
		{
			zbx_strlcpy(error, "Invalid timeout value in script configuration.", sizeof(error));
			rc = FAIL;
			goto fail;
		}

		script_name = row[16];

		/* validate script permissions */

		scope = atoi(row[14]);
		ZBX_DBROW2UINT64(groupid, row[13]);

		ZBX_STR2UINT64(host.hostid, row[0]);
		ZBX_DBROW2UINT64(host.proxy_hostid, row[1]);

		if (ZBX_SCRIPT_SCOPE_ACTION != scope)
		{
			zbx_snprintf(error, sizeof(error), "Script is not allowed in action operations: scope:%d",
					scope);
			rc = FAIL;
			goto fail;
		}

		if (EVENT_SOURCE_SERVICE == event->source)
		{
			/* service event cannot have target, force execution on Zabbix server */
			script.execute_on = ZBX_SCRIPT_EXECUTE_ON_SERVER;
			strscpy(host.host, "Zabbix server");
		}
		else
		{
			/* get host details */

			if (0 == host.hostid)
			{
				/* target is "Current host" */
				if (SUCCEED != (rc = get_host_from_event((NULL != r_event ? r_event : event), &host, error,
						sizeof(error))))
				{
					goto fail;
				}
			}

			if (FAIL != zbx_vector_uint64_search(&executed_on_hosts, host.hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				goto skip;

			zbx_vector_uint64_append(&executed_on_hosts, host.hostid);

			if (0 < groupid && SUCCEED != zbx_check_script_permissions(groupid, host.hostid))
			{
				zbx_strlcpy(error, "Script does not have permission to be executed on the host.",
						sizeof(error));
				rc = FAIL;
				goto fail;
			}


			if ('\0' == *host.host)
			{
				/* target is from "Host" list or "Host group" list */

				strscpy(host.host, row[2]);
				host.tls_connect = (unsigned char)atoi(row[17]);
#ifdef HAVE_OPENIPMI
				host.ipmi_authtype = (signed char)atoi(row[18]);
				host.ipmi_privilege = (unsigned char)atoi(row[19]);
				strscpy(host.ipmi_username, row[20]);
				strscpy(host.ipmi_password, row[21]);
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				strscpy(host.tls_issuer, row[18 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_subject, row[19 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_psk_identity, row[20 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_psk, row[21 + ZBX_IPMI_FIELDS_NUM]);
#endif
			}
		}

		/* substitute macros in script body and webhook parameters */

		if (ZBX_SCRIPT_TYPE_WEBHOOK != script.type)
		{
			if (SUCCEED != zbx_substitute_simple_macros_unmasked(&actionid, event, r_event, NULL, NULL, &host,
					NULL, NULL, ack, service_alarm, service, default_timezone, &script.command,
					macro_type, error,
					sizeof(error)))
			{
				rc = FAIL;
				goto fail;
			}

			/* expand macros in command_orig used for non-secure logging */
			if (SUCCEED != zbx_substitute_simple_macros(&actionid, event, r_event, NULL, NULL, &host,
					NULL, NULL, ack, service_alarm, service, default_timezone, &script.command_orig,
					macro_type, error, sizeof(error)))
			{
				/* script command_orig is a copy of script command - if the script command  */
				/* macro substitution succeeded, then it will succeed also for command_orig */
				THIS_SHOULD_NEVER_HAPPEN;
				rc = FAIL;
				goto fail;
			}
		}
		else
		{
			if (SUCCEED != DBfetch_webhook_params(script.scriptid, &webhook_params, error, sizeof(error)))
			{
				rc = FAIL;
				goto fail;
			}

			for (i = 0; i < webhook_params.values_num; i++)
			{
				if (SUCCEED != zbx_substitute_simple_macros_unmasked(&actionid, event, r_event, NULL,
						NULL, &host, NULL, NULL, ack, service_alarm, service, default_timezone,
						(char **)&webhook_params.values[i].second, macro_type, error,
						sizeof(error)))
				{
					rc = FAIL;
					goto fail;
				}
			}

			zbx_webhook_params_pack_json(&webhook_params, &webhook_params_json);
		}
fail:
		alertid = DBget_maxid("alerts");

		if (SUCCEED == rc)
		{
			if (SUCCEED == (rc = zbx_script_prepare(&script, &host.hostid, error, sizeof(error))))
			{
				if (0 == host.proxy_hostid || ZBX_SCRIPT_EXECUTE_ON_SERVER == script.execute_on ||
						ZBX_SCRIPT_TYPE_WEBHOOK == script.type)
				{
					rc = zbx_script_execute(&script, &host, webhook_params_json, NULL, error,
							sizeof(error), NULL);
					status = ALERT_STATUS_SENT;
				}
				else
				{
					if (0 == zbx_script_create_task(&script, &host, alertid, time(NULL)))
						rc = FAIL;
				}
			}
		}

		if (SUCCEED != rc)
			status = ALERT_STATUS_FAILED;

		add_command_alert(&db_insert, alerts_num++, alertid, host.host, event, r_event, actionid,
				esc_step, (ZBX_SCRIPT_TYPE_WEBHOOK == script.type) ? script_name : script.command_orig,
				status, error);
skip:
		zbx_free(webhook_params_json);

		for (i = 0; i < webhook_params.values_num; i++)
		{
			zbx_free(webhook_params.values[i].first);
			zbx_free(webhook_params.values[i].second);
		}

		zbx_vector_ptr_pair_destroy(&webhook_params);
		zbx_script_clean(&script);
	}
	DBfree_result(result);
	zbx_vector_uint64_destroy(&executed_on_hosts);

	zbx_dc_close_user_macros(um_handle);

	if (0 < alerts_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

#undef ZBX_IPMI_FIELDS_NUM

static void	get_mediatype_params(const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event, zbx_uint64_t actionid,
		zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *sendto, const char *subject,
		const char *message, const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm,
		const ZBX_DB_SERVICE *service, char **params, const char *tz)
{
	DB_RESULT		result;
	DB_ROW			row;
	DB_ALERT		alert = {.sendto = (char *)sendto,
					.subject = (char *)(uintptr_t)subject,
					.message = (char *)(uintptr_t)message
				};

	struct zbx_json		json;
	char			*name, *value;
	int			message_type;
	zbx_dc_um_handle_t	*um_handle;

	if (NULL != ack)
		message_type = MACRO_TYPE_MESSAGE_UPDATE;
	else
		message_type = (NULL != r_event ? MACRO_TYPE_MESSAGE_RECOVERY : MACRO_TYPE_MESSAGE_NORMAL);

	zbx_json_init(&json, 1024);

	um_handle = zbx_dc_open_user_macros();

	result = DBselect("select name,value from media_type_param where mediatypeid=" ZBX_FS_UI64, mediatypeid);
	while (NULL != (row = DBfetch(result)))
	{
		name = zbx_strdup(NULL, row[0]);
		value = zbx_strdup(NULL, row[1]);

		zbx_substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL, &alert,
				ack, service_alarm, service, tz, &name, message_type, NULL, 0);
		zbx_substitute_simple_macros_unmasked(&actionid, event, r_event, &userid, NULL, NULL, NULL, &alert,
				ack, service_alarm, service, tz, &value, message_type, NULL, 0);

		zbx_json_addstring(&json, name, value, ZBX_JSON_TYPE_STRING);
		zbx_free(name);
		zbx_free(value);

	}
	DBfree_result(result);

	zbx_dc_close_user_macros(um_handle);

	*params = zbx_strdup(NULL, json.buffer);
	zbx_json_free(&json);
}

static void	add_message_alert(const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event, zbx_uint64_t actionid,
		int esc_step, zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *subject, const char *message,
		const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service,
		int err_type, const char *tz)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		now, priority, have_alerts = 0, res;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	ackid;
	const char	*error;
	char		*period = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);
	ackid = (NULL == ack ? 0 : ack->acknowledgeid);

	if (ZBX_ALERT_MESSAGE_ERR_USR == err_type)
		goto err_alert;

	if (0 == mediatypeid)
	{
		result = DBselect(
				"select m.mediatypeid,m.sendto,m.severity,m.period,mt.status,m.active"
				" from media m,media_type mt"
				" where m.mediatypeid=mt.mediatypeid"
					" and m.userid=" ZBX_FS_UI64,
				userid);
	}
	else
	{
		result = DBselect(
				"select m.mediatypeid,m.sendto,m.severity,m.period,mt.status,m.active"
				" from media m,media_type mt"
				" where m.mediatypeid=mt.mediatypeid"
					" and m.userid=" ZBX_FS_UI64
					" and m.mediatypeid=" ZBX_FS_UI64,
				userid, mediatypeid);
	}

	mediatypeid = 0;
	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		priority = event->trigger.priority;
	}
	else if (EVENT_SOURCE_SERVICE == event->source)
	{
		priority = NULL == service_alarm ? event->severity : service_alarm->value;
	}
	else
		priority = TRIGGER_SEVERITY_NOT_CLASSIFIED;

	while (NULL != (row = DBfetch(result)))
	{
		int		severity, status;
		const char	*perror;
		char		*params;

		ZBX_STR2UINT64(mediatypeid, row[0]);
		severity = atoi(row[2]);
		period = zbx_strdup(period, row[3]);
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&period, MACRO_TYPE_COMMON, NULL, 0);

		zabbix_log(LOG_LEVEL_DEBUG, "severity:%d, media severity:%d, period:'%s', userid:" ZBX_FS_UI64,
				priority, severity, period, userid);

		if (MEDIA_STATUS_DISABLED == atoi(row[5]))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "will not send message (user media disabled)");
			continue;
		}

		if (((1 << priority) & severity) == 0)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "will not send message (severity)");
			continue;
		}

		if (SUCCEED != zbx_check_time_period(period, time(NULL), tz, &res))
		{
			status = ALERT_STATUS_FAILED;
			perror = "Invalid media activity period";
		}
		else if (SUCCEED != res)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "will not send message (period)");
			continue;
		}
		else if (MEDIA_TYPE_STATUS_DISABLED == atoi(row[4]))
		{
			status = ALERT_STATUS_FAILED;
			perror = "Media type disabled.";
		}
		else if (ZBX_ALERT_MESSAGE_ERR_MSG == err_type)
		{
			status = ALERT_STATUS_FAILED;
			perror = "No message defined for media type.";
		}
		else
		{
			status = ALERT_STATUS_NEW;
			perror = "";
		}

		if (0 == have_alerts)
		{
			have_alerts = 1;
			zbx_db_insert_prepare(&db_insert, "alerts", "alertid", "actionid", "eventid", "userid",
					"clock", "mediatypeid", "sendto", "subject", "message", "status", "error",
					"esc_step", "alerttype", "acknowledgeid", "parameters",
					(NULL != r_event ? "p_eventid" : NULL), NULL);
		}

		get_mediatype_params(event, r_event, actionid, userid, mediatypeid, row[1], subject, message, ack,
				service_alarm, service, &params, tz);

		if (NULL != r_event)
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), actionid, r_event->eventid, userid,
					now, mediatypeid, row[1], subject, message, status, perror, esc_step,
					(int)ALERT_TYPE_MESSAGE, ackid, params, event->eventid);
		}
		else
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), actionid, event->eventid, userid,
					now, mediatypeid, row[1], subject, message, status, perror, esc_step,
					(int)ALERT_TYPE_MESSAGE, ackid, params);
		}

		zbx_free(params);
	}

	zbx_free(period);

	DBfree_result(result);

	if (0 == mediatypeid)
	{
err_alert:
		have_alerts = 1;
		error = "No media defined for user.";

		zbx_db_insert_prepare(&db_insert, "alerts", "alertid", "actionid", "eventid", "userid", "clock",
				"subject", "message", "status", "retries", "error", "esc_step", "alerttype",
				"acknowledgeid", (NULL != r_event ? "p_eventid" : NULL), NULL);

		if (NULL != r_event)
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), actionid, r_event->eventid, userid,
					now, subject, message, (int)ALERT_STATUS_FAILED, (int)ALERT_MAX_RETRIES, error,
					esc_step, (int)ALERT_TYPE_MESSAGE, ackid, event->eventid);
		}
		else
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), actionid, event->eventid, userid,
					now, subject, message, (int)ALERT_STATUS_FAILED, (int)ALERT_MAX_RETRIES, error,
					esc_step, (int)ALERT_TYPE_MESSAGE, ackid);
		}
	}

	if (0 != have_alerts)
	{
		zbx_db_insert_autoincrement(&db_insert, "alertid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters: event    - event to check                                      *
 *             actionid - action ID for matching                              *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static int	check_operation_conditions(const ZBX_DB_EVENT *event, zbx_uint64_t operationid, unsigned char evaltype)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_condition_t	condition;

	int		ret = SUCCEED; /* SUCCEED required for CONDITION_EVAL_TYPE_AND_OR */
	int		cond, exit = 0;
	unsigned char	old_type = 0xff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, operationid);

	/* events with service events source can't have operation conditions */
	if (EVENT_SOURCE_SERVICE == event->source)
		goto succeed;

	result = DBselect("select conditiontype,operator,value"
				" from opconditions"
				" where operationid=" ZBX_FS_UI64
				" order by conditiontype",
			operationid);

	while (NULL != (row = DBfetch(result)) && 0 == exit)
	{
		memset(&condition, 0, sizeof(condition));
		condition.conditiontype	= (unsigned char)atoi(row[0]);
		condition.op = (unsigned char)atoi(row[1]);
		condition.value = row[2];
		zbx_vector_uint64_create(&condition.eventids);

		switch (evaltype)
		{
			case CONDITION_EVAL_TYPE_AND_OR:
				if (old_type == condition.conditiontype)	/* OR conditions */
				{
					if (SUCCEED == check_action_condition(event, &condition))
						ret = SUCCEED;
				}
				else						/* AND conditions */
				{
					/* Break if PREVIOUS AND condition is FALSE */
					if (ret == FAIL)
						exit = 1;
					else if (FAIL == check_action_condition(event, &condition))
						ret = FAIL;
				}
				old_type = condition.conditiontype;
				break;
			case CONDITION_EVAL_TYPE_AND:
				cond = check_action_condition(event, &condition);
				/* Break if any of AND conditions is FALSE */
				if (cond == FAIL)
				{
					ret = FAIL;
					exit = 1;
				}
				else
					ret = SUCCEED;
				break;
			case CONDITION_EVAL_TYPE_OR:
				cond = check_action_condition(event, &condition);
				/* Break if any of OR conditions is TRUE */
				if (cond == SUCCEED)
				{
					ret = SUCCEED;
					exit = 1;
				}
				else
					ret = FAIL;
				break;
			default:
				ret = FAIL;
				exit = 1;
				break;
		}

		zbx_vector_uint64_destroy(&condition.eventids);
	}
	DBfree_result(result);
succeed:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	escalation_execute_operations(DB_ESCALATION *escalation, const ZBX_DB_EVENT *event,
		const DB_ACTION *action, const ZBX_DB_SERVICE *service, const char *default_timezone,
		zbx_hashset_t *roles)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		next_esc_period = 0, esc_period, default_esc_period;
	ZBX_USER_MSG	*user_msg = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	default_esc_period = 0 == action->esc_period ? SEC_PER_HOUR : action->esc_period;
	escalation->esc_step++;

	result = DBselect(
			"select o.operationid,o.operationtype,o.esc_period,o.evaltype"
			" from operations o"
			" where o.actionid=" ZBX_FS_UI64
				" and o.operationtype in (%d,%d)"
				" and o.esc_step_from<=%d"
				" and (o.esc_step_to=0 or o.esc_step_to>=%d)"
				" and o.recovery=%d",
			action->actionid,
			OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND,
			escalation->esc_step,
			escalation->esc_step,
			ZBX_OPERATION_MODE_NORMAL);

	while (NULL != (row = DBfetch(result)))
	{
		char		*tmp;
		zbx_uint64_t	operationid;

		ZBX_STR2UINT64(operationid, row[0]);

		tmp = zbx_strdup(NULL, row[2]);
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &tmp,
				MACRO_TYPE_COMMON, NULL, 0);
		if (SUCCEED != is_time_suffix(tmp, &esc_period, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Invalid step duration \"%s\" for operation of action \"%s\","
					" using default operation step duration of the action", tmp, action->name);
			esc_period = 0;
		}
		zbx_free(tmp);

		if (0 == esc_period)
			esc_period = default_esc_period;

		if (0 == next_esc_period || next_esc_period > esc_period)
			next_esc_period = esc_period;

		if (SUCCEED == check_operation_conditions(event, operationid, (unsigned char)atoi(row[3])))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions match our event. Execute operation.");

			switch (atoi(row[1]))
			{
				case OPERATION_TYPE_MESSAGE:
					add_object_msg(action->actionid, operationid, &user_msg, event, NULL, NULL,
							NULL, service, MACRO_TYPE_MESSAGE_NORMAL, action->eventsource,
							ZBX_OPERATION_MODE_NORMAL, default_timezone, roles);
					break;
				case OPERATION_TYPE_COMMAND:
					execute_commands(event, NULL, NULL, NULL, service, action->actionid, operationid,
							escalation->esc_step, MACRO_TYPE_MESSAGE_NORMAL,
							default_timezone);
					break;
			}
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions do not match our event. Do not execute operation.");
	}
	DBfree_result(result);

	flush_user_msg(&user_msg, escalation->esc_step, event, NULL, action->actionid, NULL, NULL, service);

	if (EVENT_SOURCE_TRIGGERS == action->eventsource || EVENT_SOURCE_INTERNAL == action->eventsource ||
			EVENT_SOURCE_SERVICE == action->eventsource)
	{
		char	*sql;

		sql = zbx_dsprintf(NULL,
				"select null"
				" from operations"
				" where actionid=" ZBX_FS_UI64
					" and (esc_step_to>%d or esc_step_to=0)"
					" and recovery=%d",
					action->actionid, escalation->esc_step, ZBX_OPERATION_MODE_NORMAL);
		result = DBselectN(sql, 1);

		if (NULL != DBfetch(result))
		{
			next_esc_period = (0 != next_esc_period) ? next_esc_period : default_esc_period;
			escalation->nextcheck = time(NULL) + next_esc_period;
		}
		else if (ZBX_ACTION_RECOVERY_OPERATIONS == action->recovery)
		{
			escalation->status = ESCALATION_STATUS_SLEEP;
			escalation->nextcheck = time(NULL) + default_esc_period;
		}
		else
			escalation->status = ESCALATION_STATUS_COMPLETED;

		DBfree_result(result);
		zbx_free(sql);
	}
	else
		escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute escalation recovery operations                            *
 *                                                                            *
 * Parameters: event      - [IN] the event                                    *
 *             r_event    - [IN] the recovery event                           *
 *             action     - [IN] the action                                   *
 *                                                                            *
 * Comments: Action recovery operations have a single escalation step, so     *
 *           alerts created by escalation recovery operations must have       *
 *           esc_step field set to 1.                                         *
 *                                                                            *
 ******************************************************************************/
static void	escalation_execute_recovery_operations(const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event,
		const DB_ACTION *action, const ZBX_DB_SERVICE *service, const char *default_timezone,
		zbx_hashset_t *roles)
{
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_USER_MSG	*user_msg = NULL;
	zbx_uint64_t	operationid;
	unsigned char	operationtype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select o.operationid,o.operationtype"
			" from operations o"
			" where o.actionid=" ZBX_FS_UI64
				" and o.operationtype in (%d,%d,%d)"
				" and o.recovery=%d",
			action->actionid,
			OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, OPERATION_TYPE_RECOVERY_MESSAGE,
			ZBX_OPERATION_MODE_RECOVERY);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(operationid, row[0]);
		operationtype = (unsigned char)atoi(row[1]);

		switch (operationtype)
		{
			case OPERATION_TYPE_MESSAGE:
				add_object_msg(action->actionid, operationid, &user_msg, event, r_event, NULL, NULL,
						service, MACRO_TYPE_MESSAGE_RECOVERY, action->eventsource,
						ZBX_OPERATION_MODE_RECOVERY, default_timezone, roles);
				break;
			case OPERATION_TYPE_RECOVERY_MESSAGE:
				add_sentusers_msg(&user_msg, action->actionid, operationid, event, r_event, NULL, NULL,
						service, action->eventsource, ZBX_OPERATION_MODE_RECOVERY,
						default_timezone, roles);
				break;
			case OPERATION_TYPE_COMMAND:
				execute_commands(event, r_event, NULL, NULL, service, action->actionid, operationid, 1,
						MACRO_TYPE_MESSAGE_RECOVERY, default_timezone);
				break;
		}
	}
	DBfree_result(result);

	flush_user_msg(&user_msg, 1, event, r_event, action->actionid, NULL, NULL, service);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute escalation update operations                              *
 *                                                                            *
 * Parameters: event  - [IN] the event                                        *
 *             action - [IN] the action                                       *
 *             ack    - [IN] the acknowledgment                               *
 *                                                                            *
 * Comments: Action update operations have a single escalation step, so       *
 *           alerts created by escalation update operations must have         *
 *           esc_step field set to 1.                                         *
 *                                                                            *
 ******************************************************************************/
static void	escalation_execute_update_operations(const ZBX_DB_EVENT *event, const ZBX_DB_EVENT *r_event,
		const DB_ACTION *action, const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm,
		const ZBX_DB_SERVICE *service, const char *default_timezone, zbx_hashset_t *roles)
{
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_USER_MSG	*user_msg = NULL;
	zbx_uint64_t	operationid;
	unsigned char	operationtype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select o.operationid,o.operationtype"
			" from operations o"
			" where o.actionid=" ZBX_FS_UI64
				" and o.operationtype in (%d,%d,%d)"
				" and o.recovery=%d",
			action->actionid,
			OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, OPERATION_TYPE_UPDATE_MESSAGE,
			ZBX_OPERATION_MODE_UPDATE);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(operationid, row[0]);
		operationtype = (unsigned char)atoi(row[1]);

		switch (operationtype)
		{
			case OPERATION_TYPE_MESSAGE:
				add_object_msg(action->actionid, operationid, &user_msg, event, r_event, ack,
						service_alarm, service, MACRO_TYPE_MESSAGE_UPDATE, action->eventsource,
						ZBX_OPERATION_MODE_UPDATE, default_timezone, roles);
				break;
			case OPERATION_TYPE_UPDATE_MESSAGE:
				add_sentusers_msg(&user_msg, action->actionid, operationid, event, r_event, ack,
						service_alarm, service, action->eventsource, ZBX_OPERATION_MODE_UPDATE,
						default_timezone, roles);
				if (NULL != ack)
				{
					add_sentusers_ack_msg(&user_msg, action->actionid, operationid, event, r_event,
							ack, action->eventsource, default_timezone);
				}
				break;
			case OPERATION_TYPE_COMMAND:
				execute_commands(event, r_event, ack, service_alarm, service, action->actionid,
						operationid, 1, MACRO_TYPE_MESSAGE_UPDATE, default_timezone);
				break;
		}
	}
	DBfree_result(result);

	flush_user_msg(&user_msg, 1, event, r_event, action->actionid, ack, service_alarm, service);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether the escalation trigger and related items, hosts are *
 *          not deleted or disabled.                                          *
 *                                                                            *
 * Parameters: triggerid   - [IN] the id of trigger to check                  *
 *             source      - [IN] the escalation event source                 *
 *             ignore      - [OUT] 1 - the escalation must be ignored because *
 *                                     of dependent trigger being in PROBLEM  *
 *                                     state,                                 *
 *                                 0 - otherwise                              *
 *             error       - [OUT] message in case escalation is cancelled    *
 *                                                                            *
 * Return value: FAIL if dependent trigger is in PROBLEM state                *
 *               SUCCEED otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	check_escalation_trigger(zbx_uint64_t triggerid, unsigned char source, unsigned char *ignore,
		char **error)
{
	DC_TRIGGER		trigger;
	zbx_vector_uint64_t	functionids, itemids;
	DC_ITEM			*items = NULL;
	DC_FUNCTION		*functions = NULL;
	int			i, errcode, *errcodes = NULL, ret = FAIL;

	/* trigger disabled or deleted? */
	DCconfig_get_triggers_by_triggerids(&trigger, &triggerid, &errcode, 1);

	if (SUCCEED != errcode)
	{
		goto out;
	}
	else if (TRIGGER_STATUS_DISABLED == trigger.status)
	{
		*error = zbx_dsprintf(*error, "trigger \"%s\" disabled.", trigger.description);
		goto out;
	}

	if (EVENT_SOURCE_TRIGGERS != source)
	{
		/* don't check dependency for internal trigger events */
		ret = SUCCEED;
		goto out;
	}

	/* check items and hosts referenced by trigger expression */
	zbx_vector_uint64_create(&functionids);
	zbx_vector_uint64_create(&itemids);

	zbx_get_serialized_expression_functionids(trigger.expression, trigger.expression_bin, &functionids);
	if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == trigger.recovery_mode)
	{
		zbx_get_serialized_expression_functionids(trigger.recovery_expression, trigger.recovery_expression_bin,
				&functionids);
	}

	functions = (DC_FUNCTION *)zbx_malloc(functions, sizeof(DC_FUNCTION) * (size_t)functionids.values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * (size_t)functionids.values_num);

	DCconfig_get_functions_by_functionids(functions, functionids.values, errcodes, (size_t)functionids.values_num);

	for (i = 0; i < functionids.values_num; i++)
	{
		if (SUCCEED == errcodes[i])
			zbx_vector_uint64_append(&itemids, functions[i].itemid);
	}

	DCconfig_clean_functions(functions, errcodes, (size_t)functionids.values_num);
	zbx_free(functions);

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	items = (DC_ITEM *)zbx_malloc(items, sizeof(DC_ITEM) * (size_t)itemids.values_num);
	errcodes = (int *)zbx_realloc(errcodes, sizeof(int) * (size_t)itemids.values_num);

	DCconfig_get_items_by_itemids(items, itemids.values, errcodes, (size_t)itemids.values_num);

	for (i = 0; i < itemids.values_num; i++)
	{
		if (SUCCEED != errcodes[i])
		{
			*error = zbx_dsprintf(*error, "item id:" ZBX_FS_UI64 " deleted.", itemids.values[i]);
			break;
		}

		if (ITEM_STATUS_DISABLED == items[i].status)
		{
			char key_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

			*error = zbx_dsprintf(*error, "item \"%s\" disabled.", zbx_truncate_itemkey(items[i].key_orig,
					VALUE_ERRMSG_MAX, key_short, sizeof(key_short)));
			break;
		}
		if (HOST_STATUS_NOT_MONITORED == items[i].host.status)
		{
			*error = zbx_dsprintf(*error, "host \"%s\" disabled.", items[i].host.host);
			break;
		}
	}

	DCconfig_clean_items(items, errcodes, (size_t)itemids.values_num);
	zbx_free(items);
	zbx_free(errcodes);

	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_uint64_destroy(&functionids);

	if (NULL != *error)
		goto out;

	*ignore = (SUCCEED == DCconfig_check_trigger_dependencies(trigger.triggerid) ? 0 : 1);

	ret = SUCCEED;
out:
	DCconfig_clean_triggers(&trigger, &errcode, 1);

	return ret;
}

static const char	*check_escalation_result_string(int result)
{
	switch (result)
	{
		case ZBX_ESCALATION_CANCEL:
			return "cancel";
		case ZBX_ESCALATION_DELETE:
			return "delete";
		case ZBX_ESCALATION_SKIP:
			return "skip";
		case ZBX_ESCALATION_PROCESS:
			return "process";
		case ZBX_ESCALATION_SUPPRESS:
			return "suppress";
		default:
			return "unknown";
	}
}

static int	check_unfinished_alerts(const DB_ESCALATION *escalation)
{
	int		ret;
	char		*sql;
	DB_RESULT	result;

	if (0 == escalation->r_eventid)
		return SUCCEED;

	sql = zbx_dsprintf(NULL, "select eventid from alerts where eventid=" ZBX_FS_UI64 " and actionid=" ZBX_FS_UI64
			" and status in (0,3)", escalation->eventid, escalation->actionid);

	result = DBselectN(sql, 1);
	zbx_free(sql);

	if (NULL != DBfetch(result))
		ret = FAIL;
	else
		ret = SUCCEED;

	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether escalation must be cancelled, deleted, skipped or   *
 *          processed.                                                        *
 *                                                                            *
 * Parameters: escalation - [IN]  escalation to check                         *
 *             action     - [IN]  action responsible for the escalation       *
 *             error      - [OUT] message in case escalation is cancelled     *
 *                                                                            *
 * Return value: ZBX_ESCALATION_CANCEL   - the relevant event, item, trigger  *
 *                                         or host is disabled or deleted     *
 *               ZBX_ESCALATION_DELETE   - escalations was created and        *
 *                                         recovered during maintenance       *
 *               ZBX_ESCALATION_SKIP     - escalation is paused during        *
 *                                         maintenance or dependable trigger  *
 *                                         in problem state                   *
 *               ZBX_ESCALATION_SUPPRESS - escalation was created before      *
 *                                         maintenance period                 *
 *               ZBX_ESCALATION_PROCESS  - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static int	check_escalation(const DB_ESCALATION *escalation, const DB_ACTION *action, const ZBX_DB_EVENT *event,
		char **error)
{
	DC_ITEM		item;
	int		errcode, ret = ZBX_ESCALATION_CANCEL;
	unsigned char	maintenance = HOST_MAINTENANCE_STATUS_OFF, skip = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__func__, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		if (SUCCEED != check_escalation_trigger(escalation->triggerid, event->source, &skip, error))
			goto out;

		maintenance = (ZBX_PROBLEM_SUPPRESSED_TRUE == event->suppressed ? HOST_MAINTENANCE_STATUS_ON :
				HOST_MAINTENANCE_STATUS_OFF);

		if (0 == skip && SUCCEED != check_unfinished_alerts(escalation))
			skip = 1;
	}
	else if (EVENT_SOURCE_INTERNAL == event->source)
	{
		if (EVENT_OBJECT_ITEM == event->object || EVENT_OBJECT_LLDRULE == event->object)
		{
			/* item disabled or deleted? */
			DCconfig_get_items_by_itemids(&item, &escalation->itemid, &errcode, 1);

			if (SUCCEED != errcode)
			{
				*error = zbx_dsprintf(*error, "item id:" ZBX_FS_UI64 " deleted.", escalation->itemid);
			}
			else if (ITEM_STATUS_DISABLED == item.status)
			{
				char key_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

				*error = zbx_dsprintf(*error, "item \"%s\" disabled.",
						zbx_truncate_itemkey(item.key_orig, VALUE_ERRMSG_MAX,
						key_short, sizeof(key_short)));
			}
			else if (HOST_STATUS_NOT_MONITORED == item.host.status)
			{
				*error = zbx_dsprintf(*error, "host \"%s\" disabled.", item.host.host);
			}
			else
				maintenance = item.host.maintenance_status;

			DCconfig_clean_items(&item, &errcode, 1);

			if (NULL != *error)
				goto out;

			if (SUCCEED != check_unfinished_alerts(escalation))
				skip = 1;
		}
	}

	if (EVENT_SOURCE_TRIGGERS == action->eventsource &&
			ACTION_PAUSE_SUPPRESSED_TRUE == action->pause_suppressed &&
			HOST_MAINTENANCE_STATUS_ON == maintenance &&
			escalation->acknowledgeid == 0)
	{
		/* remove paused escalations that were created and recovered */
		/* during maintenance period                                 */
		if (0 == escalation->esc_step && 0 != escalation->r_eventid)
		{
			ret = ZBX_ESCALATION_DELETE;
			goto out;
		}

		/* suppress paused escalations created before maintenance period */
		/* until maintenance ends or the escalations are recovered       */
		if (0 == escalation->r_eventid)
		{
			ret = ZBX_ESCALATION_SUPPRESS;
			goto out;
		}
	}

	if (0 != skip)
	{
		/* one of trigger dependencies is in PROBLEM state, process escalation later */
		ret = ZBX_ESCALATION_SKIP;
		goto out;
	}

	ret = ZBX_ESCALATION_PROCESS;
out:

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:'%s'", __func__, check_escalation_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: write escalation cancellation warning message into log file       *
 *                                                                            *
 * Parameters: escalation - [IN] the escalation                               *
 *             error      - [IN] the error message                            *
 *                                                                            *
 ******************************************************************************/
static void	escalation_log_cancel_warning(const DB_ESCALATION *escalation, const char *error)
{
	if (0 != escalation->esc_step)
		zabbix_log(LOG_LEVEL_WARNING, "escalation canceled: %s", error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cancel escalation with the specified error message                *
 *                                                                            *
 * Parameters: escalation - [IN/OUT] the escalation to cancel                 *
 *             action     - [IN]     the action                               *
 *             event      - [IN]     the event                                *
 *             error      - [IN]     the error message                        *
 *                                                                            *
 ******************************************************************************/
static void	escalation_cancel(DB_ESCALATION *escalation, const DB_ACTION *action, const ZBX_DB_EVENT *event,
		const char *error, const char *default_timezone, const ZBX_DB_SERVICE *service, zbx_hashset_t *roles)
{
	ZBX_USER_MSG	*user_msg = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__func__, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	/* the cancellation notification can be sent if no objects are deleted and notification is not disabled */
	if (NULL != action && NULL != event && 0 != event->trigger.triggerid && 0 != escalation->esc_step &&
			ACTION_NOTIFY_IF_CANCELED_FALSE != action->notify_if_canceled)
	{
		add_sentusers_msg_esc_cancel(&user_msg, action->actionid, event, ZBX_NULL2EMPTY_STR(error),
				default_timezone, service, roles);
		flush_user_msg(&user_msg, escalation->esc_step, event, NULL, action->actionid, NULL, NULL, NULL);
	}

	escalation_log_cancel_warning(escalation, ZBX_NULL2EMPTY_STR(error));
	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute next escalation step                                      *
 *                                                                            *
 * Parameters: escalation - [IN/OUT] the escalation to execute                *
 *             action     - [IN]     the action                               *
 *             event      - [IN]     the event                                *
 *                                                                            *
 ******************************************************************************/
static void	escalation_execute(DB_ESCALATION *escalation, const DB_ACTION *action, const ZBX_DB_EVENT *event,
		const ZBX_DB_SERVICE *service, const char *default_timezone, zbx_hashset_t *roles)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__func__, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	escalation_execute_operations(escalation, event, action, service, default_timezone, roles);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process escalation recovery                                       *
 *                                                                            *
 * Parameters: escalation - [IN/OUT] the escalation to recovery               *
 *             action     - [IN]     the action                               *
 *             event      - [IN]     the event                                *
 *             r_event    - [IN]     the recovery event                       *
 *                                                                            *
 ******************************************************************************/
static void	escalation_recover(DB_ESCALATION *escalation, const DB_ACTION *action, const ZBX_DB_EVENT *event,
		const ZBX_DB_EVENT *r_event, const ZBX_DB_SERVICE *service, const char *default_timezone,
		zbx_hashset_t *roles)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__func__, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	escalation_execute_recovery_operations(event, r_event, action, service, default_timezone, roles);

	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process escalation acknowledgment                                 *
 *                                                                            *
 * Parameters: escalation - [IN/OUT] the escalation to recovery               *
 *             action     - [IN]     the action                               *
 *             event      - [IN]     the event                                *
 *             r_event    - [IN]     the recovery event                       *
 *                                                                            *
 ******************************************************************************/
static void	escalation_acknowledge(DB_ESCALATION *escalation, const DB_ACTION *action, const ZBX_DB_EVENT *event,
		const ZBX_DB_EVENT *r_event, const char *default_timezone, zbx_hashset_t *roles)
{
	DB_ROW		row;
	DB_RESULT	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " acknowledgeid:" ZBX_FS_UI64 " status:%s",
			__func__, escalation->escalationid, escalation->acknowledgeid,
			zbx_escalation_status_string(escalation->status));

	result = DBselect(
			"select message,userid,clock,action,old_severity,new_severity,suppress_until from acknowledges"
			" where acknowledgeid=" ZBX_FS_UI64,
			escalation->acknowledgeid);

	if (NULL != (row = DBfetch(result)))
	{
		DB_ACKNOWLEDGE	ack;

		ack.message = row[0];
		ZBX_STR2UINT64(ack.userid, row[1]);
		ack.clock = atoi(row[2]);
		ack.acknowledgeid = escalation->acknowledgeid;
		ack.action = atoi(row[3]);
		ack.old_severity = atoi(row[4]);
		ack.new_severity = atoi(row[5]);
		ack.suppress_until = atoi(row[6]);

		escalation_execute_update_operations(event, r_event, action, &ack, NULL, NULL, default_timezone, roles);
	}

	DBfree_result(result);

	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process update escalation                                         *
 *                                                                            *
 * Parameters: escalation       - [IN/OUT] the escalation to recovery         *
 *             action           - [IN] the action                             *
 *             event            - [IN] the event                              *
 *             service_alarm    - [IN] the service alarm                      *
 *             service          - [IN] the service                            *
 *             default_timezone - [IN] the time zone                          *
 *                                                                            *
 ******************************************************************************/
static void	escalation_update(DB_ESCALATION *escalation, const DB_ACTION *action,
		const ZBX_DB_EVENT *event, const zbx_service_alarm_t *service_alarm, const ZBX_DB_SERVICE *service,
		const char *default_timezone, zbx_hashset_t *roles)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " servicealarmid:" ZBX_FS_UI64 " status:%s",
			__func__, escalation->escalationid, escalation->servicealarmid,
			zbx_escalation_status_string(escalation->status));

	escalation_execute_update_operations(event, NULL, action, NULL, service_alarm, service, default_timezone, roles);

	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

typedef struct
{
	zbx_uint64_t		escalationid;

	int			nextcheck;
	int			esc_step;
	zbx_escalation_status_t	status;

#define ZBX_DIFF_ESCALATION_UNSET			__UINT64_C(0x0000)
#define ZBX_DIFF_ESCALATION_UPDATE_NEXTCHECK		__UINT64_C(0x0001)
#define ZBX_DIFF_ESCALATION_UPDATE_ESC_STEP		__UINT64_C(0x0002)
#define ZBX_DIFF_ESCALATION_UPDATE_STATUS		__UINT64_C(0x0004)
#define ZBX_DIFF_ESCALATION_UPDATE 								\
		(ZBX_DIFF_ESCALATION_UPDATE_NEXTCHECK | ZBX_DIFF_ESCALATION_UPDATE_ESC_STEP |	\
		ZBX_DIFF_ESCALATION_UPDATE_STATUS)
	zbx_uint64_t		flags;
}
zbx_escalation_diff_t;

static zbx_escalation_diff_t	*escalation_create_diff(const DB_ESCALATION *escalation)
{
	zbx_escalation_diff_t	*diff;

	diff = (zbx_escalation_diff_t *)zbx_malloc(NULL, sizeof(zbx_escalation_diff_t));
	diff->escalationid = escalation->escalationid;
	diff->nextcheck = escalation->nextcheck;
	diff->esc_step = escalation->esc_step;
	diff->status = escalation->status;
	diff->flags = ZBX_DIFF_ESCALATION_UNSET;

	return diff;
}

static void	escalation_update_diff(const DB_ESCALATION *escalation, zbx_escalation_diff_t *diff)
{
	if (escalation->nextcheck != diff->nextcheck)
	{
		diff->nextcheck = escalation->nextcheck;
		diff->flags |= ZBX_DIFF_ESCALATION_UPDATE_NEXTCHECK;
	}

	if (escalation->esc_step != diff->esc_step)
	{
		diff->esc_step = escalation->esc_step;
		diff->flags |= ZBX_DIFF_ESCALATION_UPDATE_ESC_STEP;
	}

	if (escalation->status != diff->status)
	{
		diff->status = escalation->status;
		diff->flags |= ZBX_DIFF_ESCALATION_UPDATE_STATUS;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if acknowledgment events of current escalation has related  *
 *          recovery events and add those recovery event IDs to array of      *
 *          event IDs if this escalation                                      *
 *                                                                            *
 * Parameters: escalations - [IN] array of escalations to be processed        *
 *             eventids    - [OUT] array of events of current escalation      *
 *             event_pairs - [OUT] the array of event ID and recovery event   *
 *                                 pairs                                      *
 *                                                                            *
 * Comments: additionally acknowledgment event IDs are mapped with related    *
 *           recovery event IDs in get_db_eventid_r_eventid_pairs()           *
 *                                                                            *
 ******************************************************************************/
static void	add_ack_escalation_r_eventids(zbx_vector_ptr_t *escalations, zbx_vector_uint64_t *eventids,
		zbx_vector_uint64_pair_t *event_pairs)
{
	int			i;
	zbx_vector_uint64_t	ack_eventids, r_eventids;

	zbx_vector_uint64_create(&ack_eventids);
	zbx_vector_uint64_create(&r_eventids);

	for (i = 0; i < escalations->values_num; i++)
	{
		DB_ESCALATION	*escalation;

		escalation = (DB_ESCALATION *)escalations->values[i];

		if (0 != escalation->acknowledgeid)
			zbx_vector_uint64_append(&ack_eventids, escalation->eventid);
	}

	if (0 < ack_eventids.values_num)
	{
		zbx_db_get_eventid_r_eventid_pairs(&ack_eventids, event_pairs, &r_eventids);

		if (0 < r_eventids.values_num)
			zbx_vector_uint64_append_array(eventids, r_eventids.values, r_eventids.values_num);
	}

	zbx_vector_uint64_destroy(&ack_eventids);
	zbx_vector_uint64_destroy(&r_eventids);
}

static void	get_services_rootcause_eventids(const zbx_vector_uint64_t *serviceids, zbx_vector_service_t *services)
{
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;
	int			i;
	zbx_ipc_message_t	response;

	for (i = 0; i < serviceids->values_num; i++)
		zbx_service_serialize_id(&data, &data_alloc, &data_offset, serviceids->values[i]);

	if (NULL == data)
		return;

	zbx_ipc_message_init(&response);
	zbx_service_send(ZBX_IPC_SERVICE_SERVICE_ROOTCAUSE, data, (zbx_uint32_t)data_offset, &response);
	zbx_service_deserialize_rootcause(response.data, (zbx_uint32_t)response.size, services);
	zbx_ipc_message_clean(&response);

	zbx_free(data);
}

static void	db_get_services(const zbx_vector_ptr_t *escalations, zbx_vector_service_t *services,
		zbx_vector_ptr_t *events)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	serviceids, eventids;
	int			i, j, index;
	zbx_int64_t		last_serviceid = -1;

	zbx_vector_uint64_create(&serviceids);
	zbx_vector_uint64_create(&eventids);

	for (i = 0; i < escalations->values_num; i++)
	{
		DB_ESCALATION	*escalation;

		escalation = (DB_ESCALATION *)escalations->values[i];

		if (0 != escalation->serviceid)
		{
			zbx_vector_uint64_append(&serviceids, escalation->serviceid);
		}
	}

	zbx_vector_uint64_sort(&serviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&serviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "s.serviceid", serviceids.values,
			serviceids.values_num);

	result = DBselect(
			"select s.serviceid,s.name,s.description,st.tag,st.value"
			" from services s left join service_tag st on s.serviceid=st.serviceid"
			" where%s order by s.serviceid",
			sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DB_SERVICE	*service;
		zbx_uint64_t	serviceid;

		ZBX_STR2UINT64(serviceid, row[0]);

		if ((zbx_int64_t)serviceid == last_serviceid)
		{
			ZBX_DB_SERVICE	*last_service;
			zbx_tag_t	*tag = zbx_malloc(NULL, sizeof(zbx_tag_t));

			last_service = services->values[services->values_num - 1];

			tag->tag = zbx_strdup(NULL, row[3]);
			tag->value = zbx_strdup(NULL, row[4]);

			zbx_vector_tags_append(&last_service->service_tags, tag);
			continue;
		}

		service = (ZBX_DB_SERVICE*)zbx_malloc(NULL, sizeof(ZBX_DB_SERVICE));
		service->serviceid = serviceid;
		service->name = zbx_strdup(NULL, row[1]);
		service->description = zbx_strdup(NULL, row[2]);
		zbx_vector_uint64_create(&service->eventids);
		zbx_vector_ptr_create(&service->events);
		zbx_vector_tags_create(&service->service_tags);

		if (FAIL == DBis_null(row[3]))
		{
			zbx_tag_t	*tag = zbx_malloc(NULL, sizeof(zbx_tag_t));

			tag->tag = zbx_strdup(NULL, row[3]);
			tag->value = zbx_strdup(NULL, row[4]);

			zbx_vector_tags_append(&service->service_tags, tag);
		}

		zbx_vector_service_append(services, service);

		last_serviceid = (zbx_int64_t)service->serviceid;
	}
	DBfree_result(result);
	zbx_free(sql);

	get_services_rootcause_eventids(&serviceids, services);

	for (i = 0; i < services->values_num; i++)
	{
		ZBX_DB_SERVICE	*service = services->values[i];

		for (j = 0; j < service->eventids.values_num; j++)
			zbx_vector_uint64_append(&eventids, service->eventids.values[j]);
	}

	if (0 != eventids.values_num)
	{
		zbx_db_get_events_by_eventids(&eventids, events);

		for (i = 0; i < services->values_num; i++)
		{
			ZBX_DB_SERVICE	*service = services->values[i];
			ZBX_DB_EVENT	*event;

			for (j = 0; j < service->eventids.values_num; j++)
			{
				if (FAIL == (index = zbx_vector_ptr_bsearch(events, &service->eventids.values[j],
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					continue;
				}

				event = (ZBX_DB_EVENT *)events->values[index];

				if (0 != event->trigger.triggerid)
					zbx_vector_ptr_append(&service->events, event);
			}
		}
	}

	zbx_vector_uint64_destroy(&eventids);
	zbx_vector_uint64_destroy(&serviceids);
}

static void	db_get_service_alarms(zbx_vector_service_alarm_t *service_alarms,
		const zbx_vector_uint64_t *service_alarmids)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*filter = NULL;
	size_t		filter_alloc = 0, filter_offset = 0;

	DBadd_condition_alloc(&filter, &filter_alloc, &filter_offset, "servicealarmid", service_alarmids->values,
			service_alarmids->values_num);

	result = DBselect("select servicealarmid,clock,value"
			" from service_alarms"
			" where%s order by servicealarmid",
			filter);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_service_alarm_t	service_alarm;

		ZBX_STR2UINT64(service_alarm.service_alarmid, row[0]);
		service_alarm.clock = atoi(row[1]);
		service_alarm.value = atoi(row[2]);

		zbx_vector_service_alarm_append(service_alarms, service_alarm);
	}
	DBfree_result(result);

	zbx_free(filter);
}

static void	get_db_service_alarms(zbx_vector_ptr_t *escalations, zbx_vector_service_alarm_t *service_alarms)
{
	int			i;
	zbx_vector_uint64_t	service_alarmids;

	zbx_vector_uint64_create(&service_alarmids);

	for (i = 0; i < escalations->values_num; i++)
	{
		DB_ESCALATION	*escalation;

		escalation = (DB_ESCALATION *)escalations->values[i];

		if (0 != escalation->servicealarmid)
			zbx_vector_uint64_append(&service_alarmids, escalation->servicealarmid);
	}

	if (0 != service_alarmids.values_num)
	{
		zbx_vector_uint64_sort(&service_alarmids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&service_alarmids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		db_get_service_alarms(service_alarms, &service_alarmids);
	}

	zbx_vector_uint64_destroy(&service_alarmids);
}

static void	service_clean(ZBX_DB_SERVICE *service)
{
	zbx_free(service->name);
	zbx_free(service->description);
	zbx_vector_ptr_destroy(&service->events);
	zbx_vector_uint64_destroy(&service->eventids);
	zbx_vector_tags_clear_ext(&service->service_tags, zbx_free_tag);
	zbx_vector_tags_destroy(&service->service_tags);
	zbx_free(service);
}

static void	service_role_clean(zbx_service_role_t *role)
{
	zbx_vector_tags_clear_ext(&role->tags, zbx_free_tag);
	zbx_vector_tags_destroy(&role->tags);
	zbx_vector_uint64_destroy(&role->serviceids);
}

static int	process_db_escalations(int now, int *nextcheck, zbx_vector_ptr_t *escalations,
		zbx_vector_uint64_t *eventids, zbx_vector_uint64_t *actionids, const char *default_timezone)
{
	int				i, ret;
	zbx_vector_uint64_t		escalationids;
	zbx_vector_ptr_t		diffs, actions, events;
	zbx_escalation_diff_t		*diff;
	zbx_vector_uint64_pair_t	event_pairs;
	zbx_vector_service_alarm_t	service_alarms;
	zbx_service_alarm_t		*service_alarm, service_alarm_local;
	zbx_vector_service_t		services;
	zbx_hashset_t			service_roles;
	ZBX_DB_SERVICE			service_local;
	zbx_dc_um_handle_t		*um_handle;

	zbx_vector_uint64_create(&escalationids);
	zbx_vector_ptr_create(&diffs);
	zbx_vector_ptr_create(&actions);
	zbx_vector_ptr_create(&events);
	zbx_vector_uint64_pair_create(&event_pairs);
	zbx_vector_service_alarm_create(&service_alarms);
	zbx_vector_service_create(&services);

	zbx_hashset_create_ext(&service_roles, 100, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)service_role_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	add_ack_escalation_r_eventids(escalations, eventids, &event_pairs);

	um_handle = zbx_dc_open_user_macros();

	get_db_actions_info(actionids, &actions);
	zbx_db_get_events_by_eventids(eventids, &events);

	if (0 != ((DB_ESCALATION *)escalations->values[0])->serviceid)
	{
		db_get_services(escalations, &services, &events);	/* reuse events vector for service events */
		get_db_service_alarms(escalations, &service_alarms);
	}

	for (i = 0; i < escalations->values_num; i++)
	{
		int		index, state = ZBX_ESCALATION_UNSET;
		char		*error = NULL;
		DB_ACTION	*action = NULL;
		ZBX_DB_EVENT	*event = NULL, *r_event;
		DB_ESCALATION	*escalation;
		ZBX_DB_SERVICE	*service = NULL;

		escalation = (DB_ESCALATION *)escalations->values[i];

		if (FAIL == (index = zbx_vector_ptr_bsearch(&actions, &escalation->actionid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			error = zbx_dsprintf(error, "action id:" ZBX_FS_UI64 " deleted", escalation->actionid);
			state = ZBX_ESCALATION_CANCEL;
		}
		else
		{
			action = (DB_ACTION *)actions.values[index];

			if (ACTION_STATUS_ACTIVE != action->status)
			{
				error = zbx_dsprintf(error, "action '%s' disabled.", action->name);
				state = ZBX_ESCALATION_CANCEL;
			}
		}

		if (FAIL == (index = zbx_vector_ptr_bsearch(&events, &escalation->eventid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			error = zbx_dsprintf(error, "event id:" ZBX_FS_UI64 " deleted.", escalation->eventid);
			state = ZBX_ESCALATION_CANCEL;
		}
		else
		{
			event = (ZBX_DB_EVENT *)events.values[index];

			if ((EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source) &&
					EVENT_OBJECT_TRIGGER == event->object && 0 == event->trigger.triggerid)
			{
				error = zbx_dsprintf(error, "trigger id:" ZBX_FS_UI64 " deleted.", event->objectid);
				state = ZBX_ESCALATION_CANCEL;
			}
			else if (EVENT_SOURCE_SERVICE == event->source)
			{
				service_local.serviceid = escalation->serviceid;

				if (0 != escalation->servicealarmid)
				{
					service_alarm_local.service_alarmid = escalation->servicealarmid;
					if (FAIL == (index = zbx_vector_service_alarm_bsearch(&service_alarms,
							service_alarm_local, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
					{
						error = zbx_dsprintf(error, "service alarm id:" ZBX_FS_UI64 " deleted.",
								escalation->servicealarmid);
						state = ZBX_ESCALATION_CANCEL;
					}
					else
						service_alarm = &service_alarms.values[index];
				}

				if (escalation->serviceid != event->objectid)
				{
					error = zbx_dsprintf(error, "service id:" ZBX_FS_UI64 " does not match"
							" escalation service id:" ZBX_FS_UI64, event->objectid,
							escalation->serviceid);
					state = ZBX_ESCALATION_CANCEL;
					THIS_SHOULD_NEVER_HAPPEN;
				}
				else if (FAIL == (index = zbx_vector_service_bsearch(&services, &service_local,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					error = zbx_dsprintf(error, "service id:" ZBX_FS_UI64 " deleted.",
							escalation->serviceid);
					state = ZBX_ESCALATION_CANCEL;
				}
				else
					service = services.values[index];
			}
		}

		if (0 != escalation->r_eventid)
		{
			if (FAIL == (index = zbx_vector_ptr_bsearch(&events, &escalation->r_eventid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				error = zbx_dsprintf(error, "event id:" ZBX_FS_UI64 " deleted.", escalation->r_eventid);
				state = ZBX_ESCALATION_CANCEL;
			}
			else
			{
				r_event = (ZBX_DB_EVENT *)events.values[index];

				if (EVENT_SOURCE_TRIGGERS == r_event->source && 0 == r_event->trigger.triggerid)
				{
					error = zbx_dsprintf(error, "trigger id:" ZBX_FS_UI64 " deleted.", r_event->objectid);
					state = ZBX_ESCALATION_CANCEL;
				}
			}
		}
		else
			r_event = NULL;

		/* Handle escalation taking into account status of items, triggers, hosts, */
		/* maintenance and trigger dependencies.                                   */
		if (ZBX_ESCALATION_UNSET == state)
			state = check_escalation(escalation, action, event, &error);

		switch (state)
		{
			case ZBX_ESCALATION_CANCEL:
				escalation_cancel(escalation, action, event, error, default_timezone, service, &service_roles);
				zbx_free(error);
				zbx_vector_uint64_append(&escalationids, escalation->escalationid);
				continue;
			case ZBX_ESCALATION_DELETE:
				zbx_vector_uint64_append(&escalationids, escalation->escalationid);
				continue;
			case ZBX_ESCALATION_SKIP:
				continue;
			case ZBX_ESCALATION_SUPPRESS:
				diff = escalation_create_diff(escalation);
				escalation->nextcheck = now + SEC_PER_MIN;
				escalation_update_diff(escalation, diff);
				zbx_vector_ptr_append(&diffs, diff);
				continue;
			case ZBX_ESCALATION_PROCESS:
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
		}

		/* Execute operations and recovery operations, mark changes in 'diffs' for batch saving in DB below. */
		diff = escalation_create_diff(escalation);

		if (0 != escalation->servicealarmid)
		{
			/* service_alarm is either initialized when servicealarmid is set or */
			/* the escalation is cancelled and this code will not be reached     */
			escalation_update(escalation, action, event, service_alarm, service, default_timezone, &service_roles);
		}
		else if (0 != escalation->acknowledgeid)
		{
			zbx_uint64_t		r_eventid = 0;
			zbx_uint64_pair_t	event_pair;

			r_event = NULL;
			event_pair.first = event->eventid;

			if (FAIL != (index = zbx_vector_uint64_pair_bsearch(&event_pairs, event_pair,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				r_eventid = event_pairs.values[index].second;

				if (FAIL != (index = zbx_vector_ptr_bsearch(&events, &r_eventid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					r_event = (ZBX_DB_EVENT *)events.values[index];
				}

			}

			escalation_acknowledge(escalation, action, event, r_event, default_timezone, &service_roles);
		}
		else if (NULL != r_event)
		{
			if (0 == escalation->esc_step)
				escalation_execute(escalation, action, event, service, default_timezone, &service_roles);
			else
				escalation_recover(escalation, action, event, r_event, service, default_timezone, &service_roles);
		}
		else if (escalation->nextcheck <= now)
		{
			if (ESCALATION_STATUS_ACTIVE == escalation->status)
			{
				escalation_execute(escalation, action, event, service, default_timezone, &service_roles);
			}
			else if (ESCALATION_STATUS_SLEEP == escalation->status)
			{
				escalation->nextcheck = now + (0 == action->esc_period ? SEC_PER_HOUR :
						action->esc_period);
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
			}
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
		}

		escalation_update_diff(escalation, diff);
		zbx_vector_ptr_append(&diffs, diff);
	}

	if (0 == diffs.values_num && 0 == escalationids.values_num)
		goto out;

	DBbegin();

	/* 2. Update escalations in the DB. */
	if (0 != diffs.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;

		sql = (char *)zbx_malloc(sql, sql_alloc);

		zbx_vector_ptr_sort(&diffs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < diffs.values_num; i++)
		{
			char	separator = ' ';

			diff = (zbx_escalation_diff_t *)diffs.values[i];

			if (ESCALATION_STATUS_COMPLETED == diff->status)
			{
				zbx_vector_uint64_append(&escalationids, diff->escalationid);
				continue;
			}

			if (0 == (diff->flags & ZBX_DIFF_ESCALATION_UPDATE))
				continue;

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update escalations set");

			if (0 != (diff->flags & ZBX_DIFF_ESCALATION_UPDATE_NEXTCHECK))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cnextcheck="
						"case when r_eventid is null then %d else 0 end", separator,
						diff->nextcheck);
				separator = ',';

				if (diff->nextcheck < *nextcheck)
				{
					*nextcheck = diff->nextcheck;
				}
			}

			if (0 != (diff->flags & ZBX_DIFF_ESCALATION_UPDATE_ESC_STEP))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cesc_step=%d", separator,
						diff->esc_step);
				separator = ',';
			}

			if (0 != (diff->flags & ZBX_DIFF_ESCALATION_UPDATE_STATUS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cstatus=%d", separator,
						(int)diff->status);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where escalationid=" ZBX_FS_UI64 ";\n",
					diff->escalationid);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)	/* in ORACLE always present begin..end; */
			DBexecute("%s", sql);

		zbx_free(sql);
	}

	/* 3. Delete cancelled, completed escalations. */
	if (0 != escalationids.values_num)
	{
		zbx_vector_uint64_sort(&escalationids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBexecute_multiple_query("delete from escalations where", "escalationid", &escalationids);
	}

	DBcommit();
out:
	zbx_dc_close_user_macros(um_handle);

	zbx_vector_ptr_clear_ext(&diffs, zbx_ptr_free);
	zbx_vector_ptr_destroy(&diffs);

	zbx_vector_ptr_clear_ext(&actions, (zbx_clean_func_t)free_db_action);
	zbx_vector_ptr_destroy(&actions);

	zbx_vector_ptr_clear_ext(&events, (zbx_clean_func_t)zbx_db_free_event);
	zbx_vector_ptr_destroy(&events);

	zbx_vector_uint64_pair_destroy(&event_pairs);
	zbx_vector_service_alarm_destroy(&service_alarms);

	zbx_vector_service_clear_ext(&services, service_clean);
	zbx_vector_service_destroy(&services);

	zbx_hashset_destroy(&service_roles);

	ret = escalationids.values_num; /* performance metric */

	zbx_vector_uint64_destroy(&escalationids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute escalation steps and recovery operations;                 *
 *          postpone escalations during maintenance and due to trigger dep.;  *
 *          delete completed escalations from the database;                   *
 *          cancel escalations due to changed configuration, etc.             *
 *                                                                            *
 * Parameters: now               - [IN] the current time                      *
 *             nextcheck         - [IN/OUT] time of the next invocation       *
 *             escalation_source - [IN] type of escalations to be handled     *
 *                                                                            *
 * Return value: the count of deleted escalations                             *
 *                                                                            *
 * Comments: actions.c:process_actions() creates pseudo-escalations also for  *
 *           EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION events,    *
 *           this function handles message and command operations for these   *
 *           events while host, group, template operations are handled        *
 *           in process_actions().                                            *
 *                                                                            *
 ******************************************************************************/
static int	process_escalations(int now, int *nextcheck, unsigned int escalation_source,
		const char *default_timezone)
{
	int			ret = 0;
	DB_RESULT		result;
	DB_ROW			row;
	char			*filter = NULL;
	size_t			filter_alloc = 0, filter_offset = 0;

	zbx_vector_ptr_t		escalations;
	zbx_vector_uint64_t		actionids, eventids;

	DB_ESCALATION		*escalation;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&escalations);
	zbx_vector_uint64_create(&actionids);
	zbx_vector_uint64_create(&eventids);

	/* Selection of escalations to be processed:                                                          */
	/*                                                                                                    */
	/* e - row in escalations table, E - escalations table, S - ordered* set of escalations to be proc.   */
	/*                                                                                                    */
	/* ZBX_ESCALATION_SOURCE_TRIGGER: S = {e in E | e.triggerid    mod process_num == 0}                  */
	/* ZBX_ESCALATION_SOURCE_ITEM::   S = {e in E | e.itemid       mod process_num == 0}                  */
	/* ZBX_ESCALATION_SOURCE_DEFAULT: S = {e in E | e.escalationid mod process_num == 0}                  */
	/*                                                                                                    */
	/* Note that each escalator always handles all escalations from the same triggers and items.          */
	/* The rest of the escalations (e.g. not trigger or item based) are spread evenly between escalators. */
	/*                                                                                                    */
	/* * by e.actionid, e.triggerid, e.itemid, e.escalationid                                             */
	switch (escalation_source)
	{
		case ZBX_ESCALATION_SOURCE_TRIGGER:
			zbx_strcpy_alloc(&filter, &filter_alloc, &filter_offset, "triggerid is not null");
			if (1 < CONFIG_ESCALATOR_FORKS)
			{
				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						" and " ZBX_SQL_MOD(triggerid, %d) "=%d",
						CONFIG_ESCALATOR_FORKS, process_num - 1);
			}
			break;
		case ZBX_ESCALATION_SOURCE_ITEM:
			zbx_strcpy_alloc(&filter, &filter_alloc, &filter_offset, "triggerid is null and"
					" itemid is not null");
			if (1 < CONFIG_ESCALATOR_FORKS)
			{
				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						" and " ZBX_SQL_MOD(itemid, %d) "=%d",
						CONFIG_ESCALATOR_FORKS, process_num - 1);
			}
			break;
		case ZBX_ESCALATION_SOURCE_SERVICE:
			zbx_strcpy_alloc(&filter, &filter_alloc, &filter_offset,
					"triggerid is null and itemid is null and serviceid is not null");
			if (1 < CONFIG_ESCALATOR_FORKS)
			{
				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						" and " ZBX_SQL_MOD(serviceid, %d) "=%d",
						CONFIG_ESCALATOR_FORKS, process_num - 1);
			}
			break;
		case ZBX_ESCALATION_SOURCE_DEFAULT:
			zbx_strcpy_alloc(&filter, &filter_alloc, &filter_offset,
					"triggerid is null and itemid is null and serviceid is null");
			if (1 < CONFIG_ESCALATOR_FORKS)
			{
				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						" and " ZBX_SQL_MOD(escalationid, %d) "=%d",
						CONFIG_ESCALATOR_FORKS, process_num - 1);
			}
			break;
	}

	result = DBselect("select escalationid,actionid,triggerid,eventid,r_eventid,nextcheck,esc_step,status,itemid,"
					"acknowledgeid,servicealarmid,serviceid"
				" from escalations"
				" where %s and nextcheck<=%d"
				" order by actionid,triggerid,itemid,escalationid", filter,
				now + CONFIG_ESCALATOR_FREQUENCY);
	zbx_free(filter);

	while (NULL != (row = DBfetch(result)) && ZBX_IS_RUNNING())
	{
		int	esc_nextcheck;

		esc_nextcheck = atoi(row[5]);

		/* skip escalations that must be checked in next CONFIG_ESCALATOR_FREQUENCY period */
		if (esc_nextcheck > now)
		{
			if (esc_nextcheck < *nextcheck)
				*nextcheck = esc_nextcheck;

			continue;
		}

		escalation = (DB_ESCALATION *)zbx_malloc(NULL, sizeof(DB_ESCALATION));
		escalation->nextcheck = esc_nextcheck;
		ZBX_DBROW2UINT64(escalation->r_eventid, row[4]);
		ZBX_STR2UINT64(escalation->escalationid, row[0]);
		ZBX_STR2UINT64(escalation->actionid, row[1]);
		ZBX_DBROW2UINT64(escalation->triggerid, row[2]);
		ZBX_DBROW2UINT64(escalation->eventid, row[3]);
		escalation->esc_step = atoi(row[6]);
		escalation->status = atoi(row[7]);
		ZBX_DBROW2UINT64(escalation->itemid, row[8]);
		ZBX_DBROW2UINT64(escalation->acknowledgeid, row[9]);
		ZBX_DBROW2UINT64(escalation->servicealarmid, row[10]);
		ZBX_DBROW2UINT64(escalation->serviceid, row[11]);

		zbx_vector_ptr_append(&escalations, escalation);
		zbx_vector_uint64_append(&actionids, escalation->actionid);
		zbx_vector_uint64_append(&eventids, escalation->eventid);

		if (0 < escalation->r_eventid)
			zbx_vector_uint64_append(&eventids, escalation->r_eventid);

		if (escalations.values_num >= ZBX_ESCALATIONS_PER_STEP)
		{
			ret += process_db_escalations(now, nextcheck, &escalations, &eventids, &actionids,
					default_timezone);
			zbx_vector_ptr_clear_ext(&escalations, zbx_ptr_free);
			zbx_vector_uint64_clear(&actionids);
			zbx_vector_uint64_clear(&eventids);
		}
	}
	DBfree_result(result);

	if (0 < escalations.values_num)
	{
		ret += process_db_escalations(now, nextcheck, &escalations, &eventids, &actionids, default_timezone);
		zbx_vector_ptr_clear_ext(&escalations, zbx_ptr_free);
	}

	zbx_vector_ptr_destroy(&escalations);
	zbx_vector_uint64_destroy(&actionids);
	zbx_vector_uint64_destroy(&eventids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret; /* performance metric */
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically check table escalations and generate alerts          *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(escalator_thread, args)
{
	int		now, nextcheck, sleeptime = -1, escalations_count = 0, old_escalations_count = 0;
	double		sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t		last_stat_time;
	zbx_config_t	cfg;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [processed %d escalations in " ZBX_FS_DBL
					" sec, processing escalations]", get_process_type_string(process_type),
					process_num, old_escalations_count, old_total_sec);
		}

		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_DEFAULT_TIMEZONE);

		nextcheck = time(NULL) + CONFIG_ESCALATOR_FREQUENCY;
		escalations_count += process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_TRIGGER,
				cfg.default_timezone);
		escalations_count += process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_ITEM,
				cfg.default_timezone);
		escalations_count += process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_SERVICE,
				cfg.default_timezone);
		escalations_count += process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_DEFAULT,
				cfg.default_timezone);

		zbx_config_clean(&cfg);
		total_sec += zbx_time() - sec;

		sleeptime = calculate_sleeptime(nextcheck, CONFIG_ESCALATOR_FREQUENCY);

		now = time(NULL);

		if (0 != sleeptime || STAT_INTERVAL <= now - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [processed %d escalations in " ZBX_FS_DBL
						" sec, processing escalations]", get_process_type_string(process_type),
						process_num, escalations_count, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [processed %d escalations in " ZBX_FS_DBL " sec, idle %d sec]",
						get_process_type_string(process_type), process_num, escalations_count,
						total_sec, sleeptime);

				old_escalations_count = escalations_count;
				old_total_sec = total_sec;
			}
			escalations_count = 0;
			total_sec = 0.0;
			last_stat_time = now;
		}

		zbx_sleep_loop(sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
