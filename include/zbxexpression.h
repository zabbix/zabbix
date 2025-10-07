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

#ifndef ZABBIX_ZBXEXPRESSION_H
#define ZABBIX_ZBXEXPRESSION_H

#include "zbxcacheconfig.h"
#include "zbxvariant.h"
#include "zbx_discoverer_constants.h"

#define ZBX_MACRO_TYPE_TRIGGER_URL		0x00000004
#define ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION	0x00000010	/* name */
#define ZBX_MACRO_TYPE_TRIGGER_COMMENTS		0x00000020	/* description */
#define ZBX_MACRO_TYPE_JMX_ENDPOINT		0x00008000
#define ZBX_MACRO_TYPE_EVENT_NAME		0x00400000	/* event name in trigger configuration */

#define ZBX_MACRO_EXPAND_NO			0
#define ZBX_MACRO_EXPAND_YES			1

/* lld macro context */
#define ZBX_MACRO_ANY		(ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO | ZBX_TOKEN_USER_MACRO)
#define ZBX_MACRO_JSON		(ZBX_MACRO_ANY | ZBX_TOKEN_JSON)
#define ZBX_MACRO_FUNC		(ZBX_MACRO_ANY | ZBX_TOKEN_FUNC_MACRO | ZBX_TOKEN_USER_FUNC_MACRO)


int	zbx_substitute_simple_macros(const zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid,
		const zbx_dc_host_t *dc_host, const zbx_dc_item_t *dc_item, const zbx_db_alert *alert,
		const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm, const zbx_db_service *service,
		const char *tz, char **data, int macro_type, char *error, int maxerrlen);

int	zbx_substitute_simple_macros_unmasked(const zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid,
		const zbx_dc_host_t *dc_host, const zbx_dc_item_t *dc_item, const zbx_db_alert *alert,
		const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm, const zbx_db_service *service,
		const char *tz, char **data, int macro_type, char *error, int maxerrlen);

void	zbx_determine_items_in_expressions(zbx_vector_dc_trigger_t *trigger_order, const zbx_uint64_t *itemids,
		int item_num);

void	zbx_count_dbl_vector_with_pattern(zbx_eval_count_pattern_data_t *pdata, char *pattern,
		zbx_vector_dbl_t *values, int *count);

const char	*zbx_dservice_type_string(zbx_dservice_type_t service);

int	zbx_get_history_log_value(const char *m, const zbx_db_trigger *trigger, char **replace_to, int N_functionid,
		int clock, int ns, const char *tz);
#endif
