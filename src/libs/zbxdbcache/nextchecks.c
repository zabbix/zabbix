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

static int	DCget_nextcheck_nearestindex(zbx_uint64_t itemid)
{
	int			first_index, last_index, index;
	ZBX_DC_NEXTCHECK	*nc;

	if (nextcheck_num == 0)
		return 0;

	first_index = 0;
	last_index = nextcheck_num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		nc = &nextchecks[index];
		if (nc->itemid == itemid)
			return index;
		else if (last_index == first_index)
		{
			if (nc->itemid < itemid)
				index++;
			return index;
		}
		else if (nc->itemid < itemid)
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
		item->nextcheck = now + CONFIG_REFRESH_UNSUPPORTED;
	}
	else
		item->nextcheck = calculate_item_nextcheck(item->itemid, item->type, item->delay,
				item->delay_flex, now - timediff) + timediff;

	sz = sizeof(ZBX_DC_NEXTCHECK);

	i = DCget_nextcheck_nearestindex(item->itemid);
	if (i < nextcheck_num && nextchecks[i].itemid == item->itemid)	/* item exists? */
	{
		if (nextchecks[i].now < now)
		{
			/* delete item */
			memmove(&nextchecks[i], &nextchecks[i + 1], sz * (nextcheck_num - (i + 1)));
			nextcheck_num --;
		}
		else
			return;
	}

	if (nextcheck_allocated == nextcheck_num)
	{
		nextcheck_allocated *= 2;
		nextchecks = zbx_realloc(nextchecks, nextcheck_allocated * sz);
	}

	/* insert new item */
	memmove(&nextchecks[i + 1], &nextchecks[i], sz * (nextcheck_num - i));

	nextchecks[i].itemid = item->itemid;
	nextchecks[i].now = now;
	nextchecks[i].nextcheck = item->nextcheck;
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
	int			i, sql_offset = 0, sql_allocated = 4096;
	char			*sql = NULL;
	zbx_uint64_t		last_itemid = 0;
	char			*error_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCflush_nextchecks()");

	if (nextcheck_num == 0)
		return;

	DBbegin();

	sql = zbx_malloc(sql, sql_allocated);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	for (i = 0; i < nextcheck_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				"update items set nextcheck=%d",
				(int)nextchecks[i].nextcheck);

		if (NULL != nextchecks[i].error_msg)
		{
			error_esc = DBdyn_escape_string_len(nextchecks[i].error_msg, ITEM_ERROR_LEN);
			zbx_free(nextchecks[i].error_msg);

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128 + strlen(error_esc),
					",status=%d,lastclock=%d,error='%s'",
					ITEM_STATUS_NOTSUPPORTED,
					(int)nextchecks[i].now,
					error_esc);
			last_itemid = nextchecks[i].itemid;

			zbx_free(error_esc);
		}

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				" where itemid=" ZBX_FS_UI64 ";\n",
				nextchecks[i].itemid);

		if (sql_offset > ZBX_MAX_SQL_SIZE)
		{
#ifdef HAVE_ORACLE
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif
			DBexecute("%s", sql);
			sql_offset = 0;
#ifdef HAVE_ORACLE
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif
		}
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	DBcommit();
}
