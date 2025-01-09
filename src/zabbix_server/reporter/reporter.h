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
#ifndef ZABBIX_REPORTER_H
#define ZABBIX_REPORTER_H

#include "zbxthreads.h"
#include "zbxjson.h"

void	zbx_report_test(const struct zbx_json_parse *jp, zbx_uint64_t userid, struct zbx_json *j);

typedef struct
{
	zbx_get_config_forks_f	get_process_forks_cb_arg;
}
zbx_thread_report_manager_args;

ZBX_THREAD_ENTRY(report_manager_thread, args);

typedef struct
{
	char	*config_tls_ca_file;
	char	*config_tls_cert_file;
	char	*config_tls_key_file;
	char	*config_source_ip;
	char	*config_webservice_url;
}
zbx_thread_report_writer_args;

ZBX_THREAD_ENTRY(report_writer_thread, args);


#endif
