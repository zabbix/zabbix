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

#ifndef ZABBIX_ZBXSERVER_H
#define ZABBIX_ZBXSERVER_H

#include "zbxcacheconfig.h"
#include "zbxvariant.h"

#define MACRO_TYPE_MESSAGE_NORMAL	0x00000001
#define MACRO_TYPE_MESSAGE_RECOVERY	0x00000002
#define MACRO_TYPE_TRIGGER_URL		0x00000004
#define MACRO_TYPE_TRIGGER_EXPRESSION	0x00000008
#define MACRO_TYPE_TRIGGER_DESCRIPTION	0x00000010	/* name */
#define MACRO_TYPE_TRIGGER_COMMENTS	0x00000020	/* description */
#define MACRO_TYPE_ITEM_KEY		0x00000040
#define MACRO_TYPE_INTERFACE_ADDR	0x00000080
#define MACRO_TYPE_COMMON		0x00000100
#define MACRO_TYPE_PARAMS_FIELD		0x00000200
#define MACRO_TYPE_SCRIPT		0x00000400
#define MACRO_TYPE_SNMP_OID		0x00000800
#define MACRO_TYPE_HTTPTEST_FIELD	0x00001000
#define MACRO_TYPE_LLD_FILTER		0x00002000
#define MACRO_TYPE_TRIGGER_TAG		0x00004000
#define MACRO_TYPE_JMX_ENDPOINT		0x00008000
#define MACRO_TYPE_MESSAGE_UPDATE	0x00010000
#define MACRO_TYPE_HTTP_RAW		0x00020000
#define MACRO_TYPE_HTTP_JSON		0x00040000
#define MACRO_TYPE_HTTP_XML		0x00080000
#define MACRO_TYPE_ALLOWED_HOSTS	0x00100000
#define MACRO_TYPE_ITEM_TAG		0x00200000
#define MACRO_TYPE_EVENT_NAME		0x00400000	/* event name in trigger configuration */
#define MACRO_TYPE_SCRIPT_PARAMS_FIELD	0x00800000
#define MACRO_TYPE_SCRIPT_NORMAL	0x01000000
#define MACRO_TYPE_SCRIPT_RECOVERY	0x02000000
#define MACRO_TYPE_REPORT		0x04000000
#define MACRO_TYPE_QUERY_FILTER		0x08000000

#define MACRO_EXPAND_NO			0
#define MACRO_EXPAND_YES		1

/* acknowledgment actions (flags) */
#define ZBX_PROBLEM_UPDATE_CLOSE		0x0001
#define ZBX_PROBLEM_UPDATE_ACKNOWLEDGE		0x0002
#define ZBX_PROBLEM_UPDATE_MESSAGE		0x0004
#define ZBX_PROBLEM_UPDATE_SEVERITY		0x0008
#define ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE	0x0010
#define ZBX_PROBLEM_UPDATE_SUPPRESS		0x0020
#define ZBX_PROBLEM_UPDATE_UNSUPPRESS		0x0040
#define ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE	0x0080
#define ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM	0x0100

#define ZBX_PROBLEM_UPDATE_ACTION_COUNT	9

/* service supported by discoverer */
typedef enum
{
	SVC_SSH = 0,
	SVC_LDAP,
	SVC_SMTP,
	SVC_FTP,
	SVC_HTTP,
	SVC_POP,
	SVC_NNTP,
	SVC_IMAP,
	SVC_TCP,
	SVC_AGENT,
	SVC_SNMPv1,
	SVC_SNMPv2c,
	SVC_ICMPPING,
	SVC_SNMPv3,
	SVC_HTTPS,
	SVC_TELNET
}
zbx_dservice_type_t;

int	zbx_substitute_simple_macros(const zbx_uint64_t *actionid, const zbx_db_event *event, const zbx_db_event *r_event,
		const zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		const zbx_db_alert *alert, const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm,
		const zbx_db_service *service, const char *tz, char **data, int macro_type, char *error, int maxerrlen);

int	zbx_substitute_simple_macros_unmasked(const zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host,
		const DC_ITEM *dc_item, const zbx_db_alert *alert, const zbx_db_acknowledge *ack,
		const zbx_service_alarm_t *service_alarm, const zbx_db_service *service, const char *tz, char **data,
		int macro_type, char *error, int maxerrlen);

void	zbx_substitute_simple_macros_allowed_hosts(zbx_history_recv_item_t *item, char **allowed_peers);

void	zbx_evaluate_expressions(zbx_vector_ptr_t *triggers, const zbx_vector_uint64_t *history_itemids,
		const zbx_history_sync_item_t *history_items, const int *history_errcodes);
void	zbx_prepare_triggers(DC_TRIGGER **triggers, int triggers_num);

void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type);

void	zbx_determine_items_in_expressions(zbx_vector_ptr_t *trigger_order, const zbx_uint64_t *itemids, int item_num);

#define ZBX_EXPRESSION_NORMAL		0
#define ZBX_EXPRESSION_AGGREGATE	1

typedef struct
{
	zbx_eval_context_t	*ctx;
	zbx_vector_ptr_t	queries;
	int			mode;
	int			one_num;
	int			many_num;
	zbx_uint64_t		hostid;

	/* cache to resolve one item queries */
	zbx_host_key_t		*hostkeys;
	DC_ITEM			*dcitems_hk;
	int			*errcodes_hk;

	/* cache to resolve many item queries */
	zbx_vector_ptr_t	groups;
	zbx_vector_ptr_t	itemtags;
	zbx_vector_ptr_t	dcitem_refs;
	DC_ITEM			*dcitems;
	int			*errcodes;
	int			dcitems_num;
}
zbx_expression_eval_t;

void	zbx_expression_eval_init(zbx_expression_eval_t *eval, int mode, zbx_eval_context_t *ctx);
void	zbx_expression_eval_clear(zbx_expression_eval_t *eval);
void	zbx_expression_eval_resolve_item_hosts(zbx_expression_eval_t *eval, const DC_ITEM *item);
void	zbx_expression_eval_resolve_filter_macros(zbx_expression_eval_t *eval, const DC_ITEM *item);
void	zbx_expression_eval_resolve_trigger_hosts_items(zbx_expression_eval_t *eval, const zbx_db_trigger *trigger);
int	zbx_expression_eval_execute(zbx_expression_eval_t *eval, const zbx_timespec_t *ts, zbx_variant_t *value,
		char **error);

/* evaluate simple */
int	zbx_evaluate(double *value, const char *expression, char *error, size_t max_error_len,
		zbx_vector_ptr_t *unknown_msgs);
int	zbx_evaluate_unknown(const char *expression, double *value, char *error, size_t max_error_len);
double	zbx_evaluate_string_to_double(const char *in);

/* lld macro context */
#define ZBX_MACRO_ANY		(ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO | ZBX_TOKEN_USER_MACRO)
#define ZBX_MACRO_JSON		(ZBX_MACRO_ANY | ZBX_TOKEN_JSON)
#define ZBX_MACRO_FUNC		(ZBX_MACRO_ANY | ZBX_TOKEN_FUNC_MACRO)

int	zbx_substitute_lld_macros(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, int flags, char *error, size_t max_error_len);
int	zbx_substitute_key_macros(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths, int macro_type,
		char *error, size_t maxerrlen);
int	zbx_substitute_key_macros_unmasked(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths, int macro_type,
		char *error, size_t maxerrlen);
int	zbx_substitute_function_lld_param(const char *e, size_t len, unsigned char key_in_param,
		char **exp, size_t *exp_alloc, size_t *exp_offset, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, size_t max_error_len);
int	zbx_substitute_macros_xml(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	zbx_substitute_macros_xml_unmasked(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	zbx_substitute_macros_in_json_pairs(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);

int	zbx_substitute_expression_lld_macros(char **data, zbx_uint64_t rules, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char **error);
#endif
