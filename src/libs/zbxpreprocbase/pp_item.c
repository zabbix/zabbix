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

#include "zbxpreprocbase.h"

#include "zbxalgo.h"

ZBX_PTR_VECTOR_IMPL(pp_step_ptr, zbx_pp_step_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: create item preprocessing data                                    *
 *                                                                            *
 * Parameters: hostid     - [IN] item host id                                 *
 *             type       - [IN] item type                                    *
 *             value_type - [IN] item value type                              *
 *             flags      - [IN] item flags                                   *
 *                                                                            *
 * Return value: The created item preprocessing data.                         *
 *                                                                            *
 ******************************************************************************/
zbx_pp_item_preproc_t	*zbx_pp_item_preproc_create(zbx_uint64_t hostid, unsigned char type, unsigned char value_type,
		unsigned char flags)
{
	zbx_pp_item_preproc_t	*preproc = zbx_malloc(NULL, sizeof(zbx_pp_item_preproc_t));

	preproc->refcount = 1;
	preproc->refcount_peak = 1;
	preproc->steps_num = 0;
	preproc->steps = NULL;
	preproc->pp_revision = 0;
	preproc->dep_itemids_num = 0;
	preproc->dep_itemids = NULL;

	preproc->hostid = hostid;
	preproc->type = type;
	preproc->value_type = value_type;
	preproc->flags = flags;
	preproc->history_cache = NULL;
	preproc->history_num = 0;

	preproc->mode = ZBX_PP_PROCESS_PARALLEL;

	return preproc;
}

void	zbx_pp_step_free(zbx_pp_step_t *step)
{
	zbx_free(step->params);
	zbx_free(step->error_handler_params);
	zbx_free(step);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free item preprocessing data                                      *
 *                                                                            *
 ******************************************************************************/
static void	pp_item_preproc_free(zbx_pp_item_preproc_t *preproc)
{
	for (int i = 0; i < preproc->steps_num; i++)
	{
		zbx_free(preproc->steps[i].params);
		zbx_free(preproc->steps[i].error_handler_params);
	}

	zbx_free(preproc->steps);
	zbx_free(preproc->dep_itemids);

	zbx_pp_history_cache_release(preproc->history_cache);

	zbx_free(preproc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy item preprocessing data                                      *
 *                                                                            *
 * Parameters: preproc - [IN] item preprocessing data                         *
 *                                                                            *
 * Return value: The copied preprocessing data.                               *
 *                                                                            *
 ******************************************************************************/
zbx_pp_item_preproc_t	*zbx_pp_item_preproc_copy(zbx_pp_item_preproc_t *preproc)
{
	if (NULL == preproc)
		return NULL;

	preproc->refcount++;

	if (preproc->refcount_peak < preproc->refcount)
		preproc->refcount_peak = preproc->refcount;

	return preproc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release item preprocessing data                                   *
 *                                                                            *
 * Parameters: preproc - [IN] item preprocessing data                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_item_preproc_release(zbx_pp_item_preproc_t *preproc)
{
	if (NULL == preproc || 0 != --preproc->refcount)
		return;

	pp_item_preproc_free(preproc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if preprocessing step requires history                      *
 *                                                                            *
 * Parameters: preproc - [IN] item preprocessing data                         *
 *                                                                            *
 * Return value: SUCCEED - the step requires history                          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_pp_preproc_has_history(int type)
{
	switch (type)
	{
		case ZBX_PREPROC_DELTA_VALUE:
		case ZBX_PREPROC_DELTA_SPEED:
		case ZBX_PREPROC_THROTTLE_VALUE:
		case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
		case ZBX_PREPROC_SCRIPT:
			return SUCCEED;
		default:
			return FAIL;

	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if preprocessing step require serial processing             *
 *                                                                            *
 * Parameters: preproc - [IN] item preprocessing data                         *
 *                                                                            *
 * Return value: SUCCEED - the step requires serial processing                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_pp_preproc_has_serial_history(int type)
{
	switch (type)
	{
		case ZBX_PREPROC_DELTA_VALUE:
		case ZBX_PREPROC_DELTA_SPEED:
		case ZBX_PREPROC_THROTTLE_VALUE:
		case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			return SUCCEED;
		default:
			return FAIL;

	}
}

void	zbx_pp_item_clear(zbx_pp_item_t *item)
{
	zbx_pp_item_preproc_release(item->preproc);
}
