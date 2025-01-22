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
#ifndef ZABBIX_ZBXREGEXP_H
#define ZABBIX_ZBXREGEXP_H

#include "zbxalgo.h"

#define ZBX_REGEXP_NO_MATCH	0
#define ZBX_REGEXP_MATCH	1
#define ZBX_REGEXP_COMPILE_FAIL	-1
#define ZBX_REGEXP_RUNTIME_FAIL	-2	/* a regexp compiled successfully but an error occurred during matching */

#define ZBX_IGNORE_CASE		0
#define ZBX_CASE_SENSITIVE	1

typedef enum {
	ZBX_REGEXP_GROUP_CHECK_DISABLE,
	ZBX_REGEXP_GROUP_CHECK_ENABLE
}
zbx_regexp_group_check_t;

typedef struct zbx_regexp zbx_regexp_t;

typedef struct
{
	char		*name;
	char		*expression;
	int		expression_type;
	char		exp_delimiter;
	unsigned char	case_sensitive;
}
zbx_expression_t;

ZBX_PTR_VECTOR_DECL(expression, zbx_expression_t *)

/* regular expressions */
int	zbx_regexp_compile(const char *pattern, zbx_regexp_t **regexp, char **err_msg);
int	zbx_regexp_compile_ext(const char *pattern, zbx_regexp_t **regexp, int flags, char **err_msg);
void	zbx_regexp_free(zbx_regexp_t *regexp);
int	zbx_regexp_match_precompiled(const char *string, const zbx_regexp_t *regexp);
int	zbx_regexp_match_precompiled2(const char *string, const zbx_regexp_t *regexp, char **err_msg);
char	*zbx_regexp_match(const char *string, const char *pattern, int *len);
int	zbx_regexp_sub(const char *string, const char *pattern, const char *output_template, char **out);
int	zbx_mregexp_sub(const char *string, const char *pattern, const char *output_template,
		zbx_regexp_group_check_t group_check, char **out);
int	zbx_iregexp_sub(const char *string, const char *pattern, const char *output_template, char **out);
int	zbx_mregexp_sub_precompiled(const char *string, const zbx_regexp_t *regexp, const char *output_template,
		size_t limit, char **out);
int	zbx_regexp_repl(const char *string, const char *pattern, const char *repl_template, char **out);
void	zbx_regexp_clean_expressions(zbx_vector_expression_t *expressions);

void	zbx_add_regexp_ex(zbx_vector_expression_t *regexps, const char *name, const char *expression,
		int expression_type, char exp_delimiter, int case_sensitive);
int	zbx_regexp_match_ex(const zbx_vector_expression_t *regexps, const char *string, const char *pattern,
		int case_sensitive);
int	zbx_regexp_sub_ex(const zbx_vector_expression_t *regexps, const char *string, const char *pattern,
		int case_sensitive, const char *output_template, char **output);
int	zbx_regexp_sub_ex2(const zbx_vector_expression_t *regexps, const char *string, const char *pattern,
		int case_sensitive, const char *output_template, char **output, char **err_msg);
int	zbx_global_regexp_exists(const char *name, const zbx_vector_expression_t *regexps);
void	zbx_regexp_escape(char **string);

/* wildcards */
void	zbx_wildcard_minimize(char *str);
int	zbx_wildcard_match(const char *value, const char *wildcard);

void	zbx_init_regexp_env(void);

#endif /* ZABBIX_ZBXREGEXP_H */
