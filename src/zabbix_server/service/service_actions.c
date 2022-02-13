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

#include "service_actions.h"

#include "log.h"

/******************************************************************************
 *                                                                            *
 * Purpose: match service update by service id                                *
 *                                                                            *
 ******************************************************************************/
static int	condition_match_service(const zbx_service_action_condition_t *condition,
		const zbx_service_update_t *update)
{
	zbx_uint64_t	serviceid;

	if (SUCCEED != is_uint64(condition->value, &serviceid) || serviceid != update->service->serviceid)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: match service update by service name                              *
 *                                                                            *
 ******************************************************************************/
static int	condition_match_service_name(const zbx_service_action_condition_t *condition,
		const zbx_service_update_t *update)
{
	return  zbx_strmatch_condition(update->service->name, condition->value, condition->op);
}

/******************************************************************************
 *                                                                            *
 * Purpose: match tag/tag+value using the specified operator                  *
 *                                                                            *
 * Parameters: tags  - [IN] the tags to match                                 *
 *             name  - [IN] the target tag name                               *
 *             value - [IN] the target tag value (NULL if only tag name are   *
 *                          being matched                                     *
 *             op    - [IN] the matching operator (CONDITION_OPERATOR_*)      *
 *                                                                            *
 * Return value: SUCCEED - the tags matches                                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: When matching tag+value the operator is using only to match      *
 *           value - the tag name will be always matched as 'equal'           *
 *                                                                            *
 ******************************************************************************/
static int	match_tags(const zbx_vector_ptr_t *tags, const char *name, const char *value, unsigned char op)
{
	int	i, ret, expected_ret;

	if (CONDITION_OPERATOR_EQUAL == op || CONDITION_OPERATOR_LIKE == op)
	{
		expected_ret = SUCCEED;
		ret = FAIL;
	}
	else
	{
		expected_ret = FAIL;
		ret = SUCCEED;
	}

	for (i = 0; i < tags->values_num; i++)
	{
		zbx_service_tag_t	*tag = (zbx_service_tag_t *)tags->values[i];

		if (NULL != value)
		{
			if (0 == strcmp(tag->name, name))
				ret = zbx_strmatch_condition(tag->value, value, op);
			else
				continue;
		}
		else
			ret = zbx_strmatch_condition(tag->name, name, op);

		if (expected_ret == ret)
			return ret;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: match service update by service tag name                          *
 *                                                                            *
 ******************************************************************************/
static int	condition_match_service_tag(const zbx_service_action_condition_t *condition,
		const zbx_service_update_t *update)
{
	return match_tags(&update->service->tags, condition->value, NULL, condition->op);
}

/******************************************************************************
 *                                                                            *
 * Purpose: match service update by service tag and its value                 *
 *                                                                            *
 ******************************************************************************/
static int	condition_match_service_tag_value(const zbx_service_action_condition_t *condition,
		const zbx_service_update_t *update)
{
	return match_tags(&update->service->tags, condition->value2, condition->value, condition->op);
}

/******************************************************************************
 *                                                                            *
 * Purpose: match service update by the specified condition                   *
 *                                                                            *
 ******************************************************************************/
static const char	*service_update_match_condition(const zbx_service_update_t *update,
		const zbx_service_action_condition_t *condition)
{
	int	ret;

	switch (condition->conditiontype)
	{
		case CONDITION_TYPE_SERVICE:
			ret = condition_match_service(condition, update);
			break;
		case CONDITION_TYPE_SERVICE_NAME:
			ret = condition_match_service_name(condition, update);
			break;
		case CONDITION_TYPE_EVENT_TAG:
			ret = condition_match_service_tag(condition, update);
			break;
		case CONDITION_TYPE_EVENT_TAG_VALUE:
			ret = condition_match_service_tag_value(condition, update);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
	}

	return SUCCEED == ret ? "1" : "0";
}

/******************************************************************************
 *                                                                            *
 * Purpose: match service update against the specified action                 *
 *                                                                            *
 ******************************************************************************/
static int	service_update_match_action(const zbx_service_update_t *update, const zbx_service_action_t *action)
{
	int		index;
	size_t		pos = 0, last_pos = 0, expr_alloc = 0, expr_offset = 0;
	char		*expr = NULL, error[256];
	const char	*value;
	zbx_token_t	token;
	zbx_uint64_t	id;
	double		res;

	if (0 == action->conditions.values_num)
		return SUCCEED;

	for (; SUCCEED == zbx_token_find(action->formula, (int)pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID); pos++)
	{
		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
				if (SUCCEED == is_uint64_n(action->formula + token.data.objectid.name.l,
						token.data.objectid.name.r - token.data.objectid.name.l + 1, &id))
				{
					zbx_strncpy_alloc(&expr, &expr_alloc, &expr_offset,
							action->formula + last_pos, token.loc.l - last_pos);

					if (FAIL != (index = zbx_vector_ptr_search(&action->conditions, &id,
							ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
					{
						value = service_update_match_condition(update,
								action->conditions.values[index]);
					}
					else
						value = "0";

					zbx_strcpy_alloc(&expr, &expr_alloc, &expr_offset, value);

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

	zbx_strcpy_alloc(&expr, &expr_alloc, &expr_offset, action->formula + last_pos);

	if (FAIL == evaluate(&res, expr, error, sizeof(error), NULL))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot evaluate action \"" ZBX_FS_UI64 "\" formula \"%s\": %s",
			action->actionid, action->formula, error);
		res = 0;
	}

	zbx_free(expr);

	return SUCCEED == zbx_double_compare(res, 0) ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: match service update against service actions                      *
 *                                                                            *
 * Parameters: update    - [IN] the service update generated when service     *
 *                              state changes                                 *
 *             actions   - [IN] the service actions                           *
 *             actionids - [OUT] the matched action identifiers               *
 *                                                                            *
 ******************************************************************************/
void	service_update_process_actions(const zbx_service_update_t *update, zbx_hashset_t *actions,
		zbx_vector_uint64_t *actionids)
{
	zbx_hashset_iter_t	iter;
	zbx_service_action_t	*action;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() serviceid:" ZBX_FS_UI64, __func__, update->service->serviceid);

	zbx_hashset_iter_reset(actions, &iter);
	while (NULL != (action = (zbx_service_action_t *)zbx_hashset_iter_next(&iter)))
	{
		if (SUCCEED == service_update_match_action(update, action))
			zbx_vector_uint64_append(actionids, action->actionid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() matched:%d", __func__, actionids->values_num);
}
