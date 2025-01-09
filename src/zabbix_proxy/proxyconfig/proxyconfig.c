/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "proxyconfig.h"

#include "proxyconfigwrite/proxyconfigwrite.h"

#include "zbxtimekeeper.h"
#include "zbxlog.h"
#include "zbxnix.h"
#include "zbxcachehistory.h"
#include "zbxself.h"
#include "zbxtime.h"
#include "zbxcompress.h"
#include "zbxrtc.h"
#include "zbxcommshigh.h"
#include "zbx_rtc_constants.h"
#include "zbx_host_constants.h"
#include "zbxstr.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxipcservice.h"
#include "zbxnum.h"
#include "zbxjson.h"

static void	process_configuration_sync(size_t *data_size, zbx_synced_new_config_t *synced,
		const zbx_thread_info_t *thread_info, zbx_thread_proxyconfig_args *args)
{
	zbx_socket_t			sock;
	struct	zbx_json_parse		jp, jp_kvs_paths = {0};
	char				value[16], *error = NULL, *buffer = NULL;
	size_t				buffer_size, reserved;
	struct zbx_json			j;
	int				ret = FAIL;
	zbx_uint64_t			config_revision, hostmap_revision;
	zbx_proxyconfig_write_status_t	status = ZBX_PROXYCONFIG_WRITE_STATUS_DATA;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* reset the performance metric */
	*data_size = 0;

	zbx_dc_get_upstream_revision(&config_revision, &hostmap_revision);

	zbx_json_init(&j, 128);
	zbx_json_addstring(&j, "request", ZBX_PROTO_VALUE_PROXY_CONFIG, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, "host", args->config_hostname, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_SESSION, zbx_dc_get_session_token(), ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CONFIG_REVISION, config_revision);

	if (0 != hostmap_revision)
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_HOSTMAP_REVISION, hostmap_revision);

	if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
	{
		zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
		goto out;
	}

	reserved = j.buffer_size;
	zbx_json_free(&j);

	zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_IDLE);
#define CONFIG_PROXYCONFIG_RETRY	10	/* seconds */
	if (FAIL == zbx_connect_to_server(&sock, args->config_source_ip, args->config_server_addrs, 600,
			args->config_timeout, CONFIG_PROXYCONFIG_RETRY, LOG_LEVEL_WARNING,
			args->config_tls)) /* retry till have a connection */
	{
		zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_BUSY);
		goto out;
	}
#undef CONFIG_PROXYCONFIG_RETRY
	zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_BUSY);

	if (SUCCEED != zbx_get_data_from_server(&sock, &buffer, buffer_size, reserved, &error))
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

	if (SUCCEED == (ret = zbx_proxyconfig_process(sock.peer, &jp, &status, &error)))
	{
		zbx_dc_sync_configuration(ZBX_DBSYNC_UPDATE, *synced, NULL, args->config_vault,
				args->config_proxyconfig_frequency);
		*synced = ZBX_SYNCED_NEW_CONFIG_YES;

		if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_MACRO_SECRETS, &jp_kvs_paths))
		{
			zbx_dc_sync_kvs_paths(&jp_kvs_paths, args->config_vault, args->config_source_ip,
					args->config_ssl_ca_location, args->config_ssl_cert_location,
					args->config_ssl_key_location);
		}

		zbx_dc_update_interfaces_availability();
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot process received configuration data from server at \"%s\": %s",
				sock.peer, error);
		zbx_free(error);
	}

	zbx_dc_set_proxy_lastonline((int)time(NULL));
error:
	zbx_disconnect_from_server(&sock);
	if (SUCCEED != ret)
	{
		/* reset received config_revision to force full resync after data transfer failure */
		zbx_dc_set_upstream_revision(0, 0);

		zbx_addrs_failover(args->config_server_addrs);
	}

out:
	zbx_free(error);
	zbx_free(buffer);
	zbx_json_free(&j);
#ifdef	HAVE_MALLOC_TRIM
	/* avoid memory not being released back to the system if large proxy configuration is retrieved from database */
	if (ZBX_PROXYCONFIG_WRITE_STATUS_DATA == status)
		malloc_trim(ZBX_MALLOC_TRIM);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	proxyconfig_remove_unused_templates(void)
{
	zbx_vector_uint64_t	hostids, templateids;
	zbx_hashset_t		templates;
	int			removed_num;
	zbx_db_row_t		row;
	zbx_db_result_t		result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&templateids);
	zbx_hashset_create(&templates, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	result = zbx_db_select("select hostid,status from hosts");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostid;
		unsigned char	status;

		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_STR2UCHAR(status, row[1]);

		if (HOST_STATUS_TEMPLATE == status)
			zbx_hashset_insert(&templates, &hostid, sizeof(hostid));
		else
			zbx_vector_uint64_append(&hostids, hostid);
	}
	zbx_db_free_result(result);

	zbx_dc_get_unused_macro_templates(&templates, &hostids, &templateids);

	if (0 != templateids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_db_begin();

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts_templates where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", templateids.values,
				templateids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto fail;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hostmacro where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", templateids.values,
				templateids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto fail;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", templateids.values,
				templateids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto fail;
fail:
		zbx_db_commit();

		zbx_free(sql);
	}

	removed_num = templateids.values_num;

	zbx_hashset_destroy(&templates);
	zbx_vector_uint64_destroy(&templateids);
	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() removed:%d", __func__, removed_num);
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
	zbx_thread_proxyconfig_args	*proxyconfig_args_in = (zbx_thread_proxyconfig_args *)
							(((zbx_thread_args_t *)args)->args);
	size_t				data_size;
	double				sec, last_template_cleanup_sec = 0, interval;
	zbx_ipc_async_socket_t		rtc;
	int				sleeptime;
	zbx_synced_new_config_t		synced = ZBX_SYNCED_NEW_CONFIG_NO;
	zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_uint32_t			rtc_msgs[] = {ZBX_RTC_CONFIG_CACHE_RELOAD};

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);
	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(proxyconfig_args_in->config_tls, proxyconfig_args_in->zbx_get_program_type_cb_arg,
			zbx_dc_get_psk_by_identity);
#endif

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, ARRSIZE(rtc_msgs), proxyconfig_args_in->config_timeout,
			&rtc);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	zbx_setproctitle("%s [syncing configuration]", get_process_type_string(process_type));
	zbx_dc_sync_configuration(ZBX_DBSYNC_INIT, ZBX_SYNCED_NEW_CONFIG_NO, NULL, proxyconfig_args_in->config_vault,
			proxyconfig_args_in->config_proxyconfig_frequency);

	zbx_rtc_notify_finished_sync(proxyconfig_args_in->config_timeout, ZBX_RTC_CONFIG_SYNC_NOTIFY, get_process_type_string(process_type), &rtc);

	sleeptime = (ZBX_PROGRAM_TYPE_PROXY_PASSIVE == info->program_type ? ZBX_IPC_WAIT_FOREVER : 0);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;
		int		config_cache_reload = 0;

		while (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_CONFIG_CACHE_RELOAD == rtc_cmd)
				config_cache_reload = 1;
			else if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				goto stop;

			sleeptime = 0;
		}

		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (ZBX_PROGRAM_TYPE_PROXY_PASSIVE == info->program_type)
		{
			if (0 != config_cache_reload)
			{
				zbx_setproctitle("%s [loading configuration]", get_process_type_string(process_type));

				zbx_dc_sync_configuration(ZBX_DBSYNC_UPDATE, synced, NULL,
						proxyconfig_args_in->config_vault,
						proxyconfig_args_in->config_proxyconfig_frequency);
				synced = ZBX_SYNCED_NEW_CONFIG_YES;
				zbx_dc_update_interfaces_availability();

				zbx_rtc_notify_finished_sync(proxyconfig_args_in->config_timeout,
					ZBX_RTC_CONFIG_SYNC_NOTIFY, get_process_type_string(process_type), &rtc);

				if (SEC_PER_HOUR < sec - last_template_cleanup_sec)
				{
					proxyconfig_remove_unused_templates();
					last_template_cleanup_sec = sec;
				}

				zbx_setproctitle("%s [synced config in " ZBX_FS_DBL " sec]",
						get_process_type_string(process_type), zbx_time() - sec);
			}

			sleeptime = ZBX_IPC_WAIT_FOREVER;
			continue;
		}

		if (1 == config_cache_reload)
			zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the configuration cache");

		zbx_setproctitle("%s [loading configuration]", get_process_type_string(process_type));

		process_configuration_sync(&data_size, &synced, info, proxyconfig_args_in);

		interval = zbx_time() - sec;

		zbx_setproctitle("%s [synced config " ZBX_FS_SIZE_T " bytes in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), (zbx_fs_size_t)data_size, interval,
				proxyconfig_args_in->config_proxyconfig_frequency);

		if (SEC_PER_HOUR < sec - last_template_cleanup_sec)
		{
			proxyconfig_remove_unused_templates();
			last_template_cleanup_sec = sec;
		}

		sleeptime = proxyconfig_args_in->config_proxyconfig_frequency;
	}
stop:
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
