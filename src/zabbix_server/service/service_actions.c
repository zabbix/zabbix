/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "common.h"
#include "log.h"
#include "service_actions.h"

static int	condition_match_service(const zbx_service_action_condition_t *condition,
		const zbx_service_update_t *update)
{
	zbx_uint64_t	serviceid;

	if (SUCCEED != is_uint64(condition->value, &serviceid) || serviceid != update->service->serviceid)
		return FAIL;

	return SUCCEED;
}

static int	condition_match_service_name(const zbx_service_action_condition_t *condition,
		const zbx_service_update_t *update)
{
	return  zbx_strmatch_condition(update->service->name, condition->value, condition->op);
}

static int	condition_match_service_tag(const zbx_service_action_condition_t *condition,
		const zbx_service_update_t *update)
{
	int	i;

	for (i = 0; i < update->service->tags.values_num; i++)
	{
		zbx_service_tag_t	*tag = (zbx_service_tag_t *)update->service->tags.values[i];

		if (0 == strcmp(tag->name, condition->value))
			return SUCCEED;
	}

	return FAIL;
}

static int	condition_match_service_tag_value(const zbx_service_action_condition_t *condition,
		const zbx_service_update_t *update)
{
	int	i;

	for (i = 0; i < update->service->tags.values_num; i++)
	{
		zbx_service_tag_t	*tag = (zbx_service_tag_t *)update->service->tags.values[i];

		if (0 == strcmp(tag->name, condition->value2) && 0 == strcmp(tag->value, condition->value))
			return SUCCEED;
	}

	return FAIL;
}

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
		case CONDITION_TYPE_SERVICE_TAG:
			ret = condition_match_service_tag(condition, update);
			break;
		case CONDITION_TYPE_SERVICE_TAG_VALUE:
			ret = condition_match_service_tag_value(condition, update);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
	}

	return SUCCEED == ret ? "1" : "0";
}

static int	service_update_match_action(const zbx_service_update_t *update, const zbx_service_action_t *action)
{
	int		pos = 0, last_pos = 0, index;
	char		*expr = NULL, error[256];
	const char	*value;
	size_t		expr_alloc = 0, expr_offset = 0;
	zbx_token_t	token;
	zbx_uint64_t	id;
	double		res;


	for (; SUCCEED == zbx_token_find(action->formula, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID); pos++)
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
