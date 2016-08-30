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
#include "nodecommand.h"
#include "comms.h"
#include "zbxserver.h"
#include "db.h"
#include "log.h"
#include "../scripts.h"

static int	DBget_user_by_active_session(zbx_user_t *user, const char *sessionid)
{
	const char	*__function_name = "DBget_user_by_active_session";
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sessionid:%s", sessionid);

	result = DBselect(
		"select u.userid,u.name,u.type"
			" from sessions s, users u"
		" where s.userid=u.userid"
			" and s.sessionid='%s'"
			" and s.status=%d",
		sessionid, ZBX_SESSION_ACTIVE);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(user->userid, row[0]);
		user->name = zbx_strdup(user->name, row[1]);
		user->type = (unsigned char)atoi(row[2]);

		ret = SUCCEED;
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

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
static int	execute_script(zbx_uint64_t scriptid, zbx_uint64_t hostid, const char *sessionid, char **result)
{
	const char	*__function_name = "execute_script";
	char		error[MAX_STRING_LEN];
	int		ret = FAIL, rc;
	DC_HOST		host;
	zbx_script_t	script;
	zbx_user_t	user;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() scriptid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " sessionid:%s",
			__function_name, scriptid, hostid, sessionid);

	*error = '\0';

	if (SUCCEED != (rc = DCget_host_by_hostid(&host, hostid)))
	{
		zbx_snprintf(error, sizeof(error), "Unknown Host ID [" ZBX_FS_UI64 "].", hostid);
		goto fail;
	}
	if (SUCCEED != (rc = DBget_user_by_active_session(&user, sessionid)))
	{
		zbx_snprintf(error, sizeof(error), "Cannot find active Session ID [" ZBX_FS_UI64 "].", sessionid);
		goto fail;
	}

	zbx_script_init(&script);

	script.type = ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT;
	script.scriptid = scriptid;

	ret = zbx_execute_script(&host, &script, &user, result, error, sizeof(error));

	zbx_script_clean(&script);
fail:
	if (SUCCEED != ret)
		*result = zbx_strdup(*result, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

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
	char		*result = NULL, *send = NULL, tmp[64], sessionid[MAX_STRING_LEN];
	int		ret = FAIL;
	zbx_uint64_t	scriptid, hostid;
	struct zbx_json	j;

	zabbix_log(LOG_LEVEL_DEBUG, "In node_process_command()");

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SCRIPTID, tmp, sizeof(tmp)) ||
			FAIL == is_uint64(tmp, &scriptid))
	{
		result = zbx_dsprintf(result, "Failed to parse command request tag: %s.", ZBX_PROTO_TAG_SCRIPTID);
		goto finish;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp)) ||
			FAIL == is_uint64(tmp, &hostid))
	{
		result = zbx_dsprintf(result, "Failed to parse command request tag: %s.", ZBX_PROTO_TAG_HOSTID);
		goto finish;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SID, sessionid, sizeof(sessionid)))
	{
		result = zbx_dsprintf(result, "Failed to parse command request tag: %s.", ZBX_PROTO_TAG_SID);
		goto finish;
	}

	if (SUCCEED == (ret = execute_script(scriptid, hostid, sessionid, &result)))
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
	if (SUCCEED != zbx_tcp_send_raw(sock, send))
		zabbix_log(LOG_LEVEL_WARNING, "Error sending result of command");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Sending back command '%s' result '%s'", data, send);
	zbx_alarm_off();

	zbx_json_free(&j);
	zbx_free(result);

	return ret;
}
