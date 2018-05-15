/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "log.h"
#include "daemon.h"
#include "zbxserver.h"
#include "zbxself.h"
#include "zbxtasks.h"

#include "escalator.h"
#include "../operations.h"
#include "../actions.h"
#include "../events.h"
#include "../scripts/scripts.h"
#include "../../libs/zbxcrypto/tls.h"
#include "comms.h"

extern int	CONFIG_ESCALATOR_FORKS;

#define CONFIG_ESCALATOR_FREQUENCY	3

#define ZBX_ESCALATION_SOURCE_DEFAULT	0
#define ZBX_ESCALATION_SOURCE_ITEM	1
#define ZBX_ESCALATION_SOURCE_TRIGGER	2

#define ZBX_ESCALATION_CANCEL		0
#define ZBX_ESCALATION_DELETE		1
#define ZBX_ESCALATION_SKIP		2
#define ZBX_ESCALATION_PROCESS		3

#define ZBX_ESCALATIONS_PER_STEP	1000

typedef struct
{
	zbx_uint64_t	userid;
	zbx_uint64_t	mediatypeid;
	zbx_uint64_t	ackid;
	char		*subject;
	char		*message;
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

static void	zbx_tag_filter_free(zbx_tag_filter_t *tag_filter)
{
	zbx_free(tag_filter->tag);
	zbx_free(tag_filter->value);
	zbx_free(tag_filter);
}

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

static void	add_message_alert(const DB_EVENT *event, const DB_EVENT *r_event, zbx_uint64_t actionid, int esc_step,
		zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *subject, const char *message,
		zbx_uint64_t ackid);

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
	zbx_free(sql);
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
	char			*sql = NULL, hostgroupid[ZBX_MAX_UINT64_LEN + 1];
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			ret = FAIL, i;
	zbx_vector_ptr_t	tag_filters;
	zbx_tag_filter_t	*tag_filter;
	DB_CONDITION		condition;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

			ret = check_action_condition(event, &condition);
		}
		else
			ret = SUCCEED;
	}
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
	zbx_uint64_t		hostgroupid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (USER_TYPE_SUPER_ADMIN == get_user_type(userid))
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

static void	add_user_msg(zbx_uint64_t userid, zbx_uint64_t mediatypeid, ZBX_USER_MSG **user_msg,
		const char *subject, const char *message, zbx_uint64_t ackid)
{
	const char	*__function_name = "add_user_msg";
	ZBX_USER_MSG	*p, **pnext;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == mediatypeid)
	{
		for (pnext = user_msg, p = *user_msg; NULL != p; p = *pnext)
		{
			if (p->userid == userid && p->ackid == ackid && 0 == strcmp(p->subject, subject) &&
					0 == strcmp(p->message, message) && 0 != p->mediatypeid)
			{
				*pnext = (ZBX_USER_MSG *)p->next;

				zbx_free(p->subject);
				zbx_free(p->message);
				zbx_free(p);
			}
			else
				pnext = (ZBX_USER_MSG **)&p->next;
		}
	}

	for (p = *user_msg; NULL != p; p = (ZBX_USER_MSG *)p->next)
	{
		if (p->userid == userid && p->ackid == ackid && 0 == strcmp(p->subject, subject) &&
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
		p->ackid = ackid;
		p->subject = zbx_strdup(NULL, subject);
		p->message = zbx_strdup(NULL, message);
		p->next = *user_msg;

		*user_msg = p;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_object_msg(zbx_uint64_t actionid, zbx_uint64_t operationid, zbx_uint64_t mediatypeid,
		ZBX_USER_MSG **user_msg, const char *subject, const char *message, const DB_EVENT *event,
		const DB_EVENT *r_event, const DB_ACKNOWLEDGE *ack, int macro_type)

{
	const char	*__function_name = "add_object_msg";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid;
	char		*subject_dyn, *message_dyn;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
		ZBX_STR2UINT64(userid, row[0]);

		/* exclude acknowledgement author from the recipient list */
		if (NULL != ack && ack->userid == userid)
			continue;

		if (SUCCEED != check_perm2system(userid))
			continue;

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (PERM_READ > get_trigger_permission(userid, event))
					continue;
				break;
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				if (PERM_READ > get_item_permission(userid, event->objectid))
					continue;
				break;
		}

		subject_dyn = zbx_strdup(NULL, subject);
		message_dyn = zbx_strdup(NULL, message);

		substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL, NULL, ack,
				&subject_dyn, macro_type, NULL, 0);
		substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL, NULL, ack,
				&message_dyn, macro_type, NULL, 0);

		add_user_msg(userid, mediatypeid, user_msg, subject_dyn, message_dyn,
				(NULL != ack ? ack->acknowledgeid : 0));

		zbx_free(subject_dyn);
		zbx_free(message_dyn);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: add_sentusers_msg                                                *
 *                                                                            *
 * Purpose: adds message to be sent to all recipients of messages previously  *
 *          generated by action operations or acknowledgement operations,     *
 *          which is related with an event or recovery event                  *
 *                                                                            *
 * Parameters: user_msg - [IN/OUT] the message list                           *
 *             actionid - [IN] the action identifier                          *
 *             event    - [IN] the event                                      *
 *             r_event  - [IN] the recover event (optional, can be NULL)      *
 *             subject  - [IN] the message subject                            *
 *             message  - [IN] the message body                               *
 *             ack      - [IN] the acknowledge (optional, can be NULL)        *
 *                                                                            *
 ******************************************************************************/
static void	add_sentusers_msg(ZBX_USER_MSG **user_msg, zbx_uint64_t actionid, const DB_EVENT *event,
		const DB_EVENT *r_event, const char *subject, const char *message, const DB_ACKNOWLEDGE *ack)
{
	const char	*__function_name = "add_sentusers_msg";
	char		*subject_dyn, *message_dyn, *sql = NULL;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid, mediatypeid;
	int		message_type;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
		message_type = MACRO_TYPE_MESSAGE_ACK;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(userid, row[0]);

		/* exclude acknowledgement author from the recipient list */
		if (NULL != ack && ack->userid == userid)
			continue;

		if (SUCCEED != check_perm2system(userid))
			continue;

		ZBX_STR2UINT64(mediatypeid, row[1]);

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (PERM_READ > get_trigger_permission(userid, event))
					continue;
				break;
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				if (PERM_READ > get_item_permission(userid, event->objectid))
					continue;
				break;
		}

		subject_dyn = zbx_strdup(NULL, subject);
		message_dyn = zbx_strdup(NULL, message);

		substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL, NULL,
				ack, &subject_dyn, message_type, NULL, 0);
		substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL, NULL,
				ack, &message_dyn, message_type, NULL, 0);

		add_user_msg(userid, mediatypeid, user_msg, subject_dyn, message_dyn,
				(NULL != ack ? ack->acknowledgeid : 0));

		zbx_free(subject_dyn);
		zbx_free(message_dyn);
	}
	DBfree_result(result);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: add_sentusers_ack_msg                                            *
 *                                                                            *
 * Purpose: adds message to be sent to all who added acknowlegment and        *
 *          involved in discussion                                            *
 *                                                                            *
 * Parameters: user_msg    - [IN/OUT] the message list                        *
 *             actionid    - [IN] the action identifie                        *
 *             mediatypeid - [IN] the media type id defined for the operation *
 *             event       - [IN] the event                                   *
 *             ack         - [IN] the acknowlegment                           *
 *             subject     - [IN] the message subject                         *
 *             message     - [IN] the message body                            *
 *                                                                            *
 ******************************************************************************/
static void	add_sentusers_ack_msg(ZBX_USER_MSG **user_msg, zbx_uint64_t actionid, zbx_uint64_t mediatypeid,
		const DB_EVENT *event, const DB_EVENT *r_event, const DB_ACKNOWLEDGE *ack, const char *subject,
		const char *message)
{
	const char	*__function_name = "add_sentusers_ack_msg";
	char		*subject_dyn, *message_dyn;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct userid"
			" from acknowledges"
			" where eventid=" ZBX_FS_UI64,
			event->eventid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(userid, row[0]);

		/* exclude acknowledgement author from the recipient list */
		if (ack->userid == userid)
			continue;

		if (SUCCEED != check_perm2system(userid) || PERM_READ > get_trigger_permission(userid, event))
			continue;

		subject_dyn = zbx_strdup(NULL, subject);
		message_dyn = zbx_strdup(NULL, message);

		substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL,
				NULL, ack, &subject_dyn, MACRO_TYPE_MESSAGE_ACK, NULL, 0);
		substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL,
				NULL, ack, &message_dyn, MACRO_TYPE_MESSAGE_ACK, NULL, 0);

		add_user_msg(userid, mediatypeid, user_msg, subject_dyn, message_dyn, ack->acknowledgeid);

		zbx_free(subject_dyn);
		zbx_free(message_dyn);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	flush_user_msg(ZBX_USER_MSG **user_msg, int esc_step, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t actionid)
{
	ZBX_USER_MSG	*p;

	while (NULL != *user_msg)
	{
		p = *user_msg;
		*user_msg = (ZBX_USER_MSG *)(*user_msg)->next;

		add_message_alert(event, r_event, actionid, esc_step, p->userid, p->mediatypeid, p->subject,
				p->message, p->ackid);

		zbx_free(p->subject);
		zbx_free(p->message);
		zbx_free(p);
	}
}

static void	add_command_alert(zbx_db_insert_t *db_insert, int alerts_num, zbx_uint64_t alertid, const DC_HOST *host,
		const DB_EVENT *event, const DB_EVENT *r_event, zbx_uint64_t actionid, int esc_step,
		const char *command, zbx_alert_status_t status, const char *error)
{
	const char	*__function_name = "add_command_alert";
	int		now, alerttype = ALERT_TYPE_COMMAND, alert_status = status;
	char		*tmp = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == alerts_num)
	{
		zbx_db_insert_prepare(db_insert, "alerts", "alertid", "actionid", "eventid", "clock", "message",
				"status", "error", "esc_step", "alerttype", (NULL != r_event ? "p_eventid" : NULL),
				NULL);
	}

	now = (int)time(NULL);
	tmp = zbx_dsprintf(tmp, "%s:%s", host->host, NULL == command ? "" : command);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#ifdef HAVE_OPENIPMI
#	define ZBX_IPMI_FIELDS_NUM	4	/* number of selected IPMI-related fields in functions */
						/* get_dynamic_hostid() and execute_commands() */
#else
#	define ZBX_IPMI_FIELDS_NUM	0
#endif

static int	get_dynamic_hostid(const DB_EVENT *event, DC_HOST *host, char *error, size_t max_error_len)
{
	const char	*__function_name = "get_dynamic_hostid";
	DB_RESULT	result;
	DB_ROW		row;
	char		sql[512];	/* do not forget to adjust size if SQLs change */
	size_t		offset;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	offset = zbx_snprintf(sql, sizeof(sql), "select distinct h.hostid,h.proxy_hostid,h.host,h.tls_connect");
#ifdef HAVE_OPENIPMI
	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			/* do not forget to update ZBX_IPMI_FIELDS_NUM if number of selected IPMI fields changes */
			",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password");
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			",h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk");
#endif
	switch (event->source)
	{
		case EVENT_SOURCE_TRIGGERS:
			zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" from functions f,items i,hosts h"
					" where f.itemid=i.itemid"
						" and i.hostid=h.hostid"
						" and h.status=%d"
						" and f.triggerid=" ZBX_FS_UI64,
					HOST_STATUS_MONITORED, event->objectid);

			break;
		case EVENT_SOURCE_DISCOVERY:
			offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" from hosts h,interface i,dservices ds"
					" where h.hostid=i.hostid"
						" and i.ip=ds.ip"
						" and i.useip=1"
						" and h.status=%d",
						HOST_STATUS_MONITORED);

			switch (event->object)
			{
				case EVENT_OBJECT_DHOST:
					zbx_snprintf(sql + offset, sizeof(sql) - offset,
							" and ds.dhostid=" ZBX_FS_UI64, event->objectid);
					break;
				case EVENT_OBJECT_DSERVICE:
					zbx_snprintf(sql + offset, sizeof(sql) - offset,
							" and ds.dserviceid=" ZBX_FS_UI64, event->objectid);
					break;
			}
			break;
		case EVENT_SOURCE_AUTO_REGISTRATION:
			zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" from autoreg_host a,hosts h"
					" where " ZBX_SQL_NULLCMP("a.proxy_hostid", "h.proxy_hostid")
						" and a.host=h.host"
						" and h.status=%d"
						" and h.flags<>%d"
						" and a.autoreg_hostid=" ZBX_FS_UI64,
					HOST_STATUS_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE, event->objectid);
			break;
		default:
			zbx_snprintf(error, max_error_len, "Unsupported event source [%d]", event->source);
			return FAIL;
	}

	host->hostid = 0;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 != host->hostid)
		{
			switch (event->source)
			{
				case EVENT_SOURCE_TRIGGERS:
					zbx_strlcpy(error, "Too many hosts in a trigger expression", max_error_len);
					break;
				case EVENT_SOURCE_DISCOVERY:
					zbx_strlcpy(error, "Too many hosts with same IP addresses", max_error_len);
					break;
			}
			ret = FAIL;
			break;
		}

		ZBX_STR2UINT64(host->hostid, row[0]);
		ZBX_DBROW2UINT64(host->proxy_hostid, row[1]);
		strscpy(host->host, row[2]);
		ZBX_STR2UCHAR(host->tls_connect, row[3]);

#ifdef HAVE_OPENIPMI
		host->ipmi_authtype = (signed char)atoi(row[4]);
		host->ipmi_privilege = (unsigned char)atoi(row[5]);
		strscpy(host->ipmi_username, row[6]);
		strscpy(host->ipmi_password, row[7]);
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		strscpy(host->tls_issuer, row[4 + ZBX_IPMI_FIELDS_NUM]);
		strscpy(host->tls_subject, row[5 + ZBX_IPMI_FIELDS_NUM]);
		strscpy(host->tls_psk_identity, row[6 + ZBX_IPMI_FIELDS_NUM]);
		strscpy(host->tls_psk, row[7 + ZBX_IPMI_FIELDS_NUM]);
#endif
	}
	DBfree_result(result);

	if (FAIL == ret)
	{
		host->hostid = 0;
		*host->host = '\0';
	}
	else if (0 == host->hostid)
	{
		*host->host = '\0';

		zbx_strlcpy(error, "Cannot find a corresponding host", max_error_len);
		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_operation_groupids                                           *
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

static void	execute_commands(const DB_EVENT *event, const DB_EVENT *r_event, const DB_ACKNOWLEDGE *ack,
		zbx_uint64_t actionid, zbx_uint64_t operationid, int esc_step, int macro_type)
{
	const char		*__function_name = "execute_commands";
	DB_RESULT		result;
	DB_ROW			row;
	zbx_db_insert_t		db_insert;
	int			alerts_num = 0;
	char			*buffer = NULL;
	size_t			buffer_alloc = 2 * ZBX_KIBIBYTE, buffer_offset = 0;
	zbx_vector_uint64_t	executed_on_hosts, groupids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	buffer = (char *)zbx_malloc(buffer, buffer_alloc);

	/* get hosts operation's hosts */

	zbx_vector_uint64_create(&groupids);
	get_operation_groupids(operationid, &groupids);

	if (0 != groupids.values_num)
	{
		zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
				/* the 1st 'select' works if remote command target is "Host group" */
				"select distinct h.hostid,h.proxy_hostid,h.host,o.type,o.scriptid,o.execute_on,o.port"
					",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command,h.tls_connect"
#ifdef HAVE_OPENIPMI
				/* do not forget to update ZBX_IPMI_FIELDS_NUM if number of selected IPMI fields changes */
				",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				",h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk"
#endif
				);

		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
				" from opcommand o,hosts_groups hg,hosts h"
				" where o.operationid=" ZBX_FS_UI64
					" and hg.hostid=h.hostid"
					" and h.status=%d"
					" and",
				operationid, HOST_STATUS_MONITORED);

		DBadd_condition_alloc(&buffer, &buffer_alloc, &buffer_offset, "hg.groupid", groupids.values,
				groupids.values_num);

		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, " union ");
	}

	zbx_vector_uint64_destroy(&groupids);

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
			/* the 2nd 'select' works if remote command target is "Host" */
			"select distinct h.hostid,h.proxy_hostid,h.host,o.type,o.scriptid,o.execute_on,o.port"
				",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command,h.tls_connect"
#ifdef HAVE_OPENIPMI
			",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			",h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk"
#endif
			);
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
			" from opcommand o,opcommand_hst oh,hosts h"
			" where o.operationid=oh.operationid"
				" and oh.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union "
			/* the 3rd 'select' works if remote command target is "Current host" */
			"select distinct 0,0,null,o.type,o.scriptid,o.execute_on,o.port"
				",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command,%d",
			operationid, HOST_STATUS_MONITORED, ZBX_TCP_SEC_UNENCRYPTED);
#ifdef HAVE_OPENIPMI
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
				",0,2,null,null");
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
				",null,null,null,null");
#endif
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
			" from opcommand o,opcommand_hst oh"
			" where o.operationid=oh.operationid"
				" and o.operationid=" ZBX_FS_UI64
				" and oh.hostid is null",
			operationid);

	result = DBselect("%s", buffer);

	zbx_free(buffer);
	zbx_vector_uint64_create(&executed_on_hosts);

	while (NULL != (row = DBfetch(result)))
	{
		int			rc = SUCCEED;
		char			error[ALERT_ERROR_LEN_MAX];
		DC_HOST			host;
		zbx_script_t		script;
		zbx_alert_status_t	status = ALERT_STATUS_NOT_SENT;
		zbx_uint64_t		alertid;

		*error = '\0';
		memset(&host, 0, sizeof(host));
		zbx_script_init(&script);

		script.type = (unsigned char)atoi(row[3]);

		if (ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT != script.type)
		{
			script.command = zbx_strdup(script.command, row[12]);
			substitute_simple_macros(&actionid, event, r_event, NULL, NULL,
					NULL, NULL, NULL, ack, &script.command, macro_type, NULL, 0);
		}

		if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT == script.type)
			script.execute_on = (unsigned char)atoi(row[5]);

		ZBX_STR2UINT64(host.hostid, row[0]);
		ZBX_DBROW2UINT64(host.proxy_hostid, row[1]);

		if (ZBX_SCRIPT_EXECUTE_ON_SERVER != script.execute_on)
		{
			if (0 != host.hostid)
			{
				if (FAIL != zbx_vector_uint64_search(&executed_on_hosts, host.hostid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					goto skip;
				}

				zbx_vector_uint64_append(&executed_on_hosts, host.hostid);
				strscpy(host.host, row[2]);
				host.tls_connect = (unsigned char)atoi(row[13]);
#ifdef HAVE_OPENIPMI
				host.ipmi_authtype = (signed char)atoi(row[14]);
				host.ipmi_privilege = (unsigned char)atoi(row[15]);
				strscpy(host.ipmi_username, row[16]);
				strscpy(host.ipmi_password, row[17]);
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				strscpy(host.tls_issuer, row[14 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_subject, row[15 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_psk_identity, row[16 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_psk, row[17 + ZBX_IPMI_FIELDS_NUM]);
#endif
			}
			else if (SUCCEED == (rc = get_dynamic_hostid((NULL != r_event ? r_event : event), &host, error,
						sizeof(error))))
			{
				if (FAIL != zbx_vector_uint64_search(&executed_on_hosts, host.hostid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					goto skip;
				}

				zbx_vector_uint64_append(&executed_on_hosts, host.hostid);
			}
		}

		alertid = DBget_maxid("alerts");

		if (SUCCEED == rc)
		{
			switch (script.type)
			{
				case ZBX_SCRIPT_TYPE_SSH:
					script.authtype = (unsigned char)atoi(row[7]);
					script.publickey = zbx_strdup(script.publickey, row[10]);
					script.privatekey = zbx_strdup(script.privatekey, row[11]);
					/* break; is not missing here */
				case ZBX_SCRIPT_TYPE_TELNET:
					script.port = zbx_strdup(script.port, row[6]);
					script.username = zbx_strdup(script.username, row[8]);
					script.password = zbx_strdup(script.password, row[9]);
					break;
				case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
					ZBX_DBROW2UINT64(script.scriptid, row[4]);
					break;
			}

			if (SUCCEED == (rc = zbx_script_prepare(&script, &host, NULL, error, sizeof(error))))
			{
				if (0 == host.proxy_hostid || ZBX_SCRIPT_EXECUTE_ON_SERVER == script.execute_on)
				{
					rc = zbx_script_execute(&script, &host, NULL, error, sizeof(error));
					status = ALERT_STATUS_SENT;
				}
				else
				{
					if (0 == zbx_script_create_task(&script, &host, alertid, time(NULL)))
						rc = FAIL;
				}
			}
		}

		if (FAIL == rc)
			status = ALERT_STATUS_FAILED;

		add_command_alert(&db_insert, alerts_num++, alertid, &host, event, r_event, actionid, esc_step,
				script.command, status, error);
skip:
		zbx_script_clean(&script);
	}
	DBfree_result(result);
	zbx_vector_uint64_destroy(&executed_on_hosts);

	if (0 < alerts_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#undef ZBX_IPMI_FIELDS_NUM

static void	add_message_alert(const DB_EVENT *event, const DB_EVENT *r_event, zbx_uint64_t actionid, int esc_step,
		zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *subject, const char *message,
		zbx_uint64_t ackid)
{
	const char	*__function_name = "add_message_alert";

	DB_RESULT	result;
	DB_ROW		row;
	int		now, priority, have_alerts = 0, res;
	zbx_db_insert_t	db_insert;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

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
	priority = EVENT_SOURCE_TRIGGERS == event->source ? event->trigger.priority : TRIGGER_SEVERITY_NOT_CLASSIFIED;

	while (NULL != (row = DBfetch(result)))
	{
		int		severity, status;
		const char	*perror;
		char		*period = NULL;

		ZBX_STR2UINT64(mediatypeid, row[0]);
		severity = atoi(row[2]);
		period = zbx_strdup(period, row[3]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &period,
				MACRO_TYPE_COMMON, NULL, 0);

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

		if (SUCCEED != zbx_check_time_period(period, time(NULL), &res))
		{
			status = ALERT_STATUS_FAILED;
			perror = "Invalid media activity period";
		}
		else if (SUCCEED != res)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "will not send message (period)");
			continue;
		}
		else if (MEDIA_TYPE_STATUS_ACTIVE == atoi(row[4]))
		{
			status = ALERT_STATUS_NEW;
			perror = "";
		}
		else
		{
			status = ALERT_STATUS_FAILED;
			perror = "Media type disabled.";
		}

		if (0 == have_alerts)
		{
			have_alerts = 1;
			zbx_db_insert_prepare(&db_insert, "alerts", "alertid", "actionid", "eventid", "userid",
					"clock", "mediatypeid", "sendto", "subject", "message", "status", "error",
					"esc_step", "alerttype", "acknowledgeid",
					(NULL != r_event ? "p_eventid" : NULL), NULL);
		}

		if (NULL != r_event)
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), actionid, r_event->eventid, userid,
					now, mediatypeid, row[1], subject, message, status, perror, esc_step,
					(int)ALERT_TYPE_MESSAGE, ackid, event->eventid);
		}
		else
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), actionid, event->eventid, userid,
					now, mediatypeid, row[1], subject, message, status, perror, esc_step,
					(int)ALERT_TYPE_MESSAGE, ackid);
		}

		zbx_free(period);
	}

	DBfree_result(result);

	if (0 == mediatypeid)
	{
		char	error[MAX_STRING_LEN];

		have_alerts = 1;

		zbx_snprintf(error, sizeof(error), "No media defined for user.");

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: check_operation_conditions                                       *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters: event    - event to check                                      *
 *             actionid - action ID for matching                              *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	check_operation_conditions(const DB_EVENT *event, zbx_uint64_t operationid, unsigned char evaltype)
{
	const char	*__function_name = "check_operation_conditions";

	DB_RESULT	result;
	DB_ROW		row;
	DB_CONDITION	condition;

	int		ret = SUCCEED; /* SUCCEED required for CONDITION_EVAL_TYPE_AND_OR */
	int		cond, exit = 0;
	unsigned char	old_type = 0xff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __function_name, operationid);

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
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	escalation_execute_operations(DB_ESCALATION *escalation, const DB_EVENT *event, const DB_ACTION *action)
{
	const char	*__function_name = "escalation_execute_operations";
	DB_RESULT	result;
	DB_ROW		row;
	int		next_esc_period = 0, esc_period;
	ZBX_USER_MSG	*user_msg = NULL;
	zbx_uint64_t	operationid;
	unsigned char	operationtype, evaltype, operations = 0;
	char		*tmp = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == action->esc_period)
	{
		result = DBselect(
				"select o.operationid,o.operationtype,o.esc_period,o.evaltype,"
					"m.operationid,m.default_msg,m.subject,m.message,m.mediatypeid"
				" from operations o"
					" left join opmessage m"
						" on m.operationid=o.operationid"
				" where o.actionid=" ZBX_FS_UI64
					" and o.operationtype in (%d,%d)"
					" and o.recovery=%d",
				action->actionid,
				OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, ZBX_OPERATION_MODE_NORMAL);
	}
	else
	{
		escalation->esc_step++;

		result = DBselect(
				"select o.operationid,o.operationtype,o.esc_period,o.evaltype,"
					"m.operationid,m.default_msg,m.subject,m.message,m.mediatypeid"
				" from operations o"
					" left join opmessage m"
						" on m.operationid=o.operationid"
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
	}

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(operationid, row[0]);
		operationtype = (unsigned char)atoi(row[1]);

		tmp = zbx_strdup(tmp, row[2]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &tmp, MACRO_TYPE_COMMON,
				NULL, 0);
		if (SUCCEED != is_time_suffix(tmp, &esc_period, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Invalid step duration \"%s\" for operation of action \"%s\","
					" using default operation step duration of the action", tmp, action->name);
			esc_period = 0;
		}

		evaltype = (unsigned char)atoi(row[3]);

		if (0 == esc_period)
			esc_period = action->esc_period;

		if (0 == next_esc_period || next_esc_period > esc_period)
			next_esc_period = esc_period;

		if (SUCCEED == check_operation_conditions(event, operationid, evaltype))
		{
			unsigned char	default_msg;
			char		*subject, *message;
			zbx_uint64_t	mediatypeid;

			zabbix_log(LOG_LEVEL_DEBUG, "Conditions match our event. Execute operation.");

			switch (operationtype)
			{
				case OPERATION_TYPE_MESSAGE:
					if (SUCCEED == DBis_null(row[4]))
						break;

					default_msg = (unsigned char)atoi(row[5]);
					ZBX_DBROW2UINT64(mediatypeid, row[8]);

					if (0 == default_msg)
					{
						subject = row[6];
						message = row[7];
					}
					else
					{
						subject = action->shortdata;
						message = action->longdata;
					}

					add_object_msg(action->actionid, operationid, mediatypeid, &user_msg,
							subject, message, event, NULL, NULL, MACRO_TYPE_MESSAGE_NORMAL);
					break;
				case OPERATION_TYPE_COMMAND:
					execute_commands(event, NULL, NULL, action->actionid, operationid,
							escalation->esc_step, MACRO_TYPE_MESSAGE_NORMAL);
					break;
			}
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions do not match our event. Do not execute operation.");

		operations = 1;
	}
	DBfree_result(result);

	flush_user_msg(&user_msg, escalation->esc_step, event, NULL, action->actionid);

	if (0 == action->esc_period)
	{
		escalation->status = (ZBX_ACTION_RECOVERY_OPERATIONS == action->recovery ? ESCALATION_STATUS_SLEEP :
				ESCALATION_STATUS_COMPLETED);
	}
	else
	{
		if (0 == operations)
		{
			result = DBselect(
					"select null"
					" from operations"
					" where actionid=" ZBX_FS_UI64
						" and esc_step_from>%d"
						" and recovery=%d",
					action->actionid, escalation->esc_step, ZBX_OPERATION_MODE_NORMAL);

			if (NULL != DBfetch(result))
				operations = 1;
			DBfree_result(result);
		}

		if (1 == operations)
		{
			next_esc_period = (0 != next_esc_period) ? next_esc_period : action->esc_period;
			escalation->nextcheck = time(NULL) + next_esc_period;
		}
		else
		{
			escalation->status = (ZBX_ACTION_RECOVERY_OPERATIONS == action->recovery ?
					ESCALATION_STATUS_SLEEP : ESCALATION_STATUS_COMPLETED);
		}
	}

	/* schedule nextcheck for sleeping escalations */
	if (ESCALATION_STATUS_SLEEP == escalation->status)
		escalation->nextcheck = time(NULL) + SEC_PER_MIN;

	zbx_free(tmp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_execute_recovery_operations                           *
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
static void	escalation_execute_recovery_operations(const DB_EVENT *event, const DB_EVENT *r_event,
		const DB_ACTION *action)
{
	const char	*__function_name = "escalation_execute_recovery_operations";
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_USER_MSG	*user_msg = NULL;
	zbx_uint64_t	operationid;
	unsigned char	operationtype, default_msg;
	char		*subject, *message;
	zbx_uint64_t	mediatypeid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select o.operationid,o.operationtype,"
				"m.operationid,m.default_msg,m.subject,m.message,m.mediatypeid"
			" from operations o"
				" left join opmessage m"
					" on m.operationid=o.operationid"
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
				if (SUCCEED == DBis_null(row[2]))
					break;

				ZBX_STR2UCHAR(default_msg, row[3]);
				ZBX_DBROW2UINT64(mediatypeid, row[6]);

				if (0 == default_msg)
				{
					subject = row[4];
					message = row[5];
				}
				else
				{
					subject = action->r_shortdata;
					message = action->r_longdata;
				}

				add_object_msg(action->actionid, operationid, mediatypeid, &user_msg, subject,
						message, event, r_event, NULL, MACRO_TYPE_MESSAGE_RECOVERY);
				break;
			case OPERATION_TYPE_RECOVERY_MESSAGE:
				if (SUCCEED == DBis_null(row[2]))
					break;

				ZBX_STR2UCHAR(default_msg, row[3]);

				if (0 == default_msg)
				{
					subject = row[4];
					message = row[5];
				}
				else
				{
					subject = action->r_shortdata;
					message = action->r_longdata;
				}

				add_sentusers_msg(&user_msg, action->actionid, event, r_event, subject, message, NULL);
				break;
			case OPERATION_TYPE_COMMAND:
				execute_commands(event, r_event, NULL, action->actionid, operationid, 1,
						MACRO_TYPE_MESSAGE_RECOVERY);
				break;
		}
	}
	DBfree_result(result);

	flush_user_msg(&user_msg, 1, event, r_event, action->actionid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_execute_acknowledge_operations                        *
 *                                                                            *
 * Purpose: execute escalation acknowledge operations                         *
 *                                                                            *
 * Parameters: event  - [IN] the event                                        *
 *             action - [IN] the action                                       *
 *             ack    - [IN] the acknowledge                                  *
 *                                                                            *
 * Comments: Action acknowledge operations have a single escalation step, so  *
 *           alerts created by escalation acknowledge operations must have    *
 *           esc_step field set to 1.                                         *
 *                                                                            *
 ******************************************************************************/
static void	escalation_execute_acknowledge_operations(const DB_EVENT *event, const DB_EVENT *r_event,
		const DB_ACTION *action, const DB_ACKNOWLEDGE *ack)
{
	const char	*__function_name = "escalation_execute_acknowledge_operations";
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_USER_MSG	*user_msg = NULL;
	zbx_uint64_t	operationid, mediatypeid;
	unsigned char	operationtype, default_msg;
	char		*subject, *message;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select o.operationid,o.operationtype,m.operationid,m.default_msg,"
				"m.subject,m.message,m.mediatypeid"
			" from operations o"
				" left join opmessage m"
					" on m.operationid=o.operationid"
			" where o.actionid=" ZBX_FS_UI64
				" and o.operationtype in (%d,%d,%d)"
				" and o.recovery=%d",
			action->actionid,
			OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, OPERATION_TYPE_ACK_MESSAGE,
			ZBX_OPERATION_MODE_ACK);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(operationid, row[0]);
		operationtype = (unsigned char)atoi(row[1]);

		switch (operationtype)
		{
			case OPERATION_TYPE_MESSAGE:
				if (SUCCEED == DBis_null(row[2]))
					break;

				ZBX_STR2UCHAR(default_msg, row[3]);
				ZBX_DBROW2UINT64(mediatypeid, row[6]);

				if (0 == default_msg)
				{
					subject = row[4];
					message = row[5];
				}
				else
				{
					subject = action->ack_shortdata;
					message = action->ack_longdata;
				}

				add_object_msg(action->actionid, operationid, mediatypeid, &user_msg, subject,
						message, event, r_event, ack, MACRO_TYPE_MESSAGE_ACK);
				break;
			case OPERATION_TYPE_ACK_MESSAGE:
				if (SUCCEED == DBis_null(row[2]))
					break;

				ZBX_STR2UCHAR(default_msg, row[3]);
				ZBX_DBROW2UINT64(mediatypeid, row[6]);

				if (0 == default_msg)
				{
					subject = row[4];
					message = row[5];
				}
				else
				{
					subject = action->ack_shortdata;
					message = action->ack_longdata;
				}

				add_sentusers_msg(&user_msg, action->actionid, event, r_event, subject, message, ack);
				add_sentusers_ack_msg(&user_msg, action->actionid, mediatypeid, event, r_event, ack,
						subject, message);
				break;
			case OPERATION_TYPE_COMMAND:
				execute_commands(event, r_event, ack, action->actionid, operationid, 1,
						MACRO_TYPE_MESSAGE_ACK);
				break;
		}
	}
	DBfree_result(result);

	flush_user_msg(&user_msg, 1, event, NULL, action->actionid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: check_escalation_trigger                                         *
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
 *             maintenance - [OUT] HOST_MAINTENANCE_STATUS_ON - if at least   *
 *                                 one of hosts used in expression is in      *
 *                                 maintenance mode,                          *
 *                                 HOST_MAINTENANCE_STATUS_OFF - otherwise    *
 *             error       - [OUT] message in case escalation is cancelled    *
 *                                                                            *
 * Return value: FAIL if dependent trigger is in PROBLEM state                *
 *               SUCCEED otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	check_escalation_trigger(zbx_uint64_t triggerid, unsigned char source, unsigned char *ignore,
		unsigned char *maintenance, char **error)
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

	get_functionids(&functionids, trigger.expression_orig);

	functions = (DC_FUNCTION *)zbx_malloc(functions, sizeof(DC_FUNCTION) * functionids.values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * functionids.values_num);

	DCconfig_get_functions_by_functionids(functions, functionids.values, errcodes, functionids.values_num);

	for (i = 0; i < functionids.values_num; i++)
	{
		if (SUCCEED == errcodes[i])
			zbx_vector_uint64_append(&itemids, functions[i].itemid);
	}

	DCconfig_clean_functions(functions, errcodes, functionids.values_num);
	zbx_free(functions);

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	items = (DC_ITEM *)zbx_malloc(items, sizeof(DC_ITEM) * itemids.values_num);
	errcodes = (int *)zbx_realloc(errcodes, sizeof(int) * itemids.values_num);

	DCconfig_get_items_by_itemids(items, itemids.values, errcodes, itemids.values_num);

	*maintenance = HOST_MAINTENANCE_STATUS_OFF;

	for (i = 0; i < itemids.values_num; i++)
	{
		if (SUCCEED != errcodes[i])
		{
			*error = zbx_dsprintf(*error, "item id:" ZBX_FS_UI64 " deleted.", itemids.values[i]);
			break;
		}

		if (ITEM_STATUS_DISABLED == items[i].status)
		{
			*error = zbx_dsprintf(*error, "item \"%s\" disabled.", items[i].key_orig);
			break;
		}
		if (HOST_STATUS_NOT_MONITORED == items[i].host.status)
		{
			*error = zbx_dsprintf(*error, "host \"%s\" disabled.", items[i].host.host);
			break;
		}

		if (HOST_MAINTENANCE_STATUS_ON == items[i].host.maintenance_status)
			*maintenance = HOST_MAINTENANCE_STATUS_ON;
	}

	DCconfig_clean_items(items, errcodes, itemids.values_num);
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
		default:
			return "unknown";
	}
}

/******************************************************************************
 *                                                                            *
 * Function: check_escalation                                                 *
 *                                                                            *
 * Purpose: check whether escalation must be cancelled, deleted, skipped or   *
 *          processed.                                                        *
 *                                                                            *
 * Parameters: escalation - [IN]  escalation to check                         *
 *             action     - [IN]  action responsible for the escalation       *
 *             error      - [OUT] message in case escalation is cancelled     *
 *                                                                            *
 * Return value: ZBX_ESCALATION_CANCEL  - the relevant event, item, trigger   *
 *                                        or host is disabled or deleted      *
 *               ZBX_ESCALATION_DELETE  - escalations was created and         *
 *                                        recovered during maintenance        *
 *               ZBX_ESCALATION_SKIP    - escalation is paused during         *
 *                                        maintenance or dependable trigger   *
 *                                        in problem state                    *
 *               ZBX_ESCALATION_PROCESS - otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	check_escalation(const DB_ESCALATION *escalation, const DB_ACTION *action, const DB_EVENT *event,
		char **error)
{
	const char	*__function_name = "check_escalation";
	DC_ITEM		item;
	int		errcode, ret = ZBX_ESCALATION_CANCEL;
	unsigned char	maintenance = HOST_MAINTENANCE_STATUS_OFF, skip = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		if (SUCCEED != check_escalation_trigger(escalation->triggerid, event->source, &skip, &maintenance, error))
			goto out;
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
				*error = zbx_dsprintf(*error, "item \"%s\" disabled.", item.key_orig);
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
		}
	}

	if (EVENT_SOURCE_TRIGGERS == action->eventsource &&
			ACTION_MAINTENANCE_MODE_PAUSE == action->maintenance_mode &&
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

		/* skip paused escalations created before maintenance period */
		/* until maintenance ends or the escalations are recovered   */
		if (0 == escalation->r_eventid)
		{
			ret = ZBX_ESCALATION_SKIP;
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:'%s'", __function_name, check_escalation_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));


	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_log_cancel_warning                                    *
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
		zabbix_log(LOG_LEVEL_WARNING, "escalation cancelled: %s", error);
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_cancel                                                *
 *                                                                            *
 * Purpose: cancel escalation with the specified error message                *
 *                                                                            *
 * Parameters: escalation - [IN/OUT] the escalation to cancel                 *
 *             action     - [IN]     the action                               *
 *             event      - [IN]     the event                                *
 *             error      - [IN]     the error message                        *
 *                                                                            *
 ******************************************************************************/
static void	escalation_cancel(DB_ESCALATION *escalation, const DB_ACTION *action, const DB_EVENT *event,
		const char *error)
{
	const char	*__function_name = "escalation_cancel";
	ZBX_USER_MSG	*user_msg = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	if (0 != escalation->esc_step)
	{
		char	*message;

		message = zbx_dsprintf(NULL, "NOTE: Escalation cancelled: %s\n%s", error, action->longdata);
		add_sentusers_msg(&user_msg, action->actionid, event, NULL, action->shortdata, message, NULL);
		flush_user_msg(&user_msg, escalation->esc_step, event, NULL, action->actionid);

		zbx_free(message);
	}

	escalation_log_cancel_warning(escalation, error);
	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_execute                                               *
 *                                                                            *
 * Purpose: execute next escalation step                                      *
 *                                                                            *
 * Parameters: escalation - [IN/OUT] the escalation to execute                *
 *             action     - [IN]     the action                               *
 *             event      - [IN]     the event                                *
 *                                                                            *
 ******************************************************************************/
static void	escalation_execute(DB_ESCALATION *escalation, const DB_ACTION *action, const DB_EVENT *event)
{
	const char	*__function_name = "escalation_execute";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	escalation_execute_operations(escalation, event, action);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_recover                                               *
 *                                                                            *
 * Purpose: process escalation recovery                                       *
 *                                                                            *
 * Parameters: escalation - [IN/OUT] the escalation to recovery               *
 *             action     - [IN]     the action                               *
 *             event      - [IN]     the event                                *
 *             r_event    - [IN]     the recovery event                       *
 *                                                                            *
 ******************************************************************************/
static void	escalation_recover(DB_ESCALATION *escalation, const DB_ACTION *action, const DB_EVENT *event,
		const DB_EVENT *r_event)
{
	const char	*__function_name = "escalation_recover";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	escalation_execute_recovery_operations(event, r_event, action);

	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_acknowledge                                           *
 *                                                                            *
 * Purpose: process escalation acknowledge                                    *
 *                                                                            *
 * Parameters: escalation - [IN/OUT] the escalation to recovery               *
 *             action     - [IN]     the action                               *
 *             event      - [IN]     the event                                *
 *             r_event    - [IN]     the recovery event                       *
 *                                                                            *
 ******************************************************************************/
static void	escalation_acknowledge(DB_ESCALATION *escalation, const DB_ACTION *action, const DB_EVENT *event,
		const DB_EVENT *r_event)
{
	const char	*__function_name = "escalation_acknowledge";

	DB_ROW		row;
	DB_RESULT	result;
	DB_ACKNOWLEDGE	ack;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " acknowledgeid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, escalation->acknowledgeid,
			zbx_escalation_status_string(escalation->status));

	memset(&ack, 0, sizeof(ack));

	result = DBselect(
			"select message,userid,clock from acknowledges"
			" where acknowledgeid=" ZBX_FS_UI64,
			escalation->acknowledgeid);

	if (NULL != (row = DBfetch(result)))
	{
		ack.message = zbx_strdup(NULL, row[0]);
		ZBX_STR2UINT64(ack.userid, row[1]);
		ack.clock = atoi(row[2]);
		ack.acknowledgeid = escalation->acknowledgeid;

		escalation_execute_acknowledge_operations(event, r_event, action, &ack);
	}

	zbx_free(ack.message);
	DBfree_result(result);

	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
 * Function: add_ack_escalation_r_eventids                                    *
 *                                                                            *
 * Purpose: check if acknowledgement events of current escalation has related *
 *          recovery events and add those recovery event IDs to array of      *
 *          event IDs if this escalation                                      *
 *                                                                            *
 * Parameters: escalations - [IN] array of escalations to be processed        *
 *             eventids    - [OUT] array of events of current escalation      *
 *             event_pairs - [OUT] the array of event ID and recovery event   *
 *                                 pairs                                      *
 *                                                                            *
 * Comments: additionally acknowledgement event IDs are mapped with related   *
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

		zbx_vector_uint64_append_array(eventids, r_eventids.values, r_eventids.values_num);
	}

	zbx_vector_uint64_destroy(&ack_eventids);
	zbx_vector_uint64_destroy(&r_eventids);
}

static int	process_db_escalations(int now, int *nextcheck, zbx_vector_ptr_t *escalations,
		zbx_vector_uint64_t *eventids, zbx_vector_uint64_t *actionids)
{
	int				i, ret;
	zbx_vector_uint64_t		escalationids;
	zbx_vector_ptr_t		diffs, actions, events;
	zbx_escalation_diff_t		*diff;
	zbx_vector_uint64_pair_t	event_pairs;

	zbx_vector_uint64_create(&escalationids);
	zbx_vector_ptr_create(&diffs);
	zbx_vector_ptr_create(&actions);
	zbx_vector_ptr_create(&events);
	zbx_vector_uint64_pair_create(&event_pairs);

	add_ack_escalation_r_eventids(escalations, eventids, &event_pairs);

	get_db_actions_info(actionids, &actions);
	zbx_db_get_events_by_eventids(eventids, &events);

	for (i = 0; i < escalations->values_num; i++)
	{
		int		index;
		char		*error = NULL;
		DB_ACTION	*action;
		DB_EVENT	*event, *r_event;
		DB_ESCALATION	*escalation;

		escalation = (DB_ESCALATION *)escalations->values[i];

		if (FAIL == (index = zbx_vector_ptr_bsearch(&actions, &escalation->actionid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			error = zbx_dsprintf(error, "action id:" ZBX_FS_UI64 " deleted", escalation->actionid);
			goto cancel_warning;
		}

		action = (DB_ACTION *)actions.values[index];

		if (ACTION_STATUS_ACTIVE != action->status)
		{
			error = zbx_dsprintf(error, "action '%s' disabled.", action->name);
			goto cancel_warning;
		}

		if (FAIL == (index = zbx_vector_ptr_bsearch(&events, &escalation->eventid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			error = zbx_dsprintf(error, "event id:" ZBX_FS_UI64 " deleted.", escalation->eventid);
			goto cancel_warning;
		}

		event = (DB_EVENT *)events.values[index];

		if (EVENT_SOURCE_TRIGGERS == event->source && 0 == event->trigger.triggerid)
		{
			error = zbx_dsprintf(error, "trigger id:" ZBX_FS_UI64 " deleted.", event->objectid);
			goto cancel_warning;
		}

		if (0 != escalation->r_eventid)
		{
			if (FAIL == (index = zbx_vector_ptr_bsearch(&events, &escalation->r_eventid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				error = zbx_dsprintf(error, "event id:" ZBX_FS_UI64 " deleted.", escalation->r_eventid);
				goto cancel_warning;
			}

			r_event = (DB_EVENT *)events.values[index];

			if (EVENT_SOURCE_TRIGGERS == event->source && 0 == r_event->trigger.triggerid)
			{
				error = zbx_dsprintf(error, "trigger id:" ZBX_FS_UI64 " deleted.", r_event->objectid);
				goto cancel_warning;
			}
		}
		else
			r_event = NULL;

		/* Handle escalation taking into account status of items, triggers, hosts, */
		/* maintenance and trigger dependencies.                                   */
		switch (check_escalation(escalation, action, event, &error))
		{
			case ZBX_ESCALATION_CANCEL:
				escalation_cancel(escalation, action, event, error);
				zbx_free(error);
				/* break; is not missing here */
			case ZBX_ESCALATION_DELETE:
				zbx_vector_uint64_append(&escalationids, escalation->escalationid);
				/* break; is not missing here */
			case ZBX_ESCALATION_SKIP:
				goto cancel_warning;	/* error is NULL on skip */
			case ZBX_ESCALATION_PROCESS:
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
		}

		/* Execute operations and recovery operations, mark changes in 'diffs' for batch saving in DB below. */
		diff = escalation_create_diff(escalation);

		if (0 != escalation->acknowledgeid)
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
					r_event = (DB_EVENT *)events.values[index];
				}

			}

			escalation_acknowledge(escalation, action, event, r_event);
		}
		else if (NULL != r_event)
		{
			if (0 == escalation->esc_step)
				escalation_execute(escalation, action, event);

			escalation_recover(escalation, action, event, r_event);
		}
		else if (escalation->nextcheck <= now)
		{
			if (ESCALATION_STATUS_ACTIVE == escalation->status)
			{
				escalation_execute(escalation, action, event);
			}
			else if (ESCALATION_STATUS_SLEEP == escalation->status)
			{
				escalation->nextcheck = time(NULL) + SEC_PER_MIN;
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
cancel_warning:
		if (NULL != error)
		{
			escalation_log_cancel_warning(escalation, error);
			zbx_vector_uint64_append(&escalationids, escalation->escalationid);
			zbx_free(error);
		}
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

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

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
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cnextcheck=%d", separator,
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
						diff->status);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where escalationid=" ZBX_FS_UI64 ";\n",
					diff->escalationid);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)	/* in ORACLE always present begin..end; */
			DBexecute("%s", sql);

		zbx_free(sql);
	}

	/* 3. Delete cancelled, completed escalations. */
	if (0 != escalationids.values_num)
		DBexecute_multiple_query("delete from escalations where", "escalationid", &escalationids);

	DBcommit();
out:
	zbx_vector_ptr_clear_ext(&diffs, zbx_ptr_free);
	zbx_vector_ptr_destroy(&diffs);

	zbx_vector_ptr_clear_ext(&actions, (zbx_clean_func_t)free_db_action);
	zbx_vector_ptr_destroy(&actions);

	zbx_vector_ptr_clear_ext(&events, (zbx_clean_func_t)zbx_db_free_event);
	zbx_vector_ptr_destroy(&events);

	zbx_vector_uint64_pair_destroy(&event_pairs);

	ret = escalationids.values_num; /* performance metric */

	zbx_vector_uint64_destroy(&escalationids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_escalations                                              *
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
 *           EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTO_REGISTRATION events,   *
 *           this function handles message and command operations for these   *
 *           events while host, group, template operations are handled        *
 *           in process_actions().                                            *
 *                                                                            *
 ******************************************************************************/
static int	process_escalations(int now, int *nextcheck, unsigned int escalation_source)
{
	const char		*__function_name = "process_escalations";

	int			ret = 0;
	DB_RESULT		result;
	DB_ROW			row;
	char			*filter = NULL;
	size_t			filter_alloc = 0, filter_offset = 0;

	zbx_vector_ptr_t	escalations;
	zbx_vector_uint64_t	actionids, eventids;

	DB_ESCALATION		*escalation;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
			zbx_strcpy_alloc(&filter, &filter_alloc, &filter_offset, "itemid is not null");
			if (1 < CONFIG_ESCALATOR_FORKS)
			{
				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						" and " ZBX_SQL_MOD(itemid, %d) "=%d",
						CONFIG_ESCALATOR_FORKS, process_num - 1);
			}
			break;
		case ZBX_ESCALATION_SOURCE_DEFAULT:
			zbx_strcpy_alloc(&filter, &filter_alloc, &filter_offset,
					"triggerid is null and itemid is null");
			if (1 < CONFIG_ESCALATOR_FORKS)
			{
				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						" and " ZBX_SQL_MOD(escalationid, %d) "=%d",
						CONFIG_ESCALATOR_FORKS, process_num - 1);
			}
			break;
	}

	result = DBselect("select escalationid,actionid,triggerid,eventid,r_eventid,nextcheck,esc_step,status,itemid,"
					"acknowledgeid"
				" from escalations"
				" where %s"
				" order by actionid,triggerid,itemid,escalationid", filter);
	zbx_free(filter);

	while (NULL != (row = DBfetch(result)))
	{
		int		esc_nextcheck;
		zbx_uint64_t	esc_r_eventid;

		esc_nextcheck = atoi(row[5]);
		ZBX_DBROW2UINT64(esc_r_eventid, row[4]);

		/* Skip escalations that must be checked later and that are not recovered */
		/* (corresponding OK event hasn't occurred yet, see process_actions()).   */
		if (esc_nextcheck > now && 0 == esc_r_eventid)
		{
			if (esc_nextcheck < *nextcheck)
				*nextcheck = esc_nextcheck;

			continue;
		}

		escalation = (DB_ESCALATION *)zbx_malloc(NULL, sizeof(DB_ESCALATION));
		escalation->nextcheck = esc_nextcheck;
		escalation->r_eventid = esc_r_eventid;
		ZBX_STR2UINT64(escalation->escalationid, row[0]);
		ZBX_STR2UINT64(escalation->actionid, row[1]);
		ZBX_DBROW2UINT64(escalation->triggerid, row[2]);
		ZBX_DBROW2UINT64(escalation->eventid, row[3]);
		escalation->esc_step = atoi(row[6]);
		escalation->status = atoi(row[7]);
		ZBX_DBROW2UINT64(escalation->itemid, row[8]);
		ZBX_DBROW2UINT64(escalation->acknowledgeid, row[9]);

		zbx_vector_ptr_append(&escalations, escalation);
		zbx_vector_uint64_append(&actionids, escalation->actionid);
		zbx_vector_uint64_append(&eventids, escalation->eventid);

		if (0 < escalation->r_eventid)
			zbx_vector_uint64_append(&eventids, escalation->r_eventid);

		if (escalations.values_num >= ZBX_ESCALATIONS_PER_STEP)
		{
			ret += process_db_escalations(now, nextcheck, &escalations, &eventids, &actionids);
			zbx_vector_ptr_clear_ext(&escalations, zbx_ptr_free);
			zbx_vector_uint64_clear(&actionids);
			zbx_vector_uint64_clear(&eventids);
		}
	}
	DBfree_result(result);

	if (0 < escalations.values_num)
	{
		ret += process_db_escalations(now, nextcheck, &escalations, &eventids, &actionids);
		zbx_vector_ptr_clear_ext(&escalations, zbx_ptr_free);
	}

	zbx_vector_ptr_destroy(&escalations);
	zbx_vector_uint64_destroy(&actionids);
	zbx_vector_uint64_destroy(&eventids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret; /* performance metric */
}

/******************************************************************************
 *                                                                            *
 * Function: main_escalator_loop                                              *
 *                                                                            *
 * Purpose: periodically check table escalations and generate alerts          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(escalator_thread, args)
{
	int	now, nextcheck, sleeptime = -1, escalations_count = 0, old_escalations_count = 0;
	double	sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t	last_stat_time;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_handle_log();

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [processed %d escalations in " ZBX_FS_DBL
					" sec, processing escalations]", get_process_type_string(process_type),
					process_num, old_escalations_count, old_total_sec);
		}

		sec = zbx_time();
		nextcheck = time(NULL) + CONFIG_ESCALATOR_FREQUENCY;
		escalations_count += process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_TRIGGER);
		escalations_count += process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_ITEM);
		escalations_count += process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_DEFAULT);

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

#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		zbx_update_resolver_conf();	/* handle /etc/resolv.conf update */
#endif
	}
}
