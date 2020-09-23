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
#include "nodecommand.h"
#include "comms.h"
#include "zbxserver.h"
#include "db.h"
#include "log.h"
#include "../scripts/scripts.h"


/******************************************************************************
 *                                                                            *
 * Function: execute_remote_script                                            *
 *                                                                            *
 * Purpose: execute remote command and wait for the result                    *
 *                                                                            *
 * Return value:  SUCCEED - the remote command was executed successfully      *
 *                FAIL    - an error occurred                                 *
 *                                                                            *
 ******************************************************************************/
static int	execute_remote_script(zbx_script_t *script, DC_HOST *host, char **info, char *error,
		size_t max_error_len)
{
	int		ret = FAIL, time_start;
	zbx_uint64_t	taskid;
	DB_RESULT	result = NULL;
	DB_ROW		row;

	if (0 == (taskid = zbx_script_create_task(script, host, 0, time(NULL))))
	{
		zbx_snprintf(error, max_error_len, "Cannot create remote command task.");
		return FAIL;
	}

	for (time_start = time(NULL); SEC_PER_MIN > time(NULL) - time_start; sleep(1))
	{
		result = DBselect(
				"select tr.status,tr.info"
				" from task t"
				" left join task_remote_command_result tr"
					" on tr.taskid=t.taskid"
				" where tr.parent_taskid=" ZBX_FS_UI64,
				taskid);

		if (NULL != (row = DBfetch(result)))
		{
			if (SUCCEED == (ret = atoi(row[0])))
				*info = zbx_strdup(*info, row[1]);
			else
				zbx_strlcpy(error, row[1], max_error_len);

			DBfree_result(result);
			return ret;
		}

		DBfree_result(result);
	}

	zbx_snprintf(error, max_error_len, "Timeout while waiting for remote command result.");

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: auditlog_global_script                                           *
 *                                                                            *
 * Purpose: record global script execution results into audit log             *
 *                                                                            *
 ******************************************************************************/
static void	auditlog_global_script(zbx_uint64_t scriptid, zbx_uint64_t hostid, zbx_uint64_t proxy_hostid,
		zbx_uint64_t userid, const char *clientip, const char *command, unsigned char execute_on,
		const char *output, const char *error)
{
	int		now;
	zbx_uint64_t	auditid;
	char		execute_on_s[MAX_ID_LEN + 1], hostid_s[MAX_ID_LEN + 1], proxy_hostid_s[MAX_ID_LEN + 1];

	now = time(NULL);
	auditid = DBget_maxid("auditlog");
	zbx_snprintf(execute_on_s, sizeof(execute_on_s), "%d", execute_on);
	zbx_snprintf(hostid_s, sizeof(hostid_s), ZBX_FS_UI64, hostid);
	if (0 != proxy_hostid)
		zbx_snprintf(proxy_hostid_s, sizeof(proxy_hostid_s), ZBX_FS_UI64, proxy_hostid);

	do
	{
		zbx_db_insert_t	db_audit, db_details;

		zbx_db_insert_prepare(&db_audit, "auditlog", "auditid", "userid", "clock", "action", "resourcetype",
				"ip", "resourceid", NULL);

		zbx_db_insert_prepare(&db_details, "auditlog_details", "auditdetailid", "auditid", "table_name",
				"field_name", "newvalue", NULL);

		DBbegin();

		zbx_db_insert_add_values(&db_audit, auditid, userid, now, AUDIT_ACTION_EXECUTE, AUDIT_RESOURCE_SCRIPT,
				clientip, scriptid);


		zbx_db_insert_add_values(&db_details, __UINT64_C(0), auditid, "script", "execute_on", execute_on_s);
		zbx_db_insert_add_values(&db_details, __UINT64_C(0), auditid, "script", "hostid", hostid_s);

		if (0 != proxy_hostid)
		{
			zbx_db_insert_add_values(&db_details, __UINT64_C(0), auditid, "script", "proxy_hostid",
					proxy_hostid_s);
		}

		zbx_db_insert_add_values(&db_details, __UINT64_C(0), auditid, "script", "command", command);

		if (NULL != output)
			zbx_db_insert_add_values(&db_details, __UINT64_C(0), auditid, "script", "output", output);

		if (NULL != error)
			zbx_db_insert_add_values(&db_details, __UINT64_C(0), auditid, "script", "error", error);

		zbx_db_insert_execute(&db_audit);
		zbx_db_insert_clean(&db_audit);

		zbx_db_insert_autoincrement(&db_details, "auditdetailid");
		zbx_db_insert_execute(&db_details);
		zbx_db_insert_clean(&db_details);

	}
	while (ZBX_DB_DOWN == DBcommit());
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_check_user_administration_permissions                        *
 *                                                                            *
 * Purpose: check if the user has specific or default access for              *
 *          administration actions                                            *
 *                                                                            *
 * Return value:  SUCCEED - the access is granted                             *
 *                FAIL    - the access is denied                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_check_user_administration_actions_permissions(zbx_user_t *user, const char *role_rule)
{
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 , __func__, user->userid);

	result = DBselect("select value_int,name from role_rule where roleid=" ZBX_FS_UI64
			" and (name='%s' or name='%s')", user->roleid, role_rule,
			ZBX_USER_ROLE_PERMISSION_ACTIONS_DEFAULT_ACCESS);

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
		else if (0 == strcmp(ZBX_USER_ROLE_PERMISSION_ACTIONS_DEFAULT_ACCESS, row[1]))
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

/******************************************************************************
 *                                                                            *
 * Function: execute_script                                                   *
 *                                                                            *
 * Purpose: executing command                                                 *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	execute_script(zbx_uint64_t scriptid, zbx_uint64_t hostid, const char *sessionid, const char *clientip,
		char **result)
{
	char		error[MAX_STRING_LEN];
	int		ret = FAIL, rc;
	DC_HOST		host;
	zbx_script_t	script;
	zbx_user_t	user;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() scriptid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " sessionid:%s",
			__func__, scriptid, hostid, sessionid);

	*error = '\0';

	if (SUCCEED != (rc = DCget_host_by_hostid(&host, hostid)))
	{
		zbx_strlcpy(error, "Unknown host identifier.", sizeof(error));
		goto fail;
	}

	if (SUCCEED != (rc = DBget_user_by_active_session(sessionid, &user)))
	{
		zbx_strlcpy(error, "Permission denied.", sizeof(error));
		goto fail;
	}

	if (SUCCEED != (rc = zbx_check_user_administration_actions_permissions(&user,
			ZBX_USER_ROLE_PERMISSION_ACTIONS_EXECUTE_SCRIPTS)))
	{
		zbx_strlcpy(error, "Permission denied. No role access.", sizeof(error));
		goto fail;
	}

	zbx_script_init(&script);

	script.type = ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT;
	script.scriptid = scriptid;

	if (SUCCEED == (ret = zbx_script_prepare(&script, &host, &user, error, sizeof(error))))
	{
		const char	*poutput = NULL, *perror = NULL;

		if (0 == host.proxy_hostid || ZBX_SCRIPT_EXECUTE_ON_SERVER == script.execute_on)
			ret = zbx_script_execute(&script, &host, result, error, sizeof(error));
		else
			ret = execute_remote_script(&script, &host, result, error, sizeof(error));

		if (SUCCEED == ret)
			poutput = *result;
		else
			perror = error;

		auditlog_global_script(scriptid, hostid, host.proxy_hostid, user.userid, clientip, script.command_orig,
				script.execute_on, poutput, perror);
	}

	zbx_script_clean(&script);
fail:
	if (SUCCEED != ret)
		*result = zbx_strdup(*result, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: node_process_command                                             *
 *                                                                            *
 * Purpose: process command received from the frontend                        *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	node_process_command(zbx_socket_t *sock, const char *data, struct zbx_json_parse *jp)
{
	char		*result = NULL, *send = NULL, tmp[64], sessionid[MAX_STRING_LEN], clientip[MAX_STRING_LEN];
	int		ret = FAIL;
	zbx_uint64_t	scriptid, hostid;
	struct zbx_json	j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): data:%s ", __func__, data);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SCRIPTID, tmp, sizeof(tmp), NULL) ||
			FAIL == is_uint64(tmp, &scriptid))
	{
		result = zbx_dsprintf(result, "Failed to parse command request tag: %s.", ZBX_PROTO_TAG_SCRIPTID);
		goto finish;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp), NULL) ||
			FAIL == is_uint64(tmp, &hostid))
	{
		result = zbx_dsprintf(result, "Failed to parse command request tag: %s.", ZBX_PROTO_TAG_HOSTID);
		goto finish;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SID, sessionid, sizeof(sessionid), NULL))
	{
		result = zbx_dsprintf(result, "Failed to parse command request tag: %s.", ZBX_PROTO_TAG_SID);
		goto finish;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLIENTIP, clientip, sizeof(clientip), NULL))
		*clientip = '\0';

	if (SUCCEED == (ret = execute_script(scriptid, hostid, sessionid, clientip, &result)))
	{
		zbx_json_addstring(&j, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_PROTO_TAG_DATA, result, ZBX_JSON_TYPE_STRING);
		send = j.buffer;
	}
finish:
	if (SUCCEED != ret)
	{
		zbx_json_addstring(&j, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_PROTO_TAG_INFO, (NULL != result ? result : "Unknown error."),
				ZBX_JSON_TYPE_STRING);
		send = j.buffer;
	}

	zbx_alarm_on(CONFIG_TIMEOUT);
	if (SUCCEED != zbx_tcp_send(sock, send))
		zabbix_log(LOG_LEVEL_WARNING, "Error sending result of command");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Sending back command '%s' result '%s'", data, send);
	zbx_alarm_off();

	zbx_json_free(&j);
	zbx_free(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
