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
#include "dbconfig.h"
#include "dbsync.h"

#include "zbx_host_constants.h"
#include "zbx_trigger_constants.h"
#include "zbxeval.h"
#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxtime.h"

typedef struct
{
	zbx_uint64_t			hostid;
	const zbx_dc_maintenance_t	*maintenance;
}
zbx_host_maintenance_t;

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_vector_ptr_t	maintenances;
}
zbx_host_event_maintenance_t;

ZBX_PTR_VECTOR_IMPL(host_maintenance_diff_ptr, zbx_host_maintenance_diff_t*)

void	zbx_host_maintenance_diff_free(zbx_host_maintenance_diff_t *hmd)
{
	zbx_free(hmd);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates maintenances in configuration cache                       *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - maintenanceid                                                *
 *           1 - maintenance_type                                             *
 *           2 - active_since                                                 *
 *           3 - active_till                                                  *
 *           4 - tags_evaltype                                                *
 *                                                                            *
 ******************************************************************************/
void	DCsync_maintenances(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_uint64_t		maintenanceid;
	zbx_dc_maintenance_t	*maintenance;
	int			found, ret;
	zbx_dc_config_t		*config = get_dc_config();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		config->maintenance_update |= ZBX_FLAG_MAINTENANCE_UPDATE_MAINTENANCE;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(maintenanceid, row[0]);

		maintenance = (zbx_dc_maintenance_t *)DCfind_id(&config->maintenances, maintenanceid,
				sizeof(zbx_dc_maintenance_t), &found);

		if (0 == found)
		{
			maintenance->state = ZBX_MAINTENANCE_IDLE;
			maintenance->running_since = 0;
			maintenance->running_until = 0;

			zbx_vector_uint64_create_ext(&maintenance->groupids, config->maintenances.mem_malloc_func,
					config->maintenances.mem_realloc_func, config->maintenances.mem_free_func);
			zbx_vector_uint64_create_ext(&maintenance->hostids, config->maintenances.mem_malloc_func,
					config->maintenances.mem_realloc_func, config->maintenances.mem_free_func);
			zbx_vector_ptr_create_ext(&maintenance->tags, config->maintenances.mem_malloc_func,
					config->maintenances.mem_realloc_func, config->maintenances.mem_free_func);
			zbx_vector_ptr_create_ext(&maintenance->periods, config->maintenances.mem_malloc_func,
					config->maintenances.mem_realloc_func, config->maintenances.mem_free_func);
		}

		ZBX_STR2UCHAR(maintenance->type, row[1]);
		ZBX_STR2UCHAR(maintenance->tags_evaltype, row[4]);
		maintenance->active_since = atoi(row[2]);
		maintenance->active_until = atoi(row[3]);
	}

	/* remove deleted maintenances */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances, &rowid)))
			continue;

		zbx_vector_uint64_destroy(&maintenance->groupids);
		zbx_vector_uint64_destroy(&maintenance->hostids);
		zbx_vector_ptr_destroy(&maintenance->tags);
		zbx_vector_ptr_destroy(&maintenance->periods);

		zbx_hashset_remove_direct(&config->maintenances, maintenance);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare maintenance tags by tag name for sorting                  *
 *                                                                            *
 ******************************************************************************/
static int	dc_compare_maintenance_tags(const void *d1, const void *d2)
{
	const zbx_dc_maintenance_tag_t	*tag1 = *(const zbx_dc_maintenance_tag_t * const *)d1;
	const zbx_dc_maintenance_tag_t	*tag2 = *(const zbx_dc_maintenance_tag_t * const *)d2;

	return strcmp(tag1->tag, tag2->tag);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates maintenance tags in configuration cache                   *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - maintenancetagid                                             *
 *           1 - maintenanceid                                                *
 *           2 - operator                                                     *
 *           3 - tag                                                          *
 *           4 - value                                                        *
 *                                                                            *
 ******************************************************************************/
void	DCsync_maintenance_tags(zbx_dbsync_t *sync)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;
	zbx_uint64_t			maintenancetagid, maintenanceid;
	zbx_dc_maintenance_tag_t	*maintenance_tag;
	zbx_dc_maintenance_t		*maintenance;
	zbx_vector_ptr_t		maintenances;
	int				found, ret, index, i;
	zbx_dc_config_t			*config = get_dc_config();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_ptr_create(&maintenances);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		config->maintenance_update |= ZBX_FLAG_MAINTENANCE_UPDATE_MAINTENANCE;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(maintenanceid, row[1]);
		if (NULL == (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
				&maintenanceid)))
		{
			continue;
		}

		ZBX_STR2UINT64(maintenancetagid, row[0]);
		maintenance_tag = (zbx_dc_maintenance_tag_t *)DCfind_id(&config->maintenance_tags, maintenancetagid,
				sizeof(zbx_dc_maintenance_tag_t), &found);

		maintenance_tag->maintenanceid = maintenanceid;
		ZBX_STR2UCHAR(maintenance_tag->op, row[2]);
		dc_strpool_replace(found, &maintenance_tag->tag, row[3]);
		dc_strpool_replace(found, &maintenance_tag->value, row[4]);

		if (0 == found)
			zbx_vector_ptr_append(&maintenance->tags, maintenance_tag);

		zbx_vector_ptr_append(&maintenances, maintenance);
	}

	/* remove deleted maintenance tags */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (maintenance_tag = (zbx_dc_maintenance_tag_t *)zbx_hashset_search(&config->maintenance_tags,
				&rowid)))
		{
			continue;
		}

		if (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
				&maintenance_tag->maintenanceid)))
		{
			index = zbx_vector_ptr_search(&maintenance->tags, &maintenance_tag->maintenancetagid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL != index)
				zbx_vector_ptr_remove_noorder(&maintenance->tags, index);

			zbx_vector_ptr_append(&maintenances, maintenance);
		}

		dc_strpool_release(maintenance_tag->tag);
		dc_strpool_release(maintenance_tag->value);

		zbx_hashset_remove_direct(&config->maintenance_tags, maintenance_tag);
	}

	/* sort maintenance tags */

	zbx_vector_ptr_sort(&maintenances, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(&maintenances, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < maintenances.values_num; i++)
	{
		maintenance = (zbx_dc_maintenance_t *)maintenances.values[i];
		zbx_vector_ptr_sort(&maintenance->tags, dc_compare_maintenance_tags);
	}

	zbx_vector_ptr_destroy(&maintenances);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates maintenance period in configuration cache                 *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - timeperiodid                                                 *
 *           1 - timeperiod_type                                              *
 *           2 - every                                                        *
 *           3 - month                                                        *
 *           4 - dayofweek                                                    *
 *           5 - day                                                          *
 *           6 - start_time                                                   *
 *           7 - period                                                       *
 *           8 - start_date                                                   *
 *           9 - maintenanceid                                                *
 *                                                                            *
 ******************************************************************************/
void	DCsync_maintenance_periods(zbx_dbsync_t *sync)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;
	zbx_uint64_t			periodid, maintenanceid;
	zbx_dc_maintenance_period_t	*period;
	zbx_dc_maintenance_t		*maintenance;
	int				found, ret, index;
	zbx_dc_config_t			*config = get_dc_config();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		config->maintenance_update |= ZBX_FLAG_MAINTENANCE_UPDATE_MAINTENANCE|ZBX_FLAG_MAINTENANCE_UPDATE_PERIOD;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(maintenanceid, row[9]);
		if (NULL == (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
				&maintenanceid)))
		{
			continue;
		}

		ZBX_STR2UINT64(periodid, row[0]);
		period = (zbx_dc_maintenance_period_t *)DCfind_id(&config->maintenance_periods, periodid,
				sizeof(zbx_dc_maintenance_period_t), &found);

		period->maintenanceid = maintenanceid;
		ZBX_STR2UCHAR(period->type, row[1]);
		period->every = atoi(row[2]);
		period->month = atoi(row[3]);
		period->dayofweek = atoi(row[4]);
		period->day = atoi(row[5]);
		period->start_time = atoi(row[6]);
		period->period = atoi(row[7]);
		period->start_date = atoi(row[8]);

		if (0 == found)
			zbx_vector_ptr_append(&maintenance->periods, period);
	}

	/* remove deleted maintenance tags */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (period = (zbx_dc_maintenance_period_t *)zbx_hashset_search(&config->maintenance_periods,
				&rowid)))
		{
			continue;
		}

		if (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
				&period->maintenanceid)))
		{
			index = zbx_vector_ptr_search(&maintenance->periods, &period->timeperiodid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL != index)
				zbx_vector_ptr_remove_noorder(&maintenance->periods, index);
		}

		zbx_hashset_remove_direct(&config->maintenance_periods, period);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates maintenance groups in configuration cache                 *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - maintenanceid                                                *
 *           1 - groupid                                                      *
 *                                                                            *
 ******************************************************************************/
void	DCsync_maintenance_groups(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_dc_maintenance_t	*maintenance = NULL;
	int			index, ret;
	zbx_uint64_t		last_maintenanceid = 0, maintenanceid, groupid;
	zbx_dc_config_t		*config = get_dc_config();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		config->maintenance_update |= ZBX_FLAG_MAINTENANCE_UPDATE_MAINTENANCE;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(maintenanceid, row[0]);

		if (last_maintenanceid != maintenanceid || 0 == last_maintenanceid)
		{
			if (NULL == (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
					&maintenanceid)))
			{
				continue;
			}
			last_maintenanceid = maintenanceid;
		}

		ZBX_STR2UINT64(groupid, row[1]);

		zbx_vector_uint64_append(&maintenance->groupids, groupid);
	}

	/* remove deleted maintenance groupids from cache */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_STR2UINT64(maintenanceid, row[0]);

		if (NULL == (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
				&maintenanceid)))
		{
			continue;
		}
		ZBX_STR2UINT64(groupid, row[1]);

		if (FAIL == (index = zbx_vector_uint64_search(&maintenance->groupids, groupid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			continue;
		}

		zbx_vector_uint64_remove_noorder(&maintenance->groupids, index);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates maintenance hosts in configuration cache                  *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - maintenanceid                                                *
 *           1 - hostid                                                       *
 *                                                                            *
 ******************************************************************************/
void	DCsync_maintenance_hosts(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_vector_ptr_t	maintenances;
	zbx_dc_maintenance_t	*maintenance = NULL;
	int			index, ret, i;
	zbx_uint64_t		last_maintenanceid, maintenanceid, hostid;
	zbx_dc_config_t		*config = get_dc_config();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_ptr_create(&maintenances);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		config->maintenance_update |= ZBX_FLAG_MAINTENANCE_UPDATE_MAINTENANCE;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(maintenanceid, row[0]);

		if (NULL == maintenance || last_maintenanceid != maintenanceid)
		{
			if (NULL == (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
					&maintenanceid)))
			{
				continue;
			}
			last_maintenanceid = maintenanceid;
		}

		ZBX_STR2UINT64(hostid, row[1]);

		zbx_vector_uint64_append(&maintenance->hostids, hostid);
		zbx_vector_ptr_append(&maintenances, maintenance);
	}

	/* remove deleted maintenance hostids from cache */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		ZBX_STR2UINT64(maintenanceid, row[0]);

		if (NULL == (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
				&maintenanceid)))
		{
			continue;
		}
		ZBX_STR2UINT64(hostid, row[1]);

		if (FAIL == (index = zbx_vector_uint64_search(&maintenance->hostids, hostid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			continue;
		}

		zbx_vector_uint64_remove_noorder(&maintenance->hostids, index);
		zbx_vector_ptr_append(&maintenances, maintenance);
	}

	zbx_vector_ptr_sort(&maintenances, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(&maintenances, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < maintenances.values_num; i++)
	{
		maintenance = (zbx_dc_maintenance_t *)maintenances.values[i];
		zbx_vector_uint64_sort(&maintenance->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_ptr_destroy(&maintenances);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: subtract two local times with DST correction                      *
 *                                                                            *
 * Parameter: minuend       - [IN] the minuend time                           *
 *            subtrahend    - [IN] the subtrahend time (may be negative)      *
 *            tm            - [OUT] the struct tm                             *
 *                                                                            *
 * Return value: the resulting time difference in seconds                     *
 *                                                                            *
 ******************************************************************************/
static time_t dc_subtract_time(time_t minuend, int subtrahend, struct tm *tm)
{
	time_t	diff, offset_min, offset_diff;

	offset_min = zbx_get_timezone_offset(minuend, tm);
	diff = minuend - subtrahend;
	offset_diff = zbx_get_timezone_offset(diff, tm);
	diff -= offset_diff - offset_min;

	return diff;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate start time for the specified maintenance period         *
 *                                                                            *
 * Parameter: maintenance   - [IN] the maintenance                            *
 *            period        - [IN] the maintenance period                     *
 *            start_date    - [IN] the period starting timestamp based on     *
 *                                 current time                               *
 *            running_since - [OUT] the actual period starting timestamp      *
 *            running_until - [OUT] the actual period ending timestamp        *
 *                                                                            *
 * Return value: SUCCEED - a valid period was found                           *
 *               FAIL    - period started before maintenance activation time  *
 *                                                                            *
 ******************************************************************************/
static int	dc_calculate_maintenance_period(const zbx_dc_maintenance_t *maintenance,
		const zbx_dc_maintenance_period_t *period, time_t start_date, time_t *running_since,
		time_t *running_until)
{
	int		day, wday, week;
	struct tm	tm;
	time_t		active_since = maintenance->active_since;

	if (TIMEPERIOD_TYPE_ONETIME == period->type)
	{
		*running_since = (period->start_date < active_since ? active_since : period->start_date);
		*running_until = period->start_date + period->period;
		if (maintenance->active_until < *running_until)
			*running_until = maintenance->active_until;

		return SUCCEED;
	}

	switch (period->type)
	{
		case TIMEPERIOD_TYPE_DAILY:
			if (start_date < active_since)
				return FAIL;

			tm = *localtime(&active_since);
			active_since = dc_subtract_time(active_since,
					tm.tm_hour * SEC_PER_HOUR + tm.tm_min * SEC_PER_MIN + tm.tm_sec, &tm);

			day = (start_date - active_since) / SEC_PER_DAY;
			start_date = dc_subtract_time(start_date, SEC_PER_DAY * (day % period->every), &tm);
			break;
		case TIMEPERIOD_TYPE_WEEKLY:
			if (start_date < active_since)
				return FAIL;

			tm = *localtime(&active_since);
			wday = (0 == tm.tm_wday ? 7 : tm.tm_wday) - 1;
			active_since = dc_subtract_time(active_since, wday * SEC_PER_DAY +
					tm.tm_hour * SEC_PER_HOUR + tm.tm_min * SEC_PER_MIN + tm.tm_sec, &tm);

			for (; start_date >= active_since; start_date = dc_subtract_time(start_date, SEC_PER_DAY, &tm))
			{
				/* check for every x week(s) */
				week = (start_date - active_since) / SEC_PER_WEEK;
				if (0 != week % period->every)
					continue;

				/* check for day of the week */
				tm = *localtime(&start_date);
				wday = (0 == tm.tm_wday ? 7 : tm.tm_wday) - 1;
				if (0 == (period->dayofweek & (1 << wday)))
					continue;

				break;
			}
			break;
		case TIMEPERIOD_TYPE_MONTHLY:
			for (; start_date >= active_since; start_date = dc_subtract_time(start_date, SEC_PER_DAY, &tm))
			{
				/* check for month */
				tm = *localtime(&start_date);
				if (0 == (period->month & (1 << tm.tm_mon)))
					continue;

				if (0 != period->day)
				{
					/* check for day of the month */
					if (period->day != tm.tm_mday)
						continue;
				}
				else
				{
					/* check for day of the week */
					wday = (0 == tm.tm_wday ? 7 : tm.tm_wday) - 1;
					if (0 == (period->dayofweek & (1 << wday)))
						continue;

					/* check for number of day (first, second, third, fourth or last) */
					day = (tm.tm_mday - 1) / 7 + 1;
					if (5 == period->every && 4 == day)
					{
						if (tm.tm_mday + 7 <= zbx_day_in_month(1900 + tm.tm_year,
								tm.tm_mon + 1))
						{
							continue;
						}
					}
					else if (period->every != day)
						continue;
				}

				if (start_date < active_since)
					return FAIL;

				break;
			}
			break;
		default:
			return FAIL;
	}

	*running_since = start_date;
	*running_until = start_date + period->period;
	if (maintenance->active_until < *running_until)
		*running_until = maintenance->active_until;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates start time for the specified maintenance period and    *
 *          checks if we are inside the maintenance period                    *
 *                                                                            *
 * Parameter: maintenance   - [IN] the maintenance                            *
 *            period        - [IN] the maintenance period                     *
 *            now           - [IN] current time                               *
 *            running_since - [OUT] the actual period starting timestamp      *
 *            running_until - [OUT] the actual period ending timestamp        *
 *                                                                            *
 * Return value: SUCCEED - current time is inside valid maintenance period    *
 *               FAIL    - current time is outside valid maintenance period   *
 *                                                                            *
 ******************************************************************************/
static int	dc_check_maintenance_period(const zbx_dc_maintenance_t *maintenance,
		const zbx_dc_maintenance_period_t *period, time_t now, time_t *running_since, time_t *running_until)
{
	struct tm	tm;
	int		seconds, rc, ret = FAIL;
	time_t		period_start, period_end;

	tm = *localtime(&now);
	seconds = tm.tm_hour * SEC_PER_HOUR + tm.tm_min * SEC_PER_MIN + tm.tm_sec;
	period_start = dc_subtract_time(now, seconds, &tm);
	period_start = dc_subtract_time(period_start, -period->start_time, &tm);

	tm = *localtime(&period_start);

	/* skip maintenance if the time does not exist due to DST */
	if (period->start_time != (tm.tm_hour * SEC_PER_HOUR + tm.tm_min * SEC_PER_MIN + tm.tm_sec))
	{
		goto out;
	}

	if (now < period_start)
		period_start = dc_subtract_time(period_start, SEC_PER_DAY, &tm);

	rc = dc_calculate_maintenance_period(maintenance, period, period_start, &period_start, &period_end);

	if (SUCCEED == rc && period_start <= now && now < period_end)
	{
		*running_since = period_start;
		*running_until = period_end;
		ret = SUCCEED;
	}
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets maintenance update flags for all timers                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_maintenance_set_update_flags(void)
{
	size_t	slots_num, timers_left;

	slots_num = zbx_maintenance_update_flags_num();

	WRLOCK_CACHE;

	memset(get_dc_config()->maintenance_update_flags, 0xff, sizeof(zbx_uint64_t) * slots_num);

	if (0 != (timers_left = ((size_t)cacheconfig_get_config_forks(ZBX_PROCESS_TYPE_TIMER) %
			(sizeof(uint64_t) * 8))))
	{
		get_dc_config()->maintenance_update_flags[slots_num - 1] >>= (sizeof(zbx_uint64_t) * 8 - timers_left);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: resets maintenance update flags for the specified timer           *
 *                                                                            *
 * Parameters: timer - [IN] the timer process number                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_maintenance_reset_update_flag(int timer)
{
	int		slot, bit;
	zbx_uint64_t	mask;

	timer--;
	slot = timer / (sizeof(uint64_t) * 8);
	bit = timer % (sizeof(uint64_t) * 8);

	mask = ~(__UINT64_C(1) << bit);

	WRLOCK_CACHE;

	get_dc_config()->maintenance_update_flags[slot] &= mask;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the maintenance update flag is set for the specified    *
 *          timer                                                             *
 *                                                                            *
 * Parameters: timer - [IN] the timer process number                          *
 *                                                                            *
 * Return value: SUCCEED - maintenance update flag is set                     *
 *               FAIL    - otherwise                                          *
 ******************************************************************************/
int	zbx_dc_maintenance_check_update_flag(int timer)
{
	int		slot, bit, ret;
	zbx_uint64_t	mask;

	timer--;
	slot = timer / (sizeof(uint64_t) * 8);
	bit = timer % (sizeof(uint64_t) * 8);

	mask = __UINT64_C(1) << bit;

	RDLOCK_CACHE;

	ret = (0 == (get_dc_config()->maintenance_update_flags[slot] & mask) ? FAIL : SUCCEED);

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if at least one maintenance update flag is set             *
 *                                                                            *
 * Return value: SUCCEED - a maintenance update flag is set                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_maintenance_check_update_flags(void)
{
	size_t	slots_num;
	int	ret = SUCCEED;

	slots_num = zbx_maintenance_update_flags_num();

	RDLOCK_CACHE;

	if (0 != get_dc_config()->maintenance_update_flags[0])
		goto out;

	if (1 != slots_num)
	{
		if (0 != memcmp(get_dc_config()->maintenance_update_flags, get_dc_config()->maintenance_update_flags + 1,
				slots_num - 1))
		{
			goto out;
		}
	}

	ret = FAIL;
out:
	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if there are change to maintenance that require immediate  *
 *          update                                                            *
 *                                                                            *
 * Return value: SUCCEED - a maintenance immediate update flag is set         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_maintenance_check_immediate_update(void)
{
	int	ret;

	RDLOCK_CACHE;
	ret = 0 != (ZBX_FLAG_MAINTENANCE_UPDATE_PERIOD & get_dc_config()->maintenance_update) ? SUCCEED : FAIL;
	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update maintenance state depending on maintenance periods         *
 *                                                                            *
 * Return value: SUCCEED - maintenance status was changed, host/event update  *
 *                         must be performed                                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function calculates if any maintenance period is running    *
 *           and based on that sets current maintenance state - running/idle  *
 *           and period start/end time.                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_update_maintenances(zbx_maintenance_timer_t maintenance_timer)
{
	zbx_dc_maintenance_t		*maintenance;
	zbx_dc_maintenance_period_t	*period;
	zbx_hashset_iter_t		iter;
	int				i, running_num = 0, started_num = 0, stopped_num = 0, ret = FAIL;
	unsigned char			state;
	time_t				now, period_start, period_end, running_since, running_until;
	zbx_dc_config_t			*config = get_dc_config();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);

	WRLOCK_CACHE;

	/* force recalculation on configuration changes only periodically when timer expires */
	if (MAINTENANCE_TIMER_PENDING == maintenance_timer)
	{
		if (0 != (ZBX_FLAG_MAINTENANCE_UPDATE_MAINTENANCE & config->maintenance_update))
			ret = SUCCEED;
	}

	zbx_hashset_iter_reset(&config->maintenances, &iter);
	while (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_iter_next(&iter)))
	{
		state = ZBX_MAINTENANCE_IDLE;
		running_since = 0;
		running_until = 0;

		if (now >= maintenance->active_since && now < maintenance->active_until)
		{
			/* find the longest running maintenance period */
			for (i = 0; i < maintenance->periods.values_num; i++)
			{
				period = (zbx_dc_maintenance_period_t *)maintenance->periods.values[i];

				if (SUCCEED == dc_check_maintenance_period(maintenance, period, now, &period_start,
						&period_end))
				{
					state = ZBX_MAINTENANCE_RUNNING;
					if (period_end > running_until)
					{
						running_since = period_start;
						running_until = period_end;
					}
				}
			}
		}

		if (state == ZBX_MAINTENANCE_RUNNING)
		{
			if (ZBX_MAINTENANCE_IDLE == maintenance->state)
			{
				maintenance->running_since = running_since;
				maintenance->state = ZBX_MAINTENANCE_RUNNING;
				started_num++;

				/* Precache nested host groups for started maintenances.   */
				/* Nested host groups for running maintenances are already */
				/* precached during configuration cache synchronization.   */
				for (i = 0; i < maintenance->groupids.values_num; i++)
				{
					zbx_dc_hostgroup_t	*group;

					if (NULL != (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(
							&config->hostgroups, &maintenance->groupids.values[i])))
					{
						dc_hostgroup_cache_nested_groupids(group);
					}
				}
				ret = SUCCEED;
			}

			if (maintenance->running_until != running_until)
			{
				maintenance->running_until = running_until;
				ret = SUCCEED;
			}
			running_num++;
		}
		else
		{
			if (ZBX_MAINTENANCE_RUNNING == maintenance->state)
			{
				maintenance->running_since = 0;
				maintenance->running_until = 0;
				maintenance->state = ZBX_MAINTENANCE_IDLE;
				stopped_num++;
				ret = SUCCEED;
			}
		}
	}

	if (MAINTENANCE_TIMER_PENDING == maintenance_timer)
		config->maintenance_update = ZBX_FLAG_MAINTENANCE_UPDATE_NONE;
	else
		config->maintenance_update &= ~ZBX_FLAG_MAINTENANCE_UPDATE_PERIOD;

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() started:%d stopped:%d running:%d", __func__,
			started_num, stopped_num, running_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: assign maintenance to a host, host can only be in one maintenance *
 *                                                                            *
 * Parameters: host_maintenances - [OUT] host with maintenance                *
 *             maintenance       - [IN] maintenance that host is in           *
 *             hostid            - [IN] ID of the host                        *
 *                                                                            *
 ******************************************************************************/
static void	dc_assign_maintenance_to_host(zbx_hashset_t *host_maintenances, zbx_dc_maintenance_t *maintenance,
		zbx_uint64_t hostid)
{
	zbx_host_maintenance_t	*host_maintenance, host_maintenance_local;

	if (NULL == (host_maintenance = (zbx_host_maintenance_t *)zbx_hashset_search(host_maintenances, &hostid)))
	{
		host_maintenance_local.hostid = hostid;
		host_maintenance_local.maintenance = maintenance;

		zbx_hashset_insert(host_maintenances, &host_maintenance_local, sizeof(host_maintenance_local));
	}
	else if (MAINTENANCE_TYPE_NORMAL == host_maintenance->maintenance->type &&
			MAINTENANCE_TYPE_NODATA == maintenance->type)
	{
		host_maintenance->maintenance = maintenance;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: assign maintenance to a host that event belongs to, events can be *
 *          in multiple maintenances at a time                                *
 *                                                                            *
 * Parameters: host_event_maintenances - [OUT] host with maintenances         *
 *             maintenance             - [IN] maintenance that host is in     *
 *             hostid                  - [IN] ID of the host                  *
 *                                                                            *
 ******************************************************************************/
static void	dc_assign_event_maintenance_to_host(zbx_hashset_t *host_event_maintenances,
		zbx_dc_maintenance_t *maintenance, zbx_uint64_t hostid)
{
	zbx_host_event_maintenance_t	*host_event_maintenance;

	if (NULL == (host_event_maintenance = (zbx_host_event_maintenance_t *)zbx_hashset_search(
			host_event_maintenances, &hostid)))
	{
		return;
	}

	zbx_vector_ptr_append(&host_event_maintenance->maintenances, maintenance);
}

typedef void	(*assign_maintenance_to_host_f)(zbx_hashset_t *host_maintenances,
		zbx_dc_maintenance_t *maintenance, zbx_uint64_t hostid);

/******************************************************************************
 *                                                                            *
 * Purpose: get hosts and their maintenances                                  *
 *                                                                            *
 * Parameters: maintenanceids    - [IN] maintenance ids                       *
 *             host_maintenances - [OUT] maintenances running on hosts        *
 *             cb                - [IN] callback function                     *
 *                                                                            *
 ******************************************************************************/
static void	dc_get_host_maintenances_by_ids(const zbx_vector_uint64_t *maintenanceids,
		zbx_hashset_t *host_maintenances, assign_maintenance_to_host_f cb)
{
	zbx_dc_maintenance_t	*maintenance;
	int			i, j;
	zbx_vector_uint64_t	groupids;
	zbx_dc_config_t		*config = get_dc_config();

	zbx_vector_uint64_create(&groupids);

	for (i = 0; i < maintenanceids->values_num; i++)
	{
		if (NULL == (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_search(&config->maintenances,
				&maintenanceids->values[i])))
		{
			continue;
		}

		for (j = 0; j < maintenance->hostids.values_num; j++)
			cb(host_maintenances, maintenance, maintenance->hostids.values[j]);

		if (0 != maintenance->groupids.values_num)	/* hosts groups */
		{
			zbx_dc_hostgroup_t	*group;

			for (j = 0; j < maintenance->groupids.values_num; j++)
				dc_get_nested_hostgroupids(maintenance->groupids.values[j], &groupids);

			zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			for (j = 0; j < groupids.values_num; j++)
			{
				zbx_hashset_iter_t	iter;
				zbx_uint64_t		*phostid;

				if (NULL == (group = (zbx_dc_hostgroup_t *)zbx_hashset_search(&config->hostgroups,
						&groupids.values[j])))
				{
					continue;
				}

				zbx_hashset_iter_reset(&group->hostids, &iter);

				while (NULL != (phostid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
					cb(host_maintenances, maintenance, *phostid);
			}

			zbx_vector_uint64_clear(&groupids);
		}
	}

	zbx_vector_uint64_destroy(&groupids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets maintenance updates for all hosts                            *
 *                                                                            *
 * Parameters: host_maintenances - [IN] maintenances running on hosts         *
 *             updates           - [OUT] updates to be applied                *
 *                                                                            *
 ******************************************************************************/
static void	dc_get_host_maintenance_updates(const zbx_hashset_t *host_maintenances,
		zbx_vector_host_maintenance_diff_ptr_t *updates)
{
	zbx_hashset_iter_t		iter;
	ZBX_DC_HOST			*host;
	int				maintenance_from;
	unsigned char			maintenance_status, maintenance_type;
	zbx_uint64_t			maintenanceid;
	zbx_host_maintenance_diff_t	*diff;
	unsigned int			flags;
	const zbx_host_maintenance_t	*host_maintenance;

	zbx_hashset_iter_reset(&(get_dc_config())->hosts, &iter);

	while (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL != (host_maintenance = zbx_hashset_search(host_maintenances, &host->hostid)))
		{
			maintenance_status = HOST_MAINTENANCE_STATUS_ON;
			maintenance_type = host_maintenance->maintenance->type;
			maintenanceid = host_maintenance->maintenance->maintenanceid;
			maintenance_from = host_maintenance->maintenance->running_since;
		}
		else
		{
			maintenance_status = HOST_MAINTENANCE_STATUS_OFF;
			maintenance_type = MAINTENANCE_TYPE_NORMAL;
			maintenanceid = 0;
			maintenance_from = 0;
		}

		flags = 0;

		if (maintenanceid != host->maintenanceid)
			flags |= ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCEID;

		if (maintenance_status != host->maintenance_status)
			flags |= ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_STATUS;

		if (maintenance_from != host->maintenance_from)
			flags |= ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_FROM;

		if (maintenance_type != host->maintenance_type)
			flags |= ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_TYPE;

		if (0 != flags)
		{
			diff = (zbx_host_maintenance_diff_t *)zbx_malloc(0, sizeof(zbx_host_maintenance_diff_t));
			diff->flags = flags;
			diff->hostid = host->hostid;
			diff->maintenanceid = maintenanceid;
			diff->maintenance_status = maintenance_status;
			diff->maintenance_from = maintenance_from;
			diff->maintenance_type = maintenance_type;
			zbx_vector_host_maintenance_diff_ptr_append(updates, diff);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush host maintenance updates to configuration cache             *
 *                                                                            *
 * Parameters: updates - [IN] updates to flush                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_flush_host_maintenance_updates(const zbx_vector_host_maintenance_diff_ptr_t *updates)
{
	int	now = time(NULL);

	WRLOCK_CACHE;

	for (int i = 0; i < updates->values_num; i++)
	{
		ZBX_DC_HOST				*host;
		int					maintenance_without_data = 0;
		const zbx_host_maintenance_diff_t	*diff = updates->values[i];

		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&(get_dc_config())->hosts, &diff->hostid)))
			continue;

		if (HOST_MAINTENANCE_STATUS_ON == host->maintenance_status &&
				MAINTENANCE_TYPE_NODATA == host->maintenance_type)
		{
			maintenance_without_data = 1;
		}

		if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCEID))
			host->maintenanceid = diff->maintenanceid;

		if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_TYPE))
			host->maintenance_type = diff->maintenance_type;

		if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_STATUS))
			host->maintenance_status = diff->maintenance_status;

		if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_FROM))
			host->maintenance_from = diff->maintenance_from;

		if (1 == maintenance_without_data && (HOST_MAINTENANCE_STATUS_ON != host->maintenance_status ||
				MAINTENANCE_TYPE_NODATA != host->maintenance_type))
		{
			/* Store time at which no-data maintenance ended for the host (either */
			/* because no-data maintenance ended or because maintenance type was  */
			/* changed to normal), this is needed for nodata() trigger function.  */
			host->data_expected_from = now;
		}
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates required host maintenance updates based on specified   *
 *          maintenances                                                      *
 *                                                                            *
 * Parameters: maintenanceids   - [IN] identifiers of maintenances to process *
 *             updates          - [OUT] pending updates                       *
 *                                                                            *
 * Comments: This function must be called after zbx_dc_update_maintenances()  *
 *           function has updated maintenance state in configuration cache.   *
 *           To be able to work with lazy nested group caching and read locks *
 *           all nested groups used in maintenances must be already precached *
 *           before calling this function.                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_host_maintenance_updates(const zbx_vector_uint64_t *maintenanceids,
		zbx_vector_host_maintenance_diff_ptr_t *updates)
{
	zbx_hashset_t	host_maintenances;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create(&host_maintenances, maintenanceids->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	RDLOCK_CACHE;

	dc_get_host_maintenances_by_ids(maintenanceids, &host_maintenances, dc_assign_maintenance_to_host);

	/* host maintenance update must be performed even without running maintenances */
	/* to reset host maintenances status for stopped maintenances                  */
	dc_get_host_maintenance_updates(&host_maintenances, updates);

	UNLOCK_CACHE;

	zbx_hashset_destroy(&host_maintenances);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() updates:%d", __func__, updates->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform maintenance tag comparison using maintenance tag operator *
 *                                                                            *
 ******************************************************************************/
static int	dc_maintenance_tag_value_match(const zbx_dc_maintenance_tag_t *mt, const zbx_tag_t *tag)
{
	switch (mt->op)
	{
		case ZBX_MAINTENANCE_TAG_OPERATOR_LIKE:
			return (NULL != strstr(tag->value, mt->value) ? SUCCEED : FAIL);
		case ZBX_MAINTENANCE_TAG_OPERATOR_EQUAL:
			return (0 == strcmp(tag->value, mt->value) ? SUCCEED : FAIL);
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches tags with [*mt_pos] maintenance tag name                  *
 *                                                                            *
 * Parameters: mtags    - [IN] the maintenance tags, sorted by tag names      *
 *             etags    - [IN] the event tags, sorted by tag names            *
 *             mt_pos   - [IN/OUT] the next maintenance tag index             *
 *             et_pos   - [IN/OUT] the next event tag index                   *
 *                                                                            *
 * Return value: SUCCEED - found matching tag                                 *
 *               FAIL    - no matching tags found                             *
 *                                                                            *
 ******************************************************************************/
static int	dc_maintenance_match_tag_range(const zbx_vector_ptr_t *mtags, const zbx_vector_tags_ptr_t *etags,
		int *mt_pos, int *et_pos)
{
	const zbx_dc_maintenance_tag_t	*mtag;
	const zbx_tag_t			*etag;
	const char			*name;
	int				i, j, ret, mt_start, mt_end, et_start, et_end;

	/* get the maintenance tag name */
	mtag = (const zbx_dc_maintenance_tag_t *)mtags->values[*mt_pos];
	name = mtag->tag;

	/* find maintenance and event tag ranges matching the first maintenance tag name */
	/* (maintenance tag range [mt_start,mt_end], event tag range [et_start,et_end])  */

	mt_start = *mt_pos;
	et_start = *et_pos;

	/* find last maintenance tag with the required name */

	for (i = mt_start + 1; i < mtags->values_num; i++)
	{
		mtag = (const zbx_dc_maintenance_tag_t *)mtags->values[i];
		if (0 != strcmp(mtag->tag, name))
			break;
	}
	mt_end = i - 1;
	*mt_pos = i;

	/* find first event tag with the required name */

	for (i = et_start; i < etags->values_num; i++)
	{
		etag = etags->values[i];
		if (0 < (ret = strcmp(etag->tag, name)))
		{
			*et_pos = i;
			return FAIL;
		}

		if (0 == ret)
			break;
	}

	if (i == etags->values_num)
	{
		*et_pos = i;
		return FAIL;
	}

	et_start = i++;

	/* find last event tag with the required name */

	for (; i < etags->values_num; i++)
	{
		etag = etags->values[i];
		if (0 != strcmp(etag->tag, name))
			break;
	}

	et_end = i - 1;
	*et_pos = i;

	/* cross-compare maintenance and event tags within the found ranges */

	for (i = mt_start; i <= mt_end; i++)
	{
		mtag = (const zbx_dc_maintenance_tag_t *)mtags->values[i];

		for (j = et_start; j <= et_end; j++)
		{
			etag = etags->values[j];
			if (SUCCEED == dc_maintenance_tag_value_match(mtag, etag))
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches maintenance and event tags using OR eval type             *
 *                                                                            *
 * Parameters: mtags    - [IN] the maintenance tags, sorted by tag names      *
 *             etags    - [IN] the event tags, sorted by tag names            *
 *                                                                            *
 * Return value: SUCCEED - event tags matches maintenance                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dc_maintenance_match_tags_or(const zbx_dc_maintenance_t *maintenance, const zbx_vector_tags_ptr_t *tags)
{
	int	mt_pos = 0, et_pos = 0;

	while (mt_pos < maintenance->tags.values_num && et_pos < tags->values_num)
	{
		if (SUCCEED == dc_maintenance_match_tag_range(&maintenance->tags, tags, &mt_pos, &et_pos))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches maintenance and event tags using AND/OR eval type         *
 *                                                                            *
 * Parameters: mtags    - [IN] the maintenance tags, sorted by tag names      *
 *             etags    - [IN] the event tags, sorted by tag names            *
 *                                                                            *
 * Return value: SUCCEED - event tags matches maintenance                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dc_maintenance_match_tags_andor(const zbx_dc_maintenance_t *maintenance,
		const zbx_vector_tags_ptr_t *tags)
{
	int	mt_pos = 0, et_pos = 0;

	while (mt_pos < maintenance->tags.values_num && et_pos < tags->values_num)
	{
		if (FAIL == dc_maintenance_match_tag_range(&maintenance->tags, tags, &mt_pos, &et_pos))
			return FAIL;
	}

	if (mt_pos != maintenance->tags.values_num)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the tags must be processed by the specified maintenance  *
 *                                                                            *
 * Parameters: maintenance - [IN] the maintenance                             *
 *             tags        - [IN] the tags to check                           *
 *                                                                            *
 * Return value: SUCCEED - the tags must be processed by the maintenance      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dc_maintenance_match_tags(const zbx_dc_maintenance_t *maintenance, const zbx_vector_tags_ptr_t *tags)
{
	switch (maintenance->tags_evaltype)
	{
		case ZBX_MAINTENANCE_TAG_EVAL_TYPE_AND_OR:
			/* break; is not missing here */
		case ZBX_MAINTENANCE_TAG_EVAL_TYPE_OR:
			if (0 == maintenance->tags.values_num)
				return SUCCEED;

			if (0 == tags->values_num)
				return FAIL;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	if (ZBX_MAINTENANCE_TAG_EVAL_TYPE_AND_OR == maintenance->tags_evaltype)
		return dc_maintenance_match_tags_andor(maintenance, tags);
	else
		return dc_maintenance_match_tags_or(maintenance, tags);
}

static void	host_event_maintenance_clean(zbx_host_event_maintenance_t *host_event_maintenance)
{
	zbx_vector_ptr_destroy(&host_event_maintenance->maintenances);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get maintenance data for events                                   *
 *                                                                            *
 * Parameters: event_queries -  [IN/OUT] in - event data                      *
 *                                       out - running maintenances for each  *
 *                                            event                           *
 *             maintenanceids - [IN] the maintenances to process              *
 *                                                                            *
 * Return value: SUCCEED - at least one matching maintenance was found        *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_event_maintenances(zbx_vector_event_suppress_query_ptr_t *event_queries,
		const zbx_vector_uint64_t *maintenanceids)
{
	zbx_hashset_t			host_event_maintenances;
	int				i, j, k, ret = FAIL;
	zbx_event_suppress_query_t	*query;
	ZBX_DC_ITEM			*item;
	ZBX_DC_FUNCTION			*function;
	zbx_hashset_iter_t		iter;
	zbx_host_event_maintenance_t	*host_event_maintenance;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create_ext(&host_event_maintenances, maintenanceids->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)host_event_maintenance_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	/* event tags must be sorted by name to perform maintenance tag matching */

	for (i = 0; i < event_queries->values_num; i++)
	{
		query = event_queries->values[i];
		if (0 != query->tags.values_num)
			zbx_vector_tags_ptr_sort(&query->tags, zbx_compare_tags);
	}

	RDLOCK_CACHE;

	for (i = 0; i < event_queries->values_num; i++)
	{
		query = event_queries->values[i];

		/* find hostids of items used in event trigger expressions */

		/* Some processes do not have trigger data at hand and create event queries */
		/* without filling query functionids. Do it here if necessary.              */
		if (0 == query->functionids.values_num)
		{
			ZBX_DC_TRIGGER	*trigger;

			if (NULL == (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_search(&(get_dc_config())->triggers,
					&query->triggerid)))
			{
				continue;
			}

			if (ZBX_FLAG_DISCOVERY_PROTOTYPE == trigger->flags)
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot process event for trigger prototype"
						" (triggerid:" ZBX_FS_UI64 ")", trigger->triggerid);
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			zbx_get_serialized_expression_functionids(trigger->expression, trigger->expression_bin,
					&query->functionids);

			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == trigger->recovery_mode)
			{
				zbx_get_serialized_expression_functionids(trigger->recovery_expression,
						trigger->recovery_expression_bin, &query->functionids);
			}
		}

		for (j = 0; j < query->functionids.values_num; j++)
		{
			ZBX_DC_HOST	*dc_host;

			if (NULL == (function = (ZBX_DC_FUNCTION *)zbx_hashset_search(&(get_dc_config())->functions,
					&query->functionids.values[j])))
			{
				continue;
			}

			if (NULL == (item = (ZBX_DC_ITEM *)zbx_hashset_search(&(get_dc_config())->items,
					&function->itemid)))
			{
				continue;
			}
			if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&(get_dc_config())->hosts,
					&item->hostid)))
			{
				continue;
			}

			if (HOST_MAINTENANCE_STATUS_OFF == dc_host->maintenance_status)
			{
				zbx_vector_uint64_clear(&query->hostids);
				break;
			}

			zbx_vector_uint64_append(&query->hostids, item->hostid);
		}

		if (0 == query->hostids.values_num)
			continue;

		zbx_vector_uint64_sort(&query->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&query->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (j = 0; j < query->hostids.values_num; j++)
		{
			zbx_host_event_maintenance_t	hem_local = {.hostid = query->hostids.values[j]};

			zbx_hashset_insert(&host_event_maintenances, &hem_local, sizeof(hem_local));
		}
	}

	zbx_hashset_iter_reset(&host_event_maintenances, &iter);
	while (NULL != (host_event_maintenance = (zbx_host_event_maintenance_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_ptr_create(&host_event_maintenance->maintenances);
		zbx_vector_ptr_reserve(&host_event_maintenance->maintenances, (size_t)maintenanceids->values_num);
	}

	dc_get_host_maintenances_by_ids(maintenanceids, &host_event_maintenances, dc_assign_event_maintenance_to_host);

	if (0 == host_event_maintenances.num_data)
		goto unlock;

	zbx_hashset_iter_reset(&host_event_maintenances, &iter);
	while (NULL != (host_event_maintenance = (zbx_host_event_maintenance_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_ptr_sort(&host_event_maintenance->maintenances, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_ptr_uniq(&host_event_maintenance->maintenances, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	for (i = 0; i < event_queries->values_num; i++)
	{
		query = (zbx_event_suppress_query_t *)event_queries->values[i];

		/* find matching maintenances */
		for (j = 0; j < query->hostids.values_num; j++)
		{
			const zbx_dc_maintenance_t	*maintenance;

			if (NULL == (host_event_maintenance = zbx_hashset_search(&host_event_maintenances,
					&query->hostids.values[j])))
			{
				continue;
			}

			for (k = 0; k < host_event_maintenance->maintenances.values_num; k++)
			{
				zbx_uint64_pair_t	pair;

				maintenance = (zbx_dc_maintenance_t *)host_event_maintenance->maintenances.values[k];

				if (ZBX_MAINTENANCE_RUNNING != maintenance->state)
					continue;

				pair.first = maintenance->maintenanceid;

				if (FAIL != zbx_vector_uint64_pair_search(&query->maintenances, pair,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					continue;
				}

				if (SUCCEED != dc_maintenance_match_tags(maintenance, &query->tags))
					continue;

				pair.second = maintenance->running_until;
				zbx_vector_uint64_pair_append(&query->maintenances, pair);
				ret = SUCCEED;
			}
		}
	}
unlock:
	UNLOCK_CACHE;

	zbx_hashset_destroy(&host_event_maintenances);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free event suppress query structure                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_suppress_query_free(zbx_event_suppress_query_t *query)
{
	zbx_vector_uint64_destroy(&query->hostids);
	zbx_vector_uint64_destroy(&query->functionids);
	zbx_vector_uint64_pair_destroy(&query->maintenances);
	zbx_vector_tags_ptr_clear_ext(&query->tags, zbx_free_tag);
	zbx_vector_tags_ptr_destroy(&query->tags);
	zbx_free(query);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get identifiers of the running maintenances                       *
 *                                                                            *
 * Return value: SUCCEED - at least one running maintenance was found         *
 *               FAIL    - no running maintenances were found                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_running_maintenanceids(zbx_vector_uint64_t *maintenanceids)
{
	zbx_dc_maintenance_t	*maintenance;
	zbx_hashset_iter_t	iter;

	RDLOCK_CACHE;

	zbx_hashset_iter_reset(&(get_dc_config())->maintenances, &iter);
	while (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_MAINTENANCE_RUNNING == maintenance->state)
			zbx_vector_uint64_append(maintenanceids, maintenance->maintenanceid);
	}

	UNLOCK_CACHE;

	return (0 != maintenanceids->values_num ? SUCCEED : FAIL);
}

#ifdef HAVE_TESTS
#	include "../../../tests/libs/zbxcacheconfig/dbconfig_maintenance_test.c"
#endif
