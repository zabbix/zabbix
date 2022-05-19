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

#include "nodecommand.h"

#include "zbxserver.h"
#include "log.h"
#include "trapper_auth.h"

#include "../scripts/scripts.h"
#include "audit/zbxaudit.h"
#include "../../libs/zbxserver/get_host_from_event.h"
#include "../../libs/zbxserver/zabbix_users.h"

/******************************************************************************
 *                                                                            *
 * Purpose: execute remote command and wait for the result                    *
 *                                                                            *
 * Return value:  SUCCEED - the remote command was executed successfully      *
 *                FAIL    - an error occurred                                 *
 *                                                                            *
 ******************************************************************************/
static int	execute_remote_script(const zbx_script_t *script, const DC_HOST *host, char **info, char *error,
		size_t max_error_len)
{
	int		time_start;
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
			int	ret;

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

static int	zbx_get_script_details(zbx_uint64_t scriptid, zbx_script_t *script, int *scope, zbx_uint64_t *usrgrpid,
		zbx_uint64_t *groupid, char *error, size_t error_len)
{
	int		ret = FAIL;
	DB_RESULT	db_result;
	DB_ROW		row;
	zbx_uint64_t	usrgrpid_l, groupid_l;

	db_result = DBselect("select command,host_access,usrgrpid,groupid,type,execute_on,timeout,scope,port,authtype"
			",username,password,publickey,privatekey"
			" from scripts"
			" where scriptid=" ZBX_FS_UI64, scriptid);

	if (NULL == db_result)
	{
		zbx_strlcpy(error, "Cannot select from table 'scripts'.", error_len);
		return FAIL;
	}

	if (NULL == (row = DBfetch(db_result)))
	{
		zbx_strlcpy(error, "Script not found.", error_len);
		goto fail;
	}

	ZBX_DBROW2UINT64(usrgrpid_l, row[2]);
	*usrgrpid = usrgrpid_l;

	ZBX_DBROW2UINT64(groupid_l, row[3]);
	*groupid = groupid_l;

	ZBX_STR2UCHAR(script->type, row[4]);

	if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT == script->type)
		ZBX_STR2UCHAR(script->execute_on, row[5]);

	if (ZBX_SCRIPT_TYPE_SSH == script->type)
	{
		ZBX_STR2UCHAR(script->authtype, row[9]);
		script->publickey = zbx_strdup(script->publickey, row[12]);
		script->privatekey = zbx_strdup(script->privatekey, row[13]);
	}

	if (ZBX_SCRIPT_TYPE_SSH == script->type || ZBX_SCRIPT_TYPE_TELNET == script->type)
	{
		script->port = zbx_strdup(script->port, row[8]);
		script->username = zbx_strdup(script->username, row[10]);
		script->password = zbx_strdup(script->password, row[11]);
	}

	script->command = zbx_strdup(script->command, row[0]);
	script->command_orig = zbx_strdup(script->command_orig, row[0]);

	script->scriptid = scriptid;

	ZBX_STR2UCHAR(script->host_access, row[1]);

	if (SUCCEED != is_time_suffix(row[6], &script->timeout, ZBX_LENGTH_UNLIMITED))
	{
		zbx_strlcpy(error, "Invalid timeout value in script configuration.", error_len);
		goto fail;
	}

	*scope = atoi(row[7]);

	ret = SUCCEED;
fail:
	DBfree_result(db_result);

	return ret;
}

static int	is_user_in_allowed_group(zbx_uint64_t userid, zbx_uint64_t usrgrpid, char *error, size_t error_len)
{
	DB_RESULT	result;
	int		ret = FAIL;

	result = DBselect("select null"
			" from users_groups"
			" where usrgrpid=" ZBX_FS_UI64
			" and userid=" ZBX_FS_UI64,
			usrgrpid, userid);

	if (NULL == result)
	{
		zbx_strlcpy(error, "Database error, cannot get user rights.", error_len);
		goto fail;
	}

	if (NULL == DBfetch(result))
		zbx_strlcpy(error, "User has no rights to execute this script.", error_len);
	else
		ret = SUCCEED;

	DBfree_result(result);
fail:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the specified event id corresponds to a problem event    *
 *          caused by a trigger, find its recovery event (if it exists)       *
 *                                                                            *
 * Parameters:  eventid       - [IN] the id of event                          *
 *              r_eventid     - [OUT] the id of recovery event (0 if there is *
 *                              no recovery event                             *
 *              error         - [OUT] the error message buffer                *
 *              error_len     - [IN] the size of error message buffer         *
 *                                                                            *
 * Return value:  SUCCEED or FAIL (with 'error' message)                      *
 *                                                                            *
 ******************************************************************************/
static int	zbx_check_event_end_recovery_event(zbx_uint64_t eventid, zbx_uint64_t *r_eventid, char *error,
		size_t error_len)
{
	DB_RESULT	db_result;
	DB_ROW		row;

	if (NULL == (db_result = DBselect("select r_eventid from event_recovery where eventid="ZBX_FS_UI64, eventid)))
	{
		zbx_strlcpy(error, "Database error, cannot read from 'events' and 'event_recovery' tables.", error_len);
		return FAIL;
	}

	if (NULL == (row = DBfetch(db_result)))
		*r_eventid = 0;
	else
		ZBX_DBROW2UINT64(*r_eventid, row[0]);

	DBfree_result(db_result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: executing command                                                 *
 *                                                                            *
 * Parameters:  scriptid - [IN] the id of a script to be executed             *
 *              hostid   - [IN] the host the script will be executed on       *
 *              eventid  - [IN] the id of an event                            *
 *              user     - [IN] the user who executes the command             *
 *              clientip - [IN] the IP of client                              *
 *              result   - [OUT] the result of a script execution             *
 *              debug    - [OUT] the debug data (optional)                    *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Comments: either 'hostid' or 'eventid' must be > 0, but not both           *
 *                                                                            *
 ******************************************************************************/
static int	execute_script(zbx_uint64_t scriptid, zbx_uint64_t hostid, zbx_uint64_t eventid, zbx_user_t *user,
		const char *clientip, char **result, char **debug)
{
	int			ret = FAIL, scope = 0, i, macro_type;
	DC_HOST			host;
	zbx_script_t		script;
	zbx_uint64_t		usrgrpid, groupid;
	zbx_vector_uint64_t	eventids;
	zbx_vector_ptr_t	events;
	zbx_vector_ptr_pair_t	webhook_params;
	char			*user_timezone = NULL, *webhook_params_json = NULL, error[MAX_STRING_LEN];
	ZBX_DB_EVENT		*problem_event = NULL, *recovery_event = NULL;
	zbx_dc_um_handle_t	*um_handle = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() scriptid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " eventid:" ZBX_FS_UI64
			" userid:" ZBX_FS_UI64 " clientip:%s",
			__func__, scriptid, hostid, eventid, user->userid, clientip);

	*error = '\0';
	memset(&host, 0, sizeof(host));
	zbx_vector_uint64_create(&eventids);
	zbx_vector_ptr_create(&events);
	zbx_vector_ptr_pair_create(&webhook_params);

	zbx_script_init(&script);

	if (SUCCEED != zbx_get_script_details(scriptid, &script, &scope, &usrgrpid, &groupid, error, sizeof(error)))
		goto fail;

	/* validate script permissions */

	if (0 < usrgrpid &&
			USER_TYPE_SUPER_ADMIN != user->type &&
			SUCCEED != is_user_in_allowed_group(user->userid, usrgrpid, error, sizeof(error)))
	{
		goto fail;
	}

	if (0 != hostid)
	{
		if (ZBX_SCRIPT_SCOPE_HOST != scope)
		{
			zbx_snprintf(error, sizeof(error), "Script is not allowed in manual host action: scope:%d",
					scope);
			goto fail;
		}
	}
	else if (ZBX_SCRIPT_SCOPE_EVENT != scope)
	{
		zbx_snprintf(error, sizeof(error), "Script is not allowed in manual event action: scope:%d", scope);
		goto fail;
	}

	/* get host or event details */

	if (0 != hostid)
	{
		if (SUCCEED != DCget_host_by_hostid(&host, hostid))
		{
			zbx_strlcpy(error, "Unknown host identifier.", sizeof(error));
			goto fail;
		}
	}
	else /* eventid */
	{
		zbx_uint64_t	r_eventid;

		if (SUCCEED != zbx_check_event_end_recovery_event(eventid, &r_eventid, error, sizeof(error)))
			goto fail;

		zbx_vector_uint64_reserve(&eventids, 2);
		zbx_vector_ptr_reserve(&events, 2);

		zbx_vector_uint64_append(&eventids, eventid);	/* problem event in element [0]*/

		if (0 != r_eventid)				/* optional recovery event in element [1] */
			zbx_vector_uint64_append(&eventids, r_eventid);

		zbx_db_get_events_by_eventids(&eventids, &events);

		if (events.values_num != eventids.values_num)
		{
			zbx_strlcpy(error, "Specified event data not found.", sizeof(error));
			goto fail;
		}

		switch (events.values_num)
		{
			case 1:
				if (eventid == ((ZBX_DB_EVENT *)(events.values[0]))->eventid)
				{
					problem_event = events.values[0];
				}
				else
				{
					zbx_strlcpy(error, "Specified event data not found.", sizeof(error));
					goto fail;
				}
				break;
			case 2:
				if (r_eventid == ((ZBX_DB_EVENT *)(events.values[0]))->eventid)
				{
					problem_event = events.values[1];
					recovery_event = events.values[0];
				}
				else
				{
					problem_event = events.values[0];
					recovery_event = events.values[1];
				}
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				zbx_snprintf(error, sizeof(error), "Internal error in %s() events.values_num:%d",
						__func__, events.values_num);
				goto fail;
		}

		if (EVENT_SOURCE_TRIGGERS != problem_event->source)
		{
			zbx_strlcpy(error, "The source of specified event is not a trigger.", sizeof(error));
			goto fail;
		}

		if (TRIGGER_VALUE_PROBLEM != problem_event->value)
		{
			zbx_strlcpy(error, "The specified event is not a problem event.", sizeof(error));
			goto fail;
		}

		if (SUCCEED != get_host_from_event((NULL != recovery_event) ? recovery_event : problem_event,
				&host, error, sizeof(error)))
		{
			goto fail;
		}
	}

	if (SUCCEED != zbx_check_script_permissions(groupid, host.hostid))
	{
		zbx_strlcpy(error, "Script does not have permission to be executed on the host.", sizeof(error));
		goto fail;
	}

	if (USER_TYPE_SUPER_ADMIN != user->type &&
			SUCCEED != zbx_check_script_user_permissions(user->userid, host.hostid, &script))
	{
		zbx_strlcpy(error, "User does not have permission to execute this script on the host.", sizeof(error));
		goto fail;
	}

	user_timezone = get_user_timezone(user->userid);

	/* substitute macros in script body and webhook parameters */

	if (0 != hostid)	/* script on host */
		macro_type = MACRO_TYPE_SCRIPT;
	else
		macro_type = (NULL != recovery_event) ? MACRO_TYPE_SCRIPT_RECOVERY : MACRO_TYPE_SCRIPT_NORMAL;

	um_handle = zbx_dc_open_user_macros();

	if (ZBX_SCRIPT_TYPE_WEBHOOK != script.type)
	{
		if (SUCCEED != zbx_substitute_simple_macros_unmasked(NULL, problem_event, recovery_event, &user->userid,
				NULL, &host, NULL, NULL, NULL, NULL, NULL, user_timezone, &script.command, macro_type,
				error, sizeof(error)))
		{
			goto fail;
		}

		if (SUCCEED != zbx_substitute_simple_macros(NULL, problem_event, recovery_event, &user->userid, NULL, &host,
				NULL, NULL, NULL, NULL, NULL, user_timezone, &script.command_orig, macro_type, error,
				sizeof(error)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto fail;
		}
	}
	else
	{
		if (SUCCEED != DBfetch_webhook_params(script.scriptid, &webhook_params, error, sizeof(error)))
			goto fail;

		for (i = 0; i < webhook_params.values_num; i++)
		{
			if (SUCCEED != zbx_substitute_simple_macros_unmasked(NULL, problem_event, recovery_event,
					&user->userid, NULL, &host, NULL, NULL, NULL, NULL, NULL, user_timezone,
					(char **)&webhook_params.values[i].second, macro_type, error,
					sizeof(error)))
			{
				goto fail;
			}
		}

		zbx_webhook_params_pack_json(&webhook_params, &webhook_params_json);
	}

	if (SUCCEED == (ret = zbx_script_prepare(&script, &host.hostid, error, sizeof(error))))
	{
		const char	*poutput = NULL, *perror = NULL;
		int		audit_res;

		if (0 == host.proxy_hostid || ZBX_SCRIPT_EXECUTE_ON_SERVER == script.execute_on ||
				ZBX_SCRIPT_TYPE_WEBHOOK == script.type)
		{
			ret = zbx_script_execute(&script, &host, webhook_params_json, result, error, sizeof(error),
					debug);
		}
		else
			ret = execute_remote_script(&script, &host, result, error, sizeof(error));

		if (SUCCEED == ret)
			poutput = *result;
		else
			perror = error;

		audit_res = zbx_auditlog_global_script(script.type, script.execute_on, script.command_orig, host.hostid,
				host.name, eventid, host.proxy_hostid, user->userid, user->username, clientip, poutput,
				perror);

		/* At the moment, there is no special processing of audit failures. */
		/* It can fail only due to the DB errors and those are visible in   */
		/* the log anyway */
		ZBX_UNUSED(audit_res);
	}
fail:
	if (NULL != um_handle)
		zbx_dc_close_user_macros(um_handle);

	if (SUCCEED != ret)
		*result = zbx_strdup(*result, error);

	zbx_script_clean(&script);
	zbx_free(webhook_params_json);
	zbx_free(user_timezone);

	for (i = 0; i < webhook_params.values_num; i++)
	{
		zbx_free(webhook_params.values[i].first);
		zbx_free(webhook_params.values[i].second);
	}

	zbx_vector_ptr_pair_destroy(&webhook_params);
	zbx_vector_ptr_clear_ext(&events, (zbx_clean_func_t)zbx_db_free_event);
	zbx_vector_ptr_destroy(&events);
	zbx_vector_uint64_destroy(&eventids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process command received from the frontend                        *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	node_process_command(zbx_socket_t *sock, const char *data, const struct zbx_json_parse *jp)
{
	char			*result = NULL, *send = NULL, *debug = NULL, tmp[64], tmp_hostid[64], tmp_eventid[64],
				clientip[MAX_STRING_LEN];
	int			ret = FAIL, got_hostid = 0, got_eventid = 0;
	zbx_uint64_t		scriptid, hostid = 0, eventid = 0;
	struct zbx_json		j;
	zbx_user_t		user;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): data:%s ", __func__, data);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_user_init(&user);

	/* check who is connecting, get user details, check access rights */

	if (FAIL == zbx_get_user_from_json(jp, &user, &result))
		goto finish;

	if (SUCCEED != check_perm2system(user.userid))
	{
		result = zbx_strdup(result, "Permission denied. User is a member of group with disabled access.");
		goto finish;
	}

	if (SUCCEED != zbx_check_user_administration_actions_permissions(&user,
			ZBX_USER_ROLE_PERMISSION_ACTIONS_DEFAULT_ACCESS,
			ZBX_USER_ROLE_PERMISSION_ACTIONS_EXECUTE_SCRIPTS))
	{
		result = zbx_strdup(result, "Permission denied. No role access.");
		goto finish;
	}

	/* extract and validate other JSON elements */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SCRIPTID, tmp, sizeof(tmp), NULL) ||
			FAIL == is_uint64(tmp, &scriptid))
	{
		result = zbx_dsprintf(result, "Failed to parse command request tag: %s.", ZBX_PROTO_TAG_SCRIPTID);
		goto finish;
	}

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOSTID, tmp_hostid, sizeof(tmp_hostid), NULL))
		got_hostid = 1;

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_EVENTID, tmp_eventid, sizeof(tmp_eventid), NULL))
		got_eventid = 1;

	if (0 == got_hostid && 0 == got_eventid)
	{
		result = zbx_dsprintf(result, "Failed to parse command request tag %s or %s.",
				ZBX_PROTO_TAG_HOSTID, ZBX_PROTO_TAG_EVENTID);
		goto finish;
	}

	if (1 == got_hostid && 1 == got_eventid)
	{
		result = zbx_dsprintf(result, "Command request tags %s and %s cannot be used together.",
				ZBX_PROTO_TAG_HOSTID, ZBX_PROTO_TAG_EVENTID);
		goto finish;
	}

	if (1 == got_hostid)
	{
		if (SUCCEED != is_uint64(tmp_hostid, &hostid))
		{
			result = zbx_dsprintf(result, "Failed to parse value of command request tag %s.",
					ZBX_PROTO_TAG_HOSTID);
			goto finish;
		}

		if (0 == hostid)
		{
			result = zbx_dsprintf(result, "%s value cannot be 0.", ZBX_PROTO_TAG_HOSTID);
			goto finish;
		}
	}
	else
	{
		if (SUCCEED != is_uint64(tmp_eventid, &eventid))
		{
			result = zbx_dsprintf(result, "Failed to parse value of command request tag %s.",
					ZBX_PROTO_TAG_EVENTID);
			goto finish;
		}

		if (0 == eventid)
		{
			result = zbx_dsprintf(result, "%s value cannot be 0.", ZBX_PROTO_TAG_EVENTID);
			goto finish;
		}
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLIENTIP, clientip, sizeof(clientip), NULL))
		*clientip = '\0';

	if (SUCCEED == (ret = execute_script(scriptid, hostid, eventid, &user, clientip, &result, &debug)))
	{
		zbx_json_addstring(&j, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_PROTO_TAG_DATA, result, ZBX_JSON_TYPE_STRING);

		if (NULL != debug)
			zbx_json_addraw(&j, "debug", debug);

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
	zbx_free(debug);
	zbx_user_free(&user);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
