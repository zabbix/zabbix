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

#include "common.h"
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "proxy.h"
#include "zbxself.h"

#include "proxyconfig.h"
#include "zbxcrypto.h"
#include "zbxcompress.h"
#include "zbxrtc.h"
#include "zbxipcservice.h"

#define CONFIG_PROXYCONFIG_RETRY	120	/* seconds */

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

extern zbx_vector_ptr_t	zbx_addrs;
extern char		*CONFIG_HOSTNAME;
extern char		*CONFIG_SOURCE_IP;
extern unsigned int	configured_tls_connect_mode;

static void	zbx_proxyconfig_sigusr_handler(int flags)
{
	if (ZBX_RTC_CONFIG_CACHE_RELOAD == ZBX_RTC_GET_MSG(flags))
	{
		if (0 < zbx_sleep_get_remainder())
		{
			zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the configuration cache");
			zbx_wakeup();
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "configuration cache reloading is already in progress");
	}
}

static void	process_configuration_sync(size_t *data_size)
{
	zbx_socket_t		sock;
	struct	zbx_json_parse	jp, jp_kvs_paths = {0};
	char			value[16], *error = NULL, *buffer = NULL;
	size_t			buffer_size, reserved;
	struct zbx_json		j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* reset the performance metric */
	*data_size = 0;

	zbx_json_init(&j, 128);
	zbx_json_addstring(&j, "request", ZBX_PROTO_VALUE_PROXY_CONFIG, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, "host", CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);

	if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
	{
		zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
		goto out;
	}

	reserved = j.buffer_size;
	zbx_json_free(&j);

	if (FAIL == connect_to_server(&sock,CONFIG_SOURCE_IP, &zbx_addrs, 600, CONFIG_TIMEOUT,
			configured_tls_connect_mode, CONFIG_PROXYCONFIG_RETRY, LOG_LEVEL_WARNING))	/* retry till have a connection */
	{
		goto out;
	}

	if (SUCCEED != get_data_from_server(&sock, &buffer, buffer_size, reserved, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain configuration data from server at \"%s\": %s",
				sock.peer, error);
		goto error;
	}

	if ('\0' == *sock.buffer)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain configuration data from server at \"%s\": %s",
				sock.peer, "empty string received");
		goto error;
	}

	if (SUCCEED != zbx_json_open(sock.buffer, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain configuration data from server at \"%s\": %s",
				sock.peer, zbx_json_strerror());
		goto error;
	}

	*data_size = (size_t)(jp.end - jp.start + 1);     /* performance metric */

	/* if the answer is short then most likely it is a negative answer "response":"failed" */
	if (128 > *data_size &&
			SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value), NULL) &&
			0 == strcmp(value, ZBX_PROTO_VALUE_FAILED))
	{
		char	*info = NULL;
		size_t	info_alloc = 0;

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_INFO, &info, &info_alloc, NULL))
			info = zbx_dsprintf(info, "negative response \"%s\"", value);

		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain configuration data from server at \"%s\": %s",
				sock.peer, info);
		zbx_free(info);
		goto error;
	}

	zabbix_log(LOG_LEVEL_WARNING, "received configuration data from server at \"%s\", datalen " ZBX_FS_SIZE_T,
			sock.peer, (zbx_fs_size_t)*data_size);

	if (SUCCEED == process_proxyconfig(&jp, &jp_kvs_paths))
	{
		DCsync_configuration(ZBX_DBSYNC_UPDATE);

		if (NULL != jp_kvs_paths.start)
			DCsync_kvs_paths(&jp_kvs_paths);

		DCupdate_interfaces_availability();
	}
error:
	disconnect_server(&sock);
out:
	zbx_free(error);
	zbx_free(buffer);
	zbx_json_free(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically request config data                                  *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(proxyconfig_thread, args)
{
	size_t			data_size;
	double			sec;
	zbx_ipc_service_t	config_service;
	char			*error = NULL;
	zbx_timespec_t		timeout = {1, 0};

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);
	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
	zbx_set_sigusr_handler(zbx_proxyconfig_sigusr_handler);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif

	if (FAIL == zbx_ipc_service_start(&config_service, ZBX_IPC_SERVICE_CONFIG, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start configuration syncer service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_setproctitle("%s [syncing configuration]", get_process_type_string(process_type));
	DCsync_configuration(ZBX_DBSYNC_INIT);

	if (SUCCEED != zbx_rtc_notify_config_sync(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send configuration syncer notification: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	while (ZBX_IS_RUNNING())
	{
		if (ZBX_PROGRAM_TYPE_PROXY_PASSIVE == program_type)
		{
			zbx_ipc_client_t	*client;
			zbx_ipc_message_t	*message;

			update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
			zbx_ipc_service_recv(&config_service, &timeout, &client, &message);
			update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

			sec = zbx_time();
			zbx_update_env(sec);

			if (NULL != message)
			{
				zbx_setproctitle("%s [loading configuration]", get_process_type_string(process_type));

				DCsync_configuration(ZBX_DBSYNC_UPDATE);
				DCupdate_interfaces_availability();

				zbx_setproctitle("%s [synced config in " ZBX_FS_DBL " sec]",
						get_process_type_string(process_type), zbx_time() - sec);
				zbx_ipc_client_send(client, ZBX_IPC_CONFIG_RELOAD_RESPONSE, NULL, 0);
			}

			zbx_ipc_message_free(message);

			if (NULL != client)
				zbx_ipc_client_release(client);

			continue;
		}

		sec = zbx_time();
		zbx_update_env(sec);

		zbx_setproctitle("%s [loading configuration]", get_process_type_string(process_type));

		process_configuration_sync(&data_size);
		sec = zbx_time() - sec;

		zbx_setproctitle("%s [synced config " ZBX_FS_SIZE_T " bytes in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), (zbx_fs_size_t)data_size, sec,
				CONFIG_PROXYCONFIG_FREQUENCY);

		zbx_sleep_loop(CONFIG_PROXYCONFIG_FREQUENCY);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
