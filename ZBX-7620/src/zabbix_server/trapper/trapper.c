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
extern int		process_num;

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
	char		host[HOST_HOST_LEN_MAX], error[256];
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	error[0] = '\0';

	if (SUCCEED != (ret = get_active_proxy_id(jp, &proxy_hostid, host, error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "history data from active proxy on \"%s\" failed: %s",
				get_ip_by_socket(sock), error);
		goto out;
	}

	update_proxy_lastaccess(proxy_hostid);

	ret = process_hist_data(sock, jp, proxy_hostid, error, sizeof(error));
out:
	zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);

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
	int		records;
	char		*info = NULL, *error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	records = proxy_get_hist_data(&j, &lastid);

	zbx_json_close(&j);

	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (SUCCEED != zbx_tcp_send_to(sock, j.buffer, CONFIG_TIMEOUT))
	{
		zabbix_log(LOG_LEVEL_WARNING, "error while sending history data to server: %s", zbx_tcp_strerror());
		goto out;
	}

	if (SUCCEED != zbx_recv_response(sock, &info, CONFIG_TIMEOUT, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "sending history data to server: error:\"%s\", info:\"%s\"",
				ZBX_NULL2EMPTY_STR(error), ZBX_NULL2EMPTY_STR(info));
		goto out;
	}

	if (0 != records)
		proxy_set_hist_lastid(lastid);
out:
	zbx_json_free(&j);
	zbx_free(info);
	zbx_free(error);

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
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	error[0] = '\0';

	if (SUCCEED != (ret = get_active_proxy_id(jp, &proxy_hostid, host, error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "heartbeat from active proxy on \"%s\" failed: %s",
				get_ip_by_socket(sock), error);
		goto out;
	}

	update_proxy_lastaccess(proxy_hostid);
out:
	zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#define ZBX_GET_QUEUE_OVERVIEW		0
#define ZBX_GET_QUEUE_PROXY		1
#define ZBX_GET_QUEUE_DETAILS		2

/* queue stats split by delay times */
typedef struct
{
	zbx_uint64_t	id;
	int		delay5;
	int		delay10;
	int		delay30;
	int		delay60;
	int		delay300;
	int		delay600;
}
zbx_queue_stats_t;

/******************************************************************************
 *                                                                            *
 * Function: queue_stats_update                                               *
 *                                                                            *
 * Purpose: update queue stats with a new item delay                          *
 *                                                                            *
 * Parameters: stats   - [IN] the queue stats                                 *
 *             delay   - [IN] the delay time of an delayed item               *
 *                                                                            *
 ******************************************************************************/
static void	queue_stats_update(zbx_queue_stats_t *stats, int delay)
{
	if (10 >= delay)
		stats->delay5++;
	else if (30 >= delay)
		stats->delay10++;
	else if (60 >= delay)
		stats->delay30++;
	else if (300 >= delay)
		stats->delay60++;
	else if (600 >= delay)
		stats->delay300++;
	else
		stats->delay600++;
}

/******************************************************************************
 *                                                                            *
 * Function: queue_stats_export                                               *
 *                                                                            *
 * Purpose: export queue stats to JSON format                                 *
 *                                                                            *
 * Parameters: queue_stats - [IN] a hashset containing item stats             *
 *             id_name     - [IN] the name of stats id field                  *
 *             json        - [OUT] the output JSON                            *
 *                                                                            *
 ******************************************************************************/
static void	queue_stats_export(zbx_hashset_t *queue_stats, const char *id_name, struct zbx_json *json)
{
	zbx_hashset_iter_t	iter;
	zbx_queue_stats_t	*stats;

	zbx_json_addarray(json, ZBX_PROTO_TAG_DATA);

	zbx_hashset_iter_reset(queue_stats, &iter);

	while (NULL != (stats = zbx_hashset_iter_next(&iter)))
	{
		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, id_name, stats->id);
		zbx_json_adduint64(json, "delay5", stats->delay5);
		zbx_json_adduint64(json, "delay10", stats->delay10);
		zbx_json_adduint64(json, "delay30", stats->delay30);
		zbx_json_adduint64(json, "delay60", stats->delay60);
		zbx_json_adduint64(json, "delay300", stats->delay300);
		zbx_json_adduint64(json, "delay600", stats->delay600);
		zbx_json_close(json);
	}

	zbx_json_close(json);
}

/* queue item comparison function used to sort queue by nextcheck */
static int	queue_compare_by_nextcheck_asc(void **d1, void **d2)
{
	zbx_queue_item_t	*i1 = *d1, *i2 = *d2;

	return i1->nextcheck - i2->nextcheck;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_session_validate                                             *
 *                                                                            *
 * Purpose: validates active session by access level                          *
 *                                                                            *
 * Parameters:  sessionid    - [IN] the session id to validate                *
 *              access_level - [IN] the required access rights                *
 *                                                                            *
 * Return value:  SUCCEED - the session is active and user has the required   *
 *                          access rights.                                    *
 *                FAIL    - the session is not active or usr has not enough   *
 *                          access rights.                                    *
 *                                                                            *
 ******************************************************************************/
static int	zbx_session_validate(const char *sessionid, int access_level)
{
	char		*sessionid_esc;
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;

	sessionid_esc = DBdyn_escape_string(sessionid);

	result = DBselect(
			"select null"
			" from users u,sessions s"
			" where u.userid=s.userid"
				" and s.status=%d"
				" and s.sessionid='%s'"
				" and u.type>=%d",
			ZBX_SESSION_ACTIVE, sessionid_esc, access_level);

	if (NULL != (row = DBfetch(result)))
		ret = SUCCEED;
	DBfree_result(result);

	zbx_free(sessionid_esc);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: recv_getqueue                                                    *
 *                                                                            *
 * Purpose: process queue request                                             *
 *                                                                            *
 * Parameters:  sock  - [IN] the request socket                               *
 *              jp    - [IN] the request data                                 *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	recv_getqueue(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "recv_getqueue";
	int			ret = FAIL, request_type = -1, now, i;
	char			type[MAX_STRING_LEN], sessionid[MAX_STRING_LEN];
	zbx_vector_ptr_t	queue;
	struct zbx_json		json;
	zbx_hashset_t		queue_stats;
	zbx_queue_stats_t	*stats;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SID, sessionid, sizeof(sessionid)) ||
		FAIL == zbx_session_validate(sessionid, USER_TYPE_SUPER_ADMIN))
	{
		zbx_send_response_raw(sock, ret, "Permission denied.", CONFIG_TIMEOUT);
		goto out;
	}

	if (FAIL != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_TYPE, type, sizeof(type)))
	{
		if (0 == strcmp(type, ZBX_PROTO_VALUE_GET_QUEUE_OVERVIEW))
			request_type = ZBX_GET_QUEUE_OVERVIEW;
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_GET_QUEUE_PROXY))
			request_type = ZBX_GET_QUEUE_PROXY;
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_GET_QUEUE_DETAILS))
			request_type = ZBX_GET_QUEUE_DETAILS;
	}

	if (-1 == request_type)
	{
		zbx_send_response_raw(sock, ret, "Unsupported request type.", CONFIG_TIMEOUT);
		goto out;
	}

	now = time(NULL);
	zbx_vector_ptr_create(&queue);
	DCget_item_queue(&queue, 6, -1);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	switch (request_type)
	{
		case ZBX_GET_QUEUE_OVERVIEW:
			zbx_hashset_create(&queue_stats, 32, ZBX_DEFAULT_UINT64_HASH_FUNC,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			/* gather queue stats by item type */
			for (i = 0; i < queue.values_num; i++)
			{
				zbx_queue_item_t	*item = queue.values[i];
				zbx_uint64_t		id = item->type;

				if (NULL == (stats = zbx_hashset_search(&queue_stats, &id)))
				{
					zbx_queue_stats_t	data = {id};

					stats = zbx_hashset_insert(&queue_stats, &data, sizeof(data));
				}
				queue_stats_update(stats, now - item->nextcheck);
			}

			zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS,
					ZBX_JSON_TYPE_STRING);
			queue_stats_export(&queue_stats, "itemtype", &json);
			zbx_hashset_destroy(&queue_stats);

			break;
		case ZBX_GET_QUEUE_PROXY:
			zbx_hashset_create(&queue_stats, 32, ZBX_DEFAULT_UINT64_HASH_FUNC,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			/* gather queue stats by proxy hostid */
			for (i = 0; i < queue.values_num; i++)
			{
				zbx_queue_item_t	*item = queue.values[i];
				zbx_uint64_t		id = item->proxy_hostid;

				if (NULL == (stats = zbx_hashset_search(&queue_stats, &id)))
				{
					zbx_queue_stats_t	data = {id};

					stats = zbx_hashset_insert(&queue_stats, &data, sizeof(data));
				}
				queue_stats_update(stats, now - item->nextcheck);
			}

			zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS,
					ZBX_JSON_TYPE_STRING);
			queue_stats_export(&queue_stats, "proxyid", &json);
			zbx_hashset_destroy(&queue_stats);

			break;
		case ZBX_GET_QUEUE_DETAILS:
			zbx_vector_ptr_sort(&queue, (zbx_compare_func_t)queue_compare_by_nextcheck_asc);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS,
					ZBX_JSON_TYPE_STRING);
			zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

			for (i = 0; i < queue.values_num && i <= 500; i++)
			{
				zbx_queue_item_t	*item = queue.values[i];

				zbx_json_addobject(&json, NULL);
				zbx_json_adduint64(&json, "itemid", item->itemid);
				zbx_json_adduint64(&json, "nextcheck", item->nextcheck);
				zbx_json_close(&json);
			}

			zbx_json_close(&json);

			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() json.buffer:'%s'", __function_name, json.buffer);

	zbx_tcp_send_raw(sock, json.buffer);

	DCfree_item_queue(&queue);
	zbx_vector_ptr_destroy(&queue);

	zbx_json_free(&json);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

static void	active_passive_misconfig(zbx_sock_t *sock)
{
	const char	*msg = "misconfiguration error: the proxy is running in the active mode but server sends "
			"requests to it as to proxy in passive mode";

	zabbix_log(LOG_LEVEL_WARNING, "%s", msg);
	zbx_send_response(sock, FAIL, msg, CONFIG_TIMEOUT);
}

static int	process_trap(zbx_sock_t	*sock, char *s)
{
	int	ret = SUCCEED;

	zbx_rtrim(s, " \r\n");

	zabbix_log(LOG_LEVEL_DEBUG, "trapper got '%s'", s);

	if ('{' == *s)	/* JSON protocol */
	{
		struct zbx_json_parse	jp;
		char			value[MAX_STRING_LEN];

		if (SUCCEED != zbx_json_open(s, &jp))
		{
			zbx_send_response(sock, FAIL, zbx_json_strerror(), CONFIG_TIMEOUT);
			zabbix_log(LOG_LEVEL_WARNING, "received invalid JSON object from %s: %s",
					get_ip_by_socket(sock), zbx_json_strerror());
			return FAIL;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_REQUEST, value, sizeof(value)))
		{
			if (0 == strcmp(value, ZBX_PROTO_VALUE_PROXY_CONFIG))
			{
				if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
				{
					send_proxyconfig(sock, &jp);
				}
				else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY_PASSIVE))
				{
					zabbix_log(LOG_LEVEL_WARNING, "Received configuration data from server."
							" Datalen " ZBX_FS_SIZE_T,
							(zbx_fs_size_t)(jp.end - jp.start + 1));
					recv_proxyconfig(sock, &jp);
				}
				else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY_ACTIVE))
				{
					/* This is a misconfiguration: the proxy is configured in active mode */
					/* but server sends requests to it as to a proxy in passive mode. To  */
					/* prevent logging of this problem for every request we report it     */
					/* only when the server sends configuration to the proxy and ignore   */
					/* it for other requests.                                             */
					active_passive_misconfig(sock);
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
			else if (0 == strcmp(value, ZBX_PROTO_VALUE_GET_QUEUE))
			{
				if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
					ret = recv_getqueue(sock, &jp);
			}
			else
				zabbix_log(LOG_LEVEL_WARNING, "unknown request received [%s]", value);
		}
	}
	else if (0 == strncmp(s, "ZBX_GET_ACTIVE_CHECKS", 21))	/* request for list of active checks */
	{
		ret = send_list_of_active_checks(sock, s);
	}
	else if (0 == strncmp(s, "ZBX_GET_HISTORY_LAST_ID", 23)) /* request for last IDs */
	{
		send_history_last_id(sock, s);
	}
	else if (0 == strncmp(s, "Data", 4))	/* node data exchange */
	{
		int	res, nodeid, sender_nodeid;
		char	*data, *answer;

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
				zabbix_log(LOG_LEVEL_WARNING, "NODE %d: sending configuration changes"
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
	}
	else if (0 == strncmp(s, "History", 7))	/* slave node history */
	{
		const char	*reply;

		reply = (SUCCEED == node_history(s, strlen(s)) ? "OK" : "FAIL");

		alarm(CONFIG_TIMEOUT);
		if (SUCCEED != zbx_tcp_send_raw(sock, reply))
			zabbix_log(LOG_LEVEL_WARNING, "cannot send %s to node", reply);
		alarm(0);
	}
	else
	{
		char		value_dec[MAX_BUFFER_LEN], lastlogsize[ZBX_MAX_UINT64_LEN], timestamp[11],
				source[HISTORY_LOG_SOURCE_LEN_MAX], severity[11];
		AGENT_VALUE	av;

		memset(&av, 0, sizeof(AGENT_VALUE));

		if ('<' == *s)	/* XML protocol */
		{
			comms_parse_response(s, av.host_name, sizeof(av.host_name), av.key, sizeof(av.key), value_dec,
					sizeof(value_dec), lastlogsize, sizeof(lastlogsize), timestamp,
					sizeof(timestamp), source, sizeof(source), severity, sizeof(severity));

			av.value = value_dec;
			if (SUCCEED != is_uint64(lastlogsize, &av.lastlogsize))
				av.lastlogsize = 0;
			av.timestamp = atoi(timestamp);
			av.source = source;
			av.severity = atoi(severity);
		}
		else
		{
			char	*pl, *pr;

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

			av.value = pr + 1;
			av.severity = 0;
		}

		zbx_timespec(&av.ts);

		if (0 == strcmp(av.value, ZBX_NOTSUPPORTED))
			av.state = ITEM_STATE_NOTSUPPORTED;

		process_mass_data(sock, 0, &av, 1, NULL);

		alarm(CONFIG_TIMEOUT);
		if (SUCCEED != zbx_tcp_send_raw(sock, "OK"))
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

	process_trap(sock, data);
}

void	main_trapper_loop(zbx_sock_t *s)
{
	double		sec = 0.0;

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s #%d [processed data in " ZBX_FS_DBL " sec, waiting for connection]",
				get_process_type_string(process_type), process_num, sec);

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED == zbx_tcp_accept(s))
		{
			update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

			zbx_setproctitle("%s #%d [processing data]", get_process_type_string(process_type),
					process_num);

			sec = zbx_time();
			process_trapper_child(s);
			sec = zbx_time() - sec;

			zbx_tcp_unaccept(s);
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "Trapper failed to accept connection");
	}
}
