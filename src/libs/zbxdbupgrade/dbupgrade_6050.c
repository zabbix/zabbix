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

#include "dbupgrade.h"
#include "zbxdbschema.h"
#include "zbxexpr.h"
#include "zbxeval.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "log.h"

/*
 * 7.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6050000(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field, NULL);
}

static int	DBpatch_6050001(void)
{
	const zbx_db_field_t	field = {"geomaps_tile_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field, NULL);
}

static int	DBpatch_6050002(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("sysmap_url", &field, NULL);
}

static int	DBpatch_6050003(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("sysmap_element_url", &field, NULL);
}

static int	DBpatch_6050004(void)
{
	const zbx_db_field_t	field = {"url_a", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050005(void)
{
	const zbx_db_field_t	field = {"url_b", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050006(void)
{
	const zbx_db_field_t	field = {"url_c", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050007(void)
{
	const zbx_db_field_t	field = {"value_str", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("widget_field", &field, NULL);
}

static int	DBpatch_6050008(void)
{
	const zbx_db_field_t	field = {"value", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("history", "value", ZBX_TYPE_FLOAT))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	return DBmodify_field_type("history", &field, &field);
}

static int	DBpatch_6050009(void)
{
	const zbx_db_field_t	field = {"value_min", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_min", ZBX_TYPE_FLOAT))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_6050010(void)
{
	const zbx_db_field_t	field = {"value_avg", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_avg", ZBX_TYPE_FLOAT))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_6050011(void)
{
	const zbx_db_field_t	field = {"value_max", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_max", ZBX_TYPE_FLOAT))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_6050012(void)
{
	const zbx_db_field_t	field = {"allow_redirect", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_6050013(void)
{
	const zbx_db_table_t	table =
			{"history_bin", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_BLOB, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static char	*fix_hist_param_escaping(const char *param, size_t left, size_t right)
{
	size_t escaped_len = 0;
	/* resulting string cannot be more than 2 times longer than original string */
	char *escaped = zbx_malloc(NULL, 2 * (right - left + 1) * sizeof(char) + 1);

	for (size_t i = left; i <= right; i++)
	{
		escaped[escaped_len++] = param[i];
		if (i < right && '\\' == param[i] && '"' != param[i + 1])
			escaped[escaped_len++] = '\\';
	}
	escaped[escaped_len++] = '\0';

	return escaped;
}

static int	DBpatch_6050014(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* functions table contains history functions used in trigger expressions */
	result = zbx_db_select("select functionid,parameter from functions where length(parameter) > 1");

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
	{
		const char	*ptr;
		char		*buf, *tmp, *param = NULL;
		size_t		param_pos, param_len, sep_pos, buf_alloc, buf_offset = 0;

		buf_alloc  = strlen(row[1]);
		buf = zbx_malloc(NULL, buf_alloc);

		for (ptr = row[1]; ptr < row[1] + strlen(row[1]); ptr += sep_pos + 1)
		{
			zbx_function_param_parse(ptr, &param_pos, &param_len, &sep_pos);

			if (param_pos < sep_pos)
			{
				param = fix_hist_param_escaping(ptr, param_pos, sep_pos - 1);
				zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, param);
			}

			if (',' == ptr[sep_pos])
				zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ',');
			zbx_free(param);
		}

		tmp = zbx_db_dyn_escape_string(buf);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update functions set parameter='%s' where functionid=%s;\n", tmp, row[0]);
		zbx_free(tmp);
		zbx_free(buf);

		if (SUCCEED == ret)
			ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	zbx_db_free_result(result);
	zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);

	return ret;
}

typedef struct {
	zbx_uint32_t	op_num;
	char		is_hist;
} expr_fun_call;

ZBX_VECTOR_DECL(fun_stack, expr_fun_call)
ZBX_VECTOR_IMPL(fun_stack, expr_fun_call)

static int	DBpatch_6050015(void)
{
	int			ret = SUCCEED;
	zbx_eval_context_t	ctx;
	int			token_num;
	const zbx_eval_token_t	*token;
	zbx_vector_fun_stack_t	fun_stack;
	zbx_vector_ptr_t	hist_param_tokens;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*tmp, *substitute = NULL, *sql = NULL, *error = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_fun_stack_create(&fun_stack);
	zbx_vector_ptr_create(&hist_param_tokens);

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (NULL == (result = zbx_db_select("select itemid,params from items where type = 15")))
		goto clean;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ret = zbx_eval_parse_expression_str_v64_compat(&ctx, row[1], ZBX_EVAL_PARSE_CALC_EXPRESSION, &error);
		if (FAIL == ret)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Failed to parse calculated item expression \"%s\" for"
					" item with id %s, error: %s", row[1], row[0], error);
			goto clean;
		}
		zbx_free(error);

		token_num = 0;
		zbx_vector_fun_stack_clear(&fun_stack);
		zbx_vector_ptr_clear(&hist_param_tokens);
		substitute = zbx_strdup(NULL, ctx.expression);

		/* finding string parameters of history functions */
		for (int token_num = ctx.stack.values_num - 1; token_num >= 0; token_num--)
		{
			token = &ctx.stack.values[token_num];

			if (0 < fun_stack.values_num) {
				expr_fun_call	*cur_call = &(fun_stack.values[fun_stack.values_num - 1]);

				if (ZBX_EVAL_TOKEN_VAR_STR == token->type && cur_call->is_hist)
					zbx_vector_ptr_append(&hist_param_tokens, (void *)token);

				if (0 == --(cur_call->op_num))
					fun_stack.values_num--;
			}

			if (0 < token->opt)
			{
				expr_fun_call	call = {token->opt, ZBX_EVAL_TOKEN_HIST_FUNCTION == token->type};

				zbx_vector_fun_stack_append(&fun_stack, call);
			}
		}

		/* Substitution logic relies on replacing further-most tokens first */
		zbx_vector_ptr_sort(&hist_param_tokens, zbx_eval_compare_tokens_by_loc);

		/* adding necessary escaping to the the string parameters of history functions */
		for (token_num = 0; token_num < hist_param_tokens.values_num; token_num++)
		{
			size_t	right_pos;

			token = hist_param_tokens.values[token_num];
			right_pos = token->loc.r;
			tmp = fix_hist_param_escaping(substitute, token->loc.l, right_pos);
			zbx_replace_string(&substitute, token->loc.l, &right_pos, tmp);

			zbx_free(tmp);
		}

		tmp = zbx_db_dyn_escape_string(substitute);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set params='%s' where itemid=%s;\n",
				tmp, row[0]);
		zbx_free(tmp);
		zbx_free(substitute);
		zbx_eval_clear(&ctx);

		if (SUCCEED == ret)
			ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	zbx_db_free_result(result);
	zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			ret = FAIL;
	}

clean:
	zbx_free(error);
	zbx_free(substitute);

	zbx_vector_fun_stack_destroy(&fun_stack);
	zbx_vector_ptr_destroy(&hist_param_tokens);
	zbx_free(sql);

	return ret;
}

#endif

DBPATCH_START(6050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6050000, 0, 1)
DBPATCH_ADD(6050001, 0, 1)
DBPATCH_ADD(6050002, 0, 1)
DBPATCH_ADD(6050003, 0, 1)
DBPATCH_ADD(6050004, 0, 1)
DBPATCH_ADD(6050005, 0, 1)
DBPATCH_ADD(6050006, 0, 1)
DBPATCH_ADD(6050007, 0, 1)
DBPATCH_ADD(6050008, 0, 1)
DBPATCH_ADD(6050009, 0, 1)
DBPATCH_ADD(6050010, 0, 1)
DBPATCH_ADD(6050011, 0, 1)
DBPATCH_ADD(6050012, 0, 1)
DBPATCH_ADD(6050013, 0, 1)
DBPATCH_ADD(6050014, 0, 1)
DBPATCH_ADD(6050015, 0, 1)

DBPATCH_END()
