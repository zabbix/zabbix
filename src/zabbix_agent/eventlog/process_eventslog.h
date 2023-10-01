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

#ifndef ZBX_EVENTLOG_H
#define ZBX_EVENTLOG_H

#include "../logfiles/logfiles.h"
#include "../metrics.h"

#include "zbxcomms.h"
#include "zbxalgo.h"

int	process_eventslog(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, const char
		*eventlog_name, zbx_vector_expression_t *regexps, const char *pattern, const char *key_severity,
		const char *key_source, const char *key_logeventid, int rate, zbx_process_value_func_t process_value_cb,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *config_source_ip,
		const char *config_hostname, int config_buffer_send, int config_buffer_size, ZBX_ACTIVE_METRIC *metric,
		zbx_uint64_t *lastlogsize_sent, char **error);
#endif /* ZBX_EVENTLOG_H */
