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

#ifndef ZABBIX_ALERTER_H
#define ZABBIX_ALERTER_H

#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"
#include "zbxdb.h"

/* media type statuses */
#define MEDIA_TYPE_STATUS_ACTIVE	0
#define MEDIA_TYPE_STATUS_DISABLED	1

/* media statuses */
#define MEDIA_STATUS_ACTIVE	0
#define MEDIA_STATUS_DISABLED	1

#define ZBX_IPC_SERVICE_ALERTER	"alerter"

#define ZBX_IPC_ALERTER_SYNC_ALERTS	1400

typedef struct
{
	int		source;
	int		object;
	zbx_uint64_t	objectid;
	int		alerts_num;
}
zbx_am_source_stats_t;

ZBX_PTR_VECTOR_DECL(am_source_stats_ptr, zbx_am_source_stats_t *)

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
	const char	*config_source_ip;
	const char	*config_ssl_ca_location;
	const char	*config_sms_devices;
}
zbx_thread_alerter_args;

typedef struct
{
	zbx_get_config_forks_f		get_process_forks_cb_arg;
	zbx_get_config_str_f		get_scripts_path_cb_arg;
	const zbx_db_config_t		*db_config;
	const char			*config_source_ip;
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
int	zbx_alerter_get_top_sources(int limit, zbx_vector_am_source_stats_ptr_t *sources, char **error);

zbx_uint32_t	zbx_alerter_serialize_alert_send(unsigned char **data, zbx_uint64_t mediatypeid, unsigned char type,
		const char *smtp_server, const char *smtp_helo, const char *smtp_email, const char *exec_path,
		const char *gsm_modem, const char *username, const char *passwd, unsigned short smtp_port,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, int maxsessions, int maxattempts, const char *attempt_interval,
		unsigned char message_format, const char *script, const char *timeout, const char *sendto,
		const char *subject, const char *message, const char *params);

void	zbx_alerter_deserialize_result_ext(const unsigned char *data, char **recipient, char **value, int *errcode,
		char **error, char **debug);

int	zbx_alerter_begin_dispatch(zbx_alerter_dispatch_t *dispatch, const char *subject, const char *message,
		const char *content_name, const char *message_format, const char *content, zbx_uint32_t content_size,
		char **error);
int	zbx_alerter_send_dispatch(zbx_alerter_dispatch_t *dispatch, const zbx_db_mediatype *mediatype,
		const zbx_vector_str_t *recipients, char **error);
int	zbx_alerter_end_dispatch(zbx_alerter_dispatch_t *dispatch, char **error);
void	zbx_alerter_clear_dispatch(zbx_alerter_dispatch_t *dispatch);
void	zbx_alerter_dispatch_result_free(zbx_alerter_dispatch_result_t *result);

zbx_uint32_t	zbx_alerter_send_alert_code(void);

#endif
