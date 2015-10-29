/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

extern unsigned char	process_type;
extern int		process_num;

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
		perm = PERM_MAX;
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

static void	add_user_msg(zbx_uint64_t userid, zbx_uint64_t mediatypeid, ZBX_USER_MSG **user_msg,
		const char *subject, const char *message, unsigned char source, zbx_uint64_t triggerid)
{
	const char	*__function_name = "add_user_msg";
	ZBX_USER_MSG	*p;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != check_perm2system(userid))
		return;

	if (EVENT_SOURCE_TRIGGERS == source && PERM_READ_ONLY > get_trigger_permission(userid, triggerid))
		return;

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

static void	add_object_msg(zbx_uint64_t operationid, zbx_uint64_t mediatypeid, ZBX_USER_MSG **user_msg,
		const char *subject, const char *message, unsigned char source, zbx_uint64_t triggerid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid;

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
		add_user_msg(userid, mediatypeid, user_msg, subject, message, source, triggerid);
	}
	DBfree_result(result);
}

static void	add_command_alert(DC_HOST *host, zbx_uint64_t eventid, zbx_uint64_t actionid,
		int esc_step, const char *command, zbx_alert_status_t status, const char *error)
{
	const char	*__function_name = "add_command_alert";
	zbx_uint64_t	alertid;
	int		now;
	char		*tmp = NULL, *command_esc, *error_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	alertid = DBget_maxid("alerts");
	now = (int)time(NULL);
	tmp = zbx_dsprintf(tmp, "%s:%s", host->host, NULL == command ? "" : command);
	command_esc = DBdyn_escape_string_len(tmp, ALERT_MESSAGE_LEN);
	error_esc = DBdyn_escape_string_len(error, ALERT_ERROR_LEN);
	zbx_free(tmp);

	DBexecute("insert into alerts"
			" (alertid,actionid,eventid,clock,message,status,error,alerttype,esc_step)"
			" values "
			"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s',%d,'%s',%d,%d)",
			alertid, actionid, eventid, now, command_esc, (int)status,
			error_esc, ALERT_TYPE_COMMAND, esc_step);

	zbx_free(error_esc);
	zbx_free(command_esc);

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
							" and ds.dhostid=" ZBX_FS_UI64
							DB_NODE,
							event->objectid,
							DBnode_local("h.hostid"));
					break;
				case EVENT_OBJECT_DSERVICE:
					zbx_snprintf(sql + offset, sizeof(sql) - offset,
							" and ds.dserviceid=" ZBX_FS_UI64
							DB_NODE,
							event->objectid,
							DBnode_local("h.hostid"));
					break;
			}
			break;
		case EVENT_SOURCE_AUTO_REGISTRATION:
			zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" from autoreg_host a,hosts h"
					" where " ZBX_SQL_NULLCMP("a.proxy_hostid", "h.proxy_hostid")
						" and a.host=h.host"
						" and h.status=%d"
						" and a.autoreg_hostid=" ZBX_FS_UI64
						DB_NODE,
					HOST_STATUS_MONITORED, event->objectid,
					DBnode_local("h.hostid"));
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
	char		*buffer = NULL;
	size_t		buffer_alloc = ZBX_KIBIBYTE, buffer_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	buffer = zbx_malloc(buffer, buffer_alloc);

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
			"select distinct h.hostid,h.host,o.type,o.scriptid,o.execute_on,o.port"
				",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command");
#ifdef HAVE_OPENIPMI
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
			",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password");
#endif
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
			" from opcommand o,opcommand_grp og,hosts_groups hg,hosts h"
			" where o.operationid=og.operationid"
				" and og.groupid=hg.groupid"
				" and hg.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union "
			"select distinct h.hostid,h.host,o.type,o.scriptid,o.execute_on,o.port"
				",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command",
			operationid, HOST_STATUS_MONITORED);
#ifdef HAVE_OPENIPMI
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
			",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password");
#endif
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
			" from opcommand o,opcommand_hst oh,hosts h"
			" where o.operationid=oh.operationid"
				" and oh.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union "
			"select distinct 0,null,o.type,o.scriptid,o.execute_on,o.port"
				",o.authtype,o.username,o.password,o.publickey,o.privatekey,o.command",
			operationid, HOST_STATUS_MONITORED);
#ifdef HAVE_OPENIPMI
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, ",0,2,null,null");
#endif
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
			" from opcommand o,opcommand_hst oh"
			" where o.operationid=oh.operationid"
				" and o.operationid=" ZBX_FS_UI64
				" and oh.hostid is null",
			operationid);

	result = DBselect("%s", buffer);

	zbx_free(buffer);

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

		script.type = (unsigned char)atoi(row[2]);

		if (ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT != script.type)
		{
			script.command = zbx_strdup(script.command, row[11]);
			substitute_simple_macros(event, NULL, NULL, NULL, NULL, NULL,
					&script.command, MACRO_TYPE_MESSAGE, NULL, 0);
		}

		if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT == script.type)
			script.execute_on = (unsigned char)atoi(row[4]);

		if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT != script.type || ZBX_SCRIPT_EXECUTE_ON_SERVER != script.execute_on)
		{
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
		}

		if (SUCCEED == rc)
		{
			switch (script.type)
			{
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

		add_command_alert(&host, event->eventid, actionid, esc_step, script.command, status, error);

		zbx_script_clean(&script);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_message_alert(DB_ESCALATION *escalation, DB_EVENT *event, DB_ACTION *action,
		zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *subject, const char *message)
{
	const char	*__function_name = "add_message_alert";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	alertid;
	int		now, severity, medias = 0;
	char		*subject_dyn, *message_dyn, *sendto_esc, *subject_esc, *message_esc, *error_esc;
	char		error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	subject_dyn = zbx_strdup(NULL, subject);
	message_dyn = zbx_strdup(NULL, message);

	substitute_simple_macros(event, &userid, NULL, NULL, NULL, NULL, &subject_dyn, MACRO_TYPE_MESSAGE, NULL, 0);
	substitute_simple_macros(event, &userid, NULL, NULL, NULL, NULL, &message_dyn, MACRO_TYPE_MESSAGE, NULL, 0);

	now = time(NULL);
	subject_esc = DBdyn_escape_string_len(subject_dyn, ALERT_SUBJECT_LEN);
	message_esc = DBdyn_escape_string_len(message_dyn, ALERT_MESSAGE_LEN);

	zbx_free(subject_dyn);
	zbx_free(message_dyn);

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

	while (NULL != (row = DBfetch(result)))
	{
		medias		= 1;

		ZBX_STR2UINT64(mediatypeid, row[0]);
		severity	= atoi(row[2]);

		zabbix_log(LOG_LEVEL_DEBUG, "Trigger severity [%d] Media severity [%d] Period [%s]",
				(int)event->trigger.priority, severity, row[3]);

		if (((1 << event->trigger.priority) & severity) == 0)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Won't send message (severity)");
			continue;
		}

		if (FAIL == check_time_period(row[3], (time_t)NULL))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Won't send message (period)");
			continue;
		}

		alertid		= DBget_maxid("alerts");
		sendto_esc	= DBdyn_escape_string_len(row[1], ALERT_SENDTO_LEN);

		if (MEDIA_TYPE_STATUS_ACTIVE == atoi(row[4]))
		{
			DBexecute("insert into alerts (alertid,actionid,eventid,userid,clock"
					",mediatypeid,sendto,subject,message,status,alerttype,esc_step)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d"
					"," ZBX_FS_UI64 ",'%s','%s','%s',%d,%d,%d)",
					alertid,
					action->actionid,
					event->eventid,
					userid,
					now,
					mediatypeid,
					sendto_esc,
					subject_esc,
					message_esc,
					ALERT_STATUS_NOT_SENT,
					ALERT_TYPE_MESSAGE,
					escalation->esc_step);
		}
		else
		{
			error_esc = DBdyn_escape_string("Media type disabled");

			DBexecute("insert into alerts (alertid,actionid,eventid,userid,clock"
					",mediatypeid,sendto,subject,message,status,alerttype,esc_step,error)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d"
					"," ZBX_FS_UI64 ",'%s','%s','%s',%d,%d,%d,'%s')",
					alertid,
					action->actionid,
					event->eventid,
					userid,
					now,
					mediatypeid,
					sendto_esc,
					subject_esc,
					message_esc,
					ALERT_STATUS_FAILED,
					ALERT_TYPE_MESSAGE,
					escalation->esc_step,
					error_esc);

			zbx_free(error_esc);
		}

		zbx_free(sendto_esc);
	}

	DBfree_result(result);

	if (0 == medias)
	{
		zbx_snprintf(error, sizeof(error), "No media defined for user \"%s\"",
				zbx_user_string(userid));

		alertid		= DBget_maxid("alerts");
		error_esc	= DBdyn_escape_string(error);

		DBexecute("insert into alerts (alertid,actionid,eventid,userid,retries,clock"
				",subject,message,status,alerttype,error,esc_step)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d"
				",'%s','%s',%d,%d,'%s',%d)",
				alertid,
				action->actionid,
				event->eventid,
				userid,
				ALERT_MAX_RETRIES,
				now,
				subject_esc,
				message_esc,
				ALERT_STATUS_FAILED,
				ALERT_TYPE_MESSAGE,
				error_esc,
				escalation->esc_step);

		zbx_free(error_esc);
	}

	zbx_free(subject_esc);
	zbx_free(message_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: check_operation_conditions                                       *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters: event - event to check                                         *
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

	int	ret = SUCCEED; /* SUCCEED required for ACTION_EVAL_TYPE_AND_OR */
	int	cond, old_type = -1, exit = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __function_name, operationid);

	result = DBselect("select conditiontype,operator,value"
				" from opconditions"
				" where operationid=" ZBX_FS_UI64
				" order by conditiontype",
			operationid);

	while (NULL != (row = DBfetch(result)) && 0 == exit)
	{
		memset(&condition, 0, sizeof(condition));
		condition.conditiontype	= atoi(row[0]);
		condition.operator	= atoi(row[1]);
		condition.value		= row[2];

		switch (evaltype)
		{
			case ACTION_EVAL_TYPE_AND_OR:
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
			case ACTION_EVAL_TYPE_AND:
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
			case ACTION_EVAL_TYPE_OR:
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
	DB_OPERATION	operation;
	int		esc_period = 0, operations = 0;
	ZBX_USER_MSG	*user_msg = NULL, *p;

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
		memset(&operation, 0, sizeof(operation));

		ZBX_STR2UINT64(operation.operationid, row[0]);
		operation.actionid = action->actionid;
		operation.operationtype = atoi(row[1]);
		operation.esc_period = atoi(row[2]);
		operation.evaltype = (unsigned char)atoi(row[3]);

		if (SUCCEED == check_operation_conditions(event, operation.operationid, operation.evaltype))
		{
			unsigned char	default_msg;
			char		*subject, *message;
			zbx_uint64_t	mediatypeid;

			zabbix_log(LOG_LEVEL_DEBUG, "Conditions match our event. Execute operation.");

			if (0 == esc_period || esc_period > operation.esc_period)
				esc_period = operation.esc_period;

			switch (operation.operationtype)
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

					add_object_msg(operation.operationid, mediatypeid, &user_msg, subject, message,
							event->source, event->objectid);
					break;
				case OPERATION_TYPE_COMMAND:
					execute_commands(event, action->actionid, operation.operationid, escalation->esc_step);
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

		add_message_alert(escalation, event, action, p->userid, p->mediatypeid, p->subject, p->message);

		zbx_free(p->subject);
		zbx_free(p->message);
		zbx_free(p);
	}

	if (0 == action->esc_period)
	{
		escalation->status = (action->recovery_msg == 1) ? ESCALATION_STATUS_SLEEP : ESCALATION_STATUS_COMPLETED;
	}
	else
	{
		if (0 == operations)
		{
			result = DBselect("select operationid from operations where actionid=" ZBX_FS_UI64 " and esc_step_from>%d",
					action->actionid,
					escalation->esc_step);

			if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
				operations = 1;

			DBfree_result(result);
		}

		if (1 == operations)
		{
			esc_period = (0 != esc_period) ? esc_period : action->esc_period;
			escalation->nextcheck = time(NULL) + esc_period;
		}
		else
			escalation->status = (action->recovery_msg == 1) ? ESCALATION_STATUS_SLEEP : ESCALATION_STATUS_COMPLETED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	process_recovery_msg(DB_ESCALATION *escalation, DB_EVENT *r_event, DB_ACTION *action)
{
	const char	*__function_name = "process_recovery_msg";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid, mediatypeid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 == action->recovery_msg)
	{
		result = DBselect("select distinct userid,mediatypeid from alerts where actionid=" ZBX_FS_UI64
				" and eventid=" ZBX_FS_UI64 " and mediatypeid is not null and alerttype=%d",
				action->actionid,
				escalation->eventid,
				ALERT_TYPE_MESSAGE);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_DBROW2UINT64(userid, row[0]);
			ZBX_STR2UINT64(mediatypeid, row[1]);

			escalation->esc_step = 0;
			add_message_alert(escalation, r_event, action, userid, mediatypeid, action->shortdata, action->longdata);
		}
		DBfree_result(result);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "escalation stopped: recovery message not defined",
				escalation->actionid);

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
 * Comments: use 'free_event_info' function to clear allocated memory         *
 *                                                                            *
 ******************************************************************************/
static int	get_event_info(zbx_uint64_t eventid, DB_EVENT *event)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	memset(event, 0, sizeof(DB_EVENT));

	result = DBselect("select eventid,source,object,objectid,clock,value,acknowledged,ns"
			" from events where eventid=" ZBX_FS_UI64,
			eventid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(event->eventid, row[0]);
		event->source		= atoi(row[1]);
		event->object		= atoi(row[2]);
		ZBX_STR2UINT64(event->objectid, row[3]);
		event->clock		= atoi(row[4]);
		event->value		= atoi(row[5]);
		event->acknowledged	= atoi(row[6]);
		event->ns		= atoi(row[7]);

		res = SUCCEED;
	}
	DBfree_result(result);

	if (res == SUCCEED && event->object == EVENT_OBJECT_TRIGGER)
	{
		result = DBselect("select description,expression,priority,comments,url"
				" from triggers where triggerid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = DBfetch(result)))
		{
			event->trigger.triggerid = event->objectid;
			strscpy(event->trigger.description, row[0]);
			strscpy(event->trigger.expression, row[1]);
			event->trigger.priority = (unsigned char)atoi(row[2]);
			event->trigger.comments = zbx_strdup(event->trigger.comments, row[3]);
			event->trigger.url = zbx_strdup(event->trigger.url, row[4]);
		}
		DBfree_result(result);
	}
	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: free_event_info                                                  *
 *                                                                            *
 * Purpose: clean allocated memory by function 'get_event_info'               *
 *                                                                            *
 * Parameters: event - [IN] event data                                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	free_event_info(DB_EVENT *event)
{
	zbx_free(event->trigger.comments);
	zbx_free(event->trigger.url);
}

static void	execute_escalation(DB_ESCALATION *escalation)
{
	const char	*__function_name = "execute_escalation";
	DB_RESULT	result;
	DB_ROW		row;
	DB_ACTION	action;
	DB_EVENT	event;
	char		*error = NULL;
	int		source = (-1);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	result = DBselect("select source from events where eventid=" ZBX_FS_UI64, escalation->eventid);
	if (NULL == (row = DBfetch(result)))
		error = zbx_dsprintf(error, "event [" ZBX_FS_UI64 "] deleted.", escalation->eventid);
	else
		source = atoi(row[0]);
	DBfree_result(result);

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* trigger disabled? */
		result = DBselect("select description,status from triggers where triggerid=" ZBX_FS_UI64,
				escalation->triggerid);
		if (NULL == (row = DBfetch(result)))
			error = zbx_dsprintf(error, "trigger [" ZBX_FS_UI64 "] deleted.",
					escalation->triggerid);
		else if (TRIGGER_STATUS_DISABLED == atoi(row[1]))
			error = zbx_dsprintf(error, "trigger '%s' disabled.", row[0]);
		DBfree_result(result);
	}

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* item disabled? */
		result = DBselect(
				"select i.name"
				" from items i,functions f,triggers t"
				" where i.itemid=f.itemid"
					" and f.triggerid=t.triggerid"
					" and t.triggerid=" ZBX_FS_UI64
					" and i.status=%d",
				escalation->triggerid, ITEM_STATUS_DISABLED);
		if (NULL != (row = DBfetch(result)))
			error = zbx_dsprintf(error, "item '%s' disabled.", row[0]);
		DBfree_result(result);
	}

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* host disabled? */
		result = DBselect(
				"select h.host"
				" from hosts h,items i,functions f,triggers t"
				" where h.hostid=i.hostid"
					" and i.itemid=f.itemid"
					" and f.triggerid=t.triggerid"
					" and t.triggerid=" ZBX_FS_UI64
					" and h.status=%d",
				escalation->triggerid, HOST_STATUS_NOT_MONITORED);
		if (NULL != (row = DBfetch(result)))
			error = zbx_dsprintf(error, "host '%s' disabled.", row[0]);
		DBfree_result(result);
	}

	switch (escalation->status)
	{
		case ESCALATION_STATUS_ACTIVE:
			result = DBselect(
					"select actionid,eventsource,esc_period,def_shortdata,def_longdata,"
						"recovery_msg,status,name"
					" from actions"
					" where actionid=" ZBX_FS_UI64,
					escalation->actionid);
			break;
		case ESCALATION_STATUS_RECOVERY:
			result = DBselect(
					"select actionid,eventsource,esc_period,r_shortdata,r_longdata,recovery_msg,"
						"status,name"
					" from actions"
					" where actionid=" ZBX_FS_UI64,
					escalation->actionid);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return;
	}

	if (NULL != (row = DBfetch(result)))
	{
		memset(&action, 0, sizeof(action));
		ZBX_STR2UINT64(action.actionid, row[0]);
		action.eventsource	= atoi(row[1]);
		action.esc_period	= atoi(row[2]);
		action.shortdata	= row[3];
		action.recovery_msg	= atoi(row[5]);

		if (ACTION_STATUS_ACTIVE != atoi(row[6]))
			error = zbx_dsprintf(error, "action '%s' disabled.", row[7]);

		if (NULL != error) {
			action.longdata = zbx_dsprintf(action.longdata, "NOTE: Escalation cancelled: %s\n%s",
					error, row[4]);
		}
		else
			action.longdata = row[4];

		switch (escalation->status)
		{
			case ESCALATION_STATUS_ACTIVE:
				if (SUCCEED == get_event_info(escalation->eventid, &event))
					execute_operations(escalation, &event, &action);
				free_event_info(&event);
				break;
			case ESCALATION_STATUS_RECOVERY:
				if (SUCCEED == get_event_info(escalation->r_eventid, &event))
					process_recovery_msg(escalation, &event, &action);
				free_event_info(&event);
				break;
			default:
				break;
		}

		if (NULL != error)
			zbx_free(action.longdata);
	}
	else
		error = zbx_dsprintf(error, "action [" ZBX_FS_UI64 "] deleted", escalation->actionid);
	DBfree_result(result);

	if (NULL != error)
	{
		escalation->status = ESCALATION_STATUS_COMPLETED;
		zabbix_log(LOG_LEVEL_WARNING, "escalation cancelled: %s", error);
		zbx_free(error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	process_escalations(int now)
{
	const char		*__function_name = "process_escalations";
	DB_RESULT		result;
	DB_ROW			row;
	DB_ESCALATION		escalation, last_escalation;
	zbx_vector_uint64_t	escalationids;
	char			*sql = NULL;
	size_t			sql_alloc = ZBX_KIBIBYTE, sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&escalationids);
	sql = zbx_malloc(sql, sql_alloc);

	result = DBselect(
			"select escalationid,actionid,triggerid,eventid,r_eventid,esc_step,status,nextcheck"
			" from escalations"
			" where 1=1"
				DB_NODE
			" order by actionid,triggerid,escalationid",
			DBnode_local("escalationid"));

	memset(&escalation, 0, sizeof(escalation));

	do
	{
		memset(&last_escalation, 0, sizeof(last_escalation));

		if (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(last_escalation.escalationid, row[0]);
			ZBX_STR2UINT64(last_escalation.actionid, row[1]);
			ZBX_DBROW2UINT64(last_escalation.triggerid, row[2]);
			ZBX_DBROW2UINT64(last_escalation.eventid, row[3]);
			ZBX_DBROW2UINT64(last_escalation.r_eventid, row[4]);
			last_escalation.esc_step = atoi(row[5]);
			last_escalation.status = atoi(row[6]);
			last_escalation.nextcheck = atoi(row[7]);

			/* just delete on the next cycle */
			if (0 != last_escalation.r_eventid)
				last_escalation.status = ESCALATION_STATUS_COMPLETED;
		}

		if (0 != escalation.escalationid)
		{
			unsigned char	esc_superseded = 0;

			if (ESCALATION_STATUS_COMPLETED == escalation.status)
			{
				/* delete a recovery record and skip all processing */
				zbx_vector_uint64_append(&escalationids, escalation.escalationid);
				goto next;
			}

			if (0 != last_escalation.escalationid)
			{
				esc_superseded = (escalation.actionid == last_escalation.actionid &&
						escalation.triggerid == last_escalation.triggerid);

				if (1 == esc_superseded)
				{
					if (0 != last_escalation.r_eventid)
					{
						/* recover this escalation */
						escalation.r_eventid = last_escalation.r_eventid;
						escalation.status = ESCALATION_STATUS_ACTIVE;
					}
					else if (escalation.nextcheck > now ||
							ESCALATION_STATUS_SLEEP == escalation.status)
					{
						zbx_vector_uint64_append(&escalationids, escalation.escalationid);
						goto next;
					}
				}
			}

			if (ESCALATION_STATUS_ACTIVE != escalation.status ||
					(escalation.nextcheck > now && 0 == escalation.r_eventid))
			{
				goto next;
			}

			DBbegin();

			if (escalation.nextcheck <= now)
				execute_escalation(&escalation);

			/* execute recovery */
			if (ESCALATION_STATUS_COMPLETED != escalation.status && 0 != escalation.r_eventid)
			{
				escalation.status = ESCALATION_STATUS_RECOVERY;
				execute_escalation(&escalation);
			}
			else if (1 == esc_superseded)
				escalation.status = ESCALATION_STATUS_COMPLETED;

			sql_offset = 0;

			if (ESCALATION_STATUS_COMPLETED != escalation.status)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update escalations set status=%d", escalation.status);
				if (ESCALATION_STATUS_ACTIVE == escalation.status)
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",esc_step=%d,nextcheck=%d",
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

			DBexecute("%s", sql);

			DBcommit();
		}
next:
		if (NULL != row)
			memcpy(&escalation, &last_escalation, sizeof(escalation));
	}
	while (NULL != row);

	DBfree_result(result);

	zbx_free(sql);

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
void	main_escalator_loop()
{
	int	now;
	double	sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_escalator_loop()");

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [processing escalations]", get_process_type_string(process_type));

		now = time(NULL);
		sec = zbx_time();
		process_escalations(now);
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s #%d spent " ZBX_FS_DBL " seconds while processing escalations",
				get_process_type_string(process_type), process_num, sec);

		zbx_sleep_loop(CONFIG_ESCALATOR_FREQUENCY);
	}
}
