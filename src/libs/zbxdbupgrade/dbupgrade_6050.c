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

#include "zbxdbhigh.h"
#include "dbupgrade.h"
#include "zbxdbschema.h"
#include "zbxexpr.h"
#include "zbxeval.h"
#include "zbxalgo.h"
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
		char		*buf, *param = NULL;
		int		escaped;
		size_t		param_pos, param_len, sep_pos, buf_alloc, buf_offset = 0;

		buf_alloc  = strlen(row[1]);
		buf = zbx_malloc(NULL, buf_alloc);

		for (ptr = row[1]; ptr < row[1] + strlen(row[1]); ptr += sep_pos + 1)
		{
			zbx_function_param_parse(ptr, &param_pos, &param_len, &sep_pos);
			param = zbx_function_param_unquote_dyn(ptr + param_pos, param_len, &escaped);

			if (SUCCEED == zbx_function_param_escape(&param, escaped))
				zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, param);
			else
				ret = FAIL;

			if (',' == ptr[sep_pos])
				zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ',');

			zbx_free(param);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update functions set parameter='%s' where functionid=%s;\n", buf, row[0]);
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

static int	DBpatch_6050009(void)
{
	char			*error = NULL;
	int			ret = SUCCEED;
	zbx_eval_context_t	ctx;
	int			token_num;
	const zbx_eval_token_t	*token;
	zbx_vector_fun_stack_t	fun_stack;
	zbx_vector_ptr_t	hist_param_tokens;
	char			*substitute = NULL;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_fun_stack_create(&fun_stack);
	zbx_vector_ptr_create(&hist_param_tokens);

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (NULL == (result = zbx_db_select("select itemid,params from items where type = 15")))
		goto clean;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ret = zbx_eval_parse_expression(&ctx, row[1], ZBX_EVAL_PARSE_CALC_EXPRESSION, &error);
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
			char	*escaped = NULL;
			size_t	escaped_len = 0;

			token = hist_param_tokens.values[token_num];
			right_pos = token->loc.r;

			/* resulting string cannot be more than 2 times longer than original string */
			escaped = zbx_malloc(NULL, 2 * (right_pos - token->loc.l + 1) * sizeof(char) + 1);

			for (size_t i = token->loc.l; i <= right_pos; i++)
			{
				escaped[escaped_len++] = substitute[i];
				if (i < right_pos && '\\' == substitute[i] && '"' != substitute[i + 1])
					escaped[escaped_len++] = '\\';
			}
			escaped[escaped_len++] = '\0';

			zbx_replace_string(&substitute, token->loc.l, &right_pos, escaped);
			zbx_free(escaped);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update items set params='%s' where itemid=%s;\n", substitute, row[0]);
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

DBPATCH_END()
