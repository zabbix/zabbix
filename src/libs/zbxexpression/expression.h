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

#ifndef ZABBIX_EXPRESSION_H
#define ZABBIX_EXPRESSION_H

#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxjson.h"

int	substitute_key_macros_impl(char **data, zbx_uint64_t *hostid, zbx_dc_item_t *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_lld_macro_path_t *lld_macro_paths, int macro_type,
		char *error, size_t maxerrlen);
int	substitute_simple_macros_impl(const zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid,
		const zbx_dc_host_t *dc_host, const zbx_dc_item_t *dc_item, const zbx_db_alert *alert,
		const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm, const zbx_db_service *service,
		const char *tz, zbx_history_recv_item_t *history_data_item, char **data, int macro_type, char *error,
		int maxerrlen);

int	zbx_expr_macro_index(const char *macro);

#endif
