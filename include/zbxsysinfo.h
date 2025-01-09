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

#ifndef ZABBIX_ZBXSYSINFO_H
#define ZABBIX_ZBXSYSINFO_H

#include "zbxcommon.h"
#include "module.h"
#include "zbxthreads.h"

/* RETRIEVE RESULT VALUE */

#define ZBX_GET_UI64_RESULT(res)	((zbx_uint64_t *)get_result_value_by_type(res, AR_UINT64))
#define ZBX_GET_DBL_RESULT(res)		((double *)get_result_value_by_type(res, AR_DOUBLE))
#define ZBX_GET_STR_RESULT(res)		((char **)get_result_value_by_type(res, AR_STRING))
#define ZBX_GET_TEXT_RESULT(res)	((char **)get_result_value_by_type(res, AR_TEXT))
#define ZBX_GET_BIN_RESULT(res)		((char **)get_result_value_by_type(res, AR_BIN))
#define ZBX_GET_LOG_RESULT(res)		((zbx_log_t *)get_result_value_by_type(res, AR_LOG))
#define ZBX_GET_MSG_RESULT(res)		((char **)get_result_value_by_type(res, AR_MESSAGE))

void	*get_result_value_by_type(AGENT_RESULT *result, int require_type);

/* collector */
#define ZBX_MAX_COLLECTOR_HISTORY	(15 * SEC_PER_MIN + 1)
#define ZBX_AVG1		0
#define ZBX_AVG5		1
#define ZBX_AVG15		2
#define ZBX_AVG_COUNT		3

#if defined(_WINDOWS)
#	define ZBX_MAX_COLLECTOR_PERIOD	(15 * SEC_PER_MIN)
#endif

#define ZBX_CPU_STATE_USER	0
#define ZBX_CPU_STATE_SYSTEM	1
#define ZBX_CPU_STATE_NICE	2
#define ZBX_CPU_STATE_IDLE	3
#define ZBX_CPU_STATE_INTERRUPT	4
#define ZBX_CPU_STATE_IOWAIT	5
#define ZBX_CPU_STATE_SOFTIRQ	6
#define ZBX_CPU_STATE_STEAL	7
#define ZBX_CPU_STATE_GCPU	8
#define ZBX_CPU_STATE_GNICE	9
#define ZBX_CPU_STATE_SPIN	10
#define ZBX_CPU_STATE_COUNT	11

#define ZBX_DSTAT_TYPE_SECT	0
#define ZBX_DSTAT_TYPE_OPER	1
#define ZBX_DSTAT_TYPE_BYTE	2
#define ZBX_DSTAT_TYPE_SPS	3
#define ZBX_DSTAT_TYPE_OPS	4
#define ZBX_DSTAT_TYPE_BPS	5

/* disk statistics */
#define ZBX_DSTAT_R_SECT	0
#define ZBX_DSTAT_R_OPER	1
#define ZBX_DSTAT_R_BYTE	2
#define ZBX_DSTAT_W_SECT	3
#define ZBX_DSTAT_W_OPER	4
#define ZBX_DSTAT_W_BYTE	5
#define ZBX_DSTAT_MAX		6
int	zbx_get_diskstat(const char *devname, zbx_uint64_t *dstat);

/* flags for process */
#define ZBX_PROCESS_LOCAL_COMMAND	0x1
#define ZBX_PROCESS_MODULE_COMMAND	0x2
#define ZBX_PROCESS_WITH_ALIAS		0x4

typedef enum
{
	ZBX_KEY_ACCESS_ALLOW,
	ZBX_KEY_ACCESS_DENY
}
zbx_key_access_rule_type_t;

void	zbx_init_library_sysinfo(zbx_get_config_int_f get_config_timeout_f, zbx_get_config_int_f
		get_config_enable_remote_commands_f, zbx_get_config_int_f get_config_log_remote_commands_f,
		zbx_get_config_int_f get_config_unsafe_user_parameters_cb, zbx_get_config_str_f
		get_config_source_ip_f, zbx_get_config_str_f get_config_hostname_f, zbx_get_config_str_f
		get_config_hostnames_f, zbx_get_config_str_f get_config_host_metadata_f, zbx_get_config_str_f
		get_config_host_metadata_item_f, zbx_get_config_str_f get_config_service_name_f);

void	zbx_init_metrics(void);
int	zbx_add_metric(zbx_metric_t *metric, char *error, size_t max_error_len);
void	zbx_free_metrics_ext(zbx_metric_t **metrics);
void	zbx_free_metrics(void);

void	zbx_init_key_access_rules(void);
void	zbx_finalize_key_access_rules_configuration(void);

int	zbx_add_key_access_rule(const char *parameter, char *pattern, zbx_key_access_rule_type_t type);
int	zbx_check_key_access_rules(const char *metric);
int	zbx_check_request_access_rules(AGENT_REQUEST *request);
void	zbx_free_key_access_rules(void);

int	zbx_execute_agent_check(const char *in_command, unsigned flags, AGENT_RESULT *result, int timeout);

void	zbx_set_user_parameter_dir(const char *path);
int	zbx_add_user_parameter(const char *itemkey, char *command, char *error, size_t max_error_len);
void	zbx_remove_user_parameters(void);
void	zbx_get_metrics_copy(zbx_metric_t **metrics);
void	zbx_set_metrics(zbx_metric_t *metrics);
void	zbx_test_parameters(void);
void	zbx_test_parameter(const char *key);

void	zbx_init_agent_request(AGENT_REQUEST *request);
void	zbx_free_agent_request(AGENT_REQUEST *request);

int	zbx_parse_item_key(const char *itemkey, AGENT_REQUEST *request);

int	zbx_set_agent_result_type(AGENT_RESULT *result, int value_type, char *c);
void	zbx_set_agent_result_meta(AGENT_RESULT *result, zbx_uint64_t lastlogsize, int mtime);

int	zbx_check_service_default_addr(AGENT_REQUEST *request, const char *default_addr, AGENT_RESULT *result, int perf);
int	zbx_check_service_validate(const unsigned char svc_type, const char *data);

/* the fields used by proc queries */
#define ZBX_SYSINFO_PROC_NONE		0x0000
#define ZBX_SYSINFO_PROC_PID		0x0001
#define ZBX_SYSINFO_PROC_NAME		0x0002
#define ZBX_SYSINFO_PROC_CMDLINE	0x0004
#define ZBX_SYSINFO_PROC_USER		0x0008

#if defined(_WINDOWS) || defined(__MINGW32__)
#define ZBX_MUTEX_ALL_ALLOW		0
#define ZBX_MUTEX_THREAD_DENIED		1
#define ZBX_MUTEX_LOGGING_DENIED	2
zbx_uint32_t	zbx_get_thread_global_mutex_flag(void);
#endif

void		zbx_add_alias(const char *name, const char *value);
void		zbx_alias_list_free(void);
const char	*zbx_alias_get(const char *orig);

int		zbx_init_modbus(char **error);
void		zbx_deinit_modbus(void);

/* stats */
ZBX_THREAD_ENTRY(zbx_collector_thread, args);

int	zbx_init_collector_data(char **error);
void	zbx_free_collector_data(void);

#if defined(_WINDOWS)
/* perfstat */
#include "zbxwin32.h"
zbx_perf_counter_data_t	*zbx_add_perf_counter(const char *name, const char *counterpath, int interval,
		zbx_perf_counter_lang_t lang, char **error);
void			zbx_remove_perf_counter(zbx_perf_counter_data_t *counter);

typedef enum
{
	ZBX_SINGLE_THREADED,
	ZBX_MULTI_THREADED
}
zbx_threadedness_t;

int	zbx_init_perf_collector(zbx_threadedness_t threadedness, char **error);
void	zbx_free_perf_collector(void);
#endif

#define ZBX_CHECK_TIMEOUT_UNDEFINED	0
int	zbx_validate_item_timeout(const char *timeout_str, int *sec_out, char *error, size_t error_len);

int	zbx_get_remote_zabbix_stats(const char *ip, unsigned short port, int timeout, AGENT_RESULT *result);
int	zbx_get_remote_zabbix_stats_queue(const char *ip, unsigned short port, const char *from, const char *to,
		int timeout, AGENT_RESULT *result);

#endif /* ZABBIX_ZBXSYSINFO_H */
