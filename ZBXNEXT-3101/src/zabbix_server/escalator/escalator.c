/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "../../libs/zbxcrypto/tls.h"
#include "comms.h"

extern int	CONFIG_ESCALATOR_FORKS;

#define CONFIG_ESCALATOR_FREQUENCY	3

#define ZBX_ESCALATION_SOURCE_DEFAULT	0
#define ZBX_ESCALATION_SOURCE_ITEM	1
#define ZBX_ESCALATION_SOURCE_TRIGGER	2

#define ZBX_ACTION_RECOVERY_NONE	0
#define ZBX_ACTION_RECOVERY_OPERATIONS	1

typedef struct
{
	zbx_uint64_t	userid;
	zbx_uint64_t	mediatypeid;
	char		*subject;
	char		*message;
	void		*next;
}
ZBX_USER_MSG;

typedef struct
{
	zbx_uint64_t	actionid;
	char		*name;
	char		*shortdata;
	char		*longdata;
	char		*r_shortdata;
	char		*r_longdata;
	int		esc_period;
	unsigned char	eventsource;
	unsigned char	maintenance_mode;
	unsigned char	recovery;
	unsigned char	status;
}
DB_ACTION;

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

static void	add_message_alert(DB_ESCALATION *escalation, DB_EVENT *event, DB_EVENT *r_event, DB_ACTION *action,
		zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *subject, const char *message)
;

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

/******************************************************************************
 *                                                                            *
 * Function: add_sentusers_msg                                                *
 *                                                                            *
 * Purpose: adds message to be sent to all recipients of messages previously  *
 *          generated by the same action and event                            *
 *                                                                            *
 * Parameters: user_msg - [IN/OUT] the message list                           *
 *             actionid - [IN] the action identifier                          *
 *             event    - [IN] the event                                      *
 *             r_event  - [IN] the recover event (optional, can be NULL)      *
 *             subject  - [IN] the message subject                            *
 *             message  - [IN] the message body                               *
 *                                                                            *
 ******************************************************************************/
static void	add_sentusers_msg(ZBX_USER_MSG **user_msg, zbx_uint64_t actionid, DB_EVENT *event, DB_EVENT *r_event,
		const char *subject, const char *message)
{
	const char	*__function_name = "add_sentusers_msg";
	char		*subject_dyn, *message_dyn;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid, mediatypeid;
	DB_EVENT	*c_event;
	int		message_type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != r_event)
	{
		c_event = r_event;
		message_type = MACRO_TYPE_MESSAGE_RECOVERY;
	}
	else
	{
		c_event = event;
		message_type = MACRO_TYPE_MESSAGE_NORMAL;
	}

	result = DBselect(
			"select distinct userid,mediatypeid"
			" from alerts"
			" where actionid=" ZBX_FS_UI64
				" and eventid=" ZBX_FS_UI64
				" and mediatypeid is not null"
				" and alerttype=%d",
				actionid, event->eventid, ALERT_TYPE_MESSAGE);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(userid, row[0]);
		ZBX_STR2UINT64(mediatypeid, row[1]);

		if (SUCCEED != check_perm2system(userid))
			continue;

		switch (c_event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (PERM_READ > get_trigger_permission(userid, c_event->objectid))
					continue;
				break;
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				if (PERM_READ > get_item_permission(userid, c_event->objectid))
					continue;
				break;
		}

		subject_dyn = zbx_strdup(NULL, subject);
		message_dyn = zbx_strdup(NULL, message);

		substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL,
				NULL, &subject_dyn, message_type, NULL, 0);
		substitute_simple_macros(&actionid, event, r_event, &userid, NULL, NULL, NULL,
				NULL, &message_dyn, message_type, NULL, 0);

		add_user_msg(userid, mediatypeid, user_msg, subject_dyn, message_dyn);

		zbx_free(subject_dyn);
		zbx_free(message_dyn);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	flush_user_msg(ZBX_USER_MSG **user_msg, DB_ESCALATION *escalation, DB_EVENT *event, DB_EVENT *r_event,
		DB_ACTION *action)
{
	ZBX_USER_MSG	*p;

	while (NULL != *user_msg)
	{
		p = *user_msg;
		*user_msg = (*user_msg)->next;

		add_message_alert(escalation, event, r_event, action, p->userid, p->mediatypeid, p->subject,
				p->message);

		zbx_free(p->subject);
		zbx_free(p->message);
		zbx_free(p);
	}
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

#ifdef HAVE_OPENIPMI
#	define ZBX_IPMI_FIELDS_NUM	4	/* number of selected IPMI-related fields in functions */
						/* get_dynamic_hostid() and execute_commands() */
#else
#	define ZBX_IPMI_FIELDS_NUM	0
#endif

static int	get_dynamic_hostid(DB_EVENT *event, DC_HOST *host, char *error, size_t max_error_len)
{
	const char	*__function_name = "get_dynamic_hostid";
	DB_RESULT	result;
	DB_ROW		row;
	char		sql[512];	/* do not forget to adjust size if SQLs change */
	size_t		offset;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	offset = zbx_snprintf(sql, sizeof(sql), "select distinct h.hostid,h.host,h.tls_connect");
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
		strscpy(host->host, row[1]);
#ifdef HAVE_OPENIPMI
		host->ipmi_authtype = (signed char)atoi(row[3]);
		host->ipmi_privilege = (unsigned char)atoi(row[4]);
		strscpy(host->ipmi_username, row[5]);
		strscpy(host->ipmi_password, row[6]);
#endif
		host->tls_connect = (unsigned char)atoi(row[2]);
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		strscpy(host->tls_issuer, row[3 + ZBX_IPMI_FIELDS_NUM]);
		strscpy(host->tls_subject, row[4 + ZBX_IPMI_FIELDS_NUM]);
		strscpy(host->tls_psk_identity, row[5 + ZBX_IPMI_FIELDS_NUM]);
		strscpy(host->tls_psk, row[6 + ZBX_IPMI_FIELDS_NUM]);
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
	const char		*__function_name = "execute_commands";
	DB_RESULT		result;
	DB_ROW			row;
	zbx_db_insert_t		db_insert;
	int			alerts_num = 0;
	char			*buffer = NULL;
	size_t			buffer_alloc = 2 * ZBX_KIBIBYTE, buffer_offset = 0;
	zbx_vector_uint64_t	executed_on_hosts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	buffer = zbx_malloc(buffer, buffer_alloc);

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
			/* the 1st 'select' works if remote command target is "Host group" */
			"select distinct h.hostid,h.host,o.type,o.scriptid,o.execute_on,o.port"
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
			" from opcommand o,opcommand_grp og,hosts_groups hg,hosts h"
			" where o.operationid=og.operationid"
				" and og.groupid=hg.groupid"
				" and hg.hostid=h.hostid"
				" and o.operationid=" ZBX_FS_UI64
				" and h.status=%d"
			" union ",
			operationid, HOST_STATUS_MONITORED);
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
			/* the 2nd 'select' works if remote command target is "Host" */
			"select distinct h.hostid,h.host,o.type,o.scriptid,o.execute_on,o.port"
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
			"select distinct 0,null,o.type,o.scriptid,o.execute_on,o.port"
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
		zbx_alert_status_t	status;

		*error = '\0';
		memset(&host, 0, sizeof(host));
		zbx_script_init(&script);

		script.type = (unsigned char)atoi(row[2]);

		if (ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT != script.type)
		{
			script.command = zbx_strdup(script.command, row[11]);
			substitute_simple_macros(&actionid, event, NULL, NULL, NULL, NULL, NULL, NULL,
					&script.command, MACRO_TYPE_MESSAGE_NORMAL, NULL, 0);
		}

		if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT == script.type)
			script.execute_on = (unsigned char)atoi(row[4]);

		if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT != script.type || ZBX_SCRIPT_EXECUTE_ON_SERVER != script.execute_on)
		{
			ZBX_STR2UINT64(host.hostid, row[0]);

			if (0 != host.hostid)
			{
				if (FAIL != zbx_vector_uint64_search(&executed_on_hosts, host.hostid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					goto skip;
				}

				zbx_vector_uint64_append(&executed_on_hosts, host.hostid);
				strscpy(host.host, row[1]);
				host.tls_connect = (unsigned char)atoi(row[12]);
#ifdef HAVE_OPENIPMI
				host.ipmi_authtype = (signed char)atoi(row[13]);
				host.ipmi_privilege = (unsigned char)atoi(row[14]);
				strscpy(host.ipmi_username, row[15]);
				strscpy(host.ipmi_password, row[16]);
#endif
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				strscpy(host.tls_issuer, row[13 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_subject, row[14 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_psk_identity, row[15 + ZBX_IPMI_FIELDS_NUM]);
				strscpy(host.tls_psk, row[16 + ZBX_IPMI_FIELDS_NUM]);
#endif
			}
			else if (SUCCEED == (rc = get_dynamic_hostid(event, &host, error, sizeof(error))))
			{
				if (FAIL != zbx_vector_uint64_search(&executed_on_hosts, host.hostid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					goto skip;
				}

				zbx_vector_uint64_append(&executed_on_hosts, host.hostid);
			}
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

		add_command_alert(&db_insert, alerts_num++, &host, event->eventid, actionid, esc_step, script.command,
				status, error);
skip:
		zbx_script_clean(&script);
	}
	DBfree_result(result);
	zbx_vector_uint64_destroy(&executed_on_hosts);

	if (0 < alerts_num)
	{
		zbx_db_insert_autoincrement(&db_insert, "alertid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#undef ZBX_IPMI_FIELDS_NUM

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

static void	escalation_execute_operations(DB_ESCALATION *escalation, DB_EVENT *event, DB_ACTION *action)
{
	const char	*__function_name = "escalation_execute_operations";
	DB_RESULT	result;
	DB_ROW		row;
	int		next_esc_period = 0, esc_period;
	ZBX_USER_MSG	*user_msg = NULL;
	zbx_uint64_t	operationid;
	unsigned char	operationtype, evaltype, operations = 0;

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

	flush_user_msg(&user_msg, escalation, event, NULL, action);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_execute_recovery_operations                           *
 *                                                                            *
 * Purpose: execute escalation recovery operations                            *
 *                                                                            *
 * Parameters: escalation - [IN] the escalation                               *
 *             event      - [IN] the event                                    *
 *             r_event    - [IN] the recovery event                           *
 *             action     - [IN] the action                                   *
 *                                                                            *
 ******************************************************************************/
static void	escalation_execute_recovery_operations(DB_ESCALATION *escalation, DB_EVENT *event, DB_EVENT *r_event,
		DB_ACTION *action)
{
	const char	*__function_name = "escalation_execute_recovery_operations";
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_USER_MSG	*user_msg = NULL;
	zbx_uint64_t	operationid;
	unsigned char	operationtype, evaltype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select o.operationid,o.operationtype,o.evaltype,"
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
		evaltype = (unsigned char)atoi(row[2]);

		if (SUCCEED == check_operation_conditions(r_event, operationid, evaltype))
		{
			unsigned char	default_msg;
			char		*subject, *message;
			zbx_uint64_t	mediatypeid;

			zabbix_log(LOG_LEVEL_DEBUG, "Conditions match our event. Execute operation.");

			switch (operationtype)
			{
				case OPERATION_TYPE_MESSAGE:
					if (SUCCEED == DBis_null(row[3]))
						break;

					default_msg = (unsigned char)atoi(row[4]);
					ZBX_DBROW2UINT64(mediatypeid, row[7]);

					if (0 == default_msg)
					{
						subject = row[5];
						message = row[6];
					}
					else
					{
						subject = action->r_shortdata;
						message = action->r_longdata;
					}

					add_object_msg(action->actionid, operationid, mediatypeid, &user_msg, subject,
							message, r_event);
					break;
				case OPERATION_TYPE_RECOVERY_MESSAGE:
					if (SUCCEED != DBis_null(row[3]))
					{
						subject = row[5];
						message = row[6];
					}
					else
					{
						subject = action->r_shortdata;
						message = action->r_longdata;
					}

					add_sentusers_msg(&user_msg, action->actionid, event, r_event, subject,
							message);
					break;
				case OPERATION_TYPE_COMMAND:
					execute_commands(r_event, action->actionid, operationid, escalation->esc_step);
					break;
			}
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Conditions do not match our event. Do not execute operation.");
	}
	DBfree_result(result);

	flush_user_msg(&user_msg, escalation, event, r_event, action);

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
	int		ret = FAIL;

	memset(event, 0, sizeof(DB_EVENT));

	result = DBselect("select eventid,source,object,objectid,clock,value,acknowledged,ns"
			" from events"
			" where eventid=" ZBX_FS_UI64,
			eventid);

	if (NULL == (row = DBfetch(result)))
		goto out;

	ZBX_STR2UINT64(event->eventid, row[0]);
	event->source = atoi(row[1]);
	event->object = atoi(row[2]);
	ZBX_STR2UINT64(event->objectid, row[3]);
	event->clock = atoi(row[4]);
	event->value = atoi(row[5]);
	event->acknowledged = atoi(row[6]);
	event->ns = atoi(row[7]);

	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		zbx_vector_ptr_create(&event->tags);

		DBfree_result(result);

		result = DBselect("select tag,value from event_tag where eventid=" ZBX_FS_UI64, eventid);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_tag_t	*tag;

			tag = zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, row[0]);
			tag->value = zbx_strdup(NULL, row[1]);

			zbx_vector_ptr_append(&event->tags, tag);
		}
	}

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		DBfree_result(result);

		result = DBselect(
				"select description,expression,priority,comments,url,recovery_expression,recovery_mode"
				" from triggers"
				" where triggerid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL == (row = DBfetch(result)))
			goto out;

		event->trigger.triggerid = event->objectid;
		event->trigger.description = zbx_strdup(event->trigger.description, row[0]);
		event->trigger.expression = zbx_strdup(event->trigger.expression, row[1]);
		ZBX_STR2UCHAR(event->trigger.priority, row[2]);
		event->trigger.comments = zbx_strdup(event->trigger.comments, row[3]);
		event->trigger.url = zbx_strdup(event->trigger.url, row[4]);
		event->trigger.recovery_expression = zbx_strdup(event->trigger.recovery_expression, row[5]);
		ZBX_STR2UCHAR(event->trigger.recovery_mode, row[6]);
	}

	ret = SUCCEED;
out:
	DBfree_result(result);

	return ret;
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
	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		zbx_vector_ptr_clear_ext(&event->tags, (zbx_clean_func_t)zbx_free_tag);
		zbx_vector_ptr_destroy(&event->tags);
	}

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		zbx_free(event->trigger.description);
		zbx_free(event->trigger.expression);
		zbx_free(event->trigger.recovery_expression);
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
		*error = zbx_dsprintf(*error, "trigger id:" ZBX_FS_UI64 " deleted.", triggerid);
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

/******************************************************************************
 *                                                                            *
 * Function: check_escalation                                                 *
 *                                                                            *
 * Purpose: check whether the escalation is still relevant (items, triggers,  *
 *          hosts are still present and were not disabled)                    *
 *                                                                            *
 * Parameters: escalation - [IN] escalation data                              *
 *             action     - [OUT] action data                                 *
 *             skip       - [OUT] 1 if the escalation must be skipped,        *
 *                                0 otherwise                                 *
 *             error      - [OUT] message in case escalation is cancelled     *
 *                                                                            *
 * Return value: SUCCEED - the escalation is usable                           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: 'action' is filled with information about action. If information *
 *           could not be gathered, 'action->actionid' is set to 0.           *
 *                                                                            *
 ******************************************************************************/
static int	check_escalation(const DB_ESCALATION *escalation, unsigned char *skip, unsigned char *maintenance,
		char **error)
{
	const char	*__function_name = "check_escalation";
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	source = 0xff, object = 0xff;
	DC_ITEM		item;
	int		errcode, ret = FAIL;

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
		goto out;
	}

	*skip = 0;
	*maintenance = HOST_MAINTENANCE_STATUS_OFF;

	if (EVENT_OBJECT_TRIGGER == object)
	{
		if (SUCCEED != check_escalation_trigger(escalation->triggerid, source, skip, maintenance, error))
			goto out;
	}
	else if (EVENT_SOURCE_INTERNAL == source)
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
			else
				*maintenance = item.host.maintenance_status;

			DCconfig_clean_items(&item, &errcode, 1);

			if (NULL != *error)
				goto out;
		}
	}

	ret = SUCCEED;
out:
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error: '%s'", __function_name, zbx_result_string(ret),
			ZBX_NULL2STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_db_action                                                  *
 *                                                                            *
 * Purpose: check whether the action is still relevant (not disabled)         *
 *                                                                            *
 * Parameters: action - [IN] the action to check                              *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the action is usable                               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	check_db_action(const DB_ACTION *action, char **error)
{
	if (ACTION_STATUS_ACTIVE != action->status)
	{
		*error = zbx_dsprintf(*error, "action '%s' disabled.", action->name);
		return FAIL;
	}

	return SUCCEED;
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
static void	escalation_log_cancel_warning(DB_ESCALATION *escalation, const char *error)
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
 * Parameters: escalation - [IN] the escalation to cancel                     *
 *             action     - [IN] the action                                   *
 *             error      - [IN] the error message                            *
 *                                                                            *
 ******************************************************************************/
static void	escalation_cancel(DB_ESCALATION *escalation, DB_ACTION *action, const char *error)
{
	const char	*__function_name = "escalation_cancel";
	DB_EVENT	event;
	ZBX_USER_MSG	*user_msg = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	if (0 != escalation->esc_step)
	{
		if (SUCCEED == get_event_info(escalation->eventid, &event))
		{
			char	*message;

			message = zbx_dsprintf(NULL, "NOTE: Escalation cancelled: %s\n%s", error, action->longdata);
			add_sentusers_msg(&user_msg, action->actionid, &event, NULL, action->shortdata, message);
			flush_user_msg(&user_msg, escalation, &event, NULL, action);

			zbx_free(message);
			free_event_info(&event);
		}
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
 * Parameters: escalation - [IN] the escalation to execute                    *
 *             action     - [IN] the action                                   *
 *                                                                            *
 ******************************************************************************/
static void	escalation_execute(DB_ESCALATION *escalation, DB_ACTION *action)
{
	const char	*__function_name = "escalation_execute";
	DB_EVENT	event;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	if (SUCCEED == get_event_info(escalation->eventid, &event))
	{
		escalation_execute_operations(escalation, &event, action);
		free_event_info(&event);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: escalation_recover                                               *
 *                                                                            *
 * Purpose: process escalation recovery                                       *
 *                                                                            *
 * Parameters: escalation - [IN] the escalation to recovery                   *
 *             action     - [IN] the action                                   *
 *                                                                            *
 ******************************************************************************/
static void	escalation_recover(DB_ESCALATION *escalation, DB_ACTION *action)
{
	const char	*__function_name = "escalation_recover";
	DB_EVENT	event, r_event;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() escalationid:" ZBX_FS_UI64 " status:%s",
			__function_name, escalation->escalationid, zbx_escalation_status_string(escalation->status));

	if (SUCCEED == get_event_info(escalation->eventid, &event))
	{
		if (SUCCEED == get_event_info(escalation->r_eventid, &r_event))
		{
			escalation_execute_recovery_operations(escalation, &event, &r_event, action);
			free_event_info(&r_event);
		}
		free_event_info(&event);
	}

	escalation->status = ESCALATION_STATUS_COMPLETED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_db_action                                                    *
 *                                                                            *
 * Purpose: reads action from database                                        *
 *                                                                            *
 * Parameters: actionid - [IN] the action identifier                          *
 *             action   - [OUT] action data                                   *
 *                                                                            *
 * Return value: SUCEED - the action was read successfully                    *
 *               FAIL   - otherwise                                           *
 *                                                                            *
 ******************************************************************************/
static int	get_db_action(zbx_uint64_t actionid, DB_ACTION *action)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = FAIL;

	result = DBselect(
			"select actionid,name,status,eventsource,esc_period,def_shortdata,def_longdata,r_shortdata,"
				"r_longdata,maintenance_mode"
			" from actions"
			" where actionid=" ZBX_FS_UI64,
			actionid);

	if (NULL != (row = DBfetch(result)))
	{

		ZBX_STR2UCHAR(action->status, row[2]);
		ZBX_STR2UINT64(action->actionid, row[0]);
		ZBX_STR2UCHAR(action->eventsource, row[3]);
		action->esc_period = atoi(row[4]);
		action->shortdata = zbx_strdup(NULL, row[5]);
		action->longdata = zbx_strdup(NULL, row[6]);
		action->r_shortdata = zbx_strdup(NULL, row[7]);
		action->r_longdata = zbx_strdup(NULL, row[8]);
		ZBX_STR2UCHAR(action->maintenance_mode, row[9]);
		action->name = zbx_strdup(NULL, row[1]);

		DBfree_result(result);
		result = DBselect("select null from operations where actionid=" ZBX_FS_UI64 " and recovery=%d",
				action->actionid, ZBX_OPERATION_MODE_RECOVERY);

		action->recovery = (NULL == DBfetch(result) ? ZBX_ACTION_RECOVERY_NONE :
				ZBX_ACTION_RECOVERY_OPERATIONS);

		ret = SUCCEED;
	}

	DBfree_result(result);

	return ret;
}

static void	free_db_action(DB_ACTION *action)
{
	zbx_free(action->shortdata);
	zbx_free(action->longdata);
	zbx_free(action->r_shortdata);
	zbx_free(action->r_longdata);
	zbx_free(action->name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_escalations                                              *
 *                                                                            *
 * Purpose: execute escalations;                                              *
 *          postpone escalations during maintenance;                          *
 *          delete completed or superseded escalations from the database.     *
 *                                                                            *
 * Parameters: now               - [IN]  the current time                     *
 *             nextcheck         - [OUT] time of the next invocation          *
 *             escalation_source - [IN]  type of escalations to be handled    *
 *                                                                            *
 * Return value: the count of deleted escalations                             *
 *                                                                            *
 ******************************************************************************/
static int	process_escalations(int now, int *nextcheck, unsigned int escalation_source)
{
	const char		*__function_name = "process_escalations";
	DB_RESULT		result;
	DB_ROW			row;

	DB_ESCALATION		escalation, last_escalation;

	zbx_vector_uint64_t	escalationids;
	int			res;

	char			*sql = NULL, *filter = NULL;
	size_t			sql_alloc = ZBX_KIBIBYTE, sql_offset, filter_alloc = 0, filter_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&escalationids);
	sql = zbx_malloc(sql, sql_alloc);

	/* Selection of escalations to be processed:                                                          */
	/*                                                                                                    */
	/* e - row in escalations table, E - escalations table, S - ordered* set of escalations to be proc.   */
	/*                                                                                                    */
	/* ZBX_ESCALATION_SOURCE_TRIGGER: S = {e in E | e.triggerid    mod process_num == 0}                  */
	/* ZBX_ESCALATION_SOURCE_ITEM::   S = {e in E | e.itemid       mod process_num == 0}                  */
	/* ZBX_ESCALATION_SOURCE_TRIGGER: S = {e in E | e.escalationid mod process_num == 0}                  */
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

	result = DBselect(
			"select escalationid,actionid,triggerid,eventid,r_eventid,nextcheck,esc_step,status,itemid"
			" from escalations"
			" where %s"
			" order by actionid,triggerid,itemid,escalationid",
			filter);

	*nextcheck = now + CONFIG_ESCALATOR_FREQUENCY;
	memset(&escalation, 0, sizeof(escalation));

	/* Some decisions can be made only by looking at the next escalation.           */
	/*                                                                              */
	/* cur_esc:  (i - 1)-th escalation in the ordered set of selected escalations.  */
	/* next_esc: i-th escalation.                                                   */
	/*                                                                              */
	/* During the first iteration cur_esc is not defined.                           */
	/* During the last iteration next_esc is not defined.                           */
	do
	{
		unsigned char	esc_superseded = 0;

		memset(&last_escalation, 0, sizeof(last_escalation));

		/* not the last iteration */
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

		/* first iteration, copy next_esc to cur_esc and continue */
		if (0 == escalation.escalationid)
			goto next;

		if (ESCALATION_STATUS_COMPLETED == escalation.status)
		{
			/* delete a recovery escalation and skip all processing */
			zbx_vector_uint64_append(&escalationids, escalation.escalationid);
			goto next;
		}

		/* if there is a next escalation in the set then check if it supersedes the current one */
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

		/* if it is time to handle the cur_esc or if cur_esc is a recovery escalation */
		if (escalation.nextcheck <= now || 0 != escalation.r_eventid)
		{
			DB_ACTION	action;
			char		*error = NULL;
			unsigned char	skip, maintenance;

			if (SUCCEED != get_db_action(escalation.actionid, &action))
			{
				zbx_vector_uint64_append(&escalationids, escalation.escalationid);
				error = zbx_dsprintf(error, "action id:" ZBX_FS_UI64 " deleted", escalation.actionid);
				escalation_log_cancel_warning(&escalation, error);
				zbx_free(error);
				goto next;
			}

			if (SUCCEED != check_db_action(&action, &error) ||
					SUCCEED != check_escalation(&escalation, &skip, &maintenance, &error))
			{
				escalation_cancel(&escalation, &action, error);

				zbx_vector_uint64_append(&escalationids, escalation.escalationid);
				free_db_action(&action);
				zbx_free(error);
				goto next;
			}

			if (EVENT_SOURCE_TRIGGERS == action.eventsource &&
					ACTION_MAINTENANCE_MODE_PAUSE == action.maintenance_mode &&
					HOST_MAINTENANCE_STATUS_ON == maintenance)
			{
				/* remove paused escalations that were created and recovered/cancelled */
				/* during maintenance period                                           */
				if (0 == escalation.esc_step && 0 != escalation.r_eventid)
				{
					zbx_vector_uint64_append(&escalationids, escalation.escalationid);
					free_db_action(&action);
					goto next;
				}

				/* skip paused escalations created before maintenance period */
				/* until maintenance ends or the escalations are recovered     */
				if (0 == escalation.r_eventid)
				{
					free_db_action(&action);
					goto next;
				}
			}

			if (0 != skip)
			{
				/* Dependable trigger in PROBLEM state. If escalation is cancelled we process */
				/* it normally in order to send notification about the error otherwise we     */
				/* skip the escalation until dependable trigger changes value to OK.          */
				free_db_action(&action);
				goto next;
			}

			DBbegin();

			sql_offset = 0;

			/* process or postpone the escalation */
			if (ESCALATION_STATUS_ACTIVE == escalation.status)
			{

				if (escalation.nextcheck <= now)
				{
					/* don't execute escalation step if it has been recovered */
					/* and at least one step processed                        */
					if (0 == escalation.esc_step || 0 == escalation.r_eventid)
						escalation_execute(&escalation, &action);
				}

				/* execute recovery if the same record has it */
				if (ESCALATION_STATUS_COMPLETED != escalation.status && 0 != escalation.r_eventid)
				{
					escalation_recover(&escalation, &action);
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
			else	/* ESCALATION_STATUS_SLEEP == cur_esc.status */
			{
				escalation.nextcheck = time(NULL) + SEC_PER_MIN;
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update escalations set nextcheck=%d"
						" where escalationid=" ZBX_FS_UI64,
						escalation.nextcheck, escalation.escalationid);
			}

			free_db_action(&action);

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

	zbx_free(filter);
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
	}
}
