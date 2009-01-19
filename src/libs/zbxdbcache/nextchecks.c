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

static int	DCget_nextcheck_nearestindex(time_t clock, zbx_uint64_t itemid)
{
	int			first_index, last_index, index, c;
	ZBX_DC_NEXTCHECK	*nc;

	if (nextcheck_num == 0)
		return 0;

	first_index = 0;
	last_index = nextcheck_num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		nc = &nextchecks[index];
		c = (NULL != nc->error_msg) ? 0 : nc->clock;
		if (c == clock && nc->itemid == itemid)
			return index;
		else if (last_index == first_index)
		{
			if (c < clock || (c == clock && nc->itemid < itemid))
				index++;
			return index;
		}
		else if (c < clock || (c == clock && nc->itemid < itemid))
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
	int	i;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCadd_nextcheck()");

	if (NULL != error_msg)
	{
		item->status = ITEM_STATUS_NOTSUPPORTED;
		item->nextcheck = now;
	}
	else
		item->nextcheck = calculate_item_nextcheck(item->itemid, item->type, item->delay,
				item->delay_flex, now - timediff) + timediff;

	sz = sizeof(ZBX_DC_NEXTCHECK);

	/* item exists? */
	for (i = 0; i < nextcheck_num; i ++)
	{
		if (nextchecks[i].itemid == item->itemid)
		{
			if (nextchecks[i].clock < item->nextcheck)
			{
				/* delete item */
				memmove(&nextchecks[i], &nextchecks[i + 1], sz * (nextcheck_num - (i + 1)));
				nextcheck_num --;
				break;
			}
			else
				return;
		}
	}

	if (nextcheck_allocated == nextcheck_num)
	{
		nextcheck_allocated *= 2;
		nextchecks = zbx_realloc(nextchecks, nextcheck_allocated * sz);
	}

	i = DCget_nextcheck_nearestindex((NULL != error_msg) ? 0 : item->nextcheck, item->itemid);

	/* insert new item */
	memmove(&nextchecks[i + 1], &nextchecks[i], sz * (nextcheck_num - i));

	nextchecks[i].itemid = item->itemid;
	nextchecks[i].clock = item->nextcheck;
	nextchecks[i].error_msg = (NULL != error_msg) ? strdup(error_msg) : NULL;

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
	int			i, sql_offset = 0, sql_allocated = 1024;
	char			*sql = NULL;
	time_t			last_clock = -1;
	zbx_uint64_t		last_itemid = 0;
	char			error_esc[ITEM_ERROR_LEN_MAX * 2];

	zabbix_log(LOG_LEVEL_DEBUG, "In DCflush_nextchecks()");

	if (nextcheck_num == 0)
		return;

	sql = zbx_malloc(sql, sql_allocated);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	for (i = 0; i < nextcheck_num; i++)
	{
		if (NULL != nextchecks[i].error_msg)
			continue;

		if (last_clock != nextchecks[i].clock) {
			if (last_clock != -1)
			{
				sql_offset--;
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ");\n");
			}

			if (last_itemid > nextchecks[i].itemid)
			{
#ifdef HAVE_ORACLE
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif
				DBbegin();
				DBexecute("%s", sql);
				DBcommit();

				sql_offset = 0;
#ifdef HAVE_ORACLE
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif
			}

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
					"update items set nextcheck=%d where itemid in (",
					(int)nextchecks[i].clock);
			last_clock = nextchecks[i].clock;
		}

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ZBX_FS_UI64 ",",
				nextchecks[i].itemid);
		last_itemid = nextchecks[i].itemid;
	}

	if (sql_offset > 8)
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ");\n");
	}

	for (i = 0; i < nextcheck_num; i++)
	{
		if (NULL == nextchecks[i].error_msg) /* not supported items */
			continue;

		if (last_itemid > nextchecks[i].itemid)
		{
#ifdef HAVE_ORACLE
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif
			DBbegin();
			DBexecute("%s", sql);
			DBcommit();

			sql_offset = 0;
#ifdef HAVE_ORACLE
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif
		}

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
		last_itemid = nextchecks[i].itemid;
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

	zbx_free(sql);
}
