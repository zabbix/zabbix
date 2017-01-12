/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#include "comms.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxserver.h"
#include "dbcache.h"
#include "proxy.h"
#include "zbxself.h"

#include "../nodewatcher/nodecomms.h"
#include "../nodewatcher/nodesender.h"
#include "nodesync.h"
#include "nodehistory.h"
#include "trapper.h"
#include "active.h"
#include "nodecommand.h"
#include "proxyconfig.h"
#include "proxydiscovery.h"
#include "proxyautoreg.h"
#include "proxyhosts.h"

#include "daemon.h"

extern unsigned char	daemon_type;
extern unsigned char	process_type;

zbx_timespec_t	recv_timespec;	/* for tracking time difference between sender and receiver */

/******************************************************************************
 *                                                                            *
 * Function: recv_agenthistory                                                *
 *                                                                            *
 * Purpose: processes the received values from active agents and senders      *
 *                                                                            *
 ******************************************************************************/
static void	recv_agenthistory(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char	*__function_name = "recv_agenthistory";
	char		info[128];
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = process_hist_data(sock, jp, 0, info, sizeof(info));

	zbx_send_response(sock, ret, info, CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: recv_proxyhistory                                                *
 *                                                                            *
 * Purpose: processes the received values from active proxies                 *
 *                                                                            *
 ******************************************************************************/
static void	recv_proxyhistory(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char	*__function_name = "recv_proxyhistory";
	zbx_uint64_t	proxy_hostid;
	char		host[HOST_HOST_LEN_MAX], info[128], error[256];
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == (ret = get_proxy_id(jp, &proxy_hostid, host, error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "History data from active proxy on [%s] failed: %s",
				get_ip_by_socket(sock), error);
		goto exit;
	}

	update_proxy_lastaccess(proxy_hostid);

	ret = process_hist_data(sock, jp, proxy_hostid, info, sizeof(info));
exit:
	zbx_send_response(sock, ret, info, CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: send_proxyhistory                                                *
 *                                                                            *
 * Purpose: send history data to a Zabbix server                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	send_proxyhistory(zbx_sock_t *sock)
{
	const char	*__function_name = "send_proxyhistory";

	struct zbx_json	j;
	zbx_uint64_t	lastid;
	zbx_timespec_t	timespec;
	int		records;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	records = proxy_get_hist_data(&j, &lastid);

	zbx_json_close(&j);

	zbx_timespec(&timespec);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, timespec.sec);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_NS, timespec.ns);

	if (FAIL == zbx_tcp_send_to(sock, j.buffer, CONFIG_TIMEOUT))
		zabbix_log(LOG_LEVEL_WARNING, "Error while sending availability of hosts. %s",
				zbx_tcp_strerror());
	else if (SUCCEED == zbx_recv_response(sock, NULL, 0, CONFIG_TIMEOUT) && 0 != records)
		proxy_set_hist_lastid(lastid);

	zbx_json_free(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: recv_proxy_heartbeat                                             *
 *                                                                            *
 * Purpose: process heartbeat sent by proxy servers                           *
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
static void	recv_proxy_heartbeat(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char	*__function_name = "recv_proxy_heartbeat";

	zbx_uint64_t	proxy_hostid;
	char		host[HOST_HOST_LEN_MAX], error[256];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == get_proxy_id(jp, &proxy_hostid, host, error, sizeof(error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Heartbeat from active proxy on [%s] failed: %s",
				get_ip_by_socket(sock), error);
		return;
	}

	update_proxy_lastaccess(proxy_hostid);

	zbx_send_response(sock, SUCCEED, NULL, CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	process_trap(zbx_sock_t	*sock, char *s, int max_len)
{
	char	*pl, *pr, *data, value_dec[MAX_BUFFER_LEN];
	char	lastlogsize[ZBX_MAX_UINT64_LEN], timestamp[11], source[HISTORY_LOG_SOURCE_LEN_MAX], severity[11];
	int	sender_nodeid, nodeid;
	char	*answer;

	int	ret = SUCCEED, res;
	size_t	datalen;

	struct 		zbx_json_parse jp;
	char		value[MAX_STRING_LEN];
	AGENT_VALUE	av;

	memset(&av, 0, sizeof(AGENT_VALUE));

	zbx_rtrim(s, " \r\n");

	datalen = strlen(s);
	zabbix_log(LOG_LEVEL_DEBUG, "Trapper got [%s] len " ZBX_FS_SIZE_T, s, (zbx_fs_size_t)datalen);

	if (0 == strncmp(s, "ZBX_GET_ACTIVE_CHECKS", 21))	/* request for list of active checks */
	{
		ret = send_list_of_active_checks(sock, s);
	}
	else if (strncmp(s, "ZBX_GET_HISTORY_LAST_ID", 23) == 0) /* request for last IDs */
	{
		send_history_last_id(sock, s);
		return ret;
	}
	else
	{
		if (0 == strncmp(s, "Data", 4))	/* node data exchange */
		{
			node_sync_lock(0);

			res = node_sync(s, &sender_nodeid, &nodeid);
			if (FAIL == res)
			{
				alarm(CONFIG_TIMEOUT);
				send_data_to_node(sender_nodeid, sock, "FAIL");
				alarm(0);
			}
			else
			{
				res = calculate_checksums(nodeid, NULL, 0);
				if (SUCCEED == res && NULL != (data = DMget_config_data(nodeid, ZBX_NODE_SLAVE)))
				{
					zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Sending configuration changes"
							" to slave node %d for node %d datalen " ZBX_FS_SIZE_T,
							CONFIG_NODEID,
							sender_nodeid,
							nodeid,
							(zbx_fs_size_t)strlen(data));
					alarm(CONFIG_TRAPPER_TIMEOUT);
					res = send_data_to_node(sender_nodeid, sock, data);
					zbx_free(data);
					if (SUCCEED == res)
						res = recv_data_from_node(sender_nodeid, sock, &answer);
					if (SUCCEED == res && 0 == strcmp(answer, "OK"))
						res = update_checksums(nodeid, ZBX_NODE_SLAVE, SUCCEED, NULL, 0, NULL);
					alarm(0);
				}
			}

			node_sync_unlock(0);

			return ret;
		}
		else if (0 == strncmp(s, "History", 7))	/* slave node history */
		{
			const char	*reply;

			reply = (SUCCEED == node_history(s, datalen) ? "OK" : "FAIL");

			alarm(CONFIG_TIMEOUT);
			if (SUCCEED != zbx_tcp_send_raw(sock, reply))
				zabbix_log(LOG_LEVEL_WARNING, "Error sending %s to node", reply);
			alarm(0);

			return ret;
		}
		else if (SUCCEED == zbx_json_open(s, &jp))	/* JSON protocol */
		{
			if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_REQUEST, value, sizeof(value)))
			{
				if (0 == strcmp(value, ZBX_PROTO_VALUE_PROXY_CONFIG))
				{
					if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
						send_proxyconfig(sock, &jp);
					else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY_PASSIVE))
					{
						zabbix_log(LOG_LEVEL_WARNING, "Received configuration data from server."
								" Datalen " ZBX_FS_SIZE_T, (zbx_fs_size_t)datalen);
						recv_proxyconfig(sock, &jp);
					}
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_AGENT_DATA) ||
					0 == strcmp(value, ZBX_PROTO_VALUE_SENDER_DATA))
				{
					recv_agenthistory(sock, &jp);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_HISTORY_DATA))
				{
					if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
						recv_proxyhistory(sock, &jp);
					else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY_PASSIVE))
						send_proxyhistory(sock);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_DISCOVERY_DATA))
				{
					if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
						recv_discovery_data(sock, &jp);
					else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY_PASSIVE))
						send_discovery_data(sock);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_AUTO_REGISTRATION_DATA))
				{
					if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
						recv_areg_data(sock, &jp);
					else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY_PASSIVE))
						send_areg_data(sock);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_PROXY_HEARTBEAT))
				{
					if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
						recv_proxy_heartbeat(sock, &jp);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS))
				{
					ret = send_list_of_active_checks_json(sock, &jp);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_HOST_AVAILABILITY))
				{
					if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
						recv_host_availability(sock, &jp);
					else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY_PASSIVE))
						send_host_availability(sock);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_COMMAND))
				{
					ret = node_process_command(sock, s, &jp);
				}
				else
					zabbix_log(LOG_LEVEL_WARNING, "unknown request received [%s]", value);
			}
			return ret;
		}
		else if ('<' == *s)	/* XML protocol */
		{
			comms_parse_response(s, av.host_name, sizeof(av.host_name), av.key, sizeof(av.key), value_dec,
					sizeof(value_dec), lastlogsize, sizeof(lastlogsize), timestamp, sizeof(timestamp),
					source, sizeof(source),	severity, sizeof(severity));

			av.value	= value_dec;
			if (SUCCEED != is_uint64(lastlogsize, &av.lastlogsize))
				av.lastlogsize = 0;
			av.timestamp	= atoi(timestamp);
			av.source	= source;
			av.severity	= atoi(severity);
		}
		else
		{
			pl = s;
			if (NULL == (pr = strchr(pl, ':')))
				return FAIL;

			*pr = '\0';
			zbx_strlcpy(av.host_name, pl, sizeof(av.host_name));
			*pr = ':';

			pl = pr + 1;
			if (NULL == (pr = strchr(pl, ':')))
				return FAIL;

			*pr = '\0';
			zbx_strlcpy(av.key, pl, sizeof(av.key));
			*pr = ':';

			av.value	= pr + 1;
			av.severity	= 0;
		}

		zbx_timespec(&av.ts);

		if (0 == strcmp(av.value, ZBX_NOTSUPPORTED))
			av.status = ITEM_STATUS_NOTSUPPORTED;

		process_mass_data(sock, 0, &av, 1, NULL);

		alarm(CONFIG_TIMEOUT);
		if (SUCCEED != zbx_tcp_send_raw(sock, SUCCEED == ret ? "OK" : "NOT OK"))
			zabbix_log(LOG_LEVEL_WARNING, "Error sending result back");
		alarm(0);
	}
	return ret;
}

static void	process_trapper_child(zbx_sock_t *sock)
{
	char	*data;

	if (SUCCEED != zbx_tcp_recv_to(sock, &data, CONFIG_TRAPPER_TIMEOUT))
		return;

	zbx_timespec(&recv_timespec);

	process_trap(sock, data, sizeof(data));
}

void	main_trapper_loop(zbx_sock_t *s)
{
	const char	*__function_name = "main_trapper_loop";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [waiting for connection]", get_process_type_string(process_type));

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED == zbx_tcp_accept(s))
		{
			update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

			zbx_setproctitle("%s [processing data]", get_process_type_string(process_type));

			process_trapper_child(s);

			zbx_tcp_unaccept(s);
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "Trapper failed to accept connection");
	}
}
