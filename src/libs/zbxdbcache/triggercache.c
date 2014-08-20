/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "zbxalgo.h"
#include "dbcache.h"
#include "triggercache.h"

/*
 * The local trigger cache is used to perform trigger dependency checks, value
 * and state updates in bulk operations to reduce configuration cache locking.
 *
 * The common usage steps are:
 *   1) initialize trigger cache: zbx_triggercache_init()
 *   2) load trigger dependencies into cache: zbx_triggercache_load()
 *   3) in a loop do:
 *        a) check trigger dependencies: zbx_triggercache_check_dependencies()
 *        b) update trigger value: zbx_triggercache_update_trigger()
 *   4) write changes to configuration cache: zbx_triggercache_flush()
 *   5) destroy trigger cache: zbx_triggercache_destroy()
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_triggercache_init                                            *
 *                                                                            *
 * Purpose: initialize local trigger cache                                    *
 *                                                                            *
 * Parameters: cache - [IN] the trigger cache                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_triggercache_init(zbx_hashset_t *cache)
{
	zbx_hashset_create(cache, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_triggercache_destroy                                         *
 *                                                                            *
 * Purpose: destroy local trigger cache                                       *
 *                                                                            *
 * Parameters: cache - [IN] the trigger cache                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_triggercache_destroy(zbx_hashset_t *cache)
{
	zbx_hashset_iter_t		iter;
	zbx_triggercache_trigger_t	*trigger;

	zbx_hashset_iter_reset(cache, &iter);

	while (NULL != (trigger = zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_ptr_destroy(&trigger->dependencies);
		zbx_free(trigger->error);
	}

	zbx_hashset_destroy(cache);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_triggercache_check_dependencies_rec                          *
 *                                                                            *
 * Purpose: check trigger dependencies don't have PROBLEM and trigger can     *
 *          change its value                                                  *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             level   - [IN] the recursion level                             *
 *                                                                            *
 * Return value: SUCCEED - trigger can change value                           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_triggercache_check_dependencies_rec(zbx_triggercache_trigger_t *trigger, int level)
{
	int	i;

	if (32 < level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "recursive trigger dependency is too deep, triggerid:" ZBX_FS_UI64,
				trigger->triggerid);
		return SUCCEED;
	}

	for (i = 0; i < trigger->dependencies.values_num; i++)
	{
		zbx_triggercache_trigger_t	*dep = (zbx_triggercache_trigger_t *)trigger->dependencies.values[i];

		if (1 == dep->loaded && TRIGGER_VALUE_PROBLEM == dep->value && TRIGGER_STATE_NORMAL == dep->state)
			return FAIL;

		if (FAIL == zbx_triggercache_check_dependencies_rec(dep, level + 1))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_triggercache_check_dependencies                              *
 *                                                                            *
 * Purpose: check trigger dependencies don't have PROBLEM and trigger can     *
 *          change its value                                                  *
 *                                                                            *
 * Parameters: trigger   - [IN] the trigger cache                             *
 *             triggerid - [IN] the trigger to check                          *
 *                                                                            *
 * Return value: SUCCEED - trigger can change value                           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_triggercache_check_dependencies(zbx_hashset_t *cache, zbx_uint64_t triggerid)
{
	int				ret = SUCCEED;
	zbx_triggercache_trigger_t	*trigger;

	if (NULL != (trigger = zbx_hashset_search(cache, &triggerid)))
		ret = zbx_triggercache_check_dependencies_rec(trigger, 0);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_triggercache_update_trigger                                  *
 *                                                                            *
 * Purpose: update trigger properties in local trigger cache                  *
 *                                                                            *
 * Parameters: cache      - [IN] the trigger cache                            *
 *             triggerid  - [IN] the trigger to update                        *
 *             value      - [IN] the new trigger value                        *
 *             state      - [IN] the new trigger state                        *
 *             error      - [IN] the new error message                        *
 *             lastchange - [IN] the last update timestamp, optional - can be *
 *                               NULL                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_triggercache_update_trigger(zbx_hashset_t *cache, zbx_uint64_t triggerid, unsigned char value,
		unsigned char state, const char *error, int *lastchange)
{
	zbx_triggercache_trigger_t	*trigger;

	if (NULL != (trigger = zbx_hashset_search(cache, &triggerid)))
	{
		trigger->error = zbx_strdup(trigger->error, error);
		trigger->value = value;
		trigger->state = state;
		trigger->modified = 1;

		if (NULL != lastchange)
			trigger->lastchange = *lastchange;
	}
}

