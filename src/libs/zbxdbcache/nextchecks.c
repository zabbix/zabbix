/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "log.h"

#include "db.h"
#include "dbcache.h"

static ZBX_DC_NEXTCHECK	*nextchecks = NULL;
static int		nextcheck_allocated = 64;
static int		nextcheck_num;

/******************************************************************************
 *                                                                            *
 * Function: DCinit_nextchecks                                                *
 *                                                                            *
 * Purpose: initialize nextchecks array                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCinit_nextchecks()
{
	zabbix_log(LOG_LEVEL_DEBUG, "In DCinit_nextchecks()");

	if (NULL == nextchecks)
		nextchecks = zbx_malloc(nextchecks, nextcheck_allocated * sizeof(ZBX_DC_NEXTCHECK));

	nextcheck_num = 0;
}

static int	DCget_nextcheck_nearestindex(time_t clock)
{
	int	first_index, last_index, index;

	if (nextcheck_num == 0)
		return 0;

	first_index = 0;
	last_index = nextcheck_num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (nextchecks[index].clock == clock)
			return index;
		else if (last_index == first_index)
		{
			if (nextchecks[index].clock < clock)
				index++;
			return index;
		}
		else if (nextchecks[index].clock < clock)
			first_index = index + 1;
		else
			last_index = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_nextcheck                                                  *
 *                                                                            *
 * Purpose: add item nextcheck to the array                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_nextcheck(DB_ITEM *item, time_t now, time_t timediff, const char *error_msg)
{
	int	index;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCadd_nextcheck()");

	if (nextcheck_allocated == nextcheck_num)
	{
		nextcheck_allocated *= 2;
		nextchecks = zbx_realloc(nextchecks, nextcheck_allocated * sizeof(ZBX_DC_NEXTCHECK));
	}

	if (NULL != error_msg)
	{
		item->status = ITEM_STATUS_NOTSUPPORTED;
		item->nextcheck = now;
	}
	else
		item->nextcheck = calculate_item_nextcheck(item->itemid, item->type, item->delay,
				item->delay_flex, now - timediff) + timediff;

	index = DCget_nextcheck_nearestindex(item->nextcheck);

	memmove(&nextchecks[index + 1], &nextchecks[index], sizeof(ZBX_DC_NEXTCHECK) * (nextcheck_num - index));

	nextchecks[index].itemid = item->itemid;
	nextchecks[index].clock = item->nextcheck;
	nextchecks[index].error_msg = (NULL != error_msg) ? strdup(error_msg) : NULL;

	nextcheck_num ++;
}

/******************************************************************************
 *                                                                            *
 * Function: DCflush_nextchecks                                               *
 *                                                                            *
 * Purpose: add item nextcheck to the array                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCflush_nextchecks()
{
	int			i, sql_offset = 0;
	static char		*sql = NULL;
	static int		sql_allocated = 4096;
	time_t			last_clock = -1;
	char			error_esc[ITEM_ERROR_LEN_MAX * 2];
	static zbx_uint64_t	*ids = NULL;
	static int		ids_alloc = 20;
	int			ids_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCflush_nextchecks()");

	if (nextcheck_num == 0)
		return;

	if (sql == NULL)
		sql = zbx_malloc(sql, sql_allocated);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	if (NULL == ids)
		ids = zbx_malloc(ids, ids_alloc * sizeof(zbx_uint64_t));

	ids_num = 0;

	for (i = nextcheck_num - 1; i >= 0; i--)
	{
		if (NULL != nextchecks[i].error_msg)
			continue;

		if (SUCCEED == uint64_array_exists(ids, ids_num, nextchecks[i].itemid))
			continue;

		uint64_array_add(&ids, &ids_alloc, &ids_num, nextchecks[i].itemid);

		if (last_clock != nextchecks[i].clock) {
			if (last_clock != -1)
			{
				sql_offset--;
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ");\n");
			}

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
					"update items set nextcheck=%d where itemid in (",
					(int)nextchecks[i].clock);

			last_clock = nextchecks[i].clock;
		}

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ZBX_FS_UI64 ",",
				nextchecks[i].itemid);
	}

	if (sql_offset > 8)
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ");\n");
	}

	ids_num = 0;

	for (i = 0; i < nextcheck_num; i++ )
	{
		if (NULL == nextchecks[i].error_msg) /* not supported items */
			continue;

		if (SUCCEED == uint64_array_exists(ids, ids_num, nextchecks[i].itemid))
			continue;

		uint64_array_add(&ids, &ids_alloc, &ids_num, nextchecks[i].itemid);

		DBescape_string(nextchecks[i].error_msg, error_esc, sizeof(error_esc));
		zbx_free(nextchecks[i].error_msg);

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1024,
				"update items set status=%d,lastclock=%d,nextcheck=%d,error='%s'"
				" where itemid=" ZBX_FS_UI64 ";\n",
				ITEM_STATUS_NOTSUPPORTED,
				(int)nextchecks[i].clock,
				(int)(nextchecks[i].clock + CONFIG_REFRESH_UNSUPPORTED),
				error_esc,
				nextchecks[i].itemid);
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)
	{
		DBbegin();
		DBexecute("%s", sql);
		DBcommit();
	}
}
