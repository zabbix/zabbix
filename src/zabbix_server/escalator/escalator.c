/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "daemon.h"
#include "zbxserver.h"

#include "escalator.h"
#include "../operations.h"
#include "../events.h"
#include "../actions.h"
#include "../poller/checks_agent.h"
#include "../poller/checks_ipmi.h"

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

	result = DBselect( "select count(g.usrgrpid) from usrgrp g,users_groups ug where ug.userid=" ZBX_FS_UI64
			" and g.usrgrpid = ug.usrgrpid and g.users_status=%d",
			userid,
			GROUP_STATUS_DISABLED);

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
	DB_RESULT	result;
	DB_ROW		row;
	int		user_type = -1, perm = PERM_DENY;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_host_permission()");

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

	result = DBselect("select min(r.permission) from rights r,hosts_groups hg,users_groups ug"
			" where r.groupid=ug.usrgrpid and r.id=hg.groupid"
			" and hg.hostid=" ZBX_FS_UI64 " and ug.userid=" ZBX_FS_UI64,
			hostid,
			userid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		perm = atoi(row[0]);

	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of get_host_permission():%s",
			zbx_permission_string(perm));

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
	DB_RESULT	result;
	DB_ROW		row;
	int		perm = PERM_DENY, host_perm;
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_trigger_permission()");

	result = DBselect("select distinct i.hostid from items i,functions f"
			" where i.itemid=f.itemid and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		host_perm = get_host_permission(userid, hostid);

		if (perm < host_perm)
			perm = host_perm;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of get_trigger_permission():%s",
			zbx_permission_string(perm));

	return perm;
}

static void	add_user_msg(zbx_uint64_t userid, zbx_uint64_t mediatypeid, ZBX_USER_MSG **user_msg,
		char *subject, char *message, unsigned char source, zbx_uint64_t triggerid)
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
		p->subject = strdup(subject);
		p->message = strdup(message);
		p->next = *user_msg;

		*user_msg = p;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_object_msg(zbx_uint64_t operationid, zbx_uint64_t mediatypeid, ZBX_USER_MSG **user_msg,
		char *subject, char *message, unsigned char source, zbx_uint64_t triggerid)
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

/******************************************************************************
 *                                                                            *
 * Function: run_remote_command                                               *
 *                                                                            *
 * Purpose: run remote command on specific host                               *
 *                                                                            *
 * Parameters: item          - [IN] DC_ITEM structure with valid              *
 *                             item->host.hostid, item->host.host,            *
 *                             item->host.ipmi_authtype,                      *
 *                             item->host.ipmi_privilege,                     *
 *                             item->host.ipmi_username,                      *
 *                             item->host.ipmi_password values                *
 *             command       - [IN] remote command                            *
 *             error         - [OUT] buffer for message if error occured      *
 *             max_error_len - [IN] lengts of error buffer                    *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	run_remote_command(DC_ITEM *item, char *command, char *error, size_t max_error_len)
{
	const char	*__function_name = "run_remote_command";

	int		ret = FAIL;
	AGENT_RESULT	agent_result;
	char		*param, *port;
#ifdef HAVE_OPENIPMI
	int		val;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostname:'%s' command:'%s'", __function_name, item->host.host, command);

	*error = '\0';

#ifdef HAVE_OPENIPMI
	if (0 == strncmp(command, "IPMI", 4))
	{
		if (SUCCEED == (ret = DCconfig_get_interface_by_type(&item->interface, item->host.hostid,
				INTERFACE_TYPE_IPMI, 1)))
		{
			item->interface.addr = strdup(item->interface.useip ? item->interface.ip_orig : item->interface.dns_orig);
			substitute_simple_macros(NULL, NULL, &item->host, NULL,
					&item->interface.addr, MACRO_TYPE_INTERFACE_ADDR, NULL, 0);

			port = strdup(item->interface.port_orig);
			substitute_simple_macros(NULL, &item->host.hostid, NULL, NULL,
					&port, MACRO_TYPE_INTERFACE_PORT, NULL, 0);
			if (SUCCEED == (ret = is_ushort(port, &item->interface.port)))
			{
				if (SUCCEED == (ret = parse_ipmi_command(command, item->ipmi_sensor, &val)))
				{
					item->key = item->ipmi_sensor;
					ret = set_ipmi_control_value(item, val, error, max_error_len);
				}
				else
					zbx_strlcpy(error, "Incorrect format of IPMI command", max_error_len);
			}
			else
				zbx_snprintf(error, max_error_len, "Invalid port number [%s]",
						item->interface.port_orig);

			zbx_free(port);
			zbx_free(item->interface.addr);
		}
		else
			zbx_snprintf(error, max_error_len, "IPMI interface is not defined for host [%s]", item->host.host);
	}
	else
	{
#endif
		if (SUCCEED == (ret = DCconfig_get_interface_by_type(&item->interface, item->host.hostid,
				INTERFACE_TYPE_AGENT, 1)))
		{
			item->interface.addr = strdup(item->interface.useip ? item->interface.ip_orig : item->interface.dns_orig);
			substitute_simple_macros(NULL, NULL, &item->host, NULL,
					&item->interface.addr, MACRO_TYPE_INTERFACE_ADDR, NULL, 0);

			port = strdup(item->interface.port_orig);
			substitute_simple_macros(NULL, &item->host.hostid, NULL, NULL,
					&port, MACRO_TYPE_INTERFACE_PORT, NULL, 0);
			if (SUCCEED == (ret = is_ushort(port, &item->interface.port)))
			{
				param = dyn_escape_param(command);
				item->key = zbx_dsprintf(NULL, "system.run[\"%s\",\"nowait\"]", param);
				zbx_free(param);

				init_result(&agent_result);

				alarm(CONFIG_TIMEOUT);
				if (SUCCEED != (ret = get_value_agent(item, &agent_result)) && ISSET_MSG(&agent_result))
					zbx_strlcpy(error, agent_result.msg, max_error_len);
				alarm(0);

				free_result(&agent_result);

				zbx_free(item->key);
			}
			else
				zbx_snprintf(error, max_error_len, "Invalid port number [%s]",
						item->interface.port_orig);

			zbx_free(port);
			zbx_free(item->interface.addr);
		}
		else
			zbx_snprintf(error, max_error_len, "Zabbix Agent interface is not defined for host [%s]", item->host.host);
#ifdef HAVE_OPENIPMI
	}
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	add_command_alert(DC_ITEM *item, zbx_uint64_t eventid, zbx_uint64_t actionid,
		int esc_step, const char *command, zbx_alert_status_t status, const char *error)
{
	const char	*__function_name = "add_command_alert";
	zbx_uint64_t	alertid;
	int		now;
	char		*tmp = NULL, *command_esc, *error_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	alertid = DBget_maxid("alerts");
	now = (int)time(NULL);
	tmp = zbx_dsprintf(tmp, "%s:%s", item->host.host, command);
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

static int	get_dynamic_hostid(DB_EVENT *event, DC_ITEM *item, char *error, size_t max_error_len)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		sql[512];
	int		offset, ret = SUCCEED;

	offset = zbx_snprintf(sql, sizeof(sql),
			"select distinct h.hostid,h.host"
#ifdef HAVE_OPENIPMI
				",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
			);

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
					" where a.proxy_hostid=h.proxy_hostid"
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

	item->host.hostid = 0;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 != item->host.hostid)
		{
			switch (event->source)
			{
				case EVENT_SOURCE_TRIGGERS:
					zbx_strlcpy(error, "Too many hosts in trigger expression", max_error_len);
					break;
				case EVENT_SOURCE_DISCOVERY:
					zbx_strlcpy(error, "Too many hosts with same IP addreses", max_error_len);
					break;
			}
			ret = FAIL;
			break;
		}
		ZBX_STR2UINT64(item->host.hostid, row[0]);
		strscpy(item->host.host, row[1]);
#ifdef HAVE_OPENIPMI
		item->host.ipmi_authtype = (signed char)atoi(row[2]);
		item->host.ipmi_privilege = (unsigned char)atoi(row[3]);
		strscpy(item->host.ipmi_username, row[4]);
		strscpy(item->host.ipmi_password, row[5]);
#endif
	}
	DBfree_result(result);

	if (FAIL == ret)
	{
		item->host.hostid = 0;
		*item->host.host = '\0';
	}
	else if (0 == item->host.hostid)
	{
		*item->host.host = '\0';

		zbx_strlcpy(error, "Cannot find corresponding host", max_error_len);
		ret = FAIL;
	}

	return ret;
}

static void	execute_commands(DB_EVENT *event, zbx_uint64_t actionid, zbx_uint64_t operationid, int esc_step)
{
	const char		*__function_name = "execute_commands";
	DB_RESULT		result;
	DB_ROW			row;
	DC_ITEM			item;
	char			*command = NULL, error[ALERT_ERROR_LEN_MAX];
	int			rc;
	zbx_alert_status_t	status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	memset(&item, 0, sizeof(item));

	result = DBselect(
			"select distinct h.hostid,h.host,o.command"
#ifdef HAVE_OPENIPMI
				",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
			" from opcommand_grp o,hosts_groups hg,hosts h"
			" where o.groupid=hg.groupid"
				" and hg.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union "
			"select distinct h.hostid,h.host,o.command"
#ifdef HAVE_OPENIPMI
				",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
#endif
			" from opcommand_hst o,hosts h"
			" where o.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union "
			"select distinct null,null,command"
#ifdef HAVE_OPENIPMI
				",null,null,null,null"
#endif
			" from opcommand_hst"
			" where operationid=" ZBX_FS_UI64
				" and hostid is null",
			operationid, HOST_STATUS_MONITORED,
			operationid, HOST_STATUS_MONITORED,
			operationid);

	while (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED != DBis_null(row[0]))
		{
			ZBX_STR2UINT64(item.host.hostid, row[0]);
			strscpy(item.host.host, row[1]);
#ifdef HAVE_OPENIPMI
			item.host.ipmi_authtype = (signed char)atoi(row[3]);
			item.host.ipmi_privilege = (unsigned char)atoi(row[4]);
			strscpy(item.host.ipmi_username, row[5]);
			strscpy(item.host.ipmi_password, row[6]);
#endif
			rc = SUCCEED;
		}
		else
			rc = get_dynamic_hostid(event, &item, error, sizeof(error));

		command = zbx_strdup(command, row[2]);
		substitute_macros(event, NULL, &command);

		if (SUCCEED == rc)
			rc = run_remote_command(&item, command, error, sizeof(error));

		if (SUCCEED != rc && '\0' != *error)
			zabbix_log(LOG_LEVEL_WARNING, "Cannot execute remote command [%s]: %s", command, error);

		status = (SUCCEED != rc ? ALERT_STATUS_FAILED : ALERT_STATUS_SENT);
		if (ALERT_STATUS_SENT == status)
			*error = '\0';

		add_command_alert(&item, event->eventid, actionid, esc_step, command, status, error);
	}
	DBfree_result(result);

	zbx_free(command);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	add_message_alert(DB_ESCALATION *escalation, DB_EVENT *event, DB_ACTION *action,
		zbx_uint64_t userid, zbx_uint64_t mediatypeid, char *subject, char *message)
{
	const char	*__function_name = "add_message_alert";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	alertid;
	int		now, severity, medias = 0;
	char		*sendto_esc, *subject_esc, *message_esc, *error_esc;
	char		error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now		= time(NULL);
	subject_esc	= DBdyn_escape_string_len(subject, ALERT_SUBJECT_LEN);
	message_esc	= DBdyn_escape_string(message);

	if (0 == mediatypeid)
	{
		result = DBselect("select mediatypeid,sendto,severity,period from media"
				" where active=%d and userid=" ZBX_FS_UI64,
				MEDIA_STATUS_ACTIVE,
				userid);
	}
	else
	{
		result = DBselect("select mediatypeid,sendto,severity,period from media"
				" where active=%d and userid=" ZBX_FS_UI64 " and mediatypeid=" ZBX_FS_UI64,
				MEDIA_STATUS_ACTIVE,
				userid,
				mediatypeid);
	}

	while (NULL != (row = DBfetch(result)))
	{
		medias		= 1;

		ZBX_STR2UINT64(mediatypeid, row[0]);
		severity	= atoi(row[2]);

		zabbix_log(LOG_LEVEL_DEBUG, "Trigger severity [%d] Media severity [%d] Period [%s]",
			event->trigger_priority,
			severity,
			row[3]);

		if (((1 << event->trigger_priority) & severity) == 0)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Won't send message (severity)");
			continue;
		}

		if (check_time_period(row[3], (time_t)NULL) == 0)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Won't send message (period)");
			continue;
		}

		alertid		= DBget_maxid("alerts");
		sendto_esc	= DBdyn_escape_string_len(row[1], ALERT_SENDTO_LEN);

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
			char		*subject = NULL, *message = NULL;
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
						subject = strdup(row[6]);
						message = strdup(row[7]);

						substitute_macros(event, NULL, &subject);
						substitute_macros(event, NULL, &message);
					}
					else
					{
						subject = action->shortdata;
						message = action->longdata;
					}

					add_object_msg(operation.operationid, mediatypeid, &user_msg, subject, message,
							event->source, event->objectid);

					if (0 == default_msg)
					{
						zbx_free(subject);
						zbx_free(message);
					}
					break;
				case OPERATION_TYPE_COMMAND:
					execute_commands(event, action->actionid, operation.operationid, escalation->esc_step);
					break;
				default:
					;
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
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid, mediatypeid;

	if (1 == action->recovery_msg)
	{
		result = DBselect("select distinct userid,mediatypeid from alerts where actionid=" ZBX_FS_UI64
				" and eventid=" ZBX_FS_UI64 " and mediatypeid is not null and alerttype=%d",
				action->actionid,
				escalation->eventid,
				ALERT_TYPE_MESSAGE);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(userid, row[0]);
			ZBX_STR2UINT64(mediatypeid, row[1]);

			escalation->esc_step = 0;
			add_message_alert(escalation, r_event, action, userid, mediatypeid, action->shortdata, action->longdata);
		}
		DBfree_result(result);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Escalation stopped: recovery message not defined",
				escalation->actionid);

	escalation->status = ESCALATION_STATUS_COMPLETED;
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
		result = DBselect("select description,priority,comments,url,type"
				" from triggers where triggerid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = DBfetch(result)))
		{
			zbx_strlcpy(event->trigger_description, row[0], sizeof(event->trigger_description));
			event->trigger_priority = atoi(row[1]);
			event->trigger_comments	= strdup(row[2]);
			event->trigger_url	= strdup(row[3]);
			event->trigger_type	= atoi(row[4]);
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
	zbx_free(event->trigger_comments);
	zbx_free(event->trigger_url);
}

static void	execute_escalation(DB_ESCALATION *escalation)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ACTION	action;
	DB_EVENT	event;
	char		*error = NULL;
	int		source = (-1);

	zabbix_log(LOG_LEVEL_DEBUG, "In execute_escalation()");

	result = DBselect("select source from events where eventid=" ZBX_FS_UI64,
			escalation->eventid);
	if (NULL == (row = DBfetch(result)))
		error = zbx_dsprintf(error, "Event [" ZBX_FS_UI64 "] deleted.",
				escalation->eventid);
	else
		source = atoi(row[0]);
	DBfree_result(result);

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* Trigger disabled? */
		result = DBselect("select description,status from triggers where triggerid=" ZBX_FS_UI64,
				escalation->triggerid);
		if (NULL == (row = DBfetch(result)))
			error = zbx_dsprintf(error, "Trigger [" ZBX_FS_UI64 "] deleted.",
					escalation->triggerid);
		else if (TRIGGER_STATUS_DISABLED == atoi(row[1]))
			error = zbx_dsprintf(error, "Trigger '%s' disabled.",
					row[0]);
		DBfree_result(result);
	}

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* Item disabled? */
		result = DBselect("select i.description from items i,functions f,triggers t"
				" where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid"
				" and i.itemid=f.itemid and i.status=%d",
				escalation->triggerid,
				ITEM_STATUS_DISABLED);
		if (NULL != (row = DBfetch(result)))
			error = zbx_dsprintf(error, "Item '%s' disabled.",
					row[0]);
		DBfree_result(result);
	}

	if (NULL == error && EVENT_SOURCE_TRIGGERS == source)
	{
		/* Host disabled? */
		result = DBselect("select h.host from hosts h,items i,functions f,triggers t"
				" where t.triggerid=" ZBX_FS_UI64 " and t.triggerid=f.triggerid"
				" and f.itemid=i.itemid and i.hostid=h.hostid and h.status=%d",
				escalation->triggerid,
				HOST_STATUS_NOT_MONITORED);
		if (NULL != (row = DBfetch(result)))
			error = zbx_dsprintf(error, "Host '%s' disabled.",
					row[0]);
		DBfree_result(result);
	}

	switch (escalation->status)
	{
		case ESCALATION_STATUS_ACTIVE:
			result = DBselect("select actionid,eventsource,esc_period,def_shortdata,def_longdata,recovery_msg,status,name"
					" from actions where actionid=" ZBX_FS_UI64,
					escalation->actionid);
			break;
		case ESCALATION_STATUS_RECOVERY:
			result = DBselect("select actionid,eventsource,esc_period,r_shortdata,r_longdata,recovery_msg,status,name"
					" from actions where actionid=" ZBX_FS_UI64,
					escalation->actionid);
			break;
		default:
			/* Never reached */
			return;
	}

	if (NULL != (row = DBfetch(result)))
	{
		memset(&action, 0, sizeof(action));
		ZBX_STR2UINT64(action.actionid, row[0]);
		action.eventsource	= atoi(row[1]);
		action.esc_period	= atoi(row[2]);
		action.shortdata	= strdup(row[3]);
		action.recovery_msg	= atoi(row[5]);

		if (ACTION_STATUS_ACTIVE != atoi(row[6]))
			error = zbx_dsprintf(error, "Action '%s' disabled.",
					row[7]);

		if (NULL != error)
			action.longdata = zbx_dsprintf(action.longdata, "NOTE: Escalation cancelled: %s\n%s",
					error,
					row[4]);
		else
			action.longdata = strdup(row[4]);

		switch (escalation->status)
		{
			case ESCALATION_STATUS_ACTIVE:
				if (SUCCEED == get_event_info(escalation->eventid, &event))
				{
					substitute_macros(&event, NULL, &action.shortdata);
					substitute_macros(&event, NULL, &action.longdata);

					execute_operations(escalation, &event, &action);
				}
				free_event_info(&event);
				break;
			case ESCALATION_STATUS_RECOVERY:
				if (SUCCEED == get_event_info(escalation->r_eventid, &event))
				{
					substitute_macros(&event, escalation, &action.shortdata);
					substitute_macros(&event, escalation, &action.longdata);

					process_recovery_msg(escalation, &event, &action);
				}
				free_event_info(&event);
				break;
			default:
				break;
		}

		zbx_free(action.shortdata);
		zbx_free(action.longdata);
	}
	else
		error = zbx_dsprintf(error, "Action [" ZBX_FS_UI64 "] deleted",
				escalation->actionid);
	DBfree_result(result);

	if (NULL != error)
	{
		escalation->status = ESCALATION_STATUS_COMPLETED;
		zabbix_log(LOG_LEVEL_WARNING, "Escalation cancelled: %s",
				error);
		zbx_free(error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of execute_escalation()");
}

static void	process_escalations(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ESCALATION	escalation;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_escalations()");

	result = DBselect("select escalationid,actionid,triggerid,eventid,r_eventid,esc_step,status"
			" from escalations where status in (%d,%d,%d,%d) and nextcheck<=%d" DB_NODE,
			ESCALATION_STATUS_ACTIVE,
			ESCALATION_STATUS_SUPERSEDED_ACTIVE,
			ESCALATION_STATUS_SUPERSEDED_RECOVERY,
			ESCALATION_STATUS_RECOVERY,
			now,
			DBnode_local("escalationid"));

	while (NULL != (row = DBfetch(result)))
	{
		memset(&escalation, 0, sizeof(escalation));
		ZBX_STR2UINT64(escalation.escalationid, row[0]);
		ZBX_STR2UINT64(escalation.actionid, row[1]);
		ZBX_DBROW2UINT64(escalation.triggerid, row[2]);
		ZBX_STR2UINT64(escalation.eventid, row[3]);
		ZBX_DBROW2UINT64(escalation.r_eventid, row[4]);
		escalation.esc_step	= atoi(row[5]);
		escalation.status	= atoi(row[6]);
		escalation.nextcheck	= 0;

		DBbegin();

		if (escalation.status == ESCALATION_STATUS_SUPERSEDED_ACTIVE)
		{
			escalation.status = ESCALATION_STATUS_ACTIVE;
			execute_escalation(&escalation);
			DBexecute("delete from escalations where escalationid=" ZBX_FS_UI64 " and status=%d",
					escalation.escalationid,
					ESCALATION_STATUS_SUPERSEDED_ACTIVE);
			DBexecute("update escalations set status=%d where escalationid=" ZBX_FS_UI64 " and status=%d",
					ESCALATION_STATUS_RECOVERY,
					escalation.escalationid,
					ESCALATION_STATUS_SUPERSEDED_RECOVERY);
		}
		else if (escalation.status == ESCALATION_STATUS_SUPERSEDED_RECOVERY)
		{
			escalation.status = ESCALATION_STATUS_ACTIVE;
			execute_escalation(&escalation);
			escalation.status = ESCALATION_STATUS_RECOVERY;
			execute_escalation(&escalation);
			DBremove_escalation(escalation.escalationid);
		}
		else
		{
			execute_escalation(&escalation);

			if (escalation.status == ESCALATION_STATUS_COMPLETED)
				DBremove_escalation(escalation.escalationid);
			else
				DBexecute("update escalations set status=%d,esc_step=%d,nextcheck=%d"
						" where escalationid=" ZBX_FS_UI64
							" and status=%d",
						escalation.status,
						escalation.esc_step,
						escalation.nextcheck,
						escalation.escalationid,
						ESCALATION_STATUS_ACTIVE);
		}

		DBcommit();
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of process_escalations()");
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
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
int	main_escalator_loop()
{
	int			now;
	double			sec;
	struct sigaction	phan;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_escalator_loop()");

        phan.sa_sigaction = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;
	sigaction(SIGALRM, &phan, NULL);

	zbx_setproctitle("escalator [connecting to the database]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		now = time(NULL);
		sec = zbx_time();

		zbx_setproctitle("escalator [processing escalations]");

		process_escalations(now);

		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "Escalator spent " ZBX_FS_DBL " seconds while processing escalation items."
				" Nextcheck after %d sec.",
				sec,
				CONFIG_ESCALATOR_FREQUENCY);

		zbx_setproctitle("escalator [sleeping for %d seconds]",
				CONFIG_ESCALATOR_FREQUENCY);

		sleep(CONFIG_ESCALATOR_FREQUENCY);
	}

	/* Never reached */
	DBclose();
}
