/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#ifndef ZABBIX_ZBXREGEXP_H
#define ZABBIX_ZBXREGEXP_H

#include "zbxalgo.h"

#define ZBX_REGEXP_NO_MATCH	0
#define ZBX_REGEXP_MATCH	1

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

/* regular expressions */
int	zbx_regexp_compile(const char *pattern, zbx_regexp_t **regexp, const char **err_msg);
int	zbx_regexp_compile_ext(const char *pattern, zbx_regexp_t **regexp, int flags, const char **err_msg);
void	zbx_regexp_free(zbx_regexp_t *regexp);
int	zbx_regexp_match_precompiled(const char *string, const zbx_regexp_t *regexp);
char	*zbx_regexp_match(const char *string, const char *pattern, int *len);
int	zbx_regexp_sub(const char *string, const char *pattern, const char *output_template, char **out);
int	zbx_mregexp_sub(const char *string, const char *pattern, const char *output_template, char **out);
int	zbx_iregexp_sub(const char *string, const char *pattern, const char *output_template, char **out);
int	zbx_mregexp_sub_precompiled(const char *string, const zbx_regexp_t *regexp, const char *output_template,
		size_t limit, char **out);

void	zbx_regexp_clean_expressions(zbx_vector_ptr_t *expressions);

void	add_regexp_ex(zbx_vector_ptr_t *regexps, const char *name, const char *expression, int expression_type,
		char exp_delimiter, int case_sensitive);
int	regexp_match_ex(const zbx_vector_ptr_t *regexps, const char *string, const char *pattern, int case_sensitive);
int	regexp_sub_ex(const zbx_vector_ptr_t *regexps, const char *string, const char *pattern, int case_sensitive,
		const char *output_template, char **output);
int	zbx_global_regexp_exists(const char *name, const zbx_vector_ptr_t *regexps);
void	zbx_regexp_escape(char **string);

/* wildcards */
void	zbx_wildcard_minimize(char *str);
int	zbx_wildcard_match(const char *value, const char *wildcard);

void	zbx_regexp_err_msg_free(const char *err_msg);

#endif /* ZABBIX_ZBXREGEXP_H */
