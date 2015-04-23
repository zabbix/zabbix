/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "daemon.h"
#include "comms.h"
#include "zbxself.h"

#include "proxypoller.h"
#include "zbxserver.h"
#include "dbcache.h"
#include "db.h"
#include "zbxjson.h"
#include "log.h"
#include "proxy.h"

extern unsigned char	process_type, daemon_type;
extern int		server_num, process_num;

static int	connect_to_proxy(DC_PROXY *proxy, zbx_socket_t *sock, int timeout)
{
	const char	*__function_name = "connect_to_proxy";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() address:%s port:%hu timeout:%d", __function_name, proxy->addr,
			proxy->port, timeout);

	if (FAIL == (ret = zbx_tcp_connect(sock, CONFIG_SOURCE_IP, proxy->addr, proxy->port, timeout)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot connect to proxy \"%s\": %s", proxy->host, zbx_socket_strerror());
		ret = NETWORK_ERROR;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	send_data_to_proxy(DC_PROXY *proxy, zbx_socket_t *sock, const char *data)
{
	const char	*__function_name = "send_data_to_proxy";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, data);

	if (FAIL == (ret = zbx_tcp_send(sock, data)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot send data to proxy \"%s\": %s", proxy->host, zbx_socket_strerror());

		ret = NETWORK_ERROR;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	recv_data_from_proxy(DC_PROXY *proxy, zbx_socket_t *sock)
{
	const char	*__function_name = "recv_data_from_proxy";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == (ret = zbx_tcp_recv(sock)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot obtain data from proxy \"%s\": %s", proxy->host,
				zbx_socket_strerror());
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "obtained data from proxy \"%s\": %s", proxy->host, sock->buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	disconnect_proxy(zbx_socket_t *sock)
{
	const char	*__function_name = "disconnect_proxy";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_tcp_close(sock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_data_from_proxy                                              *
 *                                                                            *
 * Purpose: get historical data from proxy                                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_data_from_proxy(DC_PROXY *proxy, const char *request, char **data)
{
	const char	*__function_name = "get_data_from_proxy";
	zbx_socket_t	s;
	struct zbx_json	j;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() request:'%s'", __function_name, request);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&j, "request", request, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == (ret = connect_to_proxy(proxy, &s, CONFIG_TRAPPER_TIMEOUT)))
	{
		if (SUCCEED == (ret = send_data_to_proxy(proxy, &s, j.buffer)))
			if (SUCCEED == (ret = recv_data_from_proxy(proxy, &s)))
				if (SUCCEED == (ret = zbx_send_response(&s, SUCCEED, NULL, 0)))
					*data = zbx_strdup(*data, s.buffer);

		disconnect_proxy(&s);
	}

	zbx_json_free(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxy                                                    *
 *                                                                            *
 * Purpose: retrieve values of metrics from monitored hosts                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_proxy(void)
{
	const char		*__function_name = "process_proxy";
	DC_PROXY		proxy;
	int			num, i, ret;
	struct zbx_json		j;
	struct zbx_json_parse	jp, jp_data;
	zbx_socket_t		s;
	char			*answer = NULL, *port = NULL;
	time_t			now;
	unsigned char		update_nextcheck;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == (num = DCconfig_get_proxypoller_hosts(&proxy, 1)))
		goto exit;

	now = time(NULL);

	zbx_json_init(&j, 512 * 1024);

	for (i = 0; i < num; i++)
	{
		update_nextcheck = 0;

		if (proxy.proxy_config_nextcheck <= now)
			update_nextcheck |= 0x01;
		if (proxy.proxy_data_nextcheck <= now)
			update_nextcheck |= 0x02;

		proxy.addr = proxy.addr_orig;

		port = zbx_strdup(port, proxy.port_orig);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&port, MACRO_TYPE_COMMON, NULL, 0);
		if (FAIL == is_ushort(port, &proxy.port))
		{
			zabbix_log(LOG_LEVEL_ERR, "invalid proxy \"%s\" port: \"%s\"", proxy.host, port);
			goto network_error;
		}

		if (proxy.proxy_config_nextcheck <= now)
		{
			char	*error = NULL;

			zbx_json_clean(&j);

			zbx_json_addstring(&j, ZBX_PROTO_TAG_REQUEST,
					ZBX_PROTO_VALUE_PROXY_CONFIG, ZBX_JSON_TYPE_STRING);
			zbx_json_addobject(&j, ZBX_PROTO_TAG_DATA);

			if (SUCCEED != (ret = get_proxyconfig_data(proxy.hostid, &j, &error)))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot collect configuration data for proxy \"%s\": %s",
						proxy.host, error);
				zbx_free(error);

				goto network_error;
			}

			if (SUCCEED == (ret = connect_to_proxy(&proxy, &s, CONFIG_TRAPPER_TIMEOUT)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "sending configuration data to proxy \"%s\" at \"%s\","
						" datalen " ZBX_FS_SIZE_T,
						proxy.host, get_ip_by_socket(&s), (zbx_fs_size_t)j.buffer_size);

				if (SUCCEED == (ret = send_data_to_proxy(&proxy, &s, j.buffer)))
				{
					char	*error = NULL;

					if (SUCCEED != (ret = zbx_recv_response(&s, 0, &error)))
					{
						zabbix_log(LOG_LEVEL_WARNING, "cannot send configuration data to proxy"
								" \"%s\" at \"%s\": %s",
								proxy.host, get_ip_by_socket(&s), error);
					}
					zbx_free(error);
				}

				disconnect_proxy(&s);
			}

			if (SUCCEED != ret)
				goto network_error;
		}

		if (proxy.proxy_data_nextcheck <= now)
		{
			if (SUCCEED == get_data_from_proxy(&proxy,
					ZBX_PROTO_VALUE_HOST_AVAILABILITY, &answer))
			{
				if (SUCCEED == zbx_json_open(answer, &jp))
					process_host_availability(&jp);

				zbx_free(answer);
			}
			else
				goto network_error;
retry_history:
			if (SUCCEED == get_data_from_proxy(&proxy,
					ZBX_PROTO_VALUE_HISTORY_DATA, &answer))
			{
				if (SUCCEED == zbx_json_open(answer, &jp))
				{
					process_hist_data(NULL, &jp, proxy.hostid, NULL, 0);

					if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
					{
						if (ZBX_MAX_HRECORDS <= zbx_json_count(&jp_data))
						{
							zbx_free(answer);
							goto retry_history;
						}
					}
				}
				zbx_free(answer);
			}
			else
				goto network_error;
retry_dhistory:
			if (SUCCEED == get_data_from_proxy(&proxy,
					ZBX_PROTO_VALUE_DISCOVERY_DATA, &answer))
			{
				if (SUCCEED == zbx_json_open(answer, &jp))
				{
					process_dhis_data(&jp);

					if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
					{
						if (ZBX_MAX_HRECORDS <= zbx_json_count(&jp_data))
						{
							zbx_free(answer);
							goto retry_dhistory;
						}
					}
				}
				zbx_free(answer);
			}
			else
				goto network_error;
retry_autoreg_host:
			if (SUCCEED == get_data_from_proxy(&proxy,
					ZBX_PROTO_VALUE_AUTO_REGISTRATION_DATA, &answer))
			{
				if (SUCCEED == zbx_json_open(answer, &jp))
				{
					process_areg_data(&jp, proxy.hostid);

					if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
					{
						if (ZBX_MAX_HRECORDS <= zbx_json_count(&jp_data))
						{
							zbx_free(answer);
							goto retry_autoreg_host;
						}
					}
				}
				zbx_free(answer);
			}
			else
				goto network_error;
		}

		DBbegin();
		update_proxy_lastaccess(proxy.hostid);
		DBcommit();
network_error:
		DCrequeue_proxy(proxy.hostid, update_nextcheck);
	}

	zbx_free(port);

	zbx_json_free(&j);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return num;
}

ZBX_THREAD_ENTRY(proxypoller_thread, args)
{
	int	nextcheck, sleeptime = -1, processed = 0, old_processed = 0;
	double	sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t	last_stat_time;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_daemon_type_string(daemon_type),
			server_num, get_process_type_string(process_type), process_num);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [exchanged data with %d proxies in " ZBX_FS_DBL " sec,"
					" exchanging data]", get_process_type_string(process_type), process_num,
					old_processed, old_total_sec);
		}

		sec = zbx_time();
		processed += process_proxy();
		total_sec += zbx_time() - sec;

		nextcheck = DCconfig_get_proxypoller_nextcheck();
		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [exchanged data with %d proxies in " ZBX_FS_DBL " sec,"
						" exchanging data]", get_process_type_string(process_type), process_num,
						processed, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [exchanged data with %d proxies in " ZBX_FS_DBL " sec,"
						" idle %d sec]", get_process_type_string(process_type), process_num,
						processed, total_sec, sleeptime);
				old_processed = processed;
				old_total_sec = total_sec;
			}
			processed = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(sleeptime);
	}
#undef STAT_INTERVAL
}
