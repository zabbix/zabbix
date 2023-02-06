/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_ALERTER_H
#define ZABBIX_ALERTER_H

#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"

#define ZBX_IPC_SERVICE_ALERTER	"alerter"

/* alerter -> manager */
#define ZBX_IPC_ALERTER_REGISTER	1000
#define ZBX_IPC_ALERTER_RESULT		1001
#define ZBX_IPC_ALERTER_MEDIATYPES	1002
#define ZBX_IPC_ALERTER_ALERTS		1003
#define ZBX_IPC_ALERTER_WATCHDOG	1004
#define ZBX_IPC_ALERTER_RESULTS		1005
#define ZBX_IPC_ALERTER_DROP_MEDIATYPES	1006

/* manager -> alerter */
#define ZBX_IPC_ALERTER_EMAIL		1100
#define ZBX_IPC_ALERTER_SMS		1102
#define ZBX_IPC_ALERTER_EXEC		1104
#define ZBX_IPC_ALERTER_WEBHOOK		1105

/* process -> manager */
#define ZBX_IPC_ALERTER_DIAG_STATS		1200
#define ZBX_IPC_ALERTER_DIAG_TOP_MEDIATYPES	1201
#define ZBX_IPC_ALERTER_DIAG_TOP_SOURCES	1202
#define ZBX_IPC_ALERTER_SEND_ALERT		1203
#define ZBX_IPC_ALERTER_BEGIN_DISPATCH		1204
#define ZBX_IPC_ALERTER_SEND_DISPATCH		1205
#define ZBX_IPC_ALERTER_END_DISPATCH		1206

/* manager -> process */
#define ZBX_IPC_ALERTER_DIAG_STATS_RESULT		1300
#define ZBX_IPC_ALERTER_DIAG_TOP_MEDIATYPES_RESULT	1301
#define ZBX_IPC_ALERTER_DIAG_TOP_SOURCES_RESULT		1302
#define ZBX_IPC_ALERTER_ABORT_DISPATCH			1303

typedef struct
{
	int		source;
	int		object;
	zbx_uint64_t	objectid;
	int		alerts_num;
}
zbx_am_source_stats_t;

typedef struct
{
	char	*recipient;
	char	*info;
	int	status;
}
zbx_alerter_dispatch_result_t;

ZBX_PTR_VECTOR_DECL(alerter_dispatch_result, zbx_alerter_dispatch_result_t *)

typedef struct
{
	zbx_ipc_async_socket_t			alerter;
	int					total_num;
	zbx_vector_alerter_dispatch_result_t	results;
}
zbx_alerter_dispatch_t;

typedef struct
{
	zbx_get_config_forks_f		get_process_forks_cb_arg;
	zbx_get_config_str_f		get_scripts_path_cb_arg;
	const zbx_config_dbhigh_t	*config_dbhigh;
}
zbx_thread_alert_manager_args;

typedef struct
{
	int			confsyncer_frequency;
}
zbx_thread_alert_syncer_args;

ZBX_THREAD_ENTRY(zbx_alerter_thread, args);
ZBX_THREAD_ENTRY(zbx_alert_manager_thread, args);
ZBX_THREAD_ENTRY(zbx_alert_syncer_thread, args);

int	zbx_alerter_get_diag_stats(zbx_uint64_t *alerts_num, char **error);
int	zbx_alerter_get_top_mediatypes(int limit, zbx_vector_uint64_pair_t *mediatypes, char **error);
int	zbx_alerter_get_top_sources(int limit, zbx_vector_ptr_t *sources, char **error);

zbx_uint32_t	zbx_alerter_serialize_alert_send(unsigned char **data, zbx_uint64_t mediatypeid, unsigned char type,
		const char *smtp_server, const char *smtp_helo, const char *smtp_email, const char *exec_path,
		const char *gsm_modem, const char *username, const char *passwd, unsigned short smtp_port,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, int maxsessions, int maxattempts, const char *attempt_interval,
		unsigned char content_type, const char *script, const char *timeout, const char *sendto,
		const char *subject, const char *message, const char *params);

void	zbx_alerter_deserialize_result_ext(const unsigned char *data, char **recipient, char **value, int *errcode,
		char **error, char **debug);

int	zbx_alerter_begin_dispatch(zbx_alerter_dispatch_t *dispatch, const char *subject, const char *message,
		const char *content_name, const char *content_type, const char *content, zbx_uint32_t content_size,
		char **error);
int	zbx_alerter_send_dispatch(zbx_alerter_dispatch_t *dispatch, const zbx_db_mediatype *mediatype,
		const zbx_vector_str_t *recipients, char **error);
int	zbx_alerter_end_dispatch(zbx_alerter_dispatch_t *dispatch, char **error);
void	zbx_alerter_clear_dispatch(zbx_alerter_dispatch_t *dispatch);
void	zbx_alerter_dispatch_result_free(zbx_alerter_dispatch_result_t *result);

#endif
