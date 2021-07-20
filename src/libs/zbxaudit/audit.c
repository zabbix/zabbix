/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "log.h"
#include "zbxjson.h"
#include "dbcache.h"

#include "audit.h"

#define AUDIT_RESOURCE_SCRIPT		25

#define AUDIT_ACTION_EXECUTE		7

static void add_str_json(struct zbx_json *json, const char *key, const char *val)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, "add", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, NULL, val, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Function: auditlog_global_script                                           *
 *                                                                            *
 * Purpose: record global script execution results into audit log             *
 *                                                                            *
 * Comments: 'hostid' should be always > 0. 'eventid' is > 0 in case of       *
 *           "manual script on event"                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_auditlog_global_script(unsigned char script_type, unsigned char script_execute_on,
		const char *script_command_orig, zbx_uint64_t hostid, const char *hostname, zbx_uint64_t eventid,
		zbx_uint64_t proxy_hostid, zbx_uint64_t userid, const char *username, const char *clientip,
		const char *output, const char *error)
{
	int	ret = SUCCEED;
	char	auditid_cuid[CUID_LEN], execute_on_s[MAX_ID_LEN + 1], hostid_s[MAX_ID_LEN + 1],
		eventid_s[MAX_ID_LEN + 1], proxy_hostid_s[MAX_ID_LEN + 1];

	struct zbx_json	details_json;
	zbx_config_t	cfg;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED);

	if (ZBX_AUDITLOG_ENABLED != cfg.auditlog_enabled)
		goto out;

	zbx_new_cuid(auditid_cuid);

	zbx_json_init(&details_json, ZBX_JSON_STAT_BUF_LEN);

	zbx_snprintf(execute_on_s, sizeof(execute_on_s), "%hhu", script_execute_on);

	add_str_json(&details_json, "script.execute_on", execute_on_s);

	if (0 != eventid)
	{
		zbx_snprintf(eventid_s, sizeof(eventid_s), ZBX_FS_UI64, eventid);
		add_str_json(&details_json, "script.eventid", eventid_s);
	}

	zbx_snprintf(hostid_s, sizeof(hostid_s), ZBX_FS_UI64, hostid);
	add_str_json(&details_json, "script.hostid", hostid_s);

	if (0 != proxy_hostid)
	{
		zbx_snprintf(proxy_hostid_s, sizeof(proxy_hostid_s), ZBX_FS_UI64, proxy_hostid);
		add_str_json(&details_json, "script.proxy_hostid", proxy_hostid_s);
	}

	if (ZBX_SCRIPT_TYPE_WEBHOOK != script_type)
		add_str_json(&details_json, "script.command", script_command_orig);

	if (NULL != output)
	{
		char	*output_esc;

		output_esc = zbx_strdup(NULL, output);
		zbx_json_escape(&output_esc);
		add_str_json(&details_json, "script.output", output_esc);
		zbx_free(output_esc);
	}

	if (NULL != error)
		add_str_json(&details_json, "script.error", error);

	if (ZBX_DB_OK > DBexecute("insert into auditlog (auditid,userid,username,clock,action,ip,resourceid,"
			"resourcename,resourcetype,recordsetid,details) values ('%s'," ZBX_FS_UI64 ",'%s',%d,'%d','%s',"
			ZBX_FS_UI64 ",'%s',%d,'%s','%s' )", auditid_cuid, userid, username, (int)time(NULL),
			AUDIT_ACTION_EXECUTE, clientip, hostid, hostname, AUDIT_RESOURCE_SCRIPT, auditid_cuid,
			details_json.buffer))
	{
		ret = FAIL;
	}

	zbx_json_free(&details_json);
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
