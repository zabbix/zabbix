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
#include "zbxeval.h"
#include "zbxvariant.h"

/* WDN placeholder for proper defines */
#define ZBX_CEP_EXECUTE_ON_EVENT_OCCURRED	1

typedef struct
{
	char	*hosts;	/* comma delimited host list */
}
zbx_cep_event_context_t;

static void	cep_event_context_clear(zbx_cep_event_context_t *ctx)
{
	zbx_free(ctx->hosts);
}

static int	cep_condition_eval(const zbx_cep_condition_t *cond, const zbx_cep_event_t *event,
		zbx_cep_event_context_t *ctx)
{
	return 0;
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
	int	ret;

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
	}

	return ret;
}

static int	cep_rule_discard_event(const zbx_cep_rule_t *rule, const zbx_cep_event_t *event)
{
	for (int i = 0; i < rule->operations.values_num; i++)
	{
		const zbx_cep_operation_t	*op = &rule->operations.values[i];

		if (ZBX_CEP_EXECUTE_ON_EVENT_OCCURRED != op->execute_when)
			continue;

		if (ZBX_CEP_OP_DISCARD == op->type)
		{
			ZBX_UNUSED(event);
			/* TODO: need also to check op flags if they match this event */
			return SUCCEED;
		}
	}

	return FAIL;
}

int	zbx_cep_event_match_rules(const zbx_cep_event_t *event, zbx_cep_config_handle_t handle,
		const zbx_cep_rule_t ***matched_rules, int *matched_rules_num)
{
	int				ret = FAIL;
	const zbx_vector_cep_rule_ptr_t	*rules;
	zbx_cep_event_context_t		ctx = {0};

	rules = zbx_cep_config_get_rules(handle);

	*matched_rules = (const zbx_cep_rule_t **)zbx_malloc(NULL, sizeof(zbx_cep_rule_t *) *
			rules->values_num);
	*matched_rules_num = 0;

	for (int i = 0; i < rules->values_num; i++)
	{
		if (SUCCEED != cep_rule_match_event(rules->values[i], event, &ctx))
			continue;

		if (SUCCEED == cep_rule_discard_event(rules->values[i], event))
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
	cep_event_context_clear(&ctx);

	return ret;
}

void	zbx_cep_event_execute_ops(zbx_cep_event_t *event, const zbx_vector_cep_rule_ptr_t *matched_rules,
		int op_condition)
{
}


void	zbx_cep_event_add_to_rules(zbx_cep_event_t *event, const zbx_vector_cep_rule_ptr_t *matched_rules)
{
}

