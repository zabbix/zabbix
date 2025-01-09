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

#ifndef ZABBIX_ZBXRTC_H
#define ZABBIX_ZBXRTC_H

#include "zbxalgo.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"
#include "zbxjson.h"

#define ZBX_IPC_SERVICE_RTC	"rtc"

typedef enum
{
	ZBX_RTC_SUB_CLIENT,
	ZBX_RTC_SUB_SERVICE
}
zbx_rtc_sub_type_t;

typedef union
{
	zbx_ipc_client_t	*client;
	char			*service;
}
zbx_rtc_sub_source_t;

typedef struct
{
	zbx_rtc_sub_type_t	type;
	zbx_rtc_sub_source_t	source;
	unsigned char		process_type;
	int			process_num;
	zbx_vector_uint32_t	msgs;
}
zbx_rtc_sub_t;

ZBX_PTR_VECTOR_DECL(rtc_sub, zbx_rtc_sub_t *)

typedef struct
{
	zbx_ipc_client_t	*client;
	zbx_uint32_t		code;
}
zbx_rtc_hook_t;

ZBX_PTR_VECTOR_DECL(rtc_hook, zbx_rtc_hook_t *)

typedef struct
{
	zbx_ipc_service_t	service;
	zbx_vector_rtc_sub_t	subs;
	zbx_vector_rtc_hook_t	hooks;
}
zbx_rtc_t;

typedef int	(*zbx_rtc_process_request_ex_func_t)(zbx_rtc_t *, zbx_uint32_t, const unsigned char *, char **);

/* provider API */
int	zbx_rtc_init(zbx_rtc_t *rtc ,char **error);
void 	zbx_rtc_dispatch(zbx_rtc_t *rtc, zbx_ipc_client_t *client, zbx_ipc_message_t *message,
		zbx_rtc_process_request_ex_func_t cb_proc_req);
int	zbx_rtc_wait_for_sync_finish(zbx_rtc_t *rtc, zbx_rtc_process_request_ex_func_t cb_proc_req);
void	zbx_rtc_shutdown_subs(zbx_rtc_t *rtc);

/* client API */
void	zbx_rtc_notify_finished_sync(int config_timeout, zbx_uint32_t code, const char *process_name,
		zbx_ipc_async_socket_t *rtc);

void	zbx_rtc_subscribe(unsigned char proc_type, int proc_num, zbx_uint32_t *msgs, int msgs_num, int config_timeout,
		zbx_ipc_async_socket_t *rtc);
void	zbx_rtc_subscribe_service(unsigned char proc_type, int proc_num, zbx_uint32_t *msgs, int msgs_num,
		int config_timeout, const char *service);
int	zbx_rtc_wait(zbx_ipc_async_socket_t *rtc, const zbx_thread_info_t *info, zbx_uint32_t *cmd,
		unsigned char **data, int timeout);
int	zbx_rtc_reload_config_cache(char **error);

int	zbx_rtc_parse_options(const char *opt, zbx_uint32_t *code, struct zbx_json *j, char **error);
int	zbx_rtc_notify(zbx_rtc_t *rtc, unsigned char process_type, int process_num, zbx_uint32_t code,
		const char *data, zbx_uint32_t size);
int	zbx_rtc_notify_generic(zbx_ipc_async_socket_t *rtc, unsigned char process_type, int process_num,
		zbx_uint32_t code, const char *data, zbx_uint32_t size);

int	zbx_rtc_async_exchange(char **data, zbx_uint32_t code, int config_timeout, char **error);

int	zbx_rtc_get_command_target(const char *data, pid_t *pid, int *proc_type, int *proc_num, int *scope,
		char **result);

void	zbx_rtc_sub_free(zbx_rtc_sub_t *sub);
#endif
