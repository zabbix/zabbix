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
#include "dbcache.h"
#include "log.h"
#include "proxy.h"
#include "scripts.h"

#include "proxycommand.h"

/******************************************************************************
 *                                                                            *
 * Function: get_proxy_adress                                                 *
 *                                                                            *
 * Purpose: send remote command execution request to proxy ( passive proxy).  *
 *                                                                            *
 * Parameters: proxy_hostid - [IN] id of proxy in data base                   *
 *             address      - [OUT] proxy address (DNS or IP)                 *
 *             port         - [OUT] proxy port                                *
 *                                                                            *
 * Return value: SUCCEED      - proxy address have been acquired successfully *
 *               FAIL         - failed to fetch data from data base           *
 *               NOTSUPPORTED - command forwarding to active proxy is not     *
 *                              supported                                     *
 *                                                                            *
 * Author: Arturs Galapovs                                                    *
 *                                                                            *
 ******************************************************************************/
static int	get_proxy_adress(const zbx_uint64_t proxy_hostid, char *address, unsigned short *port)
{
	const char		*__function_name = "get_proxy_adress";
	int			ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select i.useip, i.ip, i.dns, i.port, h.status"
			" from interface i, hosts h"
			" where i.hostid="ZBX_FS_UI64" and"
			" h.hostid="ZBX_FS_UI64,
			proxy_hostid, proxy_hostid);

	if (NULL != (row = DBfetch(result)))
	{
		/* Forwarding command to active proxy is not supported */
		if (HOST_STATUS_PROXY_PASSIVE != atoi(row[4]))
		{
			ret = NOTSUPPORTED;
			goto not_supported;
		}

		if (1 == atoi(row[0])) /* use IP */
		{
			zbx_strlcpy(address, row[1], INTERFACE_ADDR_LEN_MAX);
		}
		else if (0 == atoi(row[0])) /* use DNS */
		{
			zbx_strlcpy(address, row[2], INTERFACE_ADDR_LEN_MAX);
		}

		*port = (unsigned short)atoi(row[3]);
	}
	else
	{
		ret = FAIL;
	}

not_supported:
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End %s()", __function_name);
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig                                              *
 *                                                                            *
 * Purpose: process remote command execution request                          *
 *                                                                            *
 * Parameters: jp     - [IN] remote command execution request in JSON format: *
 *                           {"request":"command", "operationid":"4",         *
 *                            "hostid":"10105"}                               *
 *             result - [OUT] error description if any                        *
 *                                                                            *
 * Author: Arturs Galapovs                                                    *
 *                                                                            *
 ******************************************************************************/
static int	process_proxycommand(struct zbx_json_parse *jp, char **result)
{
	const char		*__function_name = "process_proxycommand";
	zbx_uint64_t	commandid; /* Either scriptid either operationid */
	char	tmp[MAX_ID_LEN];
	zbx_script_t	script;
	DC_HOST			host;
	char	error[MAX_STRING_LEN];
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	memset(&host, 0, sizeof(host));
	zbx_script_init(&script);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp)) ||
			FAIL == is_uint64(tmp, &host.hostid))
	{
		zbx_strlcpy(error, "Failed to parse command request hostid tag", sizeof(error) );
		goto finish;
	}

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SCRIPTID, tmp, sizeof(tmp)) &&
			SUCCEED == is_uint64(tmp, &commandid))
	{
		script.type = ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT;
		script.scriptid = commandid;
	}
	else if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_OPERATIONID, tmp, sizeof(tmp)) &&
			SUCCEED == is_uint64(tmp, &commandid))
	{
		if (SUCCEED != zbx_operation_to_script(commandid, &host, 0, NULL, &script))
		{
			zbx_strlcpy(error, "Operation conversion to a runnable script failed", sizeof(error) );
			goto finish;
		}
	}
	else
	{
		zbx_strlcpy(error, "Failed to parse command request: requested command id not found", sizeof(error) );
		goto finish;
	}

	ret = zbx_execute_script(&host, &script, result, error, sizeof(error));

finish:
	zbx_script_clean(&script);

	if (FAIL==ret)
		*result = zbx_strdup(*result, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: is_monitored_by_proxy                                            *
 *                                                                            *
 * Purpose: Check if given host is monitored by proxy.                        *
 *                                                                            *
 * Parameters:  hostid          - [IN] id of the host to check                *
 *              proxy_hostid    - [OUT] id of proxy that monitors provided    *
 *                                      host. Ignore this value if FAIL is    *
 *                                      returned                              *
 *                                                                            *
 * Return value:  SUCCEED - host is monitored by proxy                        *
 *                FAIL - host is monitored by server                          *
 *                                                                            *
 ******************************************************************************/
int is_monitored_by_proxy(const zbx_uint64_t hostid, zbx_uint64_t *proxy_hostid)
{
	const char		*__function_name = "is_monitored_by_proxy";
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	*proxy_hostid = 0;

	result = DBselect(
			"select h.proxy_hostid"
			" from hosts h"
			" where h.hostid="ZBX_FS_UI64,
			hostid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
	{
		ZBX_STR2UINT64(*proxy_hostid, row[0]);
		ret = SUCCEED;
	}
	else
	{
		ret = FAIL;
	}

	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_proxycommand                                                *
 *                                                                            *
 * Purpose: send remote command execution request to proxy ( passive proxy).  *
 *                                                                            *
 * Parameters: requester_sock - [IN] socket of original command requester     *
 *                                   (front-end for current implementation).  *
 *                                   NULL if server is an actual requester    *
 *             proxy_hostid   - [IN] id of proxy in data base                 *
 *             jbuffer        - [IN] remote command run request in json       *
 *             error          - [OUT] error description. Either internal,     *
 *                              either on proxy side. Set to NULL to ignore   *
 *             err_len        - [IN] maximum error length                     *
 *                                                                            *
 * Author: Arturs Galapovs                                                    *
 *                                                                            *
 ******************************************************************************/
int	send_proxycommand(zbx_sock_t *requester_sock, const zbx_uint64_t proxy_hostid, const char *jbuffer,
		char *error, const int err_len)
{
	const char		*__function_name = "send_proxycommand";
	char	ip[INTERFACE_ADDR_LEN_MAX];
	unsigned short	port;
	zbx_sock_t	proxy_sock;
	char	*json_proxy_response = NULL; /* proxy response in JSON format */
	char	*error_description = NULL; /* internal error description */
	int	ret, tcp_ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	if (SUCCEED == (ret = get_proxy_adress(proxy_hostid, ip, &port)))
	{
		if (SUCCEED == (tcp_ret = zbx_tcp_connect(&proxy_sock, CONFIG_SOURCE_IP, ip, port, CONFIG_TIMEOUT)))
		{
			if (SUCCEED == (tcp_ret = zbx_tcp_send(&proxy_sock, jbuffer)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Remote command request buffer sent: %s", jbuffer);
				if (SUCCEED == (tcp_ret = zbx_tcp_recv(&proxy_sock)))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "Got a remote command request response=%s", proxy_sock.buffer);
					json_proxy_response = zbx_strdup(json_proxy_response, proxy_sock.buffer);
				}
			}
			zbx_tcp_close(&proxy_sock);
		}
	}

	/* log problems if any */
	if (SUCCEED != ret)
	{
		/* non passive proxy does not support remote command forwarding */
		if(NOTSUPPORTED == ret)
			error_description = zbx_dsprintf(error_description, "Command forwarding to active proxy is not supported. "
					"Proxy id="ZBX_FS_UI64,	proxy_hostid);
		else
			error_description = zbx_dsprintf(error_description, "Failed to get proxy address. Proxy id="ZBX_FS_UI64,
					proxy_hostid);
		zabbix_log(LOG_LEVEL_ERR, "%s", error_description);
	}
	else if (SUCCEED == ret && SUCCEED != tcp_ret)
	{
		error_description = zbx_dsprintf(error_description, "TCP connection problem:%s", zbx_tcp_strerror());
		zabbix_log(LOG_LEVEL_WARNING, "%s", error_description);
	}

	/* passing result via socket */
	if (NULL != requester_sock)
	{
		char *send_message = NULL;
		struct zbx_json json_response;

		zbx_json_init(&json_response, ZBX_JSON_STAT_BUF_LEN );

		if (NULL != json_proxy_response) /* actual response from proxy */
			send_message = json_proxy_response;
		else if (NULL != error_description) /* internal problem */
		{
			zbx_compose_script_response(FAIL, error_description, &json_response);
			send_message = json_response.buffer;
		}

		if (FAIL == zbx_tcp_send_raw(requester_sock, send_message))
			zabbix_log(LOG_LEVEL_WARNING, "Error while sending response: '%s'", zbx_tcp_strerror());
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Sending back command '%s' result '%s'", jbuffer, send_message);

		zbx_json_free(&json_response);
	}

	/* passing error description via argument */
	if (NULL != error)
	{
		error[0] = '\0';

		if (NULL != error_description)
			zbx_strlcpy(error, error_description, err_len);
		else if (NULL != json_proxy_response)
		{
			struct zbx_json_parse jp;

			zbx_json_open(json_proxy_response, &jp);
			zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, error, err_len);
		}
	}

	zbx_free(json_proxy_response);
	zbx_free(error_description);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	return (SUCCEED == ret && SUCCEED == tcp_ret) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: recv_proxycommand                                                *
 *                                                                            *
 * Purpose: receive remote command execution request from server              *
 *                                                                            *
 * Author: Arturs Galapovs                                                    *
 *                                                                            *
 ******************************************************************************/
int	recv_proxycommand(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "recv_proxycommand";
	int			ret;
	char	*result = NULL;
	struct zbx_json	response;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	zbx_json_init(&response, ZBX_JSON_STAT_BUF_LEN);

	ret = process_proxycommand(jp, &result);
	zbx_compose_script_response(ret, result, &response);

	if (FAIL == zbx_tcp_send(sock, response.buffer))
		zabbix_log(LOG_LEVEL_WARNING, "Error while sending remote command response to server");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Proxy sending remote command execution response '%s'", response.buffer);

	zbx_json_free(&response);
	zbx_free(result);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	return ret;
}
