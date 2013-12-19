/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "daemon.h"
#include "comms.h"
#include "zbxself.h"

#include "proxypoller.h"
#include "dbcache.h"
#include "db.h"
#include "zbxjson.h"
#include "log.h"
#include "proxy.h"

extern unsigned char	process_type;
extern int		process_num;

static int	connect_to_proxy(DC_HOST *host, zbx_sock_t *sock, int timeout)
{
	const char	*__function_name = "connect_to_proxy";
	const char	*addr;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	addr = host->useip ? host->ip : host->dns;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() [%s]:%d timeout:%hu",
			__function_name, addr, host->port, timeout);

	if (FAIL == (ret = zbx_tcp_connect(sock, CONFIG_SOURCE_IP, addr, host->port, timeout)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Unable to connect to the proxy [%s] [%s]:%hu [%s]",
				host->host, addr, host->port, zbx_tcp_strerror());
		ret = NETWORK_ERROR;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(ret));

	return ret;
}

static int	send_data_to_proxy(DC_HOST *host, zbx_sock_t *sock, const char *data)
{
	const char	*__function_name = "send_data_to_proxy";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() [%s]",
			__function_name, data);

	if (FAIL == (ret = zbx_tcp_send(sock, data)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Error while sending data to the proxy [%s] [%s]",
				host->host, zbx_tcp_strerror());
		ret = NETWORK_ERROR;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(ret));

	return ret;
}

static int	recv_data_from_proxy(DC_HOST *host, zbx_sock_t *sock, char **data)
{
	const char	*__function_name = "recv_data_from_proxy";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == (ret = zbx_tcp_recv(sock, data)))
		zabbix_log(LOG_LEVEL_ERR, "Error while receiving answer from proxy [%s] [%s]",
				host->host, zbx_tcp_strerror());
	else
		zabbix_log(LOG_LEVEL_DEBUG, "%s() [%s]",
				__function_name, *data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(ret));

	return ret;
}

static void	disconnect_proxy(zbx_sock_t *sock)
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
static int	get_data_from_proxy(DC_HOST *host, const char *request, char **data)
{
	const char	*__function_name = "get_data_from_proxy";
	zbx_sock_t	s;
	struct zbx_json	j;
	char		*answer = NULL;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() request:'%s'",
			__function_name, request);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&j, "request", request, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == (ret = connect_to_proxy(host, &s, CONFIG_TRAPPER_TIMEOUT)))
	{
		if (SUCCEED == (ret = send_data_to_proxy(host, &s, j.buffer)))
			if (SUCCEED == (ret = recv_data_from_proxy(host, &s, &answer)))
				if (SUCCEED == (ret = zbx_send_response(&s, SUCCEED, NULL, 0)))
					*data = strdup(answer);

		disconnect_proxy(&s);
	}

	zbx_json_free(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(ret));

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
static int	process_proxy()
{
	const char		*__function_name = "process_proxy";
	DC_HOST			host;
	int			num, i, ret;
	struct zbx_json		j;
	struct zbx_json_parse	jp, jp_data;
	zbx_sock_t		s;
	char			*answer = NULL;
	time_t			now;
	unsigned char		update_nextcheck;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == (num = DCconfig_get_proxypoller_hosts(&host, 1)))
		goto exit;

	now = time(NULL);

	zbx_json_init(&j, 512 * 1024);

	for (i = 0; i < num; i++)
	{
		ret = SUCCEED;
		update_nextcheck = 0;

		if (host.snmp_disable_until <= now)
		{
			update_nextcheck |= 0x01;

			zbx_json_clean(&j);

			zbx_json_addstring(&j, ZBX_PROTO_TAG_REQUEST,
					ZBX_PROTO_VALUE_PROXY_CONFIG, ZBX_JSON_TYPE_STRING);
			zbx_json_addobject(&j, ZBX_PROTO_TAG_DATA);

			get_proxyconfig_data(host.hostid, &j);

			zabbix_log(LOG_LEVEL_WARNING, "Sending configuration data to proxy '%s'. Datalen " ZBX_FS_SIZE_T,
					host.host, (zbx_fs_size_t)j.buffer_size);

			if (SUCCEED == (ret = connect_to_proxy(&host, &s, CONFIG_TRAPPER_TIMEOUT)))
			{
				if (SUCCEED == (ret = send_data_to_proxy(&host, &s, j.buffer)))
					ret = zbx_recv_response(&s, NULL, 0, 0);

				disconnect_proxy(&s);
			}

			if (SUCCEED != ret)
				goto network_error;
		}

		if (host.ipmi_disable_until <= now)
		{
			update_nextcheck |= 0x02;

			if (SUCCEED == (ret = get_data_from_proxy(&host,
					ZBX_PROTO_VALUE_HOST_AVAILABILITY, &answer)))
			{
				if (SUCCEED == zbx_json_open(answer, &jp))
					process_host_availability(&jp);

				zbx_free(answer);
			}
			else
				goto network_error;
retry_history:
			if (SUCCEED == (ret = get_data_from_proxy(&host,
					ZBX_PROTO_VALUE_HISTORY_DATA, &answer)))
			{
				if (SUCCEED == zbx_json_open(answer, &jp))
				{
					process_hist_data(NULL, &jp, host.hostid, NULL, 0);

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
			if (SUCCEED == (ret = get_data_from_proxy(&host,
					ZBX_PROTO_VALUE_DISCOVERY_DATA, &answer)))
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
			if (SUCCEED == (ret = get_data_from_proxy(&host,
					ZBX_PROTO_VALUE_AUTO_REGISTRATION_DATA, &answer)))
			{
				if (SUCCEED == zbx_json_open(answer, &jp))
				{
					process_areg_data(&jp, host.hostid);

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
		update_proxy_lastaccess(host.hostid);
		DBcommit();
network_error:
		DCrequeue_proxy(host.hostid, update_nextcheck);
	}

	zbx_json_free(&j);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return num;
}

void	main_proxypoller_loop()
{
	const char	*__function_name = "main_proxypoller_loop";
	int		nextcheck, sleeptime, processed;
	double		sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() process_num:%d", __function_name, process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [exchanging data]", get_process_type_string(process_type));

		sec = zbx_time();
		processed = process_proxy();
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s #%d spent " ZBX_FS_DBL " seconds while processing %3d proxies",
				get_process_type_string(process_type), process_num, sec, processed);

		nextcheck = DCconfig_get_proxypoller_nextcheck();
		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		zbx_sleep_loop(sleeptime);
	}
}
