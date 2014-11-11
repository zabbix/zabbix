/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "escalator.h"
#include "../operations.h"
#include "../actions.h"
#include "../scripts.h"

#define CONFIG_ESCALATOR_FREQUENCY	3

typedef struct
{
	zbx_uint64_t	userid;
	zbx_uint64_t	mediatypeid;
	char		*subject;
	char		*message;
	void		*next;
}
ZBX_USER_MSG;

extern unsigned char	process_type, daemon_type;
extern int		server_num, process_num;

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
static int	check_perm2system(zbx_uint64_t userid)
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

/******************************************************************************
 *                                                                            *
 * Function: get_host_permission                                              *
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
static int	get_host_permission(zbx_uint64_t userid, zbx_uint64_t hostid)
{
	const char	*__function_name = "get_host_permission";
	DB_RESULT	result;
	DB_ROW		row;
	int		user_type = -1, perm = PERM_DENY;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select type from users where userid=" ZBX_FS_UI64,
			userid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		user_type = atoi(row[0]);

	DBfree_result(result);

	if (-1 == user_type)
		goto out;

	if (USER_TYPE_SUPER_ADMIN == user_type)
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	result = DBselect(
			"select min(r.permission)"
			" from rights r,hosts_groups hg,users_groups ug"
			" where r.groupid=ug.usrgrpid"
				" and r.id=hg.groupid"
				" and hg.hostid=" ZBX_FS_UI64
				" and ug.userid=" ZBX_FS_UI64,
			hostid, userid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		perm = atoi(row[0]);

	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_permission_string(perm));

	return perm;
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
static int	get_trigger_permission(zbx_uint64_t userid, zbx_uint64_t triggerid)
{
	const char	*__function_name = "get_trigger_permission";
	DB_RESULT	result;
	DB_ROW		row;
	int		perm = PERM_DENY, host_perm;
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct i.hostid"
			" from items i,functions f"
			" where i.itemid=f.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		host_perm = get_host_permission(userid, hostid);

		if (perm < host_perm)
			perm = host_perm;
	}
	DBfree_result(result);

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
static int	get_item_permission(zbx_uint64_t userid, zbx_uint64_t itemid)
{
	const char	*__function_name = "get_item_permission";
	DB_RESULT	result;
	DB_ROW		row;
	int		perm = PERM_DENY;
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select hostid from items where itemid=" ZBX_FS_UI64, itemid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		perm = get_host_permission(userid, hostid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_permission_string(perm));

	return perm;
}

static void	add_user_msg(zbx_uint64_t userid, zbx_uint64_t mediatypeid, ZBX_USER_MSG **user_msg,
		const char *subject, const char *message)
{
	const char	*__function_name = "add_user_msg";
	ZBX_USER_MSG	*p;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	p = *user_msg;

	while (NULL != p)
	{
		if (p->userid == userid && p->mediatypeid == mediatypeid &&
				0 == strcmp(p->subject, subject) && 0 == strcmp(p->message, message))
			break;

		p = p->next;
	}

	if (NULL == p)
	{
		p = zbx_malloc(p, sizeof(ZBX_USER_MSG));

		p->userid = userid;
		p->mediatypeid = mediatypeid;
		p->subject = zbx_strdup(NULL, subject);
		p->message = zbx_strdup(NULL, message);
		p->next = *user_msg;

		*user_msg = p;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_object_msg(zbx_uint64_t actionid, zbx_uint64_t operationid, zbx_uint64_t mediatypeid,
		ZBX_USER_MSG **user_msg, const char *subject, const char *message, DB_EVENT *event)
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

		if (SUCCEED != check_perm2system(userid))
			continue;

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (PERM_READ > get_trigger_permission(userid, event->objectid))
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

		substitute_simple_macros(&actionid, event, NULL, &userid, NULL, NULL, NULL, NULL,
				&subject_dyn, MACRO_TYPE_MESSAGE_NORMAL, NULL, 0);
		substitute_simple_macros(&actionid, event, NULL, &userid, NULL, NULL, NULL, NULL,
				&message_dyn, MACRO_TYPE_MESSAGE_NORMAL, NULL, 0);

		add_user_msg(userid, mediatypeid, user_msg, subject_dyn, message_dyn);

		zbx_free(subject_dyn);
		zbx_free(message_dyn);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_command_alert(zbx_db_insert_t *db_insert, int alerts_num, const DC_HOST *host, zbx_uint64_t eventid,
		zbx_uint64_t actionid, int esc_step, const char *command, zbx_alert_status_t status, const char *error)
{
	const char	*__function_name = "add_command_alert";
	int		now, alerttype = ALERT_TYPE_COMMAND, alert_status = status;
	char		*tmp = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == alerts_num)
	{
		zbx_db_insert_prepare(db_insert, "alerts", "alertid", "actionid", "eventid", "clock", "message",
				"status", "error", "esc_step", "alerttype", NULL);
	}

	now = (int)time(NULL);
	tmp = zbx_dsprintf(tmp, "%s:%s", host->host, NULL == command ? "" : command);

	zbx_db_insert_add_values(db_insert, __UINT64_C(0), actionid, eventid, now, tmp, alert_status, error, esc_step,
			(int)alerttype);

	zbx_free(tmp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	get_dynamic_hostid(DB_EVENT *event, DC_HOST *host, char *error, size_t max_error_len)
{
	const char	*__function_name = "get_dynamic_hostid";
	DB_RESULT	result;
	DB_ROW		row;
	char		sql[512];
	size_t		offset;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	offset = zbx_snprintf(sql, sizeof(sql), "select distinct h.hostid,h.host");
#ifdef HAVE_OPENIPMI
	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password");
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
		strscpy(host->host, row[1]);
#ifdef HAVE_OPENIPMI
		host->ipmi_authtype = (signed char)atoi(row[2]);
		host->ipmi_privilege = (unsigned char)atoi(row[3]);
		strscpy(host->ipmi_username, row[4]);
		strscpy(host->ipmi_password, row[5]);
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

static void	execute_commands(DB_EVENT *event, zbx_uint64_t actionid, zbx_uint64_t operationid, int esc_step)
{
	const char	*__function_name = "execute_commands";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_db_insert_t	db_insert;
	int		alerts_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct h.hostid,h.host,o.type,o.scriptid,o.execute_on,o.port"
				",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command"
#ifdef HAVE_OPENIPMI
				",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
			" from opcommand o,opcommand_grp og,hosts_groups hg,hosts h"
			" where o.operationid=og.operationid"
				" and og.groupid=hg.groupid"
				" and hg.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union "
			"select distinct h.hostid,h.host,o.type,o.scriptid,o.execute_on,o.port"
				",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command"
#ifdef HAVE_OPENIPMI
				",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
			" from opcommand o,opcommand_hst oh,hosts h"
			" where o.operationid=oh.operationid"
				" and oh.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union "
			"select distinct 0,null,o.type,o.scriptid,o.execute_on,o.port"
				",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command"
#ifdef HAVE_OPENIPMI
				",0,2,null,null"
#endif
			" from opcommand o,opcommand_hst oh"
			" where o.operationid=oh.operationid"
				" and o.operationid=" ZBX_FS_UI64
				" and oh.hostid is null",
			operationid, HOST_STATUS_MONITORED,
			operationid, HOST_STATUS_MONITORED,
			operationid);

	while (NULL != (row = DBfetch(result)))
	{
		int			rc = SUCCEED;
		char			error[ALERT_ERROR_LEN_MAX];
		DC_HOST			host;
		zbx_script_t		script;
		zbx_alert_status_t	status;

		*error = '\0';
		memset(&host, 0, sizeof(host));
		zbx_script_init(&script);

		ZBX_STR2UINT64(host.hostid, row[0]);

		if (0 != host.hostid)
		{
			strscpy(host.host, row[1]);
#ifdef HAVE_OPENIPMI
			host.ipmi_authtype = (signed char)atoi(row[12]);
			host.ipmi_privilege = (unsigned char)atoi(row[13]);
			strscpy(host.ipmi_username, row[14]);
			strscpy(host.ipmi_password, row[15]);
#endif
		}
		else
			rc = get_dynamic_hostid(event, &host, error, sizeof(error));

		script.type = (unsigned char)atoi(row[2]);

		if (ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT != script.type)
		{
			script.command = zbx_strdup(script.command, row[11]);
			substitute_simple_macros(&actionid, event, NULL, NULL, NULL, NULL, NULL, NULL,
					&script.command, MACRO_TYPE_MESSAGE_NORMAL, NULL, 0);
		}

		if (SUCCEED == rc)
		{
			switch (script.type)
			{
				case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
					script.execute_on = (unsigned char)atoi(row[4]);
					break;
				case ZBX_SCRIPT_TYPE_SSH:
					script.authtype = (unsigned char)atoi(row[6]);
					script.publickey = zbx_strdup(script.publickey, row[9]);
					script.privatekey = zbx_strdup(script.privatekey, row[10]);
					/* break; is not missing here */
				case ZBX_SCRIPT_TYPE_TELNET:
					script.port = zbx_strdup(script.port, row[5]);
					script.username = zbx_strdup(script.username, row[7]);
					script.password = zbx_strdup(script.password, row[8]);
					break;
				case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
					ZBX_DBROW2UINT64(script.scriptid, row[3]);
					break;
			}

			rc = zbx_execute_script(&host, &script, NULL, error, sizeof(error));
		}

		status = (SUCCEED != rc ? ALERT_STATUS_FAILED : ALERT_STATUS_SENT);

		add_command_alert(&db_insert, alerts_num++, &host, event->eventid, actionid, esc_step, script.command,
				status, error);

		zbx_script_clean(&script);
	}
	DBfree_result(result);

	if (0 < alerts_num)
	{
		zbx_db_insert_autoincrement(&db_insert, "alertid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_message_alert(DB_ESCALATION *escalation, DB_EVENT *event, DB_EVENT *r_event, DB_ACTION *action,
		zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *subject, const char *message)
{
	const char	*__function_name = "add_message_alert";

	DB_RESULT	result;
	DB_ROW		row;
	int		now, severity, medias_num = 0, status;
	char		error[MAX_STRING_LEN], *perror;
	DB_EVENT	*c_event;
	zbx_db_insert_t	db_insert;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	c_event = (NULL != r_event ? r_event : event);
	now = time(NULL);

	if (0 == mediatypeid)
	{
		result = DBselect(
				"select m.mediatypeid,m.sendto,m.severity,m.period,mt.status"
				" from media m,media_type mt"
				" where m.mediatypeid=mt.mediatypeid"
					" and m.active=%d"
					" and m.userid=" ZBX_FS_UI64,
				MEDIA_STATUS_ACTIVE, userid);
	}
	else
	{
		result = DBselect(
				"select m.mediatypeid,m.sendto,m.severity,m.period,mt.status"
				" from media m,media_type mt"
				" where m.mediatypeid=mt.mediatypeid"
					" and m.active=%d"
					" and m.userid=" ZBX_FS_UI64
					" and m.mediatypeid=" ZBX_FS_UI64,
				MEDIA_STATUS_ACTIVE, userid, mediatypeid);
	}

	mediatypeid = 0;

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(mediatypeid, row[0]);
		severity = atoi(row[2]);

		zabbix_log(LOG_LEVEL_DEBUG, "trigger severity:%d, media severity:%d, period:'%s'",
				(int)c_event->trigger.priority, severity, row[3]);

		if (((1 << c_event->trigger.priority) & severity) == 0)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "will not send message (severity)");
			continue;
		}

		if (FAIL == check_time_period(row[3], (time_t)0))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "will not send message (period)");
			continue;
		}

		if (MEDIA_TYPE_STATUS_ACTIVE == atoi(row[4]))
		{
			status = ALERT_STATUS_NOT_SENT;
			perror = "";
		}
		else
		{
			status = ALERT_STATUS_FAILED;
			perror = "Media type disabled";
		}

		if (0 == medias_num++)
		{
			zbx_db_insert_prepare(&db_insert, "alerts", "alertid", "actionid", "eventid", "userid", "clock",
					"mediatypeid", "sendto", "subject", "message", "status", "error", "esc_step",
					"alerttype", NULL);
		}

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), action->actionid, c_event->eventid, userid, now,
				mediatypeid, row[1], subject, message, status, perror, escalation->esc_step,
				(int)ALERT_TYPE_MESSAGE);
	}

	DBfree_result(result);

	if (0 == mediatypeid)
	{
		medias_num++;

		zbx_snprintf(error, sizeof(error), "No media defined for user \"%s\"", zbx_user_string(userid));

		zbx_db_insert_prepare(&db_insert, "alerts", "alertid", "actionid", "eventid", "userid", "clock",
				"subject", "message", "status", "retries", "error", "esc_step", "alerttype", NULL);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), action->actionid, c_event->eventid, userid, now,
				subject, message, (int)ALERT_STATUS_FAILED, (int)ALERT_MAX_RETRIES, error,
				escalation->esc_step, (int)ALERT_TYPE_MESSAGE);
	}

	if (0 != medias_num)
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
static int	check_operation_conditions(DB_EVENT *event, zbx_uint64_t operationid, unsigned char evaltype)
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
		condition.operator = (unsigned char)atoi(row[1]);
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

static void	execute_operations(DB_ESCALATION *escalation, DB_EVENT *event, DB_ACTION *action)
{
	const char	*__function_name = "execute_operations";
	DB_RESULT	result;
	DB_ROW		row;
	int		next_esc_period = 0, esc_period;
	ZBX_USER_MSG	*user_msg = NULL, *p;
	zbx_uint64_t	operationid;
	unsigned char	operationtype, evaltype, operations = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == action->esc_period)
	{
		result = DBselect(
				"select o.operationid,o.operationtype,o.esc_period,o.evaltype,"
					"m.operationid,m.default_msg,subject,message,mediatypeid"
				" from operations o"
					" left join opmessage m"
						" on m.operationid=o.operationid"
				" where o.actionid=" ZBX_FS_UI64
					" and o.operationtype in (%d,%d)",
				action->actionid,
				OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND);
	}
	else
	{
		escalation->esc_step++;

		result = DBselect(
				"select o.operationid,o.operationtype,o.esc_period,o.evaltype,"
					"m.operationid,m.default_msg,subject,message,mediatypeid"
				" from operations o"
					" left join opmessage m"
						" on m.operationid=o.operationid"
				" where o.actionid=" ZBX_FS_UI64
					" and o.operationtype in (%d,%d)"
					" and o.esc_step_from<=%d"
					" and (o.esc_step_to=0 or o.esc_step_to>=%d)",
				action->actionid,
				OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND,
				escalation->esc_step,
				escalation->esc_step);
	}

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(operationid, row[0]);
		operationtype = (unsigned char)atoi(row[1]);
		esc_period = atoi(row[2]);
		evaltype = (unsigned char)atoi(row[3]);

		if (SUCCEED == check_operation_conditions(event, operationid, evaltype))
		{
			unsigned char	default_msg;
			char		*subject, *message;
			zbx_uint64_t	mediatypeid;

			zabbix_log(LOG_LEVEL_DEBUG, "Conditions match our event. Execute operation.");

			if (0 == next_esc_period || next_esc_period > esc_period)
				next_esc_period = esc_period;

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
							subject, message, event);
					break;
				case OPERATION_TYPE_COMMAND:
					execute_commands(event, action->actionid, operationid, escalation->esc_step);
					break;
			}
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions do not match our event. Do not execute operation.");

		operations = 1;
	}
	DBfree_result(result);

	while (NULL != user_msg)
	{
		p = user_msg;
		user_msg = user_msg->next;

		add_message_alert(escalation, event, NULL, action, p->userid, p->mediatypeid, p->subject, p->message);

		zbx_free(p->subject);
		zbx_free(p->message);
		zbx_free(p);
	}

	if (0 == action->esc_period)
	{
		escalation->status = (1 == action->recovery_msg) ? ESCALATION_STATUS_SLEEP :
				ESCALATION_STATUS_COMPLETED;
	}
	else
	{
		if (0 == operations)
		{
			result = DBselect(
					"select null"
					" from operations"
					" where actionid=" ZBX_FS_UI64
						" and esc_step_from>%d",
					action->actionid, escalation->esc_step);

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
			escalation->status = (1 == action->recovery_msg) ? ESCALATION_STATUS_SLEEP :
					ESCALATION_STATUS_COMPLETED;
		}
	}

	/* schedule nextcheck for sleeping escalations */
	if (ESCALATION_STATUS_SLEEP == escalation->status)
		escalation->nextcheck = time(NULL) + SEC_PER_MIN;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	process_recovery_msg(DB_ESCALATION *escalation, DB_EVENT *event, DB_EVENT *r_event, DB_ACTION *action)
{
	const char	*__function_name = "process_recovery_msg";
	char		*subject_dyn, *message_dyn;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid, mediatypeid;
	ZBX_USER_MSG	*user_msg = NULL, *p;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 == action->recovery_msg)
	{
		result = DBselect(
				"select distinct userid,mediatypeid"
				" from alerts"
				" where actionid=" ZBX_FS_UI64
					" and eventid=" ZBX_FS_UI64
					" and mediatypeid is not null"
					" and alerttype=%d",
				action->actionid, escalation->eventid, ALERT_TYPE_MESSAGE);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_DBROW2UINT64(userid, row[0]);
			ZBX_STR2UINT64(mediatypeid, row[1]);

			if (SUCCEED != check_perm2system(userid))
				continue;

			switch (r_event->object)
			{
				case EVENT_OBJECT_TRIGGER:
					if (PERM_READ > get_trigger_permission(userid, r_event->objectid))
						continue;
					break;
				case EVENT_OBJECT_ITEM:
				case EVENT_OBJECT_LLDRULE:
					if (PERM_READ > get_item_permission(userid, r_event->objectid))
						continue;
					break;
			}

			subject_dyn = zbx_strdup(NULL, action->shortdata);
			message_dyn = zbx_strdup(NULL, action->longdata);

			substitute_simple_macros(&action->actionid, event, r_event, &userid, NULL, NULL, NULL, NULL,
					&subject_dyn, MACRO_TYPE_MESSAGE_RECOVERY, NULL, 0);
			substitute_simple_macros(&action->actionid, event, r_event, &userid, NULL, NULL, NULL, NULL,
					&message_dyn, MACRO_TYPE_MESSAGE_RECOVERY, NULL, 0);

			escalation->esc_step = 0;

			add_user_msg(userid, mediatypeid, &user_msg, subject_dyn, message_dyn);

			zbx_free(subject_dyn);
			zbx_free(message_dyn);
		}
		DBfree_result(result);

		while (NULL != user_msg)
		{
			p = user_msg;
			user_msg = user_msg->next;

			add_message_alert(escalation, event, r_event, action, p->userid, p->mediatypeid,
					p->subject, p->message);

			zbx_free(p->subject);
			zbx_free(p->message);
			zbx_free(p);
		}
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "escalation stopped: recovery message not defined (actionid:" ZBX_FS_UI64
				")", escalation->actionid);

	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_info                                                   *
 *                                                                            *
 * Purpose: get event and trigger info to event structure                     *
 *                                                                            *
 * Parameters: eventid - [IN] requested event id                              *
 *             event   - [OUT] event data                                     *
 *                                                                            *
 * Return value: SUCCEED if processed successfully, FAIL - otherwise          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: use 'free_event_info' function to release allocated memory       *
 *                                                                            *
 ******************************************************************************/
static int	get_event_info(zbx_uint64_t eventid, DB_EVENT *event)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	memset(event, 0, sizeof(DB_EVENT));

	result = DBselect("select eventid,source,object,objectid,clock,value,acknowledged,ns"
			" from events"
			" where eventid=" ZBX_FS_UI64,
			eventid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(event->eventid, row[0]);
		event->source = atoi(row[1]);
		event->object = atoi(row[2]);
		ZBX_STR2UINT64(event->objectid, row[3]);
		event->clock = atoi(row[4]);
		event->value = atoi(row[5]);
		event->acknowledged = atoi(row[6]);
		event->ns = atoi(row[7]);

		res = SUCCEED;
	}
	DBfree_result(result);

	if (SUCCEED == res && EVENT_OBJECT_TRIGGER == event->object)
	{
		result = DBselect("select description,expression,priority,comments,url"
				" from triggers"
				" where triggerid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = DBfetch(result)))
		{
			event->trigger.triggerid = event->objectid;
			event->trigger.description = zbx_strdup(event->trigger.description, row[0]);
			event->trigger.expression = zbx_strdup(event->trigger.expression, row[1]);
			event->trigger.priority = (unsigned char)atoi(row[2]);
			event->trigger.comments = zbx_strdup(event->trigger.comments, row[3]);
			event->trigger.url = zbx_strdup(event->trigger.url, row[4]);
		}
		else
			res = FAIL;
		DBfree_result(result);
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: free_event_info                                                  *
 *                                                                            *
 * Purpose: deallocate memory allocated in function 'get_event_info'          *
 *                                                                            *
 * Parameters: event - [IN] event data                                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	free_event_info(DB_EVENT *event)
{
	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		zbx_free(event->trigger.description);
		zbx_free(event->trigger.expression);
		zbx_free(event->trigger.comments);
		zbx_free(event->trigger.url);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: check_escalation_trigger                                         *
 *                                                                            *
 * Purpose: check whether the escalation trigger and related items, hosts are *
 *          not deleted or disabled.                                          *
 *                                                                            *
 * Parameters: triggerid - [IN] the id of trigger to check                    *
 *             source    - [IN] the esclation event source                    *
 *             error     - [OUT] message in case escalation is cancelled      *
 *                                                                            *
 ******************************************************************************/
static void	check_escalation_trigger(zbx_uint64_t triggerid, unsigned char source, char **error)
{
	DC_TRIGGER		trigger;
	int			i, errcode, *errcodes = NULL;
	zbx_vector_uint64_t	functionids, itemids;
	DC_ITEM			*items = NULL;
	DC_FUNCTION		*functions = NULL;

	/* trigger disabled or deleted? */
	DCconfig_get_triggers_by_triggerids(&trigger, &triggerid, &errcode, 1);

	if (SUCCEED != errcode)
	{
		*error = zbx_dsprintf(*error, "trigger id:" ZBX_FS_UI64 " deleted.", triggerid);
		goto out;
	}
	else if (TRIGGER_STATUS_DISABLED == trigger.status)
	{
		*error = zbx_dsprintf(*error, "trigger \"%s\" disabled.", trigger.description);
		goto out;
	}

	if (EVENT_SOURCE_TRIGGERS != source)
		goto out;

	/* check items and hosts referenced by trigger expression */
	zbx_vector_uint64_create(&functionids);
	zbx_vector_uint64_create(&itemids);

	get_functionids(&functionids, trigger.expression_orig);

	functions = zbx_malloc(functions, sizeof(DC_FUNCTION) * functionids.values_num);
	errcodes = zbx_malloc(errcodes, sizeof(int) * functionids.values_num);

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

	items = zbx_malloc(items, sizeof(DC_ITEM) * itemids.values_num);
	errcodes = zbx_realloc(errcodes, sizeof(int) * itemids.values_num);

	DCconfig_get_items_by_itemids(items, itemids.values, errcodes, itemids.values_num);

	for (i = 0; i < itemids.values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

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
	}

	DCconfig_clean_items(items, errcodes, itemids.values_num);
	zbx_free(items);
	zbx_free(errcodes);

	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_uint64_destroy(&functionids);
out:
	DCconfig_clean_triggers(&trigger, &errcode, 1);
}

/******************************************************************************
 *                                                                            *
 * Function: check_escalation                                                 *
 *                                                                            *
 * Purpose: check whether the escalation is still relevant (items, triggers,  *
 *          hosts, and actions are still present and were not disabled)       *
 *                                                                            *
 * Parameters: escalation - [IN] escalation data                              *
 *             action     - [OUT] action data (optional)                      *
 *             error      - [OUT] message in case escalation is cancelled     *
 *                                                                            *
 * Comments: If 'action' is not NULL, it gathers information about it. If     *
 *           information could not be gathered, its 'actionid' is set to 0.   *
 *                                                                            *
 ******************************************************************************/
static void	check_escalation(const DB_ESCALATION *escalation, DB_ACTION *action, char **error)
{
	const char	*__function_name = "check_escalation";
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	source = 0xff, object = 0xff;
	DC_ITEM		item;
	int		errcode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	result = DBselect("select source,object from events where eventid=" ZBX_FS_UI64, escalation->eventid);
	if (NULL != (row = DBfetch(result)))
	{
		source = (unsigned char)atoi(row[0]);
		object = (unsigned char)atoi(row[1]);
	}
	else
	{
		*error = zbx_dsprintf(*error, "event id:" ZBX_FS_UI64 " deleted.", escalation->eventid);
	}

	DBfree_result(result);

	if (NULL == *error && EVENT_OBJECT_TRIGGER == object)
		check_escalation_trigger(escalation->triggerid, source, error);

	if (NULL == *error && EVENT_SOURCE_INTERNAL == source)
	{
		if (EVENT_OBJECT_ITEM == object || EVENT_OBJECT_LLDRULE == object)
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

			DCconfig_clean_items(&item, &errcode, 1);
		}
	}

	switch (escalation->status)
	{
		case ESCALATION_STATUS_ACTIVE:
			result = DBselect(
					"select actionid,name,status%s"
					" from actions"
					" where actionid=" ZBX_FS_UI64,
					NULL == action ? "" : ",eventsource,esc_period"
						",def_shortdata,def_longdata,recovery_msg",
					escalation->actionid);
			break;
		case ESCALATION_STATUS_RECOVERY:
		case ESCALATION_STATUS_SLEEP:
			result = DBselect(
					"select actionid,name,status%s"
					" from actions"
					" where actionid=" ZBX_FS_UI64,
					NULL == action ? "" : ",eventsource,esc_period"
						",r_shortdata,r_longdata,recovery_msg",
					escalation->actionid);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return;
	}

	if (NULL != (row = DBfetch(result)))
	{
		if (ACTION_STATUS_ACTIVE != atoi(row[2]))
			*error = zbx_dsprintf(*error, "action '%s' disabled.", row[1]);

		if (NULL != action)
		{
			memset(action, 0, sizeof(*action));
			ZBX_STR2UINT64(action->actionid, row[0]);
			action->eventsource = atoi(row[3]);
			action->esc_period = atoi(row[4]);
			action->shortdata = zbx_strdup(NULL, row[5]);
			action->longdata = zbx_strdup(NULL, row[6]);
			action->recovery_msg = atoi(row[7]);
		}
	}
	else
	{
		if (NULL != action)
			action->actionid = 0;

		*error = zbx_dsprintf(*error, "action id:" ZBX_FS_UI64 " deleted", escalation->actionid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() error: %s", __function_name, ZBX_NULL2STR(*error));
}

static void	execute_escalation(DB_ESCALATION *escalation)
{
	const char	*__function_name = "execute_escalation";
	DB_ACTION	action;
	DB_EVENT	event, r_event;
	char		*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	check_escalation(escalation, &action, &error);

	if (0 != action.actionid)
	{
		if (NULL != error)
		{
			action.longdata = zbx_dsprintf(action.longdata, "NOTE: Escalation cancelled: %s\n%s",
					error, action.longdata);
		}

		switch (escalation->status)
		{
			case ESCALATION_STATUS_ACTIVE:
				if (SUCCEED == get_event_info(escalation->eventid, &event))
				{
					execute_operations(escalation, &event, &action);
					free_event_info(&event);
				}
				break;
			case ESCALATION_STATUS_RECOVERY:
				if (SUCCEED == get_event_info(escalation->eventid, &event))
				{
					if (SUCCEED == get_event_info(escalation->r_eventid, &r_event))
					{
						process_recovery_msg(escalation, &event, &r_event, &action);
						free_event_info(&r_event);
					}
					free_event_info(&event);
				}
				break;
			default:
				break;
		}

		zbx_free(action.longdata);
		zbx_free(action.shortdata);
	}

	if (NULL != error)
	{
		escalation->status = ESCALATION_STATUS_COMPLETED;
		zabbix_log(LOG_LEVEL_WARNING, "escalation cancelled: %s", error);
		zbx_free(error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	process_escalations(int now, int *nextcheck)
{
	const char		*__function_name = "process_escalations";
	DB_RESULT		result;
	DB_ROW			row;
	DB_ESCALATION		escalation, last_escalation;
	zbx_vector_uint64_t	escalationids;
	char			*sql = NULL;
	size_t			sql_alloc = ZBX_KIBIBYTE, sql_offset;
	int			res;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&escalationids);
	sql = zbx_malloc(sql, sql_alloc);

	result = DBselect(
			"select escalationid,actionid,triggerid,eventid,r_eventid,nextcheck,esc_step,status,itemid"
			" from escalations"
			" order by actionid,triggerid,itemid,escalationid");

	*nextcheck = now + CONFIG_ESCALATOR_FREQUENCY;
	memset(&escalation, 0, sizeof(escalation));

	do
	{
		unsigned char	esc_superseded = 0;

		memset(&last_escalation, 0, sizeof(last_escalation));

		if (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(last_escalation.escalationid, row[0]);
			ZBX_STR2UINT64(last_escalation.actionid, row[1]);
			ZBX_DBROW2UINT64(last_escalation.triggerid, row[2]);
			ZBX_DBROW2UINT64(last_escalation.eventid, row[3]);
			ZBX_DBROW2UINT64(last_escalation.r_eventid, row[4]);
			last_escalation.nextcheck = atoi(row[5]);
			last_escalation.esc_step = atoi(row[6]);
			last_escalation.status = atoi(row[7]);
			ZBX_DBROW2UINT64(last_escalation.itemid, row[8]);

			/* just delete on the next cycle */
			if (0 != last_escalation.r_eventid)
				last_escalation.status = ESCALATION_STATUS_COMPLETED;
		}

		if (0 == escalation.escalationid)
			goto next;

		if (ESCALATION_STATUS_COMPLETED == escalation.status)
		{
			/* delete a recovery record and skip all processing */
			zbx_vector_uint64_append(&escalationids, escalation.escalationid);
			goto next;
		}

		if (0 != last_escalation.escalationid)
		{
			esc_superseded = (escalation.actionid == last_escalation.actionid &&
					escalation.triggerid == last_escalation.triggerid &&
					escalation.itemid == last_escalation.itemid);

			if (0 != esc_superseded)
			{
				if (0 != last_escalation.r_eventid)
				{
					/* recover this escalation */
					escalation.r_eventid = last_escalation.r_eventid;
					escalation.status = ESCALATION_STATUS_ACTIVE;
				}
				else if (escalation.nextcheck > now || ESCALATION_STATUS_SLEEP == escalation.status)
				{
					zbx_vector_uint64_append(&escalationids, escalation.escalationid);
					goto next;
				}
			}
		}

		if (escalation.nextcheck <= now || 0 != escalation.r_eventid)
		{
			DBbegin();

			sql_offset = 0;

			if (ESCALATION_STATUS_ACTIVE == escalation.status)
			{
				if (escalation.nextcheck <= now)
					execute_escalation(&escalation);

				/* execute recovery */
				if (ESCALATION_STATUS_COMPLETED != escalation.status && 0 != escalation.r_eventid)
				{
					escalation.status = ESCALATION_STATUS_RECOVERY;
					execute_escalation(&escalation);
				}
				else if (0 != esc_superseded)
				{
					escalation.status = ESCALATION_STATUS_COMPLETED;
				}

				if (ESCALATION_STATUS_COMPLETED != escalation.status)
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"update escalations set status=%d", escalation.status);
					if (ESCALATION_STATUS_ACTIVE == escalation.status)
					{
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
								",esc_step=%d,nextcheck=%d",
								escalation.esc_step, escalation.nextcheck);
					}
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							" where escalationid=" ZBX_FS_UI64, escalation.escalationid);
				}
				else
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"delete from escalations where escalationid=" ZBX_FS_UI64,
							escalation.escalationid);
				}
			}
			else	/* ESCALATION_STATUS_SLEEP == escalation.status */
			{
				char	*error = NULL;

				check_escalation(&escalation, NULL, &error);

				if (NULL != error)
				{
					zabbix_log(LOG_LEVEL_WARNING, "escalation cancelled: %s", error);
					zbx_free(error);

					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"delete from escalations where escalationid=" ZBX_FS_UI64,
							escalation.escalationid);
				}
				else
				{
					escalation.nextcheck = time(NULL) + SEC_PER_MIN;
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"update escalations set nextcheck=%d"
							" where escalationid=" ZBX_FS_UI64,
							escalation.nextcheck, escalation.escalationid);
				}
			}

			DBexecute("%s", sql);

			DBcommit();
		}

		if (ESCALATION_STATUS_COMPLETED != escalation.status && escalation.nextcheck < *nextcheck)
			*nextcheck = escalation.nextcheck;
next:
		if (NULL != row)
			memcpy(&escalation, &last_escalation, sizeof(escalation));
	}
	while (NULL != row);

	DBfree_result(result);

	zbx_free(sql);

	res = escalationids.values_num;		/* performance metric */

	/* delete completed escalations */
	if (0 != escalationids.values_num)
	{
		zbx_vector_uint64_sort(&escalationids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DBbegin();
		DBexecute_multiple_query("delete from escalations where", "escalationid", &escalationids);
		DBcommit();
	}

	zbx_vector_uint64_destroy(&escalationids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return res;
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

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_daemon_type_string(daemon_type),
			server_num, get_process_type_string(process_type), process_num);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		if (0 != sleeptime)
		{
			zbx_setproctitle("%s [processed %d escalations in " ZBX_FS_DBL " sec, processing escalations]",
					get_process_type_string(process_type), old_escalations_count, old_total_sec);
		}

		sec = zbx_time();
		escalations_count += process_escalations(time(NULL), &nextcheck);
		total_sec += zbx_time() - sec;

		sleeptime = calculate_sleeptime(nextcheck, CONFIG_ESCALATOR_FREQUENCY);

		now = time(NULL);

		if (0 != sleeptime || STAT_INTERVAL <= now - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s [processed %d escalations in " ZBX_FS_DBL
						" sec, processing escalations]", get_process_type_string(process_type),
						escalations_count, total_sec);
			}
			else
			{
				zbx_setproctitle("%s [processed %d escalations in " ZBX_FS_DBL " sec, idle %d sec]",
						get_process_type_string(process_type), escalations_count, total_sec,
						sleeptime);

				old_escalations_count = escalations_count;
				old_total_sec = total_sec;
			}
			escalations_count = 0;
			total_sec = 0.0;
			last_stat_time = now;
		}

		zbx_sleep_loop(sleeptime);
	}
}
