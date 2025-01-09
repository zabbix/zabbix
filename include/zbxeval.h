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

#ifndef ZABBIX_ZBXEVAL_H
#define ZABBIX_ZBXEVAL_H

#include "zbxtime.h"
#include "zbxvariant.h"
#include "zbxexpr.h"
#include "zbxregexp.h"

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
#define ZBX_EVAL_CLASS_PROPERTY		(__UINT64_C(0x20) << 26)
#define ZBX_EVAL_CLASS_OPERATOR		(ZBX_EVAL_CLASS_OPERATOR1 | ZBX_EVAL_CLASS_OPERATOR2)

#define ZBX_EVAL_BEFORE_OPERAND		(ZBX_EVAL_CLASS_OPERATOR | ZBX_EVAL_CLASS_SEPARATOR)
#define ZBX_EVAL_BEFORE_OPERATOR	(ZBX_EVAL_CLASS_OPERAND | ZBX_EVAL_CLASS_PROPERTY)

#define ZBX_EVAL_OP_SET_PRECEDENCE(x)	((x) << 22)
#define ZBX_EVAL_OP_PRIORITY		ZBX_EVAL_OP_SET_PRECEDENCE(0xf)

#define ZBX_EVAL_TOKEN_NOP		(0)
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
#define ZBX_EVAL_TOKEN_VAR_MACRO	(17 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_VAR_USERMACRO	(18 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_VAR_LLDMACRO	(19 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_FUNCTIONID	(20 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_FUNCTION		(21 | ZBX_EVAL_CLASS_FUNCTION)
#define ZBX_EVAL_TOKEN_HIST_FUNCTION	(22 | ZBX_EVAL_CLASS_FUNCTION)
#define ZBX_EVAL_TOKEN_GROUP_OPEN	(23 | ZBX_EVAL_CLASS_SEPARATOR)
#define ZBX_EVAL_TOKEN_GROUP_CLOSE	(24 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_COMMA		(25 | ZBX_EVAL_CLASS_SEPARATOR)
#define ZBX_EVAL_TOKEN_ARG_QUERY	(26 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_ARG_PERIOD	(27 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_ARG_NULL		(28 | ZBX_EVAL_CLASS_OPERAND)
#define ZBX_EVAL_TOKEN_PROP_TAG		(29 | ZBX_EVAL_CLASS_PROPERTY)
#define ZBX_EVAL_TOKEN_PROP_GROUP	(30 | ZBX_EVAL_CLASS_PROPERTY)
#define ZBX_EVAL_TOKEN_EXCEPTION	(31 | ZBX_EVAL_CLASS_FUNCTION)

/* token parsing rules */

#define ZBX_EVAL_PARSE_MACRO		__UINT64_C(0x00000001)
#define ZBX_EVAL_PARSE_USERMACRO	__UINT64_C(0x00000002)
#define ZBX_EVAL_PARSE_LLDMACRO		__UINT64_C(0x00000004)
#define ZBX_EVAL_PARSE_FUNCTIONID	__UINT64_C(0x00000008)
#define ZBX_EVAL_PARSE_ITEM_QUERY	__UINT64_C(0x00000010)
#define ZBX_EVAL_PARSE_CONST_INDEX	__UINT64_C(0x00000020)
#define ZBX_EVAL_PARSE_COMPOUND_CONST	__UINT64_C(0x00000040)
#define ZBX_EVAL_PARSE_MATH		__UINT64_C(0x00000080)	/* +, -, *, / */
#define ZBX_EVAL_PARSE_COMPARE_EQ	__UINT64_C(0x00000100)	/* =, <> */
#define ZBX_EVAL_PARSE_COMPARE_SORT	__UINT64_C(0x00000200)	/* <, <=, >, >= */
#define ZBX_EVAL_PARSE_LOGIC		__UINT64_C(0x00000400)	/* or, and, not */
#define ZBX_EVAL_PARSE_VAR_NUM		__UINT64_C(0x00000800)	/* number */
#define ZBX_EVAL_PARSE_VAR_STR		__UINT64_C(0x00001000)	/* string */
#define ZBX_EVAL_PARSE_GROUP		__UINT64_C(0x00002000)	/* (, ) */
#define ZBX_EVAL_PARSE_FUNCTION_ARGS	__UINT64_C(0x00004000)
#define ZBX_EVAL_PARSE_FUNCTION_NAME	__UINT64_C(0x00008000)
#define ZBX_EVAL_PARSE_PROP_TAG		__UINT64_C(0x00010000)	/* 'tag' keyword in item query filter */
#define ZBX_EVAL_PARSE_PROP_GROUP	__UINT64_C(0x00020000)	/* 'group' keyword in item query filter */
#define ZBX_EVAL_PARSE_STR_V64_COMPAT	__UINT64_C(0x00040000)	/* no backslash escaping for history functions */

#define ZBX_EVAL_PARSE_FUNCTION		(ZBX_EVAL_PARSE_FUNCTION_NAME | ZBX_EVAL_PARSE_FUNCTION_ARGS	|\
					ZBX_EVAL_PARSE_GROUP)
#define ZBX_EVAL_PARSE_COMPARE		(ZBX_EVAL_PARSE_COMPARE_EQ | ZBX_EVAL_PARSE_COMPARE_SORT)
#define ZBX_EVAL_PARSE_VAR		(ZBX_EVAL_PARSE_VAR_NUM | ZBX_EVAL_PARSE_VAR_STR)
#define ZBX_EVAL_PARSE_PROPERTY		(ZBX_EVAL_PARSE_PROP_TAG | ZBX_EVAL_PARSE_PROP_GROUP)

/* expression parsing rules */

#define	ZBX_EVAL_PARSE_TRIGGER_EXPRESSION	(ZBX_EVAL_PARSE_MACRO | ZBX_EVAL_PARSE_USERMACRO 	|\
						ZBX_EVAL_PARSE_FUNCTIONID | ZBX_EVAL_PARSE_FUNCTION 	|\
						ZBX_EVAL_PARSE_MATH | ZBX_EVAL_PARSE_COMPARE 		|\
						ZBX_EVAL_PARSE_LOGIC | ZBX_EVAL_PARSE_VAR)

#define	ZBX_EVAL_PARSE_CALC_EXPRESSION		(ZBX_EVAL_PARSE_MACRO | ZBX_EVAL_PARSE_USERMACRO 	|\
						ZBX_EVAL_PARSE_ITEM_QUERY | ZBX_EVAL_PARSE_FUNCTION	|\
						ZBX_EVAL_PARSE_MATH | ZBX_EVAL_PARSE_COMPARE 		|\
						ZBX_EVAL_PARSE_LOGIC | ZBX_EVAL_PARSE_VAR 		|\
						ZBX_EVAL_PARSE_COMPOUND_CONST)

#define ZBX_EVAL_PARSE_QUERY_EXPRESSION		(ZBX_EVAL_PARSE_USERMACRO | ZBX_EVAL_PARSE_COMPARE_EQ		|\
						ZBX_EVAL_PARSE_LOGIC | ZBX_EVAL_PARSE_GROUP 			|\
						ZBX_EVAL_PARSE_VAR_STR | ZBX_EVAL_PARSE_PROPERTY)

#define	ZBX_EVAL_PARSE_EXPRESSION_MACRO		(ZBX_EVAL_PARSE_MACRO | ZBX_EVAL_PARSE_USERMACRO 	|\
						ZBX_EVAL_PARSE_ITEM_QUERY | ZBX_EVAL_PARSE_FUNCTION	|\
						ZBX_EVAL_PARSE_MATH | ZBX_EVAL_PARSE_COMPARE 		|\
						ZBX_EVAL_PARSE_LOGIC | ZBX_EVAL_PARSE_VAR 		|\
						ZBX_EVAL_PARSE_COMPOUND_CONST)
/* expression composition rules */

#define ZBX_EVAL_COMPOSE_LLD			__UINT64_C(0x01000000)
#define ZBX_EVAL_COMPOSE_FUNCTIONID		__UINT64_C(0x02000000)
#define ZBX_EVAL_COMPOSE_QUOTE			__UINT64_C(0x04000000)
#define ZBX_EVAL_COMPOSE_MASK_ERROR		__UINT64_C(0x08000000)

/* expression evaluation rules */

#define ZBX_EVAL_PROCESS_ERROR		__UINT64_C(0x000100000000)

/* composite rules */

#define ZBX_EVAL_TRIGGER_EXPRESSION	(ZBX_EVAL_PARSE_TRIGGER_EXPRESSION | \
					ZBX_EVAL_PARSE_CONST_INDEX | \
					ZBX_EVAL_PROCESS_ERROR)

#define ZBX_EVAL_TRIGGER_EXPRESSION_LLD	(ZBX_EVAL_PARSE_TRIGGER_EXPRESSION | \
					ZBX_EVAL_PARSE_LLDMACRO | \
					ZBX_EVAL_COMPOSE_LLD | \
					ZBX_EVAL_COMPOSE_FUNCTIONID)

#define ZBX_EVAL_CALC_EXPRESSION_LLD	(ZBX_EVAL_PARSE_CALC_EXPRESSION | \
					ZBX_EVAL_PARSE_LLDMACRO | \
					ZBX_EVAL_COMPOSE_LLD)

#define ZBX_EVAL_EXPRESSION_MACRO_LLD	(ZBX_EVAL_PARSE_EXPRESSION_MACRO | \
					ZBX_EVAL_PARSE_LLDMACRO | \
					ZBX_EVAL_COMPOSE_LLD)

#define ZBX_EVAL_QUERY_EXPRESSION_LLD	(ZBX_EVAL_PARSE_QUERY_EXPRESSION | \
					ZBX_EVAL_PARSE_LLDMACRO | \
					ZBX_EVAL_COMPOSE_LLD)

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
 *             data     - [IN] the caller data used for function evaluation   *
 *             ts       - [IN] the function execution time                    *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
typedef	int (*zbx_eval_function_cb_t)(const char *name, size_t len, int args_num, zbx_variant_t *args, void *data,
		const zbx_timespec_t *ts, zbx_variant_t *value, char **error);

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
	zbx_uint32_t		const_index;
	zbx_uint32_t		functionid_index;
	zbx_uint64_t		rules;
	zbx_timespec_t		ts;
	zbx_vector_eval_token_t	stack;
	zbx_vector_eval_token_t	ops;
	zbx_eval_function_cb_t	common_func_cb;
	zbx_eval_function_cb_t	history_func_cb;
	void			*data_cb;
}
zbx_eval_context_t;

typedef int	(*zbx_macro_expand_func_t)(void *data, char **str, const zbx_uint64_t *hostids, int hostids_num, \
		char **error);
typedef void	(*zbx_get_expressions_by_name_f)(zbx_vector_expression_t *expressions, const char *name);
typedef int (*zbx_eval_subst_macros_func_t)(zbx_token_type_t token_type, char **value, char **error, va_list args);

void	zbx_init_library_eval(zbx_get_expressions_by_name_f get_expressions_by_name_func);

int	zbx_eval_parse_expression(zbx_eval_context_t *ctx, const char *expression, zbx_uint64_t rules, char **error);
void	zbx_eval_init(zbx_eval_context_t *ctx);
void	zbx_eval_clear(zbx_eval_context_t *ctx);
int	zbx_eval_status(const zbx_eval_context_t *ctx);
size_t	zbx_eval_serialize(const zbx_eval_context_t *ctx, zbx_mem_malloc_func_t malloc_func, unsigned char **data);
void	zbx_eval_deserialize(zbx_eval_context_t *ctx, const char *expression, zbx_uint64_t rules,
		const unsigned char *data);
void	zbx_eval_compose_expression(const zbx_eval_context_t *ctx, char **expression);
int	zbx_eval_execute(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, zbx_variant_t *value, char **error);
int	zbx_eval_execute_ext(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, zbx_eval_function_cb_t common_func_cb,
		zbx_eval_function_cb_t history_func_cb, void *data, zbx_variant_t *value, char **error);
void	zbx_eval_get_functionids(zbx_eval_context_t *ctx, zbx_vector_uint64_t *functionids);
void	zbx_eval_get_functionids_ordered(zbx_eval_context_t *ctx, zbx_vector_uint64_t *functionids);
int	zbx_eval_substitute_macros(const zbx_eval_context_t *ctx, char **error, zbx_eval_subst_macros_func_t resolver,
		...);
int	zbx_eval_query_subtitute_user_macros(const char *itemquery, size_t len, char **out, char **error,
		zbx_eval_subst_macros_func_t resolver, ...);

void	zbx_eval_set_exception(zbx_eval_context_t *ctx, char *message);

void	zbx_eval_compose_expression_from_pos(const zbx_eval_context_t *ctx, char **expression, size_t pos);

#define ZBX_EVAL_EXTRACT_FUNCTIONID	0x0001
#define ZBX_EVAL_EXTRACT_VAR_STR	0x0002
#define ZBX_EVAL_EXTRACT_VAR_MACRO	0x0004
#define ZBX_EVAL_EXTRACT_VAR_USERMACRO	0x0008

#define ZBX_EVAL_EXTRACT_ALL	(ZBX_EVAL_EXTRACT_FUNCTIONID | ZBX_EVAL_EXTRACT_VAR_STR | ZBX_EVAL_EXTRACT_VAR_MACRO | \
		ZBX_EVAL_EXTRACT_VAR_USERMACRO)

zbx_eval_context_t *zbx_eval_deserialize_dyn(const unsigned char *data, const char *expression,
		zbx_uint64_t mask);
int	zbx_eval_check_timer_functions(const zbx_eval_context_t *ctx);
void	zbx_get_serialized_expression_functionids(const char *expression, const unsigned char *data,
		zbx_vector_uint64_t *functionids);
void	zbx_eval_get_constant(const zbx_eval_context_t *ctx, int index, char **value);
void	zbx_eval_replace_functionid(zbx_eval_context_t *ctx, zbx_uint64_t old_functionid, zbx_uint64_t new_functionid);
int	zbx_eval_validate_replaced_functionids(zbx_eval_context_t *ctx, char **error);
void	zbx_eval_copy(zbx_eval_context_t *dst, const zbx_eval_context_t *src, const char *expression);

char	*zbx_eval_format_function_error(const char *function, const char *host, const char *key,
		const char *parameter, const char *error);

void	zbx_eval_extract_item_refs(zbx_eval_context_t *ctx, zbx_vector_str_t *refs);
int	zbx_eval_compare_tokens_by_loc(const void *d1, const void *d2);

typedef struct
{
	char	*host;
	char	*key;
	char	*filter;
}
zbx_item_query_t;

size_t	zbx_eval_parse_query(const char *str, size_t len, zbx_item_query_t *query);
void	zbx_eval_clear_query(zbx_item_query_t *query);

void	zbx_eval_prepare_filter(zbx_eval_context_t *ctx);
int	zbx_eval_get_group_filter(zbx_eval_context_t *ctx, zbx_vector_str_t *groups, char **filter, char **error);

typedef int	(*zbx_statistical_func_t)(zbx_vector_dbl_t *values, double *result, char **error);

int	zbx_eval_calc_kurtosis(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_mad(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_skewness(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_stddevpop(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_stddevsamp(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_sumofsquares(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_varpop(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_varsamp(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_histogram_quantile(const double q, const zbx_vector_dbl_t *values, const char *err_fn,
		double *result, char **error);

int	zbx_eval_calc_avg(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_min(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_max(zbx_vector_dbl_t *values, double *result, char **error);
int	zbx_eval_calc_sum(zbx_vector_dbl_t *values, double *result, char **error);

int	zbx_eval_var_vector_to_dbl(zbx_vector_var_t *input_vector, zbx_vector_dbl_t *output_vector, char **error);

#define OP_UNKNOWN	-1
#define OP_EQ		0
#define OP_NE		1
#define OP_GT		2
#define OP_GE		3
#define OP_LT		4
#define OP_LE		5
#define OP_LIKE		6
#define OP_REGEXP	7
#define OP_IREGEXP	8
#define OP_BITAND	9
#define OP_ANY		10

typedef struct
{
	int			op;
	int			numeric_search;
	char			*pattern2;
	zbx_uint64_t		pattern_ui64;
	zbx_uint64_t		pattern2_ui64;
	double			pattern_dbl;
	zbx_vector_expression_t	regexps;
}
zbx_eval_count_pattern_data_t;

int	zbx_init_count_pattern(char *operator, char *pattern, unsigned char value_type,
		zbx_eval_count_pattern_data_t *pdata, char **error);
int	zbx_count_var_vector_with_pattern(zbx_eval_count_pattern_data_t *pdata, char *pattern, zbx_vector_var_t *values,
		int limit, int *count, char **error);
void	zbx_clear_count_pattern(zbx_eval_count_pattern_data_t *pdata);
#endif
