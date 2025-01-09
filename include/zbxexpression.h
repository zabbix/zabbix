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

#define ZBX_EXPRESSION_NORMAL		0
#define ZBX_EXPRESSION_AGGREGATE	1

#define ZBX_MACRO_TYPE_MESSAGE_NORMAL		0x00000001
#define ZBX_MACRO_TYPE_MESSAGE_RECOVERY		0x00000002
#define ZBX_MACRO_TYPE_TRIGGER_URL		0x00000004
#define ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION	0x00000010	/* name */
#define ZBX_MACRO_TYPE_TRIGGER_COMMENTS		0x00000020	/* description */
#define ZBX_MACRO_TYPE_ITEM_KEY			0x00000040
#define ZBX_MACRO_TYPE_ALERT_EMAIL		0x00000080
#define ZBX_MACRO_TYPE_COMMON			0x00000100
#define ZBX_MACRO_TYPE_PARAMS_FIELD		0x00000200
#define ZBX_MACRO_TYPE_SCRIPT			0x00000400
#define ZBX_MACRO_TYPE_SNMP_OID			0x00000800
#define ZBX_MACRO_TYPE_LLD_FILTER		0x00002000
#define ZBX_MACRO_TYPE_TRIGGER_TAG		0x00004000
#define ZBX_MACRO_TYPE_JMX_ENDPOINT		0x00008000
#define ZBX_MACRO_TYPE_MESSAGE_UPDATE		0x00010000
#define ZBX_MACRO_TYPE_HTTP_RAW			0x00020000
#define ZBX_MACRO_TYPE_HTTP_JSON		0x00040000
#define ZBX_MACRO_TYPE_HTTP_XML			0x00080000
#define ZBX_MACRO_TYPE_ALLOWED_HOSTS		0x00100000
#define ZBX_MACRO_TYPE_EVENT_NAME		0x00400000	/* event name in trigger configuration */
#define ZBX_MACRO_TYPE_SCRIPT_PARAMS_FIELD	0x00800000
#define ZBX_MACRO_TYPE_SCRIPT_NORMAL		0x01000000
#define ZBX_MACRO_TYPE_SCRIPT_RECOVERY		0x02000000
#define ZBX_MACRO_TYPE_QUERY_FILTER		0x08000000

#define ZBX_MACRO_EXPAND_NO			0
#define ZBX_MACRO_EXPAND_YES			1

/* lld macro context */
#define ZBX_MACRO_ANY		(ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO | ZBX_TOKEN_USER_MACRO)
#define ZBX_MACRO_JSON		(ZBX_MACRO_ANY | ZBX_TOKEN_JSON)
#define ZBX_MACRO_FUNC		(ZBX_MACRO_ANY | ZBX_TOKEN_FUNC_MACRO | ZBX_TOKEN_USER_FUNC_MACRO)

/* group - hostids cache */
typedef struct
{
	char			*name;
	zbx_vector_uint64_t	hostids;
}
zbx_expression_group_t;

ZBX_PTR_VECTOR_DECL(expression_group_ptr, zbx_expression_group_t *)

/* item - tags cache */
typedef struct
{
	zbx_uint64_t		itemid;
	zbx_vector_item_tag_t	tags;
}
zbx_expression_item_t;

ZBX_PTR_VECTOR_DECL(expression_item_ptr, zbx_expression_item_t *)

/* expression item query */
typedef struct
{
	/* query flags, see ZBX_ITEM_QUERY_* defines */
	zbx_uint32_t		flags;

	/* the item query /host/key?[filter] */
	zbx_item_query_t	ref;

	/* the query error */
	char			*error;

	/* the expression item query data, zbx_expression_query_one_t or zbx_expression_query_many_t */
	void			*data;
}
zbx_expression_query_t;

ZBX_PTR_VECTOR_DECL(expression_query_ptr, zbx_expression_query_t *)

typedef struct
{
	zbx_eval_context_t			*ctx;
	zbx_vector_expression_query_ptr_t	queries;
	int					mode;
	int					one_num;
	int					many_num;
	zbx_uint64_t				hostid;

	/* cache to resolve one item queries */
	zbx_host_key_t		*hostkeys;
	zbx_dc_item_t		*dcitems_hk;
	int			*errcodes_hk;

	/* cache to resolve many item queries */
	zbx_vector_expression_group_ptr_t	groups;
	zbx_vector_expression_item_ptr_t	itemtags;
	zbx_vector_dc_item_t			dcitem_refs;
	zbx_dc_item_t				*dcitems;
	int					*errcodes;
	int					dcitems_num;
}
zbx_expression_eval_t;

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

void	zbx_substitute_simple_macros_allowed_hosts(zbx_history_recv_item_t *item, char **allowed_peers);

void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type);

void	zbx_determine_items_in_expressions(zbx_vector_dc_trigger_t *trigger_order, const zbx_uint64_t *itemids,
		int item_num);

void	zbx_expression_eval_init(zbx_expression_eval_t *eval, int mode, zbx_eval_context_t *ctx);
void	zbx_expression_eval_clear(zbx_expression_eval_t *eval);
void	zbx_expression_eval_resolve_item_hosts(zbx_expression_eval_t *eval, const zbx_dc_item_t *item);
void	zbx_expression_eval_resolve_filter_macros(zbx_expression_eval_t *eval, const zbx_dc_item_t *item);
void	zbx_expression_eval_resolve_trigger_hosts_items(zbx_expression_eval_t *eval, const zbx_db_trigger *trigger);
int	zbx_expression_eval_execute(zbx_expression_eval_t *eval, const zbx_timespec_t *ts, zbx_variant_t *value,
		char **error);

/* evaluate simple */
int	zbx_evaluate(double *value, const char *expression, char *error, size_t max_error_len,
		zbx_vector_str_t *unknown_msgs);
int	zbx_evaluate_unknown(const char *expression, double *value, char *error, size_t max_error_len);
double	zbx_evaluate_string_to_double(const char *in);
int	zbx_evaluatable_for_notsupported(const char *fn);
int	zbx_evaluate_function(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *function,
		const char *parameter, const zbx_timespec_t *ts, char **error);

int	zbx_substitute_lld_macros(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, int flags, char *error, size_t max_error_len);
int	zbx_substitute_key_macros(char **data, zbx_uint64_t *hostid, zbx_dc_item_t *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		int macro_type, char *error, size_t maxerrlen);
int	zbx_substitute_key_macros_unmasked(char **data, zbx_uint64_t *hostid, zbx_dc_item_t *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		int macro_type, char *error, size_t maxerrlen);
int	zbx_substitute_function_lld_param(const char *e, size_t len, unsigned char key_in_param,
		char **exp, size_t *exp_alloc, size_t *exp_offset, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, int esc_flags, char *error,
		size_t max_error_len);
int	zbx_substitute_macros_xml(char **data, const zbx_dc_item_t *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	zbx_substitute_macros_xml_unmasked(char **data, const zbx_dc_item_t *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	zbx_substitute_macros_in_json_pairs(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, int maxerrlen);

int	zbx_substitute_expression_lld_macros(char **data, zbx_uint64_t rules, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error);

void	zbx_count_dbl_vector_with_pattern(zbx_eval_count_pattern_data_t *pdata, char *pattern,
		zbx_vector_dbl_t *values, int *count);

const char	*zbx_dservice_type_string(zbx_dservice_type_t service);
#endif
