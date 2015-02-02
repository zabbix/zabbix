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

#include "db.h"
#include "log.h"
#include "mutexs.h"

#define LOCK_ITSERVICES		zbx_mutex_lock(&itservices_lock)
#define UNLOCK_ITSERVICES	zbx_mutex_unlock(&itservices_lock)

static ZBX_MUTEX	itservices_lock;

/* status update queue items */
typedef struct
{
	/* the update source id */
	zbx_uint64_t	sourceid;
	/* the new status */
	int		status;
	/* timestamp */
	int		clock;
}
zbx_status_update_t;

/* IT service node */
typedef struct
{
	/* service id */
	zbx_uint64_t		serviceid;
	/* trigger id of leaf nodes */
	zbx_uint64_t		triggerid;
	/* the initial service status */
	int			old_status;
	/* the calculated service status */
	int			status;
	/* the service status calculation algorithm, see SERVICE_ALGORITHM_* defines */
	int			algorithm;
	/* the parent nodes */
	zbx_vector_ptr_t	parents;
	/* the child nodes */
	zbx_vector_ptr_t	children;
}
zbx_itservice_t;

/* index of services by triggerid */
typedef struct
{
	zbx_uint64_t		triggerid;
	zbx_vector_ptr_t	itservices;
}
zbx_itservice_index_t;

/* a set of IT services used during update session                          */
/*                                                                          */
/* All services are stored into hashset accessed by serviceid. The services */
/* also are indexed by triggerid.                                           */
/* The following types of services are loaded during update session:        */
/*  1) services directly linked to the triggers with values changed         */
/*     during update session.                                               */
/*  2) direct or indirect parent services of (1)                            */
/*  3) services required to calculate status of (2) and not already loaded  */
/*     as (1) or (2).                                                       */
/*                                                                          */
/* In this schema:                                                          */
/*   (1) can't have children services                                       */
/*   (2) will have children services                                        */
/*   (1) and (2) will have parent services unless it's the root service     */
/*   (3) will have neither children or parent services                      */
/*                                                                          */
typedef struct
{
	/* loaded IT services */
	zbx_hashset_t	itservices;
	/* service index by triggerid */
	zbx_hashset_t	index;
}
zbx_itservices_t;

/******************************************************************************
 *                                                                            *
 * Function: its_itservices_init                                              *
 *                                                                            *
 * Purpose: initializes IT services data set to store services during update  *
 *          session                                                           *
 *                                                                            *
 * Parameters: set   - [IN] the data set to initialize                        *
 *                                                                            *
 ******************************************************************************/
static void	its_itservices_init(zbx_itservices_t *itservices)
{
	zbx_hashset_create(&itservices->itservices, 512, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&itservices->index, 128, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: its_itservices_clean                                             *
 *                                                                            *
 * Purpose: cleans IT services data set by releasing allocated memory         *
 *                                                                            *
 * Parameters: set   - [IN] the data set to clean                             *
 *                                                                            *
 ******************************************************************************/
static void	its_itservices_clean(zbx_itservices_t *itservices)
{
	zbx_hashset_iter_t	iter;
	zbx_itservice_t		*itservice;
	zbx_itservice_index_t	*index;

	zbx_hashset_iter_reset(&itservices->index, &iter);

	while (NULL != (index = zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_destroy(&index->itservices);

	zbx_hashset_destroy(&itservices->index);

	zbx_hashset_iter_reset(&itservices->itservices, &iter);

	while (NULL != (itservice = zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_ptr_destroy(&itservice->children);
		zbx_vector_ptr_destroy(&itservice->parents);
	}

	zbx_hashset_destroy(&itservices->itservices);
}

/******************************************************************************
 *                                                                            *
 * Function: its_itservice_create                                             *
 *                                                                            *
 * Purpose: creates a new IT service node                                     *
 *                                                                            *
 * Parameters: itservices  - [IN] the IT services data                        *
 *             serviceid   - [IN] the service id                              *
 *             algorithm   - [IN] the service status calculation mode         *
 *             triggerid   - [IN] the source trigger id for leaf nodes        *
 *             status      - [IN] the initial service status                  *
 *                                                                            *
 * Return value: the created IT service node                                  *
 *                                                                            *
 ******************************************************************************/
static zbx_itservice_t	*its_itservice_create(zbx_itservices_t *itservices, zbx_uint64_t serviceid,
		zbx_uint64_t triggerid, int status, int algorithm)
{
	zbx_itservice_t		itservice = {serviceid, triggerid, status, status, algorithm}, *pitservice;
	zbx_itservice_index_t	*pindex;

	zbx_vector_ptr_create(&itservice.children);
	zbx_vector_ptr_create(&itservice.parents);

	pitservice = zbx_hashset_insert(&itservices->itservices, &itservice, sizeof(itservice));

	if (0 != triggerid)
	{
		if (NULL == (pindex = zbx_hashset_search(&itservices->index, &triggerid)))
		{
			zbx_itservice_index_t	index = {triggerid};

			zbx_vector_ptr_create(&index.itservices);

			pindex = zbx_hashset_insert(&itservices->index, &index, sizeof(index));
		}

		zbx_vector_ptr_append(&pindex->itservices, pitservice);
	}

	return pitservice;
}

/******************************************************************************
 *                                                                            *
 * Function: its_updates_append                                               *
 *                                                                            *
 * Purpose: adds an update to the queue                                       *
 *                                                                            *
 * Parameters: updates   - [OUT] the update queue                             *
 *             sourceid  - [IN] the update source id                          *
 *             status    - [IN] the update status                             *
 *             clock     - [IN] the update timestamp                          *
 *                                                                            *
 ******************************************************************************/
static void	its_updates_append(zbx_vector_ptr_t *updates, zbx_uint64_t sourceid, int status, int clock)
{
	zbx_status_update_t	*update;

	update = zbx_malloc(NULL, sizeof(zbx_status_update_t));

	update->sourceid = sourceid;
	update->status = status;
	update->clock = clock;

	zbx_vector_ptr_append(updates, update);
}

static void	zbx_status_update_free(zbx_status_update_t *update)
{
	zbx_free(update);
}

/******************************************************************************
 *                                                                            *
 * Function: its_itservices_load_children                                     *
 *                                                                            *
 * Purpose: loads all missing children of the specified services              *
 *                                                                            *
 * Parameters: itservices   - [IN] the IT services data                       *
 *                                                                            *
 ******************************************************************************/
static void	its_itservices_load_children(zbx_itservices_t *itservices)
{
	const char		*__function_name = "its_itservices_load_children";

	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_itservice_t		*itservice, *parent;
	zbx_uint64_t		serviceid, parentid;
	zbx_vector_uint64_t	serviceids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&serviceids);

	zbx_hashset_iter_reset(&itservices->itservices, &iter);

	while (NULL != (itservice = zbx_hashset_iter_next(&iter)))
	{
		if (0 == itservice->triggerid)
			zbx_vector_uint64_append(&serviceids, itservice->serviceid);
	}

	/* check for extreme case when there are only leaf nodes */
	if (0 == serviceids.values_num)
		goto out;

	zbx_vector_uint64_sort(&serviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select s.serviceid,s.status,s.algorithm,sl.serviceupid"
			" from services s,services_links sl"
			" where s.serviceid=sl.servicedownid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "sl.serviceupid", serviceids.values,
			serviceids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);
		ZBX_STR2UINT64(parentid, row[3]);

		if (NULL == (parent = zbx_hashset_search(&itservices->itservices, &parentid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		if (NULL == (itservice = zbx_hashset_search(&itservices->itservices, &serviceid)))
			itservice = its_itservice_create(itservices, serviceid, 0, atoi(row[1]), atoi(row[2]));

		if (FAIL == zbx_vector_ptr_search(&parent->children, itservice, ZBX_DEFAULT_PTR_COMPARE_FUNC))
			zbx_vector_ptr_append(&parent->children, itservice);
	}
	DBfree_result(result);

	zbx_free(sql);

	zbx_vector_uint64_destroy(&serviceids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: its_itservices_load_parents                                      *
 *                                                                            *
 * Purpose: recursively loads parent nodes of the specified service until the *
 *          root node                                                         *
 *                                                                            *
 * Parameters: itservices   - [IN] the IT services data                       *
 *             serviceids   - [IN] a vector containing ids of services to     *
 *                                 load parents                               *
 *                                                                            *
 ******************************************************************************/
static void	its_itservices_load_parents(zbx_itservices_t *itservices, zbx_vector_uint64_t *serviceids)
{
	const char	*__function_name = "its_itservices_load_parents";

	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_itservice_t	*parent, *itservice;
	zbx_uint64_t	parentid, serviceid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_sort(serviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(serviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select s.serviceid,s.status,s.algorithm,sl.servicedownid"
			" from services s,services_links sl"
			" where s.serviceid=sl.serviceupid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "sl.servicedownid", serviceids->values,
			serviceids->values_num);

	serviceids->values_num = 0;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(parentid, row[0]);
		ZBX_STR2UINT64(serviceid, row[3]);

		/* find the service */
		if (NULL == (itservice = zbx_hashset_search(&itservices->itservices, &serviceid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		/* find/load the parent service */
		if (NULL == (parent = zbx_hashset_search(&itservices->itservices, &parentid)))
		{
			parent = its_itservice_create(itservices, parentid, 0, atoi(row[1]), atoi(row[2]));
			zbx_vector_uint64_append(serviceids, parent->serviceid);
		}

		/* link the service as a parent's child */
		if (FAIL == zbx_vector_ptr_search(&itservice->parents, parent, ZBX_DEFAULT_PTR_COMPARE_FUNC))
			zbx_vector_ptr_append(&itservice->parents, parent);
	}
	DBfree_result(result);

	zbx_free(sql);

	if (0 != serviceids->values_num)
		its_itservices_load_parents(itservices, serviceids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: its_load_services_by_triggerids                                  *
 *                                                                            *
 * Purpose: loads services that might be affected by the specified triggerid  *
 *          or are required to calculate status of loaded services            *
 *                                                                            *
 * Parameters: itservices - [IN] the IT services data                         *
 *             triggerids - [IN] the sorted list of trigger ids               *
 *                                                                            *
 ******************************************************************************/
static void	its_load_services_by_triggerids(zbx_itservices_t *itservices, const zbx_vector_uint64_t *triggerids)
{
	const char		*__function_name = "its_load_services_by_triggerids";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		serviceid, triggerid;
	zbx_itservice_t		*itservice;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	serviceids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&serviceids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select serviceid,triggerid,status,algorithm"
			" from services"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids->values, triggerids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);
		ZBX_STR2UINT64(triggerid, row[1]);

		itservice = its_itservice_create(itservices, serviceid, triggerid, atoi(row[2]), atoi(row[3]));

		zbx_vector_uint64_append(&serviceids, itservice->serviceid);
	}
	DBfree_result(result);

	if (0 != serviceids.values_num)
	{
		its_itservices_load_parents(itservices, &serviceids);
		its_itservices_load_children(itservices);
	}

	zbx_vector_uint64_destroy(&serviceids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: its_itservice_update_status                                      *
 *                                                                            *
 * Purpose: updates service and its parents statuses                          *
 *                                                                            *
 * Parameters: service    - [IN] the service to update                        *
 *             clock      - [IN] the update timestamp                         *
 *             alarms     - [OUT] the alarms update queue                     *
 *                                                                            *
 * Comments: This function recalculates service status according to the       *
 *           algorithm and status of the children services. If the status     *
 *           has been changed, an alarm is generated and parent services      *
 *           (up until the root service) are updated too.                     *
 *                                                                            *
 ******************************************************************************/
static void	its_itservice_update_status(zbx_itservice_t *itservice, int clock, zbx_vector_ptr_t *alarms)
{
	int	status, i;

	switch (itservice->algorithm)
	{
		case SERVICE_ALGORITHM_MIN:
			status = TRIGGER_SEVERITY_COUNT;
			for (i = 0; i < itservice->children.values_num; i++)
			{
				zbx_itservice_t	*child = (zbx_itservice_t *)itservice->children.values[i];

				if (child->status < status)
					status = child->status;
			}
			break;
		case SERVICE_ALGORITHM_MAX:
			status = 0;
			for (i = 0; i < itservice->children.values_num; i++)
			{
				zbx_itservice_t	*child = (zbx_itservice_t *)itservice->children.values[i];

				if (child->status > status)
					status = child->status;
			}
			break;
		case SERVICE_ALGORITHM_NONE:
			goto out;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unknown calculation algorithm of service status [%d]",
					itservice->algorithm);
			goto out;
	}

	if (itservice->status != status)
	{
		itservice->status = status;

		its_updates_append(alarms, itservice->serviceid, status, clock);

		/* update parent services */
		for (i = 0; i < itservice->parents.values_num; i++)
			its_itservice_update_status(itservice->parents.values[i], clock, alarms);
	}
out:
	;
}

/******************************************************************************
 *                                                                            *
 * Function: its_updates_compare                                              *
 *                                                                            *
 * Purpose: used to sort service updates by source id                         *
 *                                                                            *
 ******************************************************************************/
static int	its_updates_compare(const zbx_status_update_t **update1, const zbx_status_update_t **update2)
{
	ZBX_RETURN_IF_NOT_EQUAL((*update1)->sourceid, (*update2)->sourceid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: its_write_status_and_alarms                                      *
 *                                                                            *
 * Purpose: writes service status changes and generated service alarms into   *
 *          database                                                          *
 *                                                                            *
 * Parameters: itservices - [IN] the IT services data                         *
 *             alarms     - [IN] the service alarms update queue              *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	its_write_status_and_alarms(zbx_itservices_t *itservices, zbx_vector_ptr_t *alarms)
{
	int			i, ret = FAIL;
	zbx_vector_ptr_t	updates;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		alarmid;
	zbx_hashset_iter_t	iter;
	zbx_itservice_t		*itservice;

	/* get a list of service status updates that must be written to database */
	zbx_vector_ptr_create(&updates);
	zbx_hashset_iter_reset(&itservices->itservices, &iter);

	while (NULL != (itservice = zbx_hashset_iter_next(&iter)))
	{
		if (itservice->old_status != itservice->status)
			its_updates_append(&updates, itservice->serviceid, itservice->status, 0);
	}

	/* write service status changes into database */
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (0 != updates.values_num)
	{
		zbx_vector_ptr_sort(&updates, (zbx_compare_func_t)its_updates_compare);
		zbx_vector_ptr_uniq(&updates, (zbx_compare_func_t)its_updates_compare);

		for (i = 0; i < updates.values_num; i++)
		{
			zbx_status_update_t	*update = updates.values[i];

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update services"
					" set status=%d"
					" where serviceid=" ZBX_FS_UI64 ";\n",
					update->status, update->sourceid);

			if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				goto out;
		}
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;

	/* write generated service alarms into database */
	if (0 != alarms->values_num)
	{
		zbx_db_insert_t	db_insert;

		alarmid = DBget_maxid_num("service_alarms", alarms->values_num);

		zbx_db_insert_prepare(&db_insert, "service_alarms", "servicealarmid", "serviceid", "value", "clock",
				NULL);

		for (i = 0; i < alarms->values_num; i++)
		{
			zbx_status_update_t	*update = alarms->values[i];

			zbx_db_insert_add_values(&db_insert, alarmid++, update->sourceid, update->status,
					update->clock);
		}

		ret = zbx_db_insert_execute(&db_insert);

		zbx_db_insert_clean(&db_insert);
	}
out:
	zbx_free(sql);

	zbx_vector_ptr_clean(&updates, (zbx_mem_free_func_t)zbx_status_update_free);
	zbx_vector_ptr_destroy(&updates);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: its_flush_updates                                                *
 *                                                                            *
 * Purpose: processes the service update queue                                *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The following steps are taken to process the queue:              *
 *           1) Load all services either directly referenced (with triggerid) *
 *              by update queue or dependent on those services (directly or   *
 *              indirectly) or required to calculate status of any loaded     *
 *              services.                                                     *
 *           2) Apply updates to the loaded service tree. Queue new service   *
 *              alarms whenever service status changes.                       *
 *           3) Write the final service status changes and the generated      *
 *              service alarm queue into database.                            *
 *                                                                            *
 ******************************************************************************/
static int	its_flush_updates(zbx_vector_ptr_t *updates)
{
	const char		*__function_name = "its_flush_updates";

	int			i, j, k, ret = FAIL;
	zbx_status_update_t	*update;
	zbx_itservices_t	itservices;
	zbx_vector_ptr_t	alarms;
	zbx_itservice_index_t	*index;
	zbx_vector_uint64_t	triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	its_itservices_init(&itservices);

	zbx_vector_uint64_create(&triggerids);

	for (i = 0; i < updates->values_num; i++)
	{
		update = (zbx_status_update_t *)updates->values[i];

		zbx_vector_uint64_append(&triggerids, update->sourceid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* load all services affected by the trigger status change and      */
	/* the services that are required for resulting status calculations */
	its_load_services_by_triggerids(&itservices, &triggerids);

	zbx_vector_uint64_destroy(&triggerids);

	if (0 == itservices.itservices.num_data)
	{
		ret = SUCCEED;
		goto out;
	}

	zbx_vector_ptr_create(&alarms);

	/* apply status updates */
	for (i = 0; i < updates->values_num; i++)
	{
		update = updates->values[i];

		if (NULL == (index = zbx_hashset_search(&itservices.index, update)))
			continue;

		/* change the status of services based on the update */
		for (j = 0; j < index->itservices.values_num; j++)
		{
			zbx_itservice_t	*itservice = (zbx_itservice_t *)index->itservices.values[j];

			if (SERVICE_ALGORITHM_NONE == itservice->algorithm || itservice->status == update->status)
				continue;

			its_updates_append(&alarms, itservice->serviceid, update->status, update->clock);
			itservice->status = update->status;
		}

		/* recalculate status of the parent services */
		for (j = 0; j < index->itservices.values_num; j++)
		{
			zbx_itservice_t	*itservice = (zbx_itservice_t *)index->itservices.values[j];

			/* update parent services */
			for (k = 0; k < itservice->parents.values_num; k++)
				its_itservice_update_status(itservice->parents.values[k], update->clock, &alarms);
		}
	}

	ret = its_write_status_and_alarms(&itservices, &alarms);

	zbx_vector_ptr_clean(&alarms, (zbx_mem_free_func_t)zbx_status_update_free);
	zbx_vector_ptr_destroy(&alarms);
out:
	its_itservices_clean(&itservices);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * Public API
 */

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_itservices                                              *
 *                                                                            *
 * Purpose: updates IT services by applying event list                        *
 *                                                                            *
 * Return value: SUCCEED - the IT services were updated successfully          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	DBupdate_itservices(const DB_EVENT *events, size_t events_num)
{
	const char		*__function_name = "DBupdate_itservices";

	int			i, ret = SUCCEED;
	zbx_vector_ptr_t	updates;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&updates);

	for (i = 0; i < events_num; i++)
	{
		if (EVENT_SOURCE_TRIGGERS != events[i].source)
			continue;

		its_updates_append(&updates, events[i].objectid, TRIGGER_VALUE_PROBLEM == events[i].value ?
				events[i].trigger.priority : 0, events[i].clock);
	}

	if (0 != updates.values_num)
	{
		LOCK_ITSERVICES;

		ret = its_flush_updates(&updates);

		UNLOCK_ITSERVICES;

		zbx_vector_ptr_clean(&updates, free);
	}

	zbx_vector_ptr_destroy(&updates);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBremove_itservice_triggers                                      *
 *                                                                            *
 * Purpose: removes specified trigger ids from dependent services and reset   *
 *          the status of those services to the default value (0)             *
 *                                                                            *
 * Parameters: triggerids     - [IN] an array of trigger ids to remove        *
 *             triggerids_num - [IN] the number of items in triggerids array  *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	DBremove_triggers_from_itservices(zbx_uint64_t *triggerids, int triggerids_num)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_ptr_t	updates;
	int			i, ret = FAIL, now;

	if (0 == triggerids_num)
		return SUCCEED;

	now = time(NULL);

	zbx_vector_ptr_create(&updates);

	for (i = 0; i < triggerids_num; i++)
		its_updates_append(&updates, triggerids[i], 0, now);

	LOCK_ITSERVICES;

	if (FAIL == its_flush_updates(&updates))
		goto out;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"update services set triggerid=null,showsla=0 where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids, triggerids_num);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);
out:
	UNLOCK_ITSERVICES;

	zbx_vector_ptr_clean(&updates, (zbx_mem_free_func_t)zbx_status_update_free);
	zbx_vector_ptr_destroy(&updates);

	return ret;
}

void	zbx_create_itservices_lock()
{
	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&itservices_lock, ZBX_MUTEX_ITSERVICES))
	{
		zbx_error("cannot create mutex for IT services");
		exit(FAIL);
	}
}

void	zbx_destroy_itservices_lock()
{
	zbx_mutex_destroy(&itservices_lock);
}
