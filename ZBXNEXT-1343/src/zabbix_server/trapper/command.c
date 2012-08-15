/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "command.h"
#include "comms.h"
#include "zbxserver.h"
#include "db.h"
#include "log.h"
#include "../scripts.h"

/******************************************************************************
 *                                                                            *
 * Function: execute_script                                                   *
 *                                                                            *
 * Purpose: execute command                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	execute_script(zbx_uint64_t scriptid, zbx_uint64_t hostid, char **result)
{
	const char	*__function_name = "execute_script";
	char		error[MAX_STRING_LEN];
	int		ret = FAIL, rc;
	DC_HOST		host;
	DB_RESULT	db_result;
	DB_ROW		db_row;
	zbx_script_t	script;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() scriptid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64,
			__function_name, scriptid, hostid);

	*error = '\0';

	if (SUCCEED != (rc = DCget_host_by_hostid(&host, hostid)))
	{
		/* let's try to get a host from a database (the host can be disabled) */
		db_result = DBselect("select host,name from hosts where hostid=" ZBX_FS_UI64, hostid);

		if (NULL != (db_row = DBfetch(db_result)))
		{
			memset(&host, 0, sizeof(host));
			host.hostid = hostid;
			strscpy(host.host, db_row[0]);
			strscpy(host.name, db_row[1]);

			rc = SUCCEED;
		}
		DBfree_result(db_result);
	}

	if (SUCCEED != rc)
	{
		zbx_snprintf(error, sizeof(error), "Unknown Host ID [" ZBX_FS_UI64 "]", hostid);
		goto fail;
	}

	zbx_script_init(&script);

	script.type = ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT;
	script.scriptid = scriptid;

	ret = zbx_execute_script(&host, &script, result, error, sizeof(error));

	zbx_script_clean(&script);
fail:
	if (SUCCEED != ret)
		*result = zbx_strdup(*result, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_command                                                  *
 *                                                                            *
 * Purpose: process command received from frontend                            *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	process_command(zbx_sock_t *sock, const char *data, struct zbx_json_parse *jp)
{
	char		*result = NULL, *send, tmp[64];
	const char	*response;
	int		ret = FAIL;
	zbx_uint64_t	scriptid, hostid;
	struct zbx_json	j;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_command()");

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SCRIPTID, tmp, sizeof(tmp)))
		return FAIL;
	ZBX_STR2UINT64(scriptid, tmp);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp)))
		return FAIL;
	ZBX_STR2UINT64(hostid, tmp);

	zbx_json_init(&j, 256);

	ret = execute_script(scriptid, hostid, &result);

	response = (FAIL == ret) ? ZBX_PROTO_VALUE_FAILED : ZBX_PROTO_VALUE_SUCCESS;

	zbx_json_addstring(&j, ZBX_PROTO_TAG_RESPONSE, response, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_VALUE, result, ZBX_JSON_TYPE_STRING);
	send = j.buffer;

	alarm(CONFIG_TIMEOUT);
	if (SUCCEED != zbx_tcp_send_raw(sock, send))
		zabbix_log(LOG_LEVEL_WARNING, "Error sending back the result of command execution");
	alarm(0);

	zbx_json_free(&j);
	zbx_free(result);

	return SUCCEED;
}
