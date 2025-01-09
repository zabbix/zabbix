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

#ifndef ZABBIX_EXPR_H
#define ZABBIX_EXPR_H

#include "zbxcommon.h"

int	zbx_is_hostname_char(unsigned char c);
int	zbx_is_key_char(unsigned char c);
int	zbx_is_function_char(unsigned char c);
int	zbx_is_macro_char(unsigned char c);
int	zbx_is_discovery_macro(const char *name);
int	zbx_is_strict_macro(const char *macro);
int	zbx_parse_key(const char **exp);
int	zbx_parse_host_key(char *exp, char **host, char **key);
void	zbx_make_hostname(char *host);
int	zbx_check_hostname(const char *hostname, char **error);

int	zbx_function_validate(const char *expr, size_t *par_l, size_t *par_r, char *error, int max_error_len);
int	zbx_function_validate_parameters(const char *expr, size_t *length);
int	zbx_user_macro_parse(const char *macro, int *macro_r, int *context_l, int *context_r,
		unsigned char *context_op);
int	zbx_user_macro_parse_dyn(const char *macro, char **name, char **context, int *length,
		unsigned char *context_op);
char	*zbx_user_macro_unquote_context_dyn(const char *context, int len);
char	*zbx_user_macro_quote_context_dyn(const char *context, int force_quote, char **error);
int	zbx_function_find(const char *expr, size_t *func_pos, size_t *par_l, size_t *par_r, char *error,
		int max_error_len);
char	*zbx_function_param_unquote_dyn_ext(const char *param, size_t len, int *quoted, int esc_bs);
char	*zbx_function_param_unquote_dyn(const char *param, size_t len, int *quoted);
char	*zbx_function_param_unquote_dyn_compat(const char *param, size_t len, int *quoted);
int	zbx_function_param_quote(char **param, int forced, int esc_bs);
char	*zbx_function_get_param_dyn(const char *params, int Nparam);

#define ZBX_BACKSLASH_ESC_OFF		0
#define ZBX_BACKSLASH_ESC_ON		1

void	zbx_function_param_parse_ext(const char *expr, zbx_uint32_t allowed_macros, int esc_bs, size_t *param_pos,
		size_t *length, size_t *sep_pos);
void	zbx_function_param_parse(const char *expr, size_t *param_pos, size_t *length, size_t *sep_pos);
void	zbx_trigger_function_param_parse(const char *expr, size_t *param_pos, size_t *length, size_t *sep_pos);
void	zbx_lld_function_param_parse(const char *expr, int esc_flags, size_t *param_pos, size_t *length,
		size_t *sep_pos);
int	zbx_function_param_parse_count(const char *expr);

typedef enum
{
	ZBX_FUNCTION_TYPE_UNKNOWN,
	ZBX_FUNCTION_TYPE_HISTORY,
	ZBX_FUNCTION_TYPE_TIMER,
	ZBX_FUNCTION_TYPE_TRENDS
}
zbx_function_type_t;

zbx_function_type_t	zbx_get_function_type(const char *func);

int	zbx_is_double_suffix(const char *str, unsigned char flags);
double	zbx_str2double(const char *str);
int	zbx_suffixed_number_parse(const char *number, int *len);
int	zbx_strmatch_condition(const char *value, const char *pattern, unsigned char op);
int	zbx_uint64match_condition(zbx_uint64_t value, zbx_uint64_t pattern, unsigned char op);

/* token START */
/* tokens used in expressions */
#define ZBX_TOKEN_UNKNOWN		0x00000
#define ZBX_TOKEN_OBJECTID		0x00001
#define ZBX_TOKEN_MACRO			0x00002
#define ZBX_TOKEN_LLD_MACRO		0x00004
#define ZBX_TOKEN_USER_MACRO		0x00008
#define ZBX_TOKEN_FUNC_MACRO		0x00010
#define ZBX_TOKEN_SIMPLE_MACRO		0x00020
#define ZBX_TOKEN_REFERENCE		0x00040
#define ZBX_TOKEN_LLD_FUNC_MACRO	0x00080
#define ZBX_TOKEN_EXPRESSION_MACRO	0x00100
#define ZBX_TOKEN_USER_FUNC_MACRO	0x00200
#define ZBX_TOKEN_VAR_MACRO		0x00400
#define ZBX_TOKEN_VAR_FUNC_MACRO	0x00800

/* additional token flags */
#define ZBX_TOKEN_JSON		0x0010000
#define ZBX_TOKEN_REGEXP	0x0040000
#define ZBX_TOKEN_XPATH		0x0080000
#define ZBX_TOKEN_REGEXP_OUTPUT	0x0100000
#define ZBX_TOKEN_PROMETHEUS	0x0200000
#define ZBX_TOKEN_JSONPATH	0x0400000
#define ZBX_TOKEN_STR_REPLACE	0x0800000
#define ZBX_TOKEN_STRING	0x1000000

/* location of a substring */
typedef struct
{
	/* left position */
	size_t	l;
	/* right position */
	size_t	r;
}
zbx_strloc_t;

/* data used by macros, lld macros and objectid tokens */
typedef struct
{
	zbx_strloc_t	name;
}
zbx_token_macro_t;

/* data used by macros, lld macros and objectid tokens */
typedef struct
{
	zbx_strloc_t	expression;
}
zbx_token_expression_macro_t;

/* data used by user macros */
typedef struct
{
	/* macro name */
	zbx_strloc_t	name;
	/* macro context, for macros without context the context.l and context.r fields are set to 0 */
	zbx_strloc_t	context;
}
zbx_token_user_macro_t;

/* data used by macro functions */
typedef struct
{
	/* the macro including the opening and closing brackets {}, for example: {ITEM.VALUE} */
	zbx_strloc_t	macro;
	/* function + parameters, for example: regsub("([0-9]+)", \1) */
	zbx_strloc_t	func;
	/* parameters, for example: ("([0-9]+)", \1) */
	zbx_strloc_t	func_param;
}
zbx_token_func_macro_t;

/* data used by simple (host:key) macros */
typedef struct
{
	/* host name, supporting simple macros as a host name, for example Zabbix server or {HOST.HOST} */
	zbx_strloc_t	host;
	/* key + parameters, supporting {ITEM.KEYn} macro, for example system.uname or {ITEM.KEY1}  */
	zbx_strloc_t	key;
	/* function + parameters, for example avg(5m) */
	zbx_strloc_t	func;
	/* parameters, for example (5m) */
	zbx_strloc_t	func_param;
}
zbx_token_simple_macro_t;

/* data used by references */
typedef struct
{
	/* index of constant being referenced (1 for $1, 2 for $2, ..., 9 for $9) */
	int	index;
}
zbx_token_reference_t;

/* the token type specific data */
typedef union
{
	zbx_token_macro_t		objectid;
	zbx_token_macro_t		macro;
	zbx_token_macro_t		lld_macro;
	zbx_token_expression_macro_t	expression_macro;
	zbx_token_user_macro_t		user_macro;
	zbx_token_func_macro_t		func_macro;
	zbx_token_func_macro_t		lld_func_macro;
	zbx_token_simple_macro_t	simple_macro;
	zbx_token_reference_t		reference;
	zbx_token_macro_t		var_macro;
	zbx_token_func_macro_t		var_func_macro;
}
zbx_token_data_t;

/* {} token data */
typedef struct
{
	/* token type, see ZBX_TOKEN_ defines */
	int			type;
	/* the token location in expression including opening and closing brackets {} */
	zbx_strloc_t		loc;
	/* the token type specific data */
	zbx_token_data_t	data;
}
zbx_token_t;

#define ZBX_TOKEN_SEARCH_BASIC			0x00
#define ZBX_TOKEN_SEARCH_REFERENCES		0x01
#define ZBX_TOKEN_SEARCH_EXPRESSION_MACRO	0x02
#define ZBX_TOKEN_SEARCH_FUNCTIONID		0x04
#define ZBX_TOKEN_SEARCH_SIMPLE_MACRO		0x08	/* used by the upgrade patches only */
#define ZBX_TOKEN_SEARCH_VAR_MACRO		0x10	/* web scenario variable support */

typedef int zbx_token_search_t;

int	zbx_token_find(const char *expression, int pos, zbx_token_t *token, zbx_token_search_t token_search);

int	zbx_token_parse_user_macro(const char *expression, const char *macro, zbx_token_t *token);
int	zbx_token_parse_macro(const char *expression, const char *macro, zbx_token_t *token);
int	zbx_token_parse_objectid(const char *expression, const char *macro, zbx_token_t *token);
int	zbx_token_parse_lld_macro(const char *expression, const char *macro, zbx_token_t *token);
int	zbx_token_parse_nested_macro(const char *expression, const char *macro, zbx_token_search_t token_search,
		zbx_token_t *token);
/* token END */

/* report scheduling */

#define ZBX_REPORT_CYCLE_DAILY		0
#define ZBX_REPORT_CYCLE_WEEKLY		1
#define ZBX_REPORT_CYCLE_MONTHLY	2
#define ZBX_REPORT_CYCLE_YEARLY		3

int	zbx_get_agent_item_nextcheck(zbx_uint64_t itemid, const char *delay, int now,
		int *nextcheck, int *scheduling, char **error);

/* interval START */
typedef struct zbx_custom_interval	zbx_custom_interval_t;
int	zbx_interval_preproc(const char *interval_str, int *simple_interval, zbx_custom_interval_t **custom_intervals,
		char **error);
int	zbx_validate_interval(const char *str, char **error);
int	zbx_custom_interval_is_scheduling(const zbx_custom_interval_t *custom_intervals);
void	zbx_custom_interval_free(zbx_custom_interval_t *custom_intervals);
int	zbx_calculate_item_nextcheck(zbx_uint64_t seed, int item_type, int simple_interval,
		const zbx_custom_interval_t *custom_intervals, time_t now);
int	zbx_calculate_item_nextcheck_unreachable(int simple_interval, const zbx_custom_interval_t *custom_intervals,
		time_t disable_until);

int	zbx_check_time_period(const char *period, time_t time, const char *tz, int *res);
int	zbx_get_report_nextcheck(int now, unsigned char cycle, unsigned char weekdays, int start_time);
/* interval END */

/* condition operators */
#define ZBX_CONDITION_OPERATOR_EQUAL		0
#define ZBX_CONDITION_OPERATOR_NOT_EQUAL		1
#define ZBX_CONDITION_OPERATOR_LIKE			2
#define ZBX_CONDITION_OPERATOR_NOT_LIKE		3
#define ZBX_CONDITION_OPERATOR_IN			4
#define ZBX_CONDITION_OPERATOR_MORE_EQUAL		5
#define ZBX_CONDITION_OPERATOR_LESS_EQUAL		6
#define ZBX_CONDITION_OPERATOR_NOT_IN		7
#define ZBX_CONDITION_OPERATOR_REGEXP		8
#define ZBX_CONDITION_OPERATOR_NOT_REGEXP		9
#define ZBX_CONDITION_OPERATOR_YES			10
#define ZBX_CONDITION_OPERATOR_NO			11
#define ZBX_CONDITION_OPERATOR_EXIST		12
#define ZBX_CONDITION_OPERATOR_NOT_EXIST		13

int	zbx_strloc_cmp(const char *src, const zbx_strloc_t *loc, const char *text, size_t text_len);

typedef struct
{
	zbx_token_search_t	token_search;

	zbx_token_t	token;			/* current token type */
	zbx_token_t	inner_token;		/* inner token type */

	const char	*macro;			/* normalized macro (without function id, index, etc.) */
	int		pos;			/* macro position in input data string */

	int	raw_value;			/* flag that resolver should resolve to raw value */
	int	indexed;
	int	index;
	int	resolved;			/* flag that macro is fully resolved (special case) */
}
zbx_macro_resolv_data_t;

typedef int (*zbx_macro_resolv_func_t)(zbx_macro_resolv_data_t *p, va_list args, char **replace_to,
		char **data, char *error, size_t maxerrlen);

int		zbx_is_indexed_macro(const char *str, const zbx_token_t *token);
const char	*zbx_macro_in_list(const char *str, zbx_strloc_t strloc, const char **macros, int *N_functionid);
char		*zbx_get_macro_from_func(const char *str, zbx_token_func_macro_t *fm, int *N_functionid);
const char	**zbx_get_indexable_macros(void);

int	zbx_substitute_macros(char **data, char *error, size_t maxerrlen, zbx_macro_resolv_func_t resolver, ...);

/* macro function calculation */
int	zbx_calculate_macro_function(const char *expression, const zbx_token_func_macro_t *func_macro, char **out);

void	zbx_url_encode(const char *source, char **result);
int	zbx_url_decode(const char *source, char **result);

#endif /* ZABBIX_EXPR_H */
