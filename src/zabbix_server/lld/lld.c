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

#include "lld.h"
#include "zbxexpression.h"

#include "zbxregexp.h"
#include "audit/zbxaudit.h"
#include "zbxnum.h"
#include "zbx_host_constants.h"
#include "zbx_trigger_constants.h"
#include "zbx_item_constants.h"
#include "zbxvariant.h"
#include "zbxdb.h"
#include "zbxexpr.h"
#include "zbxstr.h"
#include "zbxtime.h"

ZBX_PTR_VECTOR_IMPL(lld_condition_ptr, lld_condition_t*)
ZBX_PTR_VECTOR_IMPL(lld_item_link_ptr, zbx_lld_item_link_t*)
ZBX_PTR_VECTOR_IMPL(lld_override_ptr, zbx_lld_override_t*)
ZBX_PTR_VECTOR_IMPL(lld_row_ptr, zbx_lld_row_t*)
ZBX_PTR_VECTOR_IMPL(lld_item_ptr, zbx_lld_item_t*)
ZBX_PTR_VECTOR_IMPL(lld_item_prototype_ptr, zbx_lld_item_prototype_t*)

int	lld_item_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_t	*item_1 = *(const zbx_lld_item_t **)d1;
	const zbx_lld_item_t	*item_2 = *(const zbx_lld_item_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(item_1->itemid, item_2->itemid);

	return 0;
}

int	lld_item_link_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_link_t	*link_1 = *(const zbx_lld_item_link_t **)d1;
	const zbx_lld_item_link_t	*link_2 = *(const zbx_lld_item_link_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(link_1->parent_itemid, link_2->parent_itemid);

	return 0;
}

int	lld_item_full_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_full_t	*item_1 = *(const zbx_lld_item_full_t **)d1;
	const zbx_lld_item_full_t	*item_2 = *(const zbx_lld_item_full_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(item_1->itemid, item_2->itemid);

	return 0;
}

int	lld_item_prototype_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_prototype_t	*proto_1 = *(const zbx_lld_item_prototype_t **)d1;
	const zbx_lld_item_prototype_t	*proto_2 = *(const zbx_lld_item_prototype_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(proto_1->itemid, proto_2->itemid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by filter condition                  *
 *                                                                            *
 * Parameters: condition - [IN] filter condition                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_condition_free(lld_condition_t *condition)
{
	zbx_regexp_clean_expressions(&condition->regexps);
	zbx_vector_expression_destroy(&condition->regexps);

	zbx_free(condition->macro);
	zbx_free(condition->regexp);
	zbx_free(condition);
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by filter conditions                 *
 *                                                                            *
 * Parameters: conditions - [IN] filter conditions                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_conditions_free(zbx_vector_lld_condition_ptr_t *conditions)
{
	zbx_vector_lld_condition_ptr_clear_ext(conditions, lld_condition_free);
	zbx_vector_lld_condition_ptr_destroy(conditions);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares two filter conditions by their macros                    *
 *                                                                            *
 ******************************************************************************/
static int	lld_condition_compare_by_macro(const void *cond1, const void *cond2)
{
	lld_condition_t	*condition1 = *(lld_condition_t **)cond1;
	lld_condition_t	*condition2 = *(lld_condition_t **)cond2;

	return strcmp(condition1->macro, condition2->macro);
}

static void	lld_filter_init(zbx_lld_filter_t *filter)
{
	zbx_vector_lld_condition_ptr_create(&filter->conditions);
	filter->expression = NULL;
	filter->evaltype = ZBX_CONDITION_EVAL_TYPE_AND_OR;
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by LLD filter                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_filter_clean(zbx_lld_filter_t *filter)
{
	zbx_free(filter->expression);
	lld_conditions_free(&filter->conditions);
}

static int	lld_filter_condition_add(zbx_vector_lld_condition_ptr_t *conditions, const char *id, const char *macro,
		const char *regexp, const char *op, const zbx_dc_item_t *item, char **error)
{
	lld_condition_t	*condition;

	condition = (lld_condition_t *)zbx_malloc(NULL, sizeof(lld_condition_t));
	ZBX_STR2UINT64(condition->id, id);
	condition->macro = zbx_strdup(NULL, macro);
	condition->regexp = zbx_strdup(NULL, regexp);
	condition->op = (unsigned char)atoi(op);

	zbx_vector_expression_create(&condition->regexps);

	zbx_vector_lld_condition_ptr_append(conditions, condition);

	if ('@' == *condition->regexp)
	{
		zbx_dc_get_expressions_by_name(&condition->regexps, condition->regexp + 1);

		if (0 == condition->regexps.values_num)
		{
			*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.",
					condition->regexp + 1);
			return FAIL;
		}
	}
	else
	{
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, item, NULL, NULL, NULL, NULL, NULL,
				&condition->regexp, ZBX_MACRO_TYPE_LLD_FILTER, NULL, 0);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: loads LLD filter data                                             *
 *                                                                            *
 * Parameters: filter     - [IN] LLD filter                                   *
 *             lld_ruleid - [IN]                                              *
 *             item       - [IN] LLD item                                     *
 *             error      - [OUT] error message                               *
 *                                                                            *
 ******************************************************************************/
static int	lld_filter_load(zbx_lld_filter_t *filter, zbx_uint64_t lld_ruleid, const zbx_dc_item_t *item,
		char **error)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select item_conditionid,macro,value,operator"
			" from item_condition"
			" where itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)) && SUCCEED == (ret = lld_filter_condition_add(&filter->conditions,
			row[0], row[1], row[2], row[3], item, error)))
		;
	zbx_db_free_result(result);

	if (ZBX_CONDITION_EVAL_TYPE_AND_OR == filter->evaltype)
		zbx_vector_lld_condition_ptr_sort(&filter->conditions, lld_condition_compare_by_macro);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if LLD data passes filter evaluation                       *
 *                                                                            *
 * Parameters: jp_row          - [IN] LLD data row                            *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             condition       - [IN] LLD filter condition                    *
 *             result          - [OUT] result of evaluation                   *
 *             err_msg         - [OUT]                                        *
 *                                                                            *
 * Return value: SUCCEED - LLD data passed filter evaluation                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	filter_condition_match(const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, const lld_condition_t *condition, int *result,
		char **err_msg)
{
	char	*value = NULL;
	int	ret = SUCCEED;

	if (SUCCEED == zbx_lld_macro_value_by_name(jp_row, lld_macro_paths, condition->macro, &value))
	{
		if (ZBX_CONDITION_OPERATOR_NOT_EXIST == condition->op)
		{
			*result = 0;
		}
		else if (ZBX_CONDITION_OPERATOR_EXIST == condition->op)
		{
			*result = 1;
		}
		else
		{
			switch (zbx_regexp_match_ex(&condition->regexps, value, condition->regexp, ZBX_CASE_SENSITIVE))
			{
				case ZBX_REGEXP_MATCH:
					*result = (ZBX_CONDITION_OPERATOR_REGEXP == condition->op ? 1 : 0);
					break;
				case ZBX_REGEXP_NO_MATCH:
					*result = (ZBX_CONDITION_OPERATOR_NOT_REGEXP == condition->op ? 1 : 0);
					break;
				default:
					*err_msg = zbx_strdcatf(*err_msg,
						"Cannot accurately apply filter: invalid regular expression \"%s\".\n",
						condition->regexp);
					ret = FAIL;
			}
		}
	}
	else
	{
		switch (condition->op)
		{
			case ZBX_CONDITION_OPERATOR_NOT_EXIST:
				*result = 1;
				break;
			case ZBX_CONDITION_OPERATOR_EXIST:
				*result = 0;
				break;
			default:
				*err_msg = zbx_strdcatf(*err_msg,
						"Cannot accurately apply filter: no value received for macro \"%s\".\n",
						condition->macro);
				ret = FAIL;
		}
	}

	zbx_free(value);

	return ret;
}

/****************************************************************************************
 *                                                                                      *
 * Purpose: checks if LLD data passes filter evaluation by and/or/andor rules           *
 *                                                                                      *
 * Parameters: filter          - [IN] LLD filter                                        *
 *             jp_row          - [IN] LLD data row                                      *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row              *
 *             info            - [OUT] warning description                              *
 *                                                                                      *
 * Return value: SUCCEED - LLD data passed filter evaluation                            *
 *               FAIL    - otherwise                                                    *
 *                                                                                      *
 ****************************************************************************************/
static int	filter_evaluate_and_or_andor(const zbx_lld_filter_t *filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **info)
{
	int			ret = SUCCEED, error_num = 0, res;
	double			result;
	lld_condition_t		*condition;
	char			*lastmacro = NULL, *ops[] = {NULL, "and", "or"}, error[256], *expression = NULL,
				*errmsg = NULL;
	size_t			expression_alloc = 0, expression_offset = 0;
	zbx_vector_str_t	errmsgs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&errmsgs);

	for (int i = 0; i < filter->conditions.values_num; i++)
	{
		condition = filter->conditions.values[i];

		switch (filter->evaltype)
		{
			case ZBX_CONDITION_EVAL_TYPE_AND_OR:
				if (NULL == lastmacro)
				{
					zbx_chrcpy_alloc(&expression, &expression_alloc, &expression_offset, '(');
				}
				else if (0 != strcmp(lastmacro, condition->macro))
				{
					zbx_strcpy_alloc(&expression, &expression_alloc, &expression_offset, ") and (");
				}
				else
					zbx_strcpy_alloc(&expression, &expression_alloc, &expression_offset, " or ");

				lastmacro = condition->macro;
				break;
			case ZBX_CONDITION_EVAL_TYPE_AND:
			case ZBX_CONDITION_EVAL_TYPE_OR:
				if (0 != i)
				{
					zbx_chrcpy_alloc(&expression, &expression_alloc, &expression_offset, ' ');
					zbx_strcpy_alloc(&expression, &expression_alloc, &expression_offset,
							ops[filter->evaltype]);
					zbx_chrcpy_alloc(&expression, &expression_alloc, &expression_offset, ' ');
				}
				break;
			default:
				*info = zbx_strdcatf(*info, "Cannot accurately apply filter: invalid condition "
						"type \"%d\".\n", filter->evaltype);
				goto out;
		}

		if (SUCCEED == (ret = filter_condition_match(jp_row, lld_macro_paths, condition, &res, &errmsg)))
		{
			zbx_snprintf_alloc(&expression, &expression_alloc, &expression_offset, "%d", res);
		}
		else
		{
			zbx_snprintf_alloc(&expression, &expression_alloc, &expression_offset, ZBX_UNKNOWN_STR "%d",
					error_num++);
			zbx_vector_str_append(&errmsgs, errmsg);
			errmsg = NULL;
		}
	}

	if (ZBX_CONDITION_EVAL_TYPE_AND_OR == filter->evaltype)
		zbx_chrcpy_alloc(&expression, &expression_alloc, &expression_offset, ')');

	if (SUCCEED == zbx_evaluate(&result, expression, error, sizeof(error), &errmsgs))
	{
		ret = (SUCCEED != zbx_double_compare(result, 0) ? SUCCEED : FAIL);
	}
	else
	{
		*info = zbx_strdcat(*info, error);
		ret = FAIL;
	}
out:
	zbx_free(expression);
	zbx_vector_str_clear_ext(&errmsgs, zbx_str_free);
	zbx_vector_str_destroy(&errmsgs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if LLD data passes filter evaluation by custom expression  *
 *                                                                            *
 * Parameters: filter          - [IN] LLD filter                              *
 *             jp_row          - [IN] LLD data row                            *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             err_msg         - [OUT]                                        *
 *                                                                            *
 * Return value: SUCCEED - LLD data passed filter evaluation                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: 1) replace {item_condition} references with action condition     *
 *              evaluation results (1 or 0)                                   *
 *           2) call zbx_evaluate() to calculate final result                 *
 *                                                                            *
 ******************************************************************************/
static int	filter_evaluate_expression(const zbx_lld_filter_t *filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **err_msg)
{
	int			ret, res, error_num = 0;
	char			*expression = NULL, id[ZBX_MAX_UINT64_LEN + 2], *p, error[256], value[16],
				*errmsg = NULL;
	double			result;
	zbx_vector_str_t	errmsgs;
	size_t			expression_alloc = 0, expression_offset = 0, id_len, value_len;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:%s", __func__, filter->expression);

	zbx_strcpy_alloc(&expression, &expression_alloc, &expression_offset, filter->expression);

	/* include trailing zero */
	expression_offset++;

	zbx_vector_str_create(&errmsgs);

	for (int i = 0; i < filter->conditions.values_num; i++)
	{
		const lld_condition_t	*condition = filter->conditions.values[i];

		if (SUCCEED == filter_condition_match(jp_row, lld_macro_paths, condition, &res, &errmsg))
		{
			zbx_snprintf(value, sizeof(value), "%d", res);
		}
		else
		{
			zbx_snprintf(value, sizeof(value), ZBX_UNKNOWN_STR "%d", error_num++);
			zbx_vector_str_append(&errmsgs, errmsg);
			errmsg = NULL;
		}

		zbx_snprintf(id, sizeof(id), "{" ZBX_FS_UI64 "}", condition->id);

		value_len = strlen(value);
		id_len = strlen(id);

		for (p = strstr(expression, id); NULL != p; p = strstr(p, id))
		{
			size_t	id_pos = p - expression;

			zbx_replace_mem_dyn(&expression, &expression_alloc, &expression_offset, id_pos, id_len,
					value, value_len);

			p = expression + id_pos + value_len;
		}
	}

	if (SUCCEED == zbx_evaluate(&result, expression, error, sizeof(error), &errmsgs))
	{
		ret = (SUCCEED != zbx_double_compare(result, 0) ? SUCCEED : FAIL);
	}
	else
	{
		*err_msg = zbx_strdcat(*err_msg, error);
		ret = FAIL;
	}

	zbx_free(expression);
	zbx_vector_str_clear_ext(&errmsgs, zbx_str_free);
	zbx_vector_str_destroy(&errmsgs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if LLD data passes filter evaluation                       *
 *                                                                            *
 * Parameters: filter          - [IN] LLD filter                              *
 *             jp_row          - [IN] LLD data row                            *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             info            - [OUT] warning description                    *
 *                                                                            *
 * Return value: SUCCEED - LLD data passed filter evaluation                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	filter_evaluate(const zbx_lld_filter_t *filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **info)
{
	if (0 == filter->conditions.values_num)
		return SUCCEED;

	switch (filter->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_AND_OR:
		case ZBX_CONDITION_EVAL_TYPE_AND:
		case ZBX_CONDITION_EVAL_TYPE_OR:
			return filter_evaluate_and_or_andor(filter, jp_row, lld_macro_paths, info);
		case ZBX_CONDITION_EVAL_TYPE_EXPRESSION:
			return filter_evaluate_expression(filter, jp_row, lld_macro_paths, info);
	}

	return FAIL;
}

static int	lld_override_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_override_t	*override_1 = *(const zbx_lld_override_t **)d1;
	const zbx_lld_override_t	*override_2 = *(const zbx_lld_override_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(override_1->overrideid, override_2->overrideid);

	return 0;
}

static int	lld_override_conditions_load(zbx_vector_lld_override_ptr_t *overrides,
		const zbx_vector_uint64_t *overrideids, char **sql, size_t *sql_alloc, const zbx_dc_item_t *item,
		char **error)
{
	size_t			sql_offset = 0;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_override_t	*override;
	int			ret = SUCCEED, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset,
			"select lld_overrideid,lld_override_conditionid,macro,value,operator"
			" from lld_override_condition"
			" where");
	zbx_db_add_condition_alloc(sql, sql_alloc, &sql_offset, "lld_overrideid", overrideids->values,
			overrideids->values_num);

	result = zbx_db_select("%s", *sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	overrideid;

		ZBX_STR2UINT64(overrideid, row[0]);

		const zbx_lld_override_t	cmp = {.overrideid = overrideid};

		if (FAIL == (i = zbx_vector_lld_override_ptr_bsearch(overrides, &cmp,
				lld_override_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		override = overrides->values[i];
		if (FAIL == (ret = lld_filter_condition_add(&override->filter.conditions, row[1], row[2], row[3],
				row[4], item, error)))
		{
			break;
		}
	}
	zbx_db_free_result(result);

	for (i = 0; i < overrides->values_num; i++)
	{
		override = overrides->values[i];

		if (ZBX_CONDITION_EVAL_TYPE_AND_OR == override->filter.evaltype)
			zbx_vector_lld_condition_ptr_sort(&override->filter.conditions, lld_condition_compare_by_macro);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	lld_override_operations_load(zbx_vector_lld_override_ptr_t *overrides,
		const zbx_vector_uint64_t *overrideids, char **sql, size_t *sql_alloc)
{
	zbx_vector_lld_override_operation_t	ops;
	int					index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_lld_override_operation_create(&ops);

	zbx_load_lld_override_operations(overrideids, sql, sql_alloc, &ops);

	for (int i = 0; i < ops.values_num; i++)
	{
		zbx_lld_override_operation_t	*op = ops.values[i];
		zbx_lld_override_t		*override, cmp = {.overrideid = op->overrideid};

		if (FAIL == (index = zbx_vector_lld_override_ptr_bsearch(overrides,
				&cmp, lld_override_compare_func)))
		{
			zbx_lld_override_operation_free(op);
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}
		override = overrides->values[index];
		zbx_vector_lld_override_operation_append(&override->override_operations, op);
	}

	zbx_vector_lld_override_operation_destroy(&ops);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	lld_overrides_compare_by_step(const void *override1, const void *override2)
{
	ZBX_RETURN_IF_NOT_EQUAL((*(zbx_lld_override_t **)override1)->step, (*(zbx_lld_override_t **)override2)->step);

	return 0;
}

static void	lld_dump_overrides(const zbx_vector_lld_override_ptr_t *overrides)
{
	for (int i = 0; i < overrides->values_num; i++)
	{
		zbx_lld_override_t	*override = overrides->values[i];

		zabbix_log(LOG_LEVEL_TRACE, "overrideid: " ZBX_FS_UI64, override->overrideid);
		zabbix_log(LOG_LEVEL_TRACE, "  step: %d", override->step);
		zabbix_log(LOG_LEVEL_TRACE, "  stop: %d", override->stop);

		for (int j = 0; j < override->override_operations.values_num; j++)
		{
			zbx_lld_override_operation_t	*override_operation = override->override_operations.values[j];

			zabbix_log(LOG_LEVEL_TRACE, "    override_operationid:" ZBX_FS_UI64,
					override_operation->override_operationid);
			zabbix_log(LOG_LEVEL_TRACE, "    operationobject: %d", override_operation->operationtype);
			zabbix_log(LOG_LEVEL_TRACE, "    operator: %d", override_operation->operator);
			zabbix_log(LOG_LEVEL_TRACE, "    value '%s'", override_operation->value);
			zabbix_log(LOG_LEVEL_TRACE, "    status: %d", override_operation->status);
			zabbix_log(LOG_LEVEL_TRACE, "    discover: %d", override_operation->discover);
			zabbix_log(LOG_LEVEL_TRACE, "    delay '%s'", ZBX_NULL2STR(override_operation->delay));
			zabbix_log(LOG_LEVEL_TRACE, "    history '%s'", ZBX_NULL2STR(override_operation->history));
			zabbix_log(LOG_LEVEL_TRACE, "    trends '%s'", ZBX_NULL2STR(override_operation->trends));
			zabbix_log(LOG_LEVEL_TRACE, "    inventory_mode: %d", (int)override_operation->inventory_mode);

			for (int k = 0; k < override_operation->tags.values_num; k++)
			{
				zabbix_log(LOG_LEVEL_TRACE, "    tag:'%s' value:'%s'",
						override_operation->tags.values[k]->tag,
						override_operation->tags.values[k]->value);
			}

			for (int k = 0; k < override_operation->templateids.values_num; k++)
			{
				zabbix_log(LOG_LEVEL_TRACE, "    templateid: " ZBX_FS_UI64,
						override_operation->templateids.values[k]);
			}
		}
	}
}

static int	lld_overrides_load(zbx_vector_lld_override_ptr_t *overrides, zbx_uint64_t lld_ruleid,
		const zbx_dc_item_t *item, char **error)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	overrideids;
	char			*sql = NULL;
	size_t			sql_alloc = 0;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&overrideids);

	zbx_db_begin();

	result = zbx_db_select(
			"select lld_overrideid,step,evaltype,formula,stop"
			" from lld_override"
			" where itemid=" ZBX_FS_UI64
			" order by lld_overrideid",
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_override_t	*override = (zbx_lld_override_t *)zbx_malloc(NULL, sizeof(zbx_lld_override_t));

		ZBX_STR2UINT64(override->overrideid, row[0]);
		override->step = atoi(row[1]);
		lld_filter_init(&override->filter);
		override->filter.evaltype = atoi(row[2]);
		override->filter.expression = zbx_strdup(NULL, row[3]);
		override->stop = (unsigned char)atoi(row[4]);

		zbx_vector_lld_override_operation_create(&override->override_operations);

		zbx_vector_lld_override_ptr_append(overrides, override);
		zbx_vector_uint64_append(&overrideids, override->overrideid);
	}

	zbx_db_free_result(result);

	if (0 != overrideids.values_num && SUCCEED == (ret = lld_override_conditions_load(overrides, &overrideids,
			&sql, &sql_alloc, item, error)))
	{
		lld_override_operations_load(overrides, &overrideids, &sql, &sql_alloc);
	}

	zbx_db_commit();
	zbx_free(sql);
	zbx_vector_uint64_destroy(&overrideids);

	zbx_vector_lld_override_ptr_sort(overrides, lld_overrides_compare_by_step);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		lld_dump_overrides(overrides);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	lld_override_free(zbx_lld_override_t *override)
{
	lld_filter_clean(&override->filter);

	zbx_vector_lld_override_operation_clear_ext(&override->override_operations, zbx_lld_override_operation_free);
	zbx_vector_lld_override_operation_destroy(&override->override_operations);
	zbx_free(override);
}

static int	regexp_strmatch_condition(const char *value, const char *pattern, unsigned char op)
{
	switch (op)
	{
		case ZBX_CONDITION_OPERATOR_REGEXP:
			if (NULL != zbx_regexp_match(value, pattern, NULL))
				return SUCCEED;
			break;
		case ZBX_CONDITION_OPERATOR_NOT_REGEXP:
			if (NULL == zbx_regexp_match(value, pattern, NULL))
				return SUCCEED;
			break;
		default:
			return zbx_strmatch_condition(value, pattern, op);
	}

	return FAIL;
}

void	lld_override_item(const zbx_vector_lld_override_ptr_t *overrides, const char *name, const char **delay,
		const char **history, const char **trends, zbx_vector_db_tag_ptr_t *override_tags,
		unsigned char *status, unsigned char *discover)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < overrides->values_num; i++)
	{
		const zbx_lld_override_t	*override = overrides->values[i];

		for (int j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation =
					override->override_operations.values[j];

			if (ZBX_LLD_OVERRIDE_OP_OBJECT_ITEM != override_operation->operationtype)
				continue;

			zabbix_log(LOG_LEVEL_TRACE, "%s() operationid:" ZBX_FS_UI64 " cond.value:'%s' name: '%s'",
					__func__, override_operation->override_operationid, override_operation->value,
					name);

			if (FAIL == regexp_strmatch_condition(name, override_operation->value,
					override_operation->operator))
			{
				zabbix_log(LOG_LEVEL_TRACE, "%s():FAIL", __func__);
				continue;
			}

			zabbix_log(LOG_LEVEL_TRACE, "%s():SUCCEED", __func__);

			if (NULL != override_operation->delay)
				*delay = override_operation->delay;

			if (NULL != override_operation->history)
				*history = override_operation->history;

			if (NULL != override_operation->trends)
				*trends = override_operation->trends;

			for (int k = 0; k < override_operation->tags.values_num; k++)
				zbx_vector_db_tag_ptr_append(override_tags, override_operation->tags.values[k]);

			if (NULL != status)
			{
				switch (override_operation->status)
				{
					case ZBX_PROTOTYPE_STATUS_ENABLED:
						*status = ITEM_STATUS_ACTIVE;
						break;
					case ZBX_PROTOTYPE_STATUS_DISABLED:
						*status = ITEM_STATUS_DISABLED;
						break;
					case ZBX_PROTOTYPE_STATUS_COUNT:
						break;
					default:
						THIS_SHOULD_NEVER_HAPPEN;
				}
			}

			if (ZBX_PROTOTYPE_DISCOVER_COUNT != override_operation->discover)
				*discover = override_operation->discover;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	lld_override_trigger(const zbx_vector_lld_override_ptr_t *overrides, const char *name, unsigned char *severity,
		zbx_vector_db_tag_ptr_t *override_tags, unsigned char *status, unsigned char *discover)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < overrides->values_num; i++)
	{
		const zbx_lld_override_t	*override = overrides->values[i];

		for (int j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation =
					override->override_operations.values[j];

			if (ZBX_LLD_OVERRIDE_OP_OBJECT_TRIGGER != override_operation->operationtype)
				continue;

			zabbix_log(LOG_LEVEL_TRACE, "%s() operationid:" ZBX_FS_UI64 " cond.value:'%s' name: '%s'",
					__func__, override_operation->override_operationid, override_operation->value,
					name);

			if (FAIL == regexp_strmatch_condition(name, override_operation->value,
					override_operation->operator))
			{
				zabbix_log(LOG_LEVEL_TRACE, "%s():FAIL", __func__);
				continue;
			}

			zabbix_log(LOG_LEVEL_TRACE, "%s():SUCCEED", __func__);

			if (TRIGGER_SEVERITY_COUNT != override_operation->severity)
				*severity = override_operation->severity;

			for (int k = 0; k < override_operation->tags.values_num; k++)
				zbx_vector_db_tag_ptr_append(override_tags, override_operation->tags.values[k]);

			if (NULL != status)
			{
				switch (override_operation->status)
				{
					case ZBX_PROTOTYPE_STATUS_ENABLED:
						*status = TRIGGER_STATUS_ENABLED;
						break;
					case ZBX_PROTOTYPE_STATUS_DISABLED:
						*status = TRIGGER_STATUS_DISABLED;
						break;
					case ZBX_PROTOTYPE_STATUS_COUNT:
						break;
					default:
						THIS_SHOULD_NEVER_HAPPEN;
				}
			}

			if (ZBX_PROTOTYPE_DISCOVER_COUNT != override_operation->discover)
				*discover = override_operation->discover;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	lld_override_host(const zbx_vector_lld_override_ptr_t *overrides, const char *name,
		zbx_vector_uint64_t *lnk_templateids, signed char *inventory_mode,
		zbx_vector_db_tag_ptr_t *override_tags, unsigned char *status, unsigned char *discover)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < overrides->values_num; i++)
	{
		const zbx_lld_override_t	*override = overrides->values[i];

		for (int j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation =
					override->override_operations.values[j];

			if (ZBX_LLD_OVERRIDE_OP_OBJECT_HOST != override_operation->operationtype)
				continue;

			zabbix_log(LOG_LEVEL_TRACE, "%s() operationid:" ZBX_FS_UI64 " cond.value:'%s' name: '%s'",
					__func__, override_operation->override_operationid, override_operation->value,
					name);

			if (FAIL == regexp_strmatch_condition(name, override_operation->value,
					override_operation->operator))
			{
				zabbix_log(LOG_LEVEL_TRACE, "%s():FAIL", __func__);
				continue;
			}

			zabbix_log(LOG_LEVEL_TRACE, "%s():SUCCEED", __func__);

			for (int k = 0; k < override_operation->templateids.values_num; k++)
				zbx_vector_uint64_append(lnk_templateids, override_operation->templateids.values[k]);

			if (HOST_INVENTORY_COUNT != override_operation->inventory_mode)
				*inventory_mode = override_operation->inventory_mode;

			for (int k = 0; k < override_operation->tags.values_num; k++)
				zbx_vector_db_tag_ptr_append(override_tags, override_operation->tags.values[k]);

			if (NULL != status)
			{
				switch (override_operation->status)
				{
					case ZBX_PROTOTYPE_STATUS_ENABLED:
						*status = HOST_STATUS_MONITORED;
						break;
					case ZBX_PROTOTYPE_STATUS_DISABLED:
						*status = HOST_STATUS_NOT_MONITORED;
						break;
					case ZBX_PROTOTYPE_STATUS_COUNT:
						break;
					default:
						THIS_SHOULD_NEVER_HAPPEN;
				}
			}

			if (ZBX_PROTOTYPE_DISCOVER_COUNT != override_operation->discover)
				*discover = override_operation->discover;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	lld_override_graph(const zbx_vector_lld_override_ptr_t *overrides, const char *name, unsigned char *discover)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < overrides->values_num; i++)
	{
		const zbx_lld_override_t	*override = overrides->values[i];

		for (int j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation =
					override->override_operations.values[j];

			if (ZBX_LLD_OVERRIDE_OP_OBJECT_GRAPH != override_operation->operationtype)
				continue;

			zabbix_log(LOG_LEVEL_TRACE, "%s() operationid:" ZBX_FS_UI64 " cond.value:'%s' name: '%s'",
					__func__, override_operation->override_operationid, override_operation->value,
					name);

			if (FAIL == regexp_strmatch_condition(name, override_operation->value,
					override_operation->operator))
			{
				zabbix_log(LOG_LEVEL_TRACE, "%s():FAIL", __func__);
				continue;
			}

			zabbix_log(LOG_LEVEL_TRACE, "%s():SUCCEED", __func__);

			if (ZBX_PROTOTYPE_DISCOVER_COUNT != override_operation->discover)
				*discover = override_operation->discover;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

int	lld_validate_item_override_no_discover(const zbx_vector_lld_override_ptr_t *overrides, const char *name,
		unsigned char override_default)
{
	for (int i = 0; i < overrides->values_num; i++)
	{
		const zbx_lld_override_t	*override = overrides->values[i];

		for (int j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation =
					override->override_operations.values[j];

			if (ZBX_LLD_OVERRIDE_OP_OBJECT_ITEM == override_operation->operationtype &&
					SUCCEED == regexp_strmatch_condition(name, override_operation->value,
					override_operation->operator))
			{
				return ZBX_PROTOTYPE_NO_DISCOVER == override_operation->discover ? FAIL : SUCCEED;
			}
		}
	}

	return ZBX_PROTOTYPE_NO_DISCOVER == override_default ? FAIL : SUCCEED;
}

static int	lld_rows_get(const char *value, zbx_lld_filter_t *filter, zbx_vector_lld_row_ptr_t *lld_rows,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, const zbx_vector_lld_override_ptr_t *overrides,
		char **info, char **error)
{
	struct zbx_json_parse	jp, jp_array, jp_row;
	const char		*p;
	zbx_lld_row_t		*lld_row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_json_open(value, &jp))
	{
		*error = zbx_dsprintf(*error, "Invalid discovery rule value: %s", zbx_json_strerror());
		goto out;
	}

	if ('[' == *jp.start)
	{
		jp_array = jp;
	}
	else if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_array))	/* deprecated */
	{
		*error = zbx_dsprintf(*error, "Cannot find the \"%s\" array in the received JSON object.",
				ZBX_PROTO_TAG_DATA);
		goto out;
	}

	p = NULL;
	while (NULL != (p = zbx_json_next(&jp_array, p)))
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_row))
			continue;

		if (SUCCEED != filter_evaluate(filter, &jp_row, lld_macro_paths, info))
			continue;

		lld_row = (zbx_lld_row_t *)zbx_malloc(NULL, sizeof(zbx_lld_row_t));
		zbx_vector_lld_row_ptr_append(lld_rows, lld_row);

		lld_row->jp_row = jp_row;
		zbx_vector_lld_item_link_ptr_create(&lld_row->item_links);
		zbx_vector_lld_override_ptr_create(&lld_row->overrides);

#define OVERRIDE_STOP_TRUE	1

		for (int i = 0; i < overrides->values_num; i++)
		{
			zbx_lld_override_t	*override = overrides->values[i];

			if (SUCCEED != filter_evaluate(&override->filter, &jp_row, lld_macro_paths, info))
				continue;

			zbx_vector_lld_override_ptr_append(&lld_row->overrides, override);

			if (OVERRIDE_STOP_TRUE == override->stop)
				break;
		}

#undef OVERRIDE_STOP_TRUE
	}

	ret = SUCCEED;
out:
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		for (int i = 0; i < lld_rows->values_num; i++)
		{
			lld_row = lld_rows->values[i];

			zabbix_log(LOG_LEVEL_TRACE, "lld_row '%.*s' overrides:",
					(int)(lld_row->jp_row.end - lld_row->jp_row.start + 1),
					lld_row->jp_row.start);

			for (int j = 0; j < lld_row->overrides.values_num; j++)
			{
				zabbix_log(LOG_LEVEL_TRACE, "  lld_overrideid: " ZBX_FS_UI64,
						*(const zbx_uint64_t *)lld_row->overrides.values[j]);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	lld_item_link_free(zbx_lld_item_link_t *item_link)
{
	zbx_free(item_link);
}

static void	lld_row_free(zbx_lld_row_t *lld_row)
{
	zbx_vector_lld_item_link_ptr_clear_ext(&lld_row->item_links, lld_item_link_free);
	zbx_vector_lld_item_link_ptr_destroy(&lld_row->item_links);
	zbx_vector_lld_override_ptr_destroy(&lld_row->overrides);
	zbx_free(lld_row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds or updates items, triggers and graphs for discovery item     *
 *                                                                            *
 * Parameters: lld_ruleid - [IN] discovery rule id from database              *
 *             value      - [IN] received value from agent                    *
 *             error      - [OUT] Error or informational message. Will be set *
 *                               to empty string on successful discovery      *
 *                               without additional information.              *
 *                                                                            *
 ******************************************************************************/
int	lld_process_discovery_rule(zbx_uint64_t lld_ruleid, const char *value, char **error)
{
#define LIFETIME_DURATION_GET(lt, lt_str)									\
	do													\
	{													\
		char	*lt_res;										\
														\
		if (ZBX_LLD_LIFETIME_TYPE_AFTER != lt.type)							\
			break;											\
														\
		lt_res = zbx_strdup(NULL, lt_str);								\
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL, NULL, NULL, NULL,	\
				NULL, NULL, &lt_res, ZBX_MACRO_TYPE_COMMON, NULL, 0);				\
														\
		if (SUCCEED != zbx_is_time_suffix(lt_res, &lt.duration, ZBX_LENGTH_UNLIMITED))			\
		{												\
			zabbix_log(LOG_LEVEL_WARNING, "cannot process lost resources for the discovery rule "	\
					" \"%s:%s\": \"%s\" is not a valid value", zbx_host_string(hostid),	\
					discovery_key, lt_res);							\
			lt.duration = 25 * SEC_PER_YEAR;	/* max value for the field */			\
		}												\
		zbx_free(lt_res);										\
	}													\
	while(0)

	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_uint64_t			hostid;
	char				*discovery_key = NULL, *info = NULL;
	int				errcode, ret = SUCCEED;
	zbx_vector_lld_macro_path_ptr_t	lld_macro_paths;
	zbx_lld_filter_t		filter;
	zbx_lld_lifetime_t		lifetime, enabled_lifetime;
	time_t				now;
	zbx_dc_item_t			item;
	zbx_config_t			cfg;
	zbx_dc_um_handle_t		*um_handle;
	zbx_vector_lld_override_ptr_t	overrides;
	zbx_vector_lld_row_ptr_t	lld_rows;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64, __func__, lld_ruleid);

	um_handle = zbx_dc_open_user_macros();

	zbx_vector_lld_row_ptr_create(&lld_rows);
	zbx_vector_lld_macro_path_ptr_create(&lld_macro_paths);
	zbx_vector_lld_override_ptr_create(&overrides);

	lld_filter_init(&filter);

	zbx_dc_config_get_items_by_itemids(&item, &lld_ruleid, &errcode, 1);

	if (SUCCEED != errcode)
	{
		*error = zbx_dsprintf(*error, "Invalid discovery rule ID [" ZBX_FS_UI64 "].", lld_ruleid);
		ret = FAIL;
		goto out;
	}

	result = zbx_db_select(
			"select hostid,key_,evaltype,formula,lifetime_type,lifetime,enabled_lifetime_type,"
				"enabled_lifetime"
			" from items"
			" where itemid=" ZBX_FS_UI64,
			lld_ruleid);

	if (NULL != (row = zbx_db_fetch(result)))
	{

		ZBX_STR2UINT64(hostid, row[0]);
		discovery_key = zbx_strdup(discovery_key, row[1]);
		filter.evaltype = atoi(row[2]);
		filter.expression = zbx_strdup(NULL, row[3]);
		ZBX_STR2UCHAR(lifetime.type, row[4]);
		LIFETIME_DURATION_GET(lifetime, row[5]);
		ZBX_STR2UCHAR(enabled_lifetime.type, row[6]);
		LIFETIME_DURATION_GET(enabled_lifetime, row[7]);
	}
	zbx_db_free_result(result);

	if (NULL == row)
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid discovery rule ID [" ZBX_FS_UI64 "]", lld_ruleid);
		goto out;
	}

	if (SUCCEED != lld_filter_load(&filter, lld_ruleid, &item, error))
	{
		ret = FAIL;
		goto out;
	}

	if (SUCCEED != zbx_lld_macro_paths_get(lld_ruleid, &lld_macro_paths, error))
	{
		ret = FAIL;
		goto out;
	}

	if (SUCCEED != (ret = lld_overrides_load(&overrides, lld_ruleid, &item, error)))
		goto out;

	if (SUCCEED != lld_rows_get(value, &filter, &lld_rows, &lld_macro_paths, &overrides, &info, error))
	{
		ret = FAIL;
		goto out;
	}

	*error = zbx_strdup(*error, "");

	now = time(NULL);

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED | ZBX_CONFIG_FLAGS_AUDITLOG_MODE);
	zbx_audit_init(cfg.auditlog_enabled, cfg.auditlog_mode, ZBX_AUDIT_LLD_CONTEXT);

	if (SUCCEED != lld_update_items(hostid, lld_ruleid, &lld_rows, &lld_macro_paths, error, &lifetime,
			&enabled_lifetime, now))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add items because parent host was removed while"
				" processing lld rule");
		goto out;
	}

	lld_item_links_sort(&lld_rows);

	if (SUCCEED != lld_update_triggers(hostid, lld_ruleid, &lld_rows, &lld_macro_paths, error, &lifetime,
			&enabled_lifetime, now))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add triggers because parent host was removed while"
				" processing lld rule");
		goto out;
	}

	if (SUCCEED != lld_update_graphs(hostid, lld_ruleid, &lld_rows, &lld_macro_paths, error, &lifetime, now))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add graphs because parent host was removed while"
				" processing lld rule");
		goto out;
	}

	lld_update_hosts(lld_ruleid, &lld_rows, &lld_macro_paths, error, &lifetime, &enabled_lifetime, now);

	/* add informative warning to the error message about lack of data for macros used in filter */
	if (NULL != info)
		*error = zbx_strdcat(*error, info);
out:
	zbx_audit_flush(ZBX_AUDIT_LLD_CONTEXT);
	zbx_dc_config_clean_items(&item, &errcode, 1);
	zbx_free(info);
	zbx_free(discovery_key);

	lld_filter_clean(&filter);

	zbx_vector_lld_override_ptr_clear_ext(&overrides, lld_override_free);
	zbx_vector_lld_override_ptr_destroy(&overrides);
	zbx_vector_lld_row_ptr_clear_ext(&lld_rows, lld_row_free);
	zbx_vector_lld_row_ptr_destroy(&lld_rows);
	zbx_vector_lld_macro_path_ptr_clear_ext(&lld_macro_paths, zbx_lld_macro_path_free);
	zbx_vector_lld_macro_path_ptr_destroy(&lld_macro_paths);

	zbx_dc_close_user_macros(um_handle);

#ifdef	HAVE_MALLOC_TRIM
	malloc_trim(128 * ZBX_MEBIBYTE);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
#undef LIFETIME_DURATION_GET
}
