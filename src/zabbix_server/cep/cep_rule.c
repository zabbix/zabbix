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
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcalc.h"
#include "zbxcommon.h"
#include "zbxdbwrap.h"
#include "zbxeval.h"
#include "zbxexpr.h"
#include "zbxvariant.h"

/* WDN placeholder for proper defines */
#define ZBX_CEP_EXECUTE_ON_EVENT_OCCURRED	1

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated in event context                         *
 *                                                                            *
 ******************************************************************************/
void	cep_event_context_clear(zbx_cep_event_context_t *ctx)
{
	if (NULL != ctx->hosts.values)
	{
		zbx_vector_str_clear_ext(&ctx->hosts, zbx_str_free);
		zbx_vector_str_destroy(&ctx->hosts);
	}

	if (NULL != ctx->groups.values)
	{
		zbx_vector_str_clear_ext(&ctx->groups, zbx_str_free);
		zbx_vector_str_destroy(&ctx->groups);
	}
}

/*
 * event context
 */

/******************************************************************************
 *                                                                            *
 * Purpose: load host names associated with event trigger into context        *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context to populate with host names       *
 *                                                                            *
 * Comments: If host names are already loaded then do nothing.                *
 *                                                                            *
 ******************************************************************************/
static void	cep_event_context_load_hosts(zbx_cep_event_context_t *ctx)
{
	zbx_vector_uint64_t	functionids;

	if (NULL != ctx->hosts.values)
		return;

	zbx_vector_uint64_create(&functionids);

	zbx_db_trigger_get_all_functionids(&ctx->db_event->trigger, &functionids);

	zbx_vector_str_create(&ctx->hosts);
	zbx_dc_get_host_names_by_functionids(&functionids, &ctx->hosts);

	zbx_vector_uint64_destroy(&functionids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: load host group names associated with event trigger into context  *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context to populate with host group names *
 *                                                                            *
 * Comments: If host grouup names are already loaded then do nothing.         *
 *                                                                            *
 ******************************************************************************/
static void	cep_event_context_load_groups(zbx_cep_event_context_t *ctx)
{
	zbx_vector_uint64_t	functionids;

	if (NULL != ctx->groups.values)
		return;

	zbx_vector_uint64_create(&functionids);

	zbx_db_trigger_get_all_functionids(&ctx->db_event->trigger, &functionids);

	zbx_vector_str_create(&ctx->groups);
	zbx_dc_get_group_names_by_functionids(&functionids, &ctx->groups);

	zbx_vector_uint64_destroy(&functionids);
}

/*
 * operations
 */

/******************************************************************************
 *                                                                            *
 * Purpose: get built-in tag value for event                                  *
 *                                                                            *
 * Parameters: event - [IN] event to retrieve tag value for                   *
 *             ctx   - [IN/OUT] event context for caching resolved values     *
 *             tag   - [IN] built-in tag name                                 *
 *                                                                            *
 * Return value: tag value string                                             *
 *                                                                            *
 * Comments: The resolved value is cached in context and reused on            *
 *           subsequent requests for the same tag.                            *
 *                                                                            *
 ******************************************************************************/
static const char	*cep_event_get_builtin_tag(const zbx_cep_event_t *event, zbx_cep_event_context_t *ctx,
		const char *tag)
{
	/* TODO: resolve builin tags, cache in context and return */
	ZBX_UNUSED(event);
	ZBX_UNUSED(ctx);
	ZBX_UNUSED(tag);

	return "";
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate operation tag filter condition for event                 *
 *                                                                            *
 * Parameters: tag   - [IN] tag filter condition to evaluate                  *
 *             event - [IN] event to evaluate against                         *
 *             ctx   - [IN/OUT] event context for caching resolved values     *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_tag_eval(const zbx_cep_operation_tag_t *tag, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	int	ret = 0;

	if ('$' == *tag->tag)
	{
		const char	*value = cep_event_get_builtin_tag(event, ctx, tag->tag);

		if (0 == strcmp(value, tag->value))
			ret = 1;
	}
	else
	{
		for (int i = 0; i < event->tags.values_num; i++)
		{
			if (0 == strcmp(tag->tag, event->tags.values[i].tag))
			{
				if (0 == strcmp(tag->value, event->tags.values[i].value))
				{
					ret = 1;
					break;
				}

			}
		}
	}

	if (ZBX_CONDITION_OPERATOR_NOT_EQUAL == tag->operator)
		ret = !ret;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate AND/OR operation tag filter conditions for event         *
 *                                                                            *
 * Parameters: op    - [IN] operation whose tag conditions to evaluate        *
 *             event - [IN] event to evaluate against                         *
 *             ctx   - [IN/OUT] event context for caching resolved values     *
 *                                                                            *
 * Return value: SUCCEED if all conditions are met, FAIL otherwise            *
 *                                                                            *
 * Comments: Conditions on the same tag are OR-ed; distinct tags are AND-ed.  *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_eval_and_or(const zbx_cep_operation_t *op, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	for (int i = 0, j = 0; i < op->tags.values_num; i = j)
	{
		int	ret = 0;

		for (j = i; j < op->tags.values_num && 0 == strcmp(op->tags.values[j].tag, op->tags.values[i].tag); j++)
		{
			if (0 == ret)
				ret = cep_operation_tag_eval(&op->tags.values[j], event, ctx);
		}

		if (0 == ret)
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate OR operation tag filter conditions for event             *
 *                                                                            *
 * Parameters: op    - [IN] operation whose tag conditions to evaluate        *
 *             event - [IN] event to evaluate against                         *
 *             ctx   - [IN/OUT] event context for caching resolved values     *
 *                                                                            *
 * Return value: SUCCEED if any condition is met, FAIL otherwise              *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_eval_or(const zbx_cep_operation_t *op, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < op->tags.values_num; i++)
	{
		if (0 != cep_operation_tag_eval(&op->tags.values[i], event, ctx))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event matches operation tag filter                       *
 *                                                                            *
 * Parameters: op    - [IN] operation whose tag filter to match               *
 *             event - [IN] event to match against                            *
 *             ctx   - [IN/OUT] event context for caching resolved values     *
 *                                                                            *
 * Return value: SUCCEED if event matches the filter, FAIL otherwise          *
 *                                                                            *
 * Comments: Operations with no tags match all events.                        *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_match_event(const zbx_cep_operation_t *op, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	int	ret = FAIL;

	if (0 == op->tags.values_num)
		return SUCCEED;

	switch (op->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_AND_OR:
			ret = cep_operation_eval_and_or(op, event, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_OR:
			ret = cep_operation_eval_or(op, event, ctx);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("invalid CEP operation evaltype %d", op->evaltype);
			break;
	}

	return ret;
}

/*
 * conditions
 */

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate string condition operator against raw values             *
 *                                                                            *
 * Parameters: operator    - [IN] condition operator to apply                 *
 *             cond_value  - [IN] condition value to compare against          *
 *             event_value - [IN] event value to evaluate                     *
 *                                                                            *
 * Return value: 1 if condition is met, 0 otherwise                           *
 *                                                                            *
 * Comments: NOT_EQUAL and NOT_LIKE negation is expected to be handled by     *
 *           the caller.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	cep_condition_eval_value_str_raw(int operator, const char *cond_value, const char *event_value)
{
	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			return (0 == strcmp(event_value, cond_value) ? 1 : 0);
		case ZBX_CONDITION_OPERATOR_LIKE:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			return (NULL != strstr(event_value, cond_value) ? 1 : 0);
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operator %d used with string values", operator);

	return 0;
}

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

	ret =   cep_condition_eval_value_str_raw(operator, args->name, ctx->db_event->name);

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
static int	cep_condition_eval_tag_name(int operator, const zbx_cep_args_tag_name_t *args,
		const zbx_cep_event_context_t *ctx)
{
	int	ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d tag:%s", __func__, operator, args->tag);

	for (int i = 0; i < ctx->db_event->tags.values_num && 0 == ret; i++)
	{
		ret = cep_condition_eval_value_str_raw(operator, args->tag, ctx->db_event->tags.values[i]->tag);
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

	for (int i = 0; i < ctx->db_event->tags.values_num && 0 == ret; i++)
	{
		if (0 != strcmp(args->tag, ctx->db_event->tags.values[i]->tag))
			continue;

		ret = cep_condition_eval_value_str_raw(operator, args->value, ctx->db_event->tags.values[i]->value);
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
		case ZBX_CONDITION_OPERATOR_MORE_EQUAL:
			ret = ctx->db_event->severity >= args->level;
			break;
		case ZBX_CONDITION_OPERATOR_LESS_EQUAL:
			ret =  ctx->db_event->severity <= args->level;
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
 * Purpose: evaluate event host condition                                     *
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
static int	cep_condition_eval_host(int operator, const zbx_cep_args_name_t *args,
		zbx_cep_event_context_t *ctx)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d host:%s", __func__, operator, args->name);

	cep_event_context_load_hosts(ctx);

	int	ret = 0;

	for (int i = 0; i < ctx->hosts.values_num && 0 == ret; i++)
	{
		ret = cep_condition_eval_value_str_raw(operator, args->name, ctx->hosts.values[i]);
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
static int	cep_condition_eval_host_group(int operator, const zbx_cep_args_name_t *args,
		zbx_cep_event_context_t *ctx)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d host:%s", __func__, operator, args->name);

	cep_event_context_load_groups(ctx);

	int	ret = 0;

	for (int i = 0; i < ctx->groups.values_num && 0 == ret; i++)
	{
		ret = cep_condition_eval_value_str_raw(operator, args->name, ctx->groups.values[i]);
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

	if (SUCCEED == zbx_check_time_period(args->period, ctx->db_event->clock, NULL, &in))
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
			cond->conditionid, cond->type, ctx->db_event->eventid);

	switch (cond->type)
	{
		case ZBX_CEP_CONDITION_EVENT_NAME:
			ret = cep_condition_eval_event_name(cond->operator, &cond->args.event_name, ctx);
			break;
		case ZBX_CEP_CONDITION_TAG_NAME:
			ret = cep_condition_eval_tag_name(cond->operator, &cond->args.tag_name, ctx);
			break;
		case ZBX_CEP_CONDITION_TAG_VALUE:
			ret = cep_condition_eval_tag_value(cond->operator, &cond->args.tag_value, ctx);
			break;
		case ZBX_CEP_CONDITION_SEVERITY:
			ret = cep_condition_eval_severity(cond->operator, &cond->args.severity, ctx);
			break;
		case ZBX_CEP_CONDITION_HOST:
			ret = cep_condition_eval_host(cond->operator, &cond->args.host, ctx);
			break;
		case ZBX_CEP_CONDITION_HOST_GROUP:
			ret = cep_condition_eval_host_group(cond->operator, &cond->args.host_group, ctx);
			break;
		case ZBX_CEP_CONDITION_TIME_PERIOD:
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
	int			ret = FAIL;
	zbx_variant_t		value, value_fail;

	zbx_variant_set_dbl(&value_fail, 0.0);

	if (SUCCEED != zbx_eval_parse_expression(&eval, rule->formula, ZBX_EVAL_PARSE_FUNCTIONID, &error))
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

		for (int j = 0; j < rule->conditions.values_num; j++)
		{
			const zbx_cep_condition_t	*cond = &rule->conditions.values[j];

			if (cond->conditionid == conditionid)
			{
				zbx_variant_clear(&token->value);
				zbx_variant_set_ui64(&token->value, (zbx_uint64_t)cep_condition_eval(cond, ctx));
				continue;
			}
		}

		zabbix_log(LOG_LEVEL_WARNING, "cannot find CEP condition " ZBX_FS_UI64 " set in expression",
				conditionid);
		goto out;
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
			ctx->db_event->eventid);

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
 *             event - [IN] event to check                                    *
 *             ctx   - [IN/OUT] event context for caching resolved values     *
 *                                                                            *
 * Return value: SUCCEED if event should be discarded, FAIL otherwise         *
 *                                                                            *
 ******************************************************************************/
static int	cep_rule_discard_event(const zbx_cep_rule_t *rule, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < rule->operations.values_num; i++)
	{
		const zbx_cep_operation_t	*op = &rule->operations.values[i];

		if (ZBX_CEP_EXECUTE_ON_EVENT_OCCURRED != op->execute_when)
			continue;

		if (ZBX_CEP_OP_DISCARD == op->type && SUCCEED == cep_operation_match_event(op, event, ctx))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: match event against CEP rules                                     *
 *                                                                            *
 * Parameters: event             - [IN] event to match                        *
 *             handle            - [IN] CEP configuration handle              *
 *             matched_rules     - [OUT] matched rules, must be freed by      *
 *                                       the caller                           *
 *             matched_rules_num - [OUT] number of matched rules              *
 *             ctx               - [IN/OUT] event context for caching         *
 *                                         resolved values                    *
 *                                                                            *
 * Return value: SUCCEED if event was matched, FAIL if it must be discarded   *
 *                                                                            *
 * Comments: Rule processing stops at the first rule with stop flag set.      *
 *                                                                            *
 ******************************************************************************/
int	cep_event_match_rules(const zbx_cep_event_t *event, zbx_cep_config_handle_t handle,
		const zbx_cep_rule_t ***matched_rules, int *matched_rules_num, zbx_cep_event_context_t *ctx)
{
	int				ret = FAIL;
	const zbx_vector_cep_rule_ptr_t	*rules;

	if (NULL == ctx->db_event)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("event not set when matching CEP rules");
		return FAIL;
	}

	rules = zbx_cep_config_get_rules(handle);

	*matched_rules = (const zbx_cep_rule_t **)zbx_malloc(NULL, sizeof(zbx_cep_rule_t *) *
			rules->values_num);
	*matched_rules_num = 0;

	for (int i = 0; i < rules->values_num; i++)
	{
		if (SUCCEED != cep_rule_match_event(rules->values[i], ctx))
			continue;

		if (SUCCEED == cep_rule_discard_event(rules->values[i], event, ctx))
		{
			zbx_free(*matched_rules);
			*matched_rules_num = 0;

			goto out;
		}

		(*matched_rules)[(*matched_rules_num)++] = rules->values[i];

		if (0 != rules->values[i]->stop)
			break;
	}

	ret = SUCCEED;
out:
	return ret;
}

void	cep_event_execute_ops(zbx_cep_event_t *event, const zbx_cep_rule_t **matched_rules, int matched_rules_num,
	int op_condition, zbx_cep_event_context_t *ctx)
{
	ZBX_UNUSED(event);
	ZBX_UNUSED(matched_rules);
	ZBX_UNUSED(matched_rules_num);
	ZBX_UNUSED(op_condition);
	ZBX_UNUSED(ctx);

	/* TODO: implementation */
}


void	cep_event_add_to_rules(zbx_cep_event_handle_t hevent, const zbx_cep_rule_t **matched_rules,
		int matched_rules_num)
{
	ZBX_UNUSED(hevent);
	ZBX_UNUSED(matched_rules);
	ZBX_UNUSED(matched_rules_num);

	/* TODO: implementation */
}

