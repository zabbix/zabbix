/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "cep_rule.h"
#include "cep.h"
#include "cep_api.h"
#include "cep_event.h"
#include "cep_task.h"
#include "cep_rule_operation.h"
#include "zbxtypes.h"
#include "zbx_cep.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxeval.h"
#include "zbxexpr.h"
#include "zbxnum.h"
#include "zbxvariant.h"
#include "zbxdbhigh.h"

typedef int (*zbx_cep_op_condition_eval_func_t)(const zbx_cep_op_condition_t *condition, zbx_cep_event_context_t *ctx);

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate string condition operator against raw values             *
 *                                                                            *
 * Parameters: operator - [IN] condition operator to apply                    *
 *             pattern  - [IN] condition pattern to compare against           *
 *             value    - [IN] value to evaluate                              *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 * Comments: NOT_EQUAL and NOT_LIKE negation is expected to be handled by     *
 *           the caller.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_value_str_raw(int operator, const char *pattern, const char *value)
{
	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			return (0 == strcmp(value, pattern) ? 1 : 0);
		case ZBX_CONDITION_OPERATOR_LIKE:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			return (NULL != strstr(value, pattern) ? 1 : 0);
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operator %d used with string values", operator);

	return 0;
}

static int	cep_condition_compare_value_str(const char *pattern, const char *value)
{
	double	cond_dbl, event_dbl;

	if (SUCCEED == zbx_is_double(pattern, &cond_dbl) && SUCCEED == zbx_is_double(value, &event_dbl))
	{
		ZBX_RETURN_IF_DBL_NOT_EQUAL(cond_dbl, event_dbl);
		return 0;
	}

	return strcmp(pattern, value);
}

static int	cep_condition_eval_value(int operator, const char *pattern, const char *value)
{
	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_MORE_EQUAL:
			if (0 >= cep_condition_compare_value_str(pattern, value))
				return 1;
			break;
		case ZBX_CONDITION_OPERATOR_LESS_EQUAL:
			if (0 <= cep_condition_compare_value_str(pattern, value))
				return 1;
			break;
		default:
			return cep_condition_eval_value_str_raw(operator, pattern, value);
	}

	return 0;
}

/*
 * operations
 */

static int	cep_operation_condition_eval_tag(const zbx_cep_op_condition_t *condition,
		zbx_cep_event_context_t *ctx)
{
	const zbx_cep_args_tag_name_t	*args = &condition->args.tag_name;
	zbx_cep_event_t			*event;
	int				ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tag:%s", __func__, condition->args.tag_name.tag);

	if (NULL != (event = cep_event_context_get_event(ctx)))
	{
		for (int i = 0; i < event->tags.values_num && 0 == ret; i++)
		{
			ret = cep_condition_eval_value_str_raw(condition->operator, args->tag,
					event->tags.values[i].tag);
		}

		switch (condition->operator)
		{
			case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			case ZBX_CONDITION_OPERATOR_NOT_LIKE:
				ret = !ret;
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	cep_operation_condition_eval_tag_value(const zbx_cep_op_condition_t *condition,
		zbx_cep_event_context_t *ctx)
{
	const zbx_cep_args_tag_value_t	*args = &condition->args.tag_value;
	int				ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tag:%s value:%s", __func__, args->tag, args->value);

	zbx_cep_event_t	*event = cep_event_context_get_event(ctx);

	if (NULL != event)
	{
		for (int i = 0; i < event->tags.values_num && 0 == ret; i++)
		{
			if (0 != strcmp(args->tag, event->tags.values[i].tag))
				continue;

			ret = cep_condition_eval_value(condition->operator, args->value,
				event->tags.values[i].value);
		}

		switch (condition->operator)
		{
			case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			case ZBX_CONDITION_OPERATOR_NOT_LIKE:
				ret = !ret;
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	cep_operation_condition_eval_state(const zbx_cep_op_condition_t *condition, int state)
{
	int	ret = state;

	if (ZBX_CONDITION_OPERATOR_NO == condition->operator)
		ret = !ret;
	else if (ZBX_CONDITION_OPERATOR_YES != condition->operator)
		return 0;

	return ret;
}

static int	cep_operation_condition_eval_open(const zbx_cep_op_condition_t *condition,
		zbx_cep_event_context_t *ctx)
{
	zbx_cep_event_t	*event;

	if (NULL == (event = cep_event_context_get_event(ctx)))
		return 0;

	return cep_operation_condition_eval_state(condition, (NULL == event->r_event));
}

static int	cep_operation_condition_eval_symptom(const zbx_cep_op_condition_t *condition,
		zbx_cep_event_context_t *ctx)
{
	zbx_cep_event_t	*event;

	if (NULL == (event = cep_event_context_get_event(ctx)))
		return 0;

	return cep_operation_condition_eval_state(condition, (0 != event->cause_eventid));
}

static int	cep_operation_condition_eval_copied(const zbx_cep_op_condition_t *condition,
		zbx_cep_event_context_t *ctx)
{
	zbx_cep_event_t	*event;

	if (NULL == (event = cep_event_context_get_event(ctx)))
		return 0;

	return cep_operation_condition_eval_state(condition, (ZBX_EVENT_COPIED == event->flags));
}

static int	cep_operation_condition_eval_suppressed(const zbx_cep_op_condition_t *condition,
	zbx_cep_event_context_t *ctx)
{
	zbx_cep_event_t	*event;

	if (NULL == (event = cep_event_context_get_event(ctx)))
		return 0;

	return cep_operation_condition_eval_state(condition, (0 != event->suppress.values_num));
}

static int	cep_operation_condition_eval(const zbx_cep_op_condition_t *condition, zbx_cep_event_context_t *ctx)
{
	switch (condition->type)
	{
		case ZBX_CONDITION_TYPE_EVENT_TAG:
			return cep_operation_condition_eval_tag(condition, ctx);
		case ZBX_CONDITION_TYPE_EVENT_TAG_VALUE:
			return cep_operation_condition_eval_tag_value(condition, ctx);
		case ZBX_CONDITION_TYPE_EVENT_OPEN:
			return cep_operation_condition_eval_open(condition, ctx);
		case ZBX_CONDITION_TYPE_EVENT_FIRST:
			return cep_operation_condition_eval_state(condition, 0 != (ctx->pos & CEP_POS_FIRST));
		case ZBX_CONDITION_TYPE_EVENT_LAST:
			return cep_operation_condition_eval_state(condition, 0 != (ctx->pos & CEP_POS_LAST));
		case ZBX_CONDITION_TYPE_EVENT_SYMPTOM:
			return cep_operation_condition_eval_symptom(condition, ctx);
		case ZBX_CONDITION_TYPE_EVENT_COPIED:
			return cep_operation_condition_eval_copied(condition, ctx);
		case ZBX_CONDITION_TYPE_EVENT_SUPPRESSED:
			return cep_operation_condition_eval_suppressed(condition, ctx);
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operation condition type %d", condition->type);
			return 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate AND/OR operation filter conditions                       *
 *                                                                            *
 * Parameters: filter    - [IN] operation filter whose conditions to evaluate *
 *             eval_func - [IN] condition evaluation function                 *
 *             ctx       - [IN/OUT] event context passed to eval_func         *
 *                                                                            *
 * Return value: SUCCEED if all condition groups are met, FAIL otherwise      *
 *                                                                            *
 * Comments: Conditions of the same type are OR-ed; the resulting groups are  *
 *           AND-ed.                                                          *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_eval_and_or(const zbx_cep_op_filter_t *filter, zbx_cep_op_condition_eval_func_t eval_func,
		zbx_cep_event_context_t *ctx)
{
	for (int i = 0, j = 0; i < filter->conditions.values_num; i = j)
	{
		int	ret = 0;

		for (j = i; j < filter->conditions.values_num &&
				filter->conditions.values[j].type == filter->conditions.values[i].type; j++)
		{
			if (0 == ret)
				ret = eval_func(&filter->conditions.values[j], ctx);
		}

		if (0 == ret)
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate OR operation filter conditions                           *
 *                                                                            *
 * Parameters: filter    - [IN] operation filter whose conditions to evaluate *
 *             eval_func - [IN] condition evaluation function                 *
 *             ctx       - [IN/OUT] event context passed to eval_func         *
 *                                                                            *
 * Return value: SUCCEED if any condition is met, FAIL otherwise              *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_eval_or(const zbx_cep_op_filter_t *filter, zbx_cep_op_condition_eval_func_t eval_func,
		zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < filter->conditions.values_num; i++)
	{
		if (0 != eval_func(&filter->conditions.values[i], ctx))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate AND operation filter conditions                          *
 *                                                                            *
 * Parameters: filter    - [IN] operation filter whose conditions to evaluate *
 *             eval_func - [IN] condition evaluation function                 *
 *             ctx       - [IN/OUT] event context passed to eval_func         *
 *                                                                            *
 * Return value: SUCCEED if all conditions are met, FAIL otherwise            *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_eval_and(const zbx_cep_op_filter_t *filter, zbx_cep_op_condition_eval_func_t eval_func,
		zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < filter->conditions.values_num; i++)
	{
		if (0 == eval_func(&filter->conditions.values[i], ctx))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate custom expression of operation filter conditions         *
 *                                                                            *
 * Parameters: filter    - [IN] operation filter whose conditions to evaluate *
 *             eval_func - [IN] condition evaluation function                 *
 *             ctx       - [IN/OUT] event context passed to eval_func         *
 *                                                                            *
 * Return value: SUCCEED if expression evaluates to non-zero, FAIL otherwise  *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_eval_expression(const zbx_cep_op_filter_t *filter,
		zbx_cep_op_condition_eval_func_t eval_func, zbx_cep_event_context_t *ctx)
{
	zbx_eval_context_t	eval;
	char			*error = NULL;
	int			j, ret = FAIL;
	zbx_variant_t		value, value_fail;

	zbx_variant_set_none(&value);
	zbx_variant_set_dbl(&value_fail, 0.0);

	if (SUCCEED != zbx_eval_parse_expression(&eval, filter->formula,
			ZBX_EVAL_PARSE_FUNCTIONID | ZBX_EVAL_PARSE_LOGIC | ZBX_EVAL_PARSE_GROUP, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse CEP operation custom expression: %s", error);
		zbx_free(error);
		return FAIL;
	}

	for (int i = 0; i < eval.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &eval.stack.values[i];
		zbx_uint64_t		conditionid;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		if (SUCCEED != zbx_is_uint64_n(eval.expression + token->loc.l + 1, token->loc.r - token->loc.l - 1,
				&conditionid))
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid condition id in CEP operation custom expression starting"
					" with: %s", eval.expression + token->loc.l);
			goto out;
		}

		for (j = 0; j < filter->conditions.values_num; j++)
		{
			const zbx_cep_op_condition_t	*cond = &filter->conditions.values[j];

			if (cond->cep_op_conditionid == conditionid)
			{
				zbx_variant_clear(&token->value);
				zbx_variant_set_ui64(&token->value, (zbx_uint64_t)eval_func(cond, ctx));
				break;
			}
		}

		if (j == filter->conditions.values_num)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot find CEP operation condition " ZBX_FS_UI64
					" set in expression", conditionid);
			goto out;
		}
	}

	if (SUCCEED != zbx_eval_execute(&eval, NULL, &value, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot evaluate CEP operation custom expression: %s", error);
		zbx_free(error);
		goto out;
	}

	if (0 != zbx_variant_compare(&value, &value_fail))
		ret = SUCCEED;
out:
	zbx_eval_clear(&eval);
	zbx_variant_clear(&value);
	zbx_variant_clear(&value_fail);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event matches operation filter                           *
 *                                                                            *
 * Parameters: filter - [IN] operation filter to match                        *
 *             ctx    - [IN/OUT] event context for caching resolved values    *
 *                                                                            *
 * Return value: SUCCEED if event matches the filter, FAIL otherwise          *
 *                                                                            *
 * Comments: Filters without conditions match all events.                     *
 *                                                                            *
 ******************************************************************************/
int	cep_op_filter_match_event(const zbx_cep_op_filter_t *filter, zbx_cep_event_context_t *ctx)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() evaltype:%d", __func__, filter->evaltype);

	if (0 == filter->conditions.values_num)
	{
		ret = SUCCEED;
		goto out;
	}

	switch (filter->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_AND_OR:
			ret = cep_operation_eval_and_or(filter, cep_operation_condition_eval, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_OR:
			ret = cep_operation_eval_or(filter, cep_operation_condition_eval, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_AND:
			ret = cep_operation_eval_and(filter, cep_operation_condition_eval, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_EXPRESSION:
			ret = cep_operation_eval_expression(filter, cep_operation_condition_eval, ctx);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("invalid CEP operation evaltype %d", filter->evaltype);
			break;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/*
 * conditions
 */

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate event name condition                                     *
 *                                                                            *
 * Parameters: operator - [IN] condition operator to apply                    *
 *             args     - [IN] condition arguments containing name to match   *
 *             ctx      - [IN] event context                                  *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_event_name(int operator, const zbx_cep_args_name_t *args,
		const zbx_cep_event_context_t *ctx)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d name:%s", __func__, operator, args->name);

	ret =   cep_condition_eval_value_str_raw(operator, args->name, ctx->event->name);

	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			ret = !ret;
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate event tag name condition                                 *
 *                                                                            *
 * Parameters: operator - [IN] condition operator to apply                    *
 *             args     - [IN] condition arguments containing tag to match    *
 *             ctx      - [IN] event context                                  *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 * Comments: For positive operators condition is met if any event tag name    *
 *           matches; for negative operators no tag name must match.          *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_tag(int operator, const zbx_cep_args_tag_name_t *args,
		const zbx_cep_event_context_t *ctx)
{
	int	ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d tag:%s", __func__, operator, args->tag);

	for (int i = 0; i < ctx->event->tags.values_num && 0 == ret; i++)
		ret = cep_condition_eval_value_str_raw(operator, args->tag, ctx->event->tags.values[i].tag);

	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			ret = !ret;
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate event tag value condition                                *
 *                                                                            *
 * Parameters: operator - [IN] condition operator to apply                    *
 *             args     - [IN] condition arguments containing tag and value   *
 *                             to match                                       *
 *             ctx      - [IN] event context                                  *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 * Comments: For positive operators condition is met if any matching tag has  *
 *           a matching value; for negative operators no specified tag must   *
 *           have a matching value.                                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_tag_value(int operator, const zbx_cep_args_tag_value_t *args,
		const zbx_cep_event_context_t *ctx)
{
	int	ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d tag:%s value:%s", __func__, operator, args->tag, args->value);

	for (int i = 0; i < ctx->event->tags.values_num && 0 == ret; i++)
	{
		if (0 != strcmp(args->tag, ctx->event->tags.values[i].tag))
			continue;

		ret = cep_condition_eval_value(operator, args->value, ctx->event->tags.values[i].value);
	}

	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			ret = !ret;
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate event severity condition                                 *
 *                                                                            *
 * Parameters: operator - [IN] condition operator to apply                    *
 *             args     - [IN] condition arguments containing severity level  *
 *             ctx      - [IN] event context                                  *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_severity(int operator, const zbx_cep_args_severity_t *args,
	const zbx_cep_event_context_t *ctx)
{
	int	ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d severity:%d", __func__, operator, args->level);

	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
			ret = ctx->event->severity == args->level;
			break;
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			ret = ctx->event->severity != args->level;
			break;
		case ZBX_CONDITION_OPERATOR_MORE_EQUAL:
			ret = ctx->event->severity >= args->level;
			break;
		case ZBX_CONDITION_OPERATOR_LESS_EQUAL:
			ret =  ctx->event->severity <= args->level;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operator %d used with severity condition", operator);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate event host name condition                                *
 *                                                                            *
 * Parameters: operator - [IN] condition operator to apply                    *
 *             args     - [IN] condition arguments containing host to match   *
 *             ctx      - [IN/OUT] event context for caching host names       *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 * Comments: For positive operators condition is met if any event host        *
 *           matches; for negative operators no event host must match.        *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_host_name(int operator, const zbx_cep_args_name_t *args,
		zbx_cep_event_context_t *ctx)
{
	const zbx_vector_str_t	*names;
	int			ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d host:%s", __func__, operator, args->name);

	names = cep_event_context_get_host_names(ctx);

	for (int i = 0; i < names->values_num && 0 == ret; i++)
	{
		ret = cep_condition_eval_value_str_raw(operator, args->name, names->values[i]);
	}

	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			ret = !ret;
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate event host group condition                               *
 *                                                                            *
 * Parameters: operator - [IN] condition operator to apply                    *
 *             args     - [IN] condition arguments containing group to match  *
 *             ctx      - [IN/OUT] event context for caching group names      *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 * Comments: For positive operators condition is met if any event host group  *
 *           matches; for negative operators no event host group must match.  *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_hostgroup_name(int operator, const zbx_cep_args_name_t *args,
		zbx_cep_event_context_t *ctx)
{
	const zbx_vector_str_t	*groups;
	int			ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d host:%s", __func__, operator, args->name);

	groups = cep_event_context_get_groups(ctx);

	for (int i = 0; i < groups->values_num && 0 == ret; i++)
	{
		ret = cep_condition_eval_value_str_raw(operator, args->name, groups->values[i]);
	}

	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			ret = !ret;
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate event time period condition                              *
 *                                                                            *
 * Parameters: operator - [IN] condition operator to apply                    *
 *             args     - [IN] condition arguments containing time period     *
 *             ctx      - [IN] event context                                  *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_time_period(int operator, const zbx_cep_args_time_period_t *args,
		zbx_cep_event_context_t *ctx)
{
	int	ret = 0, in;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d host:%s", __func__, operator, args->period);

	if (SUCCEED == zbx_check_time_period(args->period, ctx->event->clock, NULL, &in))
	{
		if (SUCCEED == in)
			ret = 1;

		if (ZBX_CONDITION_OPERATOR_NOT_IN == operator)
			ret = !ret;
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "invalid CEP condition time period \"%s\"", args->period);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate condition against event                                  *
 *                                                                            *
 * Parameters: cond - [IN] condition to evaluate                              *
 *             ctx  - [IN/OUT] event context for caching resolved values      *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval(const zbx_cep_condition_t *cond, zbx_cep_event_context_t *ctx)
{
	int	ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() conditionid:" ZBX_FS_UI64 " type:%d eventid:" ZBX_FS_UI64, __func__,
			cond->conditionid, cond->type, ctx->event->eventid);

	switch (cond->type)
	{
		case ZBX_CONDITION_TYPE_EVENT_NAME:
			ret = cep_condition_eval_event_name(cond->operator, &cond->args.event_name, ctx);
			break;
		case ZBX_CONDITION_TYPE_EVENT_TAG:
			ret = cep_condition_eval_tag(cond->operator, &cond->args.tag_name, ctx);
			break;
		case ZBX_CONDITION_TYPE_EVENT_TAG_VALUE:
			ret = cep_condition_eval_tag_value(cond->operator, &cond->args.tag_value, ctx);
			break;
		case ZBX_CONDITION_TYPE_TRIGGER_SEVERITY:
			ret = cep_condition_eval_severity(cond->operator, &cond->args.severity, ctx);
			break;
		case ZBX_CONDITION_TYPE_HOST_VISIBLE_NAME:
			ret = cep_condition_eval_host_name(cond->operator, &cond->args.host, ctx);
			break;
		case ZBX_CONDITION_TYPE_HOST_GROUP_NAME:
			ret = cep_condition_eval_hostgroup_name(cond->operator, &cond->args.host_group, ctx);
			break;
		case ZBX_CONDITION_TYPE_TIME_PERIOD:
			ret = cep_condition_eval_time_period(cond->operator, &cond->args.time_period, ctx);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported CEP rule condition type %d", cond->type);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate AND/OR rule conditions against event                     *
 *                                                                            *
 * Parameters: rule - [IN] rule whose conditions to evaluate                  *
 *             ctx  - [IN/OUT] event context for caching resolved values      *
 *                                                                            *
 * Return value: SUCCEED if all conditions are met, FAIL otherwise            *
 *                                                                            *
 * Comments: Conditions of the same type are OR-ed; distinct types are        *
 *           AND-ed.                                                          *
 *                                                                            *
 ******************************************************************************/
static int	cep_rule_eval_and_or(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx)
{
	for (int i = 0, j = 0; i < rule->conditions.values_num; i = j)
	{
		int	ret = 0;

		for (j = i; j < rule->conditions.values_num &&
				rule->conditions.values[j].type == rule->conditions.values[i].type; j++)
		{
			if (0 == ret)
				ret = cep_condition_eval(&rule->conditions.values[j], ctx);
		}

		if (0 == ret)
			return FAIL;
	}

	return SUCCEED;
}

static int	cep_rule_eval_and(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < rule->conditions.values_num; i++)
	{
		if (0 == cep_condition_eval(&rule->conditions.values[i], ctx))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate AND rule conditions against event                        *
 *                                                                            *
 * Parameters: rule - [IN] rule whose conditions to evaluate                  *
 *             ctx  - [IN/OUT] event context for caching resolved values      *
 *                                                                            *
 * Return value: SUCCEED if all conditions are met, FAIL otherwise            *
 *                                                                            *
 ******************************************************************************/
static int	cep_rule_eval_or(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx)
{
	if (0 == rule->conditions.values_num)
		return SUCCEED;

	for (int i = 0; i < rule->conditions.values_num; i++)
	{
		if (0 != cep_condition_eval(&rule->conditions.values[i], ctx))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate custom expression rule conditions against event          *
 *                                                                            *
 * Parameters: rule - [IN] rule whose conditions to evaluate                  *
 *             ctx  - [IN/OUT] event context for caching resolved values      *
 *                                                                            *
 * Return value: SUCCEED if expression evaluates to non-zero, FAIL otherwise  *
 *                                                                            *
 ******************************************************************************/
static int	cep_rule_eval_expression(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx)
{
	zbx_eval_context_t	eval;
	char			*error = NULL;
	int			j, ret = FAIL;
	zbx_variant_t		value, value_fail;

	zbx_variant_set_none(&value);
	zbx_variant_set_dbl(&value_fail, 0.0);

	if (SUCCEED != zbx_eval_parse_expression(&eval, rule->formula,
			ZBX_EVAL_PARSE_FUNCTIONID | ZBX_EVAL_PARSE_LOGIC | ZBX_EVAL_PARSE_GROUP, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse CEP rule custom expression: %s", error);
		zbx_free(error);
		return FAIL;
	}

	for (int i = 0; i < eval.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &eval.stack.values[i];
		zbx_uint64_t		conditionid;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		if (SUCCEED != zbx_is_uint64_n(eval.expression + token->loc.l + 1, token->loc.r - token->loc.l - 1,
				&conditionid))
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid condition id in CEP rule custom expression starting"
					" with: %s", eval.expression + token->loc.l);
			goto out;
		}

		for (j = 0; j < rule->conditions.values_num; j++)
		{
			const zbx_cep_condition_t	*cond = &rule->conditions.values[j];

			if (cond->conditionid == conditionid)
			{
				zbx_variant_clear(&token->value);
				zbx_variant_set_ui64(&token->value, (zbx_uint64_t)cep_condition_eval(cond, ctx));
				break;
			}
		}

		if (j == rule->conditions.values_num)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot find CEP condition " ZBX_FS_UI64 " set in expression",
					conditionid);
			goto out;
		}
	}

	if (SUCCEED != zbx_eval_execute(&eval, NULL, &value, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot evaluate CEP rule custom expression: %s", error);
		zbx_free(error);
		goto out;
	}

	if (0 != zbx_variant_compare(&value, &value_fail))
		ret = SUCCEED;
out:
	zbx_eval_clear(&eval);
	zbx_variant_clear(&value);
	zbx_variant_clear(&value_fail);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event matches rule conditions                            *
 *                                                                            *
 * Parameters: rule - [IN] rule whose conditions to evaluate                  *
 *             ctx  - [IN/OUT] event context for caching resolved values      *
 *                                                                            *
 * Return value: SUCCEED if event matches the rule, FAIL otherwise            *
 *                                                                            *
 ******************************************************************************/
static int	cep_rule_match_event(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64 " eventid:" ZBX_FS_UI64, __func__, rule->ruleid,
			ctx->event->eventid);

	switch (rule->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_AND_OR:
			ret = cep_rule_eval_and_or(rule, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_AND:
			ret = cep_rule_eval_and(rule, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_OR:
			ret = cep_rule_eval_or(rule, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_EXPRESSION:
			ret = cep_rule_eval_expression(rule, ctx);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("invalid CEP rule evaltype %d", rule->evaltype);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event should be discarded by rule                        *
 *                                                                            *
 * Parameters: rule  - [IN] rule to check discard operations for              *
 *             ctx   - [IN/OUT] event context for caching resolved values     *
 *                                                                            *
 * Return value: SUCCEED if event should be discarded, FAIL otherwise         *
 *                                                                            *
 ******************************************************************************/
static int	cep_rule_discard_event(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < rule->operations.values_num; i++)
	{
		const zbx_cep_operation_t	*op = &rule->operations.values[i];

		if (ZBX_CEP_WHEN_EVENT_OCCURRED != op->execute_when)
			continue;

		if (ZBX_CEP_OP_DISCARD == op->type && SUCCEED == cep_op_filter_match_event(&op->execute_filter, ctx))
		{
			return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: match event against CEP rules                                     *
 *                                                                            *
 * Parameters: handle            - [IN] CEP configuration handle              *
 *             matched_rules     - [OUT] matched rules, must be freed by      *
 *                                       the caller                           *
 *             matched_rules_num - [OUT] number of matched rules              *
 *             ctx               - [IN/OUT] event context for caching         *
 *                                         resolved values                    *
 *             tasks             - [OUT] vector to append tasks created by    *
 *                                 the operations to                          *
 *                                                                            *
 * Return value: SUCCEED if event was matched, FAIL if it must be discarded   *
 *                                                                            *
 * Comments: Rule processing stops at the first rule with stop flag set.      *
 *                                                                            *
 ******************************************************************************/
int	cep_event_process_rules(zbx_cep_config_handle_t handle, const zbx_cep_rule_t ***matched_rules,
		int *matched_rules_num, zbx_cep_event_context_t *ctx, zbx_vector_mw_task_ptr_t *tasks)
{
#define CEP_WINDOW_UNIQ	(CEP_FLAG(ZBX_CEP_WINDOW_CAUSAL) | CEP_FLAG(ZBX_CEP_WINDOW_CORRELATION))

	int					ret = FAIL;
	const zbx_vector_cep_rule_ptr_t		*rules;
	zbx_uint32_t				window_mask = 0;
	zbx_vector_const_cep_operation_ptr_t	ops;

	if (NULL == ctx->event)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("event not set when matching CEP rules");
		return FAIL;
	}

	zbx_vector_const_cep_operation_ptr_create(&ops);

	rules = zbx_cep_config_get_rules(handle);

	*matched_rules = (const zbx_cep_rule_t **)zbx_malloc(NULL, sizeof(zbx_cep_rule_t *) *
			(size_t)rules->values_num);
	*matched_rules_num = 0;

	for (int i = 0; i < rules->values_num; i++)
	{
		const zbx_cep_rule_t	*rule = rules->values[i];

		if (SUCCEED != cep_rule_match_event(rule, ctx))
			continue;

		/* only first matching uniq window rules can be processed */
		if (NULL != rule->window)
		{
			zbx_uint32_t	flag = (zbx_uint32_t)CEP_FLAG(rule->window->type);

			if (0 != (flag & window_mask & CEP_WINDOW_UNIQ))
				continue;

			window_mask |= flag;
		}

		if (SUCCEED == cep_rule_discard_event(rule, ctx))
		{
			zbx_free(*matched_rules);
			*matched_rules_num = 0;

			goto out;
		}

		(*matched_rules)[(*matched_rules_num)++] = rule;

		if (0 != cep_rule_get_execute_ops(rule, ZBX_CEP_WHEN_EVENT_OCCURRED, ctx, &ops))
		{
			(void)cep_rule_event_execute_ops(rule->ruleid, ZBX_CEP_WHEN_EVENT_OCCURRED, &ops, ctx,
					&ctx->event, NULL, tasks);
		}

		if (0 != rule->stop)
			break;

		zbx_vector_const_cep_operation_ptr_clear(&ops);
	}

	ret = SUCCEED;
out:
	zbx_vector_const_cep_operation_ptr_destroy(&ops);

	return ret;

#undef CEP_WINDOW_UNIQ
}

/******************************************************************************
 *                                                                            *
 * Purpose: shift numeric tag value by specified amount                       *
 *                                                                            *
 * Parameters: value - [IN] tag value                                         *
 *             shift - [IN] amount to shift value by                          *
 *                                                                            *
 * Return value: shifted value as a newly allocated string, or NULL if value  *
 *               is not a valid double                                        *
 *                                                                            *
 ******************************************************************************/
static char	*cep_tag_value_dbl_shift(const char *value, int shift)
{
	double	value_dbl;

	if (SUCCEED == zbx_is_double(value, &value_dbl))
	{
		char	buffer[32];

		zbx_print_double(buffer, sizeof(buffer), value_dbl + shift);
		return zbx_strdup(NULL, buffer);
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: increment numeric tag value by one                                *
 *                                                                            *
 * Parameters: value - [IN] tag value                                         *
 *                                                                            *
 * Return value: incremented value as a newly allocated string, or NULL if    *
 *               value is not numeric                                         *
 *                                                                            *
 * Comments: An empty string is treated as 0. Incrementing the maximum        *
 *           uint64 value wraps around.                                       *
 *                                                                            *
 ******************************************************************************/
char	*cep_tag_value_inc(const char *value)
{
	if ('\0' == *value)
		return zbx_strdup(NULL, "1");

	if ('-' == *value)
	{
		zbx_int64_t	value_i64;

		if (SUCCEED == zbx_is_uint63(value + 1, &value_i64))
			return zbx_dsprintf(NULL, ZBX_FS_I64, -value_i64 + 1);
	}
	else
	{
		zbx_uint64_t	value_ui64;

		if (SUCCEED == zbx_is_uint64(value, &value_ui64))
			return zbx_dsprintf(NULL, ZBX_FS_UI64, value_ui64 + 1);
	}

	return cep_tag_value_dbl_shift(value, 1);
}

/******************************************************************************
 *                                                                            *
 * Purpose: decrement numeric tag value by one                                *
 *                                                                            *
 * Parameters: value - [IN] tag value                                         *
 *                                                                            *
 * Return value: decremented value as a newly allocated string, or NULL if    *
 *               value is not numeric                                         *
 *                                                                            *
 * Comments: An empty string is treated as 0. Decrementing 0 returns -1,      *
 *           and decrementing the minimum int64 value wraps around.           *
 *                                                                            *
 ******************************************************************************/
char	*cep_tag_value_dec(const char *value)
{
	if ('\0' == *value)
		return zbx_strdup(NULL, "0");

	if ('-' == *value)
	{
		zbx_int64_t	value_i64;

		if (SUCCEED == zbx_is_uint63(value + 1, &value_i64))
			return zbx_dsprintf(NULL, ZBX_FS_I64,  -value_i64 - 1);
	}
	else
	{
		zbx_uint64_t	value_ui64;

		if (SUCCEED == zbx_is_uint64(value, &value_ui64))
		{
			if (0 != value_ui64)
				return zbx_dsprintf(NULL, ZBX_FS_UI64, value_ui64 - 1);

			return zbx_strdup(NULL, "-1");
		}
	}

	return cep_tag_value_dbl_shift(value, -1);
}

void	cep_rule_handle_error(const zbx_cep_rule_t *rule, char **error, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_t	*cep;
	int		ret;

	if (NULL != *error && '\0' != **error)
	{
		size_t	len = strlen(*error);

		if ('\n' == (*error)[len - 1])
			(*error)[len - 1] = '\0';
	}

	cep_cache_acquire(&cep);
	ret = cep_rule_check_error(cep, rule->ruleid, *error);
	cep_cache_release(&cep);

	if (SUCCEED != ret)
	{
		zbx_vector_mw_task_ptr_append(tasks, cep_create_task_rule_error(rule->ruleid, *error));
		*error = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get rule operations of execution phase whose execute filter       *
 *          matches the event triggering the execution phase                  *
 *                                                                            *
 * Parameters: rule         - [IN] rule whose operations to get               *
 *             execute_when - [IN] execution phase (ZBX_CEP_WHEN_*)           *
 *             ctx          - [IN/OUT] context of the event triggering the    *
 *                                     execution phase, NULL if there is none *
 *             ops          - [OUT] operations to execute                     *
 *                                                                            *
 * Return value: number of operations in the ops vector                       *
 *                                                                            *
 * Comments: Without triggering event (window closed, pattern match) execute  *
 *           filters are not checked.                                         *
 *                                                                            *
 ******************************************************************************/
int	cep_rule_get_execute_ops(const zbx_cep_rule_t *rule, int execute_when, zbx_cep_event_context_t *ctx,
		zbx_vector_const_cep_operation_ptr_t *ops)
{
	for (int i = 0; i < rule->operations.values_num; i++)
	{
		const zbx_cep_operation_t	*op = &rule->operations.values[i];

		if (op->execute_when != execute_when)
			continue;

		if (NULL == ctx || SUCCEED == cep_op_filter_match_event(&op->execute_filter, ctx))
			zbx_vector_const_cep_operation_ptr_append(ops, op);
	}

	return ops->values_num;
}

