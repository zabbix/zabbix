/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "common.h"
#include "db.h"
#include "dbcache.h"
#include "zbxjson.h"
#include "zbxvariant.h"

#define MACRO_TYPE_MESSAGE_NORMAL	0x00000001
#define MACRO_TYPE_MESSAGE_RECOVERY	0x00000002
#define MACRO_TYPE_TRIGGER_URL		0x00000004
#define MACRO_TYPE_TRIGGER_EXPRESSION	0x00000008
#define MACRO_TYPE_TRIGGER_DESCRIPTION	0x00000010	/* name */
#define MACRO_TYPE_TRIGGER_COMMENTS	0x00000020	/* description */
#define MACRO_TYPE_ITEM_KEY		0x00000040
#define MACRO_TYPE_INTERFACE_ADDR	0x00000100
#define MACRO_TYPE_COMMON		0x00000400
#define MACRO_TYPE_PARAMS_FIELD		0x00000800
#define MACRO_TYPE_SCRIPT		0x00001000
#define MACRO_TYPE_SNMP_OID		0x00002000
#define MACRO_TYPE_HTTPTEST_FIELD	0x00004000
#define MACRO_TYPE_LLD_FILTER		0x00008000
#define MACRO_TYPE_ALERT		0x00010000
#define MACRO_TYPE_TRIGGER_TAG		0x00020000
#define MACRO_TYPE_JMX_ENDPOINT		0x00040000
#define MACRO_TYPE_MESSAGE_ACK		0x00080000
#define MACRO_TYPE_HTTP_RAW		0x00100000
#define MACRO_TYPE_HTTP_JSON		0x00200000
#define MACRO_TYPE_HTTP_XML		0x00400000
#define MACRO_TYPE_ALLOWED_HOSTS	0x00800000
#define MACRO_TYPE_ITEM_TAG		0x01000000
#define MACRO_TYPE_EVENT_NAME		0x02000000	/* event name in trigger configuration */
#define MACRO_TYPE_EXPRESSION		0x04000000	/* macros in expression macro */

#define MACRO_EXPAND_NO			0
#define MACRO_EXPAND_YES		1

#define STR_CONTAINS_MACROS(str)	(NULL != strchr(str, '{'))

int	get_N_functionid(const char *expression, int N_functionid, zbx_uint64_t *functionid, const char **end);
void	get_functionids(zbx_vector_uint64_t *functionids, const char *expression);

int	evaluate_function(char **value, DC_ITEM *item, const char *function, const char *parameter,
		const zbx_timespec_t *ts, char **error);

int	substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, const char *tz, char **data, int macro_type, char *error,
		int maxerrlen);

int	substitute_simple_macros_unmasked(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, const char *tz, char **data, int macro_type, char *error,
		int maxerrlen);

void	evaluate_expressions(zbx_vector_ptr_t *triggers);

void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type);

void	zbx_determine_items_in_expressions(zbx_vector_ptr_t *trigger_order, const zbx_uint64_t *itemids, int item_num);

void	get_trigger_expression_constant(const char *expression, const zbx_token_reference_t *reference,
		char **constant);

/* lld macro context */
#define ZBX_MACRO_ANY		(ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO | ZBX_TOKEN_USER_MACRO)
#define ZBX_MACRO_NUMERIC	(ZBX_MACRO_ANY | ZBX_TOKEN_NUMERIC)
#define ZBX_MACRO_JSON		(ZBX_MACRO_ANY | ZBX_TOKEN_JSON)
#define ZBX_MACRO_XML		(ZBX_MACRO_ANY | ZBX_TOKEN_XML)
#define ZBX_MACRO_SIMPLE	(ZBX_MACRO_ANY | ZBX_TOKEN_SIMPLE_MACRO)
#define ZBX_MACRO_FUNC		(ZBX_MACRO_ANY | ZBX_TOKEN_FUNC_MACRO)

int	substitute_lld_macros(char **data, const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths,
		int flags, char *error, size_t max_error_len);
int	substitute_key_macros(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, int macro_type, char *error, size_t maxerrlen);
int	substitute_key_macros_unmasked(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths, int macro_type,
		char *error, size_t maxerrlen);
int	substitute_function_lld_param(const char *e, size_t len, unsigned char key_in_param,
		char **exp, size_t *exp_alloc, size_t *exp_offset, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, size_t max_error_len);
int	substitute_macros_xml(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	substitute_macros_xml_unmasked(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);
void	zbx_substitute_item_name_macros(DC_ITEM *dc_item, const char *name, char **replace_to);
int	substitute_macros_in_json_pairs(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	xml_xpath_check(const char *xpath, char *error, size_t errlen);

/*
 * Token type flags (32 bits):
 * | 6 bits       | 4 bits              | 22 bits    |
 * | token class  | operator precedence | token type |
 */
#define ZBX_EVAL_CLASS_OPERAND		(__UINT64_C(0x01) << 26)
#define ZBX_EVAL_CLASS_OPERATOR1	(__UINT64_C(0x02) << 26)
#define ZBX_EVAL_CLASS_OPERATOR2	(__UINT64_C(0x04) << 26)
#define ZBX_EVAL_CLASS_FUNCTION		(__UINT64_C(0x08) << 26)
#define ZBX_EVAL_CLASS_SEPARATOR	(__UINT64_C(0x10) << 26)
#define ZBX_EVAL_CLASS_OPERATOR		(ZBX_EVAL_CLASS_OPERATOR1 | ZBX_EVAL_CLASS_OPERATOR2)

#define ZBX_EVAL_BEFORE_OPERAND		(ZBX_EVAL_CLASS_OPERATOR | ZBX_EVAL_CLASS_SEPARATOR)
#define ZBX_EVAL_BEFORE_OPERATOR	(ZBX_EVAL_CLASS_OPERAND)

#define ZBX_EVAL_OP_SET_PRECEDENCE(x)	((x) << 22)
#define ZBX_EVAL_OP_PRIORITY		ZBX_EVAL_OP_SET_PRECEDENCE(0xf)

#define ZBX_EVAL_TOKEN_OP_ADD		(1 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(4))
#define ZBX_EVAL_TOKEN_OP_SUB		(2 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(4))
#define ZBX_EVAL_TOKEN_OP_MUL		(3 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(3))
#define ZBX_EVAL_TOKEN_OP_DIV		(4 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(3))
#define ZBX_EVAL_TOKEN_OP_MINUS		(5 | ZBX_EVAL_CLASS_OPERATOR1 | ZBX_EVAL_OP_SET_PRECEDENCE(2))
#define ZBX_EVAL_TOKEN_OP_EQ		(6 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(7))
#define ZBX_EVAL_TOKEN_OP_LT		(7 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(6))
#define ZBX_EVAL_TOKEN_OP_GT		(8 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(6))
#define ZBX_EVAL_TOKEN_OP_LE		(9 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(6))
#define ZBX_EVAL_TOKEN_OP_GE		(10 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(6))
#define ZBX_EVAL_TOKEN_OP_NE		(11 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(7))
#define ZBX_EVAL_TOKEN_OP_AND		(12 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(11))
#define ZBX_EVAL_TOKEN_OP_OR		(13 | ZBX_EVAL_CLASS_OPERATOR2 | ZBX_EVAL_OP_SET_PRECEDENCE(12))
#define ZBX_EVAL_TOKEN_OP_NOT		(14 | ZBX_EVAL_CLASS_OPERATOR1 | ZBX_EVAL_OP_SET_PRECEDENCE(2))
#define ZBX_EVAL_TOKEN_VAR_NUM		(15 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_VAR_STR		(16 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_VAR_TIME		(17 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_VAR_MACRO	(18 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_VAR_USERMACRO	(19 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_VAR_LLDMACRO	(20 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_FUNCTIONID	(21 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_FUNCTION		(22 | ZBX_EVAL_CLASS_FUNCTION)
#define ZBX_EVAL_TOKEN_HIST_FUNCTION	(23 | ZBX_EVAL_CLASS_FUNCTION)
#define ZBX_EVAL_TOKEN_GROUP_OPEN	(24 | ZBX_EVAL_CLASS_SEPARATOR)
#define ZBX_EVAL_TOKEN_GROUP_CLOSE	(25 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_COMMA		(26 | ZBX_EVAL_CLASS_SEPARATOR)
#define ZBX_EVAL_TOKEN_ARG_QUERY	(27 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_ARG_TIME		(28 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_ARG_NULL		(29 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_ARG_RAW		(30 | ZBX_EVAL_CLASS_OPERAND)

/* expression parsing rules */

#define ZBX_EVAL_PARSE_MACRO		__UINT64_C(0x0001)
#define ZBX_EVAL_PARSE_USERMACRO	__UINT64_C(0x0002)
#define ZBX_EVAL_PARSE_LLDMACRO		__UINT64_C(0x0004)
#define ZBX_EVAL_PARSE_FUNCTIONID	__UINT64_C(0x0008)
#define ZBX_EVAL_PARSE_ITEM_QUERY	__UINT64_C(0x0010)
#define ZBX_EVAL_PARSE_FUNCTION		__UINT64_C(0x0020)
#define ZBX_EVAL_PARSE_CONST_INDEX	__UINT64_C(0x0040)

#define	ZBX_EVAL_PARSE_TRIGGER_EXPRESSSION	(ZBX_EVAL_PARSE_MACRO | ZBX_EVAL_PARSE_USERMACRO |	\
						ZBX_EVAL_PARSE_FUNCTIONID | ZBX_EVAL_PARSE_FUNCTION)

#define	ZBX_EVAL_PARSE_CALC_EXPRESSSION		(ZBX_EVAL_PARSE_MACRO | ZBX_EVAL_PARSE_USERMACRO |	\
						ZBX_EVAL_PARSE_ITEM_QUERY | ZBX_EVAL_PARSE_FUNCTION)

/* expression composition rules */

#define ZBX_EVAL_QUOTE_MACRO			__UINT64_C(0x00010000)
#define ZBX_EVAL_QUOTE_USERMACRO		__UINT64_C(0x00020000)
#define ZBX_EVAL_QUOTE_LLDMACRO			__UINT64_C(0x00040000)

#define ZBX_EVAL_COMPOSE_TRIGGER_EXPRESSION	(ZBX_EVAL_QUOTE_MACRO | ZBX_EVAL_QUOTE_USERMACRO)
#define ZBX_EVAL_COMPOSE_LLD_EXPRESSION		ZBX_EVAL_QUOTE_LLDMACRO

/* expression evaluation rules */

#define ZBX_EVAL_PROCESS_ERROR		__UINT64_C(0x000100000000)

/* composite rules */

#define ZBX_EVAL_TRIGGER_EXPRESSION	(ZBX_EVAL_PARSE_TRIGGER_EXPRESSSION | \
					ZBX_EVAL_COMPOSE_TRIGGER_EXPRESSION | \
					ZBX_EVAL_PROCESS_ERROR)

typedef zbx_uint32_t zbx_token_type_t;

/******************************************************************************
 *                                                                            *
 * Typedef: zbx_eval_function_cb_t                                            *
 *                                                                            *
 * Purpose: define callback function to calculate custom functions            *
 *                                                                            *
 * Parameters: name     - [IN] the function name (not zero terminated)        *
 *             len      - [IN] the function name length                       *
 *             args_num - [IN] the number of function arguments               *
 *             args     - [IN] an array of the function arguments.            *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
typedef	int (*zbx_eval_function_cb_t)(const char *name, size_t len, int args_num, const zbx_variant_t *args,
		zbx_variant_t *value, char **error);

typedef struct
{
	zbx_token_type_t	type;
	zbx_uint32_t		opt;
	zbx_strloc_t		loc;
	zbx_variant_t		value;
}
zbx_eval_token_t;

ZBX_VECTOR_DECL(eval_token, zbx_eval_token_t)

typedef struct
{
	const char		*expression;
	zbx_token_type_t	last_token_type;
	int			const_index;
	int			functionid_index;
	zbx_uint64_t		rules;
	zbx_timespec_t		ts;
	zbx_vector_eval_token_t	stack;
	zbx_vector_eval_token_t	ops;
	zbx_eval_function_cb_t	function_cb;
}
zbx_eval_context_t;

int	zbx_eval_parse_expression(zbx_eval_context_t *ctx, const char *expression, zbx_uint64_t rules, char **error);
void	zbx_eval_clean(zbx_eval_context_t *ctx);
void	zbx_eval_serialize(const zbx_eval_context_t *ctx, zbx_mem_malloc_func_t malloc_func, unsigned char **data);
void	zbx_eval_deserialize(zbx_eval_context_t *ctx, const char *expression, zbx_uint64_t rules,
		const unsigned char *data);
void	zbx_eval_compose_expression(const zbx_eval_context_t *ctx, char **expression);
int	zbx_eval_execute(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, zbx_variant_t *value, char **error);
int	zbx_eval_execute_ext(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, zbx_eval_function_cb_t function_cb,
		zbx_variant_t *value, char **error);

#endif
