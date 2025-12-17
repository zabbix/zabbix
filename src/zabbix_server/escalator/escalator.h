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

#ifndef ZABBIX_ESCALATOR_H
#define ZABBIX_ESCALATOR_H

#include "zbxthreads.h"
#include "zbxcomms.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"

#define ZBX_MESSAGE_TYPE_NORMAL		1
#define ZBX_MESSAGE_TYPE_UPDATE		2
#define ZBX_MESSAGE_TYPE_RECOVERY	3

typedef struct
{
	zbx_config_tls_t	*zbx_config_tls;
	zbx_get_program_type_f	zbx_get_program_type_cb_arg;
	int			config_timeout;
	int			config_trapper_timeout;
	const char		*config_source_ip;
	const char		*config_ssh_key_location;
	zbx_get_config_forks_f	get_process_forks_cb_arg;
	int			config_enable_global_scripts;
}
zbx_thread_escalator_args;

ZBX_THREAD_ENTRY(escalator_thread, args);

int	substitute_message_macros(char **data, char *error, int maxerrlen, int message_type,
		zbx_dc_um_handle_t * um_handle, zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, zbx_uint64_t *userid, const zbx_dc_host_t *dc_host,
		const zbx_db_alert *alert, const zbx_service_alarm_t *service_alarm, const zbx_db_service *service,
		const char *tz, const zbx_db_acknowledge *ack);

#endif
