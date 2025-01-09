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

#include "zbxcacheconfig.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxeval.h"
#include "zbxexpression.h"

typedef struct
{
	zbx_dc_trigger_t	*trigger;
	int			start_index;
	int			count;
}
zbx_trigger_func_position_t;

ZBX_PTR_VECTOR_DECL(trigger_func_position, zbx_trigger_func_position_t *)
ZBX_PTR_VECTOR_IMPL(trigger_func_position, zbx_trigger_func_position_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: triggers links with functions.                                    *
 *                                                                            *
 * Parameters: triggers_func_pos - [IN/OUT] pointer to the list of triggers   *
 *                                          with functions position in        *
 *                                          functionids array                 *
 *             functionids       - [IN/OUT] array of function IDs             *
 *             trigger_order     - [IN] array of triggers                     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_link_triggers_with_functions(zbx_vector_trigger_func_position_t *triggers_func_pos,
		zbx_vector_uint64_t *functionids, zbx_vector_dc_trigger_t *trigger_order)
{
	zbx_vector_uint64_t	funcids;
	zbx_dc_trigger_t	*tr;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trigger_order_num:%d", __func__, trigger_order->values_num);

	zbx_vector_uint64_create(&funcids);
	zbx_vector_uint64_reserve(&funcids, functionids->values_num);

	for (i = 0; i < trigger_order->values_num; i++)
	{
		zbx_trigger_func_position_t	*tr_func_pos;

		tr = trigger_order->values[i];

		if (NULL != tr->new_error)
			continue;

		zbx_eval_get_functionids(tr->eval_ctx, &funcids);

		tr_func_pos = (zbx_trigger_func_position_t *)zbx_malloc(NULL, sizeof(zbx_trigger_func_position_t));
		tr_func_pos->trigger = tr;
		tr_func_pos->start_index = functionids->values_num;
		tr_func_pos->count = funcids.values_num;

		zbx_vector_uint64_append_array(functionids, funcids.values, funcids.values_num);
		zbx_vector_trigger_func_position_append(triggers_func_pos, tr_func_pos);

		zbx_vector_uint64_clear(&funcids);
	}

	zbx_vector_uint64_destroy(&funcids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() triggers_func_pos_num:%d", __func__, triggers_func_pos->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: mark triggers that use one of the items in problem expression     *
 *          with ZBX_DC_TRIGGER_PROBLEM_EXPRESSION flag.                      *
 *                                                                            *
 * Parameters: trigger_order - [IN/OUT] pointer to the list of triggers       *
 *             itemids       - [IN] array of item IDs                         *
 *             item_num      - [IN] number of items                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_determine_items_in_expressions(zbx_vector_dc_trigger_t *trigger_order, const zbx_uint64_t *itemids,
		int item_num)
{
	zbx_vector_trigger_func_position_t	triggers_func_pos;
	zbx_vector_uint64_t			functionids, itemids_sorted;
	zbx_dc_function_t			*functions = NULL;
	int					*errcodes = NULL, t, f;

	zbx_vector_uint64_create(&itemids_sorted);
	zbx_vector_uint64_append_array(&itemids_sorted, itemids, item_num);

	zbx_vector_trigger_func_position_create(&triggers_func_pos);
	zbx_vector_trigger_func_position_reserve(&triggers_func_pos, trigger_order->values_num);

	zbx_vector_uint64_create(&functionids);
	zbx_vector_uint64_reserve(&functionids, item_num);

	zbx_link_triggers_with_functions(&triggers_func_pos, &functionids, trigger_order);

	functions = (zbx_dc_function_t *)zbx_malloc(functions, sizeof(zbx_dc_function_t) * functionids.values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * functionids.values_num);

	zbx_dc_config_history_sync_get_functions_by_functionids(functions, functionids.values, errcodes,
			(size_t)functionids.values_num);

	for (t = 0; t < triggers_func_pos.values_num; t++)
	{
		zbx_trigger_func_position_t	*func_pos = triggers_func_pos.values[t];

		for (f = func_pos->start_index; f < func_pos->start_index + func_pos->count; f++)
		{
			if (SUCCEED == errcodes[f] && FAIL != zbx_vector_uint64_bsearch(&itemids_sorted,
					functions[f].itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				func_pos->trigger->flags |= ZBX_DC_TRIGGER_PROBLEM_EXPRESSION;
				break;
			}
		}
	}

	zbx_dc_config_clean_functions(functions, errcodes, functionids.values_num);
	zbx_free(errcodes);
	zbx_free(functions);

	zbx_vector_trigger_func_position_clear_ext(&triggers_func_pos,
			(zbx_trigger_func_position_free_func_t)zbx_ptr_free);
	zbx_vector_trigger_func_position_destroy(&triggers_func_pos);

	zbx_vector_uint64_clear(&functionids);
	zbx_vector_uint64_destroy(&functionids);

	zbx_vector_uint64_clear(&itemids_sorted);
	zbx_vector_uint64_destroy(&itemids_sorted);
}
