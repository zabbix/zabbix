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

#include "dbupgrade_macros.h"
#include "dbupgrade.h"

#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxstr.h"

/* Function argument descriptors.                                                */
/* Used in varargs list to describe following parameter mapping to old position. */
/* Terminated with ZBX_DBPATCH_ARG_NONE.                                         */
/* For example:                                                                  */
/* ..., ZBX_DBPATCH_ARG_NUM, 1, ZBX_DBPATCH_ARG_STR, 0, ZBX_DBPATCH_ARG_NONE)    */
/*  meaning first numeric parameter copied from second parameter                 */
/*          second string parameter copied from first parameter                  */
typedef enum
{
	ZBX_DBPATCH_ARG_NONE,		/* terminating descriptor, must be put at the end of the list */
	ZBX_DBPATCH_ARG_HIST,		/* history period followed by sec/num (int) and timeshift (int) indexes */
	ZBX_DBPATCH_ARG_TIME,		/* time value followed by argument index (int)  */
	ZBX_DBPATCH_ARG_NUM,		/* number value followed by argument index (int)  */
	ZBX_DBPATCH_ARG_STR,		/* string value  followed by argument index (int)  */
	ZBX_DBPATCH_ARG_TREND,		/* trend period, followed by period (int) and timeshift (int) indexes */
	ZBX_DBPATCH_ARG_CONST_STR,	/* constant,fffffff followed by string (char *) value */
}
zbx_dbpatch_arg_t;

ZBX_VECTOR_IMPL(strloc, zbx_strloc_t)

/******************************************************************************
 *                                                                            *
 * Purpose: rename macros in the string                                       *
 *                                                                            *
 * Parameters: in        - [IN] the input string                              *
 *             oldmacro  - [IN] the macro to rename                           *
 *             newmacro  - [IN] the new macro name                            *
 *             out       - [IN/OUT] the string with renamed macros            *
 *             out_alloc - [IN/OUT] the output buffer size                    *
 *                                                                            *
 * Return value: SUCCEED - macros were found and renamed                      *
 *               FAIL    - no target macros were found                        *
 *                                                                            *
 * Comments: If the oldmacro is found in input string then all occurrences of *
 *           it are replaced with the new macro in the output string.         *
 *           Otherwise the output string is not changed.                      *
 *                                                                            *
 ******************************************************************************/
static int	str_rename_macro(const char *in, const char *oldmacro, const char *newmacro, char **out,
		size_t *out_alloc)
{
	zbx_token_t	token;
	int		pos = 0, ret = FAIL;
	size_t		out_offset = 0, newmacro_len;

	newmacro_len = strlen(newmacro);
	zbx_strcpy_alloc(out, out_alloc, &out_offset, in);
	out_offset++;

	for (; SUCCEED == zbx_token_find(*out, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		switch (token.type)
		{
			case ZBX_TOKEN_MACRO:
				pos = token.loc.r;
				if (0 == strncmp(*out + token.loc.l, oldmacro, token.loc.r - token.loc.l + 1))
				{
					pos += zbx_replace_mem_dyn(out, out_alloc, &out_offset, token.loc.l,
							token.loc.r - token.loc.l + 1, newmacro, newmacro_len);
					ret = SUCCEED;
				}
				break;

			case ZBX_TOKEN_USER_MACRO:
			case ZBX_TOKEN_SIMPLE_MACRO:
				pos = token.loc.r;
				break;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: rename macro in the specified database fields                     *
 *                                                                            *
 * Parameters: result     - [IN] database query with fields to replace. First *
 *                               field is table id field, following with      *
 *                               the target fields listed in fields parameter *
 *             table      - [IN] the target table name                        *
 *             pkey       - [IN] the primary key field name                   *
 *             fields     - [IN] the table fields to check for macros and     *
 *                               rename if found                              *
 *             fields_num - [IN] the number of fields to check                *
 *             oldmacro   - [IN] the macro to rename                          *
 *             newmacro   - [IN] the new macro name                           *
 *                                                                            *
 * Return value: SUCCEED  - macros were renamed successfully                  *
 *               FAIL     - database error occurred                           *
 *                                                                            *
 ******************************************************************************/
int	db_rename_macro(zbx_db_result_t result, const char *table, const char *pkey, zbx_field_len_t *fields,
		int fields_num, const char *oldmacro, const char *newmacro)
{
	zbx_db_row_t	row;
	char		*sql = 0, *value = NULL, *value_esc;
	size_t		sql_alloc = 4096, sql_offset = 0, field_alloc = 0, old_offset;
	int		i, ret = SUCCEED;
	zbx_field_len_t	*field;

	sql = zbx_malloc(NULL, sql_alloc);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		old_offset = sql_offset;

		for (i = 0; i < fields_num; i++)
		{
			field = fields + i;

			if (SUCCEED == str_rename_macro(row[i + 1], oldmacro, newmacro, &value, &field_alloc))
			{
				if (0 != field->max_len && zbx_strlen_utf8(value) > field->max_len)
				{
					zabbix_log(LOG_LEVEL_WARNING, "cannot rename macros in table \"%s\" row "
							"\"%s:%s\" field \"%s\": value is too long",
							table, pkey, row[0], field->field_name);
					continue;
				}

				value_esc = zbx_db_dyn_escape_string(value);

				if (old_offset == sql_offset)
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ", table);
				else
					zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ',');

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s='%s'", fields[i].field_name,
						value_esc);

				zbx_free(value_esc);
			}
		}

		if (old_offset != sql_offset)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where %s=%s;\n", pkey, row[0]);
			if (SUCCEED != (ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
				goto out;
		}
	}

	if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
		ret = FAIL;
out:
	zbx_free(value);
	zbx_free(sql);

	return ret;
}

void	dbpatch_function_free(zbx_dbpatch_function_t *func)
{
	zbx_free(func->name);
	zbx_free(func->parameter);
	zbx_free(func->arg0);
	zbx_free(func);
}

zbx_dbpatch_function_t	*dbpatch_new_function(zbx_uint64_t functionid, zbx_uint64_t itemid, const char *name,
		const char *parameter, unsigned char flags)
{
	zbx_dbpatch_function_t	*func;

	func = (zbx_dbpatch_function_t *)zbx_malloc(NULL, sizeof(zbx_dbpatch_function_t));
	func->functionid = functionid;
	func->itemid = itemid;
	func->name = (NULL != name ? zbx_strdup(NULL, name) : NULL);
	func->parameter = (NULL != parameter ? zbx_strdup(NULL, parameter) : NULL);
	func->flags = flags;
	func->arg0 = NULL;

	return func;
}

static void	dbpatch_add_function(const zbx_dbpatch_function_t *template, zbx_uint64_t functionid, const char *name,
		const char *parameter, unsigned char flags, zbx_vector_ptr_t *functions)
{
	zbx_dbpatch_function_t	*func;

	func = dbpatch_new_function(functionid, template->itemid, name, parameter, flags);
	func->arg0 = (NULL != template->arg0 ? zbx_strdup(NULL, template->arg0) : NULL);

	zbx_vector_ptr_append(functions, func);
}

static void	dbpatch_update_function(zbx_dbpatch_function_t *func, const char *name,
		const char *parameter, unsigned char flags)
{
	if (0 != (flags & ZBX_DBPATCH_FUNCTION_UPDATE_NAME))
		func->name = zbx_strdup(func->name, name);

	if (0 != (flags & ZBX_DBPATCH_FUNCTION_UPDATE_PARAM))
		func->parameter = zbx_strdup(func->parameter, parameter);

	func->flags = flags;
}

int	dbpatch_is_time_function(const char *name, size_t len)
{
	const char	*functions[] = {"date", "dayofmonth", "dayofweek", "now", "time", NULL}, **func;
	size_t		func_len;

	for (func = functions; NULL != *func; func++)
	{
		func_len = strlen(*func);
		if (func_len == len && 0 == memcmp(*func, name, len))
			return SUCCEED;
	}

	return FAIL;
}

static void	dbpatch_update_func_abschange(zbx_dbpatch_function_t *function, char **replace)
{
	dbpatch_update_function(function, "change", "", ZBX_DBPATCH_FUNCTION_UPDATE);
	*replace = zbx_dsprintf(NULL, "abs({" ZBX_FS_UI64 "})", function->functionid);
}

static void	dbpatch_update_func_delta(zbx_dbpatch_function_t *function, const char *parameter, char **replace,
		zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;

	dbpatch_update_function(function, "max", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);

	functionid2 = (NULL == function->arg0 ? zbx_db_get_maxid("functions") : (zbx_uint64_t)functions->values_num);
	dbpatch_add_function(function, functionid2, "min", parameter, ZBX_DBPATCH_FUNCTION_CREATE, functions);

	*replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}-{" ZBX_FS_UI64 "})", function->functionid, functionid2);
}

static void	dbpatch_update_func_diff(zbx_dbpatch_function_t *function, char **replace, zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;

	dbpatch_update_function(function, "last", "#1", ZBX_DBPATCH_FUNCTION_UPDATE);

	functionid2 = (NULL == function->arg0 ? zbx_db_get_maxid("functions") : (zbx_uint64_t)functions->values_num);
	dbpatch_add_function(function, functionid2, "last", "#2", ZBX_DBPATCH_FUNCTION_CREATE, functions);

	*replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}<>{" ZBX_FS_UI64 "})", function->functionid, functionid2);
}

static void	dbpatch_update_func_trenddelta(zbx_dbpatch_function_t *function, const char *parameter, char **replace,
		zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	functionid2;

	dbpatch_update_function(function, "trendmax", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);

	functionid2 = (NULL == function->arg0 ? zbx_db_get_maxid("functions") : (zbx_uint64_t)functions->values_num);
	dbpatch_add_function(function, functionid2, "trendmin", parameter, ZBX_DBPATCH_FUNCTION_CREATE, functions);

	*replace = zbx_dsprintf(NULL, "({" ZBX_FS_UI64 "}-{" ZBX_FS_UI64 "})", function->functionid, functionid2);
}

static void	dbpatch_update_func_strlen(zbx_dbpatch_function_t *function, const char *parameter, char **replace)
{
	dbpatch_update_function(function, "last", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);

	*replace = zbx_dsprintf(NULL, "length({" ZBX_FS_UI64 "})", function->functionid);
}

void	dbpatch_update_hist2common(zbx_dbpatch_function_t *function, int extended, char **expression)
{
	char	*str  = NULL;
	size_t	str_alloc = 0, str_offset = 0;

	if (ZBX_DBPATCH_FUNCTION_DELETE == function->flags)
		dbpatch_update_function(function, "last", "$", ZBX_DBPATCH_FUNCTION_UPDATE);

	if (0 == extended)
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, '(');
	zbx_strcpy_alloc(&str, &str_alloc, &str_offset, *expression);
	if (0 == extended)
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, ')');

	zbx_snprintf_alloc(&str, &str_alloc, &str_offset, " or ({" ZBX_FS_UI64 "}<>{" ZBX_FS_UI64 "})",
			function->functionid, function->functionid);

	zbx_free(*expression);
	*expression = str;
}

static void	dbpatch_update_func_bitand(zbx_dbpatch_function_t *function, const zbx_vector_strloc_t *params,
		char **replace)
{
	char	*parameter = NULL, *mask = NULL;
	int	secnum = 0;

	if (2 <= params->values_num && '\0' != function->parameter[params->values[1].l])
	{
		mask = zbx_substr_unquote(function->parameter, params->values[1].l, params->values[1].r);
		*replace = zbx_dsprintf(NULL, "bitand({" ZBX_FS_UI64 "},%s)", function->functionid, mask);
		zbx_free(mask);
	}
	else
		*replace = zbx_dsprintf(NULL, "bitand({" ZBX_FS_UI64 "})", function->functionid);

	if (0 < params->values_num)
	{
		char	*param;

		param = zbx_substr_unquote(function->parameter, params->values[0].l, params->values[0].r);

		if ('#' != *param && '{' != *param)
			secnum = -1;

		zbx_free(param);
	}

	dbpatch_convert_params(&parameter, function->parameter, params,
			ZBX_DBPATCH_ARG_HIST, secnum, 2,
			ZBX_DBPATCH_ARG_NONE);

	dbpatch_update_function(function, "last", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);

	zbx_free(parameter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: quote and text to a buffer                                        *
 *                                                                            *
 * Parameters: str        - [OUT] the output buffer                           *
 *             str_alloc  - [IN/OUT] an offset in the output buffer           *
 *             str_offset - [IN/OUT] the size of the output buffer            *
 *             source     - [IN] the source text                              *
 *                                                                            *
 * Return value: SUCCEED - the text is a composite constant                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
void	dbpatch_strcpy_alloc_quoted_compat(char **str, size_t *str_alloc, size_t *str_offset, const char *source)
{
	char	raw[ZBX_DBPATCH_FUNCTION_PARAM_LEN * 5 + 1], quoted[sizeof(raw)];

	zbx_strlcpy(raw, source, sizeof(raw));
	zbx_escape_string(quoted, sizeof(quoted), raw, "\"");
	zbx_chrcpy_alloc(str, str_alloc, str_offset, '"');
	zbx_strcpy_alloc(str, str_alloc, str_offset, quoted);
	zbx_chrcpy_alloc(str, str_alloc, str_offset, '"');
}

/******************************************************************************
 *                                                                            *
 * Purpose: check for composite (consisting of macro(s) + text) constant      *
 *                                                                            *
 * Parameters: str - [IN] the text to check                                   *
 *                                                                            *
 * Return value: SUCCEED - the text is a composite constant                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	dbpatch_is_composite_constant(const char *str)
{
	zbx_token_t	token;

	if (SUCCEED == zbx_token_find(str, 0, &token, ZBX_TOKEN_SEARCH_BASIC | ZBX_TOKEN_SEARCH_SIMPLE_MACRO))
	{
		if (ZBX_TOKEN_USER_MACRO != token.type && ZBX_TOKEN_LLD_MACRO != token.type)
			return SUCCEED;

		if (0 != token.loc.l || strlen(str) - 1 != token.loc.r)
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert function parameters into new syntax                       *
 *                                                                            *
 * Parameters: out       - [OUT] the converted parameter string               *
 *             parameter - [IN] the original parameter string                 *
 *             params    - [IN] the parameter locations in original parameter *
 *                              string                                        *
 *             ...       - list of parameter descriptors with parameter data  *
 *                         (see zbx_dbpatch_arg_t enum for parameter list     *
 *                         description)                                       *
 *                                                                            *
 ******************************************************************************/
void	dbpatch_convert_params(char **out, const char *parameter, const zbx_vector_strloc_t *params, ...)
{
	size_t			out_alloc = 0, out_offset = 0;
	va_list 		args;
	int			index, type, param_num = 0;
	const zbx_strloc_t	*loc;
	const char		*ptr;
	char			*arg;
	int			quoted;

	va_start(args, params);

	while (ZBX_DBPATCH_ARG_NONE != (type = va_arg(args, int)))
	{
		if (0 != param_num++)
			zbx_chrcpy_alloc(out, &out_alloc, &out_offset, ',');

		switch (type)
		{
			case ZBX_DBPATCH_ARG_HIST:
				if (-1 != (index = va_arg(args, int)) && index < params->values_num)
				{
					loc = &params->values[index];
					arg = zbx_function_param_unquote_dyn_compat(parameter + loc->l,
							1 + loc->r - loc->l, &quoted);

					if ('\0' != *arg)
					{
						zbx_strcpy_alloc(out, &out_alloc, &out_offset, arg);

						if ('#' != *arg && 0 != isdigit(arg[strlen(arg) - 1]))
							zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
					}

					zbx_free(arg);
				}

				if (-1 != (index = va_arg(args, int)) && index < params->values_num)
				{
					loc = &params->values[index];
					arg = zbx_function_param_unquote_dyn_compat(parameter + loc->l,
							1 + loc->r - loc->l, &quoted);

					if ('\0' != *arg)
					{
						if (0 == out_offset)
							zbx_strcpy_alloc(out, &out_alloc, &out_offset, "#1");

						zbx_strcpy_alloc(out, &out_alloc, &out_offset, ":now-");
						zbx_strcpy_alloc(out, &out_alloc, &out_offset, arg);
						if (0 != isdigit(arg[strlen(arg) - 1]))
							zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
					}

					zbx_free(arg);
				}

				break;
			case ZBX_DBPATCH_ARG_TIME:
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_function_param_unquote_dyn_compat(parameter + loc->l,
							1 + loc->r - loc->l, &quoted);
					if ('\0' != *str)
					{
						if (SUCCEED == dbpatch_is_composite_constant(str))
							dbpatch_strcpy_alloc_quoted_compat(out, &out_alloc, &out_offset, str);
						else
							zbx_strcpy_alloc(out, &out_alloc, &out_offset, str);

						if (0 != isdigit((*out)[out_offset - 1]))
							zbx_chrcpy_alloc(out, &out_alloc, &out_offset, 's');
					}
					zbx_free(str);
				}
				break;
			case ZBX_DBPATCH_ARG_NUM:
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_function_param_unquote_dyn_compat(parameter + loc->l,
							1 + loc->r - loc->l, &quoted);

					if (SUCCEED == dbpatch_is_composite_constant(str))
						dbpatch_strcpy_alloc_quoted_compat(out, &out_alloc, &out_offset, str);
					else
						zbx_strcpy_alloc(out, &out_alloc, &out_offset, str);

					zbx_free(str);
				}
				break;
			case ZBX_DBPATCH_ARG_STR:
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_function_param_unquote_dyn_compat(parameter + loc->l,
							1 + loc->r - loc->l, &quoted);
					dbpatch_strcpy_alloc_quoted_compat(out, &out_alloc, &out_offset, str);

					zbx_free(str);
				}
				break;
			case ZBX_DBPATCH_ARG_TREND:
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_function_param_unquote_dyn_compat(parameter + loc->l,
							1 + loc->r - loc->l, &quoted);
					zbx_strcpy_alloc(out, &out_alloc, &out_offset, str);
					zbx_free(str);
				}
				if (params->values_num > (index = va_arg(args, int)))
				{
					char	*str;

					loc = &params->values[index];
					str = zbx_function_param_unquote_dyn_compat(parameter + loc->l,
							1 + loc->r - loc->l, &quoted);
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, ':');
					zbx_strcpy_alloc(out, &out_alloc, &out_offset, str);
					zbx_free(str);
				}
				break;
			case ZBX_DBPATCH_ARG_CONST_STR:
				if (NULL != (ptr = va_arg(args, char *)))
				{
					char	quoted_esc[MAX_STRING_LEN];

					zbx_escape_string(quoted_esc, sizeof(quoted_esc), ptr, "\"\\");
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
					zbx_strcpy_alloc(out, &out_alloc, &out_offset, quoted_esc);
					zbx_chrcpy_alloc(out, &out_alloc, &out_offset, '"');
				}
				break;
		}
	}

	va_end(args);

	if (0 != out_offset)
	{
		/* trim trailing empty parameters */
		while (0 < out_offset && ',' == (*out)[out_offset - 1])
			(*out)[--out_offset] = '\0';
	}
	else
		*out = zbx_strdup(*out, "");
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse function parameter string into parameter location vector    *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_parse_function_params(const char *parameter, zbx_vector_strloc_t *params)
{
	const char	*ptr;
	size_t		len, pos, sep = 0, eol;
	zbx_strloc_t	loc;

	eol = strlen(parameter);

	for (ptr = parameter; ptr < parameter + eol; ptr += sep + 1)
	{
		zbx_function_param_parse(ptr, &pos, &len, &sep);

		if (0 < len)
		{
			loc.l = ptr - parameter + pos;
			loc.r = loc.l + len - 1;
		}
		else
		{
			loc.l = ptr - parameter + eol - (ptr - parameter);
			loc.r = loc.l;
		}

		zbx_vector_strloc_append_ptr(params, &loc);
	}

	while (0 < params->values_num && '\0' == parameter[params->values[params->values_num - 1].l])
		--params->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert function to new parameter syntax/order                    *
 *                                                                            *
 * Parameters: function   - [IN/OUT] the function to convert                  *
 *             replace    - [OUT] the replacement for {functionid} in the     *
 *                          expression                                        *
 *             functions  - [IN/OUT] the functions                            *
 *                                                                            *
 * Comments: The function conversion can result in another function being     *
 *           added.                                                           *
 *                                                                            *
 ******************************************************************************/
void	dbpatch_convert_function(zbx_dbpatch_function_t *function, char **replace, zbx_vector_ptr_t *functions)
{
	zbx_vector_strloc_t	params;
	char			*parameter = NULL;

	zbx_vector_strloc_create(&params);

	dbpatch_parse_function_params(function->parameter, &params);

	if (0 == strcmp(function->name, "abschange"))
	{
		dbpatch_update_func_abschange(function, replace);
	}
	else if (0 == strcmp(function->name, "change"))
	{
		dbpatch_update_function(function, NULL, "", ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "avg") || 0 == strcmp(function->name, "max") ||
			0 == strcmp(function->name, "min") || 0 == strcmp(function->name, "sum"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "delta"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_func_delta(function, parameter, replace, functions);
	}
	else if (0 == strcmp(function->name, "diff"))
	{
		dbpatch_update_func_diff(function, replace, functions);
	}
	else if (0 == strcmp(function->name, "fuzzytime"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_TIME, 0,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "nodata"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_TIME, 0,
				ZBX_DBPATCH_ARG_STR, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "percentile"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NUM, 2,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "trendavg") || 0 == strcmp(function->name, "trendmin") ||
			0 == strcmp(function->name, "trendmax") || 0 == strcmp(function->name, "trendsum") ||
			0 == strcmp(function->name, "trendcount"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_TREND, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "trenddelta"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_TREND, 0, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_func_trenddelta(function, parameter, replace, functions);
	}
	else if (0 == strcmp(function->name, "band"))
	{
		dbpatch_update_func_bitand(function, &params, replace);
	}
	else if (0 == strcmp(function->name, "forecast"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_TIME, 2,
				ZBX_DBPATCH_ARG_STR, 3,
				ZBX_DBPATCH_ARG_STR, 4,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "timeleft"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 1,
				ZBX_DBPATCH_ARG_NUM, 2,
				ZBX_DBPATCH_ARG_STR, 3,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "count"))
	{
		char	*op = NULL;

		if (2 <= params.values_num)
		{
			if (3 <= params.values_num && '\0' != function->parameter[params.values[2].l])
			{
				op = zbx_substr_unquote(function->parameter, params.values[2].l, params.values[2].r);

				if (0 == strcmp(op, "band"))
					op = zbx_strdup(op, "bitand");
				else if ('\0' == *op && '"' != function->parameter[params.values[2].l])
					zbx_free(op);
			}
		}

		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 0, 3,
				ZBX_DBPATCH_ARG_CONST_STR, op,
				ZBX_DBPATCH_ARG_STR, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);

		zbx_free(op);
	}
	else if (0 == strcmp(function->name, "iregexp") || 0 == strcmp(function->name, "regexp"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 1, -1,
				ZBX_DBPATCH_ARG_CONST_STR, function->name,
				ZBX_DBPATCH_ARG_STR, 0,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, "find", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);
	}
	else if (0 == strcmp(function->name, "str"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, 1, -1,
				ZBX_DBPATCH_ARG_CONST_STR, "like",
				ZBX_DBPATCH_ARG_STR, 0,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, "find", parameter, ZBX_DBPATCH_FUNCTION_UPDATE);
	}
	else if (0 == strcmp(function->name, "last"))
	{
		int	secnum = 0;

		if (0 < params.values_num)
		{
			char	*param;

			param = zbx_substr_unquote(function->parameter, params.values[0].l, params.values[0].r);

			if ('#' != *param && '{' != *param)
				secnum = -1;

			zbx_free(param);
		}

		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, secnum, 1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "prev"))
	{
		dbpatch_update_function(function, "last", "#2", ZBX_DBPATCH_FUNCTION_UPDATE);
	}
	else if (0 == strcmp(function->name, "strlen"))
	{
		int	secnum = 0;

		if (0 < params.values_num)
		{
			char	*param;

			param = zbx_substr_unquote(function->parameter, params.values[0].l, params.values[0].r);

			if ('#' != *param && '{' != *param)
				secnum = -1;

			zbx_free(param);
		}

		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, secnum, 1,
				ZBX_DBPATCH_ARG_NONE);

		dbpatch_update_func_strlen(function, parameter, replace);
	}
	else if (0 == strcmp(function->name, "logeventid") || 0 == strcmp(function->name, "logsource"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, -1, -1,
				ZBX_DBPATCH_ARG_STR, 0,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, parameter, ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}
	else if (0 == strcmp(function->name, "logseverity"))
	{
		dbpatch_convert_params(&parameter, function->parameter, &params,
				ZBX_DBPATCH_ARG_HIST, -1, -1,
				ZBX_DBPATCH_ARG_NONE);
		dbpatch_update_function(function, NULL, "", ZBX_DBPATCH_FUNCTION_UPDATE_PARAM);
	}

	zbx_free(parameter);
	zbx_vector_strloc_destroy(&params);
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace functionids {<index in functions vector>} in expression   *
 *          with their string format                                          *
 *                                                                            *
 * Parameters: expression - [IN/OUT] the expression                           *
 *             functions  - [IN] the functions                                *
 *                                                                            *
 ******************************************************************************/
static void	dbpatch_replace_functionids(char **expression, const zbx_vector_ptr_t *functions)
{
	zbx_uint64_t	index;
	int		pos = 0, last_pos = 0;
	zbx_token_t	token;
	char		*out = NULL;
	size_t		out_alloc = 0, out_offset = 0;

	for (; SUCCEED == zbx_token_find(*expression, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID |
			ZBX_TOKEN_SEARCH_SIMPLE_MACRO); pos++)
	{
		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
				if (SUCCEED == zbx_is_uint64_n(*expression + token.loc.l + 1,
						token.loc.r - token.loc.l - 1, &index) &&
						(int)index < functions->values_num)
				{
					zbx_dbpatch_function_t	*func = functions->values[index];

					zbx_strncpy_alloc(&out, &out_alloc, &out_offset,
							*expression + last_pos, token.loc.l - last_pos);

					zbx_snprintf_alloc(&out, &out_alloc, &out_offset, "%s(%s",
							func->name, func->arg0);
					if ('\0' != *func->parameter)
					{
						zbx_chrcpy_alloc(&out, &out_alloc, &out_offset, ',');
						zbx_strcpy_alloc(&out, &out_alloc, &out_offset, func->parameter);
					}
					zbx_chrcpy_alloc(&out, &out_alloc, &out_offset, ')');
					last_pos = token.loc.r + 1;
				}
				pos = token.loc.r;
				break;
			case ZBX_TOKEN_MACRO:
			case ZBX_TOKEN_USER_MACRO:
			case ZBX_TOKEN_LLD_MACRO:
				pos = token.loc.r;
				break;
		}
	}

	if (0 != out_alloc)
	{
		zbx_strcpy_alloc(&out, &out_alloc, &out_offset, *expression + last_pos);
		zbx_free(*expression);
		*expression = out;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert simple macro {host.key:func(params)} to the new syntax    *
 *          func(/host/key,params)                                            *
 *                                                                            *
 * Parameters: expression - [IN] the expression with simple macro             *
 *             data       - [IN] the simple macro token data                  *
 *             more       - [IN] also replace {HOSTNAME*} and {HOST.HOST1}    *
 *             function   - [OUT] the simple macro replacement function       *
 *                                                                            *
 ******************************************************************************/
void	dbpatch_convert_simple_macro(const char *expression, const zbx_token_simple_macro_t *data, int more,
		char **function)
{
#define HOSTHOST_STR		"{HOST.HOST"
#define HOSTNAME_STR		"{HOSTNAME"
#define HOSTHOST_IDX_POS	ZBX_CONST_STRLEN(HOSTHOST_STR)
#define HOSTNAME_IDX_POS	ZBX_CONST_STRLEN(HOSTNAME_STR)

	zbx_dbpatch_function_t	*func;
	zbx_vector_ptr_t	functions;
	char			*name, *host, *key;
	int			pos;

	name = zbx_substr(expression, data->func.l, data->func_param.l - 1);

	if (SUCCEED == dbpatch_is_time_function(name, strlen(name)))
	{
		*function = zbx_dsprintf(NULL, "%s()", name);
		zbx_free(name);
		return;
	}

	zbx_vector_ptr_create(&functions);

	func = (zbx_dbpatch_function_t *)zbx_malloc(NULL, sizeof(zbx_dbpatch_function_t));
	func->functionid = 0;
	func->itemid = 0;
	func->flags = 0;
	func->name = name;

	if (data->func_param.l + 1 == data->func_param.r)
		func->parameter = zbx_strdup(NULL, "");
	else
		func->parameter = zbx_substr(expression, data->func_param.l + 1, data->func_param.r - 1);

	host = zbx_substr(expression, data->host.l, data->host.r);
	key = zbx_substr(expression, data->key.l, data->key.r);

	if (0 == strncmp(host, HOSTHOST_STR, HOSTHOST_IDX_POS))
		pos = HOSTHOST_IDX_POS;
	else if (0 != more && 0 == strncmp(host, HOSTNAME_STR, HOSTNAME_IDX_POS))
		pos = HOSTNAME_IDX_POS;
	else
		pos = 0;

	if ((0 != pos && (('}' == host[pos] && '\0' == host[pos + 1]) || (0 != more && 0 == strcmp("1}", host + pos)))))
	{
		func->arg0 = zbx_dsprintf(NULL, "//%s", key);
	}
	else if (HOSTNAME_IDX_POS == pos && isdigit(host[pos]) && '0' != host[pos] && '}' == host[pos + 1] &&
			'\0' == host[pos + 2])
	{
		func->arg0 = zbx_dsprintf(NULL, "/{HOST.HOST%c}/%s", host[pos], key);
	}
	else
		func->arg0 = zbx_dsprintf(NULL, "/%s/%s", host, key);

	zbx_vector_ptr_append(&functions, func);

	dbpatch_convert_function(func, function, &functions);
	if (NULL == *function)
		*function = zbx_strdup(NULL, "{0}");
	dbpatch_replace_functionids(function, &functions);

	zbx_free(key);
	zbx_free(host);
	zbx_vector_ptr_clear_ext(&functions, (zbx_clean_func_t)dbpatch_function_free);
	zbx_vector_ptr_destroy(&functions);

#undef HOSTHOST_IDX_POS
#undef HOSTNAME_IDX_POS
#undef HOSTHOST_STR
#undef HOSTNAME_STR
}
