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

#define ZBX_DC_NEXTCHECK struct zbx_dc_nextcheck_type

ZBX_DC_NEXTCHECK
{
	zbx_uint64_t	itemid;
	time_t		now;
	char		*error_msg;
};

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
 * Author: Alexander Vladishev                                                *
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

/******************************************************************************
 *                                                                            *
 * Function: DCrelease_nextchecks                                             *
 *                                                                            *
 * Purpose: free memory allocated for `error_msg'es                           *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCrelease_nextchecks()
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCrelease_nextchecks()");

	for (i = 0; i < nextcheck_num; i++)
		zbx_free(nextchecks[i].error_msg);
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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_nextcheck(zbx_uint64_t itemid, time_t now, const char *error_msg)
{
	const char	*__function_name = "DCadd_nextcheck";

	int	i;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == error_msg)
		return;

	sz = sizeof(ZBX_DC_NEXTCHECK);

	i = get_nearestindex(nextchecks, sizeof(ZBX_DC_NEXTCHECK), nextcheck_num, itemid);
	if (i < nextcheck_num && nextchecks[i].itemid == itemid)	/* item exists? */
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
		nextcheck_allocated += 64;
		nextchecks = zbx_realloc(nextchecks, nextcheck_allocated * sz);
	}

	/* insert new item */
	memmove(&nextchecks[i + 1], &nextchecks[i], sz * (nextcheck_num - i));

	nextchecks[i].itemid = itemid;
	nextchecks[i].now = now;
	nextchecks[i].error_msg = zbx_strdup(NULL, error_msg);

	nextcheck_num++;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
 * Author: Alexander Vladishev, Dmitry Borovikov                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCflush_nextchecks()
{
	typedef struct
	{
		zbx_uint64_t	objectid;
		time_t		clock;
	}
	t_oid_clock;

	const char	*__function_name = "DCflush_nextchecks";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid, itemid, *ids = NULL;
	char		*error_msg_esc = NULL;
	int		ids_alloc = 0, ids_num = 0, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (nextcheck_num == 0)
		return;

	/* dealing with items */
	for (i = 0; i < nextcheck_num; i++)
		uint64_array_add(&ids, &ids_alloc, &ids_num, nextchecks[i].itemid, 64);

	/* dealing with notsupported items */
	if (0 != ids_num)
	{
		char		*sql = NULL;
		int		sql_alloc = 4096, sql_offset = 0;
		t_oid_clock 	*events = NULL;
		int		events_alloc = 32, events_num = 0;

		sql = zbx_malloc(sql, sql_alloc);

		DBbegin();

		events = zbx_malloc(events, events_alloc * sizeof(t_oid_clock));

		/* preparing triggers */
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"select t.triggerid,i.itemid"
				" from triggers t,functions f,items i"
				" where t.triggerid=f.triggerid"
					" and f.itemid=i.itemid"
					" and t.status in (%d)"
					" and t.value not in (%d)"
					" and",
				TRIGGER_STATUS_ENABLED,
				TRIGGER_VALUE_UNKNOWN);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", ids, ids_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 22, " order by t.triggerid");
		result = DBselect("%s", sql);

		ids_num = 0;
		sql_offset = 0;
#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

		/* processing triggers */
		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(triggerid, row[0]);
			ZBX_STR2UINT64(itemid, row[1]);

			/* do not generate multiple unknown events for a trigger */
			if (SUCCEED == uint64_array_exists(ids, ids_num, triggerid))
				continue;

			uint64_array_add(&ids, &ids_alloc, &ids_num, triggerid, 64);

			/* index `i' will surely contain necessary itemid */
			i = get_nearestindex(nextchecks, sizeof(ZBX_DC_NEXTCHECK), nextcheck_num, itemid);

			if (i == nextcheck_num || nextchecks[i].itemid != itemid)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			error_msg_esc = DBdyn_escape_string_len(nextchecks[i].error_msg, TRIGGER_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128 + strlen(error_msg_esc),
					"update triggers set value=%d,lastchange=%d,error='%s' where triggerid=" ZBX_FS_UI64";\n",
							TRIGGER_VALUE_UNKNOWN,
							nextchecks[i].now,
							error_msg_esc,
							triggerid);
			zbx_free(error_msg_esc);

			if (events_num == events_alloc)
			{
				events_alloc *= 2;
				events = zbx_realloc(events, events_alloc * sizeof(t_oid_clock));
			}
			events[events_num].objectid = triggerid;
			events[events_num].clock = nextchecks[i].now;
			events_num++;

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
		DBfree_result(result);

		if (0 != events_num)
		{
			zbx_uint64_t	eventid;

			eventid = DBget_maxid_num("events", events_num);

			for (i = 0; i < events_num; i++)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
						"insert into events (eventid,source,object,objectid,clock,value) "
						"values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d);\n",
						eventid++,
						EVENT_SOURCE_TRIGGERS,
						EVENT_OBJECT_TRIGGER,
						events[i].objectid,
						events[i].clock,
						TRIGGER_VALUE_UNKNOWN);

				DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
			}
		}

		zbx_free(events);

#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

		if (sql_offset > 16)	/* In ORACLE always present begin..end; */
			DBexecute("%s", sql);

		DBcommit();

		zbx_free(sql);
	}

	zbx_free(ids);

	DCrelease_nextchecks();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
