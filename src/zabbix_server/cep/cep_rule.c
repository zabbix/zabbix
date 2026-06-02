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

void	cep_event_context_clear(zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < ctx->hosts_num; i++)
		zbx_free(ctx->hosts[i]);
	zbx_free(ctx->hosts);
}

/*
 * event context
 */

static void	cep_event_context_load_hosts(zbx_cep_event_context_t *ctx)
{
	zbx_vector_uint64_t	functionids;

	zbx_vector_uint64_create(&functionids);

	zbx_db_trigger_get_all_functionids(&ctx->db_event->trigger, &functionids);

	ctx->hosts = (char **)zbx_calloc(NULL, (size_t)functionids.values_num, sizeof(char *));
	ctx->hosts_num = zbx_dc_get_host_names_by_functionids(&functionids, ctx->hosts);

	zbx_vector_uint64_destroy(&functionids);
}


/*
 * operations
 */

static const char	*cep_event_get_builtin_tag(const zbx_cep_event_t *event, zbx_cep_event_context_t *ctx,
		const char *tag)
{
	/* TODO: resolve builin tags, cache in context and return */
	ZBX_UNUSED(event);
	ZBX_UNUSED(ctx);
	ZBX_UNUSED(tag);

	return "";
}

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

static int	cep_condition_eval_value_str_raw(int operator, const char *cond_value, const char *event_value)
{
	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			return (0 == strcmp(event_value, cond_value) ? 1 : 0);
		case ZBX_CONDITION_OPERATOR_LIKE:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			return (NULL == strstr(event_value, cond_value) ? 0 : 1);
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operator %d used with string values", operator);

	return 0;
}


static int	cep_condition_eval_value_int(int operator, int cond_value, int event_value)
{
	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_MORE_EQUAL:
			return event_value <= cond_value;
		case ZBX_CONDITION_OPERATOR_LESS_EQUAL:
			return event_value >= cond_value;
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operator %d used with integer values", operator);

	return 0;
}

static int	cep_eval_time_period(const char *period, zbx_timespec_t events_ts)
{
	/* TODO: implement */
	return 0;
}

static int	cep_condition_eval_value_time(int operator, const char *cond_value, zbx_timespec_t event_ts)
{
	switch (operator)
	{
		case ZBX_CONDITION_OPERATOR_IN:
			return cep_eval_time_period(cond_value, event_ts);
		case ZBX_CONDITION_OPERATOR_NOT_IN:
			return !cep_eval_time_period(cond_value, event_ts);
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operator %d used with time values", operator);

	return 0;
}

static int	cep_condition_eval_event_name(int operator, const zbx_cep_args_name_t *args,
		const zbx_cep_event_context_t *ctx)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d name:%s", __func__, operator, args->name);

	ret =   cep_condition_eval_value_str_raw(operator, args->name, ctx->db_event->name);

	if (ZBX_CONDITION_OPERATOR_NOT_EQUAL == operator)
		ret = !ret;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

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

	return 0;
}

static int	cep_condition_eval_host(int operator, const zbx_cep_args_name_t *args,
		zbx_cep_event_context_t *ctx)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operator:%d host:%s", __func__, operator, args->name);

	cep_event_context_load_hosts(ctx);

	int	ret = 0;

	for (int i = 0; i < ctx->hosts_num && 0 == ret; i++)
	{
		ret = cep_condition_eval_value_str_raw(operator, args->name, ctx->hosts[i]);
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

static int	cep_condition_eval(const zbx_cep_condition_t *cond, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	int	ret = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() conditionid:" ZBX_FS_UI64 " eventid:" ZBX_FS_UI64, __func__,
			cond->conditionid, ctx->db_event->eventid);

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

	}
	/* TODO: implementation */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%d", __func__, ret);

	return ret;
}

static int	cep_rule_eval_and_or(const zbx_cep_rule_t *rule, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	for (int i = 0, j = 0; i < rule->conditions.values_num; i = j)
	{
		int	ret = 0;

		for (j = i; j < rule->conditions.values_num &&
				rule->conditions.values[j].type == rule->conditions.values[i].type; j++)
		{
			if (0 == ret)
				ret = cep_condition_eval(&rule->conditions.values[j], event, ctx);
		}

		if (0 == ret)
			return FAIL;
	}

	return SUCCEED;
}

static int	cep_rule_eval_and(const zbx_cep_rule_t *rule, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < rule->conditions.values_num; i++)
	{
		if (0 == cep_condition_eval(&rule->conditions.values[i], event, ctx))
			return FAIL;
	}

	return SUCCEED;
}

static int	cep_rule_eval_or(const zbx_cep_rule_t *rule, const zbx_cep_event_t *event,
	zbx_cep_event_context_t *ctx)
{
	for (int i = 0; i < rule->conditions.values_num; i++)
	{
		if (0 != cep_condition_eval(&rule->conditions.values[i], event, ctx))
			return SUCCEED;
	}

	return FAIL;
}

static int	cep_rule_eval_expression(const zbx_cep_rule_t *rule, const zbx_cep_event_t *event,
	zbx_cep_event_context_t *ctx)
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
				zbx_variant_set_ui64(&token->value, (zbx_uint64_t)cep_condition_eval(cond, event, ctx));
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

static int	cep_rule_match_event(const zbx_cep_rule_t *rule, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64 " eventid:" ZBX_FS_UI64, __func__, rule->ruleid,
			ctx->db_event->eventid);

	switch (rule->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_AND_OR:
			ret = cep_rule_eval_and_or(rule, event, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_AND:
			ret = cep_rule_eval_and(rule, event, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_OR:
			ret = cep_rule_eval_or(rule, event, ctx);
			break;
		case ZBX_CONDITION_EVAL_TYPE_EXPRESSION:
			ret = cep_rule_eval_expression(rule, event, ctx);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("invalid CEP rule evaltype %d", rule->evaltype);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

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
		if (SUCCEED != cep_rule_match_event(rules->values[i], event, ctx))
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

void	cep_event_execute_ops(zbx_cep_event_t *event, const zbx_vector_cep_rule_ptr_t *matched_rules,
		int op_condition, zbx_cep_event_context_t *ctx)
{
}


void	cep_event_add_to_rules(zbx_cep_event_t *event, const zbx_vector_cep_rule_ptr_t *matched_rules)
{
}

