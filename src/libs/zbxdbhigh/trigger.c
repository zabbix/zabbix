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

#include "common.h"

#include "db.h"
#include "log.h"
#include "dbcache.h"
#include "events.h"

#define ZBX_FLAGS_TRIGGER_CREATE_NOTHING		0x00
#define ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT		0x01
#define ZBX_FLAGS_TRIGGER_CREATE_INTERNAL_EVENT		0x02
#define ZBX_FLAGS_TRIGGER_CREATE_EVENT										\
		(ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT | ZBX_FLAGS_TRIGGER_CREATE_INTERNAL_EVENT)

/******************************************************************************
 *                                                                            *
 * Purpose: 1) calculate changeset of trigger fields to be updated            *
 *          2) generate events                                                *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger to process                          *
 *             diffs   - [OUT] the vector with trigger changes                *
 *                                                                            *
 * Return value: SUCCEED - trigger processed successfully                     *
 *               FAIL    - no changes                                         *
 *                                                                            *
 * Comments: Trigger dependency checks will be done during event processing.  *
 *                                                                            *
 * Event generation depending on trigger value/state changes:                 *
 *                                                                            *
 * From \ To  | OK         | OK(?)      | PROBLEM    | PROBLEM(?) | NONE      *
 *----------------------------------------------------------------------------*
 * OK         | .          | I          | E          | I          | .         *
 *            |            |            |            |            |           *
 * OK(?)      | I          | .          | E,I        | -          | I         *
 *            |            |            |            |            |           *
 * PROBLEM    | E          | I          | E(m)       | I          | .         *
 *            |            |            |            |            |           *
 * PROBLEM(?) | E,I        | -          | E(m),I     | .          | I         *
 *                                                                            *
 * Legend:                                                                    *
 *        'E' - trigger event                                                 *
 *        'I' - internal event                                                *
 *        '.' - nothing                                                       *
 *        '-' - should never happen                                           *
 *                                                                            *
 ******************************************************************************/
static int	zbx_process_trigger(struct _DC_TRIGGER *trigger, zbx_vector_ptr_t *diffs)
{
	const char		*new_error;
	int			new_state, new_value, ret = FAIL;
	zbx_uint64_t		flags = ZBX_FLAGS_TRIGGER_DIFF_UNSET, event_flags = ZBX_FLAGS_TRIGGER_CREATE_NOTHING;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64 " value:%d(%d) new_value:%d",
			__func__, trigger->triggerid, trigger->value, trigger->state, trigger->new_value);

	if (TRIGGER_VALUE_UNKNOWN == trigger->new_value)
	{
		new_state = TRIGGER_STATE_UNKNOWN;
		new_value = trigger->value;
	}
	else
	{
		new_state = TRIGGER_STATE_NORMAL;
		new_value = trigger->new_value;
	}
	new_error = (NULL == trigger->new_error ? "" : trigger->new_error);

	if (trigger->state != new_state)
	{
		flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE;
		event_flags |= ZBX_FLAGS_TRIGGER_CREATE_INTERNAL_EVENT;
	}

	if (0 != strcmp(trigger->error, new_error))
		flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR;

	if (TRIGGER_STATE_NORMAL == new_state)
	{
		if (TRIGGER_VALUE_PROBLEM == new_value)
		{
			if (TRIGGER_VALUE_OK == trigger->value || TRIGGER_TYPE_MULTIPLE_TRUE == trigger->type)
				event_flags |= ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT;
		}
		else if (TRIGGER_VALUE_OK == new_value)
		{
			if (TRIGGER_VALUE_PROBLEM == trigger->value || 0 == trigger->lastchange)
				event_flags |= ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT;
		}
	}

	/* check if there is something to be updated */
	if (0 == (flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE) && 0 == (event_flags & ZBX_FLAGS_TRIGGER_CREATE_EVENT))
		goto out;

	if (0 != (event_flags & ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT))
	{
		zbx_add_event(EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid,
				&trigger->timespec, new_value, trigger->description,
				trigger->expression, trigger->recovery_expression,
				trigger->priority, trigger->type, &trigger->tags,
				trigger->correlation_mode, trigger->correlation_tag, trigger->value, trigger->opdata,
				trigger->event_name, NULL);
	}

	if (0 != (event_flags & ZBX_FLAGS_TRIGGER_CREATE_INTERNAL_EVENT))
	{
		zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER, trigger->triggerid,
				&trigger->timespec, new_state, NULL, trigger->expression,
				trigger->recovery_expression, 0, 0, &trigger->tags, 0, NULL, 0, NULL, NULL,
				new_error);
	}

	zbx_append_trigger_diff(diffs, trigger->triggerid, trigger->priority, flags, trigger->value, new_state,
			trigger->timespec.sec, new_error);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s flags:" ZBX_FS_UI64, __func__, zbx_result_string(ret),
			flags);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: save the trigger changes to database                              *
 *                                                                            *
 * Parameters: trigger_diff - [IN] the trigger changeset                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_save_trigger_changes(const zbx_vector_ptr_t *trigger_diff)
{
	int				i;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	const zbx_trigger_diff_t	*diff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < trigger_diff->values_num; i++)
	{
		char	delim = ' ';
		diff = (const zbx_trigger_diff_t *)trigger_diff->values[i];

		if (0 == (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE))
			continue;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set");

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%clastchange=%d", delim, diff->lastchange);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cvalue=%d", delim, diff->value);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cstate=%d", delim, diff->state);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR))
		{
			char	*error_esc;

			error_esc = DBdyn_escape_field("triggers", "error", diff->error);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cerror='%s'", delim, error_esc);
			zbx_free(error_esc);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where triggerid=" ZBX_FS_UI64 ";\n",
				diff->triggerid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees trigger changeset                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_trigger_diff_free(zbx_trigger_diff_t *diff)
{
	zbx_free(diff->error);
	zbx_free(diff);
}

/******************************************************************************
 *                                                                            *
 * Comments: helper function for zbx_process_triggers()                       *
 *                                                                            *
 ******************************************************************************/
static int	zbx_trigger_topoindex_compare(const void *d1, const void *d2)
{
	const DC_TRIGGER	*t1 = *(const DC_TRIGGER **)d1;
	const DC_TRIGGER	*t2 = *(const DC_TRIGGER **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(t1->topoindex, t2->topoindex);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process triggers - calculates property changeset and generates    *
 *          events                                                            *
 *                                                                            *
 * Parameters: triggers     - [IN] the triggers to process                    *
 *             trigger_diff - [OUT] the trigger changeset                     *
 *                                                                            *
 * Comments: The trigger_diff changeset must be cleaned by the caller:        *
 *                zbx_vector_ptr_clear_ext(trigger_diff,                      *
 *                              (zbx_clean_func_t)zbx_trigger_diff_free);     *
 *                                                                            *
 ******************************************************************************/
void	zbx_process_triggers(zbx_vector_ptr_t *triggers, zbx_vector_ptr_t *trigger_diff)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __func__, triggers->values_num);

	if (0 == triggers->values_num)
		goto out;

	zbx_vector_ptr_sort(triggers, zbx_trigger_topoindex_compare);

	for (i = 0; i < triggers->values_num; i++)
		zbx_process_trigger((struct _DC_TRIGGER *)triggers->values[i], trigger_diff);

	zbx_vector_ptr_sort(trigger_diff, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Adds a new trigger diff to trigger changeset vector               *
 *                                                                            *
 ******************************************************************************/
void	zbx_append_trigger_diff(zbx_vector_ptr_t *trigger_diff, zbx_uint64_t triggerid, unsigned char priority,
		zbx_uint64_t flags, unsigned char value, unsigned char state, int lastchange, const char *error)
{
	zbx_trigger_diff_t	*diff;

	diff = (zbx_trigger_diff_t *)zbx_malloc(NULL, sizeof(zbx_trigger_diff_t));
	diff->triggerid = triggerid;
	diff->priority = priority;
	diff->flags = flags;
	diff->value = value;
	diff->state = state;
	diff->lastchange = lastchange;
	diff->error = (NULL != error ? zbx_strdup(NULL, error) : NULL);

	diff->problem_count = 0;

	zbx_vector_ptr_append(trigger_diff, diff);
}

/* temporary cache of trigger related data */
typedef struct
{
	zbx_uint32_t		init;
	zbx_uint32_t		done;
	zbx_eval_context_t	eval_ctx;
	zbx_eval_context_t	eval_ctx_r;
	zbx_vector_uint64_t	hostids;
}
zbx_trigger_cache_t;

/* related trigger data caching states */
typedef enum
{
	ZBX_TRIGGER_CACHE_EVAL_CTX,
	ZBX_TRIGGER_CACHE_EVAL_CTX_R,
	ZBX_TRIGGER_CACHE_EVAL_CTX_MACROS,
	ZBX_TRIGGER_CACHE_HOSTIDS,
}
zbx_trigger_cache_state_t;

/******************************************************************************
 *                                                                            *
 * Purpose: get trigger cache with the requested data cached                  *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             state   - [IN] the required cache state                        *
 *                                                                            *
 ******************************************************************************/
static zbx_trigger_cache_t	*db_trigger_get_cache(const DB_TRIGGER *trigger, zbx_trigger_cache_state_t state)
{
	zbx_trigger_cache_t	*cache;
	char			*error = NULL;
	zbx_uint32_t		flag = 1 << state;
	zbx_vector_uint64_t	functionids;

	if (NULL == trigger->cache)
	{
		cache = (zbx_trigger_cache_t *)zbx_malloc(NULL, sizeof(zbx_trigger_cache_t));
		cache->init = cache->done = 0;
		((DB_TRIGGER *)trigger)->cache = cache;
	}
	else
		cache = (zbx_trigger_cache_t *)trigger->cache;

	if (0 != (cache->init & flag))
		return 0 != (cache->done & flag) ? cache : NULL;

	cache->init |= flag;

	switch (state)
	{
		case ZBX_TRIGGER_CACHE_EVAL_CTX:
			if ('\0' == *trigger->expression)
				return NULL;

			if (FAIL == zbx_eval_parse_expression(&cache->eval_ctx, trigger->expression,
					ZBX_EVAL_TRIGGER_EXPRESSION, &error))
			{
				zbx_free(error);
				return NULL;
			}
			break;
		case ZBX_TRIGGER_CACHE_EVAL_CTX_R:
			if ('\0' == *trigger->recovery_expression)
				return NULL;

			if (FAIL == zbx_eval_parse_expression(&cache->eval_ctx_r, trigger->recovery_expression,
					ZBX_EVAL_TRIGGER_EXPRESSION, &error))
			{
				zbx_free(error);
				return NULL;
			}
			break;
		case ZBX_TRIGGER_CACHE_EVAL_CTX_MACROS:
			if (NULL == db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX))
					return NULL;
			zbx_dc_eval_expand_user_macros(&cache->eval_ctx);
			break;
		case ZBX_TRIGGER_CACHE_HOSTIDS:
			zbx_vector_uint64_create(&cache->hostids);
			zbx_vector_uint64_create(&functionids);
			zbx_db_trigger_get_all_functionids(trigger, &functionids);
			DCget_hostids_by_functionids(&functionids, &cache->hostids);
			zbx_vector_uint64_destroy(&functionids);
			break;
		default:
			return NULL;
	}

	cache->done |= flag;

	return cache;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free trigger cache                                                *
 *                                                                            *
 * Parameters: cache - [IN] the trigger cache                                 *
 *                                                                            *
 ******************************************************************************/
static void	trigger_cache_free(zbx_trigger_cache_t *cache)
{
	if (0 != (cache->done & (1 << ZBX_TRIGGER_CACHE_EVAL_CTX)))
		zbx_eval_clear(&cache->eval_ctx);

	if (0 != (cache->done & (1 << ZBX_TRIGGER_CACHE_EVAL_CTX_R)))
		zbx_eval_clear(&cache->eval_ctx_r);

	if (0 != (cache->done & (1 << ZBX_TRIGGER_CACHE_HOSTIDS)))
		zbx_vector_uint64_destroy(&cache->hostids);

	zbx_free(cache);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get functionids from trigger expression and recovery expression   *
 *                                                                            *
 * Parameters: trigger     - [IN] the trigger                                 *
 *             functionids - [OUT] the extracted functionids                  *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_all_functionids(const DB_TRIGGER *trigger, zbx_vector_uint64_t *functionids)
{
	zbx_trigger_cache_t	*cache;

	if (NULL != (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		zbx_eval_get_functionids(&cache->eval_ctx, functionids);

	if (NULL != (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX_R)))
		zbx_eval_get_functionids(&cache->eval_ctx_r, functionids);

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get functionids from trigger expression                           *
 *                                                                            *
 * Parameters: trigger     - [IN] the trigger                                 *
 *             functionids - [OUT] the extracted functionids                  *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_functionids(const DB_TRIGGER *trigger, zbx_vector_uint64_t *functionids)
{
	zbx_trigger_cache_t	*cache;

	if (NULL != (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		zbx_eval_get_functionids(&cache->eval_ctx, functionids);

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}
/******************************************************************************
 *                                                                            *
 * Purpose: get trigger expression constant at the specified location         *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             index   - [IN] the constant index, starting with 1             *
 *             out     - [IN] the constant value, if exists                   *
 *                                                                            *
 * Return value: SUCCEED - the expression was parsed and constant extracted   *
 *                         (if the index was valid)                           *
 *               FAIL    - the expression failed to parse                     *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_trigger_get_constant(const DB_TRIGGER *trigger, int index, char **out)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX_MACROS)))
		return FAIL;

	zbx_eval_get_constant(&cache->eval_ctx, index, out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the Nth function item from trigger expression                 *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             index   - [IN] the function index                              *
 *             itemid  - [IN] the function itemid                             *
 *                                                                            *
 * Comments: SUCCEED - the itemid was extracted successfully                  *
 *           FAIL    - otherwise                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_trigger_get_itemid(const DB_TRIGGER *trigger, int index, zbx_uint64_t *itemid)
{
	int			i, ret = FAIL;
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		return FAIL;

	for (i = 0; i < cache->eval_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &cache->eval_ctx.stack.values[i];
		zbx_uint64_t		functionid;
		DC_FUNCTION		function;
		int			errcode;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type || (int)token->opt + 1 != index)
			continue;

		switch (token->value.type)
		{
			case ZBX_VARIANT_UI64:
				functionid = token->value.data.ui64;
				break;
			case ZBX_VARIANT_NONE:
				if (SUCCEED != is_uint64_n(cache->eval_ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					return FAIL;
				}
				zbx_variant_set_ui64(&token->value, functionid);
				break;
			default:
				return FAIL;
		}

		DCconfig_get_functions_by_functionids(&function, &functionid, &errcode, 1);

		if (SUCCEED == errcode)
		{
			*itemid = function.itemid;
			ret = SUCCEED;
		}

		DCconfig_clean_functions(&function, &errcode, 1);
		break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get unique itemids of trigger functions in the order they are     *
 *          written in expression                                             *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             itemids - [IN] the function itemids                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_itemids(const DB_TRIGGER *trigger, zbx_vector_uint64_t *itemids)
{
	zbx_vector_uint64_t	functionids, functionids_ordered;
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		return;

	zbx_vector_uint64_create(&functionids);
	zbx_vector_uint64_create(&functionids_ordered);

	zbx_eval_get_functionids_ordered(&cache->eval_ctx, &functionids_ordered);

	if (0 != functionids_ordered.values_num)
	{
		DC_FUNCTION	*functions;
		int		i, *errcodes, index;

		zbx_vector_uint64_append_array(&functionids, functionids_ordered.values,
				functionids_ordered.values_num);

		zbx_vector_uint64_sort(&functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		functions = (DC_FUNCTION *)zbx_malloc(NULL, sizeof(DC_FUNCTION) * functionids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * functionids.values_num);

		DCconfig_get_functions_by_functionids(functions, functionids.values, errcodes,
				functionids.values_num);

		for (i = 0; i < functionids_ordered.values_num; i++)
		{
			if (-1 == (index = zbx_vector_uint64_bsearch(&functionids, functionids_ordered.values[i],
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != errcodes[index])
				continue;

			if (FAIL == zbx_vector_uint64_search(itemids, functions[index].itemid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				zbx_vector_uint64_append(itemids, functions[index].itemid);
			}
		}

		DCconfig_clean_functions(functions, errcodes, functionids.values_num);
		zbx_free(functions);
		zbx_free(errcodes);
	}

	zbx_vector_uint64_destroy(&functionids_ordered);
	zbx_vector_uint64_destroy(&functionids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get hostids from trigger expression and recovery expression       *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             hostids - [OUT] the extracted hostids                          *
 *                                                                            *
 * Return value: SUCCEED - the hostids vector was returned (but can be empty  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_trigger_get_all_hostids(const DB_TRIGGER *trigger, const zbx_vector_uint64_t **hostids)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_HOSTIDS)))
		return FAIL;

	*hostids = &cache->hostids;
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store trigger data                   *
 *                                                                            *
 * Parameters: trigger -                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_clean(DB_TRIGGER *trigger)
{
	zbx_free(trigger->description);
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
	zbx_free(trigger->comments);
	zbx_free(trigger->url);
	zbx_free(trigger->opdata);
	zbx_free(trigger->event_name);

	if (NULL != trigger->cache)
		trigger_cache_free((zbx_trigger_cache_t *)trigger->cache);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get original trigger expression/recovery expression with expanded *
 *          functions                                                         *
 *                                                                            *
 * Parameters: ctx        - [IN] the parsed expression                        *
 *             expression - [OUT] the trigger expression                      *
 *                                                                            *
 ******************************************************************************/
static void	db_trigger_get_expression(const zbx_eval_context_t *ctx, char **expression)
{
	int			i;
	zbx_eval_context_t	local_ctx;

	zbx_eval_copy(&local_ctx, ctx, ctx->expression);
	local_ctx.rules |= ZBX_EVAL_COMPOSE_MASK_ERROR;

	for (i = 0; i < local_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &local_ctx.stack.values[i];
		zbx_uint64_t		functionid;
		DC_FUNCTION		function;
		DC_ITEM			item;
		int			err_func, err_item;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
		{
			/* reset cached token values to get the original expression */
			zbx_variant_clear(&token->value);
			continue;
		}

		switch (token->value.type)
		{
			case ZBX_VARIANT_UI64:
				functionid = token->value.data.ui64;
				break;
			case ZBX_VARIANT_NONE:
				if (SUCCEED != is_uint64_n(local_ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					continue;
				}
				break;
			default:
				continue;
		}

		DCconfig_get_functions_by_functionids(&function, &functionid, &err_func, 1);

		if (SUCCEED == err_func)
		{
			DCconfig_get_items_by_itemids(&item, &function.itemid, &err_item, 1);

			if (SUCCEED == err_item)
			{
				char	*func = NULL;
				size_t	func_alloc = 0, func_offset = 0;

				zbx_snprintf_alloc(&func, &func_alloc, &func_offset, "%s(/%s/%s",
						function.function, item.host.host, item.key_orig);

				if ('\0' != *function.parameter)
					zbx_snprintf_alloc(&func, &func_alloc, &func_offset, ",%s", function.parameter);

				zbx_chrcpy_alloc(&func, &func_alloc, &func_offset,')');

				zbx_variant_clear(&token->value);
				zbx_variant_set_str(&token->value, func);
				DCconfig_clean_items(&item, &err_item, 1);
			}
			else
			{
				zbx_variant_clear(&token->value);
				zbx_variant_set_error(&token->value, zbx_dsprintf(NULL, "item id:" ZBX_FS_UI64
						" deleted", function.itemid));
			}

			DCconfig_clean_functions(&function, &err_func, 1);
		}
		else
		{
			zbx_variant_clear(&token->value);
			zbx_variant_set_error(&token->value, zbx_dsprintf(NULL, "function id:" ZBX_FS_UI64 " deleted",
					functionid));
		}
	}

	zbx_eval_compose_expression(&local_ctx, expression);

	zbx_eval_clear(&local_ctx);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get original trigger expression with expanded functions           *
 *                                                                            *
 * Parameters: trigger    - [IN] the trigger                                  *
 *             expression - [OUT] the trigger expression                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_expression(const DB_TRIGGER *trigger, char **expression)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		*expression = zbx_strdup(NULL, trigger->expression);
	else
		db_trigger_get_expression(&cache->eval_ctx, expression);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get original trigger recovery expression with expanded functions  *
 *                                                                            *
 * Parameters: trigger    - [IN] the trigger                                  *
 *             expression - [OUT] the trigger expression                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_recovery_expression(const DB_TRIGGER *trigger, char **expression)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX_R)))
		*expression = zbx_strdup(NULL, trigger->recovery_expression);
	else
		db_trigger_get_expression(&cache->eval_ctx_r, expression);
}

static void	evaluate_function_by_id(zbx_uint64_t functionid, char **value, int (*eval_func_cb)(
		zbx_variant_t *, DC_ITEM *, const char *, const char *, const zbx_timespec_t *, char **))
{
	DC_ITEM		item;
	DC_FUNCTION	function;
	int		err_func, err_item;

	DCconfig_get_functions_by_functionids(&function, &functionid, &err_func, 1);

	if (SUCCEED == err_func)
	{
		DCconfig_get_items_by_itemids(&item, &function.itemid, &err_item, 1);

		if (SUCCEED == err_item)
		{
			char		*error = NULL;
			zbx_variant_t	var;
			zbx_timespec_t	ts;

			zbx_timespec(&ts);

			if (SUCCEED == eval_func_cb(&var, &item, function.function,
					function.parameter, &ts, &error) && ZBX_VARIANT_NONE != var.type)
			{
				*value = zbx_strdup(NULL, zbx_variant_value_desc(&var));
				zbx_variant_clear(&var);
			}
			else
				zbx_free(error);

			DCconfig_clean_items(&item, &err_item, 1);
		}

		DCconfig_clean_functions(&function, &err_func, 1);
	}

	if (NULL == *value)
		*value = zbx_strdup(NULL, "*UNKNOWN*");
}

static void	db_trigger_explain_expression(const zbx_eval_context_t *ctx, char **expression, int (*eval_func_cb)(
		zbx_variant_t *, DC_ITEM *, const char *, const char *, const zbx_timespec_t *, char **))
{
	int			i;
	zbx_eval_context_t	local_ctx;

	zbx_eval_copy(&local_ctx, ctx, ctx->expression);
	local_ctx.rules |= ZBX_EVAL_COMPOSE_MASK_ERROR;

	for (i = 0; i < local_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &local_ctx.stack.values[i];
		char			*value = NULL;
		zbx_uint64_t		functionid;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
		{
			/* reset cached token values to get the original expression */
			zbx_variant_clear(&token->value);
			continue;
		}

		switch (token->value.type)
		{
			case ZBX_VARIANT_UI64:
				functionid = token->value.data.ui64;
				break;
			case ZBX_VARIANT_NONE:
				if (SUCCEED != is_uint64_n(local_ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					continue;
				}
				break;
			default:
				continue;
		}

		zbx_variant_clear(&token->value);
		evaluate_function_by_id(functionid, &value, eval_func_cb);
		zbx_variant_set_str(&token->value, value);
	}

	zbx_eval_compose_expression(&local_ctx, expression);

	zbx_eval_clear(&local_ctx);
}

static void	db_trigger_get_function_value(const zbx_eval_context_t *ctx, int index, char **value_ret,
		int (*eval_func_cb)(zbx_variant_t *, DC_ITEM *, const char *, const char *, const zbx_timespec_t *,
		char **))
{
	int			i;
	zbx_eval_context_t	local_ctx;

	zbx_eval_copy(&local_ctx, ctx, ctx->expression);

	for (i = 0; i < local_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &local_ctx.stack.values[i];
		zbx_uint64_t		functionid;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type || (int)token->opt + 1 != index)
			continue;

		switch (token->value.type)
		{
			case ZBX_VARIANT_UI64:
				functionid = token->value.data.ui64;
				break;
			case ZBX_VARIANT_NONE:
				if (SUCCEED != is_uint64_n(local_ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					continue;
				}
				break;
			default:
				continue;
		}

		evaluate_function_by_id(functionid, value_ret, eval_func_cb);
		break;
	}

	zbx_eval_clear(&local_ctx);

	if (NULL == *value_ret)
		*value_ret = zbx_strdup(NULL, "*UNKNOWN*");
}

void	zbx_db_trigger_explain_expression(const DB_TRIGGER *trigger, char **expression,
		int (*eval_func_cb)(zbx_variant_t *, DC_ITEM *, const char *, const char *, const zbx_timespec_t *,
		char **), int recovery)
{
	zbx_trigger_cache_t		*cache;
	zbx_trigger_cache_state_t	state;
	const zbx_eval_context_t	*ctx;

	state = (1 == recovery) ? ZBX_TRIGGER_CACHE_EVAL_CTX_R : ZBX_TRIGGER_CACHE_EVAL_CTX;

	if (NULL == (cache = db_trigger_get_cache(trigger, state)))
	{
		*expression = zbx_strdup(NULL, "*UNKNOWN*");
		return;
	}

	ctx = (1 == recovery) ? &cache->eval_ctx_r : &cache->eval_ctx;

	db_trigger_explain_expression(ctx, expression, eval_func_cb);
}

void	zbx_db_trigger_get_function_value(const DB_TRIGGER *trigger, int index, char **value,
		int (*eval_func_cb)(zbx_variant_t *, DC_ITEM *, const char *, const char *, const zbx_timespec_t *,
		char **), int recovery)
{
	zbx_trigger_cache_t		*cache;
	zbx_trigger_cache_state_t	state;
	const zbx_eval_context_t	*ctx;

	state = (1 == recovery) ? ZBX_TRIGGER_CACHE_EVAL_CTX_R : ZBX_TRIGGER_CACHE_EVAL_CTX;

	if (NULL == (cache = db_trigger_get_cache(trigger, state)))
	{
		*value = zbx_strdup(NULL, "*UNKNOWN*");
		return;
	}

	ctx = (1 == recovery) ? &cache->eval_ctx_r : &cache->eval_ctx;

	db_trigger_get_function_value(ctx, index, value, eval_func_cb);
}
