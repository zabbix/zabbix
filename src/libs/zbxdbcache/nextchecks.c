/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "db.h"
#include "dbcache.h"

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_timespec_t	ts;
	char		*error_msg;
}
ZBX_DC_NEXTCHECK;

static ZBX_DC_NEXTCHECK	*nextchecks = NULL;
static int		nextcheck_alloc = 0;
static int		nextcheck_num = 0;

/******************************************************************************
 *                                                                            *
 * Function: DCclean_nextchecks                                               *
 *                                                                            *
 * Purpose: free memory allocated for `error_msg'es                           *
 *                                                                            *
 ******************************************************************************/
static void	DCclean_nextchecks()
{
	int	i;

	for (i = 0; i < nextcheck_num; i++)
		zbx_free(nextchecks[i].error_msg);
	nextcheck_num = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_nextcheck                                                  *
 *                                                                            *
 * Purpose: add item nextcheck to the array                                   *
 *                                                                            *
 ******************************************************************************/
void	DCadd_nextcheck(zbx_uint64_t itemid, const zbx_timespec_t *ts, const char *error_msg)
{
	const char	*__function_name = "DCadd_nextcheck";

	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == error_msg)
		return;

	i = get_nearestindex(nextchecks, sizeof(ZBX_DC_NEXTCHECK), nextcheck_num, itemid);

	if (i < nextcheck_num && nextchecks[i].itemid == itemid)	/* an item found */
		return;

	if (nextcheck_alloc == nextcheck_num)
	{
		nextcheck_alloc += 64;
		nextchecks = zbx_realloc(nextchecks, sizeof(ZBX_DC_NEXTCHECK) * nextcheck_alloc);
	}

	/* insert a new item */
	memmove(&nextchecks[i + 1], &nextchecks[i], sizeof(ZBX_DC_NEXTCHECK) * (nextcheck_num - i));

	nextchecks[i].itemid = itemid;
	nextchecks[i].ts = *ts;
	nextchecks[i].error_msg = zbx_strdup(NULL, error_msg);

	nextcheck_num++;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCflush_nextchecks                                               *
 *                                                                            *
 * Purpose: update triggers to UNKNOWN and generate events                    *
 *                                                                            *
 ******************************************************************************/
void	DCflush_nextchecks()
{
	const char		*__function_name = "DCflush_nextchecks";

	int			i;
	zbx_uint64_t		*itemids = NULL;
	zbx_timespec_t		*timespecs = NULL;
	char			**errors = NULL;
	zbx_hashset_t		trigger_info;
	zbx_vector_ptr_t	trigger_order;
	DC_TRIGGER		*trigger;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() nextcheck_num:%d", __function_name, nextcheck_num);

	if (0 == nextcheck_num)
		goto exit;

	itemids = zbx_malloc(itemids, nextcheck_num * sizeof(zbx_uint64_t));
	timespecs = zbx_malloc(timespecs, nextcheck_num * sizeof(zbx_timespec_t));
	errors = zbx_malloc(errors, nextcheck_num * sizeof(char *));

	for (i = 0; i < nextcheck_num; i++)
	{
		itemids[i] = nextchecks[i].itemid;
		timespecs[i] = nextchecks[i].ts;
		errors[i] = nextchecks[i].error_msg;
	}

	zbx_hashset_create(&trigger_info, MAX(100, 2 * nextcheck_num),
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&trigger_order);
	zbx_vector_ptr_reserve(&trigger_order, nextcheck_num);

	DCconfig_get_triggers_by_itemids(&trigger_info, &trigger_order, itemids, timespecs, errors, nextcheck_num, 0);

	zbx_free(errors);
	zbx_free(timespecs);
	zbx_free(itemids);

	for (i = 0; i < trigger_order.values_num; i++)
	{
		trigger = (DC_TRIGGER *)trigger_order.values[i];
		trigger->new_value = TRIGGER_VALUE_UNKNOWN;
	}

	process_triggers(&trigger_order);

	DCfree_triggers(&trigger_order);

	zbx_hashset_destroy(&trigger_info);
	zbx_vector_ptr_destroy(&trigger_order);

	DCclean_nextchecks();
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
