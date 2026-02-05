/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcommon.h"
#include "zbxrtc.h"
#include "zbx_rtc_constants.h"

#include "zbxserialize.h"
#include "zbxjson.h"
#include "zbxnix.h"
#include "zbxdiag.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxalgo.h"
#include "zbxipcservice.h"
#include "zbxprof.h"
#include "zbxcachehistory.h"
#include "zbxdb.h"
#include "zbxlog.h"
#include "zbxsupervisor_client.h"

ZBX_VECTOR_IMPL(rtc_msg, zbx_rtc_msg_t)
ZBX_PTR_VECTOR_IMPL(rtc_sub, zbx_rtc_sub_t *)
ZBX_PTR_VECTOR_IMPL(rtc_hook, zbx_rtc_hook_t *)

static int	zbx_json_getuint64(const char *tag, const struct zbx_json_parse *jp, zbx_uint64_t *itemid,
		char **error)
{
	char	buffer[MAX_ID_LEN];

	if (SUCCEED != zbx_json_value_by_name(jp, tag, buffer, sizeof(buffer), NULL) ||
			SUCCEED != zbx_is_uint64(buffer, itemid))
	{
		*error = zbx_dsprintf(NULL, "cannot retrieve value of tag \"%s\"", tag);
		return FAIL;
	}

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Purpose: process ha_failover_delay runtime command                         *
 *                                                                            *
 ******************************************************************************/
static void	rtc_history_cache_clear(const char *data, char **out)
{
	struct zbx_json_parse	jp;
	zbx_uint64_t		itemid;
	int			num;

	if (FAIL == zbx_json_open(data, &jp))
	{
		*out = zbx_dsprintf(NULL, "Invalid parameter format \"%s\"\n", data);
		return;
	}

	if (SUCCEED != zbx_json_getuint64(ZBX_PROTO_TAG_ITEMID, &jp, &itemid, out))
		return;

	if (FAIL == (num = zbx_hc_clear_item_middle(itemid)))
		*out = zbx_dsprintf(NULL, "Cannot clear item from history cache: item is not in cache\n");
	else
		*out = zbx_dsprintf(NULL, "Cleared %d values from history cache\n", num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get runtime control option targets                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_get_command_target(const char *data, pid_t *pid, int *proc_type, int *proc_num, int *scope,
		char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];

	if (FAIL == zbx_json_open(data, &jp))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
		return FAIL;
	}

	if (NULL != scope)
	{
		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_SCOPE, buf, sizeof(buf), NULL))
			*scope = atoi(buf);
		else
			*scope = ZBX_PROF_UNKNOWN;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PID, buf, sizeof(buf), NULL))
	{
		zbx_uint64_t	pid_ui64;

		if (SUCCEED != zbx_is_uint64(buf, &pid_ui64) || 0 == pid_ui64)
		{
			*result = zbx_dsprintf(NULL, "Invalid pid value \"%s\"\n", buf);
			return FAIL;
		}

		*pid = (pid_t)pid_ui64;
	}
	else
		*pid = 0;

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROCESS_NUM, buf, sizeof(buf), NULL))
		*proc_num = atoi(buf);
	else
		*proc_num = 0;

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROCESS_NAME, buf, sizeof(buf), NULL))
	{
		if (ZBX_PROCESS_TYPE_UNKNOWN == (*proc_type = get_process_type_by_name(buf)))
		{
			*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
			return FAIL;
		}
	}
	else
		*proc_type = ZBX_PROCESS_TYPE_UNKNOWN;

	return SUCCEED;
}

#if defined(HAVE_SIGQUEUE)

/******************************************************************************
 *                                                                            *
 * Purpose: change log level of service process                               *
 *                                                                            *
 ******************************************************************************/
static void	rtc_change_service_loglevel(zbx_uint32_t code)
{
	if (ZBX_RTC_LOG_LEVEL_INCREASE == code)
		zabbix_increase_log_level();
	else if (ZBX_RTC_LOG_LEVEL_DECREASE == code)
		zabbix_decrease_log_level();

	zabbix_report_log_level_change();
}


/******************************************************************************
 *                                                                            *
 * Purpose: dispatch log level change runtime control option                  *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_loglevel_option(zbx_rtc_t *rtc, zbx_uint32_t code, const char *data, char **result)
{
	pid_t	pid;
	int	proc_type, proc_num;

	if (SUCCEED != zbx_rtc_get_command_target(data, &pid, &proc_type, &proc_num, NULL, result))
		return;

	/* all processes */
	if (0 == pid && ZBX_PROCESS_TYPE_UNKNOWN == proc_type)
	{
		rtc_change_service_loglevel(code);
		zbx_rtc_notify(rtc, (unsigned char)proc_type, proc_num, code, data, (zbx_uint32_t)strlen(data) + 1);
		zbx_signal_process_by_pid(0, (int)ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);
		return;
	}

	if (0 != pid)
	{
		if (pid == getpid())
		{
			rtc_change_service_loglevel(code);
			/* temporary message, the signal forwarding command output will be changed later */
			*result = zbx_strdup(NULL, "Changed log level for the main process\n");
		}
		else
			zbx_signal_process_by_pid((int)pid, (int)ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);

		return;
	}

	if (0 == zbx_rtc_notify(rtc, (unsigned char)proc_type, proc_num, code, data, (zbx_uint32_t)strlen(data) + 1))
		zbx_signal_process_by_type(proc_type, proc_num, (int)ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: dispatch profiler runtime control option                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_profiler_option(zbx_uint32_t code, const char *data, char **result)
{
	pid_t	pid;
	int	proc_type, proc_num, scope;

	if (SUCCEED != zbx_rtc_get_command_target(data, &pid, &proc_type, &proc_num, &scope, result))
		return;

	/* all processes */
	if (0 == pid && ZBX_PROCESS_TYPE_UNKNOWN == proc_type)
	{
		zbx_signal_process_by_pid(0, (int)ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);
		return;
	}

	if (0 != pid)
	{
		if (pid == getpid())
			*result = zbx_strdup(NULL, "Cannot use profiler with main process\n");
		else
			zbx_signal_process_by_pid((int)pid, (int)ZBX_RTC_MAKE_MESSAGE(code, scope, 0), result);

		return;
	}

	zbx_signal_process_by_type(proc_type, proc_num, (int)ZBX_RTC_MAKE_MESSAGE(code, scope, 0), result);
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: process diaginfo runtime control option                           *
 *                                                                            *
 * Parameters: data   - [IN] runtime control parameter (optional)             *
 *             result - [OUT] runtime control result                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_diaginfo(const char *data, char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	unsigned int		scope;

	if (FAIL == zbx_json_open(data, &jp) ||
			SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_SECTION, buf, sizeof(buf), NULL))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
		return;
	}

	if (0 == strcmp(buf, "all"))
	{
		scope = (1 << ZBX_DIAGINFO_HISTORYCACHE) | (1 << ZBX_DIAGINFO_PREPROCESSING) |
				(1 << ZBX_DIAGINFO_LOCKS);
	}
	else if (0 == strcmp(buf, ZBX_DIAG_HISTORYCACHE))
	{
		scope = 1 << ZBX_DIAGINFO_HISTORYCACHE;
	}
	else if (0 == strcmp(buf, ZBX_DIAG_PREPROCESSING))
	{
		scope = 1 << ZBX_DIAGINFO_PREPROCESSING;
	}
	else if (0 == strcmp(buf, ZBX_DIAG_LOCKS))
	{
		scope = 1 << ZBX_DIAGINFO_LOCKS;
	}
	else
	{
		if (NULL == *result)
			*result = zbx_dsprintf(NULL, "Unknown diaginfo section \"%s\"\n", buf);
		return;
	}

	zbx_diag_log_info(scope, result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process db_status runtime control option                          *
 *                                                                            *
 * Parameters: result - [OUT] runtime control result                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_db_status(char **result)
{
	size_t				result_alloc = 0, result_offset = 0;
	zbx_dbconn_pool_config_t	cfg;
	zbx_dbconn_pool_stats_t		stats;

	zbx_dbconn_pool_get_config(&cfg);
	zbx_dbconn_pool_get_stats(&stats);

	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"database connection pool configuration");
	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"  %30s: %d", "maximum idle connection limit", cfg.max_idle);
	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"  %30s: %d", "maximum connection limit", cfg.max_open);
	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"  %30s: %d", "idle timeout", cfg.idle_timeout);

	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"database connection pool statistics");
	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"  %30s: " ZBX_FS_UI64, "provided connections", stats.provided_num);
	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"  %30s: %.3f" , "total wait time", stats.time_wait);
	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"  %30s: %.3f" , "total idle time", stats.time_idle);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process dbpool_set_max_idle runtime control option                *
 *                                                                            *
 * Parameters: data   - [IN] runtime control parameter                        *
 *             limit  - [OUT] new maximum idle connection limit               *
 *             error  - [OUT] error message                                   *
 *                                                                            *
 ******************************************************************************/
static int	rtc_db_set_max_idle(const char *data, int *limit, char **error)
{
	struct zbx_json_parse		jp;
	char				buf[MAX_STRING_LEN];
	zbx_dbconn_pool_config_t	cfg;

	if (FAIL == zbx_json_open(data, &jp) ||
			SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_MAX_IDLE, buf, sizeof(buf), NULL) ||
			SUCCEED != zbx_is_int(buf, limit))
	{
		*error = zbx_dsprintf(NULL, "invalid parameters \"%s\"", data);
		return FAIL;
	}

	zbx_dbconn_pool_get_config(&cfg);

	if (cfg.max_open < *limit)
	{
		*error = zbx_dsprintf(NULL, "maximum idle connection limit %d cannot be higher than maximum connection"
				" limit %d", *limit, cfg.max_open);
		return FAIL;
	}

	if (DBPOOL_MINIMUM_MAX_IDLE > *limit)
	{
		*error = zbx_dsprintf(NULL, "maximum idle connection limit must be at least %d",
				DBPOOL_MINIMUM_MAX_IDLE);
		return FAIL;
	}

	if (DBPOOL_MAXIMUM_MAX_IDLE < *limit)
	{
		*error = zbx_dsprintf(NULL, "maximum idle connection limit must be less than or equal to %d",
				DBPOOL_MAXIMUM_MAX_IDLE);
		return FAIL;
	}

	cfg.max_idle = *limit;

	return zbx_dbconn_pool_flush_config(&cfg, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process dbpool_set_max_idle runtime control option                *
 *                                                                            *
 * Parameters: data   - [IN] runtime control parameter                        *
 *             result - [OUT] runtime control result                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_db_set_max_idle(const char *data, char **result)
{
	size_t	result_alloc = 0, result_offset = 0;
	char	*error = NULL;
	int	limit;

	if (SUCCEED != rtc_db_set_max_idle(data, &limit, &error))
	{
		zbx_strlog_alloc(LOG_LEVEL_ERR, result, &result_alloc, &result_offset,
				"cannot set maximum idle connection limit: %s", error);
		zbx_free(error);
		return;
	}

	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"updated maximum idle connection limit to %d", limit);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process dbpool_set_max_open runtime control option                *
 *                                                                            *
 * Parameters: data   - [IN] runtime control parameter                        *
 *             limit  - [OUT] new maximum connection limit                    *
 *             error  - [OUT] error message                                   *
 *                                                                            *
 ******************************************************************************/
static int	rtc_db_set_max_open(const char *data, int *limit, char **error)
{
	struct zbx_json_parse		jp;
	char				buf[MAX_STRING_LEN];
	zbx_dbconn_pool_config_t	cfg;

	if (FAIL == zbx_json_open(data, &jp) ||
			SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_MAX_OPEN, buf, sizeof(buf), NULL) ||
			SUCCEED != zbx_is_int(buf, limit))
	{
		*error = zbx_dsprintf(NULL, "invalid parameters \"%s\"", data);
		return FAIL;
	}

	zbx_dbconn_pool_get_config(&cfg);

	if (cfg.max_idle > *limit)
	{
		*error = zbx_dsprintf(NULL, "maximum open connection limit %d cannot be lower than maximum idle"
				" connection limit %d", *limit, cfg.max_idle);
		return FAIL;
	}

	if (DBPOOL_MINIMUM_MAX_OPEN > *limit)
	{
		*error = zbx_dsprintf(NULL, "maximum open connection limit must be at least %d",
				DBPOOL_MINIMUM_MAX_OPEN);
		return FAIL;
	}

	if (DBPOOL_MAXIMUM_MAX_OPEN < *limit)
	{
		*error = zbx_dsprintf(NULL, "maximum open connection limit must be less than or equal to %d",
				DBPOOL_MAXIMUM_MAX_OPEN);
		return FAIL;
	}

	cfg.max_open = *limit;

	return zbx_dbconn_pool_flush_config(&cfg, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process dbpool_set_max_open runtime control option                *
 *                                                                            *
 * Parameters: data   - [IN] runtime control parameter                        *
 *             result - [OUT] runtime control result                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_db_set_max_open(const char *data, char **result)
{
	size_t	result_alloc = 0, result_offset = 0;
	char	*error = NULL;
	int	limit;

	if (SUCCEED != rtc_db_set_max_open(data, &limit, &error))
	{
		zbx_strlog_alloc(LOG_LEVEL_ERR, result, &result_alloc, &result_offset,
				"cannot set maximum open connection limit: %s", error);
		zbx_free(error);
		return;
	}

	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"updated maximum open connection limit to %d", limit);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process db_set_idle_timeout runtime control option                *
 *                                                                            *
 * Parameters: data    - [IN] runtime control parameter                       *
 *             timeout - [OUT] new idle timeout                               *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 ******************************************************************************/
static int	rtc_db_set_idle_timeout(const char *data, int *timeout, char **error)
{
	struct zbx_json_parse		jp;
	char				buf[MAX_STRING_LEN];
	zbx_dbconn_pool_config_t	cfg;

	if (FAIL == zbx_json_open(data, &jp) ||
			SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_IDLE_TIMEOUT, buf, sizeof(buf), NULL) ||
			SUCCEED != zbx_is_int(buf, timeout))
	{
		*error = zbx_dsprintf(NULL, "invalid parameters \"%s\"", data);
		return FAIL;
	}

	if (DBPOOL_MINIMUM_IDLE_TIMEOUT > *timeout)
	{
		*error = zbx_dsprintf(NULL, "idle timeout must be at least %d seconds", DBPOOL_MINIMUM_IDLE_TIMEOUT);
		return FAIL;
	}

	if (DBPOOL_MAXIMUM_IDLE_TIMEOUT < *timeout)
	{
		*error = zbx_dsprintf(NULL, "idle timeout must be less than or equal to %d seconds",
				DBPOOL_MAXIMUM_IDLE_TIMEOUT);
		return FAIL;
	}

	zbx_dbconn_pool_get_config(&cfg);
	cfg.idle_timeout = *timeout;

	return zbx_dbconn_pool_flush_config(&cfg, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process db_set_idle_timeout runtime control option                *
 *                                                                            *
 * Parameters: data   - [IN] runtime control parameter (optional)             *
 *             result - [OUT] runtime control result                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_db_set_idle_timeout(const char *data, char **result)
{
	size_t	result_alloc = 0, result_offset = 0;
	char	*error = NULL;
	int	timeout;

	if (SUCCEED != rtc_db_set_idle_timeout(data, &timeout, &error))
	{
		zbx_strlog_alloc(LOG_LEVEL_ERR, result, &result_alloc, &result_offset,
				"cannot set database connection idle timeout: %s", error);
		zbx_free(error);
		return;
	}

	zbx_strlog_alloc(LOG_LEVEL_INFORMATION, result, &result_alloc, &result_offset,
			"updated database connection idle timeout to %d", timeout);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process db_status runtime control option                          *
 *                                                                            *
 * Parameters: result - [OUT] runtime control result                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_status(char **result)
{
	*result = zbx_supervisor_get_activities();
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify client based subscribers                                   *
 *                                                                            *
 ******************************************************************************/
static void	rtc_notify_client(zbx_rtc_sub_t *sub, const zbx_rtc_msg_t *msg, const unsigned char *data,
		zbx_uint32_t size)
{
	if (FAIL == zbx_ipc_client_send(sub->source.client, msg->code, data, size))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send RTC notification to client \"%s\" #%d",
				get_process_type_string(msg->process_type), msg->process_num);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify service based subscribers                                  *
 *                                                                            *
 ******************************************************************************/
static void	rtc_notify_service(zbx_rtc_sub_t *sub, const zbx_rtc_msg_t *msg, const unsigned char *data,
		zbx_uint32_t size, int timeout)
{
	zbx_ipc_socket_t	sock;
	char			*error = NULL;

	if (FAIL == zbx_ipc_socket_open(&sock, sub->source.service, timeout, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send RTC notification to service \"%s\" #%d: %s",
				get_process_type_string(msg->process_type), msg->process_num, error);
		zbx_free(error);
		return;
	}

	if (FAIL == zbx_ipc_socket_write(&sock, msg->code, data, size))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send RTC notification to service \"%s\" #%d",
				get_process_type_string(msg->process_type), msg->process_num);
	}

	zbx_ipc_socket_close(&sock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: find matching runtime control message in subscription             *
 *                                                                            *
 * Parameters: sub          - [IN] runtime control subscription               *
 *             process_type - [IN] process type filter                        *
 *             process_num  - [IN] process number filter                      *
 *             code         - [IN] message code                               *
 *                                                                            *
 * Return value: pointer to matching message or NULL if not found             *
 *                                                                            *
 ******************************************************************************/
static zbx_rtc_msg_t	*rtc_sub_match_msg(zbx_rtc_sub_t *sub, unsigned char process_type, int process_num,
		zbx_uint32_t code)
{
	for (int i = 0; i < sub->msgs.values_num; i++)
	{
		zbx_rtc_msg_t	*msg = &sub->msgs.values[i];

		if (ZBX_PROCESS_TYPE_UNKNOWN != process_type && process_type != msg->process_type)
			continue;

		if (0 != process_num && 0 != msg->process_num && process_num != msg->process_num)
			continue;

		if (msg->code == code)
			return msg;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify subscribers                                                *
 *                                                                            *
 ******************************************************************************/
static int	rtc_notify(zbx_rtc_t *rtc, unsigned char process_type, int process_num, zbx_uint32_t code,
		const char *data, zbx_uint32_t size, int timeout)
{
	int	i, notified_num = 0;

	for (i = 0; i < rtc->subs.values_num; i++)
	{
		zbx_rtc_msg_t	*msg;

		if (NULL == (msg = rtc_sub_match_msg(rtc->subs.values[i], process_type, process_num, code)))
			continue;

		switch (rtc->subs.values[i]->type)
		{
			case ZBX_RTC_SUB_CLIENT:
				rtc_notify_client(rtc->subs.values[i], msg, (const unsigned char *)data, size);
				break;
			case ZBX_RTC_SUB_SERVICE:
				rtc_notify_service(rtc->subs.values[i], msg, (const unsigned char *)data, size,
				timeout);
		}

		notified_num++;
	}

	return notified_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify subscribers                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_notify(zbx_rtc_t *rtc, unsigned char process_type, int process_num, zbx_uint32_t code,
		const char *data, zbx_uint32_t size)
{
	return rtc_notify(rtc, process_type, process_num, code, data, size, SEC_PER_MIN);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notifies remote subscribers                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_notify_generic(zbx_ipc_async_socket_t *rtc, unsigned char process_type, int process_num,
		zbx_uint32_t code, const char *data, zbx_uint32_t size)
{
	unsigned char	*notify_data, *ptr;
	zbx_uint32_t	notify_data_size;
	int		ret = FAIL;

	/* <process type:uchar><process num:int><code:uint32><data size:uint32><data> */
	notify_data_size = (zbx_uint32_t)(sizeof(process_type) + sizeof(process_num) +  sizeof(code) + 2 * sizeof(size))
			+ size;
	notify_data = (unsigned char *)zbx_malloc(NULL, notify_data_size);

	ptr = notify_data;
	ptr += zbx_serialize_value(ptr, process_type);
	ptr += zbx_serialize_int(ptr, process_num);
	ptr += zbx_serialize_value(ptr, code);
	ptr += zbx_serialize_value(ptr, size);
	(void)zbx_serialize_str(ptr, data, size);

	if (FAIL == zbx_ipc_async_socket_send(rtc, ZBX_RTC_NOTIFY, notify_data, notify_data_size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send %s notification", get_process_type_string(process_type));
		goto out;
	}

	ret = (int)notify_data_size;
out:
	zbx_free(notify_data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize subscription target notification codes                *
 *                                                                            *
 ******************************************************************************/
static void	rtc_deserialize_msgs(const unsigned char *data, unsigned char process_type, int process_num,
	zbx_vector_rtc_msg_t *msgs)
{
	int			msgs_num, old_num = msgs->values_num, total_num;
	const unsigned char	*ptr = data;
	zbx_rtc_msg_t		msg_local = {.process_type = process_type, .process_num = process_num};

	ptr += zbx_deserialize_value(ptr, &msgs_num);

	/* reserve additional slot for shutdown notification */
	total_num = msgs_num;
	if (0 != old_num)
		total_num += old_num;
	else
		total_num++;

	zbx_vector_rtc_msg_reserve(msgs, (size_t)total_num);

	for (int i = 0; i < msgs_num; i++)
	{
		ptr += zbx_deserialize_value(ptr, &msg_local.code);
		zbx_vector_rtc_msg_append_ptr(msgs, &msg_local);
	}

	/* automatically subscribe also for shutdown notifications */
	if (0 == old_num)
	{
		msg_local.code = ZBX_RTC_SHUTDOWN;
		zbx_vector_rtc_msg_append_ptr(msgs, &msg_local);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: subscribe for client based RTC notifications                      *
 *                                                                            *
 ******************************************************************************/
static void	rtc_subscribe(zbx_rtc_t *rtc, zbx_ipc_client_t *client, const unsigned char *data)
{
	zbx_rtc_sub_t	*sub;
	unsigned char	process_type;
	int		process_num;

	sub = (zbx_rtc_sub_t *)zbx_malloc(NULL, sizeof(zbx_rtc_sub_t));
	sub->type = ZBX_RTC_SUB_CLIENT;
	zbx_vector_rtc_msg_create(&sub->msgs);

	sub->source.client = client;
	zbx_ipc_client_addref(client);

	data += zbx_deserialize_value(data, &process_type);
	data += zbx_deserialize_value(data, &process_num);
	rtc_deserialize_msgs(data, process_type, process_num, &sub->msgs);

	zbx_vector_rtc_sub_append(&rtc->subs, sub);
}

/******************************************************************************
 *                                                                            *
 * Purpose: find runtime control service subscription by name                 *
 *                                                                            *
 * Parameters: rtc     - [IN] runtime control structure                       *
 *             service - [IN] service name                                    *
 *                                                                            *
 * Return value: pointer to service subscription or NULL if not found         *
 *                                                                            *
 ******************************************************************************/
static zbx_rtc_sub_t	*rtc_get_service_sub(zbx_rtc_t *rtc, const char *service)
{
	for (int i = 0; i < rtc->subs.values_num; i++)
	{
		zbx_rtc_sub_t	 *sub = rtc->subs.values[i];

		if (ZBX_RTC_SUB_SERVICE == sub->type && 0 == strcmp(sub->source.service, service))
			return sub;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: subscribe for service based RTC notifications                     *
 *                                                                            *
 ******************************************************************************/
static void	rtc_subscribe_service(zbx_rtc_t *rtc, const unsigned char *data)
{
	zbx_rtc_sub_t	*sub;
	zbx_uint32_t	service_len;
	char		*service = NULL;
	unsigned char	process_type;
	int		process_num;

	data += zbx_deserialize_str(data, &service, service_len);
	data += zbx_deserialize_value(data, &process_type);
	data += zbx_deserialize_value(data, &process_num);

	if (NULL == (sub = rtc_get_service_sub(rtc, service)))
	{
		sub = (zbx_rtc_sub_t *)zbx_malloc(NULL, sizeof(zbx_rtc_sub_t));
		sub->type = ZBX_RTC_SUB_SERVICE;
		sub->source.service = service;
		zbx_vector_rtc_msg_create(&sub->msgs);

		zbx_vector_rtc_sub_append(&rtc->subs, sub);
	}
	else
		zbx_free(service);

	(void)rtc_deserialize_msgs(data, process_type, process_num, &sub->msgs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unsubscribe service from RTC notifications                        *
 *                                                                            *
 ******************************************************************************/
static void	rtc_unsubscribe_service(zbx_rtc_t *rtc, const unsigned char *data)
{
	char		*service;
	zbx_uint32_t	service_len;

	(void)zbx_deserialize_str(data, &service, service_len);


	for (int i = 0; i < rtc->subs.values_num; i++)
	{
		zbx_rtc_sub_t	*sub = rtc->subs.values[i];

		if (ZBX_RTC_SUB_SERVICE == sub->type && 0 == strcmp(sub->source.service, service))
		{
			zbx_free(sub->source.service);
			zbx_vector_rtc_msg_destroy(&sub->msgs);
			zbx_free(sub);
			zbx_vector_rtc_sub_remove_noorder(&rtc->subs, i);

			break;
		}

	}

	zbx_free(service);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process runtime control option                                    *
 *                                                                            *
 * Parameters: rtc    - [IN] RTC service                                      *
 *             code   - [IN] request code                                     *
 *             data   - [IN] runtime control parameter (optional)             *
 *             result - [OUT] runtime control result                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_request(zbx_rtc_t *rtc, zbx_uint32_t code, const unsigned char *data,
		char **result)
{
	zbx_uint32_t	notify_code, notify_size;
	int		process_num;
	char		*notify_data = NULL;
	unsigned char	process_type;

	switch (code)
	{
#if defined(HAVE_SIGQUEUE)
		case ZBX_RTC_LOG_LEVEL_INCREASE:
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			rtc_process_loglevel_option(rtc, code, (const char *)data, result);
			return;
		case ZBX_RTC_PROF_ENABLE:
		case ZBX_RTC_PROF_DISABLE:
			rtc_process_profiler_option(code, (const char *)data, result);
			return;
#endif
		case ZBX_RTC_HOUSEKEEPER_EXECUTE:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_HOUSEKEEPER, 0, ZBX_RTC_HOUSEKEEPER_EXECUTE, NULL, 0);
			return;
		case ZBX_RTC_CONFIG_CACHE_RELOAD:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_CONFSYNCER, 0, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			return;
		case ZBX_RTC_SNMP_CACHE_RELOAD:
#ifdef HAVE_NETSNMP
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_SNMP_POLLER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_POLLER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_UNREACHABLE, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_DISCOVERYMANAGER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
#else
			*result = zbx_strdup(NULL, "Invalid runtime control option: no SNMP support enabled\n");
#endif
			return;
		case ZBX_RTC_DIAGINFO:
			rtc_process_diaginfo((const char *)data, result);
			return;
		case ZBX_RTC_NOTIFY:
			data += zbx_deserialize_value(data, &process_type);
			data += zbx_deserialize_int(data, &process_num);
			data += zbx_deserialize_value(data, &notify_code);
			data += zbx_deserialize_value(data, &notify_size);
			(void)zbx_deserialize_str(data, &notify_data, notify_size);
			zbx_rtc_notify(rtc, process_type, process_num, notify_code, notify_data, notify_size);
			zbx_free(notify_data);
			return;
		case ZBX_RTC_HISTORY_CACHE_CLEAR:
			rtc_history_cache_clear((const char *)data, result);
			return;
		case ZBX_RTC_DBPOOL_STATUS:
			rtc_process_db_status(result);
			return;
		case ZBX_RTC_DBPOOL_SET_MAX_IDLE:
			rtc_process_db_set_max_idle((const char *)data, result);
			return;
		case ZBX_RTC_DBPOOL_SET_MAX_OPEN:
			rtc_process_db_set_max_open((const char *)data, result);
			return;
		case ZBX_RTC_DBPOOL_SET_IDLE_TIMEOUT:
			rtc_process_db_set_idle_timeout((const char *)data, result);
			return;
		case ZBX_RTC_STATUS:
			rtc_process_status(result);
			return;
		default:
			*result = zbx_strdup(*result, "Unknown runtime control option\n");
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add hook for the specified control code                           *
 *                                                                            *
 ******************************************************************************/
static void	rtc_add_control_hook(zbx_rtc_t *rtc, zbx_ipc_client_t *client, zbx_uint32_t code)
{
	zbx_rtc_hook_t	*hook;

	hook = (zbx_rtc_hook_t *)zbx_malloc(NULL, sizeof(zbx_rtc_hook_t));
	hook->client = client;
	hook->code = code;
	zbx_vector_rtc_hook_append(&rtc->hooks, hook);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify matching hooks and remove them                             *
 *                                                                            *
 ******************************************************************************/
static void	rtc_notify_hooks(zbx_rtc_t *rtc, zbx_uint32_t code, unsigned char *data, zbx_uint32_t size)
{
	int	i;

	for (i = 0; i < rtc->hooks.values_num;)
	{
		if (rtc->hooks.values[i]->code == code)
		{
			(void)zbx_ipc_client_send(rtc->hooks.values[i]->client, code, data, size);
			zbx_free(rtc->hooks.values[i]);
			zbx_vector_rtc_hook_remove_noorder(&rtc->hooks, i);
			continue;
		}
		i++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process runtime control option                                    *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process(zbx_rtc_t *rtc, zbx_ipc_client_t *client, zbx_uint32_t code, const unsigned char *data,
		zbx_rtc_process_request_ex_func_t cb_proc_req)
{
	char		*result = NULL, *result_ex = NULL;
	zbx_uint32_t	size = 0;

	if (NULL == cb_proc_req || FAIL == cb_proc_req(rtc, code, data, &result_ex))
		rtc_process_request(rtc, code, data, &result);

	if (ZBX_RTC_NOTIFY != code)
	{
		if (NULL != result_ex)
			result = zbx_strdcat(result, result_ex);

		if (NULL == result)
		{
			/* generate default success message if no specific success or error messages were returned */
			result = zbx_strdup(NULL, "Runtime control command was forwarded successfully\n");
		}

		size = (zbx_uint32_t)strlen(result) + 1;
		zbx_ipc_client_send(client, code, (unsigned char *)result, size);
	}

	zbx_free(result_ex);
	zbx_free(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize runtime control service                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_init(zbx_rtc_t *rtc ,char **error)
{
	zbx_vector_rtc_sub_create(&rtc->subs);
	zbx_vector_rtc_hook_create(&rtc->hooks);
	return zbx_ipc_service_start(&rtc->service, ZBX_IPC_SERVICE_RTC, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: accept and process runtime control request                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_dispatch(zbx_rtc_t *rtc, zbx_ipc_client_t *client, zbx_ipc_message_t *message,
		zbx_rtc_process_request_ex_func_t cb_proc_req)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() code:%u size:%u", __func__, message->code, message->size);

	switch (message->code)
	{
		case ZBX_RTC_SUBSCRIBE:
			rtc_subscribe(rtc, client, message->data);
			break;
		case ZBX_RTC_SUBSCRIBE_SERVICE:
			rtc_subscribe_service(rtc, message->data);
			break;
		case ZBX_RTC_UNSUBSCRIBE_SERVICE:
			rtc_unsubscribe_service(rtc, message->data);
			break;
		case ZBX_RTC_CONFIG_CACHE_RELOAD_WAIT:
			rtc_add_control_hook(rtc, client, ZBX_RTC_CONFIG_SYNC_NOTIFY);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_CONFSYNCER, 0, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			break;
		case ZBX_RTC_CONFIG_SYNC_NOTIFY:
		case ZBX_RTC_SERVICE_SYNC_NOTIFY:
			rtc_notify_hooks(rtc, message->code, message->data, message->size);
			break;
		default:
			rtc_process(rtc, client, message->code, message->data, cb_proc_req);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send shutdown signal to all subscribers                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_shutdown_subs(zbx_rtc_t *rtc)
{
	rtc_notify(rtc, ZBX_PROCESS_TYPE_UNKNOWN, 0, ZBX_RTC_SHUTDOWN, NULL, 0, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free rtc subscription                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_sub_free(zbx_rtc_sub_t *sub)
{
	switch (sub->type)
	{
		case ZBX_RTC_SUB_CLIENT:
			zbx_ipc_client_close(sub->source.client);
			break;
		case ZBX_RTC_SUB_SERVICE:
			zbx_free(sub->source.service);
			break;
	}

	zbx_vector_rtc_msg_destroy(&sub->msgs);
	zbx_free(sub);
}
