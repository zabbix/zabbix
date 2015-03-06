/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	time_t		now;
	char		*error_msg;
}
ZBX_DC_NEXTCHECK;

static ZBX_DC_NEXTCHECK	*nextchecks = NULL;
static int		nextcheck_allocated = 64;
static int		nextcheck_num;

/******************************************************************************
 *                                                                            *
 * Function: DCinit_nextchecks                                                *
 *                                                                            *
 * Purpose: initialize nextchecks array                                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	DCinit_nextchecks()
{
	const char	*__function_name = "DCinit_nextchecks";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == nextchecks)
		nextchecks = zbx_malloc(nextchecks, nextcheck_allocated * sizeof(ZBX_DC_NEXTCHECK));

	nextcheck_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCrelease_nextchecks                                             *
 *                                                                            *
 * Purpose: free memory allocated for `error_msg'es                           *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 ******************************************************************************/
static void	DCrelease_nextchecks()
{
	const char	*__function_name = "DCrelease_nextchecks";

	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < nextcheck_num; i++)
		zbx_free(nextchecks[i].error_msg);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_nextcheck                                                  *
 *                                                                            *
 * Purpose: add item nextcheck to the array                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	DCadd_nextcheck(zbx_uint64_t itemid, time_t now, const char *error_msg)
{
	const char	*__function_name = "DCadd_nextcheck";

	int		i;
	size_t		sz;

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
			nextcheck_num--;
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
 * Purpose: update triggers to UNKNOWN and generate events                    *
 *                                                                            *
 * Author: Alexander Vladishev, Dmitry Borovikov                              *
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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() nextcheck_num:%d", __function_name, nextcheck_num);

	if (0 == nextcheck_num)
		goto exit;

	itemids = zbx_malloc(itemids, nextcheck_num * sizeof(zbx_uint64_t));
	timespecs = zbx_malloc(timespecs, nextcheck_num * sizeof(zbx_timespec_t));
	errors = zbx_malloc(errors, nextcheck_num * sizeof(char *));

	for (i = 0; i < nextcheck_num; i++)
	{
		itemids[i] = nextchecks[i].itemid;

		timespecs[i].sec = nextchecks[i].now;
		timespecs[i].ns = 0;

		errors[i] = nextchecks[i].error_msg;
	}

	zbx_hashset_create(&trigger_info, MAX(100, 2 * nextcheck_num),
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&trigger_order);
	zbx_vector_ptr_reserve(&trigger_order, nextcheck_num);

	DCconfig_get_triggers_by_itemids(&trigger_info, &trigger_order, itemids, timespecs, errors, nextcheck_num);

	zbx_free(itemids);
	zbx_free(timespecs);
	zbx_free(errors);

	if (0 != trigger_order.values_num)
	{
		typedef struct
		{
			zbx_uint64_t	objectid;
			zbx_timespec_t	timespec;
		}
		objectid_clock_t;

		char			*sql = NULL;
		size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
		objectid_clock_t 	*events = NULL;
		int			events_alloc, events_num = 0;
		DC_TRIGGER		*trigger;
		char			*error_esc;

		sql = zbx_malloc(sql, sql_alloc);

		events_alloc = trigger_order.values_num;
		events = zbx_malloc(events, events_alloc * sizeof(objectid_clock_t));

		DBbegin();

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < trigger_order.values_num; i++)
		{
			trigger = (DC_TRIGGER *)trigger_order.values[i];

			if (TRIGGER_VALUE_FLAG_UNKNOWN != trigger->value_flags)
			{
				DCconfig_set_trigger_value(trigger->triggerid, trigger->value,
						TRIGGER_VALUE_FLAG_UNKNOWN, trigger->new_error);

				error_esc = DBdyn_escape_string_len(trigger->new_error, TRIGGER_ERROR_LEN);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update triggers"
						" set value_flags=%d,"
							"error='%s'"
						" where triggerid=" ZBX_FS_UI64 ";\n",
						TRIGGER_VALUE_FLAG_UNKNOWN,
						error_esc,
						trigger->triggerid);
				zbx_free(error_esc);

				events[events_num].objectid = trigger->triggerid;
				events[events_num].timespec = trigger->timespec;
				events_num++;

				DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
			}

			zbx_free(trigger->expression);
			zbx_free(trigger->new_error);
		}

		if (0 != events_num)
		{
			zbx_uint64_t	eventid;

			eventid = DBget_maxid_num("events", events_num);

			for (i = 0; i < events_num; i++)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"insert into events (eventid,source,object,objectid,clock,ns,"
							"value,value_changed)"
						" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d,%d,%d);\n",
						eventid++,
						EVENT_SOURCE_TRIGGERS,
						EVENT_OBJECT_TRIGGER,
						events[i].objectid,
						events[i].timespec.sec,
						events[i].timespec.ns,
						TRIGGER_VALUE_UNKNOWN,
						TRIGGER_VALUE_CHANGED_NO);

				DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
			}
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (sql_offset > 16)	/* In ORACLE always present begin..end; */
			DBexecute("%s", sql);

		DBcommit();

		zbx_free(sql);
		zbx_free(events);
	}

	zbx_hashset_destroy(&trigger_info);
	zbx_vector_ptr_destroy(&trigger_order);

	DCrelease_nextchecks();
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
