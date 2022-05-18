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

#include "lld.h"
#include "proxy.h"

#include "log.h"
#include "zbxserver.h"
#include "zbxregexp.h"

#include "../../libs/zbxaudit/audit.h"

#define OVERRIDE_STOP_TRUE	1

/* lld rule filter condition (item_condition table record) */
typedef struct
{
	zbx_uint64_t		id;
	char			*macro;
	char			*regexp;
	zbx_vector_ptr_t	regexps;
	unsigned char		op;
}
lld_condition_t;

/* lld rule filter */
typedef struct
{
	zbx_vector_ptr_t	conditions;
	char			*expression;
	int			evaltype;
}
lld_filter_t;

/* lld rule override */
typedef struct
{
	zbx_uint64_t		overrideid;
	lld_filter_t		filter;
	zbx_vector_ptr_t	override_operations;
	int			step;
	unsigned char		stop;
}
lld_override_t;

/******************************************************************************
 *                                                                            *
 * Purpose: release resources allocated by filter condition                   *
 *                                                                            *
 * Parameters: condition  - [IN] the filter condition                         *
 *                                                                            *
 ******************************************************************************/
static void	lld_condition_free(lld_condition_t *condition)
{
	zbx_regexp_clean_expressions(&condition->regexps);
	zbx_vector_ptr_destroy(&condition->regexps);

	zbx_free(condition->macro);
	zbx_free(condition->regexp);
	zbx_free(condition);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release resources allocated by filter conditions                  *
 *                                                                            *
 * Parameters: conditions - [IN] the filter conditions                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_conditions_free(zbx_vector_ptr_t *conditions)
{
	zbx_vector_ptr_clear_ext(conditions, (zbx_clean_func_t)lld_condition_free);
	zbx_vector_ptr_destroy(conditions);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two filter conditions by their macros                     *
 *                                                                            *
 * Parameters: item1  - [IN] the first filter condition                       *
 *             item2  - [IN] the second filter condition                      *
 *                                                                            *
 ******************************************************************************/
static int	lld_condition_compare_by_macro(const void *item1, const void *item2)
{
	lld_condition_t	*condition1 = *(lld_condition_t **)item1;
	lld_condition_t	*condition2 = *(lld_condition_t **)item2;

	return strcmp(condition1->macro, condition2->macro);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes lld filter                                            *
 *                                                                            *
 * Parameters: filter  - [IN] the lld filter                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_filter_init(lld_filter_t *filter)
{
	zbx_vector_ptr_create(&filter->conditions);
	filter->expression = NULL;
	filter->evaltype = CONDITION_EVAL_TYPE_AND_OR;
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by lld filter                        *
 *                                                                            *
 * Parameters: filter  - [IN] the lld filter                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_filter_clean(lld_filter_t *filter)
{
	zbx_free(filter->expression);
	lld_conditions_free(&filter->conditions);
}

static int	lld_filter_condition_add(zbx_vector_ptr_t *conditions, const char *id, const char *macro,
		const char *regexp, const char *op, const DC_ITEM *item, char **error)
{
	lld_condition_t	*condition;

	condition = (lld_condition_t *)zbx_malloc(NULL, sizeof(lld_condition_t));
	ZBX_STR2UINT64(condition->id, id);
	condition->macro = zbx_strdup(NULL, macro);
	condition->regexp = zbx_strdup(NULL, regexp);
	condition->op = (unsigned char)atoi(op);

	zbx_vector_ptr_create(&condition->regexps);

	zbx_vector_ptr_append(conditions, condition);

	if ('@' == *condition->regexp)
	{
		DCget_expressions_by_name(&condition->regexps, condition->regexp + 1);

		if (0 == condition->regexps.values_num)
		{
			*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.",
					condition->regexp + 1);
			return FAIL;
		}
	}
	else
	{
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, item, NULL, NULL, NULL, NULL, NULL,
				&condition->regexp, MACRO_TYPE_LLD_FILTER, NULL, 0);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: loads lld filter data                                             *
 *                                                                            *
 * Parameters: filter     - [IN] the lld filter                               *
 *             lld_ruleid - [IN] the lld rule id                              *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 ******************************************************************************/
static int	lld_filter_load(lld_filter_t *filter, zbx_uint64_t lld_ruleid, const DC_ITEM *item, char **error)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select item_conditionid,macro,value,operator"
			" from item_condition"
			" where itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)) && SUCCEED == (ret = lld_filter_condition_add(&filter->conditions,
			row[0], row[1], row[2], row[3], item, error)))
		;
	DBfree_result(result);

	if (CONDITION_EVAL_TYPE_AND_OR == filter->evaltype)
		zbx_vector_ptr_sort(&filter->conditions, lld_condition_compare_by_macro);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the lld data passes filter evaluation                    *
 *                                                                            *
 * Parameters: jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             condition       - [IN] the lld filter condition                *
 *             info            - [OUT] the warning description                *
 *                                                                            *
 * Return value: SUCCEED - the lld data passed filter evaluation              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	filter_condition_match(const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths,
		const lld_condition_t *condition, char **info)
{
	char	*value = NULL;
	int	ret;

	if (SUCCEED == (ret = zbx_lld_macro_value_by_name(jp_row, lld_macro_paths, condition->macro, &value)))
	{
		if (CONDITION_OPERATOR_NOT_EXIST == condition->op)
		{
			ret = FAIL;
		}
		else if (CONDITION_OPERATOR_EXIST != condition->op)
		{
			switch (regexp_match_ex(&condition->regexps, value, condition->regexp, ZBX_CASE_SENSITIVE))
			{
				case ZBX_REGEXP_MATCH:
					ret = (CONDITION_OPERATOR_REGEXP == condition->op ? SUCCEED : FAIL);
					break;
				case ZBX_REGEXP_NO_MATCH:
					ret = (CONDITION_OPERATOR_NOT_REGEXP == condition->op ? SUCCEED : FAIL);
					break;
				default:
					ret = FAIL;
			}
		}
	}
	else if (CONDITION_OPERATOR_NOT_EXIST == condition->op)
	{
		ret = SUCCEED;
	}
	else if (CONDITION_OPERATOR_EXIST != condition->op)
	{
		*info = zbx_strdcatf(*info,
				"Cannot accurately apply filter: no value received for macro \"%s\".\n",
				condition->macro);
	}

	zbx_free(value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the lld data passes filter evaluation by and/or rule     *
 *                                                                            *
 * Parameters: filter          - [IN] the lld filter                          *
 *             jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             info            - [OUT] the warning description                *
 *                                                                            *
 * Return value: SUCCEED - the lld data passed filter evaluation              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	filter_evaluate_and_or(const lld_filter_t *filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char **info)
{
	int	i, ret = SUCCEED;
	char	*lastmacro = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < filter->conditions.values_num; i++)
	{
		int			rc;
		const lld_condition_t	*condition = (lld_condition_t *)filter->conditions.values[i];

		rc = filter_condition_match(jp_row, lld_macro_paths, condition, info);
		/* check if a new condition group has started */
		if (NULL == lastmacro || 0 != strcmp(lastmacro, condition->macro))
		{
			/* if any of condition groups are false the evaluation returns false */
			if (FAIL == ret)
				break;

			ret = rc;
		}
		else
		{
			if (SUCCEED == rc)
				ret = rc;
		}

		lastmacro = condition->macro;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the lld data passes filter evaluation by and rule        *
 *                                                                            *
 * Parameters: filter          - [IN] the lld filter                          *
 *             jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             info            - [OUT] the warning description                *
 *                                                                            *
 * Return value: SUCCEED - the lld data passed filter evaluation              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	filter_evaluate_and(const lld_filter_t *filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char **info)
{
	int	i, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < filter->conditions.values_num; i++)
	{
		/* if any of conditions are false the evaluation returns false */
		if (SUCCEED != (ret = filter_condition_match(jp_row, lld_macro_paths,
				(lld_condition_t *)filter->conditions.values[i], info)))
		{
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the lld data passes filter evaluation by or rule         *
 *                                                                            *
 * Parameters: filter          - [IN] the lld filter                          *
 *             jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             info            - [OUT] the warning description                *
 *                                                                            *
 * Return value: SUCCEED - the lld data passed filter evaluation              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	filter_evaluate_or(const lld_filter_t *filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char **info)
{
	int	i, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < filter->conditions.values_num; i++)
	{
		/* if any of conditions are true the evaluation returns true */
		if (SUCCEED == (ret = filter_condition_match(jp_row, lld_macro_paths,
				(lld_condition_t *)filter->conditions.values[i], info)))
		{
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the lld data passes filter evaluation by custom          *
 *          expression                                                        *
 *                                                                            *
 * Parameters: filter          - [IN] the lld filter                          *
 *             jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             info            - [OUT] the warning description                *
 *                                                                            *
 * Return value: SUCCEED - the lld data passed filter evaluation              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: 1) replace {item_condition} references with action condition     *
 *              evaluation results (1 or 0)                                   *
 *           2) call evaluate() to calculate the final result                 *
 *                                                                            *
 ******************************************************************************/
static int	filter_evaluate_expression(const lld_filter_t *filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char **info)
{
	int	i, ret = FAIL, id_len;
	char	*expression, id[ZBX_MAX_UINT64_LEN + 2], *p, error[256];
	double	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:%s", __func__, filter->expression);

	expression = zbx_strdup(NULL, filter->expression);

	for (i = 0; i < filter->conditions.values_num; i++)
	{
		const lld_condition_t	*condition = (lld_condition_t *)filter->conditions.values[i];

		ret = filter_condition_match(jp_row, lld_macro_paths, condition, info);

		zbx_snprintf(id, sizeof(id), "{" ZBX_FS_UI64 "}", condition->id);

		id_len = strlen(id);
		p = expression;

		while (NULL != (p = strstr(p, id)))
		{
			*p = (SUCCEED == ret ? '1' : '0');
			memset(p + 1, ' ', id_len - 1);
			p += id_len;
		}
	}

	if (SUCCEED == evaluate(&result, expression, error, sizeof(error), NULL))
		ret = (SUCCEED != zbx_double_compare(result, 0) ? SUCCEED : FAIL);

	zbx_free(expression);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the lld data passes filter evaluation                    *
 *                                                                            *
 * Parameters: filter          - [IN] the lld filter                          *
 *             jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             info            - [OUT] the warning description                *
 *                                                                            *
 * Return value: SUCCEED - the lld data passed filter evaluation              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	filter_evaluate(const lld_filter_t *filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char **info)
{
	switch (filter->evaltype)
	{
		case CONDITION_EVAL_TYPE_AND_OR:
			return filter_evaluate_and_or(filter, jp_row, lld_macro_paths, info);
		case CONDITION_EVAL_TYPE_AND:
			return filter_evaluate_and(filter, jp_row, lld_macro_paths, info);
		case CONDITION_EVAL_TYPE_OR:
			return filter_evaluate_or(filter, jp_row, lld_macro_paths, info);
		case CONDITION_EVAL_TYPE_EXPRESSION:
			return filter_evaluate_expression(filter, jp_row, lld_macro_paths, info);
	}

	return FAIL;
}

static int	lld_override_conditions_load(zbx_vector_ptr_t *overrides, const zbx_vector_uint64_t *overrideids,
		char **sql, size_t *sql_alloc, const DC_ITEM *item, char **error)
{
	size_t		sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;
	lld_override_t	*override;
	int		ret = SUCCEED, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset,
			"select lld_overrideid,lld_override_conditionid,macro,value,operator"
			" from lld_override_condition"
			" where");
	DBadd_condition_alloc(sql, sql_alloc, &sql_offset, "lld_overrideid", overrideids->values,
			overrideids->values_num);

	result = DBselect("%s", *sql);
	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	overrideid;

		ZBX_STR2UINT64(overrideid, row[0]);
		if (FAIL == (i = zbx_vector_ptr_bsearch(overrides, &overrideid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		override = (lld_override_t *)overrides->values[i];
		if (FAIL == (ret = lld_filter_condition_add(&override->filter.conditions, row[1], row[2], row[3],
				row[4], item, error)))
		{
			break;
		}
	}
	DBfree_result(result);

	for (i = 0; i < overrides->values_num; i++)
	{
		override = (lld_override_t *)overrides->values[i];

		if (CONDITION_EVAL_TYPE_AND_OR == override->filter.evaltype)
			zbx_vector_ptr_sort(&override->filter.conditions, lld_condition_compare_by_macro);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	lld_override_operations_load(zbx_vector_ptr_t *overrides, const zbx_vector_uint64_t *overrideids,
		char **sql, size_t *sql_alloc)
{
	lld_override_t			*override;
	zbx_lld_override_operation_t	*op;
	zbx_vector_ptr_t		ops;
	int				i, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&ops);

	zbx_load_lld_override_operations(overrideids, sql, sql_alloc, &ops);

	for (i = 0; i < ops.values_num; i++)
	{
		op = (zbx_lld_override_operation_t *)ops.values[i];
		if (FAIL == (index = zbx_vector_ptr_bsearch(overrides, &op->overrideid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			zbx_lld_override_operation_free(op);
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}
		override = (lld_override_t *)overrides->values[index];
		zbx_vector_ptr_append(&override->override_operations, op);
	}

	zbx_vector_ptr_destroy(&ops);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	lld_overrides_compare_by_step(const void *override1, const void *override2)
{
	ZBX_RETURN_IF_NOT_EQUAL((*(lld_override_t **)override1)->step, (*(lld_override_t **)override2)->step);

	return 0;
}

static void	lld_dump_overrides(const zbx_vector_ptr_t *overrides)
{
	int			i;
	lld_override_t		*override;

	for (i = 0; i < overrides->values_num; i++)
	{
		int	j;

		override = (lld_override_t *)overrides->values[i];

		zabbix_log(LOG_LEVEL_TRACE, "overrideid: " ZBX_FS_UI64, override->overrideid);
		zabbix_log(LOG_LEVEL_TRACE, "  step: %d", override->step);
		zabbix_log(LOG_LEVEL_TRACE, "  stop: %d", override->stop);

		for (j = 0; j < override->override_operations.values_num; j++)
		{
			zbx_lld_override_operation_t	*override_operation;
			int				k;

			override_operation = (zbx_lld_override_operation_t *)override->override_operations.values[j];

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
			for (k = 0; k < override_operation->tags.values_num; k++)
			{
				zabbix_log(LOG_LEVEL_TRACE, "    tag:'%s' value:'%s'",
						override_operation->tags.values[k]->tag,
						override_operation->tags.values[k]->value);
			}

			for (k = 0; k < override_operation->templateids.values_num; k++)
			{
				zabbix_log(LOG_LEVEL_TRACE, "    templateid: " ZBX_FS_UI64,
						override_operation->templateids.values[k]);
			}
		}
	}
}

static int	lld_overrides_load(zbx_vector_ptr_t *overrides, zbx_uint64_t lld_ruleid, const DC_ITEM *item,
		char **error)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	overrideids;
	char			*sql = NULL;
	size_t			sql_alloc = 0;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&overrideids);

	DBbegin();

	result = DBselect(
			"select lld_overrideid,step,evaltype,formula,stop"
			" from lld_override"
			" where itemid=" ZBX_FS_UI64
			" order by lld_overrideid",
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		lld_override_t	*override;

		override = (lld_override_t *)zbx_malloc(NULL, sizeof(lld_override_t));

		ZBX_STR2UINT64(override->overrideid, row[0]);
		override->step = atoi(row[1]);
		lld_filter_init(&override->filter);
		override->filter.evaltype = atoi(row[2]);
		override->filter.expression = zbx_strdup(NULL, row[3]);
		override->stop = (unsigned char)atoi(row[4]);

		zbx_vector_ptr_create(&override->override_operations);

		zbx_vector_ptr_append(overrides, override);
		zbx_vector_uint64_append(&overrideids, override->overrideid);
	}
	DBfree_result(result);

	if (0 != overrideids.values_num && SUCCEED == (ret = lld_override_conditions_load(overrides, &overrideids,
			&sql, &sql_alloc, item, error)))
	{
		lld_override_operations_load(overrides, &overrideids, &sql, &sql_alloc);
	}

	DBcommit();
	zbx_free(sql);
	zbx_vector_uint64_destroy(&overrideids);

	zbx_vector_ptr_sort(overrides, lld_overrides_compare_by_step);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		lld_dump_overrides(overrides);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	lld_override_free(lld_override_t *override)
{
	lld_filter_clean(&override->filter);

	zbx_vector_ptr_clear_ext(&override->override_operations, (zbx_clean_func_t)zbx_lld_override_operation_free);
	zbx_vector_ptr_destroy(&override->override_operations);
	zbx_free(override);
}

static int	regexp_strmatch_condition(const char *value, const char *pattern, unsigned char op)
{
	switch (op)
	{
		case CONDITION_OPERATOR_REGEXP:
			if (NULL != zbx_regexp_match(value, pattern, NULL))
				return SUCCEED;
			break;
		case CONDITION_OPERATOR_NOT_REGEXP:
			if (NULL == zbx_regexp_match(value, pattern, NULL))
				return SUCCEED;
			break;
		default:
			return zbx_strmatch_condition(value, pattern, op);
	}

	return FAIL;
}

void	lld_override_item(const zbx_vector_ptr_t *overrides, const char *name, const char **delay,
		const char **history, const char **trends, zbx_vector_db_tag_ptr_t *override_tags,
		unsigned char *status, unsigned char *discover)
{
	int	i, j, k;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < overrides->values_num; i++)
	{
		const lld_override_t	*override;

		override = (const lld_override_t *)overrides->values[i];

		for (j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation;

			override_operation = (const zbx_lld_override_operation_t *)override->override_operations.values[j];

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

			for (k = 0; k < override_operation->tags.values_num; k++)
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

void	lld_override_trigger(const zbx_vector_ptr_t *overrides, const char *name, unsigned char *severity,
		zbx_vector_db_tag_ptr_t *override_tags, unsigned char *status, unsigned char *discover)
{
	int	i, j, k;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < overrides->values_num; i++)
	{
		const lld_override_t	*override;

		override = (const lld_override_t *)overrides->values[i];

		for (j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation;

			override_operation = (const zbx_lld_override_operation_t *)override->override_operations.values[j];

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

			for (k = 0; k < override_operation->tags.values_num; k++)
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

void	lld_override_host(const zbx_vector_ptr_t *overrides, const char *name, zbx_vector_uint64_t *lnk_templateids,
		signed char *inventory_mode, zbx_vector_db_tag_ptr_t *override_tags, unsigned char *status,
		unsigned char *discover)
{
	int	i, j, k;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < overrides->values_num; i++)
	{
		const lld_override_t	*override;

		override = (const lld_override_t *)overrides->values[i];

		for (j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation;

			override_operation = (const zbx_lld_override_operation_t *)override->override_operations.values[j];

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

			for (k = 0; k < override_operation->templateids.values_num; k++)
				zbx_vector_uint64_append(lnk_templateids, override_operation->templateids.values[k]);

			if (HOST_INVENTORY_COUNT != override_operation->inventory_mode)
				*inventory_mode = override_operation->inventory_mode;

			for (k = 0; k < override_operation->tags.values_num; k++)
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

void	lld_override_graph(const zbx_vector_ptr_t *overrides, const char *name,	unsigned char *discover)
{
	int	i, j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < overrides->values_num; i++)
	{
		const lld_override_t	*override;

		override = (const lld_override_t *)overrides->values[i];

		for (j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation;

			override_operation = (const zbx_lld_override_operation_t *)override->override_operations.values[j];

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

int	lld_validate_item_override_no_discover(const zbx_vector_ptr_t *overrides, const char *name,
		unsigned char override_default)
{
	int	i, j;

	for (i = 0; i < overrides->values_num; i++)
	{
		const lld_override_t	*override;

		override = (const lld_override_t *)overrides->values[i];

		for (j = 0; j < override->override_operations.values_num; j++)
		{
			const zbx_lld_override_operation_t	*override_operation;

			override_operation = (const zbx_lld_override_operation_t *)override->override_operations.values[j];

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

static int	lld_rows_get(const char *value, lld_filter_t *filter, zbx_vector_ptr_t *lld_rows,
		const zbx_vector_ptr_t *lld_macro_paths, const zbx_vector_ptr_t	*overrides, char **info, char **error)
{
	struct zbx_json_parse	jp, jp_array, jp_row;
	const char		*p;
	zbx_lld_row_t		*lld_row;
	int			ret = FAIL, i;

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
		zbx_vector_ptr_append(lld_rows, lld_row);

		lld_row->jp_row = jp_row;
		zbx_vector_ptr_create(&lld_row->item_links);
		zbx_vector_ptr_create(&lld_row->overrides);

		for (i = 0; i < overrides->values_num; i++)
		{
			lld_override_t	*override;

			override = (lld_override_t *)overrides->values[i];

			if (SUCCEED != filter_evaluate(&override->filter, &jp_row, lld_macro_paths, info))
				continue;

			zbx_vector_ptr_append(&lld_row->overrides, override);

			if (OVERRIDE_STOP_TRUE == override->stop)
				break;
		}
	}

	ret = SUCCEED;
out:
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		for (i = 0; i < lld_rows->values_num; i++)
		{
			int	j;

			lld_row = (zbx_lld_row_t *)lld_rows->values[i];

			zabbix_log(LOG_LEVEL_TRACE, "lld_row '%.*s' overrides:",
					(int)(lld_row->jp_row.end - lld_row->jp_row.start + 1),
					lld_row->jp_row.start);

			for (j = 0; j < lld_row->overrides.values_num; j++)
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
	zbx_vector_ptr_clear_ext(&lld_row->item_links, (zbx_clean_func_t)lld_item_link_free);
	zbx_vector_ptr_destroy(&lld_row->item_links);
	zbx_vector_ptr_destroy(&lld_row->overrides);
	zbx_free(lld_row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add or update items, triggers and graphs for discovery item       *
 *                                                                            *
 * Parameters: lld_ruleid - [IN] discovery item identifier from database      *
 *             value      - [IN] received value from agent                    *
 *             error      - [OUT] error or informational message. Will be set *
 *                               to empty string on successful discovery      *
 *                               without additional information.              *
 *                                                                            *
 ******************************************************************************/
int	lld_process_discovery_rule(zbx_uint64_t lld_ruleid, const char *value, char **error)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		hostid;
	char			*discovery_key = NULL, *info = NULL;
	int			lifetime, ret = SUCCEED, errcode;
	zbx_vector_ptr_t	lld_rows, lld_macro_paths, overrides;
	lld_filter_t		filter;
	time_t			now;
	DC_ITEM			item;
	zbx_config_t		cfg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64, __func__, lld_ruleid);

	zbx_vector_ptr_create(&lld_rows);
	zbx_vector_ptr_create(&lld_macro_paths);
	zbx_vector_ptr_create(&overrides);

	lld_filter_init(&filter);

	DCconfig_get_items_by_itemids(&item, &lld_ruleid, &errcode, 1);

	if (SUCCEED != errcode)
	{
		*error = zbx_dsprintf(*error, "Invalid discovery rule ID [" ZBX_FS_UI64 "].", lld_ruleid);
		ret = FAIL;
		goto out;
	}

	result = DBselect(
			"select hostid,key_,evaltype,formula,lifetime"
			" from items"
			" where itemid=" ZBX_FS_UI64,
			lld_ruleid);

	if (NULL != (row = DBfetch(result)))
	{
		char	*lifetime_str;

		ZBX_STR2UINT64(hostid, row[0]);
		discovery_key = zbx_strdup(discovery_key, row[1]);
		filter.evaltype = atoi(row[2]);
		filter.expression = zbx_strdup(NULL, row[3]);
		lifetime_str = zbx_strdup(NULL, row[4]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&lifetime_str, MACRO_TYPE_COMMON, NULL, 0);

		if (SUCCEED != is_time_suffix(lifetime_str, &lifetime, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot process lost resources for the discovery rule \"%s:%s\":"
					" \"%s\" is not a valid value",
					zbx_host_string(hostid), discovery_key, lifetime_str);
			lifetime = 25 * SEC_PER_YEAR;	/* max value for the field */
		}

		zbx_free(lifetime_str);
	}
	DBfree_result(result);

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

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED);
	zbx_audit_init(cfg.auditlog_enabled);

	if (SUCCEED != lld_update_items(hostid, lld_ruleid, &lld_rows, &lld_macro_paths, error, lifetime, now))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add items because parent host was removed while"
				" processing lld rule");
		goto out;
	}

	lld_item_links_sort(&lld_rows);

	if (SUCCEED != lld_update_triggers(hostid, lld_ruleid, &lld_rows, &lld_macro_paths, error, lifetime, now))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add triggers because parent host was removed while"
				" processing lld rule");
		goto out;
	}

	if (SUCCEED != lld_update_graphs(hostid, lld_ruleid, &lld_rows, &lld_macro_paths, error, lifetime, now))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add graphs because parent host was removed while"
				" processing lld rule");
		goto out;
	}

	lld_update_hosts(lld_ruleid, &lld_rows, &lld_macro_paths, error, lifetime, now);

	/* add informative warning to the error message about lack of data for macros used in filter */
	if (NULL != info)
		*error = zbx_strdcat(*error, info);
out:
	zbx_audit_flush();
	DCconfig_clean_items(&item, &errcode, 1);
	zbx_free(info);
	zbx_free(discovery_key);

	lld_filter_clean(&filter);

	zbx_vector_ptr_clear_ext(&overrides, (zbx_clean_func_t)lld_override_free);
	zbx_vector_ptr_destroy(&overrides);
	zbx_vector_ptr_clear_ext(&lld_rows, (zbx_clean_func_t)lld_row_free);
	zbx_vector_ptr_destroy(&lld_rows);
	zbx_vector_ptr_clear_ext(&lld_macro_paths, (zbx_clean_func_t)zbx_lld_macro_path_free);
	zbx_vector_ptr_destroy(&lld_macro_paths);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}
